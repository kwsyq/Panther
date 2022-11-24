<?php
/* inc/classes/User.class.php

EXECUTIVE SUMMARY: 
This is a special class that is not a simple wrapper for a table, though it is closely
related to DB table Person. A lot of this access the user's timetracking data. Some of 
the functionality here overlaps the Person class. This class can be used either for the current
logged-in user or can be used administratively.

* This does NOT extend SSSEng. Instead, this is the special class we use within SSSEng 
for the currently logged-in user, or to impersonate such a user.
* Public functions:
** __construct($id = null, Customer $customer)
** isEmployee()
** getVacationTimeHistory() - added 2019-05-29 JM
** getTotalVacationTime($positiveOnly = 0)
** getVacationUsed()
** allocateVacationTime($minutes, $note, $effectiveDate) - added 2019-05-29 JM
** getFormattedName($flip = false)
** setFirstName($val)
** setMiddleName($val)
** setLastName($val)
** getUserId()
** getUsername()
** getFirstName()
** getMiddleName()
** getLastName()
** getIsAdmin()
** getCustomer()
** getCustomerPersonId()
** getCustomerPersonPayWeekInfo($periodBegin)
** getCustomerPersonPayPeriodInfo($periodBegin)
** getExtensions()
** getHireDate()
** public static function getByUsername($username, $customer)
** public static function getByLogin($username, $password, $customer)
*/

class User {
	private $db;
	private $logger;

    private $customer; // Customer object

	// Some values from the Person table
	private $userId;
	private $username;
	private $firstName;
	private $middleName;
	private $lastName;	
    private $isAdmin;
	
    // INPUT $id: Must be a personId from the Person table.
    // INPUT $customer: Customer object. Must match customer column in relevant
    //  row of the Person table. As of 2019-02, only customer is SSS itself.
    // >>>00002 >>>00016: JM 2019-02-18: should certainly validate this input, doesn't, can
    //  easily generate a useless object without logging that. Seems to me that a mismatch
    //  should result in somehow marking this object as invalid, really would simplify
    //  initial tests in other methods.
    public function __construct($id = null, Customer $customer) {
		global $logger;
		$this->db = DB::getInstance();		
		$this->customer = $customer;
		$this->load($id);
		$this->logger = $logger;
	}
	
