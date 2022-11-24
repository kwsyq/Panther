<?php 
/*  ajax/getworkorderdetails.php

    INPUT $_REQUEST['workOrderId']: primary key in DB table WorkOrder.

    Get all of the details associated with a workOrder.
    
    >>>00006 I (JM) believe that the only significant differences between this and ajax/gettaskdetails.php are
    * INPUT: $_REQUEST['workOrderId'] vs. $_REQUEST['TaskId'], which could easily be tested at start
    * The small amount of code that we use to get from that input to $details.
    So these two could become a single file, with that small difference being covered by an 'if' statement depending which input was present.
    
    Returns JSON for an associative array with the following members:    
        * 'status': "fail" if taskId not valid or any of several other failures; "success" on success.
        * 'details': array. Each element is an associative array with elements:
            * 'detailRevisionId': identifies the detail.
            * 'detailId': >>>00001: not sure what this is about, because in ssssengnew DB we use detailRevisionId. 
                I (JM) *think* it is about tying together details that are essentially the same thing, adapted over
                time to conform with changing construction codes.
            * 'dateBegin': Along with dateEnd allows a time range to be applied to a Detail. Typically 
                would be used because of changing construction codes. As of 2018-03-18, pretty much all one constant "beginning of time" date.
            * 'dateEnd': Distant future value '2500-01-01' seems typical. See note just above on dateBegin.
            * 'code': e.g. "KKDWX", another identifier for the detail. (only if detailRevisionId is nonzero)
            * 'search': >>> JM: no idea. This one isn't in getdetailsearch.php.
            * 'notes': (>>> JM: I presume) notes drawn from the Details application.
            * 'caption': typically seems to be just false, >>> JM example would be good.
            * 'createReason': >>> JM: no idea. This one isn't in getdetailsearch.php.
            * 'inserted': (>>> JM: I presume) date detail was created in Details application.
            * 'fullname': (only if detailRevisionId is nonzero)
            * 'approved': (should be 0 or 1, 1 for "approved", 0 for not.) (only if detailRevisionId is nonzero)
            * 'statusDisplay': (JM: I believe always '(approved)' or '(NOT APPROVED)') (only if detailRevisionId is nonzero)
            * 'statusName': (only if detailRevisionId is nonzero)
            * 'detailRevisionStatusTypeId': (only if detailRevisionId is nonzero)
            * 'parentId': (>>> JM: I presume) ID of parent in Details application. Presumably detailId rather than detailRevisionId.
            * 'name': detail name (>>> JM: Are these normal mnemonic names or things like just 'A'?)
            * 'parsename': >>> JM: no idea.
            * 'searchText': >>> JM: no idea. 
            * 'classifications': an array, because the same detail can actually be usable in more than one context. 
                Each array member is an associative array:
                * detailRevisionTypeItemId: (>>>00001 JM what is the domain of this? 
                    Maybe a reason for the revision (e.g. building code change), but 
                    numbers aren't particularly small integers (e.g. 346). Anyway, comes from the Details DB, not the sssnew DB.)
                * detailRevisionId: should match detailRevisionId above.
                * detailMaterialId: Id corresponding to detailMaterialName.
                * detailComponentId: Id corresponding to detailComponentName.
                * detailFunctionId: Id corresponding to detailFunctionName.
                * detailForceId: Id corresponding to detailForceName.
                * detailMaterialName: e.g. "Wood".
                * detailComponentName: e.g. "Framing".
                * detailFunctionName: e.g. "Lateral".
                * detailForceName: e.g. "In Plane". 
            * 'childCount': number of child details
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance(); // >>>00007 we don't seem ever to use $db in this file, so kill it.

$data = array();
$data['status'] = 'fail';
$data['details'] = array();

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (existWorkOrderId($workOrderId)) {
    $wo = new WorkOrder($workOrderId);	
    if (intval($wo->getWorkOrderId())) {		
        $details = $wo->getWorkOrderDetails();        
        $idstring = '';	// Comma-separted string of the IDs of all of the details		
        foreach ($details as $detail) {		
            if (is_numeric($detail['detailRevisionId'])) {					
                if (strlen($idstring)) {
                    // Not the first
                    $idstring .= ",";
                }
                $idstring .= $detail['detailRevisionId'];					
            }		
        }
        
        $infoarray = array();		
        if (strlen($idstring)) {
            // RESTful call to the Details API.
            // NOTE that the only part of the return we care about is $array['data']['info'] 
            $params = array();
            $params['act'] = 'detailinfo';
            $params['time'] = time();
            $params['keyId'] = DETAILS_HASH_KEYID;
            $params['ids'] = $idstring;
            $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);
            $results = @file_get_contents($url, false);
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
        
        foreach ($details as $detail) {
		    
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
                
			// Similarly, URL to request PDF.
            $params = array();
            $params['act'] = 'detailpdf';
            $params['time'] = time();
            $params['keyId'] = DETAILS_HASH_KEYID;
            $params['fileId'] = $detail['detailRevisionId'];                
            $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);            
            $detail['pdfurl'] = $url;
            
            $params = array();
            $params['act'] = 'supportdoc';
            $params['time'] = time();
            $params['keyId'] = DETAILS_HASH_KEYID;
            $params['detailRevisionId'] = $detail['detailRevisionId'];                
            $params['type'] = 'dwg';                
            $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);            
            $detail['dwgurl'] = $url;

            if(isset($infoarray[$detail['detailRevisionId']])) {                
                $detail['code'] = $infoarray[$detail['detailRevisionId']]['code'];
                $detail['fullname'] = $infoarray[$detail['detailRevisionId']]['fullname'];
                $detail['approved'] = intval($infoarray[$detail['detailRevisionId']]['approved']);
                $detail['classifications'] = $infoarray[$detail['detailRevisionId']]['classifications'];
                $detail['childCount'] = $infoarray[$detail['detailRevisionId']]['childCount'];
            }
            // (JM: The following is a very different data structure than any used in this file; 
            //  I believe it's a structure from the external "details" system.)
            
            /*
            // BEGIN MARTIN COMMENT
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
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>