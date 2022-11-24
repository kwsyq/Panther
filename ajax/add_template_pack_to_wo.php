<?php
/*  
    ajax/add_template_pack_to_wo.php

    Usage on workordertasks.php
    Add a task packages to the workorder on drag and drop over an element.

    PRIMARY INPUTS: 
        $_REQUEST['workOrderId']: primary key in DB table workOrder.
        $_REQUEST['elementId'] : primary key in DB table element.
        $_REQUEST['parentTaskId']: the parent of a workOrderTask.
        $_REQUEST['packageTasks']: package with hierarchy tasks to add to an element.
        
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';
    $elementId = isset($_REQUEST['elementId']) ? $_REQUEST['elementId'] : '';
    $parentTaskId = isset($_REQUEST['parentTaskId']) ? $_REQUEST['parentTaskId'] : '';
    $packageTasks = isset($_REQUEST['packageTasks']) ? $_REQUEST['packageTasks'] : array();
    array_shift($packageTasks);

    if($elementId) {
        //Drop over the Element.
        
            function writeDb($arr, $pid, $parentTaskId) {
                global $db;
                global $elementId;
                global $workOrderId;
            
                // write entry in Db:
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTask (";
                $query .= "workOrderId, taskId, parentTaskId ";
                $query .= ") VALUES (";
                $query .= intval($workOrderId);
                $query .= ", " . intval($arr["taskId"]);
                $query .= ", " . intval($pid). ") ";
                
               
                $db->query($query);
        
                $pid = $db->insert_id;
               

                $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskElement (";
                $query .= "workOrderTaskId, elementId ";
                $query .= ") VALUES (";
                $query .= intval($pid);
                $query .= ", " . intval($elementId). ") ";

                $db->query($query);

               
                if(!isset($arr["items"])) {
                    return;
                } else {
                    foreach($arr["items"] as $value) {
                        writeDb($value, $pid, $elementId);
                    }
                }
            }

            foreach($packageTasks["items"] as $arr) {
                writeDb($arr, $elementId, $parentTaskId);
            }
           
        

    } 
  
  

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>