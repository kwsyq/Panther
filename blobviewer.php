<?php
/*  blobviewer.php

    EXECUTIVE SUMMARY: Tool to look at various data such as the content of the 'data' blob from a contract or invoice 
    (assuming you have the relevant permission).
    
    INPUT $_REQUEST['contractId'] *OR* $_REQUEST['invoiceId'] *OR* $_REQUEST['workOrderId']. If there is an invoiceId, then contractId is ignored.
        If there is an invoiceId, or a contractId, then workOrderId is ignored.
    INPUT $_REQUEST['toShow']. What we want to see:
        * 'data-blob' - decrypted 'data' column from the relevant DB table ('invoice' or 'contract')
        * 'getWorkOrderTasksTree' - return of WorkOrder::getWorkOrderTasksTree()
        * 'overlay' - return of function 'overlay' (in functions.php)    
          
    
    >>>00002, >>>00016: could do more input verification    
*/

require_once './inc/config.php';
require_once './inc/perms.php';

// Get the inputs
$contractId = 0;
$workOrderId = 0;
$error = '';

$invoiceId = isset($_REQUEST['invoiceId']) ? intval($_REQUEST['invoiceId']) : 0;
if ($invoiceId) {
    $checkPerm = checkPerm($userPermissions, 'PERM_INVOICE', PERMLEVEL_R);
    if (!$checkPerm){
        // // No admin-level permission for invoice, redirect to '/panther'
        header("Location: /panther");
    }
    if (!Invoice::validate($invoiceId)) {
        $error = "Invalid invoiceId $invoiceId";
        $invoiceId = 0;
    }
} else {
    $contractId = isset($_REQUEST['contractId']) ? intval($_REQUEST['contractId']) : 0;
    if ($contractId) {
        $checkPerm = checkPerm($userPermissions, 'PERM_CONTRACT', PERMLEVEL_R);
        if (!$checkPerm){
            // // No admin-level permission for contract, redirect to '/panther'
            header("Location: /panther");
        }
        if (!Contract::validate($contractId)) {
            $error = "Invalid contractId $contractId";
            $contractId = 0;
        }
    } else {
        $workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
        if ($workOrderId) {
            if (!WorkOrder::validate($workOrderId)) {
                $error = "Invalid workOrderId $workOrderId";
                $workOrderId = 0;
            }
        } else {
            $error = "No invoiceId, contractId, or workOrderId";
        }
    }
}

if ($error) {
    $toShow = '';
} else {
    $toShow = isset($_REQUEST['toShow']) ? $_REQUEST['toShow'] : '';
    if ($toShow == 'data-blob') {
        if ($invoiceId || $contractId) { 
            // OK
        } else {
            $error = "Showing 'data-blob' only makes sense for contract or invoice"; 
        }
    } else if ($toShow == 'getWorkOrderTasksTree') {
        // OK
    } else if ($toShow == 'overlay') {
        if ($invoiceId || $contractId) { 
            // OK
        } else {
            $error = "Showing 'overlay' only makes sense for contract or invoice"; 
        }
    } else if ($toShow == '') {
        $error = "Nothing requested to show ";
    } else {    
        $error = "toShow = '$toShow', not understood.";
        $toShow = '';
    }
}

if ( !$error && ($invoiceId || $contractId) ) {
    if ($invoiceId) {
        $obj = new Invoice($invoiceId); // presume this works because we validated $invoiceId
        $contractId = $obj->getContractId(); 
    } else if ($contractId) {
        $obj = new Contract($contractId); // presume this works because we validated $contractId
    }
    $name = $obj->getNameOverride();
    
    $workOrderId = $obj->getWorkOrderId();
}

if ($workOrderId) {
    if (WorkOrder::validate($workOrderId)) {
        $wo = new WorkOrder($workOrderId);
        $wo_description = $wo->getDescription();
        
        $jobId = $wo->getJobId();
        if (Job::validate($jobId)) {
            $job = new Job($jobId);
        } else {
            $error = "Invalid underlying jobId $jobId!";
        }
    } else {
        $error = "Invalid underlying workOrderId $workOrderId!";
        $workOrderId = 0;
    }
}

