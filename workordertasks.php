<?php 

require_once './inc/config.php';
require_once './inc/perms.php';

do_primary_validation(APPLICATION_FATAL_ERROR);

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v = new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'workOrderId');
$v->rule('integer', 'workOrderId');
$v->rule('min', 'workOrderId', 1);

if( !$v->validate() ) {
    $errorId = '637425051645278490';
    $logger->error2($errorId, "workOrderId : " . $_REQUEST['workOrderId'] ."  is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = "Invalid workOrderId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die(); 
}

$workOrderId = intval($_REQUEST['workOrderId']);

if (!WorkOrder::validate($workOrderId)) {
    $errorId = '637425052578750569';
    $logger->error2( $errorId, "The provided workOrderId ". $workOrderId ." does not correspond to an existing DB workOrder row in workOrder table");
    $_SESSION["error_message"] = "Invalid workOrderId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die(); 
} 

$workOrder = new WorkOrder($workOrderId, $user);
$job = new Job($workOrder->getJobId());
// END of validating inputs 

// Combined Elements 
$combinedElements = $job->getElements($workOrderId);
$elementIds2 = [];
$elementsNames2 = [];
$nameString="";
if (!$error) { // test added 2020-03-25 JM 
  
    foreach ($combinedElements as $element) {
        if (!$element->getElementId()) {
            $elementName = 'General';
        } else {
            $elementName = $element->getElementName();
        }
        $nameString .= $elementName;
        $elementIds2[]=$element->getElementId();
        $elementsNames2[] = $element->getElementName();
        
        
    }
    unset($elementId, $element);
}

// Single Elements
$elements = $job->getElements();
$elementIds = [];
$elementsNames = [];
$nameString="";
if (!$error) { // test added 2020-03-25 JM 
  
    foreach ($elements as $element) {
        if (!$element->getElementId()) {
            $elementName = 'General';
        } else {
            $elementName = $element->getElementName();
        }
        $nameString .= $elementName;
        $elementIds[]=$element->getElementId();
        $elementsNames[] = $element->getElementName();
        
        
    }
    array_push($elementsNames,"General");
    unset($elementId, $element, $elementName);

}


$tasks = array(); // associative array, see following query for indexes
/*
$result = $db->query("UPDATE task t inner join task p ON t.parentId=p.taskId SET t.groupName=p.description");
$result = $db->query("UPDATE task set groupName=description where groupName is NULL");
*/

$query =" SELECT taskId, description, billingDescription, taskTypeId, parentId, groupName";
$query .= " FROM " . DB__NEW_DATABASE . ".task ";
$query .= " WHERE ";
$query .= " active = 1 AND taskId <> 1 ORDER BY task.description ASC "; 
/*
$query =" SELECT taskId, description, billingDescription, taskTypeId, parentId, groupName";
$query .= " FROM " . DB__NEW_DATABASE . ".task ";
$query .= " WHERE taskId IN (SELECT MIN(taskId) FROM " . DB__NEW_DATABASE . ".task GROUP BY description)";
$query .= " AND active = 1 AND parentId <> 0 AND taskId <> 1 ORDER BY task.description ASC "; 
*/
/*
echo $query;
die();
*/
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
} else {
    $logger->errorDB('159423289911', 'Hard DB error', $db);
}


$crumbs = new Crumbs($workOrder, $user);

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$workOrderTaskIdExists = false; // will be set true if we find this workOrderTaskId in the workOrder.

// check if we have a contract for this WO.

$contract = $workOrder->getContractWo($error_is_db);
if($error_is_db) {
    $errorId = '637804520770968388';
    $error = "We could not get the Contract for this WO. Database Error. Error Id: " . $errorId; // message for User
    $logger->errorDB($errorId, "getContractWo() method failed.", $db);
}

$contractStatus = 0;
$blockAdd = false; // if true, Block add/delete tasks/structures of tasks.

if(!$error) {
    if($contract) {
        $contractStatus = intval($contract->getCommitted()); // Contract status
    } 
}

// no update for: 3, 4, 5, 6.
$arrNoUpdate = [3, 4, 5, 6];
if($contractStatus && in_array($contractStatus, $arrNoUpdate)) {
    $blockAdd = true;
}

$invoices = $workOrder->getInvoices($error_is_db);
$errorInvoices = '';
if($error_is_db) { //true on query failed.
    $errorId = '637831986062109869';
    $errorInvoices = "We could not check for Invoices. Database Error."; // message for User
    $logger->errorDB($errorId, "WorkOrder::getInvoices() method failed", $db);
}
if ($errorInvoices) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorInvoices</div>";
}
unset($errorInvoices);

// no update if invoices exists.

if($invoices) {
    $blockAdd = true;
}


include_once BASEDIR . '/includes/header.php';
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>


<link rel="stylesheet" href="../styles/kendo.common.min.css" />
<link rel="stylesheet" href="../styles/kendo.material-v2.min.css" />
<script src='https://cdnjs.cloudflare.com/ajax/libs/jeditable.js/1.7.3/jeditable.min.js'> </script>
<link rel="stylesheet" href="https://kendo.cdn.telerik.com/2021.2.616/styles/kendo.default-v2.min.css" />
<!-- <script src='/js/jquery.min.js' ></script> 

 -->
<script src='/js/kendo.all.min.js' ></script>
<script>

function copyToClip(str) {
  function listener(e) {
    e.clipboardData.setData("text/html", str);
    e.clipboardData.setData("text/plain", str);
    e.preventDefault();
  }
  document.addEventListener("copy", listener);
  document.execCommand("copy");
  document.removeEventListener("copy", listener);
};



kendo.pdf.defineFont({
            "DejaVu Sans": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans.ttf",
            "DejaVu Sans|Bold": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Bold.ttf",
            "DejaVu Sans|Bold|Italic": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Oblique.ttf",
            "DejaVu Sans|Italic": "https://kendo.cdn.telerik.com/2016.2.607/styles/fonts/DejaVu/DejaVuSans-Oblique.ttf",
            "WebComponentsIcons": "https://kendo.cdn.telerik.com/2017.1.223/styles/fonts/glyphs/WebComponentsIcons.ttf"
        });
</script>

<style>

    .k-sprite {
        /*background-image: url("styles/coloricons-sprite.png"); */
    }
    li {
        line-height: 2;
       
    }
    .rootfolder { background-position: 0 0; }
    .folder { background-position: 0 -16px; }
    .pdf { background-position: 0 -32px; }
    .html { background-position: 0 -48px; }
    .image { background-position: 0 -64px; }
</style>

<style>
input.tally.bad-value {border-color:red;}
input.tally.confirmed {background-color:LightGreen;}

body, #container,table { background-color: #fff; }
#stampId {
    display: inline!important;
    width: 20%!important;
}
.newstatus {
    width: 60%!important;
}

.k-treeview span.k-in {
    cursor: default;
    font-size: 14px;
}

.nav-link {
    font-size: 13px;
}

#change-elements-used, #change-elements-used2 {
    color: #000;
    font-family: Roboto,"Helvetica Neue",sans-serif;
}

#change-elements-used:hover, #change-elements-used2:hover {
    color: #fff;
}

/* Copy link button */
#copyLink {
    color: #000;
    font-family: Roboto,"Helvetica Neue",sans-serif;
    font-size: 11px;
    font-weight: 600;
}

#copyLink:hover {
    color: #fff;
    font-size: 11px;
    font-weight: 600;
}
div.sticky {
  position: -webkit-sticky; /* Safari */
  position: sticky;
  top: 0;
  background-color: #fff!important;
  

}

/* Combo Select - ASC | DESC */
#comboSelect {
    width: auto;
    float: right;
}


.sticky {
    position: fixed;
    top: 0;
    width: 100%;
}

.headerSticky {
    padding: 10px 16px;
    color: #f1f1f1;
    /*background-color: #808080!important;*/
    opacity:1;

}

div.sticky {
    position: -webkit-sticky;
    position: sticky;
    top: 0;
    background-color: #fff!important;
    z-index: 10;
    border-bottom: 1px solid #f0f0f0;
   
}

.headerSticky2 {
    padding: 10px 16px;
    color: #f1f1f1;
    /*background-color: #808080!important;*/
    opacity:1;

}

div.headerSticky2 {
    position: -webkit-sticky;
    position: sticky;
    top: 0;
    background-color: #fff!important;
    z-index: 10;
    border-bottom: 1px solid #f0f0f0;
   
}

#copyLinkGeneralPages:hover {
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}
#firstLinkToCopy {
    color: #000;
    font-size: 18px;
    font-weight: 700;
}

</style>

<div id="container" class="clearfix">
    <?php $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/workordertasks/' . rawurlencode($workOrderId); ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
            [WO]&nbsp;<a id="linkWoToCopy" href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            [WOT]&nbsp; <a href="<?= $urlToCopy?>" >Add Task </a>
            <button id="copyLink" title="Copy WO link" class="btn btn-outline-secondary btn-sm mb-1" onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>    
        <span id="linkToCopy" style="display:none"> [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
            [WO]&nbsp;<a href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>  [WOT]&nbsp; <a href="<?= $urlToCopy?>" >Add Task </a></span>
        
        <span id="linkToCopy2" style="display:none"> <a href="<?= $urlToCopy?>">[J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<?php echo $job->getNumber();?>)
            [WO]&nbsp; <?php echo $workOrder->getDescription();?>[WOT]&nbsp; Add Task  </a></span> 
    </div>
    <div class="main-content" style="padding-top: 15px!important">
        
        <div class="container-fluid my-6">
            <div class="row">
                <span class="col-sm-8"> 
                </span>
      
                <span class="col-sm-4">
                    <?php $title = ' The Contract state decline this action ';?>
                    
                    <?php if($blockAdd == true) { ?>
                        <button id="change-elements-usedDisabled2" title="Disabled - contract exists." disabled style="float:right; margin-right: -14px!important;color:#fff;" class="btn btn-secondary btn-sm ml-2 mr-0">Combine Elements</button>
                        <button id="change-elements-usedDisabled" title="Disabled - contract exists." disabled style="float:right; color:#fff;" class="btn btn-secondary btn-sm">Select Single Element(s)</button>&nbsp;&nbsp;

                    <?php } else { ?>
                        <button id="change-elements-used2"  style="float:right; margin-right: -14px!important;" class="btn btn-outline-secondary btn-sm ml-2 mr-0">Combine Elements</button>
                        <button id="change-elements-used"  style="float:right" class="btn btn-outline-secondary btn-sm">Select Single Element(s)</button>&nbsp;&nbsp;
                    <?php }?>
                </span>
            </div>
        </div>
        <div class="card mt-3 pb-2">
            <div class="row">
                <div class="col-sm-5">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Tasks</a>
                        </li>
                        <?php if($blockAdd == true) { ?>
                            <li class="nav-item" style="display:none">
                                <a class="nav-link" id="profile-tab" disabled title="Disabled - contract exists." data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Templates</a>
                            </li>
                        <?php } else { ?>
                        <li class="nav-item" style="display:none">
                            <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Templates</a>
                        </li>
                        <?php } ?>
                        <?php if($blockAdd == true) { ?>
                        <li class="nav-item" >
                            <a class="nav-link"  id="profile-tab2" style="background-color: #f7f7f7;" disabled title="Disabled - contract exists." data-toggle="tab"  role="tab" aria-controls="profile2" aria-selected="false">WorkOrder Templates</a>
                        </li>
                        <?php } else { ?>
                            <li class="nav-item">
                                <a class="nav-link" id="profile-tab2"  data-toggle="tab" href="#profile2" role="tab" aria-controls="profile2" aria-selected="false">WorkOrder Templates</a>
                            </li>
                        <?php } ?>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">


                                <div id="tasksList" style="max-height: 90%; overflow-y:scroll;">
                                    <div class="headerSticky2" id="myHeader2"> 
                                        <input class="k-textbox" id="inputSearch" placeholder="Search by Text..." />
                                    </div>
                                    <div class="demo-section wide k-content" >
                                        <div class="treeview-flex" >
                                        </br></br>
                                            <div id="treeview-kendo"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="profile"  role="tabpanel" aria-labelledby="profile-tab">
                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; overflow-y:scroll;">
                                <div id="taskTemplateList">
                                    <div class="headerSticky" id="myHeader"> 
                                        <input class="k-textbox"  id="inputSearchJobWo"
                                            placeholder="Search by Package..."/>
                                        
                                    
                                        <select id="comboSelect" class="form-control form-control-sm mt-0.2">
                                            <option class="form-control form-control-sm" value="taskPackageId ASC">Date asc</option>
                                            <option class="form-control form-control-sm" value="taskPackageId DESC">Date desc</option>
                                            <option class="form-control form-control-sm" value="packageName ASC">Name asc</option>
                                            <option class="form-control form-control-sm" value="packageName DESC">Name desc</option>
                                        </select>
                                    </div>
                                        </br></br>
                                    <div class="demo-section wide k-content">
                                  
                                        <div class="treeview-flex">
                                         

                                            <div id="treeview-telerik" ></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="tab-pane fade" id="profile2" role="tabpanel" aria-labelledby="profile-tab">
                                <div class="card mt-3" style="min-height: 600px; max-height: 600px; overflow-y:scroll;">
                                <div id="JobWorkorders">
                                    <div class="demo-section wide k-content">
                                        <!-- Search for a Job Number. -->
                                        <form name="" action="" method="GET" id="formSearchJobNumberWO">
                                            <table border="0" cellpadding="0" cellspacing="0">
                                                <input type="hidden" name="act" value="search">
                                                <tr>
                                                    <td colspan="2">Enter Job Number</td>
                                                </tr>
                                                <tr>
                                                    <td><input type="text" class="form-control form-control-sm"  name="qSearchJob" id="qSearchJob" value="" size="40" maxlength="64"></td>
                                                    <td width="50%"> <button type="submit" id="searchJobNumberWO" class="btn btn-secondary btn-sm mr-auto ml-auto ">Search</button></td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                    <div id="divJobWo"></div>
                                    <div class="demo-section wide k-content">
                                        <div class="treeview-flex">
                                            <div id="treeview-telerik-job-wo"></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-7">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="home-tab" data-toggle="tab" href="#wotasks" role="tab"  aria-controls="home" aria-selected="true">WorkOrder Elements Tasks</a>
                    </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">

                                <div id="workOrderTaskList">
                                    <div class="demo-section wide k-content">
                                        <div class="treeview-flex">
                                            <div id="treeview-telerik-wo"></div>
                                            <div id="gantt"></div>
                                          
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div> 
        </div>
        
 
                
        
    </div>
</div>

<script>
window.onscroll = function() {myFunction()};

var header = document.getElementById("myHeader");
var sticky = header.offsetTop;

function myFunction() {
  if (window.pageYOffset > sticky) {
    header.classList.add("sticky");
  } else {
    header.classList.remove("sticky");
  }
}

</script>

<style>

    .table td, .table th { 
        border: 0px solid #fff;
    }
    .sticky {
    position: fixed;
    top: 0;
    width: 100%
    }


    .ui-dialog .ui-dialog-titlebar-close { 
        top: 90%;
    }
    .elementText {
        font-family: Roboto,"Helvetica Neue",sans-serif;
        font-size:15px;
    }

    .elementText2 {
        font-family: Roboto,"Helvetica Neue",sans-serif;
        font-size:18px;
    }
    .one-task-link {
        cursor: pointer;
        
        margin-top:5px;

    }
    .one-task-link:hover {
        text-decoration: underline; /* Why does this have no effect? (worked around it below) */
    }
    #thElementName, #thElementName2 {
        background-color: #e8e8e8;
        border-color: #c69500;
        color: #3a74a3;
    }
    .ui-dialog > .ui-widget-header {
        background: #fff!important;
        border: 0px solid #fff;
    }

    .ui-dialog .ui-dialog-title { 
        margin: -0.7em!important;
    }

    #selectElements, #combineElements {
        font-size: 16px;
    }

    [id^="deleteCombineElement"] {
        font-size:13px; 
        font-weight:500;
        padding-left:17px;
        cursor:pointer;
    }
  
