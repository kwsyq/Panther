<?php 
/* inc/classes/ServiceLoadVar.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the ServiceLoad table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.
Somewhat surprisingly, DOESN'T inherit from class SSSEng.

(FWIW, "serviceLoad" is probably a misnomer here, too specific.)
   
* Public functions:
** __construct($id = null)
** setLoadVarName($val)
** setLoadVarType($val)
** setLoadVarData($val)
** setWikiLink($val)
** getServiceLoadVarId()
** getServiceLoadId()
** getLoadVarName()
** getLoadVarType()
** getLoadVarData()
** getWikiLink()	
** update($val)
** save()

** public static function validate($serviceLoadId, $unique_error_id=null)

*/

class ServiceLoadVar {
    // The following correspond exactly to the columns of DB table ServiceLoadVar
    // See documentation of that table for further details.
	private $serviceLoadVarId;
	private $serviceLoadId;
	private $loadVarName;
	private $loadVarType;
	private $loadVarData;
	private $wikiLink;
	
	// Because this doesn't inherit from class SSSEng, it needs this handle to the database.
	private $db;	
	
    // INPUT $id: May be either of the following:
    //  * a serviceLoadVarId from the ServiceLoadVar table
    //  * an associative array which should contain an element for each columnn
    //    used in the ServiceLoadVar table, corresponding to the private variables
    //    just above. 
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
	public function __construct($id = null) {
		$this->db = DB::getInstance();
		$this->load($id);		
	}

