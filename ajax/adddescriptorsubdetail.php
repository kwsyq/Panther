<?php
/*  ajax/adddescriptorsubdetail.php

    INPUTS: 
        $_REQUEST['descriptor2Id']: primary key to DB table Descriptor2. Before 2019-12, used descriptorSubId.
        $_REQUEST['detailRevisionId']: key in Details API

    If a corresponding row doesn't yet exist in DB table descriptorSubDetail, inserts it.

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if descriptor2Id not valid or on other errors; otherwise "success".
        * 'error': text description of error on status=="fail", irrelevant otherwise
*/    

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);
/*
    [CP] 2019-11-19
    both fields are required in order to do db queries (test + insert if necessary)
    both fields must be integer and bigger than 0 as ids in table

*/

/* >>>00001 JM 2019-12-23 to Cristi: this file changed enough today that most of the old validation is no
   longer any good; I'm killing it, you'll need to work out what you can do in the new situation.
   PLEASE TREAT THIS FILE LIKE IT WAS BRAND NEW.
*/   
/* OLD CODE replaced   
$v->rule('required', ['descriptorSubId', 'detailRevisionId']); 
$v->rule('integer', ['descriptorSubId', 'detailRevisionId']); 
$v->rule('min', 'descriptorSubId', 1);
*/
// BEGIN REPLACEMENT CODE 2020-01-06 JM
$v->rule('required', ['descriptor2Id', 'detailRevisionId']);
$v->rule('integer', ['descriptor2Id', 'detailRevisionId']); 
$v->rule('min', 'descriptor2Id', 1);
$v->rule('min', 'detailRevisionId', 1);
// END REPLACEMENT CODE 2020-01-06 JM

if(!$v->validate()){
    $logger->error2('1574364035', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$db = DB::getInstance();

if( $db->connect_errno ){
    $data['error'] = "Unable to connect to DB";
    $logger->errorDb('1578597947', $data['error'], $db);
}

$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;
$detailRevisionId = intval($_REQUEST['detailRevisionId']);

if (!$data['error']) {
    if (!Descriptor2::validate($descriptor2Id, '1577122692')) {
        $data['error'] = "Invalid descriptor2Id $descriptor2Id"; 
        $logger->error2('1578592183', $data['error']);
    }
}

if (!$data['error']) {
    $exists = false;
    $query = "SELECT descriptorSubDetailId FROM " . DB__NEW_DATABASE . ".descriptorSubDetail " .
             "WHERE descriptor2Id = " . intval($descriptor2Id) . 
             " AND detailRevisionId = " . intval($detailRevisionId);
    
    $result = $db->query($query);
         
    if ($result) { 
        if ($result->num_rows > 0) {
            $exists = true;
        }
    } else {
        $data['error'] = "Hard DB error";
        $logger->errorDb('1574362347', $data['error'], $db);
    } 
}

if (!$data['error']) {
    if (!$exists){
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".descriptorSubDetail (descriptor2Id, descriptorSubId, detailRevisionId) VALUES (";
        $query .= intval($descriptor2Id);
        $query .= ", " . ($descriptorSubId===null ? 'NULL' : intval($descriptorSubId));
        $query .= ", " . intval($detailRevisionId);
        $query .= ");";
    
        $result= $db->query($query);        
    
        if(!$result) {            
            $data['error'] = "Error inserting descriptorSubDetail";
            $logger->errorDb('1574362618', $data['error'], $db);
        }
    }
}

if (!$data['error']) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>