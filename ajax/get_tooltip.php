<?php
/*  ajax/get_tooltip.php

    INPUT $_REQUEST['pageName']
   
    Get any matching row from table tooltip. Return all tooltip for a specific page
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$pageName = isset($_REQUEST['pageName']) ? $_REQUEST['pageName'] : ""; // get file name

if ($pageName) {
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".tooltip ";
    $query .= " WHERE pageName = '" . $db->real_escape_string($pageName) . "' ";

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not get the Tooltips. Database Error";
        $logger->errorDb('637483979782867920', $error, $db);
        $data['error'] = "ajax/get_tooltip.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
}

if (!$data['error']) {
    $data['status'] = 'success';
}
header('Content-Type: application/json');
echo json_encode($data);
die();
?>