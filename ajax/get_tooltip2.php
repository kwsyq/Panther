<?php
/*  ajax/get_tooltip.php

    INPUT $_REQUEST['fieldName']
   
    Get the matching row from table tooltip. Return all data for a specific fieldName.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$fieldName = isset($_REQUEST['fieldName']) ? trim($_REQUEST['fieldName']) : ""; // get filed name
$pageName = isset($_REQUEST['pageName']) ? trim($_REQUEST['pageName']) : ""; // get page name

if ($fieldName) {
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".tooltip ";
    $query .= " WHERE fieldName = '" . $db->real_escape_string($fieldName) . "' ";
    $query .= " AND pageName = '" . $db->real_escape_string($pageName) . "' ";

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not get the Tooltips. Database Error";
        $logger->errorDb('637489885510891830', $error, $db);
        $data['error'] = "ajax/get_tooltip2.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {
        $row = $result->fetch_assoc();
        $data["tooltip"] = $row["tooltip"];
        $data["help"] = $row["help"];
        $data['fieldName'] = $row["fieldName"];
        $data['pageName'] = $row["pageName"];
        
    }
} 

if (!$data['error']) {
    $data['status'] = 'success';
}
header('Content-Type: application/json');
echo json_encode($data);
die();
?>