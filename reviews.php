<?php
/*  reviews.php
    
    EXECUTIVE SUMMARY: 
            * Through v2020-2 was: Page to give a report of workOrders on hold.
            * Beginning v2020-3: Page to give a report of workOrders on which someone 
              is specified in wostCustomerPerson as being notified. 
              This should typically include HOLDs, but there is no longer a technical limitation against 
              using this for other workOrderStatuses
    
    No inputs.

    Radically simplified for v2020-3, so I (JM) haven't preserved old code.
*/

include './inc/config.php';
include './inc/access.php';

syslog(LOG_INFO, 'in reviews.php');


include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Reviews';\n</script>\n";
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

// Select all workOrders where someone is specified in wostCustomerPerson as being notified.
// Although we will eventually want to know *who* is notified, we don't get that in this query. 
$query = "SELECT DISTINCT wo.workOrderId ";
$query .= "FROM " . DB__NEW_DATABASE . ".workOrder wo ";
$query .= "JOIN " . DB__NEW_DATABASE . ".workOrderStatusTime wost on wo.workOrderStatusTimeId = wost.workOrderStatusTimeId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".wostCustomerPerson wostcp on wo.workOrderStatusTimeId = wostcp.workOrderStatusTimeId ";
$query .= "JOIN " . DB__NEW_DATABASE . ".customerPerson cp on cp.customerPersonId = wostcp.customerPersonId ";
$query .= "WHERE cp.customerId = " . intval($customer->getCustomerId()) . " "; // current customer only
$query .= "ORDER BY wost.inserted DESC;";

$workOrders = array();
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $workOrders[] = $row;                
    }
} else {
    $logger->errorDB('1591909871', "Hard DB error", $db);
}            

// Get content $wodts of DB table WorkOrderDescriptionType as an array (canonical representation);
// get only active types.
// Then rework that in $workOrderDescriptionTypes as an associative array indexed by workOrderDescriptionTypeId 
$wodts = getWorkOrderDescriptionTypes();
$workOrderDescriptionTypes = array();

foreach ($wodts as $wodt) {
    $workOrderDescriptionTypes[$wodt['workOrderDescriptionTypeId']] = $wodt;
}

$reactivateStatus = WorkOrder::getReactivateStatusId();

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
#reviewsList tfoot tr th {
    background-color: #9d9d9d;
}
#reviewsList tfoot th, #reviewsList tfoot td {
    border-right: 1px solid #898989;
}
::placeholder {
    opacity: 0.7;
}
table.dataTable tfoot th, table.dataTable tfoot td {
    padding: 10px 10px 6px 10px;
}

#reviewsList { border-bottom: 1px solid black }

/* prevent body movement */
body.modal-open {
padding-right: 0px !important;
overflow-y: auto;
}

