<?php
/*  inc/perms.php

    EXECUTIVE SUMMARY: bails out on some illegitimate situations,
    otherwise calculates $userPermissions
*/
// If there is no User object $user, or it doesn't represent some actual user...
// This same test is in inc/access.php.
if ((!$user) || (!intval($user->getUserId()))) {
    // ... take them back to the login page, and remember where they want to go.

    // We don't want to log this: it is normal behavior when someone uses a saved URL and needs to log
    // in to the system before accessing the page in question.
	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
	die();
}
// If there is no script name...
// >>>00014 how could that happen? => [Cristi] probably no, but if happen it is logged
$sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$sn = trim($sn);
if (!strlen($sn)) {
    // ... take the back to the login page, and remember where they want to go.
	$logger->warn2('1569343172', "Script name absent!");
	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
	die();
}
// Build an array of all known permissions.
// $definedPermissions will be content of DB table PERMISSIONS as an associative array
//   of associative arrays. Top level indexes are permission names such as "PERM_PERSON"
//   or "PERM_INVOICE". Second level associative arrays each give the canonical representation
//   of the appropriate row from DB table WorkOrderbStatus (column names as indexes).
$db = DB::getInstance();
$query = " select * from " . DB__NEW_DATABASE . ".permission  ";
$definedPermissions = array();
$result = $db->query($query); // CP - 2020-11-30
if ($result) {
	if ($result->num_rows > 0){
		while ($row = $result->fetch_assoc()){
			$definedPermissions[$row['permissionIdName']] = $row;
		}
	} else {
        $logger->info2('1569343371', "Permissions table is empty!");
    }
} else { // [Cristi] - what if a DB error?!
	$logger->fatal2('1569343370', "Error query on permissions table!");
	die(); // [Cristi] >>>00032 - die is not a good choice; we should create a page with and error message regarding the missing or corrupt permissions table
} // [Cristi] - END

/* BEGIN removed by Martin some time before 2019
//$permMap = array();
//$permMap['/contract.php'] = 'PERM_CONTRACT';

//if (!isset($permMap[$sn])){
//	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
//}

//$permConst = $permMap[$sn];

//if (!isset($definedPermissions[$permConst])){
//	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
//}

//$perm = $definedPermissions[$permConst];
END removed by Martin some time before 2019
*/

// Get permission string for the current user
$pers = new Person($user->getUserId());
$permString = $pers->getPermissionString();

if (!(strlen($permString) == 64)){
	$logger->warn2('1569343454', '$permString is the wrong length: ['.strlen($permString).", must be 64]. user [id - ".$user->getUserId()."]".$user->getFormattedName()."!");
	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
	die();
}
/* BEGIN removed by Martin some time before 2019
//$permBit = substr($permString, $perm['permissionId'], 1);

//if (!(intval($permBit) && intval($permBit <= 9))){
//	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
//}
END removed by Martin some time before 2019
*/

// $userPermissions will be an associative array indexed by permission names such
//   as "PERM_PERSON" or "PERM_INVOICE". The value in each case will be a single digit
//   expressing a permission level (defined in inc/config.php, set for this user in
//   DB table Permissions) such as PERMLEVEL_ADMIN=1 or PERMLEVEL_NONE=9.
$userPermissions = array();
foreach ($definedPermissions as $dkey => $definedPermission) {
	$userPermissions[$definedPermission['permissionIdName']] = substr($permString, $definedPermission['permissionId'], 1);
}

?>