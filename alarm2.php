<?php
/* alarm2.php

   EXECUTIVE SUMMARY: This is a top-level page. 
   Looks at how much time has elapsed since a workOrder status change; 
   supports notification if it stays in a particular status longer than expected.
   
   ARGUMENTS TO THIS PAGE:
   * No primary input.
   * Optional $_REQUEST['act']. Only possible value is 'snooze'. If that is set, 
     uses $_REQUEST['workOrderStatusTimeId'] and $_REQUEST['increment'].
     
   This was heavily reworked 2020-06-12 JM, and I haven't bothered maintaining history
   on each change; use the SVN repository if you want to see how it was before. Still needs
   more cleanup including logging, etc., I didn't fix everything ugly here, but it's a lot
   saner than before.
*/

require_once './inc/config.php';
require_once './inc/access.php';
?>

<?php
// INPUT $personId, $customerId
// RETURN legacyInitials (short string)
// NOTE that there is really nothing "legacy" about legacyInitials, they are a perfectly current part of the system as of version 2020-2.
function getLegacyInitials($personId) {
    $customerPerson = CustomerPerson::getFromPersonId($personId);
    if ($customerPerson) {
        return $customerPerson->getLegacyInitials();
    } else {
        // error is already logged
        return '';
    }
}

// NOTE that the various arrays here that represent DB table contents will all be
//  appropriately ordered for display
// $statuses = workOrderStatuses(); // REMOVED 2020-06-18 JM, never used. 
$crumbs = new Crumbs(null, $user);          // Crumbs object
$wodts = getWorkOrderDescriptionTypes();    // Array, each row is canonical representation of a row in DB table WorkOrderDescriptionType.

// WorkOrderDescriptionType reorganized to be indexed by workOrderDescriptionTypeId 
$workOrderDescriptionTypes = array();
foreach ($wodts as $wodt) {	
	$workOrderDescriptionTypes[$wodt['workOrderDescriptionTypeId']] = $wodt;	
}

if ($act == 'snooze') {
    // Arrive here from a self-call
    // Requires that $_REQUEST['workOrderStatusTimeId'] is nonzero and
    //  $_REQUEST['increment']is numeric, fails otherwise.    
	$workOrderStatusTimeId = isset($_REQUEST['workOrderStatusTimeId']) ? intval($_REQUEST['workOrderStatusTimeId']) : 0;
	$increment = isset($_REQUEST['increment']) ? $_REQUEST['increment'] : 0;

	if (intval($workOrderStatusTimeId)) {
		if (is_numeric($increment)) {
			$db = DB::getInstance();

			// In the specified row in DB table workOrderStatusTime, add the specified increment 
			//  to the value already in the snooze column. 
			$query = " update " . DB__NEW_DATABASE . ".workOrderStatusTime set  ";
			$query .= " snooze = snooze + " . $db->real_escape_string($increment) . " ";
			$query .= " where workOrderStatusTimeId = " . intval($workOrderStatusTimeId);

			$db->query($query); 
			// >>>00002 presumably should check the result of that query; 
			// * if it failed,there's a DB problem; 
			// * if it succeeded but didn't affect any row, $workOrderStatusTimeId is invalid. 
			// Should distinguish & log.
		} // >>>00002 presumably should log the bad input
	} // >>>00002 presumably should log the bad input

	header("Location: alarm2.php");
}

$db = DB::getInstance(); // >>>00006 if we did this above, we wouldn't have to do it inside "snooze" case.

// Select all workOrders whose status indicates they are not done/closed, and group them by JobId 
//  (effectively, chronological by when the Job was first entered into the DB, 
//  >>>00006 but we do something later that makes this order completely irrelevant).
$query = "SELECT wo.* ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
$query .= "JOIN " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
/* BEGIN REMOVED 2020-06-12 JM
$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderStatusView wosv on wo.workOrderId = wosv.workOrderId ";
// END REMOVED 2020-06-12 JM
*/
$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderStatusTime wost on wo.workOrderStatusTimeId = wost.workOrderStatusTimeId ";
/* BEGIN REPLACED 2020-06-12 JM
$query .= " where wost.workOrderStatusId != " . intval(STATUS_WORKORDER_DONE) . " ";
// END REPLACED 2020-06-12 JM
*/
// BEGIN REPLACEMENT 2020-06-12 JM
$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderStatusId = wos.workOrderStatusId ";
$query .= "WHERE wos.isDone != 1 ";
// END REPLACEMENT 2020-06-12 JM
$query .= "ORDER BY wo.jobId, wo.workOrderId ";

