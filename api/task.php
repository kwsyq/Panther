<?php
	include '../inc/config.php';
	$db = DB::getInstance();

	$act='';
	if(!isset($_REQUEST['act'])){
		$act='list';
	} else {
		$act=$_REQUEST['act'];
	}
	
	$output=array();

	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $act === 'list' ) {
		$data = $db->query("select taskId, icon, description, billingDescription, taskTypeId, groupName from task")->fetch_all(MYSQLI_ASSOC);

	}
	$output['data']=$data;

	header('Content-type: application/json');
	echo json_encode( $output );	
?>




