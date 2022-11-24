<?php 
/*  _admin/perm/index.php

    EXECUTIVE SUMMARY: PAGE to manage permissions.

    PRIMARY INPUT: $_REQUEST['permissionGroupId']: primary key to DB table PermissionGroup
    
    Other INPUT:
    * Optional $_REQUEST['warning'], allows a warning to be displayed like an error at top of page when 
      reloading after a failed action.
    * Optional $_REQUEST['act'], possible values are:
        * 'addperm' takes additional input: 
            * $_REQUEST['permissionName']
        * 'update' takes additional inputs:
            * $_REQUEST['permissionId_PP'], for each valid permissionId PP in DB table permission.     
*/

include '../../inc/config.php';
include '../../inc/access.php';

$permissionGroupId = isset($_REQUEST['permissionGroupId']) ? intval($_REQUEST['permissionGroupId']) : 0;

$db = DB::getInstance();
$action_error = '';
$action_warning = '';

/*
// BEGIN MARTIN COMMENT

Job	PERM_JOB						
Permission	PERM_PERMISSION						
Work Order	PERM_WORKORDER						
Contract	PERM_CONTRACT						
Invoice	PERM_INVOICE

// END MARTIN COMMENT
*/

/* act='addperm':
   INPUT $_REQUEST['permissionName']
   Assuming a permissionName is provided, we use our own code (rather than relying on 
    the DB system to auto-increment) to get a permissionId exactly one higher than 
    the highest one currently used (if none currently used, code makes it "one higher than zero", 
    that is, 1). 
   We insert a row into DB table permission with this new permissionId, 
   permissionIdName = 'PERM_CHANGE_ME' (a placeholder; prior to 2019-12-12 was 'PERM_CHANG_ME'), and permissionName as specified. 
   We then reload the page for the current permissionGroupId.
   NOTE that unlike almost anywhere other ID, we can have a permissionId==0.
*/

if ($act == "addperm") {
    // >>>00002 Still needs input validation here & elsewhere.
    $permissionName = isset($_REQUEST['permissionName']) ? $_REQUEST['permissionName'] : '';
    $permissionName = trim($permissionName);
    $permissionName = substr($permissionName, 0, 32); // >>>00002 truncates silently
    
    if (strlen($permissionName)) {        
        $query = " select max(permissionId) as maxid from " . DB__NEW_DATABASE . ".permission  ";

        $stayzero = false;
        $permissionId = 0;
        
        $result = $db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                /* BEGIN REPLACED 2020-03-20 JM: there should be exactly one row here, so this 'while' is not needed
                while ($row = $result->fetch_assoc()) {
                    if (is_numeric($row['maxid'])) {
                        $permissionId = $row['maxid'];
                    } else {
                        $stayzero = true;
                    }
                }
                // END REPLACED 2020-03-20 JM
                */
                // BEGIN REPLACEMENT 2020-03-20 JM
                $row = $result->fetch_assoc();
                if (is_numeric($row['maxid'])) {
                    $permissionId = $row['maxid'];
                } else {
                    $stayzero = true; // NULL. There are no rows in the table.
                }
                // END REPLACEMENT 2020-03-20 JM
            } else {
                //$stayzero = true; // REPLACED 2020-03-20 JM
                // BEGIN REPLACEMENT 2020-03-20 JM
                $action_error = 'Query for max permissionId said it was a success, but returned no row';
                $logger->errorDb('1584724950', $action_error, $db);
                // END REPLACEMENT 2020-03-20 JM
            }
        } else {
            /* BEGIN REPLACED 2020-03-20 JM
            // DB failure, should log, code here is not equal to the situation.
            $stayzero = true;
            // END REPLACED 2020-03-20 JM
            */
            // BEGIN REPLACEMENT 2020-03-20 JM
            $action_error = 'Error querying for max permissionId';
            $logger->errorDb('1584724908', $action_error, $db);
            // END REPLACEMENT 2020-03-20 JM
        }
        if (!$action_error) { // check added 2020-03-20 JM
            if (!$stayzero) {
                $permissionId++;
            }
            // BEGIN ADDED 2020-03-20 JM
            if ($permissionId > 63) {
                $action_error = 'Trying to exceed the maximum for permissionId';
                $logger->errorDb('1584725000', $action_error, $db);
            } 
            // END ADDED 2020-03-20 JM
        }
        if (!$action_error) { // check added 2020-03-20 JM
            $query = " insert into " . DB__NEW_DATABASE . ".permission (permissionId, permissionIdName, permissionName) values (";
            $query .= " " . intval($permissionId) . " ";		
            $query .= " ,'PERM_CHANGE_ME' ";
            $query .= " ,'" . $db->real_escape_string($permissionName) . "') ";		
            
            // $db->query($query); // REPLACED 2020-03-20 JM
            // BEGIN REPLACEMENT 2020-03-20 JM
            $result = $db->query($query);
            if ($result) {
                if ($permissionId > 58) {
                    // NOT ACTUALLY AN ERROR, but we want to make this warning visible to the admin.
                    $action_warning = 'Approaching the maximum for permissionId. PLEASE CONTACT DEV ' .
                       'so that we can deal with this before it becomes a crisis!';
                    $logger->warn2('1584725009', $action_error);
                }
            } else {
                $action_error = 'Error adding permissionId';
                $logger->errorDb('1584725030', $action_error, $db);
            }
            // END REPLACEMENT 2020-03-20 JM
        }
    }
    
    /* BEGIN REPLACED 2020-03-20 JM
        // Effectively, reload this page in a way that refreshing won't repeat the action.
        header("Location: index.php?permissionGroupId=" . intval($permissionGroupId));
    // END REPLACED 2020-03-20 JM
    */
    // BEGIN REPLACEMENT 2020-03-20 JM
    if ($action_error) {
?>
        <!DOCTYPE html>
        <html>
        <head>
        </head>
        <body>
        <div class="alert alert-danger" role="alert" id="action-errir" style="color:red"><?= $action_error ?></div>
        <div><a href="index.php?permissionGroupId=<?= intval($permissionGroupId) ?>">Reload this page.</a></div>
        </body>
        </html>
<?php
        die();
    } else {
        // Effectively, reload this page in a way that refreshing won't repeat the action.
        // Pass on any warning for display.
        header("Location: index.php?permissionGroupId=" . intval($permissionGroupId). 
                ($action_warning ? ('&warning=' . urlencode($action_warning)) : '')
                );
    }
    // END REPLACEMENT 2020-03-20 JM        
}

