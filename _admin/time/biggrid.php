<?php
/*  _admin/time/biggrid.php

    EXECUTIVE SUMMARY: Page to view and modify certain data for all current employees 
     of current customer (as of 2019-05, always SSS) for a pay period. NOTE that the 
     notion of "current employees" is always current as of time this code runs; when 
     running this for a past pay period, it is possible that this will omit some employee
     who has since left the company. Provides access to hourly rate, salary, copay,
     IRA amount, IRA type.
     
    All changes are made either by a double-click to edit, or by use of an HTML SELECT. No explicit "Submit".

    PRIMARY INPUT $_REQUEST['start']: the date the period starts ... e.g, "2019-10-16".

*/

//header ("Location: biggrid2.php"); // Commented out by Martin before 2019; probably just a dev experiment,
                                     // because biggrid2.php looks less than ready 2019-05 JM
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
tr.employee:nth-child(even) select {background-color:#eeeeff;}
</style>

<?php
$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';  // Martin comment: the date the week starts ... i.e. 2016-10-20
                                                               // JM: but this appears to be a pay period, always starting on the 1st or 16th,
                                                               //  not a week.

// Instantiates Time object based on inputs $start and displayType 'payperiod'. A lot of work is done in this constructor.
$time = new Time(0, $start, 'payperiod');

// Top nav. This is the "BIG GRID-PERIOD" page.
echo '<table border="0" cellpadding="0" width="100%">';
    echo '<tr>';
        echo '<td colspan="3">';
            // JM 2019-11-01 fixing http://bt.dev2.ssseng.com/view.php?id=41
            // Get rid of dead link to old PTO calendar; will eventually be revived, but right now it's a link to a dead page.
            //echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;[<a href="pto.php">PTO</a>]&nbsp;&nbsp;[BIG GRID-PERIOD]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]';
            // BEGIN REPLACEMENT CODE JM 2019-11-01
            echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;<span style="color:lightgray">[PTO]</span>&nbsp;&nbsp;[BIG GRID-PERIOD]&nbsp;&nbsp;[<a href="biggrid-week.php">BIG GRID-WEEK</a>]';
            // END REPLACEMENT CODE JM 2019-11-01
        echo '</td>';
    echo '</tr>';
echo '</table>';

echo '<br /><br /><h3>BIG GRID PAY PERIOD</h3>';

// Another small table, no headers, offering navigation to the previous or next periods 
//  (identical to summary.php). 
// In this case, the labels are:
//  * "Prev"
//  * the current date range (e.g. "06-16 thru 06-30-2020")
//  * "Next". 
// "Prev" & "Next" use the GET method to reload summary.php for the appropriate start date, with other inputs held constant.
echo '<table border="0" cellpadding="0" cellspacing="0" width="800">';
    echo '<tr><td colspan="3">&nbsp;</td></tr>';
    echo '<tr>';
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        //echo '<td style="text-align:left">&#171;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->previous . '">' . $time->previous . '</a>&#171;</td>';
        // END COMMENTED OUT BY MARTIN BEFORE 2019        
        echo '<td style="text-align:left">&#171;<a href="biggrid.php?start=' . $time->previous . '">prev</a>&#171;</td>';
        
        $e = date('Y-m-d', strtotime('-1 day', strtotime($time->next)));
        echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">Period: ' . 
            date("m-d", strtotime($time->begin)) . ' thru ' . 
            date("m-d", strtotime($e)) . '-' . date("Y", strtotime($time->begin)) . '</span></td>';
        
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        //echo '<td style="text-align:right">&#187;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->next . '">' . $time->next . '</a>&#187;</td>';
        // END COMMENTED OUT BY MARTIN BEFORE 2019
        echo '<td style="text-align:right">&#187;<a href="biggrid.php?start=' . $time->next . '">next</a>&#187;</td>';
    echo '</tr>';
echo '</table>';

$db = DB::getInstance();

// Get data for all current employees
// >>>00026 NOTE that this is "current" at the time the code is run, not current for the period in question.
// If this is run for a past period IT WILL OMIT any employees who have since left the company.
$employees = $customer->getEmployees(1);

echo "<p>Remember when updating rate or salary the amount is in cents.<br>They are DISPLAYED in the table as dollars and cents for convenience.<p>";

echo '<table border="0" cellpadding="3" cellspacing="1" id="edit_table">';
    echo '<tr>';
        echo '<th>Employee</th>';
        echo '<th>Hr Rate</th>';
        echo '<th>Salary</th>';
        echo '<th>CoPay</th>';
        echo '<th>IRA Amt.</th>';
        echo '<th>IRA Type</th>';
    echo '</tr>';

    foreach ($employees as $employee) {
        // 2019-10-21 JM: <tr> etc. moved way below for clearer structure.
        /* BEGIN REMOVED 2019-10-21 JM 
        echo '<tr>';
            // "Employee"
            echo '<td>' . $employee->getFormattedName(1) . '</td>';
        END REMOVED 2019-10-21 JM */   
            
        $query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo  ";
        $query .= " where customerPersonId = (select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson " .
                  " where customerId = " . intval($customer->getCustomerId()) . " and personId = " . $employee->getUserId() . ") ";
        $query .= " and periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "' ";
        $query .= " limit 1 ";

        $cpppi = false;

        if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                // >>>00018: 'if' would be clearer than while, since there can only be one row.
                // Or even clearer: just $cpppi = $result->fetch_assoc();
                while ($row = $result->fetch_assoc()) {
                    $cpppi = $row;
                }
            }
        } // >>>00002 else ignores failure on DB query!
    
        $rateDisp = '&nbsp;';
        $iraDisp = '&nbsp;';                
        $rateDisp = '&nbsp;'; // >>>00007 redundant to do this a second time; maybe meant to be something else?
        $salaryDisp = '&nbsp;';
        $copayDisp = '&nbsp;';
        $iraType = 0;
        
        $customerPersonPayPeriodInfoId = 0;
        $customerPersonId = 0;
        $id_pair = '0_0';
        
        if ($cpppi) {
            $customerPersonId = $cpppi['customerPersonId'];
            $x = $cpppi; // >>>00012: straight-out aliasing, why?
            
            $customerPersonPayPeriodInfoId = $x['customerPersonPayPeriodInfoId'];       

            // 2019-10-15, 2019-10-16 JM: introduce HTML data attributes, get away from multiple elements having
            //  same HTML ID, which is illegal HTML. See http://bt.dev2.ssseng.com/view.php?id=35.
            $id_pair = intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId; // introduced 2019-10-16 JM
            // END 2019-10-16 addition, more that is related below.
        
            $copay = $x['copay'];
            $rate = $x['rate'];
            $salaryHours = $x['salaryHours']; // >>>00007 set but never used
            $salaryAmount = $x['salaryAmount'];
            
            if (is_numeric($copay)) {
                $copayDisp = number_format($copay, 2);                    
            }       

            // Annual salary
            if (is_numeric($salaryAmount) && ($salaryAmount > 0)) {
                //$salaryDisp = '$' . number_format(($salaryAmount/100),2) . '/yr'; // COMMENTED OUT BY MARTIN BEFORE 2019
                $salaryDisp = number_format(($salaryAmount/100), 2);            
            }

            if (is_numeric($rate) && ($rate > 0)) {
                //$rateDisp = '$' . number_format(($rate/100),2) . '/hr'; // COMMENTED OUT BY MARTIN BEFORE 2019
                $rateDisp = number_format(($rate/100), 2);                        
            }

            $iraType = 	$x['iraType'];
            $ira = 	$x['ira'];
            
            if (is_numeric($ira)) {
                $iraDisp = $ira;
            } else {
                $iraDisp = 0;
            }                        
            // BEGIN MOVED FROM ABOVE and augmented 2019-10-21 JM    
            echo '<tr class="employee">';
                // "Employee"
                echo '<td class="employeename" data-id-pair="' . $id_pair . '">' . $employee->getFormattedName(1) . '</td>';
            // END MOVED FROM ABOVE and augmented 2019-10-21 JM
            
                // >>>00026 If it is possible for the 'if ($cpppi)' test to fail, and in particular if it is possible for it to 
                //  fail more than once in the loop, the $id_pair "0_0" will occur for all five cells on all rows where that occurs.
                // >>>00002 probably should find out if that ever happens, log as an "else" clause on the test above.
                
                // "Hr Rate": U.S. currency, 2 digits past the decimal point
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                //echo '<td align="center" class="rateeditable" id="' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '">';
                // BEGIN REPLACEMENT
                echo '<td align="center" class="rateeditable" data-id-pair="' . $id_pair . '">';
                // END REPLACEMENT
                    echo $rateDisp;
                echo '</td>';
    
                // "Salary": Annual salary, U.S. currency, 2 digits past the decimal point
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                // echo '<td align="center" class="salaryeditable" id="' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '">';
                // BEGIN REPLACEMENT
                echo '<td align="center" class="salaryeditable" data-id-pair="' . $id_pair . '">';
                // END REPLACEMENT
                    echo $salaryDisp;
                echo '</td>';
    
                // "CoPay": U.S. currency, 2 digits past the decimal point
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                // echo '<td align="center" class="copayeditable" id="' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '">';
                // BEGIN REPLACEMENT
                echo '<td align="center" class="copayeditable" data-id-pair="' . $id_pair . '">';
                // END REPLACEMENT
                    echo $copayDisp;
                echo '</td>';   
    
                // "IRA Amt.": straight from DB.
                //   * If iraType == IRA_TYPE_PERCENT, then this is the percentage employee puts into IRA (typically 3.00 or 5.00).
                //   * If iraType == IRA_TYPE_DOLLAR, then this is an amount in U.S. currency, expressed in dollars with two digits after the decimal point.
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                //echo '<td align="center" class="ieditable" id="' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '">';                        
                // BEGIN REPLACEMENT
                echo '<td align="center" class="ieditable" data-id-pair="' . $id_pair . '">';
                // END REPLACEMENT
                    echo $iraDisp;
                echo '</td>';
    
                // "IRA Type": HTML SELECT. 3 OPTIONs:
                //   * '---', value = 0
                //   * 'Percent', value = 1 ( == IRA_TYPE_PERCENT)
                //   * 'Dollar', value = 2 ( == IRA_TYPE_DOLLAR)
                $pselected = ($iraType == IRA_TYPE_PERCENT) ? ' selected ' : '';
                $dselected = ($iraType == IRA_TYPE_DOLLAR) ? ' selected ' : '';
    
                // OLD CODE removed 2019-10-15 JM, supplementary to work on HTML data attributes. ID might not be ideal way to pass this particular
                //  data, but it seems OK since it has the "type_" prefix. 
                // echo '<td align="center"><select name="iraType" id="type_' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '" ' .
                // BEGIN REPLACEMENT
                echo '<td align="center"><select name="iraType" id="type_' . $id_pair . '" ' .
                // END REPLACEMENT
                     'onchange="sendIRAType(this)"><option value="0">--</option>';
                    echo '<option value="' . IRA_TYPE_PERCENT . '" ' . $pselected . '>' . $iraTypes[IRA_TYPE_PERCENT] . '</option>';
                    echo '<option value="' . IRA_TYPE_DOLLAR . '" ' . $dselected . '>' . $iraTypes[IRA_TYPE_DOLLAR] . '</option>';
                echo '</select></td>';
            echo '</tr>';
        } else { // !$cpppi
            echo '<tr>';
                // "Employee"
                echo '<td>' . $employee->getFormattedName(1) . '</td>';
                echo '<td colspan="5"><span style="color:red">(employee has no customerPersonPayPeriodInfo record for this period)</span></td>';
            echo '</tr>';            
        }       
    } // END foreach ($employees...
