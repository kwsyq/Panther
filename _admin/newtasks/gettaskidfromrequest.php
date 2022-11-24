<?php
/* _admin/newtasks/gettaskidfromrequest.php

    Common code elimination: a function to get taskId from $_REQUEST.
    Also returns error info if $_REQUEST['taskId'] is not valid.
    
    Normal call would look like:
    list($taskId, $error) = getTaskIdFromRequest(__FILE__);

    INPUT $filename - caller filename, just for logging
    INPUT $zeroedTaskIdIsOk - Boolean, default false; if true, then a zero or missing taskId is OK. 
    
    On success, $taskId is a valid taskId.
    On failure, $taskId == 0, and $error is suitable for display to end user.
    
*/

function getTaskIdFromRequest($filename, $zeroedTaskIdIsOk = false) {
    global $logger;
    
    $error = '';
    $errorId = 0;
    $taskId = 0;
    $v=new Validator2($_REQUEST);
    list($error, $errorId) = $v->init_validation();
    
    if ($error) {
        $logger->error2('1601662342', "$filename: Error(s) found in init validation: [".json_encode($v->errors())."]");
        $error = "Error(s) found in init validation";
    } else {
        $v->stopOnFirstFail();
        $v->rule('integer', 'taskId');
        if ($zeroedTaskIdIsOk) {
            $v->rule('min', 'taskId', 0);
        } else {
            $v->rule('min', 'taskId', 1);
            $v->rule('required', 'taskId');
        }
        if( !$v->validate() ) {
            $error = "taskId" . (isset($_REQUEST['taskId']) ? " : " . $_REQUEST['taskId'] : '') . 
                " is not valid. Errors found: ".json_encode($v->errors());
            $logger->error2('1601662360', "$filename: $error");
        }
    }
    
    if (!$error) {
        $taskId = isset($_REQUEST['taskId']) ? $_REQUEST['taskId'] : 0; // allow for the $zeroedTaskIdIsOk case with missing taskId 
        if ($zeroedTaskIdIsOk && $taskId==0) {
            // fine
        } else if (!Task::validate($taskId, '1601662425')) { // '1601662425' puts a unique error in the log if this fails to validate
            $error = "$filename: taskId $taskId is not valid.";
            $taskId = 0;
        }
    }
    
    $ret = Array($taskId, $error);
    return $ret;    
}
?>