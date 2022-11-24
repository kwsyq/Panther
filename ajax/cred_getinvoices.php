<?php
/*  ajax/cred_getinvoices.php

    INPUT $_REQUEST['id']: creditRecordId, primary key in DB table CreditRecord

    Get all invoices for a credit record. 
    
    Requires admin-level rights for payments.
    
    Returns JSON for an associative array with the following members:    
        * 'status': "success" on successful update (which actually does not guarantee that the credit record exists), 'fail' otherwise.
        * 'data': empty array on failure; For success, this will be an array of associative arrays. 
                  The top-level array is in no particular order; each associative array is the canonical representation 
                  of a row from DB table invoicePayment, where the creditRecordId matches the input id. 
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

$rows = array();

$db = DB::getInstance();

/* BEGIN REPLACED 2020-02-03 JM
$query = " select * from " . DB__NEW_DATABASE . ".creditRecordInvoice ";
// END REPLACED 2020-02-03 JM
*/
// BEGIN REPLACEMENT 2020-02-03 JM
//  (For version 2020-02, invoicePayment completely supersedes creditRecordInvoice instead of having
//  duplicated information.)
$query = " select * from " . DB__NEW_DATABASE . ".invoicePayment ";
// END REPLACEMENT 2020-02-03 JM
$query .= " where creditRecordId = " . intval($id);
if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row['invoiceId'];
        }
    }
} // >>>00002 ignores failure on DB query!

if (count($rows)) {
    $data['status'] = 'success';
}

$data['rows'] = $rows;

header('Content-Type: application/json');

echo json_encode($data);
die();
?>