<?php
/*  workorderdetail.php
    
    EXECUTIVE SUMMARY: View or edit workOrder details (in the sense of detail drawings)
    
    PRIMARY INPUT: $_REQUEST['workOrderId']
    
    Martin says that to find non-trivial content, the SQL query...
    
        select count(*), workOrderId from workOrderDetail
        group by workOrderId order by count(workOrderDetailId) desc limit 5;
        
    ...should give relevant workOrderIds.
*/

include './inc/config.php';
include './inc/access.php';

$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
if (!intval($workOrderId)) {
    // Invalid workOrderId, bail out
    header("Location: /");
}

$workOrder = new WorkOrder($workOrderId, $user);

// ------------------------------------
// BEGIN CODE JOE BELIEVES IS VESTIGIAL
// >>>00007 JM 2019-04-19 I believe we just have a bunch of vestigial code here: all of this
//  goes into calculating $array, but then on live code uses $array (or any other variable set here)!
$params = array();
$params['act'] = 'searchoptions';
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;

$url = DETAIL_API . '?' . signRequest($params, DETAILS_HASH_KEY);

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$username = DETAILS_BASICAUTH_USER;
//$password = DETAILS_BASICAUTH_PASS;


//$context = stream_context_create(array(
//		'http' => array(
//				'header'  => "Authorization: Basic " . base64_encode("$username:$password")
//		)
//));
// END COMMENTED OUT BY MARTIN BEFORE 2019

$options = @file_get_contents($url, false); // >>>00002 '@' suppresses errors & warnings, but we should check status afterward & report any errors & warnings
$array = json_decode($options, 1);  // >>>00015: what do we really have here? As of 2019-04, details API is undocumented, so this call & return are little understood 
                                    // >>>00012 surely we can come up with a more mnemonic name than "array"

// END CODE JOE BELIEVES IS VESTIGIAL
// ----------------------------------

include BASEDIR . '/includes/header.php';
// Add title
echo "<script>\ndocument.title ='" .
    str_replace(Array("'", "&nbsp;"), Array("\'", ' '), "Details for ". ($workOrder->getJob()->getNumber() . " - ". $workOrder->getDescription())). 
    "';\n</script>\n"; 

$crumbs = new Crumbs(null, $user);

?>
<script type="text/javascript">

// Apparently, get all Details for this workOrder.
// Alert on AJAX failure; >>>00002 oddly, does not now alert on return of status other than success
// On success, fill in 'scrolling-wrapper' div near the bottom of the page
// Four details to a row; 2-row subtable for each. In that subtable:
//     First row: 
//       * column 1: "fullname" of detail
//       * column 2: blank
//     Second row: 
//       * column 1: detail PNG, linked to detail PDF
//       * column 2: detail 'code', linked to open relevant detail management page in a new window/tab
//                   HTML BR element (newline)
//                   Either '(approved)' or '(NOT APPROVED)'

