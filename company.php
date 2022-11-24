<?php
/*  company.php

   EXECUTIVE SUMMARY: This is a top-level page. Allows user to view and edit data about a company.
   There is a RewriteRule in the .htaccess to allow this to be invoked as just "company/foo" rather than "company.php?companyId=foo".

   PRIMARY INPUT _REQUEST['companyId'] - identifies company.

   Optional inputs:
   * $_REQUEST['act']. Possible values: 'updatecompany', 'addphone', 'updatephone', 'addemail', 'updateemail', 'updatelocationtype'.
   * Other inputs that provide content for these actions are handled in methods of inc/classes/Company.class.php (so they are detailed there).
*/

/* [BEGIN MARTIN COMMENT]

create table emailType(
    emailTypeId            int unsigned not null primary key,
    emailTypeName          varchar(16) not null unique,
    emailTypeDisplayName   varchar(16) not null unique);

create table locationType(
    locationTypeId            int unsigned not null primary key,
    locationTypeName          varchar(16) not null unique,
    locationTypeDisplayName   varchar(16) not null unique);

insert into emailType(emailTypeId, emailTypeName, emailTypeDisplayName) values (1,'accountsPayable','Accounts Payable');
insert into locationType(locationTypeId, locationTypeName, locationTypeDisplayName) values (1,'accountsPayable','Accounts Payable');

alter table companyLocation add locationTypeId int unsigned not null default 0;
alter table companyEmail add emailTypeId int unsigned not null default 0;

create table billingProfile(
    billingProfileId    int unsigned not null primary key auto_increment,
    companyId           int unsigned not null,
    companyPersonId     int unsigned not null default 0,
    personEmailId       int unsigned not null default 0,
    companyEmailId      int unsigned not null default 0,
    personLocationId    int unsigned not null default 0,
    companyLocationId   int unsigned not null default 0,
    multiplier          numeric(5,2),
    dispatch            tinyint unsigned,
    termsId             smallint unsigned,
    contractLanguageId  smallint unsigned,
    gracePeriod         smallint unsigned,
    inserted            timestamp not null default now()),
    active              tinyint unsigned; // active introduced for version 2020-2 JM


create index ix_billprofile_cid on billingProfile(companyId);
create index ix_billprofile_cpid on billingProfile(companyPersonId);
create index ix_billprofile_peid on billingProfile(personEmailId);
create index ix_billprofile_ceid on billingProfile(companyEmailId);
create index ix_billprofile_plid on billingProfile(personLocationId);
create index ix_billprofile_clid on billingProfile(companyLocationId);

[END MARTIN COMMENT]

    Quite a bit reworked JM 2019-12, generally without leaving the old code inline.

*/

require_once './inc/config.php';
require_once './inc/access.php';
require_once './inc/perms.php';

// ADDED by George 2020-05-15, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);

$error = '';
$errorId = 0;
$error_is_db = false;
$companyName = '';
$companyId = 0;
// George Added 2020-05-15. Catch errors server-side validation.
$errorPh = '';
$errorEm = '';
$errorUpdateEm = '';
$errorUpdatePh = '';
// End Add.

$v = new Validator2($_REQUEST);

$v->stopOnFirstFail();
$v->rule('required', 'companyId');
$v->rule('integer', 'companyId');
$v->rule('min', 'companyId', 1);

