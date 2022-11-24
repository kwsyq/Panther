<?php
/* inc/classes/AbstractBillingProfile.class.php

EXECUTIVE SUMMARY: 
This is an abstraction of much of the BillingProfile class, intended as a base class for both that class and a new ShadowBillingProfile class.
Up to now, we didn't handle shadow billing profiles with a class: code was ad hoc and not in the data layer.

// >>>00002, >>>00016 probably should do some validation of "set" function inputs & log if they are bad.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular billing profile.
* Public functions/methods: 
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

* Protected functions/methods 
** __construct(User $user = null)
** getId()
** setBillingProfileId($val)
** setCompanyId($val)
** setCompanyPersonId($val)
** setPersonEmailId($val)
** setCompanyEmailId($val)
** setPersonLocationId($val)
** setCompanyLocationId($val)
** setMultiplier($val)
** setDispatch($val)
** setTermsId($val)
** setContractLanguageId($val)
** function setGracePeriod($val)
** setInserted($val)
** toArray()

The following methods of BillingProfile are deliberately NOT implemented here:
** getActive()
** setActive()
** update($val)
** save()
** public static function validate($billingProfileId, $unique_error_id=null)

*/

class AbstractBillingProfile extends SSSEng {
    // The following correspond exactly to the columns of DB table BillingProfile
    // See documentation of that table for further details
    // By the nature of a billingProfile, a lot of these foreign keys for emails & locations are liable to be 0.
	private $billingProfileId;  // primary key
	private $companyId;         // foreign key to Company table
	private $companyPersonId;   // foreign key to CompanyPerson table
	private $personEmailId;     // foreign key to PersonEmail table
	private $companyEmailId;    // foreign key to CompanyEmail table
	private $personLocationId;  // foreign key to PersonLocation table
	private $companyLocationId; // foreign key to CompanyLocation table
	private $inserted;          // TIMESTAMP, when this row was inserted
	private $multiplier;        // price factor. Normally 1.0, but can use to discount good client or charge more for difficult client. 
	private $dispatch;          // day of month to send bill. Zero or null => send immediately
	private $termsId;           // foreign key to Terms table
	private $contractLanguageId; // foreign key to ContractLanguage table
	private $gracePeriod;	    // In days. Not currently used as of 2019-02. Intent is that this will eventually be used to 
	                            //   trigger automatic billingBlock if payment not received.
	
    // INPUT $user: User object; SSSEng will default this to the current logged-in user, which might be null if this is running from CLI.
    // NOTE that it is left to the concrete class to set the variables above
	protected function __construct(User $user = null) {
		parent::__construct($user);
	}

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
	protected function getId() {
		return $this->getBillingProfileId();
	}

	// NOTE that all of the following "set" functions are protectedc: we use this only as
	//  part of the inheriging classes' own load and save mechanisms, not to be used from outside.
	
	// Set primary key
	// INPUT $val: primary key (billingProfileId)
	protected function setBillingProfileId($val) {
		$this->billingProfileId = intval($val);
	}

	// Associate a company
	// INPUT $val: companyId (foreign key) 
	protected function setCompanyId($val) {
		$this->companyId = intval($val);
	}
	
	// Associate a companyPerson; this is a company + a person
	// INPUT $val: companyPersonId (foreign key) 
	protected function setCompanyPersonId($val) {
		$this->companyPersonId = intval($val);
	}

	// Associate an email associated with a person
	// INPUT $val: personEmailId (foreign key) 
	protected function setPersonEmailId($val) {
		$this->personEmailId = intval($val);
	}
	
	// Associate an email associated with a company
	// INPUT $val: companyEmailId (foreign key) 
	protected function setCompanyEmailId($val) {
		$this->companyEmailId = intval($val);
	}
	
	// Associate a location (typically mailing address) associated with a person
	// INPUT $val: personLocationId (foreign key) 
	protected function setPersonLocationId($val) {
		$this->personLocationId = intval($val);
	}
	
