<?php
/* fb/addjobperson2.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a person to a job team.
    This is the second page of a Wizard, started in fb/addjobperson.php
    Job is always associated with current customer (which as of 2020-03 always SSS).

    PRIMARY INPUTS: $_REQUEST['jobId']
                    $_REQUEST['personId']

    Optional $_REQUEST['act']. Possible values:
        * 'addjobperson' takes additional inputs:
            * $_REQUEST['companyPersonId'] - REQUIRED always should be ID of a row with a matching personId
            * $_REQUEST['teamPositionId'] - REQUIRED
            * $_REQUEST['reason'] - typically blank
*/
include '../inc/config.php';
include '../inc/access.php';


// ADDED by George 2020-06-26, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$personId = "";
$companyId = 0;
$error_is_db = false;
$db = DB::getInstance();
$person = NULL;

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', ['jobId', 'personId']);
$v->rule('integer', ['jobId', 'personId']);
$v->rule('min', ['jobId', 'personId'], 1);

if( !$v->validate() ) {
    $errorId = '637287741109287640';
    $logger->error2($errorId, "jobId : " . $_REQUEST['jobId'] . " or personId : " . $_REQUEST['personId'] ." not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid jobId or personId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$jobId = intval($_REQUEST['jobId']); // The jobId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'job'.
if (!Job::validate($jobId)) {
    $errorId = '637287743185859914';
    $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing row in job table");
    $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$job = new Job($jobId, $user);

$personId = intval($_REQUEST['personId']); //The personId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'person'.
if (!Person::validate($personId)) {
    $errorId = '637287743774827856';
    $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB row in person table"); // 2020-05-14 [CP] -
    $_SESSION["error_message"] = "PersonId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}
$person = new Person($personId, $user);

$companies = $person->getCompanies($error_is_db); //an array of associative arrays, one for each company this person is associated with
if ($error_is_db) { //true on query failed
    $errorId = '637375016701035576';
    $error = "We could not display the Companies for this Team Member. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getElements method failed => Hard DB error ", $db);
}

$positions = getTeamPositions($error_is_db);
if ($error_is_db) { //true on query failed
    $errorId = '637375020115234851';
    $error .= 'We could not display the Positions for this Team Member. Database Error.'; // message for User
    $logger->errorDB($errorId, "getElements method failed => Hard DB error ", $db);
}

$companyPersonIdDB = array(); // Declare an array of companyPersonIds (vs. array $companies, which contains additional data).
foreach ($companies as $company) {
    $companyPersonIdDB[] = $company->companyPersonId;
}

$teamPositionIdDB = array(); // Declare an array of teamPositionIds  (vs. array $positions, which contains additional data).
foreach ($positions as $position) {
    $teamPositionIdDB[] = $position["teamPositionId"]; // Build an array with valid teamPositionIds from DB, table teamPosition.
}

if ($act == 'addjobperson') {
    $v->rule('required', ['companyPersonId'])->label('Name from list');
    $v->rule('required', ['teamPositionId'])->label('Position from list');
    $v->rule('in', 'teamPositionId', $teamPositionIdDB); // teamPositionId value must be in array.
    $v->rule('in', 'companyPersonId', $companyPersonIdDB); // companyPersonId value must be in array.

    if(!$v->validate()) {
        $errorId = '637178909849225209';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        $companyPersonId = intval($_REQUEST['companyPersonId']);
        $teamPositionId = intval($_REQUEST['teamPositionId']);

        $reason = isset($_REQUEST['reason']) ? $_REQUEST['reason'] : ''; // typically blank
        $reason = truncate_for_db ($reason, 'reason', 128, '637287738296628268'); //  handle truncation when an input is too long for the database.

        $result = TeamCompanyPerson::checkDuplicate(INTABLE_JOB, $teamPositionId, $companyPersonId, $jobId);

        if ($result == null) { // query failed
            $errorId = '637299944077887791';
            $error = 'Checking if this Person is already added in the job Team failed.';
            $logger->errorDb($errorId, 'checkDuplicate method failed.', $db);
        } else { //Succes. We check if TeamPositionId Already Exist.
            if ($result->num_rows > 0) { //  TeamPositionId Already Exist.
                $errorId = '637376658224688646';
                $error = 'This Person is already added in the Job Team.';
                $logger->error2($errorId, 'This Person is already added in the Job Team.');
            } else { // Ready to insert.
                $success = TeamCompanyPerson::insertTeam(INTABLE_JOB, $teamPositionId, $companyPersonId, $jobId, $reason);
                if (!$success) {
                    $errorId = '637299944077887798';
                    $error = 'Insert a new Person in this Team failed.';
                    $logger->errorDb($errorId, 'insertTeam() method failed.', $db);
                }
            }
        }
        unset($jobId, $reason); //$companyPersonId and $teamPositionId are used further on the page
    }

    if (!$error) {
    ?>
        <script>
            parent.$.fancybox.close();
        </script>
    <?php
    }
} // END if ($act == 'addjobperson')

// BEGIN ADDED 2020-09-24 JM: Does this job already have an (active) client; !! converts to Boolean
$hasClient = !!$job->getTeamPosition(TEAM_POS_ID_CLIENT, false, true);
// END ADDED 2020-09-24 JM

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>
 <style>
 /* George*/
.dbhead{max-width: 99%;background:#fff;}
html, body, form {background:#fff;}
#companyPersonId, #teamPositionId {width: 70%;}
</style>

<?php
    /* jobpersonform2 has hidden jobId, act=addjobperson, personId; as in jobpersonform in addjobperson.php,
       job name is displayed but not-editable, as is the name of the person just selected in addjobperson.php.
       There is an HTML SELECT to choose a company from among those the person is already associated with (this sets companyPersonId),
       and an HTML SELECT to choose team position (this sets teamPositionId).
       Text warns that if there is something unusual here (e.g. adding a second client to the same job) you should fill out a reason,
       and there is an HTML INPUT allowing a reason of up to 128 characters.
       The submit button is labeled "assign". When that is clicked, it leads to the action for 'act=addjobperson'.
    */

    // BEGIN ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9:
    // We want to look at the 20 latest times this person was on a team, and offer the most likely companies & teams first.
    $companyPerson_count = Array();
    $teamPosition_count = Array();

    $query = "SELECT t.companyPersonId, t.teamPositionId ";
    $query .= "FROM team t ";
    $query .= "JOIN companyPerson cp ON t.companyPersonId = cp.companyPersonId ";
    $query .= "WHERE cp.personId = $personId ";
    $query .= "ORDER BY t.teamId DESC ";  // proxy for order inserted
    $query .= "LIMIT 20;";

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1588629011', 'Hard DB error', $db);
        // But fall through, because this is not essential to have
    } else {
        while ($row = $result->fetch_assoc()) {
            // We are creating slightly artificial strings as indexes to keep these arrays associative rather than numerically indeed.
            $companyPerson_key = '_' . $row['companyPersonId'];
            $teamPosition_key = '_' . $row['teamPositionId'];
            if (array_key_exists($companyPerson_key, $companyPerson_count)) {
                $companyPerson_count[$companyPerson_key] += 1;
            } else {
                $companyPerson_count[$companyPerson_key] = 1;
            }
            if (array_key_exists($teamPosition_key, $teamPosition_count)) {
                $teamPosition_count[$teamPosition_key] += 1;
            } else {
                $teamPosition_count[$teamPosition_key] = 1;
            }
        }


        // sort in descending order by value.
        arsort($companyPerson_count, SORT_NUMERIC);
        arsort($teamPosition_count, SORT_NUMERIC);

    }
    // END ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9:

    ?>
    <form id="jobpersonform2" name="jobperson" method="POST" action="addjobperson2.php">
        <input type="hidden" name="jobId" value="<?php echo intval($job->getJobId()); ?>">
        <input type="hidden" name="act" value="addjobperson">
        <input type="hidden" name="personId" id="personId" value="<?php echo intval($personId); ?>">
        <center>
            <?php
            echo '<h1>' . $job->getName() .'</h1>';
            echo '<h1>' . $person->getFirstName() . '&nbsp;' . $person->getLastName() .'</h1>';
            echo '<table border="1" cellpadding="10" cellspacing="5" >';
                echo '<tr>';
                    echo '<td>';
                        echo '<select id="companyPersonId" class="form-control" name="companyPersonId">';
                        if ( count($companyPerson_count) == 0) { // This test and the else-case ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9
                            echo '<option value="" >-- Choose Company --</option>';
                            foreach ($companies as $company) {
                                echo '<option value="' . $company->companyPersonId . '" '.(isset($_REQUEST['companyPersonId'])
                                && ($company->companyPersonId == $_REQUEST['companyPersonId']) ? 'selected':'').'>' . htmlspecialchars($company->getCompanyName()) . '</option>';
                            }
                        } else {
                            // BEGIN ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9
                            $first_option = true;
                            foreach ($companyPerson_count AS $name => $throwaway_count) {
                                $companyPersonId = intval(substr($name, 1));
                                $companyPerson = new CompanyPerson($companyPersonId);
                                $company = $companyPerson->getCompany();
                                echo '<option value="' . $companyPersonId . '" '. ($first_option ? 'selected' : '') . '>' .
                                      htmlspecialchars($company->getCompanyName()) . '</option>';
                                $first_option = false;
                            }
                            // Now any options we didn't already get
                            foreach ($companies as $company) {
                                if ( !array_key_exists('_'. $company->companyPersonId, $companyPerson_count)) {
                                    echo '<option value="' . $company->companyPersonId . '">' . htmlspecialchars($company->getCompanyName()) . '</option>';
                                }
                            }
                            // END ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9
                        }
                        echo '</select>';
                    echo '</td>';

                    echo '<td>';
                        echo '<select id="teamPositionId" class="form-control" name="teamPositionId">';
                        if ( count($teamPosition_count) == 0) { // This test and the else-case ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9:
                                                                // $hasClient consideration added 2020-09-24 because we have decided we should never have 2 clients.
                            echo '<option value="">-- Choose Position --</option>';
                            foreach ($positions as $position) {
                                echo '<option value="' . $position['teamPositionId'] . '" '.
                                    ($hasClient && $position['teamPositionId'] == TEAM_POS_ID_CLIENT ? 'disabled' : '') .
                                    (isset($_REQUEST['teamPositionId']) && ($position['teamPositionId'] == $_REQUEST['teamPositionId']) ? 'selected' : '').
                                '>' . htmlspecialchars($position['name']) . '</option>';
                            }
                        } else {
                            // BEGIN ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9
                            // $hasClient consideration added 2020-09-24 because we have decided we should never have 2 clients.
                            $first_option = true;
                            foreach ($teamPosition_count AS $name => $throwaway_count) {
                                $teamPositionId = intval(substr($name, 1));
                                $positionName = '-- Choose Position --';
                                foreach ($positions as $position) {

                                    if ($position['teamPositionId'] == $teamPositionId) {
                                        $positionName = $position['name'];
                                        break;
                                    }
                                }
                                $disabled = $hasClient && $teamPositionId == TEAM_POS_ID_CLIENT;

                                echo '<option value="' . $teamPositionId . '" '.
                                     ($disabled ? 'disabled' : ($first_option ? 'selected' : '')) .
                                     '>' .
                                     $positionName . '</option>';

                                if (!$disabled) {
                                    $first_option = false;
                                }
                            }
                            // Now any options we didn't already get
                            // $hasClient consideration added 2020-09-24 because we have decided we should never have 2 clients.
                            foreach ($positions as $position) {
                                if ( !array_key_exists('_'. $company->companyPersonId, $teamPosition_count)) {
                                    $disabled = $hasClient && $position['teamPositionId'] == TEAM_POS_ID_CLIENT;
                                    echo '<option value="' . $position['teamPositionId'] . '" '.
                                         ($disabled ? 'disabled' : '') .
                                         '>' . htmlspecialchars($position['name']) . '</option>';
                                }
                            }
                            // END ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9
                        }
                        echo '</select>';
                    echo '</td>';
                echo '</tr>';

                echo '<tr>';
                    echo '<td colspan="2">When adding people to positions, if you are doing something '.
                         'unusual, then please state the reason.<br><br>' .
                         'Otherwise, leave the reason blank.<br><br>'.
                         'For example, adding a second EOR merits stating a reason.</td>';
                echo '</tr>';

                echo '<tr>';
                    echo '<td colspan="2">';
                        echo '<table border="0" cellpadding="5" cellspacing="1">';
                            echo '<tr>';
                                echo '<td>Reason:</td>';
                                echo '<td><input type="text" name="reason" id="reason" class="form-control" value="" size="45" maxlength="128"></td>';
                            echo '</tr>';
                        echo '</table>';
                    echo '</td>';
                echo '</tr>'; // Added 2019-12-10 JM, obviously what was intended here.
            echo '</table>';
?>
            <center>
                <input type="submit" id="submit" class="btn btn-secondary mt-3" value="assign">
            </center>
        </form>

<script>
var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#jobpersonform2').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: {
        'companyPersonId':{
            required: true
        },
        'teamPositionId':{
            required: true
        }
    }
});
validator.showErrors(jsonErrors);

// The moment they start typing (or pasting) in a field, remove the validator warning
$('select').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#companyPersonId-error').hide();
    if ($('#companyPersonId').hasClass('text-danger')){
        $("#companyPersonId").removeClass("text-danger");
    }
    $('#teamPositionId-error').hide();
    if ($('#teamPositionId').hasClass('text-danger')){
        $("#teamPositionId").removeClass("text-danger");
    }
});
</script>

<?php
include '../includes/footer_fb.php';
?>
