<?php 
/*  _admin/descriptorpackage/packageitems.php

    EXECUTIVE SUMMARY: PAGE showing a given descriptorPackage. 
    Allows deletion of a descriptor2 from the package. 
    Contains the code to add a descriptor2 to the package, but that has to be called from 
    elsewhere (from _admin/descriptorpackage/packagesubselect.php).
    
    Prior to 2019-12 the explicit members of the package were descriptorSubs, and this was displayed
       in terms of a 4-level hierarchy of elementType, descriptorCategory, descriptor, and descriptorSub.
    
    From 2019-12 the explicit members of the package are descriptor2s, and this is displayed in an open-ended
       hierarchy of descriptor2s.
    
    So completely rewritten 2019-12 JM that I didn't save the markup of changes. 
    
    PRIMARY INPUT: $_REQUEST['descriptorPackageId'].

    Other INPUTs: Optional $_REQUEST['act']. Possible values are:
        * 'addsub', takes additional arguments 
            * $_REQUEST['modifier']: 'modifier' column in DB table descriptorPackageSub. 
                E.g. a quantity, but character to make it more general, such as a type of rafter, etc. 
            * $_REQUEST['note']: 'note' column in DB table descriptorPackageSub.
            * (before 2019-12) $_REQUEST['descriptorSubId']: Primary key to DB table descriptorSub.
            * (after 2019-12) $_REQUEST['descriptor2Id']: Primary key to DB table descriptor2.
            * $_REQUEST['descriptorPackageId']: Primary key to DB table descriptorPackage.
        * 'delsub', takes additional argument
            * $_REQUEST['descriptorPackageSubId']: Primary key to DB table descriptorPackageSub.
    
*/
include '../../inc/config.php';

$error='';
$fatalError='';
$db = DB::getInstance();
$descriptorPackageId = isset($_REQUEST['descriptorPackageId']) ? intval($_REQUEST['descriptorPackageId']) : 0; // >>>00002 >>>00016 should be validated

if ($descriptorPackageId) {
    $descriptorPackage=new DescriptorPackage($descriptorPackageId);
    
    if ($descriptorPackage) {
        if ($act == 'delsub') {
            $descriptorPackageSubId = isset($_REQUEST['descriptorPackageSubId']) ? intval($_REQUEST['descriptorPackageSubId']) : 0;
            
            if ( ! $descriptorPackage->deleteDescriptorSubFromPackage($descriptorPackageSubId)) {
                $error = "Failed to delete descriptorPackageSub $descriptorPackageSubId.";
                // Drop through to usual display
            } else {
                // reload cleanly so a refresh doesn't do something weird
                header("Location: packageitems.php?descriptorPackageId=$descriptorPackageId");
                die();
            }
        }
        
        if ($act == 'addsub') {    
            $modifier = isset($_REQUEST['modifier']) ? $_REQUEST['modifier'] : '';
            $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : '';
        
            $descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;
            
            if ( ! $descriptorPackage->addDescriptorSubToPackage($descriptor2Id, $note, $modifier)) {
                $error = "Failed to add descriptor $descriptor2Id to package";
                // Drop through to usual display
            } else {
                // reload cleanly so a refresh doesn't do something weird
                header("Location: packageitems.php?descriptorPackageId=$descriptorPackageId");
                die();
            }
            
        }    
    } else {
        $fatalError = "Invalid descriptorPackage $descriptorPackage specified.";
    }
} else {
    $fatalError = "No descriptorPackage specified.";
}

