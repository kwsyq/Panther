<?php
/*  contract.php

    EXECUTIVE SUMMARY: This is a top-level page. Displays or updates info about a contract + workOrder.
    There is a RewriteRule in the .htaccess to allow this to be invoked as just "contract/foo" rather than "contract.php?workOrderId=foo".

    Requires admin-level permission for contract.

    PRIMARY INPUTS:
        * $_REQUEST['workOrderId'] (mandatory),
        * $_REQUEST['contractId'] (optional, if missing or zero, we get the contract from the workOrder,
                                   or create a new contract if the workOrder previously lacked one).

    OPTIONAL INPUTS:
        * $_REQUEST['act']. Possible values: 'updateContract', 'commit', 'Direction', 'addProfile', 'removeProfile' (added 2020-02-14 JM)
            * For 'updateContract' or 'commit', also uses
                * $_REQUEST['termsId']
                * $_REQUEST['contractDate']
                * $_REQUEST['nameOverride']
                * $_REQUEST['contractLanguageId']
                * $_REQUEST['clientMultiplier']
                * $_REQUEST['addressOverride'].
                * (just for commit) $_REQUEST['commitNotes']
               All of these have blanks or zeroes as defaults; either 'updateContract' or 'commit' updates all of them.

               THERE ARE ALSO:
                * other $_REQUEST parameters containing '::' in their keys, related to workOrders/tasks.
                  Some of these are arrays for workOrderTasks, though if I (JM) understand correctly, only
                  the first value is significant. They are best understood by looking at the form that submits them

            * For 'Direction', also uses:
                * $_REQUEST['workOrderTaskId']
                * $_REQUEST['direction'].

            * For 'addProfile' or 'removeProfile, also uses:
                & $_REQUEST[' billingProfileId']

            After any action except 'updateContract', we reload the page instead of falling through to
               display. This prevents refresh from performing the action a second time.

    */

require_once './inc/config.php';
require_once './inc/perms.php';

$error = '';
$errorId = 0;
$error_is_db = false;
$db = NULL;

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();


$checkPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_RWAD);

if (!$checkPerm){
    // // No admin-level permission for contract, redirect to '/panther'
    header("Location: /panther");
} 
$db = DB::getInstance();

$contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (!intval($workOrderId)){
    // No valid workOrderId, redirects to '/'.
    // (No real reason to redirect to two different places.)
    header("Location: /");
}


$crumbs = new Crumbs(null, $user);
$workOrder = new WorkOrder($workOrderId, $user);

if (!intval($workOrder->getWorkOrderId())) {
    // Invalid workOrderId, redirects to '/'.
    header("Location: /");
}

// The following is a bit tricky: we take advantage of having a method to
//  build a page link for a workOrder, then massage that link to instead load
//  *this* page. This takes advantage of the RewriteRule described above in the
//  comment at the head of this file.
// >>>00006 this should probably be be pushed down to a method in the WorkOrder class rather than ad hoc in the presentation layer.
$contractLink = $workOrder->buildLink();
$contractLink = str_replace("/workorder/", "/contract/", $contractLink);


$contract = $workOrder->getContract($contractId);

if(!$contract->getContractLanguageId()){
    $bp=$contract->getBillingProfiles();
    if(count($bp)>0){
       
        $billingProfile = new BillingProfile($contract->getBillingProfiles()[0]['contractBillingProfileId']);
        $updateData=array();
        
        $updateData['contractLanguageId']=$billingProfile->getContractLanguageId();
        $updateData['termsId']=$billingProfile->getTermsId();

        $contract->update($updateData);
    }
}

//$contract = new Contract($contractId, $user);
//var_dump($contract->buildLink());

if ($act == 'addprofile') {
    $billingProfileId = isset($_REQUEST['billingProfileId']) ? intval($_REQUEST['billingProfileId']) : 0;

    if (intval($billingProfileId)) {
        $b = new BillingProfile($billingProfileId);
        if (intval($b->getBillingProfileId())) {
            // THE FOLLOWING IS REWRITTEN 2020-10-30 JM using the now ShadowBillingProfile class
            $shadowBillingProfile = ShadowBillingProfile::constructFromBillingProfile($b);

            $query = "INSERT INTO " . DB__NEW_DATABASE . ".contractBillingProfile (";
            $query .= "contractId, billingProfileId, shadowBillingProfile, companyPersonId";
            $query .= ") VALUES (";
            $query .= intval($contract->getContractId());
            $query .= ", " . intval($billingProfileId);
            $query .= ", '" . $db->real_escape_string($shadowBillingProfile->getShadowBillingProfileBlob()) . "'";
            $query .= ", " . intval($user->getUserId()) . ");";

            $result = $db->query($query); // >>>00002 $result still needs error checking

            // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
            header("Location: " . $contractLink);
        }
    }
}

if ($act == 'removeprofile') {
    $billingProfileId = isset($_REQUEST['billingProfileId']) ? intval($_REQUEST['billingProfileId']) : 0;
    if (intval($billingProfileId)) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".contractBillingProfile " .
                 "WHERE contractId=" . intval($contract->getContractId()) . " " .
                 "AND billingProfileId=" . intval($billingProfileId) . ";";
        $result = $db->query($query); // >>>00002 $result still needs error checking

        // Reload the page instead of falling through to display. This prevents refresh from performing the action a second time.
        header("Location: " . $contractLink);
    }
}

// The following amounts to "If the contractId wasn't passed in explicitly, then this contract is editable."
// >>>00001 However, JM says that in conversation Martin said this may be just a temporary expedient.
//  "It is at is it because that's how to make this system usable while it's still under development."
//  Don't be surprised if this rule needs to be rethought.
/*
if (intval($contractId)) {
    $editable = 0;
} else {
    $editable = 1;
} */

// New editable logic. George 2021-12-15.
// Get personId of the reviewr.
$reviewer = Contract::getContractReviewerId($contract->getContractId());

// The following amounts to "If the contract status is Draft and no personId as reviewer then this contract is editable."
// OR "If the contract has a personId as reviewer then this contract is editable."
/*if( ( $contract->getCommitted() == 0 && intval($reviewer) == 0) || ( intval($reviewer) == intval($user->getUserId())) ) {
    $editable = 1;
} else {
    $editable = 0;
}

if( ( $contract->getCommitted() == 3 )) {
    $editable = 0;
}*/


// WORK IN PROGRESS
$enabledBtnStatus = 0;
$editable = 0;
if( ( $contract->getCommitted() == 0 && intval($reviewer) == 0) || ( intval($reviewer) == intval($user->getUserId())) ) {
    $editable = 1;
}
if( ( $contract->getCommitted() == 3 ) ) { // delivered
    $editable = 0; // not editable;
    if( intval($reviewer) == intval($user->getUserId())) {
        $enabledBtnStatus = 1; // btns status editable 
    }
  
}
 if( ( $contract->getCommitted() == 4) ) { // signed
    $editable = 0;
} 
 if( ( $contract->getCommitted() == 5) ) { // void
    $editable = 0;
    if( intval($reviewer) == intval($user->getUserId())) {
        $enabledBtnStatus = 1; // btns status editable
    }
}

if( ( $contract->getCommitted() == 6) ) { // voided
    $editable = 0;
}
//$editable=1;
// WORK IN PROGRESS
$disabled = $editable==1 ? "" : "disabled"; // Disabled Buttons.

$blockAdd = false; // if true, Block add/delete tasks/structures of tasks.


if($contract) {
    $contractStatus = intval($contract->getCommitted()); // Contract status
}


// no update for: 3, 4, 5, 6.
$arrNoUpdate = [3, 4, 5, 6];
if($contractStatus && in_array($contractStatus, $arrNoUpdate)) {
    $blockAdd = true;
}


// END  editable logic.


// Set $needProfile true if contract is uncommitted and doesn't have any billing profile.
$needProfile = false;
if (!$contract->getCommitted()) {
    // The name getBillingProfiles is a bit misleading: the return, if not an empty array,
    //  is a single-element array, containing an associative array with the canonical representation
    //  of a row from DB table contractBillingProfile (not BillingProfile);
    // JM 2020-10-30 I've accordingly renamed some variables here to try to be bit clearer what is going on,
    //  in the process of introducing the shadowBillingProfile class.
    //  Also, note that here we are in a conditional, but lower down we make the same assignment unconditionally
    //  >>>00001 that probably deserves study and cleanup.
    $contractBillingProfiles = $contract->getBillingProfiles();
    if (!count($contractBillingProfiles)) {
        $needProfile = true;
    }
}

// George 2021-12-15. Removed.
//if (($act == 'updateContract') || ($act == 'commit')) {
// End Removed.

