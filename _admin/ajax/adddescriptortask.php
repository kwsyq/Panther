<?php 
/* _admin/ajax/adddescriptortask.php

    INPUT $_REQUEST['taskId']: primary key to DB table Task
    
    INPUT $_REQUEST['descriptor2Id']: primary key to DB table Descriptor2.
    MUST HAVE EXACTLY ONE OF THESE LATTER TWO INPUTS NON-ZERO.
    
    Verifies that the task and the descriptor2Id exist. 
    if OK, and if DB table descriptorSubTask doesn't already have a row for this descriptor2 and task, add that row.

    Returns JSON for an associative array with the following members:
        * 'status': 'success'  or 'fail'
        * 'error': only relevant on failure. Error message.
        
    Largely rewritten 2020-01-14 JM
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);
$v->rule('required', ['descriptor2Id', 'taskId']);
$v->rule('integer', ['descriptor2Id', 'taskId']); 
$v->rule('min', 'descriptor2Id', 1);
$v->rule('min', 'taskId', 1);

if(!$v->validate()){
    $logger->error2('1579041525', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;
$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;

if ( ! Task::validate($taskId)) {
    $data['error'] = "$taskId is not a valid taskId"; 
} else if ( ! Descriptor2::validate($descriptor2Id)) {
    $data['error'] = "$descriptor2Id is not a valid descriptor2Id"; 
}

if ( !$data['error'] ) {
    $exists = false;    
    $db = DB::getInstance();
    
    $query = "SELECT  descriptorSubTaskId ";
    $query .= "FROM  " . DB__NEW_DATABASE . ".descriptorSubTask  ";
    $query .= "WHERE descriptor2Id = " . intval($descriptor2Id);
    $query .= "AND taskId = " . intval($taskId) . ';';
    
    $result = $db->query($query);
    if (!$result) {
        $data['error'] = 'Descriptor2::getDeactivated: Hard DB error';
        $logger->errorDb('1579041581', $data['error'], $db);
    } else {
        $exists = !!$result->num_rows; // force to Boolean
    }
}

if ( !$data['error'] && !$exists) {
    $query = " insert into  " . DB__NEW_DATABASE . ".descriptorSubTask(descriptor2Id, taskId) values(";
    $query .= " " . intval($descriptor2Id) . " ";
    $query .= ", " . intval($taskId) . ") ";
    
    $result = $db->query($query);
    if (!$result) {
        $data['error'] = 'Descriptor2::getDeactivated: Hard DB error';
        $logger->errorDb('1579041877', $data['error'], $db);
    }
}

if ( !$data['error'] ) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>