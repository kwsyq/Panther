<?php
/*  ajax/check_wot_level_one_cost.php

    Usage: on workorderTask page. 

    On drag and drop from Tasks tab to the WO Elements tab checks for totCost of an workorderTask 
    of type Level 1. Direct child of the Element.
    A popup will be displayed: 
        " Please be aware this task has a value that will be lost when you add children. "
        " Dont forget to include this into the value of the children . "
    On user confirmation, we set the qty, cost and totCost to 0.

    POSSIBLE INPUT VALUES:
        * $_REQUEST['workOrderTaskId'], 
        * $_REQUEST['checkTotCost'],
        * $_REQUEST['updateTotCost'],
    
    
    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error ),
        * 'status': "success" on successful query.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';
    $data['errorId'] = '';
    $data['totCostVal'] = 0;


    // check for total cost / update qty, cost and totCost for this workOrderTaskId.
    $workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
    $checkTotCost  = isset($_REQUEST['checkTotCost']) ? $_REQUEST['checkTotCost'] : false;
    $updateTotCost  = isset($_REQUEST['updateTotCost']) ? $_REQUEST['updateTotCost'] : false;
    // Reset this values: 
    $cost = 0;
    $quantity = 0;
    $totCost = 0;
    $totCostVal = 0;

    if(!$workOrderTaskId) { // if not, die()
        $error = "Invalid workOrderTaskId from Request.";
        $data['errorId'] = '637793493737261071';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/check_wot_level_one_cost.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } else {

        if($checkTotCost == true) {

            // check the value of totCost
            $query = "SELECT totCost FROM " . DB__NEW_DATABASE . ".workOrderTask ";
            $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);


            $result = $db->query($query);

            if (!$result) {
                $error = "We could not get the total cost for this WOT. Database Error";
                $data['errorId'] = '637788931028041995';
                $logger->errorDb($data['errorId'], $error, $db);
                $data['error'] = "ajax/check_wot_level_one_cost: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die();
            } else {
                $row = $result->fetch_assoc();
                $totCostVal = $row['totCost'];

                if(floatval($totCostVal) > 0 ) {
                    // value used in the appellant page.
                    $data['totCostVal'] = floatval($totCostVal); 
                } // else value is 0;
            }
        } 
        
        if($updateTotCost) {
            // Update cost, quantity and totCost.
            $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
            $query .= " cost = " . floatval($cost) . ", ";
            $query .= " quantity = " . floatval($quantity) . ", ";
            $query .= " totCost = " . floatval($totCost) . " ";
            $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);
    
            $result = $db->query($query);
    
            if (!$result) {
                $error = "We could not Update the total cost for this WOT. Database Error";
                $data['errorId'] = '637788926540600855';
                $logger->errorDb( $data['errorId'], $error, $db);
                $data['error'] = "ajax/check_wot_level_one_cost: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die();
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
