<?php
/*   _admin/newtasks/edit.php

    EDIT SUMMARY: PAGE to view/edit a particular task.

    PRIMARY INPUT: $_REQUEST['taskId']: primary key to DB table Task.

   Significantly reworked JM 2020-11, including that all self-submission is replaced by AJAX.         
*/

$inside_admin_newtasks_edit_php = true; // sets a context for certain included files so they know they don't need headers, etc.

require_once '../../inc/config.php';
require_once 'gettaskidfromrequest.php';
include_once '../../includes/header_admin.php';
require_once 'rightframetabbing.php';


list($taskId, $error) = getTaskIdFromRequest(__FILE__, true); // 'true' here indicates we don't consider a zeroed taskId an error.

createNewtasksTabs('edit.php', $taskId);

echo "<h2>Edit</h2>\n";
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
if ($taskId && !$error) {
    $task = new Task($taskId);
    echo '<b>[' . $task->getDescription() . '][' . intval($task->getTaskId()) . ']</b>' . '<p>' . "\n";
    
    include_once 'properties.php';
    echo '<hr>';
    
    include_once 'addchildtask.php';
    echo '<hr>';
    
    include_once 'manageDetails.php';
    
    echo '<p></p>';
    echo '<p></p>';
    echo '<p></p>';
    
    echo '<hr>';
    
    include_once 'icon.php';
    echo '<hr>';
} // END if ($taskId && !$error)

if (!$error) {
    include_once 'usedinjobs.php';
    echo '<hr>';
}

if ($taskId && !$error) {
    include_once 'changeorder.php';
}
    
include_once '../../includes/footer_admin.php';
?>
