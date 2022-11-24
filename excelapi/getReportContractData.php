<?php
/* contractpdf.php

   EXECUTIVE SUMMARY: make a PDF version of a contract.
   Should be very similar to contract.php, but for a PDF instead of a web page, so of course no editing.
   JM 2019-03-14: Despite the similarities, there are a lot of absolutely arbitrary differences in names of equivalent variables,
     and far too little common code even when following exactly the same business logic.
     >>>00012 Someone may want to bring those in line.

   PRIMARY INPUT: $_REQUEST['contractId'], primary key into DB table Contract.
*/

use Ahc\Jwt\JWT;
require './vendor/autoload.php';

/*$token=isset($_REQUEST['token'])?$_REQUEST['token']:"";
$retdata=array();

if($token==""){
	$retdata['status']="error";
	$retdata['message']="Token not present";
	header('Content-Type: application/json');
	echo json_encode($retdata);
	exit;
}

$passdecoded=base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU");
$jwt = new JWT($passdecoded, 'HS256', 3600, 10);
$claims=$jwt->decode($token);

if(!isset($claims['wo'])){
	$retdata['status']="error";
	$retdata['message']="Token not valid!";
	header('Content-Type: application/json');
	echo json_encode($retdata);
	exit;	
}
*/

require_once '../inc/config.php';


$contractId=$_REQUEST['contractId'];

$db=DB::getInstance();
$isDebug=1;

$contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;

if (!intval($contractId)) {
	$retdata['status']="error";
	$retdata['message']="Contract Id not valid!";
	header('Content-Type: application/json');
    echo json_encode($retdata);
    die();
}

$contract = new Contract($contractId);

if (!intval($contract->getContractId())){
	$retdata['status']="error";
	$retdata['message']="Contract Id not valid!";
	header('Content-Type: application/json');
    echo json_encode($retdata);
    die();
}

$output=[];
$contractDate = new DateTime($contract->getContractDate());
$output['contractDate']=date_format($contractDate, "m/d/Y");

$workorder=new WorkOrder($contract->getWorkOrderId());

$job = new Job($workorder->getJobId());

$output['job']['jobid']=$job->getJobId();
$output['job']['jobname']=$job->getName();
$output['job']['jobnumber']=$job->getNumber();

$output['workorder']['workorderid']=$contract->getWorkOrderId();
$output['workorder']['code']=$workorder->getCode();

$clientNames = '';
$clients = $workorder->getClient();
$clients = array_slice($clients, 0, 1);

$client=$clients[0];

$person = $client->getPerson();	

$personName = $person->getFormattedName(1);
$personName = str_replace("&nbsp;", " ", $personName);
$personName = trim($personName);

if (strlen($personName)){
    if (strlen($clientNames)){
        $clientNames .= "\n";
    }
    $clientNames .= $personName;
}	
$cmp = $client->getCompany();
$companyName = $cmp->getName();
$companyName = str_replace("&nbsp;", " ", $companyName);
$companyName = trim($companyName);
if (strlen($companyName)) {
    if (!((substr($companyName, 0, 1) == '[') && (substr($companyName, -1) == ']'))) {
        if (strlen($clientNames)){
            $clientNames .= "\n";
        }
        $clientNames .= $companyName;
    }
}

$output['client']['personname']=$personName;
$output['client']['companyname']=$companyName;


$designProNames = '';
$designProfessionals = $workorder->getDesignProfessional();
foreach ($designProfessionals as $designProfessional) { 
    $design=array();
	$person = $designProfessional->getPerson();
	$personName = $person->getFormattedName(1);
	$personName = str_replace("&nbsp;", " ", $personName);
	$personName = trim($personName);
	if (strlen($personName)){
		if (strlen($designProNames)){
			$designProNames .= "\n";
		}
        $design['personname']=$personName;
        $designProNames .= $personName;
	}
	
	$cmp = $designProfessional->getCompany();
	$companyName = $cmp->getName();
	$companyName = str_replace("&nbsp;", " ", $companyName);
	$companyName = trim($companyName);
	if (strlen($companyName)){
	    if (!((substr($companyName, 0, 1) == '[') && (substr($companyName, -1) == ']'))){
	        if (strlen($designProNames)){
	            $designProNames .= "\n";
	        }
            $design['companyname']=$companyName;
	        $designProNames .= $companyName;
	    }
    }
    $output['designprofessional'][]=$design;
    
}

$locations = $job->getLocations();
$text = '';
if (count($locations)){
	// [Martin comment:] for now just deal with a single location
	$text = $locations[0]->getFormattedAddress();
	$text = trim($text);
}
$parts = explode("\n", $text);

$output['location']=$text;
$output['contractnotes']=$workorder->getContractNotes();
//---------------------------------------

$query = "SELECT elementId as id, elementName as Title, null as parentId, elementName as billingDescription,
null as taskId, null as parentTaskId, null as workOrderTaskId,
null as totCost, null as taskTypeId, null as taskStatusId, 0 as taskContractStatus,
elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workorder->getWorkOrderId().")
UNION ALL
SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.billingDescription, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
w.totCost as totCost,
w.taskTypeId as taskTypeId, w.taskStatusId as taskStatusId, w.taskContractStatus as taskContractStatus,
getElement(w.workOrderTaskId),
e.elementName, false as Expanded, false as hasChildren
from workOrderTask w
LEFT JOIN task t on w.taskId=t.taskId

