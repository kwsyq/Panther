<?php 
/* inc/classes/Company.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Company table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular company.
* Public functions:
** __construct($id = null, User $user = null)
** getCompanyId()
** getCustomerId()
** getCompanyName()
** getCompanyNickname()
** getCompanyURL()
** getCompanyLicense()
** getCompanyTaxId()
** getName()
** getPrimaryBillingProfileId() - added 2020-02-12 JM
** setPrimaryBillingProfileId($val) - added 2020-02-12 JM
** getBracketCompany - added 2020-06-05 JM
** addBillingProfile($val)
** getBillingProfiles($activeOnly=false, &$errCode=false)
** getCompanyPersons(&$errCode=false)
** getJobs(&$errCode=false)
** getLocations(&$errCode=false)
** getEmails(&$errCode=false)
** addEmail($emailAddress)
** updateEmail($val, &$integrityIssues=false)
** updateLocationType($val)
** getPhones(&$errCode=false)
** getAccountsPayableTypes() // removed
** addPhone($val)
** updatePhone($val)
** update($val)
** save()

** public static function validate($companyId, $unique_error_id=null)
** public static function errorToText($errCode)
*/


class Company extends SSSEng {
    // The following correspond exactly to the columns of DB table Company
    // See documentation of that table for further details.
    private $companyId;        // primary key
    private $customerId;       // foreign key to Customer table; as of 2019-02, only customer is SSS
    private $companyName;      // string, VARCHAR(128)
    private $companyNickname;  // string, VARCHAR(128)
    private $companyURL;       // string, VARCHAR(255) 
    private $companyLicense;   // string, VARCHAR(32), very often null or blank
    private $companyTaxId;     // string, VARCHAR(32), very often null or blank 
    private $primaryBillingProfileId;       // foreign key to billingProfile table - introduced 2020-02-12 JM
    private $isBracketCompany; // 0 or 1, effectively Boolean, introduced 2020-06-05 JM for v2020-3

    // INPUT $id: May be either of the following:
    //  * a companyId from the Company table
    //  * an associative array which should contain an element for each columnn
    //    used in the Company table, corresponding to the private variables
    //    just above.
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
    // INPUT $user: User object, typically current user. 
    //  >>>00023: JM 2019-02-18: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI). 
    public function __construct($id = null, User $user = null) {		
        parent::__construct($user);
        $this->load($id);
    }
    
    
    /*
     * 
     * 
     *  [BEGIN Martin comment] 
     *  do checks here to make sure that only companies from a certain customer are viewed or whatever needs to happen here
     *  [END Martin comment]
     *  >>>00016: Clearly that referred to a future intention rather than something that has already been done. - JM 2019-02-18
     * 
     * 
     */

