<?php
/*  _admin/axios/getemployees.php

    INPUT $_REQUEST['begin'] : date, start of a pay period
    
    EXECUTIVE SUMMARY: Get basic data about all employees of current customer, for a pay period, 
     formatted for display.
    NOTE that this starts from a list of current employees at the time it is being run, not
    a list of who were employees during that pay period.

    JM: 2019-05
    Exact status of this is unknown. This was Martin's work in progress late
     December 2018 - January 2019. Presumably related to _admin/time/biggrid2.php
     and _admin/time/biggrid.js. See those for more context.
    >>>00001: No idea whether this is something worth salvaging or not. At a quick read, it looks OK
    
    Axios is a "promise-based HTTP client for the browser and node.js" -- https://github.com/axios
    No idea what it has to do with this.
    
    Committed as-is 2019-02-11, cleaned up somewhat 2019-05-14.
    
    Returns JSON for an associative array with the following members:
        * 'title' - title for a report, e.g. 'Period: 06-01 thru 06-15-2020';
        * 'prevtext' - '<<prev' (HTML-encoded)
        * 'prevdate' - start of previous period, e.g. '05-16-2020' (>>>00001: format may be different, haven't checked) 
        * 'nexttext' - 'next>>' (HTML-encoded)
        * 'nextdate' - start of next period, e.g. '06-16-2020' (>>>00001: format may be different, haven't checked)
        * 'employees': array of associative arrays, each with the following members;
          (the first three of which appear to be formatted for display): 
            * 'rateDisp' - employee rate (hourly or yearly)
            * 'iraDisp' - IRA number
            * 'iraType' - (percent or amount)   
            * 'iraTypeId' - must be intended as an HTML ID: 'iraType_' . $customerPersonId
            * 'iraDispId' - must be intended as an HTML ID: 'iraDisp_' . $customerPersonId
            * 'rateDispId' - must be intended as an HTML ID: 'rateDisp_' . $customerPersonId  
   
*/   

include '../../inc/config.php';
include '../../inc/access.php';

$db = DB::getInstance();

$employees = $customer->getEmployees(1);

$data = array();

$begin = isset($_REQUEST['begin']) ? $_REQUEST['begin'] : '';

$time = new Time(0, $begin, 'payperiod');

$e = date('Y-m-d', strtotime('-1 day', strtotime($time->next)));
$data['title'] = 'Period: ' . date("m-d", strtotime($time->begin)) . ' thru ' . date("m-d", strtotime($e)) . '-' . date("Y", strtotime($time->begin));

$data['prevtext'] = '&lt;&lt;prev';
$data['prevdate'] = rawurlencode($time->previous);

$data['nexttext'] = 'next&gt;&gt;';
$data['nextdate'] = rawurlencode($time->next);

foreach ($employees as $employee) {    
    $p = new Person($employee->getUserId());    
    
    $query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo  ";
    $query .= " where customerPersonId = (select customerPersonId from " . 
                DB__NEW_DATABASE . ".customerPerson where customerId = " . intval($customer->getCustomerId()) . 
                " and personId = " . $employee->getUserId() . ") ";
    $query .= " and periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "' ";
    $query .= " limit 1 ";
    
    $cpppi = false;
    
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0){
            while ($row = $result->fetch_assoc()){
                $cpppi = $row;
            }
        }
    } // >>>00002 ignores failure on DB query!
    
    // >>>00044 JM 2020-05-08: Nonbreaking spaces probably should be '---' instead, but I haven't had a chance to really see the context in which these are used.
    $rateDisp = '&nbsp;';  
    $iraDisp = '&nbsp;';
    
    $iraType = 0;
    $customerPersonPayPeriodInfoId = 0;
    $customerPersonId = 0;
    if ($cpppi) {        
        $customerPersonId = $cpppi['customerPersonId'];        
        $x = $cpppi; // >>>00012 why introduce an additional, even less mnemonic, name for the same value?        
        $customerPersonPayPeriodInfoId = $x['customerPersonPayPeriodInfoId'];        
        $rate = $x['rate'];
        $salaryHours = $x['salaryHours'];
        $salaryAmount = $x['salaryAmount'];
        
        if (is_numeric($salaryAmount) && ($salaryAmount > 0)){
            $rateDisp = '$' . number_format(($salaryAmount/100),2) . '/yr';
        } else if (is_numeric($rate) && ($rate > 0)){
            $rateDisp = '$' . number_format(($rate/100),2) . '/hr';
        }
        
        $iraType = 	$x['iraType'];
        $ira = 	$x['ira'];
        
        if (is_numeric($ira)) {            
            $iraDisp = $ira;            
        } else {
            $iraDisp = 0;
        }        
    }
    
    $array = $p->toArray();
    
    $array['rateDisp'] = $rateDisp;
    $array['iraDisp'] = $iraDisp;
    $array['iraType'] = $iraType;    
    $array['iraTypeId'] = 'iraType_' . $customerPersonId;
    $array['iraDispId'] = 'iraDisp_' . $customerPersonId;
    $array['rateDispId'] = 'rateDisp_' . $customerPersonId;
    $data['employees'][] = $array;    
    
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>