LEFT JOIN element e on w.parentTaskId=e.elementId
WHERE w.workOrderId=".$workorder->getWorkOrderId()." AND w.parentTaskId is not null and w.internalTaskStatus!=5";

$res=$db->query($query);

$out2=[];
$parents=[];
$elements=[];

while( $row=$res->fetch_assoc() ) {
    $out2[]=$row;
    if( $row['parentId']!=null ) {
        $parents[$row['parentId']]=1;
    }
    if( $row['taskId']==null)    {
        $elements[$row['elementId']] = $row['elementName'] ;


    }

}

for( $i=0; $i<count($out2); $i++ ) {

    if( $out2[$i]['Expanded'] == 1 )
    {
        $out2[$i]['Expanded'] = true;
    } else {
        $out2[$i]['Expanded'] = false;
    }

    if($out2[$i]['hasChildren'] == 1)
    {
        $out2[$i]['hasChildren'] = true;
    } else {
        $out2[$i]['hasChildren'] = false;
    }

    if( isset($parents[$out2[$i]['id']]) ) {
        $out2[$i]['hasChildren'] = true;

    }
    if ( $out2[$i]['elementName'] == null ) {
        $out2[$i]['elementName']=(isset($elements[$out2[$i]['elementId']])?$elements[$out2[$i]['elementId']]:"");
    }



}

//print_r($out2);

$sumEstimatedTasks=0;
$elementsCost = [];
$estimatedTasks=array();


foreach($out2 as $value) {
    $elementsCost[$value['elementId']][] = $value['totCost'] ;
    if($value['taskTypeId'] == ESTIMATED_TASKS_CODE ){
        $key=array_search($value['parentId'], array_column($out2, 'id'));
        if($out2[$key]['id']!=$out2[$key]['elementId'] || ($out2[$key]['id']==$out2[$key]['elementId'] && !$value['hasChildren'])){
            $sumEstimatedTasks+=$value['totCost'];
            $tmp=[];
            $tmp['id']=$value['id'];
            $tmp['parentId']=$value['parentId'];
            $tmp['billingDescription']=$value['billingDescription'];
            $tmp['value']=$value['totCost'];
            $estimatedTasks[]=$tmp;
        }
    }
}

//print_r($elementsCost);
//echo $sumEstimatedTasks;

$sumTotalEl = 0;
foreach($elementsCost as $key=>$el) {
     $elementsCost[$key] = array_sum($el);
     $sumTotalEl += array_sum($el);
}

// Get task types: overhead, fixed etc.
$allTaskTypes = array();
$allTaskTypes = getTaskTypes();


$elementTasks = array();
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $elementTasks[] = $row;

    }
}
//print_r($elementTasks);

array_multisort(array_column($elementTasks, 'elementName'), SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $elementTasks);

