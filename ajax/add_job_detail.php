<?php
/*  ajax/add_job_detail.php

    INPUT $_REQUEST['jobId']
    INPUT $_REQUEST['personId']
    INPUT $_REQUEST['detailRevisionId']
    Add a detail to Job table tooltip.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$jobId = isset($_REQUEST['jobId']) ? $_REQUEST['jobId'] : ""; // get the Id of the Job
$personId = isset($_REQUEST['personId']) ? $_REQUEST['personId'] : ""; // get the person who added the detail to the Job
$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? $_REQUEST['detailRevisionId'] : ""; // get the Detail RevisionId


if ($jobId && $personId && $detailRevisionId) {
    /*$query = "SELECT * FROM " . DB__NEW_DATABASE . ".jobdetail ";
    $query .= " WHERE detailRevionId = '" . $db->real_escape_string($detailRevionId) . "' "; */

    $query = "INSERT INTO " . DB__NEW_DATABASE . ".jobdetail(jobId, detailRevisionId, personId) VALUES (";   
    $query .= intval($jobId);
    $query .= ", " . intval($detailRevisionId);
    $query .= ", " . intval($personId) . ");";

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not add the detail to this job. Database Error";
        $logger->errorDb('637564981983616471', $error, $db);
        $data['error'] = "ajax/add_job_detail.php: $error";
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