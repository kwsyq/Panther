<?php
/*
    fb/creditrecord.php
    
    EXECUTIVE SUMMARY: Display and/or apply a credit record.
    
    Used in top-level creditrecords.php.
    
    PRIMARY INPUT: $_REQUEST['creditRecordId'] identifies a row in the creditRecord DB table. 

    OPTIONAL INPUT 'monthsback' Allows us to limit how old credit records we are concerned with. Default 6.
    OPTIONAL INPUT $_REQUEST['act']. Possible values are 'applypart', 'applybal', 'applyfull', or (added 2020-02-05) 'reverse'
    * Each of those uses $_REQUEST['InvoiceId'].        
    * 'applypart' also uses $_REQUEST['part'], which should be positive.
    * 'reverse' also uses $_REQUEST['part'], which should be negative.

    2020-02-04 JM: with so many nested tables, I've given them each a class that indicates what the different levels are about, even if that
      is not technically necessary. Makes for easier debugging.
    2020-02-10 JM: I've made such large changes here that I decided to throw away the history of what changed.
     
    >>>00006 probably some good potential for common code elimination with fb/creditmemo.php and maybe fb/invoice.php
*/

include '../inc/config.php';
include '../inc/access.php';

// $monthsback is how far back we look at invoices
$monthsback = isset($_REQUEST['monthsback']) ? intval($_REQUEST['monthsback']) : 6;
if ($monthsback < 1) {
    $monthsback = 1;
}

$nonSentInvoiceStatusIdString = Invoice::getNonSentInvoiceStatusesAsString(); // this was way below, moved it up front 2020-02 for clarity

// INPUT $inv - Invoice object
// INPUT $creditRecordId - primary key into DB table creditRecord.
// RETURN total payments on this invoice (paid portion of invoice) in U.S. dollars.
// Writes (directly into stdout) the contents of a headerless table that displays, for each payment on the invoice:
//  date (month/day/year, no leading zeroes) and amount (in dollars, 2 digits past the decimal point)
function writeInvoicePaymentsTable($inv, $creditRecordId) {                                    
    $pays = $inv->getPayments();
    $paidPortionOfInvoice = 0;
    if (count($pays)) {
        echo '<table class="invoicePayments" border="1" cellpadding="2" cellspacing="0">';
        foreach ($pays as $pay) {
            echo '<tr>';
                echo '<td>' . date("n/j/Y", strtotime($pay['inserted'])) . '</td>';							
                echo '<td>' . $pay['amount'];
                //  If this payment is for a positive amount and came from the current creditRecord, and if it hasn't already been reversed, then offer to reverse it.
                if ($pay['amount'] > 0 && $pay['creditRecordId'] == $creditRecordId) {
                    $matchPositive = 0;
                    $matchNegative = 0;
                    foreach ($pays as $pay2) {
                        if ($pay2['inserted'] > $pay['inserted'] && $pay2['creditRecordId'] == $pay['creditRecordId']) {
                            if ($pay['amount'] == $pay2['amount']) {
                                ++$matchPositive;
                            } else if ($pay['amount'] == -$pay2['amount']) {
                                ++$matchNegative;
                            }
                        }
                    }
                    // Typical case is that both $matchPositive and $matchNegative are zero, but this should prevent offering to reverse what is already reversed.
                    if ($matchPositive >= $matchNegative) { 
                        echo '<td>' . 
                            '<button id="creditRecord'.intval($creditRecordId).'" class="reversePayment" ' . 
                            'data-creditrecordid="' . intval($creditRecordId) . '" ' . 
                            'data-invoiceid="' . $inv->getInvoiceId() . '" ' .
                            'data-amount="' . $pay['amount'] . '"' .
                            '">' .
                            'Reverse</button></a>';
                    }
                }
                echo '</td>';
            echo '</tr>' . "\n";
            if (is_numeric($pay['amount'])) {
                $paidPortionOfInvoice += $pay['amount'];                                                
            }
        }
        echo '</table>' . "\n";
    }
    return $paidPortionOfInvoice;
}

