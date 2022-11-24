<?php
/* inc/config.php

   EXECUTIVE SUMMARY
   The first thing (or very close) on most pages and even in most batch jobs.
   Anything configurable about the system should be in this file.
   Also defines many constants such as permission levels, various statuses that
    have associated business rules, etc.
   Also includes inc/functions.php. 
    
   * Public functions
   ** is_command_line_interface()
   ** auto_loader($class)
   * Deliberately created global variables
   ** $act
   ** $customer
   ** $user
   ** $hold_status_titles_and_grace
   ** $privatePageTesters
   ** $serv_load_var_type
   ** $payperiod
   ** $workweek
   ** $privTypes
   ** $smsNumbers
   ** $credRecTypes
   ** $iraTypes
   ** $billBlockTypes
   ** $patchcall_options
   ** $contract_return_instructions
   ** $valid_ajax_origin_domains
   ** $timeManagerEmails
*/

require_once dirname(__FILE__).'/determine_environment.php'; // Test for "live system" (vs. engineering, test, etc.)

// The main file of the Logger library customised for the project
require_once dirname(__FILE__).'/../log4php/Logger2.php';

// The config file, contains the definition of the logging system 
require_once dirname(__FILE__).'/../log4php/resources/config.log4php.php';

// The main files with some general error codes
require_once 'classes/ErrorCodes.php';

define ("VERSION", "2020-4"); // SHOULD BE UPDATED EACH TIME WE START ON A NEW VERSION. 

// the variable $log4phpconfig is defined in the configuration file and is and array with all the info. 
// using this variable definition allow to avoid the use of eval function
Logger2::configure($log4phpconfig);

$logger = Logger2::getLogger("main");

// report all errors: if we are getting errors, even in production, we want to find them and fix them, not hide them.
ini_set('display_errors',1);
error_reporting(-1);

// Sometimes cron jobs or other jobs that run from the command line need to know
//  what would be the appropriate domain on this server.
// >>>00003, >>>00004 The following is totally ad hoc, and we will certainly want to revisit it.
//  Among other things, there are (as of 2019) several places where code explicitly
//  references the production domain, meaning that even when run in a dev or test
//  environment, this will refer to the production system. I (JM) have made sure
//  that each of these uses PRODUCTION_DOMAIN so that they can be easily found and
//  the intent can be verified.
// Similarly for DEV_DOMAIN, but it there are very few appearances in the code.

$currentEnvironment=environment(); // variable defined in order to avoid the call of a function for a lot of times

define ("PRODUCTION_DOMAIN", 'ssseng.com');
if ($currentEnvironment == ENVIRONMENT_DEV_MARTINS_SERVER) {
    define ("DEV_DOMAIN", 'sssnew.com');
} else if ($currentEnvironment == ENVIRONMENT_DEV2) {    
    define ("DEV_DOMAIN", 'dev2.ssseng.com');
} else if ($currentEnvironment == ENVIRONMENT_QA) {    
    define ("DEV_DOMAIN", 'qa.ssseng.com');
} else if ($currentEnvironment == ENVIRONMENT_RC) {    
    define ("DEV_DOMAIN", 'rc.ssseng.com');
} else if ($currentEnvironment == ENVIRONMENT_RC2) {    
    define ("DEV_DOMAIN", 'rc2.ssseng.com');
}
if ( php_sapi_name() === 'cli' ){
    // We are running from the command line
    if ($currentEnvironment == ENVIRONMENT_PRODUCTION) {
        define ("DEFAULT_DOMAIN", PRODUCTION_DOMAIN);
    } else {        
        define ("DEFAULT_DOMAIN", DEV_DOMAIN);
    }
}
define ("LIVE_SYSTEM", $currentEnvironment == ENVIRONMENT_PRODUCTION);

session_set_cookie_params(86400); // seconds in a day

function is_command_line_interface() {
    return (php_sapi_name() === 'cli');
}

if (is_command_line_interface()){
    // command-line interface
    define("HTTP_HOST", '');
	define("REQUEST_SCHEME", '');
} else {
    // >>> BEGIN ADDED 2019-06-24 JM; reworked 2019-10-08 JM after discussion,
    // mainly with Michael Hasse, about http://bt.dev2.ssseng.com/view.php?id=31
    $hasHttpHost = array_key_exists('HTTP_HOST', $_SERVER) && is_string($_SERVER['HTTP_HOST']) && strlen($_SERVER['HTTP_HOST']);
    if (!$hasHttpHost) {
        $watchingBadTraffic = false; // set this true if we want to report on attempts to access without HTTP_HOST.
        if ($watchingBadTraffic) {
            $str = 'Accessed without HTTP_HOST. $_SERVER=Array(';
            $first = true;
            foreach($_SERVER as $k=>$v) {
                if ($first) {
                    $first = false;
                } else {
                    $str .= ', ';
                }
                $str .= "'$k'=>'$v'";
            }
            $first = true;
            $str .= '); $_REQUEST=Array(';
            foreach($_REQUEST as $k=>$v) {
                if ($first) {
                    $first = false;
                } else {
                    $str .= ', ';
                }
                $str .= "'$k'=>'$v'";
            }
            $str .= ')';
            $logger->info2("1569342467", $str);
        }
        die(); // We decided in 2019-10-08 meeting that there is never a good reason to let this proceed.
    }
    // >>> END ADDED 2019-06-24 JM
    
	if ($hasHttpHost && substr($_SERVER['HTTP_HOST'], 0, 4) == 'www.') {
	    // We don't want to use the 'www' prefix, strip it and redirect.
		$scheme = 'http://';
		if (isset($_SERVER['HTTPS'])){
			if (strtolower($_SERVER['HTTPS']) == 'on'){
				$scheme = 'https://';
			}
		}
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: ' . $scheme . substr($_SERVER['HTTP_HOST'], 4) . $_SERVER['REQUEST_URI']);
		die();
	}

	$uri = $_SERVER['REQUEST_URI'];

	// JM 2019-01-31: If I understand the following correctly, it is so that we can use a query string in the
	//  URL even if we use POST method. If we are using GET method, then this is redundant.
	if( ($pos = strpos( $uri, '?' )) !== false ) {
	    parse_str( substr( $uri, $pos + 1 ), $_GET );
		$_REQUEST = array_merge( $_REQUEST, $_GET );
	}
	
	unset($uri); // JM 2019-01-31: I'm virtually certain there was no intention of setting $uri as a global, so I am unsetting it.
    
    //[rio] function is deprecated. get_magic_quotes_gpc always returns FALSE.
    /* commented out entirely, 2020-05-21 JM
    if (version_compare(PHP_VERSION, '5.4', '<')) { //[rio] https://www.php.net/manual/en/migration74.deprecated.php
        if (get_magic_quotes_gpc()) {
            // >>>00007 As of PHP 5.4.0 we should never get here, per http://php.net/manual/en/function.get-magic-quotes-gpc.php 
            $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
            while (list($key, $val) = each($process)) {
                foreach ($val as $k => $v) {
                    unset($process[$key][$k]);
                    if (is_array($v)) {
                        $process[$key][stripslashes($k)] = $v;
                        $process[] = &$process[$key][stripslashes($k)];
                    } else {
                        $process[$key][stripslashes($k)] = stripslashes($v);
                    }
                }
            }
            unset($process);
        }
    }
    */
	
	// >>>00026 Clearly need to do something here in the !$hasHttpHost case, once that is understood - JM 2019-06-24 
	define("HTTP_HOST", $_SERVER['HTTP_HOST']);
	define("REQUEST_SCHEME", $_SERVER['REQUEST_SCHEME']);
	
	// If we have REDIRECT_REMOTE_USER but not REMOTE_USER, use the former to set the latter. 
	if ( array_key_exists('REDIRECT_REMOTE_USER', $_SERVER) && isset($_SERVER['REDIRECT_REMOTE_USER']) 
	    && ! (array_key_exists('REMOTE_USER', $_SERVER) && isset($_SERVER['REMOTE_USER'])) ) 
	{
	    $_SERVER['REMOTE_USER'] = $_SERVER['REDIRECT_REMOTE_USER'];
	}
    unset($hasHttpHost);
}

