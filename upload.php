<?php
/* upload.php

    EXECUTIVE SUMMARY: despite the name "upload.php", this doesn't do an upload, just grabs the filename.
    
    Used only for the dropzone in invoice.php.
    >>>00012: we may want to rename this file, especially because it is SPECIFIC to adding a filename to an invoice.
    
    Completely reworked JM 2020-04-09 to fix part of 
    http://bt.dev2.ssseng.com/view.php?id=113 ( File name capture and Invoice text over-ride on invoice page are not functioning.) 
    
    Martin had left this in a completely broken state, so I am not in any way bothering to preserve a record of his approach, except 
    in the source control history. Rebuilding this modeled loosely on _admin/creditrecord/upload.php, but much simpler because we don't 
    filter to particular file extensions and we don't actually save the file.

    RETURN: 
        * On success returns a '200 OK'; 
        * On failure ( relevant folder doesn't exist and can't be created, or isn't writable), returns a 403 with an appropriate message.
*/

include 'inc/config.php';

// INPUT $invoiceId: The invoice in question
// INPUT $fileName: The fileName to attacy
// INPUT $user: personId of current logged-in user
// RETURNs invoiceFileId of inserted row, or 0 for failure.
function makeEntry($invoiceId, $fileName, $user) {
    global $logger;
    
    $db = DB::getInstance();
    if ($db) {
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceFile(invoiceId, fileName, personId) VALUES (";
        $query .= intval($invoiceId) . ", ";
        $query .= "'" . $db->real_escape_string($fileName) . "', ";    
        $query .= intval($user->getUserId()) . ");";    
        $result = $db->query($query); 
        
        if ($result) {
            $id = $db->insert_id;
            
            if (intval($id)) {
                return $id;
            }
        } else {
            $logger->errorDb('1586462236', "Insert failed", $db);
        }
    }
    return 0;
}

// JM 2020-04-09: >>>00002 If someone wants to rewrite this with Validator2, feel free, but with the totally unusual way this
//  has to return, I didn't bother.

if (!array_key_exists('invoiceId', $_REQUEST)) {
    $errorId='1586460551';
    $logger->error2($errorId, "upload.php for invoice called without invoiceId");
    header('HTTP/1.0 403 Forbidden');
    echo "Failed to insert in DB table invoiceFile, $errorId in syslog";
    die();
}

$invoiceId = intval(trim($_REQUEST['invoiceId']));
if (!Invoice::validate($invoiceId)) {
    $errorId='1586460572';
    $logger->error2($errorId, "upload.php for invoice called with invalid invoiceId $invoiceId");
    header('HTTP/1.0 403 Forbidden');
    echo "Failed to insert in DB table invoiceFile, $errorId in syslog";
    die();
}

// We don't care about files size or allowed extensions, since we are not really uploading.

if ( !array_key_exists('file', $_FILES) || !array_key_exists('name', $_FILES['file']) ) {
    $errorId='1586460584';
    $logger->error2($errorId, "upload.php for invoice $invoiceId called without a filename");
    header('HTTP/1.0 403 Forbidden');
    echo "Failed to insert in DB table invoiceFile for invoice $invoiceId, $errorId in syslog";
    die();
}

$fileName = $_FILES['file']['name'];
$fileNameLength = strlen($fileName); 
if ( $fileNameLength > 255 ) {
    $errorId='1586460599';
    $logger->error2($errorId, "upload.php for invoice $invoiceId, filename too long, max length is 255, was $fileNameLength: '" . substr($fileName, 0, 255). "...'");
    header('HTTP/1.0 403 Forbidden');
    echo "Failed to insert filename '" . substr($fileName, 0, 255). "...' in DB table invoiceFile for invoice $invoiceId, $errorId in syslog";
    die();
}

$id = makeEntry($invoiceId, $fileName, $user);

if (!intval($id)) {
    $errorId='1586460602';
    $logger->error2($errorId, "upload.php for invoice $invoiceId, filename '$fileName': insert failed");
    header('HTTP/1.0 403 Forbidden');
    echo "Failed to insert '$fileName' in DB table invoiceFile for invoice $invoiceId, $errorId in syslog";
    die();    
}

$logger->info2('1586460603', "upload.php inserted filename '$fileName' for invoice $invoiceId");

header('HTTP/1.0 200 OK');

?>