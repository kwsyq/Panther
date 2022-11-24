<?php 
/* openworkorders.php

   Top-level page. Displays all open workOrders for current logged-in user.
   
   NO PRIMARY INPUT.
   
   OTHER INPUTS: Optional $_REQUEST['act'], only possible value 'setactive'
     * Meaningful only in conjunction with $_REQUEST['workOrderId'] 
     * Means to set the specified workOrderId as the active workOrder for current logged-in user. 
*/

include './inc/config.php';
include './inc/access.php';
?>

<?php 
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Open Work Orders';\n</script>\n";
?>
<link href="https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js" ></script>
<script>

// self-submit, triggering the act="setactive" case with the relevant workOrderId
// No need to take an action on success, because DOM should already show the desired new value.
// INPUT workOrderId
var setActive = function(workOrderId) {
    window.location.href = "/openworkorders.php?act=setactive&workOrderId=" + escape(workOrderId);    
}


// Call /ajax/setworkorderstatus.php to set a new workOrderStatusNew based on form content.
// On success, close the dialog that requested this
// INPUT formId - This will always be ID for one of the stripped-down dialogs that result from a change of status.
var changeStatusNew = function(formid) {
    let workOrderStatusId = $("#" + formid + " input[name=workOrderStatusId]").val();
    let workOrderId = $("#" + formid + " input[name=workOrderId]").val();
    // extra = $('#' + formid + ' input[name="extra[]"]');  // REPLACED by customerPersonIds 2020-06-10 JM; that was reworked again 2020-06-12
    // let customerPersonIds = $('#' + formid + ' select[name="customerPersonIds"]').val(); // REWORKED AGAIN 2020-08-26 JM to fix http://bt.dev2.ssseng.com/view.php?id=235, need [] in name.
    let customerPersonIds = $('#' + formid + ' select[name="customerPersonIds[]"]').val();
    let note = $("#" + formid + " textarea[name=note]").val();
    
    $.ajax({
        url: '/ajax/setworkorderstatusnew.php',
        data:{
            workOrderStatusId:workOrderStatusId,
            workOrderId:workOrderId ,
            // extra:extra.serialize(), // REPLACED by customerPersonIds 2020-06-10 JM
            customerPersonIds:customerPersonIds,
            note:note
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            $(".hide-answer").dialog("close");
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
}
</script>

<style>
 /* George 2021-03-08. Removed
.blacklink:link { color:#000000; text-decoration: underline; }
.blacklink:visited { color:#000000; text-decoration: none; }
.blacklink:hover { color:#000000; text-decoration: none; }
.blacklink:active { color:#000000; text-decoration: none; }

.blacklinkbig { font-size:110% }
.blacklinkbig:link { color:#000000; text-decoration: underline; }
.blacklinkbig:visited { color:#000000; text-decoration: none; }
.blacklinkbig:hover { color:#000000; text-decoration: none; }
.blacklinkbig:active { color:#000000; text-decoration: none; }
.blacklinkbig:active { color:#000000; text-decoration: none; }
*/
/* George 2021-03-08. Add */
tfoot input {
  width: 100%;
}

/* placing the footer on top */
table tfoot {
    display: table-header-group;
}
/* customize tfoot */
#openWoList tfoot tr th {
    background-color: #9d9d9d;
}
#openWoList tfoot th, #reviewsList tfoot td {
    border-right: 1px solid #898989;
}
::placeholder {
    opacity: 0.7;
}
table.dataTable tfoot th, table.dataTable tfoot td {
    padding: 10px 10px 6px 10px;
}
#openWoList { border-bottom: 1px solid black }

.newstatus {
    width: auto!important;
}
/* George 2021-03-08. End Add */
</style>

<?php

// array, each element of which is an associative array giving the canonical representation
//  of the appropriate row from DB table WorkOrderDescriptionType (column names as indexes).
//  Ordered by displayOrder.
$wodts = getWorkOrderDescriptionTypes();

// same content as associative array, indexed by workOrderDescriptionTypeId
$workOrderDescriptionTypes = array();
foreach ($wodts as $wodt) {
    $workOrderDescriptionTypes[$wodt['workOrderDescriptionTypeId']] = $wodt;
}


//The following is typically triggered by a call to local function setActive, 
// which effectively self-submits. Sets the active workOrder for the current
// logged-in user.
if ($act == 'setactive') {
    $db = DB::getInstance();
    
    $workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
    
    $query = " update " . DB__NEW_DATABASE . ".customerPerson ";
    $query .= "  set activeWorkOrder = " . intval($workOrderId) . " ";
    $query .= " where customerId = " . intval($user->getCustomer()->getCustomerId()) .  " ";
    $query .= " and personId = " . intval($user->getUserId()) . " ";
    
    $db->query($query);    
}

// BEGIN Identify the active workOrder for the current logged-in user. 
$activeWorkOrderId = 0;
$db = DB::getInstance(); // >>>00006 ought to move this above, since we do it in the $act == 'setactive' case, anyway

$query = " select * from " . DB__NEW_DATABASE . ".customerPerson ";
$query .= " where customerId = " . intval($user->getCustomer()->getCustomerId()) .  " ";
$query .= " and personId = " . intval($user->getUserId()) . " ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $activeWorkOrderId = $row['activeWorkOrder'];
    } 
} // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
// END Identify the active workOrder for the current logged-in user.

