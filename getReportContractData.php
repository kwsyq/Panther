<?php


use Ahc\Jwt\JWT;
require './vendor/autoload.php';

function base64url_decode($data) {
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

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
$contractId=$_REQUEST['contractId'];

//$contract=$claims['contractId'];
/*
if(isset($_REQUEST['woid'])){
	$woid=$_REQUEST['woid'];
}
*/
require_once "../inc/config.php";

$db=DB::getInstance();

$output=[
    'logopage' => "http://".  $_SERVER['SERVER_NAME'] . '/cust/' . $customer->getShortName() . '/img/pdf/logoblackai.pdf',
    'customerphone' => CUSTOMER_PHONE_WITH_DOTS
];

//$woid=$_GET['workorderId'];
$contract=new Contract($contractId);
if (!intval($contract->getContractId())){
	$retdata['status']="error";
	$retdata['message']="Contract Id not valid!";
	header('Content-Type: application/json');
    echo json_encode($retdata);
    die();
}
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

$output['contractdate']=$contractDateString;


$workOrder = new WorkOrder($contract->getWorkOrderId());
$job = new Job($workOrder->getJobId());
$output['job']['jobid']=$job->getJobId();
$output['job']['jobname']=$job->getName();
$output['job']['jobnumber']=$job->getNumber();

$output['workorder']['workorderid']=$contract->getWorkOrderId();
$output['workorder']['code']=$workOrder->getCode();

$clientNames = '';
$clients = $workOrder->getClient();
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
$designProfessionals = $workOrder->getDesignProfessional();
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

$output['location']=$parts;
$output['contractnotes']=$workOrder->getContractNotes();
//---------------------------------------


$elementgroups = overlay($workOrder, $contract);

$taskTypes = getTaskTypes();	
$clientMultiplier = $contract->getClientMultiplier();	
if( filter_var($clientMultiplier, FILTER_VALIDATE_FLOAT) === false ) {
	$clientMultiplier = 1;
}

$grandTotal = 0;

$grandNTE = 0; 
$elementNTE = 0;

$final = array(); 

foreach ($elementgroups as $ekey => $elementgroup) {

//echo $ekey."<br>cucu<br><br>";

//print_r($elementgroup);

	$elementTotal = 0; 
	$elementNTE = 0;

	$elementId = intval($ekey);	

	if ($elementId == PHP_INT_MAX) {
		$en = 'Other Tasks (Multiple Elements Attached)';
	} else if ($elementId == 0) {
		$en = 'General';
	} else {
		$en = ($elementgroup['element']) ? $elementgroup['element']['elementName'] : 'General';
	}
	
	$final[$elementId] = Array();
	$final[$elementId]['elementName'] = $en;
	$final[$elementId]['tasks'] = array();
	
	if (isset($elementgroup['tasks'])) {
		if (is_array($elementgroup['tasks'])) {
			$tasks = $elementgroup['tasks']; 
			$showfix = array(); 
            foreach ($tasks as $taskkey => $task) {			
				$sliced = array_slice($tasks, $taskkey + 1);  // The rest of array $tasks			
				$startLevel = intval($task['level']);
			
				$total = 0; 
				$sum = 0;   
			
				$estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;
				$estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;
			
                $sum = ($estQuantity * $estCost * $clientMultiplier);
			
				$total += $sum;
			
				$elementgroup['tasks'][$taskkey]['show'] = 1; 
				foreach ($sliced as $skey => $slice) {						
					if ($slice['level'] > $startLevel) {
						$str = 0; 
						$estQuantity = isset($slice['task']['estQuantity']) ? $slice['task']['estQuantity'] : 0;
						$estCost = isset($slice['task']['estCost']) ? $slice['task']['estCost'] : 0;
			
						$str = ($estQuantity * $estCost * $clientMultiplier);
						$total += $str;

                        if ( isset($task['task']['arrowDirection']) &&  ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN)) {
							$showfix[] = $taskkey + 1 + $skey;
						}			
					} else {			
						break;			
					}			
				}			
				$elementgroup['tasks'][$taskkey]['grp'] = $total;			
			}
				
			foreach ($showfix as $sf) { 
				$elementgroup['tasks'][$sf]['show'] = 0;
			}
				
			$tasks = $elementgroup['tasks']; 
			foreach ($tasks as $taskkey => $task) {
				if ($task['type'] == 'fake') {						
					$final[$elementId]['tasks'][] = $task;						
				}

				if ($task['type'] == 'real') {						
					$taskTypeId = $task['task']['taskTypeId']; 
					$wot = new WorkOrderTask($task['workOrderTaskId']);
						
					$viewMode = $wot->getViewMode();
					if (intval($viewMode) & WOT_VIEWMODE_CONTRACT) {
					    $t = $wot->getTask();
						$tt = '';
						if (isset($taskTypes[$t->getTaskTypeId()]['typeName'])){
							$tt = $taskTypes[$t->getTaskTypeId()]['typeName'];
						}

						$estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;
						$estCost = preg_replace("/[^0-9.+-]/", "", $estCost); 
							
						$estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;

						if (!$estQuantity){
							$estQuantity = 1;
						}
							
						$nte = '';
						if ($taskTypeId == TASKTYPE_HOURLY){
							$nte = isset($task['task']['nte']) ? intval($task['task']['nte']) : 1;
                        }
                        
						$cost = number_format($estQuantity * $estCost * $clientMultiplier, 2);
						
						$adder = preg_replace("/[^0-9.]/", "", $cost); 
						
						if (is_numeric($adder)) {
						    
							$elementTotal += $adder;
							$grandTotal += $adder;
						}						
						
						if (intval($nte) &&  ($taskTypeId == TASKTYPE_HOURLY)){
								$elementNTE += ($nte * $estCost * $clientMultiplier);
								$grandNTE += ($nte * $estCost * $clientMultiplier);
						} else {
							$elementNTE += $adder;
							$grandNTE += $adder;
						}
						
						if (!strlen($cost)){
							$cost = '';
						} else {
							$cost = '$' . $cost;
						}
						
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
							$task['cost'] = array('typeName' => $tt,'price' => $estCost,'quantity' => $estQuantity, 'cost' =>  '$' . number_format($task['grp'], 2));								
						} else {
							$task['cost'] = array('typeName' => $tt,'price' => $estCost,'quantity' => $estQuantity, 'cost' => $cost);
						}
						$task['nte'] = array('nte' => $nte, 'cost' => (($nte==''?1:$nte) * $estCostNoFormat * $clientMultiplier));
						
						if (intval($task['show'])) {
							$final[$elementId]['tasks'][] = $task;
						}
					}
				}
			}
		}
	}

	
	$final[$elementId]['elementTotal'] = $elementTotal;
	$final[$elementId]['elementNTE'] = $elementNTE;
} 

