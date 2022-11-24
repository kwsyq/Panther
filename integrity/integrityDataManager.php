<?php

include "../inc/config.php";
         
$conn=new mysqli(DB__HOST, DB__USER, DB__PASS);
$conn->select_db(DB__NEW_DATABASE);
$database=DB__NEW_DATABASE;


$vals=array();

if(isset($_REQUEST['act']) && $_REQUEST['act']=='updateTable'){
    $conn->query("call integrityTableUpdate('$database', 0, 0)");
    header("Location: integrityDataManager.php");
}


if(isset($_REQUEST['act']) && $_REQUEST['act']=='deleteSelected'){
    if(isset($_REQUEST['data'])){
        $vals=explode(",", $_REQUEST['data']);
    }
    $query="update integrityData set deleted_by='userWeb'
        where  id in ".str_replace("[", "(", str_replace("]", ")", $_REQUEST['data']))."";
        
//(deleted_at is not null) and        
//echo $query;

    $conn->query($query);
    
//print_r($conn);    
///die();        
    header("Location: integrityDataManager.php");
}

$query="select * from integrityData where isnull(deleted_by) order by deleted_at desc, status, tableName ";


$res = $conn->query($query);

?>
<html>
<head> 
<title>
        Integrity Test - companyPerson double records
</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4-4.1.1/jq-3.3.1/jszip-2.5.0/dt-1.10.21/b-1.6.3/b-flash-1.6.3/b-html5-1.6.3/datatables.min.css"/>
 
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs4-4.1.1/jq-3.3.1/jszip-2.5.0/dt-1.10.21/b-1.6.3/b-flash-1.6.3/b-html5-1.6.3/datatables.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" ></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" ></script>
</head>
<body>  


<div class="container-fluid">


<div class="container mx-auto" style="max-width: 80%">
<h3 class="my-3">Integrity Table</h3>

<div class="container-fliud">
<button class="btn btn-outline-primary" type="button" id="update">Run Update</button>
<button class="btn btn-outline-secondary" type="button"  id="deleteSelected">Delete selected</button>
<button class="btn btn-outline-warning" type="button"  id="signPK">Mark as PK</button>
<button class="btn btn-outline-danger" type="button"  id="signOK">Mark as OK</button>

</div>

    <table class="table table-striped table-hover my-3" id="tableError">
        <thead class="thead-dark">
            <tr>
                <td>Table Name</td>
                <td>Table Key</td>
                <td>Foreign Table Key</td>
                <td>Foreign Table</td>
                <td>Local Key</td>
                <td>Row Status</td>
                <td>Custom rules</td>
                <td>Deleted</td>
                <td>Sel</td>

            </tr>
        </thead>
        <tbody>
            <?php        
            while($row=$res->fetch_assoc()){
                extract($row);

            ?>
                <tr class="<?php echo ($deleted_at!==null?"bg-danger":"");?>">
                <td><?=$tableName ?></td>
                <td><?=$tableField ?></td>
                <td><?=$externalTableField ?></td>
                <td><?=$externalTableName ?></td>
                <td class="text-center"><?=($isPrimaryKey==0?'YES':'NO') ?></td>
                <td><?=$status ?></td>
                <td><?=($rule===null || $rule==''?'':'<button type=\'button\' class=\'btn btn-link\' data-toggle=\'modal\' data-target=\'#exampleModal\' data-whatever=\''.$rule.'\' >Show</button>') ?></td>
                <td><?=($deleted_at!==null ?'DELETED':'') ?></td>
                <td><input type="checkbox" tag="<?=$id?>" class="chkbox" ></td>
                </tr>


            <?php
            }
            ?>


        </tbody>
    </table>
    </div>
</div>

<div style="display:none">
<form action="integrityDataManager.php" method="post" id="formSubmit">
    <input type="hidden" name="act">
    <input type="hidden" name="data">

</form>
</div>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">New message</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group">
            <label for="message-text" class="col-form-label">Message:</label>
            <textarea class="form-control" id="message-text"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>

      </div>
    </div>
  </div>
</div>

<script>

$(document).ready(function(){
    var $chkboxes = $('.chkbox');
    var lastChecked = null;
    var table=null;

    table=$('#tableError').DataTable({
        "fixedHeader": true,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "order": [[ 7, "desc" ]],
        iDisplayLength: -1,  
        dom: 'Bfrtip',
        buttons: [
        {
            extend: 'excel',
            text: 'Export to Excel'
        }
        ]
    });
    $chkboxes.click(function(e) {
        if (!lastChecked) {
            $chkboxes = $('.chkbox');            
            lastChecked = this;
            return;
        }

        if (e.shiftKey) {
            var start = $chkboxes.index(this);
            var end = $chkboxes.index(lastChecked);

            $chkboxes.slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);
        }

        lastChecked = this;
    });

    $('#update').mousedown(function(){
        $('input[name=act]').val('updateTable');
        $('input[name=data]').val("");
        $('#formSubmit').submit();
        return;
    });

    $('#deleteSelected').mousedown(function(){
        chkboxes = $('.chkbox:checked'); 
        var ids=[];
        $.each(chkboxes, function(){
            ids.push($(this).attr('tag'));
        });
        if(ids.length<=0){
            alert("Non rows selected!!");
            return;
        }
        $('input[name=act]').val('deleteSelected');
        $('input[name=data]').val(JSON.stringify(ids));
        $('#formSubmit').submit();
        return;
    });

    $('#backRestore').mousedown(function(){
        $('input[name=act]').val('');
        $('input[name=data]').val(<?php echo isset($_REQUEST['data'])?$_REQUEST['data']:"" ?>);
        $('#formSubmit').submit();
        return;
    });

    $('#uncheckAll').mousedown(function(){
        chkboxes = $('.chkbox:checked'); 
        $.each(chkboxes, function(){
            $(this).prop("checked", false);
        });
        return;
    });
    $('#doDelete').mousedown(function(){
        chkboxes = $('.chkbox:checked'); 
        $('input[name=act]').val('doDelete');
        $('input[name=data]').val(<?php echo isset($_REQUEST['data'])?$_REQUEST['data']:"" ?>);
        $('#formSubmit').submit();
        return;
        return;
    });

$('#exampleModal').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget) // Button that triggered the modal
  var recipient = button.data('whatever') // Extract info from data-* attributes
  // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
  // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
  var modal = $(this)
  modal.find('.modal-title').text('Check rule');
  modal.find('#message-text').val(JSON.stringify(recipient, undefined, 4));
})    
});
</script>
</body>

</html>

