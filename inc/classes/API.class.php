<?php
/* inc/classes/API.class.php

EXECUTIVE SUMMARY
This class is intended to provide software that is not part of the main system with access to some of its functionality. 
Currently (2019-02) used mainly for mobile apps. 
Martin described this in late 2018 as "in the early stages of being thought about", so don't expect that this is a 
particularly mature part of the system.

The basic pattern is to create an API object, then use it to return a status ('success'/'fail') and data; you can also 
return error information.

[BEGIN Martin comments]
maybe have the instance methods here actually instantiate
the "task_" class that then can all extend the API class

look at how to stop a class (task class) from being instantiated 
by itself (as opposed from within the API class)

https://stackoverflow.com/questions/1670718/protecting-php-classes-from-undesired-instantiation
what is a "final" class ??
[END Martin comments]
JM 2019-02-04: Answering Martin's question: a "final" class is one that (in PHP terms) cannot be
further extended; that is, it cannot have child classes.

* Extends SSSEng, typically constructed for current user. Constructor can optionally take a personId & a customer object to set user. 
* Public functions:
** __construct($personId, $customer)
** setPersonId($val)
** getPersonId()
** setError($str)
** getErrors()
** setStatus($str)
** getStatus()
** setData($array)
** getData()
** public static function authenticate()
*/

require_once dirname(__FILE__).'/../config.php'; // ADDED 2019-02-13 JM

class API extends SSSEng{
	
	const API_ID = 1; // It would appear that as of 2019-02 this is the only valid API id.
	
//	const API_HASH_KEY = 'kjDF74miV5DDc07jYOwaaXeSjJMsd67e'; // This was already commented out by Martin some time before 2019

	//private $customer;
	private $api_error;
	private $status;
	private $data;
	
	private $personId;
	
	// Typically constructed for current user, but no default to make it so. 
	// Constructor can optionally take a personId & a customer object to set user.
	// INPUT $personId: unsigned integer, primary key into DB table Person.
	//   In theory, you can pass $personId in later via setPersonId but
	//   >>>00001 JM 2019-02-18 suspects that won't work well.
	// INPUT $customer: Customer object
	// >>>00001 JM 2019-02-18: NOTE that $this->status is not initialized by the constructor.
	//   I strongly suspect it should be initialized either blank or to some sentinel string.
	function __construct($personId, $customer){
		$this->api_error = new api_error();
		$this->data = array();
		
		if (intval($personId)){			
			if (get_class($customer) == 'Customer'){
				$this->setPersonId($personId);
				$user = new User($personId,$customer);					
				parent::__construct($user);
//				$this->customer = $customer; // This was already commented out by Martin some time before 2019
			}
		}
	}

	// GETs & SETs
	
	// >>>00001 Unlike constructor, setPersonId won't set $user, nor will it construct the parent,
	//  so JM 2019-02-18 doubts  this is useful as a public method.
	// INPUT $personId: unsigned integer, primary key into DB table Person.
	public function setPersonId($val){
		$this->personId = intval($val);
	}
	// Return $this->personId
	public function getPersonId(){
		return $this->personId;
	}
	
	// Insert an arbitrary error string in the API error array.
	// INPUT $str: error string
	public function setError($str){		
		$this->api_error->setError($str);	
	}
	
	// Return content of the API error array. Returns an array of strings.
	public function getErrors(){	
		return $this->api_error->getErrors();		
	}
	
	// Set status of API call.
	// INPUT $str: while in theory this is an open-ended string, in practice  
	//  it is normally "fail" or "success".
	public function setStatus($str){		
		$this->status = $str;		
	}
	
	// Return status of API call.
	public function getStatus(){		
		return $this->status;		
	}
	
