<?php
/* fb/addcompanyperson.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to Add a row in table companyPerson. 
    Starts from person. Company & person should both already exist.
    This page will be a child of the page that invokes it.

    PRIMARY INPUT: $_REQUEST['personId'].
    
    DISPLAY: a self-submitting form with hidden companyId (input).
    "New company" is in an "autocomplete" INPUT field that uses /ajax/autocomplete_company.php.
    After you select the New Company from the list companyId is set (initially blank).
    The INSERT action is performed when you select from the autocomplete choices.

    Delete Action : $act == 'deletecompany'.
    PRIMARY INPUT:  $_REQUEST['companyPersonId']. //companypersonId
    Delete action and messages are handled by static method Company::deleteCompanyPerson.

    Delete event:
    1. If we found tables with companyPersonId same value in the given DB. This association can not been Removed.
    2. We don't have entries with companyPersonId same value in the given DB. Safe to Delete the Association.
*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-06-09, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// End ADD

$error = '';
$errorId = 0;
$companyId = "";
$personId = 0;
$error_is_db = false;
$db = DB::getInstance();
$person = NULL;
$name= "";
$warning_bool = "";
$warning= "";

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'personId');
$v->rule('integer', 'personId');
$v->rule('min', 'personId', 1);

if( !$v->validate() ) {
    $errorId = '637205578577128790';
    $logger->error2( $errorId, "PersonId : " . $_REQUEST['personId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " The given Person is not valid. Please check the input."; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$personId = intval($_REQUEST['personId']);

if (!Person::validate($personId)) { 
    $errorId = '637205603370444377';
    $logger->error2($errorId, "The provided personId:  $personId does not correspond to an existing DB row in person table");
    $_SESSION["error_message"] = " The given Person is not valid. Please check the input."; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$person = new Person($personId, $user);

if (isset($_REQUEST['companyId'])) { // George 2020-06-03. This will be set when the selection is from the list.

    $v->rule('required', 'companyId');
    $v->rule('integer', 'companyId'); 
    $v->rule('min', 'companyId', 1);

    if (!$v->validate()) { //Check companyId if is integer and min 1.
        $errorId = '637260893539340839';
        $logger->error2($errorId, "CompanyId ". $_REQUEST['companyId'] ." is invalid. Selection not from the list ".json_encode($v->errors()));
        $error = "Please select a company from the list.";
    }

    if (!$error) {
        $companyId = $_REQUEST['companyId'];
        if (!Company::validate($companyId)) {   //Check for valid companyId.
            $errorId = '637260894906032239';
            $logger->warn2($errorId, "The selected companyId:  $companyId does not correspond to an existing DB company row in company table");
            $error = "This company is no longer exist in our system. You can select another company from the list.";
        }
    }

    if (!$error) { //All good for action.

        // George: An array for the situation Duplicate Company from list. Warning Message with Company name.
        $companies_already_associated = $person->getCompanies();
        $already_associated = false;

        foreach ($companies_already_associated as $cId) {
            if ($companyId == $cId->getCompanyId()) {
                $name = $cId->getCompanyName();
                $already_associated = true;
                break;
            }
        }

        if ($already_associated) {
            $error = "<b>" . $name . "</b> already associated, you can select another company";

        } else {
            // Add association between a Company and Person, entry in table companyPerson.
            $success = CompanyPerson::addCompanyPerson($companyId, $personId);

            if (!$success) {
                $errorId = '637205610015296102';
                $error = 'Add New Company to Person failed. Database error.'; //message for user
                $logger->errorDb($errorId, "Add New Company to Person failed: Hard DB error", $db);
            } 
        }
    }
}
    

/* George 2020-05-08 IMPROVMENT: I created in CompanyPerson class a static method called deleteCompanyPerson(), with three parameters:  
companypersonId, the entity: "person" or "company"; and name of company/person, table companyPerson.
Now we can take avantage of variable $act.
On click "Delete" action, For safety, Javascript message: "Are you sure you want to Delete this Company?". */
   
