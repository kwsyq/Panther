<?php
/*  ajax/deletetaskdetail.php

    INPUT $_REQUEST['taskId']: primary key in DB table Task
    INPUT $_REQUEST['detailRevisionId']: key in Details API

    Delete any matching row from table taskDetail. No explicit return. 
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;
$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;

if (existTaskId($taskId)) {	
	$query = "delete from " . DB__NEW_DATABASE . ".taskDetail ";
	$query .= " where taskId = " . intval($taskId) . " ";
	$query .= "  and detailRevisionId = " . intval($detailRevisionId) . " ";	
	$db->query($query); // >>>00002 ignores failure on DB query
} // >>> else should log invalid taskId
?>