	// Set data returned from API call. 
	// INPUT $array: should be a key-value pair, expressed as an associative array.
	//  The value may itself be an arbitrarily complex data structure; customarily
	//  if it is in any way multi-valued it will be an associative array or an
	//  array of associative arrays, rather than any sort of object.
	// To have any effect, $array must have elements $array['key'] and $array['value'].
	//  If $array['value'] is non-null, this sets the data value for $array['key']. 
	//  If $array['value'] is null, this clears the data value for $array['key']. 
	public function setData($array){
		if (isset($array['key']) && isset($array['value'])){			
			if (is_null($array['value'])){				
				if (array_key_exists($array['key'], $this->data)){					
					unset($this->data[$array['key']]);					
				}				
			} else {				
				$this->data[$array['key']] = $array['value'];				
			}			
		}	
	}
	
	// Get data returned from API call. 
	public function getData(){		
		return $this->data;		
	}
	
	// BEGIN already removed by Martin before 2019
	//private function setPrsIndex($val){
	//	$this->prsIndex = intval($val); 
	//}
	//public function getPrsIndex(){
	//	return intval($this->prsIndex);
	//}
	// END already removed by Martin before 2019

	// Private function makes sure that:
	//  $params['time'] is in the last 10 minutes and 
	//  $params['hash'] is a correct hash of the other params
	// 
	// Basically, params are stringified, then $hashkey is appended before taking an sha1 hash.
    // INPUT $params: an associative array; elements should include at least 'time' and 'hash', 
    //  anything else reasonable should be allowed.
    //  NOTE: if 'time' is missing, this will never be OK, because $time = 0 will NEVER be in the
    //   last 10 minutes. If 'hash' is missing, this will never be OK, because the hash result should
    //   never be an empty string.
    // INPUT $hashkey: an arbitrary key for an SHA-1 hash. If missing, function will always return OK.
	private static function isSigned($params, $hashkey){		
		$ok = false;		
//		$hashkey = self::API_HASH_KEY; // already removed by Martin before 2019  
		if ($hashkey){	
			$time = isset($params['time']) ? $params['time'] : 0;
	
			if (abs($time - time()) < 6000){					
				if (isset($params['hash'])){	
					$hash = isset($params['hash']) ? $params['hash'] : '';						
					unset ($params['hash']);	
					ksort($params);	
					$str = '';
					
					// Build up the key-value pairs as we would in the query portion of a URL.
					foreach ($params as $key => $value){	
						if (strlen($str)){
							$str .= '&';
						}
	
						$str .= $key . '=' . $value;	
					}
	
					if (strlen($str)){	
						if (sha1($str . $hashkey) == $hash){
							$ok = true;	
						}	
					}	
				}	
			}	
		}
	
		return $ok;	
	}

