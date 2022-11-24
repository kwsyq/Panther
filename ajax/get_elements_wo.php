<?php
/*  ajax/get_elements_wo.php
    
    Usage: on workordertasks.php,      
        *On modal Select Single Elements(s), will show all the single elements and the combined elements to be selected.
        *On modal Combine Elements, will show all the single elements to be combined.

    PRIMARY INPUT :  
        $_REQUEST['workOrderId']
        $_REQUEST['jobId'].

    Returns JSON for an associative array with the following members:
    * 'data['single']': array. Each element is an associative array with elements:
        * 'elementId': identifies the element,
        * 'elementName': the element name.
    * 'data['combined']': array. Each element is an associative array with elements:
        * 'elementId': identifies the element,
        * 'elementName': the element name.

        * 'fail': "fail" on query failure ( database error ),
        * 'status': "success" on successful query.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : 0;
    $jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;

    // Single elements
    $arr0 = [];
    $general = [];
    $query = " SELECT elementId, elementName ";
    $query .= " FROM  " . DB__NEW_DATABASE . ".element  ";
    $query .= " WHERE elementName = '" . $db->real_escape_string("General") . "'";
    $query .= " AND workOrderId = " . intval($workOrderId) . ";";

    $result = $db->query($query);
    if ($result) {
        if ($result->num_rows == 0) { // We don't have an General Element
            $general["elementId"] = "0";
            $general["elementName"] = "General";
            $arr0 = $general;
        } else {
            while ($row = $result->fetch_assoc()) {
                $arr0 = $row;
        
            }
        }
    } else {

        $error = "We could not get the the General Element. Database Error";
        $logger->errorDb('637648942257392325', $error, $db);
        $data['error'] = "ajax/get_elements_wo.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
        
    }

    $arr = [];

    $query = " SELECT elementId, elementName ";
    $query .= " FROM  " . DB__NEW_DATABASE . ".element  ";
    $query .= " WHERE jobId = ". intval($jobId)." ";
    $query .= " AND workOrderId IS NULL ;";


    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {

            $arr[] = $row;
        }
    } else {
        $error = "We could not get the the ELements. Database Error";
        $logger->errorDb('637648941831629280', $error, $db);
        $data['error'] = "ajax/get_elements_wo.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
    $arr[] = $arr0;


    // Combined elements
    $arrComb = [];
    $arr2 = [];

    $query = " SELECT elementId, elementName ";
    $query .= " FROM  " . DB__NEW_DATABASE . ".element  ";
    $query .= " WHERE jobId = ". intval($jobId)." ";
    $query .= " AND workOrderId IS NOT NULL AND workOrderId = " . intval($workOrderId) . " AND elementName<>'General';";


    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {

            $arr2[] = $row;
        }
    } else {
        $error = "We could not get the the Combined ELements. Database Error";
        $logger->errorDb('637649632106310094', $error, $db);
        $data['error'] = "ajax/get_elements_wo.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
    $arrComb[] = $arr2;


    //$data[] = $arr;
    $data['single'] = $arr;
    $data['combined'] = $arrComb;

    if (!$data['error']) {
        $data['status'] = 'success';
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>