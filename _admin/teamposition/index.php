<?php 
/*  _admin/teamposition/index.php

    EXECUTIVE SUMMARY: PAGE to list teamPositions. Allows adding & editing teamPositions.
    Any new team positions have to be coordinated with code changes, especially to TEAM_POS
    values in inc/config.php

    No primary input: displays all teamPositions.

    Optional INPUT $_REQUEST['act']; supported values are:
        * 'add', takes additional input:
            * $_REQUEST['name']. 
        * 'update', takes additional inputs: 
            * $_REQUEST['teamPositionId'] 
            * $_REQUEST['name'].
*/

include '../../inc/config.php';
?>

<html>
<head>
</head>
<body bgcolor="#ffffff">
    <?php
    $db = DB::getInstance();
    
    if ($act == 'add') {
        // This DB table doesn't use autoincrement. Instead we find the maximum teamPositionId and use a value
        // one more than that; also, at least initially, we make this last in displayOrder by similar means.
        // >>>00028: not that it's really likely two people are doing this at once, but this select + insert should be in one tranaction.
        $query = "select max(displayOrder) as maxdisp, max(teamPositionId) as maxid from " . DB__NEW_DATABASE . ".teamPosition  ";
        
        $maxdisp = 0;
        $maxid = 0;
        
        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                // >>>00018  'while' here should really be 'if', can only be one such row
                while ($row = $result->fetch_assoc()) {
                    $maxdisp = $row['maxdisp'];
                    $maxid = $row['maxid'];
                }
            }
        }  // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance.
    
        $maxdisp++;
        $maxid++;
        
        $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';
    
        $name = trim($name);
        $name = substr($name, 0, 48); // >>>00002 truncates silently
    
        if (intval($maxid) && intval($maxdisp)) {            
            if (strlen($name)) {            
                $query = "insert into " . DB__NEW_DATABASE . ".teamPosition (teamPositionId, name, displayOrder) values (";
                $query .= "  " . intval($maxid) . " ";
                $query .= " ,'" . $db->real_escape_string($name) . "' ";
                $query .= " , " . intval($maxdisp) . " ";
                $query .= ") ";
                
                $db->query($query);
            }
        }
    }
    
    if ($act == 'update') {
        // Currently, name is the only thing we can change.
        
        //$active = isset($_REQUEST['active']) ? intval($_REQUEST['active']) : 0; // Commented out by Martin before 2019
        $teamPositionId = isset($_REQUEST['teamPositionId']) ? intval($_REQUEST['teamPositionId']) : 0;
        $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';
        
        $name = trim($name);
        $name = substr($name, 0, 48); // >>>00002 truncates silently        
        
        if (intval($teamPositionId)) {            
            $query = "update " . DB__NEW_DATABASE . ".teamPosition set ";
            $query .= " name = '" . $db->real_escape_string($name) . "' ";
            //$query .= " ,active = " . intval($active) . " ";  // Commented out by Martin before 2019
            $query .= " where teamPositionId = " . intval($teamPositionId);
            
            $db->query($query);
        }
    }

    // Select all rows from DB table teamPosition, ordered by displayOrder.
    $positions = array();
    
    $query = "select * from " . DB__NEW_DATABASE . ".teamPosition order by displayOrder asc  ";
    
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $positions[] = $row;
            }
        }
    }
    
    // The display is structured by tables in a somewhat ad hoc manner.
    echo '<center><table>';    
        echo '<tr>';        
            echo '<th>Name</th>';
            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
            //	echo '<th>description</th>';
            //	echo '<th>Display Order</th>';
            // END COMMENTED OUT BY MARTIN BEFORE 2019
        echo '</tr>';
        
        // One row to view/update each existing teamPosition.
        foreach ($positions as $position) {
            // >>>00006 would be cleaner to have form inside row, rather than vice versa
            //$checked = (intval($type['active'])) ? ' checked' : ''; // COMMENTED OUT BY MARTIN BEFORE 2019            
            echo '<form name="type_' . intval($position['teamPositionId']) . '" action="index.php" method="post">';
                echo '<input type="hidden" name="teamPositionId" value="' . intval($position['teamPositionId']) . '">';
                echo '<input type="hidden" name="act" value="update">';                
                echo '<tr>';                    
                    echo '<td><input type="text" name="name" value="' . $position['name'] . '" size="30" maxlength="48"></td>';
                    echo '<td><input type="submit" value="update"></td>';                    
                echo '</tr>';            
            echo '</form>';            
        }

        // Two blank rows
        echo '<tr>';
            echo '<td colspan="3">&nbsp;</td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="3">&nbsp;</td>';
        echo '</tr>';
        
        // A row to add a new teamPosition
        // >>>00006 would be cleaner to have form inside row, rather than vice versa
        echo '<form name="type_00" action="index.php" method="post">';
            echo '<input type="hidden" name="act" value="add">';
            echo '<tr>';
                echo '<td><input type="text" name="name" value="" size="30" maxlength="48"></td>';
                echo '<td>&nbsp</td>';
                echo '<td><input type="submit" value="Add"></td>';
            echo '</tr>';
        echo '</form>';
    echo '</table></center>';   
    
    ?>
</body>
</html>