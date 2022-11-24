<?php 
/* inc/classes/Task.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Task table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular task.
* Public methods
** __construct($id = null, User $user = null)
** setIcon($val)
** setDescription($val)
** setBillingDescription($val)
** // setEstQuantity($val) // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
** // setEstCost($val)     // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
** setSortOrder($val)
** setActive($val)
** setWikiLink($val)
** getTaskId()
** getIcon()
** getDescription()
** getBillingDescription()
** // getEstQuantity()  // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
** // getEstCost()      // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table      
** getTaskTypeId()
** getParentId()        // ADDED 2020-11-10 JM
** getSortOrder()
** getActive()
** getWikiLink()
** getChilds($alphabetical=false, $active=true)
** hasChild($active=true)
** climbTree()
** getTaskType()
** getTaskDetails()
** update($val)
** save()
** toArray()

** public static function validate($taskId, $unique_error_id=null)
** public static function getZeroLevelTasks($alphabetical=false, $active=true)
** public static function addTask($parentId, $description)
*/

class Task extends SSSEng {
    // The following correspond exactly to columns of DB table Task
    // See documentation of that table for further details.
	private $taskId;
	private $parentId; // ADDED 2020-11-10 JM, no idea why this wasn't there before.  
	private $icon;
	private $description;
	private $billingDescription;
	// private $estQuantity; // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	// private $estCost;     // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	private $taskTypeId;
	private $sortOrder;
	// private $viewMode; // REMOVED 2020-10-28 JM getting rid of viewmode
	private $active;
	private $wikiLink;
	
    // INPUT $id: a taskId from the Task table
    // INPUT $user: User object, typically current user.
    //  NOTE that parent class SSSEng will default this to the
    //  current logged-in user, if there is one. 
	public function __construct($id = null, User $user = null) {	
		parent::__construct($user);
		$this->load($id);	
	}	
	
	// INPUT $val here is input $id for constructor.
	private function load($val) {
		if (is_numeric($val)) {			
			$query = "SELECT * FROM " . DB__NEW_DATABASE . ".task ";
			$query .= "WHERe taskId = " . intval($val) . ";";

			$result = $this->db->query($query);
			if ($result) {
				if ($result->num_rows > 0) {					
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$row = $result->fetch_assoc();
					
					$this->setTaskId($row['taskId']);	
					$this->setIcon($row['icon']);	
					$this->setDescription($row['description']);	
					$this->setBillingDescription($row['billingDescription']);	
					// $this->setEstQuantity($row['estQuantity']); // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table	
					// $this->setEstCost($row['estCost']);	       // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
					$this->setTaskTypeId($row['taskTypeId']);
					$this->setParentId($row['parentId']);
					$this->setSortOrder($row['sortOrder']);	
					$this->setActive($row['active']);
					// $this->setViewMode($row['viewMode']); // REMOVED 2020-10-28 JM getting rid of viewmode						
					$this->setWikiLink($row['wikiLink']);
				} // >>>00002 else ignores that we got a bad taskId!
			} // >>>00002 else ignores failure on DB query! Does this throughout file, 
			  // haven't noted each instance.
		} // >>>00002 else ignores that we got a bad taskId!
		// REMOVED creation from an input array JM 2020-11-10
	} // END private function load	
	
	// Set primary key
	// INPUT $val: primary key (taskId)
	private function setTaskId($val){
		$this->taskId = intval($val);
	}
	
	// Set icon name within the icons task directory
	//  WEBROOT/cust/CUSTOMER_SHORT_NAME/img/icons_task/,  
	//  e.g. within ... /sssnew.com/cust/ssseng/img/icons_task
	// INPUT $val: filename, relative to the icons task directory  
	public function setIcon($val) {
		$val = trim($val);
		$val = substr($val, 0, 96);  // >>>00002: truncates silently.
		$this->icon = $val;
	}
	
	// INPUT $val: description string
	public function setDescription($val) {
		$val = trim($val);
		$val = substr($val, 0, 255);  // >>>00002: truncates silently.
		$this->description = $val;
	}
	
	// INPUT $val: billing description string, often the same as description string
	public function setBillingDescription($val) {
		$val = trim($val);
		$val = substr($val, 0, 250);  // >>>00002: truncates silently.
		$this->billingDescription = $val;
	}
	
