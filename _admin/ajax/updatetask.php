<?php
/* _admin/ajax/updatetask.php

    Abstracted 2020-11-04 from _admin/newtask/edit.php
    
    INPUT $_REQUEST['taskId'] - taskId of task we are updating in DB
    INPUT $_REQUEST['description'] - mandatory
    INPUT $_REQUEST['billingDescription'] - if blank, we use INPUT $_REQUEST['description']
    INPUT $_REQUEST['taskTypeId'] - optional; if present, primary key in DB table taskType. Missing, zero, etc => we want to NULL this out.
    INPUT $_REQUEST['active'] - mandatory, quasi-boolean, 0 or 1; whether task is active.
    INPUT $_REQUEST['wikiLink'] - can be blank
    
    RETURN JSON for an associative array with the following members:
        * 'status': 'fail' or 'success'
        * 'error': error message for display to user, on failure only.
*/
include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();

if ($error) {
    $logger->error2('1604517074', "Error(s) found in init validation: [".json_encode($v->errors())."]");
    $error = "Error(s) found in init validation";
} else {
    $v->stopOnFirstFail();
    $v->rule('required', ['taskId', 'description', 'active']);
    $v->rule('integer', ['taskId', 'taskTypeId', 'active']);
    $v->rule('min', 'taskId', 1);
    $v->rule('min', 'taskTypeId', 0);
    $v->rule('min', 'active', 0);
    $v->rule('max', 'active', 1);
    if( !$v->validate() ) {
        $error = "Errors found: ".json_encode($v->errors());
        $logger->error2('1604517741', "$error");
    }
}

if (!$error) {
    $taskId = intval($_REQUEST['taskId']); 
    if (!Task::validate($taskId, '1604517888')) { // '1604517888' puts a unique error in the log if this fails to validate
        $error = "taskId $taskId is not valid.";
    }
}

if (!$error) {
    $taskTypeId = isset($_REQUEST['taskTypeId']) ? intval($_REQUEST['taskTypeId']) : '';
    if ($taskTypeId && !array_key_exists($taskTypeId, getTaskTypes()) ) {
        $error = "taskTypeId $taskTypeId is not valid.";
        $logger->error2('1604518169', "$error");
    }    
}

if (!$error) {
    $description = trim($_REQUEST['description']);
    if (strlen($description) == 0) {
        $error = "Task description cannot be empty.";
        $logger->error2('1604518202', "$error");
    }
}

if (!$error) {
    $billingDescription = isset($_REQUEST['billingDescription']) ? trim($_REQUEST['billingDescription']) : '';
    if (strlen($billingDescription) == 0) {
        $billingDescription = $description; 
    }
}

if (!$error) {    
    $active = $_REQUEST['active']; // Already sufficiently validated
    $wikiLink = trim($_REQUEST['wikiLink']); // Fine if this is empty.
    
    $task = new Task($taskId);
    $task->update(Array(
        'taskId' => $taskId,
        'description' => $description,
        'billingDescription' => $billingDescription,
        'taskTypeId' => $taskTypeId,
        'active' => $active,
        'wikiLink' => $wikiLink
    ));            
}

if ($error) {
    $data['error'] = $error;
} else {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>
