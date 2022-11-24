<?php
/*

  This is still a draft


*/


include "../inc/config.php";
         
$conn=new mysqli(DB__HOST, DB__USER, DB__PASS);
$conn->select_db(DB__NEW_DATABASE);
$database=DB__NEW_DATABASE;

//$tableName=$_REQUEST['tableName'];

$showCheck=1;

$vals=array();

if(isset($_REQUEST['act']) && $_REQUEST['act']=='confirmDelete'){
    $query="select c.*, p.*, cp.* from companyPerson cp, company c, person p 
    where 
    c.companyId=cp.companyId and
    p.personId=cp.personId and
    cp.companyPersonId in ".str_replace("[", "(", str_replace("]", ")", $_REQUEST['data']))."
    order by c.companyName, p.lastName ";
    
    $res = $conn->query($query);
    $showCheck=0;

} else if(isset($_REQUEST['act']) && $_REQUEST['act']=='doDelete'){

    if(isset($_REQUEST['data'])){
        $vals=explode(",", $_REQUEST['data']);
    }

    $query="delete 
        from 
            companyPerson
        where 
            companyPersonId in (".$_REQUEST['data'].")";
    $conn->query($query);
    header("Location: reportTwinsCompanyPerson.php");

} else {
    if(isset($_REQUEST['data'])){
        $vals=explode(",", $_REQUEST['data']);
    }
    $query="select c.*, p.*, cp.* from companyPerson cp, company c, person p 
        where 
        c.companyId=cp.companyId and
        p.personId=cp.personId and
        (cp.companyId, cp.personId) in 
        (select companyId, personId from (select companyId, personId, count(*) as nr from companyPerson where companyId>1 and personId>1 group by 1, 2) c where nr>1) 
        order by c.companyName, p.lastName ";
    
    
    $res = $conn->query($query);
}


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

<div class="container mx-auto" style="max-width: 70%">
<h3 class="my-3">companyPerson table records with same personId and companyId</h3>
<td>
<?php
if($showCheck==0){
?>
<a class="btn btn-outline-secondary float-left" type="button" id="back" style="margin-right: 1rem" href="reportTwinsCompanyPerson.php">Back&Reset</a>
<button class="btn btn-outline-primary float-left" type="button" id="backRestore" >Back&Re-select</button>
  
<?php
} else {
?>
    <button class="btn btn-outline-primary float-left" type="button" id="uncheckAll" >Uncheck All</button>
<?php    
}
?>


</td>
<?php
if($showCheck==1){
?>
<button class="btn btn-secondary float-right" type="button" id="next">Delete selected ... </button>
  
<?php
} else {
?>

<button class="btn btn-danger float-right" type="button" id="doDelete">Delete all!</button>


<?php
}
     
?>

    <table class="table table-striped table-hover my-3" id="tableError">
        <thead class="thead-dark">
            <tr>
                <td>CompanyPersonId</td>
                <td>First Name/Last Name</td>
                <td>Company Name</td>
                <td>Arbitrary Title</td>
                
                
<?php
if($showCheck==1){
?>
                <td>Select</td>
                

<?php
}
     
?>

<?php
if(!(isset($_REQUEST['act']) && $_REQUEST['act']=='doDelete')){
?>
                
                  <td>Details</td>

<?php
}
     
?>

            </tr>
        </thead>
        <tbody>
            <?php        
            while($row=$res->fetch_assoc()){
                extract($row);
                $canDelete=canDelete("companyPerson", "companyPersonId", $companyPersonId);

            ?>
                <tr class="<?php echo ($canDelete?"":"bg-warning");?>">
                <td><a href="../companyperson/<?=$companyPersonId ?>" target="_blank"><?=$companyPersonId ?></a></td>
                <td><a href="../person/<?=$personId ?>" target="_blank"><?=$firstName."/".$lastName ?></a></td>
                <td><a href="../company/<?=$companyId ?>" target="_blank"><?=$companyName?></a></td>
                <td><?=$arbitraryTitle ?></td>

<?php
if($showCheck==1 ){
?>
                  <td><input type="checkbox" tag="<?=$companyPersonId?>" class="chkbox" <?php echo in_array($companyPersonId, $vals)?"checked":""; ?> <?=($canDelete?"":"disabled") ?> ></td>
  
<?php
}
     
?>
                <td><a href="idRelationsDetail.php?tableName=companyPerson&tableColumn=companyPersonId&keyValue=<?=$companyPersonId?>" target="_blank"><?=($canDelete?"":"Details") ?></a></td>
                </tr>


            <?php
            }
            ?>


        </tbody>
    </table>
    </div>
</div>

<div style="display:none">
<form action="reportTwinsCompanyPerson.php" method="post" id="formSubmit">
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
        "order": [[ 2, "asc" ]],
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
        chkboxes = $('.chkbox:checked'); 
        $('input[name=act]').val('doDelete');
        $('input[name=data]').val(<?php echo isset($_REQUEST['data'])?$_REQUEST['data']:"" ?>);
        $('#formSubmit').submit();
        return;
        return;
    });
});
</script>
</body>

</html>

