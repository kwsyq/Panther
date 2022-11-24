<?php
/*  _admin/ajax/weekot.php

    EXECUTIVE SUMMARY: set a new value of weekOT for a particular customerPerson.    

    INPUT $_REQUEST['personId']: primary key for DB table Person 
    INPUT $_REQUEST['customerId']: primary key for DB table Customer. NOTE that this appears to be an unusual case where we 
            allow changes for a different customer than the one associated with the logged-in user. 
            >>>00004 JM 2019-05-13: seems wrong to me to do that in a function with really no security at all around who calls it.	 
    $_REQUEST['dayOT']: new value

    In DB table customerPerson, sets this as the new value of weekOT for this (customerId + personId).

    Returns JSON for an associative array with the following members:
        * 'status': 'success' (always)
        * 'weekot': associative array corresponding to the resulting row in DB table customerPerson, 
            which actually has a very large number of columns. weekot return is 0 if no row affected.
            
   >>>00037: a lot of common code with _admin/ajax/dayot.php, probably should share most code.          
*/    

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'success';

$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$customerId = isset($_REQUEST['customerId']) ? intval($_REQUEST['customerId']) : 0;
$weekOT = isset($_REQUEST['weekOT']) ? intval($_REQUEST['weekOT']) : 0;

$db = DB::getInstance();

$query = " update " . DB__NEW_DATABASE . ".customerPerson  set ";
$query .= " weekOT = " . intval($weekOT) . " ";
$query .= " where personId = " . intval($personId) . " ";
$query .= " and customerId = " . intval($customerId) . " ";

$db->query($query); // >>>00002 ignores failure on DB query!

$query  = " select * ";
$query .= " from " . DB__NEW_DATABASE . ".customerPerson  ";
$query .= " where personId = " . intval($personId) . " ";
$query .= " and customerId = " . intval($customerId) . " ";

$d = 0;

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
	if ($result->num_rows > 0) {
	    // >>>00018 really could be 'if' instead of 'while': personId + customerId is a candidate key in DB table CustomerPerson.
		while ($row = $result->fetch_assoc()) {
			$d = $row['weekOT'];
		}
	}
} // >>>00002 ignores failure on DB query!

$data['weekot'] = $d;

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