<!DOCTYPE HTML>
<?php
/*  _admin/ancillarydata/index.php

    EXECUTIVE SUMMARY: Manage ancillary data, as documented at http://sssengwiki.com/Ancillary+data
    
    PRIMARY INPUTs: 
        * $_REQUEST['table']: the table for which we are administering ancillary data. Default to 'job'.
    
*/
include '../../inc/config.php';

$underlyingTable = (array_key_exists('table', $_REQUEST) && isset($_REQUEST['table'])) ? trim($_REQUEST['table']) : 'job';

include "../../includes/header_admin.php";
?>
<div style="margin-left:10px">
<h3>Administer ancillary data</h3>
<p>If you need ancillary data for a table not yet offered in the following select, please talk to a developer.</p>
<label for="select-table">Administer ancillary data for table:</label>&nbsp;<select id="select-table">
<?php 
// NOTE that case matters in table names, because class ancillary combines them to form ancillary table names
// As of 2019-11, the only supported table here is 'job'
$supported_tables = Array('job');
foreach ($supported_tables AS $supported_table) { 
    echo "<option val=\"$supported_table\"" . ($underlyingTable==$supported_table ? ' SELECTED' : '') . ">$supported_table</option>\n";
}
?>
</select>
<br/>
<?php
    $ancillaryData = AncillaryData::load($underlyingTable);
    if (!$ancillaryData) {
        echo "<p style=\"color:red\">Cannot load AncillaryData object for $underlyingTable</p>\n";
        echo "</body></html>\n";
        die();
    }
    $types = $ancillaryData->getDataTypes(AncillaryData::INACTIVE_ALLOWED, AncillaryData::SHOW_HISTORY);
    if ($types===false) {
        echo "<p style=\"color:red\">Cannot get ancillary data types for $underlyingTable</p>\n";
        echo "</body></html>\n";
        die();
    }
?>
<p>Supported ancillary data types for <?php echo $underlyingTable; ?>:<br />
<input id="active-types-only" type="checkbox" />&nbsp;<label for="active-types-only">Show only active types</label>
</p>
<table id="show-types" border="1" cellpadding="2" cellspacing="0">
<thead>
<th>ID</th>
<th>Internal name</th>
<th>Friendly name</th>
<th>Help</th>
<th>Single-valued</th>
<th>Searchable</th>
<th>Underlying type</th>
<th>Inserted</th>
<th>Inserted by</th>
<th>Deactivated</th>
<th>Deactivated by</th>
<th><!--edit--></th>
</thead>
<tbody>
<?php
    foreach($types AS $type) {
        echo '<tr class="' . ($type['deactivated']===null ? 'active' : 'inactive') . '">'."\n"; // class here lets us easily hide inactive types
            // ID
            echo "<td class=\"ancillaryDataTypeId\">{$type['ancillaryDataTypeId']}</td>\n";
            
            // Internal name
            echo "<td class=\"internalTypeName\">{$type['internalTypeName']}</td>\n";
        
            // Friendly name
            echo "<td class=\"friendlyTypeName\">{$type['friendlyTypeName']}</td>\n";
             
            // Help text: show 15 characters (and an ellipsis if longer); hover to see more.
            echo "<td class=\"helpText\" data-helptext=\"{$type['helpText']}\">". substr($type['helpText'], 0, 15) . 
               ((strlen($type['helpText']) > 15) ? '&hellip;' : '') . "</td>\n";
                
            // Single-valued
            $singleValued = !!$type['singleValued'];
            echo "<td class=\"singleValued\" data-singlevalued=\"" . ($singleValued ? 'true' : 'false') . "\">".
                ($singleValued ? '<b>YES</b>' : 'no'). "</td>\n";
             
            // Searchable   
            $searchable = !!$type['searchable'];
            echo "<td class=\"searchable\" data-searchable=\"" . ($searchable ? 'true' : 'false') . "\">".
                ($searchable ? '<b>YES</b>' : 'no'). "</td>\n";
             
            // Underlying type (indicating string vs. unsigned int, etc.)
            $underlyingDataType = $ancillaryData->getOneUnderlyingDataType($type['underlyingDataTypeId']);
            if (!$underlyingDataType) {
                echo '</td></tr></tbody></table>'."\n";
                echo '<p style="color:red">Cannot get underlying data type!</p></body></html>'."\n";
                die();
            }
            echo "<td class=\"underlyingDataType\" data-underlying-type-id=\"{$type['underlyingDataTypeId']}\">".
                 "{$underlyingDataType['friendlyName']}".
                 "</td>\n";
             
            // Inserted (timestamp)
            echo "<td class=\"inserted\">{$type['inserted']}</td>\n";
           
            // Inserted by
            $insertedByIsNull = $type['insertedPersonId'] === null;
            echo "<td class=\"insertedBy\" data-inserted-by=\"" .
                ($insertedByIsNull ? '' : $type['insertedPersonId']) . 
                "\">";            
            if (!$insertedByIsNull) {
                $insertedByPerson = new Person($type['insertedPersonId'], $user);
                // no checking here: assuming decent database integrity
                echo $insertedByPerson->getFormattedName(true);
            }
            echo "</td>\n";
            
            // Deactivated (timestamp, can be null)
            echo "<td class=\"deactivated\">" ;
                if ($type['deactivated'] !== null) {
                    echo $type['deactivated'];
                }
            echo "</td>\n";
             
            // Deactivated by
            $deactivatedByIsNull = $type['deactivatedPersonId'] === null;
            echo "<td class=\"deactivatedBy\" data-deactivated-by=\"" .
                ($deactivatedByIsNull ? '' : $type['deactivatedPersonId']) . 
                "\">";            
            if (!$deactivatedByIsNull) {
                $deactivatedByPerson = new Person($type['deactivatedPersonId'], $user);
                // no checking here: assuming decent database integrity
                echo $deactivatedByPerson->getFormattedName(true);
            }
            echo "</td>\n";
            
            // <!--edit-->
            echo "<td class=\"edit\"><button class=\"edit\">Edit</button></td>\n";            
        echo "</tr>\n";
    } // END foreach($types AS $type) {    
