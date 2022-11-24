<?php
/* ajax/autocomplete_joblocation.php

    INPUT $_REQUEST['q'] ('q' for "query") is interpreted as a search string. Blanks are treated as multicharacter wild cards (SQL '%'),
    and a match is made against any portion of the job name. We "favor" matches at the start of the job location.
    
    Looks for matching locations in DB table job, >>>00004: DOES NOT CONSIDER whether job is for current customer.
    
    Returns JSON for an associative array with the following members:
      * 'query': the original query string q.
      * 'suggestions': an array of pairs value=>data, where value is formatted address 
         followed by parenthesized Job Number, and data is jobId, with matches at the 
         start of the formatted address coming before matches elsewhere.
*/

include '../inc/config.php';
include '../inc/access.php';

$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

$db = DB::getInstance();

$parts = explode(" ", $q);
$str = '';
foreach ($parts as $part) {
    // Not at start    
    if (strlen($str)) {
        $str .= '%';
    }
    $str .= $db->real_escape_string($part);
}

// Look for matches at the start of address1
// Limited to 50 matches
/* BEGIN REPLACED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
$query = " select l.address1, l.address2, j.name, j.number, l.locationId, j.jobId  ";
$query .= " from " . DB__NEW_DATABASE . ".job j ";
$query .= " join " . DB__NEW_DATABASE . ".jobLocation jl on j.jobId = jl.jobId ";
$query .= " join " . DB__NEW_DATABASE . ".location l on jl.locationId = l.locationId ";
$query .= " where l.address1 like '" . $str . "%' ";
// END REPLACED JM 2020-05-11
*/    
// BEGIN REPLACEMENT JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
$query .= "SELECT l.address1, l.address2, j.name, j.number, l.locationId, j.jobId ";
$query .= "FROM " . DB__NEW_DATABASE . ".job j ";
$query .= "JOIN " . DB__NEW_DATABASE . ".location l ON j.locationId = l.locationId ";
$query .= "WHERE l.address1 LIKE '" . $str . "%' ";
// END REPLACEMENT JM 2020-05-11

$query .= " order by j.number desc limit 50  ";

$locations = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {            
            $l = new Location(intval($row['locationId']));
            $a = $l->getFormattedAddress();            
            $new = array();
            $new['value'] = $a . ' (' . $row['number'] . ')';
            $new['data'] = $row['jobId'];
            $locations[] = $new;
        }
    }
} // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.

// Look for matches NOT at the start of address1
// Limited to 50 matches
/* BEGIN REPLACED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
$query = " select l.address1, l.address2, j.name, j.number, l.locationId, j.jobId  ";
$query .= " from " . DB__NEW_DATABASE . ".job j ";
$query .= " join " . DB__NEW_DATABASE . ".jobLocation jl on j.jobId = jl.jobId ";
$query .= " join " . DB__NEW_DATABASE . ".location l on jl.locationId = l.locationId ";
$query .= " where l.address1 like '%" . $str . "%' ";
$query .= " and l.locationId not in (";
    $query .= " select l.locationId  ";
    $query .= " from " . DB__NEW_DATABASE . ".job j ";
    $query .= " join " . DB__NEW_DATABASE . ".jobLocation jl on j.jobId = jl.jobId ";
    $query .= " join " . DB__NEW_DATABASE . ".location l on jl.locationId = l.locationId ";
    $query .= " where l.address1 like '" . $str . "%' ";
$query .= ") ";
// END REPLACED JM 2020-05-11
*/    
// BEGIN REPLACEMENT JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
$query .= "SELECT l.address1, l.address2, j.name, j.number, l.locationId, j.jobId  ";
$query .= "FROM " . DB__NEW_DATABASE . ".job j ";
$query .= "JOIN " . DB__NEW_DATABASE . ".location l ON j.locationId = l.locationId ";
$query .= "WHERE l.address1 LIKE '%" . $str . "%' ";
$query .= "AND l.locationId NOT IN (";
    $query .= "SELECT l.locationId  ";
    $query .= "FROM " . DB__NEW_DATABASE . ".job j ";
    $query .= "JOIN " . DB__NEW_DATABASE . ".location l ON j.locationId = l.locationId ";
    $query .= "WHERE l.address1 LIKE '" . $str . "%' ";
$query .= ") ";
// END REPLACEMENT JM 2020-05-05
$query .= "   order by j.number desc  limit 50 ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {                
            $l = new Location(intval($row['locationId']));
            $a = $l->getFormattedAddress();            
            $new = array();
            $new['value'] = $a . ' (' . $row['number'] . ')';
            $new['data'] = $row['jobId'];
            $locations[] = $new;
        }
    }
}

$data = array();

$data['query'] = $q;
$data['suggestions'] = $locations;

header('Content-Type: application/json');

echo json_encode($data);

die();
?>