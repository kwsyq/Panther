<?php
/*  _admin/employee/employee.php

    EXECUTIVE SUMMARY: PAGE, work in progress as of 2019-07.
    Currently, the only capability for a current employee is to indicate the termination date of an employee.
    When Martin left, he left a lot of vestigial code here. That has been removed, but someone may want to 
    look at history in SVN to see if they think anything would be useful. Most of it seemed to relate to
    vestigial columns in database table.
    
    INPUT $_REQUEST['personId']: primary key into DB table Person, or "all" or 0 (or simply missing) for all employees for this customer. 
    Any value other than "all" or 0 is tested
    to see whether there is a companyPerson relation tying it to the current customer; if so:
        * if there is also a customerPerson relation to the current customer, 
          then this is an employee, and we should offer functionality accordingly
        * if there is NO customerPerson relation to the current customer,
          then this is NOT an employee. We navigate to a different page to offer only the ability to make this person
          an employee. >>>00006 Currently (2019-07-09) requires that there is at least a companyPerson row for the current person & customer company.
    INPUT $_REQUEST['separateCurrent']: Boolean ('true' or 'false'). Only meaningful if $_REQUEST['personId'] is NOT an employee  
          so we are showing multiple employees; DEFAULT true => show current employees before all others.  
*/

include '../../inc/config.php';

$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$separateCurrent = isset($_REQUEST['separateCurrent'])  ? intval($_REQUEST['separateCurrent'] == 'true') : true;

