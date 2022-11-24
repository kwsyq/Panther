<?php
/*  statement.php
    EXECUTIVE SUMMARY: Displays invoices for and payments from a given company in a given time range.
    
    PRIMARY INPUT: $_REQUEST['companyId'].
    
    OPTIONAL INPUTS: $_REQUEST['fromDate'], $_REQUEST['toDate']. If present, these come from self-submission. 
    Format comes from datepicker (so it's locale-sensitive); we expect a form like "10%2F23%2F2018" which is URL-encoded "10/23/2018".
    
    Requires admin-level payment permissions, without that just redirects to panther. 
    Requires $_REQUEST['companyId'], without that redirects to '/' (>>> JM: again, why to two different places?)
*/

include './inc/config.php';
include './inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm){
    // No permission, out of here!
	header("Location: /panther");
}

$companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
if (!intval($companyId)){
    // No companyId, out of here!
	header("Location: /");
}

/* [BEGIN MARTIN COMMENT]

select c.companyName
from company c
join billingProfile bp on c.companyId = bp.companyId
join invoiceBillingProfile ibp on bp.billingProfileId = ibp.billingProfileId
join invoice i on ibp.invoiceId = i.invoiceId
where c.companyId = 828
and i.invoiceDate between '2018-07-01' and '2018-07-20'

select *
from invoicePayment ip where ip.invoiceId in (
select i.invoiceId
from company c
join billingProfile bp on c.companyId = bp.companyId
join invoiceBillingProfile ibp on bp.billingProfileId = ibp.billingProfileId
join invoice i on ibp.invoiceId = i.invoiceId
where c.companyId = 828
)
and ip.inserted between '2018-05-01' and '2018-12-01'

create algorithm = merge
view companyStatement
as
select c.customerId,
       c.companyName,
       j.number,
       j.name,
       j.description,
       j.jobStatusId,
       wo.workOrderId,
       wo.description,
       i.invoiceId
from company c
join job j on c.companyId = j.companyId
join workOrder wo on j.jobId = wo.jobId
join invoice i on wo.workOrderId = i.workOrderId
join invoicePayment on i.invoiceId = ip.invoiceId

[END MARTIN COMMENT]
*/

// [BEGIN MARTIN COMMENT]
//. will need some indexes on the invoiceDate field.
// and also if there ends up being some date field in the payments
// [END MARTIN COMMENT]

include BASEDIR . '/includes/header.php';

// JM 2019-11-15: $cc moved up so we can use it in HTML title
$cc = new Company($companyId);
echo "<script>\ndocument.title = 'Statement for ". str_replace("'", "\'", $cc->getCompanyName()) . "';\n</script>";
?>

