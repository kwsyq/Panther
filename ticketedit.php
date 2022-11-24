<?php
/*  ticketedit.php

    EXECUTIVE SUMMARY: Auxiliary page for ticket.php. Changes an existing ticket 
    (data about which is in several tables). Viewing a ticket with ticketedit.php 
    makes an entry in table ticketView even if no change is made.
    
    Page offers dropdowns to select employees in various roles, ticketStatus; autocompletion via AJAX. 
    Also a "VIEW HISTORY" section gives a list of Person (formatted name, no link) and Date/Time (m/d/Y H:i:s) 
    for each time the ticket has been viewed (including right now), most recent first.

    PRIMARY INPUT: $_REQUEST['ticketId'].

    Optional $_REQUEST['act']. Possible values:    
        'changestatus' (requires $_REQUEST['ticketStatusId'])
        'updatemessage' (requires $_REQUEST['message'])
        'addfrom' (requires $_REQUEST['personId'])
        'deletefrom' (requires $_REQUEST['personId'])
        'deleteto' (requires $_REQUEST['personId'])
        
    It appears that the addTo action is handled entirely differently, but AJAX.
*/

include './inc/config.php';
include './inc/access.php';

$db = DB::getInstance();

$ticketId = isset($_REQUEST['ticketId']) ? intval($_REQUEST['ticketId']) : 0;

// >>>00002 Seems to me that right here at the top we should validate $ticketId
// and log and display an error & get out if it's invalid.

// NOTE that we log in ticketView both when we first hit this page and on any action.

if (intval($ticketId)) {
    $query  = "INSERT INTO " . DB__NEW_DATABASE . ".ticketView (ticketId, personId) VALUES (";
    $query .= intval($ticketId);
    $query .= ", " . intval($user->getUserId()) . ");"; // logged-in user
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1594067158', 'Hard DB error', $db);
    }
}

if ($act == 'changestatus') {
    $ticketStatusId = isset($_REQUEST['ticketStatusId']) ? intval($_REQUEST['ticketStatusId']) : 0;
    // >>>00002 note that we never validate $ticketStatusId 
    
    if (intval($ticketId) && intval($ticketStatusId)) {
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".ticketStatusTime (ticketId, ticketStatusId) VALUES (";
        $query .= intval($ticketId);
        $query .= ", " . intval($ticketStatusId) . ");";        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1594067200', 'Hard DB error', $db);
        }        
    }
    
    // Reload page for this ticket, no further action
    header("Location: ?ticketId=" . intval($ticketId));
}

// Replace (not just add to) main text associated with ticket
if ($act == 'updatemessage') {
    $message = isset($_REQUEST['message']) ? $_REQUEST['message'] : '';
    $message = trim($message);
    
    $query = "UPDATE " . DB__NEW_DATABASE . ".ticket ";
    $query .= "SET ticketMessage = '" . $db->real_escape_string($message) . "' "; 
    $query .= "WHERE ticketId = " . intval($ticketId) . ";";
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1594067240', 'Hard DB error', $db);
    }
    
    // Reload page for this ticket, no further action
    header("Location: ?ticketId=" . intval($ticketId));	
}

// NOTE that there can be multiple "from" values
if ($act == 'addfrom') {	
	$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;	
	if (existPersonId($personId)) {
		$query = "SELECT * FROM " . DB__NEW_DATABASE . ".ticketFrom WHERE ticketId = " . intval($ticketId) . " AND personId = " . intval($personId) . ";";
		$result = $db->query($query);	
		if ($result) {
			if ($result->num_rows == 0) {
                $query = "INSERT INTO " . DB__NEW_DATABASE . ".ticketFrom (ticketId, personId) VALUES (";
                $query .= intval($ticketId);
                $query .= ", " . intval($personId);
                $query .= ");";
                $result = $db->query($query);
                if (!$result) {
                    $logger->errorDb('1594067340', 'Hard DB error', $db);
                }
            }
		} else {
            $logger->errorDb('1594067377', 'Hard DB error', $db);
		}	
	}
	
	// Reload page for this ticket, no further action
	header("Location: ?ticketId=" . intval($ticketId));	
}

