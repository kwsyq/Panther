<?php
/*  fb/workordercontracts.php

    EXECUTIVE SUMMARY: Shows contracts for a given workorder, allows you to load a page
    to invoice a given contract.

    PRIMARY INPUT: $_REQUEST['workorderId'].
*/

include '../inc/config.php';
include '../inc/access.php';

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
if (!intval($workOrderId)) {
    die();
}

$workOrder = new WorkOrder($workOrderId);
if (!intval($workOrder->getWorkOrderId())) {
    die();
}

include '../includes/header_fb.php';
?>

<script type="text/javascript">
    <?php /* If you click "choose" for any given row, it loads (at the parent level) a page 
             generated by domain-root-level invoice.php; the code to link the correct page is 
             a bit of a kluge. The resulting link will be along the lines of 
             "/invoice/WW/?loadId=CC" where WW is workOrderId and CC is contractId.  
    */ ?>
    var chooseContract = function(contractId) {
        setTimeout(function() {
            parent.$.fancybox.close(parent.location.href = "<?php echo str_replace("/workorder/","/invoice/", $workOrder->buildLink()); ?>/?loadId=" + escape(contractId));
        }, 1000);
    }
</script>

<?php
$contracts = $workOrder->getContracts();

echo '<h1>' . $workOrder->getName() . '</h1>';
echo '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
    echo '<thead>';
        echo '<tr>';
            echo '<td colspan="5"><h2>Current Contracts</h2></td>';			
        echo '</tr>';
        
        echo '<tr>';
            echo '<th>Contract Date</th>';
            echo '<th>Committed</th>';
            echo '<th>Person</th>';
            echo '<th>Inserted</th>';
            echo '<th>Notes</th>';
            echo '<th>&nbsp;</th>';
        echo '</tr>';
    echo '</thead>';
    echo '<tbody>';    
        foreach ($contracts as $contract) {        
            echo '<tr>';        
                echo '<td>' . $contract->getContractDate() . '</td>';
                echo '<td>' . $contract->getCommitted() . '</td>';
                echo '<td>' . $contract->getCommitPersonId() . '</td>';
                echo '<td>' . $contract->getInserted() . '</td>';
                echo '<td>' . $contract->getCommitNotes() . '</td>';
                echo '<td>[<a id="chooseContract'. intval($contract->getContractId()) . '" href="javascript:chooseContract(' . intval($contract->getContractId()) . ')">choose</a>]</a>';
            echo '</tr>';
        }
    echo '</tbody>';
echo '</table>';

include '../includes/footer_fb.php';
?>