// Array $workOrders will contain the canonical associative array representation 
//  of the relevant rows from the WorkOrder DB table.
$workOrders = array();
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1591992807', 'Hard DB error', $db);
    echo "Hard DB error, see log";
    die();
} 
while ($row = $result->fetch_assoc()) {
    $workOrders[] = $row;
}

// We then loop through $workOrders, leaving out those with workOrderDescriptionTypeId = 12 (administrative).   
$finals = array();
foreach ($workOrders as $workOrder) {
	if ($workOrder['workOrderDescriptionTypeId'] != 12) {  // administrative
		// Instantiate a WorkOrder object, get its statusData, and use that to get its "grace" value 
		$wo = new WorkOrder($workOrder['workOrderId']);		
		$statusData = $wo->getStatusData();
		$grace = $statusData['grace'];
		// NOTE: null or zero $grace means we don't want to use "grace" for here.
		if (!$grace) {
		    $grace = false; // Let's keep this to one way to represent this here.
		}
		
		// Instantiate the appropriate Job object
		$job = new Job($wo->getJobId());
		
        // Build and insert an element in numerically-indexed array $finals.
        // The element is itself an associative array, which we build by using a
        //  temporary associative array $current.
        //
        // * $workOrder is the canonical representation of a row from DB table workOrder 
        //   as an associative array. We will stick that in the array as $current['workOrder']
        // 
        // * $statusData is another associative array containing:
        //     * From workOrderStatusTime:
        //       * 'workOrderStatusTimeId', 'workOrderStatusId', 'workOrderId',
        //       * 'extra' (vestigial)
        //       * 'inserted', 'personId', 'note', 'snooze'
        //     * From workOrderStatus
        //       * 'grace', 'statusName', 'isDone', 'canNotify', 'successorId'
        //     * 'customerPersonArray' has as its value an array of associative arrays describing any
        //        related customerperson; that last array has two indexes, 'customerPersonId' and 'legacyInitials'.
        // Some of that we don't care about (e.g. personId, successorId), but it's there. We will stick
        //  that in the array as current ['statusData'].
        // 
        // -------------------------------------------------------------------------------------
        // From these and other places, we build $current as an associative array with indexes:
        // * 'workOrder': the entire associative array for this workOrder, which we got back from the initial SQL query
        // * 'statusName'
        // * 'legacyInitials': an array of the legacyInitials for any customerPerson associated with the status
        // * 'inserted': status date in 'm/d/Y' form
        // * 'adjustedGrace':
        //      Assuming $grace has a non-false value, this is the product of the grace value from the status and the 
        //       snooze value for this particular workOrder. So if something has a normal grace value of 2 days, and 
        //       the snooze value is 3, this will be 6 (days).
        //      If $grace is false, it means we never want an "alarm" here 
        //  * 'overunder': 'same' if $grace is false; otherwise, 'over' if grace period has been exceeded, 'under' otherwise.
        //  * 'statusAge': integer number of days since this status was set (row was inserted in DB table WorkOrderStatusTime).
        //  * 'workOrderStatusTimeId': from the statusData.		
		$current = array();
		$current['workOrder'] = $workOrder;
		$current['statusName'] = isset($statusData['statusName']) ? $statusData['statusName'] : '';
		$current['legacyInitials'] = Array();
		foreach ($statusData['customerPersonArray'] AS $customerPersonData) {
		    $current['legacyInitials'][] = $customerPersonData['legacyInitials'];
		}
		$current['inserted'] = date("m/d/Y", strtotime($statusData['inserted']));				
		if ($grace === false) {
		    // Beginning in v2020-3, we deliberately use this to say "never raise an alarm here" 
			$current['adjustedGrace'] = 'N/A';
		} else {
		    $current['adjustedGrace'] = intval($grace * $statusData['snooze']);
		}
		
		$dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $statusData['inserted']);
		$dt2 = new DateTime;
		$interval = $dt1->diff($dt2);		
		// $statusDays = $interval->format('%d'); // REMOVED JM 2020-06: I think this was ill-conceived on Martin's part.		                                          
		$statusAge = $interval->format('%a');
		
		$current['overunder'] = 'same';		
		if ($grace !== false) {
			$gracecalc = intval($grace * $statusData['snooze']);
			
			// $bgc = (($statusDays - $gracecalc + 1) > 0) ? 'over' : 'under'; // REPLACED BY NEXT LINE JM 2020-06: I think this was ill-conceived on Martin's part.			
			$bgc = (($statusAge - $gracecalc + 1) > 0) ? 'over' : 'under';
			
			$current['overunder'] = $bgc;
		}
		
		$current['statusAge'] = false;		
		if ($grace !== false) {
			$current['statusAge'] = $statusAge;
		}
		
		$current['workOrderStatusTimeId'] = intval($statusData['workOrderStatusTimeId']);		
		
		$finals[] = $current;
	}
}

