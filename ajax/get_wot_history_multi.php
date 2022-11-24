<?php
/*  ajax/get_wot_history_multi.php

    Usage: on contract page.

    INPUT $_REQUEST['taskId']
   
    Get the last 10 matching rows from table workOrderTask where $_REQUEST['taskId'] match the workOrderTask.taskId. 
        * 'taskId' 
        * 'cost' 
        * 'quantity' 
        * 'taskTypeId' // if null we set default to 1
        * 'typeName' // if null we set default to 'overhead'
        * 'inserted' // date
        * 'workOrderTaskId' 
    Join with workOrderTaskTime table to get the time (minutes).
    Get the workOrderId from table workOrderTask.
    Get the jobId from table workOrder for the specific workOrderId. And create object to get Number and buildLink();
    Get the clientMultiplier from table contract for the specific workOrderId.
    Create on this file the WorkOrder Multiplier ( )
    Return all data for a specific if.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0; // get taskId


if (intval($taskId)) {
    $allWot = []; // new array of all WOT

    $query = "SELECT wo.taskId, wo.cost, wo.quantity, wo.taskTypeId, tTy.typeName, wo.inserted, woTt.minutes, wo.workOrderTaskId ";
    $query .= " FROM " . DB__NEW_DATABASE . ".workOrderTask wo ";
    $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskTime woTt on woTt.workOrderTaskId = wo.workOrderTaskId ";
    $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".taskType tTy on wo.taskTypeId = tTy.taskTypeId ";
    $query .= " WHERE wo.taskId = " . intval($taskId ) . " ";
    $query .= " AND wo.cost > 0 ";
    $query .= " AND wo.quantity > 0";
    $query .= " Order By inserted DESC LIMIT 10";
    $result = $db->query($query);
    if (!$result) {
        $error = "We could not get the task details. Database Error";
        $logger->errorDb('637744792475685999', $error, $db);
        $data['error'] = "ajax/get_wot_history_multi.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {
            
        while ($row = $result->fetch_assoc()) {
            $allWot[] = $row;
        }
    }

    foreach($allWot as $woT) {
        $final = 0;
        $revenue = 0;
        $revenueTotal = 0;
        $woTimeWo = 0;
        $woTime = 0;
        $woCost = 0; // time WO
        $clientMultiplier = 1;

        $woTime = intval($woT["minutes"]/60*100)/100; // per WoT

        if($woT["quantity"] != 0 && $woT["cost"] != 0) {
            $woTimeWo += $woTime;
        }
        
        // workOrderId
        $query = "SELECT  workOrderId FROM  " . DB__NEW_DATABASE . ".workOrderTask ";
        $query .= " WHERE workOrderTaskId = " . intval($woT['workOrderTaskId']) . ";";

        $result = $db->query($query);
        $row = $result->fetch_assoc();

        // jobNumber and jobLink
        $query = "SELECT jobId FROM  " . DB__NEW_DATABASE . ".workOrder ";
        $query .= " WHERE workOrderId = " . intval($row['workOrderId']) . ";";

        $result = $db->query($query);
        $jobId = $result->fetch_assoc()['jobId'];

        $job = new Job($jobId);
     
    

        // clientMultiplier
        $query = "SELECT  clientMultiplier FROM  " . DB__NEW_DATABASE . ".contract ";
        $query .= " WHERE workOrderId = " . intval($row['workOrderId']) . ";";

        $result = $db->query($query);
        $row = $result->fetch_assoc();

        if($row['clientMultiplier'] > 0) {
            $clientMultiplier = $row['clientMultiplier'];
        } else {
            $clientMultiplier = 1;
        }

        // WO Multiplier
        $revenue = ($woT["quantity"] * $woT["cost"] * $clientMultiplier);
        $woT['revenue'] = $revenue;
        $revenueTotal += $revenue;

        if( $revenueTotal > 0 &&  $woTimeWo > 0 ) {
            $final =  $revenueTotal /  $woTimeWo;
        } else {
            $final = 0;
        }

        $woT['finalMulti'] = $final;
        $woT['clientMultiplier'] = $clientMultiplier;
        $woT['number'] = $job->getNumber();
        $woT['linkJob'] = $job->buildLink();

        if($woT['taskTypeId'] == null ) {
            $woT['taskTypeId'] = '1';
            $woT['typeName'] = 'overhead';
        }

    
        // remove time from date
        $createDate = new DateTime($woT['inserted']);
        $stripInserted = $createDate->format('Y-m-d');
        $woT['inserted'] = $stripInserted;

      
        $data['task'][] = $woT;
    }




} 

if (!$data['error']) {
    $data['status'] = 'success';
}
header('Content-Type: application/json');
echo json_encode($data);
die();
?>