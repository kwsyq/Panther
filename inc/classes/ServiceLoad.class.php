<?php
/* inc/classes/ServiceLoad.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the ServiceLoad table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.
Somewhat surprisingly, DOESN'T inherit from class SSSEng.

(FWIW, serviceLoad is probably a misnomer here, too specific.)
   
* Public functions:
** __construct($id = null)
** setLoadName($val)
** getServiceLoadId()
** getLoadName()
** update($val)
** save()
** getServiceLoadVars()

** public static function validate($serviceLoadId, $unique_error_id=null)

*/

class ServiceLoad {
    // The following correspond exactly to the columns of DB table ServiceLoad
    // See documentation of that table for further details.    
	private $serviceLoadId;
	private $loadName;
	
	// Because this doesn't inherit from class SSSEng, it needs this handle to the database.
	private $db; 
	
    // INPUT $id: May be either of the following:
    //  * a serviceLoadId from the ServiceLoad table
    //  * an associative array which should contain an element for each columnn
    //    used in the ServiceLoad table, corresponding to the private variables
    //    just above. 
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
	public function __construct($id = null){	
		$this->db = DB::getInstance();
		$this->load($id);		
	}

	// INPUT $val here is input $id for constructor.
	private function load($val) {		
		if (is_numeric($val)) {			
	        // Read row from DB table ServiceLoad
			$query = " select * ";
			$query .= " from " . DB__NEW_DATABASE . ".serviceLoad";
			$query .= " where serviceLoadId = " . intval($val);

			if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {					
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$row = $result->fetch_assoc();					
					$this->setServiceLoadId($row['serviceLoadId']);
					$this->setLoadName($row['loadName']);					
				} // >>>00002 else ignores that we got a bad serviceLoadId!
			} // >>>00002 else ignores failure on DB query! Does this throughout file, 
			  // haven't noted each instance.
		} else if (is_array($val)) {
		    // Set all of the private members that represent the DB content, from 
		    //  input associative array
			$this->setServiceLoadId($val['serviceLoadId']);
			$this->setLoadName($val['loadName']);			
		}
	}
	
	// Set primary key
	// INPUT $val: primary key (serviceLoadId)
	private function setServiceLoadId($val) {
		$this->serviceLoadId = intval($val);
	}
	
	// INPUT $val serviceLoad name
	public function setLoadName($val) {
		$val = trim($val);
		$val = substr($val, 0, 32); // >>>00002: truncates silently.
		$this->loadName = $val;
	}	
	
	// RETURN primary key (serviceLoadId)
	public function getServiceLoadId() {
		return $this->serviceLoadId;
	}

	// RETURN serviceLoad name
	public function getLoadName() {
		return $this->loadName;
	}
	
	// Update what's really the only value we carry in the table itself for a serviceLoad
	// INPUT $val typically comes from $_REQUEST. 
	//  An associative array containing the following element:
	//   * 'loadName' 
	//   If 'loadName' absent but $val is an array, still calls save(), which can update prior "sets".  
	public function update($val) {
		if (is_array($val)) {
			if (isset($val['loadName'])) {
			    // >>>00007 isset test in following line is redundant to test already made.
				$loadName = isset($val['loadName']) ? $val['loadName'] : '';
				
                // JM 2019-12-02: I'm a little afraid to mess with this, but I'm going to. I suspect
                //  that in fact this has never been called. There is no method setTypeName, nor has the code set $typeName,
                //  so the way Martin had written this would certainly fail if called. I believe he probably 
                //  meant to write $this->setLoadName($loadName), and I'm re-coding accordingly.
				/* OLD CODE REMOVED 2019-12-02 JM
				$this->setTypeName($typeName);		
                */
                // BEGIN REPLACEMENT CODE 2019-12-02 JM
                $this->setLoadName($loadName);
                // END REPLACEMENT CODE 2019-12-02 JM
			}		
			
			$this->save();			
		}		
	}
	
	// UPDATEs same fields handled by public function update.
	public function save() {		
		$query = " update " . DB__NEW_DATABASE . ".serviceLoad  set ";
		
		// JM 2019-12-02: I'm a little afraid to mess with this, but I'm going to. I suspect
		//  that in fact this has never been called. There is no method getTypeName, so the
		//  way Martin had written this would certainly fail if called. I believe he probably 
		//  meant to write getLoadName, and I'm re-coding accordingly.
		/* OLD CODE REMOVED 2019-12-02 JM
		$query .= " loadName = " . intval($this->getTypeName()) . " ";
        */
        // BEGIN REPLACEMENT CODE 2019-12-02 JM
        $query .= " loadName = " . intval($this->getLoadName()) . " ";
        // END REPLACEMENT CODE 2019-12-02 JM

		$query .= " where serviceLoadId = " . intval($this->getServiceLoadId()) . " ";

		$this->db->query($query);		
	}	

	// RETURN an array of ServiceLoadVar objects for this serviceLoadId. No particular order.
	public function getServiceLoadVars() {		
		$ret = array();		
		$query  = " select * from " . DB__NEW_DATABASE . ".serviceLoadVar where serviceLoadId = " . intval($this->getServiceLoadId()) . " ";
		//$query .= " order by displayorder "; // Commented out by Martin before 2019

		if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0){
				while ($row = $result->fetch_assoc()){
					$ret[] = new ServiceLoadVar($row['serviceLoadVarId']);
				}
			}
		}
		
		return $ret;		
	}
	
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}
	
    // Return true if the id is a valid serviceLoadId, false if not
    // INPUT $serviceLoadId: serviceLoadId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($serviceLoadId, $unique_error_id=null) {
        global $db, $logger;
        ServiceLoad::loadDB($db);
        
        $ret = false;
        $query = "SELECT serviceLoadId FROM " . DB__NEW_DATABASE . ".serviceLoad WHERE serviceLoadId=$serviceLoadId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578693201', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>