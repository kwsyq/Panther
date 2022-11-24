<?php 
/* inc/classes/Descriptor2.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Descriptor2 table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* JM 2019-12: Following Martin's approach to the four classes this is replacing (see http://sssengwiki.com/New+element-descriptor+hierarchy), 
  this DOES NOT extend SSSEng.

* Public functions:
** __construct($id = null)
** setName($val)
** setDisplayOrder($val)
** setActive($val)
** getDescriptor2Id()
** getParentId()
** getName()
** getNote()
** getDisplayOrder()
** getChildren()              
** getActive()
** getDeactivated()
** getDeactivatedByPersonId()
** getDescriptorSubDetails()
** update($val)
** save()

*Public static functions:
** add($name, $parentId, $note='')
** validate($descriptor2Id, $unique_error_id=null)
** isActive($descriptor2Id)
** getDescriptors($objects=true, $parentId=0, $recursive=true)

*/

class Descriptor2 {
    // The following correspond exactly to the columns of DB table Descriptor2
    // (ignoring temporary columns used during the transition to descriptor2). 
    // See documentation of that table for further details.
	private $descriptor2Id;
	private $parentId;
	private $name;
	private $note;
	private $displayOrder;
	
	// Unlike the above, the "active" value is synthesized.
	private $active;
	private $wasInitiallyActive;
	
	private $children; // A hook for child descriptors

	private $db; // Needed because we do not extend SSSEng.
	private $logger;
	
    // INPUT $id: May be either of the following:
    //  * a descriptor2Id from the Descriptor2 table
    //  * an associative array which should contain an element for each columnn
    //    used in the Descriptor2 table, corresponding to the private variables
    //    just above.
	public function __construct($id = null) {
        global $logger;
        $this->db = DB::getInstance();
        $this->logger=$logger;
        $this->load($id);
        $this->children = array(); // a place to add an array of child Descriptor2 objects.
	}
	
