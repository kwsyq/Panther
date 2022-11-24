<?php
/*
    fb/invoice.php
    
    EXECUTIVE SUMMARY: Display a given invoice and/or apply a credit record to it.
    
    PRIMARY INPUT: $_REQUEST['invoiceId'] identifies an invoice. 

    OPTIONAL INPUT $_REQUEST['act']. Possible values are 'applypart', 'applybal', 'applyfull', or 'reverse'
    * Each of those uses $_REQUEST['CreditRecordId']. 
    * 'applypart' also uses $_REQUEST['part'], which should be positive.
    * 'reverse' also uses $_REQUEST['part'], which should be negative.

    >>>00006 Potential for common code elimination with fb/creditrecord.php and/or fb/creditmemo.php.
*/

include '../inc/config.php';
include '../inc/access.php';

// INPUT $inv - Invoice object
// RETURN total payments on this invoice (paid portion of invoice) in U.S. dollars.
// Writes (directly into stdout) the contents of a headerless table that displays, for each payment on the invoice:
//  date (month/day/year, no leading zeroes) and amount (in dollars, 2 digits past the decimal point)
function writeInvoicePaymentsTable($inv) {                                    
    $pays = $inv->getPayments();
    $paidPortionOfInvoice = 0;
    if (count($pays)) {
        echo '<table class="invoicePayments" border="1" cellpadding="2" cellspacing="0">';
        foreach ($pays as $pay) {
            echo '<tr>';
                echo '<td>' . date("n/j/Y", strtotime($pay['inserted'])) . '</td>';							
                echo '<td>' . $pay['amount'];
                echo '<td><a id="creditRecord=' .$pay['creditRecordId'].'"  target="_blank" href="/creditrecord.php?creditRecordId=' .$pay['creditRecordId'].'">CR&nbsp;' . $pay['creditRecordId']. '</a>' .
                     '<a id="linkCreditRecord=' .$pay['creditRecordId'].'"  href="/fb/creditrecord.php?creditRecordId=' . $pay['creditRecordId'] . '"><button>' . SYMBOL_LOAD_IN_SAME_FRAME . '</button</a>';
                // If this payment is for a positive amount, and if it hasn't already been reversed, then offer to reverse it.
                
                if ($pay['amount'] > 0) {
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
                            '<button id="buttonReverse' . $inv->getInvoiceId() . '" class="reversePayment" ' .
                            'data-creditrecordid="' . $pay['creditRecordId'] . '" ' . 
                            'data-invoiceid="' . $inv->getInvoiceId() . '" ' .
                            'data-amount="' . $pay['amount'] .
                            '">' .
                            'Reverse</button></a>';
                    }
                }
                echo '</td>';
            echo '</tr>';
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
            echo '</tr>';
        }
    echo '</table>';
}

