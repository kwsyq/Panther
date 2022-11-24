<?php 
/*  _admin/creditrecord/getuploadfile.php

    Get a credit record file (with appropriate HTTP headers).

    INPUT $_REQUEST['f']: filename in folder ../ssseng_documents/uploaded_checks (relative to $_SERVER['DOCUMENT_ROOT']). 

    RETURN: 
        * If the folder and file exist, echo the file with appropriate HTTP headers. 
        * Otherwise, do nothing. >>>00006 JM: why not return a 404 if it doesn't exist?    
*/

include '../../inc/config.php';
include '../../inc/access.php';

//if ($user->isEmployee()){ // Commented out by Martin before 2019
    
    /* 
    $sep = DIRECTORY_SEPARATOR;
    OLD CODE removed 2019-02-06 JM
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '..' . $sep . 'ssseng_documents' . $sep . 'uploaded_checks' . $sep;
    */
    // BEGIN NEW CODE 2019-02-06 JM
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/uploaded_checks/';
    // END NEW CODE 2019-02-06 JM	

    if (file_exists($fileDir)) {    
        $filename = isset($_REQUEST['f']) ? $_REQUEST['f'] : null;
    
        if ($filename) {
                
            //$filename = $jobDir . $file['filename']; // Commented out by Martin before 2019
                
            if (file_exists($fileDir . $filename)) {
                // BEGIN MARTIN COMMENT
                // we should test the reliabilty of this (mime detection).
                // otherwise manually map extensions ot mime types
                // and allow certain ones etc.  or maybe just generic binary type
                // END MARTIN COMMENT
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
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($fileDir . $filename));
                    
                    readfile($fileDir . $filename);
                }
                die();
            }
        }
    }
//} // Commented out by Martin before 2019

?>