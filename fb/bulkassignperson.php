<?php
/*  fb/bulkassignperson.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to assign numerous workorderTasks to a single person;
        apparently, always adds that person specifically as a staff engineer on the workOrder team 
        (not any other role, not the job team).
    This page will be a child of the page that invokes it.
    
    PRIMARY INPUTS: 
        * $_REQUEST['workOrderId']
        * $_REQUEST['elementId']. Besides an elementId as such, there are the following special cases:
          * (this should no longer occur in v2020-4 or later)
             PHP_INT_MAX means an elementgroup with two or more related elements.
            * Tawny confirmed 2020-04-09 that this has never worked, which JM could pretty much tell from reading the code. So we are replacing
              this with the next case (2020-09-04 JM) for v2020-4. As of 2020-09-04, this is now considered an errror. 
          * String consisting of a comma-separated list of elementIds (introduced 2020-09-04 JM for v2020-4)  
          * 0 means "general": no associated element. 

    OPTIONAL INPUT $_REQUEST['act']. 
        * Only possible value: 'Assign' uses $_REQUEST['personId'].
        
    Significantly rewritten 2020-09-04, 2020-10-27 JM for v2020-4, old change remarks removed and no new ones added; consult 
    version control if you need to see the differences.
*/
include '../inc/config.php';
include '../inc/access.php';


$error = '';
$db = DB::getInstance();   
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$elementIdsString = isset($_REQUEST['elementId']) ? trim($_REQUEST['elementId']) : '0';

$logger->info2('1586469592', "bulkassignperson \$workOrderId = $workOrderId, \$elementId = $elementIdsString" .
    (isset($_REQUEST['personId']) ? $_REQUEST['personId'] : '')
    );

if (!WorkOrder::validate($workOrderId)) {
    $error = "Invalid input workOrderId = $workOrderId";
    $logger->error2('1599251291', $error);
}

if (!$error && intval($elementIdsString) == PHP_INT_MAX) {
    $error = 'elementId input was PHP_INT_MAX; broken case, means "multiple elements but we don\'t know which elements.';
    $logger->error2('1599251400', $error);
}

if (!$error) {
    $elementIds = explode(',', $elementIdsString);
    
    if ( count($elementIds) == 1 &&  $elementIds[0] == '0' ) {
        // "General", which is fine, even though it's not a "valid" elementId.
    } else {
        // loop backward here because we might splice to remove a 0 from the array.
        for ($i=count($elementIds)-1; $i>=0; --$i) {
            $elementIds[$i] = intval($elementIds[$i]);
            if ($elementIds[$i] == 0 && count($elementIds) > 1) {
                // It's a 0 ("general") and there is at least one other value in the array
                // Get rid of it.
                array_splice($elementIds, $i, 1);
            } else if ($elementIds[$i] == '0') {
                // All that's left is a single "General," which is fine.
            } else if (!Element::validate($elementIds[$i])) {
                $error = "Invalid input elementId = '$elementIdsString'; {$elementIds[$i]} is not a valid elementId.";
                $logger->error2('1599252445', $error);
                break;
            } 
        } // END for
    }
}

