<?php 
/*  ajax/delete_package_tasks.php

    Usage: in workordertasks.php, on Templates tab 

    INPUT $_REQUEST['taskPackageTaskId']: primary key in DB table taskPackageTask
    INPUT $_REQUEST['taskPackageId']: primary key in DB table taskPackage


    Assuming taskPackageTaskId is valid, delete any matching taskPackageTask. And if no rows left in taskPackageTask
    deletes the taskPackage from taskPackageTask.

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

$taskPackageTaskId = isset($_REQUEST['taskPackageTaskId']) ? intval($_REQUEST['taskPackageTaskId']) : 0;
$taskPackageId = isset($_REQUEST['taskPackageId']) ? intval($_REQUEST['taskPackageId']) : 0;  


if($taskPackageTaskId) {


    $query = " DELETE FROM " . DB__NEW_DATABASE . ".taskPackageTask WHERE taskPackageTaskId=" . intval($taskPackageTaskId) ;
    $result = $db->query($query);

    if (!$result) {
        $error = "We could not delete the task from taskPackageTask. Database Error";
        $data['error'] = "ajax/delete_package_tasks.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 

}

if($taskPackageId) {
    
    $query = " SELECT taskPackageId ";
    $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackageTask WHERE taskPackageId=" . intval($taskPackageId) ;
    syslog(LOG_ERR, $query);

    $result = $db->query($query);
    if ($result) {
        if ($result->num_rows == 0) { // delete the taskPackage entry from taskPackage table
 
            $query = " DELETE FROM " . DB__NEW_DATABASE . ".taskPackage WHERE taskPackageId=" . intval($taskPackageId); 

            $result = $db->query($query); 

            if(!$result){
                $error = "We could not delete the TaskPackage from taskPackage table. Database Error";
                $data['error'] = "ajax/delete_package_tasks.php: $error";
                header('Content-Type: application/json');
                echo json_encode($data);
                die(); 
            }
               
        }
    } else {
        $error = "We could not select the TaskPackage from taskPackage table. Database Error";
        $data['error'] = "ajax/delete_package_tasks.php: $error";
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