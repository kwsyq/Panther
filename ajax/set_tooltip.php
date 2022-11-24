<?php
/*  ajax/set_tooltip.php

    INPUT:  
    * $_REQUEST['pageName']
    * $_REQUEST['fieldName']
    * $_REQUEST['fieldLabel']
    * $_REQUEST['textTooltip'];
    * $_REQUEST['textHelp']
   
    Set/Updates any matching row from table tooltip based on fieldName.
    Selects and return the data.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';


$pageName = isset($_REQUEST['pageName']) ? trim($_REQUEST['pageName']) : ""; // get file name
$fieldName = isset($_REQUEST['fieldName']) ? trim($_REQUEST['fieldName']) : ""; // get filed name
$fieldLabel = isset($_REQUEST['fieldLabel']) ? trim($_REQUEST['fieldLabel']) : ""; 
$tooltip = isset($_REQUEST['textTooltip']) ? trim($_REQUEST['textTooltip']) : "";
$help = isset($_REQUEST['textHelp']) ? trim($_REQUEST['textHelp']) : "";
$help =preg_replace('/\s+/', '', $help);

if ($fieldName) {
    
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".tooltip ";
    $query .= " WHERE fieldName = '" . $db->real_escape_string($fieldName) . "' ";
    $query .= " AND pageName = '" . $db->real_escape_string($pageName) . "' ";


    $result = $db->query($query);

    if (!$result) {
        $error = "We could not get the Tooltips. Database Error";
        $logger->errorDb('637487210401011192', $error, $db);
        $data['error'] = "ajax/get_tooltip.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {
        if ($result->num_rows > 0) {  // we have an entry
            $query = "UPDATE " . DB__NEW_DATABASE . ".tooltip ";                                    
            $query .= "SET tooltip = '" . $db->real_escape_string($tooltip) . "', help = '" . $db->real_escape_string($help) . "' ";
            $query .= "WHERE fieldName = '" . $db->real_escape_string($fieldName) . "' ";
            $query .= " AND pageName = '" . $db->real_escape_string($pageName) . "' ";
            
        
            $result = $db->query($query);
        
            if (!$result) {
                $error = "We could not get the Tooltips. Database Error";
                $logger->errorDb('637486505309511785', $error, $db);
                $data['error'] = "ajax/set_tooltip.php: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die();
            } 
        } else {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".tooltip(pageName, fieldName, fieldLabel, tooltip, help) VALUES (";   
            $query .= " '" . $db->real_escape_string($pageName) . "'";
            $query .= ", '" . $db->real_escape_string($fieldName) . "'";
            $query .= ", '" . $db->real_escape_string($fieldLabel) . "'";
            $query .= ", '" . $db->real_escape_string($tooltip) . "'";
            $query .= ", '" . $db->real_escape_string($help) . "');";
            
            $result = $db->query($query);
            if (!$result) {
                $error = "We could not insert the Tooltips. Database Error";
                $logger->errorDb('637488148547621506', $error, $db);
                $data['error'] = "ajax/set_tooltip.php: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die();
            } 
        }
    } 
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".tooltip ";
    $query .= " WHERE fieldName = '" . $db->real_escape_string($fieldName) . "' ";
    $query .= " AND pageName = '" . $db->real_escape_string($pageName) . "' ";

    $result = $db->query($query);
    if(!$result) {
        $error = "We could not Select the Tooltips. Database Error";
        $logger->errorDb('637488155390658182', $error, $db);
        $data['error'] = "ajax/set_tooltip.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
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