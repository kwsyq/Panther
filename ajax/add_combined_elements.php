<?php
/*  ajax/add_combined_elements.php

    Usage: in workordertasks.php
        *Get an array of id's of the selected single elements and creates a Brand new Element 
            from combined elements: Element1,Element2.
    
    Possible actions: 
        *On select combined elements, from modal Combin Elementss,
            creates a Brand new Element from combineded single elements.

    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrder.
    INPUT $_REQUEST['arrayOfElements'] : ids of elements to combined.
    INPUT $_REQUEST['jobId']: primary key in DB table job.
    
    SUMMARY: 
        * get an array of id's of selected single elements.
        * check for this workorder if we already have a combined element with the same name.
        * insert the combined element entry in element table.
        * We already have this mix of Elements : ' We could not add this Element. We already have this mix of Elements. '


*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();
    $data['status'] = 'fail';
    $data['error'] = '';


    // Brand new Element from combined.
    $arrayOfElements = isset($_REQUEST['arrayOfElements']) ? $_REQUEST['arrayOfElements'] : array();
    $workOrderId = isset($_REQUEST['workorderId']) ? intval($_REQUEST['workorderId']) : 0;
    $jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;

    if ($arrayOfElements && count($arrayOfElements) > 1) {


        $combinedElements = implode(",",$arrayOfElements);
        // check for this workorder if we already have a combined element with the same name.

        $query = " SELECT elementId ";
        $query .= " FROM  " . DB__NEW_DATABASE . ".element  ";
        $query .= " WHERE elementName = '" . $db->real_escape_string($combinedElements) . "'";
        $query .= " AND workOrderId = " . intval($workOrderId) . ";";
       
        $result = $db->query($query);
        if ($result) {
            // Safe to insert the combined element entry in element table.
            if ($result->num_rows == 0) {

 
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".element (jobId, elementName, workOrderId) VALUES (";
                $query .= intval($jobId).", ";
                $query .= "'" . $db->real_escape_string($combinedElements) ."' ,";
                $query .= intval($workOrderId)." ";
                $query .= ")";

               

                $result = $db->query($query);
            
                if (!$result) {
                    $error = "We could not add a new Combined Element in Elemen table. Database Error";
                    $logger->errorDb('637647982740405651', $error, $db);
                    $data['error'] = "ajax/add_combined_elements.php: $error";
                    header('Content-Type: application/json');
                    echo json_encode($data);
                    die();
                }
            } else {

                // We already have this mix of Elements
                $data['error'] = 'We could not add this Element. We already have this mix of Elements.';
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





























