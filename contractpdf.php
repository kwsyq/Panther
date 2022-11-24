<?php
/* contractpdf.php

   EXECUTIVE SUMMARY: make a PDF version of a contract.
   Should be very similar to contract.php, but for a PDF instead of a web page, so of course no editing.
   JM 2019-03-14: Despite the similarities, there are a lot of absolutely arbitrary differences in names of equivalent variables,
     and far too little common code even when following exactly the same business logic.
     >>>00012 Someone may want to bring those in line.

   PRIMARY INPUT: $_REQUEST['contractId'], primary key into DB table Contract.
*/

require_once './inc/config.php';
require_once './inc/access.php';

$contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;

if (!intval($contractId)) {
    // Called without contractId, just die
	die();
}

$contract = new Contract($contractId);

if (!intval($contract->getContractId())){
    // Invalid contractId, redirect to top of domain.
	header("Location: /");
}

$workOrder = new WorkOrder($contract->getWorkOrderId());
$job = new Job($workOrder->getJobId());

// [BEGIN Martin comment]
// DEAL WITH MULTIPLE CLIENTS !!!!!!!
// AND MULTIPLE OTHER STUFF !!
// [END Martin comment]
// >>>00001 JM 2019-03: That's presumably a statement of work to be done, not a claim that this currently does that.

$clientNames = '';
$clients = $workOrder->getClient(); // [Martin comment:] returns array of CompanyPerson objects;
// [BEGIN Martin comment]
// only want 1 client.
// if its wrong one then it needs to be fixed at team level
// [END Martin comment]
$clients = array_slice($clients, 0, 1); // Limit to first client, never report more than one.

// The following is written to loop over multiple clients, even though just above
// we forced this to a single client.
foreach ($clients as $client) {
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
	    // ignore any mame that begins with '[' or ends with ']': these are "bracket companies", they correspond
	    //  to a single person, and we already have the person name.
	    if (!((substr($companyName, 0, 1) == '[') && (substr($companyName, -1) == ']'))) {
	        if (strlen($clientNames)){
	            $clientNames .= "\n";
	        }
	        $clientNames .= $companyName;
	    }
	}
} // END foreach ($clients...

// Design professional, parallels what we did above with client, except this time we DO allow more than one.
// >>>00037 very similar to code above for client, could eliminate common code.
$designProNames = '';
$designProfessionals = $workOrder->getDesignProfessional();  // [Martin comment:] returns array of CompanyPerson objects;
foreach ($designProfessionals as $designProfessional) {
	$person = $designProfessional->getPerson();
	$personName = $person->getFormattedName(1);
	$personName = str_replace("&nbsp;", " ", $personName);
	$personName = trim($personName);
	if (strlen($personName)){
		if (strlen($designProNames)){
			$designProNames .= "\n";
		}
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
	        $designProNames .= $companyName;
	    }
	}
}


$query = "SELECT elementId as id, elementName as Title, null as parentId, elementName as billingDescription,
null as taskId, null as parentTaskId, null as workOrderTaskId,
null as totCost, null as taskTypeId, null as taskStatusId, 0 as taskContractStatus,
elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrder->getWorkOrderId().")
UNION ALL
SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.billingDescription, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
w.totCost as totCost,
w.taskTypeId as taskTypeId, w.taskStatusId as taskStatusId, w.taskContractStatus as taskContractStatus,
getElement(w.workOrderTaskId),
e.elementName, false as Expanded, false as hasChildren
from workOrderTask w
LEFT JOIN task t on w.taskId=t.taskId

LEFT JOIN element e on w.parentTaskId=e.elementId
WHERE w.workOrderId=".$workOrder->getWorkOrderId()." AND w.parentTaskId is not null and w.internalTaskStatus!=5";

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





$pdf = new PDF('P', 'mm', 'Letter');
$pdf->setDocumentType('contract');
//$pdf->setHeaderText($job->getName(),"/".$job->getNumber()."/".$contractDateString);
$pdf->setDoHeader(true);
$pdf->setDoFooter(false);
$pdf->AddFont('Tahoma', '', 'Tahoma.php');
$pdf->AddFont('Tahoma', 'B', 'TahomaB.php');
$pdf->AliasNbPages();
$pdf->AddPage();

