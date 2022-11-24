<?php 
/*  ajax/getdetailchildren.php

    INPUT $_REQUEST['parentId']: should be an integer; we perform intval on it, 
        >>>00002, >>>00016 should validate better & log errors. Appears to be a detail
        (from the Details API) that has 'children' (>>>00001 as of 2019-05, the
        meaning of that isn't clear because the Details subsystem is undocumented;
        JM guesses drawings of sub-details)

     NOTE that this makes multiple RESTful calls to the Details API.

     >>>00001 The exact manipulations on the data structure are a bit convoluted 
      (JM: and possibly in some cases redundant; I'm not sure I understand why 
      some of this is done the way it is done), but the 'details' return appears 
      to be of the form described below (>>>00001 JM someone should verify this, 
      though, and clarify these remarks when they do).
    Returns JSON for an associative array with the following members:    
        * 'status': "success" if $idString is non-empty, "fail" otherwise. 
            So success means the return from the Details subsystem includes at least 
            one $array['data']['detailChildren'][foo]['currentDetailRevisionId']; 
            we may return 'success' even if later calls to the Details subsystem fail.
            >>>00016 probably want to revisit that.
        * 'details': (>>>00001 JM someone should verify this and either correct 
            these remarks or remove this parenthesis when they do). 
            Numerically indexed array. Each element is an associative array 
            describing a child detail of the original parentId that was passed in.
            There are at least the following element for each child element:
                * 'detailRevisionId': the main identifier for the detail
                * 'pngurl': thumb URL
                * 'pdfurl': PDF URL
                * 'code' (e.g. "JRRLF")
                * 'fullname'
                * 'approved': quasi-Boolean (0 or 1), normally 1 
                * 'classifications': an array, because the same detail can actually be usable in more than one context. For each array member:
                    * 'detailRevisionTypeItemId': (>>>Q JM what is the domain of this? Maybe a reason for the revision (e.g. building code change), but numbers aren't particularly small integers (e.g. 346). Anyway, comes from the "details" DB, not the sssnew DB.)
                    * 'detailRevisionId': should match detailRevisionId above.
                    * 'detailMaterialId': Id corresponding to detailMaterialName.
                    * 'detailComponentId': Id corresponding to detailComponentName.
                    * 'detailFunctionId': Id corresponding to detailFunctionName.
                    * 'detailForceId': Id corresponding to detailForceName.
                    * 'detailMaterialName': e.g. "Wood".
                    * 'detailComponentName': e.g. "Framing".
                    * 'detailFunctionName': e.g. "Lateral".
                    * 'detailForceName': e.g. "In Plane".
                    * 'childcount' 
            Because the Details subsystem is undocumented, I'm not certain of what else may be passed through without being touched, 
            but I (JM) believe there is also:
                * 'detailId': I believe, but am not certain, that this ties together multiple detailRevisionIds that are basically the same thing over time.
                * 'dateBegin': DATE
                * 'dateEnd': DATE, typically 2500-01-01
                * 'search': I believe an empty string here
                * 'notes': often an empty string
                * 'caption': >>> ?
                * 'createReason': >>> ?
                * 'inserted': TIMESTAMP
                * 'parentId': always as passed in
                * 'name': (e.g. 'A')
                * 'parsename': >>> ?
                * 'searchText': I believe an empty string here 
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance(); // >>>00007 we don't seem ever to use $db in this file, so kill it.

$data = array();

$data['status'] = 'fail';
$data['details'] = array();

$parentId = isset($_REQUEST['parentId']) ? intval($_REQUEST['parentId']) : 0;

// Get the child details of the given parent detail. 
$params = array();
$params['act'] = 'detailchildren';
$params['detailId'] = intval($parentId);
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;

$url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);
$results = @file_get_contents($url, false); // NOTE suppression of errors & warnings >>>00002 we should at least log them.
                                            // Looks like tests that follow effectively prevent "success" return on error.
$array = json_decode($results, 1);
$detailChildren = array();

$idstring = ''; // Comma-separted string of the IDs of all of the children
if (isset($array['data'])) {    
    if (is_array($array['data'])) {        
        if (isset($array['data']['detailChildren'])) {            
            if (is_array($array['data']['detailChildren'])) { // >>>00001 JM: not sure whether this is an associative array (indexed by some sort of ID)
                                                              // or just numerically indexed; in any case, the indexes are ignored.
                $detailChildren = $array['data']['detailChildren'];                
                foreach ($detailChildren as $child) {
                    // $child is an associative array describing one Detail; the only element we care about here is $child['currentDetailRevisionId']. 
                    // Not sure if it may have other elements.
                    if (intval($child['currentDetailRevisionId'])) {
                        if (strlen($idstring)) {
                            // Not the first
                            $idstring .= ",";
                        }
                        $idstring .= intval($child['currentDetailRevisionId']);
                    }
                }
            }
        }
    }
}

// Explicitly unset any $detailChildren['revisionCount'] (>>>00014 JM: not sure why).
if (isset($detailChildren['revisionCount'])) {
    unset($detailChildren['revisionCount']);
}

$infoarray = array();
if (strlen($idstring)) {
    if (strlen($idstring)) { // >>>00007 This test is completely redundant and should be dropped.
        // Use $idString to make another RESTful call to the Details API to get info for all of these children. 
        $params = array();
        $params['act'] = 'detailinfo';
        $params['time'] = time();
        $params['keyId'] = DETAILS_HASH_KEYID;
        $params['ids'] = $idstring;
            
        $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);

        $results = @file_get_contents($url, false);   // NOTE suppression of errors & warnings >>>00002 we should at least log them.
                                                      // and >>>00006 probably should prevent "success" return on error
        $array = json_decode($results, 1);
        
        if (is_array($array['data'])) {
            if (isset($array['data']['info'])) {
                if (is_array($array['data']['info'])) {
                    foreach ($array['data']['info'] as $info) {
                        $infoarray[$info['detailRevisionId']] = $info;
                    }                        
                }                    
            }                
        }
    }
    
    //$data['details'] = array(); // Commented out by Martin before 2019
    
    foreach ($infoarray as $detail) {
        // Build URL to request PNG thumb for the listed detail from the Details API.
        // This URL will call the Details API using the GET method.
        // NOTE that we don't make that call now: that happens when someone uses this URI.
        $params = array();
        $params['act'] = 'detailthumb';
        $params['time'] = time();
        $params['keyId'] = DETAILS_HASH_KEYID;
        $params['fileId'] = $detail['detailRevisionId'];
        
        $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);            
        $detail['pngurl'] = $url;
            
        // Build URL to request PDF for the listed detail from the Details API.
        // This URL will call the Details API using the GET method.
        // NOTE that we don't make that call now: that happens when someone uses this URI.
        $params = array();
        $params['act'] = 'detailpdf';
        $params['time'] = time();
        $params['keyId'] = DETAILS_HASH_KEYID;
        $params['fileId'] = $detail['detailRevisionId'];
            
        $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);
        $detail['pdfurl'] = $url;
        
        if(isset($infoarray[$detail['detailRevisionId']])) {                
            $detail['code'] = $infoarray[$detail['detailRevisionId']]['code'];
            $detail['fullname'] = $infoarray[$detail['detailRevisionId']]['fullname'];
            $detail['approved'] = intval($infoarray[$detail['detailRevisionId']]['approved']);
            $detail['classifications'] = $infoarray[$detail['detailRevisionId']]['classifications'];
            $detail['childCount'] = $infoarray[$detail['detailRevisionId']]['childCount'];
            
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
    } // END foreach ($infoarray...), which is to say for each detail
    
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>