<?php
/* inc/classes/SSSEng.class.php

EXECUTIVE SUMMARY: The base class for most of our other classes, especially
those centered on a particular database table. Probably should be the base class 
for more of them than it is as of 2019-02.

>>>00017 JM: I'm surprised this class is not abstract, is it ever directly instantiated?
* Public functions
** __construct(User $user = null)
** myClass()
** buildLink($urionly = false)
** getCrumbId()
** getUser()
** addNote($noteText)
** deleteNote($noteId)
** getNotes()
** getPhoneTypes()
** getEmailTypes() - should be static
** getLocationTypes() - should be static
** static function validatePhoneNumber($v, $valueToValidate, $isRequired, $actionName, $entity)
*/

class SSSEng {	
	protected $user; // Should be a User object, typically for the logged-in user
	                 // but can also be artificial, e.g. in CLI. >>>00016 I (JM)
	                 // am a bit surprised there isn't a check on this: e.g. if 
	                 // we are on the web, I would think this should always be the
	                 // current logged-in user or nothing.	                 
	protected $db;   // Keeps a DB instance around, means methods don't each have 
	                 // to do their own DB::getInstance() 
	protected $logger; // Access to the logger from object methods. Added 2020-05-20 JM  
	private $class;  // Class name of the concrete object, e.g. 'Job', 'Person'
	                 // >>>00017: should be used more consistently, even though we
	                 // set this in the constructor, we call get_class($this) again. 
    /*
    // BEGIN REPLACED 2020-01-10 JM
	public function __construct(User $user = null) {		
		$this->user = $user;
	// END REPLACED 2020-01-10 JM
	*/
	// BEGIN REPLACEMENT 2020-01-10 JM
	public function __construct(User $overtUser = null) {
	    global $user;
		//global $logger; // added 2020-05-20 JM
		
		if ($overtUser !== null) {
		    $this->user = $overtUser;
		} else {
		    // Grab the global $user. That could be null, too, but otherwise it should be a serviceable value.
		    $this->user = $user;
		}		
    // END REPLACEMENT 2020-01-10 JM		
		$this->db = DB::getInstance();
		$this->class = get_class($this);

		$this->logger = Logger2::getLogger("main");
		//$this->logger = $logger; // added 2020-05-20 JM
	}

	// RETURN own class name; >>>00017 a bit odd to make this a class method, since
	//  it is the same as PHP's get_class($this) or accessing $this->class,
	//  set in constructor. 
	public function myClass() {		
		return get_class($this);		
	}	
	