// Now make sense of $toShow
if (! $error) {
    if ($wo && $toShow == 'getWorkOrderTasksTree') {
        $data = $wo->getWorkOrderTasksTree();
    } else if ($obj && $toShow == 'data-blob') {
        // $obj is explicitly fed invoice or contract
        $data = $obj->getData();
    } else if ($obj && $toShow == 'overlay') {
        // $obj is explicitly fed invoice or contract
        $data = overlay($wo, $obj);
    } else {
        $data = null;
    }
}

include_once BASEDIR . '/includes/header.php';

if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<!-- select something to view -->
<form action="blobviewer.php" id="InvoiceForm">
<label for="newInvoiceId">InvoiceId:</label>&nbsp;
<input id="newInvoiceId" type="number" name="invoiceId" <?= $invoiceId ? "value=\"$invoiceId\"" : '' ?> />&nbsp;
<select name="toShow" id="toShowInv">
<option value="getWorkOrderTasksTree">getWorkOrderTasksTree</option>
<option value="data-blob" selected>invoice.data (from DB)</option>
<option value="overlay">return of function 'overlay'</option>
</select>
<button id="goInvoice">Go!</button>
</form>
<form action="blobviewer.php" id="ContractForm">
<label for="newContractId">ContractId:</label>&nbsp;
<input id="newContractId" type="number" name="contractId" <?= $contractId ? "value=\"$contractId\"" : '' ?> />&nbsp;
<select name="toShow" id="toShowContract">
<option value="getWorkOrderTasksTree">getWorkOrderTasksTree</option>
<option value="data-blob" selected>contract.data (from DB)</option> 
<option value="overlay">return of function 'overlay'</option>
</select>
<button id="goContract">Go!</button>
</form>
<form action="blobviewer.php" id="WorkOrderForm">
<label for="newWorkOrderId">WorkOrderId:</label>&nbsp;
<input id="newWorkOrderId" type="number" name="workOrderId" <?= $workOrderId ? "value=\"$workOrderId\"" : '' ?>  />&nbsp;
<select name="toShow" id="toShowWorkorder">
<option value="getWorkOrderTasksTree" selected>getWorkOrderTasksTree</option>
</select>
<button id="goWorkorder">Go!</button>
</form>
<br />
<?php
if ($toShow) {
?>    
    <script>
        $(function() {
            $('select[name="toShow"] option[value="<?= $toShow ?>"]').prop('selected', true);
        });
    </script>
<?php
}

if ($invoiceId) {
    $invoiceLink = $obj->buildLink();
} else if ($contractId) {
    // (If we started from an invoice, we'll build $contractLink below instead)
    $contractLink = $obj->buildLink();    
}

if (isset($wo) && $wo) {
    $woLink = $wo->buildLink();
}
if (isset($job) && $job) {
    $jobLink = $job->buildLink();
}

