#!/usr/bin/php -q
<?php
/*  crons/reviews.php
    
    EXECUTIVE SUMMARY: send email to employees (before v2020-3, strictly EORs, engineers of record) letting them
    know what workOrders are on hold awaiting their review.
    
    Email to the employee person in question (and also to EMAIL_DEV) lists:    
        * workOrder description
        * workOrder status (added v2020-3)
        * URL for workOrder (always on production system even when this is run in dev/test)
        * Job Number
        * job name
        * list of EORs
        * list of Staff Engineers
        * list of Lead Engineers
        * list of Support Engineers     
*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1589574751', "crons/reviews.php must be run from the command line, was apparently accessed some other way.");
	die();
}

$reconstructed_cmd = 'php';
for ($i=0; $i<count($argv); ++$i) {
    $reconstructed_cmd .= ' ';
    $reconstructed_cmd .= $argv[$i]; 
}

// Critical logging will happen in any case, but does the caller want more?
$logging = false; 
foreach ($argv as $i => $value) {
    if ($value == '-log') {
        $logging = true;
        array_splice($argv, $i, 1); // remove that
        $logger->info2('1589574768', "start crons/reviews.php: $reconstructed_cmd");        
        break;
    }
}
unset($value, $i);

$db = DB::getInstance();

/* BEGIN REPLACED 2020-06-11 JM 
// Select all rows from DB table workOrder where the workOrder is on hold.
//  these are ordered by when they received that status, earliest first.

$query = "SELECT wo.* ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
$query .= "JOIN " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderId = wos.workOrderId ";
$query .= "LEFT JOIN  " . DB__NEW_DATABASE . ".workOrderStatusTime wost on wo.workOrderStatusTimeId = wost.workOrderStatusTimeId ";
$query .= "WHERE wost.workOrderStatusId = " . intval(STATUS_WORKORDER_HOLD) . " ";
$query .= "ORDER BY wost.inserted desc;";
// END REPLACED 2020-06-11 JM
*/
// BEGIN REPLACEMENT 2020-06-11 JM
$query = "SELECT wo.workOrderId, cp.customerPersonId ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
$query .= "JOIN " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderId = wos.workOrderId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatusTime wost on wo.workOrderStatusTimeId = wost.workOrderStatusTimeId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".wostCustomerPerson wostcp on wo.workOrderStatusTimeId = wostcp.workOrderStatusTimeId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".customerPerson cp on cp.customerPersonId = wostcp.customerPersonId ";
$query .= "WHERE cp.customerId = " . intval($customer->getCustomerId()) . " "; // current customer only
$query .= "AND cp.personId = " . intval($user->getUserId()) . " "; // current user only
$query .= "ORDER BY wost.inserted DESC;";
// END REPLACEMENT 2020-06-11 JM

$workOrders = array(); // maybe a slightly misleading variable name: just a workOrderId and a customerPerson to notify

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589574801', "Hard DB error", $db);
    die();
}

while ($row = $result->fetch_assoc()) {				
    $workOrders[] = $row;
}

/* BEGIN REPLACED 2020-06-08 JM; much simplified as we rework workOrderStatus
// $eor_extra_flag comes from inc/config.php.
$workOrdersForEors = array();
foreach ($eor_extra_flag AS $eor => $flag) {
    $workOrdersForEors[$eor] = array();
}

foreach ($workOrders as $workOrder) {
	$wo = new WorkOrder($workOrder['workOrderId']);
	$data = $wo->getStatusData();
	$j = new Job($wo->getJobId());
		
	if (isset($data['extra'])) {
		if (intval($data['extra'])) {			
			$extraEor = 0;				
			if (isset($customerPerson['extraEor'])) {					
				$extraEor = intval($customerPerson['extraEor']);					
			}
			
            // Get the eor flags from the associative array defined in /inc/config.php into a normal array
            $eors = array();
            foreach ($eor_extra_flag AS $flag) {
                $eors[] = $flag;
            }
			
			foreach ($eors as $bit) {
				if ($bit & $data['extra']) {					
					foreach ($eor_extra_flag AS $eor => $flag) {
					    if ($bit == $flag) {
					        $workOrdersForEors[$eor][] = $wo;
					    }
					}
				} // END if ($bit & $data['extra']){			
			} // END foreach ($eors...
		} // END if (intval($data['extra'])) {
	} // END if (isset($data['extra']))
} // END foreach ($workOrders...
// END REPLACED 2020-06-08 JM
*/
// BEGIN REPLACEMENT 2020-06-08 JM; further simplified 2020-06-10
$employees = CustomerPerson::getAll(true); // true => current employees only, I think in this context that is correct - JM 2020-06-08  

// Beginning with v2020-3, this name is a bit misleading: we can have any customerPerson here, not just EORs
$workOrdersForEors = array();
foreach ($employees AS $employee) {
    $workOrdersForEors[$employee->getCustomerPersonId()] = array();
}

/* FIRST APPROACH
foreach ($workOrders as $workOrder) {
	$wo = new WorkOrder($workOrder['workOrderId']);
	$statusData = $wo->getStatusData();
	
    foreach ($statusData['customerPersonArray'] as $customerPersonData) {
        $workOrdersForEors[$customerPersonData['customerPersonId']][] = $wo;
	}
}
BUT AFTER 2020-06-11 this got even simpler:
*/
foreach ($workOrders as $workOrder) {
    $workOrder['customerPersonId'][] = new WorkOrder($workOrder['workOrderId']);
}

// END REPLACEMENT 2020-06-08 JM
// There are also some changes below, but they are smaller & I haven't tracked them here - JM

