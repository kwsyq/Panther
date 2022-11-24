<?php
/*  ticket.php
    
    EXECUTIVE SUMMARY: Create, view or edit a ticket.
    
    NO PRIMARY INPUT: displays all open tickets.
    
    Optional input $_REQUEST['act']. Only possible value: 'addticket', which causes multiple DB insertions. Associated inputs: 
        * $_REQUEST['suggestJobId']
        * $_REQUEST['suggestFromId']
        * $_REQUEST['suggestToId']
        * $_REQUEST['ticketStatusId']
        * $_REQUEST['ticketMessage']. 
        * Also, implicitly, uses current user personId on insertion.
*/

include './inc/config.php';
include './inc/access.php';

/*
[BEGIN MARTIN COMMENT]
create table ticketView(
    ticketViewId   int unsigned not null primary key auto_increment,
    ticketId       int unsigned,
    personId       int unsigned,
    inserted timestamp not null default now());
[END MARTIN COMMENT]
*/

if ($act == 'addticket') {
    $ok = true;
    $db = DB::getInstance();
    $tuid = 0; // userId for current logged-in user
    if (is_object($user)) {
        if ($user instanceof User) {
            $tuid = $user->getUserId();
        }    
    }
    
    $jobId = isset($_REQUEST['suggestJobId']) ? intval($_REQUEST['suggestJobId']) : 0;
    $fromId = isset($_REQUEST['suggestFromId']) ? intval($_REQUEST['suggestFromId']) : 0;
    $toId = isset($_REQUEST['suggestToId']) ? intval($_REQUEST['suggestToId']) : 0;
    $ticketStatusId = isset($_REQUEST['ticketStatusId']) ? intval($_REQUEST['ticketStatusId']) : 0;
    $ticketMessage = isset($_REQUEST['ticketMessage']) ? $_REQUEST['ticketMessage'] : '';
    $ticketMessage = trim($ticketMessage);
    
    // >>>00028 Multiple insertions, should be transactional.
    $query = "INSERT INTO " . DB__NEW_DATABASE . ".ticket (receivedBy, referenceId, ticketMessage) VALUES (";
    $query .= intval($tuid);
    $query .= ", " . intval($jobId);
    $query .= ", '" . $db->real_escape_string($ticketMessage) . "');";
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDB('1594062403', 'Hard DB error', $db);
        $ok = false;
    }
    if ($ok) {
        $id = $db->insert_id;
        if (!intval($id)) {
            $logger->errorDB('1594062420', 'Didn\'t get an insertId', $db);
            $ok=false;
        }
    }
    
    if ($ok) {
        if ($fromId) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".ticketFrom (ticketId, personId) VALUES (";
            $query .= intval($id);
            $query .= ", " . intval($fromId) . ");";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDB('1594062444', 'Hard DB error', $db);
                $ok=false;
            }
        }
    }
    
    if ($ok) {
        if ($toId) {
            $query = "INSERT INTO " . DB__NEW_DATABASE . ".ticketTo (ticketId, personId) VALUES (";
            $query .= intval($id);
            $query .= ", " . intval($toId) . ");";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDB('1594062456', 'Hard DB error', $db);
                $ok=false;
            }
        }   
    }
    
    if ($ok) {
        // (Code note: Martin opted to do 2 queries here for clarity, rather than unneeded higher performance.)
        $ticketStatusId = 0;
        $query = "SELECT ticketStatusId " . // Reworked JM 2020-02-28, was 'select *', but we only ever look at ticketStatusId 
                 "FROM " . DB__NEW_DATABASE . ".ticketStatus WHERE ticketStatusIdName = 'received';";
        $result = $db->query($query);
        if ($result) {
            if ($row = $result->fetch_assoc()) {
                $ticketStatusId = intval($row['ticketStatusId']);
                if (!$ticketStatusId) {
                    $logger->errorDB('1594062575', 'ticketStatus "received" has ticketStatusId 0', $db);
                    $ok=false;
                }
            } else {
                $logger->errorDB('1594062580', 'ticketStatus table missing ticket status "received"', $db);
                $ok=false;
            }
        } else {
            $logger->errorDB('1594062501', 'Hard DB error', $db);
            $ok=false;
        }
    }
    
    if ($ok) {
        $query = "INSERT INTO " . DB__NEW_DATABASE . ".ticketStatusTime (ticketId, ticketStatusId) VALUES (";
        $query .= intval($id);				
        $query .= ", " . intval($ticketStatusId) . ");";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDB('1594062530', 'Hard DB error', $db);
        }
    }
    
    // Reload this page
    header("Location: /ticket.php");
}

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Tickets - ".str_replace("'", "\'", CUSTOMER_NAME)."';\n</script>\n";

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
	
