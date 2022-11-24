<?php
/*  invoice.php

    EXECUTIVE SUMMARY: PAGE. View or edit an invoice.
    A RewriteRule in .htaccess allows this to be invoked as just "invoice/foo" rather than "invoice.php?invoiceId=foo".

    Requires admin-level permission for invoice.

    NOTE that this has strong parallels to contract.php. >>>00001 There may be some potential to share common code.

    PRIMARY INPUT: $_REQUEST['invoiceId'].

    OPTIONAL INPUTs:
    * $_REQUEST['act']. Possible values: 'addagingnote', 'delinvoicefile', 'delworkorderfile', 'settextoverride', 'adjustment',
         'awaitDeliveryStatus', 'approvalRequired', 'addprofile', 'removeProfile', 'deleteadjust' (delete an adjustment),
         'updatetempNote' (added 2020-09-22 JM: this is the same note editable in workorder.php)
         'updateInvoiceNotes' (added 2020-09-22 JM: this note appears on the invoice PDF)
         'updateInvoice', 'commit' (which is 'updateInvoice' plus adding commitNotes & marking it as committed).
         Some of these require further inputs:
        * 'addagingnote' requires $_REQUEST['note'].
        * 'delinvoicefile' requires $_REQUEST['invoiceFileId'].
        * 'delworkorderfile' requires $_REQUEST['workOrderFileId'].
        * 'settextoverride' requires $_REQUEST['textOverride'].
        * 'adjustment' requires $_REQUEST['invoiceAdjustTypeId'], $_REQUEST['invoiceAdjustNote'], and $_REQUEST['invoiceAdjustAmount'].
        * 'awaitDeliveryStatus' and 'approvalRequired' do not require further inputs.
        * 'addprofile' and 'removeprofile' require $_REQUEST['billingProfileId'].
        * 'deleteadjust' requires $_REQUEST['invoiceAdjustId'].
        * 'updatetempNote' requires $_REQUEST['tempNote'].
        * 'updateInvoiceNotes' requires $_REQUEST['invoiceNotes'].
        * 'updateInvoice' and 'commit' require $_REQUEST['termsId'], $_REQUEST['invoiceDate'], $_REQUEST['nameOverride'],
            $_REQUEST['clientMultiplier'], and $_REQUEST['addressOverride'].
            * For 'updateInvoice' and 'commit', there are also other $_REQUEST parameters containing '::' in their keys,
              and related to workOrders/tasks. Some of these are arrays for workOrderTasks, though if I (JM) understand correctly,
              only the first value is significant. They are best understood by looking at the description of the form, below.

    Prior to 2020-09-11 "est"/"estimate" language was blindly carried over from the contract code; here these are *not* just estimates.
    So I've changed variables that previously had names like $estQuantity and $estCost to be $quantity and $cost. Sadly, we can't easily
    do the same in the data structure returned from function 'overlay'. - JM

*/

require_once './inc/config.php';
require_once './inc/perms.php';
require_once './includes/workordertimesummary.php'; // contains the single function insertWorkOrderTimeSummary
use Ahc\Jwt\JWT;
require_once __DIR__.'/vendor/autoload.php';
$jwt = new JWT(base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU"), 'HS256', 3600, 10);

/* [BEGIN MARTIN COMMENT]
[companyLocationId] => 609
[companyId] => 828
[locationId] => 918
[name] => PO box
[isPrimary] => 0
[addressType] => Billing
[status] => 1
[created] => 2014-09-11 00:00:00
[locationTypeId] => 1
)

[0] => Array
(
        [companyEmailId] => 670
        [companyId] => 828
        [emailAddress] => martin@allbsd.com
        [confirmed] => 1
        [displayorder] => 0
        [emailTypeId] => 1
)

[END MARTIN COMMENT]
*/

$checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_ADMIN);
if (!$checkPerm){
    // // No admin-level permission for contract, redirect to '/panther'
    header("Location: /panther");
    die();
}

$invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;

if (!intval($invoiceId)){
    // No valid invoiceId, redirects to '/'.
    // (No real reason to redirect to two different places.)
    header("Location: /");
    die();
}

$crumbs = new Crumbs(null, $user);
$invoice = new Invoice($invoiceId, $user);

if($invoice->getWorkOrderId()<=14174){
    $token = $jwt->encode([
        'user' => $user->getUsername()
    ]);
    header("Location: https://old.ssseng.com/redirectToken.php?url=".urlencode($_SERVER[REQUEST_URI])."&page=INV&token=".$token);
    die();
}

$totGen=$invoice->getInvoiceTotal();
$data = $invoice->getData();
foreach( $data[4] as $key=>$value){
    $res=$db->query("select tally from taskTally where workOrderTaskId=".$value['id']);
    if($res){
        $rows=$res->fetch_assoc();
        if($rows){
            $tally=$rows['tally'];
            $data['4'][$key]['tally']=$tally;
        }
    }
}
//print_r($data);
//die();

$out=reset($data);
//print_r($out);

/*
$parents=[];
$elements=[];

for( $i=0; $i<count($out); $i++ ) {
    if( $out[$i]['parentId']!=null ) {
        $parents[$out[$i]['parentId']]=1;
    }
    if( $out[$i]['taskId']==null)    {
        $elements[$out[$i]['elementId']] = $out[$i]['elementName'] ;
    }
}
*/


if (!intval($invoice->getInvoiceId())){
    // No valid invoiceId, redirects to '/'.
    header("Location: /");
    die();
}

$db = DB::getInstance();

$link = $invoice->buildLink();

// BEGIN ADDED 2020-05-22 JM, simplifies several things below
$invoiceStatusAwaitingDelivery = Invoice::getInvoiceStatusIdFromUniqueName('awaitingdelivery');
if (!$invoiceStatusAwaitingDelivery) {
    $errorId = '1646120900543';
    $logger->error2($errorId, "Invoice status 'awaitingdelivery' is undefined, serious problem, contact an administrator or developer. InvoiceId:".$invoiceId);
    $_SESSION["error_message"] = "Invoice status 'awaitingdelivery' is undefined, serious problem, contact an administrator or developer.";
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}
$invoiceStatusNeedsLookover = Invoice::getInvoiceStatusIdFromUniqueName('needslookoverbyeors');
if (!$invoiceStatusAwaitingDelivery) {
    // Invoice::getInvoiceStatusIdFromUniqueName will already have logged the problem
    $errorId = '1646120920498';
    $logger->error2($errorId, "Invoice status 'needslookoverbyeors' is undefined, serious problem, contact an administrator or developer. InvoiceId:" . $invoiceId);
    $_SESSION["error_message"] = " Invoice status 'needslookoverbyeors' is undefined, serious problem, contact an administrator or developer.";
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

// actions



if ($act == 'addagingnote') {
    print_r($_REQUEST);
    $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : '';
    $note = trim($note);

    if (strlen($note)){
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".agingNote(invoiceId, note, personId) VALUES (";
        $query .= intval($invoice->getInvoiceId());
        $query .= ", '" . $db->real_escape_string($note) . "'";
        $query .= ", " . intval($user->getUserId()) . ");";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1598632617', 'Hard DB error', $db);
        }
    }

    // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
    header("Location: " . $link);
    die();
}

if ($act == 'delinvoicefile') {
    $invoiceFileId = isset($_REQUEST['invoiceFileId']) ? intval($_REQUEST['invoiceFileId']) : 0;
    if (intval($invoiceFileId)) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".invoiceFile ";
        $query .= "WHERE invoiceId = " . intval($invoice->getInvoiceId()) . " ";
        $query .= "AND invoiceFileId = " . intval($invoiceFileId) . ";";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1598632726', 'Hard DB error', $db);
        }
    }

    // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
    header("Location: " . $link);
    die();
}

if ($act == 'delworkorderfile') {
    $workOrderFileId = isset($_REQUEST['workOrderFileId']) ? intval($_REQUEST['workOrderFileId']) : 0;
    if (intval($workOrderFileId)) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderFile ";
        $query .= "WHERE workOrderFileId = " . intval($workOrderFileId) . ";";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1600114313', 'Hard DB error', $db);
        }
    }

    // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
    header("Location: " . $link);
    die();
}

if ($act == 'settextoverride') {
    $textOverride = isset($_REQUEST['textOverride']) ? $_REQUEST['textOverride'] : '';
    $textOverride = trim($textOverride);

    $query = "UPDATE " . DB__NEW_DATABASE . ".invoice SET ";
    $query .= "textOverride = '" . $db->real_escape_string($textOverride) . "' ";
    $query .= "WHERE invoiceId = " . intval($invoice->getInvoiceId()) . ";";

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1598632806', 'Hard DB error', $db);
    }

    // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
    header("Location: " . $link);
    die();
}

if ($act == 'adjustment') {
    $invoiceAdjustTypeId = isset($_REQUEST['invoiceAdjustTypeId']) ? intval($_REQUEST['invoiceAdjustTypeId']) : 0;
    $invoiceAdjustNote = isset($_REQUEST['invoiceAdjustNote']) ? $_REQUEST['invoiceAdjustNote'] : '';
    $amount = isset($_REQUEST['invoiceAdjustAmount']) ? $_REQUEST['invoiceAdjustAmount'] : '';

    $invoiceAdjustNote = trim($invoiceAdjustNote);
    if (intval($invoiceAdjustTypeId) && is_numeric($amount)) {
        $invoice->addAdjustment($invoiceAdjustTypeId, $amount, $invoiceAdjustNote);
    }

    // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
    header("Location: " . $link);
    die();
}


if ($act == 'awaitDeliveryStatus') {
    $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceStatusTime (";
    $query .= "invoiceStatusId, invoiceId, personId";
    // REMOVED 2020-08-10 JM for v2020-4 // $query .= ", extra"; // vestigial in v2020-3
    $query .= ") VALUES (";
    $query .= intval($invoiceStatusAwaitingDelivery);
    $query .= ", " . intval($invoice->getInvoiceId());
    $query .= ", " . intval($user->getUserId());
    // REMOVED 2020-08-10 JM for v2020-4 // $query .= ", 0"; // vestigial in v2020-3
    $query .= ");";

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1598632885', 'Hard DB error', $db);
    }

    // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
    header("Location: " . $link);
    die();
}

if ($act == 'approvalRequired') {
    /* REPLACED 2020-05-22 JM, completely different approach to this: managers, not EORs, & customerPersonIds, not bitflags
    /// DO NOT DELETE until this is all sorted out - JM
    $invoiceStatusDataArray = Invoice::getInvoiceStatusDataArray(); // associative array of all invoice statuses, indexed by name of status

    $w = new WorkOrder($invoice->getWorkOrderId());
    $eors = $w->getTeamPosition(TEAM_POS_ID_EOR, false);
    $ids = array();
    foreach ($eors as $eor) {
        $cp = new CompanyPerson($eor['companyPersonId']);
        foreach ($invoiceStatusDataArray['needslookoverbyeors']['subs'] as $invoiceStatusData) {
            if ($cp->getPerson()->getPersonId() == $invoiceStatusData['misc']) {
                $ids[] = $invoiceStatusData['invoiceStatusId'];
            }
        }
    }

    $extra = 0;

    foreach ($ids as $iv) {
        $extra += $iv; // Really a bitwise "OR", but written as plua on the assumption that we can't get duplicates.
                       // >>>00026 $extra |= $iv would be a lot safer.
    }

    $invoice->setStatus($invoiceStatusNeedsLookover, $extra, 'from needs approval button');
    */
    // BEGIN REPLACEMENT 2020-05-26 JM, enchanced 2020-05-27 JM
    // Kluge: add everyone who is allowed to approve invoices
    // >>>00032 we still need to see if they are an EOR and already on the workorder,
    // because then we will select them by default
    $invoiceApproverCustomerPersonIds = $customer->getInvoiceApprovers(); // On success, these are customerPersonIds
    if ($invoiceApproverCustomerPersonIds===false) {
        $logger->error2('1590511961', 'Hard error getting invoiceApproverCustomerPersonIds');
        header("Location: /invoice/" . $invoiceId); // reload the page, hopeless >>>00002 handle this better? some sort of message
        die();
    }

    $w = new WorkOrder($invoice->getWorkOrderId());
    $eors = $w->getTeamPosition(TEAM_POS_ID_EOR, false); // These are associative arrays corresponding to rows in DB table Team
                                                         // Of particular interest is $eors[$i]['companyPersonId']
    $eorApprovers = array();

    foreach ($eors as $eor) {
        $companyPerson = new CompanyPerson($eor['companyPersonId']);
        $person = $companyPerson->getPerson();
        $personId = $person->getPersonId();
        $customerPerson = $customer->getCustomerPersonFromPersonId($personId);
        foreach ($invoiceApproverCustomerPersonIds as $invoiceApproverCustomerPersonId) {
            if ($customerPerson == $invoiceApproverCustomerPersonId) {
                $eorApprovers[] = $customerPerson;
            }
        }
    }
    $invoice->setStatus($invoiceStatusNeedsLookover, $eorApprovers, 'from needs approval button');

    // END REPLACEMENT 2020-05-26 JM

    header("Location: /workorder/" . $w->getWorkOrderId());
    die();
} // END if ($act == 'approvalRequired') {

if ($act == 'addprofile') {
    $billingProfileId = isset($_REQUEST['billingProfileId']) ? intval($_REQUEST['billingProfileId']) : 0;
    if (intval($billingProfileId)) {
        $b = new BillingProfile($billingProfileId);
        if (intval($b->getBillingProfileId())) {
            // THE FOLLOWING IS REWRITTEN 2020-10-30 JM using the now ShadowBillingProfile class
            $shadowBillingProfile = ShadowBillingProfile::constructFromBillingProfile($b);

            $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceBillingProfile (";
            $query .= "invoiceId, billingProfileId, shadowBillingProfile, companyPersonId";
            $query .= ") VALUES (";
            $query .= intval($invoice->getInvoiceId());
            $query .= ", " . intval($billingProfileId);
            $query .= ", '" . $db->real_escape_string($shadowBillingProfile->getShadowBillingProfileBlob()) . "'";
            $query .= ", " . intval($user->getUserId()) . ");";

            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1598632978', 'Hard DB error', $db);
            }

            // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
            header("Location: " . $link);
            die();
        }
    }
}

