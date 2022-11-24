<?php 

/* Top-level page. Each tab here is rather different from the others, 
   so this is hard to characterize globally: the various tabs are:
   
   * '(wo closed, tasks open)'
   * '(wo open, tasks closed)'
   * '(wo no invoice)'
   * '(wo closed, open invoice)'
   * '(mailroom -- awaiting delivery)'
   * '(aging summary - awaiting payment)'
   * '(cred recs)'
   * '(do payments)'
   * '(cred memos)'
   
   Tabs display different data.  Some do so for obvious reasons; other differences seem unmotivated and are, 
   at best, historical. >>>00001 JM: we may want to revisit those differences. 

   Requires admin-level permission for payments. 
   
   PRIMARY INPUT: $_REQUEST['tab'] (default 2).
   SECONDARY INPUT: beginning with v2020-3, $_REQUEST['companyId'] allows to filter certain tabs to just data for one company. 

   OTHER INPUTS: $_REQUEST['act'], supported values are 'awaitingpayment', 'close'.
    * 'awaitingpayment' takes further argument $_REQUEST['invoiceId] and ignores input $_REQUEST['tab'].
    * 'close', takes further argument $_REQUEST['invoiceId'] and pays attention to input $_REQUEST['tab'] for what to do after.
    * 'monthsback' (added 2020-02-04) is only meaningful if combined with $_REQUEST['tab']=6. Allows us to limit how old credit records we
      are concerned with.
    
*/   

/*
[BEGIN MARTIN COMMENT]

create table invoicePayment(
    invoicePaymentId   int unsigned not null primary key auto_increment,
    creditRecordId     int unsigned not null,
    invoiceId          int unsigned not null,
    amount             decimal(10,2),
    personId           int unsigned not null,
    inserted           timestamp not null default now());

create index ix_invpay_crid on invoicePayment(creditRecordId);
create index ix_invpay_iid on invoicePayment(invoiceId);

insert into 

invoice id 16202
cred rec id 70

insert into invoicePayment(creditRecordId, invoiceId, amount, personId) values (70, 16202, 2070, 2043);

insert into invoicePayment(creditRecordId, invoiceId, amount, personId) values (69, 10902, 230, 2043);

[END MARTIN COMMENT]
*/

include './inc/config.php';
include './inc/perms.php';

$checkPerm = checkPerm($userPermissions, 'PERM_PAYMENT', PERMLEVEL_ADMIN);
if (!$checkPerm) {
    // Lacks admin-level permission for payments, out of here.
    header("Location: /panther.php");
}

$db = DB::getInstance();

// $companyId. As of 2020-06-18, this applies only to tabs 4 & 5. Other tabs preserve the information, but do not use it, nor do they offer an option to change it.
$companyId = 0; // 0 => no company
// We don't really validate companyId input. Either it is a valid companyId and, if applicable to the tab in question, we use it,
//  or it is not, and we don't
// >>>00002, >>>00016: may want to revisit that.
$companyId = array_key_exists('companyId', $_REQUEST) ? intval($_REQUEST['companyId']) : 0;
if ($companyId && !Company::validate($companyId)) {
    $companyId = 0;
}

// Cleaner way of dealing with invoice statuses introduced 2020-05-21 JM (here and where these are used below)
$invoiceStatusAwaitingPayment = Invoice::getInvoiceStatusIdFromUniqueName('awaitingpayment');
$invoiceStatusClosed = Invoice::getInvoiceStatusIdFromUniqueName('closed');
if ($invoiceStatusAwaitingPayment === false) {
    // Invoice::getInvoiceStatusIdFromUniqueName will already have logged the problem
    reportSevereError("Invoice status 'awaitingpayment' is undefined, serious problem, contact an administrator or developer.");
}
if ($invoiceStatusClosed === false) {
    // Invoice::getInvoiceStatusIdFromUniqueName will already have logged the problem
    reportSevereError("Invoice status 'closed' is undefined, serious problem, contact an administrator or developer.");
}
function reportSevereError($text) {
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<div style="color:red; font-weight:bold;"><?= $text ?></div>
<div><a href='/panther.php'>Go to Panther home page.</a></div>
</body>
</html>
<?php
die();
}

/* ACTION $_REQUEST['act'] = 'awaitingpayment':
   INPUT $_REQUEST['invoiceId']
   
   Changes status of invoice specified by $_REQUEST['invoiceId'] to 'awaitingpayment' 
   (value for column 'note' is always 'from tab 4'), then reloads page always using tab=4. 
   Status is set by inserting a row in DB table invoiceStatusTime.
   
   Misc cleanup here JM 2020-05-22. However, I suspect this is never called. 
*/   
if ($act == "awaitingpayment") {
    $logger->info2('1590166791', 'Contrary to what Joe thought, $act == "awaitingpayment" is called. Someone needs to analyze how that happens.');
    $invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;
    
    $query = "INSERT INTO " . DB__NEW_DATABASE . ".invoiceStatusTime (";
    $query .= "invoiceStatusId, invoiceId, personId, note";
    // REMOVED 2020-08-10 JM for v2020-4 // $query .= ", extra"; // >>>00032 'extra' will eventually go away
    $query .= ") VALUES (";
    $query .= intval($invoiceStatusAwaitingPayment);
    $query .= ", " . intval($invoiceId);
    $query .= ", " . intval($user->getUserId());
    $query .= ", 'from tab 4'";
    // REMOVED 2020-08-10 JM for v2020-4 // $query .= ", 0"; // >>>00032 'extra' will eventually go away
    $query .= ");";
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1590166779', 'Hard DB error', $db);
        // but just continue.
    }
    header("Location: /multi.php?tab=4" . ($companyId ? "&companyId=$companyId" : '') );
    die();
}

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : '';
if ($tab == '') {
    $tab = 2;
}

/* ACTION $_REQUEST['act'] = 'close'
   INPUT $_REQUEST['invoiceId']
   INPUT $_REQUEST['tab']
   
   Change status of invoice specified by $_REQUEST['invoiceId'] to 'closed', 
   then reload page using $_REQUEST['tab']. We go through Invoice class methods, 
   but at a low level, status is set by inserting a row in DB table invoiceStatusTime.
*/
if ($act == 'close') {
    $invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;
    $invoice = new Invoice($invoiceId, $user);
    if (intval($invoice->getInvoiceId())) {
        $invoice->setStatus($invoiceStatusClosed, 0, '');
    }
    
    header('Location: /multi.php?tab=' . $tab . ($companyId ? "&companyId=$companyId" : '') );
    die();
}

/*  George 10-03-2021.
    ACTION $_REQUEST['act'] = 'deleteCreditRecord'
    INPUT $_REQUEST['creditRecordId']
    PARAMETERS: $_REQUEST['tab'], $_REQUEST['monthsback']

    On click "Delete" action, for safety, Javascript message: "Are you sure you want to Delete this Credit Record?". */
if ($act == 'deleteCreditRecord') {

    $creditRecordId = intval($_REQUEST['creditRecordId']); // credit record id
    $tabRecord = intval($_REQUEST['tab']); // tab 6
    $monthsbackValue = isset($_REQUEST['monthsback']) ? intval($_REQUEST['monthsback']) : 0; // selected monthsback

    if ($creditRecordId) {
        $query = "DELETE FROM " . DB__NEW_DATABASE . ".creditRecord ";
        $query .= " WHERE creditRecordId = " . intval($creditRecordId) . ";";
        
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('637509723127615922', "Failed to delete a creditRecord, creditRecordId = " . intval($creditRecordId) . "", $db);
        } else {
            header("Location: multi.php?tab=".$tabRecord ."&monthsback=".$monthsbackValue);
            die();
        }
  }
} 


// Some code very similar in multiple tabs, so we've moved it here into a function
function htmlAndScriptForMinAndMaxAgeSections($tab) {
?>    
    Minimum age:&nbsp;
    <input type="radio" name="tab<?= $tab ?>-min-age" id="tab<?= $tab ?>-min-age-0" value="0" checked />&nbsp;<label for="tab<?= $tab ?>-min-age-0">No min</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-min-age" id="tab<?= $tab ?>-min-age-30" value="30" />&nbsp;<label for="tab<?= $tab ?>-min-age-30">30</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-min-age" id="tab<?= $tab ?>-min-age-45" value="45" />&nbsp;<label for="tab<?= $tab ?>-min-age-45">45</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-min-age" id="tab<?= $tab ?>-min-age-60" value="60" />&nbsp;<label for="tab<?= $tab ?>-min-age-60">60</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-min-age" id="tab<?= $tab ?>-min-age-90" value="90" />&nbsp;<label for="tab<?= $tab ?>-min-age-90">90</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-min-age" id="tab<?= $tab ?>-min-age-other" value="other" />&nbsp;<label for="tab<?= $tab ?>-min-age-other">Other:</label>&nbsp;
    <input type="number" id="min-age-other" min="0" max="1000"/>
    <script>
        $('#min-age-other').change(function() {
            $('#tab<?= $tab ?>-min-age-other').click();
        });
        $('[name="tab<?= $tab ?>-min-age"]').click(function() {
            let $this = $(this);
            let minAge=$('#min-age-other').val();
            let itemCount = 0;
            if ( ! $('#tab<?= $tab ?>-min-age-other').is(':checked') ) {
                // actual minAge value comes from the radio button
                minAge=$('[name="tab<?= $tab ?>-min-age"]:checked').val();
            }
            minAge=parseInt(minAge);
            $("#tab<?= $tab ?>-table tbody tr").each(function() {
                let $this = $(this);
                if ($this.hasClass('extra-client')) {
                    if ($this.prev().hasClass('too-new')) {
                        $this.addClass('too-new');
                    } else {
                        $this.removeClass('too-new');
                    }
                } else {
                    let age = $('td.age', $this).html();
                    if ($('td.age', $this).hasClass('no-invoice-date')) {
                        $this.removeClass('too-new');
                    } else if (age < minAge) {
                        $this.addClass('too-new');
                    } else {
                        $this.removeClass('too-new');
                    }
                    if ( !$this.hasClass('too-new') && !$this.hasClass('too-old') && !$this.hasClass('wrong-company') ) { 
                        ++itemCount;
                    }
                }
            });
            $('#itemCount').html(itemCount);
        });
    </script>
    <br />
    Maximum age:&nbsp;
    <input type="radio" name="tab<?= $tab ?>-max-age" id="tab<?= $tab ?>-max-age-no-max" value="1000000" checked/>&nbsp;<label for="tab<?= $tab ?>-max-age-30">No max</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-max-age" id="tab<?= $tab ?>-max-age-30" value="30" />&nbsp;<label for="tab<?= $tab ?>-max-age-30">30</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-max-age" id="tab<?= $tab ?>-max-age-45" value="45" />&nbsp;<label for="tab<?= $tab ?>-max-age-45">45</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-max-age" id="tab<?= $tab ?>-max-age-60" value="60" />&nbsp;<label for="tab<?= $tab ?>-max-age-60">60</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-max-age" id="tab<?= $tab ?>-max-age-90" value="90" />&nbsp;<label for="tab<?= $tab ?>-max-age-90">90</label>&nbsp;
    <input type="radio" name="tab<?= $tab ?>-max-age" id="tab<?= $tab ?>-max-age-other" value="other" />&nbsp;<label for="tab<?= $tab ?>-max-age-other">Other:</label>&nbsp;
    <input type="number" id="max-age-other" min="0" max="1000"/>
    <script>
        $('#max-age-other').change(function() {
            $('#tab<?= $tab ?>-max-age-other').click();
        });
        $('[name="tab<?= $tab ?>-max-age"]').click(function() {
            let $this = $(this);
            let maxAge=$('#max-age-other').val();
            let itemCount = 0;
            if ( ! $('#tab<?= $tab ?>-max-age-other').is(':checked') ) {
                maxAge=$('[name="tab<?= $tab ?>-max-age"]:checked').val();
            }
            maxAge=parseInt(maxAge);
            $("#tab<?= $tab ?>-table tbody tr").each(function() {
                let $this = $(this);
                if ($this.hasClass('extra-client')) {
                    if ($this.prev().hasClass('too-old')) {
                        $this.addClass('too-old');
                    } else {
                        $this.removeClass('too-old');
                    }
                } else {
                    let age = $('td.age', $this).html();
                    if ($('td.age', $this).hasClass('no-invoice-date') && !$('#tab<?= $tab ?>-max-age-no-max').is(':checked')) {
                        $this.addClass('too-old');
                    } else if (age > maxAge) {
                        $this.addClass('too-old');
                    } else {
                        $this.removeClass('too-old');
                    }
                    if ( !$this.hasClass('too-new') && !$this.hasClass('too-old') && !$this.hasClass('wrong-company') ) { 
                        ++itemCount;
                    }
                }
            });
            $('#itemCount').html(itemCount);
        });
    </script>
<?php    
} // END function htmlAndScriptForMinAndMaxAgeSections

// Similarly for limiting to one company (or not)
function htmlAndScriptForCompanySelection($tab, $companyId) {
    if ($companyId) {
        $company = new Company($companyId);
        ?>
        <div style="font-weight:bold"><span id="describe-company-rule">Limited to company <?= $company->getCompanyName() ?>&nbsp;&nbsp;&nbsp;&nbsp; 
        <a href="" class="show-all-companies">(click to show all companies)</a>&nbsp;&nbsp;&nbsp;&nbsp; 
        Choose a different company:&nbsp;</span><input type="text" name="q" value="" size="40" maxlength="64" class="autocomplete-company" placeholder="Type to select">
        </div>
    <?php
    } else {
    ?>
        <div style="font-weight:bold"><span id="describe-company-rule">Showing all companies. Type to limit to one company:&nbsp;</span> 
        <input type="text" name="q" value="" size="40" maxlength="64" class="autocomplete-company" placeholder="Type to select">
        </div>
<?php
    }
    ?>
    <script>
        // Delegate this because we want to be able to create a new '.show-all-companies' on the fly. 
        $('body').on('click', '.show-all-companies', function(event) {
            event.preventDefault();
            $("#tab<?= $tab ?>-table tbody tr").removeClass('wrong-company');      
            $('#itemCount').html($("#tab<?= $tab ?>-table tbody tr").length);
            $("#main-navigation-tabs td a").each(function() {
                let $this = $(this);
                let href = $this.attr('href');
                // The following may need to be refined if we ever pass anything there AFTER companyId, but it will do for now
                let companyIdAt = href.indexOf('companyId');
                if (companyIdAt > 0) {
                    href = href.substring(0, companyIdAt-1);
                }
                $this.attr('href', href);
            });
            $("#describe-company-rule").html('Showing all companies. Type to limit to one company:&nbsp;');
            $(".autocomplete-company").val('');
            $("#offer-multiple-invoice-pdf, .multiple-invoice-checkbox").hide();
        });
        
        function showOnlyOneCompany(companyName, companyId) {
            let itemCount = 0;
            $("#tab<?= $tab ?>-table tbody tr").each(function() {
                 let $this = $(this);
                 if ($this.data('companyid') == companyId) {
                     $this.removeClass('wrong-company');
                 } else {
                     $this.addClass('wrong-company');
                 }
                if ( !$this.hasClass('too-new') && !$this.hasClass('too-old') && !$this.hasClass('wrong-company') ) { 
                    ++itemCount;
                }
            });
            $('#itemCount').html(itemCount);
            $("#main-navigation-tabs td a").each(function() {
                let $this = $(this);
                let href = $this.attr('href');
                // The following may need to be refined if we ever pass anything there AFTER companyId, but it will do for now
                let companyIdAt = href.indexOf('companyId');
                if (companyIdAt > 0) {
                    href = href.substring(0, companyIdAt-1);
                }
                let questionAt = href.indexOf('?');
                if (questionAt == -1) {
                    href += '?';
                } else {
                    href += '&';
                }
                href += 'companyId=' + companyId;
                $this.attr('href', href);
            });
            $("#describe-company-rule").html('Limited to company ' + companyName + '&nbsp;&nbsp;&nbsp;&nbsp;' + 
                '<a href="" class="show-all-companies">(click to show all companies)</a>&nbsp;&nbsp;&nbsp;&nbsp;' + 
                'Choose a different company:&nbsp;');
            $(".autocomplete-company").val('');
            $("#offer-multiple-invoice-pdf, .multiple-invoice-checkbox").show();
        } // END function showOnlyOneCompany
    </script>
    <?php
}


include BASEDIR . '/includes/header.php';

// the following script added 2020-06 JM as part of implementing $companyId
?>    
    <script src="/js/jquery.autocomplete.min.js"></script>
    <script>
        var companyList = []; // deliberately global
        $(function() {
            $('.autocomplete-company').devbridgeAutocomplete({
                // serviceUrl: '/ajax/autocomplete_company.php',
                lookup: companyList, 
                onSelect: function (suggestion) {
                    if (suggestion.data && suggestion.data > 0) {
                        let companyId = suggestion.data;
                        let companyName = suggestion.value;
                        if (companyId) {
                            showOnlyOneCompany(companyName, companyId); 
                        }
                    }
                },
                paramName:'q'
            });
        });
    </script>
<?php

// BEGIN ADDED 2020-02-04 JM
?>
<style>
.legacy {
    background-color: yellow!important;
}