/*
ksort_recursive($out2);

function ksort_recursive(&$out2) {
    ksort($out2);
    foreach ( $out2 as &$a ) {
        is_array($a) && ksort_recursive($a);
    }
}

$price = array_column($out2, 'elementName');

array_multisort($price, SORT_DESC, $out2);*/






	// New Code George.
	// Need to arange on the task.
    $newPack2 = array();
    $allTasksPack2 = array();
    foreach ($elementTasks as $a) {
        $newPack2[$a['parentId']][] = $a;
    }

    function createTreePack2(&$listPack3, $parent) {
        $tree = array();

        foreach ($parent as $k=>$l ) {

            if(isset($listPack3[$l['workOrderTaskId']]) ) {

                $l['items'] = createTreePack2($listPack3, $listPack3[$l['workOrderTaskId']]);

           }

            $tree[] = $l;

        }

        return $tree;
    }


    foreach($elementTasks as $key=>$value) {

        if( $value["parentId"] == $value["elementId"] ) {

            $createAllTasks3 = createTreePack2($newPack2, array($elementTasks[$key]));



            $found = false;
            foreach($allTasksPack2 as $k=>$v) {

                $tree = array();
                if($v['elementId'] == $value['parentId']) {
                    $allTasksPack2[$k]['items'][] = $createAllTasks3[0];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $node = array();

                $node['items'][] = $createAllTasks3[0];
                $node['elementId'] = $value['elementId'];
                $node['Title'] = $value['elementName'];

                $allTasksPack2[] = $node;

            }
        } else if ($value["parentId"] == null ) {
            $node2 = array();
            $node2['elementId'] = $value['elementId'];
            $node2['Title'] = $value['elementName'];
            $allTasksPack2[] = $node2;
        }


    }

//print_r($allTasksPack2);

$elemTots=array();
$totElement=0;
$totGeneral=0;
foreach($allTasksPack2 as $elementTasks){

	$elemName=$elementTasks['Title'];
	$totElement=0;
	$children=$elementTasks['items'];
	calcLevel($children, 0);
	$tmp=array();
	$tmp['name']=strtolower($elemName);
	$tmp['value']=$totElement;
	$elemTots[]=$tmp;

	//$totGeneral+=$totElement;

}

$totGeneral=0;
foreach($elemTots as $value){
    $totGeneral+=$value['value'];
}
//print_r($elemTots);

//die();


function calcLevel($inArray, $level){
	global $totElement;

	$level++;

	if($inArray===null){
		return;
	}

	foreach($inArray as $elem){

		if ($elem['totCost']>0 && $elem['taskContractStatus']==1){
            if($level==1) {
             $totElement+=$elem['totCost'];
            }
			if(isset($elem['items']) && $elem['taskContractStatus']==1){

				$child=$elem['items'];
				calcLevel($child, $level);
			}
		}

	}

}


////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////

// The return of overlay is rather "hairy"; see the function for details, not reiterating here (more detail below
//  where we discuss $final), but the one part crucial to understand here is that a it is a multi-level array structure,
//  and each $elementgroup[$i] represents workOrderTasks associated with one of the following, depending on the nature of index $i:
// 0 - "General" workOrderTasks not associated with any element
// positive integer - workOrderTasks associated with a single element; $i is the elementId
// comma-separated list of positiveintegers - workOrderTasks associated jointly with two or more elements; $i is
//   a comma-separated list of elementIds.
$elementgroups = overlay($workorder, $contract);

// JM 2020-10-30: the code to write "Scope Of Work" or "Scopes of Work" used to be here,
// but that was actually a bit misleading; I moved it considerably down.
$taskTypes = getTaskTypes();
$clientMultiplier = $contract->getClientMultiplier();
if( filter_var($clientMultiplier, FILTER_VALIDATE_FLOAT) === false ) {
	$clientMultiplier = 1;
}

$grandTotal = 0;

$grandNTE = 0; // NTE => "not to exceed"
$elementNTE = 0;

$final = array(); // As discussed above, $ekey can be either 0 ("General"), a positive integer elementId, or a
                  //  comma-separated list of positive integer elementIds; any of these can be an "elementGroup"
                  //  and can have any number of associated workOrderTasks
                  //
                  // JM 2020-10-30 addressing http://bt.dev2.ssseng.com/view.php?id=261 (An Element is not displaying in the contract PDF):
                  //  previous code did not deal correctly with the comma-separated list of positive integer elementIds (which, in v2020-4
                  //  superseded a generic "multiple elements" case). Changes are too pervasive to indicate one by one, but basically
                  //  the new variable $elementGroupId replaces both an old loop index $ekey and a variable $elementId that only made sense if
                  //  $ekey was always to be understood as an integer.
                  //
                  // In any case it is an array of associative arrays, and the associative arrays have members:
                  //  * 'elementName'
                  //  * 'tasks', itself an array, copied from $elementgroup['tasks'] (really these are workOrderTasks, not tasks, despite the name).
                  //     * Besides what we copy from $elementgroup['tasks'], there is
                  //       * $final['tasks'][$taskkey]['groupTotal']: U.S. currency total appended to
                  //          each task, taking into account all of its subtasks. Before 2020-02-09, 'groupTotal' was 'grp'
                  //       * $final['tasks'][$taskkey]['show']. Whether to show the task in the contract.
                  //  * 'elementTotal'
                  //  * 'elementNTE'
                  //

foreach ($elementgroups as $elementGroupId => $elementgroup) {
	$elementTotal = 0; // total this one time through the loop
	$elementNTE = 0;

    // renamed $en as $elementOrGroupName JM 2020-09-02
    if ($elementGroupId == PHP_INT_MAX){
        // believed never to happen any more in v2020-4
        $elementOrGroupName = 'Other Tasks (Multiple Elements Attached)';
    } else if ($elementGroupId == 0){
        $elementOrGroupName = 'General';
    } else {
        // 'Other tasks' should never arise, here only out of an excess of caution.
        $elementOrGroupName = ($elementgroup['element']) ? $elementgroup['element']['elementName'] : 'Other tasks';
    }

	$final[$elementGroupId] = Array(); // initialize array before using it
	$final[$elementGroupId]['elementName'] = $elementOrGroupName;
	$final[$elementGroupId]['tasks'] = array();

	if (isset($elementgroup['tasks'])) {
		if (is_array($elementgroup['tasks'])) {
            // BEGIN REPLACED 2020-09-02 JM
            // $tasks = $elementgroup['tasks'];
            // END REPLACED 2020-09-02 JM
            // BEGIN REPLACEMENT 2020-09-02 JM
            $tasks = &$elementgroup['tasks']; // NOTE the ampersand: $tasks here is just an alias/reference
            // END REPLACEMENT 2020-09-02 JM

			///////////////////////////////////////////
			///////////////////////////////////////////
			///////////////////////////////////////////
			///////////////////////////////////////////
			///////////////////////////////////////////
            // BEGIN REMOVED 2020-09-02 JM
            // $showfix = array(); // $showfix is preparatory to the 'show' data that determines whether the row gets shown.
            // END REMOVED 2020-09-02 JM

            // BEGIN ADDED 2020-09-02 JM
            // Initialize all tasks to 'show'==1 before we go through and change some to 'show'==0.
            // See discussion of $final['tasks'][$taskkey]['show'] above.
            foreach ($tasks as $taskkey => $task) {
                $tasks[$taskkey]['show'] = 1; // initialize, see discussion of $final['tasks'][$taskkey]['show'] above.
            }
            // END ADDED 2020-09-02 JM

            // We will loop twice through tasks, first to see decide what to show and to "group in" subtasks,
            // second to do the rest of the work.
            foreach ($tasks as $taskkey => $task) {
				$sliced = array_slice($tasks, $taskkey + 1);  // The rest of array $tasks
				$startLevel = intval($task['level']);

				$groupTotal = 0; // total of this task and its subtasks RENAMED 2020-09-02 JM, was just $total
				$taskTotal = 0;  // just for this task, not its subtasks RENAMED 2020-09-02 JM, was just $sum

				$estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;
				$estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;

                $taskTotal = ($estQuantity * $estCost * $clientMultiplier);

				$groupTotal += $taskTotal;

                // BEGIN REMOVED 2020-09-02 JM
                // Wrong place to initialize this, now initialize above as $tasks[$taskkey]['show'] = 1
                // $elementgroup['tasks'][$taskkey]['show'] = 1;
                // END REMOVED 2020-09-02 JM

                // Now we want to look at any subtasks.
                // NOTE that each of these subtasks will come up again in the outer loop as a task in its own right.
				foreach ($sliced as $skey => $slice) {
					if ($slice['level'] > $startLevel) {
					    // This is a subtask of $task, so add it in
						$subtaskTotal = 0; // total for this subtask, excluding its own subtasks RENAMED 2020-09-02 JM, was just $str

						$estQuantity = isset($slice['task']['estQuantity']) ? $slice['task']['estQuantity'] : 0;
						$estCost = isset($slice['task']['estCost']) ? $slice['task']['estCost'] : 0;

						$subtaskTotal = ($estQuantity * $estCost * $clientMultiplier);
						$groupTotal += $subtaskTotal;

                        /* BEGIN REPLACED 2020-09-02 JM
                        // This is scratch for the ad hoc $elementgroup['tasks'][$taskkey]['show']. In this
                        //  case the ultimate effect will be to set $task['tasks'][$taskkey + 1 + $skey]['show']=0.
                        //  JM: No idea why we use $showfix instead of just setting that here.
                        //  See discussion of $task['tasks'][$taskkey]['show'] above.
                        //
                        // If this task has a downarrow, calculate the next index in $tasks, which will always be exactly one level
                        //  deeper in the hierarchy than the present task. Mark that to later get $task['tasks'][$taskkey]['show']
                        //  set to 0.
                        if ( isset($task['task']['arrowDirection']) &&  ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN)) {
                            $showfix[] = $taskkey + 1 + $skey;
                        }
                        // END REPLACED 2020-09-02 JM
                        */
                        // BEGIN REPLACEMENT 2020-09-02 JM
                        // If the ancestor task (lower "level" number) has a downarrow:
                        //  * we calculate the index **in $tasks** of the workOrderTask we are currently looking at **in $sliced**
                        //  * we modify its 'show' value **in $tasks**.
                        if ( isset($task['task']['arrowDirection']) &&  ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN)) {
                            $tasks[$taskkey + 1 + $skey]['show'] = 0; // see discussion of $final['tasks'][$taskkey]['show'] above.
                        }
                        // END REPLACEMENT 2020-09-02 JM
                        unset($subtaskTotal); // ADDED 2020-09-02 JM
					} else {
						break;
					}
				}
				/* BEGIN REPLACED 2020-09-02 JM
				$elementgroup['tasks'][$taskkey]['grp'] = $groupTotal;
				// END REPLACED 2020-09-02 JM
				$tasks is an alias for $elementgroup['tasks'], let's be consistent in using it
				*/
				// BEGIN REPLACEMENT 2020-09-02 JM
				$tasks[$taskkey]['groupTotal'] = $groupTotal;
				// END REPLACEMENT 2020-09-02 JM
				unset($sliced, $startLevel, $groupTotal, $taskTotal, $estQuantity, $estCost); // ADDED 2020-09-02 JM
			}

            /* BEGIN REMOVED 2020-09-02 JM
            foreach ($showfix as $sf) {
                $elementgroup['tasks'][$sf]['show'] = 0; // see discussion of $final['tasks'][$taskkey]['show'] above.
            }
            // END REMOVED 2020-09-02 JM
            */

            /* BEGIN REMOVED 2020-09-02 JM : No need to do this now that we made $tasks a reference rather than a copy.
            $tasks = $elementgroup['tasks'];
            // END REMOVED 2020-09-02 JM
            */
			///////////////////////////////////////////
			///////////////////////////////////////////
			///////////////////////////////////////////
			///////////////////////////////////////////
			///////////////////////////////////////////

			foreach ($tasks as $task) {
				if ($task['type'] == 'fake') {
                    /* $task['type'] == 'fake' is a temporary expedient for a missing parent task.
                       Theory is that this should go away, but I (JM) doubt it, as long as we still want to
                       be able to look at old contracts.

                       If the task is "fake", we just span all the columns with a task description.
                    */
                    if (intval($task['show'])) { // TEST ADDED 2020-09-02 JM
                        $final[$elementGroupId]['tasks'][] = $task;
                    }
				} else if ($task['type'] == 'real') {
					$taskTypeId = $task['task']['taskTypeId']; // $taskTypeId determines whether we show NTE ("not to exceed").
					                                           // >>>00014: NTE was dropped from contract.php. Is it going away?
					                                           //  Was that an error there? If not, why do the two files differ?
					$wot = new WorkOrderTask($task['workOrderTaskId']);

					/* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
					$viewMode = $wot->getViewMode();
					// Some workOrderTasks show in the contract, others don't; make the distinction.
					// 00042 JM 2020-09-02 after brief discussion with Damon, this may not be quite right, may need
					//   further thought.
					if (intval($viewMode) & WOT_VIEWMODE_CONTRACT) {
					// END REMOVED 2020-10-28 JM
					*/
					    $t = $wot->getTask();
						$tt = '';
						if (isset($taskTypes[$t->getTaskTypeId()]['typeName'])){
							$tt = $taskTypes[$t->getTaskTypeId()]['typeName'];
						}

						$estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;
						$estCost = preg_replace("/[^0-9.+-]/", "", $estCost); // >>>00002: Not a great test. For example, allows
						                                            // "45.55.66"; changes "579.8gyy5" to "579.85". We should have a better
						                                            // test and should log bad data.

						$estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;

						if (!$estQuantity){
							$estQuantity = '';
						}

						$nte = '';
						if ($taskTypeId == TASKTYPE_HOURLY){
							$nte = isset($task['task']['nte']) ? intval($task['task']['nte']) : 0;
						}

						// Before 2020-09-11, $calculated_cost was just $cost. Name changed to parallel invoicepdf.php. - JM
						$calculated_cost = number_format(
						    (($estQuantity=='' ? 0 : $estQuantity) * ($estCost=='' ? 0 : $estCost) * ($clientMultiplier=='' ? 0 : $clientMultiplier)),
						    2);

						$adder = preg_replace("/[^0-9.]/", "", $calculated_cost); // >>>00002: Not a great test, as for $estCost above.

						if (is_numeric($adder)) {
						    // add to total both the for this element and overall
							$elementTotal += $adder;
							$grandTotal += $adder; // NOTE that we add to grand total regardless of "show"
						}

						if (intval($nte) &&  ($taskTypeId == TASKTYPE_HOURLY)) {
                            $elementNTE += ($nte * $estCost * $clientMultiplier);
                            $grandNTE += ($nte * $estCost * $clientMultiplier);
						} else {
							$elementNTE += $adder;
							$grandNTE += $adder;
						}

						if (!strlen($calculated_cost)){
							$calculated_cost = '';
						} else {
							$calculated_cost = '$' . $calculated_cost;
						}

                        if (intval($task['show'])) { // BEFORE 2020-09-02 JM this test was much farther down, but it makes more sense here.
                                                     // Nothing past here at this level means anything unless we are showing this task.
                            $estCostNoFormat = $estCost;

                            if (!strlen($estCost)){
                                $estCost = '';
                            } else {
                                $estCost = '$' . number_format(($estCost * $clientMultiplier) , 2);
                            }

                            if (!isset($task['task']['arrowDirection'])) {
                                $task['task']['arrowDirection'] = ARROWDIRECTION_RIGHT;
                            }
                            if ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN) {
                                $task['cost'] = array('typeName' => $tt,'price' => $estCost,'quantity' => $estQuantity, 'cost' =>  '$' . number_format($task['groupTotal'], 2));
                            } else {
                                $task['cost'] = array('typeName' => $tt,'price' => $estCost,'quantity' => $estQuantity, 'cost' => $calculated_cost);
                            }

                            $task['nte'] = array(
                                'nte' => $nte,
                                'cost' => (($nte=='' ? 0 : $nte) * ($estCostNoFormat=='' ?  0 : $estCostNoFormat) * ($clientMultiplier=='' ? 0 : $clientMultiplier))
                                );

						// if (intval($task['show'])) { // THIS TEST REMOVED here & added 20 lines or so above 2020-09-02 JM
							$final[$elementGroupId]['tasks'][] = $task;
						}
					// } // REMOVED 2020-10-28 JM getting rid of viewmode
					unset($taskTypeId, $wot); // ADDED 2020-09-02 JM
				} else {
                    $logger->error2('1599085720', 'Encountered a task that is neither "real" nor "fake".' );
                }
			} // END foreach ($tasks
		}
	} // END if (isset($elementgroup['tasks'])) {


	$final[$elementGroupId]['elementTotal'] = $elementTotal;
	$final[$elementGroupId]['elementNTE'] = $elementNTE;
} // END foreach ($elementgroups...

