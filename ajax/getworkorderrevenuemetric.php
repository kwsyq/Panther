<?php
/*  ajax/getworkorderrevenuemetric.php

    INPUT $_REQUEST['workOrderIdList']: List of primary keys in DB table WorkOrder;
    allows comma-separated lists of groupings of workOrderIds (typically for a single job);
    no spaces, semi-colon separator between jobs. 
    Nothing enforces that being exactly how it's used, but return makes it useful to work that way.
    
    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      * 'metrics': associative array, indexed by comma-separated lists of groupings of workOrderIds; value
        is a formatted, comma-separated string with the respective values from WorkOrder::revenueMetric.
        
   >>>00038 As of 2020-04-10, even if the user has $timeSummaryPerm and not $adminPerm, we do NOT check to make sure that these
   workorders are all ones where that user is on the team for the workorder or the associated job. That would be an expensive
   check, but imaginably might be worth doing, certainly if this were ever to be used for multiple customers on the same system.
   
*/

include '../inc/config.php';
include '../inc/access.php';
include '../inc/perms.php';

// >>>00016: We may want to work out what (if any) better input validation we can do here;
//  feel free to grab me to discuss. JM 2020-01-29

// >>>00038 We may want to put more security around this; ideally limit callers
// to those who get $timeSummaryPerm==true in top-level person.php, but that requires
// doing some comparisons we don't have access to here. Maybe try to limit by origin
// of page calling this?

$data = array();
$data['status'] = 'fail';
$data['error'] = '';
$data['metrics'] = [];

$v=new Validator2($_REQUEST);

$v->rule('required', 'workOrderIdList');
$v->rule('regex', 'workOrderIdList', '/^((\d+)(,\d+)*)(;((\d+)(,\d+)*))*$/');

if(!$v->validate()){
    $logger->error2('1580341022', "Error input parameters ".json_encode($v->errors())); // >>>00016: Cristi, I modeled here on what you did elsewhere,
                                                                                        // but I don't really know whether this would provide a useful log.
    header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

// BEGIN ADDED 2020-04-10 JM for http://bt.dev2.ssseng.com/view.php?id=120
if (is_command_line_interface()) {
    // We are going to assume full permissions
    $adminPerm = true;
    $timeSummaryPerm = true;
} else {
    $adminPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_ADMIN); 
    $timeSummaryPerm = $adminPerm || 
            checkPerm($userPermissions, 'PERM_OWN_TIME_SUMMARY', PERMLEVEL_R);
            
}

if (!$adminPerm && !$timeSummaryPerm) {
    $data['error'] = 'Insufficient permission';
    $logger->error2('1586547187', "Insufficient permission for revenue metrics, user " . ($user ? $user->getUserId() : 'unidentified'));
    echo json_encode($data);
    die();
}
// END ADDED 2020-04-10 JM

$workOrderIdList = $_REQUEST['workOrderIdList']; 
$workOrderIdsByJob = explode(';', $workOrderIdList);
foreach ($workOrderIdsByJob AS $oneJobList) {
    $str = '';
    $workOrderIds = explode(',', $oneJobList);
    foreach($workOrderIds AS $workOrderId) {
        if (strlen($str)) { // 2020-04-10 JM to address http://bt.dev2.ssseng.com/view.php?id=120: changed this from just if ($str), because what if $str == '0'? 
            // Not the first
            $str .= ', ';
        }
        $workOrder = new WorkOrder(intval($workOrderId));
        $metric = $workOrder->revenueMetric();
        if ($metric === null) {
            $str .= '--';
        } else if ($adminPerm) { // check for adminPerm added 2020-04-10 JM to address http://bt.dev2.ssseng.com/view.php?id=120
            // two digits past the decimal point
            $str .= number_format($metric, 2);
        } else {
            // This case added 2020-04-10 JM to address http://bt.dev2.ssseng.com/view.php?id=120
            /* We fudge this a little, but still keep it meaningful. A little better precision than 1 digit.
               Leading two digits can be:
                10
                12
                15
                18
                20
                23
                27
                30
                35
                40
                45
                50
                etc. up to 95.
                */
                
             // if it's in a range where we won't ultimately say 0, we want to guarantee at least 3 leading digits to work with before the decimal    
             $metric_times_10000 = intval($metric * 10000); 
              
             if ($metric_times_10000 < 50) {
                 $str .= 0;
             } else if ($metric_times_10000 < 100) {
                 $str .= 0.01;
             } else {
                 // We will have at least 3 digits when we write $metric_times_10000 as an integer
                 $scratch_str_1 = '' . $metric_times_10000;
                 $first_three_digits = substr($scratch_str_1, 0, 3);
                 $count_additional_digits = strlen($scratch_str_1) - 3;
                 
                 if ($first_three_digits < '110') {
                     $first_three_digits = '100';
                 } else if ($first_three_digits < '135') {
                     $first_three_digits = '120';
                 } else if ($first_three_digits < '155') {
                     $first_three_digits = '150';
                 } else if ($first_three_digits < '19') {
                     $first_three_digits = '180';
                 } else if ($first_three_digits < '215') {
                     $first_three_digits = '200';
                 } else if ($first_three_digits < '250') {
                     $first_three_digits = '230';
                 } else if ($first_three_digits < '285') {
                     $first_three_digits = '270';
                 } else if ($first_three_digits < '325') { // from here down, the pattern is quite simple
                     $first_three_digits = '300';
                 } else if ($first_three_digits < '375') {
                     $first_three_digits = '350';
                 } else if ($first_three_digits < '425') {
                     $first_three_digits = '400';
                 } else if ($first_three_digits < '475') {
                     $first_three_digits = '450';
                 } else if ($first_three_digits < '525') {
                     $first_three_digits = '500';
                 } else if ($first_three_digits < '575') {
                     $first_three_digits = '550';
                 } else if ($first_three_digits < '625') {
                     $first_three_digits = '600';
                 } else if ($first_three_digits < '675') {
                     $first_three_digits = '650';
                 } else if ($first_three_digits < '725') {
                     $first_three_digits = '700';
                 } else if ($first_three_digits < '775') {
                     $first_three_digits = '750';
                 } else if ($first_three_digits < '825') {
                     $first_three_digits = '800';
                 } else if ($first_three_digits < '875') {
                     $first_three_digits = '850';
                 } else if ($first_three_digits < '925') {
                     $first_three_digits = '900';
                 } else if ($first_three_digits < '975') {
                     $first_three_digits = '950';
                 } else {
                     $first_three_digits = '1000'; // OK, so sue me, rounding up here gived 4 digits, but it works. 
                 }
                 $scratch_str_2 = '' . $first_three_digits;
                 for ($i=0; $i<$count_additional_digits; ++$i) {
                     $scratch_str_2 .= '0';
                 }
                 
                 $rounded_metric = intval($scratch_str_2) / 10000;
                 if ($rounded_metric >= 10) {
                     $display_rounded_metric = intval($rounded_metric);
                 } else if ($rounded_metric > 1) {
                     $display_rounded_metric = number_format($rounded_metric, 1);
                 } else {                     
                     $display_rounded_metric = number_format($rounded_metric, 2);
                 }
                 
                 $debug_this = false; // set this true if you want to understand the effect of this
                 if ($debug_this) {
                     $logger->debug2('1586549561', "metric $metric will display as $display_rounded_metric");
                 }                        
                 
                 $str .= $display_rounded_metric;
             }
        }
    }
    $data['metrics'][$oneJobList] = $str;    
}

$data['status'] = 'success';
header('Content-Type: application/json');
echo json_encode($data);
?>
