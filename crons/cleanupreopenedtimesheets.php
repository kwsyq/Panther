#!/usr/bin/php -q
<?php 
/*  crons/cleanupreopenedtimesheets.php

    EXECUTIVE SUMMARY: cron job to close out timesheets that had previously been signed off, 
    then reopened for modifications but left open longer than is allowed. Emails notification
    to employee and manager when closing out a timesheet. 
    
    Inputs allow some policies to be set by caller. 
    
    These four inputs each refer to an amount of time after this cron job may "close" a reopened timesheet. All should be in minutes.
    Missing or 0 means we *do not apply this criterion*, so if none of these are provided, this cron job effectively does nothing.
    No spaces within an input, no quotes, e.g. maxMinsForReviewed=120, minsAfterEditForUnreviewed=120
    
    maxMinsForReviewed: An admin already signed off the timesheet. It is at least this many minutes since the employee reopened it.
        It does not matter how recently the employee may have made an additional edit.
    maxMinsForUnreviewed: No admin has signed off the timesheet. It is at least this many minutes since the employee reopened it.
        It does not matter how recently the employee may have made an additional edit.
    minsAfterEditForReviewed: An admin already signed off the timesheet. It is at least this many minutes since the employee edited a time.
        It does not matter when the timesheet was reopened.
    minsAfterEditForUnreviewed: No admin has signed off the timesheet. It is at least this many minutes since the employee edited a time.
        It does not matter when the timesheet was reopened.
    In the event that the same argument is given twice, only the last value will be used.
        
    Optional '-log'.

    >>>00004 no consideration given here to having more than one "customer", strictly SSS

*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1602543626', "crons/cleanupreopenedtimesheets.php must be run from the command line, was apparently accessed some other way.");
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
        $logger->info2('1602543800', "start crons/cleanupreopenedtimesheets.php: $reconstructed_cmd");        
        break;
    }
}
unset($value, $i);

$maxMinsForReviewed = 0;
$maxMinsForUnreviewed = 0;
$minsAfterEditForReviewed = 0;
$minsAfterEditForUnreviewed = 0;

foreach ($argv as $value) {
    $parts = explode('=', $value);
    if (count($parts) != 2) {
        $logger->info2('1602544655', "crons/cleanupreopenedtimesheets.php: malformed argument '$value'");
        die();
    } 
    if ($parts[0] == 'maxMinsForReviewed') {
        $maxMinsForReviewed = intval($parts[1]);
    } else if ($parts[0] == 'maxMinsForUnreviewed') {
        $maxMinsForUnreviewed = intval($parts[1]);
    } else if ($parts[0] == 'minsAfterEditForReviewed') {
        $minsAfterEditForReviewed = intval($parts[1]);
    } else if ($parts[0] == 'minsAfterEditForUnreviewed') {
        $minsAfterEditForUnreviewed = intval($parts[1]);
    } else {
        $logger->info2('1602544789', "crons/cleanupreopenedtimesheets.php: unrecognized argument '$value'");
        die();
    }
}

if ($maxMinsForReviewed == 0 && $maxMinsForUnreviewed == 0 && $minsAfterEditForReviewed == 0 && $minsAfterEditForUnreviewed == 0) {
    $logger->info2('1602543928', "crons/cleanupreopenedtimesheets.php: Nothing to do.");
    echo "Nothing to do.\n";
	die();
}

$query  = "SELECT * FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
$query .= "WHERE reopenTime IS NOT NULL;";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1602545273', "Hard DB error", $db);
    die();
}
$rows = Array();
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$now = time(); // UNIX epoch time in seconds

foreach ($rows as $row) {
    $closeThis = false;
    
    $periodBegin = $row['periodBegin'];
    $customerPersonId = $row['customerPersonId'];
        
    
    $reopenTimeString = $row['reopenTime'];
    $reopenTime = strtotime($reopenTimeString); // >>>00001 may have timezone issues, need to work this through.
    // BEGIN DEBUG
    if ($logging) {
        $logger->info2('1602546079', "DEBUG: $reopenTimeString maps to $reopenTime; now is $now");
    }
    // END DEBUG
    $diffMinutes = ($now - $reopenTime) / 60;  
    $adminSignedPayrollTime = $row['adminSignedPayrollTime'];
    $closeThis = $closeThis || ($maxMinsForReviewed && $adminSignedPayrollTime && $diffMinutes > $maxMinsForReviewed);
    $closeThis = $closeThis || ($maxMinsForUnreviewed && $adminSignedPayrollTime===null && $diffMinutes > $maxMinsForUnreviewed);
    
    $time = new Time($employee, $periodBegin, 'payperiod');
        
    if ($closeThis && $logging) {
        $logger->info2('1602546451', "Period beginning $periodBegin for customer customerPersonId " . 
                 "will be closed based on total time reopened.");
    }
    
    if (!$closeThis && ( ($minsAfterEditForReviewed && $adminSignedPayrollTime) || 
                         ($minsAfterEditForUnreviewed && $adminSignedPayrollTime===null) )) 
    {
        // We need to look at individual rows in workOrderTaskTimeLateModification & ptoLateModification to see when last edited.
        $customerPerson = new CustomerPerson($customerPerson);
        $personId = $customerPerson->getPersonId();
        $employee = new User($personId);
        
        $wotLateModifications = $time->getWorkOrderTaskTimeLateModifications();
        $ptoLateModifications = $time->getPtoLateModifications();
        
        $noTimeString = '0000-00-00 00:00:00';
        $latestTimeString = $noTimeString;
        foreach ($wotLateModifications as $wotLateModification) {
            if ($wotLateModification['inserted'] > $latestTimeString) {
                $latestTimeString = $wotLateModification['inserted'];
            }                
        }
        foreach ($ptoLateModifications as $ptoLateModification) {
            if ($ptoLateModification['inserted'] > $latestTimeString) {
                $latestTimeString = $ptoLateModification['inserted'];
            }                
        }
        
        $latestTime = strtotime($latestTimeString); // >>>00001 may have timezone issues, need to work this through.
        // BEGIN DEBUG
        if ($logging) {
            $logger->info2('1602547770', "DEBUG: $latestTimeString maps to $latestTime; now is $now");
        }
        // END DEBUG
           
        $diffMinutes = ($now - $latestTime) / 60;  
        $closeThis = $closeThis || ($minsAfterEditForReviewed && $adminSignedPayrollTime && $diffMinutes > $minsAfterEditForReviewed);
        $closeThis = $closeThis || ($minsAfterEditForUnreviewed && $adminSignedPayrollTime===null && $diffMinutes > $minsAfterEditForUnreviewed);
        if ($closeThis && $logging) {
            $logger->info2('1602547891', "Period beginning $periodBegin for customer customerPersonId " . 
                     "will be closed based on time since last edit.");
        }
    }
    // We now know whether we want to close it or not
    if ($closeThis) {
        // CLOSE IT! & send a notification.
        $query = "UPDATE " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
        $query .= "SET lastSignoffTime = now()";
        $query .= ", reopenTime = NULL ";
        $query .= "WHERE customerPersonId = $customerPersonId ";
        $query .= "AND periodBegin = '$periodBegin';"; 
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDB('1602604232', 'Hard DB error updating customerPersonPayPeriodInfo.initialSignoffTime', $db);
        } else if ($db->affected_rows != 1) {
            $logger->errorDb('1602604264', 'Expect 1 affected row, got '. $db->affected_rows, $db);
        } else if ($logging) {
            $logger->info2('1602604279', "query succeeded: $query");
        }
        
        $time->notifyLateModifications();
    }
}


if ($logging) {
    $logger->info2('1593536452', "crons/cleanupreopenedtimesheets.php succeeded.");
    echo "SUCCESS\n";
}

?>