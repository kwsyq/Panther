<?php
/*  ajax/set_note_workordertask.php


    Usage: workorder.php. On the existing workorder structure of workOrderTasks, we can add a note.


    INPUT $_REQUEST['workOrderTaskId']: primary key in DB table workOrderTask.
    INPUT $_REQUEST['noteText'] : the nte text for a workOrderTask.
    INPUT $_REQUEST['personId']: the person id of the person who enter the note.


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

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0; // get workOrderTaskId
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0; // get personId
$noteText = isset($_REQUEST['noteText']) ? trim($_REQUEST['noteText']) : ""; // get noteText
$noteTypeId = 5;

if (intval($workOrderTaskId)) {



    if (trim($noteText) != "") {
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".note (";
        $query .= "noteTypeId, id, noteText, personId ";
        $query .= ") VALUES (";
        $query .= intval($noteTypeId);
        $query .= ", " . intval($workOrderTaskId);
        $query .= ", '" . $db->real_escape_string($noteText) . "' ";
        $query .= ", " . intval($personId). ") ";
        
        $result = $db->query($query);

        if (!$result) {
            $error = "We could not insert the Notes. Database Error";
            $logger->errorDb('637713702415966185', $error, $db);
            $data['error'] = "ajax/set_note_workordertask.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
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