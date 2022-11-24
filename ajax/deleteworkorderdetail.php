<?php
/*  ajax/deleteworkorderdetail.php

    INPUT $_REQUEST['workOrderId']: primary key in DB table WorkOrder
    INPUT $_REQUEST['detailRevisionId']: key in Details API

    Delete any matching row from table WorkOrderDetail. No explicit return. 
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;

if (existWorkOrderId($workOrderId)) {	
	$query = "update " . DB__NEW_DATABASE . ".workOrderDetail set hidden = 1 ";
	$query .= " where workOrderId = " . intval($workOrderId) . " ";
	$query .= "  and detailRevisionId = " . intval($detailRevisionId) . " ";	
	$db->query($query); // >>>00002 ignores failure on DB query
} // >>> else should log invalid taskId
?>