<?php
/*  ajax/invoicestatus.php

    INPUT $_REQUEST['invoiceId']: primary key to DB table Invoice
    
    This writes directly to the HTML document using PHP echo, and is intended to dynamically create the content of
    a dialog. It should be called using code like:
    
        $("#FOO_dialog").load('/ajax/invoicestatus.php?invoiceId=' + escape(invoiceId), function(){
            $('#FOO_dialog').dialog({height:'auto', width:'auto'});
        });
        
   Heavily rewritten as part of http://sssengwiki.com/EORs%2C+stamps%2C+etc conversion, to the point where it was not worth preserving old code
*/


include '../inc/config.php';
include '../inc/access.php';
require_once '../inc/perms.php';

if ($userPermissions['PERM_INVOICE'] > PERMLEVEL_R) { // Lower number => more permissions.
    // user does not have permission to read invoices; this presumably should have been headed off at a higher level
    $logger->error2('1589928238', "User w/ userId = " . $user->getUserId() . " accessing ajax/invoicestatus.php without sufficient permission."); 
    die(); // die without echoing any HTML 
}
   
$db = DB::getInstance();
$invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;

// Getting history of invoice status, in backward chronological order
// Order relies on invoiceStatusTimeId increasing monotonically.
$ists = array();
$query = "SELECT ist.invoiceStatusTimeId, s.statusName, cp.legacyInitials, ist.inserted, ist.personId, ist.note "; // changed from 'SELECT *' 2020-05-19 JM
$query .= "FROM " . DB__NEW_DATABASE . ".invoiceStatusTime ist "; 
$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".istCustomerPerson ON ist.invoiceStatusTimeId = istCustomerPerson.invoiceStatusTimeId ";
$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".customerPerson cp ON istCustomerPerson.customerPersonId = cp.customerPersonId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".invoiceStatus s on ist.invoiceStatusId = s.invoiceStatusId ";
$query .= "WHERE ist.invoiceId = " . intval($invoiceId) . " ";
$query .= "ORDER BY ist.invoiceStatusTimeId DESC, istCustomerPerson.customerPersonId ASC;";

$result = $db->query($query);

if (!$result) {
    $logger->errorDb('1589928253', 'Hard DB error', $db);
    die(); // die without echoing any HTML
}

$prevInvoiceStatusTimeId = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['invoiceStatusTimeId'] == $prevInvoiceStatusTimeId) {
        // Same status, should differ only in legacyInitials, append that 
        $ists[count($ists)-1]['legacyInitials'] .= ',' . $row['legacyInitials']; 
    } else {
        $ists[] = $row;
    }
    $prevInvoiceStatusTimeId = $row['invoiceStatusTimeId']; 
}
unset($prevInvoiceStatusTimeId);
?>

<style>
    .thistable {
        font-size: 75%;
    }
</style>

<?php
    echo '<table border="1" cellpadding="2" cellspacing="2" class="thistable">';
        echo '<tr>';
            echo '<th>Status</th>';
            echo '<th>Extra</th>';
            echo '<th>Inserted</th>';
            echo '<th>Who</th>';
            echo '<th>Note</th>';
        echo '</tr>';
        // Use "for: rather than "foreach" so we can fiddle the case with multiple customerPersons
        for ($ikey=0; $ikey<count($ists); ++$ikey) {
            $ist = $ists[$ikey];
        // END REPLACEMENT 2020-05-20 JM        
            echo '<tr>';
                // "Status"
                echo '<td>' . $ist['statusName'] . '</td>';                

                // "Extra": subtable with legacyIntials of any customerPerson(s) specifically called out in the status 
                echo '<td>';
                    if ($ist['legacyInitials']) {
                        $legacyInitialsArray = explode(',', $ist['legacyInitials']);
                        if ($legacyInitialsArray) {
                            echo '<table border="0" cellpadding="1" cellspacing="0">';
                            foreach ($legacyInitialsArray as $legacyInitials) {
                                echo '<tr><td valign="top">&gt;</td><td valign="top">' . $legacyInitials . '</td></tr>';
                            }
                            echo '</table>';                        
                        }
                    } // else blank cell
                echo '</td>';
                
                // "Inserted": date associated with this status
                echo '<td>' . date("m/d/Y", strtotime($ist['inserted'])) . '</td>';
        
                // "Who": formatted name of person who inserted the status
                echo '<td>';        
                    if (intval($ist['personId'])) {
                        $pp = new Person($ist['personId']);
                        if (intval($pp->getPersonId())) {
                            echo $pp->getFormattedName(1);
                        }
                    }
                echo '</td>';       
        
                // "Note"
                echo '<td>';
                    echo $ist['note'];
                echo '</td>';
            echo '</tr>';                
        }

    echo '</table>';
?>