if( !$v->validate() ) {
    $errorId = '637251334173648362';
    $logger->error2($errorId, "CompanyId : " . $_REQUEST['companyId'] ."  is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = "Invalid CompanyId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$companyId = intval($_REQUEST['companyId']); // 2020-05-15 George. The companyId is already checked before, in the validator

if (!Company::validate($companyId)) {
    $errorId = '637188438766423561';
    $logger->error2( $errorId, "The provided companyId ". $companyId ." does not correspond to an existing DB company row in company table");
    $_SESSION["error_message"] = "Invalid CompanyId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$company = new Company($companyId); // should always succeed, since we've already validated the ID.
$crumbs = new Crumbs($company, $user);

// BEGIN ACTIONS

// To understand these, see inc/classes/company.class.php and/or the self-submitting forms created lower in this file.
// All of these except 'updatecompany' continue to the usual company.php page code on completion.

if ($act == 'updatecompany') {
    $v->rule('required', 'companyName');
    $v->rule('url', 'companyURL'); // if present it should be validated as a URL format
    $v->rule('lengthMax', ['companyLicense', 'companyTaxId'], 32); // maxlengthy 32 characters.
    $v->rule('lengthMax', 'companyNickname', 128); // maxlengthy 128 characters.

    if (!$v->validate()) {
        $errorId = '637188453039890623';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        $request_company = array();

        $request_company['companyName'] = trim($_REQUEST['companyName']);
        $request_company['companyNickname'] = isset($_REQUEST['companyNickname']) ? $_REQUEST['companyNickname'] : '';
        $request_company['companyURL'] = isset($_REQUEST['companyURL']) ? $_REQUEST['companyURL'] : '';
        $request_company['companyLicense'] = isset($_REQUEST['companyLicense']) ? $_REQUEST['companyLicense'] : '';
        $request_company['companyTaxId'] = isset($_REQUEST['companyTaxId']) ? $_REQUEST['companyTaxId'] : '';
        // $request_company['primaryBillingProfileId'] = $_REQUEST['primaryBillingProfileId']; // REMOVED 2020-03-16 JM: doesn't appear in companyform, so this can't be here.
                                                                           // I would think this was a guaranteed error, did anyone test this?
                                                                           // AND INDEED IT WAS A GUARANTEED ERROR: IT PUT AN INVALID
                                                                           // primaryBillingProfileId = 0 (violation of referential integrity) in the DB
                                                                           // row for every company it was called on. I've now fixed the dev2 DB.
        // George 2020-05-21. Changed in Company class methods: update() and save() to return a boolean.
        if ($company->update($request_company)) {
            // success
            header("Location: " . $company->buildLink());
            die();
        } else {
            $errorId = '637233338955533952';
            $error = 'Update Company data <b>' . $request_company['companyName'] .  '</b> not possible! Please check Input.'; // George 2020-05-15. Change error message.
            $logger->error2($errorId, $error); // REMARK George 2020-05-15 - the reason of the error is already logged in update method.
                                               // This is just a ancillary message which concludes the update action on page
        }
        unset($request_company); // don't let these get out of this scope
    }

}

//  Add Phone
else if ($act == 'addphone') {
    $request_phone = array(); // Build an array with the relevant content rather than pass $_REQUEST directly to a class method.

    list($errorPh, $phoneNumber) =
        SSSEng::validatePhoneNumber($v, $_REQUEST['phoneNumber'], true, "company->AddPhone", "company");

    if (!$errorPh) {
        $request_phone['phoneNumber'] = $phoneNumber;
        $request_phone['phoneTypeId'] =  $_REQUEST['phoneTypeId'];
        // George 2020-04-28. Changed in Company::addPhone() to return a boolean.
        if ($company->addPhone($request_phone)) {
            // success
            header("Location: " . $company->buildLink());
            die();
        } else {
            $errorId = '637251515262001946';
            $error = 'Add Phone Number <b>' . $phoneNumber .  '</b> not possible! => Add Phone Number <b>' . $phoneNumber .  '</b> failed!';
            $logger->error2($errorId, $error); // Changed 2020-05-15 George - the reason of the error is already logged in addPhone method.
                                               // This is just a ancillary message which concludes the addPhone action on page
        }
        unset($phoneNumber);
    }
    unset($request_phone); // don't let these get out of this scope
}

// Update Phone
else if ($act == 'updatephone') {
    $update_phone = array(); // Build an array with the relevant content rather than pass $_REQUEST directly to a class method.

    list($errorUpdatePh, $phoneNumber) =
        SSSEng::validatePhoneNumber($v,
        isset($_REQUEST['phoneNumber']) ? $_REQUEST['phoneNumber'] : '', //it's not required.
        false, "company->UpdatePhone", "company");

    if (!$errorUpdatePh) {
        $update_phone['phoneNumber']    = $phoneNumber;
        $update_phone['phoneTypeId']    = $_REQUEST['phoneTypeId'];
        $update_phone['companyPhoneId'] = $_REQUEST['companyPhoneId']; //make sure companyPhoneId matches this company is done in Company::updatePhone()
        $update_phone['ext1'] =
            (isset($_REQUEST['ext1']) && is_numeric($_REQUEST['ext1'])) ?
             $_REQUEST['ext1'] : '';

        // George IMPROVED 2020-04-28. Changed in Company::updatePhone() to return a boolean.
        if ($company->updatePhone($update_phone)) { // If we update phoneNumber with blank entry, the entry will be deleted. Handled by method in the class.
            // success
            header("Location: " . $company->buildLink());
            die();
        } else {
            $errorId = '637236682753184251';
            $error = 'Update Phone Number <b>' . $phoneNumber .  '</b> not possible! => Update Phone Number <b>' . $phoneNumber .  '</b> failed!';
            $logger->error2($errorId, $error); // 2020-05-18 George - the reason of the error is already logged in updatePhone method.
                                            // This is just a ancillary message which concludes the updatePhone action on page
        }
        unset($phoneNumber);
    }
    unset($update_phone);
}

//  Add Email
else if ($act == 'addemail') {
    $v->rule('required', 'emailAddress');
    $v->rule('emailTrim', 'emailAddress');

    if (!$v->validate()) {
        $errorId = '637254121407233090';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        // George ADDED 2020-05-11. Add error to errorEm.
        $errorEm = json_encode($v->errors());
        $errorEm = json_decode($errorEm, true);
    } else {
        $request_email = trim($_REQUEST['emailAddress']);
        // George 2020-05-13. Company::addEmail() returns a boolean.
        if ($company->addEmail($request_email)) {
            // success
            header("Location: " . $company->buildLink());
            die();
        } else {
            $errorId = '637254121298317913';
            $error = 'Add Email <b>' . $request_email .  '</b> not possible! Please check Input.'; // changed the error message
            $logger->error2($errorId, $error); // 2020-05-18 George - the reason of the error is already logged in addEmail method.
                                                //This is just a ancillary message which concludes the addEmail action on page
        }
    }
    unset($request_email);
}
// Update Email
else if ($act == 'updateemail') {
    $update_email = array(); // Build an array with the relevant content rather than pass $_REQUEST directly to a class method.
    $emailTypes = array("0"); //Build an array for valid emailTypeIds. Add value 0 for : normal (blank selection).
    foreach($company->getEmailTypes() as $key=>$value){ //get emailTypeIds from DB.
        $emailTypes[] = $value["emailTypeId"];
    }

    // 2020-05-18 George. ADD some extra validation
    $v->rule('required', 'companyEmailId');
    $v->rule('numeric', 'companyEmailId');
    $v->rule('in', 'emailTypeId', $emailTypes); //emailTypeId value must be in array.

    if(isset($_REQUEST['emailAddress']) && trim($_REQUEST['emailAddress']) != "") {
        $v->rule('emailTrim', 'emailAddress');
    }

    if (!$v->validate()) {
        $errorId = '637217883050065292';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        // George ADDED 2020-05-18. Add error to errorUpdateEm
        $errorUpdateEm = json_encode($v->errors());
        $errorUpdateEm = json_decode($errorUpdateEm, true);
    } else {
        $update_email['emailAddress'] = isset($_REQUEST['emailAddress']) ? trim($_REQUEST['emailAddress']) : ''; //it's not required.
        $update_email['companyEmailId'] = $_REQUEST['companyEmailId']; //make sure companyEmailId matches this company is done in Company::updatePhone()
        $update_email['emailTypeId']    = $_REQUEST['emailTypeId'];

        // George IMPROVED 2020-05-18. Company::updateEmail() returns a boolean.
        $success = $company->updateEmail($update_email, $integrityIssue);

        if (!$success) { //If we update emailAddress with blank entry, the entry will be deleted. Handled by method in the class.
            $errorId = '637236714457435824';
            $error = 'Update Email <b>' . $update_email['emailAddress'] .  '</b> not possible! Please check Input.'; //  changed the error message
            $logger->error2($errorId, $error); // 2020-05-18  George - the reason of the error is already logged in updateEmail method.
                                                //This is just a ancillary message which concludes the updateEmail action on page
        } else if (!$error && $integrityIssue == true) { //no query failed but DB Integrity issue for Delete.
            $errorId = '637334422252911913';
            $error = 'Email still in use, delete not possible.'; // DB Integrity issue message.
            $logger->error2($errorId, $error. " company => updateEmail. At least one reference to this row exists in the database, violation of database integrity.");
        } else {
            // success
            header("Location: " . $company->buildLink());
            die();
        }

        // END IMPROVEMENT
    }
    unset($update_email, $emailTypes);
}

else if ($act == 'updatelocationtype') {
    $update_location = array();// Build an array with the relevant content rather than pass $_REQUEST directly to a class method.
    $locationTypes = array("0"); //Build an array for valid locationTypeIds. Add value 0 for : normal (blank selection).
    foreach($company->getLocationTypes() as $key=>$value){ //get locationTypeId from DB.
        $locationTypes[] = $value["locationTypeId"];
    }
    // 2020-05-19 George. ADD some extra validation
    $v->rule('required', ['locationTypeId', 'companyLocationId']);
    $v->rule('in', 'locationTypeId',  $locationTypes); //locationTypeId value must be in array.

    if (!$v->validate()) {
        $errorId = '637221113848232776';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        $update_location['locationTypeId']= $_REQUEST['locationTypeId'];
        $update_location['companyLocationId'] = $_REQUEST['companyLocationId'];

        // George IMPROVED 2020-05-19. Company::updateLocationType($update_location) returns a boolean.
        if ($company->updateLocationType($update_location)) {
            // success
            header("Location: " . $company->buildLink());
            die();
        } else {
            $errorId = '637236721342532001';
            $error = 'Update Location type not possible! Please check.'; //  changed the error message
            $logger->error2($errorId, $error); // 2020-05-19  George - the reason of the error is already logged in updateLocationType method.
                                                //This is just a ancillary message which concludes the updateLocationType action on page.
        }
    }
    unset($update_location, $locationTypes);
}
// END ACTIONS

include_once BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title = 'Company: ". str_replace("'", "\'", $company->getCompanyName()) . "';\n</script>";

if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<style>
/* George ADDED 2020-04-28 */
h2.heading { font-weight: 500;}
#col1 { width: 38%; }
#col2 { width: 62%; left:65%; }
#container1 { background:#fff;}
#container2 { background:#fff;}
.icon_img {width:45px; height:45px;}
.table-sm td, .table-sm th{vertical-align: middle;}
.table-sm{border: none;}
#iframe {width: 100%;}
.error{
    color:red;
}
/* End ADD */
td.revenue-metric, tr.wots-container {
    display:none;
}
body.show-revenue-metric td.revenue-metric {
    display:table-cell;
}
body.show-revenue-metric tr.wots-container {
    display:table-row;
}
.arCenter td {
    text-align:center!important;
}
#copyLink {
    color: #000;
    font-family: Roboto,"Helvetica Neue",sans-serif;
    font-size: 12px;
    font-weight: 600;
}

#copyLink:hover {
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}
#firstLinkToCopy {
    font-size: 18px;
    font-weight: 700;
}
</style>
<script>
// Copy link on clipboard.
function copyToClip(str) {
    function listener(e) {
        e.clipboardData.setData("text/html", str);
        e.clipboardData.setData("text/plain", str);
        e.preventDefault();
    }
    document.addEventListener("copy", listener);
    document.execCommand("copy");
    document.removeEventListener("copy", listener);
};
$(document).ready(function() {
    // Change text Button after Copy.
    $('#copyLink').on("click", function (e) {
        $(this).text('Copied');
    });
    $("#copyLink").tooltip({
        content: function () {
            return "Copy WO Link";
        },
        position: {
            my: "center bottom",
            at: "center top"
        }
    });
});

