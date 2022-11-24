<?php 
$_SESSION['username']='rskinner@ssseng.com';

require_once '../inc/config.php';
//require_once '../inc/access.php';

$req=$_SERVER['REQUEST_METHOD'];

    $db = DB::getInstance();

switch($req){
	case 'GET':
	    	$query = " select taskTypeId, typeName from " . DB__NEW_DATABASE . ".taskType order by displayOrder";
		    $result = $db->query($query);
    		$rows=[];
		    while($row=$result->fetch_assoc()){
		        $rows[]=$row;
		    };
		    header('Content-Type: application/json');
		    echo json_encode($rows);
			die();
		break;
	}

?>