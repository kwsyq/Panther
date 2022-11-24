<?php 
/* fb/addpersoncompany.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to Add and Delete a row in table companyPerson. 
    Analogous to addcompanyperson.php, but starts from company. Company & person should both already exist.
    This page will be a child of the page that invokes it.

    PRIMARY INPUT: $_REQUEST['companyId'].

    DISPLAY: a self-submitting form with hidden personId (input).
    "New Person" is in an "autocomplete" INPUT field that uses /ajax/autocomplete_person.php; 
    After you select the New Person from the list personId is set (initially blank).
    The INSERT action is performed when you select from the autocomplete choices.

    Delete Action : $act == 'deleteperson'.
    PRIMARY INPUT: $_REQUEST['companyPersonId']. //companypersonId
    Delete action and messages are handled by static method Company::deleteCompanyPerson.

    Delete event:
    1. If we found tables with companyPersonId same value in the given DB. This association can not been Removed.
    2. We don't have entries with companyPersonId same value in the given DB. Safe to Delete the Association.
*/


include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-06-05, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
/* End ADD */

$error = '';
$errorId = 0;
$companyId = 0;
$personId = "";
$db = DB::getInstance();
$company = NULL;
$name= "";
$warning_bool = "";
$warning= "";

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'companyId');
$v->rule('integer', 'companyId');
$v->rule('min', 'companyId', 1);