// BEGIN ADDED 2020-03-20 JM
// Basically an assertion
if ($action_error) {    
    $logger->error2('1584731123', 'Coding error: should never arrive here with nonempty $action_error');
}
// END ADDED 2020-03-20 JM

// >>>00001 NOTE JM 2020-03-20 (kill this note once it is dealt with): while I've done some cleanup past here, I haven't done anything like the 
//  level of detail I did above. The rest of this file still needs an equivalent pass to that (DB & input error checking, for example).

$permissions = array();
$query = " select * from " . DB__NEW_DATABASE . ".permission order by permissionId ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
} // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance

// At this point, $permissions is the canonical representation of the Permission table in permissionId order  

$groups = array();
$currentGroup = false;
$query = " select * from " . DB__NEW_DATABASE . ".permissionGroup order by permissionGroupId ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.->query($query)) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($row['permissionGroupId'] == $permissionGroupId) {
                $currentGroup = $row;
            }
            $groups[] = $row;
        }
    }
}

// At this point, $groups is the canonical representation of the PermissionGroup table in permissionGroupId order,
// $currentGroup is the canonical representation of the row matching the input permissionGroupId (or false if there was no match).

/* act='update'
    Break the current permissionGroup's permissionString down to so-called bits that are 
    really digits/characters (see below for more on that).    
    Then for each input of the form ['permissionId_PP'], where PP is a pemissionId,
    we look at the permissionId, which should be a number 0-64 (>>>00001: that's a bit unusual, 
    you'd expect 0-63 or 1-64, but seems not to get us in trouble in practice. 
    Possibly there is one potential outlying "bad" value, but in practice it never 
    gets passed in. Or maybe there's really 65 of them, which would actually be harmless). 
    We set the value for that "bit" based on what came in (properly speaking, it is not a 
    "bit" but a "digit", since the possible values are 1, 2, 3, 5, 7, 9). 
    We then update the row for that permissionGroupId in DB table permissionGroup accordingly, 
    with the resulting imploded string, and reload the page for the current permissionGroupId.
*/

