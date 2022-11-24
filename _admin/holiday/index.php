<?php 
/* _admin/holiday/index.php

   EXECUTIVE SUMMARY: Allows updating PTO (in minutes) for each employee for holidays.

   Beginning with v2020-3, company holidays are drawn from DB table 'holiday'; basically, holidays are 
   implemented as a holidayName & holidayDate (e.g. 'Thanksgiving Day +1', '2018-11-23'). We also store
   year explicitly for convenience, and of course there is a primary key holidayId.
   
   PRIMARY INPUTS: $_REQUEST['startDate'], $_REQUEST['endDate']; dates are inclusive; if missing, we will have go from one year before the present date to one year after
   
   OPTIONAL INPUTS: $_REQUEST['act']; supported values:
       * "addHoliday", uses additional values $_REQUEST['holidayName'], $_REQUEST['holidayDate']
       * "renameHoliday", uses additional values $_REQUEST['newHolidayName'], $_REQUEST['holidayDate'], $_REQUEST['holidayId']
       * "deleteHoliday" (only offered if no associated user hours), uses additional value $_REQUEST['holidayId']
*/

include '../../inc/config.php';
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">

<style>
/* sticky added 2020-06-24 */
.sticky-left {
    position:webkit-sticky; /* for Safari */
    position:sticky;
    left:0;
    background-color: #f8f0ff;
}
</style>
	
<script type="text/javascript">

var adjustTime = function(val) {
	alert(val);	
}

</script>

<?php
$db = DB::getInstance();
$employees = $customer->getEmployees();

$error = '';
$errorId = 0;
$v = new Validator2($_REQUEST);
list($error, $errorId) = $v->init_validation();
if ($error) {
    $logger->error2('1592324775', "Error(s) found in init validation: [".json_encode($v->errors())."]");
} else {
    $v->stopOnFirstFail(); 
    $v->rule('dateFormat', ['startDate', 'endDate'], 'Y-m-d');
    $v->rule('in', 'act', ['addHoliday', 'renameHoliday', 'deleteHoliday']);
    if( !$v->validate() ) {
        $error = "Input error, bad start or end date: ";
        $error .= json_encode($v->errors());
        $logger->error2('1592324800', $error);
    }
}

