<?php 
/*  fb/viewframefetch.php

    EXECUTIVE SUMMARY: Despite the generic name, specific to creditRecord files. Streams a credit record file.

    PRIMARY INPUT: $_REQUEST['creditRecordId'], which identifies a row in the creditRecord DB table.
*/

include '../inc/config.php';
include '../inc/perms.php';

// Checks for "revenue" permission; if logged-in user lacks that, dies.
// >>>00002: which sure seems worth logging.
$checkPerm = checkPerm($userPermissions, 'PERM_REVENUE', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    die();
}

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$calcId = isset($_REQUEST['calcId']) ? intval($_REQUEST['calcId']) : 0;

ini_set('display_errors',1);
error_reporting(-1);

$db = DB::getInstance();
$calc = false;
$query = " select * from " . DB_DETAILS . ".calc where calcId = " . intval($calcId) . " ";
$result = $db->query($query);

if ($result) {
    if ($result->num_rows > 0){
        while ($row = $result->fetch_assoc()){
            $calc = $row;
        }
    }
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

$creditRecordId = isset($_REQUEST['creditRecordId']) ? intval($_REQUEST['creditRecordId']) : 0;
$cr = new CreditRecord($creditRecordId);
if (intval($cr->getCreditRecordId())) {
    // file extension
    $ext = pathinfo($cr->getFileName(), PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    
    /* OLD CODE REMOVED 2019-02-15 JM
    $sep = DIRECTORY_SEPARATOR;
    //$filePath = BASEDIR . $sep . '../..' . $sep . 'ssseng_documents' . $sep . 'uploaded_checks' . $sep . substr($did, -1) . $sep  . substr($rid, -1) . $sep . $rid . $sep . $calc['fileName'];
    $filePath = BASEDIR . $sep . '..' . $sep . 'ssseng_documents' . $sep . 'uploaded_checks' . $sep . $cr->getFileName();
    */
    // BEGIN NEW CODE 2019-02-15 JM
    $filePath = BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/' . $cr->getFileName();
    // END NEW CODE 2019-02-15 JM
    
    if ($ext == 'pdf') {
        // After verifying that the file exists (this all becomes a no-op if it doesn't), 
        //  we make return affix appropriate HTTP headers and stream the file with PHP readfile. 
        if (file_exists($filePath)) {        
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($filePath);
        
            $newname = 'hey.pdf'; // Martin comment: $calc['originalName'];
        
            //header('Content-Description: File Transfer'); // Commented out by Martin before 2019
            header('Content-Type: ' . $mime);
            //header('Content-Disposition: attachment; filename="' . $file['originalName'] . '"'); // Commented out by Martin before 2019
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
        
            readfile($filePath);        
            die();        
        }
    } else if (($ext == 'jpeg') || ($ext == 'jpg') || ($ext == 'png')) {
        //  Affix appropriate HTTP headers and stream the content either with PHP 
        //   imagecreatefromjpeg + imagejpeg + imagedestroy or 
        //   imagecreatefrompng + imagepng+ imagedestroy.
        if (($ext == 'jpeg') || ($ext == 'jpg')) {                
            $im = imagecreatefromjpeg($filePath);
            header('Content-Type: image/jpeg');
            imagejpeg($im);
            imagedestroy($im);                
        } else if (($ext == 'png')) {
            $im = imagecreatefrompng($filePath);
            header('Content-Type: image/png');
            imagepng($im);
            imagedestroy($im);        
        }
    } else {
        // >>>00002 unsupported filetype, should log
    }
} // >>>00002 else bad ID, should log

die();
?>