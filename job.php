<?php
/*  job.php

    EXECUTIVE SUMMARY: A top-level page to view or edit a job.
    There is a RewriteRule in the .htaccess to allow this to be invoked as just "job/foo" rather than "job.php?rwname=foo".

    PRIMARY INPUT: $_REQUEST['rwname'], *not* 'jobId' or 'jobNumber'.

    OPTIONAL INPUT $_REQUEST['act']. Possible values: 'updatejob', 'deletejobteam'.
      * 'deletejobteam' uses further argument $_REQUEST['teamId'].
      * 'updatejob' uses further arguments:
        * $_REQUEST['name'] (job name)
        * $_REQUEST['description']
        * $_REQUEST['jobStatusId']
        Also, at least in theory it can take these, though I (JM) believe these aren't used because they never change:
          * $_REQUEST['customerId']
          * $_REQUEST['number']
          * $_REQUEST['rwname']
          * $_REQUEST['created']
          * $_REQUEST['code']

*/

require_once './inc/config.php';
require_once './inc/perms.php';
// ADDED by George 2020-08-20, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add
$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'number');
if (!$v->validate()) {
    $errorId = '637335223123647588';
    $logger->error2($errorId, "Rwname errors: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid Number for this Job in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$jobNumber = trim($_REQUEST['number']);
//syslog(LOG_INFO, "\$rwname = '$rwname'"); //notification in log system of a Job.

// Now we make sure that the row actually exists in DB table 'job'
if (!Job::validateNumber($jobNumber)) {
    $errorId = '637335281672552232';
    $logger->error2($errorId, "The provided job $jobNumber does not correspond to an existing DB row in job table");
    $_SESSION["error_message"] = "Invalid job. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die();
}

$job = new Job($jobNumber); // Construct Job object

// Job Status.
/*
    * Declare an array of jobStatusId's.
    * Default selection of dropdown is "Active", jobStatusId 1.
*/
/* BEGIN REMOVED 2020-11-18 JM: no longer allow user to overtly set jobStatus
$statusesIdsDB = array();

$statuses = jobStatuses($error_is_db); //Handled in function.
if($error_is_db) { //true on query failed.
    $errorId = '637335320698426132';
    $error = 'We could not display the Statuses for this Job. Database Error.';
    $logger->errorDB($errorId, 'jobStatuses method failed.', $db);
}

if (!$error) {
    foreach ($statuses as $value) {
        $statusesIdsDB[] = $value["jobStatusId"]; //Build an array with valid jobStatusId's from DB, table jobStatus.
    }
}
// End Job Status check.
// END REMOVED 2020-11-18 JM
*/

// Update Job
if (!$error && $act == 'updatejob') {

    $v->rule('required', 'name'); // Required.
    $v->rule('regex', 'name', '/[a-zA-Z]/'); // Job name must contain at least one alphabetic character.
    /* BEGIN REMOVED 2020-11-18 JM: no longer allow user to overtly set jobStatus
    $v->rule('in', 'jobStatusId', $statusesIdsDB); // jobStatusId value must be in array.
    // END REMOVED 2020-11-18 JM
    */

    if (!$v->validate()) {
        $errorId = '637335370998457259';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {

        $request_job = array();
        // reworked JM 2020-03, modeled on company.php, >>>00002 but in both files it would be better to properly validate inputs.
        //  (Leave this as is for release 2020-2 unless anyone finds an active bug here.)

        $request_job['name'] = $_REQUEST['name']; // Required. Maxlength 75, entry in table Job.
        $request_job['name'] = truncate_for_db ($request_job['name'], 'Job name', 75, '637335353342414272'); //  handle truncation when an input is too long for the database.

        $request_job['description'] = isset($_REQUEST['description']) ? $_REQUEST['description'] : ''; //Maxlength 255, entry in table Job.
        $request_job['description'] = truncate_for_db ($request_job['description'], 'Job Description', 255, '637335355125858510');

        /* BEGIN REMOVED 2020-11-18 JM: no longer allow user to overtly set jobStatus
        // value must be in array statusesIdsDB.
        $request_job['jobStatusId'] = $_REQUEST['jobStatusId'];
        // END REMOVED 2020-11-18 JM
        */

        $success = $job->update($request_job);

        if (!$success) {
            $errorId = '637336055336228720';
            $error = 'update Job failed.'; // message for User
            $logger->errorDB($errorId, "update Job method failed => Hard DB error ", $db);
        } else {
            // Reload this page cleanly, no $act.
            header("Location: " . $job->buildLink(true));
            die();
        }
        // unset, don't let these get out of this scope
        unset($request_job);
    }

}
// End Update Job.

// Delete Job Team.
if (!$error && $act == 'deletejobteam') {

    $v->rule('required', 'teamId'); // Required.
    $v->rule('integer', 'teamId');

    if (!$v->validate()) {
        $errorId = '637335391763749780';
        $logger->error2($errorId, "Error in parameters ".json_encode($v->errors()));
        $error = "Error in parameters, invalid teamId";
    } else {

        $teamId = $_REQUEST['teamId'];

        $success = $job->deleteFromTeam($teamId, $integrityIssues);

        if (!$success) {
            $errorId = '637338690120187773';
            $error = 'Delete Team method failed.';
            $logger->errorDB($errorId, "deleteFromTeam method failed => Hard DB error ", $db);

        } else if (!$error && $integrityIssues == true) { // If no query failled (result true) and At least one reference to this row exists in the database, violation of database integrity.
            $errorId = '637338690058164670';
            $error = 'Team member still in use, delete not possible.'; // DB Integrity issue message.
            $logger->error2($errorId, $error. " jobTeam => deleteFromTeam. At least one reference to this row exists in the database, violation of database integrity.");
        } else {
            // success and no reference to this row exists in the database.
            header("Location: " . $job->buildLink());
            die();
        }

    }
}
// End Delete Job Team.



// Delete Job Team.
if ($act == 'deletejobelement') {
    //var_dump($_REQUEST);
    //die();
    $v->rule('required', 'elementId'); // Required.
    $v->rule('integer', 'elementId');

    if (!$v->validate()) {
        $errorId = '637689387751009944';
        $logger->error2($errorId, "Error in parameters ".json_encode($v->errors()));
        $error = "Error in parameters, invalid elementId";
    } else {

        $elementId = $_REQUEST['elementId'];

        $success = $job->deleteFromElement($elementId, $integrityIssues);

        if (!$success) {
            $errorId = '637689388092549971';
            $error = 'Delete ELement method failed.';
            $logger->errorDB($errorId, "deleteFromElement method failed => Hard DB error ", $db);

        } else if (!$error && $integrityIssues == true) { // If no query failled (result true) and At least one reference to this row exists in the database, violation of database integrity.
            header("Location: " . $job->buildLink()); 
            // George - maybe we gone Log in the future.
            //$errorId = '637689388385522488';
            //$error = 'Job Element still in use, delete not possible.'; // DB Integrity issue message.
            //$logger->error2($errorId, $error. " jobElement => deleteFromElement. At least one reference to this row exists in the database, violation of database integrity.");
           
        } else {
            // success and no reference to this row exists in the database.
            header("Location: " . $job->buildLink());
            die();
        }

    }
}
// End Delete Job Team.


$workorders = $job->getWorkOrders($error_is_db); // variable pass by reference in method.
if ($error_is_db) { //true on query failed.
    $errorId = '637338757911368626';
    $error .= ' We could not display the WorkOrders for this Job. Database Error.';
    $logger->errorDB($errorId, "getWorkOrders method failled.", $db);
}


// [Martin comment:] calc hours on job
$totaltime = 0;
$wototals = array(); // Associative array, indexed by workOrderId, value is minutes.
foreach ($workorders as $workOrder) {
	$wototals[$workOrder->getWorkOrderId()] = 0;
	$workOrderTasks = $workOrder->getWorkOrderTasksRaw($error_is_db, "1601498623");
    if($error_is_db){
        $error .= ' Cannot read Work Order Tasks Raw for WorkorderID '.$workOrder->getWorkOrderId()."<br>";
        continue;
    }
	foreach ($workOrderTasks as $workOrderTask) {
       
		$times = $workOrderTask->getWorkOrderTaskTime($error_is_db, "1601498624");
        if($error_is_db){
            $error .= ' Cannot read Work Order Tasks Time for WorkorderTask Id '.$workOrderTask->getWorkOrderTaskId()."<br>";
            continue;
        }
       
		foreach ($times as $time) {
            $totaltime += intval($time['minutes']);
           
			$wototals[$workOrder->getWorkOrderId()] += intval($time['minutes']);
		}
	}
}


$crumbs = new Crumbs($job, $user);

include BASEDIR . '/includes/header.php';
// Apparently it is possible to lack a job name, so we account for that in writing title:
$jobName = $job->getName();
echo "<script>\ndocument.title = 'Job ". $job->getNumber() .
    ($jobName ? (': ' . str_replace("'", "\'", $jobName)) : '') .
    "';\n</script>\n"; // Add title
unset ($jobName);

if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
include BASEDIR . '/includes/crumbs.php';

/* BEGIN REMOVED 2020-04-30 JM: killing code for an old migration.
$migration = isset($_REQUEST['migration']) ? $_REQUEST['migration'] : 'no';
$elementIds = isset($_REQUEST['elementIds']) ? $_REQUEST['elementIds'] : array();
$method = isset($_REQUEST['method']) ? $_REQUEST['method'] : '';

if ($migration == 'yes') {
	if ($method == 'combine') {

	    // CASE SHOULD BE COMPLETELY GONE 2020-03-09 JM; in the unlikely event that we somehow get here, scream!
	    $migration_error = "COMBINE case arose, shouldn't ever happen any more. \$rwname = $rwname, jobId = {$job->getJobId()}, " .
	        " \$migration = $migration, \$elementIds = $elementIds, \$method = $method";
	    echo "<p style=\"font-weight bold\">$migration_error</p>";
	    $logger->error2('1583796638', $migration_error);

		// methodCombine($elementIds, $job->getJobId(), true); // REMOVED 2020-03-09 JM


[BEGIN MARTIN COMMENT]
		+-------------+------------------+------+-----+---------+----------------+
		| Field       | Type             | Null | Key | Default | Extra          |
		+-------------+------------------+------+-----+---------+----------------+
		| elementId   | int(10) unsigned | NO   | PRI | NULL    | auto_increment |
		| jobId       | int(10) unsigned | YES  |     | NULL    |                |
		| workOrderId | int(10) unsigned | YES  |     | NULL    |                |
		| elementName | varchar(255)     | YES  |     | NULL    |                |
		| retired     | tinyint(4)       | NO   |     | 0       |                |
		+-------------+------------------+------+-----+---------+----------------+
		5 rows in set (0.00 sec)
[END MARTIN COMMENT]
		// [Martin comment:] s1510026
	}
}
// & fall through to normal use
// END REMOVED 2020-04-30 JM: killing code for an old migration.
*/


/*********************************************************************
 ************************* Normal use ********************************
 *********************************************************************/



?>

<script>

<?php /* BEGIN commented out by Martin before 2019, this is replaced by similar code near bottom of file */ ?>
/*
 * THESE 2 FUNCTIONS FOR GOOG MAP STUFF

function initialize() {
  var myLatlng = new google.maps.LatLng('<?php //echo $location->getLatitude();?>', '<?php //echo $location->getLongitude();?>');
  var mapOptions = {
    zoom: 11,
    center: myLatlng
  }
  var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

  var marker = new google.maps.Marker({
      position: myLatlng,
      map: map,
      title: 'location'
  });
}

function loadScript() {
  var script = document.createElement('script');
  script.type = 'text/javascript';
  // 2014-02-04 JM If this code is brought back to life, it will presumably need this outdated key to be updated
  script.src = 'https://maps.googleapis.com/maps/api/js?v=3.exp&key=AIzaSyC7pZil55Hw7m4earb0bT4vCN8UqJFT9sM&' +
      'callback=initialize';
  document.body.appendChild(script);
}
*/
//https://maps.googleapis.com/maps/api/js
<?php /* END commented out by Martin before 2019 */ ?>

//window.onload = loadScript; <?php /* commented out by Martin before 2019 */ ?>

<?php /* >>>00007: vacuous & never called, let's get rid of it. */ ?>
function submitSite() {
    <?php /* BEGIN commented out by Martin before 2019 */ ?>
	//if (document.upInfo.cmpCompany.value == "") {
	//	document.upInfo.cmpCompany.focus();
	//	alert("please enter the company name");
	//	return false;
	//}
	//return true;
	<?php /* END commented out by Martin before 2019 */ ?>
}

<?php /* [Martin comment:] new new */
/* Make AJAX call to make a team member active or inactive.
INPUT teamId: Primary key in DB table Team.
INPUT active: 'true' or 'false'
*/
?>

// [CP] function seems to be vestigial. No called from any part of the page
/*var setTeamMemberActive = function(teamId, active) {
    var formData = "teamId=" + escape(teamId) + "&active=" + escape(active);

    <?php /* write temporary icon in relevant cell to indicate server action taking place */?>
    var cell = document.getElementById("teamactivecell_" + teamId);
    cell.innerHTML = '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/loader/ajax_loader.gif" width="16" height="16" border="0">';

    $.ajax({
        url: '/ajax/woteam_active_toggle.php',
        data:formData,
        async:false,
        type:'post',
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') { // [T000016]
                    <?php /* restore cell with new value */?>
                    var html =  '<td align="center" id="teamactivecell_' + teamId + '">' +
                                '<a href="javascript:setTeamMemberActive(' + teamId + ',' + data['linkActive'] + ')">' +
                                '<img src="/cust/<?php echo $customer->getShortName(); ?>' +
                                '/img/icons/icon_active_' + data['active'] + '_24x24.png" width="16" height="16" border="0"></a></td>';
                    cell.innerHTML = html;
                    //restoreClick(workOrderTaskId); <?php /* commented out by Martin before 2019 */ ?>
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
}*/

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
            return "Copy WO Link";
        },
        position: {
            my: "center bottom",
            at: "center top"
        }
    });
});