</script>
<div id="container" class="clearfix">
    <?php
        $companyToCopy = "";
        $companyNickNameToCopy = $company->getCompanyNickname();
        $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/company/' . rawurlencode($companyId);
        $companyToCopy = $company->getCompanyName(); 

        if(  trim($companyToCopy) == "" ) {
            $companyToCopy = ucfirst($companyNickNameToCopy) ;
        }

        if( $companyToCopy == "" && trim($companyNickNameToCopy) == "" ) {
            $companyToCopy = "Company (" . $company->getCompanyId() .")";
        }
    ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            (C)&nbsp;<a href="<?php echo $company->buildLink(); ?>"><?php echo $companyToCopy ?></a>
       
            <button id="copyLink" title="Copy CO link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>    
        <span id="linkToCopy" style="display:none"> (C)<a href="<?php echo $company->buildLink(); ?>">&nbsp;<?php echo $companyToCopy ?>&nbsp;</span>

        <span id="linkToCopy2" style="display:none"> (C)<a style="display:none" href="<?= $urlToCopy;?>">&nbsp;  <?php echo $companyToCopy ?></a></span>
    </div>
    <div class="clearfix"></div>


    <div class="main-content">
        <h1><?php
        // display company name, as a header.
        echo htmlspecialchars($company->getCompanyName());

        // If the user has admin-level permissions for payments, then a link is added to
        //  /statement.php?companyId=CompanyId; the link is labeled as "Statement".

        $checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
        if ($checkPerm) {
            echo '&nbsp;(<a id="linkStatement" href="/statement.php?companyId=' . intval($company->getCompanyId()) . '">Statement</a>)';
        }
        ?></h1>

        <div class="container-fluid">
            <div class="row col-md-12">
                <div class="col-md-5" style="padding-top:50px;">
                    <br />
                    <?php /* Form to view & update some data from the company table. Self-submitting, and pretty self-explanatory. */ ?>
                    <?php /* BEGIN REPLACED 2020-03-16 JM Changing ill-chose form name
                    <form name="person" id="companyform" method="POST" action="">
                    // END REPLACED 2020-03-16 JM
                    // BEGIN REPLACEMENT 2020-03-16 JM
                    */ ?>
                    <form name="companyform" id="companyform" method="POST" action="">
                    <? /* END REPLACEMENT 2020-03-16 JM */ ?>
                        <input type="hidden" name="act" value="updatecompany" />
                        <input type="hidden" name="companyId" value="<?php echo intval($company->getCompanyId()); ?>" />
                        <table class="table table-bordered table-striped text-left table-responsive table-sm" style="width: 100%;"><tbody>
                            <tr>
                                <td>Name</td>
                                <td width="100%"><input type="text" id="companyName" class="form-control input-sm" name="companyName"
                                    value="<?php echo htmlspecialchars($company->getCompanyName()); ?>" maxlength="128"  /></td>
                            </tr>
                            <tr>
                                <td>Nickname</td>
                                <td><input type="text" class="form-control input-sm" name="companyNickname" id="companyNickname"
                                    value="<?php echo htmlspecialchars($company->getCompanyNickname()); ?>" maxlength="128"  /></td>
                            </tr>
                            <tr>
                                <td>URL</td>
                                <td><input type="text" class="form-control input-sm" name="companyURL" id="companyURL"
                                    value="<?php echo htmlspecialchars($company->getCompanyURL()); ?>" maxlength="255"  /></td>
                            </tr>
                            <tr>
                                <td>License</td>
                                <td><input type="text" class="form-control input-sm" name="companyLicense" id="companyLicense"
                                    value="<?php echo htmlspecialchars($company->getCompanyLicense()); ?>" maxlength="32"  /></td>
                            </tr>
                            <tr>
                                <td>Tax&nbsp;ID</td>
                                <td><input type="text" class="form-control input-sm" name="companyTaxId" id="companyTaxId"
                                    value="<?php echo htmlspecialchars($company->getCompanyTaxId()); ?>" maxlength="32"  /></td>
                            </tr>
                            <tr><td colspan="2"><input type="submit" id="updateCompany" class="btn btn-secondary btn-sm mr-auto ml-auto mt-1" value="Update" />
                        </tbody></table>
                    </form>

                    <?php
                    ////////////////////////////
                    // BEGIN NOTES
                    ////////////////////////////
                    ?>
                    <div class="siteform">
                        <?php /* >>>00012 in next line: we should probably have a distinct icon_company_notes, even if it is just a symlink */?>
                        <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_notes.png" class="icon_img"/>
                        <br/>
                        Recent Notes<br/>
                        <iframe id="iframe" class="embed-responsive" src="/iframe/recentnotes.php?companyId=<?php echo $company->getCompanyId(); ?>"></iframe>
                        <br/>
                        <a data-fancybox-type="iframe" class="fancyboxIframe" id="companyIframeNotes" href="/fb/notes.php?companyId=<?php echo $company->getCompanyId(); ?>">See All Notes</a>
                        <br/ ><br/ >

                        <?php /* Martin comment here said <!-- Column two end -->, but that appears to be wrong, he was off by a level. */ ?>
                     </div> <!-- end notes-->
                    <?php
                    ////////////////////////////
                    // END NOTES
                    ////////////////////////////
                    ?>
                </div> <!-- end col-md-5  Div Contains companyform and Notes -->
                <div class="col-md-1"> </div> <!-- George : DIV used only for additional space -->
                <div class="col-md-6">
                    <?php
                    ////////////////////////////
                    // PHONE [Martin comment]
                    ////////////////////////////
                    $extensions = $user->getExtensions();

                    $phoneTypes = Company::getPhoneTypes($error_is_db);
                    $errorPhoneTypes = '';
                    if($error_is_db) { //true on query failed.
                        $errorId = '637418200302501788';
                        $errorPhoneTypes = "We could not display the Phone Types. Database Error."; // message for User
                        $logger->errorDB($errorId, "Company::getPhoneTypes() method failed.", $db);
                    }
                    if ($errorPhoneTypes) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorPhoneTypes</div>";
                    }
                    unset($errorPhoneTypes);

                    $phones = $company->getPhones($error_is_db);
                    $errorPhones = '';
                    if($error_is_db) { //true on query failed.
                        $errorId = '637418202473757377';
                        $errorPhones = "We could not display the Phone Numbers for this company. Database Error."; // message for User
                        $logger->errorDB($errorId, "Company::getPhones() method failed", $db);
                    }
                    if ($errorPhones) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorPhones</div>";
                    }
                    unset($errorPhones);
                    ?>
                    <br /><br />
                    <div class="form-group row">
                    <?php /* NOTE that in the following there are two different notions of extensions:
                             ext1 & the DB table Extension are handling two differnt things. */ ?>
                    <?php /* person phone icon (apparently there no distinct company phone icon) */ ?>
                    <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_phone.png" class="icon_img"/>
                    <table class="table table-bordered table-striped text-left table-responsive table-sm mt-3" style="width:100%;" >
                        <?php
                        echo '<thead><tr>' . "\n";
                            echo '<th>&nbsp;</th>' . "\n";
                            echo '<th>Number</th>' . "\n";
                            echo '<th>Ext</th>' . "\n";

                            echo '<th>Type</th>' . "\n";
                            echo '<th>&nbsp;</th>' . "\n";

                            if (count($extensions)) {
                                echo '<th style="text-align: center">Patch</th>' . "\n";
                            } else {
                                echo '<th>&nbsp;</th>' . "\n";
                            }
                            echo '<th>SMS</th>' . "\n";
                        echo '</tr></thead>' . "\n";

                        echo '<tbody>' . "\n";
                        // George Added 2020-05-18. Display Error Message update Phone!
                        if ($errorUpdatePh) {
                            foreach ($errorUpdatePh as $key => $value) {
                                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                            }
                        }
                        //End Add
                        foreach ($phones as $pkey => $phone) {
                            // NOTE that there is no officially clean way to mix rows and forms, but all known browsers should cope just fine.
                        echo '<tr>' . "\n";
                            echo '<form name="updatephone" id="updatephone' . $phone['companyPhoneId'] . '" method="post" action="">' . "\n";

                                echo '<td>' . "\n"; // column with no header, 1-based index of phone.
                                echo '<input type="hidden" name="act" value="updatephone" />' . "\n";
                                echo '<input type="hidden" name="companyPhoneId" value="' . htmlspecialchars($phone['companyPhoneId']) . '" />' . "\n";
                                    echo ($pkey + 1) . ')';
                                echo '</td>' . "\n";

                                // "Number"
                                echo '<td><input type="text"  class="form-control input-sm updatePhoneNumber noTooltip" name="phoneNumber" id="phoneNumber' . $phone['companyPhoneId'] . '" value="' . htmlspecialchars($phone['phoneNumber']) . '" size="15" maxlength="64" /></td>' . "\n";

                                // "Ext"
                                echo '<td><input type="text"  class="form-control input-sm noTooltip" name="ext1" id="ext1' . $phone['companyPhoneId'] . '" value="' . htmlspecialchars($phone['ext1']) . '" size="3" maxlength="5" /></td>' . "\n";

                                // "Type", this is a dropdown, no "empty" value
                                echo '<td>' . "\n";
                                    echo '<select class="form-control input-sm noTooltip" style="width:90px;" name="phoneTypeId"  id="phoneTypeId' . $phone['companyPhoneId'] . '" >' . "\n";
                                    foreach ($phoneTypes as $phoneType) {
                                        $selected = ($phoneType['phoneTypeId'] == $phone['phoneTypeId']) ? ' selected' : '';
                                        echo '<option value="' . $phoneType['phoneTypeId'] . '" ' . $selected . '>' . $phoneType['typeName'] . '</option>' . "\n";
                                    }
                                    echo '</select>' . "\n";
                                echo '</td>' . "\n";

                                // "Submit" button for this row
                                echo '<td><input type="submit" class="btn btn-secondary btn-sm mr-auto ml-auto updatePh" id="updatePh' . $phone['companyPhoneId'] . '" value="Update" /></td>' . "\n";

                                // If there are extensions, then this column is headed "Patch"
                                // and is a nested table of extensions, each with a button to place a call.
                                // Logic here slightly cleaned up by JM 2020-02-17
                                echo '<td>' . "\n";
                                    if (count($extensions)) {
                                        echo '<table border="0" cellpadding="2" cellspacing="1">' . "\n";
                                        echo '<tr>' . "\n";
                                        foreach ($extensions as $extension) {

                                            // BEGIN MARTIN COMMENT
                                            //	[extensionType] => softmobile
                                            //	[extensionTypeDisplay] => Soft Mobile
                                            //	[displayOrder] => 1
                                            //	[extension] => 801
                                            //	[description] => Cellphone
                                            // END MARTIN COMMENT

                                            echo '<td><a class="async-phoneextension"  tx="Ext : ' . $extension['extension'] .
                                                 '<p>Type : ' . $extension['extensionTypeDisplay'] .
                                                 '<p>Desc : ' . $extension['description'] . '" id="phoneExtension' . $phone['companyPhoneId'] . '" href="javascript:placeCall(\'' . $extension['extension'] . '\',\'' . htmlspecialchars($phone['phoneNumber']) . '\')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_phone_' . $extension['extensionType'] . '.png" width="28" height="28"></a></td>' . "\n";
                                        }
                                        // BEGIN commented out by Martin before 2019
                                        //echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $person->getPersonId() . '&personPhoneId=' . rawurlencode($phone['personPhoneId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                        // END commented out by Martin before 2019

                                        echo '</tr>' . "\n";
                                        echo '</table>' . "\n";
                                    } else {
                                        echo '&nbsp;';
                                    }
                                echo '</td>' . "\n";
                                // BEGIN commented out by Martin before 2019
                                //echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $person->getPersonId() . '&personPhoneId=' . rawurlencode($phone['personPhoneId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                // END commented out by Martin before 2019
                                echo '<td>&nbsp;</td>' . "\n";
                            echo '</form>' . "\n";
                        echo '</tr>' . "\n";

                        }
                        // ADD PHONE. Continues same table.
                        // NOTE that there is no officially clean way to mix rows and forms, but all known browsers should cope just fine.
                        echo '<tr>';   // George 2020-05-15 moved form inside <tr>
                        // George Added 2020-05-15. Display Error Message add Phone!
                        if ($errorPh) {
                            foreach ($errorPh as $key => $value) {
                                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                            }
                        }
                        // End Add
                            echo '<form name="addphone" id="addphone" method="post" action="">';
                                echo '<input type="hidden" name="act" value="addphone" />';
                                echo '<td>&nbsp;</td>'; // no index
                                // "Number"
                                echo '<td><input type="text" class="form-control input-sm" name="phoneNumber" id="addphoneNumber" value="" size="15" maxlength="64" required ></td>';
                                // phoneType, this is a dropdown, no "empty" value
                                echo '<td>';
                                    echo '<select class="form-control input-sm" name="phoneTypeId" id="phoneTypeId" >';
                                    foreach ($phoneTypes as $phoneType) {
                                        echo '<option value="' . $phoneType['phoneTypeId'] . '">' . $phoneType['typeName'] . '</option>';
                                    }
                                    echo '</select>';
                                echo '</td>';
                                echo '<td><input type="submit" id="addPh" class="btn btn-secondary btn-sm mr-auto ml-auto" value="Add"></td>';
                            echo '</form>';
                        echo '</tr>';

                        ?>
                    </tbody>
                    </table>

                    <?php
                    ////////////////////////////
                    // END PHONE [Martin comment]
                    ////////////////////////////
                    ?>
                    <br /><br />
                    <br /><br />
                     <?php
                    ////////////////////////////
                    // EMAIL [Martin comment]
                    ////////////////////////////
                    $emailTypes = $company->getEmailTypes($error_is_db);
                    $errorEmailTypes = '';
                    if($error_is_db) { //true on query failed.
                        $errorId = '637418213012373974';
                        $errorEmailTypes = "We could not display the Email Types. Database Error."; // message for User
                        $logger->errorDB($errorId, "Company::getEmailTypes() method failed", $db);
                    }

                    if ($errorEmailTypes) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorEmailTypes</div>";
                    }
                    unset($errorEmailTypes);

                    $emails = $company->getEmails($error_is_db);
                    $errorEmails = '';
                    if($error_is_db) { //true on query failed.
                        $errorId = '637418219419537046';
                        $errorEmails = "We could not display the Emails for this company. Database Error."; // message for User
                        $logger->errorDB($errorId, "getEmails() method failed", $db);
                    }

                    if ($errorEmails) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorEmails</div>";
                    }
                    unset($errorEmails);
                    ?>
                    <?php /* person email icon (apparently there no distinct company email icon)
                    >>>00012 we should probably have a distinct icon_company_mail, even if it is just a symlink
                    */ ?>
                    <img class="mt-5"  src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_mail.png" class="icon_img" />
                    <table class="table table-bordered table-striped text-left table-responsive table-sm mt-3" >
                        <?php
                        echo '<tr>';
                            echo '<th>&nbsp;</th>';
                            echo '<th>Email</th>';
                            //echo '<th>&nbsp;</th>';  // commented out by Martin before 2019
                            echo '<th>Email Type</th>';
                            echo '<th>&nbsp;</th>';
                            echo '<th></th>';
                        echo '</tr>' . "\n";
                    // George ADDED 2020-05-11. Display Error Message  Update Email.
                    if ($errorUpdateEm) {
                        foreach ($errorUpdateEm as $key=>$value) {
                            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                        }
                    }
                    // End ADD
                    foreach ($emails as $ekey => $email) {
                        echo '<!-- ' . print_r($email, true) . ' -->' . "\n";
                        // NOTE that there is no officially clean way to mix rows and forms, but all known browsers should cope just fine.
                        echo '<tr>' . "\n";
                        echo '<form name="updateemail" method="post" action="" id="updateEmailForm' . $email['companyEmailId'] . '">' . "\n";
                            // column with no header, 1-based index of email address.
                            echo '<td>';
                            echo '<input type="hidden" name="act" value="updateemail" />' . "\n";
                            echo '<input type="hidden" name="companyEmailId" value="' . htmlspecialchars($email['companyEmailId']) . '" />' . "\n";
                                echo ($ekey + 1) . ')';
                            echo '</td>' . "\n";
                            // "Email"
                            echo '<td><input type="text" name="emailAddress" class="form-control input-sm updateEmailAddress noTooltip" id="updateEmailAddress'. $email['companyEmailId'] .'" value="' . $email['emailAddress'] . '" size="40" maxlength="255" /></td>' . "\n";

                            // emailType (no header), this is a dropdown
                            echo '<td>' . "\n";
                            // echo '<select name="emailTypeId"><option value="0"></oprion>'; // "empty" value // oprion" obvious typo FIXED 2019-11-26 JM
                            echo '<select style="width:160px;" class="form-control input-sm noTooltip" name="emailTypeId" id="emailTypeId' . $email['companyEmailId'] . '" ><option value="0"></option>' . "\n"; // "empty" value
                            foreach ($emailTypes as $emailType) {
                                $selected = ($emailType['emailTypeId'] == $email['emailTypeId']) ? ' selected' : '';
                                echo '<option value="' . $emailType['emailTypeId'] . '" ' . $selected . '>' . $emailType['emailTypeDisplayName'] . '</option>' . "\n";
                            }
                            echo '</select>' . "\n";
                            echo '</td>' . "\n";

                            // "Submit" button for this row
                            echo '<td><input type="submit" class="btn btn-secondary btn-sm mr-auto ml-auto updateEm" id="updateEm' . $email['companyEmailId'] . '" value="Update" /></td>' . "\n";

                            // BEGIN commented out by Martin before 2019
                            // echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/email.php?personId=' . $person->getPersonId() . '&personEmailId=' . $email['personEmailId'] . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_email.png" width="28" height="28"></a></td>';
                            // END commented out by Martin before 2019
                            if (strlen($email['emailAddress'])) {
                                echo '<td><a id="companyMailTo' . $email['companyEmailId'] . '" href="mailto:' . $email['emailAddress'] . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_email.png" width="28" height="28"></a></td>' . "\n";
                            } else {
                                echo '<td>&nbsp;</td>' . "\n";
                            }

                        echo '</form>' . "\n";
                        echo '</tr>' . "\n";
                    }
                        // George ADDED 2020-05-11. Display Error Message  Add Email.
                        if ($errorEm) {
                            foreach ($errorEm as $key=>$value) {
                                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                            }
                        }
                        // End ADD
                        // ADD EMAIL. Continues same table.
                        // NOTE that there is no officially clean way to mix rows and forms, but all known browsers should cope just fine.
                        echo '<tr>' . "\n";
                        echo '<form name="addemail" method="post" action="" id="addEmailForm" >' . "\n";
                            echo '<input type="hidden" name="act" value="addemail" />' . "\n";
                            echo '<td>&nbsp;</td>' . "\n"; // no index
                            echo '<td><input type="text" class="form-control input-sm" name="emailAddress" id="addEmailAddress" value="" size="40" maxlength="255" required /></td>' . "\n";

                            echo '<td><input type="submit" id="addEm" class="btn btn-secondary btn-sm mr-auto ml-auto" value="Add" /></td>' . "\n";
                            echo '<td colspan="2">&nbsp;</td>' . "\n";
                        echo '</form>' . "\n";
                        echo '</tr>' . "\n";
                        ?>
                    </table>
                    <?php
                    ////////////////////////////
                    // END EMAIL [Martin comment]
                    ////////////////////////////
                    ?>
                    <br /><br />
                    </div> <!-- end form-group row. Div compact layout ( phone and email ). -->
                </div> <!-- end col-md-6. Div Column two ( phone and email ). -->
            </div> <!-- end Row col-md-12. Div wraps all forms -->
        </div> <!-- end container-fluid -->
    </div> <!-- end main-content  -->

    <div class="full-box clearfix">
        <?php
        ////////////////////////////
        // BILLING PROFILE TEMPLATES
        ////////////////////////////
        include "company-billing-profiles-section.php";
        ?>
    <div class="full-box clearfix">
        <?php
        ////////////////////////////
        // BEGIN PERSONS
        ////////////////////////////

        $cps = $company->getCompanyPersons($error_is_db);
        $errorCompanyPersons = "";
        if($error_is_db) { //true on query failed.
            $errorId = '637418223483488465';
            $errorCompanyPersons = "We could not display the Persons associated to this Company. Database Error."; // message for User
            $logger->errorDB($errorId, "Company::getCompanyPersons() method failed", $db);
        }

        if ($errorCompanyPersons) {
            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorCompanyPersons</div>";
        }
        unset($errorCompanyPersons);
        ?>
        <h2 class="heading">Persons</h2>
        <?php
        if ( ! $company->getIsBracketCompany() ) { // JM 2020-06-05, per http://bt.dev2.ssseng.com/view.php?id=164: Don't allow adding or removing a person from a bracket company
        ?>
            <a data-fancybox-type="iframe" id="addPersonCompany" class="button add show_hide fancyboxIframe"  href="/fb/addpersoncompany.php?companyId=<?php echo $company->getCompanyId(); ?>">Add</a>
        <?php
        }
        ?>
        <table class="arCenter table table-bordered table-striped">
            <tr>
                <th class="async-company-person-header">C_P</th>
                <th>Person</th>
                <th>Company</th>
                <th>Arb Title</th>
                <th>&nbsp;</th>
            </tr>
        <?php
            $counter = 0;
            foreach ($cps as $cp) {
                $p = $cp->getPerson();
                //$c = $cp->getCompany(); // [CP] >>>>0000 variable $c not used below. Maybe a good idea to remove this line
                if ($p->getPersonId() != 1) {  // [Martin comment] old 'AAA' person
                                                // [CP] >>>>0000 in case the personId does not exists in the table, it returns 0 and will fill the page with junk data
                    $bg = (++$counter%2) ? ' bgcolor="#cccccc" ' : ''; // alternate color, keeping it the same for multiple companies for a person
                    // BEGIN commented out by Martin before 2019
                    //echo '<tr ' . $bg . '>';
                    //echo '<td><a href="' . $p->buildLink() . '">' . $p->getFormattedName()  . '</a></td>';
                    //echo '</tr>';
                    // END commented out by Martin before 2019

                    $cpbyperson = $p->getCompanyPersons(); // companyPersons for person

                    foreach ($cpbyperson as $cpbpkey => $cpbp) {
                        echo '<tr ' . $bg . '>' . "\n";
                            // "Edit" icon in column w/ no header, links to a page to edit this companyPerson.
                            echo '<td align="center" width="20"><a id="linkCompanyPerson' . $cpbp->getCompanyPersonId() . '" href="' . $cpbp->buildLink() . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>' . "\n";

                            // "Person"
                            if ($cpbpkey) {
                                // So we put the person's name only on the first line for this person
                                echo '<td>&nbsp;</td>' . "\n";
                            } else {
                                // Link to person page displays ">>edit<<"
                                $disp = $p->getFormattedName();
                                if (!strlen($disp)) {
                                    $disp = '>>edit<<';
                                }
                                echo '<td><a id="linkCpPerson' . $cpbp->getCompanyPersonId() . '" href="' . $p->buildLink() . '">' . $disp  . '</a></td>' . "\n";
                            }
                            // "Company"
                            // link to company page (for that company) displays company name
                            echo '<td><a id="linkCpCompany' . $cpbp->getCompanyPersonId() . '" href="' . $cpbp->getCompany()->buildLink() . '">' . $cpbp->getCompany()->getCompanyName() . '</a></td>' . "\n";

                            // "Arb Title"
                            echo '<td>' . $cpbp->getArbitraryTitle() . '</td>' . "\n";

                            // no header. Click to show/hide in jobtable below to show jobs that match this companyPerson.
                            echo '<td>[<a id="linkCpJobs' . $cpbp->getCompanyPersonId() . '" href="javascript:lightUp(' . intval($cpbp->getCompanyPersonId()) . ');">jobs</a>]</td>' . "\n";
                        echo '</tr>' . "\n";
                    }
                }
            }

        echo '</table>' . "\n";

        ////////////////////////////
        // END PERSONS
        ////////////////////////////
        ?>
    </div>
    <div class="full-box clearfix">
        <?php
        ////////////////////////////
        // BEGIN JOBS
        ////////////////////////////
        ?>
        <h2 class="heading">Jobs</h2>

        <?php
            // >>>00006 could move function out of the middle of a bunch of HTML,
            //  or even make this a method of the Validate class.
            // And a lot of the PHP code following could also be moved entirely out of any DIV
            function validateDate($date) {
                $d = DateTime::createFromFormat('Y-m-d H:i:s', $date);
                return $d && $d->format('Y-m-d H:i:s') === $date;
            }

            $jobs = $company->getJobs($error_is_db);
            $errorJobs = "";
            if($error_is_db) { //true on query failed.
                $errorId = '637418341831430275';
                $errorJobs = "We could not display the Jobs associated to this Company. Database Error."; // message for User
                $logger->errorDB($errorId, "Company::getJobs() method failed", $db);
            }

            if ($errorJobs) {
                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorJobs</div>";
            }
            unset($errorJobs);

            $arranged = array();
            foreach ($jobs as $job) {
                $arranged[$job['jobId']]['ids'][$job['companyPersonId']] = $job['companyPersonId'];
                $arranged[$job['jobId']]['workorders'][$job['workOrderId']] = $job;
            }

            // We now have:
            //  For each $arranged[jobId]
            //     * ['ids'][companyPersonId] = companyPersonId    useful only in terms of whether companyPersonId
            //                                                     exists or not, since value just repeats companyPersonId
            //     * ['workorders'][workOrderId] = $job            again, useful only in terms of whether workOrderId
            //                                                     exists or not, since value just repeats the job itself.
            //
            // So it looks like this is a roundabout (and inconsistent) way of indicating set membership.

            $bg = " bgcolor=\"#dddddd\" ";
            ?>
            <table  class="arCenter table table-bordered table-striped" id="jobtable">
                <tr>
                    <th>Job#</th>
                    <th>Job Name</th>
                    <th>Work Orders</th>
                    <th>Earliest Delivery</th>
                    <th>Latest Delivery</th>
                </tr>
                <tbody>
            <?php

                $breaker = 0;
                foreach ($arranged as $jobId => $job) {
                    $bg = ($breaker%2) ? ' bgcolor="#cccccc" ' : '';  // alternate color every line
                    $ids = $job['ids']; // companyPersonIds
                    $idstring = '';

                    foreach ($ids as $id) {
                        $idstring .= '-' . $id . '-';
                    }
                    // $idstring ends up looking like, for example, "-358--529--1043-"
                    // 2019-10-16 JM: Removing a "time bomb". HTML IDs should be unique, and
                    //  there is no reason to think a particular combination of companyPersons should
                    //  be unique across jobs. While it is possible that we are not currently
                    //  getting in trouble with this, if so it is only because we don't really use
                    //  this as an ID. This should be stored in a data-* attribute.
                    // Introduce HTML data attributes, get away from multiple elements having
                    //  same HTML ID, which is illegal HTML. See http://bt.dev2.ssseng.com/view.php?id=35.
                    // OLD CODE removed 2019-10-15 JM
                    //echo '<tr ' . $bg . ' id="' . $idstring . '">';
                    // BEGIN REPLACEMENT
                    echo '<tr ' . $bg . ' data-company-person-ids="' . $idstring . '">'."\n";
                    // END REPLACEMENT
                        $workorders = $job['workorders'];
                        $earliest = '3000-01-01 01:01:01';     // initialize impossibly late
                        $latest = '1970-01-01 01:01:01';       // initialize impossibly early

                        $gotEarliest = false;
                        $gotLatest = false;
                        foreach ($workorders as $workorder) {
                            if (validateDate($workorder['deliveryDate'])) {
                                $gotEarliest = true;
                                $gotLatest = true;

                                if ($workorder['deliveryDate'] < $earliest) {
                                    $earliest = $workorder['deliveryDate'];
                                }

                                if ($workorder['deliveryDate'] > $latest) {
                                    $latest = $workorder['deliveryDate'];
                                }
                            }
                        }

                        $j = new Job($jobId);

                        // "Job#"
                        echo '<td><a id="linkCompanyJobs' . $j->getJobId() . '" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>'."\n";

                        // "Job Name"
                        echo '<td>' . $j->getName() . '</td>'."\n";

                        // "Work Orders"
                        echo '<td style="text-align:center;">' . count($workorders) . '</td>'."\n";

                        // "Earliest Delivery"
                        if ($gotEarliest) {
                            echo '<td style="text-align:center;">' . date("M j, Y",strtotime($earliest)) . '</td>'."\n";
                        } else {
                            echo '<td>&nbsp;</td>'."\n";
                        }

                        // "Latest Delivery"
                        if ($gotLatest) {
                            echo '<td style="text-align:center;">' . date("M j, Y",strtotime($latest)) . '</td>'."\n";
                        } else {
                            echo '<td>&nbsp;</td>'."\n";
                        }
                    echo '</tr>'."\n";

                    // limit to 1000 entries
                    if ($breaker++ > 1000) {
                        break;
                    }
                } // END foreach $arranged
    /* [BEGIN commented out by Martin some time before 2019]
            $jobs = array();
            foreach ($jobs as $jkey => $job){

                echo '<tr>';

                    echo '<td>' . $job['inTable'] . '</td>';
                    echo '<td>' . $job['companyPersonId'] . '</td>';
                    echo '<td>' . $job['position'] . '</td>';
                    echo '<td>' . $job['description'] . '</td>';
                    echo '<td>' . $job['active'] . '</td>';
                    echo '<td>' . $job['teamId'] . '</td>';
                    echo '<td>' . $job['jobId'] . '</td>';
                    echo '<td>' . $job['personId'] . '</td>';
                    echo '<td>' . $job['firstName'] . '</td>';
                    echo '<td>' . $job['lastName'] . '</td>';
                    echo '<td>' . $job['companyName'] . '</td>';


                echo '</tr>';


            }
        [END commented out by Martin some time before 2019]
        */
                echo '</tbody>';
            echo '</table>';

        ////////////////////////////
        // END JOBS
        ////////////////////////////
        ?>
    </div>

    <?php

    ////////////////////////////
    // BEGIN LOCATIONS
    ////////////////////////////

    // location type can be changed, otherwise just a list
    $locations = $company->getLocations($error_is_db);
    $errorLocations = "";
    if ($error_is_db) { //true on query failed.
        $errorId = '637418343931428800';
        $errorLocations = "We could not display the Locations associated to this Company. Database Error."; // message for User
        $logger->errorDB($errorId, "Company::getLocations() method failed", $db);
    }

    if ($errorLocations) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorLocations</div>";
    }
    unset($errorLocations);

    $locationTypes = $company->getLocationTypes($error_is_db);
    $errorLocationTypes = "";
    if ($error_is_db) { //true on query failed.
        $errorId = '637418345279765777';
        $errorLocationTypes = "We could not display the Location Types associated to this Location. Database Error."; // message for User
        $logger->errorDB($errorId, "Company::getLocationTypes() method failed", $db);
    }

    if ($errorLocationTypes) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorLocationTypes</div>";
    }
    unset($errorLocationTypes);

    $title = (count($locations) == 1) ? 'Location' : 'Locations';

    ?>
    <div class="full-box clearfix">
        <h2 class="heading" ><?php  echo $title; ?></h2>
        <?php
            // permission check added 2019-11-26 JM
            if (checkPerm($userPermissions, 'PERM_LOCATION', PERMLEVEL_RWA)) {
                echo '<a id="addCompanyLocation' . $company->getCompanyId() . '" class="button add show_hide" href="/location.php?companyId=' . $company->getCompanyId() . '">Add</a>';
            }
        ?>
        <table>
            <tbody>
            <?php  // Table has no headers.
            foreach ($locations as $row) {
                $location = new Location($row['locationId']);

                // NOTE that there is no officially clean way to mix rows and forms, but all known browsers should cope just fine.
                echo '<tr>'."\n";
                // REPLACED 2019-12-10 JM to avoid duplicate form name
                //echo '<form name="loctype_" method="POST" action="">'."\n";
                // BEGIN REPLACEMENT 2019-12-10 JM
                echo '<form name="loctype_' . intval($row['companyLocationId']). '" id="loctype_' . intval($row['companyLocationId']). '" method="POST" action="">'."\n";
                // END REPLACEMENT 2019-12-10 JM
                    echo '<input type="hidden" name="act" value="updatelocationtype" />'."\n";
                    echo '<input type="hidden" name="companyLocationId" value="' . intval($row['companyLocationId']) . '" />'."\n";

                    // formatted address
                    // companyId added JM 2019-11-21 so location.php can know to navigate back here.
                    echo '<td><a id="linkCompanyLocation' . intval($row['companyLocationId']) . '" href="/location.php?locationId=' . $location->getLocationId() .
                        '&companyId='.$company->getCompanyId().'">' . $location->getFormattedAddress() . '</a></td>'."\n";

                    // location type dropdown
                    echo '<td>'."\n";
                    // echo '<select name="locationTypeId"><option value="0"></oprion>'; // "oprion" obvious typo FIXED 2019-11-26
                    echo '<select  class="form-control input-sm" style="width:70%;margin-top:3px" name="locationTypeId" id="locationTypeId' . intval($row['companyLocationId']) . '" ><option value="0"></option>'."\n"; // "empty" value
                    foreach ($locationTypes as $locationType) {
                        $selected = ($locationType['locationTypeId'] == $row['locationTypeId']) ? ' selected' : '';
                        echo '<option value="' . $locationType['locationTypeId'] . '" ' . $selected . '>' . $locationType['locationTypeDisplayName'] . '</option>'."\n";
                    }
                    echo '</select>'."\n";

                    echo '</td>'."\n";
                    // Submit button
                    echo '<td><input type="submit" id="updateType' . intval($row['companyLocationId']) . '" class="btn btn-secondary btn-sm ml-auto mt-1" value="Update type" border="0" />';

                    echo '</td>'."\n";
                echo '</form>'."\n";
                echo '<td>';
                // JM added remove/delete/delink capability 2019-11-26 (see also script below)
                if (checkPerm($userPermissions, 'PERM_LOCATION', PERMLEVEL_RWA)) {
                        echo '&nbsp;<button type="button" id="delink'.$location->getLocationId().'" class="delink btn btn-secondary btn-sm mr-auto ml-auto mt-1" data-locationid="'.$location->getLocationId().'">Remove location</button>';
                }
                echo '</td>'."\n";
                echo '</tr>'."\n";
            }
            ?>
            </tbody>
        </table>
    </div>
    <script> <?php /* BEGIN ADDED 2019-11-26 JM to allow delinking a location */ ?>
        $('button.delink').click(function() {
            let $this = $(this);
            $.ajax({
                url: '/ajax/delinklocation.php',
                data: {
                    locationId: $this.data('locationid'),
                    companyId: <?php echo $companyId; ?>
                },
                async:false,
                type:'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            // To avoid reloading this page, we are going to delete the row we were on.
                            // >>>00014: visually, it looks to me (JM) like a reload is happening on the DOM edit.
                            //  I have no idea why. Anyway, the end result is correct, so I haven't messed with it.
                            $this.find('tr').remove();
                            location.reload();
                        } else {
                            alert(data['error']);
                        }
                    } else {
                        alert('error: no status');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error');
                }
            });
        });
    </script> <?php /* END ADDED 2019-11-26 JM to allow delinking a location */ ?>

    <?php
    ////////////////////////////
    // END LOCATIONS
    ////////////////////////////
    ?>
