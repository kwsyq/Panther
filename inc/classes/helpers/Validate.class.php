<?php
/*  Validate.class.php

    EXECUTIVE SUMMARY: other classes can use this to determine the validity of their inputs.
    As of 2019-03-08, this looks like it is mostly about new-user registration, but 
    // >>>00006 we could do a lot more here.
    
    * Public methods
    * __construct(Customer $customer = null)
    * verifyDate($date, $strict = true, $format = 'Y-m-d H:i:s')
    * username($val)
    * password($val,$confirm)
    * email($val)
*/

class Validate {	
	private $db;
	private $customer;

	// INPUT $customer: optional, Customer object. If non-null and nonzero, 
	//  provides context for validation by rejecting (for example) usernames
	//  that are already taken
	public function __construct(Customer $customer = null) {
		$this->customer = $customer;
		$this->db = DB::getInstance();		
	}

    // INPUT $date: date & time, formatted per $format
    // INPUT $strict: whether to check for PHP warnings
    // INPUT $format: format, default 'Y-m-d H:i:s'
    //
    // Unlike other methods here, doesn't throw exceptions 
    // RETURNs a Boolean. If not $strict, and $date (which should be an integer) 
    //  and the format matches, returns true, otherwise false. If $strict, we  
    //  further check for PHP warnings, return false if there were any.
	public function verifyDate($date, $strict = true, $format = 'Y-m-d H:i:s') {
		$dateTime = DateTime::createFromFormat($format, $date);

		if ($strict) {
			$errors = DateTime::getLastErrors();
			if (!empty($errors['warning_count'])) {
				return false;
			}
		}
		return $dateTime !== false;
	}	
	
	// INPUT $val: proposed username.
	// Throws exception if too short, too long, or contains invalid character; 
	//  also (assuming customer has been provided to the constructor) if name 
	//  is already taken.
	public function username($val) {		
		$val = trim($val);
		
		if (strlen($val) < 8){
			throw new Exception('too short');
		}
		
		if (preg_match('/[^A-Za-z0-9_]/', $val)) {
			throw new Exception('bad chars');
		}

		if (strlen($val) > 32) {
			throw new Exception('too long');
		}
		
		$query  = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".person ";
		$query .= " where username = '" . $this->db->real_escape_string($val) . "' ";
		$query .= " and customerId = " . intval($this->customer->getCustomerId());
		
		
		if ($result = $this->db->query($query)) {  // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				throw new Exception('taken');
			}
		} // >>>00002 else ignores failure on DB query! Does this throughout file, 
          // haven't noted each instance.
		
		return $val;		
		
	}

	// INPUT $val, $confirm: proposed password. These must match each other.
	// Throws exception if too short, too long, or if $val and $confirm don't match.
	// >>>00006 A bit more enforcement would make sense (e.g. this will accept '11111111' as a password).
	public function password($val,$confirm) {	
		if (strlen($val) < 8){
			throw new Exception('too short');
		}
	
		if (strlen($val) > 32){
			throw new Exception('too long');
		}

		if ($val != $confirm){
			throw new Exception('dont match');
		}
		
		return $val;	
	}
	
	// INPUT $val: email address. Tests for:
	//   * too long
	//   * not shaped like an email address
	//   * if $this->customer is non-null, non-zero: already having a matching personEmail for some
	//  personId that matches this customer. 
	//  
	//  >>>00006 really could have a second parameter for whether we want that "customer" test.
	// 
	// Throws an exceptions when it encounters invalid content.
	public function email($val) {	
		$val = trim($val);
		
		if (strlen($val) > 255){
			throw new Exception('too long');
		}
		
		if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
			throw new Exception('not valid');
		}
	
		$query  = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".personEmail pe ";
		$query .= " join " . DB__NEW_DATABASE . ".person p on pe.personId = p.personId ";
		$query .= " where pe.emailAddress = '" . $this->db->real_escape_string($val) . "' ";
		$query .= " and p.customerId = " . intval($this->customer->getCustomerId()) . " ";
		
		if ($result = $this->db->query($query)) {  // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0){
				throw new Exception('taken');
			}
		}
		
		return $val;		
	} // END public function email
}


?>