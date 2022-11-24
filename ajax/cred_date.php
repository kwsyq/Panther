<?php
/*  ajax/cred_date.php

    INPUT $_REQUEST['id']: creditRecordId, primary key in DB table CreditRecord
    INPUT $_REQUEST['value']: date in 'Y-m-d' form, e.g. '2019-05-30'. 

    Update date for a credit record. 
    
    Requires admin-level rights for payments.
    
    Returns JSON for an associative array with only the one member:
         * 'status': "success" on successful query (which, among other things, guarantees that the credit record did exist), 'fail' otherwise.
    
    JM did various cleanup 2020-03-23 for v2020-3; it didn't seem worth keeping track changes within the file, because some of this was structural.
    
    >>>00002 might be good to have this return an error message as well; study the context from which it is called.
*/    

include '../inc/config.php';
include '../inc/perms.php';

$data = array();
$data['status'] = 'fail';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    $logger->error2('1584981600', "Attempted access by someone who lacks Admin-level payments permission: userId" . 
        ($user ? ('='.$user->getUserId()) : ' unavailable'));  
    echo json_encode($data);
    die();
}

// >>>00016 >>>00002: needs input validation

$creditRecordId = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
$value = trim($value);

$time = date('Y-m-d', strtotime($value));

$db = DB::getInstance();

if (intval($creditRecordId)) {    
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " creditDate = '" . $db->real_escape_string($time) . "' ";
    $query .= " where creditRecordId = " . intval($creditRecordId) . " ";
    $result = $db->query($query);
    
    if ($result) {    
        $data['status'] = 'success';
    } else {
        $logger->errorDb('1584981635', "", $db); 
    }
}

header('Content-Type: application/json');

echo json_encode($data);

die();
?>