if (!$error) {
    if (array_key_exists('startDate', $_REQUEST)) {
        $startDateUnix = strtotime($_REQUEST['startDate']);        
    } else {
        $startDateUnix = strtotime(' -1 year');
    }
    $startDate = date('Y-m-d', $startDateUnix);

    if (array_key_exists('endDate', $_REQUEST)) {
        $endDateUnix = strtotime($_REQUEST['endDate']);        
    } else {
        $endDateUnix = strtotime(' +1 year');
    }
    $endDate = date('Y-m-d', $endDateUnix);
    
    if ($act == 'addHoliday') {
        $v->rule('required', ['holidayName', 'holidayDate']);
        $v->rule('dateFormat', 'holidayDate', 'Y-m-d');
        $v->rule('lengthBetween', 'holidayName', 1, 100);
        if( !$v->validate() ) {
            $error = "Input error, bad holiday name or date: ";
            $error .= json_encode($v->errors());
            $logger->error2('1592343989', $error);
        }
        if (!$error) {
            $holidayDate = $_REQUEST['holidayDate'];
            $holidayName = $_REQUEST['holidayName'];
            $year = explode('-', $holidayDate)[0];
            
            // Is there a match too close to allow this?
            $query = "SELECT holidayId, holidayName, year, holidayDate FROM " . DB__NEW_DATABASE . ".holiday ";
            $query .= "WHERE (holidayName='" . $db->real_escape_string($holidayName) . "' AND year='" . $db->real_escape_string($year) . "') ";
            $query .= "OR holidayDate='" . $db->real_escape_string($holidayDate) . "';";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1592344447', 'Hard DB error', $db);
                echo "Hard DB error, see log\n";
                die();
            }
            if ($result->num_rows) {
                $row = $result->fetch_assoc();
                $error = "Cannot add $holidayName on $holidayDate, there is already a {$row['holidayName']} on {$row['holidayDate']}";
            }
        }
        if (!$error) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".holiday ";
            $query .= "(holidayName, year, holidayDate) ";
            $query .= "VALUES ";
            $query .= "('" . $db->real_escape_string($holidayName) . "', '" . $db->real_escape_string($year) . "', '" . $db->real_escape_string($holidayDate) . "');";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1592345806', 'Hard DB error', $db);
                echo "Hard DB error, see log\n";
                die();
            }
        }
    } // END if ($act == 'addHoliday') 
    
    if ($act=='renameHoliday') {
        $v->rule('required', ['newHolidayName', 'holidayId']);
        $v->rule('lengthBetween', 'newHolidayName', 1, 100);
        $v->rule('dateFormat', 'holidayDate', 'Y-m-d');
        $v->rule('integer', 'holidayId');
        $v->rule('min', 'holidayId', 1);
        if( !$v->validate() ) {
            $error = "Input error, bad holiday name or id: ";
            $error .= json_encode($v->errors());
            $logger->error2('1592346441', $error);
        }
        if (!$error) {
            $newHolidayName = $_REQUEST['newHolidayName'];
            $holidayId = $_REQUEST['holidayId'];
            $holidayDate = $_REQUEST['holidayDate'];
            $year = explode('-', $holidayDate)[0];
            // Is there already a holiday that year with this new name?
            $query = "SELECT holidayId, holidayName, year, holidayDate FROM " . DB__NEW_DATABASE . ".holiday ";
            $query .= "WHERE (holidayName='" . $db->real_escape_string($newHolidayName) . "' AND year='" . $db->real_escape_string($year) . "');";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1592346983', 'Hard DB error', $db);
                echo "Hard DB error, see log\n";
                die();
            }
            if ($result->num_rows) {
                $row = $result->fetch_assoc();
                $error = "Cannot change name: there is already a $newHolidayName on {$row['holidayDate']}";
            }

            $query = "UPDATE " . DB__NEW_DATABASE . ".holiday ";
            $query .= "SET holidayName = '" . $db->real_escape_string($newHolidayName) . "' ";
            $query .= "WHERE holidayId = $holidayId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1592346682', 'Hard DB error', $db);
                echo "Hard DB error, see log\n";
                die();
            }
        }
    } // END if ($act=='renameHoliday')
    
    $holidays = Array();    
    $query = "SELECT holidayId, year, holidayName, holidayDate  FROM " . DB__NEW_DATABASE . ".holiday ";
    $query .= "WHERE holidayDate >= '$startDate' ";
    $query .= "AND holidayDate <= '$endDate' ";
    $query .= "ORDER BY holidayDate ASC;";
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1592321024', 'Hard DB error', $db);
        echo "Hard DB error, see log\n";
        die();
    }
    while ($row = $result->fetch_assoc()) {
        $holidays[] = $row; 
    }
    $holidayDatesString = ''; // comma-separated string of dates built as we go through writing the dates in the UI
    foreach ($holidays as $holiday) {
        if (strlen($holidayDatesString)) {
            // not the first
            $holidayDatesString .= ',';
        }
        $holidayDatesString .= "'" . $holiday['holidayDate'] . "'";
    } 
    $employeesUserIdString = '';
    foreach ($employees as $employee) {	
        if (strlen($employeesUserIdString)) {
            // not the first
            $employeesUserIdString .= ',';
        }
        $employeesUserIdString .= $employee->getUserId();
    }
    foreach ($holidays as $hkey => $holiday) {
        $query = "SELECT * ";
        $query .= "FROM " . DB__NEW_DATABASE . ".pto ";
        $query .= "WHERE personId IN ($employeesUserIdString) ";
        $query .= "AND ptoTypeId = " . intval(PTOTYPE_HOLIDAY) . " ";
        $query .= "AND day = '" . $holiday['holidayDate'] . "' ";
        $query .= "LIMIT 1;";

        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1592348671', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        }
        $holidays[$hkey]['hasData'] = $result->num_rows > 0;
    }
    if ($act=='deleteHoliday') {
        // we need $holidays filled in before we can work with this, which is why it is not fartuer up. 
        $v->rule('required', 'holidayId');
        $v->rule('integer', 'holidayId');
        $v->rule('min', 'holidayId', 1);
        if( !$v->validate() ) {
            $error = "Input error, bad holiday name or id: ";
            $error .= json_encode($v->errors());
            $logger->error2('1592349827', $error);
        }
        if (!$error) {
            $holidayId = $_REQUEST['holidayId'];
            foreach ($holidays AS $holiday) {
                if ($holiday['holidayId'] == $holidayId) {
                    break; // found the right oe
                }
            }
            if (!$holiday || $holiday['holidayId'] != $holidayId) {
                $error = "Asked to delete holidayId $holidayId, but it's not found";
                $logger->error2('1592349896', $error);
            }
        }
        if (!$error) {
            if ($holiday['hasData']) {
                $error = "Asked to delete holidayId $holidayId ({$holiday['name']}, {$holiday['date']}), but it has hours logged.";
                $logger->error2('1592349645', $error);
            }
        }
        if (!$error) {
            $query = "DELETE FROM " . DB__NEW_DATABASE . ".holiday ";
            $query .= "WHERE holidayId = $holidayId;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDb('1592350389', 'Hard DB error', $db);
                echo "Hard DB error, see log\n";
                die();
            }
        }
        // Need to reload, since we need to rebuild $holidays
        header("Location: index.php");
    } // END if ($act=='deleteHoliday')
}
$num_columns = count ($holidays) + 1; 

