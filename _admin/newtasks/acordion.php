<?php 
/*   _admin/newtasks/acordion.php  (>>>00012 Yes, that is a misspelling of "accordion")

    EXECUTIVE SUMMARY: Page that displays an accordion of tasks with an additional "add task" 
        at the bottom of each level, so you can add a task as a child of any existing task.
    
    No primary input, because this always shows all tasks.
    
    Optional INPUT $_REQUEST['act']. Only supported value 'addtask': if present also takes inputs 
        * $_REQUEST['parentId']: primary key of parent in DB table Task
        * $_REQUEST['description']
        
    Optional INPUT $_REQUEST['taskId'] added 2020-08-24 JM, means to show this particular task.
    Optional INPUT $_REQUEST['alphabetical'] added 2020-08-27 JM in support of feature request http://bt.dev2.ssseng.com/view.php?id=229,
                if 'true' then we show tasks at top level & for each given parent in alphabetical order rather than sortOrder.
    
    JM: I believe a bunch of this code comes from https://codepen.io/vikasverma93/pen/raxGaM, though it
    might be some other similar codepen tool.
*/

include '../../inc/config.php';

$db = DB::getInstance();
$tasks = array();

// BEGIN ADDED 2020-08-27 JM in support of feature request http://bt.dev2.ssseng.com/view.php?id=229
// >>>00002, >>>00016: could validate inputs
$alphabetical = isset($_REQUEST['alphabetical']) && ($_REQUEST['alphabetical'] == 'true');
// END ADDED 2020-08-27 JM

