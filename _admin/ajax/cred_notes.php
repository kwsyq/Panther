<?php
/*  _admin/ajax/cred_notes.php

    EXECUTIVE SUMMARY: Update notes for a credit record.

    INPUT $_REQUEST['id']: primary key creditRecordId in DB table CreditRecord.
        >>>00016 NOTE that we don't really validate this beyond it being an integer.
    INPUT $_REQUEST['value']: value for column "notes". Will be trimmed to 1024 characters.

    Returns JSON for an associative array with the following members:
        * 'status': 'success' on success, 'fail' otherwise. As of 2019-05-13, 'success' is really just that the input id is an integer.
        
   >>>00037 Common code should be eliminated: this is extremely similar to ajax/cred_notes.php, they should share common code.
   ALSO a lot of the _admin/ajax/cred_*.php and ajax/cred_*.php functions are very similar, it is possible that the shared code to
    be written could be a paramaterized function and cover several of these.
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';
$value = trim($value);
$value = substr($value, 0, 1024); // >>>00002: truncates silently

$db = DB::getInstance();

if (intval($id)) {
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " notes = '" . $db->real_escape_string($value) . "' ";
    $query .= " where creditRecordId = " . intval(intval($id)) . " ";
    
    $db->query($query);
    $data['status'] = 'success';    
} // >>>00002 ignores failure on DB query!

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
