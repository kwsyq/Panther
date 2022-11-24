<?php
/* inc/classes/AncillaryData.class.php

EXECUTIVE SUMMARY:
This started out as a request for multiple job numbers for a single job (so we can deal with different stakeholders using different numbers for it) but after some discussion, it's gotten broader: there seem to be a lot of cases where we'd like to be able to "hang arbitrary data on the side of the system".

Unlike most of these classes, AncillaryData handles two entire classes of tables, rather than a single table. You instantiate an AncillaryData object
to perform AncillaryData operations on the pair of tables (one for ancillary data types, one for ancillary data) that support a single database table 
such as DB table job or company.

In some cases � the job numbers are a good example � these will need to be searchable.

NOTE that ancillary data types:
  * Are not simple types like "integer", they are things like "job number", "email address", etc.
  * Are never remote IDs into a DB table.
  * As of 2019-10, are scalars (never arrays)

See wiki discussion at http://sssengwiki.com/Ancillary+data

In general, bad inputs here result in a report back to the caller, rather than logging from within this file/class. Exception: if
the DB appears to be messed up, we log that.

POLICY DECISION: we can duplicate the internal name or friendly name of an *inactive* ancillary data type

Public static functions:
* load($underlyingTable) - rather than a public constructor
* getUnderlyingDataTypes()
* getOneUnderlyingDataType($id)

Public static functions to generate UI:
    * Yes, it may be a little questionable to put presentation-layer considerations in a class that is mainly data-layer, but it means
      that so much functionality can be provided with a single call. NOTE that no other method here has presentation-layer considerations.
* generateAncillaryDataSection($underlyingTable, $rowId)
* generateAncillaryDataForSearchResults($underlyingTable, $rowId)
      
Functions to get data from dataType table:
* getDataTypes($activeOnly=true, $showHistory=null)

Functions to administer dataType table (in this case, any modification is an administrative matter):
* addDataType($val, $truncate=false)
* deactivateDataType($ancillaryDataTypeId=0, $internalTypeName='', $friendlyTypeName='')
* getDataTypeById($ancillaryDataTypeId, $activeOnly=true, $showHistory=null)
* getDataTypeByInternalTypeName($internalTypeName, $activeOnly=true, $showHistory=null)
* getDataTypeByFriendlyTypeName($internalTypeName, $activeOnly=true, $showHistory=null)
* validateInternalTypeName($ancillaryDataTypeId, $newInternalTypeName, $truncate=false)
* validateFriendlyTypeName($ancillaryDataTypeId, $newFriendlyTypeName, $truncate=false)
* validateHelpText($ancillaryDataTypeId, $newHelpText, $truncate=false)
* validateSearchable($ancillaryDataTypeId, $newSearchable, $truncate=false)
* validateSingleValued($ancillaryDataTypeId, $newSingleValued)
* changeInternalTypeName($ancillaryDataTypeId, $newInternalTypeName, $truncate=false)
* changeFriendlyTypeName($ancillaryDataTypeId, $newFriendlyTypeName, $truncate=false)
* changeHelpText($ancillaryDataTypeId, $newHelpText, $truncate=false)
* changeSearchable($ancillaryDataTypeId, $newSearchable, $truncate=false)
* changeSingleValued($ancillaryDataTypeId, $newSingleValued)

Functions to get data from the data table:
* getData($rowId=0, $ancillaryDataTypeId=0, $searchableOnly=false, $activeOnly=true, $showHistory=null)
* getAllSearchableData()
* getAllDataForRow($rowId)

Functions to modify data in the data table:
* putData($rowId, $ancillaryDataTypeId, $val, $truncate=false)
* deleteData($datumId)

We do not currently have functions to create new tables: in particular, it is still a developer task
to create ancillaryData and ancillaryDataType tables for a new underlying table.

>>>00028 Probably worth studying for transactionality issues.

*/
require_once __DIR__. '/../config.php';

class AncillaryData {
                                  
    // BEGIN Class constants:
    const ACTIVE_ONLY = true;
    const INACTIVE_ALLOWED = false;
    const SHOW_HISTORY = true;
    const DONT_SHOW_HISTORY = false;
    const MAY_TRUNCATE = true;
    const DONT_TRUNCATE = false;
    
    const UNDERLYING_DATA_TYPE_STRING = 1;
    const UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER = 2;
    const UNDERLYING_DATA_TYPE_SIGNED_INTEGER = 3;
    const UNDERLYING_DATA_TYPE_DATETIME = 4;
    
    // END Class constants:
    
    private $db;
    private $localLogger;
    private $underlyingTable;
    private $ancillaryDataTable;
    private $ancillaryDataTypeTable;
    private $namesCanDuplicateInactive;

    // ============ BEGIN GENERAL FUNCTIONS ============

    // INPUT $underlyingTable: name of underlying DB table, e.g. 'job', 'company'
    // Code outside this class should call this, and should not call the constructor.
    // RETURN correctly constructed AncillaryData object, or null on error 
    public static function load($underlyingTable) {
        try {
            return new AncillaryData($underlyingTable);
        } catch (Exception $ex) {
            return null;
        }
    }
    
    // INPUT $underlyingTable: name of underlying DB table, e.g. 'job', 'company'
    // INPUT $user - should be the current logged-in user
    // NOTE that the public function to create an object of this type is 'load'
    private function __construct($underlyingTable) {
        global $logger, $user;
        $this->localLogger = $logger;
        $this->user = $user;
        
        if ( !isset($user) || !$user || !intval($user->getUserId())) {
            // ... No one is logged in
            $this->localLogger->error2('1572034450', 'AncillaryData constructor requires logged-in user');
            throw new Exception('AncillaryData constructor requires logged-in user');
        }        
        
        $this->underlyingTable = $underlyingTable;
        $this->ancillaryDataTable = $underlyingTable . 'AncillaryData';
        $this->ancillaryDataTypeTable = $underlyingTable . 'AncillaryDataType';
		$this->db = DB::getInstance();
		$this->namesCanDuplicateInactive = true; // POLICY DECISION: we can duplicate the internal name or friendly name of an *inactive* ancillary data type

        $ok = false;
        
        $query = "SELECT COUNT({$this->underlyingTable}Id) from {$this->underlyingTable} LIMIT 1";
        $result = @$this->db->query($query);
        if ($result && $result->num_rows == 1) {
            // underlying table exists
            $query = "SELECT COUNT({$this->ancillaryDataTable}Id) from {$this->ancillaryDataTable} LIMIT 1";
            $result = @$this->db->query($query);
            if ($result && $result->num_rows == 1) {
                // data table exists
                $query = "SELECT COUNT({$this->ancillaryDataTypeTable}Id) from {$this->ancillaryDataTypeTable} LIMIT 1";
                $result = @$this->db->query($query);
                if ($result && $result->num_rows == 1) {
                    // data type table exists
                    $ok = true;
                } else {
                    $this->localLogger->errorDb('1571945134', "There is no such table as '{$this->ancillaryDataTypeTable}'", $this->db);
                }
            } else {
                $this->localLogger->errorDb('1571945145', "There is no such table as '{$this->ancillaryDataTable}'", $this->db);
            }
        } else {
            $this->localLogger->errorDb('1571945156', "There is no such table as '{$this->underlyingTable}'", $this->db);
        }
        
        if (!$ok) {
            throw new Exception('AncillaryData constructor lacks a required table');
        }
    } // END private function __construct
    
    // The underlying data types are DB data types, and are independent of the particular
    // ancillary data table. 
    // This returns an array of associative arrays, each describing an underlying data type.
    // Alternatively, this could be described in a database table and the whole table could be
    //  SELECTed here
    public static function getUnderlyingDataTypes() {
        return array(
            array('underlyingDataTypeId' => AncillaryData::UNDERLYING_DATA_TYPE_STRING, 'internalTypeName' => 'UNDERLYING_DATA_TYPE_STRING',
                  'friendlyName' => 'string', 'sqlDeclaration' => 'VARCHAR(2000)', 'column' => 'stringValue'), 
            array('underlyingDataTypeId' => AncillaryData::UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER, 'internalTypeName' => 'UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER',
                  'friendlyName' => 'unsigned integer', 'sqlDeclaration' => 'UNSIGNED INT', 'column' => 'unsignedIntegerValue'), 
            array('underlyingDataTypeId' => AncillaryData::UNDERLYING_DATA_TYPE_SIGNED_INTEGER, 'internalTypeName' => 'UNDERLYING_DATA_TYPE_SIGNED_INTEGER',
                  'friendlyName' => 'signed integer', 'sqlDeclaration' => 'SIGNED INT', 'column' => 'signedIntegerValue'),
            array('underlyingDataTypeId' => AncillaryData::UNDERLYING_DATA_TYPE_DATETIME, 'internalTypeName' => 'UNDERLYING_DATA_TYPE_DATETIME',
                  'friendlyName' => 'date and time', 'sqlDeclaration' => 'DATETIME', 'column' => 'dateTimeValue')
        );
    }
    
    // INPUT $id may be underlyingDataTypeId, internalTypeName, friendlyName, OR column, as returned by getUnderlyingDataTypes.
    // Effectively RETURNs the appropriate single row from the table returned by getUnderlyingDataTypes(). That is, the return is a
    //  single associative array with indexes 'underlyingDataTypeId', 'internalTypeName', etc.
    // RETURNs false on failure
    public static function getOneUnderlyingDataType($id) {
        $ret = false;
        $underlyingDataTypes = AncillaryData::getUnderlyingDataTypes();
        foreach($underlyingDataTypes AS $underlyingDataType) {
            if ($id==$underlyingDataType['underlyingDataTypeId'] || 
                $id==$underlyingDataType['internalTypeName'] ||
                $id==$underlyingDataType['friendlyName'] ||
                $id==$underlyingDataType['column']
            ) {
                $ret = $underlyingDataType;
                break;
            }
        }
        
        return $ret;
    }

    // ============ BEGIN UI FUNCTIONS ============
    // For an underlying DB table FOO it should be possible to add ancillary data support by:
    // 1) creating the relevant DB tables FOOAncillaryData and FOOAncillaryDataType, which will have the exact same form as 
    //    (say) JobAncillaryDataType and JobAncillaryData.
    // 2) adding 'FOO' to $supportedTables in _admin/ancillarydata/index.php.
    // 3) making a single call to public static function generateAncillaryDataSection where we want to see that data in the UI.
    //
    // A bit more work is involved if you want to support searchable values, and any code *specific* to a particular ancillary data type
    //  always has to be hand-written, but the ability to "hang extra data onto an object" comes this easily. See documentation of 
    //  public static function generateAncillaryDataForSearchResults
    //
    
