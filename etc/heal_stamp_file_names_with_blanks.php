<?php
/* etc/heal_stamp_file_names_with_blanks.php

    One-time process (but harmless to run it again) to change the names of any stamp files 
    that have blanks in the filename to use underscore instead of blank. Also changes
    database accordingly. 
    
    This is intended to "heal" a situation in v2020-3 (as described in http://bt.dev2.ssseng.com/view.php?id=221).
    It *must* be run as part of the conversion to v2020-4, but can be convenient to run before that.    
    
*/

require_once __DIR__.'/../inc/config.php';

if (!(php_sapi_name() === 'cli')) {
    echo "Not to be run from web, must be run from a command line\n";
    $logger->info2('1597782492', 'Attempted to run etc/heal_stamp_file_names_with_blanks.php from web, must be run from command line.');
	die();
}

$db = DB::getInstance();

$logger->info2('1597782535', 'Running heal_stamp_file_names_with_blanks.php');

$saveDir = BASEDIR. '/../' . CUSTOMER_DOCUMENTS . '/stamps';

$query = "SELECT stampId, filename FROM " . DB__NEW_DATABASE . ".stamp;";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1597782599', 'Hard DB error', $db);
    echo "Hard DB error, see log\n";
    die();
}

while ($row = $result->fetch_assoc()) {
    $stampId = $row['stampId'];
    $filename = trim($row['filename']); // trim is probably not needed here, but should be harmless
    $fixed_filename = str_replace(' ', '_', $filename);
    if ($fixed_filename != $filename) {        
        // we need to move the file ... 
        if ( rename("$saveDir/$filename", "$saveDir/$fixed_filename") ) {       
            $logger->info2('1597782860', "moved '$saveDir/$filename' to '$saveDir/$fixed_filename'");
        } else {
            $exists = file_exists("$saveDir/$filename");
            $error_message =  "Failed: move_uploaded_file('$saveDir/$filename', '$saveDir/$fixed_filename'). " .
                ($exists ? 'Source file exists. ' : 'Source file not found. ') .
                "Probably requires manual cleanup.";
            $logger->error2('1597782870',  $error_message);
            echo "$error_message\n";
            die(); 
        }
        
        // ... and modify the DB.
        $query2 = "UPDATE " . DB__NEW_DATABASE . ".stamp SET filename='" . $db->real_escape_string($fixed_filename) . "' ";
        $query2 .= "WHERE stampId = $stampId;";
        $result2 = $db->query($query2);
        if (!$result2) {
            $logger->errorDb('1597783106', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        } else {
            $logger->info2('1597783260', "Fixed stamp table: filename for stampId $stampId is now '$fixed_filename'");
        }
    }
}
    
echo "SUCCESS\n";
   
?>