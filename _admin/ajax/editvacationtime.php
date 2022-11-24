<?php
/*  _admin/ajax/editvacationtime.php

    EXECUTIVE SUMMARY Return HTML for vacation-alloc-dialog in _admin/time/time.php to let an admin allocate vacation/sick time for an employee (user).
    On error, returns error message.
    
    INPUT $_REQUEST['userId'] - primary key to DB table Person
    INPUT $_REQUEST['customerId'] - primary key to DB table Customer; as of 2019-05, always SSS
    
    The two inputs together suffice to determine a companyPersonId as needed in DB table VacationTime.
    
    Presumes the availablility of jQuery and jQueryUI.
    
    >>>00002: errors, such as invalid input, should also log
*/

require_once __DIR__.'/../../inc/config.php';
?>
<div id="editvacationtime">
    <script src="/js/addHoverHelp.js" />
    <?php
    
    if ( ! array_key_exists('userId', $_REQUEST) ) {
        return 'editvacationtime.php: missing userId';
    }
    if ( ! array_key_exists('customerId', $_REQUEST) ) {
        return 'editvacationtime.php: missing customerId';
    }
    $userId = intval($_REQUEST['userId']);
    $customerId = intval($_REQUEST['customerId']);
    if ( ! ($userId > 0) ) {
        return 'editvacationtime.php: userId must be a positive integer, got "' . $_REQUEST['userId'] . '"';
    }
    if ( ! ($customerId > 0) ) {
        return 'editvacationtime.php: customerId must be a positive integer, got "' . $_REQUEST['customerId'] . '"';
    }
    
    $customer = new Customer($customerId);
    $user = new User($userId, $customer);
    if ( !intval($user->getCustomerPersonId()) ) {
        return "editvacationtime.php: user $userId, customer $customerId does not resolve to a user";
    }
    if ( !$user->isEmployee() ) {
        return "editvacationtime.php: user $userId, customer $customerId, CustomerPerson " . $user->getCustomerPersonId() . " is not an employee";
    }
    
    // Canonical representation of rows in DB table VacationTime for this employee
    $vacationTimeRows = $user->getVacationTimeHistory();
    $currentVacationTime = 0;
    $futureVacationTime = 0;
    $now = date("Y-m-d H:i:s");
    foreach ($vacationTimeRows as $vacationTimeRow) {
        $isFuture = $vacationTimeRow['effectiveDate'] > $now;
        $minsOnRow = $vacationTimeRow['allocationMinutes'];
        if ($isFuture) {
            $futureVacationTime += $minsOnRow;
        } else {
            $currentVacationTime += $minsOnRow;
        }
    }
    $vacationUsed = $user->getVacationUsed();
    $vacationBalance = $currentVacationTime - $vacationUsed;
    
    $dayHourWeekWidget = new DayHourWeekWidget( Array(
        'visibleMinutes' => true, 
        'visibleHours' => true, 
        'visibleDays' => true, 
        'increment' => 15, // minutes
        'promptPrefix' => 'Allocate time in ',
        'promptSuffix' => ':',
        'idPrefix' => 'allocate-'
    ));
    
    
    // user's name as heading
    echo '<h3>'.$user->getFormattedName(true).'</h3>';
    
    echo '<p>PTO must be allocated in multiples of 15 minutes, and can be positive or negative.</p>';
    
    echo "<table>";
        echo "<tbody>";
        
            echo $dayHourWeekWidget->getHTML(); // rows for allocate-minutes, allocate-hours, allocate-days
            
            echo "<tr>";
                echo "<th align=\"right\">Note:&nbsp;</th>";
                echo "<td><input id=\"allocate-note\" type=\"text\" /></td>"; // No directly related events
            echo "</tr>";
            echo "<tr>";
                echo "<th align=\"right\">Issue date (default is now):&nbsp;</th>";
                echo "<td>";
                    echo "<input id=\"allocate-date\" type=\"date\" />"; // code for events is below table
                echo "</td>";
            echo "</tr>";
            echo "<tr>";
                echo "<td colspan=\"2\">";
                    echo "<button id=\"do-it\">Allocate time</button>"; // code for events is below table
                echo "</td>";
            echo "</tr>";
        echo "</tbody>";
    echo "</table>";
    echo $dayHourWeekWidget->getMechanismJS();
    ?>
    
    <script>    
        $('#do-it').click(function() {
             var effectiveDate = $('#allocate-date').val() ? $('#allocate-date').val() : false;
             /* >>> DEBUG: see what we are sending
             alert('../ajax/allocatevacationtime.php' 
                 + '?minutes=' + $('#allocate-minutes').val()
                 + '&customerId=<?php echo $customerId; ?>'
                 + '&userId=<?php echo $userId; ?>'
                 + '&note=' + encodeURIComponent($('#allocate-note').val())
                 + '&effectiveDate=' + effectiveDate
                 );
             */
             
            $.ajax({
                url: '../ajax/allocatevacationtime.php',
                data:{ 
                       minutes: <?php echo $dayHourWeekWidget->getValueInMinutesJS(); ?>, 
                       customerId: <?php echo $customerId; ?>,
                       userId: <?php echo $userId; ?>,
                       note: encodeURIComponent($('#allocate-note').val()),
                       effectiveDate: effectiveDate
                },
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {    
                        if (data['status'] == 'success') {
                            $('#vacation-alloc-dialog').dialog('close');
                            // Reload, because calculating the new total time is complicated enough that it's not worth being clever.
                            window.location.reload(true);
                            // avoid ever having 2 copies of this at once
                            $('#editvacationtime').remove();
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
             
             
        });
    </script>
    <?php
    echo "<br/><br/>";
    echo "<table>";
        echo "<tbody>";
            echo "<tr><th align=\"right\">Current PTO time allocated:&nbsp;</th><td>$currentVacationTime minutes (" .  
                  number_format((float)intval($currentVacationTime)/60, 2, '.', '') . " hours)</td></tr>";
            
            // If there is any future PTO, we will show this HEADING in red.      
            echo "<tr><th align=\"right\">" . 
                  ($futureVacationTime ?  "<span style=\"color:red\"> " : '') . 
                  "PTO time allocated, but not yet effective:" . 
                  ($futureVacationTime ?  "</span>" : '') . 
                  "&nbsp;</th><td>$futureVacationTime minutes (" .  
                  number_format((float)intval($futureVacationTime)/60, 2, '.', '') . " hours)</td></tr>";
    
            echo "<tr><th align=\"right\">PTO used:&nbsp;</th><td>$vacationUsed minutes (" .  
                  number_format((float)intval($vacationUsed)/60, 2, '.', '') . " hours)</td></tr>";
                  
            // If vacation balance is negative, it will be parenthesized & in red.
            echo "<tr><th align=\"right\">PTO balance (ignores future allocations):&nbsp;</th><td>";
            if ($vacationBalance >= 0) {
                echo "$vacationBalance minutes (" .  
                      number_format((float)intval($vacationUsed)/60, 2, '.', ''); 
            } else {
                echo "<span style=\"color:red\">( $vacationBalance )</span> minutes (" .  
                     "<span style=\"color:red\"> (". number_format((float)intval($vacationBalance)/60, 2, '.', '') . " )</span> ";
            }
            echo " hours)</td></tr>";
        echo "</tbody>";
    echo "</table>";
    
    echo '<div id="hiding-history">' .
         '<button id="show-history" type="button">Show allocation history</button>' . // script for this button follows DIV hiding-history
         '</div>';     
    ?>
    <script>
        $(function() {
            $('#show-history').click(function() {
                $('#hiding-history').hide();
                $('#showing-history').show();
                $('#vacation-alloc-dialog').dialog({height:'auto', width:'auto'});
            });
        });
    </script>
    
    
    <style>
    #history-table.table, #history-table th, #history-table td { border: 1px solid black; }
    </style>
    
    <?php
    echo '<div id="showing-history" style="display:none">' .
         '<button id="hide-history" type="button">Hide allocation history</button><br/>' . // script for this button follows DIV showing-history
         '<table id="history-table">'.
             '<thead>'.
                 '<th>As minutes</th>'.
                 '<th>As hours</th>'.
                 '<th>Alloc. date</th>'.
                 '<th>Effective</th>'.
                 '<th>Alloc. by</th>'.
                 '<th>Note</th>'.
             '</thead>'.
             '<tbody>';
             foreach ($vacationTimeRows as $vacationTimeRow) {
                 $mins = $vacationTimeRow['allocationMinutes'];
                 echo '<tr>';
                    // "As minutes"
                    if ($mins >= 0) {
                        echo '<td>'. $mins .'</td>';
                    } else {
                        echo '<td><span style="color:red">('. $mins .')</span></td>';
                    }
                    
                    // "As hours"
                    if ($mins >= 0) {
                        echo '<td>'. number_format((float)intval($mins)/60, 2, '.', '') . '</td>';
                    } else {
                        echo '<td><span style="color:red">('. number_format((float)intval($mins)/60, 2, '.', '') . ')</span></td>';
                    }
                    
                    // "Alloc date"
                    echo '<td>'. $vacationTimeRow['inserted'] . '</td>';
                    
                    // "Effective [date]"
                    echo '<td>'. $vacationTimeRow['effectiveDate'] . '</td>';
                
                    // "Alloc by"
                    $insertedById = $vacationTimeRow['personId'];
                    if ($insertedById) {
                        $insertedByPerson = new User($insertedById, $customer); // We are presuming the same customer
                                                // >>>00004 If we ever allow administering across customers, that could become a problem.
                        echo '<td>'. $insertedByPerson->getFormattedName(true) . '</td>';
                    } else {
                        echo '<td>***</td>';
                    }
                    
                    // "Note"
                    echo '<td>'. $vacationTimeRow['note'] . '</td>';
                     
                 echo '</tr>';
             }
    echo     '</tbody>'.
         '</table>'.
         '</div>'; // END div id="showing-history"
    ?>
    <script>
        $(function() {
            $('#hide-history').click(function() {
                $('#showing-history').hide();
                $('#hiding-history').show();
                $('#vacation-alloc-dialog').dialog({height:'auto', width:'auto'});
            });
        });    
    </script>
</div> <!-- id="editvacationtime" -->
<?php
?>