    // INPUT $underlyingTable: the table for which we are fetching ancillary data. E.g. if this is "job" we 
    //  fetch types from jobAncillaryDataType and data from jobAncillaryData.
    // INPUT $rowId: primary identifier of a row in that table (e.g. in "Job")
    //
    // Writes directly to stdout.
    // 
    // A lot of HTML classes and IDs in this function contain the word "ancillary" to minimize the chance of conflicting with other HTML classes and IDs.  
    public static function generateAncillaryDataSection($underlyingTable, $rowId) {
        echo '<div class="ancillary-display full-box clearfix " style="border-radius: 4px;">'."\n";
            echo '<h2 class="heading">Ancillary Data</h2>'."\n";
            $ok = true;
            $ancillaryData=AncillaryData::load($underlyingTable);
            if (!$ancillaryData) {
                echo "<p style=\"color:red\">Cannot load AncillaryData object</p>\n";
                $ok = false;
            }
            if ($ok) {
                $types_raw = $ancillaryData->getDataTypes(AncillaryData::INACTIVE_ALLOWED, AncillaryData::SHOW_HISTORY);
                if ($types_raw===false) {
                    echo "<p style=\"color:red\">Cannot get ancillary data types</p>\n";
                    $ok = false;
                }
            } 
            if ($ok) {
                $underlyingDataTypes = $ancillaryData->getUnderlyingDataTypes();
                $types = Array();
                foreach ($types_raw AS $raw) {
                    $underlyingDataTypeFriendlyName = '';
                    foreach($underlyingDataTypes AS $underlyingDataType) {
                        if ($underlyingDataType['underlyingDataTypeId'] == $raw['underlyingDataTypeId']) { 
                            $underlyingDataTypeFriendlyName = $underlyingDataType['friendlyName'];
                            break;
                        }
                    }
                    $types[$raw['ancillaryDataTypeId']] = Array(
                        'ancillaryDataTypeId' => $raw['ancillaryDataTypeId'],
                        'underlyingDataTypeId' => $raw['underlyingDataTypeId'],
                        'underlyingDataTypeFriendlyName' => $underlyingDataTypeFriendlyName, 
                        'friendlyTypeName' => $raw['friendlyTypeName'],
                        'helpText' => $raw['helpText'],
                        'singleValued' => $raw['singleValued'],
                        'searchable' => $raw['searchable'],
                        'active' => $raw['deactivated']===null
                        );         
                }
            }
            if ($ok) {
                $data = $ancillaryData->getData($rowId);
                if ($data===false) {
                    echo "<p style=\"color:red\">Cannot get ancillary data</p>\n";
                    $ok = false;
                }
            }
            if ($ok) {
                // ancillary-expanddialog" is an initially invisible DIV we can use for "hover" display. It opens like a popup
                echo '<div id="ancillary-expanddialog" style="display:none"></div>'. "\n";            
                
                foreach ($types AS $ancillaryDataTypeId => $type) {
                    $active = $type['active'];
                    $hasData = false;
                    $body_for_type = '';
                    $body_for_type .= '<p class ="' . ($active ? 'active' : 'inactive') . '">'; 
                    $body_for_type .= ($active ? '<b>' : '<i>'); 
                    $body_for_type .= "&nbsp<span class=\"type-name\" data-typeid=\"$ancillaryDataTypeId\" " .
                          "data-helptext=\"{$type['helpText']}\" " .
                          "data-underlyingtype=\"{$type['underlyingDataTypeFriendlyName']}\">" . 
                          $type['friendlyTypeName'] .
                          "</span>: "; 
                    $body_for_type .= ($active ? '</b>' : '</i>');
                    foreach ($data AS $datum) {
                        if ($datum ['ancillaryDataTypeId'] == $ancillaryDataTypeId) {
                            if ($hasData) {
                                $body_for_type .= ' &nbsp;&nbsp;&nbsp;'; // Guarantee at least 3 spaces, start with a simple space to allow line split.
                                                                         // This way of doing it also gives an indent on any new line. 
                            }
                            $hasData = true;
                            $body_for_type .= '<span class="datum-area" data-dbid="' . $datum['ancillaryDataId'] . '">';
                                $body_for_type .= '<span class="datum">'.$datum['value']. '</span>'; // THE ACTUAL VALUE
                                $body_for_type .= '&nbsp;<button class="delete-ancillary-button btn btn-secondary btn-sm mr-auto ml-auto">Delete</button>';
                            $body_for_type .= '</span>';
                        }
                    }
                    if ($hasData) {
                        if (!$type['singleValued']) {
                            $body_for_type .= '<br />';
                        }
                    } else {
                        $body_for_type .= '<i>No data</i>&nbsp;';
                    }
                    if ($active) {
                        // These pretty much share the same code, just a matter of captioning
                        if ($type['singleValued']) {
                            $body_for_type .= '&nbsp<button class="replace-ancillary-button btn btn-secondary btn-sm mr-auto ml-auto">Replace</button>';
                        } else {                    
                            $body_for_type .= '&nbsp<button class="add-ancillary-button btn btn-secondary btn-sm mr-auto ml-auto">Add</button>';
                        }
                    }
                    $body_for_type .= "</p>\n";
                    if ($active || $hasData) {
                        echo $body_for_type;
                    }
                    unset($body_for_type);
                }
            } // END if $ok
            
        echo "</div>\n";
    
        if ($ok) {
        ?>
        <script>
            // Hover over type name to show help text
            $('.ancillary-display span.type-name').mouseenter(function() {
                let $this = $(this);
                if ($this.data('helptext').length > 0) {
                    $('#ancillary-expanddialog').html($this.data('helptext'))
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
            $(".ancillary-display span.type-name, #ancillary-expanddialog" ).mouseleave(function() {
                $( "#ancillary-expanddialog" ).hide().dialog("close");
            });
            
            // Add new data
            $('button.add-ancillary-button, button.replace-ancillary-button').click(function() {
                let $this = $(this);
                let replace = $this.hasClass('replace-ancillary-button');
                let $typeinfo = $this.closest('p').find('span.type-name');
                let ancillaryDataTypeId = $typeinfo.data("typeid");
                let underlyingDataTypeFriendlyName = $typeinfo.data("underlyingtype"); 
                let typeName = $typeinfo.text();
                
                $('<div id="add-ancillary-data">\n' +
                    '<label for="add-ancillary-data-Value">Data value:</label>&nbsp;<input class="form-control form-control-sm" id="add-ancillary-data-Value" ' +
                    (underlyingDataTypeFriendlyName == <?php echo AncillaryData::UNDERLYING_DATA_TYPE_STRING; ?> ?
                        'type="text"' : 
                    (underlyingDataTypeFriendlyName == <?php echo AncillaryData::UNDERLYING_DATA_TYPE_DATETIME; ?> ?
                        'type="time"' :
                    (underlyingDataTypeFriendlyName == <?php echo AncillaryData::UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER; ?> ?
                        'type="number" step="1"' :
                    (underlyingDataTypeFriendlyName == <?php echo AncillaryData::UNDERLYING_DATA_TYPE_SIGNED_INTEGER; ?> ?
                        'type="number" step="1" min="0"' :
                        'type="text"' // shouldn't happen
                    )))) + ' /><br /><br />' + 
                    '<button class="btn btn-secondary btn-sm mr-auto ml-auto" id="add-ancillary-submit">' + (replace ? 'Replace' : 'Add') +'</button>\n' +
                '</div>\n'
                ).dialog({
                    autoOpen: true,
                    title: (replace ? 'Replace' : 'Add new') + ' "' + typeName + '" data',
                    modal: true,
                    closeOnEscape: false,
                    position: {
                        my: 'center top', 
                        at: 'center top', 
                        of:window
                    },
                    resizeable: true,
                    height: Math.max(Math.min(250, $(window).height() - 50), 50),
                    width: Math.max(Math.min(550, $(window).width() - 100), 50),
                    close: function() {
                        // reload page
                        location.reload();
                    },
                    closeText: ''
                });
                $(document).on('click', '#add-ancillary-submit', function() {
                    let dataOK = true;    
                    let data = {
                        underlyingTable: '<?php echo $underlyingTable; ?>',
                        rowId: '<?php echo $rowId; ?>',
                        ancillaryDataTypeId: ancillaryDataTypeId
                    };
                    
                    if ($('#add-ancillary-data-Value').val().trim()) {
                        data.val = $('#add-ancillary-data-Value').val().trim();
                    } else {
                        alert('You must provide a value');
                        dataOK = false;
                    }
                    
                    if (dataOK) {
                        $.ajax({
                            url: '../ajax/addancillarydata.php',
                            data: data,
                            async: false,
                            type: 'post',
                            context: this,
                            success: function(data, textStatus, jqXHR) {
                                if (data['status']) {
                                    if (data['status'] == 'success') {
                                        // close dialog, and thereby refresh page
                                        $('#add-ancillary-data').dialog('close');
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
                }); // END $(document).on('click', '#add-ancillary-submit'
            }); // END $('button.add-ancillary-button, button.replace-ancillary-button').click
            
            // Delete data
            $('button.delete-ancillary-button').click(function() {
                let $this = $(this);
                let $typeinfo = $this.closest('p').find('span.type-name');
                let typeName = $typeinfo.text();
                let $datumArea = $this.closest('span.datum-area');
                let datumId = $datumArea.data('dbid'); 
                let val = $datumArea.find('span.datum').text();
                
                $('<div id="delete-ancillary-data">\n' +
                    '<label for="delete-ancillary-data-value">Data value:</label>&nbsp;<input class=" form-control-sm"  id="delete-ancillary-data-value" readonly value="'+ val + '"/>' +
                    '<br /><br />' + 
                    '<button class="btn btn-secondary btn-sm"  id="delete-ancillary-submit">Delete</button>\n' +
                '</div>\n'
                ).dialog({
                    autoOpen: true,
                    title: 'Delete one "' + typeName + '"',
                    modal: true,
                    closeOnEscape: false,
                    position: {
                        my: 'center top', 
                        at: 'center top', 
                        of:window
                    },
                    resizeable: true,
                    height: Math.max(Math.min(200, $(window).height() - 50), 50),
                    width: Math.max(Math.min(550, $(window).width() - 100), 50),
                    close: function() {
                        // reload page
                        location.reload();
                    },
                    closeText: ''
                });
                $(document).on('click', '#delete-ancillary-submit', function() {
                    let dataOK = true;    
                    let data = {
                        underlyingTable: '<?php echo $underlyingTable; ?>',
                        datumId: datumId
                    };
                    
                    if (dataOK) {
                        $.ajax({
                            url: '../ajax/deleteancillarydata.php',
                            data: data,
                            async: false,
                            type: 'post',
                            context: this,
                            success: function(data, textStatus, jqXHR) {
                                if (data['status']) {
                                    if (data['status'] == 'success') {
                                        // close dialog, and thereby refresh page
                                        $('#delete-ancillary-data').dialog('close');
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
                }); // END $(document).on('click', '#delete-ancillary-submit'
            }); // END $('button.delete-ancillary-button').click
            
        </script>
        <?php
        } // END if $ok    
    } // END public static function generateAncillaryDataSection
    
    
    // INPUT $underlyingTable: the table for which we are fetching ancillary data. E.g. if this is "job" we 
    //  fetch types from jobAncillaryDataType and data from jobAncillaryData.
    // INPUT $rowId: primary identifier of a row in that table (e.g. in "Job")
    //
    // Returns the HTML. Blank on failure.
    //
    // There is a bunch of common code with generateAncillaryDataSection, but I (JM) think it is better to keep these separate.
    // 
    // This function is part of what we need to support searching for ancillary data values. This one lets us display ancillary data values
    // in the search results. In particular, see how this is called for "job" in /ajax/tooltip_job.php, and the ancillary data that returns is
    // used in js/poshytip/index.js.
    //
    // The other piece of searching for ancillary data values is the search as such. See inc/classes/Search.class.php private function jobAncillary
    // for an example of how that is done.
    public static function generateAncillaryDataForSearchResults($underlyingTable, $rowId) {
        $ok = true;
        $ancillaryData=AncillaryData::load($underlyingTable);
        if (!$ancillaryData) {
            $ok = false;
        }
        if ($ok) {
            $types_raw = $ancillaryData->getDataTypes(AncillaryData::INACTIVE_ALLOWED, AncillaryData::SHOW_HISTORY);
            if ($types_raw===false) {
                $ok = false;
            }
        } 
        if ($ok) {
            $underlyingDataTypes = $ancillaryData->getUnderlyingDataTypes();
            $types = Array();
            foreach ($types_raw AS $raw) {
                $underlyingDataTypeFriendlyName = '';
                foreach($underlyingDataTypes AS $underlyingDataType) {
                    if ($underlyingDataType['underlyingDataTypeId'] == $raw['underlyingDataTypeId']) { 
                        $underlyingDataTypeFriendlyName = $underlyingDataType['friendlyName'];
                        break;
                    }
                }
                $types[$raw['ancillaryDataTypeId']] = Array(
                    'ancillaryDataTypeId' => $raw['ancillaryDataTypeId'],
                    'underlyingDataTypeId' => $raw['underlyingDataTypeId'],
                    'underlyingDataTypeFriendlyName' => $underlyingDataTypeFriendlyName, 
                    'friendlyTypeName' => $raw['friendlyTypeName'],
                    'helpText' => $raw['helpText'],
                    'singleValued' => $raw['singleValued'],
                    'searchable' => $raw['searchable'],
                    'active' => $raw['deactivated']===null
                    );         
            }

            $data = $ancillaryData->getData($rowId);
            if ($data===false) {
                $ok = false;
            }
        }
        if ($ok) {
            // ancillary-expanddialog" is an initially invisible DIV we can use for "hover" display. It opens like a popup
            foreach ($types AS $ancillaryDataTypeId => $type) {
                $hasData = false;
                $body_for_type = '<p>'.$type['friendlyTypeName']. ':'; 
                foreach ($data AS $datum) {
                    if ($datum ['ancillaryDataTypeId'] == $ancillaryDataTypeId) {
                        if ($hasData) {
                            $body_for_type .= ', '; 
                        }
                        $hasData = true;
                        $body_for_type .= $datum['value'];
                    }
                }
                $body_for_type .= "</p>\n";
                if ($hasData) {
                    return $body_for_type;
                }
            }
        } // END if $ok
        return '';
    } // END public static function generateAncillaryDataForSearchResults
    
    
    // ============ BEGIN FUNCTIONS TO GET DATA FROM DATATYPE TABLE ============

    // INPUT $activeOnly - Boolean, if true (which is the default) we get only rows with 'deactivated IS null'. 
    //   For clarity, use AncillaryData::ACTIVE_ONLY or AncillaryData::INACTIVE_ALLOWED. 
    // INPUT $showHistory - Boolean. Effective default is the inverse of $activeOnly. If true, returned rows will include 'inserted', 
    //    'insertedPersonId', 'deactivated', 'deactivatedPersonId'. If false, these will be excluded.
    //   For clarity, use AncillaryData::SHOW_HISTORY or AncillaryData::DONT_SHOW_HISTORY.
    // RETURN an array of associative arrays of available data types, one row per type OR false on error. 
    // For each data type, the associative array will contain the following index-value pairs; inputs determine which of these we want:
    //  * 'ancillaryDataTypeId' - Primary key in DB table $this->ancillaryDataTypeTable. 
    //    NOTE that the column in the DB is actually $this->underlyingTable.'AncillaryDataTypeId'.
    //  * 'underlyingDataTypeId' - ID indicating string vs. unsigned int, etc.
    //  * 'internalTypeName' - Name we use to refer to this in code. E.g. 'TYPE_JOB_NUMBER'. 
    //  * 'friendlyTypeName' - Name we  use to refer to this in the UI. E.g. 'Additional Job Number'
    //  * 'helpText' - possibly including limited HTML markup, can be displayed as tooltip, etc.
    //  * 'singleValued' - 0 or 1. 0 means $this->$ancillaryDataTable may contain multiple such data.
    //  * 'searchable' - 0 or 1. 1 means intended to be searchable, leading to the related row in the underlying DB table.
    //  * 'inserted' - DATETIME.  When inserted into table. (only if $showHistory==true)
    //  * 'insertedPersonId' - Who inserted it. Nullable, optional. When present, foreign key into DB table Person. 
    //    (only if $showHistory==true)
    //  * 'deactivated' - DATETIME. Nullable, null if row is active. We consider this deactivated rather than deleted so that 
    //     it's OK if existing data in DB table fooAncillaryData references this, just can't add more data of a deactivated type.
    //      (only if $showHistory==true)
    //  * 'deactivatedPersonId' - Who deactivated it. Nullable, used only if column 'deactivated ' is non-null, and optional even then. 
    //    When present, foreign key into DB table Person.  (only if $showHistory==true)
 
    public function getDataTypes($activeOnly=true, $showHistory=null) {
        $ret = array();
        if ($showHistory === null) {
            $showHistory = !$activeOnly;
        }
        $query = "SELECT {$this->ancillaryDataTypeTable}Id AS ancillaryDataTypeId, ";
        $query .= 'underlyingDataTypeId, internalTypeName, friendlyTypeName, helpText, singleValued, searchable';
        $query .= $showHistory ? ', inserted, insertedPersonId, deactivated, deactivatedPersonId ' : ' ';
        $query .= "FROM {$this->ancillaryDataTypeTable}";
        if ($activeOnly) {
            $query .= " WHERE deactivated IS NULL"; 
        }
        $query .= ";";
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) { // NOTE assignment in conditional
                $ret[] = $row;
            }
        } else {
            $this->localLogger->errorDb('1571773000', "DB error SELECTing in AncillaryData::getDataTypes for {$this->underlyingTable}", $this->db);
            return false;
        }
        return $ret;
    } // END public function getDataTypes

    // ============ BEGIN FUNCTIONS TO ADMINISTER DATATYPE TABLE ============

    // INPUT $val is an associative array containing the following index-value pairs  
    //  * 'ancillaryDataTypeId' - Optional, can be defaulted. If provided, must be unique within the table; function will error out if this
    //    conflicts with an existing row in the relevant table.
    //    NOTE that the column in the DB is actually $this->underlyingTable.'AncillaryDataTypeId'.
    //  * 'underlyingDataType' - ID indicating string vs. unsigned int, etc.
    //  * 'internalTypeName' - Name we use to refer to this in code. E.g. 'TYPE_JOB_NUMBER'. Must be unique within the table (and cannot conflict
    //    with a friendly type name either); function will error out if this conflicts with an existing row in the relevant table.
    //    Also, cannot be entirely numeric, so it can't be confused with ID. If we are going to use it programmtically (e.g. via inc/config.php)
    //    it is going to have to be unique across the system.
    //  * 'friendlyTypeName' - Name we use to refer to this in the UI. E.g. 'Additional Job Number'. Must be unique within the table (and cannot conflict
    //    with an internal type name either); function will error out if this conflicts with an existing row in the relevant table.
    //    Also, cannot be entirely numeric, so it can't be confused with ID.
    //  * 'helpText' - optional string, default empty
    //  * 'singleValued' - optional BOOL, default false. False means $this->$ancillaryDataTable may contain multiple such data.
    //    1, 0, 'T', 'F', 'true', 'false' are also acceptable inputs; ignores case.
    //  * 'searchable' - optional BOOL, default false. True => intended to be searchable, leading to the related row in the underlying DB table
    //    1, 0, 'T', 'F', 'true', 'false' are also acceptable inputs; ignores case.
    // INPUT $truncate - BOOL, default false. If true, then any over-length strings will be truncated instead of causing errors.
    //   For clarity, use AncillaryData::MAY_TRUNCATE or AncillaryData::DONT_TRUNCATE
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->addDataType($val);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    //
    //  This will rarely be called -- rare admin task -- so we want it to be bulletproof rather than efficient
    public function addDataType($val, $truncate=false) {
        $error = '';
        // Validating input
        if (array_key_exists('ancillaryDataTypeId', $val)) {
            $temp = intval($val['ancillaryDataTypeId']);
            if ( $temp <= 0) {
                // Not a loggable error, but we have a problem we'll want to return to caller
                $error = "{$val['ancillaryDataTypeId']} is not a valid ID";
            } else {            
                $val['ancillaryDataTypeId'] = $temp; // assign it the cleaned-up value
                
                $query = "SELECT {$this->ancillaryDataTypeTable}Id ";
                $query .= "FROM {$this->ancillaryDataTypeTable} ";
                $query .= "WHERE {$this->ancillaryDataTypeTable}Id = {$val['ancillaryDataTypeId']};";
                
                $result = $this->db->query($query);
                if ($result) {
                    if ($result->num_rows) {
                        // Not a loggable error, but we have a name conflict we'll want to return
                        $error = "{$this->ancillaryDataTypeTable} already has a row with ID = {$val['ancillaryDataTypeId']}";
                    }
                } else {
                    $this->localLogger->errorDb('1571773020', "DB error SELECTING ID in AncillaryData::addDataType for {$this->underlyingTable}", $this->db);
                    $error = "Hard database error";
                }
            }
        } else {
            $query = "SELECT COALESCE(MAX({$this->ancillaryDataTypeTable}Id)+1, 1) as newValue ";
            $query .= "FROM {$this->ancillaryDataTypeTable} ";
            $result = $this->db->query($query);
            
            if ($result) {
                // always should return exactly one row
                $val['ancillaryDataTypeId'] = $result->fetch_assoc()['newValue'];
            } else {
                $this->localLogger->errorDb('1571773030', "DB error SELECTING MAX in AncillaryData::addDataType for {$this->underlyingTable}", $this->db);
                $error = "Hard database error";
            }
        }
        if (!$error) {
            if (!array_key_exists('underlyingDataTypeId', $val)) {
                $error = "Missing underlying type, e.g. 1 for string";
            } else {
                $temp = intval($val['underlyingDataTypeId']); // allow for possibility of string.
                
                $underlyingDataTypes = AncillaryData::getUnderlyingDataTypes();
                $validUnderlyingType = false;
                foreach ($underlyingDataTypes AS $u) {
                    if ($u['underlyingDataTypeId'] == $temp) {
                        $validUnderlyingType = true; 
                        break;                                                   
                    }
                }
                
                if ($validUnderlyingType) {
                    $val['underlyingDataTypeId'] = $temp;
                } else {
                    $error = "unknown underlying type {$val['underlyingDataTypeId']}, must be one of ";
                    foreach ($underlyingDataTypes as $ix => $row) {
                        if ($ix > 0) {
                            $error .= ', ';
                        }
                        $error .= "{$row['underlyingDataTypeId']}=>{$row['internalTypeName']}";
                    }
                }
            }
        }        
        if (!$error) {
            if (!array_key_exists('internalTypeName', $val)) {
                // Not a loggable error, but we have a bad input we'll want to return (etc., won't keep repeating this)
                $error = "Missing internal type name, e.g. 'TYPE_JOB_NUMBER'";
            } else if (!is_string($val['internalTypeName'])) {
                $error = "Internal type name must be string";                
            } else if (!trim($val['internalTypeName'])) {
                $error = "Blank internal type name";
            } else {
                $val['internalTypeName'] = trim($val['internalTypeName']);
                if (preg_match ('/^\d+$/', $val['internalTypeName'])) {
                    $error = "Internal type name cannot be a number, got ". $val['internalTypeName'];
                } else if (strlen($val['internalTypeName']) > 100 && ! $truncate) {
                    $error = "Internal type name must be less that 100 characters";
                } else {
                    $val['internalTypeName'] = substr($val['internalTypeName'], 0, 100);  
                    
                    $query = "SELECT {$this->ancillaryDataTypeTable}Id ";
                    $query .= "FROM {$this->ancillaryDataTypeTable} ";
                    $query .= "WHERE (";
                    $query .= "internalTypeName = '" . $this->db->real_escape_string($val['internalTypeName']) . "' OR ";
                    $query .= "friendlyTypeName = '" . $this->db->real_escape_string($val['internalTypeName']) . "'";
                    $query .= ")";
                    $query .= $this->namesCanDuplicateInactive ? " AND deactivated is NULL" : '';
                    $query .= ";";
                    
                    $result = $this->db->query($query);
                    if ($result) {
                        if ($result->num_rows) {
                            $error = "{$this->underlyingTable} already has a row with friendly or internal type name = {$val['internalTypeName']}";
                        }
                    } else {
                        $this->localLogger->errorDb('1571773040', "DB error SELECTING internalTypeName in AncillaryData::addDataType for {$this->underlyingTable}", $this->db);
                        $error = "Hard database error";
                    }
                }
            }
        }
            
        if (!$error) {
            if (!array_key_exists('friendlyTypeName', $val)) {
                $error = "Missing friendly type name, e.g. 'Additional Job Number'";
            } else if (!is_string($val['friendlyTypeName'])) {
                $error = "Friendly type name must be string";                
            } else if (!trim($val['internalTypeName'])) {
                $error = "Blank friendly type name";
            } else {
                $val['friendlyTypeName'] = trim($val['friendlyTypeName']);
                
                if (preg_match ('/^\d+$/', $val['friendlyTypeName'])) {
                    $error = "Friendly type name cannot be a number, got ". $val['friendlyTypeName'];
                } else if (strlen($val['friendlyTypeName']) > 100 && ! $truncate) {
                    $error = "Internal type name must be less that 100 characters";
                } else {
                    $val['friendlyTypeName'] = substr($val['friendlyTypeName'], 0, 100);
                    
                    $query = "SELECT {$this->ancillaryDataTypeTable}Id ";
                    $query .= "FROM {$this->ancillaryDataTypeTable} ";
                    $query .= "WHERE (";
                    $query .= "internalTypeName = '" . $this->db->real_escape_string($val['friendlyTypeName']) . "' OR ";
                    $query .= "friendlyTypeName = '" . $this->db->real_escape_string($val['friendlyTypeName']) . "'";
                    $query .= ")";
                    $query .= $this->namesCanDuplicateInactive ? " AND deactivated is NULL" : '';
                    $query .= ";";
                    
                    $result = $this->db->query($query);
                    if ($result) {
                        if ($result->num_rows) {
                            $error = "{$this->underlyingTable} already has a row with friendly or internal type name = {$val['friendlyTypeName']}";
                        }
                    } else {
                        $this->localLogger->errorDb('1571773040', "DB error SELECTING friendlyTypeName in AncillaryData::addDataType for {$this->underlyingTable}", $this->db);
                        $error = "Hard database error";
                    }
                }
            }            
        }

        if (!$error) {
            if (!array_key_exists('helpText', $val)) {
                $val['helpText'] = '';
            } else if (!is_string($val['helpText'])) {
                $error = "Help text must be string";                
            } else {
                $val['helpText'] = trim($val['helpText']);
                
                if (strlen($val['helpText']) > 1000 && ! $truncate) {
                    $error = "Help text name must be less that 1000 characters";
                } else {
                    $val['helpText'] = substr($val['helpText'], 0, 1000);
                }
            }
        }
        if (!$error) {
            if (!array_key_exists('singleValued', $val)) {
                $val['singleValued'] = false;
            } else if (!is_bool($val['singleValued'])) {
                if ($val['singleValued'] === 1) {
                    $val['singleValued'] = true;
                } else if ($val['singleValued'] === 0) {
                    $val['singleValued'] = false;
                } else if (is_string($val['singleValued'])) {
                    $val['singleValued'] = strtolower(trim($val['singleValued']));
                    if ($val['singleValued'] == 't' || $val['singleValued'] == 'true') {
                        $val['singleValued'] = true;
                    } else if ($val['singleValued'] == 'f' || $val['singleValued'] == 'false') {
                        $val['singleValued'] = false;
                    } else {
                        $error = "Single-valued must be Boolean, 0/1, or string 'true'/'false'";
                    }
                }
            } else {
                $error = "Single-valued must be Boolean, 0/1, or string 'true'/'false'";
            }
        }
        if (!$error) {
            if (!array_key_exists('searchable', $val)) {
                $val['searchable'] = false;
            } else if (!is_bool($val['searchable'])) {
                if ($val['searchable'] === 1) {
                    $val['searchable'] = true;
                } else if ($val['searchable'] === 0) {
                    $val['searchable'] = false;
                } else if (is_string($val['searchable'])) {
                    $val['searchable'] = strtolower(trim($val['searchable']));
                    if ($val['searchable'] == 't' || $val['searchable'] == 'true') {
                        $val['searchable'] = true;
                    } else if ($val['searchable'] == 'f' || $val['searchable'] == 'false') {
                        $val['searchable'] = false;
                    } else {
                        $error = "Searchable must be Boolean, 0/1, or string 'true'/'false'";
                    }
                }
            } else {
                $error = "Searchable must be Boolean, 0/1, or string 'true'/'false'";
            }
        }
        if (!$error) {
            $query = "INSERT INTO {$this->ancillaryDataTypeTable} (";
            $query .= "{$this->ancillaryDataTypeTable}Id, underlyingDataTypeId, internalTypeName, friendlyTypeName, helpText, singleValued, searchable, insertedPersonId";
            $query .= ") VALUES (";
            $query .= "'" . $this->db->real_escape_string($val['ancillaryDataTypeId']) . "', ";
            $query .= $val['underlyingDataTypeId'].', ';
            $query .= "'" . $this->db->real_escape_string($val['internalTypeName']) . "', ";
            $query .= "'" . $this->db->real_escape_string($val['friendlyTypeName']) . "', ";
            $query .= "'" . $this->db->real_escape_string($val['helpText']) . "', ";
            $query .= ($val['singleValued'] ? 'true' : 'false') .', ';
            $query .= ($val['searchable'] ? 'true' : 'false') .', ';
            $query .= $this->user->getUserId();
            $query .= ");";
            
            $result = $this->db->query($query);
            if (!$result) {
                $error = "Failed to insert row in {$this->ancillaryDataTypeTable}";
                $this->localLogger->errorDb('1572022827', $error, $this->db);
            } else {
                $affected = $this->db->affected_rows;
                if ($affected !=1) {
                    $error = "Expected to insert one row in {$this->ancillaryDataTypeTable}, but inserted $affected";
                    $this->localLogger->errorDb('1572022997', $error, $this->db);
                }
            }            
        }
     
        return array($error==='', $error);
    } // END public function addDataType
    
    // Deactivate an existing active data type, which may be identified by any of the following.
    // All inputs are optional, at least one must be provided; if more than one is provided, they must all match
    //  as to what row they identify.
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; 0 means 'ignore' 
    // INPUT $internalTypeName - internalTypeName (string) in DB table FOOAncillaryDataType  
    // INPUT $friendlyTypeName - friendlyTypeName (string) in DB table FOOAncillaryDataType
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->deactivateDataType(0, $internalTypeName);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    //
    //  This will rarely be called -- rare admin task -- so we want it to be bulletproof rather than efficient
    public function deactivateDataType($ancillaryDataTypeId=0, $internalTypeName='', $friendlyTypeName='') {
        $error = '';
        $ancillaryDataTypeId = intval($ancillaryDataTypeId);
        if ($internalTypeName) {
            if (!is_string($internalTypeName)) {
                $error = "Internal type name must be string";                
            } else {
                $internalTypeName = trim($internalTypeName);
            }
        }
        if (!$error) {
            if ($friendlyTypeName) {
                if (!is_string($friendlyTypeName)) {
                    $error = "Friendly type name must be string";                
                } else {
                    $internalTypeName = trim($friendlyTypeName);
                }
            }
        }
        if (!$error) {
            $where = '';
            if ($ancillaryDataTypeId) {
                $where .= "{$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId";
            }
            if ($internalTypeName) {
                $where .= ($where ? ' AND ' : '');
                $where .= "internalTypeName = '" . $this->db->real_escape_string($internalTypeName) . "'";
            }
            if ($friendlyTypeName) {
                $where .= ($where ? ' AND ' : '');
                $where .= "friendlyTypeName = " . $this->db->real_escape_string($friendlyTypeName) . "'";
            }                
            if ( ! $where ) {
                $error = "Must specify at least one of ancillaryDataTypeId, internal type name, friendly type name"; 
            }
        }
        if (!$error) {
            $query = "UPDATE {$this->ancillaryDataTypeTable} SET ";
            $query .= "deactivated = CURRENT_TIMESTAMP, ";
            $query .= "deactivatedPersonId = " . intval($this->user->getUserId()) . ' ';
            $query .= "WHERE $where AND deactivated IS NULL;";

            $result = $this->db->query($query);
            if (!$result) {
                $error = "Failed to deactivate row in {$this->ancillaryDataTypeTable}";
                $this->localLogger->errorDb('1572036896', $error, $this->db);
            } else {
                $affected = $this->db->affected_rows;
                if ($affected !=1) {
                    $error = "Expected update to deactivate one row in {$this->ancillaryDataTypeTable}, but updated $affected";
                    $this->localLogger->errorDb('1572036897', $error, $this->db);
                }
            }
        }        
        
        return array($error==='', $error);
    } // END public function deactivateDataType
    
    // INPUT $ancillaryDataTypeId - Primary key in DB table $this->ancillaryDataTypeTable
    // INPUT $activeOnly - Boolean, if true (which is the default) we get only rows with 'deactivated IS null'.
    //   For clarity, use AncillaryData::ACTIVE_ONLY or AncillaryData::INACTIVE_ALLOWED.
    // INPUT $showHistory - Boolean. Effective default is the inverse of $activeOnly.
    //   For clarity, use AncillaryData::SHOW_HISTORY or AncillaryData::DONT_SHOW_HISTORY
    // RETURN: exactly like the getDataTypes method, except that the return can only have at most one row.
    //  Return false on error.
    public function getDataTypeById($ancillaryDataTypeId, $activeOnly=true, $showHistory=null) {
        $ret = array();
        if ($showHistory === null) {
            $showHistory = !$activeOnly;
        }
        
        $query = "SELECT {$this->ancillaryDataTypeTable}Id AS ancillaryDataTypeId, ";
        $query .= 'underlyingDataTypeId, internalTypeName, friendlyTypeName, helpText, singleValued, searchable';
        $query .= $showHistory ? ', inserted, insertedPersonId, deactivated, deactivatedPersonId ' : ' ';
        $query .= "FROM {$this->ancillaryDataTypeTable}";
        $query .= " WHERE ";
        if ($activeOnly) {
            $query .= " deactivated IS NULL AND "; 
        }
        $query .= "{$this->ancillaryDataTypeTable}Id = " . intval($ancillaryDataTypeId) . ";";
        $result = $this->db->query($query);
        if ($result) {
            if ($row = $result->fetch_assoc()) { // NOTE assignment in conditional
                $ret[] = $row;
            }
        } else {
            $this->localLogger->errorDb('1572040962', "DB error SELECTing in AncillaryData::getDataTypeById for {$this->underlyingTable}", $this->db);
            return false;
        }

        return $ret;
    } // END public function getDataTypeById
    
    // Common code for getDataTypeByInternalTypeName, getDataTypeByFriendlyTypeName
    // INPUT $modifier - must be 'internal' or 'friendly'. Not validated because this can only 
    //   be called from within this class.
    // INPUT $typeName - Name we use to refer to this in code. E.g. an internal type name like 'TYPE_JOB_NUMBER'
    //  or a friendly type name like 'job number'.
    // INPUT $activeOnly - Boolean, if true (which is the default) we get only rows with 'deactivated IS null'
    //   For clarity, use AncillaryData::ACTIVE_ONLY or AncillaryData::INACTIVE_ALLOWED.
    // INPUT $showHistory - Boolean. Effective default is the inverse of $activeOnly.
    //   For clarity, use AncillaryData::SHOW_HISTORY or AncillaryData::DONT_SHOW_HISTORY
    // RETURN: exactly like the getDataTypes method.
    //  If $activeOnly == true, then the return can only have at most one row.
    //  Return false on error.
    private function getDataTypeByTypeName($modifier, $typeName, $activeOnly=true, $showHistory=null) {
        $ret = array();
        $typeName = trim($typeName);
        if ($showHistory === null) {
            $showHistory = !$activeOnly;
        }
        $query = "SELECT {$this->ancillaryDataTypeTable}Id AS ancillaryDataTypeId, ";
        $query .= 'underlyingDataTypeId, internalTypeName, friendlyTypeName, helpText, singleValued, searchable';
        $query .= $showHistory ? ', inserted, insertedPersonId, deactivated, deactivatedPersonId ' : ' ';
        $query .= "FROM {$this->ancillaryDataTypeTable}";
        $query .= " WHERE ";
        if ($activeOnly) {
            $query .= " deactivated IS NULL AND "; 
        }
        $query .= "{$modifier}TypeName = '" . $this->db->real_escape_string($typeName)."';";
        $result = $this->db->query($query);
        if ($result) {
            if ($row = $result->fetch_assoc()) { // NOTE assignment in conditional
                $ret[] = $row;
            }
        } else {
            $this->localLogger->errorDb('1572040975', "DB error SELECTing in AncillaryData::getDataTypeByTypeName ({$modifier}TypeName) for {$this->underlyingTable}", $this->db);
            return false;
        }
        return $ret;
    } // END private function getDataTypeByTypeName

    // INPUT $internalTypeName - Name we use to refer to this in code. E.g. 'TYPE_JOB_NUMBER'.
    // INPUT $activeOnly - Boolean, if true (which is the default) we get only rows with 'deactivated IS null'
    //   For clarity, use AncillaryData::ACTIVE_ONLY or AncillaryData::INACTIVE_ALLOWED.
    // INPUT $showHistory - Boolean. Effective default is the inverse of $activeOnly.
    //   For clarity, use AncillaryData::SHOW_HISTORY or AncillaryData::DONT_SHOW_HISTORY
    // RETURN: exactly like the getDataTypes method. 
    //  If $activeOnly == true, then the return can only have at most one row.
    //
    // So a reasonable way to get id from an active internal type name would be
    //    $typeNameInfo = getDataTypeByInternalTypeName($internalTypeName);
    //    if (count($typeNameInfo)) {
    //        // there can only be one, given that we get back only active rows
    //        $ancillaryDataTypeId = $typeNameInfo[0]['ancillaryDataTypeId']; 
    //    } else {
    //        // didn't find any
    //        $ancillaryDataTypeId = 0; 
    //    }
    public function getDataTypeByInternalTypeName($internalTypeName, $activeOnly=true, $showHistory=null) {
        return $this->getDataTypeByTypeName('internal', $internalTypeName, $activeOnly, $showHistory);
    } // END public function getDataTypeByInternalTypeName
    
    // INPUT $friendlyTypeName - Name we use to refer to this in code. E.g. 'job number'.
    // INPUT $activeOnly - Boolean, if true (which is the default) we get only rows with 'deactivated IS null'
    //   For clarity, use AncillaryData::ACTIVE_ONLY or AncillaryData::INACTIVE_ALLOWED.
    // INPUT $showHistory - Boolean. Effective default is the inverse of $activeOnly.
    //   For clarity, use AncillaryData::SHOW_HISTORY or AncillaryData::DONT_SHOW_HISTORY
    // RETURN: exactly like the getDataTypes method. 
    // If $activeOnly == true, then the return can only have at most one row.
    // Return false on error.
    //
    // So a reasonable way to get id from an active friendly type name would be
    //    $typeNameInfo = getDataTypeByFriendlyTypeName($friendlyTypeName);
    //    if (count($typeNameInfo)) {
    //        // there can only be one, given that we get back only active rows
    //        $ancillaryDataTypeId = $typeNameInfo[0]['ancillaryDataTypeId']; 
    //    } else {
    //        // didn't find any
    //        $ancillaryDataTypeId = 0; 
    //    }
    public function getDataTypeByFriendlyTypeName($friendlyTypeName, $activeOnly=true, $showHistory=null) {
        return $this->getDataTypeByTypeName('friendly', $friendlyTypeName, $activeOnly, $showHistory);
    } // END public function getDataTypeByInternalTypeName
    
    // Common code for changeInternalTypeName, validateInternalTypeName, changeFriendlyTypeName, validateFriendlyTypeName, 
    // INPUT $takeAction - BOOLEAN, true => actually make the change, false => just calling to validate inputs, don't make the change
    // INPUT $modifier - must be 'internal' or 'friendly'. Not validated because this can only 
    //   be called from within this class.
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; mandatory 
    // INPUT $newInternalTypeName - proposed new internalTypeName (string) for the row with the relevant $ancillaryDataTypeId in DB table FOOAncillaryDataType
    // INPUT $truncate - BOOL, default false. If true, then any over-length strings will be truncated instead of causing errors.
    //   For clarity, use AncillaryData::MAY_TRUNCATE or AncillaryData::DONT_TRUNCATE
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure.
    // So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->changeOrValidateTypeName(true, 'internal', $ancillaryDataTypeId, $newTypeName);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    private function changeOrValidateTypeName($takeAction, $modifier, $ancillaryDataTypeId, $newTypeName, $truncate=false) {
        $error = '';
        $temp = intval($ancillaryDataTypeId);
        if ( $temp <= 0) {
            $error = "$ancillaryDataTypeId is not a valid ID";
        } else {            
            $ancillaryDataTypeId = $temp; // assign it the cleaned-up value
        }
        
        if (!$error) {
            if (!is_string($newTypeName)) {
                $error = "$modifier type name must be string";                
            } else if (!trim($newTypeName)) {
                $error = "Blank $modifier type name";
            } else {
                $newTypeName = trim($newTypeName);
                if (strlen($newTypeName) > 100 && ! $truncate) {
                    $error = "$modifier type name must be less that 100 characters";
                } else {
                    $newTypeName = substr($newTypeName, 0, 100);                
                    
                    // If the row we are changing is active, we have to make sure no OTHER active row contains this internalTypeName
                    // If the following query returns any rows, we have a conflict.
                    $query = "SELECT {$modifier}TypeName, {$this->ancillaryDataTypeTable}Id AS ancillaryDataTypeId, deactivated ";
                    $query .= "FROM {$this->ancillaryDataTypeTable} "; 
                    $query .= "WHERE {$modifier}TypeName= '".$this->db->real_escape_string($newTypeName)."' "; // same internal type name
                    $query .= "AND {$this->ancillaryDataTypeTable}Id <> $ancillaryDataTypeId "; // different row
                    $query .= "AND deactivated IS NULL "; // active row
                    $query .= "AND EXISTS (";
                    // From here on is about the CHANGED row being active
                    $query .=     "SELECT {$this->ancillaryDataTypeTable}Id AS ancillaryDataTypeId2 ";
                    $query .=     "FROM {$this->ancillaryDataTypeTable} ";
                    $query .=     "WHERE {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId "; // same row
                    $query .=     "AND deactivated IS NULL"; // active row
                    $query .= ")";
                     
                    $result = $this->db->query($query);
                    
                    if ($result) {
                        // If any rows return, that's a conflict
                        if ($result->num_rows) {
                            $error = "There is already ". ($modifier = 'internal' ? 'an internal' : 'a friendly') ." type name '$newTypeName'";
                        }
                    } else {
                        $this->localLogger->errorDb('1572452832', "DB error looking for conflict in AncillaryData:changeOrValidateTypeName", $this->db);
                        $error = "Hard database error";
                    }
                }
            }
        }
        
        if (!$error && $takeAction) {
            $query = "UPDATE {$this->ancillaryDataTypeTable} SET ";
            $query .= "{$modifier}TypeName='{$this->db->real_escape_string($newTypeName)}' ";
            $query .= "WHERE {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId;";

            $result = $this->db->query($query);
            if (!$result) {
                $error = "Failed to set {$modifier} type name for row in {$this->ancillaryDataTypeTable}";
                $this->localLogger->errorDb('1572452858', $error, $this->db);
            } else {
                $affected = $this->db->affected_rows;
                if ($affected !=1) {
                    $error = "Expected update to set {$modifier} type name in one row in {$this->ancillaryDataTypeTable}, but updated $affected";
                    $this->localLogger->errorDb('1572452877', $error, $this->db);
                }
            }
        }   
        
        return array($error==='', $error);
    } // END private function changeOrValidateTypeName

    // Change the internal type name for an existing data type, which may be active or not.
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; mandatory 
    // INPUT $newInternalTypeName - proposed new internalTypeName (string) for the row with the relevant $ancillaryDataTypeId in DB table FOOAncillaryDataType
    // INPUT $truncate - BOOL, default false. If true, then any over-length strings will be truncated instead of causing errors.
    //   For clarity, use AncillaryData::MAY_TRUNCATE or AncillaryData::DONT_TRUNCATE
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->changeInternalTypeName($ancillaryDataTypeId, $newInternalTypeName);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    public function changeInternalTypeName($ancillaryDataTypeId, $newInternalTypeName, $truncate=false) {
        return $this->changeOrValidateTypeName(true, 'internal', $ancillaryDataTypeId, $newInternalTypeName, $truncate);
    } // END public function changeInternalTypeName
    
    // Just like public function changeInternalTypeName, BUT DON'T ACTUALLY TAKE THE ACTION. 
    // If this returns successfully, then the action should succeed; all that is omitted is the update.
    public function validateInternalTypeName($ancillaryDataTypeId, $newInternalTypeName, $truncate=false) {
        return $this->changeOrValidateTypeName(false, 'internal', $ancillaryDataTypeId, $newInternalTypeName, $truncate);
    } // END public function validateInternalTypeName
    
    // Change the friendly type name for an existing data type, which may be active or not.
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; mandatory 
    // INPUT $newInternalTypeName - proposed new internalTypeName (string) for the row with the relevant $ancillaryDataTypeId in DB table FOOAncillaryDataType
    // INPUT $truncate - BOOL, default false. If true, then any over-length strings will be truncated instead of causing errors
    //   For clarity, use AncillaryData::MAY_TRUNCATE or AncillaryData::DONT_TRUNCATE
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->changeFriendlyTypeName($ancillaryDataTypeId, $newFriendlyTypeName);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    public function changeFriendlyTypeName($ancillaryDataTypeId, $newFriendlyTypeName, $truncate=false) {
        return $this->changeOrValidateTypeName(true, 'friendly', $ancillaryDataTypeId, $newFriendlyTypeName, $truncate);
    } // END public function changeInternalTypeName
    
    
    // Just like public function changeInternalTypeName, BUT DON'T ACTUALLY TAKE THE ACTION. 
    // If this returns successfully, then the action should succeed; all that is omitted is the update.
    public function validateFriendlyTypeName($ancillaryDataTypeId, $newFriendlyTypeName, $truncate=false) {
        return $this->changeOrValidateTypeName(false, 'friendly', $ancillaryDataTypeId, $newFriendlyTypeName, $truncate);
    } // END public function validateFriendlyTypeName
    
    // Common code for changeSearchable, validateSearchable 
    // INPUT $takeAction - BOOLEAN, true => actually make the change, false => just calling to validate inputs, don't make the change
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; mandatory 
    // INPUT $newSearchable - Boolean. True => searchable. We will accept anything "truthy" as true
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->changeOrValidateSearchable(true, $ancillaryDataTypeId, $newSearchable);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    private function changeOrValidateSearchable($takeAction, $ancillaryDataTypeId, $newSearchable) {    
        $error = '';
        $temp = intval($ancillaryDataTypeId);
        if ( $temp <= 0) {
            $error = "$ancillaryDataTypeId is not a valid ID";
        } else {            
            $ancillaryDataTypeId = $temp; // assign it the cleaned-up value
        }
        
        if (!$error && $takeAction) {
            $query = "UPDATE {$this->ancillaryDataTypeTable} SET ";
            $query .= "searchable=" . ($newSearchable ? 'true' : 'false') . " ";
            $query .= "WHERE {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId;";

            $result = $this->db->query($query);
            if (!$result) {
                $error = "Failed to set searchable for row in {$this->ancillaryDataTypeTable}";
                $this->localLogger->errorDb('1572453020', $error, $this->db);
            } else {
                $affected = $this->db->affected_rows;
                if ($affected !=1) {
                    $error = "Expected update to set searchable in one row in {$this->ancillaryDataTypeTable}, but updated $affected";
                    $this->localLogger->errorDb('1572453025', $error, $this->db);
                }
            }
        }   
        
        return array($error==='', $error);
    } // END private function changeOrValidateSearchable
    
    // See changeOrValidateSearchable for documentation of inputs, returns
    public function changeSearchable($ancillaryDataTypeId, $newSearchable) {
        return $this->changeOrValidateSearchable(true, $ancillaryDataTypeId, $newSearchable);
    }
    
    // See changeOrValidateSearchable for documentation of inputs, returns
    public function validateSearchable($ancillaryDataTypeId, $newSearchable) {
        return $this->changeOrValidateSearchable(false, $ancillaryDataTypeId, $newSearchable);
    }
    
    // Common code for changeSingleValued, validateSingleValued 
    // INPUT $takeAction - BOOLEAN, true => actually make the change, false => just calling to validate inputs, don't make the change
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; mandatory 
    // INPUT $newSingleValued - Boolean. True => single-valued. We will accept anything "truthy" as true
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->changeOrValidateSingleValued(true, $ancillaryDataTypeId, $newSearchable);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  }
    // 
    // This one is a bit tricky: we can't set single-valued to false if there is already corresponding 
    //  active, multi-valued data in the relevant DB table.
    private function changeOrValidateSingleValued($takeAction, $ancillaryDataTypeId, $newSingleValued) {
        $error = '';
        $temp = intval($ancillaryDataTypeId);
        if ( $temp <= 0) {
            $error = "$ancillaryDataTypeId is not a valid ID";
        } else {            
            $ancillaryDataTypeId = $temp; // assign it the cleaned-up value
        }
        
        if (!$error && $newSingleValued) {
            // Need to make sure there isn't any existing multi-valued data in the relevant DB table.
            $query = "SELECT count(*) ";  
            $query .= "FROM {$this->ancillaryDataTable} ";           // corresponding data table (vs. dataType table)
            $query .= "WHERE deleted IS NULL ";                      // active data only
            $query .= "AND {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId "; // this ancillary data type
            $query .= "GROUP BY {$this->underlyingTable}Id ";        // which element it describes
            $query .= "HAVING count(*) > 1;";
            
            $result = $this->db->query($query);
            
            if ($result) {
                if ($result->num_rows) {
                    // TRY to get a friendly name for the ancillary data type in question; OK if this fails.
                    $friendlyTypeName = "(Can't get type name)";
                    $rows = $this->getDataTypeById($ancillaryDataTypeId, false);
                    if ( count($rows)) {
                        $friendlyName = $rows[0]['friendlyTypeName']; 
                    }                    
                    $error = "{$this->underlyingTable} has a row with more than one value for ancillary " . 
                            "data type $friendlyTypeName (ID=$ancillaryDataTypeId), so we cannot make this single-valued.";
                }
            } else {
                $this->localLogger->errorDb('1571773020', "DB error SELECTING ID in AncillaryData::addDataType for {$this->underlyingTable}", $this->db);
                $error = "Hard database error";
            }            
        }
        
        if (!$error && $takeAction) {
            $query = "UPDATE {$this->ancillaryDataTypeTable} SET ";
            $query .= "singleValued=" . ($newSingleValued ? 'true' : 'false') . " ";
            $query .= "WHERE {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId;";

            $result = $this->db->query($query);
            if (!$result) {
                $error = "Failed to set singleValued for row in {$this->ancillaryDataTypeTable}";
                $this->localLogger->errorDb('1572453120', $error, $this->db);
            } else {
                $affected = $this->db->affected_rows;
                if ($affected !=1) {
                    $error = "Expected update to set singleValued in one row in {$this->ancillaryDataTypeTable}, but updated $affected";
                    $this->localLogger->errorDb('1572453125', $error, $this->db);
                }
            }
        }   
        
        return array($error==='', $error);
    } // END private function changeOrValidateSingleValued
    
    // See changeOrValidateSingleValued for documentation of inputs, returns
    public function changeSingleValued($ancillaryDataTypeId, $newSearchable) {
        return $this->changeOrValidateSingleValued(true, $ancillaryDataTypeId, $newSearchable);
    }
    
    // See changeOrValidateSingleValued for documentation of inputs, returns
    public function validateSingleValued($ancillaryDataTypeId, $newSearchable) {
        return $this->changeOrValidateSingleValued(false, $ancillaryDataTypeId, $newSearchable);
    }
    
    // Common code for changeHelpText, validateHelpText 
    // INPUT $takeAction - BOOLEAN, true => actually make the change, false => just calling to validate inputs, don't make the change
    // INPUT $ancillaryDataTypeId - primary key (positive integer) to DB table FOOAncillaryDataType; mandatory 
    // INPUT $newHelpText - proposed new help text (string). Currently we are trusting our admins not to mess this up: HTML is allowed, should 
    //  be valid HTML!
    // INPUT $truncate - BOOL, default false. If true, then any over-length strings will be truncated instead of causing errors.
    //   For clarity, use AncillaryData::MAY_TRUNCATE or AncillaryData::DONT_TRUNCATE
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure.
    // So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->changeOrValidateTypeName(true, $ancillaryDataTypeId, $newHelpText);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    private function changeOrValidateHelpText($takeAction, $ancillaryDataTypeId, $newHelpText, $truncate=false) {
        $error = '';
        $temp = intval($ancillaryDataTypeId);
        if ( $temp <= 0) {
            $error = "$ancillaryDataTypeId is not a valid ID";
        } else {            
            $ancillaryDataTypeId = $temp; // assign it the cleaned-up value
        }
        
        if (!$error) {
            if (!is_string($newHelpText)) {
                $error = "Help text must be string";                
            } else {
                $newHelpText = trim($newHelpText);
                if (strlen($newHelpText) > 1000 && ! $truncate) {
                    $error = "Help text must be less that 1000 characters";
                } else {
                    $newHelpText = substr($newHelpText, 0, 1000);               
                    
                }
            }
        }
        
        if (!$error && $takeAction) {
            $query = "UPDATE {$this->ancillaryDataTypeTable} SET ";
            $query .= "helpText='{$this->db->real_escape_string($newHelpText)}' ";
            $query .= "WHERE {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId;";

            $result = $this->db->query($query);
            if (!$result) {
                $error = "Failed to set help text for row in {$this->ancillaryDataTypeTable}";
                $this->localLogger->errorDb('1572452858', $error, $this->db);
            } else {
                $affected = $this->db->affected_rows;
                if ($affected !=1) {
                    $error = "Expected update to set help text in one row in {$this->ancillaryDataTypeTable}, but updated $affected";
                    $this->localLogger->errorDb('1572452877', $error, $this->db);
                }
            }
        }   
        
        return array($error==='', $error);
    } // END private function changeOrValidateHelpText

    // See changeOrValidateHelpText for documentation of inputs, returns
    public function changeHelpText($ancillaryDataTypeId, $newHelpText, $truncate=false) {
        return $this->changeOrValidateHelpText(true, $ancillaryDataTypeId, $newHelpText, $truncate);
    }
    
    // See changeOrValidateHelpText for documentation of inputs, returns
    public function validateHelpText($ancillaryDataTypeId, $newHelpText, $truncate=false) {
        return $this->changeOrValidateHelpText(false, $ancillaryDataTypeId, $newHelpText, $truncate);
    }
    
    
    // ============ BEGIN FUNCTIONS TO GET DATA FROM DATA TABLE ============
    
    // Get all the data of a particular type from the relevant ancillary data table.
    // INPUT $rowId - Primary key in DB table $this->underlyingTable. 0 means "don't care, give me everything". 
    //    NOTE that the column in the relevant DB tables is actually $this->underlyingTable.'Id'.
    // INPUT $ancillaryDataTypeId - This can be the obvious primary key in DB table $this->ancillaryDataTypeTable --   
    //    $this->underlyingTable.'AncillaryDataTypeId' (most efficient) -- but for active types it can also be the internal or friendly type name.
    // INPUT $searchableOnly - Boolean, if true only searchable data should be returned. Default: false.
    // INPUT $activeOnly - Boolean, if true (which is the default) we get only rows with 'deleted IS null'
    //   For clarity, use AncillaryData::ACTIVE_ONLY or AncillaryData::INACTIVE_ALLOWED.
    // INPUT $showHistory - Boolean. Effective default is the inverse of $activeOnly.  If true, returned rows will include 'inserted', 
    //    'insertedPersonId', 'deleted', 'deletedPersonId'. If false, these will be excluded.
    //   For clarity, use AncillaryData::SHOW_HISTORY or AncillaryData::DONT_SHOW_HISTORY
    // RETURN an array of associative arrays, one row per ancillary data item. 
    // For each ancillary data item, the associative array will contain the following index-value pairs; inputs determine which of these we want:
    //  * 'rowId' -  Primary key in DB table $this->underlyingTable. Identifies the row in that table 
    //    for which this is ancillary data
    //  * 'ancillaryDataTypeId' - Primary key in DB table $this->ancillaryDataTypeTable. 
    //    NOTE that the column in the DB is actually $this->underlyingTable.'AncillaryDataTypeId'.
    //  * 'ancillaryDataId' - Primary key in DB table $this->ancillaryDataTable. 
    //    NOTE that the column in the DB is actually $this->underlyingTable.'AncillaryDataId'.
    //  * 'internalTypeName' - Name we use to refer to this in code. E.g. 'TYPE_JOB_NUMBER'. From $this->ancillaryDataTypeTable. 
    //  * 'friendlyTypeName' - Name we  use to refer to this in the UI. E.g. 'Additional Job Number'. From $this->ancillaryDataTypeTable.
    //  * 'helpText' - help text for type.
    //  * 'searchable' - BOOL. True => intended to be searchable, leading to the related row in the underlying DB table From $this->ancillaryDataTypeTable.
    //  * 'singleValued' - BOOL. True => type is single-valued for any row in the underlying table.
    //  * 'underlyingDataTypeId' - ID indicating string vs. unsigned int, etc. From $this->ancillaryDataTypeTable.
    //  * 'value' - the meaning of this depends on 'underlyingDataTypeId', and this can come from any of the following columns:
    //    stringValue, unsignedIntegerValue, signedIntegerValue, datetimeValue    
    //  * 'inserted' - DATETIME.  When inserted into table.  (only if $showHistory==true)    
    //  * 'insertedPersonId' - Who inserted it. Nullable, optional. When present, foreign key into DB table Person.  (only if $showHistory==true)
    //  * 'deleted' - DATETIME. Nullable, null if row is active.  (only if $showHistory==true)
    //  * 'deletedPersonId' - Who deleted it. Nullable, used only if column 'deleted' is non-null, and optional even then. 
    //    When present, foreign key into DB table Person.  (only if $showHistory==true)
    // Return false on error.
    public function getData($rowId=0, $ancillaryDataTypeId=0, $searchableOnly=false, $activeOnly=true, $showHistory=null) {
        $ret = array();
        
        if ($showHistory === null) {
            $showHistory = !$activeOnly;
        }
        
        $where = '';
        if ($activeOnly) {
            $where .= "deleted IS NULL"; 
        }
        if ($ancillaryDataTypeId) {
            // Make sense of the $ancillaryDataTypeId; overwrite it with a proper ID if necessary
            $ancillaryDataType = $this->getDataTypeById($ancillaryDataTypeId, AncillaryData::INACTIVE_ALLOWED) || 
                                 $this->getDataTypeByInternalTypeName($ancillaryDataTypeId) ||
                                 $this->getDataTypeByFriendlyTypeName($ancillaryDataTypeId);            
            if ($ancillaryDataType) {
                $ancillaryDataTypeId = $ancillaryDataType['ancillaryDataTypeId']; // overwrite
                if ($where) {
                    $where .= " AND ";
                }
                $where .= "ancillaryDataTypeId = $ancillaryDataTypeId";
            } else {
                // $ancillaryDataTypeId was provided, but it's "bad". Log error & BAIL OUT
                $this->localLogger->error2('1572557265', "No ancillaryDataTypeId '$ancillaryDataTypeId' for table {$this->underlyingTable}");
                
                return false;
            }
        }
        if ($rowId) {
            // Not bothering to validate: if it's not a valid row, then this will not return any matches.
            if ($where) {
                $where .= " AND ";
            }
            $where .= "data.{$this->underlyingTable}Id = $rowId";
        }
        if ($searchableOnly) {
            if ($where) {
                $where .= " AND ";
            }
            $where .= "searchable <> 0";
        }
        
        $query = "SELECT data.{$this->underlyingTable}Id AS rowId, ";
        $query .= "data.{$this->ancillaryDataTable}Id AS ancillaryDataId, ";
        $query .= "data.{$this->ancillaryDataTypeTable}Id AS ancillaryDataTypeId, ";
        $query .= "type.internalTypeName, ";
        $query .= "type.friendlyTypeName, ";
        $query .= "type.helpText, ";
        $query .= "type.searchable, ";
        $query .= "type.singleValued, ";
        $query .= "type.underlyingDataTypeId, ";
        $query .= "COALESCE(data.stringValue, data.unsignedIntegerValue, data.signedIntegerValue, data.datetimeValue) AS value ";
        $query .= $showHistory ? ', data.inserted, data.insertedPersonId, data.deleted, data.deletedPersonId ' : '';
        $query .= "FROM {$this->ancillaryDataTable} AS data ";
        $query .= "JOIN {$this->ancillaryDataTypeTable} AS type ON data.{$this->ancillaryDataTypeTable}Id = type.{$this->ancillaryDataTypeTable}Id ";
        $query .= "WHERE $where ";
        $query .= ";";
        
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) { // NOTE assignment in conditional
                $ret[] = $row;
            }
        } else {
            $this->localLogger->errorDb('1572469386', "DB error SELECTing in AncillaryData::getData for {$this->underlyingTable}", $this->db);
            return false;
        }
        return $ret;
    } // END public function getData
    
    // For convenience, some functions that wrap getData
    
    // Return all active, searchable data for table
    public function getAllSearchableData() {
        return $this->getData(0, 0, $this->searchableOnly);
    }
    
    // Return all active data for a particular row of the underlying table
    public function getAllDataForRow($rowId) {
        return $this->getData(0, $rowId);
    }

    // ============ BEGIN FUNCTIONS TO MODIFY DATA IN DATA TABLE ============
    // Put a value in the table. The semantics of this are a bit various: 
    // * If this value is already present, has no effect.
    // * Otherwise, if the relevant ancillary data type is single-valued, this will soft-delete and replace any existing value.
    // * Otherwise, if multi-valued and this value is not already present, adds this value.
    // INPUT $rowId - Primary key in DB table $this->underlyingTable. Must be a valid primary key into that table.
    //    NOTE that the column in the relevant DB tables is actually $this->underlyingTable.'Id'.
    // INPUT $ancillaryDataTypeId - This can be the obvious primary key in DB table $this->ancillaryDataTypeTable --   
    //    $this->underlyingTable.'AncillaryDataTypeId' (most efficient) -- but FOR ACTIVE TYPES it can also be the internal or friendly type name.
    // INPUT $val - type should be appropriate for $ancillaryDataTypeId. If it's a datetime, it should be either in the form
    //    'YYYY-MM-DD hh:mm:ss'' or just 'YYYY-MM-DD''.
    // INPUT $truncate - true => truncation is allowed. Falseh is the default. Only relevant for strings.
    // RETURN Array with two values: first is boolean success, second is a message, relevant only on failure. So a typical call to this would be:
    //  list($success, $error) = $ancillaryJobData->putData($jobId, AncillaryDate::UNDERLYING_DATA_TYPE_STRING, $val);
    //  if ( ! $success ) {
    //     // report the error $error 
    //  } 
    public function putData($rowId, $ancillaryDataTypeId, $val, $truncate=false) {
        $error = '';
        
        if (is_null($val)) {
            $error = " AncillaryData::putData cannot have a null \$val input";
            $this->localLogger->error2('1572559487', $error);
        }   
                
        if (!$error) {
            // Make sure we have a valid $rowId
            $query = "SELECT {$this->underlyingTable}Id ";
            $query .= "FROM {$this->underlyingTable} ";
            $query .= "WHERE {$this->underlyingTable}Id = $rowId;"; 
    
            $result = $this->db->query($query);
            if ($result) {
                // Selected on primary key, so there should be zero or one match
                if ($result->num_rows == 0) {
                    $error = " AncillaryData::putData: $rowId is not primary key for a row in {$this->underlyingTable}";
                    $this->localLogger->error2('1572557767', $error);
                }
            } else {
                $error = "DB error SELECTing in AncillaryData::putData for {$this->underlyingTable}";
                $this->localLogger->errorDb('1572557755', $error, $this->db);
            }
        }
        
        if (!$error) {
            // Try to make sense of the ancillary data type
            $ancillaryDataTypeArray = $this->getDataTypeById($ancillaryDataTypeId, AncillaryData::INACTIVE_ALLOWED);
            if (!$ancillaryDataTypeArray) {
                $ancillaryDataTypeArray = $this->getDataTypeByInternalTypeName($ancillaryDataTypeId);
            }
            if (!$ancillaryDataTypeArray) {
                 $ancillaryDataTypeArray = $this->getDataTypeByFriendlyTypeName($ancillaryDataTypeId);
            }
            
            if (!$ancillaryDataTypeArray) {
                $error = "AncillaryData::putData: No ancillaryDataTypeId '$ancillaryDataTypeId' for table {$this->underlyingTable}";
                $this->localLogger->error2('1572471761', $error);
            }
        }
        
        if (!$error) {
            if (count($ancillaryDataTypeArray) != 1) {
                $error = "AncillaryData::putData: Expect one matching row for {$this->underlyingTable} data type '$ancillaryDataTypeId', got " . count($ancillaryDataTypeArray);
                $this->localLogger->error2('1573242855', $error);                
            }
        }
        
        if (!$error) {
            $ancillaryDataTypeId = $ancillaryDataTypeArray[0]['ancillaryDataTypeId']; // overwrite: might have been a string.
            $friendlyTypeName = $ancillaryDataTypeArray[0]['friendlyTypeName']; 
            $underlyingDataTypeId = $ancillaryDataTypeArray[0]['underlyingDataTypeId'];
            $singleValued = $ancillaryDataTypeArray[0]['singleValued'];            
            
            $underlyingDataTypes = AncillaryData::getUnderlyingDataTypes();
            $underlyingDataType = null;
            foreach ($underlyingDataTypes AS $u) {
                if ($u['underlyingDataTypeId'] == $underlyingDataTypeId) {
                    $underlyingDataType = $u; // This is a row in the table returned by AncillaryData::getUnderlyingDataTypes() 
                    break;                                                   
                }
            }
            if (!$underlyingDataType) {
                // This would be weird, but let's cover it
                $error = "No underlying data type [$underlyingDataTypeId] for table {$this->underlyingTable}";
                $this->localLogger->error2('1572471761', $error);
            }
        }
                
        if (!$error && !is_null($val)) {
            $column = $underlyingDataType['column'];            
            if ($underlyingDataTypeId == AncillaryData::UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER) {
                if (intval($val) < 0) {
                    $error = "$friendlyTypeName should be unsigned integer;". trim($val) . " is not a valid value.";   
                }
            } else if ($underlyingDataTypeId == AncillaryData::UNDERLYING_DATA_TYPE_DATETIME) {
                $val = trim($val);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    // just date. Make sure it's a valid date.
                    $dt = DateTime::createFromFormat("Y-m-d", $val);
                    if ($dt === false || !array_sum($dt::getLastErrors())) {
                        $error = "Invalid date '$val'"; 
                    }
                } else if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}-\d{2}-\d{2}$/', $val)) {
                    // Date & time. Make sure it's valid.
                    $dt = DateTime::createFromFormat("Y-m-d H:i:s", $val);
                    if ($dt === false || !array_sum($dt::getLastErrors())) {
                        $error = "Invalid date and time '$val'"; 
                    }
                } else {
                    // "shape" of this is invalid
                    $error = "Invalid date/time '$val', expects form 'YYYY-MM-DD' or 'YYYY-MM-DD hh:mm:ss'";
                }
            } else if ($underlyingDataTypeId == AncillaryData::UNDERLYING_DATA_TYPE_STRING) {
                // Need to prevent HTML tags 
                $val = trim($val);
                $val = str_replace('<', '&lt;', $val);
                
                if (strlen($val) > 2000) {
                    if ($truncate) {
                        $val = substr($val, 0, 2000);
                    } else {
                        $error = "String too long (limit 2000, including some expansion of HTML entities).";
                    }
                }
            }
            if (!$error) {
                $valString = '';
                if ($underlyingDataTypeId == AncillaryData::UNDERLYING_DATA_TYPE_UNSIGNED_INTEGER ||
                    $underlyingDataTypeId == AncillaryData::UNDERLYING_DATA_TYPE_SIGNED_INTEGER)
                {
                    $val = intval($val);
                    $valString = (string)$val;
                } else {
                    // string-based
                    $val = trim($val);
                    $valString = "'".$this->db->real_escape_string(trim($val))."'";
                }
            }
        } // END if (!$error && !is_null($val))

        if (!$error) {
            // We've now validated our input and created the relevant value string for a SQL INSERT or UPDATE query
            // This now breaks down to several cases.
            
            // First test: is the value already there?
            // (We could probably use getData method here, but I - JM -- think this is clearer.)
            $query = "SELECT $column FROM {$this->ancillaryDataTable} ";
            $query .= "WHERE {$this->underlyingTable}Id = $rowId ";
            $query .= "AND {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId ";
            $query .= "AND deleted IS NULL;";

            $result = $this->db->query($query);
            if (!$result) {
                $error = "DB error SELECTing in AncillaryData::$fnName for {$this->underlyingTable}";
                $this->localLogger->errorDb('1572549705', $error, $this->db);
            }
            if (!$error) {
                $hasValue = !! $result->num_rows;
                $alreadyThere = false;
                // Look through the results for a matching value.
                while ($row = $result->fetch_assoc()) { // NOTE assignment in conditional
                    if ($row[$column] === $val) {
                        $alreadyThere = true;
                        // Nothing to do (and we want to leave the old "inserted" datetime alone)
                        break;
                    }
                }
                if (!$alreadyThere) {
                    if ($singleValued && $hasValue) {
                        // Kill any prior value.
                        // It's single-valued, so no need for the WHERE clause to check '$column=$valString' again.
                        $query = "UPDATE {$this->ancillaryDataTable} ";
                        $query .= "SET deleted=CURRENT_TIMESTAMP, deletedPersonId=" . $this->user->getUserId() . " ";  
                        $query .= "WHERE {$this->underlyingTable}Id = $rowId ";
                        $query .= "AND {$this->ancillaryDataTypeTable}Id = $ancillaryDataTypeId ";
                        $query .= "AND deleted IS NULL;";
                        
                        $result = $this->db->query($query);
                        if (!$result) {
                            $error = "DB error UPDATEing in AncillaryData::$fnName for {$this->underlyingTable}";
                            $this->localLogger->errorDb('1572549733', $error, $this->db);
                        }
                    }
                    if (!$error) {
                        $query = "INSERT INTO {$this->ancillaryDataTable} (";
                        $query .= "{$this->ancillaryDataTypeTable}Id, ";
                        $query .= "{$this->underlyingTable}Id, ";
                        $query .= $column;
                        $query .= ") VALUES (";
                        $query .= "$ancillaryDataTypeId, ";
                        $query .= "$rowId, ";
                        $query .= $valString;
                        $query .= ");";
                        
                        $result = $this->db->query($query);
                        if (!$result) {
                            $error = "DB error INSERTing in AncillaryData::$fnName for {$this->underlyingTable}";
                            $this->localLogger->errorDb('1572549785', $error, $this->db);
                        }
                    }
                } // END if (!$alreadyThere)
            }
        }
     
        return array($error==='', $error);
    } // END public function putData
    
    // Delete a value from the table (or delete a set of values). This in NOT a "hard delete", it simply marks the value as deleted
    // INPUT $datumId - Primary key in $this->$ancillaryDataTable, the actual data table. 
    public function deleteData($datumId) {
        $error = '';
        
        $query = "UPDATE {$this->ancillaryDataTable} ";
        $query .= "SET deleted=CURRENT_TIMESTAMP, deletedPersonId=" . $this->user->getUserId() . " ";  
        $query .= "WHERE {$this->ancillaryDataTable}Id = $datumId ";
        $query .= "AND deleted IS NULL;";
        $result = $this->db->query($query);
        if (!$result) {
            $error = "DB error UPDATEing in AncillaryData::deleteData for {$this->underlyingTable}";
            $this->localLogger->errorDb('1573251446', $error, $this->db);
        }
        return array($error==='', $error);
    } // END public function deleteData
    
} // END class AncillaryData

?>
