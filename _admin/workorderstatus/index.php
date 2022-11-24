<?php 
/*  _admin/workorderstatus/index.php

    EXECUTIVE SUMMARY: page to manage workOrderStatuses
    
    Completely reworked for v2020-3.
    See http://sssengwiki.com/EORs%2C+stamps%2C+etc

    No primary input, because this looks at all workOrderStatuses.

    INPUT: Optional $_REQUEST['act']: supported values are 'update', 'addStatus', 'deleteStatus', and 'changeParent'. 
    
    'update' takes the following additional inputs:
        * $_REQUEST['initialStatusId']: workOrderStatusId of new initial status.
        * $_REQUEST['reactivateStatusId']: workOrderStatusId of new status to use for reactivate.
        * For each defined workOrderStatusId 'WOSID':
            * $_REQUEST['statusName_WOSID'] (e.g. $_REQUEST['statusName_1']; similarly below
            * $_REQUEST['successorId_WOSID']
            * $_REQUEST['grace_WOSID']                                             
            * $_REQUEST['isDone_WOSID']
            * $_REQUEST['canNotify_WOSID']
            * $_REQUEST['color_WOSID']
            * $_REQUEST['notes_WOSID']
            * $_REQUEST['active_WOSID']
            * $_REQUEST['parentId_WOSID'] (as of 2020-06-03, this is ignored)
            * $_REQUEST['displayOrder_WOSID']
            
    'addStatus' takes the following additional input:
        * $_REQUEST['parentId']
    
    'deleteStatus' takes the following additional input:
        * $_REQUEST['workOrderStatusId']
    
    'changeParent' takes the following additional input:
        * $_REQUEST['childId']
        * $_REQUEST['parentId']
    
    We assume here that there is always at least one existing workOrderStatus (a safe assumption, some have been around for years). It is likely
    that some of this would fail on a completely empty table.
*/

include '../../inc/config.php';
$db = DB::getInstance();

$errorMessage = ''; // To show to the user. We also use this as a sentinel to indicate that there was an error.

// Suck the whole workOrderStatus table into memory (except columns about who created/modified, etc.)
// >>>00006: might want to create a WorkOrderStatus class, make this query a method, maybe be explicited
//  about ORDERED BY as well
$query = "SELECT workOrderStatusId, statusName, grace, successorId, parentId, displayOrder, ";
$query .= "isInitialStatus, useForReactivate, isDone, canNotify, color, notes, active ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatus;";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1590785327', 'Hard DB error', $db);
    echo "Hard DB error, see log\n";
    die();
}
$workOrderStatuses = Array();
while ($row = $result->fetch_assoc()) {
    $workOrderStatuses[] = $row;
}

// ACTIONS
if ($act == 'update') {
    // ALTHOUGH parentId values are in the inputs, this action currently does NOT make changes to parentId, which
    // are handled through a specialized action.
    
    // >>>00002, >>>00016: need input validation here. Should be fine for the moment: we shouldn't be sending anything bad
    //  from client side, and this is in the Apache-protected _admin folder.
    
    // >>>00006: Again, might be worth pushing a bunch of this into a WorkOrderStatus class: all we would do here is validate
    //  inputs, copy $_REQUEST to kill any extraneous values, and pass that to a method that does the real work here and returns
    //  any $errorMessage
    
    $updates = Array(); // Associative array, one entry per workOrderStatus for the ones we are changing; 
                        //  slightly artificial keys because we can't use numeric keys in an associative
                        //  array so, for example, for workOrderStatus == 2 (primary key for a row in DB table workOrderStatus), 
                        //  the array index will be 'ws_2'. 
                        // Next level of array is column name in DB table workOrderStatus; final level is new value for that row and column.
                        // So, for example $updates[6]['successorId'] == 0 means:
                        //   For 'Can start' (workOrderStatus == 6), the successorId is 0 (use "Reactivate" status)
    
    // There is exactly one initial status and exactly one reactivate status.
    // The DB doesn't enforce this rule: it is enforced by the logic of this page using radio buttons.
    $newInitialStatusId = intval($_REQUEST['initialStatusId']);
    $newReactivateStatusId = intval($_REQUEST['reactivateStatusId']);
    
    foreach ($workOrderStatuses as $workOrderStatus) {
        $workOrderStatusId = $workOrderStatus['workOrderStatusId'];

        // Record any change to initial status ---
        if ($workOrderStatusId == $newInitialStatusId &&  
            $workOrderStatus['isInitialStatus'] != 1 ) 
        {
            // Set the new value
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['isInitialStatus'] = 1;
        }
        
        if ($workOrderStatus['workOrderStatusId'] != $newInitialStatusId &&  
            $workOrderStatus['isInitialStatus'] == 1 ) 
        {
            // Unset the old value
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['isInitialStatus'] = 0;
        }
        
        // Record any change to status upon reactivation ---
        if ($workOrderStatusId == $newReactivateStatusId &&  
            $workOrderStatus['useForReactivate'] != 1 ) 
        {
            // Set the new value
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['useForReactivate'] = 1;
        }

        if ($workOrderStatusId != $newReactivateStatusId &&  
            $workOrderStatus['useForReactivate'] == 1 ) 
        {
            // Unset the old value
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['useForReactivate'] = 0;
        }
            
        // From here down, values for each status are independent of any other status
        //  (except perhaps for validation issues such as name conflicts)
        //  so just look at the new value and set it if it's a change.
        
        // Remember that displayOrder is only meaningful within same parentId.
        $displayOrder = intval($_REQUEST["displayOrder_$workOrderStatusId"]);
        if ($displayOrder != $workOrderStatus['displayOrder']) { 
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['displayOrder'] = $displayOrder; 
        }
        
        $statusName = trim($_REQUEST["statusName_$workOrderStatusId"]);
        if ($statusName != $workOrderStatus['statusName']) {
            // >>>00002, >>>00016: should check for conflict with any other statusName (it's already checked client-side, though)
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['statusName'] = $statusName;
            // >>>00002, >>>00016: should deal with truncation
        }
        
        $successorId = intval($_REQUEST["successorId_$workOrderStatusId"]);
        if ($successorId != $workOrderStatus['successorId']) {
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['successorId'] = $successorId; 
        }
        
        $grace = intval($_REQUEST["grace_$workOrderStatusId"]);
        if ($grace != $workOrderStatus['grace']) {
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['grace'] = $grace; 
        }
                
        // Slightly tricky here for checkbox: not explicit in $_REQUEST if not checked
        $isDone = (array_key_exists("isDone_$workOrderStatusId", $_REQUEST) && $_REQUEST["isDone_$workOrderStatusId"]) ? 1 : 0;
        if ($isDone != $workOrderStatus['isDone']) {
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['isDone'] = $isDone; 
        }
        
        $canNotify = trim($_REQUEST["canNotify_$workOrderStatusId"]); 
        if ($notes != $workOrderStatus['canNotify']) { 
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['canNotify'] = $canNotify;
            // >>>00002, >>>00016: should deal with truncation
        }
        
        $notes = trim($_REQUEST["notes_$workOrderStatusId"]); 
        if ($notes != $workOrderStatus['notes']) { 
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['notes'] = $notes;
            // >>>00002, >>>00016: should deal with truncation
        }
        
        $color = strtolower(trim($_REQUEST["color_$workOrderStatusId"]));
        $colorOk = preg_match('/^#[a-f0-9]{6,6}$/', $color); 
        
        if ($colorOk === false) {
            $errorMessage = 'Error in preg_match checking color';
            $logger->error2('1591029730', $errorMessage);
        } else if ($colorOk == 0) {
            $errorMessage = 'Invalid color "' . $color . '", should be pound sign ("#") and six hex characters';
            $logger->error2('1591029733', $errorMessage);
        } else {
            $color = substr($color, 1); // get rid of the leading pound sign ("#")
            if ($color != $workOrderStatus['color']) { 
                if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                    $updates['ws_'.$workOrderStatusId] = Array();
                }
                $updates['ws_'.$workOrderStatusId]['color'] = $color; 
            }
        }

        // Slightly tricky here for checkbox: not explicit in $_REQUEST if not checked
        $isActive = (array_key_exists("active_$workOrderStatusId", $_REQUEST) && $_REQUEST["active_$workOrderStatusId"]) ? 1 : 0;
        if ($isActive != $workOrderStatus['active']) {
            if ( ! array_key_exists('ws_'.$workOrderStatusId, $updates) ) {
                $updates['ws_'.$workOrderStatusId] = Array();
            }
            $updates['ws_'.$workOrderStatusId]['active'] = $isActive; 
        }        
    }
    // At this point, $updates is filled in.

    $startedTransaction = false;
    if (!$errorMessage) {
        $query = 'START TRANSACTION;';
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1591038799', 'Hard DB error', $db);
            $errorMessage .= "Hard DB error on $query<br>";
        }
    }
        
    if (!$errorMessage) {
        $startedTransaction = true;
        foreach($updates AS $ws => $update) {
            $workOrderStatusId = intval(substr($ws, 3));
            
            $oldStatusName = '???';
            foreach($workOrderStatuses as $workOrderStatus) {
                if ($workOrderStatus['workOrderStatusId'] == $workOrderStatusId) { 
                    $oldStatusName = $workOrderStatus['statusName']; // "old" because there could be a name change.
                }
            }
            
            $query = "UPDATE workOrderStatus SET ";
            $firstOne = true;
            
            foreach($update AS $propertyName=>$value) {
                if ($firstOne) {
                    $firstOne = false;
                } else {
                    $query .= ", ";
                }
                if ($propertyName == 'statusName' || $propertyName == 'notes' || $propertyName == 'color') {
                    // update a text value
                    $query .= "$propertyName='" . $db->real_escape_string($value) . "'";
                } else {
                    // update a numeric value
                    $query .= "$propertyName=$value";
                }
    
                $logger->info2('1591037909', "Admin changing data for workOrderStatus. $oldStatusName ($workOrderStatusId): " . 
                    "$propertyName changes from '{$workOrderStatus[$propertyName]}' to '$value'");
            }
            $query .= " WHERE workOrderStatusId = $workOrderStatusId;";
            
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1591038817', 'Hard DB error', $db);
                $errorMessage .= "Hard DB error on update, see log. <br>";
            }        
        } // foreach($updates AS $ws => $update)
    } // if (!$errorMessage) {    
        
    if (!$errorMessage) {
        $query = 'COMMIT;';
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1591038833', 'Hard DB error on COMMIT', $db);
            $errorMessage .= "Hard DB error on $query<br>";            
        }
    }
    
    if ($errorMessage && $startedTransaction) {
        $query = 'ROLLBACK;';
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1591038837', 'Hard DB error on ROLLBACK', $db);
            $errorMessage .= "Hard DB error on $query<br>";            
        }
    }
    
    if (!$errorMessage) {    
        // Reload page cleanly (no $act)
        header("Location: index.php");
        die();
    } // else we haven't modified the DB, and will display the error
} // END if ($act == 'update')