if ($act == "update") {
    if ($currentGroup) {
        $string = $currentGroup['permissionString'];        
        $characters = array(); // JM 2020-03-03: $characters used to be $bits, but that was actually misleading. Values are digits; if we needed
                         // more levels we'd have to use some other characters, but these are in no sense bits.
        
        for ($i = 0; $i < 64; ++$i) {            
            $characters[$i] = substr($string, $i, 1);            
        }

        foreach ($_REQUEST as $key => $val) {
            // E.g. $key == "permissionId_3"
            $pos = strpos($key, 'permissionId_');
            if ($pos !== false) {    
                $parts = explode("_", $key);           
                if (count($parts) == 2) {
                    $position = $parts[1];                  
                    $position = intval($position);                    
                    // if (($position >= 0) && ($position <= 64)) { // REPLACED 2020-03-20 JM: this test was wrong, went one position past what the DB contains
                    if (($position >= 0) && ($position < 64)) { // REPLACEMENT  2020-03-20 JM
                        $val = intval($val);                            
                        if ($val) {
                            $characters[$position] = $val;                        
                        }                        
                    }                    
                }                
            }            
        }
        
        $string = implode("", $characters);
        
        $query = " update " . DB__NEW_DATABASE . ".permissionGroup set  ";
        $query .= " permissionString = '" . $db->real_escape_string($string) . "' ";
        $query .= " where permissionGroupId = " . intval($currentGroup['permissionGroupId']);
        
        $db->query($query);        
    }

    // Effectively, reload this page in a way that refreshing won't repeat the action.
    header("Location: index.php?permissionGroupId=" . intval($permissionGroupId));
}


?>
<!DOCTYPE html>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <style>
        #group tr:nth-child(odd) {
            background-color: #e4e4e4;
        }
        #group tr:nth-child(even) {
            background-color: #eeeeee;
        }
        #group tr.new {
            background-color: #ffc;
        }
    </style>
