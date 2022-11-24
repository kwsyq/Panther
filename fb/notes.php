<?php 
/*  fb/notes.php

    EXECUTIVE SUMMARY: manage notes for a job, person, company, or workOrder.

    PRIMARY INPUT: Should have one of: 
        $_REQUEST['jobId'], $_REQUEST['personId'], $_REQUEST['companyId'], $_REQUEST['workOrderId']. 

    Optional $_REQUEST['act'] can have values:
        * 'deletenote', which uses $_REQUEST['noteId']
        * 'addnote', which uses $_REQUEST['noteText']. 
*/

include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-07-27, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$notes = array();
$db = DB::getInstance();

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

// Added 2020-03-23 JM: check to make sure it has exactly one of the above four inputs.
$numPrimaryInputs = ($jobId ? 1 : 0) + ($personId ? 1 : 0) + ($companyId ? 1 : 0) + ($workOrderId ? 1 : 0);
if ($numPrimaryInputs == 0) {
    $errorId = '1584995780';
    $error = "Must have one of jobId, personId, companyId, workOrderId; none of these were present in input.";
    $logger->error2($errorId, $error);
    $_SESSION["error_message"] =  $error; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe"; //Different Message for end user (in error.php).
    header("Location: /error.php");
    die(); 
} else if ($numPrimaryInputs > 1) {
    $errorId = '1584995795';
    $error = trim( "Must have exactly one of jobId, personId, companyId, workOrderId; input gave: " .
             ($jobId ? 'jobId ' : '') . ($personId ? 'personId ' : '') . ($companyId ? 'companyId ' : '') . ($workOrderId ? 'workOrderId' : '') );
    $logger->error2($errorId, $error);
    $_SESSION["error_message"] = $error; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}