if ($act == 'addStatus') {
    // >>>00002, >>>00016: need input validation here. Should be fine for the moment: we shouldn't be sending anything bad
    //  from client side, and this is in the Apache-protected _admin folder.
    
    // >>>00006: Again, might be worth pushing a bunch of this into a workOrderStatus class; I'm going to stop repeating that.

    $parentId = intval($_REQUEST['parentId']); // always add under a particular parent
    
    // Find out the maximum existing workOrderStatusId (not auto-incremented!) and 
    //  the maximum existing displayOrder under this parent, so we can use the next value.
    $maxWorkOrderStatusId = -1;
    $maxDisplayOrder = -1;
    foreach ($workOrderStatuses AS $workOrderStatus) {
        $maxWorkOrderStatusId = max($maxWorkOrderStatusId, $workOrderStatus['workOrderStatusId']);
        if ($workOrderStatus['parentId'] == $parentId) {
            $maxDisplayOrder = max($maxDisplayOrder, $workOrderStatus['displayOrder']);
        }
    }    
    
    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderStatus (";
    $query .= "workOrderStatusId, statusName, grace, parentId, displayOrder";
    $query .= ") VALUES (";
    $query .= ($maxWorkOrderStatusId+1) . ", ";
    $query .= "'UNNAMED_" . ($maxWorkOrderStatusId+1) . "', ";
    $query .= "2, ";  // grace
    $query .= "$parentId, ";
    $query .= ($maxDisplayOrder+1);
    $query .= ")";
    
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1591113933', 'Hard DB error', $db);
        echo "Hard DB error, see log\n";
        die();
    }
    // Reload page cleanly (no $act)
    header("Location: index.php");
    die();
}

if ($act == 'deleteStatus') {
    // >>>00002, >>>00016: need input validation here. Should be fine for the moment: we shouldn't be sending anything bad
    //  from client side, and this is in the Apache-protected _admin folder.
    
    $workOrderStatusId = intval($_REQUEST['workOrderStatusId']);
    
    // Several conditions preclude hard-delete. Some of these may be impossible to have arise,
    // but let's not presume. NOTE that the following loop applies even to inactive statuses.
    
    // First set of tests: is it referenced inside this table? Is it in use in a way that precludes deletion (e.g. it is active)?
    foreach ($workOrderStatuses AS $workOrderStatus) {
        if ($workOrderStatus['successorId'] == $workOrderStatusId) {
            $errorMessage = "Cannot hard-delete, it is successor to status " . $workOrderStatus['statusName'];
            break;
        }
        if ($workOrderStatus['parentId'] == $workOrderStatusId) {
            $errorMessage = "Cannot hard-delete, it is parent of status " . $workOrderStatus['statusName'];
            break;
        }
        if ($workOrderStatus['workOrderStatusId'] == $workOrderStatusId) {
            // This is the status we want to delete
            if ($workOrderStatus['isInitialStatus']) {
                $errorMessage = "Cannot hard-delete, {$workOrderStatus['statusName']} is the initial status";
                break;
            }
            if ($workOrderStatus['useForReactivate']) {
                $errorMessage = "Cannot hard-delete, {$workOrderStatus['statusName']} is the \"Reactivate\" status";
                break;
            }
            if ($workOrderStatus['active']) {
                $errorMessage = "Cannot hard-delete, {$workOrderStatus['statusName']} is active";
                break;
            }
        }
    }
    if (!$errorMessage) {
        // Now we look for foreign-key references from elsewhere in the DB: tables workOrder and workOrderStatusTime use workOrderStatusId.
        //
        // If there is any reference to this status in workOrder, then there must also be a reference in workOrderStatusTime,
        // so just check the latter. Nowhere else outside of this table itself checks this, and we've just examined this table itself.
        $query = "SELECT workOrderStatusTimeId FROM " . DB__NEW_DATABASE . ".workOrderStatusTime WHERE workOrderStatusId=$workOrderStatusId LIMIT 1;";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1591128299', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        }
        if ($result->num_rows != 0) {
            $row = $result->fetch_assoc();
            $errorMessage = "Cannot hard-delete, there is at least one reference to this workOrderStatus in DB table workOrderStatusTime, " . 
                "workOrderStatusTimeId=" . $row['workOrderStatusTimeId']; 
        }        
    }
    if (!$errorMessage) {        
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderStatus WHERE workOrderStatusId=$workOrderStatusId;";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1591128323', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        }
        // Reload page cleanly (no $act)
        header("Location: index.php");
        die();
    }
}

