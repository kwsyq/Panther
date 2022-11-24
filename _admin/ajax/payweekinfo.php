<?php
/*  _admin/ajax/payweekinfo.php

    EXECUTIVE SUMMARY: Sets a new value for a caller-specified column ('payPeriod' or 'rate') in DB table customerPersonPayWeekInfo.

    INPUT REQUEST['id']: should be of the form column_customerPersonPayWeekInfoId, where:
        * column is a column-name in DB table customerPersonPayPeriodInfo (either 'dayHours', 'dayOT', or 'weekOT'; anything else fails).
        * customerPersonPayWeekInfoId is a primary key to customerPersonPayWeekInfo. Not validated; update will simply fail if this
          is not valid.
    INPUT $_REQUEST['value']: the new value for that row and column. Interpreted as integer; >>>00016 probably could use more validation.
    
    If DB table customerPersonPayWeekInfo has the specified row & column, it should succeed in performing the update.
    
    Returns JSON for an associative array with the following members:
        * 'status': 'success' if the update succeeds, otherwise 'fail'.
*/

include '../../inc/config.php';
include '../../inc/access.php';

$data = array();
$data['status'] = 'fail';

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$value = isset($_REQUEST['value']) ? $_REQUEST['value'] : '';

$parts = explode("_", $id);

$ok = array('dayHours', 'dayOT', 'weekOT');

$db = DB::getInstance();

if (is_array($parts)) {	
	if (count($parts) == 2) {
		if (in_array($parts[0], $ok)) {			
			$query = " update " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo set ";
			$query .= " " . $db->real_escape_string($parts[0])  . " = " . intval($value) .  " ";			
			$query .= " where customerPersonPayWeekInfoId = " . intval($parts[1]);			

			$db->query($query); // >>>00002 ignores failure on DB query!
			
			$query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo ";
			$query .= " where customerPersonPayWeekInfoId = " . intval($parts[1]);	

			if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {						
					$row = $result->fetch_assoc();
					if (intval($value) == intval($row[$parts[0]])) {						
						$data['status'] = 'success';						
					}			
				}			
			} // >>>00002 ignores failure on DB query!			
		}	
	}	
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>