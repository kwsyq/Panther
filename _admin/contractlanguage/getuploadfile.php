<?php
/*  _admin/contractlanguage/getuploadfile.php

    Get a contract language file (with appropriate HTTP headers).

    INPUT $_REQUEST['f']: filename in folder ../ssseng_documents/contract_language (relative to $_SERVER['DOCUMENT_ROOT']). 

    RETURN: 
        * If the folder and file exist, echo the file with appropriate HTTP headers. 
        * Otherwise, do nothing. >>>00006 JM: why not return a 404 if it doesn't exist?    
*/

include '../../inc/config.php';
include '../../inc/access.php';
//if ($user->isEmployee()){ // Commented out by Martin before 2019
    /* 
    OLD CODE removed 2019-02-06 JM
    $sep = DIRECTORY_SEPARATOR;
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . $sep . '..' . $sep . 'ssseng_documents' . $sep . 'contract_language' . $sep;
    */
    // BEGIN NEW CODE 2019-02-06 JM
    $fileDir = $_SERVER['DOCUMENT_ROOT'] . '/../' . CUSTOMER_DOCUMENTS . '/contract_language/';
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

                header('Content-Description: File Transfer');
                header('Content-Type: ' . $mime);
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fileDir . $filename));
    
                readfile($fileDir . $filename);
    
                die();    
            }    
        }    
    }    
//} // Commented out by Martin before 2019
?>