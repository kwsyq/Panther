<?php 
/*  fb/workordertaskcats.php

    EXECUTIVE SUMMARY: According to Martin, allows adding tasks to a workorder. 
    Does that at the level of "task packages" or individual tasks. 
    
    NOTE (from Martin): checkboxes allow for combining elements but you can just click the 
    link if dealing with a single element (or just check a single box).
    
    PRIMARY INPUTS: $_REQUEST['workorderId'], $_REQUEST['elementId']; $_REQUEST['elementId'] value can be an array (also can be empty).

    Optional $_REQUEST['act'], only meaningful value 'addpackage', uses $_REQUEST['taskPackageId'].
*/

include '../inc/config.php';
include '../inc/access.php';
include '../includes/header_fb.php';
?>
    <link rel="stylesheet" href="styles/kendo.common.min.css" />
    <link rel="stylesheet" href="styles/kendo.default.min.css" />
    <link rel="stylesheet" href="styles/kendo.default.mobile.min.css" />

    <script src="js/jquery.min.js"></script>


    <script src="js/kendo.all.min.js"></script>

    <link rel="stylesheet" href="../styles/kendo.common.min.css" />
    <link rel="stylesheet" href="../styles/kendo.material-v2.min.css" />

<?php
/* BEGIN REPLACED 2020-03-26 JM
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$elementInput = isset($_REQUEST['elementId']) ? $_REQUEST['elementId'] : 0;

$elementIds = array();
if (is_array($elementId)) {
    $elementIds = $elementId;
} else {
    $elementIds = array(intval($elementId));
}

if (!intval($workOrderId)) {
    die();
}
// END REPLACED 2020-03-26 JM
*/

// BEGIN REPLACEMENT 2020-03-26 JM
// Added validation & logging, plus before $elementIdInput here was just $elementId, but that name
// was multiplexed all over the file; trying to make this clearer.
$db = DB::getInstance();
$error = '';
$errorId = 0;
$error_is_db = false;

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();
$v->rule('required', ['workOrderId', 'elementId']);
$v->rule('integer', ['workOrderId']);
$v->rule('min', ['workOrderId'], 1);

if($v->validate()){
    list($error, $errorId) = $v->init_validation();
}  else {
    $errorId = '1585163093';
    $error = "Error in input parameters: " . json_encode($v->errors());
    $logger->error2($errorId, $error);    
}

if (!$error) {
    $workOrderId = intval($_REQUEST['workOrderId']); // We already know it exists (and that it's a positive integer), no need to check again here
    // Make sure it is a key in the workOrder table
    if (!WorkOrder::validate($workOrderId, '1585255387')) { // second param here is error code for hard DB error
        $error = "Invalid workOrderId $workOrderId";
        $errorId = '1585255392';
        $logger->error2($errorId, $error);
    }    
}
if (!$error) {
    $elementIdInput = $_REQUEST['elementId']; // We already know it exists, no need to check again here
    $elementIds = array();
    if (is_array($elementIdInput)) {
        $elementIds = $elementIdInput;
        // Theoretically, we could build another validator for this array, but I (JM) think code is clearer this way
        if (count($elementIds) > 20) {
            $errorId = '1585256517';
            $error = "Got over 20 elementIds in input, anything more that 3 or 4 is unlikely, something is wrong here.";
            $logger->error2($errorId, $error);
        }
        foreach ($elementIds as $ix => $elementIdString) {
            $elementId = intval($elementIdString);
            // regex in next line tests for all-digits
            // 0 is allowed because it means "general"
            if (!preg_match('/^\d+$/', trim($elementId)) || $elementId < 0) {
                $errorId = '1585256846';
                $error = "Invalid elementId $elementId, position $ix in input array. Raw element input is $elementIdInput";
                $logger->error2($errorId, $error);
                break; // have an error, no need to look further
            }
            
            // Make sure it is a key in the Element table or 0 for "general"
            if ($elementId == 0) {
                // fine, "general"
            } else if (!Element::validate($elementId, '1585256893')) { // second param here is error code for hard DB error
                $error = "Invalid elementId $elementId";
                $errorId = '1585256898';
                $logger->error2($errorId, $error);
            }
        }
        unset($ix, $elementIdString, $elementId);
    } else {
        $v->rule('integer', ['elementId']);
        $v->rule('min', ['elementId'], 0); // 0 is allowed because it means "general"
        if(!$v->validate()){
            $errorId = '1585163094';
            $error = "Error in input parameters: " . json_encode($v->errors());
            $logger->error2($errorId, $error);    
        }
        if (!$error) {
            // Make sure it is a key in the Element table or 0 for "general"
            if ($elementIdInput == 0) {
                // fine, "general"
                $elementIds = array(0);
            } else if (Element::validate($elementIdInput, '1585255392')) { // second param here is error code for hard DB error
                $elementIds = array(intval($elementIdInput));
            } else {   
                $error = "Invalid elementId $elementId";
                $errorId = '1585255398';
                $logger->error2($errorId, $error);
            }
        }
    }
}
// END REPLACEMENT 2020-03-25 JM

// END of validating inputs 

