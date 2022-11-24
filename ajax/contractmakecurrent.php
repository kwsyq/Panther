<?php 
/*  ajax/contractmakecurrent.php

    EXECUTIVE SUMMARY: >>>00001 JM 2019-04: if I  understand correctly -- and I might not --
    the intent is to make a particular version of the contract the current, uncommitted 
    version of the contract to work on further. As long as the contractId passed in is NOT
    a committed contract, it is OK that it can readily be deleted here, and that a new row in 
    DB table contract will be created, copying some of its data.
    NOTE that the new contractId is not returned. That is OK, because the algorithm to find the
    latest contract for this workOrder will be able to find it.
    
    >>>00001 Maybe this is intended to take a committed contract and base a new uncommitted version on that?
    >>>00042 Work with Ron & Damon to determine intent.
    Talked briefly with Damon 2020-04-02: we need to get back to this and really understand it as part of a broader discussion on contracts & invoices.
    
    >>>00026 Anyway, I strongly suspect this is buggy, in that it can delete a committed contract. JM 2019-04
    
    INPUT $_REQUEST['contractId']

    Returns JSON for an associative array with only the following member:
    * status: "fail" if contractId not valid or any of several other failures; 
              "success" on success. 
    >>>00026: Looks to me (JM) that it will return success even if last $db->query($query) actually returns a bad status. 
*/


include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$db = DB::getInstance();

$contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;

// Make sure this is a valid contractId, otherwise we will just return "fail"
if (existContract($contractId)) {
    // Get all data for this contractId in DB table Contract into $row; only can be one such row, since this is primary key
	$row = false;
	$query = " select * from " . DB__NEW_DATABASE . ".contract where contractId = " . intval($contractId);	
	if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
		}
	} // >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.
	
	if ($row) { // Make sure we got the data before proceeding
	    //  Selects a "del" contractId for the latest uncommitted contract matching the workOrderId. 
	    //  "Latest" is determined by the fact that contractIds increase monotonically over time, so we use a "max". 
	    //  (There should be no more than one uncommited contract, anyway. 
	    //   There does not seem to be any check to make sure this differs from the original input contractId. 
	    //   It should be OK that we can delete an earlier version of the row we are inserting.)
	    
		$del = false;
		
// BEGIN MARTIN COMMENT >>>00001
// FIX THIS UP TO MAKE SURE IT IS DELETING ONE THATS NOT COMMITTED
// ETC
// CURRENTLY SEEMS TO BE DELETING ISELF
// END MARTIN COMMENT
		$query = "  select max(contractId) as contractId ";
		$query .= "     from " . DB__NEW_DATABASE . ".contract ";
		$query .= "     where workOrderId = " . intval($row['workOrderId']) . " ";
		$query .= "     and committed = 0 ";

// BEGIN MARTIN COMMENT >>>00001
// might also want to check here that it is deleting the right one ... i.e.
// is there some shitty uncommitted one before the currently committed ones
// also check if "committing" leaves it with no uncommitted versions
// and deal with that too
// END MARTIN COMMENT		
		
		$db->query($query); // >>>00007 redundant, already have this
		
		if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				$del = $result->fetch_assoc();
			}
		}

		if ($del) {
			// For that "del" contractId, delete that row from the contract table...
			$query = " delete from " . DB__NEW_DATABASE . ".contract ";
			$query .= " where contractId = " . intval($del['contractId']);
			$db->query($query);		
			
			// ... and insert a new row using workOrderId, nameOverride, contractDate, termsId, 
			//  contractLanguage, data, clientMultiplier based on the original input contractId.
			// NOTE that this will get a new, distinct contractId
			$query = " insert into " . DB__NEW_DATABASE . ".contract (";
			$query .= "    workOrderId ";
			$query .= "   , nameOverride ";
			$query .= "   , contractDate ";
			$query .= "   , termsId ";
			$query .= "   , contractLanguageId ";
		//	$query .= "   , committed "; // commented out by Martin before 2019
		//	$query .= "   , committedTime "; // commented out by Martin before 2019
			$query .= "   , data ";
		//	$query .= "   , commitNotes "; // commented out by Martin before 2019
		//	$query .= "   , commitPersonId "; // commented out by Martin before 2019
			$query .= "   , clientMultiplier ";
			$query .= ") values (";
			$query .= "  " . intval($row['workOrderId']) . " ";
			$query .= " ,  '" . $db->real_escape_string($row['nameOverride']) . "' ";
			$query .= " ,  '" . $db->real_escape_string($row['contractDate']) . "' ";
			$query .= " ,  " . intval($row['termsId']) . " " ;
			$query .= " ,  " . intval($row['contractLanguageId']) . " ";
			//$query .= " ,  " . intval($row['committed']) . " " ; // commented out by Martin before 2019
			//$query .= " ,  '" . $db->real_escape_string($row['committedTime']) . "' "; // commented out by Martin before 2019
			$query .= " ,  '" . $db->real_escape_string($row['data']) . "' ";
			//$query .= " ,  '" . $db->real_escape_string($row['commitNotes']) . "' "; // commented out by Martin before 2019
			//$query .= " ,  " . intval($row['commitPersonId']) . " " ; // commented out by Martin before 2019
			$query .= " ,  " . preg_replace("/[^0-9.]/","",$row['clientMultiplier']) . " ";
			$query .= ")";

			$db->query($query);
			
			$data['status'] = 'success';
		}
	}
	
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//	$query = " select * from " . DB__NEW_DATABASE . ".workOrderTask where workOrderId = " . intval($workOrderId) . " and taskId = " . intval($taskId);	
//	$workOrderTaskId = 0;
	
//	if ($result = $db->query($query)) {
//		if ($result->num_rows > 0){
	
//		}
//	}
// END COMMENTED OUT BY MARTIN BEFORE 2019
}

header('Content-Type: application/json');

echo json_encode($data);

die();

?>