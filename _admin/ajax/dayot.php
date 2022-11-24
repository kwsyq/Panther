<?php
/*  _admin/ajax/dayot.php

    EXECUTIVE SUMMARY: set a new value of dayOT for a particular customerPerson.    

    INPUT $_REQUEST['personId']: primary key for DB table Person 
    INPUT $_REQUEST['customerId']: primary key for DB table Customer. NOTE that this appears to be an unusual case where we 
            allow changes for a different customer than the one associated with the logged-in user. 
            >>>00004 JM 2019-05-13: seems wrong to me to do that in a function with really no security at all around who calls it.	 
    $_REQUEST['dayOT']: new value

    In DB table customerPerson, sets this as the new value of dayOT for this (customerId + personId).

    Returns JSON for an associative array with the following members:
        * 'status': 'success' (always)
        * 'dayot': associative array corresponding to the resulting row in DB table customerPerson, 
            which actually has a very large number of columns. dayot return is 0 if no row affected. 
*/    

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'success';

$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$customerId = isset($_REQUEST['customerId']) ? intval($_REQUEST['customerId']) : 0;
$dayOT = isset($_REQUEST['dayOT']) ? intval($_REQUEST['dayOT']) : 0;

$db = DB::getInstance();

$query = " update " . DB__NEW_DATABASE . ".customerPerson  set ";
$query .= " dayOT = " . intval($dayOT) . " ";
$query .= " where personId = " . intval($personId) . " ";
$query .= " and customerId = " . intval($customerId) . " ";

$result=$db->query($query);

if(!$result){

	$logger->errorDb('1569439970', '', $db);
}


$query  = " select * ";
$query .= " from " . DB__NEW_DATABASE . ".customerPerson  ";
$query .= " where personId = " . intval($personId) . " ";
$query .= " and customerId = " . intval($customerId) . " ";

$d = 0;

$result = $db->query($query);

if ($result) { 
	if ($result->num_rows > 0) {
	    // there is only one result because personId + customerId is a candidate key in DB table CustomerPerson.
	    $row = $result->fetch_assoc();
	    $d = $row['dayOT'];
	} else {

		$logger->debug2('1571772008', 'No results for : ['.$query.']');		

	}
} else {

	$logger->errorDb('1569440126', ' ', $db);
	
}

$data['dayot'] = $d;

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$workorder = new WorkOrder($workOrderId);

if (intval($workorder->getWorkOrderId())){	
	$workorder->update($_REQUEST);	
	$workorder = new WorkOrder($workOrderId);
	if (intval($workorder->getWorkOrderId())){	
		$data['status'] = 'success';		
		$data['isVisible'] = $workorder->getIsVisible();
		$data['workOrderId'] = $workorder->getWorkOrderId();		
	}
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$data['number'] = $job->getNumber();
$data['name'] = $job->getName();
$data['description'] = $job->getDescription();
//$data['notes'] = $job->getNotes();
$data['status'] = $job->getStatus();
$data['created'] = $job->getCreated();
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

header('Content-Type: application/json');
echo json_encode($data);
die();

?>