?>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
</head>
<body bgcolor="#ffffff">
<?php
    $person = null;
    if ($personId == 'ALL') {
        $personId = 0;
    }    
    if ($personId) {
        $person = new Person($personId, $user);
        if (!$person || !$person->getPersonId()) {
            echo "<p><b>$personId is not a valid personId. Will display all employees.</b></p>\n";
            $person = null;
            $personId = 0;
        }
    }
    if ($person) {
        $companyPersons = $person->getCompanyPersons(); // array of CompanyPerson objects, in no particular order,
                                                        // one for each company this person is associated with. 
        $matchesCurrentCompany = false;
        foreach($companyPersons as $companyPerson) {
            if ($companyPerson->getCompanyId() == $customer->getCompanyId()) {
                $matchesCurrentCompany = true;
                break;
            }
        }
        if (!$matchesCurrentCompany) {
            // >>>00006 perhaps should offer to create a row in companyPerson for this person and current customer
            echo "<p><b>$personId ({$person->getFormattedName()}) is not associated with current customer. Will display all employees.</b></p>\n";
            $person = null;
            $personId = 0;
        }        
    }
    if ($person) {
        $user = new User($personId, $customer);
        $isEmployee = $user->isEmployee();
        $user = null; // don't need it, let's not leave it lying around
    }
    if ($person && !$isEmployee) {
        // Not an employee so all we can really do is offer to make them one.
        echo "<script> window.location.href = 'addemployee.php?personId=$personId'; </script>\n";
        die();
    }
    
    // At this point we should have one of two situations:
    // 1) $personId and $person are set, truth-y, and refer to a (current or past) employee of the customer
    // 2) $personId==0, $person==nul. We interpret that as "all employees".
    
    if ($personId) {
        echo "<h2>Employee: ". $person->getFormattedName() . ' (' . $person->getUserName() . ")</h2>\n";
    } else {
        echo "<h2>All employees</h2>\n";
    }
    
    function validateDate($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }    
    
    // PHP version
    function formatDate($string) {        
        if (validateDate($string,"Y-m-d")) {
            return date("M j, Y", strtotime($string));
        }
        return '';        
    }
    
    // INPUT $hire, $terminate:
    //  Both dates should be in "Y-m-d" form; for current employees, $terminate will be a date in the very distant future.
    // RETURN: 'current' if this is a current employee (>>>00006 though it doesn't check that $hire is in the past, what if we
    //  set a future date?); 'former' if this is a former employee; 'unknown' if either date is not set, or not formatted appropriately.
    function employeeStatus($hire, $terminate) {        
        if (validateDate($hire,"Y-m-d") && validateDate($terminate,"Y-m-d")) {    
            if (strtotime($terminate) > strtotime('today')) {
                return 'current';	
            } else {
                return 'former';
            }            
        }        
        return 'unknown';
    }

    // INPUT $hire, $terminate:
    //  Both dates should be in "Y-m-d" form; for current employees, $terminate will be a date in the very distant future.
    // RETURN: 'green' if this is a current employee (>>>00006 though it doesn't check that $hire is in the past, what if we
    //  set a future date?); 'red' if this is a former employee; 'black' if either date is not set, or not formatted appropriately.
    function dateColor($hire, $terminate) {        
        if (validateDate($hire,"Y-m-d") && validateDate($terminate,"Y-m-d")) {    
            if (strtotime($terminate) > strtotime('today')) {
                return 'green';	
            } else {
                return 'red';
            }            
        }        
        return 'black';
    }

    if (!$personId) {
        echo '<input type="checkbox" id="separate-current" '. ($separateCurrent ? ' checked' : '') .
             '/>&nbsp;<label for="separate-current">Current employees first</label><br />'."\n";
        ?>
        <script>
        $('#separate-current').change(function() {
            window.location.href = 'employee.php?separateCurrent=' + ($('#separate-current').is(':checked') ? 'true' : 'false');
        });
        </script>
        <?php
    }
    echo 'Double-click any date to edit it.<br /><br />'."\n";
    
    echo '<table border="1" cellpadding="3" cellspacing="2">'."\n";
    echo '<tr>'."\n";
        echo '<th>Name</th>'."\n";
        echo '<th>Initials</th>'."\n";
        echo '<th>Hire Date</th>'."\n";
        echo '<th>Term Date</th>'."\n";
        /* We may want to re-introduce these, but they no longer draw from CustomerPerson, they draw from other tables  
        echo '<th>Day Hours</th>'."\n";
        echo '<th>Day OT</th>'."\n";
        echo '<th>Week OT</th>'."\n";
        echo '<th>Rate</th>'."\n";
        echo '<th>Vacation</th>'."\n";
        echo '<th>Pay Period</th>'."\n";
        echo '<th>Work Week</th>'."\n";
        */
    echo '</tr>'."\n";
    
    // INPUT $user is an "enhanced" version of the User object, see Customer::getEmployees.
    // INPUT $employeeStatus: if present, limit to this employee status. If non-blank must be one of 'current', 'former', 'unknown'
    // Rewritten 2020-06-10 JM to get rid of User::getCustomerPersonDataKludge, which we do not need now that we have a CustomerPerson class
    function showUser($user, $employeeStatus='') {
        $customerPersonId = $user->getCustomerPersonId();
        $customerPerson = new CustomerPerson($customerPersonId);
        $hireDate = $customerPerson->getHireDate();
        $terminationDate = $customerPerson->getTerminationDate();
        if (isset($employeeStatus) && $employeeStatus && 
            employeeStatus($hireDate, $terminationDate) != $employeeStatus) 
        {
            return;    
        }

        $name = $user->getLastName() . ',&nbsp;' . $user->getFirstName();
        echo '<tr data-customerperson="' . $customerPersonId .'" data-name="' . $name. '">';
        echo '<td><font color="' . dateColor($hireDate, $terminationDate) . '">' . $name . '</font></td>';
        echo '<td>' . $customerPerson->getLegacyInitials()  . '</td>';
        
        // Hire date
        // Code to edit this is in click-handler below
        $explicitNoHire = $hireDate == NO_BEGINNING;
        $implicitNoHire = !$hireDate; // >>>00006 maybe want to do some validation here.
        echo '<td class="hireDate">';
            if ($explicitNoHire) {
                echo '<span style="color:gray">No date</span>';
            } else if ($implicitNoHire) {
                echo '<span style="color:red">Missing</span>';
            } else {
                echo formatDate($customerPerson->getHireDate());
            }
        echo '</td>';
        
        // Term date
        // Code to edit this is in click-handler below
        $explicitNoTermination = $terminationDate == NO_TERMINATION;
        $implicitNoTermination = !$terminationDate; // >>>00006 maybe want to do some validation here.
        // The following is a weird case, but we've seen it.
        $terminatesAtZero = $terminationDate == NO_BEGINNING; 
        echo '<td class="termDate">';
            if ($explicitNoTermination) {
                echo '<span style="color:gray">No date</span>';
            } else if ($implicitNoTermination) {
                echo '<span style="color:red">Missing</span>';
            } else if ($terminatesAtZero) {
                echo '<span style="color:red">0000-00-00</span>';
            } else {
                echo formatDate($customerPerson->getTerminationDate());
            }
        echo '</td>';
        
        /* We may want to re-introduce these, but they no longer draw from CustomerPerson, they draw from other tables
        echo '<td>' . $customerPersonData['dayHours']  . '</td>';
        echo '<td><a  class="links" personId="' . intval($user->getUserId()) . '" href="#" id="dayot_' . intval($user->getUserId()) . '_' . intval($customer->getCustomerId()) . '">' . $customerPersonData['dayOT']  . '</a></td>';

        echo '<td  id="dayot_' . intval($user->getUserId()) . '_' . intval($customer->getCustomerId()) . '"><select personId="' . intval($user->getUserId()) . '" onChange="setDayOT(this)" name="" >';
            for ($i = 0; $i < 100; ++$i){
                $selected = ($customerPersonData['dayOT'] == $i) ? ' selected ' : '';
                echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
            }
        echo '</select></td>';            
        echo '<td  id="weekot_' . intval($user->getUserId()) . '_' . intval($customer->getCustomerId()) . '"><select personId="' . intval($user->getUserId()) . '" onChange="setWeekOT(this)" name="" >';
        for ($i = 0; $i < 100; ++$i){
            $selected = ($customerPersonData['weekOT'] == $i) ? ' selected ' : '';
            echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
        }
        echo '</select></td>';
        
        echo '<td>' . $customerPersonData['weekOT']  . '</td>';
        echo '<td>' . $customerPersonData['rate']  . '</td>';
        echo '<td>' . $customerPersonData['vacationTime']  . '</td>';
        echo '<td>' . $payperiod[$customerPersonData['payPeriod']]  . '</td>';
        echo '<td>' . $workweek[$customerPersonData['workWeek']]  . '</td>';
        */
        
        echo '</tr>'. "\n";
    }
    
    $employees = $customer->getEmployees();
    
    if ($separateCurrent && !$personId) {
        foreach ($employees as $user) {
            showUser($user, 'current');
        }
        foreach ($employees as $user) {
            showUser($user, 'unknown');
        }
        foreach ($employees as $user) {
            showUser($user, 'former');
        }
    } else {
        foreach ($employees as $user) {
            if ($personId && $user->getUserId() != $personId) {
                // We only care about one person, and this isn't it.
                continue;
            }
            showUser($user);
        }
    }
    echo '</table>';
