<?php 
/* fb/addjob.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a job.
    This page will be a child of the page that invokes it.
    Job is always associated with current customer (which as of 2019-04 always SSS).

    No primary input.

    Optional INPUT $_REQUEST['act']. Only possible value: 'addjob', which uses $_REQUEST['name'], $_REQUEST['description'].
    
    On completion, navigates to the job page for the new job.
*/

include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-07-21, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();
$name="";
$description="";

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

if ($act == 'addjob') {

    $v->rule('required', 'name');

    if(!$v->validate()) {
        $errorId = '637309234096702227';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, Job name is required.";
    } else {
        // The happy path is to do the action, navigate to the correct page (which will implicitly close the fancybox), and die, but there 
        // are many possible errors to check for along the way.
        // Do the action, navigate to the correct page (which will implicitly close the fancybox), and die.
        $jobId = $customer->addJob();
        if (!intval($jobId)) {
            $errorId = '637166058917878901';
            // George 2020-10-14 Changed error message, previous message "addJob method failed." was irelevant to the end User.
            $error = 'We could not add a New Job. Please try again.';  //message for User. 
            $logger->error2($errorId, "addJob() method failed.");
        } 
        else if ($jobId <= 0) {
            // $jobId will be an error code set by Job object (called via the Customer object), so have that object interpret it.
            // This will always set non-empty $error string. 
            list($error, $errorId) = Job::errorToText($jobId); 
            $error_is_db = isDbError($jobId);

            if ($error_is_db) {
                $logger->errorDb($errorId, $error, $db);
            } else {
                $logger->error2($errorId, $error);
            }
        }

        if (!$error) {
            $job = new Job($jobId);
            if (!$job) {
                $errorId = '637166060611388868';
                //George 2020-10-14 Changed error message, previous message "Failed to create job object" was irelevant to the end User.
                $error = 'We could not add a New Job. Please try again.';  //message for User. 
                $logger->error2($errorId, "Failed to create Job object.");
            }
        }

        if (!$error) {
            // Happy path: do the action, navigate to the correct page, and die
            $name = $_REQUEST['name'];
            $name = truncate_for_db ($name, 'Job Name', 75, '637309270358321751'); //  handle truncation when an input is too long for the database.
           
            if (!strlen($name)){  // trimed by truncate_for_db().
                $name = $job->getNumber();
            }

            $description = isset($_REQUEST['description']) ? $_REQUEST['description'] : ''; // Not required.
            $description = truncate_for_db ($description, 'Job description', 255, '637309270949126230'); //  handle truncation when an input is too long for the database.
           
            $success = $job->update(array('name' => $name, 'description' => $description));

            if ($success === false) {
                $errorId = '637382770932014726';
                $error = 'We could not add a New Job. Database Error. '; // message for User
                $logger->errorDB($errorId, "update() Job method failed => Hard DB error ", $db);
            } else { //success
                ?>
                <script type="text/javascript">
                    top.window.location = '<?php echo $job->buildLink(); ?>';           
                </script>
                
                <?php
                die();
            }
            unset($name, $description, $success);
        }
       
    }
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<style>
body { background: white !important; }
</style>

<div class="container-fluid">

<form name="note" action="addjob.php" method="post" id="addJobForm">
    <input type="hidden" name="act" value="addjob">
    <div class="form-group row">
        <label for="jobname" class="col-sm-4 col-form-label">Job Name</label>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="jobname" name="name" value="<?=$name?>" placeholder="job name" maxlength="75" required>
        </div>
    </div>
    <div class="form-group row">
        <label for="jobdescription" class="col-sm-4 col-form-label">Descripiton</label>
        <div class="col-sm-8">
            <textarea class="form-control" id="jobdescription" name="description" rows="5" maxlength="255" value="<?=$description?>" placeholder="job description"></textarea>
        </div>
    </div>
    <div class="form-group row mt-5">
        <button type="submit" id="addJob" class="btn btn-secondary mr-auto ml-auto">Add job</button>
    </div>  
</form>
</div>
<script>

jQuery.validator.addMethod("containLetter", function(value, element) {
    return this.optional(element) || value.match(/[a-zA-Z]/);
}, "This field must contain at least one letter.");

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#addJobForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        name:{
            required: true,
            containLetter: true
        }
    }
});

validator.showErrors(jsonErrors);

// The moment they start typing(or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
});

</script>
<?php
include '../includes/footer_fb.php';
?>