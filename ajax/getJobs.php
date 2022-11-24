<?php 
    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();


    $query = "call GetInfosJob()";

    $res=$db->query($query);

    while($row=$res->fetch_assoc()){
    	$data['detail'][]=$row;
    }
    $data['status'] = 'success';

	header('Content-Type: application/json');
	echo json_encode($data);
	die();

 ?>