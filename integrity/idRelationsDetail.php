<?php
/*

  This is still a draft


*/


require_once("../inc/config.php");
require_once("morefunctions.php");
 
 
         
$conn=new mysqli(DB__HOST, DB__USER, DB__PASS);
$conn->select_db(DB__NEW_DATABASE);
$database=DB__NEW_DATABASE;

if(isset($_REQUEST['act']) && $_REQUEST['act']=='doDelete'){
    
    $data=json_decode($_REQUEST['data']);
    
    $tableName=$data->tableName;

    $tableColumn=$data->tableColumn;
    $keyValue=$data->keyValue;

    $result=json_decode(externalLinksDetail($tableName, $tableColumn, $keyValue));
    
    foreach($result->data as $item){

        $conn->query("delete from ". $item->tableName. " where companyPersonId=".$keyValue);
        
    }
    
    header("Location: reportTwinsCompanyPerson.php");
    
} else {
    
    $tableName=$_REQUEST['tableName'];
    $tableColumn=$_REQUEST['tableColumn'];
    $keyValue=$_REQUEST['keyValue'];

    $result=json_decode(externalLinksDetail($tableName, $tableColumn, $keyValue));

    
}

?>
<html>
<head> 
<title>
    Panther Database - Integrity Test
</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.5.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" ></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" ></script>
<script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" ></script>
</head>
<body>  


<div class="container-fluid">

<div class="container mx-auto" style="max-width: 100%">
<h3 class="my-3">Table <?=$tableName?> keyValue <span style="color: green"><?= $keyValue ?></span> - where is used</h3>


 <button class="btn btn-danger float-right" type="button" id="doDelete">Delete all!</button>
    <table class="table table-striped table-hover my-3" id="tableError">
        <thead class="thead-dark">
            <tr>
                <td>Table Name</td>
                <td>Table Key</td>
                <td>Key Value</td>
                <td>Full Row</td>
                

            </tr>
        </thead>
        <tbody>
            <?php        
            foreach($result->data as $value)
            {

            ?>
                <tr>
                <td><?=$value->tableName ?></td>
                <td><?=$value->tableKey ?></td>
                <td><?=$value->tableKeyValue ?></td>
                <td><?=json_encode($value->data); ?></td>
                </tr>


            <?php
            }
            ?>


        </tbody>
    </table>
    </div>
</div>

<div style="display:none">
<form action="idRelationsDetail.php" method="post" id="formSubmit">
    <input type="hidden" name="act">
    <input type="hidden" name="data">

</form>
</div>

<script>

$(document).ready(function(){
    var $chkboxes = $('.chkbox');
    var lastChecked = null;
    var table=null;

    table=$('#tableError').DataTable({
        "fixedHeader": true,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "order": [[ 0, "asc" ]],
        iDisplayLength: -1
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

    $('#next').mousedown(function(){
        chkboxes = $('.chkbox:checked'); 
        var ids=[];
        $.each(chkboxes, function(){
            ids.push($(this).closest('tr').find('td').eq(0).text());
        });
        if(ids.length<=0){
            alert("Non rows selected!!");
            return;
        }
        $('input[name=act]').val('confirmDelete');
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
        $('input[name=act]').val('doDelete');
        $('input[name=data]').val('<?php echo isset($_REQUEST['tableName'])?json_encode($_REQUEST):"" ?>');
        $('#formSubmit').submit();
        return;
    });
});
</script>
</body>

</html>

