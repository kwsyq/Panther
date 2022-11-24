<?php 
/*  ajax/invoiceactivitynotes.php

    INPUT $_REQUEST['invoiceId']: primary key in DB table Invoice
    
    Requires Admin-level payment permissions.
    
    This writes directly to the HTML document using PHP echo, and is intended to dynamically create the content of
    a dialog. It should be called using code like:
    
        $("#FOO_dialog").load('/ajax/invoiceactivitynotes.php?invoiceId=' + escape(invoiceId), function(){
            $('#FOO_dialog').dialog({height:'auto', width:'auto'});
        });    
*/

include '../inc/config.php';
include '../inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    // Die without doing anything.
    // >>>00002: at least let's log this probable attempt to access a protected portion of the system!
    die();
}

$invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;

$notes = array();

$query =  " select * from " . DB__NEW_DATABASE . ".agingNote ";
$query .= " where invoiceId = " . intval($invoiceId) . " ";
$query .= " order by inserted desc ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0){
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }
    }
} // >>>00002 ignores failure on DB query!

$color = ' bgcolor="#cccccc" '; // medium-light gray for headers

echo '<table border="0" cellpadding="0" cellspacing="0" width="550">';
    echo '<tr>';
        echo '<th>Note</th>';
        echo '<th>Person</th>';
        echo '<th>Time</th>';
    echo '</tr>';
    
    foreach ($notes as $nkey => $note) {
        if (intval($nkey % 2)) {        
            $color = ' bgcolor="#eaeaea" '; // very light gray for odd-numbered rows        
        } else {        
            $color = "";         // white for even numbered rows
        }
        
        echo '<tr ' . $color . '>';    
            $p = new Person($note['personId']);
            // "Note" (note text)
            echo '<td width="400">' . $note['note'] . '</td>';
            // "Person" (who inserted the note)
            echo '<td>' . $p->getFormattedName(1) . '</td>';
            // "Time" (insertion time of note)
            echo '<td nowrap>' . date("m/d/Y", strtotime($note['inserted'])) . '</td>';
        echo '</tr>';
    }
echo '</table>';

?>