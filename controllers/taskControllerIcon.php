<?php 

$_SESSION['username']='rskinner@ssseng.com';

require_once '../inc/config.php';
//require_once '../inc/access.php';

$errors= array();
$taskId=$_REQUEST['taskId'];
$file_name = $_FILES['files']['name'];
$file_size =$_FILES['files']['size'];
$file_tmp =$_FILES['files']['tmp_name'];
$file_type=$_FILES['files']['type'];

$a=explode('.',$_FILES['files']['name']);
$file_ext=strtolower(end($a));

$extensions= array("jpeg","jpg","png");

if(in_array($file_ext,$extensions)=== false){
 $errors[]="extension not allowed, please choose a JPEG or PNG file.";
}

if($file_size > 12097152){
 $errors[]='File size must be excately 12 MB';
}

if(empty($errors)==true){
	move_uploaded_file($file_tmp,"/var/www/ssseng_documents/icons_task/".$file_name);
	echo "Success";
}else{
	print_r($errors);
}

if(count($errors)==0){
	$db->query("update task set icon='".$db->real_escape_string($file_name)."' where taskId=$taskId");

	$row=$db->query("select t.taskId, t.groupName, t.description, t.icon, t.billingDescription, t.taskTypeId, tt.typeName, if(active=1, 'true', 'false') as active from " . DB__NEW_DATABASE . ".task t inner join taskType tt on t.taskTypeId=tt.taskTypeId where t.taskid=$taskId")->fetch_assoc();

	die();
}


die();

 ?>