	// Builds an HTTP link to the top-level web page for this concrete object.
	// INPUT $urionly: quasi-Boolean, relevant only for $class == 'Job';
	// 
	// With one exception, this will only work correctly if we've accessed 
	//  this through CGI (web), not through CLI (command-line interface).
	//  The exception is that if $urionly==true, and if this object is a Job, 
	//  then the return is possibly useful.
	// >>>00017 there is some awfully tight coupling between this class and its children.
	//  On some classes that inherit from SSSEng, this will have no return, because
	//   there is no default case here.
	public function buildLink($urionly = false) {		
		$class = get_class($this);

		if ($class == 'Job'){
			if ($urionly){
				return '/job/' . rawurlencode($this->getNumber());
			} else {
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/job/' . rawurlencode($this->getNumber());				
			}			
		} else if ($class == 'Person') {
			if (method_exists($this, 'getId')) {
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/person/' . rawurlencode($this->getId());
			}
			// >>>00017 why not the same else-case we get for all the other? 
		} else if ($class == 'WorkOrder') {
			if (method_exists($this, 'getId')) {
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/workorder/' . rawurlencode($this->getId());
			}			
			return REQUEST_SCHEME . '://' . HTTP_HOST . '/';		
		} else if ($class == 'Company') {
			if (method_exists($this, 'getId')){
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/company/' . rawurlencode($this->getId());
			}			
			return REQUEST_SCHEME . '://' . HTTP_HOST . '/';		
		} else if ($class == 'Invoice') {
			if (method_exists($this, 'getId')){
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/invoice/' . rawurlencode($this->getId());
			}			
			return REQUEST_SCHEME . '://' . HTTP_HOST . '/';		
		} else if ($class == 'CompanyPerson') {
			if (method_exists($this, 'getId')) {
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/companyperson/' . rawurlencode($this->getId());
			}			
			return REQUEST_SCHEME . '://' . HTTP_HOST . '/';	
		} else if ($class == 'Contract') {		    
		    /* BEGIN REPLACED 2020-01-28 JM
		    // Replacing this because I believe it was simply wrong. I believe it was never previously used anywhere, so I'm not too worried. - JM
			if (method_exists($this, 'getId')) {
				return REQUEST_SCHEME . '://' . HTTP_HOST . '/contract/' . rawurlencode($this->getId());
			}
			// END REPLACED 2020-01-28 JM
			*/
			// BEGIN REPLACEMENT 2020-01-28 JM
			// The shortened URL for a contract is a bit odd, but it can be determined.
			if (method_exists($this, 'getId') && method_exists($this, 'getWorkOrderId')) {
			    if ($this->getId()) {
			        return REQUEST_SCHEME . '://' . HTTP_HOST . '/contract/' . rawurlencode($this->getWorkOrderId()) . '/?contractId='. rawurlencode($this->getId());
			    } else {
			        // ID is zero, this is a meaningful scenario for when a contract is being written.
			        // I (JM) believe this sometimes starts from zero and sometimes pulls an uncommitted contract from the DB.
			        return REQUEST_SCHEME . '://' . HTTP_HOST . '/contract/' . rawurlencode($this->getWorkOrderId());
			    }
			}
			// END REPLACEMENT 2020-01-28 JM
			return REQUEST_SCHEME . '://' . HTTP_HOST . '/';	
		} else if ($class = 'Location') {
		    if(method_exists($this, 'getLocationId')) {
		        return REQUEST_SCHEME . '://' . HTTP_HOST . '/location.php?locationId=' . rawurlencode($this->getId());
		    }
			return REQUEST_SCHEME . '://' . HTTP_HOST . '/';
		} 
	} // END public function buildLink($urionly = false)	
	
	// This is a bit convoluted. Returns an ID whose meaning varies with the type
	//  of the object. For example, for a person, this is a personId and for a company
	//  it is a companyId. It appears always to be the primary key into the relevant table.
	// Classes that extend SSSEng consistently protect this method.
	public function getCrumbId() {		
	    // [BEGIN Martin comment]
		// prolly could have just unprotected the getId method in the classes
		// but for now just doing this way
		// [END Martin comment]		
		if (method_exists($this, 'getId')) {
			return $this->getId();
		}
		
		return 0;		
	}	
		
	// RETURNs a NOTE_TYPE appropriate to the concrete object, for use in DB table Note.
	// Can return false if no NOTE_TYPE is associated with the concrete object's class. 
	private function getNoteType() {		
		$class = get_class($this);
		
		if ($this->class == 'Job'){
			return NOTE_TYPE_JOB;
		} else if ($this->class == 'Person'){
			return NOTE_TYPE_PERSON;
		} else if ($this->class == 'Company'){
			return NOTE_TYPE_COMPANY;
		} else if ($this->class == 'WorkOrderTask'){
			return NOTE_TYPE_WORKORDERTASK;
		} else if ($this->class == 'WorkOrder'){
			return NOTE_TYPE_WORKORDER; 
		}		
		
		return false;		
	}
	
	// RETURNs the User object associated with the concrete object's class; null if none. 
	public function getUser() {		
		return $this->user;
	}
	
	// Adds a note for this user, with a NOTE_TYPE appropriate to the concrete object's class.
	// INPUT $noteText
	// Return true on success and false on query failed.
	public function addNote($noteText) {
		if ($this->user == null) {
			$this->logger->error2('637314607507942357', ' User is null');
			return false;
		}

		$personId = intval($this->user->getUserId());
		$id = 0;
		
		if (method_exists($this, 'getId')) {
			$id = $this->getId();
		}			

		$noteType = $this->getNoteType();

		if ($id && $noteType) {		
			$noteText = trim($noteText);
			$noteText = truncate_for_db($noteText, "noteText", 2048, '637314602794494576');

			if (strlen($noteText)){
				$query =  "INSERT INTO " . DB__NEW_DATABASE . ".note (noteTypeId, id, noteText, personId) VALUES (";
				$query .= " " . intval($noteType) . " ";
				$query .= " ," . intval($id) . " ";
				$query .= " ,'" . $this->db->real_escape_string($noteText) . "' ";
				$query .= " ," . intval($personId) . ") ";

				$result = $this->db->query($query);
			
				if (!$result) {
					$this->logger->errorDb('637314591553507024', ' addNote: Hard DB error', $this->db);
					return false;
				}
			
			} else {
				$this->logger->warn2('637314591553507024', ' addNote got a noteText empty: ' . $noteText);
			}
		}
		return true;
	} // END public function addNote	
	
