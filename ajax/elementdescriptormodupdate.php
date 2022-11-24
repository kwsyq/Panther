<?php
/*  ajax/elementdescriptormodupdate.php

    INPUT $_REQUEST['id']: elementDescriptorId, primary key in DB table ElementDescriptor 
    INPUT $_REQUEST['value']: new 'modifier' value, which overwrites the old; anything past 32 characters is ignored.   
    
    Updates row in table elementDescriptor  to set modifier to specified value. 
    Uses an UPDATE, so that will do nothing if id isn't valid. 
    Then does a SELECT to make sure it succeeded.
    
    Returns JSON for an associative array with only the following member:    
        * status: "success" on success; "fail" if for any reason value is not set in the DB. 
*/    

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';

$value = trim($value);
$value = substr($value, 0, 32); // >>>00002 truncation should log.

$db = DB::getInstance();			

$query = "UPDATE " . DB__NEW_DATABASE . ".elementDescriptor SET ";
$query .= "modifier = '" . $db->real_escape_string($value) .  "' ";
$query .= "WHERE elementDescriptorId = " . intval($id) . ";";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1594157237', "Hard DB error", $db);
} 

$query = "SELECT modifier FROM " . DB__NEW_DATABASE . ".elementDescriptor "; // before v2020-3, was just SELECT *
$query .= "WHERE elementDescriptorId = " . intval($id) . ";";

$result = $db->query($query);
if ($result) {
	if ($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		if ($value == $row['modifier']) {
			$data['status'] = 'success';
		}			
	}		
} else  {
    $logger->errorDb('1594157299', "Hard DB error", $db);
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>