else if ($act == 'deletecompany') {

    $v->rule('required', ['companyPersonId', 'name']);
    $v->rule('integer',  'companyPersonId');
    $v->rule('min', 'companyPersonId', 1);

    if (!$v->validate()) {
        $errorId = '637260201316495561';
        $error = "CompanyPersonId ". $_REQUEST['companyPersonId'] ." or name ". $_REQUEST['name'] ." is invalid ".json_encode($v->errors());
        $logger->error2($errorId, $error);
    } else { //All good for delete association.

        $companypersonId = intval($_REQUEST['companyPersonId']);
        $name = $_REQUEST['name'];
        
        list($warning_bool, $warning) = CompanyPerson::deleteCompanyPerson($companypersonId, "person", $name);
    }
}
// George 2020-06-16. We need to get current companies after Possible actions: addCompanyPerson or deleteCompanyPerson.
// This can have entrys or not. Log error on query failed.
$companies = $person->getCompanies($error_is_db);
if ($error_is_db) { //true on query failed
    $errorId = '637402559318429768';
    $error = 'We could not display the current Companies already associated to this Person. Database Error.'; // message for User
    $logger->errorDB($errorId, "getCompanies method failed => Hard DB error ", $db);
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
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
        <?php /*Self-submitting form with hidden personId (input), act='addcompanyperson', ts=time, companyId (initially blank); 
                person name is displayed but not-editable. 
                "New company" is in an "autocomplete" INPUT field that uses /ajax/autocomplete_company.php; submit button is labeled "add". 
                Typically there is no need to hit the "add" button: the INSERT action is performed when you select from the autocomplete choices.
                Ron & Damon confirm 2020-04-02 that they are happy with this.
        */ ?>
     
        <form id="companypersonform" name="companyperson" method="POST" action="addcompanyperson.php">
            <input type="hidden" name="personId" value="<?php echo intval($person->getPersonId()); ?>">
            <input type="hidden" name="ts" value="<?php echo time(); ?>">
            <input type="hidden" name="companyId" id="companyId" value="">
            
            <div class="form-group row">
               <div class="col-sm-8 float:left">
                   <h1><?php echo $person->getFirstName() . '&nbsp;' . $person->getLastName(); ?></h1>
               </div>
                   <label for="companyName" class="col-sm-12 col-form-label mt-3"> Start typing to select the New Company from the list below.</label>
                <div class="autocomplete-wrapper col-sm-12 mt-2 ">
                    <input type="text" class="form-control" placeholder="Start typing and select"  name="companyName" id="autocomplete"/>
                </div>       
            </div>
        </form>
    
        <?php 

            $title = (count($companies) == 1) ? 'Company' : 'Companies';
        ?>
        <div class="full-box clearfix mt-2">
            <h2 class="heading">Current <?php  echo $title; ?></h2>
            <?php /* 
                A table titled "Company" or "Companies" (as appropriate) with four columns:                
                    * Company Name
                    * Company Nickname
                    * URL
                    * Action
                This displays current companies for this person.
            */ ?>
            <table class="table table-bordered table-striped text-left">
                <tbody>
                    <tr>
                        <th>Company Name</th>
                        <th>Company Nickname</th>
                        <th>URL</th>
                        <th>Action</th>
                    </tr>
                    <?php
                        foreach ($companies as $company) {
                            echo '<tr>';        
                                echo '<td>' . $company->getCompanyName() . '</td>';
                                echo '<td>' . $company->getCompanyNickname() . '</td>';
                                echo '<td>' . $company->getCompanyURL() . '</td>';
                                echo  '<td><a id="linkDeleteCompanyPerson'.  $company->companyPersonId .'" onclick="return confirm(\'Are you sure you want to Delete this Company?\')" href="addcompanyperson.php?act='."deletecompany".'&companyPersonId='. $company->companyPersonId .'&personId='. $personId .'&name='. urlencode($company->getCompanyName()) .'">Delete</a></td>';                           
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
    serviceUrl: '/ajax/autocomplete_company.php',
    onSelect: function (suggestion) {
        // if-clause ADDED 2020-04-08 as part of fixing http://bt.dev2.ssseng.com/view.php?id=105 (Person error on company page)
        // Not clear why this would fire with suggestion.data blank or zero, but it clearly did.
        if (suggestion.data && suggestion.data > 0) {
            $("#companyId").val(suggestion.data);
            $("#companypersonform" ).submit();
        }
    },
    paramName:'q'
});

// The moment they start typing (or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
});

</script>

<?php
    include '../includes/footer_fb.php';
?>