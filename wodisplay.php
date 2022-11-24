<?php
/*  wodisplay.php

    EXECUTIVE SUMMARY: Top-level page. Refreshes every 10 seconds, and cycles through workOrderDescriptionTypes: 
        2 ("comments"), 5 ("RFI"), 6 ("Modifications"), 9 ("Review").

    Requires admin-level permission for job.

    INPUT: $_REQUEST['workOrderDescriptionTypeId'], $_REQUEST['cycle'], $_REQUEST['sortby'].

    For each workOrder matching $_REQUEST['workOrderDescriptionTypeId'] (using join with job) displays genesis, age, etc., job number & name, 
    workOrderDescriptionType, workOrderDescription. Can display sorted by intake, genesis, delivery, or (probably uselessly) workOrderDescriptionType. 
    (Along the way, fills in an array $workOrderDescriptionTypes w/ rows from table workOrderDescription, which is vestigial.)
    The usual color-coding for workOrderDescriptionTypes.

*/
include './inc/config.php';
include './inc/perms.php';

// BEGIN comparison functions
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
// END comparison functions

$checkPerm = checkPerm($userPermissions, 'PERM_JOB', PERMLEVEL_ADMIN);
	
if (!$checkPerm){
	header("Location: /panther.php");
}

$workOrderDescriptionTypeId = isset($_REQUEST['workOrderDescriptionTypeId']) ? intval($_REQUEST['workOrderDescriptionTypeId']) : 0;

if (!$workOrderDescriptionTypeId){
	$workOrderDescriptionTypeId = 2; // comments
}

$cycles = array();
$db = DB::getInstance();
$workOrderDescriptionTypes = array();

$query = " select * ";
$query .= " from " . DB__NEW_DATABASE . ".workOrderDescriptionType order by displayOrder";

$workOrders = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
	if ($result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {
			$workOrderDescriptionTypes[] = $row;
		}
	}
} // >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.

$cycles = array(2, 5, 6, 9); // See comment at head of file
$cycle = isset($_REQUEST['cycle']) ? intval($_REQUEST['cycle']) : 0;

$workOrderDescriptionTypeId = $cycles[$cycle];

$cycle++;
if ($cycle >= count($cycles)){
	$cycle = 0;
}
echo '<!DOCTYPE html>';
echo '<html>';
    echo '<head>';
