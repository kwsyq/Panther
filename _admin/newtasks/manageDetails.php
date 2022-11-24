<?php 
/*  _admin/newtasks/manageDetails.php

    EXECUTIVE SUMMARY: view or edit Details for a task.

    INPUT $_REQUEST['taskId'] - primary key into DB table task
    
*/

$nested = isset($inside_admin_newtasks_edit_php) && $inside_admin_newtasks_edit_php;

if (!$nested) {
    require_once '../../inc/config.php';
    require_once 'gettaskidfromrequest.php';
    include_once '../../includes/header_admin.php';
    require_once 'rightframetabbing.php';
    
    list($taskId, $error) = getTaskIdFromRequest(__FILE__);
    createNewtasksTabs('manageDetails.php', $taskId);
} else if (!$taskId) {
    $error = "_admin/newtasks/manageDetails.php called nested without \$taskId";
    $logger->error2('1604651724', $error); 
} else { 
    $error = '';
}

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} else {
    if (!$nested) {
        echo "<h2>Edit</h2>\n";
    }
    $task = new Task($taskId);
    ?>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td><b>Current Details for [<?= htmlspecialchars($task->getDescription()) ?>][<?= intval($task->getTaskId()) ?>]</b>
        </tr>
        <tr>
            <td width="35%" id="currentDetails" valign="top" bgcolor="#dddddd">&nbsp;</td>
        </tr>
    </table>
    <script>
    /* Fill in the "currentDetails" element. Clear any prior value in this cell, then POST a synchronous call to 
         /ajax/gettaskdetails.php, passing the taskId. Any AJAX error results in an alert; a return with 
         data['status'] != 'success' fails silently (>>>00002 should alert and/or log). 
         
       On successful return, we use the content of array data['details'] to fill in the cell. 
       We build a table with two rows for each element of data['details']. 
       Each data['details'][i] is an associative array; content is drawn from that array. See comments in code for further detail.
    */  
    var getTaskDetails = function() {
        var resultcell = document.getElementById('currentDetails');
        resultcell.innerHTML = '';    
        $.ajax({
            url: '/ajax/gettaskdetails.php',
            data: {
                taskId: <?= intval($taskId) ?> 
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status'] == 'success') {
                    var html = '';
                    var resultcell = document.getElementById('currentDetails');    
                    var details = data['details'];
                    
                    html += '<table border="0" cellpadding="0" cellspacing="0">';                    
                        for (i = 0; i < details.length; i++) {
                            /* Row 1, spanning 2 columns: detail name, followed by a link labeled "[del]" that calls 
                               local function deleteTaskDetail, passing the relevant detailRevisionId. */ 
                            html += '<tr>';        
                                html += '<td colspan="2">' + details[i]['fullname'] + '&nbsp;[<a href="javascript:deleteTaskDetail(' + escape(details[i]['detailRevisionId']) + ')">del</a>]</td>';
                            html += '</tr>';

                            /* Row 2, 2 columns */
                            html += '<tr>';        
                                /* Link to download a PDF; displays a PNG thumb. */
                                html += '<td><a href="' + details[i]['pdfurl'] + '"><img src="' + details[i]['pngurl'] + '"></a></td>';
                                /* * Display detail 'code', e.g. "DRDDD", linking to open a page from the details system in a new window or tab; 
                                    it opens the page for the appropriate detailRevisionId.
                                   * BR element (linebreak)
                                   * Display status, which I (JM) believe should always be either '(approved)' or '(NOT APPROVED)' 
                                */ 
                                html += '<td>';
                                    html += '<a target="_blank" href="<?= DETAIL_ROOT ?>/manage/index.php?detailRevisionId=' + 
                                            details[i]['detailRevisionId'] + '">' + details[i]['code'] + '</a>';
                                    html += '<br>';
            
                                    html += '(' + details[i]['statusDisplay'] + ')';                                    
                                html += '</td>';                                
                            html += '</tr>';        
                        }    
                    html += '</table>';    
                    resultcell.innerHTML = html;
                } else {
                    alert('error no \'status\' in data returned from /ajax/gettaskdetails.php.\n' + 
                        'Typically this means that you are logged in as admin, but not as a user.\n' +
                        'Log in to <?= REQUEST_SCHEME . '://' . HTTP_HOST ?>/panther.php (in a different tab), then try the action here again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error in AJAX call to /ajax/gettaskdetails.php');
            }
        });    
    }; // END function getTaskDetails

    /* POST a synchronous call to /ajax/deletetaskdetail.php, passing the taskId & 
       the detail to delete from this task. 
        Any AJAX error results in an alert; a return with data['status'] != 'success' 
        fails silently (>>>00002 should alert and/or log).
        
        INPUT detailRevisionId: identifies the detail to delete from this task
    */
    var deleteTaskDetail = function(detailRevisionId) {
        $.ajax({
            url: '/ajax/deletetaskdetail.php', // This is in the main /ajax directory, not /_admin/ajax. >>>00001 JM is that wise?
            data: {
                taskId: <?= intval($taskId) ?>,  
                detailRevisionId: detailRevisionId   
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                getTaskDetails();
                // Martin comment: reload details here
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    }; // END function deleteTaskDetail

    /* POSTs a synchronous call to /ajax/addtaskdetail.php, passing the taskId 
        and the detailRevisionId.
        
        We alert on AJAX error; we don't check any status on success, we just 
        make a new call to local function getTaskDetails to reload the details.
    
        INPUT detailRevisionId: identified the detail to add to this task
    */
    var addTaskDetail = function(detailRevisionId) {
        $.ajax({
            url: '/ajax/addtaskdetail.php', // This is in the main /ajax directory, not /_admin/ajax. >>>00001 JM is that wise?
            data:{
                taskId: <?= intval($taskId) ?>,  
                detailRevisionId: detailRevisionId   
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                // >>>00002 will probably want to think about how best to handle this; alert is probably not the best choice.
                if (data['status'] == 'fail') {
                    alert(data['error']); 
                }
                getTaskDetails();                
                // Martin comment: reload details here    
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    } // END function addTaskDetail

    // >>>00007 As of 2020-11-03, there is no code to call this, so I haven't looked at it closely.
    // There is other similar code in other files. I believe it will come back to life when Radu
    //  gets Details working, so I'm leaving it in for now. - JM
    // It is possible that this now belongs in a different file.
    var optionSearch = function() {    
        var material = $('#material');
        var component = $('#component');
        var func = $('#function');
        var force = $('#force');        
    
        var materialValue = material.find(":selected").val();
        var componentValue = component.find(":selected").val();
        var functionValue = func.find(":selected").val();
        var forceValue = force.find(":selected").val();
        
        var resultcell = document.getElementById('results');
        resultcell.innerHTML = '';
        $.ajax({
            url: '/ajax/getdetailsearch.php', // This is in the main /ajax directory, not /_admin/ajax. >>>00001 JM is that wise?
            data: {
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
                    // BEGIN MARTIN COMMENT (presumably documents structure obtained from the details subsystem)
    
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
                        for (i = 0; i < results.length; i++) {    
                            html += '<tr>';    
                                html += '<td colspan="5" bgcolor="#cccccc">' + results[i]['fullname'] + '&nbsp;[<a href="javascript:addTaskDetail(' + escape(results[i]['detailRevisionId']) + ')">add</a>]</td>';
                            html += '</tr>';        
                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019; keeping this here for possible revival of details subsystem
                            //  html += '<tr>';
                            // JM: NOTE hard-coding in next line: if this ever comes back to life, use values from config.inc.
                            //  html += '<td colspan="5"><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';
                
                            // html += '</tr>';
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
                                        if (j == 0) {
                                            // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019; keeping this here for possible revival of details subsystem
                                            // JM NOTE hard-coding in next line: if this ever comes back to life, use values from config.inc.
                                            // html += '<td rowspan="' + (classifications.length + 1) + '" ><img src="http://detuser:sonics^100@detail.ssseng.com/fetchpng.php?fileId=' + results[i]['detailRevisionId'] + '"></td>';
                                            // END COMMENTED OUT BY MARTIN BEFORE 2019
        
                                            html += '<td rowspan="' + (classifications.length) + '" ><a href="' + results[i]['pdfurl'] + '"><img src="' + results[i]['pngurl'] + '"></a></td>';
                                        }
                                            
                                        // html += '<td>&nbsp;</td>'; // COMMENTED OUT BY MARTIN BEFORE 2019
                                        html += '<td>' + classifications[j]['detailMaterialName'] + '</td>';
                                        html += '<td>' + classifications[j]['detailComponentName'] + '</td>';
                                        html += '<td>' + classifications[j]['detailFunctionName'] + '</td>';
                                        html += '<td>' + classifications[j]['detailForceName'] + '</td>';    
                                    html += '</tr>';    
                                }    
                            }
                            // keeping the following line here for possible revival of details subsystem
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
    } // END function optionSearch
    getTaskDetails(<?= $taskId ?>);
    </script>
<?php    
}

if (!$nested) {
    include_once '../../includes/footer_admin.php';
}
unset($nested); 
?>