</head>
<body>
<?php
    // BEGIN ADDED JM 2020-03-20
    if ( !$action_error && array_key_exists('warning', $_REQUEST) && $_REQUEST['warning']) {
        // Grab warning to display it like an error.
        $action_error = $_REQUEST['warning'];
    }
    if ($action_error) {
        echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"action-errir\" style=\"color:red\">$action_error</div>";
    }
    // END ADDED JM 2020-03-20
    
    echo '<center>' . "\n";
        /* Form (using a table) for the current permissionGroup, present even if
            there is no current permissionGroup.
           Functionally, the form consists only of a single HTML SELECT. 
           The first OPTION has value 0 (meaning no permissionGroup selected) and text "-- choose group --". 
           Then for each permissionGroup we have an OPTION whose value is the permissionGroupId and 
            that displays the permissionGroupName. 
           When selection changes, this form immediately self-submits to this page to reload for the newly chosen permissionGroup.*/
        echo '<form name="changegroup" method="POST" action="index.php">' . "\n";        
            echo '<table border="0" cellpadding="5" cellspacing="2">' . "\n";
                echo '<tr>' . "\n";                
                    echo '<td>Permission Group</td>' . "\n";
                    echo '<td>' . "\n";
                        echo '<select name="permissionGroupId" onChange="this.form.submit();"><option value="0">-- choose group --</option>' . "\n";
                            foreach ($groups as $group) {
                                $selected = ($group['permissionGroupId'] == $permissionGroupId) ? ' selected ' : '';
                                echo '<option ' . $selected . ' value="' . $group['permissionGroupId'] . '">' . $group['permissionGroupName']  . '</option>' . "\n";				
                            }
                        echo '</select>' . "\n";
                    echo '</td>' . "\n";
                echo '</tr>' . "\n";
            echo '</table>' . "\n";
        echo '</form>' . "\n";
            
        if ($currentGroup) {
            /*  based on the current permissionGroup, we display another form. Note that the permission levels here
                 must correspond to PERMLEVEL values declared in inc/config.php. 
                 >>>00006: might be clearer to somehow use PERMLEVEL values explicitly here. 
                * (hidden) act='update'
                * (hidden) permissionGroupId 
                * Then a table, with one row for each permission in this group, and with the following columns:
                    * Perm Name (spans two columns. The two columns display, respectively, permissionName and permissionIdName.
                      Double-click on either to edit it.
                    * Admin: radio button for name="permissionId_PP" (where PP is the permissionId) and value 1. 
                      Here and for the following buttons, the button corresponding to the current permissionId value is checked.
                    * R/W/A/D: radio button for name="permissionId_PP" and value 2.
                    * R/W/A:   radio button for name="permissionId_PP" and value 3.
                    * R/W:     radio button for name="permissionId_PP" and value 5.
                    * R:       radio button for name="permissionId_PP" and value 7.
                    * None:    radio button for name="permissionId_PP" and value 9. 
                * Below this all is a submit button labeled 'update' 
            */
            $string = $currentGroup['permissionString'];
        
            echo '<form name="addperm" method="POST" action="index.php">' . "\n";
                echo '<input type="hidden" name="act" value="update" />' . "\n";
                echo '<input type="hidden" name="permissionGroupId" value="' . intval($permissionGroupId) . '" />' . "\n";
                echo '<table id="group" border="0" cellpadding="4" cellspacing="1" >' . "\n";
                    echo '<tr class="new">' . "\n";
                        echo '<th colspan="2">Perm Name</th>' . "\n";
                        echo '<th>Admin</th>' . "\n";
                        echo '<th>R/W/A/D</th>' . "\n";
                        echo '<th>R/W/A</th>' . "\n";
                        echo '<th>R/W</th>' . "\n";
                        echo '<th>R</th>' . "\n";
                        echo '<th>None</th>' . "\n";
                    echo '</tr>' . "\n";

                    foreach ($permissions as $pkey => $permission) {                        
                        $bit = $permission['permissionId'];                        
                        echo '<tr>' . "\n";
                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                            // - 1 edit
                            //$perm = substr($string, ($bit - 1), 1);
                            // END COMMENTED OUT BY MARTIN BEFORE 2019
                            $perm = substr($string, $bit, 1);
                        
                            // On these first two, user can doubleclick to edit.
                            // 2019-10-15 JM: introduce HTML data attributes, get away from edit_name and edit_idname having
                            //  same HTML ID, which is illegal HTML. See http://bt.dev2.ssseng.com/view.php?id=35.
                            // OLD CODE removed 2019-10-15 JM: 
                            //echo '<td class="edit_name" id="' . intval($permission['permissionId']) . '">' . $permission['permissionName'] . '</td>';			
                            //echo '<td class="edit_idname" id="' . intval($permission['permissionId']) . '">' . $permission['permissionIdName'] . '</td>';
                            // BEGIN REPLACEMENT
                            echo '<td class="edit_name" data-permission-id="' . intval($permission['permissionId']) . '">' . $permission['permissionName'] . '</td>' . "\n";			
                            echo '<td class="edit_idname" data-permission-id="' . intval($permission['permissionId']) . '">' . $permission['permissionIdName'] . '</td>' . "\n";
                            // END REPLACEMENT
                            
                            $checked = ($perm == 1) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" name="permissionId_' . intval($permission['permissionId']) . '" value="1" ' . $checked . ' /></td>' . "\n";
                            $checked = ($perm == 2) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" name="permissionId_' . intval($permission['permissionId']) . '" value="2" ' . $checked . ' /></td>' . "\n";
                            $checked = ($perm == 3) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" name="permissionId_' . intval($permission['permissionId']) . '" value="3" ' . $checked . ' /></td>' . "\n";
                            $checked = ($perm == 5) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" name="permissionId_' . intval($permission['permissionId']) . '" value="5" ' . $checked . ' /></td>' . "\n";
                            $checked = ($perm == 7) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" name="permissionId_' . intval($permission['permissionId']) . '" value=7" ' . $checked . ' /></td>' . "\n";
                            $checked = ($perm == 9) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" name="permissionId_' . intval($permission['permissionId']) . '" value="9" ' . $checked . ' /></td>' . "\n";
                        echo '</tr>' . "\n";                        
                    }
                    echo '<tr class="new">' . "\n";
                        echo '<td colspan="8" align="center"><input type="submit" value="update" border="0" />' . "\n";
                    echo '</tr>' . "\n";
                echo '</table>' . "\n";
            echo '</form>' . "\n";
        }
        
        echo '<hr><hr>' . "\n";        
        
        /* A further form to add a new permission; naturally, this only means something if there is code behind that permission.
            * Header: "New Permission"
            * (hidden) act='addperm'
            * (hidden) permissionGroupId=permissionGroupId
            * text input "permissionName"
            * Submit button labeled 'add' 
        */
        echo '<form name="addperm" method="POST" action="index.php">' . "\n";
            echo '<input type="hidden" name="act" value="addperm" />' . "\n";
            echo '<input type="hidden" name="permissionGroupId" value="' . intval($permissionGroupId) . '" />' . "\n";            
            echo '<table border="0" cellpadding="5" cellspacing="2">' . "\n";
                echo '<tr>' . "\n";
                    echo '<td>New Permission</td>' . "\n";
                    echo '<td><input type="text" name="permissionName" value="" /></td>' . "\n";
                    echo '<td><input type="submit" value="add" border="0" /></td>' . "\n";
                echo '</tr>' . "\n";
            echo '</table>' . "\n";
        echo '</form>' . "\n";
    echo '</center>' . "\n";
    ?>
    
    <script>
        $(function () {
            <?php /* Double-click action to edit permissionIdName for a permission: 
                     Prompt for a new name. 
                     Using a synchronous POST, we pass (1) permissionId 
                      and (2) new permissionIdName to _admin/ajax/permupdateidname.php 
                      to update the DB. We rely on _admin/ajax/permupdateidname.php to 
                      always uppercase the permissionIdName.
                     On success, we update it in the form. 
                     On failure, we alert appropriately. 
                  */ ?>                
            $("#group td.edit_idname").dblclick(function () {
                // OLD CODE replaced 2019-12-12 JM:
                // var OriginalContent = $(this).text();
                // var inputNewText = prompt("Enter new content for PermId internal (code) name:", OriginalContent);        
                // BEGIN REPLACEMENT 2019-12-12 JM
                var $this = $(this);
                var OriginalContent = $this.text();
                var inputNewText;
                {
                    let ui_name = $this.closest('tr').find('.edit_name').text().trim();
                    if (ui_name.length == 0) {
                        ui_name = '(unnamed)';
                    }
                    inputNewText = prompt("Enter new internal (code) name for '" + ui_name + "':", OriginalContent);
                }
                // END REPLACEMENT 2019-12-12 JM
                if (inputNewText!=null) {
                    $.ajax({
                        url: '../ajax/permupdateidname.php',
                        // 2019-10-15 JM: introduce HTML data attributes, as discussed above
                        // OLD CODE removed 2019-10-15 JM: 
                        // data:{ id: $(this).attr('id'), value: inputNewText },
                        // BEGIN REPLACEMENT
                        data:{ id: $(this).attr('data-permission-id'), value: inputNewText },
                        // END REPLACEMENT
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {        
                            if (data['status']) {        
                                if (data['status'] == 'success') {
                                    // OLD CODE replaced 2019-12-12 JM:
                                    // $(this).text(inputNewText.toUpperCase());
                                    // BEGIN REPLACEMENT 2019-12-12 JM
                                    $this.text(inputNewText.toUpperCase());
                                    // END REPLACEMENT 2019-12-12 JM
                                } else {
                                    alert('error not success');
                                }
                            } else {
                                alert('error no status');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('error');
                        }
                    });
                }        
            });        
        }); 
        
        <?php /* Double-click action to edit permissionName for a permission: 
                 Prompt for a new name. 
                 Using a synchronous POST, we pass (1) permissionId 
                 and (2) new permissionName to _admin/ajax/permupdatename.php 
                 to update the DB. 
                 On success, we update it in the form. 
                 On failure, we alert appropriately. 
              */ ?>                
        $(function () {
            $("#group td.edit_name").dblclick(function () {
                // OLD CODE replaced 2019-12-12 JM:    
                // var OriginalContent = $(this).text();        
                // var inputNewText = prompt("Enter new content for permId user-friendly name:", OriginalContent);
                // BEGIN REPLACEMENT 2019-12-12 JM
                var $this = $(this);
                var OriginalContent = $this.text();
                var inputNewText;
                {
                    let code_name = $this.closest('tr').find('.edit_idname').text().trim();
                    if (code_name.length == 0) {
                        code_name = '(unnamed)';
                    }
                    inputNewText = prompt("Enter new user-friendly name for " + code_name + ":", OriginalContent);
                }
                // END REPLACEMENT 2019-12-12 JM
        
                if (inputNewText!=null) {
                    $.ajax({
                        url: '../ajax/permupdatename.php',
                        // 2019-10-15 JM: introduce HTML data attributes, as discussed above
                        // OLD CODE removed 2019-10-15 JM: 
                        // data:{ id: $(this).attr('id'), value: inputNewText },
                        // BEGIN REPLACEMENT
                        data:{ id: $(this).attr('data-permission-id'), value: inputNewText },
                        // END REPLACEMENT
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['status']) {
                                if (data['status'] == 'success') {
                                    $(this).text(inputNewText);
                                } else {
                                    alert('error not success');
                                }        
                            } else {
                                alert('error no status');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('error');
                        }
                    });
                }
            });
        });
    </script>

</body>
</html>