	// INPUT $noteId: Primary key of note to delete, must match this object
	// >>>00028 JM: Should be transactional, right now it could delete from
	// main notes table & not add to history.
	// Return true on success and false on query failed.
	public function deleteNote($noteId) {
		$id = 0;

		if($this->user == null) {
			$this->logger->error2('637314614629819970', ' User is null');
			return false;
		}

		if (method_exists($this, 'getId')) {
			$id = $this->getId();
		}
		
		$noteType = $this->getNoteType();		
		
		if ($id && $noteType) {
			$oldnote = '';
			
			// $query  = " select * "; // REPLACED 2020-03-02 JM: just use "select noteText", that's all we access.
			$query  = " SELECT noteText "; // REPLACEMENT 2020-03-02 JM
			$query .= " FROM " . DB__NEW_DATABASE . ".note ";
			$query .= " WHERE noteTypeId = " . intval($noteType) . " ";
			$query .= " AND id = " . intval($id) . " ";
			$query .= " AND noteId = " . intval($noteId) . " ";

			$result = $this->db->query($query); // Rewrite. George 2020-07-27
			
			if (!$result) {
				$this->logger->errorDb('637314547771203272', ' Select noteText: Hard DB error', $this->db);
				return false;
			} 

			if ($result->num_rows > 0){
				$row = $result->fetch_assoc();
				$oldnote = $row['noteText'];
			}
			// In the following, if we didn't get $oldnote, then no rows will be affected by the deletion.
			//  >>>00017 JM 2019-02-28: I'm a little concerned that below we check $this->db->affected_rows without first
			//  being sure the operation succeeded.
			$query =  "DELETE FROM " . DB__NEW_DATABASE . ".note ";
			$query .= " WHERE noteTypeId = " . intval($noteType) . " ";
			$query .= " AND id = " . intval($id) . " ";
			$query .= " AND noteId = " . intval($noteId) . " ";
			
			$result = $this->db->query($query); // Rewrite. George 2020-07-27

			if (!$result) {
				$this->logger->errorDb('637314561226751703', ' deleteNote: Hard DB error', $this->db);
				return false;
			} 
			
			// If we deleted, then we copy the old note to the History table.
			// >>>00026: all of this seems possibly incomplete. In particular,
			//  History table seems to have some unfulfilled notion of Entity;
			//  also we don't track what type of object the deleted note referred to.
			//  I (JM 2019-02-28) suspect unfinished work in progress.
			if (intval($this->db->affected_rows)) {
				$history = new History($this->user->getUserId(), intval($noteId));
				$history->add(HISTORY_DELETE_NOTE, $oldnote); // defined in config.php	
				return true; // Add. George 2020-07-27
			}
		}	
	} // END public function deleteNote	
	
	// RETURNs an array of associative arrays, representing all Notes associated
	//  with this object or NULL on query failed.
	//  * Each associative array is the canonical representation of a row from 
	//    DB table Note, with each index corresponding to a column name, plus an
	//    additional index 'person' that will be a Person object corresponding to
	//    the 'personId', or false if 'personId' is zero or invalid.
	//  * Order of array will effectively be chronological. 
	public function getNotes() {
		$notes = array();		
		$id = 0;
		
		if(method_exists($this, 'getId')){
			$id = $this->getId();
		}
		
		$noteType = $this->getNoteType();
		
		if ($id && $noteType) {			
			$query = " SELECT n.* ";
			$query .= " FROM " . DB__NEW_DATABASE . ".note n ";
			$query .= " WHERE id = " . intval($id) . " ";
			$query .= " AND n.noteTypeId = " . intval($noteType) . " "; // [Martin comment] defined in config.php
			$query .= " ORDER BY n.noteId ";
	
			$result = $this->db->query($query);
			
			if (!$result) {
				$this->logger->errorDb('637314652474978780', ' getNotes: Hard DB error', $this->db);
				return null; //query failed
			} else {
				while ($row = $result->fetch_assoc()) {
					if (intval($row['personId'])) {
						$row['person'] = new Person($row['personId']);
					} else {
						$row['person'] = false;
					}
					$notes[] = $row;
				}
			}			
		}		
	
		return $notes;
	} // END public function getNotes	
	