if (!intval($contract->getCommitted())) {
    $pdf->SetXY(0, 0);
    $pdf->SetFont('Tahoma', 'B', 60);
    $pdf->SetTextColor(227, 227, 227);
    $pdf->Rotate(315);
    $pdf->Text(0, 0, 'DRAFT DRAFT DRAFT DRAFT'); // Indicates draft contract, not that this code is a draft. This is because not committed.

    $pdf->SetXY(0, 0);
    $pdf->Rotate(-315); // undo rotation, back to normal

    $pdf->SetTextColor(0, 0, 0);
}

////////////////////////////////////////////
// [Martin comment:] header logo
////////////////////////////////////////////

$num_pages = $pdf->setSourceFile2(BASEDIR . '/cust/' . $customer->getShortName() . '/img/pdf/logoblackai.pdf');
$template_id = $pdf->importPage(1); // [Martin comment:] if the grafic is on page 1
$size = $pdf->getTemplateSize($template_id);

$pdf->useTemplate($template_id, 15, 10, $size['width']/2.5, $size['height']/2.5); // JM guesses that 2.5 is coarse conversion of centimeters to inches
                                                                         // but could just be ad hoc
$yPos = 10;
$pdf->setY($yPos);
$pdf->SetFont('Tahoma', 'B', 15);
$total_string_width = $pdf->GetStringWidth('Standard Contract');
$pdf->setX($pdf->GetPageWidth() - $total_string_width - 15);
$pdf->cell(0, 6, 'Standard Contract', 0);

$pdf->setY($yPos + 7);
$pdf->SetFont('Tahoma', 'B', 11);

$total_string_width = $pdf->GetStringWidth('Ph. '.CUSTOMER_PHONE_WITH_DOTS);
$pdf->setX($pdf->GetPageWidth() - $total_string_width - 15);
$pdf->cell(0, 6, 'Ph. '.CUSTOMER_PHONE_WITH_DOTS, 0);

define("PAGE_WIDTH", $pdf->GetPageWidth());
define("RIGHT_MARGIN", 15);
define("LEFT_MARGIN", 15);
define("LEFT_MARGIN2", 50);
define("SPACE", 15); // space between columns
define("RIGHT_EDGE", PAGE_WIDTH - RIGHT_MARGIN); // x-index of right edge of written area
define("EFFECTIVE_WIDTH", PAGE_WIDTH - RIGHT_MARGIN - LEFT_MARGIN); // effective page width excluding margins
define("EFFECTIVE_COL_WIDTH", intval(PAGE_WIDTH - RIGHT_MARGIN - LEFT_MARGIN - SPACE ) / 2);  // effective width of a single column in 2-column areas
define("COL1_END", LEFT_MARGIN + EFFECTIVE_COL_WIDTH);
define("COL2_BEGIN", COL1_END + SPACE);

// ------- "Good faith" statement -------
$pdf->SetFont('Tahoma', '', 11);
$yPos = 34;
$pdf->setY($yPos);

$pdf->SetLeftMargin(LEFT_MARGIN);
$pdf->SetRightMargin(RIGHT_MARGIN);

// E.g. "Sound Structural Solutions, Inc intends to provide the following professional services in good faith and in accordance with the practices currently standard to this industry in exchange for the fees outlined below."
$pdf->Write(5, CONTRACT_GOOD_FAITH);

$pdf->Ln(8);
$pdf->Line(LEFT_MARGIN, 33, RIGHT_EDGE, 33); // [Martin comment:] 20mm from each edge
$pdf->Line(LEFT_MARGIN, 46, RIGHT_EDGE, 46); // [Martin comment:] 20mm from each edge

