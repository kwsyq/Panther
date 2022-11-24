<?php
/*  ajax/delete_tooltip.php

    INPUT $_REQUEST['[fieldId]']
   
    Get one matching row from table tooltip. Delete the row.
    Return : status fail on query failure or status success.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$fieldId = isset($_REQUEST['fieldId']) ? intval($_REQUEST['fieldId']) : ""; // get unique id.

if ($fieldId) {
    $query = "DELETE FROM " . DB__NEW_DATABASE . ".tooltip ";
    $query .= "WHERE id =" . intval($fieldId) . ";";

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not delete the Tooltip. Database Error";
        $logger->errorDb('637489885329040419', $error, $db);
        $data['error'] = "ajax/delete_tooltip.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
} 

if (!$data['error']) {
    $data['status'] = 'success';
}
header('Content-Type: application/json');
echo json_encode($data);
die();
?>