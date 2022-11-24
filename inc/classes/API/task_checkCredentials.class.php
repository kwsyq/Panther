<?php 
/* task_checkCredentials.class.php

    EXECUTIVE SUMMARY: an API class. See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_checkCredentials extends API {
	
    // inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
	// Typically constructed for current user, but no default to make it so. 
	// Constructor can optionally take a personId & a customer object to set user.
	// INPUT $personId: unsigned integer, primary key into DB table Person.
	// INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		parent::__construct($personId, $customer);	
	}
	
	// EFFECTIVE RETURN is via setStatus and setData
	// Always sets status to 'success'.
	// Sets data as single key-value pair with key 'user' and value an associative array with elements:
	// * 'firstName': user's first name
	// * 'lastName': user's last name
	// * 'level': either 'regular' or 'admin'
	// >>>00002, >>>00017 behavior on failure must fall out somehow, but is unclear. For example, 
	//  if $person==0, I (JM) believe this will 'firstName' and 'lastName' as empty strings, and
	//  'level' as 'regular', but it would sure make more sense to set status='fail'
	public function run(){		
		$level = 'regular';
		/* OLD CODE REMOVED 2019-03-05
		if ($this->getPersonId() == 587) { // Ron Skinner, hardcoded
			$level = 'admin';
		}
				
		if ($this->getPersonId() == 1274) { // Chris Shaw, hardcoded
			$level = 'admin';
		}
		*/
		// BEGIN NEW CODE 2019-03-05
		// Use $adminIds from config.php; as of 2019-03-05, this is Ron & Damon
		foreach($adminIds AS $adminPersonId) {
		    if ($this->getPersonId() == $adminPersonId) {
		        $level = 'admin';
		        break;
		    }
		}
		// END NEW CODE 2019-03-05
				
		$user = array();
		
		$person = new Person($this->getPersonId());
	
		$user['firstName'] = $person->getFirstName();
		$user['lastName'] = $person->getLastName();
		$user['level'] = $level;		
				
		$this->setStatus('success');
		$this->setData(array('key' => 'user', 'value' => $user));
	} // END public function run	
}

?>