// Arrive here => input has been validated, $workOrderId and $elementIds should be Assignod
if (!$error) {
    $workOrder = new WorkOrder($workOrderId);

    $contract = $workOrder->getContractWo($error_is_db);
    if($error_is_db) {
        $errorId = '637808015221066818';
        $error = "We could not get the Contract for this WO. Database Error. Error Id: " . $errorId; // message for User
        $logger->errorDB($errorId, "getContractWo() method failed.", $db);
    }
    
    $contractStatus = 0;
    $blockAdd = false; // if true, Block add/delete tasks/structures of tasks.
    
    if(!$error) {
        if($contract) {
            $contractStatus = intval($contract->getCommitted()); // Contract status
        } 
    }
    
    // no update for: 3, 4, 5, 6.
    //$arrNoUpdate = [3, 4, 5, 6];
    //if($contractStatus && in_array($contractStatus, $arrNoUpdate)) {
    //    $blockAdd = true;
    //}
    



    $wots = $workOrder->getWorkOrderTasksRawWithElements($blockAdd);   
    $assigns = array();
    // Get the relevant tasks (really workOrderTasks) into $assigns. 
    foreach ($wots as $workOrderTaskId => $wot) {
        if (isset($wot['elements'])) {
            $elementIdsForWot = $wot['elements'];
            if (is_array($elementIdsForWot)) { // JM 2020-09-04: I think this test will now always be true, 
                                    // though it is possible that $elementIdsForWot
                                    // is an array containing the single value 0, for "general"
                // - If 0=>"general" is passed in, we only want to assign general workOrderTasks
                // - If any single element is passed in, we want to assign all tasks that pertain to that 
                //   particular element, including those that apply to multiple elements
                // - If a comma-separated list of elements is passed in, then we want to assign tasks that 
                //   pertain to precisely that group of elements.
                if (count($elementIds) == count($elementIdsForWot)) {
                    $match_so_far = true;
                    foreach ($elementIds as $elementId) { 
                        if (!in_array($elementId, $elementIdsForWot)) {
                            $match_so_far = false;
                            break;
                        }
                    }
                    if ($match_so_far) {
                        $assigns[] = $wot;
                    }
                }
            } else {
                $logger->error2('1603830560', "\$elementIdsForWot for workOrderTaskId $workOrderTaskId is not an array".
                    (is_scalar($elementIdsForWot) ? " It's '$elementIdsForWot'." : " Not a scalar, either!")
                    );
            }
        }
    }
    unset($workOrderTaskId, $wot);
    
    $act=(isset($_REQUEST['act'])?$_REQUEST['act']:'');
    
    if ($act == 'Assign') {
        // NOTE that inside here we don't look again at $elementIds; they're presumed OK.
        
        // Make the assignments, close the fancybox on completion.
        
        $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;

        
   
        if (!Person::validate($personId)) {
            $error = "Invalid personId $personId";
            $logger->error2('1599255147', $error);
        } else {
            $teamMembershipValidated = false;
            foreach ($assigns as $assign) {
                // First check: is this person already associated with this workorder task?
                $exists = false;
                
                $query = "SELECT workOrderTaskPersonId FROM " . DB__NEW_DATABASE . ".workOrderTaskPerson ";
                $query .= "WHERE workOrderTaskId = " . intval($assign['wot']['workOrderTaskId']) . " ";
                $query .= "AND personId = " . intval($personId) . ";";
                
                $result = $db->query($query);
                if ($result) {
                    $exists = $result->num_rows > 0;
                } else {
                    $logger->errorDb('1599255508', 'Hard DB error', $db);
                    $error = 'Hard DB error selecting from workOrderTaskPerson';
                    break;
                }
    
                if (!$exists) {
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskPerson (workOrderTaskId, personId) VALUES (";
                    $query .= intval($assign['wot']['workOrderTaskId']);
                    $query .= ", " . intval($personId) . ");";
                    
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1599255666', 'Hard DB error', $db);
                        $error = 'Hard DB error inserting into workOrderTaskPerson';
                        break;
                    }
                    
                    // BEGIN MARTIN COMMENT
                    // this is the same kludge in WorkOrderTask.class.php
                    // this should all probably get put into that class .. but didnt want to test it all right now
                    
                    // here comes some kludge for auto adding a person to the work order team
                    // if the task gets assigned to them to work on.
                    // first part of the kludge is to assign a particular company to the customer table
                    // i.e. add the "company" called Sound Structural Solutions to the "customer" called Sound Structural Solutions.
                    //  this company can then be used to create a companyPerson with the SSS company with the person in question
                    // since we can look up the correct company when dealing with the particular customer (the employees)
                    // so added a column in the "customer" table called companyId
                    // and added companyId of '1' to for the companyId column in the customer table (for the customer sss eng)
                    // currently theres only one row in the customer table.
                    // END MARTIN COMMENT
                    
                    // Of course, of this is Assigning to happen at all, it will be first time we get here. JM added code 2020-09-04
                    // to prevent having to test this each time through.
                    // 
                    // It's OK that this isn't transactional: perfectly OK to create the companyPerson & fail on the customerPerson
                    // This is somewhat reworked 2020-09-04 JM using our new-ish validate methods; also added a ton of error-checking
                    
                    // $workOrder = new WorkOrder(intval($assign['wot']['workOrderId'])); // REMOVED 2020-09-04 JM, has to be the same workOrder!
                    if (!$teamMembershipValidated) {
                        $jobId = intval($workOrder->getJobId());
                        if (!Job::validate($jobId)) {
                            $error = "Invalid jobId $jobId";
                            $logger->error2('1599257909', $error);
                            break;
                        }
                        $job = new Job($jobId);
                        $customerId = $job->getCustomerId();
                        if (!Customer::validate($customerId)) {
                            $error = "Invalid customerId $customerId";
                            $logger->error2('1599257985', $error);
                            break;
                        }
                        $customer = new Customer($job->getCustomerId());
                        $companyId = $customer->getCompanyId();
                        if (!Company::validate($companyId)) {
                            $error = "Invalid companyId $companyId";
                            $logger->error2('1599258165', $error);
                            break;
                        }
                        
                        $companyPersonId = 0;
                        $query = "SELECT companyPersonId FROM " . DB__NEW_DATABASE . ".companyPerson ";
                        $query .= "WHERE companyId = " . intval($customer->getCompanyId()) . " ";
                        $query .= "AND personId = " . intval($personId) . ";";
                        
                        $result = $db->query($query);
                        if (!$result) {
                            $logger->errorDb('1599258369', 'Hard DB error', $db);
                            $error = 'Hard DB error selecting companyPersonId';
                            break;
                        }
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $companyPersonId = intval($row['companyPersonId']);
                        } else { 
                            // Create the companyPerson we need here, so we can show that this person is in this role
                            // as an employee of this particular company.
                            $query = "INSERT INTO " . DB__NEW_DATABASE . ".companyPerson (companyId, personId) VALUES (";
                            $query .= intval($customer->getCompanyId());
                            $query .= ", " . intval($personId) . ");";
        
                            $result = $db->query($query);
                            if (!$result) {
                                $logger->errorDb('1599258422', 'Hard DB error', $db);
                                $error = 'Hard DB error inserting into companyPersonId';
                                break;
                            }
                            $companyPersonId = intval($db->insert_id);                        
                        }
        
                        // Do we already have the relevant companyPerson as staff engineer on this team?
                        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".team ";
                        $query .= "WHERE inTable = " . intval(INTABLE_WORKORDER) . " ";
                        $query .= "AND id = " . intval($workOrder->getWorkOrderId()) . " ";
                        $query .= "AND companyPersonId = " . intval($companyPersonId) . " ";
                        $query .= "AND teamPositionId = " . intval(TEAM_POS_ID_STAFF_ENG) . ";";
    
                        $exists = false;
                        $result = $db->query($query);
                        if (!$result) {
                            $logger->errorDb('1599258546', 'Hard DB error', $db);
                            $error = 'Hard DB error selecting from team';
                            break;
                        }
                        $exists = ($result->num_rows > 0);
    
                        if (!$exists) {
                            // Add the relevant companyPerson as staff engineer on this team
                            $query = "INSERT INTO " . DB__NEW_DATABASE . ".team(inTable, id, teamPositionId, companyPersonId) VALUES (";
                            $query .= intval(INTABLE_WORKORDER);
                            $query .= ", " . intval($workOrder->getWorkOrderId());
                            $query .= ", " . intval(TEAM_POS_ID_STAFF_ENG);
                            $query .= ", " . intval($companyPersonId) . ");";
    
                            $result = $db->query($query);
                            if (!$result) {
                                $logger->errorDb('1599258673', 'Hard DB error', $db);
                                $error = 'Hard DB error inserting into team';
                                break;
                            }
                        }
                        $teamMembershipValidated = true;
                    } // END if (!$teamMembershipValidated), END the part Martin describes above as a kluge.
                } // else already assigned, nothing to do here
            } // END foreach ($assigns as $assign) {
            
            if (!$error ) {
                include '../includes/header_fb.php';
                ?>
                <script>
                    parent.$.fancybox.close();
                </script>
                <?php
                include '../includes/footer_fb.php';
                die();
            }
        } 
    } // END if ($act == 'Assign')





    // Action Assign to MySelf
    if ($act == 'AssignMe') {
        // NOTE that inside here we don't look again at $elementIds; they're presumed OK.
        
        // Make the assignments, close the fancybox on completion.
        
        $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;

    
        if (!Person::validate($personId)) {
            $error = "Invalid personId $personId";
            $logger->error2('1599255147', $error);
        } else {
            $teamMembershipValidated = false;
            foreach ($assigns as $assign) {
                // First check: is this person already associated with this workorder task?
                $exists = false;
                
                $query = "SELECT workOrderTaskPersonId FROM " . DB__NEW_DATABASE . ".workOrderTaskPerson ";
                $query .= "WHERE workOrderTaskId = " . intval($assign['wot']['workOrderTaskId']) . " ";
                $query .= "AND personId = " . intval($personId) . ";";
                
                $result = $db->query($query);
                if ($result) {
                    $exists = $result->num_rows > 0;
                } else {
                    $logger->errorDb('1599255508', 'Hard DB error', $db);
                    $error = 'Hard DB error selecting from workOrderTaskPerson';
                    break;
                }
    
                if (!$exists) {
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskPerson (workOrderTaskId, personId) VALUES (";
                    $query .= intval($assign['wot']['workOrderTaskId']);
                    $query .= ", " . intval($personId) . ");";
                    
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1599255666', 'Hard DB error', $db);
                        $error = 'Hard DB error inserting into workOrderTaskPerson';
                        break;
                    }
                    
                    // BEGIN MARTIN COMMENT
                    // this is the same kludge in WorkOrderTask.class.php
                    // this should all probably get put into that class .. but didnt want to test it all right now
                    
                    // here comes some kludge for auto adding a person to the work order team
                    // if the task gets assigned to them to work on.
                    // first part of the kludge is to assign a particular company to the customer table
                    // i.e. add the "company" called Sound Structural Solutions to the "customer" called Sound Structural Solutions.
                    //  this company can then be used to create a companyPerson with the SSS company with the person in question
                    // since we can look up the correct company when dealing with the particular customer (the employees)
                    // so added a column in the "customer" table called companyId
                    // and added companyId of '1' to for the companyId column in the customer table (for the customer sss eng)
                    // currently theres only one row in the customer table.
                    // END MARTIN COMMENT
                    
                    // Of course, of this is Assigning to happen at all, it will be first time we get here. JM added code 2020-09-04
                    // to prevent having to test this each time through.
                    // 
                    // It's OK that this isn't transactional: perfectly OK to create the companyPerson & fail on the customerPerson
                    // This is somewhat reworked 2020-09-04 JM using our new-ish validate methods; also added a ton of error-checking
                    
                    // $workOrder = new WorkOrder(intval($assign['wot']['workOrderId'])); // REMOVED 2020-09-04 JM, has to be the same workOrder!
                    if (!$teamMembershipValidated) {
                        $jobId = intval($workOrder->getJobId());
                        if (!Job::validate($jobId)) {
                            $error = "Invalid jobId $jobId";
                            $logger->error2('1599257909', $error);
                            break;
                        }
                        $job = new Job($jobId);
                        $customerId = $job->getCustomerId();
                        if (!Customer::validate($customerId)) {
                            $error = "Invalid customerId $customerId";
                            $logger->error2('1599257985', $error);
                            break;
                        }
                        $customer = new Customer($job->getCustomerId());
                        $companyId = $customer->getCompanyId();
                        if (!Company::validate($companyId)) {
                            $error = "Invalid companyId $companyId";
                            $logger->error2('1599258165', $error);
                            break;
                        }
                        
                        $companyPersonId = 0;
                        $query = "SELECT companyPersonId FROM " . DB__NEW_DATABASE . ".companyPerson ";
                        $query .= "WHERE companyId = " . intval($customer->getCompanyId()) . " ";
                        $query .= "AND personId = " . intval($personId) . ";";
                        
                        $result = $db->query($query);
                        if (!$result) {
                            $logger->errorDb('1599258369', 'Hard DB error', $db);
                            $error = 'Hard DB error selecting companyPersonId';
                            break;
                        }
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $companyPersonId = intval($row['companyPersonId']);
                        } else { 
                            // Create the companyPerson we need here, so we can show that this person is in this role
                            // as an employee of this particular company.
                            $query = "INSERT INTO " . DB__NEW_DATABASE . ".companyPerson (companyId, personId) VALUES (";
                            $query .= intval($customer->getCompanyId());
                            $query .= ", " . intval($personId) . ");";
        
                            $result = $db->query($query);
                            if (!$result) {
                                $logger->errorDb('1599258422', 'Hard DB error', $db);
                                $error = 'Hard DB error inserting into companyPersonId';
                                break;
                            }
                            $companyPersonId = intval($db->insert_id);                        
                        }
        
                        // Do we already have the relevant companyPerson as staff engineer on this team?
                        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".team ";
                        $query .= "WHERE inTable = " . intval(INTABLE_WORKORDER) . " ";
                        $query .= "AND id = " . intval($workOrder->getWorkOrderId()) . " ";
                        $query .= "AND companyPersonId = " . intval($companyPersonId) . " ";
                        $query .= "AND teamPositionId = " . intval(TEAM_POS_ID_STAFF_ENG) . ";";
    
                        $exists = false;
                        $result = $db->query($query);
                        if (!$result) {
                            $logger->errorDb('1599258546', 'Hard DB error', $db);
                            $error = 'Hard DB error selecting from team';
                            break;
                        }
                        $exists = ($result->num_rows > 0);
    
                        if (!$exists) {
                            // Add the relevant companyPerson as staff engineer on this team
                            $query = "INSERT INTO " . DB__NEW_DATABASE . ".team(inTable, id, teamPositionId, companyPersonId) VALUES (";
                            $query .= intval(INTABLE_WORKORDER);
                            $query .= ", " . intval($workOrder->getWorkOrderId());
                            $query .= ", " . intval(TEAM_POS_ID_STAFF_ENG);
                            $query .= ", " . intval($companyPersonId) . ");";
    
                            $result = $db->query($query);
                            if (!$result) {
                                $logger->errorDb('1599258673', 'Hard DB error', $db);
                                $error = 'Hard DB error inserting into team';
                                break;
                            }
                        }
                        $teamMembershipValidated = true;
                    } // END if (!$teamMembershipValidated), END the part Martin describes above as a kluge.
                } // else already assigned, nothing to do here
            } // END foreach ($assigns as $assign) {
            
            if (!$error ) {
                include '../includes/header_fb.php';
                ?>
                <script>
                    parent.$.fancybox.close();
                </script>
                <?php
                include '../includes/footer_fb.php';
                die();
            }
        } 
    } // END if ($act == 'Assign to Myself')
}

