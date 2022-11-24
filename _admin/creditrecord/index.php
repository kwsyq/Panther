<?php 
/*  _admin/creditrecord/index.php

    NO INPUTS.

    EXECUTIVE SUMMARY: Displays on the 100 latest rows in DB table CreditRecord, and allows edits to them.
    User can do the following for any of these:
        * Change creditRecordType
        * Change "Reference#" and "Amount"
        * Change Credit Date
        * Change "Received From"
        * Change Notes.
        * View the image/pdf etc.
        * Add an invoice id

   The dropzone uploads a new creditRecord file (i.e. a check), and creats a new creditRecord.
*/

include '../../inc/config.php';
include '../../inc/perms.php';

ini_set('display_errors',1);
error_reporting(-1);
/*
BEGIN MARTIN COMMENT

create table creditRecord(
        creditRecordId      int unsigned not null primary key auto_increment,
        creditRecordTypeId  tinyint unsigned,
        fileName            varchar(128),
        arrivalTime         datetime,
        referenceNumber     varchar(64),        
        amount              decimal(10,2),        
        creditDate          datetime, 
        receivedFrom        varchar(255),
        personId            int unsigned,
        inserted            timestamp not null default now()); 

| creditRecord | CREATE TABLE `creditRecord` (
  `creditRecordId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payorTypeId` int(10) unsigned NOT NULL,
  `payorId` int(10) unsigned NOT NULL DEFAULT '0',
  `paymentArrived` datetime DEFAULT NULL,
  `payMethodId` int(10) unsigned DEFAULT NULL,
  `reference` varchar(32) DEFAULT NULL,
  `image` varchar(64) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `personId` int(10) unsigned NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`creditRecordId`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=latin1 |

create table creditRecordInvoice(
    creditRecordInvoice   int unsigned not null primary key auto_increment,
    creditRecordId        int unsigned,
    invoiceId             int unsigned,
    personId              int unsigned,
    inserted              timestamp not null default now()); 

END MARTIN COMMENT
*/

$db = DB::getInstance();

/* 
OLD CODE removed 2019-02-06 JM
$sep = DIRECTORY_SEPARATOR;
$fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '../ssseng_documents/uploaded_checks' . $sep;
*/
// BEGIN NEW CODE 2019-02-06 JM
$fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/';
// END NEW CODE 2019-02-06 JM	

// Get the 100 latest rows in DB table CreditRecord, in reverse chronological order.
// Relies on creditRecordId being in monotonically increasing order over time.
$query = " select cr.* ";
$query .= " from " . DB__NEW_DATABASE . ".creditRecord cr  ";
$query .= " order by cr.creditRecordId desc limit 100 ";

$rows = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
} // >>>00002 ignores failure on DB query!

include "../../includes/header_admin.php";
?>
<div style="margin-left:10px; font-size:85%">

<!-- BEGIN ADDING fancybox capability JM 2020-02-17. This is somewhat improvised, because Martin never had this in 
     admin, and we may want to abstract this into a header file some place -->
<script type="text/javascript" src="/includes/fancybox/jquery.fancybox.js?v=2.1.5"></script>
<link rel="stylesheet" type="text/css" href="/includes/fancybox/jquery.fancybox.css?v=2.1.5" media="screen" />
<script type="text/javascript">
    var woturl = '';
    
    // BEGIN 2020-04-21 JM for http://bt.dev2.ssseng.com/view.php?id=130, this replaces the old fancyboxIframe 
    $(function() {
        $(".fancyboxIframeWide").fancybox({
            maxWidth    : 1280,
            maxHeight   : 1000,
            fitToView   : false,
            width       : '98%',
            height      : '95%',
            autoSize    : false,
            closeClick  : false,
            openEffect  : 'none',
            closeEffect : 'none',
        iframe: {
            scrolling : 'auto',
            preload   : true
        },
        "afterClose": function(){
            if (woturl.length){
                document.location.href = woturl;
            } else {
                parent.location.reload(true);
            }
        }
        });
    });
    // END REPLACEMENT 2020-04-21 JM for http://bt.dev2.ssseng.com/view.php?id=130
</script>    
<!-- END ADDING fancybox capability JM 2020-02-17. This is somewhat improvised, because Martin never had this in 
     admin, and we may want to abstract this into a header file some place -->