	// RETURN an array of associative arrays representing phoneTypes, in typeName order.
	//  Each associative array is the canonical representation of a row in DB table PhoneType,
	//  with each index corresponding to a column name.
	public static function getPhoneTypes(&$errCode=false) {	
		$db = DB::getInstance();
		global $logger;
		$errCode = false;
		$ret = array();
	
		$query  = " SELECT * ";
		$query .= " FROM " . DB__NEW_DATABASE . ".phoneType ";
		$query .= " ORDER BY typeName ";
	
		$result = $db->query($query);
		if (!$result) {
			$logger->errorDB('637406853273974981', " getPhoneTypes() => Hard DB error", $db);
			$errCode = true;
		} else {
			while ($row = $result->fetch_assoc()) {
				if ($row['typeName'] != '0'){  // [Martin comment] some legacy crap that should actually be removed.  but just in case forgot to
					$ret[] = $row;
				}
			}
		}
	
		return $ret;
	}
	
	// RETURN an array of associative arrays representing emailTypes, in emailTypeName order.
	//  Each associative array is the canonical representation of a row in DB table EmailType,
	//  with each index corresponding to a column name.
	public function getEmailTypes(&$errCode=false) {
		//$db = DB::getInstance(); // >>>00017: fetching this locally is actually a good idea, if we want to make this static.
		$errCode=false;
		$ret = array();
	
		$query  = "SELECT * ";
		$query .= "FROM " . DB__NEW_DATABASE . ".emailType ";
		$query .= "ORDER BY emailTypeName ";

		$result = $this->db->query($query);
		if (!$result) {
			$this->logger->errorDB('637406856012780701', "getEmailTypes() => Hard DB error", $this->db);
			$errCode = true;
		} else {
			while ($row = $result->fetch_assoc()) {
				$ret[] = $row;
			}
		}
		
		return $ret;
	}
	
	// RETURN an array of associative arrays representing locationTypes, in locationTypeName order.
	//  Each associative array is the canonical representation of a row in DB table LocationType,
	//  with each index corresponding to a column name.
	public function getLocationTypes(&$errCode=false) {	
		//$db = DB::getInstance(); // >>>00017: fetching this locally is actually a good idea, if we want to make this static.
		$errCode = false;
		$ret = array();
	
		$query  = "SELECT * ";
		$query .= "FROM " . DB__NEW_DATABASE . ".locationType ";
		$query .= "ORDER BY locationTypeName ";
	
		$result = $this->db->query($query);

		if (!$result) {
			$this->logger->errorDB('637406861490338409', "getLocationTypes() => Hard DB error", $this->db);
			$errCode = true;
		} else {
			while ($row = $result->fetch_assoc()){
				$ret[] = $row;
			}
		}
		
		return $ret;
	}

