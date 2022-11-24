<?php
/* companyperson.php

   EXECUTIVE SUMMARY: This is a top-level page. Allows user to view and edit data about a companyPerson,
    that is: a person in the context of one particular company with whom they are associated.

   There is a RewriteRule in the .htaccess to allow this to be invoked as just "companyperson/foo"
   rather than "companyperson.php?companyPersonId=foo".

  PRIMARY INPUT _REQUEST['companyPersonId'] - identifies companyPerson.

  Optional inputs:
    * $_REQUEST['act']. Possible values: 'updatearbitrary', 'setBlockException', 'addBlock'.
        * $_REQUEST['act']='updatearbitrary' takes additional inputs:
            * _REQUEST['companyPersonId'] and _REQUEST['arbitraryTitle'].
        * $_REQUEST['act']='setBlockException' takes additional inputs
            * _REQUEST['companyPersonId'] and _REQUEST['blockException'].
        * $_REQUEST['act']='addBlock' takes additional inputs:
            & _REQUEST['companyPersonId'], _REQUEST['billingBlockTypeId'], and _REQUEST['note'].

*/

/* [BEGIN MARTIN COMMENT]
create table billingBlock(
    billingBlockId        int unsigned not null primary key auto_increment,
    billingBlockTypeId    tinyint unsigned not null,
    companyPersonId       int unsigned not null,
    note                  text,
    personId              int unsigned not null,
    inserted              timestamp not null default now()
);

create index ix_billingblock_bbtid on billingBlock(billingBlockTypeId);
create index ix_billingblock_cpid on billingBlock(companyPersonId);

[END MARTIN COMMENT]
*/
require_once './inc/config.php';
require_once './inc/perms.php';
// ADDED by George 2020-08-04, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'companyPersonId');
$v->rule('integer', 'companyPersonId');
$v->rule('min', 'companyPersonId', 1);

