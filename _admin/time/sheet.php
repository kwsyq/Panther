<?php
/*  _admin/time/sheet.php

    EXECUTIVE SUMMARY: Calculate pay for a given person for a given period. Also allows tweaking
        pay rate, IRA, etc.

    PRIMARY INPUTs:
        * $_REQUEST['start']: the date the period starts, e.g, '2016-10-20'
        * $_REQUEST['userId']: employee, primary key to DB table Person
        * $_REQUEST['displayType']: 'payperiod' (default) / 'workweek' / 'incomplete'

    Other INPUT: Optional $_REQUEST['act']. Only possible value:
        * 'updateperiodinfo', only meaningful in conjunction with $_REQUEST['displayType']=='payperiod'. Takes additional inputs:
            * $_REQUEST['rate']
            * $_REQUEST['ira']
            * $_REQUEST['salaryHours']
            * $_REQUEST['salaryAmount']
            * $_REQUEST['copay']

    >>>00037: there is a *lot* of code in common with summary.php, someone would do well to look at common code elimination

*/

include '../../inc/config.php';

$db = DB::getInstance();

// Identical to the function of the same name in summary.php, >>>00037 should be common code in one place
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
function calc($user,$start) {
    $date = new DateTime($start);

    $w1 = new Time($user,$date->format("Y-m-d"),'workWeek');
    $w1info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $date->modify("+7 days");
    $w2 = new Time($user,$date->format("Y-m-d"),'workWeek');
    $w2info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $date->modify("+7 days");
    $w3 = new Time($user,$date->format("Y-m-d"),'workWeek');
    $w3info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    $date->modify("+7 days");
    $w4 = new Time($user,$date->format("Y-m-d"),'workWeek');
    $w4info = $user->getCustomerPersonPayWeekInfo($date->format("Y-m-d"));

    // See note in header comment of this file about getWorkOrderTasksByDisplayType
    $w1tasks = $w1->getWorkOrderTasksByDisplayType();
    $w2tasks = $w2->getWorkOrderTasksByDisplayType();
    $w3tasks = $w3->getWorkOrderTasksByDisplayType();
    $w4tasks = $w4->getWorkOrderTasksByDisplayType();

    $run = new DateTime($start); // >>>00012 odd variable name
    $days = array();

    $payWeekInfos = array($w1info, $w2info, $w3info, $w4info,);
    $payWeekInfosOffset = 0;

    for ($i = 0; $i < 28; ++$i) {
        if (($i % 7) == 0) {
            $payWeekInfosOffset++;
        }
        $days[$run->format("Y-m-d")] = array('pto' => 0, 'regular' => 0, 'ptobreakdown' => array(), 'payWeekInfo' => $payWeekInfos[$payWeekInfosOffset - 1] );
        $run->modify("+1 day");
    }

    $alltasks = array_merge($w1tasks,$w2tasks,$w3tasks,$w4tasks);

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

// Identical to the function of the same name in summary.php, >>>00037 should be common code in one place
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

$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';  // Martin comment: the date the week starts ... i.e. 2016-10-20
$userId = isset($_REQUEST['userId']) ? intval($_REQUEST['userId']) : 0;
$displayType = isset($_REQUEST['displayType']) ? $_REQUEST['displayType'] : '';

// >>>00016, >>>00002: might want to handle defaults more carefully; e.g. default on any "bad" values, but log it.
if (!strlen($displayType)) {
    $displayType = 'payperiod';
}

$employee = new User($userId, $customer);
?>
<!DOCTYPE html>
<html>
<head>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
</head>
<body>
<?php

echo '&nbsp;&nbsp;[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;<span style="color:lightgray">[PTO]</span>&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]' . "\n";

echo '<h1>' . $employee->getFirstName() . '&nbsp;' . $employee->getLastName() . '</h1>' . "\n"; // employee name as header

// Instantiates Time object based on inputs. A lot of work is done in this constructor.
$time = new Time($employee, $start, $displayType);

// See note in header comment of this file about getWorkOrderTasksByDisplayType
$workordertasks = $time->getWorkOrderTasksByDisplayType();

$user = new User($userId, $customer);

$inot = 0; // "in overtime"

if ($displayType == "payperiod") {
    // >>>00037: NOTE this whole section is more or less common code with summary.php
    // Main difference is that summary.php makes more use of validateNum where this just uses intval.
    // I (JM) don't think that is a significant difference.

    // Have to look at full week to calculate overtime correctly, even if the pay period begins mid-week.
    // That means that we have to look at a broader timespan than just the period for this summary: always
    //  start looking at the beginning of a week.
    //
    // NOTE that below we will add more elements to each associative array $days[$i]: calc just gets this started.
    $days = calc($user, $time->firstDayWorkWeek->format("Y-m-d"));

    $c = 0; // index within the timespan we are looking at. Always 0 mod 7 at start of week.
    foreach ($days as $dkey => $day) {
        /* BEGIN REPLACED 2020-07-24 JM: Let's handle it more smoothly if payWeekInfo is missing.
        $dayotminutes = (60 * $day['payWeekInfo']['dayOT']); // day overtime threshold in minutes
        $daysinweek = 7;  // Martin comment: days in work week
        $weekotminutes = (60 * $day['payWeekInfo']['weekOT']); // week overtime threshold in minutes
        // END REPLACED 2020-07-24 JM
        */
        // BEGIN REPLACEMENT 2020-07-24 JM
        $daysinweek = 7;  // days in work week
        if ($day['payWeekInfo']) {
            $dayotminutes = (60 * $day['payWeekInfo']['dayOT']); // day overtime threshold in minutes
            $weekotminutes = (60 * $day['payWeekInfo']['weekOT']); // week overtime threshold in minutes
        } else {
            echo "<p style=\"color:red\">No payWeekInfo data for $dkey</p>\n";
            $dayotminutes = 60 * 10;    // sane default
            $weekotminutes = 60 * 40;   // sane default
        }
        // END REPLACEMENT 2020-07-24 JM

        $mod = ($c % $daysinweek);
        // Code relies on the fact that the first time through the loop this will be true.
        // That is how $weektotal & $inot get initialized.
        if ($mod == 0) {
            $weektotal = 0;
            $inot = 0;
        }

        $weektotal += intval($day['regular']);

        $days[$dkey]['dailyot'] = 0;
        $days[$dkey]['worked'] = intval($day['regular']); // 'regular' vs., for example, 'pto'
        $days[$dkey]['weektotal'] = $weektotal;  // So, week total worked up to and including this day.
        $days[$dkey]['weekot'] = 0;
        if ($weektotal > $weekotminutes) {
            if ($inot) {
                // already in overtime, so this is all overtime
                $days[$dkey]['weekot'] = intval($day['regular']);
            } else {
                if (intval($day['regular'])) {
                    if (!isset($days[$dkey]['weekot'])) {
                        $days[$dkey]['weekot'] = 0;
                    }
                    $days[$dkey]['weekot'] = ($weektotal - $weekotminutes);
                }
            }
            $inot = 1; // and anything after this is overtime
        }

        // Did they work so much on the one day that some of this is overtime?
        if (intval($day['regular']) > $dayotminutes) {
            $days[$dkey]['dailyot'] = (intval($day['regular'] - $dayotminutes));
        }

        $weekot = intval($days[$dkey]['weekot']);    // how many of these hours would be overtime based on the week?
        $dailyot = intval($days[$dkey]['dailyot']);  // how many of these hours would be overtime based on the day?

        // Then take the more generous of the two numbers for how many of today's hours count as overtime.
        $days[$dkey]['ot'] = 0;
        if ($weekot > $dailyot) {
            $days[$dkey]['ot'] = intval($weekot);
        } else {
            $days[$dkey]['ot'] = intval($dailyot);
        }
        $c++;
    }
}

?>

<style>
    .datarow td {
        border-bottom-style: solid;
        border-bottom-color: #cccccc;
    }

    .headings th {
        background-color:#dedede;
    }

    .totalcell {
        background-color:#ddddff;
        text-align:center;
    }
    .diagopen {
        text-align:center;
    }

    input.cantfocus {
        background-color:#f5f5f5;
    }
</style>

<?php
echo '<table border="0" cellpadding="0" width="100%">' . "\n";
    echo '<tr>' . "\n";
        echo '<td colspan="3">';
            // Navigate among the three displays: [INCOMPLETE], [WEEKS], [PAY PERIOD]
            if ($displayType == 'workWeek') {
                echo '[<a href="sheet.php?displayType=incomplete&userId=' . intval($userId) . '">INCOMPLETE</a>]' .
                     '&nbsp;&nbsp;[WEEKS]&nbsp;&nbsp;' .
                     '[<a href="sheet.php?displayType=payperiod&userId=' . intval($userId) . '">PAY PERIOD</a>]';
            } else if ($displayType == 'incomplete') {
                echo '[INCOMPLETE]' .
                     '&nbsp;&nbsp;[<a href="sheet.php?displayType=workWeek&userId=' . intval($userId) . '">WEEKS</a>]&nbsp;&nbsp;' .
                     '[<a href="sheet.php?displayType=payperiod&userId=' . intval($userId) . '">PAY PERIOD</a>]';
            } else if ($displayType == 'payperiod') {
                echo '[<a href="sheet.php?displayType=incomplete&userId=' . intval($userId) . '">INCOMPLETE</a>]' .
                     '&nbsp;&nbsp;[<a href="sheet.php?displayType=workWeek&userId=' . intval($userId) . '">WEEKS</a>]&nbsp;&nbsp;' .
                     '[PAY PERIOD]';
            }
        echo '</td>' . "\n";
    echo '</tr>' . "\n";

    if ($displayType == 'workWeek') {
        /* show, in three separate columns:
            * '� date one week earlier �', with the date linked to navigate and replace the current page by the
                equivalent page with the start date 7 days earlier.
            * 'Week Starting start date' (no link).
            * '� date one week later �', with the date linked to navigate and replace the current page by the
                equivalent page with the start date 7 days later
        */
        echo '<tr><td colspan="3">&nbsp;</td></tr>' . "\n";
        echo '<tr>' . "\n";
            echo '<td style="text-align:left">&#171;<a href="sheet.php?displayType=workWeek&userId=' . intval($userId) .
                 '&start=' . $time->previous . '">' . $time->previous . '</a>&#171;</td>' . "\n";
            echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">Week Starting ' . $time->begin . '</span></td>' . "\n";
            echo '<td style="text-align:right">&#187;<a href="sheet.php?displayType=workWeek&userId=' . intval($userId) .
                 '&start=' . $time->next . '">' . $time->next . '</a>&#187;</td>' . "\n";
        echo '</tr>' . "\n";
    } else if ($displayType == 'payperiod') {
        /* show, in three separate columns, similar 3-column display to workWeek, but the dates displayed and linked
           correspond to previous and later pay periods (first and 16th of the month).
        */
        echo '<tr><td colspan="3">&nbsp;</td></tr>' . "\n";
        echo '<tr>' . "\n";
            echo '<td style="text-align:left">&#171;<a href="sheet.php?displayType=payperiod&userId=' . intval($userId) .
                 '&start=' . $time->previous . '">' . $time->previous . '</a>&#171;</td>' . "\n";
            echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">Period Starting ' . $time->begin . '</span></td>' . "\n";
            echo '<td style="text-align:right">&#187;<a href="sheet.php?displayType=payperiod&userId=' . intval($userId) .
                 '&start=' . $time->next . '">' . $time->next . '</a>&#187;</td>' . "\n";
        echo '</tr>' . "\n";

        echo '<tr>' . "\n";
            echo '<td colspan="3">' . "\n";
                if ($displayType == 'payperiod') {
                    if ($act == 'updateperiodinfo') {
                        // Make appropriate updates to DB table customerPersonPayPeriodInfo on the row for this customer and period, then redisplay.
                        // >>>00002, >>>00016: quite a few of these deserve range checks or other validation.
                        $query = " select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson " .
                                 " where customerId = " . intval($customer->getCustomerId()) . " and personId = " . $employee->getUserId() . " ";

                        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();

                                $rate = isset($_REQUEST['rate']) ? intval($_REQUEST['rate']) : 0;
                                $ira = isset($_REQUEST['ira']) ? $_REQUEST['ira'] : '';
                                $iraType = isset($_REQUEST['iraType']) ? $_REQUEST['iraType'] : '';
                                $salaryHours = isset($_REQUEST['salaryHours']) ? $_REQUEST['salaryHours'] : '';
                                $salaryAmount = isset($_REQUEST['salaryAmount']) ? $_REQUEST['salaryAmount'] : '';
                                $copay = isset($_REQUEST['copay']) ? $_REQUEST['copay'] : '';

                                $ira = trim($ira);
                                $iraType = trim($iraType);
                                $salaryHours = trim($salaryHours);
                                $copay = trim($copay);

                                if (!is_numeric($ira)) {
                                    $ira = 0;
                                }

                                if (!is_numeric($iraType)) {
                                    $iraType = 0;
                                }

                                if (!is_numeric($copay)) {
                                    $copay = 0;
                                }

                                if (!is_numeric($salaryHours)) {
                                    $salaryHours = 0;
                                }

                                $query = " update " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo set ";
                                $query .= " rate = " . intval($rate) . " ";
                                $query .= " ,ira = " . $db->real_escape_string($ira) . " ";
                                $query .= " ,iraType = " . $db->real_escape_string($iraType) . " ";
                                $query .= " ,copay = " . $db->real_escape_string($copay) . " ";
                                $query .= " ,salaryHours = " . $db->real_escape_string($salaryHours) . " ";
                                $query .= " ,salaryAmount = " . intval($salaryAmount) . " ";
                                $query .= " where customerPersonId = " . intval($row['customerPersonId']) . " ";
                                $query .= " and periodBegin = '" . $db->real_escape_string(date("Y-m-d", strtotime($time->begin))) . "' ";

                                $db->query($query);
                            }
                        }  // >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.
                    } // END if ($act == 'updateperiodinfo') {

                    // >>>00037 Martin comment: get this into a class
                    $query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo  ";
                    $query .= " where customerPersonId = (". "
                                    select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson " .
                                    "where customerId = " . intval($customer->getCustomerId()) .
                                    " and personId = " . $employee->getUserId() .
                                ") ";
                    $query .= " and periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "' ";

                    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();

                            //  form, structured by a table, one datum to a row (plus some hidden INPUTs); all values are appropriately initialized:
                            echo '<form id="periodupdate" name="periodupdate">' . "\n";
                                echo '<input type="hidden" name="userId" value="' . intval($employee->getUserId()) . '" />' . "\n"; // userId is really a personId
                                echo '<input type="hidden" name="act" value="updateperiodinfo" />' . "\n";

                                /*
                                // JM MEANT TO KILL THIS 2019-12-02 but failed to comment it out at that time.
                                // Pretty sure it now (2020-07-22) effectively does nothing, but commenting it out now for v2020-4.
                                // Please feel free to remove it entirely after a couple of more releases.
                                //
                                // Shouldn't the following have "displayType" rather than "type"? JM asked Martin ~2018-10.
                                //  Martin said "probably not used" but JM thinks we're getting away with this only because
                                //  displaytype=='payperiod' is the default.
                                //
                                // Anyway, I the line that now follows this has handled displaytype since v2020-1, and we should be good.
                                //
                                echo '<input type="hidden" name="type" value="payperiod" />' . "\n";
                                */
                                echo '<input type="hidden" name="displaytype" value="payperiod" />' . "\n";

                                echo '<input type="hidden" name="start" value="' . htmlspecialchars( date("Y-m-d", strtotime($time->begin))) . '" />' . "\n"; // start of pay period
                                echo '<table border="1" cellpadding="3" cellspacing="0">' . "\n";
                                    echo '<tr>' . "\n";
                                        echo '<td>Rate (in dollars):</td>' . "\n";
                                        echo '<td><input type="text" name="rate" value="' .
                                        formatCentsAsDollars(htmlspecialchars($row['rate'])) .
                                        '" size="5" /></td>' . "\n";
                                    echo '</tr>' . "\n";
                                    echo '<tr>' . "\n";
                                        echo '<td>IRA:</td>' . "\n";
                                        echo '<td><input type="text" name="ira" value="' . htmlspecialchars($row['ira']) . '" size="5" />' . "\n";
                                        // "IRA Type": HTML SELECT. 3 OPTIONs:
                                        //   * '---', value = 0
                                        //   * 'Percent', value = 1 ( == IRA_TYPE_PERCENT)
                                        //   * 'Dollar', value = 2 ( == IRA_TYPE_DOLLAR)
                                        $iraType = $row['iraType'];
                                        $pselected = ($iraType == IRA_TYPE_PERCENT) ? ' selected ' : '';
                                        $dselected = ($iraType == IRA_TYPE_DOLLAR) ? ' selected ' : '';

                                        echo '<select name="iraType">' . "\n";
                                            echo '<option value="0">--</option>' . "\n";
                                            echo '<option value="' . IRA_TYPE_PERCENT . '" ' . $pselected . '>' . $iraTypes[IRA_TYPE_PERCENT] . '</option>' . "\n";
                                            echo '<option value="' . IRA_TYPE_DOLLAR . '" ' . $dselected . '>' . $iraTypes[IRA_TYPE_DOLLAR] . '</option>' . "\n";
                                        echo '</select></td>' . "\n";
                                    echo '</tr>' . "\n";
                                    echo '<tr>' . "\n";
                                        echo '<td>Co-Pay:</td>' . "\n";
                                        echo '<td><input type="text" name="copay" value="' . htmlspecialchars($row['copay']) . '" size="5" /></td>' . "\n";
                                    echo '</tr>' . "\n";
                                    echo '<tr>' . "\n";
                                        echo '<td>SalaryHours:</td>' . "\n";
                                        echo '<td><input type="text" name="salaryHours" value="' . htmlspecialchars($row['salaryHours']) . '" size="5" /></td>' . "\n";
                                    echo '</tr>' . "\n";
                                    echo '<tr>' . "\n";
                                        echo '<td>Salary Amt (in dollars):</td>' . "\n";
                                        echo '<td><input type="text" name="salaryAmount" value="' .
                                            formatCentsAsDollars(htmlspecialchars($row['salaryAmount'])) .
                                            '" size="9" /></td>' . "\n";
                                    echo '</tr>' . "\n";
                                    echo '<tr>' . "\n";
                                        echo '<td colspan="2"><input id="submit-periodupdate" type="button" value="Update period info" border="0" /></td>' . "\n";
                                    echo '</tr>' . "\n";
                                echo '</table>' . "\n";
                            echo '</form>' . "\n";
                        }
                    }
                }
            echo '</td>' . "\n";
        echo '</tr>' . "\n";
    }