// INPUT $inv - Invoice object
// INPUT $paidPortionOfInvoice - total payments on this invoice (paid portion of invoice) in U.S. dollars.
//   This is the same as the return of function writeInvoicePaymentsTable.
// INPUT $creditRecordId - primary key into DB table creditRecord.
// INPUT $cr_paid_amount - total already paid from this credit record.
// INPUT $cr_balance - balance remaining on this creditRecord.
function offerActions($inv, $paidPortionOfInvoice, $creditRecordId, $cr_paid_amount, $cr_balance) {
    if ($inv->getTriggerTotal() > $paidPortionOfInvoice) {
        echo '<table class="invoiceActions" border="0" cellpadding="0" cellspacing="0">';
            echo '<tr valign="top">';
                // If no portion of the credit has yet been applied... 
                if ($cr_paid_amount == 0) {
                    // ... show a button "Apply Full" that self-submits
                    // (restyled as a button JM 2020-02-05)
                    echo '<td><a id="applyFull'. intval($creditRecordId).$inv->getInvoiceId() .'"  href="?act=applyfull&creditRecordId=' . intval($creditRecordId) . '&invoiceId=' . $inv->getInvoiceId() . '">' .
                    '<button>Apply Full</button></a></td>';
                } else if ($cr_balance > 0) {
                    // Some portion has been applied, but a balance remains on the current credit so...
                    // ...show a button "Apply Bal." that self-submits
                    // (restyled as a button JM 2020-02-05)
                    echo '<td><a id="applyBal'. intval($creditRecordId).$inv->getInvoiceId() .'"  href="php?act=applybal&creditRecordId=' . intval($creditRecordId) . '&invoiceId=' . $inv->getInvoiceId() . '">' .
                    '<button>Apply Bal.</button></a></td>';
                }
                // Regardless of whether a portion of the credit has been applied, if a credit balance remains on the current credit record... 
                if ($cr_balance > 0) {
                    // ...show a self-submitting form consisting of an input for amount and a balance to apply,
                    //  plus the hidden fields this needs. 
                    echo '<td>';
                    echo '<form id="invForm' . $inv->getInvoiceId() . '" name="inv_' . $inv->getInvoiceId() . '"><input type="hidden" name="act" value="applypart">' . 
                         '<input type="hidden" name="creditRecordId" value="' . intval($creditRecordId) . '">' . 
                         '<input type="hidden" name="invoiceId" value="' . $inv->getInvoiceId() . '">';
                        echo '<input type="text" name="part" value="" size="5">';
                    echo '</td>';
                    echo '<td><input type="submit" id="applyPart'.$inv->getInvoiceId() . '" value="App.Part."></form></td>';				
                }
            echo '</tr>';
        echo '</table>';
    }
}

