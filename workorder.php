<?php
/*
    workorder.php

    EXECUTIVE SUMMARY: Top-level page to view or edit a workOrder. Display and actions will vary with user's permissions.

    There are RewriteRules in the .htaccess to allow this to be invoked as just:
        * "workorder/foo" rather than "workorder.php?workOrderId=foo"
        * "workorder/foo/bar" rather than "workorder.php?workOrderId=foo&workOrderTaskId=bar".

    PRIMARY INPUT: $_REQUEST['workOrderId']

    SECONDARY INPUTS:
      * $_REQUEST['workOrderTaskId'] (optional, default 0)

    OPTIONAL INPUT $_REQUEST['act']. Possible values:
        * 'updatecontractNotes'
        * 'updatetempNote' added 2020-09-22
        * 'delworkorderfile' added 2020-09-24
      * 'updatecontractNotes' and 'updatetempNote' both use the update method of workOrder object (/inc/classes/WorkOrder.php).
         That function (see that file for details) updates a row in the workorder table, using the passed data
      * 'delworkorderfile' requires $_REQUEST['workOrderFileId'].

*/

require_once './inc/config.php';

use Ahc\Jwt\JWT;
require_once __DIR__.'/vendor/autoload.php';
$jwt = new JWT(base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU"), 'HS256', 3600, 10);

if(isset($_REQUEST['workOrderId']) && $_REQUEST['workOrderId']<=14174)
{
    $token = $jwt->encode([
        'user' => $user->getUsername()
    ]);
    header("Location: https://old.ssseng.com/redirectToken.php?url=".urlencode($_SERVER[REQUEST_URI])."&page=WO&token=".$token);

    die();

}

require_once './inc/perms.php';
require_once './includes/workordertimesummary.php'; // contains the single function insertWorkOrderTimeSummary

// ADDED by George 2020-12--2, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'workOrderId');
$v->rule('integer', 'workOrderId');
$v->rule('min', 'workOrderId', 1);

if( !$v->validate() ) {
    $errorId = '637425051645278495';
    $logger->error2($errorId, "workOrderId : " . $_REQUEST['workOrderId'] ."  is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = "Invalid workOrderId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$workOrderId = intval($_REQUEST['workOrderId']);

if (!WorkOrder::validate($workOrderId)) {
    $errorId = '637425052578750569';
    $logger->error2( $errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB workOrder row in workOrder table");
    $_SESSION["error_message"] = "Invalid workOrderId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$workOrder = new WorkOrder($workOrderId, $user);
$job = new Job($workOrder->getJobId());

$invoiceStatusDataArray = Invoice::getInvoiceStatusDataArray($error_is_db); // variable pass by reference in method.
if($error_is_db) {
    $errorId = '637425081810020354';
    $error .= ' We could not display the Invoice Status Data for this Workorder. Database Error.';
    $logger->errorDB($errorId, "getInvoiceStatusDataArray method failled.", $db);
}

$classes = Array(); // Let's at least make sure the array is there, even if it's empty.

if ($act == 'updatecontractNotes') {

    $contractNotes = isset($_REQUEST['contractNotes']) ? trim($_REQUEST['contractNotes']) : '';
    $array = Array('contractNotes' => $contractNotes);

    $success = $workOrder->update($array);
    if (!$success) {
        $errorId = "637425180525991364";
        $error = "We could not update Contract Notes. Database Error."; // message for User
        $logger->errorDB($errorId, "WorkOrder::update() method failed => Hard DB error ", $db);
    } else {
        // reload this page without the action inputs
        header("Location: " . $workOrder->buildLink());
        die();
    }
    unset($array, $contractNotes);
}

// BEGIN ADDED 2020-09-22 JM
else if ($act == 'updatetempNote') {
    // Blank value here is meaningful
    $tempNote = isset($_REQUEST['tempNote']) ? trim($_REQUEST['tempNote']) : '';
    $array = Array('tempNote' => $tempNote);

    $success = $workOrder->update($array);

    if (!$success) {
        $errorId = "637425193560488506";
        $error = "We could not update the Notes for the Invoice. Database Error."; // message for User
        $logger->errorDB($errorId, "WorkOrder::update() method failed => Hard DB error ", $db);
    } else {
        // reload this page without the action inputs
        header("Location: " . $workOrder->buildLink());
        die();
    }
    unset($array, $tempNote);
}
// END ADDED 2020-09-22 JM

// BEGIN ADDED 2020-09-24 JM
else if ($act == 'delworkorderfile') {

    $v->rule('required', 'workOrderFileId');
    $v->rule('integer', 'workOrderFileId');
    $v->rule('min', 'workOrderFileId', 1);

    if (!$v->validate()) {
        $errorId = '637426027283177903';
        $logger->error2($errorId, "Error in parameters ".json_encode($v->errors()));
        $error = "Error in parameters, invalid workOrderFileId.";
    } else {
        $workOrderFileId = intval($_REQUEST['workOrderFileId']);

        $query = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderFile ";
        $query .= "WHERE workOrderFileId = " . intval($workOrderFileId) . ";";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1600984328', 'Hard DB error', $db);
            $error = "We could not Delete this WorkOrder File. Database Error."; // message for User
        } else {
            // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
            header("Location: " . $workOrder->buildLink());
            die();
        }
        unset($workOrderFileId);
    }
}
// END ADDED 2020-09-24 JM

$crumbs = new Crumbs($workOrder, $user);

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$workOrderTaskIdExists = false; // will be set true if we find this workOrderTaskId in the workOrder.


$contract = $workOrder->getContractWo($error_is_db);
if($error_is_db) {
    $errorId = '637805374268215053';
    $error = "We could not get the Contract for this WO. Database Error. Error Id: " . $errorId; // message for User
    $logger->errorDB($errorId, "getContractWo() method failed.", $db);
}

$contractStatus = 0;
$blockAdd = false; // if true, Block add/delete tasks/structures of tasks.

if(!$error) {
    if($contract) {
        $contractStatus = intval($contract->getCommitted()); // Contract status
    }
}

// no update for: 3, 4, 5, 6.
/*$arrNoUpdate = [3, 4, 5, 6];
if($contractStatus && in_array($contractStatus, $arrNoUpdate)) {
    $blockAdd = true;
}
*/



$invoices = $workOrder->getInvoices($error_is_db);
$errorInvoices = '';
if($error_is_db) { //true on query failed.
    $errorId = '637831999800013136';
    $errorInvoices = "We could not check for Invoices. Database Error."; // message for User
    $logger->errorDB($errorId, "WorkOrder::getInvoices() method failed", $db);
}
if ($errorInvoices) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorInvoices</div>";
}
unset($errorInvoices);

// no update if invoices exists.

if($invoices) {
    $blockAdd = true;
}

$blockAdd=false;
include_once BASEDIR . '/includes/header.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
// BEGIN ADDED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100
?>
<style>
input.tally.bad-value {border-color:red;}
input.tally.confirmed {background-color:LightGreen;}

body, #container,table { background-color: #fff; }
#stampId {
    display: inline!important;
    width: 20%!important;
}
.newstatus {
    width: 60%!important;
}
</style>
<?php
// END ADDED 2020-09-23 JM

// Add title
echo "<script>\ndocument.title ='" .
    str_replace(Array("'", "&nbsp;"), Array("\'", ' '), $job->getNumber() . " - ". $workOrder->getDescription()).
    "';\n</script>\n";

// $tasks = $workOrder->getTasks(); // Removed 2020-11-11, never used

?>
<script>


</script>
<script src='https://maps.googleapis.com/maps/api/js?v=3.exp&key=<?php echo CUSTOMER_GOOGLE_LOADSCRIPT_KEY; ?>'></script>

<script type="text/javascript">

/* POSTs to /ajax/setinvoicestatus.php to set a new status for an invoice. */
var changeStatusNew = function(formid) {
    let invoiceStatusId = $("#" + formid + " input[name=invoiceStatusId]").val();
    let invoiceId = $("#" + formid + " input[name=invoiceId]").val();
    let customerPersonIds = $('#' + formid + ' input[name="customerPersonIds[]"]');
    let note = $("#" + formid + " textarea[name=note]").val();

    $.ajax({
        url: '/ajax/setinvoicestatus.php',
        data:{
            invoiceStatusId:invoiceStatusId,
            invoiceId:invoiceId ,
            customerPersonIds: customerPersonIds.serialize(),
            note:note
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            // Update oldstatus so we don't restore the prior value on dialog close.
            var $selector = $('.newstatus[name="invoiceId_' + invoiceId + '"]');
            $selector.data('oldstatus', invoiceStatusId);
            $(".hide-answer").dialog("close");
            setTimeout(function(){ location.reload(); }, 500);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
}; // END function changeStatusNew



$(document).ready(function() {

    <?php /* Set up the hide-answer dialog, only shown when we need it*/ ?>
    //
    $(".hide-answer").dialog({
        autoOpen: false,
        open: function() {
            // is this an actual change of status, or just a refinement such as allowing user to change customerPersonIds?
            var $this = $(this);
            var invoiceId = $this.find("input[name='invoiceId']").val();
            var invoiceStatusId = $this.find("input[name='invoiceStatusId']").val();
            var $statusSelector = $('.newstatus[name="invoiceId_' + invoiceId + '"]');
            var oldStatusId = $statusSelector.data('oldstatus');
            if (invoiceStatusId == oldStatusId) {
                // just refining status, not setting new status; we want to work out which checkboxes (if any) to check
                // Which customerPersonIds are effectively checked for the current status?

                // >>>00032 Also, it would be nice if it could temporarily create checkboxes for anyone who is not
                // normally listed in the dialog, but is listed here. However, that's a lot of work, and
                // I haven't addressed it at this time.
                var $extras = $statusSelector.closest('tr').find('td.extra');
                var $customerPersonIds = $extras.find("span.customerPerson");
                var customerPersonIds = [];
                $customerPersonIds.each(function() {
                    customerPersonIds.push($(this).data('customerpersonid'));
                });
                $this.find('input[type="checkbox"]').each(function() {
                    var $this = $(this); // the checkbox input element
                    var customerPersonId = $this.val();
                    var checked = false;
                    for (var i in customerPersonIds) {
                        if (customerPersonIds[i] == customerPersonId) {
                            checked = true;
                            break;
                        }
                    }
                    $this.prop('checked', checked);
                });
            } else {
                // New invoiceStatus.
                // Restore the "normal" checked boxes
                $this.find('input[type="checkbox"]').each(function() {
                    var $this = $(this); // the checkbox input element
                    $this.prop('checked', $this.data('checkedwhenchanging')=='true');
                });
            }
        },
        close: function() {
            // If user bailed out of changing the status, restore old status.
            // If not, then they should already have updated data-oldstatus, so
            //  when we restore "old" status here it will actually be the new status.
            var $this = $(this);
            var invoiceId = $this.find("input[name='invoiceId']").val();
            var invoiceStatusId = $this.find("input[name='invoiceStatusId']").val();
            var $statusSelector = $('.newstatus[name="invoiceId_' + invoiceId + '"]');
            $statusSelector.find('option').prop('selected', false);
            $statusSelector.find('option[value="' + $statusSelector.data('oldstatus') + '"]').prop('selected', true);
        }
    });
});

<?php /* POSTs to /ajax/task_active_toggle.php to set a new taskStatus for a workOrderTask. */ ?>
// BEGIN Martin comment
//maybe just do all the logic serverside and just pass here the workOrderTaskID
//this function also exists in time.php
// END Martin comment
var setTaskStatusId = function(workOrderTaskId, taskStatusId) {

    var formData = "workOrderTaskId=" + escape(workOrderTaskId) + "&taskStatusId=" + escape(taskStatusId);
    var cell = document.getElementById("statuscell_" + workOrderTaskId);
    cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';

    $.ajax({
        url: '/ajax/task_active_toggle.php',
        data:formData,
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { // [T000016]
                    var html = '<td id="statuscell_' + workOrderTaskId + '"><a href="javascript:setTaskStatusId(' + workOrderTaskId + ',' +
                               data['linkTaskStatusId'] + ')"><img src="/cust/<?php echo $customer->getShortName(); ?>' +
                               '/img/icons/icon_active_' + data['taskStatusId'] + '_24x24.png" width="16" height="16" border="0"></a></td>';
                    cell.innerHTML = html;
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
}; // end function setTaskStatusId


var setTaskStatusIdNew = function(workOrderTaskId, taskStatusId) {

        var formData = "workOrderTaskId=" + escape(workOrderTaskId) + "&taskStatusId=" + escape(taskStatusId);
        var cell = document.getElementById("statuscell_" + workOrderTaskId);

        $.ajax({
            url: '/ajax/task_active_toggle.php',
            data:formData,
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        if( data.linkTaskStatusId == 1) {
                            var html = '<a id="statuscell_' + workOrderTaskId + '" class="statusWoTaskColor2 rounded-circle"  href="javascript:setTaskStatusIdNew(' + workOrderTaskId + ',' +
                               data['linkTaskStatusId'] + ')" role="button"></a>';
                               $(cell).removeClass("statusWoTaskColor");

                        } else {

                            var html = '<a id="statuscell_' + workOrderTaskId + '" class="statusWoTaskColor  rounded-circle"  href="javascript:setTaskStatusIdNew(' + workOrderTaskId + ',' +
                               data['linkTaskStatusId'] + ')" role="button"></a>';
                               $(cell).removeClass("statusWoTaskColor2");
                    }
                    cell.innerHTML = html;

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
    }; // end function setTaskStatusIdNew

<?php /* POSTs workOrderId to /ajax/newinvoice.php and reloads page in any scenario where the AJAX returns. */ ?>
var newInvoice = function() {
    $.ajax( {
        type: "POST",
        url: '/ajax/newinvoice.php',
        data: 'workOrderId=' + escape(<?php echo $workOrder->getWorkOrderId();?>),
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') {
                    location.reload();
                } else {
                   alert(data['error']);
                }
            } else {
                alert('error: no status');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }

    });
}; // function newInvoice

var makeInvoiceFromInternalTasks = function() {
    $.ajax( {
        type: "POST",
        url: '/ajax/newinvoicefrominternaltasks.php',
        data: 'workOrderId=' + escape(<?php echo $workOrder->getWorkOrderId();?>),
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') {
                    location.reload();
                } else {
                   alert(data['error']);
                }
            } else {
                alert('error: no status');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }

    });
}; // function newInvoice


<?php /* POSTs contractId to /ajax/makeinvoicefromcontract.php.php and reloads page in any scenario where the AJAX returns. */ ?>
var makeInvoiceFromContract = function(contractId) {
    <?php /* temporarily have the cell for this action show the ajax_loader.gif */ ?>
    var up = document.getElementById('makeinvoice_' + contractId);
    if (up) {
        up.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
    }
    $.ajax({
        type: "POST",
        url: '/ajax/makeinvoicefromcontract.php',
        data:{
            contractId:contractId
        },
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') {
                    location.reload();
                } else {
                    alert(data['error']);
                }
            } else {
                alert('error: no status');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
}; // END function makeInvoiceFromContract

<?php
    // Slightly roundabout way to build a link to the contract page for this workOrder.
    // >>>00006 Really ought to have a function somewhere to do this cleanly.
    $contractLink = $workOrder->buildLink();
    $contractLink = str_replace("/workorder/", "/contract/", $contractLink);
?>

<?php /* POSTs to /ajax/contractmakecurrent.php, which at least in theory
makes this version of the contract the current uncommitted version
and navigates away from this page to $contractLink, which is the contract.php for the relevant contract.
>>>00026 As of 2019-04-17, /ajax/contractmakecurrent.php looks buggy
to me. It could delete a committed contract, if contractId refers to one.

NOTE that at the end when we navigate to $contractLink: that can deal with having input contractId
no longer be the relevant contractId: it is based on the ID of the workOrder, not of the contract,
so if we change what contractId is relevant, that should "take".
*/ ?>
/* George 2021-11-18. Removed this function.*/
var makeCurrent = function(contractId) {
    $(document).ready( function() {
        var up = document.getElementById('current_' + contractId);
        if (up) {
            up.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
        }

        $.ajax( {
            type: "POST",
            url: '/ajax/contractmakecurrent.php',
            data: 'contractId=' + escape(contractId),
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016]
                        up.innerHTML = '<a href="javascript:makeCurrent(' + escape(contractId) + ');"><img src="/cust/<?php echo $customer->getShortName();?>/img/icons/icon_down_48x48.png" width="20" height="20" title="Make this current" alt="Make this current"></a>';
                        location.href = '<?php echo $contractLink; ?>';
                    }
                }
            }
        });
    });
} // END function makeCurrent. END REMOVED

<?php /* Despite a somewhat odd name (>>>00012), $( ".elementopen" ) is the button to add
         a new workOrderTask to the workOrder. It has that name just as part of a tooltip-like
         hover-help mechanism. The idea here is that when that is clicked
         we open a stripped-down dialog just above the button. The dialog initially shows
         the ajax_loader.gif, and we call /ajax/jobelements.php to fill in the dialog,
         passing the workOrderId. That gives us a list of elements to which we might want
         to add a task / workOrderTask. We select the element(s) and get a dialog to add a task.
         Return of /ajax/jobelements.php, lets you choose one or more elements
         from this workOrder.
         We then use the result of that selection to call fb/workordertaskcats.php, which builds
         the dialog to add tasks to a workorder.
      */ ?>
$(function() {
    $( "#elementdialog" ).dialog({autoOpen:false, width:400, height:200});
    $( ".elementopen" ).click(function(event) {
        event.preventDefault();

        $( "#elementdialog" ).dialog({
            position: { my: "center bottom", at: "center top", of: $(this) },
            open: function(event, ui) {
                $(".ui-dialog-titlebar-close", ui.dialog | ui ).show();
                $(".ui-dialog-titlebar", ui.dialog | ui ).show();
            }
        });

        // In the following: 2020-08-25 JM added passing the dialog name
        //  so as not have the code so tightly coupled that /ajax/jobelements.php needs to know the name of a DIV in this file.
        $("#elementdialog").dialog("open").html(
            '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').dialog({height:'45', width:'auto'})
            .load('/ajax/jobelements.php?workOrderId=' + escape(<?php echo $workOrder->getWorkOrderId(); ?>) + '&dialogName=elementdialog', function(){
                $('#elementdialog').dialog({height:'auto', width:'auto'});
        });

        $( "#elementdialog" ).dialog("open");
        workOrderTaskId = $(this).attr('name'); // NOTE that this sets a global. // >>>00014 JM: I don't understand, though. It's assigning attr('name'),
    	                                        // but $(this) is $( ".elementopen" ), and I don't see where that has a 'name' attribute.
    });
});

</script>
<link rel="stylesheet" href="../styles/kendo.common.min.css" />
<link rel="stylesheet" href="../styles/kendo.material-v2.min.css" />
<script src='https://cdnjs.cloudflare.com/ajax/libs/jeditable.js/1.7.3/jeditable.min.js'> </script>
<link rel="stylesheet" href="https://kendo.cdn.telerik.com/2021.2.616/styles/kendo.default-v2.min.css" />
<!-- <script src='/js/jquery.min.js' ></script>

 -->
 <script>

// Copy link on clipboard.

function copyToClip(str) {
  function listener(e) {
    e.clipboardData.setData("text/html", str);
    e.clipboardData.setData("text/plain", str);
    e.preventDefault();
  }
  document.addEventListener("copy", listener);
  document.execCommand("copy");
  document.removeEventListener("copy", listener);
};


/*
kendo.pdf.defineFont({
            "DejaVu Sans": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans.ttf",
            "DejaVu Sans|Bold": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Bold.ttf",
            "DejaVu Sans|Bold|Italic": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Oblique.ttf",
            "DejaVu Sans|Italic": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Oblique.ttf",
            "WebComponentsIcons": "https://kendo.cdn.telerik.com/2017.1.223/styles/fonts/glyphs/WebComponentsIcons.ttf"
        });
*/
</script>

<style>

    .k-sprite {
        /*background-image: url("styles/coloricons-sprite.png"); */
    }
    li {
        line-height: 2;

    }
    .rootfolder { background-position: 0 0; }
    .folder { background-position: 0 -16px; }
    .pdf { background-position: 0 -32px; }
    .html { background-position: 0 -48px; }
    .image { background-position: 0 -64px; }
</style>

<style>
    input.tally.bad-value {border-color:red;}
    input.tally.confirmed {background-color:LightGreen;}

    body, #container,table { background-color: #fff; }
    #stampId {
        display: inline!important;
        width: 20%!important;
    }
    .newstatus {
        width: 60%!important;
    }

    .k-treeview span.k-in {
        cursor: default;
        font-size: 14px;
    }

    .nav-link {
        font-size: 13px;
    }

    #change-elements-used, #change-elements-used2 {
        color: #000;
        font-family: Roboto,"Helvetica Neue",sans-serif;
    }

    #change-elements-used:hover, #change-elements-used2:hover {
        color: #fff;
    }

    /* Copy link button */
    #copyLink {
        color: #000;
        font-family: Roboto,"Helvetica Neue",sans-serif;
        font-size: 12px;
        font-weight: 600;
    }

    #copyLink:hover {
        color: #fff;
        font-size: 12px;
        font-weight: 600;
    }
    div.sticky {
    position: -webkit-sticky; /* Safari */
    position: sticky;
    top: 0;
    background-color: #fff!important;


    }
    div.sticky input {
        background-color: #fff!important;
        /*filter: invert(100%);*/
    }

    /* Combo Select - ASC | DESC */
    #comboSelect {
        width: auto;
        float: right;
    }

    #firstLinkToCopy{
        font-size: 18px;
        font-weight: 700;
    }
