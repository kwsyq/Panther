<?php 
/*  credrec_upload.php

    EXECUTIVE SUMMARY: upload file associated with a creditRecord.
    Despite being top-level this is not a web page. This is called by a dropzone action in multi.php.
    User must have admin-level payment permissions.
    
    PRIMARY INPUT: $_FILES['file']: Allowed extensions: 'png','jpg','jpeg','pdf'. 
    See http://php.net/manual/en/reserved.variables.files.php for more on $_FILES.
    
    Logging added JM 2020-02-20.
    >>>00002 Probably more cases should return a 403 or some such. We can't use our usual AJAX return approach, because this is more like a form submission.

*/

require_once './inc/config.php';
require_once './inc/perms.php';

//User must have admin-level payment permissions; otherwise, dies immediately.
$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm){
    $logger->error2('1584987802', "Attempted access by someone who lacks Admin-level payments permission: userId" . 
        ($user ? ('='.$user->getUserId()) : ' unavailable'));  
    die();
}

$db = DB::getInstance();    

// INPUT $user: User object.
// Insert a row in DB table CreditRecord with personId matching $user.
// On success, RETURN the primary-key Id of inserted row.
// Otherwise returns 0.
function makeEntry($user) {
    global $db, $logger;
    $query = "INSERT INTO " . DB__NEW_DATABASE . ".creditRecord(personId) VALUES (";
    $query .= intval($user->getUserId()) . ");"; // personId
    $result = $db->query($query);
    
    if (!$result) {
        $logger->errorDb('1582217840', 'Failed to insert creditRecord', $db);  
    } else {
        $id = $db->insert_id;    
        if (intval($id)) {
            $logger->info2('1582217845', "Created new creditRecord, creditRecordId=$id");
            return $id;  // THIS IS THE RETURN ON SUCCESS
        } else {
            $logger->errorDb('1582217846', 'Invalid creditRecordId on new insert', $db);  
        }
    }
    
    return 0;    
}

//$sizeLimit = 2097152; // REMOVED 2020-03-23 JM: it is set again before it is ever used.

// [Martin comment:] get this in a more central place and more definitive perhaps
$allowedExtensions = array('png', 'jpg', 'jpeg', 'pdf');

// Before even looking at $_FILES['file'], calls own function makeEntry to insert a row 
//  in DB table creditRecord with personId matching current user.
//  NOTE that we make this DB entry even if something fails subsequently; e.g. we do this before we test file size.
// >>>00032: may want to rethink that.
// [Martin comment:] this function is replicated across these upload php files.  needs to move to a good spot
$id = makeEntry($user);

