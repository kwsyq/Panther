<?php 
/* inc/classes/CustomerPerson.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the CustomerPerson table.
This is specific to employees (not, for example, contractors who may have an account on the system but
 no row in this table).

Added 2020-05-26 JM

Public methods:
 * __construct($val)
 * getCustomerPersonId()
 * getCustomerId()
 * getPersonId()
 * getLegacyInitials()
 * setLegacyInitials($val)
 * getHireDate()
 * setHireDate($val)
 * getTerminationDate()
 * setTerminationDate($val)
 * getActiveWorkOrder()
 * setActiveWorkOrder($val)
 * getDaysBack()
 * setDaysBack($val)
 * getEmployeeId()
 * setEmployeeId($val)
 * getWorkerId()
 * setWorkerId($val)
 * getSmsPerm()
 * setSmsPerm($val)
 * getIsEor()
 * setIsEor($val)
 * getCustomer()
 * getPerson()
 * buildLink($urionly = false)
 * getEmailAndName()
 * save()
 
Public static methods; 
 * validate($customerPersonId, $unique_error_id=null)
 * getAll($activeOnly)
 * getFromPersonId($personId)

*/

class CustomerPerson  extends SSSEng {
    
    private $customerPersonId; // primary key
    private $customerId;       // foreign key into DB table customer
    private $personId;         // foreign key into DB table person
    private $legacyInitials;   // nothing "legacy" about this, initials that identify this person for the customer
    private $hireDate;
    private $terminationDate;
    private $activeWorkOrder;
    private $daysBack;         // how far back they can change their hours
    // Ignoring extraEor, which is going away for v2020-3 
    private $employeeId;
    private $workerId;
    private $smsPerm;          // SMS permission string.
    private $isEor;
    
    /* Constructor takes identification of customer, which (unlike some 
        older classes) takes only one form: customerPersonId */
    public function __construct($id, User $user = null) {
        parent::__construct($user);
        $this->load($id);
    }
    
