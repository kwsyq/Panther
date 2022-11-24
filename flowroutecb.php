<?php
/*  flowroutecb.php

    EXECUTIVE SUMMARY: Callback from SMS system. Probably not appropriate that this is at top level.
    Decode JSON for incoming SMS, process accordingly.
*/

ini_set('display_errors',1);
error_reporting(-1);

/*
[BEGIN MARTIN COMMENT]
// providerId

create table inboundSms(
    inboundSmsId   int unsigned not null primary key auto_increment,
    smsProviderId  tinyint unsigned,
    didTo          bigint unsigned,
    didFrom        bigint unsigned,
    id             varchar(128),
    body           text,
    media          text,
    inserted       timestamp not null default now()
    );

create index ix_insms_provid on inboundSms (smsProviderId);
create index ix_insms_to on inboundSms (didTo);
create index ix_insms_from on inboundSms (didFrom);

[END MARTIN COMMENT]
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

if (in_array($_SERVER['REMOTE_ADDR'], $ips)){
/*
OLD CODE removed 2019-02-13 JM
    // JM 2019-02-11: Martin had made a change here on his system circa December 2019 without putting
    // it in production. I've now brought it into production, for what it's worth.
    // BEGIN PRODUCTION CODE
	if (trim(`hostname`) == 'devssseng'){
        $rawjson = '{"body": "open", "to": "14257781023", "from": "12066175403", "id": "mdr2-790f0c8cbf4f11e7a39f0e9698164806"}';
    // END PRODUCTION CODE
    // BEGIN MARTIN CODE
	if (trim(`hostname`) == 'devssseng'){
//		$rawjson = '{"body": "open", "to": "14257781023", "from": "12066175403", "id": "mdr2-790f0c8cbf4f11e7a39f0e9698164806"}';
		$rawjson = '{"body": "sou", "to": "14257781023", "from": "12532858197", "id": "mdr2-c3f86280d65411e88a87feddb0f3394c"}';
    // END MARTIN CODE
*/
// BEGIN NEW CODE 2019-02-13 JM
	if (environment() != ENVIRONMENT_PRODUCTION) {
		$rawjson = '{"body": "sou", "to": "'.FLOWROUTE_SMS_FRONT_DOOR.'", "from": "'.
		    HARDCODED_FLOWROUTE_DEV_SENDER_1.'", "id": "'.HARDCODED_FLOWROUTE_ID_DEV.'"}';
// END NEW CODE 2019-02-13 JM
	} else {
		$rawjson = file_get_contents("php://input");
	}

    // JM 2019-02-11: Martin had made the following removal on his system circa December 2019 without putting
    // it in production. I've now brought it into production, for what it's worth.
    // BEGIN OLD CODE REMOVED
	// file_put_contents('/tmp/raw.txt',$rawjson);
	// END OLD CODE REMOVED
    /*	
    If raw data from the request body is valid JSON for an associative array, then:
     * Decode it 
     * Grab the following elements from the decoded associative array: 'to', 'from', 'id', 'body'. 
     * If the 'to' value -- a phone number -- is a member of $smsNumbers in inc/config.php 
       (as of 2019-03, that's FLOWROUTE_SMS_DAMON and FLOWROUTE_SMS_FRONT_DOOR), then
       determine the appropriate class (as of 2019-03, that's always SMS_FlowRoute).
     * Pass relevant data to a constructor for that class to produce an object $sms
     * Call $sms->processInbound().
    */
	if ((strlen($rawjson) > 0) && isValidJSON($rawjson)) {
		$data = json_decode($rawjson, true);

		/*
		[BEGIN commented out by Martin some time before 2019]
		$str = '';

		foreach ($data as $key => $value){
			if (strlen($str)){
				$str .= "\n";
			}
			$str .= $key . " = " . $value;
		}

		file_put_contents('/tmp/flow.txt', $str);
		[END commented out by Martin some time before 2019]
		*/

		$to = isset($data['to']) ? $data['to'] : 0;
		$from = isset($data['from']) ? $data['from'] : 0;
		$id = isset($data['id']) ? $data['id'] : '';
		$body = isset($data['body']) ? $data['body'] : '';


		if (key_exists($to, $smsNumbers)) { // [Martin comment:] this array in config.php
			$smsNumber = $smsNumbers[$to];
			if (class_exists($smsNumber['class'])) {
				$className = $smsNumber['class'];
				$sms = new $className($to, $from, $id, $body, 'in',array(),$customer);
				$sms->processInbound();
			}
		}

		/*
		[BEGIN commented out by Martin some time before 2019]

		$didTo = isset($data['to']) ? $data['to'] : 0;
		$didFrom = isset($data['from']) ? $data['from'] : 0;
		$id = isset($data['id']) ? $data['id'] : '';
		$body = isset($data['body']) ? $data['body'] : '';

		$didTo = preg_replace("/[^0-9]/", "", $didTo);
		$didFrom = preg_replace("/[^0-9]/", "", $didFrom);

		$id = trim($id);
		$id = substr($id, 0, 128);

		$body = trim($body);
		$body = substr($body, 0, 1024);

		$db = DB::getInstance();

		$query = " insert into  " . DB__NEW_DATABASE . ".inboundSms (smsProviderId,didTo,didFrom,id,body) values (";
		$query .= " " . intval(SMSPROVIDERID_FLOWROUTE) . " ";
		$query .= " ," . $db->real_escape_string($didTo) . " ";
		$query .= " ," . $db->real_escape_string($didFrom) . " ";
		$query .= " ,'" . $db->real_escape_string($id) . "' ";
		$query .= " ,'" . $db->real_escape_string($body) . "') ";

		$db->query($query);


		if (strtoupper($body) == 'STAT'){

		    // JM 2019-02-04: even though this is commented out, using new variables from config.php
		    // BEGIN OLD CODE
			$from = '14253126220';
			$to = '12067142475';
			$username = '41470238';
			$password = '31nA0oi3xXUdaZ4GjQt6QY3xCAI7gx5M';
			// END OLD CODE
			// BEGIN NEW CODE 2019-02-04 JM
            $from = strval(FLOWROUTE_SMS_DAMON); // Damon's phone number 14253126220 forwards to ext. 701 (Damon Fleming)
            $to = strval(CELL_PHONE_RON);
            $username = FLOWROUTE_API_USER;
            $password = FLOWROUTE_API_PASS;
			// END NEW CODE 2019-02-04 JM

			$data = array();
			$data['to'] = $didFrom;
			$data['from'] = $didTo;
			$body = "STATS\n";
			$body .= "nothing here yet !!!\n";
			$body .= "\n\n";

			$data['body'] = $body;

			$data_string = json_encode($data);

			$ch = curl_init('https://api.flowroute.com/v2/messages');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($data_string))
			);

			$result = curl_exec($ch);

		}


		if (strtoupper($body) == 'PING'){

			$from = '14253126220';
			$to = '12067142475';
			$username = '41470238';
			$password = '31nA0oi3xXUdaZ4GjQt6QY3xCAI7gx5M';

			$data = array();
			$data['to'] = $didFrom;
			$data['from'] = $didTo;
			$body = "PONG\n";
			$body .= "\n\n";

			$data['body'] = $body;

			$data_string = json_encode($data);

			$ch = curl_init('https://api.flowroute.com/v2/messages');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($data_string))
			);

			$result = curl_exec($ch);

		}

		[END commented out by Martin some time before 2019]
		*/

	} else {
		// [Martin comment:] problem with incoming json
		
		// >>>00002 so log that!
	}
	http_response_code(200);
} else {
	http_response_code(404);
}

?>
