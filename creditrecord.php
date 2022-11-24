<?php 
/*  creditrecord.php

    EXECUTIVE SUMMARY: This is a top-level page. Displays or updates info about a creditRecord
    
    PRIMARY INPUT: $_REQUEST['creditRecordId'] identifies a credit record.
*/

require_once './inc/config.php';
// >>>00001 Oddly, includes './inc/perms.php' but as of 2019-03 doesn't check permissions.
require_once './inc/perms.php';


$creditRecordId = isset($_REQUEST['creditRecordId']) ? intval($_REQUEST['creditRecordId']) : 0;
$creditRecord = new CreditRecord($creditRecordId, $user);
if (!intval($creditRecord->getCreditRecordId())){
    // No valid creditRecordId, redirects to '/'. 
	header("Location: /");
}

$crumbs = new Crumbs($creditRecord, $user);

include_once BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title = 'Credit Record $creditRecordId';\n</script>\n";

// [Martin comment:] http://sssnew.com.martin.devel/creditrecord.php?creditRecordId=274

$types = CreditRecord::creditRecordTypes();
?>


<div id="container" class="clearfix">
	<div class="main-content">
		<h1>
		Credit Record 
		</h1>
	
		<?php 
		// Throughout, dates are in 'm/d/y' form, and a missing or invalid date shows as '0/0/0'. Otherwise, missing values are blank.
		$creditDate = date_parse($creditRecord->getCreditDate());
		$creditDateField = '';
		
		if (is_array($creditDate)){
			if (isset($creditDate['year']) && isset($creditDate['day']) && isset($creditDate['month'])){
		
				$creditDateField = intval($creditDate['month']) . '/' . intval($creditDate['day']) . '/' . intval($creditDate['year']);
				if ($creditDateField == '0/0/0'){
					$creditDateField = '';
				}
			}
		}
		
		$depositDate = date_parse($creditRecord->getDepositDate());
		$depositDateField = '';
		
		if (is_array($depositDate)){
			if (isset($depositDate['year']) && isset($depositDate['day']) && isset($depositDate['month'])){
		
				$depositDateField = intval($depositDate['month']) . '/' . intval($depositDate['day']) . '/' . intval($depositDate['year']);
				if ($depositDateField == '0/0/0'){
					$depositDateField = '';
				}
			}
		}		
		
		// Table, where the left column consists of headers and the right column is the value 
		echo '<table border="0" cellpadding="4" cellspacing="2">';		
            // Type: name of the creditRecordType, e.g. 'Check', 'Paypal' 
            echo '<tr>';
                echo '<th>Type</th>';
                echo '<td>' . $types[$creditRecord->getCreditRecordTypeId()]['name'] . '</td>';
            echo '</tr>';
            
            //Ref #: Arbitrary reference number associated with a creditRecord, e.g. a check number, PayPal transaction number, etc. 
            echo '<tr>';
            echo '<th>Ref #</th>';
                $ref = $creditRecord->getReferenceNumber();
                $ref = trim($ref);
                if (!strlen($ref)){
                    $ref = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                }
                echo '<td>' . $ref . '</td>';
            echo '</tr>';		
            
            //Amount: U.S. currency; formatted with 2 digits past the decimal point. 
            echo '<tr>';
            echo '<th>Amount</th>';
            $amt =  $creditRecord->getAmount();
            $amt = trim($amt);
            if (!strlen($amt)){
                $amt = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            echo '<td>' . $amt . '</td>';
            echo '</tr>';
            
            // Credit Date: When the credit is applied, "on the books" 
            echo '<tr>';
            echo '<th>Credit Date</th>';
            echo '<td>' . $creditDateField . '</td>';
            echo '</tr>';
            
            // Deposit Date: Date of actual deposit to the bank, if relevant (e.g. for a check). 
            echo '<tr>';
            echo '<th>Deposit Date</th>';
            echo '<td>' . $depositDateField . '</td>';
            echo '</tr>';
            
            // Received From: Typically what is written on a check, name of a PayPal account, etc. 
            // Typically a company name, but it's free-form human-written text. 
            echo '<tr>';
            echo '<th>Received From</th>';
            echo '<td>' . htmlspecialchars($creditRecord->getReceivedFrom()) . '</td>';
            echo '</tr>';		
            
            // Image: If filename for this credit record has extension 'png', 'jpg', 'jpeg', or 'gif', 
            //  use credrec_getuploadfile.php?f=fileName to display the relevant image. 
            echo '<tr>';
            echo '<th>Image</th>';		
            $ok = array('png','jpg','jpeg','gif');		
            $fn = $creditRecord->getFileName();
            $parts = explode(".", $fn);
            if (count($parts) > 1){
                $ext = strtolower(end($parts));
                if (in_array($ext, $ok)){
                    echo '<td><img src="credrec_getuploadfile.php?f=' . rawurlencode($creditRecord->getFileName()) . '" width="75%"></td>';
                } else {
                    echo '<td>&nbsp;</td>';
                }
            } else {
                echo '<td>&nbsp;</td>';
            }		
            echo '</tr>';
            
            //Notes: Open-ended note about the credit record, most commonly an address 
            echo '<tr>';
            echo '<th>Notes</th>';
            echo '<td>' . htmlspecialchars($creditRecord->getNotes()) . '</td>';
            echo '</tr>';		
		echo '</table>';
		
		$payments = $creditRecord->getPayments();
		$invoices = $payments['invoices'];
		
		echo '<h2>Payments</h2>';
		
		// Table, this time with a more conventional format using column headers. 
		echo '<table border="0" cellpadding="4" cellspacing="2">';
            echo '<tr>';
                echo '<th>Invoice</th>';
                echo '<th>Amount</th>';
                echo '<th>Day</th>';
            echo '</tr>';
            
            // For each invoice that has a payment associated with this credit record
            foreach ($invoices as $invoice) {                
                $inv = new Invoice($invoice['invoiceId']);
                
                echo '<tr>';
                    // Invoice: invoiceId, linked to HTML page for this invoice.
                    echo '<td><a id="linkInoivce'. $inv->getInvoiceId() .'" href="' . $inv->buildLink() . '">' . $inv->getInvoiceId()  . '</a></td>';
                    
                    // Amount: invoice amount. U.S. currency; formatted with 2 digits past the decimal point. 
                    // >>>00014, >>>00025 JM: I'm a bit confused here: won't this be misleading if this creditRecord 
                    //  was used in partial payment of an invoice? Shouldn't we want something related to this 
                    //  particular payment on the invoice? Martin said in email 2018-12-04 that he would look 
                    //  into that, but never did.
                    echo '<td>' . number_format($invoice['amount'],2) . ' </td>';
                    
                    // Day: 'n/j/Y' format is distinct from 'm/d/Y' in that it doesn't use leading zeroes.
                    // Date invoice inserted. (NOTE, not the date payment was made). 
                    echo '<td>' . date("n/j/Y", strtotime($invoice['inserted'])) . '</td>';
                echo '</tr>';
            }
		echo '</table>';		
		?>
	</div>
</div>

<?php 
include_once BASEDIR . '/includes/footer.php';
?>