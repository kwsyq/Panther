<?php 
/*  fb/element.php

    EXECUTIVE SUMMARY: view and edit data about an element
    An element is typically a building or other structure.

    PRIMARY INPUT: $_REQUEST['elementId'].
    
    Optional $_REQUEST['act']. Possible values are:
        * 'deletesub' 
            * uses $_REQUEST['elementDescriptorId']
        * 'adddescriptorpackage' 
            * uses $_REQUEST['descriptorPackageId']
        * 'adddescriptor'
            * uses $_REQUEST['descriptor2Id'], $_REQUEST['modifier'], and $_REQUEST['note']
              * before 2020-01-02 used descriptorSubId instead of descriptor2Id.
        * 'editelement': 
            * (no further inputs) 
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$elementId = isset($_REQUEST['elementId']) ? intval($_REQUEST['elementId']) : 0;
$element = new Element($elementId);

if ($act == 'deletesub') {    
    $db = DB::getInstance();
    $elementDescriptorId = isset($_REQUEST['elementDescriptorId']) ? intval($_REQUEST['elementDescriptorId']) : 0;    
    $query = " delete from " . DB__NEW_DATABASE . ".elementDescriptor ";
    $query .= " where elementDescriptorId = " . intval($elementDescriptorId) . " ";    
    $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
    
    // Drop through to redisplay
}

if ($act == 'adddescriptorpackage') {
    // ------- Add to this element all of the descriptor2s that are associated with this package. --------
    $db = DB::getInstance();    
    $descriptorPackageId = isset($_REQUEST['descriptorPackageId']) ? intval($_REQUEST['descriptorPackageId']) : 0;
    /* BEGIN REPLACED 2020-01-15 JM
    $query = " select * from  " . DB__NEW_DATABASE . ".descriptorPackageSub where descriptorPackageId = " . intval($descriptorPackageId);
    // END REPLACED 2020-01-15 JM
    */
    // BEGIN REPLACEMENT 2020-01-15 JM
    // Include only active descriptors.
    $query = "SELECT descriptorPackageSub.* " . 
             "FROM " . DB__NEW_DATABASE . ".descriptorPackageSub " .
             "JOIN " . DB__NEW_DATABASE . ".descriptor2 ON descriptorPackageSub.descriptor2Id = descriptor2.descriptor2Id " .  
             "WHERE descriptorPackageId = " . intval($descriptorPackageId) . " " .
             "AND  descriptor2.deactivated IS NULL;";
    // END REPLACEMENT 2020-01-15 JM
    $subs = array();    
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $subs[] = $row;
            }
        }
    }
    
    /* REPLACED 2020-01-02 JM
    foreach ($subs as $skey => $sub) { // $skey never used, could be just foreach ($subs as $sub)
    */
    // BEGIN REPLACEMENT 2020-01-02 JM
    foreach ($subs as $sub) {
    // END REPLACEMENT 2020-01-02 JM
        $modifier = trim($sub['modifier']);
        $modifier = substr($modifier, 0, 32); // >>>00002 can truncate without notifying
        
        $note = trim($sub['note']);
        $note = substr($note, 0, 64); // >>>00002 can truncate without notifying (not as big an issue on a note)
        
        /* REPLACED 2020-01-02 JM
        $query = " insert into   " . DB__NEW_DATABASE . ".elementDescriptor (elementId, descriptorSubId, note, modifier) values (";
        */
        // BEGIN REPLACEMENT 2020-01-02 JM
        $query = " insert into   " . DB__NEW_DATABASE . ".elementDescriptor (elementId, descriptor2Id, note, modifier) values (";
        // END REPLACEMENT 2020-01-02 JM
        
        $query .= " " . intval($element->getElementId()) . " ";
        
        /* REPLACED 2020-01-02 JM
        $query .= " ," . intval($sub['descriptorSubId']) . " ";
        */
        // BEGIN REPLACEMENT 2020-01-02 JM
        $query .= " ," . intval($sub['descriptor2Id']) . " ";
        // END REPLACEMENT 2020-01-02 JM
        
        $query .= " ,'" . $db->real_escape_string($note) . "' ";
        $query .= " ,'" . $db->real_escape_string($modifier) . "') ";
        
        $db->query($query);
    }
    // Drop through to redisplay
}

