<?php

/* inc/classes/helpers/Time.class.php
   EXECUTIVE SUMMARY:
   
   * public methods
   ** __construct($user,$start,$displayType = 'workWeek')
   ** getWorkOrderTasksByDisplayType()
   ** getWorkOrderTaskTimeLateModifications()
   ** getPtoLateModifications()
   ** getWorkOrderTaskTimeNetLateModifications()
   ** getPtoNetLateModifications()
   ** deleteWorkOrderTaskTimeLateModifications()
   ** deletePtoLateModifications()
   ** notifyLateModifications()
   
   * public properties: 
   ** Strings in 'Y-m-d' format; some can be null. See below for documentation.
   *** begin
   *** beginIncludingPrior 
   *** end
   *** next
   *** previous
   ** Strings in 'Y-m-d h:m:s' format; some can be null. See below for documentation. 
      Further documentation at http://sssengwiki.com/Documentation+V3.0#customerPersonPayPeriodInfo
   *** adminSignedPayrollTime
   *** initialSignoffTime
   *** lastSignoffTime
   *** readyForSignoff
   *** reopenTime
   ** Other public properties
   *** dates - An array of associative arrays. Top-level is 0-indexed and corresponds to days in the period. 
               This is about displaying dates in various forms (e.g. '2019-06-05', '06/05'). See below for further documentation.
   *** firstDayWorkWeek - PHP DateTime object; Only defined if displayType == 'payperiod': the Monday on or before $begin.
   *** workWeekFormatN;	// ISO-8601 numeric representation of a day of the week, 1 (for Monday) through 7 (for Sunday). See below for further documentation.
*/
class Time {
	private $db;
	private $user;     // User object, if this is constructed for a particular user
	private $logger;
	private $customerPersonData; // Set only if object is constructed for a particular user.
                                //  undefined if there is no such user (>>>00006: JM suggests making it false instead, just
                                //  like for a user with no row in CustomerPersonPayWeekInfo).
                                //  An associative array equivalent to the content of the row for that user 
                                //   in DB table CustomerPersonPayWeekInfo; false if there is no such row. 
                                //  The associative array will have members: 
                                //   * 'customerPersonPayWeekInfoId'
                                //   * 'customerPersonId'
                                //   * 'periodBegin'
                                //   * 'dayHours'
                                //   * 'dayOT'
                                //   * 'weekOT'
                                //   * 'workWeek'
	public $begin;     // First day of period.
	                   // As of 2019-03, we play rather fast and loose with this, multiplexing it
	                   // before finally setting it as a string in 'Y-m-d' format (e.g. '2019-06-05').
	                   // If $start is passed in explicitly, that will be the date. Otherwise:
	                   //   * If displayType == 'workWeek' or 'incomplete': the last Monday 
	                   //     before tomorrow (so can be today, if it's a Monday).
	                   //   * If displayType == 'payperiod': we look at today's date and 
	                   //     anything greater than the 15th is coerced to the 1st of the 
	                   //     current month; anything else (1-15) is coerced to the 16th 
	                   //     of the previous month. Presumably the intent is to get the 
	                   //     last completed pay period. 	                   
	public $beginIncludingPrior; // added 2020-06-29 JM: for displayType 'workWeek', 'workWeekAndPrior', 'incomplete'
	                   //  this is always the beginning of the *pay period* in which the start of that workweek falls.
	                   //  For 'payperiod', it is the same as $begin.
    private $endOfPayPeriod; // added 2020-10-16. This is sort of a dual to $beginIncludingPrior: it is the end of the pay period, regardless
                       // of displayType
	private $beginForFetchingData; // added 2020-07-30, Addressing http://bt.dev2.ssseng.com/view.php?id=200, issue 3, needed to distinguish
	                   // this from $beginIncludingPrior.
	public $end;       // Last day of week/period, a string in 'Y-m-d' format. 
	
	public $next;      // First day of next week/period, a string in 'Y-m-d' format.
	public $previous;  // First day of previous week/period, a string in 'Y-m-d' format.
	
	public $dates;     // An array of associative arrays. Top-level is 0-indexed and corresponds to days in the period. 
	                   // Each associative array has elements"
	                   // * 'position': date in 'Y-m-d' form  (e.g. '2019-06-05')
	                   // * 'short': date in 'm/d' form (e.g. '06/05')
	                   
	public $workWeekFormatN;	// ISO-8601 numeric representation of a day of the week, 
	                            // 1 (for Monday) through 7 (for Sunday).
	                            // Value here appears to be *always* 1.
	public $firstDayWorkWeek;   // Only defined if displayType == 'payperiod': the Monday on or before $begin.
	                            // 
	                            // NOT in 'Y-m-d' format. This one is a PHP DateTime object, as described at http://php.net/manual/en/class.datetime.php.
	                            // 
	                            // If $start is passed in explicitly, and it represents either the 1st or 16th of the month, 
	                            // or if $start is not passed in explicitly:
	                            //   If $this->begin falls on a Monday, it is the same day as $this->begin; 
	                            //   otherwise it is the immediately prior Monday.
    public $readyForSignoff;        // from CustomerPersonPayPeriodInfo; for this and the following,
                                    // there is further documentation at http://sssengwiki.com/Documentation+V3.0#customerPersonPayPeriodInfo
    public $initialSignoffTime;     // from CustomerPersonPayPeriodInfo
    public $adminSignedPayrollTime; // from CustomerPersonPayPeriodInfo
    public $lastSignoffTime;        // from CustomerPersonPayPeriodInfo
    public $reopenTime;             // from CustomerPersonPayPeriodInfo
    
    
	private $gold;    // >>>00001 JM: The gold/overlay stuff is very complicated, and I could never get
 	               //  Martin to discuss it. There is still going to be some serious work in teasing out
 	               //  what is going on here. It has something to do with reconstructing a workOrderTask 
 	               //  hierarchy, including "fake" tasks where SSS at one time historically had to "fake up"
 	               //  parent tasks (or maybe just fake workOrderTasks) that had no explicit representation.
 	               // Reading the code, there is no clear reason why this is a private member of the class object,
 	               //  rather than just a local variable in the one function that uses it. 
	