echo '</table>' . "\n";

?>
<script>
// Previous to 2019-10-21, the periodupdate form was just handled with straight-out HTML form submission. However, that really didn't work well
// for validation, etc, and had led to issues like http://bt.dev2.ssseng.com/view.php?id=39, where salaries were getting messed up. This new approach
// should allow us to display rates and salaries in dollars-and-cents instead of just cents, and have everything still work well.
// >>>00032 We may want more (strictly client-side) validation here
$('#submit-periodupdate').click(function() {
    let getString = "sheet.php?";
    $('#periodupdate input[type="hidden"]').each(function() {
        getString += $(this).attr('name') + '=' + $(this).val() + '&';
    });
    $('#periodupdate input[name="ira"], #periodupdate select[name="iraType"], #periodupdate input[name="copay"],' +
        '#periodupdate input[name="salaryHours"]').each(function() {
        getString += $(this).attr('name') + '=' + $(this).val() + '&';
    });

    let rate = Math.round(parseFloat($('#periodupdate input[name="rate"]').val()) * 100);
    getString += 'rate=' + rate + '&';

    let salaryAmountCents = Math.round(parseFloat($('#periodupdate input[name="salaryAmount"]').val()) * 100);
    getString += 'salaryAmount=' + salaryAmountCents + '&';

    getString = getString.substring(0, getString.length-1); // lose the last ampersand
    getString = encodeURI(getString);

    window.location.href=getString; // Self-submit
});
</script>