foreach ($final as $elementgroup) {
	if (isset($elementgroup['tasks'])) {		
		if (count($elementgroup['tasks']) > 0) {			
			if (intval($elementgroup['elementTotal'])) {

                $totalElem=array();
                $totalElem['elementtotal']=$elementgroup['elementTotal'];
                $totalElem['elementname']=$elementgroup['elementName'];
				$nte = $elementgroup['elementNTE'];
				
				if (is_numeric($nte)) {
					if ($nte != $elementgroup['elementTotal']){
                        $totalElem['elementnte']=$elementgroup['elementNTE'];
					}				
                }		
                
                $output['totals'][]=$totalElem;
			}			
		}
	}
}

if (count($final)) {		
    $output['expand']['title']="Please review the following pages of this contract for:";
    $output['expand']['list']="     - Expanded scope of work details";
}
$output['financialresponsability']=CONTRACT_ASSUMES_FINANCIAL_RESPONSIBILITY;
$disp = 'Please return a signed copy via email to '.CUSTOMER_INBOX.' or mail to '.CUSTOMER_ADDRESS_ONE_LINE.'.';
$output['disp']=$disp;

foreach ($final as $elementgroup) {
	if (isset($elementgroup['tasks'])) {
		if (count($elementgroup['tasks']) > 0) {
			if (intval($elementgroup['elementTotal'])) {
                $elem=array();


                $elem['elementname']=$elementgroup['elementName'];
				$elem['detail']=[];
				foreach ($elementgroup['tasks'] as $task) {
					$reptask=array();
                    if (isset($task['cost'])) {
                        $cost = $task['cost'];
                        if ($cost['cost'] != '$0.00') {
                            $reptask['cost']=$cost['cost'];
                            
                            if ($task['task']['arrowDirection'] != ARROWDIRECTION_DOWN){
                                $reptask['quantity']=$cost['quantity'];
                                $reptask['price']=$cost['price'];
                                $reptask['typename']=$cost['typeName'];
                                
                            }							
                            $nte = $task['nte'];							
                            
                            $text = $task['task']['billingDescription'];

                            $reptask['description']=$task['task']['billingDescription'];                                
                            
//$pdf->MultiCell(90, 5, $text, 0, 'L');
                                                            
                            if (intval($nte['cost'])) {
                                $disp = '$' . number_format($nte['cost'], 2);								
                                $reptask['nte']=$nte['cost'];                                
                            }
						}
					}
					if(count($reptask)>0)
                    	$elem['detail'][]=$reptask;
				}
				
				$disp = '$' . number_format($elementgroup['elementTotal'],2);
				//$total_string_width = $pdf->GetStringWidth($disp);
				//$pdf->setX($pdf->w - $total_string_width - RIGHT_MARGIN);
				//$pdf->cell(0, 5, $disp, 0, 0, 'R');
								
				
				$nte = $elementgroup['elementNTE'];
				
				if (is_numeric($nte)) {				
					if ($nte != $elementgroup['elementTotal']) {						
						//$pdf->SetFont('Tahoma', 'B', 12);
						
						$disp = '$' . number_format($nte, 2);
						//$total_string_width = $pdf->GetStringWidth($disp);
						//$pdf->SetFont('Helvetica', 'I', 10);
						
						//$pdf->setX($pdf->w - $total_string_width - RIGHT_MARGIN);

						//$pdf->cell(0, 5, $disp, 0, 0, 'R');
				
						//$pdf->setX(LEFT_MARGIN + 5);
						//$pdf->SetFont('Helvetica', 'I', 10);
						//$pdf->MultiCell($pdf->w - LEFT_MARGIN - RIGHT_MARGIN - 30, 5, 'NTE:', 0, 'R');
                    }				
				}
			}  // END if (intval($elementgroup['elementTotal']))
		}
	}
}
$output['elems']=$elem;


//---------------------------------------
header('Content-Type: application/json');
echo json_encode($output);
die();







