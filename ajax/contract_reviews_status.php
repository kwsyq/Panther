<?php
/*  ajax/contract_reviews_status.php

    INPUT $_REQUEST['notificationId']: primary key to DB table contractNotification
    INPUT $_REQUEST['reviewStatus']: status of the Notification

    EXECUTIVE SUMMARY:  
        * Default notification status (reviewStatus) default is 0. If a review of a contract status is done 
            we change the reviewStatus to 1. 

        Returns JSON for an associative array with the following members:
         * 'status': "success" on successful query ( update of reviewStatus / on query success ),

*/

include '../inc/config.php';
include '../inc/access.php';


$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$notificationId = isset($_REQUEST['notificationId']) ? intval($_REQUEST['notificationId']) : 0;
$reviewStatus = isset($_REQUEST['reviewStatus']) ? intval($_REQUEST['reviewStatus']) : 0;


if (intval($notificationId)) {   


    $query = " UPDATE " . DB__NEW_DATABASE . ".contractNotification SET  ";
    $query .= " reviewStatus = " . intval($reviewStatus) . " ";
    $query .= " WHERE notificationId = " . intval($notificationId);


    $result = $db->query($query);

    if (!$result) {
        $error = "We could not Update the Notification Status. Database Error";
        $logger->errorDb('637768966285786651', $error, $db);
        $data['error'] = "ajax/contract_reviews_status.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {
        $query = "SELECT reviewStatus FROM " . DB__NEW_DATABASE . ".contractNotification ";
        $query .= " WHERE notificationId = " . intval($notificationId);
 
        $result = $db->query($query);
    
        if (!$result) {
            $error = "We could not get the Review Status. Database Error";
            $logger->errorDb('637768971195135196', $error, $db);
            $data['error'] = "ajax/contract_reviews_status.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        } else {

      
            $row = $result->fetch_assoc();

            $data['reviewStatus'] = $row['reviewStatus'];
            
        }
    }

}



if (!$data['error']) {
    $data['status'] = 'success';
}


header('Content-Type: application/json');
echo json_encode($data);
die();

?>