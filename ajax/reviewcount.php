<?php
/*  ajax/reviewcount.php

    No inputs.
    
    Returns JSON for an associative array with the following members:
        * status: "fail" on error; otherwise "success".
        * count: 
            * Through v2020-2 was: count of workOrders that are in "HOLD" state and on which the current user is specified 
              as an EOR (Engineer of Record) via "extra" in the hold status.
            * Beginning v2020-3: count of workOrders on which the current user is specified in wostCustomerPerson as being
              notified. This should typically include HOLDs, but there is no longer a technical limitation against 
              using this for other workOrderStatuses
    
    Radically simplified for v2020-3, so I (JM) haven't preserved old code.
*/

require_once '../inc/config.php';
require_once '../inc/access.php';

$data = array();

$data['status'] = 'fail';

// BEGIN ADDED 2020-07-15 JM for http://bt.dev2.ssseng.com/view.php?id=180 ("Uncaught TypeError")
if (!$customer) {
    $logger->error2('1594832498', 'global $customer is false-y');
} else if (!$customer->getCustomerId()) {
    $logger->error2('1594832555', '$customer->getCustomerId() is false-y');
} else if (!$user) {
    $logger->info2('1594832587', 'global $user is false-y. That means no one is logged in. That\'s OK.');
    $data['status'] = 'success';
    $data['count'] = 0;
} else if (!$user->getUserId()) {
    $logger->error2('1594832601', '$user->getUserId() is false-y');
} else {
// END ADDED 2020-07-15 JM
    $db = DB::getInstance();
    
    // Select all workOrders where the current user is specified in wostCustomerPerson as being notified.
    $query = "SELECT wo.workOrderId ";
    $query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
    $query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatusTime wost on wo.workOrderStatusTimeId = wost.workOrderStatusTimeId ";
    $query .= "JOIN " . DB__NEW_DATABASE . ".wostCustomerPerson wostcp on wo.workOrderStatusTimeId = wostcp.workOrderStatusTimeId ";
    $query .= "JOIN " . DB__NEW_DATABASE . ".customerPerson cp on cp.customerPersonId = wostcp.customerPersonId ";
    $query .= "WHERE cp.customerId = " . intval($customer->getCustomerId()) . " "; // current customer only
    $query .= "AND cp.personId = " . intval($user->getUserId()) . " "; // current user only
    $query .= "ORDER BY wost.inserted DESC;";
    
    $result = $db->query($query);
    if ($result) {
        $data['status'] = 'success';
        $data['count'] = $result->num_rows;
    } else {
        $logger->errorDB('1591908084', "Hard DB error", $db);
    }    
// BEGIN ADDED 2020-07-15 JM for http://bt.dev2.ssseng.com/view.php?id=180 ("Uncaught TypeError")    
}
// END ADDED 2020-07-15 JM

header('Content-Type: application/json');
echo json_encode($data);
?>