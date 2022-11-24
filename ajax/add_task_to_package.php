<?php
/*  ajax/add_task_to_package.php
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    // Brand new taskPackage.
    $taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;
    $taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;	
    $parentTaskId = isset($_REQUEST['parentTaskId']) ? intval($_REQUEST['parentTaskId']) : 0;	

    if (strlen($taskId)) {  
    
        $query = " INSERT INTO " . DB__NEW_DATABASE . ".taskPackageTask ";
        $query .= "(taskPackageId, taskId, parentTaskId) VALUES (";
        $query .= intval($taskPackageId);
        $query .= ", " . intval($taskId);
        $query .= ", " . intval($parentTaskId);
        $query .= ")";

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not add a new task to the package. Database Error";
            $logger->errorDb('6376109496157552399', $error, $db);
            $data['error'] = "ajax/add_task_to_package.php: $error";
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





