if ($act == 'adddescriptor') {
    /* REPLACED 2020-01-02 JM
    $descriptorSubId = isset($_REQUEST['descriptorSubId']) ? intval($_REQUEST['descriptorSubId']) : 0;
    */
    // BEGIN REPLACEMENT 2020-01-02 JM
    $descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;
    // END REPLACEMENT 2020-01-02 JM
    $modifier = isset($_REQUEST['modifier']) ? $_REQUEST['modifier'] : '';
    $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : '';
    /* REPLACED 2020-01-02 JM
    $element->addDescriptor($descriptorSubId, $modifier, $note);
    */
    // BEGIN REPLACEMENT 2020-01-02 JM
    $element->addDescriptor($descriptor2Id, $modifier, $note);
    // END REPLACEMENT 2020-01-02 JM
    
    // Drop through to redisplay
}

if ($act == 'editelement') {
    if (intval($element->getElementId()) && trim($_REQUEST["elementName"]) != "" && trim($_REQUEST["elementName"]) != "General") { 
        $element->update($_REQUEST);        
        ?>
        <script type="text/javascript">        
            setTimeout(function() { parent.$.fancybox.close(); }, 250);                        
        </script>       
        <?php
        die();
    } 
}

include '../includes/header_fb.php';
?>

<?php
// Styles for "Add descriptor" section completely rewritten 2020-01-02 JM, parallel to what
//  I already did with _admin/descriptor/descriptor2.php
?>
<style>
    a {
        text-decoration: none;
        color: inherit;
    }
    ul {
        list-style: none;
        padding: 0;
    }
    ul .inner {
        padding-left: 1em;
        overflow: hidden;
        display: none;
    }
    ul .inner.show {
        display: block;
    }
    li {
        margin: .2em 0;
    }
    li a.toggle {
        width: 90%;
        display: block;
        color: #fefefe;
        padding: .25em;
        border-radius: 0.15em;
        transition: background .3s ease;
        position: relative;
        left: 70px;
        top: -30px;
    }
    li a.toggle {
        background: rgba(0, 0, 0, 0.78);
    }
    li a.leaf {
        background: rgba(0, 0, 255, 0.2);
    }
</style>

