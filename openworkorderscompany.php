<?php 
/* openworkorderscompany.php

   EXECUTIVE SUMMARY: top-level page; shows all uncompleted workOrders, except administrative workOrders. 
   
   Requires admin-level permission for job.
   
   >>>00004 Does not appear to be restricted to workOrders for the current customer!

   Optional input $_REQUEST['sortBy'].
*/

include './inc/config.php';
include './inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_JOB', PERMLEVEL_ADMIN);
	
if (!$checkPerm){
    // No permission, out of here
	header("Location: /panther.php");
}
?>

<style>
.diagopen {
    text-align:center;
}
</style>

<?php 
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='All Open Work Orders';\n</script>\n";
?>

<style>
#max{
max-width:92%;
width: 93%;
margin: 2.5% auto;
text-align:center;
-moz-box-shadow:    0 0 12px 5px #888;
-webkit-box-shadow: 0 0 12px 5px #888;
box-shadow:         0 0 12px 5px #888;
border:1px solid #777;
background-color:#efefef;
}
</style>

<script type="text/javascript">
<?php /* This is for displaying further detail on hover. We'll make an AJAX call and get
         absolutely current information for a particular workOrder. */ ?>
$(function() {
    $( "#expanddialog" ).dialog({  autoOpen: false, width:10, height:20
    });

    $( ".expandopen" ).mouseenter(function() {
        $( "#expanddialog" ).dialog({
            position: { my: "left bottom", at: "left top", of: $(this) },
            autoResize:true ,
            open: function(event, ui) {
                $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
            }
        });
        
        var workOrderId = $(this).attr('name');

        <?php /* on dialog open we first put the AJAX loading symbol, then call /ajax/workorderstatus.php to fill in the dialog. */ ?> 
        $("#expanddialog").dialog("open").html (
            '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').dialog({height:'45', width:'auto'})
            .load('/ajax/workorderstatus.php?workOrderId=' + escape(workOrderId), function(){
                    $('#expanddialog').dialog({height:'auto', width:'auto'});
            });
    });
    
    $( ".expandopen" ).mouseleave(function() {
        $( "#expanddialog" ).dialog("close");
    });

});
</script>

<?php 
$crumbs = new Crumbs(null, $user);
$db = DB::getInstance();

// $workOrders will be an array, one member for each uncompleted workOrder; 
// grouped to keep workOrders for the same job together, ordered by jobId.
$query = " select wo.* ";
$query .= " from " . DB__NEW_DATABASE . ".workOrder wo ";
$query .= " join " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
$query .= " left join " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderId = wos.workOrderId ";
$query .= " left join " . DB__NEW_DATABASE . ".workOrderStatusTime wost on wo.workOrderStatusTimeId = wost.workOrderStatusTimeId ";
/* BEGIN REPLACED 2020-06-12 JM
$query .= " where wost.workOrderStatusId != " . intval(STATUS_WORKORDER_DONE) . " ";
// END REPLACED 2020-06-12 JM
*/
// BEGIN REPLACEMENT 2020-06-12 JM
$query .= " JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderStatusId = wos.workOrderStatusId ";
$query .= " WHERE wos.isDone = 1 ";
// END REPLACEMENT 2020-06-12 JM
$query .= " order by wo.jobId, wo.workOrderId ";

$workOrders = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
	if ($result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {			
			$workOrders[] = $row;
		}
	}
} // >>>00002 ignores failure on DB query! Does this throughout file, 
  // haven't noted each instance.

$currentJobId = 0; // >>>00007 never used, should delete

// RETURNs content of DB table WorkOrderDescriptionType as an array, limited to active types. 
//  Each element is an associative array giving the canonical representation
//  of the appropriate row from DB table WorkOrderDescriptionType (column names as indexes).
$dts = getWorkOrderDescriptionTypes();
// Rework that as an associative array indexed by workOrderDescriptionTypeId.
$dtsi = Array(); // Added 2019-12-02 JM: initialize array before using it!
foreach ($dts as $dt) {
    $dtsi[$dt['workOrderDescriptionTypeId']] = $dt;
}

echo "Hover over status to get detail about the most recent status.<br>If status name has asterisks around it then there's a note associated with this status";
echo '<p>'; // >>>00006 Empty paragraph, never closed. BR would make more sense, or at least <p/>