<div id="container" class="clearfix">
    <div class="main-content">
        <div class="full-box clearfix">
            <h2 class="heading">TICKETS</h2>
            <br>
            (Displaying most recent first);
            <?php /* BEGIN add allowing closed tickets 2019-09-11 JM
            In accord with http://bt.dev2.ssseng.com/view.php?id=14, added a mechanism to show closed tickets. 
            However, this is probably just a temporary expedient. The way I did it for now always puts ALL closed 
            tickets in the page (>>>00038 which means anyone who wants to see them can examine page source, >>>00021 and which 
            also will not scale well), and effectively adds a show/hide toggle via a checkbox. */
            ?>
            <input type="checkbox" id="show-closed-tickets" name="show-closed-tickets" /> <label for="show-closed-tickets">Show closed tickets</label>
            <script>
                $(function() {
                    // A click-handler for that.
                    $("#show-closed-tickets").change(function() {
                        if ($("#show-closed-tickets").is(':checked')) {
                             $(".closed-ticket").show();
                        } else {
                             $(".closed-ticket").hide();
                        }
                    });
                })
            </script>
            <?php /* END add allowing closed tickets 2019-09-11 JM */ ?>
        
            <a  class="button add show_hide" id="addNewTicket" href="/ticketnew.php">Add</a>
    
            <?php 
            
            $db = DB::getInstance();
            
            $tickets = array();
            
            // Select tickets in reverse chronological order (relying on the fact that ticketId increases monotonically over time)
            // Select all the columns of DB table Ticket, plus:
            //  * toId - personId from DB table TicketTo, can be multiple per ticket
            //  * fromId - personId from DB table TicketFrom, can be multiple per ticket
            // Because toId & fromId can be multiple per ticket, all relevant combinations of these will be returned
            // >>>00004: not limited to current customer, presumably should be.
            /* BEGIN REPLACED 2020-07-06 JM
            $query = "SELECT p1.personId AS receivedId, ";
            $query .= "t.*, tt.personId AS toId, tf.personId AS fromId FROM " . DB__NEW_DATABASE . ".ticket t ";
            // END REPLACED 2020-07-06 JM
            */
            // BEGIN REPLACEMENT 2020-07-06 JM
            $query = "SELECT t.ticketId, t.receivedBy, t.referenceId, t.ticketMessage, tt.personId AS toId, tf.personId AS fromId ";
            $query .= "FROM " . DB__NEW_DATABASE . ".ticket t ";
            // END REPLACEMENT 2020-07-06 JM            
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".ticketTo tt ON t.ticketId = tt.ticketId "; 		
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".ticketFrom tf ON t.ticketId = tf.ticketId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".person p1 ON t.receivedBy = p1.personId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".person p2 ON tt.personId = p2.personId ";
            $query .= "LEFT JOIN " . DB__NEW_DATABASE . ".person p3 ON tf.personId = p2.personId ";
            $query .= "ORDER BY t.ticketId DESC;";
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()){
                    $tickets[] = $row;
                }
            } else {
                $logger->errorDB('1594063998', 'Hard DB error', $db);
            }

            foreach ($tickets as $tkey => $ticket) {
                // Select latest status for each ticket
                $query = "SELECT ts.ticketStatusIdName, ts.ticketStatusName "; // Reworked JM 2020-07-06, was "select ts.*, tst.*" even though we care about only two columns
                $query .= "FROM " . DB__NEW_DATABASE . ".ticketStatusTime tst  ";
                $query .= "JOIN " . DB__NEW_DATABASE . ".ticketStatus ts ON tst.ticketStatusId = ts.ticketStatusId  ";                
                $query .= "WHERE tst.ticketId = " . intval($ticket['ticketId']) . " ";
                $query .= "ORDER BY tst.statusTime DESC LIMIT 1;";
                
                $result = $db->query($query);
                if ($result) {
                    if ($row = $result->fetch_assoc()) {
                        $tickets[$tkey]['time'] = $row; // >>>00012: 'time' is not the most mnemonic, something like "lateststatus" would be clearer
                    }
                } else {
                    $logger->errorDB('1594064026', 'Hard DB error', $db);
                }                
            }
            
            foreach ($tickets as $tkey => $ticket) {
                // Select original "received" status for each ticket; that would always be from the initial insertion,
                // which uses the $act == 'addticket' code above
                $query = "SELECT tst.statusTime "; // Reworked JM 2020-07-06, was "select ts.*, tst.*" even though we care about only one column
                $query .= "FROM " . DB__NEW_DATABASE . ".ticketStatusTime tst  ";
                $query .= "JOIN " . DB__NEW_DATABASE . ".ticketStatus ts ON tst.ticketStatusId = ts.ticketStatusId  ";
                $query .= "WHERE tst.ticketId = " . intval($ticket['ticketId']) . " ";
                $query .= "AND ts.ticketStatusIdName = 'received' ";
                $query .= "ORDER BY tst.statusTime ASC LIMIT 1;";
            
                if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $tickets[$tkey]['receivetime'] = $row; // >>>00012: 'receivetime' is not the most mnemonic, something like "receivestatus" would be clearer
                    }
                }            
            }
            
            // As noted above, we can have more than one "to" or "from" for the same ticket; organize that.
            $froms = array();
            $tos = array();
            
            foreach ($tickets as $ticket) {                
                $fp = new Person($ticket['fromId']);
                $tp = new Person($ticket['toId']);                
                
                // BEGIN ADDED 2019-12-02 JM: initialize array before using it! 
                if (!isset($froms[$ticket['ticketId']])) {
                    $froms[$ticket['ticketId']] = array();
                }
                // END ADDED 2019-12-02 JM
                $froms[$ticket['ticketId']][$ticket['fromId']] = array(
                        'id' => $ticket['fromId'],
                        'formattedName' => $fp->getFormattedName()
                        );
                
                // BEGIN ADDED 2019-12-02 JM: initialize array before using it! 
                if (!isset($tos[$ticket['ticketId']])) {
                    $tos[$ticket['ticketId']] = array();
                }
                // END ADDED 2019-12-02 JM
                $tos[$ticket['ticketId']][$ticket['toId']] = array(
                        'id' => $ticket['toId'],
                        'formattedName' => $tp->getFormattedName()
                        );
            }
            
            $lastTicketId = 0; // so we can track when we transition from one ticket to another
            
            echo '<table border="1" cellpadding="0" cellspacing="0">';
            echo '<tr>';
                echo '<th>Received By</th>';
                echo '<th>Job</th>';
                echo '<th>From</th>';
                echo '<th>To</th>';
                echo '<th>Msg</th>';
                echo '<th>Stat</th>';
                echo '<th>Age</th>';
                echo '<th>&nbsp;</th>';                
            echo '</tr>';
            
            foreach ($tickets as $ticket) {
                $ticketStatusIdName = '';
                
                // If we have a status for the ticket, get the name of that status 
                if (isset($ticket['time'])) {
                    $time = $ticket['time'];
                    if (is_array($time)){
                        $ticketStatusIdName = $time['ticketStatusIdName'];
                    }
                }
                
                // if ($ticketStatusIdName != 'closed') { // REMOVED BECAUSE allowing closed tickets 2019-09-11 JM
                    if ($lastTicketId != $ticket['ticketId']) {
                        /* OLD CODE REMOVED BECAUSE allowing closed tickets 2019-09-11 JM
                        echo '<tr>';
                        END OLD CODE REMOVED BECAUSE allowing closed tickets 2019-09-11 JM
                        BEGIN REPLACEMENT CODE 2019-09-11 JM
                        */
                        if ($ticketStatusIdName == 'closed') {
                            echo '<tr class="closed-ticket" style="display:none">';
                        } else {
                            echo '<tr>';
                        }
                        /* END REPLACEMENT CODE 2019-09-11 JM */
                            // "Received By": formatted name, linked to page for this person
                            echo '<td>';
                            if (intval($ticket['receivedBy'])) {                                
                                $u = new Person($ticket['receivedBy']);                                
                                echo '<a  id="receivedBy'.$u->getPersonId().'"  href="' . $u->buildLink() . '">' . $u->getFormattedName() . '</a>';                                
                            }                        
                            echo '</td>';
                            
                            // "Job": Job Number, linked to page for job
                            echo '<td>';                                
                                if(intval($ticket['referenceId'])) {
                                    $j = new Job($ticket['referenceId']);
                                    echo $j->getName() . '&nbsp;(<a  id="reference'.$j->getJobId().'" href="' . $j->buildLink() . '">' , $j->getNumber() . '</a>)';
                                }                            
                            echo '</td>';
                            
                            // "From": formatted name, linked to page for this person; if multiple people, <BR> between people 
                            echo '<td>';
                                if (isset($froms[$ticket['ticketId']])){
                                    $fs = $froms[$ticket['ticketId']];
                                    if (is_array($fs)){
                                        $fs = array_values($fs);
                                        foreach ($fs as $fskey => $f) {
                                            if ($fskey) {
                                                // not the first
                                                echo '<br>';
                                            }
                                            $p = new Person($f['id']);
                                            echo '<a id="ticketPersonFrom'.$p->getPersonId().'" href="' . $p->buildLink() . '">' . $f['formattedName'] . '</a>';
                                        }
                                    } else {
                                        echo '&nbsp;';
                                    }
                                } else {
                                    echo '&nbsp;';
                                }
                            echo '</td>';
                            
                            // "To": formatted name, linked to page for this person; if multiple people, <BR> between people
                            echo '<td>';
                                if (isset($tos[$ticket['ticketId']])){
                                    $ts = $tos[$ticket['ticketId']];
                                    if (is_array($ts)){
                                        $ts = array_values($ts);
                                        foreach ($ts as $tskey => $t){
                                            if ($tskey) {
                                                // not the first
                                                echo '<br>';
                                            }										
                                            $p = new Person($t['id']);
                                            echo '<a id="ticketPersonTo'.$p->getPersonId().'" href="' . $p->buildLink() . '">' . $t['formattedName'] . '</a>';                                            
                                        }
                                    } else {
                                        echo '&nbsp;';
                                    }
                                } else {
                                    echo '&nbsp;';
                                }
                            echo '</td>';
                            
                            // "Msg"
                            echo '<td>' . $ticket['ticketMessage'] . '</td>';
                            
                            // "Stat": ticket status
                            echo '<td>';                            
                                $disp = '';
                            
                                if (isset($ticket['time'])) {
                                    $time = $ticket['time'];
                                    if (is_array($time)){
                                        $disp = $time['ticketStatusName'];                                        
                                    }
                                }
                                echo $disp;
                            echo '</td>';
                            
                            // "Age": how long since status was set.
                            echo '<td>';                            
                                $disp = '';
                            
                                if (isset($ticket['receivetime'])) {
                                    $time = $ticket['receivetime'];
                                    if (is_array($time)){
                                        if (isset($time['statusTime'])) {    
                                            $genesisDT = '';  // >>>00007 not used
                                            $deliveryDT = '';  // >>>00007 not used
                                            $ageDT = ''; // >>>00007 not used
                                            
                                            if ($time['statusTime'] != '0000-00-00 00:00:00'){
                                                $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $time['statusTime']);
                                                $dt2 = new DateTime;
                                                $interval = $dt1->diff($dt2);
                                                $disp = $interval->format('%dd %hh');
                                            } else {
                                                $disp = '&mdash;';
                                            }
                                        }
                                    }
                                }                            
                                echo $disp;                            
                            echo '</td>';
                            
                            // (no header): link captioned "view" to open this ticket in ticketedit.php to edit it                             
                            echo '<td>[<a id="ticketEdit'.$ticket['ticketId'].'" href="/ticketedit.php?ticketId=' . $ticket['ticketId'] . '">view</a>]</td>';
                        echo '</tr>';
                    } // END if ($lastTicketId != $ticket['ticketId'])
                    // else it is the same ticket with a different to/from pair
                    
                    $lastTicketId = $ticket['ticketId'];
                // }  // REMOVED BECAUSE allowing closed tickets 2019-09-11 JM                
            } // END foreach ($tickets...
            
            echo '</table>';