    // Private static function doAuthenticate builds on isSigned to do further authentication. 
    // >>>00001 JM 2019-02-18: I haven't completely studied this, but there's a special case where 
    //  keyId A887739 (which I have now moved to inc/config.php) uses Ron Skinner's credentials.
    //   >>>00004 The case that uses Ron's credentials is a big kluge: we can't get the user's credentials, 
    //   so we are just using something powerful. This is definitely something that should change.
    // Otherwise it really works out whose credentials to use and in either case can return 
    //  appropriate credentials (or return false).
    // IMPLICIT INPUT $_REQUEST. We special-case $_REQUEST['hash'], $_REQUEST['time'], and 			
	//  $_REQUEST['keyId'] to validate the rest of the request.  
    // RETURN: credentials or FALSE. credentials are in the form of an associative array with the 
    //  following elements, normally drawn from DB table customerPersonApiKey:
    //  * 'customerPersonApiKeyId': unsigned integer primary key
    //  * 'customerPersonId': unsigned integer foreign key into customerPerson table
    //  * 'apiId': unsigned integer numeric identifier of the API in question. It appears that as of
    //    2019-02, this must always be 1.
    //  * 'keyId': unsigned integer, effectively a name for the keyString (small number as name).
    //  * 'keyString': VARCHAR(64) string; hash key.
    private static function doAuthenticate(){
        $db = DB::getInstance();
		if (is_array($_REQUEST)){				
			if (isset($_REQUEST['hash']) && isset($_REQUEST['time'])){			
				$keyId = isset($_REQUEST['keyId']) ? $_REQUEST['keyId'] : '';
				$keyId = trim($keyId);
				$record = false;

				if (substr($keyId, 0, 1) == 'A'){					
					// Martin comment: kludging a bit to allow access by something that is not in customerPerson
					if ($keyId == API_KLUDGE_KEY_ID){
						$record = array();
						$record['apiId'] = API::API_ID;
                        $record['keyString'] = API_KLUDGE_KEY_STRING;
						
						$query  = "SELECT customerPersonId ";  // Was SELECT * before v2020-3
						$query .= "FROM " . DB__NEW_DATABASE . ".customerPerson ";
						$query .= "WHERE personId = " . API_KLUDGE_PERSON_ID . ";";
						$result = $db->query($query);
						if ($result) {
							if ($result->num_rows > 0){
								$row = $result->fetch_assoc();
								$record['customerPersonId'] = $row['customerPersonId']; // Martin comment: ron skinner id .. total kludge
							}
						} else {
						    $logger->errorDb('1594158199', "Hard DB error", $db);
						}
					}
				} else {
					$keyId = intval($keyId);
					if (intval($keyId)){
						$query  = "SELECT apiId, keyString, customerPersonId "; // Was SELECT * before v2020-3
						$query .= "FROM " . DB__NEW_DATABASE . ".customerPersonApiKey ";
						$query .= "WHERE keyId = " . intval($keyId) . ";";
						$result = $db->query($query);						
						if ($result) {
							if ($result->num_rows > 0){					
								$row = $result->fetch_assoc();
								$record = $row;					
							}
						} else {
						    $logger->errorDb('1594158207', "Hard DB error", $db);
						    return false;
						}
					}
				}
				
				if (!$record) {
					return false;
				}

				if (intval($record['apiId']) != API::API_ID) {
					return false;
				}
				
				$hashkey = trim($record['keyString']);
				
				if (!strlen($hashkey)) {
					return false;
				}
				
				if (self::isSigned($_REQUEST, $hashkey)) {							
					$query  = "SELECT customerId, personId "; // Was SELECT * before v2020-3
					$query .= "FROM " . DB__NEW_DATABASE . ".customerPerson ";
					$query .= "WHERE customerPersonId = " . intval($record['customerPersonId']) . ";";
                    $result = $db->query($query);						
                    if ($result) {
						if ($result->num_rows > 0){
							$row = $result->fetch_assoc();
							$c = new Customer($row['customerId']);
							return array($row['personId'], $c);
						}
                    } else {
                        $logger->errorDb('1594158381', "Hard DB error", $db);
                        return false;
					}				
				}
			}
		}
		return false;
	} // END private static function doAuthenticate
	
	// Public static function authenticate builds on private static functions isSigned and doAuthenticate.
	// If doAuthenticate returns credentials (rather than FALSE) and if $_REQUEST['action'] makes sense in 
	//  terms of there being a task_'action' class, this function constructs and returns an object of that 
	//  class with the appropriate credentials.
	// IMPLICIT INPUT $_REQUEST['action'] indicates the class we are trying to construct. E.g. if this is called
	//  via GET method and the URL includes 'action=newcreditrecord', this will create an object of 
	//  class task_newcreditrecord.
	// RETURN: an object of appropriate class with appropriate credentials.
	public static function authenticate() {		
		$creds = self::doAuthenticate();
		if ($creds !== false) {
			$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
			if (class_exists('task_' . $action)) {						
				$n = 'task_' . $action;				
				$obj = new  $n($creds[0], $creds[1]);
				return $obj;					
			}			
		}

		return false;
	}	
}
?>