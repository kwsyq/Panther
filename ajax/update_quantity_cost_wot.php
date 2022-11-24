<?php
/*  ajax/update_quantity_cost_wot.php

    Usage: on contract page. 
    Updates the qty, cost and total cost for each workorderTask. 
    After wot update, we update the total cost for Level One wot ( the parent ).

    POSSIBLE INPUT VALUES:
        * $_REQUEST['quantity'],  
        * $_REQUEST['cost'], 
        * $_REQUEST['workOrderTaskId'], 
        * $_REQUEST['updateCost'].

    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error with errorId ),
        * 'status': "success" on successful query.
        * $data["totCost"]: total cost of the children wot.
        * $data['sum'] : total cost of Level One wot.
        * $data['workOrderTaskIdOne'] : workOrderTaskId of Level One wot.

*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $data['status'] = 'fail';
    $data['error'] = '';
    $data['errorId'] = '';

    $data["totCost"] = 0; // total cost of the children wot.
    $data['sum'] = 0; // total cost of Level One wot.
    $data['workOrderTaskIdOne'] = 0; // workOrderTaskId of Level One wot.


    // update quantity or cost for this workOrderTaskId
    $quantity = isset($_REQUEST['quantity']) ? floatval($_REQUEST['quantity']) : 0;
    $cost = isset($_REQUEST['cost']) ? floatval($_REQUEST['cost']) : 0;
    $workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
    $workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
    $updateCost  = isset($_REQUEST['updateCost']) ? $_REQUEST['updateCost'] : false; // for cost

    // if 1 is LEVEl ONE, if 2 is LEVEL TWO
    $levelTwoTask = isset($_REQUEST['levelTwoTask']) ? intval($_REQUEST['levelTwoTask']) : 0;
    $sum = 0;
    $tmp = 0; 


    if(!$workOrderTaskId) { // if not, die()
        $error = "Invalid workOrderTaskId from Request.";
        $data['errorId'] = '637795063060344323';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
    if(!$workOrderId) { // if not, die()
        $error = "Invalid workOrderId from Request.";
        $data['errorId'] = '637795765398723393';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    $tmp = $workOrderTaskId;

    if($updateCost == false) {

        // update quantity
        $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
        $query .= " quantity = " . floatval($quantity) . " ";
        $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not Update the quantity for this WOT. Database Error";
            $data['errorId'] = '637741363067035970';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
    } else {
        // update cost
        $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
        $query .= " cost = " . floatval($cost) . " ";
        $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not Update the cost for this WOT. Database Error";
            $data['errorId'] = '637741363815807584';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
    }

    // create value for totCost
    $query = "SELECT quantity,cost FROM " . DB__NEW_DATABASE . ".workOrderTask ";
    $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not get the quantity, cost. Database Error";
        $data['errorId'] = '637741377810031340';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 

    $row = $result->fetch_assoc();
    $data["totCost"] = $row["quantity"] * $row["cost"];

    // update field totCost
    $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
    $query .= " totCost = " . floatval($data["totCost"]) . " ";
    $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not Update the total cost for this WOT. Database Error";
        $data['errorId'] = '637741379140297399';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
   
    if($levelTwoTask == 2) { // the task is level TWO

        // WOT LEVEL 1 total cost as parent for this wot.
        $query = " SELECT workOrderTaskId, parentTaskId FROM workOrderTask WHERE  workOrderId = " . intval($workOrderId);
      
        $result=$db->query($query);

        if (!$result) {
            $error = "We could not retrive the data from from workOrderTask. Database Error";
            $data['errorId'] = '637795064531686050';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }

        $arr = [];

        while($row = $result->fetch_assoc()) {
            $arr[$row['workOrderTaskId']] = $row['parentTaskId'];
        }
    
        do {
            $tmp = $arr[$tmp]; // the Id of LEVEL 1

        } while (isset($arr[$arr[$tmp]]) != null);

    
        // all children of level 1
        $query = "select workOrderTaskId,
        parentTaskId, totCost
        from    (select * from workOrderTask
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$tmp') initialisation
        where   find_in_set(parentTaskId, @pv)
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not retrive the data from workOrderTask. Database Error";
            $data['errorId'] = '637795066259583428';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
        
        while( $row=$result->fetch_assoc() ) { 
            $sum +=floatval($row['totCost']); // Level 1 Sum
        }

        // Update the parent total cost
        $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
        $query .= " totCost = " . floatval($sum) . " ";
        $query .= " WHERE workOrderTaskId = " . intval($tmp);

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not update the total cost. Database Error";
            $data['errorId'] = '637795066173486054';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_quantity_cost_wot.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }


        $data['sum'] = floatval($sum); // total cost of level one
        $data['workOrderTaskIdOne'] = intval($tmp);  // workOrderTaskId of level one
    } 


    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>