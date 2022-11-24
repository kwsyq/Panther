<?php
/*  _admin/time/info_week.php

    EXECUTIVE SUMMARY: PAGE that lets you see historical per-week pay rate information for an employee 
    and alter factors (e.g. when overtime sets in) that determine pay for any given pay week.
        
    PRIMARY INPUT: $_REQUEST['userId']. Should be an employee.
*/

include '../../inc/config.php';

$userId = isset($_REQUEST['userId']) ? intval($_REQUEST['userId']) : 0;
$employee = new User($userId, $customer);
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

// JM 2019-11-01 based on conversations around http://bt.dev2.ssseng.com/view.php?id=41, allow broader navigation
// Old policy: Allow navigation back to time.php, the only place from which we get here.
// OLD CODE REMOVED
// echo '&nbsp;&nbsp;[<a href="time.php">EMPLOYEES</a>]';
// BEGIN REPLACEMENT CODE JM 2019-11-01
echo '&nbsp;&nbsp;[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;<span style="color:lightgray">[PTO]</span>&nbsp;&nbsp;[<a href="biggrid.php">BIG GRID-PERIOD</a>]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]' . "\n";
// END REPLACEMENT CODE JM 2019-11-01

// Employee name as header
echo '<h1>' . $employee->getFirstName() . '&nbsp;' . $employee->getLastName() . '</h1>' . "\n";
echo '<h2>Pay Week Info (Double-Click values for Day Hours, Day OT, WeekOT)</h2>' . "\n";

$user = new User($userId, $customer);
$customerPersonId = $user->getCustomerPersonId();

//$customerPersonData = $user->getCustomerPersonData(); // COMMENTED OUT BY MARTIN BEFORE 2019

// will go in chronological order because customerPersonPayWeekInfoId increases monotonically over time. 
$db = DB::getInstance();
$query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayWeekInfo ";
$query .= " where customerPersonId = " . intval($customerPersonId);
$query .= " order by customerPersonPayWeekInfoId asc ";

$infos = array();

if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {                    
        while ($row = $result->fetch_assoc()) {        
            $infos[] = $row;        
        }        
    }        
}  // >>>00002 else ignores failure on DB query!

echo '<table border="0" cellpadding="3" cellspacing="2" id="edit_table">' . "\n";
    foreach ($infos as $ikey => $info) {
        // write a heading at top & every 20th row
        if (($ikey % 20) == 0){
            echo '<tr bgcolor="#cccccc">';
                echo '<th>Period Begin</th>';
                echo '<th>Day Hours</th>';
                echo '<th>Day OT</th>';
                echo '<th>Week OT</th>';
                echo '<th>Work Week</th>';
            echo '</tr>' . "\n";
        }
        
        $t = strtotime($info['periodBegin']);
        
        echo '<tr>' . "\n";;
            // JM: classes added to distinguish these TDs 2019-12-12 JM
            echo '<td class="week">' . date("M d Y", $t) . '</td>' . "\n";;
            echo '<td align="center" class="editable dayHours" id="dayHours_' . $info['customerPersonPayWeekInfoId'] . '">' . $info['dayHours'] . '</td>' . "\n";;
            echo '<td align="center" class="editable dayOT" id="dayOT_' . $info['customerPersonPayWeekInfoId'] . '">' . $info['dayOT'] . '</td>' . "\n";;
            echo '<td align="center" class="editable weekOT" id="weekOT_' . $info['customerPersonPayWeekInfoId'] . '">' . $info['weekOT'] . '</td>' . "\n";;
            echo '<td>' . $workweek[$info['workWeek']] . '</td>' . "\n";;
        echo '</tr>' . "\n";;
    }
echo '</table>' . "\n";; // close TABLE element added 2019-12-10 JM    

?>

<script>

$(function () {
    // Double-click any editable cell to launch this.
    // NOTE no AJAX "loading" icon in cell
    // Makes synchronous POST to _admin/ajax/payweekinfo.php, passing an ID like
    //  'dayHours_ID', 'dayOT_ID', or 'weekOT_ID, where ID is the primary key for 
    //  the relevant row in DB table CustomerPersonPayWeekInfo.
    // Updates cell on successful return; alerts on error.
    $("#edit_table td.editable").dblclick(function () {
        // OLD CODE replaced 2019-12-12 JM:
        // var OriginalContent = $(this).text();
        // var inputNewText = prompt("Enter new content for:", OriginalContent);
        // BEGIN REPLACEMENT 2019-12-12 JM
        var $this = $(this);
        var OriginalContent = $this.text();
        var beginWeek = $this.closest('tr').find('.week').text().trim();
        var promptText;
        if ($this.is('.dayHours')) {
            promptText = "Enter hours per day for week of " + beginWeek + ':';
        } else if ($this.is('.dayOT')) {
            promptText = "Enter hours per day triggering overtime for week of " + beginWeek + ':';
        } else if ($this.is('.weekOT')) {
            promptText = "Enter hours per week triggering overtime for week of " + beginWeek + ':';
        } else {
            promptText = "Enter new content for:";
        }
        var inputNewText = prompt(promptText, OriginalContent);
        // END REPLACEMENT 2019-12-12 JM
        if (inputNewText!=null) {
            $.ajax({
                url: '../ajax/payweekinfo.php',
                data:{ id: $(this).attr('id'), value: inputNewText },
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            // OLD CODE replaced 2019-12-12 JM:
                            // $(this).text(inputNewText);
                            // BEGIN REPLACEMENT 2019-12-12 JM
                            $this.text(inputNewText);
                            // END REPLACEMENT 2019-12-12 JM
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
    });
});

</script>
</body>
</html>