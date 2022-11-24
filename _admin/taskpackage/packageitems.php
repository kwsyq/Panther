<?php 
/*  _admin/taskpackage/packageitems.php

    EXECUTIVE SUMMARY: PAGE to displays tasks in a package. Allows deletion of tasks from the package. 
    Also contains code for addition of tasks to the package, but that is called from elsewhere.

    PRIMARY INPUT: $_REQUEST['taskPackageId'].

    Other INPUT: Optional $_REQUEST['act'], possible values are:
      * 'deltask' takes additional input:
          * $_REQUEST['taskPackageTaskId']
      * 'addtask' takes additional input:
          * $_REQUEST['taskId'].
*/

include '../../inc/config.php';
?>

<html>
<head>
</head>
<body bgcolor="#eeeeee">
    <?php 
    $db = DB::getInstance();
    if ($act == 'deltask') {
        // Delete a row from DB table taskPackageTask per $_REQUEST['taskPackageTaskId'], then redisplay.
        // Ignores $_REQUEST['taskPackageId'] (>>>00016 probably should check it for validation).        

        $taskPackageTaskId = isset($_REQUEST['taskPackageTaskId']) ? intval($_REQUEST['taskPackageTaskId']) : 0;
    
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".taskPackageTask ";
        $query .= "WHERE taskPackageTaskId = " . intval($taskPackageTaskId) . ";";
        
        $result = $db->query($query); // >>>00002 ignores failure on DB query!
        
        // Fall through to display, $_REQUEST['taskPackageId'] matters here.
    }
    
    if ($act == 'addtask') {
        // Insert into DB table taskPackageTask using the values $_REQUEST['taskPackageTaskId'] and $_REQUEST['taskId'], then redisplay.
        
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        //$modifier = isset($_REQUEST['modifier']) ? $_REQUEST['modifier'] : '';
        //$note = isset($_REQUEST['note']) ? $_REQUEST['note'] : '';
        // END COMMENTED OUT BY MARTIN BEFORE 2019
    
        $taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;	
        $taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;
        
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019    
        //	$modifier = trim($modifier);	
        //	$modifier = substr($modifier, 0, 32);
        
        //	$note = trim($note);
        //	$note = substr($note, 0, 64);
        // END COMMENTED OUT BY MARTIN BEFORE 2019
    
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".taskPackageTask ";
        $query .= "(taskPackageId,taskId) VALUES (";
        $query .= intval($taskPackageId);
        $query .= ", " . intval($taskId);
        $query .= ")";
        
        $result = $db->query($query);  // >>>00002 ignores failure on DB query!
    }
    
    /* BEGIN REMOVED 2020-01-06 JM; no idea why this was ever in this particular file, probably accidental copy-paste. - JM
    // Recursive function to count the elements (in the Panther sense: for example, a building) in a hierarchy of arrays.
    //  Each array in the hierarchy can be a mix of elements (in the computer science sense)
    //  which each represent either a single element (in the Panther sense) or another
    //  potentially mixed array (elements in the Panther sense + arrays).
    // INPUT $array - top level of what is to be counted
    // INPUT $counter - counter to increment by number of elements (in the Panther sense) encountered.
    // RETURN $counter incremented by the number of elements (in the Panther sense) encountered.
    //  Because elements (in the computer sense) of $array can, themselves be arrays of elements (in the Panther sense),
    //  this increment may exceed count($array).
    function counter($array, $counter) {    
        foreach ($array as $akey => $element) {    
            if (is_array($element) && (!array_key_exists('elementTypeId', $element))) {
                $counter = counter($element, $counter);
            } else {
                // $element is an element in the Panther sense.
                $counter++;
            }    
        }    
        return $counter;
    }
    // END REMOVED 2020-01-06 JM
    */
    
    /*
    OLD CODE removed 2019-02-06 JM
    $sep = DIRECTORY_SEPARATOR;
    */
    
    $subs = array();    
    $taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;
    
    /* BEGIN REPLACED 2020-07-03 JM
    // SELECT * on multiple tables in a JOIN is not great practice: they coincide on at least
    //  one column name (the one they are joined on) and it's really a quirk that MySQL allows this.
    //  Should clean this up to specific columns we care about.
    // Really could be just 'select tp.packageName, t.taskId, t.description, tpt.taskPackageTaskId'
    $query = " select * ";
    $query .= " from  " . DB__NEW_DATABASE . ".taskPackage tp ";
    $query .= " join  " . DB__NEW_DATABASE . ".taskPackageTask tpt on tp.taskPackageId = tpt.taskPackageId ";
    $query .= " join  " . DB__NEW_DATABASE . ".task t on tpt.taskId = t.taskId ";
    $query .= " where tp.taskPackageId = " . intval($taskPackageId) . " ";
    // END REPLACED 2020-07-03 JM
    */
    // BEGIN REPLACEMENT 2020-07-03 JM
    $query = "SELECT tp.packageName, t.taskId, t.description, tpt.taskPackageTaskId ";
    $query .= "FROM  " . DB__NEW_DATABASE . ".taskPackage tp ";
    $query .= "JOIN  " . DB__NEW_DATABASE . ".taskPackageTask tpt ON tp.taskPackageId = tpt.taskPackageId ";
    $query .= "JOIN  " . DB__NEW_DATABASE . ".task t ON tpt.taskId = t.taskId ";
    $query .= "WHERE tp.taskPackageId = " . intval($taskPackageId) . ";";
    // END REPLACEMENT 2020-07-03 JM
    
    $tasks = array();
    
    $packageName = '';
    $result = $db->query($query);
    if ($result) {
        // if ($result->num_rows > 0) { // REMOVED 2020-07-03 JM
            while ($row = $result->fetch_assoc()) {
                $packageName = $row['packageName']; // OK that we keep re-setting this, because they should all be in the same package.
                $tasks[] = $row;
            }
        // } // REMOVED 2020-07-03 JM
    } // >>>00002 else ignores failure on DB query!
    
    echo '<h2>' . $packageName . '</h2>';  // packageName as a header
    
    // Table (no headings) where each row corresponds to a task in the package. Columns are:
    // * taskId
    // * tskDsc
    // * link labeled '[del]', linked to do a self-submit by the GET method & remove this task from the package. 
    echo '<table border="1" cellpadding="2" cellspacing="0">';    
        foreach ($tasks as $tkey => $task) {
            echo '<tr>';
                echo '<td>' . $task['taskId'] . '</td>';
                /* BEGIN REPLACED 2020-06-15 JM per http://bt.dev2.ssseng.com/view.php?id=163 (Lose the useless distinction between task.tskDesc and task.description)
                echo '<td>' . $task['tskDesc'] . '</td>';
                // END REPLACED 2020-06-15 JM
                */
                // BEGIN REPLACEMENT 2020-06-15 JM
                echo '<td>' . $task['description'] . '</td>';
                // END REPLACEMENT 2020-06-15 JM
                echo '<td>[<a href="packageitems.php?act=deltask&taskPackageId=' . intval($taskPackageId) . 
                     '&taskPackageTaskId=' . intval($task['taskPackageTaskId']) . '">del</a>]</td>' . "\n";
            echo '</tr>';            
        }    
    echo '</table>';    
    ?>
</body>
</html>