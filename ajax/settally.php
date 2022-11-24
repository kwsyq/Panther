<?php
/* ajax/settally.php

    INPUT $_REQUEST['workOrderTaskId']: primary key in DB table workOrderTask
    INPUT $_REQUEST['tally']: tally to set for this workOrder
    
    Modified for v2020-4: we used to have the potential to keep separate per-user tallies, 
    but decided that was not a good idea (http://bt.dev2.ssseng.com/view.php?id=94#c1100),
    so this has changed a lot for v2020-4.

    Insert or update and return (whether user is employee or not) tally 
     for workOrderTaskId. (tally is treated as a real number. 
     Any characters other than digits or '.' in INPUT tally will be ignored. Can be zero.)
    
    Returns JSON for an associative array with the following members:    
        * 'status': "fail" if workOrderTaskId not valid or on other errors; otherwise "success".
        * 'tally': new tally value; can be zero, reflecting no entry in DB. 
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$data = array();
$data['status'] = 'fail';
$changesOk = true;

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;

$tally = isset($_REQUEST['tally']) ? trim($_REQUEST['tally']) : '';

if (!strlen($tally)) {
    // blank is OK, treat it as 0.
    $tally = 0;
}

if (is_numeric($tally)) {
    $userId = $user->getUserId();
    
    $taskTallyId = false;
    $query = "SELECT taskTallyId FROM " . DB__NEW_DATABASE . ".taskTally ";
    $query .= "WHERE workOrderTaskId = " . intval($workOrderTaskId) . ";";
    $result = $db->query($query);
    
    if (!$result) {
        $logger->errorDB('1600881010', 'Hard DB error selecting taskTally', $db);
    } else {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $taskTallyId = $row['taskTallyId']; 
        }
    }
    
    if ($taskTallyId) {
        // change the tally number and indicate who changed it. We do this
        // even if someone has zeroed out a previously non-zero tally: we
        // want a record of who zeroed it.
        $query = "UPDATE taskTally SET ";
        $query .= "tally = $tally";
        $query .= ", personId = $userId ";
        $query .= "WHERE taskTallyId = $taskTallyId;"; 
        $result = $db->query($query);
        
        if (!$result) {
            $logger->errorDB('1600881111', 'Hard DB error updating taskTally', $db);
        } else {
            $data['status'] = 'success';
            $data['tally'] = $tally;
        }
    } else if ($tally != 0) {
        // insert the new tally
        $query = "INSERT INTO taskTally (workOrderTaskId, tally, personId) VALUES (";
        $query .= intval($workOrderTaskId);
        $query .= ", $tally";
        $query .= ", $userId";
        $query .= ");"; 
        $result = $db->query($query);
        
        if (!$result) {
            $logger->errorDB('1600881243', 'Hard DB error inserting into taskTally', $db);
        } else {
            $data['status'] = 'success';
            $data['tally'] = $tally;
        }
    }
} else {
    $logger->error2('1600880990', 'non-numeric tally "' . $_REQUEST['tally'] . '"');
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>