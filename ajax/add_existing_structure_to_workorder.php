<?php
/*  
    ajax/add_existing_structure_to_workorder.php

    Usage: in workordertasks.php. Drag existing workorder structure (Workorder Templates tab )
        to the current workorder ( Workorder Elements tab ). Inserts in Database the tree hierarchy on the current workorder.


    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrderTask.
    INPUT $_REQUEST['elementId'] : primary key in DB table element.
    INPUT $_REQUEST['parentTaskId']: identifies the parent of the workordertask.
    INPUT $_REQUEST['packageTasks']: identifies the tree hierarchy of workordertasks.

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

/*S2205015*/

  
    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';
    $elementId = isset($_REQUEST['elementId']) ? $_REQUEST['elementId'] : '';
    $parentTaskId = isset($_REQUEST['parentTaskId']) ? $_REQUEST['parentTaskId'] : '';
    $packageTasks = isset($_REQUEST['packageTasks']) ? $_REQUEST['packageTasks'] : '';
 
//print_r($_REQUEST);
//die();

    if($elementId) {

        function writeDb($arr, $pid, $parentTaskId) {
            global $db;
            global $elementId;
            global $workOrderId;
            global $user;
            $task= new Task($arr['taskId']);

            // write entry in Db:
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTask (";
            $query .= "workOrderId, taskId, parentTaskId, internalTaskStatus, insertedPersonId, taskContractStatus, taskTypeId, billingDescription ";
            $query .= ") VALUES (";
            $query .= intval($workOrderId);
            $query .= ", " . intval($arr["taskId"]);
            $query .= ", " . intval($pid). ", 0 ";
            $query .= ", " . intval($user->getUserId());
            $query .= ", 1";
            $query .= ", ".$task->getTaskTypeId();
            $query .= ", '".$db->real_escape_string($task->getBillingDescription()). "'";
            $query .= " ) ";

            $db->query($query);
   
            $pid = $db->insert_id;

            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskElement (";
            $query .= "workOrderTaskId, elementId ";
            $query .= ") VALUES (";
            $query .= intval($pid);
            $query .= ", " . intval($elementId). ") ";

            $db->query($query);

            if(!isset($arr["items"])) {
               // return;
            } else {
                foreach($arr["items"] as $value) {
                    writeDb($value, $pid, $elementId);
                }
            }
        }
        foreach($packageTasks['items'] as $node){
            writeDb($node, $elementId, $parentTaskId);
        }
        
    }
 
  
  

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>