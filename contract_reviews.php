<?php
/*  contract_reviews.php
    
    EXECUTIVE SUMMARY: 
            * Page to give a report of Contracts to review based on status.
            * Page to give a report of contracts on which someone with read/ write perms
              is specified as being notified. 
              This should typically include employees
            * Page also increase/ decrease in real time the notification 
              count in the header (to do / reviewed) on call to action button.
    No inputs.


*/

include './inc/config.php';
include './inc/access.php';



include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Contract Reviews';\n</script>\n";
?>
<link href="https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js" ></script>
<style>
body {
    background-color: #fff;
}
</style>>

<?php
$crumbs = new Crumbs(null, $user);

$db = DB::getInstance();

// Select all contracts information where someone is specified in contractNotification as being notified ( the reviewer ).


$query = " SELECT cn.*, cs.statusName ";
$query .= " FROM " . DB__NEW_DATABASE . ".contractNotification cn ";
$query .= " LEFT JOIN " . DB__NEW_DATABASE . ".contractStatus cs ON cn.contractStatus = cs.contractStatusId ";
$query .= " WHERE reviewerPersonId = " . intval($user->getUserId()) . " "; // current user only
$query .= " ORDER BY cn.date DESC LIMIT 100;"; // only 100 entries

$contractsRev = array();
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $contractsRev[] = $row;                
    }
} else {
    $logger->errorDB('637747469365596177', "Hard DB error", $db);
}            



?>
<style>
tfoot input {
  width: 100%;
}

/* placing the footer on top */
table tfoot {
    display: table-header-group;
}
/* customize tfoot */
#reviewsContractList tfoot tr th {
    background-color: #9d9d9d;
}
#reviewsContractList tfoot th, #reviewsContractList tfoot td {
    border-right: 1px solid #898989;
}
::placeholder {
    opacity: 0.7;
}
table.dataTable tfoot th, table.dataTable tfoot td {
    padding: 10px 10px 6px 10px;
}

#reviewsContractList { border-bottom: 1px solid black }

table.dataTable tbody td {
    font-size: 16px;
    font-weight: 500;
}
.cellWidth {
    width: 70px;
}
.cellWidth2 {
    width: 100px;
}

.cellWidthBtn{
    width: 60px;
}
a.statusLink {
    color: #fff;
}
</style>  
<script>

