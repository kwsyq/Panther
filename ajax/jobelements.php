<?php 
/*  ajax/jobelements.php

    INPUT $_REQUEST['workOrderId']: primary key in DB table WorkOrder
    INPUT $_REQUEST['dialogName']: name of dialog we are filling in in calling file.
        Passing the dialog name added 2020-08-25 JM: let's not have the code so tightly coupled that /ajax/jobelements.php 
        needs to know the name of a DIV in the calling file.        

    Builds an HTML form containing the elements of the job corresponding to the workOrder. 
    Also builds javascript function checkElements. 
    Code is slightly confusing because 'elements' is used both in our sense and in the HTML sense.
    
    This writes directly to the HTML document using PHP echo, and is intended to dynamically create the content of
    a dialog. It should be called using code like:
    
        $("#FOO_dialog").load('/ajax/jobelements.php?workOrderId=' + escape(<?php echo $workOrder->getWorkOrderId(); ?>), function(){
            $('#FOO_dialog').dialog({height:'auto', width:'auto'});
        });
        
   >>>00002, >>>00016: still needs to properly check inputs     
*/

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$workOrder = new WorkOrder($workOrderId);
// BEGIN ADDED 2020-08-25 JM default to old constant value, >>>00002, >>>00016 bBut eventually inputs should be checked, and explicit value should be mandatory.
$dialogname= isset($_REQUEST['dialogName']) ? intval($_REQUEST['dialogName']) : 'elementdialog';
// END ADDED 2020-08-25 JM

$elements = array();
if ($workOrder->getWorkOrderId()) {
    $job = new Job($workOrder->getJobId());    
    $elements = $job->getElements();
}
?>

<script type="text/javascript">

<?php /* function checkElements uses /fb/workordertaskcats.php to run in an iframe, 
         adding multiple elements to a workOrder; then reloads the page.  */ ?>
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
        alert('check at least one box if you want to combine');
    } else {
        $.fancybox({
            'href': "/fb/workordertaskcats.php?" + $(f).serialize(),
            'type': 'iframe',
            "afterClose": function() {   
                parent.location.reload(true);        
            }
        });

        <?php /* 2020-08-25 JM got rid of tight coupling to caller here, using passed in $dialogname */ ?> 
        $("#<?= $dialogname ?>").dialog("close");          
    }
    return false;
}
</script>

<?php /* 2020-04-21 JM BEGIN CODE in support of fix for http://bt.dev2.ssseng.com/view.php?id=106 */ ?>
<style>
.one-task-link {
    cursor: pointer;
    color: blue; /* Why does this have no effect? (worked around it below) */
}
.one-task-link:hover {
    text-decoration: underline; /* Why does this have no effect? (worked around it below) */
}
</style>
<?php /* 2020-04-21 JM END CODE in support of fix for http://bt.dev2.ssseng.com/view.php?id=106 */ ?>

<?php 
/* HTML form/table: "Which element are you working with?" 
    For 'General' and for each element: 
        * a checkbox with the elementId as value (0 for 'General')
        * element name (or 'General') is a link submitting the elementId and workOrderId to 
          /fb/workordertaskcats.php in an iframe. 
    Submitting the form with the submit button labeled "combine" calls function checkElements, effectively submitting 
     all checked elements to /fb/workordertaskcats.php in an iframe. 
    REMOVING THE FOLLOWING 2019-12: There is also a link to workOrder.php for the current workorder and act='importdescriptortasks', 
     labeled "Import Descriptor Tasks".  Based on discussions with Martin, 'importdescriptortasks' appears to have been a one-time task, 
     already completed some time in 2017 or 2018; JM believes it should probably be stripped out of the code because it has no further use.
*/

