<?php
/*  ajax/cred_refnum.php

    INPUT $_REQUEST['id']: creditRecordId, primary key in DB table CreditRecord
    INPUT $_REQUEST['value']: new 'referenceNumber' value (e.g. a check number or PayPal transaction number); 
        string, anything past 64 characters is ignored.  
        
    Requires admin-level rights for payments. 

    Returns JSON for an associative array with only the one member:
        * 'status': "success" on successful query (which, among other things, guarantees that the credit record did exist), 'fail' otherwise. 
*/

include '../inc/config.php';
include '../inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    // >>>00002 should probably log attempted access by someone without permission
    // NOTE that this doesn't even return a 'fail' status, just dies.
    die();
}

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
$value = trim($value);
$value = substr($value, 0, 64); // >>>00002 truncation should log.

$db = DB::getInstance();

if (intval($id)) {    
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " referenceNumber = '" . $db->real_escape_string($value) . "' ";
    $query .= " where creditRecordId = " . intval(intval($id)) . " "; // >>>00006: I (JM) presume the double 'intval' is nothing special, just a minor coding glitch,
                                                                      // but we probably should clean it up to just 'intval'
    
    $db->query($query); // >>>00002 ignores failure on DB query, AND STILL CALLS THIS A SUCCESS!
    
    $data['status'] = 'success';    
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>
