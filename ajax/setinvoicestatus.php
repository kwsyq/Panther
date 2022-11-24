<?php
/*  ajax/setinvoicestatus.php

    INPUT $_REQUEST['invoiceStatusId']
    INPUT $_REQUEST['invoiceId']
    // REMOVED in v2020-3: INPUT $_REQUEST['extra']:
    INPUT $_REQUEST['customerPersonIds']: OPTIONAL: can be a single customerPersonId or an array of customerPersonIds to associate
       with this invoiceStatus (basically, people to notify). If none, you can use anything false-y: 0, null, etc. // ADDED in v2020-3
    INPUT $_REQUEST['note']: note for the invoice

    For the invoice indicated by invoiceId, set status (etc.) indicated by invoiceStatusId, customerPersonIds, note.

    Returns JSON for an associative array with only the following member:
        * status: "success" if at the end of this the specified invoice has the specified invoiceStatus, 
          "fail" otherwise. NOTE that if this call to setinvoicestatus.php would not change the invoiceStatus, 
          but should overwrite the extra or note, any failure in the latter respect would still be considered 'success'.
          
    // >>>00002, >>>00016: we might want to do come checking at this level; Invoice class does a fairly good job of that, though.
*/    

include '../inc/config.php';
include '../inc/access.php';

/* REMOVED 2020-05-22 JM
ini_set('display_errors', 1);
error_reporting(-1);
*/

// BEGIN MOVED FROM BELOW 2020-05-22 JM
$ret = array();
$ret['status'] = 'fail';
// END MOVED FROM BELOW 2020-05-22 JM

$db = DB::getInstance();

$invoiceStatusId = isset($_REQUEST['invoiceStatusId']) ? intval($_REQUEST['invoiceStatusId']) : 0;
// $extras = isset($_REQUEST['extra']) ? $_REQUEST['extra'] : 0; // REMOVED 2020-05-22 JM

// BEGIN ADDED 2020-05-22 JM, further work 2020-05-26
$customerPersonIdsString = isset($_REQUEST['customerPersonIds']) ? trim($_REQUEST['customerPersonIds']) : '';
// Typically, this will be something like "customerPersonIds%5B%5D=4" or "customerPersonIds%5B%5D=4&customerPersonIds%5B%5D=31" or an empty string

$customerPersonIds = Array();
if (strlen($customerPersonIdsString)) {
    // >>>00001 I (JM) suspect there is a better way to handle this that I'm not aware of, but I'm brute-forcing this here:
    $customerPersonIdsString = str_replace('customerPersonIds%5B%5D=', '',  $customerPersonIdsString); 
    // It will be something like "4" or "4&31"
    $customerPersonIds = explode('&', $customerPersonIdsString);
}
// END ADDED 2020-05-22 JM, further work 2020-05-26

$note = isset($_REQUEST['note']) ? $_REQUEST['note'] : "";

$invoiceId = isset($_REQUEST['invoiceId']) ? $_REQUEST['invoiceId'] : 0;
$invoice = new Invoice($invoiceId, $user);	

/* BEGIN REMOVED 2020-05-22 JM
if (!is_array($extras)) {
    // parse string $extras into an array $result
    // This is a bit of a roundabout way of taking a presumably single value and turning it into a
    //  one-element array, but it should work.
    $result = array();
    parse_str($extras, $result);
    $extras = isset($result['extra']) ? $result['extra'] : 0;
    if (!is_array($extras)) {
        $extras = array();
    }
}

// union the bitflags in array $extra
$x = 0;  
foreach ($extras as $extra) {
    $x += intval($extra);
}
END REMOVED 2020-05-22 JM
*/

/* BEGIN REPLACED 2020-05-22 JM
$invoice->setStatus($invoiceStatusId, $extra, $note)) {
$invoice = new Invoice($invoiceId, $user);
$ret = array();
$ret['status'] = 'fail';
if ($invoice->getInvoiceStatusId() == $invoiceStatusId) {
    $ret['status'] = 'success';
}
END REPLACED 2020-05-22 JM
*/
// BEGIN REPLACEMENT 2020-05-22 JM ($ret now initialized above) 
if ($invoice->setStatus($invoiceStatusId, $customerPersonIds, $note)) {
    $ret['status'] = 'success';
}
// END REPLACEMENT 2020-05-22 JM

header('Content-Type: application/json');
echo json_encode($ret);
die();
   
?>