</script>
<style type="text/css">
.job sh1 {font-size : 130%;font-weight: bold; }
body, #container1, #container2, #col2, table { background-color: #fff; }
h2.heading { font-weight: 500;}
.full-box, #jobName { margin-top: 30px; }
.siteform { margin-bottom: 30px; }
h6 { font-weight: 400; margin-bottom: 0.5px; }
a.disabled {
  pointer-events: none;
  cursor: default;
  color: #000;
}
#copyLink {
    color: #000;
    font-family: Roboto,"Helvetica Neue",sans-serif;
    font-size: 12px;
    font-weight: 600;
}

#copyLink:hover {
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}
#firstLinkToCopy {
    font-size: 18px;
    font-weight: 700;
}
</style>

<div id="container" class="clearfix">
<?php 
            $elements = $job->getElements($error_is_db); 
       
          
            $retur  = array();
            $clientMulti = array();
            $elementsFinalArr = array();
            $data = array();
            $finalJobMulti = 0;
            $nrEl = 0;
            foreach($workorders as $workorder) {
            
                $query = "SELECT  data2, clientMultiplier FROM  " . DB__NEW_DATABASE . ".contract ";
                $query .= " WHERE workOrderId = " . intval( $workorder->getWorkOrderId()) . ";";
           
                        $result = $db->query($query);

                        while ($row = $result->fetch_assoc()) {
                            $retur[] = $row;
                            $clientMulti[] = $row['clientMultiplier'];
                        }

             
            }

         
            foreach($retur as $returVal) {
                $data[] = json_decode($returVal["data2"], TRUE);
            }
           
        
            foreach ($elements as $element) {
          
                $ret = array(); // all workOrderTasks for Elements
                $resultTasks = array();
                $resultWo = array();
                $woTasksIds = array();
                $woTime = 0; // time WO
                $woCost = 0; // cost WO
                $woTimeEl = 0;
                $revenue = 0;
                $final = 0;
                $revenueTotal = 0;
                $clientMultiplier = 1;
                $woTaskWithHours = array();
               

                       
                $arrayWot = [];
                $query = "SELECT woEl.workOrderTaskId, woEl.elementId, woTt.minutes, wo.quantity, wo.cost ";
                $query .= " FROM " . DB__NEW_DATABASE . ".workOrderTaskElement woEl ";
                $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskTime woTt on woTt.workOrderTaskId = woEl.workOrderTaskId ";
                $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTask wo on wo.workOrderTaskId = woTt.workOrderTaskId ";
                $query .= " WHERE woEl.elementId = " . intval($element->getElementId()) . ";";
         

                $result = $db->query($query);
                while ($row = $result->fetch_assoc()) {
                    $arrayWot[] = $row;
                }

                
                foreach($arrayWot as $woT) {
                    $woTime = intval($woT["minutes"]/60*100)/100; // per WoT
                    if($woT["quantity"] != 0 && $woT["cost"] != 0) {
                        $woTimeEl += $woTime;
                    }
                    

                    $query = "SELECT  workOrderId FROM  " . DB__NEW_DATABASE . ".workOrderTask ";
                    $query .= " WHERE workOrderTaskId = " . intval($woT['workOrderTaskId']) . ";";
               
                    $result = $db->query($query);

                        
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $query = "SELECT  clientMultiplier FROM  " . DB__NEW_DATABASE . ".contract ";
                        $query .= " WHERE workOrderId = " . intval($row['workOrderId']) . ";";

                        $result = $db->query($query);
    
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            if($row['clientMultiplier'] > 0) {
                                $clientMultiplier = $row['clientMultiplier'];
                            } else {
                                $clientMultiplier = 1;
                            }
                        }
            
                    }
                   
                 
                    $revenue = ($woT["quantity"] * $woT["cost"] * $clientMultiplier);


             
                    $revenueTotal += $revenue;
                    
                }
              
                if( $revenueTotal > 0 &&  $woTimeEl > 0 ) {
                    $final =  $revenueTotal /  $woTimeEl;
                } else {
                    $final = 0;
                }

               

                $elementsFinalArr[] = $final;
               
                $finalJobMulti += $final;

               
            }

            $nrEl = count( array_filter($elementsFinalArr ));
           
            if( $nrEl  > 0 &&  $finalJobMulti > 0) {
                $finalJobMulti = $finalJobMulti /  $nrEl;
            } else {
                $finalJobMulti = 0;
            }
  
        ?>
        
        <?php
        $urlToCopy = REQUEST_SCHEME . '://' . HTTP_HOST . '/job/' . rawurlencode($job->getJobId());
    ?>
    <div  style="overflow: hidden;background-color: #fff!important; position: sticky; top: 125px; z-index: 50;">
        <p id="firstLinkToCopy" class="mt-2 mb-1 ml-4" style="padding-left:3px; float:left; background-color:#fff!important">
            (J)&nbsp;<a href="<?php echo $job->buildLink(); ?>"><?php echo $job->getNumber();?></a>
            - <?php echo $job->getName();?> </a>
            <button id="copyLink" title="Copy Job link" class="btn btn-outline-secondary btn-sm mb-1 " onclick="copyToClip(document.getElementById('linkToCopy').innerHTML)">Copy</button>
        </p> 
        <?php if($finalJobMulti > 0) { ?>

            <p class="mt-3 mb-1 ml-4"  style="padding-right:3%; float:right; background-color:#fff!important; font-size:18px;" ><a class="font-weight-bold mr-1' style='font-size:19px; " href="#"> <?php echo number_format($finalJobMulti, 2) ; ?></a></p>
        <?php } else { 
            $finalJobMulti = 0;
        ?>
            
            <p class="mt-3 mb-1 ml-4"  style="padding-right:4%; float:right; background-color:#fff!important" ><a class="font-weight-bold mr-1' style='font-size:19px; " href=""> <?php echo number_format($finalJobMulti, 2) ; ?></a></p>

        <?php } ?>
        
        
        <span id="linkToCopy" style="display:none"> (J)<a href="<?php echo $job->buildLink(); ?>">&nbsp;(<?php echo $job->getNumber();?></a>)&nbsp; - &nbsp;<a href="<?= $job->buildLink()?>"> <?php echo $job->getName();?> </a></span>

        <span id="linkToCopy2" style="display:none"> <a href="<?= $urlToCopy?>">(J)&nbsp;<?php echo $job->getNumber();?>
            - &nbsp; <?php echo $job->getName();?> </a></span>
    </div>
    <div class="clearfix"></div>
	<div class="main-content">
	<?php
	$ids = array(); // Associative array, indexed by companyPersonId, value is always the same as index (so it's really
	                // just membership in a set). Lists everyone who is in any of these roles, but does not provide
	                // any information about what role they are in.

	$roles = array(TEAM_POS_ID_CLIENT
	    ,TEAM_POS_ID_DESIGN_PRO
	    ,TEAM_POS_ID_EOR
	    ,TEAM_POS_ID_STAFF_ENG
	    ,TEAM_POS_ID_CORRESPONDEE
	    ,TEAM_POS_ID_REFERRER
	    ,TEAM_POS_ID_ORIGINATOR
	    ,TEAM_POS_ID_CONTRACTOR
	    ,TEAM_POS_ID_JURISDICTION
	    ,TEAM_POS_ID_SUPPLIER
	    ,TEAM_POS_ID_LEADENGINEER
	    ,TEAM_POS_ID_SUPPORTENGINEER
	    ,TEAM_POS_ID_CONSULTANT);

	foreach ($workorders as $workOrder) {
	    foreach ($roles as $role) {
	        $ts = $workOrder->getTeamPosition($role, false, false); // [Martin comment:]  this will get active and non active
	        foreach ($ts as $t) {
	            $ids[$t['companyPersonId']] = $t['companyPersonId'];
	        }
	    }
	}

	$blockedcps = array(); // numerically-indexed array of CompanyPerson objects for billing-blocked companyPersons

    // For each person with a role on the job...
    foreach ($ids as $id) {
        $blocks = array(); // numerically-indexed array of rows from DB table BillingBlock
        $blocked = false;

        // >>>00027 would be a lot clearer to add LIMIT 1 to this query, since that's all we ever look at.
        $query = "select * from " . DB__NEW_DATABASE . ".billingBlock ";
        $query .= " where companyPersonId = " . intval($id) . " ";
        $query .= " order by inserted desc "; // reverse chronological
        //$query .= "LIMIT 1;"; // George: sugesstion of code.

        $result = $db->query($query); // George 2020-08-24. Rewrite "if" statement.

        if (!$result) {
            $errorId = '637338806325927298';
            $logger->errorDB($errorId, "Select query => billingBlock, hard DB error", $db);
        } else  if ($result->num_rows > 0) {
            // >>>00006 since we only care about the first row, the following could be 'if' instead of 'while'
            while ($row = $result->fetch_assoc()) {
                $blocks[] = $row;
            } // George: sugesstion of code.
            //$row = $result->fetch_assoc();
            //$blocks[] = $row;

        }

        if (count($blocks)) {
            $current = $blocks[0];

            if ($current) {
                if ($current['billingBlockTypeId'] != BILLBLOCK_TYPE_REMOVEBLOCK) {
                    // There have been blocks, and the latest isn't a "remove", so this companyPerson ($id) is
                    // blocked globally, though there still might be exceptions.
                    $blocked = true;
                }
            }
            if ($blocked) {
                $cp = new CompanyPerson($id);
                $blockException = $cp->getBlockException();
                $check = explode(",", $blockException);

                if (is_array($check)){
                    $jn = $job->getNumber();
                    $jn = trim($jn);
                    if (!in_array($jn, $check)) {
                        // There are exceptions, but not for this job
                        $blockedcps[] = $cp;
                    }
                } else {
                    // There are no exceptions
                    $blockedcps[] = $cp;
                }
            }
        } // END if (count($blocks)) {
    } // END foreach ($ids...

    // If anyone related to this job is billing-blocked, display that:
    if (count($blockedcps)) {
        echo '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
            echo '<tr>';
                echo '<td bgcolor="#ffbdbd"><h2>STOP, DO NOT WORK ON THIS :: There is a billing issue with the following Company-Person(s)</h2></td>';
            echo '</tr>';
            foreach ($blockedcps as $cp) {
                //$cp = new CompanyPerson($blockedid);	 // commented out by Martin before 2019
                echo '<tr>';
                    echo '<td  bgcolor="#ffbdbd">';
                        $cmp = $cp->getCompany();
                        $per = $cp->getPerson();
                        echo '<a id="blockedCps" href="' . $cp->buildLink() . '">' . $cmp->getName() . '&nbsp;/&nbsp;' . $per->getFormattedName(1) . '</a>';
                    echo '</td>';
                echo '</tr>';
            }
        echo '</table>';
    }

    ?>

