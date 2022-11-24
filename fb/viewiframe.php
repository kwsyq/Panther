<?php
/*  fb/viewiframe.php

    EXECUTIVE SUMMARY: Despite the generic name, specific to creditRecords. 
    Displays a creditRecord file in either an HTML OBJECT or IMG element, as appropriate to the file type.
    This wraps fb/viewframefetch.php

    PRIMARY INPUT: $_REQUEST['creditRecordId'], which identifies a row in the creditRecord DB table.
*/

include '../inc/config.php';
include '../inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_REVENUE', PERMLEVEL_ADMIN);
if (!$checkPerm) {
	die();
}

$creditRecordId = isset($_REQUEST['creditRecordId']) ? intval($_REQUEST['creditRecordId']) : 0;
$cr = new CreditRecord($creditRecordId);

if (intval($cr->getCreditRecordId())) {
    $ext = pathinfo($cr->getFileName(), PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    
    if ($ext == 'pdf') {
        ?>
        <object data="viewframefetch.php?creditRecordId=<?php echo intval($cr->getCreditRecordId()); ?>" type="pdf" width="100%" height="100%">
        <p>Can't view so</p><?php /* will be visible only if we can't build object. >>>00016 looks like there should be something more here. */ ?>
        </object>
        <?php		
    } else if (($ext == 'jpeg') || ($ext == 'jpg') || ($ext == 'png')) {        
        echo '<img src="viewframefetch.php?creditRecordId=' . intval($cr->getCreditRecordId()) . '">';
    } else {
        // >>>00002 unsupported extension, should log
    }
}

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
            <object data="viewframefetch.php" type="pdf" width="100%" height="100%">
            <p>Can't view so</p>
            </object>	
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

?>