#!/usr/bin/php -q
<?php 
/*  crons/addholiday.php

    EXECUTIVE SUMMARY: give every employee 8 hours pay for a particular holiday.
    >>>00026: but what about part-timers?    
    
    Invoked as 'addholiday.php DATE', where DATE should be in Y-m-d form (e.g. 2019-06-30).
    
    Martin comment: not actually a cron job at this point .. but might be
*/


function validateDate($date) {
	$d = DateTime::createFromFormat('Y-m-d', $date);
	return $d && $d->format('Y-m-d') === $date;
}

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web) and must have exactly the following args:
//  - the filename itself
//  - a date in 'Y-m-d' form
//  - optional '-log'
if (!is_command_line_interface()) {
    $logger->error2('1589555635', "crons/addholiday.php must be run from the command line, was apparently accessed some other way.");
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
        $logger->info2('1589555665', "start crons/addholiday.php: $reconstructed_cmd");
        break;
    }
}
unset($value, $i);

if (count($argv) != 2) {
    $logger->error2('1589555684', "crons/addholiday.php: wrong number of arguments. Expect date in 'Y-m-d' form and optional '-log'. Got $reconstructed_cmd");
	die();
}

$holiday = $argv[1];

if (!(validateDate($holiday))) {
    $logger->error2('1589555704', "crons/addholiday.php: invalid input date '$holiday'. Expect date in 'Y-m-d' form.");
	die();
} else if ($logging) {
    $logger->info2('1589555707', "crons/addholiday.php: adding PTO for '$holiday'.");
}

// Can't use HTTP_HOST because we are running from the command line.
$domain = DEFAULT_DOMAIN; // use a value from inc/config.php

$customer = new Customer(PRODUCTION_DOMAIN);

$employees = $customer->getEmployees(1); // current employees only
                                         // >>>00026: presumes the current list of employees is exactly the list for the date
                                         //   in question. Really ought to be a more complex query that gets employees 
                                         //   on the relevant date.
if ($logging) {
    $logger->info2('1589555707', count ($employees) . " employees");
}
                                         
$db = DB::getInstance();

$already_existed = 0;
$added = 0;
foreach ($employees as $ix => $employee) {	
	$exists = false;
	
	$query = "SELECT ptoId FROM " . DB__NEW_DATABASE . ".pto ";
	$query .= "WHERE personId = " . intval($employee->getUserId()) . " ";
	$query .= "AND day = '" . $db->real_escape_string($holiday) . "' ";
	
	$result = $db->query($query);
	if ($result) {
		$exists = !!$result->num_rows;
	} else {
        $logger->errorDb('1589555776', "Hard DB error", $db);
        die();
	}
	
	
	if ($exists) {
	    ++$already_existed;
	} else {
		$query = "INSERT INTO " . DB__NEW_DATABASE . ".pto (ptoTypeId, personId, day, minutes) VALUES (";
		$query .= intval(PTOTYPE_HOLIDAY)  . " ";
		$query .= ", " . intval($employee->getUserId());
		$query .= ", '" . $db->real_escape_string($holiday)  . "'";
		$query .= ", " . intval(480)  . ");";
		
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1589555790', "Hard DB error after processsing $ix of " . count($employees) . ' employees.', $db);
            die();
        } 
	    ++$added;
	}
}

if ($logging) {
    $logger->info2('1589555888', "crons/addholiday.php succeeded. $already_existed rows already existed, $added added. " . 
        "To validate, SELECT * FROM pto WHERE day='" . $db->real_escape_string($holiday)  . "';");
}

?>