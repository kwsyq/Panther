<?php
/* inc/classes/Job.class.php

[BEGIN MARTIN COMMENT]
TODO:
do validity check on status numbers when setting
check the created date crap so its set/get/saved properly
[END MARTIN COMMENT]

EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the Job table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular job.
* Public functions:
** __construct($id = null, User $user = null)
** setName($val)
** setDescription($val)
** getJobId()
** getCustomerId()
** getNumber()
** getName()
** getRwName()
** getDescription()
** getCreated()
** getCode()
** getLocations(&$errCode = false)
** getWorkOrders(&$errCode = false)
** getTeamPosition($teamPositionId, $onlyOne = true, $onlyActive = false)
** getElements(&$errCode = false)
** getJobStatusName()
** deleteFromTeam($teamId, &$integrityIssues=false)
** deleteFromElement($elementId, &$integrityIssues=false)
** getTeam($active = 0, &$errCode = false)
** addWorkOrder()
** setJobActive($active)
** getJobActive()
** addElement()
** update($val)

** public static functions:
** validate($jobId, $unique_error_id=null)
** validateRwname($rwname, $unique_error_id=null)
** errorToText($errCode)
*/
class Job extends SSSEng {
    // The following correspond exactly to the columns of DB table Job
    // See documentation of that table for further details.
    private $jobId;
    private $customerId;
    private $number;
    const MAX_JOBS_IN_MONTH = 999;
    private $name;
    private $rwname;
    private $description;
    private $jobStatusId;
    private $created;
    private $code;

    // private $elements; // REMOVED 2020-03-02 JM, was used only by a now-removed function.

    // INPUT $id: May be either of the following:
    //  * a jobId from the Job table
    //  * an rwname such as "plan-1686-2-car" (used in URLs) from the Job table
    //    * this is unusual, doesn't have parallel on many classes.
    //  * PRIOR to v.2020-4, allowed an associative array which should contain an element for each columnn
    //    used in the Job table, corresponding to the private variables just above.
    //    This is no longer supported.
    // INPUT $user: User object, typically current user.
    //  NOTE that as of version 2020-2, parent class SSSEng will default this to the
    //  current logged-in user, if there is one. 
    public function __construct($id = null, User $user = null) {
        parent::__construct($user);
        $this->load($id);
    }

