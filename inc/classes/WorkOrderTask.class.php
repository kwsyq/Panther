<?php 
/* inc/classes/WorkOrderTask.class.php
EXECUTIVE SUMMARY: 
One of the many classes that essentially wraps a DB table, in this case the WorkOrderTask table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular workOrderTask.
* Public functions:
** __construct($id = null, User $user = null)
** setExtraDescription($val)
** public static function getInvoiceStatusDataArray()
** getWorkOrderTaskId()
** getWorkOrderId()
** getTaskId()
** getTaskStatusId()
** getExtraDescription()
** getInserted() // Added by JM for v2020-3
** getInsertedPersonId() // Added by JM for v2020-3
** getTask()
** getWorkOrderTaskElements()
** getWorkOrderTaskPersons()
** addElementIds($elementIds)
** addPersonIds($personIds)
** getWorkOrderTaskTime()
** getWorkOrderTaskTimeWithRates($customer = null)
** getTally() // ADDED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100
** update($val)
** save() 
** toArray()

** public static function validate($workOrderTaskId, $unique_error_id=null)
*/

class WorkOrderTask extends SSSEng {
    // The following correspond exactly to columns of DB table WorkOrderTask, though
    //  most columns do not have a variable here
    // See documentation of that table for further details.
    private $workOrderTaskId;
    private $workOrderId;
    private $taskId;
    private $taskStatusId;
    // private $viewMode; // REMOVED 2020-10-28 JM getting rid of viewmode
    private $extraDescription;
    private $inserted; // Added by JM for v2020-3: when inserted
    private $insertedPersonId; // Added by JM for v2020-3: who inserted it
    // Not included here, for less obvious reasons
    // * billingDescription
    // * cost
    // * quantity
    
    private $task; // Task object
    
    // INPUT $id: May be either of the following:
    //  * a workOrderIdTask from the WorkOrderTask table
    //  * an associative array which should contain an element for each columnn
    //    used in the WorkOrderTask table, corresponding to the private variables
    //    just above. 
    //  >>>00016: JM 2019-03-04: should certainly validate this input, doesn't.
    // INPUT $user: User object, typically current user. 
    //  >>>00023: JM 2019-03-04: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI). 
    public function __construct($id = null, User $user = null) {	
        parent::__construct($user);
        $this->load($id);
    }	
    
