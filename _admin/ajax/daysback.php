<?php
/*  _admin/ajax/daysback.php

    EXECUTIVE SUMMARY: Sets daysback for a particular customerPerson

    INPUT $_REQUEST['id']: should be of the form customerId_personId. 
    INPUT $_REQUEST['value']: new (integer) daysback value
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' if the inputs validated as integers, 'fail' otherwise. 
           >>>00002: NOTE that if either of the IDs is invalid, there will be no row to update, 
           but this will still be considered a success; similarly, no range-check on daysback.
*/

include '../../inc/config.php';
include '../../inc/access.php';

/* BEGIN REMOVED 2020-02-25 JM This function is not used in this file.
function validateDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// END REMOVED 2020-02-25 JM
*/

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';

$parts = explode("_", $id);

$db = DB::getInstance();

if (is_array($parts)) {
    if (count($parts) == 2) {
        if (intval($parts[0])) {
            if (intval($parts[1])) {
                if (intval($value)) {
                    $query = " update " . DB__NEW_DATABASE . ".customerPerson set ";
                    $query .= " daysBack = " . intval($value) . " ";
                    $query .= " where customerId = " . intval(intval($parts[0])) . " ";
                    $query .= " and personId = " . intval(intval($parts[1])) . " ";

                    $db->query($query); // >>>00002 ignores failure on DB query!
                    $data['status'] = 'success';
                } else {
                    $data['status'] = 'success';
                }
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