var setNotificationStatus = function(notificationId, reviewStatus) {
      
        var cell = document.getElementById("buttonReview_" + notificationId);

  
        $.ajax({
            type:'GET',
            url: '../ajax/contract_reviews_status.php',
            async:false,
            dataType: "json",
            data: {
                notificationId: notificationId,
                reviewStatus: reviewStatus
            },
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') { 
                        if( data['reviewStatus'] == 0) {
                            var html = '<a id="buttonReview_' + notificationId + '" class="statusLink btn btn-success btn-sm active"  href="javascript:setNotificationStatus(' + notificationId + ',' + 
                               1 + ')" role="button" title="For review">To Do</a>';
    
                                var bubble = $("#notify-bubble").html();
                                console.log('bubble + 1');
                                console.log(bubble);
                                bubble = Number(bubble);
                                bubble = bubble + 1;
                                $("#notify-bubble").text(bubble);
                                $(cell).removeClass("btn btn-secondary btn-sm active");
                        } else {
       
                            var html = '<a id="buttonReview_' + notificationId + '" class="statusLink btn btn-secondary btn-sm active" href="javascript:setNotificationStatus(' + notificationId + ',' + 
                               0 + ')" role="button" title="Moved it to Reviewed">Reviewed</a>';
     
                                var bubble = $("#notify-bubble").html();
                                console.log('bubble - 1');
                                console.log(bubble);
                                bubble = Number(bubble);
                                bubble = bubble - 1;
                                $("#notify-bubble").text(bubble);
                                $(cell).removeClass("btn btn-success btn-sm active");
                    }
                    cell.innerHTML = html; 
              
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
    }; // end function setNotificationStatus

    </script>
<div id="container" class="clearfix">

    <div class="main-content">
        <div class="full-box clearfix">
        <h2 class="heading">CONTRACTS REVIEWS</h2>
        <br>
            <table  class="stripe row-border cell-border" id="reviewsContractList" style="width:100%" >    
                <thead>    
                    <tr>
                        <th>WorkOrder</th>
                        <th class="cellWidth">Ctr. Id</th>
                        <th>Contract Name</th>
                 
                        <th >Contract Status</th>
                        <th class="cellWidth2">Status Date</th>
                        <th>Status By</th>
                        <th>&nbsp;</th> <!-- view contract  -->
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>WorkOrder</th>
                        <th  class="cellWidth">Ctr. Id</th>
                        <th>Contract Name</th>
                      
                        <th >Contract Status</th>
                        <th class="cellWidth2">Status Date</th>
                        <th>Status By</th>
                        <th id="viewContractId">&nbsp;</th> <!--  view contract  -->
                        <th id="activeReview">&nbsp;</th> <!--  deactivated review -->
                    </tr>
                </tfoot>   
                <tbody>
                <?php     
                foreach ($contractsRev as $rev) {
                  
                    $user = new User($rev['personId'], $customer);          
                    $contract = new Contract($rev['contractId']); 
                    $woId = $contract->getWorkOrderId();
                    $wo = new WorkOrder(intval($woId));
                    $woName = $wo->getName();
                 
                ?>
                    <tr>
                        <td><a href="<?php echo $wo->buildLink()?>" ><?php echo  $woName . " ". "( ". $woId ." )" ?></a></td>
                        <td  class="cellWidth"><?php echo $rev["contractId"] ?></td>
                        <td><?php echo  $contract->getNameOverride(); ?></td>
                    
                        <td ><?php echo $rev['statusName']; ?></td>
                        <td class="cellWidth2"><?php    echo DateTime::createFromFormat("Y-m-d H:i:s", $rev['date'])->format("m/d/Y"); ?></td>
                        <td><?php echo $user->getFirstName() . " " .  $user->getLastName();   ?></td>
                        <td><a href="<?php echo $contract->buildLink()?>" class="btn btn-secondary btn-sm active" role="button" aria-pressed="true">View Contract</a></td>
                        
                        <?php if(intval($rev['reviewStatus']) == 0) { ?>
                        <td class="cellWidthBtn"><a href="javascript:setNotificationStatus(<?php echo $rev['notificationId'];?>, 1)" role="button"  class="btn btn-success btn-sm active" title="For review" id="buttonReview_<?=$rev['notificationId']?>">To Do</a></td>
                      
                        <?php } else {?>
                            <td class="cellWidthBtn"><a   href="javascript:setNotificationStatus(<?php echo $rev['notificationId'];?>, 0)" role="button" class="btn btn-secondary btn-sm active" title="Moved it to Reviewed" id="buttonReview_<?=$rev['notificationId']?>">Reviewed</a></td>
                        <?php }?>
                    </tr>
                    <?php } ?>  <!--  END foreach ($contractsRev...   -->
                </tbody>       
           </table>
        </div>
    </div>
</div>


<script>
$(document).ready(function() {

    $('#reviewsContractList tfoot th').each( function () {
        $(this).html( '<input type="text" placeholder="Search.." />' );
        $('#viewContractId input').hide(); //Hide Search for last column. 
 
    } );

    // DataTable
    var table = $('#reviewsContractList').DataTable({
        "autoWidth": true,
        "columnDefs": [{ 'targets': 4, type: 'date' }],
        "order": [4, 'desc'],
        initComplete: function () {
            // Apply the search
            this.api().columns().every( function () {
                var that = this;
 
                $( 'input', this.footer() ).on( 'keyup change clear', function () {
                    if ( that.search() !== this.value ) {
                        that
                            .search( this.value )
                            .draw();
                    }
                } );
            } );
        }
    });
 
} );


</script>

<?php 
include BASEDIR . '/includes/footer.php';
?>