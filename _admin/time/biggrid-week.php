<?php
/*  _admin/time/biggrid-week.php

    EXECUTIVE SUMMARY: PAGE to look at time data for all employees for a given week.
*/

//JM: apparently Martin intended this to be replaced eventually by biggrid2.php, which 
//  I believe he left as work in progress
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//header ("Location: biggrid2.php");
// END COMMENTED OUT BY MARTIN BEFORE 2019

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
<style>
tr.employee:nth-child(even) td {background-color:#eeeeff;}
</style>

<?php
$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';  // Martin comment: the date the week starts ... i.e. 2016-10-20
$time = new Time(0,$start);

// Top nav. This is the "BIG GRID-WEEK" page.
echo '<table border="0" cellpadding="0" width="100%">';
    echo '<tr>'. "\n";
        echo '<td colspan="3">';
            // JM 2019-11-01 fixing http://bt.dev2.ssseng.com/view.php?id=41
            // Get rid of dead link to old PTO calendar; will eventually be revived, but right now it's a link to a dead page.
            //echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;[<a href="pto.php">PTO</a>]&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[BIG GRID-WEEK]';
            // BEGIN REPLACEMENT CODE JM 2019-11-01
            echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;<span style="color:lightgray">[PTO]</span>&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[BIG GRID-WEEK]';
            // END REPLACEMENT CODE JM 2019-11-01
        echo '</td>'. "\n";
    echo '</tr>'. "\n";
echo '</table>'. "\n";

echo '<p><h3>BIG GRID PAY WEEK</h3>';

/* show, in three separate columns:
    * '« prev «', with the date one week earlier linked to navigate and replace the current page by the 
        equivalent page with the start date 7 days earlier.
    * 'Period: m-d thru m-d-Y', wher the first m-d is start date of this week and the m-d-Y is end date of this week (no link).
    * '» next one week later »', with date one week later linked to navigate and replace the current page by the 
        equivalent page with the start date 7 days later
*/
echo '<table border="0" cellpadding="0" cellspacing="0" width="800">'. "\n";
    echo '<tr><td colspan="3">&nbsp;</td></tr>'. "\n";
    echo '<tr>'. "\n";
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        //echo '<td style="text-align:left">&#171;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->previous . '">' . $time->previous . '</a>&#171;</td>';
        // END COMMENTED OUT BY MARTIN BEFORE 2019
        
        echo '<td style="text-align:left">&#171;<a href="biggrid-week.php?start=' . $time->previous . '">prev</a>&#171;</td>'. "\n";

        $e = date('Y-m-d', strtotime('-1 day', strtotime($time->next)));

        echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">' .
            'Period: ' . date("m-d", strtotime($time->begin)) . ' thru ' . date("m-d", strtotime($e)) . '-' . date("Y", strtotime($time->begin)) . '</span></td>'. "\n";

        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        //echo '<td style="text-align:right">&#187;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->next . '">' . $time->next . '</a>&#187;</td>';
        // END COMMENTED OUT BY MARTIN BEFORE 2019

        echo '<td style="text-align:right">&#187;<a href="biggrid-week.php?start=' . $time->next . '">next</a>&#187;</td>'. "\n";

    echo '</tr>'. "\n";
echo '</table>'. "\n";

$db = DB::getInstance();

// Get some basic data for all current employees of current customer (as of 2019-05, always SSS). 
$employees = $customer->getEmployees(1);

echo '<table border="0" cellpadding="3" cellspacing="1" id="edit_table">';
    echo '<tr>';
        echo '<th>Employee</th>';
        echo '<th>Day Hrs</th>';
        echo '<th>Day OT</th>';
        echo '<th>Week OT</th>';
    echo '</tr>'. "\n";

    foreach ($employees as $employee) {
        echo '<tr class="employee">'. "\n";
            // "Employee"
            echo '<td class="formatted-name">' . $employee->getFormattedName(1) . '</td>'. "\n";
            
            // Now digress to a bunch of calculation
    
            $query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo  ";
            $query .= " where customerPersonId = (select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson where customerId = " . intval($customer->getCustomerId()) . " and personId = " . $employee->getUserId() . ") ";
            $query .= " and periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "' ";
            $query .= " limit 1 ";

            $cppwi = false; 
    
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $cppwi = $row;
                    }
                }
            } // >>>00002 else ignores failure on DB query! Does this throughout file, haven't noted each instance.

            $dayHoursDisplay = '&nbsp;';
            $dayOTDisplay = '&nbsp;';
            $weekOTDisplay = '&nbsp;'; 
    
            $customerPersonPayWeekInfoId = 0;
            $customerPersonId = 0;
            if ($cppwi) {
                $customerPersonId = $cppwi['customerPersonId'];        
                
                $customerPersonPayWeekInfoId = $cppwi['customerPersonPayWeekInfoId'];
                $dayHours = $cppwi['dayHours'];
                $dayOT = $cppwi['dayOT'];
                $weekOT = $cppwi['weekOT'];

                if (is_numeric($dayHours)) {
                    $dayHoursDisplay = intval($dayHours);
                }
                if (is_numeric($dayOT)) {
                    $dayOTDisplay = intval($dayOT);
                }
                if (is_numeric($weekOT)) {
                    $weekOTDisplay = intval($weekOT);
                }
            }

            //" Day Hrs"
            echo '<td align="center" class="dheditable" data-ids="' . intval($customerPersonId) . '_' . $customerPersonPayWeekInfoId . '">'. "\n";
            echo $dayHoursDisplay;
            echo '</td>';
            
            //" Day OT"
            echo '<td align="center" class="doteditable" data-ids="' . intval($customerPersonId) . '_' . $customerPersonPayWeekInfoId . '">'. "\n";
            echo $dayOTDisplay;
            echo '</td>';
            
            //" Week OT"
            echo '<td align="center" class="woteditable" data-ids="' . intval($customerPersonId) . '_' . $customerPersonPayWeekInfoId . '">'. "\n";
            echo $weekOTDisplay;
            echo '</td>'. "\n";
        echo '</tr>'. "\n";
    }
