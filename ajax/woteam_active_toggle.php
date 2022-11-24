<?php
/*  ajax/woteam_active_toggle.php (teamId, active)

    INPUT $_REQUEST['teamId']: primary key to DB table Team
    INPUT $_REQUEST['active']: INPUT active should be 0 or 1, and represents the new value for 'active' column. 
        Not really a "toggle": ignores old value of active. Requires that teamId already exists.

    Returns JSON for an associative array with the following members:
        * 'status': "fail" if teamId not valid or on any of a number of other failures; "success" on success.
        * 'active': the new value of whether this team is active (0 or 1).
        * 'linkactive': The Boolean opposite of active (if active=1, linkactive=0, and vice versa). 
*/

include '../inc/config.php';
include '../inc/access.php';

sleep(1);  // Martin comment: this is so the user can see the progress gif

$data = array();
$data['status'] = 'fail';

$teamId= isset($_REQUEST['teamId']) ? intval($_REQUEST['teamId']) : 0;
$active= isset($_REQUEST['active']) ? intval($_REQUEST['active']) : 0;

if (intval($teamId)) {
    $db = DB::getInstance();
    
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".team WHERE teamId = " . intval($teamId) . ";";
    $teamId = 0;
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1600970172', 'Hard DB error', $db);
    } else {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $teamId = intval($row['teamId']);
        } else {
            $logger->error2('1600970144', "$teamId appears not to be a valid teamId");
        }        
    }
    
    if (intval($teamId)) {
        $query = "UPDATE " . DB__NEW_DATABASE . ".team SET active = " . intval($active) . " WHERE teamId = " . intval($teamId) . ";";    
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1600970567', 'Hard DB error', $db);
        } else {
            $query = "SELECT * FROM " . DB__NEW_DATABASE . ".team WHERE teamId = " . intval($teamId) . ";";
            $teamId = 0;
            $active = false;
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1600970637', 'Hard DB error', $db);
            } else {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $teamId = intval($row['teamId']);
                    $active = $row['active'];
                }
            }
        }
    }
        
    if (intval($teamId)) {            
        $data['status'] = 'success';
        $linkactive = (intval($active)) ? 0 : 1;
        $data['active'] = intval($active);
        $data['linkActive'] = intval($linkactive);
    }
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>