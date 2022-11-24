<?php 
/*  fb/jobelements.php
    
    EXECUTIVE SUMMARY: add an element to a job.

    PRIMARY INPUT: $_REQUEST['jobId']

    Optional $_REQUEST['act']. Only meaningful value: 'addelement', which uses value $_REQUEST['elementname'].
*/

include '../inc/config.php';
include '../inc/access.php';
// ADDED by George 2020-08-10, function do_primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$elementName = '';
$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'jobId');
$v->rule('integer', 'jobId');
$v->rule('min', 'jobId', 1);

if( !$v->validate() ) {
    $errorId = '637326599973789348';
    $logger->error2($errorId, "jobId : " . $_REQUEST['jobId'] . " not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid jobId. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$jobId = intval($_REQUEST['jobId']); // The jobId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'job'.
if (!Job::validate($jobId)) {
    $errorId = '637326602185432861';
    $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing DB row in job table.");
    $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$job = new Job($jobId);
$elementName = "";
if ($act == 'addelement') {

    $v->rule('required', 'elementName');
   // $v->rule('different', 'elementName', 'General');

    if (!$v->validate()) {
        $errorId = '637326607633080545';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, elementName is required.";
    } else {
        // Truncation for elementName length 255 is handled in Element::setElementName()
        $elementName = $_REQUEST['elementName'];
       
        //  On success, close the fancybox. 
        //  $result alias to $elementId in previous code.
        // >>>00028 Shouldn't adding element and giving it a name (below, with the update method) be inside a transaction? 
        // George 2020-08-10. Rewrite logic in methods in order to rollback insert if save not possible
        // addElement() -> set the jobId, get the elementId, check vor a valid elementId, setElementName and save.
        if (trim($elementName) != "General") { 

            $success = $job->addElement($elementName, $error_is_db);
       


            if (!$success) { // false on Element add not possible or elementId not integer and greater than 0.
                $errorId = '637375960130047864';
                $error = 'Add Element failed.'; // message for User
                $logger->error2($errorId, "addElement method failed [$elementName]");
            }

            if (!$error) {
                if ($error_is_db) { //true on query failed.
                    $errorId = '637375960954778213';
                    $error = 'addElement method failed. Database Error.'; // message for User
                    $logger->errorDB($errorId, "addElement method failed => Hard DB error ", $db);
                }
            }
               // Do the action, close the fancybox.
            if (!$error) {
            ?>
                    <script type="text/javascript">       
                        //parent.location.reload(true);     
                        if ( window.history.replaceState ) {
                            window.history.replaceState( null, null, window.location.href );
                        }
                    </script>               
            <?php
            } 
        } 

    }


}

$elements = $job->getElements($error_is_db); //an array of Element objects
if ($error_is_db) {
    $errorId = '637372350960888514';
    $error = 'We could not display the Elements for this Job. Database Error.'; // message for User
    $logger->errorDB($errorId, "getElements method failed => Hard DB error ", $db);
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>"; 
}
?>

<style>
/* George: beautify the form popup */
body {background:#fff;}
</style>

<?php
echo '<h1>' . $job->getName() . '</h1>';
echo '<form id="topForm" name="addElement" action="" method="POST">';
    echo '<input type="hidden" name="jobId" value="' . intval($job->getJobId()) . '">';
    echo '<input type="hidden" name="act" value="addelement">';
    
    echo '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
        /* pseudo-heading spanning the table */
        echo '<tr>';            
            echo '<td colspan="2"><h2>Current Elements</h2></td>';			
        echo '</tr>';
        
        /* list all current elements for the job (in a subtable) */
        echo '<tr>';
            echo '<td colspan="2">';        
                echo '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
                foreach ($elements as $element) {
                    echo '<tr>';
                        echo '<td>&nbsp;&#8226;</td>'; // bullet
                        echo '<td width="100%">' . $element->getElementName() . '</td>';
                    echo '</tr>';
                }
                echo '</table>';            
            echo '</td>';
        echo '</tr>';
        
        /* empty row + pseudo-heading spanning the table */
        echo '<tr>';
            echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="2"><h2>New Element</h2></td>';
        echo '</tr>';
        
        /* fill in new element name & submit */
        echo '<tr>';
            echo '<td>Element Name</td>';
            echo '<td><input type="text" class="form-control col-md-6" id="elementName" name="elementName" value="" size="50" maxLength="255" required></td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td colspan="2" style="text-align:center;">
                <input type="submit" id="addElement" class="btn btn-secondary mr-3" value="add element" border="0">
                <buton type="button" class="btn btn-info mr-3" onClick="parent.location.reload(true);">Close</button>
                </td>';
        echo '</tr>';
    echo '</table>';
echo '</form>';
?>
<script>

    
// Not General for the Job.
$("#addElement").click(function() { 
    var elementNameInput = $("#elementName").val();


    if(elementNameInput.trim() == "General") {
      
        alert("Element General already exists.");
    }

});

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#topForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        'elementName':{
            required: true
        }
    }
});

validator.showErrors(jsonErrors);

// The moment they start typing(or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#elementName-error').hide();
    if ($('#elementName').hasClass('text-danger')){
        $("#elementName").removeClass("text-danger");
    }  
   
});


</script>

<?php
include '../includes/footer_fb.php';

?>