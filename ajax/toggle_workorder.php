<?php
/*  ajax/toggle_workorder.php

    INPUT $_REQUEST['workOrderId']: primary key to DB table WorkOrder
    INPUT $_REQUEST['isVisible']: value to set. Should be 0 or 1. 0 means "effectively deleted, but just kept to preserve referential integrity". 

    Despite the name, this is not a toggle. It will set 'isVisible' to whatever value is passed in (should be 0 or 1)

    >>>00016: Because this does not thoroughly check its inputs, and it passes $_REQUEST to 
      $workordertask->update(), it would also act on anything else in $_REQUEST that is handled by $workordertask->update:  
      $_REQUEST['extraDescription'], $_REQUEST['fakeInvoice]] or any of a number of other values if those were passed in.
      We should validate to prevent that.
    
    >>>00016: Also, nothing here to make sure isVisible is just 0 or 1.
    
    >>>00016, >>>00002: NEED TO VALIDATE INPUTS and also check for error returns from functions called. 

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if workOrderId not valid or any of several other failures; "success" on success.
        * 'isVisible': the new value of this (0 or 1), returned on success.
        * 'workOrderId': as input, returned on success. 
*/

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$workorder = new WorkOrder($workOrderId);

if (intval($workorder->getWorkOrderId())) {    
    $workorder->update($_REQUEST);
    $workorder = new WorkOrder($workOrderId);
    if (intval($workorder->getWorkOrderId())) {    
        $data['status'] = 'success';        
        $data['isVisible'] = $workorder->getIsVisible();
        $data['workOrderId'] = $workorder->getWorkOrderId();        
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>