// BEGIN ADDED 2020-02-14 JM
if ($act == 'removeprofile') {
    $billingProfileId = isset($_REQUEST['billingProfileId']) ? intval($_REQUEST['billingProfileId']) : 0;
    if (intval($billingProfileId)) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".invoiceBillingProfile " .
                 "WHERE contractId=" . intval($invoice->getInvoiceId()) . " " .
                 "AND billingProfileId=" . intval($billingProfileId) . ";";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1598632922', 'Hard DB error', $db);
        }

        // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
        header("Location: " . $link);
        die();
    }
}
// END ADDED 2020-02-14 JM

// BEGIN ADDED 2020-09-22 JM
if ($act == 'updatetempNote') {
    // NOTE that the value edited here is in the workOrder, not the invoice itself.
    // Blank value here is meaningful
    $tempNote = isset($_REQUEST['tempNote']) ? trim($_REQUEST['tempNote']) : '';
    $array = Array('tempNote' => $tempNote);
    $workOrderId = $invoice->getWorkOrderId();
    $workOrder = new WorkOrder($workOrderId);
    $workOrder->update($array);
    // END REPLACEMENT 2020-09-22 JM

    // reload this page without the action inputs
    header("Location: " . $link);
    die();
}

if ($act == 'updateInvoiceNotes') {
    // Blank value here is meaningful
    $invoiceNotes = isset($_REQUEST['invoiceNotes']) ? trim($_REQUEST['invoiceNotes']) : '';
    $array = Array('invoiceNotes' => $invoiceNotes);
    $invoice->update($array);
    // END REPLACEMENT 2020-09-22 JM

    // reload this page without the action inputs
    header("Location: " . $link);
    die();
}
// END ADDED 2020-09-22 JM

if ($act == 'commit') {
    //Does nothing right now, but I'm pretty sure this needs to come back to life & do something - JM 2020-01-13
}

if ($act == 'deleteadjust') {
    $invoiceAdjustId = isset($_REQUEST['invoiceAdjustId']) ? intval($_REQUEST['invoiceAdjustId']) : 0;
    $invoice->deleteAdjustment($invoiceAdjustId);
    // reload this page without the action inputs (added 2020-09-30 JM)
    header("Location: " . $link);
    die();
}

if (($act == 'updateInvoice') || ($act == 'commit')) {
    $workOrderId = $invoice->getWorkOrderId();
    $workOrder = new WorkOrder($workOrderId);
    $termsId = isset($_REQUEST['termsId']) ? intval($_REQUEST['termsId']) : 0;
    $invoiceDate = isset($_REQUEST['invoiceDate']) ? $_REQUEST['invoiceDate'] : '';
    $nameOverride = isset($_REQUEST['nameOverride']) ? $_REQUEST['nameOverride'] : '';
    $clientMultiplier = isset($_REQUEST['clientMultiplier']) ? $_REQUEST['clientMultiplier'] : '';
    $addressOverride = isset($_REQUEST['addressOverride']) ? $_REQUEST['addressOverride'] : '';

    $invoice->update(array(
            'termsId' => $termsId,
            'invoiceDate' => $invoiceDate,
            'nameOverride' => $nameOverride,
            'addressOverride' => $addressOverride,
            'clientMultiplier' => $clientMultiplier,
            'IncrementEditCount' => 1
    )  );

    // other $_REQUEST parameters containing '::' in their keys, related to workOrders/tasks.
    // Some of these are arrays for workOrderTasks, though if I (JM) understand correctly, only
    // the first value is significant. They are best understood by looking at the form that submits them.
    header("Location: " . $link);
    die();
}  // END common code for 'updateInvoice' and 'commit' actions

if ($act == 'commit') {
    // [Martin comment:] this simply flags the most recent uncommitted entry in contract table for this work order as committed now
    $commitNotes = isset($_REQUEST['commitNotes']) ? $_REQUEST['commitNotes'] : '';
    $ret = $invoice->update(array('committed' => 1,'commitNotes' => $commitNotes));
    $invoice->storeTotal();
    // >>>00001 After a 'commit' (but not after 'updateInvoice'), we reload the
    //  *WorkOrder* page instead of falling through to display the Invoice page.
    // >>>00026 Is that intentional? Somewhat surprising.
    header("Location:" . $workOrder->buildLink());
    die();
}


// end actions


include_once BASEDIR . '/includes/header.php';
// Housekeeping to get job and invoice date.

$workOrder = new WorkOrder($invoice->getWorkOrderId());
echo "<script>\ndocument.title = 'Invoice: ". str_replace("'", "\'", $workOrder->getDescription())."';\n</script>\n"; // Add title

$job = new Job($workOrder->getJobId());
$invoiceDate = date_parse( $invoice->getInvoiceDate());
$invoiceDateField = '';

if (is_array($invoiceDate)) {
    if (isset($invoiceDate['year']) && isset($invoiceDate['day']) && isset($invoiceDate['month'])) {
        $invoiceDateField = intval($invoiceDate['month']) . '/' . intval($invoiceDate['day']) . '/' . intval($invoiceDate['year']);
        if ($invoiceDateField == '0/0/0'){
            $invoiceDateField = '';
        }
    }
}

// Get and unserialize any shadow billing profile
if (!strlen($invoiceDateField)) {
    // The name getBillingProfiles is a bit misleading: the return, if not an empty array,
    //  is a single-element array, containing an associative array with the canonical representation
    //  of a row from DB table invoiceBillingProfile (not BillingProfile);
    // JM 2020-10-30 I've accordingly renamed some variables here to try to be bit clearer what is going on,
    //  in the process of introducing the shadowBillingProfile class.
    $shadowBillingProfile = false;
    $invoiceBillingProfiles = $invoice->getBillingProfiles();
    if (count($invoiceBillingProfiles)) {
        $invoiceBillingProfile = $invoiceBillingProfiles[0];
        $shadowBillingProfile = new ShadowBillingProfile($invoiceBillingProfile['shadowBillingProfile']);
    }

    if ($shadowBillingProfile) {
        $dispatch = $shadowBillingProfile->getDispatch();
        if (intval($dispatch)){
            $invoiceDateField = date("n/j/Y",nextDate($dispatch));
        } else {
            $invoiceDateField = date("n/j/Y");
        }
    } else {
        $invoiceDateField = date("n/j/Y");
    }
}


// Get task types: overhead, fixed etc.
$allTaskTypes = array();
$allTaskTypes = getTaskTypes();


// calculate cost per element and total cost.
$wo = new WorkOrder($workOrder->getWorkOrderId());
$jobId = $wo->getJobId();
$job = new Job($jobId);

$workOrderId=$wo->getWorkOrderId();

$query = " SELECT e.elementId ";
$query .= " FROM " . DB__NEW_DATABASE . ".element e ";
$query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wo on wo.elementId = e.elementId ";
$query .= " WHERE jobId = " . intval($jobId) ." group by e.elementId ";

$result = $db->query($query);

$errorElements = '';
if (!$result) {
    $errorId = '637798491724341928';
    $errorElements = 'We could not retrive the cost for the elements. Database error. Error id: ' . $errorId;
    $logger->errorDb($errorId, 'We could not retrive the elements for this job', $db);

}
if ($errorElements) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorElements</div>";
}


$allElements = [];
if(!$errorElements) {
    while ($row = $result->fetch_assoc()) {
        $allElements[] = $row['elementId'];
    }
}

unset($errorElements);

$sumTotalEl = $invoice->getTotal();

$wData=$invoice->getData();

$elementsCost = [];
$errorCostEl = '';
foreach($wData[4] as $key=>$value)
{
    $taskId=$value['id'];
    if($value['id']==$value['elementId']){
        $elementsCost[$value['id']]=intval($value['totCost']);
    }
}

//print_r($elementsCost);

/*foreach($allElements as $value) {



        $query = "select workOrderTaskId,
        parentTaskId, totCost
        from    (select * from workOrderTask where internalTaskStatus=5
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$value') initialisation
        where   find_in_set(parentTaskId, @pv) and parentTaskId = '$value' and workOrderId = '$workOrderId'
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";

        $result = $db->query($query);
        if (!$result) {
            $errorId = '637798493011752921';
            $errorCostEl = 'We could not retrive the total cost for each Element. Database error. Error id: ' . $errorId;
            $logger->errorDb($errorId, 'We could not retrive the total cost for each Element', $db);
        }

        if(!$errorCostEl) {
            while( $row=$result->fetch_assoc() ) {
                $elementsCost[$row['parentTaskId']][] = $row['totCost'];

            }
        }

}

if ($errorCostEl) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorCostEl</div>";
}


unset($errorCostEl);

foreach($elementsCost as $key=>$el) {
    $elementsCost[$key] = array_sum($el);
    $sumTotalEl += array_sum($el);
}
*/


// get Ids of Level One WOT
$dataInvoice=$invoice->getData();

$levelOne=[];

foreach($dataInvoice[4] as $key=>$value)
{
    if($value['parentTaskId']==$value['elementId'] && $value['hasChildren']) // level 1 task
    {
        $levelOne[]=strval($value['id']);
    }
}
//$levelOne  = $workOrder->getLevelOne($error_is_db);
/*$errorWotLevelOne = "";
if($error_is_db) { //true on query failed.
    $errorId = '637799252773313187';
    $errorWotLevelOne = "Error fetching Level One WorkOrderTasks. Database Error. Error Id: " . $errorId; // message for User
    $logger->errorDB($errorId, "getLevelOne() method failled", $db);
}

if ($errorWotLevelOne) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWotLevelOne</div>";
}
unset($errorWotLevelOne);
 */

$levelOneTasks = [];
$errorWotLevelOneTasks = "";
foreach($levelOne as $value) {

    foreach($wData[4] as $k=>$v){
        if($v['id']==$value && $v['taskContractStatus']==9){
            $levelOneTasks[] = $value;
            continue;
        }
    }

}

if ($errorWotLevelOneTasks) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWotLevelOneTasks</div>";
}
unset($errorWotLevelOneTasks);
?>





<div id="dialog">
</div>
<div style="display:none">
    <div id="data">
        <table border="1" cellpadding="5" cellspacing="3">
            <tr>
                <td align="left" colspan="2">
                Please add a comment as to why this commit of the invoice was made.
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <textarea name="cn" id="cn" cols="40" rows="10">
                    </textarea>
                </td>
            </tr>
            <tr>
                <td style="text-align:center;"><button type="button" id="cancelFormButton" onClick="cancelForm();">Cancel</button> </td>
                <td style="text-align:center;"><button type="button" id="commitFormButton" onClick="commitForm();">Commit</button> </td>
            </tr>
        </table>
    </div>
</div>