	protected static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}

	
    /**
    * Validates Phone Number format for Add and Update phone methods.
    * Initially we applied rules from Validator2, after that, if it passed the rulles, we apply code based logic.
    * @param object $v. Type Validator2($_REQUEST),
    * We specified a set of rulles like : "required", "numeric", "in" etc. This rulles are clled on object $v,
    * E.g  $v->rule('required', 'phoneNumber');
    * @param mixed $valueToValidate. Input to validate.
    * @param bool $isRequired. isRequired is true if input is required on Add given Phone Number E.g: "person->AddPhone",
    * isRequired is false if input is not required on Update given Phone Number E.g: "person->UpdatePhone",
    * used in Log messages (dynamicaly);
    * @param string $actionName. Specifices if method is called for Add or Update. E.g: "person->UpdatePhone" or "person->AddPhone",
    * used for Log message;
    * @return array ($errorPh, $phoneNumber) where $errorPh is an error message if Phone Number format is not valid and $phoneNumber
    * is the Phone Number after we apply the code based logic, used for Add or Update actions.
    */
    
    public static function validatePhoneNumber($v, $valueToValidate, $isRequired, $actionName, $entity) {
		global $logger;
		$phoneNumber = trim($valueToValidate);
		$onlyDigits =''; // Added an initialisation of the onlyDigits.
		$inputNotDigits =''; //Added an initialisation of the inputNotDigits.
		$onlyDigitsLen = 0;
		$inputNotDigitsLen = 0;
		$phoneTypes = array(); //Build an array for valid phoneTypeIds.

		$onlyDigits =  preg_replace("/[^0-9]/", "", $phoneNumber); // this is what we want to check for length.

		if($onlyDigits != "") {
			$onlyDigitsLen = strlen($onlyDigits);
		} 

		$inputNotDigits = preg_replace('/\d/', '', $phoneNumber); //input after we remove all the digits

		if($inputNotDigits != "") {
			$inputNotDigitsLen = strlen($inputNotDigits);
		} 

        $phoneTypesFromDb = SSSEng::getPhoneTypes();
        foreach ($phoneTypesFromDb as $value) { //get phoneTypeIds from DB.
            $phoneTypes[] = $value["phoneTypeId"]; 
        }
		// IMPROVED by George 2020-07-03.

		if($isRequired == false && $entity == "person") {
			$v->rule('required', 'personPhoneId');
			$v->rule('numeric', 'personPhoneId');
			if($inputNotDigitsLen > 0 && $onlyDigitsLen == 0 ) {
				$v->error('phoneNumber', 'No digits in Phone Number.');
			}
		}
		if($isRequired == false && $entity == "company") {
			$v->rule('required', 'companyPhoneId');
			$v->rule('numeric', 'companyPhoneId');
			if($inputNotDigitsLen > 0 && $onlyDigitsLen == 0 ) {
				$v->error('phoneNumber', 'No digits in Phone Number.');
			}
		}

		if($isRequired == true) {
			$v->rule('required', 'phoneNumber'); // In Add actions.
		}
		
		$v->rule('required', 'phoneTypeId');
        $v->rule('numeric', 'phoneTypeId');
        $v->rule('in', 'phoneTypeId', $phoneTypes); //phoneTypeId value must be in array.    
        // End IMPROVEMENT
        
        if (!$v->validate()) {
            $errorId = '637223872279492154';
			$logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
		} else {
			//George IMPROVED 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
			if (!preg_match("/^[- ()0-9]*$/", $phoneNumber)) {
				$errorId = '637412209534318493';
				//Add error message.
				$v->error('phoneNumber', 'Invalid characters in phoneNumber');
				$logger->error2($errorId, "Invalid characters in phoneNumber. ".json_encode($v->errors()));
			}
            if($isRequired) {
                if($onlyDigitsLen == 0) {
                    $errorId = '637248138099727640';
                    //Add error message.
                    $v->error('phoneNumber', 'No digits in input for phoneNumber');
					$logger->error2($errorId, "Wrong input for phoneNumber ".json_encode($v->errors()));
                            
                } else if($onlyDigitsLen != PHONE_NADS_LENGTH) { //Check if we have less than 10 digits!
                    $errorId = '637262803464790616';
                    //Add error message.
                    $v->error('phoneNumber', 'Input phone number, not '.PHONE_NADS_LENGTH.' digits long!');
                    $logger->error2(
                        $errorId,
						$actionName." => Input phone number, not ".PHONE_NADS_LENGTH." digits long! ".json_encode($v->errors()));
                }
            } else if($onlyDigitsLen > 0 && $onlyDigitsLen != PHONE_NADS_LENGTH) {
                $errorId = '637248033324498415';
                //Add error message.
                $v->error('phoneNumber', 'Input phone number, not '.PHONE_NADS_LENGTH.' digits long!');
                $logger->error2(
                    $errorId,
                    $actionName." => Input phone number, not ".PHONE_NADS_LENGTH." digits long! ".json_encode($v->errors()));
            }
        }

        unset($phoneTypes, $onlyDigits, $onlyDigitsLen, $inputNotDigits, $inputNotDigitsLen);

        return array($v->errors(), $phoneNumber);
    }


}

?>