if (!$error) { // test added 2020-03-25 JM 
    // Build $nameString to describe the relevant elements: comma-separated list of names.
    $nameString = ''; // will be a comma-separated list of the relevant element names.
    foreach ($elementIds as $elementId) {
        $element = new Element($elementId); // JM 2020-03-26 we validated $elementId above, but >>>00002 we still might want to check for hard DB error. 
        if (strlen($nameString)) {
            // Not the first
            $nameString .= ", ";
        }
        if (!$element->getElementId()) {
            $elementName = 'General';
        } else {
            $elementName = $element->getElementName();
        }
        $nameString .= $elementName;
    }
    unset($elementId, $element, $elementName);
    
    // BEGIN self-submitted actions
    if ($act == 'addpackage') {
        // Query the DB to identify all tasks in the package

        /* BEGIN REPLACED 2020-03-26 JM
        $taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;

        // Given that all we need is the taskIds, this query does way too much - JM 2020-03-26
        $query = " select * ";
        $query .= " from  " . DB__NEW_DATABASE . ".taskPackage tp ";
        $query .= " join  " . DB__NEW_DATABASE . ".taskPackageTask tpt on tp.taskPackageId = tpt.taskPackageId ";
        $query .= " join  " . DB__NEW_DATABASE . ".task t on tpt.taskId = t.taskId ";
        $query .= " where tp.taskPackageId = " . intval($taskPackageId) . " ";

        $tpts = array();       
        $packageName = ''; // does not appear to be used - JM 2020-03-26
        
        // END REPLACED 2020-03-26 JM
        */
        // BEGIN REPLACEMENT 2020-03-26 JM
        $v->rule('required', ['taskPackageId']);
        $v->rule('integer', ['taskPackageId']);
        $v->rule('min', ['taskPackageId'], 1);
        if(!$v->validate()){
            $errorId = '1585258375';
            $error = "Bad validator rules: " . json_encode($v->errors());
            $logger->error2($errorId, $error);    
        }
        if (!$error) {
            $taskPackageId = intval($_REQUEST['taskPackageId']);
            
            $query = "SELECT taskId ";
            $query .= "FROM  " . DB__NEW_DATABASE . ".taskPackageTask ";
            $query .= "WHERE taskPackageId = " . intval($taskPackageId) . ";";
            
        // END REPLACEMENT 2020-03-26 JM
        // There are more changes in what follows, but they are not as comprehensive, and I haven't noted them all. Effectively,
        //  array $taskIds and $parentTaskIds replace what were unnecessarily more complicated structures, and I've made other simplifications.
        //  I also improved variable names & removed a lot of redundant tests of things that were already known.
        //  >>>00002: note that as of 2020-07-08 there is no protection against code which should not execute if there has been a prior error.
        
            $taskIds = array();            
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $taskIds[] = $row['taskId'];
                }
            } else {
                $logger->errorDB('1594234118', 'Hard DB error', $db);
            }
            
            // For each task in the package... 
            foreach ($taskIds as $taskId) {
                if (Task::validate($taskId)) {
                    $workOrderTaskIds = array(); // This will represent this workOrderTaskId and workOrderTaskIds for any ancestor tasks we add
                    $rawTask = new Task($taskId);
                    
                    // Insert (workOrderId, taskId, viewMode) into DB table WorkOrderTask. 
                    // JM 2020-06-15: added insertedPersonId, per http://bt.dev2.ssseng.com/view.php?id=67 (workOrderTask ought to have timestamp)
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTask (workOrderId, taskId, " . 
                             // "viewMode, " . // REMOVED 2020-10-28 JM getting rid of viewmode 
                             "insertedPersonId) VALUES (";
                    $query .= intval($workOrderId);
                    $query .= ", " . intval($taskId);
                    // $query .= ", " . intval($rawTask->getViewMode()); // REMOVED 2020-10-28 JM getting rid of viewmode
                    $query .= ", " . intval($user->getUserId()) . ");";
                    
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDB('1594234207', 'Hard DB error', $db);
                    }
                        
                    $workOrderTaskId = intval($db->insert_id);
                    
                    if (intval($workOrderTaskId)) {
                        $workOrderTaskIds[] = $workOrderTaskId;
                        unset($workOrderTaskId);
                        
                        // Make sure the parents are in there, too.
                        //
                        // Trace recursively up the task hierarchy: for any task we've inserted, if its 
                        // parent is not yet associated with this workOrderId, we insert that as well. 
                        // JM 2020-03-26: this is rather esoteric SQL, but it seems to work, and I am leaving it alone
                        //  at least for now. If someone can write this more cleanly, or work out where Martin got the basis for this
                        //  (I don't believe his SQL skills were such as to write this from scratch), that would be helpful.                        
                            
                        $parentTaskIds = array();
                            
                        // $query = " SELECT T2.taskId, T2.description "; // REPLACED by the following line JM 2020-03-26 because description was never used. 
                        $query = " SELECT T2.taskId ";
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
                        $query .= " ORDER BY T1.lvl DESC ";
                            
                        $result = $db->query($query);
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $parentTaskIds[] = $row['taskId'];
                            }
                        } else {
                            $logger->errorDB('1594234288', 'Hard DB error', $db);
                        }
                        
                        foreach ($parentTaskIds as $parentTaskId) {
                            if ($parentTaskId != $taskId) {                                
                                $exists = false;
                                    
                                $query = "SELECT workOrderTaskId FROM " . DB__NEW_DATABASE . ".workOrderTask ";
                                $query .= "WHERE workOrderId = " . intval($workOrderId) . " ";
                                $query .= "AND taskId = " . intval($parentTaskId) . ";";
                        
                                $result = $db->query($query);
                                if ($result) {
                                    /* BEGIN REPLACED 2020-09-11 JM, addressing http://bt.dev2.ssseng.com/view.php?id=114:
                                       It's not just whether the task is associated with the workOrder, but whether it is
                                       associated with this particular set of workOrderTasks.
                                        
                                    $exists = $result->num_rows > 0;
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
                                    $logger->errorDB('1594234305', 'Hard DB error', $db);
                                }
                                    
                                if (!$exists) {
                                    // JM 2020-03-26 >>>00002 should probably validate $parentTaskId
                                    $parentTask = new Task($parentTaskId); 
                        
                                    // JM 2020-06-15: added insertedPersonId, per http://bt.dev2.ssseng.com/view.php?id=67 (workOrderTask ought to have timestamp)
                                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTask (workOrderId, taskId, " . 
                                             // "viewMode, " . // REMOVED 2020-10-28 JM getting rid of viewmode 
                                             "insertedPersonId) VALUES (";
                                    /* BEGIN REPLACED 2020-07-20 JM: fixing http://bt.dev2.ssseng.com/view.php?id=182
                                    // 2020-07-20 JM: NOTE that $workOrderId was completely missing and we had old variable name $rt for $parentTask  
                                    $query .= ", " . intval($parentTaskId);
                                    $query .= ", " . intval($rt->getViewMode());
                                    $query .= ", " . intval($user->getUserId()) . ");";
                                    // END REPLACED 2020-07-20 JM
                                    */ 
                                    // BEGIN REPLACEMENT 2020-07-20 JM
                                    $query .= " " . intval($workOrderId);
                                    $query .= ", " . intval($parentTaskId);
                                    // $query .= ", " . intval($parentTask->getViewMode()); // REMOVED 2020-10-28 JM getting rid of viewmode
                                    $query .= ", " . intval($user->getUserId()) . ");";
                                    // END REPLACEMENT 2020-07-20 JM
                                    $result = $db->query($query);
                                    if (!$result) {
                                        $logger->errorDB('1594234385', 'Hard DB error', $db);
                                    }
                        
                                    $parentWorkOrderTaskId = $db->insert_id;
                        
                                    if (intval($parentWorkOrderTaskId)) {
                                        $workOrderTaskIds[] = $parentWorkOrderTaskId;
                                    }
                                    unset($parentTask, $parentWorkOrderTaskId);
                                }
                                unset($exists);
                            }
                        } // END foreach ($parentTaskIds
                        unset($parentTaskIds, $parentTaskId, $exists);
    
                        // For each element in $elementIds, if that task is not yet associated with the element, 
                        // we associate the task with the element as well: insert (workOrderTaskId, elementId) 
                        // into DB table WorkOrderTaskElement. 
                        foreach ($elementIds as $elementId) {
                           
                            foreach ($workOrderTaskIds as $workOrderTaskId) {                            
                                $exists = false;                                    
                                $query = "SELECT workOrderTaskElementId FROM " . DB__NEW_DATABASE . ".workOrderTaskElement WHERE workOrderTaskId = " . 
                                         intval($workOrderTaskId) . " AND elementId = " . intval($elementId) . ";";
                        
                                $result = $db->query($query);
                                if ($result) {
                                    $exists = $result->num_rows > 0;
                                } else {
                                    $logger->errorDB('1594234443', 'Hard DB error', $db);
                                }
                                    
                                if (!$exists) {                        
                                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskElement (workOrderTaskId, elementId) VALUES (";
                                    $query .= intval($workOrderTaskId);
                                    $query .= ", " . intval($elementId) . ");";
                                        
                                    $result = $db->query($query);
                                    if (!$result) {
                                        $logger->errorDB('1594234516', 'Hard DB error', $db);
                                    }
                                }
                            }
                            unset($workOrderTaskId, $exists);
                        }
                    }
                    unset($workOrderTaskIds);
                } // >>>00002 else ought to log invalid taskId and what taskPackage this came from 
            }
            unset($taskIds, $taskId);
        } // end error check added JM 2020-03-26
        // Fall through to the main action/display
    } // END if if ($act == 'addpackage')
    // END self-submitted actions
    
    // Build objects for the workOrder & corresponding job
    $workOrder = new WorkOrder($workOrderId); // This should always succeed because we validated $workOrderId above, but >>>00002 we still might
                                              // want to check for hard DB error.
    
    $job = new Job($workOrder->getJobId()); // This should always succeed but >>>00002 we still might want to check for hard DB error.
    
    $tasks = array(); // associative array, see following query for indexes
    
    // >>>00001 JM 2020-07-08: I don't fully understand what the following is about.
    // >>>00026 It is possible that it is totally redundant, and may result in including the same tasks twice (these seem to be covered below). But I don't 
    //  see any indication of such duplication; this may deserve further study.
    /* BEGIN REPLACED 2020-07-08 JM: most of this was totally vestigial stuff.
    $query ="SELECT t.taskId,  t.description, c.taskCategoryId, t.icon, t.tskBillDesc, c.categoryName, t.tskSort, t.sortOrder "; //REWORKED 2020-06-
    $query .= "FROM " . DB__NEW_DATABASE . ".task t JOIN " . DB__NEW_DATABASE . ".taskCategory c ON t.taskCategoryId = c.taskCategoryId ";
    $query .= "WHERE t.active = 1 ORDER BY c.displayorder, t.sortOrder;";
    // END REPLACED 2020-07-08 JM
    */
    // BEGIN REPLACEMENT 2020-07-08 JM
    $query ="SELECT taskId, description ";
    $query .= "FROM " . DB__NEW_DATABASE . ".task ";
    $query .= "WHERE active = 1 ";
    // $query .= "ORDER BY sortOrder;"; // 2020-08-24 JM: per http://bt.dev2.ssseng.com/view.php?id=229, they say they'd rather have this alphabetical
    $query .= "ORDER BY description;";

    
    
    // END REPLACEMENT 2020-07-08 JM
    
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    } else {
        $logger->errorDB('1594232899', 'Hard DB error', $db);
    }
    
    /* BEGIN REMOVED 2020-07-08 JM: $taskArray was never used
    $taskArray = array();
    foreach ($tasks as $task) {
        // (See SELECT query above for what will be in associative array corresponding to $task here) 
        $taskArray[$task['taskCategoryId']][] = $task;
    }
    // END REMOVED 2020-07-08 JM
    */
}    
?>

<style>
    @import url('http://fonts.googleapis.com/css?family=Pacifico|Open+Sans:300,400,600');
    * {
        box-sizing: border-box;
        font-family: 'Open Sans', sans-serif;
        /*font-weight: 300; */
    }
    a {
        text-decoration: none;
        color: inherit;
    }
    p {
        font-size: 1.1em;
        margin: 1em 0;
    }
    .description {
        margin: 1em auto 2.25em;
    }
    body {    
        margin: 1.5em auto;
        color: #333;
    }
    h1 {
        font-weight: 400;
        font-size: 2.5em;
    }
    ul {
        list-style: none;
        padding: 0;
    }
    ul .inner {
        padding-left: 1em;
        overflow: hidden;
        display: none;
    }
    ul .inner.show {
        /*display: block;*/
    }
    ul li {
        margin: .2em 0;
    }
    ul li a.toggle {
        width: 100%;
        display: block;
        background: rgba(0, 0, 0, 0.78);
        color: #fefefe;
        padding: .25em;
        border-radius: 0.15em;
        transition: background .3s ease;
    }
    ul li a.toggle:hover {
        background: rgba(99, 99, 99, 0.9);
    }
</style>

<script>
<?php /* Manipulate the parent window. 
         >>>00014 JM: I haven't seen anything like the latter for any other fancybox code, what's this about? 
         For what it's worth, this fancybox is opened from top-level workorder.php, so that is what we will be 
         sending a 'resize' message, but I (JM 2019-05-06) don't see addEventListener anywhere in that code. */ ?>
window.console = window.console || function(t) {};

//if (document.location.search.match(/type=embed/gi)) {
//    window.parent.postMessage("resize", "*"); <?php /* '*' here means no test for URI of parent */ ?>  
//}
</script>

