<?php 
/*  _admin/descriptor/task.php

    EXECUTIVE SUMMARY: 
        Before 2019-12 was: PAGE to manage tasks related to a descriptorSub.
        Beginning 2019-12: PAGE to manage tasks related to a descriptor2.

    PRIMARY INPUT $_REQUEST['descriptor2Id'] descriptor2.
    
    We also typically pass in $_REQUEST['name'], which is used only to create a header.

    >>>00037 There is a lot of very similar code here to fb/workordertaskcats.php, 
     potential for common code elimination. By no means all of it is common code, but
     enough to merit study. At the very least, client-side function showsub seems to be identical,
     and drawLevel looks similar enough that most differences may be mistakes.
*/

include '../../inc/config.php';

$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>	
    <script src="/js/jquery.autocomplete.min.js"></script>	
    <script>
        // INPUT t - task Id. Parent of tasks to display.
        // This fills in the table "subtable" in the second column of the main table here
        // Synchronous AJAX post to /ajax/taskchilds.php;
        //  for behavior on AJAX success, see remarks inline; 
        //  ignores AJAX failure (>>>00002 should log).
        function showsub(t) {
            console.log('in function showsub with parent ' + t);
            var et = document.getElementById('subtable');
            et.innerHTML = ''; // temporarily clear subtable.
            
            var html = '';
            $.ajax({
                url: '/ajax/taskchilds.php',    
                data:{
                    parentId: escape(t)
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
                    console.log(data);
                    // There is no status as such in return
                    // Assuming there is data about the task 't' itself, 
                    //  set up a row, spanning both columns, that displays the task description; 
                    //  This is a link to add that task, using its taskId.
                    if (data['task']) {
                        html += '<tr>';
                            html += '<td colspan="2"><a href="javascript:addTask(' + data['task']['taskId'] + ')">';
                                html += data['task']['description'];
                            html += '</a></td>';
                        html += '</tr>';                        
                    }
                    // Then do the same for each child; we get an indent by blanking column 1 & putting this in column 2.
                    if (data['childs']) {
                        for (var i = 0; i < data['childs'].length; i++) {    
                            html += '<tr>';    
                                html += '<td>&nbsp;&nbsp;&nbsp;</td>';
                                html += '<td><a href="javascript:addTask(' + data['childs'][i]['taskId'] + ');">' + data['childs'][i]['description'] + '</a></td>';
                            html += '</tr>';                            
                        }                        
                        //et.innerHTML = html; // Commented out by Martin before 2019                        
                    }    
                    et.innerHTML = html;                    
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //	alert('error'); // Commented out by Martin before 2019
                }
            });
        } // END function showsub
    </script>
    <style>
        @import url('http://fonts.googleapis.com/css?family=Pacifico|Open+Sans:300,400,600');
        * {
            box-sizing: border-box;
            font-family: 'Open Sans', sans-serif;
            font-weight: 300;
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
        window.console = window.console || function(t) {}; <?php /* Make sure a call to window.console won't error out */ ?> 

        <?php /*  >>>00014 why are we sending a resize message to the parent window (which will contain _admin/descriptor/index.php)? */ ?>
        if (document.location.search.match(/type=embed/gi)) {
            window.parent.postMessage("resize", "*");    <?php /* '*' here means no test for URI of parent */ ?>
        }

        <?php
        // Fill in subtable "existingtasks" (Column 3 of main table) with
        // all existing tasks for this workOrder
        // Synchronous AJAX post to _admin/ajax/existingdescriptortasks.php;
        //  for behavior on AJAX success, see remarks inline; 
        //  ignores AJAX failure (>>>00002 should log).
        ?>
        var getCurrentTasks = function() {
            var et = document.getElementById('existingtasks');
            et.innerHTML = '';
              
            $.ajax({                
                url: '../ajax/existingdescriptortasks.php',        
                data: {
                    descriptor2Id: <?php echo intval($descriptor2Id); ?>
                },
                async: false,
                type: 'post',        
                success: function(data, textStatus, jqXHR) {
                    if (data['status'] == 'fail') {
                        if (data['error']) {
                            alert(data['error']);
                        } else {
                            alert('_admin/ajax/existingdescriptortasks.php says failure, but gave no error message.');
                        }
                    }
                    if (data['tasks']) {
                        var et = document.getElementById('existingtasks');        
                        var html = '';        
                        html += '<table border="0" cellpadding="0" cellspacing="0">';
                            for (var i = 0; i < data['tasks'].length; i++) {        
                                html += '<tr>';
                                    // Task description
                                    html += '<td>' + data['tasks'][i]['description'] + '</td>';
                                    // Deletion icon, linked to local function deleteDescriptorSubTask
                                    html += '<td><a href="javascript:deleteDescriptorSubTask(' + data['tasks'][i]['descriptorSubTaskId'] + ');">' +
                                            '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_delete_16x16.png" ' +
                                            'width="16" height="16" border="0"></a></td>';
                                html += '</tr>';                            
                            }        
                        html += '</table>';        
                        et.innerHTML = html;        
                    }                                     
                },        
                error: function(jqXHR, textStatus, errorThrown) {
                    //	alert('error'); // Commented out by Martin before 2019
                }
            });
        } // END function getCurrentTasks

        // Make a synchronous POST to _admin/ajax/deletedescriptorsubtask.php
        // to delete a row from DB table DescriptorSubTask, breaking connection 
        // between this descriptorSub and a particular task.
        // >>>00002: should log errors.
        // INPUT descriptorSubTaskId identifies the row to delete
        var deleteDescriptorSubTask = function(descriptorSubTaskId) {
            $.ajax({                
                url: '../ajax/deletedescriptorsubtask.php',        
                data: {
                    descriptorSubTaskId:descriptorSubTaskId
                },
                async: false,
                type: 'post',                
                success: function(data, textStatus, jqXHR) {        
                    getCurrentTasks();
                },        
                error: function(jqXHR, textStatus, errorThrown) {
                    //	alert('error');  // Commented out by Martin before 2019
                }
            });
        } // END function deleteDescriptorSubTask

        // Make a synchronous POST to _admin/ajax/adddescriptortask.php
        // to add a row to DB table DescriptorSubTask to associate a task with this descriptor2.
        // INPUT taskId identifies the task for which to add a row.
        var addTask = function(taskId) {
            $.ajax({            
                url: '../ajax/adddescriptortask.php',    
                data:{
                    taskId:taskId,
                    descriptor2Id: <?php echo intval($descriptor2Id); ?>
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {    
                    if (data['status'] == 'fail') {
                        if (data['error']) {
                            alert(data['error']);
                        } else {
                            alert('_admin/ajax/adddescriptortask.php says failure, but gave no error message.');
                        }
                    }
                    getCurrentTasks();
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //	alert('error');  // Commented out by Martin before 2019
                }
            });
        } // END function addTask

</script>
</head>

<body bgcolor="#ffffff">
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
         
         REWRITTEN 2020-11-10 JM: much simplified by using methods of Task class; also, now limited to active tasks.
    */
    function drawLevel($parentId) {
        if ($parentId == 0) {
            $tasks = Task::getZeroLevelTasks();
        } else {
            $parent = new Task($parentId);
            $tasks = $parent->getChilds();
        }        
    
        foreach ($tasks as $task) {
            // Lookahead: does this task have children? In other words, is this task a leaf (no children of its own)?
            $hasSubs = $task->hasChild(true); // true -> limit to active
            $taskId = $task->getTaskId();
    
            echo '<li>' . "\n";
                if ($hasSubs) {
                    // For non-leaf nodes, we show the task description, followed by the tree of its descendants
                    echo '<a rel="nofollow noreferrer" class="toggle" id="' . $taskId . '" href="javascript:void(0);">' . 
                         $task->getDescription() . '</a>' . "\n";
                    echo '<ul class="inner">';
                        drawLevel($taskId);
                    echo '</ul>';
                } else {
                    // For leaf nodes, we show just show the "description". 
                    echo $task->getDescription();
                }
            echo '</li>' . "\n";
        }
    } // END function drawLevel

    $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';    
    echo '<h2>&nbsp;&nbsp;' . $name . '</h2>';

    echo '<table border="0" cellpadding="0" cellspacing="0">';
        echo '<tr>';
            echo '<td width="33%" valign="top">';        
                echo '<ul class="accordion">';        
                    $parentId = 0; // tasks with no parents            
                    drawLevel($parentId);        
                echo '</ul>';    
            echo '</td>';
    
            echo '<td valign="top" align="left" width="33%" bgcolor="#dddddd">';
                // TABLE "subtable", filled in by function showsub: will show a task and its immediate children
                //  (not deeper descendants).
                echo '<table border="0" cellpadding="0" cellspacing="0" id="subtable">';
                echo '</table>';
            echo '</td>';
            
            // TABLE "existingtasks", initially empty. After building the HTML, 
            //  we call function getCurrentTasks, which in turn calls _admin/ajax/existingdescriptortasks.php, 
            //  passing it the descriptor2Id; that fills this in
            //  with all existing tasks for this descriptor2.
            echo '<td width="33%" valign="top">';
                echo '<table border="0" cellpadding="3" cellspacing="0" id="existingtasks" width="100%">';
                echo '</table>';            
            echo '</td>';            
        echo '</tr>';        
    echo '</table>';	
    ?>

    <?php /* stopExecutionOnTimeout was apparently brought in wholesale with the accordion code, probably harmless but not needed. */ ?>
    <script src="//production-assets.codepen.io/assets/common/stopExecutionOnTimeout-b2a7b3fe212eaa732349046d8416e00a9dec26eb7fd347590fbced3ab38af52e.js"></script>

    <script>
    <?php /* Clicking a non-leaf node in the accordion 
             >>>00006 Weird that we can only do this with click: no way to toggle from keyboard. */ 
    ?>
    $('.toggle').click(function(e) {
        e.preventDefault();      
        var $this = $(this);
        
        showsub($(this).attr('id')); <?php /* Regardless of whether we show or hide, fill in table "subtable"
                                              based on the task we just clicked. */ ?>    
        
        <?php /* Handle the accordion toggling */ ?>
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
    
    <?php /* Fill in subtable "existingtasks" */ ?>
    getCurrentTasks();
        
    </script>
</body>
</html>