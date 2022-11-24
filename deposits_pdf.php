<?php 
/*  deposits_pdf.php

    EXECUTIVE SUMMARY: A PDF of a way of looking at creditRecords, parallel to deposits.php,
    >>>00006 with which it could probably share more code. 
    Print information about all creditRecords that were deposited in a particular time frame, including 
    what payments have been made against them. Requires Admin-level Payments permission.

    PRIMARY INPUTs: 
     * $_REQUEST['fromDate'], $_REQUEST['toDate'] in month/day/year form.
     * $_REQUEST['creditRecordTypeId']: As of 2019-03, creditRecordTypeId values are not defined in a DB table, 
        they come from inc/config.php and are further elaborated in /inc/classes/CreditRecord.class.php. 
        E.g. CRED_REC_TYPE_CHECK, CRED_REC_TYPE_PAYPAL.

    Example inputs: 
        $_REQUEST['fromDate'] = '2019-06-06';
        $_REQUEST['toDate'] = '2019-12-31';
        $_REQUEST['creditRecordTypeId'] = 1; // CRED_REC_TYPE_CHECK
        
    OUTPUTs to file 'deposits_' . $fromDate . '_' .  $toDate . '_' . $credRecTypes[$creditRecordTypeId]['name'] . '.pdf'    
*/

require_once './inc/config.php';
require_once './inc/perms.php';

// Die if not admin-level payments permission.
$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm){
    die();
}

// See note at top of file about date formats & what happens on "bad" dates.
$fromDate = isset($_REQUEST['fromDate']) ? $_REQUEST['fromDate'] : '';
$toDate = isset($_REQUEST['toDate']) ? $_REQUEST['toDate'] : '';
$creditRecordTypeId = isset($_REQUEST['creditRecordTypeId']) ? intval($_REQUEST['creditRecordTypeId']) : 0;

// >>>00002 As usual, it might make more sense to indicate that the input is bad, and probably log something.

$db = DB::getInstance();

// >>>00007 BEGIN redundant code, should get rid of this 
$toDate = isset($_REQUEST['toDate']) ? $_REQUEST['toDate'] : '';
$fromDate = isset($_REQUEST['fromDate']) ? $_REQUEST['fromDate'] : '';
$creditRecordTypeId = isset($_REQUEST['creditRecordTypeId']) ? intval($_REQUEST['creditRecordTypeId']) : 0;
// >>>00007 END redundant code, should get rid of this

// Does a SQL query to select all creditRecords with the relevant creditRecordTypeId 
//  and depositDate between fromDate and toDate, inclusive. 
// Result is ordered by depositDate, creditRecordId (the latter being effectively 
//  chronological by the time the row was inserted). 
// We then instantiate a CreditRecord object for each of these.

$query = " select * from " . DB__NEW_DATABASE . ".creditRecord ";
$query .= " where creditRecordTypeId = " . intval($creditRecordTypeId) . " ";
$query .= " and depositDate between ";
$query .= " '" . $db->real_escape_string(date("Y-m-d", strtotime($fromDate))) . "' ";
$query .= " and ";
$query .= " '" . $db->real_escape_string(date("Y-m-d", strtotime($toDate))) . "' ";
$query .= " order by depositDate, creditRecordId ";

if ($result = $db->query($query)) {
    if ($result->num_rows > 0){
        while ($row = $result->fetch_assoc()){
            $deposits[] = new CreditRecord($row);
        }
    }
}  // >>>00002 ignores failure on DB query!

$pdf = new PDF('P','mm','Letter');
//$pdf->cMargin = 0;
$pdf->AddFont('Tahoma','','Tahoma.php');
$pdf->AddFont('Tahoma','B','TahomaB.php');
$pdf->AliasNbPages();
$pdf->AddPage();

$subTotal = 0;
$currentDay = 0;
$grandTotal = 0;

$color = 'eeeeee';
$lastColor = '';

$credRecTypes = CreditRecord::creditRecordTypes();

$pdf->SetY(5);
$pdf->SetFont('Tahoma','B',13);
$pdf->setX(5);
$pdf->cell(0, 6, 'Deposit Summary', 0, 1, 'C');

$str = $credRecTypes[$creditRecordTypeId]['name'] . " : " . date("n/j/Y", strtotime($fromDate)) . " - " . date("n/j/Y", strtotime($toDate));

$border = 0;

//$pdf->SetY(5); // commented out by Martin before 2019
$pdf->SetFont('Tahoma','B',12);
$pdf->setX(5);
$pdf->cell(0, 6, $str, $border, 0, 'L');

$pdf->setY($pdf->GetY() + 7);

// Column headers
$pdf->SetFont('Tahoma','B',11);
$pdf->setX(10);
$pdf->cell(23, 6, 'Day', 'B', 0, 'L');
$pdf->cell(25, 6, 'Ref#', 'B', 0, 'L');
$pdf->cell(60, 6, 'Client', 'B', 0, 'L');
$pdf->cell(25, 6, 'Amount', 'B', 0, 'L');
$pdf->cell(25, 6, 'Date', 'B', 0, 'L');
$pdf->cell(25, 6, 'Day Sum', 'B', 1, 'L');

$pdf->SetY($pdf->GetY() - 3);

