<?php
/*  ajax/addworkordertask.php

// BEGIN MARTIN COMMENT
// this is the kludge to just add tasks to a work order with an [optional] "elementid"
// and go back and try to sort it out.
// the whole point was "ease of use"
// END MARTIN COMMENT

    INPUTS
        $_REQUEST['taskId'] : primary key in DB table Task
        $_REQUEST['elementId'] : primary key in DB table Element:
                optional, and can be a comma-separated list; any elementIds that don't already exist in the DB will be ignored.
        $_REQUEST['workOrderId'] : primary key in DB table workOrder

    No explicit return.

    Acts only if taskId and workorderId are valid.
    
    There are some subtleties here (e.g. avoid adding what already exists); read the code & comments below if you need to understand them.
    
    >>>00042 JM 2020-07-08: I am not by any means convinced this is entirely correct. According to Ron, it makes perfect sense for the same 
    task to be added more than once to a given workorder + element (typically with different workOrderTask.extraDescription). We *do* allow for that
    here, but seem to presume that we can only need one entry for any parent of the task. Given that Ron says it may make perfect sense to
    add a task that isn't a "leaf", it doesn't seem to me (JM) to make sense to say there can be only one workOrderTask for the parent task. 
*/
include '../inc/config.php';
include '../inc/access.php';

$v=new Validator2($_REQUEST);
/*
    [CP] 2019-11-19
    both fields are required in order to do db queries (test + insert if necessary)
    both fields must be integer and bigger than 0 as ids in table
    elementId could be empty or not present 
*/
$v->rule('required', ['taskId', 'workOrderId']); 
$v->rule('integer', ['taskId', 'workOrderId']); 
$v->rule('min', 'taskId', 1); 
$v->rule('min', 'workOrderId', 1);

if(!$v->validate()) {
    $logger->error2('1574364035', "Error input parameters ".json_encode($v->errors()));	
	header('Content-Type: application/json');
    echo $v->getErrorJson(); // >>>00001 >>>00026 JM to CP: How does this fit in with the "no explicit return" above? Seems
                             // to me to contradict that. Might be harmless, but is at best odd, and certainly should not go
                             // without remark. Do you have some intent in the broader context?
    exit;
}

$db = DB::getInstance();

$taskId = intval($_REQUEST['taskId']);
$elementId = $_REQUEST['elementId'] ? $_REQUEST['elementId'] : array();
$workOrderId = intval($_REQUEST['workOrderId']);

$elementIds = explode(",", $elementId);
if (!is_array($elementIds)) {
    $elementIds = array();
}

