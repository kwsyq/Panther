<?php
/*  ajax/addtaskdetail.php

    INPUTS: 
        * $_REQUEST['taskId']: primary key in DB table task
        * $_REQUEST['detailRevisionId']: key in Details API
        
    If we don't already have a corresponding row for (taskId, detailRevisionId) in DB table taskDetail, insert it.
    Acts only if taskId is valid (row exists in task table).
    No explicit return.
*/


include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);

$v->rule('required', ['taskId', 'detailRevisionId']);
$v->rule('integer', ['taskId', 'detailRevisionId']);
$v->rule('min', ['taskId', 'detailRevisionId'], 1);

if(!$v->validate()){
    $logger->error2('1572336673', "Error input parameters ".json_encode($v->errors()));
    header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$db = DB::getInstance();

if( $db->connect_errno ){
    $data['error'] = "Unable to connect to DB";
    $logger->errorDb('1578587834', $data['error'], $db);
}

$taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;
$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;


if(!$data['error']){
    if (!existTaskId($taskId)) {    
        $data['error'] = "TaskId does not exist!";
        $logger->error2('1578596107', "TaskId $taskId does not exist!");
    }
}

$exists = false;
if(!$data['error']){
    // we have valid taskId
    $query = " select taskDetailId from " . DB__NEW_DATABASE . ".taskDetail where taskId = " . intval($taskId) . " and detailRevisionId = " . intval($detailRevisionId);
    $result = $db->query($query);
    if ($result) { 
        if ($result->num_rows > 0) {
            $exists = true;
        }
    } else {
        $data['error'] = "Hard DB error";
        $logger->errorDb('1578588043', $data['error'], $db);
    } 
}


if(!$data['error']){
    if (!$exists) {
        $query = "insert into " . DB__NEW_DATABASE . ".taskDetail (taskId, detailRevisionId) values (";
        $query .= " " . intval($taskId) . " ";
        $query .= " ," . intval($detailRevisionId) . " ";
        $query .= ") "; 
           
        $result = $db->query($query);        

        if(!$result){
            $data['error'] = "Error inserting detailRevisionId ";
            $logger->errorDb('1578588248', $data['error'], $db);
        }
    }    
}

if (!$data['error']) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
exit;

?>