<div id="container" class="clearfix">
<?php
        $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/invoice/' . rawurlencode($invoiceId);
    ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
            [WO]&nbsp;<a id="linkWoToCopy" href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            (Iv)&nbsp;Invoice&nbsp;(<a href="<?php echo $invoice->buildLink(); ?>"><?php echo $invoice->getInvoiceId();?></a>)
            <button id="copyLink" title="Copy Invoice link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>
        <span id="linkToCopy" style="display:none"> (Iv)<a href="<?php echo $invoice->buildLink(); ?>">&nbsp;Invoice&nbsp;(<?php echo $invoice->getInvoiceId();?></a>)&nbsp;[WO]&nbsp;<a href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a></span>

        <span id="linkToCopy2" style="display:none"> <a href="<?= $urlToCopy?>">(Iv)&nbsp;Invoice&nbsp;<?php echo $invoice->getInvoiceId();?>
            [WO]&nbsp; <?php echo $workOrder->getDescription();?> </a></span>
    </div>
    <div class="clearfix"></div>
    <div class="main-content" style="padding-top: 1%!important;">
    <?php
    // BEGIN ADDED 2020-09-15 JM addressing http://bt.dev2.ssseng.com/view.php?id=238
    $committedWithoutProfile = false;
    if ($invoice->getCommittedNew() && !count($invoice->getBillingProfiles())) {
        echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"no-profile-warning\" style=\"color:red; font-weight:bold;\">" .
        "This invoice was committed without a billing profile. You will probably want to go ".
        "<a id=\"linkBackWo\" href=\"{$workOrder->buildLink()}\"> back to Work Order</a> and change the invoice status.<br></div>";
        $committedWithoutProfile = true;
    }
    // END ADDED 2020-09-15 JM
    $needProfile = false;
    if (!$invoice->getCommittedNew()) {
        $bps = $invoice->getBillingProfiles();
        if (!count($bps)){
            $needProfile = true;
        }
    }

    if ($needProfile) {
        $tp = $workOrder->getTeamPosition(TEAM_POS_ID_CLIENT, 0, 1);
        if (count($tp)) {
            // we have a client
            $client = $tp[0];
            $cp = new CompanyPerson($client['companyPersonId']);
            if (intval($cp->getCompanyPersonId())) {
                $cmp = $cp->getCompany();
                $cbps = $cmp->getBillingProfiles(true); // true => active only, added 2020-02-13 JM
                $numActiveCbps = count($cbps);

                // In the case where there is only one active billing profile, we use that without asking.
                if ($numActiveCbps == 1) {
                    // Exact equivalent of act=addprofile case
                    $billingProfileId = $cbps[0]['billingProfile']->getBillingProfileId();
                    $b = new BillingProfile($billingProfileId);
                    $shadowBillingProfile = ShadowBillingProfile::constructFromBillingProfile($b);

                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceBillingProfile (";
                    $query .= "invoiceId, billingProfileId, shadowBillingProfile, companyPersonId";
                    $query .= ") VALUES (";
                    $query .= intval($invoice->getInvoiceId()) ;
                    $query .= ", " . intval($billingProfileId);
                    $query .= ", '" . $db->real_escape_string($shadowBillingProfile->getShadowBillingProfileBlob()) . "'";
                    $query .= ", " . intval($user->getUserId());
                    $query .= ");";

                    $result = $db->query($query);
                    if ($result) {
                        $needProfile = false;
                    } else {
                        $logger->errorDb('1581714562', 'Could not insert unique billing profile into invoice', $db);
                    }
                    $invoice->update(array(
                        "termsId" => $b->getTermsId()
                    ));
                }
            }
        }
    }

    if ($needProfile) {
        // There is no billing profile associated with this invoice, and we weren't able to solve this by picking a unique one just above.
        if (!count($tp)) {
            echo '<p>There is nobody on the workorder or job team in the role of "client" '.
                 'so there are no Billing Profile Templates to refer back to. ' .
                 'Go <a id="backWoLink" href="'.$workOrder->buildLink().'"> back To Work Order</a> and add a client in the applicable place!<p>';
                 // >>>00032 ought to link to those applicable places.
        } else if (intval($cp->getCompanyPersonId())) {
        ?>
            <div class="full-box clearfix">
                <h1>WorkOrder: <?php echo $workOrder->getDescription();?></h1>
                <a id="linkWo'.<?php echo $workOrder->getWorkOrderId()?>.'" href="<?php echo $workOrder->buildLink(); ?>">[Back To Work Order]</a>
                <br/><br/>
                <p>You need to attach a billing profile template to this invoice:</p>
                <?php
                // Checks whether there is a client associated with this workOrder.
                // Ignores any that are not "active" per WorkOrder::getTeamPosition.
                // $cp was set above, as part of seeing if there was a unique profile
                if (intval($cp->getCompanyPersonId())) {
                    // $cmp, $cbps, $numActiveCbps were set above, as part of seeing if there was a unique profile
                    if ($numActiveCbps == 0) {
                        echo '<p>The client\'s company, ' . $cmp->getCompanyName() . ', has no billing profile templates.<br />' . "\n";
                        echo '<a id="addBilling' . $cmp->getCompanyId() . '" data-fancybox-type="iframe" class="button fancyboxIframe" ' .
                             'href="/fb/addbillingprofile.php?companyId=' . $cmp->getCompanyId() . '">Add billing profile</a>' . "\n";
                    } else {
                        $primaryBillingProfileId = $cmp->getPrimaryBillingProfileId();

                        // There is at least one such billing profile.
                        // "Choose one", followed by a table with the indicated columns, and a row for each such billing profile.
                        echo '<p>Choose One</p>';
                        echo '<table border="0">';
                            echo '<tr>';
                                echo '<th>Name</th>';
                                echo '<th>Location</th>';
                                echo '<th>&nbsp;</th>';
                            echo '</tr>';
                            foreach ($cbps as $cbp) {
                                echo '<tr>';
                                    $billingProfileId = $cbp['billingProfile']->getBillingProfileId();
                                    $cpid = $cbp['billingProfile']->getCompanyPersonId();
                                    $isPrimary = $billingProfileId == $primaryBillingProfileId;
                                    // "Name": Formatted name of person associated with this billing profile
                                    echo '<td>';
                                        if (intval($cpid)) {
                                            $cp = new CompanyPerson($cpid);
                                            $p = $cp->getPerson();
                                            echo $p->getFormattedName(1);
                                        }
                                    echo '</td>';
                                    // "Location": location ('loc') associated with this billing profile
                                    echo '<td>';
                                        echo $cbp['loc'];
                                    echo '</td>';
                                    // (no heading): self-submitting link labeled "use this". Link uses GET method,
                                    //  self-submitting to this page by using $link with query string.
                                    echo '<td><a id="addProfile'.$cbp['billingProfile']->getBillingProfileId() . '"  href="' . $link . '?act=addprofile&billingProfileId=' .
                                         $cbp['billingProfile']->getBillingProfileId() . '">Use this ' .
                                         ($billingProfileId == $primaryBillingProfileId ? ' (DEFAULT)' : '')  .
                                         ' </a></td>';
                                echo '</tr>';
                            }
                        echo '</table>';
                    }
                }

            ?>
            </div>
        <?php
        }
    } else {
        $editCount = $invoice->getEditCount(); // MOVED from below, separate from whether there is a billing profile
        // BEGIN ADDED 2020-09-15 JM addressing http://bt.dev2.ssseng.com/view.php?id=238
        if ($committedWithoutProfile) {
            // >>>00026 we have to decide what we will want to do about this. Maybe allow adding a billing profile to a committed invoice?
            $loc = 'NO BILLING PROFILE => NO ADDRESS';
            $formClientMultiplier = $invoice->getClientMultiplier();
        } else {
        // END ADDED 2020-09-15 JM addressing http://bt.dev2.ssseng.com/view.php?id=238

            // There *is* a billing profile associated with this invoice.

            // [BEGIN Martin comment]
            // logic needs to go here regarding the editCount.
            // so if its been updated before then the editCount goes up
            // and the contract no longer looks to the billing profile template
            // for the meta data that is contained in the billing profile template.
            // it just goes with whatever is stored in the "data" blob for the contract
            // [END Martin comment]

            // The following is reworkedc 2020-11-05 JM to use ShadowBillingProfile object
            $bps = $invoice->getBillingProfiles();
            $shadowBillingProfile = new ShadowBillingProfile($bps[0]['shadowBillingProfile']);

            // [BEGIN Martin comment]
            // WHY IS EDIT COUNT GOING UP BY  2 DURING EVERY UPDATE... the update is called twice for some rweason
            // [END Martin comment]
            // JM: Despite Martin's concern about editCount not being correctly maintained,
            //  it is at least correctly zero or non-zero, and can therefore be used as a Boolean to determine
            //  whether we get certain values (termsId, multiplier, location) from the invoice itself
            //  (if editCount is nonzero) or from the billing profile. I'm pretty sure that's all we care about it.

            $formTermsId = 0;
            if (intval($editCount) > 1) {  // [Martin comment:] starts at 1 due to invoice being creted on workorderpage
                $formTermsId = $invoice->getTermsId();
            } else {
                $formTermsId = $shadowBillingProfile->getTermsId();
            }

            $formClientMultiplier = 0;
            if (intval($editCount)){
                $formClientMultiplier = $invoice->getClientMultiplier();
            } else {
                $formClientMultiplier = $shadowBillingProfile->getMultiplier();
            }

            $loc = '';
            $ccn = '';
            $cc = new Company($shadowBillingProfile->getCompanyId());

            $ccn = $cc->getCompanyName();
            $ccn = trim($ccn);
            if (((substr($ccn, 0, 1) == '[') && (substr($ccn,-1) == ']'))) {
                $ccn = substr($ccn, 1);
                $ccn = substr($ccn, 0, (strlen($ccn) - 1));
            }
            if (!strlen($loc)) {
                $loc = $ccn;
            }
            if (intval($shadowBillingProfile->getCompanyPersonId())) {
                $cp = new CompanyPerson($shadowBillingProfile->getCompanyPersonId());
                if (intval($cp->getCompanyPersonId())) {
                    $pp = $cp->getPerson();
                    if (strlen($loc)){
                        $loc .= "\n";
                    }
                    $loc .= $pp->getFormattedName(1);
                }
            }

            if (intval($shadowBillingProfile->getPersonEmailId())) {
                $query = "SELECT * FROM  " . DB__NEW_DATABASE . ".personEmail ";
                $query .= "WHERE personEmailId = " . intval($shadowBillingProfile->getPersonEmailId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0){
                        $row = $result->fetch_assoc();
                        if (strlen($loc)) {
                            $loc .= "\n";
                        }
                        $loc .= $row['emailAddress'];
                    }
                } else {
                    $logger->errorDb('1598633424', 'Hard DB error', $db);
                }
            }

            if (intval($shadowBillingProfile->getPersonLocationId())) {
                $query = "SELECT locationId FROM  " . DB__NEW_DATABASE . ".personLocation " .
                $query .= "WHERE personLocationId = " . intval($shadowBillingProfile->getPersonLocationId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        if (strlen($loc)){
                            $loc .= "\n";
                        }
                        $l = new Location($row['locationId']);
                        $loc .= $l->getFormattedAddress();
                    }
                } else {
                    $logger->errorDb('1598633562', 'Hard DB error', $db);
                }
            }

            if (intval($shadowBillingProfile->getCompanyEmailId())) {
                $query = "SELECT * FROM  " . DB__NEW_DATABASE . ".companyEmail ";
                $query .= "WHERE companyEmailId = " . intval($shadowBillingProfile->getCompanyEmailId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        if (strlen($loc)){
                            $loc .= "\n";
                        }
                        $loc .= $row['emailAddress'];
                    }
                } else {
                    $logger->errorDb('1598633676', 'Hard DB error', $db);
                }
            }

            if (intval($shadowBillingProfile->getCompanyLocationId())) {
                $query = "SELECT * FROM  " . DB__NEW_DATABASE . ".companyLocation ";
                $query .= "WHERE companyLocationId = " . intval($shadowBillingProfile->getCompanyLocationId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        if (strlen($loc)){
                            $loc .= "\n";
                        }
                        $l = new Location($row['locationId']);
                        $loc .= $l->getFormattedAddress();
                    }
                } else {
                    $logger->errorDb('1598633761', 'Hard DB error', $db);
                }
            }
        } // END has profile

        $addressString = '';
        if (intval($editCount) > 1) {
            $addressString = $invoice->getAddressOverride();
        } else {
            $addressString = $loc;
        }
    ?>
    <?php /* --- Invoice page header image, floated to the right; header: "Invoice:" + workOrder description. */ ?>
    <img style="float:right;" src="/cust/<?php echo $customer->getShortName(); ?>/img/pageheaderimages/invoice_page_header_image.gif" width="32" height="32" border="0">

    <h3>Invoice: <?php echo $workOrder->getDescription();?></h3>

    <?php /* --- "[Back To Work Order]", linking to the workOrder page */ ?>
    <a id="workOrderLink" href="<?php echo $workOrder->buildLink(); ?>" class="btn btn-sm btn-secondary text-light">Back To Work Order</a>

    <div class="full-box clearfix">
        <?php
            // [ BEGIN MARTIN COMMENT]
            // get committed here is just checking the current statusid
            // to make sure its prior in the process than "committed" used to be
            // just trying not to modify much code here
            // [ END MARTIN COMMENT]

            // $editable = intval(!$invoice->getCommittedNew()); // COMMENTED OUT BY MARTIN BEFORE 2019

            // [ BEGIN MARTIN COMMENT]
            // there are 2 of these kludges   1

            // KLUDGE TO BRING INVOICES INTO LINE
            // 2018-07-26 ... this needs to be reversed out
            // after the kludging is done
            // [ END MARTIN COMMENT]
            $editable = 1;
            $canProceed = true;
            if (!$canProceed) {
                // >>>00001 at least for the moment, can't happen. Looks like this related to commented-out code
                // counting the clients.
                echo '<h2>Cannot Proceed! (perhaps too many Clients)</h2>';
            } else {
                /* --- Button, labeled "Print", linking to invoicepdf.php and passing invoiceId */
                echo '<a id="printInv'.$invoice->getInvoiceId().'" class="button print show_hide"  href="/invoicepdf.php?invoiceId=' . $invoice->getInvoiceId()  . '">Print</a>';
                ?>
                <?php /* table inside a form; >>>00006 hidden inputs really should go outside the table
                         Self-submitting form "invoiceform", using POST method:
                             * (hidden) act="updateInvoice"
                             * (hidden) commitNotes; these are edited indirectly via a form that is made visible when you click "Commit"
                             * Job #: Job Number
                             * Job Name Override: if editable, a text input, name="nameOverride"; otherwise, just text.
                               Initialized with nameOverride from invoice.
                             * Date: if editable, a text input (name="invoiceDate") with a date picker, otherwise just text.
                               In "n/j/Y" form (month/day/year, no leading zeroes, e.g. '6/14/2019').
                               Initialized from invoice; if nothing there, initialized to current date.
                          DESCRIPTION CONTINUES BELOW, THERE IS A LOT IN THIS FORM.
                  */ ?>
                <div class="container-fluid">
                    <form name="invoice" id="invoiceform" action="<?php echo $link; ?>" method="post" style="margin-top: 50px;">
                        <input type="hidden" name="act" value="updateInvoice">
                        <input type="hidden" name="commitNotes" value="">
                        <div class="row mt-5">
                            <label for="staticEmail" class="col-sm-2 col-form-label">Job#</label>
                            <div class="col-sm-4">
                                <?php echo htmlspecialchars($job->getNumber()); ?>
                            </div>
                        </div>
                        <div class="row mt-1">
                            <label for="nameOverride" class="col-sm-2 col-form-label">Job Name Override</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control form-control-sm" id="nameOverride" name="nameOverride" value="<?php echo htmlspecialchars($invoice->getNameOverride()); ?>">
                            </div>
                        </div>
                        <div class="row mt-1">
                            <label for="invoiceDate" class="col-sm-2 col-form-label">Date</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control form-control-sm" id="invoiceDate" name="invoiceDate" value="<?php echo htmlspecialchars($invoiceDateField); ?>">
                            </div>
                        </div>

                        <div class="row mt-1">
                            <label for="termsId" class="col-sm-2 col-form-label">Terms</label>
                            <div class="col-sm-5">
                                <?php
                                    if ($editable) {
                                ?>
                                    <select class="form-select form-control  form-control-sm" aria-label="Select Terms" id="termsId" name="termsId">
                                        <option value="0"></option><?php
                                        $terms = getTerms();
                                        //print_r($terms);
                                        foreach ($terms as $term) {
                                            $selected = (intval($formTermsId) == intval($term['termsId'])) ? ' selected ' : '';
                                            echo '<option value="' . intval($term['termsId']) . '" ' . $selected . '>' . $term['termName'] . '</option>';
                                        }
                                    ?>
                                    </select>
                                <?php } else {
                                        $terms = getTerms();
                                        print_r($terms);
                                        foreach ($terms as $term) {
                                            if (intval($formTermsId) == intval($term['termsId'])){
                                                echo $term['termName'];
                                            }
                                        }
                                        ?>
                                        <input type="hidden" name="termsId" value="<?php echo htmlspecialchars($formTermsId); ?>">
                                        <?php
                                } ?>
                            </div>
                        </div>
                        <div class="row mt-1">
                            <label for="clientMultiplier" class="col-sm-2 col-form-label">Client Mult.</label>
                            <div class="col-sm-4">
                                <?php echo htmlspecialchars($formClientMultiplier); ?>
                            </div>
                        </div>
                        <div class="row mt-1">
                            <label for="addressOverride" class="col-sm-2 col-form-label">Address</label>
                            <div class="col-sm-4">
                                <?php
                                    if ($editable) {
                                        echo '<textarea id="addressOverride" name="addressOverride" cols="30" rows="3" class="form-control form-control-sm">' . htmlspecialchars($addressString) . '</textarea>';
                                    } else {
                                    echo '<td bgcolor="#cccccc"><pre>';
                                        echo $addressString;
                                    echo '</pre></td>';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="row mt-3">

                                <div id="gantt" class="clearfix" ></div>
                        </div>
                </div>

<script src='/js/kendo.all.min.js' ></script>
<link rel="stylesheet" href="../styles/kendo.common.min.css" />
<link rel="stylesheet" href="../styles/kendo.material-v2.min.css" />
<script src='https://cdnjs.cloudflare.com/ajax/libs/jeditable.js/1.7.3/jeditable.min.js'> </script>
<link rel="stylesheet" href="https://kendo.cdn.telerik.com/2021.2.616/styles/kendo.default-v2.min.css" />


<style type="text/css">
    .thistable, th, td {
    border: 0px;
    }
    .forminput {
        width: 100%;
    }
    .formspan {
        display: block;
        overflow: hidden;
        padding-right:10px;
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
    .table td {
        vertical-align: middle;
    }
    .form-control {
        width: auto;
    }
</style>

<style>
    @media screen and (max-width: 680px) {
        .treeview-flex {
            flex: auto !important;
            width: 100%;
        }
    }
    .workorderClick
    {
        text-transform:capitalize;
        padding:25px;
        font-size:15px;
        line-height:2.5;
        font-weight: 600;
    }

    #treeview .k-sprite {
        /*background-image: url("https://demos.telerik.com/kendo-ui/content/web/treeview/coloricons-sprite.png"); */
    }

    .folder { background-position: 0 -16px; }
    .html { background-position: 0 -48px; }
    body {
        background-image: url("");
    }

    /* Changes on popup Edit Mode */
    .k-button-primary, .k-button.k-primary {
        color: #fff;
        background-color: #3fb54b;
        font-size: 12px;
    }
    .k-window-titlebar {
        padding: 8px 6px;
    }
    /* End changes on popup Edit Mode */
    .telerik-icon {
        margin-left: 5px;
    }
    #treeview-kendo > ul > li > div > span > span.k-icon.k-i-close.telerik-icon {
        display:none!important;
    }



    .treeInlineEdit > input
    {
        font-size: 1.5em;
        min-width: 10em;
        min-height: 2em;
        border-radius: 5px 5px 5px 5px;
        -moz-border-radius: 5px 5px 5px 5px;
        border: 0px solid #ffffff;
    }


    #myCustomDescription {
        height:100%;
        line-height: 1.3;
    }
    #gantt .k-grid-header
    {
    padding: 0 !important;
    }

    #gantt .k-grid-content
    {
    overflow-y: visible;
    }
    .k-gantt-header  {
        display:none;
    }
    .k-gantt-footer {
        display:none;
    }
    .k-gantt-timeline{
        display:none;
    }
   /* .k-gantt-treelist {
        width: 100%!important;

    }*/


    .k-grid tbody button.k-button {
        min-width: 20px;
        border: 0px solid #fff;
        background: transparent;
    }

    #treeview-telerik-wo {
        display:none!important;
    }

    /* Header padding */
    .k-gantt-treelist .k-grid-header tr {
        height: calc(2.8571428571em + 4px);
        vertical-align: bottom;
    }

    .k-gantt .k-treelist .k-grid-header .k-header {
        padding-left: calc(0.8571428571em + 6px);
    }
    /* Header padding */

   .k-command-cell>.k-button, .k-edit-cell>.k-textbox, .k-edit-cell>.k-widget, .k-grid-edit-row td>.k-textbox, .k-grid-edit-row td>.k-widget {
        vertical-align: middle;
        background-color: #fff;
    }

    .k-scheduler-timelineWeekview > tbody > tr:nth-child(1) .k-scheduler-table tr:nth-child(2) {
    display: none!important;
    }

    /* Extradescription in two rows */
    .k-grid  td {
        height: auto;
        white-space: normal;
    }
    .no-scrollbar .k-grid-header
    {
    padding: 0 !important;
    }

    .no-scrollbar .k-grid-content
    {
        overflow-y: visible;
    }


    /* Hide the Horizonatal bar scroll */
    .k-gantt .k-treelist .k-grid-content {
       /* overflow-y: hidden;*/
        overflow-x: hidden;
    }

    /* Hide the Vertical bar */
    .k-gantt .k-splitbar {
        display: none;
    }
    .k-gantt-treelist .k-i-expand,
    .k-gantt-treelist .k-i-collapse {
        cursor: pointer;
    }
    /* Horizontal Scroll*/
    #gantt .k-grid-content {
       /* overflow-y: hide!important;*/
    }
    .k-i-cancel:before {
        content: "\e13a"; /* Adds a glyph using the Unicode character number */
    }


  </style>