var getWorkOrderDetails = function() {
    var resultcell = document.getElementById('scrolling-wrapper');
    resultcell.innerHTML = '';

    $.ajax({
        url: '/ajax/getworkorderdetails.php',
        data:{
            workOrderId:<?php echo intval($workOrder->getWorkOrderId());  ?> 
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {                
                var html = '';                
                var resultcell = document.getElementById('scrolling-wrapper');
                var details = data['details'];

                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                //	html+= '<div class="detBox">';                
                //	html += '<table border="1" cellpadding="0" cellspacing="0" width="25%">';
                // END COMMENTED OUT BY MARTIN BEFORE 2019
                
                var c=0;
                for (var i = 0; i < details.length; i++) {
                    // Add a BR element after every 4 details
                    if (c == 4){
                        html += '<br />';
                        c = 0;
                    }

                    c++;                    
                    html+= '<div class="detBox">';                    
                        html += '<table border="0" cellpadding="0" cellspacing="0" width="25%">';
                        
                            // First row: 
                            //   * column 1: "fullname" of detail
                            //   * column 2: blank
                            html += '<tr>';
                                html += '<td>' + details[i]['fullname']                                
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                //html + = '&nbsp;[<a href="javascript:deleteWorkOrderDetail(' + escape(details[i]['detailRevisionId']) + ')">del</a>]';
                                // END COMMENTED OUT BY MARTIN BEFORE 2019            
                                html += '</td>';
                                html += '<td style="text-align:right;">';                                
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                //html += '<a data-fancybox-type="iframe" class="buttonData fancyboxIframe" href="/fb/detaildata.php?detailRevisionId=' +escape(details[i]['detailRevisionId']) + '">Info</a>';
                                // END COMMENTED OUT BY MARTIN BEFORE 2019
                                html += '</td>';
                            html += '</tr>';
                            
                            // Second row: 
                            //   * column 1: detail PNG, linked to detail PDF
                            //   * column 2: detail 'code', linked to open relevant detail management page in a new window/tab
                            //               HTML BR element (newline)
                            //               Either '(approved)' or '(NOT APPROVED)'
                            html += '<tr>';
                                html += '<td><a id="detailsPdfUrl' + details[i]['pdfurl'] + '"  href="' + details[i]['pdfurl'] + '"><img src="' + details[i]['pngurl'] + '"></a></td>';
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                //html += '<td>' + details[i]['detailRevisionId'] + '&nbsp;[<a href="javascript:deleteWorkOrderDetail(' + escape(details[i]['detailRevisionId']) + ')">del</a>]</td>';
                                // END COMMENTED OUT BY MARTIN BEFORE 2019
                                html += '<td>';
                                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                    //html += '<a href="' + details[i]['pdfurl'] + '">' + details[i]['code'] + '</a>';
                                    // END COMMENTED OUT BY MARTIN BEFORE 2019
    
                                    /*
                                    OLD CODE removed 2019-02-15 JM 
                                    html += '<a target="_blank" href="http://detail.ssseng.com/manage/index.php?detailRevisionId=' + details[i]['detailRevisionId'] + '">' + details[i]['code'] + '</a>';
                                    */
                                    // BEGIN NEW CODE 2019-02-15 JM
                                    html += '<a id="detailRev' + details[i]['detailRevisionId'] + '">' + details[i]['code'] + '" target="_blank" href="<?php echo DETAIL_ROOT; ?>/manage/index.php?detailRevisionId=' + details[i]['detailRevisionId'] + '">' + details[i]['code'] + '</a>';
                                    // END NEW CODE 2019-02-15 JM
                                    
                                    html += '<br />';
                                    if (details[i]['approved'] == 1){
                                        html += '(approved)';
                                    } else {
                                        html += '(NOT APPROVED)';
                                    }
                                html += '</td>';
                            html += '</tr>';
                        html += '</table>';
                    html += '</div>';
                }

                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                // html += '</table>';
                // html += '</div>';
                // END COMMENTED OUT BY MARTIN BEFORE 2019
                    
                resultcell.innerHTML = html;                
            }
            
            // Martin comment; reload details here
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
} // END function getWorkOrderDetails

</script>

<style>
  .buttonData {
    background-color:#4467c7;
    -moz-border-radius:28px;
    -webkit-border-radius:28px;
    border-radius:28px;
    border:1px solid #1829ab;
    display:inline-block;
    cursor:pointer;
    color:#ffffff;
    font-family:Arial;
    font-size:12px;
    padding:4px 10px;
    text-decoration:none;
    text-shadow:0px 1px 0px #2f2766;
}
.buttonData:hover {
    background-color:#5c2abf;
}
.buttonData:active {
    position:relative;
    top:1px;
}
.buttonData:link {
    color:#ffffff;
}
.buttonData:visited {
    color:#ffffff;
}

#scrolling-wrapper {
    width:95%;
    
    background:#cccccc;
    padding:10px;
    height:1000px; 
    
    text-align: center;
    
    
    overflow-y: scroll;
    white-space: nowrap;
}
  
.highlightBox {
    background-color: #66ccff;
}
  
.detBox {
    background-color: #eeeeee;
    display: inline-table;
    vertical-align: top;
    margin: 2px 2px;
} 

    #firstLinkToCopy{
        font-size: 18px;
        font-weight: 700;
    }

    #copyLink {
        color: #000;
        font-family: Roboto,"Helvetica Neue",sans-serif;
        font-size: 11px;
        font-weight: 600;
    }
