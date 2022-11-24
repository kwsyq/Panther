<?php
/*  _admin/ajax/holiday.php

    EXECUTIVE SUMMARY: Insert or replace a row in DB table PTO for a particular 
    person, date, and specified number of minutes, with ptoTypeId=PTOTYPE_HOLIDAY.
   
    INPUT: $_REQUEST['id'] of the form personId_date, with date in 'Y-m-d' form. 
            personId is a primary key in DB table Person.
            >>>00016 NOTE that we don't validate personId; if it's invalid, this could violate referential integrity.
    INPUT: $_REQUEST['value'] number of minutes. 
   
    You can also deliberately not pass $_REQUEST['value'] or — theoretically — pass it as 
    something that won't evaluate to an integer to make this just a deletion, rather than an 
    insert/replacement.

    Inserts or replaces a row for this person, date, and ptoTypeId=PTOTYPE_HOLIDAY in DB table pto, indicating the specified number of minutes.

    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'; 'fail' means either $_REQUEST['id'] failed formal validation (not integer or not 'Y-m-d', as expected), 
            or that $_REQUEST['value'] was an integer but the insertion somehow failed.
*/

include '../../inc/config.php';
include '../../inc/access.php';

function validateDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

$data = array();  // the return
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';

$parts = explode("_", $id);

$db = DB::getInstance();

if (is_array($parts)) {
    if (count($parts) == 2) {
        if (intval($parts[0])) {
            if (validateDate($parts[1], 'Y-m-d')) {
                $query = " delete from " . DB__NEW_DATABASE . ".pto ";
                $query .= " where ptoTypeId = " . intval(PTOTYPE_HOLIDAY) . " ";
                $query .= " and personId = " . intval($parts[0]) . " ";
                $query .= " and day = '" . $db->real_escape_string($parts[1]) . "' ";

                $db->query($query); // >>>00002 ignores failure on DB query!
                
                if (intval($value)) {            
                    $query = " insert into " . DB__NEW_DATABASE . ".pto (ptoTypeId, personId, day, minutes) values (";
                    $query .= " " . intval(PTOTYPE_HOLIDAY) . " ";
                    $query .= " ," . intval(intval($parts[0])) . " ";
                    $query .= " ,'" . $db->real_escape_string($parts[1]) . "' ";
                    $query .= " ," . intval($value) . ") ";
                        
                    $db->query($query); // >>>00002 ignores failure on DB query!

                    $inserted_id = $db->insert_id;
                    
                    if (intval($inserted_id)) {                        
                        $data['status'] = 'success';                        
                    }                    
                } else {                    
                    $data['status'] = 'success';                    
                }
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>