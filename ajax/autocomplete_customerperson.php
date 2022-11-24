<?php
/*  ajax/autocomplete_customerperson.php

    INPUT $_REQUEST['q'] ('q' for "query") is interpreted as a search string. Blanks are treated as multicharacter wild cards (SQL '%'),
    and a match is made against any portion of the first or last name.
        
    Looks for matching first or last names in table person, where there is a relation 
     via table customerPerson to the current customer (as of 2019-05 always SSS).

    Returns JSON for an associative array with the following members:
      * 'query': the original query string q.
      * 'suggestions': an array of pairs value=>data, where value is a lastName + space + firstName 
         and data is a personId; ordered by lastName, firstName. 
*/

include '../inc/config.php';
include '../inc/access.php';

$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

$db = DB::getInstance();

$parts = explode(" ", $q);
$str = '';
foreach ($parts as $part) {
    if (strlen($str)) {
        // Not at start
        $str .= '%';
    }
    $str .= $db->real_escape_string($part);
}

$persons = array();

if (is_object($customer)) {        
    if ($customer instanceof Customer) {        
        $query = " select p.personId, p.firstName, p.lastName ";
        $query .= " from " . DB__NEW_DATABASE . ".customerPerson cp ";
        $query .= " join " . DB__NEW_DATABASE . ".person p on cp.personId = p.personId ";
        $query .= " where cp.customerId = " . intval($customer->getCustomerId()) . " ";
        $query .= " and( p.firstName like '%" . $str . "%' ";
        $query .= " or p.lastName like '%" . $str . "%') ";        
        $query .= " order by p.lastName, p.firstName ";

        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {                    
                    $new = array();
                    $new['value'] = $row['lastName'] . ' ' . $row['firstName'];
                    $new['data'] = $row['personId'];
                    $persons[] = $new;
                }
            }
        } // >>>00002 ignores failure on DB query!
    }        
}

$data = array();
$data['query'] = $q;
$data['suggestions'] = $persons;

header('Content-Type: application/json');

echo json_encode($data);
die();
?>