<?php 
/* ajax/changelinkedlocation.php
    
    Used by location.php to change the location that is linked for one or more jobs/persons/companies
    
    INPUT
    * $_REQUEST['oldLocationId'] - primary key to row in DB table Location for the current linked location.
    * $_REQUEST['newLocationId'] - primary key to row in DB table Location for the desired linked location.
    * $_REQUEST['itemsToRelink'] - a JSON-encoded array of values; each element of the array has a value of one of the following forms:
        * 'job-nnn', where 'nnn' is a jobId
        * 'person-nnn', where 'nnn' is a personId
        * 'company-nnn', where 'nnn' is a companyId
        * NOTE that itemsToRelink can include what we call the "jcb" in location.php, a job, company, or person
          that is NOT LINKED to the oldLocationId, but which (like the others) we want to link to the newLocationId. 
    
    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise.
      * 'error': used only if status = 'fail', reports what went wrong.
      
*/

require_once '../inc/config.php';
require_once '../inc/access.php';

$db = DB::getInstance();

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

// >>>00016 should confirm that the logged-in user has PERM_LOCATION permission, fail and log error if not,

$oldLocationId = isset($_REQUEST['oldLocationId']) ? intval($_REQUEST['oldLocationId']) : 0;
$newLocationId = isset($_REQUEST['newLocationId']) ? intval($_REQUEST['newLocationId']) : 0;

$oldLocation=new Location($oldLocationId);
$newLocation=new Location($newLocationId);

if ( ! $oldLocation->getLocationId() ) {
    $data['error'] = "changelinkedlocation.php: invalid old locationId $oldLocationId";
} else if ( ! $newLocation->getLocationId() ) {
    $data['error'] = "changelinkedlocation.php: invalid new locationId $newLocationId";
}

if (!$data['error']) {
    if (!isset($_REQUEST['itemsToRelink'])) {
        $data['error'] = "changelinkedlocation.php: missing itemsToRelink.";
    }
}
if (!$data['error']) {
    $itemsToRelink = json_decode($_REQUEST['itemsToRelink']);
    if ($itemsToRelink == null) {
        $data['error'] = "changelinkedlocation.php: invalid itemsToRelink, details are in system log";
        $logger->error2('1574381349', "invalid 'itemsToRelink' input, should be JSON-encoded array: ". $_REQUEST['itemsToRelink']);
    }
}

if (!$data['error']) {
    $jobIds = Array();
    $personIds = Array();
    $companyIds = Array();
    
    foreach($itemsToRelink AS $item) {
        if (substr($item, 0, 4) == 'job-') {
            $jobIds[] = intval(substr($item, 4));
        } else if (substr($item, 0, 7) == 'person-') {
            $personIds[] = intval(substr($item, 7));
        } else if (substr($item, 0, 8) == 'company-') {
            $companyIds[] = intval(substr($item, 8));
        } else {
            $data['error'] = "changelinkedlocation.php: invalid item to relink: '$item', more details are in system log";
            $logger->error2('1574381398', "invalid item '$item' in 'itemsToRelink' input: ". $_REQUEST['itemsToRelink']);
            break;
        }
    }
}

if (!$data['error']) {
    foreach($jobIds AS $jobId) {
        if ($jobId == 0) {
            $data['error'] = "changelinkedlocation.php: at least one 0 or non-numeric jobId item to relink, more details are in system log";
            $logger->error2('1574381410', "invalid or 0 jobId in 'itemsToRelink' input: ". $_REQUEST['itemsToRelink']);
            break;
        }
    }
}

if (!$data['error']) {
    foreach($personIds AS $personId) {
        if ($personId == 0) {
            $data['error'] = "changelinkedlocation.php: at least one 0 or non-numeric personId item to relink, more details are in system log";
            $logger->error2('1574381410', "invalid or 0 personId in 'itemsToRelink' input: ". $_REQUEST['itemsToRelink']);
            break;
        }
    }
}

if (!$data['error']) {
    foreach($companyIds AS $companyId) {
        if ($companyId == 0) {
            $data['error'] = "changelinkedlocation.php: at least one 0 or non-numeric companyId item to relink, more details are in system log";
            $logger->error2('1574381410', "invalid or 0 companyId in 'itemsToRelink' input: ". $_REQUEST['itemsToRelink']);
            break;
        }
    }
}

// JM 2019-11-25: In the following, I had originally tried putting the whole transaction in one big string, separated by semicolons, then
// making a single call to $db->query. In theory, that should be fine, but it was failing, so I am now experimenting with a series of separate calls.
// No idea why it wouldn't allow one big query, but seems to be working OK once I took it apart.
if (!$data['error']) {
    $query = "START TRANSACTION;";
    $result = $db->query($query);
    if (!$result) {
        $data['error'] = "changelinkedlocation.php: failed to start transaction";
        $logger->errorDb('1574720088', "DB error starting transaction", $db);
    }
}

