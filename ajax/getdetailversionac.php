<?php
/*  ajax/getdetailversionac.php
    INPUT $_REQUEST['term']: >>>00001 JM 2019-05-08 not sure but believes this is an optional string to be autocompleted,
                                    maybe related to detailComponentName in the Details subsystem.

    Basically, this whole file wraps a single RESTful query to the Details API.
    
    >>>00001 As of 2019-05, everything is a bit tentative until the Details API is better documented. 
    BASICALLY, if there is an $array['data']['terms'] in the return from the RESTful query to the Details API,
    we will JSON-encode that and return it.
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance(); // >>>00007 we don't seem ever to use $db in this file, so kill it.
$data = array();

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['status'] = 'fail';
//$data['descriptors'] = array();
// END COMMENTED OUT BY MARTIN BEFORE 2019

$term = isset($_REQUEST['term']) ? $_REQUEST['term'] : '';
$params = array();
/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$detailMaterialId_1 = isset($_REQUEST['material_1']) ? intval($_REQUEST['material_1']) : 0;
$detailComponentId_1 = isset($_REQUEST['component_1']) ? intval($_REQUEST['component_1']) : 0;
$detailFunctionId_1 = isset($_REQUEST['function_1']) ? intval($_REQUEST['function_1']) : 0;
$detailForceId_1 = isset($_REQUEST['force_1']) ? intval($_REQUEST['force_1']) : 0;
$detailMaterialId_2 = isset($_REQUEST['material_2']) ? intval($_REQUEST['material_2']) : 0;
$detailComponentId_2 = isset($_REQUEST['component_2']) ? intval($_REQUEST['component_2']) : 0;
$detailFunctionId_2 = isset($_REQUEST['function_2']) ? intval($_REQUEST['function_2']) : 0;
$detailForceId_2 = isset($_REQUEST['force_2']) ? intval($_REQUEST['force_2']) : 0;
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/
$params['act'] = 'versionautocomplete';
$params['term'] = $term;
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;
/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$params['material_1'] = $detailMaterialId_1;
$params['component_1'] = $detailComponentId_1;
$params['function_1'] = $detailFunctionId_1;
$params['force_1'] = $detailForceId_1;

$params['material_2'] = $detailMaterialId_2;
$params['component_2'] = $detailComponentId_2;
$params['function_2'] = $detailFunctionId_2;
$params['force_2'] = $detailForceId_2;
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/
$url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$username = DETAILS_BASICAUTH_USER;
//$password = DETAILS_BASICAUTH_PASS;
//$context = stream_context_create(array(
//		'http' => array(
//				'header'  => "Authorization: Basic " . base64_encode("$username:$password")
//		)
//));
// END COMMENTED OUT BY MARTIN BEFORE 2019
$results = @file_get_contents($url, false);
$array = json_decode($results, 1);

$ret = array();
if (isset($array['data'])) {    
    if (is_array($array['data'])) {        
        if (isset($array['data']['terms'])) {            
            if (is_array($array['data']['terms'])) {                
                $ret = $array['data']['terms'];
            }            
        }        
    }    
}

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['results'] = $array['data'];
//print_r($array);
// END COMMENTED OUT BY MARTIN BEFORE 2019

header('Content-Type: application/json');
echo json_encode($ret);
die();

?>