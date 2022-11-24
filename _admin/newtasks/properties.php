<?php 
/*  _admin/newtasks/properties.php

    EXECUTIVE SUMMARY: Edit task properties.

    INPUT $_REQUEST['taskId'] - primary key into DB table task
    
    NOTE: Besides being moved into a separate file, this was rewritten 2020-11 JM to use AJAX rather than self-submit.
*/

$nested = isset($inside_admin_newtasks_edit_php) && $inside_admin_newtasks_edit_php;

if (!$nested) {
    require_once '../../inc/config.php';
    require_once 'gettaskidfromrequest.php';
    include_once '../../includes/header_admin.php';
    require_once 'rightframetabbing.php';
    
    list($taskId, $error) = getTaskIdFromRequest(__FILE__);
    createNewtasksTabs('properties.php', $taskId);
} else if (!$taskId) {
    $error = "_admin/newtasks/properties.php called nested without \$taskId";
    $logger->error2('1604686046', $error); 
} else { 
    $error = '';
}


if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    $task = new Task($taskId);
    if (!$nested) {
        echo "<h2>Edit</h2>\n";
        // pseudo-header, bolded: [task description][taskId]
        echo '<b>[' . $task->getDescription() . '][' . intval($task->getTaskId()) . ']</b>' . '<p>';
    }
    $taskTypes = getTaskTypes();
    $taskIsActive = $task->getActive();
    $hasChildren = $task->hasChild(false);       // false => do not limit to active
    $hasActiveChildren = $task->hasChild(true);  // true=> limit to active
    $color = ($taskIsActive) ? '#6cc417' : '#c24641'; // dull green background for active tasks, dull red for inactive. 
    
    ?>
        <style type="text/css">
            form[name="task"], form[name="task"] th, form[name="task"] td {
                border: 0
            }
            
            .forminput {
                width: 100%;
            }
            .formspan {
                display: block;
                overflow: hidden;
                padding-right:10px;
            }
        </style>
    <?php    

    // Form largely implemented as a table. Most of this should be pretty self-explantory, since 
    //  left column is basically like headers even if it is TD rather than TH.
    // separate form to add a child task; rewritten 2020-11-04 JM to use AJAX rather than self-submit
    echo '<form name="updatetaskform">';
        // echo '<input type="hidden" name="taskId" value="' . $task->getTaskId() . '">'; // REMOVED 2020-11-04 JM, not needed with AJAX approach 
        // echo '<input type="hidden" name="act" value="updatetask">';  // REMOVED 2020-11-04 JM because we are switching to AJAX
        echo '<table width="100%" border="1" cellpadding="1" cellspacing="0">';
            echo '<tr bgcolor="' . $color . '">';
                echo '<td nowrap>Description</td>';
                
                echo '<td><span class="formspan"><input class="forminput" type="text" ' .
                     'name="description" value="' . htmlspecialchars($task->getDescription()) . '"></span></td>';
                // Per-task icon
                $icon = $task->getIcon();
                if ($icon) {
                    echo '<td rowspan="6" align="center"><img style="max-width:100px; max-height:100px" src="' . getFullPathnameOfTaskIcon($icon, '1595357672') . '"></td>';
                } else {
                    echo '<td rowspan="6">&nbsp;(No icon)</td>';
                }
                unset($icon);
            echo '</tr>';
            echo '<tr bgcolor="' . $color . '">';
                echo '<td nowrap>Bill Description</td>';
                echo '<td><span class="formspan"><input class="forminput" type="text" ' . 
                     'name="billingDescription" value="' . htmlspecialchars($task->getBillingDescription()) . '"></span></td>';
            echo '</tr>';               
            
            echo '<tr bgcolor="' . $color . '">';
                echo '<td nowrap>Task Type</td>';
                // HTML SELECT, option reflecting current value is initially selected; blank option at top with value="0" 
                echo '<td><select name="taskTypeId"><option value="0"></option>';
                    foreach ($taskTypes as $taskType) {                            
                        $selected = ($taskType['taskTypeId'] == $task->getTaskTypeId()) ? ' selected ' : '';                            
                        echo '<option value="' . $taskType['taskTypeId'] . '" ' . $selected . '>' . $taskType['typeName'] . '</option>';                            
                    }
                echo '</select></td>';
            echo '</tr>';
            
            echo '<tr bgcolor="' . $color . '">';
                echo '<td nowrap>Active</td>'; // Boolean, checkbox
                $checked = $taskIsActive ? ' checked ' : '';
                echo '<td><input type="checkbox" name="active" value="1" ' . $checked . '>&nbsp;&nbsp;&nbsp;';
                if ($taskIsActive) {
                    $hasInactiveAncestor = false;
                    $ancestorTasks = $task->climbTree(); // array from root to task, including the task itself
                    array_pop($ancestorTasks);           // remove the task itself from the array
                    foreach ($ancestorTasks as $ancestorTask) {
                        if (!$ancestorTask->getActive()) {
                            $hasInactiveAncestor = true;
                            break;
                        }
                    }
                }
                if ($taskIsActive && $hasInactiveAncestor) {
                    echo 'This active task has an inactive ancestor.';
                } else if ($hasChildren) {
                    if ($taskIsActive && $hasActiveChildren) {
                        echo 'Deactivating this task will also deactivate child tasks.';
                    } else if (!$taskIsActive && $hasActiveChildren) {
                        echo 'Activating this task will also activate at least one child task.';
                    }
                } else {
                    echo 'This task has no children';
                }
                echo '</td>';
            echo '</tr>'; // added JM 2019-12-09, pretty obviously should be here.                
            
            echo '<tr bgcolor="' . $color . '">';
                echo '<td nowrap>WikiLink</td>';
                echo '<td><input type="text" name="wikiLink" value="' . htmlspecialchars($task->getWikiLink()) . 
                     '" size="35" maxlength="512">[<a target="_blank" href="' . htmlspecialchars($task->getWikiLink()) . '">look</a>]';
                echo '</td>';
            echo '</tr>';	

            echo '<tr bgcolor="' . $color . '">';
                // Submit button, labeled "update"
                echo '<td colspan="2" align="center"><input type="submit" value="update"></td>';
            echo '</tr>';
        echo '</table>';
    echo '</form>';
    ?>
    <script>
    $('form[name="updatetaskform"]').on('submit', function(ev) {
        var $this = $(this);
        ev.preventDefault();
        
        // >>>00002: could use some client-side validation here before calling AJAX
        $.ajax({
            url: '../ajax/updatetask.php',
            data: {
                taskId: <?= intval($taskId); ?>,
                description: $('form[name="updatetaskform"] input[name="description"]').val(),
                billingDescription: $('form[name="updatetaskform"] input[name="billingDescription"]').val(),
                taskTypeId: $('form[name="updatetaskform"] select[name="taskTypeId"] option:checked').val(),
                active: $('form[name="updatetaskform"] input[name="active"]').is(':checked') ? 1 : 0,
                wikiLink: $('form[name="updatetaskform"] input[name="wikiLink"]').val()                
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        location.reload();
                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error no \'status\' in data returned from _admin/ajax/updatetask.php.\n' + 
                        'Typically this means that you are logged in as admin, but not as a user.\n' +
                        'Log in to <?= REQUEST_SCHEME . '://' . HTTP_HOST ?>/panther.php (in a different tab), then try the action here again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to _admin/ajax/addtask.php');
            }
        });    
    });        
    </script>
<?php
}

if (!$nested) {
    include_once '../../includes/footer_admin.php';
}
unset($nested); 
?>