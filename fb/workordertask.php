<?php 
/*  fb/workordertask.php

    EXECUTIVE SUMMARY: View and edit a workOrderTask.

    PRIMARY INPUT: $_REQUEST['workorderTaskId'].
    
    Optional $_REQUEST['act'] can have values:
        'addnote', which uses $_REQUEST['noteText'].
        'updateextra', which uses $_REQUEST['extraDescription'].
        'update', which uses $_REQUEST['personIds']. 
*/    

include '../inc/config.php';
include '../inc/access.php';
$db = DB::getInstance();

$workOrderTaskId = isset($_REQUEST['workOrderTaskId']) ? intval($_REQUEST['workOrderTaskId']) : 0;
$workOrderTask = new WorkOrderTask($workOrderTaskId, $user);

if (!intval($workOrderTask->getWorkOrderTaskId())) {
    die();
}
if ($act == 'addnote') {
    // add the desired note to DB table Note for this workOrderTask
    // NOTE that the relevant method is in inc/classes/SSSEng.class.php
    $noteText = isset($_REQUEST['noteText']) ? $_REQUEST['noteText'] : '';
    $workOrderTask->addNote($noteText);
}

if ($act == 'updateextra') {
    // update extraDescription column for the workOrderTask; this is just text. 
    $extraDescription = isset($_REQUEST['extraDescription']) ? $_REQUEST['extraDescription'] : '';    
    $workOrderTask->update(array('extraDescription' => $extraDescription));    
}



if ($act == 'update') {
    // Associate one or more employees to this WorkOrderTask via DB table workOrderTaskPerson.
    // Despite the method name addPersonIds, this also removes any prior such associations
    //  of employees to this workOrderTask. If you want to preserve an existing relation,
    //  it must be passed in $_REQUEST['personIds']; $_REQUEST['personIds'] can be passed
    //  empty to remove all.
    
    // pass the personIds array to $workOrderTask->addPersonIds() and close the fancybox.
    $personIds = isset($_REQUEST['personIds']) ? $_REQUEST['personIds'] : array();    
    if (!is_array($personIds)) {
        $personIds = array();
    }
    $personIds = array_unique($personIds);
 
    
    // Despite its name, the method addPersonIds() is really more like "setPersonIds" in that 
    //  it will also remove any personIds that are not in the passed array.
    $workOrderTask->addPersonIds($personIds);

    $elementIds = array(); // >>>00014 JM: why clear this? I don't see any other action we take before we die. Bizarre
                           // >>>00007 JM: Unless I'm misreading, this can simply be removed.
  
    
    ?>
    <script>
        parent.$.fancybox.close();
    </script>
    <?php
    die();

}

// $persons will be an array of associative arrays, with each item in the top-level 
// array corresponding to a person associated with this WorkOrderTask. 
// Each associative array unions every column of DB table WorkOrderTaskPerson with  
// 'legacyInitials' from DB table customerPerson. 
$persons = $workOrderTask->getWorkOrderTaskPersons();

//$employees = getEmployees($customer); // COMMENTED OUT BY MARTIN BEFORE 2019

$employees = $customer->getEmployees();
$employeesCurrent = $customer->getEmployees(EMPLOYEEFILTER_CURRENTLYEMPLOYED);
$workOrder = new WorkOrder($workOrderTask->getWorkOrderId());

// BEGIN MARTIN COMMENT
// jobelements are all elements on job
// elements are which ones may or may not be assigned to a workordertask
// END MARTIN COMMENT

$elements = $workOrderTask->getWorkOrderTaskElements();    
$elementIds = array();    
foreach ($elements as $element) {    
    $elementIds[$element->getElementId()] = $element;
}    

$job = new Job($workOrder->getJobId());    
$jobElements = $job->getElements();

include '../includes/header_fb.php';


?>

