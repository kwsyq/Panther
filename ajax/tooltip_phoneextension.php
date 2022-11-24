<?php
/*  ajax/tooltip_phoneextension.php

    INPUT $_REQUEST['phoneExtensionId']: primary key to DB table phoneExtension

    Unlike the other tooltip AJAX functions, this does not return its input at all. 
    Returns JSON for an array of associative arrays, each with the following members:    
        * 'extensionType': a form of the phone extension type intended for programmatic use.
        * 'extensionTypeDisplay': a form of the phone extension type intended for display
        * 'displayOrder': display order for the phone extension type
        * 'extension': actual extension number
        * 'description': open-ended, per extension. 
    
    NOTE that this does not return any identification of the person associated with the extension, though that could be derived.

*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$phoneExtensionId = isset($_REQUEST['phoneExtensionId']) ? intval($_REQUEST['phoneExtensionId']) : 0;

$data = array();

$query = " select pet.extensionType, pet.extensionTypeDisplay, pet.displayOrder, pe.extension, pe. description ";
$query .= " from " . DB__NEW_DATABASE . ".phoneExtension pe ";
$query .= " join " . DB__NEW_DATABASE . ".phoneExtensionType pet on pe.phoneExtensionTypeId = pet.phoneExtensionTypeId ";
$query .= " where pe.phoneExtensionId = " . intval($phoneExtensionId);

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data = $row;
        }
    }
} // >>>00002 ignores failure on DB query!

header('Content-Type: application/json');
echo json_encode($data);
die();

?>