.newinv {
    background-color: lightgreen!important;
}
body tr.zero-bal {
    display:none
}
body.show-zero-bal tr.zero-bal {
    display:table-row
}    
/* autocomplete styles added 2020-06-18 JM */    
.autocomplete-wrapper { max-width: 600px; float:left; }
.autocomplete-wrapper label { display: block; margin-bottom: .75em; color: #3f4e5e; font-size: 1.25em; }
.autocomplete-wrapper .text-field { padding: 0 0px; width: 100%; height: 40px; border: 1px solid #CBD3DD; font-size: 1.125em; }
.autocomplete-wrapper ::-webkit-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
.autocomplete-wrapper :-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
.autocomplete-wrapper ::-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
.autocomplete-wrapper :-ms-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }

.autocomplete-suggestions { overflow: auto; border: 1px solid #CBD3DD; background: #FFF; cursor: pointer; }
.autocomplete-suggestion { overflow: hidden; padding: 5px 15px; white-space: nowrap; }
.autocomplete-selected { background: #F0F0F0; }
.autocomplete-suggestions strong { color: #029cca; font-weight: normal; }

/* The following added 2020-06 JM in support of being able to limit to showing only 
   rows whose ages fall within a particular range */
tr.too-new {display:none;}
tr.too-old {display:none;}
tr.wrong-company {display:none;}

/* sticky added 2020-06-24. George 2021-03-08. Removed
.sticky-header {
    position: -webkit-sticky; 
    position: sticky;
    top:0;
    
}*/

/* sticky Added George 2021-03-08 */
thead tr:nth-child(1) th {
    position: webkit-sticky; /* for Safari */
    position: sticky;
    top: 0;
    z-index: 10;
}

#monthsback {
    display:inline-block!important;
    width: 10%;
    font-size: 13px!important;
}
input, select, textarea, button:not(#editTooltip, #hideTooltip, #clickme, #refresh-tab-6) {
    font-size:11px!important;
}
[id^=linkDeleteCreditRecord] {
    color: #fff!important;
}
[id^=cred_invoices_] {
    font-size: 12px!important;
}
</style>
<?php
// END ADDED 2020-02-04 JM

// JM 2019-11-15: adding HTML title
// Moved these titles way up so we can do this early.
$titles = array();
$titles[0] = '(wo closed, tasks open)';
$titles[1] = '(wo open, tasks closed)';
$titles[2] = '(wo no invoice)';
$titles[3] = '(wo closed, open invoice)';
$titles[4] = '(mailroom -- awaiting delivery)';
$titles[5] = '(aging summary - awaiting payment)';
$titles[6] = '(cred recs)';
$titles[7] = '(do payments)';
$titles[8] = '(cred memos)';

echo "<script>\ndocument.title ='Multi: ". str_replace("'", "\'", $titles[$tab]) ."';\n</script>\n";

?>

<script>
/* INPUT invoiceId.
   Use the input invoiceId to identify the cell "confirm_send_invoiceId" from 
   which it was called, then POSTs to ./ajax/setinvoicestatus.php, passing the invoiceId 
   and the invoiceStatusId that means 'awaitingpayment'. 
   
   On success, it turns the cell background green; on failure it turns it red. 
   >>>00002 If there is a failure, there is currently no indication of the nature of the failure.
*/
var sendInvoice = function(invoiceId) {
    var $cell = $('#confirm_send_' + invoiceId);
    $.ajax({
        url: './ajax/setinvoicestatus.php',
        data:{
            invoiceId : invoiceId,
            invoiceStatusId : <?= $invoiceStatusAwaitingPayment ?>
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { 
                    $cell.css('background-color', 'green');
                } else {
                    console.log('ajax/setinvoicestatus.php(' + invoiceId + ', <?= $invoiceStatusAwaitingPayment ?>) returned data[\'status\'] ', data['status']); 
                    $cell.css('background-color', 'red');
                }
            } else {
                console.log('ajax/setinvoicestatus.php(' + invoiceId + ', <?= $invoiceStatusAwaitingPayment ?>) did not return data[\'status\'].');
                $cell.css('background-color', 'red');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('ajax/setinvoicestatus.php(' + invoiceId + ', <?= $invoiceStatusAwaitingPayment ?>) AJAX problem.');
            $cell.css('background-color', 'red');
        }
    });
}

/* Below, on page load, we launch this recursive function 'repeater', always with
   setTimeout('repeater("nothing")', 2000);
   That is, it is set to run after 2 seconds. 
   
   repeater effectively fills in most of the content on this page. 
*/
var repeater = function(name) {
    <?php
        // [Martin comment:] lol
        
    /* Initially, repeater puts the ajax_loader.gif in each of the 'count' and 'total' cells as well 
        as the 'lastupdate' cell (see below). It then posts  data to /ajax/financial_count.php.
        (In practice, that data is always {name:"nothing"}, and is ignored anyway; 
        this related to older stuff that's gone as of 2019).
    */        
    ?>
    var cell = document.getElementById("tab0_count");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    
    cell = document.getElementById("tab1_count");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    
    cell = document.getElementById("tab2_count");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    cell = document.getElementById("tab2_total");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    
    cell = document.getElementById("tab3_count");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    cell = document.getElementById("tab3_total");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    
    cell = document.getElementById("tab4_count");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    cell = document.getElementById("tab4_total");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    
    cell = document.getElementById("tab5_count");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    cell = document.getElementById("tab5_balance");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');
    
    cell = document.getElementById("lastupdate");
    cell.innerHTML = ('<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">');

    $.ajax({
            url: '/ajax/financial_count.php',
            async:true,
            type:'post',
            data: {
                name: name
            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { // [T000016] 
                        if (data['payload']) {
                            <?php /* data['payload'] is a JSON equivalent of the content of the ajaxdata DB table built by crons/ajaxdata.php.
                                     key here is a tab (e.g. 'tab2', let's call the general case tabNN). */ ?>
                            for (var key in data['payload']) {
                                if (data['payload'][key]['data']) {
                                    for (var key2 in data['payload'][key]['data']) {
                                        <?php /* Values here for key2: 
                                            'Count': we write the value into the TD element with id="tabNN_count"
                                            'Hours': we write the value into the TD element with id="tabNN_total" (only relevant for NN = 2, 3, or 4)
                                            'Trigger Total': Behaves identically to "Hours", so we'd better not have both of these on the same tab!
                                            'Balance: we write the value into the TD element with id="tabii_balance" (only relevant for NN = 5)
                                        */ ?> 
                                        if (key2 == 'Count') {
                                            var cell = document.getElementById(key + '_count');
                                            cell.innerHTML = data['payload'][key]['data'][key2];
                                        }
                                        if (key2 == 'Hours') {
                                            var cell = document.getElementById(key + '_total');
                                            cell.innerHTML = data['payload'][key]['data'][key2];
                                        }
                                        if (key2 == 'Trigger Total') {
                                            var cell = document.getElementById(key + '_total');
                                            cell.innerHTML = data['payload'][key]['data'][key2];
                                        }
                                        if (key2 == 'Balance') {
                                            var cell = document.getElementById(key + '_balance');
                                            cell.innerHTML = data['payload'][key]['data'][key2];
                                        }
                                    }
                                }
                            }
                        }

                        <?php /* We set the cell with id="lastupdate" to 'Last : ' + h + ':' + m + ':' + s;, e.g. "Last : 10:43:12" */?>
                        var d = new Date();
                        var day = d.getDate();
                        var month = (d.getMonth() + 1);
                        var year = d.getFullYear();
                        var h = ('0' + d.getHours()).slice(-2);
                        var m = ('0' + d.getMinutes()).slice(-2);
                        var s = ('0' + d.getSeconds()).slice(-2);
                        
                        var ucell = document.getElementById("lastupdate");
                        // BEGIN REMOVED 2020-02-19 JM: previously, we set ucell.innerHTML twice. Only the second one mattered.
                        // ucell.innerHTML = month + '/' + day + '/' + year + ' ' + h + ':' + m + ':' + s;
                        // END REMOVED 2020-02-19 JM
                        ucell.innerHTML = 'Last : ' + h + ':' + m + ':' + s;
                        
                        <?php /* In a somewhat convoluted way, we set up to call repeater again in a minute 
                              (in fact, we trigger it to decrement a count once a second, eventually calling itself. 
                              The reason we don't just use setTimeout( 'repeater("nothing")', 60000 ); is so that we 
                              can present a countdown in the "counter" cell to the right of "lastupdate". 
                              Data will only change when the cron job runs; that might be every 10 minutes or thereabouts.
                              */ ?>
                        var count = 59, timer = setInterval(function() {
                            $("#counter").html(count--);
                            if(count == 0) {
                                clearInterval(timer);
                                setTimeout( 'repeater("nothing")', 100 );
                            };
                        }, 1000);                        
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
            }
    });
}

<?php
// As noted above, on load get "repeater" started; that's what actually
// gets us most of the content.
// >>>00014 JM 2019-03-22: no idea whether there is a good reason why this
//  is "on load" rather than the more common "on ready".
?>
$(window).on('load', function() {
    setTimeout('repeater("nothing")', 2000);
});

<?php
// Sets it up so that you can hover over the "eye" icon to see the various elements
//  with class="financials".
// >>>00014 JM 2019-03-22: no idea whether there is a good reason why this
//  is "on load" rather than the more common "on ready".
?>
$(window).on('load', function() {
    $('#finhov').hover(function() {
        $('.financials').show();
        setTimeout( '$(".financials").hide()', 10000 );
    });
});

</script>

<?php
if ($tab == 2 || $tab == 3 || $tab == 4 || $tab == 5) {
    // 2020-08-07 JM: address http://bt.dev2.ssseng.com/view.php?id=212: 
    //  handle table sorting more uniformly
    //  and base it on class of table rather than Id.
?>
    <script type="text/javascript" src="/js/jquery.tablesorter.min.js"></script>
    <script>            
        $(document).ready(function() {
            $("table.tablesorter-demo").tablesorter();
        }); 
    </script>
<?php
}
if ($tab == 4 || $tab == 5) {
?>    
    <script>
    $(function() {
       $('#offer-multiple-invoice-pdf').click(function() {
            let $checked = $("tbody .multiple-invoice-checkbox").filter(':visible').filter(':checked');
            if ($checked.length) {
                let html_query_string = '';
                $checked.each(function() {
                    let $this = $(this);
                    if (html_query_string) {
                        html_query_string += '&';
                    }
                    html_query_string += 'invoiceId[]=' + $this.val();                            
                });
                // We assume here that the rule has been enforced that these are all for one company, 
                // so the first match is as good as any.
                let companyString = encodeURIComponent($checked.first().closest('tr').find('td.company-name-cell').text().replace(/ /g, '_'));
                html_query_string += '&companyName=' + companyString;
                window.open('invoicepdf.php?' + html_query_string);
            } else {
                alert('Nothing selected to print, check box for at least one invoice');
            }
       });
       $('thead .multiple-invoice-checkbox').click(function() {
            let $this = $(this);   
            //debugger;
            $("tbody .legacyinv").filter(':visible').prop('checked', $this.prop('checked'));   
       });
    });               
    </script>
<?php
} // END if ($tab == 4 || $tab == 5)
?>

<div id="container" class="clearfix">
    <div class="main-content">
        <div class="full-box clearfix">
            <?php /* 2020-02-19 JM: REMOVED empty header
            <h2 class="heading"></h2>
            */ ?>

            <?php /* User can hover over this "eye" icon to see the various elementswith class="financials". */ ?>
            <a id="finhov"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_eye_64x64.png" width="50" height="50"></a>
            
            <?php 
            $t0color = ($tab == 0) ? '#83fc83' : '#ffffff';
            $t1color = ($tab == 1) ? '#83fc83' : '#ffffff';
            $t2color = ($tab == 2) ? '#83fc83' : '#ffffff';
            $t3color = ($tab == 3) ? '#83fc83' : '#ffffff';
            $t4color = ($tab == 4) ? '#83fc83' : '#ffffff';
            $t5color = ($tab == 5) ? '#83fc83' : '#ffffff';
            $t6color = ($tab == 6) ? '#83fc83' : '#ffffff';
            $t7color = ($tab == 7) ? '#83fc83' : '#ffffff';
            $t8color = ($tab == 8) ? '#83fc83' : '#ffffff';
            $preserveCompanyId = $companyId ? "&companyId=$companyId" : '';
            $preserveCompanyId_no_tab = $companyId ? "?companyId=$companyId" : '';

            ?>
            <?php /*  For each of these, the tab number is presented as a header, and under that in much smaller characters 
                      is the parenthesized name. */ ?>
            <table id="main-navigation-tabs" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr> <?php /* This first row is exactly as in creditRecords.php. 
                              NOTE that the one with label '(cred recs)' is NOT the one that links to creditRecords.php. */ ?>
                    <td width="12%" style="text-align:center" bgcolor="<?= $t0color ?>"><h1><a href="multi.php?tab=0<?= $preserveCompanyId ?>" title="<?= $titles[0] ?>">0</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t1color ?>"><h1><a href="multi.php?tab=1<?= $preserveCompanyId ?>" title="<?= $titles[1] ?>">1</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t2color ?>"><h1><a href="multi.php?tab=2<?= $preserveCompanyId ?>" title="<?= $titles[2] ?>">2</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t3color ?>"><h1><a href="multi.php?tab=3<?= $preserveCompanyId ?>" title="<?= $titles[3] ?>">3</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t4color ?>"><h1><a href="multi.php?tab=4<?= $preserveCompanyId ?>" title="<?= $titles[4] ?>">4</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t5color ?>"><h1><a href="multi.php?tab=5<?= $preserveCompanyId ?>" title="<?= $titles[5] ?>">5</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t6color ?>"><h1><a href="multi.php?tab=6<?= $preserveCompanyId ?>" title="<?= $titles[6] ?>">6</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t7color ?>"><h1><a href="creditrecords.php<?= $preserveCompanyId_no_tab ?>" title="<?= $titles[7] ?>">7</a></h1></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t8color ?>"><h1><a href="multi.php?tab=8<?= $preserveCompanyId ?>" title="<?= $titles[8] ?>">8</a></h1></td>
                </tr>
                <tr>
                    <td width="12%" style="text-align:center" bgcolor="<?= $t0color ?>"><a href="multi.php?tab=0<?= $preserveCompanyId ?>" title="<?= $titles[0] ?>" style="font-size:80%;"><?= $titles[0] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t1color ?>"><a href="multi.php?tab=1<?= $preserveCompanyId ?>" title="<?= $titles[1] ?>" style="font-size:80%;"><?= $titles[1] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t2color ?>"><a href="multi.php?tab=2<?= $preserveCompanyId ?>" title="<?= $titles[2] ?>" style="font-size:80%;"><?= $titles[2] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t3color ?>"><a href="multi.php?tab=3<?= $preserveCompanyId ?>" title="<?= $titles[3] ?>" style="font-size:80%;"><?= $titles[3] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t4color ?>"><a href="multi.php?tab=4<?= $preserveCompanyId ?>" title="<?= $titles[4] ?>" style="font-size:80%;"><?= $titles[4] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t5color ?>"><a href="multi.php?tab=5<?= $preserveCompanyId ?>" title="<?= $titles[5] ?>" style="font-size:80%;"><?= $titles[5] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t6color ?>"><a href="multi.php?tab=6<?= $preserveCompanyId ?>" title="<?= $titles[6] ?>" style="font-size:80%;"><?= $titles[6] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t7color ?>"><a href="creditrecords.php<?= $preserveCompanyId_no_tab ?>" title="<?= $titles[7] ?>" style="font-size:80%;"><?= $titles[7] ?></a></td>            
                    <td width="12%" style="text-align:center" bgcolor="<?= $t6color ?>"><a href="multi.php?tab=8<?= $preserveCompanyId ?>" title="<?= $titles[8] ?>" style="font-size:80%;"><?= $titles[8] ?></a></td>                            
                </tr>
                <?php /* The next row is initially independent of visible content, and will be filled in by AJAX. 
                        Except as noted, in column NN (0-based):
                            * There is an HTML DIV with class="financials", initially with no display.
                            * For columns 0-5, this DIV contains a subtable with two rows, each of which has a single column.
                                * In the first row, the TD element has id="tabNN_count"
                                    * On page load, we launch a recursive function repeater, which is set to run after 2 seconds. 
                                      That is what fills these in. See 'repeater' above for more explanation. 
                                      On successful return, that will fill these in.
                                *  The second row differs for different columns:
                                    * columns 0, 1: nothing
                                    * columns 2, 3, 4: TD element has id="tabNN_total"
                                    * columns 5: TD element has id="tabNN_balance"
                                    * empty for columns 6, 7
                                    * (>>>00001 JM: oddly) not even overtly empty for column 8, just ignored. 
                */ ?>
                <tr>
                    <td width="12.5%" style="text-align:center" valign="top">
                        <div class="financials" style="display: none;">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center" id="tab0_count"></td>
                            </tr>
                            <tr>
                                <td style="text-align:center">&nbsp;</td>
                            </tr>
                        </table>
                        </div>
                    </td>
                    <td width="12.5%" style="text-align:center" valign="top">
                        <div class="financials" style="display: none;">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center" id="tab1_count"></td>    
                            </tr>
                            <tr>
                                <td style="text-align:center">&nbsp;</td>
                            </tr>                    
                        </table>
                        </div>
                    </td>
                    <td width="12.5%" style="text-align:center" valign="top">
                        <div class="financials" style="display: none;">                
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center" id="tab2_count"></td>    
                            </tr>
                            <tr>
                                <td style="text-align:center" id="tab2_total"></td>
                            </tr>                    
                        </table>
                        </div>
                    </td>
                    <td width="12.5%" style="text-align:center" valign="top">
                        <div class="financials" style="display: none;">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center" id="tab3_count"></td>    
                            </tr>
                            <tr>
                                <td style="text-align:center" id="tab3_total"></td>
                            </tr>                    
                        </table>
                        </div>
                    </td>
                    <td width="12.5%" style="text-align:center" valign="top">
                        <div class="financials" style="display: none;">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center" id="tab4_count"></td>    
                            </tr>
                            <tr>
                                <td style="text-align:center" id="tab4_total"></td>
                            </tr>                    
                        </table>
                        </div>
                    </td>
                    <td width="12.5%" style="text-align:center" valign="top">
                        <div class="financials" style="display: none;">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center" id="tab5_count"></td>    
                            </tr>
                            <tr>
                                <td style="text-align:center" id="tab5_balance"></td>
                            </tr>                    
                        </table>
                        </div>
                    </td>
                    <td width="12.5%" style="text-align:center">&nbsp;</td>
                    <td width="12.5%" style="text-align:center">&nbsp;</td>
                </tr>
                <?php /* Then comes a row, also initially empty, with the following TD elements;
                    "lastupdate" is time of last update by function repeater, and "counter" is
                    a countdown of time to next such update. */ ?>
                <tr>
                    <td colspan="1" align="right" id="lastupdate">
                    </td>            
                    <td colspan="1" align="right" id="counter">
                    </td>            
                    <td colspan="6"> <?php /*>>>00001 JM: I'd expect colspan="7", but probably harmless) */ ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php 
            /* BEGIN REMOVED 2020-02-19 JM: redunant 
            $titles = array();
            $titles[0] = '(wo closed, tasks open)';
            $titles[1] = '(wo open, tasks closed)';
            $titles[2] = '(wo no invoice)';
            $titles[3] = '(wo closed, open invoice)';
            $titles[4] = '(mailroom -- awaiting delivery)';
            $titles[5] = '(aging summary - awaiting payment)';
            $titles[6] = '(cred recs)';
            $titles[7] = '(do payments)';
            $titles[8] = '(cred memos)';
            // END REMOVED 2020-02-19 JM
            */
            
            echo '<br />';
            
            // Tab title: this time, as a header within the tab.
            echo '<h3>Tab: ' . $tab . '&nbsp;&nbsp;' . $titles[$tab] . '</h3>';
        ?>       
        
        <?php
        if ($tab == 0) {
            /* =================================================
               ============= TAB 0 (wo closed, tasks open) ===== 
               ================================================= */           
            // [Martin comment:] revisit this.  it will not scale forever !!
            
            /* $ret is an associative array as returned by Financial::getWOClosedTasksOpen, with two elements:
                * 'other' is always an empty array.
                * 'workOrders' is an array of WorkOrder objects, one for each closed workOrder that has at least one open task.
            */
            $fin = new Financial();
            $ret = $fin->getWOClosedTasksOpen();
            $workOrders = $ret['workOrders'];
            
            echo 'Item Count : ' . count($workOrders); // Number of closed workOrders with open tasks
            
            // Display: table with no headers, one row for each such workOrder. Each row has two columns:
            //  * first column displays Job Number, links to the job, opens in a new tab or window.
            //  * second column displays workOrder description, links to the workorder, opens in a new tab or window  
            echo '<table border="1" cellpadding="3" cellspacing="0">';
                echo '<tbody>';
                    foreach ($workOrders as $workOrder) {
                        echo '<tr>';                                           
                            $j = new Job($workOrder->getJobId());
                            echo '<td><a target="_blank" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>';
                            echo '<td><a target="_blank" href="' . $workOrder->buildLink() . '">' . $workOrder->getDescription() .  '</a></td>';
                            echo '</tr>';
                    }
                echo '</tbody>';
            echo '</table>';
        } else if ($tab == 1) {
            /* =================================================
               ============= TAB 1 (wo open, tasks closed) =====
               ================================================= */
           
            // [MARTIN COMMENT]: revisit this.  it will not scale forever !!
                        
            /* The getWOOpenTasksClosed method of the Financial class returns us an associative array, which includes 
               an element 'workOrders' that is an array of WorkOrder objects, one for each open workOrder that has no 
               open tasks. We display "Item Count" : count of such workOrders
            */
            $financial = new Financial();
            $ret = $financial->getWOOpenTasksClosed();
            $workOrders = $ret['workOrders'];
            
            echo 'Item Count : ' . count($workOrders);
            
            // table with no headers, one row for each such workOrder. The four columns are described in the foreach loop below.
            echo '<table border="1" cellpadding="3" cellspacing="0">';
            
            foreach ($workOrders as $workOrder) {
                echo '<tr>';
                    $engineers = array();
                    
                    // A series of calls to the WorkOrder::getTeamPosition to get the 
                    //  companyPersonIds for the engineers associated with this workOrder. 
                    // Engineering roles are 'support engineer', 'lead engineer', 
                    // 'staff engineer', and 'eor' (engineer of record). 
                    // There may be more than one person in a particular role. 
                    
                    $supportingEngineers = $workOrder->getTeamPosition(TEAM_POS_ID_SUPPORTENGINEER, false);
                    $leadEngineers = $workOrder->getTeamPosition(TEAM_POS_ID_LEADENGINEER, false);
                    $staffEngineers = $workOrder->getTeamPosition(TEAM_POS_ID_STAFF_ENG, false);
                    $eors = $workOrder->getTeamPosition(TEAM_POS_ID_EOR, false);
                    
                    foreach ($supportingEngineers as $supportingEngineer) {
                        $cp = new CompanyPerson($supportingEngineer['companyPersonId']);
                        $engineers['support engineer'][] = $cp;
                    }
                    
                    foreach ($leadEngineers as $leadEngineer) {
                        $cp = new CompanyPerson($leadEngineer['companyPersonId']);
                        $engineers['lead engineer'][] = $cp;
                    }
                    
                    foreach ($staffEngineers as $staffEngineer) {
                        $cp = new CompanyPerson($staffEngineer['companyPersonId']);
                        $engineers['staff engineer'][] = $cp;                        
                    }
                    
                    foreach ($eors as $eor) {
                        $cp = new CompanyPerson($eor['companyPersonId']);
                        $engineers['eor'][] = $cp;
                    }
                    
                    $j = new Job($workOrder->getJobId());
                            
                    // first column, no header: displays Job Number, links to the job, link opens in a new tab or window.         
                    echo '<td><a target="_blank" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a></td>';
                    
                    // second column, no header: displays workOrder description, links to the workorder, opens in a new tab or window
                    echo '<td><a target="_blank" href="' . $workOrder->buildLink() . '">' . $workOrder->getDescription() .  '</a></td>';
                    
                    /* third column is a subtable. For each engineering role we insert a row in this subtable with two columns (no headers)
                        * role ('support engineer', 'lead engineer', 'staff engineer', or 'eor')
                        * assuming they are associated with the current customer (as of 2019-03, always SSS): 
                          legacyInitials (which are not really "legacy"; an unfortunate column name in the DB).
                    */ 
                    echo '<td>';
                        echo '<table border="0" cellpadding="0" cellspacing="0">';
                            // if (count($engineers)) { JM 2020-02-19 REMOVED a totally unnecessary test.
                                foreach ($engineers as $ekey => $group) {
                                    foreach ($group as $engineer) {
                                        echo '<tr>';
                                            // * role
                                            echo '<td>' . $ekey . '</td>'; 
                                            // * legacyInitials (which are not really "legacy")
                                            echo '<td>';
                                                $p = $engineer->getPerson();
                                                
                                                // BEGIN REPLACED 2020-02-19 JM
                                                // $query = "select * from " . DB__NEW_DATABASE . ".customerPerson ";
                                                // END REPLACED 2020-02-19 JM
                                                // BEGIN REPLACEMENT 2020-02-19 JM
                                                $query = "select legacyInitials from " . DB__NEW_DATABASE . ".customerPerson ";
                                                // END REPLACEMENT 2020-02-19 JM
                                                $query .= " where personId = " . intval($p->getPersonId()) . " ";
                                                $query .= " and customerId = " . intval($customer->getCustomerId()) . " ";
                                                
                                                $result = $db->query($query);
                                                if ($result) {
                                                    if ($result->num_rows > 0) {
                                                        $row = $result->fetch_assoc();
                                                        echo $row['legacyInitials'];
                                                    }
                                                } // else >>>00002 ignores failure on DB query! 
                                                // haven't noted each instance.
                                            echo '</td>';
                                        echo '</tr>';
                                    }
                                }
                            // } JM 2020-02-19 REMOVED if (count($engineers)) 
                        echo '</table>';
                    echo '</td>';
                    
                    // fourth column is totalTime for this workOrder, formatted "h:mm", e.g. "16:00"
                    echo '<td style="text-align:right">';
                        echo number_format($workOrder->totalTime/60,2) . '&nbsp;hr';
                    echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else if ($tab == 2) {
            /* =================================================
               ============= TAB 2 (wo no invoice) =============
               ================================================= */
            ?>
            <?php
            
                // $ret will be an associative array as returned by Financial::getWONoInvoice with two elements:
                //  * 'other' is total time over all relevant workOrders, in minutes.
                //  * 'workOrders' is an array corresponding to the closed workOrders with no invoice; 
                //     elements are the canonical associative-array representation of rows from a SQL SELECT: 
                //     indexes are all the column names from workOrder & job.
                $fin = new Financial();
                $ret = $fin->getWONoInvoice();
                $workOrders = $ret['workOrders'];
                
                // Display:
                // * "Total Time :" total time over all relevant workOrders, written out as hours and minutes, e.g. "5:30 hr".
                // * "Item Count :" size of returned 'workOrders' array 
                echo 'Item Count : ' . count($workOrders);
                echo '<br>';
                echo 'Total Time : ' .  number_format($ret['other']['grandTotalTime']/60,2) . " hr";
                
                echo '<center>';
            ?>
            <?php /* 2020-08-07 JM: address http://bt.dev2.ssseng.com/view.php?id=212: 
                    handle table sorting more uniformly
                    and base it on class of table rather than Id. */ 
            ?>
            <table style="font-size: 80%;" class="tablesorter-demo tablesorter" id="creditRecordTable" border="0" cellpadding="0" cellspacing="1">
                <thead>
                    <tr class="sticky-header">
                        <th>Job</th>
                        <th>WO</th>
                        <th>Client</th>
                        <th>EOR</th>
                        <th>Pro</th>                
                        <th>CloseDate</th>
                        <th>By</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    // $dts is an array of associative arrays providing the canonical 
                    //  representation of DB table WorkOrderDescriptionType.
                    //  Includes types that are no longer active.
                    //
                    // $dtsi is the same data, reorganized so that the top level is an 
                    //  associative array indexed by workOrderDescriptionTypeId. 
                    $dts = getWorkOrderDescriptionTypes();
                    $dtsi = Array(); // Added 2019-12-02 JM: initialize array before using it!
                    foreach ($dts as $dt) {
                        $dtsi[$dt['workOrderDescriptionTypeId']] = $dt;
                    }

                    foreach ($workOrders as $workOrder) {
                            
                        if (intval($workOrder['totalTime'])) {
                            $wo = new WorkOrder($workOrder);
                            $j = new Job($workOrder['jobId']);
                            
                            /* BEGIN REPLACED 2020-06-12 JM
                            $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(),$wo->getWorkOrderStatusId());
                            // END REPLACED 2020-06-12 JM
                            */
                            // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                            $ret = formatGenesisAndAge($wo->getGenesisDate(), $wo->getDeliveryDate(), $wo->isDone());
                            // END REPLACEMENT 2020-06-12 JM
                            
                            $genesisDT = $ret['genesisDT'];
                            $deliveryDT = $ret['deliveryDT'];
                            $ageDT = $ret['ageDT'];                            
                            
                            echo '<tr>';
                                // "Job": displays Job Number as a link to open the Job in a new tab/window, followed by the jobName, which is unlinked.
                                // Parentheses added 2020-02-19 JM
                                echo '<td><a href="' . $j->buildLink() . '">' . $j->getNumber() . '</a> (' . $j->getName(). ')';
                                echo '</td>';
                                
                                // "WO":
                                //  * If this workorder has a meaningful workOrderDescriptionTypeId, then link to open 
                                //    the relevant workOrder page in a new tab/window; displays the typename for that workOrder.
                                //  * If this workorder does not have a meaningful workOrderDescriptionTypeId, then link to open 
                                //    the relevant workOrder page in the present window+tab; displays just '---'
                                //  Link is followed by the workOrder description. 
                                echo '<td>';
                                    if (isset($dtsi[$wo->getWorkOrderDescriptionTypeId()])) {
                                        echo '<a target="_blank" href="' . $wo->buildLink() . '">' . $dtsi[$wo->getWorkOrderDescriptionTypeId()]['typeName'] . '</a>';
                                    } else {
                                        echo '<a href="' . $wo->buildLink() . '">---</a>';
                                    }
                                    echo $wo->getDescription();
                                echo '</td>';
                                
                                // "Client": using WorkOrder object $wo, we get the client, allowing for the
                                //   possibility that there will be more than one client for the workOrder. 
                                //   Then we list them here, with an HTML BR after each. 
                                echo '<td>';
                                    $clients = $wo->getTeamPosition(TEAM_POS_ID_CLIENT);
                                    foreach ($clients as $client) {
                                        $companyPerson = new CompanyPerson($client['companyPersonId']);
                                        $formattedName = '';
                                        if ($companyPerson->getPerson()) {
                                            $formattedName = $companyPerson->getPerson()->getFormattedName(1);
                                        }
                                        $name = '';
                                        if ($companyPerson->getCompany()) {
                                            $name = $companyPerson->getCompany()->getName(1);
                                        }
                                        echo '<a target="_blank" href="' . $companyPerson->buildLink() . '">' . $formattedName . '/' . $name . '</a><br>';
                                    }
                            
                                echo '</td>';
                                
                                // "EOR": engineer of record, handled exactly like client, except using TEAM_POS_ID_EOR 
                                echo '<td>';
                                    $eors = $wo->getTeamPosition(TEAM_POS_ID_EOR);
                                    
                                    foreach ($eors as $ekey => $eor) {
                                        $companyPerson = new CompanyPerson($eor['companyPersonId']);
                                        $formattedName = '';
                                        if ($companyPerson->getPerson()) {
                                            $formattedName = $companyPerson->getPerson()->getFormattedName(1);
                                        }
                                        $name = '';
                                        if ($companyPerson->getCompany()) {
                                            $name = $companyPerson->getCompany()->getName(1);
                                        }
                                        echo '<a target="_blank" href="' . $companyPerson->buildLink() . '">' . $formattedName . '/' . $name . '</a><br>';
                                    }
                                echo '</td>';
                                
                                // "PRO": design professional, handled exactly like client, except using TEAM_POS_ID_DESIGN_PRO 
                                echo '<td>';
                                    $clients = $wo->getTeamPosition(TEAM_POS_ID_DESIGN_PRO);
                                    foreach ($clients as $client) {
                                        $companyPerson = new CompanyPerson($client['companyPersonId']);
                                        $formattedName = '';
                                        if ($companyPerson->getPerson()) {
                                            $formattedName = $companyPerson->getPerson()->getFormattedName(1);
                                        }
                                        $name = '';
                                        if ($companyPerson->getCompany()) {
                                            $name = $companyPerson->getCompany()->getName(1);
                                        }
                                        echo '<a target="_blank" href="' . $companyPerson->buildLink() . '">' . $formattedName . '/' . $name . '</a><br>';
                                    }
                                echo '</td>';

                                // "CloseDate": 'inserted' date for latest workOrder status for this workOrder, 
                                //   in "m/d/Y" form (e.g. "12/9/2020"). Blank if not set.                 
                                echo '<td>';
                                    $status = $wo->getWorkOrderStatus();
                                    if (isset($status['inserted'])) {
                                        echo date("m/d/Y", strtotime($status['inserted']));
                                    }                                    
                                echo '</td>';
                                
                                // "By": formatted name of person responsible for setting that workOrder status. Blank if not set. 
                                echo '<td>';
                                    if (isset($status['personId'])) {
                                        $ppp = new Person($status['personId']);
                                        echo $ppp->getFormattedName(1);
                                    }
                                echo '</td>';

                                // "Hours": totalTime from workOrder, formatted "h:mm", e.g. "16:00" 
                                echo '<td>';
                                    echo number_format($workOrder['totalTime']/60,2) . '';
                                echo '</td>';
                                                
                                //echo '<td></td>'; // REMOVED 2020-02-19 JM: an unwanted blank  column with no heading.
                                
            
                            echo '</tr>';
                        }
                            
                        /* [BEGIN MARTIN COMMENT]
                         [workOrderId] => 5666
                        [jobId] => 2479
                        [nameOLD] => RFI - Over excavation
                        [descriptionOLD] =>
                        [workOrderDescriptionTypeId] => 5
                        [description] =>
                        [deliveryDate] => 0000-00-00 00:00:00
                        [eorOLD] => 0
                        [workStream] => Fu // REMOVED 2020-01-13 JM
                        [workOrderStatusId] => 9
                        [genesisDate] => 2015-09-08 00:00:00
                        [intakeDate] => 0000-00-00 00:00:00
                        [isVisible] => 1
                        [contractNotes] =>
                        [workStreamId] => 19 // REMOVED 2020-01-13 JM
                        [code] => J6J6X2Z7
                        [InvoiceTxnId] => 14A60-1442866779
                        [fakeInvoice] => 0
                        [customerId] => 1
                        [locationId] =>
                        [number] => s1506012
                        [name] => 303 21st Ave
                        [rwname] => 303-21st-ave
                        [jobStatusId] => 9
                        [created] => 2015-06-03
                        [END MARTIN COMMENT]
                        */
                    } // foreach ($workOrders...
                    
                    echo '</tbody>';
                echo '</table>';
            echo '</center>';
        } else if ($tab == 3) { 
            /* =================================================
               ============= TAB 3 (wo closed, open invoice) === 
               ================================================= */
            ?>
            <?php 
                /* $ret will be an associative array as returned by Financial::getWOClosedInvoiceOpen; similar to other
                   more heavily analyzed cases here.
                */
                $fin = new Financial();
                $ret = $fin->getWOClosedInvoiceOpen();
                $invoices = $ret['invoices'];
    
                // Number of invoices
                echo 'Item Count : ' . count($invoices);
                echo '<br>';
                echo '<div class="financials" style="display: none;">';
                // sum of the triggerTotal values for the invoices
                // BEGIN REPLACED 2020-04-03 JM
                // echo 'Trigger Total : ' . number_format($ret['other']['total'], 2, '.', ',');
                // END REPLACED 2020-04-03 JM
                // BEGIN REPLACEMENT 2020-04-03 JM
                echo 'Total, including adjustments : ' . number_format($ret['other']['total'], 2, '.', ',');
                // END REPLACEMENT 2020-04-03 JM
                echo '</div>';
            ?>
                        
            <?php /* 2020-08-07 JM: address http://bt.dev2.ssseng.com/view.php?id=212: 
                    handle table sorting more uniformly
                    and base it on class of table rather than Id. */ 
            ?>
            <table style="font-size: 80%;" class="tablesorter tablesorter-demo" border="0" cellpadding="0" cellspacing="1">
                <thead>
                    <tr class="sticky-header">
                        <th>Job</th>
                        <th>Client</th>
                        <th>EOR</th>
                        <th>WO</th>
                        <th>InvName</th>
                        <th>InvDate</th>
                        <th>InvId</th>
                        <th>LastStat</th>
                        <th>LastDate</th>
                        <th>OrigTot</th> <?php /* was 'Tot', changed 2020-04-03 JM */ ?> 
                        <th class="gets-simple-adjusted-invoice-tooltip">AdjTot</th>  <?php /* was 'TrigTot', changed 2020-04-03 JM */ ?>
                    </tr>
                </thead>
            <tbody>
        <?php
            echo '</tr>';
            foreach ($invoices as $invoice) {
                $j = new Job($invoice['jobId']);
                            
                echo '<tr>';
                    $wo = new WorkOrder($invoice); // We rely on the fact that this per-invoice associative array includes all of the relevant columns
                                                   // for a workOrder object; in fact, it includes every column from DB table 'workOrder'.
                    
                    // "Job": displays Job Number as a link to open the Job in a new tab/window, followed by the jobName, which is unlinked. 
                    echo '<td><a target="_blank" href="' . $j->buildLink() . '">' . $invoice['jobNumber'] . '</a>&nbsp;&nbsp;' . $invoice['jobName'] . '</td>';
                    
                    // "Client": using WorkOrder object $wo, we get the client, allowing for the 
                    //  possibility that there will be more than one client for the workOrder. 
                    //  Then we list them here as ("PERSON/COMPANY"), with an HTML BR after each.  
                    echo '<td>';
                        $clients = $wo->getTeamPosition(TEAM_POS_ID_CLIENT, false); // Boolean false means it's OK to find more than one.
                                                                                    // Typically a business error when that's the case, but
                                                                                    //  we don't want to just hide it!
                                                                                    // This partially fixes http://bt.dev2.ssseng.com/view.php?id=27
                        
                        foreach ($clients as $client) {
                            $companyPerson = new CompanyPerson($client['companyPersonId']);
                                        
                            $formattedName = '';
                            if ($companyPerson->getPerson()) {
                                $formattedName = $companyPerson->getPerson()->getFormattedName(1);
                            }
                            $name = '';
                            if ($companyPerson->getCompany()) {
                                $name = $companyPerson->getCompany()->getName(1);
                            }
                            echo '<a target="_blank" href="' . $companyPerson->buildLink() . '">' . $formattedName . '/' . $name . '</a><br>';
                        } // END foreach
                    echo '</td>';
                    
                    //EOR: engineer of record, handled like client, except using TEAM_POS_ID_EOR and using "legacyInitials" as the display.
                    // Despite the name, "legacyInitials"  are not "legacy", very much used and will be used going forward.
                    // Linked to open the relevant companyPerson page in a new tab/window.
                    echo '<td>';
                        $eors = $wo->getTeamPosition(TEAM_POS_ID_EOR); 
                        foreach ($eors as $eor) {
                            $companyPerson = new CompanyPerson($eor['companyPersonId']);
                            
                            // BEGIN REPLACED 2020-02-19 JM        
                            // $query = "select * from " . DB__NEW_DATABASE . ".customerPerson ";
                            // END REPLACED 2020-02-19 JM
                            // BEGIN REPLACEMENT 2020-02-19 JM
                            $query = "select legacyInitials from " . DB__NEW_DATABASE . ".customerPerson ";
                            // END REPLACEMENT 2020-02-19 JM
                            $query .= " where personId = " . intval($companyPerson->getPerson()->getPersonId()) . " ";
                            $query .= " and customerId = " . intval($customer->getCustomerId()) . " ";
                            
                            $result = $db->query($query);
                            if ($result) {
                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    echo '<a target="_blank" href="' . $companyPerson->buildLink() . '">' . $row['legacyInitials'] . '</a><br>';
                                }
                            } // else >>>00002 ignores failure on DB query!
                        }  // END foreach
                        echo '</td>';
                        
                        //"WO": displays workOrder description, links to open the page for the workOrder in a new tab/window 
                        echo '<td><a href="/workorder/' . $invoice['workOrderId'] . '">' . $invoice['description'] . '</a></td>';
                        
                        // "InvName": displays nameOverride from the invoice, links to open the page for the invoice in a new tab/window 
                        echo '<td><a target="_blank" href="/invoice/' . $invoice['invoiceId'] . '">' . $invoice['nameOverride'] . '</a></td>';
                        
                        // "InvDate": displays date associated with the latest status change for the invoice, 
                        // links to open the page for the invoice in a new tab/window 
                        echo '<td><a target="_blank" href="/invoice/' . $invoice['invoiceId'] . '">' . date("m/d/Y",strtotime($invoice['invoiceDate'])) . '</a></td>';
                        
                        // "InvId": invoice ID. This and what follows are not links. 
                        echo '<td>' . $invoice['invoiceId']  . '</td>';
                        
                        // "LastStat": display status name of the latest status change for the invoice. 
                        echo '<td>' . $invoice['invoiceStatusName'] . '</td>';
                        
                        // "LastDate": displays date associated with the latest status change for the invoice. 
                        echo '<td>' . $invoice['lastDate'] . '</td>';
                        
                        // "OrigTot": original total for invoice (in U.S. currency, 2 places past the decimal) 
                        echo '<td>$' . $invoice['total']  . '</td>';
                        
                        // "AdjTot": trigger total for invoice (in U.S. currency, 2 places past the decimal)
                        echo '<td class="gets-simple-adjusted-invoice-tooltip">$' . $invoice['triggerTotal']  . '</td>';                                
                                
                    echo '</tr>';
                }
            echo '</table>';
        } else if ($tab == 4) { 
            /* =================================================
               ============= TAB 4 (mailroom -- awaiting delivery)
               ================================================= */
            ?>
        <?php        
            /* $ret will be an associative array with two elements, as returned by Financial::getAwaitingDelivery
                * 'other' is itself an associative array with two elements:
                    * 'total', the sum of the triggerTotal values
                    * 'balance', always zero.
                * 'invoices' (before version 2020-2, 'workOrders') is an array each element of which represents 
                  an invoice awaiting delivery; the content is an associative array, with 
                  indexes corresponding to:
                   * each column of DB table workOrder
                   * each column of DB table invoice
                   * 'lastStat': invoice status name
                   * 'lastTime': invoice status time
                   * the following from columns in DB table job: 
                     * 'jobId'
                     * 'customerId'
                     * 'locationId'
                     * number as 'jobNumber'
                     * name as 'jobName'
                     * 'rwname'
                     * description as 'jobDescription'
                     * 'jobStatusId'
                     * 'created'
                     * 'code'
                 */ 
            $fin = new Financial();
            $ret = $fin->getAwaitingDelivery();
            $invoices = $ret['invoices'];

            htmlAndScriptForCompanySelection($tab, $companyId);
            $style = $companyId ? '' : ' style="display:none;"';
            ?>
            <div>
            <span id="offer-multiple-send"><button>Send selected invoices</button></span>
            <span id="offer-multiple-invoice-pdf" <?= $style ?> checked/>&nbsp;<button>Create PDF of all selected invoices</button></span>
            </div>
            <?php
            unset($style);
            htmlAndScriptForMinAndMaxAgeSections($tab);
            ?>
            <br />
            <?php
            if ($companyId) {
            ?>    
                Item Count : <span id="itemCount"></span>
            <?php
            } else {
            ?>
                Item Count : <span id="itemCount"><?= count($invoices) ?></span>
            <?php                
            }
            $itemCount = 0; // initialize
            ?>
            <div class="financials" style="display: none;">
            <?php
            // sum of the triggerTotal values for the invoices
            // In UI, call this "Adjusted Total", not "Trigger Total"
            // echo 'Trigger Total : ' . number_format($ret['other']['total'], 2, '.', ',');
            // END REPLACED 2020-04-03 JM
            // BEGIN REPLACEMENT 2020-04-03 JM
            echo 'Total, including adjustments : ' . number_format($ret['other']['total'], 2, '.', ',');
            // END REPLACEMENT 2020-04-03 JM
            echo '</div>';
            ?>
            <script>
            $(function() {
               $('#offer-multiple-send').click(function() {
                    let $checked = $("tbody .multiple-send-checkbox").filter(':visible').filter(':checked');
                    if ($checked.length) {
                        $checked.each(function() {
                            let $this = $(this);
                            sendInvoice($this.val());
                        });
                    } else {
                        alert('Nothing selected to send, check box for at least one invoice');
                    }
               });
               $('thead .multiple-send-checkbox').click(function() {
                    let $this = $(this);   
                    $("tbody .multiple-send-checkbox").filter(':visible').prop('checked', $this.prop('checked'));   
               });
            });
            </script>
            <?php /* 2020-08-07 JM: address http://bt.dev2.ssseng.com/view.php?id=212: 
                    handle table sorting more uniformly
                    and base it on class of table rather than Id. */ 
            ?>
            <span style="float: right; margin-right: 700px;">
            <input type="checkbox" class="multiple-invoice-checkbox" value="0" id="legcheck" checked /> Leg 
            <input type="checkbox" class="multiple-invoice-checkbox" value="0" id="newcheck"/> New
            </span>
            <table id="tab4-table" style="font-size: 80%;" class="tablesorter tablesorter-demo" border="0" cellpadding="0" cellspacing="1">
                <thead>
                    <tr class="sticky-header">
                        <th align="left"> <?php /* blank column heading for "send", just a checkbox; through v2020-2, this was last, but in v2020-3 it became first */ ?>
                        <input type="checkbox" class="multiple-send-checkbox" value="0" />
                        </th>
                        <th>Job</th>
                        <th>Company</th>
                        <th>Person</th>
                        <th>WO</th>
                        <th>InvId</th>
                        <th>InvDate</th>
                        <th class="gets-simple-adjusted-invoice-tooltip">Inv.&nbsp;Amt.</th>
                        <th align="left"> <?php /* blank column heading, unless we are showing the multiple-invoice-checkbox */
                        $style = $companyId ? '' : ' style="display:none;"';
                        ?>
                        <?php unset($style); ?>
                        </th>
                        </tr>
                    </thead>
                    <tbody>
<script>
$(document).ready(function(){

    $("#legcheck").change(function(){
        if ($(this).is(':checked')) {
            $('.newinv').prop('checked', false);
            $('.legacyinv').prop('checked', true);
			$("#newcheck").prop('checked', false);
        } else {
            $('.legacyinv').prop('checked', false);
        }
    })

    $("#newcheck").change(function(){
        if ($(this).is(':checked')) {
            $('.legacyinv').prop('checked', false);
            $('.newinv').prop('checked', true);
			$("#legcheck").prop('checked', false);
        } else {
            $('.newinv').prop('checked', false);
        }
    })
	$('input.legacyinv').change(function(){
		if ($(this).is(':checked')){
			$("#newcheck").prop('checked', false);
            $('.newinv').prop('checked', false);
		}
	})

	$('input.newinv').change(function(){
		if ($(this).is(':checked')){
			$("#legcheck").prop('checked', false);
            $('.legacyinv').prop('checked', false);
		}
	})
})

</script>

                <?php                     
                    // echo '</tr>'; // REMOVED 2020-02-19 JM: almost certainly bad: we just started the TBODY, how can we be closing a TR?
                    
                    $associativeCompanyArray = Array();
                    foreach ($invoices as $invoice) {
                        $j = new Job($invoice['jobId']);
                        $wo = new WorkOrder($invoice); // We rely on the fact that this per-invoice associative array includes all of the relevant columns
                                                       // for a workOrder object; in fact, it includes every column from DB table 'workOrder'.
                        // Before 2020-06-18, we gathered info about clients in the middle of writing the row.
                        // Pulled it outside of that, and reworked it a little.
                        $clients = $wo->getTeamPosition(TEAM_POS_ID_CLIENT, false); // Boolean false means it's OK to find more than one.
                                                                                    // Typically a business error when that's the case, but
                                                                                    //  we don't want to just hide it!
                                                                                    // This partially fixes http://bt.dev2.ssseng.com/view.php?id=27
                        $client_info = array(); // which we will fill in below 
                        $show_this_row = ($companyId == 0); // If we don't set this true here, then we need to look at the client(s) to decide whether to show the row.
                        foreach ($clients as $client) {
                            $companyPerson = new CompanyPerson($client['companyPersonId']);
                            $company = $companyPerson->getCompany();
                            if ($company) {
                                $companyName = $company->getName(1);
                                $associativeCompanyArray[$company->getCompanyName()] = $company->getCompanyId();
                            } else {
                                $companyName = '';
                                $logger->error2('1592850753', 'invalid companyId ' . $companyPerson->getCompanyId());
                            }
                            if (!$show_this_row) {
                                $show_this_row = ($companyId == $companyPerson->getCompanyId()); // written in a way this will work even if the companyId is invalid
                            }
                            unset($company);
                            $formattedName = '';
                            if ($companyPerson->getPerson()) {
                                $formattedName = $companyPerson->getPerson()->getFormattedName(1);
                            }
                            $client_info[] = array(
                                'cpLink' => $companyPerson->buildLink(),
                                'companyName' => $companyName,
                                'personName' => $formattedName
                            );
                        }
                        if ($show_this_row) {
                            ++ $itemCount;
                        }
                        $num_clients = count($client_info);
                        if ($num_clients <= 1) {
                            // normal case, ignore $rowspan
                            $rowspan = '';
                        } else {
                            $rowspan = ' rowspan="'. $num_clients . '"';
                        }

                        echo '<tr ' . ($show_this_row ? '' : 'class="wrong-company"') . ' data-companyid="' . $companyPerson->getCompanyId() . '" '.($wo->getWorkOrderId()<=14174?' class="legacy" ': ' class="newinv" ').'>';
                            // (no header) link labeled '[send]'. Calls local function sendInvoice(invoiceId). 
                            echo '<td ' . $rowspan . 'id="confirm_send_' . $invoice['invoiceId'] . '">';
                                echo '<input type="checkbox" class="multiple-send-checkbox" value="' . $invoice['invoiceId'] . '" />&nbsp;';
                                echo '[<a href="javascript:sendInvoice(' . $invoice['invoiceId'] . ')">send</a>]&nbsp;&nbsp;';
                            echo '</td>';
                            
                            // "Job": displays Job Number as a link to open the Job in a new tab/window, followed by the jobName, which is unlinked.
                            echo '<td' . $rowspan . '><a target="_blank" href="' . $j->buildLink() . '">' . $invoice['jobNumber'] . '</a>&nbsp;&nbsp;' . $invoice['jobName'] . '</td>';

                            /* BEGIN REPLACED 2020-06-29 JM
                            // "Client": using WorkOrder object $wo, we call get client, allowing for the
                            //   possibility that there will be more than one client for the workOrder. 
                            //   Then we list them here, with an HTML BR after each.
                            echo '<td>';
                                // For each client, we display formatted person name, a slash ('/'), and company name.
                                // All that is linked to open the relevant companyPerson page in a new tab/window.
                                foreach ($client_info as $c_info) {
                                    echo '<a target="_blank" href="' . $c_info['cpLink'] . '">' . $c_info['personName'] . '/' . $c_info['companyName'] . '</a><br>';
                                }
                            echo '</td>';
                            // END REPLACED 2020-06-29 JM
                            */
                            // BEGIN REPLACEMENT 2020-06-29 JM
                            if ($num_clients) {
                                echo '<td class="company-name-cell">' . 
                                    '<a target="_blank" href="' . $client_info[0]['cpLink'] . '">' . $client_info[0]['companyName'] . '</a></td>';
                                
                                echo '<td>' . 
                                    '<a target="_blank" href="' . $client_info[0]['cpLink'] . '">' . $client_info[0]['personName'] . '</a></td>';
                            } else {
                                echo '<td></td>';
                                echo '<td></td>';
                            }                            
                            // END REPLACEMENT 2020-06-29 JM
                            
                            // "WO": displays workOrder description, links to open the page for the workOrder in a new tab/window 
                            echo '<td' . $rowspan . '><a href="/workorder/' . $invoice['workOrderId'] . '">' . $invoice['description'] . '</a></td>';
                            
                            // "InvId": invoice ID, links to open the page for the invoice in a new tab/window 
                            echo '<td' . $rowspan . '><a target="_blank" href="/invoice/' . $invoice['invoiceId'] . '">' . $invoice['invoiceId']  . '</a></td>';
                            
                            // InvDate: displays date associated with the latest status change for the invoice. Not a link, nor is anything from here down 
                            echo '<td' . $rowspan . '>' . date("m/d/Y",strtotime($invoice['invoiceDate'])) . '</td>';
                            
                            // "Inv. Amt.: invoice trigger total (U.S. currency, 2 places past the decimal) 
                            echo '<td class="gets-simple-adjusted-invoice-tooltip" style="text-align:right">$' . $invoice['triggerTotal']  . '</td>';
                            
                            echo '<td' . $rowspan . '>';// (no header) link labeled '[print]', links to invoicepdf.php for this invoice
                                // Also, added for v2020-3, a checkbox for printing multiple invoices
                                $style = $companyId ? '' : ' style="display:none;"';
                                echo '<input type="checkbox" value="' . $invoice['invoiceId'] . '" ' . $style . ($wo->getWorkOrderId()<=14174?' class="multiple-invoice-checkbox legacyinv"  checked ': ' class="multiple-invoice-checkbox newinv" ').'/>&nbsp;';
                                echo '<a href="invoicepdf.php?invoiceId=' . $invoice['invoiceId'] . '">[print]</a>';
                            echo '</td>';
                            
                            // The following hidden column added 2020-06-19 JM, is very artificial, but it allows us to use the same code
                            //  as in TAB 5 to deal with the age of the invoice and whether to show it.
                            $mark_as_ancient = '';
                            $ageDT = '';
                            if ($invoice['invoiceDate'] != '0000-00-00 00:00:00') {
                                $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $invoice['invoiceDate']);
                                $dt2 = new DateTime;
                                $interval = $dt1->diff($dt2);
                                $ageDT = $interval->format('%a');
                            } else {
                                $ageDT = '&mdash;';
                                $mark_as_ancient = ' no-invoice-date';
                            }
                            $do_not_show = 'style="display:none"';
                            echo '<td ' . $rowspan . '' . $do_not_show . ' class="age' . $mark_as_ancient . '" style="text-align:center">';
                                echo $ageDT;
                            echo '</td>';    
                        echo '</tr>';
                    }
                    ksort($associativeCompanyArray); // sort alphabetically by key (company name)
                echo '</tbody>'; // Added 2020-02-19 JM 
                ?>
                <script>
                    <?php
                    foreach ($associativeCompanyArray AS $name => $id) {
                        /* JM 2020-08-07
                            Fixing http://bt.dev2.ssseng.com/view.php?id=212 in the following:
                            We hadn't accounted for apostrophe in company name.
                            OLD CODE WAS: 
                                companyList.push({value: '<?= $name ?>', data: <?= $id ?>});
                        */
                        ?>
                        companyList.push({value: '<?= str_replace ("'", "\\'", $name)  ?>', data: <?= $id ?>});
                    <?php
                    }
                    ?>
                </script>
                <?php
                if ($companyId) {
                    ?>
                    <script>
                        $('#itemCount').html('<?= $itemCount ?>');                        
                    </script>    
                    <?php    
                }
                
            echo '</table>';
        } else if ($tab == 5) { 
?>


<?php
            /* =================================================
               ============= TAB 5 (aging summary - awaiting payment)
               ================================================= */
            $invoices = array();

            $financial = new Financial();
            
            /* The getAwaitingPayment method of the Financial class, called here,
               returns an associative array with two elements, as returned by Financial::getAwaitingPayment :
               * 'other', itself an associative array with two elements:
                  * 'total', the sum of the triggerTotal values for the invoices.
                  * 'balance', the sum of the balances on these invoices, after accounting for payments.
                * 'invoices' (before version 2020-2, 'workOrders') is an array of associative arrays, each of which represents an 
                  invoice awaiting payment; the members of each associative array include: 
                  * indexes corresponding to each column of DB table workOrder
                  * indexes corresponding to each column of DB table invoice
                  * 'lastStat': invoice status name
                  * 'lastTime': invoice status time 
                  * 'sumPayments' and 'bal': calculated for the invoice
                  * the following from columns from DB table job: 
                    * 'jobId'
                    * 'customerId'
                    * 'locationId'
                    * number as 'jobNumber', 
                    * name as 'jobName', 
                    * 'rwname', 
                    * description as 'jobDescription'
                    * 'jobStatusId'
                    * 'created'
                    * 'code'
            */
            $ret = $financial->getAwaitingPayment();
            $invoices = $ret['invoices'];
            htmlAndScriptForCompanySelection($tab, $companyId);
            $style = $companyId ? '' : ' style="display:none;"';
            ?>
            <div>
            <span id="offer-multiple-invoice-pdf" <?= $style ?> checked/>&nbsp;<button>Create PDF of all selected invoices</button></span>
            </div>
            <?php
            htmlAndScriptForMinAndMaxAgeSections($tab);
            ?>
            <br />
            <?php
            if ($companyId) {
            ?>    
                Item Count : <span id="itemCount"></span>
            <?php
            } else {
            ?>
                Item Count : <span id="itemCount"><?= count($invoices) ?></span>
            <?php                
            }
            $itemCount = 0; // initialize
            ?>
            <div class="financials" style="display: none;">
            <?php
                // sum of the triggerTotal values for the invoices
                // BEGIN REPLACED 2020-04-03 JM
                // echo 'Trigger Total : ' . number_format($ret['other']['total'], 2, '.', ',');
                // END REPLACED 2020-04-03 JM
                // BEGIN REPLACEMENT 2020-04-03 JM
                echo 'Total, including adjustments : ' . number_format($ret['other']['total'], 2, '.', ',');
                // END REPLACEMENT 2020-04-03 JM
                echo '<br>';
                // sum of the balances on these invoices, after accounting for payments.
                echo 'Balance : ' . number_format($ret['other']['balance'], 2, '.', ',');
            echo '</div>';
            ?>
            
            <?php /* 2020-08-07 JM: address http://bt.dev2.ssseng.com/view.php?id=212: 
                    handle table sorting more uniformly
                    and base it on class of table rather than Id. */ 
            ?>
                        <span style="float: right; margin-right: 700px;">
            <input type="checkbox" class="multiple-invoice-checkbox" value="0"  id="legcheck" checked /> Leg 
            <input type="checkbox" class="multiple-invoice-checkbox" value="0"  id="newcheck"/> New
</span>
            <table id="tab5-table" style="font-size: 80%;" class="tablesorter tablesorter-demo" border="0" cellpadding="0" cellspacing="1">
                <thead>
                    <tr class="sticky-header">
                        <th>Job</th>
                        <?php /* BEGIN REPLACED 2019-12-31 JM for bug http://bt.dev2.ssseng.com/view.php?id=63 
                        <th>Client</th>
                        */
                        // BEGIN REPLACEMENT 2019-12-31 JM
                        ?>
                        <th>Company</th>
                        <th>Person</th>
                        <?php /* END REPLACEMENT 2019-12-31 JM
                        Corresponding code changes in the loop below are not so neatly marked, because this required a restructure.
                        */ ?>
                        <th>WO</th>
                        <th>InvId</th>
                        <th>InvDate</th>
                        <th>Age</th>
                        <th>Notes</th>
                        <th>Last Active</th>
                        <th>Balance</th>
                        <th align="left"> <?php /* Printing added for v2020-3. Blank column heading, unless we are showing the multiple-invoice-checkbox */
                        $style = $companyId ? '' : ' style="display:none;"';
                        ?>
                        <?php unset($style); ?>
                        </th>
                        <th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th> <?php /* blank column heading */ ?> 
                    </tr>
                </thead>
                <tbody>
<script>
$(document).ready(function(){

    $("#legcheck").change(function(){
        if ($(this).is(':checked')) {
            $('.newinv').prop('checked', false);
            $('.legacyinv').prop('checked', true);
			$("#newcheck").prop('checked', false);
        } else {
            $('.legacyinv').prop('checked', false);
        }
    })

    $("#newcheck").change(function(){
        if ($(this).is(':checked')) {
            $('.legacyinv').prop('checked', false);
            $('.newinv').prop('checked', true);
			$("#legcheck").prop('checked', false);
        } else {
            $('.newinv').prop('checked', false);
        }
    })
	$('input.legacyinv').change(function(){
		if ($(this).is(':checked')){
			$("#newcheck").prop('checked', false);
            $('.newinv').prop('checked', false);
		}
	})

	$('input.newinv').change(function(){
		if ($(this).is(':checked')){
			$("#legcheck").prop('checked', false);
            $('.legacyinv').prop('checked', false);
		}
	})
})

</script>

            <?php
                $bb = 0;
                // echo '</tr>'; // REMOVED 2020-02-19 JM: almost certainly bad: we just started the TBODY, how can we be closing a TR?
                /* JM 2019-10-25: The thing about $excludes = array(0,0,0,0) REMOVED here was just a roundabout way 
                   to make sure the invoiceId is nonzero.
                   I asked Martin about this 2018-11 and he responded, 
                   "This is as it reads. was simply checking some IDs and doesn't anymore." 
                   Rewritten that more normally 2019-10-25; feel free eventually to kill everything about $excludes including this comment.
                */
                /*
                   Martin writes 2018-11-11, "this whole area has a few loose ends due to the nature of 
                   regular requirement changes at the time. Overall it functions as intended though." 
                   Joe thinks we should clean up the loose ends: see PROPOSED REPLACEMENT CODE just below)
                */
                //  $excludes = array(0,0,0,0); // REMOVED 2019-10-25 JM
                $associativeCompanyArray = Array();
                foreach ($invoices as $invoice) {
                    if ($invoice['invoiceId']) { // JM 2020-02-19: I don't think it's possible for this to be false    
                        $j = new Job($invoice['jobId']); // job object
                        $wo = new WorkOrder($invoice); // We rely on the fact that this per-invoice associative array includes all of the relevant columns
                                                       // for a workOrder object; in fact, it includes every column from DB table 'workOrder'.
                        
                        // Before 2019-12-23, we gathered info about clients in the middle of writing the row.
                        // Pulled it outside of that, and reworked it a little.
                        $clients = $wo->getTeamPosition(TEAM_POS_ID_CLIENT, false); // Boolean false means it's OK to find more than one.
                                                                                    // Typically a business error when that's the case, but
                                                                                    //  we don't want to just hide it!
                                                                                    // This partially fixes http://bt.dev2.ssseng.com/view.php?id=27
                        
                        $client_info = array(); // which we will fill in below
                        
                        $show_this_row = ($companyId == 0); // If we don't set this true here, then we need to look at the client(s) to decide whether to show the row. 
                        foreach ($clients as $client) {
                            $companyPerson = new CompanyPerson($client['companyPersonId']);
                            $company = $companyPerson->getCompany();
                            if ($company) {
                                $name = $company->getName(1);
                                $associativeCompanyArray[$company->getCompanyName()] = $company->getCompanyId();
                            } else {
                                $name = '';
                                $logger->error2('1592850703', 'invalid companyId ' . $companyPerson->getCompanyId()); 
                            }
                            if (!$show_this_row) {
                                $show_this_row = ($companyId == $companyPerson->getCompanyId()); // written in a way this will work even if the companyId is invalid
                            }
                            unset($company);
                                                                    
                            $formattedName = '';
                            if ($companyPerson->getPerson()) {
                                $formattedName = $companyPerson->getPerson()->getFormattedName(1);
                            }
                            
                            // Now we look into the billing block aspect.
                            $blocked = false;
                            /* BEGIN REPLACED 2020-02-19 JM
                            // select * is overkill, could just use select billingBlockTypeId
                            // Also, we really only care about the latest row, and 'inserted desc' already
                            // gives us reverse chronological order, so this could use 'limit 1'.
                            $blocks = array();
                            $query = "SELECT * FROM " . DB__NEW_DATABASE . ".billingBlock ";
                            $query .= " where companyPersonId = " . intval($companyPerson->getCompanyPersonId()) . " ";
                            $query .= " order by inserted desc ";
                            
                            if ($result = $db->query($query)) { // Assignment inside "if" statement
                                if ($result->num_rows > 0) {
                                    // 00018 JM: No good reason for a 'while' rather than an 'if': we only care about first row.
                                    while ($row = $result->fetch_assoc()) {
                                        $blocks[] = $row;
                                    }
                                }
                            }
                            // If there is block info, and the latest isn't "remove", then this client is blocked
                            if (count($blocks)) {
                                $current = $blocks[0];
                                if ($current['billingBlockTypeId'] != BILLBLOCK_TYPE_REMOVEBLOCK) {
                                    $blocked = true;
                                }
                            }
                            // END  REPLACED 2020-02-19 JM
                            */
                            
                            // BEGIN REPLACEMENT 2020-02-19 JM
                            // We really only care about the latest row, and 'inserted desc' 
                            // gives us reverse chronological order, so we can use 'limit 1'.
                            $query = "SELECT billingBlockTypeId FROM " . DB__NEW_DATABASE . ".billingBlock ";
                            $query .= "WHERE companyPersonId = " . intval($companyPerson->getCompanyPersonId()) . " ";
                            $query .= "ORDER BY inserted DESC ";
                            $query .= "LIMIT 1;";

                            // If there is block info, and the latest isn't "remove", then this client is blocked
                            $result = $db->query($query);
                            if ($result) {
                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $blocked = $row['billingBlockTypeId'] != BILLBLOCK_TYPE_REMOVEBLOCK;
                                } // else: it's fine that this person was never blocked
                            } // else >>>00002 ignores failure on DB query!
                            
                            $client_info[] = array(
                                'blocked' => $blocked, 
                                'cpLink' => $companyPerson->buildLink(),
                                'companyName' => $name,
                                'personName' => $formattedName
                            );
                        }
                        if ($show_this_row) {
                            ++$itemCount;
                        }
                        $num_clients = count($client_info);
                        if ($num_clients <= 1) {
                            // normal case, ignore $rowspan
                            $rowspan = '';
                        } else {
                            $rowspan = ' rowspan="'. $num_clients . '"';
                        }
                            
                        echo '<tr ' . ($show_this_row ? '' : 'class="wrong-company"') . ' data-companyid="' . $companyPerson->getCompanyId() . '" '.($wo->getWorkOrderId()<=14174?' class="legacy" ': ' class="newinv" ').'>';
                            // "Job": Job Number as a link to open the Job in a new tab/window, followed by the jobName, which is unlinked. 
                            echo '<td' . $rowspan . '><a target="_blank" href="' . $j->buildLink() . '">' . $invoice['jobNumber'] . '</a>&nbsp;&nbsp;' . $invoice['jobName'] . '</td>';
                            
                            // For each client, we will want to display company name and person name. 
                            // Both are linked to open the relevant companyPerson page in a new tab/window.
                            // Cell color will be a light red if client has a billing block.
                            // Here we deal only with the first client; additional clients (rare) get
                            //  rows of their own.
                            if ($num_clients) {
                                echo '<td' . ($client_info[0]['blocked'] ? ' style="background-color:#ffbdbd"' : '' ). '>' . 
                                    '<a target="_blank" href="' . $client_info[0]['cpLink'] . '">' . $client_info[0]['companyName'] . '</a></td>';
                                
                                echo '<td' . ($client_info[0]['blocked'] ? ' style="background-color:#ffbdbd"' : '' ). '>' . 
                                    '<a target="_blank" href="' . $client_info[0]['cpLink'] . '">' . $client_info[0]['personName'] . '</a></td>';
                            } else {
                                echo '<td></td>';
                                echo '<td></td>';
                            }
                            
                            // "WO": display workOrder description, link to open the page for the workOrder in the *current* tab/window.
                            echo '<td' . $rowspan . '><a href="/workorder/' . $invoice['workOrderId'] . '">' . $invoice['description'] . '</a></td>';
                            
                            // "InvId": display invoice ID, link to open the page for the invoice in a new tab/window
                            echo '<td style="text-align:center"' . $rowspan . '><a target="_blank" href="/invoice/' . $invoice['invoiceId'] . '">' . $invoice['invoiceId']  . '</a></td>';
                            
                            // "InvDate": display invoiceDate from invoice DB table
                            // JM 2020-07-29 added the test here to at least partially address http://bt.dev2.ssseng.com/view.php?id=196
                            //  (Tab 5 Error on date of invoice) 
                            if ($invoice['invoiceDate'] == '0000-00-00 00:00:00') {
                                // Let's at least print something sane if there is no invoiceDate. However, 
                                //  we shouldn't be on tab 5 for an invoice that was never sent.
                                echo '<td' . $rowspan . '>&nbsp;&nbsp;&nbsp;&nbsp;&mdash;</td>';
                            } else {
                                echo '<td' . $rowspan . '>' . date("m/d/Y",strtotime($invoice['invoiceDate'])) . '</td>';
                            }
                            
                            // "Age": days elapsed since $invoice['invoiceDate']
                            $mark_as_ancient = '';
                            $ageDT = '';
                            if ($invoice['invoiceDate'] != '0000-00-00 00:00:00') {
                                $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $invoice['invoiceDate']);
                                $dt2 = new DateTime;
                                if($dt1 !== false) { // 2021-01-04 George addded. Because $dt1 Returns a new DateTime instance or false on failure.
                                    $interval = $dt1->diff($dt2);
                                    $ageDT = $interval->format('%a');
                                }
                            } else {
                                $ageDT = '&mdash;';
                                $mark_as_ancient = ' no-invoice-date';
                            }
                            echo '<td class="age' . $mark_as_ancient . '" style="text-align:center"' . $rowspan . '>';
                                echo $ageDT;
                            echo '</td>';    
                                
                            /* "Notes": We check DB table agingNote to see whether there are any rows 
                               for this invoiceId. We display the count of such notes (not their content) here. 
                               If that count is nonzero, then it gets a light blue-green background. 
                               The count is in an HTML SPAN with class="expand-notes-open" (before version
                               2020-2, "expandopen"), data-invoiceid="NN" (before version
                               2020-2, id="NN") where "NN" is the invoiceId.
                                                              
                               When the user hovers the mouse over this cell, we pop up a stripped-down jQuery 
                               dialog just above it, using the 'expand-notes-dialog' DIV (before version 2020-2,
                               just 'expanddialog'). Initially it shows the 
                               ajax_loader.gif, then we load in the content of 
                               /ajax/invoiceactivitynotes.php?invoiceId=invoiceId and display it in the dialog. */
                            $notes = array();                                
                            $query =  " select * from " . DB__NEW_DATABASE . ".agingNote ";
                            $query .= " where invoiceId = " . $invoice['invoiceId'] . " ";
                            $query .= " order by inserted desc ";
                            
                            $result = $db->query($query);
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    $notes[] = $row;
                                }
                            } // else >>>00002 ignores failure on DB query!
                            $bgc = "";
                            if (count($notes)) {
                                $bgc = ' bgcolor="#8cffbf" ';
                            }
                            
                            echo '<td ' . $bgc . ' style="text-align:center"' . $rowspan . '>';
                                /* OLD CODE replaced by JM 2019-03-28: SPAN was not properly closed, obvious error, fixing.
                                ALSO substituted a more mnemonic class name, and used a 'data' attribute in place of a numeric HTML ID. 
                                echo '<span class="expandopen" id="' . $invoice['invoiceId'] . '">' . count($notes) . '</span';
                                */
                                // BEGIN REPLACEMENT CODE JM 2019-03-28, improved 2020-02-20
                                echo '<span class="expand-notes-open" data-invoiceid="' . $invoice['invoiceId'] . '">' . count($notes) . '</span>';
                                // END REPLACEMENT CODE JM 2019-03-28
                            echo '</td>';
                            
                            // "Last Active": Number of days since the latest row in agingNote for this invoiceId; blank if no such row. 
                            echo '<td style="text-align:center"' . $rowspan . '>';
                                if (intval(count($notes))) {
                                    $n = $notes[0];
                                    
                                    $ageDT = '';
                                    if ($n['inserted'] != '0000-00-00 00:00:00'){
                                        $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $n['inserted']);
                                        $dt2 = new DateTime;
                                        $interval = $dt1->diff($dt2);
                                        $ageDT = $interval->format('%a');
                                    } else {
                                        $ageDT = '&mdash;';
                                    }
                                    echo $ageDT;
                                } else {
                                    echo $ageDT;
                                }
                            echo '</td>';
                            
                            // "Balance": balance on invoice (U.S. currency, 2 places past the decimal)
                            
                            // $bal = ''; // REMOVED 2020-02-04 JM: set but not used before setting again
                            echo '<td style="text-align:right"' . $rowspan . '>';
                                echo number_format($invoice['balance'], 2, '.', '');
                            echo '</td>';
                            
                            echo '<td' . $rowspan . '>'; // printing added for v2020-3 (no header) link labeled '[print]', links to invoicepdf.php for this invoice
                                // Also,a checkbox for printing multiple invoices
                                $style = $companyId ? '' : ' style="display:none;"';
                                echo '<input type="checkbox" value="' . $invoice['invoiceId'] . '" ' . $style . ($wo->getWorkOrderId()<=14174?' class="multiple-invoice-checkbox legacyinv"  checked ': ' class="multiple-invoice-checkbox newinv" ').'/>&nbsp;';
                                echo '<a href="invoicepdf.php?invoiceId=' . $invoice['invoiceId'] . '">[print]</a>';
                            echo '</td>';
                            
                            // (no header): if balance is zero, shows a button labeled '[close]', that uses 
                            //  GET method to self-submit. This effectively changes status of that invoice 
                            //  to 'closed' by inserting a row in DB table invoiceStatusTime, then reloads 
                            //  page using this same tab. 
                            echo '<td' . $rowspan . '>';
                                if (is_numeric($invoice['balance'])) {
                                    if ($invoice['balance'] == 0) {
                                        echo '[<a href="multi.php?tab=5&act=close&invoiceId=' . intval($invoice['invoiceId']) . '">close</a>]';
                                    }
                                }
                            echo '</td>';
                        echo '</tr>';
                        
                        // In case of multiple clients (this will rarely happen, and client 0 is already handled):
                        for ($i=1; $i<$num_clients; ++$i) {
                            echo '<tr class="extra-client">';
                            // For each client, we will want to display company name and person name. 
                            // Both are linked to open the relevant companyPerson page in a new tab/window.
                            // Cell color will be a light red if client has a billing block.
                            echo '<td' . ($client_info[$i]['blocked'] ? ' style="background-color:#ffbdbd"' : '' ). '>' . 
                                '<a target="_blank" href="' . $client_info[$i]['cpLink'] . '">' . $client_info[$i]['companyName'] . '</a></td>';
                            
                            echo '<td' . ($client_info[$i]['blocked'] ? ' style="background-color:#ffbdbd"' : '' ). '>' . 
                                '<a target="_blank" href="' . $client_info[$i]['cpLink'] . '">' . $client_info[$i]['personName'] . '</a></td>';
                            echo '</tr>';    
                        }     
                    } // END if ($invoice['invoiceId']
                } // END foreach ($invoices...
                ksort($associativeCompanyArray); // sort alphabetically by key (company name)
                ?>
                <script>
                    <?php
                    foreach ($associativeCompanyArray AS $name => $id) {
                        /* JM 2020-08-07
                            Fixing http://bt.dev2.ssseng.com/view.php?id=212 in the following:
                            We hadn't accounted for apostrophe in company name.
                            OLD CODE WAS: 
                                companyList.push({value: '<?= $name ?>', data: <?= $id ?>});
                        */
                        ?>
                        companyList.push({value: '<?= str_replace ("'", "\\'", $name)  ?>', data: <?= $id ?>});
                    <?php
                    }
                    ?>
                </script>
                <?php
                if ($companyId) {
                    ?>
                    <script>
                        $('#itemCount').html('<?= $itemCount ?>');                        
                    </script>    
                    <?php    
                }

                echo '</table>';
            ?>

        <?php
        /* 'expand-notes-dialog' DIV (before version 2020-2, just 'expanddialog'), 
            the DIV for the notes dialog. Absolutely arbitrary where this goes in the HTML BODY */ 
        ?>             
        <div id="expand-notes-dialog"></div>
        <?php /* code to implement expanded "Notes" for TAB 5 (aging summary - awaiting payment). */ ?> 
        <script type="text/javascript">
            $(function() {
                $( ".expand-notes-open" ).mouseenter(function() {
                    $( "#expand-notes-dialog" ).dialog({
                        position: { my: "center bottom", at: "center top", of: $(this) },
                        autoResize:true ,
                        open: function(event, ui) {
                            $(".ui-dialog-titlebar-close", ui.dialog | ui ).hide();
                            $(".ui-dialog-titlebar", ui.dialog | ui ).hide();
                        }
                    });    
                    
                    // var invoiceId = $(this).attr('id'); // reworked 2020-02-20 JM
                    var invoiceId = $(this).data('invoiceid');
                    var date = $(this).attr('name');
                    $("#expand-notes-dialog").dialog("open").html(
                        '<img src="/cust/<?= $customer->getShortName() ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">').dialog({height:'45', width:'auto'})
                        .load('/ajax/invoiceactivitynotes.php?invoiceId=' + escape(invoiceId), function(){
                                $('#expand-notes-dialog').dialog({height:'auto', width:'auto'});
                        });
                });
                // OLD CODE REMOVED 2019-10-24 JM
                //$( ".expandopen" ).mouseleave(function() {
                // BEGIN REPLACEMENT 2019-10-24 JM, make it easy to close the pop-up even if it covers the ."expand-notes-open" (before version
                //   2020-2, "expandopen") that triggered it.
                $( ".expand-notes-open, #expand-notes-dialog" ).mouseleave(function() {
                // END REPLACEMENT 2019-10-24 JM
                    $( "#expand-notes-dialog" ).dialog("close");
                });
            });
        </script>

        <?php 
        } else if ($tab == 6) {  
            /* =================================================
               ============= TAB 6 (cred recs) =================
               ================================================= */
               
           // BEGIN REPLACED 2020-02-05     
           /* ACTION $_REQUEST['act'] = 'delcriid'
               NOTE that this only works in conjunction with $_REQUEST['tab']=6
               INPUT $_REQUEST['creditRecordInvoiceId]               
               
               Deletes the row specified by $_REQUEST['creditRecordInvoiceId] from DB 
               table creditRecordInvoice and drops through to the normal handling of tab=6.
            */
            /*
            if ($act == 'delcriid') {
                $creditRecordInvoiceId = isset($_REQUEST['creditRecordInvoiceId']) ? intval($_REQUEST['creditRecordInvoiceId']) : 0;
                if (intval($creditRecordInvoiceId)) {
                    $query = " delete from " . DB__NEW_DATABASE . ".creditRecordInvoice ";
                    $query .= " where creditRecordInvoiceId = " . intval($creditRecordInvoiceId);
                    $db->query($query);
                }
            }
            */
            // END REPLACED 2020-02-05
        ?>
        <script>
            // BEGIN REPLACEMENT 2020-02-05 JM; replacement is client-side
            $(document).on("click", "button.reversePayment", function() {
                let $this = $(this);
                let creditRecordId = $this.data('creditrecordid');
                let invoiceId = $this.data('invoiceid');
                let amount= $this.data('amount');
                let $rvDialog = $('<div>Credit back $' + amount + ' to credit record ' + creditRecordId + 
                        ' from invoice ' + invoiceId + '.</div>').dialog(
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
            // END REPLACEMENT 2020-02-05 JM
        </script>
        <style>    
            table.scroll {
                border-spacing: 0;
            }
            
            table.scroll tbody,
            table.scroll thead { display: block; }
            
            thead tr th { 
                height: 30px;
                line-height: 30px;
            }
            
            table.scroll tbody {
                height: 450px;
                overflow-y: auto;
                overflow-x: hidden;
            }
            
            </style>
        <?php 
            // SELECT from DB table creditRecord, most recent first (though we will mess
            //  with this to bring those with NO credit date to the front).
            // Put this in an array, then re-sort so that all rows with an empty creditDate 
            //  come before all rows with a non-empty credit date. Later, as we write out the 
            //  table, we will assign class "zero-bal" (before 2020-02-04 this was "bal") 
            //  to any rows that have a zero balance and a non-zero creditRecordTypeId.
            // >>>00004 Also, assumes SSS only (single customer).
            
            // BEGIN ADDED 2020-02-04 JM
            // Introduced $monthsback because of our concern that otherwise this wouldn't scale well once DB table creditRecord grows big. 
            $monthsback = isset($_REQUEST['monthsback']) ? intval($_REQUEST['monthsback']) : 6;
            if ($monthsback < 1) {
                $monthsback = 1;
            }
            // END ADDED 2020-02-04 JM
            
            $query = "SELECT * ";
            $query .= "FROM " . DB__NEW_DATABASE . ".creditRecord ";
            $query .= "WHERE inserted > DATE_ADD(NOW(), INTERVAL -$monthsback MONTH) ";// added 2020-02-04 JM
            $query .= "ORDER BY creditDate DESC, creditRecordId DESC";
            
            $rows = array();
            
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            } // else >>>00002 ignores failure on DB query!
            
            $sorted = array();
            
            foreach ($rows as $row) {
                if (!strlen($row['creditDate'])) { // NOTE negation here: these are the ones with no creditDate
                    $sorted[] = $row;
                }
            }
            foreach ($rows as $row) {
                if (strlen($row['creditDate'])) {
                    $sorted[] = $row;
                }
            }
            $rows = $sorted;

            echo '<p><a href="deposits.php">Link to Deposit Summary Page</a></p>' . "\n";
            echo '<center>' . "\n";
                // Instructions
                echo '<table border="0" cellpadding="4" cellspacing="0" width="600">' . "\n";
                    echo '<tr><td>Changing the Record Type, Reference#, Amount, Credit Date, or Deposit Date updates immediately in database.<td></tr>' . "\n";
                    echo '<tr><td>Ditto for "Received From" and "Notes", but you must click outside the edit area to save to the database.<td></tr>' . "\n";
                    echo '<tr><td>Click the file name to view the image/pdf etc.</td></tr>' . "\n";
                    echo '<tr><td>Credit records with non-zero balance offer the option to apply a payment to an invoice. An updated list is then fetched from the database.</td></tr>' . "\n";
                echo '</table>' . "\n";
                
                // BEGIN DROPZONE MOVED FROM BELOW 2020-04-20 at Ron's request per http://bt.dev2.ssseng.com/view.php?id=131
                ?>
                <script src="/js/dropzone.js?v=1524508426"></script>
                <link rel="stylesheet" href="/cust/<?= $customer->getShortName() ?>/css/dropzone.css?v=1524508426" />;
                <script>
                {
                    // This script must run BEFORE dropzone uploadcredit is instantiated.
                    // Martin's code had that wrong (had it inside of a $().ready ) so I'm pretty sure
                    //  it never worked before version 2020-2.
                    // Also, I've greatly simplified this. It must have been copy-pasted from somewhere, 
                    //  and had a bunch of variables and handlers that were not relevant here. - JM
                    // See https://www.dropzonejs.com/#configuration-options 
                    window.Dropzone.options.uploadcredit = {    
                        uploadMultiple:false,
                        maxFiles:1,
                        autoProcessQueue:true,
                        maxFilesize: 2, // MB
                        clickable: false,
                        addRemoveLinks : true,
                        acceptedFiles : "application/pdf,.pdf,.png,.jpg,.jpeg",
                        init: function() {
                            // >>>00001 I'm not at all sure what is up with 'bind' here; I've left it as it was - JM 2020-02-20
                            this.on("error", function(file, errorMessage) {
                                alert(errorMessage); // added 2020-02-20 JM, >>>00002 maybe alert is not exactly what we should do.
                                setTimeout(this.removeFile.bind(this, file), 3000);
                            }.bind(this)
                            );
                            
                            this.on('complete', function () {
                                setTimeout(function(){ window.location.reload(false); }, 2000);
                            }.bind(this)
                            );
                            
                            this.on("success", function(file) {
                                setTimeout(this.removeFile.bind(this, file), 1000);
                            }.bind(this)
                            );
                        }
                    };
                }
                </script>
                <div class="drop-area">
                    <form id="uploadcredit" class="dropzone" action="credrec_upload.php">
                        <h2 class="heading"></h2>
                        <div id="dropzone">
                        </div>
                    </form>
                </div>
                <?php
                // END DROPZONE MOVED FROM BELOW 2020-04-20 at Ron's request per http://bt.dev2.ssseng.com/view.php?id=131
                
                // Separate table just to contain iframe "imageframe" (see handling 
                //  of "Image" column below to make sense of this) 
                echo '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
                    echo '<tr>';
                        echo '<td width="100%" height="250" colspan="8">';
                            echo '<iframe name="imageframe" id="imageframe" style="display: block; width: 100%; height: 100%; border: none;"></iframe>';
                        echo '</td>';
                    echo '</tr>' . "\n";
                echo '</table>' . "\n";
            echo '</center>' . "\n";
                // Button labeled "Toggle View" does a show/hide on any HTML elements with class "zero-bal" (before 2020-02-04 this was "bal")
                //  Initially, we display only the rows that are *not* assigned class "zero-bal" 
                // >>>00006 JM: Here and elsewhere we could probably come up with a lot clearer name 
                //  than "Toggle View", have the label change as it toggles, say what it will do.
                //                
            ?>
                <br />
                <button class="btn btn-secondary btn-sm mr-auto ml-auto mt-2 mb-2" id="clickme">Toggle View</button><?php /* BEGIN ADDED 2020-02-04 JM */ ?>                
                &nbsp;&nbsp;&nbsp;<label for="monthsback">Show&nbsp;credit&nbsp;records&nbsp;for&nbsp;last&nbsp;</label><select class="form-control form-control-sm" id="monthsback" name="monthsback">
                <option value="">----</option>
                <option value="1" <?php if ($monthsback==1) {echo ' selected';}?> >1 month</option>
                <option value="2" <?php if ($monthsback==2) {echo ' selected';}?> >2 months</option>
                <option value="3" <?php if ($monthsback==3) {echo ' selected';}?> >3 months</option>
                <option value="6" <?php if ($monthsback==6) {echo ' selected';}?> >6 months</option>
                <option value="12" <?php if ($monthsback==12) {echo ' selected';}?> >1 year</option>
                <option value="24" <?php if ($monthsback==24) {echo ' selected';}?> >2 years</option>
                <option value="36" <?php if ($monthsback==36) {echo ' selected';}?> >3 years</option>
                <option value="10000" <?php if ($monthsback==10000) {echo ' selected';}?> >All dates</option>
                </select>&nbsp;<button class="btn btn-secondary btn-sm mr-auto ml-auto" id="refresh-tab-6">Go</button>
                <?php /* END ADDED 2020-02-04 JM */ ?>
                <br />

                <script>
                $( "#clickme" ).click(function() {
                    // BEGIN REPLACED 2020-02-04 JM                        
                    // $( ".zero-bal" ).toggle( "fast", function() {
                    // });
                    // END REPLACED 2020-02-04 JM
                    // BEGIN REPLACEMENT 2020-02-04 JM
                    $('body').toggleClass('show-zero-bal');
                    // END REPLACEMENT 2020-02-04 JM
                });
                <?php /* BEGIN ADDED 2020-02-04 JM, amended 2020-06-18 to bring in companyId */ ?>
                $('#refresh-tab-6').click(function() {
                    window.location = '?tab=6&monthsback=' + $('#monthsback').val() + <?= ($companyId ? "'&companyId=$companyId'" : "''") ?>;
                });
                <?php /* END ADDED 2020-02-04 JM */ ?>
                </script>
            <?php
                echo '<table class="table" style="font-size: 80%;" border="1" cellpadding="2" cellspacing="0" width="100%">';
                    echo '<thead>';
                        echo '<tr class="sticky-header">';
                            echo '<th>Record Type</th>';
                            echo '<th>Reference#</th>';
                            echo '<th>Amt</th>';
                            echo '<th>Cred Date</th>';
                            echo '<th>Dep. Date</th>';
                            echo '<th>Received From</th>';
                            echo '<th>Image</th>';
                            echo '<th>View CR / Pay Invoice</th>';  // changed from Inv. ID\'s 2020-02-10 
                            echo '<th>Notes</th>';
                            echo '<th>Delete</th>';
                        echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    $types = CreditRecord::creditRecordTypes();
// BEGIN OUTDENT: for each row in the body of TAB 6
foreach ($rows as $row) {
    $creditDate = date_parse($row['creditDate']);
    $creditDateField = '';
    if (is_array($creditDate)) {
        if (isset($creditDate['year']) && isset($creditDate['day']) && isset($creditDate['month'])) {
            $creditDateField = intval($creditDate['month']) . '/' . intval($creditDate['day']) . '/' . intval($creditDate['year']);
            if ($creditDateField == '0/0/0') {
                $creditDateField = '';
            }
        }
    }
    
    $depositDate = date_parse($row['depositDate']);
    $depositDateField = '';                    
    if (is_array($depositDate)) {
        if (isset($depositDate['year']) && isset($depositDate['day']) && isset($depositDate['month'])) {
            $depositDateField = intval($depositDate['month']) . '/' . intval($depositDate['day']) . '/' . intval($depositDate['year']);
            if ($depositDateField == '0/0/0') {
                $depositDateField = '';
            }
        }
    }

    // [Martin comment on next line]
    // creditRecordTypeId | arrivalTime | referenceNumber | amount | creditDate | receivedFrom | personId | inserted            | fileName |
    
    $credrec = new CreditRecord($row['creditRecordId']);
    $bal = $credrec->getBalance();
    
    $class = '';
    if (($bal == 0) && (intval($credrec->getCreditRecordTypeId()))) {
        // We will assign class "zero-bal" (before 2020-02-04 this was "bal") to any 
        //  rows that have a zero balance and a non-zero creditRecordTypeId.
        // Initially, we display only the rows that are not assigned class "zero-bal". 
        $class = ' class="zero-bal" ';
    }
    echo '<tr ' . $class . '>';
        /* "Record Type": creditRecord type, in a dropdown; takes immediate action in the DB.
        
           This column functions as a form all on its own.
            * The form name is based on the creditRecordId; ditto for the ID. So if the creditRecordId
              is (for example) 2, then the form name is "type_2", as is the ID
            * Hidden input passes a variable with name "id" and the creditRecordId
            * Then comes the element that is the nub of the matter: HTML SELECT (dropdown)
              offering creditRecordTypes. First row has blank value and says "-- choose type --"; 
              other rows each have creditRecordTypeId as value and display corresponding name. 
              On change, calls local function typeForm(creditRecordId), which in turn POSTs to 
              ./ajax/cred_type.php, passing the content of this form, serialized. 
              NOTE that what we pass to function typeForm is sufficient information to identify 
              this form (given that it is closely tied to forms with IDs that begin with "type").
          */
        echo '<td><form name="type_' . intval($row['creditRecordId']) . '" id="type_' . intval($row['creditRecordId']) . '"><input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
            echo '<select name="value" class="form-control form-control-sm" style="width:auto!important" onChange="typeForm(' . intval($row['creditRecordId']) . ')"><option value="">-- choose type --</option>';
            foreach ($types as $tkey => $type) {
                $selected = ($tkey == $row['creditRecordTypeId']) ? ' selected ' : '';
                echo '<option value="' . $tkey . '" ' . $selected . '>' . $type['name']  . '</option>';
            }
        echo '</select></form></td>';
        
        /* "Reference": creditRecord reference number.
           
            Double-click here pops up a "prompt" dialog to get the new text; on completion, 
            POSTs that to ./ajax/cred_refnum.php; alerts on error, otherwise updates the text per user edit.
        */
        // BEGIN OLD CODE REWORKED 2020-02-10 to make this editable in place
        /*
            $ref = $row['referenceNumber'];
            $ref = trim($ref);
            if (!strlen($ref)) {
                $ref = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            // 2019-10-16 JM: introduce HTML data attributes, get away from ref_editable and amt_editable having
            //  same HTML ID, which is illegal HTML. See http://bt.dev2.ssseng.com/view.php?id=35.
            // OLD CODE removed 2019-10-16 JM: 
            //echo '<td class="ref_editable" id="' . intval($row['creditRecordId']) . '">' . $ref . '</td>';
            // BEGIN REPLACEMENT
            echo '<td class="ref_editable" data-credit-record-id="' . intval($row['creditRecordId']) . '">' . $ref . '</td>';
            // END REPLACEMENT
            // END OLD CODE REWORKED 2020-02-10
        */
        // BEGIN NEW CODE 2020-02-10
            $ref = $row['referenceNumber'];
            $ref = trim($ref);
            echo '<td><input class="ref_editable form-control form-control-sm" data-credit-record-id="' . intval($row['creditRecordId']) . '" value="' . $ref . '" maxlength="64"></td>';
        // END NEW CODE 2020-02-10
        
        /* "Amt": amount (dollars, with 2 digits past the decimal, as usual).
           
           Double-click here pops up a "prompt" dialog to get the new text; 
           on completion, POSTs that to ./ajax/cred_amount.php; 
           alerts on error, otherwise updates the text per user edit.
        */    
        // BEGIN OLD CODE REWORKED 2020-02-10 to make this editable in place
        /*
        $amt = $row['amount'];
        $amt = trim($amt);
        if (!strlen($amt)) {
            $amt = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        // 2019-10-16 JM: introduce HTML data attributes, as discussed above
        // OLD CODE removed 2019-10-16 JM: 
        //echo '<td class="amt_editable" id="' . intval($row['creditRecordId']) . '">' . $amt . '</td>';
        // BEGIN REPLACEMENT; corrected 2020-02-04
        echo '<td class="amt_editable" data-credit-record-id="' . intval($row['creditRecordId']) . '">' . $amt . '</td>';
        // END REPLACEMENT
        // END OLD CODE REWORKED 2020-02-10
        */
        // BEGIN NEW CODE 2020-02-10
        $amt = $row['amount'];
        $amt = trim($amt);
        echo '<td><input class="amt_editable form-control form-control-sm" data-credit-record-id="' . intval($row['creditRecordId']) . '" value="' . $amt . '" size="8" maxlength="20"></td>';
        // END NEW CODE 2020-02-10
        
        /* "Cred Date": this column functions as a form in its own right.
        
            * The form name is based on the creditRecordId; ditto for the ID. So if the creditRecordId
              is (for example) 2, then the form name is "form_date_2", as is the ID
            * Hidden input passes a variable with name "id" and the creditRecordId
            * Then comes the element that is the nub of the matter: "datepicker" text input 
              displays month/day/year, no leading zeroes; if no value: "0/0/0".
              Click here pops up a datepicker to get the new date. On completion, $(".datepicker").datepicker handler 
              POSTs to ./ajax/cred_date.php, passing the content of this form, serialized. 
              NOTE that we reconstruct identifier for this form from text input ID.
            */
        echo '<td>';
            echo '<form name="form_date_' . intval($row['creditRecordId']) . '" id="form_date_' . intval($row['creditRecordId']) . '"><input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
                echo '<input type="text" name="value" class="datepicker form-control form-control-sm" id="date_' . intval($row['creditRecordId']) . '" value="' . htmlspecialchars($creditDateField) . '" size="12">';
            echo '</form>';
        echo '</td>';
        
        /* "Dep. Date": deposit date, highly analogous to preceding "Cred Date" */
        echo '<td>';
            echo '<form name="form_depdate_' . intval($row['creditRecordId']) . '" id="form_depdate_' . intval($row['creditRecordId']) . '"><input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
                echo '<input type="text" name="value" class="datepicker form-control form-control-sm" id="depdate_' . intval($row['creditRecordId']) . '" value="' . htmlspecialchars($depositDateField) . '" size="12">';
            echo '</form>';
        echo '</td>';
        
        /* BEGIN REPLACED 2020-02-10 JM
        // "Received From": yet another column that is a form in its own right,
        //   in this case with an overt button "set rec from" to submit the form after editing the TEXTAREA.                           
        //   When that is clicked, calls local function receivedFromForm(creditRecordId), which in turn 
        //   POSTs to ./ajax/cred_recfrom.php, passing the content of this form, serialized. 
        //   NOTE that what we pass to function receivedFromForm is sufficient information to identify 
        //   this form (given that it knows we are concerned here with "received from").
           
        //   00031 The button is outside the form, which I (JM) guess must work because of the argument
        //   it passes to receivedFromForm or it would have been noticed by now (2019-03) but that is really 
        //   poor practice. The button should be moved inside the form. 
        echo '<td>';
            echo '<form name="recfrom_' . intval($row['creditRecordId']) . '" id="recfrom_' . intval($row['creditRecordId']) . '">' .
                '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
            echo '<textarea name="value">' . htmlspecialchars($row['receivedFrom']) . '</textarea>';
            echo '</form><input type="button" onClick="receivedFromForm(' . intval($row['creditRecordId']) . ')" value="set rec from">';
        echo '</td>';
        // END REPLACED 2020-02-10 JM
        */
        // BEGIN REPLACEMENT 2020-02-10 JM
        echo '<td>';
            echo '<form name="recfrom_' . intval($row['creditRecordId']) . '" id="recfrom_' . intval($row['creditRecordId']) . '">' .
                '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
            echo '<textarea class="form-control form-control-sm" name="value" id="recivedFrom_' . intval($row['creditRecordId']) . '" onChange="receivedFromForm(' . intval($row['creditRecordId']) . ')">' . htmlspecialchars($row['receivedFrom']) . '</textarea>';
            echo '</form>';
        echo '</td>';
        // END REPLACEMENT 2020-02-10 JM
        
        /* "Image":
           * if fileName is not of the form FOO.ext, leave this cell blank
           * if fileName has suffix 'png', 'jpg', 'jpeg', or 'gif':
              * link to open credrec_getuploadfile.php?f=fileName in imageframe. 
                If balance is 0, link displays 'click for image'; 
                otherwise link displays largish (currently 163x122 px) image 
                from credrec_getuploadfile.php?f=fileName. 
                (Naturally, 'click for image' only does something useful 
                if you have these images on your system, which generally won't be the case for dev.)
            * Otherwise, same link to open credrec_getuploadfile.php?f=fileName in imageframe, but just displays "click".
            
           It is also possible to upload, but not here. Martin says, 
           "if you look at the page in a browser it says at the bottom 
               'drag file here to upload'. That dropzone is the upload mechanism. */
               
        $ok = array('png','jpg','jpeg','gif');
        $fn = $row['fileName'];
        $parts = explode(".", $fn);
        if (count($parts) > 1) {
            $ext = strtolower(end($parts));
            if (in_array($ext, $ok)) {
                echo '<td><a target="imageframe" href="credrec_getuploadfile.php?f=' . rawurlencode($row['fileName']) . '">';
                    if ($bal == 0) {
                        echo 'click for image';
                    } else {
                        echo '<img src="credrec_getuploadfile.php?f=' . rawurlencode($row['fileName']) . '" width="163" height="122">';
                    }
                echo '</a></td>';
            } else {
                echo '<td>[<a target="imageframe" href="credrec_getuploadfile.php?f=' . rawurlencode($row['fileName']) . '">click</a>]</td>';
            }
        } else {
            echo '<td>&nbsp;</td>';
        }
        
        /* View CR / Pay Invoice;  // changed from Inv. ID\'s 2020-02-10 */

        // JM 2020-02-10: This has changed so much recently I've stopped trying to mark all the individual changes; instead
        //  I'm indicating the old (pre-2020-02 code); then the new code follows.
        // BEGIN OLD CODE --------
        /*
        // At start of cell, sets up a DIV with id="cred_invoices_creditRecordId, but closes that without 
        // any content. So everything else in the cell, as initially loaded, is *outside* the DIV.
        echo '<td><div id="cred_invoices_' . intval($row['creditRecordId']) . '"></div>';
            // Queries DB table invoicePayment for all rows matching this creditRecordId. 
            // Displays the returned invoices, one to a line. For each:
            //   * Display invoiceId
            //   * Display "[del]", which links to self-submitting GET-method delcriid code (to delete)
            $invoices = array();
            $query = " select * from " . DB__NEW_DATABASE . ".creditRecordInvoice ";
            $query .= " where creditRecordId = " . intval($row['creditRecordId']);    
            if ($result = $db->query($query)) { // Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0){
                    while ($r = $result->fetch_assoc()){
                        $invoices[] = $r; // [Martin comment:] $r['invoiceId'];
                    }
                }
            }
            
            foreach ($invoices as $invoice) {
                echo $invoice['invoiceId'] . '&nbsp;[<a href="multi.php?act=delcriid&tab=' . intval($tab) . 
                    '&creditRecordInvoiceId=' . intval($invoice['creditRecordInvoiceId']) . '">del</a>]<br>';
            }

            //  Then comes a form:
            //     * name="invoice_NN" where NN=creditRecordId, id same as name
            //     * (hidden input) id=creditRecordId
            //     * Text input, initially blank.
            //     * button labeled "add inv". When clicked calls local function invoiceForm(creditRecordId), 
            //       which in turn POSTs to ./ajax/cred_invoice.php, passing the content of this form, serialized. 
            //       (NOTE that what we pass to function invoice Form is sufficient information to identify this form,
            //       given that it knows we are concerned here with invoice IDs.)
            //        On return, that calls local function get_invoices, passing along the creditRecordId.
            //         * get_invoices sets the cell content to the ajax_loader.gif and POSTs the creditRecordId 
            //           to ./ajax/cred_getinvoices.php. On success, it replaces the cell content with a table 
            //           showing the invoice IDs, one to a row (note a different layout than the initial display, 
            //           which uses straight text and BR elements 
            //           00026 and doesn't match how the DIV above is initially used, should be reworked more consistently).
            //       00031 The button is outside the form, which I (JM) guess must work because of the argument
            //       it passes to invoiceForm or it would have been noticed by now (2019-03) but that is really 
            //       poor practice. The button should be moved inside the form. 
            echo '<form name="invoice_' . intval($row['creditRecordId']) . '" id="invoice_' . intval($row['creditRecordId']) . '">' .
                 '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
                 echo '<input type="text" name="value" size="10" maxlength="10">';
                 echo '</form><input type="button" onClick="invoiceForm(' . intval($row['creditRecordId']) . ')" value="add inv">';
        echo '</td>'. "\n";
        */
        // END OLD CODE --------
        
        // BEGIN NEW CODE 2020-02 -------
        
        // Put content of this cell in a DIV with id="cred_invoices_creditRecordId. >>>00032 Eventually, this
        // could let us rebuild this cell without having to reload the page (not yet implemented).
        echo '<td><div id="cred_invoices_' . intval($row['creditRecordId']) . '">';
            // Queries DB table invoicePayment for all rows matching this creditRecordId. 
            // Displays the returned invoice payments, one to a line. For each:
            //   * Display invoiceId and amount
            //   * Provide a link to reverse the credit and reload the page.
            $payments = array();
            $query = "SELECT invoiceId, amount, inserted FROM " . DB__NEW_DATABASE . ".invoicePayment ";
            $query .= "WHERE creditRecordId = " . intval($row['creditRecordId']);
            
            $result = $db->query($query);
            if ($result) {
                if ($result->num_rows > 0){
                    while ($r = $result->fetch_assoc()){
                        $payments[] = $r;
                    }
                }
            } // else >>>00002 ignores failure on DB query!
            
            foreach ($payments as $payment) {
                echo $payment['invoiceId'] . ': ';
                if ($payment['amount'] >= 0) {
                    echo  '$' . $payment['amount'];
                    $matchPositive = 0;
                    $matchNegative = 0;
                    foreach ($payments as $payment2) {
                        if ($payment2['inserted'] > $payment['inserted']) { // It's a given that the creditRecordIds match, since that was in our WHERE clause
                            if ($payment['amount'] == $payment2['amount']) {
                                ++$matchPositive;
                            } else if ($payment['amount'] == -$payment2['amount']) {
                                ++$matchNegative;
                            }
                        }
                    }
                    // Typical case is that both $matchPositive and $matchNegative are zero, but this should prevent offering to reverse what is already reversed.
                    if ($matchPositive >= $matchNegative) { 
                        echo '<button class="reversePayment btn btn-secondary btn-sm mr-auto ml-2 mt-2 mb-2" ' .
                            'data-creditrecordid="' . intval($row['creditRecordId']) . '" ' .
                            'data-invoiceid="' . intval($payment['invoiceId']) . '" ' .
                            'data-amount="' . intval($payment['amount']) . '">' .
                            'Reverse</button>';
                    }
                    echo '<br>';
                } else {
                    echo '-$' . number_format(abs($payment['amount']), 2) . '<br>';
                }
            }

            //  Then comes a link disguised as a button:
            //  Allows you to view this credit record or (if it has a non-zero balance) make a payment 
            $label = $bal ? 'View '. $row['creditRecordId'] . '/ Make payment' : 'View CR '. $row['creditRecordId'];
            echo '<a data-fancybox-type="iframe" class="fancyboxIframeWide" ' . 
                 'href="/fb/creditrecord.php?creditRecordId=' . intval($row['creditRecordId']) . '"><button class="btn btn-secondary btn-sm mr-auto ml-auto mt-2 mb-2">' . $label . '</button></a>';
        echo '</div></td>'. "\n";
        // END NEW CODE 2020-02 -------
        
        /* Notes: this column functions as a form in its own right.
           When the button labeled "set notes" is clicked, calls 
           local function notesForm(creditRecordId), which in turn 
           POSTs to ./ajax/cred_notes.php, passing the content of this form, serialized. 
           NOTE that what we pass to function notesForm is sufficient information to 
           identify this form, given that it knows we are concerned here with notes.
        */
        /* BEGIN REPLACED 2020-02-10 JM
        echo '<td><form name="notes_' . intval($row['creditRecordId']) . '" id="notes_' . intval($row['creditRecordId']) . '">' . 
                 '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
            echo '<textarea name="value">' . htmlspecialchars($row['notes']) . '</textarea>';
            echo '</form><input type="button" onClick="notesForm(' . intval($row['creditRecordId']) . ')" value="set notes">';
        echo '</td>';
        // END REPLACED 2020-02-10 JM
        */
        // BEGIN REPLACEMENT 2020-02-10 JM
        echo '<td>';
            echo '<form name="notes_' . intval($row['creditRecordId']) . '" id="notes_' . intval($row['creditRecordId']) . '">' .
                '<input type="hidden" name="id" value="' . intval($row['creditRecordId']) . '">';
            echo '<textarea name="value" class="form-control form-control-sm" id="notesTextarea_' . intval($row['creditRecordId']) . '" onChange="notesForm(' . intval($row['creditRecordId']) . ')">' . htmlspecialchars($row['notes']) . '</textarea>';
            echo '</form>';
        echo '</td>';
        echo '<td>';
        echo  '<a class="btn btn-secondary btn-sm" type="button" id="linkDeleteCreditRecord'. intval($row['creditRecordId']) .'"  
        href="multi.php?tab=6&act='."deleteCreditRecord".'&creditRecordId='. intval($row['creditRecordId']) .'">Delete</a>';   
        echo '</td>';
        // END REPLACEMENT 2020-02-10 JM
    echo '</tr>' . "\n";
}
// END OUTDENT: for each row in the body of TAB 6
                        echo '</tbody>';
                echo '</table>';
                
                // Build the dropzone
                ?>
                <?php /* BEGIN: DROPZONE MOVED UP ABOVE 2020-04-20 at Ron's request per http://bt.dev2.ssseng.com/view.php?id=131
                <script src="/js/dropzone.js?v=1524508426"></script>
                <link rel="stylesheet" href="/cust/<?php echo $customer->getShortName(); ?>/css/dropzone.css?v=1524508426" />
                <script>
                {
                    // This script must run BEFORE dropzone uploadcredit is instantiated.
                    // Martin's code had that wrong (had it inside of a $().ready ) so I'm pretty sure
                    //  it never worked before version 2020-2.
                    // Also, I've greatly simplified this. It must have been copy-pasted from somewhere, 
                    //  and had a bunch of variables and handlers that were not relevant here. - JM
                    // See https://www.dropzonejs.com/#configuration-options 
                    window.Dropzone.options.uploadcredit = {    
                        uploadMultiple:false,
                        maxFiles:1,
                        autoProcessQueue:true,
                        maxFilesize: 2, // MB
                        clickable: false,
                        addRemoveLinks : true,
                        acceptedFiles : "application/pdf,.pdf,.png,.jpg,.jpeg",
                        init: function() {
                            // >>>00001 I'm not at all sure what is up with 'bind' here; I've left it as it was - JM 2020-02-20
                            this.on("error", function(file, errorMessage) {
                                alert(errorMessage); // added 2020-02-20 JM, >>>00002 maybe alert is not exactly what we should do.
                                setTimeout(this.removeFile.bind(this, file), 3000);
                            }.bind(this)
                            );
                            
                            this.on('complete', function () {
                                setTimeout(function(){ window.location.reload(false); }, 2000);
                            }.bind(this)
                            );
                            
                            this.on("success", function(file) {
                                setTimeout(this.removeFile.bind(this, file), 1000);
                            }.bind(this)
                            );
                        }
                    };
                }
                </script>
                <div class="drop-area">
                    <form id="uploadcredit" class="dropzone" action="credrec_upload.php">
                        <h2 class="heading"></h2>
                        <div id="dropzone">
                        </div>
                    </form>
                </div>
                // END: DROPZONE MOVED UP ABOVE 2020-04-20 at Ron's request per http://bt.dev2.ssseng.com/view.php?id=131
                */
                
/* Show/hide rows that  have a zero balance and a non-zero creditRecordTypeId.
   This is part of the TAB 6 logic. */
?>
<script>
// BEGIN REMOVED 2020-02-04 JM                        
//$( ".zero-bal" ).toggle( "fast", function() {
//    $table = $('table.scroll');
//    $bodyCells = $table.find('tbody tr:first').children();
//    
//    var colWidth;
//
//    <?php /* JM 2019-03: a slightly tricky mechanism follows: defines a handler and
//             then immediately calls it in the same statement.
//             00026: but is this done right? Won't this add another handler
//             every time this toggle is called?*/ ?>
//    // [Martin comment:] Adjust the width of thead cells when window resizes
//    $(window).resize(function() {
//        // [Martin comment:] Get the tbody columns width array
//        colWidth = $bodyCells.map(function() {
//            return $(this).width();
//        }).get();
//        
//        // [Martin comment:] Set the width of thead columns
//        $table.find('thead tr').children().each(function(i, v) {
//            $(v).width(colWidth[i]);
//        });
//    }).resize(); // [Martin comment:] Trigger resize handler
//});
// END REMOVED 2020-02-04 JM
{ 
    // >>>00001 I (JM 2020-02-19) don't understand why the following is here at all, and whether it is useful
    
    let $table = $('table.scroll');
    let $bodyCells = $table.find('tbody tr:first').children();
    let colWidth;
     
    // [Martin comment:] Adjust the width of thead cells when window resizes
    $(window).resize(function() {
        // [Martin comment:] Get the tbody columns width array    
        colWidth = $bodyCells.map(function() {
                return $(this).width();
        }).get();
        
        // [Martin comment:] Set the width of thead columns
        $table.find('thead tr').children().each(function(i, v) {
                $(v).width(colWidth[i]);
        });
    }).resize(); // [Martin comment:] Trigger resize handler
                 // >>>00006 JM: It would make more sense to call this on document ready
                 //  than to trigger it as the page loads!
}

<?php
// Handlers for date changes in TAB 6 rows (credit records: credit date & deposit date). 
?>
$(function() {
    var dateForm = function(dpid) {
        // >>>00002 Not checking return
        $.post('./ajax/cred_date.php', $('#form_' + dpid).serialize());
    }
    
    var depForm = function(dpid) {
        // >>>00002 Not checking return
        $.post('./ajax/deposit_date.php', $('#form_' + dpid).serialize());
    }
    
    $(".datepicker").datepicker( {
        onSelect: function() {
            if (this.id.indexOf("depdate") !== -1) {
                depForm(this.id);
            } else {
                dateForm(this.id);
            }
        }
    });
});

<?php /*
INPUT creditRecordId
Makes AJAX call to get all invoices for a particular creditRecord.
Updates cell with HTML ID cred_invoices_NN, where NN is creditRecordId.
On success, it replaces the cell content with a table showing the invoice IDs, 
one to a row (note a different layout than the initial display, which uses straight text and BR elements). 
*/ 
/* BEGIN REMOVED 2020-02-06 JM
var getInvoices = function(creditRecordId) {
    var cell = document.getElementById("cred_invoices_" + escape(creditRecordId));
    
    // Temporarily mark cell as "loading".
    cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
    
    var formData = "id=" + escape(creditRecordId); // Simulated HTML query string 
    $.ajax({
        url: './ajax/cred_getinvoices.php',
        data:formData,
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { // [T000016] 
                    if (data['rows']) {
                        var html = '';
                        html += '<table>';
                        for (var i = 0; i < data['rows'].length; i++){
                            html += '<tr>';
                            html += '<td>';
                            html += data['rows'][i];
                            html += '</td>';
                            html += '</tr>';
                        }
                        html += '</table>';                        
                        cell.innerHTML = html;
                    }
                } else {
                    alert('error not success');
                }
            } else {
                alert('error no status');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
} // END function getInvoices
// END REMOVED 2020-02-06 JM

<?php /*
INPUT creditRecordId
POST HTML form with ID invoice_NN, where NN is creditRecordId.
On success, it calls getInvoices to update cell content for cred_invoices_NN. 
*/ 
/* BEGIN REMOVED 2020-02-06 JM
var invoiceForm = function(creditRecordId) {
    // BEGIN BROKEN 2020-02-05, needs work if we want to update page rather than refresh
    $.post( "./ajax/cred_invoice.php", $('#invoice_' + creditRecordId).serialize())
        .done(function( data ) {
            getInvoices(creditRecordId);
        }
    );
}
// END REMOVED 2020-02-06 JM
*/

/*
INPUT creditRecordId
POST HTML form with ID notes_NN, where NN is creditRecordId. 
*/ ?>
var notesForm = function(creditRecordId) {
    $.post('./ajax/cred_notes.php', $('#notes_' + creditRecordId).serialize())
}

<?php /*
INPUT creditRecordId
POST HTML form with ID recfrom_NN, where NN is creditRecordId. 
*/ ?>
var receivedFromForm = function(creditRecordId) {
    $.post('./ajax/cred_recfrom.php', $('#recfrom_' + creditRecordId).serialize());
}

<?php /*
INPUT creditRecordId
POST HTML form with ID type_NN, where NN is creditRecordId. 
*/ ?>
var typeForm = function(creditRecordId){
    $.post('./ajax/cred_type.php', $('#type_' + creditRecordId).serialize())
}

<?php /* Always scrolls to top on document ready. >>>00001 not always a great policy, if someone has started typing while the document
is still being loaded; we may want to revisit this. */ ?>
$(document).ready(function(){
    $(this).scrollTop(0);
});

<?php /*
Change handler for "Reference" column. Uploads via AJAX POST.
*/ ?>
/* BEGIN REMOVED 2020-02-10 JM
$(function () {
    $("td.ref_editable").dblclick(function () {
        var OriginalContent = $(this).text();
        var inputNewText = prompt("Enter new content for refnum:", OriginalContent);
        
        if (inputNewText!=null) {
            $.ajax({
                url: './ajax/cred_refnum.php',
                // 2019-10-16 JM: introduce HTML data attributes, as discussed above
                // OLD CODE removed 2019-10-15 JM: 
                //data: { id: $(this).attr('id'), value: inputNewText },
                // BEGIN REPLACEMENT
                data: { id: $(this).attr('data-credit-record-id'), value: inputNewText },
                // END REPLACEMENT                
                async:false,
                type:'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            $(this).text(inputNewText)
                        } else {
                            alert('error not success');
                        }
                    } else {
                        alert('error no status');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error');
                }
            });
        }
    });
});
// END REMOVED 2020-02-10 JM
*/
// BEGIN NEW CODE 2020-02-10 JM
$(function () {
    $("input.ref_editable").change(function () {
        let $this = $(this);
        $.ajax({
            url: './ajax/cred_refnum.php',
            data: { id: $this.attr('data-credit-record-id'), value: $this.val() },
            async: true,
            type: 'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        // Do nothing, it takes care of itself.
                    } else {
                        alert('error not success updating refnum'); // >>>00037 should instrument /ajax/cred_refnum.php to return a usable error message
                    }
                } else {
                    alert('error no status updating refnum');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/cred_refnum.php');
            }
        });
    });
});
// END NEW CODE 2020-02-10 JM

<?php /*
Change handler for "Amt" (amount) column.
Prompts user to edit, then uploads via AJAX POST.
*/ ?>
/* BEGIN REMOVED 2020-02-10 JM
$(function () {
    $("td.amt_editable").dblclick(function () {
        var OriginalContent = $(this).text();
        var inputNewText = prompt("Enter new content for amount:", OriginalContent);
        if (inputNewText!=null) {
            $.ajax({
                url: './ajax/cred_amount.php',
                // 2019-10-16 JM: introduce HTML data attributes, as discussed above
                // OLD CODE removed 2019-10-15 JM: 
                //data: { id: $(this).attr('id'), value: inputNewText },
                // BEGIN REPLACEMENT
                data: { id: $(this).attr('data-credit-record-id'), value: inputNewText },
                // END REPLACEMENT                
                async:false,
                type:'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            $(this).text(inputNewText)
                        } else {
                            alert('error not success');
                        }
                    } else {
                        alert('error no status');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error');
                }
            });
        }
    });
});
// END REMOVED 2020-02-10 JM
*/
// BEGIN NEW CODE 2020-02-10 JM
$(function () {
    $("input.amt_editable").change(function () {
        let $this = $(this);
        $.ajax({
            url: './ajax/cred_amount.php',
            data: { id: $this.attr('data-credit-record-id'), value: $this.val() },
            async: true,
            type: 'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        // Do nothing, it takes care of itself.
                    } else {
                        alert('error not success updating amount'); // >>>00037 should instrument /ajax/cred_amount.php to return a usable error message
                    }
                } else {
                    alert('error no status updating amount');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to ajax/cred_amount.php');
            }
        });
    });
});
// END NEW CODE 2020-02-10 JM
</script>
            <?php 
        } else if ($tab == 7) {
            // >>>00002 Probably should give an error message: this case is handled by creditrecord.php, not here
        } else if ($tab == 8) {
            /* =================================================
               ============= TAB 8 (cred memos) ================
               ================================================= */
        
            $memos = array();
            $query = " select * From " . DB__NEW_DATABASE . ".creditMemo order by creditMemoId ";
            
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $memos[] = $row;
                }
            } // else >>>00002 ignores failure on DB query!
            
            echo '<table border="0" cellpadding="3" cellspacing="2" width="100%">';
            echo '<thead>'; // thead, tbody added to this table 2020-02-19 JM
                echo '<tr class="sticky-header">';
                    echo '<th>creditMemoId</th>';
                    echo '<th>creditMemoType</th>';
                    echo '<th>Id</th>';
                    echo '<th>Company</th>';
                    echo '<th>amount</th>';
                    echo '<th>personId</th>';
                    echo '<th>inserted</th>';
                echo '</tr>';
            echo '</thead>';
            
            echo '<tbody>';
            foreach ($memos as $memo) {
                echo '<tr>';
                    // "creditMemoId"
                    echo '<td>' . $memo['creditMemoId'] . '</td>';
                    
                    // "creditMemoType" ($credRecTypes comes from /inc/config.php; value is text name of type)
                    // As of 2019-03, this is just "Inbound" or "Outbound"
                    echo '<td>' . $credRecTypes[$memo['creditMemoTypeId']] . '</td>';
                    
                    if ($memo['creditMemoTypeId'] == CRED_MEMO_TYPE_IN) {
                        // "Id"
                        //   Martin remarked 2018-10: the id of a creditRecord against which this credit memo 
                        //    is an allocation of funds, but might (for some future creditMemoType) refer to 
                        //    something else, such as an invoice.
                        echo '<td>Cred-Record:' . $memo['id'] . '</td>';
                        
                        // "Company": company name, linked to open page for appropriate company in a new browser tab 
                        echo '<td>';
                        $c = new Company($memo['companyId']);
                        if (intval($c->getCompanyId())){
                            $n = '';
                            $n = $c->getName();
                            $n = trim($n);
                            if (!strlen($n)){
                                $n = "___"; // No company name available, use 3 underscores     
                            }
                            echo '<a target="_blank" href="' . $c->buildLink() . '">' . $n . '</a>';
                        }
                        echo '</td>';
                    } else if ($memo['creditMemoTypeId'] == CRED_MEMO_TYPE_OUT) {
                        // "Id", "Company"
                        // >>>00032 >>>00026: CODE HERE IS JUST AN INDICATION THAT THIS HASN'T BEEN DONE.
                        echo '<td>need to implement this type</td>';
                        echo '<td>needs implementing</td>';
                    }
                    
                    // "Amount": U.S. currency, 2 digits past the decimal, as usual 
                    echo '<td>' . $memo['amount'] . '</td>';
                    
                    // "personId": straight from the DB (the employee who entered this, not a person at the relevant company)
                    // "inserted": straight from the DB, TIMESTAMP for insertion of this row 
                    echo '<td>' . $memo['personId'] . '</td>';
                    echo '<td>' . $memo['inserted'] . '</td>';
                echo '</tr>';                    
            } // END foreach ($memos...
            echo '</tbody>';
            echo '</table>';
            ?>
        <?php 
        } else {
            // >>>00002 Probably should give an error message: no tab currently defined beyond 8
        }
        
        ?>
        </div>
    </div>