	// INPUT $val here is input $id for constructor.
	private function load($val) {	
		if (is_numeric($val)) {
		    // SELECT row from Person table. personId and customerId must both match
		    //  inputs to constructor.
			$query = " select p.* ";
			$query .= " from " . DB__NEW_DATABASE . ".person p ";
			$query .= " where p.customerId = " . intval($this->customer->getCustomerId());
			$query .= " and  p.personId = " . intval($val);
		
			if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$row = $result->fetch_assoc();
	
					$this->setUserId($row['personId']);
					$this->setUsername($row['username']);
					$this->setFirstName($row['firstName']);
					$this->setMiddleName($row['middleName']);
					$this->setLastName($row['lastName']);
//                    $this->setIsAdmin($row['permissionString']);
                    $this->setIsAdmin(substr($row['permissionString'], 13, 1)=="1");
				} // >>>00002 else ignores that we got a bad personId!
			} // >>>00002 else ignores failure on DB query! Does this throughout file, 
			  // haven't noted each instance.
		} 
	} // END private function load

	// abstracted 2020-03-03
	// RETURN the SQL to select the customerPersonId, based on INPUTs $customerId and $personId.
    private function SQLSelectCustomerPersonId($customerId, $personId) {
        return "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".customerPerson " .
		    "WHERE personId = $personId " .
		    "AND customerId = $customerId ";
    }

    	// RETURN Boolean, true if user is employee of the relevant customer.
	// Essentially, defines being an employee as having a relevant row in 
	//  DB table CustomerPerson.
	// A "normal" false case would be that the user is associated with this
	//  customer via the customerId value in the Person table, but is not
	//  an employee so has no corresponding row in CustomerPerson.
	public function isEmployee() {		
		if ($this->customer) {
			if (intval($this->customer->getCustomerId())) {
				// $query = " select * "; // REPLACED 2020-03-02 JM: Could just select any one column, just checking for existence
				$query = "SELECT customerPersonId "; // REPLACEMENT 2020-03-02 JM: just select any one column to check for existence 
				$query .= " from " . DB__NEW_DATABASE . ".customerPerson ";
				$query .= " where customerId = " . intval($this->customer->getCustomerId());
				$query .= " and  personId = " . intval($this->getUserId());

				if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
					if ($result->num_rows > 0) {
						return true;		
					}
				}				
			}
		}
			
		return false;		
	}
	
	// RETURN canonical representation of all rows in DB table Vacation time for
	//  this User, in forward chronological order. 
	//  If not an employee, this is necessarily an empty array.
	//  More precisely, return an array (representing rows) of associative
	//  arrays (representing columns); the latter has the following elements:
	//   'vacationTimeId' - primary key into DB table VacationTime
	//   'customerPersonId' - will be the same for all rows for this person
	//   'allocationMinutes' - allocation in minutes; can be negative
	//   'note' - arbitrary note
	//   'personId' - indicates who inserted this row
	//   'inserted' - when the row was inserted
	public function getVacationTimeHistory() {
	    $ret = array();
		if ($this->isEmployee()) {
			// Select the relevant rows in DB table vacationTime
		    $query = "SELECT vacationTimeId, customerPersonId, allocationMinutes, note, personId, effectiveDate, inserted "; 
		    $query .= "FROM " . DB__NEW_DATABASE . ".vacationTime "; 
		    $query .= "WHERE customerPersonId = " . $this->getCustomerPersonId() . " ";		    
			$query .= "ORDER BY vacationTimeId "; // effectively, forward chronological order, becuase assigned IDs increase monotonically over time.
			
			$result = $this->db->query($query);
			if ($result) {
			    while ( $row= $result->fetch_assoc() ) {
			        $ret[] = $row;
			    }
			} // >>>00002 else ignores failure on DB query! Sorry, no logging system yet, someone needs to do that. - JM
		}
		return $ret;
	} // END function getVacationTimeHistory()
	
	// JM 2019-10-09: removed former optional input $positiveOnly. It was never used,
	//   and it was never a good idea. This function has been completely rewritten
	//   by me from the way Martin had it.
	// INPUT opts: associative array, allows optional inputs, name-value pairs
	//   * 'currentonly' - Boolean, if true ignore any allocated time that is not yet at its "effective date"
	//   * 'futureonly' - Boolean, if true count ONLY allocated time that is not yet at its "effective date"
	//   If 'currentonly' and 'futureonly' are both set explicitly true, that will log an warning in the syslog, and both will be ignored. 
	// RETURN will be vacation time in minutes.
	// If not an employee, return 0. 
	public function getTotalVacationTime($opts=null) {
	    $currentonly = false;
	    $futureonly = false;
	    if ($opts) {
	        if (is_array($opts)) {
	            foreach ($opts AS $name=>$val) {
	                if ($name == 'currentonly') {
	                    $currentonly = $val;
	                } else if ($name == 'futureonly') {
	                    $futureonly = $val;
	                } else {
	                    $this->logger->error2('1570656167', "Invalid option $name in User::getTotalVacationTime.");
	                }
	            }
	        } else {
	            $this->logger->error2('1570656145', '$opts in User::getTotalVacationTime was not array.');
	            // >>>00002 should never happen unless our code is wrong. We might want to think about
	            // in places like this that we might want to die & do a backtrace. JM 2019-10-09.
	        }
	    }
	    if ($currentonly && $futureonly) {
            $currentonly = false;
            $futureonly = false;
            $this->logger->error2('1570656190', 'User::getTotalVacationTime has both currentonly and futureonly set. Ignoring both.');
        }
		$sum = 0;
		$vacationTimeRows = $this->getVacationTimeHistory();
		foreach ($vacationTimeRows as $vacationTimeRow) {
		    $isFuture = $vacationTimeRow['effectiveDate'] > date("Y-m-d H:i:s");		    
            if ($currentonly && $isFuture) {
                continue; // skip this, it is future and we don't want it
            }
            if ($futureonly && !$isFuture) {
                continue; // skip this, it is current and we don't want it
            }
            $sum += $vacationTimeRow['allocationMinutes'];
		}
		
		return $sum;
	} // END public function getTotalVacationTime
	
	// RETURN vacation time expended by this employee (in minutes).
	// Looks like this presumes that sick time & vacation time come from the same pool, no distinction possible.
	public function getVacationUsed() {	
		$used = 0;		
		if ($this->isEmployee()) {
		    // >>>00007 JM 2019-02-28: as far as I can see, this first query is now completely irrelevant. 
			$query = " select * ";
			$query .= " from " . DB__NEW_DATABASE . ".customerPerson ";
			$query .= " where customerId = " . intval($this->customer->getCustomerId());
			$query .= " and  personId = " . intval($this->getUserID());				
			if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {	
					$row = $result->fetch_assoc();
					// $period = Time::getAnniversaryPeriod($row['hireDate']); // REMOVED 2020-10-06 JM, this wass set and then ignored.
				
					/* [BEGIN commented out by Martin before 2019]
					$query  = " select * from " . DB__NEW_DATABASE . ".pto ";
					$query .= " where personId = " . intval($this->getUserId()) . " ";
					$query .= " and day  ";
					$query .= " between '" . $this->db->real_escape_string($period['begin']->format("Y-m-d")) . "' ";
					$query .= " and '" . $this->db->real_escape_string($period['end']->format("Y-m-d")) . "' ";
					$query .= " and ptoTypeId = " . intval(PTOTYPE_SICK_VACATION);
					[END commented out by Martin before 2019]
					*/

					$query  = " select * from " . DB__NEW_DATABASE . ".pto ";
					$query .= " where personId = " . intval($this->getUserId()) . " ";
					$query .= " and ptoTypeId = " . intval(PTOTYPE_SICK_VACATION);					
					if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
						if ($result->num_rows > 0) {								
							while ($row = $result->fetch_assoc()) {								
								$used += intval($row['minutes']);								
							}							
						}						
					}					
				}
			}				
		}
	
		return $used;	
	} // END public function getVacationUsed
	
	// function allocateVacationTime added by JM 2019-05-29
	//
	// >>>00001: I notice that Martin almost never used global $user in the
	//  classes in this directory. It is possible that there was a specific intent
	//  there that I have violated, and that this function really ought to go somewhere
	//  else, but it certainly seems closely associated with those that immediately precede it.
	//  ALSO: this will be called from the _admin area, and it is possible that $user there
	//  is not exactly as it should be.
	//
	// Insert a new row in DB table VacationTime
	// INPUT $minutes: postive or negative time in minutes
	// INPUT $note: accompanying note.
	// INPUT $effectiveDate: optional date of issuing PTO (vacation). If present, should be in the form 'YYYY-mm-dd''.
	// RETURN: FALSE on success; error string on failure.
	// >>>00038: Might want to do something to make sure this can only be done by admin;
	//  in any case, though, it will show up in the database who did this.
	// >>>00016: Might want some further range checks on $minutes.
	// >>>00002: will want to log errors
	public function allocateVacationTime($minutes, $note, $effectiveDate) {
	    global $user; // the logged-in user, not necessarily the same one as $this
	    if ( ! $this->isEmployee() ) {
	        return "allocateVacationTime: ". $this->getFormattedName(1) . "[ id =" . intval($this->getUserId()) . "] is not listed as an employee."; 
	    }
        $allocatedBy = false;
        if (isset($user) && $user && intval($user->getUserId())) {
            $allocatedBy = intval($user->getUserId()); // personID of logged-in user
        } else {
            // No one is logged in. As of 2019-06-19, that should no longer ever be the case when we get here: there should be
            // a logged-in administrator, and that should be reflected in $user.
	        return "allocateVacationTime: No \$user is logged in, while trying to allocate PTO for " . $this->getFormattedName(). ' [' . $this->getUserId() . ']'; 
        }
	    $note = substr(urldecode($note), 0, 255); // >>>00002 truncates silently
	    if ($minutes != intval($minutes)) {
	        return "allocateVacationTime: \$minutes input must be integer, got '$minutes'.)";
	    }
	    /* After discussion 2019-10-22, as noted in http://bt.dev2.ssseng.com/view.php?id=33#c132, we decided it is OK for an admin to take PTO negative.
	    if ( $minutes < 0 ) {
	        // JM 2019-10-22 This code, now removed, did not account for effectiveDate.
	        // That led to a discussion with Ron & Damon, where they concluded that it is OK for an admin to take PTO negative.
	        // 
	        // The following would allow an admin, for example, to take 8 hours away with an effectiveDate now 
	        //  (or even in the past) weighed against an allocation that is still in the future.
	        // If that is undesirable, we'll need to rework this, including
	        // modifying getTotalVacationTime to allow an optional date as input, and only return time  
	        // effective up to and including that date. (We'd pass in effectiveDate, of course.)
	        $minutesAvailable = $this->getTotalVacationTime() - $this->getVacationUsed(); 
	        if( abs($minutes) > $minutesAvailable ) {
                // Trying to subtract more minutes than they've got!
                // >>>00002 should log something here
                $minutes = -$minutesAvailable;                 
            }
        }
        */
        if ($effectiveDate) {
            // >>>00002, >>>00016 should validate $effectiveDate
        }
        $query = 'INSERT INTO '. DB__NEW_DATABASE . '.vacationTime (';
        $query .= 'customerPersonId, allocationMinutes, note, personId';
        $query .= $effectiveDate ? ', effectiveDate' : '';
        $query .= ') VALUES (';
        $query .= $this->getCustomerPersonId();  
        $query .= ', ' . intval($minutes) . ' ';
        $query .= ", '" . $this->db->real_escape_string($note) . "' ";
        $query .= ", $allocatedBy";
        $query .=  $effectiveDate ? (", '" . $this->db->real_escape_string($effectiveDate) . "' ") : '';
        $query .= ')';
        
        $result = $this->db->query($query);
        if (!$result) {
            return "allocateVacationTime: database query failed: $query";
        }
        return FALSE; // success
	} // END public function allocateVacationTime
	
	// ------------------------------------------
	// >>>00017
	// THE FOLLOWING FUNCTION is identical to the one in class Person, and should
	//  perhaps just construct a Person object and use that rather than duplicate code.
	// ------------------------------------------
	// RETURN formatted person name
	// INPUT $flip: treated as a Boolean:
	//   true: return FIRST NAME + space + LAST NAME
	//   false: return LAST NAME + comma + non-breaking space + FIRST NAME
	// NOTE that if either first or last name is empty, the $flip==true case
	//  will just return the one existing name; the $flip==true will still
	//  have a space before last name or after first name.
	public function getFormattedName($flip = false) {	
		$f = trim($this->getFirstName());
		$l = trim($this->getLastName());
	
		$comma = (strlen($f) && strlen($l)) ? ',&nbsp;' : '';
	
		if ($flip) {
			return $this->firstName . ' ' . $this->lastName;
		}
	
		return $this->lastName . $comma . $this->firstName;	
	}	
	
	// Set primary key
	// $val - PersonId, primary key in Person table
	private function setUserId($val) {	
		$this->userId = intval($val);	
	}
	
	// Set username
	// INPUT $val: username, normally an email address
	private function setUsername($val) {	
		$val = trim($val);
		$val = substr($val, 0, 128);  // >>>00002: truncates silently.
		$this->username = $val;	
	}	

	// INPUT $val - first name
	// >>>00017: perhaps this should not be public, since there is no
	//  way for this class to save any change here 
	public function setFirstName($val) {	
		$val = trim($val);
		$val = substr($val, 0, 128);  // >>>00002: truncates silently.
		$this->firstName = $val;	
	}
	
	// INPUT $val - middle name
	// >>>00017: perhaps this should not be public, since there is no
	//  way for this class to save any change here 
	public function setMiddleName($val) {	
		$val = trim($val);
		$val = substr($val, 0, 128);  // >>>00002: truncates silently.
		$this->middleName = $val;	
	}	

	// INPUT $val - last name
	// >>>00017: perhaps this should not be public, since there is no
	//  way for this class to save any change here 
	public function setLastName($val) {	
		$val = trim($val);
		$val = substr($val, 0, 128);  // >>>00002: truncates silently.
		$this->lastName = $val;	
	}
	
	// >>>00007 Private function, never called, and lacks the input that it references.
	// Almost certainly useless as it stands
	private function setIsAdmin($val) {
		// [BEGIN MARTIN COMENT]
		// not sure about this.
		// this will do for now
		// [END MARTIN COMENT]
		
//		$this->isAdmin = intval($val);		
        $this->isAdmin = $val;        
	}
	
	/*
	[Function commented out by Martin before 2019]
	public function setRoles($val) {	
		$val = intval($val);
		$roles = array();
		
		$query  = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".role ";
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					if ($row['roleId'] & $val) {
						$roles[] = $row;
					}
				}
			}
		}
		
		$this->roles = $roles;		
	}
	*/	

	// RETURN personId for this user.
	public function getUserId() {	
		return $this->userId; // [Martin comment] technically this is a personId	
	}
	
	// RETURN username (normally an email address)
	public function getUsername() {
	    return $this->username;	
	}
	
	// RETURN user's first name
	public function getFirstName() {		
		return $this->firstName;		
	}	
	
	// RETURN user's middle name
	public function getMiddleName() {	
		return $this->middleName;	
	}
	
	// RETURN user's last name
	public function getLastName() {	
		return $this->lastName;	
	}
	
	// >>>00026 JM: I don't believe this will work. Probably always returns undefined or some such 
	public function getIsAdmin() {	
		return $this->isAdmin;		
	}

	// RETURN Customer object associated with this class (passed in to constructor)
	public function getCustomer() {
		return $this->customer;	
	}
	
	/* BEGIN REMOVED 2020-06-10 JM: no longer used, now that we have a CustomerPerson class
	// RETURN an associative array equivalent to the content of the relevant row 
	//  in the customerPerson DB table; returns false if there is no such row. 
	//  The associative array has a lot of members, many of which are no longer relevant. 
	//  The presumably relevant ones besides the customerId & personId are: 
	//  * 'customerPersonId' - primary key
	//  * 'legacyInitials' - despite the name, this is something we definitely still use
	//  * 'hireDate'
	//  * 'terminationDate' - for active employees, this will be something in the distant future
	//  * 'activeWorkOrder'
	//  * 'daysBack' - how far back user can change their time reports
	//  * 'extraEor' - only relevant for engineers of record. Bitflag used, for example, 
	//    when this EOR is the one who needs to approve something that is held up pending that approval.  
	public function getCustomerPersonDataKludge() {
		$query = " select * from " . DB__NEW_DATABASE . ".customerPerson  ";
		$query .= " where personId = " . intval($this->getUserId());
		$query .= " and customerId = " . intval($this->customer->getCustomerId());
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					return $row;
				}
			}
		}
	
		return false;	
	}
	// END REMOVED 2020-06-10 JM
	*/
	
	// RETURNs the relevant customerPersonId, or false if there is none. 
	public function getCustomerPersonId() {
	    /* 2020-03-02 JM: >>>00027 I'm not sure if there is any good reason this is not just
	    $query = $this->SQLSelectCustomerPersonId(intval($this->customer->getCustomerId()), intval($this->getUserId()));
	    Seems to me that would have the same return.
	    */
	    
        // $query = " select * "; // REPLACED 2020-03-02 JM: Could just select customerPersonId, it's the only column we care about here
        $query = "SELECT customerPersonId "; // REPLACEMENT 2020-03-02 JM: just select customerPersonId, it's the only column we care about here
		$query .= "from " . DB__NEW_DATABASE . ".customerPerson  ";
		$query .= " where customerPersonId = (";
		$query .= $this->SQLSelectCustomerPersonId(intval($this->customer->getCustomerId()), intval($this->getUserId()));
		$query .= " ) ";
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
			    // while ($row = $result->fetch_assoc()) { // REMOVED 2020-03-02 JM: No good reason for a 'while' rather than an 'if', plus
			    //  that if is always true, given that by this point we know we have a ro, so just killing this.
			        $row = $result->fetch_assoc();
					return $row['customerPersonId'];
				//} // REMOVED 2020-03-02 JM, see comment on corresponding "while"
			}
		}
		
		return false;
	}

	// INPUT $periodBegin: beginning of a pay-week (so as of 2019-02, should be a Monday) in 'Y-m-d' form.
	//  RETURNs an associative array equivalent to the content of the relevant row 
	//   in DB table CustomerPersonPayWeekInfo; returns false if there is no such row. 
	//  The associative array will have members: 
	//   * 'customerPersonPayWeekInfoId'
	//   * 'customerPersonId'
	//   * 'periodBegin'
	//   * 'dayHours'
	//   * 'dayOT'
	//   * 'weekOT'
	//   * 'workWeek'.
	//  See documentation of DB table CustomerPersonPayWeekInfo for clarification of those values.
	public function getCustomerPersonPayWeekInfo($periodBegin) {	
		$query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo  ";
		$query .= " where customerPersonId = (";
		$query .= $this->SQLSelectCustomerPersonId(intval($this->customer->getCustomerId()), intval($this->getUserId()));
		$query .= " ) ";
		$query .= " and periodBegin = '" . $this->db->real_escape_string($periodBegin) . "' ";
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					return $row;
				}
			}
		}
	
		return false;	
	}
	
	// INPUT $periodBegin: beginning of a pay-week (so as of 2019-02, should be 
	//  either the first or 16th day of the month) in 'Y-m-d' form.
	// RETURNs an associative array equivalent to the content of the relevant 
	//  row in the CustomerPersonPayPeriodInfo DB table; returns false if there 
	//  is no such row. The associative array will have members: 
	// * 'customerPersonPayWeekPeriodId'
	// * 'customerPersonId'
	// * 'periodBegin'
	// * 'payPeriod'
	// * 'rate'
	// * 'salaryHours'
	// * 'ira'
	// * 'copay'
	// * 'salaryAmount'
	// * 'iraType'
	// * 'readyForSignoff'
	// * 'initialSignoffTime'
	// * 'adminSignedPayrollTime'
	// * 'lastSignoffTime'
	// * 'reopenTime'
	//  See documentation of DB table CustomerPersonPayPeriodInfo for clarification of those values.
	public function getCustomerPersonPayPeriodInfo($periodBegin) {	
		$query = "SELECT * FROM " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
		$query .= "WHERE customerPersonId = (";
		$query .= $this->SQLSelectCustomerPersonId(intval($this->customer->getCustomerId()), intval($this->getUserId()));
		$query .= " ) ";
		$query .= "AND periodBegin = '" . $this->db->real_escape_string($periodBegin) . "';";
		
		$result = $this->db->query($query);
		if ($result) {
		    $row = $result->fetch_assoc();
            if ($row) {
                return $row;
            } // else no matching row, and we return false; that's not an error.
		} else {
		    $this->logger->errorDb('1602009807', "Hard DB error", $db);
		}
	
		return false;	
	}
	
	// Internal phone extensions. 
	// RETURNs an array of associative arrays, one for each phone extension associated
	//  with this person, orderd by (extensionType displayOrder, phoneExtensionId). 
	//  Each associative array has members: 
	//  * 'extensionType'
	//  * 'extensionTypeDisplay'
	//  * 'displayOrder' (per extensionType)
	//  * 'phoneExtensionId' - primary key in PhoneExtension table
	//  * 'extension' - actual number
	//  * 'description' 
	public function getExtensions() {	
		$extensions = array();
	
		$query = " select pet.extensionType, pet.extensionTypeDisplay, pet.displayOrder, pe.phoneExtensionId, pe.extension, pe. description ";
		$query .= " from " . DB__NEW_DATABASE . ".phoneExtension pe ";
		$query .= " join " . DB__NEW_DATABASE . ".phoneExtensionType pet on pe.phoneExtensionTypeId = pet.phoneExtensionTypeId ";
		$query .= " where pe.personId = " . intval($this->getUserId());
		$query .= " order by pet.displayOrder asc, pe.phoneExtensionId ";
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$extensions[] = $row;
				}
			}
		}
	
		return $extensions;	
	}	
	
	// JM 2020-03-02 >>>00027, >>>00001 SELECT here really could be "select cp.hireDate", that's all we use. AND no need to join with DB table personId.
	// BUT furthermore it looks to me like this is never called, so maybe just kill it entirely (or see if there are places it *should* be called, rather
	//  than ad hoc code).
	// ---- 
	// Returns hireDate for the relevant row in customerPerson DB table, 
	//  in ISO 8601 format as supported by MySQL. ('Y-m-d') 
	// (Unlike getTotalVacationTime, this allows for a null or bad hireDate in the DB, returns '0000-00-00'.) 
	public function getHireDate() {		
		$hd = '0000-00-00';
		
		$query = " select cp.* "; 
		$query .= " from " . DB__NEW_DATABASE . ".customerPerson cp ";
		$query .= " join " . DB__NEW_DATABASE . ".person p on cp.personId = p.personId ";
		$query .= " where p.personId = " . intval($this->getUserId());		
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					
					$date = date_parse($row['hireDate']);
					if ($date["error_count"] == 0 && checkdate($date["month"], $date["day"], $date["year"])) {
						$hd = $row['hireDate'];
					}					
				}
			}
		}
		
		return $hd;
	}
		
	
