<?php
/*  _admin/time/time.php

    EXECUTIVE SUMMARY: Manage some payroll/time-related values for all employees:
        * view or edit how many days back they can edit their time
        * view (but not edit) PTO (vacation/sick) time; the UI previously called this "vacation" or "Vac.", 
          and lower-level code still uses this name.
    The following used to be here, but were removed by Martin some time around 2018:
        * type & amount of IRA
    
    No primary input, because this covers all employees.
*/

include '../../inc/config.php';
?>
<!DOCTYPE html>
<html>
<head>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
</head>
<body>

<?php
$employees = array(); // This will be an array of User objects, with additional data added to each
                      // object for terminationDate, customerPersonId

// For each employee (past or present, though later we will filter out the past employees & display only current employees)
$employeesScratch = getEmployees($customer);
foreach ($employeesScratch as $employee) {
    $e = new User($employee['personId'], $customer);
    $e->terminationDate = $employee['terminationDate'];
    $e->customerPersonId = $employee['customerPersonId'];
    $employees[] = $e;
}
    
// BEGIN TEST CODE JM 2019-05-31
$showWhoIsLoggedIn = false; // Set this true to demo who is logged in at this point
if ($showWhoIsLoggedIn) {
    if (!isset($user)) {
        echo '<p><b>NO $user</b></p>';
    } else {
        echo '<p><b>logged-in $user is '. $user->getFormattedName() .'['. $user->getUserId() .']</b></p>';
    }
}
// END TEST CODE

// Top nav. This is the "EMPLOYEES" page.
echo '<table border="0" cellpadding="0" width="100%">' . "\n";
    echo '<tr>' . "\n";
        echo '<td colspan="3">' . "\n";
            // JM 2019-11-01 fixing http://bt.dev2.ssseng.com/view.php?id=41
            // Get rid of dead link to old PTO calendar; will eventually be revived, but right now it's a link to a dead page.
            //echo '[EMPLOYEES]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;[<a href="pto.php">PTO</a>]&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]';
            // BEGIN REPLACEMENT CODE JM 2019-11-01
            echo '[EMPLOYEES]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;<span style="color:lightgray">[PTO]</span>&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]' . "\n";
            // END REPLACEMENT CODE JM 2019-11-01
        echo '</td>' . "\n";
    echo '</tr>' . "\n";
echo '</table>' . "\n";

echo '<br />' . "\n";

echo "Double-click the days back column to change the value. This is how many days back the person can edit their time. " .
     "It's off by one so always allow one extra day\n"; //>>>00032: obviously should rework UI so it is not off by 1!
echo '<br>(this is how many *work days* back)' . "\n";
echo '<br><br>Double-click PTO Alloc column to allocate PTO.' . "\n";
echo '<br><br>Any PTO allocated for a future effective date shows up after a plus sign ("+").' . "\n";

echo '<br /><br />' . "\n";

echo '<table border="0" cellpadding="3" cellspacing="2" id="edit_table">';
    echo '<tr>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td>Days Back</td>';
        echo '<td>PTO Alloc</td>';
        echo '<td>PTO Used</td>';
        echo '<td>PTO Remain</td>';
    echo '</tr>' . "\n";

    $db = DB::getInstance();

    $current_date = new DateTime();
    foreach ($employees as $employee) {
        $term_date = new DateTime($employee->terminationDate);
        
        if (!($current_date > $term_date)) {        
            echo '<tr>' . "\n";
                // (no header) last name 
                echo '<td class="lastname">' . $employee->getLastName() . '</td>' . "\n";
                // (no header) first name
                echo '<td class="firstname">' . $employee->getFirstName() . '</td>' . "\n";
                // (no header) 'Sheet' linked to sheet.php for this userId
                echo '<td><a href="sheet.php?userId=' . $employee->getUserId() . '">Sheet</a></td>' . "\n";
                // (no header) 'Week Info' linked to info_week.php for this userId >>>00026 JM: NOTE NO info_period. 
                echo '<td><a href="info_week.php?userId=' . $employee->getUserId() . '">Week Info</a></td>' . "\n";
                
                // "Days Back": how many days back user can edit time, selected from DB table customerPerson for this employee
                $daysback = '-';    
                $query = " select daysBack from " . DB__NEW_DATABASE . ".customerPerson " . 
                         " where customerId = " . intval($customer->getCustomerId()) . " and personId = " . $employee->getUserId() . " ";    
                $result = $db->query($query);
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $daysback = $row['daysBack'];
                } // >>>00002 else ignores failure on DB query! Does this throughout file,
                
                echo '<td align="center" class="days_editable" id="days_' . intval($customer->getCustomerId()) . '_' . $employee->getUserId() . '">' . $daysback . '</td>' . "\n";
                
                // "PTO Alloc": Total vacation/sick time ever allocated to this employee, since original hire.
                // Written in hours as decimal, with two digits past the decimal point. 
                // 2019-06-05 JM: got rid of a fudge Martin had in here to show negative allocations as time that
                //  the person took as PTO. That is not the intent: negative allocations are simply adjustments to allocations.
                //
                // Addition 2019-10-09: if time has been allocated that is not yet effective, it will show as a separate number after a plus sign.
                //   E.g. 120.00 + 40.00 means that the 40 is allocated, effective at a future date.
                //
                // The calculations here were rewritten 2019-10-09 JM because of the introduction of the possibility of allocating time for a future date.
                $remain = 0;
                $currentMinutes = intval($employee->getTotalVacationTime(Array('currentonly'=>true)));
                $futureMinutes = intval($employee->getTotalVacationTime(Array('futureonly'=>true)));
                $usedMinutes = intval($employee->getVacationUsed());
                $total = number_format((float)$currentMinutes/60, 2, '.', '');
                if ($futureMinutes > 0) {
                    $total .= ' + ' . number_format((float)$futureMinutes/60, 2, '.', '');
                } else if ($futureMinutes < 0) {
                    $total .= ' - ' . number_format((float)abs($futureMinutes)/60, 2, '.', '');
                }
                $used = number_format((float)$usedMinutes/60, 2, '.', '');            
                $remain = number_format((float)($currentMinutes - $usedMinutes)/60, 2, '.', '');            
                if ($remain < 0) {
                    $remain = '(' . $remain . ')';
                }
                if ($futureMinutes) {
                    $remain .= ' + ' . number_format((float)$futureMinutes/60, 2, '.', '');
                }
                
                // PTO Alloc: Total vacation/sick time ever allocated to this employee, since original hire. 
                // Written in hours as decimal, with two digits past the decimal point.
                echo '<td align="center" class="pto_editable" id="pto_' . intval($customer->getCustomerId()) . '_' . $employee->getUserId() . '">' . $total .'</td>' . "\n";
                
                // PTO Used: Total vacation time ever used by this employee, since original hire. 
                // Written in hours as decimal, with two digits past the decimal point.
                echo '<td align="center">' . $used . '</td>' . "\n";
                
                // PTO Remain: Vacation balance, written in hours as decimal, with two digits past the decimal point. 
                // If this is negative, it will be parenthesized. E.g. "5.50", "(-4.00)".
                echo '<td align="center">' . $remain . '</td>' . "\n";
            echo '</tr>';
        }
    } // end foreach ($employees...
