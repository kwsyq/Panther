<?php 
/*  workorderfile_upload.php

    EXECUTIVE SUMMARY: upload file associated with a workOrder.
    Despite being top-level this is not a web page. This is called by a dropzone action in invoice.php.
    User must have write-level invoice permissions.
    
    Added for v2020-4 JM.
    
    PRIMARY INPUTs: 
        $_FILES['file']: Allowed extensions: 'png','jpg','jpeg','pdf'. 
          See http://php.net/manual/en/reserved.variables.files.php for more on $_FILES.
        $_REQUEST['workOrderId']
    
    >>>00002 Probably more cases should return a 403 or some such. We can't use our usual AJAX return approach, because this is more like a form submission.
*/

require_once './inc/config.php';
require_once './inc/perms.php';

//User must have write-level invoice permissions; otherwise, dies immediately.
$checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_RW);
if (!$checkPerm){
    $logger->error2('1600100673', "Attempted access by someone who lacks Write-level invoice permission: userId" . 
        ($user ? ('='.$user->getUserId()) : ' unavailable'));  
    die();
}

if ( ! array_key_exists('workOrderId',$_REQUEST) ) {
    $logger->error2('1600100735', "Missing workOrderId");  
    die();
}

$workOrderId = intval($_REQUEST['workOrderId']);
if (!WorkOrder::validate($workOrderId)) {
    $logger->error2('1600100777', "Invalid workOrderId " . $workOrderId);  
    die();
}

$db = DB::getInstance();