</div> <!-- end container -->
</div>

<script>
    // INPUT companyPersonId: in Jobs table, show only rows where this companyPersonId applies
    // >>>00032: passing in 0 ought to show all. (Would also need a place to request that,
    //  probably at top or bottom of Jobs section.)
    var lightUp = function(companyPersonId) {
        // Hide all rows in Jobs table
        /* REPLACED 2019-12-10 JM
        // 2019-10-16: why on earth do this hide separately for even & odd?
        $("table#jobtable tbody tr:even").hide();
        $("table#jobtable tbody tr:odd").hide();
        */
        // BEGIN REPLACEMENT 2019-12-10 JM
        $("table#jobtable tbody tr").hide();
        // END REPLACEMENT 2019-12-10 JM

        // Show all rows where this is one of the companyPersons
        // 2019-10-16 JM: See note above about removing a "time bomb".
        // OLD CODE removed 2019-10-15 JM
        // $( "tr[id*='-" + companyPersonId + "-']" ).show();
        // BEGIN REPLACEMENT
        $( "tr[data-company-person-ids*='-" + companyPersonId + "-']" ).show();
        // END REPLACEMENT

        <?php /* BEGIN commented out by Martin before 2019 */ ?>
        // $( "tr[id*='-" + companyPersonId + "-']" ).css('background-color', '#ffffcc' );
        // var color = "cccccc";
        <?php /* END commented out by Martin before 2019 */ ?>

        var counter = 0;

        // "stripe" it again.
        $('table#jobtable tbody tr').each(function() {
            if ($(this).is(':visible')) {
                if ((counter % 2) == 0) {
                    color = "#cccccc";
                } else {
                    color = "#ffffff";
                }

                $(this).css("background-color", color);
                counter++;
            }
        });

        <?php /* BEGIN commented out by Martin before 2019 */ ?>
        //$('#table_row').is(':visible')
        //$("table#jobtable tbody tr:even").css("background-color", "#ffffff");
        //$("table#jobtable tbody tr:odd").css("background-color", "#ffffff");
        <?php /* END commented out by Martin before 2019 */ ?>
    }

    var jsonErrors = <?=json_encode($v->errors())?>;
    var validator = $('#companyform').validate({
        errorClass: 'text-danger',
        errorElement: "span",
        rules: {
            'companyName': {
                required: true
            }
        }
    });
    validator.showErrors(jsonErrors);

    // The moment they start typing (or pasting) in a field, remove the validator warning
    $('#companyName').on('mousedown', function() {
        $('#validator-warning').hide();
        // George : hide error-messages after input
        $('#companyName-error').hide();
        if ($('#companyName').hasClass('text-danger')) {
            $("#companyName").removeClass("text-danger");
        }
    });


    /* George ADDED 2020-04-27
    New functions for Client side validation.
    Validation with Validator failed. Added for each action a function.
    Validate phone, updatephone, email, updateemail.

    George ADDED 2020-04-27. Jquery Validation.
    New action on form name="addemail".
    Validation field id="addEmailAddress" for not empty, Validate only if we have emails format!
    */
    $('#addEm').click(function() {
        $(".error").hide();
            var hasError = false;
            var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;

            var emailaddressVal = $("#addEmailAddress").val();
            emailaddressVal =  emailaddressVal.trim(); //trim value

            if (emailaddressVal == '') {
                $("#addEmailAddress").after('<span class="error">Please enter your email address.</span>');
                hasError = true;
            } else if (!emailReg.test(emailaddressVal)) {
                $("#addEmailAddress").after('<span class="error">Enter a valid email address.</span>');
                hasError = true;
            }

            if (hasError == true) {
                return false;
            }
    });
    //End ADD

    /* George ADDED 2020-04-27. Jquery Validation.
    New action on form name="updateemail".
    Validation field class="updateEmailAddress" for not empty, Validate only if we have emails format!
    */
    $('.updateEm').click(function() {
        $(".error").hide();
        var hasError = false;
        var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;

        //target exact input to update
        var specific = $(this).closest('tr').find('input.updateEmailAddress');
        var emailaddressVal = $(this).closest('tr').find('input.updateEmailAddress').val();

        emailaddressVal =  emailaddressVal.trim(); //trim value

        if (!emailReg.test(emailaddressVal)) {
            $(specific).after('<span class="error">Enter a valid email address.</span>');
            hasError = true;
        }

        if (hasError == true) {
            return false;
        }
    });
    //End ADD

    /* George ADDED 2020-04-29. Jquery Validation.
    New action on form name="addphone".
    Validation field id="addphoneNumber" for not empty, no letters input.
    Javascript Message if we have don't have exactly 10 digits! We have a Log message in server side.
    */
    $('#addPh').click(function() {
        $(".error").hide();
        var hasError = false;

        var addPhoneNumber = $("#addphoneNumber").val();
        // Digits. Count lenght of digits characters.
        var inputPh = addPhoneNumber.match(/\d/g);
        var inputPhLen = 0;

        if(inputPh != null) {
            inputPhLen = inputPh.length;
        }

        if (addPhoneNumber == '') {
            $("#addphoneNumber").after('<span class="error">Please enter your Phone Number.</span>');
            hasError = true;
        // George IMPROVED 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
        } else if(!addPhoneNumber.match(/^[- ()0-9]*$/) ) {
            $("#addphoneNumber").after('<span class="error">Invalid characters in Phone number.</span>');
            hasError = true;
        // Warning  message if we don't have exactly 10 digits.
        } else if (inputPhLen != 10) {
            alert("Phone number must be 10 digits. Enter a valid number.");
            hasError = true;
        }

        if (hasError == true) {
            return false;
        }
    });
    // End ADD

    /* George ADDED 2020-04-27. Jquery Validation.
    New action on form name="updatephone".
    Validation field class="updatePhoneNumber" for not empty, no letters input.
    Javascript Message if we have don't have exactly 10 digits! We have a Log message in server side.
    */
    $('.updatePh').click(function() {
        $(".error").hide();
        var hasError = false;

        // target exact input to update
        var specific = $(this).closest('tr').find('input.updatePhoneNumber');
        var addPhoneNumber = $(this).closest('tr').find('input.updatePhoneNumber').val();

        /* George ADDED 2020-04-29. If Phone Number DON'T have exactly 10 digits.
        Message: Do you still want to use this Phone Number? If clicks "Ok" we bring initial value (oldPhone), to rewrite. */
        var oldPhone = $(specific).attr("value");

        // Digits. Lenght of digits characters.
        addPhoneNumber = addPhoneNumber.trim();
        var inputPh = addPhoneNumber.match(/\d/g);
        var inputPhLen = 0;

        if(inputPh != null) {
            inputPhLen = inputPh.length;
        }
        // Not digits. Lenght of non digits characters.
        var inputNotDigits = addPhoneNumber.match(/[^0-9]/g);
        var inputNotDigitsLen = 0;

        if(inputNotDigits != null) {
            inputNotDigitsLen = inputNotDigits.length;
        }

        // If no digits characters.
        if( inputNotDigitsLen > 0 && inputPhLen == 0) {
            $(specific).after('<span class="error">No digits in Phone Number.</span>');
            $(specific).val(oldPhone);
            hasError = true;
        }
        // George IMPROVED 2020-11-24. Phone number can contain only: digits, parentheses, dashes, spaces!
        else if(!addPhoneNumber.match(/^[- ()0-9]*$/) ) {
            $(specific).after('<span class="error">Invalid characters in Phone number.</span>');
            hasError = true;

        // George IMPROVED 2020-11-24. Warning message if we DON'T have exactly 10 digits.
        // If "Ok" we bring initial value. If "Cancel" we update with the new value.
        } else if (inputPhLen > 0 && inputPhLen != 10) {
            if(confirm("Phone Number "+ addPhoneNumber +" must be 10 digits. Press Ok to edit this or Cancel to keep the old one!")) {
                $(specific).val(addPhoneNumber);
                hasError = true;
            } else {
                $(specific).val(oldPhone);
            }
        }

        if (hasError == true) {
            return false;
        }
    });
    // End ADD

    $('#addphoneNumber, #addEmailAddress, .updatePhoneNumber, .updateEmailAddress').on('mousedown', function() {
        // George 2020-04-27 : hide error-messages on mousedown in input filed
        $('.error').hide();
    });

    $(document).ready(function() {
        $('a.fancybox').fancybox({
            'type': 'iframe'
        });
    });
</script>
<?php

include_once BASEDIR . '/includes/footer.php';
?>