<?php
// >>>00032 the following probably should be common code somewhere more global
// >>>00032 might want to add optional input to format numbers over 1000 with commas
// >>>00032 might want to add optional input to format "0" as "0.00".
// INPUT cents - cents as string or integer
// RETURN string: same value formatted as dollars and cents: e.g. "102345" => "1023.45"; special case: "0" stays "0" (not "0.00")
function formatCentsAsDollars($cents) {
    if (intval($cents) == 0) {
        return '0';
    } else {
        return number_format(intval($cents)/100, 2, '.', '');
    }
}
?>

<br/>
<br/>

<?php
// BEGIN ADDED 2020-10-21 JM
if ($displayType == 'payperiod') {
    $lateModificationsCount = 0;
    $lateModificationsMinutes = 0;
    $ptoModifications2 = Array();
    $wotModifications2 = Array();

    $payperiodinfo = $employee->getCustomerPersonPayPeriodInfo($start);
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
}
// END ADDED 2020-10-21 JM

echo '<table border="0">' . "\n";
    $date1 = new DateTime($time->begin);
    $date2 = new DateTime($time->end);

    $diff = $date2->diff($date1)->format("%a") + 1;

    $daysinperiod = $diff;
    $currentJobId = -1;
    $totarray = array();

    if ($displayType == 'payperiod') {
        // Table will have five more columns than the number of days in the period: four at left, one per day, plus totals
        // >>>00037 what follows looks like mostly common code with summary.php. Main difference is intval vs. validateNum,
        //  but I (JM) don't think that difference is significant.
        echo '<tr>';
            // Span everything except final totals column: "Summary"
            echo '<th style="background-color:#dddddd;text-align:left" colspan="' . (4 + $daysinperiod) . '" style="text-align:left;">Summary</th>';
        echo '</tr>' . "\n";

        // Headings split across 2 rows
        echo '<tr class="headings">' . "\n";
            echo '<td colspan="4">&nbsp;'; // 4 columns with no heading
                echo '</td>';
                for ($i = 0; $i < $diff; $i++) {
                    // "short date" (e.g. '05-24')
                    echo '<th>' . $time->dates[$i]['short'] . '</th>' . "\n";
                }
            echo '<td>&nbsp;</td>' . "\n"; // 1 column with no heading
        echo '</tr>' . "\n";
        echo '<tr class="headings">' . "\n";
            echo '<td colspan="4">&nbsp;</td>' . "\n"; // 4 columns with no heading
            for ($i = 0; $i < $diff; $i++) {
                $x = new DateTime($time->dates[$i]['position']);
                // day of week ("Mon", "Tue", etc.)
                echo '<th>' . $x->format("D") . '</th>' . "\n";
            }
            echo '<td>&nbsp;</td>' . "\n"; // 1 column with no heading
        echo '</tr>' . "\n";

        $regulartotal = 0;
        $ottotal = 0;
        $workedtotal = 0;
        $pto1total = 0;
        $pto2total = 0;

        // Total worked hours
        echo '<tr class="datarow">' . "\n";
            echo '<td colspan="4">Worked</td>' . "\n"; // 4 columns
            // For each day in period...
            for ($i = 0; $i < $diff; $i++) {
                // Here and below, the somewhat odd expression $time->dates[$i]['position'] is
                //  an index into $days; $days covers only the period, $time->dates can begin earlier
                //  to get a boundary on a new week.
                $mins = intval(intval($days[$time->dates[$i]['position']]['worked']));
                $workedtotal += $mins;
                if (intval($mins)) {
                    // hours, two digits past the decimal point.
                    echo '<td id="workedcell_' . $i . '" style="text-align:center;">' . number_format((float)intval($mins)/60, 2, '.', '') . '</td>' . "\n";
                } else {
                    echo '<td id="workedcell_' . $i . '">&nbsp;</td>' . "\n";
                }
            }
            echo '<td id="workedcell_total" class="totalcell">' . number_format((float)intval($workedtotal)/60, 2, '.', '') . '</td>' . "\n";
        echo '</tr>' . "\n";

        // Similarly, omitting overtime.
        echo '<tr class="datarow">' . "\n";
            echo '<td colspan="4">Regular</td>' . "\n"; // 4 columns
            // For each day in period...
            for ($i = 0; $i < $diff; $i++) {
                if (intval(intval($days[$time->dates[$i]['position']]['ot']))) {
                    // hours, two digits past the decimal point.
                    $mins = (intval($days[$time->dates[$i]['position']]['worked'])) - intval(intval($days[$time->dates[$i]['position']]['ot']));
                } else {
                    $mins = (intval($days[$time->dates[$i]['position']]['worked']));
                }

                $regulartotal += $mins;
                if (intval($mins)) {
                    echo '<td id="workedregularcell_' . $i . '" style="text-align:center;">' . number_format((float)intval($mins)/60, 2, '.', '') . '</td>' . "\n";
                } else {
                    echo '<td id="workedregularcell_' . $i . '" >&nbsp;</td>' . "\n";
                }
            }
            echo '<td id="workedregularcell_total" class="totalcell">' . number_format((float)intval($regulartotal)/60, 2, '.', '') . '</td>' . "\n";
        echo '</tr>';

        // Similarly, just overtime.
        echo '<tr class="datarow">' . "\n";
            echo '<td colspan="4">OT</td>' . "\n"; // 4 columns
            // For each day in period...
            for ($i = 0; $i < $diff; $i++) {
                $mins = intval(intval($days[$time->dates[$i]['position']]['ot']));
                $ottotal += $mins;
                if (intval($mins)) {
                    echo '<td id="workedovertimecell_' . $i . '" style="text-align:center;">' . number_format((float)intval($mins)/60, 2, '.', '') . '</td>' . "\n";
                } else {
                    echo '<td id="workedovertimecell_' . $i . '">&nbsp;</td>' . "\n";
                }
            }
            echo '<td id="workedovertimecell_total" class="totalcell">' . number_format((float)intval($ottotal)/60, 2, '.', '') . '</td>' . "\n";
        echo '</tr>' . "\n";

        // vacation/sick
        echo '<tr class="datarow">' . "\n";
            echo '<td colspan="4">PTO</td>' . "\n"; // 4 columns
            // For each day in period...
            for ($i = 0; $i < $diff; $i++) {
                $mins = 0;
                if (isset($days[$time->dates[$i]['position']]['ptobreakdown'])) {
                    if (isset($days[$time->dates[$i]['position']]['ptobreakdown'][1])) {
                        $mins = intval(intval($days[$time->dates[$i]['position']]['ptobreakdown'][1]));
                        $pto1total += $mins;
                    }
                }
                if (intval($mins)) {
                    echo '<td id="ptocell_' . $i . '" style="text-align:center;">' . number_format((float)intval($mins)/60, 2, '.', '') . '</td>' . "\n";
                } else {
                    echo '<td id="ptocell_' . $i . '">&nbsp;</td>' . "\n";
                }
            }
            echo '<td id="ptocell_total" class="totalcell">' . number_format((float)intval($pto1total)/60, 2, '.', '') . '</td>' . "\n";
        echo '</tr>';

        // company holiday
        echo '<tr class="datarow">' . "\n";
            echo '<td colspan="4">Holiday</td>' . "\n"; // 4 columns
            // For each day in period...
            for ($i = 0; $i < $diff; $i++) {
                $mins = 0;
                if (isset($days[$time->dates[$i]['position']]['ptobreakdown'])) {
                    if (isset($days[$time->dates[$i]['position']]['ptobreakdown'][2])) {
                        $mins = intval(intval($days[$time->dates[$i]['position']]['ptobreakdown'][2]));
                        $pto2total += $mins;
                    }
                }
                if (intval($mins)) {
                    echo '<td id="holidaycell_' . $i . '" style="text-align:center;">' . number_format((float)intval($mins)/60, 2, '.', '') . '</td>' . "\n";
                } else {
                    echo '<td id="holidaycell_' . $i . '">&nbsp;</td>' . "\n";
                }
            }
            echo '<td id="holidaycell_total" class="totalcell">' . number_format((float)intval($pto2total)/60, 2, '.', '') . '</td>' . "\n";
        echo '</tr>' . "\n";

        // add all categories of pay
        $gt = intval($regulartotal) + intval($ottotal) + intval($pto1total) + intval($pto2total);

        echo '<tr>';
            echo '<th style="text-align:right" colspan="' . (4 + $daysinperiod) . '" style="text-align:left;">TOTAL</th>' . "\n"; // all columns except the last
            echo '<th id="gtcell2">' . number_format((float)intval($gt)/60, 2, '.', '') . '</th>' . "\n";
        echo '</tr>' . "\n";
    } // END if ($displayType == 'payperiod')

    foreach ($workordertasks as $wotkey => $workordertask) {
        if ($workordertask['type'] == 'real') {
            // If not within the same job as previous line...
            if ($workordertask['jobId'] != $currentJobId) {
                // If not the very first job, or if displaying period
                if ($wotkey || ($displayType == 'payperiod')) {
                    // blank row; >>>00031 JM suspects the colspan here is absolutely arbitrary. Correct for week, not really for period.
                    echo '<tr>' . "\n";
                        echo '<td colspan="12">&nbsp;</td>' . "\n";
                    echo '</tr>' . "\n";
                }

                $jo = new Job($workordertask['jobId']);

                // If there is a Job Number, format it (only for the very next row).
                // Display Job Number in square brackets, link to open Job in new tab/window. 2 spaces before it.
                $number = (strlen($workordertask['number'])) ? '&nbsp;&nbsp;[<a target="_blank" href="' . $jo->buildLink() . '">' . $workordertask['number'] . '</a>]' : '';

                echo '<tr>';
                    // Span everything except final totals column: job name + Job Number (the latter a link)
                    echo '<th style="background-color:#dddddd;text-align:left" colspan="' . (4 + $daysinperiod) . '" style="text-align:left;">' .
                         $workordertask['name'] . $number . '</th>' . "\n";
                echo '</tr>' . "\n";

                // Headings split across 2 rows, so we are repeating these headings for every job
                echo '<tr class="headings">' . "\n";
                    // 4 columns with no heading
                    echo '<td>&nbsp;</td>' . "\n";
                    echo '<td>&nbsp;</td>' . "\n";
                    echo '<td>&nbsp;</td>' . "\n";
                    echo '<td>&nbsp;</td>' . "\n";
                    for ($i = 0; $i < $diff; $i++) {
                        // "short date" (e.g. '05-24')
                        echo '<th>' . $time->dates[$i]['position'] . '</th>' . "\n";
                    }
                    echo '<td>&nbsp;</td>' . "\n"; // 1 column with no heading
                echo '</tr>' . "\n";
                echo '<tr class="headings">' . "\n";
                     // 4 columns with no heading
                    echo '<td>&nbsp;</td>' . "\n";
                    echo '<td>&nbsp;</td>' . "\n";
                    echo '<td>&nbsp;</td>' . "\n";
                    echo '<td>&nbsp;</td>' . "\n";
                    for ($i = 0; $i < $diff; $i++) {
                        $x = new DateTime($time->dates[$i]['position']);
                        // day of week ("Mon", "Tue", etc.)
                        echo '<th>' . $x->format("D") . '</th>' . "\n";
                    }
                    echo '<td>&nbsp;</td>' . "\n";  // 1 column with no heading
                echo '</tr>' . "\n";
            }

            $wot = new WorkOrderTask($workordertask['workOrderTaskId']);
            $workordertaskpersons = $wot->getWorkOrderTaskPersons();

            echo '<tr class="datarow">' . "\n";
                // column 1: task icon
                if (strlen($workordertask['icon'])) {
                    echo '<td><img src="'. getFullPathnameOfTaskIcon($workordertask['icon'], '1595357734') . '" width="18" height="18" border="0"></td>' . "\n";
                } else {
                    echo '<td>&nbsp;</td>' . "\n";
                }

                // column 2: blank
                echo '<td width="40%">&nbsp;</td>' . "\n";

                // column 3: task description
                // Translate special inputs -100, -200 into appropriate PTOTYPE_SICK_VACATION, PTOTYPE_HOLIDAY respectively.
                $workOrderTaskId = $workordertask['workOrderTaskId'];
                if ($workOrderTaskId < 0 && !$workordertask['taskDescription']) {
                    $ptotype = abs( ($workOrderTaskId / 100));
                    if ($ptotype == PTOTYPE_SICK_VACATION) {
                        $workordertask['taskDescription'] = 'Normal PTO';
                    } else if ($ptotype == PTOTYPE_HOLIDAY) {
                        $workordertask['taskDescription'] = 'Holiday';
                    }
                }
                unset ($ptotype);
                // Keep $workOrderTaskId for use below 2020-10-21
                echo '<td width="40%">' . $workordertask['taskDescription'] . '</td>' . "\n";

                // column 4: Displays icon for task status.
                // >>>00007 JM: This is a link, but I believe the link does nothing: there does not appear to be
                //   any function setTaskStatusId, and it look like the "statuscell_..." ID does nothing here,
                //   so I would be inclined to strip them as potentially misleading.
                if (intval($workordertask['workOrderTaskId']) > 0) {
                    // Martin comment: legacy shit where 9 was "completed"
                    $active = ($workordertask['taskStatusId'] == 9) ? 0 : 1;
                    $newstatusid = ($workordertask['taskStatusId'] == 9) ? 1 : 9;
                    echo  '<td id="statuscell_' . intval($workordertask['workOrderTaskId']) . '">' .
                          '<a href="javascript:setTaskStatusId(' . $workordertask['workOrderTaskId'] . ',' . intval($newstatusid) . ')">' .
                          '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_active_' . intval($active) . '_24x24.png" width="16" height="16" border="0"></a></td>' . "\n";
                } else {
                    // Martin comment: for PTO the workOrderTaskId's are less than zero
                    echo '<td>&nbsp;</td>' . "\n";
                }

                $ranges = array();
                $isPTO = false;
                if (isset($workordertask['ptoitems'])) {
                    $ranges = $workordertask['ptoitems'];
                    $isPTO = true;
                } else if (isset($workordertask['regularitems'])) {
                    $ranges = $workordertask['regularitems'];
                }

                $tot = 0;

                for ($i = 0; $i < $daysinperiod; ++$i) {
                    // JM: NOTE in following that $ranges[FOO]['minutes'] is a scalar for the $isPTO case, and an array otherwise!

                    $val = 0;
                    $modified = false;
                    if (isset($ranges[$time->dates[$i]['position']])) {
                        if ($isPTO) {
                            // Here and below, the somewhat odd expression $time->dates[$i]['position'] is
                            //  an index into $ranges; apparently -- >>>00001 although the code behind it
                            //  in Time::getWorkOrderTasksByDisplayType remains a bit mysterious --
                            //  $ranges covers only the period, $time->dates can begin earlier
                            //  to get a boundary on a new week.
                            $val = $ranges[$time->dates[$i]['position']]['minutes'];
                            $modified = isset($ptoModifications2[$i]) && is_array($ptoModifications2[$i]); // ADDED 2020-10-21 JM
                        } else {
                            foreach ($ranges[$time->dates[$i]['position']] as $item) {
                                if ($item['personId'] == $user->getUserId()) { // because apparently there could also be data here about other people here who also worked on the task
                                    $val += $item['minutes'];
                                }
                            }
                            $modified = isset($wotModifications2[$workOrderTaskId][$i]) && is_array($wotModifications2[$workOrderTaskId][$i]); // ADDED 2020-10-21 JM
                        }
                    }
                    if (intval($val)) {
                        if (!isset($totarray[$i])) {
                            $totarray[$i] = 0; // initialize
                        }
                        $totarray[$i] += $val; // Sum of $ranges[$time->dates[$i]['position']]['minutes'];
                    }

                    // Format as hours, two digits past the decimal point
                    $dispval = (intval($val)) ? number_format((float)intval($val)/60, 2, '.', '') : '';

                    // 'diagopen' means clicking here will open a dialog to edit this.
                    // 'cantfocus' means any effort to focus on this element will blur immediately.
                    // Both implemented by jQuery code below.
                    $class = (intval($workordertask['editable'])) ? 'diagopen' : 'cantfocus';
                    $class .= $isPTO ? ' pto' : ''; // ADDED 2020-08-12 JM
                    $class .= $modified ? ' expand-modified' : ''; // ADDED 2020-10-21 JM
                    // BEGIN ADDED 2020-10-21
                    $dataReport = '';
                    if ($modified) {
                        if ($isPTO) {
                            foreach ($ptoModifications2[$i] as $modification) {
                                $dataReport .= '<b>' . number_format(validateNum($modification['oldMinutes'])/60, 2, '.', '') . ' hr</b> changed to <b>' ;
                                $dataReport .= number_format(validateNum($modification['newMinutes'])/60, 2, '.', '') . ' hr</b> <small>(' . $modification['inserted'] . ')</small><br>';
                            }
                        } else {
                            foreach ($wotModifications2[$workOrderTaskId][$i] as $modification) {
                                $dataReport .= '<b>' . number_format(validateNum($modification['oldMinutes'])/60, 2, '.', '') . ' hr</b> changed to <b>' ;
                                $dataReport .= number_format(validateNum($modification['newMinutes'])/60, 2, '.', '') . ' hr</b> <small>(' . $modification['inserted'] . ')</small><br>';
                            }
                        }
                    }
                    // END ADDED 2020-10-21
                    echo '<td id="cell_' . $workordertask['workOrderTaskId'] . '_' . $i . '">' .
                         '<input class="' . $class . '" type="text" ' .
                             'id="' . $workordertask['workOrderTaskId'] . '_' . $i . '" ' .
                             'data-workOrderTaskId="' . $workordertask['workOrderTaskId'] . '" ' .
                             'data-dayInPeriod="' . $i . '" ' .
                             ($dataReport ? 'data-report="' . $dataReport . '" '  : ''). // ADDED 2020-10-21
                             ($modified ? 'style="background-color:lightpink" ' : '') .  // ADDED 2020-10-21
                             'value="' . $dispval . '" size="3" /></td>' . "\n";

                    $tot += intval($val); // add to the running total for this row
                } // END for ($i = 0; $i < $daysinperiod; ++$i)

                // total for row; again, format as hours, two digits past the decimal point
                echo '<td class="totalcell" id="totalcell_' . $workordertask['workOrderTaskId'] . '">' . number_format((float)$tot/60, 2, '.', '') . '</td>' . "\n";
            echo '</tr>';

            $currentJobId = $workordertask['jobId']; // so we can see if the next one differs
        }
    } // END foreach ($workordertasks...

    if (count($workordertasks)) {
        $gt = 0;
        echo '<tr>' . "\n";
            echo '<td></td>' . "\n"; // skip one column to indent...
            echo '<td style="text-align:right;" colspan="3" width="80%">&nbsp;</td>' . "\n"; // ... but then next 3 are blank as well, so who knows why first was distinguished

            // total time for each days in period: hours with 2 digits past the decimal point.
            for ($i = 0; $i < $daysinperiod; ++$i) {
                if (isset($totarray[$i])) {
                    $gt += intval($totarray[$i]);
                    echo '<td id="colcell_' . $i . '" class="totalcell">' . number_format((float)intval($totarray[$i])/60, 2, '.', '') . '</td>' . "\n";
                } else {
                    echo '<td id="colcell_' . $i . '" width="80px">&nbsp;</td>' . "\n";
                }
            }

            // grand total for period: hours with 2 digits past the decimal point.
            echo '<th id="gtcell">' . number_format((float)$gt/60, 2, '.', '') . '</th>' . "\n";
        echo '</tr>' . "\n";
    }
