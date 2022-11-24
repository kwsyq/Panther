<?php
/*  flowroutemmscb.php

    EXECUTIVE SUMMARY: Callback from SMS system. Probably not appropriate that this is at top level.
    Decode JSON for incoming SMS, process accordingly. Appears to be for handling incoming media
    (graphical) content.
    
*/    

/*
OLD CODE removed 2019-02-01 JM
if (trim(`hostname`) == 'devssseng'){
	$_SERVER['REMOTE_ADDR'] = '52.88.246.140';
}
*/
// BEGIN NEW CODE 2019-02-01 JM
// >>>00013: this and other changes here need testing.
require_once dirname(__FILE__).'/inc/determine_environment.php';

if (environment() != ENVIRONMENT_PRODUCTION){
    // >>>00014 =>"Serious mystery": why are we faking up who made the request?
	$_SERVER['REMOTE_ADDR'] = HARDCODED_FLOWROUTE_IP_1; // JM moved the hardcoding to config.php 2019-02-01
}
// END NEW CODE 2019-02-01 JM

require_once './inc/config.php';

ini_set('display_errors',0);
error_reporting(0);

// RETURN true if INPUT $str is valid JSON.
function isValidJSON($str) {
	json_decode($str);
	return (json_last_error() == JSON_ERROR_NONE);
}

/*
OLD CODE removed 2019-02-01 JM
$ips = array('52.88.246.140','52.10.220.50');
*/
// BEGIN NEW CODE 2019-02-01 JM
$ips = array(HARDCODED_FLOWROUTE_IP_1, HARDCODED_FLOWROUTE_IP_2);
// END NEW CODE 2019-02-01 JM