<?php
    /* INPUT $parentId - 0 means tasks with no parent. Any other number means the 
        children of a particular task.
    
        Recursive code to implement an "accordion" listing all tasks 
        (independent of this particular workOrder, this is "system global"), 
        implemented as a set of nested UL/LI elements. 
        
        At the top level, initially displayed, are tasks with no parent. 
        If a task has any descendants, then it is represented by a link 
        that can be clicked to toggle open or closed a list of its children. 
        "Leaf nodes" are not links.  
         
        (The only action from the accordion is to fill in the second column of the table
         that has the accordion in the first column.)
    */
    function drawLevel($parentId) {
        if ($parentId == 0) {
            $tasks = Task::getZeroLevelTasks(true, true); // true, false -> alphabetical, and limit to active
        } else {
            $parent = new Task($parentId);
            $tasks = $parent->getChilds(true, true); // true, false -> alphabetical, and limit to active
        }        
    
        foreach ($tasks as $task) {
            // Lookahead: does this task have children? In other words, is this task a leaf (no children of its own)?
            $hasSubs = $task->hasChild(true); // true -> limit to active
            $taskId = $task->getTaskId();
    
            echo '<li>' . "\n";
                if ($hasSubs) {                
                    // For non-leaf nodes, we show the task description, followed by the tree of its descendants
                    echo '<a rel="nofollow" rel="noreferrer" class="toggle" id="' . $taskId . '" href="javascript:void(0);">' . 
                         $task->getDescription() . '</a>' . "\n";
                    echo '<ul class="inner">';
                        drawLevel($taskId);
                    echo '</ul>';
            } else {
                // leaf node: just show "description".
                $task->getDescription();
            }    
            echo '</li>' . "\n";
        }
    } // END function drawLevel

