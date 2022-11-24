#!/usr/bin/php -q
<?php 
/*  crons/payperiodinfo.php

    EXECUTIVE SUMMARY: cron job to insert appropriate rows in customerPersonPayPeriodInfo on the 1st or 16th of the month.

    The similarity to the name payweekinfo.php is accidental: this is only tangentially related to that.

    Invoked as 'payperiodinfo.php 1' or 'payperiodinfo.php 16' (day of month when pay period begins) 
    Optional '-log'.

	// NOTE that this presumes this is run in the relevant month.
	// >>>00032 ought to have a way to pass an argument to override that.
    
*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1589568092', "crons/payperiodinfo.php must be run from the command line, was apparently accessed some other way.");
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
        $logger->info2('1589568125', "start crons/payperiodinfo.php: $reconstructed_cmd");        
        break;
    }
}
unset($value, $i);

if (count($argv) != 2) {
    $logger->error2('1589568148', "crons/payperiodinfo.php: wrong number of arguments. Expect '1' or '16' and optional '-log'. Got $reconstructed_cmd");
	die();
}

$period = intval($argv[1]);

// SSS pay periods always begin on 1st or 16th of month.
// >>>00004 This probably would need to be more flexible if we roll this out to other customers.
if (!(($period == 1) || ($period == 16))){
    $logger->error2('1589568148', "crons/payperiodinfo.php: Bad arguments. Expect '1' or '16' and optional '-log'. Got $reconstructed_cmd");
	die();
}

// >>>00004 this may have to be revisited for any dev/test systems that aren't specific to SSS, 
//  or we may need to revisit our notion of PRODUCTION_DOMAIN as a way to determine customer:
// >>>00004 JM suspects 2019-10-04 that we might want to set $domain as a global in inc/config.php
//  (currently we deliberately unset it) and use that here. That would probably be best.
$domain = PRODUCTION_DOMAIN;

$customer = new Customer($domain);
$employees = $customer->getEmployees(1); // current employees

$now = new DateTime('now');
$month = $now->format('m');
$year = $now->format('Y');
$periodBegin = $year . '-' . $month . '-' . $argv[1];

if ($logging) {
    $logger->info2('1589568149', "inserting customerPersonPayPeriodInfo for ". count($employees) . " employees for the period beginning $periodBegin");
}

$db = DB::getInstance();

foreach ($employees as $employee) {
	// NOTE that this presumes this is run in the relevant month.
	// >>>00032 ought to have a way to pass an argument to override that.
	
	$query = "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".customerPerson ";
	$query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
	$query .= "AND personId = " . $employee->getUserId() . " ";
	$query .= "LIMIT 1;"; // theory is there should only be one, anyway
		
	$result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1589568177', "Hard DB error", $db);
        die();
    }
    
    $customerPersonId = 0;
    if ($row = $result->fetch_assoc()){
        $customerPersonId = $row['customerPersonId'];
    }
	
	if ($customerPersonId) {		
		$row = false;
		
		$query = "SELECT * FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
		$query .= "WHERE customerPersonId = " . intval($customerPersonId) . " ";
		$query .= "ORDER BY periodBegin DESC LIMIT 1;";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1589568217', "Hard DB error", $db);
            die();
        }
        
        $row = $result->fetch_assoc();
		
		if ($row) {
		    // Had a prior customerPersonPayPeriodInfo row for this employee, base
		    // the new row on that.
			$ira = $row['ira'];
			$copay = $row['copay'];
			
			if (!is_numeric($ira)) {
				$ira = 0;
			}
			if (!is_numeric($copay)) {
				$copay = 0;
			}
				
			$query = "INSERT INTO " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo (".
			"customerPersonId, ".
			"periodBegin, ".
			"payPeriod, ".
			"rate, ".
			"salaryHours, ".
			"ira, ".
			"copay, ".
			"salaryAmount".
			") VALUES (";
			$query .= intval($customerPersonId);			
			$query .= ", '" . $db->real_escape_string($periodBegin)  . "'";
			$query .= ", " . intval($row['payPeriod']);
			$query .= ", " . intval($row['rate']);
			$query .= ", " . intval($row['salaryHours']);
			$query .= ", " .  $ira;
			$query .= ", " .  $copay;
			$query .= ", " . intval($row['salaryAmount']);
			$query .= ");";
		} else {
		    // No prior customerPersonPayPeriodInfo row for this employee, 
		    // the new row gets basically zero values.
		    // >>>00032 could initialize from data in customerPerson. It used to before 2017-12, not
		    //  clear why we got rid of that.
		    
			$query = "INSERT INTO " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo (".
			"customerPersonId, ".
			"periodBegin, ".
			"payPeriod, ".
			"rate, ".
			"salaryHours, ".
			"ira, ".
			"copay, ".
			"salaryAmount".
			") VALUES (";
			$query .= intval($customerPersonId)  . " ";
			$query .= ", '" . $db->real_escape_string($periodBegin)  . "' ";
			$query .= ", " . intval(PAYPERIOD_BIMONTHLY_1_16)  . " ";
			$query .= ", 0 ";
			$query .= ", 0 ";
			$query .= ", 0 ";
			$query .= ", 0 ";
			$query .= ", 0";
			$query .= ");";
		}
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1589568271', "Hard DB error", $db);
            die();
        }		
	} else {
	    $logger->warn2('1589568298', "No customerPersonId for customerId={$customer->getCustomerId()}, personId={$employee->getUserId()}");
	}
}

if ($logging) {
    $logger->info2('1589568100', "crons/payperiodinfo.php succeeded.");
}

?>