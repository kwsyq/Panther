<?php
/* task_openWorkOrdersAll.class.php

    EXECUTIVE SUMMARY: an API class. Returns all workOrders that have open workOrderTasks (optionally limited to workOrders for one individual).
    >>>00026 According to Martin 2018-11-13, this is not fully up to date and isn't currently being used.

    See http://sssengwiki.com/Joe%27s+code+notes%3A+inc_classes+N-Z#API
    for general context. As of 2019-03, these "API classes" have very limited use, mainly for mobile apps. 
    Nothing from the web application ever comes through these APIs. The plan is that anything but our own 
    web application & cron jobs should come through this.
    
    * Extends API
    * Public methods:
    ** __construct($personId, $customer)
    ** run()
*/

class task_openWorkOrdersAll extends API {

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
    INPUT $_REQUEST['userId']: If present and valid, limit to this user. Zeroing
     this input is the same as omitting it.
    ACTION: Returns information about all of the workOrders that have open tasks,
      optionally limited to a single user.
    EFFECTIVE RETURN is via setStatus and setData.    
    
    >>>00001 Identification of workOrders that have open tasks comes down to how 
    Time::getWorkOrderTasksByDisplayType() behaves on a time object constructed 
    with $begin=false && $displayType='incomplete', something I (JM) have not yet 
    fully analyzed 2019-03.)
    
    ----
    If $_REQUEST['userId'] is zero, then for each current employee, we create a 
    "Time" object -- $time = Time($employee, false, 'incomplete') -- and call 
    $time->getWorkOrderTasksByDisplayType() to get a list of workOrderTasks. 
    (>>> JM: as noted above, I'm not sure exactly what tasks that returns.) 
    We filter out any "fake" tasks (ones that are implicit as parents of "real" 
    tasks, but are not themselves explicit), and put the content relating to 
    "real" tasks in a multidimensional array $wots[jobId][workOrderId][], 
    each of whose elements represents a task as returned by 
    Time::getWorkOrderTasksByDisplayType() (>>> JM: which, again is not yet fully 
    characterized, but which is presumably some sort of multidimensional 
    associative array). We then build a flat array $workordertasks, by looping 
    through the jobs & workorders in $wots.

    We loop through this flat $workordertasks, checking once again 
    (>>> JM: redundantly, I think) to make sure the tasks are "real", 
    counting distinct workOrderId values (there's other stuff going on, 
    but it's only relevant to the case where a userId was specified). 
    
    On RETURN, status='success' and data is an array (one array entry for 
    each employee) of associative arrays each with two elements:
        * 'name': employee name in "lastName, firstName form
        * 'openWorkOrders': a count of open workOrders for that employee.
            So we would have (for example) data[i]['openWorkOrders']+- is a count 
            for the person whose name is -+data~np~[i]['name']. 

    ----
    If $_REQUEST['userId'] is non-zero, we actually go through much of the same 
    looping, but throw away all of the data that is not for the relevant employee; 
    as of 2019-03 we don't even do a 'break' when we've got what we need. 
    The return has a similar form, with only one element in the data array. 
    data[0]['openWorkOrders'] won't be just a number, though. Instead, it will 
    be an array with an element for each matching workOrder. Each element of that 
    array is, in turn, an associative array with elements:
        * 'wn': workOrder name (as returned by WorkOrder::getName())
        * 'wid': workOrderId()
        * 'jn': job name (as returned by Job::getName()). 
    So we would have (for example) data[0]['openWorkOrders'][i]['wid'] is a workOrderId.     
    */
	public function run() {
		$userId = isset($_REQUEST['userId']) ? intval($_REQUEST['userId']) : 0;
		$db = DB::getInstance();
		$employees = $this->getUser()->getCustomer()->getEmployees(1);

		$ret = array();

		foreach ($employees as $ekey => $employee) {
		    // >>>00017: if $userId is nonzero, seems we could save a lot of work
		    //  by comparing IDs here and continuing if this is a different employee.
			$time = new Time($employee,false,'incomplete');
			$workordertasks = $time->getWorkOrderTasksByDisplayType(); // See discussion above
			$wots = array();

			foreach ($workordertasks as $wkey => $wot) {
				if (isset($wot['jobId'])) {
					if ($wot['type'] == 'real') {
						$wots[$wot['jobId']][$wot['workOrderId']][] = $wot;
					}
				}
			}
			// At this point $wots is a multi-dimensional array of workOrderTask information for this employee:
			//  $wots[$jobId][$workOrderId][index] is an associative array that somehow represents a workOrderTask
			//  (>>>00001 the content of this last associative array
			//  being unclear until we better analyze Time::getWorkOrderTasksByDisplayType)

			$workordertasks = array();

			foreach ($wots as $wkey => $job) {
				foreach ($job as $jkey => $workorder) {
					foreach ($workorder as $wokey => $workordertask) {
						$workordertasks[] = $workordertask;
					}
				}
			}
			// At this point $workordertasks is a flat array of associative arrays that each 
			//  somehow represent a workOrderTask (>>>00001 the content of this last associative array
			//  being unclear until we better analyze Time::getWorkOrderTasksByDisplayType)
			// JM 2019-03-06: So it looks to me like we first put this in the more structured
			//  $wots just to force a particular order, making sure all workOrderTasks for a particular
			//  workOrder are together.

			$lastWorkOrderId = 0;
			$lastJobId = 0;
			$tally = 0;        // Looks to me (JM 2019-03) like this is relevant only if $userId is zero.
			$fudged = array(); // Looks to me (JM 2019-03) like this is relevant only if $userId is nonzero.

			foreach ($workordertasks as $wkey => $workordertask) {
			    // Test here to skip any $workordertask['type'] == 'fake'
				if ($workordertask['type'] == 'real') {
					if ($lastWorkOrderId != $workordertask['workOrderId']) {
					    // Start of a new workOrder
						$wo = new WorkOrder($workordertask['workOrderId']);
						$j = new Job($wo->getJobId());						
						$fudged[] = array('wn' => $wo->getName(),'wid' => $wo->getWorkOrderId(),'jn' => $j->getName());
						$tally++;
					}
				}
				$lastWorkOrderId = $workordertask['workOrderId'];
			}

			if (intval($userId)) {
				if ($employee->getUserId() == intval($userId)) {
					$emp = array();
					$emp['name'] = $employee->getFormattedName(1);
					$emp['openWorkOrders'] = $fudged;
					$ret[] = $emp;
					// >>>00017: why not break out of the loop here?
				}
			} else {
				$emp = array();
				$emp['name'] = $employee->getFormattedName(1);
				$emp['openWorkOrders'] = $tally;
				$ret[] = $emp;
			}
		}

		$this->setStatus('success');
		$this->setData(array('key' => 'employees', 'value' => $ret));
	}
}

?>
