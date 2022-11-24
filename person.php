<?php
/* person.php

    EXECUTIVE SUMMARY: Top-level page to view or edit info about a person.
    There is a RewriteRule in the .htaccess to allow this to be invoked as just "person/foo" rather than "person.php?personId=foo".

    PRIMARY INPUT: $_REQUEST['personId'] specifies person.

    Optional input $_REQUEST['act']. Possible values:
    * 'addphone'. Additional args:
        * $_REQUEST['phoneNumber'] - should be 10-digit string, North American dialing with no initial '1'.
          OK if some other characters are there, they will be stripped, so for example '(206)555-1212' means '2065551212'
        * 'phoneTypeId' - foreign key into DB table PhoneType
    * 'updatephone'. Additional args:
        * $_REQUEST['phoneNumber'] (as for 'addphone')
        * $_REQUEST['ext1'] - extension for this phone. >>>00016: no validation
        * $_REQUEST['phoneTypeId'] - key into DB table PhoneType, desired phone type
        * $_REQUEST['companyPhoneId'] - key into DB table PersonPhone, row to update.
    * 'addemail'
        * $_REQUEST['emailAddress']
    * 'updateemail'
        * $_REQUEST['emailAddress']
        * $_REQUEST['personEmailId'] - key into DB table personEmail, row to update.
    * 'updatesmsperms'
       * uses $_REQUEST['smsPerm'] as array input
    * 'updateperson' (any of the following)
       * $_REQUEST['username'] - username is tested for uniqueness, and is not used if it matches any other username.
       * $_REQUEST['permissionString'] - validated (for length, not for content) and used only if valid // not used in this file.
       * $_REQUEST['firstName']
       * $_REQUEST['middleName']
       * $_REQUEST['lastName']
    * 'updatepassword'
      * Checks $_REQUEST['newpassword'], $_REQUEST['newconfirm'] & makes sure they match;
      * Has a side effect of emailing about success or failure).

    UI layout majorly reworked JM 2019-12-03, generally without leaving the old code inline.
*/
include './inc/config.php';
include './inc/perms.php';

// ADDED by George 2020-06-09, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();
$username = '';
$firstname = '';
$lastname = '';
$personId = 0;
//Catch errors server-side validation.
$errorPh = '';
$errorEm = '';
$errorUpdateEm = '';
$errorUpdatePh = '';
$mailresult = null;

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'personId');
$v->rule('integer', 'personId');
$v->rule('min', 'personId', 1);

