<?php
/*  fb/personpermissions.php

    EXECUTIVE SUMMARY: view/modify permissions on this site for a particular person.
    PRIMARY INPUT: $_REQUEST['personId'].

    Optional $_REQUEST['act']. Only possible value: 'update', which uses:
        * $_REQUEST['permissionId_0'] 
        * $_REQUEST['permissionId_1'] 
        * etc. up to the number of distinct permissions we handle.
*/

include '../inc/config.php';      
include '../inc/access.php';
include '../inc/perms.php';

// BEGIN ADDED 2019-12-02 JM
// Check to ensure that the current user has admin-level permission for granting permissions.
$checkPerm = checkPerm($userPermissions, 'PERM_PERMISSION', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    $logger->error2('1575317894', 'Tried to access fb/personpermissions.php without admin-level PERM_PERMISSION; user '. $user->getUsername().'(userId='.$user->getUserId().')');
    include '../includes/header_fb.php';
    echo '<p>Error 1575317894. Please contact a system administrator.</p>';
    include '../includes/footer_fb.php';
    die();
}
// END ADDED 2019-12-02 JM

// Validates personId, builds a person object, dies if personId is invalid. 
// >>>00002 should log if invalid.
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
if (intval($personId)) {
    $person = new Person($personId);
}

if (!intval($person->getPersonId())) {
    die();
}

include '../includes/header_fb.php'; // Added 2019-12-02 JM

$db = DB::getInstance();

// Effectively, build an in-memory copy of DB table Permissions
$permissions = array();
$query = " select * from " . DB__NEW_DATABASE . ".permission order by permissionId ";
if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
    }
} // >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.

$string = $person->getPermissionString();

if ($act == "update") {
    // Update permissions, wait a second, close fancybox.
    
    // >>>00002 JM: this seems to me a consequential enough action that we should log who does this ($user),
    //  affected person & before-and-after values of permissionString.
    
    $bits = array(); // >>>00012: these aren't really bits, they are characters (as of 2019-05, always digits)
    for ($i = 0; $i < 64; ++$i) {                
        $bits[$i] = substr($string, $i, 1);                
    }

    foreach ($_REQUEST as $key => $val) {
        $pos = strpos($key, 'permissionId_');
        if ($pos !== false) {
            $parts = explode("_", $key); // E.g. 'permissionId_0' becomes array('permissionId', '0')
            if (count($parts) == 2) {
                $position = $parts[1];                        
                $position = intval($position);
                
                if (($position >= 0) && ($position <= 64)) {
                    $val = intval($val); // New value for this permission
                    if ($val) {
                        $bits[$position] = $val;
                    }
                }                        
            }
        }                
    }

    $string = implode("",  $bits);

    $query = " update " . DB__NEW_DATABASE . ".person set  ";
    $query .= " permissionString = '" . $db->real_escape_string($string) . "' ";
    $query .= " where personId = " . intval($person->getPersonId());

    echo '<br>';
    
    $db->query($query);
    ?>
    <script type="text/javascript">
        setTimeout(function(){ parent.$.fancybox.close(); }, 1000);
    </script>
                    
    <?php 
}
?>

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

<?php 
    echo '<center>';
        // Self-submitting form: pretty obvious, so not a lot of notes here.
        echo '<form name="addperm" id="addperm" method="POST" action="personpermissions.php">';
            echo '<input type="hidden" name="act" value="update">';
            echo '<input type="hidden" name="personId" value="' . intval($person->getPersonId()) . '">';
                echo '<table id="group" border="0" cellpadding="4" cellspacing="1" >';
                    echo '<tr class="new">';
                        echo '<th>Perm Name</th>';
                        echo '<th>Admin</th>';
                        echo '<th>R/W/A/D</th>';
                        echo '<th>R/W/A</th>';
                        echo '<th>R/W</th>';
                        echo '<th>R</th>';
                        echo '<th>None</th>';
                    echo '</tr>';
                    
                    foreach ($permissions as $pkey => $permission) {                            
                        $bit = $permission['permissionId'];                            
                        echo '<tr>';
                            $perm = substr($string, $bit, 1);
                                
                            // "Perm Name" 
                            echo '<td class="edit_name" id="' . intval($permission['permissionId']) . '">' . $permission['permissionName'] . '</td>';
                            
                            // "Admin"
                            // >>>00006 instead of hardcoded integer in $perm == 1, should really use $perm == PERMLEVEL_ADMIN.
                            //  Similar comments apply to all subsequent cells; defined values are in inc/config.php.
                            $checked = ($perm == 1) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" id="permissionId_' . intval($permission['permissionId']) . '100'. '"
                            name="permissionId_' . intval($permission['permissionId']) . '" value="1" ' . $checked . '></td>';
                            
                            // "R/W/A/D"
                            $checked = ($perm == 2) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" id="permissionId_' . intval($permission['permissionId']) . '200'. '"
                            name="permissionId_' . intval($permission['permissionId']) . '" value="2" ' . $checked . '></td>';
                            
                            // "R/W/A"
                            $checked = ($perm == 3) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" id="permissionId_' . intval($permission['permissionId']) . '300'. '"
                            name="permissionId_' . intval($permission['permissionId']) . '" value="3" ' . $checked . '></td>';
                                                        
                            // "R/W"
                            $checked = ($perm == 5) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" id="permissionId_' . intval($permission['permissionId']) . '400'. '"
                            name="permissionId_' . intval($permission['permissionId']) . '" value="5" ' . $checked . '></td>';
                            
                            // "R"
                            $checked = ($perm == 7) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" id="permissionId_' . intval($permission['permissionId']) . '500'. '"
                            name="permissionId_' . intval($permission['permissionId']) . '" value="7" ' . $checked . '></td>';                            
                            
                            // "None"
                            $checked = ($perm == 9) ? ' checked ' : '';
                            echo '<td align="center"><input type="radio" id="permissionId_' . intval($permission['permissionId']) . '600'. '"
                            name="permissionId_' . intval($permission['permissionId']) . '" value="9" ' . $checked . '></td>';
                        echo '</tr>';                            
                    }
                    echo '<tr class="new">';
                        echo '<td colspan="8" align="center"><input type="submit" id="updatePermissions" value="update" border="0">';
                    echo '</tr>';
            echo '</table>';
        echo '</form>';
    echo '</center>';

    include '../includes/footer_fb.php';
?>