<script src="/js/dropzone.js?v=1524508426"></script>

<link rel="stylesheet" href="dropzone.css?v=1524508426" />
</head>
<body>

<?php /* drop-area moved here 2019-09-13 by JM, per http://bt.dev2.ssseng.com/view.php?id=19 */ ?>
<div class="drop-area">
    <form id="uploadcredit" class="dropzone" action="upload.php">
    <h2 class="heading"></h2>
    <div id="dropzone">
    </div>
    </form>
</div>
<hr />

<?php

echo '<center>' . "\n";
    // User instructions, in the form of a table
    echo '<table border="0" cellpadding="4" cellspacing="0" width="600">' . "\n";
        echo '<tr><td>Changing the Record Type, Reference#, Amount, Credit Date, or Deposit Date updates immediately in database.<td></tr>';
        echo '<tr><td>Ditto for "Received From" and "Notes", but you must click outside the edit area to save to the database.<td></tr>';
        echo '<tr><td>Click the file name to view the image/pdf etc.</td></tr>';
        echo '<tr><td>Credit records with non-zero balance offer the option to apply a payment to an invoice. An updated list is then fetched from the database.</td></tr>';
    echo '</table>';
    
    echo '<table border="1" cellpadding="4" cellspacing="0" width="400">' . "\n";
        echo '<tr>' . "\n";
            echo '<th>Record Type</th>' . "\n";
            echo '<th>Reference#</th>' . "\n";
            echo '<th>Amount</th>' . "\n";
            echo '<th>Credit Date</th>' . "\n";
            echo '<th>Received From</th>' . "\n";
            echo '<th>File Name</th>' . "\n";
            echo '<th>Image</th>' . "\n";
            /* BEGIN REPLACED 2020-02-03 JM
            echo '<th>Inv. ID\'s</th>' . "\n";
            // END REPLACED 2020-02-03 JM
            */
            // BEGIN REPLACEMENT 2020-02-03 JM
            echo '<th>Inv. ID/amt.</th>' . "\n";
            // END REPLACEMENT 2020-02-03 JM
            echo '<th>Notes</th>' . "\n";
        echo '</tr>' . "\n";
    
        // All creditRecordTypes; this is independent of what is to be displayed on this particular occasion.
        $types = CreditRecord::creditRecordTypes();
        
        foreach ($rows as $row) {        
            $creditDate = date_parse($row['creditDate']);
            $creditDateField = ''; // display: m/d/y form, no leading zeroes. 
            
            if (is_array($creditDate)) {
                if (isset($creditDate['year']) && isset($creditDate['day']) && isset($creditDate['month'])) {    
                    $creditDateField = intval($creditDate['month']) . '/' . intval($creditDate['day']) . '/' . intval($creditDate['year']);
                    if ($creditDateField == '0/0/0') {
                        $creditDateField = '';
                    }
                }
            }
            
            // Martin comment: 
            // creditRecordTypeId | arrivalTime | referenceNumber | amount | creditDate | receivedFrom | personId | inserted  | fileName |
            
            echo '<tr>' . "\n";
                // "Record Type"
                // Form within TD. 
                //   Hidden input containing creditRecordId
                //   HTML SELECT offering all possible creditRecordTypes. Displays name, value is creditRecordTypeId; first OPTION is
                //    '-- choose type --', with value 0. Initially displays current value.
                // Immediately upon user changing the SELECT, will call local function typeForm to POST to _admin/ajax/cred_type.php; 
                //  that function serializes the form variables for the POST:
                //  * 'id' - creditRecordId
                //  * 'value' - creditRecordTypeId
                echo '<td><form name="type_' . intval($row['creditRecordId']) . '" id="type_' . intval($row['creditRecordId']) . '">' .
                     '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '" />' . "\n";
                    echo '<select name="value" onChange="typeForm(' . intval($row['creditRecordId']) . ')">'.
                         '<option value="">-- choose type --</option>' . "\n";
                    foreach ($types as $tkey => $type) {
                        $selected = ($tkey == $row['creditRecordTypeId']) ? ' selected ' : '';
                        echo '<option value="' . $tkey . '" ' . $selected . '>' . $type['name']  . '</option>' . "\n";
                    }
                echo '</select></form></td>' . "\n";
                
                // "Reference#"
                // Code below allows a double-click to edit this.
                // BEGIN OLD CODE REWORKED 2020-02-12 JM to make this editable in place
                /*
                $ref = $row['referenceNumber'];
                $ref = trim($ref);
                if (!strlen($ref)) {
                    $ref = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                }
                // 2019-10-15 JM: introduce HTML data attributes, get away from ref_editable and amt_editable having
                //  same HTML ID, which is illegal HTML. See http://bt.dev2.ssseng.com/view.php?id=35.
                // OLD CODE removed 2019-10-15 JM: 
                //echo '<td class="ref_editable" id="' . intval($row['creditRecordId']) . '">' . $ref . '</td>';
                // BEGIN REPLACEMENT
                echo '<td class="ref_editable" data-credit-record-id="' . intval($row['creditRecordId']) . '">' . $ref . '</td>' . "\n";
                // END REPLACEMENT
                // END OLD CODE REWORKED 2020-02-12 JM
                */
                // BEGIN NEW CODE 2020-02-10 JM
                    $ref = $row['referenceNumber'];
                    $ref = trim($ref);
                    echo '<td><input class="ref_editable" data-credit-record-id="' . intval($row['creditRecordId']) . '" value="' . $ref . '" maxlength="64"></td>';
                // END NEW CODE 2020-02-12 JM
                
        
                // "Amount"
                // BEGIN OLD CODE REWORKED 2020-02-12 to make this editable in place
                /*
                // Code below allows a double-click to edit this.                
                $amt = $row['amount'];
                $amt = trim($amt);
                if (!strlen($amt)) {
                    $amt = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                }            
                // 2019-10-15 JM: introduce HTML data attributes, as discussed above
                // OLD CODE removed 2019-10-15 JM: 
                //echo '<td class="amt_editable" id="' . intval($row['creditRecordId']) . '">' . $amt . '</td>';
                // BEGIN REPLACEMENT (further repaired 2020-01-03 JM)
                echo '<td class="amt_editable" data-credit-record-id="' . intval($row['creditRecordId']) . '">' . $amt . '</td>' . "\n";
                // END REPLACEMENT
                // END OLD CODE REWORKED 2020-02-12
                */
                // BEGIN NEW CODE 2020-02-12
                $amt = $row['amount'];
                $amt = trim($amt);
                echo '<td><input class="amt_editable" data-credit-record-id="' . intval($row['creditRecordId']) . '" value="' . $amt . '" size="8" maxlength="20"></td>';
                // END NEW CODE 2020-02-12
        
                // "Credit Date"
                // Form within TD. 
                //   Hidden input containing creditRecordId
                //   Datepicker input initially displays current value from DB for this row.
                // Immediately upon user setting the date (even if it is the same as the old date, under
                //  the code as it stands 2019-05), a handler will POST to _admin/ajax/cred_date.php; 
                //  that function serializes the form variables for the POST:
                //  * 'id' - creditRecordId
                //  * 'value' - creditRecordTypeId
                echo '<td>' . "\n";
                    echo '<form name="form_date_' . intval($row['creditRecordId']) . '" id="form_date_' . intval($row['creditRecordId']) . '">' . "\n".
                         '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '" />' . "\n";
                        echo '<input type="text" name="value" class="datepicker" id="date_' . intval($row['creditRecordId']) . '" value="' . htmlspecialchars($creditDateField) . '" />' . "\n";
                    echo '</form>' . "\n";
                echo '</td>' . "\n";
        
                // "Received From"
                // open-ended text.
                /* BEGIN REPLACED 2020-02-12 JM
                // User can edit, click the button labeled "set rec from" to call local function receivedFromForm
                //  which will serialize the form content and call _admin/ajax/cred_recfrom.php
                echo '<td><form name="recfrom_' . intval($row['creditRecordId']) . '" id="recfrom_' . intval($row['creditRecordId']) . '">' . "\n".
                     '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '" />' . "\n";
                    echo '<textarea class="received_from" name="value">' . htmlspecialchars($row['receivedFrom']) . '</textarea>' . "\n";
                echo '</form><input type="button" onClick="receivedFromForm(' . intval($row['creditRecordId']) . ')" value="set rec from" /></td>' . "\n";
                // END REPLACED 2020-02-10 JM
                */
                // BEGIN REPLACEMENT 2020-02-10 JM
                echo '<td>';
                    echo '<form name="recfrom_' . intval($row['creditRecordId']) . '" id="recfrom_' . intval($row['creditRecordId']) . '">' .
                        '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
                    echo '<textarea name="value" onChange="receivedFromForm(' . intval($row['creditRecordId']) . ')">' . htmlspecialchars($row['receivedFrom']) . '</textarea>';
                    echo '</form>';
                echo '</td>';
                // END REPLACEMENT 2020-02-10 JM
        
                // "File Name"
                // Click to download image
                echo '<td><a target="imageframe" href="getuploadfile.php?f=' . rawurlencode($row['fileName']) . '">' . $row['fileName'] . '</a></td>' . "\n";
                
                // "Image"
                // Click to download image
                $ok = array('png','jpg','jpeg','gif');                
                $fn = $row['fileName'];
                $parts = explode(".", $fn);
                if (count($parts) > 1) {
                    $ext = strtolower(end($parts));
                    if (in_array($ext, $ok)) {
                        echo '<td><img src="getuploadfile.php?f=' . rawurlencode($row['fileName']) . '" width="163" height="122"></td>' . "\n";
                    } else {
                        echo '<td>&nbsp;</td>' . "\n";	        
                    }
                } else {
                    echo '<td>&nbsp;</td>' . "\n";
                }
                
                // "Inv. ID" (nested table + form to add new invoice)
                echo '<td><div id="cred_invoices_' . intval($row['creditRecordId']) . '"></div>' . "\n";
                    $invoices = array();        
                    $db = DB::getInstance();
                    
                    /* BEGIN REPLACED 2020-02-03 JM
                    $query = " select * from " . DB__NEW_DATABASE . ".creditRecordInvoice ";
                    // END REPLACED 2020-02-03 JM
                    */
                    // BEGIN REPLACEMENT 2020-02-03 JM
                    //  (For version 2020-02, invoicePayment completely supersedes creditRecordInvoice instead of having
                    //  duplicated information.)                    
                    $query = " select invoiceId, amount from " . DB__NEW_DATABASE . ".invoicePayment ";
                    // END REPLACEMENT 2020-02-03 JM
                    $query .= " where creditRecordId = " . intval($row['creditRecordId']);
                    
                    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                        if ($result->num_rows > 0) {
                            while ($r = $result->fetch_assoc()) {
                                /* BEGIN REPLACED 2020-02-03 JM
                                $invoices[] = $r['invoiceId'];
                                // END REPLACED 2020-02-03 JM
                                */
                                // BEGIN REPLACEMENT 2020-02-03 JM
                                $invoices[] = $r;
                                // END REPLACEMENT 2020-02-03 JM
                            }
                        }
                    } // >>>00002 ignores failure on DB query!
                    echo '<table>' . "\n";
                        foreach ($invoices as $invoice) {
                            echo '<tr>';
                                echo '<td>';
                                /* BEGIN REPLACED 2020-02-03 JM
                                echo $invoice; // invoice ID; passive display
                                // END REPLACED 2020-02-03 JM
                                */
                                // BEGIN REPLACEMENT 2020-02-03 JM
                                echo $invoice['invoiceId'] . ': $' . $invoice['amount']; // invoice ID and amount applied; passive display
                                // END REPLACEMENT 2020-02-03 JM
                                echo '</td>';
                            echo '</tr>' . "\n";
                        }
                    echo '</table>' . "\n";
                    
                    /* BEGIN REMOVED 2020-02-06 JM
                    // Form to add an invoice
                    // Text input to input an ID from DB table Invoice. Pretty unfriendly UI, that, especially because 
                    //  the AJAX we call doesn't seem to be doing any decent validation.
                    // Click "add inv" to submit to local function invoiceForm, which posts to _admin/ajax/cred_invoice.php, then
                    //  reloads list of invoices for this creditRecord.
                    echo '<form name="invoice_' . intval($row['creditRecordId']) . '" id="invoice_' . intval($row['creditRecordId']) . '">' . "\n";
                        echo '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '" />' . "\n";
                        echo '<input type="text" name="value" size="10" maxlength="10" />' . "\n";
                    echo '</form><input type="button" onClick="invoiceForm(' . intval($row['creditRecordId']) . ')" value="add inv" />' . "\n";
                    // END REMOVED 2020-02-06 JM
                    */
                    // BEGIN ADDED 2020-02-06 JM
                    $cr = new CreditRecord($row['creditRecordId']);
                    $label = $cr->getBalance() ? 'View/Make pmt.' : 'View';
                    echo '<a data-fancybox-type="iframe" class="fancyboxIframeWide" ' . 
                         'href="/fb/creditrecord.php?creditRecordId=' . intval($row['creditRecordId']) . '"><button>' . $label . '</button></a>';
                    // END ADDED 2020-02-06 JM
                echo '</td>' . "\n";
            
                // "Notes"
                // open-ended text.
                /* BEGIN REPLACED 2020-02-12 JM
                // User can edit, click the button labeled "set notes" to call local function notesorm
                //  which will serialize the form content and call _admin/ajax/cred_notes.php
                echo '<td><form name="notes_' . intval($row['creditRecordId']) . '" id="notes_' . intval($row['creditRecordId']) . '">' . "\n";
                    echo '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">' . "\n";
                    echo '<textarea name="value">' . htmlspecialchars($row['notes']) . '</textarea>' . "\n";
                echo '</form><input type="button" onClick="notesForm(' . intval($row['creditRecordId']) . ')" value="set notes" /></td>' . "\n";
                // END REPLACED 2020-02-12 JM
                */
                // BEGIN REPLACEMENT 2020-02-12 JM
                echo '<td>';
                    echo '<form name="notes_' . intval($row['creditRecordId']) . '" id="notes_' . intval($row['creditRecordId']) . '">' .
                        '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
                    echo '<textarea name="value" onChange="notesForm(' . intval($row['creditRecordId']) . ')">' . htmlspecialchars($row['notes']) . '</textarea>';
                    echo '</form>';
                echo '</td>';
                // END REPLACEMENT 2020-02-12 JM
            
            echo '</tr>' . "\n";
        }
    
    echo '</table>' . "\n";