// calculate contract date, but don't yet write it
$contractDate = date_parse( $contract->getContractDate());
$contractDateString = '';
if (is_array($contractDate)){
	if (isset($contractDate['year']) && isset($contractDate['day']) && isset($contractDate['month'])){

		$contractDateString = intval($contractDate['month']) . '/' . intval($contractDate['day']) . '/' . intval($contractDate['year']);
		if ($contractDateString == '0/0/0'){
			$contractDateString = '';
		}
	}
}
$pdf->setHeaderText(ucfirst($job->getName())." / ".$job->getNumber()." / ".$contractDateString);
// -------- Job name -------
$pdf->setY($pdf->GetY() + 2);
$text = ucfirst($job->getName());
$pdf->SetFont('Tahoma', '', 13);
$total_string_width = $pdf->GetStringWidth($text);
$pdf->SetFont('Tahoma', 'B', 13);
$pdf->MultiCell(0, 5, $text, 0, 'L');

// Calculate max y-coord so far, and remember it.
$number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
$number_of_lines = ceil( $number_of_lines );  // [Martin comment:] Round it up.
$height_of_cell = $number_of_lines * 5;
$height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.
$pdf->setY($pdf->GetY() + $height_of_cell);

$yAfterJobName = $pdf->GetY();

// -------- Date, Job Number, WorkOrder Number -------
// Print these in left column.
$yPos = $yAfterJobName;
$pdf->setX(20);
$pdf->setY($yPos);
$rows = array();
$rows[] = array( array(5, 'Date:'), array(5, $contractDateString));
$rows[] = array( array(5, 'Job#'),  array(5, $job->getNumber()));
//$rows[] = array( array(5, 'WO#'),   array(5, $workOrder->getCode()));

$data = array();
$data['overallWidth'] = EFFECTIVE_COL_WIDTH;
$data['widths'][] = 15;
$data['widths'][] = $data['overallWidth'] - 15;
$data['style'] = '2column';
$data['fonts'][] = array('Tahoma', 'B', 12);
$data['fonts'][] = array('Tahoma', '', 11);
$data['rows'] = $rows;
$pdf->doColumn($data);

// The words 'Client Information', still effectively in left column
$pdf->setY($pdf->GetY() + 3);

$pdf->SetFont('Tahoma', 'B', 12);
$pdf->cell(0, 5, 'Client Information', 0);
$pdf->setX(0);
$pdf->Line(LEFT_MARGIN, $pdf->GetY() + 5, COL1_END, $pdf->GetY()+5);

$afterLeft = $pdf->GetY();
$pdf->setY($pdf->GetY() + 5);

// As of 2019-03, this is always a single client name.
$text = $clientNames;
$parts = explode("\n", str_replace("&nbsp;", " ", $text));

foreach ($parts as $part) {
	$pdf->SetFont('Tahoma', '', 11);
	$data['text'] = $part;
	$data['width'] = EFFECTIVE_COL_WIDTH;
	$data['height'] = 5;
	$pdf->doMulti($data);
}

$afterLeft = $pdf->GetY(); // y-coord at bottom of left column

$yPos = $yAfterJobName-5; // Back to top of where we split out to columns, do some calculation to set up for
                        // right column, which starts with Project Location.
                        // As with client name, as of 2019-03 we handle only one such location.

$xPos = COL2_BEGIN;
$pdf->setY($yPos);
$pdf->setX($xPos);
$pdf->SetFont('Tahoma', 'B', 12);
$pdf->cell(0, 5, 'Project Location', 0);
$pdf->Line($xPos, $pdf->GetY() + 5, RIGHT_EDGE, $pdf->GetY()+5);

$pdf->setY($pdf->GetY() + 6);

$locations = $job->getLocations();
$text = '';
if (count($locations)){
	// [Martin comment:] for now just deal with a single location
	$text = $locations[0]->getFormattedAddress();
	$text = trim($text);
}

//$text = "1900 56th Ave W\nLynnwood, WA 98036"; // Martin comment, presumably intended as an example
$parts = explode("\n", $text);

foreach ($parts as $part) {
	$pdf->SetFont('Tahoma', '', 11);
	$pdf->setX(COL2_BEGIN);
	$data['text'] =$part;
	$data['width'] = EFFECTIVE_COL_WIDTH;
	$data['height'] = 5;
	$pdf->doMulti($data);
}

