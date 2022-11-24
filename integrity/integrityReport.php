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
    $query="select cp.companyPersonId, cp.companyId as masterCompanyId, cp.personId as masterPersonId, cp.arbitraryTitle, 
        p.*, 
        c.* 
        from 
            companyPerson cp 
                left join person p on cp.personId=p.personid
                left join company c on  cp.companyId=c.companyId			
        where 
            (c.companyId is not null or p.personId is not null) and
            (c.companyId is null
            or  p.personId is null) and 
            cp.companyPersonId in ".str_replace("[", "(", str_replace("]", ")", $_REQUEST['data']))."
        order by companyId, companyName, personId, lastName, firstName";


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
    header("Location: integrityReport.php");

} else {
    if(isset($_REQUEST['data'])){
        $vals=explode(",", $_REQUEST['data']);
    }
    $query="select cp.companyPersonId, cp.companyId as masterCompanyId, cp.personId as masterPersonId, cp.arbitraryTitle, 
        p.*, 
        c.* 
        from 
            companyPerson cp 
                left join person p on cp.personId=p.personid
                left join company c on  cp.companyId=c.companyId			
        where 
            (c.companyId is not null or p.personId is not null) and
            (c.companyId is null
            or  p.personId is null)
        order by companyId, companyName, personId, lastName, firstName";
    
    
    $res = $conn->query($query);
}


?>
<html>
<head> 
<title>
    Integrity Test - companyPerson broken links
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
<h2 class="my-3">companyPerson table integrity issues</h2>
<td>
<?php
if($showCheck==0){
?>
<a class="btn btn-outline-secondary float-left" type="button" id="back" style="margin-right: 1rem" href="integrityReport.php">Back&Reset</a>
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
                if(($personId!==null && $masterCompanyId==0) || ($companyId!==null && $masterPersonId==0)){
                    continue;
                }
            ?>
                <tr class="<?php echo ($canDelete?"":"bg-warning");?>">
                <td><a href="../companyperson/<?=$companyPersonId ?>" target="_blank"><?=$companyPersonId ?></a></td>
                <?php
                    if($personId===null){
                ?>
                    <td class="text-danger">missing Id=<?=$masterPersonId ?></td>
                <?php } else { ?>
                        <td><a href="../person/<?=$masterPersonId ?>" target="_blank"><?=$firstName."/".$lastName ?></a></td>
                <?php } ?>

                <?php
                    if($companyId===null){
                ?>
                    <td class="text-secondary">missing Id=<?=$masterCompanyId ?></td>
                <?php } else { ?>
                    <td><a href="../company/<?=$masterCompanyId ?>" target="_blank"><?=$companyName?></a></td>
                <?php } ?>

<?php
if($showCheck==1){
?>
                  <td><input type="checkbox" tag="<?=$companyPersonId?>" class="chkbox" <?php echo in_array($companyPersonId, $vals)?"checked":""; ?>  <?=($canDelete?"":"disabled") ?>></td>
  
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
<form action="integrityReport.php" method="post" id="formSubmit">
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