// INPUT $inv - Invoice object
// No return.
// Writes (directly into stdout) the contents of a headerless table that displays, for each billing profile 
//  one profile per row. E.g. "Ian&dorothy@sssplaceholder.com / iansmgardner@gmail.com" 
//  or "nwBuilt / keith@northwest-built.com". 
function writeBillingProfilesTable($inv) { 
    echo '<table class="billingProfiles" border="1" cellpadding="0" cellspacing="0">';                        
        // The name getBillingProfiles is a bit misleading: the return, if not an empty array, 
        //  is a single-element array, containing an associative array with the canonical representation
        //  of a row from DB table invoiceBillingProfile (not BillingProfile); 
        // JM 2020-10-30 I've accordingly renamed some variables here to try to be bit clearer what is going on,
        //  in the process of introducing the shadowBillingProfile class. 
        $invoiceBillingProfiles = $inv->getBillingProfiles();

        foreach ($invoiceBillingProfiles as $invoiceBillingProfile) {
            $shadowBillingProfile = new ShadowBillingProfile($invoiceBillingProfile['shadowBillingProfile']);
            $shadowBillingProfileId = $shadowBillingProfile->getBillingProfileId();
            $company = new Company($shadowBillingProfile->getCompanyId());                                    
            $cpbps = $company->getBillingProfiles();
            $loc = '';
            foreach ($cpbps as $cpbp) {
                if ($shadowBillingProfileId == $cpbp['billingProfile']->getBillingProfileId()) {
                    $loc = $cpbp['loc'];
                }
            }
        
            echo '<tr>';                        
                echo '<td>' . $company->getCompanyName() . ' / ' . $loc . '</td>';
            echo '</tr>' . "\n";
        }
    echo '</table>' . "\n";
}

// INPUT $inv - Invoice object
// INPUT $paidPortionOfInvoice - total payments on this invoice (paid portion of invoice) in U.S. dollars.
//   This is the same as the return of function writeInvoicePaymentsTable.
// INPUT $creditRecordId - primary key into DB table creditRecord.
// INPUT $cr_paid_amount - total already paid from this credit record.
// INPUT $cr_balance - balance remaining on this creditRecord.
function offerActions($inv, $paidPortionOfInvoice, $creditRecordId, $cr_paid_amount, $cr_balance) {
    global $monthsback;
    if ($inv->getTriggerTotal() > $paidPortionOfInvoice) {
        echo '<table class="invoiceActions" border="0" cellpadding="0" cellspacing="0">';
            echo '<tr>';
                // If no portion of the credit has yet been applied... 
                if ($cr_paid_amount == 0) {
                    // ... show a button "Apply Full" that self-submits
                    echo '<td><a id="applyFull'.intval($creditRecordId).$inv->getInvoiceId() .'" href="creditrecord.php?act=applyfull&creditRecordId=' . intval($creditRecordId) . '&invoiceId=' . $inv->getInvoiceId() . 
                    '&monthsback=' . intval($monthsback) . '"><button>Apply Full</button></a></td>';
                } else if ($cr_balance > 0) {
                    // Some portion has been applied, but a balance remains on the current credit so...
                    // ...show a button "Apply Bal." that self-submits
                    echo '<td><a id="applyBal'.intval($creditRecordId).'" href="creditrecord.php?act=applybal&creditRecordId=' . intval($creditRecordId) . '&invoiceId=' . $inv->getInvoiceId() .
                    '&monthsback=' . intval($monthsback) . '"><button>Apply Bal.</button></a></td>';
                }
                // Regardless of whether a portion of the credit has been applied, if a credit balance remains on the current credit record... 
                if ($cr_balance > 0) {
                    // ...show a self-submitting form consisting of an input for amount and a balance to apply,
                    //  plus the hidden fields this needs. 
                    echo '<td><form name="inv_' . $inv->getInvoiceId() . '" id="invForm' . $inv->getInvoiceId() . '" ><input type="hidden" name="act" value="applypart">' . 
                         '<input type="hidden" name="creditRecordId" value="' . intval($creditRecordId) . '">' . 
                         '<input type="hidden" name="invoiceId" value="' . $inv->getInvoiceId() . '">' .
                         '<input type="hidden" name="monthsback" value="' . intval($monthsback) . '">';
                        echo '<input type="text" id="part' . $inv->getInvoiceId() . '" name="part" value="" size="5">';
                    echo '</td>';
                    echo '<td><input type="submit" id="appPart' . $inv->getInvoiceId() . '" value="App.Part."></form></td>';				
                }
            echo '</tr>' . "\n";
        echo '</table>' . "\n";
    }
}

