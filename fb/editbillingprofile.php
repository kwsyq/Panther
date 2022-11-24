<?php
/*  fb/editbillingprofile.php

    EXECUTIVE SUMMARY: Edit a specified billing profile (always for an implicit but specific company: companyId is in the billing profile)

    PRIMARY INPUT: $_REQUEST['billingProfileId'].

    Optional $_REQUEST['act']. Only possible value: 'editbillingprofile', uses the following members of the $_REQUEST associative arrays:
        'multiplier', 'dispatch', 'termsId', 'contractLanguageId', 'gracePeriod', 'active',
        all corresponding to columns in DB table BillingProfile.

    Everything about 'active' is added 2020-02-12 JM.
*/

include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-08-03, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add
$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();
// From billingProfileId, identifies billing profile and company; makes sure
//  billing profile is valid & this company has this as one of its billing profiles; dies if it doesn't
//  all check out.

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'billingProfileId');
$v->rule('integer', 'billingProfileId');
$v->rule('min', 'billingProfileId', 1);

if( !$v->validate() ) {
    $errorId = '637320530350684178';
    $logger->error2($errorId, "billingProfileId : " . $_REQUEST['billingProfileId'] . "  not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid billingProfileId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe"; //Different Message for end user (in error.php) within iframe.
    header("Location: /error.php");
    die();
}

$billingProfileId = intval($_REQUEST['billingProfileId']);

if (!BillingProfile::validate($billingProfileId)) {
    $errorId = '637320531053009375';
    $logger->error2($errorId, "The provided billingProfileId ". $billingProfileId ." does not correspond to an existing DB person row in billingProfile table");
    $_SESSION["error_message"] = "BillingProfileId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$billingProfile = new BillingProfile($billingProfileId, $user);
$company = new Company($billingProfile->getCompanyId());

// getBillingProfiles RETURNS an array of associative arrays, each corresponding
// to one billingProfile that applies to this company. No particular order.
$bps = $company->getBillingProfiles(false, $error_is_db);

if ($error_is_db) { //true on query failed.
    $errorId = '637420060727518895';
    $error = "We could not display the Billing Profiles. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getBillingProfiles() method failed", $db);
}


$billingProfile = false;
$loc = false; // Once set, either an email address or a formatted location

foreach ($bps as $bp) {
    if ($bp['billingProfile']->getBillingProfileId() == $billingProfileId) {
        $billingProfile = $bp['billingProfile'];
        $loc = $bp['loc'];
        break; // added 2020-02-12 JM
    }
}

if (!$billingProfile) {
    $errorId = '637320641502861805';
    $logger->error2($errorId, "No BillingProfile found for the provided billingProfileId " . $billingProfileId );
    $_SESSION["error_message"] = "No Billing Profile found."; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

// contractLanguage
$contractLanguageIdsDB = array(); // Declare an array of contractLanguageId's.

$files = getContractLanguageFiles($error_is_db); // get Contract Language Files as an array.

if ($error_is_db) { //true on query failed.
    $errorId = '637420061564039352';
    $error .=  "We could not display the Contract Language Files for this Billing Profile. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getContractLanguageFiles() function failed", $db);
}

$keyOldLang = 0;
$contractLanguageIdOld = 0;
$contractLanguageFileOld = "";
foreach ($files as $key => $value) {
   
    $contractLanguageIdsDB[] = $value["contractLanguageId"]; //Build an array with valid contractLanguageId's from DB, table contractlanguage.

    if ($value['contractLanguageId'] == intval($billingProfile->getContractLanguageId())){ 
        $contractLanguageIdOld = $value['contractLanguageId'];
        $contractLanguageFileOld = $value['fileName'];
        $keyOldLang = $key;
    }
}


// End contractLanguage.

// Terms.
// Declare an array of termsId's. Add value 0 for : --Choose Terms--
$termsIdsDB = array("0");

$terms = getTerms($error_is_db); // get Terms as an array.
if ($error_is_db) { //true on query failed.
    $errorId = '637420062260553713';
    $error .= "We could not display the Terms for this Billing Profile. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getTerms() function failed", $db);
}