include '../includes/header_fb.php';
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>
<style>
#editTooltip, #hideTooltip, .btn-outline-success {
    display:none;
}
#Assign {
    margin-bottom:30px;
}
.fancybox-skin{
 
    left:-0px!important;
    padding:0px!important;
}
body {background:#fff;}
</style>

<?php
if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"error\" style=\"color:red\">$error</div>";
} else {
    /* Form implemented as table.
    
       Hidden act='Assign', elementId, workOrderId. At the top of the display it says, "You are about to bulk assign a user to all these tasks", 
       followed by a non-editable list of tasks associated that match both workOrderId & elementId. We then offer 
       an HTML SELECT to choose an personId of an employee of the current customer (as of 2018-02, always SSS). 
       That HTML SELECT initially shows "-- Assigned To --"; each option shows legacyInitials + Name.
       ("legacyInitials" is a poor name: nothing "legacy" about it.)
       Submit button is labeled "Assign". When that is clicked, it leads to the action for act='Assign'.
    */
    echo "\n";
    
    $elementNameString = '';
    foreach ($elementIds as $elementId) {
        $element = new Element($elementId);
        if ($elementNameString) {
            $elementNameString .= ', ';
        }
        $elementNameString .= $element->getElementName();
        unset($element);
    }    
    
    ?>    
    <center>
        <h2>Assign tasks for <?= $elementNameString ?></h2>
        <?php 
        $arrEmpl = [];
   
        $employees = $customer->getEmployees(1);
        foreach ($employees as $employee) {
            $arrEmpl[] = intval($employee->getUserId());
        }
  
   
    ?>       
        <div style="padding-right:250px; padding-bottom:10px;">
            <form name="bulkMe" id="bulkMe" method="POST" action="">
                <input type="hidden" name="act" value="AssignMe">
                <input type="hidden" name="elementId" value="<?= $elementIdsString ?>">
                <input type="hidden" name="workOrderId" value="<?=  intval($workOrderId) ?>">
                <?php if(in_array($user->getUserId(),  $arrEmpl)) { ?>
                    <input type="hidden"id="personId" name="personId"  value="<?=intval($user->getUserId()) ?>">
                <?php } ?>
                <table border="0" cellpadding="0" cellspacing="0">

                    <tr>
                        <td>            
                        <input type="submit" class="btn btn-secondary btn-sm " value="Assign To Myself" id="AssignMe" border="0">                       
                        </td>
                    </tr>
                    
                
                </table>
            
            </form>
        </div>



        <form name="bulk" id="bulk" method="POST" action="">
            <input type="hidden" name="act" value="Assign">
            <input type="hidden" name="elementId" value="<?= $elementIdsString ?>">
            <input type="hidden" name="workOrderId" value="<?=  intval($workOrderId) ?>">
            <table border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>                
                        You are about to bulk assign a user to all these tasks:
                        <hr>                        
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <?php
                        foreach ($assigns as $assign) {
                            echo $assign['wot']['description']; // workOrderTask description
                            echo '<br>';
                        }
                        ?>
                        <hr>                    
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <select class="form-control form-control-sm" id="personId" name="personId">
                            <option value="">-- Assigned To --</option>
                            <?php
                            $employees = $customer->getEmployees(1);
                            foreach ($employees as $employee) {
                                echo '<option class="form-control form-control-sm" value="' . intval($employee->getUserId()) . '">' 
                                    .'[' . $employee->legacyInitials . '] ' . $employee->getFirstName() . ' ' . $employee->getLastName() . '</option>';
                            }
                            ?>
                        </select>                
                    </td>
                </tr>
                
                <tr>
                    <td>            
                        <input type="submit" class="btn btn-secondary btn-sm mt-3" value="Assign" id="Assign" border="0">            
                    </td>
                </tr>
            
            </table>
        </form>
  
        
 
    </center>
    <div style="height:20px"></div>
<?php    
}

include '../includes/footer_fb.php';
?>