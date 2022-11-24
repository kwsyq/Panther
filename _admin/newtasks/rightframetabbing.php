<?php
/*   _admin/newtasks/rightframetabbing.php

    EXECUTIVE SUMMARY: function to create tabs at the top of the right frame of _admin/newtasks
*/

// INPUT $self - filename, no path, of the caller so we know their tab is active
// INPUT $taskId
function createNewtasksTabs($self, $taskId=0) {
    $tabs = Array(
        'edit.php' => 'All',
        'properties.php' => 'Properties',
        'icon.php' => 'Icon',
        'addchildtask.php' => 'Add child',
        'manageDetails.php' => 'Details',
        'usedinjobs.php' => 'Used in jobs',
        'changeorder.php' => 'Sort order'
    );
    ?>
    <style>
    #tabtable, #tabtable tr {
        border: 0px;
    }
    #tabtable th {
        background-color: lightblue;
        text-align: center;
        padding-left: 8px;
        padding-right: 8px;
        border-left: 1px solid black;
        border-right: 1px solid black;
        border-bottom: 1px solid black;
    }
    #tabtable th:first-of-type {
        border-left: 0;
    }
    #tabtable th.self {
        border-bottom: 0px;
        background-color: white;
    }
    #tabtable th.self:last-of-type {
        border-right: 0;
    }
    </style>
    <?php
    echo '<table id="tabtable"><thead><tr>' . "\n";
    foreach ($tabs as $file => $display) {
        if ($file == $self) {
            echo '<th class="self">' . $display . '</th>' . "\n";
        } else {
            echo '<th><a href="' . $file . '?taskId=' . $taskId . '">' . $display . '</a></th>' . "\n"; 
        }        
    }    
    echo '</tr></thead></table>' . "\n";
}
?>