if( !$v->validate() ) {
    $errorId = '637321360272096266';
    $logger->error2($errorId, "companyPersonId : " . $_REQUEST['companyPersonId'] . "  not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid companyPersonId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$companyPersonId = intval($_REQUEST['companyPersonId']);

// Now we make sure that the row actually exists in DB table 'companyPerson'.
if (!CompanyPerson::validate($companyPersonId)) {
    $errorId = '637321360998551380';
    $logger->error2($errorId, "The provided companyPersonId ". $companyPersonId ." does not correspond to an existing row in companyPerson table");
    $_SESSION["error_message"] = "CompanyPersonId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$companyPerson = new CompanyPerson($companyPersonId);

// 'updatearbitrary': update the arbitraryTitle column to value _REQUEST['arbitraryTitle']
// for the row in DB table companyPerson indicated by _REQUEST['companyPersonId'].
// We then fall through to the usual page content.
if ($act == 'updatearbitrary') {
    $v->rule('lengthMax', 'arbitraryTitle', 64); // maxlengthy 64 characters.

    if (!$v->validate()) {
        $errorId = '637387237003665605';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Arbitrary Title is too long. Maxlength is 64 characters."; //message for User.
    } else {
        $arbitraryTitle = isset($_REQUEST['arbitraryTitle']) ? $_REQUEST['arbitraryTitle'] : ''; // truncation is made in the CompanyPerson::setArbitraryTitle().

        $success = $companyPerson->update(array('arbitraryTitle' => $arbitraryTitle));

        if ( $success === false ) {
            $errorId = '637384600471076421';
            $logger->error2($errorId, 'update => arbitraryTitle: DB error.');
            $error = 'We could not Update the Arbitrary Title. Please contact an admin or developer'; // message for User.
        } else {
            // Redirect to reload this page: that's so that a refresh won't re-post.
            header("Location: " . $companyPerson->buildLink());
            die();
        }
        unset($arbitraryTitle, $success);
    }
}

// 'setBlockException': update the relevant row in DB table companyPerson
// with the new value for blockException. We then reload the page.
else if ($act == 'setblockexception') {
    $v->rule('lengthMax', 'blockException', 1024); // maxlength 1024 characters. Truncation for DB is made in the CompanyPerson::setBlockException(),
                                                   // but of course once we catch it here, truncation there will never be applied.
                                                   // NOTE that 'blockException' is not "required" in the sense used by our validator. That means it can
                                                   //  be blank, which is a meaningful value to set ("no exceptions").

    if (!$v->validate()) {
        $errorId = '637322375318760441';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Invalid input for BlockException. Maxlenght 1024 characters.";
    } else {
        $blockException = str_replace(" ", "",$_REQUEST['blockException']);
        $blockException = explode(",", $blockException);

        foreach($blockException as $block) {
            if (!preg_match('/^s[0-9]{7}$/', $block) && strlen($block) > 0) {
                $error = "Invalid input for BlockException. Not a comma-delimited list of *Job* numbers.";
                $logger->error2("637384546172332194", "Invalid input for BlockException, not a Job number : [$block]" );
            }
        }
        if (!$error) {
            $blockException = implode(",", $blockException); // put it back together as a single comma-separated string, no spaces.

            $success = $companyPerson->update(array('blockException' => $blockException)); // again, $blockException may be blank meaning "no exceptions"

            if (!$success) {
                $errorId = '637321394294040453';
                $logger->errorDb($errorId, 'update => blockException: DB error', $db);
                $error = "Update blockException failed.";
            } else {
                // Redirect to reload this page: that's so that a refresh won't re-post.
                header("Location: " . $companyPerson->buildLink());
                die();
            }
            unset($success, $blockException);
        }
    }
}

// 'addBlock': validate _REQUEST['billingBlockTypeId']. Assuming it is valid,
// insert a row in DB table billingBlock, using cleaned_request_addblock:
//  * _REQUEST['billingBlockTypeId'],
//  * _REQUEST['note']
// along with the current logged-in user as the person inserting this.
// We then reload the page.
else if ($act == 'addblock') {
    $billBlockTypesIds = array(); // Declare an array of billingBlockTypeId's.

    foreach ($billBlockTypes as $key => $value) { // $billBlockTypes is defined in inc/config.php. NOTE that one of the
                                                  // types is 'Block Removed'
        $billBlockTypesIds[] = $key; // Add in array valid billingBlockTypeId's. NOTE that we are grabbing keys, not values.
    }

    $v->rule('required', 'billingBlockTypeId'); // billingBlockTypeId value is required.
    $v->rule('integer', 'billingBlockTypeId');  // billingBlockTypeId value must be integer.
    $v->rule('in', 'billingBlockTypeId', $billBlockTypesIds); // billingBlockTypeId value must be one of the supported values.

    if (!$v->validate()) {
        $errorId = '637321419838480223';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters. Select a Block Type from list."; //message for user.
    } else {

        $billingBlockTypeId = $_REQUEST['billingBlockTypeId'];
        if (array_key_exists('note', $_REQUEST)) {
            $note = $_REQUEST['note'];
        }

        $cleaned_request_addblock = Array(
            'billingBlockTypeId' => $billingBlockTypeId,
            'note' => $note,
            'personId' => $user->getUserId()  // the current logged-in user as the person inserting this.
        );

        $success = $companyPerson->addBlock($cleaned_request_addblock, $error_is_db);

        if ($error_is_db) {
            $errorId = '637322304977487853';
            $logger->errorDb($errorId, 'Insert => billingBlock: DB error', $db);
            $error = 'We could not add a new Billing Block. Database Error.';
        } else if ( $success === false ) {
            $errorId = '637384590970766267';
            $logger->error2($errorId, 'addBlock method failed.');
            $error = 'We could not add a new Billing Block. Please check input!';
        } else {
            // Redirect to reload this page: that's so that a refresh won't re-post.
            header("Location: " . $companyPerson->buildLink());
            die();
        }
        unset($success, $billBlockTypesIds, $cleaned_request_addblock);
    }
}

$crumbs = new Crumbs($companyPerson, $user);

$contacts = $companyPerson->getContacts($error_is_db); // variable pass by reference in method.

if($error_is_db) { // true on query failed.
    $errorId = '637321533900679248';
    $error .= "</br> We could not display the Contacts. Database Error."; // message for user.
    $logger->errorDb($errorId, 'getContacts method failed. DB error', $db);
}

$errorContact = '';
$contactsError = array(); // array of individual query failure.

$use = array('email' => array(),'phone' => array(),'location' => array());

foreach ($contacts as $contact) {
    if (($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILPERSON) || ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILCOMPANY)) {
        $sorts['email'][] = $contact;
    }
    if (($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONEPERSON) || ($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONECOMPANY)) {
        $sorts['phone'][] = $contact;
    }
    if ($contact['companyPersonContactTypeId'] == CPCONTYPE_LOCATION){
        $sorts['location'][] = $contact;
    }
    $contactsError[] = $contact['typeError']; // Normally (no error) this is just a blank.
}

// array_filter in the following skips the blanks.
foreach (array_filter($contactsError) as $errorValue) {
    // The errors are Logged in the Class CompanyPerson::getContacts().
    $errorContact .=  " We could not display the $errorValue. Database Error. </br> "; // message for user.
}

include_once BASEDIR . '/includes/header.php';
?>
<style> .error { color: red;} </style>

<?php echo "<script>\ndocument.title = 'CP: ".
    str_replace(Array("'", "&nbsp;"), Array("\'", ' '),
        $companyPerson->getCompany()->getCompanyName() . '/' .
        $companyPerson->getPerson()->getFormattedName(0)
    ).
    "';\n</script>";

    if ($error) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
    }

    if ($errorContact) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorContact</div>";
    }

    unset($error, $errorContact, $contactsError);