/* BEGIN add allowing closed tickets 2019-09-11 JM */
            echo "\n"; 
?>
            <script>
                // Initial show/hide when this loads; will run once jQuery is fully loaded
                $(function() {
                    if ($("#show-closed-tickets").is(':checked')) {
                         $(".closed-ticket").show();
                    } else {
                         $(".closed-ticket").hide();
                    }
                })
            </script>
<?php /* END add allowing closed tickets 2019-09-11 JM */
            
            /*
            // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]            
            $db = DB::getInstance();	
            
            echo '<table border="0" cellpadding="3" cellspacing="0">';
    
            echo '<tr>';
                echo '<td>To<td>';
                echo '<td>From<td>';
                echo '<td width="80%">Body<td>';
            echo '</tr>';
            
            $query = " select * from " . DB__NEW_DATABASE . ".inboundSms ";
            $query .= " order by inboundSmsId ";
            
            if ($result = $db->query($query)) {
                if ($result->num_rows > 0){
                    while ($row = $result->fetch_assoc()){
                        $inbounds[] = $row;
                    }
                }
            }
            
            foreach ($inbounds as $ikey => $inbound){
                echo '<tr>';
                    echo '<td>' . $inbound['didTo'] . '<td>';				
                    echo '<td>' . $inbound['didFrom'] . '<td>';
                    echo '<td>' . $inbound['body'] . '<td>';
                    echo '<td>';
                        $media = unserialize($inbound['media']);
                        if (is_array($media)){
                            foreach ($media as $mkey  => $m){
                                echo '<img src="' . $m['url'] . '">';
                            }
                        }
                    echo '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            // [END COMMENTED OUT BY MARTIN BEFORE 2019]
            */
            ?>
        </div>
    </div>
</div>

<?php 
include BASEDIR . '/includes/footer.php';
?>