	/* BEGIN REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	// INPUT $val: estimated quantity, should be a floating point number if relevant
	public function setEstQuantity($val) {
		if (filter_var($val, FILTER_VALIDATE_FLOAT) !== false) {
			$this->estQuantity = $val;
		} else {
			$this->estQuantity = 0;
		}
	}
	// END REMOVED 2020-10-29 JM
	*/
	
	/* BEGIN REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	// INPUT $val: estimated quantity, should be a floating point number if relevant
	public function setEstCost($val) {
		if (filter_var($val, FILTER_VALIDATE_FLOAT) !== false) {
			$this->estCost = $val;
		} else {
			$this->estCost = 0;
		}
	}
	// END REMOVED 2020-10-29 JM
	*/
	
	// INPUT $val: foreign key into DB table TaskType 
	private function setTaskTypeId($val) {
		$this->taskTypeId = intval($val);
	}
	
	// INPUT $val: taskId of parent
	private function setParentId($val) {
	    $this->parentId = $val;
	}
	
	// INPUT $val: Order for tasks in a given category. 
	public function setSortOrder($val) {
		$this->sortOrder = intval($val);
	}
	
	// INPUT $val: quasi-Boolean, 0 allows effective deletion of a row for most purposes
	public function setActive($val) {
		$this->active = intval($val);
	}
	
    /* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
	// Whether or not this gets printed in contract/invoice (vs. internal admin tasks).
	// INPUT $val: bit-string; so far just
	//  * contract => bit 1 ("WOT_VIEWMODE_CONTRACT")
	//  * timesheet => bit 2 ("WOT_VIEWMODE_TIMESHEET")
	// So typical input is 3.
	public function setViewMode($val) {
		$this->viewMode = intval($val);
	}
	// END REMOVED 2020-10-28 JM
	*/
	
	// INPUT $val - allows indication of a relevant wiki page, used in
	//  conjunction with global WIKI_URL. Can be blank or null if no such.
	public function setWikiLink($val) {
		$val = trim($val);
		$val = substr($val, 0, 512);  // >>>00002: truncates silently.
		$this->wikiLink = $val;
	}	
	
	// RETURN primary key
	public function getTaskId(){
		return $this->taskId;
	}
	
	// RETURN icon name within the icons task directory.
	// This directory can be accessed over the web as
	//  http://WEBROOT/cust/CUSTOMER_SHORT_NAME/img/icons_task/,
	//  e.g. http://ssseng.com/cust/ssseng/img/icons_task
	// but internally as of v2020-4 the files are really in 
	//  /var/www/ssseng_documents/icons_task
	public function getIcon(){
		return $this->icon;
	}
	// RETURN description
	public function getDescription(){
		return $this->description;
	}
	// RETURN billing description
	public function getBillingDescription(){
		return $this->billingDescription;
	}
	/* BEGIN REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	// RETURN estimated quantity, can be 0
	public function getEstQuantity(){
		return $this->estQuantity;
	}
	// RETURN estimated cost, can be 0
	public function getEstCost(){
		return $this->estCost;
	}
	// END REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	*/
	// RETURN foreign key into DB table TaskType
	public function getTaskTypeId(){
		return $this->taskTypeId;
	}
	
	// RETURN taskId of parent
	public function getParentId() {
	    return $this->parentId;
	}
	
	// RETURN order for tasks in a category. 
	public function getSortOrder(){
		return $this->sortOrder;
	}
	// RETURN quasi-Boolean, 0 allows effective deletion of a row for most purposes
	public function getActive(){
		return $this->active;
	}
	
	/* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
	// RETURN Whether or not this gets printed in contract/invoice (vs. internal admin tasks).
	// Bit-string; so far just
	//  * contract => bit 1 ("WOT_VIEWMODE_CONTRACT")
	//  * timesheet => bit 2 ("WOT_VIEWMODE_TIMESHEET")
	public function getViewMode(){
		return $this->viewMode;
	}
	// END REMOVED 2020-10-28 JM
	*/
	
	// RETURN: if not null, empty string, etc.: relevant wiki page, used in
	//  conjunction with global WIKI_URL. Can be blank or null if no such.
	public function getWikiLink(){
		return $this->wikiLink;
	}	
	
