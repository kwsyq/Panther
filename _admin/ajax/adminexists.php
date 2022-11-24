<?php
/*  _admin/ajax/adminexists.php

    INPUT $_REQUEST['username'] - username of new (or existing) admin

    EXECUTIVE SUMMARY: Determines whether a username refers to a current administrator 
    >>>00004 as noted in inc/classes/Administrator.class.php, our current 
    approach to this is provisional, and is limited to a single customer (currently SSS itself) being on the system.
        
    Returns JSON for an associative array with the following members:
        * 'exists': 'true' or 'false'        
*/

require_once '../../inc/config.php';

$ok = true;
$data = array();
$error = '';

$username = array_key_exists('username', $_REQUEST) ? trim($_REQUEST['username']) : '';

if ( strpos($username, ' ') !== false ) {
    // admin name cannot contain a space
    $data['exists'] = 'false';
} else {
    $data['exists'] = Administrator::isAdmin($username, $customer) ? 'true' : 'false';
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
