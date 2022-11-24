<?php
/*  _admin/time/summary.php

    EXECUTIVE SUMMARY: Page giving time summary for all employees for a week/period.
     Also allows updating payPeriodNotes.
     >>>00004 Note that this is tied to a system of 2 pay periods per month, beginning
      on the 1st & 16th, respectively.

    PRIMARY INPUTs: 
        * $_REQUEST['start'] (the date the week starts ... e.g, '2016-10-20')
        * $_REQUEST['displayType']: 'payperiod' (default) / 'incomplete'. Should NOT use 'workweek'.
        * $_REQUEST['render'] ('expanded' (default) / 'paychex').

    Optional INPUT:  $_REQUEST['act']
        * Only supported value is 'updatenotes', which takes additional input:
            * $_REQUEST['notes'].
*/

include '../../inc/config.php';

$db = DB::getInstance();

// Identical to the function of the same name in sheet.php, >>>00037 should be common code in one place
// INPUT $user: User object (for the employee)
// INPUT $start: start date 
// RETURNs an associative array covering a 28-day period with that start date, as follows: 
//  Indexes are dates in the form "Y-m-d", e.g. "2019-08-19". Each value is, in turn an associative array with the following elements:
//  * 'pto': paid time off in minutes
//  * 'regular': work time in minutes
//  * 'ptobreakdown': an associative array. For each different type of PTO that arises for a given day, 
//     a count in minutes will be maintained under two different indexes, so all of this information is in there twice. 
//     It is indexed both by ptoTypeName and ptoTypeId.
//  * 'payWeekInfo': as returned by User::getCustomerPersonPayWeekInfo for the relevant week. 
function calc($user, $start) {
    $date = new DateTime($start);

    $w1 = new Time($user, $date->format("Y-m-d"), 'workWeek');
    $w1info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $date->modify("+7 days");
    $w2 = new Time($user, $date->format("Y-m-d"), 'workWeek');
    $w2info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $date->modify("+7 days");
    $w3 = new Time($user, $date->format("Y-m-d"), 'workWeek');
    $w3info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $date->modify("+7 days");
    $w4 = new Time($user, $date->format("Y-m-d"), 'workWeek');
    $w4info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $w1tasks = $w1->getWorkOrderTasksByDisplayType();
    $w2tasks = $w2->getWorkOrderTasksByDisplayType();
    $w3tasks = $w3->getWorkOrderTasksByDisplayType();
    $w4tasks = $w4->getWorkOrderTasksByDisplayType();

    $dateTimeIterator = new DateTime($start); // PHP DateTime object; before v2020-4 this was called $run, now $dateTimeIterator
    $days = array();

    $payWeekInfos = array($w1info, $w2info, $w3info, $w4info,);
    $payWeekInfosOffset = 0;

    for ($i = 0; $i < 28; ++$i) {
        if (($i % 7) == 0) {
            $payWeekInfosOffset++;
        }        
        $days[$dateTimeIterator->format("Y-m-d")] = array('pto' => 0, 'regular' => 0, 'ptobreakdown' => array(), 'payWeekInfo' => $payWeekInfos[$payWeekInfosOffset - 1] );
        $dateTimeIterator->modify("+1 day");
    }

    $alltasks = array_merge($w1tasks, $w2tasks, $w3tasks, $w4tasks);
    
    foreach ($alltasks as $task) { 
        if (isset($task['regularitems'])) {
            $regularitems = $task['regularitems'];
            foreach ($regularitems as $rkey => $items) {
                foreach ($items as $item) { // MARTIN COMMENT: possible for more than one person to have time on this task
                    if ($item['personId'] == $user->getUserId()) {
                        $days[$rkey]['regular'] += validateNum($item['minutes']);
                    }
                }
            }
        }

        if (isset($task['ptoitems'])) {
            $ptoitems = $task['ptoitems'];
            foreach ($ptoitems as $pkey => $item) {
                // * 'ptoTypeId': 1 (sick/vacation) or 2 (holiday)
                // * 'ptoTypeName': either "Holiday" or "Sick/Vacation", as appropriate
                if (!isset($days[$pkey]['ptobreakdown'][$item['ptoTypeId']])) {
                    $days[$pkey]['ptobreakdown'][$item['ptoTypeId']] = 0;
                }
                if (!isset($days[$pkey]['ptobreakdown'][$item['ptoTypeName']])) {
                    $days[$pkey]['ptobreakdown'][$item['ptoTypeName']] = 0;
                }
                $days[$pkey]['ptobreakdown'][$item['ptoTypeName']] += validateNum($item['minutes']);
                $days[$pkey]['ptobreakdown'][$item['ptoTypeId']] += validateNum($item['minutes']);

                $days[$pkey]['pto'] += validateNum($item['minutes']);
            }
        }
    }

    return $days;
} // END function calc

// INPUT $val should be either a valid INT or FLOAT
// RETURN: 
//   * if $val is valid, then the same value as a float with two significant digits after the decimal point.
//   * otherwise returns 0.
function validateNum($val) {
    if ((filter_var($val, FILTER_VALIDATE_INT) !== FALSE) || (filter_var($val, FILTER_VALIDATE_FLOAT) !== FALSE)) {
        $val = (float)intval($val * 100) / 100;
        return $val;
    }

    return 0;
}

$error = '';
$errorId = 0;
$v = new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();
if ($error){
    $logger->error2('1601922079', "Error(s) found in init validation: [".json_encode($v->errors())."]");
    // >>>00032 JM 2020-10-05: eventually, we should probably have an interstitial page for errors like this,
    // but for now let's handle this the same way we do everywhere else.
    header("Location: /"); // out of here, back to home page
    die();
}

// Inputs are all optional, but validate that if present they make sense.
$v->stopOnFirstFail();
$v->rule('regex', 'start', '/^20[0-9][0-9]-(0[1-9]|1[0-2])-(01|16)$/'); // validate date is in the 2000s & is 1st or 16th of month 
$v->rule('in', 'displayType', ['payperiod', 'incomplete']);
$v->rule('in', 'render', ['expanded', 'paychex']);

if( !$v->validate() ) {
    $logger->error2('1601922429', "Invalid input. Errors found: ".json_encode($v->errors()));
    // >>>00032 JM 2020-10-05: eventually, we should probably have an interstitial page for errors like this,
    // but for now let's handle this the same way we do everywhere else.
    header("Location: /"); // out of here, back to home page
    die();
}

// $userId = isset($_REQUEST['userId']) ? intval($_REQUEST['userId']) : 0; // REMOVED 2020-10-02 JM: vestigial
$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';  // If present, must be the 1st or 16th of the month
$displayType = isset($_REQUEST['displayType']) ? $_REQUEST['displayType'] : '';
$render = isset($_REQUEST['render']) ? $_REQUEST['render'] : '';

// If $start not passed in, set value for the most recent 1st or 16th of the month.
if (!strlen($start)) {
    $date = new DateTime();
    $date->setTime(0, 0, 0);
    $start_scratch = $date->format('Y-m-d');
    $start_exploded = explode('-', $start_scratch);
    if (intval($start_exploded[2]) >= 16) {
        $start_exploded[2] = '16';
    } else {
        $start_exploded[2] = '1';
    }
    $start = implode('-', $start_exploded); 
    unset($date, $start_scratch, $start_exploded);
}

if (!strlen($render)) {
    $render = 'expanded';
}

if (!strlen($displayType)) {
    $displayType = 'payperiod';
}

// $act == 'updatenotes' was previously buried way down the file; JM moved it up 2020-10-05 & also added error-handling etc.
//  NOTE that the form and action are both inside the 'paychex' case. 
// 
//  Insert note as requested into DB table PayPeriodNotes. 
//  Despite "update" in name, this *adds* a note; no way to get rid of any old ones.
if ($render == 'paychex' && $act == 'updatenotes') {
    $notes = isset($_REQUEST['notes']) ? $_REQUEST['notes'] : ''; // Apparently empty is OK, so nothing to validate
    $notes = trim($notes);
    
    $query  = "INSERT INTO " . DB__NEW_DATABASE . ".payPeriodNotes (periodBegin, notes) VALUES (";
    $query .= " '" . $db->real_escape_string($start) . "' ";
    $query .= " ,'" . $db->real_escape_string($notes) . "') ";

    $result = $db->query($query);
    if (!$result) {
         $logger->errorDb('1601923966', 'Hard DB failure updating notes', $db);
        // >>>00032 JM 2020-10-05: eventually, we should probably have an interstitial page for errors like this,
        // but for now let's handle this the same way we do everywhere else.
        header("Location: /"); // out of here, back to home page
        die();
    }
    // Reload this page cleanly
    header("Location: summary.php?start=$start&displayType=$displayType&render=$render");
}

