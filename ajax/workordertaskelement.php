<?php 
/* ajax/workordertaskelement.php

    INPUT $_REQUEST['workOrderTaskId']: Primary key to DB table workOrderTask 
    INPUT $_REQUEST['elementId']: Primary key to DB table Element
    INPUT $_REQUEST['state']: Boolean ('true' or 'false'; anything other than 'true' is considered 'false').
        Depending on state, this is an insert or deletion in table workOrderTaskElement.
        >>>00012: would make a lot more sense to call this 'insert' rather than 'state'
        
    NOTE that because of how it is written, if you "insert" where an equivalent row already exists, 
    you'll get a new workOrderTaskElementId.
    
    NOTE very similar name to ajax/workordertaskelements.php, just that the other is pluralized.

    >>>00016, >>>00026 JM: Looks to me like this would do bad things if called with an invalid workOrderTaskId, especially 0.
    Would violate referential integrity.
    
    >>>00028 JM: Should be transactional. As it is, you could get a delete & no insertion giving the opposite effect than intended.
    
    Returns JSON for an associative array with the following members:
        * 'status': always "success".
        * 'row': will be present only on insertion or (probably accidentally) on failed deletion. 
          An associative array equivalent to the row inserted:
            * 'workOrderTaskElementId': newly created
            * 'workOrderTaskId': as input
            * 'elementId': as input 
*/

include '../inc/config.php';
include '../inc/access.php';

sleep(1); // So user will see AJAX icon & know something is happening.

$db = DB::getInstance();

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? $_REQUEST['workOrderTaskId'] : 0;
$elementId = isset($_REQUEST['elementId']) ? $_REQUEST['elementId'] : 0;
$state = isset($_REQUEST['state']) ? $_REQUEST['state'] : '';
if ($state != 'true'){
    $state = 'false';
}
$state = ($state == 'true') ? 1 : 0;

$workOrderTask = new WorkOrderTask($workOrderTaskId);

$query  = " delete from " . DB__NEW_DATABASE . ".workOrderTaskElement ";
$query .= " where workOrderTaskId = " . intval($workOrderTaskId) . " ";
$query .= " and elementId = " . intval($elementId) . " ";

$db->query($query);  // >>>00002 ignores failure on DB query! Similar issue throughout file, not noted each time.

if ($state) {
    $query  = " insert into " . DB__NEW_DATABASE . ".workOrderTaskElement (workOrderTaskId,elementId) values (";
    $query .= " " . intval($workOrderTaskId) . " ";
    $query .= " ," . intval($elementId) . ") ";

    $db->query($query);    
}

$query  = " select * from " . DB__NEW_DATABASE . ".workOrderTaskElement ";
$query .= " where workOrderTaskId = " . intval($workOrderTaskId) . " ";
$query .= " and elementId = " . intval($elementId) . " ";

$dat = false;

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dat = $row;
        }
    }
}

$data = array();
$data['status'] = 'success';

if ($dat) {
    $data['row'] = $dat;
}

header('Content-Type: application/json');
echo json_encode($data);
die();

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$personIds = isset($_REQUEST['personIds']) ? $_REQUEST['personIds'] : array();

if (!is_array($personIds)){
    $personIds = array();
}

$workOrderTask->addPersonIds($personIds);
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

?>