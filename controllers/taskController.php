<?php 
//session_start();
$_SESSION['username']='rskinner@ssseng.com';

require_once '../inc/config.php';
//require_once '../inc/access.php';

$req=$_SERVER['REQUEST_METHOD'];

$db = DB::getInstance();

switch($req){
	case 'GET':
	    	$query = " select t.taskId, t.groupName, t.description, t.icon, t.billingDescription, t.taskTypeId, tt.typeName, if(active=1, 'true', 'false') as active, t.wikiLink from " . DB__NEW_DATABASE . ".task t left join taskType tt on t.taskTypeId=tt.taskTypeId";
		    $result = $db->query($query);
    		$rows=[];
		    while($row=$result->fetch_assoc()){
		    	$tmp['taskTypeId']=$row['taskTypeId'];
		    	$tmp['typeName']=$row['typeName'];
		    	$row['taskType']=$tmp;
		        $rows[]=$row;
		    };
		    header('Content-Type: application/json');
		    echo json_encode($rows);
			die();
		break;
	case 'POST':
			$outData=[];
			parse_str(file_get_contents('php://input'), $outData);
			$models=json_decode($outData['models']);
			$rows=[];
			foreach($models as $row){
				$query="insert into  task set 
					groupName='".$db->real_escape_string($row->groupName)."', 
					description='".$db->real_escape_string($row->description)."', 
					billingDescription='".$db->real_escape_string($row->billingDescription)."', 
					wikiLink='".$db->real_escape_string($row->wikiLink)."', 
					icon='".$db->real_escape_string($row->icon)."', 
					active='".($row->active?1:0)."', 
					taskTypeId='".$db->real_escape_string($row->taskType->taskTypeId)."'";
				$db->query($query);
				$taskId=$db->insert_id;
				$row->taskId=$taskId;
				$rows[]=$row;
			}
		    header('Content-Type: application/json');
		    echo json_encode($rows);
			die();
		break;
	case 'PUT':
			$outData=[];
			parse_str(file_get_contents('php://input'), $outData);
			$models=json_decode($outData['models']);
			$rows=[];
			foreach($models as $row){
				$query="update task set 
					groupName='".$db->real_escape_string($row->groupName)."', 
					description='".$db->real_escape_string($row->description)."', 
					billingDescription='".$db->real_escape_string($row->billingDescription)."', 
					wikiLink='".$db->real_escape_string($row->wikiLink)."', 
					icon='".$db->real_escape_string($row->icon)."', 
					active='".($row->active?1:0)."', 
					taskTypeId='".$db->real_escape_string($row->taskType->taskTypeId)."' 
					where taskId=".$row->taskId;
				$db->query($query);
				$rows[]=$row;
			}
		    header('Content-Type: application/json');
		    echo json_encode($rows);
			die();
		break;
	case 'DELETE':
		break;

}
echo $_SERVER['REQUEST_METHOD'];
die();






?>