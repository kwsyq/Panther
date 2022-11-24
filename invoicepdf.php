<?php
/* invoicepdf.php

   EXECUTIVE SUMMARY: make a PDF version of an invoice or invoices.
   Should be very similar to invoice.php, but for a PDF instead of a web page, so of course no editing.
   JM 2019-03-14: Despite the similarities, there are a lot of absolutely arbitrary differences in names of equivalent variables,
     and far too little common code even when following exactly the same business logic.
     >>>00012 Someone may want to bring those in line.
     >>>00001 Also: once it gets into elementgroups & tasks, this is pretty unnecessarily convoluted,
       and a lot of things don't really have the most mnemonic names. That part might deserve an overall rewrite.

   PRIMARY INPUT: $_REQUEST['invoiceId'], primary key into DB table Invoice. Beginning with v2020-3, this can be an array of invoiceIds.

   ADDITIONAL INPUT: $_REQUEST['companyName'], optional, germane only if there are multiple invoiceIds.
   >>>00002, >>>00016 as of 2020-06-30, not validated. That's OK for now because of how we call this, but not very secure. Could probably use some thought.
     Look at where this is called (e.g. in multi.php) when analyzing this.

    Prior to 2020-09-11 "est"/"estimate" language was blindly carried over from the contract code; here these are *not* just estimates.
    So I've changed variables that previously had names like $estQuantity and $estCost to be $quantity and $cost. Also, the old
    $cost became $calculated_cost. Sadly, we can't easily do the same in the data structure returned from function 'overlay'. - JM
*/

require_once './inc/config.php';
require_once './inc/access.php';

// This function (and calls to it) introduced for v2020-3
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

$invoiceIds = isset($_REQUEST['invoiceId']) ? $_REQUEST['invoiceId'] : 0;
if (!is_array($invoiceIds)) {
    if ($invoiceIds === 0) {
        $error = "invoicepdf.php called without invoiceId";
        $logger->error2('1593466482', $error);
        reportSevereError($error);
    } else {
        $invoiceIds = array($invoiceIds);
    }
}

$companyName = array_key_exists('companyName', $_REQUEST) ? $_REQUEST['companyName'] : 'client_company';

$invoiceStatusAwaitingDelivery = Invoice::getInvoiceStatusIdFromUniqueName('awaitingdelivery');
if (!$invoiceStatusAwaitingDelivery) {
    // Invoice::getInvoiceStatusIdFromUniqueName will already have logged the problem
    reportSevereError("Invoice status 'awaitingdelivery' is undefined, serious problem, contact an administrator or developer.");
}
$invoiceStatusAwaitingPayment = Invoice::getInvoiceStatusIdFromUniqueName('awaitingpayment');
if (!$invoiceStatusAwaitingPayment) {
    // Invoice::getInvoiceStatusIdFromUniqueName will already have logged the problem
    reportSevereError("Invoice status 'awaitingpayment' is undefined, serious problem, contact an administrator or developer.");
}
$invoiceStatusPartiallyPaid = Invoice::getInvoiceStatusIdFromUniqueName('partiallypaid');
if (!$invoiceStatusPartiallyPaid) {
    // Invoice::getInvoiceStatusIdFromUniqueName will already have logged the problem
    reportSevereError("Invoice status 'partiallypaid' is undefined, serious problem, contact an administrator or developer.");
}
$invoiceableStatuses = Array(
    $invoiceStatusAwaitingDelivery,
    $invoiceStatusAwaitingPayment,
    $invoiceStatusPartiallyPaid
    );

