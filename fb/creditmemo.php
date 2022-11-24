<?php
/*  fb/creditmemo.php

    REQUIRES admin-level revenue permissions.

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a creditMemo
    As of 2019-04, credit memos are still a bit of a work in progress, among
     the parts of the system most likely to undergo further change.
     In particular, no code here yet for CRED_MEMO_TYPE_OUT. 
     
    PRIMARY INPUT: $_REQUEST['creditRecordId'] identifies a row in the creditRecord DB table. 
    Input is used to create a CreditRecord object, whose contents are then displayed.
    The idea is to create a creditMemo against this creditRecord.
    
    Optional $_REQUEST['act']. Only supported value is 'addmemo', which uses $_REQUEST['amount'] and $_REQUEST['companyId'].
*/

include '../inc/config.php';
include '../inc/perms.php';

/*
// BEGIN MARTIN COMMENT
cr_overpay

define ("CRED_MEMO_TYPE_IN",1);
define ("CRED_MEMO_TYPE_OUT",2);

/////////////////////////////////////////
///////////////////////////////////////////

types : inbound, outbound


outbound   is a positive credit memo ... carries credit record id in the "id" field 
           does this actually need the reference to original credit memo that can derive the original credit record ???

inbound     goes into credit memo as a negative number (from overpayment or a re-payment)
    -- carries the original credit record id in the "id" field then also a companyid   

inbound can be used to pay an invoice later (or create an outbound record)

the inbound credit memos can be displayed on Tab 7  (unless they have been used up by equal sum of outbounds)
     mesh this into existing payment functionality somehow ("view" link on tab 7)
     these payments .. whatever form they take .. will need to also be made into an outbound credit memo

/////////////////////////////////////////
///////////////////////////////////////////

payout goes into a credit memo as a negative number
and carries refering credit memo id
then needs to define who gets the money ... i.e if cutting a check (this would be company)
or goes to pay an invoice


create table creditMemo(
    creditMemoId     int unsigned not null primary key auto_increment,
    creditMemoTypeId tinyint unsigned not null,
    id               int unsigned not null,
    amount           decimal(10,2),
    personId         int unsigned not null,
    inserted         timestamp not null default now()
)

create index ix_credmem_cmtid on creditMemo(creditMemoTypeId);
create index ix_credmem_id on creditMemo(id);

// END MARTIN COMMENT
*/

$checkPerm = checkPerm($userPermissions, 'PERM_REVENUE', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    // Insufficient permission, out of here.
    // >>>00002 should log attempt by someone with insufficient permission.
    die();
}

// Validate creditRecordId and create CreditRecord object 
$creditRecordId = isset($_REQUEST['creditRecordId']) ? intval($_REQUEST['creditRecordId']) : 0;
$record = new CreditRecord($creditRecordId, $user);
$creditRecordId = $record->getCreditRecordId();
if (!intval($creditRecordId)) {
    // >>>00002 should log attempt by someone with invalid creditRecordId
    die();
}

if ($act == 'addmemo') {
    $db = DB::getInstance();
    
    $amount = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
    $companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
    
    if (is_numeric($amount)) {
        if ($amount != 0) {            
            $amount = abs($amount) * -1;
            
            $query = "insert into " . DB__NEW_DATABASE . ".creditMemo(creditMemoTypeId, id, amount, companyId, personId) values(";
            $query .= " " . intval(CRED_MEMO_TYPE_IN) . " ";
            $query .= " ," . intval($creditRecordId) . " ";
            $query .= " ," . $db->real_escape_string($amount) . " ";
            $query .= " ," . intval($companyId) . " ";
            $query .= " ," . intval($user->getUserId()) . ") ";

            $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, haven't noted each instance.
            
            // Wait a second & close fancybox.
            ?>
            <script type="text/javascript">
                setTimeout(function() { parent.$.fancybox.close(); }, 1000);
            </script>
            <?php
        } // >>>00002 zero amount deserves to be logged
    } // >>>00002 non-numeric amount deserves to be logged
} // END if ($act == 'addmemo') {

echo '<h3>Credit Record</h3>';
echo '<table border="0" cellpadding="4" cellspacing="2">';
    echo '<tr>';
        echo '<td bgcolor="#cccccc"><b>Reference Number</b></td>';
        echo '<td>' . $record->getReferenceNumber() . '</td>'; // E.g. check # or Paypal payment #
    echo '</tr>';
    echo '<tr>';
        echo '<td bgcolor="#cccccc"><b>Amount</b></td>';
        echo '<td>' . $record->getAmount() . '</td>';
    echo '</tr>';
    echo '<tr>';
        echo '<td bgcolor="#cccccc"><b>Credit Date</b></td>';
        echo '<td>' . $record->getCreditDate() . '</td>';
    echo '</tr>';
    echo '<tr>';
        echo '<td bgcolor="#cccccc"><b>Received From</b></td>';
        echo '<td>' . $record->getReceivedFrom() . '</td>'; // Typically name of company credit was received from but this is very open-ended: 
                                                            // what was written on a check, or name on a PayPal account. 
    echo '</tr>';
    echo '<tr>';
        echo '<td bgcolor="#cccccc"><b>Notes</b></td>';
        echo '<td>' . $record->getNotes() . '</td>'; // Open-ended, in practice usually an address 
    echo '</tr>';
