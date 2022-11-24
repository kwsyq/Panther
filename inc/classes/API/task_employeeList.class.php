<?php

/* task_employeeList.class.php

    EXECUTIVE SUMMARY: an API class. Returns a full list of current employees of the  
    customer associated with the current logged-in user.

    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/


class task_employeeList extends API {

    // Inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
    // Typically constructed for current user, but no default to make it so. 
    // Constructor can optionally take a personId & a customer object to set user.
    // INPUT $personId: unsigned integer, primary key into DB table Person.
    // INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		parent::__construct($personId, $customer);
	}

	/*
	    NO INPUT, just looks at who is asking.
        EFFECTIVE RETURN is via setStatus and setData.
        Always sets status to 'success'.
        Sets data as a single key-value pair:
        * 'employees': an array of associative arrays, each of which consists just of:
            * 'name': firstName + space + lastName
            * 'userId': personId
    */
	public function run() {
		$db = DB::getInstance();
		
		$employees = $this->getUser()->getCustomer()->getEmployees(1);

		$ret = array();

		foreach ($employees as $ekey => $employee) {
			$emp = array();
			$emp['name'] = $employee->getFormattedName(1);
			$emp['userId'] = $employee->getUserId();
			$ret[] = $emp;
		}

		$this->setStatus('success');
		$this->setData(array('key' => 'employees', 'value' => $ret));

	} // END public function run
}

?>