    // INPUT $user: a User object for an employee, may also be null
    // INPUT $start: date string. If $displayType == 'payperiod', then this must be the 1st or 16th of the month.
    // >>>00001 NOT SURE of the desired format, though it may actually
    //  be pretty flexible, because it uses PHP strtotime, which gives a lot of slack. And apparently
    //  there is some trickiness about not passing in a value here at all; see discussion of class member
    //  $begin above.
    // INPUT $displayType: default 'workWeek'; can also be 'incomplete' or 'payperiod'; 
    //   'incomplete' is so the user can enter partial data during a pay period. 
    // This constructor does more work than most, calculating end of period, start of next period, etc. and fills in a private array
    //   providing two written forms of all of the dates in the week/period.
    // >>>00016, >>>00002 probably should validate inputs, report (at least log) if they are bad and
    //  also do something to invalidate the object in that case.
	public function __construct($user, $start, $displayType = 'workWeek') {
	    global $logger, $customer;
	    
		$this->db = DB::getInstance();
		$this->user = $user;
		$this->logger = $logger;
		$this->begin = $start;  // >>>00006 rather sloppy, making temporary multiplexed use of $this->begin,
		                        //  which we will soon overwrite TWICE; we should introduce different variables,
		                        //  nothing gained by multiplexing a class member variable.
		$this->displayType = $displayType;
		
		
		if ($this->user) {		
			$this->customerPersonData = $this->user->getCustomerPersonPayWeekInfo($this->begin);
		}
		
		$dow = 'monday';
		$this->workWeekFormatN = 1;  // BEGIN MARTIN COMMENT
		                             // default .. used when admin doing pay periods for everyone.
                                     // mostly to set up the previous and next links in the interface
                                     // END MARTIN COMMENT

	
        if ($this->customerPersonData) {
			if ($this->customerPersonData['workWeek'] == WORKWEEK_MON_SUN) {				
				$this->workWeekFormatN = 1;  // [Martin comment] when checking "N" date format .. 1 is monday				
			}
        }
			
		if ($displayType == 'workWeek' || $displayType == 'workWeekAndPrior') {
		    if ($this->customerPersonData) {
		        $dow = '';
				
				if ($this->customerPersonData['workWeek'] == WORKWEEK_MON_SUN) {
					$dow = 'monday';					
				} // >>>00002 else a value not currently supported, and we should log that.
            }
				
			// >>>00006 Again rather sloppy, making temporary multiplexed use of $this->begin,
            //  which we will soon overwrite AGAIN; we should introduce different variables,
            //  nothing gained by multiplexing a class member variable.            
            if (strlen($start)) {
				$this->begin = strtotime($start);
			} else {
			    // Set this to the most recent Monday (well, the most recent $dow, but as of
			    //  2019-03 that should consistently be Monday), including today.  
			    $this->begin = strtotime('last ' . $dow, strtotime('tomorrow'));
            }
            
            $this->end = date("Y-m-d",strtotime("+6 day", $this->begin));
            $this->previous =  date("Y-m-d", strtotime("-7 day", $this->begin));
            $this->next =  date("Y-m-d", strtotime("+7 day", $this->begin));
            $this->begin = date("Y-m-d",$this->begin);  // [Martin comment] this is at the end here because the stuff above depends on $this->begin still being a unix time

            /* BEGIN REMOVED 2020-07-30 JM
            // Addressing http://bt.dev2.ssseng.com/view.php?id=200, issue 3, the calculation
            //  of $this->http://bt.dev2.ssseng.com/view.php?id=200 is more general, so it is moved down below.
            if ($this->displayType == 'workWeekAndPrior') {
                // has to be either the 1st or 16th
                $parts = explode('-', $this->begin);
                if (intval($parts[2]) >=16) {
                    $parts[2] = '16';
                } else {
                    $parts[2] = '01';
                }
                $this->beginIncludingPrior = implode('-', $parts);
            } else {
                $this->beginIncludingPrior = $this->begin;
            }
            // END REMOVED 2020-07-30 JM
            */
        } else if ($displayType == 'incomplete') {
            if ($this->customerPersonData) {
				$dow = '';				
				if ($this->customerPersonData['workWeek'] == WORKWEEK_MON_SUN) {						
					$dow = 'monday';						
				}				
            }
			// >>>00006 Again rather sloppy, making temporary multiplexed use of $this->begin,
            //  which we will soon overwrite AGAIN; we should introduce different variables,
            //  nothing gained by multiplexing a class member variable.            
            $this->begin = strtotime('last ' . $dow, strtotime('tomorrow'));
            $this->end = date("Y-m-d", strtotime("+6 day", $this->begin));
            $this->previous =  date("Y-m-d", strtotime("-7 day", $this->begin));
            $this->next =  date("Y-m-d", strtotime("+7 day", $this->begin));
            $this->begin = date("Y-m-d",$this->begin);  // [Martin comment] this is at the end here because the stuff above depends on $this->begin still being a unix time
            /* BEGIN REMOVED 2020-07-30 JM
            // Addressing http://bt.dev2.ssseng.com/view.php?id=200, issue 3 (described above), this is handled differently, below.
			$this->beginIncludingPrior = $this->begin;
			// END REMOVED 2020-07-30 JM
			*/
        } else if ($displayType == 'payperiod') {
            // If there is an explicit start date AND it is either the first or 16th of the month (the only legitimate
            //  values in this context)
            // >>>00006: it might make a lot of sense to get that day-of-month for start into a variable.
            if ((strlen($start)) && ((date("j", strtotime($start)) == 1) || (date("j", strtotime($start)) == 16))) {
                $startDate = new DateTime($start);
			
                if ($startDate->format("j") == 1) {
                    $endDate = new DateTime($startDate->format("Y-m-d"));
                    $endDate->modify('+14 days');
                }
                if ($startDate->format("j") == 16) {
                    $endDate = new DateTime($startDate->format("Y-m-d"));
                    $endDate->modify('last day of this month');        
                }
			
                // >>>00006 the following is common code for all displayType == 'payperiod' cases,
                // and really ought to be moved out a level and not be in here three separate times.
                // 
                // initialize $this->firstDayWorkWeek as DateTime form of $startDate... 
                $this->firstDayWorkWeek = new DateTime($startDate->format("Y-m-d"));  
                //
                // ... and then move it backward until it is a Monday.
                while ($this->firstDayWorkWeek->format("N") != $this->workWeekFormatN) {
                    $this->firstDayWorkWeek->modify("-1 day");
                }        
                // END common code    
            } else {
                // No explicit start date or illegitimate explicit start date
                $startDate = new DateTime();        
                $dom = date("j");        
                if ($dom > 15){        
                    $startDate->modify('first day of this month');
        
                    $endDate = new DateTime();
                    $endDate->modify('first day of this month');
                    $endDate->modify('+14 days');
        
                    $this->firstDayWorkWeek = new DateTime($startDate->format("Y-m-d"));
        
                    while ($this->firstDayWorkWeek->format("N") != $this->workWeekFormatN){
                        $this->firstDayWorkWeek->modify("-1 day");
                    }        
                } else {			
                    $startDate->modify('first day of last month');
                    $startDate->modify('+15 days');
        
                    $endDate = new DateTime();
                    $endDate->modify('last day of last month');
        
                    $this->firstDayWorkWeek = new DateTime($startDate->format("Y-m-d"));
        
                    while ($this->firstDayWorkWeek->format("N") != $this->workWeekFormatN) {
                        $this->firstDayWorkWeek->modify("-1 day");
                    }			
                }			
            } // END else: NOT (strlen($start)) && ((date("j", strtotime($start)) == 1) || (date("j", strtotime($start)) == 16))
                
            if (strlen($start)) {
                $this->begin = date("Y-m-d", strtotime($start));					
            } else {
                $this->begin = $startDate->format("Y-m-d");
            }

            $this->end = $endDate->format("Y-m-d");			
                
            // Set $this->next and $this->previous 
            if (date("j", strtotime($this->begin)) == 1) {			
                $s = new DateTime($this->begin);
                $s->modify('+15 days');
                $nextTimePeriodStart = strtotime($s->format("Y-m-d"));
                $this->next = $s->format("Y-m-d");
        
                $e = new DateTime($this->begin);
                $e->modify('-1 month');
                $e->modify('+15 days');
        
                $prevTimePeriodStart = strtotime($e->format("Y-m-d"));
                $this->previous = $e->format("Y-m-d");
            }
                
            if (date("j", strtotime($this->begin)) == 16) {			
                $s = new DateTime($this->begin);
                $s->modify('+1 month');
                $s->modify('-15 days');
                $nextTimePeriodStart = strtotime($s->format("Y-m-d"));
                $this->next = $s->format("Y-m-d");
        
                $e = new DateTime($this->begin);
                $e->modify('-15 days');
        
                $prevTimePeriodStart = strtotime($e->format("Y-m-d"));
                $this->previous = $e->format("Y-m-d");
            }
            /* BEGIN REMOVED 2020-07-30 JM
            // Addressing http://bt.dev2.ssseng.com/view.php?id=200, issue 3 (described above), this is handled below.
            $this->beginIncludingPrior = $this->begin;
            // END BEGIN REMOVED 2020-07-30 JM
            */
        } // >>>00002 else invalid displayType, and we should presumably log that.
        
        // BEGIN ADDED 2020-07-30 JM
        // Addressing http://bt.dev2.ssseng.com/view.php?id=200, issue 3, the calculation
        //  of $this->http://bt.dev2.ssseng.com/view.php?id=200 is now more general.
        if ($displayType == 'workWeek' || $displayType == 'workWeekAndPrior' || $displayType == 'incomplete') {            
            // beginIncludingPrior has to be either the 1st or 16th
            $parts = explode('-', $this->begin);
            if (intval($parts[2]) >=16) {
                $parts[2] = '16';
            } else {
                $parts[2] = '01';
            }
            $this->beginIncludingPrior = implode('-', $parts);
            // END ADDED 2020-07-30 JM
        } else if ($displayType == 'payperiod') {
            // $this->begin is already either the 1st or 16th
            $this->beginIncludingPrior = $this->begin;
        }
        
        // Now set $this->endOfPayPeriod
        $parts = explode('-', $this->beginIncludingPrior);
        
        if (intval($parts[2])==1) {
            $parts[2] = '15';            
        } else {
            $month = intval($parts[1]);
            if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
                $parts[2] = '30';
            } else if ($month == '2') {
                $year = intval($parts[0]);
                // The rule here will be correct for several hundred years; it will break in 2400
                if ($year%4 == 0) {
                    $parts[2] = '29';
                } else {
                    $parts[2] = '28';
                }
            } else {
                $parts[2] = '31';
            }
        }
        $this->endOfPayPeriod = implode('-', $parts);
        