$crumbs = new Crumbs(null, $user);
// Time constructor does more work than most, calculating end of period, start of next period, etc. 
//  and fills in a private array providing two written forms of all of the dates in the week/period.
$time = new Time($user, false, 'incomplete');
$workordertasks = $time->getWorkOrderTasksByDisplayType();  // >>>00014 this is the poorly understood "gold/overlay" structure,
                                              // which as of 2019-03 remains the least understood part of the code.
$wots = array();

// In the following, 'real' workOrderTasks are ones that are explicitly in a workOrder, as against 'fake' workOrderTasks being
// there implicitly because they are parents/ancestors of explicit workOrderTasks. Apparently we are reorganizing the content
// of $workordertasks to be accessed by a multi-dimensional array, so for a given job & workOrder we find them by:
//  $wots[$jobId]][$workOrderId][$i], where $i is an arbitrary numeric index.
foreach ($workordertasks as $wot) {
    if (isset($wot['jobId'])) {
        if ($wot['type'] == 'real') {
            // BEGIN ADDED 2020-02-28 JM: initialize arrays before using them, cleaner PHP 
            if (! isset($wots[$wot['jobId']])) {
                $wots[$wot['jobId']] = Array();
            }
            if (! isset($wots[$wot['jobId']][$wot['workOrderId']])) {
                $wots[$wot['jobId']][$wot['workOrderId']] = Array();
            }
            // END ADDED 2020-02-28 JM
            $wots[$wot['jobId']][$wot['workOrderId']][] = $wot;
        }
    }
}

$workordertasks = array(); // >>>00012: multiplexing! Clear out $workordertasks to use it again. 
                           // We will buildup the same content it had before, but possibly with different indexes:
                           // now guaranteed to group by job, and within that by workOrder, convenient for ordering HTML table.

foreach ($wots as $job) {
    foreach ($job as $workorder) {
        foreach ($workorder as $workordertask) {
            $workordertasks[] = $workordertask;
        }
    }
}
//  Canonical representation of DB table WorkOrderStatus: array of associative
//  arrays, the latter with column names as indexes. Order by displayOrder.
$statuses = WorkOrder::getAllWorkOrderStatuses();
$workOrderStatusHierarchy = WorkOrder::getWorkOrderStatusHierarchy($statuses);

// FUNCTION ABSTRACTED 2020-06-12 JM
// display $workOrderStatus and all of its substatuses as HTML OPTIONs
// I believe this part isn't exactly in _admin/workorderstatus/index.php, but it is still duplicated several places
function displayOptions($workOrderStatus, $selectedStatusId) {
    $selected = (intval($workOrderStatus['workOrderStatusId']) == $selectedStatusId) ? ' selected ' : '';
    echo '<option value="' . $workOrderStatus['workOrderStatusId'] . '" ' . $selected . '>';
    // indent to reflect level in hierarchy
    for ($i=0; $i< $workOrderStatus['level'] * 3; ++ $i) {
        echo '&nbsp;';
    }
    echo $workOrderStatus['statusName'] . '</option>';
    foreach ($workOrderStatus['children'] as $child) {
        displayOptions($child, $selectedStatusId);
    }
}
?>
    
