<?php
/*  ajax/get_job_workorders.php

    Usage: on workordertasks.php, Workorder Templates tab, get the workOrders for that specific job.

    Possible actions: 
        *On Workorder Templates tab, search by Job Number, display all the workOrders, for that specific job, 
            that have workOrderTasks. The workOrders are displayed as active links, by clicking on an workorder
            the coresponding workOrderTasks tree data will be displayed.

    PRIMARY INPUT $_REQUEST['jobNumber'].

    Returns JSON for an associative array with the following members:
    * 'data': array. Each element is an associative array with elements:
        * 'workOrderId': identifies the workOrder, table workOrder.
        * 'description': description of the workOrder.

        * 'fail': "fail" on query failure ( database error ),
        * 'status': "success" on successful query.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    // Select jobId based on the unique jobNumber.
    $jobNumber = isset($_REQUEST['jobNumber']) ? trim($_REQUEST['jobNumber']) : "";
   
    if (strlen($jobNumber)) {  
    
        $query  = " SELECT jobId ";
        $query .= " FROM " . DB__NEW_DATABASE . ".job ";
        $query .= " WHERE number = '" . $db->real_escape_string($jobNumber) . "' ";

        
        $result = $db->query($query);
        if ($result) {
           if($result->num_rows == 1) {
           
                $row = $result->fetch_assoc();

                $query  = "SELECT wo.workOrderId ";
                $query .= " FROM " . DB__NEW_DATABASE . ".workOrder wo ";
                $query .= " WHERE wo.jobId = " . intval($row["jobId"]) . " ";
                $query .= " ORDER BY wo.workOrderId;";

              
                $result = $db->query($query);
                if($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data2[] = $row;
                    }
                }

              
                foreach ($data2 as $work) {
                  
                    $query  = "SELECT wo.workOrderId, wo.description  ";
                    $query .= " FROM " . DB__NEW_DATABASE . ".workOrder wo ";
                    $query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".workOrderTask wt ON wt.workOrderId = wo.workOrderId ";
                    $query .= " WHERE wo.workOrderId = " . intval($work["workOrderId"]) . " ";
                    $query .= " GROUP BY wo.workOrderId;";

                    $result = $db->query($query);
                    if($result) {
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                    }
                }
                

           }
        } else {

            $error = "We could not found the Job. Database Error";
            $logger->errorDb('6376109496157552129', $error, $db);
            $data['error'] = "ajax/get_job_workorders.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
     

    }

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>





