echo '</table>' . "\n";
?>

<script>
    // make sure uneditable times can never even be focused on
    $( ".cantfocus" ).click(function() {
        $(this).blur();
    });
</script>

<?php
// From here to bottom is essentially rewritten JM 2019-06-27
//  * Introduced TimeAdjustWidget
//  * Cleaned up a lot of poorly done code
//  * timeAdjust is still about half Martin's

/* Device to add or remove given amount of time (in minutes) from a particular value */

$timeAdjustWidget = new TimeAdjustWidget(
        Array(
            'callback' => 'timeAdjust', // Name of JavaScript function where the real work gets done
            'customer' => $customer
        )
);

// NOTE that the following call just builds inline HTML, CSS, JavaScript etc.
echo $timeAdjustWidget->getDeclarationHTML();   // declare/define the widget
?>
<script>
    // The following two variables define the time to be adjusted. They should are set
    //  in the "click" function, so they will be available to the callback function timeAdjust.
    var workOrderTaskId = 0;
    var dayInPeriod = -1;

    // When clicking an editable time...
    // This delegates event handling so that as we add and remove ".diagopen" elements,
    //  the handler continues to work without any special code to attach it to new elements.
    $("body").on("click", ".diagopen", function () {
        workOrderTaskId = $(this).attr('data-workOrderTaskId');
        dayInPeriod = $(this).attr('data-dayInPeriod');

        // NOTE that the following call just builds inline JavaScript etc.
        // When the user clicks on a time to change it, this pops up the "device" to adjust a time
        //  and saves off what workOrderTask we are adjusting */
        <?php echo $timeAdjustWidget->getOpenJS(); ?>
    });

    // Prevent typing directly into diagopen inputs
    // Allow tabbing; turn ENTER into a click
    $("body").on("keypress", ".diagopen", function (event) {
        var $this = $(this);
        if (event.which == 13) {
            // ENTER
            event.preventDefault();
            $this.click();
            return false;
        } else if (event.which == 16 || event.which == 9) {
            // shift, tab: accept these, do normal behavior
            return true;
        } else {
            // just ignore it
            event.preventDefault();
            return false;
        }
    });


