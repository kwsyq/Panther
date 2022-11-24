<?php
/*  ajax/get_note_workordertask.php

    Usage: on workorder.php

    INPUT $_REQUEST['workOrderTaskId'] : primary key in table workOrdertasks.
   
    Get the last matching row from table note where $_REQUEST['workOrderTaskId'] match the note.id. 
    Join with Person table to get the full Name of person who inserted the note.

    Returns JSON for an associative array with the following members:
        * 'data': array. Each element is an associative array with elements:
            * 'id': identifies the note.
            * 'noteText': the text of the for a specific workOrderTask, table note.
            * 'inserted': identifies the person who wrote the note.
            * 'firstName': identifies the person firstName who wrote the note, table person.
            * 'lastName': identifies the person lastName who wrote the note, table person.
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
$noteTypeId = 5;

if (intval($workOrderTaskId)) {
    $query = "SELECT nt.id, nt.noteText, nt.inserted, ps.firstName, ps.lastName FROM " . DB__NEW_DATABASE . ".note nt ";
    $query .= " LEFT JOIN person ps on ps.personId=nt.personId ";
    $query .= " WHERE id = " . intval($workOrderTaskId) . " ";
    $query .= " AND noteTypeId = " . intval($noteTypeId) . " ";


    $result = $db->query($query);

    if (!$result) {
        $error = "We could not get the Notes. Database Error";
        $logger->errorDb('637713600410320915', $error, $db);
        $data['error'] = "ajax/get_note_workordertask.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {
        while( $row=$result->fetch_assoc() ) {
           $data['info'][] = $row;
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