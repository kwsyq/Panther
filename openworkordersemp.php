<?php 
/* openworkordersemp.php

   EXECUTIVE SUMMARY: Displays ALL open work orders, ordered by employee (for current customer, as of 2019-04 always SSS).
   
   No primary input.
   Optional input: $_REQUEST['act']. Only possible value: 'changeStatus', uses $_REQUEST['workOrderStatusId'], $_REQUEST['workOrderId'] to update workorder status.
     NOTE that this self-submission cannot change customerPersonIds          
        
   >>>00001 JM 2019-04-02: probably worth looking into common code elimination with openworkorders.php
   
   >>>00013 JM 2019-04-02: This file was a particular mess. My intent so far has been just to do cleanup, but there was so much cleanup
     that I'd really like to see this tested. Plus, I suspect it was kind of broken even before.
*/

include './inc/config.php';
include './inc/access.php';

?>

<?php 
include BASEDIR . '/includes/header.php';
$db = DB::getInstance(); // ADDED 2020-02-21 JM

if ($act = 'changestatus') {
    $workOrderStatusId = isset($_REQUEST['workOrderStatusId']) ? $_REQUEST['workOrderStatusId'] : 0;
    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : 0;	
    $workOrder = new WorkOrder($workOrderId);	
    if (intval($workOrder->getWorkOrderId())) {
        /* BEGIN REPLACED 2020-11-18 JM
        // THIS WAS NEVER A GOOD IDEA. This failed to insert into workOrderStatusTime.
        $workOrder->update(array('workOrderStatusId' => intval($workOrderStatusId)));
        // END REPLACED 2020-11-18 JM
        */
        // BEGIN REPLACEMENT 2020-11-18 JM
        $customerPersons = Array(); // nobody
        $note = '';
        $workOrder->setStatus($workOrderStatusId, $customerPersons, $note); // ignoring failure here: it's already been logged, and there is nothing we can do about it.
        unset($customerPersons, $note);
        // END REPLACEMENT 2020-11-18 JM
    }
    unset($workOrderStatusId, $workOrderId, $workOrder); // JM 2020-07-23: cleanup motivated by http://bt.dev2.ssseng.com/view.php?id=185
    // Drop through to show page as usual
}

