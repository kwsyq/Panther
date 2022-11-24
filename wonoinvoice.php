<?php
/* wonoinvoice.php

   EXECUTIVE SUMMARY: "Quick & dirty" report on workOrders where there is no invoice of any sort 
   and job.number > 's12' (meaning 2012 onward). Requires admin-level permission for job.
*/

include './inc/config.php';
include './inc/perms.php';

// BEGIN COMPARISON FUNCTIONS
function cmpIntake($a, $b) {
	$date1 = new DateTime($a['intakeDate']);
	$date2 = new DateTime($b['intakeDate']);

	if ($date1 == $date2) {
		return 0;
	}
	return ($date1 < $date2) ? -1 : 1;
}

function cmpGenesis($a, $b) {
	$date1 = new DateTime($a['genesisDate']);
	$date2 = new DateTime($b['genesisDate']);

	if ($date1 == $date2) {
		return 0;
	}
	return ($date1 < $date2) ? -1 : 1;
}

function cmpDelivery($a, $b) {
	$date1 = new DateTime($a['deliveryDate']);
	$date2 = new DateTime($b['deliveryDate']);

	if ($date1 == $date2) {
		return 0;
	}
	return ($date1 < $date2) ? -1 : 1;
}

function cmpType($a, $b) {
	if ($a['workOrderDescriptionTypeId'] == $b['workOrderDescriptionTypeId']) {
		return 0;
	}
	return ($a['workOrderDescriptionTypeId'] < $b['workOrderDescriptionTypeId']) ? -1 : 1;
}
// END COMPARISON FUNCTIONS

$checkPerm = checkPerm($userPermissions, 'PERM_JOB', PERMLEVEL_ADMIN);

if (!$checkPerm){
    // No permission, out of here
    header("Location: /panther.php");
}
?>

<?php /* BEGIN REMOVED 2020-11-17 JM, wasn't called any longer
INPUT workOrderId
Calls /ajax/toggle_fakeinvoice.php
NOTE that there is not much feedback on how this goes.
<script>
var setFakeInvoice = function (workOrderId) {
    $.ajax({
        url: '/ajax/toggle_fakeinvoice.php',
        data:{
            workOrderId:workOrderId
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            // BEGIN commented out by Martin before 2019
            //if (data['status'] == 'success'){
            //} else {
            //	alert('there was a problem updating');
            //}
            // END commented out by Martin before 2019
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
}
</script>
// END REMOVED 2020-11-17 JM
*/

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='WO - no invoice';\n</script>\n";

$crumbs = new Crumbs(null, $user);

$db = DB::getInstance();

$query = "SELECT wo.* ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
$query .= "JOIN " . DB__NEW_DATABASE . ".job j ON wo.jobId = j.jobId ";
$query .= "WHERE wo.InvoiceTxnId IS NULL ";
$query .= "AND j.number > 's12' ";
$query .= "ORDER BY wo.genesisDate DESC;";

$workOrders = array();

$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $workOrders[] = $row;
    }
} else {
    $logger->errorDb('1605655478', 'Hard DB error', $db);
}

$currentJobId = 0;

$dts = getWorkOrderDescriptionTypes();
$dtsi = Array(); // Added 2019-12-02 JM: initialize array before using it!
foreach ($dts as $dt) {
    $dtsi[$dt['workOrderDescriptionTypeId']] = $dt;	
}