        if ($displayType == 'workWeekAndPrior') {
            $this->beginForFetchingData = $this->beginIncludingPrior;
        } else {
            $this->beginForFetchingData = $this->begin;
        }
        // END ADDED 2020-07-30 JM

        
        // Build array of all dates in the period/week; for each we have the date in two forms:
        // 'Y-m-d' (e.g. '2019-06-05') and 'm/d' (e.g. '06/05')
        $this->dates = array();
        
        $query = "SELECT a.Date,  DATE_FORMAT(a.Date,'%m/%d') AS smalldate ";
        $query .= "FROM (";
        /* BEGIN CODE REPLACED 2020-03-09 JM
        
        // This approach from https://stackoverflow.com/questions/5442618/mysql-is-it-possible-to-fill-a-select-with-values-without-a-table,
        // which that page itself describes it as "VERY exotic, but it DOES work". 
        // This approach was overkill and very inefficient when the longest period we will be looking at is 16 days. (With the introduction of workWeekAndPrior this became
        //  23 rather than 16)
        // Code changed (simplified) 2020-03-09 JM to make intent much clearer and get rid of 984 cases (this went 0-999 when we only needed 0-16!) that never arise.
        //  (With the introduction of workWeekAndPrior, we added back 17-23) 
        
        $query .= " select '" . $this->begin . "' + INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date ";
        $query .= " from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a ";
        $query .= " cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b ";
        $query .= " cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c ";
        $query .= " ) a ";
        // END CODE REPLACED 2020-03-09 JM
        */
        // BEGIN REPLACEMENT CODE 2020-03-09 JM
        // The idea is to get a row for every date, whether it has data or not.
        // We've revised this a couple of times for various requests; originally this used $this->begin, then $this->beginIncludingPrior, now (2020-07-30)
        // $this->beginForFetchingData.
        $query .= "SELECT '" . $this->beginForFetchingData . "' + INTERVAL (a) DAY AS Date "; // here and below, was $this->begin; changed 2020-06-29 JM
        $query .= "FROM (select 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 "; 
        $query .= "UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 "; 
        $query .= "UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 ";
        $query .= "UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 ";
        $query .= "UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23) AS b) a ";
        // END REPLACEMENT CODE 2020-03-09 JM
        $query .= "WHERE a.Date >= '" . $this->beginForFetchingData . "' ";
        $query .= "AND a.Date <= '" . $this->end . "';";

