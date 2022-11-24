<?php
/*  ajax/updateworkordertaskpersons.php

    INPUT $_REQUEST['workOrderTaskId']: primary key to DB table WorkOrderTask
    INPUT $_REQUEST['personIds']: array of primary keys to DB table Person 

    Adds the specified personIds to the indicated workOrderTask (by making entries as needed in table workOrderTaskPerson). No explicit return.

    Unlike other places (e.g. ajax/addworkordertask.php) where we use comma-separated lists and 
    PHP explode to handle an array of values for an input, personIds uses the technique with 
    square brackets in the name of the HTML SELECT element to pass an array as such.
    
    No explicit return.
*/

include '../inc/config.php';
include '../inc/access.php';

sleep(1); // Pause so use sees AJAX icon & knows something is happening

$db = DB::getInstance();

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? $_REQUEST['workOrderTaskId'] : 0;
$workOrderTask = new WorkOrderTask($workOrderTaskId);

$personIds = isset($_REQUEST['personIds']) ? $_REQUEST['personIds'] : array();

if (!is_array($personIds)){
    $personIds = array();
}

$workOrderTask->addPersonIds($personIds);

?>