foreach ($invoiceIds AS $invoiceId) {
    $invalidInvoiceIds = Array();
    if (!Invoice::validate($invoiceId)) {
        $invalidInvoiceIds[] = $invoiceId;
    }
    if ($invalidInvoiceIds) {
        $error = "invoicepdf.php called with one or more invalid invoiceIds:";
        foreach ($invalidInvoiceIds AS $invalidInvoiceId) {
            $error .= " $invalidInvoiceId";
        }
        $logger->error2('1593466511', $error);
        reportSevereError($error);
    }
}
use Ahc\Jwt\JWT;
require_once __DIR__.'/vendor/autoload.php';
$jwt = new JWT(base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU"), 'HS256', 3600, 10);

define ('PASS_CONTRACT', 1);
define ('PASS_NONCONTRACT', 2);
$token = $jwt->encode([
    'user' => $user->getUsername()
]);

foreach ($invoiceIds AS $invoiceId) {
    $invoice = new Invoice($invoiceId);

    if($invoice->getWorkOrderId()<=14174){
        header("Location: https://old.ssseng.com/redirectToken.php?url=".urlencode($_SERVER[REQUEST_URI])."&page=INVPDF&token=".$token);
        die();
    }

    /* BEGIN REPLACED 2020-09-02 JM
    $contractId = $invoice->getContractId(); // JM 2020-01: It appears that more often than not, this is 0...
    $contract = new Contract($contractId);   // ... so more often than not, this is an empty object.
    // END REPLACED 2020-09-02 JM
    */
    // BEGIN REPLACEMENT 2020-09-02 JM
    $contractId = $invoice->getContractId(); // JM 2020-01: It appears that more often than not, this $contractId is 0,
                                             // ... so more often than not, there is no contract
    if ($contractId) {
        $contract = new Contract($contractId);
    } else {
        $contract = null;
    }
    // END REPLACEMENT 2020-09-02 JM
    $workOrder = new WorkOrder($invoice->getWorkOrderId());
    $job = new Job($workOrder->getJobId());

    // [BEGIN Martin comment]
    // DEAL WITH MULTIPLE CLIENTS !!!!!!!
    // AND MULTIPLE OTHER STUFF !!
    // [END Martin comment]
    // >>>00001 JM 2019-03: The above comment is presumably a statement of work to be done, not a claim that this currently does that, and
    //  the comment that follows is a statement of current policy.
    // [BEGIN Martin comment]
    // only want 1 client.
    // if its wrong one then it needs to be fixed at team level
    // [END Martin comment]
    $clientNames = '';
    $clients = $workOrder->getClient(); // [Martin comment:] returns array of CompanyPerson objects;
    $clients = array_slice($clients, 0, 1);

    // The following is written to loop over multiple clients, even though just above
    // we forced this to a single client.
    foreach ($clients as $client) {
        $person = $client->getPerson();
        $personName = $person->getFormattedName(1);
        $personName = str_replace("&nbsp;"," ",$personName);
        $personName = trim($personName);

        if (strlen($personName)){
            if (strlen($clientNames)){
                $clientNames .= "\n";
            }
            $clientNames .= $personName;
        }
    }
    // >>>00001 but the we don't do anything with $clientNames, should we? - JM 2020-10-29

    $designProNames = '';
    $designProfessionals = $workOrder->getDesignProfessional();  // [Martin comment:] returns array of CompanyPerson objects;
    foreach ($designProfessionals as $designProfessional) {
        $person = $designProfessional->getPerson();
        $personName = $person->getFormattedName(1);
        $personName = str_replace("&nbsp;"," ",$personName);
        $personName = trim($personName);

        if (strlen($personName)){
            if (strlen($designProNames)){
                $designProNames .= "\n";
            }
            $designProNames .= $personName;
        }
    }
    // >>>00001 but the we don't do anything with $designProNames, should we? - JM 2020-10-29

    if (!isset($pdf)) {
        $pdf = new PDF('P','mm','Letter');
        $pdf->AddFont('Tahoma','','Tahoma.php');
        $pdf->AddFont('Tahoma','B','TahomaB.php');
        $pdf->AliasNbPages();
        $pdf->setDocumentType('contract'); // Effectively adds footer to be initialed
                                           // >>>00026 is this actually wanted on invoice?
                                           // In any case, we change this below so it's only on the first section
                                           //  not the element/task stuff.
        // The following is significantly reworked 2020-01-13 JM. Previously, this was done in terms of variables rather than constants,
        //  and a lot of the following were repeated expressions without names. I believe these constants make the logic a lot clearer.
        define("PAGE_WIDTH", $pdf->GetPageWidth());
        define("PAGE_HEIGHT", $pdf->GetPageHeight());
        define("RIGHT_MARGIN", 15);
        define("LEFT_MARGIN", 15);
        define("RIGHT_EDGE", PAGE_WIDTH - RIGHT_MARGIN); // x-index of right edge of written area
        define("EFFECTIVE_WIDTH", PAGE_WIDTH - RIGHT_MARGIN - LEFT_MARGIN); // effective page width excluding margins
    }
    $pdf->AddPage();

    if (!in_array(intval($invoice->getInvoiceStatusId()), $invoiceableStatuses)) {
        $pdf->SetXY(0,0);
        $pdf->SetFont('Tahoma','B',60);
        $pdf->SetTextColor(227, 227, 227);
        $pdf->Rotate(315);
        $pdf->Text(0,0,'DRAFT DRAFT DRAFT DRAFT'); // Indicates draft invoice, not that this code is a draft. This is because
                                                   // invoice is neither awaiting payment nor delivery.
        $pdf->SetXY(0,0);
        $pdf->Rotate(-315);
        $pdf->SetTextColor(0, 0, 0);
    }

    $pdf->SetAutoPageBreak(true, 5);

    ////////////////////////////////////////////
    // [Martin comment:] header logo
    ////////////////////////////////////////////
    $num_pages = $pdf->setSourceFile2(BASEDIR . '/cust/' . $customer->getShortName() . '/img/pdf/logoblackai.pdf');
    $template_id = $pdf->importPage(1); //if the grafic is on page 1
    $size = $pdf->getTemplateSize($template_id);

    $pdf->useTemplate($template_id, PAGE_WIDTH - $size['width']/2.65 - 10, 10, $size['width']/2.65, $size['height']/2.65);

    $pdf->setY(PAGE_HEIGHT / 6);

    $date = $invoice->getInvoiceDate();

    $dateshown = false;
    if ((strlen($date) && ($date != '0000-00-00 00:00:00'))) {
        // Has presumably valid date, show it in 'm/d/Y' form
        $pdf->setY($pdf->GetY() + 5);
        $pdf->SetFont('Tahoma', 'B', 12);
        $d = new DateTime($date);
        $total_string_width = $pdf->GetStringWidth($d->format('m/d/Y'));
        $pdf->setX(PAGE_WIDTH - $total_string_width - 11);
        $pdf->cell(0, 6, $d->format('m/d/Y'), 0, 2);
        $dateshown = true;
    }
    if (!$dateshown){
        $pdf->ln(5);
    }

    // ----- Display invoice number ------
    $pdf->SetFont('Tahoma', 'B', 16);
    $total_string_width = $pdf->GetStringWidth("Invoice #" . $invoice->getInvoiceId());
    $pdf->setX(PAGE_WIDTH - $total_string_width - 11);
    $pdf->cell(0, 6, 'Invoice #' . $invoice->getInvoiceId(), 0, 2);

    // ----- Display customer info (as of 2019-03 always SSS) ------
    $pdf->SetY(20);
    $pdf->SetX(17);

    $pdf->SetFont('Tahoma','',10);
    /* E.g.
    "Sound Structural Solutions"
    "24113 56th Ave W\nMountlake Terrace WA, 98043"
    "Ph (425)778 1023"
    */
    // BEGIN NEW CODE 2019-02-06 JM
    $pdf->Cell(80, 5, CUSTOMER_NAME, 0, 2);
    $pdf->Cell(80, 5, CUSTOMER_STREET_ADDRESS, 0, 2);
    $pdf->Cell(80, 5, CUSTOMER_CITY_AND_ZIP, 0, 2);
    // END NEW CODE 2019-02-06 JM

    // ----- Display job/project address (multi-line), possibly modified for invoice ------
    $pdf->SetY(58);
    $pdf->SetX(25);

    $parts = explode("\n", $invoice->getAddressOverride());

    // BEGIN ADDED 2020-11-05 JM to address http://bt.dev2.ssseng.com/view.php?id=262: try to get address from the shadow billing profile
    if (count($parts) == 1 && strlen(trim($parts[0])) == 0) {
        // Clear it out
        $parts = Array();
    }

    if (count($parts) == 0) {
        $bps = $invoice->getBillingProfiles();
        if (count($bps)) {
            $shadowBillingProfile = new ShadowBillingProfile($bps[0]['shadowBillingProfile']);

            $ccn = '';
            $cc = new Company($shadowBillingProfile->getCompanyId());

            $ccn = $cc->getCompanyName();
            $ccn = trim($ccn);
            if (((substr($ccn, 0, 1) == '[') && (substr($ccn,-1) == ']'))) {
                $ccn = substr($ccn, 1);
                $ccn = substr($ccn, 0, (strlen($ccn) - 1));
            }
            if ($ccn) {
                $parts[] = $ccn;
            }
            if (intval($shadowBillingProfile->getCompanyPersonId())) {
                $cp = new CompanyPerson($shadowBillingProfile->getCompanyPersonId());
                if (intval($cp->getCompanyPersonId())) {
                    $pp = $cp->getPerson();
                    $parts[] = $pp->getFormattedName(1);
                }
            }

            if (intval($shadowBillingProfile->getPersonEmailId())) {
                $query = "SELECT * FROM  " . DB__NEW_DATABASE . ".personEmail ";
                $query .= "WHERE personEmailId = " . intval($shadowBillingProfile->getPersonEmailId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0){
                        $row = $result->fetch_assoc();
                        $parts[] = $row['emailAddress'];
                    }
                } else {
                    $logger->errorDb('1604614936', 'Hard DB error', $db);
                }
            }

            if (intval($shadowBillingProfile->getPersonLocationId())) {
                $query = "SELECT locationId FROM  " . DB__NEW_DATABASE . ".personLocation " .
                $query .= "WHERE personLocationId = " . intval($shadowBillingProfile->getPersonLocationId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $l = new Location($row['locationId']);
                        $temp = trim($l->getFormattedAddress());
                        if ($temp) {
                            $tempArray = explode("\n", $invoice->getAddressOverride());
                            $parts = array_merge($parts, $tempArray);
                        }
                    }
                } else {
                    $logger->errorDb('1604615035', 'Hard DB error', $db);
                }
            }

            if (intval($shadowBillingProfile->getCompanyEmailId())) {
                $query = "SELECT * FROM  " . DB__NEW_DATABASE . ".companyEmail ";
                $query .= "WHERE companyEmailId = " . intval($shadowBillingProfile->getCompanyEmailId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $parts[] = $row['emailAddress'];
                    }
                } else {
                    $logger->errorDb('1604615155', 'Hard DB error', $db);
                }
            }

            if (intval($shadowBillingProfile->getCompanyLocationId())) {
                $query = "SELECT * FROM  " . DB__NEW_DATABASE . ".companyLocation ";
                $query .= "WHERE companyLocationId = " . intval($shadowBillingProfile->getCompanyLocationId()) . ";";

                $result = $db->query($query);
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $l = new Location($row['locationId']);
                        $temp = trim($l->getFormattedAddress());
                        if ($temp) {
                            $tempArray = explode("\n", $invoice->getAddressOverride());
                            $parts = array_merge($parts, $tempArray);
                        }
                    }
                } else {
                    $logger->errorDb('1604615206', 'Hard DB error', $db);
                }
            }
        } // END has profile
    }
    // END ADDED 2020-11-05 JM

    if (count($parts)) {
        foreach ($parts as $part) {
            $pdf->Cell(80, 5, trim($part), 0, 2);
        }
    }
    $pdf->SetY(PAGE_HEIGHT/6);

    // ----- Display job/project name -- possibly modified for invoice -- and Job Number ------
    $pdf->ln(2);
    $pdf->SetFont('Tahoma','B',13);
    $jn = '';
    $jn = $invoice->getNameOverride();
    if (!strlen($jn)){
        $jn = $job->getName();
    }

    $text = 'Project: ' . $jn;
    $total_string_width = $pdf->GetStringWidth($text);

    if ($total_string_width > 120){
        $total_string_width = 120;
    }

    $yval = (PAGE_HEIGHT/3) - (($pdf->GetStringWidth($text)/$total_string_width) * 5);
    $yval = $yval ;
    $pdf->SetY($yval);
    $pdf->SetX(10);
    $pdf->SetFont('Tahoma', 'B', 12);
    $pdf->MultiCell(120, 5, $text, 0, 'L');

    $yval = (PAGE_HEIGHT/3) - (($pdf->GetStringWidth($text)/$total_string_width) * 5);
    $yval = $yval - 5;
    $pdf->SetY($yval);
    $pdf->SetFont('Tahoma', 'B', 13);

    $text = 'SSS# ' . $job->getNumber();;
    $total_string_width = $pdf->GetStringWidth($text);
    $pdf->setX(10);
    $pdf->SetFont('Tahoma','B',12);
    $pdf->cell(100, 5, $text, 0, 2, 'L');

    $len = 5;

    // Effectively, mark a line ONLY IN MARGINS (though we do this independent of our margins as such).
    //$pdf->Line(0, PAGE_HEIGHT/3, $len + 5, PAGE_HEIGHT/3);
    //$pdf->Line(PAGE_WIDTH - $len - 5, PAGE_HEIGHT/3, PAGE_WIDTH, PAGE_HEIGHT/3);

    $pdf->SetLeftMargin(LEFT_MARGIN);
    $pdf->SetRightMargin(RIGHT_MARGIN);

    // The return of overlay is rather "hairy"; see the function for details, not reiterating here (more detail below
    //  where we discuss $final), but the one part crucial to understand here is that a it is a multi-level array structure,
    //  and each $elementgroup[$i] represents workOrderTasks associated with one of the following, depending on the nature of index $i:
    // 0 - "General" workOrderTasks not associated with any element
    // positive integer - workOrderTasks associated with a single element; $i is the elementId
    // comma-separated list of positiveintegers - workOrderTasks associated jointly with two or more elements; $i is
    //   a comma-separated list of elementIds.
    $elementgroups = overlay($workOrder, $invoice);
    $taskTypes = getTaskTypes();

    $clientMultiplier = $invoice->getClientMultiplier();
    if(filter_var($clientMultiplier, FILTER_VALIDATE_FLOAT) === false) {
        $clientMultiplier = 1;
    }

    $grandTotal = 0;
    $contractTotal = 0;

    // $grandNTE = 0; // NTE => "not to exceed" // REMOVED 2020-09-02 JM
    // $elementNTE = 0; // REMOVED 2020-09-15, redundant to set this here

    $final = array(); // This array represents tasks we will actually show in the invoice. In particular
                      //  it should omit tasks that are not shown in their own right, but lumped into their parents.
                      //
                      // JM 2020-10-30 addressing http://bt.dev2.ssseng.com/view.php?id=261 (An Element is not displaying in the contract PDF):
                      //   the issue here is analogous.
                      //  Previous code did not deal correctly with the comma-separated list of positive integer elementIds (which, in v2020-4
                      //  superseded a generic "multiple elements" case). Changes are too pervasive to indicate one by one, but basically
                      //  the new variable $elementGroupId replaces both an old loop index $ekey and a variable $elementId that only made sense if
                      //  $ekey was always to be understood as an integer.
                      //
                      // This will be an array of associative arrays, and the associative arrays have members:
                      //  * 'elementName'
                      //  * 'tasks', itself an array, copied from $elementgroup['tasks'] (really these are workOrderTasks, not tasks, despite the name).
                      //     * Besides what we copy from $elementgroup['tasks'], there is
                      //       * $final['tasks'][$taskkey]['groupTotal']: U.S. currency total appended to
                      //          each task, taking into account all of its subtasks. Before 2020-02-09, 'groupTotal' was 'grp'
                      //       * $final['tasks'][$taskkey]['show']. Whether to show the task in the invoice.
                      //  * 'elementTotal'
                      //  * 'elementNoncontractTotal' ADDED 2020-09-21 JM to address bt.dev2.ssseng.com/view.php?id=194#c1099
                      //  * 'elementNTE'

    $noncontractcount = 0; // number of tasks NOT from contract
    $contractcount = 0; // number of tasks from contract

    foreach ($elementgroups as $elementGroupId => $elementgroup) {
        $elementTotal = 0; // total for this element or elementGroup.
        $elementNoncontractTotal = 0; // similarly, but noncontract only
        $elementNTE = 0;

        // renamed $en as $elementOrGroupName JM 2020-01-16
        if ($elementGroupId == PHP_INT_MAX) {
            // Should no longer occur in v2020-4
            $elementOrGroupName = 'Other Tasks (Multiple Elements Attached)';
        } else if ($elementGroupId == 0){
            $elementOrGroupName = 'General';
        } else {
            // 'Other tasks' should never arise, here only out of an excess of caution.
            $elementOrGroupName = ($elementgroup['element']) ? $elementgroup['element']['elementName'] : 'Other tasks';
        }

        $final[$elementGroupId] = Array(); // Added 2019-12-02 JM: initialize array before using it!
        $final[$elementGroupId]['elementName'] = $elementOrGroupName;
        $final[$elementGroupId]['tasks'] = array();

        if (isset($elementgroup['tasks'])) {
            if (is_array($elementgroup['tasks'])) {
                // BEGIN REPLACED 2020-01-16 JM
                // $tasks = $elementgroup['tasks'];
                // END REPLACED 2020-01-16 JM
                // BEGIN REPLACEMENT 2020-01-16 JM
                $tasks = &$elementgroup['tasks']; // NOTE the ampersand: $tasks here is just an alias/reference
                // END REPLACEMENT 2020-01-16 JM

                ///////////////////////////////////////////
                ///////////////////////////////////////////
                ///////////////////////////////////////////
                ///////////////////////////////////////////
                ///////////////////////////////////////////
                // BEGIN REMOVED 2020-01-16 JM
                // $showfix = array(); // $showfix is preparatory to the 'show' data that determines whether the row gets shown.
                // END REMOVED 2020-01-16 JM

                // BEGIN ADDED 2020-09-02 JM
                // Initialize all tasks to 'show'==1 before we go through and change some to 'show'==0.
                // See discussion of $final['tasks'][$taskkey]['show'] above.
                foreach ($tasks as $taskkey => $task) {
                    $tasks[$taskkey]['show'] = 1; // initialize, see discussion of $final['tasks'][$taskkey]['show'] above.
                    $tasks[$taskkey]['showWithoutNumbers'] = 0; // Added 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
                }
                // END ADDED 2020-09-02 JM

                // We will loop twice through tasks, first to see decide what to show and to "group in" subtasks,
                // second to do the rest of the work.
                foreach ($tasks as $taskkey => $task) {
                    $sliced = array_slice($tasks, $taskkey + 1); // The rest of array $tasks
                    $startLevel = intval($task['level']);

                    $groupTotal = 0; // total of this task and its subtasks RENAMED 2020-09-02 JM, was just $total
                    $taskTotal = 0;  // just for this task, not its subtasks RENAMED 2020-09-02 JM, was just $sum

                    // Quantity & cost are actuals, not estimates, despite the
                    //  names of the array indexes in the data structure
                    $quantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;
                    $cost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;

                    $taskTotal = (($quantity=='' ? 0 : $quantity) * ($cost=='' ? 0 : $cost) * $clientMultiplier);

                    $groupTotal += $taskTotal;

                    // BEGIN REMOVED 2020-09-02 JM
                    // Wrong place to initialize this, now initialize above as $tasks[$taskkey]['show'] = 1
                    // $elementgroup['tasks'][$taskkey]['show'] = 1;
                    // END REMOVED 2020-09-02 JM

                    // Now we want to look at any subtasks.
                    // NOTE that each of these subtasks will come up again in the outer loop as a task in its own right.
                    foreach ($sliced as $skey => $slice) {
                        if ($slice['level'] > $startLevel) {
                            // This is a subtask of $task, so add it in
                            $subtaskTotal = 0; // total for this subtask, excluding its own subtasks RENAMED 2020-09-02 JM, was just $str

                            // Quantity & cost are actuals, not estimates, despite the
                            //  names of the array indexes in the data structure
                            $quantity = isset($slice['task']['estQuantity']) ? $slice['task']['estQuantity'] : 0;
                            $cost = isset($slice['task']['estCost']) ? $slice['task']['estCost'] : 0;

                            $subtaskTotal = ( ($quantity =='' ? 0: $quantity) * ($cost==''? 0 : $cost) * $clientMultiplier);
                            $groupTotal += $subtaskTotal;

                            /* BEGIN REPLACED 2020-01-16 JM
                            // This is scratch for the ad hoc $elementgroup['tasks'][$taskkey]['show']. In this
                            //  case the ultimate effect will be to set $task['tasks'][$taskkey + 1 + $skey]['show']=0.
                            //  JM: No idea why we use $showfix instead of just setting that here.
                            //  See discussion of $task['tasks'][$taskkey]['show'] above.
                            //
                            // If this task has a downarrow, calculate the next index in $tasks, which will always be exactly one level
                            //  deeper in the hierarchy than the present task. Mark that to later get $task['tasks'][$taskkey]['show']
                            //  set to 0.
                            if ( isset($task['task']['arrowDirection']) &&  ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN)) {
                                $showfix[] = $taskkey + 1 + $skey;
                            }
                            // END REPLACED 2020-01-16 JM
                            */
                            // BEGIN REPLACEMENT 2020-01-16 JM
                            // If the ancestor task (lower "level" number) has a downarrow:
                            //  * we calculate the index **in $tasks** of the workOrderTask we are currently looking at **in $sliced**
                            //  * we modify its 'show' value **in $tasks**.
                            if ( isset($task['task']['arrowDirection']) &&  ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN)) {
                                $tasks[$taskkey + 1 + $skey]['show'] = 0; // see discussion of $final['tasks'][$taskkey]['show'] above.
                            }
                            // END REPLACEMENT 2020-01-16 JM
                            unset($subtaskTotal); // ADDED 2020-09-02 JM
                        } else {
                            break;
                        }
                    }
                    /* BEGIN REPLACED 2020-09-02 JM
                    $elementgroup['tasks'][$taskkey]['grp'] = $groupTotal;
                    // END REPLACED 2020-09-02 JM
                    $tasks is an alias for $elementgroup['tasks'], let's be consistent in using it
                    */
                    // BEGIN REPLACEMENT 2020-09-02 JM
                    $tasks[$taskkey]['groupTotal'] = $groupTotal;
                    // END REPLACEMENT 2020-09-02 JM
                    unset($sliced, $startLevel, $groupTotal, $taskTotal, $quantity, $cost); // ADDED 2020-09-02 JM
                }

                // At this point, 'groupTotal' and 'show' have been calculated.

                /* BEGIN REMOVED 2020-01-16 JM
                foreach ($showfix as $sf) {
                    $elementgroup['tasks'][$sf]['show'] = 0; // see discussion of $final['tasks'][$taskkey]['show'] above.
                }
                // END REMOVED 2020-01-16 JM
                */

                /* BEGIN REMOVED 2020-01-16 JM : No need to do this now that we made $tasks a reference rather than a copy.
                $tasks = $elementgroup['tasks'];
                // END REMOVED 2020-01-16 JM
                */

                ///////////////////////////////////////////
                ///////////////////////////////////////////
                ///////////////////////////////////////////
                ///////////////////////////////////////////
                ///////////////////////////////////////////

                foreach ($tasks as $task) {
                    /* BEGIN REPLACED 2020-01-16 JM
                    $con = 0;

                    // As it stands the following does nothing. EVERYTHING will be marked
                    // as non-contract!
                    if (isset($task['task']['fromContract'])){
                        if (!intval($task['task']['fromContract'])){
                            $con = 0;
                        } else {
                            // BEGIN commented out by Martin before 2019
                            // 2018 11 01  // this actually wasnt here .. so what was i trying to do ???
                            //$con = 1;
                            // END commented out by Martin before 2019
                        }
                    } else {
                        $con = 0;
                        //$noncontractcount++; // commented out by Martin before 2019
                    }
                    // END REPLACED 2020-01-16 JM
                    */
                    // BEGIN REPLACEMENT 2020-01-16 JM
                    // NOTE that as of 2020 most SSS invoices do not even have a corresponding contract. Most work is done
                    //  under a general agreement with a client rather than an explicit contract.
                    if ( isset($task['task']['fromContract']) && intval($task['task']['fromContract']) ) {
                        /* BEGIN REPLACED 2020-09-02 JM
                        $contractcount++;
                        // END REPLACED 2020-09-02 JM
                        */
                        // BEGIN REPLACEMENT 2020-09-02 JM
                        if ($contract) {
                            $contractcount++;
                        } else {
                            // Theoretically this shouldn't happen, but it's been seen.
                            // Note the DB problem, and ignore 'fromContract'
                            $logger->warn2('1599080103', "Task {$task['workOrderTaskId']} says it is from contract but invoice $invoiceId is not based on a contract.");
                            $noncontractcount++;
                        }
                        // END REPLACEMENT 2020-09-02 JM
                    } else {
                        $noncontractcount++;
                    }
                    // END REPLACEMENT 2020-01-16 JM

                    if ($task['type'] == 'fake'){
                        /* $task['type'] == 'fake' is an expedient for a missing parent task.
                           Theory (according to Martin) is that this should eventually go away, but
                           they are still being created as of v2020-3, so we will need this (or a fix elsewhere
                           to old invoices) as long as we still want to be able to look at old contracts
                           (where old means 2020 or earlier).

                           If the task is "fake" and we need to show it, we just span all the columns with a task description.
                        */
                        if (intval($task['show'])) { // TEST ADDED 2020-09-02 JM
                            $final[$elementGroupId]['tasks'][] = $task;
                        }
                    } else if ($task['type'] == 'real') {
                        $taskTypeId = $task['task']['taskTypeId']; // $taskTypeId determines whether we show NTE ("not to exceed").
                                                                   // >>>00014: NTE was dropped from invoice.php. Is it going away?
                                                                   //  Was that an error there? If not, why do the two files differ?
                        $wot = new WorkOrderTask($task['workOrderTaskId']);

                        // $viewMode = $wot->getViewMode(); // REMOVED 2020-10-28 JM getting rid of viewmode

                        /* THIS TEST REMOVED 2020-09-02 JM after discussion with Damon. We think it is irrelevant old stuff, not how we determine this now.
                        if (intval($viewMode) & WOT_VIEWMODE_CONTRACT) {
                        */
                            $t = $wot->getTask();
                            $tt = '';
                            if (isset($taskTypes[$t->getTaskTypeId()]['typeName'])){
                                $tt = $taskTypes[$t->getTaskTypeId()]['typeName'];
                            }

                            // Quantity & cost are actuals, not estimates, despite the
                            //  names of the array indexes in the data structure
                            $cost = isset($task['task']['estCost']) ? $task['task']['estCost'] : 0;
                            $cost = preg_replace("/[^0-9.+-]/", "", $cost); // >>>00002: Not a great test. For example, allows
                                                                        // "45.55.66"; changes "579.8gyy5" to "579.85". We should have a better
                                                                        // test and should log bad data.

                            $quantity = isset($task['task']['estQuantity']) ? $task['task']['estQuantity'] : 0;

                            $nte = '';
                            if ($taskTypeId == TASKTYPE_HOURLY) {
                                $nte = isset($task['task']['nte']) ? intval($task['task']['nte']) : 0;
                            }
                            $calculated_cost = number_format(($quantity=='' ? 0 : $quantity) * ($cost=='' ? 0 : $cost) * $clientMultiplier, 2);

                            if (!$quantity){
                                $quantity = '';
                            }

                            $adder = preg_replace("/[^0-9.]/", "", $calculated_cost); // >>>00002: Not a great test, as for $cost above.

                            if (is_numeric($adder)){
                                $elementTotal += $adder;
                                $grandTotal += $adder; // NOTE that we add to grand total regardless of "show"

                                if ($contract) { // test added 2020-09-02 JM: if there is a contract
                                    if (isset($task['task']['fromContract'])){
                                        if($task['task']['fromContract']==1) {
                                            if (intval($task['task']['fromContract'])) {
                                                $contractTotal += $adder;
                                            }
                                        } else {
                                            $elementNoncontractTotal += $adder;
                                        }
                                    }
								} else {
                                    // BEGIN ADDED 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
                                    $elementNoncontractTotal += $adder;
                                    // END ADDED 2020-09-15 JM
                                }
                            }

                            if (intval($nte) &&  ($taskTypeId == TASKTYPE_HOURLY)) {
                                $elementNTE += ($nte * $estCost * $clientMultiplier);
                                // $grandNTE += ($nte * $estCost * $clientMultiplier); // REMOVED 2020-09-02 JM
                            } else {
                                $elementNTE += $adder;
                                // $grandNTE += $adder; // REMOVED 2020-09-02 JM
                            }

                            if (!strlen($calculated_cost)){
                                $calculated_cost = '';
                            } else {
                                $calculated_cost = '$' . $calculated_cost;
                            }

                            if (intval($task['show'])) { // BEFORE 2020-09-02 JM this test was much farther down, but it makes more sense here.
                                                         // Nothing past here at this level means anything unless we are showing this task.
                                $costNoFormat = $cost;

                                if (!strlen($cost)){
                                    $cost = '';
                                } else {
                                    $cost = '$' . number_format($cost, 2); // >>>00026: missing $clientMultiplier here, is that deliberate?
                                }

                                // NOTE that the following lines assign to $task, which is a foreach-loop variable. That is
                                //  OK, because it functions here as a scratch value for $final.
                                if (!isset($task['task']['arrowDirection'])){
                                    $task['task']['arrowDirection'] = ARROWDIRECTION_RIGHT;
                                }
                                if ($task['task']['arrowDirection'] == ARROWDIRECTION_DOWN) {
                                    /* BEGIN REPLACED 2020-09-21 JM to address bt.dev2.ssseng.com/view.php?id=194#c1099
                                    $task['cost'] = array('typeName' => $tt, 'price' => $cost, 'quantity' => $quantity, 'cost' =>  '$' . number_format($task['groupTotal'], 2));
                                    // END REPLACED 2020-09-21 JM
                                    */
                                    // BEGIN REPLACEMENT 2020-09-21 JM
                                    // Don't show price or quantity for non-leaf workOrderTasks that actually summarize multiple descendent workOrderTasks
                                    $task['cost'] = array('typeName' => $tt, 'price' => '', 'quantity' => '', 'cost' =>  '$' . number_format($task['groupTotal'], 2));
                                    // END REPLACEMENT 2020-09-21 JM
                                } else {
                                    $task['cost'] = array('typeName' => $tt, 'price' => $cost, 'quantity' => $quantity, 'cost' => $calculated_cost);
                                }

                                $task['nte'] = array('nte' => $nte, 'cost' => (($nte==''?0:$nte) * ($costNoFormat==''?0:$costNoFormat) * ($clientMultiplier==''?0:$clientMultiplier)));

                            // if (intval($task['show'])) { // THIS TEST REMOVED here & added 20 lines or so above 2020-09-02 JM
                                $final[$elementGroupId]['tasks'][] = $task;
                            }
                            unset($t, $tt, $cost, $quantity, $nte, $cost, $calculated_cost, $adder, $costNoFormat);  // ADDED 2020-09-02 JM
                        // } // REMOVED 2020-09-02 JM
                        unset($taskTypeId, $wot); // ADDED 2020-09-02 JM
                    } else {
                        $logger->error2('1599070621', 'Encountered a task that is neither "real" nor "fake".' );
                    }
                }
            }
        }

        $final[$elementGroupId]['elementTotal'] = $elementTotal;
        $final[$elementGroupId]['elementNoncontractTotal'] = $elementNoncontractTotal; // ADDED 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
        $final[$elementGroupId]['elementNTE'] = $elementNTE;
        // BEGIN ADDED 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
        // loop backward to find anything that is not shown but has a child that is shown
        $currentShownLevel = -1;
        $childIsShown = false;

        for ($taskkey=count($final[$elementGroupId]['tasks'])-1; $taskkey>=0; --$taskkey) {
            $rawcost = isset($final[$elementGroupId]['tasks'][$taskkey]['task']['estCost']) ? $final[$elementGroupId]['tasks'][$taskkey]['task']['estCost'] : 0;
            if ($final[$elementGroupId]['tasks'][$taskkey]['level'] > $currentShownLevel) {
                // start over from here; note that in theory we can hit this several times
                // going through siblings some of which are shown, but not the first that we hit.
                // (Not sure that case ever arises, though.)
                $currentShownLevel = -1;
                $childIsShown = $rawcost && $final[$elementGroupId]['tasks'][$taskkey]['show'] &&
                    !(isset($final[$elementGroupId]['tasks'][$taskkey]['task']['fromContract']) && $final[$elementGroupId]['tasks'][$taskkey]['task']['fromContract']);
                if ($childIsShown) {
                    $currentShownLevel = $final[$elementGroupId]['tasks'][$taskkey]['level'];
                }
            } else if ($childIsShown && $final[$elementGroupId]['tasks'][$taskkey]['level'] < $currentShownLevel) {
                // We may not want numbers here, but since a child is shown we will want the description
                $final[$elementGroupId]['tasks'][$taskkey]['showWithoutNumbers'] = true;
            }
        }
        // END ADDED 2020-09-15 JM
        unset($elementTotal, $elementNTE); // ADDED 2020-09-15 JM
    } // END foreach ($elementgroups...

