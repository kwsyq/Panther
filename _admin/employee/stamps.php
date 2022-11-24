<?php
/* _admin/employee/stamps.php

    Manage stamps, including EOR stamps.
    Intended to be loaded into a frame of Admin | Employees.
    
    OPTIONAL INPUTs:
    
    $_REQUEST['act'], accessed as $act, may be any of:
        * 'updateActive' requires
            * $_REQUEST['active']: 0 or 1
            * $_REQUEST['stampId']  what stamp we are working on
        * 'updateDisplayName' requires
            * $_REQUEST['displayname']: string
            * $_REQUEST['stampId']  what stamp we are working on
        * 'updateInternalName' requires
            * $_REQUEST['internalname']: string
            * $_REQUEST['stampId']  what stamp we are working on
        * 'updateFilename' requires
            * $_REQUEST['filename']
            * $_REQUEST['stampId']  what stamp we are working on
        * 'updateDateIssued' requires
            * $_REQUEST['issued']: date string, can be empty
            * $_REQUEST['stampId']  what stamp we are working on
        * 'updateDateExpires' requires
            * $_REQUEST['expires']: date string, can be empty
            * $_REQUEST['stampId']  what stamp we are working on
        * 'uploadFile' requires
            * $_REQUEST['MAX_FILE_SIZE']
            * $_FILES
            * $_REQUEST['filename']: filename; if there is already a filename for this stamp, should be provided by hidden input and equal the current value
            * $_REQUEST['stampId']  what stamp we are working on
        * 'newShopStamp' requires
            * $_REQUEST['MAX_FILE_SIZE']
            * $_FILES
            * $_REQUEST['displayname']: string
            * $_REQUEST['internalname']: string
            * $_REQUEST['filename']: optional filename override, otherwise we use the name of the uploaded file
        * 'newEorStamp' requires
            * $_REQUEST['MAX_FILE_SIZE']
            * $_FILES (will also determine filename)
            * $_REQUEST['displayname']: string
            * $_REQUEST['personid']: identifies the EOR
            * $_REQUEST['state']: 2-letter state abbreviation
            * $_REQUEST['issued']: date string, can be empty
            * $_REQUEST['expires']: date string, can be empty
            * $_REQUEST['filename']: optional filename override, otherwise we use the name of the uploaded file

    Error reporting in this file is a bit unconventional: after writing to the usual log, 
     we also set a cookie and reload the page without $act to show the error message.
     
    >>>00016: as of 2020-04-17, this could use some client-side validation, and maybe more validation overall.
              It certainly does way more of the latter than any of the code Martin left behind, but I know
              that RDC has been pushing way past that on some fronts.
              
    >>>00043: like everything in _admin, this could use some cosmetic UI work. But don't go overboard: this is used
              rarely and by very few people.
*/

require_once '../../inc/config.php';

// ------------------ UTILITY FUNCTIONS FOR FILE UPLOADS ------------------------------- //

