<?php
/* _admin/ajax/addtask.php

    Abstracted 2020-11-04 from _admin/newtask/edit.php
    
    INPUT $_REQUEST['parentId'] - taskId of parent, or 0 for root level
    INPUT $_REQUEST['description'] - description of new task; also used for billingDescription.
    
    RETURN JSON for an associative array with the following members:
        * 'status': 'fail' or 'success'
        * 'taskId': new taskId on success, 0 otherwise
        * 'error': error message for display to user, on failure only.
*/
include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['taskId'] = 0;
$data['error'] = '';

$parentId = 0;
$v=new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();

if ($error) {
    $logger->error2('1604508832', "Error(s) found in init validation: [".json_encode($v->errors())."]");
    $error = "Error(s) found in init validation";
} else {
    $v->stopOnFirstFail();
    $v->rule('integer', 'parentId');
    $v->rule('required', ['parentId', 'description']);
    $v->rule('min', 'parentId', 0);
    if( !$v->validate() ) {
        $error = "parentId" . (isset($_REQUEST['parentId']) ? " : " . $_REQUEST['parentId'] : '') . 
            " is not valid. Errors found: ".json_encode($v->errors());
        $logger->error2('1604508960', "$error");
    }
}

if (!$error) {
    $parentId = $_REQUEST['parentId']; 
    if ($parentId == 0) {
        // fine
    } else if (!Task::validate($parentId, '1604509123')) { // '1604509123' puts a unique error in the log if this fails to validate
        $error = "parentId $parentId is not valid.";
    }
}

if (!$error) {
    $description = trim($_REQUEST['description']);
    if (strlen($description) == 0) {
        $error = "Must provide description to create a new task.";
        $logger->error2('1604509326', "$error");
    }
}

if (!$error) {
    list($taskId, $error) = Task::addTask($parentId, $description);
}

if ($error) {
    $data['error'] = $error;
} else {
    $data['status'] = 'success';
    $data['taskId'] = $taskId; 
}

header('Content-Type: application/json');
echo json_encode($data);
?>