<script type="text/javascript">
    <?php /*  On completion, navigate to the page for the new workOrderTask. We do this by setting parent.woturl.
            >>>00012 That is an oddly chosen name, as it has nothing to do with a workOrderTask (though in this case it happens
            to be one). It is handled in includes/header.php:
            the result is to navigate to the page that is set this way, after a fancybox is closed. */ ?>
    parent.woturl = '<?php echo $workOrder->buildLink() . '/' . intval($workOrderTaskId); ?>'; 
    
    // addPersonSelect is used when adding (or, despite the function name, removing)
    // a person associated with the workOrderTask via DB table workOrderTaskPerson
    var addPersonSelect = function() {
        var len = document.getElementById("personTable").rows.length;    
        var table = document.getElementById("personTable");
        var row = table.insertRow(-1);
        var cell1 = row.insertCell(0);
        var cell2 = row.insertCell(1);

        cell1.innerHTML = '<select class="form-control form-control-sm" id="personIds_' + len + '" name="personIds[]" ></select>';    
  
   
        $('#personIds_' + (len - 1) + ' option').clone().appendTo('#personIds_' + len);   
        $('#personIds_' + len).prop('selectedIndex',0);   

        for (var i = 0, row; row = table.rows[i]; i++) {
            row.cells[1].innerHTML = '';                             
        }    
        
        cell2.innerHTML = '<span style="text-decoration:none;font-size:130%;"><a style="text-decoration:none;" href="javascript:addPersonSelect()">+</a></span>';    
    } // END function addPersonSelect



    /* INPUT elementId                                              
        Temporarily replace the cell content with the ajax_loader.gif. 
        Call /ajax/workordertaskelement.php, passing it the elementId, workOrderTaskId, 
         and a Boolean for whether the box is now checked or unchecked. 
         On success, we recreate the cell. 
         On failure/error, we alert (JavaScript built-in alert function) accordingly.
    */
    var elementClick = function(elementId) {    
        var cell = document.getElementById('cell_' + elementId);
        var cb = document.getElementById('cb_' + elementId);
    
        cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';
    
        var  formData = "elementId=" + escape(elementId) + "&state=" + escape(cb.checked) + 
                        "&workOrderTaskId=" + escape(<?php echo $workOrderTask->getWorkOrderTaskId(); ?>);  
        
        $.ajax({
            url: '/ajax/workordertaskelement.php',
            data: formData,
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {    
                if (data['status']) {
                    if (data['status'] == 'success') {  // [T000016] 
                        var checked = '';                        
                        if (data['row']) {    
                            checked = 'checked';    
                        }    
                        cell.innerHTML = '<input id="cb_' + escape(elementId) + '" onClick="elementClick(' + escape(elementId) + ')" type="checkbox" ' + checked + '>';
                    } else {    
                        alert('error not success');    
                    }    
                } else {
                    alert('error no status');
                }                
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });
    } // END function elementClick 
</script>

<style>
    .xtable {
        border-collapse: collapse;
    }
    
    .xtable, .xtable th, .xtable td {
        padding:10px;   
    }
    
    .xtable td {
        
        padding:10px;        
    }
    
    .ytable {
        border-collapse: collapse;
    }
    
    .ytable, .ytable th, .ytable td {
        border: 0px solid black;
    }
    
    .ytable td {
        padding:2px;        
    }
  
    #editTooltip, #hideTooltip, .btn-outline-success {
        display:none;
    }
    #go {
        margin-bottom:100px;
    }
    .fancybox-skin{
        width:500px!important;
    
    }
    .fancybox-inner {
        height:400px!important;
    }
    body {background:#fff;}
    .tg-yw4l {
        display:none;
    }
    #removeCurrentText {
        color:red;
        font-weight: 600;
    }
</style>