<?php /* OUTDENT. We are still within div "container", "main-content" */ ?>

<div id="container2">
    <div id="container1">
        <div id="col1" class="row col-md-12">
            <div class="siteform col-md-6">
                <?php /* [Martin comment] Column one start */
                /* FORM:  * hidden: act=updatejob
                          * hidden: jobId
                          * job number and scannable qr-code
                          * (if user has admin-level permission for Jobs):
                            * total time in hours, summed over all workOrders for this Job
                          * job name, description, status (all editable, status is from dropdown)
                          * Submit button, labeled "Update"
                */
                ?>
                <form name="jobupdate" id="jobupdate" method="post" action="">
                    <input type="hidden" name="act" value="updatejob">
                    <?php /* Get rid of QR codes 2020-01-16 JM per http://bt.dev2.ssseng.com/view.php?id=74
                    <img style="float:right;" src="/other/phpqrcode.php?codeData=<?php echo rawurlencode($job->buildLink()); ?>" width="75" height="75" />
                    */ ?>
                    <h6>Job Number</h6>
                    <h1><?php echo $job->getNumber(); ?><?php
                        $checkPerm = checkPerm($userPermissions, 'PERM_JOB', PERMLEVEL_ADMIN);
                        if ($checkPerm) {
                            echo '&nbsp;(' . intval($totaltime/60*100)/100 . ' hrs)';
                        }
                    ?></h1>
                    <h6 id="jobName">Job Name</h6>
                    <input type="text" name="name" id="name" class="form-control input-sm" value="<?php echo htmlspecialchars($job->getName()); ?>" size="" maxlength="75" required>
                    <p></p>
                    <h6>Description</h6>
                    <textarea  name="description" id="description" class="form-control input-sm" rows="3" maxlength="255" ><?php echo htmlspecialchars($job->getDescription()); ?></textarea>
                    <p></p>
                    <?php /* BEGIN REMOVED 2020-11-18 JM: no longer allow user to overtly set jobStatus
                        <h6>Status</h6>
                            <?php
                            // [BEGIN Martin comment]
                            // fix this up and possibly make a generic status table
                            // and also generic defines in config.php
                            // [END Martin comment]
                            ?>
                        <div style="width:95%"><select class="form-control input-sm" name="jobStatusId" style="width:95%">
                            <?php
                                foreach($statuses as $status) {
                                    $selected = ($status['jobStatusId'] == $job->getJobStatusId()) ? ' selected' : '';
                                    echo '<option value="' . $status['jobStatusId'] . '" ' . $selected . '>' .'<strong>'.$status['jobStatusName'] . '<strong>'.'</option>';
                                }
                            ?>
                        </select></div>
                    // END REMOVED 2020-11-18 JM
                    */ ?>
                    <input type="submit" id="updateJob" style="width:20%" class="btn btn-secondary btn-lg mr-auto ml-auto mt-2" value="Update" >
                </form>

                <?php /* BEGIN ADDED 2019-12-04 JM as part of fixing http://bt.dev2.ssseng.com/view.php?id=53 */ ?>
                <script>
                    $('form[name="jobupdate"]').submit(function(e){
                        e.preventDefault();
                        var form = this;
                        // Job name cannot be empty, nor can it be entirely numeric. Look for at least one alphabetic character.
                        if ($('form[name="jobupdate"] input[name="name"]').val().match(/[a-zA-Z]/) ) {
                            form.submit(); // submit bypassing the jQuery bound event
                        } else {
                            alert("Job name must contain at least one alphabetic character");
                        };
                    });
                </script>
                <?php /* END ADDED 2019-12-04 JM as part of fixing http://bt.dev2.ssseng.com/view.php?id=53 */ ?>

                <?php /* [Martin comment] Column one end */ ?>
            </div> <?php /* END class=siteform */ ?>
       
            <div class="col-md-6" style="text-align: center;"><br></br>
         
            <br></br>
                <?php /* Ancillary data added 2019-11 JM */
                    AncillaryData::generateAncillaryDataSection('job', intval($job->getJobId()));
                ?>
                    <a href="/documentCenter.php?rwname=<?php echo $job->getRwName(); ?>" class="btn btn-outline-primary ">Document Center</a>
            </div>
            <div>
            </div>
        </div> <?php /* END col1 */ ?>
        <div id="col2">

            <?php /* ---- Any number of locations, including ability to create/edit & map from Google Maps ---- */ ?>

            <div class="siteform">
                <?php /* [Martin comment] Column two start */
                /*
                // [BEGIN commented out by Martin before 2019]
                <a data-fancybox-type="iframe" class="button add show_hide fancyboxIframe"  href="#">Add Exiting</a>&nbsp;
                <a  class="button edit show_hide"  href="/location.php?locationId=0&jobId=<?php echo $job->getJobId(); ?>">Create New</a>
                // [END commented out by Martin before 2019]
                */?>

                <?php
                // Separate error messages for this block. The user can still perform other actions on this page!
                $error_is_db = false;
                $errorLocations = '';
                $locations = $job->getLocations($error_is_db); // variable pass by reference in method.
                if ($error_is_db) { //true on query failed.
                    $errorId = '637338751840388743';
                    $errorLocations = 'We could not display the Locations for this Job. Database Error.';
                    $logger->errorDB($errorId, "getLocations method failled.", $db);
                }

                    $title = 'Locations';
                    if (count($locations)){
                        $title = 'Location';
                    }
                ?>

                <table class="table table-bordered table-striped" border="0" cellpadding="1" cellspacing="2" width="100%">
                <?php // Display specific "query failled" error for this Tab.
                    if ($errorLocations) {
                        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorLocations</div>";
                    }
                unset($errorLocations);
                ?>
                <?php
                    foreach ($locations as $location) {
                        echo '<tr>';
                            echo '<td valign="top" bgcolor="#dddddd">';
                                $address = str_replace("\n", "<br>",$location->getFormattedAddress());
                                echo $address;
                            echo '</td>';
                            echo '<td valign="top" bgcolor="#dddddd">';
                                echo 'Lat:' . $location->getLatitude();
                                echo '<br>';
                                echo 'Lng:' . $location->getLongitude();
                            echo '</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<td colspan="2" bgcolor="#dddddd"><a id="linkJobMap' . $location->getLocationId() . '" href="/location.php?locationId=' . $location->getLocationId() .
                                '&jobId='.$job->getJobId().'">Edit</a>&nbsp;||&nbsp;'; // jobId added JM 2019-11-21 so location.php can know to navigate back here.
                            /* NOTE link to local JS function newmap in next line */
                            echo '<a id="linkNewMap" href="javascript:newMap(\'' . $location->getLatitude() . '\',\'' . $location->getLongitude() . '\')">Map</a></td>';
                        echo '</tr>';
                    }
                ?>
                    <tr>
                        <td colspan="4" align="center" width="100%">
                            <div class="col" style="width:400px; float:right; height:280px; border:1px solid #aaa;" >
                                <div id="map-canvas" style="width: 100%; height: 100%"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <?php /*
                            // [BEGIN commented out by Martin before 2019]
                            <a data-fancybox-type="iframe" class="button add show_hide fancyboxIframe"  href="#">Add Exiting</a>&nbsp;
                            // [END commented out by Martin before 2019]

                            // [BEGIN commented out by Martin before 2019]
                            [<a href="#">Add Existing</a>]&nbsp;
                            // [END commented out by Martin before 2019]
                            */ ?>
                            [<a id="linkCreateNewMap" href="/location.php?locationId=0&jobId=<?php echo $job->getJobId(); ?>">Create New</a>]
                        </td>
                    </tr>
                </table>

                <?php /*
                // [BEGIN commented out by Martin before 2019]
                Recent Notes<br/>
                <iframe src="/iframe/recentnotes.php?jobId=<?php echo $job->getJobId(); ?>"></iframe>
                <br/>
                <a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/notes.php?jobId=<?php echo $job->getJobId(); ?>">See All Notes</a>
                <p></p>
                // [END commented out by Martin before 2019]
                */ ?>

                <?php /* [Martin comment] Column two start */ ?>
            </div> <?php /* END class=siteform */ ?>
        </div>  <?php /* END "col2" */ ?>
    </div>  <?php /* END "container1" */ ?>