	// INPUT $val here is input $id for constructor.
	private function load($val) {
		if (is_numeric($val)) {			
	        // Read row from DB table ServiceLoad
			$query = " select * ";
			$query .= " from " . DB__NEW_DATABASE . ".serviceLoadVar ";
			$query .= " where serviceLoadVarId = " . intval($val);

			if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0) {					
					$row = $result->fetch_assoc();
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$this->setServiceLoadVarId($row['serviceLoadVarId']);
					$this->setServiceLoadId($row['serviceLoadId']);
					$this->setLoadVarName($row['loadVarName']);
					$this->setLoadVarType($row['loadVarType']);
					$this->setLoadVarData($row['loadVarData']);
					$this->setWikiLink($row['wikiLink']);					
				} // >>>00002 else ignores that we got a bad serviceLoaVarId!
			} // >>>00002 else ignores failure on DB query! Does this throughout file, 
			  // haven't noted each instance.
		} else if (is_array($val)) {
		    // Set all of the private members that represent the DB content, from 
		    //  input associative array
			$this->setServiceLoadVarId($val['serviceLoadVarId']);
			$this->setServiceLoadId($val['serviceLoadId']);
			$this->setLoadVarName($val['loadVarName']);
			$this->setLoadVarType($val['loadVarType']);
			$this->setLoadVarData($val['loadVarData']);
			$this->setWikiLink($val['wikiLink']);
		}
	} // END private function load	
	
	// Set primary key
	// INPUT $val: primary key (serviceLoadVarId)
	private function setServiceLoadVarId($val) {
		$this->serviceLoadVarId = intval($val);
	}
	
	// Indicate what ServiceLoad this is a variable for
	// INPUT $val: foreign key into DB table ServiceLoad
	private function setServiceLoadId($val) {
		$this->serviceLoadId = intval($val);
	}
		
	// INPUT $val: serviceLoadVar name
	public function setLoadVarName($val) {
		$val = trim($val);
		$val = substr($val, 0, 32); // >>>00002: truncates silently.
		$this->loadVarName = $val;
	}
	
	// INPUT $val: serviceLoadVar type
	// >>>00016, >>>00002 certainly should be validated against a list of types.
	//  As of 2019-02 hard code supports this, no referent in the DB, and 
	//    inc/config.php may not have it right:
	//   0 => open-ended text
	//   1 => select from loadVarData
	//   2 => numeric.  
	public function setLoadVarType($val) {
		$this->loadVarType = intval($val);
	}
	
	// INPUT $val - string, only relevant if LoadVarType==1. A pipe-separated set 
	//  of possible data values for this ServiceLoadVar. E.g. "B|C|D", 
	//  "0|0.18|0.55","Enclosed Building|Partially Enclosed Building|Open Building". 
	//  No effective difference between null & blank here.
	public function setLoadVarData($val) {
		$val = trim($val);
		$val = substr($val, 0, 1024); // >>>00002: truncates silently.
		$this->loadVarData = $val;
	}
	
	// INPUT $val - allows indication of a relevant wiki page, used in
	//  conjunction with global WIKI_URL. Can be blank or null if no such.
	public function setWikiLink($val) {
		$val = trim($val);
		$val = substr($val, 0, 256); // >>>00002: truncates silently.
		$this->wikiLink = $val;
	}	
	
	// RETURN primary key.
	public function getServiceLoadVarId() {
		return $this->serviceLoadVarId;
	}

	// RETURN foreign key into DB table ServiceLoad, what ServiceLoad this is a variable for.
	public function getServiceLoadId() {
		return $this->serviceLoadId;
	}
	
	// RETURN ServiceLoadVar name
	public function getLoadVarName() {
		return $this->loadVarName;
	}
	
	// RETURN ServiceLoadVar type (as described above in setLoadVarType method)
	public function getLoadVarType() {
		return $this->loadVarType;
	}
	
	// RETURN ServiceLoadVar data (as described above in setLoadVarData method)
	public function getLoadVarData() {
		return $this->loadVarData;
	}
	
	// RETURN name of relevant wiki page, used in
	//  conjunction with global WIKI_URL. Can be blank or null if no such.
	public function getWikiLink() {
		return $this->wikiLink;
	}	
	
	// Update several values for this person
	// INPUT $val typically comes from $_REQUEST. 
	//  An associative array containing the following elements:
	//   * 'serviceLoadId' - this is validated for referential integrity 
	//   * 'loadVarName'
	//   * 'loadVarType' - currently validated as an integer, >>>00016, should be better validated
	//   * 'loadVarData'
	//   * 'wikiLink'
	//   Any or all of these may be present. >>>00016: Maybe more validation?
	public function update($val) {		
		if (is_array($val)) {		
			if (isset($val['serviceLoadId'])) {			
				if (trim($val['serviceLoadId']) != '') {						
                    // >>>00007 isset test in following line is redundant to test already made, as are analogous ones 
                    //  for other elements of the associative array.
					$serviceLoadId = isset($val['serviceLoadId']) ? intval($val['serviceLoadId']) : null;
						
					$query  = " select * ";
					$query .= " from " . DB__NEW_DATABASE . ".serviceLoad ";
					$query .= " where serviceLoadId = " . intval($serviceLoadId);
			
					$serviceLoadId = null;
						
					if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
						if ($result->num_rows > 0){
							$row = $result->fetch_assoc();
							$serviceLoadId = $row['serviceLoadId'];
						}
					}
						
					if ($serviceLoadId){
						$this->setServiceLoadId($serviceLoadId);
					} // >>>00002 else it is silent about invalid serviceLoadId, just ignores it. 						
				}					
			}
						
			if (isset($val['loadVarName'])) {			
				$loadVarName = isset($val['loadVarName']) ? $val['loadVarName'] : '';
				$this->setLoadVarName($loadVarName);			
			}
			
			if (isset($val['loadVarType'])) {					
				$loadVarType = isset($val['loadVarType']) ? intval($val['loadVarType']) : 0;
				if (intval($loadVarType)){
					$this->setLoadVarType($loadVarType);
				} // >>>00002 else it is silent about invalid loadVarType, just ignores it.					
			}
			
			if (isset($val['loadVarData'])) {					
				$loadVarData = isset($val['loadVarData']) ? $val['loadVarData'] : '';
				$this->setLoadVarData($loadVarData);					
			}
			
			if (isset($val['wikiLink'])) {					
				$wikiLink = isset($val['wikiLink']) ? $val['wikiLink'] : '';
				$this->setWikiLink($wikiLink);					
			}
			
			$this->save();			
		}		
	} // END public function update
	
	// UPDATEs same fields handled by public function update.
	public function save() {		
		$query = " update " . DB__NEW_DATABASE . ".serviceLoadVar  set ";
		$query .= " serviceLoadId = " . intval($this->getServiceLoadId()) . " ";
		$query .= " ,loadVarName = '" . $this->db->real_escape_string($this->getLoadVarName()) . "' ";
		$query .= " ,loadVarType = " . intval($this->getLoadVarType()) . " ";
		$query .= " ,loadVarData = '" . $this->db->real_escape_string($this->getLoadVarData()) . "' ";
		$query .= " ,wikiLink = '" . $this->db->real_escape_string($this->getWikiLink()) . "' ";		
		$query .= " where serviceLoadVarId = " . intval($this->getServiceLoadVarId()) . " ";
		
		$this->db->query($query);		
	}	
	
	/* BEGIN REMOVED 2020-01-06 JM
	// RETURNs an array of Descriptor objects (ordered by descriptor.displayorder) corresponding to this serviceLoadVarId.
	// or at least that's the apparent intent. But there is no method getDescriptorCategoryId, so the intent here is rather
	//  unclear, especially because there isn't an obvious relationship between tables ServiceLoadVar & Descriptor 
	public function getDescriptors() {	
		$ret = array();	
		$query  = " select * from " . DB__NEW_DATABASE . ".descriptor " .
		          " where descriptorCategoryId = " . intval($this->getDescriptorCategoryId()) . 
		          " order by displayorder ";
	
		if ($result = $this->db->query($query)) {
			if ($result->num_rows > 0){
				while ($row = $result->fetch_assoc()){
					$ret[] = new Descriptor($row['descriptorId']);
				}
			}
		}
	
		return $ret;
		
	}
	// END REMOVED 2020-01-06 JM
	*/
	
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}
	
    // Return true if the id is a valid serviceLoadVarId, false if not
    // INPUT $serviceLoadVarId: serviceLoadVarId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($serviceLoadVarId, $unique_error_id=null) {
        global $db, $logger;
        ServiceLoadVar::loadDB($db);
        
        $ret = false;
        $query = "SELECT serviceLoadVarId FROM " . DB__NEW_DATABASE . ".serviceLoadVar WHERE serviceLoadVarId=$serviceLoadVarId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578693357', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>