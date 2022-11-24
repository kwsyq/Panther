<?php
/*  ajax/makeinvoicefromcontract.php

    INPUT $_REQUEST['contractId']: primary key in DB table Contract

    If an invoice does not already exist (in the DB) for the contract, generate one.

    Returns JSON for an associative array with only the following member:
        * status: "fail" if invoice already exists, contractId not valid, or on other errors; otherwise "success".
*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-08-17, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add


$data = array();
$data['status'] = 'fail';
$data['error'] = '';
$db = DB::getInstance();

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'contractId');
$v->rule('integer', 'contractId');
$v->rule('min', 'contractId', 1);


if(!$v->validate()) {   // if any error in validation (fields => rules + manually added errors) the validator generates
                        // the return structure with 'fail' status, returns the JSON to caller and exits

    $logger->error2('637432991518894069', "Error input parameters ".json_encode($v->errors()));
    header('Content-Type: application/json');
    echo $v->getErrorJson();
    die();
}

$contractId =  intval($_REQUEST['contractId']);

if (existContract($contractId)) {
    $exists = false;
    $query = "SELECT invoiceId FROM " . DB__NEW_DATABASE . ".invoice "; // before v2020-3, was just SELECT *
    $query .= "WHERE contractId = " . intval($contractId) . ";";
    $result = $db->query($query);
    if ($result) {
        $exists = $result->num_rows > 0;
    } else  {
        $logger->errorDb('1594157608', "Hard DB error", $db);
        $data['error'] = "Database error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    if (!$exists) {
        // No existing invoice
        $query = "SELECT workOrderId, contractId "; // before v2020-3, was just SELECT *
        $query .= "FROM " . DB__NEW_DATABASE . ".contract ";
        $query .= "WHERE contractId = " . intval($contractId). ";";
        $result = $db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $workOrder = new WorkOrder($row['workOrderId']);
                $invoice=$workOrder->newInvoice($row['contractId']); // This is where we actually create the invoice
                if(!$invoice) {
                    $logger->error2('637437180516604291', "We could not make an Invoice.");
                    $data['error'] = "We could not Make an Invoice. Please contact an administrator.";
                    header('Content-Type: application/json');
                    echo json_encode($data);
                    die();
                }
            }
        } else  {
            $logger->errorDb('1594157697', "Hard DB error", $db);
            $data['error'] = "Database error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
    }
} else {

    $logger->error2('637432992419304053', "Not a valid contractId. Input given :  $contractId ");
    $data['error'] = "We could not Make an Invoice. Please contact an administrator.";
    header('Content-Type: application/json');
    echo json_encode($data);
    die();

}

if (!$data['error']) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
