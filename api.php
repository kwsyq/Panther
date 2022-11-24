<?php
/*  api.php

    EXECUTIVE SUMMARY: Hook for external calls from apps outside of the system, used externally. 
    
    INPUT: $_REQUEST['action'] (NOTE this is not the common $_REQUEST['act]); also concerned with user credentials. 
*/

require_once './inc/config.php';

$api = API::authenticate(); // This implicitly uses $_REQUEST['action'] and user credentials to build
                            // an appropriate API object that can run 'task_' . $_REQUEST['action'].
                            // E.g. if $_REQUEST['action'] == 'checkPhoneNumber', this will return
                            // an object of type task_checkPhoneNumber, based on /inc/classes/API/task_checkPhoneNumber.php
$ret['status'] = 'fail';
if ($api) {
    // Arrive here means the inputs were good.
    
    // Calling the 'run' method should set the $api object's own $data value. 
	$api->run();
	
	//header('Content-Type: application/json'); // Commented out by Martin some time before 2019.
	
	if ($api->getStatus() == 'success') {
	    // 'getData' method fetch $api's data value
		$data = $api->getData();
		
		// If there are explicit headers, use them, otherwise we default to 'Content-Type: application/json'
		if (isset($data['headers'])) {			
			$headers = $data['headers'];
			unset($data['headers']);			
			foreach ($headers as $hkey => $header){
				header($header);
			}			
		} else {			
			header('Content-Type: application/json');
		}
		
		$type = '';
		if (isset($data['type'])) {
			$type = $data['type'];	
		}
		
		if ($type == 'download') {
			// effectively return a file
			readfile($data['downloadfile']);
			die();			
		} else {
			// effectively return data to be handled at a higher level
			$ret['status'] = 'success';
			$ret['data'] = $api->getData();			
		}
	} else {
	    // Failure, return that as JSON.
		header('Content-Type: application/json');
		$ret['status'] = 'fail';
		// [Martin comment] put the errors here too		
	}
	
	echo trim(json_encode($ret));
	
	die();	
}

// Some problem with inputs, show a 404 page
do404();

?>