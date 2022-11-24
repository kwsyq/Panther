<?php
/* fb/addworkorderperson2.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a person to a workOrder team.
    This is the second page of a Wizard, started in fb/addworkorderperson.php
    WorkOrder is always associated with current customer (which as of 2020-03 always SSS).

    PRIMARY INPUTS: $_REQUEST['workOrderId']
                    $_REQUEST['personId']

    Optional $_REQUEST['act']. Possible values:
        * 'addworkorderperson' takes additional inputs:
            * $_REQUEST['companyPersonId'] - REQUIRED always should be ID of a row with a matching personId
            * $_REQUEST['teamPositionId'] - REQUIRED
            * unlike addjobperson.php, no "reason" here.
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
$personId = 0;
$workOrderId = 0;
$person = NULL;

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', ['workOrderId', 'personId']);
$v->rule('integer', ['workOrderId', 'personId']);
$v->rule('min', ['workOrderId', 'personId'], 1);

if( !$v->validate() ) {
    $errorId = '637287773951424328';
    $logger->error2($errorId, "workOrderId : " . $_REQUEST['workOrderId'] . " or personId : " . $_REQUEST['personId'] ." not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid workOrderId or personId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$workOrderId = intval($_REQUEST['workOrderId']); // The workOrderId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'workorder'.
if (!WorkOrder::validate($workOrderId)) {
    $errorId = '637287770856201664';
    $logger->error2($errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB row in workorder table");
    $_SESSION["error_message"] = "WorkOrderId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$workorder = new WorkOrder($workOrderId, $user);

$personId = intval($_REQUEST['personId']); //The personId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'person'
if (!Person::validate($personId)) {
    $errorId = '637287771476387549';
    $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB row in person table"); // 2020-05-14 [CP] -
    $_SESSION["error_message"] = "PersonId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$person = new Person($personId, $user);

$companies = $person->getCompanies($error_is_db);
if ($error_is_db) { //true on query failed
    $errorId = '637396495317489215';
    $error = "We could not display the Companies for this Member. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getCompanies() method failed => Hard DB error ", $db);
}

$companyPersonIdDB = array(); // Declare an array of companyPersonIds (vs. array $companies, which contains additional data).
foreach ($companies as $company) {
    $companyPersonIdDB[] = $company->companyPersonId;
}

$positions = getTeamPositions($error_is_db);

if ($error_is_db) { //true on query failed
    $errorId = '637396494748962511';
    $error .= "We could not display the Team Positions for this Member. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getTeamPositions() method failed => Hard DB error ", $db);
}

$teamPositionIdDB = array(); // Declare an array of teamPositionIds (vs. array $positions, which contains additional data).
foreach ($positions as $value) {
    $teamPositionIdDB[] = $value["teamPositionId"]; //Build an array with valid teamPositionIds from DB, table teamPosition.
}

if ($act == 'addworkorderperson') {
    $v->rule('required', 'companyPersonId')->label('Name from list');
    $v->rule('required', 'teamPositionId')->label('Position from list');
    $v->rule('in', 'teamPositionId', $teamPositionIdDB); // teamPositionId value must be in array.
    $v->rule('in', 'companyPersonId', $companyPersonIdDB); // companyPersonId value must be in array.

    if(!$v->validate()) {
        $errorId = '637182308744840221';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        $companyPersonId = intval($_REQUEST['companyPersonId']);
        $teamPositionId = intval($_REQUEST['teamPositionId']);

        $result = TeamCompanyPerson::checkDuplicate(INTABLE_WORKORDER, $teamPositionId, $companyPersonId, $workOrderId);

        if ($result == null) {
            $errorId = '637302407433262096';
            $error = 'Checking if this Person is already added in the WorkOrder Team failed.';
            $logger->errorDb($errorId, 'TeamCompanyPerson::checkDuplicate method failed.', $db);
        } else { //Success. We check if TeamPositionId Already Exist.
            if($result->num_rows > 0) { //  TeamPositionId Already Exist.
                $errorId = '637299944077887791';
                $error = 'This Person is already added in the WorkOrder Team.';
                $logger->error2($errorId, $error);
            } else { //Ready to insert.
                $success = TeamCompanyPerson::insertTeam(INTABLE_WORKORDER, $teamPositionId, $companyPersonId, $workOrderId);
                if (!$success) {
                    $errorId = '637302407604605292';
                    $error = 'Insert a new Person in this Team failed.';
                    $logger->errorDb($errorId, 'insertTeam() method failed.', $db);
                }
            }
        }
        unset($teamPositionIdDB, $companyPersonIdDB);
    }

    if (!$error) {
        ?>
            <script>
                parent.$.fancybox.close();
            </script>
        <?php
    }
} // END if ($act == 'addworkorderperson')

// BEGIN ADDED 2020-09-24 JM: Does this workorder already have an (active) client; !! converts to Boolean
$hasClient = !! $workorder->getTeamPosition(TEAM_POS_ID_CLIENT, false, true, true);
// END ADDED 2020-09-24 JM

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>
 <style>
.dbhead{max-width: 99%;background:#fff;}
html, body, form {background:#fff;}
</style>

<?php
    /* workorderpersonform 2 has hidden workorderId, act=addworkorderperson, personId; as in workorderpersonform in addworkorderperson.php,
       workorder name is displayed but not-editable, as is the name of the person just selected in addworkorderperson.php.
       There is an HTML SELECT to choose a company from among those the person is already associated with (this sets companyPersonId),
       and an HTML SELECT to choose team position (this sets teamPositionId).
       The submit button is labeled "assign". When that is clicked, it leads to the action for 'act=addworkorderpersonperson'.
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
        $logger->errorDb('637396590879647679', 'Hard DB error', $db);
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
    <form id="workorderpersonform2" name="workorderperson" method="POST" action="addworkorderperson2.php">
        <input type="hidden" name="workOrderId" value="<?php echo intval($workorder->getWorkOrderId()); ?>">
        <input type="hidden" name="act" value="addworkorderperson">
        <input type="hidden" name="personId" id="personId" value="<?php echo intval($personId); ?>">
        <center>
            <?php
            echo '<h1>' . $workorder->getName() .'</h1>';
            echo '<h1>' . $person->getFirstName() . '&nbsp;' . $person->getLastName() .'</h1>';
            echo '<table border="0" cellpadding="10" cellspacing="5">';
                echo '<tr>';
                    echo '<td>';
                        echo '<select id="companyPersonId" class="form-control" name="companyPersonId">';
                        if ( count($companyPerson_count) == 0) { // This test and the else-case ADDED 2020-05-04 JM to address http://bt.dev2.ssseng.com/view.php?id=9
                            echo '<option value="">-- Choose Company --</option>';
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
                                    ($hasClient && $position['teamPositionId'] == TEAM_POS_ID_CLIENT ? 'disabled ' : '') .
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
            echo '</table>';
?>
        <center>
            <input type="submit"  class="btn btn-secondary mt-3"  id="assignWoPerson" value="assign">
        </center>
    </form>

<script>
var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#workorderpersonform2').validate({
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