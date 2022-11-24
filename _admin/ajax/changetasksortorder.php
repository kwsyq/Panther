<?php
/* _admin/ajax/changetasksortorder.php

    EXECUTIVE SUMMARY: For two tasks with a common parent, change the sortorder position to be immediately before or after the other 
    
    Introduced 2020-11-09 JM
    
    INPUT $_REQUEST['taskId'] - taskId of task whose sortorder we want to change
    INPUT $_REQUEST['parentId'] - taskId of parent task
    INPUT $_REQUEST['relativeToTask'] - taskId of task we want to be before or after
    INPUT $_REQUEST['beforeOrAfter'] - 'before' or 'after'. We should always place specified task 
                                       either before a task that previously preceded it, or after
                                       one that previously followed it.
    
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
    $logger->error2('1604966201', "Error(s) found in init validation: [".json_encode($v->errors())."]");
    $error = "Error(s) found in init validation";
} else {
    $v->stopOnFirstFail();
    $v->rule('required', ['taskId', 'parentId', 'relativeToTask', 'beforeOrAfter']);
    $v->rule('integer', ['taskId', 'parentId', 'relativeToTask']);
    $v->rule('min', ['taskId', 'relativeToTask'], 1);
    $v->rule('min', 'parentId', 0);
    $v->rule('min', 'active', 0);
    $v->rule('max', 'active', 1);
    $v->rule('in', 'beforeOrAfter', ['before', 'after']); 
    if( !$v->validate() ) {
        $error = "Errors found: ".json_encode($v->errors());
        $logger->error2('1604966641', "$error");
    }
}

if (!$error) {
    $beforeOrAfter = $_REQUEST['beforeOrAfter']; // fully validated

    $taskId = intval($_REQUEST['taskId']); 
    if (!Task::validate($taskId, '1604966888')) { // '1604966888' puts a unique error in the log if this fails to validate
        $error = "taskId $taskId is not valid.";
    }
}

if (!$error) {
    $parentId = intval($_REQUEST['parentId']); 
    // Allow 0 here
    if ($parentId && !Task::validate($parentId, '1604967010')) { // '1604967010' puts a unique error in the log if this fails to validate
        $error = "parentId $parentId is not a valid taskId.";
    }
}

if (!$error) {
    $relativeToTask = intval($_REQUEST['relativeToTask']); 
    if (!Task::validate($relativeToTask, '1604967121')) { // '1604967121' puts a unique error in the log if this fails to validate
        $error = "relativeToTask taskId $relativeToTask is not valid.";
    }
}

if (!$error) {
    if ($parentId) {
        $parentTask = new Task($parentId);
        $siblings = $parentTask->getChilds(false, false); // false, false => use sortOrder, include inactive
    } else {
        // 0-level
        $siblings = Task::getZeroLevelTasks(false, false); // false, false => use sortOrder, include inactive
    }    
}

if (!$error) {
    $foundTaskId = false;
    $foundRelativeToTask = false;
    $sequenceError = false;
    foreach ($siblings as $sibling) {
        if ($sibling->getTaskId() == $taskId) {
     
            $foundTaskId = true;
            if ($beforeOrAfter == 'before') {
                $sequenceError = !$foundRelativeToTask;
            } else {
                $sequenceError = $foundRelativeToTask;
            }
        }
        if ($sibling->getTaskId() == $relativeToTask) {
            $foundRelativeToTask = true;
        }
    }
    if (!$foundTaskId) {
        $error = "taskId $taskId is not a child of taskId $parentId";
        $logger->error2('1604968010', $error);
    } else if (!$foundRelativeToTask) {
        $error = "taskId $relativeToTask is not a child of taskId $parentId";
        $logger->error2('1604968345', $error);
    } else if ($sequenceError) {
        $error = "Failed to place taskId $taskId before task $relativeToTask";
        if ($beforeOrAfter == 'before') {
            $logger->error2('1604968765', "Trying to place taskId $taskId before task $relativeToTask, which currently comes after it; unsupported");
        } else {
            $logger->error2('1604968801', "Trying to place taskId $taskId after task $relativeToTask, which currently comes before it; unsupported");
        }
    }
}

if (!$error) {
    // >>>00006: the following might better be one or two new methods in the Task class
    // Rewrite the whole ordering, starting with 1 for first task
    //  This will have the side effect of fixing up a lot of old artifacts for v2020-4.
    $sortOrder = 1;
    if ($beforeOrAfter == 'before') {
        $lookingForRelativeTo = true;
        $lookingForTask = false;
        foreach ($siblings as $sibling) {
            if ($lookingForRelativeTo && $sibling->getTaskId() != $relativeToTask) {
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
            } else if ($lookingForRelativeTo) {
                // We got a match on that
                $desiredSortOrder = $sortOrder++; // We'll want to apply this to our task
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
                $lookingForRelativeTo = false;
                $lookingForTask = true;
            } else if ($lookingForTask && $sibling->getTaskId() != $taskId) {
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
            } else if ($lookingForTask) {
                // found it!
                $sibling->setSortOrder($desiredSortOrder);
                $sibling->save();
                $lookingForTask = false;
            } else {
                // trailing values
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
            }
        }
    } else {
        // $beforeOrAfter == 'after'
        $lookingForTask = true;
        $lookingForRelativeTo = false;
        foreach ($siblings as $sibling) {
            if ($lookingForTask && $sibling->getTaskId() != $taskId) {
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
            } else if ($lookingForTask) {
                // found it!
                $taskItself = $sibling;
                $lookingForTask = false;
                $lookingForRelativeTo = true;
            } else if ($lookingForRelativeTo && $sibling->getTaskId() != $relativeToTask) {
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
            } else if ($lookingForRelativeTo) {
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
                $taskItself->setSortOrder($sortOrder++);
                $taskItself->save();
                $lookingForRelativeTo = false;
            } else {
                $sibling->setSortOrder($sortOrder++);
                $sibling->save();
            }
        }
    }
}

if ($error) {
    $data['error'] = $error;
} else {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>
