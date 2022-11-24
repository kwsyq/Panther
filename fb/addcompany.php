<?php
/* fb/addcompany.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a company.
    This page will be a child of the page that invokes it.
    Company is always associated with current customer (which as of 2019-04 always SSS).

    No primary input.

    Optional INPUT $_REQUEST['act']. Only possible value: 'addcompany', which uses $_REQUEST['companyName'].
    
    On completion, navigates to the company page for the new company.

*/

include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-07-21, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$companyName = '';
$companyId = 0;

$error = '';
$errorId = 0;
$error_is_db = false;
$db = NULL;

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

if ($act == 'addcompany') {

    $v->rule('required', ['companyName']);

    if (!$v->validate()) {
        $errorId = '637196176709036789';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    }  else {
        $companyName = $_REQUEST['companyName'];
        $companyName = truncate_for_db ($companyName, 'companyName', 75, '637309332183702944'); //  handle truncation when an input is too long for the database.
        
        if (!$companyName) { //for safety.
            $error = "Error blank company name input, please fix and try again";
            $errorId = '637147964057217689';
        }
    }

    // The happy path is to do the action, navigate to the correct page (which will implicitly close the fancybox), and die, but there 
    // are many possible errors to check for along the way.
    if (!$error) {
        $db = DB::getInstance();
        
        $companyId = $customer->addCompany($companyName);
        if (!intval($companyId)) {
            $errorId = '637147973003088525';
            $error = 'Customer::addCompany method failed.';
        } else if ($companyId <= 0) {
            // $companyId will be an error code set by Company object (called via the Customer object), so have that object interpret it.
            // This will always set non-empty $error string. 
            list($error, $errorId) = Company::errorToText($companyId);
            $error_is_db = isDbError($companyId);
        }

        if (!$error) {
            // Happy path: do the action, navigate to the correct page, and die
            $c = new Company($companyId);                
            ?>
            <script type="text/javascript">
                top.window.location = '<?php echo $c->buildLink(); ?>';              
            </script>                
            <?php
            die();
        }
    }

    if ($error) {
        if ($error_is_db) {
            $logger->errorDb($errorId, $error, $db);
        } else {
            $logger->error2($errorId, $error);
        }
    }
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<style>    
    body {
        background: white !important;
    }
</style>
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

<div class="container-fluid">

<form name="note" action="addcompany.php" method="post" id="addCompanyForm">
    <input type="hidden" name="act" value="addcompany">
    <div class="form-group row">
        <label for="companyName" class="col-sm-4 col-form-label">Company Name</label>
        <div class="col-sm-8">
            <input type="text" name="companyName" class="form-control" placeholder="Company Name - Start typing and select" id="autocomplete" value="<?=$companyName?>" />
        </div>
    </div>
    <div class="form-group row mt-5">
        <button type="submit" id="addCompany" class="btn btn-secondary mr-auto ml-auto">Add company</button>
    </div>  
</form>
</div>
<script>

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#addCompanyForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        companyName:{
            required: true
        }
    }
});

validator.showErrors(jsonErrors);

// The moment they start typing (or pasting) in a field, remove the validator warning
$('input').on('keyup change', function() {
    $('#validator-warning').hide();
    if($('#companyName').hasClass('text-danger')){
        $('#companyName').removeClass('text-danger');
    }
});

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

</script>

<?php

include '../includes/footer_fb.php';

?>