if ($act == 'changeParent') {
    // >>>00002, >>>00016: need input validation here. Should be fine for the moment: we shouldn't be sending anything bad
    //  from client side, and this is in the Apache-protected _admin folder.
    
    $parentId = intval($_REQUEST['parentId']);
    $childId = intval($_REQUEST['childId']);
    
    $query = "UPDATE " . DB__NEW_DATABASE . ".workOrderStatus ";
    $query .= "SET parentId=$parentId ";
    $query .= "WHERE workOrderStatusId=$childId;";
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1591136123', 'Hard DB error', $db);
        echo "Hard DB error, see log\n";
        die();
    }
    // Reload page cleanly (no $act)
    header("Location: index.php");
    die();
}
// DONE WITH ACTIONS.
// * If there was a successful action, we won't be here, because we will already 
//   have reloaded page and exited PHP. 
// * If there was an unsuccessful action, we are here and $errorMessage is set
// * If there was no action, we are here and $errorMessage is empty string.

// ---------------------------------------
// Stitch together the hierarchy
// ---------------------------------------
function cmpDisplayOrder($a, $b) {
    if ($a['displayOrder'] == $b['displayOrder']) {
        return 0;
    }
    return ($a['displayOrder'] < $b['displayOrder']) ? -1 : 1;
}

// recursive function, building hierarchy
function hierarchyUnderParent($parentId, $level=0) {
    global $workOrderStatuses;
    
    // Prevent this going into crazy recursion
    if ($level > 50) {
        $logger->error2('1591294417', 'Runaway recursion in function hierarchyUnderParent'); 
        exit();  
    }
    
    $ret = Array();
    foreach ($workOrderStatuses as $workOrderStatus) {
        if ($workOrderStatus['parentId'] == $parentId) {
            $workOrderStatus['children'] = hierarchyUnderParent($workOrderStatus['workOrderStatusId'], $level+1);
            $workOrderStatus['level'] = $level;
            $ret[] = $workOrderStatus;
        }
    }
    usort($ret, "cmpDisplayOrder");
    return $ret;
}
$workOrderStatusHierarchy = hierarchyUnderParent(0);

// ---------------------------------------
// Recursive function to display a workOrderStatus and its children
// We will use this below to build the table in the HTML.
// INPUT $workOrderStatus is an associative array with the canonical representation of  
//  data from a row in DB table workOrderStatus, plus $workOrderStatus['children'], 
//  which is an array of similar associative arrays with similar canonical representations.
//
// Since we've already been through to build the hierarchy, we know recursion won't run away.
// ---------------------------------------
function displayStatusRow($workOrderStatus) {
    global $workOrderStatusHierarchy; 
    
    $workOrderStatusId = $workOrderStatus['workOrderStatusId'];
    
    $disableIfInactive = $workOrderStatus['active'] ? '' : 'disabled'; // HTML attribute
?>        
    <tr class="<?= $workOrderStatus['active'] ? 'active' : 'inactive' ?>">
        <td> <?php /* Both visually and otherwise, this cell is a bit of a catch-all */ ?>
        
            <?php /* two hidden values; we put them somewhere convenient */ ?>
            <input class="parentId" type="hidden" name="parentId_<?= $workOrderStatus['workOrderStatusId'] ?>" value="<?= $workOrderStatus['parentId'] ?>">
            <input class="displayOrder" type="hidden" name="displayOrder_<?= $workOrderStatus['workOrderStatusId'] ?>" value="<?= $workOrderStatus['displayOrder'] ?>">
            
            <?php /* Status Name, indented to reflect level in hierarchy */ ?>
            <?php
                for ($i=0; $i< $workOrderStatus['level'] * 3; ++ $i) {
                    echo '&nbsp;';
                }
            ?>            
            <input class="statusName" name="statusName_<?= $workOrderStatusId ?>" type="text" value="<?= $workOrderStatus['statusName'] ?>">
            
            <?php /* device to move the row up or down */ ?>
            <span class="updown">&updownarrow;&nbsp;</span>&nbsp;<br />
            
            <?php /* buttons to change parent, or to add a child status */ ?>
            <div style="float:right">
                <button class="change-parent" type="button" data-childid="<?= $workOrderStatusId ?>">Change Parent</button>
                <button class="add-status" type="button" data-parentid="<?= $workOrderStatusId ?>">Add child</button>
            </div>
        </td>
        
        <?php /* Successor */ ?>
        <td>
        <select class="successor" name="successorId_<?= $workOrderStatusId ?>">
            <option value="0"<?=($workOrderStatus['successorId'] == 0 ? ' selected' : '') .
                ($workOrderStatus['useForReactivate'] ? ' disabled class="disabledForReactivate"' : '') ?>>(use "Reactivate" Status)</option>
            <?php displayWorkOrderStatusesAsOptions($workOrderStatusHierarchy, $workOrderStatusId, $workOrderStatus['successorId']); ?>
        </select>
        </td>
        
        <?php /* Grace */ ?>
        <td><input name="grace_<?= $workOrderStatusId ?>" type="number" min="0" max="365" size="4" value="<?= $workOrderStatus['grace'] ?>"></td>
        
        <?php /* Initial Status */ ?>
        <td><input class="isInitial" name="initialStatusId" type="radio" <?= $disableIfInactive ?> value="<?= $workOrderStatusId ?>" <?= ($workOrderStatus['isInitialStatus'] ? 'checked' : '') ?>></td>
        
        <?php /* "Reactivate" Status */ ?>
        <td><input class="isReactivate" name="reactivateStatusId" type="radio" <?= $disableIfInactive ?> value="<?= $workOrderStatusId ?>" <?= ($workOrderStatus['useForReactivate'] ? 'checked' : '') ?>></td>
        
        <?php /* Means "Done" */ ?>
        <td><input name="isDone_<?= $workOrderStatusId ?>" type="checkbox" <?= ($workOrderStatus['isDone'] ? 'checked' : '') ?>></td>
        
        <?php /* Can Notify */ ?>
        <td>
        <?php
        $isDone = $workOrderStatus['isDone'];
        $canNotify = $workOrderStatus['canNotify'];
        ?>
        <select class="canNotify" name="canNotify_<?= $workOrderStatusId ?>" <? ($isDone ? ' disabled' : '')?>>
            <?php 
            if ($isDone) {
            ?>    
                <option value="0" selected>Main inbox</option>
            <?php
            } else {
            ?>    
                <option value="0"<?=($canNotify == 0 ? ' selected' : '')?>>N/A</option>
                <option value="<?= CAN_NOTIFY_EORS ?>"<?=($canNotify == CAN_NOTIFY_EORS ? ' selected' : '')?>>EORs</option>
                <option value="<?= CAN_NOTIFY_EMPLOYEES ?>"<?=($canNotify == CAN_NOTIFY_EMPLOYEES ? ' selected' : '')?>>Any employee</option>
            <?php
            }
            ?>  
        </select>
        </td>
        
        <?php /* "Color" (palette) */ ?>
        <td><input name="color_<?= $workOrderStatusId ?>" type="text" class="full" value="#<?= $workOrderStatus['color'] ?>"/></td>
        
        <?php /* Note */ ?>
        <td><textarea name="notes_<?= $workOrderStatusId ?>" rows="2" cols="20" maxlength="256"><?= $workOrderStatus['notes'] ?></textarea></td>
        
        <?php /* Active */ ?>
        <td><input class="isActive" name="active_<?= $workOrderStatusId ?>" type="checkbox" <?= ($workOrderStatus['active'] ? 'checked' : '') ?>>
        <br /><button class="delete-status" type="button" style="float:right" data-workorderstatusid="<?= $workOrderStatusId ?>">Del</button>
        </td>
    </tr>
<?php
    foreach ($workOrderStatus['children'] as $child) {
        displayStatusRow($child);
    }
} // END function displayStatusRow

