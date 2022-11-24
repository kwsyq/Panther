<?php
/* Register.class.php

   EXECUTIVE SUMMARY: Registering a new user. >>>00001: Per discussions with Martin, though,
   this may not be the way this is really done 2019-03.
   
   * Public methods
   ** __construct(Customer $customer = null)
   ** getErrors()
   ** register($data = null)
*/

class Register {	
	private $db;
	private $data;
	private $errors;
	private $customer;
	
	// INPUT $customer: Customer object. While theoretically this is optional
	//  all efforts to register will certainly fail in the absence of a Customer.
	public function __construct(Customer $customer = null) {
		$this->db = DB::getInstance();
		$this->errors = array();
		$this->customer = $customer;		
	}
	
	// RETURN private member $errors to see list of errors after a failure.
	// Returns an associative array. Possible elements are
    //  * 'username', an array of strings; possible strings include (but are not limited to) 'Username must be at least 8 characters', 'Username is already taken!'.
    //  * 'password', an array of strings; possible strings include (but are not limited to) 'Password is too short', 'Passwords don\'t match'.
    //  * 'email', an array of strings; possible strings include (but are not limited to) 'Email is not valid', 'Email address is taken already'
    //  * 'insert failed': only possible value, if present, is 'yes'. 
	public function getErrors() {
		return $this->errors;
	}

	// INPUT $data: associative array, should have members:
	//  * 'username': typically an email address
	//  * 'password' and 'passwordconfirm' should be identical strings
	//  * 'email': always an email address, typically the same as username. 
	// ACTION: validates the arguments (saving error info if there is any, see getErrors), 
	//  and if everything is OK, it inserts appropriate rows in the Person and PersonEmail 
	//  database table. 
	// RETURNs true on success (and echoes 'hey hey'), returns false on error.
	public function register($data = null) {		
		$this->data = array();		
		if (is_array($data)){
			$this->data = $data;
		}
		
		$val = new Validate($this->customer);
		
		$username = isset($this->data['username']) ? $this->data['username'] : '';
		$password = isset($this->data['password']) ? $this->data['password'] : '';
		$passwordconfirm = isset($this->data['passwordconfirm']) ? $this->data['passwordconfirm'] : '';
		$email = isset($this->data['email']) ? $this->data['email'] : '';
		
		try {
			$username = $val->username($username);
		} catch (Exception $e) {
			if ($e->getMessage() == 'too short'){
				$this->errors['username'][] = 'Username must be at least 8 characters';
			}
			if ($e->getMessage() == 'too long'){
				$this->errors['username'][] = 'Username too long';
			}
			if ($e->getMessage() == 'bad chars'){
				$this->errors['username'][] = 'Username can only contain letters numbers and underscore';
			}
			if ($e->getMessage() == 'taken'){
				$this->errors['username'][] = 'Username is already taken!';
			}
		}
		
		try {
			$password = $val->password($password, $passwordconfirm);
		} catch (Exception $e) {
			if ($e->getMessage() == 'too short'){
				$this->errors['password'][] = 'Password is too short';
			}
			if ($e->getMessage() == 'too long'){
				$this->errors['password'][] = 'Password is too long';
			}
			if ($e->getMessage() == 'dont match'){
				$this->errors['password'][] = 'Passwords don\'t match';
			}				
		}
		
		try {
			$email = $val->email($email);
		} catch (Exception $e) {
			if ($e->getMessage() == 'not valid'){
				$this->errors['email'][] = 'Email is not valid';
			}
			if ($e->getMessage() == 'too long'){
				$this->errors['email'][] = 'Email too long';
			}
			if ($e->getMessage() == 'taken'){
				$this->errors['email'][] = 'Email address is taken already';
			}				
		}
		
		if (count($this->errors)) {
			return false;
		} else {
			$inserted = false;			
			$secure = new SecureHash();
			$salt = ''; // blank salt here tells $secure->create_hash to create a salt; in the next line, $salt is passed by reference. 
			$encrypted = $secure->create_hash($password, $salt);
			
			if ($this->customer) {
			    // >>>00028 JM suspects the two inserts here should be in a single transaction.
				if (intval($this->customer->getCustomerId())) {					
					$query = " insert into " . DB__NEW_DATABASE . ".person (";
					$query .= " customerId ";
					$query .= " ,username ";
					$query .= " ,pass ";
					$query .= " ,salt ";
					$query .= " ) values ( ";
					$query .= " " . intval($this->customer->getCustomerId()) . " ";
					$query .= " ,'" . $this->db->real_escape_string($username) . "'";
					$query .= " ,'" . $this->db->real_escape_string($encrypted) . "'";
					$query .= " ,'" . $this->db->real_escape_string($salt) . "'";
					$query .= ")";

					$this->db->query($query);
					
					$id = $this->db->insert_id;

					if (intval($id)) {						
						$inserted = true;
						
						$query = " insert into " . DB__NEW_DATABASE . ".personEmail (";
						$query .= " personId ";
						$query .= " ,emailAddress ";
						$query .= " ) values ( ";
						$query .= " " . intval($id) . " ";
						$query .= " ,'" . $this->db->real_escape_string($email) . "' ";
						$query .= ")";
						
						$this->db->query($query); // >>>00002 JM: looks like failure on this insert would not report an error						
					}					
				} // >>>00002 else invalid customer, should probably somehow report that				
			} // >>>00002 else no customer, should probably somehow report that			

			if ($inserted) {
				echo "hey hey";
				return true;				
			} else {				
				$this->errors['insert failed'] = 'yes';
				return false;				
			}			
		}
	} // END public function register
}

?>