echo 'S-E =  staff engineer<br>';
echo 'L-E =  lead engineer<br>';
echo 'SU-E =  support engineer<br>';

echo '<p>'; // >>>00006 Empty paragraph, never closed. BR would make more sense, or at least <p/>

echo '<table  style="font-size: 80%;" border="1" cellpadding="0" cellspacing="0">';
    echo '<tr>';
        echo '<td>Job</td>';
        echo '<td>Job Name</td>';
        echo '<td><a id="woCompanyType" href="openworkorderscompany.php?sortBy=type">Type</a></td>';  // (links to sort-by-type, which reloads with different sort)
        echo '<td>Description</td>';
        echo '<td>Workorder/Job Team</td>';
        echo '<td>Engineers</td>';
        echo '<td><a id="woCompanyIntake" href="openworkorderscompany.php?sortBy=intakeDate">Intake</a></td>'; // (links to sort-by-intake-date, which reloads with different sort)
        echo '<td><a id="woCompanyGenesis" href="openworkorderscompany.php?sortBy=genesisDate">Genesis</a></td>'; // (links to sort-by-genesis-date, which reloads with different sort)
        echo '<td><a id="woCompanyDelivery" href="openworkorderscompany.php?sortBy=deliveryDate">Delivery</a></td>'; // (links to sort-by-delivery-date, which reloads with different sort)
        echo '<td>Age</td>';
        echo '<td>Status</td>';
        echo '<td>Extra</td>';
    echo '</tr>';

// time-compare functions to give different sorts.    
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

// If optional input $_REQUEST['sortBy'] is used, apply the appropriate sort.
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

// >>>00006 Weird: $wodts & $workOrderDescriptionTypes are ABSOUTELY IDENTICAL to
// $dts & $dtsi. Get rid of one of these pairs!
$wodts = getWorkOrderDescriptionTypes();
$workOrderDescriptionTypes = array();

foreach ($wodts as $wodt) {
    $workOrderDescriptionTypes[$wodt['workOrderDescriptionTypeId']] = $wodt;
}

