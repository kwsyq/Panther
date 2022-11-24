<?php 
/* _admin/ajax/modifyancillarydatatype.php
    
    Used by _admin/ancillarydata/index.php to modify an ancillary data type.
    
    PRIMARY INPUTs
    * $_REQUEST['underlyingTable'] - name of table to which this ancillaryDataType applies; e.g. if this is 'job' we are editing DB table jobAncillaryDataType 
    * $_REQUEST['ancillaryDataTypeId'] - primary key in the table we are editing. E.g. if $_REQUEST['underlyingTable'] == 'job', then this is a jobAncillaryDataTypeId
    
    ADDITIONAL INPUTs
    All of the following are optional and indicate columns we wish to update:
    * $_REQUEST['internalTypeName'] - string
    * $_REQUEST['friendlyTypeName'] - string
    * $_REQUEST['helpText'] - string
    * $_REQUEST['singleValued'] -  'true' or 'false' (Here and following, we treat anything other than 'true' as 'false')
    * $_REQUEST['searchable'] -  'true' or 'false'
    * $_REQUEST['deactivated'] -  'true' or 'false' (though we never reactivate a row, so we will presumably never see 'true')

    Returns JSON for an associative array with the following members:
      * 'status': 'success' on success, status='fail' otherwise. 
      * 'error': used only if status = 'fail', reports what went wrong.
      
    // >>>00002: Cristi will probably want to revisit validation of inputs, I've done something quick & ad hoc for the moment - JM
    
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$changedDB = false;

$underlyingTable = isset($_REQUEST['underlyingTable']) ? $_REQUEST['underlyingTable'] : '';
$id = isset($_REQUEST['ancillaryDataTypeId']) ? $_REQUEST['ancillaryDataTypeId'] : '';
if (!$underlyingTable) {
    $data['error'] .= 'modifyancillarydatatype.php requires name of underlying table';
}
if (!$id) {
    if ($data['error']) {
        $data['error'] .= '; ';
    }
    $data['error'] .= "modifyancillarydatatype.php modifying {$underlyingTable}AncillaryDataType requires input 'ancillaryDataTypeId'";
}
if ($underlyingTable && $id) {
    $ancillaryData = AncillaryData::load($underlyingTable);
    if (!$ancillaryData) {
        $data['error'] .= 'modifyancillarydatatype.php cannot build AncillaryData object;'; 
    } else {
        if (array_key_exists('internalTypeName', $_REQUEST)) {
            $internalTypeName = $_REQUEST['internalTypeName'];
            list($success, $error) = $ancillaryData->validateInternalTypeName($id, $internalTypeName);
            if (!$success) {
                $data['error'] .= $error;
            }
        }
        if (array_key_exists('friendlyTypeName', $_REQUEST)) {
            $friendlyTypeName = $_REQUEST['friendlyTypeName'];
            list($success, $error) = $ancillaryData->validateFriendlyTypeName($id, $friendlyTypeName);
            if (!$success) {
                if ($data['error']) {
                    $data['error'] .= '; ';
                }
                $data['error'] .= $error;
            }
        }
        if (array_key_exists('helpText', $_REQUEST)) {
            $helpText = $_REQUEST['helpText'];
            list($success, $error) = $ancillaryData->validateHelpText($id, $helpText);
            if (!$success) {
                if ($data['error']) {
                    $data['error'] .= '; ';
                }
                $data['error'] .= $error;
            }
        }
        if (array_key_exists('singleValued', $_REQUEST)) {
            $singleValued = $_REQUEST['singleValued'] == 'true';
            list($success, $error) = $ancillaryData->validateSingleValued($id, $singleValued);
            if (!$success) {
                if ($data['error']) {
                    $data['error'] .= '; ';
                }
                $data['error'] .= $error;
            }
        }
        if (array_key_exists('searchable', $_REQUEST)) {
            $searchable = $_REQUEST['searchable'] == 'true';
            list($success, $error) = $ancillaryData->validateSearchable($id, $searchable);
            if (!$success) {
                if ($data['error']) {
                    $data['error'] .= '; ';
                }
                $data['error'] .= $error;
            }
        }
        if (array_key_exists('deactivated', $_REQUEST)) {
            $deactivated = $_REQUEST['deactivated'] == 'true';
        }
    }
    if (!$data['error']) {
        if (isset($internalTypeName)) {
            list($success, $error) = $ancillaryData->changeInternalTypeName($id, $internalTypeName);
            if ($success) {
                $changedDB = true;
            } else {
                $data['error'] .= $error;
            }
        }
    }
    if (!$data['error']) {
        if (isset($friendlyTypeName)) {
            list($success, $error) = $ancillaryData->changeFriendlyTypeName($id, $friendlyTypeName);
            if ($success) {
                $changedDB = true;
            } else {
                $data['error'] .= $error;
            }
        }
    }
    if (!$data['error']) {
        if (isset($helpText)) {
            list($success, $error) = $ancillaryData->changeHelpText($id, $helpText);
            if ($success) {
                $changedDB = true;
            } else {
                $data['error'] .= $error;
            }
        }
    }
    if (!$data['error']) {
        if (isset($singleValued)) {
            list($success, $error) = $ancillaryData->changeSingleValued($id, $singleValued);
            if ($success) {
                $changedDB = true;
            } else {
                $data['error'] .= $error;
            }
        }
    }
    if (!$data['error']) {
        if (isset($searchable)) {
            list($success, $error) = $ancillaryData->changeSearchable($id, $searchable);
            if ($success) {
                $changedDB = true;
            } else {
                $data['error'] .= $error;
            }
        }
    }
    if (!$data['error']) {
        if (isset($deactivated) && $deactivated) { // can never reactivate
            list($success, $error) = $ancillaryData->deactivateDataType($id);
            if ($success) {
                $changedDB = true;
            } else {
                $data['error'] .= $error;
            }
        }
    }
}
if ($data['error']) { 
    if ($changedDB) {
        // Typically this should only happen on a hard DB error (because we determined everything was valid, and then an UPDATE failed).
        $data['error'] = 'An error occurred AFTER some changes were made in the database. ' . $data['error'] . 
            ' Please close the edit window and check change values carefully; you may want to consult the database administrator.';
    }
} else {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>
