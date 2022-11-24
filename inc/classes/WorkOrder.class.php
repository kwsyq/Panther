<?php
/* inc/classes/WorkOrder.class.php

EXECUTIVE SUMMARY:
One of the many classes that essentially wraps a DB table, in this case the WorkOrder table.
As for quite a few such classes, the functionality reaches into auxiliary tables as well.

* Extends SSSEng, constructed for current user, or for a User object passed in, and optionally for a particular person.
* Public functions:
** __construct($id = null, User $user = null)
** setWorkOrderDescriptionTypeId($val)
** setDescription($val)
** setDeliveryDate($val)
** setGenesisDate($val)
** setIntakeDate($val)
** setIsVisible($val)
** setContractNotes($val)
** setFakeInvoice($val)
** setTempNote($val)
** getWorkOrderId()
** getJobId()
** getWorkOrderDescriptionTypeId()
** getDescription()
** getDeliveryDate()
** getWorkOrderStatus()
** getWorkOrderStatusHistory(&$errCode = false)
** getStatusData()
** getWorkOrderStatusId()
** isDone()
** getStatusName()
** getGenesisDate()
** getIntakeDate()
** getIsVisible()
** getContractNotes()
** getCode()
** getInvoiceTxnId()
** getFakeInvoice()
** getTempNote()
** getJob()
** getNameWithoutType()
** getName()
** getWorkOrderTasksTree()
** getTeamPosition($teamPositionId, $onlyOne = true, $onlyActive = false)
** deleteWorkOrderTask($workOrderTaskId)
** getWorkOrderTasksRawWithElements($blockAdd)
** getWorkOrderDetails($showHidden = 0)
** getWorkOrderTasksRaw()
** allTasksDone()
** update($val)
** save()
** getTeam($active = 0)
** getContracts($allow_uncommitted=false)
** getContractWo(&$errCode = false)
** newContract()
** getContract($contractId = 0)
** newInvoice($contractId = 0)
** getInvoices()
** getDesignProfessional()
** getClient()
** setStatus($workOrderStatusId, $customerPersonIds, $note)
** toArray()s
** revenueMetric()
** getLevelOne(&$errCode = false)
** public static function validate($workOrderId, $unique_error_id=null)
** public static function errorToText($errCode)
** public static function getReactivateStatusId()
** public static function getInitialStatusId()
** public static getAllWorkOrderStatuses(&$errCode = false)
** public static function validateWorkOrderStatus($workOrderStatusId) {
** public static function workOrderStatusIsDone($workOrderStatusId) {

*/
require_once dirname(__FILE__).'/../determine_environment.php'; // [CP] 20200909 Added because is necessary to call environment() function before sending emails (just in production the emails should be sent)

class WorkOrder extends SSSEng {
    // The following correspond exactly to columns of DB table WorkOrder
    // See documentation of that table for further details.
    private $workOrderId;
    private $jobId;
    // No variables for nameOld, descriptionOld, workOrderDescriptionOld
    private $workOrderDescriptionTypeId;
    private $description;
    private $deliveryDate;
    // No variable for eorOld
    //private $workOrderStatusId; // commented out by Martin before 2019, so no variable for this column
    private $genesisDate;
    private $intakeDate;
    private $isVisible;
    private $contractNotes;
    private $code;
    private $InvoiceTxnId;
    private $fakeInvoice;
    // private $tempAmount; // Removed 2020-09-21 JM
    private $tempNote;
    // No variables related to the status, e.g. workOrderStatus, workOrderStatusTime

    private $gold; // >>>00001 JM: The gold/overlay stuff is very complicated, and I could never get
                   //  Martin to discuss it. There is still going to be some serious work in teasing out
                   //  what is going on here. It has something to do with reconstructing a workOrderTask
                   //  hierarchy, including "fake" tasks where SSS at one time historically had to "fake up"
                   //  parent tasks (or maybe just fake workOrderTasks) that had no explicit representation.
                   // Reading the code, there is no clear reason why this is a private member of the class object,
                   //  rather than just a local variable in the one function that uses it.

    // INPUT $id: May be either of the following:
    //  * a workOrderId from the WorkOrder table
    //  * an associative array which should contain an element for each columnn
    //    used in the WorkOrder table, corresponding to the private variables
    //    just above.
    //  >>>00016: JM 2019-02-28: should certainly validate this input, doesn't.
    // INPUT $user: User object, typically current user.
    //  >>>00023: JM 2019-02-28: No way to set this later, so hard to see why it's optional.
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
            $query = " SELECT wo.* ";
            $query .= " FROM " . DB__NEW_DATABASE . ".workOrder wo ";
            $query .= " WHERE wo.workOrderId = " . intval($val);

            $result = $this->db->query($query);

            if ($result) {
                if ($result->num_rows > 0) {
                    // Since query used primary key, we know there will be exactly one row.

                    // Set all of the private members that represent the DB content
                    $row = $result->fetch_assoc();

                    $this->setWorkOrderId($row['workOrderId']);
                    $this->setJobId($row['jobId']);
                    $this->setWorkOrderDescriptionTypeId($row['workOrderDescriptionTypeId']);
                    $this->setDescription($row['description']);
                    $this->setDeliveryDate($row['deliveryDate']);
                    $this->setGenesisDate($row['genesisDate']);
                    $this->setIntakeDate($row['intakeDate']);
                    $this->setIsVisible($row['isVisible']);
                    $this->setcontractNotes($row['contractNotes']);
                    $this->setCode($row['code']);
                    $this->setInvoiceTxnId($row['InvoiceTxnId']);
                    $this->setFakeInvoice($row['fakeInvoice']);
                    // $this->setTempAmount($row['tempAmount']); Removed 2020-09-21 JM
                    $this->setTempNote($row['tempNote']);
                } else {
                    $this->logger->errorDb('637383688295449656', "Invalid workorderId", $this->db);
                }
            } else {
                $this->logger->errorDb('637383687988942079', "Hard DB error", $this->db);
            }
        } else if (is_array($val)) {
            // Set all of the private members that represent the DB content, from
            //  input associative array
            $this->setWorkOrderId($val['workOrderId']);
            $this->setJobId($val['jobId']);
            $this->setWorkOrderDescriptionTypeId($val['workOrderDescriptionTypeId']);
            $this->setDescription($val['description']);
            $this->setDeliveryDate($val['deliveryDate']);
            $this->setGenesisDate($val['genesisDate']);
            $this->setIntakeDate($val['intakeDate']);
            $this->setIsVisible($val['isVisible']);
            $this->setcontractNotes($val['contractNotes']);
            $this->setCode($val['code']);
            $this->setInvoiceTxnId($val['InvoiceTxnId']);
            $this->setFakeInvoice($val['fakeInvoice']);
            // $this->setTempAmount($val['tempAmount']); Removed 2020-09-21 JM
            $this->setTempNote($val['tempNote']);
        }
    } // END private function load

    // Inherited getId is protected, presumably to prevent it being called directly on this class.
    protected function getId() {
        return $this->getWorkOrderId();
    }

    // >>>00016, >>>00002: the "set" functions here presumably should validate & should log on error

    // Set primary key
    // INPUT $val: primary key (workOrderTaskId)
    private function setWorkOrderId($val) {
        if ( ($val != null) && (is_numeric($val)) && ($val >=1)) {
            $this->workOrderId = intval($val);
        } else {
            $this->logger->error2("627383735851197849", "Invalid input for workorderId : [$val]" );
        }

    }

    // INPUT $val: foreign key into DB table Job
    private function setJobId($val) {
        if (Job::validate($val)) {
            $this->jobId = intval($val);
        } else {
            $this->logger->error2("627383729043828386", "Invalid input for JobId : [$val]" );
        }
    }

    // INPUT $val: foreign key into DB table WorkOrderDescriptionType
    public function setWorkOrderDescriptionTypeId($val) {
        $this->workOrderDescriptionTypeId = intval($val);
    }

    // INPUT $val: Work order description
    public function setDescription($val) {
        $val = truncate_for_db($val, 'WorKOrder => Description', 128, '637377634930772975');
        $this->description = $val;
    }

    // INPUT $val: delivery date in 'Y-m-d H:i:s' format
    // If not valid, sets to '0000-00-00 00:00:00'
    // In the input, 'H:i:s' form ('H:i:s' should always be '00:00:00') >>>00016 but we don't test that as of 2019-03-01
    //   >>>00002: invalid => really ought to log.
    public function setDeliveryDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->deliveryDate = $val;
        } else {
            $this->deliveryDate = '0000-00-00 00:00:00';
        }
    }

    // INPUT $val: genesis date in 'Y-m-d H:i:s' format
    // If not valid, sets to '0000-00-00 00:00:00'
    public function setGenesisDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->genesisDate = $val;
        } else {
            $this->genesisDate = '0000-00-00 00:00:00';
        }
    }

    // INPUT $val: intake date in 'Y-m-d H:i:s' format
    // If not valid, sets to '0000-00-00 00:00:00'
    public function setIntakeDate($val) {
        $v = new Validate();
        if ($v->verifyDate($val, true, 'Y-m-d H:i:s')) {
            $this->intakeDate = $val;
        } else {
            $this->intakeDate = '0000-00-00 00:00:00';
        }
    }

    // Per discussion with Martin, "an active/inactive/soft delete kind of thing".
    // INPUT $val: 0 means "effectively deleted, but just kept to preserve referential integrity".
    //  Normal value is 1.
    public function setIsVisible($val) {
        if (($val == 1) || ($val == 0)) {
            $this->isVisible = intval($val);
        } else {
            $this->isVisible = 1; //Default in Database.
            $this->logger->error2('637390583153260658', "Invalid \$val IsVisible =" .$val );
        }
    }

    // $val: string, here rather than in contract table because there can be multiple versions of the contract.
    public function setContractNotes($val) {
        $val = truncate_for_db($val, 'WorkOrder => Contract Notes', 2048, '637377635740213805');
        $this->contractNotes = $val;

    }

    // $val: Alternate key. Random generated identifier, another way a workOrder can be referred to.
    private function setCode($val) {
        $val = truncate_for_db($val, 'WorkOrder => Code', 16, '637377636168583676');
        $this->code = $val;
    }

    // $val - QuickBooks Id. Not used for new rows once we completed transition to our own invoicing.
    private function setInvoiceTxnId($val) {
        $val = truncate_for_db($val, 'WorkOrder => InvoiceTxnId', 36, '637377636597450513');
        $this->InvoiceTxnId = $val;
    }

    // $val - Boolean, default 0. Martin said "Eventually won't be used for new rows."
    //  A way to deal with transition of invoicing.
    // $val - >>>00017 2019-02 JM May be able to get rid of this, at least may be able to be made private
    //   at this point, not used for new content.
    public function setFakeInvoice($val) {
        $this->fakeInvoice = intval($val);
    }

    /* BEGIN REMOVED 2020-09-21 JM
    // INPUT $val - U.S. currency; Martin said in June 2018 that this
    // was being used just temporarily, will go away; >>>00001: no idea
    // of its status 2019-03.
    public function setTempAmount($val) {
        if (is_numeric($val)) {
            $this->tempAmount = $val;
        }
    }
    // END REMOVED 2020-09-21 JM
    */

    // INPUT $val - Martin used this temporarily in 2018; JM reviving it for v2020-4.
    public function setTempNote($val) {
        $val = truncate_for_db($val, 'WorkOrder => TempNote', 4096, '637377637780525862');
        $this->tempNote = $val;
    }

    // RETURN primary key
    public function getWorkOrderId() {
        return $this->workOrderId;
    }

    // RETURN foreign key into DB table Job
    public function getJobId() {
        return $this->jobId;
    }

    // RETURN foreign key into DB table WorkOrderDescriptionType
    public function getWorkOrderDescriptionTypeId() {
        return $this->workOrderDescriptionTypeId;
    }

    // RETURN description (string)
    public function getDescription() {
        return $this->description;
    }

    // RETURN deliveryDate in 'Y-m-d H:i:s' form ('H:i:s' should always be '00:00:00')
    public function getDeliveryDate() {
        return $this->deliveryDate;
    }

    // RETURN associative array: this has changed a little for v2020-3. Basically it is the canonical
    //  representation of a row from DB table workOrderStatusTime as an associative array, plus some
    //  additional indexes.
    //   * From workOrderStatusTime:
    //     * workOrderStatusTimeId, workOrderStatusId, workOrderId,
    //     * inserted, personId, note, snooze
    //   * From workOrderStatus
    //     grace, statusName, isDone, canNotify, successorId
    //   * 'customerPersonArray' has as its value an array of associative arrays describing any
    //  related customerperson; that last array has two indexes, customerPersonId and legacyInitials.
    // (Can return false on database failure.)
    // Side effect: if no status is associated, set WorkOrder::getInitialStatusId()
    // Heavily rewritten 2020-06-08 JM for v2020-3, mostly to take into account the new wostCustomerPerson
    public function getWorkOrderStatus() {
        $workOrderStatusTimeId = 0;
        $exists = false;

        // Relies on the fact that higher workOrderStatusTimeId means later insertion.
        // Before 2020-06-08 this used SELECT *, I've made the columns explicit.
        // $query = "SELECT wost.* ";

        $query = "SELECT workOrderStatusTimeId, workOrderStatusTime.workOrderStatusId, workOrderId, ";
        // REMOVED 2020-08-10 JM for v2020-4 // $query .= "extra, "; // this is vestigial in v2020-3
        $query .= "workOrderStatusTime.inserted, personId, workOrderStatusTime.note, snooze, ";
        $query .= "workOrderStatus.grace, workOrderStatus.statusName, ";
        $query .= "workOrderStatus.isDone, workOrderStatus.canNotify, workOrderStatus.successorId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatusTime ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatus ON workOrderStatusTime.workOrderStatusId = workOrderStatus.workOrderStatusId ";
        $query .= "WHERE workOrderId = " . intval($this->getWorkOrderId()) . " ";
        $query .= "ORDER BY workOrderStatusTimeId DESC LIMIT 1;";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDB('1591654684', "Hard DB error", $this->db);
            return false;
        }
        // LIMIT 1 above, so either 0 or 1 rows returned
        $exists = $result->num_rows == 1;
        if ($exists) {
            $row = $result->fetch_assoc();

            // We want to look for any associated customerPerson
            $row['customerPersonArray'] = Array();
            $workOrderStatusTimeId = $row['workOrderStatusTimeId'];
            $query = "SELECT cp.customerPersonId, cp.legacyInitials ";
            $query .= "FROM " . DB__NEW_DATABASE . ".wostCustomerPerson wostcp ";
            $query .= "JOIN " . DB__NEW_DATABASE . ".customerPerson cp ON wostcp.customerPersonId = cp.customerPersonId ";
            $query .= "WHERE wostcp.workOrderStatusTimeId = $workOrderStatusTimeId;";

            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDB('1591654724', "Hard DB error", $this->db);
                return false;
            }
            while ($row_2 = $result->fetch_assoc()) {
                $row['customerPersonArray'][] = Array('customerPersonId'=>$row_2['customerPersonId'], 'legacyInitials'=>$row_2['legacyInitials']);
            }
            return $row;
        } else {
            /* BEGIN REPLACED 2020-06-10 JM
            // No status is associated, set STATUS_WORKORDER_NONE
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderStatusTime (workOrderStatusId,workOrderId,extra) VALUES (";
            $query .= intval(STATUS_WORKORDER_NONE);
            $query .= ", " . intval($this->getWorkOrderId());
            $query .= ", 0) ";
            // END REPLACED 2020-06-10 JM
            */
            // BEGIN REPLACEMENT 2020-06-10 JM
            // No status is associated, set to the initial status (same as return of WorkOrder::getInitialStatusId())
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderStatusTime (workOrderStatusId, workOrderId) VALUES (";
            $query .= "(SELECT workOrderStatusId FROM " . DB__NEW_DATABASE . ".workOrderStatus WHERE workOrderStatus.isInitialStatus=1 LIMIT 1)";
            $query .= ", " . intval($this->getWorkOrderId());
            $query .= ") ";
            // END REPLACEMENT 2020-06-10 JM

            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDB('1591654793', "Hard DB error", $this->db);
                return false;
            }
            $saved_query = $query;

            $query = "SELECT workOrderStatusTimeId, workOrderStatusId, workOrderId, ";
            // REMOVED 2020-08-10 JM for v2020-4 // $query .= "extra, "; // this is vestigial in v2020-3
            $query .= "inserted, personId, note, snooze ";
            $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatusTime ";
            $query .= "WHERE workOrderId = " . intval($this->getWorkOrderId()) . " ";
            $query .= "ORDER BY workOrderStatusTimeId DESC LIMIT 1;";

            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDB('1591654865', "Hard DB error", $this->db);
                return false;
            }
            if ($result->num_rows == 0) {
                $this->logger->errorDB('1591654901', "We can't find the row we just inserted", $this->db);
                $this->logger->error2('1591654902', "It was inserted with $saved_query");
                return false;
            }
            $row = $result->fetch_assoc();
            $row['customerPersonArray'] = Array(); // can't be any associated customerPersons, we just inserted it
            return $row;
        }

        // shouldn't ever get here, but just in case...
        return false;
    } // END public function getWorkOrderStatus


    /**
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $ret.
        *   Just like public function getWorkOrderStatus, but instead of returning a single
        *	associative array, it returns an array of associative arrays, each just like the
        *	return of associative array, representing the status history of the workOrder,
        *	in reverse chronological order. Unlike getWorkOrderStatus, won't insert a status
        *   if there is none, just gives an empty array.
    */

    public function getWorkOrderStatusHistory(&$errCode = false) {
        $ret = Array();
        $errCode = false;

        $query = "SELECT workOrderStatusTimeId, workOrderStatusTime.workOrderStatusId, workOrderId, ";
        // REMOVED 2020-08-10 JM for v2020-4 // $query .= "extra, "; // this is vestigial in v2020-3
        $query .= "workOrderStatusTime.inserted, personId, workOrderStatusTime.note, snooze, ";
        $query .= "workOrderStatus.grace, workOrderStatus.statusName ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatusTime ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatus ON workOrderStatusTime.workOrderStatusId = workOrderStatus.workOrderStatusId ";
        $query .= "WHERE workOrderId = " . intval($this->getWorkOrderId()) . " ";
        $query .= "ORDER BY workOrderStatusTimeId DESC;";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDB('1591744291', "Hard DB error", $this->db);
            $errCode = true;
            return $ret;
        }

        while ($row = $result->fetch_assoc()) {
            // We want to look for any associated customerPerson
            $row['customerPersonArray'] = Array();
            $workOrderStatusTimeId = $row['workOrderStatusTimeId'];
            $query = "SELECT cp.customerPersonId, cp.legacyInitials ";
            $query .= "FROM " . DB__NEW_DATABASE . ".wostCustomerPerson wostcp ";
            $query .= "JOIN " . DB__NEW_DATABASE . ".customerPerson cp ON wostcp.customerPersonId = cp.customerPersonId ";
            $query .= "WHERE wostcp.workOrderStatusTimeId = $workOrderStatusTimeId;";

            $result_2 = $this->db->query($query);
            if (!$result_2) {
                $this->logger->errorDB('1591744324', "Hard DB error", $this->db);
                $errCode = true;
                return Array();
            }
            while ($row_2 = $result_2->fetch_assoc()) {
                $row['customerPersonArray'][] = Array('customerPersonId'=>$row_2['customerPersonId'], 'legacyInitials'=>$row_2['legacyInitials']);
            }
            $ret[] = $row;
        }

        return $ret;
    } // END public function getWorkOrderStatusHistory


    // Reworked for v2020-02 to be pretty much the same as getWorkOrderStatus (which, in v2020-3, it now calls)
    // The differences were not particularly valuable.
    // For consistency with old code, returns an empty array on failure, rather than false.
    public function getStatusData() {
        /* BEGIN REPLACED 2020-06-08 JM
        $status = array();

        $query = "select * from " . DB__NEW_DATABASE . ".workOrderStatusTime wost ";
        $query .= " join " . DB__NEW_DATABASE . ".workOrderStatus s on wost.workOrderStatusId = s.workOrderStatusId ";
        $query .= " where workOrderId = " . intval($this->getWorkOrderId()) . " ";
        $query .= " order by wost.workOrderStatusTimeId desc limit 1 ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $status = $row;
            }
        }
        return $status;
        // END REPLACED 2020-06-08 JM
        */
        // BEGIN REPLACEMENT 2020-06-08 JM
        $row = $this->getWorkOrderStatus();
        return ( $row ? $row : array() );
        // END REPLACEMENT 2020-06-08 JM
    } // END public function getStatusData

    // RETURN workOrderStatus as Id (index into DB table workOrderStatus)
    // Side effect: if no status is associated, set the unique initial status
    // Radically simplified 2020-06-10 JM by using another existing function.
    public function getWorkOrderStatusId() {
        // BEGIN NEW VERSION 2020-06-10 JM
        $row = $this->getWorkOrderStatus();
        if ($row) {
            return $row['workOrderStatusId'];
        } else {
            return false;
        }
        // END NEW VERSION 2020-06-10 JM
        /* Old code (which uses the old "extra" stuff, and presumes that STATUS_WORKORDER_NONE is a constant)
        $workOrderStatusId = 0;
        $exists = false;

        // Relies on the fact that higher workOrderStatusTimeId means later insertion.
        $query = " select wost.* ";
        $query .= " from " . DB__NEW_DATABASE . ".workOrderStatusTime wost ";
        $query .= " where wost.workOrderId = " . intval($this->getWorkOrderId()) . " ";
        $query .= " order by wost.workOrderStatusTimeId desc limit 1 ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $workOrderStatusId = $row['workOrderStatusId'];
                // JM: 2019-03-01 really could just return $workOrderStatusId here.
                $exists = true;
            }
        }

        if (!$exists) {
            // No status is associated, set STATUS_WORKORDER_NONE
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderStatusTime (workOrderStatusId,workOrderId,extra) VALUES (";
            $query .= intval(STATUS_WORKORDER_NONE);
            $query .= ", " . intval($this->getWorkOrderId());
            $query .= ", 0) ";
            $this->db->query($query);
            $workOrderStatusId = STATUS_WORKORDER_NONE;
        }

        return $workOrderStatusId;
        */
    } // END public function getWorkOrderStatusId

    public function isDone() {
        return WorkOrder::workOrderStatusIsDone($this->getWorkOrderStatusId());
    }


    // RETURN workorder status as text
    /* >>>00017: if we don't mind a harmless side effect, body could be rewritten as simply:
        $row = $this->getStatusData();
        if (array_key_exists('statusName', $row)) {
            return $row['statusName'];
        } else {
            return '';
        }
    */
    public function getStatusName() {
        $statusName = "";

        // Relies on the fact that higher workOrderStatusTimeId means later insertion.
        $query = "SELECT s.statusName, wost.workOrderStatusTimeId as time "; // reworked 2020-02-28 JM, Was " select * "
                                                                             // but we are really just getting statusName; also
                                                                             // need to support our ORDER BY.
        $query .= " FROM " . DB__NEW_DATABASE . ".workOrderStatusTime wost ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".workOrderStatus s ON wost.workOrderStatusId = s.workOrderStatusId ";
        $query .= " WHERE workOrderId = " . intval($this->getWorkOrderId()) . " ";
        $query .= " ORDER BY time DESC LIMIT 1 ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637425889126204628', "getStatusName() Hard DB error", $this->db);
        } else {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $statusName = $row['statusName'];
            }
        }

        return $statusName;
    } // END public function getStatusName

    // RETURN genesisDate in 'Y-m-d H:i:s' form ('H:i:s' should always be '00:00:00')
    public function getGenesisDate() {
        return $this->genesisDate;
    }

    // RETURN intakeDate in 'Y-m-d H:i:s' form ('H:i:s' should always be '00:00:00')
    public function getIntakeDate() {
        return $this->intakeDate;
    }

    // RETURN quasi-Boolean isVisible, "an active/inactive/soft delete kind of thing".
    //  Value: 0 or 1.
    //  0 means "effectively deleted, but just kept to preserve referential integrity".
    public function getIsVisible() {
        return $this->isVisible;
    }

    // RETURN contract notes. Associated with WorkOrder rather than contract because
    //  there can be multiple versions of the contract.
    public function getContractNotes() {
        return $this->contractNotes;
    }

    // RETURN Alternate key 'code'. Random generated identifier, another way a workOrder can be referred to.
    public function getCode() {
        return $this->code;
    }

    // RETURN QuickBooks Id. Not used for new rows once we completed transition to our own invoicing.
    public function getInvoiceTxnId() {
        return $this->InvoiceTxnId;
    }

    // RETURN Boolean, default 0. Martin said "Eventually won't be used for new rows."
    //  A way to deal with transition of invoicing.
    // $val - >>>00017 2019-02 JM May be able to get rid of this, at least may be able to be made private
    //   at this point, not used for new content.
    public function getFakeInvoice() {
        return $this->fakeInvoice;
    }

    /* BEGIN REMOVED 2020-09-21 JM
    // RETURN value in U.S. currency; Martin said in June 2018 that this
    // was being used just temporarily, will go away; >>>00001: no idea
    // of its status 2019-03.
    public function getTempAmount() {
        return $this->tempAmount;
    }
    // END REMOVED 2020-09-21 JM
    */

    // RETURN tempNotel Martin used this temporarily in 2018; JM reviving it for v2020-4.
    public function getTempNote() {
        return $this->tempNote;
    }