/*
if($contractId==0){
    $query = "SELECT elementId as id, elementName as Title, null as parentId, elementName as billingDescription,
    null as taskId, null as parentTaskId, null as workOrderTaskId,
    null as totCost, null as taskTypeId, null as taskStatusId, 0 as taskContractStatus,
    elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
    from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrder->getWorkOrderId().")
    UNION ALL
    SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.billingDescription, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
    w.totCost as totCost,
    w.taskTypeId as taskTypeId, w.taskStatusId as taskStatusId, w.taskContractStatus as taskContractStatus,
    getElement(w.workOrderTaskId),
    e.elementName, false as Expanded, false as hasChildren
    from workOrderTask w
    LEFT JOIN task t on w.taskId=t.taskId

    LEFT JOIN element e on w.parentTaskId=e.elementId
    WHERE w.workOrderId=".$workOrder->getWorkOrderId()." AND w.parentTaskId is not null and w.internalTaskStatus=5 and w.invoiceId=".$invoiceId;

} else {

    $query = "SELECT elementId as id, elementName as Title, null as parentId, elementName as billingDescription,
    null as taskId, null as parentTaskId, null as workOrderTaskId,
    null as totCost, null as taskTypeId, null as taskStatusId, 0 as taskContractStatus,
    elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
    from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrder->getWorkOrderId().")
    UNION ALL
    SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.billingDescription, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
    w.totCost as totCost,
    w.taskTypeId as taskTypeId, w.taskStatusId as taskStatusId, w.taskContractStatus as taskContractStatus,
    getElement(w.workOrderTaskId),
    e.elementName, false as Expanded, false as hasChildren
    from workOrderTask w
    LEFT JOIN task t on w.taskId=t.taskId

    LEFT JOIN element e on w.parentTaskId=e.elementId
    WHERE w.workOrderId=".$workOrder->getWorkOrderId()." AND w.parentTaskId is not null and w.internalTaskStatus=0";
}
*/

