
<?php 
/*  ajax/newinvoice.php

    INPUT $_REQUEST['workOrderId']: primary key in DB table WorkOrder

    Generate new invoice for specified workOrder. 
    
    If there is a committed contract for the workOrder in question, we are invoicing
    for that contract and for possibly additional work. However, the actually more common
    case is that there is no contract for a workOrder as such, that we are operating
    under a broader fee-for-service business agreement, and that the invoice is simply
    for the work done under a workOrder, with no contract involved.

    Returns JSON for an associative array with only the following member:    
        * status: "fail" if workOrderId not valid or on other errors; otherwise "success". 
*/
include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-08-17, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$data = array();
$data['status'] = 'fail';
$data['error'] = '';
$db = DB::getInstance();

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'workOrderId');
$v->rule('integer', 'workOrderId');
$v->rule('min', 'workOrderId', 1);


if(!$v->validate()) {   // if any error in validation (fields => rules + manually added errors) the validator generates
                        // the return structure with 'fail' status, returns the JSON to caller and exits

    $logger->error2('637432078590667989', "Error input parameters ".json_encode($v->errors()));    
    header('Content-Type: application/json');    
    echo $v->getErrorJson();
    die();
} else {

    $workOrderId = intval($_REQUEST['workOrderId']);

    if (existWorkOrder($workOrderId)) {
        $workOrder = new WorkOrder($workOrderId);
        if(!$workOrder->newInvoiceInternal($workOrderId)) {
            $logger->error2('637437179347217554', "We could not make an Invoice.");
            $data['error'] = "We could not Make an Invoice. Please contact an administrator.";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
    } else {
        $logger->error2('637432063251266293', "Not a valid workOrderId. Input given :  $workOrderId ");
        $data['error'] = "We could not Make an Invoice. Please contact an administrator.";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
    
}

if (!$data['error']) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>