if (!$error) {    
    echo "<h1>";
    echo $invoiceId ? "Invoice <a id=\"invoiceId$invoiceId\" href=\"$invoiceLink\">$invoiceId</a>: $name" : 
         ($contractId ? "Contract: <a id=\"contractId$contractId\" href=\"$contractLink\">$contractId</a>: $name" :             
         ($workOrderId ? "WorkOrder: <a id=\"workorderDesc$workOrderId\" href=\"$woLink\">$workOrderId</a>: $wo_description" :
                'Select above'));
    echo "</h1>\n";
    
    if ($invoiceId) {
        if ($contractId) {
            $contract = new Contract($contractId);
            $contractName = $obj->getNameOverride();
            $contractLink = $contract->buildLink();
            echo "Derives from Contract: <a id=\"contractName$contractId\" href=\"$contractLink\">$contractId</a>: $contractName";
        } else {
            echo "No underlying contract";
        }
        echo "<br>\n";
    }
    
    if ($invoiceId || $contractId) {
        echo "WorkOrder: <a id=\"woDesc$workOrderId\" href=\"$woLink\">$workOrderId</a>: $wo_description<br />\n";
    }
        
    echo "Job: <a id=\"jobId$jobId\"  href=\"$jobLink\">$jobId [" . $job->getNumber(). "]</a>: " . $job->getName(). "<br /><br />\n";    
        
    
    // Get print_r version & clean it up for HTML display
    $print_data = print_r($data, true);
    
    if ($toShow == 'getWorkOrderTasksTree') {
        // Unfortunately, that thing is a monster, and we need to suppress part of it to make this readable
        // We certainly have no interest in what is inside the many Logger2 objects, all called 'logger', and they
        //  made up more than half of the output. This strips them out. JM 2020-09-01
        $position = 0;
        while ($position = strpos($print_data, '[logger:', $position)) {
            $position = strpos($print_data, "\n", $position);
            $scanner = strpos($print_data, "(", $position); // opening parenthesis
            $nested_parentheses = 0;
            $len = strlen($print_data);
            do {
                // >>>00001 algorithm here assumes no unbalanced parentheses inside quoted strings, could be made smarter.
                $ret = preg_match('/[\(\)]/', $print_data, $matches, PREG_OFFSET_CAPTURE, $scanner);
                if (!$ret || !$matches) {
                    // shouldn't ever happen >>>00001 might be able to learn more here.
                    $logger->error2('1598974341', 'misformed logger in $print_data');
                    break;
                }
                $scanner = $matches[0][1];
                $ch = substr($print_data, $scanner, 1);
                if ($ch == '(') {
                    ++$nested_parentheses;
                } else if ($ch == ')') {
                    --$nested_parentheses;
                }
                ++$scanner;
            } while ($nested_parentheses > 0 && $scanner < $len); // the latter condition should never go false, it's a failsafe to prevent runaway
            // $scanner should now be just past the last parenthesis
            $print_data = $ch = substr($print_data, 0, $position) . ': OBJECT SUPPRESSED' . substr($print_data, $scanner);
        }
    }

    $print_data = str_replace("\n", "<br>\n", $print_data);
    $print_data = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $print_data);
    $print_data = str_replace(' ', "&nbsp;", $print_data);
    
    $showing = 'Showing ';
    if ($toShow == "getWorkOrderTasksTree") {
        $showing .= "getWorkOrderTasksTree.";
    } else if ($toShow == "overlay") {
        $showing .= "return of function 'overlay'.";
    } else if ($toShow == "data-blob") {
        if ($invoiceId) {
            $showing .= "invoice.data (from DB).";
        } else {
            $showing .= "contract.data (from DB).";
        }
    } else {
        $showing .= "???";
    }
    ?>
    
    <div style="text-align:left; margin-left:100px; width:100%;">
    <b><?= $showing ?></b><br />
    
    <?php
    $json_problem = '';
    if ($toShow == 'getWorkOrderTasksTree') {
        $json_problem = 'Contains PHP objects, cannot be viewed in JSON.'; 
    }
    $json_class = '';
    $print_r_class = '';
    if ($json_problem) {
        $json_class = 'hidden';
    ?>
        <input type="radio" name="show" id="show-print_r" checked />&nbsp;<label for="show-print_r">Show print_r version.</label>
        &nbsp;<?= $json_problem ?>
    <?php
        
    } else {
        $print_r_class = 'hidden'; // intially show JSON
    ?>
        <!-- choose how to view it -->
        <input type="radio" name="show" id="show-print_r" />&nbsp;<label for="show-print_r">Show print_r version</label>
        &nbsp;&nbsp;&nbsp;
        <input type="radio" name="show" id="show-json" checked />&nbsp;<label for="show-json">Show JSON version</label>
    <?php
    }
    ?>
    
    <style>
    div.hidden{display:none;}    
    </style>
    
    <script>
        $(function() {
            $('input[type="radio"][name="show"]').change(function() {
                if ($('#show-print_r').is(':checked')) {
                    $('#print_r').removeClass('hidden');
                    $('#json').addClass('hidden');
                } else {
                    $('#print_r').addClass('hidden');
                    $('#json').removeClass('hidden');
                }
            });
        });
    </script>    
    
    <div id="print_r" class="<?= $print_r_class ?>">
        PHP print_r: <br /><br />
<?php
    // apparently $print_data here can be enormous, so big that just echoing it doesn't work
    $len = strlen($print_data);
    for ($i = 0; $i < $len; $i += 10000) {
        echo substr($print_data, $i, 10000);
    }
?>
        <hr>
    </div>
    <div id="json" class="<?= $json_class ?>">
        JSON: <br /><br />

        <pre>
<?= trim(json_encode($data, JSON_PRETTY_PRINT)) ?>
        </pre>
        <hr>
    </div>
    <?php
}

include_once BASEDIR . '/includes/footer.php';
?>