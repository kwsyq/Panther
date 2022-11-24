<?php
/*  creditrecords.php

    Top-level page for ALL creditRecords.
    
    >>>00004 As of 2019-03-15, this never checks "customer", so it assumes whole system is SSS.
    
    Requires admin-level permission for payments, otherwise just redirects to panther.php.
    
    No primary input, because it looks at ALL creditRecords
    SECONDARY INPUT added for v2020-3: $_REQUEST['companyId']. This file really doesn't care about this value, but
     we have to preserve it for tabbing to other tabs in multi.php
*/

/* [BEGIN MARTIN COMMENT]

create table emailType(
    emailTypeId            int unsigned not null primary key,
    emailTypeName          varchar(16) not null unique,
    emailTypeDisplayName   varchar(16) not null unique);

create index ix_billprofile_cid on billingProfile(companyId);
[END MARTIN COMMENT]
*/
require_once './inc/config.php';
require_once './inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);

if (!$checkPerm){
    header("Location: /panther.php");
}

$crumbs = new Crumbs(null, $user);
$db = DB::getInstance();

// $companyId. As of 2020-06-18, this applies only to tabs 4 & 5, which are in a different file. 
// Other tabs, such as this one, preserve the information, but do not use it, nor do they offer an option to change it.
$companyId = 0; // 0 => no company
// We don't really validate companyId input. Either it is a valid companyId and, if applicable to the tab in question, we use it,
//  or it is not, and we don't
// >>>00002, >>>00016: may want to revisit that.
$companyId = array_key_exists('companyId', $_REQUEST) ? intval($_REQUEST['companyId']) : 0;
if ($companyId && !Company::validate($companyId)) {
    $companyId = 0;
}

// Selects all creditRecords in backward chronological order (relying on the fact that primary key creditRecordId increases monotonically).
$query = " select * from " . DB__NEW_DATABASE . ".creditRecord order by creditRecordId desc ";
if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
    if ($result->num_rows > 0){
        while ($row = $result->fetch_assoc()){
            $records[] = new CreditRecord($row);
        }
    }  
} // >>>00002 ignores failure on DB query!

include_once BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title = 'Credit Records';\n</script>\n";
?>
<script type="text/javascript" src="/js/jquery.tablesorter.min.js"></script>

<style>
body tr.zero-bal {
    display:none
}
body.show-zero-bal tr.zero-bal {
    display:table-row
}
</style>
        
<script>

$(document).ready(function() { 
    $("#tablesorter-demo").tablesorter(); 
}); 

</script>

