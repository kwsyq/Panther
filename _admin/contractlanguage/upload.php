<?php 
/* _admin/contractlanguage/upload.php

    EXECUTIVE SUMMARY: Uploads contract language PDF files.
    
    INPUT $_FILES['file']. See http://php.net/manual/en/reserved.variables.files.php for more on $_FILES
    $_REQUEST['displayName'] optionally allows a display name to override the uploaded file name.

    Requires 'pdf' extension but does not validate file content; 
    can add characters to the first part of the file name to prevent conflict with an existing file; 
    makes appropriate entry in DB table contractLanguage.
    
    If $_REQUEST['type'] not empty, the new type for the specific contract language is added.
    If $_REQUEST['type'] has value 'NEW", a new type is creaded, based on the pdf file text in parenthesis.
    
    RETURN: 
        * On success, redirects to index.php. 
        * On failure ( relevant folder doesn't exist and can't be created, or isn't writable), returns a 403 with an appropriate message.
*/

include '../../inc/config.php';


$sizeLimit = 2097152; // Martin comment: deal with this at some point  sync back and front end and web server
                      // JM: that's 2MB.

// Martin comment: get this in a more central place and more definitive perhaps
$allowedExtensions = array('pdf');

/*
OLD CODE removed 2019-02-06 JM
$sep = DIRECTORY_SEPARATOR;
$fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '../ssseng_documents/contract_language' . $sep;
*/
// BEGIN NEW CODE 2019-02-06 JM
$fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/contract_language/';
// END NEW CODE 2019-02-06 JM	
                        
if (!file_exists($fileDir)) {
    @mkdir($fileDir);
}

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$jobDir = $fileDir . intval($jobId) . $sep;
//
//	if (!file_exists($jobDir)){
//	@mkdir($jobDir);
//	}
// END COMMENTED OUT BY MARTIN BEFORE 2019

if (!file_exists($fileDir)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Save Dir Doesn't Exist";
    die();
}
    
if (!is_writable($fileDir)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Can't Write To Save Dir";
    die();
}

$success = false;

$displayName = isset($_REQUEST['displayName']) ? $_REQUEST['displayName'] : '';
$displayName = trim($displayName);
$displayName = substr($displayName, 0, 255); // >>>00002 Truncates silently (though the number is pretty generous)

$type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : ""; // contractLanguage Type
$type = strtoupper($type);

$t = $firstpart = $ext = "";

// if (!(!isset($_FILES['file']['error']) || is_array($_FILES['file']['error']))) { // REPLACED 2020-02-25 JM
if (isset($_FILES['file']['error']) && !is_array($_FILES['file']['error'])) {      // REPLACEMENT 2020-02-25 JM
    if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {            
        if ($_FILES['file']['size'] <= $sizeLimit) {
            // BEGIN Martin comment
            //and maybe dispense with this [JM: I presume "this" is the sizeLimit test]
            // would imagine there will be files much larger than this
            // so need to adjust configs
            // END Martin comment
            
            $fileName = $_FILES['file']['name'];
            
            $parts = explode(".", $fileName);

            $firstpart = implode(".", array_slice($parts, 0, count($parts) - 1));
                
            if (count($parts)) {
                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                //////////
                /*
                if (is_dir($fileDir)){
                    if ($dh = opendir($fileDir)){
                        while (($file = readdir($dh)) !== false){
                            if (is_file($fileDir . $file)){
                                @unlink($fileDir . $file);

                            }
                        }
                        closedir($dh);
                    }
                }
                */
                //////////
                // END COMMENTED OUT BY MARTIN BEFORE 2019

                $ext = strtolower(end($parts)); // extension

                if (in_array($ext, $allowedExtensions)) {                        
                    $saveName = sprintf('%s%s', $fileDir, $firstpart . '.' . $ext);                        
                    while (file_exists($saveName)) {
                        // Use time to avoid conflicting filename
                        /* BEGIN REPLACED 2020-02-25 JM
                        $t = time();
                        $saveName = sprintf('%s%s', $fileDir, $firstpart . '.' . $t . '.' . $ext);
                        // END REPLACED 2020-02-25 JM
                        */
                        // BEGIN REPLACEMENT 2020-02-25 JM
                        $t = '.' . time();
                        $saveName = sprintf('%s%s', $fileDir, $firstpart . $t . '.' . $ext);
                        // END REPLACEMENT 2020-02-25 JM
                    }

                    // BEGIN Martin comment
                    // deal with later
                    // the potential disconnect between
                    // filsesytem naming and the names in the database;
                    // END Martin comment

                    if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {
                        /* BEGIN NO LONGER NEEDED BECAUSE OF REPLACEMENT ABOVE 2020-02-25 JM
                        if (strlen($t)) {
                            $t = '.' . $t;
                        } else {
                            $t = '';
                        }
                        // END NO LONGER NEEDED BECAUSE OF REPLACEMENT ABOVE 2020-02-25 JM
                        */
                        
                        if (!strlen($displayName)) {
                            $displayName = $firstpart . $t . '.' . $ext;
                        }

                        // new categ
                        $typeToGet = $firstpart . $t . '.' . $ext;
                        if($type=="NEW") {
                            $stringType = explode(')', (explode('(', $typeToGet)[1]))[0];
                        
                            $contractLanguages = getContractLanguageFiles();
                            $contractLanguagesTypes = [];
                            $contractLanguagesTypes = array_unique(array_column($contractLanguages, 'type'));

                            $type = $stringType;
                        }
                      
                     
                        
                        $db = DB::getInstance();

                        $query = " UPDATE " . DB__NEW_DATABASE . ".contractLanguage ";
                        $query .= " SET status = 0 ";
                        $query .= " WHERE type = '" . $db->real_escape_string($type) . "' ";
                
                        $result = $db->query($query);
                
                        if (!$result) {
                            $errorId = '637813135963201192';
                            $error= 'We could not update the Contract Language Status. Database error. Error id: ' . $errorId;
                            $logger->errorDb($errorId, 'We could not update the Contract Language Status', $db);
                        } 

                        if(!$error) {

                            $status = 1; // default Active
                        
                            $query = " insert into " . DB__NEW_DATABASE . ".contractLanguage(fileName, displayName, type, status) values (";
                            $query .= " '" . $db->real_escape_string($firstpart . $t . '.' . $ext) . "' ";					    
                            $query .= " ,'" . $db->real_escape_string($displayName) . "' ";
                            $query .= " ,'" . $db->real_escape_string($type) . "' ";
                            $query .= " ," . intval($status) . ") ";
    
    
                            //$db->query($query); // >>>00002 ignores failure on DB query!

                            $result = $db->query($query);
                
                            if (!$result) {
                                $errorId = '637813136930740944';
                                $error= 'We could not update the Contract Language Status. Database error. Error id: ' . $errorId;
                                $logger->errorDb($errorId, 'We could not update the Contract Language Status', $db);
                            } 
    
                            if(!$error) {
                                $success = true;
                            }
                        }
                      
                        
                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                        //$result = insertFile($fileName, $saveName, $jobId, $USR_ID, $connect);
                        //if (!$result){
                        //		@unlink($saveName);
                        //	} else {
                        //		$success = true;
                        //	}
                        // END COMMENTED OUT BY MARTIN BEFORE 2019
                    }
                }
            }
        }
    }
}
    
if (!$success){
    header('HTTP/1.0 403 Forbidden');
    echo "seems to be a general problem with the upload";
} else {
    header("Location: index.php");
}
die();					
?>