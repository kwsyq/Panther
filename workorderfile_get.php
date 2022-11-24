<?php 
/*  workorderfile_get.php

    EXECUTIVE SUMMARY: download file associated with a workOrder.
    Despite being top-level this is not a web page.
    User must have read-level invoice permissions.

    PRIMARY INPUT: $_REQUEST['f'] identifies a file in a subfolder of ssseng_documents/uploaded_wo_files,
    where ssseng_documents is at the same level as document root. 
*/

require_once './inc/config.php';
require_once './inc/perms.php';

//User must have admin-level payment permissions; otherwise, dies immediately.
$checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_R);
if (!$checkPerm) {
	$logger->warn2('1600114977', 'The logged-in user has no permissions to access this script [id = ' . $user->getPersonId() .'] '. $user->getFormattedName());
    die(); 
}

$fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/uploaded_wo_files/';

if (file_exists($fileDir)) {	
    $filename = isset($_REQUEST['f']) ? $_REQUEST['f'] : null;	
    if ($filename) {				
        // >>>00016, >>>00002: should validate $filename. What if it started with '../..', for example?
        //  Right now you could access any file on the system!

        if (file_exists($fileDir . $filename)) {
            // Code here is basically copied from credrec_getuploadfile.php
            // Relies on file system to suggest MIME type (finfo(FILEINFO_MIME_TYPE). 
            // JPEGs and PNGs get just a 'Content-Type' HTTP header followed by image content. 
            // Anything else gets a more complicated set of headers.  
            // >>>00001 Looks to me (JM) like the intent is to prevent any caching, but Martin says no special 
            //  intent here, just copy-pasted headers from elsewhere.
            
            // [BEGIN MARTIN COMMENT]
            // we should test the reliabilty of this (mime detection).
            // otherwise manually map extensions ot mime types
            // and allow certain ones etc.  or maybe just generic binary type
            // [END MARTIN COMMENT]
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($fileDir . $filename);
            $mime = strtolower($mime);
            if (($mime == 'image/jpeg') || ($mime == 'image/jpg')) {
                $im = imagecreatefromjpeg($fileDir . $filename);
                header('Content-Type: image/jpeg');
                imagejpeg($im);
                imagedestroy($im);
            } else if (($mime == 'image/png')) {
                $im = imagecreatefrompng($fileDir . $filename);
                header('Content-Type: image/png');
                imagepng($im);
                imagedestroy($im);
            } else {
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $mime);
                // "Content-Disposition: attachment" here effectively means anything  
                //  other than JPEGs & PNGs will be treated as a file download.
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fileDir . $filename));
                
                readfile($fileDir . $filename);
            }        
        } else {
            $logger->warn2('1600115101', 'The requested file does not exist: '.$fileDir.$filename);
        } 
    } else {
        $logger->warn2('1600115124', '$_REQUEST does not indicates a filename (not set or empty)');
    } 
} else {
    $logger->warn2('1600115138', 'Top-level directory for uploaded_wo_files does not exist, should be ' . $fileDir);
} 

?>