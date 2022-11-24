<?php 
/*  _admin/descriptor/icon.php

    EXECUTIVE SUMMARY: view or replace icon. Prior to 2019-12, this was typically an icon for any of
        elementType, descriptorCategory, descriptor, descriptorSub. As of 2019-12, these are all replaced by descriptor2.

    INPUT $_REQUEST['type'] - Beginning 2019-12: should always be 'd2', for descriptor2 
    INPUT $_REQUEST['id'] - primary key into appropriate table for $_REQUEST['type']; as of 2020-01 this must be a descriptor2Id 
    INPUT $_REQUEST['name'] - name of descriptor2
    
    Optional $_REQUEST['act']. Only possible value: 'upload'. 
        * If this is used, we also need $_FILES['file']. See http://php.net/manual/en/reserved.variables.files.php for more on $_FILES.    
    
*/

include '../../inc/config.php';
?>

<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';

// 
if (in_array($type, array('d2'))) { // as of 2020-01 this must be 'd2', meaning 'descriptor2'
    $sizeLimit = 500000; // Martin comment: deal with this at some point  sync back and front end and web server
                         // JM: limit 500KB
    
    // Martin comment: get this in a more central place and more definitive perhaps
    $allowedExtensions = array('gif');
    /* 
    OLD CODE removed 2019-02-06 JM
    $sep = DIRECTORY_SEPARATOR;
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . 'cust' . $sep . 'ssseng' . $sep . 'img' . $sep . 'icons_desc' . $sep;
    */
    // BEGIN NEW CODE 2019-02-06 JM
    // $fileDir = $_SERVER['DOCUMENT_ROOT'] . '/cust/' . CUSTOMER . '/img/icons_desc/';
    // END NEW CODE 2019-02-06 JM
    // BEGIN NEW CODE 2020-09-01 JM
    // We decided to move descriptor icons outside of any area that is under version control,
    //  since it is administered by _admin. For web access, Apache will get to these 
    //  by mapping a directory path.
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/icons_desc/';
    // END NEW CODE 2020-09-01 JM
    
    echo '<h2>[' . $type . ']' . $name . '</h2>'; // type & name, e.g. '[et]Building'
    
    echo '<br />'; 
    
    /* 
    OLD CODE removed 2019-02-06 JM
    echo '<img src="' . $sep . 'cust' . $sep . 'ssseng' . $sep . 'img' . $sep . 'icons_desc' . $sep . $type . '_' . $id . '.gif?t=' . rand(1000000,9000000) . '">';
    */
    // BEGIN NEW CODE 2019-02-06 JM
    // Here's where it displays the current icon.  rand should prevent caching.
    echo '<img src="/cust/' . CUSTOMER . '/img/icons_desc/' . $type . '_' . $id . '.gif?t=' . rand(1000000,9000000) . '">';
    // END NEW CODE 2019-02-06 JM	
    // echo '</tr>';  // REMOVED 2019-12-09 JM. Ended a TR that was never opened, we are not even in a table

    if (!file_exists($fileDir)) {
        @mkdir($fileDir);
    }
    
    if (!file_exists($fileDir)) {
        echo "Save Dir Doesn't Exist";
        die();
    }
    
    if (!is_writable($fileDir)) {
        echo "Can't Write To Save Dir";
        die();
    }
    
    if ($act == 'upload') {        
        $success = false;
        
        if (!(!isset($_FILES['file']['error']) || is_array($_FILES['file']['error']))) {        
            if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {                    
                if ($_FILES['file']['size'] <= $sizeLimit) {
                    // BEGIN MARTIN COMMENT
                    //and maybe dispense with this
                    // would imagine there will be files much larger than this
                    // so need to adjust configs
                    // END MARTIN COMMENT
                    $fileName = $_FILES['file']['name'];
                    //$saveName = sprintf('%s%s', $jobDir, $fileName); // commented out by Martin before 2019
        
                    $parts = explode(".", $fileName);        
                    $firstpart = implode(".", array_slice($parts, 0, count($parts) - 1));        
                    if (count($parts)) {        
                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                        //if (is_dir($fileDir)){
                        //	if ($dh = opendir($fileDir)){
                        //		while (($file = readdir($dh)) !== false){
                        //			if (is_file($fileDir . $file)){
                        //				@unlink($fileDir . $file);
        
                        //			}
                        //		}
                        //		closedir($dh);
                        //	}
                        //}        
                        // END COMMENTED OUT BY MARTIN BEFORE 2019
        
                        $ext = strtolower(end($parts));        
                        if (in_array($ext, $allowedExtensions)) {        
                            $saveName = sprintf('%s%s', $fileDir, $type . '_' . $id . '.' . $ext);
        
                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                            //while (file_exists($saveName)){        
                            //	$saveName = sprintf('%s%s', $fileDir, $firstpart . '.' . time() . '.' . $ext);                                    
                            //}        
                            //if (file_exists($saveName)){        
                            //	header('HTTP/1.0 403 Forbidden');
                            //	echo "That File Already Exists";
                            //	die();        
                            //} else {
                            // END COMMENTED OUT BY MARTIN BEFORE 2019
                            
                            if (move_uploaded_file($_FILES['file']['tmp_name'], $saveName)) {                                    
                                $success = true;
                                    
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                //$result = insertFile($fileName, $saveName, $jobId, $USR_ID, $connect);
                                //if (!$result){
                                //		@unlink($saveName);
                                //	} else {
                                //		$success = true;
                                //	}
                                // END COMMENTED OUT BY MARTIN BEFORE 2019
                            }
                            //} // COMMENTED OUT BY MARTIN BEFORE 2019        
                        }        
                    }        
                }                    
            }            
        }
        
        // Now that icon has changed, force reload of appropriate frame.
        // BEGIN REMOVED 2020-01-07 JM
        /*
        if ($type == 'et') {
            ?>
            <script type="text/javascript">
                parent.elementType.location.reload();
            </script>
            <?php
        }
        if ($type == 'dc') {
            ?>
            <script type="text/javascript">
                parent.descriptorCategory.location.reload();
            </script>
            <?php
        }
        if ($type == 'd') {
            ?>
            <script type="text/javascript">
                parent.descriptor.location.reload();
            </script>
            <?php
        }	
        if ($type == 'ds') {
            ?>
            <script type="text/javascript">
                parent.descriptorSub.location.reload();
            </script>
            <?php
        }
        */
        // END REMOVED 2020-01-07 JM
        
        // BEGIN ADDED 2020-01-07 JM
        // Inform parent of new icon
        echo '        <script type="text/javascript">' . "\n";
        echo "             parent.iconRefresh('$id');\n";
        echo '        </script>' . "\n";
        // END ADDED 2020-01-07 JM        
        
    } // END if ($act == 'upload')
    
    // form to upload a different icon; lets user choose a file & click submit button labeled "Upload File" to self-submit with $act == 'upload'
    ?>
    
    <form action="icon.php" method="post" enctype="multipart/form-data">
        Select file to upload:
        <input type="hidden" name="act" value="upload">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
        <input type="file" name="file" id="file"><br/>
        <input type="submit" value="Upload File" name="submit">
    </form>
    
    <?php
}
?>
</body>
</html>
