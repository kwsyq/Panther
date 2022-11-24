<?php
/*  _admin/employee/addemployee.php

    EXECUTIVE SUMMARY: Page to add an employee; this should already be a known person on the system, 
    and should already have a row in DB table CompanyPerson associating them with the current customer company.
    The idea is to add a CustomerPerson row, which makes them known to the system as an employee
    
    >>>00002 needs a more robust method to report/log errors.
    
    Initially, if no input, offers a choice among all users (persons with userNames) who are not employees.
    
    As usual, process is implemented as a series of self-submissions of forms.
    
    OPTIONAL INPUT $_REQUEST['act'] takes values "gatherinformation" and "addemployee". Either of these can take further inputs:
        * $_REQUEST['personId']: personId from DB table Person. Mandatory for "gatherinformation" and "addemployee".
        * $_REQUEST['hireDate']: in YYYY-mm-dd form; defaults to current date
        * $_REQUEST['initials']: optional for "gatherinformation", required for "addemployee". We want to set a unique set of initials
          to identify each employee for a given customer. In the DB, these are called "legacyInitials", but there is nothing "legacy" about them.
*/
?>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
	<style>
	table, th, td {border: 1px solid black};
	</style>
</head>
<body>
    <h2>Add Employee</h2>

<?php
require_once '../../inc/config.php';

$error = '';
function addError($err) {
    global $error;
    if ($error) {
        $error .= '; ';
    } else {
        $error .= 'addemployee: ';
    }
    $error .= $err;
}

$db = DB::getInstance();

if ($act == "addemployee" || $act == "gatherinformation") {
    $personId = array_key_exists('personId', $_REQUEST) ? $_REQUEST['personId'] : 0;
    $hireDate = trim(array_key_exists('hireDate', $_REQUEST) ? $_REQUEST['hireDate'] : date('Y-m-d'));
    $initials = trim(array_key_exists('initials', $_REQUEST) ? $_REQUEST['initials'] : '');

    if ( !intval($personId) ) {
        addError('Missing or invalid $_REQUEST[\'personId\']');
    } else {
        $person = new Person($personId, $user);
        if (!$person) {
            addError('Cannot construct Person object from input $_REQUEST[\'personId\'] = ' . $personId);
        } else {
            if ($person->getCustomerId() != $customer->getCustomerId()) {
                addError('$_REQUEST[\'personId\'] = ' . $personId . ': person not associated with current customer.');
            }
        }
    }
    // >>>00016 we could add more validation for hireDate.
}