echo '<form name="elementForm" id="elementForm" method="" action="" onSubmit="return checkElements();">' . "\n";
    echo '<input type="hidden" name="workOrderId" value="' . intval($workOrderId) . '">' . "\n"; /* hidden workOrder Id */ 
    echo '<table border="0" cellpadding="0" cellspacing="0" width="200">' . "\n";
    
    echo '<tr>' . "\n";
        echo '<td colspan="2">Which element are you working with?</td>' . "\n";
    echo '</tr>' . "\n";
        echo '<tr>' . "\n";
            echo '<th colspan="2">Element Name</th>' . "\n";
        echo '</tr>' . "\n";
        
        echo '<tr>' . "\n";
            echo '<td><input type="checkbox" name="elementId[]" value="0"></td>' . "\n";
            /* BEGIN REPLACED 2020-04-21 JM for http://bt.dev2.ssseng.com/view.php?id=106
            // The following is reasonable, but apparently doesn't work: page opened, but not in a fancybox.
            // Tried adding class "fancybox", but it didn't help. 
            // My (JM) guess is that the code that would make it run was executed dynamically *before* this element
            //  was created, so I am replacing it with completely dynamic code.
            echo '<td><a data-fancybox-type="iframe" class="closelink fancyboxIframe" href="/fb/workordertaskcats.php?' . 
                  'elementId=0&' . 
                  'workOrderId=' . rawurlencode($workOrderId) . '">General</a></td>';
            // END REPLACED 2020-04-21 JM
            */
            // BEGIN REPLACEMENT 2020-04-21 JM
            // (>>>00001: I (JM) have absolutely no idea why I couldn't effectively do text-decoration or color via the style element above, but I couldn't, so
            //   it's directly on the element; no way to confine this to show just on hover).
            echo '<td><a class="one-task-link closelink" style="text-decoration: underline; color: blue" data-elementid="0">General</a></td>' . "\n";
            // END REPLACEMENT 2020-04-21 JM
        echo '</tr>';
        foreach ($elements as $element) {            
            echo '<tr>';        
                echo '<td><input type="checkbox" name="elementId[]" value="' . intval($element->getElementId()) . '"></td>' . "\n";
                /* BEGIN REPLACED 2020-04-21 JM for http://bt.dev2.ssseng.com/view.php?id=106
                // See comment for similar case above
                echo '<td><a data-fancybox-type="iframe" class="closelink fancyboxIframe" href="/fb/workordertaskcats.php?' . 
                  'elementId=' . intval($element->getElementId()) . '&' . 
                  'workOrderId=' . rawurlencode($workOrderId) . '">' . $element->getElementName() . '</a></td>';                
                // END REPLACED 2020-04-21 JM
                */
                // BEGIN REPLACEMENT 2020-04-21 JM 
                // (>>>00001: I (JM) have absolutely no idea why I couldn't effectively do text-decoration or color via the style element above, but I couldn't, so
                //   it's directly on the element; no way to confine this to show just on hover).
                echo '<td><a class="one-task-link closelink" style="text-decoration: underline; color: blue" data-elementid="' . $element->getElementId() . '">' . 
                    $element->getElementName() . '</a></td>' . "\n";
                // END REPLACEMENT 2020-04-21 JM
            echo '</tr>';
        }
        echo '<tr>';
            echo '<td colspan="2" style="text-align:center"><input type="submit" value="combine"></td>';
        echo '</tr>';
        
        echo '<tr>';
            // just a spacer
            echo '<td colspan="2" style="text-align:center"><hr></td>';
        echo '</tr>';
        /* BEGIN REMOVED 2019-12-18
        echo '<tr>';
            echo '<td colspan="2" style="text-align:center"><a href="' . $workOrder->buildLink() . '?act=importdescriptortasks">Import Descriptor Tasks</a></td>';
        echo '</tr>';
        END REMOVED 2019-12-18
        */
    echo '</table>';
echo '</form>';

?>

<script>
<?php /* 2020-08-25 JM got rid of tight coupling to caller here, using passed in $dialogname */ ?> 
$('#<?= $dialogname ?> .closelink').click(function () {
    $("#<?= $dialogname ?>").dialog("close");
    });

<?php /* 2020-04-21 JM BEGIN CODE in support of fix for http://bt.dev2.ssseng.com/view.php?id=106 */ ?>
$('.one-task-link').click(function() {
    $.fancybox({
       href: '/fb/workordertaskcats.php?elementId=' + $(this).data('elementid') + '&workOrderId=<?= $workOrderId ?>', 
       type: 'iframe',
       afterClose: function() {   
            parent.location.reload(true);
       }
    })
});
<?php /* 2020-04-21 JM END CODE in support of fix for http://bt.dev2.ssseng.com/view.php?id=106 */ ?>
</script>