</div>  <?php /* END "container2" */ ?>

<?php
/* List of job elements:
    * ability to add via /fb/jobelements.php in an iframe
    * ability to edit existing job element via /fb/element.php in an iframe.
*/
// Separate error messages for this block. The user can still perform other actions on this page!
$error_is_db = false;
$errorElements = '';

$elements = $job->getElements($error_is_db); // variable pass by reference in method.

if ($error_is_db) { //true on query failed.
    $errorId = '637338839442702849';
    $errorElements = 'We could not display the Elements for this Job. Database Error.';
    $logger->errorDB($errorId," getElements method failled.", $db);
}

?>
<div class="full-box clearfix">
    <?php
        /* kind-of messy English-specific pluralization */
        $plural = (!count($elements) || (count($elements) > 1)) ? 's' : '';
    ?>
    <h2 class="heading">Element<?php echo $plural; ?></h2>

    <?php /* Ability to add */ ?>
    <a data-fancybox-type="iframe" class="button add show_hide fancyboxIframe"  id="jobElements" href="/fb/jobelements.php?jobId=<?php echo $job->getJobId(); ?>">Add</a>

    <table class="table table-bordered table-striped">
        <tbody>
    <?php // Display specific "query failled" error for this Tab.
    if ($errorElements) {
        echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorElements</div>";
    }
    unset($errorElements);
    ?>
            <tr>
                <?php /* ?>		<th>Id</th> 		<?php */ /* commented out by Martin before 2019 */  ?>
				<th >&nbsp;</th>
				<th width="60%">Name</th>
				<th>Multi</th>
                <th>Descriptors</th>
                <th></th>
			</tr>
            <?php /* >>>00007 JM 2019-03-21: related to now-completed migration, we can get rid of this.
            // [BEGIN commented out by Martin before 2019]
            <script type="text/javascript">
            var methodCombine = function() {
                var form = document.getElementById('elementTool');
                form.method.value = "combine";
                form.submit();
            }
            </script>
            // [END commented out by Martin before 2019]
            */ ?>

			<?php /* >>>00007 JM 2019-03-21: related to now-completed migration, we can get rid of this. */
            // BEGIN MARTIN COMMENT
            // after migration can get rid of this form completely
            // and the checkboxes
            // and javascript
            // END MARTIN COMMENT

            // [BEGIN commented out by Martin before 2019]
            //	echo '<form name="elementTool" id="elementTool" method="post" action="">';
            //	echo '<input type="hidden" name="jobId" value="' . $job->getJobId() . '">';
            //	echo '<input type="hidden" name="rwname" value="' . $job->getRwName() . '">';
            //	echo '<input type="hidden" name="method" value="">';
            //	echo '<input type="hidden" name="migration" value="yes">';
            //	$needForms = false;
            // [END commented out by Martin before 2019]
            ?>
            <?php
                $retur  = array();
                $clientMulti = array();
                $data = array();

              
                foreach ($elements as $element) {
              
                    $ret = array(); // all workOrderTasks for Elements
                    $resultTasks = array();
                    $resultWo = array();
                    $woTasksIds = array();
                    $woTime = 0; // time WO
                    $woCost = 0; // cost WO
                    $woTimeEl = 0;
                    $revenue = 0;
                    $final = 0;
                    $revenueTotal = 0;
                    $clientMultiplier = 1;
                    $woTaskWithHours = array();


                   
                
                    $arrayWot = [];
                    $query = "SELECT woEl.workOrderTaskId, woEl.elementId, woTt.minutes, wo.quantity, wo.cost ";
                    $query .= " FROM " . DB__NEW_DATABASE . ".workOrderTaskElement woEl ";
                    $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTaskTime woTt on woTt.workOrderTaskId = woEl.workOrderTaskId ";
                    $query .= " LEFT JOIN " . DB__NEW_DATABASE . ".workOrderTask wo on wo.workOrderTaskId = woTt.workOrderTaskId ";
                    $query .= " WHERE woEl.elementId = " . intval($element->getElementId()) . ";";
             

                    $result = $db->query($query);
                    while ($row = $result->fetch_assoc()) {
                        $arrayWot[] = $row;
                    }

                    
                    foreach($arrayWot as $woT) {
                        $woTime = intval($woT["minutes"]/60*100)/100; // per WoT
                        if($woT["quantity"] != 0 && $woT["cost"] != 0) {
                            $woTimeEl += $woTime;
                        }
                        

                        $query = "SELECT  workOrderId FROM  " . DB__NEW_DATABASE . ".workOrderTask ";
                        $query .= " WHERE workOrderTaskId = " . intval($woT['workOrderTaskId']) . ";";
                   
                        $result = $db->query($query);
                       
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $query = "SELECT  clientMultiplier FROM  " . DB__NEW_DATABASE . ".contract ";
                            $query .= " WHERE workOrderId = " . intval($row['workOrderId']) . ";";

                            $result = $db->query($query);

                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                if($row['clientMultiplier'] > 0) {
                                    $clientMultiplier = $row['clientMultiplier'];
                                } else {
                                    $clientMultiplier = 1;
                                }
                            }
                
                        }
                    
  
               
                     
                        $revenue = ($woT["quantity"] * $woT["cost"] * $clientMultiplier);

    
                 
                        $revenueTotal += $revenue;
                        
                    }
                  
                    if( $revenueTotal > 0 &&  $woTimeEl > 0 ) {
                        $final =  $revenueTotal /  $woTimeEl;
                    } else {
                        $final = 0;
                    }

                    
                   
                    echo '<tr>';
               
                        //echo '<td>' . $element->getElementId() . '</td>'; // commented out by Martin before 2019

                        // (no header): "Edit" icon
                        echo '<td align="center" width="20"><a data-fancybox-type="iframe" class="fancyboxIframe" id="linkPencilElement' .intval($element->getElementId()) . '" href="/fb/element.php?elementId=' . intval($element->getElementId()) . '">'.
                             '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';
                        // "Name"
                        echo '<td>';
                            echo '<a data-fancybox-type="iframe"  id="linkElementName' .intval($element->getElementId()) . '" class="fancyboxIframe" href="/fb/element.php?elementId=' . intval($element->getElementId()) . '">';
                            echo $element->getElementName();
                            echo '</a>';
                            // [BEGIN commented out by Martin before 2019]
                            //if (!intval($element->retired) && !intval($element->migration)){
                            //	$needForms = true;
                            //	echo '<input type="checkbox" name="elementIds[]" value="' . $element->getElementId() . '">';
                            //}
                            // [END commented out by Martin before 2019]
                        echo '</td>';
                        echo '<td style="text-align: center;">';
           
                            // Em = Total revenue for Element / Hours spent on Element
                            echo " " . number_format($final, 2);
                            
         
                        echo '</td>';
                        // "Descriptors". All we really get here is a count.
                        echo '<td>';
                            // BEGIN REPLACED 2020-01-06 JM
                            // $descriptors = $element->getDescriptors();
                            // BEGIN REPLACEMENT 2020-01-06 JM
                            $descriptors = $element->getDescriptor2Ids();
                            // END REPLACEMENT 2020-01-06 JM
                            echo count($descriptors);
                        echo '</td>';
                        echo '<td>';
                        if ( canDelete('element', 'elementId', $element->getElementId()) ) {
                            echo '[<a id="deleteElement' . intval($element->getElementId()) . '" href="/job.php?rwname=' . urlencode($job->getRwName()) . '&act=deletejobelement&elementId=' . intval($element->getElementId()) .'">del</a>]';
                        }else {
                            echo '[<a onclick="return false;" class="disabled" id="deleteElement' . intval($element->getElementId()) . '" href="/job.php?rwname=' . urlencode($job->getRwName()) . '&act=deletejobelement&elementId=' . intval($element->getElementId()) .'">del</a>]';
                        }
                       
                        echo '</td>';
                    echo '</tr>';
                }
