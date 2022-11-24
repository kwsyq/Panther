<?php 
/*  ajax/getsignofftimedialog.php

    EXECUTIVE SUMMARY: time.php needs to dynamically build the dialog for a user to sign off their timesheet (dialog to launch by clicking 'signing-timesheet'), 
        because relevant DB values may have changed since the page time.php was fetched. We call this function to build the HTML in the dialog. 
    
    PRIMARY INPUTS:
        * $_REQUEST['displayType']: display type of page, e.g. 'incomplete' (default), 'workWeek' 'workWeekAndPrior'.
        * $_REQUEST['start']: the date the week (or other period) starts
        These are exactly the same as the inputs of time.php, where they are more thoroughly documented.
        
    RETURN an associative array. On invalid inputs it will return with status="fail". Otherwise:
        * 'status': "success"
        * 'html': the HTML for the dialog    

*/

include dirname(__FILE__).'/../inc/config.php';
include dirname(__FILE__).'/../inc/access.php';

// We should NOT continue after a hard DB error!
function returnAfterError() {
    global $response;
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}
$response = array();
$response['status'] = 'fail';
$response['html'] = '';

$v = new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();
if ($error){
    $logger->error2('1603219750', "Error(s) found in init validation: [".json_encode($v->errors())."]");
    returnAfterError();
}
$v->stopOnFirstFail();
$v->rule('required', ['start', 'displayType']);
$v->rule('regex', 'start', '/^20[0-9][0-9]-(0[1-9]|1[0-2])-([0-2][1-9]|3[0-1])$/'); // validate date is in the 2000s; some invalid dates (like February 30) will sneak through
$v->rule('in', 'displayType', ['incomplete', 'workWeek', 'workWeekAndPrior']);
if( !$v->validate() ) {
    $logger->error2('1602268956', "Invalid input. Errors found: ".json_encode($v->errors()));
    returnAfterError();
}

$displayType = isset($_REQUEST['displayType']) ? $_REQUEST['displayType'] : '';
$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';

$time = new Time($user, $start, $displayType);
$html = '';

$parts = explode('-', $time->beginIncludingPrior);
if ($parts[2] == 1) {
    $parts[2] = 15;
} else {
    $month = $parts[1]; 
    if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
        $parts[2] = 30;
    } else if ($month == 2) {
        $year = $parts[0];
        // simplifying the rule, because this will be good until 2099
        $parts[2] = ($year % 4) ? 28 : 29;
    } else {
        $parts[2] = 31;
    }
}
$payPeriodEnd = implode('-', $parts);

$displayPayPeriodStart = date("M j, Y", strtotime($time->beginIncludingPrior));
$displayPayPeriodEnd = date("M j, Y", strtotime($payPeriodEnd));

$periodDisplay = 'this period';
if ($displayPayPeriodStart && $displayPayPeriodEnd) {
    $periodDisplay = "the period $displayPayPeriodStart - $displayPayPeriodEnd";  
} else if ($displayPayPeriodStart) {
    $periodDisplay = "the period beginning $displayPayPeriodStart";
} else if ($displayPayPeriodEnd) {
    $periodDisplay = "the period ending $displayPayPeriodEnd";
}

$reopened = $time->reopenTime !== null;
$adminSigned = $time->adminSignedPayrollTime !== null;
if ($adminSigned && ! $reopened) {
    // user never signed this off, but a manager did!
    $html .= 'You are currently submitting a timesheet for a pay period that a manager already reviewed.<br />' . "\n";
    $html .= 'Click "Sign off" to sign off your time for '. $periodDisplay .', or "Cancel" to continue entering/modifying your time.<br /><br />' . "\n";
    $html .= 'After you sign off, you may want to contact a manager to make sure your paycheck is based on the correct numbers.' . "\n";
} else { 
    if ($reopened) {
        $html .= 'You are currently amending a timesheet that was already submitted' .
                    ($adminSigned ? ' and which a manager has reviewed' : '') .
                    '.<br />' . "\n";
        $netLateWotModifications = $time->getWorkOrderTaskTimeNetLateModifications();
        $netLatePtoModifications = $time->getPtoNetLateModifications();
        $totalOldMinutes = 0;
        $totalNewMinutes = 0;
        foreach ($netLatePtoModifications as $netLatePtoModification) {
            $totalOldMinutes += $netLatePtoModification['oldMinutes'];
            $totalNewMinutes += $netLatePtoModification['newMinutes'];
        }
        foreach ($netLateWotModifications as $netLateWotModification) {
            $totalOldMinutes += $netLateWotModification['oldMinutes'];
            $totalNewMinutes += $netLateWotModification['newMinutes'];
        }
        
        if ($totalOldMinutes == $totalNewMinutes) {
            $html .= 'Your total time for this period remains the same.<br />' . "\n";
        } else if ($totalOldMinutes > $totalNewMinutes) {
            $html .= 'Your total time for this period has decreased by ' . 
            number_format((float)($totalOldMinutes - $totalNewMinutes)/60, 2, '.', '') . ' hr.<br />' . "\n";
        } else {
            $html .= 'Your total time for this period has increased by ' . 
            number_format((float)($totalNewMinutes - $totalOldMinutes)/60, 2, '.', '') . ' hr.<br />' . "\n";
        }
    }
    $html .= 'Click "Sign off" to sign off your time for ' . $periodDisplay . ', or "Cancel" to continue entering/modifying your time.<br /><br />' . "\n";
    if ($adminSigned) {
        $html .= 'After you sign off, you may want to contact a manager to make sure your paycheck is based on the correct numbers.' . "\n";
    } else {
        $html .= 'Once you sign off, a manager will consider your timesheet complete, and can base your paycheck on these numbers.' . "\n";
    }
}                    

$response['status'] = 'success';
$response['html'] = $html;

header('Content-Type: application/json');
echo json_encode($response);
die();

?>