<?php
    /* A table
        * heading consisting of workOrder name & workOrderTask description
        * subhead "Assigned Elements:"
        * unnamed self-submitting form to add or remove an element of the job from this workOrder
        * iframe to allow editing this workOrder
    */
    echo '<center>';
    echo '<table border="0" cellpadding="0" cellspacing="0">';
        echo '<tr>';
            echo '<td colspan="2">';
                echo '<h3>' . $workOrder->getName() . '&nbsp;/</br>&nbsp;' . $workOrderTask->getTask()->getDescription() . '</h3>';
            echo '</td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';
    
        echo '<tr>';
            echo '<td colspan="2" style="text-align:center" width="100%">';
                echo '<table class="xtable" width="100%">';
                    echo '<tr>';
                        echo '<td style="display:none" class="tg-yw4l">'; // >>>00014: here and below, no idea what class="tg-yw4l"is about.
                            echo '<h3>Assigned Elements:</h3>';            
                            /* unnamed self-submitting form, but we don't really use it as a form.
                                hidden: workOrderTaskId (but seems irrelevant, see discussion of changing a checkbox inside form)
                                hidden: act='update' (but seems irrelevant, see discussion of changing a checkbox inside form)
                                Further notes are inside form code.
                            */
                            echo '<form name="" id="assigElements" method="post" action="">';
                                echo '<input type="hidden" name="workOrderTaskId" value="' . intval($workOrderTask->getWorkOrderTaskId()) . '">';
                                echo '<input type="hidden" name="act" value="update">';                                    
                                echo '<table class="ytable" border="0" cellpadding="0" cellspacing="0">';
                                /* For each element of the job... */  
                                    foreach ($jobElements as $jobElement) {
                                        echo '<tr>';                                        
                                            /*... we put a TD cell with HTML id "cell_EE" where EE is elementId, and within that a 
                                              checkbox with id "cb_EE". If the element is associated with this workOrderTask, then 
                                              the box is checked. 
                                              If the user clicks on the checkbox, we call elementClick(elementId), which makes the relevant
                                              changes in the database.
                                              NOTE that this means any check/uncheck immediately affects the database and does not wait for 
                                              the form to be submitted. Indeed, there does not appear to be a submit button, 
                                              so presumably the hidden 'act=update' for this form is irrelevant. */
                                            $checked = (array_key_exists($jobElement->getElementId(), $elementIds)) ? ' checked ' : '';                                        
                                            echo '<td id="cell_' . $jobElement->getElementId() . '"><input id="cb_' . $jobElement->getElementId() . 
                                                 '" onClick="elementClick(' . intval($jobElement->getElementId()) . ')" type="checkbox" ' . $checked . '></td><td>' . 
                                                 $jobElement->getElementName() . '</td>';
                                        echo '</tr>';                                        
                                    }                                        
                                    echo '</table>';
                                echo '<span style="font-size:80%">(Click boxes as needed. No need to press an update button)</span>';                        
                            echo '</form>';
                        echo '</td>';
                        echo '<td class="tg-yw4l" rowspan="3">';
                            echo '<h2>Notes</h2>';
                            
                            // Another form, this time really used as a self-submitting form.
                            // In IFRAME, display "recent" notes (which I believe in practice means all notes - JM 2019-05-03)
                            //  for this workOrderTask      
                            // Textarea + submit button labeled "add note" allows adding a new note.
                            ?>
                            <iframe width="100%" src="/iframe/recentnotes.php?workOrderTaskId=<?php echo $workOrderTask->getWorkOrderTaskId(); ?>"></iframe>                    
                            <?php
                            echo '<form name="note" id="note" action="" method="post">';
                                echo '<input type="hidden" name="act" value="addnote">';
                                echo '<input type="hidden" name="workOrderTaskId" value="' . $workOrderTask->getWorkOrderTaskId() . '">';
                                echo '<textarea name="noteText" id="noteText" cols="45" rows="5"></textarea><br><input type="submit" id="addNote" value="add note">';
                            echo '</form>';
                        echo '</td>';
                    echo '</tr>';
                    echo '<tr>';
                        // Another self-submitting form, this time to edit the "extraDescription" column in DB table WorkOrderTask
                        echo '<td class="tg-yw4l">';
                            echo '<h3>Extra Description:</h3>';            
                            echo '<form name="extra" id="extraForm" method="post" action="">';
                                echo '<input type="hidden" name="workOrderTaskId" value="' . intval($workOrderTask->getWorkOrderTaskId()) . '">';
                                echo '<input type="hidden" name="act" value="updateextra">';
                                echo '<textarea name="extraDescription" id="extraDescription" rows="3" cols="35">' . htmlspecialchars($workOrderTask->getExtraDescription()) . '</textarea>';
                                echo '<br>';
                                echo '<input type="submit" id="updateExtra" value="update extra" border="0">';
                            echo '</form>';
                            echo '<span style="font-size:80%">(Edit description, then press "update extra")</span>';
                        echo '</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<td rowspan="2" >';
                            // Another self-submitting form, this time to associate one or more employees to this WorkOrderTask
                            // via DB table workOrderTaskPerson.
                            echo '<form name="" id="addWoEmployees" method="post" action="">';
                                echo '<input type="hidden" name="workOrderTaskId" value="' . intval($workOrderTask->getWorkOrderTaskId()) . '">';
                                echo '<input type="hidden" name="act" value="update">';
                                if (count($persons)) {
                                echo '<h5> Remove/ Replace : </h5>';
                                echo '<span style="font-size:90%;padding-bottom:5px;font-weight: 600;" class="mt-1">*To Remove a person, select  " Remove current " </span></br>';
                                echo '<span style="font-size:90%;padding-bottom:5px;font-weight: 600;">*To Replace a person, select another name from the list</span></br>';
                                } else {
                                    echo '<h5 class="mt-4">Assign to:</h5>';
                                    echo '<span style="font-size:90%;padding-bottom:5px;font-weight: 600;">*To Assign a person select  " Assign to "</span>';
                                }
                              
                                echo '<table class="ytable mt-3" name="personTable" id="personTable">';
                                    if (!count($persons)) {
                                        // No one yet assigned.
                                        // We display a single HTML SELECT, id="personIds_0" name="personIds[]" 
                                        //  (that form of name means HTML builds an array). 
                                        //  First option is "-- Assigned To --" with blank value. 
                                        //  Then for each current employee, display legacy initials & employee name; value is userId. 
                                        echo '<tr>';
                                            echo '<td>';
                                                echo '<select class="form-control form-control-sm"  id="personIds_0" name="personIds[]">';
                                                    echo '<option class="form-control form-control-sm"  value="">-- Assigned To --</option>';
                                                    foreach ($employeesCurrent as $employee) {
                                                        echo '<option class="form-control form-control-sm"  value="' . intval($employee->getUserId()) . '">[' . 
                                                        $employee->legacyInitials . '] ' . $employee->getFirstName() . ' ' . 
                                                        $employee->getLastName() . '</option>';
                                                    }
                                                echo '</select>';                                
                                            echo '</td>';
                                            
                                            echo '<td><span style="text-decoration:none;font-size:130%;"><a style="text-decoration:none;" id="addSelectedEmployees" href="javascript:addPersonSelect()">+</a></span></td>';
                                        echo '</tr>';                                
                                    } else {
                                        // There are some people already assigned.
                                        // We may have multiple HTML SELECTs, similar to the above, with id="personIds_0", id="personIds_1", etc. 
                                        //  The options are generally as above, but a person who is not a current employee will also show up in 
                                        //  the options for the relevant HTML SELECT if they are currently assigned the workOrderTask. 
                                        //  Also, in each SELECT, the option for the current person will be preselected.
                                        //  (More below about what comes after the last such SELECT.)
                                        $adds = array();                                
                                        foreach ($persons as $person) {                                
                                            $inCurrent = false;                                
                                            foreach ($employeesCurrent as $employee) {
                                                if ($person->getPersonId() == $employee->getUserId()) {
                                                    $inCurrent = true;
                                                }                                
                                            }
                                    
                                            if (!$inCurrent) {
                                                foreach ($employees as $employee) {
                                                    if ($person->getPersonId() == $employee->getUserId()) {
                                                        $adds[] = $employee;
                                                    }                                                    
                                                }
                                                //$adds[] = $person; // COMMENTED OUT BY MARTIN BEFORE 2019                                
                                            }
                                        }
                                    
                                        foreach ($adds as $add) {                                
                                            foreach ($employees as $employee) {                                
                                                if ($add->getUserId() == $employee->getUserId()) {                                
                                                    $employeesCurrent[] = $add;
                                                }                                
                                            }                                
                                        }
                                   
                                        foreach ($persons as $pkey => $person) {  
                                            
                                            echo '<tr>';
                                                echo '<td>';
                                                    echo '<select class="form-control form-control-sm" id="personIds_' . $pkey . '" name="personIds[]">';
                                                        if($pkey == (count($persons) - 0) ) {
                                                            echo '<option class="form-control form-control-sm"  value="">-- Assign to --</option>';
                                                        } else {
                      
                                                            echo '<option class="form-control form-control-sm"  value="" id="removeCurrentText">-- Remove current --</option>';
                                                        }
                                                        
                                                        foreach ($employeesCurrent as $employee) {
                                                            $selected = ($employee->getUserId() == $person->getPersonId()) ? ' selected ' : '';
                                                            echo '<option class="form-control form-control-sm"  value="' . intval($employee->getUserId()) . '" ' . $selected . '>[' . $employee->legacyInitials . '] ' . $employee->getFirstName() . ' ' . $employee->getLastName() . '</option>';
                                                        }
                                                    echo '</select>';
                                                echo '</td>';
                                                if ($pkey == (count($persons) - 0)) {
                                                    // After the last such SELECT, a row with a cell containing an HTML link marked "+", which if clicked 
                                                    //  calls addPersonselect(). Basically, if clicked this replicates the immediate prior HTML SELECT, 
                                                    //  using the next integer for id="personIds_nn", with nothing selected. 
                                                    //  (Note that if that last assignment was to a person who is no longer an employee, 
                                                    //   that person will inappropriately show up in the options.) It then replicates itself.
                                                    echo '<td><span style="text-decoration:none;font-size:130%;"><a id="addPersonSelect" style="text-decoration:none;"' .
                                                         ' href="javascript:addPersonSelect()">+</a></span></td>';
                                                } 
                                                
                                                else {
                                                    //echo '<td><span class="ui-icon ui-icon-info" title="To Remove a person select " Remove current " style="float: right;"></span></td>';
                                                    echo '<td></td>';
                                                } 
                                               
                                            echo '</tr>';
                                        } 
                                        echo '<tr>';
                                            echo '<td>';
                                            echo '<h5 class="mt-4">Assign to:</h5>';
                                            echo '<span style="font-size:90%;padding-bottom:5px;font-weight: 600;">*To Assign a person select  " Assign to "</span>';
                                            echo '</td>';
                                        echo '</tr>';
                                        echo '<tr>';
                                            echo '<td>';
                                                echo '<select class="form-control form-control-sm"  id="personIds_'.(count($persons) + 1).'" name="personIds[]">';
                                                    echo '<option class="form-control form-control-sm"  value="">-- Assigned To --</option>';
                                                    foreach ($employeesCurrent as $employee) {
                                                        echo '<option class="form-control form-control-sm"  value="' . intval($employee->getUserId()) . '">[' . 
                                                        $employee->legacyInitials . '] ' . $employee->getFirstName() . ' ' . 
                                                        $employee->getLastName() . '</option>';
                                                    }
                                                echo '</select>';                                
                                            echo '</td>';
                                            
                                            echo '<td><span style="text-decoration:none;font-size:130%;"><a style="text-decoration:none;" id="addSelectedEmployees" href="javascript:addPersonSelect()">+</a></span></td>';
                                    echo '</tr>';                                 
                                    }
                                echo '</table>';
                                echo '<input type="submit" class="btn btn-secondary btn-sm mt-3" id="updateAssignments" value="update assignments" border="0">';
                            echo '</form>';
                            echo '<span style="font-size:80%">(edit assignments then press "update assignments")</span>';
                        echo '</td>';
                    echo '</tr>';
                    echo '<tr class="tg-yw4l">';
                        /* first column already covered by a rowspan, this in second column */
                        echo '<td>';
                        $insertedDate = $workOrderTask->getInserted();
                        $insertedById = $workOrderTask->getInsertedPersonId();
                        if ($insertedDate || $$insertedById) {
                            echo '(workOrderTask created';
                            if ($insertedDate) {
                                echo ' ' . $workOrderTask->getInserted();
                            }
                            if ($insertedById) {
                                $insertedBy = new Person($insertedById);
                                echo ' by ' . $insertedBy->getFormattedName(1) . ' personId=' . $insertedBy->getPersonId();
                            }
                            echo ')';
                        }
                        echo '</td>';
                    echo '</tr>';
                echo '</table>';
            echo '</td>';
        echo '</tr>';
    
        echo '<tr>';
            echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';
    echo '</table>';
    echo '</center>';
    
    include '../includes/footer_fb.php';

?>