<?php
/*  ajax/update_ctr_status_child.php

    Usage: on contract page.
    Updates the contractStatus for all children of this workOrderTaskId.

    POSSIBLE INPUT VALUES:
        * $_REQUEST['workOrderTaskId'],
        * $_REQUEST['workOrderId'],

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
    $taskContractStatus = isset($_REQUEST['taskContractStatus']) ? intval($_REQUEST['taskContractStatus']) : 0;


    if(!$workOrderId) { // if not, die()
        $error = "Invalid workOrderId from Request.";
        $data['errorId'] = '637799136188800594';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/update_ctr_status_child.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

   if(!$workOrderTaskId) { // if not, die()
        $error = "Invalid workOrderTaskId from Request.";
        $data['errorId'] = '637799136084050045';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/update_ctr_status_child.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    // Update contractStatus childs of Level 1 WOT if we check the checkbox.


    if(isset($_REQUEST['invoiceId']))
    {
        $invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;
        $invoice=new Invoice($invoiceId);
        $data2=$invoice->getData();

        $out=[];

        foreach($data2[4] as $task){
            if($task['id']==$workOrderTaskId){
                $task['taskContractStatus']=$taskContractStatus;
            }
            $out[]=$task;
        }

        $allChildren = [];

        $query = "select workOrderTaskId,
        parentTaskId
        from    (select * from workOrderTask
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$workOrderTaskId') initialisation
        where   find_in_set(parentTaskId, @pv)
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";


        $result = $db->query($query);
        if (!$result) {
            $error = "We could not retrive the data from workOrderTask. Database Error";
            $data['errorId'] = '637799154907579683';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_ctr_status_child.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }

        while( $row=$result->fetch_assoc() ) {
            $key=array_search($row['workOrderTaskId'], array_column($out, 'id'));
            if($key!==false)
            {
                $out[$key]['taskContractStatus']=$taskContractStatus;
            }

        }


        $dataContract = [ "4" => $out];
        $dataContractJson = json_encode($dataContract);
//print_r($dataContract);
        $invoice->update(array(
            // send contract data for signed or voided.
            'data' => $dataContractJson,
        ));
    } else {
        $allChildren = [];
        // all children of level 1
        $query = "select workOrderTaskId,
        parentTaskId
        from    (select * from workOrderTask
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$workOrderTaskId') initialisation
        where   find_in_set(parentTaskId, @pv)
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not retrive the data from workOrderTask. Database Error";
            $data['errorId'] = '637799154907579683';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/update_ctr_status_child.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }

        while( $row=$result->fetch_assoc() ) {
            $allChildren[] = $row["workOrderTaskId"];
        }

        foreach( $allChildren as $val ) {
            // Update the workOrderTaskId status for each children.
            $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
            $query .= " taskContractStatus = " . $taskContractStatus . " ";
            $query .= " WHERE workOrderTaskId = " . intval($val);

            $result = $db->query($query);

            if (!$result) {
                $error = "We could not update the taskContractStatus. Database Error";
                $data['errorId'] = '637799158365122944';
                $logger->errorDb($data['errorId'], $error, $db);
                $data['error'] = "ajax/update_ctr_status_child.php: $error";
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