	// RETURN an array of Task objects representing child tasks of the present task
    // INPUT $alphabetical: optional Boolean, default false. If true order alphabetically by description, otherwise use sortOrder.
    //  Everything related to $alphabetical added 2020-08-24 JM for v2020-4, to address http://bt.dev2.ssseng.com/view.php?id=229 
	// INPUT $active: if true, only get "active" children (not soft-deleted). Argument added 2020-11-10 for v2020-4. 
	public function getChilds($alphabetical=false, $active=true) {
		$ret = array();
		$query = " SELECT taskId ";
		$query .= "FROM " . DB__NEW_DATABASE . ".task ";
		$query .= "WHERE parentId = " . intval($this->getTaskId()) . " ";
		if ($active) {
		    $query .= "AND active = 1 ";
		}
		if ($alphabetical) {
		    $query .= " ORDER BY description;";
		} else {
		    $query .= " ORDER BY sortOrder;";
		}
		
		$result = $this->db->query($query);
		if ($result) {
            while ($row = $result->fetch_assoc()) {					
                $ret[] = new Task($row['taskId']);					
            }
		} else {
		    $this->logger->errorDb('1604947023', "Hard DB error", $this->db);
		}			

		return $ret;		
	}
	
	// INPUT $active: Boolean, whether we only want to look for "active" child tasks, default true
	// RETURN Boolean: true => a child task exists 
	public function hasChild($active=true) {
		$ret = false;
		$query = " SELECT taskId ";
		$query .= "FROM " . DB__NEW_DATABASE . ".task ";
		$query .= "WHERE parentId = " . intval($this->getTaskId()) . " "; 
		if ($active) {
		    $query .= "AND active = 1 ";
		}
		$query .= " LIMIT 1;";
		
		$result = $this->db->query($query);
		if ($result) {
            if ($row = $result->fetch_assoc()) {					
                $ret = true;					
            }
		} else {
		    $this->logger->errorDb('1604947890', "Hard DB error", $this->db);
		}			

		return $ret;		
	}

	// RETURNs an array of Task objects. The query sequence traces up the 
	//  hierarchy of parents, until it reaches a task with no parent. 
	//  The returned array goes in order DOWN the hierarchy, from a task 
	//  with no parent to the current task. 
	public function climbTree() {
		$ret = array();
		
		$query =  " SELECT T2.taskId , T2.description ";
		$query .= " FROM ( ";
		$query .= "     SELECT ";
		$query .= "         @r AS _id, ";
		$query .= "         (SELECT @r := parentId FROM " . DB__NEW_DATABASE . ".task WHERE taskId = _id) AS parentId, ";
		$query .= "         @l := @l + 1 AS lvl ";
		$query .= "     FROM ";
		$query .= "         (SELECT @r := " . intval($this->getTaskId()) . ", @l := 0) vars, ";
		$query .= "         " . DB__NEW_DATABASE . ".task t ";
		$query .= "     WHERE @r <> 0) T1 ";
		$query .= " JOIN " . DB__NEW_DATABASE . ".task T2 ";
		$query .= " ON T1._id = T2.taskId ";
		$query .= " ORDER BY T1.lvl desc ";		

		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {					
				while ($row = $result->fetch_assoc()) {						
					$ret[] = new Task($row['taskId']);						
				}		
			}				
		}
		