echo '</table>';
?>
<script>

$(function () {
    <?php /* Set copay.
             Doesn't use an "AJAX working" icon.
             Synchronous POST to _admin/ajax/copayamount.php passing new number >>>00002 no apparent validity check.
             Updates text (number) on success.
             Alerts on failure.
    */ ?>
    $("#edit_table td.copayeditable").dblclick(function () {
        var OriginalContent = $(this).text();
        // BEGIN OLD CODE REPLACED 2019-10-21 JM
        //var inputNewText = prompt("Enter new content for copay:", OriginalContent);
        // END OLD CODE REPLACED 2019-10-21 JM
        // BEGIN NEW CODE 2019-10-21 JM
        let employeeName = $('.employeename', $(this).closest('tr')).text();
        let inputNewText = prompt("Enter new copay for " + employeeName +":", OriginalContent);
        // END NEW CODE 2019-10-21 JM
        if (inputNewText!=null) {
            $.ajax({
                url: '../ajax/copayamount.php',
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                //data:{ id: $(this).attr('id'), value: inputNewText },                        
                // BEGIN REPLACEMENT
                data:{ id: $(this).attr('data-id-pair'), value: inputNewText },
                // END REPLACEMENT
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
    }); // END $("#edit_table td.copayeditable").dblclick(function () {
});

$(function () {
    <?php /* Set annual salary.
             Doesn't use an "AJAX working" icon.
             Synchronous POST to _admin/ajax/salaryamount.php passing new number >>>00002 no apparent validity check.
             Updates text (number) on success.
             Alerts on failure.
    */ ?>
    $("#edit_table td.salaryeditable").dblclick(function () {
        var OriginalContent = $(this).text();
        // BEGIN OLD CODE REPLACED 2019-10-21 JM
        //var inputNewText = prompt("Enter new content for annual salary (dollars):", OriginalContent);
        // END OLD CODE REPLACED 2019-10-21 JM
        // BEGIN NEW CODE 2019-10-21 JM
        let employeeName = $('.employeename', $(this).closest('tr')).text();
        let inputNewText = prompt("Enter new annual salary for " + employeeName +" (dollars):", OriginalContent);
        // END NEW CODE 2019-10-21 JM
        
        // BEGIN ADDED 2019-10-18
        // Per http://bt.dev2.ssseng.com/view.php?id=39: the user was prompted for dollars; cope with several different ways they might have entered that.
        // Underlying data in DB is cents.
        { // limit scope of 'valid'
            let valid = false;
            while (!valid) {
                // Get rid of any commas; >>>00001 we could be more subtle (make sure they were in the right place)
                inputNewText = inputNewText.replace(/,/g, '');
                
                if (inputNewText.match(/^\d+$/)) {
                    // entirely digits; effectively, multiply by 100
                    inputNewText += '00';
                    valid = true;
                } else if (inputNewText.match(/^\d+\.\d\d$/)) {
                    // dollars & cents; drop the decimal point
                    inputNewText = inputNewText.replace(/\./, '')
                    valid = true;
                } else {
                    inputNewText = prompt('"' + inputNewText + '"' + " is not valid. Enter new annual salary for " + employeeName +" (dollars):", OriginalContent);
                }
            }
        }
        // END ADDED 2019-10-18
        
        if (inputNewText!=null) {
            $.ajax({
                url: '../ajax/salaryamount.php',
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                //data:{ id: $(this).attr('id'), value: inputNewText },                        
                // BEGIN REPLACEMENT
                data:{ id: $(this).attr('data-id-pair'), value: inputNewText },
                // END REPLACEMENT
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            // Per http://bt.dev2.ssseng.com/view.php?id=39: convert cents to dollars
                            // BEGIN REMOVED 2019-10-18
                            // $(this).text(inputNewText);
                            // END REMOVED 2019-10-18
                            // BEGIN REPLACEMENT 2019-10-18
                            if (inputNewText.match(/^\d+\d\d$/)) { // at least 3 digits: NOTE this is not inherent, but we are not allowing amounts under $1.00
                                let centsAt = inputNewText.length-2;
                                let centsString = inputNewText.substring(centsAt);
                                let dollarStringScratch = inputNewText.substring(0, centsAt);
                                let dollarString = '';
                                
                                // now insert any commas into the dollar string
                                while (dollarStringScratch.length > 0) {
                                    dollarString = dollarStringScratch.substring(dollarStringScratch.length-3) + (dollarString ? (',' + dollarString) : '');
                                    dollarStringScratch = dollarStringScratch.substring(0, dollarStringScratch.length-3);
                                }
                                
                                $(this).text(dollarString + '.' + centsString);
                            } else {
                                alert('error invalid value in cents: "' + inputNewText + '"');
                            }
                            // END REPLACEMENT 2019-10-18
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
    }); // END $("#edit_table td.salaryeditable").dblclick
});

$(function () {
    <?php /* Set hourly rate.
             Doesn't use an "AJAX working" icon.
             Synchronous POST to _admin/ajax/rateamount.php passing new number >>>00002 no apparent validity check.
             Updates text (number) on success.
             Alerts on failure.
    */ ?>
    $("#edit_table td.rateeditable").dblclick(function () {        
        var OriginalContent = $(this).text();
        // BEGIN OLD CODE REPLACED 2019-10-21 JM
        //var inputNewText = prompt("Enter new content for hourly rate (dollars):", OriginalContent);
        // END OLD CODE REPLACED 2019-10-21 JM
        // BEGIN NEW CODE 2019-10-21 JM
        let employeeName = $('.employeename', $(this).closest('tr')).text();
        let inputNewText = prompt("Enter new hourly rate for " + employeeName +" (dollars):", OriginalContent);
        // END NEW CODE 2019-10-21 JM
        
        // BEGIN ADDED 2019-10-18
        // Parallel to salary, >>>00037 might want to share common code
        // Underlying data in DB is cents.
        { // limit scope of 'valid'
            let valid = false;
            while (!valid) {
                // Get rid of any commas; >>>00001 we could be more subtle (make sure they were in the right place)
                inputNewText = inputNewText.replace(/,/g, '');
                
                if (inputNewText.match(/^\d+$/)) {
                    // entirely digits; effectively, multiply by 100
                    inputNewText += '00';
                    valid = true;
                } else if (inputNewText.match(/^\d+\.\d\d$/)) {
                    // dollars & cents; drop the decimal point
                    inputNewText = inputNewText.replace(/\./, '')
                    valid = true;
                } else {
                    inputNewText = prompt('"' + inputNewText + '"' + " is not valid. Enter new hourly rate for " + employeeName +" (dollars):", OriginalContent);
                }
            }
        }
        // END ADDED 2019-10-18

        if (inputNewText!=null) {
            $.ajax({
                url: '../ajax/rateamount.php',
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                //data:{ id: $(this).attr('id'), value: inputNewText },                        
                // BEGIN REPLACEMENT
                data:{ id: $(this).attr('data-id-pair'), value: inputNewText },
                // END REPLACEMENT
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            // BEGIN REMOVED 2019-10-18
                            // $(this).text(inputNewText);
                            // END REMOVED 2019-10-18
                            // BEGIN REPLACEMENT 2019-10-18
                            // >>>00037: again, potential for common code with salary
                            if (inputNewText.match(/^\d+\d\d$/)) { // at least 3 digits: NOTE this is not inherent, but we are not allowing amounts under $1.00
                                let centsAt = inputNewText.length-2;
                                let centsString = inputNewText.substring(centsAt);
                                let dollarStringScratch = inputNewText.substring(0, centsAt);
                                let dollarString = '';
                                
                                // now insert any commas into the dollar string
                                while (dollarStringScratch.length > 0) {
                                    dollarString = dollarStringScratch.substring(dollarStringScratch.length-3) + (dollarString ? (',' + dollarString) : '');
                                    dollarStringScratch = dollarStringScratch.substring(0, dollarStringScratch.length-3);
                                }
                                
                                $(this).text(dollarString + '.' + centsString);
                            } else {
                                alert('error invalid value in cents: "' + inputNewText + '"');
                            }
                            // END REPLACEMENT 2019-10-18
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
    }); // END $("#edit_table td.rateeditable").dblclick
});

$(function () {
    <?php /* Set IRA amount, which can be either U.S. currency or a percentage.
             Doesn't use an "AJAX working" icon.
             Synchronous POST to _admin/ajax/iraamount.php passing new number >>>00002 no apparent validity check.
             Updates text (number) on success.
             Alerts on failure.
    */ ?>
    $("#edit_table td.ieditable").dblclick(function () {
        var OriginalContent = $(this).text();
        // BEGIN OLD CODE REPLACED 2019-10-21 JM
        //var inputNewText = prompt("Enter new content for IRA amount (currency or percentage):", OriginalContent);
        // END OLD CODE REPLACED 2019-10-21 JM
        // BEGIN NEW CODE 2019-10-21 JM
        let employeeName = $('.employeename', $(this).closest('tr')).text();
        let inputNewText = prompt("Enter new IRA amount (currency or percentage) for " + employeeName +":", OriginalContent);
        // END NEW CODE 2019-10-21 JM
        if (inputNewText!=null) {
            $.ajax({
                url: '../ajax/iraamount.php',
                // OLD CODE removed 2019-10-15 JM, see note above about HTML data attributes
                //data:{ id: $(this).attr('id'), value: inputNewText },                        
                // BEGIN REPLACEMENT
                data:{ id: $(this).attr('data-id-pair'), value: inputNewText },
                // END REPLACEMENT
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
    }); // END $("#edit_table td.ieditable").dblclick
});

// Action triggered by changing SELECT.
<?php /* 
    INPUT sel: an HTML SELECT object. Its ID should be of the form type_customerPersonId_customerPersonPayPeriodInfoId, where
        * type is the literal 'type'.
        * customerPersonId is a primary key to DB table CustomerPerson.
        * customerPersonPayPeriodInfoId is a primary key to DB table CustomerPersonPayPeriodInfo.
        * e.g. type_577_6543 would be customerPersonId = 577, customerPersonPayPeriodInfoId = 6543.  
    The selected OPTION value will be the new value for the corresponding row in CustomerPersonPayPeriodInfo.  
    
    Alerts on failure.

    >>>00002 >>>00016 no apparent validity checks.        
*/ ?>    
var sendIRAType = function(sel) {
    var sel = document.getElementById(sel.id);
    var val = sel.options[sel.selectedIndex].value;
    $.ajax({
        url: '../ajax/iratype.php',
        data:{ id: sel.id, value: val },
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
} // END function sendIRAType

</script>
</body>
</html>
