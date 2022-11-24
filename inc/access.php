<?php 

/* inc/access.php

   EXECUTIVE SUMMARY
   Handles some redirects that depend on who is logged on so can't be handled by .htaccess.

*/
// If $customer as already set by inc/config.php doesn't indicate an actual supported customer 
// (corresponding to a row in DB table 'customer') then log the error and exit.
// As of 2020, there is exactly one customer: SSS itself.
if($customer->getCustomerId() < 1) {
	// ... redirect to the error page, show message there.
	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
	die();
}

// If there is no User object $user, or it doesn't represent some actual user... 
// This same test is in inc/perms.php.
if ((!$user) || (!intval($user->getUserId()))) {
    // ... take them back to the login page, and remember where they want to go.
    
    // We don't want to log this: it is normal behavior when someone uses a saved URL and needs to log 
    // in to the system before accessing the page in question.
    
	header("Location: /login?ret=" . rawurlencode($_SERVER['REQUEST_URI']));
	die();
}

/* BEGIN REMOVED 2020-01-23 JM: /contract.php and /invoice.php haven't included this in some time, they use 
   the usual "permissions" mechanism.
   
// If they are trying to look at the contract or invoice page...
if (($_SERVER['SCRIPT_NAME'] == '/contract.php') or ($_SERVER['SCRIPT_NAME'] == '/invoice.php')) {
	// ... and they aren't one of the few admins identified in config.php ... 
	if (!(in_array($user->getUserId(), $adminids))) {
	    // ... take them to the front page
		header ("Location: /panther");	
	}
}
// END REMOVED 2020-01-23 JM
*/

?>