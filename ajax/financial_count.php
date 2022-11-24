<?php
/*  ajax/financial_count.php

    NO INPUT
    
    Requires admin-level rights for payments.
    
    Returns JSON equivalent of the content of the DB table Ajaxdata, which is 
     built by crons/ajaxdata.php. Currently (2019-05) limited to dataName in 
     ('tab0', 'tab1', 'tab2', 'tab3', 'tab4', 'tab5'). 
    For each matching row in the table, we use the 'dataName' and 'dataArray'. 
    The return represents an associative array, where each 'dataName' functions 
     as an index (so those had better be unique!) and the corresoponding value is 
     drawn from the 'dataArray' in the same row, this time serialized as JSON 
     rather than the serialization we do for the DB, which differs (the latter 
     is a base64 serialization to make a blob).
*/

include '../inc/config.php'; 
include '../inc/perms.php';

sleep(1); // Wait a second, presumably to leave a time when the "loading AJAX" icon is visible

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    // >>>00002 should probably log attempted access by someone without permission
    // NOTE that this doesn't even return a 'fail' status (there is no overt status return in any case), just dies.
	die();
}

$payload = array();
$tabs = array(); // >>>00007 initialized but apparently never used
$db = DB::getInstance();

$query = "SELECT dataName, dataArray FROM " . DB__NEW_DATABASE . ".ajaxData "; // before v2020-3, was just SELECT * 
$query .= "WHERE dataName IN ('tab0', 'tab1', 'tab2', 'tab3', 'tab4', 'tab5') ";

$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payload[$row['dataName']] = unserialize(base64_decode($row['dataArray']));
    }
} else  {
    $logger->errorDb('1594157426', "Hard DB error", $db);
} 

$data = array();
$data['status'] = 'success';
$data['payload'] = $payload;

header('Content-Type: application/json');
echo json_encode($data);
die();

?>