// INPUT $user: User object.
// Insert a row in DB table workOrderFile with personId matching $user.
// On success, RETURN the primary-key Id of inserted row.
// Otherwise returns 0.
function makeEntry($workOrderId, $user) {
    global $db, $logger;
    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderFile(workOrderId, personId) VALUES (";
    $query .= $workOrderId;
    $query .= ", " . intval($user->getUserId()) . ");"; // personId
    $result = $db->query($query);
    
    if (!$result) {
        $logger->errorDb('', 'Failed to insert workOrderFile', $db);  
    } else {
        $id = $db->insert_id;    
        if (intval($id)) {
            $logger->info2('1600101086', "Created new workOrderFile, workOrderFileId=$id");
            return $id;  // THIS IS THE RETURN ON SUCCESS
        } else {
            $logger->errorDb('1600101110', 'Invalid workOrderFileId on new insert: $id', $db);  
        }
    }
    
    return 0;    
}

$allowedExtensions = array('png', 'jpg', 'jpeg', 'pdf');

// Before even looking at $_FILES['file'], calls own function makeEntry to insert a row 
//  in DB table workOrderFile with personId matching current user.
//  NOTE that we make this DB entry even if something fails subsequently; e.g. we do this before we test file size.
$id = makeEntry($workOrderId, $user);

if (!$id) {  
    // makeEntry failed, error is already reported
    die();
} else {
    $delete_row = false;
    
    // Use last digit $tail of workOrderFileId to select a directory ssseng_documents/uploaded_checks/$tail as the destination for the upload.
    $tail = substr($id, -1);
	$saveDir = '../' . CUSTOMER_DOCUMENTS . '/uploaded_wo_files/' . $tail . '/';
    
    $str = "";
    $sizeLimit = 47185920; // 45MB

    if (!file_exists($saveDir)) {
        $logger->info2('1600102066', "Attempting to create new directory '$saveDir'");
        @mkdir($saveDir); // >>>00026: Our experience on dev2 is that this is failing. Might be worth studying,
                          // but as long as we prebuild the directories in production (or anywhere else we care about!) this is  
                          // not a big problem. 
    }
    
    if (!file_exists($saveDir)) {
        $logger->error2('1600102074', "Directory '$saveDir' does not exist");
        $delete_row = true;
    } else if (!is_writable($saveDir)) {
        $logger->error2('1600102076', "Directory '$saveDir' not writable");
        $delete_row = true;
    } else {
        if ( ! (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) ) {            
            if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {                
                if ($_FILES['file']['size'] <= $sizeLimit) {
                    /*
                    File will be saved to ssseng_documents/uploaded_wo_files/$tail/$workOrderId.$ext (where $ext is one of 'png','jpg','jpeg','pdf'). 
                    If file already exists (shouldn't ever happen, would be reuse of same workOrderIdId), return a '403 Forbidden' HTTP header, 
                      echo "That File Already Exists", and die. 
                    Otherwise, put the file where it belongs and update the filename in the workOrderFile DB table (value is $tail/$workOrderId.$ext, e.g. '3/98743.jpg') 
                      and return a '200 OK' HTTP header.
                    */
                    $parts = explode(".", $_FILES['file']['name']);
                    
                    if (count($parts)) {
                        $ext = strtolower(end($parts));
                        
                        $origFileName = 'unnamed';
                        $nameBeforeExt = strtolower(prev($parts));
                        if ($nameBeforeExt) {
                            $origFileName = $nameBeforeExt;
                        }
                        $origFileName .= "." . $ext;
                        
                        $fileName = $id . "." . $ext;
                        $saveName = sprintf('%s%s', $saveDir, $fileName);            
                        if (in_array($ext, $allowedExtensions)) {                        
                            if (file_exists($saveName)) {
                                $logger->error2('', "Attempted to overwrite $saveName");
                                // We handle deletion of the row a bit differently here because this gets its own separate "Forbidden" return.
                                header('HTTP/1.0 403 Forbidden');
                                echo "File [$saveName] already exists; it may have been imported to this system without a corresponding database entry.";
                                $query = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderFile ";
                                $query .= "WHERE workOrderFileId = " . intval($id) . ";";
                                $result = $db->query($query);
                                if (!$result) {
                                    $logger->errorDb('1600102389', "Failed to delete a workOrderFile DB table row, workOrderId = $id, after filename conflict", $db);
                                    // not really anything we can do besides report that error.
                                }
                                die();
                            } else {                            
                                if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {                               
                                    $query = "UPDATE " . DB__NEW_DATABASE . ".workOrderFile ";                                    
                                    $query .= "SET fileName = '" . $db->real_escape_string($tail . '/' . $fileName) . "'";
                                    $query .= ", origFileName = '" . $db->real_escape_string($origFileName) . "' ";
                                    $query .= "WHERE workOrderFileId = " . intval($id) . ";";
                                    
                                    $result = $db->query($query);                                    
                                    if ($result) {
                                        header('HTTP/1.0 200 OK'); // SUCCESS!
                                        die();
                                    } else {
                                        $logger->errorDb('1600102772', 
                                            "Failed to update filename for newly uploaded workOrderFile, workOrderFileId = " . intval($id) . 
                                                " filename = '" . $db->real_escape_string($tail . '/' . $fileName) . "'",
                                            $db);
                                        // Leave workOrderFile row alone, there's a chance a developer can fix it.
                                    }
                                } else {
                                    $logger->error2('1600102895', "Failed move uploaded file from '{$_FILES['file']['tmp_name']}' to '{$saveName}'");
                                    // Leave workOrderFile row alone, there's a chance a developer can fix it.
                                }
                            }
                        } else {
                            $logger->error2('1600102910', "'{$saveName}' has invalid extension '$ext'; not saving workOrderFile, workOrderFileId = " . intval($id));
                            $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
                        }                            
                    } else {
                        $logger->error2('1600102916', "Explode on '{$_FILES['file']['name']}' failed to find an extension; not saving workOrderFile, workOrderFileId = " . intval($id));
                        $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
                    }
                } else {
                    $logger->error2('1600102919', "Uploaded file too big, limit $sizeLimit bytes, had {$_FILES['file']['size']}; not saving workOrderFile, workOrderFileId = " . intval($id));
                    $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
                }
            } else {
                $logger->error2('1600102922', 'File upload error ' . $_FILES['file']['error'] . "; not saving workOrderFile, workOrderFileId = " . intval($id));
                $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
            }
        } else {
            $logger->error2('1600102924', '$_FILES[\'file\'][\'error\'] not set or is array' . "; not saving workOrderFile, workOrderFileId = " . intval($id));
            $delete_row = true; // ADDED 2020-08-12 JM, error reworded accordingly
        }
        // BEGIN ADDED 2020-08-12 JM as part of dealing with http://bt.dev2.ssseng.com/view.php?id=207 ( Wrong behavior when workOrderFileIds clash)
        if ($delete_row) {
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderFile ";
            $query .= "WHERE workOrderFileId = " . intval($id) . ";";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1600103696', "Failed to delete a workOrderFile, workOrderFileId = " . intval($id) . " after error", $db);
                // not really anything we can do besides report that error.
            }
      }
        // END ADDED 2020-08-12 JM
    } // END else: '$saveDir' is writable 
}
die();
?>