foreach ($workOrdersForEors as $customerPersonId => $workOrdersForEor) {	
	if (count($workOrdersForEor)) {		
		// $body = "You have one or more workorders on hold:\n\n";  // reworded 2020-06-11 JM, not going to be just about hold
		$body = "You have one or more workorders on hold or otherwise awaiting attention:\n\n";
		
		foreach ($workOrdersForEor as $workOrder) {		    
		    $staffEngineers = $workOrder->getTeamPosition(TEAM_POS_ID_STAFF_ENG, 0);
		    $eors = $workOrder->getTeamPosition(TEAM_POS_ID_EOR, 0);
		    $leadEngineers = $workOrder->getTeamPosition(TEAM_POS_ID_LEADENGINEER, 0);
		    $supportEngineers = $workOrder->getTeamPosition(TEAM_POS_ID_SUPPORTENGINEER, 0);
		    
		    $jj = new Job($workOrder->getJobId());
		    
		    $body .= "============================================================\n";
		    $body .= "============================================================\n\n";
		    $body .= $workOrder->getDescription() . "\n\n";
		    // BEGIN ADDED 2020-06-11 JM
		    $body .= "Status: $workOrder->getStatusName()\n";
		    // END ADDED 2020-06-11 JM
			// Apparently, this path goes to the production domain even if we are on the dev or test system.
			$body .= 'http://'.PRODUCTION_DOMAIN.'/workorder/' . $workOrder->getWorkOrderId();
			$body .= "\n\n";
			$body .= "Job Number : " . $jj->getNumber() . "\n";
			$body .= "Job Name : " . $jj->getName() . "\n\n";

			$body .= "\tEORs\n";
			$body .= "\t-----------------\n";
			foreach ($eors as $ekey => $engineer) {
			    $cp = new CompanyPerson($engineer['companyPersonId']);
			    $p = new Person($cp->getPersonId());
			    if ($ekey) {
			        // not the first
			        $body .= "\n";
			    }
			    $body .= "\t" . $p->getFormattedName(1);
			}
			
			$body .= "\n\n"; 
			$body .= "\tStaff Engineers\n";
			$body .= "\t-----------------\n";
			foreach ($staffEngineers as $ekey => $engineer) {
			    $cp = new CompanyPerson($engineer['companyPersonId']);
			    $p = new Person($cp->getPersonId());
			    if ($ekey) {
			        // not the first
			        $body .= "\n";
			    }
			    $body .= "\t" . $p->getFormattedName(1);
			}
			
			$body .= "\n\n";			
			
			$body .= "\tLead Engineers\n";
			$body .= "\t-----------------\n";
			foreach ($leadEngineers as $ekey => $engineer) {
			    $cp = new CompanyPerson($engineer['companyPersonId']);
			    $p = new Person($cp->getPersonId());
			    if ($ekey) {
			        // not the first
			        $body .= "\n";
			    }
			    $body .= "\t" . $p->getFormattedName(1);
			}
			
			$body .= "\n\n";
			
			$body .= "\tSupport Engineers\n";
			$body .= "\t-----------------\n";
			foreach ($supportEngineers as $ekey => $engineer) {
			    $cp = new CompanyPerson($engineer['companyPersonId']);
			    $p = new Person($cp->getPersonId());
			    if ($ekey) {
			        // not the first
			        $body .= "\n";
			    }
			    $body .= "\t" . $p->getFormattedName(1);
			}
			
			$body .= "\n\n\n";
		}
		
		$body .= "\n\nThis was an auto generated email.";

		$address_mail_to = ''; 
		$mail = new SSSMail();
        $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);

        // Find the matching customerPerson so we can give their name.
        /* BEGIN REPLACED 2020-06-09 JM  
        foreach ($eor_email_address AS $eor => $eor_email) {
            if ($customerPersonId == $eor){
                // uppercase the first letter of the EOR's name
                $uppercased_name = strtoupper(substr($eor, 0, 1)) . substr($eor, 1);   
                
                $mail->addTo($eor_email, $uppercased_name);
                $mail->addTo(EMAIL_DEV, $uppercased_name);
                
                if ($address_mail_to) {
                    $address_mail_to .= '; ';
                }
                $address_mail_to .= "$eor_email <$uppercased_name>, " . EMAIL_DEV . "<$uppercased_name>";
            }
        }
        // END REPLACED 2020-06-09 JM
        */
        // BEGIN REPLACEMENT 2020-06-09 JM
        $customerPerson = new CustomerPerson($customerPersonId);
        if ($customerPerson->getCustomer() == $customer) { // $customer is set in inc/config.php
            list($target_email_address, $firstName, $lastName) = $customerPerson->getEmailAndName();
            if ($target_email_address) {
                $mail->addTo($target_email_address, $firstName);
            } else {
                $logger->error2('1591721961', "Cannot find email address for $firstName (personId = $personId) at customer " . $customer.getCustomerId());
            }
            $mail->addTo(EMAIL_DEV, $firstName);
                
            if ($address_mail_to) {
                $address_mail_to .= '; ';
            }
            $address_mail_to .= "$use_email_address <$firstName>, " . EMAIL_DEV . "<$firstName>";
        } else {
            $logger->error2('1591721951', "customerPersonId $customerPersonId does not correspond to the current customer! Won't be sending email.");
        }
        
        
        // END REPLACEMENT 2020-06-09 JM
		
		$mail->setSubject('Workorder(s) needing review');
		$mail->setBodyText($body);
		$mail_succeeded = $mail->send();
		if ($mail_succeeded) {
			if ($logging) {
			    $logger->info2('1589574839', "Sent mail to $address_mail_to.");
			}			
		} else {
			if ($logging) {
			    $logger->error2('1589574869', "FAILED TO SEND mail to $address_mail_to.");
			}			
		}
	}
}

if ($logging) {
    $logger->info2('1589574829', "crons/reviews.php succeeded.");
}

?>