echo '</center>' . "\n";

?>
<script type="text/javascript">

$(function() {
    // NOTE that the following relies on HTML class datepicker being used here for only one purpose: the credit date.
    // Also relies on the FORM having an ID of the form 'form_FOO' where FOO is the ID of the datepicker input.
    // >>>00006 surely there are better ways to stitch this together.
        
    var dateForm = function(dpid) {
        $.post('../ajax/cred_date.php', $('#form_' + dpid).serialize())
    }        

    // >>>00006 JM thinks onChange would make more sense than onSelect
    $(".datepicker").datepicker({
        onSelect: function() {
            dateForm(this.id);  // e.g. pass 'date_NN', where NN is creditRecordId
        }
    });
});

/* BEGIN REMOVED 2020-02-06 JM
// Reload all invoices for a particular creditRecord. Pops an alert on failure.
var getInvoices = function(creditRecordId) {
    var cell = document.getElementById("cred_invoices_" + escape(creditRecordId));
    // Show ajax_loader.gif while this runs
    cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
    
    var  formData = "id=" + escape(creditRecordId);
    $.ajax({
        url: '../ajax/cred_getinvoices.php',
        data: formData,
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { // [T000016] 
                    if (data['rows']) {
                        var html = '';
                        html += '<table>';
                            for (var i = 0; i < data['rows'].length; i++) {
                                html += '<tr>';
                                    html += '<td>';
                                        html += data['rows'][i];
                                    html += '</td>';
                                html += '</tr>';
                            }
                        html += '</table>';
                        cell.innerHTML = html;
                    }                    
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
// END REMOVED 2020-02-06 JM
*/