// We need two creditRecord HTML tables here: one for creditRecords that exactly match
// a particular value, one for those that have a different positive balance.
// INPUT $invoice - Invoice object
// INPUT $paidPortionOfInvoice -  (U.S. currency, two digits past the decimal point)
// INPUT $records - Array of creditRecord objects.
// INPUT $invoiceBalance - balance of this invoice (U.S. currency, two digits past the decimal point)
// INPUT $matchBalance - Boolean; true => credit record must match $invoiceBalance; false => credit record must differ from $invoiceBalance   
function writeCreditRecordTable($invoice, $paidPortionOfInvoice, $records, $invoiceBalance, $matchBalance) {
    $creditRecordTypes = CreditRecord::creditRecordTypes();
?>    
    <table class="creditRecords" border="1" cellpadding="0" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th bgcolor="#cccccc">Type</th>
                <th bgcolor="#cccccc">ID&nbsp;/&nbsp;Ref#</th>
                <th bgcolor="#cccccc">Amount</th>
                <th bgcolor="#cccccc">Cred Date</th>
                <th bgcolor="#cccccc">Rec From</th>
                <th bgcolor="#cccccc">Pmts</th>
                <th bgcolor="#cccccc">CR&nbsp;Bal</th>
                <th bgcolor="#cccccc">Actions</th>
                <th bgcolor="#cccccc">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <?php
                foreach ($records as $record) {
                    $bal = $record->getBalance();
                    if ( $bal <= 0) {
                        continue; // zero or (can this happen?) negative balance, skip it
                    }
                    if ( ($bal == $invoiceBalance) != $matchBalance ) {
                        continue; // not an exact match to the balance on this invoice
                    }
                    
                    $creditDate = date_parse($record->getCreditDate());
                    $creditDateField = '';
                    
                    if (is_array($creditDate)) {
                        if (isset($creditDate['year']) && isset($creditDate['day']) && isset($creditDate['month'])) {
                            $creditDateField = intval($creditDate['month']) . '/' . intval($creditDate['day']) . '/' . intval($creditDate['year']);
                            if ($creditDateField == '0/0/0') {
                                $creditDateField = '';
                            }
                        }
                    }
                    
                    $from = $record->getReceivedFrom();
                    $from = trim($from);
                    $from = str_replace("\n","<br>",$from);
            
                    $type = '';
                    if (array_key_exists(intval($record->getCreditRecordTypeId()), $creditRecordTypes)) {
                        $type = $creditRecordTypes[$record->getCreditRecordTypeId()]['name'];
                    }    
            
                    // one row per credit record
                    echo '<tr>';
                        // Type: creditRecordType name, e.g. "Check", "PayPal" 
                        echo '<td>' . $type . '</td>';
                        
                        // Ref#: referenceNumber of creditRecord: such as a check # or a PayPal payment number 
                        echo '<td style="text-align:center">CR ' . $record->getCreditRecordId() . ' / ' . $record->getReferenceNumber() . '</td>';
                        
                        // Amount: dollar amount, leading dollar sign, 2 digits past the decimal
                        echo '<td style="text-align:right"><pre>$' . number_format($record->getAmount(),2) . '</pre></td>';
                    
                        // Cred Date: month/day/year; "0/0/0" if unavailable, invalid, etc.
                        echo '<td style="text-align:center">' . $creditDateField . '</td>';
                        
                        // Rec From: receivedFrom of creditRecord; any newlines in value are replaced by HTML BR element 
                        echo '<td>' . $from . '</td>';
                                                
                        // Pmts: subtable, only present if there are payments for this creditRecord in DB table invoicePayment. 
                        // No headers, a row for each payment with the following columns: 
                        //  "Paid", dollar amount, leading dollar sign, 2 digits past the decimal
                        //  "To", link to open the page for the appropriate invoice in a new tab/window; 
                        //   displays "Inv: invoiceId" (>>> JM: at least as of 2019-03, that page requires 
                        //   higher permissions than this: admin-level permission for invoices. 
                        //   >>>00001 Is that difference intentional?)
                        //
                        echo '<td>';    
                            $payments = $record->getPayments();            
                            if (isset($payments['invoices'])) {
                                $pays = $payments['invoices'];
                                if (count($pays)) {
                                    echo '<table class="payments" border="0" cellpadding=1" cellspacing="0">';
                                    foreach ($pays as $payment) {
                                        echo '<tr>';
                                        echo '<td>Paid&nbsp;</td>';
                                        if ($payment['amount'] >= 0) {                                            
                                            echo '<td>$' . number_format($payment['amount'], 2) . '</td>';
                                        } else {
                                            echo '<td>-$' . number_format(abs($payment['amount']), 2) . '</td>';
                                        }
                                        echo '<td>To&nbsp;';
                                        echo '<td>';
                                        echo '<a id="linkInv'.$payment['invoiceId'].'"  target="_blank" href="/invoice/' . $payment['invoiceId'] . '">Inv:' . $payment['invoiceId'] . '</a>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }   
                                    echo '</table>';
                                }
                            }
                    
                            // still within "Pmts", another subtable, only present if there are creditMemos for this 
                            // creditRecord in DB table creditMemo. No headers, a row for each creditMemo with the following columns:
                            //  * "Paid", dollar amount, leading dollar sign, 2 digits past the decimal
                            //  * "To", link to multi.php?tab=8; displays "Memo:creditMemoId" 
                            if (isset($payments['creditmemos'])) {
                                $pays = $payments['creditmemos'];            
                                if (count($pays)) {   
                                    echo '<table class="creditMemos" border="0" cellpadding=1" cellspacing="0">';    
                                    foreach ($pays as $payment) {
                                        echo '<tr>';
                                        echo '<td>Paid&nbsp;</td>';
                                        echo '<td>$' . $payment['amount'] . '</td>';
                                        echo '<td>To&nbsp;';
                                        echo '<td>';
                                            echo '<a id="linkMulti'.$payment['creditMemoId'].'"  target="_blank" href="/multi.php?tab=8">Memo:' . $payment['creditMemoId'] . '</a>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    echo '</table>';
                                }
                            }
                        echo '</td>';
                    
                        // CR Bal: if the credit record has a zero balance, just '0'. (In fact, that will never happen here, but leave this
                        //   code in place for clarity.)
                        // If the credit record has a nonzero balance, that balance (should be dollars with two digits past the decimal point)... 
                        //    >>>00013 As of 2019-03-15 I (JM) haven't seen good data on which to test this, not sure it's really been exercised.
                        //    >>>00026: 2019-03-15 JM: one for invoice 8015 and displays as '0.60000000000036'; I presume that's a bug. 
                        //      Martin following up in late 2018, said probably QuickBooks-related, but not immediately obvious what's going on, 
                        // ... followed by a parenthesized link to open /fb/creditmemo.php?creditRecordId=creditRecordId in a fancybox; 
                        // link is displayed as "memo". 
                        echo '<td>';
                        if (is_numeric($record->getAmount()) && is_numeric($record->getPaymentsTotal())) {
                            echo $bal;
                            if ($bal != 0) {
                                echo '&nbsp;(<a id="linkMemo'.$record->getCreditRecordId().'" data-fancybox-type="iframe" class="fancyboxIframe"  href="/fb/creditmemo.php?creditRecordId=' . $record->getCreditRecordId() . '">memo</a>)';
                            }
                        } else {
                            echo 'n/a';
                        }
                        echo '</td>';
                        
                        // "Actions"
                        echo '<td>';
                        offerActions($invoice, $paidPortionOfInvoice, $record->getCreditRecordId(), $record->getPaymentsTotal(), $record->getBalance());
                        echo '</td>';
                
                        // (no header) a link to open /fb/creditrecord.php for this creditRecordId in this fancybox instead of the current page; link is 
                        //  displayed as "View" with a hook arrow. 
                        echo '<td>';
                        echo '<a id="linkCreditRecordId'.$record->getCreditRecordId().'" href="/fb/creditrecord.php?creditRecordId=' . $record->getCreditRecordId() . '">View ' . SYMBOL_LOAD_IN_SAME_FRAME . '</a>';
                        echo '</td>';
            
                    echo '</tr>' . "\n";
                }
            ?>
        </tbody>
    </table>
<?php            
} // END function creditRecordTable


// ----------------------------

$db = DB::getInstance(); 
    
$invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;    
$invoice = new Invoice($invoiceId, $user);

if ($act == 'applypart' || $act == 'applybal' || $act == 'applyfull' || $act == 'reverse') {
    $creditRecordId = isset($_REQUEST['creditRecordId']) ? intval($_REQUEST['creditRecordId']) : 0;
    $cr = new CreditRecord($creditRecordId);
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
            $invoice->pay('payBal', $creditRecordId);
        }
    }
    
    if ($act == 'applyfull') {
        // Call the pay method on the relevant invoice object, passing in "payFull" and the relevant creditRecordId, then redisplay. 
        if ($invoice->getInvoiceId()) {
            $invoice->pay('payFull', $creditRecordId);
        }
    }
    
    if ($act == 'reverse') {
        // Call the pay method on the relevant invoice object, passing in "reversePay", the relevant creditRecordId, and the (negative) part value; then redisplay.
        $part = isset($_REQUEST['part']) ? $_REQUEST['part'] : '';    
        if ($invoice->getInvoiceId()) {
            $invoice->pay('reversePay', $creditRecordId, $part);
        }
    }
    
    if ($status == 'OK') {
        // Success. Now reload in a way that a refresh won't repeat the action.
        header("Location: /fb/invoice.php?invoiceId=$invoiceId");
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

<?php
if (intval($invoice->getInvoiceId()) == 0) {
    echo "<p>$invoiceId is not a valid invoiceId\n";    
} else {
    $wo = new WorkOrder($invoice->getWorkOrderId());
    $job = new Job($wo->getJobId());
    // $invoiceStatuses = Invoice::getInvoiceStatusDataArray(); // Deleted 2020-05-20 JM: set but never referenced.
    
    echo "<h3>Invoice # <a id=\"invoiceLink{$invoiceId}\" target=\"_blank\" href=\"{$invoice->buildLink()}\">$invoiceId</a> ({$invoice->getNameOverride()}) " .
         "[WO] <a id=\"woLink{$wo->getWorkOrderId()}\" target=\"_blank\" href=\"{$wo->buildLink()}\">{$wo->getDescription()}</a> " .
         "[J] <a id=\"jobLink{$job->getNumber()}\" target=\"_blank\" href=\"{$job->buildLink()}\">{$job->getName()} ({$job->getNumber()})</a>" .
         "</h3>\n";
    
    
    echo '<table class="Invoice" border="1" cellpadding="0" cellspacing="0" width="100%">' . "\n";
        echo '<thead><tr>' . "\n";
            echo '<th bgcolor="#cccccc">Invoice Date</th>' . "\n";
            echo '<th bgcolor="#cccccc">Orig. Total</th>' . "\n";
            echo '<th bgcolor="#cccccc">Adjusted Total</th>' . "\n";
            echo '<th bgcolor="#cccccc">History</th>' . "\n";          
            echo '<th bgcolor="#cccccc">Profiles</th>' . "\n";
            echo '<th bgcolor="#cccccc">Status</th>' . "\n";
        echo '</tr></thead>' . "\n";
        echo '<tbody><tr>' . "\n";
            // "Invoice Date": month/day/year, no leading zeroes 
            echo '<td>' . date("n/j/Y", strtotime($invoice->getInvoiceDate())) . '</td>' . "\n";
            
            // "Original Total": from invoice
            echo "<td>{$invoice->getTotal()}</td>\n";
            
            // "Adjusted Total": from invoice
            echo "<td>{$invoice->getTriggerTotal()}</td>\n";
            
            // "Hist": payments, nested table
            echo '<td>';
                $paidPortionOfInvoice = writeInvoicePaymentsTable($invoice);
            echo '</td>' . "\n";
            
            // "Profile(s)": nested table like the "Profiles" column in the per-invoice table above.
            echo '<td>';
                writeBillingProfilesTable($invoice);
            echo '</td>' . "\n";
            
            // "Status": invoice status
            // echo '<td>' . $invoice->getStatusData()['statusName'] . '</td>' . "\n"; // REPLACED 2020-05-22 JM            
            echo '<td>' . $invoice->getStatusName() . '</td>' . "\n"; // REPLACEMENT 2020-05-22 JM
        echo '</tr></tbody>' . "\n";
    echo '</table>' . "\n";
    
    if ($invoice->getTriggerTotal() > $paidPortionOfInvoice) {
        // Selects all creditRecords in backward chronological order (relying on the fact that primary key creditRecordId increases monotonically).
        $records = [];
        $query = "SELECT * FROM " . DB__NEW_DATABASE . ".creditRecord ORDER BY creditRecordId DESC;";
        $result = $db->query($query);
        if (!$result) {
            echo '<p>Cannot access creditRecord table</p>';
            $logger->errorDb('1581014995', '', $db);
        } else {
            while ($row = $result->fetch_assoc()){
                $records[] = new CreditRecord($row);
            }
        }
        echo "<h3>Credit records with exactly this balance</h3>\n";
        writeCreditRecordTable($invoice, $paidPortionOfInvoice, $records, $invoice->getTriggerTotal(), true);
        echo "<h3>Other credit records with positive balance</h3>\n";
        writeCreditRecordTable($invoice, $paidPortionOfInvoice, $records, $invoice->getTriggerTotal(), false);
    } else {
        echo "<h3>Paid in full.<h3>\n";
    }
}

include '../includes/footer_fb.php';
?>
</body>
</html>