?>
<div id="container" class="clearfix">
    <div class="main-content">
        <h1><?php echo $companyPerson->getName(); ?></h1>
        <a id="linkToCompany<?php echo $companyPerson->getCompany()->getCompanyId() ?>" href="<?php echo $companyPerson->getCompany()->buildLink(); ?>">See Company: <?php echo $companyPerson->getCompany()->getCompanyName(); ?></a>
        <br />
        <a id="linkToPerson<?php echo $companyPerson->getPerson()->getPersonId() ?>" href="<?php echo $companyPerson->getPerson()->buildLink(); ?>">See Person: <?php echo $companyPerson->getPerson()->getFormattedName(0); ?></a>
        <br />
        <hr>
        <center>
        <?php /* table inside a form; form to update the person's (arbitrary) title at the company */ ?>
        <form name="arbtitle" id="arbTitleForm" method="post" action="">
        <input type="hidden" name="companyPersonId" value="<?php echo intval($companyPerson->getCompanyPersonId()); ?>">
        <input type="hidden" name="act" value="updatearbitrary">;
        <table border="0" cellpadding="5" cellspacing="4" >
            <tr>
                <td colspan="2" >Title is for this person's relationship to this company:</td>
            </tr>
            <tr>
                <td>Arbitrary Title</td>
                <td>
                    <input type="text" class="form-control" id="arbitraryTitle" name="arbitraryTitle" value="<?php echo htmlspecialchars($companyPerson->getArbitraryTitle()); ?>" size="25" maxlength="64">
                </td>
                <td colspan="2"><input type="submit" class="btn btn-secondary mr-auto ml-auto" id="updateArbTitle" value="update arb title"></td>
            </tr>
        </table>
        </form>
        </center>
        <hr>
        <center>
        <p>&nbsp;</p>
        When this company + person is attached to a job or workorder, we will use the following contact info.<br/>
        Click 'Edit' below to select other known phones/email addresses/physical addresses for this company + person.
        <table border="0" cellpadding="5" cellspacing="4">
            <tr>
                <?php /* Icons here are not active, just indicate what data is below them
                         >>>00032: they would look better centered in their columns, but that
                         can be tricky for an image. */ ?>
                <td width="25%">
                    <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_phone.png" />
                </td>
                <td width="25%">
                    <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_mail.png" />
                </td>
                <td width="25%">
                    <img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_person_location.png" />
                </td>
            </tr>
            <tr>
                <td>
                    <?php /* Subtable with Phones for this companyPerson */ ?>
                    <table border="0" cellpadding="2" cellspacing="1">
                    <?php
                        if (isset($sorts['phone'])) {
                            foreach ($sorts['phone'] as $phone) {
                                echo '<tr>';
                                // BEGIN commented out by Martin before 2019
                                //	echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/companyperson.php?act=phone&companyPersonId=' . intval($companyPersonId) . '">' . $phone['dat'] . '</a></td>';
                                // END commented out by Martin before 2019
                                echo '<td>' . $phone['dat'] . '</td>';
                                echo '</tr>';
                            }
                        }
                    ?>
                    </table>
                </td>
                <td>
                    <?php /* Subtable with EmailAddresses for this companyPerson */ ?>
                    <table border="0" cellpadding="2" cellspacing="1">
                        <?php
                        if (isset($sorts['email'])){
                            foreach ($sorts['email'] as $email) {
                                echo '<tr>';
                                // BEGIN commented out by Martin before 2019
                                //	echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/companyperson.php?act=email&companyPersonId=' . intval($companyPersonId) . '">' . $email['dat'] . '</a></td>';
                                // END commented out by Martin before 2019
                                echo '<td>' . $email['dat'] . '</td>';

                                echo '</tr>';
                            }
                        }
                    ?>
                    </table>
                </td>
                <td>
                    <?php /* Subtable with physical addresses (Locations) for this companyPerson */ ?>
                    <table border="0" cellpadding="2" cellspacing="1">
                        <?php
                        if (isset($sorts['location'])){
                            foreach ($sorts['location'] as $location) {
                                echo '<tr>';
                                // BEGIN commented out by Martin before 2019
                                //	echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/companyperson.php?act=email&companyPersonId=' . intval($companyPersonId) . '">' . $email['dat'] . '</a></td>';
                                // END commented out by Martin before 2019
                                echo '<td>' . $location['dat'] . '</td>';

                                echo '</tr>';
                            }
                        }
                        ?>
                    </table>
                </td>
            </tr>
            <tr>
                <?php /* Link "edit" to edit the above in a fancybox */ ?>
                <td colspan="3" align="center"><center>
                <a id="linkIframeCp" data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/companyperson.php?companyPersonId=<?php echo intval($companyPersonId); ?>">Edit</a>
                </center></td>
            </tr>
        </table>
        </center>
        <?php
        // If logged-in user has Admin-level invoice permissions, they can
        // see whether there is a billing block on this companyPerson.
        $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);
        if ($checkPerm) {
        ?>
            <hr>
            <h2>Billing Block History</h2>
            <?php
            $errorBlock = "";
            $blocks = $companyPerson->getBillingBlocks($error_is_db); // an array of billing blocks.

            if ($error_is_db) {
                $errorId = '637384655952977804';
                $errorBlock = "We could not display the Billing Block History. Database Error."; // message for logged-in user with Admin-level.
                $logger->errorDb($errorId, $errorBlock . " => billingBlocks method failed.", $db);
            }
            if ($errorBlock) {
                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red; width: 50%;\">$errorBlock</div>";
            }
            unset($errorBlock);

            /* "Current Status :"
                * If there has never been a billingBlock for this user: "Not Blocked (no block history)" (on a green background)
                * If there has been a billingBlock for this user, but the latest relevant row in the billingBlock table has
                  typeId equal to BILLBLOCK_TYPE_REMOVEBLOCK: "Not blocked" (on a green background)
                * Otherwise (there is a current billingBlock): "Blocked for reason" (on a dull red background).
                  As of 2019-03, the only possible reason is "Previous Non Payment(s)".
            */
            $mostRecent = false;
            if (count($blocks)) {
                $mostRecent = $blocks[0];
            }

            if ($mostRecent) {
                echo 'Current Status :';
                if ($mostRecent['billingBlockTypeId'] != BILLBLOCK_TYPE_REMOVEBLOCK) {
                    echo '<span style="color:#eb5f5f">Blocked for ' . $billBlockTypes[$mostRecent['billingBlockTypeId']] . '</span>';
                } else {
                    echo '<span style="color:#2dcf25">Not Blocked</span>';
                }
            } else {
                echo '<span style="color:#2dcf25">Not Blocked (no block history)</span>';
            }

            /* Table with the following columns:
                * Block Type: currently either "Previous Non Payment(s)" or "Block removed".
                * Note: any text note associated with the block or removal.
                * Person: formatted name of the person who inserted (or removed) the block.
                * Time: date of block or removal in m/d/Y form (e.g. 1/15/2019).

              After the "normal" entries in the table, but still in the table, we have a blank row,
              and then a row for creating or removing a block. The row is wrapped in a self-submitting
              form with name="newblock". It uses the POST method, has a hidden act='addblock',
              and makes the following use of the table columns:
                * (Block Type) : an HTML SELECT, name="billingBlockTypeId". First option is just labeled '--'
                   and has a blank value. The others draw in the billBlockTypes in config.php; as of 2019-03,
                   that means the only values are 1, which displays as 'Previous Non Payment(s)', and
                   2, which displays as 'Block removed'.
                   * From inc/config.php:
                        define ("BILLBLOCK_TYPE_NONPAY_PREVIOUS",1);
                        define ("BILLBLOCK_TYPE_REMOVEBLOCK",2);
                        $billBlockTypes[BILLBLOCK_TYPE_NONPAY_PREVIOUS] = 'Previous Non Payment(s)';
                        $billBlockTypes[BILLBLOCK_TYPE_REMOVEBLOCK] = 'Block Removed';
                * (Note): a TEXTAREA, name="note"
                * (Person): a submit button, labeled "Add New"
                * (Time): (not used)
            */
            echo '<table border="0" cellpadding="5" cellspacing="2" >';
                echo '<tr>';
                    echo '<th>Block Type</th>';
                    echo '<th>Note</td>';
                    echo '<th>Person</th>';
                    echo '<th>Time</th>';
                echo '</tr>';
                foreach ($blocks as $block) {
                    echo '<tr>';
                        echo '<td>' . $billBlockTypes[$block['billingBlockTypeId']] . '</td>';
                        echo '<td>' . $block['note'] . '</td>';
                        $p = new Person($block['personId']);
                        echo '<td>' . $p->getFormattedName(1) . '</td>';
                        echo '<td>' . date("m/d/Y", strtotime($block['inserted'])) . '</td>';
                    echo '</tr>';
                }
                echo '<tr>';
                    echo '<td colspan="4">&nbsp;</td>';
                echo '</tr>';
                echo '<form name="newblock" id="newblock" action="" method="post">';
                echo '<input type="hidden" name="act" value="addblock">';
                echo '<tr>';
                    echo '<td><select class="form-control" id="billingBlockType" name="billingBlockTypeId">';
                    // BEGIN RESTORED JM 2020-10-26: We do not want a nonzero value selected by default,
                    //  and if there is no "empty" option that is what the browser has to do.
                    echo '<option value="0" selected disabled> -- </option>';
                    // END RESTORED JM 2020-10-26
                    foreach ($billBlockTypes as $btkey => $bbt) {
                        echo '<option  value="' . $btkey . '">' . $bbt . '</option>';
                    }
                    echo '</select></td>';
                    echo '<td><textarea class="form-control" id="billingBlock" rows="2" cols="30" name="note" maxlength="1024" ></textarea></td>';
                    echo '<td colspan="2"><input type="submit" class="btn btn-secondary mr-auto ml-auto" id="addNew" value="Add New"></td>';
                echo '</tr>';
                echo '</form>';
            echo '</table>';

            echo '<p>&nbsp;</p>';

            /* Outside the table: a smaller header "Block exceptions" and the explanation
               "comma-delimited list of *Job* numbers". This is another HTML form
               (though this time not table-formatted). No name, self-submitting using
               the POST method, with the following content:
                * (hidden) act='setblockexception'
                * a TEXTAREA, name="blockException", intialized to the current blockException
                  column value from the relevant row in DB table companyPerson.
                  May be blank ("no exceptions")
                * a "submit" button, labeled "update list"
            */
            echo '<h3>Block Exceptions</h3>';
            echo 'comma-delimited list of *Job* numbers<br>';
            echo '<form name="" action="" id="setblockexception" method="POST">';
            echo '<input type="hidden" name="act" value="setblockexception">';
            echo '<textarea class="form-control col-sm-4" cols="40" rows="4" id="blockException" name="blockException" maxlength="1024">' . htmlspecialchars($companyPerson->getBlockException()) . '</textarea>';
            echo '<br>';
            echo '<input type="submit" id="idblockexception" class="btn btn-secondary mr-auto ml-auto" value="update list">';
            echo '</form>';

        } // end if ($checkPerm)
        ?>
    </div>
