<?php
/*  _admin/wrkorderdesctype/index.php

    EXECUTIVE SUMMARY: view/edit workOrderDescriptionTypes

    No primary input, because this looks at all workOrderDescriptionTypes.

    Optional INPUT $_REQUEST['act']: possible values are:
        * 'add', expects additional input:
            * $_REQUEST['typeName']
        * 'update', expects additional inputs:
            * $_REQUEST['active']
            * $_REQUEST['workOrderDescriptionTypeId']
            * $_REQUEST['typeName']
            * $_REQUEST['color'] desired new color to associate with status, chosen from a palette.
                                 RGB described in 6 lowercase hex digits.
                                 DEFAULT 'ffffff' (white).
*/

include '../../inc/config.php';
?>

<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <link rel="stylesheet" type="text/css" href="../spectrum/spectrum.css">
    <script type="text/javascript" src="../spectrum/spectrum.js"></script>
    
    <style type="text/css">
        .full-spectrum .sp-palette {
            max-width: 200px;
        }
    </style>
</head>
<body bgcolor="#ffffff">
    <?php 
    $db = DB::getInstance();
    
    if ($act == 'add') {
        // This DB table doesn't use autoincrement. Instead we find the maximum workOrderDescriptionTypeId and use a value
        // one more than that; also, at least initially, we make this last in displayOrder by similar means.
        // >>>00028: not that it's really likely two people are doing this at once, but this select + insert should be in one tranaction.
        $query = "select max(displayOrder) as maxdisp, max(workOrderDescriptionTypeId) as maxid from " . DB__NEW_DATABASE . ".workOrderDescriptionType  ";
        
        $maxdisp = 0;
        $maxid = 0;
        
        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $maxdisp = $row['maxdisp'];
                    $maxid = $row['maxid'];
                }
            }
        }  // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance.
    
        $maxdisp++;
        $maxid++;
        
        $typeName = isset($_REQUEST['typeName']) ? $_REQUEST['typeName'] : '';
    
        $typeName = trim($typeName);
        $typeName = substr($typeName, 0, 32); // >>> truncates silently	
    
        if (intval($maxid) && intval($maxdisp)) {            
            if (strlen($typeName)) {            
                $query = "insert into " . DB__NEW_DATABASE . ".workOrderDescriptionType (workOrderDescriptionTypeId, typeName, displayOrder, active) values (";
                $query .= "  " . intval($maxid) . " ";
                $query .= ", '" . $db->real_escape_string($typeName) . "' ";
                $query .= ", " . intval($maxdisp) . " ";
                $query .= ", 1) ";
                
                $db->query($query);
            }
        }
    } // END if ($act == 'add')    
    
    if ($act == 'update') {
        $active = isset($_REQUEST['active']) ? intval($_REQUEST['active']) : 0;
        $workOrderDescriptionTypeId = isset($_REQUEST['workOrderDescriptionTypeId']) ? intval($_REQUEST['workOrderDescriptionTypeId']) : 0;
        $typeName = isset($_REQUEST['typeName']) ? $_REQUEST['typeName'] : '';
        $color = isset($_REQUEST['color']) ? $_REQUEST['color'] : 'ffffff';
        
        $typeName = trim($typeName);
        $typeName = substr($typeName, 0, 32); // >>> truncates silently
    
        $color = trim($color);
        $color = strtolower($color);
        
        $color = preg_replace("/[^0-9a-f]/","", $color);
        
        if (!(strlen($color) == 6)) {
            $color = 'ffffff';
        }
        
        if (intval($workOrderDescriptionTypeId)) {            
            $query = "update " . DB__NEW_DATABASE . ".workOrderDescriptionType set ";
            $query .= " typeName = '" . $db->real_escape_string($typeName) . "' ";
            $query .= " , active = " . intval($active) . " ";
            $query .= " , color = '" . $db->real_escape_string($color) . "' ";
            $query .= " where workOrderDescriptionTypeId = " . intval($workOrderDescriptionTypeId);
            
            $db->query($query);
        }
    } // END if ($act == 'update')
    
    // Select all rows from DB table workOrderDescriptionType, ordered alphabetically by timeName.
    $query = "select * from " . DB__NEW_DATABASE . ".workOrderDescriptionType order by typeName asc  ";
    
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $types[] = $row;
            }
        }
    }
    
    echo '<center><table cellpadding="3" cellspacing="1">';
        echo '<tr>';
            echo '<th>Type Name</th>';
            echo '<th>Active</th>';
            echo '<th>Color</th>';
            echo '<th>&nbsp;</th>';
        echo '</tr>';
        
        foreach ($types as $type) {
            $checked = (intval($type['active'])) ? ' checked' : '';
            
            // One row to view/update each existing workOrderDescriptionType.
            echo '<form name="type_' . intval($type['workOrderDescriptionTypeId']) . '" action="index.php" method="post">';
                // >>>00006 would be cleaner to have form inside row, rather than vice versa
                echo '<input type="hidden" name="workOrderDescriptionTypeId" value="' . intval($type['workOrderDescriptionTypeId']) . '">';
                echo '<input type="hidden" name="act" value="update">';
                echo '<tr>';
                    // "Type Name"
                    echo '<td><input type="text" name="typeName" value="' . $type['typeName'] . '" size="30" maxlength="32"></td>';
                    // "Active"
                    echo '<td><input type="checkbox" name="active" value="1" ' . $checked . '></td>';
                    // "Color"
                    $value = $type['color'];
                    echo '<td><input name="color" type="text" class="full" value="#' . $value . '"/></td>';
                    // (no header) submit button, labeled "update"
                    echo '<td><input type="submit" value="update"></td>';
                echo '</tr>';
            echo '</form>';
        }
        
        // Two blank rows
        echo '<tr>';
            echo '<td colspan="4">&nbsp;</td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="4">&nbsp;</td>';
        echo '</tr>';
        
        // A row to add a new workOrderDescriptionType.
        // >>>00006 would be cleaner to have form inside row, rather than vice versa
        //
        // Modified http://bt.dev2.ssseng.com/view.php?id=35, JM 2019-10-18
        // OLD CODE REMOVED JM 2019-10-18. Form name here was guaranteed to conflict with the last one above, 
        //  and used $type outside of the loop that set it. Value of $type here was completely irrelevant.
        //  Fix is per http://bt.dev2.ssseng.com/view.php?id=35
        //echo '<form name="type_' . intval($type['workOrderDescriptionTypeId']) . '" action="index.php" method="post">';
        // BEGIN REPLACEMENT CODE 2019-10-18
        echo '<form name="type_addworkorderdescriptiontype" action="index.php" method="post">';
        // END REPLACEMENT CODE 2019-10-18
            echo '<input type="hidden" name="act" value="add">';
            echo '<tr>';
                echo '<td><input type="text" name="typeName" value="" size="30" maxlength="32"></td>';
                echo '<td>&nbsp</td>';
                echo '<td>&nbsp</td>';
                echo '<td><input type="submit" value="Add"></td>';
            echo '</tr>';
        echo '</form>';
    echo '</table></center>';    
    ?>
    
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