echo 'Might not be 100% accurate until all Quickbooks invoices mapped to workorders.<p>Only showing 2012 onwards';
echo '<table  style="font-size: 80%;" border="1" cellpadding="0" cellspacing="0">';
    echo '<tr>';
        echo '<td>&nbsp;&nbsp;&nbsp;</td>';
        echo '<td>Job Name</td>';
        echo '<td><a id="woNoInvoiceType" href="wonoinvoice.php?sortBy=type">Type</a></td>'; // this & 3 like it below are linked to sort table
        echo '<td>Team</td>';
        echo '<td>Description</td>';
        echo '<td><a id="woNoInvoiceIntake" href="wonoinvoice.php?sortBy=intakeDate">Intake</a></td>';
        echo '<td><a id="woNoInvoiceGenesis" href="wonoinvoice.php?sortBy=genesisDate">Genesis</a></td>';
        echo '<td><a id="woNoInvoiceDelivery" href="wonoinvoice.php?sortBy=deliveryDate">Delivery</a></td>';
        echo '<td>Age</td>';
        echo '<td>Status</td>';
        echo '<td>Minutes</td>';
        echo '<td></td>';
        echo '<td>Fake Inv</td>';
    echo '</tr>';
    
    $sortBy = isset($_REQUEST['sortBy']) ? $_REQUEST['sortBy'] : '';
    
    if ($sortBy == 'intakeDate'){
        uasort($workOrders, 'cmpIntake');
    }
    if ($sortBy == 'genesisDate'){
        uasort($workOrders, 'cmpGenesis');
    }
    if ($sortBy == 'deliveryDate'){
        uasort($workOrders, 'cmpDelivery');
    }
    if ($sortBy == 'type'){
        uasort($workOrders, 'cmpType');
    }
    
    $lost = 0;  // total time over all such invoices
    
    foreach ($workOrders as $workOrder) {    
        $query = "SELECT * ";
        $query .= "FROM " . DB__NEW_DATABASE . ".invoice  ";
        $query .= "WHERE workOrderId = " . intval($workOrder['workOrderId']) . " ORDER BY invoiceId;";
        
        $invoices = array();
        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()){
                $invoices[] = $row;
            }
        } else {
            $logger->errorDb('1605655562', 'Hard DB error', $db);
        }
        
        // A "fake" invoice is old stuff: we won't be creating new ones.
        // They come from before our DB modeled invoices. They are a way to make
        //  a pseudo-invoice for a workOrder that had no invoice associated.
        // 2020-04-02 Ron says we have long since generated the "fake" invoices for
        //  any workOrders that needed the, so we are cutting out a bunch of code below here,
        //  while still making sure we don't end up with an unexplained silence from the system
        //  in the unlikely event that someone tries to use this feature..
        // 
        // If we don't have a new-style invoice for this workOrder...
        if (!count($invoices)) {
            // ... and the workOrder is completed (workOrder Status 9 = done)...
            /* BEGIN REPLACED 2020-11-18 JM
            if ($workOrder['workOrderStatusId'] == 9) {
            // END REPLACED 2020-11-18 JM
            */
            // BEGIN REPLACEMENT 2020-11-18 JM
            if (WorkOrder::workOrderStatusIsDone($workOrder['workOrderStatusId'])) {                
            // END REPLACEMENT 2020-11-18 JM    
                // ... and we don't have it listed as already having a "fake" invoice...
                if (!intval($workOrder['fakeInvoice'])) {
                    // BEGIN ADDED 2020-04-02 JM
                    // As of 2020-04-02, we shouldn't be here, but handle it gracefully
                    $logger->warn2('1585868445', 
                        "Trying to generate a fake invoice; we no longer do that. workOrderId=" . $workOrder['workOrderId']);
                    
                    echo '<p>This is an old workOrder with no invoice. Typically, those should no longer concern us as of 2020. ' .
                         'Please contact a developer or administrator and tell that that this message came up for workOrder ' .
                         $workOrder['workOrderId'];
                    // END ADDED 2020-04-02 JM
                    
                    /* BEGIN REMOVED 2020-04-02 JM
                    // Add up total time for this workOrderTask.
                    $totaltime = 0;
                    $wo = new WorkOrder($workOrder['workOrderId']);
                    $workOrderTasks = $wo->getWorkOrderTasksRaw();
                    foreach ($workOrderTasks as $wotkey => $workOrderTask){
                        $times = $workOrderTask->getWorkOrderTaskTime();
                        foreach ($times as $tkey => $time){
                            $totaltime += intval($time['minutes']);
                        }
                    }
                    if (intval($totaltime)) {
                        
// BEGIN OUTDENT: there is some time for this workOrderTask, it's all done, and there doesn't seem to be any sort of invoice 

$j = new Job($workOrder['jobId']);
$num = $j->getNumber();

// >>>00014 some sort of special cases, not sure what this is about.
if ((substr($num, -5) != '00000') &&  (substr($num, -6) != '00000b')) {
    $lost += $totaltime;
    // BEGIN commented out by Martin before 2019                
    //if ($j->getJobId() != $currentJobId){

    //	echo '<tr>';
    //		echo '<td colspan="7"><a href="' . $j->buildLink() . '">' . $j->getName() . '</a></td>';
    //	echo '</tr>';                
    //}
    // END commented out by Martin before 2019

    $wo = new WorkOrder($workOrder['workOrderId']); // >>>00006 appears to be redundant
    $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(),$wo->getWorkOrderStatusId());

    $genesisDT = $ret['genesisDT'];
    $deliveryDT = $ret['deliveryDT'];
    $ageDT = $ret['ageDT'];

    echo '<tr>';
        // (no header) Job Number, linked to page for job
        echo '<td><a href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>';
        // "Job Name"
        echo '<td>';    
            echo $j->getName();        
        echo '</td>';

        // "Type": WorkOrderDescriptionType name, linked to workOrder page. NOTE that this is a link even if it is blank.
        //  >>>00006 blank link is not the best UI, ought to have some symbol
        echo '<td>';
        if (isset($dtsi[$wo->getWorkOrderDescriptionTypeId()])){
            echo '<a target="_blank" href="' . $wo->buildLink() . '">' . $dtsi[$wo->getWorkOrderDescriptionTypeId()]['typeName'] . "</a>";
        } else {
            echo '<a href="' . $wo->buildLink() . '">&nbsp;</a>';
        }
        echo '</td>';

        // "Team": subtable,  successively lists:
        //   * client: labeled "C", formatted person name + company name, linked to companyPerson.
        //   * design professional: labeled "D", formatted person name + company name, linked to companyPerson.
        //   * engineer of record: labeled "E", formatted person name + company name, linked to companyPerson. 
        echo '<td>';
            echo '<table border="1" cellpadding="2" style="font-size: 90%;">';
                $clients = $wo->getTeamPosition(TEAM_POS_ID_CLIENT);    
                foreach ($clients as $ckey => $client) {
                    $companyPerson = new CompanyPerson($client['companyPersonId']);
                    echo '<tr>';
                    echo '<td bgcolor="#cccccc">C</td>';
                    echo '<td><a target="_blank" href="' . $companyPerson->buildLink() . '">' . $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getCompany()->getName() . '</a></td>';
                    echo '</tr>';
                }
                
                $pros = $wo->getTeamPosition(TEAM_POS_ID_DESIGN_PRO);    
                foreach ($pros as $pkey => $pro){
                    $companyPerson = new CompanyPerson($pro['companyPersonId']);
                    echo '<tr>';
                    echo '<td bgcolor="#cccccc">D</td>';
                    echo '<td><a target="_blank" href="' . $companyPerson->buildLink() . '">' . $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getCompany()->getName() . '</a></td>';
                    echo '</tr>';
                }    

                $eors = $wo->getTeamPosition(TEAM_POS_ID_EOR);    
                foreach ($eors as $ekey => $eor){
                    $companyPerson = new CompanyPerson($eor['companyPersonId']);
                    echo '<tr>';
                    echo '<td bgcolor="#cccccc">E</td>';
                    echo '<td><a target="_blank" href="' . $companyPerson->buildLink() . '">' . $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getPerson()->getName() . '</a></td>';
                    echo '</tr>';
                }
            echo '</table>';
        echo '</td>';
        
        // "Description" WorkOrder description
        echo '<td>' . htmlspecialchars($wo->getDescription()) . '</td>';
        
        // "Intake": date
        if ($wo->getIntakeDate() != '0000-00-00 00:00:00'){
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $wo->getIntakeDate());
            $int = $dt->format('M d Y');
        } else {
            $int = '&mdash;';
        }
        echo '<td>' . $int . '</td>';
        
        // "Genesis": date
        echo '<td>' . $genesisDT . '</td>';
        
        // "Delivery": date
        echo '<td>' . $deliveryDT . '</td>';
        
        // "Age": calculated elapsed time
        echo '<td>' . $ageDT . '</td>';
        
        // "Status": workOrderStatus name
        echo '<td>' . $wo->getStatusName() . '</td>';
        
        // "Minutes": actually hours, with two digits past the decimal point.
        echo '<td style="text-align: right" nowrap>' . number_format($totaltime/60,2) . ' hr</td>';

        // (no heading) 
        // >>>00014 JM 2019-04-10: how can the query here ever find any invoice? It appears to me to be 
        //  the same test we did above, and there we didn't drill down if this came back with any results.
        // >>>00012: also note we are using $invoices for a second time, sort of multiplexing (though I suppose it's the same thing, in a way)
        //
        // Anyway, if we get something here, there's a subtable...
        echo '<td>';
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".invoice  ";
            $query .= " where workOrderId = " . intval($wo->getWorkOrderId()) . " order by invoiceId ";
        
            $invoices = array();
            
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $invoices[] = $row;
                    }
                }
            }
            echo '<table border="1" cellpadding="0" cellspacing="0">';
                foreach ($invoices as $invoice) {
                    echo '<tr>';
                        // Link to invoice page; displays invoice total in U.S. currency with a dollar sign preceding, two digits after the decimal
                        echo '<td><a target="_blank" href="/invoice/' . $invoice['invoiceId'] . '">$' . $invoice['total'] . '</a></td>';
                        // Date inserted, in "Y-m-d" form
                        echo '<td>' . date("Y-m-d", strtotime($invoice['inserted'])) . '</td>';
                    echo '</tr>';
                }
            echo '</table>';
        echo '</td>';

        // >>>00014: I (JM) don't see how $checked can ever be true, doesn't test above rule this out? 
        $checked = (intval($workOrder['fakeInvoice'])) ? ' checked ' : '';
    
        // "Fake Inv": checkbox to toggle "fake invoice". I (JM) believe that we only see it here if there was initially no fake invoice,
        //  but since it doesn't do any page update I guess once we see a workorder on the page, it doesn't go away without a page refresh.
        echo '<td ><input type="checkbox" name="fakeinvoice_' . $workOrder['workOrderId'] . '"' . 
             ' value="' . $workOrder['workOrderId']  . '" ' . $checked . ' onClick="setFakeInvoice(' . $workOrder['workOrderId'] . ')"></td>';

    echo '</tr>';
} // END if ((substr($num, -5) != '00000') &&  (substr($num, -6) != '00000b'))
// END OUTDENT

                    } // END if (intval($totaltime))
                //END REMOVED 2020-04-02 JM 
                */    
                }
            }
            //$currentJobId = $j->getJobId(); // Commented out by Martin before 2019
        }
    }

echo '</table>';

echo $lost;

include BASEDIR . '/includes/footer.php';
?>