<style>
div.textwrapperNotes {

  height: 240px;
  overflow: auto;
}

/* Gantt */


#gantt .k-grid-header
{
padding: 0 !important;
}

#gantt .k-grid-content
{
overflow-y: visible;
}
.k-gantt-header  {
    display:none;
}
.k-gantt-footer {
    display:none;
}
.k-gantt-timeline{
    display:none;
}

.k-grid tbody button.k-button {
    min-width: 20px;
    border: 0px solid #fff;
    background: transparent;
}

.k-grid .k-button {
    padding-left: calc(0.61428571em + 6px);
}

#treeview-telerik-wo {
    display:none!important;
}

/* Header padding */
.k-gantt-treelist .k-grid-header tr {
    height: calc(2.8571428571em + 4px);
    vertical-align: bottom;
}

.k-gantt .k-treelist .k-grid-header .k-header {
    padding-left: calc(0.8571428571em + 6px);
}
/* Header padding */

.k-command-cell>.k-button, .k-edit-cell>.k-textbox, .k-edit-cell>.k-widget, .k-grid-edit-row td>.k-textbox, .k-grid-edit-row td>.k-widget {
    vertical-align: middle;
    background-color: #fff;
}

.k-scheduler-timelineWeekview > tbody > tr:nth-child(1) .k-scheduler-table tr:nth-child(2) {
    display: none!important;
}

/* Extradescription in two rows */
.k-grid  td {
    height: auto;
    white-space: normal;
}
.no-scrollbar .k-grid-header
{
padding: 0 !important;
}

/* #gantt > div.k-gantt-content > div.k-gantt-treelist > div > div.k-grid-header > div > table {
    min-width: 1299.6px!important;
}
.k-grid-header-wrap {
    max-width: 1299.6px!important;
} */
.k-grid-header {
    background-color: grey;
}

.no-scrollbar .k-grid-content
{
    overflow-y: visible;
}


/* Hide the Horizonatal bar scroll */
.k-gantt .k-treelist .k-grid-content {
    overflow-y: hidden;
    overflow-x: hidden;
}

/* Hide the Vertical bar */
.k-gantt .k-splitbar {
    display: none;
}
.k-gantt-treelist .k-i-expand,
.k-gantt-treelist .k-i-collapse {
    cursor: pointer;
}
/* Horizontal Scroll*/



.k-gantt .k-grid-content {
    overflow-y: visible !important;


}

.k-gantt .k-gantt-layout {
    height: 140% !important;

}
.k-auto-scrollable {
    height:499px!important;

}
.k-grid-content table, .k-grid-content-locked table {
    min-width: 701.513px!important;
}

.statusWoTaskColor {
    background-color: #4ca807; padding: 1px 10px;
}

.statusWoTaskColor2 {
    background-color: #c4370a; padding: 1px 10px;
}
.statusWoTaskColorNone {
    display:none;
}
.tree-list-img {
    width: 40px;
    height: 40px;
}

.k-i-comment, .k-i-edit-tools {

    cursor:pointer;
}


#gantt  > table {
    max-width: 1298px!important;
}

/* Contract and Invoice Notes */
.float-container {
border: 0.1px solid #fff;
padding-top:10px;

}

.float-child {
    width: 50%;
    float: left;
    padding: 0px;
    border: 1px solid #c6c6c6;
}

.k-i-arrow-right {
    font-size:35px;
}
.statusTask2 {
    transform: rotate(90deg);
}

.statusTask {
    transform: rotate(0deg);
}
.formClass {
    height: auto!important;
    padding: 8px;
}

.formClassBill {
    height:auto;
    width:auto;
}
/* bigger checkbok */
.zoomCheck {
    zoom: 1.5;
}

</style>




