<?php
/*  ajax/cred_type.php

    INPUT $_REQUEST['id']: creditRecordId, primary key in DB table CreditRecord
    INPUT $_REQUEST['value']: creditRecordTypeId (primary key to DB table creditRecordType).  
        
    Update creditRecordTypeId for a credit record.
    
    Requires admin-level rights for payments. 

    Returns JSON for an associative array with only the one member:
        * status: "success" on successful insert (but see caveats in other comments about the limits of what this guarantees); 'fail' otherwise.
        
    JM did various cleanup 2020-03-23 for v2020-3; it didn't seem worth keeping track changes within the file, because some of this was structural.
    
    >>>00002 might be good to have this return an error message as well; study the context from which it is called.
*/

include '../inc/config.php';
include '../inc/perms.php';

$data = array();
$data['status'] = 'fail';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm){
    $logger->error2('1584983023', "Attempted access by someone who lacks Admin-level payments permission: userId" . 
        ($user ? ('='.$user->getUserId()) : ' unavailable'));  
    echo json_encode($data);
    die();
}

/* BEGIN REPLACED 2020-08-27 JM to address http://bt.dev2.ssseng.com/view.php?id=236
$creditRecordId = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$creditRecordTypeId = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
$creditRecordTypeId = intval($value);

$db = DB::getInstance();

if (intval($creditRecordId)) { 
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " creditRecordTypeId = " . intval($creditRecordTypeId) . " ";
    $query .= " where creditRecordId = " . intval($creditRecordId) . " ";
    $result = $db->query($query);
// END REPLACED 2020-08-27 JM
*/
// BEGIN REPLACEMENT 2020-08-27 JM
// >>>00016 >>>00002: needs input validation. Right now, bad inputs could easily violate referential integrity.
$creditRecordId = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : '');
$creditRecordTypeId = intval(isset($_REQUEST['value']) ? $_REQUEST['value'] : '');

$db = DB::getInstance();

// We allow setting $creditRecordTypeId == 0. That's OK, means "unknown".
if ($creditRecordId) {
    $query = "UPDATE " . DB__NEW_DATABASE . ".creditRecord SET ";
    $query .= "creditRecordTypeId = " . $creditRecordTypeId . " ";
    $query .= "WHERE creditRecordId = " . $creditRecordId . ";";
    $result = $db->query($query);
// END REPLACEMENT 2020-08-27 JM
    
    if ($result) {    
        $data['status'] = 'success';
    } else {
        $logger->errorDb('1584983220', "", $db); 
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>