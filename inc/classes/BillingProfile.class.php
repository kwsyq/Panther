<?php
/* inc/classes/BillingProfile.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the BillingProfile table.

* Extends AbstractBillingProfile, constructed for current user, or for a User object passed in. Constructed
  from DB table BillingProfile row indentified by the input to the constructor

* Inherited public functions/methods: 
** getBillingProfileId()
** getCompanyId()
** getCompanyPersonId()
** getPersonEmailId()
** getCompanyEmailId()
** getPersonLocationId()
** getCompanyLocationId()
** getMultiplier()
** getDispatch()
** getTermsId()
** getContractLanguageId()
** getGracePeriod()
** getInserted()

The function also makes use of numerous inherited protected methods.

* Other public functions: 
** __construct($id = null, User $user = null)
** getActive()
** setActive()
** update($val)
** save()
** toArray()

** public static function validate($billingProfileId, $unique_error_id=null)

NOTE that as of 2020-10-30 this class is NOT involved in the inital save of a BillingProfile to the database,

*/

class BillingProfile extends AbstractBillingProfile {
    // Class member variables for most columns of DB table are handled by AbstractBillingProfile   
    // See documentation of that class and of the table for further details
    
	private $active;            // Quasi-boolean (0 or 1). If true, we can add to new contracts/invoices. 
	                            // If not, just here to maintain referential integrity for history.
	
    // INPUT $id: a billingProfileId from the BillingProfile table
    // INPUT $user: User object; default set in ancestor class SSSEng is current user. 
	public function __construct($id = null, User $user = null) {
		parent::__construct($user);
		$this->load($id);
	}

	// INPUT $val: a billingProfileId from the BillingProfile table
	private function load($val) {
		if (is_numeric($val)) {
		    // Read row from DB table BillingProfile 
			$query = "SELECT * ";
			$query .= "FROM " . DB__NEW_DATABASE . ".billingProfile ";
			$query .= "WHERE billingProfileId = " . intval($val);

			$result = $this->db->query($query);
			if ($result) {
			    if ($result->num_rows > 0){
			        // Since query used primary key, we know there will be exactly one row.
			        
					// Set all of the private members that represent the DB content 
					$row = $result->fetch_assoc();
					
					$this->setBillingProfileId($row['billingProfileId']);
					$this->setCompanyId($row['companyId']);  
					$this->setCompanyPersonId($row['companyPersonId']);
					$this->setPersonEmailId($row['personEmailId']);
					$this->setCompanyEmailId($row['companyEmailId']);
					$this->setPersonLocationId($row['personLocationId']);
					$this->setCompanyLocationId($row['companyLocationId']);
					$this->setInserted($row['inserted']);
					$this->setMultiplier($row['multiplier']);
					$this->setDispatch($row['dispatch']);
					$this->setTermsId($row['termsId']);
					$this->setContractLanguageId($row['contractLanguageId']);
					$this->setGracePeriod($row['gracePeriod']);
					$this->setActive($row['active']);
				} // >>>00002 else ignores that we got a bad billingProfileId!
			} // >>>00002 else ignores failure on DB query!
		}
	} // END private function load
		
	// INPUT $val - quasi-Boolean
	public function setActive($val) {
	    $this->active = $val ? 1 : 0;
	}
	
	// RETURN 1 if this is an active profile, 0 if strictly historical
	public function getActive() {
	    return $this->active;
	}
	
    // Sets precisely the values used by public function save and calls that function.
    // INPUT $val: associative array, provides all the input values
	public function update($val){		
	    if (is_array($val)){
			if (isset($val['multiplier'])){
				$multiplier = $val['multiplier'];
				$this->setMultiplier($multiplier);
			}

			if (isset($val['dispatch'])){
				$dispatch = $val['dispatch'];
				$this->setDispatch($dispatch);
			}
				
			if (isset($val['termsId'])){
				$termsId = $val['termsId'];
				$this->setTermsId($termsId);
			}
				
			if (isset($val['contractLanguageId'])){
				$this->setContractLanguageId($val['contractLanguageId']);
			}
				
			if (isset($val['gracePeriod'])){
				$this->setGracePeriod($val['gracePeriod']);
			}
				
			if (isset($val['active'])){
				$this->setActive($val['active']);
			}
				
			$this->save();
		}
	}
	

	// Updates the following columns of the relevant row in DB table billingProfile:  
	//  multiplier, dispatch, termsId, contractLanguageId, gracePeriod. 
	// >>>00017 NOTE that as of 2019-02 this does not (for example) modify any of the 
	//  contact information. 	
	//  AS OF 2019-02 IT WOULD SEEM THAT THIS CLASS CANNOT SAVE CONTACT INFORMATION.
	//  Not clear whether that is intentional or something that needs followup.
	public function save() {		
		$query = "UPDATE " . DB__NEW_DATABASE . ".billingProfile SET ";
		$query .= "multiplier = " . $this->db->real_escape_string($this->getMultiplier()) . " ";
		$query .= ", dispatch = " . intval($this->getDispatch());
		$query .= ", termsId = " . intval($this->getTermsId());
		$query .= ", contractLanguageId = " . intval($this->getContractLanguageId());
		$query .= ", gracePeriod = " . intval($this->getGracePeriod());
		$query .= ", active = " . intval($this->getActive()) . " ";		
		$query .= "WHERE billingProfileId = " . intval($this->getBillingProfileId()) . ";";

		$result = $this->db->query($query); 
		// >>>00002 ignores failure on DB query!
		
		// >>>00028 Ideally, the following would be transactional/atomic with the above
		if (!intval($this->getActive())) {
		    // Make sure this is no longer considered primary billing profile for this company.
		    $company = new Company($this->getCompanyId());
		    if ($company && intval($company->getPrimaryBillingProfileId())) {
		        $company->setPrimaryBillingProfileId(null);
		        $company->save();
		    } // >>>00002 ignores failure
		}		
	}
	
	// Returns an associative array corresponding to the content of a row in DB table billingProfile, 
	// based on the content of this object.
	public function toArray() {
	    $ret = parent::toArray();
	    $ret['active'] = $this->getActive();
	    return $ret;
	}

    // Return true if the id is a valid billingProfileId, false if not
    // INPUT $billingProfileId: billingProfileId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($billingProfileId, $unique_error_id=null) {
        global $db, $logger;
        BillingProfile::loadDB($db);
        
        $ret = false;
        $query = "SELECT billingProfileId FROM " . DB__NEW_DATABASE . ".billingProfile WHERE billingProfileId=$billingProfileId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578686334', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>