<div id="container" class="clearfix">
    <div class="main-content mt-10">

        <?php /* Heading "My Open Workorders", then table to display the current user's workorders, grouped by job: 
                 for each relevant job we make sort of a header for the job followed by a line for each relevant workOrder. 
                 Columns as indicated by the column-headings just below. George 2021-03-08. Reworked. */ ?>                 

        <div class="full-box clearfix">
            <h2 class="heading">My Open Workorders</h2>
            <br>
            <table class="stripe row-border cell-border"  id="openWoList" style="width:100%">
                <thead>
                    <tr>
                        <th>Job Number</th>
                        <th>Job Name</th>
                        <th>Client / Design Pro</th>
                        <th>Active</th>
                        <th>Type</th>
                        <th>EOR</th>
                        <th>Status</th>
                        <th>Extra</th>
                        <th>Genesis</th>
                    </tr>
                </thead> 
                <tfoot> 
                    <tr>
                        <th>Job Number</th>
                        <th>Job Name</th>
                        <th>Client / Design Pro</th>
                        <th id="activeStatusId">Active</th>
                        <th>Type</th>
                        <th>EOR</th>
                        <th id="changeStatusId">Status</th>
                        <th>Extra</th>
                        <th>Genesis</th>
                    </tr>
                </tfoot> 
        <?php        
                $lastWorkOrderId = 0;
                $lastJobId = 0;
                
    // BEGIN OUTDENT                        
    foreach ($workordertasks as $workordertask) {
        if ($workordertask['type'] == 'real') {
            if (intval($workordertask['jobId'])) {
                if ($lastWorkOrderId != $workordertask['workOrderId']) {
                    // Starting a new job within the table
                    $job = new Job($workordertask['jobId']);
                    
                    // if there was a prior job, a blank row
                    /*if ($lastJobId) { // George
                        echo '<tr>';
                            echo '<td colspan="6"><hr></td>';
                        echo '</tr>';
                    }*/
                    echo '<tr>';
                        // Skip "Active" column
                    // echo '<td>&nbsp;</td>';
                        
                        // Use all the remaining columns for a link to the job, labeled with 
                        //  Job Number + workOrderTask number + workOrderTask name.$clientString.
                        // Unless it is an administrative item, this is followed by 
                        //  "(Cl : client person name, client company name) 
                        //  (DP : design pro person name, design pro company name)".
                        
                        $clientString = '';
                        $clients = $job->getTeamPosition(TEAM_POS_ID_CLIENT,1);
                        $clients = array_slice($clients,0,1);
                        if (count($clients)) {
                            $client = $clients[0];
                            if (isset($client['companyPersonId'])) {
                                $cp = new CompanyPerson($client['companyPersonId']);
                                if (intval($cp->getCompanyPersonId())) {
                                    $p = $cp->getPerson();
                                    $c = $cp->getCompany();
                                    $clientString = '(Cl : ' . $p->getFormattedName(1) . ", " . $c->getName() . ')';
                                }
                            }
                        }    
                    
                        $designProString = '';
                        $pros = $job->getTeamPosition(TEAM_POS_ID_DESIGN_PRO,1);
                        $pros = array_slice($pros,0,1);
                        if (count($pros)) {
                            $pro = $pros[0];
                            if (isset($pro['companyPersonId'])) {
                                $cp = new CompanyPerson($pro['companyPersonId']);
                                if (intval($cp->getCompanyPersonId())) {
                                    $p = $cp->getPerson();
                                    $c = $cp->getCompany();
                                    $designProString = '(DP : ' . $p->getFormattedName(1) . ", " . $c->getName() . ')';
                                }
                            }
                        }
                            
                        // Use all the remaining columns for a link to the job, labeled with 
                        //  Job Number (misleadingly named $workOrderTask['number']) + workOrderTask name.
                        // Unless it is an administrative item, this is followed by 
                        //  "(Cl : client person name, client company name) 
                        //  (DP : design pro person name, design pro company name)".
                        /*echo '<td colspan="5" nowrap><h3><a id="blacklinkbig' . $job->getJobId() . '"  class="blacklinkbig" href="' . $job->buildLink() . '">' .
                            '[' . $workordertask['number'] . ']&nbsp;' . $workordertask['name'] . '</a>' . 
                            '</h3>' . $clientString . ' ' . $designProString . '</td>'; */
                        echo '<td><a id="blacklinkbig' . $job->getJobId() . '"  class="blacklinkbig" href="' . $job->buildLink() . '">' .
                    $workordertask['number'] .'</a></td>';
                        echo '<td ><a id="blacklinkbig' . $job->getJobId() . '"  class="blacklinkbig" href="' . $job->buildLink() . '">' .
                        $workordertask['name'] .'</a></td>';
                        echo '<td > ' . $clientString . ' ' . $designProString . '</td>';
                                
                
                } // END if ($lastJobId != $workordertask['jobId'])
                $workOrder = new WorkOrder($workordertask['workOrderId']);
                if ($lastWorkOrderId != $workordertask['workOrderId']) {
                    /* BEGIN REPLACED 2020-06-12 JM
                    $ret = formatGenesisAndAge($workorder->getGenesisDate(), $workorder->getDeliveryDate(), $workorder->getWorkOrderStatusId());
                    // END REPLACED 2020-06-12 JM
                    */
                    // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                    $ret = formatGenesisAndAge($workOrder->getGenesisDate(), $workOrder->getDeliveryDate(), $workOrder->isDone());
                    // END REPLACEMENT 2020-06-12 JM
        
                    $genesisDT = $ret['genesisDT'];
                    $deliveryDT = $ret['deliveryDT'];
                    $ageDT = $ret['ageDT'];
                    
                    
                        $checked = ($activeWorkOrderId == intval($workOrder->getWorkOrderId())) ? ' checked ':'';
                        // "Active": Radio button; on click calls setActive(WorkOrderId). That causes a self-submission 
                        // with act=setactive&workOrderId=workOrderId. 
                        echo '<td><input class="currentWo" onClick="setActive(' . intval($workOrder->getWorkOrderId()) . ')" type="radio" id="currentWo' . $workOrder->getWorkOrderId() . '"  name="currentWorkOrderId" value="' . $workOrder->getWorkOrderId() . '" ' . $checked . '></td>';
                        
                        // (unlabeled): blank for workOrder, used only for job
                        //echo '<td>&nbsp;&nbsp;</td>';
                        
                        // (unlabeled): link to open workOrder.php in same window/tab for the relevant workOrder, 
                        //  labeled with workOrder description; background color is based on workOrderDescriptionType.
                        $color = "ffffff";                    
                        if (isset($workOrderDescriptionTypes[$workOrder->getWorkOrderDescriptionTypeId()])) {
                            $color = $workOrderDescriptionTypes[$workOrder->getWorkOrderDescriptionTypeId()]['color'];
                            // BEGIN ADDED 2020-04-03 JM
                            $workOrderType = $workOrderDescriptionTypes[$workOrder->getWorkOrderDescriptionTypeId()]['typeName'];
                            // END ADDED 2020-04-03 JM
                        }
                        
                        // In the following, $workOrderType added 2020-04-03 JM 
                        echo '<td  bgcolor="#' . $color . '" ><a  id="blacklinkWo'. $workOrder->getWorkOrderId() .' "  class="blacklink" href="' . $workOrder->buildLink() . '">' . 
                            $workOrderType . ': ' . $workOrder->getDescription() . '</a></td>';
                        
                        // "EOR": formatted name of Engineer of Record
                        $eor = '';
                        $db = DB::getInstance();
                        $x = $workOrder->getTeamPosition(TEAM_POS_ID_EOR,1);
                        
                        if (count($x)) {
                            $query = " select * ";
                            $query .= "  from " . DB__NEW_DATABASE . ".person  p ";
                            $query .= "  join " . DB__NEW_DATABASE . ".companyPerson cp  on p.personId = cp.personId ";
                            $query .= " where cp.companyPersonId = " . intval($x[0]['companyPersonId']);
                            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $p = new Person($row['personId']);
                                    $eor = $p->getFormattedName(1);
                                }
                            }
                        }
                        echo '<td>' . $eor . '</td>';
                        // "Status": HTML SELECT element to choose status of workOrder. Using jQuery, changing this triggers a stripped-down
                        //  jQuery dialog containing an HTML FORM. That form offers different possibilities for different selected statuses.
                        // Reworked 2020-06-12 to use $workOrderStatusHierarchy rather than $statuses, so we can get these in the right order
                        // Function displayOptions abstracted, above
                        /* BEGIN REPLACED 2020-06-12 JM
                        echo '<td nowrap><select name="workOrderStatusId" class="newstatus" data-woid="' . $workOrder->getWorkOrderId() . 
                            '" id="select_' . $workOrder->getWorkOrderId() . '" >';
                            foreach ($statuses as $status) {
                                $checked = (intval($status['workOrderStatusId']) == intval($workOrder->getWorkOrderStatusId())) ? ' selected ' : '';
                                echo '<option value="' . $status['workOrderStatusId'] . '" ' . $checked . '>' . $status['statusName'] . '</option>';
                            }
                        echo '</select></td>';
                        // END REPLACED 2020-06-12 JM
                        */
                        // BEGIN REPLACEMENT 2020-06-12 JM
                        echo '<td><select  name="workOrderStatusId" class="newstatus form-control form-control-sm" data-woid="' . $workOrder->getWorkOrderId() . '" id="select_' . $workOrder->getWorkOrderId() . '" >';
                            foreach ($workOrderStatusHierarchy as $status) {
                                displayOptions($status, intval($workOrder->getWorkOrderStatusId()));
                            }
                        echo '</select></td>';
                        // END REPLACEMENT 2020-06-12 JM
                        
                        // "Extra": A table within the table. Shows initials of EORs associated with this workOrder.
                        echo '<td>';
                            $statusdata = $workOrder->getStatusData();
                            
                            /* BEGIN REPLACED 2020-06-09 JM
                            if (isset($statusdata['extra'])) {
                                if (intval($statusdata['extra'])) {
                                    $extras = $workOrderStatusExtra[$statusdata['workOrderStatusId']];
                                    echo '<table border="0" cellpadding="1" cellspacing="0">';
                                        foreach ($extras as $ekey => $e) {
                                            if ($ekey & $statusdata['extra']) {
                                                echo '<tr><td valign="top">&gt;</td><td valign="top">' . $e['title'] . '</td></tr>';
                                            }
                                        }
                                    echo '</table>';
                                }
                            }
                            // END REPLACED 2020-06-09 JM
                            */
                            // BEGIN REPLACEMENT 2020-06-09 JM
                            if ($statusdata['customerPersonArray']) {
                                $nrEor = 0; // check if multiple EOR's
                                foreach($statusdata['customerPersonArray'] AS $customerPersonData) {
                                    if(array_key_exists("legacyInitials",$customerPersonData)) {
                                        $nrEor = $nrEor +1;
                                    }
                                    if($nrEor > 1) {
                                        //echo '<td valign="top">&gt;</td><td valign="top">' . $customerPersonData['legacyInitials'] . '</td>';
                                        echo  " | " . $customerPersonData['legacyInitials'];
                                    } else {
                                        echo  $customerPersonData['legacyInitials']; // display EOR without separator
                                    }
                                }
                            }
                            // END REPLACEMENT 2020-06-09 JM
                        echo '</td>';
                            
                        // "Genesis": "age" of the workOrder.
                        echo '<td nowrap>' . $ageDT . '</td>';
                    echo '</tr>';                    
                } // END if ($lastWorkOrderId != $workordertask['workOrderId'])
                    
                // Context for next time through the foreach ($workordertasks...) loop 
                $lastWorkOrderId = $workordertask['workOrderId'];
                $lastJobId = $workordertask['jobId'];
                
                // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                //echo '</tr>';
                // [END COMMENTED OUT BY MARTIN BEFORE 2019]
            }
        } // END if ($workordertask['type'] == 'real')
    } // END foreach ($workordertasks...
    // END OUTDENT    
                
            echo '</table>';
                    
            ?>                
        </div>    
    </div>