$invoice->getInvoiceTotal();
$wdata=$invoice->getData();

//$res=$db->query($query);

$out2=($wdata[4]);
$parents=[];
$elements=[];

/*
while( $row=$res->fetch_assoc() ) {
    $out2[]=$row;
    if( $row['parentId']!=null ) {
        $parents[$row['parentId']]=1;
    }
    if( $row['taskId']==null)    {
        $elements[$row['elementId']] = $row['elementName'] ;


    }

}
*/

for( $i=0; $i<count($out2); $i++ ) {

    if($out2[$i]['parentId']!=null){
        $parents[$out2[$i]['parentId']]=1;
    }
    if( $out2[$i]['taskId']==null)    {
        $elements[$out2[$i]['elementId']] = $out2[$i]['elementName'] ;
    }
    if( $out2[$i]['Expanded'] == 1 )
    {
        $out2[$i]['Expanded'] = true;
    } else {
        $out2[$i]['Expanded'] = false;
    }

    if($out2[$i]['hasChildren'] == 1)
    {
        $out2[$i]['hasChildren'] = true;
    } else {
        $out2[$i]['hasChildren'] = false;
    }

    if( isset($parents[$out2[$i]['id']]) ) {
        $out2[$i]['hasChildren'] = true;

    }
    if ( $out2[$i]['elementName'] == null ) {
        $out2[$i]['elementName']=(isset($elements[$out2[$i]['elementId']])?$elements[$out2[$i]['elementId']]:"");
    }



}
//print_r($elements);
//print_r($parents);


