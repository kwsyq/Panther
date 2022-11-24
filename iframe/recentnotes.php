<?php 
/* iframe/recentnotes.php
   
   EXECUTIVE SUMMARY: Display notes related to some row in one or another DB table. 
   Despite the file name, this seems to get all such notes, not just recent notes.

   No deep reason iframe directory is separate from fb, except that it doesn't use the "fancybox".

   INPUTs: Exactly one of the following should be set on input (>>>00016, >>>00002: although as of 2019-04 that is not validated);
   the others should be blank or zero.
        $_REQUEST['jobId']
        $_REQUEST['personId']
        $_REQUEST['companyId']
        $_REQUEST['workOrderTaskId']
        $_REQUEST['workOrderId']   
*/

include '../inc/config.php';

// ADDED by George 2020-07-28, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add
$error = '';
$errorId = 0;
$notes = array();
$reversed = array();


$jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;


//  George 2020-07-28 : check to make sure it has exactly one of the above four inputs.
$numPrimaryInputs = ($jobId ? 1 : 0) + ($personId ? 1 : 0) + ($companyId ? 1 : 0) +  ($workOrderTaskId ? 1 : 0) + ($workOrderId ? 1 : 0);
if ($numPrimaryInputs == 0) {
    $errorId = '637315508869095724';
    $error = "Must have one of jobId, personId, companyId, workOrderTaskId, workOrderId; none of these were present in input.";
    $logger->error2($errorId, $error);
    $_SESSION["error_message"] =  $error; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
} else if ($numPrimaryInputs > 1) {
    $errorId = '637315508951619527';
    $error = trim( "Must have exactly one of jobId, personId, companyId, workOrderTaskId, workOrderId; input gave: " .
             ($jobId ? 'jobId ' : '') . ($personId ? 'personId ' : '') . ($companyId ? 'companyId ' : '') . ($workOrderTaskId ? 'workOrderTaskId ' : '') . ($workOrderId ? 'workOrderId' : '') );
    $logger->error2($errorId, $error);
    $_SESSION["error_message"] = $error; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}


if (!$error) {
    // George 2020-07-28. Removed Code.
    /*
    if (intval($jobId)){
        $job = new Job($jobId);
        // Martin comment: later fix this to fial unless a valid job and/or actual data to return
        // JM: I suspect "fial" => "fail", but maybe he meant something else. Can't see what else would make sense here.
        $notes = $job->getNotes();
    }
    if (intval($personId)){
        $person = new Person($personId);
        $notes = $person->getNotes();
    }
    if (intval($companyId)){
        $company = new Company($companyId);
        $notes = $company->getNotes();
    }
    if (intval($workOrderTaskId)){
        $workOrderTask = new WorkOrderTask($workOrderTaskId);
        $notes = $workOrderTask->getNotes();
    }
    if (intval($workOrderId)){
        $workOrder = new WorkOrder($workOrderId);
        $notes = $workOrder->getNotes();
    } */
    //End Removed Code.

    // George 2020-07-28 Added new Code.
    $obj = null;
    
    if ($jobId) {
        if (!Job::validate($jobId)) {
            $errorId = '637315504007845271';
            $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing DB row in job table");
            $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new Job($jobId);
    }
    else if ($personId) {
        if (!Person::validate($personId)) {
            $errorId = '637315504153754954';
            $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB row in person table");
            $_SESSION["error_message"] = "PersonId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new Person($personId);
      
    }
    else if ($companyId) {
        if (!Company::validate($companyId)) {
            $errorId = '637315504362950961';
            $logger->error2($errorId, "The provided companyId ". $companyId ." does not correspond to an existing DB row in company table");
            $_SESSION["error_message"] = "CompanyId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new Company($companyId);
     
    }
    else if ($workOrderTaskId) {
        if (!WorkOrderTask::validate($workOrderTaskId)) {
            $errorId = '637315504528910594';
            $logger->error2($errorId, "The provided workOrderTaskId ". $workOrderTaskId ." does not correspond to an existing DB row in workOrderTask table");
            $_SESSION["error_message"] = "WorkOrderTaskId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new WorkOrderTask($workOrderTaskId);
     
    }
    else if ($workOrderId) {
        if (!WorkOrder::validate($workOrderId)) {
            $errorId = '637315504630185632';
            $logger->error2($errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB row in workOrder table");
            $_SESSION["error_message"] = "WorkOrderId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new WorkOrder($workOrderId);
     
    }
    if ($obj) { 
        $notes = $obj->getNotes();
    }
    if ($notes === null) { //null on query failed.
        $errorId = '637315505364955052';
        $error = "We could not display the Recent Notes for this ".get_class($obj).". Database Error.";
        $logger->errorDb($errorId, "getNotes() method failed ", $db = DB::getInstance());
    } else {
        $reversed = array_reverse($notes); // Prior to this, notes are in forward chronological order, we want backward chronological
    }
   
}
include '../includes/header_fb.php';

if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} 
?>
<style>

body{
    background: none;
}

</style>
<div class="main-content">
    <?php
    if (!$error) {
        // $nkey in the following is 0-based numeric
        foreach ($reversed as $nkey => $note) {    
            $time = 'n/a';
            
            if ($note['inserted'] != '0000-00-00 00:00:00') {            
                $date = new DateTime($note['inserted']);
                $time = $date->format('n/j/Y g:i A');            
            }
            
            $by = '';
            
            if ($note['person']) {
                $person = $note['person'];
                if (intval($person->getPersonId())){
                    $by = $person->getFirstName() . '&nbsp;' . $person->getLastName();
                }
            }
        
            if (strlen($by)) {
                $by = '&nbsp;by&nbsp;' . $by;
            }
            
            // Notes will be numbered in descending order, with the oldest note geting number 1.
            echo '<i>#' . (count($notes) - $nkey) . '&nbsp;(' . htmlspecialchars($time) . ')' . $by . '</i>';
            echo '<br/>';
            echo $note['noteText'];
            echo '<br/>';	
            echo '<br/>';
        } // END foreach ($reversed...
    }
    ?>

</div>

<?php
include '../includes/footer_fb.php';
?>