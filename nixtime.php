<?php
/* nixtime.php
   Martin says: Just a little thing to return the current (server) time as JSON. Used externally to generate a hash (or something like that).
*/

$ret = array();
$ret['time'] = time();
header('Content-Type: application/json');
echo json_encode($ret);
die();
?>