<?php
/* _admin/ajax/vimeovideos.php

    Unfinished, Martin said "don't worry about it" as of 2018-10. 
    >>>00006 Sets up a variable $return, but never uses it. 
    Makes an API call to https://api.vimeo.com, gets back a response, parses it a bit, 
    but doesn't seem to pass anything back to the caller. 
    
    >>>00032 I (JM) don't think this is called anywhere, but don't trash it, we'll probably eventually
    want to flesh it out.
*/

function isValidJSON($str) {
    json_decode($str);
    return (json_last_error() == JSON_ERROR_NONE);
}

include '../../inc/config.php';
include '../../inc/access.php';

$return = array();
$return['status'] = 'fail';

$videos = array();

/*
OLD CODE removed 2019-05-13 JM
$headers = array('Authorization: Bearer 39cd40233c0ec07a3894224b5af9d679');
*/
// BEGIN NEW CODE 2019-05-13 JM
$headers = array(VIMEO_AUTHORIZATION);
// END NEW CODE 2019-05-13 JM

$next = '/me/videos?per_page=100';

do {    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.vimeo.com' . $next);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $next = '';
    
    if ((strlen($response) > 0) && isValidJSON($response)) {    
        $json = json_decode($response,true);    
        if (isset($json['paging'])) {
            $paging = $json['paging'];
            if (is_array($paging)){
                if (isset($paging['next'])) {
                    $next = $paging['next'];
                }
            }
        }
        
        if (isset($json['data'])){
            $data = $json['data'];
            if (is_array($data)){
                foreach ($data as $video) { 
                    $videos[] = $video;					
                }
            }
        }    
    }
    
} while (strlen($next));

?>