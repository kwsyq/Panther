<?php 
/* inc/classes/Financial.class.php

   Extends SSSEng, and should be constructed for current user. Unlike most 
   classes in the system, constructor does not take a $val argument with 
   an index or associative array: always constructed basically empty. 
   Has no data of its own, just the following public functions, which are 
   effectively (though not overtly) static:

   * getWOClosedTasksOpen
   * getWOOpenTasksClosed
   * getWONoInvoice
   * getWOClosedInvoiceOpen
   * getAwaitingDelivery
   * getAwaitingPayment
   Also, of course, a public constructor:
   * __construct(User $user = null)
*/


class Financial extends SSSEng {	
    // INPUT $user: User object, typically current user. 
    //  >>>00023: JM 2019-02-20: No way to set this later, so hard to see why it's optional.
    //  Probably should be required, or perhaps class SSSEng should default this to the
    //  current logged-in user, with some sort of default (or at least log a warning!)
    //  if there is none (e.g. running from CLI). 
    public function __construct(User $user = null) {	
        parent::__construct($user);		
    }	
    
    /*
    RETURNs an associative array with two elements:
      * 'other' is always an empty array.
      * 'workOrders' is an array of WorkOrder objects, one for each closed workOrder that has at least one open task.
    */  
    public function getWOClosedTasksOpen() {
        // [Martin commment] revisit this.  it will not scale forever !!	    
        $render = array();	    
        $jobs = array();
        
        // Select the entire DB table job, order by jobId, effectively chronological.
        // Make a Job object for each job, put them in an array $jobs.
        $query = "SELECT jobId FROM " . DB__NEW_DATABASE . ".job ORDER BY jobId;";
        $query="SELECT jobId, workOrderId FROM " . DB__NEW_DATABASE . ".workOrder 
            where workOrderStatusId=9 and workOrderId in 
            (select workOrderId from " . DB__NEW_DATABASE . ".workOrderTask where taskStatusId<>9) and jobId in (select jobId from job);";
            
        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            while ($row = $result->fetch_assoc()) {
                $j = new Job($row['jobId']);
                $jobs[] = $j;
            }
        } // >>>00002 else ignores failure on DB query! Does this throughout file, 
          // haven't noted each instance.
        
        // For each job, examine all corresponding workOrders; if status 
        //  indicates that the workOrder is done (the workOrder is closed), look at 
        //  all tasks for that workorder; if any have a taskStatusId other than 9, 
        //  then that is an open task. Keep track of which closed workOrders have open tasks. 
        foreach ($jobs as $job) {	        
            $workOrders = $job->getWorkOrders();
            foreach ($workOrders as $workOrder) {
                /* BEGIN REPLACED 2020-06-12 JM
                $status = $workOrder->getWorkOrderStatusId();
                if ($status == STATUS_WORKORDER_DONE) {	                
                // END REPLACED 2020-06-12 JM
                */
                // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                //$wo = new WorkOrder($workOrder); // 2021-01-04. Added by George.
                if ($workOrder->isDone()) {
                // END REPLACEMENT 2020-06-12 JM
                    $tasks = $workOrder->getWorkOrderTasksRaw(); 
                    $anyOpen = false;
                    foreach ($tasks as $task) {
                        // [BEGIN Martin comment]
                        // the isnumeric test here is because some rows were returned with no id
                        // this seems to be because the "load" in workordertask class
                        // didnt load properly because the underlying "task" didnt exist
                        
                        // e.g. workOrderTaskId 426 .. references taskId 26
                        // e.g. workOrderTaskId 65804  references taskId 0
                        
                        // will need to go back through and see which workOrderTasks references
                        // tasks that dont exist and figure out why that is	                    
                        
                        // select * from workOrderTask where taskId not in (select taskId from task);
                        // [END Martin comment]
                        
                        if (is_numeric($task->getTaskStatusId())) {
                            if ($task->getTaskStatusId() != 9) {	                            
                                $anyOpen = true;	                            
                            }
                        }	                    
                    }
                    
                    if ($anyOpen) {	                    
                        $render[] = $workOrder;	                    
                    }	                
                }
            }	        
        }
        