$contractLanguageId = $contract->getContractLanguageId();
$contractLanguageId = intval($contractLanguageId);

$bullets=array();

if (count($final) && strlen($workorder->getContractNotes())) {
    $bullets[] = "Project Description & Expanded Scope of Work";
} elseif(strlen($workorder->getContractNotes())){
	$bullets[] = "Project Description";
} elseif(count($final)){
    $bullets[] = "Expanded Scope of Work";
}

if (intval($contractLanguageId)) {
	$db = DB::getInstance();
	$query  = "SELECT * FROM " . DB__NEW_DATABASE . ".contractLanguage WHERE contractLanguageId = " . intval($contractLanguageId) . ";";
	$row = false;

	$result = $db->query($query);
	if ($result) {
		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
		}
	} else {
	    $logger->errorDb('1599086080', 'Hard DB error', $db);
	}

    // If we got contract language, first bullet point is the display name of that contract language
	if ($row) {
        $bullets[] = $row['displayName'];
	}
}

foreach ($elemTots as $elementgroup) {

    if($elementgroup['value']>0){

		$output['scopeofwork'][]=array(
			'name' => ucfirst($elementgroup['name']),
			'value' => $elementgroup['value']
		);
	}
} // END foreach ($final...

if (count($bullets)) {
	$output['bullets']=$bullets;
}


