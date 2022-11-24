<?php
/* _admin/creditrecord/upload.php

    EXECUTIVE SUMMARY: Makes a new row in DB table CreditRecord for current logged-in user
        Uploads a new credit record file (e.g. copy of a check) and associates it with that creditRecord.
        >>>00028 JM: I'd expect this to be transactional, or at least wait until the file is uploaded to
        make a row in DB table CreditRecord. As it stands, we can create a new row in DB table CreditRecord
        but then fail on the file upload (e.g. name conflict) or can upload the file successfully, but fail 
        on attaching it to the row in DB table CreditRecord and not unwind that at all. 
    
    INPUT $_FILES['file']. See http://php.net/manual/en/reserved.variables.files.php for more on $_FILES

    Requires 'png', 'jpg', 'jpeg', or 'pdf'' extension but does not validate file content; 
        gives a 403 on conflict with an existing file. 
        updates appropriate row in DB table creditRecord. 
    
    RETURN: 
        * On success returns a '200 OK'; 
        * On failure ( relevant folder doesn't exist and can't be created, or isn't writable), returns a 403 with an appropriate message.

*/

include '../../inc/config.php';
//include '../../inc/access.php'; // Commented out by Martin before 2019

$db = DB::getInstance();

// INPUT $user: personId of current logged-in user
// RETURNs creditRecordId of inserted row, or 0 for failure.
function makeEntry($user) {
    global $db;
    
    $query = " insert into " . DB__NEW_DATABASE . ".creditRecord(personId) values(";
    $query .= "  " . intval($user->getUserId()) . " )"; // Martin comment: person id    
    $db->query($query); // >>>00002 ignores failure on DB query!
    
    $id = $db->insert_id;
    
    if (intval($id)) {
        return $id;
    }
    
    return 0;    
}

$sizeLimit = 2097152; // Martin comment: deal with this at some point  sync back and front end and web server

// Martin comment: get this in a more central place and more definitive perhaps
$allowedExtensions = array('png','jpg','jpeg','pdf');

// Martin comment: this function is replicated across these upload php files.  needs to move to a good spot

$id = makeEntry($user);

if (!intval($id)) {    
    //$this->api_error("problem with making credit entry");  // Commented out by Martin before 2019    
} else {
    $tail = substr($id, -1); // get the last digit to split these among directories
    
    /* 
    OLD CODE removed 2019-02-06 JM
    $sep = DIRECTORY_SEPARATOR;
    $saveDir = '..' . $sep . '..' . $sep . '..' . $sep . 'ssseng_documents' . $sep . 'uploaded_checks' . $sep . $tail . $sep;
    */
    // BEGIN NEW CODE 2019-02-06 JM
    $saveDir = '../../../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/' . $tail . '/';
    // END NEW CODE 2019-02-06 JM	
    
    $str = "";    
    $sizeLimit = 2097152;  // 2MB
    
    /* 
    OLD CODE removed 2019-02-14 JM
    $sep = DIRECTORY_SEPARATOR;
    */
    
    if (!file_exists($saveDir)) {
        @mkdir($saveDir);
    }
    
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //if (!file_exists($this->saveDir)){
    //    $this->api_error->setError("Save Dir Doesnt Exist");
    //}
    // END COMMENTED OUT BY MARTIN BEFORE 2019
    
    if (!is_writable($saveDir)) {
        //$this->api_error->setError("Save Dir Not writable");  // Commented out by Martin before 2019
    } else {        
        // if (!(!isset($_FILES['file']['error']) || is_array($_FILES['file']['error']))) { // REPLACED 2020-02-25 JM            
        if (isset($_FILES['file']['error']) && !is_array($_FILES['file']['error'])) { // REPLACEMENT 2020-02-25 JM
            if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {                
                if ($_FILES['file']['size'] <= $sizeLimit) {
                    // BEGIN MARTIN COMMENT
                    //and maybe dispense with this
                    // would imagine there will be files much larger than this
                    // so need to adjust configs
                    // END MARTIN COMMENT
                    
                    $parts = explode(".", $_FILES['file']['name']);
                    
                    if (count($parts)) { 
                        $ext = strtolower(end($parts));
                        
                        $fileName = $id . "." . $ext; // Martin comment: $_FILES['file']['name'];
                        $saveName = sprintf('%s%s', $saveDir, $fileName);
                        
                        if (in_array($ext, $allowedExtensions)) {                        
                            if (file_exists($saveName)) {                                
                                header('HTTP/1.0 403 Forbidden');
                                echo "That File Already Exists";
                                die();                                
                            } else {                                
                                if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {                                    
                                    $query = " update " . DB__NEW_DATABASE . ".creditRecord ";
                                    /* 
                                    OLD CODE removed 2019-02-14 JM
                                    $query .= " set fileName = '" . $db->real_escape_string($tail . $sep . $fileName) . "' ";
                                    */
                                    // BEGIN NEW CODE 2019-02-14 JM
                                    $query .= " set fileName = '" . $db->real_escape_string($tail . '/' . $fileName) . "' ";
                                    // END NEW CODE 2019-02-14 JM
                                    $query .= " where creditRecordId = " . intval($id) . " ";
                                    
                                    $db->query($query); // >>>00002 ignores failure on DB query!
                                    
                                    header('HTTP/1.0 200 OK');
                                    die();
                                }                                
                            }                        
                        }                        
                    }                    
                }                
            }            
        }        
    }
}	
die();

?>