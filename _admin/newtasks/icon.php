<?php 
/*  _admin/newtasks/icon.php

    EXECUTIVE SUMMARY: view or replace task icon.

    INPUT $_REQUEST['taskId'] - primary key into DB table task
    
    Optional $_REQUEST['act']. Only possible value: 'upload'. 
        * If this is used, we also need $_FILES['file']. See http://php.net/manual/en/reserved.variables.files.php for more on $_FILES.    
    
*/

$nested = isset($inside_admin_newtasks_edit_php) && $inside_admin_newtasks_edit_php;

if (!$nested) {
    require_once '../../inc/config.php';
    require_once 'gettaskidfromrequest.php';
    include_once '../../includes/header_admin.php';
    require_once 'rightframetabbing.php';
    
    list($taskId, $error) = getTaskIdFromRequest(__FILE__);
    createNewtasksTabs('icon.php', $taskId);
} else if (!$taskId) {
    $error = "_admin/newtasks/icon.php called nested without \$taskId";
    $logger->error2('1604435030', $error); 
} else { 
    $error = '';
}    

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    if (!$nested) {
        echo "<h2>Edit</h2>\n";
    }
    $task = new Task($taskId);
    $taskDescription = $task->getDescription();
    $iconName = $task->getIcon();

    // We decided to move task icons outside of any area that is under version control,
    //  since it is administered by _admin. For web access, Apache will get to these 
    //  by mapping a directory path.
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/icons_task/';
    
    echo '<b>Icon for [' . $task->getDescription() . '][' . intval($task->getTaskId()) . ']</b>' . '<p>';
    
    $iconFullPath = ''; 
    if ($iconName) {        
        echo "Current icon name '$iconName'<br />\n";
        $iconFullPath = getFullPathnameOfTaskIcon($iconName);
    } else {
        echo "No current icon<br />\n";
    }
    
    // Here's where it displays the current icon. rand should prevent caching.
    echo '<img style="max-width:100px; max-height:100px" src="' . 
        ($iconFullPath ? $iconFullPath . '?t=' . rand(1000000,9000000) : '') . 
        '">';
    
    if (!file_exists($fileDir)) {
        @mkdir($fileDir);
    }

    if (!file_exists($fileDir)) {
        $error = "Save Dir $fileDir doesn't exist";
        $logger->error2('1601664317', $error);
    } else if (!is_writable($fileDir)) {
        $error = "Can't write to Save Dir $fileDir";
        $logger->error2('1601664402', $error);
    }
}

if (!$error && $act == 'upload') {
    $success = false;
    $sizeLimit = 500000; // limit 500KB; no deep reason not to allow bigger, if we like.
    $allowedExtensions = array('jpg'); // NOTE that if we allowed more than one type here, things would get a lot more complicated,
                                       // because if you replaced an icon of one file type with an icon of another type, then the
                                       // name would have to change in the DB.
    
    if (!(!isset($_FILES['file']['error']) || is_array($_FILES['file']['error']))) {        
        if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {                    
            if ($_FILES['file']['size'] <= $sizeLimit) {
                $fileName = $_FILES['file']['name'];
                
                $parts = explode(".", $fileName);        
                $firstpart = implode(".", array_slice($parts, 0, count($parts) - 1));        
                if (count($parts)) {        
                    $ext = strtolower(end($parts));
                    if (in_array($ext, $allowedExtensions)) {
                        if (!$iconName) {
                            // We need to create a name for the icon; historically (before v2020-4), we based these on the uploaded file,
                            // but going forward let's base it on the taskId.
                            $iconName = 't_' . $taskId . '.jpg'; 
                        }
                        $saveName = sprintf('%s%s', $fileDir, $fileName);
                        if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {                                    
                            $success = true;
                        }
                    } else {
                        $error = "Extension '$ext' not allowed for task icons.";
                    }
                } else {
                    $error = "Malformed filename '$fileName'";
                }
            } else {
                $error = "File too big, {$_FILES['file']['size']} bytes, limit is $sizeLimit";
            }
        } else {
            $error = "\$_FILES['file']['error'] = {$_FILES['file']['error']}";
        }
    } else {
        $error = "\$_FILES['file'] is missing its 'error' element'";
    }
    if ($success) {
        if ($fileName != $iconName) {
            // We've either uploaded an icon where there was none before, or uploaded an icon with a different filename than before.
            // Update the databse
            $task->setIcon($fileName);
            $task->save();
        }
        // Now that icon has changed, force reload of this page.
        echo '        <script type="text/javascript">' . "\n";
        echo "             location.href = '" . $_SERVER['PHP_SELF'] . "?taskId=$taskId';\n";
        echo '        </script>' . "\n";
        include_once '../../includes/footer_admin.php';
        die();
    }
    
} // END if ($act == 'upload')

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    // form to upload a different icon; lets user choose a file & click submit button labeled "Upload File" to self-submit with $act == 'upload'
    
    // $_SERVER['PHP_SELF'] in the action helps this deal correctly with the fact that the current file may be included in another file.    
    ?>    
    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
        <label for="file"><b>Select file to upload:</b></label>
        <input type="hidden" name="act" value="upload">
        <input type="hidden" name="taskId" value="<?= $taskId ?>">
        <input type="file" name="file" id="file"><br/>
        <input type="submit" value="Upload File" name="submit">
    </form>
<?php    
}

if (!$nested) {
    include_once '../../includes/footer_admin.php';
}
unset($nested); 
?>