<?php
/*  fb/detailsearch.php

    EXECUTIVE SUMMARY: Search for a detail via Details API
    
    PRIMARY INPUTS: $_REQUEST['workOrderId'], $_REQUEST['taskId'].
    
    >>>00026 2019-12-10 JM: It looks like this is quite broken, needs a major revisit
    when we activate details
*/

include '../inc/config.php';
include '../inc/perms.php';

$taskId = isset($_REQUEST['taskId']) ? intval($_REQUEST['taskId']) : 0;
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;

if (intval($workOrderId)) {
    /* Uses input workOrderId to build a workOrderObject; uses that object to validate the input. Dies if invalid.*/
    $workOrder = new WorkOrder($workOrderId);
    
    // JM 2019-04-19 capitalization of getWorkORderId must be wrong; taking the liberty of fixing it.
    // if (!$workOrder->getWorkORderId()) {
    // BEGIN REPLACEMENT CODE
    if (!$workOrder->getWorkOrderId()) {          
    // END REPLACEMENT CODE
        die();
    }	
}

if (intval($taskId)) {
    /* Uses input taskId to build a workOrderObject; uses that object to validate the input. Dies if  invalid.*/
    $task = new Task($taskId);
    if (!$task->getTaskId()) {
        die();
    }
}

// Make sure at least one of these is nonzero. 
if (!$workOrderId && !$taskId) {
    die();
}

// BEGIN MARTIN COMMENT
// interfering with jquery stuff on this page.  figure out later.
//include '../includes/header_fb.php';
// END MARTIN COMMENT
// >>>00006 because you can't have multiple HEAD, BODY, etc.
?>

<html>
<head>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        html * {
            font-family: Arial,Helvetica !important;
        }        
        .buttonClear {
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
        .buttonClear:hover {
            background-color:#5c2abf;
        }
        .buttonClear:active {
            position:relative;
            top:1px;
        }
        .buttonAdd {
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
            padding:2px 8px;
            text-decoration:none;
            text-shadow:0px 1px 0px #2f2766;
        }
        .buttonAdd:hover {
            background-color:#5c2abf;
        }
        .buttonAdd:active {
            position:relative;
            top:1px;
        }
        .ui-autocomplete-loading {
            background: white url("images/ui-anim_basic_16x16.gif") right center no-repeat;
        }  
        #scrolling-wrapper {
            width:95%;
            float:left;
            position:absolute;
            background:#eeeeee;
            padding:10px;
            bottom:0px;
            height:300px; 
            
            overflow-x: scroll;
            overflow-y: hidden;
            white-space: nowrap;
        }  
        #datacell {
            height:265;
            overflow-y: auto;
        }
        .highlightBox {
            background-color: #66ccff;
        }
        .detBox {
            display: inline-block;
            vertical-align: top;
            margin: 0px 2px;
        }
        .classification {
            font-size:70%;
        }
        #container1 {
            width:95%;
            float:left;
            position:absolute;
            background:#cccccc;
            padding:10px;
            bottom:320px;
            height:375px;
        }
        tr.border_bottom td {
            border-bottom:1pt solid black;
        }  
    </style>
    <script src="//code.jquery.com/jquery-1.12.4.js"></script>
    <script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

    <script>
        // Mousewheel scrolling, used in this file for horizontal scrolling in the "scrolling-wrapper" DIV.
        // Clearly third-party code. >>>00001 JM: I didn't read this closely, but it seems to create jQuery mousewheel event handlers that 
        //  allow a mousewheel to be used either vertically or horizontally. 
        // >>>00006 JM: this should almost certainly be in a file of its own, or with other utility functions; 
        //  either the source should be noted (if known) or it deserves analysis and commentary. 
        //  There should not be a hunk of unexplained, unsourced mystery code. 
        (function(a){function d(b){var c=b||window.event,d=[].slice.call(arguments,1),e=0,f=!0,g=0,h=0;return b=a.event.fix(c),b.type="mousewheel",c.wheelDelta&&(e=c.wheelDelta/120),c.detail&&(e=-c.detail/3),h=e,c.axis!==undefined&&c.axis===c.HORIZONTAL_AXIS&&(h=0,g=-1*e),c.wheelDeltaY!==undefined&&(h=c.wheelDeltaY/120),c.wheelDeltaX!==undefined&&(g=-1*c.wheelDeltaX/120),d.unshift(b,e,g,h),(a.event.dispatch||a.event.handle).apply(this,d)}var b=["DOMMouseScroll","mousewheel"];if(a.event.fixHooks)for(var c=b.length;c;)a.event.fixHooks[b[--c]]=a.event.mouseHooks;a.event.special.mousewheel={setup:function(){if(this.addEventListener)for(var a=b.length;a;)this.addEventListener(b[--a],d,!1);else this.onmousewheel=d},teardown:function(){if(this.removeEventListener)for(var a=b.length;a;)this.removeEventListener(b[--a],d,!1);else this.onmousewheel=null}},a.fn.extend({mousewheel:function(a){return a?this.bind("mousewheel",a):this.trigger("mousewheel")},unmousewheel:function(a){return this.unbind("mousewheel",a)}})})(jQuery)
        $(document).ready(function() {
            $('#scrolling-wrapper').mousewheel(function(e, delta) {
                this.scrollLeft -= (delta * 350);
                e.preventDefault();
            });
        });
    </script>


</head>
<body>
<?php
    /* BEGIN DELETED JM 2019-12-10: immediately overwritten, never used  
    $params = array();    
    $params['act'] = 'detaildata';
    $params['detailId'] = 285;
    $params['time'] = time();
    $params['keyId'] = DETAILS_HASH_KEYID;
    $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);
    //echo $url; // Commented out by Martin before 2019
    // Martin comment: wood, framing, awning, cantilever
    END DELETED JM 2019-12-10 */    

    // Makes a RESTful call (uses GET method) to the Details API (act = 'searchoptions').
    // Results come back as JSON form of an associative array $array; we look only at 
    //  $array['data']['options'], an associative array from which we glean four arrays: 
    //  $materials, $components, $functions, $forces.
    $params = array();
    $params['act'] = 'searchoptions';
    $params['time'] = time();
    $params['keyId'] = DETAILS_HASH_KEYID;

    $url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);

    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //$username = DETAILS_BASICAUTH_USER;
    //$password = DETAILS_BASICAUTH_PASS;
    //$context = stream_context_create(array(
    //		'http' => array(
    //				'header'  => "Authorization: Basic " . base64_encode("$username:$password")
    //		)
    //));
    // END COMMENTED OUT BY MARTIN BEFORE 2019
    $options = @file_get_contents($url, false); // >>>00002: NOTE suppression of warnings, errors; presumably we should then check status & log
    $array = json_decode($options, 1);
    $options = $array['data']['options'];
    $materials = $options['material'];
    $components = $options['component'];
    $functions = $options['function'];
    $forces = $options['force'];

    if (intval($workOrderId)) {
        // We build a Job object based on the workOrderId and, at the top of the fancybox, 
        //  create a SPAN with slightly-larger-than-usual text, in which we write 
        //  a bolded 'J' followed by a link to the relevant Job page; the text for the link is the job name. 
        //  That is followed by a bolded 'WO' and a link to the relevant WorkOrder page; the text for the link is the workOrder name.
        $j = new Job($workOrder->getJobId());    
        echo '<span style="font-size:105%"><b>[J]</b>&nbsp;<a  id="linkJob'. $j->getJobId().'"   target="_blank" href="' . $j->buildLink() . '">' . $j->getName() . '</a>'.
             '&nbsp;&nbsp;&nbsp;<b>[WO]</b>&nbsp;<a  id="linkWo'. $workOrder->getWorkOrderId().'"  target="_blank" href="' . $workOrder->buildLink() . '">' . $workOrder->getName() . '</a></span>';    
    }
?>