// recursive function to offer workOrderStatuses as HTML OPTIONs to choose successor
// At top level, INPUT $hierarchy should be $workOrderStatusHierarchy; as we go down it will be a structure of the same form
// INPUT $selfId: workOrderStatusId for this row
// INPUT $successorId: workOrderStatusId for current successor
// INPUT $level: 0 at top, increases on recursion
//
// Since we've already been through to build the hierarchy, we know recursion won't run away.
function displayWorkOrderStatusesAsOptions($hierarchy, $selfId, $successorId, $level=0) {
    foreach ($hierarchy as $workOrderStatus) {
        $workOrderStatusId = $workOrderStatus['workOrderStatusId'];
        echo "<option value=\"$workOrderStatusId\"";
        if ($workOrderStatus['workOrderStatusId'] == $successorId) {
            echo " selected";
        }
        if ($workOrderStatus['workOrderStatusId'] == $selfId || $workOrderStatus['active'] == 0) {
            echo " disabled";
        }
        echo ">";
        for ($i=0; $i< $workOrderStatus['level'] * 3; ++ $i) {
            echo '&nbsp;';
        }
        echo trim($workOrderStatus['statusName']);
        echo "</option>\n";
        displayWorkOrderStatusesAsOptions($workOrderStatus['children'], $selfId, $successorId, $level+1);
    }    
} // END displayWorkOrderStatusesAsOptions

// recursive function to offer workOrderStatuses as HTML OPTIONs to choose new parent
// NOTE that (1) this cannot be used once there have been changes, so it is OK that it is hardwired from
//  current data; must save before using this (which will refresh $workOrderStatusHierarchy).
// At top level, INPUT $hierarchy should be $workOrderStatusHierarchy; as we go down it will be a structure of the same form
// INPUT $level: 0 at top, increases on recursion
//
// Since we've already been through to build the hierarchy, we know recursion won't run away.
function displayWorkOrderStatusesForNewParent($hierarchy, $level=0) {
    foreach ($hierarchy as $workOrderStatus) {
        echo '<option value="' . $workOrderStatus['workOrderStatusId'] . '" class="' . ($workOrderStatus['active'] ? 'active' : 'inactive') . '">';        
        for ($i=0; $i< $workOrderStatus['level'] * 3; ++ $i) {
            echo '&nbsp;';
        }
        echo trim($workOrderStatus['statusName']);
        echo "</option>\n";
        displayWorkOrderStatusesForNewParent($workOrderStatus['children'], $level+1);
    }    
} // END displayWorkOrderStatusesForNewParent
?>