// Actions: add default note, add note, add notification, add personId as reviewer, update contract.
if ($act == 'addPersonContract' && isset($_POST['changeStatus'])) {
 

    $contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;
    $contractStatus = isset($_REQUEST['contractStatusIdUpdate']) ? intval($_REQUEST['contractStatusIdUpdate']) : 0;
    // current status
    $contractStatusId = Contract::getContractStatusName($contract->getCommitted());

    // Default Note on Change status.
    $noteType = 2; // default note type. 

    // Status update change status to $contractStatus
    $defaultNote = "changed status to " . Contract::getContractStatusName($contractStatus);



    $query = "INSERT INTO " . DB__NEW_DATABASE . ".contractNote (";
    $query .= "contractId, contractStatus, note, noteType, personId) VALUES (";

    $query .= intval($contractId);
    $query .= ", " . intval($contract->getCommitted());
    $query .= ", '" . $db->real_escape_string($defaultNote) . "' ";
    $query .= ", " . intval($noteType);
    $query .= ", " . $user->getUserId();
    $query .= ");";

    $result = $db->query($query);
    if (!$result) {
        $errorId = '637747271106811037';
        $error = "We colud not add a contract Note. Database error. Error id: " . $errorId ;
        $logger->errorDb($errorId, $error, $db);
    }

 
    // Add Note on Change status.
    $note = isset($_REQUEST['noteTextCtr']) ? trim($_REQUEST['noteTextCtr']) : "";
    $note = truncate_for_db ($note, 'contractNote', 1000, '637747259508965829'); 

    if($note != "") {
        $noteType = 1; // status note.
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".contractNote (";
        $query .= "contractId, contractStatus, note, noteType, personId) VALUES (";
    
        $query .= intval($contractId);
        $query .= ", " . intval($contract->getCommitted());
        $query .= ", '" . $db->real_escape_string($note) . "' ";
        $query .= ", " . intval($noteType);
        $query .= ", " . $user->getUserId();
        $query .= ");";
    
        $result = $db->query($query);
        if (!$result) {
            $errorId = '637747261025189149';
            $error = "We colud not add a contract Note. Database error. Error id: " . $errorId ;
            $logger->errorDb($errorId, $error, $db);
        }
        
    } 
  
   
    if(!$error) {

        // Add Notification on Change status.
        $reviewerPersonId = isset($_REQUEST['personIdCtr']) ? intval($_REQUEST['personIdCtr']) : 0;

        if($reviewerPersonId) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".contractNotification (";
            $query .= "contractId, contractStatus, reviewerPersonId, personId, reviewStatus) VALUES (";
        
            $query .= intval($contractId);
            $query .= ", " . intval($contractStatus);
            $query .= ", " . intval($reviewerPersonId);
            $query .= ", " . $user->getUserId();
            $query .= ", " . 0;
            $query .= ");";
        
            $result = $db->query($query);
            if (!$result) {
                $errorId = '637747447816083074';
                $error = "We colud not add a reviewer. Database error. Error id: " . $errorId ;
                $logger->errorDb($errorId, $error, $db);
            } 
        }

        if(!$error) {
            // no update for: 3, 4, 5, 6.
            $arrNoUpdate = [3, 4, 5, 6];
            if( !in_array(intval($contract->getCommitted()), $arrNoUpdate)) {
                $termsId = isset($_REQUEST['termsId']) ? intval($_REQUEST['termsId']) : 0;
                //$contractDate = isset($_REQUEST['contractDate']) ? $_REQUEST['contractDate'] : '';
                $nameOverride = isset($_REQUEST['nameOverride']) ? $_REQUEST['nameOverride'] : '';
                $contractLanguageId = isset($_REQUEST['contractLanguageId']) ? intval($_REQUEST['contractLanguageId']) : 0;
                $clientMultiplier = isset($_REQUEST['clientMultiplier']) ? $_REQUEST['clientMultiplier'] : '';
                $addressOverride = isset($_REQUEST['addressOverride']) ? $_REQUEST['addressOverride'] : '';  
                $hourlyRate = isset($_REQUEST['hourlyRate']) ? intval($_REQUEST['hourlyRate']) : 0; 
            
                // method in class has no explicit return of true/ false.
                $contract->update(array(
                        'termsId' => $termsId,
                        //'contractDate' => $contractDate,
                        'nameOverride' => $nameOverride,
                        'addressOverride' => $addressOverride,
                        'contractLanguageId' => $contractLanguageId,
                        'clientMultiplier' => $clientMultiplier,
                        'committed' => $contractStatus,
                        'hourlyRate' => $hourlyRate,
                        'IncrementEditCount' => 1
                        ));
            } else {


                // status Signed or Voided. Save contract Data.
                if(intval($contractStatus) == 4 || intval($contractStatus) == 6) {
                    $dataContract = [];
                    $outData  = getContractData($workOrderId, $error_is_db);

                    if($error_is_db) {
                        $errorId = '637804395384266079';
                        $error = "We could not get the Contract data. Database Error. Error Id: " . $errorId; // message for User
                        $logger->errorDB($errorId, "getContractData() function failed.", $db);
                    } else {
                        $dataContract = [ $contractStatus => $outData];
                        $dataContractJson = json_encode($dataContract);
                        $contract->update(array(
                            // send contract data for signed or voided.
                            'data' => $dataContractJson,
                        ));
                    }
              
                
                }
                if(!$error) {
                    $contract->update(array(
                        //'contractDate' => $contractDate, // removed.
                        'committed' => $contractStatus,
                        'IncrementEditCount' => 1
                    ));
                }
            



            }
            if(!$error) {
                header("Location:" . $contract->buildLink());
                die();
            }
        }

    }


  

   /*   George 2021-12-15. Removed.
   
   $formWorkOrderTasks = array();

    // other $_REQUEST parameters containing '::' in their keys, related to workOrders/tasks.
    // Some of these are arrays for workOrderTasks, though if I (JM) understand correctly, only
    // the first value is significant. They are best understood by looking at the form that submits them.
    //var_dump($_REQUEST);
    foreach ($_REQUEST as $key => $val) {
 
        $pos = strpos($key, "::");
        if ($pos !== false) {
            if (!is_array($val)) {
                $val = array($val);
            }
            if (is_array($val)) {
                $parts = explode("::", $key);

                // [BEGIN MARTIN COMMENT]
                // this is a bit of a kludge for when
                // multiples come through with the same workordercategorytaskid
                // for when the same thing appears in the form
                // due to it being listed more than once
                // due to it being associated to different elements
                // this should just eliminate overwrites by
                // fields further down the form that have the same id.
                // i.e. for now just use the first occurence of that id in the form
                // [END MARTIN COMMENT]

                if (count($parts) == 2) {
                    if (intval($parts[1]) && strlen($parts[0])) {
                        foreach ($val as $v) {

                            // [BEGIN MARTIN COMMENT]
                            // for now just iterate through and use the first one .. by checking if
                            // the result array is already set.

                            // in future maybe do something a little more smart
                            // to check if a user has put entries in both fields in the form.
                            // [END MARTIN COMMENT]

                            if (!isset($formWorkOrderTasks[$parts[1]][$parts[0]])) {
                                $formWorkOrderTasks[$parts[1]][$parts[0]] = $v;
                            }
                        }
                    }
                }
            }
        }
    }

    // >>>00001, >>>00014 As of 2019-03 JM, overlay is one of the biggest remaining mysteries in the code.
    $overlaid = overlay($workOrder, $contract, $formWorkOrderTasks);

    $contract->update(array('data' => $overlaid)); 
    End REMOVE */
    
} // END common code for addPersonContract.

    /* [BEGIN MARTIN COMMENT]

    still need to populate the original data
    with the new Task class stuff

    specifically $workOrder->getWorkOrderTasks

    whatever is in contract->getData (that should just be from  $workOrder->getWorkOrderTasks)

    I THINK THE LOGIC NEEDS TO BE IS TO GRAB "LIVE" DATA
    AND STORED DATA IF THERE IS ANY THEN GO THROUGH STORED DATA
    AND OVERLAY ANY OF IT ONTO THE LIVE DATA
    FOR PURPOSES OF GENERATING THE FORM ON THE PAGE.
    THEN WHEN UPDATING GRAB ALL THE FORM DATA AND STORE IT AS NECESSARY OVER
    PROBSBLY ANOTHER "LIVE" GRAB WHEN STORING.

    [END MARTIN COMMENT]
    */
/*   George 2021-12-15. Removed. */
/*
if ($act == 'commit') {
    // NOTE that this note applies to the *prior* contract
    $commitNotes = isset($_REQUEST['commitNotes']) ? trim($_REQUEST['commitNotes']) : '';
    if ($commitNotes) {
        $priorContracts = $workOrder->getContracts(); // gets array of committed contracts, as contracts, in forward chronological order
        if ($priorContracts) {
            $numPriorContracts = count($priorContracts);
            $latestPriorContract = ($priorContracts[$numPriorContracts-1]);
            $latestPriorContract->setCommitNotes($commitNotes);
            $latestPriorContract->save(false);  // Per http://bt.dev2.ssseng.com/view.php?id=251, added arg 'false'. We don't want
                                                // to affect the editCount of the prior contract, and we have to pass something.
            $logger->info2('1580168695', "Note for overridden contract " . $latestPriorContract->getContractId() .
                   " for workOrder $workOrderId: '$commitNotes'");
        }
    }

    // [Martin comment] this simply flags the most recent uncommitted entry in contract table for this work order as committed now
    // Quotations placed around 'commitNotes' in next line are a bug fix for http://bt.dev2.ssseng.com/view.php?id=226, 2020-098-20 JM
    $ret = $contract->update(array('committed' => 1, 'commitNotes' => '')); // >>>00002 we should look at $ret & have some sane policy on error.
    $logger->info2('1580168705', "Committing contract " . $contract->getContractId() ." for workOrder $workOrderId");

    // After a 'commit' (but not after 'updateContract'), we reload
    header("Location:" . $contract->buildLink());
}

if ($act == 'updateContract') {
    // implicitly, fall through to usual display
} */

 /*END REMOVED */


include_once BASEDIR . '/includes/header.php';
echo "<!-- WorkOrder {$workOrder->getWorkOrderId()}, overt contractId $contractId, actual contractId {$contract->getContractId()} -->\n";
echo "<script>\ndocument.title = 'Contract: ". str_replace("'", "\'", $workOrder->getDescription()) . "';\n</script>\n";


if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
// Housekeeping to get job and contract date.
$job = new Job($workOrder->getJobId());
$contractDate = date_parse( $contract->getContractDate());
$contractDateField = '';

if (is_array($contractDate)) {
    if (isset($contractDate['year']) && isset($contractDate['day']) && isset($contractDate['month'])) {
        $contractDateField = intval($contractDate['month']) . '/' . intval($contractDate['day']) . '/' . intval($contractDate['year']);
        if ($contractDateField == '0/0/0'){
            $contractDateField = '';
        }
    }
}

if (!strlen($contractDateField)){
    $contractDateField = date("n/j/Y");
}

// Get and unserialize any shadow billing profile
$shadowBillingProfile = false;
$contractBillingProfile = false;
$contractBillingProfiles = $contract->getBillingProfiles();
if (intval(count($contractBillingProfiles))) {
    $contractBillingProfile = $contractBillingProfiles[0];
    $shadowBillingProfile = new ShadowBillingProfile($contractBillingProfile['shadowBillingProfile']);
}

?>
<script src='/js/kendo.all.min.js' ></script>
<link rel="stylesheet" href="../styles/kendo.common.min.css" />
<link rel="stylesheet" href="../styles/kendo.material-v2.min.css" />
<script src='https://cdnjs.cloudflare.com/ajax/libs/jeditable.js/1.7.3/jeditable.min.js'> </script>
<link rel="stylesheet" href="https://kendo.cdn.telerik.com/2021.2.616/styles/kendo.default-v2.min.css" />

<script type="text/javascript">

kendo.pdf.defineFont({
            "DejaVu Sans": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans.ttf",
            "DejaVu Sans|Bold": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Bold.ttf",
            "DejaVu Sans|Bold|Italic": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Oblique.ttf",
            "DejaVu Sans|Italic": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Oblique.ttf",
            "WebComponentsIcons": "https://kendo.cdn.telerik.com/2017.1.223/styles/fonts/glyphs/WebComponentsIcons.ttf"
        });



<?php /* Use the current selection within $('#contractLanguageId') to choose
         a file to show by navigating to /_admin/contractlanguage/getuploadfile.php?f=filename */

?>
var viewLanguageFile = function () {
    var sel = $('#contractLanguageId :selected').text();
    if (sel.length > 5) {
        location.href = '/_admin/contractlanguage/getuploadfile.php?f=' + escape(sel);
    }
}

$(function() {
    $( ".datepicker" ).datepicker();
});

<?php /* 
George 2021-12-15. This will be REMOVED

Set arrow direction for a given workOrderTask. In practice,
         used only to toggle to the other arrow direction. Amend form content
         accordingly and immediately self-submit the form, causing an update. 
         
*/ ?>


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



<?php /* 2020-01-27 JM: "commit" comment sufficiently reworked that I'm not keeping the old code around.
         The idea now is that on a commit, if there was a prior committed contract, we'll want to apply a
         comment TO THE CONTRACT WE ARE OVERRIDING.

         Also, it is possible to cancel out of the commit here.

         "Commit" copies from the textarea into the hidden
         note in contractform, sets its 'act' to 'commit' (vs. 'updateContract'), and submits the form.
         */
$priorContracts = $workOrder->getContracts(); // gets array of committed contracts, as contracts, in forward chronological order
if ($priorContracts) {
    $numPriorContracts = count($priorContracts);
    $latestPriorContract = ($priorContracts[$numPriorContracts-1]);
    ?>
    <script>
        function attemptCommit () {
            $commitDialog = $('<div id="commitComment">' +
                'Please add a comment as to why the previous version of this contract is overridden. <br />' +
                '<textarea name="cn" id="cn" cols="40" rows="4"></textarea>' +
            '</div>').dialog({
                title       : 'Comment for old contract',
                width       : 500,
                height      : 400,
                modal       : true,
                buttons     : {
                    'Cancel' : function() {
                        $commitDialog.dialog('close');
                    },
                    'Commit' : function() {
                        let $cf = $("#contractform");
                        let $cn = $("#cn"); // NOTE that beginning 2020-01-27 JM, this note will apply to the *prior* contract
                        $('input[name="act"]', $cf).val('commit');
                        $('input[name="commitNotes"]', $cf).val($cn.val().trim());
                        $cf.submit();

                        $commitDialog.dialog('close');
                    }
                }
            });
        }; // END function attemptCommit";
    </script>
<?php
} else {
?>
<script>
    function attemptCommit() {
        let $cf = $("#contractform");
        $('input[name=\"act\"]', $cf).val('commit');
        //$cf.submit();
    }
</script>
<?php
}