<?php
    // json_encode & then dumping straight into the JavaScript is a bit obscure,
    //  but judging by code below, this gives us an array with 0-based integer indexes,
    //  corresponding to the days in the period; for each index, the value is
    //  the date in form 'YYYY-MM-DD'.
    $js_datePeriodPosition = json_encode($time->dates);
    echo "var datePeriodPosition = ". $js_datePeriodPosition . ";\n";
?>

    // The callback when time is adjusted.
    // INPUT increment: time adjustment in minutes
    // Implicit INPUT workOrderTaskId, dayInPeriod
    // For example, if we are adjusting time downward by an hour for workOrderTask 9879, person 666, date 2020-06-03, and that date is dayInPeriod 2,
    //  which has zero-based index 2 within the period, then increment is -60,
    //  formData will be "workOrderTaskId=9879&increment=-60&personId=666&day=2020-06-03".
    //  cell will be "cell_9879_2".
    //
    // Shows AJAX loader in the edited cell; makes synchronous POST to _admin/ajax/timeadjust.php; alerts on any failure. On success, updates cell
    //  with new value and adjusts all affected totals.
    // _admin/ajax/timeadjust.php is just a wrapper for ajax/timeadjust.php so that it can identify the logged-in admin.
    var timeAdjust = function(increment) {
        var formData = "workOrderTaskId=" + workOrderTaskId + "&increment=" + encodeURIComponent(increment) +
                        "&personId=<?php echo intval($userId); ?>&day=" + encodeURIComponent(datePeriodPosition[dayInPeriod]['position']);
        var cell = document.getElementById("cell_" + workOrderTaskId + '_' + dayInPeriod);
        let savedHTML = cell.innerHTML;
        cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
        // BEGIN ADDED 2020-08-12 JM
        var isPTO = cell.classList.contains('pto');
        // END ADDED 2020-08-12 JM

        $.ajax({
            url: '/_admin/ajax/timeadjust.php',
            data: formData,
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
console.log(cell);                
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016]

console.log("1");                

                        var html = '<input class="diagopen" type="text" ' +
                                   'id="' + workOrderTaskId + '_' + dayInPeriod + '"' +
                                   'data-workOrderTaskId="' + workOrderTaskId + '" ' +
                                   'data-dayInPeriod="' + dayInPeriod + '" ' +
                                   'value="' + data['minutes'] + '" size="3" />';
                                   console.log("1");                

                        var totcell = document.getElementById("totalcell_" + workOrderTaskId);
                        var gtcell = document.getElementById("gtcell");
                        var gtcell2 = document.getElementById("gtcell2"); // ADDED JM 2020-08-12: everything to do with HTML ID gtcell2
                        var colcell = document.getElementById("colcell_" + dayInPeriod);
                        // BEGIN ADDED JM 2020-08-12: everything to do with these HTML IDs
                        var ptocell = document.getElementById("ptocell_" + dayInPeriod);
                        var ptocell_total = document.getElementById("workedcell_total");
                        var workedcell = document.getElementById("workedcell_" + dayInPeriod);
                        var workedcell_total = document.getElementById("workedcell_total");
                        var workedregularcell = document.getElementById("workedregularcell_" + dayInPeriod);
                        var workedregularcell_total = document.getElementById("workedregularcell_total");
                        // END ADDED JM 2020-08-12: everything to do with these HTML IDs

                        // JM 2020-08-03, fixing http://bt.dev2.ssseng.com/view.php?id=204 (and related matters)
                        // Here and below, we typically had things like:
                        //  var n = Number(totcell.innerHTML) + Number(data['hourincrement']);
                        // but that didn't account well for blank (representing 0) so we replaced that with
                        // the new code that uses scratch and treats NaN as 0.
                        // ALSO 2020-08-12: abstracted function applyIncrement, and added the stuff to update
                        //  values for ptocell, workedcell, etc.
                        function applyIncrement(increment, cell) {
                            if(cell){
                                let scratch = Number(cell.innerHTML);
                                if (isNaN(scratch)) {
                                    scratch = 0;
                                }
                                let n = scratch + increment;
                                if (n <= 0) {
                                    n = 0;
                                }
                                cell.innerHTML = n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                            }
                        };
                        applyIncrement(Number(data['hourincrement']), totcell);
                        applyIncrement(Number(data['hourincrement']), gtcell);
                        if(gtcell2){
                            applyIncrement(Number(data['hourincrement']), gtcell2);
                        }
                        applyIncrement(Number(data['hourincrement']), colcell);

                        if (isPTO) {
                            applyIncrement(Number(data['hourincrement']), ptocell);
                            applyIncrement(Number(data['hourincrement']), ptocell_total);
                        } else {
                            applyIncrement(Number(data['hourincrement']), workedcell);
                            applyIncrement(Number(data['hourincrement']), workedcell_total);
                            applyIncrement(Number(data['hourincrement']), workedregularcell);
                            applyIncrement(Number(data['hourincrement']), workedregularcell_total);
                        }

                        cell.innerHTML = html;

                        if (data['vacationError']) {
                            alert('would exceed allowed vacationhours');
                        }


                    } else {
                        alert('error not success (returned from ajax/timeadjust.php)');
                        cell.innerHTML = savedHTML; // added 2020-09-29 JM; message also clarified
                    }
                } else {
                    alert('error no \'status\' in data returned from ajax/timeadjust.php.\n' +
                        'Typically this means that you are logged in as admin, but not as a user.\n' +
                        'Log in to <?= REQUEST_SCHEME . '://' . HTTP_HOST ?>/panther.php (in a different tab), then try the action here again.');
                    cell.innerHTML = savedHTML; // added 2020-09-29 JM; message also clarified
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/timeadjust.php');
                cell.innerHTML = savedHTML; // added 2020-09-29 JM; message also clarified
            }
        });
    } // END function timeadjust

