<?php 
/*  ajax/delete_combined_elements.php

    Usage: in workordertasks.php
        * Deletes a combined element: Element1,Element2, if the specified element does not have a workordertasks structure, 
            warning message:  'We could not delete this Element. Structure found.'
    
    Possible actions: 
        *On open modal Select Single Element(s), on tab Combined Elements, we can delete Combined element based on his element Id 
        if does not have a workordertasks structure.

    INPUT $_REQUEST['combinedElementId']: as elementId primary key in DB table element
    INPUT $_REQUEST['workorderId']: primary key in DB table workorder

    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error ),
        * 'status': "success" on successful query.
  
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$combinedElementId = isset($_REQUEST['combinedElementId']) ? intval($_REQUEST['combinedElementId']) : 0;
$workorderId = isset($_REQUEST['workorderId']) ? intval($_REQUEST['workorderId']) : 0;  


if($combinedElementId) {
    
    $query = " SELECT parentTaskId ";
    $query .= " FROM  " . DB__NEW_DATABASE . ".workOrderTask  ";
    $query .= " WHERE parentTaskId = " . intval($combinedElementId) . " ";
    $query .= " AND workOrderId = " . intval($workorderId) . ";";

    $result = $db->query($query);
    if ($result) {
        // No structure of tasks found in workOrderTask. Safe to delete the element entry from elemnt table
        if ($result->num_rows == 0) {

            $query = " DELETE FROM " . DB__NEW_DATABASE . ".element  ";
            $query .= " WHERE elementId = " . intval($combinedElementId) . " ";
            $query .= " AND workOrderId = " . intval($workorderId) . ";";

            $result = $db->query($query); 

            if(!$result) {
                $error = "We could not delete the Element from Element table. Database Error";
                $data['error'] = "ajax/delete_combined_elements.php: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die(); 
            }
               
        } else {
             // Structure of tasks found in workOrderTask. Not Safe to delete this element entry.
            $data['error'] = 'We could not delete this Element. Structure found.';
        }
    } else {
        $error = "We could not select the parentTaskId from workOrderTask table. Database Error";
        $data['error'] = "ajax/delete_combined_elements.php: $error";
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