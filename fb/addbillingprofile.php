<?php

/* fb/addbillingprofile.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a billing profile for a specified company.
    This page will be a child of the page that invokes it.

    PRIMARY INPUT: $_REQUEST['companyId'].

    Optional $_REQUEST['act']. Only possible value: 'addbillingprofile', uses the
        following members of the $_REQUEST associative array:
        * 'companyId' (e.g. $_REQUEST['companyId'], similarly for all the others)
        * 'companyPersonId' - NOTE that if you intend to send bills to the
                              company email address or company location,
                              it is OK to omit selection of a companyPersonId entirely.
                              Used only if 'useName' is "truthy"; if so, it also goes in the billingProfile row.
                              If used, must be associated with the companyId just above.
        * 'multiplier'
        * 'dispatch'
        * 'termsId'
        * 'contractLanguageId'
        * 'gracePeriod'
        * 'usename' - quasi-Boolean, see remarks on 'companyPersonId' of someone whose *name* we want to use;
                      irrelevant if $_REQUEST['companyPersonId'] is zero or blank
        * 'varyLocationId' - one of the following; we use only *one* of these in any given
          row of DB table BillingProfile
            'pe:' followed by a personEmailId
            'pl:' followed by a personLocationId.
            'ce:' followed by a companyEmailId
            'cl:' followed by a companyLocationId.

    All of these except 'usename' and 'varyLocationId' correspond exactly
    to columns in DB table BillingProfile.

    // >>>00001 it is possible that there is something more going on about 'usename' and 'companyPersonId' that I (JM) am missing.
    // See closely related code/comments in inc/classes/Company.class.php function public function addBillingProfile.

*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-07-30, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$companyId = 0;
$multiplier = 0;
$dispatch= 0;
$termsId= 0;
$contractLanguageId = 0;
$gracePeriod = 0;
$useName = 0;
$companyPersonId = 0;
$varyLocationId = 0;

$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'companyId');
$v->rule('integer', 'companyId');
$v->rule('min', 'companyId', 1);

if( !$v->validate() ) {
    $errorId = '637317006846356799';
    $logger->error2($errorId, "companyId : " . $_REQUEST['companyId'] . "  not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid companyId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$companyId = intval($_REQUEST['companyId']); // George 2020-07-30. The companyId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'company'.

if (!Company::validate($companyId)) {
    $errorId = '637317008251166767';
    $logger->error2($errorId, "The provided companyId ". $companyId ." does not correspond to an existing DB person row in company table");
    $_SESSION["error_message"] = "CompanyId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die();
}

$company = new Company($companyId, $user);

// contractLanguage
// Default of dropdown selection is filename 140404_Standard_Contract_Language_(NL).pdf
// with contractLanguageId 3
$contractLanguageIdsDB = array(); // Declare an array of contractLanguageId's.

$files = getContractLanguageFiles($error_is_db); // get Contract Language Files as an array.

if ($error_is_db) { //true on query failed.
    $errorId = '637317120658571770';
    $error =  "We could not display the Contract Language Files for this Billing Profile. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getContractLanguageFiles() function failed", $db);
}

// Default Contract Language
$contractLanguageIdDefault = 0;
$contractLanguageFileDefault = "";
foreach ($files as $value) {
    $contractLanguageIdsDB[] = $value["contractLanguageId"]; //Build an array with valid contractLanguageId's from DB, table contractlanguage.
    
    if($value['type'] == 'NL' && $value['status'] == 1) {
        $contractLanguageIdDefault = $value["contractLanguageId"];
        $contractLanguageFileDefault = $value["fileName"];
    }
}

// End contractLanguage.

// Terms.
// Declare an array of termsId's. Add value 0 for : --Choose Terms--
// Default selection of dropdown is "Due on receipt", termsId 2
$termsIdsDB = array("0");

$terms = getTerms($error_is_db); // get Terms as an array.

if ($error_is_db) { //true on query failed.
    $errorId = '637318099635673531';
    $error .= "We could not display the Terms for this Billing Profile. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getTerms() function failed", $db);
}

foreach ($terms as $value) {
    $termsIdsDB[] = $value["termsId"]; //Build an array with valid termName's from DB, table terms.
}

// End Terms.
if (!$error && $act == 'addbillingprofile') {

    $v->rule('numeric', ['multiplier', 'dispatch', 'gracePeriod']);
    $v->rule('in', 'contractLanguageId', $contractLanguageIdsDB); // contractLanguageId value must be in array.
    $v->rule('in', 'termsId', $termsIdsDB); // termsId value must be in array.

    if (!$v->validate()) {
        $errorId = '637317054619792850';
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

        $succces = $company->addBillingProfile($cleaned_request_billing);
        if (!$succces) {
            $errorId = '637420045406620970';
            $error = 'We colud not Add a new BillingProfile. Database error.';
            $logger->error2($errorId, $error);
        } else {
            ?>
            <script type="text/javascript">
            setTimeout(function() {
                parent.$.fancybox.close();
                parent.location.reload(true);
            }, 1000);
            </script>
            <?php
            die();
        }
        unset($cleaned_request_billing);
    }
    unset($contractLanguageIdsDB, $termsIdsDB);
}

$cps = $company->getCompanyPersons($error_is_db);

if ($error_is_db) { //true on query failed.
    $errorId = '637318009290710158';
    $error .= "We could not display the Company Persons for this Company. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getCompanyPersons() method failed", $db);
}

//$emailTypes = $company->getEmailTypes(); // George 2020-07-30. We don't use it on this file.
$emails = $company->getEmails($error_is_db);

if ($error_is_db) { //true on query failed.
    $errorId = '637318046148276678';
    $error .= "We could not display the Emails for this Company. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getEmails() method failed", $db);
}

$locations = $company->getLocations($error_is_db);

if ($error_is_db) { //true on query failed.
    $errorId = '637318046499143244';
    $error .= "We could not display the Locations for this Company. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getLocations() method failed", $db);
}

//$locationTypes = $company->getLocationTypes(); // George 2020-07-30. We don't use it on this file.

include '../includes/header_fb.php';
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<script type="text/javascript">

// INPUT companyPersonId
// Use companyPersonId to load two tables here that, respectively, allow selection of
//  personEmail & personLocation
var loadPerson = function (companyPersonId) {
    $("#personEmailTable").empty();
    $("#personLocationTable").empty();
	if(companyPersonId){
        $.ajax({
            url: '/ajax/get_emails_locations_cp_person.php',
            data:{
                companyPersonId:companyPersonId
            },
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status'] == 'success') {
                    /* Fill in Person Email TABLE; radio buttons offer each email associated with this person. */
                    for (var i = 0; i < data['emails'].length; ++i) {
                        var row = $('<tr>');

                        row.append( $('<td>').html(  '<input style="width:15px;height:15px" type="radio" name="varyLocationId" id="varyLocationIdpe' + escape(data['emails'][i]['personEmailId']) + '" value="pe:' + escape(data['emails'][i]['personEmailId']) + '">'));
                        row.append( $('<td>').html(data['emails'][i]['emailAddress']));
                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                        //row.append( $('<td>').html('<a href="javascript:loadDescriptor(' + data['descriptorCategories'][i][0] + ')">' + data['descriptorCategories'][i][1] + '</a>'));
                        // END COMMENTED OUT BY MARTIN BEFORE 2019
                        $("#personEmailTable").append(row);
                    }

                    /* Fill in Person Location TABLE; radio buttons offer each llocation associated with this person. */
                    for (var i = 0; i < data['locations'].length; ++i) {
                        var row = $('<tr>');

                        row.append( $('<td>').html(  '<input style="width:15px;height:15px" type="radio" name="varyLocationId" id="varyLocationIdpl' + escape(data['locations'][i]['personLocationId']) + '"  value="pl:' + escape(data['locations'][i]['personLocationId']) + '">'));
                        row.append( $('<td>').html(data['locations'][i]['formattedAddress']));
                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                        //row.append( $('<td>').html('<a href="javascript:loadDescriptor(' + data['descriptorCategories'][i][0] + ')">' + data['descriptorCategories'][i][1] + '</a>'));
                        // END COMMENTED OUT BY MARTIN BEFORE 2019
                        $("#personLocationTable").append(row);
                    }
                }
                else {
                    alert('there was a problem updating');
                }
            },
             error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    }
}