</style>
<script>
    // Copy link on clipboard.

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

    $(document).ready(function() {
        // Change text Button after Copy.
        $('#copyLink').on("click", function (e) {
            $(this).text('Copied');
        });
        $("#copyLink").tooltip({
            content: function () {
                return "Copy Link";
            },
            position: {
                my: "center bottom",
                at: "center top"
            }
        });
    });
</script>
<div id="container" class="clearfix">
<?php
    $job = new Job($workOrder->getJobId());
    $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/workorderdetail.php?workOrderId=' . rawurlencode($workOrderId);
?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)
            [WO]&nbsp;<a id="linkWoToCopy" href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            [D]<a href="<?= $urlToCopy?>">&nbsp;(Details)</a>
            <button id="copyLink" title="Copy Details link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p>    
        <span id="linkToCopy" style="display:none"> 
            [J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>)&nbsp;
            [WO]&nbsp;<a href="<?= $workOrder->buildLink()?>"> <?php echo $workOrder->getDescription();?> </a>
            [D]<a href="<?= $urlToCopy?>">&nbsp;(Details)</a>
        </span>

        <span id="linkToCopy2" style="display:none"> <a href="<?= $urlToCopy?>">[J]&nbsp;<?php echo $job->getName(); ?>&nbsp;(<?php echo $job->getNumber();?>)
            [WO]&nbsp; <?php echo $workOrder->getDescription();?> </a></span>
    </div>
    
    <div class="main-content">        
        <?php /* Information icon to get help for this page */ ?>
        <img class="helpopen" id="workOrder" src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_info.gif" width="20" height="20" border="0">
    
        <h1>Details : <?php echo $workOrder->getDescription();?></h1>
        
        <h2><a id="backToWo<?php echo $workOrder->getWorkOrderId() ?>" href="<?php echo $workOrder->buildLink(); ?>">Back To Workorder</a></h2>
    
        <?php /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019 ?>
        <a class="button print"  href="/workorderpdf.php?workOrderId=<?php  echo $workOrder->getWorkOrderId(); ?>">Print</a>
        <a class="button print"  href="/workorderdetail.php?workOrderId=<?php  echo $workOrder->getWorkOrderId(); ?>">Details</a>
        [<a href="javascript:addTaskDetails();">bulk task deetz</a>]&nbsp;[<a href="javascript:addDescriptorDetails();">bulk desc deetz</a>]
        <?php  END COMMENTED OUT BY MARTIN BEFORE 2019 */ ?>	

        <p> <?php /* >>>00031 dubious use of HTML P element (paragraph, never closed); <BR/> would be better */ ?>
                
        <div class="full-box clearfix">
            <?php /* "Search" button brings up a fancybox in an iframe, invoking /fb/detailsearch.php and passing the workOrderId. */?>
                     
            <?php /* JM 2019-04-19 capitalization of getWorkORderId must be wrong; taking the liberty of fixing it.
            <a data-fancybox-type="iframe" class="button print fancyboxIframe" href="/fb/detailsearch.php?workOrderId=<?php echo $workOrder->getWorkORderId(); ?>">Search</a>
            BEGIN REPLACEMENT CODE */?>
            <a data-fancybox-type="iframe" id="detailSearch<?php echo $workOrder->getWorkOrderId(); ?>" class="button print fancyboxIframe" href="/fb/detailsearch.php?workOrderId=<?php echo $workOrder->getWorkOrderId(); ?>">Search</a>
            <?php /* END REPLACEMENT CODE */?>
            
            <h2 class="heading">Details</h2>
            
            <?php 
            /* BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
            $options = $array['data']['options'];
            $materials = $options['material'];
            $components = $options['component'];
            $functions = $options['function'];
            $forces = $options['force'];
            ?>
            <table border="4" cellpadding="0" cellspacing="0">
            <tr>
                <th>Material</th>			
                <th>Component</th>			
                <th>Function</th>			
                <th>Force</th>			
            </tr>
            <tr>
                <td>
                    <select name="material" id="material" onChange="optionSearch()"><option value=""></option>
                    <?php 
                    foreach ($materials as $mkey => $material){
                        echo '<option value="' . $material[0] . '">' . $material[1] . '</option>';						
                    }					
                    ?>
                    </select>
                </td>
                <td>
                    <select name="component" id="component" onChange="optionSearch()"><option value=""></option>
                    <?php 
                    foreach ($components as $ckey => $component){
                        echo '<option value="' . $component[0] . '">' . $component[1] . '</option>';						
                    }					
                    ?>
                    </select>
                </td>
                <td>
                    <select name="function" id="function" onChange="optionSearch()"><option value=""></option>
                    <?php 
                    foreach ($functions as $fkey => $function){
                        echo '<option value="' . $function[0] . '">' . $function[1] . '</option>';						
                    }					
                    ?>
                    </select>
                </td>
                <td>
                    <select name="force" id="force" onChange="optionSearch()"><option value=""></option>
                    <?php 
                    foreach ($forces as $fkey => $force){
                        echo '<option value="' . $force[0] . '">' . $force[1] . '</option>';						
                    }					
                    ?>
                    </select>
                </td>
            </tr>
            </table>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td>Current Details</td>
                <td>Search Results</td>
            </tr>
            <tr>
                <td width="35%" id="currentDetails" valign="top" bgcolor="#dddddd">&nbsp;</td>
                <td width="65%" id="results">&nbsp;</td>
            </tr>
            </table>
            <?php
            END COMMENTED OUT BY MARTIN BEFORE 2019 */
            ?>
            
            <?php /* The intent is that this last DIV be able to scroll horizontally without affecting the rest of the display.*/ ?>
            <div id="scrolling-wrapper" ></div><?php /* This is where the details returned by getWorkOrderDetails go. 
                                                        >>>00012: could have a much more mnemonic name. Describe what it's used for, rather than
                                                        a techincal fact about it. */ ?>
        </div>
    </div>
