<?php
/*  _admin/descriptor/detail.php

    EXECUTIVE SUMMARY: PAGE to manage details for a descriptor2 (before 2019-12, was a descriptorSub).
    
    >>>00032 JM It looks to me like this was left in a state 2019-01 where there is no way to 
    add a detail to the descriptorSub (and hence now descriptor2). We will need to implement that functionality.
    In particular, optionSearch and hence addDescriptorSubDetail are not currently reachable.  
    
    INPUT $_REQUEST['descriptor2Id']: primary key to DB table Descriptor2.
    MUST HAVE EXACTLY ONE OF THESE ABOVE TWO INPUTS NON-ZERO.
    INPUT $_REQUEST['name']: We also typically pass in $_REQUEST['name'], which is used 
        only to create a header. 

    >>>00001 JM note to Cristi, which can be removed once input validation & DB returns are properly dealt with: 
    I replaced descriptorSubId with descriptor2Id, and did some cleanup that entailed, but there is doubtless more to do here.        
*/

include '../../inc/config.php';

$descriptor2Id = isset($_REQUEST['descriptor2Id']) ? intval($_REQUEST['descriptor2Id']) : 0;
$name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';

$error = '';

if (!$descriptor2Id) {
    $error = '_admin/descriptor/detail.php must have descriptor2Id input';
}
?>

<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php
if ($error) {
    echo "<p>$error</p>\n";
    echo "</body></html>\n";
    die();
}
?>

<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<script>

// GLOBAL $descriptor2Id is implicitly an input
// Fill in currentDetails:
//  First clear it (oddly, not using the AJAX icon as a temporary fill), 
//  then pass descriptor2Id to /ajax/getdescriptorsubdetails.php (called 
//  synchronously with POST method). Alert on AJAX failure; do nothing
//  on other failure (>>>00002 should alert, and any failure should log.)
//  On success, fill in currentDetails as a table, see code for details 
//  (in the other sense of "details"!)
var getDescriptorSubDetails = function() {
    var resultcell = document.getElementById('currentDetails');
    resultcell.innerHTML = '';

    $.ajax({
        url: '/ajax/getdescriptorsubdetails.php',
        data:{
            descriptor2Id: <?php echo intval($descriptor2Id); ?>
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'error') {
                alert(data['error']); 
            } else if (data['status'] == 'success') {
                var html = '';                
                var resultcell = document.getElementById('currentDetails');
                var details = data['details'];
                html += '<table border="0" cellpadding="0" cellspacing="0">';
                    // For each detail
                    for (i = 0; i < details.length; i++) {
                        html += '<tr>';
                            // detail name + [del]; the latter links to deleteDescriptorSubDetail to remove this detail.
                            html += '<td colspan="2">' + details[i]['fullname'] + '&nbsp;[<a href="javascript:deleteDescriptorSubDetail(' + 
                                    escape(details[i]['detailRevisionId']) + ')">del</a>]</td>';
                        html += '</tr>';
                        
                        html += '<tr>';
                            // Displays PNG thumbnail, links to download PDF
                            html += '<td><a href="' + details[i]['pdfurl'] + '"><img src="' + details[i]['pngurl'] + '"></a></td>';
                            
                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                            //html += '<td>' + details[i]['detailRevisionId'] + '&nbsp;[<a href="javascript:deleteWorkOrderDetail(' + escape(details[i]['detailRevisionId']) + ')">del</a>]</td>';
                            // END COMMENTED OUT BY MARTIN BEFORE 2019
                            html += '<td>';
                                // Displays 'code' from detail, e.g. "KKDWX", links to code in details subsystem (!) to manage
                                // this detail.
                                html += '<a target="_blank" href=<?php echo DETAIL_ROOT; ?>/manage/index.php?detailRevisionId=' + details[i]['detailRevisionId'] + '">' + 
                                         details[i]['code'] + '</a>';
    
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                //	html += '<a href="' + details[i]['pdfurl'] + '">' + details[i]['code'] + '</a>';
                                // END COMMENTED OUT BY MARTIN BEFORE 2019
                                html += '<br>';
    
                                // Displays 'statusDisplay' from detail in parentheses
                                // JM: I believe this is always either '(approved)' or '(NOT APPROVED)'. 
                                html += '(' + details[i]['statusDisplay'] + ')';
                                // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019                        
                                //	if (details[i]['approved'] == 1){
                                //		html += '(approved)';
                                //	} else {
                                //		html += '(NOT APPROVED)';
                                //	}
                                // END COMMENTED OUT BY MARTIN BEFORE 2019
                            html += '</td>';                        
                        html += '</tr>';
                    }
                html += '</table>';
                resultcell.innerHTML = html;                
            }
            // Martin comment: reload details here
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });
    // Fall through to normal display
} // END function getDescriptorSubDetails