// $dayHourWeekWidget introduced 2019-07-10 JM to allow editing in minutes, hours, or days, rather then just minutes as before
// File was considerably reworked at this time.
$dayHourWeekWidget = new DayHourWeekWidget( Array(
    'visibleMinutes' => true, 
    'visibleHours' => true, 
    'visibleDays' => true, 
    'increment' => 15, // minutes
    'allowNegative' => false,
    'promptPrefix' => 'Allocate vacation time in ',
    'promptSuffix' => ':',
    'idPrefix' => 'allocate-'
));
?>
<div  class="alert alert-danger" role="alert" id="validator-warning" style="color:red"><?= $error ?></div>
<label for="startDate">Date-range start:&nbsp;</label><input type="date" id="startDate" name="startDate" value="<?= $startDate ?>" />&nbsp;
<label for="endDate">Date-range end:&nbsp;</label><input type="date" id="endDate" name="endDate" value="<?= $endDate ?>" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<button id="addHoliday">Open 'Add Holiday'</button>
<script>
$(function() {
    $('#startDate, #endDate').change(function() {
        let startDate = $('#startDate').val();
        let endDate = $('#endDate').val();
        let queryString;
        if (startDate) {
            queryString = 'startDate=' + startDate; 
        } 
        if (endDate) {
            if (queryString) {
                queryString += '&';
            }
            queryString += 'endDate=' + endDate; 
        } 
        window.location.replace('index.php' + (queryString ? '?' + queryString : '')); // reload page        
    });
    
    $('#addHoliday').click(function() {
        $('#addHolidayForm').show();
        $('#addHoliday').hide();
    });
});
</script>

