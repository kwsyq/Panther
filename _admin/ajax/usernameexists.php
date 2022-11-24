<?php
/*  _admin/ajax/usernameexists.php

    INPUT $_REQUEST['username'] - username of new (or existing) admin

    EXECUTIVE SUMMARY: Determines whether a username refers to a current user. 
        
    Returns JSON for an associative array with the following members:
        * 'exists': 'true' or 'false'        
*/

require_once '../../inc/config.php';

$ok = true;
$data = array();
$error = '';

$username = array_key_exists('username', $_REQUEST) ? trim($_REQUEST['username']) : '';

$data['exists'] = !!User::getByUsername($username, $customer) ? 'true' : 'false';

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
