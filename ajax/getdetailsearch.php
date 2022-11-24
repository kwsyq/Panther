<?php
/*  ajax/getdetailsearch.php

    >>>00001 JM it is possible that this is no longer actually used as of 2019-04; if not,
    and if there is no reason to revive it, let's get rid of it.

    ALL inputs are optional.
    INPUT $_REQUEST['workOrderId']: Primary key in DB table WorkOrder. If present, then 'exists' values in the
                                    return reflect whether this workOrder already has a particular detail; if
                                    absent, then 'exists' is always 0.
    INPUTs $_REQUEST['material_1'], $_REQUEST['component_1'], $_REQUEST['func_1'], $_REQUEST['force_1'],
           $_REQUEST['material_2'], $_REQUEST['component_2'], $_REQUEST['func_2'], $_REQUEST['force_2'],
           $_REQUEST['material_3'], $_REQUEST['component_3'], $_REQUEST['func_3'], $_REQUEST['force_3']: exactly as in
                                    our usual search for details; see, for example, /fb/detailsearch.php.            
    INPUT $_REQUEST['term']: >>>00001 JM 2019-05-08 not sure but believes this is an optional string to be autocompleted,
                                    maybe related to detailComponentName in the Details subsystem.
    
    Returns JSON for an associative array, largely as returned from the Details API RESTful interface.
    >>>00001 JM The following is all a bit tentative, please revise if I'm wrong.
    NOTE the extra 'data' level.
        * 'data'
            * 'status'
            * 'data'    
                * 'searchresults', an array of associative arrays, one per matching detail.
                    * 'pngurl'
                    * 'pdfurl'
                    * 'exists': 1 if the detailRevisionId is an index in $workOrderDetails, 0 if not.
                    * 'matchcount': the number of inputs matched and that the results will be in descending order by this value 
                        (which should correlate to relevance).
                    * 'detailRevisionId': identifies the detail.
                    * 'name': detail name. These seem typically to be a single letter, not sure how they are used; 
                        Martin says as of 2018-03-08 highly subject to change.
                    * 'fullname': a fuller form of name, basically placing it in a hierarchy; 
                        e.g. if name is "F" this might be "BG.F". Martin says as of 2018-03-08 highly subject to change.
                    * 'dateBegin': Along with dateEnd allows a time range to be applied to a Detail. 
                        Typically would be used because of changing construction codes. As of 2018-03-18, pretty 
                        much all one constant "beginning of time" date.
                    * 'dateEnd': Distant future value 2500-01-01 seems typical. See note just above on dateBegin.
                    * 'code': e.g. "KKDWX", another identifier for the detail.
                    * 'caption': typically seems to be just false; place to hang a caption for the detail
                    * 'classifications': an array, because the same detail can actually be usable in more than one context. For each array member:
                        * 'detailRevisionTypeItemId': (>>>Q JM what is the domain of this? Maybe a reason for the revision (e.g. building code change), 
                            but numbers aren't particularly small integers (e.g. 346). Anyway, comes from the "details" DB, not the sssnew DB.)
                        * 'detailRevisionId': should match detailRevisionId above.
                        * 'detailMaterialId': Id corresponding to detailMaterialName.
                        * 'detailComponentId': Id corresponding to detailComponentName.
                        * 'detailFunctionId': Id corresponding to detailFunctionName.
                        * 'detailForceId': Id corresponding to detailForceName.
                        * 'detailMaterialName': e.g. "Wood".
                        * 'detailComponentName': e.g. "Framing".
                        * 'detailFunctionName': e.g. "Lateral".
                        * 'detailForceName':                                                                                                                                                                                     e.g. "In Plane".     
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance(); // >>>00007 we don't seem ever to use $db in this file, so kill it.
$data = array();

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['status'] = 'fail';
//$data['descriptors'] = array();
// END COMMENTED OUT BY MARTIN BEFORE 2019

/*
// JM believes the following comment refers to content in the Details subsystem,
//  not anything directly acccessed here.

// BEGIN MARTIN COMMENT
0] => Array
        (
            [workOrderDetailId] => 9
            [workOrderId] => 7020
            [detailRevisionId] => 547
            [personId] => 2043
            [hidden] => 0
            [inserted] => 2017-08-16 19:46:32
        )
// END MARTIN COMMENT
*/

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$workOrder = new WorkOrder($workOrderId);

$workOrderDetails = array();
if (intval($workOrder->getWorkOrderId())) {    
    $wods = $workOrder->getWorkOrderDetails();    
    foreach ($wods as $wod) {
        // Martin commment: need to look at again for if using det rev id or det id
        // 
        // >>>00001 JM: if I understand correctly, that comment is Martin expressing doubt
        // about which ID from the details subsystem is actually used in workOrderDetails
        // (detailId or detailRevisionId). As of 2019-05, I certainly do NOT have
        // independent knowledge of the answer.
        $workOrderDetails[$wod['detailRevisionId']] = $wod;        
    }    
}

$material_1 = isset($_REQUEST['material_1']) ?$_REQUEST['material_1'] : '';
$component_1 = isset($_REQUEST['component_1']) ?$_REQUEST['component_1'] : '';
$function_1 = isset($_REQUEST['func_1']) ?$_REQUEST['func_1'] : '';
$force_1 = isset($_REQUEST['force_1']) ?$_REQUEST['force_1'] : '';