</div>

<script>
$('#idblockexception').click(function(e) {
    $(".error").hide();
    hasError = false;

    var blockException = $('#blockException').val().trim(); // get the string

    if (blockException.length > 1024) {
        $("#blockException").after('<span class="error"> Invalid input. Limit of 1024 characters exceeded.</span>');
        e.preventDefault(); // added 2020-10-26 JM
        return false;
    }

    blockException = blockException.replace(/\s/g, '');  // remove all white spaces
    blockException = blockException.replace(/,$/, ""); //remove trailing comma if exists
    var array = blockException.split(',');

    <?php /* Validate form of each Job Number. We are client-side here, so we cannot validate that each Job Numbers exists */ ?>
    <?php /* >>>00001 JM 2020-11-13: (1) I changed "obj" to "jobNumber" for clarity. (2) Why the test for jobNumber.length > 0?
             What is the circumstance where we'd get an empty string in the array?
Cristi 2020-11-24: Example of string which generates an empty string in the array: "s2011001,,s2011002" after split will generate:
            array[0]='s2011001';
            array[1]=''; => -----empty string------
            array[2]='s2011002';
             (You can remove this remark once it's either
             explained or changed, I don't know whether there is a good reason for this check or not.) */ ?>
    $(array).each(function(i, jobNumber) {
        if( jobNumber.length > 0 & !jobNumber.match(/^s[0-9]{7}$/) ) {
            hasError = true;
        }
    });

    if (hasError) {
        $("#blockException").after('<span class="error"> Invalid comma-delimited list of *Job* numbers. Please check input.</span>');
        e.preventDefault(); // added 2020-10-26 JM
        return false;
    }
});

$('#blockException').on('mousedown', function() {
    $('.error').hide();
    }
);
</script>
<?php
include_once BASEDIR . '/includes/footer.php';
?>
