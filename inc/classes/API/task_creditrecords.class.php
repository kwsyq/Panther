<?php 
/* task_creditrecords.class.php

    EXECUTIVE SUMMARY: an API class. Returns recent data from the creditRecords DB table.
    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_creditrecords extends API {

    // Inputs to the constructor are passed to the parent constructor, which in turn
    // extends SSSEng.
    // Typically constructed for current user, but no default to make it so. 
    // Constructor can optionally take a personId & a customer object to set user.
    // INPUT $personId: unsigned integer, primary key into DB table Person.
    // INPUT $customer: Customer object    
	function __construct($personId, $customer) {
		$this->customer = $customer;		
		parent::__construct($personId, $customer);	
	}
	
/*
    INPUT $_REQUEST['count']: sets a limit on how many rows to return; the default is 20.
    ACTION: Returns recent data from the creditRecords DB table
    
    EFFECTIVE RETURN is via setStatus and setData.
    Always sets status to 'success'. (There is one place where status='fail' is set, but I - JM - 
        don't see any path through the code where it would not be overridden.
    Sets data as a single key-value pair:
    * 'records': an array of associative arrays, each of which corresponds to 
       a row in DB table creditRecord. It gives the canonical representation
       with column names as indexes, plus one additional index: 
        * canonical representation: columns as indexes
        * 'inserted_formatted': date of the 'inserted' TIMESTAMP in "m/d/Y" form.    
*/
	public function run() {
		$this->setStatus('fail');		
		$count = isset($_REQUEST['count']) ? intval($_REQUEST['count']) : 0;		
		if (!$count){
			$count = 20;
		}
		
		$db = DB::getInstance();			
		$ret = array();
		
		$query  = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".creditRecord ";
		$query .= " order by creditRecordId desc limit " . intval($count) . " "; // effectively get reverse chronological order.
			
		if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0){
				while ($row = $result->fetch_assoc()){
					$row['inserted_formatted'] = date("m/d/Y",strtotime($row['inserted']));
					$ret[] = $row;					
				}
			}
		} // >>>00002 else ignores failure on DB query!

		$this->setStatus('success');
		$this->setData(array('key' => 'records', 'value' => $ret));
	} // END public function run
}

?>