if (WorkOrder::validate($workOrderId)) {
    if (Task::validate($taskId)) {
        $rawTask = new Task($taskId);
        $workOrderTaskId = 0;
        
        /* First, we try to insert a new workOrderTask using workOrderId, taskId. 
           (Before v2020-4, this had a viewMode based on the taskId, but we have eliminated viewMode.)
           
           JM 2019-12-02: It is OK to have more than one row here with the same (workOrderId, taskId).
           You can run the following query and see quite a few: over 8000 as of 2019-12-02. 
           
                select A.workOrderTaskId, A.workOrderId, A.taskId, A.* 
                from workOrderTask as A
                where EXISTS (
                  Select workOrderTaskId from workOrderTask as B
                  where B.workOrderTaskId <> A.workOrderTaskId
                  and B.workOrderId = A.workOrderId
                  and B.taskId = A.taskId  
                )
                order by workOrderId, taskId;
                
           JM 2020-06-15: added insertedPersonId, per http://bt.dev2.ssseng.com/view.php?id=67 (workOrderTask ought to have timestamp)     
        */                
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTask (workOrderId, taskId, " . 
                 // "viewMode, " . // REMOVED 2020-10-28 JM getting rid of viewmode 
                 "insertedPersonId) VALUES (";
        $query .= intval($workOrderId);
        $query .= ", " . intval($taskId);
        // $query .= ", " . intval($rawTask->getViewMode()); // REMOVED 2020-10-28 JM getting rid of viewmode
        $query .= ", " . intval($user->getUserId()) . ");";
            
        $result = $db->query($query);

        if (!$result) {
            // Hard error
            $logger->errorDb('1574368603', '', $db);
            exit;
        }
        
        $workOrderTaskId = intval($db->insert_id);
        
        /* JM 2020-07-08: I think the following test is completely redundant, won't ever fail. If we had a 
           DB error, we already exited. If not, we should have a nonzero $workOrderTaskId. But I've left the 
           test & am logging an error if it fails. */
        if ($workOrderTaskId) {
            $workOrderTaskIds = array();
            $workOrderTaskIds[] = $workOrderTaskId;
            /*
                // BEGIN MARTIN COMMENT                    
                // here do the check if the parent(s) are in there too                    
                41 has 2 parents
                // END MARTIN COMMENT
            */
            /* Query for "ancestors" of the INPUT taskId in the task hierarchy. We form an array $parents, 
               each successive row of which is an associative array giving (taskId, description) for a task, 
               going sequentially from the most distant ancestor to the originally specified task. 
               
               >>>00037 JM 2020-02-03: the following appears more or less identical to saying:
                   $task = new Task(intval($taskId));
                   $parents = $task->climbTree(); 
               which is a lot more concise.
            */                    
            $parents = array(); /* >>>00012 really "ancestors" */
            
            $query = " SELECT T2.taskId, T2.description ";
            $query .= " FROM ( ";
            $query .= "     SELECT ";
            $query .= "         @r AS _id, ";
            $query .= "         (SELECT @r := parentId FROM " . DB__NEW_DATABASE . ".task WHERE taskId = _id) AS parentId, ";
            $query .= "         @l := @l + 1 AS lvl ";
            $query .= "     FROM ";
            $query .= "         (SELECT @r := " . intval($taskId) . ", @l := 0) vars, ";
            $query .= "         " . DB__NEW_DATABASE . ".task t ";
            $query .= "     WHERE @r <> 0) T1 ";
            $query .= " JOIN " . DB__NEW_DATABASE . ".task T2 ";
            $query .= " ON T1._id = T2.taskId ";
            $query .= " ORDER BY T1.lvl DESC;";

            $result = $db->query($query);

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $parents[] = $row;
                }
            } else {
                $logger->errorDb('1574368603', '', $db);
            }

            /* For any ancestor tasks not yet associated with this workOrder, we insert a row in table workOrderTask. */
            foreach ($parents as $parent) {
                if ($parent['taskId'] != $taskId) {                            
                    $exists = false;                            
                    $query = "SELECT workOrderTaskId FROM " . DB__NEW_DATABASE . ".workOrderTask ";
                    $query .= "WHERE workOrderId = " . intval($workOrderId) . " ";
                    $query .= "AND taskId = " . intval($parent['taskId']) . ";";

                    $result = $db->query($query);
                    if ($result) {
                        /* BEGIN REPLACED 2020-09-11 JM, addressing http://bt.dev2.ssseng.com/view.php?id=114:
                           It's not just whether the task is associated with the workOrder, but whether it is
                           associated with this particular set of workOrderTasks.
                       
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $workOrderTaskIds[] = $row['workOrderTaskId'];
                            $exists = true;
                        }
                        // END REPLACED 2020-09-11 JM
                        */
                        // BEGIN REPLACEMENT 2020-09-11 JM
                        while ($row = $result->fetch_assoc()) {
                            // Does this have exactly this set of elements associated?
                            $parentWorkOrderTaskId = $row['workOrderTaskId'];
                            $parentWorkOrderTask = new WorkOrderTask($parentWorkOrderTaskId);
                            $parentWorkOrderTaskElements = $parentWorkOrderTask->getWorkOrderTaskElements();
                            if (count($parentWorkOrderTaskElements) == count($elementIds)) {
                                // same number of elements, could be a match
                                $match = true;
                                foreach ($parentWorkOrderTaskElements as $element) {
                                    if (!in_array($element->getElementId(), $elementIds)) {
                                        $match = false;
                                        break;
                                    }
                                }
                                $exists = $match;
                            }
                            if ($exists) {
                                break;
                            }
                        }
                        unset($parentWorkOrderTaskId, $parentWorkOrderTask, $parentWorkOrderTaskElements, $element);
                        // END REPLACEMENT 2020-09-11 JM
                    } else {
                        $logger->errorDb('1574369023', 'Hard DB error', $db);
                    }
                    
                    if (!$exists) {                                
                        $rt = new Task($parent['taskId']);
                        
                        // JM 2020-06-15: added insertedPersonId, per http://bt.dev2.ssseng.com/view.php?id=67 (workOrderTask ought to have timestamp)
                        $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTask (workOrderId, taskId, " . 
                                 // "viewMode, " . // REMOVED 2020-10-28 JM getting rid of viewmode 
                                 "insertedPersonId) VALUES (";
                        $query .= intval($workOrderId);
                        $query .= ", " . intval($parent['taskId']);
                        // $query .= ", " . intval($rt->getViewMode()); // REMOVED 2020-10-28 JM getting rid of viewmode
                        $query .= ", " . intval($user->getUserId()) . ");";
                        
                        $result = $db->query($query);
                        if ($result) {
                            $id = $db->insert_id;                                    
                            if (intval($id)) {
                                $workOrderTaskIds[] = $id;
                            } else {
                                $logger->errorDb('1574369054', 'Failed to insert wot', $db);
                            }
                        } else {
                            $logger->errorDb('1574369092', 'Hard DB error', $db);
                        }
                    }
                }
            } // END foreach ($parents...
            
            /* Now, for each elementId, if we don't already have a row in workOrderTaskElement with this 
               workorderTaskId and elementId, insert it.
               
               NOTE that no entry is ever made here for "General" (element 0, present in all jobs), since
               that would be incoherent, not associated with the particular job. */ 
            foreach ($elementIds as $elementId) {
                if (Element::validate($elementId)) {
                    foreach ($workOrderTaskIds as $workOrderTaskId) {
                        $exists = false;                                
                        $query = "SELECT workOrderTaskElementId FROM " . DB__NEW_DATABASE . ".workOrderTaskElement " . 
                                 "WHERE workOrderTaskId = " . intval($workOrderTaskId) . " " . 
                                 "AND elementId = " . intval($elementId) . ";";
    
                        $result = $db->query($query);
                        if ($result) {
                            if ($result->num_rows > 0) {
                                $exists = true;
                            }
                        } else {
                            $logger->errorDb('1574369245', 'Hard DB error', $db);
                        }
                            
                        if (!$exists) {                            
                            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskElement (workOrderTaskId, elementId) VALUES (";
                            $query .= intval($workOrderTaskId);
                            $query .= ", " . intval($elementId) . ");";
        
                            $result=$db->query($query);
                            if (!$result) {
                                $logger->errorDb('1574369281', 'Hard DB error', $db);                                        
                            }
                        }
                    } // END foreach ($workOrderTaskIds...
                } else {
                    $logger->error2('1594226662', "Invalid elementId $elementId");
                }
            } // END foreach ($elementIds...
        } else {
            $logger->error2('1594227097', '$workOrderTaskId is not truthy');
        }
    } else {
        $logger->error2('1594225929', 'invalid $taskId ' . $taskId);
    }	
} else {
    $logger->error2('1594225929', 'invalid $workOrderId ' . $workOrderId);
}
?>