/* BEGIN REMOVED JM 2020-03-27 because all the code that worked with this is already commented out
?>
<div id="expanddialog">
</div>
<?php
// END REMOVED JM 2020-03-27
*/
// error handling added 2020-03-26 JM
if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"error\" style=\"color:red\">$error</div>";
} else {
    $query =" SELECT taskId, description";
    $query .= " FROM " . DB__NEW_DATABASE . ".task ";
    $query .= " WHERE taskId IN (SELECT MIN(taskId) FROM " . DB__NEW_DATABASE . ".task GROUP BY description)";
    //$query .= " AND active = 1 AND parentId <> 0 AND taskId <> 21 AND taskId <> 27"; // George 2021-10-06 - we will see about parentId <> 0
    $query .= " AND active = 1 AND parentId <> 0 AND taskId <> 1"; // George 2021-10-06 - we will see about parentId <> 0

    $tasks=$db->query($query)->fetch_all(MYSQLI_ASSOC);
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"error\" style=\"color:red; display:none\"></div>";
    echo '<center>';
        //echo '<button id="change-elements-used">Re-select elements</button></br>'; // ADDED 2020-08-26 JM for http://bt.dev2.ssseng.com/view.php?id=230
        // Calling this TABLE 1 just to have a name for it - JM             
        echo '<table border="0" cellpadding="3" cellspacing="3" width="100%">' . "\n";
            echo '<tr>';        
                echo '<td colspan="3">';
                    // Comma-separated list of the relevant element names.
                    //echo '<h1>' . $nameString . '</h1>';            
                echo '</td>';        
            echo '</tr>' . "\n";
            
            /*echo '<tr>' . "\n";
                // Column 1: (1) "addpackage" form & (2) accordion of tasks that can be used to fill in column 2.
                echo '<td width="33%" valign="top">';
                    // FORM to add a package of tasks as workOrder tasks for all or some of these elements.
                    echo '<form id="addPackageForm" name="addpackage"><input type="hidden" name="act" value="addpackage">' . "\n";
                        foreach ($elementIds as $elementId) {
                            echo '<input type="hidden" name="elementId[]" value="' . intval($elementId) . '">' . "\n";                            
                        }
                        unset($elementId);
                        
                        echo '<input type="hidden" name="workOrderId" value="' . intval($workOrderId) . '">' . "\n";
                        
                        // HTML SELECT "taskPackageId". First option is "-- task package --" with blank value. 
                        //   Then for each taskPackage, display packageName; value is taskPackageId. 
                        echo '<select id="taskPackageId" name="taskPackageId"><option value="0">-- task package --</option>';                    
                            $query = "SELECT taskPackageId, packageName ";
                            $query .= "FROM  " . DB__NEW_DATABASE . ".taskPackage "; 
                            $query .= "ORDER BY taskPackageId;";
                            $result = $db->query($query);
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . intval($row['taskPackageId']) . '">' . htmlspecialchars($row['packageName']) . '</option>';
                                }
                            } else {
                                $logger->errorDB('1594234604', 'Hard DB error', $db);
                            }
                        echo '</select>' . "\n";
                        echo '<input type="submit" id="addPackage" value="add package">' . "\n";
                    echo '</form>' . "\n";
    
                    echo '<hr>';
                    
                    echo '<ul class="accordion">';
                        $parentId = 0; // tasks with no parents
                        drawLevel($parentId);
                    echo '</ul>';        
                echo '</td>' . "\n";
                
                // TABLE "subtable", filled in by function showsub: will show a task and its immediate children
                //  (not deeper descendants).
                echo '<td valign="top" align="left" width="33%" bgcolor="#dddddd">';
                    echo '<table border="0" cellpadding="0" cellspacing="0" id="subtable">';
                    echo '</table>';
                echo '</td>' . "\n";
                
                // TABLE "existingtasks", initially empty. After building the HTML, 
                //  we call function getCurrentTasks, which in turn calls /ajax/existingtasks.php, 
                //  passing it the workOrderId and the elementId array; that fills this in
                //  with all existing tasks for this workOrder.
                echo '<td width="33%" valign="top">';
                    echo '<table border="0" cellpadding="3" cellspacing="0" id="existingtasks" width="100%">';
                    echo '</table>';
                echo '</td>' . "\n";
            echo '</tr>' . "\n"; */
            echo '<tr>';
            echo '<td>';
            ?>
  <!-- Modal -->

  <div class="modal-dialog modal-dialog-centered modal-lg" role="document" style="max-width: 1200px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">WorkOrder Tasks</h5>
        <!--<button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button> -->
      </div>
      <div class="modal-body">
        <div class="container-fluid my-6">
            <div class="row">
                <span class="col-sm-3">Element:</span>
                <span id="elementId" name="elementId" class="form-control col-sm-6">
                    <span value="1"><?=$nameString?></span>
               

                </span>
                <span class="col-sm-3">
                    <button id="change-elements-used" class="btn btn-outline-warning btn-sm">Change Elements</button>
                  
                </span>
            </div>
        </div>
        <div class="card mt-3 pb-2">
            <div class="row">
                <div class="col-sm-6">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Tasks</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Templates</a>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">


                                <div id="tasksList" style="max-height: 90%; overflow-y:scroll;">
                                    <div class="demo-section wide k-content" >
                                        <div class="treeview-flex" >
                                            <div id="treeview-kendo"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; overflow-y:scroll;">
                                <div id="taskTemplateList">
                                    <div class="demo-section wide k-content">
                                        <div class="treeview-flex">
                                            <div id="treeview-telerik"></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#wotasks" role="tab" aria-controls="home" aria-selected="true">WorkOrder Tasks</a>
                        </li>
                        </ul>
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">

                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">
                            <!--<span class="col-sm-3">
                                <button class="btn btn-outline-warning btn-sm mr-3 mt-1 mb-2" id="refreshBtn">Refresh</button>
                            </span>-->
                                        <div id="workOrderTaskList">
                                            <div class="demo-section wide k-content">
                                                <div class="treeview-flex">
                                                    <div id="treeview-telerik-wo"></div>
                                                </div>
                                            </div>
                                        </div>
                            </div>
                            </div>
                        </div>
                </div>
            </div>


        </div>
    </div>

    </div>
  </div>




            <?php
             echo '</td>';
             echo '</tr>';
            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
            //echo '<tr>';        
            //	echo '<td><img src="/cust/' . $customer->getShortName() . '/img/trans_32x32.png" width="200" height="30"></td>';
            //	echo '<td><img src="/cust/' . $customer->getShortName() . '/img/trans_32x32.png" width="200" height="1"></td>';
            //echo '</tr>';
            // END COMMENTED OUT BY MARTIN BEFORE 2019
        
        echo '</table>';
    echo '</center>';
    ?>
    
    <script>    
        // INPUT workOrderTaskId
        // Delete this workOrderTask from the current workOrder.
        // Synchronous AJAX post to /ajax/deleteworkordertask.php;
        //  on AJAX success, doesn't check return, just updates current task
        //  list in HTML with getCurrentTasks(); ignores AJAX failure (>>>00002 should log).
        var deleteWorkOrderTask = function(workOrderTaskId) {
            var html = '';        
            $.ajax({            
                url: '/ajax/deleteworkordertask.php',    
                data: {
                    workOrderTaskId: workOrderTaskId,
                    workOrderId:<?php echo intval($workOrderId); ?>
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {    
                    getCurrentTasks();
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //		alert('error'); // COMMENTED OUT BY MARTIN BEFORE 2019
                }    
            });
        } // END function deleteWorkOrderTask
            
        // INPUT taskId
        // Add a single task to the workOrder
        // Necessarily associates ALL elements of the workOrder.
        // Synchronous AJAX post to /ajax/addworkordertask.php;
        //  on AJAX success, doesn't check return, just updates current task
        //  list in HTML with getCurrentTasks(); ignores AJAX failure (>>>00002 should log).
        var addTask = function(taskId) {
            var html = '';        
            $.ajax({            
                url: '/ajax/addworkordertask.php',    
                data: {
                    taskId: taskId,
                    workOrderId: <?php echo intval($workOrderId); ?>,
                    elementId:'<?php echo implode(",", $elementIds);?>'
                },
                async:false,
                type:'post',    
                success: function(data, textStatus, jqXHR) {
                    getCurrentTasks();
                },    
                error: function(jqXHR, textStatus, errorThrown) {
            //		alert('error');
                }
            });
        } // // END function addTask
        
        // INPUT t - task Id. Parent of tasks to display.
        // This fills in the table "subtable" in the second column of TABLE 1.
        // Synchronous AJAX post to /ajax/taskchilds.php;
        //  for behavior on AJAX success, see remarks inline; 
        //  ignores AJAX failure (>>>00002 should log).
        function showsub(t) {
            var et = document.getElementById('subtable');
            et.innerHTML = ''; // temporarily clear subtable.
        
            var html = '';        
            $.ajax({            
                url: '/ajax/taskchilds.php',
                data: {
                    parentId: escape(t),
                    alphabetical: 'true'
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
                    // There is no status as such in return
                    // Assuming there is data about the task 't' itself, 
                    //  set up a row, spanning both columns, that displays the task description; 
                    //  This is a link to add that task, using its taskId.
                    if (data['task']) {
                        html += '<tr>';
                            html += '<td colspan="2"><a id="addTask' + data['task']['taskId'] + '" href="javascript:addTask(' + data['task']['taskId'] + ')">';
                                html += data['task']['description'];
                            html += '</a></td>';
                        html += '</tr>';                    
                    }
                    // Then do the same for each child; we get an indent by blanking column 1 & putting this in column 2. 
                    if (data['childs']) {    
                        for (var i = 0; i < data['childs'].length; i++) {
                            html += '<tr>';    
                                html += '<td>&nbsp;&nbsp;&nbsp;</td>';
                                html += '<td><a  id="addTask' + data['childs'][i]['taskId'] + '" href="javascript:addTask(' + data['childs'][i]['taskId'] + ');">' + 
                                        data['childs'][i]['description'] + '</a></td>';    
                            html += '</tr>';                        
                        }                    
                        //et.innerHTML = html; // COMMENTED OUT BY MARTIN BEFORE 2019                    
                    }    
                    et.innerHTML = html;                
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //	alert('error'); // COMMENTED OUT BY MARTIN BEFORE 2019
                }
            });
        } // END function showsub
        
        // logError added 2020-07-22 JM
        // Server-side logging of an error
        // INPUT errorId: numeric code, should be unique in our codebase, typically obtained by using 'Unix date +%s'
        // INPUT text: error text to log.
        function logError(errorId, text) {
            $.ajax({           
                url: '/ajax/log.php',
                data: {
                    errorId: errorId,
                    severity: 'error',
                    text: text
                },
                async: true,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
                    if ( ! ('status' in data) || data['status'] != 'success' ) {
                        // >>>00001: do we want to do something other than an alert here?
                        alert('problem 1 trying to log ' + errorId + ': ' + text + ', please inform ' +
                              'administrator or developer.');
                    }
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                        // >>>00001: do we want to do something other than an alert here?
                        alert('problem 2 trying to log ' + errorId + ': ' + text + ', please inform ' +
                              'administrator or developer.');
                }
            });
        }
        
        // Fill in subtable "existingtasks" (TABLE 1, Column 3) with
        // all existing tasks for this workOrder
        // Synchronous AJAX post to /ajax/existingtasks.php;
        //  for behavior on AJAX success, see remarks inline; 
        //  ignores AJAX failure (>>>00002 should log).
        var getCurrentTasks = function() {
            var et = document.getElementById('existingtasks');
            et.innerHTML = '';
            
            $.ajax({            
                url: '/ajax/existingtasks.php',
                data:{
                    workOrderId: <?php echo intval($workOrder->getWorkOrderId()); ?>,
                    elementId: '<?php echo implode(',', $elementIds) ?>',                
                    
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
                    console.log(data);
                    <?php /*
                    // Code can be confusing because we use the word "element" both for an architectural element (building or other
                    //  structure) for an HTML element, and sometimes an array element (value)!
                    // Most array elements data['elementgroups'][i] will represent a particular building or other structure, 
                    //  and the elementName and elementId will be just what one might expect, from DB table element.
                    // We also have the following special cases:
                    //  * elementId == 0, "General", not attached to any architectural element
                    //  * elementId == PHP_INT_MAX, which lumps together ALL data for workOrderTasks that apply to more than one architectural element.
                    //    This last was not dealt with here at all before 2020-07-10; Joe is made it behave somewhat sanely for v2020-3,
                    //    but THIS SHOULD NO LONGER ARISE in v2020-4 or later; it is replaced by...
                    //  * elementId is a comma-separated string (no spaces) of elementIds, so for multi-element workOrderTasks we can
                    //    see exactly what elements they are associated with.
                    //
                    //  As of v2020-4, the array data['elementgroups'] should have only zero or one member. If it has a member, it should
                    //  describe the workOrderTasks that apply to exactly the relevant element or elements.
                    */ ?>
                    if (data['elementgroups']) {
                        var html = '';   
                        var currentLevel=0;                     
                        for (var i = 0; i < data['elementgroups'].length; i++) {
                            <?php /* before 2020-07-14 var elementgroupdata was var elementgroup; the new name parallels /ajax/existingtasks.php. */ ?> 
                            var elementgroupdata = data['elementgroups'][i]; <?php /* normally data about workOrderTasks for single element, but can
                                                                              // also be about "General" (workOrderTasks not associated with any
                                                                              // particular element) or about multiple elements.
                                                                              */ ?>

                            <?php /*
                            // BEGIN ADDED 2020-07-10 JM as part of dealing with http://bt.dev2.ssseng.com/view.php?id=122#c771
                            // for /ajax/existingtasks.php will already have limited the PHP_INT_MAX case to workOrderTasks where at least one of the elements
                            //  for the workOrderTask is among the elements that were passed to it.
                            // BUT beginning in v2020-4, this should never arise.
                            */ ?>
                            var multielementgroup = elementgroupdata['elementId'] == <?= PHP_INT_MAX ?>;
                            if (multielementgroup) {
                                elementgroupdata['elementName'] = 'Multi-element tasks including at least one of the elements above';
                            }
                            <?php /* // END ADDED 2020-07-10 JM */ ?>
                            
                            if (!(elementgroupdata['elementName'])) {
                                elementgroupdata['elementName'] = 'General';
                            }
                            if (!(elementgroupdata['elementName'].length > 0)) {    
                                elementgroupdata['elementName'] = 'General';    
                            }
        
                            html += '<tr>';
                                <?php /* // Span the whole table; display element name (or "General") */ ?> 
                                html += '<td bgcolor="#cccccc" colspan="' + (data['maxlevel'] + 3) + '" ><b>' + elementgroupdata['elementName'] + '</b></td>';
                            html += '</tr>';
        
                            <?php /*
                           (Prior to 2020-07-31 JM elementgroupdata['wotsForDisplay'] was called elementgroupdata[''gold']. 
                            This is NOT the same thing as 'gold' in the return of $workorder->getWorkOrderTasksTree(), 
                            so that was a terrible naming convention. No change comments inline for this change of name.) 

                            elementgroupdata['wotsForDisplay'] is a flat array (small-integer indexes) representing a 
                             pre-order traversal of the workOrderTask hierarchy for this elementgroup, with the tree
                             structure based on the corresponding taskId of the abstract task associated with each workOrderTask.
                            "Fake" nodes fill in for any ancestors that lack an overt workOrderTask.
                            Each element in this array is a further associative array and represents a workOrderTask.
                            The indexes in that associative array are:   
                            * 'type': 'real' or 'fake' ("fake" means faked up by filling in parent/ancestor of a task that is "real" for this workorder)
                            * 'level'
                            * 'data': a further associative array (>>>00001, >>>00014 the following is POSSIBLY INCOMPLETE deserves more study)
                                * if wotsForDisplay['type'] is 'fake':
                                  * ['icon']
                                  * ['description']
                                  * maybe more in some circumstances
                                * if wotsForDisplay['type'] is 'real':
                                  * ['workOrderId']
                                  * ['taskId']
                                  * ['taskStatusId']
                                  * ['workOrderTaskId']
                                  * ['task']['taskId'] - yes, this is redundant to a level up
                                  * ['task']['icon'] - NOTE here and the next, one level deeper than for 'fake'
                                  * ['task']['description']
                                  * ['task']['billingDescription']
                                  * ['task']['estQuantity']
                                  * ['task']['estCost']
                                  * ['task']['taskTypeId']
                                  * ['task']['sortOrder']
                                  * maybe more in some circumstances
                            * 'times': a count of workOrderTaskTime rows. How many separate times some employee has logged time worked
                                 for this workOrder. Always 0 if element is "fake".                            
                            // See also ajax/existingtasks.php for more thorough documentation.
                            */ ?>
                            if (elementgroupdata['wotsForDisplay']) {
                                for (var j = 0; j < elementgroupdata['wotsForDisplay'].length; j++) {
                                    html += '<tr>';
                                        var wotsForDisplay = elementgroupdata['wotsForDisplay'][j];
                                        <?php /* // Column 1: task icon */ ?>
                                        if (wotsForDisplay['type'] == 'real') {
                                            <?php /* 
                                            // explicit workOrderTask
                                            // display task icon
                                            */ ?>
                                            <?php /* Significantly reworked 2020-07-22 JM to address http://bt.dev2.ssseng.com/view.php?id=183,
                                                we had no error-checking here before */ ?>
                                            let icon = 'none.jpg';
                                            if ('icon' in wotsForDisplay['data']['task'] && wotsForDisplay['data']['task']['icon']) {
                                                icon = wotsForDisplay['data']['task']['icon'];
                                            } else if ('taskId' in wotsForDisplay) {                                          
                                                logError('1595439671', 'No icon for "real" task, taskId = ' + wotsForDisplay['taskId'] + ', "' + wotsForDisplay['data']['task']['description'] + '"');
                                            } else {
                                                logError('1595439713', 'No icon and no taskId for "real" task: "' + wotsForDisplay['data']['task']['description'] + '"');
                                            }
                                            html += '<td nowrap><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons_task/' + 
                                                    encodeURIComponent(icon) + '" width="25" height="25" border="0"></td>';
                                        } else {
                                            <?php /*
                                            // implicit: its descendant exists, so we reconstruct it as a "fake" task
                                            // Note that the only difference here from "real" case is how we get the task icon.
                                            */ ?>
                                            <?php /* Significantly reworked 2020-07-22 JM to address http://bt.dev2.ssseng.com/view.php?id=183,
                                                we had no error-checking here before */ ?>
                                            let icon = 'none.jpg';
                                            if (wotsForDisplay['data']['icon']) {
                                                icon = wotsForDisplay['data']['icon']
                                            } else {
                                                logError('1595439723', 'No icon and no taskId for "fake" task: "' + wotsForDisplay['data']['description'] + '"');
                                            }
                                            html += '<td nowrap><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons_task/' + 
                                                    encodeURIComponent(icon) + '" width="25" height="25" border="0"></td>';
                                        }
                                        
                                        <?php /* Blank columns to indent appropriately for hierarchy */ ?>
                                        for (var k = 0; k < wotsForDisplay['level']; k++) {    
                                            html += '<td>&nbsp;&nbsp;&nbsp;</td>';    
                                        }
            
                                        <?php /*
                                        // Span columns as needed so that all levels in the hierarchy end up using
                                        // the same number of columns in the table.
                                        */ ?>
                                        html += '<td colspan="' + (data['maxlevel'] - wotsForDisplay['level'] + 1)  + '" width="100%">';
                                        if (wotsForDisplay['type'] == 'real') {
                                            <?php /* Description, followed by... */ ?>
                                            html += wotsForDisplay['data']['task']['description'];
                                            html += '</td>';
                                            if (wotsForDisplay['times'] == 0) {
                                                <?php /* ... link with icon, to delete task from workOrder */ ?>
                                                console.log(j, elementgroupdata['wotsForDisplay'].length);
                                                if(j>=elementgroupdata['wotsForDisplay'].length-1){
                                                    html += '<td><a id="deleteWoTask'+ wotsForDisplay['data']['workOrderTaskId'] +'" href="javascript:deleteWorkOrderTask(' + wotsForDisplay['data']['workOrderTaskId'] + ');">' +
                                                        '<img src="/cust/<?php echo $customer->getShortName(); ?>' +
                                                        '/img/icons/icon_delete_16x16.png" width="16" height="16" border="0"></a></td>';
                                                } else {
                                                    if(elementgroupdata['wotsForDisplay'][j]['level']>=elementgroupdata['wotsForDisplay'][j+1]['level']){
//console.log(elementgroupdata['wotsForDisplay'][j]['level'], elementgroupdata['wotsForDisplay'][j+1]['level']);
                                                        html += '<td><a id="delWoTask'+ wotsForDisplay['data']['workOrderTaskId'] +'" href="javascript:deleteWorkOrderTask(' + wotsForDisplay['data']['workOrderTaskId'] + ');">' +
                                                            '<img src="/cust/<?php echo $customer->getShortName(); ?>' +
                                                            '/img/icons/icon_delete_16x16.png" width="16" height="16" border="0"></a></td>';
                                                    } else {
                                                        html += '<td></td>';
                                                    }
                                                }
                                            } else {
                                                html += '&nbsp;';
                                            }
                                        } else {
                                            <?php /* // "Fake" => just description */ ?>
                                            html += wotsForDisplay['data']['description'];
                                            html += '</td>';
                                            html += '<td>&nbsp;</td>';                                
                                        }
                                    html += '</tr>';
                                }    
                            }                        
                        }
                        
                        et.innerHTML = html; <?php /* replace content of subtable "existingtasks" */ ?>
                    } <?php /* END data['elementgroups'] */ ?>
                },
                error: function(jqXHR, textStatus, errorThrown) {
            //		alert('error'); // COMMENTED OUT BY MARTIN BEFORE 2019
                }
            });
        } // END function getCurrentTasks
        
        getCurrentTasks();
    
    </script>
        <?php /*stopExecutionOnTimeout was apparently brought in wholesale with the accordion code, probably harmless but not needed. */ ?>
        <script src="//production-assets.codepen.io/assets/common/stopExecutionOnTimeout-b2a7b3fe212eaa732349046d8416e00a9dec26eb7fd347590fbced3ab38af52e.js"></script>
    
        <script>
        // >>>00006 Weird that we can only do this with click: no way to toggle from keyboard.
        $('.toggle').click(function(e) {
            e.preventDefault();
            var $this = $(this);
            showsub($(this).attr('id')); // Regardless of whether we show or hide, fill in table "subtable"
                                         // based on the task we just clicked. This is how the second column gets filled in.
    
            // Handle the accordion toggling
            if ($this.next().hasClass('show')) {
                $this.next().removeClass('show');
                $this.next().slideUp(150);            
            } else {
                $this.parent().parent().find('li .inner').removeClass('show');
                $this.parent().parent().find('li .inner').slideUp(350);
                $this.next().toggleClass('show');
                $this.next().slideToggle(150);
            }
        });
        //# sourceURL=pen.js // Commented out by Martin before 2019
    </script>

<?php 
    /* BEGIN ADDED 2020-08-26 JM to address http://bt.dev2.ssseng.com/view.php?id=230 */ 
    /*  The code here is loosely based on existing code in top-level workorder.php and in /ajax/jobelements.php.
    
        $( ".change-elements-used" ) is the button to change what elements we are working on. 
        The idea here is that when that is clicked we open a dialog just above the button, 
        to allow the user to re-select which elements they want to work on, then reload fb/workordertaskcats.php, 
        in the same frame to work on the new set of elements.
    */
    
    /* HTML form/table: "Which element are you working with?" 
        For 'General' and for each element: 
            * a checkbox with the elementId as value (0 for 'General')
            * element name (or 'General') is a link submitting the elementId and workOrderId to 
              /fb/workordertaskcats.php in an iframe. 
        Submitting the form with the submit button labeled "combine" calls function checkElements, effectively submitting 
         all checked elements to reload /fb/workordertaskcats.php. 
    */   
    $elements = $job->getElements();
    ?>
    <div id="elementdialog" display="none">
        <form name="elementForm" id="elementForm" method="" action="" onSubmit="return checkElements();">
            <input type="hidden" name="workOrderId" value="<?= intval($workOrderId) ?>"> <?php /* hidden workOrderId */ ?> 
            <table border="0" cellpadding="0" cellspacing="0" width="200">
                <tr>
                    <td colspan="2">Which element are you working with?</td>
                </tr>
                <tr>
                    <th colspan="2" id="thElementName">Element Name</th>
                </tr>
                <tr class="spacer"><td></td></tr>
                <tr>
                    <td><input type="checkbox" id="elementId" name="elementId[]" value="0"></td>
                    <?php /*
                    (>>>00001: I (JM) have absolutely no idea why I couldn't effectively do text-decoration or color via the style element above, but I couldn't, so
                    it's directly on the element; no way to confine this to show just on hover).
                    */ ?>
                    <td><a class="one-task-link closelink" style="text-decoration: underline; color: blue" data-elementid="0">General</a></td>
                </tr>
                <?php
                foreach ($elements as $element) {
                ?>
                    <tr>        
                        <td><input type="checkbox" id="elementId<?= $element->getElementId() ?>" name="elementId[]" value="<?= $element->getElementId() ?>"></td>
                        <?php /*
                        (>>>00001: I (JM) have absolutely no idea why I couldn't effectively do text-decoration or color via the style element above, but I couldn't, so
                        it's directly on the element; no way to confine this to show just on hover).
                        */ ?>
                        <td><a id="linkElement<?= $element->getElementId() ?>"  class="one-task-link closelink" style="text-decoration: underline; color: blue" data-elementid="<?= $element->getElementId() ?>"> 
                            <?= $element->getElementName() ?> </a></td>
                    </tr>
                <?php
                }
                ?>
                <tr>
                    <td colspan="2" style="text-align:center"><input type="submit" class="btn btn-warning btn-sm mt-3" id="combineElements" value="combine"></td>
                </tr>
                
                <tr>
                    <td colspan="2" style="text-align:center"><hr></td>  <?php /* just a spacer */ ?>
                </tr>
            </table>
        </form>
    </div>

    <script>
    $(function() {
        $("#elementdialog").show().dialog({
            autoOpen:false, 
            width:400, 
            height:200,
            closeText: ''
            });
        
        $("#change-elements-used").click(function(event) {
            event.preventDefault();
            
            $( "#elementdialog" ).dialog({
                position: { my: "center bottom", at: "center top", of: $(this) },
                open: function(event, ui) {
                    $(".ui-dialog-titlebar-close", ui.dialog | ui ).show();
                    $(".ui-dialog-titlebar", ui.dialog | ui ).show();
                }
            });
            $('#elementdialog').dialog({height:'auto', width:'auto'});            
            $('#elementdialog').dialog("open");
        });
    });
</script>

<style>
.one-task-link {
    cursor: pointer;
    font-weight: 800;
    margin-top:5px;

}
.one-task-link:hover {
    text-decoration: underline; /* Why does this have no effect? (worked around it below) */
}
#combineElements, #thElementName {
    background-color: #ffc107;
    border-color: #c69500;
    color: #fff;
}
.spacer{
    height:10px;
}
.ui-dialog > .ui-widget-header {background: #727272;}
</style>

<script>
<?php /* function checkElements uses /fb/workordertaskcats.php to run in an iframe, 
         adding multiple elements to a workOrder; then reloads the page.  */ ?>
    var checkElements = function() {
        var f = document.getElementById('elementForm');
        var elements = f.elements;
        var c = 0;    
        for (var i = 0, element; element = elements[i++];) {
            if (element.type === "checkbox" && element.checked) {
                c++;
            }        
        }
    
        if (c == 0) {
            alert('check at least one box if you want to combine');
        } else {
            window.location = '?' + $(f).serialize();    
        }
        return false;
    }

    $('#elementdialog.closelink').click(function () {
        $("#elementdialog").dialog("close");
    });
    
    <?php /* 2020-04-21 JM BEGIN CODE in support of fix for http://bt.dev2.ssseng.com/view.php?id=106 */ ?>
    $('.one-task-link').click(function() {
        window.location = '?elementId=' + $(this).data('elementid') + '&workOrderId=<?= $workOrderId ?>'; 
    });
                    
</script>
    
<?php /* END ADDED 2020-08-26 JM to address http://bt.dev2.ssseng.com/view.php?id=230 */ ?>    
    

    <script id="treeview" type="text/kendo-ui-template">

        # if (!item.items && item.spriteCssClass) { #
        #: item.text #
        <span class='k-icon k-i-close kendo-icon'></span>
        # } else if(!item.items && !item.spriteCssClass) { #
        <span class="k-sprite pdf"></span>
        #: item.text #
        <span class='k-icon k-i-close telerik-icon'></span>
        # } else if (item.items && item.spriteCssClass){ #
        #: item.text #
        # } else { #
        <span class="k-sprite folder"></span>
        #: item.text #
        # } #
    </script>

    <script>
        $("#treeview-kendo").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            dataSource: [{
                id: 1, text: "My Documents", expanded: true, spriteCssClass: "rootfolder", items: [
                    {
                        id: 2, text: "Kendo UI Project", expanded: true, spriteCssClass: "folder", items: [
                            { id: 3, text: "about.html", spriteCssClass: "html" },
                            { id: 4, text: "index.html", spriteCssClass: "html" },
                            { id: 5, text: "logo.png", spriteCssClass: "image" }
                        ]
                    },
                    {
                        id: 6, text: "Reports", expanded: true, spriteCssClass: "folder", items: [
                            { id: 7, text: "February.pdf", spriteCssClass: "pdf" },
                            { id: 8, text: "March.pdf", spriteCssClass: "pdf" },
                            { id: 9, text: "April.pdf", spriteCssClass: "pdf" }
                        ]
                    }
                ]
            }],
            dragAndDrop: true,
            checkboxes: {
                checkChildren: true
            },
            loadOnDemand: true
        });

        $("#treeview-telerik").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            dataSource: [{
                id: 1, text: "My Documents", expanded: true, items: [
                    {
                        id: 2, text: "New Web Site", expanded: true, items: [
                            { id: 3, text: "mockup.pdf" },
                            { id: 4, text: "Research.pdf" },
                        ]
                    },
                    {
                        id: 5, text: "Reports", expanded: true, items: [
                            { id: 6, text: "May.pdf" },
                            { id: 7, text: "June.pdf" },
                            { id: 8, text: "July.pdf" }
                        ]
                    }
                ]
            }],
            dragAndDrop: true,
            checkboxes: true,
            loadOnDemand: true
        });
  
    </script>
    <style>
        @media screen and (max-width: 680px) {
            .treeview-flex {
                flex: auto !important;
                width: 100%;
            }
        }

        /*
        #demo-section-title h3 {
            margin-bottom: 2em;
            text-align: center;
        }

        .treeview-flex h4 {
            color: #656565;
            margin-bottom: 1em;
            text-align: center;
        }

        #demo-section-title {
            width: 100%;
            flex: auto;
        }

        .treeview-flex {
            flex: 1;
            -ms-flex: 1 0 auto;
        }

        .k-treeview {
            max-width: 240px;
            margin: 0 auto;
        }



        #treeview-telerik .k-sprite {
            background-image: url("../content/web/treeview/coloricons-sprite.png");
        }

        .demo-section {
            margin-bottom: 5px;
            overflow: auto;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .rootfolder {
            background-position: 0 0;
        }

        .folder {
            background-position: 0 -16px;
        }

        .pdf {
            background-position: 0 -32px;
        }

        .html {
            background-position: 0 -48px;
        }

        .image {
            background-position: 0 -64px;
        }
*/

    </style>

   
