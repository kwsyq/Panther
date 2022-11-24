<?php 
/*  ajax/ticket_opencount.php
    
    No inputs.
    
    This is one of the few AJAX functions that calls ajaxorigin (in functions.php) to die if the origin is not a page from an authorized server.
    
    Returns JSON for an associative array with the following members:    
        * 'status': "fail" on any failure; "success" on success.
        * 'count': number of open tickets for current user (meaning current user is the person on ticketTo. 
            As of 2019-12-16, this specifically ignores  ticketFrom or receivedBy. NOTE that there can be multiple people for ticketTo).
*/    

require_once '../inc/config.php';
require_once '../inc/access.php';

ajaxorigin();

$data = array();
$data['status'] = 'fail';

$db = DB::getInstance();
            
$tickets = array();

/* BEGIN REPLACED 2019-12-16 JM
$query = " select p1.personId as receivedId ";
$query .= " , t.*, tt.personId as toId, tf.personId as fromId from " . DB__NEW_DATABASE . ".ticket t "; 
$query .= " left join " . DB__NEW_DATABASE . ".ticketTo tt on t.ticketId = tt.ticketId "; 		
$query .= " left join " . DB__NEW_DATABASE . ".ticketFrom tf on t.ticketId = tf.ticketId ";
$query .= " left join " . DB__NEW_DATABASE . ".person p1 on t.receivedBy = p1.personId ";
$query .= " left join " . DB__NEW_DATABASE . ".person p2 on tt.personId = p2.personId ";
$query .= " left join " . DB__NEW_DATABASE . ".person p3 on tf.personId = p2.personId ";
$query .= " where tt.personId = " . intval($user->getUserId()) . " ";
END REPLACED 2019-12-16 JM */
// BEGIN NEW CODE 2019-12-16 JM
$query = " select t.ticketId from " . DB__NEW_DATABASE . ".ticket t "; 
$query .= " left join " . DB__NEW_DATABASE . ".ticketTo as tt on t.ticketId = tt.ticketId "; 		
$query .= " where tt.personId = " . intval($user->getUserId());
// END NEW CODE 2019-12-16 JM
        
if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {            
            $tickets[] = $row;            
        }
    }
} // >>>00002 ignores failure on DB query!

$count = 0;
foreach ($tickets as $ticket) {    
    $open = false;
    
    // The following query gets the latest status: relies on ticketStatusTimeId being assigned in monotonically increasing order
    //  as rows are added to DB table TicketStatusTime
    $query = " select ";
    $query .= " * from " . DB__NEW_DATABASE . ".ticketStatusTime where ticketId = " . intval($ticket['ticketId']) . " ";
    $query .= " order by ticketStatusTimeId desc limit 1 ";
    
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019    
    //$query .= " and ticketStatusId != ";
    //$query .= " (select ticketStatusId from  " . DB__NEW_DATABASE . ".ticketStatus where ticketStatusIdName = 'closed' ) ";
    // END COMMENTED OUT BY MARTIN BEFORE 2019
    
    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();        
            if ($row['ticketStatusId'] != TICKET_STATUS_CLOSED) {
                $open = true;
            }
        }
    } // >>>00002 ignores failure on DB query!
    
    if ($open) {
        $count++;
    }    
}
            
$data['status'] = 'success';
$data['count'] = intval($count);

header('Content-Type: application/json');
echo json_encode($data);
die();

?>