$pdf->setY($pdf->GetY() + 2); // >>>00006 JM 2019-03-14: seems to presume how many lines there will be in address; I'd expect count($parts) rather than 2.

// Design Professional Information
$xPos = COL2_BEGIN;
$pdf->setX($xPos);
$pdf->SetFont('Tahoma', 'B', 12);
$pdf->cell(0, 5, 'Design Professional Information', 0);
$pdf->Line($xPos, $pdf->GetY() + 5, RIGHT_EDGE, $pdf->GetY()+5);

$pdf->setY($pdf->GetY() + 5);

//$text = "some joker\nanother joker\nPenguin"; // Martin comment, presumably intended as an example
$text = $designProNames;

$parts = explode("\n", $text);

foreach ($parts as $part) {
	$pdf->SetFont('Tahoma', '', 11);
	$pdf->setX(COL2_BEGIN);
	$pdf->SetFont('Tahoma', '', 11);
	$data['text'] = $part;
	$data['width'] = EFFECTIVE_COL_WIDTH;
	$data['height'] = 5; // >>>00001 is there a basis for this? - JM 2019-03-14

	$pdf->doMulti($data);
}

$afterRight = $pdf->GetY();

if ($afterLeft > $afterRight) {
	$pdf->setY($afterLeft);
} // No need for else {$pdf->setY($afterRight);}, that's already the value.

// ---- Contract notes
$contractNotes = $workOrder->getContractNotes();
$contractNotes = trim($contractNotes);


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
$elementgroups = overlay($workOrder, $contract);

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

// Write "Scope Of Work" or "Scopes of Work"
// JM 2020-10-30: This used to be way up above, which was a bit confusing.
/* BEGIN REPLACED 2020-10-30 JM
if (count($elementgroups)) {
    $plural = (count($elementgroups) > 1) ? 's' : '';
	// END REPLACED 2020-10-30 JM
	*/
// BEGIN REPLACEMENT 2020-10-30 JM
if (count($final)) {
    $plural = (count($final) > 1) ? 's' : '';
	// END REPLACEMENT 2020-10-30 JM

    $pdf->setY($pdf->GetY() + 6);
    $pdf->setX(LEFT_MARGIN);
    $pdf->SetFont('Tahoma', 'B', 12);
    $pdf->cell(0, 5, 'Scope' . ' Of Work', 0, 2);
    $pdf->setX(0);

    $pdf->Line(LEFT_MARGIN, $pdf->GetY(), RIGHT_EDGE, $pdf->GetY());
}




/*$totEl=0;
$totGen=0;
foreach($allTasksPack2 as $elementTasks){

	$totElement=0;
	$children=$elementTasks['items'];
	calcLevel($children, 0);

	$text = ucfirst(strtolower(trim( $elementTasks['Title'])));
	$fontBold=true;
	$pdf->SetFont('Tahoma', ($fontBold?'B':''), 13);
	$pdf->SetX(15);
	$pdf->SetY($pdf->getY()+15);
	$pdf->Cell(130, -2.5, $text, 0, 0, 'L');
	$totElement=0;
	$children=$elementTasks['items'];
	printLevel($children, 0);

	$pdf->SetXY(RIGHT_MARGIN-75, $pdf->GetY()+2);

	$pdf->SetFont('Tahoma', 'B', 13);

	$pdf->MultiCell(45, 5, "$".number_format($totElement, 2), 1, 'R', false);
	$totGeneral+=$totElement;
	$totGeneral+=$totElement;
}

function calcLevel($inArray, $level){
	global $totEl;

	$level++;

	if($inArray===null){
		return;
	}

	foreach($inArray as $elem){


		if ($elem['totCost']>0 ){

			$totElement+=$elem['totCost'];
			if(isset($elem['items']) && $elem['taskContractStatus']==1){
				$child=$elem['items'];
				calcLevel($child, $level);
			}
		}

	}

}
*/


$sumTotalEl=0;

