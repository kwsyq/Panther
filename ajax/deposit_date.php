<?php
/*  ajax/deposit_date.php

    INPUT $_REQUEST['id']: creditRecordId, primary key in DB table CreditRecord 
    INPUT $_REQUEST['value']: date, form of value is somewhat flexible, we'll interpret 
        it with PHP function strtotime, then format it as 'Y-m-d' to pass to the SQL.  

    Requires admin-level Payment permission, otherwise dies. 
    
    ACTION: In DB table creditRecord, for the row with creditRecordId=id, set depositDate=value.
   
    Returns JSON for an associative array with the following members:    
        * status: "success" if INPUT $_REQUEST['id'] is a nonzero integer, "fail" otherwise.

    JM did various cleanup 2020-03-23 for v2020-3; it didn't seem worth keeping track changes within the file, because some of this was structural.
    
    >>>00002 might be good to have this return an error message as well; study the context from which it is called.
*/    

include '../inc/config.php';
include '../inc/perms.php';

$data = array();
$data['status'] = 'fail';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm){
    $logger->error2('1584983323', "Attempted access by someone who lacks Admin-level payments permission: userId" . 
        ($user ? ('='.$user->getUserId()) : ' unavailable'));  
    echo json_encode($data);
    die();
}

// >>>00016, >>>00002 JM: needs validation of inputs.
$creditRecordId = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
$value = trim($value);
$time = date('Y-m-d',strtotime($value));

$db = DB::getInstance();
if (intval($creditRecordId)) {    
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " depositDate = '" . $db->real_escape_string($time) . "' ";
    $query .= " where creditRecordId = " . intval($creditRecordId) . " ";
    $result = $db->query($query); // ADDED 2020-08-27 JM to address http://bt.dev2.ssseng.com/view.php?id=236. This was obviously never tested at all
                                  // before v2020-3 release, since without this line this is basically a no-op. 
    if ($result) {    
        $data['status'] = 'success';
    } else {
        $logger->errorDb('1584983320', "", $db); 
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>