if ($act == 'addtask') {
    /* $_REQUEST['description'] is used for description and billingDescription, so these are identical. */
    // Should share code with _admin/ajax/addtask.php and be a method of Task class
    $parentId = isset($_REQUEST['parentId']) ? intval($_REQUEST['parentId']) : 0;
    $description = isset($_REQUEST['description']) ? $_REQUEST['description'] : '';
    $description = trim($description);
    $description = substr($description, 0, 255); // >>>00002 truncates silently
    if (strlen($description)) {
        list($taskId, $error) = Task::addTask($parentId, $description);
        if ($error) {
            // >>>00001 not really dealt with!
        } else {
            // reload cleanly so addtask won't happen twice; navigate to the new task
            header("Location: " . 'acordion.php?taskId='.$taskId
                . ($alphabetical ? '&alphabetical=true' : '')
                ); 
            die();
        }
    }    
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <?php /* >>>00001 JM: I'm not at all sure we want the next two lines, which I'm sure were Martin's copy-paste from an example */ ?>  
    <link rel="shortcut icon" type="image/x-icon" href="https://production-assets.codepen.io/assets/favicon/favicon-8ea04875e70c4b0bb41da869e81236e54394d63638a1ef12fa558a4a835f1164.ico" />
    <link rel="mask-icon" type="" href="https://production-assets.codepen.io/assets/favicon/logo-pin-f2d2b6d2c61838f7e76325261b7195c27224080bc099486ddd6dccb469b8e8e6.svg" color="#111" />

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
            width: 40%;
            min-width: 300px;
            max-width: 400px;
            margin: 1.5em auto;
            color: #333;
        }
        h1 {
            font-family: 'Pacifico', cursive;
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
            background-color: rgba(99, 50, 50, 0.78);
            color: #fefefe;
            padding: .25em;
            border-radius: 0.15em;
            transition: background .3s ease;
        }
        ul li a.toggle.active {
            background-color: rgba(0, 0, 0, 0.78);
        }
        ul li a.toggle:hover {
            background-color: rgba(99, 50, 50, 0.5);
        }
        ul li a.toggle.active:hover {
            background-color: rgba(99, 99, 99, 0.9);
        }
        .selected-node:before {
            content: '\2192\00A0'; /* right arrow & nonbreaking space */
        }
    </style>
    <script>
        window.console = window.console || function(t) {}; // Make sure function window.console won't error out when called

        <?php /*  >>>00014 why are we sending a resize message to the parent window (which will contain _admin/descriptor/index.php)? */ ?>
        if (document.location.search.match(/type=embed/gi)) {
            window.parent.postMessage("resize", "*");    <?php /* "*" here means no test for URI of parent */ ?>
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js" ></script>
</head>

<body translate="no">
    <?php
    // RECURSIVE function to create accordion
    // INPUT $parentId: primary key in DB table TASK of parent for current level; 0 for top level.
    function drawLevel($parentId) {
        global $alphabetical;
        
        if ($parentId == 0) {
            $tasks = Task::getZeroLevelTasks($alphabetical, false); // false -> don't limit to active
        } else {
            $parent = new Task($parentId);
            $tasks = $parent->getChilds($alphabetical, false); // false -> don't limit to active
        }        
        
        foreach ($tasks as $task) {
            // Lookahead: does this task have children? In other words, is this task a leaf (no children of its own)?
            $hasSubs = $task->hasChild(false); // false -> don't limit to active
            $taskId = $task->getTaskId();
            $taskActive = $task->getActive(); 
            if ($hasSubs) {
                // internal node.
                // For non-leaf nodes, we show the task description, followed by the tree of its descendants
                echo '<li>' . "\n";
                    // class 'node' in the following added 2020-08-27 JM in support of feature request http://bt.dev2.ssseng.com/view.php?id=229
                    // NOTE number as HTML ID in the following; not great practice, but at least the way this file is arranged there can
                    //  never be any duplicate IDs.
                    echo '<a rel="nofollow noreferrer" class="node toggle' . ($taskActive ? ' active' : '') . '" data-taskid="'. $taskId . '" ' . 
                         'id="task_' . $taskId . '" ' . 
                         'href="taskId=' . $taskId . '">' . // no actual effect, but it's nice on hover 
                         $task->getDescription() . '</a>' . "\n";
                    echo '<ul class="inner">';
                    drawLevel($taskId);
                    echo '</ul>';
                echo '</li>' . "\n";
            } else {
                // leaf node: color indicates active vs. inactive task
                // For leaf nodes, we show just show the "description".
                $color = $taskActive ? '#6cc417' : '#c24641';
                // class 'node' & the HTML ID in the following added 2020-08-27 JM in support of feature request http://bt.dev2.ssseng.com/view.php?id=229
                // NOTE number as HTML ID in the following; not great practice, but at least the way this file is arranged there can
                //  never be any duplicate IDs. This one is actually my (JM) doing, copying what was already happening for the "toggle" nodes 
                echo '<li style="background-color:' . $color . '">'.
                     '<a rel="nofollow noreferrer" target="edit" class="node leaf" data-taskid="' . $taskId . '" '. 
                     'id="task_' . $taskId . '"' .
                     'href="taskId=' . $taskId . '">' . // no actual effect, but it's nice on hover
                     $task->getDescription() . '</a></li>' . "\n";
            }                
        }
        // At every level, including leaf nodes, the ability to add a child task.
        echo '<li><p><p><form name="addform_' . intval($parentId) . '">' .
             '<input type="hidden" name="parentId" value="' . intval($parentId) . '">' .
             '<input type="hidden" name="act" value="addtask">' .
             '<input type="text" name="description" value="">' .
             '<input type="submit" value="add"></form></li>';
    } // END function drawLevel
    
    if (array_key_exists('taskId', $_REQUEST) && $_REQUEST['taskId']) {
        $taskId = $_REQUEST['taskId'];
    } else {
        $taskId = 0;
    }
    ?>
    <script>
    var taskId = <?= $taskId ?>; // This is deliberately a JavaScript global, don't use 'let'.
    </script>
    <input id="sort-by-sortorder" type="radio" name="sort-by" value="sortorder" <?= $alphabetical ? '' : 'checked'?>>&nbsp;
    <label for="sort-by-sortorder">Sort by contract/invoice sort order</label>&nbsp;&nbsp;&nbsp;<br />
    <input id="sort-by-alphabetical" type="radio" name="sort-by" value="alphabetical" <?= $alphabetical ? 'checked' : ''?>>&nbsp;
    <label for="sort-by-alphabetical">Sort alphabetically</label><br />
    <script>
    $(function() {
        $('input[name="sort-by"]').change(function() {
            let alphabetical = $('#sort-by-alphabetical').is(':checked');
            if (taskId) {
                document.location = 'acordion.php?taskId=' + taskId + (alphabetical ? '&alphabetical=true' : '');
            } else {
                document.location = 'acordion.php' + (alphabetical ? '?alphabetical=true' : '');
            }   
        });           
    });
    </script>
    <?php
    echo '<ul class="accordion">';    
        $parentId = 0;
        drawLevel($parentId);  // Start the recursive process of creating accordion.
    echo '</ul>';
    ?>

    <script>
    <?php /* Function to load right frame for a particular taskId, while maintaining its tabbing (that is, its PHP source) */ ?>
    function loadRightFrame(taskId) {
        if (parent.frames.length) {
            let parts = parent.frames['edit'].location.href.split('?');
            let filename = (parts.length ? parts[0] : 'edit.php');                
            parent.frames['edit'].location.href = filename + '?taskId=' + taskId;            
        }
    }
    
    <?php /* Clicking a non-leaf node in the accordion 
             >>>00006 Weird that we can only do this with click: no way to toggle from keyboard. */ ?>
        $('.toggle').click(function(e) {
            e.preventDefault();
            var $this = $(this);
        
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

        $('.node').click(function(e) {
            e.preventDefault();  
            var $this = $(this);    
            taskId = $this.data('taskid'); // deliberately global
            if (parent.frames.length) {
                loadRightFrame(taskId);
            }
            markAsSelected($this);
        });
        
        function markAsSelected($node) {
            $('.selected-node').removeClass('selected-node'); // clear any prior selection
            $node.addClass('selected-node');
        }
    </script>
    <?php
    if ($taskId) {
        ?>
        <script>
        $(function() {
            <?php /* Expand the parent (and up the line to root). Reworked JM 2020-11-06 */ ?>
            var $parent = $('#task_<?= $taskId ?>').closest('ul').closest('li').find('.toggle');
            if ($parent.length) {
                do {
                    // This one, then up the hierarchy
                    $parent.click();
                    $parentList = $parent.closest('ul');
                    if ( ! $parentList.hasClass('accordion') ) {
                        $parent = $parentList.closest('li').find('.toggle');
                    }
                } while ( ! $parentList.hasClass('accordion') ); 
            } else {
                // top-level, let's expand the node itself
                $('#task_<?= $taskId ?>').click();
            }
            
            <?php /* Edit selected task in "edit" frame */ ?>
            if (parent.frames.length) {
                loadRightFrame(<?= $taskId ?>);
            }
            markAsSelected($('#task_<?= $taskId ?>'));
        });
        </script>
    <?php
    }
    /* END ADDED 2020-08-24 */
    ?>
</body>
</html>