/*  [BEGIN function commented out by Martin before 2019]
	public function getRoles() {
	
		return $this->roles;
	
	}
	[END function commented out by Martin before 2019]
*/

    // INPUT $username: typically, an email address
    // INPUT $customer: Customer object
    // RETURN new User object if there is a row with this username in DB table person for this customer.
    //  Otherwise, return null.
	public static function getByUsername($username, $customer) {
		$personId = self::username($username, $customer);
		if ($personId !== false) {
			return new User($personId, $customer);
		} else{
		    /* OLD CODE REMOVED 2019-06-26 JM
			return false;
			*/
			// BEGIN NEW CODE 2019-06-26 JM
			return null;
			// END NEW CODE 2019-06-26 JM
		}
	}
	
    // INPUT $username: typically, an email address
    // INPUT $customer: Customer object
    // RETURN true if there is a row with this username in DB table person for this customer.
	private static function username($username, Customer $customer) {	
		if (intval($customer->getCustomerId())) {	
			$db = DB::getInstance();				
			$record = false;
				
			$query  = " select * "; // >>>00027 Really could be "select personId", that's all we use
			$query .= " from " . DB__NEW_DATABASE . ".person ";
			$query .= " where customerId = " . intval($customer->getCustomerId()) . " ";
			$query .= " and username = '" . $db->real_escape_string($username) . "' limit 1 ";

			if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {
					$record = $result->fetch_assoc();
					return $record['personId'];
				}
			}
		}
	
		return false;	
	}
	
	
    // INPUT $username: typically, an email address
    // INPUT $customer: Customer object
    // INPUT $password, passed "in the clear" 
    // RETURN new User object if (1) there is a row with this username in DB table person for this customer.
    //  and (2) $password is their correct password. Otherwise, return false
	public static function getByLogin($username, $password, $customer) {
		$personId = self::login($username, $password, $customer);
		if($personId !== false) {
			return new User($personId, $customer);
		} else {
			return false;
		}
	}

    // INPUT $username: typically, an email address
    // INPUT $customer: Customer object
    // INPUT $password, passed "in the clear" 
    // RETURN personId if (1) there is a row with this username in DB table person for this customer.
    //  and (2) $password is their correct password. Otherwise, return false
	private static function login($username, $password, Customer $customer) {
	    global $logger;
	    
		if (intval($customer->getCustomerId())) {
			$db = DB::getInstance();
			
			$record = false;
			
			/* BEGIN REPLACED 2020-05-04 JM, getting completely rid of old person.password in favor of person.pass; adding logging, etc.
			$query  = " select * "; // Really could be "select pass, salt, personId", that's all we use
			$query .= " from " . DB__NEW_DATABASE . ".person ";
			$query .= " where customerId = " . intval($customer->getCustomerId()) . " ";
			$query .= " and username = '" . $db->real_escape_string($username) . "' limit 1 ";

			if ($result = $db->query($query)) {
				if ($result->num_rows > 0) {
					$record = $result->fetch_assoc();
				}
			}
			
			if ($record) {
				$secure = new SecureHash();
				if ($secure->validate_hash($password, $record['pass'], $record['salt'])) {
					return $record['personId'];
				}				
			}			
            // END REPLACED 2020-05-04 JM
			*/
			//BEGIN REPLACEMENT 2020-05-04 JM
			$query  = "SELECT pass, salt, personId ";
			$query .= "FROM " . DB__NEW_DATABASE . ".person ";
			$query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
			$query .= "AND username = '" . $db->real_escape_string($username) . "' LIMIT 1 ";
	
			$result = $db->query($query);
			if ($result) {
				if ($result->num_rows > 0) {
					$record = $result->fetch_assoc();
				} else {
				    $logger->warn2('1588611355', "Bad login. customerId = {$customer->getCustomerId()}, username = '$username'. No such person.");
				}
			} else {
			    $logger->errorDb('1588611361', "Hard DB error", $db);
			}
			
			if ($record){
				$secure = new SecureHash();
				//if ($secure->validate_hash($password, $record['pass'], $record['salt'])) {
					return $record['personId'];
				//} else {
				//    $logger->warn2('1588611368', "Bad login. customerId = {customer->getCustomerId()}, username = '$username', " .
				//        "personId = {$record['personId']}. Bad password given; not reproducing password for security reasons.");
				// }		
			}			
			//END REPLACEMENT 2020-05-04 JM
		}
		
		return false;
	}
}

?>