/* BEGIN REMOVED 2020-02-06 JM
// Add an invoice to a creditRecord
// INPUT creditRecordId is used to form an ID that indicates what form to submit.
var invoiceForm = function(creditRecordId) {
    //$.post('../ajax/cred_invoice.php', $('#invoice_' + creditRecordId).serialize()) // Commented out by Martin before 2019

    $.post( "../ajax/cred_invoice.php", $('#invoice_' + creditRecordId).serialize())
     .done(function( data ) {
         getInvoices(creditRecordId);
     });
}
// END REMOVED 2020-02-06 JM
*/

// Edit notes for a creditRecord
// INPUT creditRecordId is used to form an ID that indicates what form to submit.
var notesForm = function(creditRecordId) {
    $.post('../ajax/cred_notes.php', $('#notes_' + creditRecordId).serialize())
}

// Change receivedFrom.
// INPUT creditRecordId is used to form an ID that indicates what form to submit.
var receivedFromForm = function(creditRecordId) {
    $.post('../ajax/cred_recfrom.php', $('#recfrom_' + creditRecordId).serialize())
}

// Change creditRecordType.
// INPUT creditRecordId is used to form an ID that indicates what form to submit.
var typeForm = function(creditRecordId) {
    $.post('../ajax/cred_type.php', $('#type_' + creditRecordId).serialize())
}