?>
<!DOCTYPE html>
<html>
<head>
</head>
<body bgcolor="#eeeeee">
    <?php
    
    if ($fatalError) {
        echo "<p>$fatalError</p></body></html>";
        die();
    }
    if ($error) {
        echo "<p>$error</p>";
    }        
    
    // >>>00006 What follows can probably be made at least somewhat more object-oriented & pushed down into a class.
    // It's a little tricky, though, because at least as it stands, we are using a pretty non-standard data structure.
    // If we were to add this to the DescriptorPackage class, it would still have to return a pretty sui generis object
    //  containing the information that we fill in below to $top_level and $descriptor2Array. Both are arrays whose
    //  content doesn't conform precisely to any object we use elsewhere.
    
    // Get explicit members of the descriptorPackage
    $query = "SELECT dps.note, dps.modifier, d2.descriptor2Id, d2.name, \n" .
        "d2.parentId, d2.displayOrder, dps.descriptorPackageSubId, 1 as explicit \n";
    $query .= "FROM  " . DB__NEW_DATABASE . ".descriptorPackage dp \n";
    $query .= "JOIN  " . DB__NEW_DATABASE . ".descriptorPackageSub dps ON dp.descriptorPackageId = dps.descriptorPackageId \n";
    $query .= "JOIN  " . DB__NEW_DATABASE . ".descriptor2 d2 ON dps.descriptor2Id = d2.descriptor2Id \n";
    $query .= "WHERE dp.descriptorPackageId=$descriptorPackageId \n";
    $query .= "AND  d2.deactivated IS NULL \n";  // added 2020-01-15 JM: active members only
    $query .= "ORDER BY d2.parentId, d2.displayOrder;\n";
    
    $result = $db->query($query);
    if (!$result) {
        $error = "Hard DB error interpreting descriptorPackage";
        $logger->errorDb('1576876758', $error, $db);
        echo "<p>$error</p></body></html>";
        die();
    }
    
    $top_level = array();    // An array of descriptor2Ids of the top-level descriptor2s (e.g. "Building", "Vault"), indexed by displayOrder 
    $descriptors_with_unresolved_parent = array(); // scratch array of descriptor2Ids of descriptors we have observed, but still
                             //  need to get into the hierarchy 
    $descriptor2Array = array();  // An array of associative arrays describing descriptor2s, indexed by descriptor2Id
                             // Each associative array represents the data for a descriptor2; besides DB columns, we have:
                             //  'explicit': 
                             //     quasi-Boolean
                             //        1 for descriptor2s explicitly in the package. 
                             //        0 for other ancestors up the hierarchy.
                             //     Explicit nodes will have some additional indexes (see query above) compared to others (see query below).
                             // 'children': 
                             //     Structure exactly like $top_level
                             // 
                             // JM 2019-12: I believe we don't care if original explicit descriptor2s have
                             //  children of their own. Before 2019-12 that was impossible, so it may need thought. 
                             //  As of 2019-12, those children are not represented.
                             
    while ($row = $result->fetch_assoc()) {
        $descriptor2Id = $row['descriptor2Id'];
        
        $row['children'] = array();
        $descriptor2Array[$descriptor2Id] = $row;
        if ($row['parentId'] == 0) {
            // Probably won't ever happen in the real world, but...
            // Got a top-level descriptor2 right away, has no children. 
            $top_level[$row['displayOrder']] = $descriptor2Id; // NOTE that here & below, we rely on there not being a duplicate parentId+displayorder
        } else {
            // First time through, so we cannot already have seen this.
            // NOTE that we are building a key that we can reconstruct from the data: we will 
            $descriptors_with_unresolved_parent[] = $descriptor2Id;
        }
        unset($descriptor2Id);
    }
    
    // The following is not super-efficient, but we don't expect packages big enough for this to be a problem.
    while ($descriptor2Id = array_shift($descriptors_with_unresolved_parent)) {
        $descriptor = $descriptor2Array[$descriptor2Id];    
        if (array_key_exists($descriptor['parentId'], $descriptor2Array)) {
            $descriptor2Array[$descriptor['parentId']]['children'][$descriptor['displayOrder']] = $descriptor2Id;
        } else {
            // Need to find parent of $descriptor 
            $query = "SELECT name, descriptor2Id, parentId, displayOrder, 0 as explicit";
            $query .= " FROM  " . DB__NEW_DATABASE . ".descriptor2 ";
            $query .= "WHERE descriptor2Id=" . $descriptor['parentId'] . ";";
            
            $result = $db->query($query);
            if (!$result) {
                $error = "Hard DB error navigating up hierarchy";
                $logger->errorDb('1577143907', $error, $db);
                echo "<p>$error</p></body></html>";
                die();
            }
            if ($result->num_rows == 0) {
                $error = "Navigating up hierarchy, no parent " . $descriptor['parentId'] . " for " . $descriptor['descriptor2Id'];
                $logger->errorDb('1577144007', $error, $db);
                echo "<p>$error</p></body></html>";
                die();
            }
            $row = $result->fetch_assoc();
            $row['children'] = array();
            $row['children'][$descriptor['displayOrder']] = $descriptor2Id; // now parent knows about child $descriptor
            
            $descriptor2Array[$row['descriptor2Id']] = $row; // put the parent of $descriptor in the $descriptor2Array array 
            
            if ($row['parentId'] == 0) {
                // Parent of $descriptor is a top-level descriptor2; we know we haven't seen it before, &
                //  it isn't in the hierarchy, because we haven't seen this row before; put it in $top_level.
                $top_level[$row['displayOrder']] = $row['descriptor2Id']; // NOTE that here & above, we rely on there not being a duplicate parentId+displayorder
            } else {
                // Parent of $descriptor is NOT top-level, so we will need to examine its parent as well.
                $descriptors_with_unresolved_parent[] = $row['descriptor2Id'];
            }
        }
    }
    
    echo '<h2>' . $descriptorPackage->getPackageName() . '</h2>' . "\n";    
    echo '<table border="1" cellpadding="2" cellspacing="0">' . "\n";
        echo '<tr>' . "\n";
            echo '<th>Descriptor</th>' . "\n";
            echo '<th>Value</th>' . "\n"; // "modifier"
            echo '<th>Note</th>' . "\n";
            echo '<th></th>'; // (no header: [del])
        echo '</tr>' . "\n";
        
        writeRecursive($top_level);
        
    echo '</table>' . "\n";

    function writeRecursive($arr, $level=0) {
        global $descriptor2Array, $descriptorPackageId;
        foreach ($arr as $descriptor2Id) { // necessarily in displayOrder, because that is how these arrays are organized
            $descriptor = $descriptor2Array[$descriptor2Id];
            echo '<tr>' . "\n";
            if ($descriptor['explicit']) {
                echo '<td style="background-color:#D0D0D0">';
            } else {
                echo '<td>';
            }
            for ($i=0; $i<$level; ++$i) {
                echo '&nbsp;&nbsp;&nbsp;';                    
            }
            echo '<img width="35" height="35" src="/cust/' . CUSTOMER . '/img/icons_desc/d2_' . $descriptor['descriptor2Id'] . '.gif">&nbsp;' . 
                $descriptor['name'] . '</td>' . "\n";
            if ($descriptor['explicit']) {
                echo '<td>' . $descriptor['modifier'] . '</td>' . "\n";
                echo '<td>' . $descriptor['note'] . '</td>' . "\n";
                // A link labeled '[del]', linked to self-submit to delete this descriptor2 from the descriptorPackage.  
                echo '<td><input type="button" value="Delete" onclick="delItem(' . $descriptorPackageId . ', ' . 
                    $descriptor['descriptorPackageSubId'] . ')" /></td>' . "\n";                
            } else {
                echo '<td colspan="3">&nbsp;</td>' . "\n";
            }
            echo '</tr>' . "\n";
            if ($descriptor['children']) {
                writeRecursive($descriptor['children'], $level+1);
            }
        }
    } // END function writeRecursive
    
    ?>
<script>
function delItem(descriptorPackageId, descriptorPackageSubId) {
    window.location.href='packageitems.php?act=delsub&descriptorPackageId=' + descriptorPackageId +  
                         '&descriptorPackageSubId=' + descriptorPackageSubId;
}
</script>
</body>
</html>