echo '</table>';

echo '<h3>Payments</h3>';
//$cr_paid_amount = 0; // COMMENTED OUT BY MARTIN BEFORE 2019

$crps = $record->getPayments();
$pays = array();

if (isset($crps['invoices'])) {
    $pays = $crps['invoices'];
}

// Table showing past payments on this credit record (based on rows in DB table InvoicePayment for this credit record)
echo '<table border="0" cellpadding="4" cellspacing="2">';
echo '<tr>';
    echo '<th bgcolor="#cccccc">InvoiceId</th>';
    echo '<th bgcolor="#cccccc">Payment Amount</th>';
    echo '<th bgcolor="#cccccc">Profile</th>';
    echo '<th bgcolor="#cccccc">Company (for profile)</th>';
echo '</tr>';

$candidates = array();

foreach ($pays as $crp) {
    echo '<tr>';
        // "InvoiceId": Primary key into DB table Invoice, linked to open page for this invoice in a new tab 
        $invoice = new Invoice($crp['invoiceId'], $user);
        // The name getBillingProfiles is a bit misleading: the return, if not an empty array, 
        //  is a single-element array, containing an associative array with the canonical representation
        //  of a row from DB table invoiceBillingProfile (not BillingProfile); 
        // JM 2020-10-30 I've accordingly renamed some variables here to try to be bit clearer what is going on,
        //  in the process of introducing the shadowBillingProfile class. 
        $invoiceBillingProfiles = $invoice->getBillingProfiles();
        echo '<td><a id="linkInvBilling' . $invoice->getInvoiceId(). '" target="_blank" href="' . $invoice->buildLink() . '">' . $invoice->getInvoiceId() . '</a></td>';
        
        // "Payment Amount": U.S. currency, 2 digits past the decimal point
        echo '<td>' . $crp['amount'] . '</td>';
        
        // "Profile": A list of of contacts. We go through quite a lot to get this:
        // Starting from the Invoice object relevant to this payment, we get an array of billing profiles; only the first concerns us. 
        //   * $invoiceBillingProfiles is an array; Martin said as of 2018-10 that it is still a bit up in the air as to whether an invoice will be 
        //     able to have more than one billing profile, though we are leaning toward only one.
        // We then verify that has an invoiceBillingProfileId and a shadowBillingProfile. If not, we are done and there is nothing to display in this column.
        //  The shadow billing profile exists to "freeze" the billing profile at the moment when the invoice was created; we don't want the invoice
        //  to reflect later changes to the billing profile, because it the invoice is a static document that is sent externally.
        // Assuming we make it this far, we unserialize the shadowBillingProfile, then go through the shadow profile (an associative array) 
        //  concatenating any of the following we find, with '::' as a separator between them:
        //   * 'companyPersonId': use formatted name of person
        //   * 'personEmailId': use email address
        //   * 'companyEmailId': use email address
        //   * 'personLocationId': use formatted address; commas instead of newlines
        //   * 'companyLocationId': use formatted address; commas instead of newlines 
        echo '<td>';        
            $billingProfileId = 0;
            if (is_array($invoiceBillingProfiles)) {
                $invoiceBillingProfile = $invoiceBillingProfiles[0];                
                if (is_array($invoiceBillingProfile)) {
                    if (isset($invoiceBillingProfile['invoiceBillingProfileId'])) {
                        if (isset($invoiceBillingProfile['shadowBillingProfile'])) {
                            $shadowBillingProfile = new ShadowBillingProfile($invoiceBillingProfile['shadowBillingProfile']);
                            
                            $str = '';                            
                            $db = DB::getInstance();                            
                            if ($shadowBillingProfile) {
                                if ($shadowBillingProfile->getCompanyPersonId()) {
                                    $cp = new CompanyPerson($shadowBillingProfile->getCompanyPersonId());
                                    $person = $cp->getPerson();
                                    if (strlen($str)) {
                                        $str .= " :: ";
                                    }
                                    $str .= $person->getFormattedName(1);
                                }
                                if ($shadowBillingProfile->getPersonEmailId()) {
                                    $query = "SELECT emailAddress FROM " . DB__NEW_DATABASE . ".personEmail ";
                                    $query .= "WHERE personEmailId = " . $shadowBillingProfile->getPersonEmailId() . ";";
                                    $result = $db->query($query);
                                    if ($result) {
                                        if ($result->num_rows > 0) {
                                            $row = $result->fetch_assoc();
                                            if (strlen(trim($row['emailAddress']))) {
                                                if (strlen($str)) {
                                                    $str .= " :: ";
                                                }
                                                $str .= trim($row['emailAddress']);
                                            }
                                        }
                                    }
                                }
                                if ($shadowBillingProfile->getCompanyEmailId()) {
                                    $query = "SELECT emailAddress FROM " . DB__NEW_DATABASE . ".companyEmail ";
                                    $query .= "WHERE companyEmailId = " . $shadowBillingProfile->getCompanyEmailId() . ";";
                                    $result = $db->query($query);
                                    if ($result) {
                                        if ($result->num_rows > 0) {
                                            $row = $result->fetch_assoc();
                                            if (strlen(trim($row['emailAddress']))) {
                                                if (strlen($str)) {
                                                    $str .= " :: ";
                                                }
                                                $str .= trim($row['emailAddress']);
                                            }
                                        }
                                    }
                                }
                                if ($shadowBillingProfile->getPersonLocationId()) {
                                    $query = "SELECT locationId FROM  " . DB__NEW_DATABASE . ".personLocation "; 
                                    $query .= "WHERE personLocationId = " . $shadowBillingProfile->getPersonLocationId() . ";";
                                    $result = $db->query($query);
                                    if ($result) {
                                        if ($result->num_rows > 0) {
                                            $row = $result->fetch_assoc();
                                            $loc = new Location($row['locationId']);
                                            $add = $loc->getFormattedAddress();
                                            if (strlen(trim($add))) {
                                                $commas = str_replace("\n", "," , trim($add));
                                                if (strlen($str)) {
                                                    $str .= " :: ";
                                                }
                                                $str .= $commas;
                                            }
                                        }
                                    }
                                }
                                if ($shadowBillingProfile->getCompanyLocationId()) {
                                    $query = "SELECT locationId FROM  " . DB__NEW_DATABASE . ".companyLocation "; 
                                    $query .= "WHERE companyLocationId = " . $shadowBillingProfile->getCompanyLocationId() . ";";
                                    $result = $db->query($query);
                                    if ($result) {
                                        if ($result->num_rows > 0) {
                                            $row = $result->fetch_assoc();
                                            $loc = new Location($row['locationId']);
                                            $add = $loc->getFormattedAddress();
                                            if (strlen(trim($add))) {
                                                $commas = str_replace("\n", "," , trim($add));
                                                if (strlen($str)) {
                                                    $str .= " :: ";
                                                }
                                                $str .= $commas;
                                            }
                                        }
                                    }
                                }
                            }
                            echo $str;
                        }
                    }
                }
                $billingProfileId = intval($invoiceBillingProfile['billingProfileId']);
            }
        echo '</td>';
        
        // "Company (for profile)". Company name associated with the billing profile
        // Side effect: add companyId to array $candidates, used below where we list Candidates for applying Credit Memo 
        echo '<td>';
            if (intval($billingProfileId)) {
                $bp = new BillingProfile($billingProfileId);
                if (intval($bp->getBillingProfileId())) {
                    $c = new Company($bp->getCompanyId());
                    if (intval($c->getCompanyId())) {
                        echo $c->getName();                        
                        $candidates[] = $c;
                    }
                }
            }
        echo '</td>';
    echo '</tr>';
    //$cr_paid_amount += $crp['amount']; // COMMENTED OUT BY MARTIN BEFORE 2019
}
echo '</table>';

