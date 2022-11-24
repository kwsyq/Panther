<?php
/*  ajax/workorderinvoicescontracts.php

    INPUT $_REQUEST['workOrderId']: primary key to WorkOrder table.
    
    Get contracts and invoices for a given workOrder.
    
    Returns JSON for an associative array with the following members:    
        * 'status': always "success".
        * 'invoices': array of associative arrays equivalent to the result of a select from table invoice for invoices for this workOrder.
            'data' column is removed from output. 
            (See documentation of table invoice for details.)
        * 'contracts': array of associative arrays equivalent to the result of a select from table contract for contracts for this workOrder.
            'data' column is removed from output.
            (See documentation of table contract for details.) 
            
    >>>00007 As of 2020-09-25, it looks like the only call to this is from a function in workorder.php that is, itself never called! So this may
    be completely vestigial code. - JM
*/

sleep(1); // so user can see AJAX icon & know something is happenning

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

$db = DB::getInstance();
$data = array();
$data['status'] = 'success';
$data['invoices'] = array();
$data['contracts'] = array();

$query = " select * from " . DB__NEW_DATABASE . ".invoice  ";
$query .= " where workOrderId = " . intval($workOrderId) . " ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {            
        while ($row = $result->fetch_assoc()) {
            unset($row['data']);            
            $data['invoices'][] = $row;
        }   
    }
} // >>>00002 ignores failure on DB query!

$query = " select * from " . DB__NEW_DATABASE . ".contract  ";
$query .= " where workOrderId = " . intval($workOrderId) . " ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {            
        while ($row = $result->fetch_assoc()) {
            unset($row['data']);                
            $data['contracts'][] = $row;                
        }            
    }
} // >>>00002 ignores failure on DB query!

header('Content-Type: application/json');
echo json_encode($data);
die();

?>