</style>

<script src='/js/kendo.all.min.js' ></script>


<div id="elementdialog"></div>

<div id="container" class="clearfix">
<?php

        // WORKORDER MULTIPLIER
        $woTime = 0; // time WO
        $woCost = 0; // cost WO
        $woTimeWo = 0;
        $revenue = 0;
        $final = 0;
        $revenueTotal = 0;
        $clientMultiplier = 1;
        $arrayWot = [];

        $query = "SELECT wo.workOrderTaskId, wo.quantity, wo.cost, woTt.minutes ";
        $query .= " FROM " . DB__NEW_DATABASE . ".workOrderTask wo ";
        $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskTime woTt on woTt.workOrderTaskId = wo.workOrderTaskId ";
        $query .= " WHERE wo.workOrderId = " . intval($workOrder->getWorkOrderId()) . ";";

        $result = $db->query($query);
        while ($row = $result->fetch_assoc()) {
            $arrayWot[] = $row;
        }


        foreach($arrayWot as $woT) {

            $woTime = intval($woT["minutes"]/60*100)/100; // per WoT
            if($woT["quantity"] != 0 && $woT["cost"] != 0) {
                $woTimeWo += $woTime;
            }


            $query = "SELECT  workOrderId FROM  " . DB__NEW_DATABASE . ".workOrderTask ";
            $query .= " WHERE workOrderTaskId = " . intval($woT['workOrderTaskId']) . ";";

            $result = $db->query($query);

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $query = "SELECT  clientMultiplier FROM  " . DB__NEW_DATABASE . ".contract ";
                $query .= " WHERE workOrderId = " . intval($row['workOrderId']) . ";";

                $result = $db->query($query);

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if($row['clientMultiplier'] > 0) {
                        $clientMultiplier = $row['clientMultiplier'];
                    } else {
                        $clientMultiplier = 1;
                    }
                }

            }


            $revenue = ($woT["quantity"] * $woT["cost"] * $clientMultiplier);

            $revenueTotal += $revenue;

        }

        if( $revenueTotal > 0 &&  $woTimeWo > 0 ) {
            $final =  $revenueTotal /  $woTimeWo;
        } else {
            $final = 0;
        }

        // WOm = Total revenue for WorkOrder / Hours spent on WorkOrder
        //echo " " . number_format($final, 2);
        //echo "<span class='font-weight-bold mr-1' style='font-size:18px; float:right'> Multiplier: " . number_format($final, 2) ."</span>";




    $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/workorder/' . rawurlencode($workOrderId);
?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
            [WO]&nbsp;<a id="linkWoToCopy" href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            <button id="copyLink" title="Copy WO link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>
        <p class="mt-3 mb-1 ml-4"  style="padding-right:3%; float:right; background-color:#fff!important; font-size: 18px;" ><a class="font-weight-bold mr-1' style='font-size:19px; " href="#"> <?php echo number_format($final, 2) ; ?></a></p>
        <span id="linkToCopy" style="display:none"> [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)&nbsp;[WO]&nbsp;<a href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a></span>

        <span id="linkToCopy2" style="display:none"> <a href="<?= $urlToCopy?>">[J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<?php echo $job->getNumber();?>)
            [WO]&nbsp; <?php echo $workOrder->getDescription();?> </a></span>
    </div>
    <div class="clearfix"></div>
    <div class="main-content">
        <?php /* Information icon at top (as of 2019-04, doesn't lead to much). George - removed.
        <img class="helpopen" id="workOrder" src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_info.gif" width="20" height="20" border="0">
        */ ?>
        <?php /* Header with job name & link, + work order name. */ ?>

        <?php /* "Print" button, links to workorderpdf.php, passes workOrderId. */

        $criteria = Array();
        $criteria['customerId'] = $customer->getCustomerId();
        $criteria['active'] = 'yes';
        $criteria['eor'] = 'both';

        $locations = $job->getLocations($error_is_db);
        if ($error_is_db) { // true on query failure
            $errorId = '637429419080452984';
            $logger->errorDB($errorId, "Job::getLocations() method failed.", $db);
        }

        $state = '';
        if ($locations) {
            $state = $locations[0]->getState();
        }
        if (!$state) {
            $state = HOME_STATE;
        }
        $criteria['state'] = $state;
        unset($locations, $state);

        $stamps = Stamp::getStamps($criteria);
        if ($stamps) {
            ?>
            <form method="POST" id="workOrderPdfForm" action="/workorderpdf.php">
            <input type="hidden" name="workOrderId" value="<?= $workOrder->getWorkOrderId(); ?>" />
            <a id="printpdf" class=" print ml-0 btn btn-sm btn-secondary mb-2 " style="cursor:pointer; color:#fff">&nbsp;Print</a>
            <select class="form-control input-sm" id="stampId" name="stampId">
                <?php
                $eorPersonId = null;
                $teamRows = $workOrder->getTeamPosition(TEAM_POS_ID_EOR, false, true); // OK if we find more than one; we'll use the first here.
                if (!$teamRows) {
                    // look to the job for a possible EOR
                    $workOrder->getJob()->getTeamPosition(TEAM_POS_ID_EOR, false, true);
                }
                if ($teamRows) {
                    // If there is more than one, we pick the first.
                    $companyPersonId = $teamRows[0]['companyPersonId'];
                    if ($companyPersonId) {
                        $companyPerson = new CompanyPerson($companyPersonId);
                        if ($companyPerson) {
                            $eorPersonId = $companyPerson->getPersonId();
                        } else {
                            $logger->error2('1588022584', "WorkOrder $workOrderId no valid personId for companyPersonId $companyPersonId");
                        }
                    } else {
                        $logger->warn2('1588022565', "WorkOrder $workOrderId no valid companyPersonId for EOR on team row {$teamRows[0]['teamId']}");
                    }
                } else {
                    $logger->info2('1588022555', "WorkOrder $workOrderId has no EOR");
                }
                foreach ($stamps AS $stamp) {
                    $stampPersonId = $stamp->getEorPersonId();
                    $selected = ($stampPersonId && $eorPersonId && $stampPersonId == $eorPersonId) ? 'selected' : '';
                    echo "<option value=\"{$stamp->getStampId()}\" $selected>{$stamp->getDisplayName()}</option>\n";
                }
                unset($teamRows, $companyPerson, $companyPersonId, $eorPersonId, $stampPersonId, $selected);
                ?>
            </select>
            <?php
        } else {
            $logger->warn2('1588022525', "Could not get stamps");
        }
        ?>
        </form>
        <script>
        $(function() {
            $('#printpdf').click(function() {
                $(this).closest('form').submit();
            });
        });
        </script>



        <?php /* "Details" button, links to workorderdetail.php, passes workOrderId. */ ?>
        <a class="print ml-0 btn btn-secondary  btn-sm  mb-2 mt-2 " style="color:#fff"  id="workOrderDetails" href="/workorderdetail.php?workOrderId=<?php  echo $workOrder->getWorkOrderId(); ?>">&nbsp;Details</a>

        <div class="full-box clearfix mt-3">

        <?php
        /* ===============================
            Tasks section.

            "Add button" & table of existing workOrderTasks.

        <h2 class="heading">Tasks</h2>  */ ?>

        <?php /* "Add" button brings up a minimal jQuery dialog elementdialog (implemented above)
                    which should allow the addition of new workOrderTasks to this workOrder. */ ?>

        <?php /* " <a class="button add show_hide elementopen" id="openElementDialog" href="#">Add</a>*/ ?>


        <a class="btn btn-secondary btn-sm mb-2 mt-2 " style="color:#fff" id="openElementDialogNew" href="/workordertasks/<?php echo $workOrderId?>">Add Tasks</a>
        <!-- <div id="gantt"  style="display: block; border: 1px solid black; height:650px!important" ></div> -->
        <div id="gantt" class="clearfix" ></div>

<?php


    $query = "SELECT elementId as id, elementName as Title, null as parentId,
    null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, null as internalTaskStatus, '' as icon, '' as wikiLink, null as taskStatusId, null as tally, null as hoursTime,
    null as personInitials, '' as noteId, '' as noteText, '' as inserted, '' as firstName, elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
    from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
    UNION ALL
    SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
    w.extraDescription as extraDescription, w.internalTaskStatus as internalTaskStatus, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId, tl.tally as tally, wt.tiiHrs as hoursTime, wopi.legacyInitials,
    nv.id as noteId, nv.noteText as noteText, nv.inserted as inserted, nv.firstName as firstName, getElement(w.workOrderTaskId),
    e.elementName, false as Expanded, false as hasChildren
    from workOrderTask w
    LEFT JOIN task t on w.taskId=t.taskId
    LEFT JOIN taskTally tl on w.workOrderTaskId=tl.workOrderTaskId


    LEFT JOIN (

        SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
        FROM workOrderTaskTime wtH
        GROUP BY wtH.workOrderTaskId
        ) AS wt
        on wt.workOrderTaskId=w.workOrderTaskId


    LEFT JOIN (

        SELECT
        nt.id, nt.noteText, nt.inserted, ps.firstName
        FROM note nt
        LEFT JOIN person ps on ps.personId=nt.personId
        GROUP BY nt.id
        ) AS nv
        ON nv.id=w.workOrderTaskId

    LEFT JOIN (

        SELECT
        wop.workOrderTaskId, GROUP_CONCAT( cp.legacyInitials SEPARATOR ', ') as legacyInitials
        FROM workOrderTaskPerson wop
        LEFT JOIN customerPerson cp on cp.personId=wop.personId
        GROUP BY wop.workOrderTaskId
        ) AS wopi
        ON wopi.workOrderTaskId=w.workOrderTaskId

    LEFT JOIN element e on w.parentTaskId=e.elementId
    WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null ORDER BY FIELD(elementName, 'General') DESC, internalTaskStatus DESC";

    //echo $query;
    //die();
    $res=$db->query($query);

    $out=[];
    $parents=[];
    $elements=[];


    while( $row=$res->fetch_assoc() ) {
        $out[]=$row;
        if( $row['parentId']!=null ) {
        $parents[$row['parentId']]=1;
    }
    if( $row['taskId']==null)    {
        $elements[$row['elementId']] = $row['elementName'] ;

        }
    }

    for( $i=0; $i<count($out); $i++ ) {
        if( $out[$i]['Expanded'] == 1 )
        {
            $out[$i]['Expanded'] = true;
        } else {
            $out[$i]['Expanded'] = false;
        }

        if($out[$i]['hasChildren'] == 1)
        {
            $out[$i]['hasChildren'] = true;
        } else {
            $out[$i]['hasChildren'] = false;
        }

        if( isset($parents[$out[$i]['id']]) ) {
            $out[$i]['hasChildren'] = true;
        }
        if ( $out[$i]['elementName'] == null ) {
            $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
        }

    }


    $elementsIds=[];
    $elementsIds = array_unique(array_column($out, 'elementId'));

    $elementInternalTask= [];
    $internalTaskAssign = [];

    $errorWotInternal = ''; // errors
    foreach($elementsIds as $element) {
        // all children internalTaskStatus of each element
        $query = "select workOrderTaskId, internalTaskStatus,
        parentTaskId
        from    (select * from workOrderTask
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$element') initialisation
        where   find_in_set(parentTaskId, @pv)
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";

        $result = $db->query($query);

        if (!$result) {
            $errorId = '637810460319540691';
            $errorWotInternal = 'We could not retrive the workOrderTasks. Database error. Error id: ' . $errorId;
            $logger->errorDb($errorId, 'We could not retrive workOrderTasks internal status', $db);

        }

        if(!$errorWotInternal) {
            while( $row=$result->fetch_assoc() ) {

                if($row["internalTaskStatus"] != 0) {
                    $internalTaskAssign[] = $row["workOrderTaskId"];
                    $elementInternalTask[] = $element;
                }
            }
        }

    }
    if ($errorWotInternal) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWotInternal</div>";
    }
    unset($errorWotInternal);

    $elementInternalTask = array_merge(array_unique($elementInternalTask));

?>


