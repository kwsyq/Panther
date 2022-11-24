<?php 
/* ajax/delinklocation.php
    
    Delink a location from a company, or person
    
    INPUT
    * $_REQUEST['locationId'] - primary key to row in DB table Location to be delinked from company/person. 
    * $_REQUEST['companyId'] - (optional) primary key to row in DB table Company to be delinked.
    * $_REQUEST['personId'] - (optional) primary key to row in DB table Person to be delinked.
    
    Typically will be called with a locationId and one of companyId/personId. It's legal to pass more than one of companyId/personId. 
    
    If no linkage existed before this was called, then nothing will happen in the DB, but that is still considered success.
    
    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise.
      * 'error': used only if status = 'fail', reports what went wrong.
      
*/
require_once '../inc/config.php';
require_once '../inc/access.php';
require_once '../inc/perms.php';
// ADDED by George 2020-08-17, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

// confirm that the logged-in user has PERM_LOCATION permission, fail and log error if not.
// ADDED by George 2020-08-17. Check for permissions.
if (!checkPerm($userPermissions, 'PERM_LOCATION', PERMLEVEL_RWA)) { 
    $errorId = '637332725848819923';
    $logger->error2($errorId, "The logged-in user doesn't have permissions for delink location action.");
    $data["error"] =  "You don't have permissions for this action.";  // Message for end user
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
}

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'locationId');
$v->rule('optional', ['companyId', 'personId']);
$v->rule('integer', ['locationId', 'companyId', 'personId']);
$v->rule('min', ['locationId', 'companyId', 'personId'], 1);


if(!$v->validate()){   // if any error in validation (fields => rules + manually added errors) the validator generates
                        // the return structure with 'fail' status, returns the JSON to caller and exits

    $logger->error2('1574792428', "Error input parameters ".json_encode($v->errors()));    
    header('Content-Type: application/json');    
    echo $v->getErrorJson();    
    die();
}

$locationId = $_REQUEST['locationId'];  // we already know this is set

$companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;


// Added 2020-08-17 George: check to make sure it has exactly one of the above two inputs.
$numPrimaryInputs = ($companyId ? 1 : 0) + ($personId ? 1 : 0);
if ($numPrimaryInputs == 0) {
    $errorId = '637332743968201623';
    $logger->error2($errorId, "Must have one of companyId, personId; none of these were present in input.");
    $data["error"] =  "Must have one of companyId, personId; none of these were present in input.";
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
} else if ($numPrimaryInputs > 1) {
    $errorId = '637332744770806213';
    $error = trim( "Must have exactly one of companyId, personId; input gave: " .
            ($companyId ? 'companyId ' : '') . ($personId ? 'personId ' : '') );
    $logger->error2($errorId, $error);
    $data["error"] =  $error;
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
}
// >>>00006 JM 2019-11-26: There don't seem to be any class methods to do the following, so I've put the 
//  DB code directly in line here. Obviously, it would make sense to get this into a class.

$db = DB::getInstance();

// Company Location
if ($companyId) {
    if (!Company::validate($companyId)) {
        $errorId = '637332749264467617';
        $logger->error2($errorId, "The provided companyId ". $companyId ." does not correspond to an existing DB row in company table.");
        $data["error"] =  "CompanyId is not valid. Please check the input!"; // Message for end user
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    // check for database Integrity issues.
    $query = " SELECT companyLocationId FROM " . DB__NEW_DATABASE . ".companyLocation WHERE companyId = $companyId AND locationId = $locationId; ";
    $result = $db->query($query);

    if (!$result) {
        $error = "Database error.";
        $logger->errorDb('637333459594264003', $error, $db);
        $data['error'] = "ajax/delinklocation.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 

    if ($result->num_rows == 0) {
        $logger->warn2('637368975695584354', "Nothing to delink. companyId = $companyId and locationId = $locationId");
        $data['status'] = 'success';
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
    
    $row = $result->fetch_assoc();
    // Issues with third argument, are Logged in the function: not a number, zero or is negative.
    $integrityTest = canDelete('companyLocation', 'companyLocationId', $row['companyLocationId']);

    // if True, No reference to the primary key of this row is found in the database.
    if ($integrityTest == true) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".companyLocation WHERE companyId = $companyId AND locationId = $locationId;";

        $result = $db->query($query);

        if (!$result) {
            $error = "Delinking company from location failed.";
            $logger->errorDb('1574793267', $error, $db);
            $data['error'] = "ajax/delinklocation.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        } else {
            $logger->info2('637368975695584355', "Ran query: [$query] - ".$db->affected_rows ." row(s) deleted!");
        }
    } else {
        // At least one reference to this row exists in the database, violation of database integrity.
        $logger->warn2('637334414490862316', "Delinking company from location, not possible. At least one reference to this row exists in the database, violation of database integrity.");
        $data['error'] = "Delinking company from location, not possible.";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
}
    
// Person Location
if ($personId) {
    if (!Person::validate($personId)) {
        $errorId = '637332754230888203';
        $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB row in person table.");
        $data["error"] =  "PersonId is not valid. Please check the input!"; // Message for end user
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    // check for database Integrity issues.
    $query = " SELECT personLocationId FROM " . DB__NEW_DATABASE . ".personLocation WHERE personId = $personId AND locationId = $locationId; ";
    $result = $db->query($query);

    if (!$result) {
        $error = "Database error.";
        $logger->errorDb('637333547944410494', $error, $db);
        $data['error'] = "ajax/delinklocation.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    } 
    if ($result->num_rows == 0) {
        $logger->warn2('637368977128608327', "Nothing to delink. personId = $personId and locationId = $locationId");
        $data['status'] = 'success';
        header('Content-Type: application/json');
        echo json_encode($data);
        die();        
    }

    $row = $result->fetch_assoc();
    
    // Issues with third argument, are Logged in the function: not a number, zero or is negative.
    $integrityTest = canDelete('personLocation', 'personLocationId', $row['personLocationId']);

    // if True, No reference to the primary key of this row is found in the database.
    if ($integrityTest == true) { 

        $query = "DELETE FROM " . DB__NEW_DATABASE . ".personLocation WHERE personId = $personId AND locationId = $locationId;";

        $result = $db->query($query);

        if (!$result) {
            $error = "Delinking person from location failed.";
            $logger->errorDb('1574793278', $error, $db);
            $data['error'] = "ajax/delinklocation.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        } else {
            $logger->info2('637368975695584355', "Ran query: [$query] - ".$db->affected_rows ." row(s) deleted!");
        }
    } else {
        // At least one reference to this row exists in the database, violation of database integrity.
        $logger->warn2('637334415154679681', "Delinking person from location, not possible. At least one reference to this row exists in the database, violation of database integrity.");
        $data['error'] = "Delinking person from location, not possible.";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
}


if (!$data['error']) {
    $data['status'] = 'success';
}
header('Content-Type: application/json');
echo json_encode($data);
?>