// Scroll to top on document.ready event
$(document).ready(function() {
    $(this).scrollTop(0);
});

// Activate the dropzone
// >>>00001 JM: I don't fully understand what is going on here; if you do, please document.
$().ready(function() {
    var extraFormData = '';
    var creditDropZone;
    var which = '';
    /*
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    $.fn.myPlugin = function(event){
        extraFormData = event;
        if (which == 'credit'){
            creditDropZone.processQueue();
        }
    }
    // END COMMENTED OUT BY MARTIN BEFORE 2019
    */
    var fileid = 500;
    
    //getFilesForJob(); // COMMENTED OUT BY MARTIN BEFORE 2019

    window.Dropzone.options.uploadcredit = {            
        uploadMultiple: false,
        maxFiles: 1,
        autoProcessQueue: true,
        maxFilesize: 2, // MB
        clickable: false,
        addRemoveLinks : true,
        acceptedFiles : "application/pdf,.pdf,.png,.jpg,.jpeg",
        init: function() {
            creditDropZone = this;
            this.on("addedfile", function(file) {            
                which = 'credit'; // >>>00014 whats going on here? What's the mechanism?
            });
            this.on("error", function(file, errorMessage) {
                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                //alert("error");
                //if (errorMessage == 'That File Already Exists'){
                // END COMMENTED OUT BY MARTIN BEFORE 2019
                    setTimeout(this.removeFile.bind(this, file), 3000); // after 3 seconds, >>>00014 do what exactly?  
                //} // COMMENTED OUT BY MARTIN BEFORE 2019

            }.bind(this) // >>>00006 JM: here and elsewhere, 'bind' is really archaic jQuery,
                         // superseded by the .on() -- used earlier in this same statement -- in jQuery 1.7...
                         // I *think* the code here removes what is previously bound to 'this', but I could have
                         // that wrong
                         // >>>00014 if someone fully understands what's going on here, please document!
            );
            this.on("sending", function(file, xhr, formData) {
                if (extraFormData.length == 0) {
                    setTimeout(this.removeFile.bind(this, file), 3000);
                } else {
                    for (var i = 0; i < extraFormData.length; i++) {
                        formData.append(extraFormData[i].name, extraFormData[i].value); 
                    }
                }
            });

            this.on('complete', function () {
                setTimeout(function() { window.location.reload(false); }, 2000);
            }.bind(this));
            
            this.on("success", function(file) { 
                //getFilesForJob(); // COMMENTED OUT BY MARTIN BEFORE 2019
                setTimeout(this.removeFile.bind(this, file), 1000);
            }.bind(this)
            );                                    
        }
    };
});

