<?php
/*  c/jobs.php

    EXECUTIVE SUMMARY: some sort of way to view certain jobs as a "private" page
    for non-employees. 
    
    To do anything nontrivial here, the hash must be current and there must be a 
    row in DB table Private where privateId = $_REQUEST['t'] and privateTypeId == PRIVTYPE_JOBS (=2).
    
    INPUTS:    
        * $_REQUEST['e']: expiration time for hash
        * $_REQUEST['hash']
        * $_REQUEST['t']: token (privateId)
        * $_REQUEST['act']: OPTIONAL Possible values 'setintake', 'email'
        * $_REQUEST['workOrderId']: OPTIONAL. Should be set if $_REQUEST['act'] == 'setintake'; 
            indicates what workOrderId to update to the date indicated by $_REQUEST[foointake_bar]. 
            >>>00016 JM: Looks like it may not be sufficiently validated.
        * Any number of variables $_REQUEST[foointake_bar] for any values of foo and bar: 
           The last of these sets variable $date. (>>>00014 JM: seems an odd way to do things, what's going on here?). 
           OPTIONAL. Should definitely be set if $_REQUEST['act'] == 'setintake'.
           >>>00016 JM: Looks like it may not be sufficiently validated.
        * $_REQUEST['personEmailId']: OPTIONAL Primary key into DB table personEmail. 
           Should be set if $_REQUEST['act'] == 'email'. Well-validated,     

     That is activity by a non-employee, so we log every call to this, with full details of what was requested/done.
*/

include '../inc/config.php';

ini_set('display_errors',1);
error_reporting(-1);

?>
<html>
<head>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">	
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>	
    <script type="text/javascript" src="/js/jquery.tablesorter.min.js"></script>
