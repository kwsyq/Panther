<?php 
/*  ajax/autocomplete_external_person.php

    Usage: in addworkorderperson.php
        On iframe addworkorderperson in the search afrea a match is made against any portion 
        of the person's first or last name ( only for externals persons ).

    INPUT $_REQUEST['q'] ('q' for "query") is interpreted as a search string. Blanks are treated as multicharacter wild cards (SQL '%'),
    and a match is made against any portion of the person's first or last name (so match need not be at beginning of name).
    INPUT $_REQUEST['companyId'] (optional) limits search to people with a connection to the company in question in DB table CompanyPerson.
    
    Returns JSON for an associative array with the following members:
      * 'query': the original query string q.
      * 'suggestions': an array of pairs value=>data, where value is a lastName + space + firstName 
         and data is a personId; ordered by lastName, firstName. 
*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-06-05, Validator2::primary_validation includes validation for DB, customer, customerId
list($error, $errorId) = Validator2::primary_validation();

if ($error) {
    $logger->error2($errorId, "Error(s) found in primary validation: $error");
    $data = array();
    $data['status']='fail';
    $data['info']= "Error(s) found in primary validation: ".  $error;
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
}
// End ADD

$v=new Validator2($_REQUEST);
$v->rule('required', 'q');

if (!$v->validate()) {
    $logger->error2('637776768605484757', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    die();
}

$q = $_REQUEST['q'];
$companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;

$db = DB::getInstance();

$parts = explode(" ", $q);
$str = '';
foreach ($parts as $part) {
    // Not the first    	
	if (strlen($str)) {
		$str .= '%';
	}
	$str .= $db->real_escape_string($part);
}


$employees = $customer->getEmployees();
$arrEmployees = []; // employees Ids
foreach ($employees as $employee) {
    $arrEmployees[] = intval($employee->getUserId());
}
$arrEmployees2 = implode(',', array_map('intval', $arrEmployees)); 

if (intval($companyId)) {
    $query = "select p.personId, p.firstName, p.lastName from " . DB__NEW_DATABASE . ".person p ";
    $query .= " join " . DB__NEW_DATABASE . ".companyPerson cp on p.personId = cp.personId ";
    $query .= " where cp.companyId = " . intval($companyId) . " and (p.firstName like '%" . $str . "%' ";
    //$query .= " or p.lastName like '%" . $str . "%') AND p.personId NOT IN  ( $arrEmployees2 ) ";
    $query .= " or p.lastName like '%" . $str . "%') ";
    $query .= " order by p.lastName, p.firstName ";    
} else {
    $query = "select personId, firstName, lastName from " . DB__NEW_DATABASE . ".person ";
    $query .= " where ( firstName like '%" . $str . "%' ";
//    $query .= " or lastName like '%" . $str . "%' ) AND personId NOT IN  ( $arrEmployees2 )  ";
    $query .= " or lastName like '%" . $str . "%' ) ";
    $query .= " order by lastName, firstName ";
   
}

$persons = array();

$result = $db->query($query);

if (!$result) {
    $logger->errorDb('637776768737891356', 'Select external person details failed: Hard DB error', $db);
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $new = array();
        $new['value'] = $row['lastName'] . ' ' . $row['firstName'];
        $new['data'] = $row['personId'];
        $persons[] = $new;
    }
} 


$data = array();
$data['query'] = $q;
$data['suggestions'] = $persons;

header('Content-Type: application/json');

echo json_encode($data);

die();
?>