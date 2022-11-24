<?php 
/* _admin/ajax/addancillarydatatype.php
    
    Used by _admin/ancillarydata/index.php to add an ancillary data type.
    
    Mandatory INPUTs
    * $_REQUEST['underlyingTable'] - name of table to which this ancillaryDataType applies; e.g. if this is 'job' we are editing DB table jobAncillaryDataType 
    * $_REQUEST['internalTypeName'] - string
    * $_REQUEST['friendlyTypeName'] - string
    
    Optional INPUTs
    The following inputs are optional:
    * $_REQUEST['ancillaryDataTypeId'] - primary key in the table we are editing. E.g. if $_REQUEST['underlyingTable'] == 'job', then this is a jobAncillaryDataTypeId
                                         Default if missing is for the system to generate an ID
    * $_REQUEST['helpText'] - string
    * $_REQUEST['singleValued'] -  'true' or 'false' (Here and following, we treat anything other than 'true' as 'false')
    * $_REQUEST['searchable'] -  'true' or 'false'

    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      
    We rely on function $ancillaryData->addDataType to do most of the input validation.
    
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$underlyingTable = isset($_REQUEST['underlyingTable']) ? $_REQUEST['underlyingTable'] : '';
if (!$underlyingTable) {
    $data['error'] .= 'addancillarydatatype.php requires name of underlying table';
}

if (!$data['error']) {
    $ancillaryData = AncillaryData::load($underlyingTable);
    if (!$ancillaryData) {
        $data['error'] .= 'addancillarydatatype.php cannot build AncillaryData object;';
    }
}
if (!$data['error']) {
    list($success, $error) = $ancillaryData->addDataType($_REQUEST);
    if ($success) {
        $data['status'] = 'success';
    } else {
        $data['error'] .= $error;
    }
}
header('Content-Type: application/json');
echo json_encode($data);
?>
