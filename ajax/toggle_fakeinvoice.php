<?php
/*  ajax/toggle_fakeinvoice.php

    INPUT $_REQUEST['workOrderId']: primary key to DB table WorkOrder

    In table WorkOrder, fakeInvoice is a Boolean; this toggles it. 
    Martin described this 2018-02 as "transitional stuff... there are plans to make 
     it go away with the new invoicing." JM suspects it will still be around
     for a long time.
        
    >>>00016: Because this does not thoroughly check its inputs, and it passes $_REQUEST to 
      $workordertask->update(), it would also act on anything else in $_REQUEST that is handled by $workordertask->update:  
      $_REQUEST['extraDescription'], $_REQUEST['fakeInvoice]] or any of a number of other values if those were passed in.
      We should validate to prevent that.

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if workOrderId not valid or any of several other failures; "success" on success.
        * 'fakeInvoice': the new value of this (0 or 1), returned on success.
        * 'workOrderId': as input, returned on success. 
*/

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$workorder = new WorkOrder($workOrderId);

if (intval($workorder->getWorkOrderId())) {
    // get current value
    $fakeInvoice = $workorder->getFakeInvoice();
    
    // toggle it
    if (intval($fakeInvoice)){
        $_REQUEST['fakeInvoice'] = 0;
    } else {
        $_REQUEST['fakeInvoice'] = 1;
    }
    $workorder->update($_REQUEST);
    
    $workorder = new WorkOrder($workOrderId);
    if (intval($workorder->getWorkOrderId())) {
        $data['status'] = 'success';
        $data['fakeInvoice'] = $workorder->getfakeInvoice();
        $data['workOrderId'] = $workorder->getWorkOrderId();
    }
}

/*
// BEGIN commented out by Martin before 2019
$data['number'] = $job->getNumber();
$data['name'] = $job->getName();
$data['description'] = $job->getDescription();
//$data['notes'] = $job->getNotes();
$data['status'] = $job->getStatus();
$data['created'] = $job->getCreated();
// END commented out by Martin before 2019
*/

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