// Get data for all current employees
// >>>00026 NOTE that this is "current" at the time the code is run, not current for the period in question.
// If this is run for a past period IT WILL OMIT any employees who have since left the company.
$employees = $customer->getEmployees(1);

// Instantiates Time object based on inputs $start and $displayType. A lot of work is done in this constructor.
$time = new Time(0, $start, $displayType);

include "../../includes/header_admin.php";
?>
<style>
    .datarow td {
        border-bottom-style: solid;
        border-bottom-color: #cccccc;
    }
    
    .datarow tr:nth-child(even) {background-color: #ededed}
    .datarow tr:nth-child(odd) {background-color: #ffffff}
    
    .headings th {
        background-color:#aeaeae;
    }
    
    .totalcell {
        background-color:#dedede;
        text-align:center;
    }
    .diagopen {
        text-align:center;
    }
    
    textarea
    {
      width:100%;
    }
    .textwrapper
    {
      border:1px solid #999999;
      margin:5px 0;
      padding:3px;
    }
</style>

<div style="margin-left:10px">
<?php
// Top nav. This is the "SUMMARY" page.
echo '<table border="0" cellpadding="0" width="100%">';
    echo '<tr>';
        echo '<td colspan="3">';
            // Gray out link to PTO calendar; we believe this will eventually be revived, but right now it's a dead page.
            //echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[SUMMARY]&nbsp;&nbsp;[<a href="pto.php">PTO</a>]&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]';
            echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[SUMMARY]&nbsp;&nbsp;<span style="color:lightgray">[PTO]</span>&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]';
        echo '</td>';
    echo '</tr>';
echo '</table>';

// Another small table, no headers, offering navigation to the previous or next periods 
//  (similar, but not identical to '? date one week earlier ?', etc. in sheet.php). 
// In this case, the labels are:
//  * "Prev"
//  * the current date range (e.g. "06-16 thru 06-30-2020")
//  * "Next". 
// "Prev" & "Next" use the GET method to reload summary.php for the appropriate start date, with other inputs held constant.
echo '<table border="0" cellpadding="0" cellspacing="0" width="800">';
    echo '<tr><td colspan="3">&nbsp;</td></tr>';
    echo '<tr>';
        echo '<td style="text-align:left">&#171;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->previous . '">prev</a>&#171;</td>';
        
        $e = date('Y-m-d', strtotime('-1 day', strtotime($time->next)));
        echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">Period: ' . 
             date("m-d", strtotime($time->begin)) . ' thru ' . 
             date("m-d", strtotime($e)) . '-' . date("Y", strtotime($time->begin)) . '</span></td>'; // NOTE that we presume (appropriately) that the  
                                                                                                     // time period does not cross the year boundary
        echo '<td style="text-align:right">&#187;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->next . '">next</a>&#187;</td>';
    echo '</tr>';
echo '</table>';

// Yet another nav: [Paychex] [Expanded summary], the two modes for this page.
if ($render == 'paychex') {
    echo '[Paychex]&nbsp;&nbsp;[<a href="summary.php?displayType=payperiod&render=expanded&start=' . $time->begin . '">Expanded summary</a>]';
} else {
    echo '[<a href="summary.php?displayType=payperiod&render=paychex&start=' . $time->begin . '">Paychex</a>]&nbsp;&nbsp;[Expanded summary]';
}

echo '<div id="signoff-status"></div>';
$requiring_signoff = Array();

if ($render == 'paychex') {
    echo '<table border="0" width="900" cellpadding="5" cellspacing="2">';
    echo '<tr class="headings">';
        echo '<th align="left">ID</th>';
        echo '<th align="left">Name</th>';
        echo '<th align="center">Reg</th>';
        echo '<th align="center">OT</th>';
        echo '<th align="center">PTO</th>';
        echo '<th align="center">Hol</th>';
        echo '<th align="center">Co-Pay</th>';
        echo '<th align="center">IRA</th>';
        echo '<th align="center">Match</th>';
        echo '<th align="center">Miles</th>';
        echo '<th align="center">Total</th>';
        echo '<th align="center" colspan="2">Signoff</th>';
    echo '</tr>';
}

// Grand totals over all employees
$colreg = 0;
$colot = 0;
$colpto = 0;
$colhol = 0;

echo '<tbody class="datarow">';

foreach ($employees as $employee) {
    $employeeId = $employee->employeeId;
    $workerId = $employee->workerId;
    $payperiodinfo = $employee->getCustomerPersonPayPeriodInfo($start);
    $userId = $employee->getUserId();
    
    $employee = new User($userId, $customer);
    $time = new Time($employee, $start, $displayType);
    $workordertasks = $time->getWorkOrderTasksByDisplayType();

    $inot = 0; // quasi-Boolean "in overtime"

    // Have to look at full week to calculate overtime correctly, even if the pay period begins mid-week.
    // That means that we have to look at a broader timespan than just the period for this summary: always
    //  start looking at the beginning of a week. 
    // 
    // NOTE that below we will add more elements to each associative array $days[$i]: calc just gets this started.
    $days = calc($employee, $time->firstDayWorkWeek->format("Y-m-d")); 

    $c = 0; // index within the timespan we are looking at. Always 0 mod 7 at start of week.
    $errors = Array();
    
    if (!$payperiodinfo) {
        $error = 'Lacking payPeriodInfo data for this period for ' . $employee->getFirstName() . ' ' . $employee->getLastName();
        $logger->error2('1597787469', $error);
        $errors[] = $error;
    }
    
    foreach ($days as $dkey => $day) {
        if ($dkey > $time->end) {
            // we don't care about time after the relevant period
            break;
        }
        if (is_bool($day['payWeekInfo'])) {
            $error = $dkey . ': lacking payWeekInfo data for ' . $employee->getFirstName() . ' ' . $employee->getLastName();
            $logger->error2('1593792300', $error);
            $errors[] = $error;
            continue; 
        }
                
        $dayotminutes = (60 * $day['payWeekInfo']['dayOT']); // day overtime threshold in minutes                                           
        $daysinweek = 7;  // Martin comment: days in work week
        $weekotminutes = (60 * $day['payWeekInfo']['weekOT']); // week overtime threshold in minutes

        $mod = ($c % $daysinweek);
        // Code relies on the fact that the first time through the loop this will be true.
        // That is how $weektotal & $inot get initialized. 
        if ($mod == 0) {
            $weektotal = 0;
            $inot = 0;
        }

        $weektotal += validateNum($day['regular']);

        $days[$dkey]['dailyot'] = 0;
        $days[$dkey]['worked'] = validateNum($day['regular']); // 'regular' vs., for example, 'pto'
        $days[$dkey]['weektotal'] = $weektotal;  // So, week total worked up to and including this day.
        $days[$dkey]['weekot'] = 0;
        if ($weektotal > $weekotminutes) {
            if ($inot) {
                // already in overtime, so this is all overtime
                $days[$dkey]['weekot'] = validateNum($day['regular']);
            } else {
                if (validateNum($day['regular'])) {
                    if (!isset($days[$dkey]['weekot'])) {
                        $days[$dkey]['weekot'] = 0;
                    }
                    $days[$dkey]['weekot'] = ($weektotal - $weekotminutes); // some portion of this is allocated to overtime
                }
            }

            $inot = 1; // and anything after this is overtime
        }

        // Did they work so much on the one day that some of this is overtime?
        if (validateNum($day['regular']) > $dayotminutes) {
            $days[$dkey]['dailyot'] = (validateNum($day['regular'] - $dayotminutes));
        }

        $weekot = validateNum($days[$dkey]['weekot']);    // how many of these hours would be overtime based on the week?
        $dailyot = validateNum($days[$dkey]['dailyot']);  // how many of these hours would be overtime based on the day?

        // Then take the more generous of the two numbers for how many of today's hours count as overtime.
        $days[$dkey]['ot'] = 0;
        if ($weekot > $dailyot) {
            $days[$dkey]['ot'] = validateNum($weekot);
        } else {
            $days[$dkey]['ot'] = validateNum($dailyot);
        }

        $c++;
    }
    if ($errors) {
        echo "<br />\n";
    }
    foreach ($errors AS $error) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" style=\"color:red\">$error</div>\n";
    }

    $date1 = new DateTime($time->begin);
    $date2 = new DateTime($time->end);

    $diff = $date2->diff($date1)->format("%a") + 1;    
    $daysinperiod = $diff;
    $currentJobId = -1;

    if ($render == 'paychex') {
        // Display as a table
        
        /////////////////////////////
        /////////////////////////////

        echo '<tr>';
            $regulartotal = 0;
            $ottotal = 0;
            $workedtotal = 0;
            $pto1total = 0; // sick/vacation
            $pto2total = 0; // holiday

            // For each day of the period...
            // Here and below, the somewhat odd expression $time->dates[$i]['position'] is 
            //  an index into $days; $days covers only the period, $time->dates can begin earlier
            //  to get a boundary on a new week.
            for ($i = 0; $i < $diff; $i++) {
                $date = $time->dates[$i]['position'];
                if (array_key_exists('worked', $days[$date])) {
                    $mins = validateNum($days[$date]['worked']);
                    $workedtotal += $mins;
                }                
            }

            // Again, for each day of the period...
            for ($i = 0; $i < $diff; $i++) {
                $date = $time->dates[$i]['position'];
                // $regulartotal should leave out any overtime
                if (array_key_exists('worked', $days[$date])) {
                    if (array_key_exists('ot', $days[$date]) && validateNum($days[$date]['ot'])) {
                        $mins = (validateNum($days[$date]['worked'])) - validateNum($days[$date]['ot']);
                    } else {
                        $mins = (validateNum($days[$date]['worked']));
                    }
                } else {
                    $mins = 0;
                }
                $regulartotal += $mins;
            }

            // Again, for each day of the period...
            for ($i = 0; $i < $diff; $i++) {
                $date = $time->dates[$i]['position'];
                if (array_key_exists('ot', $days[$date])) {
                    $mins = validateNum($days[$date]['ot']);
                    $ottotal += $mins;
                }
            }

            // Again, for each day of the period...
            for ($i = 0; $i < $diff; $i++) {
                $date = $time->dates[$i]['position'];
                $mins = 0;
                if (isset($days[$date]['ptobreakdown'])) {
                    if (isset($days[$date]['ptobreakdown'][1])) { // 1 is sick/vacation            
                        $mins = validateNum($days[$date]['ptobreakdown'][1]);
                        $pto1total += $mins;
                    }
                }
            }

            // Again, for each day of the period...
            for ($i = 0; $i < $diff; $i++) {
                $date = $time->dates[$i]['position'];
                $mins = 0;
                if (isset($days[$date]['ptobreakdown'])) {
                    if (isset($days[$date]['ptobreakdown'][2])) { // 2 is holiday
                        $mins = validateNum($days[$date]['ptobreakdown'][2]);
                        $pto2total += $mins;
                    }
                }
            }

            // "ID": employeeId (payroll company stuff).
            echo '<td>' . $employeeId . '</td>';
    
            // "Name"
            echo '<td width="25%">' . $employee->getFirstName() . '&nbsp;' . $employee->getLastName() . '</td>';
            
            $salaryHours = 0;

            if ($payperiodinfo && validateNum($payperiodinfo['salaryHours'])) {
                // "Reg": half a month's salary hours, formatted with two digits past the decimal point.
                // >>>00004 Note deep assumption that salaried employees get paid 2 times a month
                $salaryHours = number_format((($payperiodinfo['salaryHours'] / 12) / 2), 2);
                echo '<td align="center">' . $salaryHours . '</td>';
                
                // For salaried employees, next 3 columns ("OT", "PTO", "Hol") are blank.
                echo '<td width="10%">&nbsp;</td>';
                echo '<td width="10%">&nbsp;</td>';
                echo '<td width="10%">&nbsp;</td>';
            } else {
                // "Reg": regular hours, formatted with two digits past the decimal point.
                if (validateNum($regulartotal)) {
                    echo '<td width="10%" align="center">' . number_format(validateNum($regulartotal)/60, 2, '.', '') . '</td>';
                } else {
                    echo '<td width="10%">&nbsp;&nbsp;&nbsp;</td>';
                }

                // "OT": overtime hours, formatted with two digits past the decimal point.
                if (validateNum($ottotal)) {
                    echo '<td width="10%" align="center">' . number_format(validateNum($ottotal)/60, 2, '.', '') . '</td>';
                } else {
                    echo '<td width="10%">&nbsp;&nbsp;&nbsp;</td>';
                }

                // "PTO": vacation/sick hours, formatted with two digits past the decimal point.
                if (validateNum($pto1total)) {
                    echo '<td width="10%" align="center">' . number_format(validateNum($pto1total)/60, 2, '.', '') . '</td>';
                } else {
                    echo '<td width="10%">&nbsp;&nbsp;&nbsp;</td>';
                }

                if (validateNum($pto2total)) {
                    echo '<td width="10%" align="center">' . number_format(validateNum($pto2total)/60, 2, '.', '') . '</td>';
                } else {
                    echo '<td width="10%">&nbsp;&nbsp;&nbsp;</td>';
                }
            }

            // "Co-Pay": U.S. currency, two digits past the decimal point 
            echo '<td width="10%" align="center">';
            if ($payperiodinfo && $payperiodinfo['copay'] > 0) {
                $d = ($payperiodinfo['copay'] > 0) ? $payperiodinfo['copay'] : 0;
                echo number_format($d, 2);
            }
            echo '</td>';

            $maxDollars = 0;        
            if ($payperiodinfo) {
                if (validateNum($payperiodinfo['salaryHours'])) {
                    // We'll be here only if they are salaried; otherwise $payperiodinfo['salaryHours'] == 0.
                    
                    $so = ((($payperiodinfo['salaryHours'] / 12) / 2) * 60); // half a month's salary hours, converted to minutes
                    
                    // add in these other categories of pay, but I (JM) believe they should be zero for salaried employees
                    // >>>00001 JM someone may want to think over whether this is really what we want here.
                    // Also, note the weird thing we do with $so: convert it back to hours with 2 digits past the decimal point, then back to minutes again.
                    $gt = validateNum(round($so/60, 2) * 60) + validateNum($ottotal) + validateNum($pto1total) + validateNum($pto2total);                
                    
                    $regulartotal = round($so/60, 2) * 60; // convert it back to hours with 2 digits past the decimal point, then back to minutes again.
                
                    // 24 pay periods per year. Apparently presumes IRA is a percentage. This is then the max IRA contribution that the 
                    //  company will match.
                    // >>>00001 JM: But is that correct policy? If someone foregoes IRA contributions for a quarter, then doubles down the next quarter,
                    //  won't the company match the doubling down? >>>00042 Need to talk to Ron & Damon.
                    // Damon says 2020-04-02 this needs to be thought through better at some time, revisit in the future.
                    $maxDollars = ($payperiodinfo['salaryAmount'] / 24) * (COMPANY_IRA_MATCH/100);            
                } else {
                    // Hourly employee. Simpler, similar.
                    // >>>00001 my (JM) question about percentage match applies here, too.
                    $gt = validateNum($regulartotal) + validateNum($ottotal) + validateNum($pto1total) + validateNum($pto2total);    
                    $maxDollars = ($gt/60 * $payperiodinfo['rate'] / 100) * (COMPANY_IRA_MATCH/100);                
                }        
            
                if ($payperiodinfo['iraType'] == IRA_TYPE_PERCENT) {
                    // JM simplified some code here 2020-10-05
                    
                    $payperiodIRAPercent = ($payperiodinfo['ira'] > 0) ? $payperiodinfo['ira'] : 0;
                    
                    // "IRA". Employee's own IRA contribution. Percentage, two digits past the decimal point.
                    echo '<td width="10%" align="center">';
                        if ($payperiodIRAPercent > 0) {
                            echo number_format($payperiodIRAPercent, 2);
                        }
                    echo '</td>';            
                
                    // "Match": Company (customer) IRA contribution. Percentage, two digits past the decimal point.
                    echo '<td width="10%" align="center">';
                        if ($payperiodIRAPercent > 0) {
                            // Cap it at the max percentage the company matches, >>>00001 but as discussed above, is this correct policy?
                            $match = max($payperiodIRAPercent, COMPANY_IRA_MATCH);
                            echo number_format($match, 2);
                        }
                    echo '</td>';
                } else if ($payperiodinfo['iraType'] == IRA_TYPE_DOLLAR) {
                    // JM simplified some code here 2020-10-05
                    
                    $payperiodIRADollars = ($payperiodinfo['ira'] > 0) ? $payperiodinfo['ira'] : 0;
                    
                    // "IRA". Employee's own IRA contribution. U.S. currency, '$' prefixed, two digits past the decimal point.
                    echo '<td width="10%" align="center">';
                        if  ($payperiodinfo['ira'] > 0) {
                            echo '$' . number_format($payperiodIRADollars, 2);
                        }
                    echo '</td>';
                
                    // "Match": Company (customer) IRA contribution. Percentage, two digits past the decimal point.
                    echo '<td width="10%" align="center">';
                        $match = max($payperiodIRADollars, $maxDollars); 
                        echo '$' . number_format($match,  2);
                    echo '</td>';            
                } else {
                    // Apparently, no IRA.
                    // Blank for "IRA", "Match"
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';                
                }
        
                // "Miles", currently always blank
                echo '<td width="10%" align="center">&nbsp;&nbsp;&nbsp;</td>';
    
                // "Total"
                if (validateNum($gt)) {
                    // format $gt as hours, 2 digits past the decimal point.
                    echo '<td width="10%" align="center">' . number_format(validateNum($gt)/60, 2, '.', '') . '</td>';
                } else {
                    // Blank
                    echo '<td width="10%">&nbsp;&nbsp;&nbsp;</td>';    
                }
                
                // "Signoff" (employee part)
                if ($payperiodinfo['initialSignoffTime'] === null) {
                    if ($payperiodinfo['readyForSignoff'] === null) {
                        echo '<td width="15%">&nbsp;</td>';
                    } else {
                        echo '<td width="15%" style="font-weight:bold; background-color:lightblue">Pending</td>';
                        $requiring_signoff[] = $employee->getFirstName() . '&nbsp;' . $employee->getLastName();
                    }                    
                } else if ($payperiodinfo['reopenTime'] !== null) {
                    echo '<td width="15%" style="font-weight:bold; background-color:lightpink">Reopened</td>';
                    $requiring_signoff[] = $employee->getFirstName() . '&nbsp;' . $employee->getLastName();
                } else if ( $payperiodinfo['adminSignedPayrollTime'] !== null && 
                    $payperiodinfo['lastSignoffTime'] > $payperiodinfo['adminSignedPayrollTime']) 
                {
                    $netLateWotMods = $time->getWorkOrderTaskTimeNetLateModifications();
                    $netLatePtoMods = $time->getPtoNetLateModifications();
                    $oldMinutes = 0;
                    $newMinutes = 0;
                    foreach ($netLateWotMods as $mod) {
                        $oldMinutes += $mod['oldMinutes'];
                        $newMinutes += $mod['newMinutes'];
                    }
                    foreach ($netLatePtoMods as $mod) {
                        $oldMinutes += $mod['oldMinutes'];
                        $newMinutes += $mod['newMinutes'];
                    }
                    $change = 'net: 0';
                    if ($newMinutes > $oldMinutes) {
                        $change = '+' . number_format(validateNum($newMinutes-$oldMinutes)/60, 2, '.', '') . ' hr';
                    } else if  ($oldMinutes > $newMinutes) {
                        $change = '-' . number_format(validateNum($oldMinutes-$newMinutes)/60, 2, '.', '') . ' hr';
                    }
                    echo '<td width="15%">Modified (' . $change . ')</td>';
                } else {
                    echo '<td width="15%">Completed</td>';
                }
                
                // "Signoff" (admin part)
                if ($payperiodinfo && 
                    $payperiodinfo['reopenTime'] === null && 
                    ( 
                      $payperiodinfo['adminSignedPayrollTime'] === null ||
                      ( ($payperiodinfo['lastSignoffTime'] !== null) && 
                          ($payperiodinfo['adminSignedPayrollTime'] < $payperiodinfo['lastSignoffTime'])
                      )
                    )
                  ) 
                {
                    echo '<td width="15%"><button class="accept-timesheet" data-employee="' . $employee->getCustomerPersonId() . '">Accept</button></td>';
                } else if ($payperiodinfo && $payperiodinfo['reopenTime'] === null && $payperiodinfo['adminSignedPayrollTime'] !== null) {
                    echo '<td width="15%"><button class="rescind-timesheet" data-employee="' . $employee->getCustomerPersonId() . '">Rescind</button></td>';
                } else {
                    echo '<td width="15%">&nbsp;</td>';
                }
    
                $colreg += validateNum($regulartotal);
                
                $colot += validateNum($ottotal);
                $colpto += validateNum($pto1total);
                $colhol += validateNum($pto2total);
            } else {
                echo '<td colspan="5">&nbsp;</td>';
            }
            echo '</tr>';
            ///////////////////////
            ///////////////////////
            
            // NOTE that this table will be continued below.

    } else if ($render == 'expanded') {
        // BEGIN ADDED 2020-10-09 JM
        $lateModificationsCount = 0;
        $lateModificationsMinutes = 0;
        $ptoModifications2 = Array();
        $wotModifications2 = Array();
        
        if ($payperiodinfo && $payperiodinfo['lastSignoffTime'] !== null &&
            $payperiodinfo['reopenTime'] === null && $payperiodinfo['adminSignedPayrollTime'] !== null &&
            $payperiodinfo['adminSignedPayrollTime'] < $payperiodinfo['lastSignoffTime'] )
        {
            // Check for late modifications (after admin signoff). We clear these on admin signoff.
            $wotLateModifications = $time->getWorkOrderTaskTimeLateModifications();
            $ptoLateModifications = $time->getPtoLateModifications();
            $lateModificationsCount = count($wotLateModifications) + count($ptoLateModifications);
            
            foreach($wotLateModifications as $wotLateModification) {
                $lateModificationsMinutes = $lateModificationsMinutes + $wotLateModification['newMinutes'] - $wotLateModification['oldMinutes'];  
            }
            foreach($ptoLateModifications as $ptoLateModification) {
                $lateModificationsMinutes = $lateModificationsMinutes + $ptoLateModification['newMinutes'] - $ptoLateModification['oldMinutes'];  
            }
            
            // Organize these in a more useful manner
            if (isset($ptoLateModifications) && count($ptoLateModifications)) {
                // NOTE that the sequence of $ptoLateModifications guarantees that changes in the data for a given day 
                //  are listed in forward chronological order by when they were changed. 
                // >>>00026 2020-10-08 JM: haven't yet accounted for admin changing these values downstream of employee.
                //  Not sure if that affects anything here or not.
                foreach ($ptoLateModifications as $ptoLateModification) {
                    $dayOfPeriod = $ptoLateModification['dayOfPeriod'];
                    if (!isset($ptoModifications2[$dayOfPeriod])) {
                        $ptoModifications2[$dayOfPeriod] = Array();
                    }
                    $ptoModifications2[$dayOfPeriod][] = $ptoLateModification;  
                }
                unset ($dayOfPeriod);
                // Get rid of any that net out to no change:
                foreach ($ptoModifications2 as $dayOfPeriod => $dayArray) {
                    if ($dayArray[0]['oldMinutes'] == $dayArray[count($dayArray)-1]['newMinutes']) {
                        unset($ptoModifications2[$dayOfPeriod]);
                    }
                }
                unset ($dayOfPeriod);
            }
            if (isset($wotLateModifications) && count($wotLateModifications)) {
                // NOTE that the sequence of $wotLateModifications guarantees that changes in the data for a given 
                //  workOrderTask and day are listed in forward chronological order by when they were changed. 
                // >>>00026 2020-10-08 JM: haven't yet accounted for admin changing these values downstream of employee.
                //  Not sure if that affects anything here or not.
                foreach ($wotLateModifications as $wotLateModification) {
                    $dayOfPeriod = $wotLateModification['dayOfPeriod'];
                    $workOrderTaskId = $wotLateModification['workOrderTaskId'];
                    if (!isset($wotModifications2[$dayOfPeriod])) {
                        $wotModifications2[$workOrderTaskId] = Array();
                    }
                    if (!isset($wotModifications2[$workOrderTaskId][$dayOfPeriod])) {
                        $wotModifications2[$workOrderTaskId][$dayOfPeriod] = Array();
                    }
                    $wotModifications2[$workOrderTaskId][$dayOfPeriod][] = $wotLateModification;  
                }
                unset ($dayOfPeriod);
                // Get rid of any that net out to no change:
                foreach ($wotModifications2 as $workOrderTaskId => $wotArray) {
                    foreach ($wotArray as $dayOfPeriod => $dayArray) {
                        if ($dayArray[0]['oldMinutes'] == $dayArray[count($dayArray)-1]['newMinutes']) {
                            unset($wotModifications2[$workOrderTaskId][$dayOfPeriod]);
                        }
                    }
                    unset($dayOfPeriod);
                    if (count($wotModifications2[$workOrderTaskId]) == 0) {
                        unset($wotModifications2[$workOrderTaskId]);
                    }
                }
                unset ($workOrderTaskId);
            }
        }
        // END ADDED 2020-10-09 JM
        
        // Heading: employee name
        echo '<h1>' . $employee->getFirstName() . '&nbsp;' . $employee->getLastName() . '</h1>';
        
        $offerAdminSignoff = false; // initializing, value is set below
        $offerRescindAdminSignoff = false; // initializing, value is set below
        $bgcolor = 'white'; // This background is just for the totals displayed as the first numbers for the employee, above anything
                            // specific to what work the time was allocated to.
        
        // Several cases added to the following 2020-10-09 JM                    
        if (!$payperiodinfo) {
            echo '<div>No payperiod data for this period.</div>';
        } else if ($payperiodinfo['initialSignoffTime'] === null) {
            // per conversation with Damon 2020-10-13, allow admin to sign off even if employee did not.
            $offerAdminSignoff = $payperiodinfo['adminSignedPayrollTime'] === null; 
            $offerRescindAdminSignoff = !$offerAdminSignoff;
            echo '<div>';
            if ($payperiodinfo['readyForSignoff'] === null) {
                echo 'Employee was not prompted to sign off their time for this period.';
            } else {
                echo 'Employee <span style="color:red">has not</span> signed off their time for this period.';
                if ($offerAdminSignoff) {
                    $bgcolor = 'lightblue';
                    $requiring_signoff[] = $employee->getFirstName() . '&nbsp;' . $employee->getLastName();
                }
            }
            if ($offerRescindAdminSignoff) {
                echo '<br />Admin has already accepted the timesheet.';
            }
            echo '</div>';
        } else if ($payperiodinfo['reopenTime'] !== null) {
            // Employee signed off earlier, but is currently editing.
            if ($payperiodinfo['adminSignedPayrollTime'] === null) {
                echo '<div>Employee has signed off their time for this period but reopened for further editing.</div>';
            } else if ($payperiodinfo['reopenTime'] >= $payperiodinfo['adminSignedPayrollTime']) {
                echo '<div>Admin signed off employee\'s time for this period, but <span style="color:red">employee is editing again</span>.</div>';
            } else {
                // Shouldn't happen.
                echo '<div><span style="color:red">WARNING: it appears that admin signed off employee\'s time for this period while employee was editing it.</span>.</div>';
            }
            $bgcolor = 'redbrick';
            $requiring_signoff[] = $employee->getFirstName() . '&nbsp;' . $employee->getLastName();
        } else if ($payperiodinfo['adminSignedPayrollTime'] !== null) {
            // Admin & employee have already signed off for this period, and employee is not currently editing.
            if ($payperiodinfo['adminSignedPayrollTime'] >= $payperiodinfo['lastSignoffTime']) {
                echo '<div>Time for this period is fully signed off.</div>';
                $offerRescindAdminSignoff = true;
            } else {
                $offerAdminSignoff = true;
                if ($lateModificationsCount == 0) {
                    '<div>Time for this period was signed off; employee opened to make further edits, but did not make any.</div>';
                } else if ($lateModificationsMinutes == 0) {
                    echo '<div>Time for this period was signed off; employee made further edits, but the net time is the same.</div>';
                } else {
                    echo '<div>Time for this period was signed off; employee made further edits, ';
                    if ($lateModificationsMinutes > 0) {
                        'adding ' . number_format(validateNum($lateModificationsMinutes)/60, 2, '.', '') . 'hours.';
                    } else {
                        'reducing their time by  ' . number_format(validateNum(-$lateModificationsMinutes)/60, 2, '.', '') . 'hours.';
                    }
                    echo '</div>';
                    $bgcolor = '#fff6f5';  // This is even lighter than 'mistyrose'; we want just a "suggestion" of pink here, sort of a "blush"
                }                
            }
        } else {
            echo '<div>Employee has signed off their time for this period.</div>';
            $offerAdminSignoff = true;
        }
        if ($offerAdminSignoff) {
            echo '<button class="accept-timesheet" data-employee="' . $employee->getCustomerPersonId() . '">Accept timesheet</button>';
        } else if ($offerRescindAdminSignoff) {
            echo '<button class="rescind-timesheet" data-employee="' . $employee->getCustomerPersonId() . '">Rescind acceptance</button>';
        }              

        // Table for this one employee
        echo '<table border="0" cellpadding="2" cellspacing="1">';
            echo '<tr>';
                // colspan here is actually 1 less than width of table. That is actually deliberate, and looks good here.
                echo '<th style="background-color:#dddddd;text-align:left" colspan="' . (4 + $daysinperiod) . '" style="text-align:left;">Summary</th>';
            echo '</tr>';

            // Headings split across 2 rows
            echo '<tr class="headings">';
                echo '<td colspan="4">&nbsp;</td>'; // 4 columns with no heading
                for ($i = 0; $i < $diff; $i++) {
                    // "short date" (e.g. '05-24') 
                    echo '<th>' . $time->dates[$i]['short'] . '</th>';
                }
                echo '<td>&nbsp;</td>'; // 1 column with no heading
            echo '</tr>';
            echo '<tr class="headings">';
                echo '<td colspan="4">&nbsp;</td>'; // 4 columns with no heading
                for ($i = 0; $i < $diff; $i++) {   
                    // day of week ("Mon", "Tue", etc.) 
                    $x = new DateTime($time->dates[$i]['position']);
                    echo '<th>' . $x->format("D") . '</th>'; // 1 column with no heading
                }
                echo '<td>&nbsp;</td>';
            echo '</tr>';

            $regulartotal = 0;
            $ottotal = 0;
            $workedtotal = 0;
            $pto1total = 0;
            $pto2total = 0;

            // Total worked hours
            echo '<tr class="datarow">';
                echo '<td colspan="4">Worked</td>'; // 4 columns
                // For each day in period...
                for ($i = 0; $i < $diff; $i++) {
                    // Here and below, the somewhat odd expression $time->dates[$i]['position'] is 
                    //  an index into $days; $days covers only the period, $time->dates can begin earlier
                    //  to get a boundary on a new week.
                    $date = $time->dates[$i]['position'];
                    if (array_key_exists('worked', $days[$date])) {
                        $mins = validateNum($days[$date]['worked']);
                        $workedtotal += $mins;
                    } else {
                        $mins = '0';
                    }
                    if (validateNum($mins)) {
                        // hours, two digits past the decimal point.
                        echo '<td style="text-align:center; background-color:' . $bgcolor . '">' . number_format(validateNum($mins)/60, 2, '.', '') . '</td>';
                    } else {
                        echo '<td>&nbsp;</td>';
                    }
                }
                echo '<td class="totalcell">' . number_format(validateNum($workedtotal)/60, 2, '.', '') . '</td>'; // Total
            echo '</tr>';

            // Similarly, omitting overtime.
            echo '<tr class="datarow">';
                echo '<td colspan="4">Regular</td>'; // 4 columns
                for ($i = 0; $i < $diff; $i++) {
                    $date = $time->dates[$i]['position'];
                    if (array_key_exists('worked', $days[$date])) {
                        if (array_key_exists('ot', $days[$date]) && validateNum($days[$date]['ot'])) {
                            $mins = (validateNum($days[$date]['worked'])) - validateNum($days[$date]['ot']);
                        } else {
                            $mins = (validateNum($days[$date]['worked']));
                        }
                    } else {
                        $mins = '0';
                    }
        
                    $regulartotal += $mins;
                    if (validateNum($mins)) {
                        echo '<td style="text-align:center; background-color:' . $bgcolor . ';">' . number_format(validateNum($mins)/60, 2, '.', '') . '</td>';
                    } else {
                        echo '<td>&nbsp;</td>';
                    }
                }
                echo '<td class="totalcell">' . number_format(validateNum($regulartotal)/60, 2, '.', '') . '</td>'; // Total
            echo '</tr>';

            // Similarly, just overtime.
            echo '<tr class="datarow">';
                echo '<td colspan="4">OT</td>'; // 4 columns
                for ($i = 0; $i < $diff; $i++) {
                    $date = $time->dates[$i]['position'];
                    if (array_key_exists('ot', $days[$date])) {
                        $mins = validateNum($days[$date]['ot']);
                    } else {
                        $mins = 0;
                    }
                    $ottotal += $mins;
                    if (validateNum($mins)) {
                        echo '<td style="text-align:center; background-color:' . $bgcolor . ';">' . number_format(validateNum($mins)/60, 2, '.', '') . '</td>';
                    } else {
                        echo '<td>&nbsp;</td>';
                    }
                }
                echo '<td class="totalcell">' . number_format(validateNum($ottotal)/60, 2, '.', '') . '</td>'; // total
            echo '</tr>';

            // vacation/sick
            echo '<tr class="datarow">';
                echo '<td colspan="4">PTO</td>'; // 4 columns
                for ($i = 0; $i < $diff; $i++) {
                    $date = $time->dates[$i]['position'];
                    $mins = 0;
                    if (isset($days[$date]['ptobreakdown'])) {
                        if (isset($days[$date]['ptobreakdown'][1])) {
                            $mins = validateNum($days[$date]['ptobreakdown'][1]);
                            $pto1total += $mins;
                        }
                    }
                    // The following is significantly reworked 2020-10-08 JM
                    // If the employee modified any PTO for this period, then this row is really the only place we can indicate 
                    //  just what they changed. If it changed, we will want a pink background and the ability to hover and get more information.
                    // >>>00026 2020-10-08 JM: haven't yet accounted for admin changing these values downstream of employee.
                    //  Not sure if that affects any code here or not.
                    $modified = isset($ptoModifications2[$i]) && is_array($ptoModifications2[$i]);
                    echo '<td ';
                    if ($modified) {
                        echo 'class="expand-modified" data-report="';
                        foreach ($ptoModifications2[$i] as $modification) {
                            echo '<b>' . number_format(validateNum($modification['oldMinutes'])/60, 2, '.', '') . ' hr</b> changed to <b>' ;  
                            echo number_format(validateNum($modification['newMinutes'])/60, 2, '.', '') . ' hr</b> <small>(' . $modification['inserted'] . ')</small><br>';
                        }
                        echo '"';
                    }
                    if (validateNum($mins)) {
                        echo 'style="text-align:center; background-color:' . 
                                ($modified ? 'lightpink' : $bgcolor) . ';">' .  
                                number_format(validateNum($mins)/60, 2, '.', ''); 
                    } else {
                        echo 'style="background-color:' . 
                                ($modified ? 'lightpink' : 'white') . ';">' .  
                                '&nbsp;</td>';
                    }
                    echo '</td>';
                    // end significantly reworked 2020-10-08 JM
                }
                echo '<td class="totalcell">' . number_format(validateNum($pto1total)/60, 2, '.', '') . '</td>'; // total
            echo '</tr>';

            // company holiday
            echo '<tr class="datarow">';
                echo '<td colspan="4">Holiday</td>'; // 4 columns
                for ($i = 0; $i < $diff; $i++) {
                    $date = $time->dates[$i]['position'];
                    $mins = 0;
                    if (isset($days[$date]['ptobreakdown'])) {
                        if (isset($days[$date]['ptobreakdown'][2])) {
                            $mins = validateNum($days[$date]['ptobreakdown'][2]);
                            $pto2total += $mins;
                        }
                    }
                    if (validateNum($mins)) {
                        echo '<td style="text-align:center; background-color:' . $bgcolor . ';">' . number_format(validateNum($mins)/60, 2, '.', '') . '</td>';
                    } else {
                        echo '<td>&nbsp;</td>';
                    }
                }
                echo '<td class="totalcell">' . number_format(validateNum($pto2total)/60, 2, '.', '') . '</td>'; // total
            echo '</tr>';

            // add all categories of pay
            $gt = validateNum($regulartotal) + validateNum($ottotal) + validateNum($pto1total) + validateNum($pto2total);

            echo '<tr>';
                echo '<th style="text-align:right" colspan="' . (4 + $daysinperiod) . '" style="text-align:left;">TOTAL</th>'; // all columns except the last
                echo '<th>' . number_format(validateNum($gt)/60, 2, '.', '') . '</th>';
            echo '</tr>';

            echo '<tr>';
                echo '<td colspan="' . (5 + $daysinperiod) . '">&nbsp;</td>'; // all columns, blank
            echo '</tr>';

            $cols = array();
            $sorts = array();
    
            // query the database for data per workOrderTask.            
            $query  = "SELECT wott.*, wot.billingDescription, wo.description, j.name, j.number, j.jobId, wo.workOrderId, wot.billingDescription ";
            $query .= "FROM " . DB__NEW_DATABASE . ".workOrderTaskTime wott ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTask wot on wott.workOrderTaskId = wot.workOrderTaskId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrder wo on wot.workOrderId = wo.workOrderId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
            $end = end($time->dates);
            $query .= "WHERE wott.day between '" . $time->dates[0]['position'] . "' and '" . $end['position'] . "' and wot.taskId in (select taskId from task)";
            $query .= "AND wott.personId = " . intval($employee->getUserId()) . ";";
    
            $result = $db->query($query);
            if (!$result) {
                 $logger->errorDb('1601923684', 'Hard DB failure', $db);
                 echo '<p>Hard DB failure, see log.</p></body></html>';
                 die();
            } else {
                while ($row = $result->fetch_assoc()) {
                    if (!array_key_exists($row['jobId'], $sorts)) {  
                        $sorts[$row['jobId']] = Array(); // initialize array before using it!
                        $sorts[$row['jobId']]['jobId'] = $row['jobId'];
                        $sorts[$row['jobId']]['name'] = $row['name'];
                        $sorts[$row['jobId']]['number'] = $row['number'];
                        $sorts[$row['jobId']]['workOrders'] = Array(); // initialize array before using it!
                    }
                    if (!array_key_exists($row['workOrderId'], $sorts[$row['jobId']]['workOrders'])) {
                        $sorts[$row['jobId']]['workOrders'][$row['workOrderId']] = Array(); // initialize array before using it!
                        $sorts[$row['jobId']]['workOrders'][$row['workOrderId']]['workOrderId'] = $row['workOrderId'];
                        $sorts[$row['jobId']]['workOrders'][$row['workOrderId']]['description'] = $row['description'];
                        $sorts[$row['jobId']]['workOrders'][$row['workOrderId']]['workOrderTasks'] = Array(); // initialize array before using it!
                    }
                    
                    if (!array_key_exists($row['workOrderTaskId'], $sorts[$row['jobId']]['workOrders'][$row['workOrderId']]['workOrderTasks'])) {
                        $sorts[$row['jobId']]['workOrders'][$row['workOrderId']]['workOrderTasks'][$row['workOrderTaskId']] = Array(); // initialize array before using it!
                        $sorts[$row['jobId']]['workOrders'][$row['workOrderId']][$row['workOrderTaskId']]['days'] = Array(); // initialize array before using it!
                    }
                    $sorts[$row['jobId']]['workOrders'][$row['workOrderId']]['workOrderTasks'][$row['workOrderTaskId']]['days'][$row['day']] = $row;

                    if (!array_key_exists($row['day'], $cols)) {
                        $cols[$row['day']] = 0;
                    }

                    $cols[$row['day']] += intval($row['minutes']);
                }
            }
            
            /* So at this point we have
                * $sorts: array indexed by jobId. Each element is an associative array with indexes:
                    * 'jobId'
                    * 'name'
                    * 'number'
                    * 'workorders': array indexed by workOrderId. Each element is an associative array with indexes:
                        * 'workOrderId'
                        * 'description'
                        * 'workOrderTasks': array indexed by workOrderTaskId. Each element is an associative array with the single index:
                            * 'days': associative array indexed by date in form 'YYYY-mm-dd'. Each element is an associative array containing 
                              the canonical representation of the return of the query above:
                                * 'workOrderTaskTimeId' (from DB table WorkOrderTaskTime)
                                * 'workOrderTaskId' (from DB table WorkOrderTaskTime)
                                * 'personID' (from DB table WorkOrderTaskTime)
                                * 'day' (from DB table WorkOrderTaskTime)
                                * 'minutes' (from DB table WorkOrderTaskTime)
                                * 'tiiHrs' (from DB table WorkOrderTaskTime)
                                * 'billingDescription' (from DB table WorkOrderTask)
                                * 'description' (from DB table WorkOrder)
                                * 'name': job name (from DB table Job)
                                * 'number: Job Number (from DB table Job)
                                * 'jobId'  (from DB table Job)
                                * 'workOrderId' (from DB table WorkOrder)
                                * 'billingDescription' (from DB table WorkOrderTask)
                * $cols: array indexed by date in form 'YYYY-mm-dd': total minutes for that day, across all workOrders
                
                Note that below, the somewhat odd expression $time->dates[$i]['position'] is 
                an index into various arrays; that is because these arrays cover only the period, 
                while $time->dates can begin earlierto get a boundary on a new week.                
            */

            // If there was some time on tasks...
            if (count($cols)) {
                echo '<tr>';    
                    echo '<td colspan="4">&nbsp;</td>'; // 4 columns with no heading        
                    for ($i = 0; $i < $diff; $i++) {
                        // "short date" (e.g. '05-24') 
                        echo '<td  bgcolor="#cccccc"><b>' . $time->dates[$i]['short'] . '</b></td>';        
                    }    
                    echo '<td>&nbsp;</td>'; // 1 column with no heading
                echo '</tr>';

                echo '<tr>';
                    echo '<td colspan="4">&nbsp;</td>'; // 4 columns with no heading; >>>00001 JM: I'd expect something to indicate that this was across all workOrders
                    for ($i = 0; $i < $diff; $i++) {
                        $date = $time->dates[$i]['position'];
                        if (array_key_exists($date, $cols)) {
                            // convert time (across all workOrders) to hours with two digits past the decimal point
                            echo '<td  bgcolor="#cccccc" align="center">' . number_format(validateNum($cols[$date])/60, 2, '.', '') . '</td>';    
                        } else {    
                            echo '<td  bgcolor="#cccccc">&nbsp;</td>';    
                        }    
                    }    
                    echo '<td>&nbsp;</td>'; // 1 column with no heading
                echo '</tr>';
            }

            // For each job...
            foreach ($sorts as $sort) {    
                echo '<tr bgcolor="#add8e6">';    
                    $j = new Job($sort['jobId']);  
                    // 4 columns: Link to open Job in new window; displays Job Number, job name
                    echo '<td colspan="4">[<a target="_blank" href="' . $j->buildLink() . '">' . $sort['number'] . '</a>]&nbsp;' . $sort['name'] . '</td>' ;
                    // the rest of the columns blank
                    echo '<td colspan="' . ($daysinperiod + 1) . '">&nbsp;</td>';    
                echo '</tr>';
    
                if (isset($sort['workOrders'])) {
                    // For each workOrder in the job...
                    foreach ($sort['workOrders'] as $workOrder) {    
                        echo '<tr bgcolor="#ffa500">';    
                            echo '<td>&nbsp;</td>'; // one blank column to indent
                            echo '<td colspan="3">' . $workOrder['description'] . '</td>'; // workOrder description spanning 3 columns
                            echo '<td colspan="' . ($daysinperiod + 1) . '">&nbsp;</td>'; // blanks in all the other columns
                        echo '</tr>';    
                        if (isset($workOrder['workOrderTasks'])) {
                            $c = 0;
                            // for each workOrderTask in the workOrder; before 2020-10-09 $workOrderTaskId was $wotkey, but let's call it what it is! 
                            foreach ($workOrder['workOrderTasks'] as $workOrderTaskId => $workOrderTask) {    
                                $color = ($c++ % 2) ? '#ffffff' : '#eeeeee';    
                                echo '<tr bgcolor="' . $color . '">';    
                                    $w = new WorkOrderTask($workOrderTaskId); // construct WorkOrderTask object.

                                    $desc = '';
                                    //print_r($w->getTask());
                                   
                                    $desc = $w->getTask()->getDescription();  // task description
                                    
                                    /* BEGIN MARTIN COMMENT
                                    +---------------------+-----------------+----------+------------+---------+--------+
                                    | workOrderTaskTimeId | workOrderTaskId | personId | day        | minutes | tiiHrs |
                                    +---------------------+-----------------+----------+------------+---------+--------+
                                    |               21185 |               0 |     2142 | 2017-10-02 |     840 |   NULL | eileen
                                    |               21186 |               0 |     2142 | 2017-10-04 |     300 |   NULL |
                                    |               21221 |               0 |     2043 | 2017-10-01 |      75 |   NULL |
                                    |               21222 |               0 |     2043 | 2017-09-29 |      15 |   NULL |
                                    |               21229 |               0 |     2272 | 2017-10-05 |     480 |   NULL | janice
                                    |               21231 |               0 |     1638 | 2017-10-06 |     360 |   NULL | easton
                                    +---------------------+-----------------+----------+------------+---------+--------+
                                    END MARTIN COMMENT
                                    */
                                    echo '<td colspan="2">' . $desc . '</td>'; // task description, 2 columns
                                    if (isset($workOrderTask['days'])) {
                                        // For each day in the period
                                        for ($i = 0; $i < $diff; $i++) {
                                            $date = $time->dates[$i]['position'];
                                            // The following is significantly reworked 2020-10-08 JM to deal with whether
                                            //  employee modified this time after the admin had signed off the timesheet.
                                            //  If it changed, we will want a pink background and the ability to hover and get more information.
                                            // >>>00026 2020-10-08 JM: haven't yet accounted for admin changing these values downstream of employee.
                                            //  Not sure if that affects any code here or not.
                                            $modified = isset($wotModifications2[$workOrderTaskId][$i]) && is_array($wotModifications2[$workOrderTaskId][$i]);
                                            echo '<td align="center" ';
                                            if ($modified) {
                                                echo 'class="expand-modified" data-report="';
                                                foreach ($wotModifications2[$workOrderTaskId][$i] as $modification) {
                                                    echo '<b>' . number_format(validateNum($modification['oldMinutes'])/60, 2, '.', '') . ' hr</b> changed to <b>' ;  
                                                    echo number_format(validateNum($modification['newMinutes'])/60, 2, '.', '') . ' hr</b> <small>(' . $modification['inserted'] . ')</small><br>';
                                                }
                                                echo '" ';
                                                echo 'style="background-color:lightpink">';
                                            } else {
                                                echo '>';
                                            }
                                            if (array_key_exists($date, $workOrderTask['days'])) {
                                                // time on task, expressed in hours with 2 digits past the decimal point.
                                                $mins = intval($workOrderTask['days'][$date]['minutes']);
                                                echo number_format(validateNum($mins)/60, 2, '.', '');
                                            } else {
                                                echo '&nbsp;';
                                            }
                                            echo '</td>';
                                        }
                                        echo '<td>&nbsp;</td>'; // blank column
                                    } else {
                                        echo '<td colspan="' . ($daysinperiod + 1) . '">&nbsp;</td>'; // blank the rest (first 4 columns were covered above)
                                    }
                                echo '</tr>';
                            }
                        } // END if (isset($workOrder['workOrderTasks']))
                    }
                }
            } // END foreach ($sorts...

            // If there was some time on tasks...
            if (count($cols)) {
                // Reiterate the same "totals" row we wrote above the task-by-task breakdown
                echo '<tr>';    
                    echo '<td colspan="4">&nbsp;</td>';    
                    for ($i = 0; $i < $diff; $i++) {
                        $date = $time->dates[$i]['position'];
                        if (array_key_exists($date, $cols)) {
                            // convert time (across all workOrders) to hours with two digits past the decimal point
                            echo '<td  bgcolor="#cccccc" align="center">' . number_format(validateNum($cols[$date])/60, 2, '.', '') . '</td>';        
                        } else {        
                            echo '<td  bgcolor="#cccccc">&nbsp;</td>';        
                        }        
                    }
                    echo '<td>&nbsp;</td>';
                echo '</tr>';

                // Reiterate short dates
                echo '<tr>';
                    echo '<td colspan="4">&nbsp;</td>';
                    for ($i = 0; $i < $diff; $i++) {
                        echo '<td  bgcolor="#cccccc"><b>' . $time->dates[$i]['short'] . '</b></td>';
                    }
                    echo '<td>&nbsp;</td>';
                echo '</tr>';
            }
        echo '</table>';
    } // END if ($render == 'expanded')
} // END foreach ($employees...

