<?php
/*  _admin/ajax/deleteadmin.php

    INPUT $_REQUEST['username'] - username of admin to delete

    EXECUTIVE SUMMARY: Delete an administrator (remove their admin rights).
    >>>00004 as noted in inc/classes/Administrator.class.php, our current 
    approach to this is provisional, and is limited to a single customer (currently SSS itself) being on the system.
        
    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'. 
        On fail we also return:
            * 'error': error message(s)
*/

require_once '../../inc/config.php';

syslog(LOG_INFO, 'In _admin/ajax/deleteadmin.php');

$ok = true;
$data = array();
$data['status'] = 'fail';
$error = '';

function addError($err) {
    global $error;
    if ($error) {
        $error .= '; ';
    } else {
        $error .= 'addadmin: ';
    }
    $error .= $err;
}

$username = array_key_exists('username', $_REQUEST) ? trim($_REQUEST['username']) : '';

if ( strpos($username, ' ') !== false ) {
    $ok = false;
    addError('admin name cannot contain a space');
}
if ( strpos($username, '*') !== false ) {
    $ok = false;
    addError('admin name cannot contain an asterisk');
}

if ($ok) {
    syslog(LOG_INFO, 'Calling Administrator::removeAdmin("' . $username . '", $customer)');  // >>> DEBUG
    if (Administrator::removeAdmin($username, $customer)) {
        syslog(LOG_INFO, 'Administrator::removeAdmin success'); // >>> DEBUG
        $data['status'] = 'success';      
    } else {
        syslog(LOG_INFO, 'Administrator::removeAdmin fail'); // >>> DEBUG
        addError("Administrator::removeAdmin cannot remove \"$username\"");
        $ok = false;
    }
}

if ( !$ok ) {
    $data['error'] = $error;
    // >>>00002: should log $error  
    
    syslog(LOG_INFO, '$error'); // >>> DEBUG
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
