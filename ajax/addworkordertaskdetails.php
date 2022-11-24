<?php 
/*  ajax/addworkordertaskdetails.php

    INPUTS:
        $_REQUEST['workOrderId'] : primary key in DB table WorkOrder
        
    Drill down successively through workorderTask, task, taskDetails, and then for each detail, 
    if we don't already have a row in table workOrderDetail for (workOrderId, detailRevisionId), 
    insert it using personId from current user. 
    
    No explicit return.

    Acts only if workOrderId is valid. 
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (existWorkOrderId($workOrderId)) {
	$wo = new WorkOrder($workOrderId);
	
	if (intval($wo->getWorkOrderId())) {
		$workordertasks = $wo->getWorkOrderTasksRaw();
		foreach ($workordertasks as $wot) {
			$wott = $wot->getTask();
			$task = new Task($wott->getTaskId());
			$details = $task->getTaskDetails();
			
			foreach ($details as $detail) {
				$exists = false;
				$query = "SELECT workOrderDetailId FROM " . DB__NEW_DATABASE . ".workOrderDetail " . // before v2020-3, was SELECT * 
				         "WHERE workOrderId = " . intval($workOrderId) . " ". 
				         "AND detailRevisionId = " . intval($detail['detailRevisionId']) . ";";
			
				$result = $db->query($query);
				if ($result) {
					if ($result->num_rows > 0) {
						$exists = true;
					}
				} else {
				    $logger->errorDb('1594157082', "Hard DB error", $db); 				        
				}
			
				if (!$exists) {
					$query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderDetail (workOrderId, detailRevisionId, personId) VALUES (";
					$query .= intval($workOrderId);
					$query .= ", " . intval($detail['detailRevisionId']);
					$query .= ", " . intval($user->getUserId());
					$query .= ");";
			
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1594157109', "Hard DB error", $db);
                    }
				}
			}
		}
	}
} // END if (existWorkOrderId($workOrderId))


/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (existTaskId($taskId)){
	if (existWorkOrderId($workOrderId)){
		$task = new Task($taskId);
		
		$details = $task->getTaskDetails();
	
		foreach ($details as $dkey => $detail){
			print_r($detail);
			$exists = false;
			
			$query = " select * from " . DB__NEW_DATABASE . ".workOrderDetail where workOrderId = " . intval($workOrderId) . " and detailRevisionId = " . intval($detail['detailRevisionId']);
			
			if ($result = $db->query($query)) {
				if ($result->num_rows > 0){
					$exists = true;
				}
			}
	
			if (!$exists){			
					$query = "insert into " . DB__NEW_DATABASE . ".workOrderDetail (workOrderId, detailRevisionId, personId) values (";
					$query .= " " . intval($workOrderId) . " ";
					$query .= " ," . intval($detail['detailRevisionId']) . " ";
					$query .= " ," . intval($user->getUserId()) . " ";
					$query .= ") ";
			
					$db->query($query);
			}
		}
	}
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

?>