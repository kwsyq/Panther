<?php
/*  ajax/autocomplete_company.php

    INPUT $_REQUEST['q'] ('q' for "query") is interpreted as a search string. Blanks are treated as multicharacter wild cards (SQL '%'), 
    '%' is appended to beginning and end of the string as well. So the match can be anywhere in the string, not
    just at the beginning.
    
    Looks for matching companyNames in table company. >>>00004: No consideration of whether company has any relation to current customer.
    
    Returns JSON for an associative array with the following members:
      * 'query': the original query string q.
      * 'suggestions': an array of pairs value=>data, where value is a companyName 
         and data is a companyId; ordered by companyName. 
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

$query = "select companyId, companyName from " . DB__NEW_DATABASE . ".company ";
$query .= " where companyName like '%" . $str . "%' ";
$query .= " order by companyName ";

$companies = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {            
            $new = array();
            $new['value'] = $row['companyName'];
            $new['data'] = $row['companyId'];
            $companies[] = $new;
        }
    }
} // >>>00002 ignores failure on DB query!

$data = array();
$data['query'] = $q;
$data['suggestions'] = $companies;

header('Content-Type: application/json');
echo json_encode($data);
die();
?>