if (!$data['error']) {    
    if (count($jobIds)) {
        foreach ($jobIds AS $jobId) {
            /* BEGIN REPLACED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".jobLocation (";
            $query .= "jobId, locationId, jobLocationTypeId";
            $query .= ") VALUES (";
            $query .= "$jobId, $newLocationId, ".JOBLOCTYPE_SITE;    
            $query .= ");";
            // END REPLACED JM 2020-05-11
            */    
            // BEGIN REPLACEMENT JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
            $query = "UPDATE " . DB__NEW_DATABASE . ".job ";
            $query .= "SET locationId = $newLocationId ";
            $query .= "WHERE jobId=$jobId;";
            // END REPLACEMENT JM 2020-05-11

            $result = $db->query($query);
            if (!$result) {
                $data['error'] = "changelinkedlocation.php: failed to insert link for jobId $jobId to locationId $newLocationId";
                $logger->errorDb('1574720100', "DB error linking job to location", $db);
                
                $query = "ROLLBACK;";
                $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
                break;
            }
        }
        
        /* BEGIN REMOVED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153: no need to do this now that
        //   locationId is directly in Job table.
        
        if (!$data['error']) {    
            $whereClause = "locationId=$oldLocationId AND jobId IN (";
            foreach ($jobIds AS $i=>$jobId) {
                if ($i > 0) {
                    $whereClause .= ", ";
                }
                $whereClause .= $jobId;
            }
            $whereClause .= ")";
            
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".jobLocation WHERE $whereClause;";
            $result = $db->query($query);
            if (!$result) {
                $data['error'] = "changelinkedlocation.php: failed to delete links for old location IDs while cloning";
                $logger->errorDb('1574720112', "DB error removing links from job to location", $db);
                
                $query = "ROLLBACK;";
                $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
            }
        }
        // END REMOVED JM 2020-05-05
        */
    }
}

if (!$data['error']) {    
    if (count($personIds)) {
        foreach ($personIds AS $personId) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".personLocation (";
            $query .= "personId, locationId";
            $query .= ") VALUES (";
            $query .= "$personId, $newLocationId";    
            $query .= ");";
            $result = $db->query($query);
            if (!$result) {
                $data['error'] = "changelinkedlocation.php: failed to insert link for personId $personId to locationId $newLocationId";
                $logger->errorDb('1574720124', "DB error linking person to location", $db);
                
                $query = "ROLLBACK;";
                $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
                break;
            }
        }
        
        if (!$data['error']) {    
            $whereClause = "locationId=$oldLocationId AND personId IN (";
            foreach ($personIds AS $i=>$personId) {
                if ($i > 0) {
                    $whereClause .= ", ";
                }
                $whereClause .= $personId;
            }
            $whereClause .= ")";
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".personLocation WHERE $whereClause;";
            $result = $db->query($query);
            if (!$result) {
                $data['error'] = "changelinkedlocation.php: failed to delete links for old location IDs while cloning";
                $logger->errorDb('1574720136', "DB error removing links from person to location", $db);
                
                $query = "ROLLBACK;";
                $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
            }
        }
    }
}

if (!$data['error']) {    
    if (count($companyIds)) {
        foreach ($companyIds AS $companyId) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".companyLocation (";
            $query .= "companyId, locationId";
            $query .= ") VALUES (";
            $query .= "$companyId, $newLocationId";    
            $query .= ");";            
            $result = $db->query($query);
            if (!$result) {
                $data['error'] = "changelinkedlocation.php: failed to insert link for companyId $jobId to locationId $newLocationId";
                $logger->errorDb('1574720148', "DB error linking company to location", $db);
                
                $query = "ROLLBACK;";
                $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
                break;
            }
        }
        
        if (!$data['error']) {
            $whereClause = "locationId=$oldLocationId AND companyId IN (";
            foreach ($companyIds AS $i=>$companyId) {
                if ($i > 0) {
                    $whereClause .= ", ";
                }
                $whereClause .= $companyId;
            }
            $whereClause .= ")";        
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".companyLocation WHERE $whereClause;";
            $result = $db->query($query);
            if (!$result) {
                $data['error'] = "changelinkedlocation.php: failed to delete links for old location IDs while cloning";
                $logger->errorDb('1574720160', "DB error removing links from company to location", $db);
                
                $query = "ROLLBACK;";
                $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
            }
        }
    }
    
    $query = "COMMIT;";    
    $result = $db->query($query);
    if (!$result) {
        $data['error'] = "changelinkedlocation.php: DB error in COMMIT";
        $logger->errorDb('1574382856', "DB error in COMMIT", $db);
        $query = "ROLLBACK;";
        $db->query($query); // don't even bother checking the result, nothing we can do if it fails.
    }
}

if (!$data['error']) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>