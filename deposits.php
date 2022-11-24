<?php 
/*  deposits.php

    ACCORDING TO TAWNY, AS OF 2020-02-20, THIS PAGE IS TOTALLY BROKEN. To the best of her
    knowledge, it has NEVER worked. Something Martin was working on that never got finished.
    JM did some cleanup 2020-03-23, but only a bit. 
    
    2020-04-02: Damon says we want to fix this file.

    EXECUTIVE SUMMARY: Another way of looking at creditRecords, besides creditrecords.php. 
    See all creditRecords that were deposited in a particular time frame, including 
    what payments have been made against them. Requires Admin-level Payments permission.

    PRIMARY INPUTs: $_REQUEST['fromDate'], $_REQUEST['toDate'], $_REQUEST['creditRecordTypeId']. 
    'fromDate' and 'toDate' should be input in 'Y-m-d' form. 
    Reformats dates as month/day/year, using '0/0/0' if they won't parse. That means a failed 
    fromDate means "from the beginning of time", but a failed toDate means no data will match.
    >>>00002 >>>00016 as usual, it might make more sense to indicate that the input is bad, and probably log something.
    
    As of 2019-03, creditRecordTypeId values are not defined in a DB table, they come from inc/config.php
    and are further elaborated in /inc/classes/CreditRecord.class.php. E.g. CRED_REC_TYPE_CHECK, CRED_REC_TYPE_PAYPAL.
    
*/

require_once './inc/config.php';
require_once './inc/perms.php';

// Die if not admin-level payments permission.
$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm){
    die();
}

$crumbs = new Crumbs(null, $user);

$db = DB::getInstance();

// See note at top of file about date formats & what happens on "bad" dates.
// JM 2020-03-23: somewhat rewritten to remove multiplexing of $fromDate, $toDate:
//  distinguish $fromDateOriginalInput from $fromDateScratch, and $toDateOriginalInput from $toDateScratch 
$fromDateOriginalInput = isset($_REQUEST['fromDate']) ? $_REQUEST['fromDate'] : '';
$toDateOriginalInput = isset($_REQUEST['toDate']) ? $_REQUEST['toDate'] : '';
$creditRecordTypeId = isset($_REQUEST['creditRecordTypeId']) ? intval($_REQUEST['creditRecordTypeId']) : 0;

$fromDateScratch = date_parse($fromDateOriginalInput);
$fromDateField = '';
if (is_array($fromDateScratch)){
    if (isset($fromDateScratch['year']) && isset($fromDateScratch['day']) && isset($fromDateScratch['month'])) {        
        $fromDateField = intval($fromDateScratch['month']) . '/' . intval($fromDateScratch['day']) . '/' . intval($fromDateScratch['year']);
        if ($fromDateField == '0/0/0'){
            $fromDateField = '';
        }
    }
}

$toDateScratch = date_parse($toDateOriginalInput);
$toDateField = '';
if (is_array($toDateScratch)) {
    if (isset($toDateScratch['year']) && isset($toDateScratch['day']) && isset($toDateScratch['month'])) {        
        $toDateField = intval($toDateScratch['month']) . '/' . intval($toDateScratch['day']) . '/' . intval($toDateScratch['year']);
        if ($toDateField == '0/0/0'){
            $toDateField = '';
        }
    }
}

$deposits = array();

if (strlen($toDateField) && strlen($fromDateField) && intval($creditRecordTypeId)) {
    // Does a SQL query to select all creditRecords with the relevant creditRecordTypeId 
    //  and depositDate between fromDate and toDate, inclusive. 
    // Result is ordered by depositDate, creditRecordId (the latter being effectively 
    //  chronological by the time the row was inserted). 
    // We then instantiate a CreditRecord object for each of these.
    $db = DB::getInstance();
    
    // 2020-03-23 JM got rid of a re-read of $_REQUEST['toDate'], $_REQUEST['fromDate'], and $_REQUEST['creditRecordTypeId'] here
    
    $query = " select * from " . DB__NEW_DATABASE . ".creditRecord ";
    $query .= " where creditRecordTypeId = " . intval($creditRecordTypeId) . " ";
    $query .= " and depositDate between ";
    $query .= " '" . $db->real_escape_string(date("Y-m-d", strtotime($fromDateOriginalInput))) . "' ";
    $query .= " and ";
    $query .= " '" . $db->real_escape_string(date("Y-m-d", strtotime($toDateOriginalInput))) . "' ";
    $query .= " order by depositDate, creditRecordId ";

    if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        if ($result->num_rows > 0){
            while ($row = $result->fetch_assoc()){
                $deposits[] = new CreditRecord($row);
            }
        }
    }  // >>>00002 ignores failure on DB query! 
}