define('BASEDIR', dirname(dirname(__FILE__)));
define('CUSTOMER', 'ssseng'); // Martin had a lot of places where 'ssseng' was hard-coded;
                              //  This gives us something we can configure later.
                              // Used mostly, but not exclusively, as part of a path to where images are stored.
                              // >>>00006 Might want to reorganize so this comes AFTER
                              //  $customer is set, and can use data from there.
define('CUSTOMER_DOCUMENTS', CUSTOMER.'_documents');

define ('LANG_FILES_DIR', BASEDIR . '/../'.CUSTOMER_DOCUMENTS.'/contract_language/');

include (BASEDIR . '/inc/functions.php');

$path = BASEDIR . '/inc/classes/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);  // pretty much just for the Zend stuff
unset($path); // JM 2019-06-20: I'm virtually certain there was no intention of setting $path as a global, so I am unsetting it.

// Loads a class.
// INPUT $class: a classname. Normally begins with a capital letter. 
//  The file that implements the class should be named "$class.class.php" and
//  should be in one of the three directories indicated here. Class names must
//  be unique across the three directories.
function auto_loader($class) {
	$paths = array(
			BASEDIR . '/inc/classes/',
			BASEDIR . '/inc/classes/API/',
			BASEDIR . '/inc/classes/helpers/');

	foreach($paths as $pkey => $path) {
		$file = sprintf('%s%s.class.php', $path, $class);
		if (is_file($file)){
			include_once $file;
		}
	}
}
//... and make that part of the autoload sequence.
spl_autoload_register('auto_loader');

$act = ''; // GLOBAL VARIABLE that we will set based on $_REQUEST['act']

// Database names & passwords
// >>>00004 Current code is specific to ssseng, will need to be revisited.
$wordpressServerName="";
if ($currentEnvironment == ENVIRONMENT_PRODUCTION){
	$username = "dba";
	$password = "P4EWaV9EzcQ"; // >>>00005 Hardcoded password
	$hostname = "localhost";
	// $database = "ssseng"; // REMOVED 2020-04-30 JM for v2020-3
	$newdatabase = "sssengnew";	
	$detaildatabase = "detailnew";
} else if ($currentEnvironment == ENVIRONMENT_DEV2 || $currentEnvironment == ENVIRONMENT_RC || $currentEnvironment == ENVIRONMENT_RC2) {
    // This case added 2019-05-15 JM
    $username = "dba";
    $password = "G1d%of58"; // >>>00005 Hardcoded password
	$hostname = "localhost";
	//$database = "sssdev";  // REMOVED 2020-04-30 JM for v2020-3
	$newdatabase = "sssengnew"; // >>>00001 Do we really want this under a different name on this machine? Because right now it is.
	$detaildatabase = "detailnew";	
} else if ($currentEnvironment == ENVIRONMENT_QA ) {
    // This case added 2020-04-29 [CP]
  $username = "dba";
  $password = "G1d%of58"; // >>>00005 Hardcoded password
  $hostname = "localhost";
  //$database = "qasssdev";  // REMOVED 2020-04-30 JM for v2020-3 
  $newdatabase = "qasssdev"; 
  $detaildatabase = "qadetailnew";
  $wordpressServerName = "rc2.ssseng.com";
}else if ($currentEnvironment == ENVIRONMENT_DEV_MARTINS_SERVER) {
	$username = "dba";
	$password = "PmDfo7Xw3DF"; // >>>00005 Hardcoded password
	$hostname = "localhost";
	//$database = "ssseng";  // REMOVED 2020-04-30 JM for v2020-3
	$newdatabase = "sssengnew";
	$detaildatabase = "detailnew";	
} else if ($currentEnvironment == ENVIRONMENT_RTC) {
	$username = "cristi";
	$password = "ab7727t"; // >>>00005 Hardcoded password
	$hostname = "localhost";
	// $database = "sssdev";  // REMOVED 2020-04-30 JM for v2020-3
	$newdatabase = "sssdevnew";	
	$detaildatabase = "detailnew";
}

define ("OFFER_BUG_LOGGING", true);
define ("BUG_LOG", 'http://bt.dev2.ssseng.com');

define ('DB__HOST', $hostname);
define ('DB__USER', $username);
define ('DB__PASS', $password);
define ('DB__NEW_DATABASE', $newdatabase); // the main DB
define ('DB__DETAIL_DATABASE', $detaildatabase);
define ('DB__SCHEMA', 'information_schema'); // ADDED 2020-05-14 JM

// JM 2019-06-20: I'm virtually certain there was no intention of setting any of the following as globals, so I am unsetting them.
unset($username);
unset($password);
unset($hostname);
unset($newdatabase);
unset($detaildatabase);

if (HTTP_HOST == 'dev2.ssseng.com' || HTTP_HOST == 'rc.ssseng.com' || HTTP_HOST == 'rc2.ssseng.com' || HTTP_HOST=='panther.raitec.prv' || HTTP_HOST == 'qa.ssseng.com' ) {
    // simulate SSS
    $domain = 'ssseng.com';
} else if (substr(HTTP_HOST, -6) == '.devel') {
    // >>>00001 2019-01-31 JM: I haven't quite worked out what Martin is up to here;
	$parts = explode(".", HTTP_HOST);
	$domain = implode(".", array_slice($parts, 0, count($parts) - 2));
} else {
	$domain = HTTP_HOST;
}

// This creates an actual Customer object - GLOBAL VARIABLE
$customer = new Customer($domain);

unset($domain); // JM 2019-06-20: I'm virtually certain there was no intention of setting $domain as a global, so I am unsetting it.

if (!is_command_line_interface()){
	$user = null;
	$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
	if (($_SERVER['SCRIPT_NAME'] == '/api.php')){
	    // BEGIN MARTIN COMMENT
		// do this in api.php
		// so that the 404 stuff displays ok
		//header('Content-Type: application/json');
		// END MARTIN COMMENT
	} else if (($_SERVER['SCRIPT_NAME'] == '/flowroutecb.php')) {
	    // >>>00014: apparently Martin wanted to head off anything about $user in this case, but I don't know why. JM 2019-06-06
	} else {
	    // NOTE: $_SESSION['username'] is set in login.php.
		if ($act == 'logout'){
			session_start();
			unset($_SESSION['username']);
			session_unset();
			session_destroy();
			unset ($user);
		} else {
			session_start();
			// BEGIN JM 2019-11-12: single session cookie for whole domain. Totally crazy for things in different directories to have different session cookies.
			// OLD CODE: setcookie(session_name(),session_id(),time()+6000); // expire session in 100 minutes
			// setcookie(session_name(),session_id(),time()+6000,'/'); // expire session in 100 minutes
			// [CP] 2020-06-30 - add samesite=secure in the cookie definition - works only on php 7.3 and more
			setcookie(session_name(), session_id(), [
			  'expires' => time() + 6000,
			  'path' => '/',
			  'samesite' => 'strict',
			]); 
			// END [CP]  2020-06-30      
			// END JM 2019-11-12: single session cookie for whole domain.
			
            // There are two different methods to log into the SSS web interface:
            //
            //  * Normally, users log in through a web page; the handling of username and password is carried in a session cookie.
            //  * In the _admin area, users log in via an Apache login. This means we will get the logged-in user from $_SERVER['REMOTE_USER'].
            //    This is MUCH better than Martin's old method, which pretty much required that an admin first log in to the non-admin side of the
            //    system, then keep the session open and log in as an admin.
            //
            //    The separate admin login as such is probably good: it provides strong protection on some very sensitive code, 
            //    so you can't just jump in with a deep link.
            //
			//  Martin had ignored $_SERVER['REMOTE_USER'], which meant we had no way to know who was logged
			//   in in the _admin area unless a session cookie was sitting around from a prior non-admin login. Fixing that here.
			if (array_key_exists('REMOTE_USER', $_SERVER) && isset($_SERVER['REMOTE_USER'])) {
			    // At least as of 2019-06, this means we are in the _admin area, because that is the only place
			    // we use Apache-style login. Imaginably, we could eventually use it elsewhere as well.
			    $user = User::getByUsername($_SERVER['REMOTE_USER'], $customer);
			    
			    if ( !$user || !$user->getUserId() ) {
			        // >>>00001 We may want to rule this out later, but for now this is legal: e.g. 'adminuser'
			        $user = null;
			    } else {
			        // We want admin login to also create a normal session.
			        $_SESSION['username'] = $user->getUserName(); 
			    }
			}
			
			if (!$user && isset($_SESSION['username'])) {
			    // Someone is already logged in: we know from the session cookie. 
			    
				// Martin comment: this is technically a person in person table.  calling a "user" to avoid confusion and some recursion
				$user = User::getByUsername($_SESSION['username'], $customer);
				
				// The following are here to make it easy to uncomment one of the following lines
				//  in a dev or test enviornment and simulate being that particular person logged in.
				
				//$user = User::getByUsername('z.moeller@ssseng.com', $customer);
				//$user = User::getByUsername('e.wright@ssseng.com', $customer);
				//$user = User::getByUsername('k.klentzman@ssseng.com', $customer);
				//$user = User::getByUsername('t.levine@ssseng.com', $customer);				
                //$user = User::getByUsername('d.fleming@ssseng.com', $customer);
                //$user = User::getByUsername('r.skinner@ssseng.com', $customer);
				//$user = User::getByUsername('e.underbrink@ssseng.com', $customer);
				if (!$user) {
					unset($_SESSION['username']);
					session_destroy();
				}
			}
		}
		header('Content-Type: text/html; charset=utf-8');
	}
}