foreach ($elemTots as $elementgroup) {

//print_r($elementgroup);

    if($elementgroup['value']>0){

    $pdf->ln(3);

    $pdf->SetFont('Tahoma', '', 12);
    $disp = '$' . number_format($elementgroup['value'], 2);

    $pdf->setX(RIGHT_EDGE - 40);
    $pdf->cell(0, 5, $disp, 0, 0, 'R');

    $pdf->setX(LEFT_MARGIN + 5);
    $pdf->SetFont('Tahoma', 'B', 12);
    $pdf->MultiCell(EFFECTIVE_WIDTH - 50, 5, ucfirst($elementgroup['name']), 0, 'L');
    $sumTotalEl+=$elementgroup['value'];
    }

} // END foreach ($final...


$pdf->ln(2);
$pdf->Line(LEFT_MARGIN, $pdf->GetY(), RIGHT_EDGE,$pdf->GetY());
$pdf->ln(1);

$pdf->SetFont('Tahoma', 'B', 12);
$disp = " Total Estimate: $" . number_format($sumTotalEl, 2);
$total_string_width = $pdf->GetStringWidth($disp);
$pdf->setX(RIGHT_EDGE - $total_string_width);
$pdf->cell(0, 5, $disp, 0, 0, 'L');

$pdf->ln(15);
$pdf->setDocumentType(''); // [Martin commment:] setting this here so that the initials thing doesnt
							// on this page.  just the ones preceeding it

//////////////////////////////
/////////////////////////////

$contractLanguageId = $contract->getContractLanguageId();
$contractLanguageId = intval($contractLanguageId);

$bullets = array();

// If we have any tasks, next bullet point is "Expanded scope of work details"
if (count($final) && strlen($contractNotes)) {
    $bullets[] = "     - Project Description & Expanded Scope of Work";
} elseif(strlen($contractNotes)){
	$bullets[] = "     - Project Description";
} elseif(count($final)){
    $bullets[] = "     - Expanded Scope of Work";
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
        $bullets[] = "     - " . $row['displayName'];
	}
}


if (count($bullets)) {
    // Print the bullet points one to a line
    $text = "Please review the following pages of this contract for:\n";
    foreach ($bullets as $bkey => $bullet) {
        if ($bkey){
            $text .= "\n";
        }
        $text .= $bullet;
    }

    $pdf->SetFont('Tahoma', 'B', 12);
    $total_string_width = $pdf->GetStringWidth($text);

    $number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
    $number_of_lines = ceil( $number_of_lines );  // [Martin comment:] Round it up.
    $pdf->SetFont('Tahoma', '', 12);
    $line_height = 5;                             // [Martin comment:] Whatever your line height is.
    $height_of_cell = $number_of_lines * $line_height;
    $height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.

    $pdf->MultiCell(EFFECTIVE_WIDTH, $line_height, $text, 0, 'L');

    $pdf->setY($pdf->GetY() + $line_height);
}

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
if(intval($contract->getHourlyRate())) {
	$hoursRate = $contract->getHourlyRate();
	$text = "Standard hourly rate for extra services : $$hoursRate/hr";
} else {
	$text = "Standard hourly rate for extra services : $$hoursRate/hr";
}


$pdf->SetFont('Tahoma', 'B', 12);
$total_string_width = $pdf->GetStringWidth($text);

$number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
$number_of_lines = ceil( $number_of_lines );  // [Martin comment:] Round it up.

$pdf->SetFont('Tahoma', '', 12);
$line_height = 5;                             // [Martin comment:] Whatever your line height is.

$height_of_cell = $number_of_lines * $line_height;
$height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.

$pdf->MultiCell(EFFECTIVE_WIDTH, $line_height, $text, 0, 'L');

$pdf->setY($pdf->GetY() + $line_height);

$text = CONTRACT_ASSUMES_FINANCIAL_RESPONSIBILITY;

$pdf->SetFont('Tahoma', 'B', 12);
$total_string_width = $pdf->GetStringWidth($text);

$number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
$number_of_lines = ceil( $number_of_lines );  // [Martin comment:] Round it up.

