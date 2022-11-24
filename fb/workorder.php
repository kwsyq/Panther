<?php
/*  fb/workorder.php

    EXECUTIVE SUMMARY: View and update a workorder, especially status
    
    PRIMARY INPUT: $_REQUEST['workorderId']
    
    Optional $_REQUEST['act'] can have values:    
        * 'updatestatus', which uses: 
            * $_REQUEST['workOrderStatusId']
            * $_REQUEST['customerPersonIds'] - should be an array, if present.
            * $_REQUEST['note']
        * 'updateworkorder', which uses $_REQUEST values drawn from the "workOrder" form
            * $_REQUEST['workOrderId']
            * $_REQUEST['workOrderDescriptionTypeId']
            * $_REQUEST['description']
            * $_REQUEST['genesisDate']
            * $_REQUEST['deliveryDate']
            * $_REQUEST['intakeDate']
*/
                                                                  
include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-08-10, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'workOrderId');
$v->rule('integer', 'workOrderId');
$v->rule('min', 'workOrderId', 1);

if( !$v->validate() ) {
    $errorId = '637326546923422801';
    $logger->error2($errorId, "workOrderId : " . $_REQUEST['workOrderId'] . " not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid workOrderId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$workOrderId = intval($_REQUEST['workOrderId']);

if (!WorkOrder::validate($workOrderId)) {
    $errorId = '637311889551358446';
    $logger->error2($errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB row in workorder table");
    $_SESSION["error_message"] = "WorkOrderId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$workOrder = new WorkOrder($workOrderId, $user);

/*  WorkOrder DescriptionType.
    * Declare an array of WorkOrder Description Types Id's. 
    * Get the Id's of DB table WorkOrderDescriptionType.
    * argument 1 is for activeOnly.
*/
// NOTE that $types array has various information, $descriptionTypesIdsDB is just an array of IDs.
// Similar distinctions for several similar cases below.
$descriptionTypesIdsDB = array();
$types = getWorkOrderDescriptionTypes(1, $error_is_db); //Handled in function. 
if ($error_is_db) { //true on query failed.
    $errorId = '637347356801960695';
    $error = "We could not display the Types for this WorkOrder. Database Error. </br>";  // Message for end user
    $logger->errorDB($errorId, 'getWorkOrderDescriptionTypes function failed.', $db);
}

foreach ($types as $value) { // empty array on error, skip iteration.
    $descriptionTypesIdsDB[] = $value["workOrderDescriptionTypeId"]; //Build an array with valid WorkOrderDescriptionTypesIds from DB, table workOrderDescriptionType.
} 

// End WorkOrder DescriptionType Id's check.

/*  WorkOrder Statuses.
    * Declare an array of WorkOrder Statuses Id's. 
    * Get the Id's of DB table workOrderStatus.
*/
$workOrderStatusesIdsDB = array();
$statuses = WorkOrder::getAllWorkOrderStatuses($error_is_db); //Handled in function.

if($error_is_db) { //true on query failed.
    $errorId = '637347405630512636';
    $error .= "We could not display the WorkOrder Statuses. Database Error. </br>";  // Message for end user
    $logger->errorDB($errorId, 'getAllWorkOrderStatuses method failed.', $db);
}

foreach ($statuses as $value) { // empty array on error, skip iteration.
    $workOrderStatusesIdsDB[] = $value["workOrderStatusId"]; //Build an array with valid workOrderStatusIds from DB, table workOrderStatus.
} 
// End WorkOrder Statuses Id's check.

/*  CustomerPersonId.
    * Declare an array of CustomerPersonId's. 
    * Get the Id's of DB table customerPerson.
*/
$customerPersonIdsDB = array();
$customerPersons = CustomerPerson::getAll(true, $error_is_db); //Handled in method.

if($error_is_db) { //true on query failed.
    $errorId = '637352660388386579';
    $error .= "We could not display the Customer Persons. Database Error.";  // Message for end user
    $logger->errorDB($errorId, 'CustomerPerson::getAll() method failed.', $db);
}

foreach ($customerPersons as $value) { // empty array on error, skip iteration.
    $customerPersonIdsDB[] = $value->getCustomerPersonId(); //Build an array with valid customerPersonIds from DB, table customerPerson.
} 

// End customerPersonId's check.

if (!$error && $act == 'updatestatus') {
    /* BEGIN REPLACED 2020-06-09 JM
    // Grab workOrderStatusId (default 0), extras (default 0), and note (default blank) from input.
    $workOrderStatusId = isset($_REQUEST['workOrderStatusId']) ? intval($_REQUEST['workOrderStatusId']) : 0;
    $extras = isset($_REQUEST['extra']) ? $_REQUEST['extra'] : 0;
    $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : "";    
    
    // extras is an array, so we add up the (bitfield) values (reall arithmetic OR, but addition is safe). 
    if (!is_array($extras)) {
        $extras = array();
    }    
    $x = 0;    
    foreach ($extras as $extra) {        
        $x += intval($extra);        
    }
    
    // Pass the three variables to $workOrder->setStatus(), which also uses the current time and current logged-in user,
    //  to create a row in DB table workOrderStatusTime.    
    $workOrder->setStatus($workOrderStatusId, $x, $note);
    
    // Fall through to usual functionality of this page.
    // END REPLACED 2020-06-09 JM
    */

    $v->rule('required', 'workOrderStatusId'); // Required.
    $v->rule('integer', 'workOrderStatusId'); // Integer.
    $v->rule('in', 'workOrderStatusId', $workOrderStatusesIdsDB); // workOrderStatusId value must be in array.

    if (!$v->validate()) {
        $errorId = '637347482268000619';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, invalid WorkOrder Status Id.";
    } else { //check if we have valid CustomerPersonIds. Log an error for each invalid customerPersonId. 
        if (isset($_REQUEST['customerPersonIds']) && is_array($_REQUEST['customerPersonIds'])) {
            foreach ($_REQUEST['customerPersonIds'] as $i => $val) {
                if (!in_array($val, $customerPersonIdsDB)) {
                    $errorId = '637360340206685321';
                    $logger->error2($errorId, "Invalid CustomerPersonId value $val given in input array, position $i.");
                    $error = "Invalid CustomerPersonId, please select from the list."; // Message for end user
                }
            }
        } 
    }

    if (!$error) {
        // BEGIN REPLACEMENT 2020-06-09 JM
        // Grab workOrderStatusId (default 0), customerPersonIds (default 0), and note (default blank) from input.

        $workOrderStatusId = $_REQUEST['workOrderStatusId']; // required, integer, valid.
        
        $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : "";
        $note = truncate_for_db($note, 'WorkOrder UpdateStatus => note', 255, '637351749889766622'); //Maxlength 255, entry in table workorderstatustime.
        
        $customerPersonIds = isset($_REQUEST['customerPersonIds']) ? $_REQUEST['customerPersonIds'] : 0;
        if (!is_array($customerPersonIds)) {
            $customerPersonIds = array();
        }

        // Pass the three variables to $workOrder->setStatus(), which also uses the current time and current logged-in user,
        //  to create a row in DB table workOrderStatusTime. 
        $success = $workOrder->setStatus($workOrderStatusId, $customerPersonIds, $note);
        
        if ($success === false) {
            $errorId = '637381143474694951';
            $error = 'Set Status failed. Database Error. '; // message for User
            $logger->errorDB($errorId, "setStatus() method failed => Hard DB error ", $db);
        } 
        
        // Fall through to usual functionality of this page.
        // END REPLACEMENT 2020-06-09 JM
    }
    unset($workOrderStatusId, $customerPersonIds, $note, $customerPersonIdsDB);
}
unset($workOrderStatusesIdsDB);

if (!$error && $act == 'updateworkorder') {
    // Pass all the self-submitted values in the main form for this page and pass them 
    //  to function $workOrder->update(), then wait a quarter-second and close the fancybox.
    // JM removed workOrderStatus here 2020-11-19.
    
    $v->rule('required', ['workOrderDescriptionTypeId', 'description']); // Required.
    $v->rule('integer', ['workOrderDescriptionTypeId']); // Integer.
    $v->rule('in', 'workOrderDescriptionTypeId', $descriptionTypesIdsDB); // workOrderDescriptionTypeId value must be in array.
    $v->rule('dateFormat', ['genesisDate', 'deliveryDate', 'intakeDate'], 'm/d/Y'); // dateFormat

    if (!$v->validate()) {
        $errorId = '637335370998457259';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";        
    } else {
        if($_REQUEST['deliveryDate']) {

            if($_REQUEST['genesisDate']) {
                $genesis = new DateTime($_REQUEST['genesisDate']);
                $genesis = $genesis->format('Y-m-d H:i:s');
            } else {
                $genesis = '0000-00-00 00:00:00';
            }
            
            $delivery = new DateTime($_REQUEST['deliveryDate']);
            $delivery = $delivery->format('Y-m-d H:i:s');

            if ($genesis > $delivery) {
                $errorId = '637471839888405136';
                $error = 'Genesis Date is grather than Delivery Date. Please enter the correct values!'; // Message for end User.
                $logger->error2($errorId, " Genesis Date is grather than Delivery Date. Genesis input: ". $_REQUEST['genesisDate'] 
                . " Delivery input: " .$_REQUEST['deliveryDate']);
    
            }  
        }
 
        if (!$error) {
            $workorder_request = array();
        
            /* BEGIN REMOVED 2020-11-18 JM: Clearly an error, we cannot possibly want to change the workOrderId!           
            $workorder_request['workOrderId'] = $workOrderId; //validate and checked before for existence in DB.
            // END REMOVED 2020-11-18 JM
            */            
            $workorder_request['workOrderDescriptionTypeId'] = $_REQUEST['workOrderDescriptionTypeId']; // value must be in array descriptionTypesIdsDB.
            /* BEGIN REMOVED 2020-11-18 JM
            // THIS WAS NEVER A GOOD IDEA. I hadn't realize that the old code (replaced 2020-06-11) was actually broken 
            // This failed to insert into workOrderStatusTime.							    
            $workorder_request['workOrderStatusId'] = $_REQUEST['workOrderStatusId']; // value must be in array workOrderStatusesIdsDB.
            // END REMOVED 2020-11-18 JM
            */
        
            $workorder_request['description'] = isset($_REQUEST['description']) ? $_REQUEST['description'] : ''; //Maxlength 128, entry in table workorder.
            $workorder_request['description'] = truncate_for_db($workorder_request['description'], 'WorkOrder Description', 128, '637348143662711090');

            // Calculations to get genesisDate, intakeDate, deliveryDate, handled below on page.
            $workorder_request['genesisDate'] = $_REQUEST['genesisDate'];
            $workorder_request['deliveryDate'] = $_REQUEST['deliveryDate'];
            $workorder_request['intakeDate'] = $_REQUEST['intakeDate'];
            $success = $workOrder->update($workorder_request);

            if (!$success) {
                $errorId = '637350764506703979';
                $error = 'We could not update this WorkOrder. Database Error.'; // Message for end User.
                $logger->errorDB($errorId, 'Update() method failed.', $db);
            } else { //success
                ?>
                    <script type="text/javascript">        
                        setTimeout(function(){ parent.$.fancybox.close(); }, 250);                 
                    </script>           
                <?php 
                die();
            }
            unset($workorder_request);
        }
        unset($genesis, $delivery);
    }
    unset($descriptionTypesIdsDB);
}

include '../includes/header_fb.php';
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
// Just some stuff we need for reference
$months = selectMonths();
$workOrderStatusHierarchy = WorkOrder::getWorkOrderStatusHierarchy($statuses);

// FUNCTION ABSTRACTED 2020-06-12 JM
// display $workOrderStatus and all of its substatuses as HTML OPTIONs
// duplicated from top-level file openworkorders.php
function displayOptions($workOrderStatus, $selectedStatusId) {
    $selected = (intval($workOrderStatus['workOrderStatusId']) == $selectedStatusId) ? ' selected ' : '';
    echo '<option value="' . $workOrderStatus['workOrderStatusId'] . '" ' . $selected . '>';
    // indent to reflect level in hierarchy
    for ($i=0; $i< $workOrderStatus['level'] * 3; ++ $i) {
        echo '&nbsp;';
    }
    echo $workOrderStatus['statusName'] . '</option>';
    foreach ($workOrderStatus['children'] as $child) {
        displayOptions($child, $selectedStatusId);
    }
}

// Do the usual calculations to get genesisDate, intakeDate, deliveryDate. 
$genesisDate = date_parse($workOrder->getGenesisDate());
$deliveryDate = date_parse($workOrder->getDeliveryDate());
$intakeDate = date_parse($workOrder->getIntakeDate());

$genesisDateField = '';
$deliveryDateField = '';
$intakeDateField = '';

if (is_array($genesisDate)) {
    if (isset($genesisDate['year']) && isset($genesisDate['day']) && isset($genesisDate['month'])) {
        $genesisDateField = intval($genesisDate['month']) . '/' . intval($genesisDate['day']) . '/' . intval($genesisDate['year']);
        if ($genesisDateField == '0/0/0') {
            $genesisDateField = '';
        }
    }
}
if (is_array($deliveryDate)) {
    if (isset($deliveryDate['year']) && isset($deliveryDate['day']) && isset($deliveryDate['month'])) {
        $deliveryDateField = intval($deliveryDate['month']) . '/' . intval($deliveryDate['day']) . '/' . intval($deliveryDate['year']);
        if ($deliveryDateField == '0/0/0') {
            $deliveryDateField = '';
        }
    }
}
if (is_array($intakeDate)) {
    if (isset($intakeDate['year']) && isset($intakeDate['day']) && isset($intakeDate['month'])) {
        $intakeDateField = intval($intakeDate['month']) . '/' . intval($intakeDate['day']) . '/' . intval($intakeDate['year']);
        if ($intakeDateField == '0/0/0') {
            $intakeDateField = '';
        }
    }
}

?>
<script type="text/javascript">
    $(function() {
        $( ".datepicker" ).datepicker();
    });


</script>
<style>
body, table { background: white !important; }
.error {
    color:red;
}
</style>

<div id="container" class="clearfix">
    <div class="full-box clearfix ">
        <?php /* Self-submitting "workOrder" form, largely structured by three tables */ ?>
        <form name="workOrder" id="workOrder" method="POST" action="workorder.php">
            <?php /* hidden: workOrderId
                     hidden: act=updateworkorder */ 
            ?>         
            <input type="hidden" name="workOrderId" value="<?php echo intval($workOrder->getWorkOrderId()); ?>">
            <input type="hidden" name="act" value="updateworkorder">
            <table class="siteform table" >                                              
                <tr>
                    <th colspan="2" width="100%">Job</th>
                </tr>
                <tr>
                    <?php /* Job name & Job Number */ ?>
                    <td colspan="2">
                        <?php
                        $job = $workOrder->getJob();
                        echo $job->getName();
                        echo '<br/>';
                        echo $job->getNumber();
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Type</th>
                    <th>Description</th>
                </tr>
    
                <tr>
                    <?php /* "Type": HTML SELECT "workOrderDescriptionTypeId". 
                             First option is "-- Description Type --" with blank value. 
                             Then for each workOrderDescriptionType, display typename; value is workOrderDescriptionTypeId. 
                             If there is a current workOrderDescriptionType, it is initially selected. 
                    */ ?>
                    <td width="30%">
                        <div >
                            <select class="form-control input-sm" name="workOrderDescriptionTypeId" id="workOrderDescriptionTypeId" style="width:100%">
                                <option disabled="true" value="">-- Description Type --</option>
                                <?php
                                foreach ($types as $type) {
                                    $selected = ($type['workOrderDescriptionTypeId'] == $workOrder->getWorkOrderDescriptionTypeId()) ? ' selected' : '';
                                    echo '<option value="' . $type['workOrderDescriptionTypeId'] . '" ' . $selected . '>' . htmlspecialchars($type['typeName']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </td>
                    <?php /* "Description": text input "description". If there is already a description, it is pre-populated. */ ?> 
                    <td width="70%"><input type="text" id="descriptionId" name="description" value="<?php echo $workOrder->getDescription(); ?>" maxlength="128" required></td>
                </tr>
            </table>
            <?php /* Still inside same FORM. Table with three columns: "Genesis Date", "Delivery Date", "Intake Date".
                     Each of thise is pre-populated from dates calculated above, editable with datepicker. */ ?>
            <table class="siteform table-sm">
                <tr>
                    <th width="33%">Genesis Date</th>
                    <th width="34%">Delivery Date</th>
                    <th width="33%">Intake Date</th>
                </tr>
        
                <tr>
                    <td>
                        <input type="text" autocomplete="off" name="genesisDate" class="datepicker" id="genesisDate" value="<?php echo htmlspecialchars($genesisDateField); ?>">
                    </td>
                    <td>
                        <input type="text" autocomplete="off" name="deliveryDate" class="datepicker" id="deliveryDate" value="<?php echo htmlspecialchars($deliveryDateField); ?>">
                    </td>
                    <td>
                        <input type="text" autocomplete="off" name="intakeDate" class="datepicker" id="intakeDate" value="<?php echo htmlspecialchars($intakeDateField); ?>">
                    </td>
                </tr>
            </table>
            <center>
                <?php /* "Submit" button for form */ ?>
                <input type="submit" class="btn btn-secondary mr-auto ml-auto" id="updateWorkOrder" value="Update">
            </center>
        </form>
        <br />    
        <?php /* Prior to v2020-4, the following was still inside same FORM, but JM changed that 2020-11-19, because we 
            really don't want the workOrderStatus to submit with the form: it has its own approach, popping up a dialog. 
            Table with two columns: "Status", "Extra" (associated customerPersons). */ ?>
        <table class="siteform table-sm" >        
            <tr>
                <th>Status</th>
                <th>Extra</th>
                <?php /* BEGIN REMOVED 2020-01-13 JM
                ?>
                <th>Work Stream</th>
                <?php /* END REMOVED 2020-01-13 JM */
                ?>
            </tr>
            <tr colspan="2">
                <td colspan="2" style="text-align:center;">Setting status takes immediate effect, independent of the "Update" in the form above.</td>
            </tr>    
            <tr>
                <?php /* "Status": HTML SELECT. First option is "-- Choose Status --" with blank value. 
                         Then for each workOrderStatus, display status name; value is workOrderStatusId. 
                         If there is a current workOrderStatusId, it is initially selected.

                         Any time we change the status we open a jQuery dialog, see $("#newstatus").change 
                         and Status change dialogs, both below.
                 */ ?>
                <td width="30%">
                    <div class="styled-select">
                        <?php
                            //Method is Logged in class. If we have an error on a query failure we display an error message on parent page (job.php).             
                            $workOrderStatus = $workOrder->getWorkOrderStatus();

                            $workOrderStatusId = $workOrderStatus['workOrderStatusId'];
                            /* BEGIN REPLACED 2020-06-09 JM
                            $extra = $workOrderStatus['extra'];
                            // END REPLACED 2020-06-09 JM
                            */
                            // BEGIN REPLACEMENT 2020-06-09 JM
                            $customerPersonDataArray = $workOrderStatus['customerPersonArray'];
                            // END REPLACEMENT 2020-06-09 JM
                        
                        ?>
                        <select id="newstatus" class="form-control input-sm" name="workOrderStatusId">
                            <option disabled="true" value="">-- Choose Status --</option>
                            <?php
                            /* BEGIN REPLACED 2020-06-18 JM  
                            // Reworked 2020-06-18 to use $workOrderStatusHierarchy rather than $statuses, so we can get these in the right order
                            foreach ($statuses as $status) {
                                $selected = ($status['workOrderStatusId'] == $workOrderStatusId) ? ' selected' : '';
                                echo '<option value="' . $status['workOrderStatusId'] . '" ' . $selected . '>' . 
                                     htmlspecialchars($status['statusName']) . '</option>';
                            }
                            // END REPLACED 2020-06-18 JM
                            */
                            // BEGIN REPLACEMENT 2020-06-18 JM
                            foreach ($workOrderStatusHierarchy as $status) {
                                displayOptions($status, intval($workOrder->getWorkOrderStatusId()));
                            }
                            // END REPLACEMENT 2020-06-18 JM
                            ?>                                
                        </select>                        
                    </div>
                </td>
                
                <?php /* "Extra": a list of people who might need notifications about this status
                (before v2020-3 it was more general, but the rest of its uses have been moved into
                the statuses themselves)
                */ ?>
                <td width="20%">
                    <?php
                    /* BEGIN REPLACED 2020-06-09 JM
                    if (intval($extra)) {
                        $extras = $workOrderStatusExtra[$workOrderStatusId];
                        echo '<ul>';
                        foreach ($extras as $ekey => $e) {
                            if ($ekey & $extra) {
                                echo '<li>' . $e['title'] . '</li>';
                            }
                        }
                        echo '</ul>';
                    }
                    // END REPLACED 2020-06-09 JM
                    */
                    // BEGIN REPLACEMENT 2020-06-09 JM
                    if ($customerPersonDataArray) {
                        echo '<ul>';
                        foreach ($customerPersonDataArray AS $customerPersonData) {
                            echo '<li>' . $customerPersonData['legacyInitials'] . '</li>';
                        }
                        echo '</ul>';
                    }
                    // END REPLACEMENT 2020-06-09 JM
                    ?>
                </td>
            </tr>
        </table>
        
        <h2>History</h2>  <?php /* History of workOrderStatus for this workOrder */ ?>
        <?php
            /* BEGIN REPLACED 2020-06-09 JM
            $db = DB::getInstance();        
            $wosts = array();
        
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".workOrderStatusTime wost ";
            $query .= " join " . DB__NEW_DATABASE . ".workOrderStatus s on wost.workOrderStatusId = s.workOrderStatusId ";
            $query .= " where wost.workOrderId = " . intval($workOrder->getWorkOrderId()) . " ";
            $query .= " order by wost.workOrderStatusTimeId desc ";
    
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $wosts[] = $row;
                    }
                }
            } // >>>00002 ignores failure on DB query!
            // END REPLACED 2020-06-09 JM
            */
            // BEGIN REPLACEMENT 2020-06-09 JM
            //$wosts = $workOrder->getWorkOrderStatusHistory();
            // END REPLACEMENT 2020-06-09 JM
            
            // Separate error messages for this block. The user can still perform other actions on this page!
            $errorWorkOrderStatusHistory = '';
            $wosts = $workOrder->getWorkOrderStatusHistory($error_is_db); // variable pass by reference in method.
            if ($error_is_db) { //true on query failed.
                $errorId = '637351686437838039';
                $errorWorkOrderStatusHistory = 'We could not display the WorkOrder Status History for this workOrder. Database Error.';
                $logger->errorDB($errorId, "getWorkOrderStatusHistory method failled.", $db);
            }

            echo '<table border="1" cellpadding="5" cellspacing="0">';
            // Display specific "query failed" error message for this section.
            if ($errorWorkOrderStatusHistory) {
                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorWorkOrderStatusHistory</div>";
            }
            unset($errorWorkOrderStatusHistory);
                echo '<tr>';
                    echo '<th>Status</th>';
                    echo '<th>Extra</th>';
                    echo '<th>Inserted</th>';
                    echo '<th>Who</th>';
                    echo '<th>Note</th>';
                echo '</tr>';
                foreach ($wosts as $wost) {
                    echo '<tr>';
                        // "Status": status name 
                        echo '<td>' . $wost['statusName'] . '</td>';
                        // "Extra": subtable, with a row for each associated customerPerson (legacyInitials) 
                        echo '<td>'; 
                            /* BEGIN REPLACED 2020-06-09 JM
                            if (intval($wost['extra'])) {                            
                                $extras = $workOrderStatusExtra[$wost['workOrderStatusId']];                            
                                echo '<table border="0" cellpadding="1" cellspacing="0">';
                                    foreach ($extras as $ekey => $e) {
                                        if ($ekey & $wost['extra']) {
                                            echo '<tr><td valign="top">&gt;</td><td valign="top">' . $e['title'] . '</td></tr>';
                                        }                        
                                    }
                                echo '</table>';
                            }
                            // END REPLACED 2020-06-09 JM
                            */
                            // BEGIN REPLACEMENT 2020-06-09 JM
                            if ($wost['customerPersonArray']) {
                                echo '<table border="0" cellpadding="1" cellspacing="0">';
                                foreach($wost['customerPersonArray'] AS $customerPersonData) {
                                    echo '<tr><td valign="top">&gt;</td><td valign="top">' . $customerPersonData['legacyInitials'] . '</td></tr>';
                                }
                                echo '</table>';
                            }
                            // END REPLACEMENT 2020-06-09 JM
                        echo '</td>';
                        // "Inserted": "m/d/Y" date 
                        echo '<td>' . date("m/d/Y",strtotime($wost['inserted'])) . '</td>';                        
                        // "Who": who set the status 
                        echo '<td>';
                            if (intval($wost['personId'])) {
                                $pp = new Person($wost['personId']);
                                if (intval($pp->getPersonId())) {
                                    echo $pp->getFormattedName(1);
                                }
                            }
                        echo '</td>';
                        // "Note": any note accompanying the status 
                        echo '<td>';
                            echo $wost['note'];                    
                        echo '</td>';                    
                    echo '</tr>';                    
                } // END foreach ($wosts...
            
            echo '</table>';

            /*  Status change dialogs.
                Any time we change the status in the "workOrder" form, we open a jQuery dialog (see $("#newstatus").change below). 
                There is a distinct dialog per status; it will have id "status-NN" where "NN" is workOrderStatusId, and title drawn
                from the statusName, and its body consists of a single unnamed self-submitting form, containing:                
                    * hidden: workOrderId
                    * hidden: workOrderStatusId
                    * hidden: act=updatestatus
                    * At the top of what is visible, it says, "This is for statusName"
                    * In v2020-3, we got rid of the old "extras" approach. All that remains that is not implied in the status 
                        itself is a choice of customerPersons to assign for holds, implemented with checkboxes; (the HTML for the 
                        checkboxes uses name="customerPersonIds[]").
                    * "Note": a textarea
                    * submit button labeled "update"
                None of these dialogs are initially visible: they become visible only on a status change.
            */
            foreach ($statuses as $status) {    
                ?>
                <div class="hide-answer" id="status-<?php echo $status['workOrderStatusId']; ?>" title="<?php echo htmlspecialchars($status['statusName']); ?>">
                    <form name="" action="" method="post">
                        <input type="hidden" name="workOrderId" value="<?php echo $workOrder->getWorkOrderId(); ?>">
                        <input type="hidden" name="act" value="updatestatus">
                        <input type="hidden" name="workOrderStatusId" value="<?php echo intval($status['workOrderStatusId']);?>">
                
                        <p>This is for "<?php echo htmlspecialchars($status['statusName']); ?>"</p>
                        <?php
                        /* BEGIN REPLACED 2020-06-09 JM
                        if (isset($workOrderStatusExtra[$status['workOrderStatusId']])) {            
                            $extras = $workOrderStatusExtra[$status['workOrderStatusId']];                            
                            echo '<table>';
                                foreach ($extras as $ekey => $extra) {
                                    echo '<tr>';
                                        echo '<td><input type="checkbox" name="extra[]" value="' . intval($ekey) . '"></td>';
                                        echo '<td>' . $extra['title'] . '</td>';
                                    echo '</tr>';
                                }
                            echo '</table>';
                        }
                        // END REPLACED 2020-06-09 JM
                        */
                        // BEGIN REPLACEMENT 2020-06-09 JM, amended 2020-06-15 JM
                        if ($status['canNotify']) { // This can be 0 to indicate "don't set any notification at all";
                            echo '<select class="form-control" style="width:50%;" id="customerPersonIds" multiple name="customerPersonIds[]">';
                            //$customerPersons = CustomerPerson::getAll(true); // array of CustomerPersons. Handled top page.
                            foreach ($customerPersons AS $customerPerson) {
                                if ($status['canNotify']==CAN_NOTIFY_EMPLOYEES || ($status['canNotify']==CAN_NOTIFY_EORS  && $customerPerson->getIsEor())) {
                                    echo '<option value="' . intval($customerPerson->getCustomerPersonId()) . '">' . $customerPerson->getLegacyInitials() . '</option>';
                                }
                            }
                            echo '</select>';
                        }
                        // END REPLACEMENT 2020-06-09 JM

                        ?>
                        <p class="mt-4">
                        Note:
                        <textarea cols="20" class="form-control" rows="3"  name="note" maxlenght="255"></textarea>                    
                        
                        <p>
                        <input type="submit" class="btn btn-secondary mr-auto ml-auto" id="updateStatus" value="update" border="0">
                    </form>
                </div>
                <?php
            }
            ?>
        
        <script>
    
            $(document).ready(function() {
                $(".hide-answer").dialog({
                        autoOpen: false
                });
    
                <?php /* Open the right status change dialog */ ?>
                $("#newstatus").change(function() {
                    $(".hide-answer").dialog("close");
                    var sel = $(this).val();
                    console.log("Opening #status-" + sel);
                    $("#status-" + sel).dialog('open');
                });
                //Caught the status:
                var oldStat = $("#newstatus").val();

                //Set old Status:
                $(".hide-answer").on('dialogclose', function(event) {
                    $("#newstatus").val(oldStat);
                });
            });
        </script>    
    </div>
</div>

<?php /* client-side input validation */ ?> 
<script>
var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#workOrder').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        'workOrderDescriptionTypeId':{
            required: true           
        },
        'description':{
            required: true
        },
        'workOrderStatusId':{
            required: true
        }
    }
});
validator.showErrors(jsonErrors);

// The moment they start typing/select (or pasting) in a field, remove the validator warning
$('select').on('keyup change', function() {
    $('#validator-warning').hide();
    $('#workOrderDescriptionTypeId-error').hide();
    if ($('#workOrderDescriptionTypeId').hasClass('text-danger')) {
        $("#workOrderDescriptionTypeId").removeClass("text-danger");
    }
});

$('input').on('keyup change', function() {
    $('#validator-warning').hide();
    $('#descriptionId-error').hide();
    if ($('#descriptionId').hasClass('text-danger')) {
        $("#descriptionId").removeClass("text-danger");
    } 
});

$('select').on('keyup change', function() {
    $('#validator-warning').hide();
    $('#newstatus-error').hide();
    if ($('#workOrderStatusId').hasClass('text-danger')) {
        $("#workOrderStatusId").removeClass("text-danger");
    }
});


// George 2021-01-25. Added
$('#updateWorkOrder').click(function() {
        $(".error").hide();

        var genesis = $("#genesisDate").val();
        var delivery = $("#deliveryDate").val();

        if (genesis) {
            var isValidDateGenesis = Date.parse(genesis);
            if (isNaN(isValidDateGenesis)) {
                alert("Incorrect Date format for Genesis Date. Please select the correct date!");
                return false;
            }
        }
        
        if (delivery) {
            var isValidDateDelivery = Date.parse(delivery);
            if (isNaN(isValidDateDelivery)) {
                alert("Incorrect Date format for Delivery Date. Please select the correct date!");
                return false;
            }
        }
  
     
        genesis = new Date(genesis);
        delivery = new Date(delivery);

        if (delivery) {
            if (genesis > delivery) {
                $("#updateWorkOrder").before('<p class="error">Genesis Date is grather than Delivery Date. <br> Please enter the correct values!</p>');
                return false; 
            } 
        }
    
    });
    
    $('#genesisDate, #deliveryDate').on('mousedown', function() {
        // George 2020-04-27 : hide error-messages on mousedown in input filed
        $('.error').hide();
    });
    
    // End ADD.
    
</script>

<?php
    include '../includes/footer_fb.php';
?>