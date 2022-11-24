<?php 
/*  _admin/newtasks/changeorder.php

    EXECUTIVE SUMMARY: change where a task falls in the order of tasks under a given parent.

    INPUT $_REQUEST['taskId'] - primary key into DB table task
*/

$nested = isset($inside_admin_newtasks_edit_php) && $inside_admin_newtasks_edit_php;

if (!$nested) {
    require_once '../../inc/config.php';
    require_once 'gettaskidfromrequest.php';
    include_once '../../includes/header_admin.php';
    require_once 'rightframetabbing.php';
    
    list($taskId, $error) = getTaskIdFromRequest(__FILE__);
    createNewtasksTabs('changeorder.php', $taskId);
} else if (!$taskId) {
    $error = "_admin/newtasks/changeorder.php called nested without \$taskId";
    $logger->error2('1604959295', $error); 
} else { 
    $error = '';
}
?>
<style>
#tasklist tr {
    padding-top:2px;
    padding-bottom:2px;
    border-top: 0px;
    border-bottom: 0px;
}
#tasklist.selecting tr.currentHover.above {
    padding-top: 1px;
    border-top: 1px solid red;
    cursor: pointer;
}
#tasklist.selecting tr.currentHover.below {
    padding-bottom: 1px;
    border-bottom: 1px solid red;
    cursor: pointer;
}
#tasklist.selected tr.selected.above {
    padding-top: 0px;
    border-top: 2px solid red;
}
#tasklist.selected tr.selected.below {
    padding-bottom: 0px;
    border-bottom: 2px solid red;
}
#tasklist tr.match {
    font-weight: bold;
}
#tasklist td {
    background-color: #d04d48; /* Lighter than in accordion so red border is visible */
}
#tasklist tr.active td {
    background-color: #6cc417;
}
</style>
<?php
if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    if (!$nested) {
        echo "<h2>Edit</h2>\n";
    }
    $task = new Task($taskId);
    echo '<b>Sort order for [' . $task->getDescription() . '][' . intval($task->getTaskId()) . ']</b>' . '<p>';
    
    // Slightly roundabout way to get own parent task:
    $parentId = $task->getParentId();    
    
    $siblings = array(); // sibling tasks including self
    if ($parentId) {
        $parentTask = new Task($parentId);
        $siblings = $parentTask->getChilds(false, false); // false, false => use sortOrder, include inactive 
    } else {
        // 0-level
        $siblings = Task::getZeroLevelTasks(false, false); // false, false => use sortOrder, include inactive
    }
    echo '<table id="tasklist" class="selecting"><tbody>';
    $belowMatch = false;
    foreach ($siblings as $sibling) {
        $match = $sibling->getTaskId() == $taskId;
        $active = $sibling->getActive();
        $class = ($match ? 'match' : ($belowMatch ? 'below' : 'above')) . ($active ? ' active' : '');  
        echo '<tr class="'. $class . '" data-taskid="' . $sibling->getTaskId() . '"><td>';
        echo $sibling->getDescription();
        echo '</td></tr>' . "\n";
        if ($match) {
            $belowMatch = true;
        }
    }
    echo '</tbody></table>';
}

?>
<script>
// Building our own hover mechanism because the built-in ones are suprisingly weak for TR hover
// All events are delegated: we need that dynamism
$(function () {
    $('body').on('mousenter, mousemove', '#tasklist.selecting tr', function() {
        let $this = $(this);
        $('*').not($this).removeClass('currentHover'); // extra assurance
        $this.addClass('currentHover');
    });
    $('body').on('mouseleave', '#tasklist.selecting tr', function() {
        let $this = $(this);
        $this.removeClass('currentHover');
    });
    $('body').on('click', '#tasklist.selecting tr.above, #tasklist.selecting tr.below', function() {
        let $this = $(this);
        $this.addClass('selected');
        $('#tasklist').removeClass('selecting').addClass('selected');
        $('#tasklist tr.currentHover').removeClass('currentHover');
        $this.find('td').append('<span id="buttons">&nbsp;<button id="button-move">Move here</button><button id="button-unlock">Unlock</button></span>');
        $('*').not($this).removeClass('currentHover'); // extra assurance
    });
    $('body').on('click', '#button-unlock', function() {
        let $this = $(this);
        $('#buttons').remove();
        $('*').removeClass('selected');
        $('#tasklist').addClass('selecting');
        $this.closest('tr').addClass('currentHover');
    });
    $('body').on('click', '#button-move', function() {
        let $this = $(this);
        let $tr = $this.closest('tr'); 
        let data = {
            parentId: <?= $parentId ?>,
            taskId: <?= $taskId ?>,
            beforeOrAfter: $tr.hasClass('above') ? 'before' : 'after',
            relativeToTask: $tr.data('taskid')
        }
        $.ajax({
            url: '../ajax/changetasksortorder.php',
            data: data,
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        location.href = '?taskId=' + <?= $taskId ?>; // leave the rest of the URL implicit so this works OK on PHP inclusions.
                        parent.frames['acordion'].location.href = 'acordion.php?taskId=' + <?= $taskId ?>; // reload left frame
                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error no \'status\' in data returned from _admin/ajax/changetasksortorder.php.\n' + 
                        'Typically this means that you are logged in as admin, but not as a user.\n' +
                        'Log in to <?= REQUEST_SCHEME . '://' . HTTP_HOST ?>/panther.php (in a different tab), then try the action here again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to _admin/ajax/changetasksortorder.php');
            }
        });    
    });
})
</script>
<?php

if (!$nested) {
    include_once '../../includes/footer_admin.php';
}
unset($nested); 
?>