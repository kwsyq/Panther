<?php

include "inc/config.php";


$conn=new mysqli(DB__HOST, DB__USER, DB__PASS);
$conn->select_db(DB__NEW_DATABASE);

$res=$conn->query("select * from integrityResultData where tableName='".$_REQUEST['table']."' order by keycolumnValue+0");


$output=array();
$lastid=0;
while($row=$res->fetch_assoc()){
	$output[]=$row;
}

header('Content-Type: application/json');
echo (json_encode($output));

exit;


?>