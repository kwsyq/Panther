<?php

/*  _admin/ajax/iratype.php

    EXECUTIVE SUMMARY: Set a new value for the 'iraType' column in DB table CustomerPersonPayPeriodInfo. 

    INPUT $_REQUEST['id']:  should be of the form type_customerPersonId_customerPersonPayPeriodInfoId, where
        type is the literal 'type' (>>>00002, >>>00016: although as of 2019-05-13 we don't validate that),
        customerPersonId is a primary key to DB table CustomerPerson, and customerPersonPayPeriodInfoId
        is a primary key to DB table CustomerPersonPayPeriodInfo.
        >>>00016 JM: as of 2019-05 we don't validate the customerPersonId beyond making sure it is nonzero numeric. 
            It is otherwise ignored (has no effect on the update or the return), and we do nothing to make sure it matches the row customerPersonPayPeriodInfoId.
        >>>00016 JM: as of 2019-05 we don't validate the customerPersonPayPeriodInfoId beyond making sure it is nonzero numeric.
            If it is not a valid ID for a row, then nothing will be updated.
    INPUT $_REQUEST['value']: the new value for column 'ira' 
        >>>00016 JM: as of 2019-05 the only validation we do on this before attempting an update is to make sure it is nonzero numeric.
            Probably should have some range limitation (at least that it's positive!) 

    Returns JSON for an associative array with the following members:
        * 'status': 'success' or 'fail'; >>>00006 the notion of success is perhaps a bit odd: besides a successful update, 
          it covers all cases where the customerPersonId and customerPersonPayPeriodInfoId are nonzero numeric, regardless 
          of whether either is a valid ID or even whether $_REQUEST['value'] is numeric, let alone having the update succeed.
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
    if (count($parts) == 3) {
        if (intval($parts[1])) {
            if (intval($parts[2])) {
                if (intval($value)) {
                    $query = " update " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo set ";
                    $query .= " iraType = " . intval($value) . " ";
                    $query .= " where customerPersonPayPeriodInfoId = " . intval($parts[2]) . " ";

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
