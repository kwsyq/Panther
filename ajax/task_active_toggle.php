<?php
/*  ajax/task_active_toggle.php

    INPUT $_REQUEST['workOrderTaskId']: Primary key in DB table WorkOrderTask 
    INPUT $_REQUEST['taskStatusId']: Primary key in DB table TaskStatus 

    Despite the name, this is not a toggle. It will happily set the same value that already exists.

    There are only 2 valid task statuses: active (1) and done (9). If the input taskStatusId is active, 
    then this can have the side effect of changing the corresponding workOrder from done to active.

    Verifies that taskStatusId is a valid ID, takes no action (but "succeeds") if it is not. 
    >>>00016: Because this does not thoroughly check its inputs, and it passes $_REQUEST to 
      $workordertask->update(), it would also act on anything else in $_REQUEST that is handled by $workordertask->update:  
      $_REQUEST['extraDescription'], $_REQUEST['fakeInvoice]] or any of a number of other values if those were passed in.
      We should validate to prevent that.

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if workOrderTaskId not valid or on other failures; "success" on success.
        * 'linkTaskStatusId': resulting taskStatusId, active (1) and done (9)
        * 'taskStatusId': same information as linkTaskStatusId in a different form: 1 for active, 0 for done.
        * 'workOrderTaskId': on success, should always match input. 
*/    

include '../inc/config.php';
include '../inc/access.php';

//sleep(1);  // Martin comment: this is so the user can see the progress gif

$data = array();
$data['status'] = 'fail';

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$workordertask = new WorkOrderTask($workOrderTaskId);

// >>>00006 NOTE that we make three calls to $workordertask->getWorkOrderTaskId(),
// all of which should return the same value (which will be the same as any legitimate value
// in $_REQUEST['workOrderTaskId'], or 0 if that value is not legitimate. Wouldn't it be
// clearer, on the first call to this, either to overwrite $workOrderTaskId or to store that in 
// a different variable and use only that other variable from this point forward?
if (intval($workordertask->getWorkOrderTaskId())) {    
    $workordertask->update($_REQUEST);    // NOTE this passing on of whatever got passed in >>>00016 with no validation
    
    // At this point, the DB is updated. The rest of this is about the return.    
    $workordertask = new WorkOrderTask($workOrderTaskId);
    if (intval($workordertask->getWorkOrderTaskId())) {
        $data['status'] = 'success';        
        $data['taskStatusId'] = $workordertask->getTaskStatusId();
        if ($workordertask->getTaskStatusId() == 9) {
            $data['taskStatusId'] = 0;
        } else {
            $data['taskStatusId'] = 1;
        }        
        if ($workordertask->getTaskStatusId() == 9) {
            $data['linkTaskStatusId'] = 1;
        } else {
            $data['linkTaskStatusId'] = 9;
        }        
        $data['workOrderTaskId'] = $workordertask->getWorkOrderTaskId();    
    }
}


header('Content-Type: application/json');
echo json_encode($data);
die();

?>