$pdf->SetFont('Tahoma', '', 12);
$line_height = 5;                             // [Martin comment:] Whatever your line height is.

$height_of_cell = $number_of_lines * $line_height;
$height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.

$pdf->MultiCell(EFFECTIVE_WIDTH, $line_height, $text, 0, 'L');

$pdf->setY($pdf->GetY() + $line_height);

/// [Martin comment:] here goes the total for an element
// >>>00001 JM: I think the above comment is simply wrong.

$pdf->ln(5);

$pdf->SetFont('Tahoma','B',10);
$disp = "PRINTED NAME";
$total_string_width = $pdf->GetStringWidth($disp);
$pdf->setX(LEFT_MARGIN);
$pdf->Cell($total_string_width, 4, $disp, 0, 2, 'L');

$pdf->Line(LEFT_MARGIN + 2 + $total_string_width, $pdf->GetY(), EFFECTIVE_WIDTH - 60, $pdf->GetY());
$pdf->ln(1);

$pdf->ln(10);

$pdf->SetFont('Tahoma', 'B', 10);
$disp = "SIGNATURE";
$total_string_widthsig = $pdf->GetStringWidth($disp);
$pdf->setX(LEFT_MARGIN);
$pdf->Cell($total_string_widthsig, 4, $disp, 0, 0, 'L');


$pdf->SetFont('Tahoma', 'B', 10);
$disp = "DATE";
$total_string_width = $pdf->GetStringWidth($disp);
$pdf->setX(EFFECTIVE_WIDTH - 50);
$pdf->Cell($total_string_width, 4, $disp, 0, 2, 'L');

$pdf->Line(LEFT_MARGIN + 2 + $total_string_widthsig, $pdf->GetY(), EFFECTIVE_WIDTH - 60, $pdf->GetY());

$pdf->Line(EFFECTIVE_WIDTH - 50 + 2 + $total_string_width, $pdf->GetY(), RIGHT_EDGE, $pdf->GetY());

$pdf->ln(10);

$pdf->SetFont('Tahoma','',12);
$disp = 'Unsigned, this standard contract will expire 30 days after the date shown at the top of this page';
$pdf->setX(LEFT_MARGIN);
$pdf->SetFont('Tahoma','',12);
$pdf->MultiCell(EFFECTIVE_WIDTH, 5, $disp, 0,'L');

$pdf->ln(3);

$pdf->SetFont('Tahoma','',10);
$disp = 'Please return a signed copy via email to '.CUSTOMER_INBOX.' or mail to '.CUSTOMER_ADDRESS_ONE_LINE.'.';

$pdf->setX(LEFT_MARGIN);
$pdf->SetFont('Tahoma','',10);
$pdf->MultiCell(EFFECTIVE_WIDTH, 5, $disp, 0, 'L');

////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////

/* [BEGIN Martin comment]

[0] => Array
                        (
                            [workOrderTaskId] => 25786
                            [workOrderId] => 7020
                            [taskId] => 32
                            [taskStatusId] => 1
                            [task] => Array
                                (
                                    [taskId] => 32
                                    [icon] => Root Task.jpg
                                    [description] => Closure
                                    [billingDescription] =>
                                    [estQuantity] => 1
                                    [estCost] => 0
                                    [taskTypeId] => 1
                                    [sortOrder] => 0
                                )

                            [type] => real
                            [level] => 0
                            [cost] => Array
                                (
                                    [typeName] => overhead
                                    [price] => $0.00
                                    [quantity] => 1
                                    [cost] => $0.00
                                )

                            [nte] => Array
                                (
                                    [nte] =>
                                    [cost] => 0
                                )

                        )

[END Martin comment]
*/

// -------- EXPANDED SCOPE OF WORK --------
// This is where we list tasks.

$pdf->AddPage();