<?php /* "Add holiday" form, hidden until you click "Add holiday" */ ?>
<form id="addHolidayForm" style="display:none;" method="POST" action="">
<br /><b>Add holiday:</b>&nbsp;
<input type="hidden" name="act" value="addHoliday">
<input type="hidden" name="startDate" value="<?= $startDate ?>">
<input type="hidden" name="endDate" value="<?= $endDate ?>">
<label for="holidayName">Name:&nbsp;</label><input type="text" id="holidayName" name="holidayName"/>&nbsp;
<label for="holidayDate">Date:&nbsp;</label><input type="date" id="holidayDate" name="holidayDate"/>
<button>Add</button>
<button id="cancelAddHoliday" type="button">Cancel</button>
</form>
<script>
$(function() {
    $('#cancelAddHoliday').click(function() {
        $('#addHolidayForm').hide();
        $('#addHoliday').show();
    });
    $('#addHolidayForm').submit(function(event) {
        let hasRequiredValues = $('#holidayName').val() && $('#holidayDate').val();
        if ( !hasRequiredValues ) {
            // prevent submission
            event.preventDefault();
            return false; 
        }
    });
});
</script>


<p>Times are in minutes. Double-click any time to edit.</p>
<?php
/* Table: 
    * first column, no header: employee's name, last name first, alphabetical order
    * in each successive column:
      * two lines of heading: The name of each holiday, and their respective dates. 
      * one line per employee displaying:
        * PTO minutes for that person for that day, in minutes. If no such time, just a set of nonbreaking spaces.
          * If the user double-clicks on any of these individual PTO values, the handler prompts for a new value, 
            then calls _admin/ajax/holiday.php, passing it the HTML ID and the new value. That updates the database. 
            On success, we update the display.
*/


?>
<table border="1" cellpadding="2" cellspacing="0" id="edit_table">
    <tr id="holiday_names">
        <td>&nbsp;</td> <?php /* Blank in person name column */ ?>
        <?php 
        foreach ($holidays as $holiday) {
            // Name of holiday: this is an editable, self-submitting form in its own right.
            ?>
            <th align="center">
                <form method="POST" action="">
                    <input type="hidden" name="act" value="renameHoliday">
                    <input type="hidden" name="startDate" value="<?= $startDate ?>">
                    <input type="hidden" name="endDate" value="<?= $endDate ?>">
                    <input type="hidden" name="holidayId" value="<?= $holiday['holidayId'] ?>">
                    <input type="hidden" name="holidayDate" value="<?= $holiday['holidayDate'] ?>">
                    <input type="text" name="newHolidayName" value="<?= $holiday['holidayName'] ?>" />
                </form>
            </th>    
            <?php
        }
        ?>
    </tr>
    <script>
        $(function() {
            $('input[name="newHolidayName"]').change(function() {
                let $this = $(this);
                if ($this.val()) {
                    $this.closest('form').submit();
                }
            });
        });
    </script>
    
    <tr id="holiday_dates">    
        <td>&nbsp;</td> <?php /* Blank in person name column */ ?>
        <?php 
        foreach ($holidays as $holiday) {
            if ($holiday['hasData']) {
                // just date of holiday
            ?>
                <th align="center"><?= $holiday['holidayDate'] ?></td>
            <?php
            } else {
            ?>
                <th align="center"><?= $holiday['holidayDate'] ?>&nbsp;
                <form method="POST" action="">
                <input type="hidden" name="act" value="deleteHoliday">
                <input type="hidden" name="startDate" value="<?= $startDate ?>">
                <input type="hidden" name="endDate" value="<?= $endDate ?>">
                <input type="hidden" name="holidayId" value="<?= $holiday['holidayId'] ?>">
                <button>Del</button>
                </form>
                </td>
            <?php
            }
        }   
        ?>
    </tr>
    <?php
    $db = DB::getInstance();
    foreach ($employees as $employee) {	
		$actuals = array(); // associative array of rows for this employee from DB table pto, indexed by day  
		
		$query = "SELECT * ";
		$query .= "FROM " . DB__NEW_DATABASE . ".pto  ";
		$query .= "WHERE personId = " . intval($employee->getUserId()) . " ";
		$query .= "AND ptoTypeId = " . intval(PTOTYPE_HOLIDAY) . " ";
		$query .= "AND day IN (" . $holidayDatesString . ") ";

		$result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1592348713', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        }
        while ($row = $result->fetch_assoc()) {
            $actuals[$row['day']] = $row; 
        }
		   
        echo '<tr>';
            echo '<td class="sticky-left">' . $employee->getLastName() . ',&nbsp;' . $employee->getFirstName() . '</td>';
            foreach ($holidays as $holiday) {
                $display = isset($actuals[$holiday['holidayDate']]) ? $actuals[$holiday['holidayDate']]['minutes'] : '&nbsp;&nbsp;&nbsp;&nbsp';
                echo '<td align="center" class="editable" id="' . $employee->getUserId() . '_' . $holiday['holidayDate'] . '" '.
                     'onClicks="adjustTime(\'' . $employee->getUserId() . '_' . $holiday['holidayDate'] . '\')">' . $display . '</td>';                    
            }		
        echo '</tr>' . "\n";
    } // END foreach ($employees as $employee)