<?php
    /* Self-submitting form/table to update element name
        * Header: element name
        * At the table/form level are two hidden inputs, act='editelement' and the elementId. 
        * No column headers, and only one row, which contains:
           * The text "Name:"
           * Editable HTML INPUT element with elementName
           * Submit button "update". 
    */
    echo '<h1>' . $element->getElementName() . '</h1>';
    echo '<center>';
        echo '<table border="0" cellpadding="5" cellspacing="2" width="80%">';
            echo '<form name="element" id="element" action="element.php" method="post">';
                echo '<input type="hidden" name="act" value="editelement">';
                echo '<input type="hidden" name="elementId" value="' . $element->getElementId() . '">';
                echo '<tr>';
                    echo '<td>Name:</td>';
                    echo '<td><input type="text" name="elementName" required id="elementName" value="' . htmlspecialchars($element->getElementName()) . '" size="50" maxlength="255"></td>';
                    echo '<td style="text-align:center;" colspan="2"><input type="submit" id="updateElement" value="update"></td>';
                echo '</tr>';
            echo '</form>';
        echo '</table>';
    echo '</center>';
    
    echo '<hr>';
    
    /* Table of Attached Descriptors
       No explicit FORM, double-click Modifier to edit
       
       Completely rewritten 2020-01-02 JM, modeled on the approach I took in _admin/descriptorpackage/packageitems.php
    */
    // >>>00006 What follows can probably be made at least somewhat more object-oriented & pushed down into a class.
    // It's a little tricky, though, because at least as it stands, we are using a pretty non-standard data structure.
    // If we were to add this to the Element class or a new ElementDescriptor class, it would still have to return a pretty sui generis object
    //  containing the information that we fill in below to $top_level and $descriptor2Array. Both are arrays whose
    //  content doesn't conform precisely to any object we use elsewhere.
    
    // Get descriptor2s that are explicitly associated with this element
    $query = "SELECT ed.elementDescriptorId, ed.modifier, ed.note, \n" .
        "d2.descriptor2Id, d2.name, d2.parentId, d2.displayOrder, 1 as explicit \n";
    $query .= "FROM  " . DB__NEW_DATABASE . ".elementDescriptor ed \n";
    $query .= "JOIN  " . DB__NEW_DATABASE . ".descriptor2 d2 ON ed.descriptor2Id = d2.descriptor2Id \n";
    $query .= "WHERE ed.elementId=$elementId \n";
    $query .= "ORDER BY d2.parentId, d2.displayOrder;\n";
    
    //var_dump($query);
    $result = $db->query($query);
    
    if (!$result) {
        $error = "Hard DB error interpreting elementDescriptor";
        $logger->errorDb('1577993601', $error, $db);
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
                             // JM 2020-01: I believe we don't care if original explicit descriptor2s have
                             //  children of their own. Before 2020-01 that was impossible, so it may need thought. 
                             //  As of 2020-01, those children are not represented.    
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
    
    // The following is not super-efficient, but we don't expect elements to have enough descriptors for this to be a problem.
    while ($descriptor2Id = array_shift($descriptors_with_unresolved_parent)) {
        $descriptor = $descriptor2Array[$descriptor2Id];    
        if (array_key_exists($descriptor['parentId'], $descriptor2Array)) {
            $descriptor2Array[$descriptor['parentId']]['children'][$descriptor['displayOrder']] = $descriptor2Id;
        } else {
            // Need to find parent of $descriptor 
            $query = "SELECT name, descriptor2Id, parentId, displayOrder, 0 as explicit ";
            $query .= "FROM  " . DB__NEW_DATABASE . ".descriptor2 ";
            $query .= "WHERE descriptor2Id=" . $descriptor['parentId'] . ";";
            
            $result = $db->query($query);
            if (!$result) {
                $error = "Hard DB error navigating up hierarchy";
                $logger->errorDb('1577993907', $error, $db);
                echo "<p>$error</p></body></html>";
                die();
            }
            if ($result->num_rows == 0) {
                $error = "Navigating up hierarchy, no parent " . $descriptor['parentId'] . " for " . $descriptor['descriptor2Id'];
                $logger->errorDb('1577994007', $error, $db);
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
    echo 'Attached Descriptors<br>(Dbl-Click Modifier to edit)';
    echo '<center>';
        echo '<table border="1" cellpadding="2" cellspacing="0" id="edit_table">';
            echo '<tr>' . "\n";
                echo '<th>Descriptor</th>' . "\n";
                echo '<th>Value</th>' . "\n"; // "modifier"
                echo '<th>Note</th>' . "\n";
                echo '<th></th>'; // (no header: [del])
            echo '</tr>' . "\n";
            
            writeAttachedDescriptorsRecursive($top_level);
        echo '</table>' . "\n";
    echo '</center>';
        
    function writeAttachedDescriptorsRecursive($arr, $level=0) {
        global $descriptor2Array, $elementId;
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
                // modifier
                // >>>00012 rather than just 'editable' it would make more sense to give a class name showing
                //  what this is about, e.g. 'editableModifier'
                echo '<td class="editable" width="25" id="' . intval($descriptor['elementDescriptorId']) . '">' . $descriptor['modifier'] . '</td>' . "\n";
                // A link labeled '[del]', linked to self-submit to delete this descriptor2 from the descriptorPackage.  
                echo '<td>' . $descriptor['note'] . '</td>' . "\n";
                echo '<td><input type="button" value="Delete" id="deleteElementDescriptor' . $descriptor['elementDescriptorId'] . '" 
                onclick="delItem(' . $elementId . ', ' . 
                    $descriptor['elementDescriptorId'] . ')" /></td>' . "\n";                
            } else {
                echo '<td colspan="3">&nbsp;</td>' . "\n";
            }
            echo '</tr>' . "\n";
            if ($descriptor['children']) {
                writeAttachedDescriptorsRecursive($descriptor['children'], $level+1);
            }
        }
    } // END function writeAttachedDescriptorsRecursive
?>    
    <script>
    function delItem(elementId, elementDescriptorId) {
        window.location.href='element.php?act=deletesub&elementId=' + elementId +  
                             '&elementDescriptorId=' + elementDescriptorId;
    }
    </script>
<?php    
    
    echo '<br /><br />';

    /* The next section is "Descriptor Packages". Allows adding all of the descriptors
        in a particular descriptorPackage to this element.
        
       This is another self-submitting HTML form containing:    
        * hidden: act='adddescriptorpackage'
        * hidden elementId
        * HTML selector with "choose package" (value=0) as default, and with an option for each descriptor package, 
          showing the package name, with the appropriate ID as value.
        The submit button is labeled "add package". 
    */
    $db = DB::getInstance();
    $packages = array();
    $query = " select * from  " . DB__NEW_DATABASE . ".descriptorPackage order by packageName ";
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
        }
    }

    echo '<hr>';
    echo '<form name="element" id="elementForm" action="element.php" method="post">';
        echo '<table border="0" cellpadding="3" cellspacing="2" width="80%">';
            echo '<tr>';
                echo '<td colspan="2">Descriptor Packages</td>';
            echo '</tr>';
            echo '<input type="hidden" name="act" value="adddescriptorpackage">';
            echo '<input type="hidden" name="elementId" value="' . $element->getElementId() . '">';
            echo '<tr>';
                echo '<td><select id="descriptorPackageId" name="descriptorPackageId"><option value="0">-- choose package -- </option>';
                foreach ($packages as $package) { 
                    echo '<option value="' . intval($package['descriptorPackageId']) . '">' . $package['packageName'] . '</option>';		
                }
                echo '</select></td>';
                echo '<td><input type="submit" value="add package" id="addPackage" border="0"></td>';
            echo '</tr>';        
        echo '</table>';    
    echo '</form>';
    echo '<hr>';

    /* Finally comes a section "Add Descriptor". This uses a hierarchy similar to 
       the one in "Attached Descriptors", but this time it is an accordion making all elementDescriptors available.
       2020-01-14 JM: limited to "active" descriptors (not soft-deleted).
    */
    