<style>
    @media screen and (max-width: 680px) {
        .treeview-flex {
            flex: auto !important;
            width: 100%;
        }
    }
    .workorderClick
    {
        text-transform:capitalize;
        padding:25px;
        font-size:15px;
        line-height:2.5;
        font-weight: 600;
    }

    #treeview .k-sprite {
        /*background-image: url("https://demos.telerik.com/kendo-ui/content/web/treeview/coloricons-sprite.png"); */
    }

    .folder { background-position: 0 -16px; }
    .html { background-position: 0 -48px; }
    body {
        background-image: url("");
    }
    .fancybox-wrap .fancybox-desktop .fancybox-type-iframe .fancybox-opened {
        left: 100px!important;
    }

    /* Changes on popup Edit Mode */
    .k-button-primary, .k-button.k-primary {
        color: #fff;
        background-color: #3fb54b;
        font-size: 12px;
    }
    .k-window-titlebar {
        padding: 8px 6px;
    }
    /* End changes on popup Edit Mode */
    .telerik-icon {
        margin-left: 5px;
    }
    #treeview-kendo > ul > li > div > span > span.k-icon.k-i-close.telerik-icon {
        display:none!important;
    }
    /*#treeview-telerik-wo > ul > li > ul > li > div >  span > span.k-icon.k-i-close.telerik-icon {
        display:none!important;

    }*/


    .treeInlineEdit > input
    {
        font-size: 1.5em;
        min-width: 10em;
        min-height: 2em;
        border-radius: 5px 5px 5px 5px;
        -moz-border-radius: 5px 5px 5px 5px;
        border: 0px solid #ffffff;
    }


    #myCustomDescription {
        height:100%;
        line-height: 1.3;
    }
    #gantt .k-grid-header
    {
    padding: 0 !important;
    }

    #gantt .k-grid-content
    {
    overflow-y: visible;
    }
    .k-gantt-header  {
        display:none;
    }
    .k-gantt-footer {
        display:none;
    }
    .k-gantt-timeline{
        display:none;
    }
   /* .k-gantt-treelist {
        width: 100%!important;

    }*/


    .k-grid tbody button.k-button {
        min-width: 20px;
        border: 0px solid #fff;
        background: transparent;
    }

    #treeview-telerik-wo {
        display:none!important;
    }

    /* Header padding */
    .k-gantt-treelist .k-grid-header tr {
        height: calc(2.8571428571em + 4px);
        vertical-align: bottom;
    }

    .k-gantt .k-treelist .k-grid-header .k-header {
        padding-left: calc(0.8571428571em + 6px);
    }
    /* Header padding */

   .k-command-cell>.k-button, .k-edit-cell>.k-textbox, .k-edit-cell>.k-widget, .k-grid-edit-row td>.k-textbox, .k-grid-edit-row td>.k-widget {
        vertical-align: middle;
        background-color: #fff;
    }

    .k-scheduler-timelineWeekview > tbody > tr:nth-child(1) .k-scheduler-table tr:nth-child(2) {
    display: none!important;
    }

    /* Extradescription in two rows */
    .k-grid  td {
        height: auto;
        white-space: normal;
    }
    .no-scrollbar .k-grid-header
    {
    padding: 0 !important;
    }

    .no-scrollbar .k-grid-content
    {
        overflow-y: visible;
    }


    /* Hide the Horizonatal bar scroll */
    .k-gantt .k-treelist .k-grid-content {
       /* overflow-y: hidden;*/
        overflow-x: hidden;
    }

    /* Hide the Vertical bar */
    .k-gantt .k-splitbar {
        display: none;
    }
    .k-gantt-treelist .k-i-expand,
    .k-gantt-treelist .k-i-collapse {
        cursor: pointer;
    }
    /* Horizontal Scroll*/
    #gantt .k-grid-content {
       /* overflow-y: hide!important;*/
    }
    .k-i-cancel:before {
        content: "\e13a"; /* Adds a glyph using the Unicode character number */
    }


  </style>

<script id="column-template"  type="text/x-kendo-template">
#
    // var host = window.location.host;  // sseng
    //var domain = window.location.origin; /// https://ssseng.com/
    //var urlImg = domain + '/cust/' + host + '/img/icons_task/';
    var urlImg = 'https://ssseng.com/cust/ssseng/img/icons_task/';

#
# if(parentId != null) { #

    # if(icon && wikiLink) { #
        <img class="tree-list-img" src="#= urlImg + icon #" border="0" title="More info"  onclick="showJobTask('#= id#')" style="cursor: pointer" >
    # } else if(icon && !wikiLink) { #
        <img class="tree-list-img" src="#= urlImg + icon #" border="0" onclick="showJobTask('#= id#')" style="cursor: pointer">

    # } else { #
        <img class="tree-list-img" src="#= urlImg + 'none.jpg' #" border="0" onclick="showJobTask('#= id#')"  style="cursor: pointer">
    # } #

# } #
</script>

<script>
    function showJobElement(elementName){
        $.ajax({
            url: '/ajax/getElementJobs.php',
            data: {
                elementName: elementName,

            },
            async:false,
            type:'get',
            success: function(data, textStatus, jqXHR) {
                // create Gantt Tree
                $('#jobmap').animate({left: "200px"}, 1000);
                console.log(data);
            }
        }).done(function() {

/*            var dataSource = new kendo.data.GanttDataSource({ data: allTasks });
            var grid = $('#gantt').data("kendoGantt");

            dataSource.read();
            grid.setDataSource(dataSource);
*/
        });
    }


</script>


<script id="column-title" type="text/x-kendo-template">

# if(parentId == null) { #
   <span class="font-weight-bold" >#= Title#</span>

# } else { #
    # if(wikiLink==null || wikiLink=='' || wikiLink=='null') { #
        <span title='#=Title#' style='cursor: pointer;text-decoration: underline; color: blue' onclick='alert("No link to wiki Page")'>#=Title#</span>
    # } else { #
        <a  target="_blank" href="#: wikiLink #"><span title='#=Title#' style='cursor: pointer;text-decoration: underline;; color: blue'>#=Title#</span></a>
    # } #

# } #

</script>

<script id="column-desc" type="text/x-kendo-template">

    # if(parentId != null) { #
        # if(extraDescription) { #
                <span class='form-control form-control-sm formClassDesc' title="Edit" >#=extraDescription#</span>
        # } else { #
            <span class='form-control form-control-sm formClass' title="Edit" ></span>
        # } #
    # } else { #
        <span ></span>
    # } #

</script>


<script id="column-personInitials" type="text/x-kendo-template">

# if(personInitials != null) { #
   <span class="font-weight-bold"">#= personInitials#</span>

# } else { #
    <span> </span>

# } #

</script>

<script id="column-tally" type="text/x-kendo-template">
#tally = Number(tally);#
# if(tally) { #
    # if(typeof tally === 'number') { #
            #if(tally % 1 === 0) { #
                # tally = parseInt(tally); #
            # } else { #
                # tally =  parseFloat(tally).toFixed(2); #
            #}  #
        # }  #
   <span class='form-control form-control-sm tallyClass' title="Edit">#=tally#</span>

# } else if(parentId == null) { #
    <span></span>

# } else { #
    #if (Number.isNaN(Number.parseFloat(tally))) {#
            #tally = 0;#
        #} #
    <span class='form-control form-control-sm tallyClass' title="Edit">#=tally#</span>

# } #

</script>

<script id="column-hoursTime" type="text/x-kendo-template">

# if(hoursTime) { #

   <span>#=kendo.parseFloat((hoursTime/60*100)/100) #</span>


# } else if(parentId == null) { #
    <span></span>

# } else { #
    <span>0</span>

# } #

</script>


<script id="column-status" type="text/x-kendo-template">

# if(taskStatusId == "1" || taskStatusId == "0") { #

    <a id="statuscell_#=workOrderTaskId#" class="  statusWoTaskColor rounded-circle" href="javascript:setTaskStatusIdNew(#=workOrderTaskId#,9)" role="button" />
# } else if(taskStatusId == "9") { #

    <a id="statuscell_#=workOrderTaskId#" class="  statusWoTaskColor2 rounded-circle" href="javascript:setTaskStatusIdNew(#=workOrderTaskId#,1)" role="button" />

# } else if (parentId == null ) { #
    <button style='' class='changeStatusWoTask  statusWoTaskColorNone rounded-circle'></button>
# } #

</script>




<script>