</head>
<body style="font-family:sans-serif">
    <script>
        $(document).ready(function() { 
            $("#tablesorter-demo").tablesorter(); 
        }); 
    </script>
    <?php
    $act = '';
    $personEmailId = 0;
    $date = '';
    $workOrderId = 0;
    
    // Unset each $_REQUEST as we read it, so that $act == 'email' case gets only the residuum.
    // NOTE that we DON'T unset $_REQUEST['e'], $_REQUEST['hash'], & $_REQUEST['t'].
    if (isset($_REQUEST['act'])) {
        $act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
        unset($_REQUEST['act']);
    }
    if (isset($_REQUEST['workOrderId'])) {
        $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';
        unset($_REQUEST['workOrderId']);
    }
    foreach ($_REQUEST as $key => $value) {
        $pos = strpos($key, 'intake_');
        if ($pos !== false){
            $date = $value;
            unset($_REQUEST[$key]); 
        }
    }
    if (isset($_REQUEST['personEmailId'])) {
        $personEmailId = isset($_REQUEST['personEmailId']) ? $_REQUEST['personEmailId'] : '';
        unset($_REQUEST['personEmailId']);
    }    
    
    $db = DB::getInstance();
    
    if (isPrivateSigned($_REQUEST, PRIVATE_HASH_KEY)) {    
        $token = isset($_REQUEST['t']) ? intval($_REQUEST['t']) : 0;    
        if ($token) {    
            $query = " select * from " . DB__NEW_DATABASE . ".private where privateId = " . intval($token);    
            $row = false;    
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    // Only can be one row, since privateId is primary key
                    $row = $result->fetch_assoc();
                }
            } // >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.
    
            if ($row) {
                if ($row['privateTypeId'] == PRIVTYPE_JOBS) {
                    // Hash etc. refers to something real
                    $personId = $row['id'];
                    $oid = $personId;
                    
                    // Because we create these "private" pages for outside users rather than employees, 
                    // we need to have a way for someone to see how those pages behave.
                    
                    // If the person is a designated tester (as of 2019-04, just Ron)
                    // we change it to a fixed outside user (as of 2019-04, sarah@noviongroup.com)
                    // though we always keep the original around as $oid.
                    // 
                    // $privatePageTesters comes from inc/config.php
                    foreach ($privatePageTesters as $tester) {
                        if ($personId == $tester) {
                            $personId = PRIVATE_PAGE_TEST_CASE;
                            break;
                        }
                    }
                    
                    $person = new Person($personId);
                    
                    if ($act == 'setintake') {
                        // This is specific to a passed-in workOrderId. 
                        $w = new WorkOrder($workOrderId);                        
                        $clients = $w->getTeamPosition(TEAM_POS_ID_CLIENT);
                        $pros = $w->getTeamPosition(TEAM_POS_ID_DESIGN_PRO);    
                        $ok = false;
                        
                        // For each client associated with this workOrder (JM is pretty certain there should 
                        // never be more than one), if it matches the personId we got from the hash, 
                        // then we echo 'yes' and set an $ok flag that lets this action continue.                         
                        foreach ($clients as $client) {
                            $cp = new CompanyPerson($client['companyPersonId']);
                            if ($personId == $cp->getPerson()->getPersonId()){
                                echo 'yes'; // >>>00014 what is this about? Looks like it will go into the HTML...
                                $ok = true;
                            }
                        }
                        // For each design professional associated with this workOrder, if it matches 
                        // the personId we got from the hash, then we also set the $ok flag, but don't echo 'yes'.
                        foreach ($pros as $pro) {
                            $cp = new CompanyPerson($pro['companyPersonId']);
                            if ($personId == $cp->getPerson()->getPersonId()){
                                $ok = true;
                            }
                        }
                        
                        if ($ok) {
                            // Update the relevant workOrder in the DB
                            
                            $oldIntakeDate = $w->getIntakeDate(); // ADDED 2020-07-22 JM for clearer logging
                            
                            $db = DB::getInstance();
                            $newdate = date("Y-m-d 00:00:00", strtotime($date));                            
                            $query = "update " . DB__NEW_DATABASE . ".workOrder set intakeDate = '" . $db->real_escape_string($newdate) . 
                                     "' where workOrderId = " . intval($w->getWorkOrderId()) . " ";
                            $db->query($query);
    
                            // Log that we did this
                            /* BEGIN REPLACED 2020-07-22 JM       
                            // how on earth do we have a DB table called "crap"
                            $dat = " intakedate :: pid-" . $oid . " :: woid-" . $w->getWorkOrderId() . " :: from-" . $w->getIntakeDate() . 
                                   " :: to-" . $newdate . "";
                            $query = "insert into " . DB__NEW_DATABASE . ".crap (data) values ('" . $db->real_escape_string($dat) . "')";
                            $db->query($query);
                            END REPLACED 2020-07-22 JM
                            */
                            // BEGIN REPLACEMENT 2020-07-22 JM
                            $logger->info2('1595452315', "personId (from DB table 'private'): $oid; workOrderId " / $w->getWorkOrderId() . 
                                  "; intake date changed from $oldIntakeDate to $newdate");
                            // END REPLACEMENT 2020-07-22 JM
                        }
                        // BEGIN MARTIN COMMENT
                        //	[workOrderId] => 9919
                        //	[intake_9919] => 10/15/2018 ) setintakeArray ( [e] => 1540070585 [t] => 888 [hash] => e93ab98732c746b80ec268c2345e3e3591f5a3df )
                        // END MARTIN COMMENT
                    } // END if ($act == 'setintake')
                    
                    if ($act == 'email') {
                        if (intval($personEmailId)) {
                            $emails = $person->getEmails();
                            foreach ($emails as $email) {
                                if ($email['personEmailId'] == $personEmailId) {
                                    // Requested email address matches person making request.
                                    $qs = '';                                    
                                    foreach ($_REQUEST as $key => $value) {                                        
                                        if (strlen($qs)) {
                                            //  not the first one
                                            $qs .= '&';
                                        }                                        
                                        $qs .= rawurlencode($key) . '=' . rawurlencode($value);                                        
                                    }
                                    
                                    $body = "Here is the link for your active Workorders/Jobs\n\n";
    
                                    $body .= (isset($_SERVER['HTTPS']) ? "https://" : "http://") . 
                                             $_SERVER['HTTP_HOST'] .
                                             "/c/jobs.php?" . $qs;
                                    
                                    $body .= "\n\n\n";
                                    
                                    $mail = new SSSMail();
                                    $mail->addTo($email['emailAddress'], '');
                                    
                                    $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
                                    
                                    $mail->setSubject('Active Workorder/Job List');
                                    $mail->setBodyText($body);
                                    $result = $mail->send();
                                    if ($result) {    
                                        echo '<center>Mail Sent OK!!</center>';
                                        echo '<p></p>';
                                        // BEGIN ADDED 2020-07-28 JM
                                        $logger->info2('1595951255', "personId (from DB table 'private'): $oid; email sent");
                                        // END ADDED 2020-07-28 JM
                                    } else {    
                                        echo '<center>Problem sending mail!!</center>';
                                        echo '<p></p>';
                                        // BEGIN ADDED 2020-07-28 JM
                                        $logger->error2('1595951296', "personId (from DB table 'private'): $oid; sending email failed");
                                        // END ADDED 2020-07-28 JM
                                    }
                                }
                            }
                        }
                    } // END if ($act == 'email')
                    
                    // Now, regardless of any "act", get a list of all companies with which the current person is associated; 
                    //  then for each of these companies we build arrays of active and inactive jobs. For each of these, 
                    //  the elements of the arrays are associative arrays, basically as returned from Company::getJobs, 
                    //  but with one additional member 'companyId'.
                    if (intval($person->getPersonId())) {    
                        $cps = $person->getCompanyPersons();                
                        $companies = array();                        
                        foreach ($cps as $cp) {                            
                            $companies[] = $cp->getCompany();                            
                        }
    
                        $activejobs = array();
                        $inactivejobs = array();
                        
                        foreach ($companies as $company) {                            
                            $rawjobs = $company->getJobs();
                            $all = array();
                            $jobs = array();            			
                            
                            foreach ($rawjobs as $job) {
                                $all[$job['jobId']] = $job;
                            }
    
                            foreach ($all as $a) {
                                $jobs[] = $a;
                            }
    
                            foreach ($jobs as $job) {                               
                                $job['companyId'] = $company->getCompanyId();
                                /* BEGIN REPLACED 2020-11-18 JM - we are getting rid of realStatus
                                if ($job['realStatus'] == 1) {                                    
                                    $activejobs[] = $job;                                    
                                } else {                                    
                                    $inactivejobs[] = $job;                                    
                                } 
                                // END REPLACED 2020-11-18 JM
                                */
                                // BEGIN REPLACEMENT 2020-11-18 JM
                                $jobObject = new Job($job['jobId']);
                                if ($jobObject->isActive()) {                                    
                                    $activejobs[] = $job;                                    
                                } else {                                    
                                    $inactivejobs[] = $job;                                    
                                } 
                                // END REPLACEMENT 2020-11-18 JM
                            }                            
                        }
                        
                        // We start the main display with a table that has one cell to a row. This code should be pretty self-explanatory.      
                        echo '<center>';
                        
                        echo '<table border="0" cellpadding="6" cellspacing="4" width="500">';
                            echo '<tr>';
                                echo '<td align="center"><h3>Active Workorders/Jobs</h3></td>';
                            echo '</tr>';
                            echo '<tr>';                            
                                $qs = '';                            
                                foreach ($_REQUEST as $key => $value) {
                                    if (strlen($qs)) {
                                        $qs .= '&';
                                    }
                                
                                    $qs .= rawurlencode($key) . '=' . rawurlencode($value);
                                }                                
                                $emails = $person->getEmails();
                                
                                if (count($emails) > 0) {
                                    echo '<tr><td>To get this link in an email then please choose the address you\'d like to use:</td></tr>';
                                    foreach ($emails as $email) {                            
                                        echo '<tr><td><a href="jobs.php?' . $qs . '&act=email&personEmailId=' . intval($email['personEmailId']) . '">' . htmlspecialchars($email['emailAddress']) . '</td></tr>';                                        
                                    }
                                } else {
                                    echo '<tr><td>We do not have your email on file so we can\'t send you this link via email.</td></tr>';
                                }
                            echo '<tr>';                            
                                echo '<td>Click column heading to sort columns.</td>';
                            echo '</tr>';
                                                        
                            echo '<tr>';                            
                                echo '<td>Click on an Intake Date to set it!</td>';
                            echo '</tr>';
                            
                            echo '<tr>';
                                echo '<td>**Note : this data is up to date whenever you refresh this page.</td>';                            
                            echo '</tr>';                          
                        echo '</table>';
                                                
                        // Then comes a separate, sortable table; one row per workOrder for active jobs (grouped by job), 
                        //  ignore the inactive jobs. We skip any workOrder with a workOrderStatus indicating that it is done. Each row also 
                        //  constitutes a form (including hidden data as well as the overt columns) to call the setintake code. 
                        echo '<table border="0" cellpadding="6" cellspacing="4" id="tablesorter-demo" class="tablesorter">';
                            echo '<thead>';
                                echo '<tr>';
                                    echo '<th bgcolor="8ccdff">Job#</th>';
                                    echo '<th bgcolor="8ccdff">Name</th>';
                                    echo '<th bgcolor="8ccdff">Company</th>';
                                    echo '<th bgcolor="8ccdff">Design Pro(s)</th>';
                                    echo '<th bgcolor="8ccdff">Workorder Description</th>';
                                    echo '<th bgcolor="8ccdff">Intake</th>';
                                echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                                $lastJobId = 0; // >>>00007 we maintain this variable, but we no longer use it.
                                
                                foreach ($activejobs as $job) {
                                    $j = new Job($job['jobId']);
                                    $c = new Company($job['companyId']);
                                    $wos = $j->getWorkOrders();
                                    foreach ($wos as $wo) {
                                        /* BEGIN REPLACED 2020-06-12 JM 
                                        $status = $wo->getWorkOrderStatusId();
                                        if ($status != STATUS_WORKORDER_DONE) {
                                        // END REPLACED 2020-06-12 JM
                                        */
                                        // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                                        if ($wo->isDone()) {
                                        // END REPLACEMENT 2020-06-12 JM
                                            echo '<tr>';
                                                echo '<form name="form_intake_' . intval($wo->getWorkOrderId()) . '" id="form_intake_' . intval($wo->getWorkOrderId()) . '">';
                                                    echo '<input type="hidden" name="act" value="setintake">';
                                                    echo '<input type="hidden" name="workOrderId" value="' . intval($wo->getWorkOrderId()) . '">';
                                                    
                                                    foreach ($_REQUEST as $key => $value) {                                                
                                                        if (strlen($qs)) {
                                                            // Not the first
                                                            $qs .= '&';
                                                        }
                                                        echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
                                                    }
                                                
                                                    // "Job#"
                                                    echo '<td valign="top">' . $j->getNumber() . '</td>';
                                                    
                                                    // "Name"
                                                    echo '<td valign="top">' . $j->getName() . '</td>';
                                                    
                                                    // "Company"
                                                    echo '<td valign="top">' . $c->getCompanyName() . '</td>';
                                                    
                                                    // "Design Pro(s)" (one to a line, BR element in between)
                                                    echo '<td valign="top">';                                                
                                                        $pros = $wo->getTeamPosition(TEAM_POS_ID_DESIGN_PRO);
                                                        foreach ($pros as $pkey => $pro) {
                                                            if ($pkey){
                                                                echo '<br>';
                                                            }                                            
                                                            $cp = new CompanyPerson($pro['companyPersonId']);
                                                            echo $cp->getPerson()->getFormattedName(1);
                                                        }
                                                    echo '</td>';            			            
                                            
                                                    // "Workorder Description"
                                                    echo '<td valign="top">' . $wo->getDescription(). '</td>';
                                                    
                                                    // "Intake" (blank if none yet, can use datepicker to change)
                                                    // Selecting in the datepicker triggers a handler that calls dateForm with the ID of that datepicker
                                                    echo '<td valign="top">';                                        
                                                        $dd = '';                                        
                                                        if ($wo->getIntakeDate() != '0000-00-00 00:00:00') {
                                                            $dd = date("Y-m-d", strtotime($wo->getIntakeDate()));
                                                        }                                            
                                                        echo '<input type="text" name="intake_' . intval($wo->getWorkOrderId()) . '" class="datepicker" id="intake_' . intval($wo->getWorkOrderId()) . '" value="' . htmlspecialchars($dd) . '">';
                                                    echo '</td>';
                                                echo '</form>';
                                            echo '</tr>';
                                        } // END if ($wo->isDone())
                                    }
                                    
                                    $lastJobId = $j->getJobId();
                                } // END foreach ($activejobs...
                            echo '</tbody>';
                        echo '</table>';
                        echo '</center>';
                    }
                } // END if ($row['privateTypeId'] == PRIVTYPE_JOBS)
            } // END if ($row)
            // >>> else: >>>00002 this looks like a possible hacking attempt, certainly should log 
        }
    } // END if (isPrivateSigned...
    ?>
    
    <script>
    $(function() {
        // INPUT dpid: a particular datepicker. We'll self-submit the entire form/row containing that datepicker
        //  as a means to set intake date.
        var dateForm = function(dpid) {
            window.location.href = '/c/jobs.php?' + $('#form_' + dpid).serialize();
        }
    
        // Selecting in any datepicker triggers a handler that calls dateForm with the ID of that datepicker
        $( ".datepicker").datepicker({
            onSelect: function() {
                dateForm(this.id);
            }
        });
    });
    </script>

</body>
</html>