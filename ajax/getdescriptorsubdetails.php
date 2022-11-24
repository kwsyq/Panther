<?php 
/*  ajax/getdescriptorsubdetails.php

    INPUT $_REQUEST['descriptor2Id']: primary key to DB table Descriptor2.
    MUST HAVE EXACTLY ONE OF THESE TWO INPUTS NON-ZERO.
    
    Get the details (in the sense of Details API) for a descriptor2 (before 2019-12, for a descriptorSub).

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if descriptor2Id not valid or on other errors; otherwise "success".
        * 'error': text description of error on status=="fail", irrelevant otherwise
        * 'details': on success, array of associative arrays, each with content:
            * 'pngurl'
            * 'pdfurl'
            * 'code'
            * 'fullname'
            * 'approved' (should be 0 or 1)
            * 'statusDisplay' (JM: I believe always '(approved)' or '(NOT APPROVED)' >>>00001 can someone verify?)
            * 'statusName'
            * 'detailRevisionStatusTypeId' 
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance();  // >>>00007 we don't seem ever to use $db in this file, so kill it.

$data = array();
$data['status'] = 'fail';
$data['details'] = array();

$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;

if (!Descriptor2::validate($descriptor2Id, '1577119183')) {
    $data['error'] = "Couldn't validate descriptor2Id $descriptor2Id";
    return $data;
}

// At this point we know descriptor2Id is valid.
$descriptor2 = new Descriptor2($descriptor2Id);
$details=$descriptor2->getDescriptorSubDetails();
    
$idstring = '';            
foreach ($details AS $detail) {        
    if (is_numeric($detail['detailRevisionId'])) {                    
        if (strlen($idstring)) {
            // not the first
            $idstring .= ",";
        }
        $idstring .= $detail['detailRevisionId'];                    
    }        
}

$infoarray = array(); // associative array indexed by detailRevisionId

if (strlen($idstring)) {
    // Use Details API to get detailinfo for the listed details
    $params = array();
    $params['act'] = 'detailinfo';
    $params['time'] = time();
    $params['keyId'] = DETAILS_HASH_KEYID;
    $params['ids'] = $idstring;
    
    $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);    
    $results = @file_get_contents($url, false); // NOTE suppression of errors & warnings >>>00002 we should at least log them.
                                                // and >>>00006 probably should prevent "success" return on error
    $array = json_decode($results, 1);
    
    if (is_array($array['data'])) {
        if (isset($array['data']['info'])) {
            if (is_array($array['data']['info'])) {            
                foreach ($array['data']['info'] as $inkey => $info) {
                    $infoarray[$info['detailRevisionId']] = $info;
                }                        
            }                    
        }                
    }
}

//$data['details'] = array(); // Commented out by Martin before 2019

foreach ($details as $detail) {
    // Build URL to request PNG thumb for the listed detail from the Details API.
    // This URL will call the Details API using the GET method.
    // NOTE that we don't make that call now: that happens when someone uses this URI.
    $params = array();
    $params['act'] = 'detailthumb';
    $params['time'] = time();
    $params['keyId'] = DETAILS_HASH_KEYID;
    $params['fileId'] = $detail['detailRevisionId'];
    
    $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);                
    $detail['pngurl'] = $url;
    
    // Build URL to request PDF for the listed detail from the Details API.
    // This URL will call the Details API using the GET method.
    // NOTE that we don't make that call now: that happens when someone uses this URI.
    $params = array();
    $params['act'] = 'detailpdf';
    $params['time'] = time();
    $params['keyId'] = DETAILS_HASH_KEYID;
    $params['fileId'] = $detail['detailRevisionId'];
        
    $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);            
    $detail['pdfurl'] = $url;
    
    // Fill in more data from $infoarray
    if(isset($infoarray[$detail['detailRevisionId']])) {                
        $detail['code'] = $infoarray[$detail['detailRevisionId']]['code'];
        $detail['fullname'] = $infoarray[$detail['detailRevisionId']]['fullname'];
        $detail['approved'] = intval($infoarray[$detail['detailRevisionId']]['approved']);                
        $detail['statusDisplay'] = $infoarray[$detail['detailRevisionId']]['statusDisplay'];
        $detail['statusName'] = $infoarray[$detail['detailRevisionId']]['statusName'];
        $detail['detailRevisionStatusTypeId'] = intval($infoarray[$detail['detailRevisionId']]['detailRevisionStatusTypeId']);
    }
    
    /*
    (JM: The following is a very different data structure than any used in this file; 
         I believe it's a structure from the external "details" system.)
         
    // BEGIN MARTIN COMMENT
    [784] => Array
    (
            [detailRevisionId] => 784
            [detailId] => 866
            [dateBegin] => 2017-02-22
            [dateEnd] => 2500-01-01
            [code] => JLLRF
            [search] =>
            [notes] =>
            [caption] =>
            [createReason] =>
            [inserted] => 2017-02-22 17:03:10
            [approved] => 1
            [parentId] => 98
            [name] => A
            [parsename] =>
            [searchText] =>
    )
    // END MARTIN COMMENT
    */           
    
    $data['details'][] = $detail;
}
        
$data['status'] = 'success';
header('Content-Type: application/json');
echo json_encode($data);
die();

?>