$(document).ready(function() {
    // Change text Button after Copy.
    $('#copyLink').on("click", function (e) {
        $(this).text('Copied');
    });
    $("#copyLink").tooltip({
        content: function () {
            return "Copy WO Link";
        },
        position: {
            my: "center bottom",
            at: "center top"
        }
    });



    var allTasksWoElements=<?php echo json_encode($out); ?>;
    var elementInternalTask = <?php echo json_encode($elementInternalTask);?>;
    var internalTaskAssign = <?php echo json_encode($internalTaskAssign);?>;
    var blockAdd = <?php echo json_encode($blockAdd);?>;


    var gantt = $("#gantt").kendoGantt({
        editable: "incell",
        dataSource : allTasksWoElements,

        resizable: true,
        schema: {
            model: {
                id: "id",
                parentId :"parentId",
                expanded: true,
                fields: {
                    id: { from: "id", type: "number" },
                    elementId: { from: "elementId", type: "number" },
                    parentId: { from: "parentId", type: "string" },
                    elementName: { from: "elementName", defaultValue: "", type: "string" },
                    workOrderTaskId: { from: "workOrderTaskId", type: "number" },
                    taskId: { from: "taskId", type: "number"},
                    parentTaskId : { from: "parentTaskId", type: "number" },
                    text: { from: "text", defaultValue: "", type: "string" },
                    personInitials: { from: "personInitials", defaultValue: "", type: "string" },
                    taskStatusId: { from: "taskStatusId", type: "number" },
                    extraDescription: { from: "extraDescription", type: "string" , attributes: {class: "word-wrap"}},
                    noteText: { from: "noteText", defaultValue: "", type: "string" },
                    tally: { from: "tally", type: "float" },
                    hoursTime: { from: "hoursTime", type: "float" },
                    icon: { from: "icon", type: "string" },
                    wikiLink: { from: "wikiLink", type: "string" },
                    expanded: { from: "Expanded", type: "boolean", defaultValue: true }
            }

            }
        },

        columns: [

            { command:
            [
            { name: "assign",
                className: "assignIframe fancyboxIframe text-lowercase table-cell k-text-center",
                text: ""

                },

             { name: "b-ass",
                className: "bassIframe fancyboxIframe text-lowercase font-weight-bold table-cell k-text-center",
                text: ""

                }  ,
                {name:'delrow', className: "k-gantt-aici", text: "N"}
            ],

            title: "", attributes: {
                "class": "assignPersonElement"
            },
            width: "90px" },


            { field: "Title", title: "Task", template: $("#column-title").html(), editable: false, width: 250 },

            { field: "personInitials", title: "Person", template: $("#column-personInitials").html(), editable: false, width: 80 },


            { field: "icon", title:"Icon", template: $("#column-template").html(),
            attributes: {
                "class": "table-cell k-text-center"
            }, editable: false, width: 60 },

            { field: "extraDescription", title: "Additional Information", template: $("#column-desc").html(), headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "extraDescriptionUpdate"
            }, editable: true, width: 250 },


            { field: "", title: "Notes", headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "table-cell k-text-center noteClass"
            },editable: false, width: 60 },



            { field: "hoursTime", title: "Hours", template: $("#column-hoursTime").html(), headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "table-cell k-text-center"
            },editable: false, width: 60 },

            { field: "tally", title: "Tally", template: $("#column-tally").html(), headerAttributes: { style: "white-space: normal"}, attributes: {
                "class": "table-cell k-text-center tallyCell"
            },editable: true, width: 60 },



            { field: "taskStatusId", title: "Status", template: $("#column-status").html(),  headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "table-cell k-text-center updateStatusToTask"
            }, editable: false, width: 60 },

        ],
        dataBound: function(e) {
        },
        edit: function(e) {
            // George : prevent add/ edit extra Description to Elements.
            if(e.task.parentId == null) {
                e.preventDefault();
            }
            if(blockAdd == true && e.task.internalTaskStatus !=5 ) {
                e.preventDefault();
            }
        },
        assign: function(e) {
            // George : prevent add/ edit extra Description to Elements.
            if(e.task.parentId == null) {
                this.hide();
            }
        },


        toolbar: false,
        header: false,
        listWidth: "100%",
        height: "550px",
        listHeight: "550px",
        scrollable: true,
        //selectable: "row",
        dragAndDrop: false,
        selectable: true,
        drag: false,
        //dataBound: onDataBound,
        dataBound:function(e){
              this.list.bind('dragstart', function(e) {
                  return;
              })
            },

        dataBound:function(e) {
            this.list.bind('drop', function(e) {
                e.preventDefault();
                return;

            })
        }


    }).data("kendoGantt");


    $(document).bind("kendo:skinChange", function () {
        gantt.refresh();
    });

    gantt.bind("dataBound", function(e) {
        gantt.element.find("tr[data-uid]").each(function (e) {
        var dataItem = gantt.dataSource.getByUid($(this).attr("data-uid"));

            if(dataItem.parentId == null) {


                $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html(''); // FIRST COLUMN BUTTON

                if(blockAdd == true) {
                    // we have INTERNAL TASKS
                    if(elementInternalTask.includes(dataItem.id.toString())) {

                        $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe"  class="bassIframe fancyboxIframe text-lowercase font-weight-bold"  href="/fb/bulkassignperson.php?elementId='+dataItem.elementId+'&workOrderId='+<?php echo intval($workOrderId)?>+' "><span class="k-icon k-i-edit"></span> [b-ass]</a>'); // FIRST COLUMN BUTTON

                    } else {
//                        $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" style="pointer-events:none;" class="bassIframe fancyboxIframe text-lowercase font-weight-bold"  href="/fb/bulkassignperson.php?elementId='+dataItem.elementId+'&workOrderId='+<?php echo intval($workOrderId)?>+' "><span class="k-icon k-i-edit"></span> [b-ass]</a>'); // FIRST COLUMN BUTTON
                        $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" class="bassIframe fancyboxIframe text-lowercase font-weight-bold"  href="/fb/bulkassignperson.php?elementId='+dataItem.elementId+'&workOrderId='+<?php echo intval($workOrderId)?>+' "><span class="k-icon k-i-edit"></span> [b-ass]</a>'); // FIRST COLUMN BUTTON

                    }
                } else {
                    // NO INTERNALS
                    $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe"  class="bassIframe fancyboxIframe text-lowercase font-weight-bold"  href="/fb/bulkassignperson.php?elementId='+dataItem.elementId+'&workOrderId='+<?php echo intval($workOrderId)?>+' "><span class="k-icon k-i-edit"></span> [b-ass]</a>'); // FIRST COLUMN BUTTON
                }

                $("[data-uid=" +dataItem.uid + "] td.noteClass").html('');
                $("[data-uid=" +dataItem.uid + "] td.extraDescriptionUpdate").html('');
                $('.bassIframe').fancybox({
                    type: 'iframe',
                    iframe: {
                        scrolling : 'auto',
                        preload   : true
                    },
                    'onComplete' : function() {
                        $('#fancyboxIframe').load(function() { // wait for frame to load and then gets it's height
                        $('#fancybox-content').height($(this).contents().find('body').height()+30);
                        });
                    },

                    "afterClose": function() {
                        // Reload Gant Data
                        gantTreeWoAjaxCall();
                        expandGanttTree();

                    }
                });
                $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").addClass('statusWoTaskColorNone');
            } else {

                $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html(''); // FIRST COLUMN BUTTON


                if(blockAdd == true) {
                    if(internalTaskAssign.includes(dataItem.workOrderTaskId.toString())) {
                        $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" class="assignIframe fancyboxIframe text-lowercase"  href="/fb/workordertask.php?workOrderTaskId='+dataItem.workOrderTaskId+'"><span class="k-icon k-i-edit"></span> assign</a>'); // FIRST COLUMN BUTTON

                    } else {
//                        $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" class="assignIframe fancyboxIframe text-lowercase"  style="pointer-events:none;" href="/fb/workordertask.php?workOrderTaskId='+dataItem.workOrderTaskId+'"><span class="k-icon k-i-edit"></span> assign</a>'); // FIRST COLUMN BUTTON
                        $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" class="assignIframe fancyboxIframe text-lowercase" href="/fb/workordertask.php?workOrderTaskId='+dataItem.workOrderTaskId+'"><span class="k-icon k-i-edit"></span> assign</a>'); // FIRST COLUMN BUTTON

                    }
                } else {
                    $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" class="assignIframe fancyboxIframe text-lowercase"  href="/fb/workordertask.php?workOrderTaskId='+dataItem.workOrderTaskId+'"><span class="k-icon k-i-edit"></span> assign</a>'); // FIRST COLUMN BUTTON
                }


                //$("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html('<a data-fancybox-type="iframe" class="assignIframe fancyboxIframe text-lowercase"  href="/fb/workordertask.php?workOrderTaskId='+dataItem.workOrderTaskId+'"><span class="k-icon k-i-edit"></span> assign</a>'); // FIRST COLUMN BUTTON
                //$("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").prop('id', 'assignCell_' + dataItem.workOrderTaskId + '');
                $('.assignIframe').fancybox({
                    type: 'iframe',

                    "afterClose": function() {
                        // Reload Gant Data
                        gantTreeWoAjaxCall();
                        expandGanttTree();

                        //console.log(IndexofRow);
                        table = $("#gantt");
                        row = table.find('tr').eq(IndexofRow + 1);

                        var bg = $(row).css('background'); // store original background
                        row.css('background-color', '#FFDAD7'); //change element background
                        setTimeout(function() {
                            $(row).css('background', bg); // change it back after ...
                        }, 10000); // 10 seconds


                    }
                });

                if(dataItem.internalTaskStatus != null) {

                    if(blockAdd == true && parseInt(dataItem.internalTaskStatus) !=5 ) {
                        $("[data-uid=" +dataItem.uid + "]").find(".formClass").attr('readonly', 'readonly');

                    } else {
                        $("[data-uid=" +dataItem.uid + "]").find(".formClass").attr("readonly", false);
                    }

                }


                if(blockAdd == true) { // INTERNAL TASKS
                    if(internalTaskAssign.includes(dataItem.workOrderTaskId.toString())) {
                        // notes
                        if(dataItem.noteText != null) {

                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('');
                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' class="k-icon gantt-modalNote k-i-edit-tools"></a></span>'); // FIRST COLUMN BUTTON
                        } else {

                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('');
                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' class="k-icon gantt-modalNote k-i-comment"></a></span>'); // FIRST COLUMN BUTTON
                        }
                        // wot Status

                        if(dataItem.taskStatusId == "1" || dataItem.taskStatusId == "0") {
                            $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").addClass('statusWoTaskColor');
                            $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").removeClass('statusWoTaskColor2');

                        } else  {
                            $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").addClass('statusWoTaskColor2');
                            $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").removeClass('statusWoTaskColor');
                        }
                        // wot Status
                        $("tr[data-uid=" +dataItem.uid + "] td.updateStatusToTask").css('pointer-events', 'all');

                    } else {
                        // notes blocked
                        if(dataItem.noteText != null) {

                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('');
                            //$("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' style="pointer-events:none;"  class="k-icon gantt-modalNote k-i-edit-tools"></a></span>'); // FIRST COLUMN BUTTON
                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' class="k-icon gantt-modalNote k-i-edit-tools"></a></span>'); // FIRST COLUMN BUTTON
                        } else {

                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('');
//                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' style="pointer-events:none;"  class="k-icon gantt-modalNote k-i-comment"></a></span>'); // FIRST COLUMN BUTTON
                            $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' class="k-icon gantt-modalNote k-i-comment"></a></span>'); // FIRST COLUMN BUTTON
                        }

                        // wot Status blocked
                        $("tr[data-uid=" +dataItem.uid + "] td.updateStatusToTask").css('pointer-events', 'none');


                    }
                } else { // no internal tasks
                    // notes
                    if(dataItem.noteText != null) {

                        $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('');
                        $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' class="k-icon gantt-modalNote k-i-edit-tools"></a></span>'); // FIRST COLUMN BUTTON
                    } else {

                        $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('');
                        $("tr[data-uid=" +dataItem.uid + "] td.noteClass").html('<span><a id="assignCell_' + dataItem.workOrderTaskId + '" data-id='+dataItem.workOrderTaskId+' class="k-icon gantt-modalNote k-i-comment"></a></span>'); // FIRST COLUMN BUTTON
                    }

                    // status
                    if(dataItem.taskStatusId == "1" || dataItem.taskStatusId == "0") {
                        $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").addClass('statusWoTaskColor');
                        $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").removeClass('statusWoTaskColor2');

                    } else  {
                        $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").addClass('statusWoTaskColor2');
                        $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").removeClass('statusWoTaskColor');
                    }
                }



            }
            $("tr").removeAttr("style");
        });
    })

    // Prevent Drag and Drop.
    $("#gantt .k-grid-content").on("dragstart mousedown", function(e){
       //return false;
    });


    // George: Get DB data with ajax.
    // Used for status update,b-ass and assign persons to WorkOrderTasks.
    var gantTreeWoAjaxCall = function() {
        $.ajax({
            url: '/ajax/get_wo_tasks3.php',
            data: {
                workOrderId: <?php echo intval($workOrderId); ?>,

            },
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                // create Gantt Tree
                allTasks = data[0];

            }
        }).done(function() {

            var dataSource = new kendo.data.GanttDataSource({ data: allTasks });
            var grid = $('#gantt').data("kendoGantt");

            dataSource.read();
            grid.setDataSource(dataSource);

        });
    }



    var expandGanttTree = function(e) {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].hasChildren) {
                tas[i].set("expanded", true);
            }
            if(tas[i].internalTaskStatus != null) {

                if(blockAdd == true && parseInt(tas[i].internalTaskStatus) !=5 ) {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", true);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", true);
                    $("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'none');

                } else {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", false);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", false);
                    $("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'all');

                }
          }
        }
    }
    expandGanttTree();

    $( "#gantt" ).on( "click", ".k-i-collapse", function() {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {

            if(tas[i].internalTaskStatus != null) {

                if(blockAdd == true && parseInt(tas[i].internalTaskStatus) !=5 ) {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", true);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", true);
                    $("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'none');

                } else {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", false);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", false);
                    $("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'all');

                }
            }
        }
    });

    $( "#gantt" ).on( "click", ".k-i-expand", function() {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {

            if(tas[i].internalTaskStatus != null) {

                if(blockAdd == true && parseInt(tas[i].internalTaskStatus) !=5 ) {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", true);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", true);
                    $("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'none');

                } else {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", false);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", false);
                    $("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'all');

                }
            }
        }
    });


   var gatIndexofRow = function(workOrderTaskId) {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].workOrderTaskId == workOrderTaskId) {
               return i; // row Index.
            }
        }
    }


    var IndexofRow = "";
    $("#gantt").on("click", "tr", function() {
        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));

        var workOrderTaskId = item.workOrderTaskId;
         IndexofRow = gatIndexofRow(workOrderTaskId);

    });

    var IndexofRowTd = ""; // used for add notes.
    $("#gantt tbody").on("click",  "[id^='assignCell_']", function() {

        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));

        var workOrderTaskId = item.workOrderTaskId;
        IndexofRowTd = gatIndexofRow(workOrderTaskId);

    });





    var idNote;
    // on icon "Note" click, populate the modal with values.
    $("#gantt tbody").on('click', "[id^='assignCell_']", function (ev) {
        var gantt = $("#gantt").data("kendoGantt");

        var task = gantt.dataItem(this);
        var noteWorkOrderTaskId = task.workOrderTaskId;

        idNote = $(this).closest("tr").find("[id^='assignCell_']").attr('id'); //td Id

        ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.

        $.ajax({
            type:'GET',
            url: '../ajax/get_note_workordertask.php',
            async:false,
            dataType: "json",
            data: {
                workOrderTaskId: noteWorkOrderTaskId,

            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {

                    if (data['status'] == 'success') {

                        $(data["info"]).each(function(i, value) {
                            if( value.noteText) {
                                $(".modal-body #infoNote").append('<span>' + "# " + value.firstName + "# " + value.inserted  + "</br>" + value.noteText + '</span></br>');
                            }

                        });

                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
            // alert('error');
            }
        });
        $("#noteModalInfo").modal(); // use native function of Bootstrap. Display Modal.
    });

    $('#noteModalInfo').on('hidden.bs.modal', function (e) {
        $(".modal-body #infoNote").html('');
        $(".modal-body #noteText").html('');
        $(".modal-body #noteText").val('');

    })

    $('#noteModalInfo').on('show.bs.modal', function (e) {
        // Save
        $("#saveNoteInfo").click(function(ev) {
            var noteWorkOrderTaskId = parseInt(idNote.split('_')[1]); // workOrderTaskId

            ev.stopImmediatePropagation();
            var noteText = $("#noteText").val();
            noteText.trim();

            $.ajax({
                    type:'POST',
                    url: '../ajax/set_note_workordertask.php',
                    async:false,
                    dataType: "json",
                    data: {
                        noteText: noteText,
                        workOrderTaskId : noteWorkOrderTaskId,
                        personId: <?php echo intval($user->getUserId());?>
                    },

                    success: function(data, textStatus, jqXHR) {

                        if (data['status']) {
                            if (data['status'] == 'success') {

                            $('#noteModalInfo').modal('hide'); // close modal.
                            // in Note Text not empty change icon.
                            if($.trim(noteText).length > 0) {
                                $('#'+idNote).removeClass('k-i-comment');
                                $('#'+idNote).addClass('k-i-edit-tools');
                                  // Reload Gant Data
                                    gantTreeWoAjaxCall();
                                    expandGanttTree();

                                    table = $("#gantt");
                                    row = table.find('tr').eq(IndexofRowTd + 1);

                                    var bg = $(row).css('background'); // store original background
                                    row.css('background-color', '#FFDAD7'); //change element background
                                    setTimeout(function() {
                                        $(row).css('background', bg); // change it back after ...
                                    }, 10000); // 10 seconds

                                        }

                            } else {
                                alert(data['error']);
                            }
                        } else {
                            alert('error: no status');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        //alert('error');
                    }
                });

            delete idNote, noteWorkOrderTaskId;


        });
    });



    // Add/ Edit extra description.
    $( "#gantt tbody" ).on( "change", "td.k-edit-cell > input#extraDescription", function() {
        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));

        var extraDescription = item.extraDescription;
        var nodeTaskId = item.workOrderTaskId;

        $.ajax({
            url: '/ajax/add_extra_description_task.php',
            data: {
                nodeTaskId : nodeTaskId,
                extraDescription : extraDescription
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {

                //success
                //gantt.refresh();
            },
            error: function (xhr, status, error) {
            //error
            }
        })


    });

    $( "#gantt tbody" ).on( "change", "td.k-edit-cell > input#tally", function() {
        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));
        //console.log(item);
        var tally = Number(item.tally);
        var workOrderTaskId = item.workOrderTaskId;

        $.ajax({
            url: '/ajax/settally.php',
            data:{
                workOrderTaskId : workOrderTaskId,
                tally: tally
            },

            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                //success
                //gantt.refresh();
            },
            error: function (xhr, status, error) {
            //error
            }
        })


    });




    // Delete WO Team
    $("table tbody").on('click', "[id^='teamDelete_']", function (ev) {

        //gantTreeWoAjaxCall();

        idRowTeam = $(this).closest("tr").find("[id^='teamDelete_']").attr('id'); //td Id
        var teamId = parseInt(idRowTeam.split('_')[1]); // teamId



        ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.

        $.ajax({
            type:'GET',
            url: '../ajax/delete_workorder_team.php',
            async:false,
            dataType: "json",
            data: {
                teamId: teamId,
                workOrderId: <?php echo intval($workOrderId);?>

            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {

                    if (data['status'] == 'success') {

                       location.reload();
                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
            // alert('error');
            }
        });

    });


// End Document Ready
});

    </script>

  <table  style="display:none" border="0">
                <tbody>
                    <?php
                        $elementgroups = $workOrder->getWorkOrderTasksTree($error_is_db);
                        $errorTasksTree = "";
                        if ($error_is_db) {
                            $errorId = '637425229802140077';
                            $errorTasksTree = "We could not display the Workorder Tasks for this Workorder. ";
                            $logger->error2($errorId, "WorkOrder::getWorkOrderTasksTree() method failled.");
                        }
                        if ($errorTasksTree) {
                            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorTasksTree</div>";
                        }
                        unset($errorTasksTree);
                        $color = '#ffffff';
                        $maxdepth = 0;
                        foreach ($elementgroups as $elementgroup) {
                            if ($elementgroup['maxdepth'] > $maxdepth) {
                                $maxdepth = $elementgroup['maxdepth'];
                            }
                        }

                        /* For each element associated with the workOrder that has data in 'gold',
                              there is a row with a column that spans the whole table,
                              with the element name (or "General", or an "element group"), then "[b-ass]" linked to trigger an iframe that will use
                              /fb/bulkassignperson.php (passed workorderId & elementId) to bulk-assign related tasks.
                          For each element this is followed by a headers row & a set of rows that effectively make up a table
                              (though using the same HTML table)
                        */
                        foreach ($elementgroups as $elementgroup) {
                            $goldcount = 0;
                            if (isset($elementgroup['gold'])) {
                                if (is_array($elementgroup['gold'])) {
                                    $goldcount = count($elementgroup['gold']);
                                }
                            }
                            if ($goldcount) {
                                $en = '';
                                $elementId = 0;
                                if (isset($elementgroup['elementId']) && (intval($elementgroup['elementId']) == PHP_INT_MAX)) {
                                    // This is the pre-version-2020-4 case for an elementgroup with more than one element
                                    // Leaving code in place for the moment.
                                    $elementId = intval($elementgroup['elementId']);
                                    $en = $elementgroup['elementName'];
                                } else if (isset($elementgroup['elementId']) && is_string($elementgroup['elementId']) &&
                                           strpos($elementgroup['elementId'], ',') !== false )
                                {
                                    // This is the case for an elementgroup with more than one element introduced in version 2020-4.
                                    // The multiple elements are represented by a comma-separated string of elementIds
                                    $elementId = $elementgroup['elementId'];
                                    $en = $elementgroup['elementName'];
                                } else {
                                    // NOTE that this covers both individual elements and "General" (for the latter, the elementId is 0)
                                    $elementId = $elementgroup['element']->getElementId();
                                    $en = (intval($elementgroup['element']->getElementId())    ) ? $elementgroup['element']->getElementName() : 'General';
                                }
                                /* BEGIN REPLACED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100
                                $span = (intval($workOrderTaskId)) ? '10' : '9';
                                // END REPLACED 2020-09-23 JM
                                */
                                // BEGIN REPLACEMENT 2020-09-23 JM
                                $span = (intval($workOrderTaskId)) ? 12 : 11;
                                // END REPLACEMENT 2020-09-23 JM
                                $span += $maxdepth;

                                // Row with with the element name (or "General") or an elementgroup name and "[b-ass]", as described above.
                                echo '<tr>';
                                    echo '<td colspan="' . $span . '" bgcolor="#dddddd" style="font-size:125%;font-weight:bold;">' . $en .
                                         '&nbsp;[<a data-fancybox-type="iframe" id="bulkAssignPerson' . $elementId . '" class="fancyboxIframe" href="/fb/bulkassignperson.php?elementId=' .
                                         /* BEGIN REPLACED 2020-10-27 JM: This should not be an intval, it is crucial that it is passed as a string,
                                            especially when it is a comma-separated list of multiple elementIds
                                         intval($elementId) .
                                         // END REPLACED 2020-10-27 JM
                                         */
                                         // BEGIN REPLACEMENT 2020-10-27 JM
                                         urlencode($elementId) . // NOTE not intval. This can be a comma-separated string.
                                         // END REPLACEMENT 2020-10-27 JM

                                         '&workOrderId=' . intval($workOrder->getWorkOrderId()) . '">b-ass</a>]';
                                    echo '</td>';
                                echo '</tr>'."\n";

                                // Per-element headers row
                                echo '<tr>';
                                    // NOTE: this first column is present only if the user has clicked on at least one task,
                                    // so although we are calling it 'column 1' in notes below, it will be absent in some cases.
                                    if (intval($workOrderTaskId)){
                                        echo '<th>&nbsp;</th>'; /* indicator of what task was most recently clicked on */
                                    }
                ?>
                                    <th>&nbsp;</th> <?php /* column 2: icon */ ?>
                                    <th>&nbsp;</th> <?php /* person's initials */ ?>
                                    <th>&nbsp;</th> <?php /* task link */ ?>
                                    <?php /* column 5 et. seq. Note colspan in the following to account for number of levels; */ ?>
                                    <th width="100%" colspan="<?php echo $maxdepth; ?>">Desc</th>
                                    <th>Extra</th>
                                    <?php /* BEGIN ADDED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100 */ ?>
                                    <th>Time</th>
                                    <th>Tally</th>
                                    <?php /* END ADDED 2020-09-23 JM*/ ?>
                                    <th><img src="/cust/<?php  echo $customer->getShortName(); ?>/img/icons/icon_ticket_32x32.png" width="24" height="24" title="Notes"></th>
                                    <th>Status</th>

                                </tr>
                <?php
                                foreach ($elementgroup['gold'] as $gold) {
                                                if ($gold['type'] == 'real') {
            // BEGIN OUTDENT
            $wot = $gold['data']; // WorkOrderTask object.
            $level = $gold['level'];
            echo '<tr>';

            // Column 1: (no header). Marks the "current" workOrderTask. For that one row, shows a right-arrow indicator,
            // indicating what task was most recently clicked on (that is, the icon in the next column was clicked on).
            // This column is present only if the user has clicked on at least one task.
            if (intval($workOrderTaskId)) {
                if (intval($workOrderTaskId) == $wot->getWorkOrderTaskId()){
                    $workOrderTaskIdExists = true;
                    echo '<td><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_indicator_36x36.png" width="20" height="20" border="0"></td>';
                } else {
                    echo '<td>&nbsp;</td>';
                }
            }

            // Column 2 (no header). An icon linking to relevant /fb/workordertask.php page, brings that up in an iframe.
            echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '"><a data-fancybox-type="iframe" id="linkWoTask' . intval($wot->getWorkOrderTaskId()) . '"
            class="fancyboxIframe" href="/fb/workordertask.php?workOrderTaskId=' .
                intval($wot->getWorkOrderTaskId()) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="20" height="20"></a></td>';

            // Column 3 (no header). "Legacy initials" of people assigned the task, multiple lines if there are multiple people.
            // Nothing actually "legacy" about this, there are no plans to get rid of these.
            echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '">';
            $persons = $wot->getWorkOrderTaskPersons();
            foreach ($persons as $pkey => $person) {
                if ($pkey) {
                    // Not the first
                    echo '<br>';
                }
                $customerPerson = CustomerPerson::getFromPersonId($person->getPersonId());
                if ($customerPerson) {
                    echo $customerPerson->getLegacyInitials();
                }  else {
                    echo '';
                }
                //echo $person->getLegacyInitials(); // George. 2020-12-09. getLegacyInitials() was moved in CustomerPerson class
            }
            unset ($person);
            echo '</td>';

            // Column 4: (no header). If the task has a wikilink, link to it with appropriate
            // task icon, otherwise question-mark icon & no link.
            echo '<td>';
                $tt = $wot->getTask();
                $wikiLink = $tt->getWikiLink();
                $img = $tt->getIcon();
                if (strlen($img)) {
                    if (strlen($wikiLink)){
                        echo '<a id="linkWoTaskWiki' . $wot->getWorkOrderTaskId() . '" target="_blank" href="' . htmlspecialchars($tt->getWikiLink()) . '"><img src="'. getFullPathnameOfTaskIcon($img, '1595358834') . '" width="40" height="40" border="0"></a>';
                    } else {
                        echo '<img src="'. getFullPathnameOfTaskIcon($img, '1595358920') . '" width="40" height="40" border="0">';
                    }
                } else {
                    if (strlen($wikiLink)) {
                        // no icon, but still provide some way to get to the linked wiki page.
                        echo '<a target="_blank" href="' . htmlspecialchars($tt->getWikiLink()) . '">---</a>';
                    }
                }
            echo '</td>';

            // column 5 et. seq.: fill columns with blanks to get adequate indentation to reflect task levels,
            //  then give workOrderTask description
            for ($i = 0; $i < $level; ++$i) {
                echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '">&nbsp;&nbsp;&nbsp;</td>';
            }
            echo '<td  class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '" colspan="' . ($maxdepth - $level) . '" width="100%">' . $wot->getTask()->getDescription() . '</td>';

            // From this point, column numbers will differ based on how many levels there are...
            // "Extra": in the DB, this is workOrderTask.extraDescription, arbitrary text.
            echo '<td valign="middle" class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '">';
            ?>
                <table style="width:220px;border-collapse:collapse;padding:0; margin:0;" >
                    <tr>
                        <td>
                        <?php echo $wot->getExtraDescription(); ?></td>
                    </tr>
                </table><?php
            echo '</td>';

            // BEGIN ADDED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100
            $timeRows = $wot->getWorkOrderTaskTime();
            $time = 0;
            foreach ($timeRows AS $timeRow) {
                $time += $timeRow['minutes'];
            }
            echo '<td>' . ( $time ? number_format((float)$time/60, 2, '.', '') : '')  . '</td>';
            unset($time, $timeRows);

            $tally = $wot->getTally();
            if ($tally == 0) {
                $tally = '';
            }
            echo '<td>';
                // Using type="text" rather than "number" because "number" too readily gives bogus error messages for non-integers
                // Also: intially disable this so it won't be active until its handler is loaded
                echo "<input class=\"tally\" id=\"tally_" . $wot->getWorkOrderTaskId() . "\"type=\"text\" value=\"$tally\" size=\"6\" disabled/>";
            echo '</td>';
            unset($tally);
            // END ADDED 2020-09-23 JM

            // (column w/ notes icon in header): number of notes, background is different if non-zero.
            // >>>00014 Oddly, no link to the notes
            $notes = $wot->getNotes();
            $notecount = '&nbsp;';
            $ncbg = '';
            if (intval(count($notes))) {
                $notecount = count($notes);
                $ncbg = ' bgcolor="#9fff64" ';
            }
            echo '<td ' . $ncbg . ' style="text-align: center;">';
                echo $notecount;
            echo '</td>';

            // "Status": shows icon for status (just completed or not), links to function setTaskStatusId
            echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '" style="text-align:center;" id="statuscell_' . $wot->getWorkOrderTaskId() . '">';
                $active = ($wot->getTaskStatusId() == 9) ? 0 : 1;
                $newstatusid = ($wot->getTaskStatusId() == 9) ? 1 : 9;
                echo '<a id="setTaskStatus' . $wot->getWorkOrderTaskId() . '"  href="javascript:setTaskStatusId(' . $wot->getWorkOrderTaskId() . ',' . intval($newstatusid) . ')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_active_' . intval($active) . '_24x24.png" width="16" height="16" border="0"></a>';
            echo '</td>';

            /* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
            // (column w/ clock icon in header): eye icon (open or closed) for whether this task shows up in timesheets
            //
            // * Open eye => this task will show on timesheets (some tasks don't).
            // * Closed eye => this task will NOT show on timesheets.
            //
            // links to function setWorkOrderTaskMode with TIMESHEET view mode, this is a toggle, uses /ajax/cycle_womode.php.
            $viewMode = $wot->getViewMode();
            echo '<td id="modediv_' . $wot->getWorkOrderTaskId() . '_' . WOT_VIEWMODE_TIMESHEET . '" class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '"><a href="javascript:setWorkOrderTaskMode(' . intval($wot->getWorkOrderTaskId()) . ',' . intval(WOT_VIEWMODE_TIMESHEET) . ')">';
            if (intval($viewMode) & WOT_VIEWMODE_TIMESHEET){  // Martin comment: 1
                echo  '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_eye_64x64.png" width="21" height="21" border="0">';
            } else {
                echo  '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_eye_hidden_64x64.png" width="21" height="21" border="0">';
            }
            echo '</a></td>';

            // (column w/ contract icon in header): Similar to previous, for whether item is visible in contract.
            echo '<td id="modediv_' . $wot->getWorkOrderTaskId() . '_' . WOT_VIEWMODE_CONTRACT . '" class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '"><a href="javascript:setWorkOrderTaskMode(' . intval($wot->getWorkOrderTaskId()) . ',' . intval(WOT_VIEWMODE_CONTRACT) . ')">';
            if (intval($viewMode) & WOT_VIEWMODE_CONTRACT){  // Martin comment: 1
                echo  '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_eye_64x64.png" width="21" height="21" border="0">';
            } else {
                echo  '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_eye_hidden_64x64.png" width="21" height="21" border="0">';
            }
            echo '</a></td>';

            // (column w/ invoice icon in header): Similar to previous, for whether item is visible in invoice.
            echo '<td id="modediv_' . $wot->getWorkOrderTaskId() . '_' . WOT_VIEWMODE_INVOICE . '" class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '"><a href="javascript:setWorkOrderTaskMode(' . intval($wot->getWorkOrderTaskId()) . ',' . intval(WOT_VIEWMODE_INVOICE) . ')">';
            if (intval($viewMode) & WOT_VIEWMODE_INVOICE){  // Martin comment: 1
                echo  '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_eye_64x64.png" width="21" height="21" border="0">';
            } else {
                echo  '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_eye_hidden_64x64.png" width="21" height="21" border="0">';
            }
            echo '</a></td>';
            // END REMOVED 2020-10-28 JM
            */
            echo '</tr>';

            if (!isset($classes[$wot->getWorkOrderTaskId()])) {
                $classes[$wot->getWorkOrderTaskId()] = Array();
            }
            $classes[$wot->getWorkOrderTaskId()][] = ''; // JM 2019-04-18: This is a bit confusing.
                                                        // I think all that's going on here is basically membership in a set, represented
                                                        //  by the fact that this array entry exists.
                                                        // Basically, for each workOrderTaskId $wotid encountered, we set
                                                        //  $classes[$wotid][0] = ''. I'm pretty sure we can't encounter the same $wotid
                                                        //  twice, so it's just membership in a set of workOrderTasks, by id.
                                                        // >>>00014 JM: I have my doubts as to whether as of 2019-04 this is EVER meaningfully used,
                                                        //  see further code related to $classes below. Maybe it was FORMERLY meaningful.
            // END OUTDENT
                                            } // END if ($gold['type'] == 'real')
                                            else { // George 2020-12-11. Before was $gold['type'] == 'fake'
                                                $fakekey = $gold['data'];
                                                $level = $gold['level'];
                                                echo '<tr>';
                                                    if (intval($workOrderTaskId)){
                                                            echo '<td>&nbsp;</td>';
                                                    }
                                                    echo '<td>&nbsp;</td>';

                                                    echo '<td>&nbsp;</td><td>&nbsp;</td>';
                                                    for ($i = 0; $i < $level; ++$i){

                                                        echo '<td>&nbsp;&nbsp;&nbsp;</td>';

                                                    }

                                                    $task = new Task(str_replace("a","",$fakekey));
                                                    echo '<td colspan="' . ($maxdepth - $level ) . '" width="100%">' . $task->getDescription() . '</td>';
                                                    echo '<td></td>';
                                                    echo '<td>&nbsp;</td>';
                                                    echo '<td>&nbsp;</td>';
                                                echo '</tr>';
                                            }
                                        } //END foreach ($elementgroup['gold'] )
                                    } // END if ($goldcount)
                        } // END foreach ($elementgroups )

                ?>
            </tbody>
        </table>
    </div>