<!DOCTYPE html> 
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" type="text/css" href="../spectrum/spectrum.css">
    <script type="text/javascript" src="../spectrum/spectrum.js"></script>
    
    <style type="text/css">
        /* display color */ 
        .full-spectrum .sp-palette {
            max-width: 200px;
        }
        
        /* how our tables look */
        td, th {border:1px solid black;}
        
        /* show/hide "Save" and "Cancel" : visible only when something to save */
        body button.save-button, body button.cancel-button {
            display: none;
        }        
        body.show-save button.save-button, body.show-save button.cancel-button {
            display: inline;
        }
        
        /* show/hide the various buttons to add a status or change a parent : visible only when NOTHING to save,
           hence no changes yet. */
        body button.add-status, body button.change-parent {
            display: inline;
        }        
        body.show-save button.add-status, body.show-save button.change-parent {
            display: none;
        }
        
        /* show "Delete" only for inactive statuses */
        body tr.inactive button.delete-status {
            display: inline;
        }        
        tr.active button.delete-status, body.show-save tr.inactive button.delete-status {
            display: none;
        }
        
        /* gray out inactive rows */
        tr.inactive td, tr.inactive input, tr.inactive option, tr.inactive textarea {
            background-color: lightgray;
        }

        /* While moving a row (changing displayOrder), pink for the row we are moving */
        tr.active.updown-movethis td, tr.active.updown-movethis input, tr.active.updown-movethis option, tr.active.updown-movethis textarea,
        tr.inactive.updown-movethis td, tr.inactive.updown-movethis input, tr.inactive.updown-movethis option, tr.inactive.updown-movethis textarea {
            background: rgba(255, 0, 0, 0.4);
        }
        
        /* show/hide arrows for adjusting displayOrder */
        body.show-displayorder span.updown {
            display: inline;
        }
        body.dont-show-displayorder span.updown {
            display: none;
        }
        
        /* Once we have selected a row to move, we don't want to see the other arrows */
        body.moving span.updown {
            opacity: 0.0; 
        }
        /* Once we have selected a row to move, we want its arrow to turn red */
        body.moving span.updown.updown-movethis {
            opacity: 1.0;
            color: red;  
        }        
        tr.mark-above td {
            border-top: 4px solid red; /* Display position to move row whose displayOrder is being changed. */
        }
        tr.mark-below td {
            border-bottom: 4px solid red; /* Display position to move row whose displayOrder is being changed. */
        }
        span.updown {
            cursor:pointer; /* to indicate it can be clicked */
            font-weight:bold;
        }
    </style>
    <script>
        // Once they edit at all, we set the save button and warn if user tries to navigate away from page.
        // We don't try to be smart about whether things go back to their original state, >>>00032, though in principle, we could.
        //
        // See https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event for our somewhat non-standard approach to 'beforeunload'
        function beforeUnloadEvent(event) {
            // Cancel the event as stated by the standard.
            event.preventDefault();
            // Chrome requires returnValue to be set.
            event.returnValue = '';
        }
        function showSave() {
            $('body').addClass('show-save');
            window.addEventListener('beforeunload', beforeUnloadEvent);                    
        };
        $(function() { // on document ready
            // Restore state (scrolling, etc.) on "document ready"
            restoreState();
            
            // BEGIN: more related to the "Save" and "Cancel" buttons ----
            
            $('#main-table input, #main-table select, #main-table textarea').change(function() {
                showSave();
            });
            $('#main-table textarea').keyup(function(event) {
                var charCode = event.which || event.keyCode;
                if (event.key == "Escape") {
                    // ESC doesn't mean a change we need to save.
                } else if (charCode == 9 ) {
                    // tab doesn't mean a change we need to save.
                    // NOTE that for TEXTAREA, unlike INPUT, we *do* want to save ENTER 
                } else if (charCode >= 33 && charCode <= 40 ) {
                    // PgUp, PgDown, END, HOME, arrow keys don't mean a change we need to save
                } else {
                    showSave();
                }
            });
            $('#main-table input').keyup(function(event) {
                var charCode = event.which || event.keyCode;
                if (event.key == "Escape") {
                    // ESC doesn't mean a change we need to save.
                } else if (charCode == 9 || charCode == 13) {
                    // tab and enter don't mean a change we need to save
                } else if (charCode >= 33 && charCode <= 40 ) {
                    // PgUp, PgDown, END, HOME, arrow keys don't mean a change we need to save
                } else {
                    showSave();
                }
            });
            $('button.save-button').click(function(event) {
                // One piece of validation we want to do *before* submitting: make sure we don't have conflicting statusNames
                // Use an associative array here, representing membership in a set 
                var statusNames = [];
                var failed = false;
                $('input.statusName').each(function() {
                    var $this = $(this);
                    var statusName = $this.val();
                    if (statusName in statusNames) {
                        alert('Status name "' + statusName + '" appears twice, cannot save.');
                        failed = true;
                        return false; // break out of "each" loop
                    }
                    statusNames[statusName] = true;
                });
                
                if (failed) {
                    event.preventDefault(); // prevent save
                } else {
                    saveState(); // save scrolling, etc.
                    window.removeEventListener('beforeunload', beforeUnloadEvent);
                    // and, of course, this will also submit and save.
                }
            });
            $('button.cancel-button').click(function(event) {
                window.removeEventListener('beforeunload', beforeUnloadEvent);
                saveState(); // save scrolling, etc.
                window.location.reload(true); 
            });
        
            // END: related to the "Save" and "Cancel" buttons ----
            // --------------------------------------
            
            // --------------------------------------
            // Show/hide instructions
            // --------------------------------------
            $('#hide-instructions').click(function() {
                $('#instructions').hide();
                $('#show-instructions').show();
            });
            $('#show-instructions').click(function() {
                $('#instructions').show();
                $('#show-instructions').hide();
            });
            // --------
            
            // --------------------------------------
            // Don't let ENTER have the side-effect of submitting the form
            // --------------------------------------
            $('#main-table input').keydown(function(event) {
                var charCode = event.which || event.keyCode;
                if (charCode == 13 ) {
                    event.stopPropagation();
                    event.preventDefault();
                    return false;
                }
            });
        
            // --------------------------------------
            // Now we get into the code behind individual INPUT elements in the main form/table.
            // NOTE that changing these cells does NOT do an immediate save.
            // --------------------------------------
            
            // "Active" -----
            $('input.isActive').change(function() {
                var $this = $(this);                                                             
                var $row = $this.closest('tr');
                var workOrderStatusId = $this.attr("name").split('_')[1];
            
                // Impose limits on when we can deactivate a row.
                // NOTE that when we detect that a rule is broken, we 
                //  set $this.prop('checked', true), so the next test
                //  can look at that as a condition. Also cleans up the
                //  invalid deactivation.
                if (!$this.prop('checked')) {
                    if ($('input.isInitial', $row).prop('checked')) {
                        alert('Cannot deactivate the initial status');
                        $this.prop('checked', true);
                    }
                }
                if (!$this.prop('checked')) {
                    if ($('input.isReactivate', $row).prop('checked')) {
                        alert('Cannot deactivate the "Reactivate" status');
                        $this.prop('checked', true);
                    }
                }
                if (!$this.prop('checked')) {
                    $('select.successor').each(function() {
                        var $sel = $(this);
                        var selIsActive = $sel.closest('tr').find('input.isActive').prop('checked'); 
                        if (selIsActive && $sel.val() == workOrderStatusId) {
                            $this.prop('checked', true);
                            alert('Cannot deactivate a status that is used as a successor (used by ' +
                                $sel.closest('tr').find('input.statusName').val() + ')');
                            return false; // break out of "each" loop
                        }
                    });
                }
                if (!$this.prop('checked')) {
                    $('input.parentId').each(function() {
                        var $parentInfo = $(this);
                        if ($parentInfo.val() == workOrderStatusId) {
                            var childWorkOrderStatusId = $parentInfo.attr("name").split('_')[1];
                            if ($('input[name="active_' + childWorkOrderStatusId + '"]').prop('checked')) {
                                $this.prop('checked', true);
                                alert('Cannot deactivate a status that has active child statuses.');
                                return false; // break out of "each" loop
                            }
                        }
                    });
                }
                
                // Appropriate settings for graying row and for whether radio buttons on this row can be used.  
                let isActive = $this.prop('checked');
                if (isActive) {
                    $row.addClass('active').removeClass('inactive');
                    $('input[type="radio"]', $row).prop('disabled', false);
                } else {
                    $row.removeClass('active').addClass('inactive');
                    $('input[type="radio"]', $row).prop('disabled', true);
                }
                
                // When we change whether a status is active, we need to fiddle options in the "successor" dropdown
                // An inactive status may never be the "Reactivation" status, so that doesn't come into play here.
                $('select.successor option[value="' + workOrderStatusId + '"]').each(function() {
                    let $this2 = $(this);
                    $this2.prop('disabled', !isActive);
                });                
            });
            
            // "StatusName" -----
            $('input.statusName').change(function() {
                var $this = $(this);
                var newName = $this.val();
                $('input.statusName').not($this).each(function() {
                    var $other = $(this); 
                    if (newName==$other.val()) {
                        alert('Warning: there is already a status with name "' + newName + '". You must fix this, or Save will fail'); 
                    }
                });
            });
            
            // ""Reactivate" Status" -----
            // When we change which status is the "Reactivate" status, we need to fiddle options in the "successor" dropdown
            $('input.isReactivate').click(function() {
                let workOrderStatusId = $('input.isReactivate:checked').val();  // workOrderStatusId for the "reactivate" status 
                $('select.successor option[value="0"]').each(function() {
                    let $this = $(this);
                    if ($this.closest('select').attr('name').split('_')[1] == workOrderStatusId) {
                        // this is the "reactivate" status...
                        $this.prop('disabled', true);  // ... so it doesn't make any sense to say its successor is the "reactivate" status
                        $this.addClass('disabledForReactivate'); // Identify this so we know WHY this is disabled, and can reenable if it no longer applies
                    } else if ($this.hasClass('disabledForReactivate')) {
                        $this.prop('disabled', false);
                        $this.removeClass('disabledForReactivate');
                    }
                });
            });
            
            // --------------------------------------
            // Adding and deleting statuses
            // --------------------------------------
            $('button.add-status').click(function(event) {
                let $this = $(this);
                event.preventDefault();
                let form = '<form id="add-status-form" action="index.php" method="post">\n' + 
                    '<input type="hidden" name="act" value="addStatus">\n' +
                    '<input type="hidden" name="parentId" value="' + $this.data('parentid') + '">\n' +
                    '</form>';
                $('body').append($(form));
                saveState(); // save scrolling, etc., because on success we will reload
                $('#add-status-form').submit();
            });
            
            $('button.delete-status').click(function(event) {
                let $this = $(this);
                event.preventDefault();
                let form = '<form id="delete-status-form" action="index.php" method="post">\n' + 
                    '<input type="hidden" name="act" value="deleteStatus">\n' +
                    '<input type="hidden" name="workOrderStatusId" value="' + $this.data('workorderstatusid') + '">\n' +
                    '</form>';
                $('body').append($(form));
                saveState(); // save scrolling, etc., because on success we will reload
                $('#delete-status-form').submit();
            });
            
            // --------------------------------------
            // Change parent: this launches a dialog
            // --------------------------------------
            // The DIV that is the basis for the dialog is a permanent, normally-hidden part of the page.
            // We tailor it to the specific workOrderStatus for which we are changing the parent.
            $('button.change-parent').click(function(event) {
                event.preventDefault();
                let $this = $(this);
                let childId= $this.data('childid');
                let parentId= $('input.parentId[name="parentId_' +  childId + '"]').val(); // current parent
                let isActive = $this.closest('tr').find('input.isActive').prop('checked'); // determine whether child is an active status
                
                $('#change-parent-select').find('option').prop('disabled', false);  // enable all statuses in the select
                if (isActive) {
                    // child is active, so disable inactive statuses from becoming its parent
                    $('#change-parent-select').find('option.inactive').prop('disabled', true);
                }
                
                // BEGIN disable from becoming a child of itself or its own descendant
                // Look at all rows in the table
                $('#main-table tbody tr').each(function() {
                    let $this_2 = $(this);
                    // Arbitrary convenient place to get the workOrderStatusId for this row. 
                    let workOrderStatusId = $this_2.find('input.statusName').attr('name').split('_')[1];
                    let scanningWorkOrderStatusId = workOrderStatusId;
                    let preventRunaway = 0;
                    while (scanningWorkOrderStatusId != childId && scanningWorkOrderStatusId != 0 && ++preventRunaway<10) {
                        scanningWorkOrderStatusId = $this_2.find('input.parentId').val();
                        // Let $this_2 travel up the hierarchy with the scan
                        // Cheating a little: we know where to find scanningWorkOrderStatusId as a value on the relevant row 
                        $this_2 = $('input.isInitial[value="' + scanningWorkOrderStatusId + '"]').closest('tr');
                    }
                    if (preventRunaway == 10) {
                        alert("runaway 1591318426, please ask an administrator or developer to look into this"); // client-side, so we can't log 
                    }
                    if (scanningWorkOrderStatusId == childId) {
                        // workOrderStatusId was self or descendant
                        $('#change-parent-select').find('option[value="' + workOrderStatusId + '"]').prop('disabled', true); // disable from becoming own parent
                    }
                      
                });
                
                
                
                // END disable from becoming a child of itself or its own descendant
                
                
                $('#change-parent-select').find('option[value="' + parentId + '"]').prop('selected', true); // select the current parent
                
                // Show the dialog
                $('#change-parent-dialog').show().dialog({
                    title: 'New parent for ' + $this.closest('tr').find('input.statusName').val(),
                    minWidth: 400,
                    minHeight: 400,
                    closeText: '',
                    buttons: {
                        "Save": function() {
                            // save by creating and submitting a FORM.
                            let form = '<form id="change-parent-form" action="index.php" method="post">\n' + 
                                 '<input type="hidden" name="act" value="changeParent">\n' +
                                 '<input type="hidden" name="childId" value="' + $this.data('childid') + '">\n' +
                                 '<input type="hidden" name="parentId" value="' + $('#change-parent-select').val() + '">\n' +
                                 '</form>';
                             $('body').append($(form));
                             saveState(); // save scrolling, etc.
                             window.removeEventListener('beforeunload', beforeUnloadEvent);
                             $('#change-parent-form').submit();
                             
                             // We'll be loading a new page, so we don't care what happens to the dialog 
                        },
                        "Cancel": function() {
                            $('#change-parent-dialog').dialog('close');
                        }
                    },
                    close: function() {
                        // Hide $('#change-parent-dialog'), which is otehrwise left alone, and destroy the dialog we built from it
                        // 
                        $('#change-parent-dialog').hide().dialog('destroy');
                    }                     
                });
            });
            
            // --------------------------------------
            // Save and restore state, so we can reload page while preserving certain user choices.
            // --------------------------------------
            // Save state of #show-displayorder, and scrolling
            //  so we can restore it after page reload
            function saveState() {
                let showDisplayOrder = $('#show-displayorder').is(':checked');
                let showInstructions = $('#instructions').is(':visible');
                let scrollTopMain = $(document).scrollTop();
                let scrollTopLeftSide = $('#left-side-main').scrollTop();
                
                sessionStorage.setItem('adminWorkOrderStatus_showDisplayOrder', (showDisplayOrder ? 'true' : 'false'));
                sessionStorage.setItem('adminWorkOrderStatus_showInstructions', (showInstructions ? 'true' : 'false'));
                sessionStorage.setItem('adminWorkOrderStatus_scrollTopMain', scrollTopMain);
                sessionStorage.setItem('adminWorkOrderStatus_scrollTopLeftSide', scrollTopLeftSide);
            }
            
            // Restores state saved with saveState(), then deletes it from sessionStorage 
            function restoreState() {
                let showDisplayOrder = sessionStorage.getItem('adminWorkOrderStatus_showDisplayOrder') == 'true';
                let scrollTopLeftSide = sessionStorage.getItem('adminWorkOrderStatus_scrollTopLeftSide');
                let scrollTopMain = sessionStorage.getItem('adminWorkOrderStatus_scrollTopMain');
                
                // Special case here because we treat NULL as true.
                let showInstructions = sessionStorage.getItem('adminWorkOrderStatus_showInstructions') === null ||   
                                       sessionStorage.getItem('adminWorkOrderStatus_showInstructions') == 'true';
                
                if (showDisplayOrder) {
                    $('#show-displayorder').prop('checked', true);
                    showOrHideDisplayOrder();        
                }
                if (showInstructions == false) {
                    $('#instructions').hide();
                    $('#show-instructions').show();
                }                
                $(document).scrollTop(scrollTopMain);
                $('#left-side-main').scrollTop(scrollTopLeftSide);
            
                sessionStorage.removeItem('adminWorkOrderStatus_showDisplayOrder') == 'true';
                sessionStorage.removeItem('adminWorkOrderStatus_scrollTopLeftSide');
                sessionStorage.removeItem('adminWorkOrderStatus_scrollTopMain');
            }

            // --------------------------------------
            // Changing displayOrder
            // --------------------------------------
            // It will be rare that they want to change displayOrder, so don't distract them with the mechanism & instructions
            // when they don't want it. Normally they just get a checkbox they can check to show this.
            function showOrHideDisplayOrder() {
                if ($('#show-displayorder').is(':checked')) {
                    $('body').addClass('show-displayorder');
                    $('body').removeClass('dont-show-displayorder');
                    $('#displayorder-instructions').show();
                } else {
                    $('body').addClass('dont-show-displayorder');
                    $('body').removeClass('show-displayorder');
                    $('#displayorder-instructions').hide();
                }
            }
            
            // Click to show/hide the updown arrows that let you change display order;
            //  also show/hide relevant instructions
            // NOTE that elsewhere in the code, this button will be "locked" at checked=true 
            //  while we are actually changing a display order
            $('#show-displayorder').change(function() {
                showOrHideDisplayOrder();
            });
            
            // Click to begin change of displayOrder for a status
            // NOTE Because one of the effects of being in the mode where we are moving a status row is to hide these updown arrows (in each
            //  span.updown), we know that if it was possible to click on a span.updown, we weren't in the middle of moving a different status row.
            // >>>00001: we could disable a whole bunch of HTML INPUTs during this; I (JM) haven't bothered. Might be worth doing.
            $('span.updown').click(function() {
                let $this = $(this);
                
                let $this_tr = $this.closest('tr');
                // navigate from span.updown to the cell it shares with input.statusName, grab the name attribute and, from that 
                //  atribute value, grab the workOrderStatusId. 
                let workOrderStatusIdToMove = $this.closest('td').find('input.statusName').attr('name').split('_')[1];
                
                // Mark the row we are moving; it will turn pink.
                $this_tr.addClass('updown-movethis'); 
                
                // >>>00001 Need to work out why descendants aren't getting marked in pink
                
                let descendantWorkOrderStatusIds = []; // declare here so it is in scope for function addDescendantWorkOrderStatusIds 
                // recursive function 
                function addDescendantWorkOrderStatusIds(parentId) {
                    $('input.parentId').each(function() {           // go through All the input.parentId elements
                        let $parentInfo = $(this);
                        if ($parentInfo.val() == parentId) {        // if it matches the parentId we are looking for...
                            var childWorkOrderStatusId = $parentInfo.attr("name").split('_')[1]; // ... grab its workOrderStatusId... 
                            descendantWorkOrderStatusIds.push(childWorkOrderStatusId);           // ... put that in descendantWorkOrderStatusIds ...  
                            addDescendantWorkOrderStatusIds(childWorkOrderStatusId);             // ... and recurse to find its children.
                        }
                    });
                }
                
                addDescendantWorkOrderStatusIds(workOrderStatusIdToMove); // Call the recursive function to find children of the status to be moved
                
                // For each child...
                for (let i in descendantWorkOrderStatusIds) {
                    let workOrderStatusId = descendantWorkOrderStatusIds[i];
                    // cheating a little here for a place we know we can find the workOrderStatusId as a value
                    let $row = $('input.isInitial[value="' + workOrderStatusId + '"]').closest('tr');
                    $row.addClass('updown-movethis');
                }
                $('body').addClass('moving').removeClass('not-moving');
                $('#show-displayorder').prop('disabled', true); // It's *necessarily* checked right now, don't let it be unchecked.
                
                <?php /* not using tooltips etc. here right now, so commenting this out. JM 2020-06-02
                // Bit of a cheat here: update the tooltip:
                $("#expanddialog").html("Setting display order for this node");
                
                $('<div id="updown-instructions" style="position:fixed; top:10px; left:250px; background-color:rgba(255, 255, 102, 1.0); border:2px solid black;">' + 
                    'Move cursor, then click to change display order.<br />ESC to cancel.</div>').appendTo('body');
                // Wait 3 seconds, then fade out for .3 seconds & remove
                window.setTimeout(
                    function() {
                        $('#updown-instructions').fadeOut(300, function() {
                            $(this).remove();
                        })
                    },            
                    3000
                );
                */
                ?>
                
                let parentId = $('input.parentId[name="parentId_' + workOrderStatusIdToMove + '"]').val(); 
                
                // What we want is NOT all jQuery siblings! We have a notion of siblinghood here that is narrower than 
                //  the DOM's notion. *All* TR elements here are jQuery/ DOM siblings, but we only want the once with the same parentId.
               
                // sibling items 
                let $allSiblings = $this_tr.siblings('tr').filter(
                    function() {
                        let $sib = $(this);
                        let sibParentId = $('input.parentId', $sib).val();
                        return sibParentId == parentId;
                    }
                );
                
                $allSiblings.addClass('updown-sibling'); // mark the siblings
                
                // For each sibling that is in the table BEFORE the one we are moving, if the user hovers over it,
                // we want to use its upper TD borders to make a red line indication the new position you would 
                // get by clicking on this row.
                $this_tr.prevAll().filter($allSiblings).on('mouseenter.updown mousemove.updown', event, function() {
                    let $this2 = $(this);
                    $allSiblings.removeClass('mark-above').removeClass('mark-below'); // get rid of any TD borders used to show potential new position
                                                                                      // (shouldn't be any, but let's be safe)
                    $this2.addClass('mark-above');
                });
                // For each sibling that is in the table AFTER the one we are moving, if the user hovers over it,
                // we want to use its lower TD borders to make a red line indication the new position you would 
                // get by clicking on this row.
                $this_tr.nextAll().filter($allSiblings).on('mouseenter.updown mousemove.updown', event, function() {
                        let $this2 = $(this);
                        $allSiblings.removeClass('mark-above').removeClass('mark-below'); // get rid of any TD borders used to show potential new position
                                                                                          // (shouldn't be any, but let's be safe)
                        $this2.addClass('mark-below');
                    });
                // Leave row => turn off those red borders
                $allSiblings.on('mouseleave.updown', function() {
                    $(this).removeClass('mark-above').removeClass('mark-below');
                });
                
                // For each sibling that is in the table BEFORE the one we are moving, if the user clicks on it
                // we want to move the row we are moving (and any children) before this row
                $this_tr.prevAll().filter($allSiblings).on('click.updown', function() {
                     changeDisplayOrder($('tr.updown-movethis'), 'before', $(this), $allSiblings);
                     closeUpDown();
                });
                // For each sibling that is in the table AFTER the one we are moving, if the user clicks on it
                // we want to move the row we are moving (and any children) after this row and its children
                $this_tr.nextAll().filter($allSiblings).on('click.updown', function() {
                     changeDisplayOrder($('tr.updown-movethis'), 'after', $(this), $allSiblings);
                     closeUpDown();
                });
                
                // Anywhere in the document, ESC closes changing displayOrder
                $('body').on('keydown.updown', function(e) {
                    if (e.key == "Escape") {
                        closeUpDown();
                    }
                });
                
                // Turn off everything related to changing display of a status row; this takes us back to allowing 
                //  the user to select a different row to move.
                function closeUpDown() {
                    $('#updown-instructions').remove();
                    $('tr').removeClass('updown-sibling').removeClass('mark-above').removeClass('mark-below');
                    $('*').off('.updown');
                    $('tr').removeClass('updown-movethis');
                    $('body').removeClass('moving').addClass('not-moving');
                    // Bit of a cheat here: update the tooltip:
                    $("#expanddialog").html("Click to set display order");
                    $('#show-displayorder').prop('disabled', false); // It's necessarily checked right now, let it be unchecked.
                }
            });
        });

        // Change displayOrder for $rowToMove (and its child statuses); new position is relative to $relativeToRow 
        // INPUT $rowToMove - jQuery object for row to move
        // INPUT beforeOrAfter - 'before' or 'after': how its new position compares to $relativeToRow
        // INPUT $relativeToRow - jQuery object for row we will be placing $rowToMove immediately before or after
        // INPUT $allSiblings - all the sibling rows (excluding $rowToMove, which we will merge in) 
        function changeDisplayOrder($rowToMove, beforeOrAfter, $relativeToRow, $allSiblings) {            
            // Recursive function to find the last descendant of a status row.
            // We rely on $statusRow and local variable $lastRowWithParent each referencing at most a single row
            //  (which our code assures will be the case).
            function $findLastDescendant($statusRow) {
                // Arbitrary convenient place to get the workOrderStatusId for this row. 
                let workOrderStatusId = $statusRow.find('input.statusName').attr('name').split('_')[1];
                
                // What is the last row with this as parent (if any)?
                let $lastRowWithParent = $('input.parentId[value="' + workOrderStatusId + '"').last().closest('tr');
                if ($lastRowWithParent.length) {
                    return $findLastDescendant($lastRowWithParent);
                } else {
                    return $statusRow;
                }
            }
            
            if (beforeOrAfter == 'before') {
                $rowToMove.detach().insertBefore($relativeToRow);
            } else {
                // This is trickier: >>>00026 we need to find its last child 
                $rowToMove.detach().insertAfter($findLastDescendant($relativeToRow));
            }
            let displayOrder = 0;
            $allSiblings.add($rowToMove).each(function() {
                 $(this).find('input.displayOrder').val(displayOrder++);
                 showSave();
            });
        }
    </script>
