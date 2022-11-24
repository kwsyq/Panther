<?php 
// use Ahc\Jwt\JWT;
// require './vendor/autoload.php';

$isDebug=1;
/*
function base64url_decode($data) {
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
*/
require_once "../inc/config.php";
require_once "../inc/functions.php";

function getContractStatusName($statusId=0){
	if($statusId==0)
		return 'DRAFT';
	if($statusId==1)
		return 'REVIEW';
	if($statusId==2)
		return 'COMMITED';
	if($statusId==3)
		return 'DELIVERED';
	if($statusId==4)
		return 'SIGNED';
	if($statusId==5)
		return 'VOID';
	if($statusId==6)
		return 'VOIDED';
}
function getPaymentType($c){
	if($c==1)
		return 'CREDIT CARD';
	if($c==2)
		return 'CHECK';
	if($c==3)
		return 'CASH';
	return '';
}

$db=DB::getInstance();
$db->query("delete from masterList31");

function getContractTotal($contractId, $workOrderId){
	global $db;

if($workOrderId<=14174){
	return -1;
	die();
}
		$query = "SELECT elementId as id, elementName as Title, null as parentId, 
		null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, '' as billingDescription, null as cost, null as quantity, 
		null as totCost, null as taskTypeId, '' as icon, '' as wikiLink, null as taskStatusId, 0 as taskContractStatus, null as hoursTime,  
		elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
		from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
		UNION ALL
		SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId, 
		w.extraDescription as extraDescription, w.billingDescription as billingDescription, w.cost as cost, w.quantity as quantity, w.totCost as totCost,
		w.taskTypeId as taskTypeId, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId,  w.taskContractStatus as taskContractStatus, wt.tiiHrs as hoursTime,
		getElement(w.workOrderTaskId),
		e.elementName, false as Expanded, false as hasChildren
		from workOrderTask w
		LEFT JOIN task t on w.taskId=t.taskId
		LEFT JOIN (
		    SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
		    FROM workOrderTaskTime wtH
		    GROUP BY wtH.workOrderTaskId
		    ) AS wt
		    on wt.workOrderTaskId=w.workOrderTaskId
		LEFT JOIN element e on w.parentTaskId=e.elementId
		WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null AND w.internalTaskStatus != 5 ORDER BY FIELD(elementName, 'General') DESC";

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
if(count($out)==0){
	return 0;
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


		// Get task types: overhead, fixed etc.
		$allTaskTypes = array();
		$allTaskTypes = getTaskTypes();


		// calculate cost per element and total cost.
		$wo = new WorkOrder($workOrderId);
		$jobId = $wo->getJobId();
		$job = new Job($jobId);

		$query = " SELECT e.elementId ";
		$query .= " FROM " . DB__NEW_DATABASE . ".element e ";
		$query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wo on wo.elementId = e.elementId ";
		$query .= " WHERE jobId = " . intval($jobId) ." group by e.elementId ";

		$result = $db->query($query);

		$errorElements = ''; 
		if (!$result) {
		    $errorId = '637798491724341928';
		    $errorElements = 'We could not retrive the cost for the elements. Database error. Error id: ' . $errorId;
		    $logger->errorDb($errorId, 'We could not retrive the elements for this job', $db);

		}
		if ($errorElements) {
		    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorElements</div>";
		}


		$allElements = [];
		if(!$errorElements) {
		    while ($row = $result->fetch_assoc()) {
		        $allElements[] = $row['elementId'];
		    }
		}

		unset($errorElements);


		$elementsCost = [];
		$errorCostEl = '';
		foreach($allElements as $value) {

		        $query = "select workOrderTaskId,
		        parentTaskId, totCost
		        from    (select * from workOrderTask
		        order by parentTaskId, workOrderTaskId) products_sorted,
		        (select @pv := '$value') initialisation
		        where   find_in_set(parentTaskId, @pv) and parentTaskId = '$value' and workOrderId = '$workOrderId'
		        and     length(@pv := concat(@pv, ',', workOrderTaskId))";

		        $result = $db->query($query);
		        if (!$result) {
		            $errorId = '637798493011752921';
		            $errorCostEl = 'We could not retrive the total cost for each Element. Database error. Error id: ' . $errorId;
		            $logger->errorDb($errorId, 'We could not retrive the total cost for each Element', $db);
		        }
		      
		        if(!$errorCostEl) {
		            while( $row=$result->fetch_assoc() ) { 
		                $elementsCost[$row['parentTaskId']][] = $row['totCost'];
		    
		            }
		        }
		 
		}

		if ($errorCostEl) {
		    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorCostEl</div>";
		}


		unset($errorCostEl);
		$sumTotalEl = 0;

		foreach($elementsCost as $key=>$el) {
		    $elementsCost[$key] = array_sum($el);
		    $sumTotalEl += array_sum($el);
		}
//echo $workOrderId."<br>";
//print_r($elementsCost);
//echo "<br>";
//return $sumTotalEl;
//die();
		// get Ids of Level One WOT
		$levelOne = $workOrder->getLevelOne($error_is_db);
		$errorWotLevelOne = "";  
		if($error_is_db) { //true on query failed.
		    $errorId = '637799252773313187';
		    $errorWotLevelOne = "Error fetching Level One WorkOrderTasks. Database Error. Error Id: " . $errorId; // message for User
		    $logger->errorDB($errorId, "getLevelOne() method failled", $db);
		}

		if ($errorWotLevelOne) {
		    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWotLevelOne</div>";
		}
		unset($errorWotLevelOne);


		$levelOneTasks = [];
		$errorWotLevelOneTasks = "";
		foreach($levelOne as $value) {

		    $query = "select workOrderTaskId,
		    parentTaskId, taskContractStatus
		    from    (select * from workOrderTask
		    order by parentTaskId, workOrderTaskId) products_sorted,
		    (select @pv := '$value') initialisation
		    where   find_in_set(parentTaskId, @pv)
		    and     length(@pv := concat(@pv, ',', workOrderTaskId))";

		    $result = $db->query($query);

		    if (!$result) {
		        $errorId = '637800842774158005';
		        $errorWotLevelOneTasks = 'We could not retrive the workorder tasks for level one. Database error. Error id: ' . $errorId;
		        $logger->errorDb($errorId, 'We could not retrive the workorder tasks for level one', $db);
		    }
		    
		    if(!$errorWotLevelOneTasks) {
		        while( $row=$result->fetch_assoc() ) { 
		            if($row['taskContractStatus'] == 9) {
		                // this level 1 workOrderTaskId has at least one children with status 9. not display
		                $levelOneTasks[] = $value; 
		            }
		        }
		    }

		    
		}
die();
return 22;

}
$startDate=new DateTime();


$sql="SELECT j.jobId, j.NUMBER AS jobNumber, 
	j.NAME AS jobName, 
	js.jobStatusName, 
	w.workOrderId, 
	w.description, 
	wodt.typeName AS workordertype,
	wos.statusName AS workOrderStatus, 
	getClient(w.workOrderId) AS client,
	getDesignProfessional(w.workOrderId) AS designProfessional,
	getEOR(w.workOrderId) AS Eor,
	CONCAT(l.address1, ' ', l.city, ' ', l.state, ' ', l.postalCode, ' ', l.country) AS address
	FROM job j 
		INNER JOIN location l ON j.locationId=l.locationId
		LEFT JOIN workOrder w ON w.jobId=j.jobId
		inner JOIN workOrderDescriptionType wdt on wdt.workOrderDescriptionTypeId =w.workOrderDescriptionTypeId 
		INNER JOIN jobStatus js ON js.jobStatusId=j.jobStatusId
		INNER JOIN workOrderStatus wos ON wos.workOrderStatusId=w.workOrderStatusId 
		left join workOrderStatusTime wst on w.workOrderStatusTimeId =wst.workOrderStatusTimeId 
		LEFT JOIN workOrderDescriptionType wodt ON wodt.workOrderDescriptionTypeId=w.workOrderDescriptionTypeId";
$res=$db->query($sql);
if(!$res){
	echo $sql;
	echo $db->error;
	die();
}
$totRows=0;
$db->query("delete from masterList31");
while($row=$res->fetch_assoc()){
	$jobNumber=$row['jobNumber'];
	$jobName=$db->real_escape_string($row['jobName']);
	$job=new Job($row['jobId']);
	$jobStatus=$job->getJobStatusName();
	$workOrder=new WorkOrder($row['workOrderId']);

	if($row['workOrderId']<=14174){ // old
		$contracts=$workOrder->getContracts();
		$contract=(count($contracts)>0?$contracts[count($contracts)-1]:NULL);
	} else { //new
		$contract=$workOrder->getContractWo();
	}
	if($contract){
		$contractStatusId=$db->query("select committed from contract where contractId=".$contract->getContractId())->fetch_assoc()['committed'];
	}
	$invoices=$workOrder->getInvoices();
	if($invoices){
		foreach($invoices as $invoice){
			$adjustments=$invoice->getAdjustments();
			$adj="-";
			$total=$invoice->getTotal();
			foreach($adjustments as $a){
				$adj.=($a['invoiceAdjustTypeId']==1?"$":$a['invoiceAdjustTypeId']==2?"%":"QBS$").$a['amount']."|";
				if($a['invoiceAdjustTypeId']==1 || $a['invoiceAdjustTypeId']==3){
					$total+=$a['amount'];
				} else {
					$total-=($total*$a['amount']/100);
				}

			}
			$payments=$invoice->getPayments();
			$paid=0;
			$payms="";
			$c=null;
			foreach($payments as $p){
				$c=new CreditRecord($p['creditRecordId']);
				$paid+=$p['amount'];
			}

			$sql1="insert into masterList31 set 
				jobNumber='".$jobNumber."',
				jobName='".$jobName."',
				jobStatus='".$jobStatus."',
				workOrderId=".$row['workOrderId'].",
				`workOrderName`='".$db->real_escape_string($row['description'])."',
				`workOrderStatus`='".$db->real_escape_string($row['workOrderStatus'])."',
				`client`='".$db->real_escape_string($row['client'])."',
				`designProfessional`='".$db->real_escape_string($row['designProfessional'])."',
				`eor`='".$db->real_escape_string($row['Eor'])."',
				`location`='".$db->real_escape_string($row['address'])."',
				`contractDate`='".($contract?$contract->getContractDate():null)."',
				`contractTotal`='".($contract?getContractTotal($contract->getContractId(), $row['workOrderId']):0)."',
				`contractStatus`='".($contract?getContractStatusName($contractStatusId):null)."',
				`invoiceNumber`='".$invoice->getInvoiceId()."',
				`invoiceDate`='".$invoice->getInvoiceDate()."',
				`invoiceOpenBalance`='".(intval($total)-intval($paid))."',
				`invoiceTotal`='".$invoice->getTotal()."',
				`invoiceDiscount`='".$adj."',
				`invoiceAdj`='".$total."',
				`invoiceStatus`='".$invoice->getStatusName()."',
				`paymentReceivedFrom`='".(isset($c) && $c!=null?$db->real_escape_string($c->getReceivedFrom()):'')."',
				`paymentDateReceived`='".(isset($c) && $c!=null?$c->getDepositDate():'')."',
				`paymentDateCredited`='".(isset($c) && $c!=null?$c->getCreditDate():'')."',
				`paymentRecordType`='".(isset($c) && $c!=null?getPaymentType($c->getCreditRecordTypeId()):'')."',
				`paymentReference`='".(isset($c) && $c!=null?$c->getReferenceNumber():'')."',
				`paymentNotes`='".(isset($c) && $c!=null?$c->getNotes():'')."',
				`paymentAmountPaid`='".$paid."'
				";

				$r1=@$db->query($sql1);
				if(!$r1){
					echo $sql1;
					echo $db->error;
					die();
				}
	$totRows++;

		}

	} else {
		$sql1="insert into masterList31 set 
			jobNumber='".$jobNumber."',
			jobName='".$jobName."',
			jobStatus='".$jobStatus."',
			workOrderId=".$row['workOrderId'].",
			`workOrderName`='".$db->real_escape_string($row['description'])."',
			`workOrderStatus`='".$db->real_escape_string($row['workOrderStatus'])."',
			`client`='".$db->real_escape_string($row['client'])."',
			`designProfessional`='".$db->real_escape_string($row['designProfessional'])."',
			`eor`='".$db->real_escape_string($row['Eor'])."',
			`location`='".$db->real_escape_string($row['address'])."',
			`contractDate`='".($contract?$contract->getContractDate():null)."',
			`contractTotal`='".'0'."',
			`contractStatus`='".($contract?getContractStatusName($contractStatusId):null)."',
			`invoiceNumber`='0',
			`invoiceDate`=null,
			`invoiceOpenBalance`='".'0'."',
			`invoiceTotal`='".'0'."',
			`invoiceDiscount`='',
			`invoiceAdj`='0',
			`invoiceStatus`='',
			`paymentReceivedFrom`='',
			`paymentDateReceived`=null,
			`paymentDateCredited`=null,
			`paymentRecordType`='',
			`paymentReference`='',
			`paymentNotes`='',
			`paymentAmountPaid`='".'0'."'
			";

			$r1=$db->query($sql1);
			if(!$r1){
				echo $sql1;
				echo $db->error;
				die();
			}
	$totRows++;
	}



}

$endDate=new DateTime();
$sql="insert into `logLoadMasterList` (startDate, masterlist, endDate, rows) values ('".$startDate->format("Y-m-d H:i:s")."', 'masterList31', '".$endDate->format("Y-m-d H:i:s")."', ".$totRows.")";

$res1=$db->query($sql);
if(!$res1){
	echo $db->error;
}
//echo $sql;


 ?>