define("PHONE_NADS_LENGTH", 10); // length of phone numbers in the North American dialing system

define("SUDO_SCRIPTS_FOR_ADMIN", '/var/www/ssseng_php_sudo_scripts');

// JM 2019-06-19: I don't see PAYMETHOD values used in any current code, looks like part of an unfinished approach to creditRecord; see comment on PAYMETHOD_CHECK
define("PAYMETHOD_CREDITCARD", 1);
define("PAYMETHOD_CHECK", 2);  // JM: as of 2019-06-19 There is a commented-out reference to this in inc/classes/API/task_newcreditrecord.class.php.
                               // Looks like something we might want, going forward, so I'm leaving these in place.
define("PAYMETHOD_CASH", 3);

// noteTypeId for DB table 'note'. Must be 1-1 with IDs defined by rows in DB table noteType.
define("NOTE_TYPE_JOB", 1);
define("NOTE_TYPE_WORKORDER", 2);
define("NOTE_TYPE_PERSON", 3);
define("NOTE_TYPE_COMPANY", 4);
define("NOTE_TYPE_WORKORDERTASK", 5);
define("NOTE_TYPE_AGEKLUDGE", 6);

// >>>00001: JM 2019-01-31 Not well understood; as of 2019-01-31, used only in SSSEng::deleteNote.
define('HISTORY_DELETE_NOTE', 1);

// introduced for v2020-3: values for DB table workOrderStatus column canNotify
define('CAN_NOTIFY_EORS', 1);
define('CAN_NOTIFY_EMPLOYEES', 2);

/* BEGIN REMOVED 2020-11-17 JM
// WorkOrder statuses, column workOrderStatusId in DB table workOrderStatusTime.
// (Also echoed intable workOrder using Trigger wostatustime_after_insert)
// Must be 1-1 with IDs defined by rows in DB table workOrderStatus.
// In v2020-3, we needed this for conversion but dropped for v2020-4
define("STATUS_WORKORDER_ACTIVE", 1);
define("STATUS_WORKORDER_WAIT_CUST_RESP", 2);
define("STATUS_WORKORDER_NONE", 3);
define("STATUS_WORKORDER_HOLD", 4);
define("STATUS_WORKORDER_RFP", 5);
define("STATUS_WORKORDER_CAN_START", 6);
define("STATUS_WORKORDER_HOLD_EOR", 7);
define("STATUS_WORKORDER_HOLD_PROPOSAL", 8);
define("STATUS_WORKORDER_DONE", 9);
// END REMOVED 2020-11-17 JM
*/

/* BEGIN REMOVED 2020-11-17 JM
// Bitflags for column extra in DB table workOrderStatusTime (also echoed in 
//  table workOrder using Trigger wostatustime_after_insert); 
//  used in conjunction with workOrderStatusId == STATUS_WORKORDER_HOLD
// EOR is "engineer of record"
// 00004: some of these are hardcoded to particular SSS employees! Surely we
// need a more general approach
// In v2020-3, we needed this for conversion but dropped for v2020-4
define("HOLD_EXTRA_EOR_RVS", 1); // Ron Skinner
define("HOLD_EXTRA_EOR_DSF", 2); // Damon Fleming
define("HOLD_EXTRA_EOR_TF", 4);  // Tim File
define("HOLD_EXTRA_CUSTOMER", 8);
define("HOLD_EXTRA_SCHEDULE", 32);
define("HOLD_EXTRA_PLANS", 64);
define("HOLD_EXTRA_EOR_EAU", 128); // Evan Underbrink
define("HOLD_EXTRA_EOR_MP", 256); // Mitchell Pearce
define("HOLD_EXTRA_EOR_EW", 512); // Easton Wright
// END REMOVED 2020-11-17 JM
*/

/* BEGIN REMOVED 2020-11-17 JM
// This is, at best, a temporary expedient, but at least lets us get
//  specific EOR names out of code elsewhere and helps the transition
//  away from being SSS-specific.
// 00005 Hardcoded email addresses follow
// 00004 Later, we have to get it away from being so SSS-specific.
// Examples of use: /crons/reviews.php, /inc/classes/WorkOrder.class.php
// In v2020-3, we needed this for conversion but dropped for v2020-4
define("EMAIL_RVS", 'r.skinner@ssseng.com'); // Ron Skinner
define("EMAIL_DSF", 'd.fleming@ssseng.com'); // Damon Fleming
define("EMAIL_TF", 't.file@ssseng.com');  // Tim File
define("EMAIL_EAU", 'e.underbrink@ssseng.com');  // Evan Underbrink
define("EMAIL_MP", 'm.pearce@ssseng.com'); // Mitchell Pearce
define("EMAIL_EW", 'e.wright@ssseng.com'); // Easton Wright
// END REMOVED 2020-11-17 JM
*/

// >>>00004, >>>00005 Hardcoded email addresses follow. Obviously, if we go beyond just SSS as customer, we need to make this configurable.
$timeManagerEmails = Array('d.fleming@ssseng.com'); // Effectively, who gets notified for late changes to someone's timesheet.

// >>>00004: Eventually we may want to split out sys admin from dev here. 
define("EMAIL_DEV", 'ssspantherdev@gmail.com');
define("EMAIL_DEV2", 'ssspantherdev@gmail.com');
define("DEV_NAME", 'Dev');
define("EMAIL_TEST", 'ssspantherdev@gmail.com');


define("EMAIL_OFFICE_MANAGER", 't.levine@ssseng.com');  // Tawny Levine, Office manager
define("OFFICE_MANAGER_NAME", 'Tawny');  // Tawny Levine, Office manager // >>>00005 Hardcoded name

