<?php

include "../inc/config.php";


$conn=new mysqli(DB__HOST, DB__USER, DB__PASS);
$conn->select_db(DB__NEW_DATABASE);

$res=$conn->query("select * from integrityResult where transferred=0");

$output=array();
$lastid=0;
while($row=$res->fetch_assoc()){
	$output[]=$row;
	if($row['id']>$lastid){
		$lastid=$row['id'];
	}
}

$conn->query("update integrityResult set transferred=1 where id<=".$lastid);


header('Content-Type: application/json');
echo (json_encode($output));

exit;


?>