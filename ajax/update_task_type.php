<?php
/*  ajax/update_task_type.php

    Usage: in contract.php
        For each workOrderTask there is a taskType. After the selection from dropdown the tasktype is updated. 
        Possible values: overhead(default), qty/item, fixed, ast hourly. Values from taskType table.


    INPUT $_REQUEST['workOrderTaskId']: primary key in DB table workOrderTask.
    INPUT $_REQUEST['taskTypeId']: primary key in DB table taskType.


    EXECUTIVE SUMMARY:  
        * Updates table workOrderTask with the specified values from REQUEST.

    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error ),
        * 'status': "success" on successful query.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    // update taskTypeId for this workOrderTaskId
    $taskTypeId = isset($_REQUEST['taskTypeId']) ? intval($_REQUEST['taskTypeId']) : 0;
    $workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;

    
    $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
    $query .= " taskTypeId = " . intval($taskTypeId) . " ";
    $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not Update the taskTypeId. Database Error";
            $logger->errorDb('637741296524362350', $error, $db);
            $data['error'] = "ajax/update_task_type.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }

   

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>