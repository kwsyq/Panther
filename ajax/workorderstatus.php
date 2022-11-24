<?php
/*
    ajax/workorderstatus.php

    INPUT $_REQUEST['workOrderId']: primary key to WorkOrder table.
    
    This writes directly to the HTML document using PHP echo, and is intended to dynamically create the content of
    a dialog. It should be called using code like:
    
        $("#FOO_dialog").load('/ajax/workorderstatus.php?workOrderId=' + escape(workOrderId), function(){
            $('#FOO_dialog').dialog({height:'auto', width:'auto'});
        });    

    Builds an HTML table with the status history for the workOrder. Table is in reverse chronological order (newest first).
    
    Heavily rewritten by JM 2020-06 for v2020-2; see http://sssengwiki.com/EORs%2C+stamps%2C+etc for the massive changes in this area.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

$wosts = array();
   
// Will be in reverse chronological order because workOrderStatusTimeId is created in monotonically increasing order for new rows  
$query = "SELECT wost.workOrderStatusTimeId, s.statusName, cp.legacyInitials, wost.inserted, wost.personId, wost.note ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatusTime wost ";
$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatus s ON wost.workOrderStatusId = s.workOrderStatusId ";
$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".wostCustomerPerson wostcp ON wost.workOrderStatusTimeId = wostcp.workOrderStatusTimeId ";
$query .= "LEFT JOIN (";
    $query .= "SELECT customerPersonId, customerId, legacyInitials ";
    $query .= "FROM ". DB__NEW_DATABASE . ".customerPerson ";
    $query .= "WHERE customerId=" . $customer->getCustomerId() . ") cp ";
$query .= "ON wostcp.customerPersonId = cp.customerPersonId ";
$query .= "WHERE wost.workOrderId = " . intval($workOrderId) . " ";
$query .= "ORDER BY wost.workOrderStatusTimeId DESC;";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1591647694', 'Hard DB error', $db);
    die(); // die without echoing any HTML
}
while ($row = $result->fetch_assoc()) {
    $wosts[] = $row;
}
$prevWorkOrderStatusTimeId = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['workOrderStatusTimeId'] == $prevWorkOrderStatusTimeId) {
        // Same status, should differ only in legacyInitials, append that 
        $wosts[count($wosts)-1]['legacyInitials'] .= ',' . $row['legacyInitials']; 
    } else {
        $wosts[] = $row;
    }
    $prevWorkOrderStatusTimeId = $row['workOrderStatusTimeId']; 
}
unset($prevWorkOrderStatusTimeId);
?>
<style>
    .thistable {
        font-size: 75%;
    }
</style>

<table border="1" cellpadding="2" cellspacing="2" class="thistable">
    <thead>
        <tr>
            <th>Status</th>
            <th>Extra</th>
            <th>Inserted</th>
            <th>Who</th>
            <th>Note</th>
        </tr>
    </thead>
    <tbody>
    <?php    
    // Use "for" rather than "foreach" so we can fiddle the case with multiple customerPersons
    for ($ikey=0; $ikey<count($wosts); ++$ikey) {
        $wost = $wosts[$ikey];
        ?>
            
        <tr>
            <?php /* "Status" */ ?>
            <td><?= $wost['statusName'] ?></td>

            <?php /* "Extra": subtable with legacyIntials of any customerPerson(s) specifically called out in the status */ ?> 
            <td>
                <?php
                if ($wost['legacyInitials']) {
                    $legacyInitialsArray = explode(',', $wost['legacyInitials']);
                    if ($legacyInitialsArray) {
                        ?>
                        <table border="0" cellpadding="1" cellspacing="0">
                        <?php  
                        foreach ($legacyInitialsArray as $legacyInitials) { ?>    
                            <tr><td valign="top">&gt;</td><td valign="top"><?= $legacyInitials ?></td></tr>
                        <?php } ?>
                        </table>
                        <?php
                    }
                } // else blank cell
                ?>
            </td>
            
            <?php /* "Inserted": date associated with this status */ ?>
            <td><?= date("m/d/Y", strtotime($wost['inserted'])) ?></td>
    
            <?php /* "Who": formatted name of person who inserted the status */ ?>
            <td>
                <?php
                if (intval($wost['personId'])) {
                    $pp = new Person($wost['personId']);
                    if (intval($pp->getPersonId())) {
                        echo $pp->getFormattedName(1);
                    }
                }
                ?>
            </td>       
    
            <?php /* "Note" */ ?>
            <td><?= $wost['note'] ?></td>
        </tr>
    <?php    
    }
    ?>
    </tbody>
</table>
