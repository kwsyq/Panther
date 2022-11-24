<?php
/* inc/classes/ShadowBillingProfile.class.php

EXECUTIVE SUMMARY: 
This is similar to the BillingProfile class, but is for the shadowBillingProfile 
that we store in DB tables contractBillingProfile and invoiceBillingProfile. This is an encoding of
the content of a row from DB table BillingProfile. The idea is to preserve it as it was at a particular point
in time, so that if the underlying data in DB table BillingProfile changes, this data will still remain as
it was.

* Extends AbstractBillingProfile, constructed for current user, or for a User object passed in. Constructed
  from the content of the shadowBillingProfile row in contractBillingProfile or invoiceBillingProfile.

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

NOTE that there are no public "set" methods: once created, the shadowBillingProfile is read-only.

* Other public functions: 
** __construct($billingProfileBlob = null, User $user = null)
** toArray()
** getShadowBillingProfileBlob()

** public static function constructFromBillingProfile($billingProfile)

*/

class ShadowBillingProfile extends AbstractBillingProfile {
    
    private $shadowBillingProfileBlob;
    
    // Class member variables for the BillingProfile columns we care about here
    //  are all handled by AbstractBillingProfile   
    // See documentation of that class and of the table for further details
    
    // INPUT $id: a billingProfileId from the BillingProfile table
    // INPUT $user: User object; default set in ancestor class SSSEng is current user. 
	public function __construct($billingProfileBlob = null, User $user = null) {
		parent::__construct($user);
		$this->shadowBillingProfileBlob = $billingProfileBlob; 
		$this->load($billingProfileBlob);
	}

	// INPUT $billingProfileBlob: a shadowBillingProfileId from DB tables contractBillingProfile or invoiceBillingProfile.
	private function load($billingProfileBlob) {
	    // JM 2020-10-30: >>>00032 >>>00006 Cristi, when you rework the shadowBillingProfile in the DB to use JSON,
	    // you will need to change this. You can look at Contract::setData as a model of what may be involved.
        $bp = unserialize(base64_decode($billingProfileBlob));

        // Set all of the private members that represent the DB content 
        $this->setBillingProfileId($bp['billingProfileId']);
        $this->setCompanyId($bp['companyId']);  
        $this->setCompanyPersonId($bp['companyPersonId']);
        $this->setPersonEmailId($bp['personEmailId']);
        $this->setCompanyEmailId($bp['companyEmailId']);
        $this->setPersonLocationId($bp['personLocationId']);
        $this->setCompanyLocationId($bp['companyLocationId']);
        $this->setInserted($bp['inserted']);
        $this->setMultiplier($bp['multiplier']);
        $this->setDispatch($bp['dispatch']);
        $this->setTermsId($bp['termsId']);
        $this->setContractLanguageId($bp['contractLanguageId']);
        $this->setGracePeriod($bp['gracePeriod']);
	} // END private function load
		
	// Returns an associative array corresponding to the content of a row in DB table billingProfile, 
	// based on the content of this object.
	public function toArray() {
	    return parent::toArray();
	}
	
	public function getShadowBillingProfileBlob() {
	    return $this->shadowBillingProfileBlob;
	}

	// INPUT $billingProfile: BillingProfile object
    // JM 2020-10-30: >>>00032 >>>00006 Cristi, as above, when you rework the shadowBillingProfile in the DB to use JSON,
    // you will need to change this.
    public static function constructFromBillingProfile($billingProfile) {
        $shadowBillingProfileBlob = base64_encode(serialize($billingProfile->toArray()));
        return new ShadowBillingProfile($shadowBillingProfileBlob);
    }
}

?>