foreach ($terms as $value) {
    $termsIdsDB[] = $value["termsId"]; //Build an array with valid termName's from DB, table terms.
}

// End Terms.

if (!$error && $act == 'editbillingprofile') {

    $v->rule('numeric', ['multiplier', 'dispatch', 'gracePeriod']);
    $v->rule('in', 'contractLanguageId', $contractLanguageIdsDB); // contractLanguageId value must be in array.
    $v->rule('in', 'termsId', $termsIdsDB); // termsId value must be in array.


    if (!$v->validate()) {
        $errorId = '637320699326412349';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {

        $cleaned_request_billing = Array(
            'multiplier' => (array_key_exists('multiplier', $_REQUEST)? $_REQUEST['multiplier']: 1),
            'dispatch' => (array_key_exists('dispatch', $_REQUEST)? $_REQUEST['dispatch']: ''),
            'termsId' => (array_key_exists('termsId', $_REQUEST)? $_REQUEST['termsId']: 0),
            'contractLanguageId' => (array_key_exists('contractLanguageId', $_REQUEST)? $_REQUEST['contractLanguageId']: 0),
            'gracePeriod' => (array_key_exists('gracePeriod', $_REQUEST)? $_REQUEST['gracePeriod']: 0),
            'useName' => (array_key_exists('useName', $_REQUEST)? $_REQUEST['useName']: 0),
            'companyPersonId' => (array_key_exists('companyPersonId', $_REQUEST)? $_REQUEST['companyPersonId']: 0),
            'varyLocationId' => (array_key_exists('varyLocationId', $_REQUEST)? $_REQUEST['varyLocationId']: 0)
        );

        $billingProfile->update($cleaned_request_billing); // Update billing profile (row in DB table BillingProfile)
        
        unset($cleaned_request_billing);
    }

    if (!$error) {
        // Do the action, wait a second, close the fancybox, and die.
    ?>
        <script type="text/javascript">
        setTimeout(function() {
            parent.$.fancybox.close();
            //refresh parent page after Update action.
            parent.location.reload(true);
        }, 1000);
        </script>
        <?php
        die();
    }
    unset($contractLanguageIdsDB, $termsIdsDB);
}

$active = !!$billingProfile->getActive(); // double logical negation makes this a Boolean

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>
<style>
/* George: beautify the form in popup*/
.dbhead{max-width: 99%;background:#fff;}
 body, form {background:#fff;}
</style>

<div id="container" class="clearfix">
    <div class="clearfix dbhead">
        <?php /* HTML FORM largely implemented as table.
                 Hidden billingProfileId, act='editbillingprofile', ts=timestamp
        */ ?>
        <form id="billingprofileform" name="billingprofileform" method="POST" action="editbillingprofile.php">
            <input type="hidden" name="billingProfileId" value="<?php echo intval($billingProfile->getBillingProfileId()); ?>">
            <input type="hidden" name="act" value="editbillingprofile">
            <input type="hidden" name="ts" value="<?php echo time(); ?>">
            <table class="table table-bordered table-striped text-left">
                <tr>
                    <?php /* echo company name as header */ ?>
                    <td colspan="2" width="100%"><h1><?php echo $company->getCompanyName(); ?></h1></td>
                </tr>
                <?php
                    // get name of person associated with this billing profile
                    $name = '';
                    $cpid = $billingProfile->getCompanyPersonId();
                    if (intval($cpid)) {
                        $cp = new CompanyPerson($cpid);
                        $p = $cp->getPerson();
                        $name = $p->getFormattedName(1);
                    }
                ?>
                <tr>
                    <th colspan="1" width="10%">Name</th>
                    <td colspan="1"><?php echo $name; ?></td>
                </tr>
                    <th>Status</th>
                    <td>
                        <input id="status-active" type="radio" name="active" value="1"<?php echo ($active ? ' checked' : ''); ?>>&nbsp;<label for="status-active">Active</label>
                        <input id="status-inactive" type="radio" name="active" value="0"<?php echo ($active ? '' : ' checked'); ?>>&nbsp;<label for="status-inactive">Inactive</label>
                    </td>
                <tr>
                </tr>
                <tr>
                    <?php /* either an email address or a formatted location (presumably a mailing address) */ ?>
                    <th colspan="1" width="10%">Location</th>
                    <td colspan="1"><?php echo $loc; ?></td>
                </tr>
                <tr>
                    <?php /* a factor to increase time and/or money for a difficult client. */ ?>
                    <th colspan="1" width="10%">Multiplier</th>
                    <td colspan="1"><input type="text" name="multiplier" id="multiplier"  value="<?php echo $billingProfile->getMultiplier(); ?>"></td>
                </tr>
                <tr>
                    <?php /* day of month to send bill. Zero or null => send immediately. */ ?>
                    <th colspan="1" width="10%">Dispatch</th>
                    <td colspan="1"><input type="text" name="dispatch" id="dispatch" value="<?php echo intval($billingProfile->getDispatch()); ?>"></td>
                </tr>
                <tr>
                    <?php /* Terms: HTML SELECT, initially select current value.
                             For each option, value is termsId; display appropriate name.
                             First OPTION is value 0, display is '--Choose Terms--' */ ?>
                    <th colspan="1" width="10%">Terms</th>
                    <td colspan="1"><select id="termsId" name="termsId"><option value="0">-- Choose Terms --</option><?php
                        foreach ($terms as $term) {
                            $selected = ($term['termsId'] == intval($billingProfile->getTermsId())) ? ' selected ':'' ;
                            echo '<option value="' . intval($term['termsId']) . '"' .$selected . '>' . $term['termName'] . '</option>';
                        }
                    ?></td>
                </tr>
                <tr>
                    <?php /* Contract Language, implemented similarly to Terms */ ?>
                    <th colspan="1" width="10%" nowrap>Contract Language</th>
                    <td colspan="1"><select  id="contractLanguageId" name="contractLanguageId">
                    <?php
                        echo '<option value="' . $contractLanguageIdOld . '" >' . $contractLanguageFileOld . '</option>';
                        echo '<option value="">-- Choose Language --</option>';
                        foreach ($files as $file) {
                           /* $selected = ($file['contractLanguageId'] == intval($billingProfile->getContractLanguageId())) ? ' selected ' : '';
                            if ($file['contractLanguageId'] == intval($billingProfile->getContractLanguageId())){
                                //echo '<option value="' . $file['contractLanguageId'] . '">' . $file['fileName'] . '</option>';
                                echo '<option value="' . $file['contractLanguageId'] . '"' . $selected .  '>' . $file['fileName'] . '</option>';
                           
                            }*/
                            
                            if($file['status'] == 1) {
                                echo '<option value="' . $file['contractLanguageId'] . '" >' . $file['fileName'] . '</option>';
                            }
                        
                        }
                    ?></td>
                </tr>
                <tr>
                    <?php /* Grace Period in days */ ?>
                    <th colspan="1" width="10%">Grace Period</th>
                    <td colspan="1"><input type="text" name="gracePeriod" id="gracePeriod" value="<?php echo intval($billingProfile->getGracePeriod()); ?>"></td>
                </tr>
            </table>
            <center>
                <?php /* "Submit" button for form. */ ?>
                <input type="submit" id="updateBillingProfile" class="btn btn-secondary mr-auto ml-auto"  value="update">
            </center>
        </form>
    </div>
</div>

<script>
var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#billingprofileform').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: {
        'multiplier':{
            number: true
        },
        'dispatch':{
            number: true
        },
        'gracePeriod':{
            number: true
        }
    }
});
validator.showErrors(jsonErrors);

// The moment they press on select, remove the validator warning
$('select').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#termsId-error').hide();
    if ($('#termsId').hasClass('text-danger')){
        $("#termsId").removeClass("text-danger");
    }
    $('#contractLanguageId-error').hide();
    if ($('#contractLanguageId').hasClass('text-danger')){
        $("#contractLanguageId").removeClass("text-danger");
    }
});

   // George add with Jquery class form-control Bootstrap, to tables components.
   $('input[type=text]').addClass('form-control');
   $('input[type=checkbox]').addClass('form-control');
   $('select').addClass('form-control');
</script>
<?php
    include '../includes/footer_fb.php';
?>