<?php 
/*  _admin/ajax/accepttimesheet.php

    EXECUTIVE SUMMARY: accepts (or "unaccepts"!) timesheet for a particular employee for a particular pay period.
    If we are accepting their timesheet, we also kill all related rows in DB tables workOrderTaskTimeLateModifications and
    ptoLateModifications.
    
    INPUT $_REQUEST['customerPersonId']: Primary key in DB table customerPerson
    INPUT $_REQUEST['periodBegin']: First day of period in Y-m-d form. Should always be the 1st or 16th of the month
    INPUT $_REQUEST['delete']: optional, default 'false'. If this is 'true', nulls out adminSignedPayrollTime instead of setting it.
    
    If DB table customerPersonPayPeriodInfo has the specified row & column, it should succeed in performing the update.
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' if the update succeeds, otherwise 'fail'.
                    Also additional status 'reopened' if the employee has reopened their timesheet. Never use 'reopened' if 
                    input 'delete' is 'true': in that case, it's harmless if the employee has reopened their timesheet.
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';

$v = new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();
if ($error){
    $logger->error2('1602172783', "Error(s) found in init validation: [".json_encode($v->errors())."]");
}
if (!$error) {
    $v->stopOnFirstFail();
    $v->rule('required', ['customerPersonId', 'periodBegin']);
    $v->rule('integer', 'customerPersonId');
    $v->rule('min', 'customerPersonId', 1);
    $v->rule('regex', 'periodBegin', '/^20[0-9][0-9]-(0[1-9]|1[0-2])-(01|16)$/'); // validate date is in the 2000s & is 1st or 16th of month
    $v->rule('in', 'delete', ['true', 'false']);
    if( !$v->validate() ) {
        $logger->error2('1602172892', "Invalid input. Errors found: ".json_encode($v->errors()));
        $error = true;
    }
}
if (!$error) {
    $customerPersonId = $_REQUEST['customerPersonId'];
    $periodBegin = $_REQUEST['periodBegin'];
    
    // calculate start of next period
    $ymdArray = explode('-', $periodBegin); // [0]=>year, [1]=>month, [2]=>day
    if ($ymdArray[2] < 16) {
        $ymdArray[2] = 16;
    } else { 
        if ($ymdArray[1] == 12) {
            // last payperiod of the year
            $ymdArray[0] = $ymdArray[0] + 1;
            $ymdArray[1] = '01'; // string so we force the leading zero 
        } else {
            $ymdArray[1] = sprintf('%02d', ($ymdArray[1] + 1)); // force the leading zero if single digit
        }
        $ymdArray[2] = '01';
    }
    $nextPeriodBegin = implode('-', $ymdArray);
    
    $delete = isset($_REQUEST['delete']) && $_REQUEST['delete'] == 'true';
    if ( ! CustomerPerson::validate($customerPersonId, '1602173322') ) { 
        $error = true;
    }
}
if (!$error) {
    $db = DB::getInstance();
}
if (!$error && !$delete) {
    $query  = "SELECT reopenTime FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
    $query .= "WHERE customerPersonId=$customerPersonId ";
    $query .= "AND periodBegin='$periodBegin';";
    
    $result = $db->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row) {
            if ($row['reopenTime'] !== null) {
                $data['status'] = 'reopened';
                
                // And get out of here
                header('Content-Type: application/json');
                echo json_encode($data);
                die();
            }
        } else {
            $logger->error2('1603300497', "No row found for $query");
            $error = true;
        }
    } else {
        $logger->errorDb('1603300378', "Hard DB failure", $db);
        $error = true;
    }
}
if (!$error) {
    $query  = "UPDATE " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo SET ";
    $query .= "adminSignedPayrollTime=";
    if ($delete) {
        $query .= "NULL ";
    } else {
        $query .= "now() ";
    }
    $query .= "WHERE customerPersonId=$customerPersonId ";
    $query .= "AND periodBegin='$periodBegin';";
    
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1602174027', "Hard DB failure", $db);
        $error = true;
    }
}
if (!$error) {
    if (!$delete) {
        // Admin has signed these off the changes, so no further need to keep track of them.
        // Yes, it is a little confusing that NOT deleting adminSignedPayrollTime means we DO delete the rows in
        //  WorkOrderTaskTimeLateModifications and PtoLateModifications 
        // >>>00001 do we want to do any validation in this next 5 lines?
        $customerPerson = new CustomerPerson($customerPersonId); // should always succeed, we already validated $customerPersonId
        $personId = $customerPerson->getPersonId();
        $tempUser = new User($personId, $customer);
        $time = new Time($tempUser, $periodBegin, 'payperiod');
        $time->deleteWorkOrderTaskTimeLateModifications();
        $time->deletePtoLateModifications();
        
        // Similarly, no further need to track any workOrderTaskTime that has gone to zero
        // >>>00006 Maybe add a method to Time class for this; that would let us get rid
        // of calculating $nextPeriodBegin above
        $query  = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
        $query .= "WHERE personId = " . $customerPerson->getPersonId() . " ";
        $query .= "AND minutes <= 0 ";
        $query .= "AND day >= '$periodBegin' ";
        $query .= "AND day < '$nextPeriodBegin';";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1602527989', "Hard DB failure", $db);
            $error = true;
        }
        if (!$error) {
            // Similarly, no further need to track any pto that has gone to zero
            // >>>00006 Maybe add a method to Time class for this; that would let us get rid
            // of calculating $nextPeriodBegin above
            $query  = "DELETE FROM " . DB__NEW_DATABASE . ".pto ";
            $query .= "WHERE personId = " . $customerPerson->getPersonId() . " ";
            $query .= "AND minutes <= 0 ";
            $query .= "AND day >= '$periodBegin' ";
            $query .= "AND day < '$nextPeriodBegin';";
            
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1602528010', "Hard DB failure", $db);
                $error = true;
            }
        }
    }
}
if (!$error) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>