<!--

end tasks

                    -->

    <?php
    /* "Contract Project Description (on PDF)" section.
        Available only if user has read-write permission for contract.
        Editable text area: view and update a note.
        Form (submit button is "update notes") posts 'updatecontractNotes' back to this page.
    */

    $checkPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_RW);
    if ($checkPerm) {
    ?>
    <div class="float-container full-box clearfix">

            <div class="float-child">
                <h2 class="heading">Contract Project Description (on PDF)</h2>
                <table>
                    <tbody>
                        <form name="" id="updateContractNotesForm" method="post" action="<?php echo $workOrder->buildLink(); ?>">
                            <tr>
                                <td colspan="5">
                                    <input type="hidden" name="act" value="updatecontractNotes">
                                    <div class="textwrapper"><textarea rows="4" name="contractNotes" id="contractNotes" maxlength="2048" class="widetextarea form-control"><?php echo htmlspecialchars($workOrder->getContractNotes()); ?></textarea></div>
                                </td>
                            </tr>

                            <tr>
                                <td style="text-align:center;" colspan="5"><center></center><input type="submit"  id="updateNotes" class="btn btn-secondary btn-sm mr-auto ml-auto" value="update notes"></center></td>
                            </tr>
                        </form>
                    </tbody>
                </table>
            </div>


        <?php /* Notes passed through to the person preparing the invoice. You can edit this even if you yourself don't have Invoice permissions. */ ?>
        <div  class="float-child">
            <h2 class="heading">Notes for preparing invoice</h2>
            <table >
                <tbody>
                    <form name="" id="invoiceNotesForm" method="post" action="<?php echo $workOrder->buildLink(); ?>">
                        <tr>
                            <td colspan="5">
                                <input type="hidden" name="act" value="updatetempNote">
                                <div class="textwrapper"><textarea rows="4" name="tempNote" id="tempNote" maxlength="4096" class="widetextarea form-control"><?php echo htmlspecialchars($workOrder->getTempNote()); ?></textarea></div>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:center;" colspan="5"><center></center><input type="submit"  id="updateNotesInvoice" class="btn btn-secondary btn-sm mr-auto ml-auto" value="update notes"></center></td>
                        </tr>
                    </form>
                </tbody>
            </table>
        </div>
    </div>


    <?php } else { ?>
        <div class="full-box clearfix">
            <h2 class="heading">Notes for preparing invoice</h2>
            <table >
                <tbody>
                    <form name="" id="invoiceNotesForm" method="post" action="<?php echo $workOrder->buildLink(); ?>">
                        <tr>
                            <td colspan="5">
                                <input type="hidden" name="act" value="updatetempNote">
                                <div class="textwrapper"><textarea rows="4" name="tempNote" id="tempNote" maxlength="4096" class="widetextarea form-control"><?php echo htmlspecialchars($workOrder->getTempNote()); ?></textarea></div>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:center;" colspan="5"><center></center><input type="submit"  id="updateNotesInvoice" class="btn btn-secondary btn-sm mr-auto ml-auto" value="update notes"></center></td>
                        </tr>
                    </form>
                </tbody>
            </table>
        </div>
    <?php } ?>

        <?php /* "Job Team" section. One row per person+role. A person can appear more than once in distinct roles. */ ?>
        <div class="full-box clearfix">
            <h2 class="heading">Job Team</h2>
            <?php
                $jobteam = $job->getTeam(0, $error_is_db);

                $errorJobTeam = "";
                if ($error_is_db) {
                    $errorId = '637425253779235061';
                    $errorJobTeam = "We could not display the Job Team for this Workorder. Database Error.";
                    $logger->errorDB($errorId, "Job::getTeam() method failled.", $db);
                }
                if ($errorJobTeam) {
                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorJobTeam</div>";
                }
                unset($errorJobTeam);
            ?>
            <table id="tableTarget" class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>&nbsp;</th> <?php /* column will contain link to edit person's position. */ ?>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Position</th>
                        <th>&nbsp;</th> <?php /* email */ ?>
                    </tr>

                    <?php
                    foreach ($jobteam as $member) {
                        $person = new Person ($member['personId'], $user);
                        $cp = new CompanyPerson($member['companyPersonId']);
                        $comp = $cp->getCompany();
                        echo '<tr>';
                            // link to edit person's position, represented as an "edit" icon
                            echo '<td><a id="linkTeamCp' . $member['teamId'] . '" data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/teamcompanyperson.php?teamId=' . intval($member['teamId']) . '"><img src="/cust/' . $customer->getShortName() .
                                 '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';

                            // "Name"
                            echo '<td>' . $person->getFormattedName() . '</td>';

                            // "Company"
                            echo '<td>' . $comp->getCompanyName() . '</td>';

                            // "Position" (really should be "Role")
                            echo '<td>' . $member['name'] . '</td>';

                            // (no header). Email addresses.
                            // Display; A series of HTML A elements (link) with "mailto:" links.
                            //  Visible text for each is just "mail". Each sends mail to one of the available
                            //  email addresses; each has email subject "job-name - workorder description - SSS# job number".
                            echo '<td>';
                                $contacts = $cp->getContacts($error_is_db);

                                $errorContacts = "";
                                if ($error_is_db) {
                                    $errorId = "637425884334020498";
                                    $errorContacts = "We could not display the Contact Information. Database Error.";
                                    $logger->errorDb($errorId, "getContacts() method failed. Database error.", $db);
                                }
                                if ($errorContacts) {
                                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorContacts</div>";
                                }
                                unset($errorContacts);

                                $sorts = array('email' => array(),'phone' => array(),'location' => array());
                                foreach ($contacts as $contact) {
                                    if (($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILPERSON) || ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILCOMPANY)) {
                                        $sorts['email'][] = $contact;
                                    }
                                }
                                echo '<pre>';
                                foreach ($sorts['email'] as $email) {
                                    $string = $job->getName() . ' - ' . $workOrder->getDescription() . ' - SSS#' . $job->getNumber();
                                    echo '<a id="linkMailTo' . $member['teamId'] . '" title='. $email['dat']. ' href="mailto:' . $email['dat'] . '?subject=' . rawurlencode($string) . '">mail</a>';
                                }
                                echo '</pre>';
                            echo '</td>';
                        echo '</tr>';
                        unset($person, $cp, $comp);
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php /* "Workorder Team" section. One row per person+role. A person can appear more than once in distinct roles. */ ?>
        <div class="full-box clearfix">
            <h2 class="heading">Workorder Team</h2>
            <a data-fancybox-type="iframe" id="addWorkOrderPerson" class="btn add show_hide btn-secondary btn-sm mb-4 mt-2 fancyboxIframe" style="color:#fff; font-size:14px"  href="/fb/addworkorderperson.php?workOrderId=<?php echo $workOrder->getWorkOrderId(); ?>">Add</a>


            <table border="1" class="table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th>&nbsp;</th> <?php /* column will contain link to edit person's position. */ ?>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Position</th>
                        <th>&nbsp;</th> <?php /* column will contain link to related companyPerson page. */ ?>
                        <th>Mail</th>
                        <th style="display:none">&nbsp;</th> <?php /* active/inactive. */ ?>
                        <th>Del</th>
                    </tr>
                    <?php

                    $team = $workOrder->getTeam(0,$error_is_db);

                    $errorWorkOrderTeam = "";
                    if ($error_is_db) {
                        $errorId = '637425227749951203';
                        $errorWorkOrderTeam = "We could not display the WorkOrder Team for this Workorder. Database Error.";
                        $logger->errorDB($errorId, "WorkOrder::getTeam() method failled.", $db);
                    }
                    if ($errorWorkOrderTeam) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWorkOrderTeam</div>";
                    }


                    unset($errorWorkOrderTeam);


                    // Logic for Employees
                    $arrEmpployeeId = []; // new array of Current Employees.
                    $employeesCurrent = $customer->getEmployees(EMPLOYEEFILTER_CURRENTLYEMPLOYED); // Employees.

                    foreach ($employeesCurrent as $employee) {
                        $arrEmpployeeId[] = $employee->getUserId();
                    }


                    $query = " SELECT workOrderTaskId FROM " . DB__NEW_DATABASE . ".workOrderTask ";
                    $query .= " WHERE workOrderId = " . intval($workOrderId) . " ";


                    $arrayWorkOrderTasks = array();
                    $result = $db->query($query);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            array_push($arrayWorkOrderTasks, $row["workOrderTaskId"]);
                        }
                    }


                    $arrayWorkOrderTasks2 = implode(',', array_map('intval', $arrayWorkOrderTasks));

                    $arrayWorkOrderTasksPersons = [];
                    $query = " SELECT personId FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
                    $query .= " WHERE workOrderTaskId in ( $arrayWorkOrderTasks2 ) ";
                    $query .= " and minutes IS NOT NULL ";

                    $result = $db->query($query);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {

                            array_push($arrayWorkOrderTasksPersons, $row['personId']); // employees with time // !!!!!!!!!!! for TEAM

                        }
                    }
                    // End Logic for Employees


                    $arrExternalsIds = []; // new array of Externals.
                    $arrNewIds = [];

                    foreach ($team as $member) {
                        $person = new Person($member['personId']);
                        //if ($member['teamId'] && !in_array( $person->getPersonId(), $arrEmpployeeId)) {
                            $arrExternalsIds[] = $person->getPersonId(); //Ids External !!!!!!!!!!!  for TEAM
                        //}
                    }

                    $team1 = [];
                    foreach( $team as $teamMember) {
                        if (in_array($teamMember['personId'],$arrExternalsIds) || in_array($teamMember['personId'],$arrayWorkOrderTasksPersons)) {
                               $team1[] = $teamMember;
                        }
                    }
                    foreach ($team1 as $member) {
                        $person = new Person($member['personId']);
                        $cp = new CompanyPerson($member['companyPersonId']);

                        // Logic for Externals with ContractId
                        $arrExternalsIds = []; // new array of Externals.
                        $arrContract = []; // contract of this WO
                        $arrBillingProfileId = []; // BillingProfileIds on contracts.


                        if ($member['teamId'] && !in_array( $person->getPersonId(), $arrEmpployeeId)) {
                            $arrExternalsIds[] = $person->getPersonId(); //Ids
                        }

                        $query = " SELECT contractId FROM " . DB__NEW_DATABASE . ".contract ";
                        $query .= " WHERE workOrderId = " . intval($workOrderId) . " ";

                        $result = $db->query($query);
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                array_push($arrContract, $row["contractId"]);
                            }
                        }

                        $arrContract2 = implode(',', array_map('intval', $arrContract));

                        $query = " SELECT billingProfileId FROM " . DB__NEW_DATABASE . ".contractBillingProfile ";
                        //$query .= " WHERE contractId = " . intval($contractId) . " ";
                        $query .= " WHERE contractId in ( $arrContract2 ) ";

                        $result = $db->query($query);
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                array_push($arrBillingProfileId, $row["billingProfileId"]);
                            }
                        }



                        $arrCompanyPersonIdExternal = [];
                        $arrBillingProfileId2 = implode(',', array_map('intval', $arrBillingProfileId));

                        $query = " SELECT cp.personId FROM " . DB__NEW_DATABASE . ".billingProfile b ";
                        $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".companyPerson cp ON cp.companyPersonId = b.companyPersonId ";
                        $query .= " WHERE billingProfileId in ( $arrBillingProfileId2 ) ";

                        $result = $db->query($query);
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                               array_push($arrCompanyPersonIdExternal, $row["personId"]);
                            }
                        }

                        // End Logic for External client contractId


                        echo '<tr>';
                            // (no header): link to edit person's position, represented as an "edit" icon
                            echo '<td><a data-fancybox-type="iframe" id="linkTeamCompanyPerson' . $member['teamId'] . '" class="fancyboxIframe" href="/fb/teamcompanyperson.php?teamId=' .
                                 intval($member['teamId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';

                            // "Name"; this is a link to the person's page
                            echo '<td><a id="linkWoPerson_' . $person->getPersonId() . '" href="' . $person->buildLink() . '">' . $member['firstName'] . ' ' . $member['lastName'] . '</a></td>';

                            // "Company"
                            echo '<td>' . $member['companyName'] . '</td>';

                            // "Position" (really should be "Role")
                            echo '<td>' . $member['name'] . '</td>';

                            // (no header): '[cp]' link to related companyPerson page

                            echo '<td>[<a id="linkWoCp_' . $cp->getCompanyPersonId() . '" href="' . $cp->buildLink() . '">cp</a>]</td>';

                            echo '<td>';
                                $contacts = $cp->getContacts();

                                $sorts = array('email' => array(),'phone' => array(),'location' => array());

                                foreach ($contacts as $contact){
                                    if (($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILPERSON) || ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILCOMPANY)) {
                                        $sorts['email'][] = $contact;
                                    }
                                }
                                echo '<pre>';
                                foreach ($sorts['email'] as $email) {
                                    $string = $job->getName() . ' - ' . $workOrder->getDescription() . ' - SSS#' . $job->getNumber();
                                    echo '<a id="linkMailMember' . $cp->getCompanyPersonId() . '" title='. $email['dat'].' href="mailto:' . $email['dat'] . '?subject=' . rawurlencode($string) . '">mail</a>';
                                }
                                echo '</pre>';
                            echo '</td>';

                            // (no header) Uses a red or green circular icon to display status. Clicking that toggles via function setTeamMemberActive,
                            //  which uses AJAX to make the appropriate database update.
                            echo '<td style="display:none" align="center" id="teamactivecell_' . intval($member['teamId']) .'">';
                                $active = (intval($member['active'])) ? 1 : 0;
                                $newstatusid = ($active) ? 0 : 1;
                                echo '<a  id="teamMemberActive' . $member['teamId'] . '" href="javascript:setTeamMemberActive(' . $member['teamId'] . ',' . intval($newstatusid) .
                                     ')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_active_' . intval($active) .
                                     '_24x24.png" width="16" height="16" border="0"></a>';
                            echo '</td>';
                            echo '<td>';
                            // Delete WO Member

                            if ($member['teamId'] && !in_array( $person->getPersonId(), $arrEmpployeeId)) {

                                if(in_array( $person->getPersonId(), $arrCompanyPersonIdExternal) || $member['teamPositionId'] == 1) {

                                    echo '<button  disabled  id="teamDelete_' . intval($member['teamId']) .'"
                                    class="btn btn-secondary btn-sm" >Del</button>'; // can 't delete
                                } else {
                                    echo '<button  id="teamDelete_' . intval($member['teamId']) .'"
                                    class="btn btn-secondary btn-sm" >Del</button>';
                                }

                            }

                            echo '</td>';
                        echo '</tr>';
                    } // END foreach ($team...
                    unset ($member, $person, $cp);
                    ?>
                </tbody>
            </table>
        </div>

        <?php /* "Contracts" section. Available only if user has Read permission for contract. */
        // Prior to 2020-08-12, this required RWA permission (Read-write-add). In
        //  thinking about http://dev2.ssseng.com/workorder.php?workOrderId=11221, JM decided to
        //  show the section on just Read permssion, but require RWA for the "make this current" button.
        $checkPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_R);
        $default_contract_for_invoice = 0;
        if ($checkPerm) {
        ?>
            <div class="full-box clearfix">
        <?php
                $contract = array();
                $job = new Job($workOrder->getJobId());
                $jobName = $job->getName();


                $contracts = $workOrder->getContracts(true, $error_is_db);  // true => include uncommitted contracts
                $errorGetContracts = "";
                if ($error_is_db) { //true on query failed.
                    $errorId = "637431180301174698";
                    $errorGetContracts = "We could not display the Contracts. Database Error."; // message for User
                    $logger->errorDB($errorId, "WorkOrder::getContracts() method failed", $db);
                }

                if ($errorGetContracts) {
                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorGetContracts</div>";
                }
                unset($errorGetContracts);



                // George 2021-11-19. Only one Contract
                $contract = $workOrder->getContractWo($error_is_db);
                $errorGetContract = "";
                if ($error_is_db) { //true on query failed.
                    $errorId = "637729113963644640";
                    $errorGetContract = "We could not display the Contract. Database Error."; // message for User
                    $logger->errorDB($errorId, "WorkOrder::getContractWo() method failed", $db);
                }

                if ($errorGetContract) {
                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorGetContract</div>";
                }
                unset($errorGetContract);

                $plural = ((count($contracts) > 1) || (!count($contracts))) ? 's' : '';
        ?>

                <h2 class="heading">Contract<?php echo $plural; ?></h2>
        <?php
                // George - removed Curren Vers button. New button "Add Draft" 2021-11-18
                // "Current Ver." button leading to contract.php and passing workOrderId to get current version of contract.
                /*echo '<a class="button add show_hide"  id="linkWoContract" href="' . str_replace("/workorder/","/contract/",$workOrder->buildLink()) .
                     '">Current Ver.</a>'; */
                //var_dump($contract);
                if(!$contract && count($workOrder->getInvoices($error_is_db))==0 && count($out)>0) {
                //if($contract->getContractId() == null ) { // if no contract show Add Draft
                    echo '<a class="btn add show_hide btn-info btn-sm mb-3 mt-2 " style="color:#fff; font-size:14px"  id="linkWoContract" href="' . str_replace("/workorder/","/contract/",$workOrder->buildLink()) .
                    '">Add Draft</a>';
                }

        ?>
                <table class="table table-bordered table-striped">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Inserted</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Committer</th>

                            <th>BP</th>
                            <?php /* George removed. <th>&nbsp;</th>  "Make this current" */ ?>
                            <th>&nbsp;</th> <?php /* "Has invoice" / "Make invoice" */ ?>
                        </tr>
        <?php
                        foreach ($contracts as $ckey => $contract) { /*George 2021-11-19. Removed. Only one Contract. */
                            if($contract->getContractId() != null )  { // Check if we have a Contract.
                            //var_dump(Contract::getContractStatusName($contract->getCommitted()));
                            $billingProfiles = $contract->getBillingProfiles();
                            $name = ($jobName != $contract->getNameOverride()) ? $contract->getNameOverride() : $jobName;
                            $committed = $contract->getCommitted();

                            echo '<tr>' . "\n";
                                // "Name": from override in contract table; if none, use job name
                                // Links to load contract page for this workOrder & specified contract
                                echo '<td>[<a id="contractpdf' . intval($contract->getContractId()) . '" href="/contractpdf.php?contractId=' . intval($contract->getContractId()) . '">print</a>]&nbsp;&nbsp;[<a id="linkContract' . $contract->getContractId() . '" href="' . $contractLink . '?contractId=' . intval($contract->getContractId()) . '">' . $name . '</a>]</td>' . "\n";

                                // "Inserted" (date)
                                echo '<td>' . date('m/d/Y',strtotime($contract->getInserted())) . '</td>' . "\n";

                                // "Committed". New Status: Initial state Draft -> Review -> Committed -> Sign / Void.
                                if (Contract::getContractStatusName($contract->getCommitted())) {
                                    echo '<td>' . Contract::getContractStatusName($contract->getCommitted()) . '</td>' . "\n";
                                } else {
                                    echo '<td><i>No Status</i></td>' . "\n";
                                }

                                // "Notes" (commit notes for contract, if committed)
                                echo '<td><!-- notes -->' . $contract->getCommitNotes() . '</td>' . "\n";

                                // "Committer" (person who committed contract)
                                if ($committed) {
                                    if (intval($contract->getCommitPersonId())) {
                                        $p = new Person($contract->getCommitPersonId());
                                        echo '<td><!-- committer -->' . $p->getFormattedName() . '</td>' . "\n";
                                    } else {
                                        echo '<td><!-- committer -->&nbsp;</td>' . "\n";
                                    }
                                } else {
                                    echo '<td><!-- no committer -->&nbsp;</td>' . "\n";
                                }

                                // "BP": 'Yes' or 'No' depending on whether there is any billingProfile for this contract
                                echo '<td><!--has billing profile-->';
                                    if (count($billingProfiles)){
                                        echo 'Yes';
                                    } else {
                                        echo 'No';
                                    }
                                echo '</td>' . "\n";

                                // (no header):
                                $checkPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_RWA); // surrounding code is available with just RW permission
                                /*
                                George 2021-11-18. Removed for 2021-2 version.
                                if ($checkPerm) {
                                    // button to make this contract current (uses a downarrow icon, >>>00001 probably not the best UI,
                                    //  but at least the "alt" text should effectively give a tooltip)
                                    echo '<td ><a id="current_' . intval($contract->getContractId()) . '" href="javascript:makeCurrent(' .
                                         intval($contract->getContractId()) . ');"><img src="/cust/' . $customer->getShortName() .
                                         '/img/icons/icon_down_48x48.png" width="20" height="20" title="Make this current" alt="Make this current"></a></td>' . "\n";
                                } else {
                                    echo '<td>&nbsp;</td>';
                                }*/

                                // (no header):
                                if ($committed) {
                                    // * If invoice exists, just text "Has Inv".
                                    // * Otherwise, if this is the latest contract for this workorder and user has Admin permissions for invoices,
                                    //   then link "make inv": passes contractId to function makeInvoiceFromContract. That posts to
                                    //   /ajax/makeinvoicefromcontract.php and, on completion, reloads.

                                    $invoiceExists = false;
                                    $query = " SELECT * from " . DB__NEW_DATABASE . ".invoice ";
                                    $query .= " WHERE contractId = " . intval($contract->getContractId());

                                    $result = $db->query($query);

                                    if (!$result) {
                                        $logger->errorDb('637425994616215104', "Hard DB error", $db);
                                    } else {
                                        if ($result->num_rows > 0) {
                                            $invoiceExists = true;
                                        }
                                    }

                                    if ($invoiceExists) {
                                        echo '<td>Has Inv</td>' . "\n";
                                    } else {
                                        $ckey = 0;
                                        if (($ckey == count($contracts) - 1)) {
                                            $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);

                                            if ($checkPerm && intval($contract->getCommitted()) == 4) {
                                                $default_contract_for_invoice = $contract->getContractId();
                                                echo '<td >[<a id="makeinvoice_' . intval($contract->getContractId()) . '"  href="javascript:makeInvoiceFromContract(' . intval($contract->getContractId()) . ');">make inv</a>]</td>' . "\n";
                                            } else {
                                                echo '<td>&nbsp;</td>' . "\n";
                                            }
                                        } else {
                                            echo '<td>&nbsp;</td>' . "\n";
                                        }
                                    }
                                } else {
                                    // Not committed, so cannot invoice
                                    echo '<td>&nbsp;</td>' . "\n";
                                }
                            echo '</tr>' . "\n";
                        } // END if ( contract )
                    }
        ?>
                    </tbody>
                </table>
                </div>