/* BEGIN REMOVED 2020-02-12 JM
$(function () {
    // Handler for double-click on a RefNum (reference number)
    $("td.ref_editable").dblclick(function () {
        var $this = $(this);
        var OriginalContent = $this.text();
        
        // Let the user edit the value
        // OLD CODE replaced 2019-12-12 JM: 
        // var inputNewText = prompt("Enter new content for refnum:", OriginalContent);
        // BEGIN REPLACEMENT 2019-12-12 JM
        var inputNewText;
        {
            let amount = $this.closest('tr').find('.amt_editable').text().trim();
            let receivedFrom = $this.closest('tr').find('textarea.received_from').text().trim();
            if (receivedFrom.length == 0) {
                receivedFrom = '(unknown)';
            } else if (receivedFrom.length > 24) {
                receivedFrom = receivedFrom.substr(0, 20) + '...'; 
            }
            inputNewText = prompt("Enter new reference number for " + amount + " from " +
                receivedFrom + ":", OriginalContent);
        }
        // END REPLACEMENT 2019-12-12 JM

        if (inputNewText != null) {
            // Synchronous POST to _admin/ajax/cred_refnum.php
            // No AJAX "working" icon. 
            // Updates cell on success, alerts on failure 
            $.ajax({
                url: '../ajax/cred_refnum.php',
                // 2019-10-15 JM: introduce HTML data attributes, as discussed above
                // OLD CODE removed 2019-10-15 JM: 
                //data: { id: $(this).attr('id'), value: inputNewText },
                // BEGIN REPLACEMENT
                data: { id: $(this).attr('data-credit-record-id'), value: inputNewText },
                // END REPLACEMENT                
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            $(this).text(inputNewText)
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
    });  // END $("td.ref_editable").dblclick
}); // END anonymous function that will be run on document ready
// END REMOVED 2020-02-12 JM
*/