        $c = 0;
        
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {						
                $this->dates[] = array('position' => $row['Date'], 'short' => $row['smalldate']);;
            }
        } else {
            $this->logger->errorDb('1602000388', 'Hard DB error', $this->db);
        }
	    
        // BEGIN added 2020-07-01 JM; modified 2020-07-23 JM to account for the possibility that
        //  there is no payPeriodInfo for the relevant pay period. 
        if ($user && ($this->displayType == 'workWeekAndPrior' || $this->displayType == 'payperiod'
	            // BEGIN ADDED 2020-07-30 JM to address http://bt.dev2.ssseng.com/view.php?id=200 issue 3: make this available for more displayTypes
	            || $this->displayType == 'workWeek' || $this->displayType == 'incomplete'
	            // END ADDED 2020-07-30
	            )
	        ) 
	    {
	        $payPeriodInfo = $this->user->getCustomerPersonPayPeriodInfo($this->beginIncludingPrior);
	        if ($payPeriodInfo) {
                $this->readyForSignoff = $payPeriodInfo['readyForSignoff'];
                $this->initialSignoffTime = $payPeriodInfo['initialSignoffTime'];
                $this->adminSignedPayrollTime = $payPeriodInfo['adminSignedPayrollTime'];
                $this->lastSignoffTime = $payPeriodInfo['lastSignoffTime'];
                $this->reopenTime = $payPeriodInfo['reopenTime'];
            } else {
                $this->readyForSignoff = null;
                $this->initialSignoffTime = null;
                $this->adminSignedPayrollTime = null;
                $this->lastSignoffTime = null;
                $this->reopenTime = null;
            }
	    }
	    // END added 2020-07-01 JM
	} // END public function __construct
	

	public function getWorkOrderTasksByDisplayType() {
		$workOrderTasks = array();
	
		// BEGIN MARTIN COMMENT 
		///////////////////////////////////////
		///////////////////////////////////////
		// PTO
		///////////////////////////////////////
		///////////////////////////////////////
		// END MARTIN COMMENT
	
		$ptos = array();
		// $ptos will be a multi-level array, as follows:
		// * Top-level indexes are 1 (sick/vacation, always present) and 2 (holiday, present
		//   only if it arises in the data). For each of these, the array element is an associative array
		//   with the following elements:
		//   * 'editable': 1 for sick/vacation, 0 for holiday
		//   * 'workOrderTaskTimeId': 0
		//   * 'workOrderTaskId': -100 for sick/vacation, -200 for holiday
		//   * 'personId': based on current user
		//   * 'icon': empty string
		//   * 'taskStatusId': 0
		//   * 'workOrderId': 0
		//   * 'taskId': 0
		//   * 'workOrderDescriptionTypeId': 0
		//   * 'description': empty string
		//   * 'number': empty string
		//   * 'name': 'PTO';
		//   * 'jobId': 0
		//   * 'taskDescription': empty string
		//   * 'categoryName': either "Holiday" or "Sick/Vacation", as appropriate
		//   * 'ptoitems': present only if there is at least one such row.
		//     Associative array indexed by date in 'Y-m-d' form. Array elements are
		//     themselves associative arrays, with elements
		//     * 'Date': same value as index pointing to this. Date in 'Y-m-d' form.
		//     * 'smalldate': empty string >>>00001 That's odd - JM
		//     * 'minutes': minutes of PTO, 4800 for an 8-hour day.
		//     * 'workOrderTaskId': empty string
		//     * 'ptoTypeId': same as top-level index. 1 (sick/vacation) or 2 (holiday)
		//     * 'ptoTypeName': same as 'categoryName' above: either "Holiday" or "Sick/Vacation", as appropriate
		
		// Comma-separated string of dates in week/period in 'Y-m-d' form, 
		// for use in SQL query
		$daystring = '';
		foreach ($this->dates as $date) {	
			if (strlen($daystring)) {
				$daystring .= ',';
			}	
			$daystring .= "'" . $date['position'] . "'";	
		}
	
		// As of 2019-03, the two ptoTypeIds are PTOTYPE_SICK_VACATION=1 and PTOTYPE_HOLIDAY=2.
		// PTOTYPE_SICK_VACATION rows are editable, PTOTYPE_HOLIDAY are not.
		//
		// Select all rows showing PTO for this user in the relevant time period. NOTE that this
		//  selects nothing for $user==undefined.
		$query = "SELECT  p.*, pt.ptoTypeName FROM " . DB__NEW_DATABASE . ".pto p ";
		$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".ptoType pt ON p.ptoTypeId = pt.ptoTypeId ";
		$query .= "WHERE day IN (" . $daystring . ") ";
		$query .= "AND personId = " . intval($this->user->getUserId()) . " ";
		$query .= "ORDER BY ptoTypeId, day;";	
	
		$result = $this->db->query($query);
		if ($result) {
            while ($row = $result->fetch_assoc()) {
                $editable = ($row['ptoTypeId'] == PTOTYPE_SICK_VACATION) ? 1 : 0;
                    
                $ptos[$row['ptoTypeId']]['editable'] = $editable;
                $ptos[$row['ptoTypeId']]['workOrderTaskTimeId'] = 0;
                $ptos[$row['ptoTypeId']]['workOrderTaskId'] = ($row['ptoTypeId'] * -100);
                $ptos[$row['ptoTypeId']]['personId'] = $this->user->getUserId();
                $ptos[$row['ptoTypeId']]['icon'] = '';
                $ptos[$row['ptoTypeId']]['taskStatusId'] = 0;
                $ptos[$row['ptoTypeId']]['workOrderId'] = 0;
                $ptos[$row['ptoTypeId']]['taskId'] = 0;
                $ptos[$row['ptoTypeId']]['workOrderDescriptionTypeId'] = 0;
                $ptos[$row['ptoTypeId']]['description'] = '';
                $ptos[$row['ptoTypeId']]['number'] = '';
                $ptos[$row['ptoTypeId']]['name'] = 'PTO';
                $ptos[$row['ptoTypeId']]['jobId'] = 0;
                $ptos[$row['ptoTypeId']]['taskDescription'] = '';
                $ptos[$row['ptoTypeId']]['categoryName'] =  $row['ptoTypeName'];
                $ptos[$row['ptoTypeId']]['ptoitems'][$row['day']] = array(	
                        'Date' => $row['day'],
                        'smalldate' => '',
                        'minutes' => $row['minutes'],
                        'workOrderTaskId' => '',
                        'ptoTypeId' => $row['ptoTypeId'],
                        'ptoTypeName' => $row['ptoTypeName']
                );
            }
		} else {
		    $this->logger->errorDb('1602000486', 'Hard DB error', $this->db);
		}
	
		// [Martin comment] have to put in some dummy info so that the vacation item exists in the display for
		// users to interact with
		if (!array_key_exists(PTOTYPE_SICK_VACATION, $ptos)) {
		    $ptos[PTOTYPE_SICK_VACATION]['editable'] = 1;
			$ptos[PTOTYPE_SICK_VACATION]['workOrderTaskTimeId'] = 0;
			$ptos[PTOTYPE_SICK_VACATION]['workOrderTaskId'] = (PTOTYPE_SICK_VACATION * -100);
			$ptos[PTOTYPE_SICK_VACATION]['personId'] = $this->user->getUserId();
			$ptos[PTOTYPE_SICK_VACATION]['icon'] = '';
			$ptos[PTOTYPE_SICK_VACATION]['taskStatusId'] = 0;
			$ptos[PTOTYPE_SICK_VACATION]['workOrderId'] = 0;
			$ptos[PTOTYPE_SICK_VACATION]['taskId'] = 0;
			$ptos[PTOTYPE_SICK_VACATION]['workOrderDescriptionTypeId'] = 0;
			$ptos[PTOTYPE_SICK_VACATION]['description'] = '';
			$ptos[PTOTYPE_SICK_VACATION]['number'] = '';
			$ptos[PTOTYPE_SICK_VACATION]['name'] = 'PTO';
			$ptos[PTOTYPE_SICK_VACATION]['jobId'] = 0;
			$ptos[PTOTYPE_SICK_VACATION]['taskDescription'] = '';
			$ptos[PTOTYPE_SICK_VACATION]['categoryName'] =  'Vacation/Sick'; // >>>00006 really should be "Sick/Vacation" to match DB
		}
		
		// Copy $ptos into $workOrderTasks, but that's in a plain array rather than
		// having top-level indexes be forced to 1 & 2, respectively, based on ptoTypeId. 
		$lastPtoTypeId = -1;	
		foreach ($ptos as $pkey => $pto) {	
			if ($pkey != $lastPtoTypeId) { // >>>00001 JM: how can this ever be false?	
				$workOrderTasks[] = $pto;	
			}	
		}
	
		// BEGIN MARTIN COMMENT
		///////////////////////////////////////
		///////////////////////////////////////
		// REGULAR
		///////////////////////////////////////
		///////////////////////////////////////
		// END MARTIN COMMENT
	
		$rows = array();	
		$query = '';

		// The idea of the following is to get a list of workOrderTasks, ordered by
		// (jobId, workOrderId, workOrderTaskId), and data about those workOrderTasks.
		// Although we look here at all of the work done on those tasks in the relevant period
		// we actually throw all that data away and come back to it later, and produce
		// only one row per workOrderTask.
		
		// Identify all work that contributes to a workOrderTask in the relevant week/period, and
		// select data about that workOrderTask
		// UNION that with data for all uncompleted workOrderTasks.
		// Using "group by", selec only one such row for each workOrderTask.
		
		// Elements of the associative array produced by this query:
		// * 'editable': always 1
		// * 'workOrderTaskTimeId': primary key into DB table WorkOrderTaskTime 
		// * 'workOrderTaskId': primary key into DB table WorkOrderTask
		// * 'personId': primary key into DB table Person
		// * 'icon': task icon (string)
		// * 'taskStatusId': primary key into DB table TaskStatus. This is, of course, about a WorkOrderTask, not a general Task
		//   >>>00001 Weirdly, in there twice; even more weirdly, by experiment, there seems to be a good reason for that - JM 2019-12-11
		// * 'workOrderId': primary key into DB table WorkOrder
		// * 'taskId': primary key into DB table Task
		// * 'workOrderDescriptionTypeId': primary key into DB table WorkOrderDescriptionType
		// * 'description': workOrder description          
		// * 'number': Job Number, e.g. 's1906045'
		// * 'name': job name
		// * 'jobId': primary key into DB table Job
		// * 'taskDescription': task description
		if (($this->displayType == 'workWeek') || ($this->displayType == 'workWeekAndPrior') || ($this->displayType == 'payperiod')) {
			$query  = "SELECT 1 AS editable, ";
			$query .= "wott.workOrderTaskTimeId, wot.workOrderTaskId, wott.personId, t.icon, wot.taskStatusId";
			$query .= ", wot.workOrderId";
			$query .= ", wot.taskId";
			$query .= ", wot.taskStatusId"; // >>>00001 I have no idea how it can make sense to get this a second time, but I've determined
			                                  // that if you remove this, things break! Bizarre. JM 2019-12-11.
			$query .= ", wo.workOrderDescriptionTypeId";
			$query .= ", wo.description";
			$query .= ", j.number";
			$query .= ", j.name";
			$query .= ", j.jobId";
			$query .= ", t.description as taskDescription ";
			$query .= "FROM ( ";
			/* BEGIN CODE REPLACED 2020-03-09 JM
			// SEE REMARKS FOR SIMILAR CASE ABOVE FOR WHY THIS WAS REPLACED 
			$query .= " select '" . $this->begin . "' + INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date ";
			$query .= " from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a ";
			$query .= " cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b ";
			$query .= " cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c ";
			$query .= " ) a ";
            // END CODE REPLACED 2020-03-09 JM
            */
            // BEGIN REPLACEMENT CODE 2020-03-09 JM
            // The idea is to get a row for every date, whether it has data or not.
            // We've revised this a couple of times for various requests; originally this used $this->begin, then $this->beginIncludingPrior, now (2020-07-30)
            // $this->beginForFetchingData.
			$query .= "SELECT '" . $this->beginForFetchingData . "' + INTERVAL (a) DAY AS Date "; // here and below, was $this->begin; changed 2020-06-29 JM 
            $query .= "FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 "; 
            $query .= " UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 "; 
            $query .= " UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 ";
            $query .= " UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 ";
            $query .= " UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23) AS b) a ";
            // END REPLACEMENT CODE 2020-03-09 JM
			$query .= "LEFT JOIN   " . DB__NEW_DATABASE . ".workOrderTaskTime wott ON a.Date = wott.day ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTask wot ON wott.workOrderTaskId = wot.workOrderTaskId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTask wotc ON wot.workOrderTaskId = wotc.workOrderTaskId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrder wo ON wot.workOrderId = wo.workOrderId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".job j ON wo.jobId = j.jobId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".task t ON wot.taskId = t.taskId ";
			$query .= "WHERE wott.personId = " . intval($this->user->getUserId()) . " ";
			$query .= "AND a.Date >= '" . $this->beginForFetchingData . "' ";
			$query .= "AND a.Date <= '" . $this->end . "' ";

            // BEGIN MARTIN COMMENT				
			// this is a massive kludge to show the crap that is unfinished in previous weeks' times for the purpose of 
			// adding time to those tasks because they didn't show in prev weeks if no time had been previously added
			// so here just making the fields the same so the union works.
			// this next part of the query was cut and paste from the block below from this conditional
			//   else if ($this->displayType == 'incomplete'){
			// END MARTIN COMMENT
			
			// JM: UNION means we don't have to worry about duplicating the content from above.
			// This latter part of the query finds data for all uncompleted workOrderTasks.
			
			$query .= "UNION ";
			
			$query  .= "SELECT 1 AS editable ";
			$query .= ", 0 AS workOrderTaskTimeId "; // [Martin comment] added for union
			$query .= ", wot.workOrderTaskId ";
			$query .= ", wotp.personId  ";				
			$query .= ", t.icon ";
			$query .= ", wot.taskStatusId";
			$query .= ", wotc.workOrderId ";
			$query .= ", wot.taskId ";
			$query .= ", wot.taskStatusId ";
			$query .= ", wo.workOrderDescriptionTypeId ";				
			$query .= ", wo.description ";
			$query .= ", j.number ";
			$query .= ", j.name ";			
			$query .= ", j.jobId ";
			$query .= ", t.description as taskDescription ";
			$query .= "FROM " . DB__NEW_DATABASE . ".workOrderTask wot ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTaskPerson wotp ON wot.workOrderTaskId = wotp.workOrderTaskId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTask wotc ON wot.workOrderTaskId = wotc.workOrderTaskId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrder wo ON wotc.workOrderId = wo.workOrderId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".job j ON wo.jobId = j.jobId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".task t ON wot.taskId = t.taskId ";
			$query .= "WHERE wotp.personId = " . intval($this->user->getUserId()) . " ";
			$query .= "AND wot.taskStatusId != (select taskStatusId from " . DB__NEW_DATABASE . ".taskStatus where queryName = 'completed') ";
			
			$query .= "GROUP BY wot.workOrderTaskId ORDER BY jobId, workOrderId, workOrderTaskId ";
	
						
            // (Martin's comment below marked "incomplete" matches Joe's list above, before the SELECT; I -- JM -- 
            //  can't really make out what he's up to with the comment marked "returned for previous week"; admittedly,
            //  I didn't put a ton of time into trying to understand.)
			
			/* BEGIN MARTIN COMMENT
			 * 
			incomplete
			 +----------+---------------+--------------+-----------------+-------------+-----------------+--------+--------------+----------+----------------------------+------------------+----------+-------------------+-------+-----------------------+
			| editable | icon          | taskStatusId | workOrderTaskId | workOrderId | workOrderTaskId | taskId | taskStatusId | personId | workOrderDescriptionTypeId | description      | number   | name              | jobId | taskDescription       |
			+----------+---------------+--------------+-----------------+-------------+-----------------+--------+--------------+----------+----------------------------+------------------+----------+-------------------+-------+-----------------------+
			|        1 | Root Task.jpg |            1 |           25786 |        7020 |           25786 |     32 |            1 |     2043 |                         11 | Permit Submittal | s1608037 | 1101 107th Ave SE |  3124 | Closure               |
			|        1 | zdef_task.jpg |            1 |           25790 |        7020 |           25790 |    285 |            1 |     2043 |                         11 | Permit Submittal | s1608037 | 1101 107th Ave SE |  3124 | Wood Shearwall Design |
			|        1 | Root Task.jpg |            1 |           25791 |        7020 |           25791 |     36 |            1 |     2043 |                         11 | Permit Submittal | s1608037 | 1101 107th Ave SE |  3124 | Lateral Design        |
			+----------+---------------+--------------+-----------------+-------------+-----------------+--------+--------------+----------+----------------------------+------------------+----------+-------------------+-------+-----------------------+
			 
			 returned for previous week 
			+----------+---------------------+-----------------+----------+---------------+--------------+-------------+--------+--------------+----------------------------+------------------+----------+-------------------+-------+-----------------+
			| editable | workOrderTaskTimeId | workOrderTaskId | personId | icon          | taskStatusId | workOrderId | taskId | taskStatusId | workOrderDescriptionTypeId | description      | number   | name              | jobId | taskDescription |
			+----------+---------------------+-----------------+----------+---------------+--------------+-------------+--------+--------------+----------------------------+------------------+----------+-------------------+-------+-----------------+
			|        1 |               21119 |           25786 |     2043 | Root Task.jpg |            1 |        7020 |     32 |            1 |                         11 | Permit Submittal | s1608037 | 1101 107th Ave SE |  3124 | Closure         |
			+----------+---------------------+-----------------+----------+---------------+--------------+-------------+--------+--------------+----------------------------+------------------+----------+-------------------+-------+-----------------+
			
			END MARTIN COMMENT
			*/
		
		} else if ($this->displayType == 'incomplete') {
		    // Rows have same structure as above, but query is much simpler.
			$query  = "SELECT 1 as editable, t.icon,wot.taskStatusId ";
			$query .= ", wot.workOrderTaskId ";
			$query .= ", wotc.workOrderId ";
			$query .= ", wotc.workOrderTaskId ";	
			$query .= ", wot.taskId ";
			$query .= ", wot.taskStatusId ";
			$query .= ", wotp.personId ";
			$query .= ", wo.workOrderDescriptionTypeId ";
			$query .= ", wo.description ";
			$query .= ", j.number ";
			$query .= ", j.name ";
			$query .= ", j.jobId ";
			$query .= ", t.description AS taskDescription ";
			$query .= "FROM " . DB__NEW_DATABASE . ".workOrderTask wot ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTaskPerson wotp ON wot.workOrderTaskId = wotp.workOrderTaskId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderTask wotc ON wot.workOrderTaskId = wotc.workOrderTaskId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".workOrder wo ON wotc.workOrderId = wo.workOrderId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".job j ON wo.jobId = j.jobId ";
			$query .= "JOIN " . DB__NEW_DATABASE . ".task t ON wot.taskId = t.taskId ";
			$query .= "WHERE wotp.personId = " . intval($this->user->getUserId()) . " ";
			$query .= "AND wot.taskStatusId != (SELECT taskStatusId FROM " . DB__NEW_DATABASE . ".taskStatus WHERE queryName = 'completed') ";
			$query .= "ORDER BY j.jobId, wo.workOrderId, wot.workOrderTaskId";
		} // >>>00002 else invalid, ought to log
		$query .= ";";
		
		// >>>00007 Seems to me (JM 2019-03-08) that the "group by" above would already have gotten rid
		// of any duplicates, so I think $nodupes is an irrelevant distraction. But this would require experiments.		
		
        // BEGIN MARTIN COMMENT
        // fucking massive kludge .. no time right now to 
		// deal with duplicate records and what not
		// coming back in the kludged unioned shit above.
		// all this is because need to see current open shit in previous weeks
		// for the purposes of people that want to retrofill previous week 
		// time after the fact.
		// END MARTIN COMMENT
		
		$nodupes = array(); // This exists to prevent us getting two rows for the same workOrderTask
		$result = $this->db->query($query);
		if (!$result) {
		    $this->logger->errorDb('1601940311', 'Hard DB error', $this->db);
		} else {
            while ($row = $result->fetch_assoc()) {
                if (!in_array($row['workOrderTaskId'], $nodupes)) {
                    $rows[] = $row;						
                }
                $nodupes[$row['workOrderTaskId']] = $row['workOrderTaskId'];
            }
		}
		unset($nodupes);
		
		foreach ($rows as $r) {				
            // Elements of the associative array produced by this query:
            // * 'Date': in 'Y-m-d' form
            // * 'smalldate': in 'm/d' form
            // * 'minutes': minutes of work
            // * 'workOrderTaskId': primary key into DB table WorkOrderTask
            // * 'personId': primary key into DB table Person
			$query = "SELECT a.Date, DATE_FORMAT(a.Date,'%m/%d') AS smalldate, wott.minutes, wott.workOrderTaskId, wott.personId ";
			$query .= "FROM (";
			/* BEGIN CODE REPLACED 2020-03-09 JM
			// SEE REMARKS FOR SIMILAR CASE ABOVE FOR WHY THIS WAS REPLACED 
			$query .= " select '" . $this->begin . "' + INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date ";
			$query .= " from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a ";
			$query .= " cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b ";
			$query .= " cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c ";
			$query .= " ) a ";
            // END CODE REPLACED 2020-03-09 JM
            */
            // BEGIN REPLACEMENT CODE 2020-03-09 JM
            // The idea is to get a row for every date, whether it has data or not.
            // We've revised this a couple of times for various requests; originally this used $this->begin, then $this->beginIncludingPrior, now (2020-07-30)
            // $this->beginForFetchingData.
			$query .= "SELECT '" . $this->beginForFetchingData . "' + INTERVAL (a) DAY AS Date "; // here and below, was $this->begin; changed 2020-06-29 JM
            $query .= "FROM (select 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 "; 
            $query .= "UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 "; 
            $query .= "UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 ";
            $query .= "UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 ";
            $query .= "UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23) AS b) a ";
            // END REPLACEMENT CODE 2020-03-09 JM
			$query .= "LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskTime wott ON a.Date = wott.day  ";
			$query .= "WHERE a.Date >= '" . $this->beginForFetchingData . "' ";
			$query .= "AND a.Date <= '" . $this->end . "' ";
			$query .= "AND wott.workOrderTaskId = " . intval($r['workOrderTaskId']) . ";";						
			
			$result = $this->db->query($query);
			if ($result) {
                while ($row = $result->fetch_assoc()){
                    $r['regularitems'][$row['Date']][] = $row;
                }
			} else {
			    $this->logger->errorDb('1602001493', 'Hard DB error', $this->db);
			}
			// So we will add these to $workOrderTasks, and they will have exactly
			// the same form as for PTO above.
			$workOrderTasks[] = $r;
		}
		
        /* We will build $render, a multi-dimensional array using massaged taskIds as its indexes
           (each index is 'a' prefixed to a taskId) to represent the relevant subset of the abstract task hierarchy  
           Also, for any "real" workOrderTask (explicitly represented in DB table WorkOrderTask) the array at the relevant level will
           also have an index 'wots', with its value being an array of WorkOrderTask objects.
           E.g. $render['a35']['a210']['a455']['wots'][$i] is a workOrderTask Object.
           For internal nodes there can be only one array element in that last array, but at the leaf it is *possible*
           for the same abstract task to be used more than once in a workOrder, even for the same job element.
           NOTE that for "real" workOrderTasks -- ones that exist explicitly in DB table WorkOrderTask, rather than merely being
           constructed as the necessary parent of some other workOrderTask -- there will be a ['wots'] value even for an "internal"
           node. So, in the given example, if there is a real workOrderTask with taskId==210, then $render['a35']['a210']['wots']
           will be an array containing a single workOrderTask Object (single because an internal node is basically structural, so
           there is no reason to have more than one such workOrderTask for a given workOrder).
       */

		$render = array();
		$maxdepth = 0;
		
		foreach ($workOrderTasks as $wot) { 			
			if (intval($wot['taskId'])) {				
				$task = new Task($wot['taskId']);
				
				/* BEGIN REPLACED 2020-10-05 JM
                // For each task, we use the Task method climbTree to get an array of Task objects, 
                //  ordered beginning with a task with no parent, down to the present task. 
                $tree = $task->climbTree(); // The name $tree is a bit misleading here: this is strictly linear.
				// JM 2019-03-08 the following is WAY more complicated and esoteric than 
				// it has any need to be. I'm adding detailed comments explaining what it does,
				// but I highly recommend a less esoteric rewrite. (Feel free to snag me to do that!)
				//
				// At this point $tree is an array of Task objects in order DOWN 
				// the hierarchy, from a task with no parent to the current task.
				// In each case, all we really care about here are the respective taskIds.
				// So, consider the case where the respective taskIds are 35, 210, 455. 
				
				$str = '';					
				foreach ($tree as $tkey => $node) {
				    // In the example given above, as we loop, $str will successively be "['a35']", "['a210']", "['a455']" 
					$str .= '[\'a' . $node->getTaskId() . '\']';
					
					// In the example given above, as we loop, this will successively eval:
					//  if (!isset($render['a35'])) {$render['a35'] = array();}
					//  if (!isset($render['a210'])) {$render['a210'] = array();}
					//  if (!isset($render['a455'])) {$render['a455'] = array();}
					eval('if (!isset($render' . $str . ')){$render' . $str . ' = array();}');
										
					if ($tkey == (count($tree) - 1)) {
					    // In the example given above, on the last time through the loop,
					    // this will eval:
					    // $render['a455']["wots"][] = $wot;					    
						eval('$render' . $str . '["wots"][] = $wot;');							
					}				
				}
				// Make $maxdepth account for the deepest tree we have seen
				if (count($tree) > $maxdepth) {
					$maxdepth = count($tree);
				}
			} else {
			    // e.g. PTO; 00006 I cannot for the life of me imagine why this
			    //  completely vanilla PHP is executed as an eval. - JM 2019-03-08
				eval('$render["a0"]["wots"][] = $wot;');
			}
			// END REPLACED 2020-10-05 JM
			*/
			// BEGIN REPLACEMENT 2020-10-05 JM
                // For each task, we use the Task method climbTree to get an array of Task objects, 
                //  ordered beginning with a task with no parent, down to the present task.
                // $ladder here was previously called $tree, but it is a single linear path from root to leaf, so $ladder is more appropriate.                        
                $ladder = $task->climbTree();
                if (count($ladder)) {
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
			} else {
			    // e.g. PTO
				$render["a0"]["wots"][] = $wot;
			}
			// END REPLACEMENT 2020-10-05 JM
		}
	
		$this->gold = array();
		$this->buildTaskTreeLeadingToWorkOrderTasks($render, $this->gold, 0);
		
		return $this->gold; // [Martin comment] $workOrderTasks;	
	} // END public function getWorkOrderTasksByDisplayType
	
	// For the user & period we are looking at, return all relevant rows from workOrderTaskTimeLateModification,
	// enhanced by some additional context.
	// RETURNs an array of associative arrays, each of whose elements corresponds to a row in DB table workOrderTaskTimeLateModification.
	//  For each row, the associative array contains:
	//  'workOrderTaskTimeLateModificationId' - Primary key into DB table workOrderTaskTimeLateModification 
	//  'workOrderTaskTimeId' - Primary key into DB table workOrderTaskTime
    //  'inserted' - Time (Y-m-d h:m:s) when this row was inserted in workOrderTaskTimeLateModification: a modification by the employee
    //  'oldMinutes' - Old value of workOrderTaskTime.minutes before the change. Can be null.
    //  'newMinutes' - New value of workOrderTaskTime.minutes after the change. Can be null. 
    //  'notificationSent' - 1 if admin was notified, 0 if not.
    //  'jobId' - Primary key into DB table job
    //  'workOrderId' - Primary key into DB table workOrder
    //  'workOrderTaskId' - Primary key into DB table workOrderTask 
    //  'day' - day this refers to in Y-m-d form
    //  'dayOfPeriod' - 0-indexed from $this->beginIncludingPrior
	public function getWorkOrderTaskTimeLateModifications() {
	    $rows = Array();
	    $query  = "SELECT late.*, job.jobId, workOrder.workOrderId, workOrderTask.workOrderTaskId, workOrderTaskTime.day "; 
	    $query .= "FROM " . DB__NEW_DATABASE . ".workOrderTaskTimeLateModification AS late ";
	    $query .= "JOIN workOrderTaskTime ON late.workOrderTaskTimeId = workOrderTaskTime.workOrderTaskTimeId ";
	    $query .= "JOIN workOrderTask ON workOrderTaskTime.workOrderTaskId = workOrderTask.workOrderTaskId ";
	    $query .= "JOIN workOrder ON workOrderTask.workOrderId = workOrder.workOrderId ";
	    $query .= "JOIN job ON job.jobId = workOrder.jobId ";
	    $query .= "WHERE workOrderTaskTime.personId = " . intval($this->user->getUserId()) . " "; 
	    $query .= "AND workOrderTaskTime.day >= '" .  $this->beginIncludingPrior . "' ";
	    $query .= "AND workOrderTaskTime.day <= '" .  $this->endOfPayPeriod . "' ";
	    $query .= "ORDER BY	job.jobId, workOrder.workOrderId, workOrderTask.workOrderTaskId, workOrderTaskTime.day, late.inserted;";
	    
	    $result = $this->db->query($query);
	    if ($result) {
	        while ($row = $result->fetch_assoc()) {
                // We take advantage of the fact the fact that pay periods never cross month boundaries, so we can cheat a little in 
                // calculating day of period from day of month.
	            $row['dayOfPeriod'] = intval(explode('-', $row['day'])[2]) - intval(explode('-', $this->beginIncludingPrior)[2]); 
	            $rows[] = $row; 
	        }
	    } else {
	        $this->logger->errorDb('1602176001', "Hard DB error", $this->db);
	    }
	    return $rows;
	} // END public function getWorkOrderTaskTimeLateModifications
	
	// For the user & period we are looking at, return all relevant rows from ptoLateModification.
	// RETURNs an array of associative arrays, each of whose elements corresponds to a row in DB table ptoLateModification.
	//  For each row, the associative array contains:
	//  'ptoLateModificationId' - Primary key into DB table ptoLateModification 
    //  'ptoId' - Primary key into DB table pto
    //  'inserted' - Time (Y-m-d h:m:s) when this row was inserted in workOrderTaskTimeLateModification: a modification by the employee
    //  'oldMinutes' - Old value of workOrderTaskTime.minutes before the change. Can be null.
    //  'newMinutes' - New value of workOrderTaskTime.minutes after the change. Can be null. 
    //  'notificationSent' - 1 if admin was notified, 0 if not.
    //  'day' - day this refers to in Y-m-d form
    //  'dayOfPeriod' - 0-indexed from $this->beginIncludingPrior
	public function getPtoLateModifications() {
	    $rows = Array();
	    $query  = "SELECT late.*, pto.day "; 
	    $query .= "FROM " . DB__NEW_DATABASE . ".ptoLateModification AS late ";
	    $query .= "JOIN pto ON late.ptoId = pto.ptoId ";
	    $query .= "WHERE pto.personId = " . intval($this->user->getUserId()) . " "; 
	    $query .= "AND pto.day >= '" .  $this->beginIncludingPrior . "' ";
	    $query .= "AND pto.day <= '" .  $this->endOfPayPeriod . "' ";
	    $query .= "ORDER BY	pto.day, late.inserted;";
	    
	    $result = $this->db->query($query);
	    if ($result) {
	        while ($row = $result->fetch_assoc()) {
                // We take advantage of the fact the fact that pay periods never cross month boundaries, so we can cheat a little in 
                // calculating day of period from day of month.
	            $row['dayOfPeriod'] = intval(explode('-', $row['day'])[2]) - intval(explode('-', $this->beginIncludingPrior)[2]); 
	            $rows[] = $row; 
	        }
	    } else {
	        $this->logger->errorDb('1602184511', "Hard DB error", $this->db);
	    }
	    return $rows;
	} // END public function getPtoLateModifications

	// For the user & period we are looking at, return a more processed version of the data from DB table ptoLateModification.
	// For each day, we work out the net effect of all modifications
	// RETURNs an array of associative arrays, each of whose elements can summarize one or more rows in DB table ptoLateModification.
	//  Each associative array contains:
    //  'workOrderTaskTimeId' - Primary key into DB table workOrderTaskTime. We rely on all data for the same workOrderTaskTimeId being grouped together
    //  'oldMinutes' - Old value of workOrderTaskTime.minutes before the first change. Can be null.
    //  'newMinutes' - New value of workOrderTaskTime.minutes after the last change. Can be null. 
    //  'notificationSent' - 1 if admin was notified of last change, 0 if not.
    //  'day' - day this refers to in Y-m-d form
    //  'dayOfPeriod' - 0-indexed from $this->beginIncludingPrior
    //  'jobId' - Primary key into DB table job
    //  'workOrderId' - Primary key into DB table workOrder
    //  'workOrderTaskId' - Primary key into DB table workOrderTask 
	public function getWorkOrderTaskTimeNetLateModifications() {
	    $ret = Array();
	    $wotLateModifications = $this->getWorkOrderTaskTimeLateModifications();

        $workOrderTaskTimeId = null;
        // $ptoLateModifications is in (day, inserted) order
        foreach ($wotLateModifications as $wotLateModification) {
            if ($wotLateModification['newMinutes'] != $wotLateModification['oldMinutes']) {
                if ($wotLateModification['workOrderTaskTimeId'] != $workOrderTaskTimeId) {
                    // new workOrderTaskTimeId
                    $workOrderTaskTimeId = $wotLateModification['workOrderTaskTimeId']; 
                    $ret[] = Array(
                       'workOrderTaskTimeId' => $wotLateModification['workOrderTaskTimeId'],
                       'oldMinutes' => $wotLateModification['oldMinutes'],
                       'newMinutes' => $wotLateModification['newMinutes'],
                       'notificationSent' => $wotLateModification['notificationSent'],
                       'day' => $wotLateModification['day'],
                       'dayOfPeriod' => $wotLateModification['dayOfPeriod'], 
                       'jobId' => $wotLateModification['jobId'],
                       'workOrderId' => $wotLateModification['workOrderId'],
                       'workOrderTaskId' => $wotLateModification['workOrderTaskId'] 
                    );
                } else {
                    // same workOrderTaskTimeId
                    $ret[count($ret)-1]['newMinutes'] = $wotLateModification['newMinutes'];
                    $ret[count($ret)-1]['notificationSent'] = $wotLateModification['notificationSent'];
                }
            }
        }        
        return $ret;
	} // END public function getWorkOrderTaskTimeNetLateModifications
	
	// For the user & period we are looking at, return a more processed version of the data from DB table ptoLateModification.
	// For each day, we work out the net effect of all modifications
	// RETURNs an array of associative arrays, each of whose elements can summarize one or more rows in DB table ptoLateModification.
	//  Each associative array contains:
    //  'ptoId' - Primary key into DB table pto. We rely on all data for the same ptoId being grouped together.
    //  'oldMinutes' - Old value of workOrderTaskTime.minutes before the first change. Can be null.
    //  'newMinutes' - New value of workOrderTaskTime.minutes after the last change. Can be null. 
    //  'notificationSent' - 1 if admin was notified of last change, 0 if not.
    //  'day' - day this refers to in Y-m-d form
    //  'dayOfPeriod' - 0-indexed from $this->beginIncludingPrior
	public function getPtoNetLateModifications() {
	    $ret = Array();
	    $ptoLateModifications = $this->getPtoLateModifications();
	    
        $ptoId = null;
        // $ptoLateModifications is in (day, inserted) order
        foreach ($ptoLateModifications as $ptoLateModification) {
            if ($ptoLateModification['newMinutes'] != $ptoLateModification['oldMinutes']) {
                if ($ptoLateModification['ptoId'] != $ptoId) {
                    // new ptoId
                    $ptoId = $ptoLateModification['ptoId'];
                    $ret[] = Array(
                       'ptoId' => $ptoLateModification['ptoId'],
                       'oldMinutes' => $ptoLateModification['oldMinutes'],
                       'newMinutes' => $ptoLateModification['newMinutes'],
                       'notificationSent' => $ptoLateModification['notificationSent'],
                       'day' => $ptoLateModification['day'],
                       'dayOfPeriod' => $ptoLateModification['dayOfPeriod']
                    );
                } else {
                    // same ptoId
                    $ret[count($ret)-1]['newMinutes'] = $ptoLateModification['newMinutes'];
                    $ret[count($ret)-1]['notificationSent'] = $ptoLateModification['notificationSent'];
                }
            }
        }
        return $ret;
	} // END public function getPtoNetLateModifications
	
	// For the user & period we are looking at, delete all relevant rows from workOrderTaskTimeLateModification (after admin accepts them).
	public function deleteWorkOrderTaskTimeLateModifications() {
	    $rows = Array();
	    $query  = "DELETE FROM " . DB__NEW_DATABASE . ".workOrderTaskTimeLateModification \n";
	    $query .= "WHERE workOrderTaskTimeLateModificationId IN (\n";
	    $query .= "    SELECT * FROM (\n"; // we need this extra level due to a MySQL quirk
	    $query .= "        SELECT workOrderTaskTimeLateModificationId FROM " . DB__NEW_DATABASE . ".workOrderTaskTimeLateModification \n";
	    $query .= "        JOIN " . DB__NEW_DATABASE . ".workOrderTaskTime \n";
	    $query .= "          ON workOrderTaskTimeLateModification.workOrderTaskTimeId = workOrderTaskTime.workOrderTaskTimeId \n";
	    $query .= "        WHERE workOrderTaskTime.personId = " . intval($this->user->getUserId()) . " \n"; 
	    $query .= "        AND workOrderTaskTime.day >= '" .  $this->beginIncludingPrior . "' \n";
	    $query .= "        AND workOrderTaskTime.day <= '" .  $this->endOfPayPeriod . "' ";
	    $query .= "    ) B\n";
	    $query .= ");";
	    
	    $result = $this->db->query($query);
	    if (!$result) {
	        $this->logger->errorDb('1602188728', "Hard DB error", $this->db);
	    }
	    return $rows;
	} // END public function deleteWorkOrderTaskTimeLateModifications
	
	// For the user & period we are looking at, delete all relevant rows from ptoLateModification (after admin accepts them).
	public function deletePtoLateModifications() {
	    global $timeManagerEmails;
	    
	    $rows = Array();
	    $query  = "DELETE FROM " . DB__NEW_DATABASE . ".ptoLateModification \n";
	    $query .= "WHERE ptoLateModificationId IN (\n";
	    $query .= "    SELECT * FROM (\n"; // we need this extra level due to a MySQL quirk
	    $query .= "        SELECT ptoLateModificationId FROM " . DB__NEW_DATABASE . ".ptoLateModification \n";
	    $query .= "        JOIN " . DB__NEW_DATABASE . ".pto ON ptoLateModification.ptoId = pto.ptoId \n";
	    $query .= "        WHERE pto.personId = " . intval($this->user->getUserId()) . " \n"; 
	    $query .= "        AND pto.day >= '" .  $this->beginIncludingPrior . "' \n";
	    $query .= "        AND pto.day <= '" .  $this->endOfPayPeriod . "' \n";
	    $query .= "    ) B\n";
	    $query .= ");";
	    
	    $result = $this->db->query($query);
	    if (!$result) {
	        $this->logger->errorDb('1602188635', "Hard DB error", $this->db);
	    }
	    return $rows;
	} // END public function deletePtoLateModifications
	
	// Emails notifications for the late modifications for the relevant user & payperiod
	public function notifyLateModifications() {
	    global $timeManagerEmails; // from inc/config.php
	    
	    $personId = $this->user->getUserId();
        $customerPerson = CustomerPerson::getFromPersonId($personId);
        list($target_email_address, $firstName, $lastName) = $customerPerson->getEmailAndName();
        
        $mail = new SSSMail();
        $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
        if ($target_email_address) {
            $mail->addTo($target_email_address, $firstName);
            $mail->setSubject('Pay period timesheet was modified');                    
        } else {
            $logger->error2('1602534679', "Cannot find email address for $firstName $lastName (personId = $personId) at customer " . $customer->getCustomerId());
            $mail->setSubject('FAILED Pay period timesheet modified reminder');
            $body = "Could not find email address for customerId " . $customer->getCustomerId() . " personId " . $personId . 
                    " " . $firstName . " " . $lastName . "\n\n" . $body;
        }
        foreach ($timeManagerEmails as $timeManagerEmail) {
            $mail->addTo($timeManagerEmail, "Manager");
        }
        
	    $netLatePtoModifications = $this->getPtoNetLateModifications();
	    $netLateWotModifications = $this->getWorkOrderTaskTimeNetLateModifications();
	    
	    // What is the overall net change?
        $totalOldMinutes = 0;
        $totalNewMinutes = 0;
        foreach ($netLatePtoModifications as $netLatePtoModification) {
            $totalOldMinutes += $netLatePtoModification['oldMinutes'];
            $totalNewMinutes += $netLatePtoModification['newMinutes'];
        }
        foreach ($netLateWotModifications as $netLateWotModification) {
            $totalOldMinutes += $netLateWotModification['oldMinutes'];
            $totalNewMinutes += $netLateWotModification['newMinutes'];
        }
	    
        $body = "This is an auto-generated email summarizing late changes to the timesheet for $firstName $lastName " .  
                "for the pay period from " . $this->beginIncludingPrior . " to " . $this->end . "\n";
        if ($totalOldMinutes == $totalNewMinutes) {
            $body .= 'The total logged time for this period remains the same.' . "\n\n";
        } else if ($totalOldMinutes > $totalNewMinutes) {
            $body .= 'The total logged time for this period has decreased by ' . 
            number_format((float)($totalOldMinutes - $totalNewMinutes)/60, 2, '.', '') . ' hr' . "\n\n";
        } else {
            $body .= 'The total logged time for this period has increased by ' . 
            number_format((float)($totalNewMinutes - $totalOldMinutes)/60, 2, '.', '') . ' hr' . "\n\n";
        }
        
        foreach ($netLatePtoModifications as $netLatePtoModification) {
            $body .= 'PTO for ' . $netLatePtoModification['day'] . ' changed from ' .
                number_format((float)$netLatePtoModification['oldMinutes']/60, 2, '.', '') . ' hr to ' .
                number_format((float)$netLatePtoModification['newMinutes']/60, 2, '.', '') . ' hr' . "\n";
        }
        if (count($netLatePtoModifications)) {
            $body .= "\n";
        }        
        
        $jobId = null; 
        $workOrderId = null;
        $workOrderTaskId = null;
        foreach ($netLateWotModifications as $netLateWotModification) {
            if ($netLateWotModification['jobId'] != $jobId) {
                $job = new Job($jobId);
                $body .= 'JOB: ' . $job->getName() . ' ['. $job->getNumber() . ']' . "\n";
            }
            if ($netLateWotModification['workOrderId'] != $workOrderId) {
                $workOrder = new WorkOrder($workOrderId);
                $body .= '  WO: ' . $workOrder->getName() . "\n";
            }
            if ($netLateWotModification['workOrderTaskId'] != $workOrderTaskId) {
                $workOrderTask = new WorkOrderTask($workOrderTaskId);
                $task = new Task($workOrderTask->getTaskId());
                $body .= '    ' . $task->getDescription() . "\n";
            }
            $body .= '      Time worked ' . $netLatePtoModification['day'] . ' changed from ' .
                number_format((float)$netLateWotModification['oldMinutes']/60, 2, '.', '') . ' hr to ' .
                number_format((float)$netLateWotModification['newMinutes']/60, 2, '.', '') . ' hr' . "\n";
            
            $jobId = $netLateWotModification['jobId']; 
            $workOrderId = $netLateWotModification['workOrderId'];
            $workOrderTaskId = $netLateWotModification['workOrderTaskId'];        
        }
        $body .= "\n--END--\n";
        $mail->setBodyText($body);
        $mail_result = $mail->send();
        if ($mail_result) {
            $this->logger->info2('1602538532', 'Timesheet modification mail sent to ' .
                "$target_email_address, $firstName; Manager for pay period from " . $this->beginIncludingPrior . " to " . $this->end);
        } else {
            // "fail"; logging added here 2020-08-04 JM
            $this->logger->error2('1602538708', 'Failed sending timesheet modification mail to ' . 
                "$target_email_address, $firstName; Manager for pay period from " . $this->beginIncludingPrior . " to " . $this->end);
        }
	} // END public function notifyLateModifications
	
	// This function has to do with navigating the task hierarchy, potentially
	//  including "fake" tasks that are not overtly part of the workOrder even 
	//  though their descendants are. It is highly analogous to  
	//  private function WorkOrder::buildTaskTreeLeadingToWorkOrderTasks();
	//  the only difference is that the entry here for a 'real' function, which
	//  places $wot contents one level higher in the array than the WorkOrder approach.
	//  That is, $gold[$i]['data'][FOO] in the WorkOrder approach will be
	//   $gold[$i][FOO] here. >>>00001 it might be worth rewriting this so that the
	//   two structures are actually identical.
	//  >>>00001 JM 2020-10-05: I'm not sure how this relates to the multiple "elements"
	//   (buildings, etc.) of a job. It may or may not be analogous to the similar
	//   WorkOrder function in that respect.
	//
	// Prior to 2020-10-05, this was an inappropriately public function called just 'recursive'
	private function buildTaskTreeLeadingToWorkOrderTasks($array, &$gold, $level) {			
		foreach ($array as $key => $value) {	
			if (array_key_exists('wots', $value)) {					
				foreach ($value['wots'] as $wot) {	
					$wot['type'] = 'real'; 
					$wot['level'] = $level;
					
					$gold[] = $wot; //[Martin comment] array('type' => 'real', 'level' => $level, 'data' => $wot);	
				}					
			} else {
				if ($key != 'wots') {
					$gold[] = array('type' => 'fake', 'level' => $level, 'data' => $key);
				}
			}
	
			if ($key != 'wots') {					
				if(is_array($value) && count($value)){
					$this->buildTaskTreeLeadingToWorkOrderTasks($value, $gold, $level + 1);
				}	
			}	
		}			
	} // END function buildTaskTreeLeadingToWorkOrderTasks
	
	/* BEGIN REMOVED 2020-10-06 JM
	The only call to this set a variable that was then ignored. I've removed that, and am killing this. - JM
	
	// INPUT $hireDate: in DateTime form
	// RETURN an associative array with two elements, 'begin' and 'end'. Both are DateTimes. 
	//  'begin' is either the hire date itself or an anniversary of the hire date; 'end is
	//  exactly one day shy of their next work anniversary. So 'begin' and 'end' together 
	//  will always represent a period of exactly a year that (1) includes the current date, and 
	//  (2) begins on the hire date itself or an anniversary of the hire date.
	public static function getAnniversaryPeriod($hireDate) {		
		$hireDate = new DateTime($hireDate);
		
		$annivThisYear = new DateTime(date("Y") . "-" . $hireDate->format("m") . "-" . $hireDate->format("d"));
		$currentDate = new DateTime();
		
		if ($annivThisYear > $currentDate) {		
			$periodEnd = clone $annivThisYear;
			$periodEnd->modify("-1 day");
			$periodBegin = clone $annivThisYear;
			$periodBegin->modify("-1 year");		
		} else {		
			$periodBegin = $annivThisYear;
			$periodEnd = clone $annivThisYear;
			$periodEnd->modify("+1 year");
			$periodEnd->modify("-1 day");		
		}
		
		return array('begin' => $periodBegin, 'end' => $periodEnd);
		
	} // END public static function getAnniversaryPeriod
	END REMOVED 2020-10-06 JM
	*/
}

?>