<?php /* BEGIN ADDED 2020-10-21 JM */ ?>
$(function() {
    // On document ready...

    // If we've saved a position, scroll to it.
    let scrollTopMain = sessionStorage.getItem('admin_sheet_scrollTopMain');
    $(document).scrollTop(scrollTopMain);
    sessionStorage.removeItem('admin_sheet_scrollTopMain');

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
                        sessionStorage.setItem('admin_sheet_scrollTopMain', scrollTopMain);
                        location.href = 'sheet.php?start=<?= $start ?>&displayType=<?= $displayType ?>&userId=<?= $userId ?>';
                    } else if (data['status'] == 'success') {
                        // On success, reload page (saving position)
                        // save position and refresh
                        let scrollTopMain = $(document).scrollTop();
                        sessionStorage.setItem('admin_sheet_scrollTopMain', scrollTopMain);
                        location.href = 'sheet.php?start=<?= $start ?>&displayType=<?= $displayType ?>&userId=<?= $userId ?>';
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
                        sessionStorage.setItem('admin_sheet_scrollTopMain', scrollTopMain);
                        location.href = 'sheet.php?start=<?= $start ?>&displayType=<?= $displayType ?>&userId=<?= $userId ?>';
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
<?php /* END ADDED 2020-10-21 JM */ ?>
</script>
</body>
</html>