    // INPUT $val here is input $id for constructor.
    private function load($val) {
        if (is_numeric($val)) {
            // Read row from DB table Job
            $query = "SELECT j.* ";
            $query .= "FROM " . DB__NEW_DATABASE . ".job j ";
            $query .= "WHERE j.jobId = " . intval($val) . ";";

            $result = $this->db->query($query);

            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.
                        
                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();

                    $this->setJobId($row['jobId']);
                    $this->setCustomerId($row['customerId']);
                    $this->setNumber($row['number']);
                    $this->setName($row['name']);
                    $this->setRwName($row['rwname']);
                    $this->setDescription($row['description']);
                    $this->setJobStatusId($row['jobStatusId']);
                    $this->setCreated($row['created']);
                    $this->setCode($row['code']);
                } else {
                    $this->logger->errorDb('637383688295445656', "Invalid jobId", $this->db);
                }
            } else {
                $this->logger->errorDb('637383687988142079', "Hard DB error", $this->db);
            }
        } else if (is_array($val)) {
            $this->logger->error2('1605742635', "construct/load Job no longer supports passing an array; code used obsolete feature");
        } else {
            // Validation really not needed here. If the value is bad, we just won't find a match.
            $query = "SELECT j.* ";
            $query .= "FROM " . DB__NEW_DATABASE . ".job j ";
            $query .= "WHERE j.number = '" . $this->db->real_escape_string($val) . "';";
            
            $result = $this->db->query($query);

            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used a candidate key, we know there will be exactly one row.
                        
                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();

                    $this->setJobId($row['jobId']);
                    $this->setCustomerId($row['customerId']);
                    $this->setNumber($row['number']);
                    $this->setName($row['name']);
                    $this->setRwName($row['rwname']);
                    $this->setDescription($row['description']);
                    $this->setJobStatusId($row['jobStatusId']);
                    $this->setCreated($row['created']);
                    $this->setCode($row['code']);
                } else {
                    $this->logger->errorDb('637383689607453026', "Invalid input '$val' is neither a jobId (because it is not numeric) nor a rwname", $this->db);
                }
            } else {
                $this->logger->errorDb('637383688852511398', "Hard DB error", $this->db);
            }
        }
    } // END private function load

    // Set primary key
    // INPUT $val: primary key (companyId)
    private function setJobId($val) {
        if ( ($val != null) && (is_numeric($val)) && ($val >=1)) {
            $this->jobId = intval($val);
        } else {
            $this->logger->error2("637383735851197849", "Invalid input for jobId : [$val]" );
        }  
    }

    // Set customerId
    // INPUT $val: foreign key to Customer table; as of 2019-02, only customer is SSS
    private function setCustomerId($val) {
        if (Customer::validate($val)) {
            $this->customerId = intval($val);
        } else {
            $this->logger->error2("637383729043828386", "Invalid input for CustomerId : [$val]" );
        }
    }

    // INPUT $val: Job Number. String, always the letter 's' followed by 7 decimal digits, e.g. "s1906001".
    //  The first two digits represent year, the second two represent month; the other three are sequential
    //  within that. >>>00021 that scales only so far.
    private function setNumber($val) {
        if (!preg_match('/^s[0-9]{7}$/', trim($val))) { // Weed out bad length and bad format.
            $this->logger->error2("637390624799389320", "Invalid input for \$val, not a Job number : $val" );
        } else {
            $this->number = $val;
        }
      
    }

    // Set Job Name
    // INPUT $val: string
    public function setName($val) {
        $val = truncate_for_db($val, 'Job => Name', 75, '637372439600257007');
        $this->name = $val;
    }

    // Set rwname for use in URLs
    // INPUT $val: string. Should be a candidate key, but >>>00016 we do nothing currently
    //  at this level to enforce that.
    // JM 2020-10-22: >>>00001 the remark immediately above is still not addressed. It may be tricky to do, and this may not be the 
    //  place in the code to do it. The only case here that looks tricky to me is when private function load($val) gets an array input.
    //  (1) Do we ever do that? If so, do we do it for a good reason? If not, we can just eliminate that part of private function load.
    //     In the other cases, we know rwname is valid because it came from the DB.
    //  (2) If we can't get rid of that case, then we should validate that it is shaped like an rwname, either there or here. 
    // JM 2020-10-22: This was a public method for no detectable reason (and with a lot of potential
    //  for damage if a bad value was passed in). Changed to be private: cannot be modified for an existing job.
    // Cristi 2020-11-13 x Joe - Agree the method must be private, but why do you think is not possible to change the rwname for existing
    // job? Is not used in any other place except the buildLink method.
    //   * JM 2020-11-13: It's not that it would be deeply wrong in database terms to change it, just that we deliberately do not provide
    //     a UI to do so; we treat this as a permanent identifier, just as we treat the jobId.
    //   ** Cristi 2020-11-24 - the UI where we change the job name, based on actual logic, automaticaly change the rwname too... Line 733.
    // Cristi 2020-11-13: I think is not necessary to do extra-validation on Rwname as is not used in other tables and there are not 
    // known rules for the structure of the string. 
    //   * JM 2020-11-13: I more or less agree, though there is probably some limitation on what characters it can contain; for example, 
    //     I suspect any of '?:/' would be a problem, and probably others I'm not thinking of.
    //   ** Cristi - Line 733 where the jobName is updated, include some limitation of characters. Imedialety after the rwname is regenerated based on the new
    //      jobname.
    private function setRwName($val) {
        $val = truncate_for_db($val, 'Job => RwName', 128, '637372440283636890');
        $this->rwname = $val;
    }

    // Set Job Description
    // INPUT $val: string
    public function setDescription($val) {
        $val = truncate_for_db($val, 'Job => Description', 255, '637372440610873629');
        $this->description = $val;
    }

    // INPUT $val should be valid job status, a foreign key into DB table jobStatus.
    // As of 2020-11, the only valid values are 1 ('Active') and 9 ('Done')
    // Made private 2020-11-18 JM, removed input checking because the class can trust itself.
    private function setJobStatusId($val) {
        $this->jobStatusId = intval($val);
    }

    // $val: DATE. Should be in 'Y-m-d' form.
    private function setCreated($val) {
        $val = truncate_for_db($val, 'Job => Created Date', 32, '637372441296383940');
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$val)) {
            $this->created = $val;
        } else {
            //$this->logger->error2('637372466973645316', "Job => Created Date '$val' is not a valid value.");
            $this->created = NULL; // default in table jobStatus.
        }
    }
    
    // $val: String. Another alternate key, typical value is "J6D5G2W9". These each consist of a 
    //  "J" followed by 7 alphanumerics. Random generated identifier, another way a job can be 
    //  referred to. Idea is to avoid giving away the info encoded in the logic of job.number.
    private function setCode($val) {
        $val = truncate_for_db($val, 'Job => Code', 16, '637372443103419170');
        $this->code = $val;
    }
    
    // RETURN primary key.
    public function getJobId() {
        return $this->jobId;
    }

    // RETURN foreign key to Customer table; as of 2019-02, only customer is SSS
    public function getCustomerId() {
        return $this->customerId;
    }

    // RETURN Job Number: String, always the letter 's' followed by 7 decimal digits, e.g. "s1906001".
    //  The first two digits represent year, the second two represent month; the other three are sequential
    //  with in that. >>>00021 that scales only so far.
    public function getNumber() {
        return $this->number;
    }

    // RETURN job name (string)
    public function getName() {
        return $this->name;
    }

    // RETURN rwname (for use in URLs)
    public function getRwName() {
        return $this->rwname;
    }

    // RETURN job description (string)
    public function getDescription() {
        return $this->description;
    }

    // RETURN job status, a foreign key into DB table jobStatus..
    // As of 2019-02, the only possible values are STATUS_TASK_ACTIVE=1 and STATUS_TASK_DONE=9; Note that those names
    //  relate to TASK, not JOB.
    private function getJobStatusId() {
        return $this->jobStatusId;
    }

    // RETURN date this job was created, in 'Y-m-d' form.
    public function getCreated() {
        return $this->created;
    }
    
    // RETURN string, typical value is "J6D5G2W9". More precisely, a "J" followed 
    //  by 7 alphanumerics. Random generated identifier, another way a job can be 
    //  referred to. Idea is to avoid giving away the info encoded in the logic of job.number.
    public function getCode() {
        return $this->code;
    }
    


    /**
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $ret. RETURN an array of Location objects, 
        * corresponding to rows in the jobLocation DB table that match this jobId.
        *  JM 2020-05-11: http://bt.dev2.ssseng.com/view.php?id=153 means that there can now only be one such location, so there
        *  are either 0 or 1 row(s) in the return.
    */
    public function getLocations(&$errCode = false) {
        $errCode=false;
        $ret = array();

        /* BEGIN REPLACED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
        $query  = " select locationId ";
        $query .= " from " . DB__NEW_DATABASE . ".jobLocation jl ";
        $query .= " where jl.jobId = " . intval($this->getJobId()) . " ";
        $query .= " order by jl.jobLocationId ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $ret[] = new Location($row['locationId']);
                }
            }
        }
        // END MARKED FOR REPLACED JM 2020-05-11
        */
        // BEGIN REPLACEMENT JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
        $query  = "SELECT locationId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".job ";
        $query .= "WHERE jobId = " . intval($this->getJobId()) . ";";

        $result = $this->db->query($query);
        
        if (!$result) { // George 2020-08-24. Rewrite "if" statement.
            $this->logger->errorDB('637338749748936266', "Hard DB error", $this->db);
            $errCode = true;
        } else if ($row = $result->fetch_assoc()) {
            $ret[] = new Location($row['locationId']);
        }

        // END REPLACEMENT JM 2020-05-11

        return $ret;
    }


    /**
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $workorders. RETURNs an array of WorkOrder objects, corresponding to rows in the workOrder DB table that match this jobId.
        * Ordered by workOrderId, which means chronologically by when workOrders were created.
    */
    public function getWorkOrders(&$errCode = false) {
        $workorders = array();
        $errCode=false;

        $query  = "SELECT wo.* ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
        $query .= "WHERE wo.jobId = " . intval($this->getJobId()) . " ";
        $query .= "ORDER BY wo.workOrderId;";

        $result = $this->db->query($query);

        if (!$result) { // George 2020-08-24. Rewrite "if" statement.
            $this->logger->errorDB('637338759093892384', "Hard DB error", $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $workorder = new WorkOrder($row, $this->user);
                $workorders[] = $workorder;
            }
        }

        return $workorders;
    }


    /**
    * @param int $teamPositionId: specified $teamPositionId. These can be found in inc/config.php, e.g.
    *   TEAM_POS_ID_CLIENT=1, TEAM_POS_ID_DESIGN_PRO=2.
    * @param bool $onlyOne: Quasi-Boolean. If true and there is more than one match, then the return will be an empty array.
    * @param bool $onlyActive: Quasi-Boolean. If true, then query will be limited to rows with active = 1. 
    * @return array $positions. For this job & the specified teamPosition, return the appropriate rows from 
    *  DB table team, each as an associative array.
    */
    public function getTeamPosition($teamPositionId, $onlyOne = true, $onlyActive = false) {
        $positions = array();

        $query = "SELECT * from " . DB__NEW_DATABASE . ".team ";
        $query .= "WHERE id = " . intval($this->getJobId()) . " ";
        $query .= "AND inTable = " . intval(INTABLE_JOB) . " ";
        $query .= "AND teamPositionId = " . intval($teamPositionId);
        if ($onlyActive) {
            $query .= " AND active = 1";
        }
        $query .= ";";
        $result = $this->db->query($query);

        if (!$result)  {
            $this->logger->errorDb('637376661094120531', 'error DB: getTeamPosition()', $this->db);
        } else {
            while ($row = $result->fetch_assoc()) {
                $positions[] = $row;
            }
        }
        
        if ($onlyOne && (count($positions) > 1)) {
            // [BEGIN MARTIN COMMENT]
            // this is because we only wanted one here
            // and theres more than one
            // so something needs to be "fixed up" in the workorder
            // by removing the extra people in that position
            // [END MARTIN COMMENT]
            return array();
        }
        
        return $positions;
    }

    // RETURNs an array of Element objects, corresponding to the rows in DB table element that match this jobId. 
    //  Order is arbitrary. 

    /**
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $elements. RETURNs an array of Element objects, 
        * corresponding to the rows in DB table element that match this jobId,
        * Order is arbitrary. 
    */
    public function getElements($workOrderId = null, &$errCode = false) {
        // [BEGIN MARTIN COMMENT]
        // see old version of method above.
        // probably dont need to store this on the instance
        // [END MARTIN COMMENT]
        $errCode=false;
        $elements = array();
        if($workOrderId == null) {
            $query = "SELECT * ";
            $query .= "FROM " . DB__NEW_DATABASE . ".element ";
            $query .= "WHERE jobId = " . intval($this->getJobId()) . " AND workOrderId IS NULL ";
        } else {
            $query = "SELECT * ";
            $query .= "FROM " . DB__NEW_DATABASE . ".element ";
            $query .= "WHERE jobId = " . intval($this->getJobId()) . " AND workOrderId = " . intval($workOrderId) . "";
        }
       
    

        $result = $this->db->query($query); // George 2020-08-24. Rewrite "if" statement.
        if (!$result){ 
            $this->logger->errorDb('637338839107547524', 'getElements: Hard DB error', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $elements[] = new Element($row);
            }
        }
        
        return $elements;
    } // END public function getElements

    // RETURN jobStatusName corresponding to jobStatus Id of this job. 
    public function getJobStatusName() {
        // $query  = " select * "; // REPLACED 2020-03-02 JM: just use "select jobStatusName", that's all we access.
        $query = " select jobStatusName "; // REPLACEMENT 2020-03-02 JM  
        $query .= " from " . DB__NEW_DATABASE . ".jobStatus ";
        $query .= " where jobStatusId = " . intval($this->getJobStatusId()) . " ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                /* BEGIN REPLACED 2020-03-02 JM: "while" is really a bit confusing here
                while ($row = $result->fetch_assoc()) {
                    return $row['jobStatusName'];
                }
                // END REPLACED 2020-03-02 JM
                */
                // BEGIN REPLACEMENT 2020-03-02 JM
                return $result->fetch_assoc()['jobStatusName'];
                // END REPLACEMENT 2020-03-02 JM
            }
        }

        return false;
    }

    /**
        * Deletes specified row from DB table team. Only has effect if row indicates that it was for this job. 
        * @param int $teamId. Primary key in table team.
        * @param bool $integrityIssues, variable pass by reference. Default value is false.
        * $integrityIssues is True on query failed.
        * @return bool true on succes.
    */

    public function deleteFromTeam($teamId, &$integrityIssues=false) {

        $integrityIssues=false;
        // Start with an integrity test in order to check if no links in other tables are still existing
        $integrityTest = canDelete('team', 'teamId', $teamId);

        if (!$integrityTest) {
            $integrityIssues = true; 
            $this->logger->warn2('637336136003974401', 'deleteFromTeam: Delete entry not possible! At least one reference to this row exists in the database, violation of database integrity.');
            return true;
        } 
        // if True, No reference to the primary key of this row is found in the database.
        $query = "DELETE  ";
        $query .= "FROM " . DB__NEW_DATABASE . ".team ";
        $query .= "WHERE intable  = " . intval(INTABLE_JOB) . " ";
        $query .= "AND id = " . intval($this->getJobId()) . " ";
        $query .= "AND teamId = " . intval($teamId) . ";";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDB('637335382299537978', "Hard DB error", $this->db);
            return false;
        } else {
            $this->logger->info2('637371492879214257', "deleteFromTeam => action success. TeamId: ". $teamId);
        }
        return true; //Delete success.

    }


    /**
        * Deletes specified row from DB table element. Only has effect if row indicates that it was for this job. 
        * @param int $elementId. Primary key in table element.
        * @param bool $integrityIssues, variable pass by reference. Default value is false.
        * $integrityIssues is True on query failed.
        * @return bool true on succes.
    */

    public function deleteFromElement($elementId, &$integrityIssues=false) {

        $integrityIssues=false;
        // Start with an integrity test in order to check if no links in other tables are still existing
        $integrityTest = canDelete('element', 'elementId', $elementId);

        if (!$integrityTest) {
            $integrityIssues = true; 
            $this->logger->warn2('637689512050942181', 'deleteFromElement: Delete entry not possible! At least one reference to this row exists in the database, violation of database integrity.');
            return true;
        } else {
            // if True, No reference to the primary key of this row is found in the database.
            $query = "DELETE  ";
            $query .= "FROM " . DB__NEW_DATABASE . ".element ";
            $query .= "WHERE jobId = " . intval($this->getJobId()) . " ";
            $query .= "AND elementId = " . intval($elementId) . ";";

            $result = $this->db->query($query);

            if (!$result) {
                $this->logger->errorDB('637689511341304373', "Hard DB error", $this->db);
                return false;
            } else {
                $this->logger->info2('637689511431052222', "deleteFromElement => action success. ElementId: ". $elementId);
            }
            return true; //Delete success.
        }


    }
    /*  INPUT $active: Quasi-Boolean. If nonzero, then this is limited to team.active=1, that is, active members of the team.
        @param bool $errCode, variable pass by reference. Default value is false. $errCode is True on query failed.
        RETURNs an array of associative arrays, each of which describes a member of the team  Content of each such associative array is:
            * 'inTable': always intval(INTABLE_JOB)
            * 'companyPersonId'
            * 'position': team.role
            * 'description': team.description
            * 'active': team.active
            * 'teamId': team.teamId
            * 'teamPositionId'
            * 'personId'
            * 'firstName': person.firstName
            * 'lastName': person.lastName
            * 'companyName': company.companyName
            * 'name': teamPosition.name
            * 'tpdescription': teamPosition.description
    */ 
    public function getTeam($active = 0, &$errCode=false) {
        $errCode=false;
        $team = array();

        $query = " select t.inTable,t.companyPersonId,t.role as position,t.description,t.active,t.teamId,tp.teamPositionId  ";
        $query .= " ,p.personId  ";
        $query .= " ,p.firstName  ";
        $query .= " ,p.lastName ";
        $query .= " ,c.companyName  ";
        $query .= " ,tp.name  ";
        $query .= " ,tp.description as tpdescription  ";
        $query .= " from " . DB__NEW_DATABASE . ".team t ";
        $query .= " join " . DB__NEW_DATABASE . ".companyPerson cp on t.companyPersonId = cp.companyPersonId ";
        $query .= " left join " . DB__NEW_DATABASE . ".teamPosition tp on t.teamPositionId = tp.teamPositionId ";
        $query .= " join " . DB__NEW_DATABASE . ".person p on cp.personId = p.personId ";
        $query .= " join " . DB__NEW_DATABASE . ".company c on cp.companyId = c.companyId ";

        // [BEGIN MARTIN COMMENT]
        // workorderId will probably be different things
        // depending on what the inTable is
        // or at least i think that was my original thought :)
        // [END MARTIN COMMENT]

        $query .= " where t.id = " . intval($this->getJobId()) . " ";
        $query .= " and t.inTable = " . intval(INTABLE_JOB) . " ";

        if (intval($active)) {
            $query .= " and t.active = 1 ";
        }

        $result = $this->db->query($query); // George 2020-08-24. Rewrite "if" statement.
        if (!$result) {
            $this->logger->errorDb('637338848626251417', 'getTeam: Hard DB error', $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $team[] = $row;
            }
        }
 
        return $team;
    } // END public function getTeam

    // Creates a new workOrder for this job. 
    // RETURNs workOrderId on success, false on failure.
    // Heavily reworked 2020-05-28 JM
    public function addWorkOrder() {
        $ok = false;
        $code = '';         
        while (!$ok) {
            $code = 'W' . generateCodeJobAndWorkOrder(7);
            
            $query = "SELECT workOrderId ";
            $query .= "FROM " . DB__NEW_DATABASE . ".workOrder ";
            $query .= "WHERE code = '" . $this->db->real_escape_string($code) . "';";
            $ok = true;
            
            $result = $this->db->query($query);        
            if ($result) {
                $ok = $result->num_rows == 0; // OK if no prior workOrder has this code. 
            } else {
                $this->logger->errorDb('1590707932', 'Hard DB error', $this->db);
                return false;
            }
        }
        
        // BEGIN ADDED 2020-06-11 JM
        $initialStatusId = WorkOrder::getInitialStatusId();
        if ($initialStatusId === false) {
            $this->logger->error2('1591906843', 'Can\'t set $initialStatusId'); // lower-level error should already be logged
            return false;
        }
        // END ADDED 2020-06-11 JM
        
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrder (jobId, genesisDate, workOrderStatusId, code) values (";
        $query .= intval($this->getJobId()) . " ";
        $query .= ", now() ";                                               //  genesisDate: now
        // $query .= ", " . intval(STATUS_WORKORDER_NONE) . " ";            //  workOrderStatusId: STATUS_WORKORDER_NONE // REPLACED 2020-06-11 JM
        $query .= ", " . intval($initialStatusId) . " ";             //  workOrderStatusId: the unique workOrderStatus with isInitialStatus==1 // REPLACEMENT 2020-06-11 JM 
        $query .= ", '" . $this->db->real_escape_string($code) . "') ";     //  code: just now generated on the fly, 'W' followed by 7 digits.

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('15907079552', 'Hard DB error', $this->db);
            return false;
        }

        $workOrderId = $this->db->insert_id;

        if (intval($workOrderId)) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderStatusTime (";
            $query .= "workOrderStatusId, workOrderId, personId, note) VALUES (";
            //$query .= intval(STATUS_WORKORDER_NONE);  // REPLACED 2020-06-11 JM
            $query .= intval($initialStatusId); // REPLACEMENT 2020-06-11 JM
            $query .= ", " . $workOrderId;
            $query .= ", " . $this->user->getUserId();
            $query .= ", 'automated initial creation'"; 
            $query .= ");";
            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDb('15907079567', 'Hard DB error', $this->db);
                // But let it continue: lack of overt status should not be a reason to count this as a failure.
            }
            
            // BEGIN ADDED 2020-11-18 JM
            // New workOrder necessarily makes this job active.
            $this->setJobActive(true);
            // END ADDED 2020-11-18 JM
            
            return $workOrderId;
        }
        
        return false;
    } // END public function addWorkOrder
    
    // INPUT $active - true means set it active, false means set it inactive.
    // method added 2020-11-18 JM
    public function setJobActive($active) {
        // We rely here on the only JobStatus values being 'Active' and 'Done'. If others were introduced, or those names were changed, 
        // this would need a rewrite.
        
        $jobStatusName = $active ? 'Active' : 'Done';
        
        $query = "SELECT jobStatusId FROM " . DB__NEW_DATABASE . ".jobStatus ";
        $query .= "WHERE jobStatusName = '$jobStatusName';";
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1605734780', 'Hard DB error', $this->db);
            return; // hopeless
        }
        $row = $result->fetch_assoc();
        if (!$row) {
            $this->logger->errorDb('1605734801', 'Couldn\'t find jobStatus we would expect always to find', $this->db);
            return; // hopeless
        }
        
        $this->setJobStatusId($row['jobStatusId']);
        $this->save();
    }

    // RETURN: true means active, false means inactive.
    // method added 2020-11-18 JM
    public function getJobActive() {
        $query = "SELECT jobStatusName FROM " . DB__NEW_DATABASE . ".jobStatus ";
        $query .= "WHERE jobStatusId = $this->jobStatusId;";
        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1605736535', 'Hard DB error', $this->db);
            return false; // hopeless
        }
        $row = $result->fetch_assoc();
        if (!$row) {
            $this->logger->errorDb('1605734801', "Invalid jobStatusId {$this->jobStatusId} in DB table job row {$this->jobId}", $this->db);
            return false; // hopeless
        }
        
        // We rely here on the only JobStatus values being 'Active' and 'Done'. If others were introduced, or those names were changed, 
        // this would need a rewrite.
        return $row['jobStatusName'] == 'Active'; 
    }

    // 
    /**
    * Create a new element for this job. Add Element Name based on a valid elementId.
    *  Returns true on success, the return of $element->save(). False on failure.
    * @param string INPUT $elementName.
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed. And in this particular case if elementId is Invalid.
    * 
    */
    public function addElement($elementName, &$errCode=false) {
        $errCode = false;
        /* BEGIN REPLACED 2020-04-30 JM: killing code for an old migration.
        $query = "insert into " . DB__NEW_DATABASE . ".element (jobId, migration, retired) values (";
        $query .= " " . intval($this->getJobId()) . " ";
        $query .= " ,1,0) ";
        // [MARTIN COMMENT] just set the migration flag so it doesnt get confused (only really useful during pre-launch testing)
        // END REPLACED 2020-04-30 JM: killing code for an old migration.
        */
        // BEGIN REPLACEMENT 2020-04-30 JM: killing code for an old migration.

        $elementNameOK = Element::generateUniqueElementName($elementName, intval($this->getJobId()), $errCode);

        if($errCode){
            $this->logger->error2('637326624251156080', "Element add not possible. element Name: [$elementName] jobId: [".$this->getJobId()."]");
            return false;
        }

        $query = "INSERT INTO " . DB__NEW_DATABASE . ".element (jobId, elementName) VALUES (";
        $query .= intval($this->getJobId()).", ";
        $query .= "'" . $this->db->real_escape_string($elementNameOK) ."'";
        $query .= ")";

        $result = $this->db->query($query);
        
        if (!$result) {
            $this->logger->errorDB('637326624251156078', "addElement => Hard DB error", $this->db);
            $errCode=true;            
            return false;
        }
        return true;
    }

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getJobId();
    }


    /**
    * Update several values for this Job.
    * @param array $val, this INPUT typically comes from $_REQUEST.
    *   An associative array containing the following elements
    *   'name' (Job name).
    *   'description'
    *   REMOVED 2020-11-18 JM: 'jobStatusId'
    * Any or all of these may be present.
    * @return boolean true on success, the return of $this->save(). False on failure.
    */
    public function update($val) {
        if (!is_array($val)) {
            $this->logger->error2('637335286961720605', 'update Job => expected array as input, got something not an array');
            return false;
        }
      
        if (isset($val['description'])) {            
            $this->setDescription($val['description']);       
        }

        /* BEGIN REMOVED 2020-11-18 JM: do this through setJobActive
        if (isset($val['jobStatusId'])) {            
            $this->setjobStatusId($val['jobStatusId']); 
        }
        // END REMOVED 2020-11-18 JM
        */

        if (isset($val['name']) && !empty($val['name'])) {
            // Not empty. Job name must be set and must contain at least one alphabetic character.
            if (preg_match('/[a-zA-Z]/', $val['name'])) {
                $this->setName($val['name']);
                $name = $this->getName();
    
                $ok = false;
                $count = 0;
    
                // http://bt.dev2.ssseng.com/view.php?id=179: JM 2020-07-09. Beyond the obvious bug, the following "while" loop was a wreck.
                // In fixing this, I (JM) am not making any effort to preserve the old code, just rewriting it to do what it should.
                // Look at the SVN repository if you need the history of how it got here
                $rw = $this->makeRwName($name);
                while (!$ok) {
                    // name is passed to private method makeRwName to build a canonical rwname. 
                    // We then make sure it doesn't conflict with the rwname for any other job 
                    //  (and, if necessary, append a count to its end so it doesn't).  
                    $try_rw_name = $rw;
                    if ($count > 0) {
                        $try_rw_name .= '-' . $count;
                    } 
                    
                    $query = "SELECT jobId ";
                    $query .= "FROM " . DB__NEW_DATABASE . ".job "; 
                    $query .= "WHERE rwname = '" . $this->db->real_escape_string($try_rw_name) . "' ";
                    $query .= "AND jobId != " . intval($this->getJobId()) . ";";
    
                    $result = $this->db->query($query);
                    if (!$result) {
                        $this->logger->errorDb('1594322195', "Hard DB error", $this->db);
                        return false; // Hard error, give up.
                    }
                    
                    if ($result->num_rows == 0) {
                        $this->setRwName($try_rw_name);
                        $ok = true;
                    } else {                    
                        $count++;
                    }
                }
            } else {
                $this->logger->warn2('637336032247247490', 'Invalid Job name, exiting!');
                return false;
            }
        }

        return $this->save();
        
    } // END public function update

    // NOTE that it's unusual that this is private; means that anything that uses
    //  a public set function must do something like call public function update
    //  with an empty array to trigger the actual save.
    private function save() {
        // http://bt.dev2.ssseng.com/view.php?id=179: JM 2020-07-09. ADDED code to save rwname (instead of
        //  handling it separately in public function update), cleaned up the SQL here, and sdded check for error.
        $query = "UPDATE " . DB__NEW_DATABASE . ".job SET ";
        $query .= "name = '" . $this->db->real_escape_string($this->getName()) . "'";
        $query .= ", description = '" . $this->db->real_escape_string($this->getDescription()) . "'";
        $query .= ", jobStatusId = " . intval($this->getJobStatusId()) . " ";
        $query .= ", rwname = '" . $this->db->real_escape_string($this->getRwName()) . "' ";
        $query .= "WHERE jobId = " . intval($this->getJobId()) . ";";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1594322834', "Hard DB error", $this->db);
            return false;
            // already at end of funciton, so no point to bailing out.
        }

        return true;
    }

    // Build canonical rwname for a given job name
    // INPUT $name: job name
    private function makeRwName($name) {
        $name = preg_replace("/[^0-9A-Za-z]/", "-", $name);
        $pos = false;

        for ($i = 0; $i < 10; ++$i) {
            $name = str_replace("--", "-", $name);
        }

        if (substr($name, 0, 1) == "-") {
            $name = substr($name, 1);
        }
        if (substr($name, -1) == "-") {
            $name = substr($name, 0, (strlen($name) - 1));
        }

        $name = trim($name);
        
        // BEGIN added for http://bt.dev2.ssseng.com/view.php?id=179: JM 2020-07-09
        if (strlen($name) == 0) {
            $name = 'unnamed-job';
        }
        // END added for http://bt.dev2.ssseng.com/view.php?id=179: JM 2020-07-09
        
        return strtolower($name);
    }

    // Despite the singular in the function name, RETURNs an array of CompanyPerson objects, 
    //  where each person is a member of the team for this job, and teamPositionId = TEAM_POS_ID_DESIGN_PRO.
    //  Considers only active members of the team.
    public function getDesignProfessional() {
        $team = $this->getTeam(1);
        $designpros = array();
        foreach ($team as $person) {
            if ($person['teamPositionId'] == TEAM_POS_ID_DESIGN_PRO) {
                $designpros[] = new CompanyPerson($person['companyPersonId']);
            }
        }
        return $designpros;
    }

    // Despite the singular in the function name, RETURNs an array of CompanyPerson objects, 
    //  where each person is a member of the team for this job, and teamPositionId = TEAM_POS_ID_CLIENT.
    //  Considers only active members of the team.
    public function getClient() {
        $team = $this->getTeam(1);
        $clients = array();
        foreach ($team as $person) {
            if ($person['teamPositionId'] == TEAM_POS_ID_CLIENT) {
                $clients[] = new CompanyPerson($person['companyPersonId']);
            }
        }
        return $clients;
    }

    
    /**
    * @param string $rwname: rwname to validate, should be an string but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the rwname is a valid Rwname, false if not.
    */
    public static function validateRwname($rwname, $unique_error_id=null) {
        global $db, $logger;
        Job::loadDB($db);
        $ret = false;

        $query = "SELECT rwname FROM " . DB__NEW_DATABASE . ".job WHERE rwname='" . $db->real_escape_string($rwname) . "'";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '637372256990399776', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    public static function validateNumber($number, $unique_error_id=null) {
        global $db, $logger;
        Job::loadDB($db);
        $ret = false;

        $query = "SELECT rwname FROM " . DB__NEW_DATABASE . ".job WHERE number='" . $db->real_escape_string($number) . "'";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '637372256990399776', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
    
    /**
    * @param integer $jobId: jobId to validate, should be an integer but we will coerce it if not.
    * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
    * @return true if the id is a valid jobId, false if not.
    */
    public static function validate($jobId, $unique_error_id=null) {
        global $db, $logger;
        Job::loadDB($db);
        $ret = false;
     
        $query = "SELECT jobId FROM " . DB__NEW_DATABASE . ".job WHERE jobId=$jobId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578691934', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }

    public static function errorToText($errCode) {
        $error = '';
        $errorId = 0;
    
        if($errCode == 0) {
            $errorId = '637173048386083828';
            $error = 'addJob method failed.';
        } else if($errCode == DB_EXECUTION_ERR) {
            $errorId = '637173048817514681';
            $error = 'Database error.';
        } else if($errCode == NOT_AVAILABLE_VALUE) {
            $errorId = '637173049241394414';
            $error = "The number of Jobs added this month exceeded the maximum number: " . Job::MAX_JOBS_IN_MONTH;
        } else {
            // this point should not be reached.
            $error = "Unknown error, with error code: ". $errCode .", please try again";
            $errorId = "637173062468782030";
        }
    
        return array($error, $errorId);
    }
}

?>
