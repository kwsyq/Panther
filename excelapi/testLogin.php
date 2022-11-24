<?php 
session_start();

use Ahc\Jwt\JWT;
require './vendor/autoload.php';





if(isset($_SESSION['username'])){
	echo "sessiune existenta!";
} else {
	$token=isset($_REQUEST['token'])?$_REQUEST['token']:"";
	//$token="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ3byI6MTA1NDF9.djmfUMjX6DYJlEF93lpIktNixBk13iVJKsXV4CzeOW4";
	$retdata=array();

	if($token==""){
		$retdata['status']="error";
		$retdata['message']="Token not present";
		header('Content-Type: application/json');
		echo json_encode($retdata);
		exit;
	}

	$passdecoded=base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU");

	$jwt = new JWT($passdecoded, 'HS256', 3600, 10);
	$claims=$jwt->decode($token);

	if(!isset($claims['username'])){
		$retdata['status']="error";
		$retdata['message']="Token not valid!";
		header('Content-Type: application/json');
		echo json_encode($retdata);
		exit;
	}
	$username=$claims['username'];
	$_SESSION['username']=$username;
}
header("Location: ".$_REQUEST['url']);
die();





?>