</div>

<?php
// Make the stripped-down dialog for each workOrderStatus, for when a new workOrderStatus is selected in the dropdown
//
// As mentioned above, changing the HTML OPTION of the HTML SELECT dropdown in the "Status" column opens 
//  an HTML FORM in a (very stripped-down) jQuery dialog, implemented here. The content of the form depends 
//  on the newly selected status, hence the foreach loop here. 
// All of the forms contain a hidden workOrderId and workOrderStatusId (the newly selected status). 
//  They may contain checkboxes for any number of "extras", and also a user-editable note for the status change.
//  In v2020-3, we have simplified "extras" to be just people to be notified [currently only for holds, that may change]. 
//  The rest of what used to be handled by extras is now handled by substatuses.
// >>>00032: NOTE that if the user cancels out of this form, the old status value is not visibly restored, 
//  although the database is unchanged; refreshing the page will make everything OK. 
//  REALLY OUGHT TO refresh automatically - JM 2019-03-29. 
foreach ($statuses as $status) {
?>
    <div class="hide-answer" id="status-<?php echo $status['workOrderStatusId']; ?>" title="<?php echo htmlspecialchars($status['statusName']); ?>">
        <form name="" id="status-<?php echo $status['workOrderStatusId'];?>-Form" action="" method="post">
            <input type="hidden" name="workOrderId" value="">
            <input type="hidden" name="workOrderStatusId" value="<?php echo intval($status['workOrderStatusId']);?>">
            
            <p>This is for "<?php echo htmlspecialchars($status['statusName']); ?>"</p>
            <?php
            /* BEGIN REPLACED 2020-06-10 JM
            if (isset($workOrderStatusExtra[$status['workOrderStatusId']])) {
                $extras = $workOrderStatusExtra[$status['workOrderStatusId']];
                
                echo '<table>';
                foreach ($extras as $ekey => $extra) {
                    echo '<tr>';
                        echo '<td><input type="checkbox" name="extra[]" value="' . intval($ekey) . '"></td>';
                        echo '<td>' . $extra['title'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            // END REPLACED 2020-06-10 JM
            */
            // BEGIN REPLACEMENT 2020-06-10, amended 2020-06-12 JM
            if ($status['canNotify']) {  // This can be 0 to indicate "don't set any notification at all";
                echo '<select id="customerPersonIds" multiple name="customerPersonIds[]">';
                $customerPersons = CustomerPerson::getAll(true); // array of CustomerPersons
                foreach ($customerPersons AS $customerPerson) {
                    if ($status['canNotify']==CAN_NOTIFY_EMPLOYEES || ($status['canNotify']==CAN_NOTIFY_EORS  && $customerPerson->getIsEor())) {
                        echo '<option value="' . intval($customerPerson->getCustomerPersonId()) . '">' . $customerPerson->getLegacyInitials() . '</option>';
                    }
                }
                echo '</select>';
            }
            // END REPLACEMENT 2020-06-10 JM
            ?>
            <p>
            Note:
            <textarea class="form-control" cols="20" rows="3" name="note"></textarea>
            <p>
            <input type="button"  class="btn btn-secondary btn-sm mr-auto ml-auto" value="Submit" id="changeStatusNew<?php echo $status['workOrderStatusId']; ?>" onclick="changeStatusNew('status-<?php echo $status['workOrderStatusId']; ?>')"/>
        </form>
    </div>
<?php        
}         
?>

