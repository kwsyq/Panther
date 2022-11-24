<?php 
/* inc/classes/TeamCompanyPerson.class.php

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Team table.
This is one of very few cases where the class name does not correspond exactly to the table name.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Unlike most such classes, this does NOT extend SSSEng.
* Public functions:
** __construct($id = null)
** getTeamId()
** getInTable()
** getId()
** getRole()
** getDescription()
** getTeamPositionId()
** getCompanyPersonId()
** getCompanyPerson()
** update($val)
** save()
** public static function checkDuplicate($inTable, $teamPositionId, $companyPersonId, $id)
** public static function insertTeam($inTable, $teamPositionId, $companyPersonId, $id, $reason="")
** public static function validate($teamId, $unique_error_id=null)
** public static function validateTeamPositionId($teamPositionId, $unique_error_id=null)

*/

class TeamCompanyPerson extends SSSEng {
    // The following correspond exactly to the columns of DB table Team.
    // See documentation of that table for further details.
    private $teamId;
    private $inTable;
    private $id;
    private $role;
    private $description;
    private $teamPositionId;
    private $companyPersonId;
    // no variable for reason
    // no variable for active
    
    private $companyPerson; // CompanyPerson object corresponding to $companyPersonId 
    
    // INPUT $id: May be either of the following:
    //  * a teamId from the Team table
    //  * an associative array which should contain an element for each columnn
    //    used in the Team table, corresponding to the private variables
    //    just above.
    // INPUT $user: User object, typically current user. 
    //  >>>00016: JM 2019-02-18: should certainly validate this input, doesn't.
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->load($id);
        
        if (intval($this->getCompanyPersonId())) {
            $this->companyPerson = new CompanyPerson($this->getCompanyPersonId());            
        } 
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {
        if (is_numeric($val)) {            
            // Read row from DB table Team
            $query = " select t.* ";
            $query .= " from " . DB__NEW_DATABASE . ".team t  ";
            $query .= " where t.teamId = " . intval($val);
            
            $result = $this->db->query($query); // George 2020-07-14. Rewrite if statement.
             
            if(!$result) {
                $this->logger->errorDb('637303207904602465', 'load: Hard DB error', $this->db);
                return false;
            }
            
            if ($result->num_rows > 0) {
                // Since query used primary key, we know there will be exactly one row.
                // Set all of the private members that represent the DB content
                $row = $result->fetch_assoc();

                $this->setTeamId($row['teamId']);    
                $this->setInTable($row['inTable']);
                $this->setId($row['id']);
                $this->setRole($row['role']);
                $this->setDescription($row['description']);
                $this->setTeamPositionId($row['teamPositionId']);
                $this->setCompanyPersonId($row['companyPersonId']);                    
            } else {
                $this->logger->errorDb('637303208746112385', "No rows found", $this->db);
            }
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from 
            // input associative array
            $this->setTeamId($val['teamId']);    
            $this->setInTable($val['inTable']);
            $this->setId($val['id']);
            $this->setRole($val['role']);
            $this->setDescription($val['description']);
            $this->setTeamPositionId($val['teamPositionId']);
            $this->setCompanyPersonId($val['companyPersonId']);        
        }                
    } // END private function load

    // Set primary key
    // INPUT $val: primary key teamId in table team 
    private function setTeamId($val) {
        $this->teamId = intval($val);        
    }
    
    // INPUT $val: 1=>workOrder (INTABLE_WORKORDER), 2=>job (INTABLE_JOB) 
    private function setInTable($val) {    
        $this->inTable = intval($val);    
    }
    
    // INPUT $val: workOrderId or JobId, depending on value of $this->inTable
    private function setId($val) {    
        $this->id = intval($val);    
    }
    
    // INPUT $val: string, e.g. "Designer", "Developer", "Project Manager". 
    // Open-ended, probably just for display, might now be superseded by teamPositionId, 
    //  which is programmatic. 
    private function setRole($val) {    
        $val = truncate_for_db($val, 'Role', 64, '637303212548565271'); // truncate for db.
        $this->role = $val;    
    }    

    // INPUT $val: Text. Probably typically comes from description in table TeamPosition 
    private function setDescription($val) {    
        $val = truncate_for_db($val, 'Description', 1024, '637303213957294528'); // truncate for db.
        $this->description = $val;
    }

    // INPUT $val: foreign key into DB table TeamPosition 
    private function setTeamPositionId($val) {    
        $this->teamPositionId = intval($val);    
    }

    // INPUT $val: foreign key into DB table CompanyPerson
    private function setCompanyPersonId($val) {    
        if (CompanyPerson::validate(intval($val))) { // condition rewritten JM 2020-10-14
            $this->companyPersonId = intval($val);
        } else { 
            $this->logger->error2('637303325926719528', "Invalid CompanyPersonId : {$val}");
            return false;
        }    
    }    
    
    // RETURN primary key
    public function getTeamId() {    
        return $this->teamId;    
    }
    
    // RETURN $val: 1=>workOrder (INTABLE_WORKORDER), 2=>job (INTABLE_JOB)
    public function getInTable() {    
        return $this->inTable;    
    }
    
    // NOTE THAT THIS IS UNRELATED TO SSSEng:getId().
    // RETURN workOrderId or JobId, depending on value of $this->inTable
    public function getId() {    
        return $this->id;    
    }
    
    // RETURN string, e.g. "Designer", "Developer", "Project Manager". 
    // Open-ended, probably just for display, might now be superseded by teamPositionId, 
    //  which is programmatic.
    public function getRole() {
        return $this->role;    
    }
    
