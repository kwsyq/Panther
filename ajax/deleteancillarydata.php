<?php 
/* ajax/deleteancillarydata.php
    
    Used by job.php (an eventually by others) to delete ancillary data.
    
    PRIMARY INPUTs
    * $_REQUEST['underlyingTable'] - name of table to which this ancillaryData applies; e.g. if this is 'job' we are editing DB table jobAncillaryData 
    * $_REQUEST['datumId'] - primary key in tha relevant ancillary data table, for example a jobAncillaryDataId in DB table jobAncillaryData
    
    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      
    // >>>00002, >>>00016: we may want more validation of inputs here.
*/

require_once '../inc/config.php';
require_once '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$underlyingTable = isset($_REQUEST['underlyingTable']) ? $_REQUEST['underlyingTable'] : '';
if (!$underlyingTable) {
    $data['error'] .= 'deleteancillarydata.php requires name of underlying table';
}

$datumId = isset($_REQUEST['datumId']) ? intval($_REQUEST['datumId']) : '';
if (!$datumId) {
    if ($data['error']) { $data['error'] .= ', ';}
    $data['error'] .= 'deleteancillarydata.php requires datumId (primary key in ' .$underlyingTable. 'AncillaryData)';
}

if (!$data['error']) {
    $ancillaryData = AncillaryData::load($underlyingTable);
    if (!$ancillaryData) {
        $data['error'] .= 'deleteancillarydata.php cannot build AncillaryData object;';
    }
}

if (!$data['error']) {
    list($success, $error) = $ancillaryData->deleteData($datumId);
    if ($success) {
        $data['status'] = 'success';
    } else {
        $data['error'] .= $error;
    }
}
header('Content-Type: application/json');
echo json_encode($data);
?>