// --------------------------------------

$query = " SELECT hoursRate, hoursRateId, date ";
$query .= " FROM " . DB__NEW_DATABASE . ".extraHoursService ";

$hoursRate = 0;

$result = $db->query($query);
if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hoursRate = intval($row['hoursRate']); // standard cost

    }
}
$output['hourlyRate']=$hoursRate;

function makeOneLevelArray($in, &$out, $prefixLevel = "   ") {
	$level = "		";

    foreach ($in as $v) {
        if (array_key_exists('totCost', $v)) {
			$v['Title']  = preg_replace_callback('/^([^a-z]*)([a-z])/', function($m)
			{
			return $m[1].strtoupper($m[2]);
			}, strtolower($v['Title'] )); // uppercase first letter.

			$v['Title']=ucfirst($v['Title']);

            if($v['taskContractStatus'] != 9) {
				$v['totCost'] =  $v['totCost'] ;

            } else {
                $v['totCost'] = "";
            }

		} else { // Element
			$prefixLevel = "";
			$level = "";
            $v['Title'] = strtoupper($v['Title']) ;
            $v['totCost'] = "";

        }


		$out[]['Title'] = $prefixLevel . $level . ' ' . $v['Title'];
        $out[]['totCost'] = $v['totCost'];
        if (!empty($v['items'])) {
            makeOneLevelArray($v['items'], $out, $prefixLevel . $level . ' ');
        } else {
			return;
		}
        $level++;
    }
}