    // RETURN text. Probably typically comes from description in table TeamPosition
    public function getDescription() {
        return $this->description;            
    }
    
    // RETURN foreign key into DB table TeamPosition
    public function getTeamPositionId() {    
        return $this->teamPositionId;    
    }
    
    // RETURN foreign key into DB table CompanyPerson 
    public function getCompanyPersonId() {    
        return $this->companyPersonId;    
    }    
    
    // RETURN corresponding CompanyPerson
    public function getCompanyPerson(){    
        return $this->companyPerson;    
    }
    
    /**
    * @param array $val, INPUT $val is an associative array; only element 'teamPositionId' is meaningful.
    * @return boolean true on success, the return of $this->save(). False on failure.
    */
    public function update($val) {
        if (!is_array($val)) {
            $this->logger->error2('637303229860783247', 'update TeamCompanyPerson => expected array as input, got something not an array');
            return false;
        }
    
        if (isset($val['teamPositionId'])) {
            $exists = self::validateTeamPositionId($val['teamPositionId']); // check for valid teamPositionId.
            if ($exists) {
                $this->setTeamPositionId($val['teamPositionId']);
            } else {
                $this->logger->error2('637303267597454847', "teamPositionId => is not a valid entry in table TeamPosition. TeamPositionId : " . $val['teamPositionId']);
                return false;
            }
        }        
        return $this->save();
    }    
    

    /**
    * UPDATEs same fields handled by public function update.
    * @return boolean true on success. False on failure.
    */
    public function save() {
        $query = "UPDATE " . DB__NEW_DATABASE . ".team  SET ";
        $query .= "teamPositionId = " . intval($this->getTeamPositionId()) . " ";
        $query .= "WHERE teamId = " . intval($this->getTeamId()) . ";";

        //George 2020-07-14. Added/ improved.
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637303273796686593', 'save: Hard DB error', $this->db);
            return false;
        } 
        return true;
        // End Added.
    }
    
    /**
    * Check in DB table Team if an entry already exists.
    *
    * @param constant $inTable is INTABLE_JOB for Job or INTABLE_WORKORDER for workOrder.
    * INTABLE_JOB value is 2, INTABLE_WORKORDER value is 1.
    * @param int $teamPositionId.
    * @param int $companyPersonId.
    * @param int $id. Can be jobId or workOrderId.
    * @return object $result. Null if Query failed. On success object with selected table rows.
    */
    public static function checkDuplicate($inTable, $teamPositionId, $companyPersonId, $id){
        global $db, $logger;
        TeamCompanyPerson::loadDB($db);

        $query  = "SELECT teamPositionId, companyPersonId FROM " . DB__NEW_DATABASE . ".team WHERE ";
        $query .= "inTable = " . intval($inTable) . " "; // This is where we associate to job, rather than workOrder
        $query .= "AND teamPositionId = " . intval($teamPositionId) . " ";
        $query .= "AND companyPersonId = " . intval($companyPersonId) . " ";
        $query .= "AND id =" . intval($id) . " LIMIT 1;";

        $result = $db->query($query);
        
        if (!$result)  {
            $logger->errorDb('637299915533760882', 'error DB: checkDuplicate', $db);
            return null; //Query failed.
        }

        return $result;
    }

    
    /**
    * Make the insertion in DB table Team.
    *
    * @param constant $inTable is INTABLE_JOB for Job or INTABLE_WORKORDER for workOrder.
    * INTABLE_JOB value is 2, INTABLE_WORKORDER value is 1.
    * @param int $teamPositionId.
    * @param int $companyPersonId.
    * @param int $id. Can be jobId or workOrderId.
    * @param string $reason. Not required. An empty string for workOrder.
    * @return bool. False if Query failed. On insertion success is true.
    */
    public static function insertTeam($inTable, $teamPositionId, $companyPersonId, $id, $reason=""){
        global $db, $logger;
        TeamCompanyPerson::loadDB($db);

        $query  = "INSERT INTO " . DB__NEW_DATABASE . ".team (inTable, teamPositionId, companyPersonId, id, reason) VALUES (";
        $query .= intval($inTable) . " "; // This is where we associate to job, rather than workOrder
        $query .= ", " . intval($teamPositionId);
        $query .= ", " . intval($companyPersonId);
        $query .= ", " . intval($id);
        $query .= ", '" . $db->real_escape_string($reason) . "');";

        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb('637299934563196722', 'error DB: insertTeam', $db);
            return false; //Query failed.
        }

        return true; //Insert Ok.
    }


    /**
    * @param integer $teamId: teamId to validate, should be an integer but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the id is a valid teamId, false if not.
    */
    public static function validate($teamId, $unique_error_id=null) {
        global $db, $logger;
        TeamCompanyPerson::loadDB($db);
        
        $ret = false;
        $query = "SELECT teamId FROM " . DB__NEW_DATABASE . ".team WHERE teamId=$teamId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578693912', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    /**
    * @param integer $teamPositionId: teamPositionId to validate, should be an integer but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the id is a valid teamPositionId, false if not.
    */
    public static function validateTeamPositionId($teamPositionId, $unique_error_id=null) {
        global $db, $logger;
        TeamCompanyPerson::loadDB($db);
        
        $ret = false;
        $query = "SELECT teamPositionId FROM " . DB__NEW_DATABASE . ".teamPosition WHERE teamPositionId=$teamPositionId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1600963316', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>