</style>

<div id="elementdialog" display="none;z-index: 30;">
        <form name="elementForm" id="elementForm"  >
            <input type="hidden" name="workOrderId" value="<?= intval($workOrderId) ?>"> <?php /* hidden workOrderId */ ?> 
            <table border="0" class="table table-sm" id="elementTable" cellpadding="0" cellspacing="0" width="200">
                <tr>
                    <td class="elementText2" colspan="2">Which element(s) are you working with?</td>
                </tr>
                <tr id="trDataELements">
                    <th class="elementText2" colspan="2" class="thElementName">Single Elements</th>
                </tr>
                <!-- George - Here data will be populated by the Ajax request -->
                <tr id="headCombinedElements" >
                    <th class="elementText2 thElementName" colspan="2" >Combined Elements</th>
                </tr> 
                <tr>
                    <td colspan="2" style="text-align:center"><input type="button" class="btn btn-secondary btn-sm mt-3" id="selectElements"  onClick="return checkElements();" value="Select"></td>
                </tr>
                
                
            </table>
        </form>
</div>

<!--  Combine ELements -->
<div id="elementdialog2" display="none; z-index: 30;">
        <form name="elementForm2" id="elementForm2"  method="post" action="">
            <input type="hidden" name="workOrderId" value="<?= intval($workOrderId) ?>"> <?php /* hidden workOrderId */ ?> 
            <input type="hidden" name="act" value="combineElements">
            <table border="0" class="table table-sm" cellpadding="0" cellspacing="0" width="200">

                <tr>
                    <td class="elementText2" colspan="2">Combine elements you are working with</td>
                </tr>
                <tr id="trDataELements2">
                    <th class="elementText2" colspan="2" class="thElementName2">Single Elements</th>
                </tr>
               
          
                <tr>
                    <td colspan="2" style="text-align:center"><input type="button" name="combineElements" class="btn btn-secondary btn-sm mt-3"  id="combineElements"  onClick="return checkElements2();" value="Combine"></td>
                </tr>
                
                
            </table>
        </form>
</div>


<script>
// DISABLED TAB 2 and TAB 3
var blockAddTab = <?php echo json_encode($blockAdd);?>;
$("#profile-tab, #profile-tab2").on("click", function(e) {
    if (blockAddTab) {
        e.preventDefault();
        return false;
    }
});





// Change text Button after Copy.
$('#copyLink').on("click", function (e) {
    $(this).text('Copied');
});
$("#copyLink").tooltip({
    content: function () {
        return "Copy WO Link";
    },
    position: {
        my: "center bottom",
        at: "center top"
    }
});

var elementsList11 = new Array();
    $(function() {
        $("#elementdialog").show().dialog({
            autoOpen:false, 
            width:400, 
            height:200,
            closeText: '',
            close : function() {
                $('input:checkbox:checked').each(function() {
                    $(this).prop('checked', false); // remove checked elementes on close.
                   
                }); 
              },
            });
        
        $("#change-elements-used").click(function(event) {
            event.preventDefault();
               
            $( "#elementdialog" ).dialog({
                position: { my: "center bottom", at: "center top", of: $(this) },
                open: function(event, ui) {
                    $(".ui-dialog-titlebar-close", ui.dialog | ui ).show();
                    $(".ui-dialog-titlebar", ui.dialog | ui ).show();

               
                }
            });
            $('#elementdialog').dialog({height:'auto', width:'auto'});            
            $('#elementdialog').dialog("open");

            // George - Prevent both Dialog to be open at the same time
            if($('#elementdialog').dialog('isOpen')) {
                $('#elementdialog2').dialog("close");

         
            }

            // Single Elements
            $("table #singleElementRow").remove();
            $("table #singleElementRow").html('');

            // Combined Elements
            $("table #trCombined").remove();
            $("table #trCombined").html('');

            $.ajax({
                url: '/ajax/get_elements_wo.php',
                data: {
                    workOrderId: <?php echo $workOrder->getWorkOrderId(); ?>,
                    jobId: <?php echo  $job->getJobId() ; ?>
                },
                async:false,
                type:'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                   
                    if (data['status']) {
                        if (data['status'] == 'success') { 

                            // Single elements. The General logic is handled in ajax/get_elements_wo.php.
                            $(data['single']).each(function(i, value) {
                                var rowTd = $('<tr id="singleElementRow">');
                                rowTd.append( $('<td>').html( '<input type="checkbox"  id="elementId'+ value["elementId"]+'" class="Checked" name="elementId[]" value="'+ value["elementId"]+'">'));
                                rowTd.append( $('<td>').html( '<a id="linkElement'+ value["elementId"]+'" class="elementText" data-elementid="'+ value["elementId"]+'">'+ value["elementName"]+''));
                                $("#trDataELements").after(rowTd);
                            });
         
                            // Combined Elements. 
                            $(data['combined'][0]).each(function(i, value2) {
    
                                var rowTd2 = $('<tr id="trCombined">');
        
                                rowTd2.append( $('<td>').html( '<input type="checkbox"  id="elementId'+ value2["elementId"]+'" class="Checked" name="elementId[]" value="'+ value2["elementId"]+'">'));
                                rowTd2.append( $('<td>').html( '<a id="linkElement'+ value2["elementId"]+'" class="elementText" data-elementid="'+ value2["elementId"]+'">'+ value2["elementName"]+'  <span id="deleteCombineElement'+ value2["elementId"]+'" data-elementid="'+ value2["elementId"]+'">X'));

                              
                                $("#headCombinedElements").after(rowTd2);
                            }); 

                        } else {
                            console.log("error");
                            
                        }
                    } else {
                        alert('error: no status');
                    } 
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error');
                }
            });


            
                // Get Single elements Ids.
               $("input.Checked:checkbox").change(function() {
                    elementsList10 = new Array();
                    $('input:checkbox:checked').each(function() {

                        elementsList10.push(
                            $(this).val()
                        )
                    });
                    elementsList11 = elementsList10;

                });  
     
            // Delete Combined Element if no structure of tasks found.
            // Else alert message: We could not delete this Element. Structure found.
            $("#elementTable tbody").on('click', "[id^='deleteCombineElement']", function (ev) {
                let $this = $(this);
                ev.stopImmediatePropagation(); 
                if (!confirm('Are you sure to Delete this Element?')) {
                return;
                ev.preventDefault(); 
                } else {
                    $.ajax({
                        url: '/ajax/delete_combined_elements.php',
                        data: {
                            combinedElementId: $this.data('elementid'),
                            workorderId: <?php echo $workOrder->getWorkOrderId(); ?>
                        },
                        async:false,
                        type:'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['status']) {
                                if (data['status'] == 'success') {
                                    $this.closest('tr').remove();
                
                                } else {
                                    alert(data['error']);
                                }
                            } else {
                                alert('error: no status');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('error');
                        }
                    });
                }
                
            });

        });
      
    });

    $(function() {
        $("#elementdialog2").show().dialog({
            autoOpen:false, 
            width:400, 
            height:200,
            closeText: '',
            close : function() {
                $('input:checkbox:checked').each(function() {
                    $(this).prop('checked', false); // remove checked elementes on close.
                }); 
              }
            });
        
        $("#change-elements-used2").click(function(event) {
            event.preventDefault();
            
            $( "#elementdialog2" ).dialog({
                position: { my: "center bottom", at: "center top", of: $(this) },
                open: function(event, ui) {
                    $(".ui-dialog-titlebar-close", ui.dialog | ui ).show();
                    $(".ui-dialog-titlebar", ui.dialog | ui ).show();
                }
            });
            $('#elementdialog2').dialog({height:'auto', width:'auto'});            
            $('#elementdialog2').dialog("open");
            // George - Prevent both Dialog to be open at the same time
            if($('#elementdialog2').dialog('isOpen')) {
                $('#elementdialog').dialog("close");
            }

            // Single Elements
            $("table #singleElementRow2").remove();
            $("table #singleElementRow2").html('');

            $.ajax({
                url: '/ajax/get_elements_wo.php',
                data: {
                    workOrderId: <?php echo $workOrder->getWorkOrderId(); ?>,
                    jobId: <?php echo  $job->getJobId() ; ?>
                },
                async:false,
                type:'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                   
                    if (data['status']) {
                        if (data['status'] == 'success') { 

                            // Single elements to Combine. The General logic is handled in ajax/get_elements_wo.php.
                            $(data['single']).each(function(i, value) {
                                var rowTd = $('<tr id="singleElementRow2">');
                                rowTd.append( $('<td>').html( '<input type="checkbox"  id="elementId'+ value["elementId"]+'" class="Checked" name="elementId2[]" value="'+ value["elementName"]+'">'));
                                rowTd.append( $('<td>').html( '<a id="linkElement'+ value["elementId"]+'" class="elementText" data-elementid="'+ value["elementId"]+'">'+ value["elementName"]+''));
                                $("#trDataELements2").after(rowTd);
                            });
         

                        } else {
                            console.log("error");
                            
                        }
                    } else {
                        alert('error: no status');
                    } 
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error');
                }
            });

 
        });
    });

</script>


<script>

    $('#elementdialog.closelink').click(function () {
        $("#elementdialog").dialog("close");
        $("#elementdialog").destroy();
    });

    $('#elementdialog2.closelink').click(function () {
        $("#elementdialog2").dialog("close");
       
    });

              
