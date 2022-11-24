<?php
/*  crumbs.php

    EXECUTIVE SUMMARY: Passive page, though not exactly "static" in that it draws on a DB table.
    Just a page showing crumbs (& nothing else). 
    There is a RewriteRule in the .htaccess to allow this to be invoked as "crumbs/foo" rather than "crumbs.php".
*/

require_once './inc/config.php';
require_once './inc/perms.php';

$crumbs = new Crumbs(null, $user);
$newCrumbs = $crumbs->getNewCrumbs();
$indexed = array();

foreach ($newCrumbs as $key => $newCrumb) {	
    $indexed[] = array('name' => $key, 'data' => $newCrumb);
}

include_once BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title = 'Crumbs';\n</script>\n";
?>

<div id="container" class="clearfix">
    <div class="main-content">
        <h1>Crumbs</h1>
        <?php
            echo '<center>';
            echo '<table border="0" cellpadding="5" cellspacing="4">';
            
            while (count($indexed)) {
                $work = array_slice($indexed, 0, 2);
                $indexed = array_slice($indexed, 2);
                if (count($work)) {
                    echo '<tr>';
                        for ($i = 0; $i < 2; ++$i) {				
                            if (isset($work[$i])){
                                echo '<td><h2>' . $work[$i]['name'] . '</h2></td>';
                            } else {
                                echo '<td></td>';
                            }						
                            if (!$i) {							
                                echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';							
                            }
                        }				
                    echo '</tr>';
                    
                    echo '<tr>';				
                    for ($i = 0; $i < 2; ++$i) {
                        if (isset($work[$i])) {
                            echo '<td valign="top">';
                            if (count($work[$i]['data'])) {
                                echo '<table border="0" cellpadding="4" cellspacing="0">';							
                                foreach ($work[$i]['data'] as $record) {							
                                    if ($work[$i]['name'] == 'Search') {
                                        // link to that search page
                                        echo '<tr><td><a id="linkSearch'.$record['id'].'" href="/panther.php?act=search&q=' . rawurlencode($record['id']) . '">' . 
                                             $record['id'] . '</a>&nbsp;[' . date("m/d/Y",$record['time']) . ']</td></tr>';
                                    } else {
                                        $class = $work[$i]['name'];
                                        $object = new $class($record['id']);
                                        // JM 2019-12-04: dealing with issues related to http://bt.dev2.ssseng.com/view.php?id=53
                                        // (better supporting longer job names)
                                        // CODE REPLACED JM 2019-12-04
                                        /*
                                        // link to page to view/edit that object
                                        echo '<tr><td><a href="' . $object->buildLink() . '">' . $object->getName() . '</a>' . 
                                             '&nbsp;[' . date("m/d/Y",$record['time']) . ']</td></tr>';
                                        */ 
                                        // BEGIN REPLACEMENT CODE JM 2019-12-04
                                        // link to page to view/edit that object
                                        $name = $object->getName();
                                        if (strlen($name) > 30 ) {
                                            $name = substr($name, 0, 30) . '&hellip;';
                                        }
                                        echo '<tr><td><a id="linkObj'.$record['id'].preg_replace("/[^a-zA-Z0-9]/", "", $name).'" href="' . $object->buildLink() . '">' . $name . '</a>' . 
                                             '&nbsp;[' . date("m/d/Y",$record['time']) . ']</td></tr>';
                                        // END REPLACEMENT CODE JM 2019-12-04
                                    }
                                }
                                echo '</table>';
                            }
                            echo '</td>';
                        } else {
                        echo '<td></td>';
                        }
                        
                        if (!$i) {								
                        echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>';								
                        }
                        
                    }
                    echo '</tr>';				
                }
            }
            
            echo '</table>';
            echo '</center>';
        ?>
    </div>
</div>

<?php
include_once BASEDIR . '/includes/footer.php';
?>