<?php
/*  ajax/update_task_package_name.php


    Usage: on workordertasks.php, Templates tab. Edit the Package name.
        On right click on a package name, a popup will apear, edit/ update the name of a template package.

    INPUT $_REQUEST['nodeId']: alias of taskPackageId, primary key in DB table taskPackage.
    INPUT $_REQUEST['packageNameUpdate']: the Package Name value.


    EXECUTIVE SUMMARY:  
        * Updates in the table taskPackage rhe package name with the specified value from REQUEST.

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


    // Brand new taskPackage.
    $taskPackageId = isset($_REQUEST['nodeId']) ? intval($_REQUEST['nodeId']) : 0;
    $packageName = isset($_REQUEST['packageNameUpdate']) ? trim($_REQUEST['packageNameUpdate']) : "";

    if (strlen($packageName)) {  
    


        $query = " UPDATE " . DB__NEW_DATABASE . ".taskPackage SET  ";
        $query .= " packageName = '" . $db->real_escape_string($packageName) . "' ";
        $query .= " WHERE taskPackageId = " . intval($taskPackageId);
    

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not Update the package name. Database Error";
            $logger->errorDb('637610949615751211', $error, $db);
            $data['error'] = "ajax/update_task_package_name.php: $error";
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





























