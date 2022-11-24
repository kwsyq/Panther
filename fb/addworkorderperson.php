<?php 
/* fb/addworkorderperson.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a person to a workOrder team.
    Assign role, then add to team.
    This page will be a child of the page that invokes it.
    WorkOrder is always associated with current customer (which as of 2020-03 always SSS).

    PRIMARY INPUT: $_REQUEST['workOrderId'].
    
    No $_REQUEST['act'], because this goes to a different page, fb/addworkorderperson2.php, for its action.
    Before version 2020-2, the content of fb/addworkorderperson2.php was part of this file and we had a $_REQUEST['act'] that could 
    be 'assignrole' or 'addworkorderperson'. We've killed 'assignrole' completely, and 'addworkorderperson' is in fb/addworkorderperson2.php.) 
    This always was a two-step Wizard model, and it makes a lot more sense to handle it in two files.
    
*/
include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-06-26, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$workOrderId = 0;

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'workOrderId');
$v->rule('integer', 'workOrderId');
$v->rule('min', 'workOrderId', 1);

if( !$v->validate() ) {
    $errorId = '637287765489963942';
    $logger->error2($errorId, "workOrderId : " . $_REQUEST['workOrderId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid workOrderId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$workOrderId = intval($_REQUEST['workOrderId']); // The workOrderId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'workorder'.
if (!WorkOrder::validate($workOrderId)) {
    $errorId = '637287767645013331';
    $logger->error2($errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB row in workorder table");
    $_SESSION["error_message"] = "WorkOrderId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$workorder = new WorkOrder($workOrderId, $user);

include '../includes/header_fb.php';
?>

<style>
    .autocomplete-wrapper { max-width: 600px; }
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
    /* George*/
    .dbhead{max-width: 99%;background:#fff;}
    html, body, form {background:#fff;}
</style>

<style>
tr.inactive td {color:lightgray;}
</style>

<div id="container" class="clearfix">
    <div class="full-box clearfix dbhead">
        <?php /* Form (unusually for Panther, not self-submitting) with hidden workOrderId, ts=time, personId (initially blank); 
                 workOrder name is displayed but not-editable; 
                 "New Team Member" is in an "autocomplete" INPUT field that uses /ajax/autocomplete_person.php; submit button is labeled "add". 
                 NOTE no button: the action is performed when you select from the autocomplete choices. There is no other case, 
                 effective submission is always through function $('#autocomplete').devbridgeAutocomplete below.
                 Ron & Damon confirm 2020-04-02 that they are happy with this.
                 
                 When the user selects a person in the workorderpersonform below (by using autocomplete),
                 that navigates to fb/addworkorderperson.php, which builds an entirely different display consisting
                 of a self-submitting form workorderpersonform2.                 
 
             */ 
        ?>
        
        <form id="workorderpersonform" name="workorderperson" >
            <input type="hidden" name="workOrderId" id="workOrderId" value="<?php echo intval($workorder->getWorkOrderId()); ?>">
            <input type="hidden" name="ts" value="<?php echo time(); ?>">
            <div class="form-group row">
                <div class="col-sm-8 float:left">
                    <h1><?php echo $workorder->getName(); ?></h1>
                </div>
                        <label for="personName" class="col-sm-12 col-form-label mt-3"> Start typing to select the New Team Member from the list below.</label>
                <div class="autocomplete-wrapper col-sm-12 mt-2">
                    <input type="text" name="personName"   class="form-control" placeholder="Start typing and select" id="autocomplete"/>
                </div>
            </div>
        </form>
        
<?php 
        // JM 2020-03-10: variables previously called $teams and $team are now $members and $member, much better description of what they represent.
        $members = $workorder->getTeam();    
        // Logic for Employees
        $arrEmpployeeId = []; // new array of Current Employees.
        $employeesCurrent = $customer->getEmployees(EMPLOYEEFILTER_CURRENTLYEMPLOYED); // Employees.
        
        foreach ($employeesCurrent as $employee) { 
            $arrEmpployeeId[] = $employee->getUserId();
        }

        
        $query = " SELECT workOrderTaskId FROM " . DB__NEW_DATABASE . ".workOrderTask ";
        $query .= " WHERE workOrderId = " . intval($workOrderId) . " ";

        $arrayWorkOrderTasks = array();
        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                array_push($arrayWorkOrderTasks, $row["workOrderTaskId"]);        
            }
        }

        
        $arrayWorkOrderTasks2 = implode(',', array_map('intval', $arrayWorkOrderTasks));
        
        $arrayWorkOrderTasksPersons = [];
        $query = " SELECT personId FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
        $query .= " WHERE workOrderTaskId in ( $arrayWorkOrderTasks2 ) ";
        $query .= " and minutes IS NOT NULL ";
        
        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {

                array_push($arrayWorkOrderTasksPersons, $row['personId']); // employees with time //  for TEAM

            }
        }
        // End Logic for Employees


        $arrExternalsIds = []; // new array of Externals.
        $arrNewIds = []; 

        foreach ($members as $member) {
            $person = new Person($member['personId']);
            if ($member['teamId'] && !in_array( $person->getPersonId(), $arrEmpployeeId)) { 
                $arrExternalsIds[] = $person->getPersonId(); //Ids External !!!!!!!!!!!  for TEAM
            }
        }
        
        $team1 = [];
        foreach( $members as $teamMember) {
            if (in_array($teamMember['personId'],$arrExternalsIds) || in_array($teamMember['personId'],$arrayWorkOrderTasksPersons)) {
                    $team1[] = $teamMember;
            }
        }
        
        $title = (count($team1) == 1) ? 'Team Member' : 'Team Members';        
?>
        <div class="full-box clearfix mt-2">
            <?php /* A table titled "Current Team Member" or "Current Team Members" (as appropriate)                
                     This displays current people for this workorder. */ ?>
            <h2 class="heading">Current <?php  echo $title; ?></h2>            
            <table  class="table table-bordered table-striped text-left">
                <tbody>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th><!-- Active --></th>
                    </tr>
<?php
                    foreach ($team1 as $member) {    
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
    serviceUrl: '/ajax/autocomplete_external_person.php',
    onSelect: function (suggestion) {
        // if-clause ADDED 2020-04-08 as part of fixing http://bt.dev2.ssseng.com/view.php?id=105 (Person error on company page)
        // Not clear why this would fire with suggestion.data blank or zero, but it clearly did.
        if (suggestion.data) {
            var workOrderId  =  $("#workOrderId").val();
            window.location.href = "addworkorderperson2.php?personId="+suggestion.data+"&workOrderId="+workOrderId+"";
        }
    },
    paramName:'q'    
});
</script>
<?php
    include '../includes/footer_fb.php';
?>