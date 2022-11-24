<?php
/* _admin/ajax/weekotamount.php

    EXECUTIVE SUMMARY: Update weekOT for indicated row in DB table customerPersonPayWeekInfoInfo as specified. 

    INPUT $_REQUEST['id']. This is in the form foo_id, where foo may not contain the character "_", must have a nonzero intval, 
                            but is otherwise ignored, and id is a customerPersonPayWeekInfoId, primary key for DB table customerPersonPayWeekInfo.
    INPUT $_REQUEST['value']. New weekOT value for the indicated row in DB table customerPersonPayWeekInfoInfo. Relevant column data type is TINYINT(4). 

    Returns JSON for an associative array with the following members:
        'status': 'success' on success, status='fail' otherwise. 
          >>>00006: As of 2019-05-13, 'success' really only means that we can extract the two nonzero intvals from $_REQUEST['id'] . 
          For example, if there is no relevant row to update in DB table customerPersonPayWeekInfo, or if we can't get a valid 
          numeric value from $_REQUEST['value'], then nothing is changed in the DB, but we call it "success".
          
   >>>00037: a lot of common code with _admin/ajax/dayotamount.php, probably should share most code.         

*/
include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';

$parts = explode("_", $id);

$db = DB::getInstance();

if (is_array($parts)) {
	if (count($parts) == 2) {
		if (intval($parts[0])) {
		    if (intval($parts[1])) {
				if (is_numeric($value)) {
					$query = " update " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo set ";
					$query .= " weekOT = " . intval($value) . " ";
					$query .= " where customerPersonPayWeekInfoId = " . intval($parts[1]) . " ";

					$db->query($query); // >>>00002 ignores failure on DB query!
					$data['status'] = 'success';
				} else {
					$data['status'] = 'success';
				}
			}
		}
	}
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>
