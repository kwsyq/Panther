<?php 
/* inc/classes/DescriptorPackage.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the DescriptorPackage table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* This DOES NOT extend SSSEng.

* Public functions:
** __construct($id = null)
** setPackageName($val)
** getDescriptorPackageId()
** getPackageName()
** deleteDescriptorSubFromPackage($descriptorPackageSubId)
** addDescriptorSubToPackage($descriptor2Id, $note='', $modifier='')
** update($val)
** save()

* Public static functions:
** getAll()
** add($packageName)
** delete($descriptorPackageId)
** validate($descriptorPackageId, $unique_error_id=null)

*/

class DescriptorPackage {
    // The following correspond exactly to the columns of DB table DescriptorPackage. 
    // See documentation of that table for further details.
	private $descriptorPackageId;
	private $packageName;

	private $db; // Needed because we do not extend SSSEng.
	private $logger;
	
    // INPUT $id: May be either of the following:
    //  * a descriptorPackageId from the DescriptorPackage table
    //  * an associative array which should contain an element for each columnn
    //    used in the DescriptorPackage table, corresponding to the private variables
    //    just above.
	public function __construct($id = null) {
        global $logger;
        $this->db = DB::getInstance();
        $this->logger=$logger;
        $this->load($id);
	}
	
	// INPUT $val here is input $id for constructor 
	private function load($val) {		
		if (is_numeric($val)) {			
		    // Read row from DB table DescriptorPackage
			$query = " SELECT descriptorPackageId, packageName ";
			$query .= " FROM " . DB__NEW_DATABASE . ".descriptorPackage";
			$query .= " WHERE descriptorPackageId = " . intval($val);
			
			$result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDb('1577388314', 'Hard DB error', $this->db);
            } else {
				if ($result->num_rows == 0) { // This is where we effectively validate the input $val				    
				    $this->logger->errorDb('1577388345', "Invalid descriptorPackageId $val", $this->db);
				} else {
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$row = $result->fetch_assoc();

					$this->setDescriptorPackageId($row['descriptorPackageId']);
					$this->setPackageName($row['packageName']);					
				}
			}
		} else if (is_array($val)) {
            //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.

		    // Set all of the private members that represent the DB content, from 
		    //  input associative array
			$this->setDescriptorPackageId($val['descriptorPackageId']);
			$this->setPackageName($val['packageName']);			
		}
	} // END private function load
	
	// $val: primary key
	private function setDescriptorPackageId($val) {
		$this->descriptorPackageId = intval($val);
	}	
	
	// $val: Arbitrary name of descriptorPackage. String.
	// This should be unique to the one package.
	// >>>00016, >>>00002: could validate here, log on error
	public function setPackageName($val) {
		$val = trim($val);
		$val = substr($val, 0, 128); // >>>00002: truncates silently
		$val = trim($val); // in case substr left terminal whitespace
		$this->packageName = $val;
	}	
	
	// RETURN primary key
	public function getDescriptorPackageId() {
		return $this->descriptorPackageId;
	}
	
	// RETURN Arbitrary name of descriptor2. (parentId, name) is a candidate key.
	public function getPackageName() {
		return $this->packageName;
	}
	
	// INPUT $descriptorPackageSubId: primary key into descriptorPackageSub
	// NOTE this will only work if the $descriptorPackageSubId is associated with this sub.
	// RETURN true on success, false on error. 
	public function deleteDescriptorSubFromPackage($descriptorPackageSubId) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".descriptorPackageSub ";
        $query .= "WHERE descriptorPackageSubId=" . intval($descriptorPackageSubId) . " ";
        $query .= "AND descriptorPackageId=" . $this->getDescriptorPackageId() . ";";
        
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1577390109', "Hard DB error deleting from descriptorPackage", $this->db);
            return false;
        }
        if ($this->db->affected_rows == 0) {
            $this->logger->errorDb('1577390148', "No such descriptorPackageSub row to delete", $this->db);
            return false;
        }
        return true;
	} // END public function deleteDescriptorSubFromPackage

	// Associate a descriptor2 with this package, possibly with $note and $modifier 
	// RETURN true on success, false on error. 
	public function addDescriptorSubToPackage($descriptor2Id, $note='', $modifier='') {
	    if ( ! Descriptor2::validate($descriptor2Id) ) {
	        $this->logger->error2('1577391060', "Invalid descriptor2Id $descriptor2Id");
	        return false;
	    }
	    
        $modifier = trim($modifier);	
        $modifier = substr($modifier, 0, 32); // >>>00002 truncates silently
        $modifier = trim($modifier);
    
        $note = trim($note);
        $note = substr($note, 0, 64); // >>>00002 truncates silently
        $modifier = trim($modifier);
	    
        $query = " INSERT INTO " . DB__NEW_DATABASE . ".descriptorPackageSub(descriptorPackageId, descriptor2Id, note, modifier) VALUES (";
        $query .= $this->getDescriptorPackageId();
        $query .= ", " . intval($descriptor2Id);
        $query .= ", '" . $this->db->real_escape_string($note) . "'";
        $query .= ", '" . $this->db->real_escape_string($modifier) . "');";
        
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1577232688', "Hard DB error inserting into descriptorPackage", $db);
            return false;
        }
        return true;
	} // END public function addDescriptorSubToPackage
	
	// Update several values for this descriptor2
	// INPUT $val typically comes from $_REQUEST.
	//  An associative array containing the following elements
	//   * 'packageName'
	public function update($val) {		
        if (isset($val['packageName'])) {
            // >>>00016: Maybe some validation?
            $packageName = $val['packageName'];
            $this->setPackageName($packageName);
        }
        
        $this->save();			
	} // END public function update	
	
	// UPDATEs same fields handled by public function update.
	// Some of these also might have been set by public "set" methods.
	public function save() {		
		$query = " UPDATE " . DB__NEW_DATABASE . ".descriptorPackage SET ";
		$query .= " descriptorPackageId = " . intval($this->getDescriptorPackageId()  );
		$query .= ", packageName = '" . $this->db->real_escape_string($this->getPackageName()) . '"';
		$query .= " WHERE descriptorPackageId = " . intval($this->getDescriptorPackageId()) . " ";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1577388771', 'Hard DB error', $this->db);
        }
	}

	// RETURN an array containing objects representing all known DescriptorPackages in alphabetical order
	//  (or return false on error)
	public static function getAll() {
	    global $db, $logger;
	    
        $packages = array();
        $query = "SELECT descriptorPackageId, packageName FROM  " . DB__NEW_DATABASE . ".descriptorPackage ORDER BY packageName ";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1577395911', 'Hard DB error', $db);
            return false;
        }
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = new DescriptorPackage($row);
        }
        return $packages;
	}
	
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}

    // Insert a new row in DB table descriptorPackage. 
    // INPUTs correspond exactly to columns of DB table Descriptor2.
    // INPUT $packageName - name of new descriptorPackage
    // RETURN true on success, false on failure
    public static function add($packageName) {
        global $db, $logger;
        DescriptorPackage::loadDB($db);
        
        $ok = true;
        $packageName = trim($packageName);
		$name = substr($packageName, 0, 128); // >>>00002: truncates silently, should log
		$packageName = trim($packageName); // in case substr left terminal whitespace
		
		if (strlen($name) == 0) {
            $logger->error2('1577388921', 'DescriptorPackage::Add called with empty $packageName');
            $ok = false;
        }
            
        if ($ok) {
            $query = "SELECT descriptorPackageId FROM descriptorPackage WHERE packageName='". $db->real_escape_string($packageName) ."';";
            $result = $db->query($query);
            
            if (!$result)  {
                $logger->errorDb('1577388955', "Hard error", $db);
                $ok = false;
            } else if ($result->num_rows > 0){
                $logger->errorDb('1577130991', "Determined that we were trying to insert a second row in descriptor2 with packageName='$packageName'", $db); 
                $ok = false;
            }
        }
		
        if ($ok) {
            $query =  "INSERT INTO " . DB__NEW_DATABASE . ".descriptorPackage (packageName)\n" .
                  " VALUES \n" .
                  "('" . $db->real_escape_string($packageName) . "');\n";
            $result = $db->query($query);
                
            if (!$result)  {
                $logger->errorDb('1577131001', "Hard error", $db);
                $ok = false;
            }
        }
        return $ok;
    } // END public static function add
    
    // Delete the row in descriptorPackage identified by INPUT $descriptorPackageId.
    //  Harmless if there is no such row, and will clean up any bad references in descriptorPackageSub
    //  to the nonexistent row if such exist.
    // Order of the two queries here is important: on any partial failure, this should not break referential integrity.
    // RETURN true on success, false on failure
    public static function delete($descriptorPackageId) {  
        global $db, $logger;
        DescriptorPackage::loadDB($db);
        
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".descriptorPackageSub ";
        $query .= "WHERE descriptorPackageId = " . intval($descriptorPackageId) . ";";
        
        $result = $db->query($query);
        if (!$result)  {
            $logger->errorDb('1577394505', "Hard error", $db);
            return false;
        }        
        
        // Side effect: Delete all relevant rows from descriptorPackageSub
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".descriptorPackage ";
        $query .= "WHERE descriptorPackageId = " . intval($descriptorPackageId) . ";";
        
        $result = $db->query($query);
        if (!$result)  {
            $logger->errorDb('1577394515', "Hard error", $db);
            return false;
        }
        return true;
    } // END public static function delete
    
    // Return true if the id is a valid descriptorPackageId, false if not
    // INPUT $descriptorPackageId: id to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($descriptorPackageId, $unique_error_id=null) {
        global $db, $logger;
        DescriptorPackage::loadDB($db);
        
        $ret = false;
        $query = "SELECT descriptorPackageId FROM descriptorPackage WHERE descriptorPackageId=$descriptorPackageId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1577389199', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>