<?php 
/*  ajax/tooltip_person.php

    INPUT $_REQUEST['personId']: primary key to DB table Person
    
    Just returns misc info about a person.

    Returns JSON for an associative array with the following members:
        * 'personId': as input
        * 'firstName'
        * 'middleName'
        * 'lastName'
        * 'formattedName'
        * 'phones: associative array with the following members
            * 'typeName': e.g. "Office", "Cell"
            * 'phoneNumber' 
*/        

include '../inc/config.php';
include '../inc/access.php';

$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$person = new Person($personId, $user);

$data = array();
$data['personId'] = $person->getPersonId();
$data['firstName'] = $person->getFirstName();
$data['middleName'] = $person->getMiddleName();
$data['lastName'] = $person->getLastName();
$data['formattedName'] = $person->getFormattedName();
$data['phones'] = $person->getPhones();

header('Content-Type: application/json');
echo json_encode($data);
die();

?>