if (!intval($id)) {  
    // makeEntry failed
    $logger->error2('1582217865', "Invalid creditRecordId $id");
} else {
    // BEGIN ADDED 2020-08-12 JM as part of dealing with http://bt.dev2.ssseng.com/view.php?id=207 ( Wrong behavior when creditRecordIds clash)
    // This (and other code below related to this variable) wasn't directly called for by that bug report, but it's a similar issue.
    $delete_row = false;
    // END ADDED 2020-08-12 JM
    
    // Use last digit $tail of creditRecordId to select a directory ssseng_documents/uploaded_checks/$tail as the destination for the upload.
    $tail = substr($id, -1);
	$saveDir = '../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/' . $tail . '/';
    
    $str = "";
    $sizeLimit = 2097152;

    if (!file_exists($saveDir)) {
        $logger->info2('1582217872', "Attempting to create new directory '$saveDir'");
        @mkdir($saveDir); // >>>00026: 2020-02-20: Our experience on dev2 is that this is failing. Might be worth studying,
                          // but as long as we prebuild the directories in production (or anywhere else we care about!) this is not 
                          // a big problem. 
    }
    
    if (!file_exists($saveDir)){
        $logger->error2('1582217874', "Directory '$saveDir' does not exist");
        $delete_row = true; // ADDED 2020-08-12 JM
    } else if (!is_writable($saveDir)) {
        $logger->error2('1582217876', "Directory '$saveDir' not writable");
        $delete_row = true; // ADDED 2020-08-12 JM
    } else {
        if ( ! (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) ) {            
            if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {                
                if ($_FILES['file']['size'] <= $sizeLimit) {
                    /*
                    File will be saved to ssseng_documents/uploaded_checks/$tail/creditRecordId.$ext (where $ext is one of 'png','jpg','jpeg','pdf'). 
                    If file already exists (shouldn't ever happen, would be reuse of same creditRecordId), return a '403 Forbidden' HTTP header, 
                      echo "That File Already Exists", and die. 
                    Otherwise, put the file where it belongs and update the filename in the creditRecord (value is $tail/creditRecordId.$ext, e.g. '3/98743.jpg') 
                      and return a '200 OK' HTTP header.
                    */
                    // [BEGIN Martin comment]     
                    //and maybe dispense with this
                    // would imagine there will be files much larger than this
                    // so need to adjust configs
                    // [END Martin comment]
                    // JM: I think Martin's comment referred to $sizeLimit
                    $parts = explode(".", $_FILES['file']['name']);
                    
                    if (count($parts)) { 
                        $ext = strtolower(end($parts));                        
                        $fileName = $id . "." . $ext; //$_FILES['file']['name'];
                        $saveName = sprintf('%s%s', $saveDir, $fileName);            
                        if (in_array($ext, $allowedExtensions)) {                        
                            if (file_exists($saveName)) {
                                $logger->error2('1582217880', "Attempted to overwrite $saveName");
                                header('HTTP/1.0 403 Forbidden');
                                // BEGIN REPLACED 2020-08-12 JM
                                // echo "That File Already Exists";
                                // END REPLACED 2020-08-12 JM
                                // BEGIN REPLACEMENT 2020-08-12 JM to deal with http://bt.dev2.ssseng.com/view.php?id=207 ( Wrong behavior when creditRecordIds clash)
                                // We handle deletion of the row a bit differently here because this gets its own separate "Forbidden" return.
                                echo "File [$saveName] already exists; it may have been imported to this system without a corresponding database entry.";
                                $query = "DELETE FROM " . DB__NEW_DATABASE . ".creditRecord ";
                                $query .= "WHERE creditRecordId = " . intval($id) . ";";
                                $result = $db->query($query);
                                if (!$result) {
                                    $logger->errorDb('1597249382', "Failed to delete a creditRecord, creditRecordId = " . intval($id) . " after filename conflict", $db);
                                    // not really anything we can do besides report that error.
                                }
                                // END REPLACEMENT 2020-08-12 JM                                
                                die();
                            } else {                            
                                if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {                               
                                    $query = "UPDATE " . DB__NEW_DATABASE . ".creditRecord ";                                    
                                    $query .= "SET fileName = '" . $db->real_escape_string($tail . '/' . $fileName) . "' ";
                                    $query .= "WHERE creditRecordId = " . intval($id) . ";";
                                    
                                    $result = $db->query($query);
                                    
                                    if ($result) {
                                        header('HTTP/1.0 200 OK'); // SUCCESS!
                                        die();
                                    } else {
                                        $logger->errorDb('1582217890', 
                                            "Failed to update filename for newly uploaded creditRecord, creditRecordId = " . intval($id) . 
                                                " filename = '" . $db->real_escape_string($tail . '/' . $fileName) . "'",
                                            $db);
                                        // Leave creditRecord row alone, there's a chance a developer can fix it.
                                    }
                                } else {
                                    $logger->error2('1582217895', "Failed move uploaded file from '{$_FILES['file']['tmp_name']}' to '{$saveName}'");
                                    // Leave creditRecord row alone, there's a chance a developer can fix it.
                                }
                            }
                        } else {
                            $logger->error2('1582217910', "'{$saveName}' has invalid extension '$ext'; not saving creditRecord, creditRecordId = " . intval($id));
                            $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
                        }                            
                    } else {
                        $logger->error2('1582217916', "Explode on '{$_FILES['file']['name']}' failed to find an extension; not saving creditRecord, creditRecordId = " . intval($id));
                        $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
                    }
                } else {
                    $logger->error2('1582217919', "Uploaded file too big, limit $sizeLimit bytes, had {$_FILES['file']['size']}; not saving creditRecord, creditRecordId = " . intval($id));
                    $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
                }
            } else {
                $logger->error2('1582217922', 'File upload error ' . $_FILES['file']['error'] . "; not saving creditRecord, creditRecordId = " . intval($id));
                $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
            }
        } else {
            $logger->error2('1582217924', '$_FILES[\'file\'][\'error\'] not set or is array' . "; not saving creditRecord, creditRecordId = " . intval($id));
            $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
        }
        // BEGIN ADDED 2020-08-12 JM as part of dealing with http://bt.dev2.ssseng.com/view.php?id=207 ( Wrong behavior when creditRecordIds clash)
        if ($delete_row) {
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".creditRecord ";
            $query .= "WHERE creditRecordId = " . intval($id) . ";";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1597249696', "Failed to delete a creditRecord, creditRecordId = " . intval($id) . " after error", $db);
                // not really anything we can do besides report that error.
            }
      }
        // END ADDED 2020-08-12 JM
    } // END else: '$saveDir' is writable 
}
die();
?>