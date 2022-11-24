<?php
/* fb/editjobteamposition.php

   EXECUTIVE SUMMARY: Edit a row in the team table, by effectively changing the "position" 
    name associated with a particular person on that team. Person must already be on the team.

   PRIMARY INPUT: $_REQUEST['teamId'], which identifies a particular person's association with a team 
    (primary key in DB table Team).

   Optional $_REQUEST['act']. Only meaningful value: 'update', which uses value $_REQUEST['teamPositionId']. 
   There is also some vestgial code for $_REQUEST['act'] = 'addworkorderperson', but it does nothing.
*/

include '../inc/config.php';
include '../inc/access.php';

$error = '';
$errorId = 0;
$personId = 0;
$teamId = 0;
$companyId = 0;
$error_is_db = false;
$db = NULL;
$person = NULL;
$teamCompanyPerson = NULL;
$companyPerson = "";
$companyPersonId = 0;
$teamPositionId = 0;


$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'teamId');
$v->rule('integer', 'teamId');
$v->rule('min', 'teamId', 1);

if( !$v->validate() ) {
    $logger->error2('637190138899683511', "Page requested by: ".$_SERVER['REQUEST_URI'] . "Errors found: ".json_encode($v->errors()));
    header("Location: /404.php");
    exit();
}

list($error, $errorId) = $v->init_validation();
if (!$error) { 
    $teamId = intval($_REQUEST['teamId']);
}

$db = DB::getInstance();
if (!$error && $act == 'update') {

    $v->rule('required', ['teamPositionId'])->label('Select position from list');
     
    if (!$v->validate()) {
        $errorId = '637183115313887184';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    }       

	if (!$error) {
        $teamPositionId = $_REQUEST['teamPositionId'];
    
        // Update the relevant row in DB table Team; close fancybox on success.
        $query = " update " . DB__NEW_DATABASE . ".team  set ";
        $query .= " teamPositionId = " . intval($teamPositionId) . " ";	
        $query .= " where teamId = " . intval($teamId);
        $result = $db->query($query);

        if (!$result) {
            $errorId = '637196233123425183';
            $error = 'editJobTeamPosition method failed.';
            $error_is_db = true;
            $logger->errorDb($errorId, $error, $db);
        }
    }

    if ($error) {
        if ($error_is_db) {
            $logger->errorDb($errorId, $error, $db);
        } else {
            $logger->error2($errorId, $error);
        }
    }
    else {
	?>
        <script type="text/javascript">
            parent.$.fancybox.close();
        </script>

	<?php 
	}
}

$record = false;

$query = " select * ";
$query .= " from " . DB__NEW_DATABASE . ".team ";
$query .= " where teamId = " . intval($teamId) . " ";

$result = $db->query($query);
if (!$result) {
    // >>>00001: JM 2020-03-12 if the prior error ('637196233123425183') also occurred, it is probably the more important one to report
    // in the UI, so you may want to rethink just overwriting it here.
    $errorId = '637196234107483028';
    $error = 'SelectTeamId method failed.';
    $error_is_db = true;
    $logger->errorDb($errorId, $error, $db);
} else {       
    if ($result->num_rows > 0) {
        /* BEGIN REPLACED 2020-03-12 JM : no reason for a while here, can only be one row
        while ($row = $result->fetch_assoc()) {
            $record = $row;
        }
        */
        // BEGIN REPLACEMENT 2020-03-12 JM
        $record = $result->fetch_assoc();
        // END REPLACEMENT 2020-03-12 JM
   }
}

include '../includes/header_fb.php';

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}


if ($record) {
    $companyPerson = new CompanyPerson($record['companyPersonId']);    
    if (intval($companyPerson->getCompanyPersonId())) {
        echo'<p>&nbsp;</p>';
        echo'<p>&nbsp;</p>';
        echo'<p>&nbsp;</p>';
        echo'<p>&nbsp;</p>';

        echo '<form name="theform" id="theform"  action="editjobteamposition.php" method="POST">';
            echo '<input type="hidden" name="teamId" value="' . intval($teamId) . '">';
            echo '<input type="hidden" name="act" value="update">';
            echo '<center>';
                echo '<table border="0" cellpadding="6" cellspacing="3">';
                    echo '<tr>';
                        echo '<th>Person</th>';
                        echo '<th>Company</th>';
                        echo '<th>Position</th>';				
                    echo '</tr>';
                    echo '<tr>';
                        $company = $companyPerson->getCompany();
                        $person = $companyPerson->getPerson();            
                        echo '<td>' . $person->getFormattedName() . '</td>';
                        echo '<td>' . $company->getCompanyName() . '</td>';
                            
                        echo '<td>';
                            /* Position: HTML SELECT, initially select current value. 
                                 For each option, value is teamPositionId; display appropriate name. 
                                 First OPTION is value 0, display is '--Choose Position--' */
                            $positions = getTeamPositions();
                            echo '<select name="teamPositionId" onChange="this.form.submit();"><option value="">-- Choose Position --</option>';
                            foreach ($positions as $position) {
                                $selected = ($position['teamPositionId'] == $record['teamPositionId']) ? ' selected ' : '';
                                echo '<option ' . $selected . ' value="' . $position['teamPositionId'] . '">' . htmlspecialchars($position['name']) . '</option>';
                            }
                            echo '</select>';
                        echo '</td>';
                    echo '</tr>';
                echo '</table>';
			echo '</center>';
        echo '</form>';
        echo '<input type="submit" value="update">';
    }
} // END if ($record)

?>
<script>
var jsonErrors = <?=json_encode($v->errors())?>;
var validator = $('#theform').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        'teamPositionId':{
            required: true
        }
    }
});
//console.log(validator);
validator.showErrors(jsonErrors);
</script>
<?php
include '../includes/footer_fb.php';
?>