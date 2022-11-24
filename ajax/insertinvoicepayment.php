<?php 
/*  ajax/insertinvoicepayment.php

    INPUT $_REQUEST['creditRecordId']: primary key in DB table creditRecord
    INPUT $_REQUEST['invoiceId']: primary key in DB table invoice
    INPUT $_REQUEST['type']: one of 'payFull', 'payBal', 'payPart', 'reversePay' 
    INPUT $_REQUEST['amount']: amount to be paid, U.S. currency, 2 digits past the decimal point. 
        Positive if INPUT $_REQUEST['type'] == 'payPart', negative if INPUT $_REQUEST['type'] == 'reversePay', ignored otherwise.

    This is a wrapper to call Invoice::pay().
    
    RETURN: JSON-encoded associative array with the following indexes:
    * 'status': 'success' or 'fail'
    * 'error': text, used only on status = 'fail'
    
*/
include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);

$v->rule('required', ['creditRecordId', 'invoiceId', 'type']);
$v->rule('integer', ['creditRecordId', 'invoiceId']);
$v->rule('min', ['creditRecordId', 'invoiceId'], 1);
$v->rule('in', 'type', ['payFull', 'payBal', 'payPart', 'reversePay']);
$v->rule('optional', 'amount');
$v->rule('numeric', 'amount');

if(!$v->validate()){
    $logger->error2('1580928029', "Error input parameters ".json_encode($v->errors()));
    header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$creditRecordId = $_REQUEST['creditRecordId'];
$invoiceId = $_REQUEST['invoiceId'];
$type = $_REQUEST['type'];
$amount = floatval(array_key_exists('amount', $_REQUEST) ? $_REQUEST['amount'] : 0);
if ($type == 'payPart' && ! ($amount > 0) ) {
    $data['error'] = "'payPart' requires positive value for part, got $amount"; 
    $logger->error2('1580928339', $data['error']);
} else if ($type == 'reversePay' && ! ($amount < 0) ) {
    $data['error'] = "'reversePay' requires negative value for part, got $amount"; 
    $logger->error2('1580928349', $data['error']);
}

if ( !$data['error'] && ! CreditRecord::validate($creditRecordId) ) {
    $data['error'] = "$creditRecordId is not a valid creditRecordId";
}
if ( !$data['error'] && ! Invoice::validate($invoiceId) ) {
    $data['error'] = "$invoiceId is not a valid invoiceId";
}

if (!$data['error']) {
    $invoice = new Invoice($invoiceId);
    $payStatus = $invoice->pay($type, $creditRecordId, $amount);
    if ($payStatus == 'OK') {
        $data['status'] = 'success';
    } else {
        $data['error'] = $payStatus;
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>