/* BEGIN REMOVED 2020-08-10 JM for v2020-4
// Note that in the following, capitalized versions of the names (e.g. 'ron' => 'Ron') will show up 
//  in the "to" lines of emails.
$eor_email_address = Array();
$eor_email_address['ron'] = EMAIL_RVS;
$eor_email_address['damon'] = EMAIL_DSF;
$eor_email_address['tim'] = EMAIL_TF;
$eor_email_address['evan'] = EMAIL_EAU;
$eor_email_address['mitch'] = EMAIL_MP;
$eor_email_address['easton'] = EMAIL_EW;

$eor_extra_flag = Array();
$eor_extra_flag['ron'] = HOLD_EXTRA_EOR_RVS;
$eor_extra_flag['damon'] = HOLD_EXTRA_EOR_DSF;
$eor_extra_flag['tim'] = HOLD_EXTRA_EOR_TF;
$eor_extra_flag['evan'] = HOLD_EXTRA_EOR_EAU;
$eor_extra_flag['mitch'] = HOLD_EXTRA_EOR_MP;
$eor_extra_flag['easton'] = HOLD_EXTRA_EOR_EW;

// In v2020-3, we need this for conversion but >>>00007 this can go away for v2020-4
$eor_personId = Array();
$eor_personId['ron'] = 587;
$eor_personId['damon'] = 588;
$eor_personId['tim'] = 2110;
$eor_personId['evan'] = 3328;
$eor_personId['mitch'] = 2462;
$eor_personId['easton'] = 1638;
// END REMOVED 2020-08-10 JM for v2020-4
*/

/* REMOVED 2020-04-27 JM for v2020-3: we now get these entirely from DB table 'stamp'
// these are pulled from workorder.pdf, where there was a Martin comment: "later put these somewhere like the person table"
$eor_stamp = Array();
$eor_stamp[$eor_personId['ron']] = 'EOR_RON.pdf';
$eor_stamp[$eor_personId['damon']] = 'EOR_DAMON.pdf';
$eor_stamp[$eor_personId['tim']] = 'EOR_TIM.pdf';
$eor_stamp[$eor_personId['evan']] = 'EOR_EVAN.pdf';
$eor_stamp[$eor_personId['mitch']] = 'EOR_MITCH.pdf';
$eor_stamp[$eor_personId['easton']] = 'EOR_EASTON.pdf';
*/

/* BEGIN REMOVED 2020-08-10 JM for v2020-4
// Bringing this here rather than WorkOrder.class.php because it is customer-specific. - JM 2019-02-04
// In v2020-3, we need this for conversion but this can go away for v2020-4
$hold_status_titles_and_grace = Array(
    HOLD_EXTRA_EOR_RVS => array('title' => 'EOR RVS', 'grace' => 2),
    HOLD_EXTRA_EOR_DSF => array('title' => 'EOR DSF', 'grace' => 2),
    HOLD_EXTRA_EOR_TF => array('title' => 'EOR TF', 'grace' => 2),
    HOLD_EXTRA_CUSTOMER => array('title' => 'Customer', 'grace' => 2),
    HOLD_EXTRA_SCHEDULE => array('title' => 'Schedule', 'grace' => 14),
    HOLD_EXTRA_PLANS => array('title' => 'No Plans', 'grace' => 14),
    HOLD_EXTRA_EOR_EAU =>  array('title' => 'EOR EAU', 'grace' => 2),
    HOLD_EXTRA_EOR_MP =>  array('title' => 'EOR MP', 'grace' => 2),
    HOLD_EXTRA_EOR_EW =>  array('title' => 'EOR EW', 'grace' => 2)
);
// END REMOVED 2020-08-10 JM for v2020-4
*/

// >>>00004: Testers for features involving "private" pages. Because we create these "private" pages for
//  outside users rather than employees, we need to have a way for someone to see how those pages
//  behave. Old hard code in c/jobs.php had this set for Martin & Ron; setting this now for just Ron.
//  Uses personId.
$privatePageTesters = array(
    587 // Ron
    );
// And the person who we simulate them being
define("PRIVATE_PAGE_TEST_CASE", 273); // sarah@noviongroup.com

/* BEGIN REMOVED 2020-08-10 JM for v2020-4
// Bitflags for column extra in DB table workOrderStatusTime; used in conjunction with workOrderStatusId == STATUS_WORKORDER_RFP
define("RFP_EXTRA_GEN_PROP", 1);
define("RFP_EXTRA_WAIT_SIGN_PROP", 2);
define("RFP_EXTRA_SEND_PROP", 4);
// END REMOVED 2020-08-10 JM for v2020-4
*/

/* BEGIN DROPPED 2020-06-12 JM, no longer needed
// The following is filled in here by calling a public static funcion in the WorkOrder class,
//  see there to understand this somewhat complex data structure.
// Associated texts for SELECT elements etc.
$workOrderStatusExtra = WorkOrder::workOrderStatusExtras();

// (>>>00001: If I (JM) understand correctly, but I'm not sure I do)
// Set grace periods, in days, associated with certain top-level statuses.
// Determines how long until certain "alarms" are raised.
$workOrderStatusZeroLevel = array(STATUS_WORKORDER_ACTIVE => 21,
                                  STATUS_WORKORDER_DONE => 10000000,
                                  STATUS_WORKORDER_NONE => 2,
                                  STATUS_WORKORDER_CAN_START => 14);
// END DROPPED 2020-06-12 JM, no longer needed
*/

// Task statuses, column taskStatusId in DB table workOrderTask. 
// Must be 1-1 with IDs defined by rows in DB table taskStatus.
define("STATUS_TASK_ACTIVE", 1);
define("STATUS_TASK_DONE", 9);

// Values for column inTable of DB table 'team'
define("INTABLE_WORKORDER", 1);
define("INTABLE_JOB", 2);

// Values for column ptoTypeId in DB table pto. 
// Must be 1-1 with IDs defined by rows in DB table ptoType.
define("PTOTYPE_SICK_VACATION", 1);
define("PTOTYPE_HOLIDAY", 2);

// Values for column companyPersonContactTypeId in DB table companyPersonContact. 
// Must be 1-1 with IDs defined by rows in DB table companyPersonContactType. 
define("CPCONTYPE_EMAILPERSON",1);
define("CPCONTYPE_LOCATION",2);
define("CPCONTYPE_PHONEPERSON",3);
define("CPCONTYPE_EMAILCOMPANY",4);
define("CPCONTYPE_PHONECOMPANY",5);

define("WOT_VIEWMODE_CONTRACT",1);
define("WOT_VIEWMODE_TIMESHEET",2);
define("WOT_VIEWMODE_INVOICE",4);

/* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
// Bitflags for column viewMode in DB tables 'task' and workOrderTask.
// Allows us to control what tasks get printed into different documents, e.g.
//  so that we can hide certain internal tasks in an invoice, while showing
//  then in a timesheet.
define("WOT_VIEWMODE_CONTRACT",1);
define("WOT_VIEWMODE_TIMESHEET",2);
define("WOT_VIEWMODE_INVOICE",4);

// Display for the above.
$wotviewmode = Array();  // (Array initialization added JM 2019-01-31, better PHP practice; feel free to kill this comment)
$wotviewmode[WOT_VIEWMODE_CONTRACT] = 'Contract';
$wotviewmode[WOT_VIEWMODE_TIMESHEET] = 'Timesheet';
$wotviewmode[WOT_VIEWMODE_INVOICE] = 'Invoice';
// END REMOVED 2020-10-28 JM
*/

// Values for column jobLocationTypeId in DB table jobLocation. 
// Must be 1-1 with IDs defined by rows in DB table jobLocationType.
/* BEGIN REMOVED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153, we got rid of the "seismic" thing here
define("JOBLOCTYPE_SITE",1);
define("JOBLOCTYPE_CSEISMIC",2);  // county seismic
// END REMOVED JM 2020-05-11
*/

// Presumably values for loadVarType in DB table serviceLoadVar
// >>>00001, >>>00026 JM: 2019-01-31: this doesn't line up well with data seen in that table, merits investigation. 
define("SERVLOADVARTYPE_MULTI",1);
define("SERVLOADVARTYPE_SINGLE",2);
// Names for those, presumably to use in a Select
$serv_load_var_type = Array();  // (Array initialization added JM 2019-01-31, better PHP practice; feel free to kill this comment)
$serv_load_var_type[SERVLOADVARTYPE_MULTI] = 'Select Box';
$serv_load_var_type[SERVLOADVARTYPE_SINGLE] = 'String';