// [BEGIN commented out by Martin before 2019]
//			if ($needForms) {
//				echo '<button type="button" title="suuuu" label="ggg" onClick="methodCombine()">Merge To Structure</botton>';
//			}
//			echo '</form>';
// [END commented out by Martin before 2019]
            ?>
        </tbody>
    </table>

    <?php
        /* JM 2019-03-27: Martin had already killed code to *set* $needForms, and
           the action here is/was vacuous. Threw a warning, of course, which didn't
           show up visibly because Martin was suppressing warnings, of course.
           So I'm taking the liberty of killing it outright.

           BEGIN removed JM 2019-03-27
        */
        // if ($needForms){
        // }
        /* END removed JM 2019-03-27 */
    ?>
</div>

<?php
    /* List of job team. This is via a query on table team where team.inTable=INTABLE_JOB and team.id=job.jobId for the current job.

        * ability to add via /fb/addjobperson.php in an iframe
        * For each person (really companyPerson); there can be multiple lines for the same companyPerson. Table columns follow; the first three are used only once per distinct companyPerson.
          * (no header): link with "edit" icon, which launches /fb/teamcompanyperson.php?teamId=teamId in a fancybox iframe.
          * Name
          * Company
          * Position: position name, which is a link to launch /fb/editjobteamposition.php?teamId=teamId in a fancybox iframe.
          * (no header) Link to customerPerson page, labeled "cp"
          * (no header) Contacts (person or company). See details in code.
          * (no header) active/inactive. A link with text "del". Calls job.php?rwname=$job->getRwName()&act=deletejobteam&teamId=teamId''.
    */