	// Associate a location (typically mailing address) associated with a company
	// INPUT $val: companyLocationId (foreign key) 
	protected function setCompanyLocationId($val) {
		$this->companyLocationId = intval($val);
	}
	
	// Set multiplier
	// INPUT $val: normally 1, can be increased for difficult client
	protected function setMultiplier($val) {
		if (is_numeric($val)) {
			$this->multiplier = $val;
		} else {
			$this->multiplier = 1;
		}
	}
	
	// Set day of month for billing.
	// INPUT $val: day of month for billing. 0 or null => send immediately.
	protected function setDispatch($val) {
		$this->dispatch = intval($val);
	}
	
	// Set terms.
	// INPUT $val: termsId (foreign key)
	protected function setTermsId($val) {
		$this->termsId = intval($val);		
	}
	
	// Set contract language.
	// INPUT $val: contractLanguageId (foreign key)
	protected function setContractLanguageId($val) {
		$this->contractLanguageId = intval($val);
	}

	// Set grace period, intended as when we would automatically block this 
	//  account if not paid. Apparently not used as of 2019-02.
	// INPUT $val: grace period in days.
	protected function setGracePeriod($val) {
		$this->gracePeriod = intval($val);		
	}
	
	// Set inserted TIMESTAMP, 
	// $val: in theory some sort of text form of time; in practice, this
	//  function is never called.
	protected function setInserted($val) {
		$val = trim($val);
		$this->inserted = $val;
	}
	
	// RETURN primary key
	public function getBillingProfileId() {
		return $this->billingProfileId;
	}

	// RETURN companyId (foreign key)
	public function getCompanyId() {
		return $this->companyId;
	}
	
	// RETURN companyPersonId (foreign key); this is a company + a person 
	public function getCompanyPersonId() {
		return $this->companyPersonId;
	}
	
	// RETURN a usable email address, associated with a person
	public function getPersonEmailId() {
		return $this->personEmailId;
	}
	
	// RETURN a usable email address, associated with a company
	public function getCompanyEmailId() {
		return $this->companyEmailId;
	}
	
	// RETURN a location associated with a person
	public function getPersonLocationId() {
		return $this->personLocationId;
	}
	
	// RETURN a location associated with a company
	public function getCompanyLocationId() {
		return $this->companyLocationId;
	}
	
	// RETURN multiplier; normally 1, can be increased for difficult client
	public function getMultiplier() {
		return $this->multiplier;
	}
	
	// RETURN day of month for billing. 0 or null => send immediately. 
	public function getDispatch() {
		return $this->dispatch;
	}
	
	// RETURN termsId (foreign key)
	public function getTermsId() {
		return $this->termsId;
	}
	
	// RETURN contractLanguageId (foreign key)
	public function getContractLanguageId() {
		return $this->contractLanguageId;
	}
	
	// RETURN grace period in days, intended as when we would automatically block this 
	//  account if not paid. Apparently not used as of 2019-02.
	public function getGracePeriod() {
		return $this->gracePeriod;
	}
	
	// RETURN "inserted" TIMESTAMP for this row.
	public function getInserted() {
		return $this->inserted;
	}
	
	// Returns an associative array corresponding to the content of a row in DB table billingProfile,
	// (minus "active", which is irrelevant for shadowBillingProfiles, so the BillingProfile class 
	// has to handle that itself).
	protected function toArray() {		
		return array ('billingProfileId' => $this->getBillingProfileId()
					,'companyId' => $this->getCompanyId()
					,'companyPersonId' => $this->getCompanyPersonId()
					,'personEmailId' => $this->getPersonEmailId()
					,'companyEmailId' => $this->getCompanyEmailId()
					,'personLocationId' => $this->getPersonLocationId()
					,'companyLocationId' => $this->getCompanyLocationId()
					,'multiplier' => $this->getMultiplier()
					,'dispatch' => $this->getDispatch()								
					,'termsId' => $this->getTermsId()
					,'contractLanguageId' => $this->getContractLanguageId()
					,'gracePeriod' => $this->getGracePeriod()												
					,'inserted' => $this->getInserted()
					);
	}
}
?>