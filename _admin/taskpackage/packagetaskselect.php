<?php
/*  _admin/taskpackage/packagetaskselect.php

    EXECUTIVE SUMMARY: PAGE to display an accordion of tasks, which may be added to the specified taskPackage.

    >>>00001 According to Martin there was a lot of cut-and-paste here, and it may not be exactly as one would wish, but it's functional.

    PRIMARY INPUT $_REQUEST['taskPackageId']: primary key to DB table TaskPackage

    NOTE that the 'addTask' case is not a self-submission: it submits to packageitems.php.
*/

include '../../inc/config.php';

$taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;

if (intval($taskPackageId)) {
    ?>    
    
    <!DOCTYPE html>
    <html>
    
    <head>
        <meta charset="UTF-8">
        <?php /* >>>00007 I (JM) really doubt we want someone else's favicons. >>>00001 Check where else we may have sucked these in. */ ?>
        <link rel="shortcut icon" type="image/x-icon" href="https://production-assets.codepen.io/assets/favicon/favicon-8ea04875e70c4b0bb41da869e81236e54394d63638a1ef12fa558a4a835f1164.ico" />
        <link rel="mask-icon" type="" href="https://production-assets.codepen.io/assets/favicon/logo-pin-f2d2b6d2c61838f7e76325261b7195c27224080bc099486ddd6dccb469b8e8e6.svg" color="#111" />
        <title>CodePen - A simple jQuery Accordion with unlimited nesting</title> <?php /* >>>00006 I guess this shows where it came from, but that shouldn't be our page title! */ ?>      
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
                /*display: block;*/ /* COMMENTED OUT BY MARTIN BEFORE 2019 */
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
            window.console = window.console || function(t) {}; // Make sure function window.console won't error out when called

            if (document.location.search.match(/type=embed/gi)) {
                <?php /*  >>>00014 why are we sending a resize message to the parent window (which will contain _admin/taskpackage/index.php)? */ ?>
                window.parent.postMessage("resize", "*");
            }
        </script>    
    </head>
    
    <body translate="no" >
        <?php
        // RECURSIVE function to create accordion
        // INPUT $parentId: primary key in DB table TASK of parent for current level; 0 for top level.
        // INPUT $title: empty string on the top-level call, otherwise description of parent task.
        // INPUT $taskPackageId: current taskPackage; same all the way up & down the hierarchy
        function drawLevel($parentId, $title, $taskPackageId) {
            if ($parentId == 0) {
                $tasks = Task::getZeroLevelTasks(false, true); // false, true -> use sortOrder, and limit to active
            } else {
                $parent = new Task($parentId);
                $tasks = $parent->getChilds(false, true); // false, true -> use sortOrder, and limit to active
            }        
        
            foreach ($tasks as $tkey => $task) {
                // Lookahead: does this task have children? In other words, is this task a leaf (no children of its own)?
                $hasSubs = $task->hasChild(true); // true -> limit to active
                $taskId = $task->getTaskId();
    
                if ((!$tkey) && $parentId) {
                    // First task on this level, and it's not the top level
                    // Link to add *parent* task (not current task) to the current taskPackage 
                    echo '<li><a target="packageItems" href="packageitems.php?act=addtask&taskPackageId=' . intval($taskPackageId) . 
                         '&taskId=' . intval($parentId) . '">***' . $title . '***</a></li>' . "\n";
                }
                
                if ($hasSubs) {
                    // internal node.
                    // For non-leaf nodes, we show the task description, followed by the tree of its descendants
                    // As noted above, internal function showTask does nothing: the action is all in $('.toggle').click below.
                    echo '<li>' . "\n";
                        echo '<a rel="nofollow" rel="noreferrer" class="toggle" id="' . $taskId . '" ' .
                             'href="javascript:showTask(' . $taskId . ');">' . $task->getDescription() , '</a>' . "";
                        echo '<ul class="inner">';
                            drawLevel($taskId, $task->getDescription(), $taskPackageId);
                        echo '</ul>';
                    echo '</li>' . "\n";
                } else {
                    echo '<li><a target="packageItems" href="packageitems.php?act=addtask&taskPackageId=' . intval($taskPackageId) . 
                         '&taskId=' . $taskId . '">' .
                         $task->getDescription() . '</a></li>' . "\n";
                }
            }
        }
        echo '<ul class="accordion">';        
            $parentId = 0;        
            drawLevel($parentId, '', $taskPackageId);  // Start the recursive process of creating accordion.
        echo '</ul>';        

        ?>

        <?php /* stopExecutionOnTimeout was apparently brought in wholesale with the accordion code, probably harmless but not needed. */ ?>
        <script src="//production-assets.codepen.io/assets/common/stopExecutionOnTimeout-b2a7b3fe212eaa732349046d8416e00a9dec26eb7fd347590fbced3ab38af52e.js"></script>
    
        <?php /* >>>00001 this isn't where we usually get jquery, probably copy-paste, probably not where we want to get it.
                 Would be worth looking into everywhere we bring in jQuery, make a single consistent choice. */?>
        <script src='//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
    
        <script>
            <?php /* Clicking a non-leaf node in the accordion
                     NOTE: in this file, toggling has NO side effects.
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
            });  // END $('.toggle').click
        </script>

<?php 
} // END if (intval($taskPackageId))
?>
</body>
</html>