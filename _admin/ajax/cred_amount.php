<?php
/* _admin/ajax/cred_amount.php

    EXECUTIVE SUMMARY: Update amount for a creditRecord

    INPUT $_REQUEST['id']: primary key creditRecordId in DB table CreditRecord. 
    INPUT $_REQUEST['value']: new "amount" for the indicated row in DB table CreditRecord.
                            >>>00016: also, we should certainly validate at least isNumeric($value), possibly more validation.
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' on success, status='fail' otherwise.
           >>>00006 but it looks like as things stand 2019-05-13, if $id doesn't refer to any existing row, that's still 'success'.
           Similarly for any DB failure.
           
   >>>00037 Common code should be eliminated: this is extremely similar to ajax/cred_amount.php, they should share common code.
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
if (!is_numeric($value)){
    $value='';
}

$db = DB::getInstance();

if (intval($id)) {    
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " amount = '" . $db->real_escape_string($value) . "' ";
    $query .= " where creditRecordId = " . intval($id) . " ";
    
    $db->query($query); // >>>00002 ignores failure on DB query!    
    $data['status'] = 'success';    
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
