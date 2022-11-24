#!/usr/bin/php -q
<?php

/* crons/payweekinfo.php

    EXECUTIVE SUMMARY: Run on Monday to create customerPersonPayWeekInfo rows for the coming week.

    Invoked as 'php payweekinfo.php'. The way Martin had this written, it died immediately if the day 
    it is run is anything other than a Monday. Joe modifed this 2019-10-03 so that it can be run 
    on other days of the week, will retroactively add the rows for the preceding Monday.
    
    Joe further modified this 2019-10-17 so that it can be run for a prior week. That should be done only
    in exceptional circumstances, but we just had those exceptional circumstances (see http://bt.dev2.ssseng.com/view.php?id=34)
    so I figured I'd keep the code in case this ever happens again.
    
    To run this for the current week:
    cd /var/www/html/crons
    php payweekinfo.php
    
    To run this for a specified earlier date (not heavily tested, but we did fill in the weeks of 2019-10-07 and 2019-10-14 this way):
    cd /var/www/html/crons
    php payweekinfo.php YYYY-MM-DD  (for example php payweekinfo.php 2019-10-07).
    
    >>>00001: this "prior week" approach would undoubtedly merit from some bulletproofing (e.g. make sure it doesn't get a date that isn't
    a Monday). It was written on an ad hoc basis in a semi-emergency. 

    The similarity to the name payperiodinfo.php is accidental: this is only tangentially related to that.
*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1589571881', "crons/payweekinfo.php must be run from the command line, was apparently accessed some other way.");
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
        $logger->info2('1589571895', "start crons/payweekinfo.php: $reconstructed_cmd");        
        break;
    }
}
unset($value, $i);

if (count($argv) != 1 && count($argv) != 2) {
    $logger->error2('1589568148', "crons/payweekinfo.php: wrong number of arguments. Only valid args are  optional '-log' " . 
        "and optional date in 'Y-m-d' form. Got $reconstructed_cmd");
	die();
}

$db = DB::getInstance();

// >>>00004 this may have to be revisited for any dev/test systems that aren't specific to SSS, 
//  or we may need to revisit our notion of domain as a way to determine customer
$domain = PRODUCTION_DOMAIN;

$customer = new Customer($domain);
$employees = $customer->getEmployees(1);  // current employees

/* 
For each current employee, we select the latest existing relevant row from DB table customerPersonPayWeekInfo. 
Assuming there is an existing row for that employee, we insert a new row in that same table with:
    relevant customerPersonId
    periodBegin = today's date
    dayHours, dayOT, weekOT, workWeek all pulled from that latest existing row. 

If there is no existing row at all for that employee, then we insert a row with:
    relevant customerPersonId
    periodBegin = today's date
    dayHours = 8
    dayOT = 10
    weekOT = 40
    workWeek = WORKWEEK_MON_SUN    
*/

if (count($argv) == 2) {
    $periodStart = $argv[1];
    if (!preg_match ('/^(\d{4})-(\d{2})-(\d{2})$/', $periodStart, $matches)) {
        // Expect explicit date to be used from command line rather than run as a cron, so echo this error message as well
        //  as logging it.
        $msg = "Only valid args are optional '-log' and optional date in 'Y-m-d' form, e.g. 2021-06-17. Got $reconstructed_cmd; '$periodStart' is not valid."; 
        echo "$msg\n";
        $logger->error2('1589568211', $msg);        
        die();
    }
    /* NOT NEEDED, but just in case it is in the future, here's how you'd do this:
    $year = $matches[1];
    $month = $matches[2];
    $day = $matches[3];
    */
    if ($logging) {
        $logger->info2('1589568261', "inserting customerPersonPayWeekInfo for ". count($employees) . " employees for the week beginning $periodStart; " .
            "code does not currently validate that being a Monday, and it will be a problem if it isn't");
    }
} else {
    // If this is not run on a Monday, then it runs as if it were the most recent Monday.
    $adjust_values = Array('Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5, 'Sun' => 6); 
    $adjust = $adjust_values[date('D', time())];
    $adjust_string = '';
    if ($adjust) {
        $adjust_string = '-' . $adjust . ' days'; 
    }    
    
	$now = new DateTime('now');
	if (isset($adjust_string) && $adjust_string) {
	    $now->modify($adjust_string);
	}
	$month = $now->format('m');
	$year = $now->format('Y');
	$day = $now->format('d');

	$periodStart = $year . '-' . $month . '-' . $day;

    if ($logging) {
        $logger->info2('1589568285', "inserting customerPersonPayWeekInfo for ". count($employees) . " employees for the week beginning " .
            "Monday of this week: $periodStart.");
    }
}

foreach ($employees as $employee) {
	$query = "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".customerPerson ";
	$query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
	$query .= "AND personId = " . $employee->getUserId() . " ";
	$query .= "LIMIT 1;"; // theory is there should only be one, anyway

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1589568400', "Hard DB error", $db);
        die();
    }
	
    $customerPersonId = 0;
    if ($row = $result->fetch_assoc()){
        $customerPersonId = $row['customerPersonId'];
    }
	
	if ($customerPersonId) {		
		$row = false;		
		$query = "SELECT * FROM " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo ";
		$query .= "WHERE customerPersonId = " . intval($customerPersonId) . " ";
		$query .= "ORDER BY periodBegin DESC LIMIT 1;";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1589568444', "Hard DB error", $db);
            die();
        }
        
        $row = $result->fetch_assoc();

		if ($row) {
			$dayHours = $row['dayHours'];
			$dayOT = $row['dayOT'];
			$weekOT = $row['weekOT'];
			$workWeek = $row['workWeek'];

			if (!is_numeric($dayHours)) {
				$dayHours = 8;
			}
			if (!is_numeric($dayOT)) {
				$dayOT = 10;
			}
			if (!is_numeric($weekOT)) {
				$weekOT = 40;
			}
			if (!is_numeric($workWeek)) {
				$workWeek = WORKWEEK_MON_SUN;
			}

			$query = "INSERT INTO " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo (".
			"customerPersonId, ".
			"periodBegin, ".
			"dayHours, ".
			"dayOT, ".
			"weekOT, ".
			"workWeek".
			") VALUES (";
			$query .= intval($customerPersonId);
			$query .= ", '" . $db->real_escape_string($periodStart)  . "'";
			$query .= ", " . intval($dayHours);
			$query .= ", " . intval($dayOT);
			$query .= ", " . intval($weekOT);
			$query .= ", " . intval($workWeek);
			$query .= ")";
		} else {
			$query = "INSERT INTO " .  DB__NEW_DATABASE . ".customerPersonPayWeekInfo (".
			"customerPersonId, ".
			"periodBegin, ".
			"dayHours, ".
			"dayOT, ".
			"weekOT, ".
			"workWeek".
			") VALUES (";
			$query .= intval($customerPersonId)  . " ";
			$query .= ", '" . $db->real_escape_string($periodStart)  . "'";
			$query .= ", 8";
			$query .= ", 10";
			$query .= ", 40";
			$query .= ", " . intval(WORKWEEK_MON_SUN);
			$query .= ");";
		}
		$result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1589568471', "Hard DB error", $db);
            die();
        }		
	} else {
	    $logger->warn2('1589568491', "No customerPersonId for customerId={$customer->getCustomerId()}, personId={$employee->getUserId()}");
	}
}

if ($logging) {
    $logger->info2('1589568520', "crons/payweekinfo.php succeeded.");
}

?>