if (!$error) {
    $obj = null;
    $noteTypeId = 0;
    
    if ($jobId) {
        if (!Job::validate($jobId)) {
            $errorId = '637311095414425173';
            $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing DB row in job table");
            $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new Job($jobId, $user);
        $noteTypeId = NOTE_TYPE_JOB; //1
    }
    else if ($personId) {
        if (!Person::validate($personId)) {
            $errorId = '637311103651122916';
            $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB row in person table");
            $_SESSION["error_message"] = "PersonId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new Person($personId, $user);
        $noteTypeId = NOTE_TYPE_PERSON;	//3
    }
    else if ($companyId) {
        if (!Company::validate($companyId)) {
            $errorId = '637311104083458681';
            $logger->error2($errorId, "The provided companyId ". $companyId ." does not correspond to an existing DB row in company table");
            $_SESSION["error_message"] = "CompanyId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new Company($companyId, $user);
        $noteTypeId = NOTE_TYPE_COMPANY; //4
    }
    else if ($workOrderId) {
        if (!WorkOrder::validate($workOrderId)) {
            $errorId = '637311106447171459';
            $logger->error2($errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB row in workOrder table");
            $_SESSION["error_message"] = "WorkOrderId is not valid. Please check the input!"; // Message for end user
            $_SESSION["errorId"] = $errorId;
            $_SESSION["iframe"] = "iframe";
            header("Location: /error.php");
            die(); 
        }
        $obj = new WorkOrder($workOrderId, $user);
        $noteTypeId = NOTE_TYPE_WORKORDER; //2
    }
    
    
    if ($obj) {
        if ($act == 'deletenote') {
            $v->rule('required', 'noteId');
            $v->rule('integer', 'noteId');
            
            if(!$v->validate()) {
                $errorId = '637314497084446103';
                $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
                $error = "Error in input parameters, please fix them and try again";
            } else {
                $noteId = $_REQUEST["noteId"];
                $success = $obj->deleteNote($noteId);

                if(!$success) {
                    $errorId = '637314581761964896';
                    $error = "We could not delete the Note. Database Error.";
                    $logger->errorDb($errorId, "deleteNote() method failed.", $db);
                }
            }
        }    
        
        if ($act == 'addnote') {
            $v->rule('required', 'noteText');

            if(!$v->validate()) {
                $errorId = '637314589601235404';
                $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
                $error = "Error in input parameters, this field is required";
            } else {
                $noteText = $_REQUEST['noteText'];
                $success = $obj->addNote($noteText);

                if (!$success) {
                    $errorId = '637314589951496252';
                    $error = "We could not delete the Note. Database Error.";
                    $logger->errorDb($errorId, "addNote() method failed.", $db);
                }
            }

        }	
        
        $notes = $obj->getNotes();

        if($notes === null) { //null on query failed. 
            $errorId = '637314657858807035';
            $error = "We could not display the Current Notes for this ".get_class($obj).". Database Error.";
            $logger->errorDb($errorId, "getNotes() method failed.", $db);
        }
    }
}
include '../includes/header_fb.php';

if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} 
?>

<style>
body,.table{max-width: 98%;background:#fff;}
 body, form, table, tr, td {background:#fff;}
 placeholder{ opacity:0.3;}
</style>
    <?php 
    // Existing notes for this object.
    // Each row shows:
    //     * Note text
    //     * A "delete" icon, linked to a call to a self-call to delete this particular note 
    echo '<table cellpadding="5" cellspacing="2" class="table table-bordered table-striped text-left">';
    if (!$error) {
        foreach ($notes as $note) { 
            echo '<tr>';
                echo '<td width="100%">';
                   echo htmlspecialchars($note['noteText']);
                echo '</td>';
                
                if ($jobId) {
                    $link = 'notes.php?act=deletenote&noteId=' . intval($note['noteId']) . '&jobId=' . intval($obj->getJobId());
                } else if ($personId) {
                    $link = 'notes.php?act=deletenote&noteId=' . intval($note['noteId']) . '&personId=' . intval($obj->getPersonId());
                } else if ($companyId) {
                    $link = 'notes.php?act=deletenote&noteId=' . intval($note['noteId']) . '&companyId=' . intval($obj->getCompanyId());
                } else if ($workOrderId) {
                    $link = 'notes.php?act=deletenote&noteId=' . intval($note['noteId']) . '&workOrderId=' . intval($obj->getWorkOrderId());
                }           
                echo '<td valign="middle"><a href="' . $link . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_delete_24x24.png" width="20" height="20" title="Delete note" border="0"></a></td>';
            echo '</tr>';    
        }
    }
        // Form to add a new note.
        // >>> 00006 JM: I think it would be clearer to put the form inside the TR rather than surrounding it.
        echo '<form name="note" id="note" action="notes.php" method="post"  class="mt-3">';
            echo '<input type="hidden" name="act" value="addnote" >';
            echo '<div class="col-sm-8 float:left">';
                     echo' <h1>Current Notes</h1>';
            echo '</div>';
            echo '<div class="form-group row">';
            if ($jobId) {
                echo '<input type="hidden" name="jobId" value="' . $obj->getJobId() . '">';
            } else if ($personId) {
                echo '<input type="hidden" name="personId" value="' . $obj->getPersonId() . '">';
            } else if ($companyId) {
                echo '<input type="hidden" name="companyId" value="' . $obj->getCompanyId() . '">';
            } else if ($workOrderId) {
                echo '<input type="hidden" name="workOrderId" value="' . $obj->getWorkOrderId() . '">';
            }
            echo '</div>';
            echo '<div class="form-group row mt-3">';
            echo '<tr>';
                echo '<td align="center" colspan="2"><textarea maxlength="2048" required name="noteText" id="noteText" placeholder="Write a note..." cols="80" rows="5"></textarea>
                <br><input type="submit" id="addNote" class="btn btn-secondary mr-auto ml-auto mt-3" value="Add note"></td>';
            echo '</tr>';
            echo '</div>';
        echo '</form>';
    echo '</table>';


include '../includes/footer_fb.php';
?>