<?php
/* fb/addjobperson.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a person to a job team.
    Assign role, then add to team.
    This page will be a child of the page that invokes it.
    Job is always associated with current customer (which as of 2020-03 always SSS).

    PRIMARY INPUT: $_REQUEST['jobId'].
    
    No $_REQUEST['act'], because this goes to a different page, fb/addjobperson2.php, for its action.
    (Before version 2020-2, the content of fb/addjobperson2.php was part of this file and we had a $_REQUEST['act'] that could 
    be 'assignrole' or 'addjobperson'. We've killed 'assignrole' completely, and 'addjobperson' is in fb/addjobperson2.php.) 
    This always was a two-step Wizard model, and it makes a lot more sense to handle it in two files.
    
*/
include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-06-26, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'jobId');
$v->rule('integer', 'jobId');
$v->rule('min', 'jobId', 1);

if( !$v->validate() ) {
    $errorId = '637287731961957759';
    $logger->error2($errorId, "jobId : " . $_REQUEST['jobId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid jobId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$jobId = intval($_REQUEST['jobId']); // The jobId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'job'.
if (!Job::validate($jobId)) {
    $errorId = '637287732054920674';
    $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing row in job table");
    $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$job = new Job($jobId, $user);

// JM 2020-03-10: variables previously called $teams and $team are now $members and $member, much better description of what they represent.
$members = $job->getTeam(0, $error_is_db); //an array of associative arrays, each of which describes a member of the team
if ($error_is_db) { //true on query failed.
    $errorId = '637374974842529181';
    $error = 'We could not display the Members for this Team. Database Error.'; //message for User
    $logger->errorDB($errorId, "getTeam method failed => Hard DB error ", $db);
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<style>
    .autocomplete-wrapper {max-width: 600px; }
    .autocomplete-wrapper label { display: block; margin-bottom: .75em; color: #3f4e5e; font-size: 1.25em; }
    .autocomplete-wrapper .text-field { padding: 0 0px; width: 100%; height: 40px; border: 1px solid #CBD3DD; font-size: 1.125em; }
    .autocomplete-wrapper ::-webkit-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    .autocomplete-wrapper :-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    .autocomplete-wrapper ::-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    .autocomplete-wrapper :-ms-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    
    .autocomplete-suggestions { overflow: auto; border: 1px solid #CBD3DD; background: #FFF; }
    .autocomplete-suggestion { overflow: hidden; padding: 5px 15px; white-space: nowrap; }
    .autocomplete-selected { background: #F0F0F0; }
    .autocomplete-suggestions strong { color: #029cca; font-weight: normal; }

    tr.inactive td {color:lightgray;}
    /* George*/
    .dbhead{max-width: 99%;background:#fff;}
    html, body, form {background:#fff;}

</style>

<div id="container" class="clearfix">

    <div class="clearfix dbhead">
        <?php /* Form (unusually for Panther, not self-submitting) with hidden jobId, ts=time, personId (initially blank); 
                 job name is displayed but not-editable; 
                 "New Team Member" is in an "autocomplete" INPUT field that uses /ajax/autocomplete_person.php.
                 NOTE no button: the action is performed when you select from the autocomplete choices. There is no other case, 
                 effective submission is always through function $('#autocomplete').devbridgeAutocomplete below.
                 Ron & Damon confirm 2020-04-02 that they are happy with this.
                 
                 When the user selects a person in the jobpersonform below (by using autocomplete),
                 that navigates to fb/addjobperson2.php, which builds an entirely different display consisting
                 of a self-submitting form jobpersonform2.                 
             */
        ?>
        <form id="jobpersonform" name="jobperson">
            <input type="hidden" name="jobId" id="jobId" value="<?php echo intval($job->getJobId()); ?>">
            <input type="hidden" name="ts" value="<?php echo time(); ?>">
            <div class="form-group row">
                <div class="col-sm-8 float:left">
                    <h1><?php echo $job->getName(); ?></h1>
                </div>
                    <label for="personName" class="col-sm-12 col-form-label mt-3"> Start typing to select the New Team Member from the list below.</label>
                <div class="autocomplete-wrapper col-sm-12 mt-2 ">
                    <input type="text" class="form-control" placeholder="Start typing and select"  name="personName" id="autocomplete"/>
                </div>
           </div>
        </form>
    
<?php 

        $title = (count($members) == 1) ? 'Team Member' : 'Team Members';
?>
        <div class="full-box clearfix mt-2">
            <?php /* A table titled "Current Team Member" or "Current Team Members" (as appropriate). 
                     This displays current people for this job.*/ ?>
            <h2 class="heading">Current <?php  echo $title; ?></h2>
            <table  class="table table-bordered table-striped text-left">
                <tbody>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th><!-- Active --></th>
                    </tr>
<?php
                    foreach ($members as $member) {    
                        if ($member['active']) {
                        echo '<tr>';        
                        } else {
                            echo '<tr class="inactive">';
                        }
                            echo '<td>';                    
                                $t = new Person($member['personId']);
                                echo $t->getFirstName() . '&nbsp;' . $t->getLastName() . ' [' . $member['companyName'] . ']';                
                            echo '</td>';            
                            echo '<td>';
                                echo $member['name'];
                            echo '</td>';
                            echo '<td>';
                                echo $member['active'] ? 'active' : 'inactive';
                            echo '</td>';
                        echo '</tr>';    
                    }
?>	
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
$('#autocomplete').devbridgeAutocomplete({
    serviceUrl: '/ajax/autocomplete_person.php',
    onSelect: function (suggestion) {
        // if-clause ADDED 2020-04-08 as part of fixing http://bt.dev2.ssseng.com/view.php?id=105 (Person error on company page)
        // Not clear why this would fire with suggestion.data blank or zero, but it clearly did.
        if (suggestion.data) {
            var jobId  =  $("#jobId").val();
            window.location.href = "addjobperson2.php?personId="+suggestion.data+"&jobId="+jobId+"";
        }
    },
    paramName:'q'
});
</script>

<?php
include '../includes/footer_fb.php';
?>