$out2=$wdata[4] ;

$sumEstimatedTasks=0;
$elementsCost = [];
$estimatedTasks=array();

foreach($out2 as $value) {
    $elementsCost[$value['elementId']][] = $value['totCost'] ;
    if($value['taskTypeId'] == ESTIMATED_TASKS_CODE && !$value['hasChildren']){
        $sumEstimatedTasks+=$value['totCost'];
        $tmp=[];
        $tmp['id']=$value['id'];
        $tmp['parentId']=$value['parentId'];
        $tmp['billingDescription']=$value['billingDescription'];
        $tmp['value']=$value['totCost'];
        $estimatedTasks[]=$tmp;
    }
}


//echo $sumEstimatedTasks;


$sumTotalEl = 0;
foreach($elementsCost as $key=>$el) {
     $elementsCost[$key] = array_sum($el);
     $sumTotalEl += array_sum($el);
}

// Get task types: overhead, fixed etc.
$allTaskTypes = array();
$allTaskTypes = getTaskTypes();


$elementTasks = array();
/*$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $elementTasks[] = $row;

    }
} */
$elementTasks=$wdata[4];
array_multisort(array_column($elementTasks, 'elementName'), SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $elementTasks);

/*
ksort_recursive($out2);

function ksort_recursive(&$out2) {
    ksort($out2);
    foreach ( $out2 as &$a ) {
        is_array($a) && ksort_recursive($a);
    }
}

$price = array_column($out2, 'elementName');

array_multisort($price, SORT_DESC, $out2);*/






    // New Code George.
    // Need to arange on the task.
    $newPack2 = array();
    $allTasksPack2 = array();
    foreach ($elementTasks as $a) {
        $newPack2[$a['parentId']][] = $a;
    }
