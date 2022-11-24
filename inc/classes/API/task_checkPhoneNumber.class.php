<?php 
/* task_checkPhoneNumber.class.php

    EXECUTIVE SUMMARY: an API class. For a given phone number, returns a collection 
     of data about how it may be associated with one or more company and/or person. 
    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/


class task_checkPhoneNumber extends API {
    // Inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
	// Typically constructed for current user, but no default to make it so. 
	// Constructor can optionally take a personId & a customer object to set user.
	// INPUT $personId: unsigned integer, primary key into DB table Person.
	// INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		parent::__construct($personId, $customer);	
	}
	
	/* INPUT $_REQUEST['clidnum']
	   ACTION: Looks in various DB tables for phones matching gateway input.
	   
	   EFFECTIVE RETURN is via setStatus and setData.
	   Always sets status to 'success'. 
	   Sets data as a single key-value pair. The only key is 'numbers'; the value is an associative array as follows:
       * 'numbers': associative array
           * 'person': ARRAY OF associative arrays, one for each matching phone number found in DB table personPhone. For each such match:
               * 'id': personId
               * 'name': lastname, firstname 
           * 'company': ARRAY OF associative arrays, one for each matching phone number found in DB tables companyPhone. For each such match:
               * 'id': companyId
               * 'name': company name 
           * 'companyPerson': ARRAY OF associative arrays, one for each matching phone number found via either of two joins.
               * FIRST JOIN: join DB tables companyPersonContact and personPhone, where the 
                 companyPerson.companyPersonContactTypeId is CPCONTYPE_PHONEPERSON.
               * SECOND JOIN: join DB tables companyPersonContact and companyPhone, where the 
                 companyPerson.companyPersonContactTypeId is CPCONTYPE_PHONECOMPANY.
             For each such match the associative array contains:
               * 'id': companyPersonId
               * 'name': companyName followed by a forward slash ('/') with two non-breaking spaces on either side, then last name, first name
               * 'companyName': like it says
               * 'companyId': like it says
               * 'companyPerson': last name, first name
               * 'personId': like it says
               * 'type': 'person' or 'company', depending which way we found it.
    */
	public function run() {		
		ini_set('display_errors',1);
		error_reporting(-1);
		
		$numbers = array();
		$db = DB::getInstance();		
		$clidnum = isset($_REQUEST['clidnum']) ? $_REQUEST['clidnum'] : '';
		
		$query = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".personPhone  ";
		$query .= " where phoneNumber = '" . $db->real_escape_string($clidnum) . "' ";

		$row = false;
			
		if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {					
					$person = new Person($row['personId']);					
					$numbers['person'][] = array(
											'id' => $row['personId'],
											'name' => $person->getName()
											);					
				}			
			}			
		} // >>>00002 else ignores failure on DB query! Does this throughout file, 
          // haven't noted each instance.

		$query = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".companyPhone  ";
		$query .= " where phoneNumber = '" . $db->real_escape_string($clidnum) . "' ";

		$row = false;
			
		if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while($row = $result->fetch_assoc()) {					
					$company = new Company($row['companyId']);					
					$numbers['company'][] = array(
											'id' => $row['companyId'],
											'name' => $company->getName()
											);					
				}			
			}			
		}
		
		$query = " select cpc.companyPersonId,cpc.companyPersonContactTypeId, cpc.id, pp.personPhoneId  ";
		$query .= " from " . DB__NEW_DATABASE . ".companyPersonContact cpc  ";
		$query .= " join " . DB__NEW_DATABASE . ".personPhone pp on cpc.id = pp.personPhoneId  ";
		$query .= " where cpc.companyPersonContactTypeId = " . intval(CPCONTYPE_PHONEPERSON) . " ";
		$query .= " and pp.phoneNumber = '" . $db->real_escape_string($clidnum) . "' ";

		if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {					
					$companyPerson = new CompanyPerson($row['companyPersonId']);
					$company = $companyPerson->getCompany();
					$person = $companyPerson->getPerson();					
					$numbers['companyPerson'][] = array(
											'id' => $row['companyPersonId'],
											'name' => $companyPerson->getName(),
											'companyName' => $company->getName(),
											'companyId' => $company->getCompanyId(),
											'companyPerson' => $person->getName(),
											'personId' => $person->getPersonId(),
											'type' => 'person'											
											);
					
				}			
			}			
		}		

		$query = " select cpc.companyPersonId,cpc.companyPersonContactTypeId, cpc.id, pp.companyPhoneId  ";
		$query .= " from " . DB__NEW_DATABASE . ".companyPersonContact cpc  ";
		$query .= " join " . DB__NEW_DATABASE . ".companyPhone pp on cpc.id = pp.companyPhoneId  ";
		$query .= " where cpc.companyPersonContactTypeId = " . intval(CPCONTYPE_PHONECOMPANY) . " ";
		$query .= " and pp.phoneNumber = '" . $db->real_escape_string($clidnum) . "' ";

		if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0){

				while($row = $result->fetch_assoc()){
					
					$companyPerson = new CompanyPerson($row['companyPersonId']);

					$company = $companyPerson->getCompany();
					$person = $companyPerson->getPerson();
					
					$numbers['companyPerson'][] = array(
											'id' => $row['companyPersonId'],
											'name' => $companyPerson->getName(),
											'companyName' => $company->getName(),
											'companyId' => $company->getCompanyId(),
											'companyPerson' => $person->getName(),
											'personId' => $person->getPersonId(),
											'type' => 'company'											
											);
					
				}			
			}			
		}
				
		$this->setStatus('success');
		$this->setData(array('key' => 'numbers', 'value' => $numbers));
		
	} // END public function run
	
}

?>