<?php /* Outer table (level 0) */ ?>  
<table border="0" cellpadding="3" cellspacing="2" width="100%">
    <tr>
        <td valign="top">
            <?php /* first row, first column consists entirely of a subtable (level 1) */ ?>
            <table border="0" cellpadding="0" cellspacing="2">
                <tr>
                    <?php /* six columns:
                            1) A very narrow column containing just '>'
                            2) A heading "Version"
                            3) An input, implicitly text (>>>00006 JM: I'd make that explicit), id="version_ac", 
                               does an autocomplete using /ajax/getdetailversionac.php.
                            4) nonbreaking space with width=100% for the cell
                            5) "KW's" (meaning "keywords", >>>00006 JM: why not written out?) on a moderately light gray background
                            6) Another input, again implicitly text (>>>00006 JM: I'd make that explicit), id="keywords" name="keywords", no initial value
                    */ ?>
                    <td width="1">&gt;</td>
                    <th bgcolor="#cccccc">Version:</th>
                    <td><input id="version_ac" value=""></td>
                    <td width="100%">&nbsp;</td>
                    <th bgcolor="#cccccc">KW's:</th>
                    <td><input id="keywords" name="keywords" value="" size="60"></td>
                </tr>
                <tr>
                    <td colspan="6">
                        <?php /* a subtable (level 2) spanning the entire level 1 table. 
                                   * The level 2 table has six columns
                                   * Row 1 is headings.
                                   * Row 3 is the first set of material/component/function/force
                                   * Row 5 is the second set of material/component/function/force is on row 5. 
                                   * Rows 2, 4, and 6 are each spanned by one big blank column. 
                                   * (Elsewhere in the code there are effectively references to a nonexistent third row: e.g. $('#material_3'). 
                                     Those will, of course, always end up with null values.)
                                   * Column 6 spans all 6 rows (the headers plus 5 more rows). It is a link with text 'again'. 
                                     If clicked, it calls local function optionSearch.    
                        */ ?>                                 
                        <table border="0" cellpadding="1" cellspacing="2">
                            <?php /* Row 1: headings */ ?>
                            <tr bgcolor="#cccccc">
                                <td width="1">&nbsp;</td>			
                                <th>Material</th>			
                                <th>Component</th>			
                                <th>Function</th>			
                                <th>Force</th>	
                                <td rowspan="6" valign="middle">[<a id="optionSearchAgain" href="javascript:optionSearch()">again</a>]</td> <?php /* NOTE the rowspan */ ?>		
                            </tr>
                            <?php /* Row 2: blank */ ?>
                            <tr>
                                <td colspan="5" bgcolor="#000000"></td>
                            </tr>
                            <tr>
                                <?php /* (no heading): shows just '>' */ ?>
                                <td width="1">&gt;</td>
                                
                                <?php /* "Material": an HTML SELECT, with name="material_1", id same as name. 
                                         On change, calls local function optionSearch. The first OPTION in the SELECT 
                                         is blank and has an empty string as its value; each other option reflects an 
                                         element from the $materials array. That element will be an array representing 
                                         a name-value pair, with the value in position 0 and the name in position 1. 
                                         We use these to set the name & value for the option. 
                                         The blank first option is initially selected. */ ?>
                                <td>
                                    <select name="material_1" id="material_1" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($materials as $material) {
                                        echo '<option value="' . $material[0] . '">' . $material[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                                
                                <?php /* "Component": exactly analogous to "Materials" */ ?>
                                <td>
                                    <select name="component_1" id="component_1" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($components as $component) {
                                        echo '<option value="' . $component[0] . '">' . $component[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                                
                                <?php /* "Function": exactly analogous to "Materials" */ ?>
                                <td>
                                    <select name="function_1" id="function_1" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($functions as $function) {
                                        echo '<option value="' . $function[0] . '">' . $function[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                                
                                <?php /* "Force": exactly analogous to "Materials" */ ?>
                                <td>
                                    <select name="force_1" id="force_1" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($forces as $force) {
                                        echo '<option value="' . $force[0] . '">' . $force[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                            </tr>
                            <?php /* Row 4: blank */ ?>
                            <tr>
                                <td colspan="5" bgcolor="#000000"></td>
                            </tr>
                            <?php /* Row 5: just like Row 3, see that for documentation */ ?>
                            <tr>
                                <td>&gt;</td>
                                <td>
                                    <select name="material_2" id="material_2" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($materials as $material) {
                                        echo '<option value="' . $material[0] . '">' . $material[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="component_2" id="component_2" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($components as $component) {
                                        echo '<option value="' . $component[0] . '">' . $component[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="function_2" id="function_2" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($functions as $function) {
                                        echo '<option value="' . $function[0] . '">' . $function[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="force_2" id="force_2" onChange="optionSearch()"><option value=""></option>
                                    <?php 
                                    foreach ($forces as $force) {
                                        echo '<option value="' . $force[0] . '">' . $force[1] . '</option>';						
                                    }					
                                    ?>
                                    </select>
                                </td>
                            </tr>
                            <?php /* Row 6: blank */ ?>
                            <tr>
                                <td colspan="5" bgcolor="#000000"></td>
                            </tr>		
                        </table>
                    </td>
                </tr>
            </table>	
        </td>
        <?php /* Back to the second column of the level 0 table. */ ?> 
        <td valign="top">
            <?php /* Another level 1 table with rows as follows; all buttons in this table are styled by class "buttonClear" 
                     >>>00012 JM 'buttonClear' should probably be renamed, since it doesn't have anything more to do with the 
                        "clear" button than with the rest of these. */ ?>
            <table border="0" cellspacing="0" cellpadding="3">
                <?php /* Row 1: two buttons, each in a separate column:
                            * "Import From Tasks" calls local function addTaskDetails
                            * "Import From Descriptors" calls local function addDescriptorDetails */ ?> 
                <tr>
                    <td valign="top">
                        <a class="buttonClear" id="importFromTasks" href="javascript:addTaskDetails()">Import&nbsp;From&nbsp;Tasks</a> 
                    </td>
                    <td valign="top">
                        <a class="buttonClear" id="importFromDescriptors" href="javascript:addDescriptorDetails()">Import&nbsp;From&nbsp;Descriptors</a> 
                    </td>
                </tr>
        
                <?php /* Row 2: a single button in a column that spans the level 1 table:
                            * "All WO Details" calls local function getWorkOrderDetails*/ ?>
                <tr>
                    <td colspan="2" align="right">
                        <a class="buttonClear" id="allWoDetails" href="javascript:getWorkOrderDetails()">All WO Details</a> 
                    </td>
                </tr>		
        
                <?php /* Row 3: a single button in a column that spans the level 1 table:
                            * "Clear" calls local function clear, which clears out DIV "scrolling-wrapper" below. */ ?>
                <tr>
                    <td colspan="2" align="right">
                        <a class="buttonClear" id="clearAll" href="javascript:clearAll()">Clear</a> 
                    </td>
                </tr>    
            </table>
        </td>
    </tr>
</table>

<?php /* "container1" is the framework of another set of nested tables: 
       * Level 0 table: a single row        
            * level 0, column 1, just contains an initially empty DIV with ID "datacell"
            * level 0, column 2, has ID "revisioncell", and consists of a nested (level 1) table with a single row, which in turn has 
              a single row with two columns:
                * level 1, column 1: initially empty TD cell with ID "linkCell"
                * level 1, column 2: initially empty TD cell with ID "revObjectCell" 
*/ ?>
<div id="container1">
    <table border="0" cellpadding="5" cellspacing="0" width="100%" height="100%">
        <tr>
            <td width="20%" valign="top"><div id="datacell">                
                </div>
            </td>
            <td width="80%" id="revisioncell" valign="top">
                <table border="0" cellpadding="5" cellspacing="0" height="100%" width="100%">
                    <tr>
                        <?php /* ?>
                        <td valign="top">
                            <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td id="revTypeItems"></td>
                            </tr>
                            <tr>
                                <td id="revRevisions"></td>
                            </tr>
                            <tr>
                                <td id="revDiscussions"></td>
                            </tr>
                            </table>	
                        </td>
                        <?php */ ?>
                        
                        <td valign="top" id="linkCell" width="36"></td>
                        <td valign="top" id="revObjectCell" height="100%" width="100%">                            
                        </td>		
                    </tr>
                </table>	
            </td>
        </tr> <?php /* </tr> added JM 2019-12-10, obviously belongs here. */ ?>    
    </table>
</div>

<?php /* "scrolling-wrapper" originally empty, and is separated so that it can scroll horizontally without affecting the rest of the layout; 
         as noted above, it can use the mousewheel. */ ?> 
<div id="scrolling-wrapper" ></div>

<script>
<?php /* Now come a large number of local functions, some of which are referenced above, 
         others of which only make sense in the context of HTML generated once some particular function has been called. */ ?>
         
<?php /* 
    local function addDetail
    INPUT detailRevisionId 
    Calls '/ajax/addworkorderdetail.php' synchronously via POST method, passing the workOrderId from the initial input, along with detailRevisionId. 
    Does not check the return from the AJAX at all (nor even behave differently on AJAX failure, such as giving an alert); 
    it just finishes by calling local function getWorkOrderDetails() regardless. 
*/ ?>         
var addDetail = function(detailRevisionId) {
    $.ajax({
        url: '/ajax/addworkorderdetail.php',
        data: {
            workOrderId:<?php echo intval($workOrder->getWorkOrderId());  ?>,
            detailRevisionId:detailRevisionId
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            getWorkOrderDetails();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            getWorkOrderDetails();
//			alert('error'); // Commented out by Martin before 2019
        }
    });    
}

<?php /* 
    local function removeDetail
    INPUT detailRevisionId 
    Calls '/ajax/deleteworkorderdetail.php' synchronously via POST method, passing the workOrderId from the initial input, along with detailRevisionId. 
    Does not check the return from the AJAX at all (nor even behave differently on AJAX failure, such as giving an alert); 
    it just finishes by calling local function getWorkOrderDetails() regardless.
*/ ?>         
var removeDetail = function(detailRevisionId) {
    $.ajax({
        url: '/ajax/deleteworkorderdetail.php',
        data: {
            workOrderId:<?php echo intval($workOrder->getWorkOrderId());  ?>,
            detailRevisionId:detailRevisionId
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            getWorkOrderDetails();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            getWorkOrderDetails();
//			alert('error'); // Commented out by Martin before 2019
        }
    });            
}

<?php /* 
    local function getChildDetails
    INPUT parentId
    Starts by clearing the DIV with ID "scrolling-wrapper". 
    Then calls '/ajax/getdetailchildren.php' synchronously via POST method, passing the parentId. 
    On AJAX failure, it raises an alert. 
    On AJAX success, it makes sure that the returned status='success'; if not, it fails silently, 
        >>>00006 should probably raise an alert. 
    On status='success' it builds new inner HTML and inserts it into the DIV with ID "scrolling-wrapper".
        See code and comments in line to better understand that new HTML.
*/ ?>
var getChildDetails = function(parentId) {
    var resultcell = document.getElementById("scrolling-wrapper");
    resultcell.innerHTML = '';

    $.ajax({
        url: '/ajax/getdetailchildren.php',
        data: {
            parentId:parentId
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {        
                var html = '';                        
                //var resultcell = document.getElementById('currentDetails'); // Commented out by Martin before 2019
                var results = data['details'];
                
                <?php /* For each detail returned, an appropriately named DIV (HTML ID is based on detailId),
                         formatted as a table with three rows as follows:
                         * row 1
                            * a subtable in a single TD cell spanning four columns. This table has a single row with three columns:
                                * column 1: full name of detail ( results[i]['fullname'] ); if results[i] has children ( results[i]['childCount'] > 0 ) 
                                    then this is followed by 2 nonbreaking spaces and the childCount.
                                * column 2: This column is present only if there nonempty value for results[i]['pdfurl']; 
                                    as the code stands, if that is not the case, then it isn't even a nonbreaking space: the column itself is not created. 
                                    This column contains a link to results[i]['pdfurl'], labeled with the icon icon_pdf_64x64.png shrunk to 24x24.
                                * column 3: "Add" button. Labeled "add", class="buttonAdd" (which lets it be differently styled than the "buttonClear" ones), 
                                    clicking it calls locale function addDetail(detailRevisionId). 
                          * row 2: a single TD cell spanning four columns. Displays the small graphical PNG representation of the detail. 
                            That image is a link; clicking it calls local function clickScroller(detailId).
                          * row 3: present only if results[i]['classifications'] is present 
                            (>>>00006 JM probably also should check to make sure it is a nonempty array; empty array is truthy in JavaScript). 
                            If so, another subtable  with column headers and with one row per classification; 
                                * columns are Material, Component, Function, Force
                                * values are the type names from the details API, which we get from the AJAX return in 
                                  fields like results[i]['classifications'][j]['detailMaterialName']'. 
                */ ?>
                for (i = 0; i < results.length; i++) {
                    html += '<div id="detId_' + results[i]['detailId'] + '" class="detBox">';
                        html += '<table border="0" cellspacing="2" cellpadding="2">';
                            //if (results[i]['exists'] == 1){ // Commented out by Martin before 2019
                            html += '<tr bgcolor="#ddffdd">';
                            // BEGIN Commented out by Martin before 2019    
                            //} else {
                            //	html += '<tr bgcolor="#dddddd">';
                            //}
                            // END Commented out by Martin before 2019
                            
                                html += '<td colspan="4">';
        
                                // Martin comment: here 1
                                    html += '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
                                        html += '<tr>';
                                            html += '<td>' + results[i]['fullname'];        
                                                if (results[i]['childCount'] > 0) {
                                                    html += '&nbsp;&nbsp;[' + results[i]['childCount'] + ']';
                                                }
                                            html += '</td>';
        
                                            if (results[i]['pdfurl']) {
                                                html += '<td><a href="' + results[i]['pdfurl'] + '"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_pdf_64x64.png" width="24" height="24"></a></td>';
                                            }                                        
                                            //if (results[i]['exists'] != 1){ // Commented out by Martin before 2019
                                            html += '<td align="right"><a class="buttonAdd" href="javascript:addDetail(' + escape(results[i]['detailRevisionId']) + ')">add</a></td>';
                                            // BEGIN Commented out by Martin before 2019
                                            //html += '<td align="right"><a class="buttonAdd" href="javascript:removeDetail(' + escape(results[i]['detailRevisionId']) + ')">remove</a></td>';  // Commented out by Martin before 2019
                                            //}
                                            // END Commented out by Martin before 2019
                                        html += '</tr>';
                                    html += '</table>';        
                                html += '</td>';
                            html += '</tr>';                            
                            
                            html += '<tr bgcolor="#ffffff" align="center">';
                                html += '<td colspan="4">';        
                                    html += '<a href="javascript:clickScroller(' + escape(results[i]['detailId']) + ')"><img src="' + results[i]['pngurl'] + '"></a>';
                                html += '</td>';
                            html += '</tr>';
        
                            if (results[i]['classifications']) {
                                html += '<tr bgcolor="#dddddd" class="border_bottom">';
                                    html += '<td class="classification" width="22%" align="center"><em>Material</em></td>';
                                    html += '<td class="classification" width="22%" align="center"><em>Component</em></td>';
                                    html += '<td class="classification" width="22%" align="center"><em>Function</em></td>';
                                    html += '<td class="classification" width="22%" align="center"><em>Force</em></td>';
                                html += '</tr>';
        
                                var classifications = results[i]['classifications'];
                                for (j = 0; j < classifications.length; j++) {        
                                    //if (j > 0){ // Commented out by Martin before 2019
                                    html += '<tr class="border_bottom">';
                                    // BEGIN Commented out by Martin before 2019    
                                    //} else {
                                    //	html += '<tr>';
                                    //}                                   
        
                                    //html += '<tr>';
                                    // END Commented out by Martin before 2019
                                        html += '<td class="classification">' + classifications[j]['detailMaterialName'] + '</td>';
                                        html += '<td class="classification">' + classifications[j]['detailComponentName'] + '</td>';
                                        html += '<td class="classification">' + classifications[j]['detailFunctionName'] + '</td>';
                                        html += '<td class="classification">' + classifications[j]['detailForceName'] + '</td>';
                                    html += '</tr>';        
                                }
                            }
                            
                            html += '<tr>';        
                            html += '</tr>';
                        html += '</table>';
                    html += '</div>';
                }
                resultcell.innerHTML = html;                        
            }
            // Martin comment: reload details here        
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
} // END function getChildDetails

<?php /* 
    local function getWorkOrderDetails
    
    Calls local function clearAll(), then calls '/ajax/getworkorderdetails.php' synchronously via POST method, 
    passing the original input workOrderId. On AJAX failure, it raises an alert. 
    On AJAX success, it makes sure that the returned status='success'; if not, it fails silently, >>>00006 should probably raise an alert. 
    On status='success' it builds new inner HTML and inserts it into the DIV with ID "scrolling-wrapper". 
    Other than what AJAX is called, this is very similar to local function getChildDetails(parentId), 
    >>>00006 and should probably share code, so I (JM) am not documenting this in the usual detail. 
    NOTE, however, that here we we create the "add" button and its cell only if results[i]['exists'] != 1  
    
    Several other functions call this to redisplay on completion.
*/ ?>
var getWorkOrderDetails = function() {
    clearAll(); 
    var resultcell = document.getElementById("scrolling-wrapper");
    resultcell.innerHTML = '';

    $.ajax({
        url: '/ajax/getworkorderdetails.php',
        data: {
            workOrderId:<?php echo intval($workOrder->getWorkOrderId());  ?> 
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {        
                var html = '';                        
                //var resultcell = document.getElementById('currentDetails');  // Commented out by Martin before 2019        
                var results = data['details'];
                for (i = 0; i < results.length; i++) {
                    html += '<div id="detId_' + results[i]['detailId'] + '" class="detBox">';        
                        html += '<table border="0" cellspacing="2" cellpadding="2">';

                            //if (results[i]['exists'] == 1){ // Commented out by Martin before 2019
                            html += '<tr bgcolor="#ddffdd">';
                            // BEGIN Commented out by Martin before 2019    
                            //} else {
                            //	html += '<tr bgcolor="#dddddd">';
                            //}
                            // END Commented out by Martin before 2019
                    
                                html += '<td colspan="4">';
                            
                                    // Martin comment here 2
                                    html += '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
                                        html += '<tr>';
                                            html += '<td>' + results[i]['fullname'];
                                            if (results[i]['childCount'] > 0) {
                                                html += '&nbsp;&nbsp;[' + results[i]['childCount'] + ']';
                                            }
                                            html += '</td>';        
                                            if (results[i]['pdfurl']) {
                                                html += '<td><a href="' + results[i]['pdfurl'] + '"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_pdf_64x64.png" width="24" height="24"></a></td>';
                                            }
                                            <?php /* >>>00006 In the following, I suspect "! =1" may be a dangerously narrow test. I'd really
                                                     like to know exactly what are the possible values of results[i]['exists'] */ ?>
                                            if (results[i]['exists'] != 1) {
                                                html += '<td align="right"><a class="buttonAdd" href="javascript:removeDetail(' + escape(results[i]['detailRevisionId']) + ')">remove</a></td>';
                                            }
                                        html += '</tr>';
                                    html += '</table>';        
                                html += '</td>';
                            html += '</tr>';
                            
                            html += '<tr bgcolor="#ffffff" align="center">';
                                html += '<td colspan="4">';                
                                html += '<a href="javascript:clickScroller(' + escape(results[i]['detailId']) + ')"><img src="' + results[i]['pngurl'] + '"></a>';                
                                html += '</td>';                                        
                            html += '</tr>';

                            if (results[i]['classifications']) {
                                html += '<tr bgcolor="#dddddd" class="border_bottom">';
                                    html += '<td class="classification" width="22%" align="center"><em>Material</em></td>';
                                    html += '<td class="classification" width="22%" align="center"><em>Component</em></td>';
                                    html += '<td class="classification" width="22%" align="center"><em>Function</em></td>';
                                    html += '<td class="classification" width="22%" align="center"><em>Force</em></td>';        
                                html += '</tr>';

                                var classifications = results[i]['classifications'];
                                for (j = 0; j < classifications.length; j++) {                
                                    //if (j > 0){ // Commented out by Martin before 2019
                                        html += '<tr class="border_bottom">';
                                    // BEGIN Commented out by Martin before 2019
                                    //} else {
                                    //	html += '<tr>';
                                    //}                                           
        
                                    //html += '<tr>';
                                    // END Commented out by Martin before 2019
                                        html += '<td class="classification">' + classifications[j]['detailMaterialName'] + '</td>';
                                        html += '<td class="classification">' + classifications[j]['detailComponentName'] + '</td>';
                                        html += '<td class="classification">' + classifications[j]['detailFunctionName'] + '</td>';
                                        html += '<td class="classification">' + classifications[j]['detailForceName'] + '</td>';
                                    html += '</tr>';                
                                }
                            }
                    
                            html += '<tr>';
                            html += '</tr>';
                        html += '</table>';        
                    html += '</div>';
                }
                resultcell.innerHTML = html;                        
            }
            // Martin comment reload details here        
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
} // END function getWorkOrderDetails

<?php /* local function clearAll */ ?>
var clearAll = function() {
    $('#material_1').prop('selectedIndex', 0);
    $('#component_1').prop('selectedIndex', 0);
    $('#function_1').prop('selectedIndex', 0);
    $('#force_1').prop('selectedIndex', 0);
    $('#material_2').prop('selectedIndex', 0);
    $('#component_2').prop('selectedIndex', 0);
    $('#function_2').prop('selectedIndex', 0);
    $('#force_2').prop('selectedIndex', 0);
    $('#version_ac').val('');
    $('#keywords').val('');
    clear();    
}

<?php /* local function clear */ ?>
var clear = function() {
//	var cell1 = document.getElementById("container1"); // Commented out by Martin before 2019
    var cell2 = document.getElementById("scrolling-wrapper");
    var dc = document.getElementById("datacell");
    var fn = document.getElementById("revFullName");
    var ti = document.getElementById("revTypeItems");
    var r = document.getElementById("revRevisions");
    var d = document.getElementById("revDiscussions");
    var oc = document.getElementById("revObjectCell");
    var lc = document.getElementById("linkCell");
    
    if (dc) {
        dc.innerHTML = '';
    }
    // BEGIN Commented out by Martin before 2019
    //if (fn){
        //fn.innerHTML = '';
    //}
    // END Commented out by Martin before 2019
    if (ti) {
        ti.innerHTML = '';
    }
    if (r) {
        r.innerHTML = '';
    }
    if (d) {
        d.innerHTML = '';
    }
    if (oc) {
        oc.innerHTML = '';
    }
    if (lc) {
        lc.innerHTML = '';
    }
    if (cell2) {
        cell2.innerHTML = '';
    }
} // END function clear

<?php /* local function clickScroller
         INPUT detailId
         Clears any prior class "highlightBox" from anything of class "detBox"; 
         it then adds class "highlightBox" to the DIV indicated by input detailId  
         and calls local function showDetail(detailId).  
*/ ?>
var clickScroller = function(detailId) {
    $(".detBox").each(function( index ) {
          $(this).removeClass("highlightBox");
    });

    $("#detId_" + detailId).addClass("highlightBox");    
    showDetail(detailId);    
}

<?php /* local function populateRevision
         INPUT detailRevisionId
         Calls '/ajax/getdetailrevisiondata.php' synchronously via POST method, 
         passing detailRevisionId. On AJAX failure, it fails silently 
         (>>>00006 JM: Probably should raise an alert). 
         On AJAX success, it does not check the returned status='success'; instead, 
         it immediately checks data['detailRevisionData']; if that is truthy it continues, 
         otherwise it fails silently (>>>00006 JM: Probably should raise an alert).
         
         Assuming data['detailRevisionData'] is truthy, this builds new inner HTML for the cells with IDs 'revObjectCell' and 'linkCell'.
         See code & inline comments for details. */ ?> 
var populateRevision = function(detailRevisionId) {
    $.ajax({
        url: '/ajax/getdetailrevisiondata.php',
        data: {
            detailRevisionId:detailRevisionId
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {        
            if (data['detailRevisionData']) {        
                // BEGIN Commented out by Martin before 2019
                //if (data['detailRevisionData']['fullName']){
                //	var fn = document.getElementById('revFullName');
                //	if (fn){
                //		fn.innerHTML = '<h1>' + data['detailRevisionData']['fullName'] + '</h1>';
                //	}        
                //}

                var html = '';
                if (data['detailRevisionData']['pdfurl']) {
                    <?php /* Build new inner HTML for 'revObjectCell': 
                             an HTML OBJECT element embedding the PDF data['detailRevisionData']['pdfurl']. 
                             Inside the object element we put text that will be visible only if the object 
                             cannot be displayed, saying "Can't view so download", where "download" is a link to download he PDF). 
                    */ ?>                             
                    var oc = document.getElementById("revObjectCell");
                    html += '<object id="objectelement" data="' + data['detailRevisionData']['pdfurl'] + '#scrollbar=0" type="application/pdf" width="100%" height="100%">';
                    html += '<p>Can\'t view so <a href="' + data['detailRevisionData']['pdfurl'] + '">download</a></p>';
                    html += '</object>';
                    oc.innerHTML = html;
                }

                html = '';        
                if (data['detailRevisionData']['dlpdfurl']) { <?php /* (the form that is not for embedding, unlike the previous "if") */ ?>        
                    <?php /* Build new inner HTML for 'linkCell' */ ?> 
                    html = '';                            
                    var lc = document.getElementById("linkCell");        
                    <?php /* Link the PDF; link displays icon_pdf_64x64.png shrunk to 36x36.
                             Under that link is is "Rev:" followed revision number. 
                    */ ?> 
                    html += '<a href="' + data['detailRevisionData']['dlpdfurl'] + '"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_pdf_64x64.png" width="36" height="36"></a>';
                    html += '<p>Rev:' + data['detailRevisionData']['revnum'];
                    html += '<br>';
                    //	html += data['detailRevisionData']['code']; // Commented out by Martin before 2019        

                    <?php /* OLD CODE removed 2019-02-15 JM  
                    html += '<a target="_blank" href="http://detail.ssseng.com/manage/index.php?detailRevisionId=' + data['detailRevisionData']['detailRevisionId'] + '">' + data['detailRevisionData']['code'] + '</a>';
                    */
                    ?>
                    <?php /* BEGIN NEW CODE 2019-02-15 JM */ ?>
                    <?php /* Link to open a page from the Details system in a new window or tab. Displays 'code', 
                             and the target of the link DETAIL_ROOT should always be on the production system, even
                             if we are on a test/dev system. E.g. for SSS, DETAIL_ROOT is 'http://detail.ssseng.com'.                              
                    */ ?>
                    html += '<a target="_blank" href="<?php echo DETAIL_ROOT; ?>/manage/index.php?detailRevisionId=' + 
                            data['detailRevisionData']['detailRevisionId'] + '">' + data['detailRevisionData']['code'] + '</a>';
                    <?php /* END NEW CODE 2019-02-15 JM */ ?>
                            
                    //html += '<p></b>'; // Commented out by Martin before 2019
                    html+= '<p>&nbsp;</p>';
                    
                    <?php /* Button labeled 'add' calls local function addDetail(detailRevisionId). 
                             This button is styled with class="buttonAdd" (as against "buttonClear"). 
                    */ ?>
                    html += '<a class="buttonAdd" href="javascript:addDetail(' + escape(data['detailRevisionData']['detailRevisionId']) + ')">add</a>';
                    
                    html += '<p>'; <?php /* >>>00031 bad use of <p>, opens a paragraph it never closes */ ?>         

                    <?php /* if data['detailRevisionData']['infolinks'] exists, then we loop over those, writing out an appropriate icon for each link.
                             Each link opens in a new tab/window. 
                    */ ?> 
                    if (data['detailRevisionData']['infolinks']) {        
                        for (var k = 0; k < data['detailRevisionData']['infolinks'].length; k++) {
                            html += '<a target="_blank" href="' + data['detailRevisionData']['infolinks'][k]['link'] + '">' +
                                    '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_detinfo_' + 
                                    data['detailRevisionData']['infolinks'][k]['typeName'] + '_64x64.png" width="32" height="32"></a>';
                            html += '<p>';  <?php /* >>>00031 bad use of <p>, opens a paragraph it never closes */ ?>
                        }
                    }                            
                    lc.innerHTML =  html;        
                }
                /*
                // BEGIN Commented out by Martin before 2019
                html = '';                        
                if (data['detailRevisionData']['typeItems']){        
                    html += '<table border="1">';                            
                    for (i = 0; i < data['detailRevisionData']['typeItems'].length; i++){        
                        html += '<tr>';        
                            html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailMaterialName'] + '</td>';
                            html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailComponentName'] + '</td>';
                            html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailFunctionName'] + '</td>';
                            html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailForceName'] + '</td>';                                                        
                        html += '</tr>';        
                    }

                    html += '</table>';        
                    var ti = document.getElementById('revTypeItems');        
                    ti.innerHTML = html;                            
                }

                html = '';        
                if (data['detailRevisionData']['revisions']){        
                    html += '<table border="1">';                            
                    for (i = 0; i < data['detailRevisionData']['revisions'].length; i++){        
                        html += '<tr>';        
                            html += '<td>' + data['detailRevisionData']['revisions'][i]['status'] + '</td>';
                            html += '<td>' + data['detailRevisionData']['revisions'][i]['code'] + '</td>';
                            html += '<td>' + data['detailRevisionData']['revisions'][i]['caption'] + '</td>';
                            html += '<td>' + data['detailRevisionData']['revisions'][i]['createReason'] + '</td>';
                        html += '</tr>';
                    }        
                    html += '</table>'; 
                    
                    var r = document.getElementById('revRevisions');        
                    r.innerHTML = html;                            
                }

                html = '';        
                if (data['detailRevisionData']['revisions']){
                    html +=  '<table border="1" cellpadding="3" cellsapcing="2">';
                        html +=  '<tr bgcolor="#e5bfff">';
                        html +=  '<th>Revision</th>';
                        html +=  '<th colspan="3">Discussion</th>';
                        html +=  '</tr>';
                        
                        html +=  '<tr bgcolor="#e5bfff">';
                            html +=  '<th>code/date/reason</th>';
                            html +=  '<th>Note</th>';
                            html +=  '<th>Person</th>';    
                            html +=  '<th>Inserted</th>';
                        html +=  '</tr>';
                    
                    for (i = 0; i < data['detailRevisionData']['revisions'].length; i++){        
                        var ds = data['detailRevisionData']['revisions']['discussions'];
                        var span = data['detailRevisionData']['revisions'][i]['discussions'].length;
                        if (span < 2){
                            span = 1;
                        }

                        if (data['detailRevisionData']['revisions'][i]['discussions'].length > 0){        
                            for (j = 0; j < data['detailRevisionData']['revisions'][i]['discussions'].length; j++){        
                                    html += '<tr>';
                                    
                                    if (j == 0){                                                
                                        html += '<td rowspan="' + span  + '" bgcolor="#dddddd">';
                                        html += data['detailRevisionData']['revisions'][i]['discussions'][j]['cdr'];
                                        html += '</td>';                                                
                                    }
                                    
                                    html += '<td bgcolor="#eeeeee">' + data['detailRevisionData']['revisions'][i]['discussions'][j]['note'] + '</td>';
                                    html += '<td bgcolor="#eeeeee">';                                            
                                    html += data['detailRevisionData']['revisions'][i]['discussions'][j]['initials'];        
                                    html += '</td>';        
                                    html += '<td bgcolor="#eeeeee">' + data['detailRevisionData']['revisions'][i]['discussions'][j]['inserted'] + '</td>';
                                    html += '</tr>';
                            }
                        }
                    }        
                    html += '</table>';

                    var d = document.getElementById('revDiscussions');        
                    d.innerHTML = html;                            
                }
                // END Commented out by Martin before 2019
                */
            }                    
            //getWorkOrderDetails(); // Commented out by Martin before 2019        
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // BEGIN Commented out by Martin before 2019
            //getWorkOrderDetails();        
            //alert('error');
            // END Commented out by Martin before 2019
        }
    });
} // END populateRevision


<?php /* local function showDetail
         INPUT detailId 
         This mainly fills in the "resultcell" DIV; it also fills in "revObjectCell" and "linkCell". 
         Clears the "resultcell" DIV, calls /ajax/getdetaildata.php synchronously via POST method, passing detailId, 
         and on successful return (data['status'] == 'success') fills the "resultcell" DIV  
         based on the return data['detailData'] (errors produce alerts). 
         See code & inline comments for details. */ ?>
var showDetail = function(detailId) {
    getChildDetails(detailId);    
    var resultcell = document.getElementById("datacell");    
    resultcell.innerHTML = '';    
    $.ajax({
        url: '/ajax/getdetaildata.php',
        data: {
            detailId: detailId
            },
        async: false,
        type: 'get',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {
                var html = '';    
                if (data['detailData']) {
                    <?php /* detailData will be an associative array.
                             We first call local function populateRevision with the current DetailRevisionId
                             to fill "revObjectCell" and "linkCell", then start building the content to fill "resultcell". 
                    */ ?>
                    var detailData = data['detailData'];    
                    var currentDetailRevisionId = detailData['currentDetailRevisionId'];    
                    if (currentDetailRevisionId > 0) {    
                        populateRevision(currentDetailRevisionId);
                    }
                    <?php /* As so often, we use nested tables. 
                             The outer (level 0) table will consist of a single row with two columns, the second of which is always empty 
                             (so presumably this is just for layout). The first column contains a nested table, described below. */ ?>
                    html += '<table border="0" cellpadding="10" cellspacing="0">';
                        html += '<tr>';
                            html += '<td>';        
                                <?php /* Level 1 table */ ?> 
                                html += '<table border="0" cellpadding="3" cellspacing="0">';
                                    <?php /* Level 1, row 1 contains an H2 heading of detailData['fullName'].
                                                * If there is no detailData['parentTree'] or if it is an empty array, that is the only significant content. 
                                                * If detailData['parentTree'] is an empty array, we end up with sort of a degenerate case; 
                                                  >>>00006 JM: probably should check for that and handle it the same way as no detailData['parentTree']. */ ?>
                                    html += '<tr>';
                                        html += '<td>';
                                            html += '<h2>' + detailData['fullName'] + '</h2>';
                                        html += '</td>';
                                    html += '</tr>';
    
                                    <?php /* If there is a non-empty detailData['parentTree'], then call that array just parentTree, and
                                             start to build the subsequent rows for the level 1 table. */ ?>
                                    if (detailData['parentTree']) {
                                        var parentTree = detailData['parentTree'];
        
                                        html += '<tr>';
                                            html += '<td>';
<?php /* OUTDENT Level 2 table */ ?>
html += '<table border="0" cellpadding="0" cellspacing="0">';
    x = parentTree.length;

    <?php /* Using a for-loop (>>>00006 JM: which is more convoluted than need be) we indent further for each successive row, 
             using a successively larger number of columns containing multiple non-breaking spaces, and covering all columns 
             to the right in a single colspan to display the actual meaningful content
             */ ?>
    for (j = 0; j < parentTree.length; j++) {                                                    
        html += '<tr>';
            if ((parentTree.length - x) > 0) {        
                for (var z = 0; z < (parentTree.length - x); z++) {        
                    html += '<td>&nbsp;&nbsp;&nbsp;</td>';        
                }                
            }
            //html += '<td colspan="' +  (parentTree.length - j) + '">'; // Commented out by Martin before 2019

            if (parentTree[j]['detailId'] == detailId) {
                html += '<td bgcolor="#ddffdd" colspan="' +  (parentTree.length - j) + '">';
            } else {
                html += '<td colspan="' +  (parentTree.length - j) + '">';
            }                                                        

                // BEGIN Commented out by Martin before 2019
                //html += " " + (parentTree.length - x) + " ";        
                //html += " " + (parentTree.length - j) + " ";
                // END Commented out by Martin before 2019
                
                if (j > 0) {
                    html += ".";
                }
                
                <?php /* The actual meaningful content: detail name, with a link to local function showDetail.
                         NOTE that that is quasi-recursive, since we are inside local function showDetail right now. */ ?>
                html += '<a href="javascript:showDetail(' + parentTree[j]['detailId'] + ')">' + parentTree[j]['name'] + '</a>';
            html += '</td>';
        html += '</tr>';        
        x--;        
        if (x == 0) {
            <?php /*  An extra row at the bottom of the table containing another nested table. 
                      Before that table are blank columns to give it an indent reflecting the number of parents; 
                      that indent is done even if we don't do the nested table itself. */ ?>
            html += '<tr>';
                for (var z = 0; z < (parentTree.length - x - 1); z++) {        
                    html += '<td>&nbsp;&nbsp;&nbsp;</td>';        
                }                                                        
                html += '<td colspan="' +  (parentTree.length - j) + '">';

                <?php /* The nested table exists only if there is a data['detailChildren'] 
                         (>>>00006 JM: probably there should be a test for that being an empty array, as well). 
                         data['detailChildren'] is an array; the nested table has one row per element. 
                         Each row (with index k) has the following columns (no headers):
                           * just 3 nonbreaking spaces
                           * Displays data['detailChildren'][k]['name']; clicking this makes a 
                             quasi-recursive call to the current function, showDetail].  */ ?>
                if (data['detailChildren']) {                                                            
                    html += '<table border="0" cellpadding="0" cellspacing="0">';        
                        for (var k = 0; k < (data['detailChildren'].length); k++) {
                            html += '<tr>';
                                html += '<td>&nbsp;&nbsp;&nbsp;</td>';
                                if (data['detailChildren'][k]['detailId'] == detailId) {
                                    <?php /* Matching detailId (as passed into showDetail): this column gets a very light green background. */ ?>
                                    html += '<td bgcolor="#ddffdd">';
                                } else {
                                    html += '<td>';
                                }
                                    html += '.<a href="javascript:showDetail(' + data['detailChildren'][k]['detailId'] + ')">' + 
                                            data['detailChildren'][k]['name'] + '</a>';
                                html += '</td>';
                            html += '</tr>';            
                        }
                    html += '</table>';        
                }
                html += '</td>';
            html += '</tr>';
        }
    }                                             

html += '</table>';
<?php /* END OUTDENT Level 2 table */ ?>
                                            html += '</td>';
                                        html += '</tr>';
                                    }
                                        
                                html += '</table>';
                            html += '</td>';
                            html += '<td>';        
                                html += '';        
                            html += '</td>';
                        html += '</tr>';
                    html += '</table>';
                }                    
                resultcell.innerHTML = html;
            } else {
                alert('there was a problem fetching');
            }		    
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });    
    //getChildDetails(detailId);    
} // END function showDetail

<?php /* local function optionSearch() 
         Fills in the "scrolling-wrapper" DIV, based on the selected quasi-inputs:
          * material_1, component_1, function_1, force_1
          * material_2, component_2, function_2, force_2 
         values from the embedded table near the top of the fancybox. 
         (There is also code to access a nonexistent material_3, component_3, function_3, force_3.)
         
         Other quasi-inputs: sets $term from the "version_ac" HTML INPUT element and $keywords from the "keywords" HTML INPUT element. 
         
         Clears the "scrolling-wrapper" DIV, makes an AJAX call to /ajax/getdetailsearch.php passing all these values, 
         and on successful return (data['status'] == 'success') fills the "scrolling-wrapper" DIV, 
         based on the return data['data']['searchresults'] (errors produce alerts).

         >>>00006 The code here for a DIV with id="detId_result['detailId']" and class="detBox" is identical 
         to the code in local function getWorkOrderDetails() and very similar to local function getChildDetails(parentId), 
         and should probably share code, so I (JM) am not separately commenting here.
         
         Like getWorkOrderDetails(), we create the "add" button and its cell only if results[i]['exists'] != 1.
*/ ?>
var optionSearch = function() {    
    clear();
    
    var material_1 = $('#material_1');
    var component_1 = $('#component_1');
    var func_1 = $('#function_1');
    var force_1 = $('#force_1');
    
    var material_2 = $('#material_2');
    var component_2 = $('#component_2');
    var func_2 = $('#function_2');
    var force_2 = $('#force_2');

    var material_3 = $('#material_3');
    var component_3 = $('#component_3');
    var func_3 = $('#function_3');
    var force_3 = $('#force_3');

    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //var materialValue_1 = 2;
    //var componentValue_1 = 1;
    //var functionValue_1 = 9;
    //var forceValue_1 = 8;
    // END COMMENTED OUT BY MARTIN BEFORE 2019

    var materialValue_1 = material_1.find(":selected").val();
    var componentValue_1 = component_1.find(":selected").val();
    var functionValue_1 = func_1.find(":selected").val();
    var forceValue_1 = force_1.find(":selected").val();
    
    var materialValue_2 = material_2.find(":selected").val();
    var componentValue_2 = component_2.find(":selected").val();
    var functionValue_2 = func_2.find(":selected").val();
    var forceValue_2 = force_2.find(":selected").val();

    var materialValue_3 = material_3.find(":selected").val();
    var componentValue_3 = component_3.find(":selected").val();
    var functionValue_3 = func_3.find(":selected").val();
    var forceValue_3 = force_3.find(":selected").val();

    var term = $( "#version_ac" ).val();    
    var keywords = $('#keywords').val();

    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //alert(materialValue);
    //alert(componentValue);
    //alert(functionValue);
    //alert(forceValue);
    // END COMMENTED OUT BY MARTIN BEFORE 2019

    var resultcell = document.getElementById("scrolling-wrapper");
    resultcell.innerHTML = '';
        
    $.ajax({
        url: '/ajax/getdetailsearch.php',
        data: {
            workOrderId: <?php echo $workOrder->getWorkOrderId(); ?>,
            material_1: materialValue_1,    
            component_1: componentValue_1,    
            func_1: functionValue_1,    
            force_1: forceValue_1,    
            material_2: materialValue_2,    
            component_2: componentValue_2,    
            func_2: functionValue_2,    
            force_2: forceValue_2,  
            material_3: materialValue_3,    
            component_3: componentValue_3,    
            func_3: functionValue_3,    
            force_3: forceValue_3,
            term: term,
            keywords: keywords
            },
        async: false,
        type: 'get',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {
                // BEGIN MARTIN COMMENT
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
                // END MARTIN COMMENT   
                
                var html = '';
                //var resultcell = document.getElementById('results'); // Commented out by Martin before 2019

                var results = data['data']['searchresults'];    
                //html  += '<table border="0" cellpadding="2" cellspacing="1">'; // Commented out by Martin before 2019

                for (i = 0; i < results.length; i++) {
                    html += '<div id="detId_' + results[i]['detailId'] + '" class="detBox">';    
                        html += '<table border="0" cellspacing="2" cellpadding="2">';    
                            if (results[i]['exists'] == 1) {
                                html += '<tr bgcolor="#ddffdd">';
                            } else {
                                html += '<tr bgcolor="#dddddd">';
                            }                        
                                html += '<td colspan="4">';

                                    // Martin comment: here 3
                                    html += '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
                                        html += '<tr>';
                                            html += '<td>' + results[i]['fullname'];
                                                if (results[i]['childCount'] > 0) {
                                                    html += '&nbsp;&nbsp;[' + results[i]['childCount'] + ']';
                                                }                
                                            html += '</td>';
                                            
                                            if (results[i]['pdfurl']) {
                                                html += '<td><a href="' + results[i]['pdfurl'] + '"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_pdf_64x64.png" width="24" height="24"></a></td>';
                                            }
                                            if (results[i]['exists'] != 1) {
                                                html += '<td align="right"><a class="buttonAdd" href="javascript:addDetail(' + escape(results[i]['detailRevisionId']) + ')">add</a></td>';
                                            }
                                        html += '</tr>';
                                    html += '</table>';
                                html += '</td>';
                            html += '</tr>';
                            html += '<tr bgcolor="#ffffff" align="center">';
                                html += '<td colspan="4">';    
                                    //html += '<a href="' + results[i]['pdfurl'] + '"><img src="' + results[i]['pngurl'] + '"></a>'; // Commented out by Martin before 2019
                                    html += '<a href="javascript:clickScroller(' + escape(results[i]['detailId']) + ')"><img src="' + results[i]['pngurl'] + '"></a>';
                                html += '</td>';
                            html += '</tr>';
                            
                            if (results[i]['classifications']) {
                                html += '<tr bgcolor="#dddddd" class="border_bottom">';
                                    html += '<td class="classification" width="22%" align="center"><em>Material</em></td>';
                                        html += '<td class="classification" width="22%" align="center"><em>Component</em></td>';
                                        html += '<td class="classification" width="22%" align="center"><em>Function</em></td>';
                                        html += '<td class="classification" width="22%" align="center"><em>Force</em></td>';
                                    html += '</tr>';

                                    var classifications = results[i]['classifications'];
                                    for (j = 0; j < classifications.length; j++) {

                                        //if (j > 0){  // Commented out by Martin before 2019
                                        html += '<tr class="border_bottom">';
                                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                            //} else {
                                            //	html += '<tr>';
                                            //}
                                            //html += '<tr>';
                                            // END COMMENTED OUT BY MARTIN BEFORE 2019
                                            html += '<td class="classification">' + classifications[j]['detailMaterialName'] + '</td>';
                                            html += '<td class="classification">' + classifications[j]['detailComponentName'] + '</td>';
                                            html += '<td class="classification">' + classifications[j]['detailFunctionName'] + '</td>';
                                            html += '<td class="classification">' + classifications[j]['detailForceName'] + '</td>';
                                        html += '</tr>';
                                    }
                            }

                            html += '<tr>';
                            html += '</tr>';                       
                    
                            // In the unlikely event that the following ever comes back to life, needs to use constants from config.php - JM 2019-02-15
                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                            //html += '<img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';
                            // END COMMENTED OUT BY MARTIN BEFORE 2019
                        html += '</table>';    
                    html += '</div>';
                    
                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019    
                    //	html += '<td colspan="5" bgcolor="#cccccc">' + results[i]['fullname'] + '&nbsp;[<a href="javascript:addWorkOrderDetail(' + escape(results[i]['detailRevisionId']) + ')">add</a>]</td>';
                    // END COMMENTED OUT BY MARTIN BEFORE 2019

                    /*
                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
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
                                        // In the unlikely event that the following ever comes back to life, needs to use constants from config.php - JM 2019-02-15
//									    html += '<td rowspan="' + (classifications.length + 1) + '" ><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';

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
                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                    */
                    
                    //html += '<p>' + results[i]['name'] + ' : ' + results[i]['code'] + '<p>'; // COMMENTED OUT BY MARTIN BEFORE 2019    
                }
        
                //html += '</table>'; // COMMENTED OUT BY MARTIN BEFORE 2019
                
                resultcell.innerHTML = html;
            } else {
                alert('there was a problem fetching');
            }		    
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
} // END function optionSearch


<?php /* >>>00007 NOT USED, JUST GET RID OF THIS. */ ?>
var optionSearchOLD = function() {
    var material_1 = $('#material_1');
    var component_1 = $('#component_1');
    var func_1 = $('#function_1');
    var force_1 = $('#force_1');
    
    var material_2 = $('#material_2');
    var component_2 = $('#component_2');
    var func_2 = $('#function_2');
    var force_2 = $('#force_2');

    var material_3 = $('#material_3');
    var component_3 = $('#component_3');
    var func_3 = $('#function_3');
    var force_3 = $('#force_3');
    
    var materialValue_1 = material_1.find(":selected").val();
    var componentValue_1 = component_1.find(":selected").val();
    var functionValue_1 = func_1.find(":selected").val();
    var forceValue_1 = force_1.find(":selected").val();
    
    var materialValue_2 = material_2.find(":selected").val();
    var componentValue_2 = component_2.find(":selected").val();
    var functionValue_2 = func_2.find(":selected").val();
    var forceValue_2 = force_2.find(":selected").val();

    var materialValue_3 = material_3.find(":selected").val();
    var componentValue_3 = component_3.find(":selected").val();
    var functionValue_3 = func_3.find(":selected").val();
    var forceValue_3 = force_3.find(":selected").val();

    var term = $( "#version_ac" ).val();

    //alert(materialValue);
    //alert(componentValue);
    //alert(functionValue);
    //alert(forceValue);

    var resultcell = document.getElementById("scrolling-wrapper");
    resultcell.innerHTML = '';

    
    $.ajax({
        url: '/ajax/getdetailsearch.php',
        data:{
            material_1:materialValue_1,    
            component_1:componentValue_1,    
            func_1:functionValue_1,    
            force_1:forceValue_1,    
            material_2:materialValue_2,    
            component_2:componentValue_2,    
            func_2:functionValue_2,    
            force_2:forceValue_2,  
            material_3:materialValue_3,    
            component_3:componentValue_3,    
            func_3:functionValue_3,    
            force_3:forceValue_3,
            term:term
            },
        async:false,
        type:'get',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {
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
                //var resultcell = document.getElementById('results');
                var results = data['data']['searchresults'];
                html  += '<table border="0" cellpadding="2" cellspacing="1">';
                for (i = 0; i < results.length; i++) {
                    html += '<tr>';
                        html += '<td colspan="5" bgcolor="#cccccc">' + results[i]['fullname'] + '&nbsp;[<a href="javascript:addWorkOrderDetail(' + escape(results[i]['detailRevisionId']) + ')">add</a>]</td>';
                    html += '</tr>';
                    
                    if (results[i]['classifications']) {
                        html += '<tr>';
                            html += '<td width="12%">&nbsp;</td>';
                            html += '<td width="22%"><em>Material</em></td>';
                            html += '<td width="22%"><em>Component</em></td>';
                            html += '<td width="22%"><em>Function</em></td>';
                            html += '<td width="22%"><em>Force</em></td>';
                        html += '</tr>';

                        var classifications = results[i]['classifications'];
                        for (j = 0; j < classifications.length; j++) {
                            html += '<tr>';
                            if (j == 0) {
                                        // In the unlikely event that the following ever comes back to life, needs to use constants from config.php - JM 2019-02-15
                                        // html += '<td rowspan="' + (classifications.length + 1) + '" ><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>'; // COMMENTED OUT BY MARTIN BEFORE 2019
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
                html ='';
                html += '<div class="card">item 1</div>';
                html += '<div class="card">item 2</div>';
                html += '<div class="card">item 3</div>';
                html += '<div class="card">item 4</div>';
                html += '<div class="card">item 5</div>';
                html += '<div class="card">item 6</div>';
                html += '<div class="card">item 7</div>';
                html += '<div class="card">item 8</div>';
                html += '<div class="card">item 9</div>';
                html += '<div class="card">item 10</div>';
                html += '<div class="card">item 11</div>';
                html += '<div class="card">item 12</div>';
                html += '<div class="card">item 13</div>';
                html += '<div class="card">item 14</div>';
                html += '<div class="card">item 15</div>';
                html += '<div class="card">item 16</div>';
                html += '<div class="card">item 17</div>';
                html += '<div class="card">item 18</div>';
                html += '<div class="card">item 19</div>';
                html += '<div class="card">item 20</div>';
                html += '<div class="card">item 21</div>';
                html += '<div class="card">item 22</div>';
                html += '<div class="card">item 23</div>';
                resultcell.innerHTML = html;
            } else {
                alert('there was a problem fetching');
            }		    
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
} // END function optionSearchOLD


$(function() {
    $('#version_ac').autocomplete({
        source: function(request, response) {

            /*
            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
            var material_1 = $('#material_1');
            var component_1 = $('#component_1');
            var func_1 = $('#function_1');
            var force_1 = $('#force_1');
            
            var material_2 = $('#material_2');
            var component_2 = $('#component_2');
            var func_2 = $('#function_2');
            var force_2 = $('#force_2');
            
            var materialValue_1 = material_1.find(":selected").val();
            var componentValue_1 = component_1.find(":selected").val();
            var functionValue_1 = func_1.find(":selected").val();
            var forceValue_1 = force_1.find(":selected").val();
            
            var materialValue_2 = material_2.find(":selected").val();
            var componentValue_2 = component_2.find(":selected").val();
            var functionValue_2 = func_2.find(":selected").val();
            var forceValue_2 = force_2.find(":selected").val();
            // END COMMENTED OUT BY MARTIN BEFORE 2019
            */
            $.getJSON(
                "/ajax/getdetailversionac.php", 
                { 
                    term: $('#version_ac').val()
                    /*
                    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                    materialValue_1:materialValue_1,
                    componentValue_1:componentValue_1,
                    functionValue_1:functionValue_1,
                    forceValue_1:forceValue_1
                    // END COMMENTED OUT BY MARTIN BEFORE 2019
                    */
                }, 
                response);
        },
        minLength: 2,
        autoFocus: true,
        select: function( event, ui ) {
            //$( "#version_ac" ).val(ui.item.value) // COMMENTED OUT BY MARTIN BEFORE 2019    
            clickScroller(ui.item.id);                
            //log( "Selected: " + ui.item.value + " aka " + ui.item.id ); // COMMENTED OUT BY MARTIN BEFORE 2019
        }
    }).mousedown(function() {            
        $(this).autocomplete("search");
    });        
    
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //.on('keypress click', function(e){
    //    
    //    if (e.which === 13){
    //    	$('#version_ac').autocomplete({selectFirst:true});
    //    	//clickScroller($(this).element[0].id);
    //    }
    //		if (e.type === 'click') {
    //			$(this).autocomplete("search");
    //	    }
    //	  });
    //.mousedown(function(){            
    //	$(this).autocomplete("search");
    //});
    // END COMMENTED OUT BY MARTIN BEFORE 2019
});

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
$( "#version_ac" ).autocomplete({
    source: "/ajax/getdetailversionac.php",
    minLength: 2,
    select: function( event, ui ) {
        //$( "#version_ac" ).val(ui.item.value)

        clickScroller(ui.item.id);
        
      //log( "Selected: " + ui.item.value + " aka " + ui.item.id );
    }
  });
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

<?php /* >>>00007 NOT USED, JUST GET RID OF THIS. */ ?>
function toggleDisplay(optionnumber) {
    var x = document.getElementById("optionsDiv_" + optionnumber);
    if (x.style.display === "none") {
        x.style.display = "block";
        //createCookie('lastJobId', jobId, 10); // Commented out by Martin before 2019
    } else {
        x.style.display = "none";
    }
} // END function toggleDisplay

<?php /* 
local function addDescriptorDetails
Clears everything, then calls /ajax/addworkorderdescriptordetails.php synchronously via POST method, 
passing the original input workOrderId. On AJAX failure it alerts; on AJAX success it does not check 
the return status (>>>00012 JM: presumably should), just calls local function getWorkOrderDetails to redisplay.
*/ ?>
var addDescriptorDetails = function () {
    clearAll();    
    var  formData = "workOrderId=" + escape(<?php echo $workOrder->getWorkOrderId(); ?>); 
    
    $.ajax({
        url: '/ajax/addworkorderdescriptordetails.php',
        data: {workOrderId:<?php echo $workOrder->getWorkOrderId();?>},
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            getWorkOrderDetails();            
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
      });    
}

<?php /* 
local function addTaskDetails
Clears everything, then calls /ajax/addworkordertaskdetails.php synchronously via POST method, 
passing the original input workOrderId. On AJAX failure it alerts; on AJAX success it does not check 
the return status (>>>00012 JM: presumably should), just calls local function getWorkOrderDetails to redisplay.
*/ ?>
var addTaskDetails = function () {
    clearAll(); 
    <?php /* >>>00006 JM: There is some sloppiness here where we set a variable formData using an escape, 
             then actually pass data that ignores formData and does not use an escape.) */ ?>
    var  formData = "workOrderId=" + escape(<?php echo $workOrder->getWorkOrderId(); ?>);  
    
    $.ajax({
        url: '/ajax/addworkordertaskdetails.php',
        data: {workOrderId:<?php echo $workOrder->getWorkOrderId();?>},
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {    
            getWorkOrderDetails();                
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });        
}

</script>

<?php
include '../includes/footer_fb.php';
?>