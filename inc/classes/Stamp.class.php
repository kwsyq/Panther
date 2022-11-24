<?php
/* inc/classes/Stamp.class.php
   Manage DB table 'stamp'
   
   Public functions:
   * __construct($id)
   * getStampId()
   * getCustomerId()
   * getIsEorStamp()
   * getEorCustomerPersonId()
   * getState()
   * getFilename()
   * getName()
   * getDisplayName()
   * getActive()
   * getIssueDate()
   * getExpirationDate()
   * getInserted()
   * getInsertedPersonId()
   * getModified()
   * getModifiedPersonId()
   * getEorPersonId()
   * setName($name)
   * setDisplayName($displayName)
   * setActive($active) -- typically, use setActive(0) rather than doing a hard delete.
   * setIssueDate($issueDate)
   * setExpirationDate($expirationDate)
   * update($val)
   * save()
   
   Public static functions:
   * validate($stampId)
   * createEorStamp($customerId, $eorPersonId, $state, $filename, $displayName, $issueDate, $expirationDate)
   * createGenericStamp($customerId, $state, $filename, $name, $displayName, $issueDate, $expirationDate)
   * hardDelete($stampId) - not sure if this should ever be done, but here's a way to do it if it should be.
   * getStamps($criteria)
   * getStampByName($name, $customerId) - $customerId is optional, defaults to current customer 
   
   >>>00038 might want to add some security at this level, requiring some sort of permission; probably just an admin login
     required for certain actions.
        
*/ 

class Stamp {
    // Corresponding to columns in DB table 'stamp' 
    private $stampId;     // primary key
    private $customerId;  // foreign key into DB table 'customer'
    private $isEorStamp;  // 0 or 1, effectively Boolean
    private $eorCustomerPersonId; // if $isEorStamp==1, then this is the customerPersonId for that EOR 
    private $state;       // if $isEorStamp==1, then this is the 2-letter code for the U.S. state associated with the stamp.
    private $filename;    // name of the stamp; all of these will be in a single folder for a given customer (e.g. SSS). 
                          // This can be a blank string if the row is just a placeholder.
    private $name;        // Internal name for code purposes (PHP constant); should be null if $isEorStamp==1 
    private $displayName; // For display in the UI
    private $active;      // 0 or 1, effectively Boolean
    private $issueDate;         // if $isEorStamp==1, date of issue by state if known, null otherwise
    private $expirationDate;    // if $isEorStamp==1, date state-issued stamp expires if known, null otherwise
    private $inserted;          // TIMESTAMP for insertion of row
    private $insertedPersonId;  // If that insertion is attributable to an individual, their personId. Otherwise, null. 
    private $modified;          // TIMESTAMP for last modification of row
    private $modifiedPersonId;  // If that modification is attributable to an individual, their personId. Otherwise, null.
    
    // Calculated: 
    private $eorPersonId;  // corresponds to $eorCustomerPersonId  
	
	private $user; // User object for the logged-in user.
	private $db;   // Keeps a DB instance around, means methods don't each have 
	               // to do their own DB::getInstance()
    private $logger;
	
