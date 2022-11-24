<?php
/* /etc/filldata2.php
    EXECUTIVE SUMMARY: In DB tables 'contract' and 'invoice', fill in 'data2' 
    columns based on 'data' columns.
    
    Optional argument: -force  If present, means that we kill all prior data2 values.

    NO INPUTs. This is a one-time, global action as part of the v2020-4 release.
*/
require_once '../inc/config.php';

if (!(php_sapi_name() === 'cli')) {
    echo "Not to be run from web, must be run from a command line\n";
    $logger->info2('1605547932', 'Attempted to run etc/filldata2.php from web, must be run from command line.');
	die();
}

$logger->info2('1600807550', 'Running etc/filldata2.php');

$force = false;

for ($i=0; $i < count($argv); ++$i) {
    $value = $argv[$i]; 
    if ($value == '-force') {
        $force = true;
    }
}

$db = DB::getInstance();

if ($force) {
    $query = "UPDATE " . DB__NEW_DATABASE . ".contract ";
    $query .= "SET data2=NULL;";

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1605550193', "Hard DB error", $db);
        echo "FAILED: error 1605550193";
        die();
    }    

    $query = "UPDATE " . DB__NEW_DATABASE . ".invoice ";
    $query .= "SET data2=NULL;";

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1605550246', "Hard DB error", $db);
        echo "FAILED: error 1605550246";
        die();
    }
    echo "Expect this to take up to 30 seconds.\n";
} else {
    echo "Expect this to take up to 30 seconds if it has not previously been run on this data.\n";
}

// Get all contracts that have non-null 'data'
$query = "SELECT contractId, data ";
$query .= "FROM " . DB__NEW_DATABASE . ".contract ";
$query .= "WHERE data2 IS NULL AND data IS NOT NULL;";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1605548583', "Hard DB error", $db);
    echo "FAILED: error 1605548583";
    die();
}

while ($row = $result->fetch_assoc()) {
    // Yes, it is weird to call a constructor without even setting a variable
    //  to refer to the object, but the constructor has the side
    //  effect of filling in data2 and saving, so we accomplish all we need 
    //  just by constructing it!
    new Contract($row['contractId']);
}

// Now do the same thing for invoices
$query = "SELECT invoiceId, data ";
$query .= "FROM " . DB__NEW_DATABASE . ".invoice ";
$query .= "WHERE data2 IS NULL AND data IS NOT NULL;";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1605548744', "Hard DB error", $db);
    echo "FAILED: error 1605548744";
    die();
}

while ($row = $result->fetch_assoc()) {
    // Yes, it is weird to call a constructor without even setting a variable
    //  to refer to the object, but the constructor has the side
    //  effect of filling in data2 and saving, so we accomplish all we need 
    //  just by constructing it!
    new Invoice($row['invoiceId']);
}

echo "SUCCESS\n";
?>