	// INPUT $val here is input $id for constructor 
	private function load($val) {		
		if (is_numeric($val)) {			
		    // Read row from DB table Descriptor2
			$query = " SELECT descriptor2Id, parentId, name, note, displayOrder, deactivated, deactivatedByPersonId ";
			$query .= " FROM " . DB__NEW_DATABASE . ".descriptor2";
			$query .= " WHERE descriptor2Id = " . intval($val);
			
			$result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDb('1577329224', 'Hard DB error', $this->db);
            } else {
				if ($result->num_rows == 0) { // This is where we effectively validate the input $val				    
				    $this->logger->errorDb('1577329245', "Invalid descriptor2Id $val", $this->db);
				} else {
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$row = $result->fetch_assoc();

					$this->setDescriptor2Id($row['descriptor2Id']);
					$this->setParentId($row['parentId']);
					$this->setName($row['name']);
					$this->setNote($row['note']);
					$this->setDisplayOrder($row['displayOrder']);
					$this->setActive( ! $row['deactivated'] );
				}
			}
		} else if (is_array($val)) {
            //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.

		    // Set all of the private members that represent the DB content, from 
		    //  input associative array
			$this->setDescriptor2Id($val['descriptor2Id']);
			$this->setParentId($val['parentId']);
			$this->setName($val['name']);
			$this->setNote($val['note']);
			$this->setDisplayOrder($val['displayOrder']);
			$this->setActive( ! $val['deactivated'] );
		}
		$this->wasInitiallyActive = $this->getActive();
	} // END private function load
	
	// $val: primary key
	private function setDescriptor2Id($val) {
		$this->descriptor2Id = intval($val);
	}	
	
	// $val: foreign key back into the same table (or 0 if this descriptor2 is top-level) 
	// >>>00016, >>>00002: could validate here, log on error	
	private function setParentId($val) {
		$this->ParentId = intval($val);
	}	
	
	// $val: Arbitrary name of descriptor2. String.
	// This should be unique for a give parentId.
	// >>>00016, >>>00002: could validate here, log on error
	public function setName($val) {
		$val = trim($val);
		$val = substr($val, 0, 64); // >>>00002: truncates silently
		$val = trim($val); // in case substr left terminal whitespace
		$this->name = $val;
	}	
	
	// $val: Arbitrary note for descriptor2. String.
	// >>>00016, >>>00002: could validate here (just that it's a string), log on error
	public function setNote($val) {
		$val = trim($val);
		$val = substr($val, 0, 64); // >>>00002: truncates silently
		$val = trim($val); // in case substr left terminal whitespace
		$this->note = $val;
	}	
	
	// $val: display order within the current parentId 
	// (parentId, displayOrder) should be a candidate key.
	// >>>00016, >>>00002: could validate here, log on error
	public function setDisplayOrder($val) {
		$this->displayOrder = intval($val);
	}
	
	// $val: Boolean, true is active
	public function setActive($val) {
		$this->active = $val ? true : false;
	}	
	
	// Set children. $val should be an array of Descriptor2. 
	// Index need not correspond exactly to their respective displayOrder, but should go in 
	//  that same sequence. (E.g. if displayOrder has "holes", it's fine for the actual
	//  indexes to be consecutive, but displayOrder should increase monotonically with index.)
	private function setChildren($val) {
		$this->children = $val;
	}	
	
	// RETURN primary key
	public function getDescriptor2Id() {
		return $this->descriptor2Id;
	}
	
	// RETURN foreign key back into the same table (or 0 if this descriptor2 is top-level)
	private function getParentId() {
		return $this->descriptorId;
	}	
	
	// RETURN Arbitrary name of descriptor2. (parentId, name) is a candidate key.
	public function getName() {
		return $this->name;
	}	
	
	// RETURN Arbitrary name of descriptor2. (parentId, name) is a candidate key.
	public function getNote() {
		return $this->note;
	}	
	
	// RETURN display order within parent 
	// (parentId, displayOrder) should be a candidate key.
	public function getDisplayOrder() {
		return $this->displayOrder;
	}	
	
	// RETURN children (if they've been set)
	public function getChildren() {
		return $this->children;
	}
	
	// RETURN whether active
	public function getActive() {
		return $this->active;
	}

	// A slightly more expensive function to get the 'deactivated' timestamp.
	// RETURN timestamp of deactivation, or null on error.
	public function getDeactivated() {
	    $ret = null;
        $query = " SELECT deactivated";
        $query .= " FROM " . DB__NEW_DATABASE . ".descriptor2";
        $query .= " WHERE descriptor2Id = " . $this->getDescriptor2Id() . ";";
	
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1579039274', 'Descriptor2::getDeactivated: Hard DB error', $this->db);
        } else if ($this->db->num_rows == 0) {
            $this->logger->errorDb('1579039295', 'Descriptor2::getDeactivated: No row found', $this->db);
        } else {
            $row = $result->fetch_assoc();	
            $ret =$row['deactivated'];	
		}
		return $ret;
	}

	// A slightly more expensive function to get the 'deactivatedByPersonId'.
	// RETURN personId of person who deactivated, or false on error.
	// NOTE that null return is not an error. It is possible that 
	//  the row was manually deactivated an this value was not set. That's OK;
	//  this is strictly for reporting purposes.
	public function getDeactivatedByPersonId() {
	    $ret = false;
        $query = " SELECT deactivatedByPersonId";
        $query .= " FROM " . DB__NEW_DATABASE . ".descriptor2";
        $query .= " WHERE descriptor2Id = " . $this->getDescriptor2Id() . ";";
	
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1579039374', 'Descriptor2::getDeactivatedByPersonId: Hard DB error', $this->db);
        } else if ($this->db->num_rows == 0) {
            $this->logger->errorDb('1579039395', 'Descriptor2::getDeactivatedByPersonId: No row found', $this->db);
        } else {
            $row = $result->fetch_assoc();	
            $ret =$row['deactivatedByPersonId'];	
		}
		return $ret;
	}
	
	// RETURNs array of associative arrays corresponding to all rows in 
	//  DB table DescriptorSubDetail with this descriptor2Id, in no particular order.
	//  Each associative array is the canonical representation of the content
	//  of the row in question.
	public function getDescriptorSubDetails() {
		$details = array();
	
		$query = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".descriptorSubDetail ";
		$query .= " where descriptor2Id = " . intval($this->getDescriptor2Id()) . " ";
	
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1577329750', 'Hard DB error', $this->db);
        } else {
            while ($row = $result->fetch_assoc()) {	
                $details[] = $row;	
            }	
		}	
	
		return $details;	
	} // END public function getDescriptorSubDetails	
	
	// Update several values for this descriptor2
	// INPUT $val typically comes from $_REQUEST.
	//  An associative array containing the following elements
	//   * 'parentId' - effectively validated 
	//   * 'name'
	//   * 'note' 
	//   * 'displayOrder' 
	//   Any or all of these may be present. 
	public function update($val) {		
		if (is_array($val)) {		
			if (isset($val['parentId'])) {
				if (trim($val['parentId']) != '') {						
					$parentId = intval($val['parentId']);
						
					// validate that it is a defined descriptorId
					$query  = " SELECT descriptor2Id ";
					$query .= " from " . DB__NEW_DATABASE . ".descriptor ";
					$query .= " where descriptorId = " . intval($parentId);
					
					if (!$result) {
                        $this->logger->errorDb('1577329819', 'Hard DB error', $this->db);
                    } else if ($result->num_rows > 0){
                        // Yes, there was a match, that's all we need to know.
                        $this->setParentId($parentId);
					} else {
					    $this->logger->errorDb('1577329836', "Determined that we were trying to set invalid parentId $parentId " . 
					                                   "for descriptor2 {$this->getDescriptor2Id()}", $this->db);
					}
                }						
            }					
						
			if (isset($val['name'])) {
			    // >>>00016: Maybe more validation? (parentId, name) is supposed to be a candidate key.
				$name = $val['name'];
				$this->setName($name);
			}
			
            if (isset($val['note'])) {
			    // >>>00016: should at least validate that this is a string
				$note = $val['note'];
				$this->setNote($note);
			}
			
			if (isset($val['displayOrder'])) {
				//  >>>00016: Maybe more validation? (parentId, displayOrder) should be a candidate key.
				$displayOrder = $val['displayOrder'];
				$this->setDisplayOrder($displayOrder);				
			}
			
			$this->save();			
		}		
	} // END public function update	
	
	// UPDATEs same fields handled by public function update.
	// Some of these also might have been set by public "set" methods.
	public function save() {
		global $user;
		$query = " UPDATE " . DB__NEW_DATABASE . ".descriptor2 SET ";
		$query .= " name = '" . $this->db->real_escape_string($this->getName()) . "'";
		$query .= ", note = '" . $this->db->real_escape_string($this->getNote()) . "'";
		$query .= ", displayOrder = " . intval($this->getDisplayOrder());
		if ($this->active && !$this->wasInitiallyActive) {
		    $query .= ", deactivated = NULL";
		    $query .= ", deactivatedByPersonId = NULL";
		}		
		if ($this->wasInitiallyActive && !$this->active) {
		    $query .= ", deactivated = CURRENT_TIMESTAMP";
		    $query .= ", deactivatedByPersonId = " . $user->getUserId();
		}		
		$query .= " where descriptor2Id = " . intval($this->getDescriptor2Id()) . " ";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1577329861', 'Hard DB error', $this->db);
        }
	}
	
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance(); 
	    }
	}

    // Insert a new row in DB table descriptor2, with a displayOrder that is 1 greater than 
    // the previous maximum displayorder for that parent. 
    // INPUTs correspond exactly to columns of DB table Descriptor2.
    // INPUT $name - name of new descriptor2Id
    // INPUT $parentId - foreign key back into same descriptor2 table, or 0 for top-level
    // INPUT $note - optional note
    // RETURN true on success, false on failure
    public static function add($name, $parentId, $note='') {
        global $db, $logger;
        Descriptor2::loadDB($db);
        
        $ok = true;
        $name = trim($name);
		$name = substr($name, 0, 64); // >>>00002: truncates silently, should log
		$name = trim($name); // in case substr left terminal whitespace
		
		if (strlen($name) == 0) {
            $logger->error2('1577131004', 'Descriptor2::Add called with empty $name');
            $ok = false;
        }
        if ($ok) {
            if ($parentId) {
                if ( ! Descriptor2::validate($parentId, '1577130985') ) {
                    $logger->errorDb('1577130987', "Determined that we were trying to set invalid parentId=$parentId " . 
                                               "for a new descriptor2", $db);
                    $ok = false;
                }
            } // else $parentId==0 is just fine.
        }
            
        if ($ok) {
            $query = "SELECT descriptor2Id FROM " . DB__NEW_DATABASE . ".descriptor2 WHERE descriptor2Id=$parentId AND name='". $db->real_escape_string($name) ."';";
            $result = $db->query($query);
            
            if (!$result)  {
                $logger->errorDb('1577130990', "Hard error", $db);
                $ok = false;
            } else if ($result->num_rows > 0){
                $logger->errorDb('1577130991', "Determined that we were trying to insert a second row in descriptor2 with parentId=$parentId " . 
                                                   "and name='$name'", $db); 
                $ok = false;
            }
        }
		
        if ($ok) {
            $query =  "INSERT INTO " . DB__NEW_DATABASE . ".descriptor2 (parentId, name, displayOrder\n" .
                  ") VALUES (\n" .
                  "$parentId,\n" .
                  "'" . $db->real_escape_string($name) . "',\n" .
                  "    (SELECT COALESCE(MAX(d2.displayorder) + 1, 1) \n" .
                  "    FROM " . DB__NEW_DATABASE . ".descriptor2 as d2 \n" .
                  "    WHERE d2.parentId = " . intval($parentId) . ")\n" .
                  ");\n";
            $result = $db->query($query);
                
            if (!$result)  {
                $logger->errorDb('1577130999', "Hard error", $db);
                $ok = false;
            }
        }
        return $ok;
    } // END public static function add
    
    // Return true if the id is a valid descriptor2Id, false if not
    // INPUT $descriptor2Id: descriptor2Id to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($descriptor2Id, $unique_error_id=null) {
        global $db, $logger;
        Descriptor2::loadDB($db);
        
        $ret = false;
        $query = "SELECT descriptor2Id FROM " . DB__NEW_DATABASE . ".descriptor2 WHERE descriptor2Id=$descriptor2Id;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1577377404', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    // Return true if the id is a valid AND ACTIVE descriptor2Id, false if not
    // INPUT $descriptor2Id: descriptor2Id to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function isActive($descriptor2Id) {
        global $db, $logger;
        Descriptor2::loadDB($db);
        
        $ret = false;
        $query = "SELECT descriptor2Id FROM " . DB__NEW_DATABASE . ".descriptor2 " .
                 "WHERE descriptor2Id=$descriptor2Id " .
                 "AND deactivated IS NULL;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb('1579038081', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    // INPUT $objects: if true, return is in object form rather than array form. 
    // INPUT $parentId: 0 for top level
    // INPUT $recursive: Boolean, if true then don't get just immediate children, get the whole "tree" from there on down.
    // INPUT $depth: should only be used on recursive calls; outside callers should always pass
    //   this in as zero. Public method does not expose this parameter.
    // NOTE does not validate inputs
    // NOTE does not directly check for cycles, but will break on excessive recursion
    // RETURN false on error
    // On success, return an array as follows; array elements will be replaced by Descriptor2 objects if $objects == true.
    //  * Each element of the array is an associative array representing a row from DB table descriptor2. Indexes include:
    //    * Drawn directly from table:
    //      * 'descriptor2Id' - primary key
    //      * 'parentId' - descriptor2Id of parent; 0 for top level
    //      * 'name' - for display
    //      * 'note'
    //      * 'displayOrder' - within context of parentId
    //      * 'deactivated'
    //      * 'deactivatedByPersonId'
    //    * (Calculated)
    //      * 'active' (Booolean, true if and only if 'deactivated' is NULL) 
    //    * If $recursive, then (calculated):
    //      * 'children' - an array exactly like the top-level array returned, describing all rows that have this row as a parent.
    //        If this is a "leaf node" then 'children' will be an empty array.
    //    * As of 2019-12, there are other column indexes -- elementTypeId, descriptorCategoryId, descriptorId, descriptorSubId -- 
    //      but these deprecated columns relate to tables that will be going away after the spring 2020 release, and we are not returning them.
    private static function getDescriptorsLow($objects=true, $parentId=0, $recursive=true, $depth=0) {
        global $db, $logger;
        Descriptor2::loadDB($db);
        
        if ($depth > 15) {
            $logger->errorDb('1576864485', "Excessive recursion, almost certainly a loop in the hierarchy of descriptors", $db);
            return false;
        }
        $ret = array();
        $query = "SELECT descriptor2Id, parentId, name, note, displayOrder, deactivated, deactivatedByPersonId " ;
        $query .= "FROM " . DB__NEW_DATABASE . ".descriptor2 ";
        $query .= "WHERE parentId=$parentId ";
        $query .= "ORDER BY displayOrder ASC;";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1576864565', "Failed to get rows with parent $parentId", $db);
            return false;
        }
        while ($row = $result->fetch_assoc()) {
            if ($objects) {
                $obj = new Descriptor2($row);
            } else {
                $row['active'] = $row['deactivated'] === null;
            }
            if ($recursive) {
                $children = Descriptor2::getDescriptorsLow($objects, $row['descriptor2Id'], true, $depth+1);
                if ($children === false) {
                    return false;  // failure in recursive call. Error should already be logged.
                }
                // NOTE that it's fine if $children is an empty array, that just means this is a "leaf".
                if ($objects) {
                    $obj->setChildren($children);
                } else {
                    $row['children'] = $children; 
                }
            }               
            if ($objects) {
                $ret[] = $obj;
            } else {
                $ret[] = $row;
            }
        }
        return $ret;
    } // END private static function getDescriptorsLow    
    
    // For INPUTs and return, see private static function getDescriptorsLow above.
    // In addition, $parentId is validated here.
    // NOTE that this does NOT take $level as an input.
    public static function getDescriptors($objects=true, $parentId=0, $recursive=true) {
        global $logger, $db;
        Descriptor2::loadDB($db);
        
        $parentId=intval($parentId);
        if ($parentId != 0) { 
            if ( ! Descriptor2::validate($parentId, '1577378955') ) {
                $logger->errorDb('1577378957', "Determined that we were trying to get descriptors under invalid parentId=$parentId", $db);
                // fall through, let it return empty array returned by Descriptor2::getDescriptorsLow. 
            }
        } // else 0 is a valid parentId, means this is top-level
        return Descriptor2::getDescriptorsLow($objects, $parentId, $recursive);
    }
}
?>