// Values for column teamPositionId in DB table 'team'. 
// Must be 1-1 with IDs defined by rows in DB table teamPosition.
define("TEAM_POS_ID_CLIENT", 1);
define("TEAM_POS_ID_DESIGN_PRO", 2);
define("TEAM_POS_ID_EOR", 3);
define("TEAM_POS_ID_STAFF_ENG", 4);
define("TEAM_POS_ID_CORRESPONDEE", 5);
define("TEAM_POS_ID_REFERRER", 6);
define("TEAM_POS_ID_ORIGINATOR", 7);
define("TEAM_POS_ID_CONTRACTOR", 8);
define("TEAM_POS_ID_JURISDICTION", 9);
define("TEAM_POS_ID_SUPPLIER", 10);
define("TEAM_POS_ID_LEADENGINEER", 11);
define("TEAM_POS_ID_SUPPORTENGINEER", 12);
define("TEAM_POS_ID_CONSULTANT", 13);

// Values for column taskTypeId in DB table task. 
// Must be 1-1 with IDs defined by rows in DB table taskType.
// As of 2019-01-31, it looks like only TASKTYPE_HOURLY is explicitly referenced elsewhere in the code. 
define ("TASKTYPE_OVERHEAD", 1);
define ("TASKTYPE_QTY_ITEM", 2);
define ("TASKTYPE_FIXED", 3);
define ("TASKTYPE_HOURLY", 4);

// Values for column payPeriod in DB table customerPersonPayPeriodInfo
define("PAYPERIOD_BIMONTHLY_1_16", 1);
define("PAYPERIOD_WEEKLY_MON_SUN", 2);

//Values for column workWeek in DB table customerPersonPayWeekInfo
define("WORKWEEK_MON_SUN", 1);

// Associated texts for SELECT elements etc.
$payperiod = array();  // (Array initialization added JM 2019-01-31, better PHP practice; feel free to kill this comment)
$payperiod[1] = 'Bimonthly 1, 16';
$payperiod[2] = 'Weekly Mon-Sun';

$workweek = array();
$workweek[1] = 'Mon - Sun';

// >>>00009: Hardcoded values related to Details API/subsystem
define ("DETAILS_HASH_KEY", '4UO6ZPhje348ops1lknm4bLd5A2QpKlX');
define ("DETAILS_HASH_KEYID", 561312);
// NOTE that these use "DETAIL_" not "DETAILS_"
define ("DETAIL_ROOT", 'http://detail.ssseng.com');
define ("DETAIL_API", DETAIL_ROOT.'/api.php'); 

// >>>00009: Hardcoded values related to Details API/subsystem
define ("DETAILS_BASICAUTH_USER", 'detuser');
define ("DETAILS_BASICAUTH_PASS", 'sonics^100'); // It looks like in code this is used some places like a username, others like a password.

// >>>00001: used as a argument in calls to Customer::getEmployees to limit to
//  current employees, BUT as of 2019-01-31 in Customer.class.php this is
//  simply hardcoded as the number 1, doesn't use this defined constant.
define("EMPLOYEEFILTER_CURRENTLYEMPLOYED", 1);

// >>>00010 Hardcoded values related to telephony
// >>>00001 As of 2019-01-31, I haven't studied the related area of the code at all. - JM
define("FLOWROUTE_API_USER",'41470238');
define("FLOWROUTE_API_PASS",'31nA0oi3xXUdaZ4GjQt6QY3xCAI7gx5M');
define("FLOWROUTE_SMS_DID",'14253126220'); // Damon's phone number 14253126220 forwards to ext. 701 (Damon Fleming)

// Values for column phoneTypeId in DB tables personPhone, companyPhone, etc. 
// Must correspond with ID defined by a row in DB table phoneTypes.
define("PHONETYPE_CELL",5); // singled out so code can know it can send an SMS

// >>>00010 Hardcoded values related to telephony
define ("SMSMEDIA_HASH_KEY", 'loeds3rMp45Rfdsqoi80plCLWed31mnA');

// This relates to a scheme for "private" pages to let OUTSIDE users see 
// certain data via a temporary URL. 
// >>>00011: JM believes that this never fully came together as of 2019-01-31
// >>>00005: Hardcoded values
// >>>00001: as of 2019-01-31, /c/job.php refers to a PRIVTYPE_JOB_SUMMARY that is not defined here.
define ("PRIVATE_HASH_KEY", 'klEdsa58uDlkWs34Qaplpo54EdCzXlOT');
define ("PRIVTYPE_OPEN_WO", 1);
define ("PRIVTYPE_JOBS", 2);
// ... and for display
$privTypes = array();
$privTypes[PRIVTYPE_OPEN_WO] = 'Open Work Orders';
$privTypes[PRIVTYPE_JOBS] = 'Jobs';

define ("MAX_PANTHER_PASSWORD_LENGTH", 40); // Database will actually handle 128, if someone wants to increase this.

// Permission levels
// >>>00001: JM 2019-01-31: the following remark is not 100% certain; feel free to edit and to 
//  kill my remarks (including this one!) if you are sure about this.
// These correspond to the digits making up the string in column permissionString
//  in DB table permissionGroup; the meaning of each (what it is permisson FOR) 
//  is determined by DB table 'permission'. permission.permissionId is effectively
//  a position in a string of such digits.
//  Although the strings are each 64 digits, only the first few are currently 
//  significant and the rest are for future use. 
define("PERMLEVEL_ADMIN", 1);
define("PERMLEVEL_RWAD", 2); // Read-write-add-delete
define("PERMLEVEL_RWA", 3);  // Read-write-add
define("PERMLEVEL_RW", 5);   // Read-write
define("PERMLEVEL_R", 7);    // Read-only
define("PERMLEVEL_NONE", 9);

define("NO_BEGINNING", '0000-00-00'); // An "impossibly distant" start date
define("NO_TERMINATION", '9999-01-01'); // An "impossibly distant" termination date

// invoiceAdjustTypeId for DB table invoiceAdjust. Must be 1-1 with IDs defined by rows in DB table invoiceAdjustType.
define("INVOICEADJUST_DISCOUNT", 1);
define("INVOICEADJUST_PERCENTDISCOUNT", 2);
define("INVOICEADJUST_QUICKBOOKSSHIT", 3);  // >>>00012: obviously should be renamed.

// smsProviderId values for DB table inboundSMS
define("SMSPROVIDERID_FLOWROUTE", 1);
// Martin comment: in lieu of a db table [END Martin comment]
$smsNumbers = array();

/// >>>00010, >>>00004: hardcoded SSS values related to telephony
define("FLOWROUTE_SMS_DAMON", 14253126220); // Damon's phone number 14253126220 forwards to ext. 701 (Damon Fleming)
define("FLOWROUTE_SMS_FRONT_DOOR", 14257781023); // 14257781023 is SSS "front door"
define("CELL_PHONE_RON", 12067142475);

/// >>>00010, >>>00004: hardcoded SSS values related to telephony
$smsNumbers[FLOWROUTE_SMS_DAMON] = array('provider' => SMSPROVIDERID_FLOWROUTE, 'did' => FLOWROUTE_SMS_DAMON, 'class' => 'SMS_FlowRoute');
$smsNumbers[FLOWROUTE_SMS_FRONT_DOOR] = array('provider' => SMSPROVIDERID_FLOWROUTE, 'did' => FLOWROUTE_SMS_FRONT_DOOR, 'class' => 'SMS_FlowRoute');

// >>>00004: a percentage match, hardcoded.
define("COMPANY_IRA_MATCH",3);

// For the issue ticketing system.
// Ticket statuses, column ticketStatusId in DB table ticketStatusTime. 
// Must be 1-1 with IDs defined by rows in DB table ticketStatus.
define ("TICKET_STATUS_RECEIVED",1);
define ("TICKET_STATUS_OPEN",2);
define ("TICKET_STATUS_CLOSED",3);