<?php
}
    include '../includes/footer_fb.php';
?>



<?php
   
    $allTasks = array();

    $query = " SELECT workOrderTaskId";
    $query .= " FROM  " . DB__NEW_DATABASE . ".workOrderTask ";
    $query .= " WHERE workorderId = " . intval($workOrder->getWorkOrderId()) . ";"; 


    $taskIds = array();            
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $taskIds[] = $row["workOrderTaskId"];
        }
    } else {
        $logger->errorDB('1594234118134', 'Hard DB error', $db);
    }


    foreach ($taskIds as $taskId) {

        $query = " SELECT t.taskId, t.description as text, w.parentTaskId, w.workOrderTaskId ";
        $query .= " FROM  " . DB__NEW_DATABASE . ".task t ";
        $query .= " JOIN   " . DB__NEW_DATABASE . ".workOrderTask w ON w.taskId = t.taskId ";
        $query .= " WHERE w.workOrderTaskId = " . intval($taskId) . " AND t.taskId <> 1 AND w.workOrderId = " . intval($workOrder->getWorkOrderId()) . "";


        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $allTasks[] = $row;
            }
        }
    }

    unset($taskIds, $taskId);
 

    $new = array();
    $allTasks1 = array();
    foreach ($allTasks as $a) {
     
        $new[$a['parentTaskId']][] = $a;
   
    }

    if($allTasks) {
      
        foreach($allTasks as $key=>$value) {
           
  
            if($value["parentTaskId"] == "1000" ) {
                $createAllTasks1 = createTree($new, array($allTasks[$key]));
                $allTasks1[] =  $createAllTasks1[0];
           
            }
           
        }
    }
 
    function createTree(&$list, $parent) {
        $tree = array();
        foreach ($parent as $k=>$l ) {
            if(isset($list[$l['workOrderTaskId']]) ) {
                $l['items'] = createTree($list, $list[$l['workOrderTaskId']]);
            }
      
            $tree[] = $l;
           
        } 
  
        return $tree;
    }

 

