<?php 
/*  flowsms.php

    EXECUTIVE SUMMARY: Just test code. Send an SMS reporting all open tickets. "to" & "from" are hardcoded. 
    Presumably called either from shell or by hand-entering a URL.
    As of 2019-03, prevented from really running by "die" at top.
*/
die();
require_once './inc/config.php'; // Added JM 2019-02-01

/*
OLD CODE removed 2019-02-01 JM
$from = '14253126220';
$to = '12067142475';
$username = '41470238';
$password = '31nA0oi3xXUdaZ4GjQt6QY3xCAI7gx5M';
*/
// BEGIN NEW CODE 2019-02-04 JM
$from = strval(FLOWROUTE_SMS_DAMON); // Damon's phone number 14253126220 forwards to ext. 701 (Damon Fleming)
$to = strval(CELL_PHONE_RON);
$username = FLOWROUTE_API_USER;
$password = FLOWROUTE_API_PASS;
// END NEW CODE 2019-02-04 JM

$data = array();
$data['to'] = $to;
$data['from'] = $from;
$body = "Periodic Report\n";
$body .= "Open Tickets 3\n";
$body .=  "Open Workorders 10\n";
$body .= "\n\n";
/* OLD CODE REMOVED 2019-02-15 JM
$body .= "http://ssseng.com/panther";
*/
// BEGIN NEW CODE 2019-02-15 JM
$body .= 'http://'.PRODUCTION_DOMAIN.'/panther';
// END NEW CODE 2019-02-15 JM

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

?>