<?php
/*  _admin/ajax/permupdateidname.php

    EXECUTIVE SUMMARY: In DB table permission, set the permissionIdName (alternate key) for the specified row.

    INPUT $_REQUEST['id']: permissionId (primary key to DB table Permission)
    INPUT $_REQUEST['value']: new permissionIdName; must begin with "PERM_". 
        NOTE that these IDs correspond to values in inc/config.php and should not be changed lightly.
        
    >>>00026: surely there should be some sort of permissions check on something so fundamental.
    I (JM) realize we check it to get into the admin UI, but what's to stop someone from calling this file directly?
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' if the update succeeds, otherwise 'fail'.
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
$value = trim($value);
$value = substr($value, 0, 32); // >>>00002 truncates silently

$db = DB::getInstance();
			
$value = strtoupper($value);

if (substr($value, 0, 5) == "PERM_") {
	$query = "UPDATE " . DB__NEW_DATABASE . ".permission SET ";
	$query .= "permissionIdName = '" . $db->real_escape_string($value) .  "' ";
	$query .= "WHERE permissionId = " . intval($id) . ";";	
	$result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1594071862', 'Hard DB error', $db);
    }
		
	$query = "SELECT permissionIdName FROM " . DB__NEW_DATABASE . ".permission ";
	$query .= "WHERE permissionId = " . intval($id) . ";";	
	$result = $db->query($query);
	if ($result) {
		if ($result->num_rows > 0) {	
			$row = $result->fetch_assoc();	
			if ($value == $row['permissionIdName']) {	
				$data['status'] = 'success';	
			}
		}
	} else {
	    $logger->errorDb('1594071888', 'Hard DB error', $db);
	}
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>