//=====================================
    // PACKAGES


    $allPackagesIds = array();
    $allPackages = array();
    $allPackagesNames = array();

    $query = " SELECT taskPackageId, packageName";
    $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackage ";


    $taskIds = array();            
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allPackagesIds[] = $row["taskPackageId"];
            $allPackagesNames[] = $row["packageName"];
        }
    } else {
        $logger->errorDB('1594234143344', 'Hard DB error', $db);
    }


    foreach ($allPackagesIds as $package) {

        $query = " SELECT t.taskId, t.description as text, tp.packageName, tpt.taskPackageTaskId, tpt.parentTaskId, tpt.taskPackageId ";
        $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackageTask tpt ";
        $query .= " JOIN  " . DB__NEW_DATABASE . ".taskPackage tp ON tp.taskPackageId = tpt.taskPackageId ";
        $query .= " JOIN   " . DB__NEW_DATABASE . ".task t ON tpt.taskId = t.taskId ";
          $query .= " WHERE tpt.taskPackageId = " . intval($package) . "";


        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $allPackages[] = $row;
            }
        }
    }

    unset($allPackagesIds, $package);

    $newPack = array();
    $allTasksPack = array();
    foreach ($allPackages as $a) {
        $newPack[$a['parentTaskId']][] = $a;
    
    }
 
    if($allPackages) {
      
        foreach($allPackages as $key=>$value) {

            
            if($value["parentTaskId"] == "1000" ) {
             
                $createAllTasks2 = createTreePack($newPack, array($allPackages[$key]));
            

                $found = false;
                foreach($allTasksPack as $k=>$v) {
                 
                    if($v['taskPackageId'] == $value['taskPackageId']) {
                        $allTasksPack[$k]['items'][] = $createAllTasks2[0];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $node = array();
                    $node['text'] = $value['packageName'];
                    $node['taskPackageId'] = $value['taskPackageId'];
                    $node['items'][] = $createAllTasks2[0];
                    $allTasksPack[] = $node;
                }
            } 
           
        }
    }
 
    function createTreePack(&$listPack, $parent) {
        $tree = array();
        foreach ($parent as $k=>$l ) {
            if(isset($listPack[$l['taskPackageTaskId']]) ) {
                $l['items'] = createTreePack($listPack, $listPack[$l['taskPackageTaskId']]);
            }
      
            $tree[] = $l;
           
        } 
  
        return $tree;
    }


$colors = array('AntiqueWhite', 'Chartreuse',
        'DarkGoldenRod', 'DarkGrey',
        'DarkOrange', 'DarkSalmon', 'DarkSeaGreen', 'Gold',
        'HotPink', 'OrangeRed', 'Plum', 'YellowGreen');

$used = 0;
echo '<style>';
    // This appears to be just about membership in a set.
    foreach ($classes as $ckey => $class) {
        if (count($class) > 1) {
?>
            .workOrderTaskId_<?php echo $ckey; ?>{
            background-color: <?php echo $colors[$used]; ?>;
            }
<?php
            $used++;
        }
    }
echo '</style>';
?>

 
    <script src="../js/kendo.all.min.js"></script>
        <script id="treeview" type="text/kendo-ui-template">

            # if (!item.items && item.spriteCssClass) { #
            #: item.text #
            <span class='k-icon k-i-close kendo-icon'></span>
            # } else if(!item.items && !item.spriteCssClass) { #
            <span class="k-sprite pdf"></span>
            #: item.text #
            <span class='k-icon k-i-close telerik-icon'></span>
            # } else if (item.items && item.spriteCssClass){ #
            #: item.text #
            # } else { #
            <span class="k-sprite folder"></span>
            #: item.text #
            # } #
    </script>

    <!-- Begin Edit Mode popup 3
    <ul id="menuEditMode">
        <li>Edit Node22</li>
    </ul>
    
    <script id="editTemplate" type="text/x-kendo-template"> 
        <label>Text: <input class="k-textbox" value="#= node.text #" /></label>
        <button class="k-button k-primary" look="outline">Save</button>
    </script>-->
    <!-- End Edit Mode -->

    <!-- Begin Edit Mode popup 2-->
    <ul id="menuEditModeTab2">
    <li>Edit Node</li>
    </ul>
    
    <script id="editTemplate2" type="text/x-kendo-template"> 
        <label>Text: <input class="k-textbox" value="#= node.text #" /></label>
        <button class="k-button k-primary" look="outline">Save</button>
    </script>
    <!-- End Edit Mode -->

   

    <script>
    $(document).ready(function() {

 
    // =================== Tasks ========================
    var tasks=<?php echo json_encode($tasks); ?>;
        var items1=[];

        $.each(tasks, function(i,v){
            items1.push({
                id: v.taskId,
                text: v.description,
                sprite: "html"
            })
        })
        
        var items = [
          { text: "assets", sprite: "folder" },
          { text: "index.html", sprite: "html" }
        ];

        var tree1= $("#treeview-kendo").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            dataSource: items1,
            dragAndDrop: true,
            allowAdd: true,
            allowCopy: true,
            drop: onDrop
        });

        tree1.AllowDefaultContextMenu = true;



// ==========================================================
    // tree TaskId's for this Workorder
    // WORKORDER Task Packages
    var allTasks=<?php echo json_encode($allTasks1); ?>;
 
    $("#treeview-telerik-wo").kendoTreeView({
        template: kendo.template($("#treeview").html()),
        dataSource: [ {id: 1000, text: "Workorder Tasks", expanded: true, items: 
                        allTasks,
                    }],

        allowDefaultContextMenu: true,
        allowCopy: true,
        loadOnDemand: false,
        drag: true,
        drop: onDrop2,

    });

  
    var taskTreeWo;
    // Tab 3 Workorder Tasks
    var taskTreeWoAjax = function() {
        $("#treeview-telerik-wo").kendoTreeView().data("kendoTreeView").expand(".k-item");
        $('#treeview-telerik-wo').data('kendoTreeView').destroy(); 
        $.ajax({            
            url: '/ajax/get_wo_tasks.php',
            data: {
                workOrderId: <?php echo intval($workOrderId); ?>,
            },
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
           
                // create Tree and items array
                allTasks = data;

                $("#treeview-telerik-wo").kendoTreeView({
                    template: kendo.template($("#treeview").html()),
                    dataSource: [ {id: 1000, text: "Workorder Tasks", expanded: true, items: 
                        allTasks,
                    }],
                    
                    
                    allowDefaultContextMenu: true,
                    loadOnDemand: false,
                    dragAndDrop: true,
                    allowCopy: true,
                    drop: onDrop2
      
                }).data("kendoTreeView");

            },    
            error: function(jqXHR, textStatus, errorThrown) {
             alert('error');
            }
        });
    }

    taskTreeWoAjax();
    //======================================================= End Tab 3 - AJAX Workorder Tasks 


    
    // Tab2 - AJAX Templates
    // WORKORDER Task Packages 
    var taskPackages=<?php echo json_encode($allTasksPack); ?>;

    // Packages Names for Edit.
    var allPackagesNames=<?php echo json_encode($allPackagesNames); ?>;
    $(allPackagesNames).each(function(i, value) { 
        $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
            $(this).closest("span").addClass("parentFolder");
        });
    });

    var tree2= $("#treeview-telerik").kendoTreeView({
        template: kendo.template($("#treeview").html()),
        dataSource: [ {id: 1000, text: "Templates Packages", expanded: true, items: 
            taskPackages,
            
        }],
        
        dragAndDrop: true,
        allowDefaultContextMenu: true,
        allowAdd: true,
        allowCopy: true,
        drop: onDrop2   // custom function

    });
    tree2.AllowDefaultContextMenu = true;


   
    var taskTreeTemplatesAjax = function() {
        $(allPackagesNames).each(function(i, value) { 

            $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                $(this).closest("span").addClass("parentFolder");
            });
        });

        $("#treeview-telerik").kendoTreeView().data("kendoTreeView").expand(".k-item");
        $('#treeview-telerik').data('kendoTreeView').destroy(); 
        $.ajax({            
            url: '/ajax/get_template_packages.php',
           
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
                // create Tree and items array
                taskPackages = data;
                allPackagesNames = Array();
                $(taskPackages).each(function(i, value) { 
                   
                    allPackagesNames.push(value.text);
                    $("#treeview-telerik .k-group .k-group .k-in:contains(" + value.text + ")").each(function() {
                        $(this).closest("span").addClass("parentFolder");
                    });
                });
           
                $("#treeview-telerik").kendoTreeView({
                    template: kendo.template($("#treeview").html()),
                    dataSource: [ {id: 1000, text: "Templates Packages", expanded: true, items: 
                        taskPackages,
                        
                    }],
                    
                    dragAndDrop: true,
                    allowDefaultContextMenu: true,
                    allowAdd: true,
                    allowCopy: true,
                    drop: onDrop2   // custom function
      
                }).data("kendoTreeView");
               
            },    
            error: function(jqXHR, textStatus, errorThrown) {
             alert('error');
            }
        });
    }

    taskTreeTemplatesAjax();
    // END  TAB 2 - AJAX Templates

    

