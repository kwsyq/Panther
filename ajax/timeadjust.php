<?php 
/*  ajax/timeadjust.php

    INPUT $_REQUEST['day']: a date, in database form ('YYYY-MM-DD')
    INPUT $_REQUEST['increment']: increment is in minutes, may be positive or negative.
    INPUT $_REQUEST['workOrderTaskId']: normally, primary key in DB table WorkOrderTask but
                                        has some special values: -100 (sick/vacation), -200 (holiday).
    INPUT $_REQUEST['personId']: person whose time to adjust. Optional: default is current user.
    quasi-INPUT $called_from_admin_side: present and set true if this is wrapped by _admin/ajax/timeadjust.php. Allows us to make this
      behave differently with respect to workOrderTaskTimeLateModification  and ptoLateModification when called from admin side. 
      
    $called_from_admin_side introduced 2020-10-09 JM. Considerable rework at that time, to the point where I decided not to track individual
    differences in the file; see SVN version control if you want history.

    Adjust a person's (employee's) counted hours for a particular day.

    This is one of the few AJAX functions that calls ajaxorigin (in functions.php) to die if the origin is not a page from an authorized server.
    
    Returns an associative array. It's not immediately obvious to me (JM) that all failures would be caught, 
    but on (for example) invalid inputs it will return with status="fail". Otherwise:
        * 'status': "success"
        * 'workOrderTaskId': as input, except -100 => 1 (PTOTYPE_SICK_VACATION); -200 => 2 (PTOTYPE_HOLIDAY)
        * 'day': as input, possibly with some escapes.
        * 'personId': as input, or current user if defaulted.
        * 'minutes': total for this person+workOrderTaskId+day, after adjustment. Blank if zero.
        * 'hourincrement': input increment converted to decimal hours, with two places past the decimal point; e.g. 15 => 0.25.
    
    >>>00028: It would probably be best to do this all transactionally
    
    NOTE that besides being called directly as AJAX, this is also included by _admin/ajax/timeadjust.php. We do it that way to validate 
    admin-area Apache login, and to set quasi-input $called_from_admin_side.
*/

if (!isset($called_from_admin_side)) {
    $called_from_admin_side = false;
}

include dirname(__FILE__).'/../inc/config.php'; // NOTE: effective path will be the same regardless of whether ajax/timeadjust.php is called directly or included.
include dirname(__FILE__).'/../inc/access.php'; // NOTE: effective path will be the same regardless of whether ajax/timeadjust.php is called directly or included.

/*
Before doing anything else, this calls ajaxorigin() to try to assure that the calling code is part of the customer's own system. 
Also, in a few places in the code below, checks to make sure there is a logged-in user before acting.
*/

ajaxorigin();

$response = array();
$response['status'] = 'fail';

// The following function introduced 2020-10-06 JM
// We should NOT continue after a hard DB error!
function returnAfterError() {
    global $response;
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}

$v = new Validator2($_REQUEST);

list($error, $errorId) = $v->init_validation();
if ($error){
    $logger->error2('1602268942', "Error(s) found in init validation: [".json_encode($v->errors())."]");
    returnAfterError();
}
$v->stopOnFirstFail();
$v->rule('required', ['day', 'increment', 'workOrderTaskId']);
//$v->rule('regex', 'day', '/^20[0-9][0-9]-(0[1-9]|1[0-2])-([0-2][1-9]|3[0-1])$/'); // validate date is in the 2000s; some invalid dates (like February 30) will sneak through
$v->rule('dateFormat', 'day', "Y-m-d"); 
$v->rule('integer', ['increment', 'workOrderTaskId', 'personId']);
$v->rule('min', 'increment', -240);  // 240 minutes = 4 hours
$v->rule('max', 'increment', 240);
$v->rule('min', 'personId', 1);
if( !$v->validate() ) {
    $logger->error2('1602268956', "Invalid input. Errors found: ".json_encode($v->errors()));
    returnAfterError();
}

$day = $_REQUEST['day'];
$increment = intval($_REQUEST['increment']);                                           
$workOrderTaskId = intval($_REQUEST['workOrderTaskId']);
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : intval($user->getUserId());

if ($personId != $user->getUserId()) {
    if (!$called_from_admin_side) {
        // Not necessarily really a non-admin, but not logged in from admin side.
        $logger->error2('1602269123', "Non-admin " . $user->getUserId() . " trying to adjust time for " . $personId);
        returnAfterError();
    }
}