    // INPUT $val here is input $id for constructor.
    // NOTE that besides what we usually do in a "load" function, this also sets $this->task.
    private function load($val) {

        if (is_numeric($val)) {
            $query = " SELECT wot.* ";
            $query .= " ,t.icon,t.description,t.taskTypeId,tt.typeName ";
            $query .= " from " . DB__NEW_DATABASE . ".workOrderTask wot  ";
            $query .= " join " . DB__NEW_DATABASE . ".task t on wot.taskId = t.taskId ";
            $query .= " left join " . DB__NEW_DATABASE . ".taskType tt on t.taskTypeId = tt.taskTypeId ";
            $query .= " where workOrderTaskId = " . intval($val);

            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {					
                    // Since query used primary key, we know there will be exactly one row.
                        
                    // Set all of the private members that represent the DB content; alos set $this->task 
                    $row = $result->fetch_assoc();
                    
                    $this->setWorkOrderTaskId($row['workOrderTaskId']);	
                    $this->setWorkOrderId($row['workOrderId']);
                    $this->setTaskId($row['taskId']);
                    $this->setTaskStatusId($row['taskStatusId']);
                    $this->setExtraDescription($row['extraDescription']);
                    $this->setBillingDescription($row['billingDescription']);
                    $this->setTaskContractStatus($row['taskContractStatus']);
                    // $this->setViewMode($row['viewMode']); // REMOVED 2020-10-28 JM getting rid of viewmode
                    $this->inserted = $row['inserted']; 
                    $this->insertedPersonId = $row['insertedPersonId'];
                    $this->task = new Task($row['taskId']);
                } // >>>00002 else ignores that we got a bad workOrderTaskId!
            } // >>>00002 else ignores failure on DB query! Does this throughout file, 
              // haven't noted each instance.
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from 
            //  input associative array
            $this->setWorkOrderTaskId($val['workOrderTaskId']);
            $this->setWorkOrderId($val['workOrderId']);
            $this->setTaskId($val['taskId']);
            $this->setTaskStatusId($val['taskStatusId']);			
            $this->setExtraDescription($val['extraDescription']);
            $this->setBillingDescription($row['billingDescription']);
            // $this->setViewMode($val['viewMode']); // REMOVED 2020-10-28 JM getting rid of viewmode
            $this->setTaskContractStatus($row['taskContractStatus']);
            $this->inserted = $val['inserted']; 
            $this->insertedPersonId = $val['insertedPersonId'];
           
            $this->task = new Task($val['taskId']);
        }
    } // END private function load
    
    // Set primary key
    // INPUT $val - primary key workOrderTaskId 
    private function setWorkOrderTaskId($val) {
        $this->workOrderTaskId = intval($val);
    }
    
    // Set foreign key into DB table WorkOrder
    // INPUT $val - foreign key into DB table WorkOrder
    private function setWorkOrderId($val) {
        $this->workOrderId = intval($val);
    }
    
    // Set foreign key into DB table Task
    // INPUT $val - foreign key into DB table Task
    private function setTaskId($val) {
        $this->taskId = intval($val);
    }
    
    // Set foreign key into DB table TaskStatus
    // INPUT $val - foreign key into DB table TaskStatus
    private function setTaskStatusId($val) {
        $this->taskStatusId = intval($val);
    }    

    /* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
    // Set viewMode
    // INPUT $val - a bit-string:
    //  1 - WOT_VIEWMODE_CONTRACT
    //  2 - WOT_VIEWMODE_TIMESHEET
    //  so 3=> both.
    // 00016: Maybe more validation?
    private function setViewMode($val) {
        $this->viewMode = intval($val);
    }
    // END REMOVED 2020-10-28 JM
    */
    
    // Set extraDescription
    // INPUT $val - string
    public function setExtraDescription($val) {  
        $val = trim($val);
        $val = substr($val, 0, 255); // >>>00002 truncates silently
        $this->extraDescription = $val;
    }


    // Set BillingDescription
    // INPUT $val - string
    public function setBillingDescription($val) {  
        $val = trim($val);
        $val = substr($val, 0, 255); // >>>00002 truncates silently
        $this->billingDescription = $val;
    }

    // hide/ show on contract/ invoice
    private function setTaskContractStatus($val) {
        $this->taskContractStatus = intval($val);
    }    

    /* BEGIN Martin comment
    
create table workOrderTaskViewOptions(
    workOrderTaskViewOptionsId   int unsigned not null primary key,
    taskViewOptionName           varchar(32) not null unique,
    taskViewDisplayName          varchar(32) not null,
    inserted                     timestamp not null default now()
);

insert into workOrderTaskViewOptions(workOrderTaskViewOptionsId,taskViewOptionName,taskViewDisplayName) values (1, 'VIEW_CONTRACT','View in Contract');
insert into workOrderTaskViewOptions(workOrderTaskViewOptionsId,taskViewOptionName,taskViewDisplayName) values (2, 'VIEW_INVOICE','View in Invoice');
insert into workOrderTaskViewOptions(workOrderTaskViewOptionsId,taskViewOptionName,taskViewDisplayName) values (4, 'TOTAL_CONTRACT','Show Total in Contract');
insert into workOrderTaskViewOptions(workOrderTaskViewOptionsId,taskViewOptionName,taskViewDisplayName) values (8, 'TOTAL_INVOICE','Show Total in Invoice');

    END Martin comment
    */
    
    // RETURN an associative array indexed by the uniqueNames of top-level (no parent) 
    //  invoice statuses.
    // PRIOR TO v2020-3, this was a DUPLICATE of Invoice::getInvoiceStatusDataArray. Now it simply calls that.
    // See inc/classes/Invoice.php for further documentation.
    //
    public static function getInvoiceStatusDataArray() {
        return Invoice::getInvoiceStatusDataArray();
    } // END public static function getInvoiceStatusDataArray
    
    // RETURN primary key
    public function getWorkOrderTaskId() {
        return $this->workOrderTaskId;
    }
    // RETURN foreign key into WorkOrder table. 
    public function getWorkOrderId() {
        return $this->workOrderId;
    }
    // RETURN foreign key into Task table.
    public function getTaskId() {
        return $this->taskId;
    }
    // RETURN foreign key into TaskStatus table.
    public function getTaskStatusId() {
        return $this->taskStatusId;
    }
    // RETURN extraDescription string
    public function getExtraDescription() {
        return $this->extraDescription;
    }

    // RETURN billingDescription string
    public function getBillingDescription() {
        return $this->billingDescription;
    }

    // RETURN taskContractStatus
    public function getTaskContractStatus() {
        return $this->taskContractStatus;
    }
    // BEGIN Added by JM for v2020-3
    public function getInserted() {
        return $this->inserted;
    }
    // END Added by JM for v2020-3
    // BEGIN Added by JM for v2020-3
    public function getInsertedPersonId() {
        return $this->insertedPersonId;
    }
    // END Added by JM for v2020-3

    /* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
    // RETURN viewMode bit-string:
    //  1 - WOT_VIEWMODE_CONTRACT
    //  2 - WOT_VIEWMODE_TIMESHEET
    //  so 3=> both.
    public function getViewMode() {
        return $this->viewMode;
    }
    // END REMOVED 2020-10-28 JM
    */
    
    // RETURN Task object
    public function getTask() {
        return $this->task;
    }
    
    // RETURNs an array of associative arrays, with each item in the top-level 
    //  array corresponding to a task element that is associated with this 
    //  WorkOrderTask by DB table workOrderTaskElement. The content of each associative array unions 
    //  every column of DB table workOrderTaskElement with 'jobId' and 'elementName' 
    //  values from DB table element. 
    public function getWorkOrderTaskElements() {
        /* BEGIN REPLACED 2020-07-10 JM, thought I'd just be doing cleanup but this was failing silently! there is no e.workOrderId.
        $elements = array();
        
        //$query = " select wote.*, e.jobId, e.elementName, e.workOrderId, e.retired, e.migration "; // REMOVED 2020-04-30 JM: killing code for an old migration.
        $query = " select wote.*, e.jobId, e.elementName, e.workOrderId "; // ADDED 2020-04-30 JM: killing code for an old migration.
        $query .= " from " . DB__NEW_DATABASE . ".workOrderTaskElement  wote ";
        $query .= " join " . DB__NEW_DATABASE . ".element e on wote.elementId = e.elementId ";
        $query .= " where wote.workOrderTaskId = " . intval($this->getWorkOrderTaskId());
        // $query .= " and e.retired != 1 ";  // REMOVED 2020-04-30 JM: killing code for an old migration.

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {					
                while($row = $result->fetch_assoc()) {						
                    $elements[] = new Element($row);						
                }		
            }				
        }
        
        return $elements;
        // END REPLACED 2020-07-10 JM
        */
        // BEGIN REPLACEMENT 2020-07-10 JM
        $elements = array();
        
        $query = "SELECT wote.*, e.jobId, e.elementName, wot.workOrderId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderTaskElement wote ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".element e ON wote.elementId = e.elementId ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTask wot ON wote.workOrderTaskId = wot.workOrderTaskId ";
        $query .= "WHERE wote.workOrderTaskId = " . intval($this->getWorkOrderTaskId()) . " ";
        $query .= "ORDER BY wote.elementId;"; // ORDER BY added 2020-09-08
        $result = $this->db->query($query);
        if ($result) {  
            while($row = $result->fetch_assoc()) {	
                $elements[] = new Element($row);						
            }				
        } else {
            $this->logger->ErrorDb('1594414521', "Hard DB error", $this->db); 
        }
        
        return $elements;
        // END REPLACEMENT 2020-07-10 JM
    } // public function getWorkOrderTaskElements	
    
    // RETURNs an array of associative arrays, with each item in the top-level 
    // array corresponding to a person who is associated with this WorkOrderTask 
    // by DB table workOrderTaskPerson. The content of each associative array 
    // unions every column of DB table workOrderTaskPerson with 'legacyInitials' 
    // from DB table customerPerson. 	
    public function getWorkOrderTaskPersons() {		
        $persons = array();
        
        $query = " select p.*, cp.legacyInitials ";
        $query .= " from " . DB__NEW_DATABASE . ".workOrderTaskPerson  wotp ";
        $query .= " join " . DB__NEW_DATABASE . ".person p on wotp.personId = p.personId ";
        $query .= " left join " . DB__NEW_DATABASE . ".customerPerson cp on p.personId = cp.personId ";
        
        $query .= " where workOrderTaskId = " . intval($this->getWorkOrderTaskId());
        
        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {					
                while($row = $result->fetch_assoc()) {					
                    $persons[] = new Person($row);					
                }
            }			
        }
        
        return $persons;		
    } // END public function getWorkOrderTaskPersons	
    
    //  INPUT $elementIds: array of elementIDs. 
    //  Alter DB table workOrderTaskElement so that the associated set of elements 
    //   for this workOrderTask is exactly as indicated by $elementIds. 
    //  NOTE that, despite the function name, this can remove elements as well as add them. 
    //   Any previously associated elements that do not match the list passed in will have 
    //   their association with this workOrderTask deleted.
    // >>>00028 The queries here probably should be in one transaction.
    public function addElementIds($elementIds) {
        if (is_array($elementIds)) {				
            $exists = array();	
            $elements = $this->getWorkOrderTaskElements();				
            $removes = array();				
            
            foreach ($elements as $element) {					
                if (!in_array($element->getElementId(), $elementIds)) {	
                    $removes[] = $element;	
                } else {	
                    $exists[] = $element->getElementId();	
                }					
            }				
                
            foreach ($removes as $remove) {					
                $query = "delete from " . DB__NEW_DATABASE . ".workOrderTaskElement ";
                $query .= " where workOrderTaskId = " . intval($this->getWorkOrderTaskId()) . " ";
                $query .= " and elementId = " . intval($remove->getElementId()) . " ";
                    
                $this->db->query($query);				
            }
                
            foreach ($elementIds as $elementId) {					
                if (intval($elementId)) { // Martin comment: check if valid element						
                    if (!in_array($elementId, $exists)) {							
                        $query = "insert into " . DB__NEW_DATABASE . ".workOrderTaskElement (workOrderTaskId,elementId) values (";
                        $query .= " " . intval($this->getWorkOrderTaskId()) . " ";
                        $query .= " ," . intval($elementId) . ") ";
                        
                        $this->db->query($query);							
                    }						
                } // else >>>00002 really ought to report invalid elementId.
                  // >>>00016 in fact, we should probably do more checking: not just that
                  //  this can be interpreted as an integer, but that it is an elementId
                  //  for some existing element.
            }				
        } // else >>>00002 really ought to report input in wrong form	
    } // END public function addElementIds
    
    // INPUT $personIds: an array of personIds, who should all be employees of the customer. 
    // DB table workOrderTaskPerson will be altered so that the associated set of persons 
    //  for this workOrderTask is exactly as indicated by $personIds. 
    // NOTE that, despite the function name, this can remove persons as well as add them. 
    //  Any previously associated persons that do not match the list passed in will have 
    //  their association with this workOrderTask deleted. 
    // Also, there is a self-described kluge to add these people to the workOrderTeam 
    //  (but no corresponding kluge for anyone who is removed, that is appropriate, 
    //  don't want people dropped this casually, drop them only deliberately). 
    // Every person added this way is presumed to be associated with the current customer 
    //  (as of 2019-03, always SSS). As necessary, we insert rows in DB tables companyPerson and team. 
    //  The row in team will always have intable=INTABLE_WORKORDER and teamPositionId=TEAM_POS_ID_STAFF_ENG.
    // >>>00028 The queries here probably should be in one transaction.
    public function addPersonIds($personIds) {		
        if (is_array($personIds)) {			
            $exists = array();
            $persons = $this->getWorkOrderTaskPersons();			
            $removes = array();
            
            foreach ($persons as $person) {			
                if (!in_array($person->getPersonId(), $personIds)) {						
                    $removes[] = $person;						
                } else {						
                    $exists[] = $person->getPersonId();						
                }			
            }			
            
            foreach ($removes as $remove) {
                // BEGIN Martin comment
                // will need to do some checks here later about how to clean up
                // any orphaned data that was created by this person id.
                //presumably time spent working on the task or some such
                // END Martin comment
                $query = "delete from " . DB__NEW_DATABASE . ".workOrderTaskPerson ";
                $query .= " where workOrderTaskId = " . intval($this->getWorkOrderTaskId()) . " ";
                $query .= " and personId = " . intval($remove->getPersonId()) . " ";
            
                $this->db->query($query);
            
            }
            
            foreach ($personIds as $personId) {					
                if (intval($personId)) { // Martin comment: check if valid person
                                         // JM >>>00016: that's a pretty weak check, and >>>00002 this and other failures should log.
                    if (!in_array($personId, $exists)) {							
                        $query = "insert into " . DB__NEW_DATABASE . ".workOrderTaskPerson (workOrderTaskId,personId) values (";
                        $query .= " " . intval($this->getWorkOrderTaskId()) . " ";
                        $query .= " ," . intval($personId) . ") ";
            
                        $this->db->query($query);
                        
                        // BEGIN Martin comment
                        // here comes some kludge for auto adding a person to the work order team
                        // if the task gets assigned to them to work on.
                        // first part of the kludge is to assign a particular company to the customer table 
                        // i.e. add the "company" called sound structural solutions to the "customer" called sound structural solutions.
                        //  this company can then be used to create a companyPerson with the ss company with the person in question
                        // since we can look up the correct company when dealing with the paticular customer (the employees)
                        // so added a column in the "customer" table called companyId
                        // and added companyId of '1' to for the companyId column in the customer table (for the customer sss eng)
                        // currently theres only one row in the customer table.
                        // END Martin comment
                        
                        $workOrder = new WorkOrder($this->getWorkOrderId());
                        
                        if (intval($workOrder->getJobId())) {							
                            $job = new Job($workOrder->getJobId());							
                            if ($job->getCustomerId()) {
                                $customer = new Customer($job->getCustomerId());									
                                if (intval($customer->getCustomerId())) {
                                    if (intval($customer->getCompanyId())) {								
                                        $companyPersonId = 0;
                                
                                        $query = " select * from " . DB__NEW_DATABASE . ".companyPerson ";
                                        $query .= " where companyId = " . intval($customer->getCompanyId()) . " ";
                                        $query .= " and personId = " . intval($personId) . " ";
                                
                                        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                            if ($result->num_rows > 0) {
                                                $row = $result->fetch_assoc();
                                                $companyPersonId = intval($row['companyPersonId']);
                                            }
                                        }
                                
                                        if (!$companyPersonId) {												
                                            $query = " insert into " . DB__NEW_DATABASE . ".companyPerson (companyId, personId) values (";
                                            $query .= " " . intval($customer->getCompanyId()) . " ";
                                            $query .= " ," . intval($personId) . ") ";
                                                
                                            $this->db->query($query);
                                                
                                            $companyPersonId = intval($this->db->insert_id);												
                                        }
                                
                                        if (intval($companyPersonId)) {												
                                            $query = " select * from " . DB__NEW_DATABASE . ".team ";
                                            $query .= " where inTable = " . intval(INTABLE_WORKORDER) . " ";
                                            $query .= " and id = " . intval($this->getWorkOrderId()) . " ";
                                            $query .= " and companyPersonId = " . intval($companyPersonId) . " ";
                                            $query .= " and teamPositionId = " . intval(TEAM_POS_ID_STAFF_ENG);
                                            
                                            $exists = false;
                                                
                                            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                                if ($result->num_rows > 0) {
                                                    $exists = true;
                                                }
                                            }
                                                
                                            if (!$exists) {													
                                                $query = " insert into " . DB__NEW_DATABASE . ".team(inTable, id, teamPositionId, companyPersonId) values (";
                                                $query .= " " .  intval(INTABLE_WORKORDER) . " ";
                                                $query .= " ," . intval($this->getWorkOrderId()) . " ";
                                                $query .= " , " . intval(TEAM_POS_ID_STAFF_ENG) . " ";
                                                $query .= " ," . intval($companyPersonId) . ") ";
                                
                                                $this->db->query($query);													
                                            }												
                                        }								
                                    }								
                                }
                            }							
                        }                        
                    }			
                }					
            }			
        }		
    } // END public function addPersonIds
    
    // RETURNs an array of associative arrays, with each associative array 
    //  containing the canonical representation of the content of a row in 
    //  DB table workOrderTaskTime that has the current workOrderTaskId. 
    public function getWorkOrderTaskTime(&$errCode = false, $errorId="1601499294") {		
        // BEGIN Martin comment
        // currently only used this when deleting workordertask to make sure no time on it
        // later maybe flesh this out and use it elsewhere
        // also using when calculating multiplier
        // END Martin comment
        $errCode = false;        
        $ret =  array();
        
        $query = " select * From " . DB__NEW_DATABASE . ".workOrderTaskTime  ";
        $query .= " where workOrderTaskId = " . intval($this->getWorkOrderTaskId()) . " ";		
        
        $result = $this->db->query($query);
        if (!$result) { // >>>00019 Assignment inside "if" statement, may want to rewrite.

            $this->logger->errorDB($errorId, "Hard DB error", $this->db);
            $errCode = true;
        } else {
            while($row = $result->fetch_assoc()) {                      
                $ret[] = $row;                      
            }       
        }
        return $ret;
    }	
    
    // Elaborates on getWorkOrderTaskTime() to RETURN more data. In each row we add:
    // * 'rate', drawn from customerPersonPayPeriodInfo
    // * 'salaryAmount', drawn from customerPersonPayPeriodInfo; 
    // ** NOTE either 'rate' or 'salaryamount' should be zero.
    // * 'timePercentage', relating this particular row to the total for all rows
    // * 'hourly', either identical to 'rate' or calculated from 'salaryAmount'
    // * 'cost' (calculated). 
    public function getWorkOrderTaskTimeWithRates($customer = null) {		
        // BEGIN Martin comment
        // this is a kludge to get the customer.
        // in workOrderTaskTime the personId might be better to be the customerPersonId
        // also probably could have persisted custoemr down from other objects.
        // from now getting direct from front end where this method is called.
        // END Martin comment	
        
        $times = $this->getWorkOrderTaskTime();		
        $ret = array();		
        $totalMinutes = 0;
        
        foreach ($times as $time) {			
            if (intval($time['minutes'])) {
                $totalMinutes += intval($time['minutes']);
            }			
        }
        
        foreach ($times as $time) {
            $time['rate'] = 0;
            $time['salaryAmount'] = 0;
            $time['cost'] = 0;
            $time['timePercentage'] = 0;
            
            $query =  " select * from " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo ";
            $query .= " where customerPersonId = ("; 
            $query .=    " select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson ";
            $query .=    " where personId = " . intval($time['personId']) ;
            $query .=    " and customerId = " . intval($customer->getCustomerId()); 
            $query .= ") ";
            $query .= " and '" . $this->db->real_escape_string($time['day']) . "' > periodBegin and (rate > 0 or salaryHours > 0) ";
            $query .= " order by periodBegin  desc limit 1 ";
    
            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {						
                    while($row = $result->fetch_assoc()) {			
                        $time['rate'] = $row['rate'];
                        $time['salaryAmount'] = $row['salaryAmount'];
                    }			
                }			
            }			
        
            if (intval($time['minutes'])) {
                
                $time['timePercentage'] = $time['minutes'] / $totalMinutes;
                
                if (($time['rate'] > 0) && ($time['salaryAmount'] > 0)) {					
                    $time['cost'] = 0; // Martin comment: if it gets here then there's a problem because a user has a rate and a salary .. they should only have one of those
                                       // >>>00002: SO LOG IT!
                    $time['hourly'] = 0;
                } else {		
                    if ($time['rate'] > 0) {
                        $time['cost'] = $time['rate']/100 * ($time['minutes']/60);
                        $time['hourly'] = $time['rate'];
                    }
                    if ($time['salaryAmount'] > 0) {
                        // BEGIN Martin comment
                        // this in future needs to actually go against the total hours for the salary.
                        // currently just assuming full time
                        // END Martin comment
                        $time['cost'] = ($time['salaryAmount']/2080/100) * ($time['minutes']/60);
                        $time['hourly'] = $time['salaryAmount']/2080;
                    }
                }
            }
            $ret[] = $time;
        }
        return $ret;	
    }
	
    // BEGIN ADDED 2020-09-23 JM for http://bt.dev2.ssseng.com/view.php?id=94#c1100
    // In fact, there should be no more than one taskTally row for a given workOrderTask,
    //  but this is written to handle there being more than one.
    // tally is a float.
    public function getTally() {
        $tally = 0;
        
        $query = "SELECT tally FROM taskTally WHERE workOrderTaskId = " . $this->workOrderTaskId . ";";
        $result = $this->db->query($query);
        if (!$result) {
            $this->error2('1600883757', 'Hard DB error', $this->db);
        } else {
            while ($row = $result->fetch_assoc()) {
                $tally += $row['tally']; 
            }
        }
        
        return $tally;        
    }
    // END ADDED 2020-09-23 JM
    
    // Update several values for this workOrder.
    // INPUT $val typically comes from $_REQUEST. 
    //  An associative array containing the following elements:
    //   * 'extraDescription'
    //   * 'taskStatusId'
    //   Any or all of these may be present.
    // >>>00016, >>>00017: presumably more work to do here.
    public function update($val) {	
        if (is_array($val)) {		
            /* BEGIN REMOVED 2020-10-28 JM getting rid of viewmode
            if (isset($val['viewMode'])) {
                $this->setViewMode($val['viewMode']);
            }
            // END REMOVED 2020-10-28 JM
            */
            
            if (isset($val['extraDescription'])) {
                $this->setExtraDescription($val['extraDescription']);
            }
            if (isset($val['billingDescription'])) {
                $this->setBillingDescription($val['billingDescription']);
            }

            if (isset($val['taskContractStatus'])) {
                $this->setTaskContractStatus($val['taskContractStatus']);
            }
            
            if (isset($val['taskStatusId'])) {				
                $exists = false;
                
                $query = " select * ";
                $query .= " from " . DB__NEW_DATABASE . ".taskStatus  ";
                $query .= " where taskStatusId = " . intval($val['taskStatusId']);
                
                if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                    if ($result->num_rows > 0) {
                        $exists = true;				
                    }				
                }				
                
                if ($exists) {
                    // Valid taskStatus					
                    $this->setTaskStatusId($val['taskStatusId']);
					if (intval($val['taskStatusId']) == STATUS_TASK_ACTIVE) {						
                        $workOrder = new WorkOrder($this->getWorkOrderId());
                        
                        /* BEGIN REPLACED 2020-06-11 JM
                        if ($workOrder->getWorkOrderStatusId() == STATUS_WORKORDER_DONE) {							
                            $workOrder->update(array('workOrderStatusId' => STATUS_WORKORDER_ACTIVE));							
                        }
                        // END REPLACED 2020-06-11 JM
                        */
                        // BEGIN REPLACEMENT 2020-06-11 JM, refined 2020-11-18  
                        if ($workOrder->isDone()) {
							$reactivateStatus = WorkOrder::getReactivateStatusId();
							if ($reactivateStatus === false) {
							    // JM 2020-06-11
							    // >>>00001, >>>00002: this is an error, already logged. Right now, we just won't change the workOrderStatus,
							    // but we should think about better ways to handle this.
							} else {
							    /* BEGIN REPLACED 2020-11-18 JM
                                // THIS WAS NEVER A GOOD IDEA. I hadn't realized that the old code (replaced 2020-06-11) was actually broken 
                                // This failed to insert into workOrderStatusTime.							    
							    $workOrder->update(array('workOrderStatusId' => $reactivateStatus));
                                // END REPLACED 2020-11-18 JM
                                */
                                // BEGIN REPLACEMENT 2020-11-18 JM
                                $customerPersons = Array(); // nobody
                                $note = '';
                                $workOrder->setStatus($reactivateStatus, $customerPersons, $note);  // ignoring failure here: it's already been logged, and there is nothing we can do about it.
                                unset($customerPersons, $note);
                                // END REPLACEMENT 2020-11-18 JM
							}
                        }						
                        // END REPLACEMENT 2020-06-11 JM						
                    }
                    
                    
                    if (intval($val['taskStatusId']) == STATUS_TASK_DONE) {						
                        // BEGIN Martin comment
                        // this is where checks would be done
                        // to perhaps auto close a workorder
                        // if all tasks are done.
                        // END Martin comment
                    }
                }				
            }			
        }
        
        $this->save();
    
    }
    
    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getWorkOrderTaskId();
    }
    
    // Updates the following columns of the relevant row in DB table workOrderTask:  
    //  workOrderId (JM believes this never changes), taskId (JM believes this never changes), 
    //  taskStatusId, extraDescription 
    public function save() {		
        $query = " update " . DB__NEW_DATABASE . ".workOrderTask  set ";
        $query .= " workOrderId = " . intval($this->getWorkOrderId()) . " ";
        $query .= " ,taskId = " . intval($this->getTaskId()) . " ";
        $query .= " ,taskStatusId = " . intval($this->getTaskStatusId()) . " ";
        // $query .= " ,viewMode = " . intval($this->getViewMode()) . " "; // REMOVED 2020-10-28 JM getting rid of viewmode
        $query .= " ,extraDescription = '" . $this->db->real_escape_string($this->getExtraDescription()) . "' ";
        $query .= " ,billingDescription = '" . $this->db->real_escape_string($this->getBillingDescription()) . "' ";
        $query .= " ,taskContractStatus = " . intval($this->getTaskContractStatus()) . " ";
        $query .= " where workOrderTaskId = " . intval($this->getWorkOrderTaskId()) . " ";
    
        $this->db->query($query);
    
    }
    
    // RETURNs workOrderTask as associative array with members each built by the appropriate get method.
    // NOTE that as of 2019-03-04 this returns only some of the private variables
    //  of this class that represent values in DB table WorkOrder omits extraDescription.
    //  >>>00001 JM suspects that was not deliberate and perhaps should change.
    public function toArray() {	
        return array(	
                'workOrderTaskId' => $this->getWorkOrderTaskId(),
                'workOrderId' => $this->getWorkOrderId(),
                'taskId' => $this->getTaskId(),
                'taskStatusId' => $this->getTaskStatusId(),
                'task' => $this->task->toArray()	
        );
    }
    
    // Return true if the id is a valid workOrderTaskId, false if not
    // INPUT $workOrderTaskId: workOrderTaskId to validate, should be an integer but we will coerce it if not
    // INPUT $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error
    public static function validate($workOrderTaskId, $unique_error_id=null) {
        global $db, $logger;

        if (!$db) {
	        $db =  DB::getInstance(); 
	    }
        
        $ret = false;
        $query = "SELECT workOrderTaskId FROM " . DB__NEW_DATABASE . ".workOrderTask WHERE workOrderTaskId=$workOrderTaskId;";
        $result = $db->query($query);
            
        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578694563', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    }
}

?>