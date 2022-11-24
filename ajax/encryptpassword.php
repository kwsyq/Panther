<?php
/*  ajax/encryptpassword.php

    EXECUTIVE SUMMARY: 
    Encrypt a password for use in the apache passwords file.
    For security purposes, this should be called with POST method, so its inputs are not logged.
    Does not validate its inputs, other than to make sure password is at least 8 characters; the rest of that
     is a client-side responsibility.
                                                                 f
    INPUTS: $_REQUEST['username']
    INPUTS: $_REQUEST['password']
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'. 
        On fail we also return:
            * 'error': error message(s)
        On success we also return:
            * 'forPasswordFile', the line to add to /var/www/.htpasswords
*/
require_once '../inc/config.php';

$ok = true;
$data = array();
$data['status'] = 'fail';
$error = '';

function addError($err) {
    global $error;
    if ($error) {
        $error .= '; ';
    }
    $error .= $err;
}

if ( ! array_key_exists('username', $_REQUEST) ) {
    $ok = false;
    addError('missing username');
}

if ( ! array_key_exists('password', $_REQUEST) ) {
    $ok = false;
    addError('missing password');
}

if ($ok) {
    $username = trim($_REQUEST['username']);
    $password = trim($_REQUEST['password']);
    if (strlen($password) < 8) {
        $ok = false;
        addError('Password must be at least 8 characters');
    }
}

if ($ok) {
    $forPasswordFile = shell_exec("htpasswd -nb -B '$username' '$password'");
    if ($forPasswordFile) {
        $data['status'] = 'success';
        $data['forPasswordFile'] = trim($forPasswordFile);
    } else {
        $ok = false;
        addError('No content back from htpasswd');
    }
}

if ( !$ok ) {
    $data['error'] = $error;
    // >>>00002: should log $error
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>