<?php 
/* Contract.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Contract table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well, 
especially for billing profiles.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular company.
* Public functions:
** __construct($id = null, User $user = null)
** setNameOverride($val)
** setContractDate($val)
** setContractLanguageId($val)
** setCommitNotes($val)
** setCommitPersonId($val)
** setClientMultiplier($val)
** setAddressOverride($val)
** setEditCount($val)
** getContractId()
** getWorkOrderId()
** getNameOverride()
** getContractDate()
** getTermsId()
** getContractLanguageId()
** getCommitted()
** getCommittedNew()
** getCommittedTime()
** getInserted()
** getData()
** getCommitNotes()
** getCommitPersonId()
** getClientMultiplier()
** getEditCount()
** getAddressOverride()
** getBillingProfiles()
** update($val)
** save($incrementEditCount)

** public static function getContractStatusName($statusId)
**  public static function validateContractStatus($contractStatusId, $unique_error_id=null)
** public static function getContractReviewerId($contractId)
** public static function validate($contractId, $unique_error_id=null)

*/

class Contract extends SSSEng {
    // The following correspond exactly to the columns of DB table Contract
    // See documentation of that table for further details.
	private $contractId;
	private $workOrderId;
	private $nameOverride;
	private $contractDate;
	private $termsId;
	private $contractLanguageId;
	private $committed;
	private $committedTime;
	private $inserted;
	private $data;
	private $commitNotes;
	private $commitPersonId;
	private $clientMultiplier;
	private $addressOverride;	
	private $hourlyRate;	
	private $editCount;
		
	private $updateCommittedTime; // This is set if Contract::committed is set true via the update method, 
	                              // which is the only intended public method to change its value. >>>00016 NOTE,
	                              // however, the danger of a caller passing into the constructor an $id that is
	                              // an associative array with $id['contractId']==FOO and $id['committed']==1,
	                              // where the row in the DB table with contractId=FOO has committed=0.
	                              // We do nothing now to guard against that. - JM 2019-02-20
	
    // INPUT $id: Should be a contractId from the Contract table.
    // INPUT $user: User object, typically current user; defaults to current user (which can by NULL if running fron CLI).
	public function __construct($id = null, User $user = null) {
		parent::__construct($user);		
		$this->updateCommittedTime = false;		
		$this->load($id);		
	}