$pdf->SetFont('Tahoma','',10);

foreach ($deposits as $dkey => $deposit) {
    $day = date("n/j/Y", strtotime($deposit->getDepositDate()));
    
    // In the next line, the $dkey test prevents this from happening on the first row.
    if (($day != $currentDay) && (intval($dkey))) {
        // Before the start of a new day:
        //  * 4 blank columns
        //  * (no header) day just ended in "n/j/Y" form
        //  * "Daily Sum.": subtotal for day.
        $pdf->SetY($save2);
        $pdf->SetFont('Tahoma','',10);
        $pdf->setX(143);
        $pdf->cell(25, 6, $currentDay, $border, 0, 'L');
        
        $pdf->cell(25, 6, number_format($subTotal,2), $border, 2, 'L');
        
        $subTotal = 0;
    }
     
    if (is_numeric($deposit->getAmount())){
        $subTotal += $deposit->getAmount();
        $grandTotal += $deposit->getAmount();
    }

    $lastColor = $color;
    
    $pdf->setY($pdf->GetY() + 5);

    // "Day": month/day/year
    $pdf->SetFont('Tahoma','',10);
    $pdf->setX(10);
    $pdf->cell(23, 6, $day, $border, 0, 'L');

    // "Ref#": check #, PayPal payment number, etc., linked to the page for this Creditrecord
    $pdf->SetFont('Tahoma','',10);
    $pdf->setX(33);
    $pdf->cell(25, 6, $deposit->getReferenceNumber(), $border, 0, 'L');

    // "Client": This is a series of lines, separated by newlines. 
    // Each line corresponds to a payment made against this credit record, 
    //  as obtained by CreditRecord::getPayments(). For each payment, we use 
    //  the invoiceId to instantiate the appropriate Invoice object, then get 
    //  all of the billing profiles associated with that invoice, and for each 
    //  billing profile we unserialize the shadowBillingProfile to identify the company. 
    //  For each such company we then display the company name, linked to the page for that company.
    //
    // Because we use associative array $names, indexed by companyId, each company will be named only once.
    //  As of 2019-03, this is different from deposits.php.
    $payments = $deposit->getPayments();
    $invoices = $payments['invoices'];
    
    $names = array();
    
    foreach ($invoices as $ikey => $invoice) {
    	$i = new Invoice($invoice['invoiceId']);
        // The name getBillingProfiles is a bit misleading: the return, if not an empty array, 
        //  is a single-element array, containing an associative array with the canonical representation
        //  of a row from DB table invoiceBillingProfile (not BillingProfile); 
        // JM 2020-10-30 I've accordingly renamed some variables here to try to be bit clearer what is going on,
        //  in the process of introducing the shadowBillingProfile class. 
        $invoiceBillingProfiles = $i->getBillingProfiles();
        foreach ($invoiceBillingProfiles as $invoiceBillingProfile) {
            $shadowBillingProfile = new ShadowBillingProfile($invoiceBillingProfile['shadowBillingProfile']);
            $c = new Company($shadowBillingProfile->getCompanyId());
            $names[$c->getCompanyId()] = $c->getName();
        }
    }

    $str = '';
    
    foreach ($names as $nkey => $name) {
        if (strlen($str)) {
            $str .= "\n";
        }
        
        $str .= $name;
    }

    $save1 = $pdf->GetY();
    
    $cellw = 50;
    $width = $pdf->GetStringWidth($str);
    $pdf->SetX(58);
    $pdf->SetFont('Tahoma', '', 10);
    $pdf->MultiCell(60, 6, $str, $border, 'L');
    
    $save2 = $pdf->GetY();
    
    $pdf->SetY($save1);
    
    // "Amount": Credit record amount. U.S. currency; formatted with 2 digits past the decimal point. 
    $pdf->SetFont('Tahoma','',10);
    $pdf->setX(118);
    $pdf->cell(25, 5, $deposit->getAmount(), $border, 0, 'L');
    
    $pdf->SetY($save2 - 4);
    
    // last 2 columns are blank, we only use these at end of day.
    
    // After last row, we handle that as end of a day.
    if (  $dkey == (count($deposits) - 1)   ) {
        $pdf->SetY($save2);
        $pdf->SetFont('Tahoma','',10);
        $pdf->setX(143);
        $pdf->cell(25, 6, $currentDay, $border, 0, 'L');
        
    	$pdf->cell(25, 6, number_format($subTotal,2), $border, 2, 'L');
        
        $subTotal = 0;
    }
     
    $currentDay = $day;
}

// If there were any deposits, we add a "grand total" line, using only the last column.
if (count($deposits)) {
    $pdf->SetY($pdf->GetY() + 3);
    $pdf->SetFont('Tahoma','B',12);
    $pdf->setX(168);
    $pdf->cell(25, 6, number_format($grandTotal,2), $border, 0, 'L');
}

$credRecTypes = CreditRecord::creditRecordTypes();

//$pdf->Output('Contract.pdf','D'); // commented out by Martin before 2019
$pdf->Output('deposits_' . $fromDate . '_' .  $toDate . '_' . $credRecTypes[$creditRecordTypeId]['name'] . '.pdf', 'D');

?>