echo '</table>' . "\n";
?>

<?php /* BEGIN entirely new code JM 2019-05-30 */ ?>
<div id="vacation-alloc-dialog"></div>
<script>    
    $(function () {
        $('#vacation-alloc-dialog').dialog({
            autoOpen: false,
            closeOnEscape: true, // >>>00001 may want to change this, ask Damon
            title: 'Grant PTO',
            modal: true,
            resizable: true,
            // no buttons for now.
            open: function (event) {
                var $this = $(this);
                var customerId = $this.data('customerId')
                var userId = $this.data('userId'); 
                
                // "AJAX loading" icon, then get content for this dialog 
                $this.html('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').
                    dialog({height:45, width:'auto'})
	    	    .load('/_admin/ajax/editvacationtime.php?customerId=' + encodeURIComponent(customerId) + '&userId=' + encodeURIComponent(userId), function() {
	    	        // NOT sure what variable 'this' is at this point, so playing it safe with identifier
		    	    $('#vacation-alloc-dialog').dialog({height:'auto', width:'auto'});
                });
            }
        });
            
        <?php /* Bring up dialog to view more detail on PTO for employee and to allocate more time (or take away time). */ 
        ?>
        $("#edit_table td.pto_editable").dblclick(function () {
            var $this = $(this);
            var id = $this.attr('id');
            if (id.substr(0, 4) == 'pto_') {
                id = id.substr(4); // clear 'days_' off of the id to get it in the form ../ajax/daysback.php wants it.
                var parts = id.split('_');
                if (parts.length == 2) {
                    var customerId = parts[0];
                    var userId = parts[1];                    
                    $('#vacation-alloc-dialog').data({
                        customerId : customerId,
                        userId : userId
                    }).dialog('open');
                }
            }
        }); // END $("#edit_table td.pto_editable").dblclick
    });
</script>
<?php /* END entirely new code JM 2019-05-30 */ ?>

<script>
    $(function () {
        <?php /* Set days back this person can edit their time.
                 Doesn't use an "AJAX working" icon.
                 Synchronous POST to ../ajax/daysback.php passing new number >>>00002 no apparent validity check.
                 Updates text (number) on success.
                 Alerts on failure.
        */ 
        ?>
        $("#edit_table td.days_editable").dblclick(function () {
            var $this = $(this);
            
            // The following relies on knowing how the table is structured.
            var $tableRow = $this.parent('tr');
            var formattedName = $tableRow.find('.firstname').text() + ' ' + $tableRow.find('.lastname').text();  
            
            var OriginalContent = $this.text();
            var inputNewText = prompt("Enter new 'days back' content for " + formattedName + ":", OriginalContent);    
            if (inputNewText!=null) {
                var id = $this.attr('id');
                if (id.substr(0, 5) == 'days_') {
                    id = id.substr(5); // clear 'days_' off of the id to get it in the form ../ajax/daysback.php wants it.   
                    $.ajax({
                        url: '../ajax/daysback.php',
                        data:{ id: id, value: inputNewText },
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['status']) {    
                                if (data['status'] == 'success') {    
                                    $(this).text(inputNewText); 
                                } else {    
                                    alert('error not success');    
                                }    
                            } else {
                                alert('error no status');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('error');
                        }
                    });
                }
            }
        }); // END $("#edit_table td.days_editable").dblclick    
    });

</script>
</body>
</html>