</script>


        <script id="treeview" type="text/kendo-ui-template">

            # if (!item.items && item.spriteCssClass) { #
            #: item.text #
            <span class='k-icon k-i-close kendo-icon'></span>
            # } else if(!item.items && !item.spriteCssClass) { #
            <span class="k-sprite html"></span>
            #   if(item.extraDescription) { #
                    #:item.text #
                    <span class="k-sprite html extraDescription">  </span>
                  
                    #: item.extraDescription #
                    # } else { #
                    #: item.text #
                    # }#
            <span class='k-icon k-i-close telerik-icon'></span>
            # } else if (item.items && item.spriteCssClass){ #
                #   if(item.extraDescription) { #
                    #:item.text #
                    <span class="k-sprite html extraDescription">  </span>
                   
                     #:  item.extraDescription #
                 
                    # } else { #
                    #: item.text #
                    # }#
            
            # } else { #
            <span class="k-sprite folder"></span>
            #   if(item.extraDescription) { #
                    #:item.text #
                    <span class="k-sprite html extraDescription">  </span>
                   
                     #:  item.extraDescription #
                 
                    # } else { #
                    #: item.text #
                    # }#
            # } #
            
    </script>

  
  <!-- OLD CODE-->
    <script>
        $("#treeview-kendo").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            datasource: [{
                id: 1, text: "my documents", expanded: true, spritecssclass: "rootfolder", items: [
                    {
                        id: 2, text: "kendo ui project", expanded: true, spritecssclass: "folder", items: [
                            { id: 3, text: "about.html", spritecssclass: "html" },
                            { id: 4, text: "index.html", spritecssclass: "html" },
                            { id: 5, text: "logo.png", spritecssclass: "image" }
                        ]
                    },
                    {
                        id: 6, text: "reports", expanded: true, spritecssclass: "folder", items: [
                            { id: 7, text: "february.pdf", spritecssclass: "html" },
                            { id: 8, text: "march.pdf", spritecssclass: "html" },
                            { id: 9, text: "april.pdf", spritecssclass: "pdf" }
                        ]
                    }
                ]
            }],
            draganddrop: true,
            checkboxes: {
                checkchildren: true
            },
            loadondemand: true
        });

        $("#treeview-telerik").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            datasource: [{
                id: 1, text: "my documents", expanded: true, items: [
                    {
                        id: 2, text: "new web site", expanded: true, items: [
                            { id: 3, text: "mockup.pdf" },
                            { id: 4, text: "research.pdf" },
                        ]
                    },
                    {
                        id: 5, text: "reports", expanded: true, items: [
                            { id: 6, text: "may.pdf" },
                            { id: 7, text: "june.pdf" },
                            { id: 8, text: "july.pdf" }
                        ]
                    }
                ]
            }],
            draganddrop: true,
            checkboxes: true,
            loadondemand: true
        });

    </script>
    <style>
        @media screen and (max-width: 680px) {
            .treeview-flex {
                flex: auto !important;
                width: 100%;
            }
        }
    </style>
  <!-- OLD CODE-->
<?php

$query = "SELECT elementId as id, elementName as Title, null as parentId, 
null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, null as internalTaskStatus, null as tally, null as hoursTime,
elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren 
from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
UNION ALL
SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId, 
w.extraDescription as extraDescription, w.internalTaskStatus as internalTaskStatus, tl.tally as tally, wt.tiiHrs as hoursTime, getElement(w.workOrderTaskId),
e.elementName, false as Expanded, false as hasChildren
from workOrderTask w
LEFT JOIN task t on w.taskId=t.taskId

LEFT JOIN (

    SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
    FROM workOrderTaskTime wtH
    GROUP BY wtH.workOrderTaskId
    ) AS wt
    on wt.workOrderTaskId=w.workOrderTaskId

LEFT JOIN taskTally tl on w.workOrderTaskId=tl.workOrderTaskId
LEFT JOIN element e on w.parentTaskId=e.elementId
WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null ORDER BY FIELD(elementName, 'General') DESC, internalTaskStatus DESC";

    $res=$db->query($query);

    $out=[];
    $parents=[];
    $elements=[];

    while( $row=$res->fetch_assoc() ) {
        $out[]=$row;
        if( $row['parentId']!=null ) {
        $parents[$row['parentId']]=1;
    }
    if( $row['taskId']==null)    {
        $elements[$row['elementId']] = $row['elementName'] ;
        }
    }

    for( $i=0; $i<count($out); $i++ ) {
        if( $out[$i]['Expanded'] == 1 )
        {
            $out[$i]['Expanded'] = true;
        } else {
            $out[$i]['Expanded'] = false;
        }
        
        if($out[$i]['hasChildren'] == 1)
        {
            $out[$i]['hasChildren'] = true;
            
        } else {
            $out[$i]['hasChildren'] = false;
        } 

        if( isset($parents[$out[$i]['id']]) ) {
            $out[$i]['hasChildren'] = true;
           
        }
        if ( $out[$i]['elementName'] == null ) {
            $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
        }

    }

?>


<?php
// OLD CODE
$query = "select c.elementId, c.elementName, d.taskId as id, d.text as Title, d.extraDescription, d.parentTaskId as parentId, d.workOrderTaskId from
(
select e.elementId, e.elementName
from element e join workOrder wo on e.jobId=wo.jobId
where wo.workOrderId=" . intval($workOrder->getWorkOrderId()) . " ) c


left join
(
SELECT t.taskId, t.description as text, w.extraDescription, w.parentTaskId, w.workOrderTaskId, e.elementId, e.elementName
FROM " . DB__NEW_DATABASE . ".task t JOIN " . DB__NEW_DATABASE . ".workOrderTask w ON w.taskId = t.taskId
JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement we ON we.workOrderTaskId = w.workOrderTaskId
left JOIN " . DB__NEW_DATABASE . ".element e ON e.elementId=we.elementId
WHERE t.taskId <> 1 AND w.workOrderId = " . intval($workOrder->getWorkOrderId()) . " ) d on c.elementId=d.elementId";


