<?php 
/*  _admin/newtasks/addchildtask.php

    EXECUTIVE SUMMARY: Form & code to add a child task of task $taskId.

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
    createNewtasksTabs('addchildtask.php', $taskId);
} else if (!$taskId) {
    $error = "_admin/newtasks/addchildtask.php called nested without \$taskId";
    $logger->error2('1604652952', $error); 
} else { 
    $error = '';
}    

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    $task = new Task($taskId);
    if (!$nested) {
        echo "<h2>Edit</h2>\n";
        echo '<b>[' . $task->getDescription() . '][' . intval($task->getTaskId()) . ']</b>' . '<p>';
    }
    ?>
    <form name="addform">
        <input type="hidden" name="parentId" value="<?= intval($taskId) ?>">
        <label for="addsubtaskname"><b>Add SubTask:</b></label> <input id="addsubtaskname" type="text" name="description" value="">
        <input type="submit" value="Add">
    </form>
    <script>
    $('form[name="addform"]').on('submit', function(ev) {
        var $this = $(this);
        ev.preventDefault();
        
        // >>>00002: could use some client-side validation here before calling AJAX
        $.ajax({
            url: '../ajax/addtask.php',
            data: {
                description: $('form[name="addform"] input[name="description"]').val(),
                parentId: $('form[name="addform"] input[name="parentId"]').val(),
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        location.href = '?taskId=' + data['taskId']; // leave the rest of the URL implicit so this works OK on PHP inclusions.
                        parent.frames['acordion'].location.href = 'acordion.php?taskId='+ data['taskId']; // reload left frame
                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error no \'status\' in data returned from _admin/ajax/addtask.php.\n' + 
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