// Delete a detail from this descriptor2
// INPUT detailRevisionId identifies detail to delete
// GLOBAL $descriptor2Id is implicitly an input
//
// Pass $descriptor2Id & detailRevisionId to /ajax/deletedescriptorsubdetail.php (called 
//  synchronously with POST method). Alert on AJAX failure; doesn't check for
//  any other failure (>>>00002 should alert, and any failure should log.)
// On success, calls getDescriptorSubDetails() to reload details.
var deleteDescriptorSubDetail = function(detailRevisionId) {
    $.ajax({
        url: '/ajax/deletedescriptorsubdetail.php',
        data:{
            descriptor2Id: <?php echo intval($descriptor2Id); ?>,
            detailRevisionId: detailRevisionId   
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'error') {
                alert(data['error']); 
            }
            getDescriptorSubDetails();            
            // Martin comment: reload details here
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });    
    // Fall through to normal display
} // END function deleteDescriptorSubDetail

// Add a detail to this descriptor2
// >>>00026: unreachable as of 2019-05, because only code generated by optionSearch
//  can call this, and optionSearch is currently useless.
// INPUT detailRevisionId identifies detail to add
// GLOBAL $descriptor2Id is implicitly an input
//
// Pass $descriptor2Id & detailRevisionId to /ajax/adddescriptorsubdetail.php (called 
//  synchronously with POST method). Alert on AJAX failure; doesn't check for
//  any other failure (>>>00002 should alert, and any failure should log.)
// On success, calls getDescriptorSubDetails() to reload details.
var addDescriptorSubDetail = function(detailRevisionId) {
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //var resultcell = document.getElementById('results');
    //resultcell.innerHTML = '';
    // END COMMENTED OUT BY MARTIN BEFORE 2019
    
    $.ajax({
        url: '/ajax/adddescriptorsubdetail.php',
        data:{
            descriptor2Id: <?php echo intval($descriptor2Id); ?>,
            detailRevisionId: detailRevisionId   
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            // >>>00002 will probably want to think about how best to handle this (AFTER release 2020-2); alert is probably not the best choice.
            if (data['status'] == 'fail') {
                alert(data['error']); 
            }
            getDescriptorSubDetails(); // reload details here
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error');
        }
    });    
    // Fall through to normal display
} // END function addDescriptorSubDetail