// Separate error messages for this block. The user can still perform other actions on this page!
$error_is_db = false;
$errorTeams = '';

$jobteam = $job->getTeam(0, $error_is_db); // variable pass by reference in method.

if ($error_is_db) { //true on query failed.
    $errorId = '637338846570218802';
    $errorTeams = 'We could not display the Teams for this Job. Database Error.';
    $logger->errorDB($errorId, " getTeam method failled.", $db);
}

?>
<div class="full-box clearfix">
    <h2 class="heading">Job Team</h2>

    <?php /* Ability to add */ ?>
    <a data-fancybox-type="iframe"  id="addJobPerson" class="button add show_hide fancyboxIframe"  href="/fb/addjobperson.php?jobId=<?php echo $job->getJobId(); ?>">Add</a>

    <table class="table table-bordered table-striped">
        <tbody>
        <?php // Display specific "query failled" error for this Tab.
            if ($errorTeams) {
                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorTeams</div>";
            }
        unset($errorTeams);
        ?>
            <tr>
                <th>&nbsp;</th>
                <th>Name</th>
                <th>Company</th>
                <th>Position</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
            </tr>
        <?php
            $cpids = array();

            foreach ($jobteam as $member) {
                $cpids[$member['companyPersonId']][] = $member;
            }

            $jobteam = array(); // >>>00012 multiplexed variable, rebuilt this time as numerically indexed array.
            foreach ($cpids as $cpid) {
                foreach ($cpid as $member) {
                    $jobteam[] = $member;
                }
            }

            $lastCompanyPersonId = 0;
            foreach ($jobteam as $member) {
                $person = new Person ($member['personId'], $user);
                $cp = new CompanyPerson($member['companyPersonId']);
                $comp = $cp->getCompany();
                echo '<tr>';
                    // 3 columns blank except when it is a new companyPerson
                    if ($lastCompanyPersonId != $member['companyPersonId']) {
                        // (no header): link with "edit" icon, which launches /fb/teamcompanyperson.php?teamId=teamId in a fancybox iframe.
                        // teamId is an ID into the team DB table, and identifies job, role, position, person, etc.
                        echo '<td><a data-fancybox-type="iframe"  id="linkEditTeam' .intval($member['teamId']) . '" class="fancyboxIframe" href="/fb/teamcompanyperson.php?teamId=' . intval($member['teamId']) . '">'.
                             '<img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';
                        // "Name"
                        echo '<td><a id="linkPersonTeam' . $person->getPersonId() . '" href="' . $person->buildLink() . '">' .
                            // BEGIN REPLACED 2020-02-27 JM
                            // $person->getFormattedName() .
                            // END REPLACED 2020-02-27 JM
                            // BEGIN REPLACEMENT 2020-02-27 JM
                            ( $person->getFormattedName() ? $person->getFormattedName() : '---' ) .
                            // END REPLACEMENT 2020-02-27 JM
                            '</a></td>';
                        // "Company"
                        echo '<td><a id="linkCompanyTeam' . $comp->getCompanyId() . '" href="' . $comp->buildLink() . '">' .
                            // BEGIN REPLACED 2020-02-27 JM
                            // $comp->getCompanyName() .
                            // END REPLACED 2020-02-27 JM
                            // BEGIN REPLACEMENT 2020-02-27 JM
                            ( $comp->getCompanyName() ? $comp->getCompanyName() : '---' ) .
                            // END REPLACEMENT 2020-02-27 JM
                            '</a></td>';
                    } else {
                        echo '<td>&nbsp</td>';
                        echo '<td>&nbsp</td>';
                        echo '<td>&nbsp</td>';
                    }
                    // "Position": position name, which is a link to launch /fb/editjobteamposition.php?teamId=teamId in a fancybox iframe.
                    echo '<td><a data-fancybox-type="iframe"  id="editJobTeamPosition' .intval($member['teamId']) . '" class="fancyboxIframe" href="/fb/editjobteamposition.php?teamId=' . intval($member['teamId']) . '">' . $member['name'] . '</a></td>';

                    // [BEGIN commented out by Martin before 2019]
                    // echo '<td align="center" width="20"><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/editjobteamposition.php?teamId=' . intval($member['teamId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';
                    // [END commented out by Martin before 2019]

                    // (no header) Link to customerPerson page, labeled "cp"
                    echo '<td>[<a id="linkCompanyPerson' . $cp->getCompanyPersonId() . '" href="' . $cp->buildLink() . '">cp</a>]</td>';

                    /* Contacts (person or company). As of 2019-03, fetches email, phone, & location >>>00006 but seems only to use email.
                         A series of HTML A elements (link) with "mailto:" links. Visible text for each is just "mail".
                         Each sends mail to one of the available email addresses; each has email subject "job-name - SSS# job number".
                     */
                    echo '<td>';
                        // The error messages on query failled are Logged inside the method.
                        $contacts = $cp->getContacts();
                        $sorts = array('email' => array(),'phone' => array(),'location' => array());

                        foreach ($contacts as $contact){
                            if (($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILPERSON) || ($contact['companyPersonContactTypeId'] == CPCONTYPE_EMAILCOMPANY)) {
                                $sorts['email'][] = $contact;
                            }
                            if (($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONEPERSON) || ($contact['companyPersonContactTypeId'] == CPCONTYPE_PHONECOMPANY)) {
                                $sorts['phone'][] = $contact;
                            }
                            if ($contact['companyPersonContactTypeId'] == CPCONTYPE_LOCATION) {
                                $sorts['location'][] = $contact;
                            }
                        }
                        echo '<pre>';
                        foreach ($sorts['email'] as $email) {
                            $string = $job->getName() . ' - SSS#' . $job->getNumber();
                            echo '<a id="linkMailTo' . $email['companyPersonId'] . '" href="mailto:' . $email['dat'] . '?subject=' . rawurlencode($string) . '">mail</a>';
                        }
                        echo '</pre>';
                    echo '</td>';

                    // (no header) active/inactive. A link with text "del" to make this person inactive as a member of this team.
                    echo '<td align="center" id="teamactivecell_' . intval($member['teamId']) .'">';
                        /* [BEGIN MARTIN COMMENT (and commenting-out)]
                         * this is for when we did active/inactive
                           later on might go back to this but for now just delete them
                        $active = (intval($member['active'])) ? 1 : 0;
                        $newstatusid = ($active) ? 0 : 1;
                        echo '<a href="javascript:setTeamMemberActive(' . $member['teamId'] . ',' . intval($newstatusid) . ')"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_active_' . intval($active) . '_24x24.png" width="16" height="16" border="0"></a>';
                        [END MARTIN COMMENT (and commenting-out)]
                        */
                        echo '[<a id="deleteJobTeam' . intval($member['teamId']) . '" href="/job.php?rwname=' . urlencode($job->getRwName()) . '&act=deletejobteam&teamId=' . intval($member['teamId']) .'">del</a>]';
                    echo '</td>';
                echo '</tr>';

                $lastCompanyPersonId = $member['companyPersonId'];
            }
		?>
		</tbody>
    </table>