if (!Person::validate($personId)) {
    $logger->error2('1602269487', "trying to adjust time for invalid personId $personId");
    returnAfterError();
}

// $workOrderTaskId should be either one of the two supported special values or a valid workOrderTaskId
if ($workOrderTaskId != -100 && $workOrderTaskId != -200 && !(WorkOrderTask::validate($workOrderTaskId))) {
    $logger->error2('1602269876', "trying to adjust time for invalid workOrderTaskId $workOrderTaskId");
    returnAfterError();
}
// Input validation complete

$userBeingChanged = new User($personId, $customer);

$ymdArray = explode('-', $day); // [0]=>year, [1]=>month, [2]=>day
if ($ymdArray[2] < 16) {
    $ymdArray[2] = 1;
} else { 
    $ymdArray[2] = 16;
}
$payPeriodBegin = implode('-', $ymdArray); 

$payPeriodInfo = $userBeingChanged->getCustomerPersonPayPeriodInfo($payPeriodBegin);
$userIsModifyingLate = $payPeriodInfo && $payPeriodInfo['reopenTime'] && !$called_from_admin_side; 

$db = DB::getInstance();
if (intval($user->getUserId())) {  // (>>>00016) Martin comment: this just checks if someone is logged in. do more checks here if need to see who can do what.
    $actingOnPTO = $workOrderTaskId < 0;
    
    $ptoId = null;
    $workOrderTaskTimeId = null;
    $oldMins = 0;
    $newMins = 0;
    
    if ($actingOnPTO) {
        $ptoTypeId = abs( ($workOrderTaskId / 100)); // Translate special inputs -100, -200 into appropriate PTOTYPE_SICK_VACATION, PTOTYPE_HOLIDAY respectively.
        /*
            In the sick/vacation case, we insert or update a row in the pto DB table. 
            This is the row for this personId, day, and where ptoTypeId = PTOTYPE_SICK_VACATION. 
            The rules should be mostly obvious; note that if we net out to zero minutes, we delete the row.
        */
        if ($ptoTypeId == PTOTYPE_SICK_VACATION) {
            $deleted = false;  // Boolean: PTO record was deleted because minutes went to zero
                               //  (or below zero, according to code, but I don't think that's possible).
            $exists = false;   // Boolean: a PTO record already existed for this person for this day
            
            $query  = "SELECT ptoId, minutes FROM " . DB__NEW_DATABASE . ".pto ";
            $query .= "WHERE personId = " . intval($personId) . " ";
            $query .= "AND day = '" . $db->real_escape_string($day) . "' ";
            $query .= "AND ptoTypeId = " . intval(PTOTYPE_SICK_VACATION) . ";";

            $result = $db->query($query);
            
            if (!$result) {
                $logger->errorDb('1602014063', 'Hard DB error', $db);
                returnAfterError();
            }
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $oldMins = $row['minutes'];
                $ptoId = $row['ptoId'];
                $exists = true;
            }
            $newMins = max($oldMins + $increment, 0);
            
            if ($increment) {
                $vacationError = $userBeingChanged->getVacationUsed() + $increment >
                        intval($userBeingChanged->getTotalVacationTime(Array('currentonly'=>true)));
           
                if ($vacationError) {   
                    $response['vacationError'] = 1;
                    $response['hourincrement'] = 0;
                    $response['minutes'] = "";
                    $response['status']="success";
                    returnAfterError();
                }

                if ($newMins == 0 && $ptoId && !$userIsModifyingLate) {
                    // Time has gone to zero, kill the row.
                    //  But, per test just above, we don't delete if this is a "late modification", because we need
                    //  to keep the old pto row for referential integrity purposes.
                    $query  = "DELETE FROM " . DB__NEW_DATABASE . ".pto ";
                    $query .= "WHERE ptoId = $ptoId;";
                    
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1602014543', 'Hard DB error', $db);
                        returnAfterError();
                    }
                    
                    $deleted = true;                        
                } else if (!$exists && ($increment > 0)) {                
                    $query  = "INSERT INTO " . DB__NEW_DATABASE . ".pto (personId, day, ptoTypeId, minutes, lastModificationPersonId";
                    if ($called_from_admin_side) {
                        // Necessarily, the admin accepts this!
                        $query .= ", adminAcceptTime";
                        $query .= ", adminPersonId";
                    }
                    $query .= ") VALUES (";
                    $query .= intval($personId);
                    $query .= ", '" . $db->real_escape_string($day) . "' ";
                    $query .= "," . intval(PTOTYPE_SICK_VACATION) . " ";
                    $query .= ", " . intval($newMins);
                    $query .= ", " . intval($user->getUserId());
                    if ($called_from_admin_side) {
                        // Necessarily, the admin accepts this!
                        $query .= ", now()";
                        $query .= ", ". intval($user->getUserId());
                    }
                    $query .= ");";
                    
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1602014363', 'Hard DB error', $db);
                        returnAfterError();
                    }
                    $ptoId = $db->insert_id;
                } else {
                    $query  = "UPDATE " . DB__NEW_DATABASE . ".pto ";
                    $query .= "SET minutes = " . intval($newMins) . ", lastModificationPersonId = " . intval($user->getUserId()) . " ";
                    if ($called_from_admin_side) {
                        // necessarily the admin accepts this!
                        $query .= ", adminAcceptTime = now()";
                        $query .= ", adminPersonId=". intval($user->getUserId());
                    }
                    $query .= " WHERE personId = " . intval($personId) . " ";
                    $query .= "AND day = '" . $db->real_escape_string($day) . "' ";
                    $query .= "AND ptoTypeId = " . intval(PTOTYPE_SICK_VACATION) . ";";
                    
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDb('1602014599', 'Hard DB error', $db);
                        returnAfterError();
                    }                        
                }
            }
        } else if ($ptoTypeId == PTOTYPE_HOLIDAY) {
            // Holidays are handled differently, and entirely from the administrative side. An employee cannot adjust their own
            // holiday time, and admins assign that in a way that has nothing to do with the usual page for
            // reviewing timesheets.
            $logger->error2('1602279881', 'ptoTypeId ' . $ptoTypeId . 'is PTOTYPE_HOLIDAY, shouldn\'t be using ajax/timeadjust.php');
            returnAfterError();
        } else {
            $logger->error2('1602279999', "workOrderTaskId=$workOrderTaskId, makes no sense, shouldn't ever get here");
        }
    } else {
        /*
            NOT PTO. Performs a very similar operation on DB table workOrderTaskTime instead of DB table pto. 
            In this case, of course, we use workOrderTaskId directly, instead of a ptoTypeId.
        */ 
        
        $exists = false;   // Boolean: a workOrderTaskTime record already existed for this person, day, and workOrderTaskTime 
        
        $query  = "SELECT workOrderTaskTimeId, minutes FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
        $query .= "WHERE workOrderTaskId = " . intval($workOrderTaskId) . " ";
        $query .= "AND day = '" . $db->real_escape_string($day) . "' ";
        $query .= "AND personId = " . intval($personId) . ";";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1602015903', 'Hard DB error', $db);
            returnAfterError();
        }
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $oldMins = $row['minutes'];
            $workOrderTaskTimeId = $row['workOrderTaskTimeId'];
            $exists = true;
        }
        
        $newMins = max($oldMins + $increment, 0);
        
        if ($increment) {
            if ($newMins == 0 && $workOrderTaskTimeId && !$userIsModifyingLate) {
                // Time has gone to zero, kill the row.
                //  But, per test just above, we don't delete if this is a "late modification", because we need
                //  to keep the old pto row for referential integrity purposes.
                $query  = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
                $query .= "WHERE workOrderTaskTimeId = " . intval($workOrderTaskTimeId) . ";";
                $result = $db->query($query);
                if (!$result) {
                    $logger->errorDb('1602020700', 'Hard DB error', $db);
                    returnAfterError();
                }
            } else if (!$exists && ($increment > 0)) {
                $query  = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskTime (workOrderTaskId, personId, day, minutes, lastModificationPersonId";
                if ($called_from_admin_side) {
                    // Necessarily, the admin accepts this!
                    $query .= ", adminAcceptTime";
                    $query .= ", adminPersonId";
                }
                $query .= ") VALUES (";
                $query .= intval($workOrderTaskId);
                $query .= "," . intval($personId);
                $query .= ", '" . $db->real_escape_string($day) . "'";
                $query .= ", " . intval($newMins);
                $query .= ", " . intval($user->getUserId());
                if ($called_from_admin_side) {
                    // Necessarily, the admin accepts this!
                    $query .= ", now()";
                    $query .= ", ". intval($user->getUserId());
                }
                $query .= ");";
                
                $result = $db->query($query);
                if (!$result) {
                    $logger->errorDb('1602016386', 'Hard DB error', $db);
                    returnAfterError();
                }
                $workOrderTaskTimeId = $db->insert_id;
            } else {
                $query  = "UPDATE " . DB__NEW_DATABASE . ".workOrderTaskTime ";
                $query .= "SET minutes = " . intval($newMins) . ", lastModificationPersonId =" . intval($user->getUserId()) . " ";
                if ($called_from_admin_side) {
                    // necessarily the admin accepts this!
                    $query .= ", adminAcceptTime = now()";
                    $query .= ", adminPersonId=". intval($user->getUserId());
                }
                $query .= " WHERE workOrderTaskId = " . intval($workOrderTaskId) . " ";
                $query .= "AND day = '" . $db->real_escape_string($day) . "' ";
                $query .= "AND personId = " . intval($personId) . ";";
            
                $result = $db->query($query);
                if (!$result) {
                    $logger->errorDb('1602016890', 'Hard DB error', $db);
                    returnAfterError();
                }
            }
        }
    }
    
    if ($called_from_admin_side) {
        // If the admin made a modification, any history of prior user modifications is irrelevant
        if ($ptoId) {
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".ptoLateModification WHERE ptoId=$ptoId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1602281748', 'Hard DB error', $db);
                returnAfterError();
            }
        }
        if ($workOrderTaskTimeId) {
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderTaskTimeLateModification WHERE workOrderTaskTimeId=$workOrderTaskTimeId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1602281748', 'Hard DB error', $db);
                returnAfterError();
            }
        }
    } else if ($userIsModifyingLate && $newMins != $oldMins) {
        // We need to make an entry in ptoLateModification or workOrderTaskTimeLateModification  
        if ($ptoId) {
            $query  = "INSERT INTO " . DB__NEW_DATABASE . ".ptoLateModification (ptoId, oldMinutes, newMinutes) VALUES (";
            $query .= $ptoId;
            $query .= ", $oldMins";
            $query .= ", $newMins";
            $query .= ");";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1602281894', 'Hard DB error', $db);
                returnAfterError();
            }
        }
        if ($workOrderTaskTimeId) {
            $query  = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderTaskTimeLateModification (workOrderTaskTimeId, oldMinutes, newMinutes) VALUES (";
            $query .= $workOrderTaskTimeId;
            $query .= ", $oldMins";
            $query .= ", $newMins";
            $query .= ");";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1602281945', 'Hard DB error', $db);
                returnAfterError();
            }
        }
    }
    
    // DB changes have been made, now we report.
    $effectiveHourIncrement = number_format((float)($newMins-$oldMins)/60, 2, '.', '');
    if ($actingOnPTO) {
        $query  = "SELECT * FROM " . DB__NEW_DATABASE . ".pto ";
        $query .= "WHERE personId = " . intval($personId) . " ";
        $query .= "AND day = '" . $db->real_escape_string($day) . "' ";
        $query .= "AND ptoTypeId = " . intval(PTOTYPE_SICK_VACATION) . " ";
        $query .= "AND minutes > 0";			
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1602015037', 'Hard DB error', $db);
            returnAfterError();
        }                        
            
        $response['status'] = 'success';
        $response['workOrderTaskId'] = intval($workOrderTaskId);
        $response['day'] = $db->real_escape_string($day);
        $response['personId'] = intval($personId);
        $response['hourincrement'] = $effectiveHourIncrement;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc(); // NOTE that there can be at most one such row.
            $response['minutes'] = number_format((float)intval(intval($row['minutes']))/60, 2, '.', '');            
        } else {
            $response['minutes'] = '';
        }
    } else {
        $query  = "SELECT workOrderTaskTimeId, workOrderTaskId, day, personId, minutes FROM " . DB__NEW_DATABASE . ".workOrderTaskTime ";
        $query .= "WHERE workOrderTaskId = " . intval($workOrderTaskId) . " ";
        $query .= "AND day = '" . $db->real_escape_string($day) . "' ";
        $query .= "AND personId = " . intval($personId) . " ";
        $query .= "AND minutes > 0;";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1602020339', 'Hard DB error', $db);
            returnAfterError();
        }
        
        $response['status'] = 'success';
        $response['workOrderTaskId'] = intval($workOrderTaskId);
        $response['day'] = $db->real_escape_string($day);
        $response['personId'] = intval($personId);
        $response['hourincrement'] = $effectiveHourIncrement;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc(); // NOTE that there can be at most one such row.
            $response['minutes'] = number_format((float)intval($row['minutes'])/60, 2, '.', '');        
        } else {
            $response['minutes'] = '';        
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
die();

?>