<?php
/*  i/smsthumb.php

    EXECUTIVE SUMMARY: output a file from the sms_attachments folder. Writes header & file to stdout.
    If no such file, does nothing.

    INPUT $_REQUEST['name']: Filename within CUSTOMER_DOCUMENTS/sms_attachments folder at
       same level as DOCUMENT_ROOT (e.g. within '/var/www/ssseng_documents/sms_attachments' for SSS).
*/
include '../inc/config.php';

if (isPrivateSigned($_REQUEST, SMSMEDIA_HASH_KEY)) {    
    /* OLD CODE REMOVED 2019-02-15 JM
    $sep = DIRECTORY_SEPARATOR;
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '..' . $sep . 'ssseng_documents' . $sep . 'sms_attachments' . $sep;
    */
    // BEGIN NEW CODE 2019-02-15 JM
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/sms_attachments/';
    // END NEW CODE 2019-02-15 JM
    
    if (file_exists($fileDir)) {    
        $filename = isset($_REQUEST['name']) ? $_REQUEST['name'] : null;    
        if ($filename) {    
            //$filename = $jobDir . $file['filename']; // COMMENTED OUT BY MARTIN BEFORE 2019
    
            if (file_exists($fileDir . $filename)){
                // BEGIN MARTIN COMMENT
                // we should test the reliabilty of this (mime detection).
                // otherwise manually map extensions ot mime types
                // and allow certain ones etc.  or maybe just generic binary type
                // END MARTIN COMMENT
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($fileDir . $filename);
    
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $mime);
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fileDir . $filename));
    
                readfile($fileDir . $filename); // sends file straight to output.    
                die();    
            }    
        }    
    }
}

?>