</div>

<?php
/* List of work orders

    * ability to add a workorder via /fb/jobworkorders.php in an iframe
    * 1 column each to display:
        * ability to edit existing workorder via /fb/workorder.php in an iframe.
        * Type: workOrderDescriptionType display. Linked to workOrder page; if no type assigned, this is non-breaking space, but still linked.
        * Desc: workorder description
        * Genesis: as usual
        * Delivery: as usual
        * Age: as usual
        * Status: workOrder status name
        * Time: time worked so far under this workOrder, displayed in hours (to two decimal places).

*/
?>
<div class="full-box clearfix">
    <?php
        /* kind-of messy English-specific pluralization */
        $plural = ((count($workorders) > 1) || (!(count($workorders)))) ? 's' : '';
    ?>
    <h2 class="heading">Work Order<?php echo $plural; ?></h2>

    <?php /* ability to add a workorder via /fb/jobworkorders.php in an iframe */ ?>
    <a data-fancybox-type="iframe" id="addJobWorkOrders" class="button add show_hide fancyboxIframe" href="/fb/jobworkorders.php?jobId=<?php echo $job->getJobId(); ?>">Add</a>
    <table class="table table-bordered table-striped">
        <tbody>
            <tr>
                <th></th>
                <th>Type</th>
                <th>Desc</th>
                <th>Genesis</th>
                <th>Delivery</th>
                <th>Age</th>
                <th>Status</th>
                <th>Time</th>
<!--                <th>Action</th> -->
            </tr>
            <?php
            // Get content $dts of DB table WorkOrderDescriptionType as an array (canonical representation);
            // get only active types.
            // Then rework that in $dtsi as an associative array indexed by workOrderDescriptionTypeId
            // If getWorkOrderDescriptionTypes() failled, we Log the error message inside the function.
            $dts = getWorkOrderDescriptionTypes();
            $dtsi = Array(); // Added 2019-12-02 JM: initialize array before using it!
            foreach ($dts as $dt) {
                $dtsi[$dt['workOrderDescriptionTypeId']] = $dt;
            }

            $wholeteam = array();
            foreach ($workorders as $workorder) {
                /* BEGIN REPLACED 2020-06-12 JM
                $ret = formatGenesisAndAge($workorder->getGenesisDate(), $workorder->getDeliveryDate(), $workorder->getWorkOrderStatusId());
                // END REPLACED 2020-06-12 JM
                */
                // BEGIN REPLACEMENT 2020-06-12 JM, refined 2020-11-18
                $ret = formatGenesisAndAge($workorder->getGenesisDate(), $workorder->getDeliveryDate(), $workorder->isDone());
                // END REPLACEMENT 2020-06-12 JM

                $genesisDT = $ret['genesisDT'];
                $deliveryDT = $ret['deliveryDT'];
                $ageDT = $ret['ageDT'];
/*
			// [BEGIN commented out by Martin before 2019]
			$genesisDT = '';
			$deliveryDT = '';
			$ageDT = '';

			if ($workorder->getGenesisDate() != '0000-00-00 00:00:00'){
				$dt = DateTime::createFromFormat('Y-m-d H:i:s', $workorder->getGenesisDate());
				$genesisDT = $dt->format('ymd');
			} else {
				$genesisDT = '&mdash;';
			}

			if ($workorder->getDeliveryDate() != '0000-00-00 00:00:00'){
				$dt = DateTime::createFromFormat('Y-m-d H:i:s', $workorder->getDeliveryDate());
				$deliveryDT = $dt->format('M d Y');
			} else {
				$deliveryDT = '&mdash;';
			}

			if ($workorder->getGenesisDate() != '0000-00-00 00:00:00'){
				$dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $workorder->getGenesisDate());
				$dt2 = new DateTime;
				$interval = $dt1->diff($dt2);
				$ageDT = $interval->format('%R%a days');
			} else {
				$ageDT = '&mdash;';
			}

			if (intval($workorder->getWorkOrderStatusId()) == STATUS_WORKORDER_DONE){
				$ageDT = '&mdash;';
			}

			// [END commented out by Martin before 2019]
			*/

//			$deliveryDT = DateTime::createFromFormat('M d Y', $workorder->getDeliveryDate()); // commented out by Martin before 2019
            ?>
                <tr>
                    <?php /* ability to edit existing workorder via /fb/workorder.php in an iframe. */ ?>
                    <td align="center" width="20"><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/workorder.php?workOrderId=<?php echo intval($workorder->getWorkOrderId()); ?>"><img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>

                    <?php /* "Type": WorkOrderDescriptionType name, linked to workOrder page.
                             Displays typename if available.
                             Otherwise (>>>00001: does this ever arise?) a link from a non-breaking space,
                              >>>00006 which is not really a great idea */ ?>
                    <td>
                        <?php
                            if (isset($dtsi[$workorder->getWorkOrderDescriptionTypeId()])) {
                                echo '<a id="workOrderDescriptionTypeName' . $workorder->getWorkOrderId() . '" href="' . $workorder->buildLink() . '">' . $dtsi[$workorder->getWorkOrderDescriptionTypeId()]['typeName'] . "</a>";
                            } else {
                                echo '<a id="linkWorkOrder' . $workorder->getWorkOrderId() . '" href="' . $workorder->buildLink() . '">---</a>';
                            }
                        ?>
                    </td>
                    <?php /*
                            * "Desc": workorder description
                            * "Genesis" date
                            * "Delivery" date
                            * Age
                    */ ?>

                    <td><?php echo htmlspecialchars($workorder->getDescription()); ?></td>
                    <td style='text-align:center;'><?php echo $genesisDT; ?></td>
                    <td style='text-align:center;'><?php echo $deliveryDT; ?></td>
                    <td style='text-align:center;'><?php echo $ageDT; ?></td>

                    <?php /* "Status" */ ?>
                    <td style='text-align:center;'><?php echo $workorder->getStatusName(); ?></td>

                    <?php
                        /* Time: time worked so far under this workOrder, displayed in hours (to two decimal places). */
                        $timeDisplay = 0;
                        if (isset($wototals[$workorder->getWorkOrderId()])) {
                            if (is_numeric($wototals[$workorder->getWorkOrderId()])) {
                                if ($wototals[$workorder->getWorkOrderId()] > 0){
                                
                                    $timeDisplay = ($wototals[$workorder->getWorkOrderId()]/60) ;
                                }
                            }
                        }
                    ?>
                    <td style='text-align:center;'><?php echo number_format($timeDisplay, 2) . '&nbsp;hrs'; ?></td>
<!--                    <td style='text-align:center;'><?php //echo ($timeDisplay==0? '<button type="button" class="btn btn-link deleteWorkOrder" tag="'.$workOrder->getWorkOrderId().'">[del]</button>' : '')?></td>-->
                </tr>
                <?php
                    /* Merge active members of this workorder team into wholeteam */
                    $active = 1;
                    $wholeteam = array_merge($wholeteam, $workorder->getTeam($active)); // [Martin comment:] only active
            }

            $finalteam = array(); // Associative array, indexed by companyPersonId
                                  // Each value is a numerically-indexed array of associative arrays;
                                  //  those have about a dozen members, see WorkOrder.Class.php public function
                                  //  getTeam for details.
            foreach ($wholeteam as $wtkey => $member) {
                //$finalteam[$member['companyPersonId']] = $member; // commented out by Martin before 2019

                // BEGIN Added 2019-12-02 JM: initialize array before using it!
                if (!array_key_exists($member['companyPersonId'], $finalteam)) {
                    $finalteam[$member['companyPersonId']] = Array();
                }
                // END Added 2019-12-02 JM
                $finalteam[$member['companyPersonId']][] = $member;
            }
            ?>
		</tbody>
    </table>
