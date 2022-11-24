<?php
/*  manager/phoneapi.php

    EXECUTIVE SUMMARY: This does either of two things:
       * if ($act == 'ip') then return the IP address associated with the specified phone extension.
         RETURN: IP address encoded in a JSON structure: $data['computerIp']. If $_REQUEST['extension'] 
         is not a known, associated extension, then returns JSON-encoded empty object.  
       * Otherwise RETURN JSON-encoded object with two elements: 
         * realName, a persons's name in "firstName lastName" format
         * extraNotes, the first associated email address (if any) for that person, blank if no email address.
       
    PRIMARY INPUT: $_REQUEST['key']: must match PHONEAPI_KEY in inc/config.php 

    Optional INPUT: There are basically two cases here:    
        * $_REQUEST['act'] is present and its value is 'ip'
          * Requires additional input $_REQUEST['extension'], must match PHONEAPI_EXT in inc/config.php 
        * Otherwise, requires $_REQUEST['number'].(700) 
          * Should be 10-digit NADS (North American Dialing System) phone number
            If it is 11-digits, then the first digit must be '1' & will be thrown away
*/

include '../inc/config.php';


$db = DB::getInstance();

$key = isset($_REQUEST['key']) ? $_REQUEST['key'] : '';

/*
OLD CODE removed 2019-02-05 JM
if ($key != 'dhfswerywueirywRR55esdC'){
    die();
}
*/
// BEGIN NEW CODE 2019-02-05 JM
if ($key != PHONEAPI_KEY) {
    die();
}
// END NEW CODE 2019-02-05 JM

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$query = " describe ascPhone  ";
$phones = array();
if ($result = $db->query($query)) {
    if ($result->num_rows > 0){
        while ($row = $result->fetch_assoc()){
            $phones[] = $row;
        }
    }
}

print_R($phones);
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

if ($act == 'ip') {    
    $extension = isset($_REQUEST['extension']) ? $_REQUEST['extension'] : '';
    
/*
OLD CODE removed 2019-02-05 JM
    if ($extension == '700'){
        $data['computerIp'] = '192.168.70.179';
    }
*/
// BEGIN NEW CODE 2019-02-05 JM
    if ($extension == strval(PHONEAPI_EXT)) {
        $data['computerIp'] = PHONEAPI_IP_ADDRESS;
    }
// END NEW CODE 2019-02-05 JM	
    
} else {
    $number = isset($_REQUEST['number']) ? $_REQUEST['number'] : '';    
    $number = trim($number);
    
    if (strlen($number) == 11) {
        // >>>00002, >>>00016 should make sure the thrown-away leading digit is a '1'
        $number = substr($number, 1);
    }
    
    if (strlen($number) == 10) {
        // Find persons who match this phone number
        $query = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".personPhone pp  ";
        $query .= " join  " . DB__NEW_DATABASE . ".person p on pp.personId = p.personId ";
        $query .= " where pp.phoneNumber = '"  . $db->real_escape_string($number) . "' "; 
    
        $phones = array();
    
        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $p = new Person($row['personId']);
                    $row['realName'] = $p->getFormattedName(1);
                    $emails = $p->getEmails();
                    if (count($emails)){
                        $row['extraNotes'] = $emails[0]['emailAddress'];
                    } else {
                        $row['extraNotes'] = '';
                    }
                    $phones[] = $row;
                }
            }
        }  // >>>00002 ignores failure on DB query!                         
    } // END if (strlen($number) == 10)
    //  >>>00002 else should probably log invalid phone #
    
    $data = array();
    
    $data['realName'] = '';
    $data['extraNotes'] = '';

    // >>>00001 JM: Kind of odd here here if there is more than one row in $phones: each time through this loop, we
    //  overwrite $data['realName'] and $data['extraNotes'], so only the last one matters! If that is the intent
    //  why not write $data['realName'] = $phones[count($phones)-1]['realName']; I suspect this wasn't thought through
    //  but it's working "well enough" so it was never fixed.
    foreach ($phones as $phone) {
        $data['realName'] = $phone['realName'];
        $data['extraNotes'] = $phone['extraNotes'];        
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>