<?php 
/* ajax/clonelocation.php
    
    Used by location.php to clone the location & get back the new locationId.
    
    INPUT
    * $_REQUEST['locationId'] - primary key to row in DB table Location to be cloned. 
    
    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise.
      * 'locationId': the new locationId on success, 0 otherwise.
      * 'error': used only if status = 'fail', reports what went wrong.
      
*/

require_once '../inc/config.php';
require_once '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['locationId'] = 0;
$data['error'] = '';

// >>>00016 should confirm that the logged-in user has PERM_LOCATION permission, fail and log error if not,

$locationId = isset($_REQUEST['locationId']) ? intval($_REQUEST['locationId']) : 0;
$location = new Location($locationId);

if ( ! $location->getLocationId() ) {
    $data['error'] = "clonelocation.php: invalid locationId $locationId";
}

if (!$data['error']) {
    $newLocationId = $location->cloneLocation();
    if ($newLocationId) {
        $data['locationId'] = $newLocationId;
        $data['status'] = 'success';
    } else {
        $data['error'] = "clonelocation.php: could not clone location $locationId";
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>