if (!$v->validate() ) {
    $errorId = '637202203804894403';
    $logger->error2($errorId, " CompanyId : " . $_REQUEST['companyId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " CompanyId is not valid. Please check the input."; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$companyId = intval($_REQUEST['companyId']);

if (!Company::validate($companyId)) {
    $errorId = '637202205479753863';
    $logger->error2($errorId,  "The provided companyId:  $companyId does not correspond to an existing DB company row in company table");
    $_SESSION["error_message"] = " CompanyId is not valid. Please check the input."; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$company = new Company($companyId, $user);

if (isset($_REQUEST['personId'])) { // George 2020-06-03. This will be set when the selection is from the list.
 
    $v->rule('required', 'personId'); 
    $v->rule('integer', 'personId'); 
    $v->rule('min', 'personId', 1);

    if(!$v->validate()){ //Check personId if is integer and min 1.
        $errorId = '637261062330257765';
        $logger->error2($errorId, "PersonId ". $_REQUEST['personId'] ." is invalid. Selection not from the list ".json_encode($v->errors()));
        $error = "Please select a person from the list.";
    } 

    if (!$error) {
        $personId = $_REQUEST['personId'];

        if (!Person::validate($personId)) { //Check for valid personId.
            $errorId = '637261062662240640';
            $logger->warn2($errorId, "The selected personId:  $personId does not correspond to an existing DB person row in person table");
            $error = "This person is no longer exist in our system. You can select another person from the list.";
        }
    }

    if (!$error) { //All good for action.

        /* George: An array for the situation Duplicate Person from list. Warning Message with Person name. */

        $persons_already_associated = $company->getCompanyPersons(); // Errors are Logged inside method Company::getCompanyPersons();
        $already_associated = false;

        foreach ($persons_already_associated as $pId) {
            if ($personId == $pId->getPersonId()) {
                $name = $pId->getPerson()->getFormattedName();
                $already_associated = true;
                break;
            }
        }

        if ($already_associated) {
            $error = "<b>" . $name . "</b> already associated, you can select another person";   
        } else {
            // Add association between a Company and Person, entry in table companyPerson.
            $success = CompanyPerson::addCompanyPerson($companyId, $personId);

            if (!$success) {
                $errorId = '637261066047979636';
                $error = 'Add New Person to Company failed. Database error.'; //message for user
                $logger->errorDb($errorId , "Add New Person to Company failed: Hard DB error", $db);
            }
        } 
    }
} 

/* George 2020-05-13 IMPROVMENT: I created in CompanyPerson class a static method called deleteCompanyPerson(), with three parameters:  
companypersonId, the entity: "person" or "company"; and name of company/person, table companyPerson.
Now we can take avantage of variable $act.
On click "Delete" action, for safety, Javascript message: "Are you sure you want to Delete this Person?". */

else if ($act == 'deleteperson') {

    $v->rule('required', ['companyPersonId', 'name']);
    $v->rule('integer',  'companyPersonId');
    $v->rule('min', 'companyPersonId', 1);

    if (!$v->validate()) {
        $errorId = '637261081920950929';
        $error = "CompanyPersonId ". $_REQUEST['companyPersonId'] ." or name ". $_REQUEST['name'] ." is invalid ".json_encode($v->errors());
        $logger->error2($errorId, $error);
    } else { //All good for delete association.
        // Deletes association between a Person and a Company entry in table companyPerson.
        $companypersonId = intval($_REQUEST['companyPersonId']);
        $name = $_REQUEST["name"];

        /* New variable $warning, to display the correct warning message.
        With name of a specific Person. The warning messages are handled in CompanyPerson::deleteCompanyPerson. */
        list($warning_bool, $warning) = CompanyPerson::deleteCompanyPerson($companypersonId, "company", $name);
    } 
} 

// George 2020-06-16. We need to get current persons after Possible actions: addCompanyPerson or deleteCompanyPerson.
// This can have entrys or not. Log error on query failed.
$persons = $company->getCompanyPersons($error_is_db); // Errors are Logged inside method Company::getCompanyPersons();
if ($error_is_db) { //true on query failed
    $errorId = '637402553238847285';
    $error .= 'We could not display the current Persons already associated to this Company. Database Error.'; // message for User
    $logger->errorDB($errorId, "getCompanyPersons() method failed => Hard DB error ", $db);
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-info\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
if ($warning) {
    echo "<div  class=\"alert ".($warning_bool ?"alert-info":"alert-warning")."\" role=\"alert\" id=\"validator-warning\" >$warning</div>";
}
?>

<style>
    .autocomplete-wrapper { max-width: 600px; float:left; }
    .autocomplete-wrapper label { display: block; margin-bottom: .75em; color: #3f4e5e; font-size: 1.25em; }
    .autocomplete-wrapper .text-field { padding: 0 0px; width: 100%; height: 40px; border: 1px solid #CBD3DD; font-size: 1.125em; }
    .autocomplete-wrapper ::-webkit-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    .autocomplete-wrapper :-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    .autocomplete-wrapper ::-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    .autocomplete-wrapper :-ms-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
    
    .autocomplete-suggestions { overflow: auto; border: 1px solid #CBD3DD; background: #FFF; cursor: pointer; }
    .autocomplete-suggestion { overflow: hidden; padding: 5px 15px; white-space: nowrap; }
    .autocomplete-selected { background: #F0F0F0; }
    .autocomplete-suggestions strong { color: #029cca; font-weight: normal; }
    .dbhead{max-width: 99%;background:#fff;}
     body, form {background:#fff;}
</style>

<div id="container" class="clearfix">
    <div class="clearfix dbhead">
        <?php /*Self-submitting form with hidden companId (input), ts=time, personId (initially blank); 
                person name is displayed but not-editable. 
                "New Person" is in an "autocomplete" INPUT field that uses /ajax/autocomplete_person.php; 
                Typically there is no need to for an action: the INSERT action is performed when you select from the autocomplete choices.
                Ron & Damon confirm 2020-04-02 that they are happy with this.
        */ ?>
        <form id="companypersonform" name="companyperson" method="POST" action="addpersoncompany.php">
            <input type="hidden" name="companyId" value="<?php echo intval($company->getCompanyId()); ?>">
            <input type="hidden" name="ts" value="<?php echo time(); ?>">
            <input type="hidden" name="personId" id="personId" value="">           
            <div class="form-group row">
                <div class="col-sm-8 float:left">
                    <h1><?php echo $company->getCompanyName(); ?></h1>
                </div>
                    <label for="personName" class="col-sm-12 col-form-label mt-3"> Start typing to select the New Person from the list below.</label>
                <div class="autocomplete-wrapper col-sm-12 mt-2 ">
                    <input type="text" class="form-control" placeholder="Start typing and select" name="personName" id="autocomplete"/>
                </div>
           </div>
        </form>

<?php    
        $title = (count($persons) == 1) ? 'Person' : 'Persons';
?>
    	<div class="full-box clearfix mt-2">
            <?php /* 
                A table titled "Person" or "Persons" (as appropriate) with one columns:                
                    * Name                
                This displays current people for this company.
            */ ?>
	    	<h2 class="heading">Current <?php  echo $title; ?></h2>		
            <table  class="table table-bordered table-striped text-left">
                <tbody>
                    <tr>
                        <th>Name</th>
                        <th>Action</th>
                    </tr>
                    <?php
                        foreach ($persons as $person) {                           
                             echo '<tr>';                            
                                echo '<td>' . $person->getPerson()->getFormattedName() . '</td>';
                                echo  '<td><a  id="linkDeletePersonCompany'. $person->getCompanyPersonId() .'" onclick="return confirm(\'Are you sure you want to Delete this Person?\')" href="addpersoncompany.php?act='."deleteperson".'&companyPersonId='. $person->getCompanyPersonId() .'&companyId='. $companyId .'&name='. $person->getPerson()->getFormattedName() .'">Delete</a></td>';
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
        if (suggestion.data  && suggestion.data > 0) {
            $("#personId").val(suggestion.data);    	
            $("#companypersonform" ).submit();
        }
    },
    paramName: 'q'
});

// The moment they start typing (or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
});

</script>

<?php
    include '../includes/footer_fb.php';
?>