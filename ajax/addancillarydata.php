<?php 
/* ajax/addancillarydata.php
    
    Used by job.php (and eventually by others) to add ancillary data.
    
    PRIMARY INPUTs
    * $_REQUEST['underlyingTable'] - name of table to which this ancillaryDataType applies; e.g. if this is 'job' we are editing DB table jobAncillaryData 
    * $_REQUEST['rowId'] - primary key to relevant row in underlyingTable 
    * $_REQUEST['ancillaryDataTypeId'] - primary key in the relevant ancillary data type table 
                E.g. if $_REQUEST['underlyingTable'] == 'job', then this is a jobAncillaryDataTypeId.
    * $_REQUEST['val'] - the new value.
    
    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      
    We rely on function $ancillaryData->putData to do most of the input validation.
    
*/

require_once '../inc/config.php';
require_once '../inc/access.php';

$v=new Validator2($_REQUEST);

$v->rule('required', ['underlyingTable', 'rowId', 'ancillaryDataTypeId', 'val']);
$v->rule('integer', ['rowId', 'ancillaryDataTypeId']);
// BEGIN ADDED 2020-04-07 JM
$v->rule('min', ['rowId', 'ancillaryDataTypeId'], 1);
// END ADDED 2020-04-07 JM

if(!$v->validate()){
    $logger->error2('1572336673', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

// It's OK that we don't validate that $rowId is a valid identifier in the underlying table, 
// because $ancillaryData->putData will catch any such problem. 
// Similarly for ancillaryDataTypeId being meaningful, and val making sense in this context. 

$data = array(); 
$data['status']='fail';
$data['error'] = '';

// BEGIN CODE RESTORED 2020-04-07 JM
// This code was incorrectly commmented out in November 2019 and subsequently removed.
// The restored version is a bit simplified because of the error checking above.
// Restoring this should fix http://bt.dev2.ssseng.com/view.php?id=108 (Ancillary Data is experiencing an Ajax error)
$underlyingTable = trim($_REQUEST['underlyingTable']);
$rowId = intval($_REQUEST['rowId']);
$ancillaryDataTypeId = intval($_REQUEST['ancillaryDataTypeId']);
$val = trim($_REQUEST['val']);
// END CODE RESTORED 2020-04-07 JM

$ancillaryData = AncillaryData::load($underlyingTable);
if (!$ancillaryData) {
    $data['error'] .= 'addancillarydata.php cannot build AncillaryData object;';
}

if (!$data['error']) {
    list($success, $error) = $ancillaryData->putData($rowId, $ancillaryDataTypeId, $val);
    if ($success) {
        $data['status'] = 'success';
    } else {
        $data['error'] .= $error;
    }
}
header('Content-Type: application/json');
echo json_encode($data);
?>