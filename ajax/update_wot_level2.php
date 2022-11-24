<?php
/*  ajax/update_wot_level2.php
    
    Usage: in workordertasks.php, workorder.php, contract.php. On the existing workorder structure of workOrderTasks, we can add
        extra description or billing description.


    INPUT $_REQUEST['nodeTaskId']: alias workOrderTaskId, primary key in DB table workOrderTask.
    INPUT $_REQUEST['extraDescription'] : the extra description of a workOrderTask.
    INPUT $_REQUEST['billingDescription']: the billing description of a workOrderTask.
    INPUT $_REQUEST['billDesc']: boolean, if true we update the billing description.
    INPUT $_REQUEST['taskTypeId']: type of the task.
    INPUT $_REQUEST['taskContractStatus']: task contract status, 1 or 9.

    Returns JSON for an associative array with the following members:
        * 'fail': "fail" on query failure ( database error with errorId ),
        * 'status': "success" on successful query.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';
    $data['errorId'] = '';

    $workOrderId = 0; 
    $tmp = 0;

    // Added from admin billingDescription/ extraDescription.
    $workOrderTaskId = isset($_REQUEST['nodeTaskId']) ? intval($_REQUEST['nodeTaskId']) : 0; // workorderTaskId
    $extraDescription = isset($_REQUEST['extraDescription']) ? trim($_REQUEST['extraDescription']) : "";
    $billingDescription = isset($_REQUEST['billingDescription']) ? trim($_REQUEST['billingDescription']) : "";
    $billDesc  = isset($_REQUEST['billDesc']) ? $_REQUEST['billDesc'] : false;
    
    // taskType and status of a task on the contract 1 or 9.
    $taskTypeId = isset($_REQUEST['taskTypeId']) ? intval($_REQUEST['taskTypeId']) : 0;
    $taskContractStatus = isset($_REQUEST['taskContractStatus']) ? intval($_REQUEST['taskContractStatus']) : 0;
    

    if(!$workOrderTaskId) { // if not, die()
        $error = "Invalid workOrderTaskId from Request.";
        $data['errorId'] = '637793975183794879';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/update_wot_level2.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    $tmp = $workOrderTaskId;

    // default 1 or 9 for the level 2 wot
    if($taskContractStatus == 9) {

        $wot = new WorkOrderTask($workOrderTaskId);
        $workOrderId = $wot->getWorkOrderId();

        // WOT LEVEL 1 as parent for this wot.
        $query = " SELECT workOrderTaskId, parentTaskId FROM workOrderTask WHERE  workOrderId = " . intval($workOrderId);
        
        $result=$db->query($query);

        if (!$result) {
            $error = "We could not retrive the data from from workOrderTask. Database Error";
            $data['errorId'] = '637800893770824308';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_wot_level2.php: $error";
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
       
        $allChildren = [];
        // all children of level 1
        $query = "select workOrderTaskId,
        parentTaskId
        from    (select * from workOrderTask
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$tmp') initialisation
        where   find_in_set(parentTaskId, @pv)
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";
      
        $result = $db->query($query);
    
        if (!$result) {
            $error = "We could not retrive the data from workOrderTask. Database Error";
            $data['errorId'] = '637800804531964367';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_wot_level2.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
    
        while( $row=$result->fetch_assoc() ) { 
            $allChildren[] = $row["workOrderTaskId"];
        }

            
        foreach( $allChildren as $val ) { 
            // Check the workOrderTaskId status for each children.
            $query = " SELECT workOrderTaskId, taskContractStatus FROM " . DB__NEW_DATABASE . ".workOrderTask ";
            $query .= " WHERE workOrderTaskId = " . intval($val);
    
            $result = $db->query($query);

            if (!$result) {
                $error = "We could not get the data from workOrderTask. Database Error";
                $data['errorId'] = '637800898528784621';
                $logger->errorDb($data['errorId'], $error, $db);
                $data['error'] = "ajax/update_wot_level2.php: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die();
            }
            while( $row=$result->fetch_assoc() ) { 
                if($row['taskContractStatus'] == 1) {
                    // this level 1 workOrderTaskId has at least one children with status 1. make level 2 with status 1
                    $taskContractStatus = 1;
                }
            }
        }

    }
  
    // make the update
    $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
    if ($billDesc == true) {
    $query .= " billingDescription = '" . $db->real_escape_string($billingDescription) . "', ";
    } else {
        $query .= " extraDescription = '" . $db->real_escape_string($extraDescription) . "', ";
    }
    $query .= " taskTypeId = " . intval($taskTypeId) . ", ";
    $query .= " taskContractStatus = " . intval($taskContractStatus) . " ";
    $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId);

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not Update this workOrderTask. Database Error";
        $data['errorId'] = '607793975061183893';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/update_wot_level2.php: $error";
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