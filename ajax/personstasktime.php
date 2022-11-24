<?php
/*  ajax/personstasktime.php

    INPUT $_REQUEST['workOrderTaskId']: (Before v2020-4, inappropriately $_REQUEST['workOrderTaskCategoryTaskId'])
    INPUT $_REQUEST['date']: >>>00001 JM: I believe this is date in 'YYYY-MM-DD' form, but someone should verify.
    
    Builds an HTML table reporting on work done for a particular workOrderTask on a particular date. 
    Each row shows formatted person name (not linked, in this case) and time as hours:minutes.

    This writes directly to the HTML document using PHP echo, and is intended to dynamically create the content of
    a dialog. It should be called using code like:
    
        $("#FOO_dialog").load('/ajax/personstasktime.php?date=' + escape(date) + '&workOrderTaskId=' + escape(workOrderTaskId), function(){
            $('#FOO_dialog').dialog({height:'auto', width:'auto'});
        });    

*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

// >>>00002, >>>00016 Inputs need validation
$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$date = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';

$query = "SELECT minutes, personId ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
$query .= "WHERE day = '" . $db->real_escape_string($date) . "' ";
$query .= "AND workOrderTaskId = " . intval($workOrderTaskId) . ";";
$result = $db->query($query);

echo '<table border="0" cellpadding="0" cellspacing="0" width="200">';
    echo '<tr>';
        echo '<th>Person</th>';
        echo '<th>h : m</th>';    
    echo '</tr>';
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $person = new Person($row['personId']);                
            echo '<tr>';
                // "Person"
                echo '<td>' . $person->getFormattedName() . '</td>';
                // "h : m"
                echo '<td>' . gmdate("H:i", ($row['minutes'] * 60)) . ' </td>';            
            echo '</tr>';
        }            
    } else  {
        $logger->errorDb('1594158050', "Hard DB error", $db);
    }
echo '</table>';

?>