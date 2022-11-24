<?php
/*  ajax/get_task_packages.php


    Usage: on workordertasks.php, Templates tab, get the Templates Packages.

    Possible actions: 
        *On select Templates tab display all the Templates Packages.

    Returns JSON for an associative array with the following members:
    * 'data': array. Each element is an associative array with elements:
        * 'packageName': text of the package name.
        * 'taskPackageId': identifies the task Package
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$data = array();
$data['status'] = 'fail';
$data['error'] = '';


$query = "SELECT taskPackageId, packageName ";
$query .= "FROM  " . DB__NEW_DATABASE . ".taskPackage "; 
$query .= "ORDER BY taskPackageId;";


$result = $db->query($query);

if (!$result) {
    $error = "We could not get the task packages. Database Error";
    $logger->errorDb('637570360649579007', $error, $db);
    $data['error'] = "ajax/get_task_packages.php: $error";
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