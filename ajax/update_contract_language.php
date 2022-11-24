<?php
/*  ajax/update_contract_autosave.php

    Usage: on contract page.

    INPUT $_REQUEST['contractId']: primary key in DB table contract.
    POSSIBLE INPUT VALUES:
        * $_REQUEST['nameOverride'],  
        * $_REQUEST['addressOverride'], 
        * $_REQUEST['hourlyRate'], 
        * $_REQUEST['termsId'].


    EXECUTIVE SUMMARY:  
        * Updates table contract with the specified values from REQUEST.

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

    // Possible values.
    $languageId = isset($_REQUEST['languageId']) ? intval($_REQUEST['languageId']) : 0;
    $contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;

    if($contractId) {
        $query = " UPDATE " . DB__NEW_DATABASE . ".contract SET  ";
        $query .= " contractLanguageId = " . intval($languageId) . " ";
        $query .= " WHERE contractId = " . intval($contractId);
    
       
        $result = $db->query($query);

        if (!$result) {
            $error = "We could not Update the contract. Database Error";
            $logger->errorDb('637776842060994129', $error, $db);
            $data['error'] = "ajax/update_contract_language.php: $error";
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