<script type="text/javascript">
$(document).ready(function() {

var viewLanguageFile = function () {
    var sel = $('#contractLanguageId :selected').text();
    if (sel.length > 5) {
        location.href = '/_admin/contractlanguage/getuploadfile.php?f=' + escape(sel);
    }
}

$(function() {
    $( ".datepicker" ).datepicker();
});

var arrowDirection = function(workOrderTaskId, direction) {
    var x = document.getElementById('arrowDirection::' + workOrderTaskId);
    x.value = direction;
    var form = document.forms["contractform"];
    form.submit();
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

var setTaskStatusContractIdNew=function(workOrderTaskId) {
        var invoiceId = <?php echo $invoice->getInvoiceId();?> ;
        var taskContractStatus = 1;
        if($("#hideCell_" + workOrderTaskId).is(':checked')) {
            // checked
            taskContractStatus = 1;
        } else {
            // unchecked
            taskContractStatus = 9;
        }

        $.ajax({
            url: '/ajax/update_ctr_status_child.php',
            data: {
            taskContractStatus: taskContractStatus,
            workOrderId : <?php echo intval($workOrderId); ?>,
            workOrderTaskId : workOrderTaskId,
            invoiceId: invoiceId
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                    // on succes no specific return.
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            window.location.reload();
                        } else {
                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                        }
                    } else {
                        alert('Server-side error in call to ajax/update_ctr_status_child.php. No status returned.');
                    }
                },

                error: function (xhr, status, error) {
                    alert('Server-side error in ajax error in call to ajax/update_ctr_status_child.php');
                }
      });
  }; // end function setTaskStatusContractIdNew


var levelOne=<?php echo json_encode($levelOne); ?>; // Contains all id's of level one
var levelOneTasks=<?php echo json_encode($levelOneTasks); ?>;


//console.log(levelOne);

</script>
<script id="column-title" type="text/x-kendo-template">

    # if(parentId == null) { #
    <span class="font-weight-bold"">#= Title#</span>

    # } else { #
        <span>#=Title#</span>

    # } #

</script>

<script id="column-bill" type="text/x-kendo-template">
    # var display = false; #
    # if(levelOne.includes(workOrderTaskId)) {#
        #if(hasChildren == false) {#
        #  display = true;#
    # }#
    #}#
    # if(parentId != null) { #

            # if(billingDescription) { #
                <span class='form-control form-control-sm formClassBill' >#=billingDescription#</span>
            # } else { #
                <span class='form-control form-control-sm' formClassBill></span>
            # } #


    # } else { #
        <span></span>

    # } #

</script>

<script id="column-template" type="text/x-kendo-template">
    #
        // var host = window.location.host;  // sseng
        //var domain = window.location.origin; /// http://dev2.ssseng.com/
        //var urlImg = domain + '/cust/' + host + '/img/icons_task/';
        var urlImg = 'http://dev2.ssseng.com/cust/ssseng/img/icons_task/';

    #
    # var display = false; #
    # if(levelOne.includes(workOrderTaskId)) {#
        #if(hasChildren == false) {#
          #  display = true;#
       # }#
    #}#

    # if(parentId != null) { #
        # if( display == true || !levelOne.includes(workOrderTaskId)) { #
            # if(icon && wikiLink) { #
            <a  target="_blank" href="#: wikiLink #"><img class="tree-list-img" src="#= urlImg + icon #" border="0" title="More info"></a>
            # } else if(icon && !wikiLink) { #
                <img class="tree-list-img" src="#= urlImg + icon #" border="0">

            # } else { #
                <img class="tree-list-img" src="#= urlImg + 'none.jpg' #" border="0">
            # } #
        # } #
    # } #
</script>
<script id="column-hoursTime" type="text/x-kendo-template">
    # var display= false; #
    # if(levelOne.includes(workOrderTaskId)) {#
        #if(hasChildren == false) {#
            # display = true; #
    # } #
    #} #
    # if( display == true || !levelOne.includes(workOrderTaskId)) { #
        # if(hoursTime) { #
            <span>#=kendo.parseFloat((hoursTime/60*100)/100) #</span>
        # } else if(parentId == null) { #
            <span></span>

        # } else { #
            <span>0</span>

        # } #
    # } #
</script>
<script id="column-cost" type="text/x-kendo-template">
    # var display= false; #
        # if(levelOne.includes(workOrderTaskId)) {#
            #if(hasChildren == false) {#
                # display = true; #
        # } #
    #} #

    #cost = Number(cost);#

    # if(cost) { #

        # if(typeof cost === 'number') { #
            #if(cost % 1 === 0) { #
                # cost = parseInt(cost); #
            # } else { #
                # cost =  parseFloat(cost).toFixed(2); #
            #}  #
        # }  #

    <span class='form-control form-control-sm formClass' id="cost_#=workOrderTaskId#">#=cost#</span>

    # } else if(parentId == null) { #
        <span></span>

    # } else { #
        #if (Number.isNaN(Number.parseFloat(cost))) {#
            #cost = 0;#
        #} #
        # if( display == true || !levelOne.includes(workOrderTaskId)) { #
            <span class='form-control form-control-sm formClass' id="cost_#=workOrderTaskId#" title="Only numbers, format 0.00">#=cost#</span>
        # } #
    # } #

</script>


<script id="column-quantity" type="text/x-kendo-template">
    # var display= false; #
        # if(levelOne.includes(workOrderTaskId)) {#
            #if(hasChildren == false) {#
                # display = true; #
        # } #
    #} #

    #quantity = Number(quantity);#

    # if(quantity) { #
        # if(typeof quantity === 'number') { #
            #if(quantity % 1 === 0) { #
                # quantity = parseInt(quantity); #
            # } else { #
                # quantity =  parseFloat(quantity).toFixed(2); #
            #}  #
        # }  #

    <span class='form-control form-control-sm formClass' title="Only numbers, format 0.00">#=quantity#</span>

    # } else if(parentId == null) { #
        <span></span>

    # } else { #
        #if (Number.isNaN(Number.parseFloat(quantity))) {#
            #quantity = 0;#
        #} #
        # if( display == true || !levelOne.includes(workOrderTaskId)) { #
            <span class='form-control form-control-sm formClass'  title="Only numbers, format 0.00">#=quantity#</span>
        # } else { #

            <span></span>
        # } #
    # } #

</script>

<script id="column-statusHide" type="text/x-kendo-template">
    #var userToReview=<?php echo json_encode($editable); ?>; #

    # if(taskContractStatus == null) { taskContractStatus = 0}#

    # var display= false; #
        # if(levelOne.includes(workOrderTaskId)) {#
            #if(hasChildren == false) {#
                # display = true; #
        # } #
    #} #
    # if( display == true || !levelOne.includes(workOrderTaskId)) { #

    # }  else { #
        # if(userToReview == 1) { #
                #if(levelOneTasks.includes(workOrderTaskId)) {#
                    <input id="hideCell_#=workOrderTaskId#"  name type="checkbox"    class="zoomCheck xxx" onclick="javascript:setTaskStatusContractIdNew(#=workOrderTaskId#)">
                #} else { #
                    <input id="hideCell_#=workOrderTaskId#" type="checkbox" checked  class="zoomCheck yyyy"  onclick="javascript:setTaskStatusContractIdNew(#=workOrderTaskId#);">

                # } #

        # }  else { #
                #if(levelOneTasks.includes(workOrderTaskId)) {#
                    <input id="hideCell_#=workOrderTaskId#" disabled="disabled" name type="checkbox" class="zoomCheck" onclick="javascript:setTaskStatusContractIdNew(#=workOrderTaskId#)">
                #} else { #
                    <input id="hideCell_#=workOrderTaskId#" disabled="disabled" type="checkbox" checked class="zoomCheck" onclick="javascript:setTaskStatusContractIdNew(#=workOrderTaskId#)">

                # } #

        # } #
    # } #

</script>

<script id="column-totCost" type="text/x-kendo-template">

    # var display= false; #
        # if(levelOne.includes(workOrderTaskId)) {#
            #if(hasChildren == false) {#
                # display = true; #
        # } #
    #} #
    #var elementsCost=<?php echo json_encode($elementsCost); ?>; #

    # if(parentId == null) { #
        #$.each(elementsCost, function(i,v) {#
            #if(elementId == i)   {#
                <span class="font-weight-bold" id="elementCell_#=elementId#" >Tot : #=v.toLocaleString(undefined, {minimumFractionDigits: 2})#</span>
            #}#
        #});#

    # } else{  #
        #if(totCost) {#
            # if( display == true || !levelOne.includes(workOrderTaskId)) { #
                <span id="totCost_#=workOrderTaskId#">#=kendo.parseFloat(totCost)#</span>
            #} else { #
                <span style="font-weight: 600;" id="totCost_#=workOrderTaskId#">#=kendo.parseFloat(totCost)#</span>
            # } #
        #} else { #
                <span id="totCost_#=workOrderTaskId#">0</span>


        # } #

    # } #




</script>
<?php
/*







<script id="column-select" type="text/x-kendo-template">
    #var allTaskTypes=<?php echo json_encode($allTaskTypes); ?>; #
    #var userToReview=<?php echo json_encode($editable); ?>; #
    # var display= false; #
    # if(levelOne.includes(workOrderTaskId)) {#
        #if(hasChildren == false) {#
          #  display = true;#
       # }#
    #}#

    # if(parentId != null) { #
       # if( display == true || !levelOne.includes(workOrderTaskId)) { #
            # if(userToReview == 1) { #
                <select name="types" style="width:auto" class="form-control form-control-sm dropDownTypesClass formClass"   id="dropDownTypes_#=workOrderTaskId#">
                #for(var key in allTaskTypes) {#
                    #if(taskTypeId == key) {#
                        <option class='form-control form-control-sm formClass' value="#= key #">#=allTaskTypes[key].typeName  #</option>
                    #}#
                #}#
                #for(var key in allTaskTypes) {#

                        <option class='form-control form-control-sm formClass'  value="#= key #">#= allTaskTypes[key].typeName #</option>

                #  } #
                </select>
            #  } else { #
                <select name="types" style="width:auto" class="form-control form-control-sm dropDownTypesClass formClass" disabled  id="dropDownTypes_#=workOrderTaskId#">
                #for(var key in allTaskTypes) {#
                    #if(taskTypeId == key) {#
                        <option class='form-control form-control-sm formClass' readonly value="#= key #">#=allTaskTypes[key].typeName  #</option>
                    #}#
                #}#
                #for(var key in allTaskTypes) {#

                        <option class='form-control form-control-sm formClass' readonly  value="#= key #">#= allTaskTypes[key].typeName #</option>

                #  } #
                </select>
            #  } #
        # } #
    #} #
</script>








  */
?>
<script>
$(document).ready(function() {
    var userToReview=<?php echo json_encode($editable); ?>;
    //console.log(userToReview);


    // Data from Main query
    var allTasksWoElements=<?php echo json_encode($data[4]); ?>;
    var elementsCost=<?php echo json_encode($elementsCost); ?>;

    var gantt = $("#gantt").kendoGantt({
        editable: "incell",
        dataSource : allTasksWoElements,

        schema: {
            model: {
                id: "id",
                parentId :"parentId",
                expanded: true,
                fields: {
                    id: { from: "id", type: "number" },
                    elementId: { from: "elementId", type: "number" },
                    parentId: { from: "parentId", type: "string" },
                    elementName: { from: "elementName", defaultValue: "", type: "string" },
                    workOrderTaskId: { from: "workOrderTaskId", type: "number" },
                    taskId: { from: "taskId", type: "number"},
                    parentTaskId : { from: "parentTaskId", type: "number" },
                    text: { from: "text", defaultValue: "", type: "string" },

                    taskStatusId: { from: "taskStatusId", type: "number" },
                    taskContractStatus: { from: "taskContractStatus", type: "number" },
                    billingDescription: { from: "billingDescription", type: "string" , attributes: {class: "word-wrap"}},
                    cost: { from: "cost", type: "float", defaultValue: 0 },
                    totCost: { from: "totCost", type: "float", defaultValue: 0  },
                    tally: { from: "tally", type: "float" },
                    quantity: { from: "quantity", type: "number", defaultValue: 0 },
                    hoursTime: { from: "hoursTime", type: "float" },
                    icon: { from: "icon", type: "string" },
                    wikiLink: { from: "wikiLink", type: "string" },
                    expanded: { from: "Expanded", type: "boolean", defaultValue: true }
                }

            },

        },

        columns: [


            { field: "Title", title: "Task", template: $("#column-title").html(), editable: false, width: 250 },



            { field: "icon", title:"Icon",  template: $("#column-template").html(),
            attributes: {
                "class": "table-cell k-text-center iconClass"
            }, editable: false, width: 60 },



            { field: "billingDescription", title: "Billing Desc", headerAttributes: { style: "white-space: normal"}, template: $("#column-bill").html(),
            attributes: {
                "class": "billingDescriptionUpdate"
            }, editable: true, width: 200 },


            { field: "hoursTime", title: "Hr", template: $("#column-hoursTime").html(), headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "table-cell k-text-center hoursClass"
            },editable: false, width: 40 },

            { field: "", title: "", template: $("#column-statusHide").html(),  attributes: {
                "class": "table-cell k-text-center statusHide k-text-center"
            },
            width: "35px" },

            { field: "tally", title: "Tally", template: $("#column-tally").html(), headerAttributes: { style: "white-space: normal"}, attributes: {
                "class": "table-cell k-text-center tallyCell"
            },editable: false, width: 60 },

            { field: "quantity", format: "{0:c}", title: "Qty", template: $("#column-quantity").html(), attributes: {
                "class": "table-cell k-text-center quantityCell"
            },editable: true, width: 60 },

            { field: "cost", title: "Cost",  template: $("#column-cost").html(), headerAttributes: { style: "white-space: normal"}, attributes: {
                "class": "table-cell k-text-center costCell"
            },editable: true, width: 70 },

            { field: "totCost", title: "Total",  template: $("#column-totCost").html(), headerAttributes: { style: "white-space: normal"}, attributes: {
                "class": "table-cell k-text-right totCostCell"
            },editable: false, width: 90 },

        ],
        edit: function(e) {
            // George : prevent add/ edit billing Description to Elements.
            if(e.task.parentId == null) {
                e.preventDefault();
            }
            // disable edit if not the reviewer
            if(userToReview == 0) {
                e.preventDefault();
            }
            var display = false;
            if(levelOne.includes(e.task.workOrderTaskId)) {
                if(e.task.hasChildren == false) {
                    display = true;
                }
            }
            // if( display == false && levelOne.includes(e.task.workOrderTaskId)) {
                // prevent edit if wot level one and has children.
            //    e.preventDefault();
            // }
        },
        assign: function(e) {
            // George : prevent add/ edit billing Description to Elements.
            if(e.task.parentId == null) {
                this.hide();
            }
        },



        toolbar: false,
        header: false,
        listWidth: "100%",
        height: "550px",
        listHeight: "550px",
        scrollable: true,
        //selectable: "row",
        dragAndDrop: false,
        selectable: true,
        drag: false//,
        //dataBound: onDataBound,
/*        dataBound:function(e){
              this.list.bind('dragstart', function(e) {
                  return;
              })
            },

        dataBound:function(e) {
            this.list.bind('drop', function(e) {
                e.preventDefault();
                return;

            })
        }*/


    }).data("kendoGantt");


    $(document).bind("kendo:skinChange", function () {
        gantt.refresh();
    });

    gantt.bind("dataBound", function(e) {

        gantt.element.find("tr[data-uid]").each(function (e) {
            var dataItem = gantt.dataSource.getByUid($(this).attr("data-uid"));
            var display= false;

            if(levelOne.includes(dataItem.workOrderTaskId)) {
                if(dataItem.hasChildren == false) {
                     display = true;
                }
            }
            if(dataItem.parentId == null) {

                $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html(''); // FIRST COLUMN BUTTON

                $("[data-uid=" +dataItem.uid + "] ").find(".changeStatusWoTask").addClass('statusWoTaskColorNone');
                $("[data-uid=" +dataItem.uid + "] td.historyClass").html('');
                $("[data-uid=" +dataItem.uid + "] td.billingDescriptionUpdate").html('');
                $("[data-uid=" +dataItem.uid + "] td.statusHide").html('');

            } else {

                $("tr[data-uid=" +dataItem.uid + "] td.k-command-cell:eq(0)").html(''); // FIRST COLUMN BUTTON

            }
           if( display == false && levelOne.includes(dataItem.workOrderTaskId)) {
                // prevent edit if wot level one and has children.
                //e.preventDefault();
                $("[data-uid=" +dataItem.uid + "] td.costCell").css('pointer-events', 'none');
                $("[data-uid=" +dataItem.uid + "] td.quantityCell").css('pointer-events', 'none');
            }
        });

    });

    var expandGanttTree = function(e) {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].hasChildren) {
                tas[i].set("expanded", true);

            }
            $('#gantt table tr').each(function() {
                $(this).find('td').each(function() {
                    $(this).find('span,select,input').attr("readonly", false);
                    $(this).find('span,select,input').attr("disabled", false);
                    $(this).css('pointer-events', 'all');
                });
            });
        }
    }
    expandGanttTree();


    var getIndexofRow = function(workOrderTaskId) {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].workOrderTaskId == workOrderTaskId) {
               return i; // row Index.
            }
        }
    }



    var IndexofRowTd = ""; // used for add notes.
    $("#gantt tbody").on("click",  "[id^='historyCell_']", function() {

        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));

        var workOrderTaskId = item.workOrderTaskId;
        IndexofRowTd = getIndexofRow(workOrderTaskId);

    });

    // Get current Ids of all elements.
    elementIdArr = [];
    var getElementsIds = function(e) {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].parentId == null) {
                elementIdArr.push(tas[i].id);
            }

        }

    }

    // George: Get DB data with ajax.
    // Used for total update on each element and final total.
    // Reload gant to be sure we have the ultimate data.
    var totalCostAjax = 0; // total cost of the contract
    var elementsCost;
    var gantTreeWoAjaxCall = function() {
        $.ajax({
            url: '/ajax/get_contract_wot.php',
            data: {
                workOrderId: <?php echo intval($workOrderId); ?>,

            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        // create Gantt Tree
                        allTasks = data;
                        elementsCost = allTasks["elementsCost"];
                        $.each(elementsCost, function(i,v) {
                            totalCostAjax += parseFloat(v);
                        });
                    } else {
                        alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                    }
                } else {
                    alert('Server-side error in call to ajax/get_contract_wot.php. No status returned.');
                }
            },
            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/get_contract_wot.php');
            }
        }).done(function() {
            if(allTasks["out"]) {

                var dataSource = new kendo.data.GanttDataSource({ data: allTasks["out"] });
                var grid = $('#gantt').data("kendoGantt");


                dataSource.read();
                grid.setDataSource(dataSource);


                // Update total.

                $('#totalContractCost').html('<span>Total: '+ totalCostAjax.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</span>');
                totalCostAjax = 0;
            } else {
                alert('Server-side error in call to ajax/get_contract_wot.php. We could not get the contract data.');
            }

        });
    }

    $("#updateForTotal").on("click", function() {

        var self = this;
        self.textContent = 'Updated';

        setTimeout(function() {
            self.textContent = 'Update for Total';
        }, 5000);

        gantTreeWoAjaxCall();
        expandGanttTree();
        // Update total for each Element.
        $.each(elementsCost, function(i,v) {
            $('td > span#elementCell_'+i).html('<span>Tot: '+ v.toLocaleString(undefined, {minimumFractionDigits: 2}) +'</span>');
        });

    });



    var idWoHistory;
    var itemHistory;
    // on icon "Note" click, populate the modal with values.
    $("#gantt tbody").on('click', "[id^='historyCell_']", function (ev) {
        var gantt = $("#gantt").data("kendoGantt");

        var task = gantt.dataItem(this);
        itemHistory = task;
        var taskId = task.taskId;
        idWoHistory = task.workOrderTaskId; // workOrderTaskId
        ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.

        $.ajax({
            type:'GET',
            url: '../ajax/get_wot_history_multi.php',
            async:false,
            dataType: "json",
            data: {
                taskId: taskId,

            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {

                    if (data['status'] == 'success') {

                        $(".modal-body #woHistoryTable tbody").html('');
                        data["task"].shift(); // remove first element.
                        $(data["task"]).each(function(i, value) {


                            $(".modal-body #woHistoryTable tbody").append('<tr id="hCell_' + i + '">');
                            $("#hCell_" + i ).append('<td><a href=' + value.linkJob + ' target="_blank"><span>' + value.number + '</span></a></td>');
                            $("#hCell_" + i ).append('<td><span>' + value.inserted + '</span></td>');
                            $("#hCell_" + i ).append('<td><span>' + value.cost + ' </span></td>');
                            $("#hCell_" + i ).append('<td><span>' + value.quantity + ' </span></td>');
                            $("#hCell_" + i ).append('<td><span>' + value.typeName + ' </span></td>');
                            $("#hCell_" + i ).append('<td><span>' + value.finalMulti.toLocaleString(undefined, {minimumFractionDigits: 2})+ ' </span></td>');
                            $("#hCell_" + i ).append('<td><button class="btn btn-secondary btn-sm" type="button" value=' + value.cost + ' id="btnHistory_' + i + '">Use Cost</button></td>');
                            $(".modal-body #woHistoryTable tbody").append('</tr>');


                        });

                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
            // alert('error');
            }
        });
        $("#woModalHistory").modal(); // use native function of Bootstrap. Display Modal.
    });



    $('#woModalHistory').on('show.bs.modal', function (e) {

        $("#woHistoryTable tbody").on('click', "[id^='btnHistory_']", function (ev) {
            ev.stopImmediatePropagation();
            var valCost = 0;
            valCost = $(this).closest("tr").find("[id^='btnHistory_']").val(); // cost value

            // logic to task for Level 1.
            getElementsIds();
            if($.inArray(itemHistory.parentId , elementIdArr) != -1) {
                levelTwoTask = 1;  // Level 1 Tasks.
            } else {
                levelTwoTask = 2;  // Level 2 Tasks.
            }

            $.ajax({
                url: '/ajax/update_quantity_cost_wot.php', // this file updates both quantity and cost => updates the totCost
                data: {
                    workOrderTaskId : idWoHistory,
                    cost : valCost,
                    workOrderId:<?php echo intval($workOrderId); ?>,
                    updateCost : true, // used to differenciated between quantity and cost of WOT.
                    levelTwoTask: levelTwoTask
                },
                async:false,
                type:'post',
                success: function (data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            gantTreeWoAjaxCall();
                            expandGanttTree();
                            // Update total for each Element.
                            $.each(elementsCost, function(i,v) {
                                $('td > span#elementCell_'+i).html('<span>Tot: '+ v.toLocaleString(undefined, {minimumFractionDigits: 2}) +'</span>');
                            });

                            table = $("#gantt");
                            row = table.find('tr').eq(IndexofRowTd + 1);

                            var bg = $(row).css('background'); // store original background
                            row.css('background-color', '#FFDAD7'); //change element background
                            setTimeout(function() {
                                $(row).css('background', bg); // change it back after ...
                            }, 10000); // 10 seconds
                        } else {
                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                        }
                    } else {
                        alert('Server-side error in call to ajax/update_quantity_cost_wot.php. No status returned.');
                    }
                },
                error: function (xhr, status, error) {
                    alert('Server-side error in ajax error in call to ajax/update_quantity_cost_wot.php');
                }
            })

            $('#woModalHistory').modal('hide'); // close modal.
            delete idWoHistory;
        });
    });




    // Add/ Update task type.
    $( "#gantt tbody" ).on( "change", "td > select[id^='dropDownTypes_']", function() {
        selectWoId = $(this).attr('id'); // select Id we clicked

        var taskTypeId = $('#'+selectWoId).val(); // get the value of the selected option.
        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));
        var workOrderTaskId = item.workOrderTaskId;

        $.ajax({
            url: '/ajax/update_task_type.php',
            data: {
                workOrderTaskId : workOrderTaskId,
                taskTypeId : taskTypeId,
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                //success
                //gantt.refresh();
            },
            error: function (xhr, status, error) {
            //error
            }
        })


    });

    // Add/ Update task Quantity
    $( "#gantt tbody" ).on( "change", "td.k-edit-cell > input#quantity", function(e) {
        var totCostAjax = 0;
        var sumAjax = 0;
        var workOrderTaskIdOne = 0;
        var quantity = $('#quantity').val(); // get the value of the input.

        quantity = quantity.trim();
        if(!quantity.match(/^[. 0-9]*$/) ) {

           quantity = 0;
           $('#quantity').val('0');
           e.preventDefault();
        }

        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));
        console.log(item);
        var oldCost=item.totCost;

        item.totCost = Number(item.cost) * Number(quantity);
        var delta=item.totCost-oldCost;
        var item1=$("#gantt").data("kendoGantt").dataSource.taskParent(item);
        while(item1!==null){

            let item2=$("#gantt").data("kendoGantt").dataSource.taskParent(item1);
            if(item2!==null){
                item1.totCost=Number(item1.totCost)+delta;
            } else {
                elementsCost[item1.elementId]=Number(elementsCost[item1.elementId])+Number(delta);
                $("#elementCell_"+item1.elementId).html("Tot : "+Number(elementsCost[item1.elementId]));
                console.log(item1);
            }
            item1=item2;
        }
        var invoiceId = <?php echo $invoice->getInvoiceId();?> ;
        var JsonDataSource=JSON.stringify($("#gantt").data("kendoGantt").dataSource.data());
        var invoiceData=[];
        invoiceData[4]=JsonDataSource;
        $.ajax({
            url: '/ajax/update_invoice_data.php', // this file updates both quantity and cost => updates the totCost
            data: {
                invoiceId:invoiceId,
                workOrderTaskId: item.workOrderTaskId,
                data: JsonDataSource
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                // on succes we have: totCost, sum and workOrderTaskIdOne.
                if (data['status']) {
                    if (data['status'] == 'success') {

                        totCostAjax = data['totCost'];
                        sumAjax = data['sum'];
                        workOrderTaskIdOne = data['workOrderTaskIdOne'];
                        //location.reload();
                    } else {
                        alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                    }
                } else {
                    alert('Server-side error in call to ajax/update_invoice_data.php. No status returned.');
                }
            },
            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/update_invoice_data.php');
            }
        })