</script>

<style>

 /* George: beutify the popup form. */
 .dbhead{max-width: 99%;background:#fff;}
 body, form {background:#fff;}

</style>

<div id="container" class="clearfix">
    <div class="clearfix dbhead">
        <?php /* Self-submitting form, largely implemented as a table
                 A few hidden INPUTs (companyId; act; ts (current time)) */  ?>
        <form id="billingprofileform" name="billingprofileform" method="POST" action="addbillingprofile.php">
            <input type="hidden" name="companyId" value="<?php echo intval($company->getCompanyId()); ?>">
            <input type="hidden" name="act" value="addbillingprofile">
            <input type="hidden" name="ts" value="<?php echo time(); ?>">

            <table class="table table-bordered table-striped text-left">
                <?php /* Company name as a heading spanning the first row. */  ?>
                <tr>
                    <td colspan="5" width="100%"><h1><?php echo $company->getCompanyName(); ?></h1></td>
                </tr>
                <?php /* a factor to increase time and/or money for a difficult client */  ?>
                <tr>
                    <th colspan="2" width="10%">Multiplier</th>
                    <td colspan="3"><input type="text" name="multiplier" id="multiplier" value="1"></td>
                </tr>
                <?php /* day of month to send bill. Zero => send immediately  */  ?>
                <tr>
                    <th colspan="2" width="10%">Dispatch</th>
                    <td colspan="3"><input type="text" name="dispatch" id="dispatch" value=""></td>
                </tr>
                <tr>
                    <?php /* Terms: HTML SELECT dropdown; OPTIONs effectively filled in from DB table Terms. */  ?>
                    <th colspan="2" width="10%">Terms</th>
                    <td colspan="3"><select id="termsId" name="termsId"><option value="0">-- Choose Terms --</option><?php
                        foreach ($terms as $term) {
                            $selected = ($term['termsId'] == 2) ? ' selected ':'' ;
                            echo '<option value="' . intval($term['termsId']) . '"' .$selected . '>' . $term['termName'] . '</option>';
                        }
                    ?></td>
                </tr>
                <tr>
                    <?php /* Contract Language: HTML SELECT dropdown; OPTIONs effectively filled in from DB table ContractLanguage. */  ?>
                    <th colspan="2" width="10%" nowrap>Contract Language</th>
                    <td colspan="3"><select id="contractLanguageId" name="contractLanguageId">
                    <?php 
                    echo '<option value="' . $contractLanguageIdDefault . '" >' . $contractLanguageFileDefault . '</option>';
                    echo '<option value="">-- Choose Language --</option>';
                      
                    foreach ($files as $file) {
                       // $selected = ($file['fileName'] == '140404_Standard_Contract_Language_(NL).pdf') ? ' selected ' : '';
                        if($file['status'] == 1) {
                            echo '<option value="' . $file['contractLanguageId'] . '">' . $file['fileName'] . '</option>';
                        }
                    
                    }
                    ?></td>
                </tr>
                <tr>
                    <?php /* Grace period: in days. */  ?>
                    <th colspan="2" width="10%">Grace Period</th>
                    <td colspan="3"><input type="text" name="gracePeriod" id="gracePeriod" value="45"></td>
                </tr>
                <tr>
                    <?php /* Headers for row that follows. */  ?>
                    <th width="28%">Person</th>
                    <th width="18%">Person Email</th>
                    <th width="18%">Person Location</th>
                    <th width="18%">Company Email</th>
                    <th width="18%">Company Location</th>
                </tr>
                <tr>
                    <?php /* Every column in this row will be a nested table */  ?>
                    <td valign="top">
                        <table border="0" cellpadding="0" cellspacing="0" class="table table-bordered table-striped text-left">
                            <tr>
                                <?php
                                /* "Person" */
                                /* Checkbox for whether we are picking a companyPerson as the recipient of the billing */
                                echo '<td><input style="width:15px;height:15px" type="checkbox" name="useName" id="useName" value="1"></td>';

                                /* HTML SELECT dropdown; OPTIONs offer each person associated with this company.
                                   NOTE that via jQuery, any change here triggers local function loadPerson to load the subtables
                                   for personEmail & personLocation*/
                                echo '<td><select name="companyPersonId" id="companyPersonId" ><option value="">-- choose person --</option>';
                                foreach ($cps as $cp) {
                                    echo '<option value="' . $cp->getCompanyPersonId() . '">' . $cp->getPerson()->getFormattedName(1) . '</option>';
                                }

                                echo '</select></td>';
                                ?>
                            </tr>
                        </table>
                    </td>
                    <td valign="top">
                        <table id="personEmailTable">
                        </table>
                    </td>
                    <td valign="top">
                        <table id="personLocationTable">
                        </table>
                    </td>
                    <td valign="top">
                        <?php
                        /* Company Email TABLE; radio buttons offer each email associated with this company. */
                        echo '<table>';
                        foreach ($emails as $email) {
                            $emailAddress = trim($email['emailAddress']);
                            if (strlen($emailAddress)) {
                                echo '<tr>';
                                    echo '<td><input style="width:15px;height:15px" type="radio"  name="varyLocationIdce:' . $email['companyEmailId'] . '" value="ce:' . $email['companyEmailId'] . '"></td>';
                                    echo '<td>' . $emailAddress . '</td>';
                                echo '</tr>';
                            }
                        }
                        echo '</table>';
                        ?>
                    </td>
                    <td valign="top">
                        <?php
                        /* Company Location TABLE; radio buttons offer each location associated with this company. */
                        echo '<table>';
                        foreach ($locations as $row) {
                            $location = new Location($row['locationId']);
                            echo '<tr>';
                                //echo '<td><a href="/location.php?locationId=' . $location->getLocationId() . '">' . $location->getFormattedAddress() . '</a></td>'; // Not sure who commented this out, probably Martin. - JM
                                echo '<td><input style="width:15px;height:15px" type="radio" id="varyLocationIdcl' . $row['companyLocationId'] . '"  name="varyLocationId" value="cl:' . $row['companyLocationId'] . '"></td>';
                                echo '<td>' . $location->getFormattedAddress() . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                        ?>
                    </td>
                </tr>
            </table>
            <center>
                <?php /* Under the table but still in the form, a submit button labeled "add" */  ?>
                <input type="submit" id="addBillingProfile" class="btn btn-secondary mr-auto ml-auto" value="Add">
            </center>
        </form>

        <script>
            $("#companyPersonId").change(function() {
                loadPerson($(this).val());
            });
        </script>
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

// The moment they start typing(or pasting) in a field, remove the validator warning
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

// George: add with jQuery class form-control Bootstrap, to tables components.
  $('input[type=text]').addClass('form-control');
  $('input[type=radio]').addClass('form-control');
  $('input[type=checkbox]').addClass('form-control');
  $('select').addClass('form-control');
</script>

<?php
    include '../includes/footer_fb.php';
?>