// JM 2020-09-03:
// This relates to hierarchies of workOrderTasks (heirarchy dependent on their underlying abstract tasks), and is relevant:
//  * in invoice.php as instructions for invoicepdf.php 
//  * in contract.php as instructions for contractpdf.php
// >>>00042 As of 2020-09-03, invoice.php and contract.php show arrows in the UI even if something has no subtasks; not
//  clear that is useful.
// >>>00012 The names here are not very mnemonic: they are about the UI rather than what these do. Better names might be
//  SUBTASKS_SEPARATELY_LISTED and SUBTASKS_BUNDLED, respectively.
// 
// ARROWDIRECTION_DOWN means that for invoice/contract purposes, all subtasks of this task are bundled into this task, rather
//  than having their own line items: we will sum up their costs and bundle them into the cost for the present task.
// ARROWDIRECTION_RIGHT is the default case of everything left over. It applies to:
//  1) tasks that have subtasks, but those subtasks are not bundled into it
//  2) tasks that have no subtasks
// NOTE that it if we have multiple levels of tasks marked with ARROWDIRECTION_DOWN, only the one closest to the root 
//  (lowest "level" number) matters. For example, if
//  - A is parent of B, C, and D
//  - C is parent of E and F
//  - A is marked with ARROWDIRECTION_DOWN
//  then it is completely irrelevant whether C is marked with ARROWDIRECTION_DOWN, because C, E, and F are already bundled into A, as are B and D. 
define("ARROWDIRECTION_RIGHT", 1);
define("ARROWDIRECTION_DOWN", 2); 

// >>>00001: obviously something about sending SMSs, but needs study as of 2019-01-31 
define ("SMS_PERM_PING",1);
define ("SMS_PERM_HELP",2);
define ("SMS_PERM_OPEN",4);
define ("SMS_PERM_JOBS",8);
define ("SMS_PERM_OTHER",16);  // >>>00001: but just what is this?
define ("SMS_PERM_EMP_PING",1);
define ("SMS_PERM_EMP_SOU",2);  // "state of union"

// Types of payment
// Values for column creditRecordTypeId in DB table creditRecord;
// Further elaborated in /inc/classes/CreditRecord.class.php. 
define ("CRED_REC_TYPE_CHECK",1);
define ("CRED_REC_TYPE_PAYPAL",2);
define ("CRED_REC_TYPE_CASH",3);
define ("CRED_REC_TYPE_CC",4);    // credit card
define ("CRED_REC_TYPE_WIRE",5);

// Values for column creditMemoTypeId in DB table creditMemo
define ("CRED_MEMO_TYPE_IN",1);
define ("CRED_MEMO_TYPE_OUT",2);
// ... and for display
$credRecTypes = Array();  // (Array initialization added JM 2019-01-31, better PHP practice; feel free to kill this comment)
$credRecTypes[CRED_MEMO_TYPE_IN] = 'Inbound';
$credRecTypes[CRED_MEMO_TYPE_OUT] = 'Outbound';

// Values for column ira in DB table customerPersonPayPeriodInfo
// Employee IRA withholding can be a dollar amount per pay period or a percentage.
define ("IRA_TYPE_PERCENT",1);
define ("IRA_TYPE_DOLLAR",2);
// ... and for display
$iraTypes[IRA_TYPE_PERCENT] = 'Percent';
$iraTypes[IRA_TYPE_DOLLAR] = 'Dollar';

// Values for column billingBlockTypeId in DB table billingBlock
define ("BILLBLOCK_TYPE_NONPAY_PREVIOUS",1);
define ("BILLBLOCK_TYPE_REMOVEBLOCK",2);
// ... and for display
$billBlockTypes[BILLBLOCK_TYPE_NONPAY_PREVIOUS] = 'Previous Non Payment(s)';
$billBlockTypes[BILLBLOCK_TYPE_REMOVEBLOCK] = 'Block Removed';

// BEGIN added 2019-02-01 JM to configure /ajax/patchcall.php
// NOTE that this means that ANYONE in the dev environment will use '/home/martin/public_html/pami.com/vendor/autoload.php', 
//  not something specific to the developer's own code. 2019-06-20: but ENVIRONMENT_DEV_MARTINS_SERVER will soon be pretty much irrelevant.
//  As of 2019-12 we are keeping it around solely for details work.
if ($currentEnvironment == ENVIRONMENT_DEV_MARTINS_SERVER){
	define ("PAMI_PATH", '/home/martin/public_html/pami.com/vendor');
} else {
	define ("PAMI_PATH", '/var/www/pami/vendor');
}

// 2019-10-06 - Unset the $currentEnvironment after used all over was necessary. Defined in order to avoid the call of the environment() function a lot of times
unset($currentEnvironment);

// 2019-02-01 - 2019-02-05 JM: BEGIN brought here from /ajax/patchcall.php, mailtest.php
// >>>00010 Hardcoded telephony stuff
define ("PBX_SERVER_IP_ADDRESS",'162.255.20.113');
define ("FREEPBXTEST_USER",'100');
define ("FREEPBXTEST_PASSWORD",'ron800ron');
/*
OLD CODE removed 2019-03-05 JM
define ("FREEPBXTEST_TEST_PHONE_1",14582020169); // >>> according to Damon, probably one of Martin's numbers
define ("FREEPBXTEST_TEST_PHONE_2",12066057533); // >>> according to Damon, one of Martin's numbers
define ("MAILTEST_TEST_PHONE", 4257701373); // test SMS target, NOTE no leading '1'; probably Martin's
*/
// BEGIN NEW CODE 2019-03-05 JM
define ("FREEPBXTEST_TEST_PHONE_1", 12065791722); // VOIP for Michael Hasse's cell 
define ("FREEPBXTEST_TEST_PHONE_2", 14253451132); // Main number for Michael Hasse's cell   
define ("MAILTEST_TEST_PHONE", 4253451132); // Main number for Michael Hasse's cell, NOTE no leading '1'
// EMD NEW CODE 2019-03-05 JM

// For /ajax/patchcall.php 
// >>>00010 Hardcoded telephony stuff
$patchcall_options = array(
        'host' => PBX_SERVER_IP_ADDRESS,
        'port' => '5038', // Michael says probably Asterisk management interface, for programming VOIP
        'username' => 'webdial',
        'secret' => '37d677fe771a61380f54^2a7b87531bf',
        'connect_timeout' => 10,
        'read_timeout' => 10,
        'scheme' => 'tcp://' // Martin comment: try tls://
);

// >>>00010 Hardcoded telephony stuff
define ("PATCHCALL_CALLERID", '<2065790613>'); // Damon's cell

// For manager/phoneapi.php
// >>>00010 Hardcoded telephony stuff
define ("PHONEAPI_KEY", 'dhfswerywueirywRR55esdC');

// >>>00014 JM 2019-02-05: Someone needs to actually understand what this extension & IP really are about.
// Rename these here and in phoneapi.php once you understand them.
// >>>00010 Hardcoded telephony stuff
define ("PHONEAPI_EXT", 700); // Ron's extension
define ("PHONEAPI_IP_ADDRESS", '192.168.70.179'); // should be an internal IP address...  

// >>>00004 The following should eventually be configurable for different customers, but we can leave it hardcoded for the moment
//  while the only customer is SSS. Just centralizing to get rid of literals in the code.
define ("CUSTOMER_INBOX", 'inbox@ssseng.com'); // "Front door" email.
define ("CUSTOMER_NAME", 'Sound Structural Solutions');
define ("CUSTOMER_FULL_NAME", 'Sound Structural Solutions, Inc.');
define ("CUSTOMER_USES_TM", 1);
define ("CUSTOMER_USES_REGISTERED_TM", 0);
// CUSTOMER_PUBLIC_DESCRIPTION_HTML can include HTML markup. Must be written as content of an HTML paragraph.
define ("CUSTOMER_PUBLIC_DESCRIPTION_HTML", 'Sound Structural Solutions Inc is an engineering design firm '.
                'specializing in residential and commercial structures. We believe in a client-centered approach ' .
                'to consulting and strive to understand your individual needs. We welcome your inquiries and look '. 
                'forward to talking about your project.');