if ($act == 'deletefrom') {
    $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;

	$query = "DELETE FROM " . DB__NEW_DATABASE . ".ticketFrom ";
	$query .= "WHERE ticketId = " . intval($ticketId) . " ";
	$query .= "AND personId = " . intval($personId) . ";";
	$result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1594067417', 'Hard DB error', $db);
    }

	// Reload page for this ticket, no further action
	header("Location: ?ticketId=" . intval($ticketId));
}

if ($act == 'deleteto') {
    $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
	
	$query = "DELETE FROM " . DB__NEW_DATABASE . ".ticketTo ";
	$query .= "WHERE ticketId = " . intval($ticketId) . " ";
	$query .= "AND personId = " . intval($personId) . ";";
	$result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1594067452', 'Hard DB error', $db);
    }
	
	// Reload page for this ticket, no further action
	header("Location: ?ticketId=" . intval($ticketId));	
}

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='View/edit ticket - ".str_replace("'", "\'", CUSTOMER_NAME)."';\n</script>\n";

$crumbs = new Crumbs(null, $user);


/*
[BEGIN MARTIN COMMENT]
rray
(
  [act] => addticket
    [suggestJobId] => 2927
    [suggestFromId] => 1400
    [suggestToId] => 1865
    [personName] => Biddle Dave
    [personNames] => Moeller Zach
    [jobSuggest1] => s16EXP (multistory experiment)
    [jobSuggest2] => s16EXP (multistory experiment)
    [jobSuggest3] => s16
    [ticketStatusId] => 1
)
[END MARTIN COMMENT]
*/

?>

<script src="/js/jquery.autocomplete.min.js"></script>

