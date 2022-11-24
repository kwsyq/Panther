<?php 
/*  ajax/tooltip_location.php

    INPUT $_REQUEST['locationId']: primary key to DB table Location
    
    Just returns misc info about a person.

    Returns JSON for an associative array with the following members:
        * 'formattedAddress'
*/        

include '../inc/config.php';
include '../inc/access.php';

$locationId = isset($_REQUEST['locationId']) ? intval($_REQUEST['locationId']) : 0;
$location = new Location($locationId, $user);

$data = array();
$data['locationId'] = $locationId;
$data['formattedAddress'] = $location->getFormattedAddress();

header('Content-Type: application/json');
echo json_encode($data);
die();

?>