echo '<h3>Balance</h3>';
echo 'Balance on this credit record is : ';
echo $record->getBalance(); // U.S. currency, 2 digits past the decimal point

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//if (isset($crps['total'])){
//	if (is_numeric($crps['total'])){
//		$cr_balance = ($record->getAmount() - $crps['total']);
//		echo $cr_balance;
//	}
//}
// END COMMENTED OUT BY MARTIN BEFORE 2019

echo '<h3>Candidates for applying Credit Memo</h3>';

echo '<table border="0" cellpadding="4" cellspacing="2">';
    echo '<tr>';
        echo '<th bgcolor="#cccccc">CompanyId</th>';
        echo '<th bgcolor="#cccccc">CompanyName</th>';
        echo '<th bgcolor="#cccccc">&nbsp;</th>';
    echo '</tr>';

    foreach ($candidates as $company) {
        echo '<tr>';
            // "CompanyId": Primary key in DB table Company, linked to open company page in a new window/tab 
            echo '<td><a id="linkCompany'.$company->getCompanyId().'" target="_blank" href="' . $company->buildLink() . '">' . $company->getCompanyId() . '</a></td>';
            
            // "CompanyName"
            echo '<td>' . $company->getName() . '</td>';
            
            // (no heading): link labeled "apply memo", self-submits via GET method, act='addmemo', passes creditRecordId, companyId, amount=balance balance of the credit record above. 
            echo '<td><a id="aplyMemo'.intval($creditRecordId).'" href="creditmemo.php?act=addmemo&creditRecordId=' . intval($creditRecordId) . '&companyId=' . intval($company->getCompanyId()) . '&amount=' . rawurlencode($record->getBalance()) . '">apply memo</a></td>';
        echo '</tr>';
    }
echo '</table>';
?>