//$("#gantt").data("kendoGantt").refresh();
        return;



        // logic to task for Level 1.
        getElementsIds();
        if($.inArray(item.parentId , elementIdArr) != -1) {
            levelTwoTask = 1;  // Level 1 Tasks.
        } else {
            levelTwoTask = 2;  // Level 2 Tasks.
        }

        $.ajax({
            url: '/ajax/update_quantity_cost_wot.php', // this file updates both quantity and cost => updates the totCost
            data: {
                workOrderTaskId : workOrderTaskId,
                workOrderId:<?php echo intval($workOrderId); ?>,
                quantity : quantity,
                levelTwoTask : levelTwoTask
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                // on succes we have: totCost, sum and workOrderTaskIdOne.
                if (data['status']) {
                    if (data['status'] == 'success') {

                        totCostAjax = data['totCost'];
                        sumAjax = data['sum'];
                        workOrderTaskIdOne = data['workOrderTaskIdOne'];
                    } else {
                        alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                    }
                } else {
                    alert('Server-side error in call to ajax/update_quantity_cost_wot.php. No status returned.');
                }
            },
            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/update_quantity_cost_wot.php');
            }
        })
        if(typeof totCostAjax === 'number') {
            if(totCostAjax % 1 === 0) {
                $('td > span#totCost_'+workOrderTaskId).html('<span>'+ parseInt(totCostAjax) +'</span>');
            } else {
                $('td > span#totCost_'+workOrderTaskId).html('<span>'+ parseFloat(totCostAjax).toFixed(2) +'</span>');
            }
        }

        if(typeof sumAjax === 'number') {
            if(sumAjax % 1 === 0) {
                $('td > span#totCost_'+workOrderTaskIdOne).html('<span style="font-weight: 600;">'+ parseInt(sumAjax) +'</span>');
            } else {
                $('td > span#totCost_'+workOrderTaskIdOne).html('<span style="font-weight: 600;">'+ parseFloat(sumAjax).toFixed(2) +'</span>');
            }
        }

    });

        // Add/ Update task Cost
    $( "#gantt tbody" ).on( "change", "td.k-edit-cell > input#cost", function(e) {

        var totCostAjax = 0;
        var sumAjax = 0;
        var workOrderTaskIdOne = 0;

        var cost = $('#cost').val(); // get the value of the input.
        cost = cost.trim();
        if(!cost.match(/^[. 0-9]*$/) ) {
           cost = 0;
           $('#cost').val('0');
           e.preventDefault();
        }

        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));
        var oldCost=item.totCost;

        item.totCost = Number(item.quantity) * Number(cost);
        //var workOrderTaskId = item.workOrderTaskId;
        var delta=item.totCost-oldCost;
        var item1=$("#gantt").data("kendoGantt").dataSource.taskParent(item);
        while(item1!==null){

            let item2=$("#gantt").data("kendoGantt").dataSource.taskParent(item1);
            if(item2!==null){
                item1.totCost=Number(item1.totCost)+delta;
                $("#totCost_"+item1.elementId).html(Number($("#totCost_"+item1.elementId).html())+delta);
            } else {
                elementsCost[item1.elementId]=Number(elementsCost[item1.elementId])+Number(delta);
                $("#elementCell_"+item1.elementId).html("Tot : "+Number(elementsCost[item1.elementId]));
                console.log(item1);
            }
            item1=item2;
        }
        var invoiceId = <?php echo $invoice->getInvoiceId();?> ;
        var JsonDataSource=JSON.stringify($("#gantt").data("kendoGantt").dataSource.data());
        var invoiceData=[];
        invoiceData[4]=JsonDataSource;
        $.ajax({
            url: '/ajax/update_invoice_data.php', // this file updates both quantity and cost => updates the totCost
            data: {
                invoiceId:invoiceId,
                data: JsonDataSource
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                // on succes we have: totCost, sum and workOrderTaskIdOne.
                if (data['status']) {
                    if (data['status'] == 'success') {

                        totCostAjax = data['totCost'];
                        sumAjax = data['sum'];
                        workOrderTaskIdOne = data['workOrderTaskIdOne'];

                        location.reload();
                    } else {
                        alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                    }
                } else {
                    alert('Server-side error in call to ajax/update_invoice_data.php. No status returned.');
                }
            },
            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/update_invoice_data.php');
            }
        })
        return;


        // logic to task for Level 1.
        getElementsIds();
        if($.inArray(item.parentId , elementIdArr) != -1) {
            levelTwoTask = 1;  // Level 1 Tasks.
        } else {
            levelTwoTask = 2;  // Level 2 Tasks.
        }

        $.ajax({
            url: '/ajax/update_quantity_cost_wot.php', // this file updates both quantity and cost => updates the totCost
            data: {
                workOrderTaskId : workOrderTaskId,
                cost : cost,
                workOrderId:<?php echo intval($workOrderId); ?>,
                updateCost : true, // used to differenciated between quantity and cost of WOT.
                levelTwoTask: levelTwoTask
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                // on succes we have: totCost, sum and workOrderTaskIdOne.
                if (data['status']) {
                    if (data['status'] == 'success') {
                        totCostAjax = data['totCost'];
                        sumAjax = data['sum'];
                        workOrderTaskIdOne = data['workOrderTaskIdOne'];
                        location.reload();
                    } else {
                        alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                    }
                } else {
                    alert('Server-side error in call to ajax/update_quantity_cost_wot.php. No status returned.');
                }
            },
            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/update_quantity_cost_wot.php');
            }
        })
        if(typeof totCostAjax === 'number') {
            if(totCostAjax % 1 === 0) {
                $('td > span#totCost_'+workOrderTaskId).html('<span>'+ parseInt(totCostAjax) +'</span>');
            } else{
                $('td > span#totCost_'+workOrderTaskId).html('<span>'+ parseFloat(totCostAjax).toFixed(2) +'</span>');
            }
        }

        if(typeof sumAjax === 'number') {
            if(sumAjax % 1 === 0) {
                $('td > span#totCost_'+workOrderTaskIdOne).html('<span style="font-weight: 600;">'+ parseInt(sumAjax) +'</span>');
            } else {
                $('td > span#totCost_'+workOrderTaskIdOne).html('<span style="font-weight: 600;">'+ parseFloat(sumAjax).toFixed(2) +'</span>');
            }
        }

    });




    // Add/ Edit billing description.
    $( "#gantt tbody" ).on( "change", "td.k-edit-cell > input#billingDescription", function() {

        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));

        var billingDescription = item.billingDescription;
        var nodeTaskId = item.workOrderTaskId;

        var invoiceId = <?php echo $invoice->getInvoiceId();?> ;
        var JsonDataSource=JSON.stringify($("#gantt").data("kendoGantt").dataSource.data());
        var invoiceData=[];
        invoiceData[4]=JsonDataSource;
        $.ajax({
            url: '/ajax/update_invoice_data.php', // this file updates both quantity and cost => updates the totCost
            data: {
                invoiceId:invoiceId,
                data: JsonDataSource
            },
            async:false,
            type:'post',
            success: function (data, textStatus, jqXHR) {
                // on succes we have: totCost, sum and workOrderTaskIdOne.
                if (data['status']) {
                    if (data['status'] == 'success') {

                        totCostAjax = data['totCost'];
                        sumAjax = data['sum'];
                        workOrderTaskIdOne = data['workOrderTaskIdOne'];
                    } else {
                        alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                    }
                } else {
                    alert('Server-side error in call to ajax/update_invoice_data.php. No status returned.');
                }
            },
            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/update_invoice_data.php');
            }
        })


    });

    // Status Modal
    var contractStatusId = "";
        // on icon "Note" click, populate the modal with values.
    $("#statusDiv").on('click' , "[id^='changeStatus_']", function (ev) {

        buttonId = $(this).attr('id');
        contractStatusId = parseInt(buttonId.split('_')[1]); // contractStatusId

        $("#hiddenDiv").append('<input type="hidden" name="contractStatusIdUpdate" value="'+contractStatusId+'" />');
        //console.log(contractStatusId);
        if(contractStatusId == 4 || contractStatusId == 6 ) {
            $("#personIdCtr").hide();
        }

        ev.stopImmediatePropagation(); // sometimes click event fires twice in jQuery you can prevent it by this method.

        $("#statusModal").modal(); // use native function of Bootstrap. Display Modal.
    });



} ); // End Document Ready

