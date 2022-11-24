<?php
/*  detaildata.php

    EXECUTIVE SUMMARY: information about a detail (i.e. detail drawing) from the Details API.
    This uses a technique a bit different than most of the code: it initially creates an empty table, then makes an AJAX call to fill it in.

    PRIMARY INPUT: $_REQUEST['detailRevisionId'].
*/

include '../inc/config.php';
include '../inc/perms.php';

$detailRevisionId = isset($_REQUEST['detailRevisionId']) ? intval($_REQUEST['detailRevisionId']) : 0;

include '../includes/header_fb.php';

// Martin comment: detailRevisionId for test= 149
?>

<script>
    var getData = function(detailRevisionId) {
        <?php /*
            Makes a synchronous POST to /ajax/getdetailrevisiondata.php, passing detailRevisionId. 
            No deep reason this is synchronous: its the first thing after document.ready, really wouldn't 
            matter if we made it async: everything is after its return, and we don't even raise an alert 
            on AJAX error, nor do we check the return for status='success'. */
        ?>
        $.ajax({
            url: '/ajax/getdetailrevisiondata.php',
            data:{
                detailRevisionId:detailRevisionId
            },
            async:false,
            type:'post',
            success: function(data, textStatus, jqXHR) {
                <?php /* Instead of checking status, we just check for the presence in the return of detailRevisionData and use that as a proxy for success. */ ?>
                if (data['detailRevisionData']) {
                    <?php /* The rest of this is basically a report of that returned data. 
                        The format of the detailRevisionData returned by /ajax/getdetailrevisiondata.php is not fully documented as of 2019-04, 
                        because the Details API is not fully documented, but most of what is referenced here should be comprehensible. 
                        See the documentation of /ajax/getdetailrevisiondata.php. */ 
                    ?>
                    <?php /* if there is a data['detailRevisionData']['fullname'], we write that value as an H1 header in the 'fullname' cell. */ ?> 
                    if (data['detailRevisionData']['fullName']) {
                        var fn = document.getElementById('fullName');
                        if (fn) {
                            fn.innerHTML = '<h1>' + data['detailRevisionData']['fullName'] + '</h1>';
                        }
                    }
                    
                    <?php /* if there is a data['detailRevsionData']['pdfurl'] (the form of the Detail PDF URL for embedding) 
                             we replace the inner HTML of the "objectCell" cell with an HTML OBJECT element embedding the 
                             PDF data['detailRevisionData']['pdfurl']). Inside the object element we put text that will be 
                             visible only if the object cannot be displayed, saying "Can't view so download", where "download" 
                             is a link to data['detailRevisionData']['pdfurl']). */ 
                    ?> 
                    var html = '';                
                    if (data['detailRevisionData']['pdfurl']) {                
                        var oc = document.getElementById("objectCell");
                        html += '<object data="' + data['detailRevisionData']['pdfurl'] + '" type="application/pdf" width="100%" height="100%">';
                        html += '<p>Can\'t view so <a href="' + data['detailRevisionData']['pdfurl'] + '">download</a></p>';
                        html += '</object>';
                        
                        oc.innerHTML = html;
                    }
                    <?php /* if there is a data['detailRevisionData']['typeitems'], we write a subtable in the "typeItems" cell with the following
                             columns, no headers, one row per element of array data['detailRevisionData']['typeitems']:
                                * material
                                * component
                                * function
                                * force 
                           */
                    ?>            
                    html = '';                
                    if (data['detailRevisionData']['typeItems']) {
                        html += '<table border="1">';                    
                            for (i = 0; i < data['detailRevisionData']['typeItems'].length; i++) {
                                html += '<tr>';
                                    html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailMaterialName'] + '</td>';
                                    html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailComponentName'] + '</td>';
                                    html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailFunctionName'] + '</td>';
                                    html += '<td>' + data['detailRevisionData']['typeItems'][i]['detailForceName'] + '</td>';                                                
                                html += '</tr>';
                            }
                        html += '</table>';
    
                        var ti = document.getElementById('typeItems');
                        ti.innerHTML = html;                    
                    }
    
                    <?php /* if there is a data['detailRevisionData']['revisions'], we write a subtable in the "revisions" cell with 
                             the following columns, no headers, one row per element of array data['detailRevisionData']['revisions']:
                                * 'status' - >>>00001 this and the following could use more explanation
                                * 'code'
                                * 'caption'
                                * 'createReason' 
                           */
                    ?>            
                    html = '';
                    if (data['detailRevisionData']['revisions']) {
                        html += '<table border="1">';                    
                            for (i = 0; i < data['detailRevisionData']['revisions'].length; i++) {
                                html += '<tr>';
                                    html += '<td>' + data['detailRevisionData']['revisions'][i]['status'] + '</td>';
                                    html += '<td>' + data['detailRevisionData']['revisions'][i]['code'] + '</td>';
                                    html += '<td>' + data['detailRevisionData']['revisions'][i]['caption'] + '</td>';
                                    html += '<td>' + data['detailRevisionData']['revisions'][i]['createReason'] + '</td>';
                                html += '</tr>';
                            }
                        html += '</table>';
    
                        var r = document.getElementById('revisions');
                        r.innerHTML = html;                    
                    }
                    
                    <?php /* if there is a data['detailRevisionData']['revisions'], we write a subtable in the "discussions" cell with the following:
                            * First row: header "Revision" in column 1, header "Discussion" spanning the other 3 columns
                            * Second row, headers for the four columns:
                                * code/date/reason
                                * Note
                                * Person
                                * Inserted 
                            * one row per element of array data['detailRevisionData']['revisions']:
                                * 'cdr' (code/date/reason) value; this is a single column spanning the height of the rest of table, covering all the array elements
                                * 'note': actual not contents
                                * 'initials': who inserted the note
                                * 'inserted': when the note was inserted.         
                           */
                    ?>            
                    html = '';
                    if (data['detailRevisionData']['revisions']) {
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
                        
                            for (i = 0; i < data['detailRevisionData']['revisions'].length; i++) {
                                var ds = data['detailRevisionData']['revisions']['discussions']; // >>>00007 Set, never referenced
                                var span = data['detailRevisionData']['revisions'][i]['discussions'].length;
                                if (span < 2) {
                                    span = 1;
                                }
        
                                if (data['detailRevisionData']['revisions'][i]['discussions'].length > 0) {
                                    for (j = 0; j < data['detailRevisionData']['revisions'][i]['discussions'].length; j++) {
                                            html += '<tr>';                                    
                                                if (j == 0) {                                        
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
    
                        var d = document.getElementById('discussions');
                        d.innerHTML = html;                    
                    }                
                }
                
                //getWorkOrderDetails(); // Commented out by Martin before 2019
    
            },
            error: function(jqXHR, textStatus, errorThrown) {
                //getWorkOrderDetails(); // Commented out by Martin before 2019
                //alert('error'); // Commented out by Martin before 2019
            }
        });    
    }
    
    $( document ).ready(function() {
        getData();
    });
</script>

<?php /* Table as a framework to be filled in by the AJAX above */ ?>
<table border="0" cellpadding="5" cellspacing="10" width="95%" height="100%">
    <tr>
        <?php /* Left half: subtable */ ?>
        <td valign="top" width="50%">    
            <table border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td id="fullName"><?php /* Will be filled in by AJAX */ ?></td>
                </tr>	
                <tr>
                    <td id="typeItems"><?php /* Will be filled in by AJAX */ ?></td>
                </tr>
                <tr>
                    <td id="revisions"><?php /* Will be filled in by AJAX */ ?></td>
                </tr>
                
                <tr>
                    <td id="discussions"><?php /* Will be filled in by AJAX */ ?></td>
                </tr>
            </table>        
        </td>
        <?php /* Right half: a big HTML OBJECT area to let user download the PDF */ ?>
        <td id="objectCell" valign="top" width="50%"><?php /* Will be filled in by AJAX */ ?></td>
    </tr>
</table>

<?php
    include '../includes/footer_fb.php';
?>