    private function load($val) {
        // INPUT $val here is input $id for constructor. 
        if (is_numeric($val)) {
            // Read row from DB table CustomerPerson 
            $query = "SELECT customerPersonId, customerId, personId, legacyInitials, hireDate, terminationDate, ";
            $query .= "activeWorkOrder, daysBack, employeeId, workerId, smsPerm, isEor ";
            $query .= "FROM " . DB__NEW_DATABASE . ".customerPerson ";
            $query .= "WHERE customerPersonId = " . intval($val) . ";";
            
            $result = $this->db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.
                    // Set all of the private members that represent the DB content.
                    $row = $result->fetch_assoc();
                    $this->customerPersonId = $row['customerPersonId'];
                    $this->customerId = $row['customerId'];
                    $this->personId = $row['personId'];
                    $this->legacyInitials = $row['legacyInitials'];
                    $this->hireDate = $row['hireDate'];
                    $this->terminationDate = $row['terminationDate'];
                    $this->activeWorkOrder = $row['activeWorkOrder'];
                    $this->daysBack = $row['daysBack'];
                    $this->employeeId = $row['employeeId'];
                    $this->workerId = $row['workerId'];
                    $this->smsPerm = $row['smsPerm'];
                    $this->isEor = $row['isEor'];
                } else {
                    $this->logger->errorDb('1590506977', "No rows found", $this->db);
                }
            } else {
                $this->logger->errorDb('1590506982', "Invalid input to create CustomerPerson object: " . 
                    (is_string($val) || is_numeric($val) ? $val : ''), $this->db);                
            }                
        } else {
            $this->logger->error2('1590506989', "Invalid non-numeric input to create CustomerPerson object: " . (is_string($val) ? $val : ''));
        }
    } // END private function load
    
    // No "set" methods for the columns/members that constitute identity
    public function getCustomerPersonId() {
        return $this->customerPersonId;
    }
    public function getCustomerId() {
        return $this->customerId;
    }
    public function getPersonId() {
        return $this->personId;
    }
    
    // The rest of these get "set" methods as well as "get" 
    public function getLegacyInitials() {
        return $this->legacyInitials;
    }

    public function setLegacyInitials($val) {
        $val = truncate_for_db($val, ' CustomerPerson : LegacyInitials', 8, '637387210687814369'); // truncate for db.
        
        if ($val) {    
            $existingMatch = $this->getCustomer()->getCustomerPersonFromInitials($val);
            if ($existingMatch && $existingMatch->getCustomerPersonId() != $this->customerPersonId) {
                $this->logger->error2("637399304749288012", "These initials  '$val' are already associated with another employee, " .
                    $existingMatch->getPerson()->getFormattedName(true) );
            } else {
                $this->legacyInitials = $val;
            }
        } // else blank, which is OK according to http://sssengwiki.com/Documentation+V3.0#customerPerson       
    }
    
    public function getHireDate() {
        return $this->hireDate;
    }
    public function setHireDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->hireDate = $val;
        } else {
            $this->logger->info2("1603195974", "Bad hire date '$val'");
            $this->hireDate = '0000-00-00 00:00:00';
        }
    }
    
    public function getTerminationDate() {
        return $this->terminationDate;
    }
    public function setTerminationDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->terminationDate = $val;
        } else {
            $this->logger->info2("1603195975", "Bad termination date '$val'");
            $this->terminationDate = '0000-00-00 00:00:00';
        }
    }
    
    public function getActiveWorkOrder() {
        return $this->activeWorkOrder;
    }
    public function setActiveWorkOrder($val) {
        if( WorkOrder::validate($val) ) { 
            $this->activeWorkOrder = intval($val);
        } else {
            $this->logger->error2("637390644927940590", "Invalid workOrderId \$val =  ".$val);
            $this->activeWorkOrder = 0; // default in DB.
        }
    }
    
    public function getDaysBack() {
        return $this->daysBack;
    }
    public function setDaysBack($val) {
        if (($val === null) || (is_numeric($val)) && ($val >=0 && $val <= 365)) {
            $this->daysBack = intval($val);
        } else {
            $this->logger->error2("637390671481534792", "Bad daysBack ".$val);
            $this->daysBack = 3; // default in DB.
        }
    }
    
    public function getEmployeeId() {
        return $this->employeeId;
    }
    public function setEmployeeId($val) {
        if (($val != null) && (is_numeric($val)) && ($val >=1 && strlen($val) <= 10)) {
            $this->employeeId = intval($val); 
        } else {
            $this->logger->info2("1603195977", "Bad EmployeeId ".$val);
            $this->employeeId = 0; // default in DB.
        }
    }
    
    public function getWorkerId() {
        return $this->workerId;
    }
    public function setWorkerId($val) {
        $val = truncate_for_db($val, ' CustomerPerson:WorkerId', 32, '637387210036972581'); // truncate for db.
        $this->workerId = $val;
    }
    
    // See SMS_PERM flags in inc/config.php for context
    public function getSmsPerm() {
        return $this->smsPerm;
    }    
    public function setSmsPerm($val) {
        // 2020-11-13 JM: >>>00001 This next test really should be in terms of the defined SMS permissions.
        // I've fixed it so at least there won't be false negatives (previously 0, the default, failed).
        if (($val != null) && (is_numeric($val)) && ($val >=0 && strlen($val) <= 10)) {
            $this->smsPerm = intval($val);
        } else {
            $this->logger->info2("1603195975", "Bad SMS Perms string".$val);
            $this->smsPerm = 0; // default in DB.
        }
    }
    
    // quasi-Boolean: 0 or 1
    public function getIsEor() {
        return $this->isEor;
    }    
    public function setIsEor($val) {
        if (($val == 1) || ($val == 0)) {
            $this->isEor = intval($val); 
        } else {
            $this->isEor = 0; // default in DB.
            $this->logger->error2("637390649217722018", "Bad isEor " . $val . ' must be 0 or 1');
        }
    }
    
    // Getting related objects
    public function getCustomer() {
        return new Customer($this->customerId);
    }
    public function getPerson() {
        return new Person($this->personId);
    }

    // Prevent using this function inherited from SSSEng. Log an error if someone tries.
    public function buildLink($urionly = false) {
        $this->logger->error2('637396492568802869', "We can not used this method. We don't have a 'customerperson' Url to redirect to.");
        return REQUEST_SCHEME . '://' . HTTP_HOST . '/';
    }
    
    // should be called along the line of list($target_email_address, $firstName, $lastName) = $customerPerson->getEmailAndName();
    // 2020-10-22 JM: >>>00014 I'm not saying this is wrong, but I am also not convinced it is entirely right. Can we have more than
    // one email address for a CompanyPerson? I'm not sure, but if we can then we seem to be arbitrarily picking the first one we encounter,
    // and I have no idea why we would be confident that first one is the best one. Probably worth studying.
    public function getEmailAndName() {
        $customer = $this->getCustomer();
        $companyId = $customer->getCompanyId();
        $company = new Company($companyId);
        $person = $this->getPerson();
        $firstName = $person->getFirstName();
        $lastName = $person->getLastName();
        $target_email_address = '';
        
        $companyPersons = $person->getCompanyPersons();
        $companyPerson = null;
        $companyPersonId = null;
        foreach($companyPersons AS $companyPerson2) {
            if ($companyId == $companyPerson2->getCompanyId()) {
                $companyPersonId = $companyPerson2->getCompanyPersonId();
                $companyPerson = $companyPerson2;
                break;
            }
        }
        if ($companyPerson) {
            $contacts = $companyPerson->getContacts();
            foreach ($contacts AS $contact) {
                if ($contact['type'] == "Email") {
                    $target_email_address = $contact['dat'];
                    break;
                }
            }            
        } else {
            $this->logger->error2('1591721968', "Cannot find companyPerson for $firstName (personId = ". $person->getPersonId() .") at customer " . $customer->getCustomerId());
        }
        return Array($target_email_address, $firstName, $lastName);
    }
    
    public function save() {
        $query = "UPDATE " . DB__NEW_DATABASE . ".customerPerson SET ";
        $query .= "legacyInitials='" . $this->db->real_escape_string($this->legacyInitials) . "', ";
        $query .= "hireDate='" . $this->db->real_escape_string($this->hireDate) . "', ";
        $query .= "terminationDate='" . $this->db->real_escape_string($this->terminationDate) . "', ";
        $query .= "activeWorkOrder={$this->activeWorkOrder}, ";
        $query .= "daysBack={$this->daysBack}, ";
        $query .= "employeeId={$this->employeeId}, ";
        $query .= "workerId='" . $this->db->real_escape_string($this->workerId) . "', ";
        $query .= "smsPerm={$this->smsPerm}, ";
        $query .= "isEor={$this->isEor} ";
        $query .= "WHERE customerPersonId = " . $this->customerPersonId . ";";
        
        $result = $this->db->query($query);
        if (!$result)  {
            $this->logger->errorDb('1590519703', "Hard error", $this->db);
        }
    }
    
    // Return true if the id is a valid customerPersonId, false if not
    // INPUT $customerPersonId: customerPersonId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($customerPersonId, $unique_error_id=null) {
        global $logger;
        $db = DB::getInstance();
        
        $ret = false;
        $query = "SELECT customerPersonId FROM " . DB__NEW_DATABASE . ".customerPerson WHERE customerPersonId=$customerPersonId;";
        $result = $db->query($query);            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1590179377', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    /** 
        * @param bool $activeOnly, indicates whether to filter by hire date & termination date.
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $ret. RETURN an array of CustomerPerson objects; implicitly, for the current customer.
    */
    public static function getAll($activeOnly, &$errCode = false) {
        global $customer, $logger;
        $errCode = false;
        $db = DB::getInstance();
        $ret = Array();
        
        $query  = "SELECT customerPersonId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".customerPerson ";
        $query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
        if ($activeOnly) {
            $query .= "AND terminationDate > now() AND hireDate <= now() ";
        }
        $query .= "ORDER BY legacyInitials;";        
        $result = $db->query($query);            
        if (!$result)  {
            $logger->errorDb('1591663834', "Hard error", $db);
            $errCode = true;
        } else {
            while ($row=$result->fetch_assoc()) {
                $ret[] = new CustomerPerson($row['customerPersonId']);
            }
        }

        return $ret;        
    }

    // RETURN a CustomerPerson object based on INPUT $personId (or null if there is no such customerPerson)
    public static function getFromPersonId($personId) {
        global $customer, $logger;
        $db = DB::getInstance();
        
        $query  = "SELECT customerPersonId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".customerPerson ";
        $query .= "WHERE customerId = " . intval($customer->getCustomerId()) . " ";
        $query .= "AND personId = " . intval($personId) . ";";
        $result = $db->query($query);            
        if (!$result)  {
            $logger->errorDb('1591812155', "Hard error", $db);
            return null;
        } else if ($result->num_rows == 1) {
            $row=$result->fetch_assoc();
            return new CustomerPerson($row['customerPersonId']);
        } else {
            $logger->error2('1591812157', $result->num_rows . " rows in CustomerPerson for personID $personId, should be exactly one such row");
            return null;
        }
    }
}


?>
