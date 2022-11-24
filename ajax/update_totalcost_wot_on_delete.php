<?php
/*  ajax/update_totalcost_wot_on_delete.php

    Usage:  on workordertask page. 
            Action: delete a workOrderTask.
    Updates total cost for this workOrderTaskId parent LEVEL 1 task, when this workOrderTask is deleted.

    POSSIBLE INPUT VALUES:
        * $_REQUEST['workOrderTaskId'],
        * $_REQUEST['workOrderId'],
        * $_REQUEST['delete'], // boolean
    
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


    // update total cost for this workOrderTaskId parent LEVEL 1 task.
    $workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
    $workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
    $delete = isset($_REQUEST['delete']) ? ($_REQUEST['delete']) : false;
    $change = isset($_REQUEST['change']) ? ($_REQUEST['change']) : false;

    $tmp = 0; // Level 1 WOT parent
    $totCostWot = 0;
    $cost = 0;
    $quantity = 0;

    if(!$workOrderTaskId) {  // eror if not
        $error = "Invalid workOrderTaskId from Request.";
        $data['errorId'] = '637794167034244006';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/update_totalcost_wot_on_delete.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 

    // Update Level 1 WOT parent total cost if we delete this workOrderTask.
    if($delete) { //boolean

        $query = " SELECT workOrderTaskId, parentTaskId FROM workOrderTask WHERE workOrderId = " . intval($workOrderId);
    
        $result=$db->query($query);

        if (!$result) {
            $error = "We could not select workOrderTaskId for this workOrderId. Database Error";
            $data['errorId'] = '637793269933515961';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_totalcost_wot_on_delete.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die(); 
        } 
        // get the workOrderTaskId of Level one task.
        $arr = [];
        while($row = $result->fetch_assoc()) {
            $arr[$row['workOrderTaskId']] = $row['parentTaskId'];
        }

        $tmp = $workOrderTaskId;

        do {
            $tmp = $arr[$tmp];

        } while (isset($arr[$arr[$tmp]]) != null);
    
        // tmp is the Level 1 WOT workOrderTaskId

        $query = " SELECT totCost FROM workOrderTask WHERE  workOrderTaskId = " . intval($workOrderTaskId);
        $result=$db->query($query);

        if (!$result) {
            $error = "We could not get the total cost for this workOrderTaskId. Database Error";
            $data['errorId'] = '637794168539769520';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_totalcost_wot_on_delete.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        } 

        $row = $result->fetch_assoc();
        $totCostWot = $row["totCost"];
        
        // we have an entry.
        if ($result->num_rows > 0) {
            // On Delete task, substract totCost wot from totCost Level 1 WOT parent
            $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
            $query .= " cost = " . $cost . ", ";
            $query .= " quantity = " . $quantity . ", ";
            $query .= " totCost = totCost -" . floatval($totCostWot) . " "; // substract totCost
            $query .= " WHERE workOrderTaskId = " . intval($tmp);
    
            $result=$db->query($query);

            if (!$result) {
                $error = "We could not Update the total cost for this level 1 workOrderTaskId. Database Error";
                $data['errorId'] = '637793280926910558';
                $logger->errorDb($data['errorId'], $error, $db);
                $data['error'] = "ajax/update_totalcost_wot_on_delete.php: $error";
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
