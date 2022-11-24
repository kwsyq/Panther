<?php
/*  _admin/newtasks/usedinjobs.php

    EXECUTIVE SUMMARY: report on which jobs currently use this task.

    INPUT $_REQUEST['taskId'] - primary key into DB table task
    
*/

$nested = isset($inside_admin_newtasks_edit_php) && $inside_admin_newtasks_edit_php;

if (!$nested) {
    require_once '../../inc/config.php';
    require_once 'gettaskidfromrequest.php';
    include_once '../../includes/header_admin.php';
    require_once 'rightframetabbing.php';
    
    list($taskId, $error) = getTaskIdFromRequest(__FILE__, true); // true here indicates that a zeroed $taskId is OK
    createNewtasksTabs('usedinjobs.php', $taskId);    
} else { 
    $error = '';
}

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    if (!$nested) {
        echo "<h2>Edit</h2>\n";
    }
    $task = new Task($taskId);
    // Apparently, looking at the DB, as of 2020-11, there ARE workOrderTasks with wot.taskId=0
    // They all date from 2014, and they are probably erroneous.
    // In any case, we proceed to this part whether there is a nonzero taskId or not...
    
    $db = DB::getInstance();    
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".workOrderTask wot ";
    $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrder wo ON wot.workOrderId = wo.workOrderId ";
    $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".job j ON wo.jobId = j.jobId ";
    $query .= "WHERE wot.taskId = " . intval($taskId) . " ";
    $query .= "GROUP BY j.jobId ORDER BY j.jobId desc ";
    
    $jobs = array();
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;	
        }
    } else {
        $logger->errorDb('1604650293', 'Hard DB error', $db);
    }
    
    if (!Task::validate($taskId)) {
        echo '<b>Invalid taskId ' . $taskId . ' is used in:</b>' . '<p>' . "\n";
    } else {            
        echo '<b>[' . $task->getDescription() . '][' . intval($task->getTaskId()) . '] is used in:</b>' . '<p>' . "\n";
    }
    
    // A table of links to jobs that reference this task.
    // Each link is labeled with Job Number, and opens the Job page (not part of the Admin system) in a new tab/window.
    // echo '<table border="0" cellpadding="0" cellspacing="0">';
    echo '<table border="0" cellpadding="0" cellspacing="0">';
        foreach ($jobs as $job) {
            $j = new Job($job['jobId']);            
            echo '<tr>';            
                echo '<td><a target="_blank" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>';
                echo '<td style="padding-left:10px">' . $j->getName() . '</td>';            
            echo '</tr>';
        }
    echo '</table>';
}

if (!$nested) {
    include_once '../../includes/footer_admin.php';
}
unset($nested); 
?>