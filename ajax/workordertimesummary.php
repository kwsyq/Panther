<?php
/* ajax/workordertimesummary.php

   Wraps a set of calls to functions in includes/workordertimesummary.php to get that information dynamically.

   Longtime users may know this functionality as "TXN". It should be
   available only if user has admin-level invoice permission.
   
   Basically, this gives the customer (as of 2020-01, always SSS) a way to 
   eyeball how well they did in business terms for a particular workOrder.
   
   INPUT $_REQUEST['workOrderIdList']: Comma-separated list of primary keys in DB table WorkOrder.
   
   Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      * 'html': the HTML containing revenue information for this workOrder. NOTE that there is no
        outer DIV or other container element: caller should provide that.
   
*/
require_once __DIR__ . '/../inc/config.php';
require_once BASEDIR . '/includes/workordertimesummary.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';
$data['html'] = '';

$v=new Validator2($_REQUEST);

$v->rule('required', 'workOrderIdList');
$v->rule('regex', 'workOrderIdList', '/^\d+(,\s*\d+)*$/');

if(!$v->validate()){
    $logger->error2('1580415927', "Error input parameters ".json_encode($v->errors())); // >>>00016: Cristi, I modeled here on what you did elsewhere,
                                                                                        // but I don't really know whether this would provide a useful log.
    header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$workOrderIdList = $_REQUEST['workOrderIdList'];
$workOrderIds = explode(',', $workOrderIdList);

$html = '<h2 class="heading kill-with-work-order-time-summary">Work Order time summary</h2>' . "\n";
$html .= workOrderTimeSummaryCloseButton();
if (count($workOrderIds)) {    
    foreach($workOrderIds AS $workOrderId) {
        $workOrder = new WorkOrder(intval(trim($workOrderId)));
        $html .= '<h3 class="work-order-time-summary-heading">' . $workOrder->getDescription() . ' (' . $workOrder->getWorkOrderId(). ')</h3>'."\n"; 
        $html .= workOrderTimeSummaryBody($workOrder, null, false);
    }
    $html .= workOrderTimeSummaryCloseButton();
} else {
    $html .= '<p class="heading kill-with-work-order-time-summary">Nothing to display</p>' . "\n";
}

$data['status'] = 'success';
$data['html'] = $html;
header('Content-Type: application/json');
echo json_encode($data);
?>