</style>  
<div id="container" class="clearfix">
    <?php if ($reactivateStatus === false) { ?>
        <div class="alert alert-danger" role="alert" style="color:red">Error obtaining reactivateStatus, please contact an administrator or developer.</div>"; 
    <?php } ?>
    <div class="main-content">
        <div class="full-box clearfix">
        <h2 class="heading">REVIEWS</h2>
        <br>
            <table  class="stripe row-border cell-border" id="reviewsList" style="width:100%" >    
                <thead>    
                    <tr>
                        <th>Job Number</th>
                        <th>Job Name</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Status Age</th>
                        <th>Intake Date</th>
                        <th>Status By</th>
                        <th>Note</th>
                        <th>Reviewer</th>
                        <th>&nbsp;</th> <!--  for buttons, added 2019-11-21 JM -->
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Job Number</th>
                        <th>Job Name</th>
                        <th>Type</th>
                        <th>Description</th>                   
                        <th>Status Age</th>
                        <th>Intake Date</th>
                        <th>Status By</th>
                        <th>Note</th>
                        <th>Reviewer</th>
                        <th id="makeActiveId">&nbsp;</th> <!--  for buttons - deactivated search -->
                    </tr>
                </tfoot>   
                <tbody>
                <?php     
                foreach ($workOrders as $workOrder) {
                    $wo = new WorkOrder($workOrder['workOrderId']);          
                    $statusdata = $wo->getStatusData();
                    $j = new Job($wo->getJobId());
                ?>
                    <tr>
                        <td><a href="<?=$j->buildLink();?>"><?=$j->getNumber();?></a></td> <!--Job Number" -->
                        <td><?=$j->getName();?></td> <!--Job Name" -->
                        
                        <!-- "Type": workOrderTypeName, with appropriate background color -->
                        <?php if (isset($workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()])) {
                            $color = $workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()]['color'];
                        } ?>
                        <td bgcolor="<?=$color;?>">
                            <?php if (isset($workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()])) { ?>
                                <a href="<?=$wo->buildLink();?>"><?=$workOrderDescriptionTypes[$wo->getWorkOrderDescriptionTypeId()]['typeName'];?></a>
                            <?php } else { ?>
                                <a href="<?=$wo->buildLink();?>">---</a>
                            <?php } ?>
                        </td>
                    
                        <!-- "Description": workOrder description -->
                            <td><?=htmlspecialchars($wo->getDescription()); ?></td>
                    
                        
                        <!-- "Status Age": days & hours since inserted -->
                        <td style="text-align:center;">
                            <?php $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $statusdata['inserted']);
                    
                            $dt2 = new DateTime;
                            $interval = $dt1->diff($dt2);
                            $days = $interval->d; // get the days
                            $hours = $interval->h; // get the hours
                            if($days) {
                                $hours = $hours + $days*24; // days to hours
                            }
                            //$statusAge = $interval->format('%dd %hh');
                            echo $hours . " h"; // George 05-03-2021. Changed days to hours. ?> 
                        </td>
                                           
                        <!-- Intake Date  -->
                        <td>
                        <?php  if($wo->getIntakeDate() && $wo->getIntakeDate() != '0000-00-00 00:00:00') {
                            echo date("m/d/Y",strtotime($wo->getIntakeDate())); 
                        } else {
                            echo "";
                        } ?>
                        </td>

                        <!-- "Status By": Formatted name of person who set status (first name + last name). -->
                        <td>
                        <?php if (intval($statusdata['personId'])) {
                            $p = new Person($statusdata['personId']);
                            echo $p->getFormattedName(1);
                        } ?>
                        </td>
                        <!-- Note: Note on that hold status, if any.  -->
                        <td><?=$statusdata['note']; ?></td>
                        <!-- "Reviewer": Extra EOR, possibly other extras, by name. Nested table. -->
                        <td>
                        <?php if ($statusdata['customerPersonArray']) { ?>
                            <table border="0" cellpadding="1" cellspacing="0">
                            <?php foreach($statusdata['customerPersonArray'] as $customerPersonData) { ?>
                            <tr>
                                <td valign="top">&gt;</td>
                                <td valign="top"><?=$customerPersonData['legacyInitials'];?></td>
                            </tr>
                            
                        <?php } ?>
                            </table>
                        <?php } ?>
                        </td>
                        <!-- buttons, added 2019-11-21 JM -->
                        <!-- Script to implement this is below. -->
                        <td>
                        <button class="make-active btn btn-secondary" data-workorderid="<?=$wo->getWorkOrderId();?>">Make active</button>
                        </td>
                    </tr>
                    <?php } ?>  <!--  END foreach ($workOrders...   -->
                </tbody>       
           </table>
        </div>
    </div>
</div>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Activate WorkOrder </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group row">
			<input type="hidden" id="woid" value="">
            <label for="message-text" class="col-form-label col-sm-4" style='font-weight: 800'>Note:</label>
            <textarea class="form-control col-sm-7" id="message-text"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="activateWorkOrder">Activate WorkOrder</button>
      </div>
    </div>
  </div> 
</div>
<script>
$(document).ready(function() {

    $('#reviewsList tfoot th').each( function () {
        $(this).html( '<input type="text" placeholder="Search.." />' );
        $('#makeActiveId input').hide(); //Hide Search for last column. 
    } );

    // DataTable
    var table = $('#reviewsList').DataTable({
        "autoWidth": true,
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

$('.make-active').click(function() {
	let $this = $(this);
	$('#woid').val($this.data('workorderid'));	
	$('#exampleModalLabel').html("Activate WorkOrder #" + $this.data('workorderid'));
	$('#exampleModal').modal('show');
});
$('#activateWorkOrder').mousedown(function(){
	console.log($('textarea#message-text').val());
	$.ajax({
        url: '/ajax/setworkorderstatusnew.php',
        data:{
            workOrderStatusId: <?= $reactivateStatus ?>, // let this be DB-driven
            workOrderId: $('#woid').val(),
			note: $('textarea#message-text').val(),
            customerPersonIds:''
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            location.reload();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
	

});

</script>

<?php 
include BASEDIR . '/includes/footer.php';
?>