// ==================================================================================


        
        // Custom function on Drop2 if target exists will make a Copy.
        // FOR TAB 2  - TEMPLATES TASKS -
        function onDrop2(e) {
            e.preventDefault();
            var parentFolderId = "";
            var targetTree = "";
            var taskIdSource = "";
            var sourceItem = "";
            var destinationNode  = "";
            var targetTreeDivId  = "";
            var destinationItemPackageId = "";
            var destinationItem ="";
            var destinationItemID = "";
            var sourceItemTextFolder = "";
            var node = "";
            var targetsRoot = "";
            

            sourceItem = this.dataItem(e.sourceNode).toJSON();

            // Check if we have source Node and is the Root. And deny the action.
            if(sourceItem.id != "" && sourceItem.id == 1000) {
                return false;
            }
            
            var tree = $("#treeview-telerik").data("kendoTreeView");

            destinationNode = $(e.destinationNode); 
            if(destinationNode) {
                destinationItem = tree.dataItem(e.destinationNode);
                if(!destinationItem) { // Check if we have destination Node.
                    return false;
                } 
            }
           
           
            destinationItemPackageId = destinationItem.taskPackageId;
            destinationItemTaskId = destinationItem.taskId;
         

            if(!destinationItemTaskId) {
                parentFolderId = 1000;
            } else {
                parentFolderId = destinationItem.taskPackageTaskId;
            }

            if(sourceItem) {
                 sourceItemTextFolder = sourceItem.text;
                targetTree = destinationNode.closest("[data-role='treeview']").data("kendoTreeView");
                if( destinationNode.closest("[data-role='treeview']")[0].id){
                    targetTreeDivId = destinationNode.closest("[data-role='treeview']")[0].id; // get the TAB id
                }
      
                // George - get the target root. Preventing add before the root!!
                targetsRoot = $(e.dropTarget).parentsUntil(".k-treeview", ".k-item").length == 1;
            }
                

            if( targetTreeDivId == "treeview-telerik" && targetsRoot == true) { // Drop on ROOT
                    function myFunction() {
                    var packageName = "Package Name";
                    packageName = prompt("Please rename the new Template folder as a Package Name", "Package Name");
                        if(packageName != null && !allPackagesNames.includes(packageName)) {
                            return packageName;
                        } else if(packageName != null  && allPackagesNames.includes(packageName) ) {
                            if (!confirm('Please rename before saving. A duplicate exists! If you don\'t rename, your template will not be created!')) {
                                return null;
                            } else {
                                packageName = prompt("Please rename the new Template folder as a New Package Name", "Package Name 2");
                                if(packageName != null && !allPackagesNames.includes(packageName)) {
                                    return packageName;
                                } else {
                                    return null;
                                }
                               
                            }
                        } else {
                            return null;
                        }
                        
                    }
                  
                    event.preventDefault();
                    packageName = myFunction();
                    if(packageName == null) {
                        return;
                    } else {
                        $.ajax({
                            url: '/ajax/add_template_packages.php',
                            data:{
                                packageTasks : sourceItem,
                                packageName : packageName
                            },
                        
                            async: false,
                            type: 'post',  
                            success: function (response) {
                                //test to see if the response is successful...then
                                    
                                if (e.dropPosition == "before" && targetsRoot == false) { // George - not allowed before root
                                    targetTree.insertBefore(sourceItem, destinationNode);

                                    taskTreeTemplatesAjax();
                                    
                                 
                                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                    $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                           
                                    $(allPackagesNames).each(function(i, value) { 
                                       
                                        $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                            $(this).closest("span").addClass("parentFolder");
                                        });
                                    });



                                } else if (e.dropPosition == "after") {
                                    targetTree.insertAfter(sourceItem, destinationNode);

                                    taskTreeTemplatesAjax();
                                 
                                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                    $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                                    $(allPackagesNames).each(function(i, value) { 
                                   
                                        $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                            $(this).closest("span").addClass("parentFolder");
                                        });
                                    });
                                
                                } else {
                                    targetTree.append(sourceItem, destinationNode);

                                    taskTreeTemplatesAjax();
                                   
                                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                    $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                                    $(allPackagesNames).each(function(i, value) { 
                                     
                                        $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                            $(this).closest("span").addClass("parentFolder");
                                        });
                                    });
                                }
                            
                            },
                            error: function (xhr, status, error) {
                            }
                        })
                    }
                 
                
                } else if ( targetTreeDivId == "treeview-telerik" && targetsRoot == false) { // Drop on existing Packages, not on ROOT
                    var packIdfound = "";
                   
                    $.ajax({
                        url: '/ajax/add_template_packages.php',
                        data:{
                            packageTasks : sourceItem,
                            packIdfound : destinationItemPackageId,
                            parentFolderId : parentFolderId
                        },
                        
                        async: false,
                        type: 'post',  
                        success: function (response) {
                            //test to see if the response is successful...then
                                
                            if (e.dropPosition == "before" && targetsRoot == false) { // George - not allowed before Root
                                targetTree.insertBefore(sourceItem, destinationNode);

                                taskTreeTemplatesAjax();
                                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                                $(allPackagesNames).each(function(i, value) { 
            
                                    $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                        $(this).closest("span").addClass("parentFolder");
                                    });
                                });
                            } else if (e.dropPosition == "after") {
                                targetTree.insertAfter(sourceItem, destinationNode);

                                taskTreeTemplatesAjax();
                                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                                $(allPackagesNames).each(function(i, value) { 
                                    $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                        $(this).closest("span").addClass("parentFolder");
                                    });
                                });
                            
                            } else {
                                targetTree.append(sourceItem, destinationNode);

                                taskTreeTemplatesAjax();
                                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                                $(allPackagesNames).each(function(i, value) { 
                                    
                                    $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                        $(this).closest("span").addClass("parentFolder");
                                    });
                                });
                            }
                        
                        },
                        error: function (xhr, status, error) {
                        }
                    })
        
                }
        
            }

        
        
        // Custom function on Drop if target exists will make a Copy.
        // For TASKS - added to Workorder Tasks  
        function onDrop(e) {

            e.preventDefault();
            var parentTaskId = "";
            var targetTree = "";
            var taskIdSource = "";
            var sourceItem = "";

            var destinationNode = $(e.destinationNode); 
            
            sourceItem = this.dataItem(e.sourceNode).toJSON();
            
            
            if(sourceItem) {
                taskIdSource = sourceItem.id; // taskId from first tab (all tasks)
            }
            

            var tree = $("#treeview-telerik-wo").data("kendoTreeView");
            var destinationItem = tree.dataItem(e.destinationNode);
            if(!destinationItem) { // Check if we have destination Node.
                return false;
            } 

            var destinationItemID = destinationItem.id;   // Parent Id


            var destinationItemTaskId = destinationItem.workOrderTaskId;   // Parent Id
            if(destinationItemID) {
                parentTaskId = destinationItemID
            } else if (destinationItemTaskId) {
                parentTaskId = destinationItemTaskId
            } else {
                parentTaskId = 1000;
            }

            if(destinationNode) {
                targetTree = destinationNode.closest("[data-role='treeview']").data("kendoTreeView");
            }

            
            // George - get the target root. Preventing add before the root!!
            var targetsRoot = $(e.dropTarget).parentsUntil(".k-treeview", ".k-item").length == 1;
         
            if(targetTree) {
              
                $.ajax({            
                    url: '/ajax/addworkordertask2.php',
                    data: {
                        taskId:  taskIdSource,
                        parentTaskId:  parentTaskId,
                        workOrderId: <?php echo intval($workOrderId); ?>,
                        elementId:'<?php echo implode(",", $elementIds);?>'
                    },
                    async:false,
                    type:'post',    
                    success: function(data, textStatus, jqXHR) {
                            
                        if (e.dropPosition == "before" && targetsRoot == false) { // George - not allowed before root
                            targetTree.insertBefore(sourceItem, destinationNode);

                            taskTreeWoAjax();
                           
                            $("#treeview-telerik-wo").data("kendoTreeView").dataSource.read();
                            $("#treeview-telerik-wo").data("kendoTreeView").expand(".k-item");
                       
                        } else if (e.dropPosition == "after") {
                            targetTree.insertAfter(sourceItem, destinationNode);
                          
                            taskTreeWoAjax();
                           
                            $("#treeview-telerik-wo").data("kendoTreeView").dataSource.read();
                            $("#treeview-telerik-wo").data("kendoTreeView").expand(".k-item");
                        } else {
                            targetTree.append(sourceItem, destinationNode);

                            taskTreeWoAjax();
                            
                            $("#treeview-telerik-wo").data("kendoTreeView").dataSource.read();
                            $("#treeview-telerik-wo").data("kendoTreeView").expand(".k-item");

                        
                        }
                    },    
                    error: function(jqXHR, textStatus, errorThrown) {
                    //		alert('error');
                    }
                });

             
            }
          
        }


        // Begin Edit entry Tab 2 #treeview-telerik
        var editTemplate2 = kendo.template($("#editTemplate2").html());

        $(allPackagesNames).each(function(i, value) { 
            
       
            $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                $(this).closest("span").addClass("parentFolder");
            });
        });

        $("#menuEditModeTab2").kendoContextMenu({
            target: "#treeview-telerik",
            filter: ".parentFolder",
            
            select: function (e) {
                var node = $("#treeview-telerik").getKendoTreeView().dataItem($(e.target).closest(".k-item"));
               
                var nodeId = node.taskPackageId; // George - get the node taskPackageId on Edit
                if (node.hasChildren) {  // George - only folders
                    // create and open Window
                    $("<div />")
                        .html(editTemplate2({ node: node }))
                        .appendTo("body")
                        .kendoWindow({
                            modal: true,
                            visible: false,
                            deactivate: function () {
                                this.destroy();
                            }
                        })
                        // bind the Save button's click handler
                        .on("click", ".k-primary", function (e) {
                            e.preventDefault();

                            var dialog = $(e.currentTarget).closest("[data-role=window]").getKendoWindow();
                            var textbox = dialog.element.find(".k-textbox");
                        
                            node.set("text", textbox.val());
                            var packageNameUpdate = node.text;
                    
                            dialog.close(

                                $.ajax({
                                    url: '/ajax/update_task_package_name.php',
                                    data: {
                                        nodeId : nodeId,
                                        packageNameUpdate : packageNameUpdate
                                    },
                                    async:false,
                                    type:'post', 
                                    success: function (data, textStatus, jqXHR) {

                                        taskTreeTemplatesAjax();
                                        $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                        $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                                        $(allPackagesNames).each(function(i, value) { 
                                            $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                                                $(this).closest("span").addClass("parentFolder");
                                            });
                                        });
                                    },
                                        error: function (xhr, status, error) {
                                        //error
                                        }
                                    })
                                );
                        })
                        .getKendoWindow().center().open();
                }
            }
        });
        // End Edit Tab 2 entry

     // Delete entry on Tab 3 WORKORDER TASKS. Confirmation Message
     $(document).on("click", "#treeview-telerik-wo .telerik-icon", function (e) {
        e.preventDefault();

        var treeview = $("#treeview-telerik-wo").data("kendoTreeView");
        var $this = this;
        var node = $("#treeview-telerik-wo").getKendoTreeView().dataItem($(e.target).closest(".k-item"));

        var workOrderTaskId = node.workOrderTaskId; // George - get the workOrderTaskId we want to delete.

        if (!confirm('Are you sure to Delete this entry?')) {
            return;
            event.preventDefault(); 
        } else {
            $.ajax({            
                url: '/ajax/deleteworkordertask.php',
                data: {
                    workOrderTaskId: workOrderTaskId,
                    workOrderId:<?php echo intval($workOrderId); ?>
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
                
                    treeview.remove($($this).closest(".k-item"));
                    taskTreeWoAjax();
                            
                    $("#treeview-telerik-wo").data("kendoTreeView").dataSource.read();
                    $("#treeview-telerik-wo").data("kendoTreeView").expand(".k-item");
  
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //		alert('error');
                }    
            });
           
        }
    });

    // Delete entry on Tab 2 | Templates. Confirmation Message.
    $(document).on("click", "#treeview-telerik .telerik-icon", function (e) {
        e.preventDefault();
        var treeview = $("#treeview-telerik").data("kendoTreeView");

        var $this = this;
        var node = $("#treeview-telerik").getKendoTreeView().dataItem($(e.target).closest(".k-item"));
        var taskPackageId = node.taskPackageId;
        var taskPackageTaskId = node.taskPackageTaskId; // George - get the TASK ID we want to delete.

        if (!confirm('Are you sure to Delete this entry?')) {
            return;
            event.preventDefault(); 
        } else {

            $.ajax({            
                url: '/ajax/delete_package_tasks.php',
                data: {
                    taskPackageTaskId: taskPackageTaskId,
                    taskPackageId: taskPackageId
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
                
                    treeview.remove($($this).closest(".k-item"));
                    taskTreeTemplatesAjax();
                            
                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                    $("#treeview-telerik").data("kendoTreeView").expand(".k-item");

                    $(allPackagesNames).each(function(i, value) { 
                        $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                            $(this).closest("span").addClass("parentFolder");
                        });
                    });
  
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //		alert('error'); 
                }    
            });

        }
    });

});



