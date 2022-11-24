<?php
/*  _admin/ajax/addadmin.php

    Approach 1: 
    INPUT $_REQUEST['username'] - username of new (or existing) admin
    INPUT $_REQUEST['password'] - password for that admin
    
    Approach 2:
    INPUT $_REQUEST['lineForHTPasswords'] - line to add to Apache passwords file: includes both username of new (or existing) admin and encrypted password.
    Should be the output of encryptpassword.php or equivalent.
    INPUT $_REQUEST['username'] - username of that admin (optional)
    
    The two 'password' and 'lineForHTPasswords' approaches are mutually exclusive; for the latter, username is optional, 
    but if present will be checked against 'lineForHTPasswords' 

    EXECUTIVE SUMMARY: Adds an administrator, or modifies their password.
    >>>00004 as noted in inc/classes/Administrator.class.php, our current 
    approach to this is provisional, and is limited to a single customer (currently SSS itself) being on the system.
        
    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'. 
        On fail we also return:
            * 'error': error message(s)
*/

require_once '../../inc/config.php';

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
$password = array_key_exists('password', $_REQUEST) ? trim($_REQUEST['password']) : '';
$lineForHTPasswords = array_key_exists('lineForHTPasswords', $_REQUEST) ? trim($_REQUEST['lineForHTPasswords']) : '';

if ( $password ) {
    if ( $lineForHTPasswords ) {
        $ok = false;
        addError('cannot have both password and lineForHTPasswords');
    } 
    if ( ! $username ) {
        $ok = false;
        addError('password present, but missing username');
    }
} else if ( ! $lineForHTPasswords ) {
    $ok = false;
    addError('must have either password or lineForHTPasswords');
} else {  
    // make sure $lineForHTPasswords has correct form
    $colonAt = strpos($lineForHTPasswords, ':');
    if ($colonAt === false) {
        $ok = false;
        addError('no colon in lineForHTPasswords');
    } else if ($colonAt === 0) {
        $ok = false;
        addError('colon cannot be first character in lineForHTPasswords');
    } else if ($colonAt === strlen($lineForHTPasswords) -1) {
        $ok = false;
        addError('lineForHTPasswords lacks an encrypted password');
    } else if ($username) {
        // make sure $username matches initial portion of $lineForHTPasswords
        if ($username != substr($lineForHTPasswords, 0, $colonAt)) {
            $ok = false;
            addError('provided username does not match lineForHTPasswords');
        }
    }
    // >>>00006 maybe more testing?
}

if ( strpos($username, ' ') !== false ) {
    $ok = false;
    addError('admin name cannot contain a space');
}

if ($ok) {
    if ($lineForHTPasswords) {
        if (Administrator::addAdminUsingEncryptedPassword($lineForHTPasswords, $customer)) {
            $data['status'] = 'success';      
        } else {
            addError("Administrator::addAdmin cannot add \"$username\"");
            $ok = false;
        }
    } else {
        if (Administrator::addAdmin($username, $password, $customer)) {
            $data['status'] = 'success';      
        } else {
            addError("Administrator::addAdmin cannot add \"$username\"");
            $ok = false;        
        }
    }
}

if ( !$ok ) {
    $data['error'] = $error;
    // >>>00002: should log $error. Replace the following syslog call with proper logging
    syslog(LOG_INFO, '$error'); // >>> DEBUG
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