<div id="container" class="clearfix">
    <div class="main-content">
        <div class="full-box clearfix">
            <?php
            // Company name as header
            echo '<h1>' . htmlspecialchars($cc->getCompanyName()) . '</h1>';  
            ?>
            <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
            <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
            <?php
    
            // Get optional dates from input, parse them
            $fromDate = isset($_REQUEST['fromDate']) ? $_REQUEST['fromDate'] : '';
            $toDate = isset($_REQUEST['toDate']) ? $_REQUEST['toDate'] : '';
    
            $fromDate = date_parse($fromDate);
            $fromDateField = '';
            if (is_array($fromDate)) {
                if (isset($fromDate['year']) && isset($fromDate['day']) && isset($fromDate['month'])){
                    $fromDateField = intval($fromDate['month']) . '/' . intval($fromDate['day']) . '/' . intval($fromDate['year']);
                    if ($fromDateField == '0/0/0') {
                        $fromDateField = '';
                    }
                }
            }
    
            $toDate = date_parse($toDate);
            $toDateField = '';
    
            if (is_array($toDate)){
                if (isset($toDate['year']) && isset($toDate['day']) && isset($toDate['month'])) {        
                    $toDateField = intval($toDate['month']) . '/' . intval($toDate['day']) . '/' . intval($toDate['year']);
                    if ($toDateField == '0/0/0') {
                        $toDateField = '';
                    }
                }
            }
            
            echo '<center>';
                // Form lets user change dates, self-submit those
                // >>>00006 might want to do something to kill the rest of the page once either
                //  date is changed so it isn't displaying data with the wrong date.
                echo '<form name="statementform" id="statementForm" method="get" action="">';
                    echo '<input type="hidden" name="companyId" value="' . intval($companyId) . '">';
                    // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                    //echo '<input type="hidden" name="toDate" value="' . htmlspecialchars($toDateField) . '">';
                    //echo '<input type="hidden" name="fromDate" value="' . htmlspecialchars($fromDateField) . '">';
                    // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                    echo 'From:<input type="text" name="fromDate" class="datepicker" id="fromDate" value="' . htmlspecialchars($fromDateField) . '">';
                    echo 'To:<input type="text" name="toDate" class="datepicker" id="toDate" value="' . htmlspecialchars($toDateField) . '">';
                    echo '<input type="submit" id="submitStatement" value="go">';
                echo '</form>';
            echo '</center>';
            
            $db = DB::getInstance();

            // Find all invoices in the relevant date range (SQL 'between' is inclusive of both end dates), ordered by invoiceDate. 
            // Invoices are related to company by a multi-table JOIN.
            // Fills in a multi-dimensional associative array $arranged: top level is date in 'Y-m-d' form, e.g. '2020-06-14'; 
            //  next dimension is the single index 'invoice'; 
            //  then comes a simple numeric array of invoices for that day. 
            //  For each of those, we have an associative array with indexes 'companyName', 'invoiceDate' (formatted per locale), 
            //   'total', 'invoiceId' corresponding to a row returned by the query.
            $from = date("Y-m-d", strtotime($fromDateField));
            $to = date("Y-m-d", strtotime($toDateField));;

            $arranged = array();
            if (strlen($toDateField) && strlen($fromDateField)) {
                $query = " select c.companyName, i.invoiceDate, i.total, i.invoiceId ";
                $query .= " from " . DB__NEW_DATABASE . ".company c ";
                $query .= " join " . DB__NEW_DATABASE . ".billingProfile bp on c.companyId = bp.companyId ";
                $query .= " join " . DB__NEW_DATABASE . ".invoiceBillingProfile ibp on bp.billingProfileId = ibp.billingProfileId ";
                $query .= " join " . DB__NEW_DATABASE . ".invoice i on ibp.invoiceId = i.invoiceId ";
                $query .= " where c.companyId = " . intval($companyId) . " ";
                $query .= " and i.invoiceDate between '" . $db->real_escape_string($from) . "' and '" . $db->real_escape_string($to) . "' ";
                $query .= " order by i.invoiceDate ";
	
                if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $d = strtotime($row['invoiceDate']);
                            $dt = date("Y-m-d", $d);
                            // BEGIN ADDED 2019-12-02 JM: initialize array before using it! 
                            if (!isset($arranged[$dt])) {
                                $arranged[$dt] = array();
                            }
                            if (!isset($arranged[$dt]['invoice'])) {
                                $arranged[$dt]['invoice'] = array();
                            }
                            // END ADDED 2019-12-02 JM
                            $arranged[$dt]['invoice'][] = $row;
                        }
                    }
                } // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
                
                // $dt = date("n/j/Y", $d); // [COMMENTED OUT BY MARTIN BEFORE 2019]
	
                // Similarly, queries for invoice payments for all payments on invoices related to that company 
                //  where the *payments* were made in the relevant time frame. 
                // NOTE that these payments might be for older invoices: this is based on the timestamp inserted in DB table invoicePayment. 
                // These are also added to associative array $arranged: top level, as before, is date in 'Y-m-d' form; 
                //  next dimension is the single index 'payment'; 
                //  then comes a simple numeric array of payments for that day. 
                //  For each of those, we have an associative array giving the canonical representation of the row returned by the query.
                $query = " select * ";
                $query .= " from " . DB__NEW_DATABASE . ".invoicePayment ip where ip.invoiceId in ";
                $query .= "     (";
                $query .= "     select i.invoiceId ";
                $query .= "     from " . DB__NEW_DATABASE . ".company c ";
                $query .= "     join " . DB__NEW_DATABASE . ".billingProfile bp on c.companyId = bp.companyId ";
                $query .= "     join " . DB__NEW_DATABASE . ".invoiceBillingProfile ibp on bp.billingProfileId = ibp.billingProfileId ";
                $query .= "     join " . DB__NEW_DATABASE . ".invoice i on ibp.invoiceId = i.invoiceId ";
                $query .= "     where c.companyId = " . intval($companyId) . " ";
                $query .= "     ) ";
                $query .= " and ip.inserted between '" . $db->real_escape_string($from) . "' and '" . $db->real_escape_string($to) . "' ";
                $query .= " order by ip.inserted ";
                // [BEGIN MARTIN COMMENT]
                // later will need the correct arbitrary date  of the payment.
                // unless its ok to go by the inserted time
                // [END MARTIN COMMENT]
	
                if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $d = strtotime($row['inserted']);
                            $dt = date("Y-m-d", $d);
                            // BEGIN ADDED 2019-12-02 JM: initialize array before using it! 
                            if (!isset($arranged[$dt])) {
                                $arranged[$dt] = array();
                            }
                            if (!isset($arranged[$dt]['payment'])) {
                                $arranged[$dt]['payment'] = array();
                            }
                            // END ADDED 2019-12-02 JM
                            $arranged[$dt]['payment'][] = $row;
                        }
                    }
                }
                
                ksort($arranged); // guarantee that associative array will go in ascending order by key.
            } // END if (strlen($toDateField) && strlen($fromDateField))

            echo '<center>';
                echo '<table border="1" cellpadding="4" cellspacing="1">';
                    echo '<tr>';
                        echo '<td>Date</td>';
                        echo '<td>Job</td>';
                        echo '<td style="text-align:center;">Invoice#</td>';
                        echo '<td style="text-align:center;">Invoice Amount</td>';
                        echo '<td style="text-align:center;">Payment</td>';
                    echo '</tr>' . "\n";
                    $sum = 0;
                    foreach ($arranged as $akey => $day) { // >>>00012 not the best choice of variables, might be better to use something like date => dateData
                        if (array_key_exists('invoice', $day)) {
                            foreach ($day['invoice'] as $item) {
                                $inv = new Invoice($item['invoiceId']);
                                $wo = new WorkOrder($inv->getWorkOrderId());
                                $j = new Job($wo->getJobId());	
                    
                                $d = strtotime($akey);
                                $dt = date("n/j/Y", $d);
        
                                $sum += $item['total'];
                                echo '<tr>';
                                    // "Date"
                                    echo '<td>' . $dt . '</td>';
                                    // "Job": job name
                                    echo '<td>' . $j->getName() . '</td>';
                                    // "Invoice #": invoiceId, primary key to DB table Invoice
                                    echo '<td>' . $item['invoiceId'] . '</td>';
                                    // "Invoice Amount": U.S. currency, w/ dollar sign & 2 digits past the decimal point
                                    echo '<td align="right" style="text-align:right;">$' . number_format($inv->getTriggerTotal(), 2) . '</td>';
                                    // "Payment": blank
                                    echo '<td>&nbsp;</td>';
                                echo '</tr>' . "\n";
                            }
                        }

                        if (array_key_exists('payment', $day)) {
                            foreach ($day['payment'] as $item){
                                $inv = new Invoice($item['invoiceId']);				
                                $wo = new WorkOrder($inv->getWorkOrderId());
                                $j = new Job($wo->getJobId());		
                    
                                $d = strtotime($akey);
                                $dt = date("n/j/Y", $d);
                                
                                // $sum += (-1 * $item['amount']);// REPLACED FOR CLARITY 2020-02-24 JM
                                $sum -= $item['amount']; // REPLACEMENT FOR CLARITY 2020-02-24 JM
                                    
                                echo '<tr>';
                                    // "Date"
                                    echo '<td>' . $dt . '</td>';
                                    // "Job": job name
                                    echo '<td>' . $j->getName() . '</td>';
                                    // "Invoice #": invoiceId, primary key to DB table Invoice
                                    echo '<td>' . $item['invoiceId'] . '</td>';
                                    // "Invoice Amount": blank
                                    echo '<td>&nbsp;</td>';
                                    // "Payment": U.S. currency, w/ dollar sign & 2 digits past the decimal point
                                    echo '<td align="right" style="text-align:right;">$' . number_format($item['amount'], 2) . '</td>';
                                echo '</tr>' . "\n";
                            }
                        }
                    } // END foreach ($arranged...
                    
                    if (count($arranged)) {
                        echo '<tr>';
                            // Blank row
                            echo '<td colspan="5">&nbsp;</td>';
                        echo '</tr>' . "\n";
                        echo '<tr>';
                            echo '<td colspan="3" style="text-align:right;">Sum:</td>';
                            // "Sum": U.S. currency, w/ dollar sign & 2 digits past the decimal point
                            echo '<td colspan="2" style="text-align:right;"><b>$' . number_format($sum, 2) . '</b></td>';
                        echo '</tr>' . "\n";
                    }

                echo '</table>';
            echo '</center>';
            ?>
        </div>
    </div>
</div>

<script>
<?php /* >>>00007 given that the following is now a no-op, kill it. Or actually do something here. */ ?>
$(function() {        
    var dateForm = function(dpid){
        //$.post('../ajax/cred_datex.php', $('#form_' + dpid).serialize()) // [COMMENTED OUT BY MARTIN BEFORE 2019]
    }		
    
    $( ".datepicker").datepicker({
        onSelect: function(){
            dateForm(this.id);
        }
    });
});
</script>

<?php 
include BASEDIR . '/includes/footer.php';
?>