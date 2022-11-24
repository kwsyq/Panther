<?php
/*  ajax/setworkorderstatusnew.php

    INPUT $_REQUEST['workOrderStatusId']: Primary key in DB table WorkOrderStatus 
    INPUT $_REQUEST['workOrderId']: Primary key in DB table WorkOrder
    INPUT $_REQUEST['customerPersonIds']: May be a single customerPersonId or a serialized array.
                                          These go into rows of wostCustomerPerson.
    INPUT $_REQUEST['note']: note for the workOrder status
    
    Set status of workOrder; also can set customerPersonIds, note.
    
    No explicit return.

    >>>00016 Even less validation than usual (doesn't check for workOrderId existing).
*/

include '../inc/config.php';
include '../inc/access.php';

ini_set('display_errors',1);
error_reporting(-1);

$db = DB::getInstance();

$workOrderStatusId = isset($_REQUEST['workOrderStatusId']) ? intval($_REQUEST['workOrderStatusId']) : 0;
$customerPersonIds = isset($_REQUEST['customerPersonIds']) ? $_REQUEST['customerPersonIds'] : 0;
$note = isset($_REQUEST['note']) ? $_REQUEST['note'] : "";

$workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : 0;
$workOrder = new WorkOrder($workOrderId, $user);	


if (!is_array($customerPersonIds)) {
    // parse string $customerPersonIds into an array
    // This is a bit of a roundabout way of taking a presumably single value and turning it into a
    //  one-element array, but it should work.
    $scratch = array();
    parse_str($customerPersonIds, $scratch);
    $customerPersonIds = isset($scratch['customerPersonIds']) ? $scratch['customerPersonIds'] : 0;
    if ( !is_array($customerPersonIds) ) {
        // Nothing there, empty array
        $customerPersonIds = array();
    }    
}
/*
// BEGIN DEBUG
$customerPersonIdsString = '';
foreach ($customerPersonIds AS $customerPersonId) {
    if ($customerPersonIdsString) {
        $customerPersonIdsString .= ', ';
    } else {
        $customerPersonIdsString .= '[';
    }
    $customerPersonIdsString .= $customerPersonId;
}
if ($customerPersonIdsString) {
    $customerPersonIdsString.= ']';
}
$logger->info2('1592002939', 'Calling $workOrder->setStatus(' . $workOrderStatusId . ', '. $customerPersonIdsString . ', "'. $note .'")');
// END DEBUG
*/

$workOrder->setStatus($workOrderStatusId, $customerPersonIds, $note);

?>