$fontBold=true;
$fontSize=12;
$totElement=0;
//$totGeneral=0;

$o=[];
foreach($elemTots as $e){
	$items=array_search(strtolower($e['name']), array_column($allTasksPack2, 'Title'));
	$o['name']=$e['name'];
	$o['value']=$e['value'];
	$o['items'][]=$allTasksPack2[$items]['items'];
	//$output['elems'][]=$o;
}

//$output['totElement']=$elemTots;
//$output['tasks']=$allTasksPack2;


function printLevel($inArray, $level){
	$level++;

	$fontBold=false;
	$dimFont=$fontSize-$level;
	$posX=($level<=2?$level*10+15:35);
	$posY=$pdf->GetY()+5;

	if($inArray===null){
		return;
	}

	foreach($inArray as $elem){
		if ($elem['totCost']>0 && $elem['taskContractStatus']==1){
			$text = ucfirst(strtolower(trim( $elem['billingDescription']!=""?$elem['billingDescription']:$elem['Title'])));
			$pdf->SetFont('Tahoma', ($fontBold?'B':''), $dimFont);
			$pdf->SetXY($posX, $pdf->GetY()+1);
			$posY=$pdf->GetY();
			$pdf->MultiCell(150-$level*10, 5, $text, 0, 'L', false);
			$newPosY=$pdf->GetY();
            $pdf->SetXY(RIGHT_MARGIN-55-($level<=2?($level-1)*5:5), $posY);
			$pdf->MultiCell(25, 5, "$".number_format($elem['totCost'], 2), 0,'R', false);
			$pdf->SetY($newPosY);
			if(isset($elem['items']) && $elem['taskContractStatus']==1){
                $totElement+=$elem['totCost'];
				$child=$elem['items'];
				printLevel($child, $level);
			}
		}
	}
}

function getItems($e){
echo "START"."\n";
	print_r($e);
	if(!$e) {
		echo "END - 1"."\n";
		return [];
	}
	if(isset($e['taskContractStatus']) && $e['taskContractStatus']==9) {
		echo "END - 2"."\n";
		return [];
	}
	echo "END - 3"."\n";
	return getItems($e['items']);
	return [];
}

foreach($allTasksPack2 as $elementTasks){
	$out=[];
	$out['name']=$elementTasks['Title'];
	
	$text = ucfirst(strtolower(trim( $elementTasks['Title'])));
	$key = array_search(strtolower($text), array_column($elemTots, 'name'));
	
	$out['value']=$elemTots[$key]['value'];
	$out['items']=$elementTasks['items'];

	$output['elements'][]=$out;
}