</div>
<script>
    getWorkOrderDetails();
</script>
<?php 
    include BASEDIR . '/includes/footer.php';
?>

<?php /* JM 2019-04-19: I moved a bunch of disused functions down here. 
         Nothing past here should matter. */ 
/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
var addDescriptorDetails = function (){

    var  formData = "workOrderId=" + escape(<?php echo $workOrder->getWorkOrderId(); ?>);  
    
    $.ajax({
        url: '/ajax/addworkorderdescriptordetails.php',
        data:{workOrderId:<?php echo $workOrder->getWorkOrderId();?>},
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {

            getWorkOrderDetails();
            
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
      });
    
}
*/


/*
var addTaskDetails = function (){

    var  formData = "workOrderId=" + escape(<?php echo $workOrder->getWorkOrderId(); ?>);  
    
    $.ajax({
        url: '/ajax/addworkordertaskdetails.php',
        data:{workOrderId:<?php echo $workOrder->getWorkOrderId();?>},
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {

            getWorkOrderDetails();
            
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
      });
    
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
var deleteWorkOrderDetail = function(detailRevisionId){    
    $.ajax({
        url: '/ajax/deleteworkorderdetail.php',
        data:{
            workOrderId:<?php echo intval($workOrder->getWorkOrderId());  ?>,  
            detailRevisionId:detailRevisionId   
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {


            getWorkOrderDetails();
            
            // reload details here

        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });

// END COMMENTED OUT BY MARTIN BEFORE 2019    
}
*/

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
var addWorkOrderDetail = function(detailRevisionId){
    //var resultcell = document.getElementById('results');
    //resultcell.innerHTML = '';
    
    $.ajax({
        url: '/ajax/addworkorderdetail.php',
        data:{
            workOrderId:<?php echo intval($workOrder->getWorkOrderId());  ?>,  
            detailRevisionId:detailRevisionId   
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            getWorkOrderDetails();            
            // reload details here
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });    
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
var optionSearch = function(){

    var material = $('#material');
    var component = $('#component');
    var func = $('#function');
    var force = $('#force');
    
    var materialValue = material.find(":selected").val();
    var componentValue = component.find(":selected").val();
    var functionValue = func.find(":selected").val();
    var forceValue = force.find(":selected").val();
    
    //alert(materialValue);
    //alert(componentValue);
    //alert(functionValue);
    //alert(forceValue);

    var resultcell = document.getElementById('results');
    resultcell.innerHTML = '';
    
    $.ajax({
        url: '/ajax/getdetailsearch.php',
        data:{
            material:materialValue,    
            component:componentValue,    
            func:functionValue,    
            force:forceValue    
        },
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success'){
                // [5] => Array
               // (
               //     [matchcount] => 1
               //     [detailRevisionId] => 387
               //     [name] => A
               //     [dateBegin] => 2017-02-21
               //     [dateEnd] => 2500-01-01
               //     [code] => KKDWX
               //     [caption] => 
               //     [classifications] => Array
               //         (
                //            [0] => Array
                //                (
                //                    [detailRevisionTypeItemId] => 3
                //                    [detailRevisionId] => 387
                //                    [detailMaterialId] => 2
                //                    [detailComponentId] => 1
                //                    [detailFunctionId] => 1
                //                    [detailForceId] => 2
                //                    [detailMaterialName] => Wood
                //                    [detailComponentName] => Framing
                //                    [detailFunctionName] => Lateral
                //                    [detailForceName] => In Plane
                //                )

                //        )

                //)


                var html = '';
                var resultcell = document.getElementById('results');
                var results = data['data']['searchresults'];
                html  += '<table border="0" cellpadding="2" cellspacing="1">';

                for (i = 0; i < results.length; i++){
                    html += '<tr>';
                        html += '<td colspan="5" bgcolor="#cccccc">' + results[i]['fullname'] + '&nbsp;[<a href="javascript:addWorkOrderDetail(' + escape(results[i]['detailRevisionId']) + ')">add</a>]</td>';
                    html += '</tr>';
            //	html += '<tr>';

            // JM: in the unlikely event that this comes back to life, need to deal with the configurables
            //		html += '<td colspan="5"><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';

            //	html += '</tr>';
                    if (results[i]['classifications']){
                        html += '<tr>';

                        html += '<td width="12%">&nbsp;</td>';
                        html += '<td width="22%"><em>Material</em></td>';
                        html += '<td width="22%"><em>Component</em></td>';
                        html += '<td width="22%"><em>Function</em></td>';
                        html += '<td width="22%"><em>Force</em></td>';

                        html += '</tr>';
                        var classifications = results[i]['classifications'];
                        for (j = 0; j < classifications.length; j++){

                            html += '<tr>';

                            if (j == 0){							    
                                        // in the unlikely event that this comes back to life, need to deal with the configurables
                                        // html += '<td rowspan="' + (classifications.length + 1) + '" ><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';

                                        html += '<td rowspan="' + (classifications.length) + '" ><a href="' + results[i]['pdfurl'] + '"><img src="' + results[i]['pngurl'] + '"></a></td>';
                            }
                            
                            //	html += '<td>&nbsp;</td>';
                                html += '<td>' + classifications[j]['detailMaterialName'] + '</td>';
                                html += '<td>' + classifications[j]['detailComponentName'] + '</td>';
                                html += '<td>' + classifications[j]['detailFunctionName'] + '</td>';
                                html += '<td>' + classifications[j]['detailForceName'] + '</td>';

                            html += '</tr>';
                        }
                    }
                    //html += '<p>' + results[i]['name'] + ' : ' + results[i]['code'] + '<p>';
                }

                html += '</table>';
                
                resultcell.innerHTML = html;
                
                

            } else {
                alert('there was a problem fetching');
            }		    
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
}
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/
