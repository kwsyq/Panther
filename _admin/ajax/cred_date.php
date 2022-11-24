<?php
/* _admin/ajax/cred_date.php

    EXECUTIVE SUMMARY: Update creditDate for a creditRecord

    INPUT $_REQUEST['id']: primary key creditRecordId in DB table CreditRecord. 
    INPUT $_REQUEST['value']: new creditDate for the indicated row in DB table CreditRecord.
                            >>>00016: We should certainly validate this as a date and return 'fail' if it is invalid
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' on success, status='fail' otherwise.
            >>>00006 but it looks like as things stand 2019-05-13, if $id doesn't refer to any existing row, that's still 'success'.
             Similarly for any DB failure.

   >>>00037 Common code should be eliminated: this is extremely similar to ajax/cred_date.php, they should share common code.
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
$time = date('Y-m-d', strtotime($value));

$db = DB::getInstance();

if (intval($id)) {    
    $query = " update " . DB__NEW_DATABASE . ".creditRecord set ";
    $query .= " creditDate = '" . $db->real_escape_string($time) . "' ";
    $query .= " where creditRecordId = " . intval(intval($id)) . " ";
   // echo $query; // Commented out by Martin before 2019
    $db->query($query); // >>>00002 ignores failure on DB query!    
    $data['status'] = 'success';    
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