$material_2 = isset($_REQUEST['material_2']) ?$_REQUEST['material_2'] : '';
$component_2 = isset($_REQUEST['component_2']) ?$_REQUEST['component_2'] : '';
$function_2 = isset($_REQUEST['func_2']) ?$_REQUEST['func_2'] : '';
$force_2 = isset($_REQUEST['force_2']) ?$_REQUEST['force_2'] : '';

$material_3 = isset($_REQUEST['material_3']) ?$_REQUEST['material_3'] : '';
$component_3 = isset($_REQUEST['component_3']) ?$_REQUEST['component_3'] : '';
$function_3 = isset($_REQUEST['func_3']) ?$_REQUEST['func_3'] : '';
$force_3 = isset($_REQUEST['force_3']) ?$_REQUEST['force_3'] : '';

$term = isset($_REQUEST['term']) ?$_REQUEST['term'] : '';

$params = array();
$params['act'] = 'searchdetailnewnew';
$params['material_1'] = $material_1;
$params['component_1'] = $component_1;
$params['function_1'] = $function_1;
$params['force_1'] = $force_1;
$params['material_2'] = $material_2;
$params['component_2'] = $component_2;
$params['function_2'] = $function_2;
$params['force_2'] = $force_2;
$params['material_3'] = $material_3;
$params['component_3'] = $component_3;
$params['function_3'] = $function_3;
$params['force_3'] = $force_3;
$params['term'] = $term;
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;

$url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);

/*
// JM believes the following comment refers to content in the Details subsystem,
//  not anything directly acccessed here.

// BEGIN MARTIN COMMENT
0
matchcount	3
detailRevisionId	718
detailId	802
name	"E"
dateBegin	"2017-02-22"
dateEnd	"2500-01-01"
code	"YNPKX"
caption	false
fullname	"LRW.E"
classifications
    0
    detailRevisionTypeItemId	"412"
    detailRevisionId	"718"
    detailMaterialId	"2"
    detailComponentId	"31"
    detailFunctionId	"0"
    detailForceId	"0"
    personId	"2043"
    inserted	"0000-00-00 00:00:00"
    detailMaterialName	"Wood"
    detailComponentName	"Shearwall"
    detailFunctionName	""
    detailForceName	""
    
 [0] => Array
                        (
                            [matchcount] => 3
                            [detailRevisionId] => 718
                            [detailId] => 802
                            [name] => E
                            [dateBegin] => 2017-02-22
                            [dateEnd] => 2500-01-01
                            [code] => YNPKX
                            [caption] => 
                            [fullname] => LRW.E
                            [classifications] => Array
                                (
                                    [0] => Array
                                        (
                                            [detailRevisionTypeItemId] => 412
                                            [detailRevisionId] => 718
                                            [detailMaterialId] => 2
                                            [detailComponentId] => 31
                                            [detailFunctionId] => 0
                                            [detailForceId] => 0
                                            [personId] => 2043
                                            [inserted] => 0000-00-00 00:00:00
                                            [detailMaterialName] => Wood
                                            [detailComponentName] => Shearwall
                                            [detailFunctionName] => 
                                            [detailForceName] => 
                                        )
    
// END MARTIN COMMENT
*/


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

if (isset($array['data'])) {
	if (is_array($array['data'])) {
		if (isset($array['data']['searchresults'])) {
			if (is_array($array['data']['searchresults'])) {
				$searchresults = $array['data']['searchresults'];
				//print_r($searchresults); // COMMENTED OUT BY MARTIN BEFORE 2019

        /*
        // JM believes the following comment refers to content in the Details subsystem,
        //  not anything directly acccessed here.
        
        // BEGIN MARTIN COMMENT
				     [0] => Array
        (
            [matchcount] => 3
            [detailRevisionId] => 718
            [detailId] => 802
            [name] => E
            [dateBegin] => 2017-02-22
            [dateEnd] => 2500-01-01
            [code] => YNPKX
            [caption] => 
            [fullname] => LRW.E
            [classifications] => Array
            
            // BEGIN MARTIN COMMENT
				 */
				
				foreach ($searchresults as $srkey => $searchresult) {
				    if (array_key_exists($searchresult['detailRevisionId'], $workOrderDetails )) {
				        $array['data']['searchresults'][$srkey]['exists'] = 1;
				    } else {
				        $array['data']['searchresults'][$srkey]['exists'] = 0;
				    }
				    
                    // Build URL to request PNG thumb for the listed detail from the Details API.
                    // This URL will call the Details API using the GET method.
                    // NOTE that we don't make that call now: that happens when someone uses this URI.
					$params = array();
					$params['act'] = 'detailthumb';
					$params['time'] = time();
					$params['keyId'] = DETAILS_HASH_KEYID;
					$params['fileId'] = $searchresult['detailRevisionId'];
					$url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);					
					$array['data']['searchresults'][$srkey]['pngurl'] = $url;

					// Similarly, URL to request PDF
					$params = array();
					$params['act'] = 'detailpdf';
					$params['time'] = time();
					$params['keyId'] = DETAILS_HASH_KEYID;
					$params['fileId'] = $searchresult['detailRevisionId'];					
					$url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);						
					$array['data']['searchresults'][$srkey]['pdfurl'] = $url;
				}
			}
		}
	}
}

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$data['results'] = $array['data'];
//print_r($array);
// END COMMENTED OUT BY MARTIN BEFORE 2019

header('Content-Type: application/json');
echo json_encode($array);
die();

?>