echo '</table>'. "\n";

?>
<script>
    $(function () {
        <?php /* Set threshold for how much work in a week constitutes overtime for this person
                 Doesn't use an "AJAX working" icon.
                 Synchronous POST to _admin/ajax/weekotamount.php passing new number >>>00002 no apparent validity check.
                 Updates text (number) on success.
                 Alerts on failure.
        */ 
        ?>
        $("#edit_table td.woteditable").dblclick(function () {
            var OriginalContent = $(this).text();
            var inputNewText = prompt("Enter new content for Week OT for " +
                $(this).closest('tr').find('td.formatted-name').text() +
                ":", OriginalContent);
            if (inputNewText!=null) {    
                $.ajax({
                    url: '../ajax/weekotamount.php',
                    data:{ id: $(this).data('ids'), value: inputNewText },
                    async:false,
                    type:'post',
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
        }); // END $("#edit_table td.woteditable").dblclick
    });
    
    $(function () {
        <?php /* Set threshold for how much work on a day constitutes overtime for this person
                 Doesn't use an "AJAX working" icon.
                 Synchronous POST to _admin/ajax/dayotamount.php passing new number >>>00002 no apparent validity check.
                 Updates text (number) on success.
                 Alerts on failure.
        */ 
        ?>
        $("#edit_table td.doteditable").dblclick(function () {
            var OriginalContent = $(this).text();
            var inputNewText = prompt("Enter new content for Day OT for " +
                $(this).closest('tr').find('td.formatted-name').text() +
                ":", OriginalContent);
            if (inputNewText!=null) {
                $.ajax({
                    url: '../ajax/dayotamount.php',
                    data:{ id: $(this).data('ids'), value: inputNewText },
                    async:false,
                    type:'post',
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
        }); // END $("#edit_table td.doteditable").dblclick
    });    
    
    $(function () {
        <?php /* Set normal hours per day for this person
                 Doesn't use an "AJAX working" icon.
                 Synchronous POST to _admin/ajax/dayotamount.php passing new number >>>00002 no apparent validity check.
                 Updates text (number) on success.
                 Alerts on failure.
        */ 
        ?>
        $("#edit_table td.dheditable").dblclick(function () {
            var OriginalContent = $(this).text();    
            var inputNewText = prompt("Enter new content for Day Hrs for " +
                $(this).closest('tr').find('td.formatted-name').text() +
                ":", OriginalContent);    
            if (inputNewText!=null) {    
                $.ajax({
                    url: '../ajax/dayhoursamount.php',
                    data:{ id: $(this).data('ids'), value: inputNewText },
                    async:false,
                    type:'post',
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
        }); // END $("#edit_table td.dheditable").dblclick
    });
    
</script>
</body>
</html>