	// INPUT $val here is input $id for constructor.
	private function load($val) {		
		if (is_numeric($val)) {
			// Read row from DB table Contract
			$query = "SELECT c.* ";
			$query .= "FROM " . DB__NEW_DATABASE . ".contract c ";
			$query .= "WHERE c.contractId = " . intval($val) . ";";

			if ($result = $this->db->query($query)) {  // >>>00019 Assignment inside "if" statement, may want to rewrite.
				if ($result->num_rows > 0){					
				    // Since query used primary key, we know there will be exactly one row.
						
					// Set all of the private members that represent the DB content
					$row = $result->fetch_assoc();					
					$this->setContractId($row['contractId']);
					$this->setworkOrderId($row['workOrderId']);
					$this->setNameOverride($row['nameOverride']);
					$this->setContractDate($row['contractDate']);
					$this->setTermsId($row['termsId']);
					$this->setContractLanguageId($row['contractLanguageId']);
					// 1 Draft 2 Review 3 Commited 4 Delivered 5 Signed  6 Void 7 Void Deleted 
					$this->setCommitted($row['committed']); // Contract Status 
					$this->setCommittedTime($row['committedTime']);
					$this->setInserted($row['inserted']);
					/* BEGIN REPLACED 2020-09-09 JM
					$this->setData($row['data']);
					// END REPLACED 2020-09-09 JM
					*/
					// BEGIN REPLACEMENT 2020-09-09; further adapted 2020-09-28 JM
					if (is_null($row['data2'])) {
                        $this->setData($row['data']); // which has the side effect of changing the data to the new version introduced in v2020-4.
                        $query = "UPDATE " . DB__NEW_DATABASE . ".contract SET ";
                        $query .= "data2 = '" . $this->db->real_escape_string(json_encode($this->getData())) . "' ";
                        $query .= "WHERE contractId = " . intval($this->contractId) . ";";
                        
                        $result = $this->db->query($query);
                        if (!$result) {
                            $this->logger->errorDb('1599601379', "Hard DB error establishing data2 for contractId " . $this->contractId, $this->db);
                            // >>>00026 I don't think this should ever arise, but if it does we probably mess something up with any further
                            //  save of this Contract object. Need to think about what best to do here. JM 2020-09-09.
                        }
					} else {
					    $this->setData($row['data2']);
					}
					// END REPLACEMENT 2020-09-09 JM
					$this->setCommitNotes($row['commitNotes']);
					$this->setCommitPersonId($row['commitPersonId']);
					$this->setClientMultiplier($row['clientMultiplier']);
					$this->setAddressOverride($row['addressOverride']);
					$this->setHourlyRate($row['hourlyRate']);
					$this->setEditCount($row['editCount']);
				} // >>>00002 else ignores that we got a bad contractId!
			} // >>>00002 else ignores failure on DB query! Does this throughout file, 
			  // haven't noted each instance.
		} else if (is_array($val)) {
		    /* BEGIN REMOVED AS A BAD IDEA 2020-09-09 JM
		    // Set all of the private members that represent the DB content, from 
		    //  input associative array
			$this->setContractId($val['contractId']);	
			$this->setworkOrderId($val['workOrderId']);
			$this->setNameOverride($val['nameOverride']);
			$this->setNumber($val['number']);
			$this->setContractDate($val['contractDate']);
			$this->setTermsId($val['termsId']);		
			$this->setContractLanguageId($val['contractLanguageId']);				
			$this->setCommitted($val['committed']);
			$this->setCommittedTime($val['committedTime']);
			$this->setInserted($val['inserted']);							
			$this->setData($val['data']);			
			$this->setCommitNotes($val['commitNotes']);
			$this->setCommitPersonId($val['commitPersonId']);
			$this->setClientMultiplier($val['clientMultiplier']);
			$this->setAddressOverride($val['addressOverride']);
			$this->setEditCount($val['editCount']);
			// END REMOVED AS A BAD IDEA 2020-09-09 JM
			*/
			// and just in case this was somehow used (doesn't appear to have been - 2020-09-09 JM)
			$this->logger->error2('1599600673', "Contract constructor called with array instead of contractId");
		} 				
	} // END private function load	
	
	// Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId(){
		return $this->getContractId();
	}
	
	// ------ "set" functions should be largely self-explanatory ------
	// NOTE that some of these are private, others public.
	// >>>00016, >>>00002: all probably should validate (legitimate foreign keys, 
	//  strings of legal length) & log if invalid
	
	// INPUT $val is primary key
	private function setContractId($val) {
		$this->contractId = intval($val);
	}
	
	// INPUT $val is foreign key into DB table WorkOrder
	private function setWorkOrderId($val) {
		$this->workOrderId = intval($val);
	}	
	
	// INPUT $val is contract name. Apparently we *always* use this: start from 
	//  Job Name, but when we create contract we copy into the override
	//  right away.
	public function setNameOverride($val) {
		$val = trim($val);
		$val = substr($val, 0, 75); // >>>00002 truncates but does not log
		$this->nameOverride = $val;
	}	
	
	// INPUT $val is a DATETIME in 'Y-m-d H:i:s' format. 
	//  >>>00016, >>>00002: time portion should always be 00:00:00, but
	//  does not validate or log.
	public function setContractDate($val) {	
		/*$v = new Validate();
		if ($v->verifyDate($val, true, 'Y-m-d H:i:s')){
			$this->contractDate = $val;
		} else {
		     // >>>00002 zeroes date but does not log
			$this->contractDate = '0000-00-00 00:00:00';
		}*/
		$this->contractDate = $val;
	}	
	
	// INPUT $val is foreign key into DB table Terms.
	// It can make sense for this to be null if contract is work in progress (not committed)
	public function setTermsId($val) {
		$this->termsId = intval($val);
	}
	
	// INPUT $val is foreign key into DB table ContractLanguage.
	// It can make sense for this to be null if contract is work in progress (not committed)
	public function setContractLanguageId($val) {
		$this->contractLanguageId = intval($val);
	}	
	
	// INPUT $val: 0 or 1, effectively Boolean.
	// >>>00016, >>>00002: should only ever be 0 or 1, but does not even validate let alone log error. 
	private function setCommitted($val) {
		$this->committed = intval($val);
	}
	
	// INPUT $val is a DATETIME in 'Y-m-d H:i:s' format.
	// As of 2019-02-20, a bit of a mess here. This probably should be a TIMESTAMP, not (as it is)
	// a DATETIME: only time this should change is when 'committed' goes from 0 to 1 (it should never
	// go back)
	// >>>00001: probably deserves further thought/study 2019-02-20 JM
	private function setCommittedTime($val) {
		$this->committedTime = $val;
	}	
	
	// INPUT $val is a TIMESTAMP in 'Y-m-d H:i:s' format.
	// When row was created, never should be modified, so it's a bit fast & loose
	// that we let this be passed in as part of $val in constructor.
	// >>>00001: probably deserves further thought/study 2019-02-20 JM, Maybe 
	//  *always* read from DB & get value there? Maybe don't care because this
	//  never gets *written* to DB.
	private function setInserted($val) {
		$this->inserted = $val;
	}
	
	// INPUT $val encodes information about workOrderTasks that constitute the body of the contract.
	// It may be either a string or an array, as discussed below.
	// 
	// In the DB, contract.data has existed for a long time; contract.data2 is introduced in Panther v2020-4, 
	// and probably in its final form as of 2020-09-28. In the database:
	// * contract.data is a serialized, base-64-encoded version of the JSON representation of a multi-level array 
	//   structure combining associative and numerically indexed arrays. If non-null, it represents the return 
	//   of function overlay as it stood up to and including Panther v2020-3. We are no longer actively maintaining 
	//   this column.
	// * contract.data2 is the straight JSON representation of a multi-level array structure combining  
	//   associative and numerically indexed arrays. If non-null, it represents the return 
	//   of function overlay as it stands in Panther v2020-4. We actively maintain this column.
	// As of 2020-09-09, the only difference between the two JSON structures is how we handle workOrderTasks that 
	//   are associated with a combination of elements rather than with a single element. The principal 
	//   difference is in the level of this array that represents elementgroups:
	// Index             
	// 0	                                                     "General" (no particular element)
	// elementId  (any single integer except PHP_INT_MAX)        That elementId
	// PHP_INT_MAX (only in 'data')                              Any combination of two or more elements
	// comma-separated list of integer values (only in 'data2')  Two or more specific elements, as listed
	// 
	// There are also some differences within the data for elements that link to two or more elements, 
	//  mainly insofar as they include elementId and elementName.
	//
	// Going forward, we want the data2 form, and that is all we will maintain.
	// 
	// INPUT $val may be either in the form of an array, analogous to the output of function overlay, or
	//  may be the string form stored in the database. If the latter, we decode & unserialize it: object member
	//  $this->data is always in the form of the array (which is a complicated hierarchy of
	//  arrays, associative and otherwise).
	private function setData($val) {
	    /* BEGIN REPLACED 2020-09-09 JM
		if (!is_array($val) && strlen($val)){
			$t = base64_decode($val);
			$t = unserialize($t);
			if (is_array($t)){
				$this->data = $t;
			} else {
				$this->data = array();
			}
		} else if (is_array($val)){
			$this->data = $val;
		} else {
			$this->data = array();
		}
		// END REPLACED 2020-09-09 JM
		*/
		// BEGIN REPLACEMENT 2020-09-09 JM, further adapted 2020-09-28
		// >>>00006 this probably belongs somewhere as common code with Invoice.class.php
		
		if (is_string($val) && strlen($val)) {
		    $val2 = json_decode($val, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                // It's JSON, which is what we write in the DB now
                $val = $val2;
            } else {
                // Presumably the old way we encrypted this as a string before v2020-4.
                $t = base64_decode($val);
                $val = unserialize($t); // overwrite INPUT $val: we have decrypted it from a string
            }
		}
		// At this point, if it's not an array, it's junk so we'll substitute an empty array
		if (!is_array($val)) {
			$val = array();
		}
		// Now transform to the new version adopted in v2020-4.
		// * If there is an old-style multi-element entry, we need to transform it
		// * If there is a new-style multi-element entry, we must be in the new version, and no tranform is needed
		// * If there is neither, then the issue doesn't arise for this particular data, and we're fine.
		
		/*$transformNeeded = false;
		foreach ($val AS $elementGroup => $elementGroupData) {
		    if (is_string($elementGroup) && strpos($elementGroup, ',') !== false) {
		        break; // new-style multi-element, nothing to do
		    } else if ($elementGroup == PHP_INT_MAX) {
		        // old-style multi-element
		        $transformNeeded = true;
		        break;
		    }
		}
		
		if ($transformNeeded) {
		    foreach ($val AS $elementGroup => $elementGroupData) {
                if ($elementGroup == PHP_INT_MAX) {
                    // old-style multi-element
                    $newElementGroups = Array();
                    foreach($elementGroupData['tasks'] as $workOrderTaskData) {
                        $workOrderTaskId = $workOrderTaskData['workOrderTaskId'];
                        if (!WorkOrderTask::validate($workOrderTaskId)) {
                            $error = "invalid workOrderTaskId $workOrderTaskId in data";
                            // >>>00006 if we pull out common code, the context on failure probably needs to be provided by the caller.
                            if (isset($this->contractId)) {
                                $error .= " for contract {$this->contractId}";
                            }
                            $this->logger->error2('1599598830', $error);
                            // We have a mess. Just fail.
                            $this->data = Array();
                            return;
                        }
                        $workOrderTask = new WorkOrderTask($workOrderTaskId);
                        $newElements = $workOrderTask->getWorkOrderTaskElements(); // We rely here on these coming in a predictable order, by elementId.
                        $newElementGroupId = '';
                        $newElementGroupName = '';
                        foreach($newElements as $element) {
                            if ($newElementGroupId) {
                                $newElementGroupId .= ','; // NO space after comma
                            }
                            if ($newElementGroupName) {
                                $newElementGroupName .= ', '; // space after comma
                            }
                            $newElementGroupId .= $element->getElementId();
                            $newElementGroupName .= $element->getElementName(); 
                        }
                        if (!array_key_exists($newElementGroupId, $newElementGroups)) {
                            $newElementGroups[$newElementGroupId] = Array();
                            $newElementGroups[$newElementGroupId]['element'] =
                                Array("elementId" => $newElementGroupId, "elementName" => $newElementGroupName);
                            $newElementGroups[$newElementGroupId]['tasks'] = Array();    
                        }
                        $newElementGroups[$newElementGroupId]['tasks'][] = $workOrderTaskData;
                        unset($newElementGroupId);
                    }
                    foreach ($newElementGroups as $newElementGroupId => $newElementGroup) {
                        $val[$newElementGroupId] = $newElementGroup;
                    }
                    unset($val[PHP_INT_MAX]);
                    break;
                }
            } 
		} */
		$this->data = $val;		
		// END REPLACEMENT 2020-09-09 JM
	} // END private function setData
	
	// INPUT $val: note (string) 
	public function setCommitNotes($val) {
		$val = trim($val);
		$val = substr($val, 0, 1024); // >>>00002 truncates but does not log
		$this->commitNotes = $val;
	}	
	
	// INPUT $val: foreign key into DB table Person 
	public function setCommitPersonId($val){
		$this->commitPersonId = intval($val);
	}
	
	// INPUT $val: floating point number, most often 1.0
	public function setClientMultiplier($val) {
		if(filter_var($val, FILTER_VALIDATE_FLOAT) !== false) {
			$this->clientMultiplier = $val;
		} else {
		    // >>>00002 does not log validation failure.
		    // >>>00001 why isn't default 1.0? - JM 2019-02-20
			$this->clientMultiplier = 0;
		}
	}
	
	// INPUT $val is address. Apparently we *always* use this: start from 
	//  address in workOrder, but when we create contract we copy into the override
	//  right away.
	public function setAddressOverride($val) {
		$val = trim($val);
		$val = substr($val, 0, 255);
		$this->addressOverride = $val;
	}

	// standard hourly rate / contract and contract pdf.
	public function setHourlyRate($val) {	
		$this->hourlyRate = intval($val);
	}
	
	// Although we increment this integer value over time, we really use it
	// more like a Boolean: has this contract been edited?
	public function setEditCount($val) {	
		$this->editCount = intval($val);
	}	
	
	// RETURN primary key
	public function getContractId() {
		return $this->contractId;
	}
	
	// RETURN ID of corresponding workOrder, foreign key into DB table WorkOrder
	public function getWorkOrderId() {
		return $this->workOrderId;
	}
	
	// RETURN name used in contract
	public function getNameOverride() {
		return $this->nameOverride;
	}

	// RETURN nominal date of contract
	public function getContractDate() {
		return $this->contractDate;
	}

	// RETURN foreign key into DB table Terms
	public function getTermsId() {
		return $this->termsId;
	}
	
	// RETURN foreign key into DB table ContractLanguage
	public function getContractLanguageId() {
		return $this->contractLanguageId;
	}
	
	// RETURN whether contract has been committed (0 or 1)
	public function getCommitted() {
		return intval($this->committed);
	}
	
	// RETURN whether contract has been committed (0 or 1)
	// Apparently indistiguishable from public function getCommitted 
	// Martin said in a 2018 meeting that this is a loose end, something he started into and never followed up.
	public function getCommittedNew() {
		return intval($this->committed);
	}	
	
	// RETURN committed time, apparently as of 2019-02-20 a DATETIME (not a timestamp) in 'Y-m-d H:i:s' format.
	public function getCommittedTime() {
		return $this->committedTime;
	}	
	
	// RETURN inserted time, apparently as of 2019-02-20 a TIMESTAMP in 'Y-m-d H:i:s' format.
	public function getInserted() {
		return $this->inserted;
	}
	
	// RETURN encodes the information about workOrderTasks in the contract.
	// The return (a complicated hierarchy of arrays, associative and otherwise)
	//  is identical to the return of function 'overlay' in inc/functions.php, and is
	//  documented there.
	public function getData() {
		return $this->data;
	}
	
	// RETURNs a string
	public function getCommitNotes() {
		return $this->commitNotes;
	}
	// RETURN foreign key into DB table Person, indicating who committed the contract
	public function getCommitPersonId() {
		return $this->commitPersonId;
	}
	// RETURN clientMultiplier (float)
	public function getClientMultiplier() {
		return $this->clientMultiplier;
	}	
	
	// RETURN integer hourlyRate;
	public function getHourlyRate() {
		return $this->hourlyRate;
	}	

	// RETURN integer editCount; we mostly care whether this is nonzero.
	public function getEditCount() {
		return $this->editCount;
	}	
	
	// RETURN address associated with contract (string)
	public function getAddressOverride() {
		return $this->addressOverride;
	}	
	
	// Despite the plural in its name, getBillingProfiles always returns a one-element 
	//  array containing an associative array representing a single row from DB table 
	//  contractBillingProfile: the most recent row with a matching contractId.
    //  The associative array uses the canonical approach to represent row content.
	public function getBillingProfiles() {
		$ret = array();
		
		// This relies on a trick to get the most recent matching row: it assumes 
		//  that the highest contractBillingProfileId represents the most recent 
		//  entry in the cross table contractBillingProfile. 
		$query = " select * ";
		$query .= " from " . DB__NEW_DATABASE . ".contractBillingProfile  ";
		$query .= " where contractId = " . intval($this->getContractId()) . " ";
		$query .= " order by contractBillingProfileId desc limit 1 ";		
		
		if ($result = $this->db->query($query)) {  // >>>00019 Assignment inside "if" statement, may want to rewrite.
			if ($result->num_rows > 0) {
			    // >>>00018 JM: No good reason for a 'while' rather than an 'if'
				while ($row = $result->fetch_assoc()) {					
					$ret[] = $row;					
				}
			}
		} 

		return $ret;		
	} // END public function getBillingProfiles
	
	// INPUT $val is an associative array that can have any or all of the following elements:
	//  * 'termsId' - this and most of the others can best be understood by looking at the "set" methods above.
	//  * 'contractLanguageId'
	//  * 'nameOverride'
	//  * 'addressOverride'
	//  * 'committed'
	//  * 'data'
	//  * 'commitNotes'
	//  * 'contractDate'
	//    * This is NOT expected not in 'Y-m-d H:i:s' form; oddly, we don't even *allow*
	//      that form, although it is what we will build here. Instead, we want it in 'm/d/Y' form, and if it
	//      isn't input in that form, we will fall back to '0000-00-00 00:00:00'.
	//    * >>>00016: On the other hand, we don't properly validate that the month, day, and year are numbers,
	//      let alone in a sane range. We just take intval, so '42/2b3/99' would turn into an insane
	//      '0099-42-02' and make it into an UPDATE.
	//  * 'clientMultiplier'
	//  * 'IncrementEditCount': note unusual capitalization. This is effectively a Boolean, passed to 
	//    public function save to indicate whether or not to increment the edit count.
	public function update($val) {

		if (is_array($val)){
			if (isset($val['termsId'])) {
			    // >>>00007 isset test in following line is redundant to test already made, as are analogous ones 
			    //  for other elements of the associative array.
				$termsId = isset($val['termsId']) ? intval($val['termsId']) : 0;
				$this->setTermsId($termsId);					
			}
			
			if (isset($val['contractLanguageId'])) {
				$contractLanguageId = isset($val['contractLanguageId']) ? intval($val['contractLanguageId']) : 0;
				$this->setContractLanguageId($contractLanguageId);
					
			}
			
			if (isset($val['nameOverride'])) {			
				$nameOverride = isset($val['nameOverride']) ? $val['nameOverride'] : '';
				$this->setNameOverride($nameOverride);			
			}

			if (isset($val['addressOverride'])) {					
				$addressOverride = isset($val['addressOverride']) ? $val['addressOverride'] : '';
				$this->setAddressOverride($addressOverride);					
			}
			
			if (isset($val['committed'])) {			
				$committed = isset($val['committed']) ? $val['committed'] : 0;
				$this->setCommitted($committed);
				if ($committed) {
					$this->updateCommittedTime = true;
				}					
			}
			
			if (isset($val['data'])) {
                $data = isset($val['data']) ? $val['data'] : false;
				$this->setData($data);					
			}
				
			if (isset($val['commitNotes'])){			
				$commitNotes = isset($val['commitNotes']) ? $val['commitNotes'] : '';
				$this->setCommitNotes($commitNotes);			
			}
			
			/*if (isset($val['contractDate'])){				
			    // >>>00006: code like this appears in a lot of different classes,
			    // should certainly have potential for common code elimination.
				$contractDate = '0000-00-00 00:00:00';
				
				$parts = explode("/", $val['contractDate']);
				if (count($parts) == 3){
					$contractMonth = intval($parts[0]);
					$contractDay = intval($parts[1]);
					$contractYear = intval($parts[2]);
					
					$contractMonth = str_pad($contractMonth,2,'0',STR_PAD_LEFT);
					$contractDay = str_pad($contractDay,2,'0',STR_PAD_LEFT);
					$contractYear = str_pad($contractYear,4,'0',STR_PAD_LEFT);
					
					$contractDate = $contractYear . '-' . $contractMonth . '-' . $contractDay . ' 00:00:00';
				}
				
				$this->setContractDate($contractDate);				
			}*/
			
			if (isset($val['clientMultiplier'])){			
				$this->setClientMultiplier($val['clientMultiplier']);			
			}	
			
			if (isset($val['hourlyRate'])){
				$this->setHourlyRate($val['hourlyRate']);
			}			
			
			
			$IncrementEditCount = 0;
			if (isset($val['IncrementEditCount'])){
				$IncrementEditCount = isset($val['IncrementEditCount']) ? intval($val['IncrementEditCount']) : 0;
			}
			
			if (intval($IncrementEditCount)){
				$this->save(1);
			} else {
				$this->save(0);
			}
		} // >>>00002 else input had wrong form, probably worth logging
	} // END public function update	

	// save function is important here, because public set functions won't affect the DB until save is also called.
	// >>>00001: it might be worth studying whether anyone is using those public set functions, or just the update function. JM 2019-02-20.
	// INPUT $incrementEditCount: effectively a Boolean, if true we increment the edit count.
	public function save($incrementEditCount){		
		$query = " update " . DB__NEW_DATABASE . ".contract  set ";
		$query .= " nameOverride = '" . $this->db->real_escape_string($this->getNameOverride()) . "' ";
		$query .= " ,addressOverride = '" . $this->db->real_escape_string($this->getAddressOverride()) . "' ";		
		$query .= " ,contractDate = now() ";
		$query .= " ,termsId = " . intval($this->getTermsId()) . " ";
		$query .= " ,contractLanguageId = " . intval($this->getContractLanguageId()) . " ";		
		$query .= " ,committed = " . intval($this->getCommitted()) . " ";
        /* BEGIN REPLACED 2020-09-09 JM
        $query .= " ,data = '" . $this->db->real_escape_string(    base64_encode(serialize($this->getData()))    ) . "' ";
        // END REPLACED 2020-09-09 JM
        */
        // BEGIN REPLACEMENT 2020-09-09 JM, further tweaked 2020-09-28
        $query .= ", data2 = '" . $this->db->real_escape_string(json_encode($this->getData())) . "' ";
        // END REPLACEMENT 2020-09-09 JM
		$query .= " ,clientMultiplier = '" . $this->db->real_escape_string(  $this->getClientMultiplier()    ) . "' ";
		// BEGIN ADDED 2020-01-27 JM 
		//  Despite the name "commitNotes", we've decided that the note really should be added not at the time we commit this contract
		//   (which never really calls for a note) but at the time one is committed OVER it.
		$query .= " ,commitNotes = '" . $this->db->real_escape_string($this->getCommitNotes()) . "' ";
		// END ADDED 2020-01-27 JM
		
		if ($this->updateCommittedTime) {			
			$query .= " ,committedTime = now() ";
			// BEGIN REMOVED 2020-01-27 JM: see note on commitNotes above for why this is now outside of the test.
            // $query .= " ,commitNotes = '" . $this->db->real_escape_string($this->getCommitNotes()) . "' ";
            // END REMOVED 2020-01-27 JM
            $query .= " ,commitPersonId = " . intval($this->user->getUserId()) . " ";						
		}
		$query .= " ,hourlyRate = " . intval($this->getHourlyRate()) . " ";
		
		if (intval($incrementEditCount)){
		    $query .= ", editCount = (editCount+1) ";
		}
		
		$query .= " where contractId = " . intval($this->getContractId()) . " ";
		
		$this->updateCommittedTime = false; // always clear this
		syslog(LOG_ERR, $query);
		//die();
		$this->db->query($query);
	}
	

    // Return statusName if the id is a valid contractStatusId, false if not
    // INPUT $statusId: $contract->getCommitted().
	public static function getContractStatusName($statusId) {
		global $logger;
		$db = DB::getInstance();	
		if (self::validateContractStatus($statusId)) {
            $statusId = $statusId;
        } else {
            $logger->error2('15935433450033232', "Contract::setStatus called with invalid contractStatusId $statusId");
            return false;
        }	 
  
        $query  = "SELECT statusName ";
        $query .= " FROM " . DB__NEW_DATABASE . ".contractStatus ";
        $query .= " WHERE contractStatusId = " . intval($statusId) . " ";

    
		$result = $db->query($query);
        if ($result) {
			$row = $result->fetch_assoc();
			$ret = $row['statusName'];
        } 
        
        return $ret;		 
	} // END public static function getContractStatusId
	
    // Return true if the id is a valid contractStatusId, false if not
    // INPUT $contractStatusId: contractId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validateContractStatus($contractStatusId, $unique_error_id=null) {
        global $db, $logger;
        Contract::loadDB($db);
        
        $ret = false;
        $query = "SELECT contractStatusId FROM " . DB__NEW_DATABASE . ".contractStatus WHERE contractStatusId=$contractStatusId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1500107582', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
	}
	


	// Return personId of the "reviewer" of the contract. On contract status change.
	// Table contractNotification.
    // INPUT $contractId
	public static function getContractReviewerId($contractId) {
		global $logger;
		$db = DB::getInstance();
		$ret = null;
		 
  
        $query  = " SELECT reviewerPersonId ";
        $query .= " FROM " . DB__NEW_DATABASE . ".contractNotification ";
        $query .= " WHERE contractId = " . intval($contractId) . " ";

    
		$result = $db->query($query);
        if ($result) {
			if($result->num_rows > 0) {
				$row = $result->fetch_assoc();
				$ret = $row['reviewerPersonId'];
			}
			
        } else {
			$logger->error2('637750753028786028', "Contract::getContractReviewerId DB error");
			return null;
		}
        
        return $ret;		 
	} // END public static function getContractReviewerId
	
    // Return true if the id is a valid contractId, false if not
    // INPUT $contractId: contractId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($contractId, $unique_error_id=null) {
        global $db, $logger;
        Contract::loadDB($db);
        
        $ret = false;
        $query = "SELECT contractId FROM " . DB__NEW_DATABASE . ".contract WHERE contractId=$contractId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578686555', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>