	// INPUT $id: a stampId
	public function __construct($id) {
	    global $user, $logger;
        $this->user = $user;
        $this->logger = $logger;
		$this->db = DB::getInstance();
		
		$query = "SELECT s.stampId, s.customerId, s.isEorStamp, s.eorCustomerPersonId, s.state, s.filename, s.name, s.displayName, s.active, " .
		         "s.issueDate, s.expirationDate, s.inserted, s.insertedPersonId, s.modified, s.modifiedPersonId, ".
		         "cp.personId AS eorPersonId " .
		         "FROM " . DB__NEW_DATABASE . ".stamp AS s LEFT JOIN " . DB__NEW_DATABASE . ".customerPerson AS cp ON s.eorCustomerPersonId = cp.customerPersonId " .
		         "WHERE s.stampId = " . intval($id) . ';';
		$result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1586295315', "Hard DB error constructing Stamp object", $this->db);
        } else if ($result->num_rows > 0) {
            // Since query used primary key, we know there will be exactly one row.
            // Set all of the private members that represent the DB content.
            $row = $result->fetch_assoc();
            
            $this->stampId = $row['stampId'];
            $this->customerId = $row['customerId'];
            $this->isEorStamp = $row['isEorStamp'];
            $this->eorCustomerPersonId = $row['eorCustomerPersonId'];
            $this->state = $row['state'];
            $this->filename = $row['filename'];
            $this->name = $row['name'];
            $this->displayName = $row['displayName'];
            $this->active = $row['active'];
            $this->issueDate = $row['issueDate'];
            $this->expirationDate = $row['expirationDate'];
            $this->inserted = $row['inserted'];
            $this->insertedPersonId = $row['insertedPersonId'];
            $this->modified = $row['modified'];
            $this->modifiedPersonId = $row['modifiedPersonId'];
            $this->eorPersonId = $row['eorPersonId'];
        }
	} // END __construct

	// RETURN the primary key
    public function getStampId() {
        return $this->stampId;
    }
    
    // RETURN the customerId; probably little need to call this, since we don't ever normally cross over customers, it 
    //  will be the same as for global $customer.
    public function getCustomerId() {
        return $this->customerId;
    }
    
    // RETURN 0 or 1, effectively Boolean: is this for an EOR?
    public function getIsEorStamp() {
        return $this->isEorStamp;
    }
    
    // If $isEorStamp==1, then RETURN the customerPersonId for that EOR; otherwise, typically null
    public function getEorCustomerPersonId() {
        return $this->eorCustomerPersonId;
    }
    
    // if $isEorStamp==1, then this is the 2-letter code for the U.S. state associated with the stamp. Otherwise, typically null.
    public function getState() {
        return $this->state;
    }
    
    // filename of the stamp; all of these will be in a single folder for a given customer (e.g. SSS). 
    // This can be a blank string if the row is just a placeholder.
    public function getFilename() {
        return $this->filename;
    }
    
    // RETURN Internal name for code purposes (PHP constant); should be null if $isEorStamp==1
    public function getName() {
        return $this->name;
    }
    
    // RETURN name for display in the UI
    public function getDisplayName() {
        return $this->displayName;
    }
    
    // RETURN 'active' (0 or 1, effectively a boolean)
    public function getActive() {
        return $this->active;
    }
    
    // RETURN date of issue by state if known, null otherwise; null if not an EOR timestamp
    public function getIssueDate() {
        return $this->issueDate; 
    }
    
    // RETURN date of expiration for state-issued stamp if known, null otherwise; null if not an EOR timestamp
    public function getExpirationDate() {
        return $this->expirationDate; 
    }
    
    // RETURN when row was first inserted (timestamp)
    public function getInserted() {
        return $this->inserted;
    }
    
    // RETURN who inserted row, if known. Foreign key into DB table 'person'
    public function getInsertedPersonId() {
        return $this->insertedPersonId;
    }
    
    // RETURN when row was last modified (timestamp)
    public function getModified() {
        return $this->modified;
    }
    
    // RETURN who last modified row, if known. Foreign key into DB table 'person'    
    public function getModifiedPersonId() {
        return $this->modifiedPersonId;
    }

    // RETURN personId of EOR associated with the stamp, if relevant, null otherwise
    // NOTE that this is not directly stored in the table, which stores eorCustomerPersonId.
    public function getEorPersonId() {
        return $this->eorPersonId;
    }
    
    // --------------------------
    // Only a limited number of columns can be updated; most others are either driven by SQL itself, or
    //  would constitute a new stamp, and hence a new row.
        
    // NOTE that these methods must be followed by a "Save" to take effect.
    
    // INPUT $name: allows us to change the name of the associated PHP constant; alphanumeric, all caps; underscores allowed,
    //   first character cannot be a digit. Non-null name makes no sense if this is an EOR stamp.
    // RETURN true on success, false otherwise
    public function setName($name) {
        if ($name) {
            $name = trim($name);
        }
        if ($name) {
            if ($name != $this->name) {
                // trying to change it
                if ($this->eorPersonId || $this->isEorStamp) {
                    $this->logger->error2('1586803457', "Cannot set a PHP constant name for an EOR timestamp");
                    return false; // Bail out
                }
                if (!Stamp::validateName($name, $this->customerId)) {
                    $this->logger->error2('1586803458', "Error setting name for stamp ". $this->stampId);
                    return false; // bail out; detailed error is already logged
                }
                $this->name = $name;
            }
        } else {
            $this->name = null;
        }
        return true;
    }
    
    // INPUT $displayName: allows us to change the name for this stamp shown in the UI.
    // Cannot be empty, null, etc.
    // RETURN true on success, false otherwise
    public function setDisplayName($displayName) {
        if ($displayName) {
            $displayName = trim($displayName);
        }
        
        if ($displayName) {
            if ($displayName != $this->displayName ) {
                // trying to change it
                if (!Stamp::validateDisplayName($displayName, $this->customerId)) {
                    $this->logger->error2('1586803459', "Error setting displayName for stamp ". $this->stampId. ", invalid or conflicting display name '$displayName'");
                    return false; // bail out; detailed error is already logged
                }
                $this->displayName = $displayName;
            }
        } else {
            $logger->error2('1586805661', "Cannot set a null or empty display name for a stamp.");
            return false; // bail out
        }
        return true;
    }
    
    // INPUT $filename: allows us to change the filename. Currently (v2020-3) allowed only if existing filename is blank.
    // RETURN true on success, false otherwise
    public function setFilename($filename) {
        if ($filename) {
            $filename = trim($filename);
        }
        
        if ($this->filename) {
            // just ignore this and return success (but log it in case anyone is wondering)
            $this->logger->info2('1587159286', "tried to change filename to '$filename', but it is already '{$this->filename}', and we currently don't allow overwrite");
            return true;
        }
        
        if ($filename) {
            if ($filename != $this->filename ) {
                // trying to change it
                if (!Stamp::validateFilename($filename, $this->customerId)) {
                    $this->logger->error2('1587156675', "Error setting filenam for stamp ". $this->stampId. ", invalid or conflicting filename '$filename'");
                    return false; // bail out; detailed error is already logged
                }
                $this->filename = $filename;
            }
        } else {
            $logger->error2('1587156667', "Cannot set a null or empty display name for a stamp.");
            return false; // bail out
        }
        return true;
    } // END setFilename
    
    // INPUT active; whether this row is to be active. 
    // RETURN should always be true (success)
    public function setActive($active) {
        $this->active = $active ? 1 : 0;
    }
    
    // INPUT $issueDate: allows us to change the issue date of a stamp. false-y values are treated as NULL.
    // RETURN true on success, false otherwise
    public function setIssueDate($issueDate) {
        if ($issueDate) {
            $issueDate = trim($issueDate);
        }
        if ($issueDate) {
            $issueDateCleaned = Stamp::cleanDateInput($issueDate, 'issueDate');
            if ($issueDateCleaned == false) {            
                return false; // bail out;
            }
            $this->issueDate = $issueDateCleaned;
        } else {
            $this->issueDate = null; 
        }
        return true;
    }
   
    // INPUT $issueDate: allows us to change the issue date of a stamp. false-y values are treated as NULL.
    // RETURN true on success, false otherwise
    public function setExpirationDate($expirationDate) {
        if ($expirationDate) {
            $expirationDate = trim($expirationDate);
        }
        if ($expirationDate) {
            $expirationDateCleaned = Stamp::cleanDateInput($expirationDate, 'expirationDate');
            if ($expirationDateCleaned == false) {            
                return false; // bail out;
            }
            $this->expirationDate = $expirationDateCleaned;
        } else {
            $this->expirationDate = null;
        }
        return true;
    }
    
    // Allows setting multiple values at once, and does a save. 
    // INPUT $val is an associative array. Acceptable indexes are as follows, and they call the obvious 'set' methods.
    //  See the respective 'set' methods for notes on acceptable values.
    //  * name
    //  * displayName
    //  * filename
    //  * active
    //  * issueDate
    //  * expirationDate
    // RETURN true on success, false otherwise
    public function update($val) {
        if (array_key_exists('name', $val)) {
            if (!$this->setName($val['name'], $this->customerId)) {
                return false;
            }
        }
        if (array_key_exists('displayName', $val)) {
            if (!$this->setDisplayName($val['displayName'], $this->customerId)) {
                return false;
            }                    
        }
        if (array_key_exists('filename', $val)) {
            if (!$this->setFilename($val['filename'], $this->customerId)) {
                return false;
            }                    
        }
        if (array_key_exists('active', $val)) {
            if (!$this->setActive($val['active'])) {
                return false;
            }                    
        }
        if (array_key_exists('issueDate', $val)) {
            if (!$this->setIssueDate($val['issueDate'])) {
                return false;
            }
        }
        if (array_key_exists('expirationDate', $val)) {
            if (!$this->setExpirationDate($val['expirationDate'])) {
                return false;
            }                    
        }
        
        return $this->save();
    }
    
    // Does an actual save to the DB.
    // RETURN: true on success, false on failure
    // INPUT forceSave: Boolean; if true then we set 'modified' timestamp overtly to force
    //   a modification even if no data has changed (added 2020-08-19 for v2020-4) 
    public function save($forceSave=false) {
        // Who is logged in?
        $userPersonId = null;
        if ($this->user) {
            $userPersonId = $this->user->getUserId(); 
        }
        
        // UPDATE only the columns that may change
        $query = "UPDATE " . DB__NEW_DATABASE . ".stamp SET ";
        $query .= "name=" . ($this->name ? "'" . $this->db->real_escape_string($this->name) . "'" : 'NULL') . ", ";
        $query .= "displayName='" . $this->db->real_escape_string($this->displayName) . "', ";
        $query .= "filename='" . $this->db->real_escape_string($this->filename) . "', ";
        $query .= "active=" . ($this->active ? 1 : 0) . ", ";
        $query .= "issuedate=" . ($this->issueDate ? "'" . $this->db->real_escape_string($this->issueDate) . "'" : 'NULL') . ", ";
        $query .= "expirationdate=" . ($this->expirationDate ? "'" . $this->db->real_escape_string($this->expirationDate) . "'" : 'NULL') . ", ";
        $query .= "modifiedPersonId=" . ($userPersonId ? $userPersonId : 'NULL') . " ";
        if ($forceSave) {
            $query .= ", modified=CURRENT_TIMESTAMP ";
        }
        $query .= "WHERE stampId = {$this->stampId};";
        
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1586810107', "Hard DB error updating stamp", $this->db);
            return false;
        }

        $query = "SELECT modified, modifiedPersonId FROM " . DB__NEW_DATABASE . ".stamp ";
        $query .= "WHERE stampId = {$this->stampId};";
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1586810109', "Hard DB error reading from stamp after update", $this->db);
            return false;
        }
        if ($result->num_rows == 0) {
            $this->logger->errorDb('1586810111', "Can't find same stamp after update", $this->db);
            return false;
        }
        
        // necessarily exactly one row, since we selected on the primary key        
        $row = $result->fetch_assoc();                
        $this->modified = $row['modified'];
        $this->modifiedPersonId = $row['modifiedPersonId'];
        
        return true;        
    }
	
    // --------------------------
    
    // RETURN: Boolean, true if $stampId is a valid stampId
    public static function validate($stampId) {
        global $logger;
        $db = DB::getInstance();
        
        $ret = false;
		$query = "SELECT stampId FROM " . DB__NEW_DATABASE . ".stamp WHERE stampId = " . intval($stampId) . ';';
		$result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586295420', "Hard DB error validating stamp", $db);            
        } else {
            $ret = $result->num_rows > 0;
        }
        return $ret;        
    }
    
    // INPUT $dateInput - date, ideally in 'Y-m-d' format, but if not we will try to make sense of it.
    // INPUT $dateInputName - name of this date for error reporting
    // RETURN: on success, date in 'Y-m-d' format; otherwise false
    // >>>00006 we might want to pull this out of the class to somewhere it can be used more broadly
    private static function cleanDateInput($dateInput, $dateInputName) {
        global $logger;
        if ($dateInput) {
            if (preg_match('/^\d{4,4}-\d{2,2}-\d{2,2}$/', $dateInput)) {
                $dateInputCleaned = $dateInput;
            } else {
                $parsed_date = strToTime($dateInput);
                if ($parsed_date === false) {
                    $logger->error2('1586300001', "Cannot parse $dateInputName '$dateInput'");
                    return false; // bail out;
                }
                $dateInputCleaned = date('Y-m-d', $parsed_date);
                $logger->info2('1586300016', "$dateInputName '$dateInput' interpreted as '$dateInputCleaned'");
            }
        }
        return $dateInputCleaned;
    }
    
    // Validate internal PHP constant name.
    // alphanumeric, all caps; underscores allowed, not used for EOR stamps. NOTE that you have to be careful not to try to validate a name of an existing row, 
    //  because it will (appropriately) complain that it is already in use.
    // INPUT $name: Optional name for programmatic use. This should be a suitable name for a PHP constant, alphanumeric, all caps; underscores allowed,
    //   first character cannot be a digit.
    // INPUT $customerId: the relevant customerId 
    // RETURN true on valid, false on invalid.
    private static function validateName($name, $customerId) {
        global $logger;
        $db = DB::getInstance();
        
        if ($name === null) {
            return true;
        }
        
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            $logger->error2('1586800402', "invalid PHP constant name $name");
            return false; // bail out;
        }
        $query = "SELECT name FROM " . DB__NEW_DATABASE . ".stamp " .
                 "WHERE name = '" . $db->real_escape_string($name) . "' " . 
                 "AND customerId = $customerId;";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586800409', "Hard DB error selecting stamp", $db);
            return false; // bail out;
        }
        if ($result->num_rows > 0) {
            $logger->error2('1586800416', "Duplicate PHP constant stamp name '$name'");
            return false; // bail out;
        }
        return true;
    }

    // Validate display name.
    // NOTE that you have to be careful not to try to validate a name of an existing row, 
    //  because it will (appropriately) complain that it is already in use.
    // INPUT $displayName: mandatory, intended for use in the UI
    // RETURN true on valid, false on invalid.
    private static function validateDisplayName($displayName, $customerId) {
        global $logger;
        $db = DB::getInstance();
        
        if (strlen($displayName)==0) {
            $logger->error2('1586299816', "Tried to set empty or null displayName for a stamp.");
        } else {
            $query = "SELECT displayName FROM " . DB__NEW_DATABASE . ".stamp " .
                     "WHERE displayName = '" . $db->real_escape_string($displayName) . "' " . 
                     "AND customerId = $customerId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1586299917', "Hard DB error selecting stamp", $db);
                return false; // bail out;
            }
            if ($result->num_rows > 0) {
                $logger->error2('1586299935', "Duplicate displayName '$displayName' for customer $customerId");
                return false; // bail out;
            }
        }
        return true;
    }
    
    // NOTE that you have to be careful not to try to validate the filename of an existing row, 
    //  because it will (appropriately) complain that it is already in use.
    // INPUT $filename: mandatory
    // RETURN true on valid, false on invalid.
    private static function validateFilename($filename, $customerId) {
        global $logger;
        $db = DB::getInstance();
        
        if (!preg_match('/^[a-z_][a-z0-9_]*.pdf$/i', $filename)) {
            $logger->error2('1587157069', "File must be a PDF, name was $filename.");
        } else {
            $query = "SELECT filename FROM " . DB__NEW_DATABASE . ".stamp " .
                     "WHERE filename = '" . $db->real_escape_string($filename) . "' " . 
                     "AND customerId = $customerId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1587157073', "Hard DB error selecting stamp", $db);
                return false; // bail out;
            }
            if ($result->num_rows > 0) {
                $logger->error2('1587157077', "Duplicate filename '$filename' for customer $customerId");
                return false; // bail out;
            }
        }
        return true;
    }

    // Puts an EOR stamp in the DB. Returns the $stampId on success, FALSE otherwise
    // INPUT $customerId: foreign key into DB table 'customer'. Typically global $customer->getCustomerId().
    // INPUT $eorPersonId: EOR for whom this is a stamp. Note that we input personId and *calculate* customerPersonId. That conversion
    //       doubles as validation.
    // INPUT $state: 2 character U.S. state abbreviation. Default: HOME_STATE if blank or invalid.
    // INPUT $filename: file containing the stamp. This is in a folder that will be a constant for the customer, e.g. /var/www/ssseng_documents/stamps
    // INPUT $displayName: intended for use in the UI, should typically indicate person and state, e.g. "Damon-WA"
    // INPUT $issueDate: optional, can be null which should effectively be the same as "0000-00-00", "the dawn of time".
    // INPUT $expirationDate: optional, can be null which should effectively be the same as "9999-01-01", "the end of time".
    public static function createEorStamp($customerId, $eorPersonId, $state, $filename, $displayName, $issueDate, $expirationDate) {
        global $logger;
        global $customer, $user;
        $db = DB::getInstance();
        
        // Who is logged in?
        $userPersonId = null;
        if ($user) {
            $userPersonId = $user->getUserId(); 
        }
        
        // Validate customerId
        $customerId = intval($customerId);
        if (!Customer::validate($customerId)) {
            $logger->error2('1586545086', "Invalid customerId $customerId");
            return false; // bail out;
        }
        
        if ($customerId != $customer->getCustomerId()) {
            $logger->warn2('1586545089', "Setting EOR stamp for customerId $customerId, current customer is " . $customer->getCustomerId() . ', ' . 
                ( $userPersonId ? "User $userPersonId." : "Null user.") .
                " Full call was Stamp::createEorStamp($customerId, $eorPersonId, '$state', '$filename', '$displayName', " .
                ($issueDate ? '$issueDate' : 'null') . ", " .
                ($expirationDate ? '$expirationDate' : 'null') .
                ")");
        }
        
        // Validate $eorPersonId, calculate $eorCustomerPersonId 
        
        $query = "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".customerPerson WHERE personId = $eorPersonId AND customerId = $customerId;";
                 
		$result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586295501', "Hard DB error getting customerPersonId", $db);
            return false; // bail out;
        } else if ($result->num_rows > 1) {
            $logger->errorDb('1586295525', "More than one match getting customerPersonId", $db);
            return false; // bail out;
        } else if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $eorCustomerPersonId = $row['customerPersonId']; 
        }
  
        // See if we have useful information for state, if not then default to HOME_STATE 
        $state = trim($state);
        $stateCleaned = strtoupper(substr($state, 0, 2));
        $states = allStates();
        $stateValidated = false;
        foreach ($states AS $s) {
            if ($stateCleaned == $s[1]) {
                $stateValidated = true;
                break;
            }
        }
        if (!$stateValidated) {
            $stateCleaned = HOME_STATE;
        }
        if ($stateCleaned != $state) {
            $logger->warn2('1586299516', "State input as '$state', interpreted as '$stateCleaned'");
        }
        
        if ($filename) {
            $filename = trim($filename);
        }
        if ($filename) {
            if (!preg_match('/.\.[a-zA-Z]{3,4}$/', $filename)) { // if it doesn't end in an extension of 3 or 4 alphabetic characters, and have at least on character before that...
                $logger->warn2('1586299816', "Odd filename $filename, may want to look at " . BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/$filename');
            }
            $query = "SELECT filename FROM " . DB__NEW_DATABASE . ".stamp " .
                     "WHERE filename = '" . $db->real_escape_string($filename) . "' " . 
                     "AND customerId = $customerId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1586299907', "Hard DB error selecting stamp", $db);
                return false; // bail out;
            }
            if ($result->num_rows > 0) {
                $logger->error2('1586299925', "Duplicate filename '$filename'");
                return false; // bail out;
            }
            // could probably do more validation here, but that is probably best done closer to the UI.
        } else {
            $filename = '';
        }
        
        $displayName = trim($displayName);
        if (!Stamp::validateDisplayName($displayName, $customerId)) {
            $logger->error2('1586299927', "Error setting displayName for new EOR stamp");
            return false; // bail out; detailed error is already logged
        }
        
        if ($issueDate) {
            $issueDate = trim($issueDate);
        }
        if ($issueDate) {
            $issueDateCleaned = Stamp::cleanDateInput($issueDate, 'issueDate');
            if ($issueDateCleaned == false) {            
                return false; // bail out;
            }
        }
                
        if ($expirationDate) {
            $expirationDate = trim($expirationDate);
        }
        if ($expirationDate) {
            $expirationDateCleaned = Stamp::cleanDateInput($expirationDate, 'expirationDate');
            if ($expirationDateCleaned == false) {            
                return false; // bail out;
            }
        }
        if (isset($eorCustomerPersonId)) {            
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".stamp (customerId, isEorStamp, eorCustomerPersonId, state, filename, displayName";
            $query .= $issueDate ? ", issueDate" : ''; 
            $query .= $expirationDate ? ", expirationDate" : '';
            $query .= $userPersonId ? ", insertedPersonId" : '';
            $query .= $userPersonId ? ", modifiedPersonId" : '';
            $query .= ") VALUES ( ";
            $query .= "$customerId, ";
            $query .= "1, "; 
            $query .= "$eorCustomerPersonId, ";
            $query .= "'" . $db->real_escape_string($stateCleaned) . "', ";
            $query .= "'" . $db->real_escape_string($filename) . "', "; // can be empty string
            $query .= "'" . $db->real_escape_string($displayName) . "' "; // NOTE that from here down we add comma BEFORE, not AFTER, value
            $query .= $issueDate ? (", '" . $db->real_escape_string($issueDateCleaned) . "'") : ''; 
            $query .= $expirationDate ? (", '" . $db->real_escape_string($expirationDateCleaned) . "'") : '';
            $query .= $userPersonId ? ", $userPersonId, $userPersonId":''; // inserted + modified 
            $query .= ");";
        }
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586299960', "Hard DB error inserting new EOR stamp", $db);
            return false; // bail out;
        }
        return $db->insert_id;
    } // END public static function createEorStamp

    // Puts a generic (non-EOR) stamp in the DB. This is related to the customer, but not to any individual; state
    //  is optional. Returns the $stampId on success, FALSE otherwise
    // INPUT $customerId: foreign key into DB table 'customer'. Typically global $customer->getCustomerId().
    // INPUT $state: 2 character U.S. state abbreviation. Default: null if blank or invalid.
    // INPUT $filename: file containing the stamp. This is in a folder that will be a constant for the customer, e.g. /var/www/ssseng_documents/stamps
    // INPUT $name: Optional name for programmatic use. This should be a suitable name for a PHP constant, alphanumeric, all caps; underscores allowed,
    //   first character cannot be a digit.
    // INPUT $displayName: intended for use in the UI, should typically indicate person and state, e.g. "Damon-WA"
    // INPUT $issueDate: optional, can be null which should effectively be the same as "0000-00-00", "the dawn of time".
    // INPUT $expirationDate: optional, can be null which should effectively be the same as "9999-01-01", "the end of time".
    public static function createGenericStamp($customerId, $state, $filename, $name, $displayName, $issueDate, $expirationDate) {
        global $logger;
        global $customer, $user;
        $db = DB::getInstance();
        
        // Who is logged in?
        $userPersonId = null;
        if ($user) {
            $userPersonId = $user->getUserId(); 
        }
        
        // Validate customerId
        $customerId = intval($customerId);
        if (!Customer::validate($customerId)) {
            $logger->error2('1586800360', "Invalid customerId $customerId");
            return false; // bail out;
        }
        
        if ($customerId != $customer->getCustomerId()) {
            $logger->warn2('1586800367', "Setting EOR stamp for customerId $customerId, current customer is " . $customer->getCustomerId() . ', '
                ( $userPersonId ? "User $userPersonId." : "Null user.") . '.' ,
                " Full call was Stamp::createGenericStamp($customerId, '$state', '$filename', '$name', '$displayName', " .
                ($issueDate ? '$issueDate' : 'null') . ", " .
                ($expirationDate ? '$expirationDate' : 'null') .
                ")");
        }
        
        // See if we have useful information for state, if not then default to null 
        $state = trim($state);
        $stateCleaned = strtoupper(substr($state, 0, 2));
        $states = allStates();
        $stateValidated = false;
        foreach ($states AS $s) {
            if ($stateCleaned == $s[1]) {
                $stateValidated = true;
                break;
            }
        }
        if (!$stateValidated) {
            $stateCleaned = null;
        }
        if ($stateCleaned != $state) {
            $logger->warn2('1586800374', "State input as '$state', interpreted as ". ($stateCleaned ? $stateCleaned : 'NULL'));
        }
        
        if ($filename) {
            $filename = trim($filename);
        }
        if ($filename) {
            $filename = trim($filename);
            if (!preg_match('/.\.[a-zA-Z]{3,4}$/', $filename)) { // if it doesn't end in an extension of 3 or 4 alphabetic characters, and have at least on character before that...
                $logger->warn2('1586800381', "Odd filename $filename, may want to look at " . BASEDIR . '/../' . CUSTOMER_DOCUMENTS . '/stamps/$filename');
            }
            $query = "SELECT filename FROM " . DB__NEW_DATABASE . ".stamp " .
                     "WHERE filename = '" . $db->real_escape_string($filename) . "' " . 
                     "AND customerId = $customerId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1586800388', "Hard DB error selecting stamp", $db);
                return false; // bail out;
            }
            if ($result->num_rows > 0) {
                $logger->error2('1586800395', "Duplicate filename '$filename'");
                return false; // bail out;
            }
            // could probably do more validation here, but that is probably best done closer to the UI.
        } else {
            $filename = '';
        }
        
        $name = trim($name);
        if ($name) {
            if (!Stamp::validateName($name, $customerId)) {
                $logger->error2('1586800397', "Error setting name for new generic stamp");
                return false; // bail out; detailed error is already logged
            }
        } else {
            $name = null;
        }
        
        $displayName = trim($displayName);
        if (!Stamp::validateDisplayName($displayName, $customerId)) {
            $logger->error2('1586800398', "Error setting displayName for new generic stamp");
            return false; // bail out; detailed error is already logged
        }
        
        if ($issueDate) {
            $issueDate = trim($issueDate);
        }
        if ($issueDate) {
            $issueDateCleaned = Stamp::cleanDateInput($issueDate, 'issueDate');
            if ($issueDateCleaned == false) {            
                return false; // bail out; error is already reported
            }
        }
                
        if ($expirationDate) {
            $expirationDate = trim($expirationDate);
        }
        if ($expirationDate) {
            $expirationDateCleaned = Stamp::cleanDateInput($expirationDate, 'expirationDate');
            if ($expirationDateCleaned == false) {            
                return false; // bail out; error is already reported
            }
        }
        
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".stamp (customerId, isEorStamp, state, filename, name, displayName";
        $query .= $issueDate ? ", issueDate" : ''; 
        $query .= $expirationDate ? ", expirationDate" : '';
        $query .= $userPersonId ? ", insertedPersonId, modifiedPersonId" : '';
        $query .= ") VALUES ( ";
        $query .= "$customerId, ";
        $query .= "0, "; 
        $query .= ($stateCleaned ? "'" . $db->real_escape_string($stateCleaned) . "'" : 'NULL') . ", ";
        $query .= "'" . $db->real_escape_string($filename) . "', "; // can be empty string
        $query .= ($name ? "'" . $db->real_escape_string($name) . "'" : 'NULL') . ", ";
        $query .= "'" . $db->real_escape_string($displayName) . "' "; // NOTE that from here down we add comma BEFORE, not AFTER, value
        $query .= $issueDate ? ", " . $db->real_escape_string($issueDateCleaned) . "'" : ''; 
        $query .= $expirationDate ? ", " . $db->real_escape_string($expirationDateCleaned) . "'" : '';
        $query .= $userPersonId ? ", $userPersonId, $userPersonId" : ''; // inserted + modified 
        $query .= ");";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586800437', "Hard DB error inserting new EOR stamp", $db);
            return false; // bail out;
        }
        return $db->insert_id;
    } // END public static function createGenericStamp
    
    // Hard delete. Not sure if this should ever be done, but here's a way to do it if it should be.
    // RETURN true on success, false otherwise
    public static function hardDelete($stampId) {
        global $logger;
        $db = DB::getInstance();
        
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".stamp "; 
        $query .= "WHERE stampId = {intval($stampId)};";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586812130', "Hard DB error deleting stamp, stampId = $stampId", $db);
        }
        
        // Rather than rely on that result for return, we determine success by whether the row is gone; it's OK if it never existed.
        $query = "SELECT FROM " . DB__NEW_DATABASE . ".stamp "; 
        $query .= "WHERE stampId = {intval($stampId)};";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586812130', "Hard DB error checking for success after deleting stamp, stampId = $stampId", $db);
            return false; // bail out;
        }
        
        return $result->num_rows == 0;        
    }
    
    // Returns an array of Stamp objects
    // INPUT $criteria: an associative array to limit the selection. Semantics should be pretty obvious.
    //  Possible indexes and values:
    //   * 'customerId' : numeric or 'all'. This is the only one with a default other than "ignore": it defaults to current customer. 
    //   * 'active' : 'yes', 'no', 'both'
    //   * 'eor' : 'yes', 'no', 'both' (eor vs. general)
    //   * 'state' : any 2-letter state abbreviation or 'none' (no state)
    //   * 'issuedBefore' [date]: earliest issue date, inclusive. Valid date or 'isNull'. NOTE that isNull on this
    //       or any of the following means the issue/expiration date is NULL, and that you can contradict yourself
    //       by saying (for example) it is issued before a certain date and issued after 'isNull', in which case nothing can match.
    //   * 'issuedAfter' [date]: latest issue date, inclusive. Valid date or 'isNull'.
    //   * 'expiresBefore' [date]: earliest expiration date, inclusive. Valid date or 'isNull'.
    //   * 'expiresAfter' [date]: latest expiration date, inclusive. Valid date or 'isNull'.
    //  For any criteria not specified, we will not apply that as a limitation
    //  Caller shouldn't duplicate criteri, but if they do then the last value wins.
    // RETURNs an array of Stamp objects ordered by displayName, or false on failure
    // Minimal validation here: the burden is on the caller.
    public static function getStamps($criteria=null) {
        global $customer, $logger;
        $db = DB::getInstance();
        
        if (!$criteria) {                                                             
            $criteria = Array();
        }
        
        $refined_criteria = Array();
        foreach ($criteria AS $name => $value) {
            if ($name == 'customerId') {
                $refined_criteria['customerId'] = $value;
            }
            if ($name == 'active') {
                if ($value == 'yes') {
                    $refined_criteria['active'] = 1;
                } else if ($value == 'no') {
                    $refined_criteria['active'] = 0;
                }
            }
            if ($name == 'eor') {
                if ($value == 'yes') {
                    $refined_criteria['requireEor'] = 1;
                    $refined_criteria['requireNotEor'] = 0;
                } else if ($value == 'no') {
                    $refined_criteria['requireEor'] = 0;
                    $refined_criteria['requireNotEor'] = 1;
                } // else neither is required
            }
            if ($name == 'state') {
                $refined_criteria['state'] = $value;
            }
            if ($name == 'issuedBefore' || $name == 'issuedAfter' || $name == 'expiresBefore' || $name == 'expiresAfter') {
                if ($value != 'isNull') {
                    $value = Stamp::cleanDateInput($value, $name);
                    if ($value == false) {            
                        return false; // bail out;
                    }
                }
                if ($name == 'issuedBefore') {
                    $refined_criteria['issuedBefore'] = $value;
                } else if ($name == 'issuedAfter') {
                    $refined_criteria['issuedAfter'] = $value;
                } else if ($name == 'expiresBefore') {
                    $refined_criteria['expiresBefore'] = $value;
                } else if ($name == 'expiresAfter') {
                    $refined_criteria['expiresAfter'] = $value;
                }                
            }
        } // END for ($criteria...
        
        if (!array_key_exists('customerId', $refined_criteria)) {
            $refined_criteria['customerId'] = $customer->getCustomerId();
        }

        $hasWhereClause = false;
        $query = "SELECT stampId, displayName FROM " . DB__NEW_DATABASE . ".stamp";
        foreach ($refined_criteria AS $name => $value) {
            if ($hasWhereClause) {
                $query .= ' AND ';
            } else {
                $query .= ' WHERE ';
            }
            if ($name == 'customerId' && $value != 'all') {
                $query .= "customerId=$value";
                $hasWhereClause = true;
            } else if ($name == 'active') {
                $query .= "active=$value";
                $hasWhereClause = true;
            } else if ($name == 'requireEor') {
                $query .= "isEorStamp=1";
                $hasWhereClause = true;
            } else if ($name == 'requireNotEor') {
                $query .= "isEorStamp=0";
                $hasWhereClause = true;
            } else if ($name == 'state') {
                if ($value == 'none') {
                    $query .= "state IS NULL";
                } else if (array_key_exists('requireEor', $refined_criteria) && $refined_criteria['requireEor'] == 1 ||
                        array_key_exists('requireNotEor', $refined_criteria) && $refined_criteria['requireNotEor'] == 1 )
                {
                    // Take this face value: the state is required
                    $query .= "state = '" . $db->real_escape_string($value);
                } else {
                    // We are allowing both EOR and non-EOR. Apply state consideration for EOR stamps, but also
                    //  allow non-EOR stamps that are independent of state.
                    // Imaginably we might someday need to consider other possibilities, in which case we'll need another
                    //  input criterion, but this is the common case.
                    $query .= "(state = '" . $db->real_escape_string($value) . "' OR (state IS NULL AND isEorStamp=0))";
                }
                $hasWhereClause = true;
            } else if ($name == 'issuedBefore') {
                if ($value == 'isNull') {
                    $query .= "issueDate IS NULL";
                } else {
                    "issueDate <= '" . $db->real_escape_string($value) . "'";
                }
                $hasWhereClause = true;
            } else if ($name == 'issuedAfter') {
                if ($value == 'isNull') {
                    $query .= "issueDate IS NULL";
                } else {
                    "issueDate >= '" . $db->real_escape_string($value) . "'";
                }
                $hasWhereClause = true;
            } else if ($name == 'expiresBefore') {
                if ($value == 'isNull') {
                    $query .= "expirationDate IS NULL";
                } else {
                    "expirationDate <= '" . $db->real_escape_string($value) . "'";
                }
                $hasWhereClause = true;
            } else if ($name == 'expiresAfter') {
                if ($value == 'isNull') {
                    $query .= "expirationDate IS NULL";
                } else {
                    "expirationDate >= '" . $db->real_escape_string($value) . "'";
                }
                $hasWhereClause = true;
            }
        } // END foreach ($refined_criteria...
        $query .= ' ORDER BY displayName;';
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586818872', "Hard DB error in stamp::getStamps", $db);
            return false;
        }
        
        $ret = Array();
        while ($row = $result->fetch_assoc()) {
            $ret[] = new Stamp($row['stampId']);
        }
        return $ret;        
    } // END public static function getStamps
 
    // Get Stamp object by the PHP constant name; should only ever get a non-EOR stamp.
    // INPUT $name
    // INPUT $customerId - defaults to current customer; 0 also means current customer
    // RETURNs a Stamp object, or false on failure
    public static function getStampByName($name, $customerId=null) {
        global $customer;
        global $logger;
        $db = DB::getInstance();
        
        if ( !$customerId ) {
            $customerId = $customer->getCustomerId(); 
        }
        $query = "SELECT stampId FROM " . DB__NEW_DATABASE . ".stamp ";
        $query .= "WHERE customerId = $customerId ";
        $query .= "AND name='" . $db->real_escape_string($name) . "';";
 		$result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1586818901', "Hard DB error in stamp::getStampByName", $db);
            return false;
        }
        if ($result->num_rows == 0) {
            return false;
        } else if ($result->num_rows >1) {
            // Shouldn't ever happen: means we inserted duplicate values
            $logger->errorDb('1586818955', $result->num_rows . " values returned, should be only 1.", $db);
            return false;
        } else {
            $row = $result->fetch_assoc();
            return new Stamp($row['stampId']);
        }
    }
}