?>
   
        <style>    
        html *
        {
           font-size: 105%;
           font-family: Arial;
        }
        </style>
        
        <?php
        // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
        //echo '<meta http-equiv="refresh" content="5"; url="' . REQUEST_SCHEME . '://' . HTTP_HOST . '/wodisplay.php?cycle=' . $cycle . '">';
        // [END COMMENTED OUT BY MARTIN BEFORE 2019]    
        
        header( "refresh:10;url=wodisplay.php?cycle=" . $cycle ); // refresh every 10 seconds, go on to next workOrderDescriptionType in the cycle.
        echo '</head>';
        echo '<body>';
        
            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
            //include BASEDIR . '/includes/header.php';
            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
            
            $crumbs = new Crumbs(null, $user);
            
            /*
            1 | Code Update        |      1 |            0 |
            2 | Comments           |      1 |            1 |
            3 | Field Modification |      1 |            2 |
            4 | Issue Stock        |      1 |            3 |
            5 | RFI                |      1 |            4 |
            6 | Plan Modification  |      1 |            5 |
            7 | Original Design    |      1 |            6 |
            8 | Reissue Stock      |      1 |            7 |
            9 | Review             |      1 |            8 |
            10 | Trip              |      1 |            9 |
            11 | Other             |      1 |           10 |
            */    
    
            $db = DB::getInstance();
            
            $query = " select wo.* ";
            $query .= " from " . DB__NEW_DATABASE . ".workOrder wo ";
            $query .= " join " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
            // BEGIN ADDED 2020-06-12 JM
            $query .= " JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderStatusId = wos.workOrderStatusId ";
            // END ADDED 2020-06-12 JM
            $query .= " where wo.workOrderDescriptionTypeId = " . intval($workOrderDescriptionTypeId);
            /* BEGIN REPLACED 2020-06-12 JM
            $query .= " and wo.workOrderStatusId != " . intval(STATUS_WORKORDER_DONE);
            */
            // BEGIN REPLACEMENT 2020-06-12 JM
            $query .= " AND wos.isDone = 1 ";
            // END REPLACEMENT 2020-06-12 JM
            $query .= " order by wo.genesisDate asc ";
            
            $workOrders = array();
            
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {                        
                        $workOrders[] = $row;
                    }
                }
            }
            
            $currentJobId = 0;
            
            $dts = getWorkOrderDescriptionTypes();
            $dtsi = Array(); // Added 2019-12-02 JM: initialize array before using it!
            foreach ($dts as $dt) {
                $dtsi[$dt['workOrderDescriptionTypeId']] = $dt;	
            }

            echo "<script>\ndocument.title ='WODT: ".$dtsi[$workOrderDescriptionTypeId]['typeName']."  ';\n</script>\n";
            
            // >>>00006 $wodts will be identical to $dts & $workOrderDescriptionTypes will be identical to $dtsi
            // Surely we don't need two names for the same values. Consolidate.
            $wodts = getWorkOrderDescriptionTypes();
            $workOrderDescriptionTypes = array();            
            foreach ($wodts as $wodt) {            
                $workOrderDescriptionTypes[$wodt['workOrderDescriptionTypeId']] = $wodt;            
            }
    
            /*
            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
            echo '<select name="workOrderDescriptionTypeId"><option value="">-- choose description type --</option>';
            foreach ($workOrderDescriptionTypes as $wkey => $workOrderDescriptionType){
                echo '<option value="' . $workOrderDescriptionType['workOrderDescriptionTypeId'] . '">' . $workOrderDescriptionType['typeName'] . '</option>';
            }
            
            echo '</select>';
            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
            */
    
            echo '<center>';
                echo '<table border="1" cellpadding="2" cellspacing="0">';
                    echo '<tr>';
                        echo '<td>&nbsp;&nbsp;&nbsp;</td>';
                        echo '<td>&nbsp;&nbsp;&nbsp;</td>';
                        
                        echo '<td><a id="woCompanyType" href="openworkorderscompany.php?sortBy=type">Type</a></td>';
                        echo '<td>Description</td>';
                        echo '<td><a id="woCompanyIntake" href="openworkorderscompany.php?sortBy=intakeDate">Intake</a></td>';
                        echo '<td><a id="woCompanyGenesis" href="openworkorderscompany.php?sortBy=genesisDate">Genesis</a></td>';
                        echo '<td><a id="woCompanyDelivery" href="openworkorderscompany.php?sortBy=deliveryDate">Delivery</a></td>';
                        echo '<td>Age</td>';
                        echo '<td>Status</td>';
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
    
                    foreach ($workOrders as $wokey => $workOrder) {        
                        $j = new Job($workOrder['jobId']);
                        
                        // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                        //if ($j->getJobId() != $currentJobId){
                            
                        //	echo '<tr>';
                        //		echo '<td colspan="7"><a href="' . $j->buildLink() . '">' . $j->getName() . '</a></td>';
                        //	echo '</tr>';
                        //}
                        // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                        
                        $wo = new WorkOrder($workOrder['workOrderId']);
                        
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
                            echo '<td><a id="linkJobNr' . $j->getNumber() . '" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>';                        
                            echo '<td><a id="linkJobName' . $j->getJobId() . '" href="' . $j->buildLink() . '">' . $j->getName() . '</a></td>';                        
                            
                            $color = "ffffff";
                            
                            if (isset($workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()])){
                                $color = $workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()]['color'];
                            }
                        
                            echo '<td bgcolor="' . $color . '">';
                            if (isset($dtsi[$wo->getWorkOrderDescriptionTypeId()])){
                                echo '<a id="linkWoDescType' . $wo->getWorkOrderId() . '"  href="' . $wo->buildLink() . '">' . $dtsi[$wo->getWorkOrderDescriptionTypeId()]['typeName'] . "</a>";
                            } else {
                                echo '<a id="linkWoDescType' . $wo->getWorkOrderId() . '" href="' . $wo->buildLink() . '">&nbsp;</a>';
                            }
                            echo '</td>';
                        
                            echo '<td>' . htmlspecialchars($wo->getDescription()) . '</td>';			
                    
                            if ($wo->getIntakeDate() != '0000-00-00 00:00:00'){
                                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $wo->getIntakeDate());
                                $int = $dt->format('M d Y');
                            } else {
                                $int = '&mdash;';
                            }
                            echo '<td>' . $int . '</td>';	
                            echo '<td>' . $genesisDT . '</td>';
                            echo '<td>' . $deliveryDT . '</td>';
                            echo '<td>' . $ageDT . '</td>';
                            echo '<td>' . $wo->getStatusName() . '</td>';
                        echo '</tr>';
                        //$currentJobId = $j->getJobId(); // Commented out by Martin before 2019
                    } // END foreach ($workOrders...
    
                echo '</table>';
            echo '</center>';

        //include BASEDIR . '/includes/footer.php'; // Commented out by Martin before 2019
    
    echo '</body>';
echo '</html>';
?>