// Find a detail matching certain criteria. Similar to detail searches elsewhere.
// IMPLICIT INPUTS for material, component, func, force are from HTML elements 
//  that are currently (2019-05) commented out, so at present >>>00026 this will not work.
// 
// Fill in results cell: currently (2019-05) commented out, so at present >>>00026 this will not work.
//  First clear it (oddly, not using the AJAX icon as a temporary fill), 
//  then pass material, component, func, force to /ajax/getdetailsearch.php (called 
//  synchronously with POST method). Alert on AJAX failure or
//  other failure (>>>00002 failure should log.)
//  On success, fill in results as a table, see code for details 
//  (in the other sense of "details"!)
var optionSearch = function() {
    var material = $('#material');
    var component = $('#component');
    var func = $('#function');
    var force = $('#force');    

    var materialValue = material.find(":selected").val();
    var componentValue = component.find(":selected").val();
    var functionValue = func.find(":selected").val();
    var forceValue = force.find(":selected").val();
    
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //alert(materialValue);
    //alert(componentValue);
    //alert(functionValue);
    //alert(forceValue);
    // END COMMENTED OUT BY MARTIN BEFORE 2019

    var resultcell = document.getElementById('results');
    resultcell.innerHTML = '';
    
    $.ajax({
        url: '/ajax/getdetailsearch.php',
        data:{
            material: materialValue,    
            component: componentValue,    
            func: functionValue,    
            force: forceValue    
        },
        async: false,
        type: 'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status'] == 'success') {
                /*
                // BEGIN MARTIN COMMENT

                 [5] => Array
                (
                    [matchcount] => 1
                    [detailRevisionId] => 387
                    [name] => A
                    [dateBegin] => 2017-02-21
                    [dateEnd] => 2500-01-01
                    [code] => KKDWX
                    [caption] => 
                    [classifications] => Array
                        (
                            [0] => Array
                                (
                                    [detailRevisionTypeItemId] => 3
                                    [detailRevisionId] => 387
                                    [detailMaterialId] => 2
                                    [detailComponentId] => 1
                                    [detailFunctionId] => 1
                                    [detailForceId] => 2
                                    [detailMaterialName] => Wood
                                    [detailComponentName] => Framing
                                    [detailFunctionName] => Lateral
                                    [detailForceName] => In Plane
                                )
                        )
                )
                // END MARTIN COMMENT
                */
                var html = '';                
                var resultcell = document.getElementById('results');
                var results = data['data']['searchresults'];
                html  += '<table border="0" cellpadding="2" cellspacing="1">';
                    // one row per detail returned
                    for (i = 0; i < results.length; i++) {
                        html += '<tr>';
                            // Detail name, followed by [add]; the latter is a link to addDescriptorSubDetail for this detail
                            html += '<td colspan="5" bgcolor="#cccccc">' + results[i]['fullname'] + '&nbsp;[<a href="javascript:addDescriptorSubDetail(' + 
                                    escape(results[i]['detailRevisionId']) + ')">add</a>]</td>';
                        html += '</tr>';
    
                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                        //	html += '<tr>';
                        // [JM: NOTE hard-coding in next line: if this ever comes back to life, use values from config.inc.]
                        //		html += '<td colspan="5"><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';
                        //	html += '</tr>';
                        // END COMMENTED OUT BY MARTIN BEFORE 2019
                        
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
                                    // First column, no header; for the first row only, spanning all the classifications for this
                                    //  detail, display PNG thumb and link to download PDF.
                                    if (j == 0) {
                                        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
                                        // [JM: NOTE hard-coding in next line: if this ever comes back to life, use values from config.inc.]
                                        // html += '<td rowspan="' + (classifications.length + 1) + '" ><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';
                                        // END COMMENTED OUT BY MARTIN BEFORE 2019
    
                                        html += '<td rowspan="' + (classifications.length) + '" ><a href="' + results[i]['pdfurl'] + '">' + 
                                                '<img src="' + results[i]['pngurl'] + '"></a></td>';
                                    }
                                
                                    //	html += '<td>&nbsp;</td>'; // COMMENTED OUT BY MARTIN BEFORE 2019
                                    html += '<td>' + classifications[j]['detailMaterialName'] + '</td>';
                                    html += '<td>' + classifications[j]['detailComponentName'] + '</td>';
                                    html += '<td>' + classifications[j]['detailFunctionName'] + '</td>';
                                    html += '<td>' + classifications[j]['detailForceName'] + '</td>';
    
                                html += '</tr>';
                            }
                        }                    
                        //html += '<p>' + results[i]['name'] + ' : ' + results[i]['code'] + '<p>'; // COMMENTED OUT BY MARTIN BEFORE 2019
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
    // Fall through to normal display
} // END function optionSearch

</script>

<?php

// RESTful call to Details API appears to get back all possibilities for
//  material, component, function, force BUT >>>00032 as of 2019-05, any code
//  that uses these is commented out. Either we need to revive this funcitonality,
//  or we should get rid of this code as irrelevant.
$params = array();
$params['act'] = 'searchoptions';
$params['time'] = time();
$params['keyId'] = DETAILS_HASH_KEYID;

$url = DETAIL_API . '?' . signRequest($params,DETAILS_HASH_KEY);
$options = @file_get_contents($url, false);

$array = json_decode($options, 1);

if (is_array($array)) { // test added 2020-09-21 JM
    $options = $array['data']['options'];
    $materials = $options['material'];
    $components = $options['component'];
    $functions = $options['function'];
    $forces = $options['force'];
} else {
    // BEGIN ADDED 2020-09-21 JM
    $logger->error2('1600723052', "Didn't get an array back from Detail API");
    // END ADDED 2020-09-21 JM
}

// Get name from input just to output it as a heading.
echo '<h2>&nbsp;&nbsp;' . $name . '</h2>';    
/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
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

<?php
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/ ?>

<?php /* Table, which we will fill in with current details. */ ?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td nowrap>Current Details</td>
        <?php /* 
            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        ?>
        
            <td nowrap>Search Results</td>
        <?php
            // END COMMENTED OUT BY MARTIN BEFORE 2019
        */ ?>    
    </tr>
    
    <tr>
        <?php /* will be filled in by calling getDescriptorSubDetails */ ?> 
        <td width="35%" id="currentDetails" valign="top" bgcolor="#dddddd">&nbsp;</td>

        <?php
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        /* ?>
            <td width="65%" id="results">&nbsp;</td>
        <?php
        // END COMMENTED OUT BY MARTIN BEFORE 2019
        */ ?>
    </tr>
</table>
<script>
    getDescriptorSubDetails();
</script>
        
</body>
</html>