include_once BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title = 'Deposits';\n</script>\n";

$credRecTypes = CreditRecord::creditRecordTypes();


?>

<div id="container" class="clearfix">
	<div class="main-content">
	    <?php /* a link "PDF" to the corresponding call to deposits_pdf.php */   ?>  
		<a id="linkPdf" href="deposits_pdf.php?fromDate=<?php echo rawurlencode($fromDateOriginalInput); ?>&toDate=<?php echo rawurlencode($toDateOriginalInput); ?>&creditRecordTypeId=<?php echo intval($creditRecordTypeId); ?>">PDF</a>
		<center>
		    <?php /* FORM implemented as a table 
		             Self-submitting HTML form, name="statementform", formatted as a table (one row, no headers) with the following content:
		             * label: 'From', text input using datePicker name="fromDate", id="fromDate", initial value is the input fromDate
		             * label: 'To', text input using datePicker name="toDate", id="toDate", initial value is the input toDate
		             * HTML SELECT (dropdown):
		                 * First option is labeled '-- Cred Record Type --', value=0
		                 * for each creditRecordType, an option labeled with the creditRecordType name, value is the creditRecordType Id.
		                   Value matching input creditRecordTypeId is initially selected. 
                     * Submit button, labeled "go".
            */ ?>
            <form name="statementform" id="statementform" method="get" action="">
            <table border="0" cellpadding="5" cellspacing="0" width="100%">
            <tr>
                <td style="float:right">
                <table border="0" cellpadding="3" cellsapcing="0">
                <tr>
                    <?php
		            /* REMOVED 2020-03-23 JM: Get rid of two hidden inputs that will be ignored because visible elements with the same name come later in the form. 
                    echo '<input type="hidden" name="toDate" value="' . htmlspecialchars($toDateField) . '">';
                    echo '<input type="hidden" name="fromDate" value="' . htmlspecialchars($fromDateField) . '">';
                    */
                    ?>
                    <td><?php 
                    echo 'From:<input type="text" name="fromDate" class="datepicker" id="fromDate" value="' . htmlspecialchars($fromDateField) . '">';
                    ?></td>
                    <td><?php 
                    echo 'To:<input type="text" name="toDate" class="datepicker" id="toDate" value="' . htmlspecialchars($toDateField) . '">';
                    ?></td>
    
                    <td>
                        <select id="creditRecordTypeId" name="creditRecordTypeId"><option value="">-- Cred Record Type --</option>
                        <?php 
                        foreach ($credRecTypes as $crtid => $credRecType) {                            
                            $selected = ($creditRecordTypeId == $crtid) ? 'selected ' : '';
                            echo '<option value="' . intval($crtid) . '" ' . $selected . '>' . $credRecType['name'] . '</option>';
                        }
                        ?>
                        </select>
                    </td>	
                    <td>
                    <input type="submit" id="go" value="go">
                    </td>		
                </tr>			
                </table>
                </td>
            </tr>
            </table>
            </form>

            <?php /* table, this one showing the deposit data we got from the SQL query, 
                     one row per creditRecord. Columns have headers at the top, but in 
                     a few places we play a bit with their use. Days alternate between 
                     a very light gray background and a slightly darker gray background. */ ?>
            <table border="0" cellpadding="4" cellspacing="3" width="85%">
                <tr>
                    <th>Date</th>
                    <th>Ref. Number</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th></th>
                    <th>Daily Sum.</th>
                </tr>
			<?php
                $subTotal = 0;
                $currentDay = 0;
                $grandTotal = 0;
                
                $color = 'eeeeee';
                $lastColor = '';
                foreach ($deposits as $dkey => $deposit) {
                    // BEGIN commented out by Martin before 2019
                    //if (intval($dkey % 2)){			        
                    //    $color = ' bgcolor="#eaeaea" ';			        
                    // } else {			        
                    //    $color = "";			        
                    //}
                    // END commented out by Martin before 2019
                    
                    $day = date("n/j/Y", strtotime($deposit->getDepositDate()));
                    
                    // In the next line, the $dkey test prevents this from happening on the first row.
                    if (($day != $currentDay) && (intval($dkey))) {
                        // Before the start of a new day:
                        //  * 4 blank columns
                        //  * (no header) day just ended in "n/j/Y" form
                        //  * "Daily Sum.": subtotal for day.
                        echo '<tr bgcolor="' . $color . '">';
                            echo '<td colspan="4">&nbsp;</td>';	 
                            echo '<td style="text-align:right">' . $currentDay . '</td>';
                            echo '<td style="text-align:right">' . number_format($subTotal,2) . '</td>';			
                        echo '</tr>';
                        $subTotal = 0;
                    
                        if ($color == 'eeeeee'){
                            $color = 'cccccc';
                        } else {
                            $color = 'eeeeee';
                        }
                    }
			    
                    if (is_numeric($deposit->getAmount())) {
                        $subTotal += $deposit->getAmount();
                        $grandTotal += $deposit->getAmount();
                    }

                    $lastColor = $color;
                    
                    echo '<tr bgcolor="' . $color . '">';
                        // "Date": month/day/year
                        echo '<td>' . $day . '</td>';
                        
                        // "Ref. Number": check #, PayPal payment number, etc., linked to the page for this Creditrecord
                        echo '<td><a id="creditRecord'.$deposit->getReferenceNumber().'" href="creditrecord.php?creditRecordId=' . intval($deposit->getCreditRecordId()) . '">' . 
                             $deposit->getReferenceNumber() . '</a></td>';
                        
                        // "Client": This is a series of lines, separated by HTML BR elements. 
                        // Each line corresponds to a payment made against this credit record, 
                        //  as obtained by CreditRecord::getPayments(). For each payment, we use 
                        //  the invoiceId to instantiate the appropriate Invoice object, then get 
                        //  all of the billing profiles associated with that invoice, and for each 
                        //  billing profile we unserialize the shadowBillingProfile to identify the company. 
                        //  For each such company we then display the company name, linked to the page for that company.
                        //
                        // So if there are multiple billing profiles for one invoice, we actually can 
                        //  end up with multiple company names successively on one line, each linked to 
                        //  a different company.                                                      
                        echo '<td>';
                        $payments = $deposit->getPayments();                  
                        $invoices = $payments['invoices'];                  
                        foreach ($invoices as $ikey => $invoice) {
                            if (intval($ikey)) {
                                echo '<br>';
                            }
                            $i = new Invoice($invoice['invoiceId']);
                            // The name getBillingProfiles is a bit misleading: the return, if not an empty array, 
                            //  is a single-element array, containing an associative array with the canonical representation
                            //  of a row from DB table invoiceBillingProfile (not BillingProfile); 
                            // JM 2020-10-30 I've accordingly renamed some variables here to try to be bit clearer what is going on,
                            //  in the process of introducing the shadowBillingProfile class. 
                            $invoiceBillingProfiles = $i->getBillingProfiles(); 
                            foreach ($invoiceBillingProfiles as $bkey => $invoiceBillingProfile) { 
                                if (intval($bkey)) {
                                    echo ', ';
                                }
                                $shadowBillingProfile = new ShadowBillingProfile($invoiceBillingProfile['shadowBillingProfile']);
                                $c = new Company($shadowBillingProfile->getCompanyId());                  		
                                echo '<a id="shadowBilling'.$invoiceBillingProfile['invoiceBillingProfileId'].'" href="' . $c->buildLink() . '">' . $c->getName() . '</a>';
                            }
                        }
                      
                        echo '</td>';
                        
                        // "Amount": Credit record amount. U.S. currency; formatted with 2 digits past the decimal point. 
                        echo '<td>' . $deposit->getAmount() . '</td>';
                        
                        // last 2 columns are blank, we only use these at end of day.
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                    echo '</tr>';
                
                    // After last row, we handle that as end of a day.
                    if ( $dkey == (count($deposits) - 1) ) {
                        echo '<tr bgcolor="' . $color . '">';
                        echo '<td colspan="4">&nbsp;</td>';
                        echo '<td style="text-align:right">' . $day . '</td>';                   
                        echo '<td style="text-align:right">' . number_format($subTotal,2) . '</td>';
                        echo '</tr>';
                        $subTotal = 0;
                    }
                    $currentDay = $day;
                }
			
                // If there were any deposits, we add a "grand total" line, using only the last column.
                if (count($deposits)){
                    echo '<tr>';
                        echo '<td colspan="6"><hr></td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<td colspan="5">&nbsp;</td>';			    
                        echo '<td style="text-align:right">' . number_format($grandTotal,2) . '</td>';
                    echo '</tr>';
                }
			?>			
			</table>			
	</center>
	</div>
</div>
	
<script>
<?php /* >>>00006 partially commented out code at the bottom of the file that 
        would automatically self-submit on change of either date. 
        Assuming we are not restoring the POST:
        (1) we should completely get rid of the partially commented out code. 
        (2) We might want to have some visual indication if the user has 
            changed the date in this form, but has not submitted, so the 
            data in the table below below doesn't match the dates FORM "statementform"
*/ ?>            
$(function() {
	var dateForm = function(dpid){
		//$.post('../ajax/cred_datex.php', $('#form_' + dpid).serialize())
	}		

    $( ".datepicker").datepicker({
        onSelect: function(){
			dateForm(this.id);
        }
    });
});

</script>

<?php
include_once BASEDIR . '/includes/footer.php';
?>