?>
<script>
// INPUT formId - identifies which of several forms is being used; there is one for
//  each distinct workOrderStatus. 
// Will pull the data from the form, pass it to /ajax/setworkorderstatusnew.php 
//  to apply data to this workOrder, alerts on error. Closes form dialog on success.
//  Besides setting a new workOrderStatus, can also set accompanying extra & note.
var changeStatusNew = function(formid) {
    workOrderStatusId = $("#" + formid + " input[name=workOrderStatusId]").val();
    workOrderId = $("#" + formid + " input[name=workOrderId]").val();
    // extra = $('#' + formid + ' input[name="extra[]"]');  // REPLACED by customerPersonIds 2020-06-10 JM
    customerPersonIds = $('#' + formid + ' input[name="customerPersonIds[]"]');
    note = $("#" + formid + " textarea[name=note]").val();

    $.ajax({
        url: '/ajax/setworkorderstatusnew.php',
        data:{
            workOrderStatusId:workOrderStatusId,
            workOrderId:workOrderId ,
            // extra:extra.serialize(), // REPLACED by customerPersonIds 2020-06-10 JM
            customerPersonIds: customerPersonIds.serialize(),
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
} // END function changeStatusNew
</script>

<style>
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

</style>

<div id="container" class="clearfix">
    <div class="main-content">
        <div class="full-box clearfix">
            <h2 class="heading">Open Workorders By Employee</h2>
<?php
                //  Canonical representation of DB table WorkOrderStatus: array of associative
                //  arrays, the latter with column names as indexes. Order by displayOrder.
                $statuses = WorkOrder::getAllWorkOrderStatuses();
                $workOrderStatusHierarchy = WorkOrder::getWorkOrderStatusHierarchy($statuses);
                
                // FUNCTION ABSTRACTED 2020-06-12 JM
                // display $workOrderStatus and all of its substatuses as HTML OPTIONs
                // duplicated from top-level file openworkorders.php
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
                
                $crumbs = new Crumbs(null, $user);

                // array, each element of which is an associative array giving the canonical representation
                //  of the appropriate row from DB table WorkOrderDescriptionType (column names as indexes).
                //  Ordered by displayOrder.
                $wodts = getWorkOrderDescriptionTypes();
                
                // same content as associative array, indexed by workOrderDescriptionTypeId
                $workOrderDescriptionTypes = array();
                foreach ($wodts as $wodt) {
                    $workOrderDescriptionTypes[$wodt['workOrderDescriptionTypeId']] = $wodt;
                }
                
                /* BEGIN REMOVED 2020-04-03 JM: Ron & Damon say this is useless.
                // Display color legend for the various workOrderDescriptionTypes
                echo '<table border="0" cellpadding="0" cellspacing="0">';
                    $wodts = array_slice($wodts, 1); // drop the first item from the array. Seems terribly 'ad hoc'. In practice, as
                                             // of 2019, this drops "code update", but if that's the intent it should be done in a much more
                                             // resilient & less cryptic manner. 
                                             // SAME ISSUE AS FOR openworkorders.php
                    $cols = 3;
                    foreach ($wodts as $wkey => $workOrderDescriptionType) {
                        // Every third workOrderDescriptionType, a new row.
                        if (($wkey % 3) == 0) {
                            if ($wkey){
                                echo '</tr>';
                            }
                            echo '<tr>';
                        }
                        
                        $color = "ffffff";
                        if (isset($workOrderDescriptionTypes[$workOrderDescriptionType['workOrderDescriptionTypeId']])) {
                            $color = $workOrderDescriptionTypes[$workOrderDescriptionType['workOrderDescriptionTypeId']]['color'];
                        }
                        
                        echo '<td bgcolor="#' . $color . '">' . $workOrderDescriptionTypes[$workOrderDescriptionType['workOrderDescriptionTypeId']]['typeName'] . '</td>';
                        
                        if ($wkey == (count($wodts) - 1)) {
                            // last entry, close things out: as many blank columns as we need, and close the row.
                            $off = $cols - (($wkey % 3) + 1);
                            if ($off) {
                                for ($i = 0; $i < $off; ++$i) {
                                    echo '<td></td>';
                                }
                            }
                        }
                        echo '</tr>';
                    }
                echo '</table>';
                // END REMOVED 2020-04-03 JM
                */

// BEGIN OUTDENT                
$employees = $customer->getEmployees(1);  // arg 1 limits to current employees

/* OLD CODE REMOVED 2019-02-18
echo '<H2>IMPORTANT FOR CHANGING STATUS::: when selecting a STATUS in the dropdown you need to CLICK and HOLD then move the mouse to a new selection (or back to same selection).  The functionality for this is triggered when the mouse button is RELEASED hence the need to CLICK and HOLD ! (see Martin, Ron or Tawny for clarification if needed!).<br>See Tawny if the behavior doesn\'t work as expected (particularly in Chrome Browser)';
*/
// BEGIN NEW CODE 2019-02-18
echo '<H2>IMPORTANT FOR CHANGING STATUS::: when selecting a STATUS in the dropdown you need to CLICK and HOLD then move the mouse to a new selection (or back to same selection). ' .
     'The functionality for this is triggered when the mouse button is RELEASED, hence the need to CLICK and HOLD ! ' .
     WHO_TO_SEE_FOR_CHANGING_STATUS;
// END NEW CODE 2019-02-18
echo '<br>In Chrome you might need to click and hold for a second then release the button to choose a new option</h2>';

/* >>>00001, >>>00006, >>>00026 JM had conversation with Martin 2018-04-05 about Martin's note 
    about the need to click & hold while selecting status, and passed on the following. 
    I take it that this wasn't firing change events as desired. 
    mouseup seems to me a less than ideal choice, because all of this should in theory 
    also be able to be done from the keyboard; at the very least, 
    you should do the same on blur. You might consider using jQuery .data() to store 
    a copy of the "old" value, then on blur (etc.), you can do your own check to see 
    if the value has changed, act on it if it has, and then update the "old" value 
    in the jQuery .data(). If even blur isn't firing, you could have something waking 
    up once a second & checking for the change. Not pretty, but effective, and seems 
    better to me than having the user have to think about it. 
    
    BUT: I now (2019-04) suspect this may be a an artifact of some very bad HTML that
    messed up the table structures. */                

/* For each employee, display employee name & any open workOrders,
   with the usual genesis, age, etc., tasks, engineer of record (EOR) info. 
   The unique "active workOrder" for this person is marked with "**" in the 
   "Active" column (no link or action). Other workOrders shown here are part 
   of tasks assigned to that person. WorkOrder description type gets appropriate 
   background color. In general, this is very like the listing in openworkorders.php. */

foreach ($employees as $employee) {
    echo '<table border="0" cellpadding="3" cellspacing="0">';
        $activeWorkOrderId = 0; 
        // $db = DB::getInstance(); // REMOVED 2020-02-21 JM, now done above
        
        /* BEGIN REPLACED 2020-02-21 JM
        $query = " select * from " . DB__NEW_DATABASE . ".customerPerson ";
        // END REPLACED 2020-02-21 JM
        */ 
        // BEGIN REPLACEMENT 2020-02-21 JM
        $query = " select activeWorkOrder from " . DB__NEW_DATABASE . ".customerPerson ";
        // END REPLACEMENT 2020-02-21 JM
        $query .= " where customerId = " . intval($employee->getCustomer()->getCustomerId()) .  " ";
        $query .= " and personId = " . intval($employee->getUserId()) . " ";
    
        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $activeWorkOrderId = $row['activeWorkOrder'];
            }
        }  // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
        
        // Time constructor does more work than most, calculating end of period, start of next period, etc. 
        //  and fills in a private array providing two written forms of all of the dates in the week/period.
        $time = new Time($employee, false, 'incomplete');
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
                    // BEGIN ADDED 2020-02-21 JM : fix "barely legal" PHP that used array without initializing it
                    if (! isset($wots[$wot['jobId']])) {
                        $wots[$wot['jobId']] = Array();
                    }
                    if (! isset($wots[$wot['jobId']][$wot['workOrderId']])) {
                        $wots[$wot['jobId']][$wot['workOrderId']] = Array();
                    }
                    // END ADDED 2020-02-21 JM
                    $wots[$wot['jobId']][$wot['workOrderId']][] = $wot;
                }
            }
        }                                                                      
        
        $realworkordertasks = array();  // Only "real" workordertasks, now guaranteed to group by job, and within that by workOrder, 
                                    // convenient for ordering HTML table.
                                    // Prior to 2020-02-21, this "multiplexed" the variable $workordertasks; distinct $realworkordertasks introduced
                                    //  at that time by JM.
        foreach ($wots as $job) {
            foreach ($job as $workorder) {
                foreach ($workorder as $workordertask) {
                    $realworkordertasks[] = $workordertask;
                }
            }
        }
        unset($job, $workorder); // JM 2020-07-23: cleanup motivated by http://bt.dev2.ssseng.com/view.php?id=185
        
        // 2020-04-03 JM: added a blank row above name & set a style here to make employee name more visible
        echo '<tr><td colspan="7">&nbsp;</td></tr>';
        echo '<tr>';
            echo '<th colspan="7" style="font-weight:bold; font-size:150%; color:#80ff80;">' . $employee->getFormattedName(1) . '</th>';
        echo '</tr>';
        echo '<tr>'; // ADDED 2020-02-21 JM, HTML was definitely messed up without this here.	
            echo '<th>Active</th>';
            echo '<th colspan="2" width="100%"></th>';
            echo '<th>EOR</th>';
            echo '<th>Status</th>';
            echo '<th>Extra</th>';
            echo '<th>Genesis</th>';
        echo '</tr>';
        
        $lastWorkOrderId = 0;
        $lastJobId = 0;
        foreach ($realworkordertasks as $workordertask) {
            if ($workordertask['type'] == 'real') {
                if (intval($workordertask['jobId'])) {
                    if ($lastJobId != $workordertask['jobId']) {
                        // Starting a new job within the table
                        $job = new Job($workordertask['jobId']);
                        // if there was a prior job, a blank row
                        if ($lastJobId) {
                            echo '<tr>';
                                echo '<td colspan="7"><hr></td>';
                            echo '</tr>';
                        }
                        
                        // CONTENT AT START OF JOB
                        $clientString = '';
                        $clients = $job->getTeamPosition(TEAM_POS_ID_CLIENT, 1);
                        $clients = array_slice($clients, 0, 1);
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
                        $pros = $job->getTeamPosition(TEAM_POS_ID_DESIGN_PRO, 1);
                        $pros = array_slice($pros, 0, 1);
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
                        
                        echo '<tr>';
                            // Skip "Active" column
                            echo '<td>&nbsp;</td>';
                            // Use all the remaining columns for a link to the job, labeled with 
                            //  Job Number (misleadingly named $workOrderTask['number']) + workOrderTask name.
                            // Unless it is an administrative item, this is followed by 
                            //  "(Cl : client person name, client company name) 
                            //  (DP : design pro person name, design pro company name)".
                            echo '<td colspan="5" nowrap><h3><a id="linkJobNr'.$job->getJobId().'"  class="blacklinkbig" href="' . $job->buildLink() . '">' .
                                 '[' . $workordertask['number'] . ']&nbsp;' . $workordertask['name']  . '</a>' . 
                                 '</h3>' . $clientString . ' ' . $designProString . '</td>';
                        echo '</tr>';
                    } // END // Starting a new job within the table
                    
                    $workOrder = new WorkOrder($workordertask['workOrderId']);                        
                    if ($lastWorkOrderId != $workordertask['workOrderId']) {
                        // $workOrder = new WorkOrder($workordertask['workOrderId']); // JM 2020-07-23: removed as redundant, we just did that; cleanup motivated by http://bt.dev2.ssseng.com/view.php?id=185
                        /* BEGIN REPLACED 2020-06-12 JM
                        $ret = formatGenesisAndAge($workorder->getGenesisDate(), $workorder->getDeliveryDate(), $workorder->getWorkOrderStatusId());
                        // END REPLACED 2020-06-12 JM
                        */
                        // BEGIN REPLACEMENT 2020-06-12 JM; refined 2020-11-18.
                        $ret = formatGenesisAndAge($workOrder->getGenesisDate(), $workOrder->getDeliveryDate(), $workOrder->isDone());
                        // END REPLACEMENT 2020-06-12 JM
                        $genesisDT = $ret['genesisDT'];
                        $deliveryDT = $ret['deliveryDT'];
                        $ageDT = $ret['ageDT'];
                        
                        echo '<tr>';
                            // "Active": Radio button 
                            $checked = ($activeWorkOrderId == intval($workOrder->getWorkOrderId())) ? '**':'';
                            echo '<td>' . $checked . '</td>';
                    
                            // Always-empty column
                            echo '<td>&nbsp;&nbsp;</td>';
                            
                            // (no header) WorkOrder description; background color keyed from workOrderDescriptionTypeId 
                            $color = "ffffff";
                            if (isset($workOrderDescriptionTypes[$workOrder->getWorkOrderDescriptionTypeId()])) {
                                $color = $workOrderDescriptionTypes[$workOrder->getWorkOrderDescriptionTypeId()]['color'];
                                // BEGIN ADDED 2020-04-03 JM
                                $workOrderType = $workOrderDescriptionTypes[$workOrder->getWorkOrderDescriptionTypeId()]['typeName'];
                                // END ADDED 2020-04-03 JM
                            }
                            // In the following, $workOrderType added 2020-04-03 JM
                            echo '<td bgcolor="#' . $color . '" width="100%"><h3><a id="linkWoType'.$workOrder->getWorkOrderId().'" class="blacklink" href="' . $workOrder->buildLink() . '">' . 
                                $workOrderType . ': ' . $workOrder->getDescription() . '</a></h3></td>';
                                
                            // "EOR": formatted name of Engineer of Record
                            $eor = '';
                            // $db = DB::getInstance(); // REMOVED 2020-02-21 JM, now done above;
                            $x = $workOrder->getTeamPosition(TEAM_POS_ID_EOR,1);
                            
                            if (count($x)) {
                                /* BEGIN REPLACED 2020-02-21 JM
                                $query = " select ";
                                // END REPLACED 2020-02-21 JM
                                */ 
                                // BEGIN REPLACEMENT 2020-02-21 JM
                                $query = " select personId ";
                                // END REPLACEMENT 2020-02-21 JM
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
                            echo '<td nowrap>' . $eor . '</td>';
                            
                            // "Status"
                            // FORM really ought to go *inside* TD, not outside (with hidden inputs before the TD)
                            // >>>00026: 2019-04-02 JM: action here happens through a jQuery handler below, but it looks like it's 
                            //  tied to mouseup which is a poor choice (what if the user is sticking to keyboard commands, for example?)
                            echo '<form id="status_' . $workOrder->getWorkOrderId() . '" name="status_' . $workOrder->getWorkOrderId() . '">';
                                echo '<input type="hidden" name="act" value="changestatus">';
                                echo '<input type="hidden" name="workOrderId" value="' . $workOrder->getWorkOrderId() . '">';
                            
                                /* BEGIN commented out by Martin before 2019 */
                                //echo '<td nowrap><select name="workOrderStatusId" id="select_' . $workOrder->getWorkOrderId() . '_' . $employee->getUserId() . '" onchange="changeStatus(' . $workOrder->getWorkOrderId() . ',' . $employee->getUserId() . ')">';
                                /* END commented out by Martin before 2019 */
                                
                                // HTML SELECT element to choose status of workOrder. Using jQuery, changing this triggers a stripped-down
                                //  jQuery dialog containing an HTML FORM. That form offers different possibilities for different selected statuses.
                                // Reworked 2020-06-18 to use $workOrderStatusHierarchy rather than $statuses, so we can get these in the right order
                                // Function displayOptions abstracted, above
                                /* BEGIN REPLACED 2020-06-18 JM
                                echo '<td nowrap><select name="workOrderStatusId" class="newstatus" data-woid="' . $workOrder->getWorkOrderId() . 
                                    '" id="select_' . $workOrder->getWorkOrderId() . '" >';
                                    foreach ($statuses as $status) {
                                        $checked = (intval($status['workOrderStatusId']) == intval($workOrder->getWorkOrderStatusId())) ? ' selected ' : '';
                                        echo '<option value="' . $status['workOrderStatusId'] . '" ' . $checked . '>' . $status['statusName'] . '</option>';
                                    }
                                echo '</select></td>';
                                // END REPLACED 2020-06-18 JM
                                */
                                // BEGIN REPLACEMENT 2020-06-18 JM
                                echo '<td nowrap><select name="workOrderStatusId" class="newstatus" data-woid="' . $workOrder->getWorkOrderId() . '" id="select_' . $workOrder->getWorkOrderId() . '" >';
                                    foreach ($workOrderStatusHierarchy as $status) {
                                        displayOptions($status, intval($workOrder->getWorkOrderStatusId()));
                                    }
                                echo '</select></td>';
                                // END REPLACEMENT 2020-06-18 JM
                            echo '</form>';
                            
                            // "Extra": A table within the table. Shows initials of EORs associated with this workOrder.
                            echo '<td>';
                                $statusdata = $workOrder->getStatusData();
                                /* BEGIN REPLACED 2020-06-09 JM
                                if (isset($statusdata['extra'])) {
                                    if (intval($statusdata['extra'])) {
                                        $extras = $workOrderStatusExtra[$statusdata['workOrderStatusId']];
                                        echo '<table border="0" cellpadding="1" cellspacing="0">';
                                            foreach ($extras as $ekey => $e){
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
                                    echo '<table border="0" cellpadding="1" cellspacing="0">';
                                    foreach($statusdata['customerPersonArray'] AS $customerPersonData) {
                                        echo '<tr><td valign="top">&gt;</td><td valign="top">' . $customerPersonData['legacyInitials'] . '</td></tr>';
                                    }
                                    echo '</table>';
                                }
                                // END REPLACEMENT 2020-06-09 JM
                            echo '</td>';
                            
                            // "Genesis": "age" of the workOrder.
                            echo '<td nowrap>' . $ageDT . '</td>';
                        echo '</tr>';
                    } // END if ($lastWorkOrderId != $workordertask['workOrderId'])
                    
                    // Context for next time through the foreach ($realworkordertasks...) loop
                    $lastWorkOrderId = $workordertask['workOrderId'];
                    $lastJobId = $workordertask['jobId'];
                    
                    /* BEGIN commented out by Martin before 2019 */
                    //echo '</tr>';
                    /* END commented out by Martin before 2019 */
                }
            } // END if ($workordertask['type'] == 'real')
        } // END foreach ($realworkordertasks...
    echo '</table>'; // ADDED 2020-02-21 JM
} // END foreach ($employees as $ekey => $employee)

// BEGIN REMOVED 2020-02-21 JM
// echo '</table>';  // NOTE totally bad HTML structure: we open a new table for each employee, but close only the last one!
                  // Should presumably be inside that last foreach (or we can *start* the table outside the foreach loop)
// END REMOVED 2020-02-21 JM                  

// END OUTDENT

            ?>
        </div> <!-- class="full-box clearfix" -->
    </div> <!-- class="main-content" -->
</div> <!-- id="container" --> 
	

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
//  REALLY OUGHT TO refresh automatically - JM 2019-04-02. 
foreach ($statuses as $status) {
?>
    <div class="hide-answer" id="status-<?php echo $status['workOrderStatusId']; ?>" title="<?php echo htmlspecialchars($status['statusName']); ?>">
        <form name="" id="status-<?php echo $status['workOrderStatusId']; ?>-Form" action="" method="post">
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
            // BEGIN REPLACEMENT 2020-06-10 JM, amended 2020-06-15 JM
            if ($status['canNotify']) { // This can be 0 to indicate "don't set any notification at all";
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
        <br />
        Note:
        <textarea cols="20" rows="3" name="note"></textarea>
        <br />
        <input type="button" value="Submit" id="statusNew-<?php echo $status['workOrderStatusId']; ?>" onclick="changeStatusNew('status-<?php echo $status['workOrderStatusId']; ?>')"/>
    </form>
</div>
<?php 
}?>

<script>

$(document).ready(function() {
    $(".hide-answer").dialog({
        autoOpen: false
    });

    <?php /* >>>00026 in line with some remarks above: mouseup is a poor choice here;
             openworkorders.php uses change, which is better; there are other possibilities
             such as on blur, and then check for whether value has changed since focus. 
             Anyway, ought to use something more appropriate than mouseup. */ ?>        
    $(".newstatus").mouseup(function() {
        $(".hide-answer").dialog("close");
        var sel = $(this).val();
        console.log("Opening #status-" + sel);
        $("#status-" + sel + " input[name=workOrderId]").val($(this).data('woid'));
        $("#status-" + sel).dialog('open');
    });

	  
/*
    [BEGIN commented out by Martin before 2019]	  
	    $('.newstatus').change(function () {        


	    	alert('ggg');

		    $(".hide-answer").dialog("close");
		    var sel = $(this).val();
		    console.log("Opening #status-" + sel);
		    $("#status-" + sel + " input[name=workOrderId]").val($(this).data('woid'));
		    $("#status-" + sel).dialog('open');

	    	
	    }).change().bind('mousedown', function () {


		    
	        this.selectedIndex = -1;
	        this.selectedIndex = $(this).find('option:selected').index();
	    });
	    [END commented out by Martin before 2019]
	  */
/*
    [BEGIN commented out by Martin before 2019]
	  $(".newstatus").change(function() {
		alert('fff');
		  $(".hide-answer").dialog("close");
	    var sel = $(this).val();
	    console.log("Opening #status-" + sel);
	    $("#status-" + sel + " input[name=workOrderId]").val($(this).data('woid'));
	    $("#status-" + sel).dialog('open');
	  });
	 [END commented out by Martin before 2019]
*/
});
</script>	

<?php 
include BASEDIR . '/includes/footer.php';
?>