/* Now we finally get to the main display */ ?>
<div id="container" class="clearfix">
    <?php
        $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/contract/' . rawurlencode($contractId);
    ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
           
            [WO]&nbsp;<a id="linkWoToCopy" href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            (Co)&nbsp;Contract&nbsp;(<a href="<?php echo $contract->buildLink(); ?>"><?php echo $contract->getContractId();?></a>)
            <button id="copyLink" title="Copy Contract link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>    
        <span id="linkToCopy" style="display:none"> 
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
            [WO]&nbsp;<a href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            (Co)<a href="<?php echo $contract->buildLink(); ?>">&nbsp;Contract&nbsp;(<?php echo $contract->getContractId();?></a>)&nbsp;
        </span>

        <span id="linkToCopy2" style="display:none"> <a href="<?= $urlToCopy?>">(Co)&nbsp;Contract&nbsp;<?php echo $contract->getContractId();?>
            [WO]&nbsp; <?php echo $workOrder->getDescription();?> </a></span>
            <span style="float: right; margin-right: 50px; padding-top: 10px; font-weight: 600">
            <?php echo Contract::getContractStatusName($contract->getCommitted());?>            
            </span>
    </div>
    <div class="clearfix"></div>
    
    <div class="main-content">

    <?php
    if ($needProfile) {
        $profileImpossible = false;
        // Considerably reworked 2020-02-13 JM to account better for the case where there is only one active billing profile,
        //  in which case we use that without asking.
        $tp = $workOrder->getTeamPosition(TEAM_POS_ID_CLIENT, 0, 1);
        if (count($tp)) {
            // we have a client
            $client = $tp[0]; // If there is exactly one client, this is correct, but (>>>00026 / >>>00032) maybe we
                              // want to account for other cases as well.
            $cp = new CompanyPerson($client['companyPersonId']);
            if (intval($cp->getCompanyPersonId())) {
                $cmp = $cp->getCompany();
                $cbps = $cmp->getBillingProfiles(true); // true => active only, added 2020-02-13 JM
                $numActiveCbps = count($cbps);

                if ($numActiveCbps == 1) {
                    // Exact equivalent of act=addprofile case
                    $billingProfileId = $cbps[0]['billingProfile']->getBillingProfileId();
                    $shadowBillingProfile = ShadowBillingProfile::constructFromBillingProfile($cbps[0]['billingProfile']); // NOTE that this variable has a broad scope in the file

                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".contractBillingProfile (contractId, billingProfileId, shadowBillingProfile, companyPersonId)";
                    $query .= " VALUES (";
                    $query .= intval($contract->getContractId()) ;
                    $query .= ", " . intval($billingProfileId);
                    $query .= ", '" . $db->real_escape_string($shadowBillingProfile->getShadowBillingProfileBlob()) . "'";
                    $query .= ", " . intval($user->getUserId());
                    $query .= ");";

                    $result = $db->query($query);
                    if ($result) {
                        $needProfile = false;
                    } else {
                        $logger->errorDb('1581701285', 'Could not insert unique billing profile into contract', $db);
                    }
                }
                else if ($numActiveCbps == 0) {
                    // No active billing profiles; are we going to be able to form one?
                    $foundContact = false;
                    $c_id = $cp->getCompanyId();
                    $p_id = $cp->getPersonId();

                    $query = "SELECT companyEmailId FROM " . DB__NEW_DATABASE . ".companyEmail ";
                    $query .= "WHERE companyId=$c_id;";
                    $result = $db->query($query);
                    // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
                    if ($result->num_rows > 0) {
                        $foundContact = true;
                    }

                    if (!$foundContact) {
                        $query = "SELECT companyPhoneId FROM " . DB__NEW_DATABASE . ".companyPhone ";
                        $query .= "WHERE companyId=$c_id;";
                        $result = $db->query($query);
                        // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
                        if ($result->num_rows > 0) {
                            $foundContact = true;
                        }
                    }

                    if (!$foundContact) {
                        $query = "SELECT personEmailId FROM " . DB__NEW_DATABASE . ".personEmail ";
                        $query .= "WHERE personId=$p_id;";
                        $result = $db->query($query);
                        // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
                        if ($result->num_rows > 0) {
                            $foundContact = true;
                        }
                    }

                    if (!$foundContact) {
                        $query = "SELECT personPhoneId FROM " . DB__NEW_DATABASE . ".personPhone ";
                        $query .= "WHERE personId=$p_id;";
                        $result = $db->query($query);
                        // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
                        if ($result->num_rows > 0) {
                            $foundContact = true;
                        }
                    }

                    if (!$foundContact) {
                        $profileImpossible = true;
                        echo '<p>Client ' . $cp->getName() . 'does not have an email address or a phone number, ' .
                             'so we cannot build a Billing Profile Templates. ' .
                             'Go to <a id="linkCp" href="companyperson/<?= $cp->getCompanyPersonId() ?>"> the page for that companyPerson</a> ' .
                             'to add a phone number and/or email address.<p>';
                    }
                }
            }
        }
    }


    if ($needProfile && !$profileImpossible) {
       /* There is no billing profile associated with this contract, and we weren't able to solve this by picking a unique one just above. Displays:
        * (as header) "Contract: workOrder description"
        * link labeled "[Back To Work Order]" that does just what it says
        * "You need to attach a billing profile template to this contract:"
        * Checks whether there is a client associated with this workOrder.
          Ignores any that are not "active" per WorkOrder::getTeamPosition.
            * If no client (type: companyPerson) associated with this workOrder:
              "There is nobody on the workorder or job team in the role of 'client' so there
              are no Billing Profile Templates to refer back to. Go and add a client in the applicable place!"
            * If there is more than one active client associated with this workOrder, we look only at the first one.
            * If there is an active client associated with this workOrder:
                * Identify company from client and look for billing profiles associated with that company.
                    * If there are no such billing profiles: "The company associated with the client has no billing
                      profile templates. You'll need to go and add one (go to company)", where "go to company" is a
                      link to the appropriate company page.
                    * If there is at least one such billing profile, "Choose one", followed by a table with the
                      following columns, and a row for each such billing profile:
                        * Name: Formatted name of person associated with this billing profile
                        * Location: location ('loc') associated with this billing profile
                        * (no heading): self-submitting link labeled "use this".
                           Link uses GET method back (effectively) to this page by using $contractLink with query string
                           act=addprofile&billingProfileId=billingProfileId; as noted above, $contractLink provides workOrder,
                           which necessarily means we will reload with the same contract.
        */
        if (!count($tp)) {
            echo '<p>There is nobody on the workorder or job team in the role of "client" '.
                 'so there are no Billing Profile Templates to refer back to. ' .
                 'Go <a id="linkBackWo" href="'. $workOrder->buildLink(). '"> back To Work Order</a> and add a client in the applicable place!<p>';
                 // >>>00032 ought to link to those applicable places.
        } else if (intval($cp->getCompanyPersonId())) {
            ?>
            <div class="full-box clearfix">
                <h1>Contract: <?php echo $workOrder->getDescription();?></h1>
                <a id="backWo" href="<?php echo $workOrder->buildLink(); ?>">[Back To Work Order]</a>
                <br />
                <p>You need to attach a billing profile template to this contract:</p>
                <?php
                // $cp was set above, as part of seeing if there was a unique profile
                if (intval($cp->getCompanyPersonId())) {
                    // $cmp, $cbps, $numActiveCbps were set above, as part of seeing if there was a unique profile
                    if ($numActiveCbps == 0) {
                       
                        echo '<p>The client\'s company, <a id="companyNameBilling" href="' . $cmp->buildLink() . '" target="_blank"> '. $cmp->getCompanyName() . '</a> has no billing profile templates.<br />' . "\n";
                        echo '<a data-fancybox-type="iframe" id="linkAddBilling' . $cmp->getCompanyId() . '" class="button fancyboxIframe" ' .
                             'href="/fb/addbillingprofile.php?companyId=' . $cmp->getCompanyId() . '">Add billing profile</a>' . "\n";
                    } else {
                        $primaryBillingProfileId = $cmp->getPrimaryBillingProfileId();

                        echo '<p>Choose One</p>';
                        echo '<table border="0">';
                            echo '<tr>';
                                echo '<th>Name</th>';
                                echo '<th>Location</th>';
                                echo '<th>&nbsp;</th>';
                            echo '</tr>';

                            foreach ($cbps as $cbp) {
                                $billingProfileId = $cbp['billingProfile']->getBillingProfileId();
                                echo '<tr>';
                                $cpid = $cbp['billingProfile']->getCompanyPersonId();
                                    $isPrimary = $billingProfileId == $primaryBillingProfileId;
                                    echo '<td>';
                                        if (intval($cpid)) {
                                            $cp = new CompanyPerson($cpid);
                                            $p = $cp->getPerson();
                                            echo $p->getFormattedName(1);
                                        }
                                    echo '</td>';
                                    echo '<td>';
                                        echo $cbp['loc'];
                                    echo '</td>';
                                    echo '<td><a id="addProfile' . $cbp['billingProfile']->getBillingProfileId() . '" href="' . $contractLink . '?act=addprofile&billingProfileId=' .
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
        // There *is* a billing profile associated with this contract
        // $db = DB::getInstance(); // Removed 2019-12-11 JM, we now do this in one place above.

        // [BEGIN MARTIN COMMENT]
        // logic needs to go here regarding the editCount.
        // so if its been updated before then the editCount goes up
        // and the contract no longer looks to the billing profile template
        // for the meta data that is contained in the billing profile template.
        // it just goes with whatever is stored in the "data" blob for the contract
        // [END MARTIN COMMENT]

        $editCount = $contract->getEditCount();
        //$editCount=1;
// [MARTIN COMMENT] WHY IS EDIT COUNT GOING UP BY  2 DURING EVERY UPDATE... the update is called twice for some reason
// JM: Despite Martin's concern about editCount not being correctly maintained,
//  it is at least correctly zero or non-zero, and can therefore be used as a Boolean to determine
//  whether we get certain values (termsId, multiplier, location) from the contract itself
//  (if editCount is nonzero) or from the billing profile. I'm pretty sure that's all we care about it.
    
        $formTermsId = 0;
        if (intval($editCount)) {
            $formTermsId = $contract->getTermsId();
        } else {
            $formTermsId = $shadowBillingProfile->getTermsId();
        }

        $formContractLanguageId = 0;
        if (intval($editCount)) {
            $formContractLanguageId = $contract->getContractLanguageId();
        } else {
            $formContractLanguageId = $shadowBillingProfile->getContractLanguageId();
        }

        $formClientMultiplier = 0;
        if (intval($editCount)) {
            $formClientMultiplier = $contract->getClientMultiplier();
        } else {
            $formClientMultiplier = $shadowBillingProfile->getMultiplier();
        }

        $loc = '';

        if ($shadowBillingProfile->getCompanyPersonId()) {
            $cp = new CompanyPerson($shadowBillingProfile->getCompanyPersonId());
            if (intval($cp->getCompanyPersonId())) {
                $pp = $cp->getPerson();
                $loc = $pp->getFormattedName(1);
            }
        }

        if ($shadowBillingProfile->getPersonEmailId()) {
            $query = "SELECT emailAddress FROM  " . DB__NEW_DATABASE . ".personEmail ";
            $query .= "WHERE personEmailId = " . $shadowBillingProfile->getPersonEmailId() . ";";

            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (strlen($loc)) {
                        $loc .= "\n";
                    }
                    $loc .= $row['emailAddress'];
                }
            } // >>>00002 ignores failure on DB query! Does this throughout file,
              // haven't noted each instance.
        }

        if ($shadowBillingProfile->getPersonLocationId()) {
            $query = "SELECT locationId FROM  " . DB__NEW_DATABASE . ".personLocation ";
            $query .= "WHERE personLocationId = " . $shadowBillingProfile->getpersonLocationId() . ";";

            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0){
                    $row = $result->fetch_assoc();
                    if (strlen($loc)) {
                        $loc .= "\n";
                    }
                    $l = new Location($row['locationId']);
                    $loc .= $l->getFormattedAddress();
                }
            }
        }

        if ($shadowBillingProfile->getCompanyEmailId()) {
            $query = "SELECT emailAddress FROM  " . DB__NEW_DATABASE . ".companyEmail ";
            $query .= "WHERE companyEmailId = " . $shadowBillingProfile->getCompanyEmailId() . ";";

            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (strlen($loc)) {
                        $loc .= "\n";
                    }
                    $loc .= $row['emailAddress'];
                }
            }
        }

        if ($shadowBillingProfile->getCompanyLocationId()) {
            $query = "SELECT companyId, locationId FROM  " . DB__NEW_DATABASE . ".companyLocation ";
            $query .= "WHERE companyLocationId = " . $shadowBillingProfile->getCompanyLocationId() . ";";

            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (strlen($loc)) {
                        $loc .= "\n";
                    }
                    $c = new Company($row['companyId']);
                    if (strlen($c->getCompanyName())) {
                        $loc .= $c->getCompanyName();
                        $loc .= "\n";
                    }
                    $l = new Location($row['locationId']);
                    $loc .= $l->getFormattedAddress();
                }
            }
        }

        $addressString = '';
        if (intval($editCount)){
            $addressString = $contract->getAddressOverride();
        } else {
            $addressString = $loc;
        }
       
