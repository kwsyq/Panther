<?php
/* ajax/workordertaskelements.php

    INPUT $_REQUEST['workOrderTaskId']: Primary key to DB table workOrderTask
    
    NOTE very similar name to ajax/workordertaskelement.php, just that it's pluralized.

    Returns HTML for a table of elements for this workOrderTask. Table just gives element names, one to a row, each in a cell of its own.
    
    This writes directly to the HTML document using PHP echo, and is intended to dynamically create the content of
    a dialog. It should be called using code like:
    
        $("#FOO_dialog").load('/ajax/workordertaskelements.php?workOrderTaskId=' + escape(workOrderTaskId), function(){
            $('#FOO_dialog').dialog({height:'auto', width:'auto'});
        });    
*/    

include '../inc/config.php';
include '../inc/access.php';

sleep(0.5); // So user will see AJAX icon & know something is happening.

$db = DB::getInstance();

$elements = array();
$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? $_REQUEST['workOrderTaskId'] : 0;

$query = " select wote.*, e.elementName ";
$query .= " from " . DB__NEW_DATABASE . ".workOrderTaskElement wote ";
$query .= " join " . DB__NEW_DATABASE . ".element e on wote.elementId = e.elementId ";
$query .= " where wote.workOrderTaskId = " . intval($workOrderTaskId) . " ";
$query .= " order by e.elementId ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $elements[] = $row;
        }
    }
} // >>>00002 ignores failure on DB query!

echo '<table>';
    foreach ($elements as $element) {    
        echo '<tr>';    
            echo '<td>' . $element['elementName'] . '</td>';    
        echo '</tr>';    
    }
echo '</table>';

die();

?>