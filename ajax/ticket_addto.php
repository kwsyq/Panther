<?php 
/*  ajax/ticket_addto.php

    INPUT $_REQUEST['ticketId']
    INPUT $_REQUEST['personId']

    Set specified ticket to be "to" specified person. No action if personId is invalid. 
    Effect: row will be created in table ticketTo (if it doesn't already exist). 
    Note that the same ticket can be assigned to multiple people. 

    >>>00016 Does not validate ticketId. If it's invalid, this messes up referential integrity.
    >>>00016 Also: validates that personId exists, but not that it has any relation to the current customer. 

    No explicit return.
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$ticketId = isset($_REQUEST['ticketId']) ? intval($_REQUEST['ticketId']) : 0;
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;

if (existPersonId($personId)) {
    $exists = false;
    
    $query = " select * from " . DB__NEW_DATABASE . ".ticketTo where ticketId = " . intval($ticketId) . " and personId = " . intval($personId);
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            $exists = true;
        }
    }// >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.

    if (!$exists) {        
        if (!$exists) { // >>>00007 test is completely redundant, get rid of it        
            $query = "insert into " . DB__NEW_DATABASE . ".ticketTo (ticketId, personId) values (";
            $query .= " " . intval($ticketId) . " ";
            $query .= " ," . intval($personId) . " ";
            $query .= ") ";
        
            $db->query($query);
        }
    }
}

?>