if(!function_exists("createTreePack2")) {
    function createTreePack2(&$listPack3, $parent) {
        $tree = array();

        foreach ($parent as $k=>$l ) {

            if(isset($listPack3[$l['workOrderTaskId']]) ) {

                $l['items'] = createTreePack2($listPack3, $listPack3[$l['workOrderTaskId']]);

           }

            $tree[] = $l;

        }

        return $tree;
    }
}

    foreach($elementTasks as $key=>$value) {

        if( $value["parentId"] == $value["elementId"] ) {

            $createAllTasks3 = createTreePack2($newPack2, array($elementTasks[$key]));



            $found = false;
            foreach($allTasksPack2 as $k=>$v) {

                $tree = array();
                if($v['elementId'] == $value['parentId']) {
                    $allTasksPack2[$k]['items'][] = $createAllTasks3[0];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $node = array();

                $node['items'][] = $createAllTasks3[0];
                $node['elementId'] = $value['elementId'];
                $node['Title'] = $value['elementName'];

                $allTasksPack2[] = $node;

            }
        } else if ($value["parentId"] == null ) {
            $node2 = array();
            $node2['elementId'] = $value['elementId'];
            $node2['Title'] = $value['elementName'];
            $allTasksPack2[] = $node2;
        }


    }
if(!function_exists("calcLevel")) {
function calcLevel($inArray, $level){
    global $totElement;

    $level++;

    if($inArray===null){
        return;
    }

    foreach($inArray as $elem){

        if ($elem['totCost']>0 && $elem['taskContractStatus']==1){
            if($level==1) {
             $totElement=$elem['totCost'];
            }
            if(isset($elem['items']) && $elem['taskContractStatus']==1){

                $child=$elem['items'];
                calcLevel($child, $level);
            }
        }

    }

}
}
foreach($allTasksPack2 as $key=>$elementTasks){
    if(!isset($elementTasks['items'])){
        unset($allTasksPack2[$key]);
    }
}

$allTasksPack2=array_merge($allTasksPack2);
//print_r($allTasksPack2);

$elemTots=array();
$totElement=0;
$totGeneral=0;
foreach($allTasksPack2 as $elementTasks){
/*    if(isset($elementTasks['items'])){
        $elemName=$elementTasks['Title'];
        $totElement=0;
        $children=$elementTasks['items'];
        calcLevel($children, 0);
        $tmp=array();
        $tmp['name']=strtolower($elemName);
        $tmp['value']=$totElement;
        //$elemTots[]=$tmp;
    }*/
    $key = array_search($elementTasks['elementId'], array_column($out2, 'id'));

    if($key!==false){
        $tmp=array();
        $tmp['name']=strtolower($out2[$key]['Title']);
        $tmp['value']=$out2[$key]['totCost'];
        $elemTots[]=$tmp;
    }
}

$totGeneral=0;
foreach($elemTots as $value){
    $totGeneral+=$value['value'];
}


if(!function_exists("printLevel")) {
function printLevel($inArray, $level){
    global $fontSize, $fontBold, $pdf, $totElement;

    $level++;

    $fontBold=false;
    $dimFont=$fontSize-$level;
    $posX=($level<=2?$level*10+15:35);
    $posY=$pdf->GetY()+5;


    if($inArray===null){
        return;
    }

    //print_r($inArray);

    foreach($inArray as $elem){

    //$pdf->MultiCell(12, 5, "aaa", 1, 'L', false);
        if ($elem['totCost']>0 && $elem['taskContractStatus']==9){
            $text = ucfirst(strtolower(trim( $elem['billingDescription']!=""?$elem['billingDescription']:$elem['Title'])));

            $pdf->SetFont('Tahoma', ($fontBold?'B':''), $dimFont);

            $pdf->SetXY($posX, $pdf->GetY()+1);
            $posY=$pdf->GetY();
            $pdf->MultiCell(150-$level*10, 5, $text, 0, 'L', false);

            $newPosY=$pdf->GetY();
            $pdf->SetXY(RIGHT_MARGIN-55-($level-1)*5, $posY);

            $pdf->MultiCell(25, 5, "$".number_format($elem['totCost'], 2), 0,'R', false);
            $pdf->SetY($newPosY);

            continue;

        }

        if ($elem['totCost']>0 && $elem['taskContractStatus']==1){

            $text = ucfirst(strtolower(trim( $elem['billingDescription']!=""?$elem['billingDescription']:$elem['Title'])));

            $pdf->SetFont('Tahoma', ($fontBold?'B':''), $dimFont);

            $pdf->SetXY($posX, $pdf->GetY()+1);
            $posY=$pdf->GetY();
            $pdf->MultiCell(150-$level*10, 5, $text, 0, 'L', false);

            $newPosY=$pdf->GetY();
            $pdf->SetXY(RIGHT_MARGIN-55-($level-1)*5, $posY);

            $pdf->MultiCell(25, 5, "$".number_format($elem['totCost'], 2), 0,'R', false);
            $pdf->SetY($newPosY);

            if(isset($elem['items']) && $elem['taskContractStatus']==1){
                $totElement=$elem['totCost'];
                $child=$elem['items'];
                printLevel($child, $level);
            }
        }

    }

}
}

if(!function_exists("findElementFromTask")) {
function findElementFromTask($taskId)
{
    global $out3;
    $i=0;
    while($taskId!="" && $i<5){
        if($out3[$taskId]['parentId']==""){
            break;
        } else {
            $taskId=$out3[$taskId]['parentId'];
        }
        $i++;
    }
    return $out3[$taskId]['billingDescription'];
}
}



$fontBold=true;
$fontSize=13;
$totElement=0;
//$totGeneral=0;
//print_r($allTasksPack2);
//print_r($elemTots);

foreach($allTasksPack2 as $elementTasks){

    $outputArray['Title']=$elementTasks['Title'];

    $text = ucfirst(strtolower(trim( $elementTasks['Title'])));

    $key = array_search(strtolower($text), array_column($elemTots, 'name'));

    if($elemTots[$key]['value']>0){

        $fontBold=true;
        $pdf->SetFont('Tahoma', ($fontBold?'B':''), $fontSize);
        $pdf->SetX(15);
        $pdf->SetY($pdf->getY()+15);
        $pdf->Cell(130, -2.5, $text, 0, 0, 'L');

        if(CONTRACT_ELEMENT_TOTAL_POSITION  == 1){
            $key = array_search(strtolower($text), array_column($elemTots, 'name'));

            $pdf->SetXY(RIGHT_MARGIN-75, $pdf->GetY()-3.8);

            $pdf->SetFont('Tahoma', 'B', 13);
            $pdf->MultiCell(45, 5, "$".number_format($elemTots[$key]['value'], 2), 0, 'R', false);
            $pdf->Line(LEFT_MARGIN, $pdf->GetY(), RIGHT_EDGE, $pdf->GetY()); // [Martin comment:] 20mm from each edge


        }

        $totElement=0;
        $children=$elementTasks['items'];
//echo "aa"."\n";
  //print_r($children);
//        foreach($children as $child){
            printLevel($children, 0);
//        }


        if(CONTRACT_ELEMENT_TOTAL_POSITION  == 2){
            $pdf->SetXY(RIGHT_MARGIN-75, $pdf->GetY()+2);

            $pdf->SetFont('Tahoma', 'B', 13);
            $pdf->MultiCell(45, 5, "$".number_format($totElement, 2), 0, 'R', false);
        }
        //$totGeneral+=$totElement;
        $pdf->ln(1.2);
    }
}


    $pdf->SetXY(RIGHT_MARGIN-135, $pdf->GetY()+7);

    $pdf->SetFont('Tahoma', 'B', 13);

    $pdf->MultiCell(105, 5, "Total:  $".number_format($totGeneral, 2), 0, 'R', false);

//echo $pdf->GetY();

//    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY()) ;

$afterTotal=$pdf->GetY();
$pdf->ln(1.2);


$out3=[];
foreach($out2 as $val){
    $out3[$val['id']]=$val;
}
//---------------------------------------------------------------------

    $pdf->ln(5);
    $pdf->setDocumentType('');  // [BEGIN Martin comment]
                                // setting this here so that the initials thing doesnt
                                // on this page.  just the ones preceeding it
                                // [END Martin comment]

    // ----------- WorkOrder name -----------
    $n = $workOrder->getNameWithoutType();
    if (strlen($n)){
        $n = 'Work Order: ' . $n;
    } else {
        $n = 'SCOPE OF SERVICES';
    }

    $pdf->SetY(PAGE_HEIGHT/3 +1);
    $pdf->SetFont('Tahoma', 'B', 10);
    $pdf->setX(10);
    $pdf->cell(10, 5, $n, 0, 'R', '');

    $pdf->ln(7);
    $baseSize = 8;

    // From here down: logic around passes/iterations is significantly reworked 2020-01-16 JM, mainly for clarity.
    // The changes are not indicated, because this was quite a restructure; use history in SVN if you really need
    // to know.
    $pass_begin = PASS_CONTRACT;
    $pass_end = PASS_NONCONTRACT;
    if ($noncontractcount == 0) {
        $pass_end = PASS_CONTRACT;
    }
    if ($contractcount == 0) {
        $pass_begin = PASS_NONCONTRACT;
    }

    $db = DB::getInstance();

    $textOverride = '';

    $query =  "SELECT textOverride FROM " . DB__NEW_DATABASE . ".invoice ";
    $query .= "WHERE invoiceId = " . $invoice->getInvoiceId() . ";";

    $result = $db->query($query);
    if ($result) {
        // if ($result->num_rows > 0){ // No need for this test 2020-09-02 JM
            while ($row = $result->fetch_assoc()){
                $textOverride = $row['textOverride'];
            }
        // }
    } else {
        $logger->errorDb('1599077662', 'Hard DB error seeking textOverride for invoice', $db);
    }

    $textOverride = trim($textOverride);

    // if (strlen($textOverride)) { // REPLACED 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121, just simplify here.
//$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY()) ;

/* comment by Cristi


   if ($textOverride) { // REPLACEMENT 2020-04-20 JM
        // This is a rare case. Idea of textOverride here is to be able to write pretty much
        //  any content into the invoice. A "band-aid". Overrides the whole portion about
        //  what's being billed.

        $pdf->SetFont('Tahoma','',$baseSize);
        $total_string_width = $pdf->GetStringWidth($textOverride);

        $pdf->SetFont('Tahoma','',$baseSize);

        $pdf->MultiCell(0,5,$textOverride,0,'L');

        // >>>00007 We calculate $number_of_lines and $height_of_cell, but we don't seem
        // to do anything with them.
        $number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
        $number_of_lines = ceil( $number_of_lines );  // Round it up.

        $height_of_cell = $number_of_lines * 5;
        $height_of_cell = ceil( $height_of_cell );    // Round it up.
    }
    END  comment by Cristi
*/
    // else { // REMOVED 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121

    ///////////////////////////////////////////////////////////////////////////////////
    // BEGIN element groups (actual task content)
    ///////////////////////////////////////////////////////////////////////////////////

        $contractHeaderHandled = false; // this is global (once per invoice)
        $nonContractHeaderHandled = false; // "nonContractHeader" is "Additional Services"; moved here 2020-08-21 JM per discussion with Ron & Damon today
        for ($pass = $pass_begin; $pass <= $pass_end; ++$pass) {
            foreach ($final as $elementgroup) {
                // BEGIN ADDED 2020-08-21 JM, working on http://bt.dev2.ssseng.com/view.php?id=190
                $passTotal = 0; // currently relevant only for $pass == PASS_NONCONTRACT. It is possible that an equivalent of this is already calculated
                                // somewhere above, but I think not. Idea is to be able to present a section subtotal for each element
                // END ADDED 2020-08-21 JM

                // $nonContractHeaderHandled = false; // 2020-08-21 JM: REMOVED per discussion with Ron & Damon today: they only wat this once, not per-element.
                if (!$textOverride && $pass == PASS_CONTRACT && !$contractHeaderHandled) { // ADDED !$textOverride to test 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121, just simplify here.
                    //  write the contract date & contract total.
                    $cd = date("n/j/Y", strtotime($contract->getCommittedTime())); // NOTE that if no contract, or contract is not committed,
                                                                                   // this will be null
                    $pdf->setX(LEFT_MARGIN);
                    $pdf->SetFont('Tahoma','',$baseSize);
                    $pdf->MultiCell(0, 5, "Contract dated " . $cd, 0, 'L');

                    $disp = '$' . number_format($contractTotal, 2);
                    $string_width = $pdf->GetStringWidth($disp);
                    $pdf->setY($pdf->GetY()-5);
                    $pdf->setX(RIGHT_EDGE-$string_width-5);
                    $pdf->Cell(0, 5, $disp, 0,0,'R');
                    $pdf->setY($pdf->GetY()+5); // Added 2020-02-21 JM, based on observation

                    $contractHeaderHandled = true;
                }

                // Do we have anything noncontract for this element/elementgroup?
                // Anything noncontract?
                $hasNoncontractTasks = false;
                $hasContractTasks = false;

                if (isset($elementgroup['tasks'])) {
                    if (count($elementgroup['tasks']) > 0) {
                        foreach ($elementgroup['tasks'] as $task) {
                            /* BEGIN REPLACED 2020-09-02 JM
                            if (isset($task['task']['fromContract'])) {
                                if (intval($task['task']['fromContract'])) {
                                    $hasContractTasks = true;
                                } else {
                                    $hasNoncontractTasks = true;
                                }
                            }
                            // END REPLACED 2020-09-02 JM
                            */
                            // BEGIN REPLACEMENT 2020-09-02 JM
                            if ($contract) { // if there is a contract
                                if (isset($task['task']['fromContract']) && intval($task['task']['fromContract'])) {
                                    $hasContractTasks = true;
                                } else {
                                    $hasNoncontractTasks = true;
                                }
                            } else {
                                $hasNoncontractTasks = true;
                            }
                            // END REPLACEMENT 2020-09-02 JM
                        }
                    }
                }

                /* BEGIN REPLACED 2020-09-21 JM to address bt.dev2.ssseng.com/view.php?id=194#c1099
                if ($pass == PASS_NONCONTRACT && $hasNoncontractTasks ) {
                // END REPLACED 2020-09-21 JM
                */
                // BEGIN REPLACEMENT 2020-09-21 JM
                if ( $elementgroup['elementNoncontractTotal'] && $pass == PASS_NONCONTRACT && $hasNoncontractTasks ) {
                // END REPLACEMENT 2020-09-21 JM

                    // If some tasks for this element were marked as being per contract, and now we are about to start into those that are not...
                    if ($pass_begin == PASS_CONTRACT && $hasContractTasks && !$nonContractHeaderHandled) {
                        if (!$textOverride) { // ADDED !$textOverride test 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121, just simplify here.
                            $pdf->setX(LEFT_MARGIN);
                            $pdf->SetFont('Tahoma', 'B', $baseSize+2);
                            $pdf->MultiCell(0, 5, "Additional Services", 0, 'L');
                        }

                        $nonContractHeaderHandled = true;
                    }

                    // write the elementName
                    if (!$textOverride) { // ADDED !$textOverride test 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121, just simplify here.
                        $pdf->setX(LEFT_MARGIN);
                        $pdf->SetFont('Tahoma', 'B', $baseSize);
                        // $pdf->SetFont('Tahoma', '', 12); // This was what Ron requested instead at some point in February 2020, but Damon said
                        //   to go back to the old $pdf->SetFont('Tahoma', 'B', $baseSize);
                        $pdf->MultiCell(0, 5, $elementgroup['elementName'], 0,'L');

                        $pdf->SetFont('Tahoma', '', $baseSize);
                    }

                    // Now for each task within the elementgroup...

                    // putTaskArrayInCanonicalOrder() introduced in the following line 2020-11-12 JM to address
                    //  http://bt.dev2.ssseng.com/view.php?id=256 (Order of tasks within an element of a WorkOrder should be consistent).
                    //  We force the display to correspond to the current task hierarchy, independent of what the task hierarchy may have
                    //  been when this invoice was created.
//                    foreach (putTaskArrayInCanonicalOrder($elementgroup['tasks']) as $task) {
                    foreach (orderwotArray($elementgroup['tasks']) as $task) {
                        // [BEGIN Martin comment]
                        //  [typeName] => overhead
                        //  [price] => $0.00
                        //  [quantity] => 1
                        //  [cost] => $0.00
                        // [END Martin comment]

                        /* BEGIN REPLACED 2020-09-02 JM
                        $fromContract = false;
                        if (isset($task['task']['fromContract'])) {
                            if (intval($task['task']['fromContract'])) {
                                $fromContract = true;
                            }
                        }
                        // END REPLACED 2020-09-02 JM
                        */
                        // BEGIN REPLACEMENT 2020-09-02 JM
                        $fromContract = $contract &&  isset($task['task']['fromContract']) && intval($task['task']['fromContract']);
                        // END REPLACEMENT 2020-09-02 JM

                        // In the condition here:
                        //  ADDED !$textOverride test 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121, just simplify here.
                        //  Made an exception for $task['showWithoutNumbers'] 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
                        if (!$textOverride && (
                             ($pass == PASS_CONTRACT && $fromContract) ||
                             ($pass == PASS_NONCONTRACT && !$fromContract) ||
                             ($pass == PASS_NONCONTRACT && $task['showWithoutNumbers'])
                           ) )
                        {
                            $shown = false; // $shown ADDED 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
                            if (isset($task['cost'])) {
                                $cost = &$task['cost'];  // This was assignment before 2020-01-16, JM made it a reference/alias instead
                                if ($cost['cost'] != '$0.00') {
                                    // item cost, quantity, price (product)
                                    $pdf->SetFont('Tahoma', '', $baseSize - 1);

                                    $pdf->setX(RIGHT_EDGE - 25);
                                    $pdf->cell(25, 5, $cost['cost'], 0, 'R', 'R');

                                    // BEGIN ADDED 2020-08-21 JM, working on http://bt.dev2.ssseng.com/view.php?id=190
                                    $adder = preg_replace("/[^0-9.]/", "", $cost['cost']); // >>>00002: Not a great test, but it will be fine if $cost['cost'] is fine
                                    if (is_numeric($adder)) {
                                        $passTotal += $adder;
                                    }
                                    unset($adder); // this line added 2020-09-02 JM
                                    // END ADDED 2020-08-21 JM

                                    $pdf->setX(RIGHT_EDGE - 50);
                                    $pdf->cell(25, 5, $cost['quantity'], 0, 'R', 'R');

                                    $pdf->setX(RIGHT_EDGE - 75);
                                    $pdf->cell(25, 5, $cost['price'], 0, 'R', 'R');

                                    $nte = $task['nte'];

                                    $pdf->setX(RIGHT_EDGE - 110);
                                    $pdf->cell(35, 5, '', 0, 'R', 'R');

                                    $pdf->setX(LEFT_MARGIN + (intval($task['level']) * 2)); // for indentation, show task hierarchy

                                    // billing description; might wrap to multiple lines
                                    $text = $task['task']['billingDescription'];
                                    $pdf->SetFont('Tahoma','',$baseSize - 1);
                                    $total_string_width = $pdf->GetStringWidth($text);
                                    $pdf->SetFont('Tahoma','',$baseSize - 1);
                                    $pdf->MultiCell(90, 5, $text, 0, 'L');

                                    // >>>00007 We calculate $number_of_lines and $height_of_cell, but we don't seem
                                    // to do anything with them.
                                    $number_of_lines = ($total_string_width - 1) / EFFECTIVE_WIDTH;
                                    $number_of_lines = ceil( $number_of_lines );  // [Martin comment:] Round it up.

                                    $height_of_cell = $number_of_lines * 5;
                                    $height_of_cell = ceil( $height_of_cell );    // [Martin comment:] Round it up.

                                    if (intval($nte['cost'])) {
                                        // These columns are filled right-to-left, on the same line just used.
                                        // >>>00001 should examine exactly how they are positioned relative to the ones already
                                        //  in the PDF, describe just how the line is modified by this.

                                        $disp = '$' . number_format($nte['cost'], 2);
                                        $pdf->setX(RIGHT_EDGE - 22);
                                        $pdf->SetFont('Helvetica', 'I', $baseSize - 1);
                                        $pdf->cell(22, 5, $disp, 0, 'R', 'R');

                                        $pdf->setX(RIGHT_EDGE - 38);
                                        $pdf->SetFont('Helvetica', 'I', $baseSize - 1 );
                                        $pdf->cell(13, 5, $nte['nte'], 0, 'R', 'R');

                                        $pdf->setX(RIGHT_EDGE - 47);
                                        $pdf->SetFont('Helvetica', 'I', $baseSize - 1);
                                        $pdf->cell(10, 5, 'NTE:', 0, 'R', 'R');

                                        $pdf->setY($pdf->GetY() + 5);
                                        $pdf->SetFont('Tahoma', '', $baseSize - 1);
                                    } // END if (intval($nte['cost']))
                                } // END if ($cost['cost'] != '$0.00')
                            } // END if (isset($task['cost']))
                            // BEGIN ADDED 2020-09-15 JM to address http://bt.dev2.ssseng.com/view.php?id=194#c1078
                            // There might be a way to do this better with common code: it is a very partial subset
                            //  of the case above
                            if ($pass == PASS_NONCONTRACT && $task['showWithoutNumbers'] && !$shown) {
                                $pdf->SetFont('Tahoma', '', $baseSize - 1);
                                $pdf->setX(RIGHT_EDGE - 110);
                                $pdf->cell(35, 5, '', 0, 'R', 'R');

                                $pdf->setX(LEFT_MARGIN + (intval($task['level']) * 2)); // for indentation, show task hierarchy

                                // billing description; might wrap to multiple lines
                                $text = $task['task']['billingDescription'];
                                $pdf->SetFont('Tahoma', '', $baseSize - 1);
                                $total_string_width = $pdf->GetStringWidth($text);
                                $pdf->SetFont('Tahoma', '', $baseSize - 1);
                                $pdf->MultiCell(90, 5, $text, 0, 'L');
                                /*
                                $pdf->setY($pdf->GetY() + 5);
                                $pdf->SetFont('Tahoma', '', $baseSize - 1);
                                */
                            }
                            unset($shown);
                            // END ADDED 2020-09-15 JM
                        } // END for a task we actually want to display
                    } // END foreach (putTaskArrayInCanonicalOrder($elementgroup['tasks']) as $task)

                    if (!$textOverride) { // ADDED !$textOverride test 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121, just simplify here.
                        $pdf->SetY($pdf->GetY() + 5);
                        $nte = $elementgroup['elementNTE'];

                        if (is_numeric($nte)) {
                            if ($nte != $elementgroup['elementTotal']) {
                                // NTE differs from the total, write it.
                                $pdf->SetFont('Tahoma', 'B', $baseSize);

                                $disp = '$' . number_format($nte, 2);
                                $total_string_width = $pdf->GetStringWidth($disp);
                                $pdf->SetFont('Helvetica', 'I', $baseSize - 1);

                                $pdf->setX(RIGHT_EDGE - $total_string_width);

                                $pdf->cell(0, 5, $disp, 0, 0, 'R');

                                $pdf->setX(LEFT_MARGIN + 5);
                                $pdf->SetFont('Helvetica', 'I', $baseSize - 1);
                                $pdf->MultiCell(EFFECTIVE_WIDTH - 30, 5, 'NTE:', 0,'R');
                            } // END NTE differs from $elementgroup['elementTotal']
                        } // END has NTE
                    } // END if (!$textOverride)

                    // BEGIN ADDED 2020-08-21 JM, working on http://bt.dev2.ssseng.com/view.php?id=190; modeled loosely on how we display NTE
                    $pdf->SetFont('Tahoma', 'B', $baseSize);
                    $disp = '$' . number_format($passTotal, 2);
                    $total_string_width = $pdf->GetStringWidth($disp);
                    $pdf->SetFont('Helvetica', 'B', $baseSize - 1);

                    $pdf->setX(RIGHT_EDGE - $total_string_width);
                    $pdf->cell(0, 5, $disp, 0, 0, 'R');

                    $pdf->setX(LEFT_MARGIN + 5);
                    $pdf->SetFont('Helvetica', 'B', $baseSize - 1);
                    $pdf->MultiCell(EFFECTIVE_WIDTH - 30, 5, "Section subtotal:", 0,'R');
                    // END ADDED 2020-08-21 JM
                } // END if ($pass == PASS_NONCONTRACT && $hasNoncontractTasks)
            } // END foreach ($final...
        } // END for ($pass = $pass_begin; $pass <= $pass_end; ++$pass)
        unset($hasNoncontractTasks, $hasContractTasks);
        unset($pass, $contractHeaderHandled, $nonContractHeaderHandled, $passTotal); // ADDED 2020-09-02 JM

        $pdf->SetFont('Tahoma', 'B', 12);

        // If there is an overridden total, then trust it. Otherwise, use $grandTotal. Either way, display it.
        // This is a total before adjustments.
        $actualTotal = 0;
        $override = (is_numeric($invoice->getTotalOverride())) ? number_format($invoice->getTotalOverride(), 2) : '';
        if (is_numeric($override)){
            if ($override > 0){
                $actualTotal = $override;
            } else {
                $actualTotal = $grandTotal;
            }
        } else {
            $actualTotal = $grandTotal;
        }
        $disp = '$' . number_format($actualTotal,2);

        // ... and caption it as "TOTAL"
        $total_string_width = $pdf->GetStringWidth($disp);
        $pdf->setX(RIGHT_EDGE - $total_string_width);
        //$pdf->cell(0, 5, $disp ,0,0,'R');
        $pdf->setX(RIGHT_EDGE - $total_string_width - 75);
        //$pdf->cell(70, 5, 'TOTAL:' ,0,2,'R');

        // ---------------- Adjustments --------------
        /* There are three types of adjustments:

            INVOICEADJUST_DISCOUNT (displayed as 'Discount')
                increases $total by specified amount, so any actual discount should be represented by a negative value.
            INVOICEADJUST_QUICKBOOKSSHIT (displayed as 'Discount')
                increases $total by specified amount.
            INVOICEADJUST_PERCENTDISCOUNT (displayed as 'Percent Discount')
                decreases $total by specified percentage (still called 'amount').
        */
        $adjustTypes = array();
        $db = DB::getInstance();
        $query = " select * from " . DB__NEW_DATABASE . ".invoiceAdjustType order by invoiceAdjustTypeId "; // order is effectively chronological

        if ($result = $db->query($query)) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $adjustTypes[] = $row;
                }
            }
        }

        $pdf->ln(1);
        $pdf->SetFont('Tahoma','',12);

        $adjustments = $invoice->getAdjustments();

        $total = $invoice->getTotal();

        foreach ($adjustments as $adjustment) {
            if ($adjustment['invoiceAdjustTypeId'] == INVOICEADJUST_DISCOUNT) {
                $disp = 'Discount';
                $amt = '$' . number_format($adjustment['amount'],2);
                if (is_numeric($actualTotal)) {
                    $actualTotal = $actualTotal +   $adjustment['amount'];
                }
            } if ($adjustment['invoiceAdjustTypeId'] == INVOICEADJUST_QUICKBOOKSSHIT) {
                $disp = 'Discount';
                $amt = '$' . number_format($adjustment['amount'],2);
                if (is_numeric($actualTotal)) {
                    $actualTotal = $actualTotal +   $adjustment['amount'];
                }
            } else if ($adjustment['invoiceAdjustTypeId'] == INVOICEADJUST_PERCENTDISCOUNT) {
                $disp = 'Percent Discount';
                $amt = $adjustment['amount'] . '%';
                if (is_numeric($total)) {
                    $actualTotal = $actualTotal  * ((100 - $adjustment['amount'])/100);
                }
            }

            $noteDisp = '';
            $note = $adjustment['invoiceAdjustNote'];
            $note = trim($note);
            if (strlen($note)) {
                $noteDisp = ' (' . $note . ')';
            }
//echo $afterTotal;
//echo $pdf->GetY();
//die();
$pdf->SetY($afterTotal);
            $pdf->setX(PAGE_WIDTH - 105);
            $pdf->cell(70, 5, $disp . $noteDisp, 0, 0, 'R');
            $pdf->cell(20, 5, $amt, 0, 2, 'R');
            $afterTotal+=5;
        }

        // If there are any adjustments, we now need to display an adjusted total.
        if (count($adjustments)) {
            $pdf->SetFont('Tahoma', 'B', 12);
            $disp = '$' . number_format($actualTotal, 2);
            $total_string_width = $pdf->GetStringWidth($disp);
            $pdf->setX(RIGHT_EDGE - $total_string_width);
            $pdf->cell(0, 5, $disp, 0, 0, 'R');
            $pdf->setX(RIGHT_EDGE - $total_string_width - 75);
            $pdf->cell(70, 5, 'INVOICE TOTAL:', 0, 2, 'R');
            $afterTotal+=4;
        }

    ///////////////////////////////////////////////////////////////////////////////////
    // END element groups (actual task content)
    ///////////////////////////////////////////////////////////////////////////////////
    // } // REMOVED 2020-04-20 JM, fixing bug http://bt.dev2.ssseng.com/view.php?id=121

    // BEGIN ADDED 2020-01-07 JM for http://bt.dev2.ssseng.com/view.php?id=62
    $balance = $actualTotal;
    $payments = $invoice->getPayments();