// George 2021-05-25. We used this because bootstrap overrides jquery
//  and the close icon X from dialog doesn't show properly. Also in Dialog we add the property: closeText: ''
$.fn.bootstrapBtn = $.fn.button.noConflict();

</script>

<style>
    @media screen and (max-width: 680px) {
        .treeview-flex {
            flex: auto !important;
            width: 100%;
        }
    }
    #treeview .k-sprite {
        /*background-image: url("https://demos.telerik.com/kendo-ui/content/web/treeview/coloricons-sprite.png"); */
    }

    .folder { background-position: 0 -16px; }
    .html { background-position: 0 -48px; }
    body {
        background-image: url(""); 
    }
    .fancybox-wrap .fancybox-desktop .fancybox-type-iframe .fancybox-opened {
        left: 100px!important;
    }

    /* Changes on popup Edit Mode */
    .k-button-primary, .k-button.k-primary {
        color: #fff;
        background-color: #3fb54b;
        font-size: 12px;
    }
    .k-window-titlebar { 
        padding: 8px 6px;
    }
    /* End changes on popup Edit Mode */
    .telerik-icon {
        margin-left: 5px;
    }
    #treeview-kendo > ul > li > div > span > span.k-icon.k-i-close.telerik-icon {
        display:none!important;
    }


</style>