if( !$v->validate() ) {
    $errorId = '637188438766423560';
    $logger->error2($errorId, "PersonId : " . $_REQUEST['personId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid PersonId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$personId = $_REQUEST['personId'];
// Now we make sure that the row actually exists in DB table 'person'.
if (!Person::validate($personId)) {
    $errorId = '637273184027228989';
    $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB person row in person table"); // 2020-05-14 [CP] -
    $_SESSION["error_message"] = "Invalid PersonId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}
$person = new Person($personId); // should always succeed, since we've already validated the ID.
// BEGIN ADDED 2020-01-29 JM
// If current user has admin-level permission for contracts, OR
// if this is their own page, and they have read-level permission to see their own time summary
// Long-time users may know time summaries as "TXN".
$adminPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_ADMIN);
$timeSummaryPerm = $adminPerm ||
        ( $user->getUserId() == $person->getPersonId() &&
          checkPerm($userPermissions, 'PERM_OWN_TIME_SUMMARY', PERMLEVEL_R) );
// END ADDED 2020-01-29 JM

$crumbs = new Crumbs($person, $user);
//$loc = false; // George COMMENT OUT 2020-05-08. We don't need this.

if ($act == 'updatesmsperms') {
    // Values are bitflags defined in inc/config.php.
    $smsPerm = isset($_REQUEST['smsPerm']) ? $_REQUEST['smsPerm'] : 0;
    $validSmsPermsValues = array_keys(SMS::smsPerms()); // the keys are the values which are bitflags defined in inc/config.php.

    if (!is_array($smsPerm)) {
        $smsPerm = array();
    }

    $sum = 0;
    foreach ($smsPerm as $perm) {
        if (in_array($perm, $validSmsPermsValues)) {
            $sum += intval($perm);
        } else {
            $errorId = "637408828500732014";
            $error = "We could not update the Sms Permissions. Please check the input.";
            $logger->error2($errorId, "updateSmsPerms method failed. Bad input value: " . $perm);
            break; // bad input value. Stop.
        }
    }
    if (!$error) {
        if ($person->update(array('smsPerms' => $sum))) {
            // Success
            header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
            die();
        } else {
            $errorId = '637245485333112240';
            $error = 'updateSmsPerms method failed.';
            $logger->error2($errorId, $error); // 2020-05-14 [CP] - the reason of the error is already logged in update method.
                                               // This is just a ancillary message which concludes the update action on page
        }
    }
    unset($sum, $smsPerm, $validSmsPermsValues);
}

//  Add Phone
else if ($act == 'addphone') {
    // George ADDED 2020-04-28. Build an array with the relevant content rather than pass $_REQUEST directly to a class method.
    $request_phone = array();
    // validate the input phone type and phone number
    list($errorPh, $phoneNumber) =
        SSSEng::validatePhoneNumber($v, $_REQUEST['phoneNumber'], true, "person->AddPhone", "person");

    if (!$errorPh) {
        $request_phone['phoneNumber'] = $phoneNumber; // 2020-05-14 [CP] moved the value assigning of phoneNumber after the rule -> validation()
        $request_phone['phoneTypeId'] = $_REQUEST['phoneTypeId']; // Check for a valid phoneTypeId is done in SSSEng::validatePhoneNumber.

        //Person::addPhone returns a boolean.
        if ($person->addPhone($request_phone)) {
            // Success
            header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
            die();
        } else {
            $errorId = '637238511188207072';
            $error = 'Add Phone Number <b>' . $phoneNumber .  '</b> not possible! => Add Phone Number <b>' . $phoneNumber .  '</b> failed!';
            $logger->error2($errorId, $error); // 2020-05-14 [CP] - the reason of the error is already logged in addPhone method.
                                               // This is just a ancillary message which concludes the addPhone action on page
        }
        unset($phoneNumber);
    }
    unset($request_phone);
}

// Update phone
else if ($act == 'updatephone') {
    // George ADDED 2020-04-28. Build an array with the relevant content rather than pass $_REQUEST directly to a class method.
    $update_phone = array();

    // validate the input phone type and phone number
    list($errorUpdatePh, $phoneNumber) =
        SSSEng::validatePhoneNumber($v,
        isset($_REQUEST['phoneNumber']) ? $_REQUEST['phoneNumber'] : '', //it's not required.
        false, "person->UpdatePhone", "person");

    if (!$errorUpdatePh) {
        $update_phone['phoneNumber']   = $phoneNumber;
        $update_phone['phoneTypeId']   = $_REQUEST['phoneTypeId']; // Check for a valid phoneTypeId is done in SSSEng::validatePhoneNumber.
        $update_phone['personPhoneId'] = $_REQUEST['personPhoneId'];


        $update_phone['ext1'] =
                    (isset($_REQUEST['ext1']) && is_numeric($_REQUEST['ext1'])) ?
                    $_REQUEST['ext1'] : '';

        // George 2020-05-13. Person::updatePhone returns a boolean.
        if ($person->updatePhone($update_phone)) { //If we update phoneNumber with blank entry, the entry will be deleted. Handled by method in the class.
            // success
            header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
            die();
        } else {
            $errorId = '637238511084706519';
            $error = 'Update Phone Number <b>' . $phoneNumber .  '</b> not possible! => Update Phone Number <b>' . $phoneNumber .  '</b> failed!';
            $logger->error2($errorId, $error); // 2020-05-14 [CP] - the reason of the error is already logged in updatePhone method.
                                               //This is just a ancillary message which concludes the updatePhone action on page
        }

        unset($phoneNumber);
    }
    unset($update_phone);
}

// Add Email
else if ($act == 'addemail') {
    $v->rule('required', 'emailAddress');
    $v->rule('emailTrim', 'emailAddress');

    if (!$v->validate()) {
        $errorId = '637224620021058601';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        // George 2020-05-11. Add error to errorEm.
        $errorEm = json_encode($v->errors());
        $errorEm = json_decode($errorEm, true);
        // End ADD
    } else {
        $request_email = trim($_REQUEST['emailAddress']);
        // Changed in Person class method: Person:: addEmail() to return a boolean.
        if  ($person->addEmail($request_email)) {
            // success
            header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
            die();
        } else {
            $errorId = '637238521036949318';
            $error = 'Add Email <b>' . $request_email .  '</b> not possible! Please check Input.'; // 2020-05-14 [CP] changed the error message
            $logger->error2($errorId, $error); // 2020-05-14 [CP] - the reason of the error is already logged in addEmail method.
                                               //This is just a ancillary message which concludes the addEmail action on page
        }
        // END IMPROVEMENT
    }
    unset($request_email);
}

//  Update Email
else if ($act == 'updateemail') {
    $update_email = array(); // Build an array with the relevant content rather than pass $_REQUEST directly to a class method.
    // IMPROVED by George 2020-05-13
    // 2020-05-14 [CP] added some extra validation
    $v->rule('required','personEmailId');
    $v->rule('numeric', 'personEmailId');
    if(isset($_REQUEST['emailAddress']) && trim($_REQUEST['emailAddress']) != "") {
        $v->rule('emailTrim', 'emailAddress');
    }
    // End IMPROVEMENT

    if(!$v->validate()) {
        $errorId = '637248052018692685';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        // George ADDED 2020-05-11. Add error to errorUpdateEm.
        $errorUpdateEm = json_encode($v->errors());
        $errorUpdateEm = json_decode($errorUpdateEm, true);
        // End ADD
    } else {
        $update_email['emailAddress'] = isset($_REQUEST['emailAddress']) ? trim($_REQUEST['emailAddress']) : ''; //it's not required.
        $update_email['personEmailId']=  $_REQUEST['personEmailId'];

        // George 2020-05-14. Changed in Person class method: updateEmail() to return a boolean.
        if ($person->updateEmail($update_email, $integrity)) { //If we update emailAddress with blank entry and no violation of database integrity, the entry will be deleted. Handled by method in the class.

            // At least one reference to this row exists in the database, violation of database integrity.
            if ($integrity == true) {
                $errorId = '637334375476579349';
                $error = 'Email still in use, delete not possible.'; // DB Integrity issue message.
                $logger->error2($errorId, $error. " person => updateEmail. At least one reference to this row exists in the database, violation of database integrity.");
            } else {
                // success
                header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
                die();
            }
        } else {
            $errorId = '637238531931440481';
            $error = 'Update Email <b>' .  $update_email['emailAddress'] .  '</b> not possible! Please check Input.'; // 2020-05-14 [CP] changed the error message
            $logger->error2($errorId, $error); // 2020-05-14 [CP] - the reason of the error is already logged in updateEmail method.
                                               //This is just a ancillary message which concludes the updateEmail action on page
        }
        // End Improvement
    }
    unset($update_email);
}

else if ($act == 'updateperson') {
    $v->rule('required', 'username');
    $v->rule('email', 'username'); // >>>00032 JM 2020-03-12: no action for now, but we will be reconsidering whether this is a good rule.
                                    // I believe in the meeting 2020-03-03 Radu said RDC would be following up analysis of this. As of version 2020-2,
                                    // we are stuck with this because the registration process requires it.

    if (!$v->validate()) {
        $errorId = '637188315173523917';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        $request_person = array();
        // reworked JM 2020-03, modeled on company.php, >>>00002 but in both files it would be better to properly validate inputs.
        //  (Leave this as is for release 2020-2 unless anyone finds an active bug here.)
        // deliberately skipped $_REQUEST['personId']: should never change, and we already have $person.
        $request_person['username'] = trim($_REQUEST['username']);
        //$request_person['permissionString'] = isset($_REQUEST['permissionString']) ? $_REQUEST['permissionString'] : '';
        $request_person['firstName'] = isset($_REQUEST['firstName']) ? $_REQUEST['firstName'] : '';
        $request_person['middleName'] = isset($_REQUEST['middleName']) ? $_REQUEST['middleName'] : '';
        $request_person['lastName'] = isset($_REQUEST['lastName']) ? $_REQUEST['lastName'] : '';


        // George. Changed in Person class methods: update() and save() to return a boolean.
        if ($person->update($request_person)) {
            // success
            header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
            die();
        } else if ($person->getUsername() != $request_person['username'] ) {
            // The one failure we would expect to have (presuming no coding error) is if someone else had this username.
            // Not an error -- this is expected behavior if user tries to enter a duplicate -- but we do need to report this back to the user.
            // NOTE that as of 2020-05-19, in this case we do not apply ANY of the requested changes.
            // >>>00042 probably should talk to Ron & Damon about exactly how they'd prefer this behaves. Should we
            //  apply the other changes? Should we make and "info" entry in the log?
            $error = "Username ".$request_person['username']." already in use";
        } else  {
            $errorId = '637244578135489956';
            $error = 'Cannot update Person data for <b>' . $request_person['username'] .  '</b>. Please check Input.'; // 2020-05-14 [CP] changed the error message
                                                                                                                       // Further modified by JM 2020-07-28.
            $logger->error2($errorId, $error); // 2020-05-14 [CP] - the reason of the error is already logged in update method.
                                               // This is just a ancillary message which concludes the update action on page
        }
    }
    unset($request_person); // don't let these get out of this scope
}

else if ($act == 'updatepassword') {
    // George IMPROVEMENT 2020-05-13.
    /* 2020-05-14 [CP, reworded by Joe] >>>00002, >>>00016 In the reset password process, the initial validation of inputs and associated logging
                                    should be kept to the required minimum.
                                    The user may need to do a lot of retries until they are able to match the right password.
                                    CP wrote "A lot for all this cases could only allow us to understand how the people understood the procedure," JM doesn't understand.
                                    This is not a test for a malicious attack: a hacker knows very well to change a password.
    */
    $v->rule('required', ['newpassword', 'newconfirm']);
    $v->rule('lengthMax', ['newpassword', 'newconfirm'], MAX_PANTHER_PASSWORD_LENGTH);

    if (!$v->validate()) {
        $errorId = '637249820326831384';
        $logger->warn2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        $newpassword = trim($_REQUEST['newpassword']);
        $newconfirm = trim($_REQUEST['newconfirm']);
        //End Improvement.
        $errors_in_password = array();

        if ($newpassword != $newconfirm){
            $errors_in_password[] = 'Passwords don\'t match';
        }
        checkPassword($newpassword, $errors_in_password);
        if (!count($errors_in_password)) {

            $secure = new SecureHash();
            $salt = '';
            $encrypted = $secure->create_hash($newpassword, $salt); // will create its own salt. NOTE that this will modify $salt.

            $query = " UPDATE " . DB__NEW_DATABASE . ".person SET ";
            $query .= " pass = '" . $db->real_escape_string($encrypted) . "' ";
            $query .= " ,salt = '" . $db->real_escape_string($salt) . "' ";
            $query .= " WHERE personId = " . intval($person->getPersonId()) . " ";

            //George IMPROVEMENT 2020-05-07
            $result = $db->query($query);
            if (!$result) {
                $errorId = '637244613999301615';
                $error = 'Password reset was not successfull! Please check input and retry.';
                $logger->errorDb($errorId, $error, $db);
            } else {
            // End IMPROVEMENT

                $subject = "Password at SSS has been updated.";
                $body = "New password is : " . $newpassword . "\n\n\n"; // >>>00026 DO NOT EVER automatically email a password.
                                                                        // And, as Cristi notes below, we cant just tell them to change it again:
                                                                        // 2020-05-14 [CP] Advise to change again will not solve
                                                                        // the problem if any password reset will generate an email containing the password
                                                                        // The solution is to generate a link to a reset password page.
                $mail = new SSSMail();
                /*
                OLD CODE removed 2019-02-05 JM
                $mail->setFrom('inbox@ssseng.com', 'Sound Structural Solutions');
                */
                // BEGIN NEW CODE 2019-02-05 JM
                $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
                // END NEW CODE 2019-02-05 JM

                $mail->addTo($person->getUsername(), $person->getFormattedName());
                $mail->setSubject($subject);
                $mail->setBodyText($body);
                $mailresult = $mail->send();
                /*
                    2020-05-15 [CP] - Adding test on email sending success
                */
                if ($mailresult) {
                    // success
                    header("Location: " . $person->buildLink()); // Reload this page cleanly so refresh won't duplicate action
                    die();
                } else {
                    $errorId = '637249820326831377';
                    $logger->warn2($errorId, "Error sending the reset password email");
                    $error = "Error sending the reset password email";
                }
                /*
                    END - 2020-05-15 [CP] - Adding test on email sending success
                */
            }
        }
        unset($newpassword, $newconfirm);
    }
}


include BASEDIR . '/includes/header.php';

// BEGIN ADDED 2020-01-29 JM
?>
<style>
/* George ADDED 2020-04-14 */
h2.heading { font-weight: 500;}

#container1 { background:#fff;}
#container2 { background:#fff;}
.icon_img {width:45px; height:45px;}
.table-sm td, .table-sm th{vertical-align: middle;}
.table-sm{border: none;}
#iframe {width: 100%;}
.fancybox-skin{ background:#fff;}
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
</style>
<?php
if ($adminPerm) {
?>
<style>
body.show-revenue-metric td.revenue-metric {
    cursor:pointer;
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
<?php
}

?>

<?php
// END ADDED 2020-01-29 JM

echo "<script>\ndocument.title = 'Person: ". str_replace(Array("'", "&nbsp;"), Array("\'", ' '), $person->getFormattedName(0)) . "';\n</script>";

?>

<script>

// >>>00001 places a phone call to the person, not closely studied - JM 2019-04-03
function placeCall(extension, external){

<?php /*
    [BEGIN MARTIN COMMENT]
    fix this all up to return some
    useful json perhaps
    [END MARTIN COMMENT]
*/ ?>
    $.ajax({
        url: '/ajax/patchcall.php',
        data:{
            extension: extension,
            external: external
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {
                <?php /* >>>00032 Presumably to be written */ ?>
            } else {
                <?php /* >>>00032 Presumably to be written */ ?>
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
}

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

<div id="container" class="clearfix"> <?php /* jm debug: BEGIN DIV level 0
                                               This and similar comments below are to demonstrate that the DIVs are balanced.
                                               They are -- sort of -- but some are closed in the wrong place. Once that is
                                               fixed, feel free to strip these debug comments if you don't find them useful. */ ?>
    
    <?php
        $personToCopy = "";
        $userNameFromEmail = substr($person->getUsername(), 0, strrpos($person->getUsername(), '@'));
        $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/person/' . rawurlencode($personId);
        $personToCopy = htmlspecialchars($person->getFirstName()) . ' ' . htmlspecialchars($person->getLastName()); 

        if(  trim($personToCopy) == "" ) {
            $personToCopy = ucfirst($userNameFromEmail) ;
        }

        if( $personToCopy == "" && trim($userNameFromEmail) == "" ) {
            $personToCopy = "Person (" . $person->getPersonId() .")";
        }
    ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            (P)&nbsp;<a href="<?php echo $person->buildLink(); ?>"><?php echo $personToCopy ?></a>
       
            <button id="copyLink" title="Copy Person link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>    
        <span id="linkToCopy" style="display:none"> (P)<a href="<?php echo $person->buildLink(); ?>">&nbsp;<?php echo $personToCopy ?>&nbsp;</span>

        <span id="linkToCopy2" style="display:none"> (P)<a style="display:none" href="<?= $urlToCopy;?>">&nbsp;  <?php echo $personToCopy ?></a></span>
    </div>
    <div class="clearfix"></div>
    
    
    <div class="main-content">        <?php /* jm debug: BEGIN DIV level 1*/ ?>
    <?php
        /*
        [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
        $checkPerm = checkPerm($userPermissions, 'PERM_PAYRATE', PERMLEVEL_ADMIN);
        if ($checkPerm){
                $query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo  ";
                $query .= " where customerPersonId = (select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson where customerId = " . intval($customer->getCustomerId()) . " and personId = " . $person->getPersonId() . ") ";
                //$query .= " and periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "' ";
                $query .= " order by customerPersonPayPeriodInfoId desc limit 1 ";

                $cpppi = false;

                if ($result = $db->query($query)) {
                    if ($result->num_rows > 0){
                        while ($row = $result->fetch_assoc()){
                            $cpppi[] = $row;
                        }
                    }
                }
                if ($cpppi){
                    $x = $cpppi[0];
                    $rate = $x['rate'];
                    $salaryHours = $x['salaryHours'];
                    $salaryAmount = $x['salaryAmount'];
                    if (is_numeric($salaryAmount) && ($salaryAmount > 0)){
                        $pay = number_format(($salaryAmount/100),2) . '/yr';
                    } else if (is_numeric($rate) && ($rate > 0)){
                        $pay = number_format(($rate/100),2) . '/hr';
                    }
                }

                if (strlen($pay)){
                    $pay = '($' . $pay . ')';
                }
        }
        [END COMMENTED OUT BY MARTIN BEFORE 2019]
        */

        // Write person's first & last names as a heading
        ?>
        <h1><?php echo htmlspecialchars($person->getFirstName()) . '&nbsp;' . htmlspecialchars($person->getLastName());  ?>&nbsp;</h1>

        <?php
        // If this person is an employee of the current customer
        //  * get their 'intercom' phone extension
        //  * get logged-in user's own 'hard' phone extension
        if ($customer->isEmployee($person->getPersonId())) {
            $ext = '';
            $u = new User($person->getPersonId(), $customer);
            $extensions = $u->getExtensions();

            foreach ($extensions as $extension) {
                if ($extension['extensionType'] == 'intercom') {
                    $ext = $extension['extension'];
                    break;
                }
            }
      
            if (strlen($ext)) {
                $myextensions = $user->getExtensions();
                foreach ($myextensions as $mekey => $myextension) {
          
                    if ($myextension['extensionType'] == 'hard') {
                        // >>>00031 dubious use of HTML P element in the following (paragraph, never closed)
                        // >>>00014 >>>00031 I (JM 2019-04-03) have never heard of a 'tx' attribute in HTML,
                        //  so I have only a limited idea what is  going on here.
                        // Apparently displays icon for "hard" extensionType, which patches through a phone call if clicked.
                        echo '<a id="phoneExtension" class="async-phoneextension" tx="Ext : ' . $myextension['extension'] . '<p>Type : ' . $myextension['extensionTypeDisplay'] . '<p>Desc : ' . $myextension['description'] . '" href="javascript:placeCall(\'' . $myextension['extension'] . '\',\'' . htmlspecialchars($ext) . '\')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_phone_' . $myextension['extensionType'] . '.png" width="28" height="28"></a>';
                        break;
                    }
                }
            }
        } // END if this person is an employee of the current customer

        if ($error) {
            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
        }

        ?>

        <div class="container-fluid">               <?php /* jm debug: BEGIN DIV level 2*/ ?>
            <div class="row col-md-12">             <?php /* jm debug: BEGIN DIV level 3*/ ?>
                <div class="col-md-5">              <?php /* jm debug: BEGIN DIV level 4*/ ?>
                    <br />
                    <?php /* Person icon, followed by a form that lets you update various data for this person */ ?>
                    <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_person.png" class="icon_img"/>
                    <form name="person" id="personform" method="POST" action="">
                        <input type="hidden" name="act" value="updateperson">
                        <input type="hidden" name="personId" value="<?php echo intval($person->getPersonId()); ?>">
                        <table class="table table-bordered table-striped text-left table-responsive table-sm" style="width: 100%;"><tbody>
                        <div class="form-group">
                           <tr>
                                <td>Username</td>
                                <td width="100%"><input type="text" id="username" name="username" class="form-control input-sm" placeholder="Please enter Email"
                                    value="<?php echo htmlspecialchars($person->getUsername()); ?>" ></td>
                            </tr>
                        </div>
                        <div class="form-group">
                            <tr>
                                <td>First&nbsp;Name</td>
                                <td><input type="text" name="firstName"  id="firstName" class="form-control input-sm"
                                    value="<?php echo htmlspecialchars($person->getFirstName()); ?>" maxlength="100" ></td>
                            </tr>
                        </div>
                        <div class="form-group">
                            <tr>
                                <td>Middle&nbsp;Name</td>
                                <td><input type="text" name="middleName"  id="middleName" class="form-control input-sm"
                                    value="<?php echo htmlspecialchars($person->getMiddleName()); ?>" ></td>
                            </tr>
                        </div>
                        <div class="form-group">
                            <tr>
                                <td>Last&nbsp;Name</td>
                                <td><input type="text" name="lastName" id="lastName" class="form-control input-sm"
                                    value="<?php echo htmlspecialchars($person->getLastName()); ?>"></td>
                            </tr>
                        </div>
                            <tr><td colspan="2"><input type="submit" id="updatePerson" class="btn btn-secondary btn-sm mr-auto ml-auto mt-1" value="Update"></td></tr>
                        </tbody></table>
                    </form>
                    <?php
                        ////////////////////////////
                        // NOTES  //////////////////
                        ////////////////////////////
                    ?>

                    <div class="siteform mb-4" >           <?php /* jm debug: BEGIN DIV level 5*/ ?>
                        <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_notes.png" class="icon_img"/>
                        <br/>
                        Recent Notes<br/>

                        <iframe id="iframe" class="embed-responsive" src="/iframe/recentnotes.php?personId=<?php echo $person->getPersonId(); ?>"></iframe>

                        <br/>
                        <?php /* can click open a fancybox in an iframe to see all notes */ ?>
                        <a data-fancybox-type="iframe" id="linkIframePersonNotes" class="fancyboxIframe" href="/fb/notes.php?personId=<?php echo $person->getPersonId(); ?>">See All Notes</a>
                        <!-- <p></p>  /* >>George REMOVED 2020-05-06. No need for this, we can use Bootstrap class mb-4 to parent div class="siteform" -->
                    </div> <?php /* jm debug: END DIV level 5*/ ?>
                </div> <!-- Column one end -->  <?php /* (Martin wrote that comment)
                                                         jm debug: END DIV level 4; looks like things are back where they should be,
                                                         even though some prior DIVs are closed in the wrong place. */ ?>
                                                          <?php /* jm debug: BEGIN DIV level 4*/ ?>
                <div class="col-md-1"> </div> <?php /* George debug: DIV used only for additional space*/ ?>
                    <div class="col-md-6">   <?php /* George debug: BEGIN DIV level 5*/ ?>
                        <?php
                            ////////////////////////////
                            // PHONE  //////////////////
                            ////////////////////////////
                            $extensions = $user->getExtensions(); // These are the extensions of the logged-in user, *not* the person we are viewing/editing
                            ?>

                        <div class="form-group row"> <?php // George debug: BEGIN DIV level 6 ?>
                            <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_phone.png" class="icon_img mt-3"/>
                            <table class="table table-bordered table-striped text-left table-responsive table-sm mt-4">
                            <?php
                                // George IMPROVEMENT 2020-04-28. Change back to th. Add thead.
                                echo '<thead><tr>';
                                    echo '<th>&nbsp;</th>';
                                    echo '<th>Number</th>';
                                    echo '<th>Ext</th>';
                                    echo '<th>Type</th>';
                                    echo '<th>&nbsp;</th>';
                                    if (count($extensions)) {
                                        echo '<th style="text-align: center">Patch</th>';
                                    } else {
                                        echo '<th>&nbsp;</th>';
                                    }
                                    echo '<th>SMS</th>';
                                echo '</tr></thead>';
                                // END IMPROVEMENT
                                // George Added 2020-05-11. Display Error Message update Phone!
                                if ($errorUpdatePh) {
                                    foreach ($errorUpdatePh as $key => $value) {
                                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                                    }
                                }
                                // End Add
                                $phoneTypes = Person::getPhoneTypes($error_is_db);
                                $errorPhoneTypes = '';
                                if($error_is_db) { //true on query failed.
                                    $errorId = '637413772860708183';
                                    $errorPhoneTypes = "We could not display the Phone Types. Database Error."; // message for User
                                    $logger->errorDB($errorId, "Person::getPhoneTypes() method failled.", $db);
                                }
                                if ($errorPhoneTypes) {
                                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorPhoneTypes</div>";
                                }

                                $phones = $person->getPhones($error_is_db);
                                $errorPhones = '';
                                if($error_is_db) { //true on query failed.
                                    $errorId = '637413774359762915';
                                    $errorPhones = "We could not display the Phone Numbers for this person. Database Error."; // message for User
                                    $logger->errorDB($errorId, "getPhones() method failled", $db);
                                }
                                if ($errorPhones) {
                                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorPhones</div>";
                                }
                                unset($errorPhones);

                                if (!$errorPhoneTypes) {
                                    foreach ($phones as $pkey => $phone) {
                                        // George IMPROVEMENT 2020-05-06. Form inside <tr>.
                                        echo '<tr>';
                                            echo '<form name="updatephone"  id="updatePhone' . $phone['personPhoneId'] . '" method="post" action="">';
                                                // (no header) 1-based index of row
                                                echo '<td>';
                                                echo '<input type="hidden" name="act" value="updatephone">';
                                                echo '<input type="hidden" name="personPhoneId" value="' . htmlspecialchars($phone['personPhoneId']) . '">';
                                                echo ($pkey + 1) . ')';
                                                echo '</td>';
                                                // "Number": phone number
                                                echo '<td><input type="text" class="form-control input-sm updatePhoneNumber noTooltip" id="phoneNumber' . $phone['personPhoneId'] . '"
                                                name="phoneNumber"  value="' . htmlspecialchars($phone['phoneNumber']) . '" size="15" maxlength="64" ></td>';
                                                // "Ext"
                                                echo '<td><input type="text" name="ext1"  class="form-control input-sm noTooltip"  id="ext1' . $phone['personPhoneId'] . '"
                                                value="' . htmlspecialchars($phone['ext1']) . '" size="3" maxlength="5"></td>';

                                                // "Type": HTML SELECT offering all phone types; current type selected.
                                                echo '<td>';
                                                    echo '<select class="form-control input-sm noTooltip" style="width:90px;" name="phoneTypeId" id="phoneTypeId' . $phone['personPhoneId'] . '">';
                                                    foreach ($phoneTypes as $phoneType) {
                                                        $selected = ($phoneType['phoneTypeId'] == $phone['phoneTypeId']) ? ' selected' : '';
                                                        echo '<option value="' . $phoneType['phoneTypeId'] . '" ' . $selected . '>' . $phoneType['typeName'] . '</option>';
                                                    }
                                                    echo '</select>';
                                                echo '</td>';
                                                // (no header): Submit button, labeled "update"
                                                echo '<td><input type="submit"  class="btn btn-secondary btn-sm mr-auto ml-auto updatePh" id="updatePh' . $phone['personPhoneId'] . '"  value="Update"></td>';
                                            echo '</form>'; // George IMPROVEMENT 2020-05-06. Form ends here, after Update .

                                                // "Patch": nested table
                                                echo '<td>';
                                                    echo '<table border="0" cellpadding="2" cellspacing="1">';
                                                        echo '<tr>';
                                                            if (count($extensions)) {
                                                                foreach ($extensions as $extension) {
                                                                    // [BEGIN MARTIN COMMENT]
                                                                    //    [extensionType] => softmobile
                                                                    //    [extensionTypeDisplay] => Soft Mobile
                                                                    //    [displayOrder] => 1
                                                                    //    [extension] => 801
                                                                    //    [description] => Cellphone
                                                                    // [END MARTIN COMMENT]

                                                                    if ($extension['extensionType'] != 'intercom') {
                                                                        // >>>00031 dubious use of HTML P element in the following (paragraph, never closed)
                                                                        // >>>00014 >>>00031 I (JM 2019-04-03) have never heard of a 'tx' attribute in HTML,
                                                                        //  so I have only a limited idea what is  going on here.
                                                                        // Displays icon for current user's extensionType, which patches through a phone call
                                                                        //  to this phone number if clicked.
                                                                        echo '<td><a class="async-phoneextension"  id="phoneExtension' . $phone['personPhoneId'] . '" tx="Ext : ' . $extension['extension'] . '<p>Type : ' . $extension['extensionTypeDisplay'] . '<p>Desc : ' . $extension['description'] . '" href="javascript:placeCall(\'' . $extension['extension'] . '\',\'' . htmlspecialchars($phone['phoneNumber']) . '\')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_phone_' . $extension['extensionType'] . '.png" width="28" height="28"></a></td>';
                                                                    }
                                                                }
                                                            }
                                                            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                                            //echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $person->getPersonId() . '&personPhoneId=' . rawurlencode($phone['personPhoneId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a></td>';
                                                            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                                                        echo '</tr>';
                                                    echo '</table>';
                                                echo '</td>';

                                                // "SMS"
                                                echo '<td>';
                                                    if ($phone['phoneTypeId'] == PHONETYPE_CELL){
                                                        // Displays icon for sending an SMS, which brings up a dialog in an iframe to send
                                                        // this user an SMS if clicked. Always comes from "front door" phone.

                                                        // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                                        //    echo '<a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $person->getPersonId() . '&personPhoneId=' . rawurlencode($phone['personPhoneId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a>';
                                                        // [END COMMENTED OUT BY MARTIN BEFORE 2019]

                                                        /* OLD CODE REPLACED 2019-04-03 JM
                                                        echo '<a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/sms.php?personId=' . $person->getPersonId() . '&from=' . rawurlencode('14257781023') . '&to=' . rawurlencode($phone['phoneNumber']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a>';
                                                        */
                                                        // BEGIN REPLACEMENT CODE 2019-04-03 JM
                                                        echo '<a id="linkSms' . rawurlencode($phone['phoneNumber']) . '" data-fancybox-type="iframe" class="fancyboxIframe" '.
                                                            'href="/fb/sms.php?personId=' . $person->getPersonId() . '&from=' . rawurlencode(FLOWROUTE_SMS_FRONT_DOOR) . '&to=' . rawurlencode($phone['phoneNumber']) . '">'.
                                                            '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_sms.png" width="28" height="28"></a>';
                                                        // END REPLACEMENT CODE 2019-04-03 JM
                                                    }
                                                echo '</td>';
                                        echo '</tr>';   // George REPLACEMENT 2020-05-06. Before was  echo '<tr>';
                                        //echo '</form>';   // George REPLACEMENT 2020-05-06. Form is closed after Update. ;
                                    } // END foreach ($phones...


                                    // And another row to add a phone.
                                    // George IMPROVEMENT 2020-05-06. Form inside <tr>.
                                    echo '<tr>';
                                    // George Added 2020-05-11. Display Error Message add Phone!
                                    if ($errorPh) {
                                        foreach ($errorPh as $key => $value) {
                                            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                                        }
                                    }
                                    // End Add
                                        echo '<form name="addphone" id="addphone"  method="post"  action="">';
                                            echo '<input type="hidden" name="act" value="addphone">';
                                                // (first column, no header)
                                                echo '<td>&nbsp;</td>';
                                                // "Number"
                                                echo '<td ><input type="text" class="form-control input-sm" name="phoneNumber" id="addPhoneNumber" value="" size="15" maxlength="64" required > </td>';
                                                // Clearly this is phoneType, but >>>00006 it is in the "Ext" column
                                                // HTML SELECT offering all phone types; current type selected.
                                                echo '<td>';
                                                    echo '<select class="form-control input-sm" style="width:90px;" name="phoneTypeId"  id="phoneTypeId">';
                                                    foreach ($phoneTypes as $phoneType) {
                                                        echo '<option value="' . $phoneType['phoneTypeId'] . '">' . $phoneType['typeName'] . '</option>';
                                                    }
                                                    echo '</select>';
                                                echo '</td>';
                                                // "Submit" button, labeled "add"
                                                echo '<td><input type="submit" id="addPh"  class="btn btn-secondary btn-sm mr-auto ml-auto" value="Add"></td>';
                                        echo '</form>'; // George IMPROVEMENT 2020-05-06. Form inside <tr>.
                                    echo '</tr>';   // George REPLACEMENT 2020-05-06. Before was echo '<tr>'. ;
                                } unset($errorPhoneTypes);?>
                            </table>
                            <?php
                            ////////////////////////////
                            // EMAIL ///////////////////
                            ////////////////////////////
                            ?>
                            <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_mail.png" class="icon_img mt-3" />
                            <table class="table table-bordered table-striped text-left table-responsive table-sm mt-4">
                            <?php   // George IMPROVEMENT 2020-05-06. Changed td in th and echo '</tr>'.
                                echo '<tr>';
                                    echo '<th>&nbsp;</th>';
                                    echo '<th>Email</th>';
                                    echo '<th>&nbsp;</th>';
                                    echo '<th>&nbsp;</th>';
                                echo '</tr>';
                                // END IMPROVEMENT
                                $emails = $person->getEmails($error_is_db);
                                $errorEmails = '';
                                if($error_is_db) { //true on query failed.
                                    $errorId = '637413776365006740';
                                    $errorEmails = "We could not display the Emails for this person. Database Error."; // message for User
                                    $logger->errorDB($errorId, "getEmails() method failled", $db);
                                }

                                if ($errorEmails) {
                                    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorEmails</div>";
                                }
                                unset($errorEmails);
                                // George ADDED 2020-05-11. Display Error Message  Update Email.
                                if ($errorUpdateEm) {
                                    foreach ($errorUpdateEm as $key=>$value) {
                                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                                    }
                                }

                                // End ADD
                                foreach ($emails as $ekey => $email) {
                                    echo '<tr>'; // George IMPROVEMENT 2020-05-06. Form inside <tr>.
                                        echo '<form name="updateemail" method="post" id="updateEmail' . $email['personEmailId'] . '" action="">';
                                                // (no header) 1-based index of row
                                                echo '<td>';
                                                echo '<input type="hidden" name="act" value="updateemail">';
                                                echo '<input type="hidden" name="personEmailId" id="personEmail' . $email['personEmailId'] . '" value="' . htmlspecialchars($email['personEmailId']) . '">';
                                                    echo ($ekey + 1) . ')';
                                                echo '</td>';

                                                // "Email"
                                                echo '<td><input class="form-control input-sm updateEmailAddress noTooltip" id="updateEmailAddress'. $email['personEmailId'] .'" 
                                                 type="text" name="emailAddress" value="' . $email['emailAddress'] . '" size="40" maxlength="255" > </td>';
                                                // (no header): Submit button, labeled "update"
                                                echo '<td><input type="submit"  class="btn btn-secondary btn-sm mr-auto ml-auto updateEm" id="updateEm' . $email['personEmailId'] . '" value="Update"></td>';
                                                // (no header): send mail
                                                if (strlen($email['emailAddress'])) {
                                                    // 'Send mail' icon, "mailto" link brings up your default way of sending mail
                                                    echo '<td><a id="linkMailTo'. $email['personEmailId'] .'"  href="mailto:' . $email['emailAddress'] . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_email.png" width="28" height="28" ></a></td>';
                                                    // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                                    //echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/email.php?personId=' . $person->getPersonId() . '&personEmailId=' . $email['personEmailId'] . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_send_email.png" width="28" height="28"></a></td>';
                                                    // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                                                } else {
                                                    echo '<td>&nbsp;</td>';
                                                }

                                                //echo '<td>&nbsp;</td>'; Removed by George 2020-04-14: this was an extra column, no header, no idea why this existed
                                        echo '</form>'; // George IMPROVEMENT 2020-05-06. Form inside <tr>.
                                    echo '</tr>';// George REPLACEMENT 2020-05-06. Before was echo '<tr>'. ;

                                } // END foreach ($emails...


                                // And another row to add an email address.
                                echo '<tr>'; // George REPLACEMENT 2020-05-06. Form inside <tr>.
                                // George ADDED 2020-05-11. Display Error Message  Add Email.
                                if ($errorEm) {
                                    foreach ($errorEm as $key=>$value) {
                                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$value[0]</div>";
                                    }
                                }
                                // End ADD
                                    echo '<form name="addemail" method="post" id="addEmailForm" action="">';
                                        echo '<input type="hidden" name="act" value="addemail">';
                                            // (first column, no header)
                                            echo '<td>&nbsp;</td>';

                                            // "Email"
                                            echo '<td><input class="form-control input-sm" type="text" name="emailAddress" id="addEmailAddress" value="" size="40" maxlength="255" required></td>';
                                            // "Submit" button, labeled "add"
                                            echo '<td><input type="submit"   id="addEm" class="btn btn-secondary btn-sm mr-auto ml-auto" value="Add"></td>';

                                            echo '<td>&nbsp;</td>';
                                    echo '</form>';// George REPLACEMENT 2020-05-06. Form inside <tr>.
                                echo '</tr>';// George REPLACEMENT 2020-05-06. Before was echo '<tr>'. ;
                            ?>
                            </table>
                        </div>  <?php /* George debug: END DIV level 6*/ ?>

                        <?php
                        ////////////////////////////
                        // END EMAIL
                        ////////////////////////////
                        ?>

                    </div>  <?php /* George debug: END DIV level 5*/ ?>
                </div>      <?php /* jm debug: END DIV level 4*/ ?>
            </div>          <?php /* jm debug: END DIV level 3*/ ?>
        </div>              <?php /* jm debug: END DIV level 2*/ ?>

        <?php
            ////////////////////////////
            // COMPANIES & JOBS ////////
            ////////////////////////////
            $companies = $person->getCompanies($error_is_db);
            $errorCompanies = ""; // On jobs tab.
            if($error_is_db) { //true on query failed.
                $errorId = '637413777205091273';
                $errorCompanies = "Error fetching Companies. Database Error."; // message for User
                $logger->errorDB($errorId, "getCompanies() method failled", $db);
            }

            $title = (count($companies) == 1) ? 'Company' : 'Companies';
        ?>
        <div class="full-box clearfix"> <?php /* jm debug: BEGIN DIV level 2*/ ?>
            <h2 class="heading"><?php  echo $title; ?></h2>
            <?php /* button labeled "add" to add a companyPerson (new company for this person) */ ?>
            <a data-fancybox-type="iframe" id="addCompanyPerson" class="button add show_hide fancyboxIframe"  href="/fb/addcompanyperson.php?personId=<?php echo $person->getPersonId(); ?>">Add</a>
            <table class="arCenter table table-bordered table-striped">
                <tbody>
                    <tr>
                        <th class="async-company-person-header">C_P</th>
                        <th>Company Name</th>
                        <th>Company Nickname</th>
                        <th>URL</th>
                    </tr>
                    <?php
                    $cpbyperson = $person->getCompanyPersons($error_is_db); // "companyPerson by person"
                    $errorCompanyPersons = "";
                    if($error_is_db) { //true on query failed.
                        $errorId = '637413780363481626';
                        $errorCompanyPersons = "We could not display the Companies associated to this Person. Database Error."; // message for User
                        $logger->errorDB($errorId, "getCompanyPersons() method failled", $db);
                    }

                    if ($errorCompanyPersons) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorCompanyPersons</div>";
                    }
                    unset($errorCompanyPersons);

                    foreach ($cpbyperson as $cpbp) {
                        echo '<tr>';
                            // (no header): "Edit" icon, leads to CompanyPerson page
                            echo '<td align="center" width="20"><a id="linkCompanyPerson' . $cpbp->getCompanyPersonId() . '" href="' . $cpbp->buildLink() . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16" ></a></td>';

                            // "Company Name"
                            echo '<td><a id="linkCompanyName' . $cpbp->getCompany()->getCompanyId() . '" href="' . $cpbp->getCompany()->buildLink() . '">' . $cpbp->getCompany()->getCompanyName() . '</a></td>';

                            // "Company Nickname"
                            echo '<td><a id="linkCompanyNickname' . $cpbp->getCompany()->getCompanyId() . '" href="' . $cpbp->getCompany()->buildLink() . '">' . $cpbp->getCompany()->getCompanyNickname() . '</a></td>';

                            // "URL"
                            echo '<td><a id="linkCompanyUrl' . $cpbp->getCompany()->getCompanyId() . '" href="' . $cpbp->getCompany()->buildLink() . '">' . $cpbp->getCompany()->getCompanyURL() . '</a></td>';
                        echo '<tr>';
                    }

        /*
        [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
        foreach ($companies as $ckey => $company){

            echo '<tr>';

                echo '<td><a href="' . $company->buildLink() . '">' . $company->getCompanyName() . '</a></td>';
                echo '<td>' . $company->getCompanyNickname() . '</td>';
                echo '<td>' . $company->getCompanyURL() . '</td>';


            echo '</tr>';

        }
        [END COMMENTED OUT BY MARTIN BEFORE 2019]

        */

                ?>
                </tbody>
            </table>
        </div>                 <?php /* jm debug: END DIV level 2*/ ?>


        <?php
            //$companies = $person->getCompanies();  // >>>00006 That was already set above
            $title = 'Jobs';
        ?>

        <div class="full-box clearfix">     <?php /* jm debug: BEGIN DIV level 2*/ ?>
            <h2 class="heading"><?php  echo $title; ?></h2>
            <table  class="arCenter table table-bordered table-striped">
                <tbody>
                    <?php
                        // BEGIN ADDED 2020-01-29 JM
                        if ($timeSummaryPerm) {
                    ?>
                            <th colspan="5">
                                <input type="checkbox" id="show-revenue-metric"/>&nbsp;<label for="show-revenue-metric">Show revenue/cost ratio</label><br/>
                            </th>
                            <script>
                            $(
                                $("#show-revenue-metric").change(function() {
                                    if (this.checked) {
                                        $('body').addClass('show-revenue-metric');
                                    } else {
                                        $('body').removeClass('show-revenue-metric');
                                    }
                                })
                            );
                            </script>
                    <?php
                        }
                        // END ADDED 2020-01-29 JM
                    ?>
                    <tr>
                        <th>Company</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Status</th>
                    <?php
                        if ($errorCompanies) {
                            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorCompanies</div>";
                        }
                        unset($errorCompanies);
                        // BEGIN ADDED 2020-01-29 JM
                        if ($timeSummaryPerm) {
                    ?>
                            <th style="width:30%">Ratio</th>
                    <?php
                        }
                        // END ADDED 2020-01-29 JM
                    ?>
                    </tr>

                    <?php
                        foreach ($companies as $company) {
                            $ids = array();
                            $jobs = array();

                            // Select for person being on team via workOrder

                            $query  = " SELECT t.*,w.workOrderId,j.jobStatusId,j.jobId,js.jobStatusName ";
                            $query .= " FROM " . DB__NEW_DATABASE . ".team t ";
                            $query .= " JOIN " . DB__NEW_DATABASE . ".workOrder w ON t.id = w.workOrderId ";
                            $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".job j ON w.jobId = j.jobId ";
                            $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".jobStatus js ON j.jobStatusId = js.jobStatusId ";
                            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                            //    $query .= " where t.companyPersonId = " . intval($company->companyPersonId);
                            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                            $query .= " WHERE t.companyPersonId = (SELECT companyPersonId FROM " . DB__NEW_DATABASE . ".companyPerson " .
                                      " WHERE companyId = " . $company->getCompanyId() . // fixed "and" to "where" 2020-03-17 JM
                                      " AND personId = " . intval($person->getPersonId()) . ") ";
                            $query .= " AND t.inTable = " . intval(INTABLE_WORKORDER);

                            // BEGIN ADDED 2020-01-29 JM
                            if ($timeSummaryPerm) {
                                // We will want the rows that differ only in workOrder.
                                //$query .= " ORDER BY j.jobStatusId , j.number DESC, w.workOrderId ASC ";
                                $query .= " ORDER BY j.jobStatusId, j.number DESC";
                            } else {
                            // END ADDED 2020-01-29 JM
                                $query .= " GROUP BY j.jobId ";
                                $query .= " ORDER BY j.jobStatusId, j.number DESC ";
                            // BEGIN ADDED 2020-01-29 JM
                            }
                            // END ADDED 2020-01-29 JM

                            $result = $db->query($query);
                            if (!$result) {
                                // We get here if query fails
                                $logger->errorDb('637191249009805024', "", $db);
                            } else {
                                while ($row = $result->fetch_assoc()) {
                                    $jobs[] = $row;
                                    $ids[$row['jobId']] = $row['jobId'];
                                }
                            }

                            // Select for person being on team via Job, see if we pick up some where
                            //  they weren't on any workOrder-specific team.

                            $query  = " SELECT t.*,j.jobStatusId,j.jobId,js.jobStatusName ";
                            $query .= " FROM " . DB__NEW_DATABASE . ".team t ";
                            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                            //$query .= " join " . DB__NEW_DATABASE . ".workOrder w on t.id = w.workOrderId ";
                            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                            $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".job j ON t.id = j.jobId ";
                            $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".jobStatus js ON j.jobStatusId = js.jobStatusId ";
                            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                            //$query .= " where t.companyPersonId = " . intval($company->companyPersonId);
                            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                            $query .= " WHERE t.companyPersonId = (SELECT companyPersonId FROM " . DB__NEW_DATABASE . ".companyPerson WHERE companyId = " . $company->getCompanyId() . " AND personId = " . intval($person->getPersonId()) . ") ";
                            $query .= " AND t.inTable = " . intval(INTABLE_JOB);
                            $query .= " GROUP BY j.jobId ";
                            $query .= " ORDER BY j.jobStatusId ";
                            $query .= " ,j.number DESC ";

                            $result = $db->query($query);
                            if (!$result) {
                                // We get here if query fails
                                $logger->errorDb('637191248778437304', "", $db);
                            } else {
                                while ($row = $result->fetch_assoc()){
                                    if (!in_array($row['jobId'], $ids)){
                                        $jobs[] = $row;
                                    }
                                }
                            }

                            // BEGIN REPLACED 2020-01-29 JM: changing to a while loop 2020-01-29 JM so we can play with index
                            // foreach ($jobs as $jkey => $j) {
                            //     $jobId = $j['jobId'];
                            //     $job = new Job($jobId);
                            // END REPLACED 2020-01-29 JM
                            // BEGIN REPLACEMENT 2020-01-29 JM
                            $jkey=0;
                            $companyShown = false;
                            $num_jobs = count($jobs);
                            while ($jkey < $num_jobs) {
                                $j = $jobs[$jkey];
                                $jobId = $j['jobId'];
                                $job = new Job($jobId);
                                if ($timeSummaryPerm) {
                                    $workOrderList = ''; // string: comma-separated workOrderIds for this job
                                    $j2key = $jkey;
                                    while ($j2key < $num_jobs && $jobs[$j2key]['jobId'] == $jobId) {
                                        $j2 = $jobs[$j2key]; // if this is a different row than $j, the only difference will be workOrderId.
                                        if (array_key_exists('workOrderId', $j2)) {
                                            if ($workOrderList) {
                                                $workOrderList .= ',';
                                            }
                                            $workOrderList .= intval($j2['workOrderId']);
                                        }
                                        ++$j2key;
                                    }
                                }
                            // END REPLACEMENT 2020-01-29 JM

                                echo '<tr>';
                                    // "Company": for first row: list company name, link it to Company page
                                    if (!$companyShown) { // (JM 2020-01-29 changed to $companyShown rather than testing $jkey==0)
                                        echo '<td><a id="linkCompanyShown' . $company->getCompanyId() . '" href="' . $company->buildLink() . '">' . $company->getCompanyName() . '</a></td>';
                                        $companyShown = true;
                                    } else {
                                        echo '<td>&nbsp;</td>';
                                    }
                                    // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                    //echo '<td><a href="' . $company->buildLink() . '">' . $company->getCompanyName() . '</a></td>';
                                    // [END COMMENTED OUT BY MARTIN BEFORE 2019]

                                    // "Number": Job Number
                                    echo '<td><a id="linkJobNumber'. $job->getNumber() . '"  href="' . $job->buildLink() . '">' . $job->getNumber() . '</a></td>';

                                    // "Name": Job name
                                    echo '<td>' . $job->getName() . '</td>';

                                    // "Status": Job status
                                    echo '<td>' . $j['jobStatusName'] . '</td>';

                                    // BEGIN ADDED 2020-01-29 JM
                                    if ($timeSummaryPerm) {
                                        echo '<td class="revenue-metric' . ($workOrderList ? ' needs-fetch' : '') .
                                        '" data-workorderids="' . $workOrderList . '">' .
                                        ($workOrderList ? '&hellip;' : '') .
                                        '</td>';
                                    }
                                    // END ADDED 2020-01-29 JM

                                echo '</tr>';
                                // BEGIN ADDED 2020-01-29 JM
                                if ($timeSummaryPerm) {
                                    $jkey = $j2key;
                                } else {
                                    ++$jkey;
                                }
                                // END ADDED 2020-01-29 JM
                            }
                        } // END foreach ($companies...
                        ?>
                </tbody>
            </table>
        </div>    <?php /* jm debug: END DIV level 2*/ ?>
        <?php
        ////////////////////////////
        // LOCATIONS ///////////////
        ////////////////////////////
        $locations = $person->getLocations($error_is_db);
        $errorLocations = "";
        if ($error_is_db) { //true on query failed.
            $errorId = "637413781713710894";
            $errorLocations = "We could not display the Locations for this Person. Database Error.";
            $logger->errorDB($errorId, "getLocations() method failled.", $db);
        }

        if ($errorLocations) {
            echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorLocations</div>";
        }
        unset($errorLocations);

        $title = (count($locations) == 1) ? 'Location' : 'Locations';
        ?>
        <div class="full-box clearfix">  <?php /* jm debug: BEGIN DIV level 2*/ ?>
            <h2 class="heading"><?php  echo $title; ?></h2>

            <?php /* Button to add a location; opens in new page rather than popup */
            // permission check added 2019-11-26 JM
            if (checkPerm($userPermissions, 'PERM_LOCATION', PERMLEVEL_RWA)) {
                echo '<a id="addPersonLocation" class="button add show_hide" href="/location.php?personId=' . $person->getPersonId() . '">Add</a>';
            }
            ?>
            <table class="table table-bordered table-striped">
                <tbody>
                <?php
                foreach ($locations as $row) {
                    $location = new Location($row['locationId']);
                    echo '<tr><td>';
                        // formatted location, linked to open location in its own page
                        // personId added JM 2019-11-21 so location.php can know to navigate back here.
                        echo '<a id="linkPersonLocation'. $location->getLocationId() . '"    href="/location.php?locationId=' . $location->getLocationId() .
                            '&personId='.$person->getPersonId().'">' . $location->getFormattedAddress() . '</a>';
                        // JM added remove/delete/delink capability 2019-11-26
                        if (checkPerm($userPermissions, 'PERM_LOCATION', PERMLEVEL_RWA)) {
                            echo '&nbsp;<button class="delink" data-locationid="'.$location->getLocationId().'" 
                            id="removePersonLocation'. $location->getLocationId() . '" >Remove</button>';
                        }
                    echo '</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>  <?php /* jm debug: END DIV level 2*/ ?>
        <script> <?php /* BEGIN ADDED 2019-11-26 JM to allow delinking a location */ ?>
            $('button.delink').click(function() {
                let $this = $(this);
                $.ajax({
                    url: '/ajax/delinklocation.php',
                    data: {
                        locationId: $this.data('locationid'),
                        personId: <?php echo $personId; ?>
                    },
                    async:false,
                    type:'post',
                    context: this,
                    success: function(data, textStatus, jqXHR) {
                        if (data['status']) {
                            if (data['status'] == 'success') {
                                // reload this page
                                location.reload(true);
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
            // PRIVATE URLs ////////////
            ////////////////////////////

            // If logged-in user has Admin level "private URL" permissions, in theory
            //  can open /fb/privatecontact.php in an iframe, passing in the appropriate personId,
            //  and there is also a link "Open Work Orders" to access relevant work orders:
            //  it opens private.php in an iframe, passing in the appropriate personId and passing
            //  privateTypeId=PRIVTYPE_OPEN_WO.
            // >>>00026 JM: BUT as of 2018-10-08, and certainly nothing has changed 2019-04, private.php
            //  appears no longer to exist, so this is a hook to nowhere. Martin wrote 2018-11-11,
            //  "This is just some stubbed out stuff and is up in the air." I (JM) take it that
            //  means we haven't decided whether we are bringing that back or not.
            //  I (JM) think it is bad practice to leave a state where the link is there but the target isn't.
            $checkPerm = checkPerm($userPermissions, 'PERM_PRIVATEURL', PERMLEVEL_ADMIN);
            if ($checkPerm) {
            ?>
                <div class="full-box clearfix"> <?php /* jm debug: BEGIN DIV level 2*/ ?>
                    <h2 class="heading">Private URLs</h2>
                    <a data-fancybox-type="iframe" id="setContract" class="button add show_hide fancyboxIframe"  href="/fb/privatecontact.php?personId=<?php echo $person->getPersonId(); ?>">Set Contact</a>
                    <a id="openWorkOrders" href="/private.php?personId=<?php echo $person->getPersonId(); ?>&privateTypeId=<?php echo PRIVTYPE_OPEN_WO; ?>">Open Work Orders</a>
                </div> <?php /* jm debug: END DIV level 2*/ ?>
            <?php
            }

            ////////////////////////////
            // SMS PERMISSIONS /////////
            ////////////////////////////

            // If logged-in user has Admin level "private URL" permissions...
            // >>>00026 JM 2019-04-03: I'm almost certain this is the wrong permission here.
            // ... FORM to view, edit, and (via self-submission) update SMS permissions for this person
            $checkPerm = checkPerm($userPermissions, 'PERM_PRIVATEURL', PERMLEVEL_ADMIN);
            if ($checkPerm) {
            ?>
                <div class="full-box clearfix">  <?php /* jm debug: BEGIN DIV level 2*/ ?>
                    <h2 class="heading">SMS Perms</h2>
                    <form name="smsperms" id="smsperms" method="POST">
                        <input type="hidden" name="personId" value="<?php echo intval($person->getPersonId()); ?>">
                        <input type="hidden" name="act" value="updatesmsperms">
                        <table  class="table table-bordered table-striped">
                            <?php
                            foreach (SMS::smsPerms() as $smskey => $smsPerm){
                                echo '<tr>';
                                echo '<td>' . $smsPerm['display'] . '</td>';
                                $checked = ($smskey & $person->getSmsPerms()) ? ' checked ' : '';
                                echo '<td><input id="smsPerm' . $smskey . '" type="checkbox" name="smsPerm[]" value="' . $smskey . '" ' . $checked . '></td>';
                                echo '</tr>';
                            }
                            ?>
                            <tr>
                                <td colspan="2"><input type="submit" id="updateSmsPermissions" class="btn btn-secondary btn-sm mr-auto ml-auto" value="Update SMS permissions">
                            </tr>
                        </table>
                    </form>
                </div>  <?php /* jm debug: END DIV level 2*/ ?>
            <?php
            }
            /* REPLACED 2019-12-09 JM
            // if the current logged-in user is designated as an admin
            if (in_array($user->getUserId(), $adminids)) {
            */
            // BEGIN REPLACEMENT CODE 2019-12-09 JM
            // if the current logged-in user has admin-level permission to grant passwords...
            $checkPerm = checkPerm($userPermissions, 'PERM_PASSWORD', PERMLEVEL_ADMIN);
            if ($checkPerm) {
            // END REPLACEMENT CODE 2019-12-09 JM
            ?>
                <div class="full-box clearfix">  <?php /* jm debug: BEGIN DIV level 2*/ ?>
                    <h2 class="heading">Reset Password</h2>
                    <form name="updatepass" id="updatepass" method="POST" action="">
                        <input type="hidden" name="act" value="updatepassword">
                        <input type="hidden" name="personId" value="<?php echo intval($person->getPersonId()); ?>">
                        <?php
                            // >>>00006 this could really come outside of the updatepass form
                            if ($act == 'updatepassword') {
                                if(isset($errors_in_password)){
                                    if (count($errors_in_password)){
                                        // Report any errors
                                        echo '<ul>';
                                        foreach ($errors_in_password as $error_in_password) {
                                            echo '<li>' . $error_in_password . '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        // No errors, report whether email notification was sent successcully
                                        if ($mailresult) {
                                            echo "Mail notification sent OK!\n\n";
                                        } else {
                                            echo "Mail notification FAILED!\n\n";
                                        }
                                    }
                                }
                            } // END if ($act == 'updatepassword')

                        // Then, regardless of whether we just changed the password, the rest of the form to change the password.
                        ?>
                        <table  class="mt-3"><tbody>
                        <tr><td>New&nbsp;Password</td><td width="100%"><input type="password" class="form-control input-sm" name="newpassword" id="newpassword" value="" maxlength="128" style="width:300px"></td></tr>
                        <tr><td>Confirm</td><td><input type="password" name="newconfirm" id="newconfirm" class="form-control input-sm" value="" maxlength="128" style="width:300px"></td></tr>
                        <tr><td colspan="2"><input type="submit" class="btn btn-secondary btn-sm mr-auto ml-auto mt-1" id="resetPassword" value="Reset"></td></tr>
                        </tbody></table>
                    </form>
                </div>  <?php /* jm debug: END DIV level 2*/ ?>
            <?php
            } // END if the current logged-in user is designated as an admin

            // If current user has admin-level permission for granting permissions...
            $checkPerm = checkPerm($userPermissions, 'PERM_PERMISSION', PERMLEVEL_ADMIN);
            if ($checkPerm) {
                // ... (>>>00001 if I understand correctly - JM 2019-04-03) a button "Permissions"
                // to pop up a fancybox to edit permissions for the person this page is about.
            ?>
                <a data-fancybox-type="iframe" id="linkPersonPermissions" class="fancyboxIframe" href="/fb/personpermissions.php?personId=<?php echo $person->getPersonId(); ?>"><button value="" style="margin-top:20px; padding:0px 20px;" class="button save">Permissions</button></a>
            <?php
                }
            ?>
    </div> <?php /* jm debug: END DIV level 1*/ ?>
</div>     <?php /* jm debug: END DIV level 0*/ ?>

<?php
// BEGIN ADDED 2020-01-29 JM
if ($adminPerm) {
?>
<script>
    // Click to get full revenue report
    $( document ).ready(function() {
        $('td.revenue-metric').click(function() {
            let $this = $(this);
            let workOrderIdList = $this.data('workorderids');
            $('tr.wots-container').remove(); // get rid of any prior one
            {
                let columns = $this.closest('tr').find('td').length;
                $workordertimesummaryRow = $('<tr class="wots-container kill-with-work-order-time-summary"><td colspan="' + columns + '" ' +
                            'style="border-bottom: 2px solid black; border-top: 2px solid black;"></td></tr>');
            }
            $.ajax({
                url: '../ajax/workordertimesummary.php',
                data:{
                    workOrderIdList: workOrderIdList
                },
                async: true,
                type: 'post',
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            $('td', $workordertimesummaryRow).html($(data['html']));
                            $workordertimesummaryRow.insertAfter($this.closest('tr'));
                        } else {
                            // >>>00002 Here and below, probably a better way to display errors other than an alert
                            alert('Server-side error: ' + data['error'] + ' in call to ajax/workordertimesummary.php; input was: ' + workOrderIdList);
                        }
                    } else {
                        alert('Server-side error, no status returned in call to ajax/workordertimesummary.php; input was: ' + workOrderIdList);
                        console.log(data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('AJAX error in call to ajax/workordertimesummary.php; input was: ' + workOrderIdList);
                }
            });
        });
    });
</script>
<?php
} // END if ($adminPerm)

if ($timeSummaryPerm) {
?>
<script>
    // "Slow recursion" (via setTimeout) to fill in revenue metrics values
    $( document ).ready(function() {
        const jobsAtOnce = 20;          // how many of these we fill in at each call to ajax/getworkorderrevenuemetric.php
        const fetchInterval = 250;      // wait 0.25 seconds to make next call
        function fetch() {
            let workOrderIdList = '';
            let $nodes = $('td.revenue-metric.needs-fetch').slice(0, jobsAtOnce); // Identify the HTML TD elements we are trying to fill in
            if ($nodes.length) {  // This test lets us end cleanly when we've got them all.
                $nodes.each(function() {
                    if (workOrderIdList) {
                        // Not the first
                        workOrderIdList += ';'
                    }
                    workOrderIdList += $(this).data('workorderids');
                });

                // At this point, workOrderIdList will be something like the following; to
                //  keep this small, the example here assumes jobsAtOnce == 5:
                //     4987,4897;9875;1353,7789;8883
                // semicolons separate nodes, commas separate workOrderIds within a node.

                // The following is asynchronous, strictly-background stuff, so we don't bother the
                //  user with any error reporting, just write it to the console.
                $.ajax({
                    url: '../ajax/getworkorderrevenuemetric.php',
                    data:{
                        workOrderIdList: workOrderIdList
                    },
                    async: true,
                    type: 'post',
                    success: function(data, textStatus, jqXHR) {
                        if (data['status']) {
                            if (data['status'] == 'success') {
                                for (let iii in data['metrics']) {
                                    $('td.revenue-metric.needs-fetch[data-workorderids="' + iii + '"]').html(data['metrics'][iii]).removeClass('needs-fetch');
                                }
                                setTimeout(fetch, fetchInterval);
                            } else {
                                console.log('Server-side error: ' + data['error'] + ' in call to ajax/getworkorderrevenuemetric.php; input was: ' + workOrderIdList);
                            }
                        } else {
                            console.log('Server-side error, no status returned in call to ajax/getworkorderrevenuemetric.php; input was: ' + workOrderIdList);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log('AJAX error in call to ajax/getworkorderrevenuemetric.php; input was: ' + workOrderIdList);
                    }
                });
            }
        } // END function fetch
        fetch();
    });
</script>
<?php
}
// END ADDED 2020-01-29 JM
?>
<script>
var jsonErrors = <?=json_encode($v->errors())?>;
var validator = $('#personform').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: {
        'username':{
            required: true,
            email: true
        }
    }
});
validator.showErrors(jsonErrors);
// The moment they start typing (or pasting) in a field, remove the validator warning
$('#username').on('mousedown', function(){
    $('#validator-warning').hide();
    /*George : hide error-messages after input*/
    $('#username-error').hide();
    if ($('#username').hasClass('text-danger')){
        $("#username").removeClass("text-danger");
    }
});

/* George ADDED 2020-04-28
New functions for Client side validation.
Validation with Validator failed. Added for each action a function.
Validate phone, updatephone, email, updateemail */

/* George ADDED 2020-04-28. Jquery Validation.
New action on form name="addemail".
Validation field id="addEmailAddress" for not empty, Validate only if we have emails format!
*/
$('#addEm').click(function() {
    $(".error").hide();
        var hasError = false;
        var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;

        var emailaddressVal = $("#addEmailAddress").val();
        emailaddressVal =  emailaddressVal.trim(); //trim value

        if(emailaddressVal == '') {
            $("#addEmailAddress").after('<span class="error">Please enter your email address.</span>');
            hasError = true;
        }

        else if(!emailReg.test(emailaddressVal)) {
            $("#addEmailAddress").after('<span class="error">Enter a valid email address.</span>');
            hasError = true;
        }

        if(hasError == true) { return false; }
});
/*End ADD */
/* George 2020-06-12.
Validation field class="updateEmailAddress". Validate only if we have emails format!
If blank, the record is deleted from DB. Handled by the method in class. */
$('.updateEm').click(function() {
    $(".error").hide();
        var hasError = false;
        var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;

        /*target exact input to update */
        var specific = $(this).closest('tr').find('input.updateEmailAddress');
        var emailaddressVal = $(this).closest('tr').find('input.updateEmailAddress').val();
        emailaddressVal =  emailaddressVal.trim(); //trim value

        if(!emailReg.test(emailaddressVal)) {
            $(specific).after('<span class="error">Enter a valid email address.</span>');
            hasError = true;
        }

        if(hasError == true) { return false; }
});

/* George IMPROVEMENT 2020-11-17. Jquery Validation.
New action on form name="addphone".
Validation field id="addphoneNumber" for not empty, no letters input.
Javascript Message if we have don't have exactly 10 digits! We have a Log message in server side.
*/

$('#addPh').click(function() {
    $(".error").hide();
    var hasError = false;

    var addPhoneNumber = $("#addPhoneNumber").val();
    // Digits. Count lenght of digits characters.
    var inputPh = addPhoneNumber.match(/\d/g);
    var inputPhLen = 0;

    if(inputPh != null) {
        inputPhLen = inputPh.length;
    }

    if(addPhoneNumber == '') {
        $("#addPhoneNumber").after('<span class="error">Please enter your Phone Number.</span>');
        hasError = true;
    // George IMPROVED 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
    } else if(!addPhoneNumber.match(/^[- ()0-9]*$/) ) {
        $("#addPhoneNumber").after('<span class="error">Invalid characters in Phone number.</span>');
        hasError = true;
    // Warning  message if we don't have exactly 10 digits.
    } else if (inputPhLen != 10) {
        alert("Phone number must be 10 digits. Enter a valid number.");
        hasError = true;
    }

    if(hasError == true) { return false; }
});

//End IMPROVEMENT

/* George IMPROVED 2020-11-17. Jquery Validation.
New action on form name="updatephone".
Validation field class="updatePhoneNumber" no letters input.
If blank, the record is deleted from DB. Handled by the method in class.
Javascript Message if we have don't have exactly 10 digits! We have a Log message in server side.
*/

$('.updatePh').click(function() {
    $(".error").hide();
    var hasError = false;

    // target exact input to update
    var specific = $(this).closest('tr').find('input.updatePhoneNumber');
    var addPhoneNumber = $(this).closest('tr').find('input.updatePhoneNumber').val();

    //George ADDED 2020-04-29. If Phone Number is less than 10 digits.
    // Message: Do you still want to use this Phone Number? If clicks "Ok" we bring initial value (oldPhone), to rewrite.
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
    // George IMPROVED 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
    else if(!addPhoneNumber.match(/^[- ()0-9]*$/) ) {
        $(specific).after('<span class="error">Invalid characters in Phone number.</span>');
        hasError = true;

    // George IMPROVED 2020-11-17. Warning message if we DON'T have exactly 10 digits.
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


$('#addPhoneNumber, #addEmailAddress, .updatePh, .updateEm').on('mousedown', function(){
    // George : hide error-messages after input
    $('.error').hide();
    }
);
</script>

<?php
include BASEDIR . '/includes/footer.php';
?>