?>
<?php    
    echo '<form name="descriptorform" id="descriptorForm" action="element.php" method="post">';
        echo '<table border="0" cellpadding="5" cellspacing="2" width="80%">';
            echo '<input type="hidden" name="act" value="adddescriptor">';
            echo '<input type="hidden" name="elementId" value="' . $element->getElementId() . '">';
            echo '<tr>';
                echo '<td colspan="4">Add Descriptor</td>';
            echo '</tr>';
   
            echo '<tr>';
                /* Approach to this part of "Add descriptor" section completely rewritten 2020-01-02 JM, parallel to what
                    I already did with _admin/descriptor/descriptor2.php
                */
                // for HTML formatting
                function indent($n) {
                    $ret = '';
                    while ($n-- > 0) {
                        $ret .= '    ';
                    }
                    return $ret;
                }
                function writeAllDescriptorsRecursive($descriptor, $level=0) {
                    if ($descriptor->getActive()) {
                        echo indent(2*$level + 1) . '<li>' . "\n";
                        echo indent(2*$level + 2) . '<input type="radio" value="' . $descriptor->getDescriptor2Id() . '" name="descriptor2Id">';     
                        echo indent(2*$level + 2) . '&nbsp;<img class="icon" src="' . '/cust/' . CUSTOMER . '/img/icons_desc/d2_' . 
                            $descriptor->getDescriptor2Id() . '.gif" width="20" height="20" />' . "\n";
                        echo indent(2*$level + 2) . '&nbsp; <a rel="nofollow noreferrer" '. 
                             'class="toggle load-pane' . ($descriptor->getChildren() ? '' : ' leaf') . '" ' .
                             'id="descriptor2_' . $descriptor->getDescriptor2Id() . '" href="javascript:void(0);">' . 
                             $descriptor->getName() . '</a>' . "\n";
                        if ($descriptor->getChildren()) {
                            $hasActiveChild = false;
                            foreach ($descriptor->getChildren() as $child) {
                                if ($child->getActive()) {
                                    $hasActiveChild = true;
                                    break;
                                }
                            }
                            if ($hasActiveChild) {
                                echo indent(2*$level + 2) . '<ul class="inner">' . "\n";
                                foreach ($descriptor->getChildren() as $child) {
                                    writeAllDescriptorsRecursive($child, $level+1);
                                }
                                echo indent(2*$level + 2) . '</ul>' . "\n";
                            }
                        }
                        echo indent(2*$level + 1) . '</li>' . "\n";
                    }
                } // END function writeAllDescriptorsRecursive   
                
                echo '<td valign="top" bgcolor="#eeeeee">';
                $descriptors = Descriptor2::getDescriptors();
                if ($descriptors === false) {
                    echo 'Getting descriptors failed in ' . __FILE__ . ', please contact administrator or developer.';
                } else {
                    echo '<ul class="accordion">' . "\n";
                    foreach ($descriptors as $descriptor) { 
                        writeAllDescriptorsRecursive($descriptor);
                    }
                    echo '</ul>' . "\n";
                }
                echo '</td>';                
            echo '</tr>';

            echo '<tr>';
                echo '<td colspan="2">Modifier:';
                    echo '<input type="text" name="modifier" id="modifier" value="" maxlength="32" size="10">';
                echo '</td>';
                echo '<td colspan="2">Note:';
                    echo '<input type="text" name="note" id="note" value="" maxlength="64" size="10">';
                echo '</td>';                
            echo '</tr>';

            echo '<tr>';
                echo '<td style="text-align:center;" colspan="4"><input type="submit" id="addDescriptor" value="add descriptor"></td>';
            echo '</tr>';
        echo '</table>';
    echo '</form>';
    echo '<hr>';
?>

<script>
$('.toggle').click(function(e) {
    e.preventDefault();    
    var $this = $(this);
    
    /* Handle the accordion toggling */
    $nextLevel = $this.closest('li').children('ul');
    if ($nextLevel.hasClass('show')) {
        $nextLevel.removeClass('show');
    } else {
        $nextLevel.addClass('show');
    }
});
</script>

<script>

$(function () {
    // This allows a double-click to edit a descriptor2 modifier.
    // Prompt for new content (>>>00006: we could either give more context or 
    //  do this in place); update.
    $("#edit_table td.editable").dblclick(function () {
        var OriginalContent = $(this).val();
        var inputNewText = prompt("Enter new content for:", OriginalContent);

        if (inputNewText != null) {
            $.ajax({
                url: '../ajax/elementdescriptormodupdate.php',
                data:{ id: $(this).attr('id'), value: inputNewText },
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
$("#updateElement").click(function() { 
    var elementNameInput = $("#elementName").val();
    if( elementNameInput.trim() == "") {
        alert("Please add an Element name");
    }

    if(elementNameInput == "General") {
        alert("Element General already exists.");
    }

});

</script>

<?php
    include '../includes/footer_fb.php';
?>