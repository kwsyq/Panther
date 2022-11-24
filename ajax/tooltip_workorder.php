<?php
/*  ajax/tooltip_workorder.php

    INPUT $_REQUEST['workOrderId']: primary key to DB table WorkOrder

    Returns JSON for an associative array with the following members:
        * 'workOrderId': as input
        * 'name': name of workOrder
        * 'jobname': name of corresponding job 
*/    

include '../inc/config.php';
include '../inc/access.php';

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$workOrder = new WorkOrder($workOrderId, $user);
$job = new Job($workOrder->getJobId());

$data = array();
$data['workOrderId'] = $workOrder->getWorkOrderId();
$data['name'] = $workOrder->getName();
$data['jobname'] = $job->getName();

header('Content-Type: application/json');
echo json_encode($data);
die();

?>