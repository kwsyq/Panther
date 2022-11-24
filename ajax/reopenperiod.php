<?php
/* ajax/reopenperiod.php

    EXECUTIVE SUMMARY: allows employee to reopen entering hours for a pay period that was already signed off with ajax/signoffperiodtime.php.
    
    INPUT $_REQUEST['customerPersonId']: primary key to DB table customerPerson
    INPUT $_REQUEST['periodBegin']: first day of pay period in 'Y-m-d' form, e.g. '2020-09-01'
    
    Returns JSON for an associative array with only the one member:
         * 'status': "success" on successful query, 'fail' on any failure.    
*/

include '../inc/config.php';

$data = array();
$data['status'] = 'fail';

$v=new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();
if ($error) {
    $logger->error2('1602103456', "Error(s) found in init validation: [".json_encode($v->errors())."]");
}

if (!$error) {
    $v->stopOnFirstFail(); 
    $v->rule('required', ['customerPersonId', 'periodBegin']);
    $v->rule('integer', 'customerPersonId');
    $v->rule('min', 'customerPersonId', 1);
    $v->rule('dateFormat', 'periodBegin', 'Y-m-d');
    if( !$v->validate() ) {
        $logger->error2('1602103533', "Errors found: ".json_encode($v->errors()));
        $error = true;
    }
}

if (!$error) {
    $customerPersonId = intval($_REQUEST['customerPersonId']);
    $periodBegin = $_REQUEST['periodBegin'];
    
    $parts = explode ('-', $periodBegin);
    if ( $parts[2] != '1' && $parts[2] != '16' ) {
        $logger->error2('1602103984', "$periodBegin is not first day of a pay period, day of month must be 1 or 16");
        $error = true;
    }
}

if (!$error) {
    $db = DB::getInstance();
    
    $customerPerson = CustomerPerson::getFromPersonId($user->getUserId());
    if (intval($customerPersonId) != intval($customerPerson->getCustomerPersonId())) {
        $logger->error2('1602104233', "Passed-in customerPersonId $customerPersonId does not match the logged in user $customerPersonId2");
        $error = true;
    }
}

if (!$error) {
    $query = "SELECT initialSignoffTime FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
    $query .= "WHERE customerPersonId = $customerPersonId ";
    $query .= "AND periodBegin = '$periodBegin';"; 
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDB('1602874129', 'Hard DB error', $db);
        $error = true;
    }
}

if (!$error) {
    $row = $result->fetch_assoc();
    if (!$row) {
        $logger->error2('1602874291', "No row returned from [$query]");
        $error = true;
    }
}
if (!$error) {
    $initialSignoffTime = $row['initialSignoffTime']; // determine whether they've already signed off
    if ($initialSignoffTime) {    
        $query = "UPDATE " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
        $query .= "SET reopenTime = now() ";
        $query .= "WHERE customerPersonId = $customerPersonId ";
        $query .= "AND periodBegin = '$periodBegin';"; 
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDB('1602104321', 'Hard DB error updating customerPersonPayPeriodInfo.initialSignoffTime', $db);
            $error = true;
        } else if ($db->affected_rows != 1) {
            $logger->errorDb('1602104399', 'Expect 1 affected row, got '. $db->affected_rows, $db);
            $error = true;
        } else {
            $logger->info2('1602104467', "query succeeded: $query");
        }
    } // else they never signed off, so there is nothing to "reopen"
}
if (!$error) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>