<script>
$(document).ready(function() {
    $(".hide-answer").dialog({
        autoOpen: false
    });

    // Hide Close Text for dialog. We still have the X sign.
    $( ".hide-answer" ).dialog({
        closeText : ""
    });

    $(".newstatus").change(function() {
        $(".hide-answer").dialog("close");
        var sel = $(this).val();
        $("#status-" + sel + " input[name=workOrderId]").val($(this).data('woid'));
        $("#status-" + sel).dialog('open');
    });


    // DataTable tfoot Search placeholder
    $('#openWoList tfoot th').each( function () {
        $(this).html( '<input type="text" placeholder="Search.." />' );
        $('#activeStatusId input').hide(); // Hide Search for active status column. 
        $('#changeStatusId input').hide(); // Hide Search for new status column. 
        $('input[type="text"]').addClass('form-control form-control-sm');
    } );

    // DataTable
    var table = $('#openWoList').DataTable({
        "autoWidth": true,
        initComplete: function () {
            // Apply the search
            this.api().columns().every( function () {
                var that = this;

                $( 'input', this.footer() ).on( 'keyup change clear', function () {
                    if ( that.search() !== this.value ) {
                        that
                            .search( this.value )
                            .draw();
                    }
                } );
            } );
        }
    });
});
// George 2021-03-08. We used this because bootstrap overrides jquery 
//  and the close icon X from dialog doesn't show properly. Also in Dialog we add the property: closeText: ''
$.fn.bootstrapBtn = $.fn.button.noConflict();
</script>    

<?php 
include BASEDIR . '/includes/footer.php';
?>