    // INPUT $val here is input $id for constructor. 
    private function load($val) {
        if (is_numeric($val)) {
            // Read row from DB table Company
            $query = "SELECT c.* ";
            $query .= "FROM " . DB__NEW_DATABASE . ".company c ";
            $query .= "WHERE  c.companyId = " . intval($val);
            
            $result = $this->db->query($query);

            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.
                        
                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();
    
                    $this->setCompanyId($row['companyId']);
                    $this->setCustomerId($row['customerId']);
                    $this->setCompanyName($row['companyName']);
                    $this->setCompanyNickname($row['companyNickname']);
                    $this->setCompanyURL($row['companyURL']);
                    $this->setCompanyLicense($row['companyLicense']);
                    $this->setCompanyTaxId($row['companyTaxId']);
                    $this->setPrimaryBillingProfileId($row['primaryBillingProfileId']);
                    $this->setIsBracketCompany($row['isBracketCompany']);
                } else {
                    $this->setCompanyId(0);
                    $this->logger->warn2('1569417944', 'load => The companyID is not found in the database!' . $val);
                } 
            } else {
                $this->setCompanyId(0);
                $this->logger->warn2('1569418217', 'load => Query result is null ' . $query);
            } 
              // haven't noted each instance.
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from 
            //  input associative array
            $this->setCompanyId($val['companyId']);
            $this->setCustomerId($val['customerId']);
            $this->setCompanyName($val['companyName']);
            $this->setCompanyNickname($val['companyNickname']);
            $this->setCompanyURL($val['companyURL']);
            $this->setCompanyLicense($val['companyLicense']);
            $this->setCompanyTaxId($val['companyTaxId']);
            $this->setPrimaryBillingProfileId($val['primaryBillingProfileId']);
            $this->setIsBracketCompany($val['isBracketCompany']);
        } else {

            $this->logger->warn2('1571772487', 'load => Company Id not numeric and not array: ['.$val.']');

        }
    } // END private function load

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getCompanyId();
    }
    
    // NOTE that all of the following "set" functions are private: we use this only as
    //  part of our own load and save mechanisms, not to be used from outside the class.
    
    // Set primary key
    // INPUT $val: primary key (companyId)
    private function setCompanyId($val) {
        if ( ($val != null) && (is_numeric($val)) && ($val >=1) ) {
            $this->companyId = intval($val);
        } else {
            $this->logger->error2("637417361483283292", "Invalid input for companyId : [$val]" );
        } 
    }

    // Set customerId
    // INPUT $val: foreign key to Customer table; as of 2019-02, only customer is SSS
    private function setCustomerId($val) {	
        if (Customer::validate($val)) {
            $this->customerId = intval($val);
        } else {
            $this->logger->error2("637417362402727015", "Invalid input for CustomerId : [$val]" );
        }
    }	
    
    // Set company name
    // INPUT $val: string; anything past 128 characters is silently ignored.
    private function setCompanyName($val) {
        $val = truncate_for_db($val, 'CompanyName', 128, '637417362752630396'); // truncate for db.
        $this->companyName = $val;
    }	

    // Set company nickname
    // INPUT $val: string; anything past 128 characters is silently ignored.
    private function setCompanyNickname($val) {	
        $val = truncate_for_db($val, 'companyNickname', 128, '637417363199068722'); // truncate for db.
        $this->companyNickname = $val;
    }	
        
    // Set company URL
    // INPUT $val: string; anything past 255 characters is silently ignored.
    private function setCompanyURL($val) {
        if ($val) {
            $val = truncate_for_db($val, 'CompanyURL', 255, '637417363761207476'); // truncate for db.
            $url = strpos($val, 'http') !== 0 ? "http://$val" : $val;

            if ( filter_var( $url, FILTER_VALIDATE_URL) !== false ) {
                $this->companyURL = $val;
            } else {
                $this->logger->error2("637419171213751175", "Invalid input for companyURL : [$val]" );
            } 
        } else {
            $this->companyURL = $val;
        }
    }
    
    // Set company license
    // INPUT $val: string; anything past 32 characters is silently ignored.
    private function setCompanyLicense($val) {
        $val = truncate_for_db($val, 'companyLicense', 32, '637417364169055507'); // truncate for db.
        $this->companyLicense = $val;
    }
    
    // Set company tax ID
    // INPUT $val: string; anything past 32 characters is silently ignored.
    private function setCompanyTaxId($val) {	
        $val = truncate_for_db($val, 'companyTaxId', 32, '637417364704368470'); // truncate for db.
        $this->companyTaxId = $val;
    }

    
    public function setPrimaryBillingProfileId($val) {
        if ($val===null || $val==0) { // JM 2020-04-10 fixing http://bt.dev2.ssseng.com/view.php?id=118: shouldn't ever be 0, but if it is force it to null
            $this->primaryBillingProfileId = null;
        } else if ( BillingProfile::validate($val) ) {
            $billingProfile = new BillingProfile($val);
            if ($billingProfile) {
                if ($billingProfile->getCompanyId() == $this->companyId) {
                    $this->primaryBillingProfileId = $val;
                } else {
                    $this->logger->error2('1581537636', 'setPrimaryBillingProfileId: billingProfileId '.$val.' has companyId ' .
                      $billingProfile->getCompanyId() . ', can\'t make it primary for ' . $this->getCompanyId());
                }
            } else {
                $this->logger->error2('1581537686', 'setPrimaryBillingProfileId can\'t create billing profile for billingProfileId ['.$val.']');
            }
        } else {
            $this->logger->error2('1581537736', 'setPrimaryBillingProfileId => invalid billingProfileId ['.$val.']');
        }
    }
    
    private function setIsBracketCompany($val) {
        $this->isBracketCompany = $val;
    }

    // RETURN primary key
    public function getCompanyId() {	
        return $this->companyId;	
    }
    
    // RETURN foreign key to Customer table; as of 2019-02, only customer is SSS
    public function getCustomerId() {	
        return $this->customerId;	
    }
    
    // RETURN company name
    public function getCompanyName() {		
        return $this->companyName;
    }

    // RETURN company nickname (may be null or same as company name)
    public function getCompanyNickname() {		
        return $this->companyNickname;		
    }
    
    // RETURN company URL (can be empty or null)
    public function getCompanyURL() {		
        return $this->companyURL;		
    }
    
    // RETURN company license (can be empty or null)
    public function getCompanyLicense() {		
        return $this->companyLicense;		
    }
    
    // RETURN company tax ID (can be empty or null)
    public function getCompanyTaxId() {		
        return $this->companyTaxId;		
    }	
    
    public function getPrimaryBillingProfileId() {
        return $this->primaryBillingProfileId;
    }
    
    public function getIsBracketCompany() {
        return $this->isBracketCompany;
    }
    
    // Synonym for getCompanyName()
    // Martin comment: for crumbs
    public function getName() {
        return $this->getCompanyName();
    }	
    
    //INPUT $val: associative array with the following members; inserts a new row in DB table BillingProfile accordingly: 
    //  * 'multiplier': as in DB table BillingProfile
    //  * 'dispatch': as in DB table BillingProfile
    //  * 'termsId': as in DB table BillingProfile
    //  * 'contractLanguageId': as in DB table BillingProfile
    //  * 'gracePeriod': as in DB table BillingProfile
    //  * 'usename': quasi-Boolean 
    //  * 'companyPersonId': used only if 'useName' is "truthy"; if so, it also goes in the billingProfile row.
    //    If used, must be associated with the companyId of this Company object.
    //    >>>00001 JM: 2019-02-18, actually I think the picture here may be even more complicated than what I wrote,
    //     and someone (possibly me) needs to study this more closely. The policy doesn't seem to make a ton of sense, 
    //     and Martin never documented the intent. If/when someone completely sorts this out, please also update
    //     documentation of fb/addbillingprofile.php
    //  * 'varyLocationId': string, as follows
    //  **  Should begin with one of the following: "pe:", "pl:", "ce:", "cl:"
    //  ** The remainder of the string should be understood as a foreign key into one of the following tables, respectively: 
    //      "pe:": personEmail, "pl:": personLocation, "ce:": companyEmail, "cl:": companyLocation.
    //  
    //  >>>00006: the "pe:", "pl:", "ce:", "cl:" cases below are very similar, probably could pull a lot of common code into
    //   a private function.
    public function addBillingProfile($val) {

        // Prior to 2019-12-02, this was really weird: ignored $val, used $_REQUEST instead. We got away with this
        //  only because the one place this was called, the superglobal $_REQUEST was, indee, what was passed in.
        /* OLD CODE REMOVED 2019-12-02 JM
        $companyPersonId = isset($_REQUEST['companyPersonId']) ? intval($_REQUEST['companyPersonId']) : 0;
        $useName = isset($_REQUEST['useName']) ? intval($_REQUEST['useName']) : 0;

        $multiplier = isset($_REQUEST['multiplier']) ? $_REQUEST['multiplier'] : 1;		
        if (!is_numeric($multiplier)) {
            $multiplier = 1;
        }
        
        $dispatch = isset($_REQUEST['dispatch']) ? intval($_REQUEST['dispatch']) : 0;
        $termsId = isset($_REQUEST['termsId']) ? intval($_REQUEST['termsId']) : 0;
        $contractLanguageId = isset($_REQUEST['contractLanguageId']) ? intval($_REQUEST['contractLanguageId']) : 0;
        $gracePeriod = isset($_REQUEST['gracePeriod']) ? intval($_REQUEST['gracePeriod']) : 0;		
        */
        // BEGIN REPLACEMENT CODE 2019-12-02 JM

        // George 2020-04-28. companyId was not tested to see if isset in previous version. 
        $useName = isset($val['useName']) ? intval($val['useName']) : 0;
        $dispatch = isset($val['dispatch']) ? intval($val['dispatch']) : 0;
        $gracePeriod = isset($val['gracePeriod']) ? intval($val['gracePeriod']) : 0;

        $multiplier = isset($val['multiplier']) ? $val['multiplier'] : 1;
        if (!is_numeric($multiplier)) {
            $multiplier = 1;
        }
        
        $terms = getTerms();
        foreach ($terms as $value) {
            $termsIdsDB[] = $value["termsId"]; //Build an array with valid termName's from DB, table terms.
        }

        if ( isset($val['termsId']) && in_array($val["termsId"], $termsIdsDB) ) {
            $termsId = intval($val['termsId']);
        } else {
            $termsId = 0; //  Choose Terms value.
        }


        //$contractLanguageId = isset($val['contractLanguageId']) ? intval($val['contractLanguageId']) : 0;
        $contractLanguageIdsDB = array(); // Declare an array of contractLanguageId's.
        $files = getContractLanguageFiles(); // get Contract Language Files as an array
        foreach ($files as $value) {
            $contractLanguageIdsDB[] = $value["contractLanguageId"]; //Build an array with valid contractLanguageId's from DB, table contractlanguage.
        }
        if ( isset($val["contractLanguageId"]) && in_array($val["contractLanguageId"], $contractLanguageIdsDB) ) {
            $contractLanguageId = intval($val["contractLanguageId"]);
        } else {
            $contractLanguageId = 0;
        }

        // END REPLACEMENT CODE 2019-12-02 JM

        $companyPersonId = isset($val['companyPersonId']) ? intval($val['companyPersonId']) : 0;
        // BEGIN: validate any input companyPersonId, make sure it matches  
        if ($companyPersonId>0) {
            $query = "SELECT cp.companyPersonId "; // George 2020-11-26. Checking for existance
            $query .= "FROM " . DB__NEW_DATABASE . ".companyPerson cp ";
            $query .= "WHERE cp.companyId = " . intval($this->getCompanyId()) . " ";
            $query .= "AND cp.companyPersonId = " . intval($companyPersonId);

            $result = $this->db->query($query);
            if (!$result) { 
                $this->logger->errorDb('637286846801541127', 'addBillingProfile => Company Person Id: DB error', $this->db);
                return false;
            }

            if ($result->num_rows == 0) { // not in the database
                $this->logger->warn2('1569441061', 'addBillingProfile => Company Person not found companyId: ['.$this->getCompanyId()."] companyPersonId: [".$companyPersonId."]");            
                $companyPersonId = 0;
            } 
        } 
        // END: validate any input companyPersonId 

        $varyLocationId = isset($val['varyLocationId']) ? $val['varyLocationId'] : '';

        if ($varyLocationId != "") {
            $checksLocationId = array("pe", "pl", "ce", "cl"); // only this values.
            $parts = explode(":", $varyLocationId);
            if (!in_array($parts[0], $checksLocationId)) {
                $this->logger->warn2('637420001033062795', 'addBillingProfile => invalid varyLocationId, companyId: ['.$this->getCompanyId()."] varyLocationId: [".$val['varyLocationId']."]");
                return false;
            }
        }
        
        // Only if we have a companyPersonId do we want to use a person email or location. 
        if ( intval($companyPersonId) ) {
        
            $checks = array('pe','pl'); // Person
            $parts = explode(":", $varyLocationId);
            if (count($parts) == 2) {
                if (in_array($parts[0], $checks)) {
                    if (intval($parts[1])) {
                        $cpid = ($useName==1?intval($companyPersonId):0);
                        if ($parts[0] == 'pe') {
                            // Make sure we don't insert a billing profile that matches one already in there.
                            $exists = false;
                            
                            $query = " SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
                            $query .= " FROM " . DB__NEW_DATABASE . ".billingProfile ";
                            $query .= " WHERE companyId = " . intval($this->getCompanyId()) . " ";
                            $query .= " AND companyPersonId = " . $cpid . " ";
                            $query .= " AND personEmailId = " . intval($parts[1]) . " ";	
                            $query .= " AND dispatch = " . intval($dispatch) . " ";
                            $query .= " AND termsId = " . intval($termsId) . " ";
                            $query .= " AND contractLanguageId = " . intval($contractLanguageId) . " ";
                            $query .= " AND gracePeriod = " . intval($gracePeriod) . " ";
                            $query .= " AND multiplier = " . $this->db->real_escape_string($multiplier) . " ";

                            $result = $this->db->query($query);
                            
                            if ($result) {                                 
                                $exists = ($result->num_rows > 0);
                            } else {
                                $this->logger->errorDb('1569441283', 'addBillingProfile => Error query', $this->db);
                                return false;
                            } 
    
                            if (!$exists) {										
                                $query = " INSERT INTO " . DB__NEW_DATABASE . ".billingProfile (" . 
                                    "companyId, companyPersonId, personEmailId, companyEmailId, personLocationId, companyLocationId, " . 
                                    "dispatch, termsId, contractLanguageId, gracePeriod, multiplier) VALUES (";
                                $query .= " " . intval($this->getCompanyId()) . " ";
                                $query .= " ," . $cpid . " ";
                                $query .= " ," . intval($parts[1]) . " "; // personEmailId
                                $query .= " ,0 ";                         // companyEmailId
                                $query .= " ,0 ";                         // personLocationId
                                $query .= " ,0 ";                         // companyLocationId									
                                $query .= "  ," . intval($dispatch) . " ";
                                $query .= "  ," . intval($termsId) . " ";
                                $query .= "  ," . intval($contractLanguageId) . " ";
                                $query .= "  ," . intval($gracePeriod) . " ";
                                $query .= "  ," . $this->db->real_escape_string($multiplier) . " ";									
                                $query .= " ) ";
                                
                                $result = $this->db->query($query); 
                                                          
                                if (!$result) {                                    
                                    $this->logger->errorDb('1569441283', 'addBillingProfile => Error query:', $this->db);
                                    return false;
                                }
                                
                            }
                        }		
                            
                        if ($parts[0] == 'pl') {
                            // Parallel to the 'pe' case above
                            $exists = false;
                            
                            $query = " SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
                            $query .= " FROM " . DB__NEW_DATABASE . ".billingProfile ";
                            $query .= " WHERE companyId = " . intval($this->getCompanyId()) . " ";
                            $query .= " AND companyPersonId = " . $cpid . " ";
                            $query .= " AND personLocationId = " . intval($parts[1]) . " ";
                            $query .= " AND dispatch = " . intval($dispatch) . " ";
                            $query .= " AND termsId = " . intval($termsId) . " ";
                            $query .= " AND contractLanguageId = " . intval($contractLanguageId) . " ";
                            $query .= " AND gracePeriod = " . intval($gracePeriod) . " ";
                            $query .= " AND multiplier = " . $this->db->real_escape_string($multiplier) . " ";
                            
                            $result = $this->db->query($query);

                            if ($result) { 
                                $exists = ($result->num_rows > 0);
                            } else {
                                $this->logger->errorDb('1569441498', 'addBillingProfile', $this->db);
                                return false;
                            }
                                
                            if (!$exists) {		
                                $query = " INSERT INTO " . DB__NEW_DATABASE . ".billingProfile (companyId, companyPersonId,personEmailId,companyEmailId,personLocationId,companyLocationId,dispatch,termsId,contractLanguageId,gracePeriod,multiplier) VALUES (";
                                $query .= " " . intval($this->getCompanyId()) . " ";
                                $query .= " ," . $cpid . " ";
                                $query .= " ,0 ";
                                $query .= " ,0 ";
                                $query .= " ," . intval($parts[1]) . " ";
                                $query .= " ,0 ";									
                                $query .= "  ," . intval($dispatch) . " ";
                                $query .= "  ," . intval($termsId) . " ";
                                $query .= "  ," . intval($contractLanguageId) . " ";
                                $query .= "  ," . intval($gracePeriod) . " ";
                                $query .= "  ," . $this->db->real_escape_string($multiplier) . " ";
                                $query .= " ) ";
                                
                                $result = $this->db->query($query); 
                                                          
                                if (!$result) {
                                    $this->logger->errorDb('1569441553', 'addBillingProfile', $this->db);
                                    return false;
                                }
                                
                            }
                        }
                    }		
                }						
            }	
        }
        
        // There can be a company email or location even without a companyPersonId
        //$varyLocationId = isset($val['varyLocationId']) ? $val['varyLocationId'] : '';
        $checks = array('ce','cl');				
        $parts = explode(":", $varyLocationId);				
        if (count($parts) == 2) {					
            if (in_array($parts[0], $checks)) {         
                if (intval($parts[1])) {                    
                    $cpid = (intval($useName)) ? intval($companyPersonId) : 0;							
                    
                    if ($parts[0] == 'ce') {
                        // Parallel to the 'pe' case above    
                        $exists = false;
                            
                        $query = " SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
                        $query .= " FROM " . DB__NEW_DATABASE . ".billingProfile ";
                        $query .= " WHERE companyId = " . intval($this->getCompanyId()) . " ";
                        $query .= " AND companyPersonId = " . $cpid . " ";							
                        $query .= " AND companyEmailId = " . intval($parts[1]) . " ";                            
                        $query .= " AND dispatch = " . intval($dispatch) . " ";
                        $query .= " AND termsId = " . intval($termsId) . " ";
                        $query .= " AND contractLanguageId = " . intval($contractLanguageId) . " ";
                        $query .= " AND gracePeriod = " . intval($gracePeriod) . " ";
                        $query .= " AND multiplier = " . $this->db->real_escape_string($multiplier) . " ";
                        
                        $result = $this->db->query($query);

                        if ($result) { 
                            $exists = ($result->num_rows > 0);
                        } else {
                            $this->logger->errorDb('1571773925', 'addBillingProfile', $this->db);
                            return false;
                        }
                            
                        if (!$exists) {                    
                            $query = " INSERT INTO " . DB__NEW_DATABASE . ".billingProfile (companyId, companyPersonId,personEmailId,companyEmailId,personLocationId,companyLocationId,dispatch,termsId,contractLanguageId,gracePeriod,multiplier) VALUES (";
                            $query .= " " . intval($this->getCompanyId()) . " ";
                            $query .= " ," . $cpid . " ";
                            $query .= " ,0 ";
                            $query .= " ," . intval($parts[1]) . " ";
                            $query .= " ,0 ";
                            $query .= " ,0 ";                            
                            $query .= "  ," . intval($dispatch) . " ";
                            $query .= "  ," . intval($termsId) . " ";
                            $query .= "  ," . intval($contractLanguageId) . " ";
                            $query .= "  ," . intval($gracePeriod) . " ";
                            $query .= "  ," . $this->db->real_escape_string($multiplier) . " ";
                            $query .= " ) ";
                    
                                
                            $result = $this->db->query($query); 
                                                      // ditto throughout file on all INSERTs and UPDATEs.      
                            if (!$result) {                     
                                $this->logger->errorDb('1569441603', 'addBillingProfile', $this->db);
                                return false;
                            }
                                                       
                        }                            
                    }
                    
                    if ($parts[0] == 'cl') {
                        // Parallel to the 'pe' case above    
                        $exists = false;
                            
                        $query = " SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
                        $query .= " FROM " . DB__NEW_DATABASE . ".billingProfile ";
                        $query .= " WHERE companyId = " . intval($this->getCompanyId()) . " ";
                        $query .= " AND companyPersonId = " . $cpid . " ";								
                        $query .= " AND companyLocationId = " . intval($parts[1]) . " ";                            
                        $query .= " AND dispatch = " . intval($dispatch) . " ";
                        $query .= " AND termsId = " . intval($termsId) . " ";
                        $query .= " AND contractLanguageId = " . intval($contractLanguageId) . " ";
                        $query .= " AND gracePeriod = " . intval($gracePeriod) . " ";
                        $query .= " AND multiplier = " . $this->db->real_escape_string($multiplier) . " ";								
                        
                        $result = $this->db->query($query);

                        if ($result) { 
                            $exists = ($result->num_rows > 0);
                        } else {
                            $this->logger->errorDb('1571773973', 'addBillingProfile', $this->db);
                            return false;
                        }
                            
                        if (!$exists) {                                
                            $query = " INSERT INTO " . DB__NEW_DATABASE . ".billingProfile (companyId, companyPersonId,personEmailId,companyEmailId,personLocationId,companyLocationId,dispatch,termsId,contractLanguageId,gracePeriod,multiplier) VALUES (";
                            $query .= " " . intval($this->getCompanyId()) . " ";
                            $query .= " ," . $cpid . " ";
                            $query .= " ,0 ";
                            $query .= " ,0 ";
                            $query .= " ,0 ";
                            $query .= " ," . intval($parts[1]) . " ";                            
                            $query .= "  ," . intval($dispatch) . " ";
                            $query .= "  ," . intval($termsId) . " ";
                            $query .= "  ," . intval($contractLanguageId) . " ";
                            $query .= "  ," . intval($gracePeriod) . " ";
                            $query .= "  ," . $this->db->real_escape_string($multiplier) . " ";
                            $query .= " ) ";
                                
                            $result = $this->db->query($query);
                                                      
                            if (!$result) {                                
                                $this->logger->errorDb('1569441630', 'addBillingProfile', $this->db);
                                return false;
                            }
                            
                        }                                
                    }                    
                }                    
            }                
        }
        return true;		
    } // END public function addBillingProfile
    
    
    // getBillingProfiles RETURNS an array of associative arrays, each corresponding 
    //  to one billingProfile that applies to this company. No particular order. 
    // INPUT $activeOnly - Boolean, default false. If truthy, limit this to active billingProfiles. - added JM 2020-02-12
    //  Each associative array has the following members:
    //   * 'billingProfile': BillingProfile object
    //   * 'loc': either an email address or a formatted location, whichever applies. Suitable for display.
	//   * 'isPrimary': (added 2020-02-12 JM) true if this is marked as the primary billing profile for this company, false otherwise.
	//     NOTE that it is perfectly possible not to have a marked primary billing profile 
    public function getBillingProfiles($activeOnly=false, &$errCode=false) {
        $errCode = false;
        $ret = array();
    
        $query = "SELECT * ";
        $query .= "FROM " . DB__NEW_DATABASE . ".billingProfile ";
        $query .= "WHERE companyId = " . intval($this->getCompanyId()) . " ";
        if ($activeOnly) {
            $query .= "AND active != 0 ";
        }
        $query .= "AND companyId != 0 "; // Not sure why Martin had this last condition, but it's harmless - JM 2020-02
    
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('1571774719', 'getBillingProfiles', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $record = array();
                $record['loc'] = ""; // George 2020-11-27
                $bp = new BillingProfile($row['billingProfileId']);
                
                if (intval($bp->getPersonEmailId())) {
                    $query2 = "SELECT emailAddress " . // reworked 2020-02-28 JM, Was " select * " but we are really just getting emailAddress
                                "FROM " . DB__NEW_DATABASE . ".personEmail WHERE personEmailId = " . $bp->getPersonEmailId();
                    
                    $result2 = $this->db->query($query2);
                    if ($result2) { 
                        if ($result2->num_rows > 0) {
                            $row2 = $result2->fetch_assoc();
                            $record['loc'] = $row2['emailAddress'];
                        }
                    } else {
                        $this->logger->errorDb('1571774468', 'getBillingProfiles', $this->db);
                        $errCode = true;
                    }
                }
                if (intval($bp->getPersonLocationId())) {
                    $query2 = " SELECT pl.locationId "; // reworked 2020-02-28 JM, Was " select * " but we are really just getting locationId
                    $query2 .= "FROM " . DB__NEW_DATABASE . ".personLocation pl ";
                    //$query2 .= " JOIN " . DB__NEW_DATABASE . ".location l ON pl.locationId = l.locationId "; CP - 2020-11-27
                    $query2 .= " WHERE pl.personLocationId = " . $bp->getPersonLocationId();

                    $result2 = $this->db->query($query2);
                    if ($result2) {
                        if ($result2->num_rows > 0) {
                            $row2 = $result2->fetch_assoc();
                            if (!Location::validate($row2['locationId'])) {
                                $this->logger->error2('637420718602368083', "getBillingProfiles => invalid locationId : " .$row2["locationId"]);
                                $errCode = true; // show error message to user
                            } else {
                                $ll = new Location($row2['locationId']);
                                $record['loc'] = $ll->getFormattedAddress();
                            }
                        }
                    } else {
                        $this->logger->errorDb('1569441688', 'getBillingProfiles', $this->db);
                        $errCode = true;
                    }

                }
                if (intval($bp->getCompanyEmailId())) {
                    $query2 = " SELECT emailAddress " . // reworked 2020-02-28 JM, Was " select * " but we are really just getting emailAddress
                                "FROM " . DB__NEW_DATABASE . ".companyEmail WHERE companyEmailId = " . $bp->getCompanyEmailId();
                    
                    $result2 = $this->db->query($query2);
                    if ($result2) { 
                        if ($result2->num_rows > 0) {
                            $row2 = $result2->fetch_assoc();
                            $record['loc'] = $row2['emailAddress'];
                        }
                    } else {
                        $this->logger->errorDb('1569441718', 'getBillingProfiles', $this->db);
                        $errCode = true;
                    } 
                }
                if (intval($bp->getCompanyLocationId())) {
                    $query2 = " SELECT cl.locationId "; // reworked 2020-02-28 JM, Was " select * " but we are really just getting locationId
                    $query2 .= " FROM " . DB__NEW_DATABASE . ".companyLocation cl ";
                    $query2 .= " WHERE cl.companyLocationId = " . $bp->getCompanyLocationId();
                    
                    $result2 = $this->db->query($query2);
                    if ($result2) { 
                        if ($result2->num_rows > 0) {
                            $row2 = $result2->fetch_assoc();
                            
                            if (!Location::validate($row2['locationId'])) {
                                $this->logger->error2('637420720712558592', "getBillingProfiles => invalid locationId : " .$row2["locationId"]);
                                $errCode = true; // show error message to user
                            } else {
                                $ll = new Location($row2['locationId']);
                                $record['loc'] = $ll->getFormattedAddress();
                            }
                        }
                            
                    } else {
                        $this->logger->errorDb('1569441739', 'getBillingProfiles', $this->db);
                        $errCode = true;
                    } 
                }
                    
                $record['billingProfile'] = $bp;
                // BEGIN REPLACED JM 2020-04-10 fixing http://bt.dev2.ssseng.com/view.php?id=118
                // $record['isPrimary'] = $bp->getBillingProfileId() == $this->primaryBillingProfileId;
                // END REPLACED JM 2020-04-10
                // BEGIN REPLACEMENT JM 2020-04-10
                $record['isPrimary'] = $bp->getBillingProfileId() && ($bp->getBillingProfileId() == $this->primaryBillingProfileId);
                // END REPLACEMENTD JM 2020-04-10
                
                $ret[] = $record;
            }
        }
        return $ret;	
    } // END public function getBillingProfiles	
    

    /**
    * Selects all entries from table companyPerson based on companyId.
    *
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array $ret. An array of CompanyPerson objects on success. 
    **/

    public function getCompanyPersons(&$errCode=false) {
        $errCode = false;		
        $ret = array();
        
        $query = "SELECT cp.companyPersonId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".companyPerson cp ";
        $query .= "WHERE cp.companyId = " . intval($this->getCompanyId()) . " ";
        $query .= "AND cp.companyId != 0 ";
        
        $result = $this->db->query($query);
 
        if (!$result) {
            $this->logger->errorDb('637261743834431397', 'getCompanyPersons: Hard DB error', $this->db); 
            $errCode = true;
        } else  {
            while ($row = $result->fetch_assoc()) {
                $cp = new CompanyPerson($row['companyPersonId']);
                $ret[] = $cp;
            }
        }
        return $ret;
    } // END public function getCompanyPersons
        
   
      

    /* getJobs RETURNs an array -- in descending order by (jobId, deliveryDate) -- of associative arrays; 
        these may be explicitly related to the companyPerson at the job level, or at one remove via the workOrder. 
        Each associative array has the following members:
        * 'inTable': from Team table, 1=>workOrder (INTABLE_WORKORDER), 2=>job (INTABLE_JOB) 
        * 'companyPersonId'
        * 'position": Team.role
        * 'description' (from Team)
        * 'active' (from Team)
        * 'teamId'
        * 'jobId'
        * 'jobStatusId' (from Job)
        * 'personId'
        * 'firstName'
        * 'lastName'
        * 'companyName'
        * 'deliveryDate'
        * 'workOrderId'
    */
    public function getJobs(&$errCode=false) {
        $errCode=false;
        $ret = array();
    
        $query = " SELECT t.inTable as inTable, t.companyPersonId, t.role as position, t.description, t.active, t.teamId ";
        $query .= " ,j.jobId as jobId, j.jobStatusId  ";
        // $query .= " , j.realStatus  "; // DROPPED 2020-11-18 JM, getting rid of this
        $query .= " ,p.personId  ";
        $query .= " ,p.firstName  ";
        $query .= " ,p.lastName ";
        $query .= " ,c.companyName  ";
        $query .= " ,wo.deliveryDate as deliveryDate ";
        $query .= " ,wo.workOrderId ";		
        
        //$query .= " ,tp.name  "; // Commented out by martin before 2019
        //$query .= " ,tp.description as tpdescription  "; // Commented out by martin before 2019
        $query .= " FROM " . DB__NEW_DATABASE . ".team t ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".companyPerson cp ON t.companyPersonId = cp.companyPersonId ";
        //$query .= " left join " . DB__NEW_DATABASE . ".teamPosition tp on t.teamPositionId = tp.teamPositionId "; // Commented out by martin before 2019
        $query .= " JOIN " . DB__NEW_DATABASE . ".person p ON cp.personId = p.personId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".company c ON cp.companyId = c.companyId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".workOrder wo ON t.id = wo.workOrderId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".job j ON wo.jobId = j.jobId ";
        
        $query .= " WHERE c.companyId = " . intval($this->getCompanyId()) . " ";
        $query .= " AND t.inTable = " . INTABLE_WORKORDER;
        //$query .= " group by  j.jobId "; // Commented out by martin before 2019
        
        $query .= " UNION  ";
        
        $query .= " SELECT t.inTable as inTable, t.companyPersonId, t.role as position, t.description, t.active, t.teamId ";
        $query .= " ,j.jobId as jobId, j.jobStatusId  ";
        // $query .= " , j.realStatus  "; // DROPPED 2020-11-18 JM, getting rid of this
        $query .= " ,p.personId  ";
        $query .= " ,p.firstName  ";
        $query .= " ,p.lastName ";
        $query .= " ,c.companyName  ";
        $query .= " ,wo.deliveryDate as deliveryDate ";
        $query .= " ,wo.workOrderId ";		
        
        //$query .= " ,tp.name  "; // Commented out by martin before 2019
        //$query .= " ,tp.description as tpdescription  "; // Commented out by martin before 2019
        $query .= " FROM " . DB__NEW_DATABASE . ".team t ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".companyPerson cp ON t.companyPersonId = cp.companyPersonId ";
        //$query .= " left join " . DB__NEW_DATABASE . ".teamPosition tp on t.teamPositionId = tp.teamPositionId "; // Commented out by martin before 2019
        $query .= " JOIN " . DB__NEW_DATABASE . ".person p ON cp.personId = p.personId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".company c ON cp.companyId = c.companyId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".job j ON t.id = j.jobId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".workOrder wo ON j.jobId = wo.jobId ";
        
        $query .= " WHERE c.companyId = " . intval($this->getCompanyId()) . " ";
        $query .= " AND t.inTable = " . INTABLE_JOB;
        //$query .= " group by  j.jobId "; // Commented out by martin before 2019		
        
        $query .= " ORDER BY  jobId DESC, deliveryDate DESC ";
        
        //inTable asc  // Commented out by martin before 2019
        
        $result = $this->db->query($query);

        if (!$result) { 
            $this->logger->errorDb('1571775045', 'getJobs', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $ret[] = $row;
            }
        }
        
        return $ret;
    } // END public function getJobs
    
    

    /**
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array $ret, on success, an array of associative arrays,
    * Each associative array has the following members:
       * 'companyLocationId'
       * 'companyId'
       * 'locationId'
       * 'name' (from __companyLocation__)
       * 'isPrimary' (from __companyLocation__)
       * 'addressType' (from __companyLocation__)
       * 'status' (from __companyLocation__)
       * 'created' (from __companyLocation__)
       * 'customerId'
       * 'address1'
       * 'address2'
       * 'suite'
       * 'city'
       * 'state'
       * 'country'
       * 'postalCode'
       * 'latitude'
       * 'longitude'
       * 'county' // but that appears not to be used 2019-12
       * 'googleGeo'
       * 'created'
       * 'locationTypeName'
       * 'locationTypeDisplayName'
       *NOTE THAT location.name is inaccessible, because 'name' is used for companyLocation.name.
    **/
    public function getLocations(&$errCode=false) {
        $errCode = false;
        $ret = array();
    
        $query = "SELECT cl.companyLocationId, cl.locationTypeId, l.locationId  ";
        $query .= "FROM " . DB__NEW_DATABASE . ".companyLocation cl ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".location l ON cl.locationId = l.locationId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".locationType lt ON cl.locationTypeId = lt.locationTypeId ";
        $query .= "WHERE cl.companyId = " . intval($this->getCompanyId()). " ";
        $query .= "ORDER BY cl.companyLocationId ";
    
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('1571775085', 'getLocations', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $ret[] = $row;
            }
        }
        
        return $ret;
    } // END public function getLocations
    

    /**
    * @param  bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array $emails, on success, an array of associative arrays,
    * Each associative array has the following members:
       * 'companyEmailId'
       * 'companyId'
       * 'emailAddress'
       * 'confirmed'
       * 'displayOrder'
       * 'emailTypeName'
       * 'emailTypeDisplayName'
    */
    public function getEmails(&$errCode=false) {
        $errCode = false;
        $emails = array();
    
        $query  = "SELECT ce.* ,et.emailTypeName,et.emailTypeDisplayName ";
        $query .= "FROM " . DB__NEW_DATABASE . ".companyEmail ce ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".emailType et ON ce.emailTypeId = et.emailTypeId ";
        $query .= "WHERE ce.companyId = " . intval($this->getCompanyId()). " ";
        $query .= "ORDER BY ce.companyEmailId ";

        $result = $this->db->query($query);

        if(!$result) {          
            $this->logger->errorDb('1571775131', 'getEmails', $this->db);
            $errCode = true;
        } else  {
            while ($row = $result->fetch_assoc()) {
                $row['emailTypeDisplayName'] = trim($row['emailTypeDisplayName']);
                $row['emailAddress'] = trim($row['emailAddress']);
                $emails[] = $row;
            }
        }  
    
        return $emails;
    } // END public function getEmails
    
    // Add an email address for this company
    // INPUT $emailAddress typically comes from $_REQUEST.
    // A string with an email address.
    // Code checks for whether this email address is already there for this customer, 
    //  avoids redundant INSERT.
    // George IMPROVED 2020-04-28. Method returns a boolean true on success, false on failure.
    // Log messages on failure.
    public function addEmail($emailAddress) {
        // George 2020-11-23. REMOVED
        /*if (!is_array($val)) {
            $this->logger->warn2('1569441860', 'addEmail => input value is not an array ');
            return false;
        }

        if (!isset($val['emailAddress'])) {
            $this->logger->error2('637257620571974309', 'array passed to company::addEmail had no index \'emailAddress\'');
            return false;
        }

        $emailAddress = $val['emailAddress']; */
        //End

        // Validate Email.
        if ( !filter_var($emailAddress, FILTER_VALIDATE_EMAIL) ) {
            $this->logger->error2('637257621753938039', " $emailAddress is not a valid email address.");
            return false;
        }
        // END Add.

        // George 2020-05-22. IMPROVEMENT.
        $query = "SELECT companyId "; // reworked 2020-02-28 JM. Checking for existence.
        $query .= "FROM " . DB__NEW_DATABASE . ".companyEmail  ";
        $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
        $query .= "AND emailAddress = '" . $this->db->real_escape_string($emailAddress) . "' ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('1571775207', 'addEmail', $this->db);
            return false;
        }

        if ($result->num_rows > 0) {
            // Already exists, consider that success.
            $this->logger->warn2('637254940848578924', 'addEmail => email :' . $emailAddress ." already exists for companyId ". $this->getCompanyId());

        } else {
            $query = "INSERT INTO  " . DB__NEW_DATABASE . ".companyEmail (companyId, emailAddress) VALUES (";
            $query .= " " . intval($this->getCompanyId()) . " ";
            $query .= " ,'" . $this->db->real_escape_string($emailAddress) . "') ";

            $result = $this->db->query($query); 

            if (!$result) {                    
                $this->logger->errorDb('1569442022', 'addEmail', $this->db);
                return false;
            }
        } 

        return true;
        // End IMPROVEMENT.
    } // END public function addEmail


    /**
    * Update a given email address for this company.
    * @param array $val, this input typically comes from $_REQUEST. An associative array containing 
    * the following elements:
    *   'emailAddress' - can be blank to delete email address.
    *   'emailTypeId' - foreign key into emailType table. 
    *   'companyEmailId' - identifies email address to replace.
    * @param bool $integrity, variable pass by reference. Default value is false.
    * $integrity is True if no reference to the primary key of this row is found in the database.
    * @return bool true on success, false on failure.
    **/

    public function updateEmail($val, &$integrityIssues=false) {
        if (!is_array($val)) {
            $this->logger->error2('637236707861549853', 'updateEmail => expected array as input, got something not an array');
            return false;
        }
        if (!isset($val['emailAddress'])) {
            $this->logger->error2('637260003612082854', 'array passed to company::updateEmail had no index \'emailAddress\'');
            return false;
        }

        if (!isset($val['companyEmailId'])) {
            $this->logger->error2('637260003922478669', 'array passed to company::updateEmail had no index \'companyEmailId\'');
            return false;
        }

        if (!isset($val['emailTypeId'])) {
            $this->logger->error2('637260005446315427', 'array passed to company::updateEmail had no index \'emailTypeId\'');
            return false;
        }

        // BEGIN REPLACEMENT CODE 2019-12-02 JM // George UPDATE 2020-05-25.
        $emailAddress =  $val['emailAddress'];
        $companyEmailId = $val['companyEmailId'];
        $emailTypeId = $val['emailTypeId'];
        // END REPLACEMENT CODE 2019-12-02 JM //End UPDATE.

        // George. ADDED 2020-05-20.
        if ( !filter_var($emailAddress, FILTER_VALIDATE_EMAIL) ) {
            $this->logger->error2('637260004694350578', "$emailAddress is not a valid email address.");
        }
        // END ADDED.

        $query = "SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
        $query .= "FROM " . DB__NEW_DATABASE . ".companyEmail  ";
        $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
        $query .= "AND companyEmailId = " . intval($companyEmailId);
        
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637260025304386832', 'updateEmail: Hard DB error', $this->db);
            return false;
        } 

        if ($result->num_rows > 0) { //Row found for companyEmailId
            // Validate emailTypeId 
            $emailTypes = $this->getEmailTypes();				
            $ok = false;
            
            foreach ($emailTypes as $emailType) {
                if ($emailType['emailTypeId'] == $emailTypeId) {
                    $ok = true;
                }
            }
            
            if (!$ok) {
                /* OLD CODE REMOVED 2019-12-02 JM
                $phoneTypeId = 0; // JM 2019-02-19: Surely this should be $emailTypeId. As it is, it does nothing!
                */
                // BEGIN REPLACEMENT CODE 2019-12-02 JM
                $emailTypeId = 0;
                // END REPLACEMENT CODE 2019-12-02 JM
            }
            
            $emailAddress = trim($emailAddress);

            if (strlen($emailAddress)) {
                // make sure we have only one emailAddress for this company.
                $query  = "SELECT emailAddress "; // Checking for existence.
                $query .= "FROM " . DB__NEW_DATABASE . ".companyEmail  ";
                $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
                $query .= "AND emailAddress = '" . $this->db->real_escape_string($emailAddress). "' ";
                $query .= "AND companyEmailId  <> " . intval($companyEmailId) . " ;";
                
                $result = $this->db->query($query);

                if (!$result) {
                    $this->logger->errorDb('637417383004949373', 'Hard DB error ', $this->db);
                    return false;
                }
                // This emailAddress is already associated with this company.
                if ($result->num_rows > 0) {
                    $this->logger->warn2('637417383368849053', "This emailAddress $emailAddress is already associated with this cpmpany: ". $this->getCompanyId());
                    return true; // only Log an warn message. No message for User.
                } else {

                    $query = "UPDATE " . DB__NEW_DATABASE . ".companyEmail SET ";
                    $query .= " emailAddress = '" . $this->db->real_escape_string($emailAddress) . "' ";
                    $query .= " ,emailTypeId = " . intval($emailTypeId) . " ";
                    $query .= "WHERE companyEmailId = " . intval($companyEmailId) . " ;";
                        
                    $result = $this->db->query($query);
                    if (!$result) {
                        $this->logger->errorDb('1569442079', 'updateEmail', $this->db);
                        return false;
                    }
                    return true;
                }

            } else {
                // check for database Integrity issues.
                $query = "SELECT companyEmailId FROM " . DB__NEW_DATABASE . ".companyEmail WHERE companyEmailId = $companyEmailId; ";

                $result = $this->db->query($query);

                if(!$result) {                        
                    $this->logger->errorDb('637334421294385127', 'Select companyEmailId: Hard DB error', $this->db);
                    return false;
                }

                $row = $result->fetch_assoc();

                // Issues with third argument, are Logged in the function: not a number, zero or is negative.
                $integrityTest = canDelete('companyEmail', 'companyEmailId', $row['companyEmailId']);

                // if True, No reference to the primary key of this row is found in the database.
                if ($integrityTest == true) {
                    $query = "DELETE from " . DB__NEW_DATABASE . ".companyEmail  ";
                    $query .= "WHERE companyEmailId = " . intval($companyEmailId) . ";";
                        
                    $result = $this->db->query($query);
                    if(!$result) {                        
                        $this->logger->errorDb('1569442088', 'updateEmail', $this->db);
                        return false;
                    }
                    return true; // Delete success.
                } else {
                    $integrityIssues = true; // At least one reference to this row exists in the database, violation of database integrity.
                    $this->logger->warn2('637334420707147664', 'update companyEmail: Delete Email not possible! At least one reference to this row exists in the database, violation of database integrity.');
                }
            }	
        } else {
            $this->logger->warn2('637254943550807437', 'updateEmail => No row found for companyEmailId ' . $companyEmailId);
            return false;
        }

    } // END public function updateEmail	
    
    // Update location type for a particular location for this company
    // INPUT $val typically comes from $_REQUEST. An associative array containing the following elements:
    //  * 'companyLocationId' - primary key in companyLocation table
    //  * 'locationTypeId'
    // Method returns a boolean true on success, false on failure.
    // Log messages on failure.	
    public function updateLocationType($val) {
        // George 2020-05-20. ADDED.
        if (!is_array($val)) {
            $this->logger->error2('637260076756864630', 'updateLocationType => input value is not an array ');
            return false;
        }
        
        if (!isset($val['companyLocationId'])) {
            $this->logger->error2('637260077866493641', 'array passed to company::updateLocationType had no index \'companyLocationId\'');
            return false;
        }

        if (!isset($val['locationTypeId'])) {
            $this->logger->error2('637260079115648604', 'array passed to company::updateLocationType had no index \'locationTypeId\'');
            return false;
        }
        // End ADD.

        // BEGIN REPLACEMENT CODE 2019-12-02 JM. // George UPDATE 2020-05-25
        $companyLocationId =  $val['companyLocationId'];
        $locationTypeId = $val['locationTypeId'];
        // END REPLACEMENT CODE 2019-12-02 JM. // End Update

        $query = "SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
        $query .= "FROM " . DB__NEW_DATABASE . ".companyLocation  ";
        $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
        $query .= "AND companyLocationId = " . intval($companyLocationId);
        
        $result = $this->db->query($query);

        // George UPDATE 2020-05-25
        if (!$result) {
            $this->logger->errorDb('637260079921621039', 'updateLocationType: Hard DB error', $this->db);
            return false;
        }

        if ($result->num_rows > 0) { //Owns Location true
            // validate locationType
            $locationTypes = $this->getLocationTypes();
            $ok = false;
            // End Update.
            foreach ($locationTypes as $locationType) {
                if ($locationType['locationTypeId'] == $locationTypeId) {
                    $ok = true;
                }
            }
            
            if (!$ok) {
                $locationTypeId = 0;
            }			
            
            $query = "UPDATE " . DB__NEW_DATABASE . ".companyLocation  SET ";
            $query .= "locationTypeId = " . intval($locationTypeId) . " ";
            $query .= "WHERE companyLocationId = " . intval($companyLocationId) . " ";
            
            $result = $this->db->query($query);

            if (!$result) {
                $this->logger->errorDb('1569442901', 'updateLocationType', $this->db);
                return false;
            }
            return true;
        } else {
            $this->logger->warn2('1569442228', 'updateLocationType => Location not found');
            return false;
        }
    return true;
    } // END public function updateLocationType
    
    /* getPhones RETURNs an array of associative arrays. Each associative array has the following members:
       * 'companyPhoneId'
       * 'phoneTypeId'
       * 'companyId'
       * 'phoneNumber'
       * 'ext1'
       * 'ext2'
       * 'isPrimary'
       * 'displayOrder'
       * 'typeName' (from table PhoneType)
    */   
    public function getPhones(&$errCode=false) {
        $errCode=false;	
        $phones = array();
    
        $query  = "SELECT cp.*,pt.typeName ";
        $query .= "FROM " . DB__NEW_DATABASE . ".companyPhone cp ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".phoneType pt ON cp.phoneTypeId = pt.phoneTypeId ";
        $query .= "WHERE cp.companyId = " . intval($this->getCompanyId()). " ";
        $query .= "ORDER BY cp.companyPhoneId ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('1571775542', 'getPhones : Hard DB error', $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['typeName'] = trim($row['typeName']);
                $row['phoneNumber'] = trim($row['phoneNumber']);
                $phones[] = $row;
            }
        }
        
        return $phones;	
    } // END public function getPhones

    /* REMOVED 2020-05-04 JM, no longer used.
    // getAccountsPayableTypes does a pair of DB queries, finding an email address 
    //  (or, failing that, location) for the company where the emailAddressTypeName 
    //  (or locationTypeName) = 'accountspayable'. 
    // NOTE that in both cases 'desc limit 1' means we will find only the most recent
    //  (since primary-key IDs are assigned in increasing order). 
    // RETURN is an array containing a single associative array; the content of the 
    //  associative array is the canonical representation of a row
    //  either from DB table companyEmail or DB table companyLocation.
    // If no such thing is found, RETURNs false.
    public function getAccountsPayableTypes() {		
        $emails = array();
        
        $query = "SELECT * ";
        $query .= " from " . DB__NEW_DATABASE . ".companyEmail ce ";
        $query .= " where companyId = " . intval($this->getCompanyId()) . " ";
        $query .= " and ce.emailTypeId = (select emailTypeId from " . DB__NEW_DATABASE . ".emailType where emailTypeName = 'accountsPayable') ";
        $query .= " order by companyEmailId desc limit 1 ";	
    
        $result = $this->db->query($query);

        if(!$result) { // George 2020-06-25. Rewrite if statement. 
            $this->logger->errorDb('1571775578', 'getAccountsPayableTypes => emailType', $this->db); 
            return false;    
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $emails[] = $row;
        }
        
        
        $locations = array();
        
        $query = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".companyLocation cl ";
        $query .= " where companyId = " . intval($this->getCompanyId()) . " ";
        $query .= " and cl.locationTypeId = (select locationTypeId from " . DB__NEW_DATABASE . ".locationType where locationTypeName = 'accountsPayable') ";
        $query .= " order by companyLocationId desc limit 1 ";		
        
        $result = $this->db->query($query);

        if(!$result) {  
            $this->logger->errorDb('1571775609', 'getAccountsPayableTypes => locationType', $this->db);     
            return false;    
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $locations[] = $row;
        }
        
        
        if (count($emails)) {
            return $emails;
        }		
        if (count($locations)) {
            return $locations;
        }
        
        return false;
    } // END public function getAccountsPayableTypes()
    */
    
    // Add a phone number for this company
    // INPUT $val typically comes from $_REQUEST. An associative array containing the following elements:
    //  * 'phoneNumber' - should be 10-digit string, North American dialing with no initial '1'.
    //    OK if some other characters are there, they will be stripped, so for example '(206)555-1212'
    //    as input means '2065551212'
    //  * 'phoneTypeId' - foreign key into DB table PhoneType
    // Code checks for whether this phone number is already there for this company, avoids redundant INSERT.
    // George IMPROVED 2020-04-28. Method returns a boolean true on success, false on failure.
    // Log messages on failure.
    public function addPhone($val) {
        // George ADDED 2020-05-22	
        if (!is_array($val)) {
            $this->logger->warn2('1569443187', 'addPhone => expected array as input, got something not an array');
            return false;
        }

        if (!isset($val['phoneNumber'])) {
            $this->logger->error2('637257557844426622', 'array passed to company::addPhone had no index \'phoneNumber\'');
            return false;
        }

        if (!isset($val['phoneTypeId'])) {
            $this->logger->error2('637257557950296410', 'array passed to company::addPhone had no index \'phoneTypeId\'');
            return false;
        }
        // END ADDED

        // George 2020-11-23. Phone number can contain only: digits, parentheses, dashes, spaces!
        if (!preg_match("/^[- ()0-9]*$/", $val['phoneNumber'])) {
            $this->logger->error2("637417395279449089", "Invalid characters in phoneNumber, input given: " . $val['phoneNumber']);
            return false;
        } else {
            $phoneNumber = $val['phoneNumber'];
        }

        $phoneTypeId = $val['phoneTypeId'];

        // Get all phoneTypes, and single out the phoneType with typeName "Other"
        $other = false;
        $phoneTypes = Company::getPhoneTypes();

        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['typeName'] == 'Other') {
                $other = $phoneType['phoneTypeId'];
            }
        }
        if (!$other) {
            $this->logger->warn2('1587581412', "There is no phone type 'Other' in DB table phoneTypes"); 
        }
        


        // Validate input phone type
        $ok = false;	
        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['phoneTypeId'] == $phoneTypeId) {
                $ok = true;
            }
        }

        // If no valid phone type in input, use 'Other' 
        if (!$ok) {
            if ($other) {
                $phoneTypeId = $other;
                $ok = true;
            }
        }

        // [Begin Martin comment]
        // only proceed if found a valid phone type id.
        // note this can fail if can't at least find an "Other" type in the phone type table
        // so check spelling of typeNames
        // [End Martin comment]	

        // If it's not there, then insert it.
        if ($ok) {
            // only digits
            $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
            // George 2020-11-17. Check if we have exactly 10 digits! Log if not.
            if (strlen($phoneNumber) != PHONE_NADS_LENGTH) {
                $this->logger->warn2('637235926259133340', 'addPhone => Input phone number not 10 digits!');   
            }

            // NOTE that the select here ignores phone type
            $query = "SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
            $query .= "FROM " . DB__NEW_DATABASE . ".companyPhone  ";
            $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
            $query .= "AND phoneNumber = '" . $this->db->real_escape_string($phoneNumber) . "' ";
            
            $result = $this->db->query($query);

            if ($result) {            
                if ($result->num_rows > 0) {
                    $this->logger->warn2('637236009317040204', 'addPhone => Phone Number already exists in DB!');
                    return true; // only Log an warn message. No message for User.
                }
            } else {
                $this->logger->errorDb('1571775689', 'addPhone', $this->db);
                return false;
            }

            $query = "INSERT INTO  " . DB__NEW_DATABASE . ".companyPhone (phoneTypeId, companyId, phoneNumber) VALUES (";
            $query .= " " . intval($phoneTypeId) . " ";
            $query .= " ," . intval($this->getCompanyId()) . " ";
            $query .= " ,'" . $this->db->real_escape_string($phoneNumber) . "') ";

            $result = $this->db->query($query);
            if(!$result) {                        
                $this->logger->errorDb('1569443167', 'addPhone', $this->db);
                return false;
            }    
            return true;                                
            	
        } else {
            $this->logger->warn2('1569443178', $phoneType['phoneTypeId'] .
                    " is not an identifiable phone type, and there is no phone type 'Other' in DB table phoneTypes");
            return false;
        }
    } // END public function addPhone
    
    
    // Update a phone number for this company
    // INPUT $val typically comes from $_REQUEST. An associative array containing the following elements:
    //  * 'phoneNumber' - new phone number; should be 10-digit string, North American dialing with no initial '1'.
    //    Can be blank to delete.
    //  * 'ext1' - extension for this phone.
    //  * 'phoneTypeId' - foreign key into DB table PhoneType, desired phone type
    //  * 'companyPhoneId' - foreign key into DB table CompanyPhone, row to update. 
    // George IMPROVED 2020-04-28. Method returns a boolean true on success, false on failure.
    // Log messages on failure.
    public function updatePhone($val) {
        // George ADDED 2020-05-22
        if (!is_array($val)) {
            $this->logger->warn2('637223922593419759', 'updatePhone => expected array as input, got something not an array');
            return false;
        }

        if (!isset($val['phoneNumber'])) {
            $this->logger->error2('637257606151803046', 'array passed to company::updatePhone had no index \'phoneNumber\'');
            return false;
        }

        if (!isset($val['phoneTypeId'])) {
            $this->logger->error2('637257606283793247', 'array passed to company::updatePhone had no index \'phoneTypeId\'');
            return false;
        }

        if (!isset($val['companyPhoneId'])) {
            $this->logger->error2('637257606521850922', 'array passed to company::updatePhone had no index \'companyPhoneId\'');
            return false;
        }
        
        if (!isset($val['ext1'])) {
            $this->logger->error2('637257606604098289', 'array passed to company::updatePhone had no index \'ext1\'');
            return false;
        }
        // END ADDED
        // George 2020-11-17. Phone number can contain only: digits, parentheses, dashes, spaces!
        if(!preg_match("/^[- ()0-9]*$/", $val['phoneNumber'])) {
            $this->logger->error2("637417436222738696", "Invalid characters in phoneNumber, input given: " . $val['phoneNumber']);
            return false;
        } else {
            $phoneNumber = $val['phoneNumber'];
        }

        if( strlen($val['ext1']) <= 5 ) {
            $ext1 = $val['ext1'];
        } else {
            $this->logger->error2("637417436558545380", "Invalid extension for this phoneNumber, input given: " . $val['ext1']);
            return false;
        }

        // BEGIN REPLACEMENT CODE 2019-12-02 JM
        $phoneTypeId = $val['phoneTypeId'];
        $companyPhoneId = $val['companyPhoneId'];
        // END REPLACEMENT CODE 2019-12-02 JM
            
        // Get all phoneTypes, and single out the phoneType with typeName "Other"
        $other = false;	
        $phoneTypes = Company::getPhoneTypes();
        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['typeName'] == 'Other') {
                $other = $phoneType['phoneTypeId']; 
            }
        }
        if (!$other) {
            $this->logger->warn2('1587581432', "There is no phone type 'Other' in DB table phoneTypes"); 
        }
            
        // Validate input phone type
        $ok = false;	
        foreach ($phoneTypes as $phoneType) {
            if ($phoneType['phoneTypeId'] == $phoneTypeId) {
                $ok = true;
            }
        }
            
        // If no valid phone type in input, use 'Other'
        if (!$ok) {
            if ($other) {
                $phoneTypeId = $other;
                $ok = true;
            }
        }
            
        if ($ok) {
            // make sure $companyPhoneId matches this company
            $query = "SELECT companyId "; // reworked 2020-02-28 JM, Was " select * " but we are really just checking for existence.
            $query .= "FROM " . DB__NEW_DATABASE . ".companyPhone  ";
            $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
            $query .= "AND companyPhoneId = " . intval($companyPhoneId);
                
            $result = $this->db->query($query);
                
            if (!$result) {
                 $this->logger->errorDb('1569444557', 'updatePhone: Hard DB error ', $this->db);
                 return false;
            }

            if ($result->num_rows > 0) { //Entry exists.
                $phoneNumber = trim($phoneNumber);
                    
                if (strlen($phoneNumber)) {
                    // only digits
                    $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
                     // George 2020-11-23. Check if we have 10 digits! Log warning if not. 
                    if (strlen($phoneNumber) != PHONE_NADS_LENGTH) {
                        $this->logger->warn2("637235983276333747", "updatePhone => Input phone number not 10 digits!" . $phoneNumber);   
                    }
                    // make sure we have only one phoneNumber for this company.
                    $query  = "SELECT phoneNumber "; // Checking for existence.
                    $query .= "FROM " . DB__NEW_DATABASE . ".companyPhone  ";
                    $query .= "WHERE companyId = " . intval($this->getCompanyId()). " ";
                    $query .= "AND phoneNumber = '" . $this->db->real_escape_string($phoneNumber). "' ";
                    $query .= "AND companyPhoneId <> " . intval($companyPhoneId). " ;";

                    $result = $this->db->query($query);
                        
                    if (!$result) { 
                        $this->logger->errorDb('637417439882564281', 'select phoneNumber: Hard DB error ', $this->db);
                        return false;
                    } 
                    // This phoneNumber is already associated with this company.
                    if ($result->num_rows > 0) {
                    $this->logger->warn2('637417440269951571', "This phoneNumber $phoneNumber is already associated with this company: ". $this->getCompanyId());
                    return true; // only Log an warn message. No message for User.
                    } else {

                        $query = "UPDATE " . DB__NEW_DATABASE . ".companyPhone SET ";
                        $query .= " phoneNumber = '" . $this->db->real_escape_string($phoneNumber) . "' ";
                        $query .= " ,ext1 = '" . $this->db->real_escape_string($ext1) . "' ";

                        $query .= " ,phoneTypeId = " . intval($phoneTypeId) . " ";
                        $query .= "WHERE companyPhoneId = " . intval($companyPhoneId) . " ";
                        
                        $result = $this->db->query($query);
                        if (!$result) {                            
                            $this->logger->errorDb('1569444557', 'updatePhone', $this->db);
                            return false;
                        }  
                        return true;
                    }
                } else {
                    $query = "DELETE from " . DB__NEW_DATABASE . ".companyPhone  ";
                    $query .= "WHERE companyPhoneId = " . intval($companyPhoneId) . " ";
                        
                    $result = $this->db->query($query);
                    if (!$result) {                            
                        $this->logger->errorDb('1569444481', 'updatePhone', $this->db);
                        return false;
                    }
                    return true;               
                }
            } else { // George Added 2020-05-28.
                $this->logger->warn2('637262721862499811', "Didn't find a phone to match this company (companyId = {$this->getCompanyId()})");
                return false;
            } // End Add 
        } else {               
            $this->logger->warn2('637223922236134442', $phoneType['phoneTypeId'] .
                    "is not an identifiable phone type, and there is no phone type 'Other' in DB table phoneTypes");
            return false;
        }   
        
    } // END public function updatePhone	
    
    // Update several values for this company
    // INPUT $val typically comes from $_REQUEST.
    //  An associative array containing the following elements (unlike addresses, emails, etc,  
    //   these are all directly in DB table company, and a company has only one of each):
    //   * 'companyName'
    //   * 'companyNickname'
    //   * 'companyURL'
    //   * 'companyLicense'
    //   * 'companyTaxId'
    //   Any or all of these may be present. >>>00016: Maybe more validation?
    public function update($val) {
        if (is_array($val)) {
            if (isset($val['companyName'])) {
                $this->setCompanyName($val['companyName']);	
            }    
           
            if (isset($val['companyNickname'])) {
                $this->setCompanyNickname($val['companyNickname']);	
            }
            
            if (isset($val['companyURL'])) {	
                $this->setCompanyURL($val['companyURL']);				
            }
                
            if (isset($val['companyLicense'])) {	
                $this->setCompanyLicense($val['companyLicense']);				
            }
            
            if (isset($val['companyTaxId'])) {
                $this->setCompanyTaxId($val['companyTaxId']);			
            }				
            
            if (isset($val['primaryBillingProfileId'])) {	
                $this->setPrimaryBillingProfileId($val['primaryBillingProfileId']);
            }

            // ADDED George 2020-04-24, save() returns a boolean 
            return $this->save();
        }	else {
            $this->logger->warn2('637233247105298191', 'update Company => expected array as input, got something not an array', $this->db);
            return false;
        }
    } // END public function update
    
    // >>>00017 Not sure there is good reason for this method to be public.
    // UPDATEs same fields handled by public function update. Since there are
    //  no other public "set" methods, what's the use of this?
    public function save() {	
        $query = "UPDATE " . DB__NEW_DATABASE . ".company SET ";
        $query .= "companyName = '" . $this->db->real_escape_string($this->getCompanyName()) . "' ";
        $query .= ", companyNickname = '" . $this->db->real_escape_string($this->getCompanyNickname()) . "' ";
        $query .= ", companyURL = '" . $this->db->real_escape_string($this->getCompanyURL()) . "' ";
        $query .= ", companyLicense = '" . $this->db->real_escape_string($this->getCompanyLicense()) . "' ";
        $query .= ", companyTaxId = '" . $this->db->real_escape_string($this->getCompanyTaxId()) . "' ";
        // BEGIN REPLACED JM 2020-04-10 fixing http://bt.dev2.ssseng.com/view.php?id=118
        //$query .= " ,primaryBillingProfileId = '" . $this->db->real_escape_string($this->getPrimaryBillingProfileId()) . "' ";
        // END REPLACED JM 2020-04-10
        // BEGIN REPLACEMENT JM 2020-04-10, rewriting with direct reference to property rather than "get", which we probably
        //  should do in general within the class, anyway. Did this here because on the more complicated expression it is a LOT clearer.
        // (got this wrong the first time, fixing it 2020-04-13 JM)
        $query .= ", primaryBillingProfileId = " . ($this->primaryBillingProfileId ? $this->primaryBillingProfileId : 'NULL');
        // END REPLACEMENT JM 2020-04-10
        $query .= " WHERE companyId = " . intval($this->getCompanyId()) . " ";

        $result = $this->db->query($query);
        if (!$result) {            
            $this->logger->errorDb('1569444519', 'save', $this->db);
            return false;
        } else {
            return true;
        }           
    }

    
    /**
    * @param integer $companyId: companyId to validate, should be an integer but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the id is a valid companyId, false if not.
    */
    public static function validate($companyId, $unique_error_id=null) {
        global $db, $logger;
        Company::loadDB($db);
        
        $ret = false;
        $query = "SELECT companyId FROM " . DB__NEW_DATABASE . ".company WHERE companyId=$companyId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578686412', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }

    // INPUT $errCode comes from inc/classes/ErrorCodes.php
    // RETURN an array with two elements:
    //     * textual version of this error, relevant to Company class
    //     * a unique code for this error specific to Company class
    public static function errorToText($errCode) {
        $error = '';
        $errorId = 0;
    
        if($errCode == 0) {
            $errorId = '1574878375';
            $error = 'addCompany method failed.';
        } else if($errCode == DB_GENERAL_ERR) {
            $errorId = '637147969629054491';
            $error = 'Error looking for prior matching bracket company name';
        } else if($errCode == DB_ROW_ALREADY_EXIST_ERR) {
            $errorId = '637147969769263436';
            $error = "Error input parameters, companyName already in use";
        } else {
            $error = "Unknown error, please fix them and try again";
            $errorId = "637172316020296367";
        }
    
        return array($error, $errorId);
    }
}

?>