if (strlen($contractNotes)) {
	$pdf->setY($pdf->getY()-8);
	$pdf->SetLeftMargin(15);
	$pdf->SetRightMargin(15);
	$pdf->SetFont('Tahoma', 'B', 12);
	$pdf->Cell(0, 7, 'Project Description', 'B', 1);

	$pdf->SetFont('Tahoma', '', 11);
	$pdf->MultiCell(0, 7, $contractNotes, 0, 'L');
}
$pdf->ln(7);
$pdf->setX(LEFT_MARGIN);
$pdf->SetFont('Tahoma', 'B', 12);
$pdf->cell($pdf->GetPageWidth() - LEFT_MARGIN - RIGHT_MARGIN, 5, 'Expanded Scope of Work', 0, 'R', 'L');

$pdf->Line(LEFT_MARGIN, $pdf->GetY()+5, $pdf->GetPageWidth()-RIGHT_MARGIN, $pdf->GetY()+5); // 20mm from each edge

$pdf->ln(1);
/*
foreach ($final as $elementgroup) {
	if (isset($elementgroup['tasks'])) {
		if (count($elementgroup['tasks']) > 0) {
			if (intval($elementgroup['elementTotal'])) {
				$pdf->setX(LEFT_MARGIN);
				$pdf->SetFont('Tahoma', 'B', 12);
				$pdf->MultiCell(0, 5, $elementgroup['elementName'], 0, 'L');

				$pdf->ln(3);

				$pdf->SetFont('Tahoma', '', 11);

				foreach (putTaskArrayInCanonicalOrder($elementgroup['tasks']) as $task) {

            // [BEGIN Martin comment]
            //	    [typeName] => overhead
            //	    [price] => $0.00
            //	    [quantity] => 1
            //	    [cost] => $0.00
            // [END Martin comment]

                    if (isset($task['cost'])) {
                        $cost = &$task['cost'];  // This was assignment before 2020-09-02, JM made it a reference/alias instead
                        if ($cost['cost'] != '$0.00') {
                            $pdf->SetY($pdf->GetY() + 2);

                            $pdf->setX($pdf->GetPageWidth() - 25 - RIGHT_MARGIN);
                            $pdf->cell(25, 5, $cost['cost'], 0, 'R', 'R');
                            if ($task['task']['arrowDirection'] != ARROWDIRECTION_DOWN){
                                $pdf->setX($pdf->GetPageWidth() - 50 - RIGHT_MARGIN);
                                $pdf->cell(25, 5, $cost['quantity'], 0, 'R', 'R');

                                $pdf->setX($pdf->GetPageWidth() - 75 - RIGHT_MARGIN);
                                $pdf->cell(25, 5, $cost['price'], 0, 'R', 'R');

                                $pdf->setX($pdf->GetPageWidth() - 110 - RIGHT_MARGIN);
                                $pdf->cell(35, 5, $cost['typeName'], 0, 'R', 'R');
                            }
                            $nte = $task['nte'];

                            $pdf->setX(LEFT_MARGIN + (intval($task['level']) * 2));

                            $text = $task['task']['billingDescription'];

                            $pdf->SetFont('Tahoma', '', 11);
                            $total_string_width = $pdf->GetStringWidth($text);

                            $pdf->SetFont('Tahoma', '', 11);

                            $pdf->MultiCell(90, 5, $text, 0, 'L');

                            $number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
                            $number_of_lines = ceil( $number_of_lines );  // [Martin comment:] Round it up.

                            $height_of_cell = $number_of_lines * 5;
                            $height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.

                            if (intval($nte['cost'])) {
                                $disp = '$' . number_format($nte['cost'], 2);

                                $pdf->setX($pdf->GetPageWidth() - 22 - RIGHT_MARGIN);
                                $pdf->SetFont('Helvetica', 'I', 10);
                                $pdf->cell(22, 5, $disp, 0, 'R', 'R');

                                $pdf->setX($pdf->GetPageWidth() - 38 - RIGHT_MARGIN);
                                $pdf->SetFont('Helvetica', 'I', 10);
                                $pdf->cell(13, 5, $nte['nte'], 0, 'R', 'R');

                                $pdf->setX($pdf->GetPageWidth() - 47 - RIGHT_MARGIN);
                                $pdf->SetFont('Helvetica', 'I', 10);
                                $pdf->cell(10, 5, 'NTE:', 0, 'R', 'R');

                                $pdf->setY($pdf->GetY() + 5);
                                $pdf->SetFont('Tahoma', '', 11);
                            }
						} // END if ($cost['cost'] != '$0.00')
					}
				} // END foreach (putTaskArrayInCanonicalOrder($elementgroup['tasks']) as $task)

				$pdf->SetY($pdf->GetY() + 2);

				$pdf->SetFont('Tahoma', 'B', 12);
				$disp = '$' . number_format($elementgroup['elementTotal'],2);
				$total_string_width = $pdf->GetStringWidth($disp);
				$pdf->setX($pdf->GetPageWidth() - $total_string_width - RIGHT_MARGIN);
				$pdf->cell(0, 5, $disp, 0, 0, 'R');

				$pdf->SetY($pdf->GetY() + 5);

				$nte = $elementgroup['elementNTE'];

				if (is_numeric($nte)) {
					if ($nte != $elementgroup['elementTotal']) {
						$pdf->SetFont('Tahoma', 'B', 12);

						$disp = '$' . number_format($nte, 2);
						$total_string_width = $pdf->GetStringWidth($disp);
						$pdf->SetFont('Helvetica', 'I', 10);

						$pdf->setX($pdf->GetPageWidth() - $total_string_width - RIGHT_MARGIN);

						$pdf->cell(0, 5, $disp, 0, 0, 'R');

						$pdf->setX(LEFT_MARGIN + 5);
						$pdf->SetFont('Helvetica', 'I', 10);
						$pdf->MultiCell($pdf->GetPageWidth() - LEFT_MARGIN - RIGHT_MARGIN - 30, 5, 'NTE:', 0, 'R');
                    }
				}
			}  // END if (intval($elementgroup['elementTotal']))
		}
	}
}*/
// test