// CUSTOMER_ADDRESS_AND_PHONE_HTML can include HTML markup. Must be written as content of an HTML paragraph.
// Should end with phone. Email address will be appended after phone.
define ("CUSTOMER_ADDRESS_AND_PHONE_HTML", '24113 56th Ave W<br />Mountlake Terrace WA 98043<br />425.778.1023');
define ("CUSTOMER_ADDRESS_ONE_LINE", '24113 56th Ave. W - Mountlake Terrace, WA 98043');
define ("CUSTOMER_STREET_ADDRESS", '24113 56th Ave W'); // for footer.php
define ("CUSTOMER_CITY_AND_ZIP", 'Mountlake Terrace, WA 98043'); // for footer.php
define ("CUSTOMER_PHONE_WITH_DOTS", '425.778.1023');
define ("CUSTOMER_PHONE_FOR_FOOTER", CUSTOMER_PHONE_WITH_DOTS); // for footer.php
define ("CUSTOMER_PHONE_USING_PARENS", '(425)778 1023');
define ("CUSTOMER_DOMAIN_MINIMAL", 'ssseng.com');  // no http, etc.
define ("CUSTOMER_EMPLOYER_IDENTIFICATION_NUMBER", '20-2955014');
define ("CUSTOMER_CONTACT_IMAGE", 'sss-building.jpg'); // JM 2019-02-01: given the name of this image in contact.php, I figured
                                                       // we'll want to make this configurable somehow, brought it here. Alternatively,
                                                       // we could change that string to something more generic (e.g "company-logo.jpg")
                                                       // and just be able to change it by putting a different file in the directory
                                                       // for a different customer
// Google map that will open in an iframe on the contact.php page.
// >>>00005 Hardcoded for SSS
define ("CUSTOMER_GOOGLE_MAP_SRC", 'http://maps.google.com/maps?q=24113+56th+Ave+W+Mountlake-Terrace+WA+98043&amp;ie=UTF8&amp;hq=&amp;hnear=24113+56th+Ave+W,+Mountlake+Terrace,+Washington+98043&amp;gl=us&amp;t=m&amp;ll=47.779914,-122.3101717&amp;spn=0.020061,0.036392&amp;z=14&amp;iwloc=A&amp;output=embed');

// Key obtained 2020-04 for the "Panther 2020" account; see http://sssengwiki.com/Google+Maps
// >>>00005 Hardcoded for SSS
define ("CUSTOMER_GOOGLE_PANTHER_KEY", 'AIzaSyACxF471O5h1VukJYpf2GoSYCNBm_ldjE4');

// More Google map stuff for /inc/classes/Location.class.php
// Presumably also different per customer
define ("CUSTOMER_GOOGLE_JSON_KEY", CUSTOMER_GOOGLE_PANTHER_KEY);

// More Google map stuff for /inc/classes/job.php
// Presumably also different per customer
define ("CUSTOMER_GOOGLE_LOADSCRIPT_KEY", CUSTOMER_GOOGLE_PANTHER_KEY); // for job.php & location.php
define ("CUSTOMER_GOOGLE_LOADSCRIPT_KEY_2", CUSTOMER_GOOGLE_PANTHER_KEY); // for fn/job.php, no idea why it is different from

// >>>00005 Hardcoded
// >>>00004 The following should eventually be configurable for different customers, but we can leave it hardcoded for the moment
//  while the only customer is SSS. Just centralizing to get rid of literals in the code.
$contract_return_instructions = Array( // One array element per line
    'Return a signed copy to: Sound Structural Solutions, Inc',
    '24113 56th Ave W | Mountlake Terrace, Washington 98043',
    'ph 425-778-1023 | inbox@ssseng.com'
);

define ("CONTRACT_GOOD_FAITH", 'Sound Structural Solutions, Inc intends to provide the following professional services in good faith '.
              'and in accordance with the practices currently standard to this industry in exchange for the fees outlined below.');

define ("CONTRACT_ASSUMES_FINANCIAL_RESPONSIBILITY", 'The undersigned hereby assumes financial responsibility by authorizing '.
              'Sound Structural Solutions to perform the professional services as stated in this document.');

/// >>>00010, >>>00004: hardcoded SSS values related to telephony
define ("HARDCODED_FLOWROUTE_IP_1", '52.88.246.140'); // >>>00014 =>"Serious mystery": An Amazon IP; in the DEV environment
                                                      //  (but not on a live system) aome Flowroute telephony code uses this 
                                                      //  as a faked-up $_SERVER['REMOTE_ADDR']. *Might* also occur "naturally" as
                                                      //  $_SERVER['REMOTE_ADDR'] on a live system
define ("HARDCODED_FLOWROUTE_IP_2", '52.10.220.50');  // >>>00014 =>"Serious mystery": An Amazon AWS IP; along with HARDCODED_FLOWROUTE_IP_1,
                                                      //  this is one of two remote IP addresses allowed by our Flowroute telephony code.
define ("HARDCODED_FLOWROUTE_DEV_SENDER_1", 4257781023); // >>>00004, >>>00010 Hardcoded sender of Flowroute SMSs in the Dev environment. SSS "front door" number
define ("HARDCODED_FLOWROUTE_ID_DEV", 'mdr2-c3f86280d65411e88a87feddb0f3394c');   // >>>00014 =>"Serious mystery": No idea where this ID
                                                      // comes from, but apparently when in the DEV environment flowroutecb.php always
                                                      // gives this as an ID in the JSON.
define ("HARDCODED_FLOWROUTE_ID_DEV_2", 'mdr2-ec9f7b30dc7f11e786688e76b030dee9');   // >>>00014 =>"Serious mystery": Similar to the above,
                                                      // in the DEV environment for flowroutemmscb.php
// >>>00014 => another "Serious mystery", used in the DEV environment for flowroutemmscb.php. Presumably something related to AWS access,
// but basically not yet understood 2019-02-01
define ("HARDCODED_FLOWROUTE_URL_1", 'https://mms-media-prod.s3.amazonaws.com/942549551265177600-IMG_6735.jpg?AWSAccessKeyId=ASIAIYLCPWD47RN6SNCQ&Expires=1516148301&x-amz-security-token=FQoDYXdzEJf%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaDBOwDngdrN3y9kuamiKAAtC6836EYhcIOMY46U%2BwrAHLSqsoI3b0Gb3DSk6IUco%2FfYXRE3%2BIRIrD6jP2sV3%2BJV07ZQ9ogi94nYfcNhF97T%2FfI06pepsVsYR7c2lJQ5JWoOirXCpqbTZn6u5Mccu0YYv7fXCcukLHJRUPg8s3D7JkJaMDVCLry9k9Wi1vurLmn1iFRkqPK95Ok7lh4nDurS2nGHs%2F9ZahV8vN%2B9IhI5MoWzLjFRsO9aeg%2FqWO8diUl0nenBiAbh7POyzJNX7TORpt%2Feo2Z6L8TyumXvpA42rkvx3GrMSvIaAVoscyWDYE3QyesNczD85OtiMvYT4qfv7orVX1SOA5SJS9HS5Ijboo1Mjb0QU%3D&Signature=Oy3ZDwjoOciEjyNYVXlUmOzB7ZY%3D');

define ("DEV_PHONE_1", 4257781023);  // >>>00004, >>>00010 Used below in FLOWROUTEMMSCB_TEST_JSON, probably shouldn't be used anywhere else. 
                                     // SSS "front door" number.

// >>>00010 NOTE that in the DEV environment, flowroutemmscb.php sends some sort of hardcoded message that seemed too complicated 
//  to bring here to config.php at the moment. Needs further study 2019-02-01 JM. Do we want to bring that whole data structure
//  that is assigned as a value to $rawjson here into config.php?

