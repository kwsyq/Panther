<?php
/*  _admin/descriptorpackage/packagesubselect.php

    EXECUTIVE SUMMARY: provide information about a particular descriptorPackage.

    INPUT $_REQUEST['descriptorPackageId']: primary key to DB table DescriptorPackage.  
    
    No optional inputs.
*/

include '../../inc/config.php';

$descriptorPackageId = isset($_REQUEST['descriptorPackageId']) ? intval($_REQUEST['descriptorPackageId']) : 0;

// To format the HTML itself
function indentHTML($n) {
    $ret = '';
    while ($n-- > 0) {
        $ret .= '    ';
    }
    return $ret;
}
// To indent what appears on the screen
function indentText($n) {
    $ret = '';
    while ($n-- > 0) {
        $ret .= '&nbsp;&nbsp;&nbsp;';
    }
    return $ret;
}

?>
<!DOCTYPE html>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    
    <style>
        a {
            text-decoration: none;
            color: inherit;
        }
        
        /* slightly different background color for internal nodes vs. leaf nodes */
        tr.internal {
            background: rgba(0, 0, 255, 0.03);
        }
        tr.leaf {
            background: rgba(0, 0, 255, 0.1);
        }
        
        /* Hide device to open an open row or close a closed row; this is part of implementing a sort of accordion */
        tr.open td span.open_it {
            display: none;
        }
        tr.closed td span.close_it {
            display: none;
        }
        
        /* Since these are click-actions, give them an appropriate cursor. */
        span.open_it, span.close_it {
            cursor: pointer;
        }
        
        /* Hide rows that are logically subordinate to a row that is closed */
        tr.hidden {
            display: none;
        }
    
    </style>
</head>
<body>
<script>
    // Add a descriptor (optionally with modifier and/or note) to the current descriptorPackage.
    function addDescriptor2(descriptor2Id) {        
        var mod = $('#mod_' + descriptor2Id).val();        
        var note = $('#note_' + descriptor2Id).val();        
        
        // packageitems.php in packageItems performs this work (and then redraws itself)
        parent.window.frames['packageItems'].location = 'packageitems.php?act=addsub&note=' + escape(note) + 
                                                        '&modifier=' + escape(mod) + 
                                                        '&descriptor2Id=' + escape(descriptor2Id) + 
                                                        '&descriptorPackageId=<?php echo intval($descriptorPackageId); ?>';
    }
</script>
<?php
    // INPUT $descriptor: Descriptor2 object
    // INPUT $level: level of effective descriptor hierarchy. 0 is things like "building", higher numbers are increasingly specific.
    // INPUT $id_prefix: the idea here is that we represent the effective descriptor hierarchy in the table by the fact 
    //  that the HTML ID of any "parent" TR element is a prefix of the HTML IDs of each of its descendants. E.g. 
    //  row_1
    //     row_1_4
    //        row_1_4_11
    //           row_1_4_11_169  
    function writeHTMLRecursive($descriptor, $level=0, $id_prefix='row_') {
        if ( $descriptor->getActive() ) { // active only: test added 2020-01-15 JM
            $descriptor2Id = $descriptor->getDescriptor2Id() ? $descriptor->getDescriptor2Id() : 0;
            
            // BEGIN added 2020-01-15 JM
            $hasActiveChildren = false;
            $children = $descriptor->getChildren();
            foreach ($children AS $child) {
                if ($child->getActive() ) {
                    $hasActiveChildren = true;
                    break;
                }
            }            
            // END added 2020-01-15 JM; also reworked what follows to use $hasActiveChildren rather than just $descriptor->getChildren() 
            echo indentHTML(2*$level + 1) . '<tr id="' . $id_prefix . $descriptor2Id . '" ' . 
                'class="' . ($hasActiveChildren ? 'internal' : 'leaf'). 
                ($hasActiveChildren ? ' closed' : '') . // initially, don't show children: accordion is closed
                ($level > 0 ? ' hidden' : '' ) . // initially, don't show children: accordion is closed
                '">' . "\n";
            // Icon & name; 'open_it' and 'close_it' are devices to open or close the accordion
            echo indentHTML(2*$level + 2) . '<td class="descriptor' . '" >'. 
                    indentText($level) .
                    ($hasActiveChildren ? 
                        '<span class="open_it">&#9658;</span><span class="close_it">&#9660;</span>' : 
                        ''
                    ).
                    '&nbsp;<img class="icon" src="' . '/cust/' . CUSTOMER . '/img/icons_desc/d2_' . 
                    $descriptor2Id . '.gif" width="35" height="35" />&nbsp;' . $descriptor->getName(). '</td>' . "\n";
                    
            // "Mod": descriptorSub modifier
            echo indentHTML(2*$level + 2) . '<td><input type="text" size="3" maxlength="32" id="mod_' . $descriptor2Id . '"></td>' . "\n";
            
            // "Note"
            echo indentHTML(2*$level + 2) . '<td><input type="text" size="3" maxlength="32" id="note_' . $descriptor2Id . '"></td>' . "\n";
    
            // A link labeled '+', linked to self-submit to add this descriptorSub to the descriptorPackage.
            // descriptorPackageId passed as an argument here is irrelevant to addDescriptor2, but is needed afterward to get the correct display.  
            echo indentHTML(2*$level + 2) . '<td><input type="button" value="+ Add" onclick="javascript:addDescriptor2(' . $descriptor2Id . ')" /></td>' . "\n";
            
            echo indentHTML(2*$level + 1) . '</tr>' . "\n";
            
            if ($descriptor->getChildren()) {
                foreach ($descriptor->getChildren() as $child) {
                    writeHTMLRecursive($child, $level+1, $id_prefix . $descriptor2Id . '_');
                }
            }
        }
    } // END function writeHTMLRecursive
    
    if ($descriptorPackageId) {
        $descriptors = Descriptor2::getDescriptors();
        
        if ($descriptors === false) {
            echo '<p>Getting descriptors failed in ' . __FILE__ . ', please contact administrator or developer.</p>' . "\n";
        } else {
            
            echo '<table border="1" cellpadding="2" cellspacing="0">' . "\n";
            echo '    <tr>' . "\n";    
            echo '        <th></th>' . "\n"; // descriptor2 icon and name
            echo '        <th>Value</th>' . "\n"; // "modifier"
            echo '        <th>Note</th>' . "\n";
            echo '        <th></th>' . "\n"; // (add)
            echo '    </tr>' . "\n";

            foreach ($descriptors as $descriptor) { 
                writeHTMLRecursive($descriptor);
            }
            echo '</table>' . "\n";
        }
    } else {
        echo "<p>No descriptor package specified.</p>\n";
    }
?>
    <script>
    $('span.open_it').click(function() {
        let $this = $(this);
        let $tr = $this.closest('tr');
        $tr.addClass('open').removeClass('closed'); // mark this row open
        $("tr[id^='" + $tr.attr('id') + "_']").removeClass('hidden'); // un-hide all rows below...
        // ... but if those rows should be hidden bast on an intermediate row being closed, then re-close them.
        $("tr[id^='" + $tr.attr('id') + "_'].closed").each(function() {
             let $closed_row = $(this);
             $("tr[id^='" + $closed_row.attr('id') + "_']").addClass('hidden');
        });
    });
    $('span.close_it').click(function() {
        let $this = $(this);
        let $tr = $this.closest('tr');
        $tr.addClass('closed').removeClass('open'); // mark this row closed
        $("tr[id^='" + $tr.attr('id') + "_']").addClass('hidden');  // hide all rows below.
    });
    </script>
</body>
</html>