        $other = array();
        
        return array('other' => $other, 'workOrders' => $render);
    } // END public function getWOClosedTasksOpen
    

    /* RETURN an associative array with two elements:
       * 'other' is always an empty array.
       * 'workOrders' is an array of WorkOrder objects, one for each 
         open workOrder that has no open tasks. In addition to the usual 
         properties of a WorkOrder object, if the workOrder has never 
         been invoiced then there will be an additional property totalTime, 
         the sum of the times for its associated workOrderTasks (in minutes). 
    */
    public function getWOOpenTasksClosed() {
        
        // [Martin comment]revisit this.  it will not scale forever !!

            $render = array();
            $jobs = array();

            // Select the entire DB table job, order by jobId, effectively chronological. Make a Job object for each. 
            $query = "SELECT jobId FROM " . DB__NEW_DATABASE . ".job 
                where jobId in (select jobId from " . DB__NEW_DATABASE . ".workOrder where workOrderStatusId<>9) ORDER BY jobId;";
            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                while ($row = $result->fetch_assoc()) {
                    $j = new Job($row['jobId']);
                    $jobs[] = $j;
                }
            }
            
            // For each job, examine all corresponding workOrders; if status
            //  indicates that the workOrder is NOT done (the workOrder is open)
            //  look at all tasks for that workorder; 
            //  if any have a taskStatusId other than 9, then there is an open task. 
            // If the open workOrder has no open tasks, then check to see whether 
            //  it has any invoices. If it does not, and if the Job Number is 
            //  neither '00000' nor '00000b', then add up the total time (in minutes) 
            //  for the workOrderTasks associated with this workOrder.
            foreach ($jobs as $job) {        	    
                $workOrders = $job->getWorkOrders();        	    
                foreach ($workOrders as $workOrder) {        	        
                    $workOrder->totalTime = 0;        	        
                    /* BEGIN REPLACED 2020-06-12 JM
                    $status = $workOrder->getWorkOrderStatus();
                    if ($status['workOrderStatusId'] != STATUS_WORKORDER_DONE) {
                    // END REPLACED 2020-06-12 JM
                    */
                    // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                    //$wo = new WorkOrder($workOrder); // 2021-01-04. Added by George.
                    if ( ! $workOrder->isDone() ) {
                    // END REPLACEMENT 2020-06-12 JM
                        $tasks = $workOrder->getWorkOrderTasksRaw();        	            
                        $anyOpen = false;        	            
                        
                        foreach ($tasks as $task) {        	                
                            if ($task->getTaskStatusId() != 9) {        	                    
                                $anyOpen = true;        	                    
                            }        	                
                        }
                        
                        if (!$anyOpen) { // If the open workOrder has no open tasks
                            $query = " select * ";
                            $query .= " from " . DB__NEW_DATABASE . ".invoice where workOrderId = " . intval($workOrder->getWorkOrderId()) . " ";
                            $query .= " order by invoiceDate desc ";
                            
                            $invoices = array();
                            
                            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $invoices[] = $row;
                                    }
                                }
                            }
                            
                            if (!count($invoices)) { // and there are no invoices        	                    
                                $totaltime = 0;
                                
                                // $wo = new WorkOrder($workOrder); // commented out by Martin before 2019       	                    
                                
                                $j = new Job($workOrder->getJobId());        	                    
                                $num = $j->getNumber();
                                
                                // .. and if the Job Number is neither '00000' nor '00000b'...
                                if ((substr($num, -5) != '00000') &&  (substr($num, -6) != '00000b')) {
                                    // ... add up the total time (in minutes)
                                    $workOrderTasks = $workOrder->getWorkOrderTasksRaw();
                                    foreach ($workOrderTasks as $workOrderTask) {
                                        $times = $workOrderTask->getWorkOrderTaskTime();
                                        foreach ($times as $time) {
                                            $totaltime += intval($time['minutes']);
                                        }
                                    }
                                    
                                    if (intval($totaltime)) {
                                        $workOrder->totalTime = $totaltime;
                                       // $finals[] = $workOrder; // commented out by Martin before 2019
                                       // $grandTotalTime += $totaltime; // commented out by Martin before 2019
                                    }
                                }        	                    
                            }        	                
                            
                            $render[] = $workOrder;
                        }        	            
                    }        	        
                }        	    
            }
        
            $other = array();
            
            return array('other' => $other, 'workOrders' => $render);	
    } // END public function getWOOpenTasksClosed
    
    /* RETURN an associative array with two elements:
       * 'other' is total time over all relevant workOrders, in minutes.
       * 'workOrders' is an array corresponding to the closed workOrders 
         with no invoice; unlike the prior two functions/methods which 
         return WorkOrder objects, this returns rows from the initial SELECT, 
         so just an associative array (not an object) containing canonical 
         representation of DB data for workOrder & job 
         (>>> JM: or almost canonical, since it's a little unusual to use 
         SELECT * on two joined tables).
         >>>00012 the name 'workOrders' here is a bit misleading.	     
    */
    public function getWONoInvoice() {
        $workOrders = array();
        
        /*  Select workOrder and job from DB; 
            limited to workOrderStatus.isDone == 1 
            ordered by invoice date (descending, via a LEFT JOIN on DB table invoice). 
            (>>>00006 JM: given the intent, why not a WHERE NOT EXISTS... ? 
            If we are doing it this way, why involve invoice at all at this 
            point: all it does is give us multiple rows with the same data 
            if there are multiple invoices, which we then get rid of by how
            we put these in an associative array indexed by workOrderId!) 
            Builds an array of this content (all workOrder data and all job data), 
            indexed by workOrderId.
        */
        // >>>00022 SELECT * on two tables in a SQL JOIN, not really a good idea 
        $query = " select wo.*, j.* ";
        $query .= " from " . DB__NEW_DATABASE . ".workOrder wo ";
        $query .= " join " . DB__NEW_DATABASE . ".job j on wo.jobId = j.jobId ";
        $query .= " left join " . DB__NEW_DATABASE . ".invoice i on wo.workOrderId = i.workOrderId ";
        /* BEGIN REPLACED 2020-06-12 JM
        $query .= " where wo.workOrderStatusId = " . intval(STATUS_WORKORDER_DONE) . " ";
        // END REPLACED 2020-06-12 JM
        */
        // BEGIN REPLACEMENT 2020-06-12 JM: making the minimal change at this time to incorporate the recent enhancements to workOrderStatus
        $query .= " JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderStatusId = wos.workOrderStatusId ";
        $query .= " WHERE wos.isDone = 1 ";
        // END REPLACEMENT 2020-06-12 JM
        $query .= " order by i.invoiceDate desc  ";	    
        
        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // We get rid of duplicate rows due to multiple invoices here, 
                    // because we overwrite for a given workOrder as we loop.
                    $workOrders[$row['workOrderId']] = $row;
                }
            }
        }
        
        $finals = array();	    
        $grandTotalTime = 0;
        
        /* Now we loop over the workOrders & select again, a separate select 
           of invoices for each workOrder, basically to identify the ones 
           with no invoice. There is a thing to ignore it if the Job Number 
           ends in '00000' or '00000b' (>>> JM asked Martin what that is about; 
           Martin thinks it's some administrative case), otherwise we sum up 
           the time associated with the workOrderTasks for this workOrder 
           (while also keeping a running total of time). */
        foreach ($workOrders as $wokey => $workOrder) {	        
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".invoice where workOrderId = " . intval($wokey);
            $query .= " order by invoiceDate desc ";
            
            $invoices = array();
            
            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $invoices[] = $row;
                    }
                }
            }
            
            if (!count($invoices)) {	            
                $totaltime = 0;	            
                $wo = new WorkOrder($workOrder);
                $j = new Job($workOrder['jobId']);	            
                $num = $j->getNumber();
                
                // ignore it if the Job Number ends in '00000' or '00000b'
                if ((substr($num, -5) != '00000') &&  (substr($num, -6) != '00000b')) {
                    $workOrderTasks = $wo->getWorkOrderTasksRaw();
                    foreach ($workOrderTasks as $workOrderTask) {
                        $times = $workOrderTask->getWorkOrderTaskTime();
                        foreach ($times as $time) {
                            $totaltime += intval($time['minutes']);
                        }
                    }
    
                    if (intval($totaltime)) {
                        $workOrder['totalTime'] = $totaltime;
                        $finals[] = $workOrder;
                        $grandTotalTime += $totaltime;
                    }
                }
           }
        }    

        $other = array('grandTotalTime' => $grandTotalTime);
        
        return array('other' => $other, 'workOrders' => $finals);
    } // END public function getWONoInvoice
    
    /* RETURN an associative array with two elements:
       * 'other' is itself an associative array with two elements:
          * 'total', the sum of the triggerTotal values.
          * 'balance', always zero. 
       * 'invoices' (prior to version 2020-2 this was, misleadingly, 'workOrders') 
         is an array corresponding to the return of the 
         original SELECT here; each element represents an open invoice 
         for a closed workOrder; the content is an associative array whose indexes are:
          - the following columns from DB table 'job'
            - jobId
            - customerId
            - locationId
            - number as jobNumber
            - name as jobName
            - rwname
            - description as jobDescription
            - jobStatusId
            - created
            - code
          - all columns from from DB table 'workOrder'
          - all columns from from DB table 'invoice'
          - 'lastStat': latest invoice status name and invoice status time.
          - 'lastDate': latest invoice status datetime in 'm/d/Y' form.
    */
    public function getWOClosedInvoiceOpen() {	
        $tt = 0;	    
        $invoiceStatusDataArray = Invoice::getInvoiceStatusDataArray();	    
        $invoices = array();
        
        /* Select a join of workOrder, invoice, and job from the DB, 
           selecting all columns from the latter 2, and numerous columns from Job
           (see code for which, and how some are renamed). 
           Limited to workOrderStatuses that indicate the workOrder is done and to invoice statuses that 
           indicate a non-sent invoice. This starts from job, but does a 
           RIGHT JOIN on workOrder and invoice. Unlike the other functions/methods 
           here that put things in an array indexed by workOrderId, we put all returned 
           rows in a regular array (called 'invoices').
        */   
        $query = " select  ";
        $query .= "   j.jobId ";
        $query .= " , j.customerId ";
        $query .= " , j.locationId ";
        $query .= " , j.number as jobNumber";
        $query .= " , j.name as jobName ";
        $query .= " , j.rwname ";
        $query .= " , j.description as jobDescription ";
        $query .= " , j.jobStatusId ";
        $query .= " , j.created ";
        $query .= " , j.code ";
        $query .= " , wo.*, i.* ";
        // BEGIN ADDED 2020-06-23 JM
        $query .= ", `is`.statusName AS invoiceStatusName ";
        // END ADDED 2020-06-23 JM
        $query .= " from " . DB__NEW_DATABASE . ".job j ";
        $query .= " right join " . DB__NEW_DATABASE . ".workOrder wo on j.jobId = wo.jobId ";
        $query .= " right join " . DB__NEW_DATABASE . ".invoice i on wo.workOrderId = i.workOrderId ";
        // BEGIN ADDED 2020-06-23 JM
        $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".invoiceStatus `is` ON i.invoiceStatusId = `is`.invoiceStatusId ";
        // END ADDED 2020-06-23 JM
        /* BEGIN REPLACED 2020-06-12 JM
        $query .= " where wo.workOrderStatusId = " . intval(STATUS_WORKORDER_DONE) . " ";
        // END REPLACED 2020-06-12 JM
        */
        // BEGIN REPLACEMENT 2020-06-12 JM
        $query .= " JOIN " . DB__NEW_DATABASE . ".workOrderStatus wos on wo.workOrderStatusId = wos.workOrderStatusId ";
        $query .= " WHERE wos.isDone = 1 ";
        // END REPLACEMENT 2020-06-12 JM
        
        //$query .= " and i.committed != 1 "; // Commented out by Martin before 2019
        $query .= " and i.invoiceStatusId in (" . $this->db->real_escape_string(Invoice::getNonSentInvoiceStatusesAsString()) . ") ";
        
        // BEGIN ADDED 2020-08-25 JM ad hoc change to address http://bt.dev2.ssseng.com/view.php?id=233
        // Strictly ad hoc way of saying (in effect) that "wo closed, open invoice" should not include this particular status; 
        //  what is ad hoc is that we are doing this by uniquename instead of by a Boolean property in the DB.
        $query .= "AND is.uniqueName <> 'awaitingDelivery' ";
        // END ADDED 2020-08-25 JM
        $query .= " order by wo.workOrderId, i.invoiceId ";
        
        $result = $this->db->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $invoices[] = $row;
                }
            }
        } else {
            $this->logger->errorDb('1592939770', "Hard DB error", $this->db); 
        }
        
        /* Loop over the array we just built. For each row returned by the 
           original SELECT, we do another SELECT to get the latest status 
           for the relevant invoice. 
           We'll add new elements:
             * 'lastStat' (invoice status name) 
             * 'lastDate' (invoice status date) 
           to the relevant row as returned by the original SELECT. 
           We'll also keep a running sum of any triggerTotal values from the invoices. 
        */
        foreach ($invoices as $invkey => $invoice) {	        
            $lastStat = '';
            $lastDate = '';
            // BEGIN cleaned by JM 2020-05-20
            $query = "SELECT invoiceStatusId, inserted " . // was SELECT *, simplified JM 2020-05-20 
            $query = "FROM " . DB__NEW_DATABASE . ".invoiceStatusTime ist ";
            // $query . " join invoiceStatus is on ist.invoiceStatusId = is.invoiceStatusId "; // removed, no need for this JM 2020-05-20 
            $query .= "WHERE ist.invoiceId = " . intval($invoice['invoiceId']) . " ";
            $query .= "ORDER BY ist.invoicestatusTimeId DESC LIMIT 1;";
            
            $result = $this->db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {	                    
                    foreach ($invoiceStatusDataArray as $invoiceStatusData) {
                        if ($invoiceStatusData['invoiceStatusId'] == $row['invoiceStatusId']) {
                            $lastStat = $invoiceStatusData['uniqueName'];
                            $lastDate = date("m/d/Y", strtotime($row['inserted']));
                        }
                    }
                }
            } else {
                $this->logger->errorDb('1589993455', 'Hard DB error', $this->db); // log, but continue
            }
            // END cleaned by JM 2020-05-20            
            
            $invoices[$invkey]['lastStat'] = $lastStat;
            $invoices[$invkey]['lastDate'] = $lastDate;
            
            if (is_numeric($invoice['triggerTotal'])) {
                $tt += $invoice['triggerTotal'];
            }	        
        }
        
        $other = array('total' => $tt, 'balance' => 0);
        
        return array('other' => $other, 'invoices' => $invoices);	    
    } // END public function getWOClosedInvoiceOpen
    
    /* RETURNs an associative array with two elements:
       * 'other' is itself an associative array with two elements:
           * 'total', the sum of the triggerTotal values.
           * 'balance', always zero. 
       * 'invoices' (before version 2020-02 this was 'workOrders') is an array corresponding to the return of the 
         original SELECT; each element represents an invoice awaiting 
         delivery; the content is an associative array, with indexes 
         corresponding to 
           * each column of DB table workOrder, 
           * each column of DB table invoice, 
           * invoice status name 'lastStat',
           * invoice status time 'lastTime', 
           * the following from columns in DB table job: 
               * jobId
               * customerId
               * locationId 
               * number as 'jobNumber'
               * name as 'jobName' 
               * rwname
               * description as 'jobDescription', 
               * jobStatusId
               * created
               * code
    */
    public function getAwaitingDelivery() {		
        $tt = 0;
        $invoiceStatusDataArray = Invoice::getInvoiceStatusDataArray();
        $invoiceStatusAwaitingDelivery = Invoice::getInvoiceStatusIdFromUniqueName('awaitingdelivery');
        
        $invoices = array();
        if ($invoiceStatusAwaitingDelivery !== false) {
            // Select from a join of DB tables job, workOrder, and invoice 
            //  (RIGHT JOIN on the latter two, so this will effectively return 
            //  rows only where the invoices meet the criteria), where invoice 
            //  status is the ID corresponding to 'awaitingdelivery'. 
            // (If there is more than one invoice awaiting delivery for the same 
            //  workOrder, then the same workOrder could appear twice). 
            // Order by workOrderId, invoiceId, so (given that IDs increase 
            //  monotonically over time) this is forward chronological order 
            //  by workOrder, and within that forward chronological order by invoice. 
            // We get all columns from workOrder & invoice, plus several from job
            //  (see code for details). This all goes in an array of associative 
            //  arrays in the canonical manner (column names as indexes).
            
            // >>>00022 SELECT * on two tables in a SQL JOIN, not really a good idea
            $query = " select  ";
            $query .= "   j.jobId ";
            $query .= " , j.customerId ";
            $query .= " , j.locationId ";
            $query .= " , j.number as jobNumber";
            $query .= " , j.name as jobName ";
            $query .= " , j.rwname ";
            $query .= " , j.description as jobDescription ";
            $query .= " , j.jobStatusId ";
            $query .= " , j.created ";
            $query .= " , j.code ";
            $query .= " , wo.*, i.* from " . DB__NEW_DATABASE . ".job j ";
            $query .= " right join " . DB__NEW_DATABASE . ".workOrder wo on j.jobId = wo.jobId ";
            $query .= " right join " . DB__NEW_DATABASE . ".invoice i on wo.workOrderId = i.workOrderId ";
            $query .= " where i.invoiceStatusId = " . intval($invoiceStatusAwaitingDelivery) . " " ;
            $query .= " order by wo.workOrderId, i.invoiceId ";
                
        
            if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $invoices[] = $row;
                    }
                }
            }
        
        } // >>>00002 else we will have already logged this in Invoice class, but should probably log it here, too.	

        // For each row of the return, we use a further SELECT and a mapping on 
        //  invoiceStatusId to add elements:
        //  * 'lastStat' (uniqueName of the latest status for this invoice) and 
        //  * 'lastDate' (when the invoice status was was inserted (date in m/d/Y form)). 
        // We also add the triggerTotal values from all of the invoices. 
        foreach ($invoices as $invkey => $invoice) {			
            $lastStat = '';
            $lastDate = '';
            
            // BEGIN cleaned by JM 2020-05-20
            $query = "SELECT invoiceStatusId, inserted " . // was SELECT *, simplified JM 2020-05-20
            $query = "FROM " . DB__NEW_DATABASE . ".invoiceStatusTime ist ";
            // $query . " join invoiceStatus is on ist.invoiceStatusId = is.invoiceStatusId "; // removed, no need for this JM 2020-05-20 
            $query .= "WHERE ist.invoiceId = " . intval($invoice['invoiceId']) . " ";
            $query .= "ORDER BY ist.invoicestatusTimeId DESC LIMIT 1;";
            
            $result = $this->db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {			
                    foreach ($invoiceStatusDataArray as $invoiceStatusData) {
                        if ($invoiceStatusData['invoiceStatusId'] == $row['invoiceStatusId']) {
                            $lastStat = $invoiceStatusData['uniqueName'];
                            $lastDate = date("m/d/Y", strtotime($row['inserted']));
                        }
                    }
        
                }
            } else {
                $this->logger->errorDb('1589993544', 'Hard DB error', $this->db); // log, but continue
            }
            // END cleaned by JM 2020-05-20            
            
            $invoices[$invkey]['lastStat'] = $lastStat;
            $invoices[$invkey]['lastDate'] = $lastDate;
            
            if (is_numeric($invoice['triggerTotal'])) {
                $tt += $invoice['triggerTotal'];
            }			
        }		
        
        $other = array('total' => $tt, 'balance' => 0);
        
        return array('other' => $other, 'invoices' => $invoices);
        
    } // END public function getAwaitingDelivery
    
    
    /* RETURNs an associative array with two elements:
        * 'other' is itself an associative array with two elements
            * 'total', the sum of the triggerTotal values.
            * 'balance', the sum of the balances, after accounting for payments. 
        * 'invoices' (before 2020-02-19, 'workOrders') is an array corresponding to the return of the original SELECT; 
           each element represents an invoice awaiting pament; the content is an 
           associative array, with indexes corresponding to:
            * each column of DB table workOrder
            * each column of DB table invoice
            * invoice status name 'lastStat'
            * invoice status time 'lastTime'
            * the calculated 'sumPayments' for the invoice
            * the calculated 'bal' for the invoice
            * the following from columns in DB table job: 
              * jobId
              * customerId
              * locationId
              * number as 'jobNumber'
              * name as 'jobName'
              * rwname
              * description as 'jobDescription'
              * jobStatusId
              * created
              * code
         We introduced the 'partiallypaid' invoiceStatus in v2020-3; previously
         that was not distinguished. This report covers both that and 'awaitingpayment'
    */
    public function getAwaitingPayment() {
        $tt = 0;
        $bb = 0;
        $invoiceStatusDataArray = Invoice::getInvoiceStatusDataArray();		
        $invoiceStatusAwaitingPayment = Invoice::getInvoiceStatusIdFromUniqueName('awaitingpayment');
        $invoiceStatusPartiallyPaid = Invoice::getInvoiceStatusIdFromUniqueName('partiallypaid');        
        
        $invoices = array();        
        if ($invoiceStatusAwaitingPayment === false) {
            $this->logger->error2('1590098339', "Financial::getAwaitingPayment cannot find definition of invoiceStatus 'awaitingpayment', so its return will be empty");
        } else if ($invoiceStatusPartiallyPaid === false) {
            $this->logger->error2('1590098359', "Financial::getAwaitingPayment cannot find definition of invoiceStatus 'partiallypaid', so its return will be empty");
        } else {
            /* Select from a join of DB tables job, workOrder, and invoice 
               (RIGHT JOIN on the latter two, so this will effectively 
               return rows only where the invoices meet the criteria), 
               where invoice status is the ID corresponding to 'awaitingpayment'. 
               (If there is more than one invoice awaiting payment for the same 
               workOrder, then the same workOrder could appear twice). 
               Order by workOrderId, invoiceId, so (given that IDs increase 
               monotonically over time) this is forward chronological order by 
               workOrder, and within that forward chronological order by invoice. 
               We get all columns from workOrder & invoice, plus several from job
               (see code). 
               This all goes in an array of associative arrays in the canonical 
               manner (column names as indexes). 
            */
            $query = "SELECT  ";
            $query .= "   j.jobId ";
            $query .= " , j.customerId ";
            $query .= " , j.locationId ";
            $query .= " , j.number AS jobNumber";
            $query .= " , j.name AS jobName ";
            $query .= " , j.rwname ";
            $query .= " , j.description AS jobDescription ";
            $query .= " , j.jobStatusId ";
            $query .= " , j.created ";
            $query .= " , j.code ";
            $query .= " , wo.*, i.* FROM " . DB__NEW_DATABASE . ".job j ";
            $query .= "RIGHT JOIN " . DB__NEW_DATABASE . ".workOrder wo ON j.jobId = wo.jobId ";
            $query .= "RIGHT JOIN " . DB__NEW_DATABASE . ".invoice i ON wo.workOrderId = i.workOrderId ";
            $query .= "WHERE i.invoiceStatusId IN ($invoiceStatusAwaitingPayment, $invoiceStatusPartiallyPaid) ";
            $query .= "ORDER BY wo.workOrderId, i.invoiceId;";                
        
            $result = $this->db->query($query);
            if ($result) {
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $invoices[] = $row;
                    }
                }
            } else {
                $this->logger->errorDb('1590098377', "Hard error; Financial::getAwaitingPayment return will be empty", $this->db);
            }
            
            /* For each row returned above, we use a further SELECT and a mapping 
               on invoiceStatusId to add elements:
                 * 'lastStat' (uniqueName of the latest status for this invoice) and 
                 * 'lastDate' (when the invoice status was was inserted (date in m/d/Y form). 
               For each row, we also sum up the payments on the invoice and add 
               a new element to the associative array for the row: 'sumPayments'. 
               Then, if the triggerTotal for the invoice is numeric, we subtract 
                that sum from the triggerTotal and add the result as a new element 
                to the associative array for the row: 'bal'. 
               If the triggerTotal for the invoice is not numeric, then we also 
                use 'bal', but just set it to "n/a". 
               We also add the triggerTotal values from all of the invoices, 
               and keep a running total of the balances (in both cases ignoring 
               any non-numerics). 
            */
            foreach ($invoices as $invkey => $invoice) {
                
                if (is_numeric($invoice['triggerTotal'])) {
                    $tt += $invoice['triggerTotal'];
                }
                
                $lastStat = '';
                $lastDate = '';
                // BEGIN cleaned by JM 2020-05-20
                $query = "SELECT invoiceStatusId, inserted " . // was SELECT *, simplified JM 2020-05-20 
                $query = "FROM " . DB__NEW_DATABASE . ".invoiceStatusTime ist ";
                // $query . " join invoiceStatus is on ist.invoiceStatusId = is.invoiceStatusId "; // removed, no need for this JM 2020-05-20 
                $query .= "WHERE ist.invoiceId = " . intval($invoice['invoiceId']) . " ";
                $query .= "ORDER BY ist.invoicestatusTimeId DESC LIMIT 1;";
                
                $result = $this->db->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {	                    
                        foreach ($invoiceStatusDataArray as $invoiceStatusData) {
                            if ($invoiceStatusData['invoiceStatusId'] == $row['invoiceStatusId']) {
                                $lastStat = $invoiceStatusData['uniqueName'];
                                $lastDate = date("m/d/Y", strtotime($row['inserted']));
                            }
                        }
                    }
                } else {
                    $this->logger->errorDb('1589993605', 'Hard DB error', $this->db); // log, but continue
                }
                // END cleaned by JM 2020-05-20            
                
                $invoices[$invkey]['lastStat'] = $lastStat;
                $invoices[$invkey]['lastDate'] = $lastDate;
                
                $tot = 0;
                $inv = new Invoice($invoice['invoiceId'],$this->user);
                
                $payments = $inv->getPayments();
    
                foreach ($payments as $payment) {
                    if (is_numeric($payment['amount'])) {
                        $tot += $payment['amount'];
                    }
                }
    
                $invoices[$invkey]['sumPayments'] = $tot;
                
                $bal = '';
                
                if (is_numeric($invoice['triggerTotal']) && is_numeric($tot)) {
                    $bal = $invoice['triggerTotal'] - $tot;
                } else {
                    $bal = 'n/a';
                }				
                
                $invoices[$invkey]['balance'] = $bal;
                
                if (is_numeric($bal)) {
                    $bb += $bal;
                }
            }
        }		

        $other = array('total' => $tt, 'balance' => $bb);
        
        return array('other' => $other, 'invoices' => $invoices);
    } // END public function getAwaitingPayment
}
?>