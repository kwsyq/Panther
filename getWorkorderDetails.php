<?php


use Ahc\Jwt\JWT;
require './vendor/autoload.php';

$isDebug=1;

function base64url_decode($data) {
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

if($isDebug==1 && isset($_REQUEST['woid'])){
	$woid=$_REQUEST['woid'];
} else { // use token
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
	
	if(!isset($claims['wo'])){
		$retdata['status']="error";
		$retdata['message']="Token not valid!";
		header('Content-Type: application/json');
		echo json_encode($retdata);
		exit;	
	}
	$woid=$claims['wo'];	
}
/*
if(isset($_REQUEST['woid'])){
	$woid=$_REQUEST['woid'];
}
*/
require_once "../inc/config.php";

$db=DB::getInstance();


$output=[];

$output['Status'] = 'Workorder not exists'.$woid;
header('Content-Type: application/json');
echo json_encode($output);
die();