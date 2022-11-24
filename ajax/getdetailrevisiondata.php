<?php 
/*  ajax/getdetailrevisiondata.php

    INPUT $_REQUEST['detailRevisionId']: key from Details API
    
    On failure returns empty array.

    On success, returns JSON for an associative array with the following members:    
        * 'status': 'success'
        * 'detailRevisionData': (>>>00001, >>>00014 there may be omissions; most of this we know about 
              (as of 2019-05) only because array elements are referenced by fb/detaildata.php, which calls this) 
              This is the associative array returned by that original RESTful call 
              (act='detailrevisiondata', detailRevisionId = detailRevisionId) to the Details API, with additional elements:
            * 'pdfurl': (for embedding) ADDED EXPLICITLY AT THIS LEVEL OF CODE.
            * 'dlpdfurl': (the other form of the PDF URL) ADDED EXPLICITLY AT THIS LEVEL OF CODE.
            * 'fullname'
            * 'typeitems': array of associative arrays. I believe these represent the valid combinations (for this detail) of 
               material/component/function/force types drawn from the Details API. Each ...['typeitems'][foo] has elements:
                * 'detailMaterialName'
                * 'detailComponentName'
                * 'detailFunctionName'
                * 'detailForceName' 
            * 'revisions': array of associative arrays (>>> JM representing what?
              >>>00001 With Details API still undocumented 2019-05 it's hard to be fully sure what
              this is about, but JM presumes it fetches data about prior revisions related to a 
              particular detailRevisionId.). 
              >>>00001 JM: May be optional, can't tell from existing code. 
              Each ...['revisions'][foo] has elements:
                * 'status' - this and the following could use more explanation
                * 'code'
                * 'caption'
                * 'createReason'
                * 'pdfurl', which is more like 'dlpdfurl' at the higher level. ADDED EXPLICITLY AT THIS LEVEL OF CODE.
                * 'discussions': again, an array of associative arrays, each of which contains:
                    * 'cdr' (code/date/reason) value, apparently a constant across all of the arrays for a given detail.
                    * 'note': actual note contents
                    * 'initials': who inserted the note
                    * 'inserted': when the note was inserted
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance(); // >>>00007 we don't seem ever to use $db in this file, so kill it.
$data = array();

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['status'] = 'fail';
//$data['descriptors'] = array();
// END COMMENTED OUT BY MARTIN BEFORE 2019

$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;

$params = array();
$params['act'] = 'detailrevisiondata';
$params['detailRevisionId'] = intval($detailRevisionId);
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;

$url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);
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
        if (isset($array['data']['detailRevisionData'])) {            
            if (is_array($array['data']['detailRevisionData'])) {                
                $ret['detailRevisionData'] = $array['data']['detailRevisionData'];
                
                // Build URL to request PDF *for embedding* for the listed detail from the Details API.
                // This URL will call the Details API using the GET method.
                // NOTE that we don't make that call now: that happens when someone uses this URI.
                $params = array();
                $params['act'] = 'detailpdf';
                $params['forEmbedding'] = 'yes';
                $params['time'] = time();
                $params['keyId'] = DETAILS_HASH_KEYID;                
                $params['fileId'] =  $ret['detailRevisionData']['detailRevisionId'];
                
                $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);                
                $ret['detailRevisionData']['pdfurl'] = $url;

                // Build similar URL, gets a different URL that is *not* for embedding the PDF
                $params = array();
                $params['act'] = 'detailpdf';
                $params['time'] = time();
                $params['keyId'] = DETAILS_HASH_KEYID;
                $params['fileId'] =  $ret['detailRevisionData']['detailRevisionId'];
                
                $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);
                $ret['detailRevisionData']['dlpdfurl'] = $url;
                
                $revisions = $array['data']['detailRevisionData']['revisions'];
                // & build similar URL for other (>>>00001 prior?) revisions.               
                foreach ($revisions as $rkey => $revision) {                    
                    $params = array();
                    $params['act'] = 'detailpdf';
                    $params['time'] = time();
                    $params['keyId'] = DETAILS_HASH_KEYID;
                    $params['fileId'] = $revision['detailRevisionId'];
                     
                    $url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);                    
                    $ret['detailRevisionData']['revisions'][$rkey]['pdfurl'] = $url;                    
                }
                
                $ret['status'] = 'success';
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