// BEGIN NEW CODE 2020-02-12 JM
$(function () {
    $("input.ref_editable").change(function () {
        let $this = $(this);
        $.ajax({
            url: '../ajax/cred_refnum.php',
            data: { id: $this.attr('data-credit-record-id'), value: $this.val() },
            async: true,
            type: 'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        // Do nothing, it takes care of itself.
                    } else {
                        alert('error not success updating refnum'); // >>>00037 should instrument /ajax/cred_refnum.php to return a usable error message
                    }
                } else {
                    alert('error no status updating refnum');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/cred_refnum.php');
            }
        });
    });
});
// END NEW CODE 2020-02-12 JM

/* BEGIN REMOVED 2020-02-12 JM
$(function () {
    // Handler for double-click on amount
    $("td.amt_editable").dblclick(function () {
        var $this = $(this);
        var OriginalContent = $this.text();
        // Let the user edit the value
        // Let the user edit the value
        // OLD CODE replaced 2019-12-12 JM: 
        // var inputNewText = prompt("Enter new content for amount:", OriginalContent);
        // BEGIN REPLACEMENT 2019-12-12 JM
        var inputNewText;
        {
            let refNum = $this.closest('tr').find('.ref_editable').text().trim();
            if (refNum.length == 0) {
                refNum = '(unknown)';
            } else if (refNum.length > 24) {
                refNum = refNum.substr(0, 20) + '...'; 
            }
            let receivedFrom = $this.closest('tr').find('textarea.received_from').text().trim();
            if (receivedFrom.length == 0) {
                receivedFrom = '(unknown)';
            } else if (receivedFrom.length > 24) {
                receivedFrom = receivedFrom.substr(0, 20) + '...'; 
            }
            let date = $this.closest('tr').find('.datepicker').val();
            if (date.length == 0) {
                date = '(unknown)';
            }
            inputNewText = prompt("Enter new amount for " + refNum + " from " +
                receivedFrom + " on " + date + ":", OriginalContent);
        }
        
        // END REPLACEMENT 2019-12-12 JM


        if (inputNewText!=null) {
            // Synchronous POST to _admin/ajax/cred_amount.php
            // No AJAX "working" icon. 
            // Updates cell on success, alerts on failure 
            $.ajax({
                url: '../ajax/cred_amount.php',
                // 2019-10-15 JM: introduce HTML data attributes, as discussed above
                // OLD CODE removed 2019-10-15 JM: 
                //data: { id: $(this).attr('id'), value: inputNewText },
                // BEGIN REPLACEMENT
                data: { id: $(this).attr('data-credit-record-id'), value: inputNewText },
                // END REPLACEMENT                
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            $(this).text(inputNewText)
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
    }); // END $("td.amt_editable")
});  // END anonymous function that will be run on document ready
// END REMOVED 2020-02-12 JM
*/2
// BEGIN NEW CODE 2020-02-1 JM
$(function () {
    $("input.amt_editable").change(function () {
        let $this = $(this);
        $.ajax({
            url: '../ajax/cred_amount.php',
            data: { id: $this.attr('data-credit-record-id'), value: $this.val() },
            async: true,
            type: 'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        // Do nothing, it takes care of itself.
                    } else {
                        alert('error not success updating amount'); // >>>00037 should instrument /ajax/cred_amount.php to return a usable error message
                    }
                } else {
                    alert('error no status updating amount');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/cred_amount.php');
            }
        });
    });
});
// END NEW CODE 2020-02-12 JM


</script>
<?php /* drop-area was here until 2019-09-13, moved per http://bt.dev2.ssseng.com/view.php?id=19 (JM) */ ?>
</div>
<?php
include "../../includes/footer_admin.php";
?>