if ($render == 'paychex') {
    // RESUMING IN THE SAME TABLE AS ABOVE FOR paychex SCENARIO.
    
    // Write totals.
    echo '<tr class="headings">';
        echo '<td></td>'; // "ID"
        echo '<td></td>'; // "Name"
        echo '<td style="font-weight:bold">' . number_format(validateNum($colreg)/60, 2, '.', '') . '</td>'; // "Reg"
        echo '<td style="font-weight:bold">' . number_format(validateNum($colot)/60, 2, '.', '') . '</td>';  // "OT"
        echo '<td style="font-weight:bold">' . number_format(validateNum($colpto)/60, 2, '.', '') . '</td>'; // "PTO"
        echo '<td style="font-weight:bold">' . number_format(validateNum($colhol)/60, 2, '.', '') . '</td>'; // "Hol"
        $colgt = validateNum($colreg) + validateNum($colot) + validateNum($colpto) + validateNum($colhol);
        echo '<td>&nbsp;</td>'; // "Co-Pay"
        echo '<td>&nbsp;</td>'; // "IRA"
        echo '<td>&nbsp;</td>'; // "Match"
        echo '<td>&nbsp;</td>'; // "Miles"
        echo '<td>' . number_format(validateNum($colgt)/60, 2, '.', '') . '</td>'; // "Total"
    echo '</tr>';

    echo '<tr>';
        echo '<td colspan="11">&nbsp;</td>'; // blank row
    echo '</tr>';

    echo '<tr class="headings">';
        echo '<td></td>';
        echo '<td></td>';
    
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
    
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
        echo '<th>&nbsp;</th>';
    echo '</tr>';

    echo '<tr class="datarow">';
        echo '<td>&nbsp;</td>';
        echo '<td>&nbsp;</td>';
        echo '<td>&nbsp;</td>';
    
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
    
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
    echo '</tr>';

    // Looks like the following totally multiplexes the table columns, using them just for layout.
    echo '<tr class="headings">';
        echo '<td colspan="7">&nbsp;</td>';
        echo '<th colspan="2">CASH REQ.</th>';
        echo '<th colspan="2">&nbsp;</th>';
    echo '</tr>';

    // FORM to self-submit with POST method 
    //  Despite "update" in name, this *adds* a note; no way to get rid of any old ones.
    //  >>>00001: see also remarks on the corresponding form, this probably deserves study. The experience at the UI
    //  is that there is a single note, and you can edit it, but it looks like apparently something more complicated is
    //  going on in the DB, since $act == 'updatenotes' above always does a SQL INSERT, not an UPDATE. 
    echo '<form name="updateNotes" action="summary.php" method="POST">';
        echo '<input type="hidden" name="displayType" value="' . $displayType  . '">'; // just to preserve display
        echo '<input type="hidden" name="render" value="' . $render . '">'; // just to preserve display
        echo '<input type="hidden" name="start" value="' . $start . '">'; // what you are making a note about
        echo '<input type="hidden" name="act" value="updatenotes">';

        echo '<tr>';
            echo '<td colspan="11">';
            /*
            // BEGIN MARTIN COMMENT
            create table payPeriodNotes(
                payPeriodNotesId int unsigned not null primary key auto_increment,
                periodBegin      date not null,
                notes            text
            );
            // END MARTIN COMMENT
            */

            $notes = '';

            $query  = "SELECT * ";
            $query .= "FROM " . DB__NEW_DATABASE . ".payPeriodNotes ";
            $query .= "WHERE periodBegin = '" . $db->real_escape_string($start) . "';";

            $result = $db->query($query);
            if (!$result) {
                 $logger->errorDb('1601924141', 'Hard DB failure getting notes', $db);
                 echo '<p>Hard DB failure getting notes, see log.</p></body></html>';
                 die();
            }            
            while ($row = $result->fetch_assoc()) {
                // >>>00026: very weird. We keep overwriting in the while loop, so we use only the last,
                //  but there is nothing to put these in any particular order.
                $notes = $row['notes'];
            }

            ?>
            <div style="display: block;" id="rulesformitem" class="formitem">
                <label for="rules" id="ruleslabel">NOTES:</label> <?php /* >>>00031 for="rules", but there is no such id anywhere!*/ ?>
                <?php /* >>>00026 cols="2" is super narrow, can we really mean that? Something seems to override it...*/ ?>
                <div class="textwrapper"><textarea cols="2" rows="10" name="notes"><?php echo htmlspecialchars($notes); ?></textarea></div>
            </div>
            <input type="submit" name="Update Notes" value="Update Notes" border="0">
            <?php
            echo '</td>';
        echo '</tr>';
    echo '</form>';
    
    echo '</tbody>';
    echo '</table>';
} // END if ($render == 'paychex')