<div id="container" class="clearfix">
    <div class="main-content">
        <?php 
        /* 
        Sets up a 1-row table (effectively a navigation bar), each column 
        of which relates to a different call to multi.php (or in one case 
        back here to creditrecords.php). In each column is a link written out as an H1 heading:
            * '(wo closed, tasks open)'; links to multi.php?tab=0
            * '(wo open, tasks closed)'; links to multi.php?tab=1
            * '(wo no invoice)'; links to multi.php?tab=2
            * '(wo closed, open invoice)'; links to multi.php?tab=3
            * '(mailroom � awaiting delivery)'; links to multi.php?tab=4
            * '(aging sumary - awaiting payment)' (>>> JM: sic on "sumary"); links to multi.php?tab=5
            * '(cred recs)' links to multi.php?tab=6
            * '(do payments)'; links to creditrecords.php (that is, a self-link)
            * '(cred memos)' links to multi.php?tab=8 
        */ 
        $tab = 7;
        
        $t0color = ($tab == 0) ? '#83fc83' : '#ffffff';
        $t1color = ($tab == 1) ? '#83fc83' : '#ffffff';
        $t2color = ($tab == 2) ? '#83fc83' : '#ffffff';
        $t3color = ($tab == 3) ? '#83fc83' : '#ffffff';
        $t4color = ($tab == 4) ? '#83fc83' : '#ffffff';
        $t5color = ($tab == 5) ? '#83fc83' : '#ffffff';
        $t6color = ($tab == 6) ? '#83fc83' : '#ffffff';
        $t7color = ($tab == 7) ? '#83fc83' : '#ffffff';
        $t8color = ($tab == 8) ? '#83fc83' : '#ffffff';
            
        $titles = array();
        $titles[0] = '(wo closed, tasks open)';
        $titles[1] = '(wo open, tasks closed)';
        $titles[2] = '(wo no invoice)';
        $titles[3] = '(wo closed, open invoice)';
        $titles[4] = '(mailroom -- awaiting delivery)';
        $titles[5] = '(aging sumary - awaiting payment)';
        $titles[6] = '(cred recs)';
        $titles[7] = '(do payments)';
        $titles[8] = '(cred memos)';
        $preserveCompanyId = $companyId ? "&companyId=$companyId" : '';
        $preserveCompanyId_no_tab = $companyId ? "?companyId=$companyId" : '';
            
        ?>
		
        <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td width="12%" style="text-align:center" bgcolor="<?= $t0color ?>"><h1><a id="multi1Tab0" href="multi.php?tab=0<?= $preserveCompanyId ?>" title="<?= $titles[0] ?>">0</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t1color ?>"><h1><a id="multi1Tab1" href="multi.php?tab=1<?= $preserveCompanyId ?>" title="<?= $titles[1] ?>">1</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t2color ?>"><h1><a id="multi1Tab2" href="multi.php?tab=2<?= $preserveCompanyId ?>" title="<?= $titles[2] ?>">2</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t3color ?>"><h1><a id="multi1Tab3" href="multi.php?tab=3<?= $preserveCompanyId ?>" title="<?= $titles[3] ?>">3</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t4color ?>"><h1><a id="multi1Tab4" href="multi.php?tab=4<?= $preserveCompanyId ?>" title="<?= $titles[4] ?>">4</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t5color ?>"><h1><a id="multi1Tab5" href="multi.php?tab=5<?= $preserveCompanyId ?>" title="<?= $titles[5] ?>">5</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t6color ?>"><h1><a id="multi1Tab6" href="multi.php?tab=6<?= $preserveCompanyId ?>" title="<?= $titles[6] ?>">6</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t7color ?>"><h1><a id="multi1Credit7" href="creditrecords.php<?= $preserveCompanyId_no_tab ?>" title="<?= $titles[7] ?>">7</a></h1></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t8color ?>"><h1><a id="multi1Tab8" href="multi.php?tab=8<?= $preserveCompanyId ?>" title="<?= $titles[8] ?>">8</a></h1></td>
            </tr>
            <tr>
                <td width="12%" style="text-align:center" bgcolor="<?= $t0color ?>"><a id="multi2Tab0" href="multi.php?tab=0<?= $preserveCompanyId ?>" title="<?= $titles[0] ?>" style="font-size:80%;"><?= $titles[0] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t1color ?>"><a id="multi2Tab1" href="multi.php?tab=1<?= $preserveCompanyId ?>" title="<?= $titles[1] ?>" style="font-size:80%;"><?= $titles[1] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t2color ?>"><a id="multi2Tab2" href="multi.php?tab=2<?= $preserveCompanyId ?>" title="<?= $titles[2] ?>" style="font-size:80%;"><?= $titles[2] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t3color ?>"><a id="multi2Tab3" href="multi.php?tab=3<?= $preserveCompanyId ?>" title="<?= $titles[3] ?>" style="font-size:80%;"><?= $titles[3] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t4color ?>"><a id="multi2Tab4" href="multi.php?tab=4<?= $preserveCompanyId ?>" title="<?= $titles[4] ?>" style="font-size:80%;"><?= $titles[4] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t5color ?>"><a id="multi2Tab5" href="multi.php?tab=5<?= $preserveCompanyId ?>" title="<?= $titles[5] ?>" style="font-size:80%;"><?= $titles[5] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t6color ?>"><a id="multi2Tab6" href="multi.php?tab=6<?= $preserveCompanyId ?>" title="<?= $titles[6] ?>" style="font-size:80%;"><?= $titles[6] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t7color ?>"><a id="multi2Credit7" href="creditrecords.php<?= $preserveCompanyId_no_tab ?>" title="<?= $titles[7] ?>" style="font-size:80%;"><?= $titles[7] ?></a></td>			
                <td width="12%" style="text-align:center" bgcolor="<?= $t6color ?>"><a id="multi2Tab8" href="multi.php?tab=8<?= $preserveCompanyId ?>" title="<?= $titles[8] ?>" style="font-size:80%;"><?= $titles[8] ?></a></td>							
            </tr>
        </table>
        <?php                                 
        
        echo '<p>';  // >>>00006 paragraph used just as a spacer, BR would be clearer (especially since P element is never closed)
        
        echo '<h3>Tab: ' . $tab . '&nbsp;&nbsp;' . $titles[$tab] . '</h3>';
        ?>	
        <div class="full-box clearfix">
            <h2 class="heading">Credit Records</h2>
            <?php /* Toggle between "non-zero balance only" and "everything" */ ?>
            <button id="clickme">Toggle View</button>
            <script>			
                $( "#clickme" ).click(function() {
                    $('body').toggleClass('show-zero-bal');
                });
            </script>      
        
            <table border="0" cellpadding="0" cellspacing="0" id="tablesorter-demo" class="tablesorter">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Ref#</th>
                        <th>Amount</th>
                        <th>Cred Date</th>
                        <th>Rec From</th>
                        <th>Pmts</th>
                        <th>CR&nbsp;Bal</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $types = CreditRecord::creditRecordTypes();    
                        foreach ($records as $record) {
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
                            if (array_key_exists(intval($record->getCreditRecordTypeId()),$types)) {
                                $type = $types[$record->getCreditRecordTypeId()]['name'];
                            }    
                    
                            $bal = $record->getBalance();
                            $class = '';
                            if ($bal == 0){
                                $class = ' class="zero-bal" ';
                            }
                            
                            // one row per credit record
                            echo '<tr ' . $class . '>';
                                // Type: creditRecordType name, e.g. "Check", "PayPal" 
                                echo '<td>' . $type . '</td>';
                                
                                // Ref#: referenceNumber of creditRecord: such as a check # or a PayPal payment number 
                                echo '<td style="text-align:center">' . $record->getReferenceNumber() . '</td>';
                                
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
                                //$pay = 0; // Commented out by Martin some time before 2019
                                echo '<td>';    
                                    $payments = $record->getPayments();            
                                    if (isset($payments['invoices'])) {
                                        $pays = $payments['invoices'];
                                        if (count($pays)) {
                                            echo '<table border="0" cellpadding=1" cellspacing="0">';
                                            foreach ($pays as $payment) {
                                                
                                                // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                                //if (is_numeric($payment['amount'])){
                                                //	$pay += $payment['amount'];
                                                //}
                                                // [END COMMENTED OUT BY MARTIN BEFORE 2019]    
                                                echo '<tr>';
                                                echo '<td>Paid&nbsp;</td>';
                                                echo '<td>$' . $payment['amount'] . '</td>';
                                                echo '<td>To&nbsp;';
                                                echo '<td>';
                                                echo '<a id="linkInvoice'.$payment['invoicePaymentId'].'" target="_blank" href="/invoice/' . $payment['invoiceId'] . '">Inv:' . $payment['invoiceId'] . '</a>';
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
                                            echo '<table border="0" cellpadding=1" cellspacing="0">';    
                                            foreach ($pays as $payment) {
                                                // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
                                                //if (is_numeric($payment['amount'])){
                                                //	$pay += $payment['amount'];
                                                //}
                                                // [END COMMENTED OUT BY MARTIN BEFORE 2019]
                                
                                                echo '<tr>';
                                                echo '<td>Paid&nbsp;</td>';
                                                echo '<td>$' . $payment['amount'] . '</td>';
                                                echo '<td>To&nbsp;';
                                                echo '<td>';
                                                    echo '<a id="linkMemo'.$payment['creditMemoId'].'" target="_blank" href="/multi.php?tab=8">Memo:' . $payment['creditMemoId'] . '</a>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                            echo '</table>';
                                        }
                                    }
                                echo '</td>';
                            
                                // CR Bal: if the credit record has a zero balance, just '0'. 
                                // If the credit record has a nonzero balance, that balance (should be dollars with two digits past the decimal point)... 
                                //    >>>00013 As of 2019-03-15 I (JM) haven't seen good data on which to test this, not sure it's really been exercised.
                                //    >>>00026: 2019-03-15 JM: one for invoice 8015 and displays as '0.60000000000036'; I presume that's a bug. 
                                //      Martin following up in late 2018, said probably QuickBooks-related, but not immediately obvious what's going on, 
                                // ... followed by a parenthesized link to open /fb/creditmemo.php?creditRecordId=creditRecordId in a fancybox; 
                                // link is displayed as "memo". 
                                echo '<td>';
                                if (is_numeric($record->getAmount()) && is_numeric($record->getPaymentsTotal())) {
                                    // $bal = $record->getBalance(); redundant, removed 2020-02-05 JM
                                    echo $bal;
                                    if ($bal != 0) {
                                        echo '&nbsp;(<a data-fancybox-type="iframe" class="fancyboxIframe" id="linkCreditMemo'.$record->getCreditRecordId().'"  href="/fb/creditmemo.php?creditRecordId=' . $record->getCreditRecordId() . '">memo</a>)';
                                    }
                                } else {
                                    echo 'n/a';
                                }
                                echo '</td>';   
                        
                                // (no header) a link to open /fb/creditrecord.php for this creditRecordId in a fancybox; link is displayed as "View". 
                                echo '<td>';
                                $label = $bal ? 'View/Pay' : 'View';
                                echo '<a data-fancybox-type="iframe" id="linkCreditRecord'.$record->getCreditRecordId().'" class="fancyboxIframeWide" ' . 
                                     'href="/fb/creditrecord.php?creditRecordId=' . $record->getCreditRecordId() . '">' . $label . '</a>';
                                echo '</td>';
                    
                            echo '</tr>' . "\n";
                        }
                    ?>
                </tbody>
            </table>
        </div>		
    </div>
</div>

<?php


include_once BASEDIR . '/includes/footer.php';
?>