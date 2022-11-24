<?php 

/*  ajax/addworkorderdetail.php

    INPUTS:
        $_REQUEST['workOrderId']) : primary key in DB table workOrder
        $_REQUEST['detailRevisionId']: key in Details API 
        
        Effect: Makes (or "unhides") appropriate entry in DB table workOrderDetail. 
        More precisely: 
          * if there is already a corresponding row in DB table workOrderDetail, then set hidden=0 for that row. 
          * Otherwise, insert a new row in DB table workOrderDetail with specified workOrderId and detailRevisionId from input, 
            personId from current user. 
            
        No explicit return. >>>00001 but there probably should be.
        
        Acts only if workOrderId is valid (row exists in workOrder table).
*/

include '../inc/config.php';
include '../inc/access.php';

$v=new Validator2( $_REQUEST);
/*
    [CP] 2019-11-19
    both fields are required in order to do db queries (test + insert if necessary)
    both fields must be integer and bigger than 0 as ids in table
*/
$v->rule('required', ['workOrderId', 'detailRevisionId']); 
$v->rule('integer', ['workOrderId', 'detailRevisionId']); 
$v->rule('min', 'workOrderId', 1); 
$v->rule('min', 'detailRevisionId', 1);

if(!$v->validate()){
    $logger->error2('1574366651', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson(); // >>>00001 >>>00026 JM to CP: How does this fit in with the "no explicit return" above? Seems
                             // to me to contradict that. Might be harmless, but is at best odd, and certainly should not go
                             // without remark. Do you have some intent in the broader context?
    exit;
}

$db = DB::getInstance();

// 2019-12-04 JM: simplifying, given the validation above
/* REPLACED 2019-12-04 JM
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;
*/
// BEGIN REPLACEMENT CODE 2019-12-04 JM
$workOrderId = intval($_REQUEST['workOrderId']);
$detailRevisionId = intval($_REQUEST['detailRevisionId']);
// END REPLACEMENT CODE 2019-12-04 JM

if (WorkOrder::validate($workOrderId)) {
    $workOrderDetailId = 0;
    $query = "SELECT workOrderDetailId ";
    $query .= "FROM " . DB__NEW_DATABASE . ".workOrderDetail ";
    $query .= "WHERE workOrderId = " . intval($workOrderId) . " ";
    $query .= "AND detailRevisionId = " . intval($detailRevisionId) . ";";
    $result = $db->query($query);
    if ($result) {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $workOrderDetailId = $row['workOrderDetailId'];
        }
        
        if (intval($workOrderDetailId)) {
            $query = "UPDATE " . DB__NEW_DATABASE . ".workOrderDetail SET hidden = 0 ";
            $query .= "WHERE workOrderDetailId = " . intval($workOrderDetailId) . ";";
            
            $result = $db->query($query);
            $oper="update";
        } else {        
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderDetail (workOrderId, detailRevisionId, personId) VALUES (";
            $query .= intval($workOrderId) . " ";
            $query .= ", " . intval($detailRevisionId);
            $query .= ", " . intval($user->getUserId());
            $query .= "); ";
            
            $result = $db->query($query);        
            $oper="insert";
        }    
    
        if (!$result) {
            $logger->errorDb('1574363190', "Error ".$oper." workOrderDetail", $db);
        }
    } else {
        $logger->errorDb('1574362814', "Hard error", $db);
    }
} else {
    $logger->info2('1574362737', "workOrderId $workOrderId does not exist");
}

?>