?>
<tr><td colspan="12"><button id="add-data-type-button">Add data type</button></td></tr>
</tbody>
</table>
<?php /* expanddialog" is an initially invisible DIV we can use for "hover" display. It opens like a popup */ ?>
<div id="expanddialog" style="display:none"></div>

<script>
// Make "active-types-only" checkbox effective
    $(function() { // on document ready
        function showChosenTypes() {
            if ($('#active-types-only').is(':checked')) {
                $('#show-types tbody tr.inactive').hide();
            } else {
                $('#show-types tbody tr.inactive').show();
            }
        }
        
        // call it right away and on any change to active-types-only
        showChosenTypes();
        $('#active-types-only').change(function() {
            showChosenTypes();
        });     
    });

// code associated with table "show-types"

    // Hover over help text to show it if it is >15 chars
    $('.helpText').mouseenter(function() {
        let $this = $(this);
        if ($this.data('helptext').length > 0) {
            $('#expanddialog').html($this.data('helptext'))
                .show()
                .dialog({
                    position: { my: "center bottom", at: "center top", of: $(this) },
                    autoResize:true ,
                    open: function(event, ui) {
                        $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                        $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
                    }
                })
                .dialog('open');
        }
    })
    $( ".helpText, #expanddialog" ).mouseleave(function() {
        $( "#expanddialog" ).hide().dialog("close");
    });
    
    // Edit an existing ancillary data type
    $('#show-types tbody tr td.edit button.edit').click(function() {
        let $this = $(this);
        let $row = $this.closest('tr');
        let ancillaryDataTypeId = $('td.ancillaryDataTypeId', $row).text();
        let internalTypeName = $('td.internalTypeName', $row).text();
        let friendlyTypeName = $('td.friendlyTypeName', $row).text();
        let helpText = $('td.helpText', $row).data('helptext');
        let singleValued = $('td.singleValued', $row).data('singlevalued'); // jQuery is smart about converting this to a Boolean
        let searchable = $('td.searchable', $row).data('searchable'); // jQuery is smart about converting this to a Boolean
        let underlyingDataTypeId = $('td.underlyingDataType', $row).data("underlying-type-id");
        let inserted = $('td.inserted', $row).text();
        let insertedPersonId = $('td.insertedPersonId', $row).data("inserted-by");
        let deactivated = $('td.deactivated', $row).text();
        let deactivatedPersonId = $('td.deactivatedPersonId', $row).data("deactivated-by");        
        
        $('<div id="edit-data-type">' +
            '<input type="hidden" id="edit-ancillaryDataTypeId" value="' + ancillaryDataTypeId +'" />\n' +
            '<table>\n' +
            '<tbody>\n' +
            '<tr><td align="right"><label for="edit-internalTypeName">Internal type name:</label></td>' + 
                '<td><input type="text" id="edit-internalTypeName" data-orig="' + internalTypeName + '" value="' + internalTypeName + 
                '" maxlength="100" required></td></tr>\n' + 
            '<tr><td align="right"><label for="edit-friendlyTypeName">Friendly type name:</label></td>' + 
                '<td><input type="text" id="edit-friendlyTypeName" data-orig="' + friendlyTypeName + '" value="' + friendlyTypeName + 
                '" maxlength="100" required><br /></td></tr>\n' + 
            '<tr><td align="right"><label for="edit-helpText">Help text:</label></td>' + 
                '<td><textarea id="edit-helpText" rows="3" cols="50" maxlength="1000" data-orig="' + helpText +'">' + helpText + '</textarea></td></tr>\n' +
            '<tr><td></td><td><input type="checkbox" id="edit-singleValued" ' + (singleValued ? ' checked data-orig="checked"' : 'data-orig="unchecked"') + '>' +
                '&nbsp;<label for="edit-singleValued">Single-valued</label></td></tr>\n' +
            '<tr><td></td><td><input type="checkbox" id="edit-searchable" ' + (searchable ? ' checked data-orig="checked"' : 'data-orig="unchecked"') + '>' +
                '&nbsp;<label for="edit-searchable">Searchable</label></td></tr>\n' +
            (deactivated ? 
                '<input type="hidden" id="edit-deactivated checked data-orig="checked""><span style="color:red">This data type is already deactivated.</span>' : 
                '<tr><td></td><td><input type="checkbox" id="edit-deactivated" data-orig="unchecked">&nbsp;<label for="edit-deactivated">Deactivate. <br />\n' +
                'Once deactivated and updated, this cannot be undone.</label></td></tr><br />'
            ) +
            '<tr><td colspan="2"><button id="edit-submit">Update</button></td></tr>\n' +
            '</tbody>\n' +
            '</table>\n' +
            '</div>\n'
        ).dialog({
            autoOpen: true,
            title: 'Edit ' + friendlyTypeName + ' (id='+ ancillaryDataTypeId +') for ' + '<?php echo $underlyingTable; ?>',
            modal: true,
            closeOnEscape: false,
            position: {
                my: 'center top', 
                at: 'center top', 
                of:window
            },
            resizeable: true,
            height: Math.max(Math.min(600, $(window).height() - 50), 50),
            width: Math.max(Math.min(850, $(window).width() - 100), 50),
            close: function() {
                // reload page
                location.reload();
            }
        });
        
        $(document).on('click', '#edit-submit', function() {
            var data = {
                underlyingTable: '<?php echo $underlyingTable; ?>', 
                ancillaryDataTypeId: $('#edit-ancillaryDataTypeId').val()
            };
            if ($('#edit-internalTypeName').val().trim() != $('#edit-internalTypeName').data('orig')) {
                data.internalTypeName = $('#edit-internalTypeName').val().trim();
            }
            if ($('#edit-friendlyTypeName').val().trim() != $('#edit-friendlyTypeName').data('orig')) {
                data.friendlyTypeName = $('#edit-friendlyTypeName').val().trim();
            }
            if ($('#edit-helpText').val().trim() != $('#edit-helpText').data('orig')) {
                data.helpText = $('#edit-helpText').val().trim();
            }
            if ($('#edit-singleValued').is(':checked') != ($('#edit-singleValued').data('orig') == 'checked')) {
                data.singleValued = $('#edit-singleValued').is(':checked') ? 'true' : 'false';
            }
            if ($('#edit-searchable').is(':checked') != ($('#edit-searchable').data('orig') == 'checked')) {
                data.searchable = $('#edit-searchable').is(':checked') ? 'true' : 'false';
            }
            if ($('#edit-deactivated').is(':checked') != ($('#edit-deactivated').data('orig') == 'checked')) {
                data.deactivated = $('#edit-deactivated').is(':checked') ? 'true' : 'false';
            }
            $.ajax({
                url: '../ajax/modifyancillarydatatype.php',
                data: data,
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            // close dialog, and thereby refresh page
                            $('#edit-data-type').dialog('close');
                        } else {
                            alert(data['error']);
                        }
                    } else {
                        alert('error no status');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error in AJAX protocol or in called function, see server log');
                }
            }); // END ajax
        }); // END $(document).on('click', '#edit-submit'
    }); // END Edit an existing ancillary data type
    
    // Add a new ancillary data type
    $('#add-data-type-button').click(function() {
        $('<div id="add-data-type">\n' +
            '<p><small>You can choose an explicit ID, but this will fail if the ID is already in use. ' +
                'Leave ID blank or zero to have the system generate an ID.</small></p>\n' + 
            '<table>\n' +
            '<tbody>\n' +
            '<tr><td align="right"><label for="add-ancillaryDataTypeId">ID:</label></td>' + 
                '<td><input type="number" id="add-ancillaryDataTypeId" min="1" max="100000"></td></tr>\n' + 
            '<tr><td align="right"><label for="add-underlyingDataType">Underlying data type:</label></td>' + 
                '<td><select id="add-underlyingDataType">' +
                <?php
                    $underlyingDataTypes = $ancillaryData->getUnderlyingDataTypes();
                    foreach ($underlyingDataTypes AS $underlyingDataType) {
                        echo "'<option value=\"{$underlyingDataType['underlyingDataTypeId']}\">{$underlyingDataType['friendlyName']}</option>'+\n";
                    }
                ?> +
                '</select></td></tr>\n' + 
            '<tr><td align="right"><label for="add-internalTypeName">Internal type name:</label></td>' + 
                '<td><input type="text" id="add-internalTypeName" maxlength="100" required></td></tr>\n' + 
            '<tr><td align="right"><label for="add-friendlyTypeName">Friendly type name:</label></td>' + 
                '<td><input type="text" id="add-friendlyTypeName" maxlength="100" required></td></tr>\n' +
            '<tr><td align="right" valign="top"><label for="add-helpText">Help text:</label></td>' + 
                '<td><textarea id="add-helpText" rows="3" cols="50" maxlength="1000"></textarea></td></tr>\n' +
            '<tr><td></td><td><input type="checkbox" id="add-singleValued">' +
                '&nbsp;<label for="add-singleValued">Single-valued</label></td></tr>\n' +
            '<tr><td></td><td><input type="checkbox" id="add-searchable">' +
                '&nbsp;<label for="edit-searchable">Searchable</label></td></tr>\n' +
            '<tr><td colspan="2"><button id="add-submit">Add</button></td></tr>\n' +
            '</tbody>\n' +
            '</table>\n' +
            '</div>\n'
        ).dialog({
            autoOpen: true,
            title: 'Add a new ancillary data type for <?php echo $underlyingTable; ?>',
            modal: true,
            closeOnEscape: false,
            position: {
                my: 'center top', 
                at: 'center top', 
                of:window
            },
            resizeable: true,
            height: Math.max(Math.min(600, $(window).height() - 50), 50),
            width: Math.max(Math.min(850, $(window).width() - 100), 50),
            close: function() {
                // reload page
                location.reload();
            }
        });
        
        $(document).on('click', '#add-submit', function() {
            let dataOK = true;    
            let data = {
                underlyingTable: '<?php echo $underlyingTable; ?>',
            };
            
            if ($('#add-ancillaryDataTypeId').val()) {
                data.ancillaryDataTypeId = $('#add-ancillaryDataTypeId').val();
            }
            
            data.underlyingDataTypeId = $('#add-underlyingDataType').val();
            
            if ($('#add-internalTypeName').val().trim()) {
                data.internalTypeName = $('#add-internalTypeName').val().trim();
            } else {
                alert('You must provide an internal type name, e.g. "TYPE_JOB_NUMBER"');
                dataOK = false;
            }
            
            if ($('#add-friendlyTypeName').val().trim()) {
                data.friendlyTypeName = $('#add-friendlyTypeName').val().trim();
            } else {
                alert('You must provide a friendly type name, e.g. "job number"');
                dataOK = false;
            }
            
            if ($('#add-helpText').val().trim()) {
                data.helpText = $('#add-helpText').val().trim();
            }
            
            data.singleValued = $('#add-singleValued').is(':checked') ? 'true' : 'false';
            data.searchable = $('#add-searchable').is(':checked') ? 'true' : 'false';
            
            if (dataOK) {
                $.ajax({
                    url: '../ajax/addancillarydatatype.php',
                    data: data,
                    async: false,
                    type: 'post',
                    context: this,
                    success: function(data, textStatus, jqXHR) {
                        if (data['status']) {
                            if (data['status'] == 'success') {
                                // close dialog, and thereby refresh page
                                $('#add-data-type').dialog('close');
                            } else {
                                alert(data['error']);
                            }
                        } else {
                            alert('error no status');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('error in AJAX protocol or in called function, see server log');
                    }
                }); // END ajax
            } // END if (dataOK)
        }); // END $(document).on('click', '#add-submit'        
    }); // END Add a new ancillary data type
    
</script>
</div>
<?php
include "../../includes/footer_admin.php";
?>