<?php } // END requires 'PERM_CONTRACT', PERMLEVEL_RW

        /* "Invoices" section . Available only if user has RWA permission for invoice. */
        $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_RWA);
        if ($checkPerm) {
?>
            <div class="full-box clearfix">
<?php
                $job = new Job($workOrder->getJobId());
                $jobName = $job->getName();

                $invoices = $workOrder->getInvoices($error_is_db);
                $errorInvoices = '';
                if($error_is_db) { //true on query failed.
                    $errorId = '637429426115106628';
                    $errorInvoices = "We could not display the Invoices. Database Error."; // message for User
                    $logger->errorDB($errorId, "WorkOrder::getInvoices() method failed", $db);
                }
                if ($errorInvoices) {
                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorInvoices</div>";
                }
                unset($errorInvoices);

                $plural = ((count($invoices) > 1) || (!count($invoices))) ? 's' : '';
?>
                <h2 class="heading">Invoice<?php echo $plural; ?></h2>
<?php
                // If user has admin-level invoice permission,
                //  "Make Inv. (No Contract)" button.
                //  Calls function newInvoice, which posts workOrderId to /ajax/newinvoice.php and reloads on success.
                $error_is_db = false;
                $tasksOutOfContract=getOutOfContractData($workOrderId, $error_is_db);
                $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);
                if ($checkPerm) {
                    //  (Make invoice (no contract) button should have some fail safe when a contract exists)
                    // Recalculate $contracts here for the unlikely case where someone has Invoice permissions but not Contract permissions.
                    $contracts = $workOrder->getContracts(true);   // true => include uncommitted contracts
                    if(count($out)>0){
                        if (count($contracts)) {

                            if (!checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_R)) {
                                // User can see this, but can't see contracts. Not a good combination of permissions
                                echo '<span style="color:red">Bad system configuration: you have permission to add invoices, but not to see contracts. ' ;
                                echo 'Please talk to an administrator.</span>';
                            }
                            else if ($default_contract_for_invoice) {
                                echo '<a id="linkMakeInv" class="btn btn-info btn-sm mb-2 mt-2 invButton"  '.
                                    'href="javascript:makeInvoiceFromContract(' . $default_contract_for_invoice . ');">Make Inv. From Contract</a>';
                            } else if(count($tasksOutOfContract)>0) {
                                echo '<a id="linkMakeNewInv" class="btn btn-info btn-sm mb-2 mt-2 invButton"  href="javascript:makeInvoiceFromInternalTasks();">Make Inv. (Internal Tasks)</a>';
                            }
                        } else {
                            // There is no contract for this workorder
                            echo '<a id="linkMakeNewInv" class="btn btn-info btn-sm mb-2 mt-2 invButton"  href="javascript:newInvoice();">Make Inv. (No Contract)</a>';
                        }
                    }

                }
    ?>
                <table class="table table-bordered table-striped" border="0">
                    <tbody>
                        <tr>
                            <th>Name [Id]</th>
                            <th>Inserted</th>
                            <th>Status</th>
                            <th>Extra</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>&nbsp;</th> <?php /* time since status was inserted; click to show history of invoice */ ?>
                        </tr>
                        <?php
                            foreach ($invoices as $ikey => $invoice) {
                                $name = ($jobName != $invoice->getNameOverride()) ? $invoice->getNameOverride() : $jobName;
                                $name = trim($name);
                                if (!strlen($name)){
                                   $name = '____';
                                }
                                echo '<tr>';
                                    // "Name": invoice name, normally from override but can fall back to job name; links to page for invoice.
                                    echo '<td>[<a id="printInvPdf' . intval($invoice->getInvoiceId()) . '" href="/invoicepdf.php?invoiceId=' . intval($invoice->getInvoiceId()) . '">print</a>]&nbsp;&nbsp;<a
                                    id="linkInvName' . intval($invoice->getInvoiceId()) . '"href="' .
                                         $invoice->buildLink() . '">' . $name . ' [' . $invoice->getInvoiceId() . ']</a></td>';

                                    // "Inserted" (date)
                                    echo '<td>' . date('m/d/Y',strtotime($invoice->getInserted())) . '</td>';

                                    // "Status": status name for invoice status; this is in an HTML SELECT element,
                                    //  first option is basically "no status"; should initially show correct
                                    //  status, and changing this opens a dialog to provide whatever supplemental
                                    //  information that status may need (e.g. which EOR if it is waiting for an EOR).
                                    $oldStatus = $invoice->getInvoiceStatusId();
                                    echo '<td>';
                                        echo '<select id="newStatusInv' . $invoice->getInvoiceId() . '" class="newstatus form-control" ' .
                                             'data-iid="' . $invoice->getInvoiceId() . '" ' .
                                             'name="invoiceId_' . $invoice->getInvoiceId() . '"' .
                                             'data-oldstatus = "' . $oldStatus . '" ' .
                                             ' disabled ' . // disable until document ready
                                             '><option value="0">-- choose status -- </option>';
                                            foreach ($invoiceStatusDataArray as $invoiceStatusData) {
                                                $selected = ($invoiceStatusData['invoiceStatusId'] == $oldStatus) ? ' selected ' : '';
                                                echo '<option value="' . $invoiceStatusData['invoiceStatusId'] . '" ' . $selected . '>' . $invoiceStatusData['statusName'] . '</option>';
                                            }
                                            unset($selected);
                                        echo '</select>';
                                    echo '</td>';

                                    // "Extra": show associated customerPersons
                                    $needsApproval = $invoice->getInvoiceStatusId() == Invoice::getInvoiceStatusIdFromUniqueName('needslookoverbyeors');
                                    echo '<td class="extra' . ($needsApproval ? ' editable' : '') . '">';
                                        $customerPersonIds = $invoice->getStatusCustomerPersonIds();
                                        if ($customerPersonIds) {
                                            echo '<table border="0" cellpadding="0" cellspacing="0">';
                                                foreach ($customerPersonIds AS $customerPersonId) {
                                                    $customerPerson = new CustomerPerson($customerPersonId);
                                                    $initials = $customerPerson->getLegacyInitials();
                                                    if (!$initials) {
                                                        $initials = '???';
                                                        $logger->error2('1590512720', "Bad initials for customerPersonId $customerPersonId");
                                                    }
                                                    echo '<tr><td valign="top">&gt;</td><td valign="top">' .
                                                        '<span class="customerPerson" data-customerpersonid="' . $customerPersonId . '">' . $initials . '</span>' .
                                                        '</td></tr>';
                                                }
                                                unset($customerPerson, $initials);
                                            echo '</table>';
                                        } else if ($needsApproval) {
                                            echo '---';
                                        }
                                    echo '</td>';

                                    // "Total"
                                    echo '<td><span style="float:right">' . $invoice->getTriggerTotal() . '</span></td>';

                                    // "Paid"
                                    $payments = $invoice->getPayments();
                                    $totalPayments = 0;
                                    echo "\n<!-- " . count($payments) . " payments -->\n";
                                    foreach ($payments AS $payment) {
                                        $totalPayments += $payment['amount'];
                                    }
                                    echo '<td><span style="float:right">' . number_format($totalPayments, 2) . '</span></td>';

                                    // (no header): Time since status was inserted in days & hours; hover over to expand & show history of invoice
                                    $dateTimeStatusInserted = $invoice->getStatusInserted();
                                    if ($dateTimeStatusInserted) {
                                        $dateTimeNow = new DateTime;
                                        $interval = $dateTimeStatusInserted->diff($dateTimeNow);

                                        $statusAge = $interval->format('%dd %hh');

                                        // Reworked 2020-05-28 JM: Ron, Damon, & Tawny say they'd prefer this behave like a fancybox
                                        //  rather than hover help
                                        echo '<td name="' . $invoice->getInvoiceId() . '" nowrap>' .
                                            '<a id="linkInvStatus' . $invoice->getInvoiceId() . '" style="float:right" data-fancybox-type="iframe" class="fancyboxIframe" ' .
                                                'href="/ajax/invoicestatus.php?invoiceId=' . $invoice->getInvoiceId() . '">' . $statusAge . '</a>' .
                                            '</td>';
                                    } else {
                                        echo '<td nowrap>&nbsp;</td>';
                                    }
                                echo '</tr>';
                            } // END foreach ($invoices...
                            unset($ikey, $invoice, $name, $needsApproval, $customerPersonIds, $oldStatus, $payments, $totalPayments, $dateTimeStatusInserted,
                                $dateTimeNow, $interval, $statusAge);

                            // Clicking on 'extra' is like setting same status again: you can change the 'extra'.
                            ?>
                            <script>
                            $(document).ready(function() {
                                $('td.extra.editable').click(function() {
                                    var $this = $(this);
                                    // simulate a change of status (to the same status) so user can edit 'extra'
                                    $this.closest('tr').find('select.newstatus').change();
                                });
                            });
                            </script>
                        <?php /*  </form> REMOVED 2020-09-23 JM, I don't see any open FORM that this closes */ ?>
                    </tbody>
                </table>
            </div>
