<?php
/*  ajax/getdetaildata.php

    INPUT $_REQUEST['detailId']
    
    On failure returns empty array.
    On success, returns JSON for an associative array with the following members:
        * 'status': 'success'
        * 'detailData': as returned by act='detaildata' call to the Details API; >>>00001 JM: precise structure should be documented
        * 'detailChildren': more or less as returned by act = 'detailchildren' call to the Details API; >>>00001 JM: precise structure should be documented.
            One slight modification: if there is a top-level 'revisionCount' element, we unset that. 
*/
include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance(); // >>>00007 we don't seem ever to use $db in this file, so kill it.
$data = array();

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['status'] = 'fail';
//$data['descriptors'] = array();
// END COMMENTED OUT BY MARTIN BEFORE 2019

$detailId = isset($_REQUEST['detailId']) ? intval($_REQUEST['detailId']) : 0;

$params = array();

// RESTful call to Details API
$params['act'] = 'detaildata';
$params['detailId'] = intval($detailId);
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;
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

$results = @file_get_contents($url, false); // NOTE suppression of errors & warnings >>>00002 we should at least log them.
                                            // Looks like tests that follow effectively prevent "success" return on error.
$array = json_decode($results, 1);

$ret = array();
if (isset($array['data'])) {    
    if (is_array($array['data'])) {        
        if (isset($array['data']['detailData'])) {            
            if (is_array($array['data']['detailData'])) {                
                $ret['detailData'] = $array['data']['detailData'];
                $ret['detailChildren'] = array();                
                $ret['status'] = 'success'; // NOTE that we set success this early, before we've even made one of the Details API calls.

                // Another RESTful call to Details API
                $params = array();
                $params['act'] = 'detailchildren';
                $params['detailId'] = intval($detailId);
                $params['time'] = time();
                $params['keyId'] = DETAILS_HASH_KEYID;
                
                $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);                
                $results = @file_get_contents($url, false);   // NOTE suppression of errors & warnings >>>00002 we should at least log them.
                                                              // and >>>00006 probably should prevent "success" return on error                
                $array = json_decode($results, 1);               
                
                if (isset($array['data'])) {                    
                    if (is_array($array['data'])) {                        
                        if (isset($array['data']['detailChildren'])) {                            
                            if (is_array($array['data']['detailChildren'])) {                                
                                $ret['detailChildren'] = $array['data']['detailChildren'];                                
                            }                            
                        }                        
                    }                    
                }                
                
                // Explicitly unset any $detailChildren['revisionCount'] (>>>00014 JM: not sure why).
                if (isset($ret['detailChildren']['revisionCount'])) {
                    unset($ret['detailChildren']['revisionCount']);
                }
            }
        }
    }
}

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['results'] = $array['data'];
//print_r($ret);
// END COMMENTED OUT BY MARTIN BEFORE 2019

header('Content-Type: application/json');
echo json_encode($ret);
die();

?>