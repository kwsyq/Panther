<?php
/* ajax/log.php

    Introduced 2020-07 JM for v2020-4 to provide a way for client-side code to write to the syslog

    INPUTS
        $_REQUEST['errorId'] : unique numeric ID from the place in the code where we log. Although we call this an errorId, we
                               also use it when logging at other levels of severity, e.g. info. WE DO NOT VALIDATE THIS beyond
                               it having no more than 100 characters; we would not want this to fail because the caller got this wrong.
        $_REQUEST['severity'] : should be one of 'trace', 'debug', 'info', 'warn', 'error', 'fatal'; will default to 'error' if none of those.
        $_REQUEST['text'] : arbitrary text to enter in the log.
        
        NOTE that we do NOT use Validator2 here: we want to deal with all the weird cases ourselves.
        
        Returns JSON for an associative array with the following members:
        * 'status': In theory could return "fail", and calling code should account for that, but in practice always returns "success"        


*/
include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';

$errorId = '1595435536'; // arbitrary fallback
if (array_key_exists('errorId', $_REQUEST) && $_REQUEST['errorId']) {
    $errorId = trim(substr($_REQUEST['errorId'], 0, 100));
} else {
    $logger->error2('1595435675', 'Missing or empty errorId');
}

$severity = 'error'; // arbitrary fallback
$valid_severities = Array('trace', 'debug', 'info', 'warn', 'error', 'fatal');
if (array_key_exists('severity', $_REQUEST) && $_REQUEST['severity']) {
    $requested_severity = $_REQUEST['severity'];
    if (in_array($requested_severity, $valid_severities)) {
        $severity = $requested_severity; 
    } else {
        $logger->error2('1595435625', 'Invalid severity "' . $requested_severity . '"');
    }
} else {
    $logger->error2('1595435775', 'Missing or empty severity');
}

$text = array_key_exists('text', $_REQUEST) ? $_REQUEST['text'] : '';

if ($severity == 'trace') {
    $logger->trace2($errorId, $text);
} else if ($severity == 'debug') {
    $logger->debug2($errorId, $text);
} else if ($severity == 'info') {
    $logger->info2($errorId, $text);
} else if ($severity == 'warn') {
    $logger->warn2($errorId, $text);
} else if ($severity == 'error') {
    $logger->error2($errorId, $text);
} else if ($severity == 'fatal') {
    $logger->fatal2($errorId, $text);
}

$data['status'] = 'success';

header('Content-Type: application/json');
echo json_encode($data);
?>