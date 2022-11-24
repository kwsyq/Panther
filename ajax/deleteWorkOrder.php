<?php 

/*  ajax/deleteWorkOrder.php

    INPUTS:
        $_REQUEST['workOrderId']) : primary key in DB table workOrder
        
        Effect: Soft Delete WorkOrder 
        More precisely: 
            
		Returns fail + message or success
        
        Acts only if workOrderId is valid (row exists in workOrder table).
*/

include '../inc/config.php';
include '../inc/access.php';

$v=new Validator2( $_REQUEST);

$v->rule('required', 'workOrderId'); 
$v->rule('integer', 'workOrderId'); 
$v->rule('min', 'workOrderId', 1); 

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

if(!$v->validate()){
    $logger->error2('1574366651', "Error input parameters ".json_encode($v->errors()));

	header('Content-Type: application/json');
	$data['status']='fail'; // [CP] not necessary but in order to be more readable (status is assigned on row 26)
    $data['error']=$v->getErrorJson(); 
    echo json_encode($data);

    exit;
}

$db = DB::getInstance();

$workOrderId = intval($_REQUEST['workOrderId']);

if (WorkOrder::validate($workOrderId)) {
    $workOrderDetailId = 0;
    $query = "delete from workOrder where workOrderId=".$workOrderId;
    if (($result = $db->query($query))===false) {
        $logger->errorDb('1574362814', "Hard error", $db);
		header('Content-Type: application/json');
		$data['status']='fail'; // [CP] not necessary but in order to be more readable (status is assigned on row 26)
	    $data['error']="Hard DB error! More info in the application Log"; 
	    echo json_encode($data);
	    exit;        
    } else {
		header('Content-Type: application/json');
		$data['status']='success'; 
	    $data['error']=""; 
	    echo json_encode($data);
	    exit;        
    }
} else {
    $logger->info2('1574362737', "workOrderId $workOrderId does not exist");
	header('Content-Type: application/json');
	$data['status']='fail'; // [CP] not necessary but in order to be more readable (status is assigned on row 26)
    $data['error']="workOrderId $workOrderId does not exist"; 
    echo json_encode($data);
    exit;
}
exit; // [CP] Not necessary. Is impossible(?) to arrive and run this row. But I consider every script must finish with an exit
?>