if ($requiring_signoff) {
    $num_remaining = count($requiring_signoff); 
    $html = $num_remaining . ' employees still need to sign off their time';
    $html .= ': ';
    foreach ($requiring_signoff AS $i => $personName) {
        if ($i > 0) {
            $html .= ', ';
        }
        $html .= $personName;
    }
    $html .= '.<br />';
    
    echo "\n<br /><script>$('#signoff-status').html('$html');</script>\n";
}
?>
<script>
<?php /* BEGIN ADDED 2020-10-09 JM */ ?>
$(function() {
    // On document ready...
    
    // If we've saved a position, scroll to it.
    let scrollTopMain = sessionStorage.getItem('admin_summary_scrollTopMain');
    $(document).scrollTop(scrollTopMain);
    sessionStorage.removeItem('admin_summary_scrollTopMain');
    
    $('button.accept-timesheet').click(function() {
        // Set customerPersonPayPeriodInfo.adminSignedPayrollTime for correct row    
        let $this = $(this);
        let customerPersonId = $this.data('employee');
        let start = '<?= $start ?>';
        
        $.ajax({
            url: '/_admin/ajax/accepttimesheet.php',
            data: {
                customerPersonId : customerPersonId,
                periodBegin : start
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'reopened') {
                        alert('Employee has reopened their timesheet, so you cannot accept at this time. Page will reload after you close this alert.');
                        let scrollTopMain = $(document).scrollTop();
                        sessionStorage.setItem('admin_summary_scrollTopMain', scrollTopMain);
                        location.href = 'summary.php?start=<?= $start ?>&displayType=<?= $displayType ?>&render=<?= $render ?>';
                    } else if (data['status'] == 'success') { 
                        // On success, reload page (saving position)
                        // save position and refresh
                        let scrollTopMain = $(document).scrollTop();
                        sessionStorage.setItem('admin_summary_scrollTopMain', scrollTopMain);
                        location.href = 'summary.php?start=<?= $start ?>&displayType=<?= $displayType ?>&render=<?= $render ?>';
                    } else {
                        alert('error not success (returned from ajax/accepttimesheet.php)');
                    }
                } else {
                    alert('error no \'status\' in data returned from ajax/accepttimesheet.php.\n' + 
                        'Typically this means that you are logged in as admin, but not as a user.\n' +
                        'Log in to <?= REQUEST_SCHEME . '://' . HTTP_HOST ?>/panther.php (in a different tab), then try the action here again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/accepttimesheet.php; details will be in system log.');
            }
        });
    });
    
    $('button.rescind-timesheet').click(function() {
        // Set customerPersonPayPeriodInfo.adminSignedPayrollTime for correct row    
        let $this = $(this);
        let customerPersonId = $this.data('employee');
        let start = '<?= $start ?>';
        
        $.ajax({
            url: '/_admin/ajax/accepttimesheet.php',
            data: {
                customerPersonId : customerPersonId,
                periodBegin : start,
                delete : 'true' // this is how we indicate "rescind"
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { 
                        // On success, reload page (saving position)
                        // save position and refresh
                        let scrollTopMain = $(document).scrollTop();
                        sessionStorage.setItem('admin_summary_scrollTopMain', scrollTopMain);
                        location.href = 'summary.php?start=<?= $start ?>&displayType=<?= $displayType ?>&render=<?= $render ?>';
                    } else {
                        alert('error not success (returned from ajax/accepttimesheet.php)');
                    }
                } else {
                    alert('error no \'status\' in data returned from ajax/accepttimesheet.php.\n' + 
                        'Typically this means that you are logged in as admin, but not as a user.\n' +
                        'Log in to <?= REQUEST_SCHEME . '://' . HTTP_HOST ?>/panther.php (in a different tab), then try the action here again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/accepttimesheet.php; details will be in system log.');
            }
        });
    });    
});

<?php
/* 'expand-modified-dialog' DIV expands when hovering over expand-modified  
     Absolutely arbitrary where this goes in the HTML BODY */ 
?>
</script>
<div id="expand-modified-dialog"></div>
<script>
    $(function() {
        $(".expand-modified").mouseenter(function() {
            $("#expand-modified-dialog").html(content);
            $("#expand-modified-dialog").dialog({
                position: { my: "center bottom", at: "center top", of: $(this) },
                autoResize:true,
                open: function(event, ui) {
                    $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                    $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
                }
            });        
            var content = $(this).data('report');            
            $("#expand-modified-dialog").dialog("open").html(content).dialog({height:'auto', width:'auto'});
        });

        $( ".expand-modified, #expand-modified-dialog" ).mouseleave(function() {
            $( "#expand-modified-dialog" ).dialog("close");
        });
    });
<?php /* END ADDED 2020-10-09 JM */ ?>
</script>
</div>
<?php 
include "../../includes/footer_admin.php";
?>