<?php
/*  ajax/add_extra_description_task.php
    
    Usage: in workordertasks.php, workorder.php, contract.php. On the existing workorder structure of workOrderTasks, we can add
        extra description or billing description.


    INPUT $_REQUEST['nodeTaskId']: alias workOrderTaskId, primary key in DB table workOrderTask.
    INPUT $_REQUEST['extraDescription'] : the extra description of a workOrderTask.
    INPUT $_REQUEST['billingDescription']: the billing description of a workOrderTask.
    INPUT $_REQUEST['billDesc']: boolean, if true we update the billing description.

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


    // Brand new extraDescription.
    $nodeTaskId = isset($_REQUEST['nodeTaskId']) ? intval($_REQUEST['nodeTaskId']) : 0;
    $extraDescription = isset($_REQUEST['extraDescription']) ? trim($_REQUEST['extraDescription']) : "";
    $billingDescription = isset($_REQUEST['billingDescription']) ? trim($_REQUEST['billingDescription']) : "";
    $billDesc  = isset($_REQUEST['billDesc']) ? $_REQUEST['billDesc'] : false;
  


        $query = " UPDATE " . DB__NEW_DATABASE . ".workOrderTask SET  ";
        if ($billDesc == true) {
        $query .= " billingDescription = '" . $db->real_escape_string($billingDescription) . "' ";
        } else {
            $query .= " extraDescription = '" . $db->real_escape_string($extraDescription) . "' ";
        }
        $query .= " WHERE workOrderTaskId = " . intval($nodeTaskId);

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not Update the extraDescription/ billingDescription. Database Error";
            $logger->errorDb('63761094961575132', $error, $db);
            $data['error'] = "ajax/add_extra_description_task.php: $error";
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





























