<?php
/*  ajax/invoicefileadd.php

    INPUT $_REQUEST['invoiceId']: primary key in DB table Invoice
    INPUT $_REQUEST['fileName']: This should be just a fileName with extension, no path

    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      * 'invoiceFileId': used only if status='success', returns the new fileId
      * 'alreadyExisted': 'true' or 'false'. used only if status='success', boolean

    Requires Admin-level invoice permissions.

    NOTE: This is about adding a fileName to an invoice; 
    the file itself need not exist anywhere.
*/

/*
// BEGIN MARTIN COMMENT
create table invoiceFile(
    invoiceFileId     int unsigned not null primary key auto_increment,
    invoiceId         int unsigned,
    fileName          varchar(255),
    personId          int unsigned,
    inserted          timestamp not null default now()
);

create index ix_invfile_invid on invoiceFile(invoiceId);
// END MARTIN COMMENT

Heavily rewritten JM 2020-05-27
*/

include '../inc/config.php';
include '../inc/perms.php';

$data = array(); 
$data['status']='fail';
$data['error'] = '';
$data['invoiceFileId'] = '';
$data['alreadyExisted'] = 'false';

// INPUT $invoiceId: The invoice in question
// INPUT $fileName: The fileName to attacy
// INPUT $user: personId of current logged-in user
// RETURNs invoiceFileId of inserted row, or 0 for failure.
// Note that on false return, this can have the side effet of setting $data['error']
function makeEntry($invoiceId, $fileName, $user) {
    global $logger;
    global $data;
    
    $db = DB::getInstance();
    if ($db) {        
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceFile(invoiceId, fileName, personId) VALUES (";
        $query .= intval($invoiceId) . ", ";
        $query .= "'" . $db->real_escape_string($fileName) . "', ";    
        $query .= intval($user->getUserId()) . ");";    
        $result = $db->query($query); 
        
        if ($result) {
            $id = $db->insert_id;
            
            if (intval($id)) {
                return $id;
            }
        } else {
            $data['error'] = "Insert failed";
            $logger->errorDb('1590588952', $data['error'], $db);
        }
    } else {
        $data['error'] = "Could not open DB";
        $logger->error2('1590588953', $data['error']);
    }
        
    return 0;
}

// INPUT $invoiceId: The invoice in question
// INPUT $fileName: The fileName to attacy
// RETURNs true if filename already exists, false otherwise
function entryExists($invoiceId, $fileName) {
    global $logger;
    global $data;
    
    $db = DB::getInstance();
    if ($db) {
        $query = "SELECT invoiceFileId FROM " . DB__NEW_DATABASE . ".invoiceFile ";
        $query .= "WHERE invoiceId = " . intval($invoiceId) . " ";
        $query .= "AND fileName = '" . $db->real_escape_string($fileName) . "';";
        
        $result = $db->query($query); 
        
        if ($result) {
            return $result->num_rows != 0;
        } else {
            $data['error'] = "SELECT failed";
            $logger->errorDb('1590592727', $data['error'], $db);
            return false;
        }
    }
    $data['error'] = "Could not open DB";
    $logger->error2('1590592729', $data['error']);
    return false;
}

$checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    $errorId='1590586282';
    $data['error'] = "ajax/invoicefileadd.php: user lacks invoice admin permission";
    $logger->error2($errorId, $data['error']);
}

if (!$data['error']) {
    $v=new Validator2($_REQUEST);
    list($error, $errorId) = $v->init_validation();
    if($error){
        $logger->error2('1590586260', "Error(s) found in init validation: [".json_encode($v->errors())."]");
        header('Content-Type: application/json');
        echo $v->getErrorJson();
        exit();
    }
    $v->rule('required', ['invoiceId', 'fileName']);
    $v->rule('integer', ['invoiceId']);
    $v->rule('min', ['invoiceId'], 1);
    
    if(!$v->validate()){
        $logger->error2('1590586273', "Error in input parameters ".json_encode($v->errors()));
        header('Content-Type: application/json');
        echo $v->getErrorJson();
        exit();
    }
    
    $invoiceId = intval($_REQUEST['invoiceId']);
    $fileName = trim($_REQUEST['fileName']);
    
    if (!Invoice::validate($invoiceId)) {
        $errorId='1590586288';
        $data['error'] = "ajax/invoicefileadd.php called with invalid invoiceId $invoiceId";
        $logger->error2($errorId, $data['error']);
    }
}

if (!$data['error']) {
    // We don't care about files size or allowed extensions, since we are not really uploading.
    $fileNameLength = strlen($fileName); 
    if ( $fileNameLength > 255 ) {
        $errorId='1590586299';
        $data['error'] = "ajax/invoicefileadd.php for invoice $invoiceId, filename too long, max length is 255, was $fileNameLength: '" . substr($fileName, 0, 255). "...'"; 
        $logger->error2($errorId, $data['error']);
    }
}

if (!$data['error']) {
    if (entryExists($invoiceId, $fileName)) { // Note that on false return, this can have the side effet of setting $data['error']
        $data['alreadyExisted'] = 'true';
    } else {
        $id = makeEntry($invoiceId, $fileName, $user);
        if (intval($id)) {
            $data['status']='success';
            $data['invoiceFileId'] = intval($id); 
            $logger->info2('1590586413', "ajax/invoicefileadd.php inserted filename '$fileName' for invoice $invoiceId");
        } else {
            // error already logged, but we need to report back to the user.
            $data['error'] = "ajax/invoicefileadd.php for invoice $invoiceId, filename '$fileName': insert failed";
        }
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>