// There is a kludge case in /inc/classes/API.class.php that I (JM) don't fully understand.
// Martin commented, "kludging a bit to allow access by something that is not in customerPerson".
// >>>00005 Hardcoded, SSS-specific
// It uses the following constants:
define ("API_KLUDGE_KEY_ID", 'A887739');
define ("API_KLUDGE_KEY_STRING", 'fgret678wsNh53EloPdxs23WQaxcZo90rdEfuPlt');
define ("API_KLUDGE_PERSON_ID", 587); // Ron Skinner. As Martin says, "ron skinner id .. total kludge"

/* BEGIN REMOVED 2020-05-21 JM
// and for /inc/classes/API_old.class.php, which probably isn't used:
// 00005 Hardcoded, SSS-specific
define ("API_OLD_HASH_KEY", 'kjDF74miV5DDc07jYOwaaXeSjJMsd67e');
*/

// >>>00004 Current code is specific to ssseng, will need to be revisited.
define ("SSSMAIL_PORT", 587); // Secure sending of email.
define ("SSSMAIL_USERNAME", 'inbox@ssseng.com'); // I (JM) believe it is not a requirement that this be the same as CUSTOMER_INBOX,
                                                 // so I've given it its own declaration.
define ("SSSMAIL_PASSWORD", '$alutations4Th3m');                                                  

// >>>00004 Current code is specific to ssseng, will need to be revisited.
// NOTE that as of 2019-02-04 code means, for example, that sssnew.com.martin.devel, the domain 
//  that was given to Joe as a dev/test area, is guaranteed to fail.  
$valid_ajax_origin_domains = Array(
    'http://ssseng.com',
    'https://ssseng.com',
    'http://dev2.ssseng.com',
    'https://dev2.ssseng.com',
    'http://rc.ssseng.com',
    'http://rc2.ssseng.com',
    'https://rc2.ssseng.com',
    'http://panther'
);

define ("ESTIMATED_TASKS_CODE", 4);
define ("CONTRACT_ELEMENT_TOTAL_POSITION", 1); // 1 upper side (same line as the element name), 2 bottom line (after all tasks)

// >>>00004 Current code is presumably specific to ssseng, will need to be revisited.
define ("VIMEO_AUTHORIZATION", 'Authorization: Bearer 39cd40233c0ec07a3894224b5af9d679');

// >>>00004 The following should eventually be configurable for different customers, but we can leave it hardcoded for the moment
//  while the only customer is SSS. Just centralizing to get rid of literals in the code.
define ("PAYPAL_HOSTED_BUTTON_ID", 'Q3T6XPT5U7ASL');

// >>>00004 The following should eventually be configurable for different customers, but we can leave it hardcoded for the moment
//  while the only customer is SSS.
// There are wikilinks for some database entities (at least ServiceLoadVars). These wikilinks need a context to make sense
//  So in the code we want something like '... href="' . WIKI_URL . $serviceLoadVar->getWikiLink()/'"'
define ("WIKI_URL", 'http://sssengwiki.com/tiki-index.php?page=');

define ("SMS_DISPLAY_KEY", 'KfhDfs1w4e33exxh'); // this key is checked in the input of smsdisplay.php, must be present for
                                                // the file to run.

// >>>00004, >>>00010, >>>00014: The following is a bit of a mystery. Used in  
//  flowroutemmscb.php, apparently just for the dev/test environment. - JM 2019-02-05
define ("FLOWROUTEMMSCB_TEST_JSON", '{"included": ['.
                '{"attributes": {'.
                    '"url": "'.HARDCODED_FLOWROUTE_URL_1.'", '.
                    '"file_name": "IMG_6692.jpg", '.
                    '"mime_type": "image/jpeg",'.
                    '"file_size": 662315'.
                '}, '.
                '"type": "media", '.
                '"id": "939305103458545664-IMG_6692.jpg", ' .
                '"links": {'.
                    '"self": "https://api.flowroute.com/v2.1/media/939305103458545664-IMG_6692.jpg"}'.
                 '}'.
             '], '.
             '"data": {'.
                 '"relationships": {'.
                     '"media": {'.
                         '"data": [{'.
                             '"type": "media", '.
                             '"id": "939305103458545664-IMG_6692.jpg"'.
                         '}]'.
                     '}'.
                 '}, '.
                 '"attributes": {'.
                     '"status": "",'.
                     '"body": "again message",'.
                     '"direction": "inbound",'.
                     '"amount_nanodollars": 9500000,'.           // >>>00014: So is this doing something with real money?
                     '"to": "'.FLOWROUTE_SMS_DAMON.'",'.
                     '"message_encoding": 0,'.
                     '"timestamp": "2017-12-09T01:26:02.00Z",'.  // NOTE hardcoded timestamp
                     '"delivery_receipts": [],'.
                     '"amount_display": "$0.0095",'.
                     '"from": "'.DEV_PHONE_1.'",'.
                     '"is_mms": true,'.
                     '"message_type": "longcode"'.
                 '}, '.
                 '"type": "message",'.
                 '"id": "'.HARDCODED_FLOWROUTE_ID_DEV_2.'"}'.
             '}');

// >>>00004 : moving the "About" text here; obviously, different for anything other than SSS
// This allows the full capabilities of HTML
define ("ABOUT_HTML", 
'    <h1>About Us</h1>' . "\n" .
'    <ul>' . "\n" .
'        <li>started in 2005</li>' . "\n" .
'        <li>attended college together at Montana State</li>' . "\n" .
'        <li>Construction Exposure</li>' . "\n" .
'        <li>Combined 25 years Experience</li>' . "\n" .
'    </ul>' . "\n" .
'    <br />' . "\n" .
'    <p>We started Sound Structural Solutions in 2005 with the intention of providing our clients with straightforward solutions to their' . "\n" .
' construction needs. With a combined experience of 25 years in both engineering and construction we have encountered first hand,' . "\n" .
' many of the challenges faced in construction.</p>' . "\n" .
'    <p>Our friendship began at Montana State University while attending college together.' . "\n" .
' Working together for so many years has allowed us to stay competitive within our industry.' . "\n" .
' Constant collaboration requires us to justify our analysis and design outside a frame work larger than ourselves.</p>' . "\n" .
'    <p>Customer service is one of our driving concerns. Understanding many of the challenges in construction,' . "\n" .
' we appreciate the people tasked with accomplishing these goals. We draw from our clients\' experience' . "\n" .
' as they have valuable insights that benefit good working solutions.</p>' . "\n" .
'    <p>Providing simple and easy to understand plans and documents is important for the successful completion of a project.' . "\n" .
' Our goal is to save you time and money by making the information clear and quickly accessible.' . "\n" .
' We don\'t expect you to wade through invalid details and notes leaving you or your inspector to make interpretations.' . "\n" .
' Your feedback is welcome as we are constantly trying to improve our communications.</p>');


define ("WHO_TO_SEE_FOR_CHANGING_STATUS", '(see Ron, Tawny, or a developer for clarification if needed!).<br>' .
     'See Tawny if the behavior doesn\'t work as expected (particularly in Chrome Browser)');

// Defaults for location.
// >>>00004 We'll want to set this per customer
define ("HOME_STATE", "WA");
define ("HOME_COUNTRY", "US");

define ("SYMBOL_LOAD_IN_SAME_FRAME", '&#10534;');  // HTML hook arrow, used as a consistent symbol for loading a new page in the same frame, 
                                                   // mainly needed for fancyboxes. 

//  Wordpress Configuration
$schema = ""; // http or https.
//$wordpressServerName = ""; //  Wordpress Server Name


if($schema == "") {
    $schema =  $_SERVER["REQUEST_SCHEME"]; // http or https.
} 

if($wordpressServerName == "") {
    $wordpressServerName = $_SERVER['SERVER_NAME']; // take current server name
}

// Restroute used for the plugin Simple JWT Login.
$restRoute = "?rest_route=/".$schema."://".$_SERVER['SERVER_NAME'] ."/generate_jwt.php/simple-jwt-login/v1/autologin";
?>
