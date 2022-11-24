<?php
/*  ajax/task_contract_status.php

    INPUT $_REQUEST['workOrderTaskId']: Primary key in DB table WorkOrderTask 
    INPUT $_REQUEST['taskContractStatus']: field in DB table workOrderTask 

    Despite the name, this is not a toggle. It will happily set the same value that already exists. Default value is 0.
    On contract this will hide/show a woT by clicking on the black arrow.

    There are only 2 valid task statuses: active (1) and done (9). If the input taskContractStatus is active, 
    then this can have the side effect of changing the corresponding workOrderTask from hide to active on a contract.

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if workOrderTaskId not valid or on other failures; "success" on success.
        * 'linkTaskStatusId': resulting taskContractStatus, active (1) and done (9)
        * 'taskContractStatus': same information as linkTaskStatusId in a different form: 1 for active, 0 for done.
        * 'workOrderTaskId': on success, should always match input. 
*/    

include '../inc/config.php';
include '../inc/access.php';


$data = array();
$data['status'] = 'fail';

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$workordertask = new WorkOrderTask($workOrderTaskId);


if (intval($workordertask->getWorkOrderTaskId())) {    
    $workordertask->update($_REQUEST);   
    
    // At this point, the DB is updated. The rest of this is about the return.    
    $workordertask = new WorkOrderTask($workOrderTaskId);
    if (intval($workordertask->getWorkOrderTaskId())) {
        $data['status'] = 'success';        
        $data['taskContractStatus'] = $workordertask->getTaskContractStatus();
        if ($workordertask->getTaskContractStatus() == 9) {
            $data['taskContractStatus'] = 0;
        } else {
            $data['taskContractStatus'] = 1;
        }        
        if ($workordertask->getTaskContractStatus() == 9) {
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