// Re-sort the $finals array to put first all rows that are 'over', then all that are 'same', 
//  then all that are 'under'. Note that current code relies on these values coming in 
//  alphabetical order, and that this completely overrides the grouping by Job we did in the 
//  SQL query above.
function finalcmp($a, $b) {
    if ($a['overunder'] == $b['overunder']) {
        return 0;
    }
    return ($a['overunder'] < $b['overunder']) ? -1 : 1;
}
usort($finals, "finalcmp");

// $finals data is then displayed in a sortable table, with the following columns; 
//  in filling out this table, we once again instantiate the appropriate WorkOrder and Job objects as we loop through:
//
//  * Job/WorkOrder: '[Job Number] Job name'; all linked to web page for Job. 
//    Note that despite the column heading, there is nothing here specific to the workOrder.
//  * Type: Typename for WorkOrderDescriptionType, background color differs for different values.
//  * DesPro: Design professional for this job. Code presumes there is at most one, 
//    ignores any others. Displays as formatted person name, comma, company name.
//  * Client: Client for this job. Code presumes there is at most one, ignores any others. 
//    Displays as formatted person name, comma, company name.
//  * EOR: We go through some unneeded code here to format names, but in practice, 
//    for each engineer of record on this job, we give their legacyInitials and a 
//    link to open their companyPerson page in a new tab/window. If there are multiple EORs, 
//    they are separated by HTML BR elements, so this can be a multiline value.
//  * Staff: A similar potentially multiline set of legacyInitials for supporting engineers, 
//    lead engineers, and staff engineers. Each of those three groups will be grouped together 
//    (so, for example, we don't get a lead engineer between two staff engineers), 
//    but nothing in the code guarantees a particular ordering of the three groups.
//  * WO-Age: >>>00006 oddly, recalculated here instead of using 
//    $finals[ii]['statusAge'], but the value should be the same.
//  * Status: $finals[ii]['statusName']
//  * Extra: From $finals[ii]['legacyInitials']. A nested table. Each row has two columns, containing:
//      * just the character '>'
//      * the legacy initials 
//  * Stat-Date: $finals[ii]['inserted']: status date in 'm/d/Y' form
//  * Alarm: $finals[ii]['adjustedGrace']: grace in days, including any prior 'snooze' factor; also can be "N/A"
//  * Age: $finals[ii]['statusAge'], in days; appropriate background color based on $finals[ii]['overunder'].
//  * (no header):
//      * If $finals[ii]['adjustedGrace'] is numeric, displays 'snooze ' and is a self-submitting link
//      * Otherwise blank. 

?>
<?php
include_once BASEDIR . '/includes/header.php';

?>
<script>
    document.title = 'Alarms';
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
<script>
$(document).ready(function()
{
    $("#tablesorter-demo").tablesorter();
}
); 
</script>