/*
if($sumEstimatedTasks>0){
	$pdf->SetXY(RIGHT_MARGIN-135, $pdf->GetY()+7);

	$pdf->SetFont('Tahoma', 'B', 13);

	$pdf->MultiCell(105, 5, "Sub-total of Fixed Costs:  $".number_format($totGeneral-$sumEstimatedTasks, 2), 0, 'R', false);

	$pdf->ln(1.2);

	$pdf->SetXY(RIGHT_MARGIN-135, $pdf->GetY()+2);

	$pdf->SetFont('Tahoma', 'B', 13);

	$pdf->MultiCell(105, 5, "Sub-total of Estimated Costs:  $".number_format($sumEstimatedTasks, 2), 0, 'R', false);

	$pdf->ln(1.2);

	$pdf->SetXY(RIGHT_MARGIN-135, $pdf->GetY()+2);

	$pdf->SetFont('Tahoma', 'B', 13);

	$pdf->MultiCell(105, 5, "Combined Total:  $".number_format($totGeneral, 2), 0, 'R', false);

} else {
	$pdf->SetXY(RIGHT_MARGIN-135, $pdf->GetY()+7);

	$pdf->SetFont('Tahoma', 'B', 13);

	$pdf->MultiCell(105, 5, "Contract Total:  $".number_format($totGeneral, 2), 0, 'R', false);

}

$pdf->ln(1.2);


$out3=[];
foreach($out2 as $val){
	$out3[$val['id']]=$val;
}
//print_r($estimatedTasks);

if($sumEstimatedTasks>0){
$pdf->AddPage();

	$pdf->SetXY(15, $pdf->GetY()+5);
	$pdf->SetFont('Tahoma', 'B', 13);

	$pdf->MultiCell(105, 5, "Estimated Costs Detail", 0, 'L', false);
	$pdf->Line(LEFT_MARGIN, $pdf->GetY(), RIGHT_EDGE, $pdf->GetY());

	$estDisplay=[];


	foreach($estimatedTasks as $est){

		$tmp=[];
		$tmp['elem']=findElementFromTask($est['id']);
		$tmp['name']=$est['billingDescription'];
		$tmp['value']=$est['value'];

		$estDisplay[]=$tmp;
	}

	function cmp($a, $b) {
    	return strcmp($a['elem'], $b['elem']);
	}

	usort($estDisplay, "cmp");

//print_r($estDisplay);

    $elem="";
	foreach($estDisplay as $est){

        if($est['value']>0){
            $y=$pdf->GetY();
            $pdf->SetXY(15, $y);
            if($elem!=$est['elem']){
                if($pdf->GetY()>220){
                    $pdf->AddPage();
                }
                $pdf->SetFont('Tahoma', 'B', 11);
                $pdf->MultiCell(100, 5, $est['elem'], 0, 'L', false);
                $elem=$est['elem'];
            }
            $pdf->SetFont('Tahoma', '', 11);
            $y=$pdf->GetY();
            $pdf->SetX(15+20);
            //$pdf->SetXY(15+20, $y);
            $pdf->MultiCell(110, 5, $est['name'], 0, 'L', false);
            $y1=$pdf->GetY();
            $pdf->SetXY(RIGHT_MARGIN-80, $y);
            $pdf->SetFont('Tahoma', 'B', 11);
            $pdf->MultiCell(50, 5,  "$".number_format($est['value'], 2), 0, 'R', false);
            $pdf->SetY($y1);
            //$pdf->Ln(1);
        }

	}

}
$pdf->Ln(5);
if($sumEstimatedTasks>0){
	$pdf->Line(LEFT_MARGIN, $pdf->GetY()+4, RIGHT_EDGE, $pdf->GetY()+4);
	$pdf->Ln(4);

	$pdf->SetFont('Tahoma', '', 10);

	$pdf->MultiCell(150, 5, "* the value of the estimated costs is included in Combined Total value", 0, 'L');
}
//$pdf->AddPage();

function findElementFromTask($taskId)
{
	global $out3;
$i=0;
//echo $taskId."<br>";
	while($taskId!="" && $i<5){
//echo $taskId."<br>";
		if($out3[$taskId]['parentId']==""){
			break;
		} else {
			$taskId=$out3[$taskId]['parentId'];
//echo $taskId."<br>";
		}
		$i++;
	}
	return $out3[$taskId]['billingDescription'];
}


function printLevel($inArray, $level){
	global $fontSize, $fontBold, $pdf, $totElement;

	$level++;

	$fontBold=false;
	$dimFont=$fontSize-$level;
	$posX=($level<=2?$level*10+15:35);
	$posY=$pdf->GetY()+5;

	if($inArray===null){
		return;
	}

	foreach($inArray as $elem){


		if ($elem['totCost']>0 && $elem['taskContractStatus']==1){

			$text = ucfirst(strtolower(trim( $elem['billingDescription']!=""?$elem['billingDescription']:$elem['Title'])));

			$pdf->SetFont('Tahoma', ($fontBold?'B':''), $dimFont);

			$pdf->SetXY($posX, $pdf->GetY()+1);
			$posY=$pdf->GetY();
			$pdf->MultiCell(150-$level*10, 5, $text, 0, 'L', false);

			$newPosY=$pdf->GetY();
            $pdf->SetXY(RIGHT_MARGIN-55-($level<=2?($level-1)*5:5), $posY);

			$pdf->MultiCell(25, 5, "$".number_format($elem['totCost'], 2), 0,'R', false);
			$pdf->SetY($newPosY);

			if(isset($elem['items']) && $elem['taskContractStatus']==1){
                $totElement+=$elem['totCost'];
				$child=$elem['items'];
				printLevel($child, $level);
			}
		}

	}

}

*/

// $outputArray = array();
// makeOneLevelArray($allTasksPack2, $outputArray);



// foreach($outputArray as $value) {

// $cost = "";
//    $text = "";
//    $costT = "";
//     if (array_key_exists('Title', $value)) {

// 		$text = $value['Title'];
//     }
//     if (array_key_exists('totCost', $value)) {
// 		if($value['totCost']){
// 			$cost =  '$' . number_format(($value['totCost']), 2);
// 		} else {
// 			$cost = " ";
// 		}


// 	}

// 	$total_string_width = $pdf->GetStringWidth($text);
// 	$number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
// 	$number_of_lines = ceil( $number_of_lines );  //  Round it up.

// 	if($all_upper = !preg_match("/[a-z]/", $text)) {
// 		$pdf->setX($pdf->GetPageWidth() - 15 - RIGHT_MARGIN);
// 		$pdf->Cell(15, -2.5, $cost, 0, 0, 'R');

// 		$text = ucfirst(strtolower(trim($text)));
// 		$pdf->SetFont('Tahoma', 'B', 12);
// 		$pdf->ln(1);
// 		$pdf->Cell($number_of_lines, 5, $text, 0, 0, 'L');

// 		$pdf->ln(3.2);

// 	} else {

// 		$pdf->SetFont('Tahoma', '', 12);
// 		$pdf->Cell($number_of_lines, 5, $text, 0, 0, 'L');