foreach ($workOrders as $workOrder) { 
    $j = new Job($workOrder['jobId']);
	
	// [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
    //if ($j->getJobId() != $currentJobId){
		
	//	echo '<tr>';
	//		echo '<td colspan="7"><a href="' . $j->buildLink() . '">' . $j->getName() . '</a></td>';
	//	echo '</tr>';		
	//}
	// [END COMMENTED OUT BY MARTIN BEFORE 2019]
	
    $wo = new WorkOrder($workOrder['workOrderId']);  // WorkOrder object
    
    if ($wo->getWorkOrderDescriptionTypeId() != 12) { // [Martin comment:] administrative
                                                     // >>>00012 might deserve a named constant in inc/config.php
    
        /* BEGIN REPLACED 2020-06-12 JM
        $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(),$wo->getWorkOrderStatusId());
        // END REPLACED 2020-06-12 JM
        */
        // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
        $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(), $wo->isDone());
        // END REPLACEMENT 2020-06-12 JM
        
        $genesisDT = $ret['genesisDT'];
        $deliveryDT = $ret['deliveryDT'];
        $ageDT = $ret['ageDT'];	
        
        echo '<tr>';
            // "Job": Job Number, linked to job.php 
            echo '<td><a id="linkJob'.$j->getJobId().'"  href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>';
            
            // "Job Name"
            echo '<td>' . $j->getName() . '</td>';
        
            // "Type": workOrder description type name, with appropriate color-coded background.  
            $color = "ffffff";        
            if (isset($workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()])){
                $color = $workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()]['color'];
            }
            
            echo '<td bgcolor="' . $color . '">';
                if (isset($dtsi[$wo->getWorkOrderDescriptionTypeId()])){
                    echo '<a id="linkWoType'.$wo->getWorkOrderId().'"  href="' . $wo->buildLink() . '">' . $dtsi[$wo->getWorkOrderDescriptionTypeId()]['typeName'] . "</a>";
                } else {
                    // Accomodate case where type is not defined.
                    echo '<a id="linkWo'.$wo->getWorkOrderId().'"  href="' . $wo->buildLink() . '">---</a>';
                }
            echo '</td>';
            
            // "Description": workOrder description. 
            echo '<td>' . htmlspecialchars($wo->getDescription()) . '</td>';
            
            // "Workorder/Job Team": Table within the table. Successively lists clients, design professionals, EORs. 
            // For each, first column is "C" for client, "D" for design professional, or "E" for EOR. 
            // Second column is "client person name, client company name"; person & company names are 
            //   all linked appropriately to companyPerson.php or company.php.
            // >>>00006: very repetitive, could have common code elimination, probably with other pages as well.
            echo '<td>';
                echo '<table border="1" cellpadding="2" style="font-size: 90%;">';                
                    $clients = $wo->getTeamPosition(TEAM_POS_ID_CLIENT);
                    foreach ($clients as $client){
                        $companyPerson = new CompanyPerson($client['companyPersonId']);
                        echo '<tr>';
                            echo '<td bgcolor="#cccccc">C</td>';
                            if (intval($companyPerson->getCompanyPersonId())) {
                                echo '<td><a id="linkClient'.$companyPerson->getCompanyPersonId().'"   target="_blank" href="' . $companyPerson->buildLink() . '">' . 
                                    $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getCompany()->getName() . '</a></td>';
                            } else {
                                echo '<td></td>';
                            }
                        echo '</tr>';
                    }
                    
                    $pros = $wo->getTeamPosition(TEAM_POS_ID_DESIGN_PRO);
                    foreach ($pros as $pro) {
                        $companyPerson = new CompanyPerson($pro['companyPersonId']);
                        echo '<tr>';
                            echo '<td bgcolor="#cccccc">D</td>';
                            if (intval($companyPerson->getCompanyPersonId())) {
                                echo '<td><a id="linkPro'.$companyPerson->getCompanyPersonId().'" target="_blank" href="' . $companyPerson->buildLink() . '">' . 
                                    $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getCompany()->getName() . '</a></td>';
                            } else {
                                echo '<td></td>';
                            }
                        echo '</tr>';
                    }
                    
                    $eors = $wo->getTeamPosition(TEAM_POS_ID_EOR);
                    foreach ($eors as $eor) {
                        $companyPerson = new CompanyPerson($eor['companyPersonId']);
                        echo '<tr>';
                            echo '<td bgcolor="#cccccc">E</td>';
                            if (intval($companyPerson->getCompanyPersonId())) {
                                echo '<td><a id="linkEor'.$companyPerson->getCompanyPersonId().'"  target="_blank" href="' . $companyPerson->buildLink() . '">' . 
                                    $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getPerson()->getName() . '</a></td>';
                            } else {
                                echo '<td></td>';
                            }
                        echo '</tr>';
                    }
                echo '</table>';
            echo '</td>';
            
            // "Engineers": Similar to preceding column: "S-E" for staff engineers, "L-E" for lead engineer, "SU-E" for support engineers. 
            echo '<td>';
                echo '<table border="1" cellpadding="2" style="font-size: 90%;">';
                
                    // >>>00012 poor choice of name, these are staff engineers not clients
                    $clients = $wo->getTeamPosition(TEAM_POS_ID_STAFF_ENG);
                    foreach ($clients as $client) {
                        $companyPerson = new CompanyPerson($client['companyPersonId']);
                        echo '<tr>';
                            echo '<td bgcolor="#cccccc">S-E</td>';
                            if (intval($companyPerson->getCompanyPersonId())) {
                                echo '<td><a  id="linkStaffEng'.$companyPerson->getCompanyPersonId().'"  target="_blank" href="' . $companyPerson->buildLink() . '">' . 
                                $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getCompany()->getName() . '</a></td>';
                            } else {
                                echo '<td></td>';
                            }
                        echo '</tr>';
                    }
                    
                    $pros = $wo->getTeamPosition(TEAM_POS_ID_LEADENGINEER);
                    foreach ($pros as $pro) {
                        $companyPerson = new CompanyPerson($pro['companyPersonId']);
                        echo '<tr>';
                            echo '<td bgcolor="#cccccc">L-E</td>';
                            if (intval($companyPerson->getCompanyPersonId())) {
                                echo '<td><a id="linkLeadEng'.$companyPerson->getCompanyPersonId().'"  target="_blank" href="' . $companyPerson->buildLink() . '">' . 
                                    $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getCompany()->getName() . '</a></td>';
                            } else {
                                echo '<td></td>';
                            }
                        echo '</tr>';
                    }
                    
                    $eors = $wo->getTeamPosition(TEAM_POS_ID_SUPPORTENGINEER);
                    foreach ($eors as $eor) {
                        $companyPerson = new CompanyPerson($eor['companyPersonId']);
                        echo '<tr>';
                            echo '<td bgcolor="#cccccc">SU-E</td>';
                            if (intval($companyPerson->getCompanyPersonId())) {
                                echo '<td><a id="linkSuportEng'.$companyPerson->getCompanyPersonId().'" target="_blank" href="' . $companyPerson->buildLink() . '">' . 
                                $companyPerson->getPerson()->getFormattedName(1) . '/' . $companyPerson->getPerson()->getName() . '</a></td>';
                            } else {
                                echo '<td></td>';
                            }
                        echo '</tr>';
                    }
                echo '</table>';
            echo '</td>';
            
            // "Intake" (date)
            if ($wo->getIntakeDate() != '0000-00-00 00:00:00'){
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $wo->getIntakeDate());
                $int = $dt->format('M d Y');
            } else {
                $int = '&mdash;';
            }
            echo '<td>' . $int . '</td>';
            
            // "Genesis" (date)
            echo '<td>' . $genesisDT . '</td>';
            
            // "Delivery" (date)
            echo '<td>' . $deliveryDT . '</td>';
            
            // "Age"
            echo '<td>' . $ageDT . '</td>';
            
            // "Status": status of workOrder, with appropriate color-coded background. 
            // Unlike many similar pages, you can't change status here. 
            // Hovering here triggers a dialog that gives an absolutely current 
            //  workOrderStatus (fetched by AJAX). On a separate line under this is status age in days & hours.
            $statusdata = $wo->getStatusData();
            
            $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $statusdata['inserted']);
            $dt2 = new DateTime;
            $interval = $dt1->diff($dt2);
            $statusAge = $interval->format('%dd %hh');
            
            $name = isset($statusdata['statusName']) ? $statusdata['statusName'] : '';
            
            $note = isset($statusdata['note']) ? $statusdata['note'] : '';
            $note = trim($note);
            
            $style = strlen($note) ? 'style="font-weight:bold;font-size:110%"' : "";
            
            $color = isset($statusdata['color']) ? $statusdata['color'] : '';
            $color = trim($color);	
            
            $bgc = strlen($color) ? 'bgcolor="#' . $color  . '"' : "";
            
            if (strlen($style)){
                $name = "**" . $name . "**";
            }
            
            echo '<td ' . $bgc . ' class="expandopen" name="' . $wo->getWorkOrderId() . '" nowrap><span ' . $style . '>' . $name . '</span><br><span style="font-size:90%"  >' . 
                $statusAge . '</span></td>';
            
            /* BEGIN REPLACED 2020-06-08 JM as part of the big conversion of how we do workOrderStatus
            // "Extra": A table within the table. Shows appropriate text for any extraEors for this workOrder; 
            // see wiki documentation of DB table WorkOrderStatusTime if you wish to know more.
            echo '<td ' . $bgc . ' >';
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
            // END REPLACED 2020-06-08 JM
            */
            // BEGIN REPLACEMENT 2020-06-08 JM
            echo '<td ' . $bgc . ' >';
            if ($statusdata['customerPersonArray']) {
                echo '<table>';
                foreach($statusdata['customerPersonArray'] AS $customerPersonData) {
                    echo '<tr><td valign="top">&gt;</td><td valign="top">' . $customerPersonData['legacyInitials'] . '</td></tr>';
                }
                echo '</table>';
            }
            // END REPLACEMENT 2020-06-08 JM
            echo '</td>';
        echo '</tr>';
    } // END not administrative
	
	//$currentJobId = $j->getJobId(); // [COMMENTED OUT BY MARTIN BEFORE 2019]
	
} // END foreach ($workorders...

echo '</table>';

// expanddialog below is just a shell to be filled in on hover
?>
<div id="expanddialog">
</div>

<?php 
include BASEDIR . '/includes/footer.php';
?>