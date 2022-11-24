<?php
/*  _admin/serviceload/serviceload.php

    EXECUTIVE SUMMARY: Page to manage a serviceLoad (identified by primary input).

    PRIMARY INPUT $_REQUEST['serviceLoadId']: Primary key to DB table ServiceLoad.
     Initially this is displayed with no input here, baically a blank page, then
     _admin/serviceload/serviceload.php reloads the frame with meaningful input.
    
    Other INPUT: Optional $_REQUEST['act']: supported values are:
        * 'updateserviceloadvar', takes additional inputs 
            * $_REQUEST['serviceLoadVarId']: primary key in DB table serviceLoadVar, identifies row to update  
            * $_REQUEST['loadVarName']
            * $_REQUEST['loadVarType']
            * $_REQUEST['loadVarData']
            * $_REQUEST['wikiLink']
        * 'addserviceloadvar', takes additional input:
            * $_REQUEST['loadVarName']
*/

include '../../inc/config.php';
?>
<html>
<head>
</head>
<body bgcolor="#eeeeee">
    <?php /* >>>00007 nothing left in this SCRIPT section, should remove it. */ ?>  
    <h2>Service Load Var</h2>    
    <?php    
    if ($act == "updateserviceloadvar") {
        // >>>00016: note that there is no check that serviceLoadVarId has any relation to serviceLoadId  
        $serviceLoadVarId = isset($_REQUEST['serviceLoadVarId']) ? intval($_REQUEST['serviceLoadVarId']) : 0;
        $loadVarType = isset($_REQUEST['loadVarType']) ? intval($_REQUEST['loadVarType']) : 0; // >>>00012: variable set but never used
        
        // Build object for relevant row, update it (see comment above at top of this file for what else is in $_REQUEST)         
        if (intval($serviceLoadVarId)) {            
            $serviceLoadVar = new ServiceLoadVar($serviceLoadVarId);
            $serviceLoadVar->update($_REQUEST);
        }
        // Fall through to usual display
    }
    
    if ($act == "addserviceloadvar") {    
        $db = DB::getInstance();    
        $loadVarName = isset($_REQUEST['loadVarName']) ? $_REQUEST['loadVarName'] : '';
        $serviceLoadId = isset($_REQUEST['serviceLoadId']) ? intval($_REQUEST['serviceLoadId']) : 0;
        
        $loadVarName = trim($loadVarName);
        $loadVarName = substr($loadVarName, 0, 32); // >>>00002: truncates silently
        $loadVarName = trim($loadVarName);
    
        if (strlen($loadVarName) && intval($serviceLoadId)) {    
            $query =  "insert into " . DB__NEW_DATABASE . ".serviceLoadVar (serviceLoadId, loadVarName) " .
                      "values " .
                      "(" . intval($serviceLoadId) . ", '" . $db->real_escape_string($loadVarName) . "')";
    
            $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, not noted at each instance
    
            $id = $db->insert_id; // ID of row just inserted >>>00012: but we don't do anything with it.
        }    
    }
    
    $serviceLoadId = isset($_REQUEST['serviceLoadId']) ? intval($_REQUEST['serviceLoadId']) : 0;
    
    if ($serviceLoadId) {    
        $serviceLoad = new ServiceLoad($serviceLoadId);
        echo '<b>[' . $serviceLoad->getLoadName() . ']</b>' . '<p>'; // serviceLoad name as heading
        $serviceLoadVars = $serviceLoad->getServiceLoadVars();
        
        echo '<table border="0" cellpadding="4" cellspacing="0">';
        echo '<tr>';
            echo '<th>Var</th>';
            echo '<th>Type</th>';
            echo '<th>Multi Data</th>';
            echo '<th>Wiki Link</th>';
            echo '<th>&nbsp;</th>';
            echo '<th>&nbsp;</th>';
        echo '</tr>';
        
        foreach ($serviceLoadVars as $skey => $serviceLoadVar) {
            $color = ($skey % 2) ? '#cccccc' : '#dddddd'; // alternating two light shades of gray.
            
            // Each serviceLoadVar for this serviceLoad gets its own form
            // >>>00018: would be cleaner to put the FORM inside the TR rather than vice versa
            echo '<form name="update_' . $serviceLoadVar->getServiceLoadVarId() . '">';
                echo '<input type="hidden" name="serviceLoadVarId" value="' . $serviceLoadVar->getServiceLoadVarId() . '">';
                echo '<input type="hidden" name="serviceLoadId" value="' . intval($serviceLoadId) . '">';
                echo '<input type="hidden" name="act" value="updateserviceloadvar">';
                echo '<tr>';
                    // "Var"
                    echo '<td bgcolor="' . $color . '"><input type="text" name="loadVarName" value="' . $serviceLoadVar->getLoadVarName() . '"></td>';
                    
                    // "Type" (HTML SELECT)
                    echo '<td bgcolor="' . $color . '"><select name="loadVarType"><option value="0">-- select type --</option>';                    
                        foreach ($serv_load_var_type as $key => $type) {                    
                            $selected = ($serviceLoadVar->getLoadVarType() == $key) ? ' selected ' : '';                        
                            echo '<option value="' . $key . '" ' . $selected . '>' . $type . '</option>';                    
                        }
                    echo '</select></td>';
                    
                    // "Multi Data"
                    echo '<td bgcolor="' . $color . '">';
                        echo '<input type="text" name="loadVarData" value="' . htmlspecialchars($serviceLoadVar->getLoadVarData())  . '" size="20" maxlength="1024">';
                    echo '</td>';

                    // "Wiki Link"
                    echo '<td nowrap bgcolor="' . $color . '">';
                        echo 'page=<input type="text" name="wikiLink" value="' . htmlspecialchars($serviceLoadVar->getWikiLink())  . '" size="40" maxlength="256">';
                    echo '</td>';
            
                    // (no header): link to wiki page; displays "[see]"
                    echo '<td bgcolor="' . $color . '">[<a target="_blank" href="'. WIKI_URL . $serviceLoadVar->getWikiLink() . '">see</a>]</td>';
                    
                    // (no header): submit button, labeled "update"
                    echo '<td bgcolor="' . $color . '"><input type="submit" value="update"></td>';                    
                echo '</tr>';
            echo '</form>';
        }
        
        echo '</table>';
    
        echo '<hr>';

        // Another form, to add an additional serviceLoadVar for this serviceLoad.
        echo '<form name="addserviceloadvar" action="serviceloadvar.php" method="post">';
            echo '<input type="hidden" name="act" value="addserviceloadvar">';
            echo '<input type="hidden" name="serviceLoadId" value="' . intval($serviceLoadId) . '">';
            echo '<input type="text" name="loadVarName" value="">';
            echo '<input type="submit" value="add service load var">';
        echo '</form>';
    }
    
    ?>
</body>
</html>