if (in_array($_SERVER['REMOTE_ADDR'], $ips)) {
/*
OLD CODE removed 2019-02-01 JM
	if (trim(`hostname`) == 'devssseng'){
		$rawjson = '{"included": [{"attributes": {"url": "https://mms-media-prod.s3.amazonaws.com/942549551265177600-IMG_6735.jpg?AWSAccessKeyId=ASIAIYLCPWD47RN6SNCQ&Expires=1516148301&x-amz-security-token=FQoDYXdzEJf%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaDBOwDngdrN3y9kuamiKAAtC6836EYhcIOMY46U%2BwrAHLSqsoI3b0Gb3DSk6IUco%2FfYXRE3%2BIRIrD6jP2sV3%2BJV07ZQ9ogi94nYfcNhF97T%2FfI06pepsVsYR7c2lJQ5JWoOirXCpqbTZn6u5Mccu0YYv7fXCcukLHJRUPg8s3D7JkJaMDVCLry9k9Wi1vurLmn1iFRkqPK95Ok7lh4nDurS2nGHs%2F9ZahV8vN%2B9IhI5MoWzLjFRsO9aeg%2FqWO8diUl0nenBiAbh7POyzJNX7TORpt%2Feo2Z6L8TyumXvpA42rkvx3GrMSvIaAVoscyWDYE3QyesNczD85OtiMvYT4qfv7orVX1SOA5SJS9HS5Ijboo1Mjb0QU%3D&Signature=Oy3ZDwjoOciEjyNYVXlUmOzB7ZY%3D", "file_name": "IMG_6692.jpg", "mime_type": "image/jpeg", "file_size": 662315}, "type": "media", "id": "939305103458545664-IMG_6692.jpg", "links": {"self": "https://api.flowroute.com/v2.1/media/939305103458545664-IMG_6692.jpg"}}], "data": {"relationships": {"media": {"data": [{"type": "media", "id": "939305103458545664-IMG_6692.jpg"}]}}, "attributes": {"status": "", "body": "again message", "direction": "inbound", "amount_nanodollars": 9500000, "to": "14253126220", "message_encoding": 0, "timestamp": "2017-12-09T01:26:02.00Z", "delivery_receipts": [], "amount_display": "$0.0095", "from": "12066175403", "is_mms": true, "message_type": "longcode"}, "type": "message", "id": "mdr2-ec9f7b30dc7f11e786688e76b030dee9"}}';
*/
// BEGIN NEW CODE 2019-02-01 JM
	if (environment() != ENVIRONMENT_PRODUCTION){
	    $rawjson = FLOWROUTEMMSCB_TEST_JSON;
// END NEW CODE 2019-02-01 JM
	} else {	
		$rawjson = file_get_contents("php://input");
	}

	//	file_put_contents('/tmp/raw.txt',$rawjson);

    /*	
    If raw data from the request body is valid JSON for an associative array, then:
     * Decode it 
     * Grab the following elements as needed from the decoded associative array:
       * ['data']
           * ['attributes']
             * ['to']
             * ['from']
             * ['body']
             * ['is_mms']
           * ['relationships']
             * ['media']
               * ['data']
                * [] - numeric
                  * ['type']
                  * ['id']
           * ['id']
        * ['included']
          * [] - numeric
            * ['id']
            * ['attributes']
              * ['url']
              * ['file_name']
              * ['mime_type']
              * ['file_size']
     * If $json['data']['attributes']['to'] -- a phone number -- is a member of $smsNumbers in inc/config.php 
       (as of 2019-03, that's FLOWROUTE_SMS_DAMON and FLOWROUTE_SMS_FRONT_DOOR), then
       determine the appropriate class (as of 2019-03, that's always SMS_FlowRoute).
     * Pass relevant data to a constructor for that class to produce an object $sms. Data passed will be:
         * $json['data']['attributes']['to']
         * $json['data']['attributes']['from']
         * >>>00026 JM would expect $json['data']['attributes']['id'] but it looks like it gets
           $json['media']['data'][NN]['type']['id'] for the greatest such NN. Is this really right? 
         * $json['data']['attributes']['body']
         * 'in'
         * $mediaarray, array corresponding roughly to the content of $json['included'][]['attributes'], but
           with slightly differeng index names:
             * 'url' stays the same
             * 'filename' instead of 'file_name'
             * 'mimetype' instead of 'mime_type'
             * 'filesize' instead of 'file_size'
     * Call $sms->processInbound().
     
     >>>00012 probably several variables in this file could be better named.
    */
	if ((strlen($rawjson) > 0) && isValidJSON($rawjson)) {
		$json = json_decode($rawjson,true);		
		if (isset($json['data'])) {			
			$data = $json['data'];			
			if (is_array($data)) {
				$attributes = array();
				$relationships = array();
				
				if (isset($data['attributes'])) {
					$attributes = $data['attributes'];
				}
				
				if (isset($data['relationships'])) {
					$relationships = $data['relationships'];
				}
				
				$id = isset($data['id']) ? $data['id'] : '';

				$to = isset($attributes['to']) ? $attributes['to'] : 0;
				$from = isset($attributes['from']) ? $attributes['from'] : 0;
				$body = isset($attributes['body']) ? $attributes['body'] : '';
				$is_mms = isset($attributes['is_mms']) ? $attributes['is_mms'] : 0;
				
				$mediaarray = array();

				if (intval($is_mms)) { // [BEGIN MARTIN COMMENT]
				                       // could still prolly just check the relationships and skip the is_mms part .. but probably just more to the point this way
					                   // in fact original test did just that ... the is_mms check wss incorporated later
					                   // [END MARTIN COMMENT]
					if (isset($relationships['media'])) {						
						$included = array();
						$medias = array();
						
						if (isset($json['included'])) {							
							$included = $json['included'];							
							if (is_array($included)) {								
								foreach ($included as $include) {									
									$id = isset($include['id']) ? $include['id'] : ''; // >>>00012, >>>00026 at the very least, we
									                                        // shouldn't multiplex $id; JM suspects this is an actual
									                                        // bug, see note above.
									$medias[$id] = $include;									
								}								
							}
						}
						
						if (isset($relationships['media']['data'])) {
							$dat = $relationships['media']['data'];
							if (is_array($dat)) {
								foreach ($dat as $media) {									
									$mediatype = isset($media['type']) ? $media['type'] : '';
									$mediaid = isset($media['id']) ? $media['id'] : '';									
									if (isset($medias[$mediaid])) {										
										$url = '';
										$filename = '';
										$mimetype = '';
										$filesize = '';
										
										$med = $medias[$mediaid];										
										if (isset($med['attributes'])) {											
											$attr = $med['attributes'];											
											if (is_array($attr)) {												
												$url = isset($attr['url']) ? $attr['url'] : '';
												$filename = isset($attr['file_name']) ? $attr['file_name'] : '';
												$mimetype = isset($attr['mime_type']) ? $attr['mime_type'] : '';
												$filesize = isset($attr['file_size']) ? $attr['file_size'] : '';												
												$mediaarray[] = array(
														'url' => $url,
														'filename' => $filename,
														'mimetype' => $mimetype,
														'filesize' => $filesize
														);												
											}
										}
									}
								}
							}
						}
					}
				}
				
				// [Martin comment:] store here
				/* [BEGIN commented out by Martin before 2019]				
				$display = array(
						'id' => $id,
						'to' => $to,
						'from' => $from,
						'body' => $body,
						'mediaarray' => $mediaarray
					); 
			    [END commented out by Martin before 2019]		
				*/
				
				if (key_exists($to, $smsNumbers)) { // [Martin comment:] this array in config.php						
					$smsNumber = $smsNumbers[$to];				
					if (class_exists($smsNumber['class'])) {				
						$className = $smsNumber['class'];				
						$sms = new $className($to, $from, $id, $body, 'in', $mediaarray); // See discussion near top of file.				
						$sms->processInbound();
					}
				}				
				
				/* [BEGIN commented out by Martin before 2019]
				$fp = fopen('/tmp/mms.txt', 'w');
				fwrite($fp, print_r($display, TRUE));
				fclose($fp);
				[END commented out by Martin before 2019]
				*/
			}
		}
	} else {
		// [Martin comment:] problem with incoming json
	}
	http_response_code(200);
} else {
	http_response_code(404);
}

?>