<style>
.autocomplete-wrapper { margin: 2px auto 2px; max-width: 600px; }
.autocomplete-wrapper label { display: block; margin-bottom: .75em; color: #3f4e5e; font-size: 1.25em; }
.autocomplete-wrapper .text-field { padding: 0 0px; width: 100%; height: 40px; border: 1px solid #CBD3DD; font-size: 1.125em; }
.autocomplete-wrapper ::-webkit-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
.autocomplete-wrapper :-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
.autocomplete-wrapper ::-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
.autocomplete-wrapper :-ms-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }

.autocomplete-suggestions { overflow: auto; border: 1px solid #CBD3DD; background: #FFF; }

.autocomplete-suggestion { overflow: hidden; padding: 5px 15px; white-space: nowrap; }

.autocomplete-selected { background: #F0F0F0; }

.autocomplete-suggestions strong { color: #029cca; font-weight: normal; }
</style>

<?php
    // Comma-separated list of all employees. Example:
    // var employees=[{'name':'Skinner, Ron', 'personId':587},{'name':'Fleming, Damon', 'personId':588},...];
    
    $employees = $customer->getEmployees(EMPLOYEEFILTER_CURRENTLYEMPLOYED);

    echo '<script>';
    echo 'var employees=[';
    foreach ($employees as $ekey => $employee){		
        if ($ekey) {
            // Not the first
            echo ',';
        }
        
        echo '{';
            echo "'name':'" . $employee->getLastName() . ", " . $employee->getFirstName() . "'";
            echo ",'personId':" . $employee->getUserId();
        echo '}';		
    }
    
    echo '];';	
    echo '</script>';
?>

<script>
// Called when user clicks on the '+' link for "To".
// Adds an HTML SELECT (just before the table of "to" people) 
// to let user choose from a list of all employees.
// (nothing here to eliminate those who are already on the "To" list).
// When user changes the selection, calls /ajax/ticket_addto.php, which
// makes an appropriate insertion in DB table TicketTo. On success
// we remove the SELECT and (whether success or not) we reload this page.
var addTo = function() {
    var ticketId = <?php echo $ticketId; ?>;  // Per http://bt.dev2.ssseng.com/view.php?id=40, JM 2019-10-18 fixed syntax error by adding semicolon.
    
    // Per http://bt.dev2.ssseng.com/view.php?id=40, don't allow adding two of these boxes at once - JM 2019-10-18
    // BEGIN ADDED JM 2019-10-18
    if (! $('#toselect').length) {
    // END ADDED JM 2019-10-18
        $("#tos").append(
            $('<br>', {
            })
        );
    
        $("#tos").append(
            $('<select>', {
                id: 'toselect'
            })
        );                                                   
        
        $('#toselect').append($('<option>', {
            value: '0',
            text: '-- select person --'
        }));
        
        $("#toselect").on("change", function(event) {
            $.ajax({
                type: "POST",
                url: '/ajax/ticket_addto.php',
                data: {ticketId : ticketId, personId : $(this).find(":selected").val()},
                success: function(data, textStatus, jqXHR) {
                    if (data['status']){
                        if (data['status'] == 'success') { // [T000016] 
                            $("#tos").html('');
                        }
                    }
                    // Reload this page.
                    location.reload();
                }
            });
        });
            
        for (var i = 0; i < employees.length; i++) {
            $('#toselect').append($('<option>', {
                value: employees[i].personId,
                text: employees[i].name
            }));           
        }
    // BEGIN ADDED JM 2019-10-18
    } // END if (! $('#toselect').length) {
    // END ADDED JM 2019-10-18
}

// Called when user clicks on the '+' link for "From".
// Adds an HTML INPUT (just before the table of "from" people) 
//  to let user choose "from". INPUT uses 
//  devbridgeAutocomplete (https://github.com/devbridge/jQuery-Autocomplete). 
// Selection can be any person known to the system, not just employees.
//  >>>00004 it looks like as of 2019-04-05 there is nothing here to limit
//  this to the current customer.
// On select action, submits via $('#addfromform').submit(), so self-submits
//  with appropriate ticketId, person, and act="addfrom".
var addFrom = function() {
    var ticketId = <?php echo $ticketId; ?>;  // Per http://bt.dev2.ssseng.com/view.php?id=40, JM 2019-10-18 fixed syntax error by adding semicolon.
    // Per http://bt.dev2.ssseng.com/view.php?id=40, don't allow adding two of these boxes at once - JM 2019-10-18
    // BEGIN ADDED JM 2019-10-18
    if (! $('#frominput').length) {
    // END ADDED JM 2019-10-18
        $("#froms").append(
            $('<br>', {
            })
        );    
    
        $("#froms").append(
            $('<input>', {
                id: 'frominput',
                type: 'text',
                size:'30'
            })
        );
    
        $('#frominput').devbridgeAutocomplete({
            noCache:true,
            serviceUrl: '/ajax/autocomplete_person.php',
            onSelect: function (suggestion) {
                $('#personId').val(suggestion.data);
                $('#addfromform').submit();
            },
            paramName:'q'
        });
    // BEGIN ADDED JM 2019-10-18
    } // END if (! $('#frominput').length) {
    // END ADDED JM 2019-10-18
}
	
</script>
<div id="container" class="clearfix">
    <div class="main-content">
        <?php /* completely invisible form, used as part of addFrom sequence */ ?>
        <form name="addfromform" id="addfromform" action="" method="POST">
            <input type="hidden" name="ticketId" value="<?php echo intval($ticketId); ?>">
            <input type="hidden" name="personId" value="" id="personId">
            <input type="hidden" name="act" value="addfrom">
        </form>
        <?php /* REMOVED 2019-10-18, closed an extra FORM that was never opened */ 
        // </form>  
        ?>
        
        <div class="full-box clearfix">
            <h2 class="heading">View/EDIT TICKET</h2>		
            <h2>Ticket</h2>			
            <?php
            $tickets = array(); // >>>00007 never used
            $meta = array(); // Arbitrary row for data that shouldn't change across rows
            
            // For this ticket, select all the columns of DB table Ticket, plus:
            //  * receivedId - JM: seems to be the same as receivedBy, so not sure why we add this; one per ticket
            //  * toId - personId from DB table TicketTo, can be multiple per ticket
            //  * fromId - personId from DB table TicketFrom, can be multiple per ticket
            // Because toId & fromId can be multiple per ticket, all relevant combinations of these will be returned
            // >>>00004: not limited to current customer, presumably should be.
            $query = "SELECT p1.personId AS receivedId ";
            $query .= ", t.receivedBy, t.referenceId, t.ticketMessage";
            $query .= ", tt.personId AS toId, tf.personId AS fromId FROM " . DB__NEW_DATABASE . ".ticket t ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".ticketTo tt ON t.ticketId = tt.ticketId "; 		
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".ticketFrom tf ON t.ticketId = tf.ticketId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".person p1 ON t.receivedBy = p1.personId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".person p2 ON tt.personId = p2.personId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".person p3 ON tf.personId = p2.personId ";
            $query .= "WHERE t.ticketId = " . intval($ticketId) . ";";
            $result = $db->query($query);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                    $meta = $row; // Arbitrary row for data that shouldn't change across rows; inevitably, ends up with the last row                                      
                }
            } else {
                $logger->errorDb('1594067503', 'Hard DB error', $db);
            }

            // We can have more than one "to" or "from" for the same ticket; organize that.
            $froms = array();
            $tos = array();
                
            foreach ($rows as $row) {
                if (intval($row['fromId'])){
                    $fp = new Person($row['fromId']);
                    $froms[$row['fromId']] = array(
                        'id' => $row['fromId'],
                        'formattedName' => $fp->getFormattedName()
                    );
                }
                
                if (intval($row['toId'])){
                    $tp = new Person($row['toId']);
                    $tos[$row['toId']] = array(
                            'id' => $row['toId'],
                            'formattedName' => $tp->getFormattedName()
                    );
                }            
            }

            echo '<table border="1" cellpadding="0" cellspacing="0">';
                // 2 rows for "Received By", formatted name, link to Person page
                echo '<tr>';
                    echo '<th colspan="3">Received By</th>';
                echo '</tr>';
                echo '<tr>';
                    echo '<td colspan="3">';                    
                        if (intval($meta['receivedBy'])) {
                            $p = new Person($meta['receivedBy']);
                            echo '<a id="receivedBy'.$p->getPersonId().'" href="' . $p->buildLink() . '">' . $p->getFormattedName() . '</a>';
                        }
                    echo '</td>';
                echo '</tr>';                

                // Column headers "From" and "To" each have "[+]" to add a new person; "Job" is just displa
                echo '<tr>';
                    echo '<th>From&nbsp;&nbsp;[<a id="addFrom" style="color:#ffffff;text-decoration: none;" href="javascript:addFrom();">+</a>]</th>';
                    echo '<th>To&nbsp;&nbsp;[<a id="addTo" style="color:#ffffff;text-decoration: none;" href="javascript:addTo();">+</a>]</th>';
                    echo '<th>Job</th>';
                echo '</tr>';
                echo '<tr>';
                    // "From": nested table, one row per "from" person. On each row:
                    //   * Delete icon leads to deletion action,
                    //   * Formatted name is a link to Person page
                    echo '<td><div id="froms">';
                        if (is_array($froms)){
                        echo '<table border="0" cellpadding="0" cellspacing="0">';
                            $froms = array_values($froms);
                            foreach ($froms as $f) {
                                $p = new Person($f['id']);
                                echo '<tr>';
                                echo '<td><a id="deleteFrom' . intval($ticketId) . '" href="ticketedit.php?act=deletefrom&ticketId=' . intval($ticketId) . '&personId=' . intval($p->getPersonId()) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_delete_16x16.png" /></a></td>';
                                echo '<td width="100%"><a id="personLink'.$p->getPersonId().'" href="' . $p->buildLink() . '">' . $f['formattedName'] . '</a></td>';
                                echo '</tr>';						
                            }
                        echo '</table>';
                    } else {
                        echo '&nbsp;';
                    }
                    echo '</div>';
                    echo '</td>';
                    
                    // "To": nested table, one row per "to" person. On each row:
                    //   * Delete icon leads to deletion action,
                    //   * Formatted name is a link to Person page
                    echo '<td><div id="tos">';
                        if (is_array($tos)){
                            echo '<table border="0" cellpadding="0" cellspacing="0">';
                            $tos = array_values($tos);
                            foreach ($tos as $t){
                                $p = new Person($t['id']);
                                echo '<tr>';
                                echo '<td><a id="deleteTo' . intval($ticketId) . '" href="ticketedit.php?act=deleteto&ticketId=' . intval($ticketId) . '&personId=' . intval($p->getPersonId()) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_delete_16x16.png" /></a></td>';
                                echo '<td width="100%"><a id="linkPerson'.$p->getPersonId().'" href="' . $p->buildLink() . '">' . $t['formattedName'] . '</a></td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        } else {
                            echo '&nbsp;';
                        }
                    echo '</div></td>';
                    
                    
                    echo '<td>';
                        // $meta['referenceId'] is this ticket's reference into DB table Job.
                        // Display Job Number, link to appropriate Job page.
                        if (intval($meta['referenceId'])) {
                            $j = new Job($meta['referenceId']);
                            echo $j->getName() . '&nbsp;(<a id="referenceJob'.$j->getJobId().'" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a>)';
                        }
                    echo '</td>';                    
                echo '</tr>';                
                
                // Allow updating message in a TEXTAREA; button to self-submit.
                echo '<tr>';
                    echo '<th colspan="3">Message</th>';
                echo '</tr>';

                // FORM spans multiple rows. Not pretty, but probably OK
                // Per http://bt.dev2.ssseng.com/view.php?id=35, JM 2019-10-18 got rid of duplicate HTML ID ("time bomb"!) which
                //   was unused; similarly for unused HTML form name
                // OLD CODE REPLACED 2019-10-18 JM
                // echo '<form name="addfromform" id="addfromform" action="" method="POST">';
                // BEGIN NEW CODE 2019-10-18 JM
                echo '<form action="" method="POST">';
                // END NEW CODE 2019-10-18 JM
                    echo '<input type="hidden" name="ticketId" id="ticketIdForm" value="' . intval($ticketId) . '">';
                    echo '<input type="hidden" name="act" value="updatemessage">';                    
                    
                    echo '<tr>';
                        echo '<td colspan="3" align="center"><center>';
                            echo '<textarea name="message" id="message" cols="80" rows="5" width="100%">';
                            echo $meta['ticketMessage'];
                            echo '</textarea>';                    
                        echo '</center></td>';
                    echo '</tr>';			
        
                    echo '<tr>';
                        echo '<td colspan="3" align="center">';
                            echo '<center><input type="submit" id="updateMessage" value="update message"></center>';
                        echo '</td>';
                    echo '</tr>';
                echo '</form>';
            echo '</table>';
            
            // "Status Times": Trace history of status for this ticket; forward chronological order. 
            ?>
            <h2>Status Times</h2>
            
            <?php             
            $times = array();
            
            $query = "SELECT tst.statusTime, ts.ticketStatusName FROM " . DB__NEW_DATABASE . ".ticketStatusTime tst ";
            $query .= "JOIN " . DB__NEW_DATABASE . ".ticketStatus ts ON tst.ticketStatusId = ts.ticketStatusId ";
            $query .= "WHERE tst.ticketId = " . intval($ticketId) . " ";
            $query .= "ORDER BY tst.statusTime ASC;";
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {            
                    $times[] = $row;                                    
                }
            } else {
                $logger->errorDb('1594067567', 'Hard DB error', $db);
            }
            
            echo '<table border="0" cellpadding="3" cellspacing="1">';
                echo '<tr>';
                    echo '<th>Status</th>';
                    echo '<th>Time</th>';
                echo '</tr>';				
                foreach ($times as $time) {
                    echo '<tr>';
                        echo '<td>' . $time['ticketStatusName'] . '</td>';
                        echo '<td>' . date("M j Y H:i",strtotime($time['statusTime'])) . '</td>';
                    echo '</tr>';
                }
            echo '</table>';
            
            // "Change Status": give it a new status; select from all possible ticket stuatuses,
            // self-submit on changed value.
            ?>            
            <h2>Change Status</h2>
            <form name="statchangeform" id="statChangeForm" action="ticketedit.php" method="POST">
                <input type="hidden" name="act" value="changestatus">
                <input type="hidden" name="ticketId" value="<?php echo intval($ticketId); ?>">
                
                <select name="ticketStatusId" onChange="this.form.submit();"><option value="0">-- chose status --</option>
                    <?php
                    $query = "SELECT ticketStatusId, ticketStatusName ";
                    $query .= "FROM " . DB__NEW_DATABASE . ".ticketStatus ORDER BY displayOrder ASC;";
                    $result = $db->query($query);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<option value="' . $row['ticketStatusId'] . '">' . $row['ticketStatusName'] . '</option>';            
                        }
                    } else {
                        $logger->errorDb('1594067600', 'Hard DB error', $db);
                    }
                    ?>		
                </select>			
            </form>	
        
            <p>
            <?php /* "View History": history of who ahs viewed this ticket. Backward chronlogical order,
                     because ticketViewId increases monotonically over time. */ ?>
            <h2>View History</h2>
        
            <?php 
            $views = array();
            $query = "SELECT personId, inserted ";
            $query .= "FROM " . DB__NEW_DATABASE . ".ticketView ";
            $query .= "WHERE ticketId = " . intval($ticketId) . " ";
            $query .= "ORDER BY ticketViewId DESC;";
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $views[] = $row;
                }
            } else {
                $logger->errorDb('1594067635', 'Hard DB error', $db);
            }
            
            echo '<table border="0" cellpadding="0" cellspacing="0">';
                echo '<tr>';
                    echo '<th>Person</th>';
                    echo '<th>Date/Time (recent first)</th>';
                echo '</tr>';
                
                foreach ($views as $view) {
                    echo '<tr>';
                        $p = new Person($view['personId']);                    
                        echo '<td>' . $p->getFormattedName(1) . '</td>';
                        $date = new DateTime($view['inserted']);
                        $date->setTimezone(new DateTimeZone('America/Los_Angeles'));
                        echo '<td>' . $date->format('m/d/Y H:i:s') . '</td>';
                    echo '</tr>';				
                }
            echo '</table>';
            ?>
        
        </div>
    </div>
</div>
<script>
    $('#fromresult').devbridgeAutocomplete({
        noCache:true,		
        appendTo: "#fromappend",
        width:"280",
        height:"200",	
        serviceUrl: '/ajax/autocomplete_joblocation.php',
        onSelect: function (suggestion) {            
            $('#autocompleteJob1').devbridgeAutocomplete().hide();
            $('#autocompleteJob1').val(suggestion.value);
            $('#autocompleteJob2').devbridgeAutocomplete().hide();
            $('#suggestJobId').val(suggestion.data);
        },
        paramName:'q'    
    });
</script>

<?php 
include BASEDIR . '/includes/footer.php';
?>