function makeOneLevelArray($in, &$out, $prefixLevel = "   ") {
	$level = "		";

//echo strlen($prefixLevel);

    foreach ($in as $v) {
//print_r($v);
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
foreach($allTasksPack2 as $elementTasks){
	$outputArray['Title']=$elementTasks['Title'];
	$text = ucfirst(strtolower(trim( $elementTasks['Title'])));
    $key = array_search(strtolower($text), array_column($elemTots, 'name'));
    if($elemTots[$key]['value']>0){
		$fontBold=true;
		$pdf->SetFont('Tahoma', ($fontBold?'B':''), $fontSize);
		//$pdf->SetX(15);
		//$pdf->SetY($pdf->getY()+15);
		$pdf->setXY(18, $pdf->getY()+15);
		$pdf->Cell(130, -2.5, $text, 0, 0, 'L');
		if(CONTRACT_ELEMENT_TOTAL_POSITION  == 1){
			$key = array_search(strtolower($text), array_column($elemTots, 'name'));
			$pdf->SetXY(RIGHT_MARGIN-75, $pdf->GetY()-3.8);
			$pdf->SetFont('Tahoma', 'B', 13);
			$pdf->MultiCell(45, 5, "$".number_format($elemTots[$key]['value'], 2), 0, 'R', false);
			$pdf->Line(18, $pdf->GetY(), RIGHT_EDGE, $pdf->GetY()); // [Martin comment:] 20mm from each edge
		}
		$totElement=0;
		$children=$elementTasks['items'];
		printLevel($children, 0);
		if(CONTRACT_ELEMENT_TOTAL_POSITION  == 2){
			$pdf->SetXY(RIGHT_MARGIN-75, $pdf->GetY()+2);
			$pdf->SetFont('Tahoma', 'B', 13);
			$pdf->MultiCell(45, 5, "$".number_format($totElement, 2), 0, 'R', false);
		}
		//$totGeneral+=$totElement;
		$pdf->ln(1.2);
    }
}

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

$pdf->Output($job->getNumber() . '_' . $contractDateString . '_SSSContract_' . $workOrder->getWorkOrderId() . '_' . str_replace(" ","-",$contract->getNameOverride()) . '.pdf','D');

?>