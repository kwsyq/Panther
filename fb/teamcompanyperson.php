<?php
/*  fb/teamcompanyperson.php
    
    EXECUTIVE SUMMARY: Edit the "position" of a particular person (technically, companyPerson) on a particular team.

    PRIMARY INPUT: $_REQUEST['teamId']. Despite its name, that's a combination of team, company, and person.
    
    Optional $_REQUEST['act']. Only possible value: 'setTeamPosition', which uses $_REQUEST['teamPositionId'].
*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-06-26, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$teamId = 0;
$error_is_db = false;
$db = DB::getInstance();
$person = NULL;
$teamCompanyPerson = NULL;
$companyPerson = "";
$teamPositionId = 0;

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'teamId');
$v->rule('integer', 'teamId');
$v->rule('min', 'teamId', 1);

if( !$v->validate() ) {
    $errorId = '637287787153181965';
    $logger->error2($errorId, "teamId : " . $_REQUEST['teamId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid teamId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$teamId = intval($_REQUEST['teamId']); // The teamId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'team'.
if (!TeamCompanyPerson::validate($teamId)) {
    $errorId = '637287798853831976';
    $logger->error2($errorId, "The provided teamId ". $teamId ." does not correspond to an existing DB teamCompanyPerson row in team table");
    $_SESSION["error_message"] = "TeamId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$teamCompanyPerson = new TeamCompanyPerson($teamId);

// TeamPositions
$positions = getTeamPositions($error_is_db);
if ($error_is_db) { //true on query failed
    $errorId = '637377702201442586';
    $error = "We could not display the Team Positions for this Member. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getTeamPositions() method failed => Hard DB error ", $db);
}

$teamPositionIdDB = array(); // Declare an array of teamPositionIds.
foreach ($positions as $value) {
    $teamPositionIdDB[] = $value["teamPositionId"]; //Build an array with valid teamPositionIds from DB, table teamPosition.
}
//End TeamPositions

$companyPerson = $teamCompanyPerson->getCompanyPerson();
$person = $companyPerson->getPerson();

$contacts = $companyPerson->getContacts($error_is_db); // array of contacts both for company & for person.
if ($error_is_db) { //true on query failed
    $errorId = '637377715728574612';
    $error .= "We could not display the Contacts for this Member. Database Error."; // message for User
    $logger->errorDB($errorId, "getContacts() method failed => Hard DB error ", $db);
}


if (!$error && $act == 'setTeamPosition') {

    $v->rule('required', ['teamPositionId'])->label('Select position from list');
    $v->rule('min', 'teamPositionId', 1);
    $v->rule('in', 'teamPositionId', $teamPositionIdDB); // teamPositionId value must be in array.
    
    if (!$v->validate()) {
        $errorId = '637183103414657929';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Invalid Team Position. Select position from list";
    } else {
        $teamPositionId = intval($_REQUEST['teamPositionId']);

        $success = $teamCompanyPerson->update(array('teamPositionId' => $teamPositionId));

        if (!$success) {
            $errorId = '637377707255759917';
            $error = 'Update Team Position for this Member failed.'; // message for User
            $logger->errorDB($errorId, "Update Team Position method failed => Hard DB error ", $db);
        } else { // on success
            ?>
            <script>
                parent.$.fancybox.close();
            </script>
            <?php
            die();
        }
        unset($teamPositionId);
    }
    unset($teamPositionIdDB);
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}

?>
<style>
body { background: none; }
</style>
<div id="container" class="clearfix">
    <div class="full-box clearfix ">
        <?php /* Form, largely structured by a table:
                    hidden: teamId
                    hidden: act='setTeamPosition'
                    hidden: ts=time
                    header: person's name (display only)
                    HTML SELECT "teamPositionId", showing "-- Choose Position --" with blank value if nothing selected, 
                        and an option for each defined position (as returned from getTeamPositions() in functions.php):
                        The option value is the teamPositionId; the display is the position name. 
                    submit button, labeled 'update'
                
        */ ?>
        <form id="companypersonform" name="companyperson" method="POST" action="teamcompanyperson.php">
            <input type="hidden" name="teamId" value="<?php echo intval($teamCompanyPerson->getTeamId()); ?>">
            <input type="hidden" name="act" value="setTeamPosition">
            <table class=" table">
                <tr>
                    <td colspan="2" width="100%"><h1><?php echo $person->getFirstName() . '&nbsp;' . $person->getLastName(); ?></h1></td>
                </tr>
                <tr>
                    <td>
                        <?php 
                        echo '<select  style="width:30%!important;" class="form-control" id="teamPositionId" name="teamPositionId"><option value="">-- Choose Position --</option>';
                        foreach ($positions as $position) {
                            $selected = ($teamCompanyPerson->getTeamPositionId() == $position['teamPositionId']) ? ' selected' : '';
                            echo '<option value="' . $position['teamPositionId'] . '" ' . $selected . '>' . htmlspecialchars($position['name']) . '</option>';
                        }
                        echo '</select>';                        
                        ?>
                    </td>
                </tr>
            </table>
            <center>
                <input type="submit" class="btn btn-secondary mr-auto ml-auto" id="updateTeamPosition" value="Update">
            </center>
        </form>
        <br /><br />
        <?php /* A separate table, listing all contact info for the relevant companyPerson. Simple listing: contact type & data. */ ?>
        <table>
            <?php 
            foreach ($contacts as $contact) {
                echo '<tr>';
                    echo '<td>' . $contact['type'] . '</td>';
                    echo '<td>' . $contact['dat'] . '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
</div>

<script>
var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#companypersonform').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        'teamPositionId':{
            required: true
        }
    }
});

validator.showErrors(jsonErrors);
// When they select from list, remove the validator warning
$('select').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#teamPositionId-error').hide();     
    if ($('#teamPositionId').hasClass('text-danger')){
        $("#teamPositionId").removeClass("text-danger");
    }
});
</script>
<?php
    include '../includes/footer_fb.php';
?>