?>
        <?php /* --- Contract page header image, floated to the right; header: "Contract" */ ?>
        <img style="float:right; display: none" src="/cust/<?php echo $customer->getShortName(); ?>/img/pageheaderimages/contract_page_header_image.gif" width="32" height="32" border="0">
        <h3>Contract: <?php echo $workOrder->getDescription();?></h3>
<!--        <h5>Status: <?php //echo Contract::getContractStatusName($contract->getCommitted());?></h5>-->
        <div class="full-box clearfix">
            <?php /* --- "[Back To Work Order]", linking to the workOrder page */ ?>
<!--            <a id="backToWo" href="<?php //echo $workOrder->buildLink(); ?>">[Back To Work Order]</a>-->
            <?php
                /* --- Button, labeled "Print", linking to contractpdf.php and passing contractId */
                echo '<a id="printContract" class=" print show_hide btn btn-secondary mb-2 mt-2 " style="color:#fff; font-size:14px; font-weight: 500; margin-right: 0; right:0!important;"  href="/contractpdf.php?contractId=' . $contract->getContractId()  . '">&nbsp;&nbsp;Print</a>';
            ?>
            <?php
            if ($editable) {
            ?>
                <br />
                <input type="checkbox" id="submit-on-change" style="display:none" />&nbsp;<label for="submit-on-change"  style="display:none">"Autocalc": update immediately on each change.</label><br />
                <script>
                function applySubmitOnChange() {
                    if ($("#submit-on-change").prop('checked')) {
                        $("#contractform input, #contractform textarea, #contractform select").on("change.immediate", function() {
                            $("<div id=\"dialog\" title=\"autocalc\"><p>Recalculating</p></div>").dialog();
                            $("#contractform").submit();
                        });
                    } else {
                         $("#contractform input, #contractform textarea, #contractform select").off(".immediate");
                    }
                }
                $("#submit-on-change").change(appl<imgySubmitOnChange);
                </script>
            <?php
            }
            // Allow changing billing profile
            if (!$contract->getCommitted()) {
//            if (true) {
                $tp = $workOrder->getTeamPosition(TEAM_POS_ID_CLIENT, 0, 1);
                
                if (count($tp)) {
                    // We have a client. I (JM) think this will always be the case if we have a billing profile, but if
                    //  there is some edge case, I'm not going there.
                    $client = $tp[0];
                    $cp = new CompanyPerson($client['companyPersonId']);
                    if (intval($cp->getCompanyPersonId())) {
                        $cmp = $cp->getCompany();
                        $cbps = $cmp->getBillingProfiles(true); // true => active only, added 2020-02-13 JM
                        $otherPossibilitiesExist = false;
                        foreach ($cbps as $cbp) {
                            if ($cbp['billingProfile']->getBillingProfileId() != $shadowBillingProfile->getBillingProfileId()) {
                                $otherPossibilitiesExist = true;
                                break;
                            }
                        }
                        if ($otherPossibilitiesExist) {
                            echo '<a id="shadowBilling' . $shadowBillingProfile->getBillingProfileId() . '"  href="' . $contractLink . '>?act=removeprofile&billingProfileId=' . $shadowBillingProfile->getBillingProfileId() . '"><button class="btn btn-sm btn-secondary">Revisit billing profile</button></a>&nbsp;'.
                            'If this company has only one active billing profile, this will select it, if it has are several, then you can choose.<br/>' . "\n";
                        }
                    }
                }
            }
            ?>
            <br />
            <?php /* table inside a form;
                     Self-submitting form "contractform", using POST method:
                         * (hidden) act="updateContract"
                         * (hidden) commitNotes; these are edited indirectly via a form that is made visible when you click "Commit"
                         * Job #: Job Number
                         * Job Name Override: if editable, a text input, name="nameOverride"; otherwise, just text.
                           Initialized with nameOverride from contract.
                         * Date: if editable, a text input (name="contractDate") with a date picker, otherwise just text.
                           In "n/j/Y" form (month/day/year, no leading zeroes, e.g. '6/14/2019').
                           Initialized from contract; if nothing there, initialized to current date.
                      DESCRIPTION CONTINUES BELOW, THERE IS A LOT IN THIS FORM.
            */ ?>
            <form name="contract" id="contractform2" action="<?php echo $contract->buildLink(); ?>" method="post">
           
            <input type="hidden" name="commitNotes" value="" />
            <input type="hidden" name="contractId" value="<?php echo $contract->getContractId(); ?>" />
            <table border="0" class="thistable table table-sm" style="background-color: #fff" >
                <tbody>
<!--                    <tr >
                        <td nowrap>Job #</td>
                        <td width="90%" ><?php //echo htmlspecialchars($job->getNumber()); ?></td>
                    </tr>-->
                    <tr >
                        <td nowrap>Job Name Override</td>
                        <?php if ($editable) { ?>
                            <td width="90%"><input type="text" class="form-control form-control-sm"  id="nameOverride" name="nameOverride" value="<?php echo htmlspecialchars($contract->getNameOverride()); ?>" /></td>
                        <?php } else { ?>
                            <td width="90%"><?php echo htmlspecialchars($contract->getNameOverride()); ?></td>
                        <?php } ?>
                    </tr>
                    <tr >
                        <td nowrap>Date</td>
                        <?php if ($editable) { ?>
                            <td width="90%"><span type="text" class="form-control form-control-sm" id="contractDate"  class="datepicker" value="" ><?php echo htmlspecialchars($contractDateField); ?></span></td>
                        <?php } else { ?>
                            <td width="90%"><?php echo htmlspecialchars($contractDateField); ?></td>
                        <?php } ?>
                    </tr>
            <?php /* CONTINUING Self-submitting form "contractform":
                        * Terms: If editable, this is an HTML SELECT (dropdown, name="termsId").
                          All possible terms are offered. Each OPTION has a termsId as its value,
                          and displays the corresponding termName. The initial selection is the
                          terms associated with the billing profile or (if editCount>0) from the contract.
                          If not editable, just the text of the termName coming from the termsId associated
                          with the billing profile or (if editCount>0) from the contract.
                        * Language: If editable, this is an HTML SELECT (dropdown, name="contractLanguageId").
                          All possible contract language files are offered. Each OPTION has a contractLanguageId
                          as its value, and displays the corresponding fileName. The initial selection is the
                          contract language associated with the billing profile or (if editCount>0) from the contract.
                          Also if editable, this is followed by a link captioned '[view]'. If clicked, that
                          invokes a local function viewLanguageFile, which uses the current selection to choose
                          a file to show by navigating to /_admin/contractlanguage/getuploadfile.php?f=filename.
                          If not editable, just the text of the fileName coming from the contractLanguageId
                          associated with the billing profile or (if editCount>0) from the contract.
                        * Client Mult: currently (2019-03) never editable, though there is commented-out code to allow it to be.
                          Just the text representing the numeric clientMultiplier associated with the billing profile or
                          (if editCount>0) from the contract. Also a hidden input with name="clientMultiplier", and that same value.                       DESCRIPTION CONTINUES BELOW, THERE IS A LOT IN THIS FORM.
            */ ?>
                    <tr >
                        <td  nowrap>Terms</td>
                        <?php if ($editable) { ?>
                            <td width="90%"><select id="termsId" class="form-control form-control-sm" name="termsId"><option value="0"></option><?php
                            $terms = getTerms();
                            foreach ($terms as $term) {
                                
echo $formTermsId."-".$term['termsId']."\n\n\n\n\n";                                
                                $selected = (intval($formTermsId) == intval($term['termsId'])) ? ' selected ' : '';
                                echo '<option class="form-control form-control-sm" value="' . intval($term['termsId']) . '" ' . $selected . '>' . $term['termName'] . '</option>';
                            }
                        ?></select></td>
                        <?php } else {
                            echo '<td>';
                            $terms = getTerms();
                            foreach ($terms as $term) {
                                if (intval($formTermsId) == intval($term['termsId'])){
                                    echo $term['termName'];
                                }
                            }
                            echo '</td>';
                        } ?>
                    </tr>
                    <tr >
                        <td  nowrap>Language</td>
                        <?php if ($editable) { ?>
                            <td width="60%">
                            
                            <select class="form-control form-control-sm" name="contractLanguageId" id="contractLanguageId"><option value=""></option><?php
                            $files = getContractLanguageFiles();
                            foreach ($files as $file) {
                                $selected = ($formContractLanguageId == $file['contractLanguageId']) ? ' selected ' : '';
                                echo '<option class="form-control form-control-sm" value="' . intval($file['contractLanguageId']) . '" ' . $selected . '>' . htmlspecialchars($file['fileName']) . '</option>';
                            }
                            ?></select>&nbsp;<a class="btn btn-link" id="viewLanguageFile" href="javascript:viewLanguageFile()">View</a>
                            <button class="btn btn-sm btn-link" id="updateContractLanguage" type="button">Update</button>
                            </td>
                        <?php } else {
                            echo '<td>';
                            $files = getContractLanguageFiles();
                            foreach ($files as $file) {
                                if ($formContractLanguageId == $file['contractLanguageId']){
                                    echo $file['fileName'];
                                }
                            }
                            echo '</td>';
                        } ?>
                        </tr>
<!--                        <tr >
                            <td  nowrap>Client Mult.</td>
                            <?php /*
                            // >>>00032 JM This was commented out by Martin in 2018, but unlike most of his commented-out
                            //  code I would expect this to come back to life.
                            if ($editable)
                                <td width="90%"><input type="text" name="clientMultiplier" value="<?php echo htmlspecialchars($formClientMultiplier); ?>"></td>
                            } else {
                            */ ?>
                                <td width="90%"><?php //echo htmlspecialchars($formClientMultiplier); ?></td>
                                <input type="hidden" name="clientMultiplier" value="<?php  //echo htmlspecialchars($formClientMultiplier); ?>" />

                            <?php /* } */ ?>
                        </tr>
-->
                        <?php
                        /* CONTINUING Self-submitting form "contractform":
                        Address:
                        * If editable, this is a bit complex, though the complexity
                          is all hidden in $contract->getAddressOverride(), called above:
                            * textarea (input name="addressOverride") contains a concatenation of the following,
                              drawn directly or by joins from billing profile and its companyPerson,
                              with newlines between; some may not have defined values, and will be skipped.
                                * formatted person name
                                * email addresses for person (loops over any we find here, but in practice we will only ever get one, because we start from a personEmailId.)
                                * formatted location addresses for person (similarly)
                                * email addresses for company (similarly)
                                * formatted location addresses for company (similarly)
                        * If not editable, then any edits on this have already been done and it was
                          saved to the contract, so while content may be equally complicated it just
                          comes from addressOverride in the contract. This is plain text, rather than a textarea.
                        */
                        echo '<tr>';
                        echo '<td >Address</td>';
                        if ($editable){
                            echo '<td><textarea required class="form-control form-control-sm" id="addressOverride" name="addressOverride" cols="30" rows="3">' . htmlspecialchars($addressString) . '</textarea></td>';
                        } else {
                            echo '<td ><pre>';
                            echo $addressString;
                            echo '</pre></td>';
                        }
                        echo '</tr>';
                        /* CONTINUING Self-submitting form "contractform":
                           Next we go into a subtable, intended to describe the actual
                           tasks associated with this contract.

                           Comments are interspersed with the code.

                        */ 
                        $query = " SELECT hoursRate, hoursRateId, date ";
                        $query .= " FROM " . DB__NEW_DATABASE . ".extraHoursService ";

                        $hourlyRate = 0;

                        $result = $db->query($query);
                        if ($result) {
                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc(); 
                                $hourlyRate = intval($row['hoursRate']); // standard cost
                        
                            }
                        }
                        
                        ?>
                        <tr >
                            <td  nowrap>Hourly Rate</td>
                         
                            <?php  if ($editable){ ?>
                            <td width="30%">   <input style="width: 5%;"  min="1" class="form-control form-control-sm" id="hourlyRate" name="hourlyRate" value="<?php echo intval($contract->getHourlyRate()) ? intval($contract->getHourlyRate()) : $hourlyRate?>" /></td>
                            <?php } else { ?>
                                <td width="30%">   <span style="width: 5%;"  class="form-control form-control-sm" id="hourlyRate" name="hourlyRate" value="<?php echo intval($contract->getHourlyRate()) ? intval($contract->getHourlyRate()) : $hourlyRate?>"><?php echo intval($contract->getHourlyRate()) ? intval($contract->getHourlyRate()) : $hourlyRate?></span></td>
                            <?php }?>
                        </tr>
                    </table>
                  