// BEGIN MARTIN COMMENT
// listing all childs of a task
/*
select  taskId,
description,
parentId
from    (select * from task
order by parentId, taskId) tasks_sorted,
(select @pv := '29') initialisation
where   find_in_set(parentId, @pv)
and     length(@pv := concat(@pv, ',', taskId))
*/

/*
get parents

SELECT T2.taskId, T2.description
FROM (
SELECT
@r AS _id,
 (SELECT @r := parentId FROM task WHERE taskId = _id) AS parentId,@l := @l + 1 AS lvl
FROM
(SELECT @r := 301, @l := 0) vars,
task m
WHERE @r <> 0) T1
JOIN task T2
ON T1._id = T2.taskId
ORDER BY T1.lvl DESC;
*/
// END MARTIN COMMENT

    // RETURN foreign key into DB table Jobs
    public function getJob() {
        $job = new Job($this->getJobId(), $this->user);
        return $job;
    }

    // RETURN description (string)
    // A synonym for getDescription, >>>00012 which is a much clearer function name.
    //  Why does this exist at all?
    public function getNameWithoutType() {
        return $this->getDescription();
    }

    // RETURN string consisting of:
    //  * the typeName corresponding to the relevant workOrderDescriptionType name +
    //  * space +
    //  * the description of this workOrder.
    // >>>00012 Surely we can give this a better name.
    public function getName() {
        // [Martin comment] fix this shit

        $dts = getWorkOrderDescriptionTypes();
        $dtsi = Array(); // Added 2019-12-02 JM: initialize array before using it!
        foreach ($dts as $dt) {
            $dtsi[$dt['workOrderDescriptionTypeId']] = $dt;
        }

        $name = '';

        if (isset($dtsi[$this->getWorkOrderDescriptionTypeId()])) {
            $name = $dtsi[$this->getWorkOrderDescriptionTypeId()]['typeName'];
        }
        if (strlen($name)) {
            $name .= ' ';
        }
        $name .= $this->getDescription();

        return $name;
    }

    // This function has to do with the task hierarchy, potentially
    //  including "fake" tasks that are not overtly part of the workOrder even
    //  though their descendants are.
    // This is always called in the context of a single workOrder and either
    //  a single element (or the "general" tasks not associated with any element)
    //  or to handle those workOrderTasks for that workOrder that have multiple elements associated.
    // INPUT $render: see discussion of $render in public function getWorkOrderTasksTree. As explained there:
    //  multi-dimensional array using massaged taskIds ('a' prefixed to a taskId) to represent
    //  the a subset of the abstract task hierarchy, and eventually leading to an array of workOrderTasks that share
    //  the same taskId. Often there will be only one element in that last array, but it is *possible*
    //  for the same abstract task to be used more than once in a workOrder, even for the same element.
    //  E.g. $array['a35']['a210']['a455']["wots"][$i] is a workOrderTask Object.
    // OUTPUT $gold: the structure we are building. Top-level call should pass this in as an empty array.
    //  NOTE that it is a flat numerically-indexed array, even though it implicitly represents a hierarchy.
    //  On output, this will be in an order corresponding to what I believe is called pre-order traversal
    //  (*but correct me if I'm wrong, this is from 40 years ago for me! - JM).
    //  For each reconstructed internal node that doesn't have an explicit workOrderTask, we will have:
    //   * 'type' => 'fake'
    //   * 'level' => $level
    //   * 'data' => a key from input $array, e.g. 'a210' corresponding to taskId 210
    //  For each leaf node and any explicit internal node, we will have:
    //   * 'type' => 'real'
    //   * 'level' => $level
    //   * 'data' => workOrderTask object
    //  So, in the example given above for input $array, if the level-0 task is "fake":
    //   $gold[0] = array('type' => 'fake', 'level' => 0, 'data' => 'a35')
    //   $gold[1] = array('type' => 'real', 'level' => 1, 'data' => $wot1), where $wot1 is a workOrderTask whose taskId == 210
    //   $gold[2] = array('type' => 'real', 'level' => 2, 'data' => $wot2), where $wot2 is a workOrderTask whose taskId == 455
    //  We can, of course, have multiple tasks at any level of the hierarchy; each task/workOrderTask with a level $level > 0
    //  is to be understood in the context of the closest task/workOrderTask before it with a level of $level-1.
    //  So, for example, we could easily have as well:
    //   $gold[3] = array('type' => 'real', 'level' => 2, 'data' => $wot3), where $wot3 is a DIFFERENT workOrderTask whose taskId == 455
    //   $gold[4] = array('type' => 'real', 'level' => 2, 'data' => $wot4), where $wot4 is a workOrderTask with a DIFFERENT taskId that is
    //          also a child of taskId 210
    //   $gold[5] = array('type' => 'real', 'level' => 1, 'data' => $wot5), where $wot5 is a workOrderTask with a DIFFERENT taskId that is
    //          also a child of taskId 35
    //   etc.
    //
    // INPUT $level: 0 when initially called; increases by 1 at each recursive call.
    //
    // Prior to 2020-07-28, this was an inappropriately public function called just 'recursive'
    //
    // >>>00032 2020-07-29 JM: looking forward, it would make a lot more sense to distinguish the two uses of 'data' here,
    //  and have two distinct array elements, one for a taskId and the other for a workOrderTask. It *might* continue to be useful
    //  for  array values of $gold to each have only one of these, but they are completely different sorts of thing
    //  and shouldn't be referenced by the same index in the associative array.
    private function buildTaskTreeLeadingToWorkOrderTasks($render, &$gold, $level) {
        foreach($render as $key => $value) {
            if (array_key_exists('wots', $value)) {
                // ("real" only)
                foreach ($value['wots'] as $wot) {
                    $gold[] = array('type' => 'real', 'level' => $level, 'data' => $wot);
                    /* DEBUG
                    $this->logger->info2('JDEBUG 1', '$gold['. (count($gold)-1) . '] = array(\'type\' => \'real\', \'level\' => ' .
                        $level . ', \'data\' => WorkOrderTask('. $wot->getWorkOrderTaskId() .')');
                    */
                }
            } else {
                // we are here only on a "fake" node
                if ($key != 'wots') { // presumably a redundant test
                    $gold[] = array('type' => 'fake', 'level' => $level, 'data' => $key);

                    /* DEBUG
                    $this->logger->info2('JDEBUG 2', '$gold['. (count($gold)-1) . '] = array(\'type\' => \'fake\', \'level\' => ' .
                        $level . ', \'data\ =>"' . $key . '")');
                    */
                }
            }

            if ($key != 'wots') {
                if(is_array($value) && count($value)) {
                    $this->buildTaskTreeLeadingToWorkOrderTasks($value, $gold, $level + 1);
                }
            }
        }
    } // END function buildTaskTreeLeadingToWorkOrderTasks

    /* JM 2020-07-10: Martin said he intended to get rid of overlay/gold stuff, and it is super-confusing.
        However, JM believes that this or some equivalent is always going to be needed
        as long as we retain old data that involves the "fake" tasks (workOrderTasks
        implicitly included in a workOrder because their decendants are explicitly included).

       As of 2020-07-10, I (JM) have added notes, >>>00001 but this may merit more time & focus.

       Pretty significantly reworked for v2020-4; through v2020-3, we had an input of a class name,
        and did different things based on what class name was passed in.
        Now fixed, class-specfic issues handled at a higher level.

       RETURNs a representation of all of the tasks associated with the current workOrder.
       This is an enhanced version of the return of method getWorkOrderTasks below.
       On return:
         * In the following, $elementId is typically an elementId but also can be:
            * null, meaning "general, no element". In theory, we would understand 0 or an empty string also to mean this.
            * (This should no longer occur in v2020-4 or later) PHP_INT_MAX meaning "multiple elements"
            * (Introduced in v2020-4) a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements"
         * $elementgroups[$elementId]['element']: an Element object
         * $elementgroups[$elementId]['elementName']: normally, just what it says; the PHP_INT_MAX case uses 'Other Tasks (Multiple Elements Attached)',
            and if $elementId is a comma-separated list of elementIds, this is an analogous list of element names, but with spaces. Apparently
            when the index is null for "General", this is null, not the string "General"
         * $elementgroups[$elementId]['elementId']: normally, just what it says. Redundant to the $elementId: always identical to that,
            including when the index is null for "General".
         * $elementgroups[$elementId]['tasks'] unlike the return of method getWorkOrderTasks,
           is an associative array of workOrderTasks, indexed by workOrderTaskId.
           It will contain the same workOrderTasks that were returned by WorkOrder method getWorkOrderTasks,
           with the same internal structure for each task, but differently indexed.
         * $elementgroups[$elementId]['maxdepth'] is the height of the "tallest" tree extending from an
           explicit task to a task with no parent. (However if, based on $class, we do not show a
           particular task, that task always counts as having only a depth of 1.)
         * $elementgroups[$elementId]['render']: multi-dimensional array using massaged taskIds as its indexes
           (each index is 'a' prefixed to a taskId) to represent the relevant subset of the abstract task hierarchy
           Also, for any "real" workOrderTask (explicitly represented in DB table WorkOrderTask) the array at the relevant level will
           also have an index 'wots', with its value being an array of WorkOrderTask objects.
           E.g. $render['a35']['a210']['a455']['wots'][$i] is a workOrderTask Object.
           For internal nodes there can be only one element in that last array, but at the leaf it is *possible*
           for the same abstract task to be used more than once in a workOrder, even for the same job element.
           NOTE that for "real" workOrderTasks -- ones that exist explicitly in DB table WorkOrderTask, rather than merely being
           constructed as the necessary parent of some other workOrderTask -- there will be a ['wots'] value even for an "internal"
           node. So, in the given example, if there is a real workOrderTask with taskId==210, then $render['a35']['a210']['wots']
           will be an array containing a single workOrderTask Object for a particular job element or combination of elements(single
           because an internal node is basically structural, so there is no reason to have more than one such workOrderTask for a given workOrder).
         * $elementgroups[$elementId]['gold']: A flat array indicating the order to display workOrderTasks for this element and
           providing information needed to display them correctly.
           See function buildTaskTreeLeadingToWorkOrderTasks immediately above for explanation of the 'gold' structure.
    */
    public function getWorkOrderTasksTree(&$errCode=false) {
        $errCode = false;
        /* [BEGIN MARTIN COMMENT]
        Array
        (
                [a32] => Array
                (
                        [wots] => Array
                        (
                                [0] => WorkOrderTask Object

        CAN PROBABLY DEAL WITH SHOWING IN CONTRACT AND INVOICE
        BY ITERATING THE "WOTS" ARRAY AND SEEING IF ANYTHING ABOVE IT NEEDS TO BE SHOWN.
        THIS MIGHT FAIL IF MORE THAN 1 LEVEL DEEP
        SO CHECK AGAINST DEEPER LEVELS TOO

        2017 04 27.  NEW IDEA.
        WHEN ITERATING THROUGH TO BUILD THE TREE JUST DON'T DO THE TREE FOR STUFF
        THAT IS NOT VISIBLE.  THAT WAY NO UNNECESSARY STUFF WILL BE BUILT.
        CHECK AND PASS FORWARD THE INFO TO SAY IF ITS VISIBLE OR NOT.
        WILL NEED TO PASS INTO THIS METHOD IF IT IS FOR A CONTRACT OR INVOICE.
        should just be a simple process of NOT calling the climbTree method for any given task if
        it's not visible.
        then persist ONLY the tasks in the contract saving.  should be able to just rebuild the fake
        entries again as usual. (hopefully!)

        [END MARTIN COMMENT]
        */

        $elementgroups = $this->getWorkOrderTasks(false, 'normal'); // First argument might affect sort order of returned array, not
                                                                    //  well understood; in any case, it does nothing more than that.
                                                                    // Second argument no longer relevant 2020-07-10.

        // $elementgroups at this point is scratch for our return, which  will be an enhanced version of that.

        // Martin said circa June 2018 (this is a paraphrase) that elementgroup helps people understand
        //  how the workOrderTasks relate back to elements (e.g. individual buildings) so they can better
        //  understand the workOrder content of the job from their point of view.
        // Martin never explicitly defined the term elementGroup, and it is not present in the database schema, but
        //  if I (JM 2020-07-10) understand correctly, it is either an element as such, or one of two special cases.
        //  $elementId here (and elsewhere) identifies an elementGroup and can be any of the following:
        //  * null - array $elementgroupdata represents "general" workOrderTasks, not associated with any particular element
        //  * (This should no longer occur in v2020-4 or later) PHP_INT_MAX - array $elementgroupdata represents workOrderTasks each associated with more than one element
        //  * any other positive numeric value should be an elementId (primary key in DB table 'element'), and array $elementgroupdata represents
        //    workOrderTasks associated with that particular element
        //  * (Introduced in v2020-4) a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements"

        // For each elementGroup that has a non-empty member array 'tasks',
        //  we go through a loop that will restructure that 'tasks' data,
        //  replacing it with a reworked 'tasks', plus an array 'render',
        //  an integer 'maxdepth', and an array 'gold'.
        // Before 2020-07-10, $elementId was $egkey; I (JM) believe the new name is clearer, even though there are special cases for values 0 and
        //  form multiple elementIds.
        // Before 2020-07-10, $elementgroupdata was $elementgroup; I (JM) believe the new name is clearer, because it is data ABOUT an element or group.
        foreach ($elementgroups as $elementId => $elementgroupdata) {
            // NOTE as discussed in documentation of the return of getWorkOrderTasks, that the array $elementgroups will be very sparse. The 'isset' test will let
            //  us skip the meaningless elements; >>>00001 it might make more sense to test just if ($elementgroupdata), I believe the result would be the same. - JM 2020-07-10
            if (isset($elementgroupdata['tasks'])) { // I (JM) believe this never fails
                if (is_array($elementgroupdata['tasks'])) { // I (JM) believe this never fails
                    $rawtasks = array();
                    $maxdepth = 0;
                    $render = array();
                    // $wot is a WorkOrderTask object, so $rawtasks as we build it will be indexed by WorkOrderTaskId.
                    foreach ($elementgroupdata['tasks'] as $wot) {
                        $rawtasks[$wot->getWorkOrderTaskId()] = $wot;
                        $task = new Task($wot->getTaskId()); // Task as against WorkOrderTask

                        // For each task, we use the Task method climbTree to get an array of Task objects,
                        //  ordered beginning with a task with no parent, down to the present task.
                        // $ladder here was previously called $tree, but it is a single linear path from root to leaf, so $ladder is more appropriate.
                        $ladder = $task->climbTree();

                        $debug_level = 0;

                        if (count($ladder)) {
                            if ($debug_level >=1 ) {
                                // Debug to show that all is as expected at this point.
                                $debug = '';
                                foreach ($ladder as $tkey => $node) {
                                    $debug .= "$tkey => {$node->getTaskId()}; ";
                                }
                                $this->logger->info2('1598989575', $debug);
                                unset($debug);
                            }

                            // using PHP "references" here, which are sort of like pointers but not quite.
                            // This is a bit esoteric, see https://www.php.net/manual/en/language.references.php etc. for documentation.
                            $pointer = &$render;
                            // At this point $ladder is an array of Task objects in order DOWN
                            // the hierarchy, from a task with no parent to the current task.
                            // In each case, all we really care about here are the respective taskIds.
                            // So, consider the case where the respective taskIds are 35, 210, 455.
                            foreach ($ladder as $tkey => $node) {
                                $taskId = $node->getTaskId();

                                // In the example given above, as we loop, $pointer will successively be
                                //  $render; $render['a35']; $render['a35']['a210']
                                // The last time through the foreach loop, at the bottom of the loop we will
                                //  set it to $render['a35']['a210']['a455'], but we will never use that.

                                // Initialize next level of multidimensional array $render, if we didn't already do so while handling
                                //  some other workOrderTask. If this is an internal node, we'll use this on the next interation of the loop.
                                //  If it is a leaf, we will use it immediately to attach $wot.
                                if (!isset($pointer['a'. $taskId])) {
                                    if ($debug_level >=2 ) {
                                        $this->logger->info2('1598989898', 'Creating array for "a'. $taskId .'" at level ' . $tkey);
                                    }
                                    $pointer['a'. $taskId] = array();
                                }

                                if ($tkey == (count($ladder) - 1)) {
                                    // Last one, $taskId is the taskId for the task underlying this workOrderTask

                                    // Initialize array of workOrderTasks for this task, if not already done.
                                    if (!isset($pointer['a'. $taskId]['wots'])) {
                                        $pointer['a'. $taskId]['wots'] = array();
                                    }
                                    // Attach this workOrderTask (there may be others with the same taskId, hence the array).
                                    $pointer['a'. $taskId]['wots'][] = $wot;
                                }

                                // The following rigamarole is apparently necessary in PHP to do the equivalent of "advancing a pointer".
                                // Can't write $pointer=$pointer['a'. $taskId], the semantics are wrong.
                                $new_pointer = &$pointer['a'. $taskId];
                                unset($pointer);
                                $pointer = &$new_pointer;
                                unset($new_pointer);
                                // (end of rigamarole)
                            }
                            unset($pointer);
                        }

                        if (count($ladder) > $maxdepth) {
                            $maxdepth = count($ladder);
                        }
                    } // END foreach ($elementgroupdata['tasks']: we've looked over the workOrderTasks for this element (or elementgroup).

                    // So at this point:
                    // - $render contains a multi-dimensional associative array indexed by successive taskIds (with 'a' prefixed to each taskId)/
                    // - throughout the $render hierarchy, "real" tasks each have an additional index "wots" whose corresponding
                    //   value is a numerically-indexed array of workOrderTask objects.
                    // - The 'wots' array for an internal node has exactly one workOrderTask.
                    // - At each leaf, the 'wots' array potentially has more than one workOrderTask, because the same (abstract) task
                    //   can be used more than once in a workOrder (and element).
                    // Remember that we are within the context of a particular element (or for a collection of elements).

                    $this->gold = array();
                    $this->buildTaskTreeLeadingToWorkOrderTasks($render, $this->gold, 0);

                    $elementgroups[$elementId]['tasks'] = $rawtasks; // NOTE that this is an overwrite, now mapping the workOrderTaskId to the
                                                                     //  WorkOrderTask object; before (on return from method getWorkOrderTasks)
                                                                     //  the indexes in array wer arbitrary small integers, now they are workOrderTaskIds.
                    $elementgroups[$elementId]['render'] = $render;  // multi-dimensional array using massaged taskIds ('a' prefixed to a taskId) to represent
                                                                     //  a subset of the (abstract) task hierarchy, and eventually leading to an array of
                                                                     //  workOrderTasks that share the same taskId. Often there will be only one element in that
                                                                     //  last array.
                                                                     // E.g. $render['a35']['a210']['a455']["wots"][$i] is a workOrderTask Object
                    $elementgroups[$elementId]['maxdepth'] = $maxdepth; // max task depth for this element
                    $elementgroups[$elementId]['gold'] = $this->gold; // A flat array indicating the order to display workOrderTasks for this element
                } else {
                    $this->logger->error2('1599754881', "\$elementgroupdata['tasks'] was not an array");
                    $errCode = true; // getting error message for user.
                }
            } else {
                $this->logger->error2('1599754896', "\$elementgroupdata['tasks'] was not set");
                $errCode = true; // getting error message for user.
            }
        } // END foreach ($elementgroups...

        return $elementgroups;
    } // END public function getWorkOrderTasksTree

    // For this workOrder & the specified teamPosition (or, if that turns up nothing,
    //  then for the job corresponding to this workOrder & the specified teamPosition),
    //  return the appropriate row from DB table team as an associative array.
    // INPUT $teamPositionId the specified teamPosition, e.g. TEAM_POS_ID_CLIENT" or
    //  TEAM_POS_ID_DESIGN_PRO.
    // INPUT $onlyOne: Boolean. If true and there is more than one match, then
    //  the return will be an empty array. (It's OK if there is one hit on workOrder
    //  and one on Job; in that case, we will never look at the latter.)
    // INPUT $onlyActive: Boolean. If true, then query will be limited to rows with
    //   active=1. >>>00026 NOTE that as of 2019-03-01 this appears to apply only
    //   to team members explicitly associated with the WorkOrder; if we have to fall
    //   back to team members associated with the Job, then this is ignored.
    // INPUT $ignoreJob: Boolean. If true, do NOT fall back to the Job. (added for v2020-4)
    public function getTeamPosition($teamPositionId, $onlyOne = true, $onlyActive = false, $ignoreJob=false) {
        $positions = array();

        // Select based on WorkOrder
        $query = " SELECT * FROM " . DB__NEW_DATABASE . ".team ";
        $query .= " WHERE id = " . intval($this->getWorkOrderId()) . " ";
        $query .= "  AND inTable = " . intval(INTABLE_WORKORDER) . " ";
        $query .= "  AND teamPositionId = " . intval($teamPositionId) . " ";
        if ($onlyActive) {
            $query .= "  AND active = 1 ";
        }
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDB("1601505002", "Hard DB error", $this->db);
            return $positions; // [CP] Maybe we should return an error code, but in this moment consider not necessary
        } else  {
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
            // >>>00002 possibly improve on the following logging, but it's better than nothing
            $this->logger->error2('1571870521', "getTeamPosition unexpected multiple rows for [positionId = $teamPositionId, workOrderId = " .
                intval($this->getWorkOrderId()) ."]" . ($onlyActive ? ' restricted to active' : ''));

            return array();
        }

        if (!count($positions) && !$ignoreJob) {
            // Select based on Job
            $j = new Job($this->getJobId());
            // OLD CODE REMOVED 2019-10-24 JM
            // $positions = $j->getTeamPosition($teamPositionId, $onlyOne);
            // BEGIN REPLACEMENT CODE 2019-10-24 JM
            // Addressing http://bt.dev2.ssseng.com/view.php?id=27, add $onlyActive here.
            $positions = $j->getTeamPosition($teamPositionId, $onlyOne, $onlyActive);
            // END REPLACEMENT CODE 2019-10-24 JM
        }

        return $positions;
    } // END public function getTeamPosition

    // Removes specified task from the workOrder. More specifically,
    //  verifies that this task is associated with this workOrder and
    //  that it has no associated row in DB table workOrderTaskTime.
    // If that checks out, deletes the relevant row from DB table workOrderTask.
    // Note that the task remains in the DB, just no longer attached to this workOrder.
    //  That makes complete sense, because DB table Task operates at a different
    //  level of abstraction, more of a template, not related to a particular project.
    public function deleteWorkOrderTask($workOrderTaskId) {
        $workOrderTask = new WorkOrderTask($workOrderTaskId);

        if ($workOrderTask) {
            if (intval($workOrderTask->getWorkOrderTaskId()) == $workOrderTaskId) {
                if (intval($workOrderTask->getWorkOrderId()) == intval($this->getWorkOrderId())) {
                    $times = $workOrderTask->getWorkOrderTaskTime();

                    if (!count($times)) {
                        $query = " DELETE FROM " . DB__NEW_DATABASE . ".workOrderTask ";
                        $query .= " WHERE workOrderTaskId = " . intval($workOrderTask->getWorkOrderTaskId());

                        $result = $this->db->query($query);
                        if (!$result) {
                            $this->logger->errorDb("637433030101040606", "deleteWorkOrderTask() : Hard DB error", $this->db);
                        } else {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    } // END public function deleteWorkOrderTask


    /*
      RETURNS an array of associative arrays, each corresponding to a task associated with this workOrder.
          Content of the per-task associative arrays:
            * Top-level index can be any of:
               * an elementId (normal case, task associated with one element)
               * (This should no longer occur in v2020-4 or later) PHP_INT_MAX meaning "multiple elements"
               * (Introduced in v2020-4) a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements"
               * >>>00001: we need to study this further: what happens if there is wote.workOrderTaskElementId == NULL, which it sure
                 looks to me (JM) can happen because we do a LEFT JOIN with workOrderTaskElement and we can have a
                 "general" workOrderTask with no element, which apparently uses NULL as an index.
            * Each defined value (with the index just described) is an associative array.
                * For normal single-element tasks the values in this array are:
                    * 'element': an Element object
                    * 'elementName': just what it says
                    * 'elementId': just what it says, indentical to the top-level index, including null for "General"
                    * 'tasks': array of WorkOrderTask objects, one for each workOrderTask associated with this element.
                * For PHP_INT_MAX (shouldn't occur in v2020-4 or later) the values in this array are:
                    * 'element': false
                    * 'elementName': 'Other Tasks (Multiple Elements Attached)'
                    * 'elementId': always PHP_INT_MAX, redundant to the index
                    * 'tasks': array of WorkOrderTask objects, one for each workOrderTask associated with multiple elements.
                    NOTE that there is no explicit array of elementIds for these. You would have to get that by using methods of the WorkOrderTask class.
                 * For a comma-separated string of elementIds the values in this array are:
                    * 'element': that same comma-separated string of elementIds
                    * 'elementName': an analogous list of element names, but with spaces.
                    * 'elementId': comma-separated string of elementIds
                    * 'tasks': array of WorkOrderTask objects, one for each workOrderTask associated with precisely this set of elements.

        JM 2020-07-09: I did some cleanup here while working on http://bt.dev2.ssseng.com/view.php?id=122#c771. In some cases I did NOT
        keep a copy of the old code inline, consult SVN if you need the history.

        Here and in functions that use and elaborate this structure:
        * JM 2020-07-28: >>>>>>00032 'tasks' here is misleading, these are really 'wots'
    */
    private function getWorkOrderTasks() {
        // JM 2020-07-13: before today, $tasksPerElement was just $tasks, awfully easy to confuse with the index 'tasks' two levels down.
        // I've changed this even in commented-out old code.
        $tasksPerElement = array();

        // >>>00022 SELECT wo.*, t.* ignores the possibility (certainty?) of conflicting column names
        // >>>00004 might want to limit to current customer
        /* BEGIN REPLACED 2020-07-28 JM simplifying query
        $query = "SELECT wo.*, t.*, wote.workOrderTaskElementId, e.elementId, e.elementName ";
        // END REPLACED 2020-07-28 JM
        */
        // BEGIN REPLACEMENT 2020-07-28 JM
        $query = "SELECT wo.workOrderTaskId, e.elementId, e.elementName ";
        // END REPLACEMENT 2020-07-28 JM
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderTask wo ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".task t ON wo.taskId = t.taskId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wote ON wo.workOrderTaskId = wote.workOrderTaskId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".element e ON wote.elementId = e.elementId ";
        $query .= "WHERE wo.workOrderId = " . intval($this->getWorkOrderId()) . " ";
        /* BEGIN REPLACED 2020-07-28 JM simplifying query: got rid of input !$forDisplay (always passed in false)
        if (!$forDisplay) {
            $query .= "ORDER BY e.elementId, t.parentId, t.sortOrder";
        } else {
            $query .= "ORDER BY t.parentId, t.sortOrder";
        }
        $query .= ";";
        */
        // BEGIN REPLACEMENT 2020-07-28 JM
        $query .= "ORDER BY e.elementId, t.parentId, t.sortOrder;";
        // END REPLACEMENT 2020-07-28 JM

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $wot = new WorkOrderTask($row['workOrderTaskId']);
                /* BEGIN REPLACED JM 2020-07-09, this was very confusing because it is typical for the same elementId to occur many times, even dozens,
                   so as we looped we kept creating and overwriting Element objects. Similar considerations for elementName and elementId, but at
                   least those are just strings, not objects. ALSO note that we did not initialize array $tasksPerElement[$row['elementId']] or
                   $tasksPerElement[$row['elementId']]['tasks'] before appending to it, which is "barely legal" PHP.
                $tasksPerElement[$row['elementId']]['element'] = new Element($row['elementId']);
                $tasksPerElement[$row['elementId']]['elementName'] = $row['elementName'];
                $tasksPerElement[$row['elementId']]['elementId'] = $row['elementId'];
                $tasksPerElement[$row['elementId']]['tasks'][] = $wot;

                // END REPLACED JM 2020-07-09
                */
                // BEGIN REPLACEMENT JM 2020-07-09
                if (!array_key_exists($row['elementId'], $tasksPerElement)) {
                    $tasksPerElement[$row['elementId']] = array();
                    $tasksPerElement[$row['elementId']]['element'] = new Element($row['elementId']);
                    $tasksPerElement[$row['elementId']]['elementName'] = $row['elementName'];
                    $tasksPerElement[$row['elementId']]['elementId'] = $row['elementId'];
                    $tasksPerElement[$row['elementId']]['tasks'] = array(); // NOTE that these are workOrderTasks, not tasks, despite the index name.
                }
                $tasksPerElement[$row['elementId']]['tasks'][] = $wot;
                // END REPLACEMENT JM 2020-07-09
            }
        } else {
            $this->logger->errorDb('1594331458', "Hard DB error", $this->db);
        }

        /* BEGIN REPLACED 2020-09-09 JM
        $wotcount = array();
        // JM 2020-07-13: previously, $tasksForOneElement in the following was called '$task';
        //  the rename should make the code a lot clearer. Also made some changes that clarify intent.
        foreach ($tasksPerElement as $tasksForOneElement) {
            if (isset($tasksForOneElement['tasks'])) { // See comment at top of this function for why we need to check this: there are gaps in the array.
                                                       // BUT actually JM suspects 2020-09-09 that the way PHP really works we do NOT need to check this.
                $wots = $tasksForOneElement['tasks'];
                if (is_array($wots)) { // JM: pretty certain this never fails
                    foreach ($wots as $wot) {
                        $wotId = $wot->getWorkOrderTaskId();
                        if (!array_key_exists($wotId, $wotcount)) {
                            $wotcount[$wotId] = 0;
                        }
                        $wotcount[$wotId] += 1;
                        unset($wotId);
                    }
                }
            }
        }

        $wotIdsWithMultipleElementIds = array();

        foreach ($wotcount as $wckey => $wc) {
            if (intval($wc) > 1) {
                $wotIdsWithMultipleElementIds[] = $wckey;
            }
        }
        // If at least one workOrderTask has more than one associated element...
        if (count($wotIdsWithMultipleElementIds)) {
            $others = array(); // workOrderTasks with multiple elements, indexed by workOrderTaskId so we only get one entry
                               // for each such workOrderTask

            // JM 2020-09-09: prior to 2020-07, $elementId in the following was called '$tkey'; $tasksForOneElement was called '$task'.
            // Also, I did some cleanup 2020-07-09 to clarify intent.
            // This is all commented out because it was reworked again 2020-09-09 JM to get away from PHP_INT_MAX as a catch-all
            //   for multi-element tasks, reworking those with a string index that is a comma-separated array of elementIds.
            foreach ($tasksPerElement as $elementId => $tasksForOneElement) {
                if (isset($tasksForOneElement['tasks'])) { // See comment at top of this function for why we need to check this.
                    $wots = $tasksForOneElement['tasks'];
                    if (is_array($wots)) { // JM: pretty certain this never fails
                        $newwots = array();
                        foreach ($wots as $wot) {
                            $wotId = $wot->getWorkOrderTaskId();
                            if (in_array($wotId, $wotIdsWithMultipleElementIds)) {
                                // In conjunction with the assignment right after the "foreach ($wots..." loop,
                                // remove this workOrderTaskId from $tasksPerElement[$elementId]['tasks'], and add it to $others;
                                // Then later we will add that back appropriately to $tasksPerElement[] with an appropriate index
                                $others[$wotId] = $wot;
                            } else {
                                $newwots[] = $wot;
                            }
                            unset ($wotId);
                        }
                        $tasksPerElement[$elementId]['tasks'] = $newwots;
                    }
                }
            }

            $tasksPerElement[PHP_INT_MAX] = array();

            $tasksPerElement[PHP_INT_MAX]['element'] = false;
            $tasksPerElement[PHP_INT_MAX]['elementName'] = 'Other Tasks (Multiple Elements Attached)';
            $tasksPerElement[PHP_INT_MAX]['elementId'] = PHP_INT_MAX;
            $tasksPerElement[PHP_INT_MAX]['tasks'] = array();

            foreach ($others as $other) {
                $tasksPerElement[PHP_INT_MAX]['tasks'][] = $other;
            }
        }
        // END REPLACED 2020-09-09 JM
        */
        // BEGIN REPLACEMENT 2020-09-09 JM
        $wotIdToWot = Array();
        $wotIdToMultipleElementIdString = Array();
        $wotIdToMultipleElementNameString = Array();
        foreach ($tasksPerElement as $tasksForOneElement) {
            $wots = $tasksForOneElement['tasks'];
            foreach ($wots as $wot) {
                $wotId = $wot->getWorkOrderTaskId();
                if (!array_key_exists($wotId, $wotIdToMultipleElementIdString)) {
                    // Encountering this $wot for the first time in the *outer* foreach-loop
                    $wotIdToWot[$wotId] = $wot;
                    $elements = $wot->getWorkOrderTaskElements(); // We rely on these coming in a predictable order, ascending by elementId.
                    if (count($elements) > 1) {
                        $wotIdToMultipleElementIdString[$wotId] = '';
                        $wotIdToMultipleElementNameString[$wotId] = '';
                        foreach($elements as $element) {
                            if ($wotIdToMultipleElementIdString[$wotId]) {
                                $wotIdToMultipleElementIdString[$wotId] .= ','; // no space after comma
                            }
                            if ($wotIdToMultipleElementNameString[$wotId]) {
                                $wotIdToMultipleElementNameString[$wotId] .= ', '; // space after comma
                            }
                            $wotIdToMultipleElementNameString[$wotId] .= $element->getElementName();
                            $wotIdToMultipleElementIdString[$wotId] .= $element->getElementId();
                        }
                    }
                }
            }
        }

        $multipleElementIdStringToWots = Array();
        foreach ($wotIdToMultipleElementIdString as $wotId => $multipleElementIdString) {
            if (!array_key_exists($multipleElementIdString, $multipleElementIdStringToWots)) {
                $multipleElementIdStringToWots[$multipleElementIdString] = Array();
            }
            $multipleElementIdStringToWots[$multipleElementIdString][] = $wotIdToWot[$wotId];
        }

        foreach ($multipleElementIdStringToWots as $multipleElementIds => $wots) {
            $tasksPerElement[$multipleElementIds] = array();

            $firstWotId = $wots[0]->getWorkOrderTaskId(); // for things that should be the same for all of thes workOrderTasks, arbitrarily grab the first.

            $tasksPerElement[$multipleElementIds]['element'] = $wotIdToMultipleElementIdString[$firstWotId];
            $tasksPerElement[$multipleElementIds]['elementName'] = $wotIdToMultipleElementNameString[$firstWotId];
            $tasksPerElement[$multipleElementIds]['elementId'] = $wotIdToMultipleElementIdString[$firstWotId];
            $tasksPerElement[$multipleElementIds]['tasks'] = array();

            foreach ($wots as $wot) {
                $tasksPerElement[$multipleElementIds]['tasks'][] = $wot;
            }
        }

        // Now get rid of multi-element wots that are attached to single elements.
        foreach ($tasksPerElement as $elementId => $tasksForOneElement) {
            if (is_string($elementId) && strpos($elementId, ',') !== false) {
                // multi-element
                // $this->logger->info2('JDEBUG 1', 'continue for $tasksPerElement[' . $elementId . ']'); // just debug
                continue;
            }
            $wots = $tasksForOneElement['tasks'];

            // Numerically indexed array, and we'll be removing elements, so we need to traverse backward
            for ($ix=count($wots)-1; $ix >= 0; --$ix) {
                $wotId = $wots[$ix]->getWorkOrderTaskId();
                if (array_key_exists($wotId, $wotIdToMultipleElementIdString)) {
                    // This is handled in the multiples, get rid of it here
                    // $this->logger->info2('JDEBUG 2', 'kill $tasksPerElement[' . $elementId . '][\'tasks\'][' . $ix . '], $wotId =' . $wotId);  // just debug
                    array_splice($tasksPerElement[$elementId]['tasks'], $ix, 1);
                }
                // else $this->logger->info2('JDEBUG 3', 'keep $tasksPerElement[' . $elementId . '][\'tasks\'][' . $ix . '], $wotId =' . $wotId);  // just debug
            }
            if (count($tasksPerElement[$elementId]['tasks']) == 0) {
                // Nothing left: all wots for this element are multi-element
                //  So we have nothing at all to pass back for this element.
                unset($tasksPerElement[$elementId]);
            }
        }
        // END REPLACEMENT 2020-09-09 JM
        return $tasksPerElement;
    } // END private function getWorkOrderTasks

    /* Based on the workOrderId, does a join of DB tables WorkOrderTask, Task (joined on taskId),
       WorkOrderTaskElement (joined on workOrderTaskId), and Element,
       returning all of the columns from WorkOrderTask and Task, plus the elementId and elementName.
       These are RETURNed in an associative array indexed by workOrderTaskId.

       Before 2020-09-04, $wots here was misleadingly called $tasks
       Each $wot[workOrderTaskId] is itself an associative array with the following elements:
         * 'wot': the canonical associative array representing the corresponding row returned by the SQL SELECT described above.
         * 'elements': $wots[workOrderTaskId]['elements'][elementId] = elementId. As of 2020-09-04, this is true even for elementId == 0.
    */
    public function getWorkOrderTasksRawWithElements($blockAdd) {
        $wots = array();

        /* BEGIN REPLACED 2020-09-04 JM: radically reduced what columns we will return
        $query = " select wo.*,t.*,  wote.workOrderTaskElementId,e.elementId, e.elementName ";
        $query .= " from " . DB__NEW_DATABASE . ".workOrderTask wo ";
        $query .= " join " . DB__NEW_DATABASE . ".task t on wo.taskId = t.taskId ";
        $query .= "  left join " . DB__NEW_DATABASE . ".workOrderTaskElement wote  on wo.workOrderTaskId = wote.workOrderTaskId  ";
        $query .= " left join " . DB__NEW_DATABASE . ".element e on wote.elementId = e.elementId ";
        $query .= " where wo.workOrderId = " . intval($this->getWorkOrderId()) . "  ";
        if ($this->user) {
            // [BEGIN MARTIN COMMENT]
            // need the is null here because if nobody is attached to a task then it wont get it since it looks for who is assigned first
            // i hope the syntax is ok !

            //	$query .= " and (cp.customerId = " . intval($this->user->getCustomer()->getCustomerId()) . " or cp.customerId is null )  ";
            // [END MARTIN COMMENT]
        }

        // END REPLACED 2020-09-04 JM
        */

        // BEGIN REPLACEMENT 2020-09-04 JM
        $query = "SELECT wot.workOrderTaskId, wot.workOrderId, wot.internalTaskStatus, t.description, e.elementId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderTask wot ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".task t ON wot.taskId = t.taskId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wote ON wot.workOrderTaskId = wote.workOrderTaskId  ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".element e ON wote.elementId = e.elementId ";
        $query .= "WHERE wot.workOrderId = " . intval($this->getWorkOrderId()) . " ";
        if($blockAdd == true) {
            $query .= "AND wot.internalTaskStatus = 5 ";
        }
        // END REPLACEMENT 2020-09-04 JM

        $result = $this->db->query($query);
        if ($result) {
            // if ($result->num_rows > 0) { // REMOVE, unneeded, 2020-09-04 JM
                while ($row = $result->fetch_assoc()) {
                    // BEGIN ADDED 2020-10-27 JM, more correct
                    if (! isset($wots[$row['workOrderTaskId']])) {
                        $wots[$row['workOrderTaskId']] = Array();
                    }
                    // END ADDED 2020-10-27 JM
                    $wots[$row['workOrderTaskId']]['wot'] = $row;
                    if (intval($row['elementId'])) {
                        // BEGIN ADDED 2020-10-27 JM, more correct
                        if (! isset($wots[$row['workOrderTaskId']]['elements'])) {
                            $wots[$row['workOrderTaskId']]['elements'] = Array();
                        }
                        // END ADDED 2020-10-27 JM
                        $wots[$row['workOrderTaskId']]['elements'][$row['elementId']] = $row['elementId'];
                    }
                    // BEGIN ADDED 2020-09-04 JM
                    else {
                        // treat null as 0, "General"
                        $wots[$row['workOrderTaskId']]['elements'][0] = 0;
                    }
                    // END ADDED 2020-09-04 JM
                } // END while
            // } // REMOVE, unneeded, 2020-09-04 JM
        } else {
            $this->logger->errorDB("637429484940348934", "Hard DB error", $this->db);
        }

        return $wots;
    } // END getWorkOrderTasksRawWithElements


    // RETURN an array of associative arrays: the canonical representation of
    //  all rows & columns from table workOrderDetail that match the current workOrderId,
    //  WITH AN ADDITIONAL COLUMN detailId in each row (that is, an additional
    //  element 'detailId' in each associative array).
    // INPUT $showHidden: quasi-Boolean. Unless $showHidden is truthy, this is limited
    //  to workOrderDetail.hidden = 0.
    // NOTE that the Details are in a totally separate database, undocumented as of 2019-03-01.
    public function getWorkOrderDetails($showHidden = 0) {
        $ret = array();
        $details = array();

        $query = " SELECT * ";
        $query .= " FROM " . DB__NEW_DATABASE . ".workOrderDetail ";
        $query .= " WHERE workOrderId = " . intval($this->getWorkOrderId()) . " ";
        if (!intval($showHidden)) {
            $query .= " AND hidden = 0 ";
        }

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDB("637429483084904850", "Hard DB error", $this->db);
        } else {
            while ($row = $result->fetch_assoc()) {
                $details[] = $row;
            }
        }

        // [BEGIN MARTIN COMMENT]
        // fix later ... maybe store the detailid in this table too or
        // refine how these details are shown ... i.e. by revision or detail.
        //  for now just fet relevant info
        // looping instead of doing join above .. for clarity
        // [END MARTIN COMMENT]
        foreach ($details as $detail) {
            $query = " SELECT detailId "; // reworked 2020-02-28 JM, Was " select * " but we are really just getting detailId
            $query .= " FROM " . DB__DETAIL_DATABASE . ".detailRevision ";
            $query .= " WHERE detailRevisionId = " . intval($detail['detailRevisionId']) . " ";

            $detail['detailId'] = 0;

            $result = $this->db->query($query);
             // $result here is the return from the query of Details database table detailRevision
            if (!$result) {
                $this->logger->errorDB("637429481833603923", "Hard DB error", $this->db);
            } else {
                while ($row = $result->fetch_assoc()) {
                    $detail['detailId'] = $row['detailId'];
                }
            }

            // We then assign $ret[] = $detail to build a row in the array that this function will return.
            // JM 2019-03-01: So, looking at the above code, $ret[i]['detailId'] defaults to 0; if there is a single
            //  matching entry in Details database table detailRevision then we will get $ret[i]['detailId']
            //  from that row; if there is more than one such matching entry, we will get a value from a
            //  row essentially at random, because there is no ORDER BY in the SELECT.
            //  So let's hope that either there is always at most one such row, or tht if there is
            //  more than one they will have the same value for 'detailId'!
            $ret[] = $detail;
        }

        return $ret;
    } // END public function getWorkOrderDetails

    // RETURNs an array of WorkOrderTask objects, one for each task associated with this workOrder.
    public function getWorkOrderTasksRaw(&$errCode = false, $errorId="1601499293") {
        $workordertasks = array();
        $errCode = false;

        $query = " SELECT workOrderTaskId "; // reworked 2020-02-28 JM, Was " select * " but we are really just getting workOrderTaskId
        $query .= " FROM " . DB__NEW_DATABASE . ".workOrderTask wo ";
        $query .= " WHERE wo.workOrderId = " . intval($this->getWorkOrderId()) . "  ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDB($errorId, "Hard DB error", $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $wot = new WorkOrderTask($row['workOrderTaskId']);
                $workordertasks[] = $wot;
            }
        }
        return $workordertasks;
    } // END public function getWorkOrderTasksRaw

    /* RETURNs an array of associative arrays, one for each task associated with this workOrder.

       Because this uses * in a SQL selection with a join, I (JM) am not going to try to fully document
       the content of those associative arrays (not sorting through possible name conflicts), but basically it's:
        all columns of the WorkOrderTask table
        all columns of the Task table
        workOrderTaskPerson.workOrderTaskPersonId
        workOrderTaskElement.workOrderTaskElementId
        'elementId' & 'elementName' from the element table.
    >>>00004: CAUTION: the getTasks method has a SQL query that may rely on there being only one customer (SSS).
    In the case where there is a $this->user (I--JM--believe that's the normal case), we normally want to
    associate task to customer via a JOIN with customerPerson. However, it is possible that no person
    has been assigned to the task. Current code picks that up by including everything where there is
    no customerPerson assigned (SQL code is "or cp.customerId is null"). I see two possibilities here:
    the test for customerId is actually irrelevant, in which case this code is really not needed,
    or it is relevant and this relies on there being only one customer.

    REMOVED 2020-11-11 JM as a minor part of addressing http://bt.dev2.ssseng.com/view.php?id=256 (Order of
    tasks within an element of a WorkOrder should be consistent): the only remaining call to this threw away the result!
    */
    /*
    public function getTasks() {
        $tasks = array();

        $query = "SELECT wot.*, t.*, wotp.workOrderTaskPersonId, cp.legacyInitials, wote.workOrderTaskElementId, e.elementId, e.elementName ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderTask wot ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskPerson wotp ON wot.workOrderTaskId = wotp.workOrderTaskId ";
        $query .= "JOIN " . DB__NEW_DATABASE . ".task t ON wot.taskId = t.taskId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wote ON wot.workOrderTaskId = wote.workOrderTaskId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".element e ON wote.elementId = e.elementId ";
        $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".customerPerson cp ON wotp.personId = cp.personId ";
        $query .= "WHERE wot.workOrderId = " . intval($this->getWorkOrderId()) . " ";

        if ($this->user) {
            // BEGIN MARTIN COMMENT
            // need the is null here because if nobody is attached to a task then it wont get it since it looks for who is assigned first
            // i hope the syntax is ok !
            // END MARTIN COMMENT

            $query .= "AND (cp.customerId = " . intval($this->user->getCustomer()->getCustomerId()) . " OR cp.customerId IS NULL)  ";
        }

        $query .="ORDER BY e.elementId, wote.workOrderTaskId;";

        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        } else {
            $this->logger->errorDb('1605113310', "Hard DB error", $this->db);
        }

        return $tasks;
    } // END public function getTasks
    */

    // RETURNs true if all tasks related to this workOrder have status STATUS_TASK_DONE, false otherwise.
    public function allTasksDone() {
        $tasks = $this->getWorkOrderTasksRaw();
        foreach ($tasks as $task) {
            if (intval($task->getTaskStatusId()) != STATUS_TASK_DONE) {
                return false;
            }
        }
        return true;
    }

    // Update several values for this workOrder.
    // INPUT $val typically comes from $_REQUEST.
    //  An associative array containing some or all of the following elements:
    //   * 'workOrderDescriptionTypeId'
    //   * 'description'
    //   * 'tempNote'
    //   * 'genesisDate' - in 'm/d/Y' form
    //   * 'deliveryDate' - in 'm/d/Y' form
    //   * 'intakeDate' - in 'm/d/Y' form
    //   * 'isVisible'
    //   * 'contractNotes'
    //   * 'fakeInvoice'
    //   Several of these elements may no longer really be relevant 2019-03-01.
    //   Any or all of these may be present. >>>00016: Maybe more validation?
    public function update($val) {

        if (!is_array($val)) {
            $this->logger->error2('637376769850631588', 'update WorkOrder => expected array as input, got something not an array');
            return false;
        }

        if (isset($val['workOrderDescriptionTypeId']) && (trim($val['workOrderDescriptionTypeId']) != '')) {
            $workOrderDescriptionTypeId = intval($val['workOrderDescriptionTypeId']);
            // NOTE that all we are doing here is validating workOrderDescriptionTypeId
            $query = "SELECT workOrderDescriptionTypeId ";
            $query .= "FROM " . DB__NEW_DATABASE . ".workOrderDescriptionType ";
            $query .= "WHERE workOrderDescriptionTypeId = " . $workOrderDescriptionTypeId . ";";
            $result = $this->db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    $this->setWorkOrderDescriptionTypeId($workOrderDescriptionTypeId);
                } else {
                    $this->logger->error2('1582916215', "Invalid \$workOrderDescriptionTypeId=$workOrderDescriptionTypeId");
                }
            } else {
                $this->logger->errorDb('1582916226', 'Hard error in WorkOrder:update', $this->db);
            }
        }

        if (isset($val['description'])) {
            $this->setDescription($val['description']);
        }

        /* BEGIN REMOVED 2020-09-21 JM
        if (isset($val['tempAmount'])) {
            $tempAmount = isset($val['tempAmount']) ? $val['tempAmount'] : 0;
            $this->setTempAmount($tempAmount);
        }
        END REMOVED 2020-09-21 JM
        */

        if (isset($val['tempNote'])) {
            $this->setTempNote($val['tempNote']);
        }

        if (isset($val['genesisDate'])) {
            $genesisDate = '0000-00-00 00:00:00';

            $parts = explode("/", $val['genesisDate']);
            if (count($parts) == 3) {
                $genesisMonth = intval($parts[0]);
                $genesisDay = intval($parts[1]);
                $genesisYear = intval($parts[2]);

                $genesisMonth = str_pad($genesisMonth,2,'0',STR_PAD_LEFT);
                $genesisDay = str_pad($genesisDay,2,'0',STR_PAD_LEFT);
                $genesisYear = str_pad($genesisYear,4,'0',STR_PAD_LEFT);

                $genesisDate = $genesisYear . '-' . $genesisMonth . '-' . $genesisDay . ' 00:00:00';
            }
            $this->setGenesisDate($genesisDate); //this is Validated in the setter.
            // 2020-10-23 JM: >>>00001 but is it really, effectively? (Parallel issues for the other dates that follow, of course.)
            // 1) Clearly this is expecting m/d/Y input, which we turn into Y-m-d h:m:s, where the h:m:s is always '00:00:00'. What if
            //    it gets input "3/17/21"? It looks to me like that gets turned into '2100-03-17 00:00:00' instead of either
            //    '2021-03-17 00:00:00' or throwing an error
            // 2) I would expect we need something like Validate::verifyDate(trim($val['genesisDate']), true, 'm/d/Y'); that is just off the top
            //    of my head, though I haven't spent much time thinking about this.
        }

        if (isset($val['deliveryDate'])) {
            $deliveryDate = '0000-00-00 00:00:00';
            $parts = explode("/", $val['deliveryDate']);
            if (count($parts) == 3) {
                $deliveryMonth = intval($parts[0]);
                $deliveryDay = intval($parts[1]);
                $deliveryYear = intval($parts[2]);

                $deliveryMonth = str_pad($deliveryMonth,2,'0',STR_PAD_LEFT);
                $deliveryDay = str_pad($deliveryDay,2,'0',STR_PAD_LEFT);
                $deliveryYear = str_pad($deliveryYear,4,'0',STR_PAD_LEFT);

                $deliveryDateTest = $deliveryYear . $deliveryMonth . $deliveryDay; // used for testing

                // George 2020-12-03. Get and transform Genesis Date.
                $genesisDateTest = $this->getGenesisDate();
                $genesisDateTest = str_replace("00:00:00", "",$genesisDateTest);
                $genesisDateTest = str_replace("-", "",trim($genesisDateTest));

                if( intval($genesisDateTest) <= intval($deliveryDateTest) ) {
                    $deliveryDate = $deliveryYear . '-' . $deliveryMonth . '-' . $deliveryDay . ' 00:00:00';
                    $this->setDeliveryDate($deliveryDate); // good
                } else {
                    $this->logger->error2('637426078607773529', "Delivery Date is not valid, input given: " . $val['deliveryDate']);
                    $this->setDeliveryDate($deliveryDate); // set to '0000-00-00 00:00:00'
                }

            }
            $this->setDeliveryDate($deliveryDate); //this is Validated in the setter.
        }

        if (isset($val['intakeDate'])) {
            $intakeDate = '0000-00-00 00:00:00';
            $parts = explode("/", $val['intakeDate']);
            if (count($parts) == 3) {
                $intakeMonth = intval($parts[0]);
                $intakeDay = intval($parts[1]);
                $intakeYear = intval($parts[2]);

                $intakeMonth = str_pad($intakeMonth,2,'0',STR_PAD_LEFT);
                $intakeDay = str_pad($intakeDay,2,'0',STR_PAD_LEFT);
                $intakeYear = str_pad($intakeYear,4,'0',STR_PAD_LEFT);

                $intakeDate = $intakeYear . '-' . $intakeMonth . '-' . $intakeDay . ' 00:00:00';
            }
            $this->setIntakeDate($intakeDate); //this is Validated in the setter.
        }
        // Still inside function update.

        if (isset($val['isVisible']) && trim($val['isVisible']) != '') {
            $this->setIsVisible($val['isVisible']); // Validation and Log is done in setter.
        }

        if (isset($val['contractNotes'])) {
            $this->setContractNotes($val['contractNotes']);
        }

        if (isset($val['fakeInvoice'])) {
            $this->setFakeInvoice($val['fakeInvoice']);
        }

        return $this->save();

    } // END public function update

    // UPDATEs same fields handled by public function update.
    public function save() {
        $query = " UPDATE " . DB__NEW_DATABASE . ".workOrder  SET ";
        $query .= " workOrderDescriptionTypeId = " . intval($this->getWorkOrderDescriptionTypeId()) . " ";
        $query .= " ,workOrderStatusId = " . intval($this->getWorkOrderStatusId()) . " ";
        $query .= " ,isVisible = " . intval($this->getIsVisible()) . " ";
        $query .= " ,description = '" . $this->db->real_escape_string($this->getDescription()) . "' ";
        $query .= " ,genesisDate = '" . $this->db->real_escape_string($this->getGenesisDate()) . "' ";
        $query .= " ,deliveryDate = '" . $this->db->real_escape_string($this->getDeliveryDate()) . "' ";
        $query .= " ,intakeDate = '" . $this->db->real_escape_string($this->getIntakeDate()) . "' ";
        $query .= " ,contractNotes = '" . $this->db->real_escape_string($this->getContractNotes()) . "' ";
        $query .= " ,fakeInvoice = " . intval($this->getFakeInvoice()) . " ";

        /* BEGIN REMOVED 2020-09-21 JM
        if (is_numeric($this->getTempAmount())) {
            $query .= " ,tempAmount = " . $this->db->real_escape_string($this->getTempAmount()) . " ";
        }
        // END REMOVED 2020-09-21 JM
        */

        $query .= " ,tempNote = '" . $this->db->real_escape_string($this->getTempNote()) . "' ";
        $query .= " WHERE workOrderId = " . intval($this->getWorkOrderId()) . " ";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDB('637350763534133562', "Hard DB error", $this->db);
            return false;
        }

        return true;
    }

    // RETURNs an array of associative arrays, where each array represents a team member
    //  associated with this WorkOrder. Note that this does NOT return team members who
    //  are associated only with the Job.
    // Content of each such associative array:
    //  * 'teamId' - primary key into DB table team
    //  * 'inTable' - always INTABLE_WORKORDER
    //  * 'companyPersonId' - identifies this team member; primary key into companyPerson table
    //  * 'role' - E.g. "Designer", "Developer", "Project Manager". Open-ended text
    //  * 'description' - another string like role, more programmatic: typically comes from description in table TeamPosition
    //  * 'active' - quasi-boolean (0 or 1); 1 means an active team member.
    //  * 'teamPositionId' - e.g. TEAM_POS_ID_CLIENT, TEAM_POS_EOR; over a dozen possibilities can be found in inc/config.php
    //  * 'personId' - identifies this team member; primary key into Person table
    //  * 'firstName', 'lastName' - name of team member
    //  * 'companyName' - company for this team member, always matches 'companyPerson'
    //  * 'name' - name of team position, not person
    //  * 'tpdescription' - description of team position; for anything that isn't quite old, should match 'description'
    // INPUT $active: quasi-boolean (0 or 1); if 1, restrict to active team members.
    public function getTeam($active = 0, &$errCode=false) {
        $errCode=false;
        $team = array();

        $query = " SELECT t.teamId, t.inTable, t.companyPersonId, t.role, t.description, t.active, tp.teamPositionId  ";
        $query .= " ,p.personId  ";
        $query .= " ,p.firstName  ";
        $query .= " ,p.lastName ";
        $query .= " ,c.companyName  ";
        $query .= " ,tp.name  ";
        $query .= " ,tp.description as tpdescription  ";
        $query .= " FROM " . DB__NEW_DATABASE . ".team t ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".companyPerson cp ON t.companyPersonId = cp.companyPersonId ";
        $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".teamPosition tp ON t.teamPositionId = tp.teamPositionId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".person p ON cp.personId = p.personId ";
        $query .= " JOIN " . DB__NEW_DATABASE . ".company c ON cp.companyId = c.companyId ";

        // BEGIN Martin comment
        // 'id' will probably be different things
        // depending on what the inTable is
        // or at least i think that was my original thought :)
        // END Martin comment
        //
        // JM: More precisely: inTable here is INTABLE_WORKORDER, so id is workOrderId.
        // If intable were INTABLE_JOB, id would be jobId.
        $query .= " WHERE t.id = " . intval($this->getWorkOrderId()) . " ";
        $query .= " AND t.inTable = " . intval(INTABLE_WORKORDER) . " ";
        if (intval($active)) {
            $query .= " AND t.active = 1 ";
        }
        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb("637425093155275091", "WorkOrder::getTeam() Hard DB error.", $this->db);
            $errCode = true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $row['workOrderDescription'] = $this->getDescription();
                $team[] = $row;
            }
        }

        return $team;
    } // END public function getTeam

    // George 2021-11-19. Not used anymore. We only have one contract!
    // RETURNs an array of Contract objects associated with this WorkOrder.
    // INPUT $allow_uncommitted - introduced 2020-01-02 JM - Boolean, if true
    //  then uncommitted contracts will be included in the return.
    public function getContracts($allow_uncommitted=false, &$errCode=false) {
        $errCode = false;
        $contracts = array();

        $query  = "SELECT c.contractId "; // George 2020-12-10. Before was Select *
        $query .= "FROM " . DB__NEW_DATABASE . ".contract c ";
        $query .= "WHERE workOrderId = " . intval($this->getWorkOrderId()) . ' ';
        $query .= $allow_uncommitted ? '' : " AND committed = 1 ";
        $query .= "ORDER BY contractId ASC;"; // effectively chronological

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb("637425260748987464", "getContracts() Hard DB error.", $this->db);
            $errCode = true;
        } else {
            while($row = $result->fetch_assoc()) {
                $contract = new Contract($row['contractId'], $this->user);
                $contracts[] = $contract;
            }
        }

        return $contracts;
    } // END public function getContracts





    // RETURNs an Contract object associated with this WorkOrder.
    public function getContractWo(&$errCode=false) {
        $errCode = false;
        $contract = array();
        $query  = "SELECT c.contractId "; // George 2020-12-10. Before was Select *
        $query .= "FROM " . DB__NEW_DATABASE . ".contract c ";
        $query .= "WHERE workOrderId = " . intval($this->getWorkOrderId()) . ' ';


        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb("637728497217623020", "getContract() Hard DB error.", $this->db);
            $errCode = true;
        } else {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $contract = new Contract($row['contractId'], $this->user);
            }


        }

        return $contract;
    } // END public function getContract





    // Create a new contract associated with this workOrder, RETURN Contract object.
    public function newContract() {
        // >>>00028 The queries here probably should be in one transaction.
        // >>>00017 Also, it would make more sense to do the SELECT before the INSERT;
        //  then what is done here in an UPDATE could be part of the INSERT.
        // [CP] the transaction is not possible because without the commited insert is not posible to go further with the rest of the logic
        $contract = false;
        $job = new Job($this->getJobId());

        $name = $job->getName();
        $number = $job->getNumber(); // George 2020-12-11. Not used.

        $query = " INSERT INTO " . DB__NEW_DATABASE . ".contract(workOrderId, nameOverride) VALUES (";
            $query .= " " . intval($this->getWorkOrderId()) . " ";
            $query .= " ,'" . $this->db->real_escape_string($name) . "' ";
        $query .= ")";

        $this->db->query($query);
        $id = $this->db->insert_id;
        if (intval($id)) {
            // This query uses the trick that contractIds are assigned in increasing order,
            //  so the highest contractId should always be the most recent.
            $query  = " SELECT c.* ";
            $query .= " FROM " . DB__NEW_DATABASE . ".contract c ";
            $query .= " WHERE workOrderId = " . intval($this->getWorkOrderId());
            $query .= " AND committed = 1 ";
            $query .= " ORDER BY contractId DESC ";

            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $query = " UPDATE " . DB__NEW_DATABASE . ".contract SET ";
                    $query .= " data = '" . $this->db->real_escape_string($row['data']) . "' ";
                    $query .= " WHERE contractId = " . intval($id);
                    $this->db->query($query);
                }
            }

            $contract = new Contract($id, $this->user);
        }

        return $contract;
    } // END public function newContract

    // INPUT contractId - primary key in DB table Contract. If zero, then we
    //  simply want an uncommitted contract.
    // RETURN: (1) if contractId identifies a contract associated with this
    //  WorkOrder, then return a Contract object representing that contract.
    //  (2) Otherwise (e.g. the default case, $contractId == 0), insert a new contract
    //  into DB table Contract, associated with this WorkOrder and having the same
    //  data as the latest committed contract for this WorkOrder.
    //
    // >>>00026 JM thinks this is ill-conceived:
    //     We are never supposed to have more than one uncommitted contract for
    //     a given workOrder. This function, as written, can violate that: consider
    //     the case where (1) an uncommitted contract exists, associated with this
    //     WorkOrder, (2) $contractId is nonzero, but (3) $contractId does not represent that
    //     uncommitted contract associated with this WorkOrder. I (JM) believe
    //     that should be treated as an error rather than the current situation where
    //     it is just ignored and we generate another uncommitted contract.
    //     In fact, as written I don't think we ever hit the "return false" case.
    public function getContract($contractId = 0) {
        $query  = " SELECT c.contractId "; // George 2020-12-10. Before was Select *
        $query .= " FROM " . DB__NEW_DATABASE . ".contract c ";
        $query .= " WHERE workOrderId = " . intval($this->getWorkOrderId());
        if (intval($contractId)) {
            $query .= "  AND contractId = " . intval($contractId) . " ";
        } else {
            // This uses the trick that contractIds are assigned in increasing order,
            //  so the highest contractId should always be the most recent.
            // >>>00026 However, as noted above, ther is only supposed to be at most one
            //  uncommitted contract for a given WorkOrder, and by using 'desc limit 1'
            //  we are failing to spot a possible violation of that.
            $query .= " AND committed >= 0 ";
            $query .= " ORDER BY contractId DESC LIMIT 1 ";
        }

        $contract = false;

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb("637432094617790391", "getContract() Hard DB error.", $this->db);
            return false;
        } else {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $contract = new Contract($row['contractId'], $this->user);
                return $contract;
            } else {
                $contract = $this->newContract();
                return $contract;
            }
        }

    } // END public function getContract

    // Create a new invoice for this workOrder, optionally associated with a contract.
    // RETURNs Invoice object. In principle, can return false on failure, caller should
    //  check for that.
    //
    // There has been a lot of cleanup here in v2020-3 and v2020-4 (May-August 2020), enough
    //  so that I stopped trying to mark differences inline; if you need to see what changed, consult
    //  the history in SVN. - JM
    public function newInvoice($contractId = 0) {
        $invoice = false;
        $contract = false;
        $name = '';
        $contractId = intval($contractId);
        if ($contractId && Contract::validate($contractId, '1597955022')) { // '1597955022' gives a unique ID in the log so that you know the failure was in this context.
            $contract = new Contract($contractId);
            $name = trim( $contract->getNameOverride() );
        } else {
            $contractId = 0;
        }

        // If we can't get a name from a contract, get it from a job.
        if (!strlen($name)) {
            $job = new Job($this->getJobId());
            if ($job) {
                $name = $job->getName();
            } else {
                $this->logger->error2('1597952825', "No jobId for workorder " . $this->workOrderId);
            }
            unset ($job);
        }

        // >>>00028 The queries here probably should eventually be in one transaction.
        // [CP] as the sql query are mixed with class update method i do''t advise transaction

        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoice(workOrderId, nameOverride, contractId) VALUES (";
        $query .= intval($this->getWorkOrderId());
        $query .= " ,'" . $this->db->real_escape_string($name) . "' ";
        $query .= ", " . intval($contractId);
        $query .= ");";
        $result = $this->db->query($query);

        unset ($name); // just to be clear we don't use it past here

        $invoiceId = 0;
        if ($result) {
            $invoiceId = $this->db->insert_id;
            if (Invoice::validate($invoiceId, '1597953354')) { // '1597953354' gives a unique ID in the log so that you know the failure was in this context.
                $invoice = new Invoice($invoiceId, $this->user);
                if ($invoice) {
                    // NOTE: if we get this far, even if somehow the invoiceStatusTime insert fails, we will still do the "contract" part of this below
                    $initialInvoiceStatus = Invoice::getInvoiceStatusIdFromUniqueName('none');
                    if ($initialInvoiceStatus) {
                        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceStatusTime (invoiceStatusId, invoiceId, note) " .
                                  "VALUES " .
                                  "($initialInvoiceStatus," . intval($invoice->getInvoiceId()) . ",'auto add when invoice created');";
                        $result = $this->db->query($query);
                        if (!$result) {
                            $this->logger->errorDB('1590079670', "Hard DB error", $this->db);
                        }
                    } else {
                        $this->logger->error2('1590079679', "Cannot find invoiceStatus with uniqueName 'none'");
                    }
                    unset($initialInvoiceStatus);
                } else {
                    $this->logger->error2('1597953764', "Could not construct Invoice object from new invoiceId $invoiceId");
                }
            } // else Invoice::validate already logged for invalid invoiceId
        } else {
            $this->logger->errorDb('1597953617', "Failure to insert new invoice", $this->db);
        }

        if ($invoice) {
            if ($contract) {
                // A contract is associated with this invoice
                $data = $contract->getData();
                $store = $data;

                    // Grab all the tasks (really workOrderTasks) associated with this contract, mark them as "fromContract"
                    //  and update the 'data' (starting with v2020-4, 'data2') column (blob) for this new invoice accordingly.
                // >>>00017 Also, it would make more sense to do this SELECT before the INSERT
                    //  that creates the invoice; then what is done here in an UPDATE
                //   via $invoice->update() could be part of the INSERT.
                if (is_array($data)) {
                    foreach ($data as $ekey => $element) {
                        if (isset($element['tasks'])) {
                            $tasks = $element['tasks'];
                            if (is_array($tasks)) {
                                foreach ($tasks as $tkey => $task) {
                                    $task['task']['fromContract'] = '1';
                                    $task['fromContract'] = '1';
                                    $task['taskContractStatus'] = 9;
                                    $store[$ekey]['tasks'][$tkey] = $task;
                                }
                            }
                        }
                    }
                }
                $invoice->update(array('data' => $store));

                // Martin comment: this is a bit of a kludge to get contract billing profile assigned to the invoice too

                // >>>00017 JM again, it would make more sense to do this SELECT before the INSERT
                //  that creates the contract; then what is done here in an UPDATE of the invoice
                //   could be part of the INSERT. The INSERT into DB table invoiceBillingProfile would
                //   still have to wait until the invoice is created, though.

                // JM 2020-10-30: cleaned up some of the following & used class ShadowBillingProfile

                $query = "SELECT * FROM " . DB__NEW_DATABASE . ".contractBillingProfile ";
                $query .= "WHERE contractId = " . intval($contract->getContractId()) . ";";

                $contractBillingProfile = false;

                $result = $this->db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                            // We presume only one. If somehow there were more than one, we just take the first.
                        $row = $result->fetch_assoc();
                        $contractBillingProfile = $row; // row from DB table contractBillingProfile: we basically want to copy this to invoiceBillingProfile.
                    }
                } else {
                    $this->logger->errorDB('1597955619', "Hard DB error", $this->db);
                }

                if ($contractBillingProfile) {
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceBillingProfile ";
                    $query .= "(invoiceId, billingProfileId, shadowBillingProfile, companyPersonId) VALUES (";
                    $query .= intval($invoice->getInvoiceId());
                    $query .= ", " . intval($contractBillingProfile['billingProfileId']);
                    $query .= " ,'" . $this->db->real_escape_string($contractBillingProfile['shadowBillingProfile']) . "' ";
                    $query .= ", " . intval($contractBillingProfile['companyPersonId']) . ");";

                    $result = $this->db->query($query);
                    if (!$result) {
                        $this->logger->errorDB('1597955700', "Hard DB error", $this->db);
                    }

                    $shadowBillingProfile = new ShadowBillingProfile($contractBillingProfile['shadowBillingProfile']);

                    $mult = $shadowBillingProfile->getMultiplier();
                    if (filter_var($mult, FILTER_VALIDATE_FLOAT) === false) {
                        $mult = 1;
                    }

                    $query = "UPDATE " . DB__NEW_DATABASE . ".invoice ";
                    $query .= "SET clientMultiplier = " . $this->db->real_escape_string($mult) . " ";
                    $query .= "WHERE invoiceId = " . intval($invoice->getInvoiceId()) . ";";

                    $result = $this->db->query($query);
                    if (!$result) {
                        $this->logger->errorDB('1597955789', "Hard DB error", $this->db);
                    }

                    $invoice->update(array('clientMultiplier' => $mult));
                    $query= "update " . DB__NEW_DATABASE . ".workOrderTask set invoiceId =".$invoiceId." where workOrderId=".intval($this->getWorkOrderId()). " and (invoiceId=0 or invoiceId is NULL)";

                    $result = $this->db->query($query);

                }
            } else {
                // No contract is associated with this invoice
                $dataContract = [];
                $error_is_db=false;
                $overlaid=getContractData($this->getWorkOrderId(), $error_is_db);
                //$overlaid = overlay($this, $invoice, array()); // JM 2020-08-20: Kind of a hideous monster, but recently somewhat tamed.
                                                               // Some relevant documentation in inc/function.php for the function overlay().
                                                               // and more in this file in getWorkOrderTasksTree & related functions.

                $dataContract = [ "4" => $overlaid];
                $dataContractJson = json_encode($dataContract);
                $invoice->update(array(
                    'data' => $dataContractJson,
                ));

                $query= "update " . DB__NEW_DATABASE . ".workOrderTask set invoiceId =".$invoiceId." where workOrderId=".intval($this->getWorkOrderId()). " and (invoiceId=0 or invoiceId is NULL)";

                $result = $this->db->query($query);
            }

            $invoice->storeTotal();
        } // END if ($invoice)

        if (!$invoice) {
            $invoice = false;  // turn anything false-y into false
        }
        return $invoice;
    } // END public function newInvoice


    public function newInvoiceInternal($workOrderId) {
        $invoice = false;
        $contract = false;
        $name = '';
        $contractId=0;
        $contractId = intval($contractId);
        if ($contractId && Contract::validate($contractId, '1597955022')) { // '1597955022' gives a unique ID in the log so that you know the failure was in this context.
            $contract = new Contract($contractId);
            $name = trim( $contract->getNameOverride() );
        } else {
            $contractId = 0;
        }

        // If we can't get a name from a contract, get it from a job.
        if (!strlen($name)) {
            $job = new Job($this->getJobId());
            if ($job) {
                $name = $job->getName();
            } else {
                $this->logger->error2('1597952825', "No jobId for workorder " . $this->workOrderId);
            }
            unset ($job);
        }

        // >>>00028 The queries here probably should eventually be in one transaction.
        // [CP] as the sql query are mixed with class update method i do''t advise transaction

        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoice(workOrderId, nameOverride, contractId) VALUES (";
        $query .= intval($this->getWorkOrderId());
        $query .= " ,'" . $this->db->real_escape_string($name) . "' ";
        $query .= ", " . intval($contractId);
        $query .= ");";
        $result = $this->db->query($query);

        unset ($name); // just to be clear we don't use it past here

        $invoiceId = 0;
        if ($result) {
            $invoiceId = $this->db->insert_id;
            if (Invoice::validate($invoiceId, '1597953354')) { // '1597953354' gives a unique ID in the log so that you know the failure was in this context.
                $invoice = new Invoice($invoiceId, $this->user);
                if ($invoice) {
                    // NOTE: if we get this far, even if somehow the invoiceStatusTime insert fails, we will still do the "contract" part of this below
                    $initialInvoiceStatus = Invoice::getInvoiceStatusIdFromUniqueName('none');
                    if ($initialInvoiceStatus) {
                        $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceStatusTime (invoiceStatusId, invoiceId, note) " .
                                  "VALUES " .
                                  "($initialInvoiceStatus," . intval($invoice->getInvoiceId()) . ",'auto add when invoice created');";
                        $result = $this->db->query($query);
                        if (!$result) {
                            $this->logger->errorDB('1590079670', "Hard DB error", $this->db);
                        }
                    } else {
                        $this->logger->error2('1590079679', "Cannot find invoiceStatus with uniqueName 'none'");
                    }
                    unset($initialInvoiceStatus);
                } else {
                    $this->logger->error2('1597953764', "Could not construct Invoice object from new invoiceId $invoiceId");
                }
            } // else Invoice::validate already logged for invalid invoiceId
        } else {
            $this->logger->errorDb('1597953617', "Failure to insert new invoice", $this->db);
        }

        if ($invoice) {
            if (true) {
                // A contract is associated with this invoice
                $error_is_db=false;
                $dataInvoice = [];
                $outData  = getOutOfContractData($workOrderId, $error_is_db);

                if($error_is_db) {
                    $errorId = '637804395384266079';
                    $error = "We could not get the Contract data. Database Error. Error Id: " . $errorId; // message for User
                    $logger->errorDB($errorId, "getContractData() function failed.", $db);
                } else {
                    $dataContract = [ '4' => $outData];
                    $dataContractJson = json_encode($dataContract);
                    $invoice->update(array(
                        // send contract data for signed or voided.
                        'data' => $dataContractJson,
                    ));
                }
                $store = $invoice->getData();
                $query= "update " . DB__NEW_DATABASE . ".workOrderTask set invoiceId =".$invoiceId." where workOrderId=".intval($this->getWorkOrderId()). " and internalTaskStatus=5 and (invoiceId=0 or invoiceId is NULL)";
$this->logger->error2('1597953764', $query);
                $result = $this->db->query($query);
                if (!$result) {
                    $this->logger->errorDB('1647241305622', "Hard DB error", $this->db);
                }

                    // Grab all the tasks (really workOrderTasks) associated with this contract, mark them as "fromContract"
                    //  and update the 'data' (starting with v2020-4, 'data2') column (blob) for this new invoice accordingly.
                // >>>00017 Also, it would make more sense to do this SELECT before the INSERT
                    //  that creates the invoice; then what is done here in an UPDATE
                //   via $invoice->update() could be part of the INSERT.
/*                if (is_array($data)) {
                    foreach ($data as $ekey => $element) {
                        if (isset($element['tasks'])) {
                            $tasks = $element['tasks'];
                            if (is_array($tasks)) {
                                foreach ($tasks as $tkey => $task) {
                                    $task['task']['fromContract'] = '1';
                                    $task['fromContract'] = '1';
                                    $store[$ekey]['tasks'][$tkey] = $task;
                                }
                            }
                        }
                    }
                }
                $invoice->update(array('data' => $store));
  */
                // Martin comment: this is a bit of a kludge to get contract billing profile assigned to the invoice too

                // >>>00017 JM again, it would make more sense to do this SELECT before the INSERT
                //  that creates the contract; then what is done here in an UPDATE of the invoice
                //   could be part of the INSERT. The INSERT into DB table invoiceBillingProfile would
                //   still have to wait until the invoice is created, though.

                // JM 2020-10-30: cleaned up some of the following & used class ShadowBillingProfile

  /*              $query = "SELECT * FROM " . DB__NEW_DATABASE . ".contractBillingProfile ";
                $query .= "WHERE contractId = " . intval($contract->getContractId()) . ";";

                $contractBillingProfile = false;

                $result = $this->db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                            // We presume only one. If somehow there were more than one, we just take the first.
                        $row = $result->fetch_assoc();
                        $contractBillingProfile = $row; // row from DB table contractBillingProfile: we basically want to copy this to invoiceBillingProfile.
                    }
                } else {
                    $this->logger->errorDB('1597955619', "Hard DB error", $this->db);
                }

                if ($contractBillingProfile) {
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceBillingProfile ";
                    $query .= "(invoiceId, billingProfileId, shadowBillingProfile, companyPersonId) VALUES (";
                    $query .= intval($invoice->getInvoiceId());
                    $query .= ", " . intval($contractBillingProfile['billingProfileId']);
                    $query .= " ,'" . $this->db->real_escape_string($contractBillingProfile['shadowBillingProfile']) . "' ";
                    $query .= ", " . intval($contractBillingProfile['companyPersonId']) . ");";

                    $result = $this->db->query($query);
                    if (!$result) {
                        $this->logger->errorDB('1597955700', "Hard DB error", $this->db);
                    }

                    $shadowBillingProfile = new ShadowBillingProfile($contractBillingProfile['shadowBillingProfile']);

                    $mult = $shadowBillingProfile->getMultiplier();
                    if (filter_var($mult, FILTER_VALIDATE_FLOAT) === false) {
                        $mult = 1;
                    }

                    $query = "UPDATE " . DB__NEW_DATABASE . ".invoice ";
                    $query .= "SET clientMultiplier = " . $this->db->real_escape_string($mult) . " ";
                    $query .= "WHERE invoiceId = " . intval($invoice->getInvoiceId()) . ";";

                    $result = $this->db->query($query);
                    if (!$result) {
                        $this->logger->errorDB('1597955789', "Hard DB error", $this->db);
                    }

                    $invoice->update(array('clientMultiplier' => $mult));
                }
*/
            }

            $invoice->storeTotal();
        } // END if ($invoice)

        if (!$invoice) {
            $invoice = false;  // turn anything false-y into false
        }
        return $invoice;
    } // END public function newInvoice


    // RETURN an array of associative arrays, representing all invoices
    // associated with this WorkOrder, in forward chronological order.
    // Each associative array is the canonical representation of the content
    // of a row in DB table Invoice.
    public function getInvoices(&$errCode=false) {
        $errCode = false;
        $invoices = array();

        // This query uses the trick that invoiceIds are assigned in increasing order,
        //  so the highest contractId should always be the most recent.
        $query  = " SELECT i.invoiceId "; // George 2020-12-10. Before was Select *
        $query .= " FROM " . DB__NEW_DATABASE . ".invoice i ";
        $query .= " WHERE workOrderId = " . intval($this->getWorkOrderId());
        $query .= " ORDER BY invoiceId ASC ";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDB('637429424900439460', "getInvoices() Hard DB error", $this->db);
            $errCode = true;
        } else {
            while($row = $result->fetch_assoc()) {
                $invoice = new Invoice($row['invoiceId'], $this->user);
                $invoices[] = $invoice;
            }
        }

        return $invoices;
    } // END public function getInvoices

    // RETURN: Despite the singular in the function name, returns an array of
    //  CompanyPerson objects, where each person is a member of the team
    //  (see following remark), and teamPositionId = TEAM_POS_ID_DESIGN_PRO.
    //  "The team" will be the team specifically for this workOrder if there
    //  is at least one such design professional. If that array would be empty
    //  then "the team" will be the team for the corresponding job.
    // (Could still return an empty array if that also has no hits.)
    public function getDesignProfessional() {
        $team = $this->getTeam(1);
        $designpros = array();
        foreach ($team as $person) {
            if ($person['teamPositionId'] == TEAM_POS_ID_DESIGN_PRO) {
                $designpros[] = new CompanyPerson($person['companyPersonId']);
            }
        }

        if (count($designpros)) {
            return $designpros;
        }

        $job = new Job($this->getJobId());
        $designpros = $job->getDesignProfessional();
        return $designpros;
    } // END public function getDesignProfessional

    // Exactly parallel to getDesignProfessional, but with TEAM_POS_ID_CLIENT instead of TEAM_POS_ID_DESIGN_PRO.
    public function getClient() {
        $team = $this->getTeam(1);
        $clients = array();

        foreach ($team as $person) {
            if ($person['teamPositionId'] == TEAM_POS_ID_CLIENT) {
                $clients[] = new CompanyPerson($person['companyPersonId']);
            }
        }

        if (count($clients)) {
            return $clients;
        }

        $job = new Job($this->getJobId());
        $clients = $job->getClient();
        return $clients;
    } // END public function getClient

    // Inserts a new row in DB table workOrderStatusTime, using the three inputs,
    //  the current workOrderId, the current user, and (implicitly) the current time.
    //  Joe added code 2019-11-21 to validate $workOrderStatusId, & did some other cleanup at that time.
    //  Truncates $note if it's too long.
    // Further work 2020-06-09 JM: instead of old bitfield input $extra, use $customerPersonIds array.
    //  OK if this is null or an empty array.
    //  We use it to make entries in DB table wostCustomerPerson.
    // RETURN true on success, false on failure
    // >>>00002, >>>00016: really ought to validate $customerPersonIds
    private function setWorkOrderStatusTime($workOrderStatusId, $customerPersonIds, $note) {
        $workOrderStatusId = intval($workOrderStatusId);

        $note = trim($note);
        $note = substr($note, 0, 255); // >>>00002 truncates silently

        // Validate $workOrderStatusId
        $exists = false;
        if (!self::validateWorkOrderStatus($workOrderStatusId)) {
            $this->error2('1605720793', "invalid workOrderStatusId '$workOrderStatusId'");
            return false; // bail out on error
        }

        $query = "INSERT INTO " . DB__NEW_DATABASE . ".workOrderStatusTime(workOrderStatusId, workOrderId, " .
        // "extra, " . // REMOVED 2020-06-09 JM
        "personId, note) VALUES (";
        $query .= $workOrderStatusId . " ";
        $query .= ", " . intval($this->getWorkOrderId());
        // $query .= ", " . intval($extra) . " "; // REMOVED 2020-06-09 JM
        $query .= ", " . intval($this->getUser()->getUserId());
        $query .= ", '" . $this->db->real_escape_string($note) . "');";

        $result = $this->db->query($query);
        if (!$result) {
            $this->logger->errorDb('1574321475', "Hard DB error inserting into workOrderStatusTime", $this->db);
            return false; // bail out on error
        }

        // From here down, added 2020-06-09 JM
        if ($customerPersonIds) {
            $wostId = $this->db->insert_id;
            foreach ($customerPersonIds AS $customerPersonId) {
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".wostCustomerPerson ";
                $query .= "(workOrderStatusTimeId, customerPersonId) ";
                $query .= "VALUES ";
                $query .= "($wostId, $customerPersonId);";

                $result = $this->db->query($query);
                if (!$result) {
                    $this->logger->errorDb('1591740100', "Hard DB error inserting into wostCustomerPerson", $this->db);
                    return false; // bail out on error
                }
            }
        }
        return true;
    } // END private function setWorkOrderStatusTime


    /* Sets a status and, in some circumstances, sends an email (so this is a
       higher-level sort of thing than is mainly in this class). (>>>00017 might
       want to pull the email part up into more of a "business logic" layer, keep
       only the actual setting of status in this class).

       INPUT $workOrderStatusId - new status for this WorkOrder, foreign key
        into DB table workOrderStatus
       INPUT $customerPersonIds - array, each element is a primary key in DB table customerPerson.
         2020-06-09 JM: this replaces old bitfield input $extra.
        OK if this is null or an empty array.
        We use it to make entries in DB table wostCustomerPerson.
       INPUT $note: arbitrary note

       Only acts if workOrderStatusId is valid.

       Sends email if:
        (1) status is set to STATUS_WORKORDER_HOLD (or other status where $customerPersonIds
           is non-empty).
        OR
        (2) status is set to STATUS_WORKORDER_DONE (or other DONE status), and we are able to do that.
           Cannot do that if not all workOrderTasks are closed.

       For statuses that use $customerPersonIds, we build an email with information about the job and the
       engineering team members, and a link into Panther for this workOrder. This is sent
       to any person indicated by $customerPersonIds; also, if there is such a person, it is
       also sent to EMAIL_DEV and EMAIL_OFFICE_MANAGER (with a fiddled recipient
       name so they can see who it is really to).

       For DONE statuses, we will send an email to the recipients determined
       by getWorkOrderNotifyEmails in /inc/functions.php. The email gives the name
       of the workOrder (not the job as in the other email), link into Panther for
       this workOrder, indicates whether or not "all tasks done" for this workOrder,
       and indicates the user associated with this WorkOrder object as setting the "DONE" status.

       For other statuses, we just set the status, no email.

       Also, beginning with v2020-4, can set jobStatus active or inactive.

       Returns true on success (even if email fails), false on failure to make DB insertions.
    */
    public function setStatus($workOrderStatusId, $customerPersonIds, $note) {
        global $customer;
        if (intval($workOrderStatusId)) {
            $query  = "SELECT * ";
            $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatus ";
            $query .= "WHERE workOrderStatusId = " . intval($workOrderStatusId);

            $result = $this->db->query($query);
            if (!$result) {
                $this->logger->errorDb('1591740220', "Hard DB error validating workOrderStatusId", $this->db);
                return false; // bail out on error
            }

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $isDone = $row['isDone'];
                $statusName = $row['statusName'];
            } else {
                // Shouldn't ever happen, because we are in a private method, and the public method should already have validated the status
                $this->logger->error2('1591740276', "Invalid workOrderStatusId '$workOrderStatusId'");
                return false; // bail out on error
            }

            // BEGIN ADDED 2020-11-18 JM
            $job = new Job($this->getJobId()); // 2020-11-18 JM: this was already done in one case, but we'll now need it generally
            $workOrdersForJob = $job->getWorkOrders();
            // END ADDED 2020-11-18 JM


            if ($customerPersonIds) {
                $staffEngineers = $this->getTeamPosition(TEAM_POS_ID_STAFF_ENG, false);
                $eors = $this->getTeamPosition(TEAM_POS_ID_EOR, false);
                $leadEngineers = $this->getTeamPosition(TEAM_POS_ID_LEADENGINEER, false);
                $supportEngineers = $this->getTeamPosition(TEAM_POS_ID_SUPPORTENGINEER, false);

                $body = "A workorder changed to $statusName\n\n";
                $body .= "Job\n";
                $body .= "========================================\n";
                $body .= $job->getName() . "\n";
                $body .= $job->getNumber() . "\n\n";
                $body .= $this->buildLink() . "\n\n\n";
                $body .= "EORs\n";
                $body .= "========================================\n";
                foreach ($eors as $ekey => $eor) {
                    $cp = new CompanyPerson($eor['companyPersonId']);
                    $p = new Person($cp->getPersonId());
                    if ($ekey) {
                        $body .= "\n";
                    }
                    $body .= $p->getFormattedName(1);
                }
                $body .= "\n\n";
                $body .= "Staff Engineers\n";
                $body .= "========================================\n";
                foreach ($staffEngineers as $ekey => $eor) {
                    $cp = new CompanyPerson($eor['companyPersonId']);
                    $p = new Person($cp->getPersonId());
                    if ($ekey) {
                        $body .= "\n";
                    }
                    $body .= $p->getFormattedName(1);
                }
                $body .= "\n\n";

                $body .= "Lead Engineers\n";
                $body .= "========================================\n";
                foreach ($leadEngineers as $ekey => $eor) {
                    $cp = new CompanyPerson($eor['companyPersonId']);
                    $p = new Person($cp->getPersonId());
                    if ($ekey) {
                        $body .= "\n";
                    }
                    $body .= $p->getFormattedName(1);
                }
                $body .= "\n\n";

                $body .= "Support Engineers\n";
                $body .= "========================================\n";
                foreach ($supportEngineers as $ekey => $eor) {
                    $cp = new CompanyPerson($eor['companyPersonId']);
                    $p = new Person($cp->getPersonId());
                    if ($ekey) {
                        $body .= "\n";
                    }
                    $body .= $p->getFormattedName(1);
                }
                $body .= "\n\n";

                $body .= "\n\nThis was an auto generated email.";
                $address_mail_to = '';
                $mail = new SSSMail();
                $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
                if(environment()==ENVIRONMENT_PRODUCTION){
                    foreach ($customerPersonIds AS $customerPersonId) {
                        $customerPerson = new CustomerPerson($customerPersonId);
                        if ($customerPerson->getCustomer() == $customer) { // $customer is set in inc/config.php
                            list($target_email_address, $firstName, $lastName) = $customerPerson->getEmailAndName();
                            if ($target_email_address) {
                                $mail->addTo($target_email_address, $firstName);
                            } else {
                                $this->logger->error2('1591742489', "Cannot find email address for $firstName (personId = $p->getPersonId()) at customer " . $customer->getCustomerId());
                            }
                            $mail->addTo(EMAIL_DEV, $firstName);
                            $mail->addTo(EMAIL_OFFICE_MANAGER, OFFICE_MANAGER_NAME." (+$firstName)");
                            $mail->addTo(EMAIL_OFFICE_MANAGER, OFFICE_MANAGER_NAME." (+$firstName)");

                            if ($address_mail_to) {
                                $address_mail_to .= '; ';
                            }
                            $address_mail_to .= "$target_email_address <$firstName>, " . EMAIL_DEV . "<$firstName>";
                        } else {
                            $this->logger->error2('1591742501', "customerPersonId $customerPersonId does not correspond to the current customer! Won't be sending email.");
                        }
                    }
                } else {
                    $mail->addTo(EMAIL_TEST, "Panther test email"); /// [CP] 20200909 In case we are not in production the email will be sent to this test email defined in config.php
                }
                $mail->setSubject('Workorder set to EOR hold');
                $mail->setBodyText($body);
                $mail_result = $mail->send();
                if ($mail_result) {
                    //echo "ok"; // commented out by Martin before 2019
                } else {
                    //echo "fail"; // commented out by Martin before 2019
                }

                // NOTE THAT WE'VE SENT THE EMAIL, BUT HAVEN'T YET DONE THE INSERT!
            }

            if ($isDone) {
                $alltasks = false;

                if ($this->allTasksDone()) {
                    $this->setWorkOrderStatusTime($workOrderStatusId, $customerPersonIds, $note);
                    $alltasks = true;
                }

                // JM 2019-09-18 - Per discussion in http://bt.dev2.ssseng.com/view.php?id=23,
                //  we've decided to send email here only if $alltasks == true
                if ($alltasks == true) {
                    $recipients = getWorkOrderNotifyEmails();

                    $body = "This workorder was closed.\n\n";
                    $body .= "WorkOrder.\n";
                    $body .= "==========\n";
                    $body .= $this->getName() . "\n";
                    $body .= $this->buildLink();
                    $body .= "\n";

                    $body .= "\n\n\n\n";
                    $body .= "All Tasks Completed?\n";
                    $body .= "====================\n";
                    if ($alltasks) {
                        $body .= "YES\n";
                    } else {
                        // JM 2019-09-18: this should no longer ever arise.
                        $body .= "NO\n";
                    }

                    $body .= "\n\n\n\n";
                    $body .= "Person\n";
                    $body .= "====================\n";
                    $body .=  $this->getUser()->getFormattedName();
                    $body .= "\n";

                    $mail = new SSSMail();
                    $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
                    if(environment()==ENVIRONMENT_PRODUCTION){
                    foreach ($recipients as $recipient) {
                        $mail->addTo($recipient['address'], $recipient['name']);
                    }
                    } else {
                        $mail->addTo(EMAIL_TEST, "Panther test email");  /// [CP] 20200909 In case we are not in production the email will be sent to this test email defined in config.php
                    }

                    $mail->setSubject('Closed Workorder');
                    $mail->setBodyText($body);
                    $mail_result = $mail->send();

                    if ($mail_result) {
                        //echo "ok"; // commented out by Martin before 2019
                    } else {
                        //echo "fail"; // commented out by Martin before 2019
                    }
                }

                // BEGIN ADDED 2020-11-18 JM
                // Job has to have been active, since until just now this workOrder was active.
                // Check to see: are there any other active workOrders? If not, set the job inactive.
                $jobHasActiveWorkOrder = false;
                foreach ($workOrdersForJob as $workOrderForJob) {
                    if ( ! $workOrderForJob->getWorkOrderStatus()['isDone'] ) {
                        $jobHasActiveWorkOrder = true;
                        break;
                    }
                }
                if (!$jobHasActiveWorkOrder) {
                    // No active workOrder for this job, so set job inactive.
                    $job->setJobActive(false);
                }
                // END ADDED 2020-11-18 JM

            } else {
                // This is where we do the set in the case where !isDone
                $this->setWorkOrderStatusTime($workOrderStatusId, $customerPersonIds, $note);
                // BEGIN ADDED 2020-11-18 JM
                // Job now has an active workOrder, so whatever its state before, it's active now.
                $job->setJobActive(true);
                // END ADDED 2020-11-18 JM
            }
        }
    } // END public function setStatus

    /* DROPPED 2020-06-12 JM, we don't do this this way anymore, totally replaced this concept of "extras"
    // RETURNs a two-dimensional array (array of arrays) of associative arrays.
    // * The top-level array is indexed by the various workOrder statuses:
    //   STATUS_WORKORDER_HOLD, STATUS_WORKORDER_ACTIVE, STATUS_WORKORDER_DONE, STATUS_WORKORDER_RFP.
    //   (That's not actually exhaustive, but those are the only ones that are currently set, as of 2019-03.)
    // * The next level is indexed by the "extra" value, e.g. HOLD_EXTRA_EOR_DSF, HOLD_EXTRA_SCHEDULE,
    //   or RFP_EXTRA_WAIT_SIGN_PROP.
    // * Each of the associative arrays has two members, 'title' and 'grace'. For example, the respective
    //   values for the three examples just given for "extra" are ('title' => 'EOR DSF', 'grace' => 2),
    //   ('title' => 'Schedule', 'grace' => 14), and ('title' => 'Sent Waiting Signature', 'grace' => 14).
    public static function workOrderStatusExtras() {
        // BEGIN MARTIN COMMENT
        // config.php                           //////
        // ./inc/classes/WorkOrder.class.php    //////
        // ./fb/workorder.php                   //////
        // ./reviews.php                        //////
        // ./ajax/workorderstatus.php           //////
        // ./openworkorderscompany.php          //////
        // ./openworkordersemp.php              //////
        // ./openworkorders.php
        // END MARTIN COMMENT

        global $hold_status_titles_and_grace; // Added JM 2010-02-04

        $workOrderStatusExtra = array();

        // $hold_status_titles_and_grace is in /inc/config.php because it is
        //  configured for each customer.
        $workOrderStatusExtra[STATUS_WORKORDER_HOLD] = $hold_status_titles_and_grace;

        $workOrderStatusExtra[STATUS_WORKORDER_ACTIVE] = array();

        $workOrderStatusExtra[STATUS_WORKORDER_DONE] = array();


        $workOrderStatusExtra[STATUS_WORKORDER_RFP] = array(
            RFP_EXTRA_GEN_PROP => array('title' => 'Generate Proposal', 'grace' => 7),
            RFP_EXTRA_SEND_PROP => array('title' => 'Wait Send Proposal', 'grace' => 2),
            RFP_EXTRA_WAIT_SIGN_PROP => array('title' => 'Sent Waiting Signature', 'grace' => 14),
        );

        return $workOrderStatusExtra;

    } // END public static function workOrderStatusExtras
    */

    // RETURNs workOrder as associative array with members each built by the appropriate get method.
    // NOTE that as of 2019-03-04 this returns only some of the private variables
    //  of this class that represent values in DB table WorkOrder; e.g. omits
    //  code, InvoiceTxnId, fakeInvoice.
    //  tempNote added 2020-09-22 JM for v2020-4
    //  >>>00001 some or all of these
    //  omissions may be deliberate, because most of those values are at least somewhat vestigial,
    //  but for example the omission of code is quite surprising and of fakeInvoice somewhat so,
    //  more likely an oversight than a plan. - JM 2019-03
    public function toArray() {
        return array (
                'workOrderId' => $this->getWorkOrderId(),
                'jobId' => $this->getJobId(),
                'workOrderDescriptionTypeId' => $this->getWorkOrderDescriptionTypeId(),
                'description' => $this->getDescription(),
                'deliveryDate' => $this->getDeliveryDate(),
                'workOrderStatusId' => $this->getWorkOrderStatusId(),
                'genesisDate' => $this->getGenesisDate(),
                'intakeDate' => $this->getIntakeDate(),
                'isVisible' => $this->getIsVisible(),
                'contractNotes' => $this->getContractNotes(),
                'statusName' => $this->getStatusName(),
                'tempNote' => $this->getTempNote(),
                );
    }

    /*
	// This method was moved into the base class.
	private static function loadDB(&$db) {
	    if (!$db) {
	        $db =  DB::getInstance();
	    }
	}*/

	// Added 2020-01-29 JM
    // This is modeled on function insertWorkOrderTimeSummary in includes/workordertimesummary.php;
    //  the idea is to return just the bottom-line "Mult" calculated there, a ratio of revenues to costs.
    // Looks only at the latest invoice.
    // Returns null if no invoices for this workOrder.
    public function revenueMetric() {
        global $customer;
        $invs = $this->getInvoices();
        if (count($invs)) {
            $gtCost = 0;
            $elementgroups = $this->getWorkOrderTasksTree();
            foreach ($elementgroups as $elementgroup) {
                // >>>00014 NOTE that we test for the 'element' member being set, but then
                //  presume members 'elementId' and 'gold' will be set. A bit weird. Why trust one
                //  and not the other?
                if (isset($elementgroup['element']) || ($elementgroup['elementId'] == PHP_INT_MAX)) {
                    $element = 	$elementgroup['element'];
                    if ($element || ($elementgroup['elementId'] == PHP_INT_MAX)) {
                        if (isset($elementgroup['gold'])) {
                            $gold = $elementgroup['gold'];
                            foreach ($gold as $task) {
                                if ($task['type'] == 'real') {
                                    $wot = $task['data'];

                                    // Martin comment: NOTE :: read in method about passing customer here .. its a kludge
                                    $times = $wot->getWorkOrderTaskTimeWithRates($customer);
                                    foreach ($times as $time) {
                                        $gtCost += intval($time['cost']);
                                    }
                                }
                            }
                        }
                    }
                }
            } // END foreach ($elementgroups as $elementgroup) {

            $inv = $invs[count($invs) - 1]; // Get last invoice
            $invtotal = $inv->getTotal();
            $invTotaloverride = $inv->getTotalOverride();

            if (!is_numeric($invtotal)) {
                $invtotal = 0;
            }

            if (!is_numeric($invTotaloverride)){
                $invTotaloverride = 0;
            }

            $it = 0;

            if ($invTotaloverride > 0){
                $it = $invTotaloverride;
            } else {
                $it = $invtotal;
            }

            $mult = ($gtCost > 0) ? ($it / $gtCost) : 0;
        } // END if (count($invs)) {

        return isset($mult) ? $mult : null;
    } // END public function revenueMetric



    /**
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $levelOne. RETURNs an array of level one WOT ids for this specific workOrder.
    */
    public function getLevelOne(&$errCode = false) {
        $errCode = false;
        $allElements = array();
        $levelOne = array();

        $query = " SELECT e.elementId ";
        $query .= " FROM " . DB__NEW_DATABASE . ".element e ";
        $query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wo on wo.elementId = e.elementId ";
        $query .= " WHERE jobId = " . intval($this->getJobId()) ." group by e.elementId ";

        $result = $this->db->query($query);

        if (!$result) {
            $this->logger->errorDb('637799243915088373', 'getElements(): Hard DB error', $this->db);
            $errCode=true;
        } else {
            while ($row = $result->fetch_assoc()) {
                $allElements[] = $row['elementId'];
            }
        }

        $workOrderId = $this->getWorkOrderId();
        foreach($allElements as $value) {

            $query = "select workOrderTaskId,
            parentTaskId
            from    (select * from workOrderTask
            order by parentTaskId, workOrderTaskId) products_sorted,
            (select @pv := '$value') initialisation
            where   find_in_set(parentTaskId, @pv) and parentTaskId = '$value' and workOrderId = '$workOrderId'
            and     length(@pv := concat(@pv, ',', workOrderTaskId))";

            $result = $this->db->query($query);

            if (!$result) {
                $this->logger->errorDb('637799244989807140', 'getElements(): Hard DB error', $this->db);
                $errCode=true;
            } else {

                while( $row=$result->fetch_assoc() ) {
                    $levelOne[] = $row['workOrderTaskId'];
                }
            }

        }
        return $levelOne;
    }





    /**
        * @param integer $workOrderId: workOrderId to validate, should be an integer but we will coerce it if not.
        * @param string $unique_error_id: optional string, allows us to change what error ID shows up in the log on hard DB error.
        * @return true if the id is a valid workOrderId, false if not.
    */
    public static function validate($workOrderId, $unique_error_id=null) {
        global $db, $logger;
        WorkOrder::loadDB($db);

        $ret = false;
        $query = "SELECT workOrderId FROM " . DB__NEW_DATABASE . ".workOrder WHERE workOrderId=$workOrderId;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb($unique_error_id ? $unique_error_id : '1578694138', "Hard error", $db);
            return false;
        } else {
            $ret = !!($result->num_rows); // convert to boolean
        }
        return $ret;
    } // END public static function validate


    public static function errorToText($errCode) {
        $error = '';
        $errorId = 0;

        if($errCode == 0) {
            $errorId = '637176450263298190';
            $error = 'addWorkOrder method failed.';
        } else if($errCode == DB_GENERAL_ERR) {
            $errorId = '637176450437005665';
            $error = 'Database error.';
        } else if($errCode == DB_ROW_ALREADY_EXIST_ERR) {
            $errorId = '637176450570644354';
            $error = "Error input parameters, New Work Order already in use";
        } else {
            $error = "Unknown error, please fix them and try again";
            $errorId = "637176450800648635";
        }

        return array($error, $errorId);
    }

    // Get the Id of the unique workOrderStatus with useForReactivate==1 (all the others should be 0)
    // RETURNS false on error, otherwise the workOrderStatusId
    public static function getReactivateStatusId() {
        global $db, $logger;
        WorkOrder::loadDB($db);
        //$ret = false;
        $query = "SELECT workOrderStatusId FROM " . DB__NEW_DATABASE . ".workOrderStatus WHERE useForReactivate=1;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb('1591893179', "Hard error", $db);
            return false;
        } else if ($result->num_rows == 0) {
            $logger->errorDb('1591893199', "No workOrderStatus with useForReactivate=1", $db);
            return false;
        } else if ($result->num_rows >1) {
            $logger->errorDb('1591893222', "More than one workOrderStatus with useForReactivate=1", $db);
            // but we will continue and just return the first such
        }
        $row = $result->fetch_assoc();
        return $row['workOrderStatusId'];
    }

    // Get the Id of the unique workOrderStatus with isInitialStatus==1 (all the others should be 0)
    // RETURNS false on error, otherwise the workOrderStatusId
    public static function getInitialStatusId() {
        global $db, $logger;
        WorkOrder::loadDB($db);
        //$ret = false;
        $query = "SELECT workOrderStatusId FROM " . DB__NEW_DATABASE . ".workOrderStatus WHERE isInitialStatus=1;";
        $result = $db->query($query);

        if (!$result)  {
            $logger->errorDb('1591905884', "Hard error", $db);
            return false;
        } else if ($result->num_rows == 0) {
            $logger->errorDb('1591905899', "No workOrderStatus with isInitialStatus=1", $db);
            return false;
        } else if ($result->num_rows >1) {
            $logger->errorDb('1591905922', "More than one workOrderStatus with isInitialStatus=1", $db);
            // but we will continue and just return the first such
        }
        $row = $result->fetch_assoc();
        return $row['workOrderStatusId'];
    }

    /** Moved (and reworked): this was previously inc/functions.php function workOrderStatuses 2020-06-18 JM
        * @param bool $errCode, variable pass by reference. Default value is false.
        * $errCode is True on query failed.
        * @return array $ret. RETURNs content of DB table WorkOrderStatus as an array,
        *	each element of which is an associative array giving the canonical representation
        *	of the appropriate row from DB table WorkOrderStatus (column names as indexes).
        *   For any given parentId these will be in the correct displayOrder, but parentIds are not in any
        *   particular order, and typically you will want to pass the return to
        *	WorkOrder::getWorkOrderStatusHierarchy to arrange these in a hierarchy.
    */

    public static function getAllWorkOrderStatuses(&$errCode = false) {
        global $db, $logger;
        $errCode = false;
        WorkOrder::loadDB($db);

        $ret = array();

        $query  = "SELECT * ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatus ";
        $query .= "ORDER BY parentId, displayOrder";
        $result = $db->query($query);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ret[] = $row;
            }
        } else {
            $logger->errorDB('1592507669', "Hard DB error", $db);
            $errCode = true;
        }

        return $ret;
    }


    // >>>00006 The following to build a hierarchy is very similar to _admin/workorderstatus/index.php
    //  and several other places and could probably be common code in the WorkOrder class

    // Utility function for workOrder::buildWorkOrderStatusHierarchy
    private static function cmpDisplayOrder($a, $b) {
        if ($a['displayOrder'] == $b['displayOrder']) {
            return 0;
        }
        return ($a['displayOrder'] < $b['displayOrder']) ? -1 : 1;
    }

    // recursive function to build hierarchy of workOrderStatuses from:
    // INPUT $statuses equivalent to the output of WorkOrder::getAllWorkOrderStatuses()
    // The other two inputs are just for recursion.
    // RETURN: array of the rows in $statuses that represent top-level statuses, in display order;
    //  for each of these, there will be two additional indexes:
    //  'level' - 0 for top-level, increased by 1 for each successive level
    //  'children' - similar to the top-level array: this is basically a recursive use of the
    //   same structure.
    public static function getWorkOrderStatusHierarchy($statuses, $parentId=0, $level=0) {
        global $logger;
        // Prevent this going into crazy recursion
        if ($level > 50) {
            $logger->error2('1591294417', 'Runaway recursion in function getWorkOrderStatusHierarchy');
            exit();
        }

        $ret = Array();
        foreach ($statuses as $workOrderStatus) {
            if ($workOrderStatus['parentId'] == $parentId) {
                $workOrderStatus['children'] = WorkOrder::getWorkOrderStatusHierarchy($statuses, $workOrderStatus['workOrderStatusId'], $level+1);
                $workOrderStatus['level'] = $level;
                $ret[] = $workOrderStatus;
            }
        }
        usort($ret, array("WorkOrder", "cmpDisplayOrder"));
        return $ret;
    }

    // INPUT $workOrderStatusId: should be a primary key in DB table workOrderStatus.
    // RETURN true if $workOrderStatusId identifies a row in DB table workOrderStatus,
    //  false otherwise (including hard error.
    public static function validateWorkOrderStatus($workOrderStatusId) {
        global $db, $logger;
        WorkOrder::loadDB($db);

        $query = "SELECT workOrderStatusId ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatus  ";
        $query .= "WHERE workOrderStatusId = " . intval($workOrderStatusId);
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1574321450', "DB error SELECTing to validate workOrderStatusId", $db);
            return false; // bail out on error
        }
        return $result->num_rows > 0;
    }


    // INPUT $workOrderStatusId: should be a primary key in DB table workOrderStatus
    // RETURN true if the row in DB table workOrderStatus identified by $workOrderStatusId
    //  has a truthy value in column 'isDone'. Otherwise false, including if $workOrderStatusId
    //  is invalid.
    public static function workOrderStatusIsDone($workOrderStatusId) {
        global $db, $logger;
        WorkOrder::loadDB($db);

        $query = "SELECT isDone ";
        $query .= "FROM " . DB__NEW_DATABASE . ".workOrderStatus  ";
        $query .= "WHERE workOrderStatusId = " . intval($workOrderStatusId);
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1574321520', "DB error SELECTing to validate workOrderStatusId", $db);
            return false; // bail out on error
        }
        if ($result->num_rows == 0) {
            $logger->error2('1574321631', "Invalid workOrderStatusId '$workOrderStatusId'");
            return false;
        }
        $row = $result->fetch_assoc();
        return !! $row['isDone'];   // !! forces to Boolean
    }
}

?>