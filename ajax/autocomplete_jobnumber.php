<?php 
/*  ajax/autocomplete_jobnumber.php

    INPUT $_REQUEST['q'] ('q' for "query") is interpreted as a search string. Blanks are treated as multicharacter wild cards (SQL '%'),
    and a match is made against any portion of the job number. We "favor" matches at the start of the job location.
    
    Looks for matching locations in DB table job, >>>00004: DOES NOT CONSIDER whether job is for current customer.
    
    Returns JSON for an associative array with the following members:
      * 'query': the original query string q.
      * 'suggestions': an array of pairs value=>data, where value is Job Number 
         followed by parenthesized job name, and data is jobId, with matches at the 
         start of the formatted address coming before matches elsewhere.
*/

include '../inc/config.php';
include '../inc/access.php';

$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

$db = DB::getInstance();

$parts = explode(" ", $q);
$str = '';
foreach ($parts as $part) {
    // Not the first    
    if (strlen($str)) {
        $str .= '%';
    }
    $str .= $db->real_escape_string($part);
}

// Look for matches at the start of job.number
// Limited to 50 matches
$query = "select name, jobId, number  from " . DB__NEW_DATABASE . ".job ";
$query .= " where number like '" . $str . "%' ";
$query .= " order by number desc limit 50 ";

$jobs = array();
if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {            
            $new = array();
            $new['value'] = $row['number'] . ' (' . $row['name'] . ')';
            $new['data'] = $row['jobId'];
            $jobs[] = $new;
        }
    }
} // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.

// Look for matches NOT at the start of job.number
// Limited to 50 matches
$query = "select name,jobId,number  from " . DB__NEW_DATABASE . ".job ";
$query .= " where number like '%" . $str . "%' ";
$query .= " and jobId not in  (";
    $query .= "select jobId  from " . DB__NEW_DATABASE . ".job ";
    $query .= " where number like '" . $str . "%' ";
$query .= ") ";
$query .= " order by number desc  limit 50 ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $new = array();
            $new['value'] = $row['number'] . ' (' . $row['name'] . ')';
            $new['data'] = $row['jobId'];
            $jobs[] = $new;
        }
    }
}

$data = array();
$data['query'] = $q;
$data['suggestions'] = $jobs;

header('Content-Type: application/json');

echo json_encode($data);

die();
?>