<?php 

/*

    workOrderId: primary key in DB table workOrder.
    Get the data for an contract -> Gantt Tree.

    Returns JSON for an associative array with the following members:    
        * 'out': array. Each element is an associative array with elements:
            * 'elementId': identifies the element.
            * 'elementName': identifies the element name.
            * 'parentId': is null for the element, for a workorderTask is the id of the parent.
            * 'taskId': identifies the task.
            * 'parentTaskId': alias 'parentId', is null for the element, for a workorderTask is the id of the parent.
            * 'workOrderTaskId': identifies the workOrderTask.
            * 'billingDescription':  billing description for a specific workOrderTask.
            * 'icon':  icon for a specific workOrderTask.
            * 'cost':  cost for a specific workOrderTask.
            * 'totCost':  totCost for a specific workOrderTask.
            * 'taskTypeId':  type of a task ( table tasktype ).
            * 'wikiLink':  Link to Wiki for a specific workOrderTask.
            * 'taskStatusId':  status for a specific workOrderTask ( active / inactive ).
            * 'taskContractStatus':  status for a specific workOrderTask ( inactive on arrow down ).
            * 'quantity':  quantity for a specific workOrderTask, default 0.
            * 'hoursTime':  time in minutes for a specific workOrderTask, available in workOrderTaskTime.
            * 'hasChildren': identifies if a element/ woT has childrens.
*/



$query = "SELECT elementId as id, elementName as Title, null as parentId, 
null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, '' as billingDescription, null as cost, null as quantity, 
null as totCost, null as taskTypeId, '' as icon, '' as wikiLink, null as taskStatusId, 0 as taskContractStatus, null as hoursTime,  
elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
UNION ALL
SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId, 
w.extraDescription as extraDescription, w.billingDescription as billingDescription, w.cost as cost, w.quantity as quantity, w.totCost as totCost,
w.taskTypeId as taskTypeId, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId,  w.taskContractStatus as taskContractStatus, wt.tiiHrs as hoursTime,
getElement(w.workOrderTaskId),
e.elementName, false as Expanded, false as hasChildren
from workOrderTask w
LEFT JOIN task t on w.taskId=t.taskId


LEFT JOIN (

    SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
    FROM workOrderTaskTime wtH
    GROUP BY wtH.workOrderTaskId
    ) AS wt
    on wt.workOrderTaskId=w.workOrderTaskId


LEFT JOIN element e on w.parentTaskId=e.elementId
WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null AND w.internalTaskStatus != 5 ORDER BY FIELD(elementName, 'General') DESC";

$res=$db->query($query);

$out=[];
$parents=[];
$elements=[];

  
while( $row=$res->fetch_assoc() ) {
    $out[]=$row;
    if( $row['parentId']!=null ) {
        $parents[$row['parentId']]=1;
    }
    if( $row['taskId']==null)    {
        $elements[$row['elementId']] = $row['elementName'] ;

        
    }
  
}

//print_r($out);

for( $i=0; $i<count($out); $i++ ) {
  
    if( $out[$i]['Expanded'] == 1 )
    {
        $out[$i]['Expanded'] = true;
    } else {
        $out[$i]['Expanded'] = false;
    }
    
    if($out[$i]['hasChildren'] == 1)
    {
        $out[$i]['hasChildren'] = true;
    } else {
        $out[$i]['hasChildren'] = false;
    } 

    if( isset($parents[$out[$i]['id']]) ) {
        $out[$i]['hasChildren'] = true;
      
    }
    if ( $out[$i]['elementName'] == null ) {
        $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
    }

   

}


// Get task types: overhead, fixed etc.
$allTaskTypes = array();
$allTaskTypes = getTaskTypes();


// calculate cost per element and total cost.
$wo = new WorkOrder($workOrderId);
$jobId = $wo->getJobId();
$job = new Job($jobId);

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