<?php }  // END requires 'PERM_INVOICE', PERMLEVEL_RWA

        /* "Notes" section: As on several other pages, recent notes are shown by default (using /iframe/recentnotes.php),
                  all notes available by a call to bring up /fb/notes.php in an iframe. */ ?>
        <div class="full-box clearfix">
            <h2 class="heading">NOTES</h2>
            <iframe width="100%" src="/iframe/recentnotes.php?workOrderId=<?php echo $workOrder->getWorkOrderId(); ?>"></iframe>
            <br/>
            <a data-fancybox-type="iframe" id="workorderNotes" class="fancyboxIframe" href="/fb/notes.php?workOrderId=<?php echo $workOrder->getWorkOrderId(); ?>">See All Notes</a>
            <br /><br />
            <table>
                <tbody>
                    <tr>
                        <th>Name</th>
                    </tr>
                </tbody>
            </table>
        </div>

<?php
        // BEGIN ADDED 2020-09-24 JM
        echo '<br /><p>Attached files:</p>';

        $files = array();
        $query =  "SELECT * FROM " . DB__NEW_DATABASE . ".workOrderFile ";
        $query .= "WHERE workOrderId = " . $workOrderId . ";";

        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $files[] = $row;
            }
        } else {
            $logger->errorDb('1600983959', 'Hard DB error', $db);
        }

        // Separate table just to contain iframe "imageframe" (see handling
        //  of images below to make sense of this)
        if (count($files)) {
            echo '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
                echo '<tr>';
                    echo '<td width="100%" height="250" colspan="8">';
                        echo '<iframe name="imageframe" id="imageframe" style="display: block; width: 100%; height: 100%; border: none;"></iframe>';
                    echo '</td>';
                echo '</tr>' . "\n";
            echo '</table>' . "\n";
        }

        /* JM 2020-09-14: workorder.php might be invoked either as DOMAIN/workorder.php&workorderId=nnn or
           as DOMAIN/workorder/nnn. The relative path to various PHP will be different for the two, so we introduce $domain*/
        $domain = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);

        echo '<table border="0" cellpadding="5" cellspacing="0">' . "\n";
            $ok = array('png', 'jpg', 'jpeg', 'gif');
            foreach ($files as $file) {
                $explodedFilename = explode('.', $file['fileName']);
                $fileType = strtolower(end($explodedFilename));
                echo '<tr>';
                echo '<td>[<a id="delWoFile' . intval($file['workOrderFileId']) . '"  href="' . $domain . '/workorder.php?workOrderId=' . $workOrderId . '&act=delworkorderfile&workOrderFileId=' . intval($file['workOrderFileId']) . '">del</a>]</td>';
                echo '<td>' . $file['origFileName'] . '</td>';
                if (in_array($fileType, $ok)) {
                    echo '<td><a target="imageframe" id="getkWoFile' . intval($file['workOrderFileId']) . '" href="' . $domain . '/workorderfile_get.php?f=' . rawurlencode($file['fileName']) . '">';
                        echo '<img src="' . $domain . '/workorderfile_get.php?f=' . rawurlencode($file['fileName']) . '" style="max-width:160px; max-height:80px;">';
                    echo '</a></td>' . "\n";
                } else {
                    echo '<td>[<a id="clickWoFile' . intval($file['workOrderFileId']) . '" target="imageframe" href="' . $domain . '/workorderfile_get.php?f=' . rawurlencode($file['fileName']) . '">click</a>]</td>' . "\n";
                }
                echo '</tr>' . "\n";
            }
        echo '</table>' . "\n";

        // BEGIN DROPZONE code
        // Upload a file associated with the workOrder.
        ?>
        <script src="/js/dropzone.js?v=1524508426"></script>
        <link rel="stylesheet" href="/cust/<?= $customer->getShortName() ?>/css/dropzone.css?v=1524508426" />;
        <script>
        {
            // This script must run BEFORE dropzone uploadworkorderfile is instantiated.
            // See https://www.dropzonejs.com/#configuration-options
            window.Dropzone.options.uploadworkorderfile = {
                uploadMultiple:false,
                maxFiles:1,
                autoProcessQueue:true,
                maxFilesize: 45, // MB
                clickable: false,
                addRemoveLinks : true,
                acceptedFiles : "application/pdf,.pdf,.png,.jpg,.jpeg",
                init: function() {
                    // >>>00001 I'm not at all sure what is up with 'bind' here; I've left it as it was - JM
                    this.on("error", function(file, errorMessage) {
                        alert(errorMessage); // added 2020-02-20 JM, >>>00002 maybe alert is not exactly what we should do.
                        setTimeout(this.removeFile.bind(this, file), 3000);
                    }.bind(this)
                    );

                    this.on('complete', function () {
                        setTimeout(function(){ window.location.reload(false); }, 2000);
                    }.bind(this)
                    );

                    this.on("success", function(file) {
                        setTimeout(this.removeFile.bind(this, file), 1000);
                    }.bind(this)
                    );
                }
            };
        }
        </script>
        <div class="drop-area">
            <form id="uploadworkorderfile" class="dropzone" action="<?= $domain ?>/workorderfile_upload.php?workOrderId=<?= $workOrderId ?>">
                <h2 class="heading"></h2>
                <div id="dropzone">
                </div>
            </form>
        </div>
        <br /><br />
        <?php
        // END DROPZONE
        // END ADDED 2020-09-24 JM

    /* ======================
       Work Order time summary section: time summary for a single workOrder.
       Longtime users may know this as the "TXN" section.
       Available only if user has admin-level invoice permission.

       Basically, this gives the customer (as of 2020-01, always SSS) a way to
       eyeball how well they did in business terms for a particular workOrder.

       We provide an easy way to hide this, because it isn't something they'd always want someone
       to be able to see "over their shoulder". Hidden by default.

       Moved out to an include file 2020-01-29 JM

    */
    insertWorkOrderTimeSummary($workOrder, $elementgroups);
?>

    </div>
</div>

<?php
/*  For the purpose of being able to change the status of an invoice associated with the workorder, forms (initially not visible),
    one for each top-level invoice status. The relevant form becomes visible (pops up in a jQuery dialog) by changing status in the
    SELECT mechanism of the invoice section above. For each form:
        * HTML id is "status-invoiceStatusId"
        * form title is statusName
        * hidden invoiceId initially blank, set when changing status in the SELECT mechanism of the invoice section above.
        * hidden invoiceStatusId matches the status in question
        * (text): "This is for statusName"
        * a checkbox for each possible person to assign this to, with name="customerPersonIds[]", using the HTML ability to pass an array
        * a TEXTAREA to write a note to submit with the status
        * submit button calls function changeStatusNew, which in turn calls /ajax/setinvoicestatus.php to set a new status.
*/
// Get everyone who is allowed to approve invoices:
$invoiceApproverCustomerPersonIds = $customer->getInvoiceApprovers();
$noApprovers = false;
if ($invoiceApproverCustomerPersonIds===false) {
    $logger->error2('1590511966', 'Hard error getting invoiceApproverCustomerPersonIds');
    $noApprovers = true; //Hard DB error.
}

$customerPersonIds = Array();

foreach ($invoiceStatusDataArray as $invoiceStatus) {
?>
    <div class="hide-answer" id="status-<?php echo $invoiceStatus['invoiceStatusId']; ?>" title="<?php echo htmlspecialchars($invoiceStatus['statusName']); ?>">
        <form name="" action="" method="post">
            <input type="hidden" name="invoiceId" value="">
            <input type="hidden" name="invoiceStatusId" value="<?php echo intval($invoiceStatus['invoiceStatusId']);?>">
            <p>This is for "<?php echo htmlspecialchars($invoiceStatus['statusName']); ?>"</p>
<?php
            if ($invoiceStatus['uniqueName'] == 'needslookoverbyeors') {
                $eors = $workOrder->getTeamPosition(TEAM_POS_ID_EOR, false); // These are associative arrays corresponding to rows in DB table Team
                                                                     // Of particular interest is $eors[$i]['companyPersonId']
                $eorApprovers = array();

                foreach ($eors as $eor) {
                    $companyPerson = new CompanyPerson($eor['companyPersonId']);
                    $person = $companyPerson->getPerson();
                    $personId = $person->getPersonId();
                    $customerPerson = $customer->getCustomerPersonFromPersonId($personId);
                    foreach ($invoiceApproverCustomerPersonIds as $invoiceApproverCustomerPersonId) {
                        if ($customerPerson == $invoiceApproverCustomerPersonId) {
                            $eorApprovers[] = $customerPerson;
                        }
                    }
                }
                unset($eor, $companyPerson, $person, $personId, $customerPerson, $invoiceApproverCustomerPersonId);

                echo '<table>';
                if ($noApprovers) { // Hard error DB. We could not display the people having permission to approve invoices.
                    echo "<div class=\"alert alert-danger\" role=\"alert\" style=\"color:red\">We could not display the Managers. Database error.</div>";
                } else {
                    foreach ($invoiceApproverCustomerPersonIds AS $customerPersonId) {
                        $customerPerson = new CustomerPerson($customerPersonId);
                        $initials = $customerPerson->getLegacyInitials();
                        $isEorApprover = in_array($customerPersonId , $eorApprovers);
                        if (!$initials) {
                            $initials = '???';
                            $logger->error2('1590512620', "Bad initials for customerPersonId $customerPersonId");
                        }
                        echo '<tr>';
                        // In the following, 'data-checkedwhenchanging' makes it so that we can use this same dialog
                        // for modifying the customerPersonIds associated with the current status, and then later restore
                        // the usual 'checked' values so this same dialog can be used to set a new status & give default values.
                        echo '<td><input type="checkbox" id="customerPersonIds' . $customerPersonId . '" name="customerPersonIds[]" value="' . $customerPersonId . '"' .
                                    ' data-checkedwhenchanging="' . ($isEorApprover ? 'true' : 'false') . '"' .
                                    ($isEorApprover ? ' checked' : '') .
                                    '></td>';
                            echo '<td>' . $initials . '</td>';
                        echo '</tr>';
                    }
                }
                unset($customerPerson, $initials, $isEorApprover);
                echo '</table>';

                unset($eorApprovers);
            }
?>
            <br /><br />
            Note:
            <textarea cols="20" rows="3" class="form-control" maxlength="255" name="note"></textarea>
            <br />
            <input type="button" class="btn btn-secondary btn-sm mr-auto ml-auto" id="submitStatusNew'<?php echo $invoiceStatus['invoiceStatusId']?>'" value="Submit" onclick="changeStatusNew('status-<?php echo $invoiceStatus['invoiceStatusId']; ?>')"/>
        </form>
    </div>
<?php
}
unset($noApprovers);
?>


<?php

// >>>00014 The following styles appear to be intended to give an appropriate background color
// to some cells in the "Tasks" section, but I (JM) believe that as of 2019-04 this doesn't work,
// unless I'm wrong and the same workOrderTask can appear multiple times in the Tasks table.
// As far as I can tell, for a given workOrderTaskId ($ckey below, >>>00012 not mnemonic at all),
// we always have $classes[$ckey][0] == '' (empty string), and unless  we encountered the same workOrderTaskId a second time,
// there is no $classes[$ckey][1]. $class below is $classes[$ckey], so count($class) would always be 1,
// and we don't assign a background-color unless count($class) > 1.
// Also: there seem to be a finite number of colors here, and we don't circle around, so if this
// does something meaningful, it can run out of colors.
// >>>00001: Definitely needs further study. Either this does something, and we need to understand it
// or it doesn't and we should tear it out.

// Martin comment: got rid of blue colors for now because of the "eye" icon is blue for the VIEW MODE

$colors = array('AntiqueWhite', 'Chartreuse',
        'DarkGoldenRod', 'DarkGrey',
        'DarkOrange', 'DarkSalmon', 'DarkSeaGreen', 'Gold',
        'HotPink', 'OrangeRed', 'Plum', 'YellowGreen');

$used = 0;
echo '<style>';
    // This appears to be just about membership in a set.
    foreach ($classes as $ckey => $class) {
        if (count($class) > 1) {
?>
            .workOrderTaskId_<?php echo $ckey; ?>{
                background-color: <?php echo $colors[$used]; ?>;
            }
<?php
            $used++;
        }
    }
echo '</style>';
?>
<script>
    var sizes = new Array();
