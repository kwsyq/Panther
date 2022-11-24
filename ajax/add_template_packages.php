<?php
/*  ajax/add_template_packages.php

    Usage on workordertasks page.
    On drag and drop from Workorder Elements Tab to Templates tab, creates task packages to Templates.

    PRIMARY INPUTS: 
        $_REQUEST['packageName']: the name of the package.
        $_REQUEST['packageTasks']: package with hierarchy tasks to add to an element.
        $_REQUEST['packIdfound'] : taskPackageId primary key in DB table taskPackage.
       
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';

    // Brand new taskPackage.
    $packageTasks = isset($_REQUEST['packageTasks']) ? $_REQUEST['packageTasks'] : '';
    $packageName = isset($_REQUEST['packageName']) ? $_REQUEST['packageName'] : '';
    $packIdfound = isset($_REQUEST['packIdfound']) ? $_REQUEST['packIdfound'] : '';
    $parentFolderId = isset($_REQUEST['parentFolderId']) ? $_REQUEST['parentFolderId'] : '';
 

    if($packageName) {
        //Drop over Brand new taskPackage.

        $query = " INSERT INTO " . DB__NEW_DATABASE . ".taskPackage ";
        $query .= "(packageName) VALUES (";
        $query .= " '" . $db->real_escape_string($packageName) . "'";
        $query .= ")";
    
      
        $db->query($query);
    
        $query = " SELECT taskPackageId ";
        $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackage ";
        $query .= " WHERE taskPackageId = " . intval($db->insert_id) . "";
        
       
        $result = $db->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            $packId = $row["taskPackageId"];
        } else {
            $error = "We could not get the tasks. Database Error";
            $data['error'] = "ajax/add_template_packages.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
        function writeDb($arr, $pid, $packId) {
            global $db;
          
            // write entry in Db:
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".taskPackageTask (";
            $query .= "taskPackageId, taskId, parentTaskId ";
            $query .= ") VALUES (";
            $query .= intval($packId);
            $query .= ", " . intval($arr["taskId"]);
            $query .= ", " . intval($pid). ");";
    
            $db->query($query);
    
            $pid = $db->insert_id;
    
            if(!isset($arr["items"])) {
                return;
            } else {
                foreach($arr["items"] as $value) {
                    writeDb($value, $pid, $packId);
                }
            }
        }
        writeDb($packageTasks, 1000, $packId);
    } else {

        //Drop over existing Template Packet
        $packId =  $packIdfound;

        function writeDb($arr, $pid2, $packId) {
            global $db;
          
            // write entry in Db:
    
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".taskPackageTask (";
            $query .= "taskPackageId, taskId, parentTaskId ";
            $query .= ") VALUES (";
            $query .= intval($packId);
            $query .= ", " . intval($arr["taskId"]);
            $query .= ", " . intval($pid2). ");";
    
            $db->query($query);
    
            $pid2 = $db->insert_id;
    
            if(!isset($arr["items"])) {
                return;
            } else {
                foreach($arr["items"] as $value) {
                    writeDb($value, $pid2, $packId);
                }
            }
        }
        if( $parentFolderId ) {
            writeDb($packageTasks, $parentFolderId, $packId);
        } 
        
    }
  
  

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>