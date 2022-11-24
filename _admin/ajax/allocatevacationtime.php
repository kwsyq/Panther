<?php
/*  _admin/ajax/allocatevacationtime.php

    INPUT $_REQUEST['userId'] - primary key to DB table Person
    INPUT $_REQUEST['customerId'] - primary key to DB table Customer; as of 2019-05, always SSS
    INPUT $_REQUEST['minutes'] - can be negative; should be a multiple of 15 (will round down if not).
    INPUT $_REQUEST['note'] - Accompanying note, up to 255 chars; will be truncated if longer.
    INPUT $_REQUEST['effectiveDate'] - if missing, blank, false, or the string 'false', use current date/time as time issued.
                                If present, will be interpreted as a date in 'YYYY-mm-dd' form; will not have 'hh:mm:ss'.

    EXECUTIVE SUMMARY: Does the actual allocation of new vacation time to an employee.
    Intended to be called by the code generated in _admin/ajax/editvacationtime.php
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'. 
        On fail we also return:
            * 'error': error message(s)
            
    HEAVILY REWRITTEN 2019-10-23 FOR ERROR LOGGING 
*/
require_once '../../inc/config.php';

$v=new Validator2($_REQUEST);

$v->rule('required', ['userId', 'customerId', 'minutes']);
$v->rule('integer', ['userId', 'customerId', 'minutes']);
$v->rule('min', 'userId', 1);
$v->rule('min', 'customerId', 1);
$v->rule('optionaldate', 'effectiveDate');
$v->rule('dividenumber', 'minutes', 15);

$data=array();

if ( !isset($user) || !$user || !intval($user->getUserId()) ) {
    $v->error('loggedUser', 'Only available to logged-in user', []);
}


if(!$v->validate()){
    $logger->error2('1575449799', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

$data['status']='success';

$targetUserId = intval($_REQUEST['userId']);
$customerId = intval($_REQUEST['customerId']);
$minutes = intval($_REQUEST['minutes']);
$note = array_key_exists('note', $_REQUEST) ? $_REQUEST['note'] : '';
$effectiveDate = array_key_exists('effectiveDate', $_REQUEST) ? $_REQUEST['effectiveDate'] : false;
$targetUser = new User($targetUserId, $customer);

$errFromAllocate = $targetUser->allocateVacationTime($minutes, $note, $effectiveDate);

if ($errFromAllocate) {

    $data['status'] = "fail";
    $data['info']   = "Error Allocation - ".$errFromAllocate;

} else {
    $data['status'] = 'success';
}


header('Content-Type: application/json');

echo (json_encode($data));

exit;

class ErrorReporting {
    private $filename;
    private $ajax_error;  // to report back via AJAX
    private $log_error;   // to log on the server
    private $error_id;    // to log on the server
    private $ok; 

    public function __construct($filename) {
        $this->filename = $filename;
        $this->ajax_error = '';
        $this->log_error = '';
        $this->error_id = '';
        $this->ok = true;
    }
    
    public function addError($errId, $err) {
        $this->ok = false;
        if (!$errId) {
            $this->error_id = $errId;  
        }
        if ($this->ajax_error) {
            // not the first
            $this->ajax_error .= '; ';
            // Multiple errors; lets get the additional error IDs into the error string
            $this->log_error .= "; $errId - ";
        } else {
            $this->ajax_error .= $this->filename . ': ';
        }
        $this->ajax_error .= $err;
        $this->log_error .= $err; 
    }
    
    public function getOK() {
        return $this->ok;
    }
    public function getErrorId() {
        return $this->error_id;
    }
    public function getLogError() {
        return $this->log_error;
    }
    public function getAjaxError() {
        return $this->ajax_error;
    }
}

?>
