<?php 
/*  ajax/get_emails_locations_cp_person.php

    INPUT $_REQUEST['companyPersonId']: primary key in DB table companyPerson
    
    Get all emails and locations associated with a particular companyPerson. Returns only emails
     and locations associated via the Person side of the relationship, ignores ones associated only
     with the Company.
    
    Returns JSON for an associative array with the following members:    
        * 'status': "fail" if companyPersonId not valid or any of several other failures, otherwise "success".
        * 'emails': array corresponding to email addresses for the person. Each of these is itself an associative array:
            * 'emailAddress': email address
            * 'personEmailId': id in DB table personEmail. 
        * 'locations': array corresponding to locations for the person. Each of these is itself an associative array:
            * 'formattedAddress': location, formatted
            * 'locationId': id in DB table location
            * 'personLocationId': id in DB table personLocation 
*/

include '../inc/config.php'; 
include '../inc/access.php';

$db = DB::getInstance();  // >>>00007 we don't seem ever to use $db in this file, so kill it.

$data = array();

$data['status'] = 'fail';
$data['emails'] = array();
$data['locations'] = array();

$companyPersonId = isset($_REQUEST['companyPersonId']) ? intval($_REQUEST['companyPersonId']) : 0;

if ($companyPersonId) {
    $cp = new CompanyPerson($companyPersonId);
    
    if (intval($cp->getPersonId())) {
        $personId = $cp->getPersonId();
        
        if (intval($personId)) {
            $person = new Person($personId);
            
            if (intval($person->getPersonId())) {
                $emails = $person->getEmails();
                $locations = $person->getLocations();
                
                foreach ($emails as $email) {                 
                    $emailAddress = trim($email['emailAddress']);
                    
                    if (strlen($emailAddress)) {
                        $data['emails'][] = array('emailAddress' => $emailAddress,
                                'personEmailId' => $email['personEmailId']
                        );
                    }
                }
                
                foreach ($locations as $location) {
                    $l = new Location($location['locationId']);                    
                    $data['locations'][] = array('formattedAddress' => $l->getFormattedAddress(),
                                                'locationId' => $location['locationId'],
                                                'personLocationId' => $location['personLocationId']
                                                );
                }                
                $data['status'] = 'success';
            }            
        }        
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>