// INPUT $rows: canonical representation of rows from DB table invoice, as an array of associative arrays
// INPUT $exactBalance: true => show only if invoice balance == $cr_balance; false => show if invoice balance is nonzero 
// INPUT $creditRecordId - primary key into DB table creditRecord.
// INPUT $cr_paid_amount - total already paid from this credit record.
// INPUT $cr_balance - balance remaining on this creditRecord.
// INPUT $onlyThisInvoiceId - OPTIONAL. If this is a nonzero integer or nonempty array of nonzero integers, then we want only this invoice or invoices. 
//  Will ignore $exactMatch, $monthsback. CALLER IS RESPONSIBLE to make sure this is a valid invoiceId. Typically, $exactBalance should be false.
function writeOpenInvoices($rows, $exactBalance, $creditRecordId, $cr_paid_amount, $cr_balance, $onlyThisInvoiceId=0) {
    // Some basic stuff about invoice statuses
    $invoiceStatusDataArray = Invoice::getInvoiceStatusDataArray();
    $statuses = array();            
    foreach ($invoiceStatusDataArray as $invoiceStatusData) {                
        $statuses[$invoiceStatusData['invoiceStatusId']] = $invoiceStatusData;                
    }
    
    if ($onlyThisInvoiceId && ! is_array($onlyThisInvoiceId)) {
        $onlyThisInvoiceId = Array($onlyThisInvoiceId);
    }   

    // Another nested table, with a row for each invoice that has a nonzero outstanding balance
    $tableClass = $onlyThisInvoiceId ? 'noteMatchInvoice' : ($exactBalance ? 'exactMatchInvoices' : 'nonZeroInvoices' ); 
    $tableTitle = $onlyThisInvoiceId ? 'Possible match based on note:' : ($exactBalance ? 'Invoices with balances exactly matching CR:' : 'Invoices with outstanding balances:');
    echo '<table class="' . $tableClass . '" border="0" cellpadding="4" cellspacing="3">';
        echo '<caption>';
        echo $tableTitle;
        echo '</caption>' . "\n";
        echo '<thead>' . "\n";
            echo '<tr>';
                echo '<th bgcolor="#cccccc">Inv. ID</th>';
                echo '<th bgcolor="#cccccc">WO</th>';
                echo '<th bgcolor="#cccccc">Job</th>';
                echo '<th bgcolor="#cccccc">Name Override</th>';
                echo '<th bgcolor="#cccccc">Invoice Date</th>';
                echo '<th bgcolor="#cccccc">Total</th>';
                echo '<th bgcolor="#cccccc">History</th>';               
                echo '<th bgcolor="#cccccc">Profiles</th>';
                echo '<th bgcolor="#cccccc">Status</th>';
                echo '<th bgcolor="#cccccc">&nbsp;</th>';                    
            echo '</tr>';
        echo '</thead>' . "\n";
        echo '<tbody>' . "\n";                        
        foreach ($rows as $row) {
            if ($onlyThisInvoiceId && !in_array($row['invoiceId'], $onlyThisInvoiceId)) {
                continue; // not an invoice we want.
            }
            $inv = new Invoice($row['invoiceId']);
            $adjustedInvoiceTotal = $inv->getTriggerTotal();
            $paymentTotal = 0;
            $payments = $inv->getPayments();
            foreach ($payments As $payment) {
                $paymentTotal += $payment['amount'];
            }
            if ($paymentTotal == $adjustedInvoiceTotal) {
                continue; // zero balance invoice
            } else if ($exactBalance && $adjustedInvoiceTotal - $paymentTotal != $cr_balance) {
                continue; // non-matching balance
            }
            
            // This code is just like above, >>>00006 common code elimination potential
            $wo = new WorkOrder($row['workOrderId']);
            $job = new Job($wo->getJobId());
    
            echo '<tr>';
                // "InvId", "WO", "Job", each linked to the appropriate page
                echo '<td><a id="linkInv'.$row['invoiceId'].'" target="_blank" href="' . $inv->buildLink() . '">' . $row['invoiceId'] . '</a>' .
                     '<a id="invLink'.$row['invoiceId'].'" href="/fb/invoice.php?invoiceId=' . $row['invoiceId'] . '"><button>' . SYMBOL_LOAD_IN_SAME_FRAME . '</button</a></td>';
                echo '<td><a id="linkWo'.$wo->getWorkOrderId(). $row['invoiceId'].'" target="_blank" href="' . $wo->buildLink() . '">' . $wo->getDescription() . '</a></td>';
                echo '<td><a id="linkJob'.$job->getJobId().  $row['invoiceId'].'" target="_blank" href="' . $job->buildLink() . '">' . $job->getName() . '(' . $job->getNumber() . ')' . '</a></td>';
                
                // "Name Override": from invoice 
                echo '<td>' . $row['nameOverride'] . '</td>';
                
                // "Invoice Date": month/day/year, no leading zeroes 
                echo '<td>' . date("n/j/Y", strtotime($row['invoiceDate'])) . '</td>';
                
                // "Total": from invoice
                echo '<td>' . $row['total'] . '</td>';
                
                // "Hist": payments, nested table like the "History" column in the per-invoice table above.
                echo '<td>';
                    $paidPortionOfInvoice = writeInvoicePaymentsTable($inv, $creditRecordId);
                echo '</td>';
                
                // "Profile(s)": nested table like the "Profiles" column in the per-invoice table above.
                echo '<td>';
                    writeBillingProfilesTable($inv);
                echo '</td>';
                
                // "Status": invoice status, as drawn from Invoice::getInvoiceStatusDataArray(). 
                // Most of these will just be "Closed", but also "Awaiting Payment", "Awaiting Delivery", etc. 
                echo '<td>' . $statuses[$row['invoiceStatusId']]['statusName'] . '</td>';
                
                // (no header) Only if there is an unpaid balance remaining on the invoice, another nested table containing, 
                //  as appropriate, "Apply Full", "Apply Bal." and "App. Part" buttons, exactly as in the per-invoice table above.
                // SEE NOTE ABOVE ABOUT HOW TO TEST. 
                echo '<td>';
                    offerActions($inv, $paidPortionOfInvoice, $creditRecordId, $cr_paid_amount, $cr_balance);
                echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>' . "\n";
    echo '</table>';
} // END function writeOpenInvoices


// ----------------------------                                

$creditRecordId = isset($_REQUEST['creditRecordId']) ? intval($_REQUEST['creditRecordId']) : 0;
$cr = new CreditRecord($creditRecordId);

if ($act == 'applypart' || $act == 'applybal' || $act == 'applyfull' || $act == 'reverse') {
    $invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;    
    $invoice = new Invoice($invoiceId, $user);
    $status = "Invalid invoiceId $invoiceId"; // kind of cheating here, but that's the only reason we wouldn't call $invoice->pay()

    if ($act == 'applypart') {
        // Call the pay method on the relevant invoice object, passing in "payPart", the relevant creditRecordId, and the part value; then redisplay.
        $part = isset($_REQUEST['part']) ? $_REQUEST['part'] : '';    
        if ($invoice->getInvoiceId()) {
            $status = $invoice->pay('payPart', $creditRecordId, $part);
        }
    }
    
    if ($act == 'applybal') {
        // Call the pay method on the relevant invoice object, passing in "payBal" and the relevant creditRecordId, then redisplay.
        if ($invoice->getInvoiceId()) {    
            $status = $invoice->pay('payBal', $creditRecordId);
        }
    }
    
    if ($act == 'applyfull') {
        // Call the pay method on the relevant invoice object, passing in "payFull" and the relevant creditRecordId, then redisplay. 
        if ($invoice->getInvoiceId()) {
            $status = $invoice->pay('payFull', $creditRecordId);
        }
    }
    
    if ($act == 'reverse') {
        // Call the pay method on the relevant invoice object, passing in "reversePay", the relevant creditRecordId, and the (negative) part value; then redisplay.
        $part = isset($_REQUEST['part']) ? $_REQUEST['part'] : '';    
        if ($invoice->getInvoiceId()) {
            $status = $invoice->pay('reversePay', $creditRecordId, $part);
        }
    }
    if ($status == 'OK') {
        // Success. Now reload in a way that a refresh won't repeat the action.
        header("Location: /fb/creditrecord.php?creditRecordId=$creditRecordId");
        die();
    }
}

include BASEDIR. "/includes/header_fb.php";
if (isset ($status) && $status != 'OK') { 
    echo "<p>Problem with payment: $status</p>";
}
    
?>
<script>
$(function() {
    $(document).on("click", "button.reversePayment", function() {
        let $this = $(this);
        let creditRecordId = $this.data('creditrecordid');
        let invoiceId = $this.data('invoiceid');
        let amount= $this.data('amount');
        let $rvDialog = $('<div>Credit back $' + amount + ' to credit record ' + creditRecordId + 
                ' from invoice ' + invoiceId + '.</div>');
        console.log($rvDialog);
        $rvDialog.dialog(  // >>>>>>>>>>> This isn't working. Is jQuery somehow opened twice?
            {
                 title: 'Reverse a prior payment',
                 width: 400,
                 close: function() {
                     $rvDialog.dialog('destroy').remove(); // completely destroy the dialog on close
                 },
                 buttons: {
                     "Proceed" : function() {
                        $.ajax({
                            url: '/ajax/insertinvoicepayment.php',
                            data: {
                                creditRecordId: creditRecordId,
                                invoiceId: invoiceId,
                                amount: -amount, // NOTE that this is a negative to reverse payment
                                type: 'reversePay'
                            },
                            async: false,
                            type: 'post',
                            context: this,
                            success: function(data, textStatus, jqXHR) {
                                if (data['status']) {
                                    if (data['status'] == 'success') {
                                        // reload this page
                                        location.reload(true);
                                    } else {
                                        alert(data['error']);
                                    }
                                } else {
                                    alert('error: no status');
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                alert('AJAX error');
                            }
                        });
                     },
                     "Cancel" : function() {
                         $rvDialog.dialog('close');
                     }
                 } // END buttons
             }
         ); // END dialog
    }); // END click handler
});
</script>
<style>
table caption {
    font-weight:bold;
    text-align:left;
    caption-side:top;
}
</style>
<?php
// Top-level table has content only if creditRecordId is valid.
echo '<table class="creditRecord" border="1" cellpadding="0" cellspacing="0" width="100%" height="100%">';    
    if (intval($cr->getCreditRecordId())) {
        $invoices = $cr->getCreditRecordInvoices();
                            
        // First row 
        echo '<tr>';
            // First row, left column (half of table): the return of viewiframe.php for this credit record (or "can't view so" if file not available) 
            echo '<td width="50%" height="50%">';
                $src = "viewiframe.php?creditRecordId=" . intval($cr->getCreditRecordId());
                echo '<iframe id="viewiframe" src="' . $src . '" style="width: 100%; height: 100%; border: none; margin: 0; padding: 0; display: block;"></iframe>';
            echo '</td>';
            // First row, right column: a subtable, each row of which is a label and a value 
            echo '<td width="50%" valign="top">';
                $types = CreditRecord::creditRecordTypes();
                echo '<table class="creditRecordInfo" border="0" cellpadding="2" cellspacing="2">';
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">CreditRecordId</td>';
                        echo '<td>' . $cr->getCreditRecordId() . '</td>';
                    echo '</tr>' . "\n";
                    // CreditRecordType name. Types come from inc/config.php, and are further elaborated with names in 
                    //  /inc/classes/CreditRecord.class.php. E.g. CRED_REC_TYPE_CHECK, CRED_REC_TYPE_PAYPAL; e. g. "PayPal" 
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Type</td>';
                            $tn = '';
                            if (isset($types[$cr->getCreditRecordTypeId()])) {
                                $tn = $types[$cr->getCreditRecordTypeId()]['name'];
                            }
                        echo '<td>' . $tn . '</td>';
                    echo '</tr>' . "\n";
                    
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Reference #</td>';
                        echo '<td>' . $cr->getReferenceNumber() . '</td>'; // E.g. check # or Paypal payment #
                    echo '</tr>' . "\n";
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Amount</td>';
                        echo '<td>' . $cr->getAmount() . '</td>';
                    echo '</tr>' . "\n";				
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Credit Date</td>';
                        echo '<td>' . $cr->getCreditDate() . '</td>';
                    echo '</tr>' . "\n";
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Received From</td>';
                        echo '<td>' . $cr->getReceivedFrom() . '</td>'; // Typically name of company credit was received from but this is very open-ended: 
                                                                        // what was written on a check, or name on a PayPal account.
                    echo '</tr>' . "\n";
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Notes</td>';
                        echo '<td>' . $cr->getNotes() . '</td>'; // Open-ended, in practice usually an address
                    echo '</tr>' . "\n";
                    echo '<tr>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                    echo '</tr>' . "\n";
                    
                    // Invoices: a comma-separated list of invoiceIds, 
                    //  one for each invoice associated with the credit record, each linked to open the relevant invoice in a new tab. 
                    echo '<tr>';
                        echo '<td bgcolor="#cccccc">Invoices</td>';
                        echo '<td>';
                            $str = '';
                            foreach ($invoices as $invoice) {
                                if (strlen($str)) {
                                    // Not the first
                                    $str .= '<br />';
                                }
                                $str .= $invoice['invoiceId'] . 
                                    '&nbsp;<a id="invViewInTab'.$invoice['invoiceId'].'" target="_blank" href="/invoice/' . $invoice['invoiceId'] . '"><button>View in tab</button></a>' .
                                    '&nbsp;<a id="invView'.$invoice['invoiceId'].'"  href="/fb/invoice.php?invoiceId=' . $invoice['invoiceId'] . '"><button>View here ' . SYMBOL_LOAD_IN_SAME_FRAME . '</button></a>';
                            }
                            echo $str;
                        echo '</td>';
                    echo '</tr>' . "\n";
                    echo '<tr>';
                        echo '<td>&nbsp;</td>';
                        echo '<td>&nbsp;</td>';
                    echo '</tr>' . "\n";
                    echo '<tr>';
                        echo '<td colspan="2">';
                        ?>
                        <label for="monthsback" style="background-color:#cccccc;">Show&nbsp;invoices&nbsp;for&nbsp;last</label>&nbsp;<select id="monthsback" name="monthsback">
                        <option value="">----</option>
                        <option value="1" <?php if ($monthsback==1) {echo ' selected';}?> >1 month</option>
                        <option value="2" <?php if ($monthsback==2) {echo ' selected';}?> >2 months</option>
                        <option value="3" <?php if ($monthsback==3) {echo ' selected';}?> >3 months</option>
                        <option value="6" <?php if ($monthsback==6) {echo ' selected';}?> >6 months</option>
                        <option value="12" <?php if ($monthsback==12) {echo ' selected';}?> >1 year</option>
                        <option value="24" <?php if ($monthsback==24) {echo ' selected';}?> >2 years</option>
                        <option value="36" <?php if ($monthsback==36) {echo ' selected';}?> >3 years</option>
                        <option value="10000" <?php if ($monthsback==10000) {echo ' selected';}?> >All dates</option>
                        </select>&nbsp;<button id="refresh">Go</button>
                        <script>
                        $('#refresh').click(function() {
                            window.location = '?creditRecordId=' + <?php echo $creditRecordId; ?> + '&monthsback=' + $('#monthsback').val();
                        });
                        </script>
                        <?php
                        echo '</td>';
                    echo '</tr>';
                echo '</table>' . "\n";
            echo '</td>' . "\n";
        echo '</tr>' . "\n";
        
        // Second row (A single table-spanning column; blank if the amount for this credit record is zero.)
        echo '<tr>';
            echo '<td colspan="2" height="50%" valign="top">';
                $tt = $cr->getAmount();
                if (!is_numeric($tt)) {
                    $tt = 0;
                }
                if ($tt > 0) {
                    /* Before filling in content, does a bunch of calculation. Starting from the credit record object:
                       * Gets array $invoices of the associated invoices, basically rows in the CreditRecords DB table, 
                         plus the invoiceId again as index 'criinvoiceid'
                       * Gets $crps, a structure that shows what portion of this credit is applied to what invoice or credit memo.
                       * Totals up the amount applied to the various invoices, calculates the total as $cr_paid_amount and the remaining
                         balance as $cr_balance. A positive balance means not all of the credit has been assigned to invoices.  
                    */
                    $cr_paid_amount = 0;
                    $crps = $cr->getPayments();
                    $pays = array();
                    if (isset($crps['invoices'])) {
                        $pays = $crps['invoices'];			
                    }
                
                    foreach ($pays as $crp) {
                        $cr_paid_amount += $crp['amount'];
                    }
                
                    $cr_balance = ($cr->getAmount() - $cr_paid_amount);
                
                    $runTotal = 0;
                    $runTriggerTotal = 0;

                    if ($invoices) {
                        echo '<table class="invoices" border="0" cellpadding="4" cellspacing="3">';
                        
                            // First row of nested table spans the table with a heading "Invoice ID's provided (provided = NN)" where
                            //  NN is number of invoices.
                            echo '<caption>Invoices already associated with this CR</caption>';
                            // Headings
                            // >>>00032: 2020-04-02: Columns differ more than they probably should from the nonzero outstanding balance case.
                            //  Ron & Damon would like this thought through.
                            echo '<thead>';
                            echo '<tr>';
                                echo '<th bgcolor="#cccccc">Inv. ID</th>';
                                echo '<th bgcolor="#cccccc">Name Override</th>';
                                echo '<th bgcolor="#cccccc">Invoice Date</th>';
                                echo '<th bgcolor="#cccccc">Total</th>';
                                echo '<th bgcolor="#cccccc">Balance (trig)</th>';                
                                echo '<th bgcolor="#cccccc">History</th>';
                                echo '<th bgcolor="#cccccc">Profiles</th>';                
                                echo '<th bgcolor="#cccccc">&nbsp;</th>';                        
                            echo '</tr>' . "\n";
                            echo '</thead>' . "\n";
                            
                            echo '</tbody>' . "\n";
                            // One row per invoice
                            foreach ($invoices as $invoice) {
                                echo '<tr>';
                                    // "Inv. ID": invoiceId, linked to open the relevant invoice in a new tab
                                    echo '<td><a id="linkInvoice'. $invoice['invoiceId'] . '" target="_blank" href="/invoice/' . $invoice['invoiceId'] . '">' . $invoice['invoiceId'] . '</a>' .
                                        '<a id="linkInvoiceSameFrame'. $invoice['invoiceId'] . '" href="/fb/invoice.php?invoiceId=' . $invoice['invoiceId'] . '"><button>' . SYMBOL_LOAD_IN_SAME_FRAME . '</button</a></td>';
                                    // "Name Override": from invoice
                                    echo '<td>' . $invoice['nameOverride'] . '</td>';
                                    // "Invoice Date": from invoice (month/day/year, no leading zeroes) 
                                    $dd = '';
                                    if (strlen($invoice['invoiceDate'])) {
                                        $dd = date("n/j/Y", strtotime($invoice['invoiceDate']));
                                    }
                                    echo '<td>' . $dd . '</td>';
                                    // "Total" (original total)
                                    echo '<td>' . $invoice['total'] . '</td>';
                                    // "Balance" (remaining balance)
                                    echo '<td>' . $invoice['triggerTotal'] . '</td>';
                    
                                    if (is_numeric($invoice['total'])) {
                                        $runTotal += $invoice['total'];
                                    }
                                    if (is_numeric($invoice['triggerTotal'])) {
                                        $runTriggerTotal += $invoice['triggerTotal'];
                                    }
                                    
                                    $inv = new Invoice($invoice['invoiceId']);
                                    
                                    // "History": a further nested table showing all past payments on this invoice; 
                                    //  no headers, just date (month/day/year, no leading zeroes) and amount (in dollars, 2 digits past the decimal point) 
                                    echo '<td>';
                                        $paidPortionOfInvoice = writeInvoicePaymentsTable($inv, $creditRecordId);
                                    echo '</td>';
                                    
                                    // "Profiles": yet another nested table, giving company name and location from all billing profiles 
                                    //  associated with this invoice, one profile per row. E.g. "Ian&dorothy@sssplaceholder.com / iansmgardner@gmail.com" 
                                    //  or "nwBuilt / keith@northwest-built.com". 
                                    echo '<td>';
                                        writeBillingProfilesTable($inv);
                                    echo '</td>';
                        
                                    // (no header) Used only if the invoice still has an unpaid balance (based on triggerTotal, not total); 
                                    // yet another nested table, with a variable number of columns, to show the following one to a column, as relevant. 
                                    // Testing note: it may be hard to find examples to see this in the UI. 
                                    //  TO SEE THIS FOR TESTING, on the dev/test machine ONLY, delete a payment that has this creditRecordId.
    /*
    
        (hidden) act=applypart
        (hidden) creditRecordId=creditRecordId
        (hidden) invoiceId=invoiceId
        text input for "part" (JM: U.S. dollars with two digits past the decimal point)
        submit button labeled "App.Part."
        */
                                    echo '<td>';
                                        offerActions($inv, $paidPortionOfInvoice, $creditRecordId, $cr_paid_amount, $cr_balance);
                                    echo '</td>';
                                echo '</tr>' . "\n";
                            } // END foreach ($invoices...
                            
                            // At the same table level as the invoices, a row for cross-invoice totals. 
                            // Mostly blank, but in the same columns as the Total, and Balance, respectively, are column totals; 
                            //  in what I (JM) have seen as of 2019-04, no decimal point or cents 
                            echo '<tr>';
                                echo '<td>&nbsp;</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td bgcolor="#cccccc">' . number_format($runTotal, 2) . '</td>';
                                echo '<td bgcolor="#cccccc">' . number_format($runTriggerTotal, 2) . '</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td>&nbsp;</td>';                                                                                   
                            echo '</tr>' . "\n";
                            echo '</tbody>' . "\n";
                        echo '</table>' . "\n";
                    } else {
                        // Style this to match our table headings
                        echo '<span style="color: rgb(108, 117, 125); font-size: 16px; font-weight: 700; line-height: normal;">' .
                            'No invoices have been paid yet from this credit record.</span><br />' . "\n";
                    }
                    
                    // ---------------------------------------------------------------------------------
                    
                    // NOTE that we are still in left column of second row of top-level table, for a non-zero credit record
                    
                    // The following remarks are based on discussion between Joe and Martin around November 2018:
                        //  We calculate some stuff about invoice statuses (among other things, which invoices have the relevant total
                        //  � this was based on an expectation of difficulty guessing what invoice a payment applies to and using a rule of thumb � 
                        //  and using invoice status to avoid including unsent invoices), then start another nested table. 
                        //  Eventually we might want more of a search functionality here. 
                        //  In practice, Tawny at SSS is doing a good job of matching creditRecords to invoices without further tools.
                        
                    $db = DB::getInstance();
                    $rows = array();
                    if ($nonSentInvoiceStatusIdString !== false && strlen($nonSentInvoiceStatusIdString)) {
                        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".invoice ";
                        $query .= "WHERE invoiceStatusId NOT IN (" . $db->real_escape_string($nonSentInvoiceStatusIdString) . ") "; 
                        $query .= "AND inserted > DATE_ADD(NOW(), INTERVAL -$monthsback MONTH);";
                        
                        $result = $db->query($query);
                        if ($result) {
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $rows[] = $row;
                                }
                            }
                        } // else >>>00002 should log error
                    }
                    
                    $notes = trim($cr->getNotes());
                    if (is_numeric($notes) && intval($notes)) {
                        // This is actually redundant to the case that follows, but it's simple, so I have it here for clarity - JM
                        $possibleInvoiceId = intval($notes);
                        if (Invoice::validate($possibleInvoiceId)) {
                            writeOpenInvoices($rows, false, $creditRecordId, $cr_paid_amount, $cr_balance, $possibleInvoiceId);
                        }                            
                    } else if ($notes) {
                        $possibleInvoiceIdArray = Array();
                        // Look for possible invoice number(s) in notes
                        // consider whitespace, semicolon, comma, or a period followed by a space (to avoid, for example, 234.40) as separators.
                        $invoiceIdCandidates = preg_split('/(\s|;|,|\. )/', $notes);
                        foreach ($invoiceIdCandidates AS $invoiceIdCandidate) {
                            if (strlen($invoiceIdCandidate) >= 3 && preg_match('/\d*/', $invoiceIdCandidate)) {
                                // at least 3 characters and composed entirely of digits
                                $possibleInvoiceIdArray[] = intval($invoiceIdCandidate);
                            }
                        }
                        if ($possibleInvoiceIdArray) {
                            writeOpenInvoices($rows, false, $creditRecordId, $cr_paid_amount, $cr_balance, $possibleInvoiceIdArray);
                        }
                    }
                    writeOpenInvoices($rows, true, $creditRecordId, $cr_paid_amount, $cr_balance);
                    writeOpenInvoices($rows, false, $creditRecordId, $cr_paid_amount, $cr_balance);
                }

            echo '</td>';
        echo '</tr>' . "\n";
    } // END if (intval($cr->getCreditRecordId()))
echo '</table>' . "\n";

include '../includes/footer_fb.php';
?>
</body>
</html>