if ($act == "addemployee") {
    $ok = !$error;
    if ($ok) {
        if (!$initials) {
            // Need initials
            $act = "gatherinformation";
            // Fall through to gatherinformation
            $ok = false;
        }
    }
    if ($ok) {
        $existingMatch = $customer->getCustomerPersonFromInitials($initials);
        if ($existingMatch) {
            // Need to resolve conflict
            $act = "gatherinformation";
            // Fall through to gatherinformation
            $ok = false;
        }
    }
    if ($ok) {
        // This is where we actually add an employee. 
        // >>>00006 Quite likely should be a method of the Customer class.
        $query = 'INSERT INTO customerPerson (';
        $query .=     'customerId, ';
        $query .=     'personId, ';
        $query .=     'legacyInitials, ';
        $query .=     'hireDate, ';
        $query .=     'terminationDate, ';
        /* BEGIN REMOVED 2020-03-02 JM, should have been removed earlier, these are no longer in DB for version 2020-2.
        $query .=     'dayHours, dayOT, weekOT, '; // vestigial, crons/payweekinfo.php makes these columns irrelevant. 
        $query .=     'vacationTime, '; // vestigial, now handled in table vacationTime.
        */
        $query .=     'employeeId, '; // vestigial
        $query .=     'workerId'; // vestigial
        $query .= ') VALUES (';
        $query .=     $customer->getCustomerId() . ', ';
        $query .=     $personId . ', ';
        $query .=     "'" . $db->escape_string($initials) . "', ";
        $query .=     "'" . $hireDate . "', ";
        $query .=     "'9999-01-01', ";
        /* BEGIN REMOVED 2020-03-02 JM, should have been removed earlier, these are no longer in DB for version 2020-2.
        $query .=     '0, 0, 0, ';
        $query .=     "0, "; // vacationTime (handled elsewhere)
        */
        $query .=     "0, ";
        $query .=     "NULL";
        $query .= ')';
        
        $result = $db->query($query);
        if (!$result) {
            addError('DB query failed: ' . $query);
            syslog(LOG_ERROR, $error);
            $ok = false;
        }
        // BEGIN added 2020-03-02 JM
        if ( $ok ) {
            // Germane for both period & week:
            $customerPersonId = $db->insert_id; // $customerPersonId and its uses added 2020-03-02 JM
            
            $now = new DateTime('now');
            $year = $now->format('Y');
            $month = $now->format('n');
            $day = $now->format('j');
            $lastMidnight = new DateTime;
            $lastMidnight->setDate($year, $month, $day);
            
            $hireDateStart = new DateTime($hireDate);
            // PERIOD
            // If this adds an employee for the current pay period (or earlier), then we want to make appropriate insertions of rows in customerPersonPayPeriodInfo
            // because the crons/payperiodinfo.php won't catch this.
            
            $hireDateYear = $hireDateStart->format('Y');
            $hireDateMonth = $hireDateStart->format('n');
            $hireDateDay = $hireDateStart->format('j');
            
            $payPeriodStart = new DateTime(); 
            
            if ($hireDateDay <= 15) {
                $payPeriodStart->setDate($hireDateYear, $hireDateMonth, 1);                
            } else {
                $payPeriodStart->setDate($hireDateYear, $hireDateMonth, 16);
            }           
            
            while ($payPeriodStart < $lastMidnight) {
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo (".
                "customerPersonId, ".
                "periodBegin, ".
                "payPeriod, ".
                "rate, ".
                "salaryHours, ".
                "ira, ".
                "copay, ".
                "salaryAmount".
                ") VALUES (";
                $query .= " " . intval($customerPersonId)  . " ";
                $query .= ", '" . $db->real_escape_string($payPeriodStart->format('Y-m-d'))  . "' ";
                $query .= ", " . intval(PAYPERIOD_BIMONTHLY_1_16)  . " ";
                $query .= ", " . intval(0)  . " ";
                $query .= ", " . intval(0)  . " ";
                $query .= ", " .  $db->real_escape_string(0)  . " ";
                $query .= " ," .  $db->real_escape_string(0)  . " ";
                $query .= " ," . intval(0)  . ") ";
                
                $result = $db->query($query);
                if ( ! $result) {
                    addError('DB query failed: ' . $query);
                    syslog(LOG_ERR, $error);
                    $ok = false;
                    break;
                }
                
                if ($payPeriodStart->format('j') == 1) {
                    $payPeriodStart->setDate($payPeriodStart->format('Y'), $payPeriodStart->format('n'), 16);
                } else if ($payPeriodStart->format('n') == 12) {
                    // New Year
                    $payPeriodStart->setDate($payPeriodStart->format('Y') + 1, 1, 1);                    
                } else {
                    $payPeriodStart->setDate($payPeriodStart->format('Y'), $payPeriodStart->format('n') + 1, 1);
                }
            } // END while ($payPeriodStart < $lastMidnight)
        }
        
        if ( $ok ) {
            // WEEK
            // If this adds an employee for the current week (or earlier), then we want to make appropriate insertions of rows in customerPersonPayWeekInfo
            // because crons/payweekinfo.php won't catch this.           
            $payWeekStart = clone $hireDateStart;
            
            $adjust = $payWeekStart->format('N') - 1;  // Monday is 1, Tuesday is 2, etc; Sunday is 7. We want to adjust to the prior Monday (or same day it
                                              // it already is a Monday)
            if ($adjust) {
                $adjust_string = '-' . $adjust . ' days'; 
                $payWeekStart->modify($adjust_string);
            }            
            while ($payWeekStart < $lastMidnight) {
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo (".
                "customerPersonId, ".
                "periodBegin, ".
                "dayHours, ".
                "dayOT, ".
                "weekOT, ".
                "workWeek".
                ") VALUES (";
                $query .= " " . intval($customerPersonId)  . " ";
                $query .= ", '" . $db->real_escape_string($payWeekStart->format('Y-m-d'))  . "' ";
                $query .= ", " . intval(8)  . " ";
                $query .= ", " . intval(10)  . " ";
                $query .= ", " . intval(40)  . " ";
                $query .= ", " . intval(WORKWEEK_MON_SUN)  . ") ";
                
                $result = $db->query($query);
                if ( ! $result) {
                    addError('DB query failed: ' . $query);
                    syslog(LOG_ERR, $error);
                    $ok = false;
                    break;
                }
                
                $payWeekStart->modify('+7 days');
            } // END while ($payWeekStart < $lastMidnight)
        }
        // END added 2020-03-02 JM
    }
    if ($ok) {
        echo '<p><b>'.$person->getFormattedName() . ' (' . $person->getUserName() .') successfully added as employee.</b></p>';
        
        // >>>00002 the following should be replaced by whatever we do for proper logging
        syslog(LOG_INFO, 'For customer '. $customer->getCustomerName(). ', ' . $user->getUserName(). ' added ' . 
                $person->getFirstName() . ' ' . $person->getLastName() . ' (' . $person->getUserName() .') as employee.' .
                ' $customerPersonId=' . $customerPersonId);
    }
}

if ($error) {
    // drop through
} else if ($act == "gatherinformation") {
    // Gather information about one person to add them.
    $personId = array_key_exists('personId', $_REQUEST) ? $_REQUEST['personId'] : 0;
    
    $initials = trim(array_key_exists('initials', $_REQUEST) ? $_REQUEST['initials'] : '');
    echo '<p>Adding ' . $person->getFormattedName() . ' (' . $person->getUserName() .') as employee.</p>';
    
    // Form structured by a table
    echo '<form action="addemployee.php" target="_self" method="post">' . "\n";
    echo '    <input type="hidden" name="personId" value="' . $personId . '" />' . "\n";
    echo '    <input type="hidden" name="act" value="addemployee" />' . "\n";
    echo '    <table>' . "\n";
    echo '        <tbody>' . "\n";
    echo '           <tr>' . "\n";
    echo '               <th align = "right">Name: </th>' . "\n";
    echo '               <td>'. $person->getFormattedName() .'</td>' . "\n";
    echo '           </tr>' . "\n";
    echo '           <tr>' . "\n";
    echo '               <th align = "right">Username: </th>' . "\n";
    echo '               <td>'. $person->getUserName() .'</td>' . "\n";
    echo '           </tr>' . "\n";
    // "Legacy initials" (NOTE that there is nothing "legacy" about them)
    echo '           <tr>' . "\n";
    echo '               <th align = "right">Initials: </th>' . "\n";
    echo '               <td>';
        // Get a tentative value
        if (!$initials) {
            $initials = trim(substr($person->getFirstName(), 0, 1)) . 
                        trim(substr($person->getMiddleName(), 0, 1)) . 
                        trim(substr($person->getLastName(), 0, 1));
        }
        if  ($initials) {
            $existingMatch = $customer->getCustomerPersonFromInitials($initials);
            if ($existingMatch) {
                // Conflict
                echo 'There is already an employee with initials ' . $initials .', ';
                echo 'use something else.<br />';
            }
            echo '<input type="text" name="initials" value="' . $initials . '"/>';
        } else {
            // very unlikely ever to occur, but let's cover the case
            echo 'We require initials for each employee.<br />';
            echo '<input type="text" name="initials" value=""/>';
        }
    echo '               </td>' . "\n";
    echo '           </tr>' . "\n";
    echo '           <tr>' . "\n";
    echo '               <th align = "right">Hire date: </th>' . "\n";
    echo '               <td><input type="date" name="hireDate" value="' . $hireDate . '"/></td>' . "\n";
    echo '           </tr>' . "\n";
    echo '           <tr>' . "\n";
    echo '               <td></td>' . "\n";
    echo '               <td><input type="submit" value="Add"/></td>' . "\n";
    echo '           </tr>' . "\n";
    echo '        </tbody>' . "\n";
    echo '    </table>' . "\n";
    echo '</form>' . "\n";
} else {
    // Normal display
    // This includes display after a successful insert
    $candidates = Array(); // Array of associative arrays; each associative array provides username & personId for a person.
    // Get username & personId of all possible companyPersons who are NOT employees
    $query = "SELECT * from (
                  SELECT person.username, person.personId FROM companyPerson
                  JOIN person ON person.personId = companyPerson.personId
                  WHERE companyPerson.companyId=1 
              ) AS A 
              WHERE NOT EXISTS (
                  SELECT person.username FROM customerPerson 
                  JOIN person ON person.personId = customerPerson.personId
                  WHERE customerPerson.customerId=1
                  AND A.username = person.username
            )";
    $result = $db->query($query);
    if ( $result ) {
        while ($row = $result->fetch_assoc()) {
            $candidates[] = $row; 
        }    
    } else {
        addError('DB query failed: ' . $query);
    }
    
    ?>
    <p>This page allows you to add an employee for customer <?php echo CUSTOMER_NAME; ?>.</p>
    <p>Click on the username of the person you want to add.</p>
    <?php
    foreach ($candidates as $candidate) {
        // One self-submitting for each candidate
        // Script below makes clicking the username trigger submission. 
        echo '<div><form action="addemployee.php" target="_self" method="post">' . "\n";
        echo '<input type="hidden" name="act" value="gatherinformation" />' . "\n";
        echo '<input type="hidden" name="personId" value="' . $candidate['personId'] .'" />' . "\n";
        echo '<span class="submit-this-user">' . $candidate['username'] . '</span>' . "\n";
        echo '</form></div>' . "\n";
    }
}
?>
<script>
$('.submit-this-user').click(function(){
    let $this = $(this);
    $this.parent('form').submit();
});
</script>
<?php
if ($error) {
    echo '<div>';
    echo $error;
    echo '</div>';
}
?>
</body>
</html>
