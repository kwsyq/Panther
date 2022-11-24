<?php 
/* _admin/ajax/setterminationdate.php

    INPUT $_REQUEST['customerPersonId']: primary key to DB table CustomerPerson
    INPUT $_REQUEST['date']: new termination date
    
    Verifies that the customerPerson exists and that the date parses

    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'
*/

include '../../inc/config.php';
include '../../inc/access.php';

// >>>00037 can we put this somewhere it can readily be reused? JM
function validateDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}    

$data = array();
$data['status'] = 'fail';

$customerPersonId = isset($_REQUEST['customerPersonId']) ? intval($_REQUEST['customerPersonId']) : 0;
$date = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';

if (validateDate($date, 'Y-m-d') && $customerPersonId) {
    $exists = false;    
    $db = DB::getInstance();
    
    $query = " select customerPersonId ";
    $query .= " from  " . DB__NEW_DATABASE . ".customerPerson  ";
    $query .= " where customerPersonId = " . intval($customerPersonId) . " ";
    $query .= " and customerId = " . $customer->getCustomerId();
        
    $result = $db->query($query);
    if ($result) {
        $exists = $result->num_rows > 0;
    } // >>>00002 else need to log failure on DB query
    
    if ($exists) {
        $query = "update customerPerson set terminationdate = '$date' where customerPersonId = " . intval($customerPersonId);  
        $result = $db->query($query);
        if ($result) {
            $data['status'] = 'success';
        } // >>>00002 else need to log failure on DB query
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>