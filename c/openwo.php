<?php
/*  openwo.php

    EXECUTIVE SUMMARY: if hash is valid, token is nonzero, etc. we consult  
    DB table Private. We show a page that says "this page will show open workorders for person's name",
    >>>00026 but it doesn't seem actually to do that! PRESUMABLY work in progress.

INPUTS:
    $_REQUEST['e']: expiration time for hash
    $_REQUEST['hash']
    $_REQUEST['t']: token (privateId)
*/

include '../inc/config.php';

$db = DB::getInstance();

if (isPrivateSigned($_REQUEST, PRIVATE_HASH_KEY)) {
    $token = isset($_REQUEST['t']) ? intval($_REQUEST['t']) : 0;
    if ($token) {
        $query = " select * from " . DB__NEW_DATABASE . ".private where privateId = " . intval($token);
        $row = false;
        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0){
                $row = $result->fetch_assoc();
            }
        } // >>>00002 else ignores failure on DB query!

        if ($row) {
            if ($row['privateTypeId'] == PRIVTYPE_OPEN_WO) {
                $personId = $row['id'];
                $person = new Person($personId);
                if (intval($person->getPersonId())) {
                    echo "<h1>this page will show open workorders for " . $person->getFormattedName(1) . '</h1>';
                    
                    // >>>00026 but it doesn't seem actually to do that! PRESUMABLY work in progress.
                }
            }
        }
    }
}
?>
