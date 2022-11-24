<?php 
/* inc/classes/API/task_signin.class.php

    EXECUTIVE SUMMARY: an API class. Validate a username-password pair 

    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_signin extends API {
	
    // Inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
    // Typically constructed for current user, but no default to make it so. 
    // Constructor can optionally take a personId & a customer object to set user.
    // INPUT $personId: unsigned integer, primary key into DB table Person.
    // INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		$this->customer = $customer; // >>>00017: seems odd, because we could get this from parent		
		parent::__construct($personId, $customer);	
	}	
	
	// INPUT $_REQUEST['username']
	// INPUT $_REQUEST['password']
	//
	// We look in DB table Person for a row with the specified username and the 
	// current customer (as of 2018-06, always SSS). We then hash the password, 
	// and see whether it validates against the hash and salt from the DB
	//
    // EFFECTIVE RETURN is via setStatus and setData.    
	// On any failure (including lack of a password match), status='fail'.
	// On success, including password status='success'; data returned is an arbitrary somekey='somevalue'.
	//  so the data here really doesn't matter.
	public function run() {
	    global $logger; // added 2020-05-04 JM
	    
		$this->setStatus('fail');
		
		$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
		$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
		
		$username = trim($username);
		$password = trim($password);
		
		$db = DB::getInstance();
		
		$record = false;
		if (strlen($username) && strlen($password)) {
		    /* BEGIN REPLACED 2020-05-04 JM, getting completely rid of old person.password in favor of person.pass; adding logging, etc. 
			$query  = " select * "; // Really could be "select pass, salt, personId", that's all we use
			$query .= " from " . DB__NEW_DATABASE . ".person ";
			$query .= " where customerId = " . intval($this->customer->getCustomerId()) . " ";
			$query .= " and username = '" . $db->real_escape_string($username) . "' limit 1 ";
	
			if ($result = $db->query($query)) {
				if ($result->num_rows > 0) {
					$record = $result->fetch_assoc();
				}
			}
				
			if ($record){
				$secure = new SecureHash();
				if ($secure->validate_hash($password, $record['pass'], $record['salt'])) {
					$this->setStatus('success');
					$this->setData(array('key' => 'somekey', 'value' => 'somevalue'));					
				}			
			}		
			// END REPLACED 2020-05-04 JM
			*/
			//BEGIN REPLACEMENT 2020-05-04 JM
			$query  = "SELECT pass, salt, personId ";
			$query .= "FROM " . DB__NEW_DATABASE . ".person ";
			$query .= "WHERE customerId = " . intval($this->customer->getCustomerId()) . " ";
			$query .= "AND username = '" . $db->real_escape_string($username) . "' LIMIT 1 ";
	
			$result = $db->query($query);
			if ($result) {
				if ($result->num_rows > 0) {
					$record = $result->fetch_assoc();
				} else {
				    $logger->warn2('1588611255', "Bad API login. customerId = {$this->customer->getCustomerId()}, username = '$username'. No such person.");
				}
			} else {
			    $logger->errorDb('1588611261', "Hard DB error", $db);
			}
				
			if ($record){
				$secure = new SecureHash();
				if ($secure->validate_hash($password, $record['pass'], $record['salt'])) {
					$this->setStatus('success');
					$this->setData(array('key' => 'somekey', 'value' => 'somevalue'));					
				} else {
				    $logger->warn2('1588611268', "Bad API login. customerId = {$this->customer->getCustomerId()}, username = '$username', " .
				        "personId = {$record['personId']}. Bad password given,");
				}
			}		
			//END REPLACEMENT 2020-05-04 JM
		}
	} // END public function run	
}

?>