		return $ret;		
	}
	
	// RETURNs  an associative array whose elements correspond to the columns 
	//  in DB table taskType: taskTypeId, typeName, displayOrder. RETURNs false
	//  on a bad taskType.
	public function getTaskType() {
		$ret = false;
		
		// Query uses primary key into taskType, so there should be only one row returned 
		$query = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".taskType ";
		$query .= " where taskTypeId = " . intval($this->getTaskTypeId()) . " ";
		
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
			    /* BEGIN REPLACED 2020-03-02 JM
			    // JM 2020-03-02: No good reason for a 'while'
				while ($row = $result->fetch_assoc()) {		
					$ret = $row;
				}
				*/
				// BEGIN REPLACEMENT 2020-03-02 JM
				$row = $result->fetch_assoc();
				$ret = $row;
				// END REPLACEMENT 2020-03-02 JM
			}		
		}		
		
		return $ret;
	}
	
	// RETURNs an array of associative arrays whose elements correspond to the 
	//  columns in DB table taskDetail: taskDetailId, taskId, detailRevisionId. 
	//  detailRevisionId refers into the Details database. 
	public function getTaskDetails() {
		$details = array();
	
		$query = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".taskDetail ";
		$query .= " where taskId = " . intval($this->getTaskId()) . " ";
	
		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0){					
				while ($row = $result->fetch_assoc()) {	
					$details[] = $row;	
				}	
			}	
		}	
	
		return $details;	
	}
	
	// INPUT $val is an associative array that can have any or all of the following elements:
	//  * 'description'
	//  * 'billingDescription'
	//  * // 'estQuantity' // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	//  * // 'estCost'     // REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
	//  * 'taskTypeId'
	//  * 'active'
	//  * 'wikiLink' 
	public function update($val) {	
		if (is_array($val)) {
			if (isset($val['description'])) {				
				$this->setDescription($val['description']);
			}
			
			if (isset($val['billingDescription'])){			
				$this->setBillingDescription($val['billingDescription']);			
			}
				
			/* BEGIN REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
			if (isset($val['estQuantity'])) {
				$this->setEstQuantity($val['estQuantity']);
			}
				
			if (isset($val['estCost'])) {					
				$this->setEstCost($val['estCost']);					
			}
			// END REMOVED 2020-10-29 JM
			*/
	
			if (isset($val['taskTypeId'])) {					
				$this->setTaskTypeId($val['taskTypeId']);					
			}
				
			/* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
			if (isset($val['viewMode'])) {					
				$this->setViewMode($val['viewMode']);					
			}
			// END REMOVED 2020-10-28 JM
			*/ 
			
			if (isset($val['active'])) {
			    $this->setActive($val['active']);					
			}				
			
			if (isset($val['wikiLink'])) {					
				$this->setWikiLink($val['wikiLink']);					
			}
		}
		
		$this->save();	
	} // END public function update
	
	// save same values handled by function update; it also handles icon (added 2020-11-04 JM) and sortOrder (added 2020-11-09 JM) 
	// This does the actual DB update both for that function and for "set" methods	
	// Ability to actively null out taskTypeId and wikiLink added JM 2020-11-04
	public function save() {
	    $taskTypeId = $this->getTaskTypeId();
	    $wikiLink = $this->getWikiLink();
	    $icon = $this->getIcon();
	    
		$query = "UPDATE " . DB__NEW_DATABASE . ".task SET ";
		$query .= "description = '" . $this->db->real_escape_string($this->getDescription()) . "'";
		$query .= ", billingDescription = '" . $this->db->real_escape_string($this->getBillingDescription()) . "'";
		/* BEGIN REMOVED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
		$query .= " ,estQuantity = " . $this->db->real_escape_string($this->getEstQuantity()) . " ";
		$query .= " ,estCost = " . $this->db->real_escape_string($this->getEstCost()) . " ";
		// END REMOVED 2020-10-29 JM
		*/
		if ($taskTypeId) { 
		    $query .= ", taskTypeId = $taskTypeId";
		} else { 
		    $query .= ", taskTypeId = NULL";
		}
		// $query .= " ,viewMode = " . intval($this->getViewMode()) . " "; // REMOVED 2020-10-28 JM getting rid of viewmode
		if ($wikiLink) {
		    $query .= ", wikiLink = '" . $this->db->real_escape_string($wikiLink) . "'";
		} else {
		    $query .= ", wikiLink = NULL";
		}
		if ($icon) {
		    $query .= ", icon = '" . $this->db->real_escape_string($icon) . "'";
		} else {
		    $query .= ", icon = NULL";
		}
		$query .= ", active = " . intval($this->getActive()) . " ";
		$query .= ", sortOrder = " . intval($this->getSortOrder()) . " ";
		$query .= "WHERE taskId = " . intval($this->getTaskId()) . ";";
		
		$result = $this->db->query($query);
		if (!$result) {
		    $this->logger->errorDb('1604519870', "Hard DB error", $this->db);
		}
	}
	
	// RETURNs an associative array representing some, but not all, of the
	//  private members of this class that are drawn from the DB.
	// For reasons that are mostly historical as of 2020-10-29, we return a zeroed 'estQuantity' and 'estCost' here.
	//  That's because this is often used as a "starter" for a workOrderTask or as a placeholder for a "fake task" (one whose
	//  descendant is in the workOrder, but the task itself is not explicitly represented).
	public function toArray() {
		return array(				
				'taskId' => $this->getTaskId(),
				'icon' => $this->getIcon(),
				'description' => $this->getDescription(),
				'billingDescription' => $this->getBillingDescription(),
				/* BEGIN REPLACED 2020-10-29 JM: getting rid of estQuantity, estCost for task table
				'estQuantity' => $this->getEstQuantity(),
				'estCost' => $this->getEstCost(),
				// END REPLACED 2020-10-29 JM
				*/ 
				// BEGIN REPLACEMENT 2020-10-29 JM
				'estQuantity' => 0,
				'estCost' => 0,
				// END REPLACEMENT 2020-10-29 JM
				'taskTypeId' => $this->getTaskTypeId(),
				'sortOrder' => $this->getSortOrder()				
				);
	}
	
	/*
	// This method was moved into the base class in version 2020-2.
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}*/
	
    // Return true if the id is a valid taskId, false if not
    // INPUT $taskId: taskId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($taskId, $unique_error_id=null) {
        global $db, $logger;
        Task::loadDB($db);
        
        $ret = false;
        $query = "SELECT taskId FROM " . DB__NEW_DATABASE . ".task WHERE taskId=$taskId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578693451', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }

    // RETURN an array of Task objects representing the tasks whose parentId is 0 (they have no parent).
    // INPUT $alphabetical: optional Boolean, default false. If true order alphabetically by description, otherwise use sortOrder.
    //  Everything related to $alphabetical added 2020-08-24 JM for v2020-4, to address http://bt.dev2.ssseng.com/view.php?id=229 
    // INPUT $active: if true, only get "active" children (not soft-deleted). Argument added 2020-11-10 for v2020-4. 
    public static function getZeroLevelTasks($alphabetical=false, $active=true) {
        global $db, $logger;
        Task::loadDB($db);
        
        $ret = array();
        $query = "SELECT taskId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".task ";
        $query .= "WHERE parentId = 0 ";
        if ($active) {
            $query .= "AND active = 1 ";
        }
        if ($alphabetical) {
            $query .= "ORDER BY description";
        } else {
            $query .= "ORDER BY sortOrder";
        }
        $query .= ";";
        
        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {					
                $ret[] = new Task($row['taskId']);					
            }
        } else {
            $logger->errorDb('1604959861', "Hard DB error", $db);
        }			
    
        return $ret;
    } // END public static function getZeroLevelTasks
    
    // Adds a new task. Used only from the admin side or perhaps in etc files.
    // INPUT $parentId - taskId of parent, may be zero; we assume caller has validated this.
    // INPUT $description - initial task deacription & billing description; we assume caller has validated length.
    // RETURN: array of two values: $taskId (null on failure) and $error (a string, empty on success)
    //   So a normal call to this looks like list($taskId, $error) = Task::addTask($parentId, $description);
    // >>>00032 Might want to verify admin if this is not running from command line.
    public static function addTask($parentId, $description) {
        global $db, $logger;
        Task::loadDB($db);

        $taskId = null;
        $error = '';
        
        // Sort the new task after all others with the same parent, at least initially
        $query = "SELECT MAX(sortOrder) AS maxsort FROM " . DB__NEW_DATABASE . ".task ";
        $query .= "WHERE parentId = " . intval($parentId) . ";";
    
        $maxsort = false; // will stay false if this is first child task for this parent
        $result = $db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $maxsort = $row['maxsort'];
            }
        } else {
            $logger->errorDb('1604440237', 'Hard DB error', $db);
            $error = "Hard DB error on '$query";
        }

        if (!$error) {
            // Sort the new task after all others with the same parent, at least initially
            $query = "SELECT MAX(sortOrder) AS maxsort FROM " . DB__NEW_DATABASE . ".task ";
            $query .= "WHERE parentId = " . intval($parentId) . ";";
            
            $maxsort = false; // will stay false if this is first child task for this parent
            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $maxsort = $row['maxsort'];
                }
            } else {
                $logger->errorDb('1604440237', 'Hard DB error', $db);
                $error = "Hard DB error on '$query";
            }
        }

        if (!$error) {    
            if (!$maxsort) {
                $maxsort = 0;
            } else {
                $maxsort = $maxsort + 1;
            }
            
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".task (parentId, sortOrder, description, billingDescription) VALUES (";
            $query .= intval($parentId);
            $query .= ", " . intval($maxsort);
            $query .= ", '" . $db->real_escape_string($description) . "'";
            $query .= ", '" . $db->real_escape_string($description) . "');";            
            
            $result = $db->query($query);
            if ($result) {
                $id = $db->insert_id;
                $taskId = intval($id);
            } else {
                $logger->errorDb('1604442175', 'Hard DB error', $db);
                $error = "Hard DB error on '$query";
            }
        }
        return Array ($taskId, $error);
    } // END public static function addTask
}


?>