<?php



// >>>00001 The following looks like commented-out code was meant to scroll to any "current" workOrderTask after a quarter-second.
// I (JM) suspect this was dropped not because it wasn't wanted (>>>00042 talk to Ron or Damon) but because Martin couldn't make it work well.
// If it ISN'T wanted, let's kill this code. If it is: this needs to be at least inside $(function()) to wait for document ready,
//  and maybe even needs a stronger condition than that (wait for certain things to load, if they affect layout).
if (intval($workOrderTaskId) && $workOrderTaskIdExists) {
?>
    $('html,body').animate({
        //	scrollTop: $("#row_<?php echo intval($workOrderTaskId); ?>").offset().top - 100 // Commented out by Martin before 2019
    }, 250);
<?php
}

/* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
// For a given workOrderTask, turn on or off whether it is visible in a timesheet, contract, or invoice.
// INPUT workOrderTaskId
// INPUT viewMode: one of WOT_VIEWMODE_TIMESHEET, WOT_VIEWMODE_CONTRACT, WOT_VIEWMODE_INVOICE
//
// Temporarily mark the cell that shows this value with the ajax_loader.gif; POST to /ajax/cycle_womode.php
// to toggle the value in the DB; on successful return, show the icon for the new value.
// Alert on failure.
?>
var setWorkOrderTaskMode = function(workOrderTaskId, viewMode) {
    var formData = "viewMode=" + escape(viewMode) + "&workOrderTaskId=" + escape(workOrderTaskId);
    var cell = document.getElementById("modediv_" + workOrderTaskId + '_' + viewMode);
    cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="20" height="20" border="0">';

    $.ajax({
        url: '/ajax/cycle_womode.php',
        data:formData,
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { // [T000016]
                    var	html =   '<a href="javascript:setWorkOrderTaskMode(' + escape(workOrderTaskId) + ',' + escape(viewMode) + ')">';

                    if (data['state'] == 'on'){
                        html += '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_eye_64x64.png" width="21" height="21" border="0">';
                    }
                    if (data['state'] == 'off'){
                        html += '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_eye_hidden_64x64.png" width="21" height="21" border="0">';
                    }

                    html += '</a>';

                    cell.innerHTML = html;
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
} // END function setWorkOrderTaskMode
<?php
// END REMOVED 2020-10-28 JM
*/

// Martin comment: new new
// Set a member of a team as active or inactive.
// INPUT teamId: primary key into DB table Team, identifies team + customerPerson + role
// INPUT active: quasi-boolean (0 or 1)
//
// Temporarily mark the cell that shows this value with the ajax_loader.gif; POST to /ajax/woteam_active_toggle.php
// to toggle the value in the DB; on successful return, show the icon for the new value.
// Alert on failure.
?>
var setTeamMemberActive = function(teamId, active) {
    var  formData = "teamId=" + escape(teamId) + "&active=" + escape(active);
    var cell = document.getElementById("teamactivecell_" + teamId);
    cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';

    $.ajax({
        url: '/ajax/woteam_active_toggle.php',
        data:formData,
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { // [T000016]
                    var html = '<td align="center" id="teamactivecell_' + teamId + '"><a href="javascript:setTeamMemberActive(' + teamId + ',' +
                               data['linkActive'] + ')"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_active_' +
                               data['active'] + '_24x24.png" width="16" height="16" border="0"></a></td>';
                    cell.innerHTML = html;
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
} // END function setTeamMemberActive

<?php /* Action when user selects a different status for an invoice.
*/ ?>
$(document).ready(function() {
    $(".newstatus").prop('disabled', false); // enable these on document ready
    $(".newstatus").change(function() {
        $(".hide-answer").dialog("close");
        var sel = $(this).val();
        $("#status-" + sel + " input[name=invoiceId]").val($(this).data('iid'));
        $("#status-" + sel).dialog('open');
    });

});
</script>

<?php
// BEGIN ADDED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100
// Action when user changes a tally.
?>
<script>
$(function() {
    $('input.tally').on('change', function() {
        let $this = $(this);
        let tally = $this.val();
        if (tally.trim() == '') {
            tally = 0;
        }
        if (isNaN(tally)) {
            $this.addClass('bad-value');
        } else {
            $this.removeClass('bad-value');
            let id = $this.attr('id');
            let workOrderTaskId = id.split('_')[1];
            $.ajax({
                url: '/ajax/settally.php',
                data:{
                    workOrderTaskId : workOrderTaskId,
                    tally: tally
                },
                async:true,
                type:'post',
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') { // [T000016]
                            let newtally = '';
                            if (data['tally']) {
                                newtally = data['tally'];
                            }
                            if (newtally == 0) {
                                newtally = '';
                            }
                            $this.val(newtally);

                            // flash a confirmation
                            $this.addClass('confirmed');
                            setTimeout( function() {
                                $this.removeClass('confirmed');
                            }, 300);
                        } else {
                            alert('error 1 saving tally, please contact administrator or developer to check the log');
                        }
                    } else {
                        alert('error 2 saving tally, please contact administrator or developer to check the log');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error 3 saving tally, please contact administrator or developer to check the log');
                }
            });
        }
    });
    $('input.tally').prop('disabled', false);
})


</script>
<?php
// END ADDED 2020-09-23 JM
?>

<?php /* Stripped-down dialog to display up-to-the-moment status information for an invoice.
         Filled in and opened when we need it. */
?>
<div id="expanddialog">
</div>
  <style>

    .fancybox-inner {
       /*height:400px!important;*/
    }
    .fancybox-skin {

        left:-0px!important;
        padding:0px!important;
    }
    .fancybox-iframe {
        -moz-border-radius:8px 8px 8px 8px;
        border-radius:8px 8px 8px 8px;
        -webkit-border-radius:8px 8px 8px 8px;
    }
    /* Gantt */


    #gantt .k-grid-header
    {
    padding: 0 !important;
    }

    #gantt .k-grid-content
    {
    overflow-y: visible;
    }
    .k-gantt-header  {
        display:none;
    }
    .k-gantt-footer {
        display:none;
    }
    .k-gantt-timeline{
        display:none;
    }

    .k-grid tbody button.k-button {
        min-width: 20px;
        border: 0px solid #fff;
        background: transparent;
    }

    .k-grid .k-button {
        padding-left: calc(0.61428571em + 6px);
    }

    #treeview-telerik-wo {
        display:none!important;
    }

    /* Header padding */
    .k-gantt-treelist .k-grid-header tr {
        height: calc(2.8571428571em + 4px);
        vertical-align: bottom;
    }

    .k-gantt .k-treelist .k-grid-header .k-header {
        padding-left: calc(0.8571428571em + 6px);
    }
    /* Header padding */

   .k-command-cell>.k-button, .k-edit-cell>.k-textbox, .k-edit-cell>.k-widget, .k-grid-edit-row td>.k-textbox, .k-grid-edit-row td>.k-widget {
        vertical-align: middle;
        background-color: #fff;
    }

    .k-scheduler-timelineWeekview > tbody > tr:nth-child(1) .k-scheduler-table tr:nth-child(2) {
        display: none!important;
    }

    /* Extradescription in two rows */
    .k-grid  td {
        height: auto;
        white-space: normal;
    }
    .no-scrollbar .k-grid-header
    {
    padding: 0 !important;
    }

   /* #gantt > div.k-gantt-content > div.k-gantt-treelist > div > div.k-grid-header > div > table {
        min-width: 1299.6px!important;
    }
    .k-grid-header-wrap {
        max-width: 1299.6px!important;
    } */
    .k-grid-header {
        background-color: grey;
    }

    .no-scrollbar .k-grid-content
    {
        overflow-y: visible;
    }


    /* Hide the Horizonatal bar scroll */
    .k-gantt .k-treelist .k-grid-content {
        overflow-y: hidden;
        overflow-x: hidden;
    }

    /* Hide the Vertical bar */
    .k-gantt .k-splitbar {
        display: none;
    }
    .k-gantt-treelist .k-i-expand,
    .k-gantt-treelist .k-i-collapse {
        cursor: pointer;
    }
    /* Horizontal Scroll*/



    .k-gantt .k-grid-content {
        overflow-y: visible !important;


    }

    .k-gantt .k-gantt-layout {
        height: 140% !important;

    }
    .k-auto-scrollable {
        height:499px!important;

    }
    .k-grid-content table, .k-grid-content-locked table {
        min-width: 701.513px!important;
    }

    .statusWoTaskColor {
        background-color: #4ca807; padding: 1px 10px;
    }

    .statusWoTaskColor2 {
        background-color: #c4370a; padding: 1px 10px;
    }
    .statusWoTaskColorNone {
        display:none;
    }
    .tree-list-img {
        width: 40px;
        height: 40px;
    }

    .k-i-comment, .k-i-edit-tools {

        cursor:pointer;
    }
    .extraDescriptionUpdate {
        border: 2px solid #000;
    }

    #gantt  > table {
        max-width: 1298px!important;
    }

    /* Contract and Invoice Notes */
    .float-container {
    border: 0.1px solid #fff;
    padding-top:10px;

    }

    .float-child {
        width: 50%;
        float: left;
        padding: 0px;
        border: 1px solid #c6c6c6;
    }
    .tallyClass {
    height: auto!important;
    padding: 8px;
    width: auto!important;
    }
    .formClass {
        height: 20px;
        padding: 8px;
        width:auto;
    }
    .formClassDesc {
        height:auto;
        width:auto;
    }

    .invButton {
        color:#fff!important;
        font-size:14px;
        float:right;
    }

  </style>
<style>
.linksjob:visited{
    color: purple;
    background-color: #fefefe;

}

</style>
<script >
    function closeMapDialog(){
        $('#jobmap').animate({left: "-2000px"}, 500);
        //window.removeEventListener("contextmenu", e => e.preventDefault(), true);
    }

</script>

<div id="jobmap" style="position: absolute; top: 50px; left: -2000px; width: 80%; height: 800px; border-radius: 20px; box-shadow: 5px 5px; background-color: rgb(240, 240, 240); z-index: 1000; opacity: 1; padding-right:20px">
    <div class="container-fluid mt-1">
        <h5 class="text-right"><span style="cursor: pointer; width: 30px; height: 30px" onclick='closeMapDialog()'>&times;</span></h5>
        <h2 id="jobs4elementtitle"></h2>
        <div class="row">
            <div class="col-6" id="joblist" style="height: 700px; overflow-y: auto">
            </div>

            <div class="col-6 pr-1" id="map" style="height: 700px; width: 600px!important">
                
            </div>

        </div>
    </div>  
</div>

<script>

//    const noRightClick = document.getElementById("jobmap");
//    noRightClick.addEventListener("contextmenu", e => e.preventDefault());
/*    $('#jobmap').mousedown(function(){
        var e = window.event;
        e.preventDefault();
        if (e.which) rightclick = (e.which == 3);
        else if (e.button) rightclick = (e.button == 2);
        if(rightclick){
            $('#jobmap').animate({left: "-2000px"}, 500);
            window.removeEventListener("contextmenu", e => e.preventDefault(), true);
        }
    });
    */
    var map;
    var markers=[];
    const iconBase = "<?php echo REQUEST_SCHEME . '://' . HTTP_HOST . '/cust/ssseng/img/';?>";
    var iconRed={
        url: iconBase + 'red.png',
        scaledSize: new google.maps.Size(20, 20),
        origin: new google.maps.Point(0,0), 
        anchor: new google.maps.Point(0, 0) 
    };
    var iconGreen={
        url: iconBase + 'green.png',
        scaledSize: new google.maps.Size(20, 20),
        origin: new google.maps.Point(0,0), 
        anchor: new google.maps.Point(0, 0) 
    };
    function showMarker(ev){
        ev.preventDefault();
        var jobNr=$(this).attr('tag');
        console.log(jobNr);
    }
    function unShowMarker(ev){
        var jobNr=$(this).attr('tag');
        console.log(jobNr);
    }
    function showJobElement(elementName){
        $.ajax({
            url: '/ajax/getElementJobs.php',
            data: {
                elementName: elementName,
            },
            async:false,
            type:'get',
            success: function(data, textStatus, jqXHR) {
                // create Gantt Tree
                $('#jobs4elementtitle').html("Jobs for element " + elementName);
                var myLatlng = new google.maps.LatLng(47.6129428,-122.3024896);
                var mapOptions = {
                    zoom: 10,
                    center: myLatlng,
                    options: {
                        gestureHandling: 'greedy'
                    }
                }
                map = new google.maps.Map(document.getElementById('map'), mapOptions);
                var out='<div class="row border-bottom-1 border-success">';
                data.data.forEach(function(value){
                    out+="<div class='col-3 py-1 '><a class='linksjob' href='<?php echo REQUEST_SCHEME . '://' . HTTP_HOST . '/job/';?>"+value.number+"' target='_blank'>"+value.number+"</a></div>";
                    out+="<div class='col-3 py-1 small text-left'>"+value.name+"</div>";
                    out+="<div class='col-5 py-1 small text-left'>"+value.elem+"</div>";
                    let marker=new google.maps.Marker({
                      position: new google.maps.LatLng(value.latitude, value.longitude),
                      title:value.number+ '\n' +value.name+ '\n' + value.address1 + ' ' + value.city + ' ' + value.state + ' ' + value.postalCode + ' ' + value.country + '\n' + value.jobStatusName + '\n' + value.elem,
                      icon: (value.jobStatusId==1? iconBase+'red.png': iconBase+'green.png'),
                      map: map
                    });
                });
                out+="</div>";
                $('#joblist').html(out);
//                window.addEventListener("contextmenu", e => e.preventDefault());
                $('#jobmap').animate({left: "10%"}, 500);
            }
        }).done(function() {

        });
    }

    function showJobTask(taskId){
        $.ajax({
            url: '/ajax/getTaskJobs.php',
            data: {
                taskId: taskId,
            },
            async:false,
            type:'get',
            success: function(data, textStatus, jqXHR) {
                // create Gantt Tree
                $('#jobs4elementtitle').html("Jobs for Task " + data.taskName);
                var myLatlng = new google.maps.LatLng(47.6129428,-122.3024896);
                var mapOptions = {
                    zoom: 10,
                    center: myLatlng,
                    options: {
                        gestureHandling: 'greedy'
                    }
                }
                map = new google.maps.Map(document.getElementById('map'), mapOptions);
                markers=[];
                var nr=data.data.length;
                var out='<div class="row border-bottom-1 border-success pl-3">';
                out+="<div class='col-3 pl-3 py-1 text-center font-weight-bold bg-secondary text-white'> Job Number </div>";
                out+="<div class='col-6 py-1 text-left font-weight-bold bg-secondary text-white'> Job Name </div>";
                out+="<div class='col-2 py-1 text-center font-weight-bold bg-secondary text-white' id='counter'>"+nr+"</div>";
                data.data.forEach(function(value){
                    out+="<div class='col-3 py-1 '><a class='linksjob' href='<?php echo REQUEST_SCHEME . '://' . HTTP_HOST . '/job/';?>"+value.number+"' target='_blank'>"+value.number+"</a></div>";
                    out+="<div class='col-6 py-1 small text-left'>"+value.name+"</div>";
                    out+="<div class='col-2 py-1 small text-center'><button type='button' class='btn btn-link buttonShow' tag='"+value.number+"' >Show</button></div>";
                    let marker=new google.maps.Marker({
                      position: new google.maps.LatLng(value.latitude, value.longitude),
                      title:value.number+ '\n' +value.name+ '\n' + value.address1 + ' ' + value.city + ' ' + value.state + ' ' + value.postalCode + ' ' + value.country + '\n' + value.jobStatusName,
                      icon: (value.jobStatusId==1? iconBase+'red.png': iconBase+'green.png'),
                      map: map
                    });
                    markers.push({
                        "jobNumber": value.number,
                        "marker": marker
                    });
                });
                out+="</div>";
                $('#joblist').html(out);
//                window.addEventListener("contextmenu", e => e.preventDefault());
                $('#jobmap').animate({left: "10%"}, 500);
                $(".buttonShow").mousedown(function(){
                    var jobNumber=$(this).attr('tag');
                    markers.forEach(function(value){
                       // console.log(value);
                        if(value.jobNumber==jobNumber){

                        } else {
                            value.marker.setVisible(false);
                        }
                    });
                });

                $(".buttonShow").mouseup(function(){
                    setTimeout(function(){
                        markers.forEach(function(value){
                            value.marker.setVisible(true);
                        });
                    }, 1000);
                });                
            }
        }).done(function() {

        });
    }


</script>


<!-- Modal -->
<div class="modal fade" id="noteModalInfo" tabindex="-1" role="dialog" aria-labelledby="noteModalInfoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="noteModalInfoLabel">Add/ Edit Note</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group" id="infoNoteDiv">
            <span  id="infoNote" style="float:left;font-style: italic; font-size: 15px;" value=""></span></br>
        </div>
        <div class="clearfix"></div>
        <div class="form-group">
            <label for="note-text" style="float:left; font-weight:500;" class="col-form-label mt-2" id="labelNoteText" >Note :</label>
            <textarea class="form-control" placeholder="Enter note.." required name="noteText" id="noteText" value=""></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-secondary btn-sm" id="saveNoteInfo">Save changes</button>
      </div>
    </div>
  </div>
</div>

<?php
include_once BASEDIR . '/includes/footer.php';
?>