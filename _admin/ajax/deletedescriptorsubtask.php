<?php 
/*  _admin/ajax/deletedescriptorsubtask.php

    EXECUTIVE SUMMARY: Delete a row from DB table DescriptorSubTask. Effectively, break the relationship between a descriptorSub and a task.

    INPUT $_REQUEST['descriptorsubtaskId']: primary key to DB table DescriptorSubTask.

    Returns JSON for an associative array with the following members:
        * 'status': always returns 'success' (even if id is invalid).
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';

$descriptorSubTaskId = isset($_REQUEST['descriptorSubTaskId']) ? intval($_REQUEST['descriptorSubTaskId']) : 0;

$db = DB::getInstance();

$query = " delete  ";
$query .= " from  " . DB__NEW_DATABASE . ".descriptorSubTask  ";
$query .= " where descriptorSubTaskId = " . intval($descriptorSubTaskId) . " ";

$db->query($query); // >>>00002 ignores failure on DB query!

$data['status'] = 'success';
header('Content-Type: application/json');
echo json_encode($data);

die();
?>