$elementsCost = [];
$errorCostEl = '';
foreach($allElements as $value) {

        $query = "select workOrderTaskId,
        parentTaskId, totCost
        from    (select * from workOrderTask
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
$sumTotalEl = 0;

foreach($elementsCost as $key=>$el) {
    $elementsCost[$key] = array_sum($el);
    $sumTotalEl += array_sum($el);
}

// get Ids of Level One WOT
$levelOne = $workOrder->getLevelOne($error_is_db);
$errorWotLevelOne = "";  
if($error_is_db) { //true on query failed.
    $errorId = '637799252773313187';
    $errorWotLevelOne = "Error fetching Level One WorkOrderTasks. Database Error. Error Id: " . $errorId; // message for User
    $logger->errorDB($errorId, "getLevelOne() method failled", $db);
}

if ($errorWotLevelOne) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWotLevelOne</div>";
}
unset($errorWotLevelOne);


$levelOneTasks = [];
$errorWotLevelOneTasks = "";
foreach($levelOne as $value) {

    $query = "select workOrderTaskId,
    parentTaskId, taskContractStatus
    from    (select * from workOrderTask
    order by parentTaskId, workOrderTaskId) products_sorted,
    (select @pv := '$value') initialisation
    where   find_in_set(parentTaskId, @pv)
    and     length(@pv := concat(@pv, ',', workOrderTaskId))";

    $result = $db->query($query);

    if (!$result) {
        $errorId = '637800842774158005';
        $errorWotLevelOneTasks = 'We could not retrive the workorder tasks for level one. Database error. Error id: ' . $errorId;
        $logger->errorDb($errorId, 'We could not retrive the workorder tasks for level one', $db);
    }
    
    if(!$errorWotLevelOneTasks) {
        while( $row=$result->fetch_assoc() ) { 
            if($row['taskContractStatus'] == 9) {
                // this level 1 workOrderTaskId has at least one children with status 9. not display
                $levelOneTasks[] = $value; 
            }
        }
    }

    
}


if ($errorWotLevelOneTasks) {   
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWotLevelOneTasks</div>";
}
unset($errorWotLevelOneTasks);


?>
    <div><button class="btn btn-secondary btn-sm mb-2 mt-2 " type="button" style="color:#fff; float:right" id="updateForTotal" <?php echo $disabled; ?>>Update for Total</button></div>
    <div  class="clearfix" > </div>
    <div id="gantt" class="clearfix" > </div>
    <span class="font-weight-bold" id="totalContractCost" style="float:right; font-size:130%; padding-right:10px;">Total: <?php echo " " . number_format( $sumTotalEl, 2); ?></span>

 
<script>
$(document).ready(function() { 

    // Logic to Auto Save: after 2 seconds.
    // ** nameOverride, addressOverride, hourlyRate, termsId

    //setup before functions
    var typingTimer;                //timer identifier
    var doneTypingInterval = 1000;  //time in ms, 2 second


    // Name Override
    var $input = $('#nameOverride');
    var inputNameOverride = $('#nameOverride').val();

    //on keyup, start the countdown
    $input.on('keyup', function () {
        clearTimeout(typingTimer);
        inputNameOverride = $('#nameOverride').val();
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    //on keydown, clear the countdown 
    $input.on('keydown', function () {

        clearTimeout(typingTimer);
    });


    // Address Override
    var $input2 = $('#addressOverride');
    var inputAddressOverride = $('#addressOverride').val();

    //on keyup, start the countdown
    $input2.on('keyup', function () {
 
        clearTimeout(typingTimer);
        inputAddressOverride = $('#addressOverride').val();

        typingTimer = setTimeout(doneTyping, doneTypingInterval);
        
        
    });

    //on keydown, clear the countdown 
    $input2.on('keydown', function () {
     
        clearTimeout(typingTimer);
    });

    // hourly Rate
    var $input3 = $('#hourlyRate');
    var inputHourlyRate = $('#hourlyRate').val();
  
    //on keyup, start the countdown
    $input3.on('keyup', function () {
        clearTimeout(typingTimer);
        inputHourlyRate = $('#hourlyRate').val();
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    //on keydown, clear the countdown 
    $input3.on('keydown', function () {
  
        clearTimeout(typingTimer);
    });

    
    // Terms
    var $input4 = $('#termsId');
    var inputTermsId = $('#termsId').val();
    //on keyup, start the countdown
    $input4.on('change', function () {
        clearTimeout(typingTimer);
        inputTermsId = $('#termsId').val();
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    //on keydown, clear the countdown 
    $input4.on('keydown', function () {

        clearTimeout(typingTimer);
    });

    //user if "finished typing," save entry
    var doneTyping = function() { 
        if(inputAddressOverride.trim() == "") {
            alert("Adress can not be empty. Please fill the Address");
            return;
        } else {
            $.ajax({ 
                type:'post',  
                async:false,
                dataType: "json",         
                url: '/ajax/update_contract_autosave.php',
                data: {
                    nameOverride: inputNameOverride,
                    addressOverride : inputAddressOverride,
                    hourlyRate : Math.abs(inputHourlyRate),
                    termsId : inputTermsId,
                    contractId : <?php echo intval($contract->getContractId()); ?>
                },
                
                success: function(data, textStatus, jqXHR) {
                    // data saved each 2 seconds.     
                }
            })
        }
    }
    
    $("#updateContractLanguage").mousedown(function(){
        $.ajax({ 
            type:'post',  
            async:false,
            dataType: "json",         
            url: '/ajax/update_contract_language.php',
            data: {
                languageId: $("#contractLanguageId").val(),
                contractId : <?php echo intval($contract->getContractId()); ?>
            },
            
            success: function(data, textStatus, jqXHR) {
                $("#snackbar").text("Contract Language Successfully Saved!!");
                myFunction();    
            }
        })
    })
    
    function myFunction() {
      // Get the snackbar DIV
      var x = document.getElementById("snackbar");

      // Add the "show" class to DIV
      x.className = "show";

      // After 3 seconds, remove the show class from DIV
      setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
    }
});
</script>
     <div id="snackbar">Some text some message..</div>
<style>
/* The snackbar - position it at the bottom and in the middle of the screen */
#snackbar {
  visibility: hidden; /* Hidden by default. Visible on click */
  min-width: 250px; /* Set a default minimum width */
  margin-left: -125px; /* Divide value of min-width by 2 */
  background-color: #3f3; /* Black background color */
  color: #000; /* White text color */
  text-align: center; /* Centered text */
  border-radius: 5px; /* Rounded borders */
  padding: 16px; /* Padding */
  position: fixed; /* Sit on top of the screen */
  z-index: 1; /* Add a z-index if needed */
  left: 50%; /* Center the snackbar */
  bottom: 30px; /* 30px from the bottom */
}

/* Show the snackbar when clicking on a button (class added with JavaScript) */
#snackbar.show {
  visibility: visible; /* Show the snackbar */
  /* Add animation: Take 0.5 seconds to fade in and out the snackbar.
  However, delay the fade out process for 2.5 seconds */
  -webkit-animation: fadein 0.5s, fadeout 0.5s 2.5s;
  animation: fadein 0.5s, fadeout 0.5s 2.5s;
}

/* Animations to fade the snackbar in and out */
@-webkit-keyframes fadein {
  from {bottom: 0; opacity: 0;}
  to {bottom: 30px; opacity: 1;}
}

@keyframes fadein {
  from {bottom: 0; opacity: 0;}
  to {bottom: 30px; opacity: 1;}
}

@-webkit-keyframes fadeout {
  from {bottom: 30px; opacity: 1;}
  to {bottom: 0; opacity: 0;}
}

@keyframes fadeout {
  from {bottom: 30px; opacity: 1;}
  to {bottom: 0; opacity: 0;}
}
</style>

<script> 
//$(document).ready(function() {
var setTaskStatusContractIdNew = function(workOrderTaskId) {
       
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
            workOrderTaskId : workOrderTaskId
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
//});

var levelOne=<?php echo json_encode($levelOne); ?>; // Contains all id's of level one
var levelOneTasks=<?php echo json_encode($levelOneTasks); ?>;
var blockAdd = <?php echo json_encode($blockAdd);?>;
var userToReviewEdit=<?php echo json_encode($editable); ?>;


</script> 
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
                    #if(taskTypeId != key) {#
                        <option class='form-control form-control-sm formClass'  value="#= key #">#= allTaskTypes[key].typeName #</option>
                    #}#
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
<script id="column-template" type="text/x-kendo-template">
    # 
        // var host = window.location.host;  // sseng
        //var domain = window.location.origin; /// http://dev2.ssseng.com/
        //var urlImg = domain + '/cust/' + host + '/img/icons_task/';
        var urlImg = 'https://ssseng.com/cust/ssseng/img/icons_task/';
        
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


<script id="column-history" type="text/x-kendo-template">
    #var userToReview=<?php echo json_encode($editable); ?>; #
    # var display= false; #
    # if(levelOne.includes(workOrderTaskId)) {#
        #if(hasChildren == false) {#
            # display = true; #
        # } #
    #} #



    # if( display == true || !levelOne.includes(workOrderTaskId)) { #

        # if(userToReview == 1) { # 
            <span title="view data"><a id="historyCell_#=workOrderTaskId#" data-id='#=workOrderTaskId#' class="k-icon k-i-validation-data" style="font-size: 24px; cursor: pointer"></a></span>
        # } else { #
            <span title="view data"><a  data-id='#=workOrderTaskId#' class="k-icon k-i-validation-data" style="font-size: 24px; cursor: pointer"></a></span>'
        # } #
    # } else if(parentId == null) { #
        <span></span>

    # } else { #
        <span></span
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

<script id="column-totCost" type="text/x-kendo-template">

    # var display= false; #
        # if(levelOne.includes(workOrderTaskId)) {#
            #if(hasChildren == false) {#
                # display = true; #
        # } #
    #} #
    #var elementsCost=<?php echo json_encode($elementsCost); ?>; #


    #if(totCost) {#
        # if( display == true || !levelOne.includes(workOrderTaskId)) { #
            <span id="totCost_#=workOrderTaskId#">#=kendo.parseFloat(totCost)#</span>
        #} else { #
            <span style="font-weight: 600;" id="totCost_#=workOrderTaskId#">#=kendo.parseFloat(totCost)#</span>
        # } #
    #} else { #

        # if(parentId == null) { #
            #$.each(elementsCost, function(i,v) {#
                #if(elementId == i)   {#
                    <span class="font-weight-bold" id="elementCell_#=elementId#" >Tot : #=v.toLocaleString(undefined, {minimumFractionDigits: 2})#</span>
                #}#
            #});#

        # } else{  #
            <span id="totCost_#=workOrderTaskId#">0</span>
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



<script id="column-status" type="text/x-kendo-template">

    # if(taskStatusId == "1" || taskStatusId == "0") { #

        <a id="hide_#=workOrderTaskId#" class="  statusWoTaskColor rounded-circle" role="button" />
    # } else if(taskStatusId == "9") { #
    
        <a id="statuscell_#=workOrderTaskId#" class="  statusWoTaskColor2 rounded-circle" role="button" />
    
    # } else if (parentId == null ) { #
        <button style='' class='changeStatusWoTask  statusWoTaskColorNone rounded-circle'></button>
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
                    
                    <input id="hideCell_#=workOrderTaskId#" type="checkbox" checked  class="zoomCheck yyyy" onclick="javascript:setTaskStatusContractIdNew(#=workOrderTaskId#)">

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


<?php // Gantt ?>
<script> 
$(document).ready(function() {
    var userToReview=<?php echo json_encode($editable); ?>; 
    //console.log(userToReview);
    

    // Data from Main query
    var allTasksWoElements=<?php echo json_encode($out); ?>; 

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
                    cost: { from: "cost", type: "float" }, 
                    totCost: { from: "totCost", type: "float", defaultValue: 0  }, 
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
            
            
            
            { field: "billingDescription", title: "Bill Desc", headerAttributes: { style: "white-space: normal"}, template: $("#column-bill").html(), 
            attributes: {
                "class": "billingDescriptionUpdate"
            }, editable: true, width: 200 },

            


            { field: "", title: "Wot",  template: $("#column-history").html(),
            attributes: {
                "class": "table-cell k-text-center historyClass"
            },editable: false, width: 50 },


            
            { field: "hoursTime", title: "Hr", template: $("#column-hoursTime").html(), headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "table-cell k-text-center hoursClass"
            },editable: false, width: 40 },

            { field: "quantity", format: "{0:c}", title: "Qty", template: $("#column-quantity").html(), attributes: {
                "class": "table-cell k-text-center quantityCell"
            },editable: true, width: 60 },

        
            { field: "", title: "Types", template: $("#column-select").html(), headerAttributes: { style: "white-space: normal"},
            attributes: {
                "class": "k-text-center"
            }, editable: true, width: 90 },

            { field: "", title: "", template: $("#column-statusHide").html(),  attributes: {
                "class": "table-cell k-text-center statusHide k-text-center"
            }, 
            width: "35px" },


            { field: "cost", title: "Cost",  template: $("#column-cost").html(), headerAttributes: { style: "white-space: normal"}, attributes: {
                "class": "table-cell k-text-center costCell"
            },editable: true, width: 70 },

            { field: "totCost", title: "Total",  template: $("#column-totCost").html(), headerAttributes: { style: "white-space: normal"}, attributes: {
                "class": "table-cell k-text-center totCostCell"
            },editable: false, width: 90 },

            

            { field: "taskStatusId", title: "Status",  template: $("#column-status").html(),  headerAttributes: { style: "white-space: normal"}, 
            attributes: {
                "class": "table-cell k-text-center updateStatusToTask"
            }, editable: false, width: 60 },

            
        ],
        dataBound: function(e) {
        },
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
        drag: false,
        //dataBound: onDataBound,
        dataBound:function(e){
              this.list.bind('dragstart', function(e) {
                  return;
              })
            },

        dataBound:function(e) {
            this.list.bind('drop', function(e) {
                e.preventDefault();
                return;
        
            })
        }

    
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
            // block by status or user to review
            if(blockAdd == true || userToReviewEdit == false) {
                
                    $('#gantt table tr').each(function() {
                        $(this).find('td').each(function() {
                            $(this).find('span,select,input').attr("readonly", true);
                            $(this).find('span,select,input').attr("disabled", true);
                            $(this).css('pointer-events', 'none');
                        });
                    });
                } else {
                    
                    $('#gantt table tr').each(function() {
                        $(this).find('td').each(function() {
                            $(this).find('span,select,input').attr("readonly", false);
                            $(this).find('span,select,input').attr("disabled", false);
                            $(this).css('pointer-events', 'all');
                        });
                    });
     
                }
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
        var workOrderTaskId = item.workOrderTaskId;

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
        var workOrderTaskId = item.workOrderTaskId;

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
   
        $.ajax({
            url: '/ajax/add_extra_description_task.php', // file used for extra and billing Description.
            data: {
                nodeTaskId : nodeTaskId,
                billingDescription : billingDescription,
                billDesc : true,
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


<style>
    @media (min-width: 576px){
        #woModalHistoryDoc { 
           max-width: 1000px!important;
        }
    }

    @media (min-width: 576px){
        #statusModalDoc { 
           max-width: 600px!important;
        }
    }

    #woHistoryTable tbody td {
        font-weight:600;
        font-size: 16px;
    }
    
</style>
<?php

$employeesCurrent = $customer->getEmployees(EMPLOYEEFILTER_CURRENTLYEMPLOYED); 
$arrEmp = [];
$arrEmpIds = [];
foreach ($employeesCurrent as $employee) { 
    $pers = new Person($employee->getUserId());
    $permString = $pers->getPermissionString();
    
    $userPermissions = array();
    foreach ($definedPermissions as $dkey => $definedPermission) {
        $userPermissions[$definedPermission['permissionIdName']] = substr($permString, $definedPermission['permissionId'], 1);
    }


    $userPermsContract = ['1','2','3','5' ]; // valid PERM_CONTRACT

    if(in_array( $userPermissions['PERM_CONTRACT'], $userPermsContract)  ) {
        $arrEmp[] = $employee;
    }
}




?>
    <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" id="statusModalDoc" role="document">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="statusModalLabel">Assign/ add note</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>
                <div id="hiddenDiv" type="hidden">
                <input type="hidden" name="act" value="addPersonContract">
                </div>

                <div class="modal-body">
                    <div class="form-group d-flex justify-content-center" id="infoNoteDiv">
                    
                            <select class="form-control form-control-sm"  id="personIdCtr" name="personIdCtr">;
                                <option class="form-control form-control-sm"  value="">-- Assigned To --</option>
                                <?php foreach ($arrEmp as $employee) { 
                                
                                    
                                    ?>
                                <?php echo '<option class="form-control form-control-sm"  value="' . intval($employee->getUserId()) . '">[' . 
                                    $employee->legacyInitials . '] ' . $employee->getFirstName() . ' ' . 
                                    $employee->getLastName() . '</option>'; 
                                } ?>
                            </select>  
                    </div>
                    <div class="clearfix"></div>
                    <div class="form-group d-flex justify-content-center">
                                <textarea class="form-control" style="width:450px;height:147px;" placeholder="Enter note.." name="noteTextCtr" id="noteTextCtr" value=""></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    
                    <button type="submit" name="changeStatus" class="btn btn-secondary btn-sm" id="saveNoteInfo">Change Status</button>
                </div>
                </div>
    
    </div>
    </div>
    <div  class="clearfix" > </div>
    <div id="statusDiv" style="float:right">
        <?php
        
        // Buttons for each Status.
        $ctrStatus =  Contract::getContractStatusName($contract->getCommitted());
       
        $ctrStatusId = $contract->getCommitted();
        $buttonStatusText = "";
        $buttonStatusTextPrev = "";

        $ctrStatusIdPrev = 0;
        $ctrStatusIdNext = 0;

        if($ctrStatusId == 0) {  // Draft
            // Draft
            $buttonStatusText = "Send to Review";
            $ctrStatusIdNext = 1;

        } else if ($ctrStatusId == 1) { // Review
            // Review
            $ctrStatusIdPrev = 0;
            $buttonStatusTextPrev = "Send to Draft";

            $ctrStatusIdNext = 2;
            $buttonStatusText = "Send to Commit";

        } else if ($ctrStatusId == 2) { // Committed
            // commited
            // nota default + note 
            $ctrStatusIdPrev = 1;
            $buttonStatusTextPrev = "Send to Review";

            $ctrStatusIdNext = 3;
            $buttonStatusText = "Send to Delivered";
          
        } else if ($ctrStatusId == 3) { // Delivered
            $ctrStatusIdPrev = 5;
            $buttonStatusTextPrev = "Void";

            $ctrStatusIdNext = 4;
            $buttonStatusText = "Signed";
          
        } else if ($ctrStatusId ==  5) { // Void
            $ctrStatusIdPrev = 6;
            $buttonStatusTextPrev = "Voided";

            $ctrStatusIdNext = 2;
            $buttonStatusText = "Send to Commit";
          
        } 
      
        if ( $ctrStatusId != 4 || $ctrStatusId != 6 ) { // hide Button for statuses: Signed or Voided.
            if($enabledBtnStatus) {
                 $disabled = ""; // Delivered : enable buttons.
            }
            if($buttonStatusText) { 
            ?> 
           
                <button type="button" style="float:right" id="changeStatus_<?=$ctrStatusIdNext?>" <?php echo $disabled; ?> class="btn btn-secondary btn-sm mb-2 mt-5" ><?php echo $buttonStatusText?></button> </td>
           
            <?php
            }
            // except draft first status.
            if($buttonStatusTextPrev) { ?>
                <button type="button" style="float:right" id="changeStatus_<?=$ctrStatusIdPrev?>"  <?php echo $disabled; ?> class="btn btn-secondary btn-sm mr-2 mt-5" ><?php echo $buttonStatusTextPrev?></button> </td>
            <?php 
            }
        } ?>


    </div>
    </form>

    <!-- Modal For WO History and Multiplier-->
    <div class="modal fade" id="woModalHistory" tabindex="-1" role="dialog" aria-labelledby="woModalHistoryLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" id="woModalHistoryDoc" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="woModalHistoryLabel">WorkOrder Task</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group" id="infoWoDiv">
                    <span  id="infoNote" style="float:left;font-style: italic; font-size: 15px;" value=""></span>
                </div>
                <div class="form-group" id="infoWoDiv">
                <table id="woHistoryTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Job Number</th>
                            <th>Date</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Quantity Type</th>
                            <th>WO Multiplier</th>
                            <th></th>
                        </tr>
                    </thead>
    
                    <tbody>
                        <tr>
                            <td id="jobNumberId"></td>
                            <td id="dateId"></td>
                            <td id="priceId"></td>
                            <td id="quantityId"></td>
                            <td id="quanityTypeId"></td>
                            <td id="woMultiId"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>  
                </div>
                <div class="clearfix"></div>
    
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <!--<button type="button" class="btn btn-secondary btn-sm" id="saveNoteInfo">Save changes</button> -->
            </div>
            </div>
        </div>
    </div>
    <div  class="clearfix" > </div>



    <div class="float-child">
    <?php 
    $noteArr = [];
    $query  = " SELECT cn.*, cs.statusName ";
    $query .= " FROM " . DB__NEW_DATABASE . ".contractNote cn ";
    $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".contractStatus cs ON cn.contractStatus = cs.contractStatusId ";
    $query .= " WHERE cn.contractId = " . intval($contract->getContractId()) . " ORDER BY cn.noteId DESC";


    $result = $db->query($query);
    if ($result) {
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $noteArr[] = $row;
            }
        }
        
    } 
    $aNote = array();
    foreach( $noteArr  as $note ) {
        $aNote[$note['statusName']][] = $note;
    }
    //var_dump($noteArr);
    ?>
        <h2 class="heading">Contract Notes</h2>
        <table>
            <tbody>
                    <tr>
                        <td>
                            <div class="textwrapperNotes">
                                
                            <?php
                               
                               foreach( $aNote  as $key => $note ) {
                                $note = array_reverse($note);
                                array_multisort(array_column($note, 'date'), SORT_DESC, $note);
                                echo '<p class="font-weight-bold mb-2 mt-2" >Status ' . $key . '</p>';
                            

                                foreach($note  as $n ) {
                                   
                                    $user = new User($n['personId'], $customer);
                                
                                    if($n['noteType'] == 2) {
                                        echo '<p class="mb-1 mt-1 font-weight-bold" >Date: ' . $n['date'] . '</p>';
                                        echo '<p style="font-style: italic;" class="mb-1 mt-1" ><span class="font-weight-bold" >#</span> ' . $user->getFirstName() . " " .  $user->getLastName() . " " . $n['note'] . '</p>';
                                    } else {
                                        echo '<p class="mb-1 mt-1" >Note: ' . $n['note'] . '</p>';
                                    }
                                }
                            }
                                ?>
                            </div>
                        </td>
                    </tr>
            </tbody>
        </table>
    </div>






        <table style="display:none" border="0">
            <tr>
                <td colspan="2">
                    <?php /* grouped list of elements and (subordinate to elements) tasks,
                                or more precisely workOrderTasks */ ?>
                    <table border="0">
                    <?php
                        /* We get the content by calling $elementGroups = overlay($workOrder, $contract).
                            >>>00001 As remarked several times, overlay is one of the least understood parts
                            of this code.
                            The $elementGroups structure that is initially a return from function overlay,
                            gets some additional ad hoc content here. If I (JM) understand correctly, the
                            only one here is:

                            * $elementgroup['tasks'][$taskkey]['grp']:
                                * U.S. currency total appended to each task, taking into account all of its subtasks.
                        */
                        $elementgroups = overlay($workOrder, $contract);
                        $taskTypes = getTaskTypes();
                        $clientMultiplier = $formClientMultiplier; // [Martin comment] $contract->getClientMultiplier();
                        if (filter_var($clientMultiplier, FILTER_VALIDATE_FLOAT) === false) {
                            $clientMultiplier = 1;
                        }

            /*//////////////////////////////////////
            BEGIN Loop over $elementgroups outdented
            //////////////////////////////////////*/
                $grandtotal = 0; // Grand total for elementgroup across all tasks

                foreach ($elementgroups as $elementgroup) {
                    echo "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
                    /* For this elementGroup, effectively a title spanning the width of the table:
                        * If $elementgroup['element'] === false: 'Other Tasks (Multiple Elements Attached)'
                        * If there is a non-zero elementId, the name of that element
                        * If it is multiple elements, 'General'
                    */
                    // renamed $en as $elementOrGroupName JM 2020-09-02
                    $elementOrGroupName = '';
                    if ($elementgroup['element'] === false){
                        $elementOrGroupName = 'Other Tasks (Multiple Elements Attached)';
                    } else {
                        /* BEGIN REPLACED 2020-09-08 JM
                        $elementOrGroupName = (intval($elementgroup['element']['elementId'])) ? $elementgroup['element']['elementName'] : 'General';
                        // END REPLACED 2020-09-08 JM
                        */
                        // BEGIN REPLACEMENT 2020-09-08 JM
                        $elementOrGroupName = $elementgroup['element']['elementName'] ? $elementgroup['element']['elementName'] : 'General';
                        // END REPLACEMENT 2020-09-08 JM
                    }

                    echo '<tr>';
                        echo '<td colspan="10" bgcolor="#dddddd" style="font-size:125%;font-weight:bold;">' . $elementOrGroupName . '</td>';
                    echo '</tr>';
                    ?>
                    <tr>
                        <th>&nbsp;</th>
                        <th>&nbsp;</th>
                        <th width="50%">Desc</th>
                        <th width="35%">Bill Desc</th>
                        <th>&nbsp;</th>
                        <th>Qty</th>
                        <?php /* ?> <th>NTE</th> // commented out by Martin before 2019. JM:
                                    >>>00006 Unlike the other commenting out I (JM 2019-03-15) suspect NTE might come back to life, and
                                    instead of killing that code outright, we might want a Boolean to turn it on & off, here & below.
                                    2019-12-11 JM: I thought about this further. Killing it for now. If we need and NTE ("not to exceed") we
                                    can add it back in easily enough.
                                    <?php */ ?>
                        <th>Cost</th>
                        <th>Tot</th>
                        <th>Grp</th>
                    </tr>
                <?php
                    /* Having written the headers, code makes two passes through the tasks,
                    one to calculate and one to display.
                    */
                    if (isset($elementgroup['tasks'])) {
                        if (is_array($elementgroup['tasks'])) {
                            // BEGIN REPLACED 2020-09-02 JM
                            // $tasks = $elementgroup['tasks'];
                            // END REPLACED 2020-09-02 JM
                            // BEGIN REPLACEMENT 2020-09-02 JM
                            $tasks = &$elementgroup['tasks']; // NOTE the ampersand: $tasks here is just an alias/reference
                            // END REPLACEMENT 2020-09-02 JM
                            $tot = 0; // total across tasks in the elementgroup

                            // This pass through the tasks is to calculate rather than to display.
                            foreach ($tasks as $taskkey => $task) {
                                $sliced = array_slice($tasks, $taskkey + 1);  // The rest of array $tasks
                                $startLevel = intval($task['level']);
                                $total = 0; // total for task, including subtasks
                                $sum = 0;   // total for task, excluding subtasks
                                $estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;
                                $estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;

                                $sum = ($estQuantity * $estCost * $clientMultiplier);
                                
                                $total += $sum;
                                $tot += $sum;

                                // Now we want to look at any subtasks.
                                // NOTE that each of these subtasks will come up again in the outer loop as a task in its own right.
                                foreach ($sliced as $skey => $slice) {
                                    if ($slice['level'] > $startLevel) {
                                        $total_at_this_subtask_level = 0; // total for this subtask, excluding its own subtasks
                                        $estQuantity = isset($slice['task']['estQuantity']) ? $slice['task']['estQuantity'] : 0;
                                        $estCost = isset($slice['task']['estCost']) ? $slice['task']['estCost'] : 0;

                                        $total_at_this_subtask_level = ($estQuantity * $estCost * $clientMultiplier);

                                        $total += $total_at_this_subtask_level;
                                    } else {
                                        break;
                                    }
                                } // END foreach ($sliced as $skey => $slice) {
                                // A total U.S. currency amount appended to each task
                                //  of the elementgroup, taking into account all of its subtasks.
                                $elementgroup['tasks'][$taskkey]['grp'] = $total;
                            } // END foreach ($tasks as $taskkey => $task)

                            $grandtotal += $tot; // Grand total for elementgroup across all tasks

                            /* No need to do $tasks = $elementgroup['tasks'] now that we made $tasks a reference rather than a copy. */

                            // putTaskArrayInCanonicalOrder() introduced in the following line 2020-11-12 JM to address
                            //  http://bt.dev2.ssseng.com/view.php?id=256 (Order of tasks within an element of a WorkOrder should be consistent).
                            //  We force the display to correspond to the current task hierarchy, independent of what the task hierarchy may have
                            //  been when this invoice was created.
                            foreach (putTaskArrayInCanonicalOrder($tasks) as $task) {
                            
                                if (isset($task['type']) && $task['type'] == 'fake') {
                                    /* $task['type'] == 'fake' is a temporary expedient for a missing parent task.
                                    Theory is that this should go away, but I (JM) doubt it, as long as we still want to
                                    be able to look at old contracts.

                                    If the task is "fake", we just span all the columns with a task description.
                                    */
                                    echo '<tr >';
                                        echo '<td colspan="10">';
                                            echo $task['task']['description'];
                                        echo '</td>';
                                    echo '</tr>';
                                }

                                if (isset($task['type'])  && $task['type'] == 'real') {
                                    $wot = new WorkOrderTask($task['workOrderTaskId']);
                                    $bg = '';

                                    echo '<tr ' . $bg . ' id="row_' . intval($wot->getWorkOrderTaskId()) . '">';
                                        /* (no header). Note constructed class value.
                                            An icon for whether the WorkOrderTask is "active",
                                            which is to say whether it has any statusId other than 9.
                                        */
                                        // BEFORE v2020-4, class prefix here was inappropriately workOrderTaskCategoryTaskId rather than workOrderTaskId
                                        echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '" style="text-align:center;" id="statuscell_' . $wot->getWorkOrderTaskId() . '">';
                                            $active = ($wot->getTaskStatusId() == 9) ? 0 : 1;
                                            echo '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_active_' . intval($active) . '_24x24.png" width="16" height="16" border="0">';
                                        echo '</td>';

                                        /* (no header). Task icon, if any. */
                                        echo '<td>';
                                            if (isset($task['task'])) {
                                                $ttt = $task['task'];
                                                if (isset($ttt['icon'])) {
                                                    $icon = $ttt['icon'];
                                                    if (strlen($icon)) {
                                                        echo '<img src="' . getFullPathnameOfTaskIcon($icon, '1595357890') . '" width="24" height="24" border="0">';
                                                    }
                                                }
                                            }
                                        echo '</td>';

                                        /* Desc: Note constructed class value.
                                        We use multiple non-breaking spaces to indent this appropriately to show levels in the task hierarchy.
                                        That's done via a 2-column subtable with the non-breaking spaces in the left column and with the right
                                        column containing the task description followed by parenthesized level; levels are zero-based.
                                        >>>00001 JM: probably no need for a subtable, but it seems to work. */
                                        // BEFORE v2020-4, class prefix here was inappropriately workOrderTaskCategoryTaskId rather than workOrderTaskId
                                        echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '" align="left">';
                                            $spaces = '';
                                            if (intval($task['level'])) {
                                                for ($i = 0; $i < $task['level']; $i++) {
                                                    $spaces .= "&nbsp;&nbsp;&nbsp;";
                                                }
                                            }
                                            echo '<table  border="0" cellpadding="0" cellspacing="0">';
                                                echo '<tr>';
                                                    echo '<td>' . $spaces . '</td>';
                                                    echo '<td width="100%">' . $task['task']['description'] . '(' . $task['level'] . ')</td>';
                                                echo '</tr>';
                                                echo "\n";
                                            echo '</table>';
                                        echo '</td>';

                                        /* Bill Desc: Note constructed class value.
                                        Billing description.

                                        If editable, then unlike the prior columns (but like most that follow)
                                        this is part of the form: a text input with name="billingDescription::workOrderTaskId[]''.
                                        Here and below, the name is an array but, according to Martin 2018-11-05, that is just an
                                        artifact of earlier code, there will never be more than one of these for the same-named element.
                                        He adds, "leaving it like this disrupted less logic, hence it's apparent funkiness."
                                        The initial value is the current billingDescription for the task.

                                        If not editable, just the text of the billingDescription. (>>>00001 JM: I notice that this contrasts
                                        to some other cases, e.g. arrow direction, we still have form input set up even if not editable.
                                        I'm guessing that either way is OK, since there is no way to submit a form if not editable.)
                                        */
                                        // BEFORE v2020-4, class prefix here was inappropriately workOrderTaskCategoryTaskId rather than workOrderTaskId
                                        echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '"><span class="formspan">';
                                            if ($editable) {
                                                echo '<input class="forminput" id="billingDescription' . $wot->getWorkOrderTaskId() . '" type="text" name="billingDescription::' . $wot->getWorkOrderTaskId() . '[]" value="' . htmlspecialchars($task['task']['billingDescription']) . '" />';
                                            } else {
                                                echo htmlspecialchars($task['task']['billingDescription']);
                                            }
                                        echo '</span></td>';

                                        /* (no header) Arrow direction; normally drawn from contract, but defaults to ARROWDIRECTION_RIGHT
                                        if nothing there. The other possibility is ARROWDIRECTION_DOWN. ( RIGHT is default,
                                        DOWN means sum everything below it in the hierarchy into this.) This always has a hidden
                                        input with name="arrowDirection::WorkOrderTaskId", id same as name, and
                                        a value equal to the numeric value of relevant ARROWDIRECTION constant,
                                        and it always displays the relevant icon.
                                        If editable, it also is a link to call local function arrowDirection(workOrderTaskId, direction)
                                        to switch to the other direction.
                                        function arrowDirection amends HTML FORM content accordingly and immediately self-submits the form,
                                        causing an update. */
                                        if (!isset($task['task']['arrowDirection'])) {
                                            $task['task']['arrowDirection'] = ARROWDIRECTION_RIGHT;
                                        }
                                        if ($task['task']['arrowDirection'] == ARROWDIRECTION_RIGHT){
                                            echo '<input type="hidden" id="arrowDirection::' . intval($wot->getWorkOrderTaskId()) . '" name="arrowDirection::' . intval($wot->getWorkOrderTaskId()) . '" value="' . ARROWDIRECTION_RIGHT . '" />';
                                            if ($editable){
                                                echo '<td><a id="arrowDirectionDown' . intval($wot->getWorkOrderTaskId()) . '" href="javascript:arrowDirection(' . intval($wot->getWorkOrderTaskId()) . ',' . ARROWDIRECTION_DOWN . ')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_arrow_right_48x48.png" border="0" width="22" height="22"></td>';
                                            } else {
                                                echo '<td><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_arrow_right_48x48.png" border="0" width="22" height="22"></td>';
                                            }
                                        } else if ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN) {
                                            echo '<input type="hidden" id="arrowDirection::' . intval($wot->getWorkOrderTaskId()) . '" name="arrowDirection::' . intval($wot->getWorkOrderTaskId()) . '" value="' . ARROWDIRECTION_DOWN . '" />';
                                            if ($editable){
                                                echo '<td><a id="arrowDirectionRight' . intval($wot->getWorkOrderTaskId()) . '" href="javascript:arrowDirection(' . intval($wot->getWorkOrderTaskId()) . ',' . ARROWDIRECTION_RIGHT . ')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_arrow_down_48x48.png" border="0" width="22" height="22"></td>';
                                            } else {
                                                echo '<td><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_arrow_down_48x48.png" border="0" width="22" height="22"></td>';
                                            }
                                        } else {
                                            echo '<td>&nbsp;</td>';
                                        }

                                        /* Qty: Note constructed class value.
                                        Estimated quantity.

                                        If editable, then this is a text input, name="estQuantity::workOrderTaskId[]",
                                        initial value drawn from the task.

                                        If not editable, then displays the estimated quantity as text.

                                        Regardless of whether editable, and outside the editable portion if editable,
                                        followed by the task type name in square brackets. */
                                        // >>>00006 According to Martin 2018-11-05,
                                        // there is no particular reason we get the taskType name via $wot->getTask()
                                        // instead of via $task['task'], which we are otherwise using. This was arbitrary.
                                        // The other would probably be simpler & clearer.
                                        $t = $wot->getTask();
                                        $estQuantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;

                                        $tt = '';
                                        if (isset($taskTypes[$t->getTaskTypeId()]['typeName'])) {
                                            $tt = $taskTypes[$t->getTaskTypeId()]['typeName'];
                                        }
                                        if(strlen($tt)) {
                                            $tt = '[' . $tt . ']';
                                        }
                                        // BEFORE v2020-4, class prefix here was inappropriately workOrderTaskCategoryTaskId rather than workOrderTaskId
                                        echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '" nowrap>';
                                            if ($editable){
                                                echo '<input type="text" id="estQuantity' . $wot->getWorkOrderTaskId() . '" name="estQuantity::' . $wot->getWorkOrderTaskId() . '[]" value="' . htmlspecialchars($estQuantity) . '" size="3" />' . $tt;
                                            } else {
                                                echo htmlspecialchars($estQuantity) . '&nbsp;' . $tt;
                                            }
                                        echo '</td>';

                                        /* Cost: Note constructed class value.
                                        Estimated cost.

                                        If editable, then a text input with name="estCost::workOrderTaskId[]''
                                        (array isn't really needed in name, see remark above about "Bill Desc").
                                        The initial value is the product of the estCost for the task and the clientMultiplier.
                                        This isn't really "estimated": that is an artifact of old Martin stuff.

                                        If not editable, just the text for that same value.
                                        */
                                        $estCost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;
                                        // BEFORE v2020-4, class prefix here was inappropriately workOrderTaskCategoryTaskId rather than workOrderTaskId
                                        echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '" nowrap>';
                                            if ($editable){
                                                echo '<input type="text" id="estCost' . $wot->getWorkOrderTaskId() . '" name="estCost::' . $wot->getWorkOrderTaskId() . '[]" value="' . htmlspecialchars($estCost) . '" size="7" /><span style="font-size:80%">(' . ($estCost * $clientMultiplier) . ')</span>';
                                            } else {
                                                echo htmlspecialchars($estCost) . '&nbsp;<span style="font-size:80%">(' . ($estCost * $clientMultiplier) . ')</span>';
                                            }
                                        echo '</td>';

                                        /* Tot: Note constructed class value.
                                        Estimated total.

                                        If editable, then this is a text input with no name (Joe asked Martin
                                        "What's the point of that? Doesn't it mean the value will be ignored
                                        on form submission? And why allow editing a calculated value? Martin replied
                                        2018-11-05, "This field isn't actually editable (in the sense of any edit
                                        sticking) and is just derived. Probably just left over from some old methodology.").
                                        >>>00006 SO LET'S IGNORE $editable HERE AND MAKE IT LIKE THE "NOT EDITABLE" CASE. - JM
                                        THIS HAS NOT YET BEEN DEALT WITH 2019-12-11

                                        Initial value is the product of the estimated quantity, estimated cost, and client multiplier.

                                        If not editable, then the same value is used, but as simple text. */
                                        // BEFORE v2020-4, class prefix here was inappropriately workOrderTaskCategoryTaskId rather than workOrderTaskId
                                        echo '<td class="workOrderTaskId_' . $wot->getWorkOrderTaskId() . '">';
                                        if ($editable){
                                            echo '<input type="text" name="" value="' . htmlspecialchars($estQuantity * $estCost * $clientMultiplier) . '" size="5" />';
                                        } else {
                                            echo htmlspecialchars($estQuantity * $estCost * $clientMultiplier);
                                        }
                                        echo '</td>';

                                                /* Grp: this has content only if the task arrowdirection is ARROWDIRECTION_DOWN,
                                                in which case it contains a group total we calculated above for the present task,
                                                in the first loop over tasks before the one that is writing to this table. */
                                                echo '<td>';
                                                    if ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN) {
                                                        echo $task['grp'];
                                                    }
                                                echo '</td>';
                                            echo '</tr>';
                                            $classes[$wot->getWorkOrderTaskId()][] = '';
                                        } // END if ($task['type'] == 'real')
                                    }// END foreach (putTaskArrayInCanonicalOrder($tasks) as $task)

                                    // Colspan & leaving one column blank here at right is ad hoc.
                                    echo '<tr>';
                                        echo '<td colspan="9" style="font-weight:bold;text-align:right">Section subtotal: ' . $tot . '</td>';
                                        echo '<td colspan="1"></td>';
                                    echo '</tr>';
                                } // END if (is_array($elementgroup['tasks']))
                            } // END if (isset($elementgroup['tasks']))
                        } // END foreach ($elementgroups ...
                    /*////////////////////////////////////
                    END Loop over $elementgroups outdented
                    ////////////////////////////////////*/
                    ?>

                                </table>
                            </td>
                        </tr>
                        <?php /* Finally for the contract, a row spanning all columns, bolded, giving a grand total and,
                                if editable, another row offering two buttons:
                                    * a "submit" button labeled "update"
                                    * a "Commit" button, which triggers a dialog to add a commit note;
                                      any existing commit note on a committed contract is carried in a hidden element of the form.
                                      The mechanism here is a bit obscure, but it uses a jQuery function to open DIV id="commitContent" in a fancybox.
                                      That prompts "Please add a comment as to why this commit of the contract was made," and provides a
                                      textarea and "Cancel" and "Commit" buttons.
                        */ ?>
                        <tr>
                            <td colspan="2"  style="font-weight:bold;font-size:130%;text-align:right">Total : <?php echo $grandtotal; ?></td>
                        </tr>
                        <?php
                            if ($editable) {
                        ?>
                                <tr>
                                    <td style="text-align:center;"><input type="submit" id="updateCtr" disabled class="btn btn-secondary btn-sm mb-2 mt-2"  value="Update" /></td>
                                    <td style="text-align:center;"><button type="button" id="begin-commit" disabled class="btn btn-secondary btn-sm mb-2 mt-2" >Send to reviewer</button> </td>
                                    <script>
                                    $('#begin-commit').click(attemptCommit);
                                    </script>
                                </tr>
                        <?php
                            }
                        ?>
                    </tbody>
                </table>
          
            </div>
    <?php } /* END the else case: there *is* a billing profile associated with this contract */ ?>
        </div>
</div>

<script>
// Save vertical scrolling so we can restore it after page reload
function saveState() {
    let scrollTopMain = $(document).scrollTop();
    let submitOnChange = $("#submit-on-change").prop('checked');

    sessionStorage.setItem('contract_scrollTopMain', scrollTopMain);
    sessionStorage.setItem('contract_submitOnChange', submitOnChange);
}

// Restores state saved with saveState(), then deletes it from sessionStorage
function restoreState() {
    let scrollTopMain = sessionStorage.getItem('contract_scrollTopMain');
    let submitOnChange = sessionStorage.getItem('contract_submitOnChange');

    if (scrollTopMain) {
        $(document).scrollTop(scrollTopMain);
    }
    if (submitOnChange !== null) {
        $("#submit-on-change").prop('checked', submitOnChange);
        applySubmitOnChange();
    }

    sessionStorage.removeItem('contract_scrollTopMain');
    sessionStorage.removeItem('contract_submitOnChange');
}

$(function() {
    // Restore state on "document ready"
    restoreState();
});

$("#contractform").submit(saveState); // attach saveState as a handler for submitting contractform. NOTE that this will be
                                      // followed by the default action (the normal submission).
</script>
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

<script>

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#contractform2').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        personIdCtr:{
            required: true
        }
    }
});
if(validator) {
    validator.showErrors(jsonErrors);
}


// The moment they start typing (or pasting) in a field, remove the validator warning
$('input').on('keyup change', function() {
    $('#validator-warning').hide();
    if($('#personIdCtr').hasClass('text-danger')){
        $('#personIdCtr').removeClass('text-danger');
    }
});
</script>
<?php
include_once BASEDIR . '/includes/footer.php';
?>