</script>




                            <table border="0" class="thistable" >
                                <tbody>
                                    <?php

                                    $invoiceTotal=$invoice->getTotal();
                                    /*foreach($elementsCost as $cost){
                                        $invoiceTotal+=$cost;
                                    }
                                    $invoice->update([
                                        'total' => $invoiceTotal
                                    ]);*/
                                                                        /* A row in "heading" font spanning all columns, giving (unadjusted) total for invoice */
                                    echo '<tr>';

                                        echo '<th colspan="12" class="text-right pr-3">';
                                        echo "<h5>TOTAL : " .  number_format($invoiceTotal, 2)."</h5>";
                                        echo '</th>';
                                    echo '</tr>';

                                    /* Then come rows pertaining to adjustments for this invoice. There are three types of adjustments:

                                        INVOICEADJUST_DISCOUNT (displayed as 'Discount')
                                            increases $total by specified amount, so any actual discount should be represented by a negative value.
                                        INVOICEADJUST_QUICKBOOKSSHIT (displayed as 'Quickbooks Shit (dollars)')
                                            increases $total by specified amount.
                                        INVOICEADJUST_PERCENTDISCOUNT (displayed as 'Percent Discount')
                                            decreases $total by specified percentage (still called 'amount').

                                        Each adjustment is supposed to affect both $total
                                    */
                                    $adjustments = $invoice->getAdjustments();
                                    $total = $invoice->getTotal();

                                    foreach ($adjustments as $adjustment) {
                                        echo '<tr>';
                                            $disp = "";
                                            if ($adjustment['invoiceAdjustTypeId'] == INVOICEADJUST_DISCOUNT) {
                                                $disp = 'Discount';
                                                if (is_numeric($total)) {
                                                    $total = $total + $adjustment['amount'];
                                                }
                                            } else if ($adjustment['invoiceAdjustTypeId'] == INVOICEADJUST_QUICKBOOKSSHIT) {
                                                $disp = 'Quickbooks Adjustment (dollars)';
                                                if (is_numeric($total)) {
                                                    $total = $total + $adjustment['amount'];
                                                }
                                            } else if ($adjustment['invoiceAdjustTypeId'] == INVOICEADJUST_PERCENTDISCOUNT) {
                                                $disp = 'Percent Discount';
                                                if (is_numeric($total)) {
                                                    $total = $total  * ((100 - $adjustment['amount'])/100);
                                                }
                                            }

                                            /* Adjustments are always applied in the chronological order in which they are inserted.
                                                That matters if there are both dollar amount adjustments and percentage adjustments on
                                                the same invoice.

                                               Display, for each adjustment (in columns, which somewhat arbitrarily use multiple columns of the table):
                                                * self-submitting link with query string act=deleteadjust&invoiceAdjustId=invoiceAdjustId. Displays 16x16px "delete" icon.
                                                * Note for this adjustment
                                                * amount (with the annotation from above, e.g. "Discount -48", "Percent Discount 5")
                                                * "New Total" and a numeric with 2 digits past the decimal
                                            */
                                            echo '<td><a id="linkDelAdjust'. intval($adjustment['invoiceAdjustId']) .'" href="' . $link . '?act=deleteadjust&invoiceAdjustId=' . intval($adjustment['invoiceAdjustId']) . '">' .
                                            '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_delete_16x16.png" width="16" height="16" border="0"></a></tx>';

                                            echo '<td colspan="3"><span style="float:right">' . $adjustment['invoiceAdjustNote'] . '</span></td>';

                                            echo '<td colspan="3"><span style="float:right">' . $disp . '(' . $adjustment['amount'] . ')</span></td>';
                                            echo '<td colspan="5"><span style="float:right">New Total:' . number_format($total,2) . '</span></td>';
                                        echo '</tr>';
                                    }

                                    $payments = $invoice->getPayments();
                                    foreach ($payments As $payment) {
                                        $payment_amount = $payment['amount'];
                                        echo '<tr>';
                                            if (is_numeric($total)) {
                                                $total -= $payment_amount;
                                            }

                                            echo '<td colspan="2" bgcolor="#ddffdd">&nbsp;</td>';
                                            echo '<td colspan="3" bgcolor="#ddffdd">&nbsp;&nbsp;' .
                                                 '<a id="linkPayment'. $payment['creditRecordId']. '" href="/creditrecord.php?creditRecordId='. $payment['creditRecordId']. '">Payment ' .
                                                    $payment['inserted']. '</a></td>';
                                            echo '<td colspan="3" bgcolor="#ddffdd"><span style="float:right">(' . $payment_amount . ')</span></td>';
                                            echo '<td colspan="4" bgcolor="#ddffdd"><span style="float:right">New Balance:' . number_format($total,2) . '</span></td>';
                                        echo '</tr>';
                                    }
                                ?>
                                </tbody>
                                </table> <?php /* END grouped list of elements and subordinate tasks */ ?>
                        <?php
                        /* If editable, then there is another row (still part of the same form) with a single row, offering:
                            * Submit button, labeled "update"
                            * Button labeled "Approval Required": self submitting, query string is "act=approvalRequired"
                            * If invoice status is anything other than 'awaitingdelivery', button labeled "Set Await Delivery":
                              self submitting, query string is "act=awaitDeliveryStatus"
                            >>>00001, 00026: at the moment the "commit" button is commented out, and there appears to be no way to commit an invoice.
                        */
                        if ($editable) { ?>
                            <table>
                            <tr>
                                <td style="text-align:center;"><input type="submit" id="update" value="update" class="btn btn-secondary"></td>
                                <td style="text-align:center;">
                                    <button class="btn btn-outline-primary" type="button" id="buttonApprovalReq" onClick="location.href='/invoice/<?php echo $invoice->getInvoiceId();?>?act=approvalRequired';">Approval Required</button>
                                </td>
                                <?php
                                // code here simplified 2020-05-22 JM by using $invoiceStatusAwaitingDelivery.
                                if ($invoice->getInvoiceStatusId() != $invoiceStatusAwaitingDelivery) {
                                ?>
                                    <td style="text-align:center;"><button class="btn btn-outline-secondary" type="button" id="awaitingDelivery" onClick="location.href='/invoice/<?php echo $invoice->getInvoiceId();?>?act=awaitDeliveryStatus';">Set Await Delivery</button> </td>
                                <?php
                                }
                                ?>
                                <?php /* [BEGIN commented out by Martin before 2019, but probably will eventually be restored - JM]
                                    <td style="text-align:center;"><button type="button" id="commitnote" href="#data" >Commit</button> </td>
                                    // [END commented out by Martin before 2019
                                */ ?>
                            </tr>
                        </table>
                        <?php
                            } ?>

                </form>









                <?php
                if ($total) { // if there is any unpaid balance remaining on the invoice...
                    ?>
                    <a data-fancybox-type="iframe" id="makePayment" class="fancyboxIframeWide"  href="/fb/invoice.php?invoiceId=<?php echo $invoice->getInvoiceId(); ?>">
                        <button class="btn btn-sm btn-primary my-3">Make payment</button></a><br />
                    <?php
                }
                /*
                  Another self-submitting form/table for adding adjustments
                    * (hidden) act=adjustment
                    * Table consisting of a single row with the following columns
                        * "Type:" HTML SELECT (dropdown) with one option per invoiceAdjustType. First row value is blank and text is "-- choose adjustment --". Each other row: value=invoiceAdjustTypeId, displays invoiceAdjustTypeName.
                        * (blank column)
                        * "Amount:" text input, name=invoiceAdjustAmount
                        * (blank column)
                        * "Note:" text input, name=invoiceAdjustNote
                        * (blank column)
                        * Submit button labeled "add adjustment"
                */ ?>
                <form name="invoiceadjust" id="invoiceformadjust" action="<?php echo $link; ?>" method="post">
                    <input type="hidden" name="act" value="adjustment">
                    <table border="1" cellpadding="4" cellspacing="5" width="90%">
                        <?php
                        $adjustTypes = array();
                        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".invoiceAdjustType ORDER BY invoiceAdjustTypeId;";

                        $result = $db->query($query);
                        if ($result) {
                            // if ($result->num_rows > 0) { // Rm unnecessary test 2020-08-28 JM
                                while ($row = $result->fetch_assoc()) {
                                    $adjustTypes[] = $row;
                                }
                            // }  // Rm unnecessary test 2020-08-28 JM
                        } else {
                            $logger->errorDb('1598633885', 'Hard DB error', $db);
                        }

                        echo '<tr>';
                        echo '<td >Type:<select  id="invoiceAdjustTypeId" name="invoiceAdjustTypeId" class="form-control"><option value="">-- choose adjustment --</option>';
                        foreach ($adjustTypes as $adjustType) {
                            // BEGIN ADDED 2020-04-07 JM
                            if ( strpos($adjustType['invoiceAdjustTypeIdName'], 'shit') !== false ) {
                                // Martin stuff for Quickbooks transition, no one needs this choice any more.
                                continue;
                            }
                            // END ADDED 2020-04-07 JM
                            echo '<option value="' . $adjustType['invoiceAdjustTypeId'] . '">' . $adjustType['invoiceAdjustTypeName'] . '</option>';
                        }
                        echo '</select></td>';
                        echo '<td>&nbsp;&nbsp;&nbsp;</td>';
                        echo '<td>Amount:<input type="text" id="invoiceAdjustAmount" name="invoiceAdjustAmount" size="5" class="form-control"></td>';
                        echo '<td>&nbsp;&nbsp;&nbsp;</td>';
                        echo '<td>Note:<input type="text" id="invoiceAdjustNote" name="invoiceAdjustNote" size="35" class="form-control"></td>';
                        echo '<td>&nbsp;&nbsp;&nbsp;</td>';
                        echo '<td><input type="submit" id="addAdjustment" value="add adjustment" class="btn btn-secondary btn-sm mt-3">';
                        echo '</tr>';
                        ?>
                    </table>
                </form>

                <?php
                } // END: $canProceed is truthy

                // BEGIN ADDED 2020-09-22 JM
                /* More self-submitting forms.

                   "Notes from WorkOrder" is sort of an "incoming" note that you might use to prepare the invoice.
                   These are at the workOrder level, but you can edit them here.

                   "Notes*/
                ?>
                <div style="float: left;">
                <h2>Notes from WorkOrder</h2>
                <form name="" id="notesWoForm" method="post" action="">
                    <input type="hidden" name="act" value="updatetempNote">
                    <textarea rows="7" name="tempNote" id="tempNote" rows="4" cols="80"><?php echo htmlspecialchars($workOrder->getTempNote()); ?></textarea>
                    <br />
                    <input type="submit" id="updateNotesWo" value="update notes" class="btn btn-secondary">
                </form>
            </div>
                <div style="float: left;" class="ml-5">
                <h2>Notes for Invoice PDF</h2>
                <form name="" id="notesInvForm" method="post" action="">
                    <input type="hidden" name="act" value="updateInvoiceNotes">
                    <textarea rows="7" id="invoiceNotes" name="invoiceNotes" rows="4" cols="80"><?php echo htmlspecialchars($invoice->getInvoiceNotes()); ?></textarea>
                    <br />
                    <input type="submit" id="updateNotesInv" value="update notes" class="btn btn-secondary">
                </form>
