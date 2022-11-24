<?php
/*  _admin/media/list.php

    EXECUTIVE SUMMARY: Display a hierarchy of media categories; allow adding new media categories;
    load a category into media.php frame.
    
    PRIMARY INPUTs:
        * $_REQUEST['mediaCategoryId']. >>>00026 As currently written (2019-05) does not affect display, 
            so despite being written in as a primary input might be better thought of as an argument for $_REQUEST['act']='act'.
        * $_REQUEST['frame']: Intended to be "yes" or "no". For a feature still to be implemented as of 2019-05; see explanation of DISPLAY below. 

    Optional INPUT $_REQUEST['act']: Only supported value is 'addCategory'. Takes additional input:
        * $_REQUEST['name'] - name of new category
        * NOTE THAT $_REQUEST['mediaCategoryId'] can be 0 (top-level) or a specific category to make the new category a child of that category.
    
*/

include '../../inc/config.php';
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php

$mediacategoryid = isset($_REQUEST['mediaCategoryId']) ? intval($_REQUEST['mediaCategoryId']) : 0;

//$detail = new Detail($detailid); // COMMENTED OUT BY MARTIN BEFORE 2019

if ($act == 'addcategory') {
    echo '<!-- '; // Added 2019-12-11 JM
    print_r($_REQUEST);  // (Presumably debug - JM)
    echo ' -->'; // Added 2019-12-11 JM
    $db = DB::getInstance();
    
    $name = trim($_REQUEST['name']);
    $name = substr($name, 0, 32); // >>>00002 truncates silently
    
    // >>>00016 should validate $mediacategoryid: either 0 or an existing mediaCategory
    
    // >>>00018 NOTE that we split this with an 'if-else', then do exactly the same thing either way:
    //  the "if" case is just a specialization of the "else" case
    if ($mediacategoryid == 0) {
        $query = " insert into " . DB__NEW_DATABASE . ".mediaCategory (parentId, name) values (";
        $query .= " " . intval(0) . " ";
        $query .= " ,'" . $db->real_escape_string($name) . "' ";        
        $query .= " ) ";
        $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance        
    } else {
        $query = " insert into " . DB__NEW_DATABASE . ".mediaCategory (parentId, name) values (";
        $query .= " " . intval($mediacategoryid) . " ";
        $query .= " ,'" . $db->real_escape_string($name) . "' ";        
        $query .= " ) ";
        $db->query($query);
    }
} // END if ($act == 'addcategory') 

// Recursive function: generate display of a category & its descendants
function displayLevel($parentId, $level) {
    // For each level the table consists of a single row; subcategories will be in an nested table.
    echo '<table border="0" cellpadding="3" cellspacing="0">' . "\n";
        echo '<tr>' . "\n";
            echo '<td nowrap>';
                // >>>00006 Bizarre way of indenting, and if I (JM) undertand how this works,
                //  there is really no reason to have a different number of these for different levels,
                //  nesting of tables should take care of that.
                for ($i = 0; $i < $level * 5; $i++) {
                    echo '&nbsp;';
                }
            echo '</td>' . "\n";	
            echo '<td>' . "\n";
                echo '<table border="0" cellpadding="3" cellspacing="0">' . "\n";    
                    $db = DB::getInstance();
                    // "order by mediaCategoryId" means, effectively, chronological by order of creation
                    $query = " select * from " . DB__NEW_DATABASE . ".mediaCategory where parentId = " . $parentId . " order by mediaCategoryId ";
                    $result = $db->query($query);
        
                    ++$level; // >>>00006 unnecessarily confusing: should just pass $level+1 when we make the recursive call.
                    if ($result) {        
                        if ($result->num_rows > 0) {
                            // One row in this nested table for each category at this level
                            while ($row = $result->fetch_assoc()) {
                                // >>>00032 Obviously groundwork for a planned feature, but note that
                                // we promptly reset these, so effectively does nothing now.
                                $frame = isset($_REQUEST['frame']) ? $_REQUEST['frame'] : '';                    
                                $target = ($frame == 'yes') ? " target='detailframe' " : ""; 
                                $xx = ($frame == 'yes') ? '&embedded=yes' : "";
                                // END groundwork for a planned feature
            
                                $target = ' target="mediaframe" ';
                                $xx = '';
                                
                                echo '<tr>';
                                    echo '<td>';
                                        $extra = '';//(!$parentId) ? '&nbsp;<font size="-1">(' . $row['title'] . ')</font>' : ''; // Commented out by Martin before 2019
                                        
                                        // Display category name, link to open in a different frame
                                        echo '<a ' . $target . ' href="media.php?mediaCategoryId=' . intval($row['mediaCategoryId']) . $xx .  '">' . $row['name'] . $extra . '</a>';
                                        
                                        // Show any further subcategories
                                        displayLevel($row['mediaCategoryId'], $level);
                                        //print_r($row); // Commented out by Martin before 2019
                                    echo '</td>';
                                echo '</tr>' . "\n";
                            }
                        }
                    }
                    // A final row to add a new category
                    echo '<tr>';
                        echo '<td>';            
                            echo '<form name="addcategory" method="post" action="">' . "\n";
                                echo '<input type="hidden" name="act" value="addcategory" />' . "\n";
                                echo '<input type="hidden" name="mediaCategoryId" value="' . intval($parentId) . '" />' . "\n";
                                    
                                echo '<input type="text" name="name" value="" size="20" maxlength="32" />' . "\n";
                                echo '<input type="submit" value="add" />' . "\n";
                            echo '</form>';
                        echo '</td>';
                    echo '</tr>' . "\n";
                echo '</table>' . "\n";
            echo '</td>' . "\n";
        echo '</tr>' . "\n";
    echo '</table>' . "\n";    
} // END function displayLevel

displayLevel(0, 0); // Start the top-level display, everything will be shown, recursively. 


/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$childs = $detail->getChilds();

foreach ($childs as $ckey => $child){    
    print_R($child);        
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

?>
</body>
</html>