//$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY()) ;
    foreach ($payments as $payment) {
        $payment_amount = $payment['amount'];
        $balance -= $payment_amount;

        $pdf->SetFont('Tahoma', '', $baseSize);
        $disp_payment_amount = 'Payment (ID'. $payment['creditRecordId']. ') received ' . $payment['inserted'] .': $' . number_format($payment_amount, 2);
        $disp_balance = '$' . number_format($balance, 2);
        $balance_string_width = $pdf->GetStringWidth($disp_balance);
        $pdf->setX(LEFT_MARGIN);
        $pdf->cell(0, 5, $disp_payment_amount, 0, 0, 'L');
        $pdf->setX(RIGHT_EDGE - $balance_string_width);
        $pdf->cell(0, 5, $disp_balance, 0, 0, 'R');
        $pdf->setX(RIGHT_EDGE - $balance_string_width - 75);
        $pdf->cell(70, 5, 'Remaining balance:', 0, 2, 'R');
    }
    // END ADDED 2020-01-07 JM

   // ***************************** under total ****************************
   
    // -------- Terms --------
    if (intval($invoice->getTermsId())) {
        /* BEGIN REPLACED 2020-01-13 JM
        $query = " select * ". // select * here really could be just select description, that's all we use.
        // END REPLACED 2020-01-13 JM
        */
        // BEGIN REPLACEMENT 2020-01-13 JM
        $query = " select description ".
        // END REPLACEMENT 2020-01-13 JM
                   "from " . DB__NEW_DATABASE . ".terms where termsId = " . intval($invoice->getTermsId());
        if ($result = $db->query($query)) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $text = $row['description'] . ' Please include this invoice number (#' . $invoice->getInvoiceId() . ') with your payment.';
                $pdf->SetFont('Tahoma','',11);
                $total_string_width = $pdf->GetStringWidth($text);

                $pdf->SetXY(PAGE_WIDTH - 2 - 90, $afterTotal);
                $pdf->SetFont('Tahoma','',9);
                $pdf->MultiCell(90,5,$text,0,'L');

                //echo $pdf->GetY()."\n";
            }
        }
    }

    // -------- Files --------
    // just a list of filenames

    // BEGIN reworked 2020-10-29 JM to address http://bt.dev2.ssseng.com/view.php?id=263 (Uploaded files are not listing on the Invoice)
    $fileNames = array();

    // The old style, where filenames (no actual files!) are attached to the invoice.
    $query =  "SELECT * FROM " . DB__NEW_DATABASE . ".invoiceFile ";
    $query .= "WHERE invoiceId = " . $invoice->getInvoiceId() . ";";

    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()){
            $fileNames[] = $row['fileName'];
        }
    } else {
        $logger->errorDb('1603990277', "Hard DB error", $db);
    }

    // The new style added in v2020-4, where actual files are attached to the workOrder
    $query =  "SELECT * FROM " . DB__NEW_DATABASE . ".workOrderFile ";
    $query .= "WHERE workOrderId = " . $invoice->getWorkOrderId() . ";";
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()){
            $fileNames[] = $row['origFileName'];
        }
    } else {
        $logger->errorDb('1603991089', "Hard DB error", $db);
    }


    if (count($fileNames)) {
        $pdf->ln(2);
        $pdf->SetFont('Tahoma','B',$baseSize);
        $pdf->setX(LEFT_MARGIN);
        $pdf->cell(0, 5, 'Documents Created:', 0, 1, 'L');

        $pdf->SetFont('Tahoma','',$baseSize - 1);
        foreach ($fileNames as $fileName){
            $pdf->setX(LEFT_MARGIN);
            $pdf->cell(0, 5, $fileName, 0, 1, 'L');
        }

        $pdf->ln(5);
    }
    // END reworked 2020-10-29 JM to address http://bt.dev2.ssseng.com/view.php?id=263 (Uploaded files are not listing on the Invoice)

    $pdf->setY($pdf->getY()+5);
    $y = $pdf->getY();
    // Effectively, mark a line ONLY IN MARGINS (though we do this independent of our margins as such).
    //$pdf->Line(0, $y, $len + 5, $y);
    //$pdf->Line(PAGE_WIDTH - $len - 5, $y, PAGE_WIDTH, $y);

    // ---- Invoice notes
    $invoiceNotes = $invoice->getInvoiceNotes();
    $invoiceNotes = trim($invoiceNotes);

    if (strlen($invoiceNotes)) {
        $pdf->Ln(25);
        $pdf->SetLeftMargin(15);
        $pdf->SetRightMargin(15);

        $pdf->SetFont('Tahoma', '', 11);
        $pdf->MultiCell(0, 5, $invoiceNotes, 0, 'L');
    }

    // -------- Customer (as of 2019-03, always SSS) --------
    $pdf->SetY(PAGE_HEIGHT - 27);
    $pdf->setX(10);
    $pdf->SetFont('Tahoma','',10);
                    $pdf->setDoFooter(false);

    /* E.g.
    'Sound Structural Solutions, Inc.'
    'EIN# 20-2955014'
    'ssseng.com'
    '(425)778 1023'
    */
    // BEGIN NEW CODE 2019-02-06 JM
    $pdf->cell((PAGE_WIDTH - 20)/2, 5, CUSTOMER_FULL_NAME, 0,2,'L');
    $pdf->cell((PAGE_WIDTH - 20)/2, 5, 'EIN# '.CUSTOMER_EMPLOYER_IDENTIFICATION_NUMBER, 0,2,'L');

    $pdf->cell((PAGE_WIDTH - 20)/2, 5, CUSTOMER_DOMAIN_MINIMAL, 0,2,'L');  // CUSTOMER_DOMAIN_MINIMAL => no http, etc. e.g. ssseng.com
    $pdf->cell((PAGE_WIDTH - 20)/2, 5, CUSTOMER_PHONE_USING_PARENS, 0,2,'L'); // e.g. (425)778 1023
    // END NEW CODE 2019-02-06 JM

    $pdf->SetY(PAGE_HEIGHT - 27);
    $pdf->SetX(105);

    /* OLD CODE removed 2019-02-06 JM
    $text = "You can pay online by clicking the PayPal link at ssseng.com";
    */
    // BEGIN NEW CODE 2019-02-06 JM
    $text = "You can pay online by clicking the PayPal link at ".CUSTOMER_DOMAIN_MINIMAL;
    // END NEW CODE 2019-02-06 JM
    $pdf->SetFont('Tahoma','',10);
    $total_string_width = $pdf->GetStringWidth($text);
    $pdf->SetFont('Tahoma','',10);

    $pdf->MultiCell(100,5,$text,0,'R');

    $num_pages = $pdf->setSourceFile2(BASEDIR . '/cust/' . $customer->getShortName() . '/img/pdf/paypal_invoice.pdf');
    $template_id = $pdf->importPage(1); //if the grafic is on page 1
    $size = $pdf->getTemplateSize($template_id);

    $pdf->useTemplate($template_id, PAGE_WIDTH - $size['width']/4.2 - 10, PAGE_HEIGHT - $size['height']/4.2 - 7,$size['width']/4.2,$size['height']/4.2);

    // ----------- Invoice date -----------
    $invoiceDate = date_parse( $invoice->getInvoiceDate());

    $invoiceDateString = '';
    if (is_array($invoiceDate)) {
        if (isset($invoiceDate['year']) && isset($invoiceDate['day']) && isset($invoiceDate['month'])) {
            $mm =  intval($invoiceDate['month']);
            if ($mm < 10) {
                $mm = "0" . $mm;
            }
            $dd = intval($invoiceDate['day']);
            if ($dd < 10) {
                $dd = "0" . $dd;
            }
            $invoiceDateString = intval($invoiceDate['year']) . '' . $mm . '' . $dd;
            if ($invoiceDateString == '000'){
                $invoiceDateString = '';
            }
        }
    }
} // END foreach ($invoiceIds AS $invoiceId)

if (count($invoiceIds) == 1) {
    $pdf->Output($job->getNumber() . '_' . $invoiceDateString . '_SSSInvoice_' .  $invoice->getInvoiceId() . '_' . str_replace(" ","-",$invoice->getNameOverride()) . '.pdf', 'D');
} else {
    $pdf->Output('SSSInvoice_' . date("Y-m-d") . '_contains_' . count($invoiceIds) . '_invoices_for_' . $companyName. '.pdf', 'D');
}

?>