</div>

<?php /* "work order team".
    Each of these came via a query on DB table Team where team.inTable=INTABLE_WORKORDER and team.id=workorder.workorderId
    for some workorder associated with the current job.

    The person's role may edited via /fb/teamcompanyperson.php in an iframe.
*/ ?>
<div class="full-box clearfix">
    <h2 class="heading">Work Order Team</h2>
    <table class="table table-bordered table-striped">
        <tbody>
            <tr>
                <th>&nbsp;</th>
                <th>Name</th>
                <th nowrap>Work&nbsp;Order</th>
                <th>Company</th>
                <th>Position</th>
            </tr>
            <?php


                foreach ($finalteam as $tkey => $personpositions) {
                    foreach ($personpositions as $ppkey => $personposition) {
                        $person = new Person ($personposition['personId'], $user);
                        $cp = new CompanyPerson($personposition['companyPersonId']);
                        $comp = $cp->getCompany();
                        echo '<tr>';
                            $name = (intval($ppkey)) ? ' ' : $person->getFormattedName();
                            /* REPLACED George 2020-03: this link is wrong. intval($member['teamId']) --> will always open the last entry. The right one is: $personposition['teamId']
                            // (JM: $member was just an index in a loop variable above!)
                            echo '<td><a data-fancybox-type="iframe" class="fancyboxIframe" href="/fb/teamcompanyperson.php?teamId=' . intval($member['teamId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';
                            */
                            // BEGIN REPLACEMENT George 2020-03
                            echo '<td><a data-fancybox-type="iframe" id="linkWorkOrderTeam' . intval($personposition['teamId']) . '" class="fancyboxIframe" href="/fb/teamcompanyperson.php?teamId=' . intval($personposition['teamId']) . '"><img src="/cust/' . $customer->getShortName() . '/img/icons/icon_edit_20x20.png" width="16" height="16"></a></td>';
                            // END REPLACEMENT George 2020-03
                            echo '<td><a id="linkWoTeamPerson' . $personposition['teamId'] . '" href="' . $person->buildLink() . '">' . $name . '</a></td>';
                            echo '<td>' . $personposition['workOrderDescription'] . '</td>'; // this is just put in at query time of the getTeam method in workorder class
                            echo '<td><a id="linkWoTeamCompany' . $personposition['teamId'] . '" href="' . $comp->buildLink() . '">' . $comp->getCompanyName() . '</a></td>';
                            echo '<td>' . $personposition['name'] . '</td>';
                        echo '</tr>';
                    }
                }
            ?>
        </tbody>
    </table>
</div>

<?php /* notes & ability to edit add note via /fb/notes.php.

    Recent notes available by a call to /iframe/recentnotes.php in an iframe.
    All notes available by a call to /fb/notes.php in an iframe.
*/ ?>
<div class="full-box clearfix">
    <h2 class="heading">NOTES</h2>
    <iframe width="100%" src="/iframe/recentnotes.php?jobId=<?php echo $job->getJobId(); ?>"></iframe>
    <br/>
    <a data-fancybox-type="iframe" class="fancyboxIframe" id="linkJobNotes" href="/fb/notes.php?jobId=<?php echo $job->getJobId(); ?>">See All Notes</a>
    <p></p>
</div>

<?php /* END OUTDENT */ ?>
    </div><?php /* END div "main-content" */ ?>
</div><?php /* END div "container" */ ?>

<script>
$(document).ready(function() {
    $(".deleteWorkOrder").mousedown(function(){
        var woId=$(this).attr("tag");
        var row=$(this).closest('tr');
        if(woId=="" || woId===null || isNaN(woId) || woId<=0)
        {
            alert ("Data error! The workOrder Id is not valid!" + woId);
            return;
        }
        if(confirm("Are you sure you want to delete the Work Order?")){
            $.post("/ajax/deleteWorkOrder.php", {
                'workOrderId' : woId
            }).done(function(data) {
                console.log(data);
                if(data['status']=='success'){
                    alert('Work Order succesfully deleted!!');
                    //row.remove(); // [CP] it would be "safer" to use the solution from the line below but is faster and enough correct just to remove the row from table
                    location.reload();
                    return; // [CP] not necessary
                }
                if(data['status']=='fail'){
                    alert('WorkOrder delete error with message: ' + data['error']);
                    return; // [CP] not necessary
                }
            })
            .fail(function(data) {
                console.log(data);
                alert( "error" ); // if something goes wrong
            });
        }
    })
})
<?php /* Google maps code >>>00001 not closely studied JM 2019-03-21*/ ?>

<?php /* 2019-12-04: The code as Martin wrote it uses $location, set as a loop variable (!) above.
But what if there are zero iterations of the loop? JM adding code 2019-12-04 to set lat & lng to zero
if no $location. >>>00001 this may not be the best fix, but it beats what was there before. May want to revisit. */
if (isset($location)) {
?>
    var lat = '<?php echo $location->getLatitude();?>';
    var lng = '<?php echo $location->getLongitude();?>';
<?php
} else {
?>
    var lat = 0;
    var lng = 0;
<?php
}
?>

function initialize() {
    var myLatlng = new google.maps.LatLng(lat, lng);
    var mapOptions = {
        zoom: 11,
        center: myLatlng
    }
    var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

    var marker = new google.maps.Marker({
        position: myLatlng,
        map: map,
        title: 'location'
    });
}

function loadScript() {
    var script = document.createElement('script');
    script.type = 'text/javascript';

    /*
    OLD CODE removed 2019-02-04 JM
    script.src = 'https://maps.googleapis.com/maps/api/js?v=3.exp&key=AIzaSyCd0fdGcVRn_MDvtTYAQHskqYWfUbDa3Bo&' +
      'callback=initialize';
    */
    // BEGIN NEW CODE 2019-02-04 JM
    script.src = 'https://maps.googleapis.com/maps/api/js?v=3.exp&key=<?php echo CUSTOMER_GOOGLE_LOADSCRIPT_KEY; ?>&' +
      'callback=initialize';
    // END NEW CODE 2019-02-04 JM
    document.body.appendChild(script);
}
window.onload = loadScript;

function newMap(newlat, newlng) {
    lat = newlat;
    lng = newlng;

    initialize();
}
// George 2021-02-05. We used this because bootstrap overrides jquery
//  and the close icon X from dialog doesn't show properly. Also in Dialog we add the property: closeText: ''
$.fn.bootstrapBtn = $.fn.button.noConflict();
</script>

<?php
include BASEDIR . '/includes/footer.php';
?>