</head>
<body bgcolor="#ffffff" class="not-moving dont-show-displayorder">
    <div style="font-weight:bold; color:red"><?= $errorMessage ?></div>
    <p><span style="color:red; font-weight:bold">WARNING: Editing these statuses can radically alter the system!<span> If you are not
      certain what you are doing, be very wary of editing anything other than the notes.</p>
    <div id="instructions">
    <p>NOTE that if you have made modifications:</p>
    <ul>
    <li>You have to <b>SAVE before changes take effect.</b></li>
    <li>You have to <b>SAVE before adding a new status</b></li>
    <li>Also, this page offers to hard-delete an inactive status. To preserve database integrity, that deletion will succeed only if there are no
    references to that status. NOTE that if you have already made modifications, you have to <b>SAVE before deleting any status.</b></li>
    </ul>
    <button id="hide-instructions">Hide instructions</button>
    </div>
    <button id="show-instructions" style="display:none">Show instructions</button>
    <center>
    <input type="checkbox" id="show-displayorder" />&nbsp;<label for="show-displayorder">Show arrows to change display order</label>
    <div id="displayorder-instructions" style="display:none">Click on the up-down arrow (<b>&updownarrow;</b>) in the <b>Status Name</b> column for 
    the status you want to move, then click on its new position.<br />After clicking up-down arrow, you can still use ESC to cancel.<br />&nbsp;</div>
    <form name="main_form" action="index.php" method="post">
    <button class="save-button">Save</button>
    <button type="button" class="cancel-button">Cancel</button>
    <input type="hidden" name="act" value="update">
    <button class="add-status" data-parentid="0">Add top-level status</button>

    <table id="main-table" cellpadding="3" cellspacing="1">
        <thead>
            <tr>        
                <th>Status Name</th>
                <th>Successor</th>
                <th>Grace</th>
                <th>Initial Status</th>
                <th>&quot;Reactivate&quot; Status</th>
                <th>Means &quot;Done&quot;</th>
                <th>Can Notify</th>
                <th>Color</th>
                <th>Note</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
        <?php 
            // One row to view/update each existing workOrderStatus
            foreach ($workOrderStatusHierarchy as $workOrderStatus) {
                displayStatusRow($workOrderStatus);
            }
    
        ?>
        </tbody>
    </table>
    <button class="save-button">Save</button>
    <button type="button" class="cancel-button">Cancel</button>
    </form>
    </center>
    
    <!-- dialog initially not shown, used on demand -->
    <div id="change-parent-dialog" style="display:none">
        <h3>Select new parent</h3>
        <select id="change-parent-select">
        <option value="0" class="active">(TOP LEVEL)</option>
        <?php displayWorkOrderStatusesForNewParent($workOrderStatusHierarchy); ?>
        </select>
    </div>
    

    <script type='text/javascript'>//<![CDATA[
    
        // Implement palette
        $(".full").spectrum({
            
            showInput: true,
            className: "full-spectrum",
            showInitial: true,
            showPalette: true,
            showSelectionPalette: true,
            maxSelectionSize: 10,
            preferredFormat: "hex",
            localStorageKey: "spectrum.demo",
            move: function (color) {
                
            },
            show: function () {
            
            },
            beforeShow: function () {
            
            },
            hide: function () {
            
            },
            change: function() {
                
            },
            palette: [
                ["rgb(0, 0, 0)", "rgb(67, 67, 67)", "rgb(102, 102, 102)",
                "rgb(204, 204, 204)", "rgb(217, 217, 217)","rgb(255, 255, 255)"],
                ["rgb(152, 0, 0)", "rgb(255, 0, 0)", "rgb(255, 153, 0)", "rgb(255, 255, 0)", "rgb(0, 255, 0)",
                "rgb(0, 255, 255)", "rgb(74, 134, 232)", "rgb(0, 0, 255)", "rgb(153, 0, 255)", "rgb(255, 0, 255)"], 
                ["rgb(230, 184, 175)", "rgb(244, 204, 204)", "rgb(252, 229, 205)", "rgb(255, 242, 204)", "rgb(217, 234, 211)", 
                "rgb(208, 224, 227)", "rgb(201, 218, 248)", "rgb(207, 226, 243)", "rgb(217, 210, 233)", "rgb(234, 209, 220)", 
                "rgb(221, 126, 107)", "rgb(234, 153, 153)", "rgb(249, 203, 156)", "rgb(255, 229, 153)", "rgb(182, 215, 168)", 
                "rgb(162, 196, 201)", "rgb(164, 194, 244)", "rgb(159, 197, 232)", "rgb(180, 167, 214)", "rgb(213, 166, 189)", 
                "rgb(204, 65, 37)", "rgb(224, 102, 102)", "rgb(246, 178, 107)", "rgb(255, 217, 102)", "rgb(147, 196, 125)", 
                "rgb(118, 165, 175)", "rgb(109, 158, 235)", "rgb(111, 168, 220)", "rgb(142, 124, 195)", "rgb(194, 123, 160)",
                "rgb(166, 28, 0)", "rgb(204, 0, 0)", "rgb(230, 145, 56)", "rgb(241, 194, 50)", "rgb(106, 168, 79)",
                "rgb(69, 129, 142)", "rgb(60, 120, 216)", "rgb(61, 133, 198)", "rgb(103, 78, 167)", "rgb(166, 77, 121)",
                "rgb(91, 15, 0)", "rgb(102, 0, 0)", "rgb(120, 63, 4)", "rgb(127, 96, 0)", "rgb(39, 78, 19)", 
                "rgb(12, 52, 61)", "rgb(28, 69, 135)", "rgb(7, 55, 99)", "rgb(32, 18, 77)", "rgb(76, 17, 48)"]
            ]
        });
    //]]>
    </script>
</body>
</html>