<script type="text/javascript" src="/js/jquery.tablesorter.min.js"></script>
	<div id="container" class="clearfix">
		<div class="main-content">
			<div class="full-box clearfix">
			<h2 class="heading">Alarms</h2> 
<?php

echo '<center>';
// BEGIN table level 0
echo '<table border="0" cellpadding="5" cellspacing="0" id="tablesorter-demo" class="tablesorter" style="font-size: 90%;">';
echo '<thead>';
echo '<tr>';
echo '<th>Job/WorkOrder</th>';
echo '<th>Type</th>';

echo '<th>DesPro</th>';
echo '<th>Client</th>';
echo '<th>EOR</th>';
echo '<th>Staff</th>';
echo '<th nowrap>WO-Age</th>';
echo '<th>Status</th>';
echo '<th>Extra</th>';
echo '<th>Stat-Date</th>';
echo '<th>Alarm</th>';

echo '<th nowrap>Age</th>';
echo '<th></th>';

echo '</tr>';
echo '</thead>';
echo '<tbody>';
foreach ($finals as $fkey => $final) {    
    $job = new Job($final['workOrder']['jobId']);
    $wo = new WorkOrder($final['workOrder']['workOrderId']);
    
    $clientString = '';
    $clients = $job->getTeamPosition(TEAM_POS_ID_CLIENT, 1);
    $clients = array_slice($clients,0,1);
    if (count($clients)){
        $client = $clients[0];
        if (isset($client['companyPersonId'])){
            $cp = new CompanyPerson($client['companyPersonId']);
            if (intval($cp->getCompanyPersonId())){
                $p = $cp->getPerson();
                $c = $cp->getCompany();
                $clientString = $p->getFormattedName(1) . ", " . $c->getName();
            }
        }
    }    
    
    $designProString = '';
    $pros = $job->getTeamPosition(TEAM_POS_ID_DESIGN_PRO, 1);
    $pros = array_slice($pros,0,1);
    if (count($pros)){
        $pro = $pros[0];
        if (isset($pro['companyPersonId'])){
            $cp = new CompanyPerson($pro['companyPersonId']);
            if (intval($cp->getCompanyPersonId())){
                $p = $cp->getPerson();
                $c = $cp->getCompany();
                $designProString = $p->getFormattedName(1) . ", " . $c->getName();
            }
        }
    }
    
    $color = "ffffff";
    $tn = '';
    if (isset($workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()])){
        $color = $workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()]['color'];
        $tn = $workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()]['typeName'];        
    }
    
    echo '<tr>';
        // Job/WorkOrder
        echo '<td nowrap ><a id="linkJobId'.$fkey.$job->getJobId().'" href="' . $job->buildLink() . '">[' . $job->getNumber() . ']&nbsp;' . $job->getName() . '</a></td>';
        // Type
        echo '<td bgcolor="' . $color . '">' . $tn . '</td>';        
        
        // DesPro
        echo '<td>' . $designProString . '</td>';
        
        // Client
        echo '<td>' . $clientString . '</td>';
        
        // EOR
        echo '<td>';        
        $eors = $wo->getTeamPosition(TEAM_POS_ID_EOR); 
   
        foreach ($eors as $ekey => $eor){
            $companyPerson = new CompanyPerson($eor['companyPersonId']);
            $formattedName = '';
            if ($companyPerson->getPerson()){
                $formattedName = $companyPerson->getPerson()->getFormattedName(1);
            }
          
            $le = getLegacyInitials($companyPerson->getPerson()->getPersonId());
            
            echo '<a id="linkCpId'.$fkey.$eor['id'].'" target="_blank" href="' . $companyPerson->buildLink() . '">' . $le . '</a><br>';
        }
        echo '</td>';
        
        // Staff
        echo '<td>';        
        $engineers = array();
        $supportingEngineers = $wo->getTeamPosition(TEAM_POS_ID_SUPPORTENGINEER, false);
        $leadEngineers = $wo->getTeamPosition(TEAM_POS_ID_LEADENGINEER, false);
        $staffEngineers = $wo->getTeamPosition(TEAM_POS_ID_STAFF_ENG, false);
        
        foreach ($supportingEngineers as $seey => $supportingEngineer){
            $cp = new CompanyPerson($supportingEngineer['companyPersonId']);
            $engineers['staff'][] = $cp;
        }
        foreach ($leadEngineers as $leey => $leadEngineer){
            $cp = new CompanyPerson($leadEngineer['companyPersonId']);
            $engineers['lead'][] = $cp;
        }
        foreach ($staffEngineers as $seey => $staffEngineer){
            $cp = new CompanyPerson($staffEngineer['companyPersonId']);
            $engineers['staff'][] = $cp;            
        }
        
        foreach ($engineers as $ekey => $group){
            foreach ($group as $gkey => $engineer){
                $le = getLegacyInitials($engineer->getPersonId());
                
                if (strlen($le)) {
                    echo $le . '(' . $ekey . ')<br>';
                }
            }
        }
    echo '</td>';
        
        $insertTime = '';
        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".workOrderStatusTime ";
        $query .= "WHERE workOrderId = " . intval($wo->getWorkOrderId()) . " ";
        $query .= "ORDER BY workOrderStatusTimeId ASC LIMIT 1;";  // NOTE: 'ASC' not 'DESC': we are going for when this workOrder was first given   
                                                                  // its initial status, NOT when it got its current status

        $result = $db->query($query);
        if ($result) {
            if ($result->num_rows > 0){
                $row = $result->fetch_assoc();
                $insertTime = $row['inserted'];                
            }
        } else {
            $logger->errorDb('1591993030', 'Hard DB error', $db);
            echo '</td></tr></table><br /><div style="color:red; font-weight:bold">Hard DB error, see log</div>';
            die();
        }
        
        // WO-Age
        echo '<td>';        
        if (strlen($insertTime)) {            
            $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $insertTime);
            $dt2 = new DateTime;
            $interval = $dt2->diff($dt1);
            
            $statusAge = $interval->format('%a');
            
            if ($statusAge != $final['statusAge']) {
                $logger->info2('JDEBUG 1', "$statusAge != {$final['statusAge']}? ". ($statusAge == $final['statusAge'] ? 'Yes' : 'NO!'));
            }
            
            echo $statusAge;
        }        
        echo '</td>';
        
        // Status
        echo '<td>' . $final['statusName'] . '</td>';
        
        // Extra
        echo '<td>';
        $legacyInitialsArray= $final['legacyInitials'];
            // BEGIN table level 1
            echo '<table border="0" cellpadding="1" cellspacing="0">';
            foreach ($legacyInitialsArray AS $legacyInitials) {            
                echo '<tr><td valign="top">&gt;</td><td valign="top">' . $legacyInitials . '</td></tr>';
            }
            // END table level 1
        echo '</table>';
        echo '</td>';
        
        //Stat-Date
        echo '<td>' . $final['inserted'] . '</td>';
        
        // Alarm
        echo '<td style="text-align:center">' . $final['adjustedGrace'];
        echo '</td>';
        
        // Age
        $bgc = '';        
        if ($final['overunder'] == 'over'){
            $bgc = ' bgcolor="#ffcccc" ';
        }
        if ($final['overunder'] == 'under'){
            $bgc = ' bgcolor="#ccffcc" ';
        }
        echo '<td ' . $bgc . ' nowrap>' . $final['statusAge'] . '</td>';        
        
        // (no header), 'snooze' link if appropriate
        echo '<td>';        
        if (is_numeric($final['adjustedGrace']) && $final['adjustedGrace'] < 10000000) {
            //  Displays 'snooze ' as a self-submitting link
            echo '<a id="linkSnooze' . intval($final['workOrderStatusTimeId']) . '" href="alarm2.php?act=snooze&increment=1&workOrderStatusTimeId=' . intval($final['workOrderStatusTimeId']) . '">snooze</a>';
        } else {
            echo '&nbsp;';
        }        
        echo '</td>';
    echo '</tr>';
}

echo '<tbody>';
// END table level 0
echo '</table>';
echo '</center>';
?>
			</div>	
		</div>
	</div>

<?php 
include_once BASEDIR . '/includes/footer.php';
?>