</div>
                <?php
                // END ADDED 2020-09-22 JM

                /* Next, another self-submitting form, this time not using a table.
                    * Heading: "Invoice Text Override"
                    * (hidden) act=settextoverride
                    * textarea, name="textOverride" initialized to the current textOverride
                    * Submit button labeled "set override"
                */

                $textOverride = '';
                $query =  "SELECT textOverride FROM " . DB__NEW_DATABASE . ".invoice ";
                $query .= "WHERE invoiceId = " . $invoice->getInvoiceId() . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        /* BEGIN REPLACED 2020-08-28 JM
                        // No good reason for a 'while' here.
                        // Presumably finding more than one row would be an error.
                        while ($row = $result->fetch_assoc()) {
                            $textOverride = $row['textOverride'];
                        }
                        // END REPLACED 2020-08-28 JM
                        */
                        // BEGIN REPLACEMENT 2020-08-28 JM
                        $row = $result->fetch_assoc();
                        $textOverride = $row['textOverride'];
                        // END REPLACEMENT 2020-08-28 JM
                    }
                } else {
                    $logger->errorDb('1598633837', 'Hard DB error', $db);
                }
                $textOverride = trim($textOverride);
                ?>
                <div style="float: left;" class="ml-5">

                <h2>Invoice Text Override</h2>
                <form name="overrideform" id="overrideform" method="post" action="">
                    <input type="hidden" name="act" value="settextoverride">
                    <textarea name="textOverride" id="textOverride" rows="7" cols="80"><?php echo htmlspecialchars($textOverride); ?></textarea>
                    <br />
                    <input type="submit" id="setOverride" value="set override" class="btn btn-secondary">
                </form>
            </div>
                </div>

                <?php /* A table with a row for each file related to this invoice via DB table invoiceFile. For each:
                            * a link that self-submits with query string act=delinvoicefile&invoiceFileId=invoiceFileId; labeled [del]
                            * fileName */
                ?>
                <h2>Files</h2>
                <p>Only filename:</p>
                <?php
                $files = array();
                $query =  "SELECT * FROM " . DB__NEW_DATABASE . ".invoiceFile ";
                $query .= "WHERE invoiceId = " . $invoice->getInvoiceId() . ";";

                $result = $db->query($query);
                if ($result) {
                    // if ($result->num_rows > 0) { // rm unnecessary test 2020-08-28 JM
                        while ($row = $result->fetch_assoc()) {
                            $files[] = $row;
                        }
                    // } // rm unnecessary test 2020-08-28 JM
                } else {
                    $logger->errorDb('1598633956', 'Hard DB error', $db);
                }

                echo '<table border="0" cellpadding="5" cellspacing="0">';
                    foreach ($files as $file) {
                        echo '<tr>';
                        echo '<td>[<a id="delInvoiceFile' . intval($file['invoiceFileId']) . '" href="' . $invoice->buildLink() . '?act=delinvoicefile&invoiceFileId=' . intval($file['invoiceFileId']) . '">del</a>]</td>';
                        echo '<td>' . $file['fileName'] . '</td>';
                        echo '</tr>';
                    }
                    // BEGIN ADDED 2020-05-27 JM as a workaround for http://bt.dev2.ssseng.com/view.php?id=161 (Some files are not attaching to the invoice).
                    // NOTE that this just grabs the name of the file and attaches that to the invoice. It does NOT upload the actual file, nor does it
                    // attach to the workOrder.
                    ?>
                    <tr><td colspan="2"><form id="add-filename-workaround">
                        <input type="hidden" name="invoiceId" value="<?= $invoice->getInvoiceId() ?>"></input>
                            <input type="text" id="fileName" name="fileName" malength="255" size="30" required placeholder="filename (no path)"  class="form-control" style="display: inline-block!important;"></input>
                            <button class="btn btn-secondary">Add</button>
                    </form></td></tr>
                    <script>
                    $('#add-filename-workaround').submit(function(event) {
                        var $this = $(this);
                        event.preventDefault();
                        var fileName = $('#add-filename-workaround input[name="fileName"]').val();
                        if (fileName) {
                            $.ajax({
                                url: '/ajax/invoicefileadd.php',
                                data:{
                                    invoiceId:<?= $invoice->getInvoiceId(); ?>,
                                    fileName:fileName
                                },
                                async: true,
                                type: 'post',
                                success: function(data) {
                                    if (data['status']='success') {
                                        if (data['alreadyExisted'] != 'true') {
                                            var $newRow = $('<tr>' +
                                                    '<td>[<a id="delInvFile' + data['invoiceFileId'] + '" href="<?= $invoice->buildLink() ?>?act=delinvoicefile&invoiceFileId=' +
                                                        data['invoiceFileId'] + '">del</a>]</td>' +
                                                    '<td>' + fileName + '</td>' +
                                                    '</tr>');
                                            $this.closest('tr').before($newRow);
                                        }
                                        $('#add-filename-workaround input[name="fileName"]').val('');
                                    } else {
                                        alert(data['error']); // >>>00002 probably should improve on this
                                    }
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    alert('/ajax/invoicefileadd.php failed'); // >>>00002 probably should improve on this
                                }
                            });
                        }
                    });
                    </script>
                    <?php
                    // END ADDED 2020-05-27 JM
                echo '</table>';

                // BEGIN ADDED 2020-09-14 JM
                echo '<br /><p>Files attached to workOrder:</p>';

                $files = array();
                $query =  "SELECT * FROM " . DB__NEW_DATABASE . ".workOrderFile ";
                $query .= "WHERE workOrderId = " . $invoice->getWorkOrderId() . ";";

                $result = $db->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $files[] = $row;
                    }
                } else {
                    $logger->errorDb('1600106369', 'Hard DB error', $db);
                }

                // Separate table just to contain iframe "imageframe" (see handling
                //  of images below to make sense of this)
                if (count($files)) {
                    echo '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
                        echo '<tr>';
                            echo '<td width="100%" height="250" colspan="8">';
                                echo '<iframe name="imageframe" id="imageframe" style="display: block; width: 100%; height: 100%; border: none;"></iframe>';
                            echo '</td>';
                        echo '</tr>' . "\n";
                    echo '</table>' . "\n";
                }

                /* JM 2020-09-14: invoice.php might be invoked either as DOMAIN/invoice.php&invoiceId=nnn or
                   as DOMAIN/invoice/nnn. The relative path to various PHP will be different for the two, so we introduce $domain*/
                $domain = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);

                echo '<table border="0" cellpadding="5" cellspacing="0">' . "\n";
                    $ok = array('png', 'jpg', 'jpeg', 'gif');
                    foreach ($files as $file) {
                        $explodedFilename = explode('.', $file['fileName']);
                        $fileType = strtolower(end($explodedFilename));
                        echo '<tr>';
                        echo '<td>[<a id="delWorkorderFile' . intval($file['workOrderFileId']) . '" href="' . $invoice->buildLink() . '?act=delworkorderfile&workOrderFileId=' . intval($file['workOrderFileId']) . '">del</a>]</td>';
                        echo '<td>' . $file['origFileName'] . '</td>';
                        if (in_array($fileType, $ok)) {
                            echo '<td><a id="frameWoGet' . intval($file['workOrderFileId']) . '" target="imageframe" href="' . $domain . '/workorderfile_get.php?f=' . rawurlencode($file['fileName']) . '">';
                                echo '<img src="' . $domain . '/workorderfile_get.php?f=' . rawurlencode($file['fileName']) . '" style="max-width:160px; max-height:80px;">';
                            echo '</a></td>' . "\n";
                        } else {
                            echo '<td>[<a id="clickFIleWo' . intval($file['workOrderFileId']) . '" target="imageframe" href="' . $domain . '/workorderfile_get.php?f=' . rawurlencode($file['fileName']) . '">click</a>]</td>' . "\n";
                        }
                        echo '</tr>' . "\n";
                    }
                echo '</table>' . "\n";
                // END ADDED 2020-09-14 JM

                // BEGIN DROPZONE code completely rewritten 2020-09-14 JM for http://bt.dev2.ssseng.com/view.php?id=128, which
                // evolved considerably from the original request. This now will upload a file associated with the workOrder.
                ?>
                <script src="/js/dropzone.js?v=1524508426"></script>
                <link rel="stylesheet" href="/cust/<?= $customer->getShortName() ?>/css/dropzone.css?v=1524508426" />;
                <script>
                {
                    // This script must run BEFORE dropzone uploadworkorderfile is instantiated.
                    // See https://www.dropzonejs.com/#configuration-options
                    window.Dropzone.options.uploadworkorderfile = {
                        uploadMultiple:false,
                        maxFiles:1,
                        autoProcessQueue:true,
                        maxFilesize: 45, // MB
                        clickable: false,
                        addRemoveLinks : true,
                        acceptedFiles : "application/pdf,.pdf,.png,.jpg,.jpeg",
                        init: function() {
                            // >>>00001 I'm not at all sure what is up with 'bind' here; I've left it as it was - JM
                            this.on("error", function(file, errorMessage) {
                                alert(errorMessage); // added 2020-02-20 JM, >>>00002 maybe alert is not exactly what we should do.
                                setTimeout(this.removeFile.bind(this, file), 3000);
                            }.bind(this)
                            );

                            this.on('complete', function () {
                                setTimeout(function(){ window.location.reload(false); }, 2000);
                            }.bind(this)
                            );

                            this.on("success", function(file) {
                                setTimeout(this.removeFile.bind(this, file), 1000);
                            }.bind(this)
                            );
                        }
                    };
                }
                </script>
                <div class="drop-area">
                    <form id="uploadworkorderfile" class="dropzone" action="<?= $domain ?>/workorderfile_upload.php?workOrderId=<?= $invoice->getWorkOrderId() ?>">
                        <h2 class="heading"></h2>
                        <div id="dropzone">
                        </div>
                    </form>
                </div>
                <?php
                // END DROPZONE

                /* Header "Aging Summary Invoice Activity Notes"
                         * A table with a row for each row related to this invoice in DB table agingNote. Columns:
                            * Note: text of note
                            * Person: Formatted name of person
                            * Time: Day note inserted, in 'm/d/y' form
                         * Form "addnote", using last 2 rows of the table, to add a note agingNote:
                            * (hidden) act='addagingnote'
                            * textarea for note text, name='note'
                            * submit button labeled "add note". Form self-submits using POST method. */ ?>
                <div style="clear: both; float: left;">
                    <h2>Aging Summary Invoice Activity Notes</h2>
                    <?php
                        $notes = array();
                        $query =  "SELECT * FROM " . DB__NEW_DATABASE . ".agingNote ";
                        $query .= "WHERE invoiceId = " . $invoice->getInvoiceId() . " ";
                        $query .= "ORDER BY inserted DESC;";

                        $result = $db->query($query);
                        if ($result) {
                            // if ($result->num_rows > 0) { // rm unnecessary test 2020-08-28 JM
                                while ($row = $result->fetch_assoc()) {
                                    $notes[] = $row;
                                }
                            // } // rm unnecessary test 2020-08-28 JM
                        } else {
                            $logger->errorDb('1598634141', 'Hard DB error', $db);
                        }
                    ?>
                    <center>
                        <table border="0" cellpadding="4" cellspacing="2" width="600" class="table table-bordered table-striped">
                            <tr>
                                <th>Note</th>
                                <th>Person</th>
                                <th>Time</th>
                            </tr>
                            <?php
                            foreach ($notes as $note) {
                                echo '<tr>';
                                    $p = new Person($note['personId']);
                                    echo '<td>' . $note['note'] . '</td>';
                                    echo '<td nowrap>' . $p->getFormattedName(1) . '</td>';
                                    echo '<td nowrap>' . date("m/d/Y", strtotime($note['inserted'])) . '</td>';
                                echo '</tr>';
                            }
                            // >>>00006 it would be cleaner to have the form inside the row than vice versa.
                            echo '<form name="addnote" id="addNoteForm" action="' . $invoice->buildLink() . '" method="post">';
                                echo '<input type="hidden" name="act" value="addagingnote">';
                                echo '<tr>';
                                    echo '<td colspan="3" style="text-align:center">';
                                        echo '<textarea name="note" id="note" style="width:100%;" class="form-control"></textarea>';
                                    echo '</td>';
                                echo '</tr>';
                                echo '<tr>';
                                    echo '<td colspan="3" style="text-align:center">';
                                        echo '<input type="submit" id="addNote" value="add note" class="btn btn-secondary">';
                                    echo '</td>';
                                echo '</tr>';
                            echo '</form>';
                            ?>
                        </table>
                    </center>
                </table>
            </div>

<?php
/* [BEGIN MARTIN COMMENT]

create table agingNote(
    agingNoteId   int unsigned not null primary key auto_increment,
    invoiceId     int unsigned not null,
    note          text,
    personId      int unsigned,
    inserted      timestamp not null default now()
)

create index ix_agingnote_ins on agingNote(inserted);
create index ix_agingnote_pid on agingNote(personId);
create index ix_agingnote_iid on agingNote(invoiceId);

[END MARTIN COMMENT]
*/

        /* ======================
           Work Order time summary section: time summary for a single workOrder.
           Longtime users may know this as the "TXN" section.
           Available only if user has admin-level invoice permission.

           Basically, this gives the customer (as of 2020-01, always SSS) a way to
           eyeball how well they did in business terms for a particular workOrder.

           We provide an easy way to hide this, because it isn't something they'd always want someone
           to be able to see "over their shoulder". Hidden by default.

           Moved out to an include file 2020-01-29 JM

        */
        insertWorkOrderTimeSummary($workOrder);

    } // END // There *is* a billing profile associated with this invoice
    ?>
    </div>
</div>

<?php
include_once BASEDIR . '/includes/footer.php';
?>