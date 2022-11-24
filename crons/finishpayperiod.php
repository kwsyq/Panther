#!/usr/bin/php -q
<?php 
/*  crons/finishpayperiod.php

    EXECUTIVE SUMMARY: cron job to (1) send an email reminding all active employees that it is time to sign 
    off their time for a pay period that either is about to come to a close or already has and (2) mark that
    appropriately in the database.

    Invoked as 'php finishpayperiod.php DATE' where DATE is the day when the pay period *began*, e.g.
     * php finishpayperiod.php '2020-09-01'
     * php finishpayperiod.php '2020-09-16'
    Note that all pay periods start on either the first or sixteenth day of the month. 
    >>>00004 This probably would need to be more flexible if we roll this out to other customers.
    
    Optional '-log'.
    Optional '-nomail' added 2020-10-20 JM, suppresses sending any email. Useful for running this by hand just to update DB.

*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1593533867', "crons/finishpayperiod.php must be run from the command line, was apparently accessed some other way.");
	die();
}

$reconstructed_cmd = 'php';
for ($i=0; $i<count($argv); ++$i) {
    $reconstructed_cmd .= ' ';
    $reconstructed_cmd .= $argv[$i];
}

// Critical logging will happen in any case, but does the caller want more?
$logging = false;
$sendingMail = true;  // ADDED 2020-10-20 JM


// foreach ($argv as $i => $value) { // REPLACED 2020-10-20 JM
// BEGIN REPLACEMENT  2020-10-20 JM
// Loop backward because we are splicing out values
for ($i = count($argv)-1; $i>=0; --$i) {
    $value = $argv[$i]; 
// END REPLACEMENT  2020-10-20 JM
    if ($value == '-log') {
        $logging = true;
        array_splice($argv, $i, 1); // remove that
        $logger->info2('1593533900', "start crons/finishpayperiod.php: $reconstructed_cmd");        
    }
    if ($value == '-nomail') {
        $sendingMail = false;
        array_splice($argv, $i, 1); // remove that
    }
}
unset($value, $i);

if (count($argv) != 2) {
    $logger->error2('1593533948', "crons/finishpayperiod.php: wrong number of arguments. Expect start date of pay period (e.g. '2020-09-16') and optional '-log'. Got $reconstructed_cmd");
	die();
}

$periodBegin = $argv[1];

$periodBegin_dt = DateTime::createFromFormat("Y-m-d", $periodBegin);
if ($periodBegin_dt === false || array_sum($periodBegin_dt::getLastErrors())) {
    $logger->error2('1593534013', "crons/finishpayperiod.php: invalid date. Expect start date of pay period (e.g. '2020-09-16') and optional '-log'. Got $reconstructed_cmd");
    die();
}

// SSS pay periods always begin on 1st or 16th of month.
$day_of_month_begin = intval($periodBegin_dt->format('d'));
if ($day_of_month_begin != 1 && $day_of_month_begin != 16) {
    $logger->error2('1593534018', "crons/finishpayperiod.php: not start of a pay period, which must be the 1st or 16th. Expect start date of pay period (e.g. '2020-09-16') and optional '-log'. Got $reconstructed_cmd");
	die();
}

// >>>00004 this may have to be revisited for any dev/test systems that aren't specific to SSS, 
//  or we may need to revisit our notion of PRODUCTION_DOMAIN as a way to determine customer:
// >>>00004 JM suspects 2019-10-04 that we might want to set $domain as a global in inc/config.php
//  (currently we deliberately unset it) and use that here. That would probably be best.
$domain = PRODUCTION_DOMAIN;

$customer = new Customer($domain);
$employees = $customer->getEmployees(1); // current employees

$db = DB::getInstance();

// Make sure there is at least one existing row for this pay period
$query = "SELECT customerPersonPayPeriodInfoId FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
$query .= "WHERE periodBegin = '" . $db->real_escape_string($periodBegin)  . "' ";
$query .= "LIMIT 1;";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1593534033', "Hard DB error", $db);
    die();
}
if ($result->num_rows == 0) {
    $logger->info2('1593534037', 'Command was "' . $reconstructed_cmd . '" but we have rows in customerPersonPayPeriodInfo for periodBegin="' . $periodBegin . '".');
    die();
}

if ($logging) {
    $logger->info2('1593534049', 'Finishing pay period for '. count($employees) . " employees, for the period beginning $periodBegin");
}

$month = intval($periodBegin_dt->format('m'));
$year = intval($periodBegin_dt->format('Y'));

$day_of_month_end = 15; // which works if $day_of_month_begin == 1
if ($day_of_month_begin == 16) {    
    if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
        $day_of_month_end = 30;
    } else if ($month == 2) {        
        // simplifying the rule, because this will be good until 2099
        $day_of_month_end = ($year % 4) ? 28 : 29;
    } else {
        $day_of_month_end = 31;
    }
}
$periodEnd = $year . '-' . $month . '-' . $day_of_month_end;
$periodEnd_dt = DateTime::createFromFormat("Y-m-d", $periodEnd);
$iso8601_last_day = $periodEnd_dt->format('N'); // ISO-8601 numeric representation of the day of the week 1 (for Monday) through 7 (for Sunday)
$lastMondayInPeriod_dt = $periodEnd_dt;
$interval_string = 'P' . ($iso8601_last_day-1) . 'D';
$lastMondayInPeriod_dt->sub(new DateInterval($interval_string));
$lastMondayInPeriod = $lastMondayInPeriod_dt->format('Y-m-d');

$scheme = 'http://'; // No real way to know if it should be https because we are running from command line

// Can't use HTTP_HOST because we are running from the command line.
$domain = DEFAULT_DOMAIN; // use a value from inc/config.php

$signoff_url = $scheme . DEFAULT_DOMAIN .  "/time/workWeekAndPrior/$lastMondayInPeriod";

foreach ($employees as $employee) {
	$query = "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".customerPerson ";
	$query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
	$query .= "AND personId = " . $employee->getUserId() . " ";
	$query .= "LIMIT 1;"; // theory is there should only be one, anyway
		
	$result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1593534222', "Hard DB error", $db);
        die();
    }
    
    $customerPersonId = 0;
    if ($row = $result->fetch_assoc()){
        $customerPersonId = $row['customerPersonId'];
    }
	
	if ($customerPersonId) {
		$row = false;
		
		// NOTE: initialSignoffTime added to SELECT here 2020-08-04 JM as an indirect consequence of http://bt.dev2.ssseng.com/view.php?id=200
		$query = "SELECT readyForSignoff, initialSignoffTime FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
		$query .= "WHERE customerPersonId = " . intval($customerPersonId) . " ";
		$query .= "AND periodBegin = '" . $db->real_escape_string($periodBegin)  . "' ";
		$query .= "LIMIT 1;";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1593534287', "Hard DB error", $db);
            die();
        }
        
        $row = $result->fetch_assoc();
        
        if ($row) {
            if ($row['readyForSignoff']) {
                if ($logging) {
                    $logger->info2('1593534302', 'customerId ' . $customer->getCustomerId() . " personId " . $employee->getUserId() . 
                        " customerPersonId " . $customerPersonId . ' was already marked as ready for signoff for period beginning ' . $periodBegin . '; ' . 
                        'Marked ' . $row['readyForSignoff']);
                }
            } else {
                // NOTE: test for initialSignoffTime (and the case where it is true) added here 2020-08-04 JM as an indirect consequence of http://bt.dev2.ssseng.com/view.php?id=200
                if ($row['initialSignoffTime']) {
                    if ($logging) {
                        $logger->info2('1596532660', 'customerId ' . $customer->getCustomerId() . " personId " . $employee->getUserId() . 
                            " customerPersonId " . $customerPersonId . ' was already marked ' . $row['initialSignoffTime'] . 
                            ' as signed off for period beginning ' . $periodBegin . ' so no email sent.');
                    }
                } else {
                    $mail = new SSSMail();
                    $body = "This is an auto-generated email to remind you to sign off your hours for the pay period from $periodBegin to $periodEnd.\n\n";
                    $body .= "You can sign off your hours at $signoff_url\n";
                    $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
                    $customerPerson = new CustomerPerson($customerPersonId);
                    $personId = $customerPerson->getPersonId();
                    list($target_email_address, $firstName, $lastName) = $customerPerson->getEmailAndName();
                    if ($target_email_address) {
                        $mail->addTo($target_email_address, $firstName);
                        $mail->setSubject('Pay period signoff reminder');                    
                    } else {
                        $logger->error2('1593556006', "Cannot find email address for $firstName $lastName (personId = $personId) at customer " . $customer->getCustomerId());
                        $mail->setSubject('FAILED Pay period signoff reminder');
                        $body = "Could not find email address for customerId " . $customer->getCustomerId() . " personId " . $employee->getUserId() . 
                                " " . $firstName . " " . $lastName . " " . $target_email_address . "\n\n" . $body;
                    }
                    $mail->addTo(EMAIL_OFFICE_MANAGER, OFFICE_MANAGER_NAME." (+$firstName)");
                    $mail->setBodyText($body);
                    if ($sendingMail) { // TEST ADDED 2020-10-20 JM
                        $mail_result = $mail->send();
                        if ($mail_result) {
                            // "ok"; logging added here 2020-08-04 JM
                            if ($logging) {
                                $logger->info2('1596532880', 'Mail successfully sent to customerId ' . 
                                    $customer->getCustomerId() . " personId " . $employee->getUserId() . 
                                    " customerPersonId " . $customerPersonId . ' ('. $firstName . " " . $lastName .
                                    " " . $target_email_address . ') for ' . $periodBegin);
                            }
                        } else {
                            // "fail"; logging added here 2020-08-04 JM
                            $logger->error2('1596533146', 'Email address was good but mail failed, sending to customerId ' . 
                                $customer->getCustomerId() . " personId " . $employee->getUserId() . 
                                " customerPersonId " . $customerPersonId . ' ('. $firstName . " " . $lastName .
                                ') for ' . $periodBegin);
                        }
                    } else {
                        // We built the mail, but chose not to send it
                        if ($logging) {
                            $logger->info2('1596533155', 'Mail deliberately NOT sent to customerId ' . 
                                $customer->getCustomerId() . " personId " . $employee->getUserId() . 
                                " customerPersonId " . $customerPersonId . ' ('. $firstName . " " . $lastName .
                                " " . $target_email_address . ') for ' . $periodBegin);
                        }
                    }
                    
                    $query = "UPDATE " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
                    $query .= "SET readyForSignoff = now() ";
                    $query .= "WHERE customerPersonId = " . $customerPerson->getCustomerPersonId() . " "; 
                    $query .= "AND periodBegin = '$periodBegin'";
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1593534414', "Hard DB error", $db);
                        die();
                    }
                    if ($db->affected_rows != 1) {
                        $logger->errorDb('1593704155', 'Expected to update signoff for one person + pay period, affected ' . $db->affected_rows . ' rows', $db); 
                    }
                }
            }
        } else {
            $logger->warnDb('1593534456', "No row in customerPersonPayPeriodInfo for this customerPerson & pay period", $db);
        }
	} else {
	    $logger->warn2('1593534298', "No customerPersonId for customerId={$customer->getCustomerId()}, personId={$employee->getUserId()}");
	}
}

if ($logging) {
    $logger->info2('1593536452', "crons/finishpayperiod.php succeeded.");
    echo "SUCCESS\n";
}

?>