$elementTasks = array();
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $elementTasks[] = $row;

    }
} 



    $newPack2 = array();
    $allTasksPack2 = array();
    foreach ($elementTasks as $a) {
        $newPack2[$a['parentId']][] = $a;
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
    // END OLD CODE




?>


<?php

    $allPackagesIds = array();
    $allPackages = array();
    $allPackagesNames = array();

    $query = " SELECT taskPackageId, packageName";
    $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackage ORDER BY taskPackageId DESC";


    $taskIds = array();            
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allPackagesIds[] = $row["taskPackageId"];
            $allPackagesNames[] = $row["packageName"];
        }
    } else {
        $logger->errorDB('1594234143344', 'Hard DB error', $db);
    }


    foreach ($allPackagesIds as $package) {

        $query = " SELECT t.taskId, t.description as text, tp.packageName, tpt.taskPackageTaskId, tpt.parentTaskId, tpt.taskPackageId ";
        $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackageTask tpt ";
        $query .= " JOIN  " . DB__NEW_DATABASE . ".taskPackage tp ON tp.taskPackageId = tpt.taskPackageId ";
        $query .= " JOIN   " . DB__NEW_DATABASE . ".task t ON tpt.taskId = t.taskId ";
        $query .= " WHERE tpt.taskPackageId = " . intval($package) . "  ";


        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $allPackages[] = $row;
            }
        }
    }

    unset($allPackagesIds, $package);

    $newPack = array();
    $allTasksPack = array();
    foreach ($allPackages as $a) {
        $newPack[$a['parentTaskId']][] = $a;
    
    }
 
    if($allPackages) {
      
        foreach($allPackages as $key=>$value) {

            
            if($value["parentTaskId"] == "1000" ) {
             
                $createAllTasks2 = createTreePack($newPack, array($allPackages[$key]));
            

                $found = false;
                foreach($allTasksPack as $k=>$v) {
                 
                    if($v['taskPackageId'] == $value['taskPackageId']) {
                        $allTasksPack[$k]['items'][] = $createAllTasks2[0];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $node = array();
                    $node['text'] = $value['packageName'];
                    $node['taskPackageId'] = $value['taskPackageId'];
                    $node['items'][] = $createAllTasks2[0];
                    $allTasksPack[] = $node;
                }
            } 
           
        }
    }
 
    function createTreePack(&$listPack, $parent) {
        $tree = array();
        foreach ($parent as $k=>$l ) {
            if(isset($listPack[$l['taskPackageTaskId']]) ) {
                $l['items'] = createTreePack($listPack, $listPack[$l['taskPackageTaskId']]);
            }
      
            $tree[] = $l;
           
        } 
  
        return $tree;
    }




$colors = array('AntiqueWhite', 'Chartreuse',
        'DarkGoldenRod', 'DarkGrey',
        'DarkOrange', 'DarkSalmon', 'DarkSeaGreen', 'Gold',
        'HotPink', 'OrangeRed', 'Plum', 'YellowGreen');

$used = 0;

?>

    <script>var blockAdd = <?php echo json_encode($blockAdd);?>; </script>

    <!-- Begin Edit Mode Extra Description popup 3 -->
    <ul id="menuEditModeExtraDescription">
        <li>Edit Extra Descrption</li>
    </ul>
    
    <script id="editTemplate" type="text/x-kendo-template"> 

        # 
        var myCustomDescription = "";
        if(node.extraDescription) {
            myCustomDescription = node.extraDescription ; 
        }

        
        #
        <label>Text : <textarea rows="4" cols="50" class="k-textbox" id="myCustomDescription" value="#= node.extraDescription #" >#= myCustomDescription #</textarea></label>
        <button class="k-button k-primary" look="outline">Save</button>
    </script>
    <!-- End Edit Mode -->

    

    <!-- Begin Edit Mode popup 2-->
    <ul id="menuEditModeTab2">
    <li>Edit Node</li>
    </ul>
    
    <script id="editTemplate2" type="text/x-kendo-template"> 
        <label>Text: <input class="k-textbox" value="#= node.text #" /></label>
        <button class="k-button k-primary" look="outline">Save</button>
    </script>
    <!--End Edit Mode -->


    <!-- Gant ELEMENT  DESCRIPTION bold-->
    <script id="column-title" type="text/x-kendo-template">

        # if(parentId == null) { #
        <span  draggable="true"  class="font-weight-bold"">#= Title#</span>

        # } else { #
            <span draggable="true" title="#=Title#" >#=Title#</span>

        # } #

    </script>

    <script id="column-desc" type="text/x-kendo-template">
    # if(parentId != null) { #
            # if(extraDescription) { #
                    <span class='form-control form-control-sm formClassDesc' title="Edit" >#=extraDescription#</span>
            # } else { #
                <span class='form-control form-control-sm formClass' title="Edit" ></span>
            # } #
        # } else { #
            <span ></span>
        # } #
    </script>

    <script>

    // Check -> Select Single element(s)
    var checkElements = function() {
    
        var f = document.getElementById('elementForm');
        var elements = f.elements;
        var c = 0;  
        for (var i = 0, element; element = elements[i++];) {
            if (element.type === "checkbox" && element.checked) {
                c++;
            }        
        }

        if (c == 0) {
            alert('check at least one box if you want to display elements');
        }
        return false;
        
    }

    // Check -> Select combine elements
    var checkElements2 = function() {
    
        var f = document.getElementById('elementForm2');
        var elements = f.elements;
        var c = 0;  
        for (var i = 0, element; element = elements[i++];) {
            if (element.type === "checkbox" && element.checked) {
                c++;
            }        
        }

        if (c == 0 || c == 1) {
            alert('check at least two boxes if you want to combine elements');
        }
        
    }
  


   $(document).ready(function() {




    // Tasks - Tab 1.
    var tasks=<?php echo json_encode($tasks); ?>;
        var items1=[];

        $.each(tasks, function(i,v){
            items1.push({
                id: v.taskId,
                billingDesc: v.billingDescription,
                taskTypeId: v.taskTypeId,
                text: v.groupName + " - " + v.description,
                sprite: "html"
            })
        })
        
        var items = [
          { text: "assets", sprite: "folder" },
          { text: "index.html", sprite: "html" }
        ];
        var tree1= $("#treeview-kendo").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            dataSource: items1,
            dragAndDrop: true,
            allowAdd: true,
            allowCopy: true,
            drop: onDrop
        });
console.log(items1);
        tree1.AllowDefaultContextMenu = true;

    // Search Filter fo Tasks: Tab1.
    $("#inputSearch").on("input", function() {
        var query = this.value.toLowerCase();
        var dataSourceTasks = $("#treeview-kendo").data("kendoTreeView").dataSource;
        filter(dataSourceTasks, query);
    });
    // Sets the "hidden" field on items that match the query.
    function filter(dataSource, query) {
        var hasVisibleChildren = false;
        var data = dataSource instanceof kendo.data.HierarchicalDataSource && dataSource.data();

        for (var i = 0; i < data.length; i++) {
            var item = data[i];

            var text = item.text.toLowerCase();
            
            var itemVisible =
                query === true // parent already matches
                || query === "" // query is empty
                || text.indexOf(query) >= 0; // item text matches query

            var anyVisibleChildren = filter(item.children, itemVisible || query); // pass true if parent matches

            hasVisibleChildren = hasVisibleChildren || anyVisibleChildren || itemVisible;

            item.hidden = !itemVisible && !anyVisibleChildren;
        }

        if (data) {
            // Re-apply the filter on the children.
            dataSource.filter({ field: "hidden", operator: "neq", value: true });
        }

        return hasVisibleChildren;
    }



// ==========================================================
    // tree TaskId's for this Workorder
    // WORKORDER Task Packages

    
    // Gantt
 
    var allTasksWoElements=<?php echo json_encode($out); ?>;

    var gantt = $("#gantt").kendoGantt({
        editable: "incell",
        dataSource : allTasksWoElements,
        schema: {
            model: {
                id: "id",
                parentId :"parentId",
                fields: {
                    id: { from: "id", type: "number" },
                    elementId: { from: "elementId", type: "number" },
                    parentId: { from: "parentId", type: "string" },
                    elementName: { from: "elementName", defaultValue: "", type: "string" },
                    workOrderTaskId: { from: "workOrderTaskId", type: "number" },
                    taskId: { from: "taskId", type: "number"},
                    parentTaskId : { from: "parentTaskId", type: "number" },
                    internalTaskStatus: { from: "internalTaskStatus", type: "number" },
                    text: { from: "text", defaultValue: "", type: "string" },
                    extraDescription: { from: "extraDescription", type: "string" , attributes: {class: "word-wrap"}},
                    //quantity: { from: "quantity", type: "string" },
                    tally: { from: "tally", type: "float", defaultValue: 0  }, 
                    expanded: { from: "Expanded", type: "boolean", defaultValue: true }
            }
                
            }
        },

        columns: [
            { field: "Title", title: "Task", template: $("#column-title").html(), editable: false, width: 400 },
            { field: "extraDescription", title: "Additional Information", template: $("#column-desc").html(), headerAttributes: { style: "white-space: normal"}, editable: true, width: 200 },
           
            { command: [ { name: "destroy", text: " " }   ], title: "", width: "48px" }
           
        ],
        edit: function(e) {
            // George : prevent add/ edit extra Description to Elements.
            if(e.task.parentId == null) {
                //this.closeCell();
                e.preventDefault();
            } 
            // disable edit if internalTaskStatus
            if(blockAdd == true && e.task.internalTaskStatus !=5 ) {
                e.preventDefault();
            }   
    
        },
        dataBound: function(e) {
        },

        toolbar: false,
        header: false,
        //snap: false,
        showWorkHours: false,
        showWorkDays: false,
        listWidth: "100%",
        scrollable: true,
       
        selectable: true,
        dragAndDrop: true,
        drop: true,
      
        draggable: {
           enabled: true,
        },
        dataBound:function(e){
              this.list.bind('dragstart', function(e) {
                  e.preventDefault();
                  return; // Blocked for this Release 4.03.2022
              })
            },

        dataBound:function(e) {
            this.list.bind('drop', function(e) {
                e.preventDefault();
                //onDrop2(e); // Blocked for this Release 4.03.2022
    
                   
                return;
        
            })
        }


    }).data("kendoGantt");


    

    $(document).bind("kendo:skinChange", function () {
        gantt.refresh();
    });


    gantt.bind("dataBound", function(e) { 
       
      
        gantt.element.find("tr[data-uid]").each(function (e) {
        var item = gantt.dataSource.getByUid($(this).attr("data-uid"));
    
            if( item.parentId == null && item.hasChildren != true ) {
                $("[data-uid=" +item.uid + "] ").find(".k-grid-delete").show();
            }

            if(item.hasChildren == true) {
            
                $("[data-uid=" +item.uid + "]").find(".k-grid-delete").hide(); 
            } else {
                $("[data-uid=" +item.uid + "]").find(".k-grid-delete").show(); 
            }
            if(item.internalTaskStatus != null) {
              
                if(blockAdd == true && parseInt(item.internalTaskStatus) !=5 ) {
                    $("[data-uid=" +item.uid + "]").find(".k-grid-delete").prop("disabled",true);
                    $("[data-uid=" +item.uid + "]").find(".formClass").attr('readonly', 'readonly');
                    
                } else {
 
                    $("[data-uid=" +item.uid + "]").find(".formClass").attr("readonly", false);
                    $("[data-uid=" +item.uid + "]").find(".k-grid-delete").prop("disabled",false);
                } 
            }
         
           
       

        });
       
    })


    // Expanded Tree on Workorder Elements Tasks.
    var expandGanttTree = function(e) { 
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].hasChildren) {
                tas[i].set("expanded", true);
            }
            if(tas[i].internalTaskStatus != null) {
              
              if(blockAdd == true && parseInt(tas[i].internalTaskStatus) !=5 ) {
                  $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", true);
                  $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", true);
                  //$("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'none');

              } else {
                  $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", false);
                  $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", false);
                  //$("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'all');
   
              }
          }
      
        }

    }
    expandGanttTree();

    $(function() {
    $(".k-command-cell").tooltip();

    });

    // Hide X for parents. Add tooltip if tally.
    var allRowsInspection = function(e) {
        var item = $("#gantt").data('kendoGantt').dataSource.view();
        //elementsBold();

        for (i = 0; i < item.length; i++) {

            if( item[i].parentId == null && item[i].hasChildren != true ) {
                $("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").show();
            }


            if(item[i].hasChildren == true) {

              
                $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").hide();
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").removeAttr("title");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip('disable');
                
            }
            if( ( Number(item[i].tally) > 0 || Number(item[i].hoursTime) > 0 ) && item[i].hasChildren != true) { 
                $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").show();
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("border" , "2px solid rgb(92 92 92)");
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("padding" , "1.5px");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('title', 'This Task has Time/ Tally');
                $("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").addClass("k-state-disabled");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip();
                
                
            } else if(item[i].hasChildren != true && ( Number(item[i].tally) == 0 ||  Number(item[i].hoursTime) == 0)  ) {
                $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").show();
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("border" , "0px solid rgb(223 0 0)");
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("padding" , "1.5px");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").removeAttr("title");
                
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('data-original-title', '');
                $("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").removeClass("k-state-disabled");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip('disable');
                
            } else if( Number(item[i].tally) == 0 ||  Number(item[i].hoursTime) == 0 ) { 
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip('disable');
            }

            if(item[i].internalTaskStatus != null) {
              
                if(blockAdd == true && parseInt(item[i].internalTaskStatus) !=5 ) {
                    $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").prop("disabled",true);
                    $("[data-uid=" +item[i].uid + "]").find(".formClass").attr('readonly', 'readonly');
         
                } 
            }
            
        }
 
    }
    allRowsInspection();

  
    $( "#gantt" ).on( "click", ".k-i-collapse", function() {  
     
        allRowsInspection();
        //dataSource.fetch(); 
        var item = $("#gantt").data('kendoGantt').dataSource.view();
        
       
        for (i = 0; i < item.length; i++) {
        

            if(Number(item[i].tally) > 0 && item[i].hasChildren != true) { 
                    //$("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("border" , "2px solid rgb(92 92 92)"); // check this
                    //$("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('title', 'This Task has Time/ Tally');
                    //$("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('data-original-title', 'This Task has Time/ Tally Collapse');
                    //$("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").addClass("k-state-disabled");
                    //$("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip(); 
            }

            
        }


    });


  
    $( "#gantt" ).on( "click", ".k-i-expand", function() { 
        var dataSource = $("#gantt").data('kendoGantt').dataSource;
        //console.log("k-i-expand");
        allRowsInspection();
        dataSource.fetch(); 

        var item = $("#gantt").data('kendoGantt').dataSource.view();

       
        for (i = 0; i < item.length; i++) {

       
            if(Number(item[i].tally) > 0 && item[i].hasChildren != true) { 
                //$("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('title', 'This Task has Time/ Tally2');
                //$("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('data-original-title', 'This Task has Time/ Tally Expand');

                //$("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").addClass("k-state-disabled");
                //$("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip();
            }
            // George - if we have an Element with no Children
            if( item[i].parentId == null && item[i].hasChildren != true ) {
                $("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").show();

            }

         
        }
    });


    // blocked non internals.
    $( "#gantt" ).on( "click", ".k-i-collapse", function() {  
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
       
            if(tas[i].internalTaskStatus != null) {
              
                if(blockAdd == true && parseInt(tas[i].internalTaskStatus) !=5 ) {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", true);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", true);
                    //$("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'none');

                } else {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", false);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", false);
                    //$("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'all');
     
                }
            }
        }
    });

    $( "#gantt" ).on( "click", ".k-i-expand", function() {  
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
       
            if(tas[i].internalTaskStatus != null) {
              
                if(blockAdd == true && parseInt(tas[i].internalTaskStatus) !=5 ) {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", true);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", true);
                    //$("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'none');

                } else {
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("disabled", false);
                    $("[data-uid=" +tas[i].uid + "]").find('span,select,input').attr("readonly", false);
                    //$("[data-uid=" +tas[i].uid + "]").closest('tr').css('pointer-events', 'all');
     
                }
            }
        }
    });

    // END blocked non internals.
    
    var gantTreeWoAjax = function() {
        $.ajax({            
            url: '/ajax/get_wo_tasks.php',
            data: {
                workOrderId: <?php echo intval($workOrderId); ?>,
                jobId: <?php echo  intval($job->getJobId()) ; ?>,
                ids : elementsList11,
                telerikjob : true
            },
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
                // create Gantt Tree
                allTasks = data[0]; 
 
                    
            }
        }).done(function() {
            var dataSource = new kendo.data.GanttDataSource({ data: allTasks });
            var grid = $('#gantt').data("kendoGantt");
           
            dataSource.read();
            grid.setDataSource(dataSource);

            allRowsInspection();
         

        });
    }

    var ajaxResult = null;
    var sourceTreeWoAjax = function(workOrderTaskId) {
      
        $.ajax({            
            url: '/ajax/get_tasks_tree.php',
            data: {
                workOrderId: <?php echo intval($workOrderId); ?>,
                workOrderTaskId: workOrderTaskId,
             
            },
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
               
                ajaxResult = data;
               
            }
        });
    }

    // check if Level 1 has total cost.
    // popup warning that the qty, cost and total cost will be reset to 0.
    // show value.
    var ajaxTotCost = 0;
    var totCostWotAjax = function(workOrderTaskId) {
      
        $.ajax({            
            url: '/ajax/check_wot_level_one_cost.php',
            data: {
                workOrderTaskId: workOrderTaskId,
                checkTotCost : true
            },
            async:false,
            type:'post',    
            success: function (data, textStatus, jqXHR) {
                                    
                if (data['status']) {
                        if (data['status'] == 'success') {
                            //succes, get the value of the total cost.
                            ajaxTotCost = data['totCostVal'];
                        } else {
                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                        }
                } else {
                    alert('Server-side error in call to ajax/check_wot_level_one_cost.php. No status returned.');
                }
            },

            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/check_wot_level_one_cost.php');
            }
        });
    }

    // Get current Ids of all elements.
    elementIdArr = [];
    var getElementsIds = function(e) {
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].parentId == null) {
                elementIdArr.push(tas[i].id);
            }
      
        }

    }


    // check if the task has "internalTaskStatus" equal 5
    // Check contract status "blockAdd". If true, only internal tasks
    

    var ajaxInternal = 0;
    var ajaxInternal = function(workOrderTaskId) {
      
        $.ajax({            
            url: '/ajax/check_wot_internal_status.php',
            data: {
                workOrderTaskId: workOrderTaskId,
                checkInternal : true
            },
            async:false,
            type:'post',    
            success: function (data, textStatus, jqXHR) {
                                    
                if (data['status']) {
                        if (data['status'] == 'success') {
                            //succes, get the value of internalTaskStatus
                            ajaxInternal = data['internalTaskStatus'];
                        } else {
                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                        }
                } else {
                    alert('Server-side error in call to ajax/check_wot_internal_status.php. No status returned.');
                }
            },

            error: function (xhr, status, error) {
                alert('Server-side error in ajax error in call to ajax/check_wot_internal_status.php');
            }
        });
    }



    // Add/ Edit extra description.
    $( "#gantt tbody" ).on( "change", ".k-grid-edit-row", function() { 
        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));
        var extraDescription = item.extraDescription;
        var nodeTaskId = item.workOrderTaskId;

        
        $.ajax({
            url: '/ajax/add_extra_description_task.php',
            data: {
                nodeTaskId : nodeTaskId,
                extraDescription : extraDescription
            },
            async:false,
            type:'post', 
            success: function (data, textStatus, jqXHR) {
                //success
            },
            error: function (xhr, status, error) {
            //error
            }
        })
        

    });


    // Delete Task on Gantt table.
    $( "#gantt tbody" ).on( "click", ".k-grid-delete", function() {
        var workOrderTaskId = "";
        var levelTwoTask = false;

        var item = $("#gantt").data("kendoGantt").dataItem($(this).closest("tr"));

        if (item.workOrderTaskId) {
            workOrderTaskId = item.workOrderTaskId; // George - get the workOrderTaskId we want to delete.
        }

        // logic to task Contract Status and if Task is Level 1.
        getElementsIds();
        if($.inArray(item.parentId , elementIdArr) != -1) {
            levelTwoTask = false;  // Level 1 Tasks.
        } else {
            levelTwoTask = true;  // Level 2 Tasks.
        }
        
        if (workOrderTaskId) {
            event.preventDefault(); 

            // update tot cost for Level 1 parent.
            if(levelTwoTask) {
                $.ajax({            
                    url: '/ajax/update_totalcost_wot_on_delete.php',
                    data: {
                        workOrderTaskId: workOrderTaskId,
                        workOrderId:<?php echo intval($workOrderId); ?>,
                        delete: true
                    },
                    async: false,
                    type: 'post',    
                    success: function (data, textStatus, jqXHR) {
                        // on succes no specific return.
                        if (data['status']) {
                                if (data['status'] != 'success') {
                                    alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                                }
                        } else {
                            alert('Server-side error in call to ajax/update_totalcost_wot_on_delete.php. No status returned.');
                        }
                    },
        
                    error: function (xhr, status, error) {
                        alert('Server-side error in ajax error in call to ajax/update_totalcost_wot_on_delete.php');
                    }   
                });
            }
  
            $.ajax({            
                url: '/ajax/deleteworkordertask.php',
                data: {
                    workOrderTaskId: workOrderTaskId,
                    workOrderId:<?php echo intval($workOrderId); ?>
                },
                async: false,
                type: 'post',       
                success: function (data, textStatus, jqXHR) {
                    // on succes no specific return.
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            gantTreeWoAjax();
                            expandGanttTree();
                            allRowsInspection();
                        } else { 
                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                        }
                    } else {
                        alert('Server-side error in call to ajax/deleteworkordertask.php. No status returned.');
                    }
                },
    
                error: function (xhr, status, error) {
                    alert('Server-side error in ajax error in call to ajax/deleteworkordertask.php');
                }    
            });

        } else {
           
            // George. Remove Element after selection.
            // elementsList11 contains the Ids of the elements.
            var elementToRemove = item.elementId;
            elementsList11 = jQuery.grep(elementsList11, function(value) {
                return value != elementToRemove;
            });
            allRowsInspection();
            expandGanttTree();
        }
    });



    $("#selectElements").on("click", function() { 
           $("#elementdialog").dialog("close");
          
           //return;
           $.ajax({            
               url: '/ajax/get_wo_tasks.php',
               data: {
                   workOrderId: <?php echo intval($workOrderId); ?>,
                   jobId: <?php echo  intval($job->getJobId()) ; ?>,
                   ids : elementsList11,
                   telerikjob : false
               },
               async:false,
               type:'post',    
               success: function(data, textStatus, jqXHR) {
                    //gantTreeWoAjax();
                    //expandGanttTree();
                    allRowsInspection();

               },    
               error: function(jqXHR, textStatus, errorThrown) {
               alert('error');
               } 
           });

    });

    // combined elements 
    $("#combineElements").on("click", function() { 
     
           $("#elementdialog").dialog("close");
           $.ajax({            
               url: '/ajax/get_wo_tasks.php',
               data: {
                   workOrderId: <?php echo intval($workOrderId); ?>,
                   jobId: <?php echo  intval($job->getJobId()) ; ?>,
                   ids : elementsList11,
                   telerikjob : false
               },
               async:false,
               type:'post',    
               success: function(data, textStatus, jqXHR) {
                    gantTreeWoAjax();
                    expandGanttTree();
                    allRowsInspection();
               },    
               error: function(jqXHR, textStatus, errorThrown) {
               alert('error');
               }
           });
   
    });


    $('#elementdialog').on('dialogclose', function(event) {
        gantTreeWoAjax();
    });
    

    //======================================================= End Tab 3 - AJAX Workorder Tasks 


    
    // Tab2 - AJAX Templates

    // WORKORDER Task Packages 
    var taskPackages=<?php echo json_encode($allTasksPack); ?>;

    // Packages Names for Edit. For TEMPLATES PACKAGES
    var allPackagesNames=<?php echo json_encode($allPackagesNames); ?>;
    var allPackagesNamesEdit = function() {
        $(allPackagesNames).each(function(i, value) { 
            $("#treeview-telerik .k-group .k-group .k-in:contains(" + value + ")").each(function() {
                $(this).closest("span").addClass("parentFolder");
            });
        });
    }


    var tree2= $("#treeview-telerik").kendoTreeView({
        template: kendo.template($("#treeview").html()),
        dataSource: [ {id: 1000, text: "Templates Packages", expanded: true, items: 
            taskPackages,
            
        }],
        dragAndDrop: true,
        allowDefaultContextMenu: true,
        allowAdd: true,
        allowCopy: true,
        drop: onDrop4,  // custom function

 

    });
    tree2.AllowDefaultContextMenu = true;

    // The Active Package we work on. For TEMPLATES PACKAGES
    var expandItemActive = function(nodeTextExpand = "", treeview = "") {
    
        $("#treeview-telerik").data("kendoTreeView").dataSource.read();

        treeview =  $("#treeview-telerik").data("kendoTreeView");
        var node = $("#treeview-telerik li:contains('"+nodeTextExpand+"')");
        treeview.expand(node);
      
    }

 
    var taskTreeTemplatesAjax = function() {
        allPackagesNamesEdit();

        //$("#treeview-telerik").kendoTreeView().data("kendoTreeView").expand(".k-item");
        $('#treeview-telerik').data('kendoTreeView').destroy();
        $.ajax({            
            url: '/ajax/get_template_packages.php',
           
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
                // create Tree and items array
                taskPackages = data;
                allPackagesNames = Array();
                $(taskPackages).each(function(i, value) { 
                   
                    allPackagesNames.push(value.text);
                    $("#treeview-telerik .k-group .k-group .k-in:contains(" + value.text + ")").each(function() {
                        $(this).closest("span").addClass("parentFolder");
                    });
                });
           
                $("#treeview-telerik").kendoTreeView({
                    template: kendo.template($("#treeview").html()),
                    dataSource: [ {id: 1000, text: "Templates Packages", expanded: true, items: 
                        taskPackages,
                        
                    }],
                    
                    dragAndDrop: true,
                    allowDefaultContextMenu: true,
                    allowAdd: true,
                    allowCopy: true,
                    drop: onDrop4   // custom function
      
                }).data("kendoTreeView");
               
            },    
            error: function(jqXHR, textStatus, errorThrown) {
             alert('error');
            }
        });
    }

    taskTreeTemplatesAjax();
    // END  TAB 2 - AJAX Templates

    // Sort Template Packages : name, date.
    $("#comboSelect").on("change", function() {
        var value_sort = this.value;
        allPackagesNamesEdit();

        $('#treeview-telerik').data('kendoTreeView').destroy();
        $.ajax({            
            url: '/ajax/get_template_packages.php',
            data: {
                value_sort: value_sort
            },
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
                taskPackages = data;
                allPackagesNames = Array();
                $(taskPackages).each(function(i, value) { 
                  
                    allPackagesNames.push(value.text);
                    $("#treeview-telerik .k-group .k-group .k-in:contains(" + value.text + ")").each(function() {
                        $(this).closest("span").addClass("parentFolder");
                    });
                });
           
                $("#treeview-telerik").kendoTreeView({
                    template: kendo.template($("#treeview").html()),
                    dataSource: [ {id: 1000, text: "Templates Packages", expanded: true, items: 
                        taskPackages,
                        
                    }],
                    
                    dragAndDrop: true,
                    allowDefaultContextMenu: true,
                    allowAdd: true,
                    allowCopy: true,
                    drop: onDrop4   // custom function
      
                }).data("kendoTreeView");
               
            },    
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
        allPackagesNamesEdit();
    });



    
    // Search/ Filter by packagen Name - tab2.
    $("#inputSearchJobWo").on("input", function() {
        var query = this.value.toLowerCase();
        var dataSourceTasks = $("#treeview-telerik").data("kendoTreeView").dataSource;

        filter(dataSourceTasks, query);
    });
    // Sets the "hidden" field on items that match the query.
    function filter(dataSource, query) {
        var hasVisibleChildren = false;
        var data = dataSource instanceof kendo.data.HierarchicalDataSource && dataSource.data();

        for (var i = 0; i < data.length; i++) {
            var item = data[i];
            var text = item.text.toLowerCase();
            var itemVisible =
                query === true // parent already matches
                || query === "" // query is empty
                || text.indexOf(query) >= 0; // item text matches query

            var anyVisibleChildren = filter(item.children, itemVisible || query); // pass true if parent matches

            hasVisibleChildren = hasVisibleChildren || anyVisibleChildren || itemVisible;

            item.hidden = !itemVisible && !anyVisibleChildren;
        }

        if (data) {
            // Re-apply the filter on the children.
            dataSource.filter({ field: "hidden", operator: "neq", value: true });
        }

        return hasVisibleChildren;
    }
    

// ==================================================================================
   

        function onDrop4(e) {
            e.preventDefault();
            var tree2 = "";
            var parentFolderId = "";
            var targetTree = "";
            var taskIdSource = "";
            var sourceItem = "";
            var destinationNode  = "";
            var targetTreeDivId  = "";
            var destinationItemPackageId = "";
            var destinationItem ="";
            var destinationItemID = "";
            var sourceItemTextFolder = "";
            var node = "";
            var targetsRoot = "";
            
            sourceItem = this.dataItem(e.sourceNode).toJSON();
           
            // Check if we have source Node and is the Root. And deny the action.
            if(sourceItem.id != "" && sourceItem.id == 1000) {
                return false;
            }
          
            if(sourceItem) {
                taskIdSource = sourceItem.taskId; // taskId from first tab (all tasks)
            }

            var treegantt = $("#gantt").data("kendoGantt");
           

            var destinationTarget = treegantt.dataItem($(e.dropTarget));
            if(!destinationTarget) { // Check if we have destination Target.
                return false;
            } 
      
            var destinationItem = treegantt.dataItem($(e.dropTarget).closest("tr"));
            

            var destinationItemID = destinationItem.elementId;   // Parent Id

            if (destinationItem.parentId) {
                alert("Drag and Drop only on Element"); // Deny Drop on Root!
                return false;
            }
           
            var destinationItemTaskId = destinationItem.workOrderTaskId;   // Parent Id ( not allowed )
            if(!destinationItemTaskId) {
                parentTaskId = destinationItemID
            } else if (destinationItemTaskId) {

                alert("Drag and Drop only on Element"); // Deny Drop on tasks!!
                return false;
            }

            if (sourceItem.taskId) {
                alert("You need to take the entire Template Package, not invidual structures"); // Deny Drop individual strucures!
                return false;
            }


            if(destinationTarget) { // Drop on Target 
                            
                
                $.ajax({            
                    url: '/ajax/add_template_pack_to_wo.php',
                    data: {
                        packageTasks:  sourceItem,
                        parentTaskId:  parentTaskId,
                        workOrderId: <?php echo intval($workOrderId); ?>,
                        elementId: destinationItemID
                    },
                    async:false,
                    type:'post',    
                    success: function(data, textStatus, jqXHR) {
                            
                        gantTreeWoAjax();
                        expandGanttTree();
                        allRowsInspection();
                    },    
                    error: function(jqXHR, textStatus, errorThrown) {
                    //		alert('error');
                    }
                });

            
                
            }

        }
    


        
        // Custom function on Drop2 if target exists will make a Copy.
        // FOR TAB 2  - TEMPLATES TASKS -
        function onDrop2(e) {
          
            return; // Blocked for this Release 4.03.2022
            console.log("Chemata");
            // This needs review. It stop after first execution.
            onDrop2 = function(){ };
            

            e.preventDefault();
            var newArray = [];
            
            var parentFolderId = "";
            var targetTree = "";
            var taskIdSource = "";
            var sourceItem = "";
            var destinationNode  = "";
            var targetTreeDivId  = "";
            var destinationItemPackageId = "";
            var destinationItemTaskId = "";
            var destinationItem ="";
            var destinationItemID = "";
            var sourceItemTextFolder = "";
            var node = "";
            var targetsRoot = "";
            var nodeTextExpand = "";
    

         

            var treegantt = $("#gantt").data("kendoGantt");
            //console.log( e.source);

            sourceItem = e.source.toJSON();
            if(sourceItem) {
                woIdSource = sourceItem.id; // taskId from first tab (all tasks)
            }


          
            sourceTreeWoAjax(woIdSource);
    
            $.each(ajaxResult, function (i, v) {
                sourceItem['items'] = v;
            });
          

            //sourceItem = JSON.stringify(sourceItem);
            //console.log(sourceItem);

            //return;
            var tree = $("#treeview-telerik").data("kendoTreeView");
            destinationNode = $(e.dropTarget);
            //if(destinationNode) {
                destinationItem = tree.dataItem(destinationNode);
             
            //}
    
            /*if(destinationNode) {
                destinationItem = tree.dataItem(e.destinationNode);
                if(!destinationItem) { // Check if we have destination Node.
                    return false;
                } 
            }*/


           
          // var destinationTarget = treegantt.dataItem($(e));

           //if(!destinationTarget) { // Check if we have destination Target.
           //    return false;
           //} 
            //sourceItem = this.dataItem(e.sourceNode).toJSON();
            
            // Check if we have source Node and is the Root. And deny the action.
            //if(sourceItem.id != "" && sourceItem.id == 1000) {
            //    return false;
            //}

            
            if (!sourceItem.taskId) {
                alert("Drag and Drop the inviduals structures not the entire Element"); // Deny Drag Element!!
                return false;
            }
          
            //var tree = $("#treeview-telerik").data("kendoTreeView");
            //destinationNode = $(e.destinationNode); 
            
            /*if(destinationNode) {
                destinationItem = tree.dataItem(e.destinationNode);
                if(!destinationItem) { // Check if we have destination Node.
                    return false;
                } 
            }*/
      
            if(destinationItem && destinationItem.hasOwnProperty('taskId')){
                destinationItemTaskId = destinationItem.taskId;
            }
           
              
            
            if( destinationItem && typeof destinationItem.taskPackageId != "undefined") { 
                var destinationItemPackageId = destinationItem.taskPackageId;
            }
          

           //console.log("destinationItem");
           // console.log(destinationItem);
            //return;
       
            //console.log(destinationItemTaskId);
        
            if(!destinationItemTaskId) {
                parentFolderId = 1000;
            } else {
                parentFolderId = destinationItem.taskPackageTaskId;
            }

            if(destinationItemTaskId) {
                nodeTextExpand = destinationItem.packageName;
            } else if(destinationItemPackageId){
                nodeTextExpand = destinationItem.text;
            }
            //console.log(nodeTextExpand);
           
            if(sourceItem) {
                 //sourceItemTextFolder = sourceItem.text;
                targetTree = destinationNode.closest("[data-role='treeview']").data("kendoTreeView");
                ///console.log( targetTreeDivId = destinationNode.closest("[data-role='treeview']")[0]);   
               // if( destinationNode.closest("[data-role='treeview']")[0].id){
                    //targetTreeDivId = destinationNode.closest("[data-role='treeview']")[0].id; // get the TAB id
                //}
                // George - get the target root. Preventing add before the root!!
                targetsRoot = $(e.dropTarget).parentsUntil(".k-treeview", ".k-item").length == 1;
            }
    
            targetTreeDivId = "treeview-telerik";
            if( targetTreeDivId == "treeview-telerik" && targetsRoot == true) { // Drop on ROOT
                    function myFunction() {
                    var packageName = "Package Name";
                    packageName = prompt("Please rename the new Template folder as a Package Name", "Package Name");
                        if(packageName != null && !allPackagesNames.includes(packageName)) {
                           
                            return packageName;
                            return;
                        } else if(packageName != null  && allPackagesNames.includes(packageName) ) {
                            if (!confirm('Please rename before saving. A duplicate exists! If you don\'t rename, your template will not be created!')) {
                                return null;
                            } else {
                                packageName = prompt("Please rename the new Template folder as a New Package Name", "Package Name 2");
                                if(packageName != null && !allPackagesNames.includes(packageName)) {
                                    return packageName;
                                } else {
                                    return null;
                                }
                               
                            }
                        } else {
                            return null;
                        }
                        
                    }
                  
                    event.preventDefault();
                    packageName = myFunction();
                    if(packageName == null) {
                        return;
                    } else {
                        $.ajax({
                            url: '/ajax/add_template_packages.php',
                            data:{
                                packageTasks : sourceItem,
                                packageName : packageName
                            },
                        
                            async: false,
                            type: 'post',  
                            success: function (response) {
                                //test to see if the response is successful...then
                                    
                                if (e.dropPosition == "before" && targetsRoot == false) { // George - not allowed before root
                                    targetTree.insertBefore(sourceItem, destinationNode);
                                   
                                    taskTreeTemplatesAjax();
                                    
                                 
                                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                   // $("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                   expandItemActive(packageName, tree);

                                    allPackagesNamesEdit();


                                } else if (e.dropPosition == "after") {
                                    targetTree.insertAfter(sourceItem, destinationNode);

                                    taskTreeTemplatesAjax();
                                 
                                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                    //$("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                    expandItemActive(packageName, tree);


                                    allPackagesNamesEdit();
                                
                                } else {
                                    //targetTree = destinationNode.closest("[data-role='treeview']").data("kendoTreeView");
                                    tree.append(sourceItem, destinationNode);

                                    taskTreeTemplatesAjax();
                                   
                                    $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                    //$("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                    expandItemActive(packageName, tree);


                                    allPackagesNamesEdit();
                                }
                            
                            },
                            error: function (xhr, status, error) {
                            }
                           
                        })
                    
                    }
                 
                
                } else if ( targetTreeDivId == "treeview-telerik" && targetsRoot == false) { // Drop on existing Packages, not on ROOT
                    var packIdfound = "";
                   
                    $.ajax({
                        url: '/ajax/add_template_packages.php',
                        data:{
                            packageTasks : sourceItem,
                            packIdfound : destinationItemPackageId,
                            parentFolderId : parentFolderId
                        },
                        
                        async: false,
                        type: 'post',  
                        success: function (response) {
                            //test to see if the response is successful...then
                                
                            if (e.dropPosition == "before" && targetsRoot == false) { // George - not allowed before Root
                                targetTree.insertBefore(sourceItem, destinationNode);

                                taskTreeTemplatesAjax();
                                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                //$("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                expandItemActive(nodeTextExpand, tree);

                                allPackagesNamesEdit();
                            } else if (e.dropPosition == "after") {
                                targetTree.insertAfter(sourceItem, destinationNode);

                                taskTreeTemplatesAjax();
                                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                //$("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                expandItemActive(nodeTextExpand, tree);

                                allPackagesNamesEdit();
                            
                            } else {
                                //targetTree = destinationNode.closest("[data-role='treeview']").data("kendoTreeView");
                                tree.append(sourceItem, destinationNode);

                                taskTreeTemplatesAjax();
                                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                //$("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                expandItemActive(nodeTextExpand, tree);

                                allPackagesNamesEdit();
                            }
                        
                        },
                        error: function (xhr, status, error) {
                        }
                    })
        
                } 
                
            }

        
        
        // Custom function on Drop if target exists will make a Copy.
        // For TASKS - added to Workorder Tasks  
        function onDrop(e) { 
            //console.log("onDrop");
            e.preventDefault();
         
            var treegantt = "";
            var taskIdSource = "";
            var sourceItem = "";
            var billingDesc = "";
            var tasktypeId = "";
            var taskContractStatus = "";
            var levelOneTask = false;
            var destinationItemInternalStatus = 0;
            var destinationItemID = 0;
            var destinationItemTaskId = 0;
            var parentTaskId = 0;
            
            sourceItem = this.dataItem(e.sourceNode).toJSON();
            
            var treegantt = $("#gantt").data("kendoGantt");
           

            var destinationTarget = treegantt.dataItem($(e.dropTarget));
            if(!destinationTarget) { // Check if we have destination Target.
                return false;
            } 

            var destinationItem = treegantt.dataItem($(e.dropTarget).closest("tr"));


            if(sourceItem) {
                taskIdSource = sourceItem.id; // taskId from first tab (all tasks)
            }
     

            if( blockAdd == true ) {
         
                // internal tasks
                // destination item is an ELEMENT.
                destinationItemID = destinationItem.elementId;   // Parent Id
                destinationItemTaskId = destinationItem.workOrderTaskId;   // workOrderTaskId
                destinationItemInternalStatus = destinationItem.internalTaskStatus;   // internalTaskStatus

             
                if(destinationItem.parentId == null || destinationItemInternalStatus == 5) {
                    destinationItemInternalStatus = 5;
                    if(!destinationItemTaskId) {
                        parentTaskId = destinationItemID
                    } else if (destinationItemTaskId) {
                        parentTaskId = destinationItemTaskId
                        totCostWotAjax(parentTaskId);
                    } 
                } else {
                    alert("Drag and Drop only on Element or inviduals structures that are active."); // Deny Drag on internalTaskStatus == 0!!
                    return false;
                }

            } else {

                // regular Tasks
                destinationItemID = destinationItem.elementId;   // Parent Id
    
                destinationItemTaskId = destinationItem.workOrderTaskId;   // Parent Id
                if(!destinationItemTaskId) {
                    parentTaskId = destinationItemID
                } else if (destinationItemTaskId) {
                    parentTaskId = destinationItemTaskId
                    totCostWotAjax(parentTaskId); 
                }              

            }

            // logic to task Contract Status and if Task is Level 1.
            getElementsIds();
            if($.inArray(destinationItem.parentId , elementIdArr) != -1) {
                // Level 1 Tasks. Show
                levelOneTask = true;
            } else {
                levelOneTask = false;
            }

            // billig description and type of the task.
            billingDesc = sourceItem.billingDesc;
            taskTypeId = sourceItem.taskTypeId;


           
            if(destinationTarget) {

                // If true, the Destination Task is LEVEL 1.
                if(levelOneTask) {

                    // all tasks with level one destination will have Contract Status 9.
                    taskContractStatus = 9; 

                    if(ajaxTotCost > 0 ) { // we have a TOTAL COST

                        if(destinationItem.hasChildren == false) { // has total cost and have NO children.

                            if(!confirm("Please be aware this task has "+ ajaxTotCost +" value that will be lost when you add children. DONT forget to include "+ ajaxTotCost +" into the value of the children .")) {
                                return;
                                event.preventDefault();
                            } else {
                                // do the update
                                var workOrderTaskIdAjax = 0;
                                $.ajax({            
                                    url: '/ajax/addworkordertask2.php',
                                    data: {
                                        taskId:  taskIdSource,
                                        parentTaskId:  parentTaskId,
                                        workOrderId: <?php echo intval($workOrderId); ?>,
                                        elementId: destinationItemID,
                                        internalTaskStatus : destinationItemInternalStatus
                                    },
                                    async:false,
                                    type:'post',    
                                    success: function (data, textStatus, jqXHR) {
                                        // add the new WOT. Return the  workOrderTaskId. 
                                        if (data['status']) {
                                            if (data['status'] == 'success') {
                                                workOrderTaskIdAjax = data['workOrderTaskId'];
                                    
                                                gantTreeWoAjax();
                                                expandGanttTree();
                                                allRowsInspection();
                                            } else {
                                                alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                                            }
                                        } else {
                                            alert('Server-side error in call to ajax/addworkordertask2.php. No status returned.');
                                        }
                                    },  
                                    error: function(jqXHR, textStatus, errorThrown) {
                                        alert('Server-side error in ajax error in call to ajax/addworkordertask2.php');
                                    } 
                                });

                                // after we have an workOrderTaskId
                                // add billing description, tasktype and contract status.
                                $.ajax({
                                    url: '/ajax/update_wot_level2.php',
                                    data: {
                                        nodeTaskId : workOrderTaskIdAjax,
                                        billingDescription : billingDesc,
                                        billDesc : true,
                                        taskTypeId : taskTypeId,
                                        taskContractStatus : taskContractStatus,
                                    },
                                    async:false,
                                    type:'post', 
                                    success: function (data, textStatus, jqXHR) {
                                    
                                        if (data['status']) {
                                                if (data['status'] != 'success') {
                                                    alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                                                }
                                        } else {
                                            alert('Server-side error in call to ajax/update_wot_level2.php. No status returned.');
                                        }
                                    },
            
                                    error: function (xhr, status, error) {
                                        alert('Server-side error in ajax error in call to ajax/update_wot_level2.php');
                                    }
                                });
            
                          
                                $.ajax({            
                                    url: '/ajax/check_wot_level_one_cost.php',
                                    data: {
                                        workOrderTaskId: destinationItemTaskId,
                                        updateTotCost : false
                                    },
                                    async:false,
                                    type:'post',    
                                    success: function (data, textStatus, jqXHR) {
                                    
                                        if (data['status']) {
                                                if (data['status'] != 'success') {
                                                    alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                                                }
                                        } else {
                                            alert('Server-side error in call to ajax/check_wot_level_one_cost.php. No status returned.');
                                        }
                                    },
                    
                                    error: function (xhr, status, error) {
                                        alert('Server-side error in ajax error in call to ajax/check_wot_level_one_cost.php');
                                    }
                                });
                            
                            }
                        
                        } else {  
                            // the Destination Task is LEVEL 1. Has total cost and HAVE children.
                            // do the update
                            var workOrderTaskIdAjax = 0;
                            $.ajax({            
                                url: '/ajax/addworkordertask2.php',
                                data: {
                                    taskId:  taskIdSource,
                                    parentTaskId:  parentTaskId,
                                    workOrderId: <?php echo intval($workOrderId); ?>,
                                    elementId: destinationItemID,
                                    internalTaskStatus : destinationItemInternalStatus
                                },
                                async:false,
                                type:'post',    
                                success: function (data, textStatus, jqXHR) {
                                    // add the new WOT. Return the  workOrderTaskId. 
                                    if (data['status']) {
                                        if (data['status'] == 'success') {
                                            workOrderTaskIdAjax = data['workOrderTaskId'];
                                
                                            gantTreeWoAjax();
                                            expandGanttTree();
                                            allRowsInspection();
                                        } else {
                                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                                        }
                                    } else {
                                        alert('Server-side error in call to ajax/addworkordertask2.php. No status returned.');
                                    }
                                },  
                                error: function(jqXHR, textStatus, errorThrown) {
                                    alert('Server-side error in ajax error in call to ajax/addworkordertask2.php');
                                } 
                            });

                            // add billing description, tasktype and contract status.
                            $.ajax({
                                url: '/ajax/update_wot_level2.php',
                                data: {
                                    nodeTaskId : workOrderTaskIdAjax,
                                    billingDescription : billingDesc,
                                    billDesc : true,
                                    taskTypeId : taskTypeId,
                                    taskContractStatus : taskContractStatus,
                                   
                                },
                                async:false,
                                type:'post', 
                                success: function (data, textStatus, jqXHR) {
                                    
                                    if (data['status']) {
                                        if (data['status'] != 'success') {
                                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                                        }
                                    } else {
                                        alert('Server-side error in call to ajax/update_wot_level2.php. No status returned.');
                                    }
                                },

                                error: function (xhr, status, error) {
                                    alert('Server-side error in ajax error in call to ajax/update_wot_level2.php');
                                }
                            });
            
                            
                        }
                    } else {

                        // Destination task Level 1 without totCost 
                        // update the: qty, cost to 0. // 
                        // We do not the Total Cost.
                        // task contract status is 9.

                        taskContractStatus = 9;
                        var workOrderTaskIdAjax = 0;
                            $.ajax({            
                                url: '/ajax/addworkordertask2.php',
                                data: {
                                    taskId:  taskIdSource,
                                    parentTaskId:  parentTaskId,
                                    workOrderId: <?php echo intval($workOrderId); ?>,
                                    elementId: destinationItemID,
                                    internalTaskStatus : destinationItemInternalStatus
                                },
                                async:false,
                                type:'post',    
                                success: function (data, textStatus, jqXHR) {
                                    // add the new WOT. Return the  workOrderTaskId. 
                                    if (data['status']) {
                                        if (data['status'] == 'success') {
                                            workOrderTaskIdAjax = data['workOrderTaskId'];
                                
                                            gantTreeWoAjax();
                                            expandGanttTree();
                                            allRowsInspection();
                                        } else {
                                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                                        }
                                    } else {
                                        alert('Server-side error in call to ajax/addworkordertask2.php. No status returned.');
                                    }
                                },  
                                error: function(jqXHR, textStatus, errorThrown) {
                                    alert('Server-side error in ajax error in call to ajax/addworkordertask2.php');
                                }  
                            });

                            // add billing description, tasktype and contract status.
                            $.ajax({
                                url: '/ajax/update_wot_level2.php',
                                data: {
                                    nodeTaskId : workOrderTaskIdAjax,
                                    billingDescription : billingDesc,
                                    billDesc : true,
                                    taskTypeId : taskTypeId,
                                    taskContractStatus : taskContractStatus,
                                    
                                },
                                async:false,
                                type:'post', 
                                success: function (data, textStatus, jqXHR) {
                                    
                                    if (data['status']) {
                                        if (data['status'] != 'success') {
                                            alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                                        }
                                    } else {
                                        alert('Server-side error in call to ajax/update_wot_level2.php. No status returned.');
                                    }
                                },

                                error: function (xhr, status, error) {
                                    alert('Server-side error in ajax error in call to ajax/update_wot_level2.php');
                                }
                            });

                            
                            $.ajax({            
                                url: '/ajax/check_wot_level_one_cost.php',
                                data: {
                                    workOrderTaskId: destinationItemTaskId,
                                    updateTotCost : false
                                },
                                async:false,
                                type:'post',    
                                success: function (data, textStatus, jqXHR) {
                                    
                                    if (data['status']) {
                                            if (data['status'] != 'success') {
                                                alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');  
                                            }
                                    } else {
                                        alert('Server-side error in call to ajax/check_wot_level_one_cost.php. No status returned.');
                                    }
                                },
                
                                error: function (xhr, status, error) {
                                    alert('Server-side error in ajax error in call to ajax/check_wot_level_one_cost.php');
                                }
                            });
                    }
                } else {
                    // DESTINATION TASK NOT LEVEL 1.
                    // Destination task can be level 2 or more, or drop on an Element.
                    // Task Contract status 9: for all the children of Level 1 Tasks.
                    // Task Contract status 1: for children of Element.
                    // we add the WOT, add billing description, taskType, change contract status 

                    var workOrderTaskIdAjax = 0;
                    $.ajax({            
                        url: '/ajax/addworkordertask2.php',
                        data: {
                            taskId:  taskIdSource,
                            parentTaskId:  parentTaskId,
                            workOrderId: <?php echo intval($workOrderId); ?>,
                            elementId: destinationItemID,
                            internalTaskStatus : destinationItemInternalStatus
                        },
                        async:false,
                        type:'post',  

                        success: function (data, textStatus, jqXHR) {
                            // add the new WOT. Return the  workOrderTaskId. 
                            if (data['status']) {
                                if (data['status'] == 'success') {
                                    workOrderTaskIdAjax = data['workOrderTaskId'];

                                    gantTreeWoAjax();
                                    expandGanttTree();
                                    allRowsInspection();
                                } else {
                                    alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                                }
                            } else {
                                alert('Server-side error in call to ajax/addworkordertask2.php. No status returned.');
                            }
                        },  
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('Server-side error in ajax error in call to ajax/addworkordertask2.php');
                        } 
                    });

                    // Logic for Task Contract Status:
                    // Task Contract status 9: for children of Level 1
                    // Task Contract status 1: for children of Element.
                    if($.inArray(destinationItem.parentId , elementIdArr) == -1) {
                        taskContractStatus = 9; 
                        if(destinationItem.parentId == null) {
                            taskContractStatus = 1;
                        }
                    } else {
                        taskContractStatus = 1;
                    }
                    // end logic for Contract Status.

                    // add billing description, tasktype and contract status.
                    $.ajax({
                        url: '/ajax/update_wot_level2.php',
                        data: {
                            nodeTaskId : workOrderTaskIdAjax,
                            billingDescription : billingDesc,
                            billDesc : true,
                            taskTypeId : taskTypeId,
                            taskContractStatus : taskContractStatus
                        },
                        async:false,
                        type:'post', 
                        success: function (data, textStatus, jqXHR) {
                                    
                            if (data['status']) {
                                if (data['status'] != 'success') {
                                    alert('Server-side error: ' + data['error'] + '. Error Id:  ' + data['errorId'] + '');
                                }
                            } else {
                                alert('Server-side error in call to ajax/update_wot_level2.php. No status returned.');
                            }
                        },

                        error: function (xhr, status, error) {
                            alert('Server-side error in ajax error in call to ajax/update_wot_level2.php');
                        }
                    });
                 
                }

          }
        
       }


        // Begin Edit entry Tab 2 #treeview-telerik
        var editTemplate2 = kendo.template($("#editTemplate2").html());

        allPackagesNamesEdit();

        $("#menuEditModeTab2").kendoContextMenu({
            target: "#treeview-telerik",
            filter: ".parentFolder",
            
            select: function (e) {
                var node = $("#treeview-telerik").getKendoTreeView().dataItem($(e.target).closest(".k-item"));
               
                var nodeId = node.taskPackageId; // George - get the node taskPackageId on Edit
                if (node.hasChildren) {  // George - only folders
                    // create and open Window
                    $("<div />")
                        .html(editTemplate2({ node: node }))
                        .appendTo("body")
                        .kendoWindow({
                            modal: true,
                            visible: false,
                            deactivate: function () {
                                this.destroy();
                            }
                        })
                        // bind the Save button's click handler
                        .on("click", ".k-primary", function (e) {
                            e.preventDefault();

                            var dialog = $(e.currentTarget).closest("[data-role=window]").getKendoWindow();
                            var textbox = dialog.element.find(".k-textbox");
                        
                            node.set("text", textbox.val());
                            var packageNameUpdate = node.text;
                    
                            dialog.close(

                                $.ajax({
                                    url: '/ajax/update_task_package_name.php',
                                    data: {
                                        nodeId : nodeId,
                                        packageNameUpdate : packageNameUpdate
                                    },
                                    async:false,
                                    type:'post', 
                                    success: function (data, textStatus, jqXHR) {

                                        taskTreeTemplatesAjax();
                                        $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                                        //$("#treeview-telerik").data("kendoTreeView").expand(".k-item");
                                        expandItemActive(node.text);

                                        allPackagesNamesEdit();
                                    },
                                        error: function (xhr, status, error) {
                                        //error
                                        }
                                    })
                                );
                        })
                        .getKendoWindow().center().open();
                }
            }
        });
        // End Edit Tab 2 entry

        var editTemplate = kendo.template($("#editTemplate").html());

        // Edit extra description for Tasks. OLD CODE
        $("#menuEditModeExtraDescription").kendoContextMenu({
            target: "#treeview-telerik-wo",
            filter: ".k-in:not(.exceptElements)",
            
            select: function (e) {
                var node = $("#treeview-telerik-wo").getKendoTreeView().dataItem($(e.target).closest(".k-item"));
                
                var nodeTaskId = node.workOrderTaskId; // George - get the node workOrderTaskId on Edit

                // create and open Window
                $("<div />")
                    .html(editTemplate({ node: node }))
                    .appendTo("body")
                    .kendoWindow({
                        modal: true,
                        visible: false,
                        deactivate: function () {
                            this.destroy();
                        }
                    })
                    // bind the Save button's click handler
                    .on("click", ".k-primary", function (e) {
                        e.preventDefault();

                        var dialog = $(e.currentTarget).closest("[data-role=window]").getKendoWindow();
                        var textbox = dialog.element.find(".k-textbox");
                        
                        node.set("text", textbox.val());
                        
                        var extraDescription = node.text;
                        
                        dialog.close(

                            $.ajax({
                                url: '/ajax/add_extra_description_task.php',
                                data: {
                                    nodeTaskId : nodeTaskId,
                                    extraDescription : extraDescription
                                },
                                async:false,
                                type:'post', 
                                success: function (data, textStatus, jqXHR) {

                                    taskTreeWoAjax();
                                    $("#treeview-telerik-wo").data("kendoTreeView").dataSource.read();
                                    $("#treeview-telerik-wo").data("kendoTreeView").expand(".k-item");
                                    //exceptElementsDescription();
                                
                                },
                                    error: function (xhr, status, error) {
                                    //error
                                    }
                                })
                            );
                    })
                    .getKendoWindow().center().open();
            }
        });
        // End edit extra description for Tasks. OLD CODE
 

   
    // Delete entry on Tab 2 | Templates. Confirmation Message.
    $(document).on("click", "#treeview-telerik .telerik-icon", function (e) {
        e.preventDefault();
        var treeview = $("#treeview-telerik").data("kendoTreeView");

        var $this = this;
        var node = $("#treeview-telerik").getKendoTreeView().dataItem($(e.target).closest(".k-item"));
        var taskPackageId = node.taskPackageId;
        var taskPackageTaskId = node.taskPackageTaskId; // George - get the TASK ID we want to delete.

        $.ajax({            
            url: '/ajax/delete_package_tasks.php',
            data: {
                taskPackageTaskId: taskPackageTaskId,
                taskPackageId: taskPackageId
            },
            async: false,
            type: 'post',    
            success: function(data, textStatus, jqXHR) {
            
                treeview.remove($($this).closest(".k-item"));
                taskTreeTemplatesAjax();
                        
                $("#treeview-telerik").data("kendoTreeView").dataSource.read();
                expandItemActive(node.packageName);

                allPackagesNamesEdit();

            },    
            error: function(jqXHR, textStatus, errorThrown) {
                //		alert('error'); 
            }    
        });

        
    });

    // clear all the data on  Tab 3 | Workorder Templates
    $('#profile-tab2').on("click", function (e) { 
        e.preventDefault();
        $('#qSearchJob').val(''); 
        $("#divJobWo").html('');
        $("#treeview-telerik-job-wo").html('');
        
    });
    

    // Search Job Number on Tab 3 | Workorder Templates. Alert Message on empty string.
    $('#searchJobNumberWO').on("click", function (e) {

        e.preventDefault();
 
        var jobNumber = $('#qSearchJob').val(); // George - get the jobNumber from input.
        jobNumber = jobNumber.trim();

        if (jobNumber == "") {
            alert("Please enter a Job Number");
            return;
            event.preventDefault(); 
        } else {
            $("#divJobWo").html('');
            $("#treeview-telerik-job-wo").html('');
            $.ajax({    
                url: '/ajax/get_job_workorders.php',
                data: {
                    jobNumber: jobNumber,
                },
                async: false,
                type: 'post',    
                success: function(data, textStatus, jqXHR) {
              
                $("#divJobWo").html('');
                $("#treeview-telerik-job-wo").html('');
                $.each(data, function(index, work) { 
                    if( work.description ) {
                        $("#divJobWo").append('<a href="#" class="workorderClick"><span onclick="getElementsWo( '+ work.workOrderId + ')" style="">'+ work.description + '</span></a> </br>');
                    }
                });
  
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                    //		alert('error'); 
                }    
            });

        }
    });

});

    // BEGIN   Tab 3. WorkOrder Templates

  
    function getElementsWo(workOrderId) {
    $("#treeview-telerik-job-wo").kendoTreeView().data("kendoTreeView").expand(".k-item");
            $('#treeview-telerik-job-wo').data('kendoTreeView').destroy(); 
            $.ajax({            
                url: '/ajax/get_wo_tasks2.php',
                data: {
                    workOrderId: workOrderId,
                    telerikjob : true
                },
                async:false,
                type:'post',    
                success: function(data, textStatus, jqXHR) {
            
                    // create Tree and items array
                    allTasks = data;
                   
                    $("#treeview-telerik-job-wo").kendoTreeView({
                        template: kendo.template($("#treeview").html()),
                        dataSource: [ {id: 1000, text: "WorkOrder Elements Tasks", expanded: true, items: 
                            allTasks,
                        }],
                        
                        
                        allowDefaultContextMenu: true,
                        loadOnDemand: false,
                        dragAndDrop: true,
                        allowCopy: true,
                        drop: onDrop3   // custom function
        
                    }).data("kendoTreeView");
                    // Hide the delete icon for Elements WO structures.
                    $("#treeview-telerik-job-wo").find("span.k-icon.k-i-close.telerik-icon").hide();

                    $(allTasks).each(function(i, value) { 
            
                       $("#treeview-telerik-job-wo .k-group .k-group .k-in:contains(" + value.text + ")").each(function() {
                           $(this).closest("span").css("font-weight", "600");
                       });
                  
     
                   });
               
                   $("#treeview-telerik-job-wo .k-in:contains('WorkOrder Elements Tasks')").each(function() {
                           $(this).closest("span").css("font-weight", "600");
                           $(this).closest("span").css("font-size", "15px");
                       });
                   
                    $("#divJobWo").append('<span></br></span>'); // George - space after links.
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
                }
            });
    };

    var gantTreeWoAjax2 = function() {
        $.ajax({            
            url: '/ajax/get_wo_tasks.php',
            data: {
                workOrderId: <?php echo intval($workOrderId); ?>,
                jobId: <?php echo  intval($job->getJobId()) ; ?>,
                ids : elementsList11,
                telerikjob : true
            },
            async:false,
            type:'post',    
            success: function(data, textStatus, jqXHR) {
                // create Gantt Tree
                allTasks = data[0]; 
                    
            }
        }).done(function() {
            var dataSource = new kendo.data.GanttDataSource({ data: allTasks });
            var grid = $('#gantt').data("kendoGantt");
            dataSource.read();
            grid.setDataSource(dataSource);

        });
    }


    // expand Gantt Tree
    var expandGanttTree2 = function(e) { 
        var tas = $("#gantt").data("kendoGantt").dataSource.view();
        for (i = 0; i < tas.length; i++) {
            if(tas[i].hasChildren) {
                tas[i].set("expanded", true);            }
            
        }
    }
    // Hide X for parents. Add tooltip if tally.
    var allRowsInspection2 = function(e) {
        var item = $("#gantt").data('kendoGantt').dataSource.view();

       
        for (i = 0; i < item.length; i++) {
            if(item[i].hasChildren == true) {
              
                $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").hide();
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").removeAttr("title");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip('disable');
                
            }
            if( ( Number(item[i].tally ) > 0 ||  Number(item[i].hoursTime) > 0 ) && item[i].hasChildren != true  ) { 
                $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").show();
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("border" , "2px solid rgb(92 92 92)");
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("padding" , "1.5px");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('title', 'This Task has Time/ Tally');
                $("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").addClass("k-state-disabled");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip({

                    items:'other-title', content: 'test',
                    position: {
                        my: "center bottom",
                        at: "center top"
                    }
                });
                
                
            } else if( item[i].hasChildren != true && ( Number(item[i].tally) == 0 || Number(item[i].hoursTime) == 0 ) ) {
                $("[data-uid=" +item[i].uid + "]").find(".k-grid-delete").show();
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("border" , "0px solid rgb(223 0 0)");
                $("[data-uid=" +item[i].uid + "] ").find(".k-i-close").css("padding" , "1.5px");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").removeAttr("title");
                
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").prop('data-original-title', '');
                $("[data-uid=" +item[i].uid + "] ").find(".k-grid-delete").removeClass("k-state-disabled");
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip('disable');
                
            } else if( Number(item[i].tally) == 0 || Number(item[i].hoursTime) == 0 ) { 
                $("[data-uid=" +item[i].uid + "]").find(".k-command-cell").tooltip('disable');
            }
            
        }
 
    }

    function onDrop3(e) {
        e.preventDefault();
        var tree2 = "";
        var parentFolderId = "";
        var targetTree = "";
        var taskIdSource = "";
        var sourceItem = "";
        var destinationNode  = "";
        var targetTreeDivId  = "";
        var destinationItemPackageId = "";
        var destinationItem ="";
        var destinationItemID = "";
        var sourceItemTextFolder = "";
        var node = "";
        var targetsRoot = "";

        sourceItem = this.dataItem(e.sourceNode).toJSON();
        
        // Check if we have source Node and is the Root. And deny the action.
        if(sourceItem.id != "" && sourceItem.id == 1000) {
            return false;
        }
        
        if(sourceItem) {
            taskIdSource = sourceItem.taskId; 
        }

                
        var treegantt = $("#gantt").data("kendoGantt");
        

        var destinationTarget = treegantt.dataItem($(e.dropTarget));
        if(!destinationTarget) { // Check if we have destination Target.
            return false;
        } 
    
        var destinationItem = treegantt.dataItem($(e.dropTarget).closest("tr"));
      

        var destinationItemID = destinationItem.elementId;   // Parent Id

        if (destinationItem.parentId) {
            alert("Drag and Drop only on Element"); // Deny Drop on Root!
            return false;
        }
        
        var destinationItemTaskId = destinationItem.workOrderTaskId;   // Parent Id ( not allowed )
        if(!destinationItemTaskId) {
            parentTaskId = destinationItemID
        } else if (destinationItemTaskId) {
            alert("Drag and Drop only on Element"); // Deny Drop on tasks!!
            return false;
        }


        if (taskIdSource) {
            alert("You can drag only full element structure"); // Allow only full strucures!
            return false;
        }

        if( destinationTarget ) { //  Drop on Target 
                        
            $.ajax({            
                url: '/ajax/add_existing_structure_to_workorder.php',
                data: {
                    packageTasks:  sourceItem,
                    parentTaskId:  parentTaskId,
                    workOrderId: <?php echo intval($workOrderId); ?>,
                    elementId: destinationItemID
                },
                async:false,
                type:'post',    
                success: function(data, textStatus, jqXHR) {
                        
                    gantTreeWoAjax2();
                    expandGanttTree2();
                    allRowsInspection2();
                    
                },    
                error: function(jqXHR, textStatus, errorThrown) {
                //		alert('error');
                }
            });
            
        }

    }
    // END   Tab 3. WorkOrder Templates.


    // Add Combined Element. Check if duplicate exists.
    $('#combineElements').click(function() {

        var arrayOfElements = [];
        $("input:checkbox[name='elementId2[]']:checked").each(function() {
            arrayOfElements.push($(this).val());
        });
        
        $.ajax({
            url: '/ajax/add_combined_elements.php',
            data: {
                arrayOfElements: arrayOfElements,
                workorderId: <?php echo $workOrder->getWorkOrderId(); ?>,
                jobId: <?php echo  $job->getJobId() ; ?>
            },
            async:false,
            type:'post',
            context: this,
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {
                    if (data['status'] == 'success') {
                        $('#elementdialog2').dialog("close");
                    } else {
                        alert(data['error']);
                    }
                } else {
                    alert('error: no status');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
       
          
    });


// George 2021-05-25. We used this because bootstrap overrides jquery
//  and the close icon X from dialog doesn't show properly. Also in Dialog we add the property: closeText: ''
$.fn.bootstrapBtn = $.fn.button.noConflict();

</script>

<style>
    @media screen and (max-width: 680px) {
        .treeview-flex {
            flex: auto !important;
            width: 100%;
        }
    }
    .workorderClick 
    {
        text-transform:capitalize;
        padding:25px;
        font-size:15px;
        line-height:2.5;
        font-weight: 600;
    }

    #treeview .k-sprite {
        /*background-image: url("https://demos.telerik.com/kendo-ui/content/web/treeview/coloricons-sprite.png"); */
    }

    .folder { background-position: 0 -16px; }
    .html { background-position: 0 -48px; }
    body {
        background-image: url(""); 
    }
    .fancybox-wrap .fancybox-desktop .fancybox-type-iframe .fancybox-opened {
        left: 100px!important;
    }

    /* Changes on popup Edit Mode */
    .k-button-primary, .k-button.k-primary {
        color: #fff;
        background-color: #3fb54b;
        font-size: 12px;
    }
    .k-window-titlebar { 
        padding: 8px 6px;
    }
    /* End changes on popup Edit Mode */
    .telerik-icon {
        margin-left: 5px;
    }
    #treeview-kendo > ul > li > div > span > span.k-icon.k-i-close.telerik-icon {
        display:none!important;
    }
    /*#treeview-telerik-wo > ul > li > ul > li > div >  span > span.k-icon.k-i-close.telerik-icon {
        display:none!important;
        
    }*/


    .treeInlineEdit > input
{
    font-size: 1.5em;
    min-width: 10em;
    min-height: 2em;
    border-radius: 5px 5px 5px 5px;
    -moz-border-radius: 5px 5px 5px 5px;
    border: 0px solid #ffffff;
}

</style>

<div id="expanddialog">
</div>
  <style>

  .fancybox-inner {
        width:auto!important;
    }
    .fancybox-skin {
        width: 1200px!important;
        left:-168px!important;
    }

    #myCustomDescription {
        height:100%;
        line-height: 1.3;
    }
    #gantt .k-grid-header
    {
    padding: 0 !important;
    }

    #gantt .k-grid-content
    {
    overflow-y: visible;
    }
    .k-gantt-header  {
        display:none;
    }
    .k-gantt-footer {
        display:none;
    }
    .k-gantt-timeline{
        display:none;
    }
  
    .k-grid tbody button.k-button {
        min-width: 20px;
        border: 0px solid #fff;
        background: transparent; 
    }

    .k-grid .k-button {
        padding-left: calc(0.61428571em + 6px);
    }

    #treeview-telerik-wo {
        display:none!important;
    }

    /* Header padding */
    .k-gantt-treelist .k-grid-header tr {
        height: calc(2.8571428571em + 4px);
        vertical-align: bottom;
    }

    .k-gantt .k-treelist .k-grid-header .k-header { 
        padding-left: calc(0.8571428571em + 6px);
    }
    /* Header padding */   

   .k-command-cell>.k-button, .k-edit-cell>.k-textbox, .k-edit-cell>.k-widget, .k-grid-edit-row td>.k-textbox, .k-grid-edit-row td>.k-widget {
        vertical-align: middle;
        background-color: #fff;
    }

    .k-scheduler-timelineWeekview > tbody > tr:nth-child(1) .k-scheduler-table tr:nth-child(2) {
        display: none!important;
    }

    /* Extradescription in two rows */
    .k-grid  td {
        height: auto;
        white-space: normal;
    }
    .no-scrollbar .k-grid-header
    {
    padding: 0 !important;
    }

    .no-scrollbar .k-grid-content
    {
        overflow-y: visible;
    }


    /* Hide the Horizonatal bar scroll */
    .k-gantt .k-treelist .k-grid-content {
        overflow-y: hidden;
        overflow-x: hidden;
    }

    /* Hide the Vertical bar */
    .k-gantt .k-splitbar {
        display: none;
    }
    .k-gantt-treelist .k-i-expand,
    .k-gantt-treelist .k-i-collapse {
        cursor: pointer;
    }
    /* Horizontal Scroll*/
    #gantt .k-grid-content {
          /*overflow-y: hide!important; */
    }
    .k-i-cancel:before {
        content: "\e13a"; /* Adds a glyph using the Unicode character number */
    }

    .k-gantt .k-grid-content {
        overflow-y: visible !important;
        height: 110% !important;
    
    }
    
    .k-gantt .k-gantt-layout {
        height: 120% !important;

    }
    .k-grid-content table, .k-grid-content-locked table {
        min-width: 701.513px!important;
    }
    .formClass {
        height: 20px;
        padding: 8px;
        width:auto;
    }
    .formClassDesc {
        height:auto;
        width:auto;
    }
  </style>
<?php
include_once BASEDIR . '/includes/footer.php';
?>