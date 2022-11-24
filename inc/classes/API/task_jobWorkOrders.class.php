<?php
/* task_jobWorkOrders.class.php

    EXECUTIVE SUMMARY: an API class. Returns information about all of the workOrders for a given job.

    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_jobWorkOrders extends API {
	
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
    INPUT $_REQUEST['jobNumber']: Job Number, e.g. 's1909076'
    ACTION: Returns information about all of the workOrders for the job.
    EFFECTIVE RETURN is via setStatus and setData.

    On success, status='success'; >>>00001 looks like on failure (including "no such job"), status isn't set at all.
    Sets data as a key-value pair (that is, an associative array with one element):
    
    * 'workOrders': an array of associative arrays, each representing a workOrder associated with this job. 
      Those associative arrays have the following members: 
        * 'workOrderId'
        * 'jobId' - always this same job
        * 'workOrderDescriptionTypeId'
        * 'description'
        * 'deliveryDate'
        * 'workStreamId' // This is vestigial 2020-01-13 JM
        * 'workOrderStatusId'
        * 'genesisDate'
        * 'intakeDate'
        * 'isVisible'
        * 'contractNotes'
        * 'statusName'
    */
	public function run() {		
		$db = DB::getInstance();
		$number = isset($_REQUEST['jobNumber']) ? $_REQUEST['jobNumber'] : '';		
		$number = trim($number);		
		$jobId = 0;
		
		if (strlen($number)) {
			$query = " select * ";
			$query .= " from " . DB__NEW_DATABASE . ".job ";
			$query .= " where number = '" . $db->real_escape_string($number) . "' ";
			
			if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {
					$row = $result->fetch_assoc();
					$jobId = intval($row['jobId']);
				}
			} // >>>00002 else ignores failure on DB query!			
		}
		
		if (intval($jobId)) {			
			$job = new Job($jobId);
			
			if (intval($job->getJobId())) {
				$workOrders = $job->getWorkOrders();				
				$ret = array();				
				
				foreach ($workOrders as $wkey => $workOrder) {					
					$ret[] = $workOrder->toArray();
				}
				
				$this->setStatus('success');
				$this->setData(array('key' => 'workOrders', 'value' => $ret));
			}			
		}		
	} // END public function run	
}

?>