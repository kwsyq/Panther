<?php 
/*  ajax/deletedescriptorsubdetail.php

    INPUT $_REQUEST['descriptor2Id']: primary key to DB table Descriptor2. Before 2019-12, this used descriptorSubId
    INPUT $_REQUEST['detailRevisionId']: key in Details API

    Delete any matching row from table DescriptorSubDetail.
    
    Returns JSON for an associative array with the following members:
        * 'status': "fail" if descriptor2Id not valid or on other errors; otherwise "success".
        * 'error': text description of error on status=="fail", irrelevant otherwise
*/

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;
$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;

if (!$descriptor2Id) {
    $data['error'] = 'ajax/deletedescriptorsubdetail.php must have descriptor2Id';
    return $data;
}

$db = DB::getInstance();

$query = "DELETE FROM " . DB__NEW_DATABASE . ".descriptorSubDetail ";
$query .= "WHERE descriptor2Id=$descriptor2Id ";
$query .= "AND detailRevisionId=$detailRevisionId;";

$result = $db->query($query);
if (!$result) {
    $data['error'] = "Hard DB error";
    $logger->errorDb('1577122692', $data['error'], $db);
    return $data;
}

$data['status'] = 'success';
header('Content-Type: application/json');
echo json_encode($data);

?>