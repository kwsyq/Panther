<?php
/*  ajax/autocomplete_jobname.php (q)

    INPUT $_REQUEST['q'] ('q' for "query") is interpreted as a search string. Blanks are treated as multicharacter wild cards (SQL '%'),
    and a match is made against any portion of the job name. We "favor" matches at the start of the job name.

    Looks for matching names in table job. >>>00004: NO CONSIDERATION OF WHETHER JOB IS FOR current customer.
    
    Returns JSON for an associative array with the following members:    
        * 'query': the original query string q.
        * 'suggestions': an array of pairs value=>data, where value is job name followed by parenthesized Job Number 
            and data is a jobId, with matches at the start of the job name coming before matches elsewhere. 
*/

include '../inc/config.php';
include '../inc/access.php';

$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

$db = DB::getInstance();

$parts = explode(" ", $q);
$str = '';
foreach ($parts as $part) {
    if (strlen($str)) {
        // Not the first
        $str .= '%';
    }
    $str .= $db->real_escape_string($part);
}

// Look for matches at the start of (job) name
// Limited to 50 matches
$query = "select name, jobId, number  from " . DB__NEW_DATABASE . ".job ";
$query .= " where name like '" . $str . "%' ";
$query .= " order by name  limit 50 ";

$jobs = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {            
            $new = array();
            $new['value'] = $row['name'] . ' (' . $row['number'] . ')';
            $new['data'] = $row['jobId'];
            $jobs[] = $new;
        }
    }
} // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.

// Look for matches NOT at the start of (job) name
// Limited to 50 matches
$query = "select name, jobId, number  from " . DB__NEW_DATABASE . ".job ";
$query .= " where name like '%" . $str . "%' ";
$query .= " and jobId not in  (";
    $query .= "select jobId  from " . DB__NEW_DATABASE . ".job ";
    $query .= " where name like '" . $str . "%' ";
$query .= ") ";
$query .= " order by name  limit 50 ";

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $new = array();
            $new['value'] = $row['name'] . ' (' . $row['number'] . ')';
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