<?php
/* BEGIN REMOVED 2020-02-19 JM: this closed one too many DIVs
</div>
// END REMOVED 2020-02-19 JM
*/
?>
<script>
$(document).ready(function() {

    $('[id^=linkDeleteCreditRecord]').click(function (e) {    
        // Check for input values.
        var recordType = $(this).closest("tr").find("select").val(); // check if record type option is selected.
        var referenceValue = $(this).closest("tr").find(".ref_editable").val(); // Reference Value
        var amtValue = $(this).closest("tr").find(".amt_editable").val(); // Amt Value
        var dateValue = $(this).closest("tr").find('[id^=date_]').val(); // Credit Date Value
        var depDateValue = $(this).closest("tr").find('[id^=depdate_]').val(); // Dep. Date Value
        var recivedFromValue = $(this).closest("tr").find('[id^=recivedFrom_]').val(); // Recived From Value
        var notesValue = $(this).closest("tr").find('[id^=notesTextarea_]').val(); // Notes Value

        if(recordType || referenceValue || amtValue || dateValue || depDateValue || recivedFromValue || notesValue ) {
            alert("In order to Delete this Credit Record you have to remove any input or selected values to avoid violation of Database Integrity");
            e.preventDefault();
        } else {
            if(confirm('Are you sure to Delete this Credit Record ?')) {
                $(this).unbind('click'); // If OK, proceed to act = deleteRecordId
                return true;
            } else {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
<?php 
include BASEDIR . '/includes/footer.php';
?>