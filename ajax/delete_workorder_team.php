<?php
/*  ajax/delete_workorder_team.php

    Usage: in workorder.php
        On table WorkOrder Team, call to action button Del ( available only for external members 
        that are not the client of the contract). Get one matching row from table team. Delete the entry based on teamId.


    INPUT $_REQUEST['teamId'] : primary key in table team.
    INPUT $_REQUEST['workOrderId'] : primary key in table workOrder.

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

$teamId = isset($_REQUEST['teamId']) ? intval($_REQUEST['teamId']) : 0;
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0; 



if ($teamId) {

    $query = " DELETE from " . DB__NEW_DATABASE . ".team ";
    $query .= " WHERE teamId = " . intval($teamId) . " ";
    $query .= " AND id = " . intval($workOrderId) . " ";

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not delete this person from WorkOrder Team. Database Error";
        $logger->errorDb('637780248276443444', $error, $db);
        $data['error'] = "ajax/delete_workorder_team.php: $error";
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