// Get filename for a file upload, based on CGI inputs. Also does some input checking.
// INPUT $ok_to_have_no_file - Boolean, default false, if true then it's fine that
//   there is no uploaded file in this action.
// NOTE that we expect the INPUT type="file" element to use name="stampfile", so we check that as $_FILES['stampfile'].
// By default, name is $_FILES['stampfile']['name'], the name of the actual file, but 
//  if $_REQUEST['filename'] is a non-blank string, then it supersedes that.
// NOTE that in some forms 'filename' is user-entered and can be blank; in others,
//  it is a hidden input, forcing this toward an overwrite of an existing file.
// RETURN target filename
// ON ERROR this will abort completely and reload the page, displaying an error message at top.
// (Some rework 2020-08-19 JM to deal with http://bt.dev2.ssseng.com/view.php?id=221: modify this  
//  so that it changes blanks to underscores even when it gets the filename from $_FILES['stampfile']['name'].)
function getFilename($ok_to_have_no_file = false) {
    global $logger;
    $error_message = ''; // NOTE that besides its content, we use this as a sentinel to say there was an error;
                         //  Then we can use a cookie to display that error when reloading the page after abandoning
                         //  the failed action.
    
    $filename = '';
    if (array_key_exists('filename', $_REQUEST)) {
        /* BEGIN REPLACED 2020-08-04 JM
        $filename = trim($_REQUEST['filename']);
        // END REPLACED 2020-08-04 JM
        */
        // BEGIN REPLACEMENT 2020-08-04 JM
        // 2020-08-04 JM - loosely related to http://bt.dev2.ssseng.com/view.php?id=193, allow blank in filename, convert it to underscore.
        // This was a late fix for v2020-3, and it only "sort of" worked: it got us past a crisis, but it caused http://bt.dev2.ssseng.com/view.php?id=221 
        // $filename = str_replace(' ', '_', trim($_REQUEST['filename']));
        // END REPLACEMENT 2020-08-04 JM
        // BEGIN FURTHER REPLACEMENT 2020-08-19 JM: for clarity, separate out getting the filename & manipulating it.
        $filename = trim($_REQUEST['filename']);
        $filename = str_replace(' ', '_', $filename); // change all blanks to underscores
        // END FURTHER REPLACEMENT 2020-08-19 JM
        if ($filename && !preg_match('/^[a-z_][a-z0-9_]*.pdf$/i', $filename)) {
            $error_message = "Invalid stamp filename '$filename'. " .
                             "Must be a PDF, name must be alphanumeric (with optional underscores) and cannot begin with a digit.";
            $logger->error2('1586899840', $error_message);
        }
    }
    
    if (!$error_message) {
        $skip_file_check = false;
        if (count($_FILES) == 0) {
            if ($ok_to_have_no_file) {
                $skip_file_check = true;
            } else {
                $error_message = '$_FILES is empty: no file to be uploaded.'; 
                $logger->error2('1586899843', $error_message);
            }
        }
    }
    
    if (!$error_message && !$skip_file_check) {
        if (!array_key_exists('stampfile', $_FILES)) {
            if (!$ok_to_have_no_file) {
                $error_message = '$_FILES ill-formed, no $_FILES[\'stampfile\']'; 
                $logger->error2('1586899841', $error_message);
            }
        } else if  (!array_key_exists('name', $_FILES['stampfile'])) {
            $error_message = '$_FILES ill-formed, no $_FILES[\'stampfile\'][\'name\']'; 
            $logger->error2('1586899842', $error_message);
        } else { 
            if (!$filename) {    
                $filename = $_FILES['stampfile']['name'];
                // BEGIN ADDED 2020-08-19 JM to fix http://bt.dev2.ssseng.com/view.php?id=221
                // This is the same manipulation we do above in the case where we get $filename from $_REQUEST['filename'].
                // >>>00006 We should eventually restructure to share common code, but for now I'm trying to minimize the changes
                //  between v2020-3 and v2020-4.
                $filename = str_replace(' ', '_', $filename); // change all blanks to underscores
                // END ADDED 2020-08-19 JM
                
            }
        }
    }
    
    if ($error_message) {
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
    
    return $filename;    
} // END function getFilename

// NOTE that we expect the INPUT type="file" element to use name="stampfile", so we check that as $_FILES['stampfile'].
//  That check happens via the call to getFilename().  
// INPUT $stamp_row_already_added: if true, the current action already added a row to DB table 'stamp'; part of the
//  action was to upload a corresponding file and we are doing that now. Failure to upload is still treated as a partial
//  success, because we can upload later in a separate action.
// ON ERROR this will abort completely and reload the page, displaying an error message at top.
function uploadFile($stamp_row_already_added = false) {
    global $logger, $act;
    $error_message = ''; // NOTE that besides its content, we use this as a sentinel to say there was an error;
                         //  Then we can use a cookie to display that error when reloading the page after abandoning
                         //  the failed action.
    
    $filename = getFilename();

    if (!$error_message) {
        $sizeLimit = 2097152;  // 2MB  
        $allowedExtensions = array('pdf');
        $saveDir = BASEDIR. '/../' . CUSTOMER_DOCUMENTS . '/stamps';
        if (!file_exists($saveDir)) {
            @mkdir($saveDir);
        }
        if (!is_writable($saveDir)) {
            $error_message = "directory '$saveDir' not writable when uploading $filename"; 
            $logger->error2('1586899845', $error_message);
        }
    }
    
    if (!$error_message) {
        if ( !isset($_FILES['stampfile']['error']) || is_array($_FILES['stampfile']['error'])) {
            $error_message = "problem in \$_FILES structure when uploading $filename";
            $logger->error2('1586899850', $error_message);
        }
    }
    
    if (!$error_message) {
        if ($_FILES['stampfile']['error'] != UPLOAD_ERR_OK) {
            $error_message = "\$_FILES shows error " . $_FILES['stampfile']['error'] . " when uploading $filename; ";
            
            $err = $_FILES['stampfile']['error'];
            if ($err == UPLOAD_ERR_INI_SIZE) {
                $error_message .= 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
            } else if ($err == UPLOAD_ERR_FORM_SIZE) {
                $error_message .= 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
            } else if ($err == UPLOAD_ERR_PARTIAL) {
                $error_message .= 'The uploaded file was only partially uploaded.';
            } else if ($err == UPLOAD_ERR_NO_FILE) {
                $error_message .= 'No file was uploaded.';
            } else if ($err == UPLOAD_ERR_NO_TMP_DIR) {
                $error_message .= 'Missing a temporary folder.';
            } else if ($err == UPLOAD_ERR_CANT_WRITE) {
                $error_message .= 'Failed to write file to disk.';
            } else if ($err == UPLOAD_ERR_EXTENSION) {
                $error_message .= 'A PHP extension stopped the file upload; examining the list of loaded extensions with phpinfo() may help.';
            }            
            $logger->error2('1586899855', $error_message);
        }
    }
    
    if (!$error_message) {
        if ($_FILES['stampfile']['size'] > $sizeLimit) {
            $error_message = "uploaded file $filename too big, " . $_FILES['stampfile']['size']  . " bytes, max is $sizeLimit";
            $logger->error2('1586899860', $error_message);
        }
    }
                
    if (!$error_message) {
        $parts = explode(".", $_FILES['stampfile']['name']);
        if (count($parts)) {
            $ext = strtolower(end($parts));
        }
        if (!count($parts) || ! in_array($ext, $allowedExtensions)) {
            $error_message = "uploaded file $filename needs to be a PDF";
            $logger->error2('1586899865', $error_message);
        }
    }

    if (!$error_message) {
        // NOTE that move_uploaded_file here can be an overwrite, which is fine.
        $saveName = sprintf('%s/%s', $saveDir, $filename);
        if ( ! move_uploaded_file($_FILES['stampfile']['tmp_name'], $saveName) ) {
            $exists = file_exists($_FILES['stampfile']['tmp_name']);
            $error_message = "Cannot save $filename. ";
            $error_message .= $exists ? 'Source file exists.' : 'Source file not found.'; 
            $logger->error2('1586899870', $error_message . ": move_uploaded_file('{$_FILES['stampfile']['tmp_name']}', '$saveName')");
        }
    }
    
    if ($error_message) {
        if ($stamp_row_already_added) {
            // If the row was succesfully added to the table, but the file wasn't uploaded, let the user know.
            $error_message = 'A row has been successfully added to the stamp table, but no file was uploaded.<br />' + $error_message; 
        }
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
    
    $logger->info2('1586899875', "'$saveName' uploaded");    
}

// ------------------ ACTIONS ------------------------------- //
// All these actions are triggered by a self-submit.
/* BEGIN REMOVED 2020-08-19 JM
// This section was moved down below 2020-08-19 JM so that it has access to stampId
if ($act == 'uploadFile') { 
    // Upload a new stamp file for an existing row
    uploadFile();
    
    // arrive here only on successful upload: failed uploads die.                
    setcookie('stamps_message', 'File upload succeeded', time() + 60, '/'); // report success because the only visible change is a modification date
    header("Location: stamps.php"); // reload page normally
    die();
} else 
// END REMOVED 2020-08-19 JM
*/
if ($act == 'newShopStamp') {
    // upload a generic stamp, not tied to an individual, and create a brand new row for it in DB table 'stamp'
    $v=new Validator2($_REQUEST);
    $v->rule('required', ['displayname']); // Must have a way to display it to select it.
    $v->rule('lengthMin', 'displayname', 1);
    if(!$v->validate()){
        $error_message = "Error input parameters ".json_encode($v->errors());
        $logger->error2('1587064835', $error_message);
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
    
    $displayName = trim($_REQUEST['displayname']);
    $name = array_key_exists('internalname', $_REQUEST) ? trim($_REQUEST['internalname']) : ''; // OK not to have this, stamp will only ever be
                                                                                                // used by explicit user selection 
    $filename = getFilename(true); // true => OK to have $_FILES be empty
    
    // createGenericStamp will validate other inputs as needed.
    // Any detailed error messages will only be in the log.
    $stampId = Stamp::createGenericStamp(
        $customer->getCustomerId(), 
        null, //state
        $filename, 
        $name, 
        $displayName, 
        null, // issueDate
        null // expirationDate
        );
    
    if ( !$stampId ) {
        $error_message = "Failed to create shop stamp";
        $logger->error2('1587064837', $error_message);
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
        
    uploadFile(true); // It is actually OK to have this fail if no file was provided, but for
                      // clarity's sake, it will give a message about the lack of upload
                      
    // arrive here only on successful upload: failed uploads die.
    
    setcookie('stamps_message', 'Shop stamp saved and upload succeeded', time() + 60, '/'); // report success because there is no visible change
    header("Location: stamps.php"); // reload page normally
    die();
} else if ($act == 'newEorStamp') {
    // upload a stamp for an individual EOR, and create a brand new row for it in DB table 'stamp'. 
    // An EOR stamp is always associated with a particular U.S. state. If we ever go more broadly, we
    //  will need to change the model.
    $v=new Validator2($_REQUEST);
    $v->rule('required', ['displayname', 'personid', 'state']);
    $v->rule('min', 'personid', 1);
    $v->rule('lengthMin', 'displayname', 1);
    $v->rule('length', 'state', 2);
    if(!$v->validate()){
        $error_message = "Error input parameters ".json_encode($v->errors());
        $logger->error2('1587064835', $error_message);
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
    
    $personId = $_REQUEST['personid']; // EOR
    $state = $_REQUEST['state']; // 2-letter state abbreviation
    $issued = array_key_exists('issued', $_REQUEST) ? $_REQUEST['issued']: ''; // date string, can be empty
    $expires = array_key_exists('expires', $_REQUEST) ? $_REQUEST['expires']: ''; // date string, can be empty
    
    $displayName = trim($_REQUEST['displayname']);
    $filename = getFilename(true); // true => OK to have this be blank
    
    // createEorStamp will validate other inputs as needed.
    // Any detailed error messages will only be in the log.
    $stampId = Stamp::createEorStamp(
        $customer->getCustomerId(), 
        $personId, // EOR
        $state,
        $filename, 
        $displayName, 
        $issued,
        $expires
        );
    
    if ( !$stampId ) {
        $error_message = "Failed to create EOR stamp";
        $logger->error2('1587065827', $error_message);
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
        
    uploadFile(true); // It is actually OK to have this fail if no file was provided, but for
                      // clarity's sake, it will give a message about the lack of upload
    // arrive here only on successful upload: failed uploads die.
    
    setcookie('stamps_message', 'EOR stamp saved and upload succeeded', time() + 60, '/'); // report success because there is no visible change
    header("Location: stamps.php"); // reload page normally
    die();
} else if ($act) {
    // All the other actions make a change and save it to the DB for a particular existing
    //  row identified by stampId, so they share code.
    
    $v=new Validator2($_REQUEST);
    $v->rule('required', 'stampId');
    
    if(!$v->validate()){
        $error_message = "Error input parameters ".json_encode($v->errors());
        $logger->error2('1586899800', $error_message);
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
    
    $stampId = intval($_REQUEST['stampId']);
    if (!Stamp::Validate($stampId)) {
        $error_message = "Bad stampId $stampId";
        $logger->error2('1586899805', "Bad stampId $stampId");
        setcookie('stamps_message', $error_message, time() + 60, '/');
        header("Location: stamps.php"); // reload page normally
        die();
    }
    $stamp = new Stamp($stampId);

    // Now break this down to the individual actions
    // BEGIN MOVED FROM ABOVE & REWORKED 2020-08-19 JM
    // This section moved here 2020-08-19 JM so that it 
    //  will fall through to $stamp->save() below on success.
    // Before it ignored the database.
    if ($act == 'uploadFile') { 
        // Upload a new stamp file for an existing row
        uploadFile();
        // arrive here only on successful upload: failed uploads die.
        $logger->info2('JDEBUG 1', 'upload succeeded');
    } else 
    // END MOVED 2020-08-19 JM;
    // ALSO at that time changed the below to use repeated "else if" rather than "if" so it is
    //  clear that only one of these happens on a given execution.
    if ($act == 'updateActive') {
        $v->rule('required', 'active');
        $v->rule('min', 'active', 0);
        $v->rule('max', 'active', 1);
        
        if(!$v->validate()){
            $error_message = "Error input parameters ".json_encode($v->errors());
            $logger->error2('1586899810', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }    
        
        $stamp->setActive(intval($_REQUEST['active']));
    } else if ($act == 'updateDisplayName') {
        $v->rule('required', 'displayname');
    
        if(!$v->validate()){
            $error_message = "Error input parameters ".json_encode($v->errors());
            $logger->error2('1586899820', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }
        
        $displayName = trim($_REQUEST['displayname']);
        $stamp->setDisplayName($displayName);                
    } else  if ($act == 'updateInternalName') {
        // $v->rule('required', 'internalname'); // REMOVED 2020-08-19 JM, OK to blank out the internal name
        
        if(!$v->validate()){
            $error_message = "Error input parameters ".json_encode($v->errors());
            $logger->error2('1586899825', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }
        
        $name = trim($_REQUEST['internalname']);
        $stamp->setName($name);
    } else if ($act == 'updateFilename') {
        $v->rule('required', 'filename');
        
        if(!$v->validate()){
            $error_message = "Error input parameters ".json_encode($v->errors());
            $logger->error2('1586899827', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }
        
        /* BEGIN REPLACED 2020-08-04 JM
        $filename = trim($_REQUEST['filename']);
        // END REPLACED 2020-08-04 JM
        */
        // BEGIN REPLACEMENT 2020-08-04 JM
        // 2020-08-04 JM - loosely related to http://bt.dev2.ssseng.com/view.php?id=193, allow blank in filename, convert it to underscore.
        $filename = str_replace(' ', '_', trim($_REQUEST['filename']));
        // END REPLACEMENT 2020-08-04 JM        
        if ($filename && !preg_match('/^[a-z_][a-z0-9_]*.pdf$/i', $filename)) {
            $error_message = "Invalid stamp filename '$filename'. " .
                             "Must be a PDF, name must be alphanumeric (with optional underscores) and cannot begin with a digit.";
            $logger->error2('1587157310', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }
        $stamp->setFilename($filename);
    } else if ($act == 'updateDateIssued') {
        $v->rule('required', 'issued');
        
        if(!$v->validate()){
            $error_message = "Error input parameters ".json_encode($v->errors());
            $logger->error2('1586899830', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }
        
        $issued = trim($_REQUEST['issued']);
        $stamp->setIssueDate($issued);                
    } else if ($act == 'updateDateExpires') {
        $v->rule('required', 'expires');
        
        if(!$v->validate()){
            $error_message = "Error input parameters ".json_encode($v->errors());
            $logger->error2('1586899835', $error_message);
            setcookie('stamps_message', $error_message, time() + 60, '/');
            header("Location: stamps.php"); // reload page normally
            die();
        }
        
        $expires = trim($_REQUEST['expires']);
        $stamp->setExpirationDate($expires);                
    }
    
    $stamp->save($act == 'uploadFile'); // Argument added 2020-08-19 JM to force a DB save with timestamp even if nothing else in the DB row
    $logger->info2('JDEBUG 2', '$stamp->save() called');
    header("Location: stamps.php"); // reload page normally
    die();
} // END else if ($act)

// Get initials (or a substitute) for display from INPUT $personId (key into Person table):
// who inserted / last modified this row.
function initialsFromPersonId($personId) {
    $person = null;
    if ($personId) {
        $person = new Person($personId);
    }
    if ($person) {
        // getLegacyInitials() was moved in CustomerPerson class
        $customerPerson = CustomerPerson::getFromPersonId($personId);
        if ($customerPerson) {
            $initials = $customerPerson->getLegacyInitials(); // scratch variable, we will multiplex this
        } else {
            echo '';
        }
        
        if (!$initials) {
            $initials = "($personId)"; // typically admin who is not employee of this customer
        }                    
        return $initials;
    } else {
        return '---'; // Nobody, probably inserted/modified by a batch job
    }
}

$message = '';
if( isset($_COOKIE['stamps_message']) && strlen($_COOKIE['stamps_message']) ) {
    $message = $_COOKIE['stamps_message'];
    setcookie('stamps_message', '', time() - 60, '/'); // clear cookie
}

$stamps = Stamp::getStamps();
if ($stamps === false) {
    $message .= '<br />Error getting stamps.';
}

$eors = $customer->getAllEors(); // Engineers of Record
if ($eors === false) {
    $message .= '<br />Error getting EORs.';
} 

?>
<!DOCTYPE html>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <style>
        tr.stamp td {background-color:lightgray;}
        tr.stamp.active td {background-color:white;}
    </style>
</head>
<body bgcolor="#ffffff">
<?php
    if($message) {
        echo "<p style=\"color:red\">$message</p>";
    }

    if (!$stamps) {
        echo '<p>Could not obtain stamps</p>';
    } else {
        ?>
        <h2>EOR stamps</h2>
        <table border="1" cellpadding="3" cellspacing="2">
            <thead>
                <tr>
                <th>Display Name</th>
                <th>EOR</th>
                <th>State</th>
                <th>File</th>
                <th>Active</th>
                <th>Issued</th>
                <th>Expires</th>
                <th>Inserted</th>
                <th>Inserted By</th>
                <th>Modified</th>
                <th>Modified By</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // EOR stamps
            foreach ($stamps AS $stamp) {
                if ( !$stamp->getIsEorStamp() ) {
                    continue;
                }
                $active = $stamp->getActive();
                echo "<tr " . 
                     "class=\"stamp " . ($active ? "active" : '') . "\" " . 
                     "data-stamp=\"". $stamp->getStampId() . "\">\n";
                
                echo "<td><input class=\"displayname\" type=\"text\" value=\"" . $stamp->getDisplayName() . "\" /></td>\n"; // Display Name
                $person = new Person($stamp->getEorPersonId()); // scratch variable, we will multiplex this                
                echo "<td>" . $person->getFormattedName(true) . "</td>\n"; // EOR
                echo "<td>" . $stamp->getState() . "</td>\n"; // State
                
                // File
                $filename = $stamp->getFilename();
                
                echo "<td>";
                if ($filename) {
                    echo $filename . "<br/>";
                    if (file_exists (BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/' . $filename)) {
                        echo '<a href="stamppdf.php?stamp=' . $filename . '"><button>Show</button></a> &nbsp;' . "\n";
                    } else {
                        echo '<i>file not found</i>' . "\n";
                    }
                } else {
                    echo '<label>Filename override (must precede upload): <input class="filename-override" type="text" name="filename"/></br>';
                }
                echo '<form class="upload-form" enctype="multipart/form-data" method="POST">' . "\n".
                         '<input type="hidden" name="act" value="uploadFile" />' . "\n".
                         '<input type="hidden" name="stampId" value="' . intval($stamp->getStampId()) . '" />' . "\n".
                         '<input type="hidden" name="MAX_FILE_SIZE" value="2097152" />' . "\n".
                         '<input type="hidden" name="filename" value="' . $filename. '" />' . "\n" .
                         '<input class="fileupload" type="file" name="stampfile" accept="application/pdf" />' . "\n".
                     '</form>' . "\n".
                     "</td>\n";
                     
                echo "<td><input class=\"active\" type=\"checkbox\"" . ($active ? ' checked' : '') . " /></td>\n"; // Active
                $issued = $stamp->getIssueDate();
                echo "<td><input class=\"issued\" type=\"date\"" . ($issued ? 'value="' . $issued .'"' : '')  . " /></td>\n"; // Issued
                $expires = $stamp->getExpirationDate();
                echo "<td><input class=\"expires\" type=\"date\"" . ($expires ? 'value="' . $expires .'"' : '')  . " /></td>\n"; // Expires
                echo "<td>" . $stamp->getInserted() . "</td>\n"; // Inserted
                echo "<td>" . initialsFromPersonId($stamp->getInsertedPersonId()). "</td>\n"; // Inserted By
                echo "<td>" . $stamp->getModified() . "</td>\n"; // Modified
                echo "<td>" . initialsFromPersonId($stamp->getModifiedPersonId()). "</td>\n"; // Modified By
                echo "</tr>\n";
            } // END foreach ($stamps AS $stamp)
            unset($stamp, $active, $filename, $person, $issued, $expires); 
            ?>
            <tr id="add-eor-stamp-show-row"><td colspan="11"><button id="add-eor-stamp-show">Add EOR stamp</button></td></tr>
            <tr id="add-eor-stamp-tool-row" style="display:none;">
                <form id="add-eor-stamp-form" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="act" value="newEorStamp" />
                <input type="hidden" name="MAX_FILE_SIZE" value="2097152" />
                    <td><input class="new-displayname" type="text" name="displayname" /></td> <?php /* Display Name */ ?>
                    
                    <?php // EOR
                    echo "\n<td><select class=\"new-person\" name=\"personid\">\n";
                    echo "<option value=\"0\">---</option>\n";
                    foreach ($eors as $eor) {
                        echo "<option value=\"{$eor['personId']}\">{$eor['firstName']} {$eor['lastName']}</option>\n";
                    }
                    echo "</select></td>\n";
                    ?>
                    
                    <td><input class="new-state" name="state" value="<?= HOME_STATE ?>" /></td> <?php /* State */ ?>
                    
                    <?php /* File */ ?>
                    <td><input class="new-fileupload" type="file" name="stampfile" accept="application/pdf" /><br />
                    <label>Filename override: <input class="new-filename" type="text" name="filename"/></td>
                    <td>&nbsp;</td> <?php /* placeholder in "active" column */ ?>
                    <td><input class="new-issued" type="date" name="issued"/></td> <?php /* Issued */ ?>
                    <td><input class="new-expires" type="date" name="expires" /></td> <?php /* Expires */ ?>
                    <td><button id="add-eor-stamp">Add</button></td>
                    <?php /* type="button" prevents submit for button inside a form */ ?>
                    <td><button type="button" id="add-eor-stamp-hide">Hide row</button></td>
                </form>
            </tr>    
            </tbody>
        </table>
        <h2>Shop stamps</h2>
        <table border="1" cellpadding="3" cellspacing="2">
            <thead>
                <tr>
                <th>Display Name</th>
                <th>Internal Name<br />(PHP constant)</th>
                <th>File</th>
                <th>Active</th>
                <th>Inserted</th>
                <th>Inserted By</th>
                <th>Modified</th>
                <th>Modified By</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // shop stamps
            foreach ($stamps AS $stamp) {
                if ( $stamp->getIsEorStamp() ) {
                    continue;
                }
                $active = $stamp->getActive();
                echo "<tr " . 
                     "class=\"stamp " . ($active ? "active" : '') . "\" " . 
                     "data-stamp=\"". $stamp->getStampId() . "\">\n";

                echo "<td><input class=\"displayname\" type=\"text\" value=\"" . $stamp->getDisplayName() . "\" /></td>\n"; // Display Name
                echo "<td><input class=\"internalname\" type=\"text\" value=\"" . $stamp->getName() . "\" /></td>\n"; // Internal Name<br />(PHP constant)

                // File
                $filename = $stamp->getFilename();
                echo "<td>";
                if ($filename) {
                    echo $filename . "<br/>";
                    if (file_exists (BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/' . $filename)) {
                        echo '<a href="stamppdf.php?stamp=' . $filename . '"><button>Show</button></a> &nbsp;' . "\n";
                    } else {
                        echo '<i>file not found</i>' . "\n";
                    }
                } else {
                    echo '<label>Filename override (must precede upload): <input class="filename-override" type="text" name="filename"/></br>';
                }
                echo '<form class="upload-form" enctype="multipart/form-data" method="POST">' . "\n".
                         '<input type="hidden" name="act" value="uploadFile" />' . "\n".
                         '<input type="hidden" name="stampId" value="' . intval($stamp->getStampId()) . '" />' . "\n".
                         '<input type="hidden" name="MAX_FILE_SIZE" value="2097152" />' . "\n".
                         '<input type="hidden" name="filename" value="' . $filename. '" />' . "\n" .
                         '<input class="fileupload" type="file" name="stampfile" accept="application/pdf" />' . "\n".
                     '</form>' . "\n".
                     "</td>\n";
                     
                echo "<td><input class=\"active\" type=\"checkbox\"" . ($active ? ' checked' : '') . " /></td>\n"; // Active
                echo "<td>" . $stamp->getInserted() . "</td>\n"; // Inserted                
                echo "<td>" . initialsFromPersonId($stamp->getInsertedPersonId()). "</td>\n"; // Inserted By                
                echo "<td>" . $stamp->getModified() . "</td>\n"; // Modified
                echo "<td>" . initialsFromPersonId($stamp->getModifiedPersonId()). "</td>\n"; // Modified By
                echo "</tr>\n";
            } // END foreach ($stamps AS $stamp)
            unset($stamp, $active, $filename, $person);
            ?>
            <tr id="add-shop-stamp-show-row"><td colspan="8"><button id="add-shop-stamp-show">Add shop stamp</button></td></tr>
            <tr id="add-shop-stamp-tool-row" style="display:none;">
                <form id="add-shop-stamp-form" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="act" value="newShopStamp" />
                <input type="hidden" name="MAX_FILE_SIZE" value="2097152" />
                    <td><input class="new-displayname" type="text" name="displayname" /></td> <?php /* Display Name */ ?>
                    <td><input class="new-internalname" type="text" name="internalname"/></td> <?php /* Internal Name<br />(PHP constant) */ ?>
                    
                    <?php /* File */ ?>
                    <td><input class="new-fileupload" type="file" name="stampfile" accept="application/pdf" /><br />
                    <label>Filename override: <input class="new-filename" type="text" name="filename"/></td>
                    
                    <td><button id="add-shop-stamp">Add</button></td>
                    <?php /* type="button" prevents submit for button inside a form */ ?>
                    <td><button type="button" id="add-shop-stamp-hide">Hide row</button></td>
                </form>
            </tr>    
            </tbody>
        </table>
        
        <script>
            $(function() { // on document ready...
                // Save state of scrolling so we can restore it after page reload
                function saveScroll() {
                    let scrollTopMain = $(document).scrollTop();
                    sessionStorage.setItem('stamps_scrollTopMain', scrollTopMain);         
                }
                
                // Restores state saved with saveScroll(), then deletes it from sessionStorage 
                function restoreScroll() {
                    let scrollTopMain = sessionStorage.getItem('stamps_scrollTopMain');
                    $(document).scrollTop(scrollTopMain);
                    sessionStorage.removeItem('stamps_scrollTopMain');
                }
                
                <?php
                if (!$message) {
                ?>    
                    restoreScroll();
                <?php
                }
                ?>
                
                // "active" checkbox handler
                $('input.active').change(function() {
                    let $this=$(this);
                    saveScroll();
                    location.href = "stamps.php?act=updateActive&active=" + ($this.is(':checked') ? 1 : 0) + "&stampId=" + $this.closest('tr').data('stamp');
                });
                
                $('input.displayname').change(function() {
                    let $this=$(this);
                    let displayName = $this.val();
                    if (displayName) {
                        saveScroll();
                        location.href = "stamps.php?act=updateDisplayName&displayname=" + displayName + "&stampId=" + $this.closest('tr').data('stamp');
                    } else {
                        alert('Empty Display Name');
                    }
                });
                    
                $('input.internalname').change(function() {
                    let $this=$(this);
                    let internalName = $this.val();
                    let stampId = $this.closest('tr').data('stamp'); // variable introduced 2020-08-19 JM to simplify code 
                    if (internalName) {
                        saveScroll();
                        location.href = "stamps.php?act=updateInternalName&internalname=" + internalName + "&stampId=" + stampId;
                    } else {
                        /* BEGIN REPLACED 2020-08-19 JM
                        alert('Empty Internal (PHP) Name');
                        // END REPLACED 2020-08-19 JM
                        */
                        // BEGIN REPLACEMENT 2020-08-19 JM
                        // Allow blanking this, but require confirmation
                        $('<div>Are you sure you mean to remove the Internal Name"?</div>').dialog({
                            title: "Remove Internal Name?",
                            buttons: {                                
                                "No/Cancel" : function() {
                                    saveScroll();
                                    location.href = "stamps.php";
                                },
                                "Yes/Continue" : function() {
                                    // '%20' after '&internalname=' is effectively a blank
                                    saveScroll();
                                    location.href = "stamps.php?act=updateInternalName&internalname=%20&stampId=" + stampId;
                                }
                            }
                        });
                        // END REPLACEMENT 2020-08-19 JM
                    }
                });
                
                $('input.filename-override').change(function(event) {
                     let $this=$(this);
                     event.preventDefault();
                     
                     let filename = $this.val();
                     if (filename) {
                        saveScroll();
                        location.href = "stamps.php?act=updateFilename&filename=" + filename + "&stampId=" + $this.closest('tr').data('stamp');
                     }
                });

                $('input.issued').change(function() {
                    let $this=$(this);
                    let issued = $this.val();
                    if (!issued) {
                        issued = 0;
                    }
                    saveScroll();
                    location.href = "stamps.php?act=updateDateIssued&issued=" + issued + "&stampId=" + $this.closest('tr').data('stamp');
                });
                
                $('input.expires').change(function() {
                    let $this=$(this);
                    let expires = $this.val();
                    if (!expires) {
                        expires = 0;
                    }
                    saveScroll();
                    location.href = "stamps.php?act=updateDateExpires&expires=" + expires + "&stampId=" + $this.closest('tr').data('stamp');
                });
                
                // BEGIN ADDED 2020-08-19 JM
                $('.upload-form').submit(function() {
                    saveScroll();
                })
                // END ADDED 2020-08-19 JM
                
                // stamp upload handler
                $('input.fileupload').change(function() {
                    $(this).closest('form').submit();
                });
                
                $('#add-shop-stamp-show').click(function() {
                    $('#add-shop-stamp-tool-row').show();
                    $('#add-shop-stamp-show-row').hide();
                });
                $('#add-shop-stamp-hide').click(function() {
                    $('#add-shop-stamp-tool-row').hide();
                    $('#add-shop-stamp-show-row').show();
                });
                $('#add-shop-stamp').click(function() {
                    $this.closest('form').submit();
                });
                
                $('#add-eor-stamp-show').click(function() {
                    $('#add-eor-stamp-tool-row').show();
                    $('#add-eor-stamp-show-row').hide();
                });
                $('#add-eor-stamp-hide').click(function() {
                    $('#add-eor-stamp-tool-row').hide();
                    $('#add-eor-stamp-show-row').show();
                });
                $('#add-eor-stamp').click(function() {
                    $this.closest('form').submit();
                });
            });
        </script>            
        
        <?php
    }
?>
</body>
</html>
