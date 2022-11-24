<?php 
/*  fb/jobworkorders.php

    EXECUTIVE SUMMARY: display all workOrders for job and be able to add a new one.

    PRIMARY INPUT: $_REQUEST['jobId'].

    OPTIONAL INPUT $_REQUEST['act']. Only meaningful value: 'addworkorder'
      * 'addworkorder' uses values $_REQUEST['description'], $_REQUEST['workOrderDescriptionTypeId'].
*/
include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-07-23, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$description="";
$workOrderId = 0;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'jobId');
$v->rule('integer', 'jobId');
$v->rule('min', 'jobId', 1);

if( !$v->validate() ) {
    $errorId = '637311889483903230';
    $logger->error2($errorId, "jobId : " . $_REQUEST['jobId'] . " not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid jobId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$jobId = intval($_REQUEST['jobId']); // The jobId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'job'.
if (!Job::validate($jobId)) {
    $errorId = '637311889551358446';
    $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing DB row in job table");
    $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$job = new Job($jobId);

// WorkOrderDescriptionTypes
$types = getWorkOrderDescriptionTypes(1, $error_is_db);  // argument 1 here is 'active only'.
if ($error_is_db) { //true on query failed
    $errorId = '637376710759294793';
    $error = "We could not display the Types for this Work Order. Database Error. </br>"; // message for User
    $logger->errorDB($errorId, "getWorkOrderDescriptionTypes() method failed => Hard DB error ", $db);
}

$workOrderDescriptionTypeIdDB = array(); // Declare an array for workOrderDescriptionTypeIds (just IDs vs. $types which has more data).

foreach ($types as $valueId) {
    $workOrderDescriptionTypeIdDB[] = $valueId["workOrderDescriptionTypeId"]; //Build an array with valid workOrderDescriptionTypeIds from DB, table workOrderDescriptionType.
}
// End WorkOrderDescriptionTypes

$workorders = $job->getWorkOrders($error_is_db); // an array of WorkOrder objects
if ($error_is_db) { //true on query failed
    $errorId = '637377478050596379';
    $error .= "We could not display Work Orders. Database Error."; // message for User
    $logger->errorDB($errorId, "getWorkOrders() method failed => Hard DB error ", $db);
}

if (!$error && $act == 'addworkorder') {
    $v->rule('required', ['workOrderDescriptionTypeId', 'description']);
    $v->rule('in', 'workOrderDescriptionTypeId', $workOrderDescriptionTypeIdDB); //workOrderDescriptionTypeId value must be in array.

    if (!$v->validate()) {
        $errorId = '637176444893270593';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";        
    } else {
        $workOrderDescriptionTypeId = $_REQUEST['workOrderDescriptionTypeId']; //already required

        $description = $_REQUEST['description']; //already required
        $description = truncate_for_db ($description, 'description WorkOrder', 128, '637311895539643403'); //  handle truncation when an input is too long for the database.

        // Use $job->addWorkOrder() to get an Id for a new workorder, then update 
        // that workorder with description & workOrderDescriptionTypeId. 
        $workOrderId = $job->addWorkOrder();

        if (!intval($workOrderId)) {
            $errorId = '637196072621723739';
            $error = 'We could not add a new Work Order. Database Error.'; //message for User
            $logger->errorDb($errorId, '$job->addWorkOrder() method failed.', $db);
        } else if ($workOrderId <= 0) {
            // $workOrderId will be an error code set by WorkOrder object, so have that object interpret it.
            list($error, $errorId) = WorkOrder::errorToText($errCode);
            $logger->error2($errorId, "$error invalid workOrderId: $workOrderId ");
        } else {
            $workOrder = new WorkOrder($workOrderId);
            $success = $workOrder->update(array('description' => $description,'workOrderDescriptionTypeId' => $workOrderDescriptionTypeId));  
                
            if (!$success) {
                $errorId = '637376758323866146';
                $error = 'Add a New Work Order failed.'; // message for User
                $logger->errorDB($errorId, "update workOrder method failed => Hard DB error ", $db);
            } else { 
                // Success: reload the parent page (which will implicitly close the fancybox), and die.
                ?>
                <script type="text/javascript">
                    parent.location.reload(true);
                </script>
                <?php
                die(); // Reloading the parent should have killed this fancybox, so no point to continuing. 
            }
            unset($workOrder);
        }
        unset($description, $workOrderDescriptionTypeId, $workOrderId);
    }
    unset($workOrderDescriptionTypeIdDB);
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";    
}
?>
<style>
body {
    background: white !important;
}
</style>
<!--  Get array of workOrders for job, write job name as a header, then generate a self-submitting form "topForm", visually structured by a table.
    Form has: 
        * hidden: jobId
        * hidden: act='addworkorder'
        * pseudo-heading "Current Work Orders" (spanning the table)
        * nested table, with the following columns; one row per workOrder. See description below.
        * pseudo-heading "New Work Order" (spanning the table)
        * Two columns under this:
            * "Description Type": HTML SELECT, see description below.
            * "Description": Text input, name="description" 
        * Submit button, labeled "add work order". 
-->  
<?php 

echo '<h1>' . $job->getName() . '</h1>';
echo '<form id="topForm" name="addWorkOrder" action="" method="POST">';
    echo '<input type="hidden" name="jobId" value="' . intval($job->getJobId()) . '">';
    echo '<input type="hidden" name="act" value="addworkorder">';    
    echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" class="table-sm">';
        echo '<tr>';
            echo '<td colspan="2"><h2>Current Work Orders</h2></td>';			
        echo '</tr>';
        
        echo '<tr>';
        echo '<td colspan="2">';
            /* Nested table, with the following columns; one row per workOrder. Although physically inside the form, this is strictly display.
                * Description: workOrder description. Passive display.
                * Genesis: exactly as in various workOrder-related pages in the domain root. Passive display.
                * Delivery: exactly as in various workOrder-related pages in the domain root. Passive display.
                * Age: exactly as in various workOrder-related pages in the domain root. Passive display.
                * Status: exactly as in various workOrder-related pages in the domain root. Passive display.
            */        
            echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" class="table-sm table-striped">';
                echo '<tr>';
                    echo '<th width="40%">Description</th>';
                    echo '<th width="15%">Genesis</th>';
                    echo '<th width="15%">Delivery</th>';
                    echo '<th width="15%">Age</th>';
                    echo '<th width="15%">Status</th>';               
                echo '</tr>';
                foreach ($workorders as $wo) {
                    /* BEGIN REPLACED 2020-06-12 JM
                    $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(),$wo->getWorkOrderStatusId());
                    // END REPLACED 2020-06-12 JM
                    */
                    // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                    $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(), $wo->isDone());
                    // END REPLACEMENT 2020-06-12 JM
                    $genesisDT = $ret['genesisDT'];
                    $deliveryDT = $ret['deliveryDT'];
                    $ageDT = $ret['ageDT'];                    
                    echo '<tr>';
                        echo '<td width="40%">' . $wo->getDescription() . '</td>';
                        echo '<td width="15%">' . $genesisDT . '</td>';
                        echo '<td width="15%">' . $deliveryDT . '</td>';
                        echo '<td width="15%">' . $ageDT . '</td>';
                        echo '<td width="15%">' . $wo->getStatusName() . '</td>';                    
                    echo '</tr>';
                }
            echo '</table>';
        
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
            echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="2"><h2>New Work Order</h2></td>';
        echo '</tr>';
        // George 2020-07-24. Moved to the top of the page.
        // $types = getWorkOrderDescriptionTypes(1);  // argument 1 here is 'active only'

        echo '<tr>';
            echo '<td>Work Order Type</td>';
            echo '<td>Description</td>';
        echo '</tr>';
        echo '<tr>';
            // "Work Order Type" (formerly "Description Type"): HTML SELECT (workOrderDescriptionTypeId),  
            //   default is "-- Description Type --" with an empty string as value. 
            //   All workOrder description types are offered, displaying a name, with ID as value.
            echo '<td><select class="form-control col-md-12" required id="workOrderDescriptionTypeId" name="workOrderDescriptionTypeId" style="width:100%"><option value="">-- Description Type --</option>';
            foreach ($types as $type) {
                echo '<option value="' . $type['workOrderDescriptionTypeId'] . '" '.(isset($_REQUEST['workOrderDescriptionTypeId']) 
                && ($type['workOrderDescriptionTypeId'] == $_REQUEST['workOrderDescriptionTypeId']) ? 'selected':'').'>' . htmlspecialchars($type['typeName']) . '</option>';
            }        
            echo '</select></td>';
            // "Description": Text input, name="description"            
            echo '<td><input class="form-control col-md-8" type="text" id="descriptionId" name="description" value="" size="50" maxLength="128"></td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="2" style="text-align:center;"><input type="submit" id="addWorkOrder" class="btn btn-secondary mr-3" value="Add work order" border="0"></td>';
        echo '</tr>';
    echo '</table>';
echo '</form>';

// client-side form input validation
?>
<script>
var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#topForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        'workOrderDescriptionTypeId':{
            required: true
        },
        'description':{
            required: true
        }
    }
});

validator.showErrors(jsonErrors);
// The moment they start typing(or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#descriptionId-error').hide();
    if ($('#descriptionId').hasClass('text-danger')){
        $("#descriptionId").removeClass("text-danger");
    } 
});

$('select').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#workOrderDescriptionTypeId-error').hide();
    if ($('#workOrderDescriptionTypeId').hasClass('text-danger')){
        $("#workOrderDescriptionTypeId").removeClass("text-danger");
    } 
});
</script>

<?php

include '../includes/footer_fb.php';

?>