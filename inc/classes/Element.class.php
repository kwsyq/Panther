<?php 
/* inc/classes/Element.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Element table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for a particular element.
* Public functions:
** __construct($id = null, User $user = null)
** setElementName($val)
** getElementId()
** getElementName()
** getDescriptor2Ids() 
** addDescriptor($descriptor2Id, $modifier, $note)
** update($val)
** save()
** toArray()

** public static function validate($elementId, $unique_error_id=null)
** public static function errorToText($errCode)
** public static function generateUniqueElementName($val, $jobId, &$errCode=false)
*/

class Element extends SSSEng {
    // The following correspond exactly to the columns of DB table Element.
    // See documentation of that table for further details.
    private $elementId;
    // private $workOrderId; // REMOVED 2020-04-30 JM: killing code for an old migration.
    private $elementName;
    private $jobId;  // Added 2020-04-03 JM. No idea why this wasn't here. We always had sufficient information to know it.
    
    /* BEGIN REMOVED 2020-04-30 JM: killing code for an old migration.
    // [Martin commment] get rid of these 2 after all migrating of elements etc done.
    // JM: note that these two are handled as public, no local code for these outside of load.
    // public $retired; 
    // public $migration;
    // END REMOVED 2020-04-30 JM: killing code for an old migration.
    */
    