echo '</table>' . "\n";

// put this in a function so we can call it after we've built the elements it activates
echo $dayHourWeekWidget->getMechanismJS('<script>function activateTimeMechanism(){', '}</script>'); 

?>
<script>
$(function () {
    $("#edit_table td.editable").dblclick(function () {
        var $this = $(this);
        var originalContent = $this.text();
        
        // Person name is in first column of row
        var person = ($this.closest('tr').find('td').eq(0).text()).trim();
        
        // Holiday name is in same column of holiday_names row
        var column = $this.index();
        var holiday = $('#holiday_names').children().eq(column).text();
        
        /* OLD CODE REPLACED 2019-07-10 JM 
        var inputNewText = prompt("Enter new content for " + person + " for " + holiday + ":", originalContent);
        
        ... AND the AJAX call is slightly reworked.
        */
        
        if ( ! $('#time-dialog').length) {
            // need to build dialog             
            let $timeDialog = $('<div id="time-dialog">' + 
                'Enter new content for <span id="current-person"></span> for <span id="current-holiday"></span>:<br/>' +
                '<table><tbody>' + <?php echo $dayHourWeekWidget->getHTML(true); ?> + '</tbody></table>' +
            '</div>');
            $timeDialog.dialog({
                autoOpen: false,
                title: 'Holiday time',
                modal: true,
                resizable: true,
                closeOnEscape: true,
                width: 'auto',
                height: 'auto',
                position: {my:'center top', at:'center top', of:window},
                buttons: {
                    "OK": callAjaxHoliday,
                    "Cancel": function() {
                        $("#time-dialog").dialog('close');
                    }
                },
                // We discovered 2019-09-18 that we can't reuse the time-dialog for a different person
                //  because the "person" data gets built into it. There *might* be a better way to deal with 
                //  this, but we are a week away from release, and it seems like the simplest thing is to
                //  kill it after each use so that we force rebuilding it each time. This close function
                //  will do that (fixes http://bt.dev2.ssseng.com/view.php?id=26).
                close: function() {
                    $("#time-dialog").remove();
                }
            });
            activateTimeMechanism(); // Now that it's built, add the handlers
        }
        $('#current-person').html(person);
        $('#current-holiday').html(holiday);
        setValueInMinutes(parseInt(originalContent, 10));
        $("#time-dialog").dialog('open');
        function callAjaxHoliday() {
            // $this is deliberately a level above this
            $.ajax({
                url: '../ajax/holiday.php',                
                data:{ id: $this.attr('id'), value: <?php echo $dayHourWeekWidget->getValueInMinutesJS(); ?> },                
                async: false,
                type: 'post',
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            let newValue = <?php echo $dayHourWeekWidget->getValueInMinutesJS(); ?>;
                            if (!newValue || newValue == '0') {
                                newValue = '&nbsp;&nbsp;&nbsp;&nbsp';
                            }
                            $this.html(newValue);
                            $("#time-dialog").dialog('close');
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
        } // END function callAjaxHoliday
    });
});
</script>
</body>
</html>