?>
<script>
function todayAsYMD () {
    var d = new Date(),
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}

$('.termDate').dblclick(function() {
    let $this = $(this);
    let $row = $this.closest('tr');
    let customerPersonId = $row.data('customerperson');
    let name = $row.data('name');

    $('<div id="term-date-dialog">' +
           '<div id="term-date-dialog-intro">Current termination date for user ' + name + ': ' + $this.text() + '.</div>' +
           '<label for="new-term-date">New date:&nbsp;</label>' +
           '<input id="new-term-date" type="date" />&nbsp;' + 
           '<button id="today-term-date">Today</button>' +
           '<button id="no-term-date">Clear termination date</button>' +
      '</div>').dialog({
            autoOpen: true,
            title: 'Set termination date',
            modal: true,
            resizable: true,
            closeOnEscape: true,
            width: 'auto',
            height: 'auto',
            position: {my:'center top', at:'center top', of:window},
            open: function() {
                $("#no-term-date").click(function() {
                    $("#new-term-date").val("<?php echo NO_TERMINATION; ?>");
                });
                $("#today-term-date").click(function() {
                    $("#new-term-date").val(todayAsYMD());
                });
            },
            close: function() {
                $("#term-date-dialog").remove(); // we always want to kill this and rebuild a new one if needed.
            },
            buttons: {
                "OK": function() {
                    $.ajax({
                        url: '../ajax/setterminationdate.php',
                        data:{ 
                               customerPersonId: customerPersonId,
                               date: $('#new-term-date').val()
                        },
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['status']) {    
                                if (data['status'] == 'success') {
                                    $('#term-date-dialog').dialog('close');
                                    // Reload, simpler than updating any other way, & cheap enough
                                    window.location.reload(true);
                                } else {    
                                    alert(data['error']);
                                }    
                            } else {
                                alert('error no status');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('error');
                        }
                    });
                },
                "Cancel": function() {
                    $("#term-date-dialog").dialog('close');
                }
            }
        });
});
// This is basically a stripped-down version of the termDate case immediately above.
$('.hireDate').dblclick(function() {
    let $this = $(this);
    let $row = $this.closest('tr');
    let customerPersonId = $row.data('customerperson');
    let name = $row.data('name');

    $('<div id="hire-date-dialog">' +
           '<div id="hire-date-dialog-intro">Current hire date for user ' + name + ': ' + $this.text() + '.</div>' +
           '<label for="new-hire-date">New date:&nbsp;</label>' +
           '<input id="new-hire-date" type="date" />&nbsp;' + 
      '</div>').dialog({
            autoOpen: true,
            title: 'Set hire date',
            modal: true,
            resizable: true,
            closeOnEscape: true,
            width: 'auto',
            height: 'auto',
            position: {my:'center top', at:'center top', of:window},
            close: function() {
                $("#hire-date-dialog").remove(); // we always want to kill this and rebuild a new one if needed.
            },
            buttons: {
                "OK": function() {
                    $.ajax({
                        url: '../ajax/sethiredate.php',
                        data:{ 
                               customerPersonId: customerPersonId,
                               date: $('#new-hire-date').val()
                        },
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['status']) {    
                                if (data['status'] == 'success') {
                                    $('#hire-date-dialog').dialog('close');
                                    // Reload, simpler than updating any other way, & cheap enough
                                    window.location.reload(true);
                                } else {    
                                    alert(data['error']);
                                }    
                            } else {
                                alert('error no status');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('error');
                        }
                    });
                },
                "Cancel": function() {
                    $("#hire-date-dialog").dialog('close');
                }
            }
        });
});
</script>

</body>
</html>