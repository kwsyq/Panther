<?php
/* _admin/ajax/cred_getinvoices.php

    EXECUTIVE SUMMARY: Select invoiceIds from all rows from DB table invoicePayment where creditRecordId matches the input.
    
    INPUT $_REQUEST['id']: primary key creditRecordId in DB table CreditRecord.
        >>>00016 NOTE that we don't really validate this. Just won't find any invoices if it's bogus.
    
    Returns JSON for an associative array with the following members:    
        * 'status': 'success' on success, status='fail' otherwise. Success means at least one such invoice was found.
        * 'rows': on success, an array of invoiceIds from DB table invoicePayment. On failure, an empty array.

   >>>00037 Common code should be eliminated: this is extremely similar to ajax/getinvoices.php, they should share common code.
        
*/

include '../../inc/config.php';
include '../../inc/access.php';

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
        while ($row = $result->fetch_assoc()){
            $rows[] = $row['invoiceId'];
        }
    }
} // >>>00002 ignores failure on DB query!

if (count($rows)){
    $data['status'] = 'success';
}

$data['rows'] = $rows;

header('Content-Type: application/json');
echo json_encode($data);
die();

?>