    // INPUT $id: May be either of the following:
    //  * a descriptorId from the Element table
    //  * an associative array which should contain an element (as in "array element") for each columnn
    //    used in the Element table, corresponding to the private variables
    //    just above.
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
    // INPUT $user: User object, typically current user. 
    //  >>>00023: JM 2019-02-18: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI). 
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->load($id);        
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {        
        if (is_numeric($val)) {
            // Read row from DB table Element
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".element ";
            $query .= " where elementId = " . intval($val);

            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {                    
                    // Since query used primary key, we know there will be exactly one row.
                        
                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();
                    
                    $this->setElementId($row['elementId']);
                    // $this->setWorkOrderId($row['workOrderId']);  //REMOVED 2020-04-30 JM: killing code for an old migration.
                    $this->setJobId($row['jobId']); // added 2020-04-30 JM: killing code for an old migration.
                    $this->setElementName($row['elementName']);
                    
                    /* BEGIN REMOVED 2020-04-30 JM: killing code for an old migration.
                    // only used during migration
                    $this->retired = $row['retired'];
                    $this->migration = $row['migration']; 
                    // REMOVED 2020-04-30 JM: killing code for an old migration.
                    */
                } // >>>00002 else ignores that we got a bad elementId!
            } // >>>00002 else ignores failure on DB query! Does this throughout file, 
              // haven't noted each instance.
        } else if (is_array($val)) {
            // Set all of the private (or public!) members that represent the DB content, from 
            //  input associative array
            $this->setElementId($val['elementId']);
            // $this->setWorkOrderId($val['workOrderId']);   // REMOVED 2020-04-30 JM: killing code for an old migration.
            $this->setJobId($val['jobId']); // added 2020-04-03 JM
            $this->setElementName($val['elementName']);

            /* BEGIN REMOVED 2020-04-30 JM: killing code for an old migration.
            // only used during migration
            $this->retired = $val['retired'];
            $this->migration = $val['migration']; 
            // END REMOVED 2020-04-30 JM: killing code for an old migration.
            */
        }
    } // END private function load    
    
    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getElementId();
    }

    // Set primary key
    // INPUT $val: primary key (elementId)
    private function setElementId($val){
        $this->elementId = intval($val);
    }
    
    // Set jobId
    // INPUT $val: foreign key to Job table
    private function setJobId($val){
        $this->jobId = intval($val);
    }
    
    /* BEGIN REMOVED 2020-04-30 JM: killing code for an old migration.
    // Set workOrderId
    // INPUT $val: foreign key to WorkOrder table
    private function setWorkOrderId($val){
        $this->workOrderId = intval($val);
    }
    // END REMOVED 2020-04-30 JM: killing code for an old migration.
    */
    
    
    /**
    * Set element name.
    * @param string INPUT $val: anything past 255 characters is silently ignored.
    *  Should be unique within a job. 
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * 
    */
    public function setElementName($val, &$errCode=false){
        $errCode = false;
        // George 2020-08-10. Rewrite
        //$val = trim($val); 
        //$val = substr($val, 0, 255); // >>>00002 truncates silently, needs the usual cleanup
        $val = truncate_for_db ($val, 'elementName', 255, '637326607442821991'); //  handle truncation when an input is too long for the database.

        // BEGIN ADDED 2020-04-02 JM based on discussion with Ron & Damon
        // We never want to get 2 elements with the same name on a job. Suffix it with a parenthesized number of that happens.
        $original_request = $val;
        $ok = false;
        while (!$ok) {
            $query = "SELECT elementName FROM " . DB__NEW_DATABASE . ".element ";
            $query .= "WHERE jobId = " . $this->jobId . " ";  // >>>00032: need jobId! Which means we need to consistently
                                                              // set that in constructor. Will need to examine all existing cases
                                                              // where this is constructed off of an array to make sure that is 
                                                              // passed in (plus handling it when we construct from an ID.
            $query .= "AND elementName = '" . $this->db->real_escape_string($val) . "' ";
            $query .= "AND elementId != " . $this->elementId . ";";
            
            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDb('1585871078', "Hard DB error", $this->db);
                $errCode=true; // set to true on query failed.
                return; // failed. Don't just break, because we don't want to set $this->elementName. 
            }
            if ($result->num_rows == 0) {
                if ($val != $original_request) {
                    $this->logger->info2('1585871052', "Had to modify element name; original request was '$original_request', using '$val'");
                }
                $ok = true; // get out of loop
            } else {
                // small parenthesized integer as a suffix.
                $regex = '/(.*) (\(\d+\))$/';
                $preg_match_result = preg_match($regex, $val, $matches);
                if ($preg_match_result === false) {
                    $this->logger->err2('1585871083', "preg_match failed, probably a bad regex: '$regex'");
                } else if ($preg_match_result) {
                    $base = $matches[1];
                    $disambiguator = intval($matches[2]); // should be an integer
                    if ($disambiguator < 2) {
                        // edge case, what if someone wrote in '(-7)'
                        $disambiguator = 2;
                    }
                } else {
                    $base = $val;
                    // let's cut it a lot shorter to be safe about 255 with disambiguator; 255 is insanely generous, these are almost all <50
                    $base = trim(substr($val, 0, 225));
                    $disambiguator = 1; // never actually use '1', that's just so it can be incremented to 2
                }
                ++$disambiguator;
                $val = "$base ($disambiguator)";         
            }
        }
        // END ADDED 2020-04-02 JM based on discussion with Ron & Damon
        
        $this->elementName = $val;
    }

    // RETURN primary key (elementId)
    public function getElementId(){
        return $this->elementId;
    }    
    
    // RETURN foreign key to job table
    public function getJobId(){
        return $this->jobId;
    }
    
    /* BEGIN REMOVED 2020-04-30 JM: killing code for an old migration.
    // RETURN foreign key to workOrder table
    public function getWorkOrderId(){
        return $this->workOrderId;
    }
    // END REMOVED 2020-04-30 JM: killing code for an old migration.
    */
    
    // RETURN element name
    public function getElementName(){
        return $this->elementName;
    }    
    
    /* RETURNS an array of Descriptor2 Ids explicitly associated with this element via elementDescriptor. No particular order.
       
       NOTE that this just returns the Descriptor2 Ids, not modifier, notes, etc. This is NOT typically what you
       want for display purposes, but it is a quick shorthand to grab the descriptor2Ids if that is all you need.
    */
    public function getDescriptor2Ids() {        
        $ret = array();
        
        $query = "SELECT descriptor2Id " .
                 "FROM " . DB__NEW_DATABASE . ".elementDescriptor " .
                 "WHERE elementId = " . intval($this->getElementId()) . ";";
        
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1578007504', 'Hard DB error', $this->db);
            return $ret;
        }
        
        while ($row = $result->fetch_assoc()){
            $ret[] = $row['descriptor2Id'];
        }
        
        return $ret;
    } // END public function getDescriptor2Ids
    
    // INPUT $descriptor2Id: foreign key into DB table Descriptor2Id (prior to 2020-01-02, used $descriptorSubId)  
    // INPUT $modifier: string
    // INPUT $note: string
    // Makes the appropriate insertion into DB table elementDescriptor 
    //  for the current elementId. NOTE that this will fail silently 
    //  if the elementDescriptor already exists for this element: 
    //  it will not update modifier, note in that case.
    /* REPLACED 2020-01-02 JM
    public function addDescriptor($descriptorSubId, $modifier, $note) {        
    */
    // BEGIN REPLACEMENT 2020-01-02 JM
    public function addDescriptor($descriptor2Id, $modifier, $note) {
    // END REPLACEMENT 2020-01-02 JM
        $modifier = trim($modifier);
        $modifier = substr($modifier, 0, 32); // >>>00002: truncates silently

        $note = trim($note);
        $note = substr($note, 0, 64); // >>>00002: truncates silently

        /* REPLACED 2020-01-02 JM
        $query = " insert into " . DB__NEW_DATABASE . ".elementDescriptor(elementId, descriptorSubId, note, modifier) values (";
        */
        // BEGIN REPLACEMENT 2020-01-02 JM
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".elementDescriptor(elementId, descriptor2Id, note, modifier) VALUES (";
        // END REPLACEMENT 2020-01-02 JM
        
        $query .= " " . intval($this->getElementId()) . " ";
        
        /* REPLACED 2020-01-02 JM
        $query .= " ," . intval($descriptorSubId) . " ";
        */
        // BEGIN REPLACEMENT 2020-01-02 JM
        $query .= " ," . intval($descriptor2Id) . " ";
        // END REPLACEMENT 2020-01-02 JM

        $query .= " ,'" . $this->db->real_escape_string($note) . "' ";                
        $query .= " ,'" . $this->db->real_escape_string($modifier) . "') ";

        $this->db->query($query); // >>>00002: As mentioned above, if the row already exists
                                  // (but with different note and/or modifier) this will fail silently
    }
    
    /**
    *
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return boolean true on success, return of the method $this->save().
    * Update element name. INPUT $val typically comes from $_REQUEST.
    *  An associative array whose only significant element is:
    *  'elementName'
    */
    public function update($val, &$errCode=false) {
        if (is_array($val)) {
            if (isset($val['elementName'])) {                    
                // >>>00007 isset test in following line is redundant to test already made
                // George 2020-08-10. Rewrite.
                $elementName = $val['elementName'];
                
                $this->setElementName($elementName, $errCode); //$errCode is true on query failed.
            }
            
            return $this->save($errCode); //$errCode is true on query failed.

        }
    }
    
    /**
        *
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return boolean true on success. False on failure.
        * UPDATEs same fields handled by public function update, which is to say
        * all it can update is 'elementName'
    */
    public function save(&$errCode=false) {
        $query = " update " . DB__NEW_DATABASE . ".element  set ";
        $query .= " elementName = '" . $this->db->real_escape_string($this->getElementName()) . "' ";
        $query .= " where elementId = " . intval($this->getElementId()) . " ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb("637326685540563294", "saveElement => Hard error", $this->db);
            $errCode=true;
            return false;
        }

        return true;
    }
    
    // RETURN this element as associative array; >>>00001 NOTE that this doubles down
    //  on most of the columns here being vestigial.
    public function toArray() {        
        return array ('elementId' => $this->getElementId()
                ,'elementName' => $this->getElementName()
                );
    }    
    
    /*
    // This method was moved into the base class.
    private static function loadDB(&$db) {
        if (!$db) {
            $db =  DB::getInstance(); 
        }
    }*/
    
    // Return true if the id is a valid elementId, false if not
    // INPUT $elementId: elementId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($elementId, $unique_error_id=null) {
        global $db, $logger;
        Element::loadDB($db);
        
        $ret = false;
        $query = "SELECT elementId FROM " . DB__NEW_DATABASE . ".element WHERE elementId=$elementId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578691597', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    // INPUT $errCode comes from inc/classes/ErrorCodes.php
    // RETURN an array with two elements:
    //     * textual version of this error, relevant to Element class
    //     * a unique code for this error specific to Element class
    public static function errorToText($errCode) {
        $error = '';
        $errorId = 0;
    
        if($errCode == 0) {
            $errorId = '637177119207902194';
            $error = 'addElement method failed.';
        } else if($errCode == DB_GENERAL_ERR) {
            $errorId = '637177119339294288';
            $error = 'Error inserting element in Database';
        } else {
            $error = "Unknown error, please fix them and try again";
            $errorId = "637177119659648368";
        }
    
        return array($error, $errorId);
    }

    /**
    * Set element name.
    * @param string INPUT $val: The desired element name. Anything past 255 characters is truncated.
    *  Should be unique within a job. We may disambiguate in case of conflict.
    * @param int INPUT $jobId: id of the job for whom we do the test.
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    */
    public static function generateUniqueElementName($val, $jobId, &$errCode=false) {        
        $db=DB::getInstance();
        $logger = Logger2::getLogger("main");
        
        $errCode = false;
        // George 2020-08-10. Rewrite
        //$val = trim($val); 
        //$val = substr($val, 0, 255); // >>>00002 truncates silently, needs the usual cleanup
        $val = truncate_for_db ($val, 'elementName', 255, '637326607442821993'); //  handle truncation when an input is too long for the database.

        // BEGIN ADDED 2020-04-02 JM based on discussion with Ron & Damon
        // We never want to get 2 elements with the same name on a job. Suffix it with a parenthesized number of that happens.
        $original_request = $val;
        $ok = false;
        while (!$ok) {
            $query = "SELECT elementName FROM " . DB__NEW_DATABASE . ".element ";
            $query .= "WHERE jobId = " . $jobId . " ";  
            $query .= "AND elementName = '" . $db->real_escape_string($val) . "' ";
            
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1585871080', "Hard DB error", $this->db);
                $errCode=true; // set to true on query failed.
                return; // failed. Don't just break, because we don't want to set $this->elementName. 
            }
            if ($result->num_rows == 0) {
                if ($val != $original_request) {
                    $logger->info2('1585871054', "Had to modify element name; original request was '$original_request', using '$val'");
                }
                $ok = true; // get out of loop
            } else {
                // Strip out any prior disambiguator (integer in parentheses)
                $regex = '/(.*) (\(\d+\))$/';
                $preg_match_result = preg_match($regex, $val, $matches);
                if ($preg_match_result === false) {
                    $logger->err2('1585871085', "preg_match failed, probably a bad regex: '$regex'");
                } else if ($preg_match_result) {
                    $base = $matches[1];
                    $disambiguator = intval($matches[2]); // should be an integer
                    if ($disambiguator < 2) {
                        $disambiguator = 2;
                    }
                } else {
                    $base = $val;
                    $base = trim(substr($val, 0, 225)); // NOTE 225 here, not 255: make sure there is plenty of space for a disambiguator.
                    $disambiguator = 1; // never actually use '1', that's just so it can be incremented to 2
                }
                ++$disambiguator;
                $val = "$base ($disambiguator)";         
            }
        }        
        return $val;
    } // END public static function generateUniqueElementName
}

?>