// 		//if ($cost != '$0.00') {
// 			//$disp = '$' . number_format(intval($cost), 2);
// 		//if (intval($cost)) {
// 			//$pdf->ln(1);

// 			$pdf->ln(4.2);


// 		//}
// 	}



// 	//$height_of_cell = $number_of_lines * 5;
// 	//$height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.


// 	//if ($cost != '$0.00') {
// 		//$pdf->SetY($pdf->GetY() + 2);

// 		//$pdf->setX($pdf->GetPageWidth() - 25 - RIGHT_MARGIN);
// 		//$pdf->cell(25, 5, $cost, 0, 'R', 'R');

// 		//if (intval($cost)) {
// 			//$disp = '$' . number_format(intval($cost), 2);

// 			//$pdf->setX($pdf->GetPageWidth() - 22 - RIGHT_MARGIN);
// 			//$pdf->SetFont('Tahoma', '', 11);
// 			//$pdf->cell(22, 5, $disp, 0, 'R', 'R');
// 			//$pdf->Cell($number_of_lines, 5, $disp, 0, 0, 'L');




// 			/*$pdf->setX($pdf->GetPageWidth() - 38 - RIGHT_MARGIN);
// 			$pdf->SetFont('Helvetica', 'I', 10);
// 			$pdf->cell(13, 5, $cost, 0, 'R', 'R');

// 			$pdf->setX($pdf->GetPageWidth() - 47 - RIGHT_MARGIN);
// 			$pdf->SetFont('Helvetica', 'I', 10);
// 			//$pdf->cell(10, 5, 'NTE:', 0, 'R', 'R');

// 			$pdf->setY($pdf->GetY() + 5);
// 			$pdf->SetFont('Tahoma', '', 11);*/
// 		//}
// 	//}

// 	//echo $strTask1 . " " .   $strTask2 ;

// }

// //echo " Total: " . " " . "<b>" . $sumTotalEl . "</b>";

// // End CODE FROM GEORGE.
// $pdf->ln(4);

// $pdf->SetFont('Tahoma', 'B', 12);

// $disp = '$' . number_format($sumTotalEl, 2);
// $total_string_width = $pdf->GetStringWidth($disp);

// $pdf->setX($pdf->GetPageWidth() - $total_string_width - RIGHT_MARGIN);

// $pdf->cell(0, 5, $disp, 0, 0, 'R');

// $pdf->setX($pdf->GetPageWidth() - $total_string_width - RIGHT_MARGIN - 75);

// $pdf->cell(70, 5, 'ESTIMATE TOTAL :', 0, 0, 'R');
/*
if (is_numeric($grandNTE)) {
	if ($grandNTE != $sumTotalEl) {
		$pdf->SetY($pdf->GetY() + 5);

		$pdf->SetFont('Helvetica', 'I', 10);

		$disp = '$' . number_format($grandNTE, 2);
		$total_string_width = $pdf->GetStringWidth($disp);

		$pdf->setX($pdf->GetPageWidth() - $total_string_width - RIGHT_MARGIN);

		$pdf->cell(0, 5, $disp, 0, 0, 'R');

		$pdf->setX($pdf->GetPageWidth() - $total_string_width - RIGHT_MARGIN - 80);

		//$pdf->cell(70, 5, 'NTE:', 0, 0, 'R');
	}
}*/
// Now we actually write out the "contract language".
/*
$pdf->setHeaderText('');

if ($contractLanguageId) {
	$db = DB::getInstance();
	$query  = "SELECT * FROM " . DB__NEW_DATABASE . ".contractLanguage WHERE contractLanguageId = " . intval($contractLanguageId) . ";";
	$row = false;
	$result = $db->query($query);
	if ($result) {
		if ($result->num_rows > 0){
			$row = $result->fetch_assoc();
		}
	} else {
	    $logger->errorDb('1599086120', 'Hard DB error', $db);
	}

	if ($row) {
		if (strlen($row['fileName'])) {
			$extraPages = 0;
            $pages = $pdf->setSourceFile2(BASEDIR . '/../'.CUSTOMER_DOCUMENTS.'/contract_language/' . $row['fileName']);

			for ($i = 0; $i < $pages; $i++) {
				$tplIdx = $pdf->importPage($i + 1);
				$size = $pdf->getTemplateSize($tplIdx);

				if ($size['width'] > $size['height']) {
					$pdf->AddPage('L', array($size['width'], $size['height']));
				} else {
					$pdf->AddPage('P', array($size['width'], $size['height']));
				}
				$pdf->setDoFooter(false);

				$pdf->useTemplate($tplIdx);

				$extraPages++;
			}

			$pdf->setExtraPages($extraPages);
		}
	}
}

$contractDate = date_parse( $contract->getContractDate());
//var_dump($contractDate );
$contractDateString = '';
if (is_array($contractDate)){
	if (isset($contractDate['year']) && isset($contractDate['day']) && isset($contractDate['month'])) {
		$mm =  intval($contractDate['month']);
		if ($mm < 10){
			$mm = "0" . $mm;
		}
		$dd = intval($contractDate['day']);
		if ($dd < 10){
			$dd = "0" . $dd;
		}

		$contractDateString = intval($contractDate['year']) . '' . $mm . '' . $dd;
		if ($contractDateString == '000'){
			$contractDateString = '';
		}
	}
}

*/






	header('Content-Type: application/json');
    echo json_encode($output);
die();


