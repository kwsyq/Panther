<?php 
/*  ajax/deleteworkordertask.php

    Usage:  on workordertask page. 
        Assuming workorderId is valid, delete any matching workOrderTask. 

    INPUT $_REQUEST['workOrderId']: primary key in DB table WorkOrder
    INPUT $_REQUEST['workOrderTaskId']: primary key in DB table WorkOrderTask

     
    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error with errorId ),
        * 'status': "success" on successful query.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$data = array();
$data['status'] = 'fail';
$data['error'] = '';
$data['errorId'] = '';

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;

$wo = new WorkOrder($workOrderId);
$wot = new WorkOrderTask($workOrderTaskId); // was before, not used.

    if (intval($wo->getWorkOrderId()) != intval($workOrderId)) {
        $error = "Invalid workOrderId from Request.";
        $data['errorId'] = '637794185188389912';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/deleteworkordertask.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 

    if(!$workOrderTaskId) { 
        $error = "Invalid workOrderTaskId from Request.";
        $data['errorId'] = '637794183988131598';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/deleteworkordertask.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 
            
    $query = " DELETE FROM " . DB__NEW_DATABASE . ".workOrderTaskElement ";
    $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);
    
    $result = $db->query($query);
    if (!$result) {
        $error = "We could not delete this workOrderTask from workOrderTaskElement. Database Error";
        $data['errorId'] = '637794181985063197';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/deleteworkordertask.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    // Basically a deletion from table workOrderTask; details discussed in inc/classes/WorkOrder.class.php.
    // Returns a boolean. True on success query.
    $success = $wo->deleteWorkOrderTask($workOrderTaskId);
    if(!$success) {
        $error = "We could not delete this workOrderTask. Database Error";
        $data['errorId'] = '637794182580828788';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/deleteworkordertask.php: $error";
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