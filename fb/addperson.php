<?php
/* fb/addperson.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a person. Adds a row in person table 
        and rows in company and companyPerson table that effectively set up the person themself as a 
        "bracket company" (username in square brackets as company name).
    This page will be a child of the page that invokes it.
    Company is always associated with current customer (which as of 2019-04 always SSS).

    No primary input.

    Optional INPUT $_REQUEST['act']. Only possible value: 'addperson', which uses $_REQUEST['username'].
    
    On completion, navigates to the person page for the new person.
    
    Significantly rewritten 2019-11-27 JM to bring in first & last name, use those when possible for bracket company, and
    to do a better job of how we require a username.
    
*/

include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-07-22, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;

$username = '';
$firstname = '';
$lastname = '';
$personId = 0;

$db = NULL;

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

if ($act == 'addperson') {

    $v->rule('required', 'username');
    $v->rule('email', 'username');

    if (!$v->validate()) {
        $errorId = '637144392628277306';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";        
    } else {
        // The happy path is to do the action, navigate to the correct page (which will implicitly close the fancybox), and die, but there 
        // are many possible errors to check for along the way.
        $username = $_REQUEST['username'];
        $username = truncate_for_db ($username, 'username', 128, '637310109720192681');
    
        $firstname = isset($_REQUEST['firstname']) ? $_REQUEST['firstname'] : ''; // Not required.
        $firstname = truncate_for_db ($firstname, 'firstname', 128, '637310110854700259'); 

        $lastname = isset($_REQUEST['lastname']) ? $_REQUEST['lastname'] : ''; // Not required.
        $lastname = truncate_for_db ($lastname, 'lastname', 128, '637310111209348866');

        $db = DB::getInstance();
  
        $personId = $customer->addPerson($username, $firstname, $lastname);
        if (!intval($personId)) {
            $errorId = '637172360521599858';
            $error = 'addPerson method failed.';
        } else if ($personId <= 0) {
            // $person will be an error code set by Person object (called via the Customer object), so have that object interpret it.
            // This will always set non-empty $error string. 
            list($error, $errorId) = Person::errorToText($personId);
            $error_is_db = isDbError($personId);
        } 

        if (!$error) {
            $p = new Person($personId);
            if ( !$p ) {
                $errorId = '1574878357';
                $error = 'Failed to create person object.';
            }
        }
        if (!$error) {
            $companyName = ($firstname || $lastname) ?
                "$firstname $lastname" : $username;
            
            // This is the creation of the "bracket company". 
            $companyId = $customer->addCompanyWithOptions($companyName, true, '[', ']');
            if (!intval($companyId)) {
                $errorId = '637172362886904029';
                $error = 'addCompany method failed.';
            } else if ($companyId <= 0) {
                // $error will be set
                list($error, $errorId) = Company::errorToText($companyId);
                $error_is_db = isDbError($companyId);
            }

            if (!$error) {
                // Create a "bracket company" for this person (see http://sssengwiki.com/Person%2C+Employee%2C+User%2C+and+all+that)
                $exists = entityExists("companyPerson",
                                            "companyId", 
                                            $companyId,
                                            "personId = " . $personId);

                if ($exists === NULL) {
                    // server issue, but this point should not be reached.
                    // >>>00001 JM: Is that any truer here than for any other server issue?
                    $errorId = '637310165962075796';
                    $error = "Company-Person relation could not be retrieved from database.";
                } else if ($exists) {
                    // The company was created above, so, this point should not be reached.
                    // Even the company-person relation already exists, this is not a fatal error;
                    // therefore,continue as normal.
                    $logger->warn2('The company-person ' .$companyId. '-' . $personId . ' relation already exists in db, even though we just created both!');
                } else {
                    $query = " INSERT INTO " . DB__NEW_DATABASE . ".companyPerson (companyId, personId) VALUES (";
                    $query .= " " . intval($companyId) . " ";                   
                    $query .= " ," . intval($personId) . ") ";                      
                    $result = $db->query($query);
                    
                    if (!$result) {
                        $errorId = '1574878387';
                        $error = "Error connecting person to bracket company";
                        $error_is_db = true;
                    }
                }
            }
            if (!$error) {
?>
                <script type="text/javascript">
                    top.window.location = '<?php echo $p->buildLink(); ?>';
                    setTimeout(function() { parent.$.fancybox.close(); }, 1000);                
                </script>
<?php
                die();
            }
        }
    }

    if ($error) {
        if ($error_is_db) {
            $logger->errorDb($errorId, $error, $db);
        } else {
            $logger->error2($errorId, $error);
        }
    }
} // end if addperson

include '../includes/header_fb.php';

// Table (under the heading "Add Person") containing self-submitting form with hidden act='addperson', 
// editable Username (email address), submit button labeled "add person".

if ($error) {
    echo "<div id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<style>
    
    body{
        background: white !important;
    }
</style>
<div class="container-fluid">

<form name="note" action="addperson.php" method="post" id="addPersonForm">
    <input type="hidden" name="act" value="addperson">    
    <div class="form-group row">
        <label for="username" class="col-sm-4 col-form-label">Username(email address)</label>
        <div class="col-sm-8">
          <input type="email" class="form-control" id="username" name="username" value="<?=$username?>" placeholder="username (email address)" required maxlength="128">      
        </div>
    </div>
    <div class="form-group row">
        <label for="firstname" class="col-sm-4 col-form-label">First Name (optional)</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" id="firstname" name="firstname" placeholder="first name" value="<?=$firstname?>" maxlength="128">
        </div>
    </div>
    <div class="form-group row">
        <label for="lastname" class="col-sm-4 col-form-label">Last Name (optional)</label>
        <div class="col-sm-8">
          <input type="text" class="form-control" id="lastname" name="lastname" placeholder="last name" value="<?=$lastname?>" maxlength="128">
        </div>
    </div>
    <div class="form-group row mt-5">
        <button type="submit" id="addPerson" class="btn btn-secondary mr-auto ml-auto">Add person</button>
    </div>    
</form>
</div>
<script>

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#addPersonForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        'email':{  
            required: true,  
            email: true
        },   
    }
});

validator.showErrors(jsonErrors);

// The moment they start typing(or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#username-error').hide();
    if ($('#username').hasClass('text-danger')) {
        $('#username').removeClass('text-danger');
    }
});
</script>
<?php
include '../includes/footer_fb.php';
?>
