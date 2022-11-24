<?php 
/*  fb/serviceloads.php

    EXECUTIVE SUMMARY: Manage serviceLoads for a given location.

    PRIMARY INPUT: $_REQUEST['locationId'].

    Optional $_REQUEST['act']. Only possible value: 'update', which uses:
        * $_REQUEST['varValue_ServiceLoadVarId'], for the various possible values of ServiceLoadVarId. 
    
    >>>00007 As of 2019-05, there is also commented-out code for $_REQUEST['act']='addjob'.

    George 2020-11-20 Validation summary:

    PRIMARY INPUT: $_REQUEST['locationId'] : required, integer, min 1, for existance in DB table 'location'.

    Methods to display the data in the form: 
    On query failure we Log and display an error message.
        getServiceLoads() - content of DB table ServiceLoad as an array of ServiceLoad objects, ordered by loadName. 
        $location->getServiceLoad() - array of associative arrays, each representing a service load variable for this location. 

    Now the form is displayed. 
    Methods that retrive data from Database: On query failure we Log and display an error message.
        getServiceLoadsVarData() - unique array with loadVarData values. Table serviceloadvar.
        getServiceLoadsVarIds(1); // Valid Ids just for Dropdowns, DB table serviceloadvar.
        getServiceLoadsVarIds(0); // All Valid Ids, DB table serviceloadvar.

    Validation: $act == 'update'
    Assign $_REQUEST to $serviceloads_request.

    get the integer part of each Id from $serviceloads_request, typically like varValue_2, integer part: 2.
    Match all integers Id's from $serviceloads_request with Id's from Database. Log and display an error if not all id's are valid.

    for Dropdowns.
    Check if we have a value of a specific Id and if that option VALUE is legitim (from Database).

    check for max Lenght 64 for each entry.

    If no error -> success.

*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-09-24, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('required', 'locationId');
$v->rule('integer', 'locationId');
$v->rule('min', 'locationId', 1);

if( !$v->validate() ) {
    $errorId = '637365518095660295';
    $logger->error2($errorId, "locationId : " . $_REQUEST['locationId'] ." is not valid. Errors found: ".json_encode($v->errors()));
    $_SESSION["error_message"] = " Invalid locationId in the Url. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe"; // Different Message for end user, type iframe (in error.php).
    header("Location: /error.php");
    die(); 
}

$locationId = intval($_REQUEST['locationId']); // The locationId is already checked before (exists and is an integer), in the validator
// Now we make sure that the row actually exists in DB table 'location'.
if (!Location::validate($locationId)) {
    $errorId = '637365519147344575';
    $logger->error2($errorId, "The provided locationId ". $locationId ." does not correspond to an existing DB person row in location table");
    $_SESSION["error_message"] = "LocationId is not valid. Please check the input!"; // Message for end user
    $_SESSION["errorId"] = $errorId;
    $_SESSION["iframe"] = "iframe";
    header("Location: /error.php");
    die(); 
}

$location = new Location($locationId);

// Stuff used for the form. If we have errors the form is not displayed.

// Table serviceLoad. // serviceLoadId and loadName
$serviceLoads = getServiceLoads($error_is_db); // content of DB table ServiceLoad as an array of ServiceLoad objects.
if ($error_is_db) { //true on query failed. 
    $errorId = '637365586496942658';
    $error = " We could not retrieve the Service Loads Names. Database Error. </br>";
    $logger->errorDB($errorId, 'getServiceLoads() function failed.', $db);
}

// $exist will map serviceLoadVarId to varValue for each row for the location in DB table LocationServiceLoad.
$exist = Array();

$serviceLocation = $location->getServiceLoad($error_is_db); // array of associative arrays, each representing a service load variable for this location. 
if ($error_is_db) { //true on query failed. 
    $errorId = '637369007507539914';
    $error .= "We could not display the Service Loads data for this location. Database Error. </br>";
    $logger->errorDB($errorId, 'getServiceLoad() method failed.', $db);
}

foreach ($serviceLocation as $row) { // it will be an empty array on error. It's fine.
    $exist[$row['serviceLoadVarId']] = $row['varValue'];
}
// END stuff used to display the form.


// Used for Validate the input!
// Different block of error messages. Now the form is displayed.
if (!$error) {
    // Table serviceloadvar. Get only loadVarData.
    // This values ar the values from Dropdowns options (ex: Enclosed Building|Partially Enclosed Building|Open Building)
    $loadsVarData = getServiceLoadsVarData($error_is_db); // unique array with varData values

    if($error_is_db) { //true on query failed.
        $errorId = '637366379352179951';
        $error = "We could not retrieve the Service Loads Values. Database Error. </br>";
        $logger->errorDB($errorId, 'getServiceLoadsVarData() function failed.', $db);
    }

    // Table serviceloadvar. Get only serviceLoadvarIds.
    $loadsVarIdsMulti = getServiceLoadsVarIds(1, $error_is_db); // Valid Ids just for Dropdowns, from DB table serviceloadvar.
    $loadsVarIdsAll = getServiceLoadsVarIds(0, $error_is_db); // All Valid Ids, from DB table serviceloadvar.

    if($error_is_db) { //true on query failed.
        $errorId = '637369801568235914';
        $error.= " We could not retrieve the Service Loads Values. Database Error."; // Same message.
        $logger->errorDB($errorId, 'getServiceLoadsVarIds() function failed.', $db);
    }
}
// End Used for Validation input!

if (!$error && $act == 'update') {
    /* After validating the locationId, delete all serviceLoad data for the locationId, 
        then loop over the POSTed varValue_ServiceLoadVarId values, inserting 
        (locationId, serviceLoadVarId, varValue) into table locationServiceLoad for each.
                  
        Then wait half a second and close the fancybox.
        
        >>>00028: NOT CURRENTLY TRANSACTIONAL, presumably should be. 
        George 2020-09-29. This Querys are now in Location::addServiceLoad(). Still needs TRANSACTIONAL.
    */

    $serviceloads_request = $_REQUEST;
    $idsArrayRequest = Array(); // Just the Ids from REQUEST.

    foreach ($serviceloads_request as  $key => $serviceloads_req) {
        if (($pos = strpos($key, "_")) !== FALSE) {
            $idsArrayRequest[] = substr($key, $pos+1); // get the integer part of Ids from REQUEST. 
        }
    }

    // Check all integers Id's from REQUEST if exists in Database.
    foreach ($idsArrayRequest as $loadId) {
        if (!in_array($loadId, $loadsVarIdsAll)) {
            $errorId = '637417266130110709';
            $error = "Please check input! The given Input is wrong!";
            $logger->error2($errorId, "The provided Id is not good! Wrong Id given: $loadId ");
        }
    }

    if (!$error) {
        // for Dropdowns.
        // Check if we have a value of a specific Id and if that value is legitim.
        foreach ($loadsVarIdsMulti as $loadsVarId) {
            if($serviceloads_request["varValue_" . $loadsVarId] != "") {
                if ( !(in_array($serviceloads_request["varValue_" . $loadsVarId], $loadsVarData) ) ) {
                    $errorId = '637366500052307793';
                    $error = "Please check input! The given Input is wrong!";
                    $logger->error2($errorId, "The provided Value for varValue_$loadsVarId is not legitim! Wrong value given: " .$serviceloads_request["varValue_" . $loadsVarId]);
                }
            }
        }
    }
    unset($loadsVarIdsMulti, $loadsVarIdsAll, $idsArrayRequest, $loadsVarData);

    if (!$error) {
        foreach ($serviceloads_request as $key => $service ) { 
            $v->rule('lengthMax', $key, 64); //check for max Lenght 64 for each entry.
        }
    }

    if (!$v->validate()) {
        $errorId = '637368856943350184';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters. Input must not exceed 64 characters."; // message for End User.
    }
    
    if(!$error) {
        $success = $location->addServiceLoad($serviceloads_request);  // George 2020-09-29. Moved the querys in method addServiceLoad()

        if ($success === false) {
            $errorId = '637368948833090223';
            $error = "Add Service Load failed.";
            $logger->error2($errorId, "addServiceLoad() method failed."); 
        } else {
            ?>
            <script type="text/javascript">    
                setTimeout(function() {
                    parent.$.fancybox.close();
                }, 500);
            </script>
            <?php
            die();
        }
    }
    unset($serviceloads_request);
}

if ($act == 'addjob') {
    /*
    
    if ($customer) {
        if (intval($customer->getCustomerId())) {
            
            $jobId = $customer->addJob();
            
            if (intval($jobId)) {
                
                $job = new Job($jobId);

                $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';
                $name = trim($name);
                if (!strlen($name)) {
                    $name = $job->getNumber();
                }

                $description = isset($_REQUEST['description']) ? $_REQUEST['description'] : '';
                
                $job->update(array('name' => $name,'description' => $description));

                ?>
                <script type="text/javascript">

                parent.woturl = '<?php echo $job->buildLink(); ?>';

                setTimeout(function(){ parent.$.fancybox.close(); }, 1000);
                
                </script>
                
                <?php
            }
        }
    }    
    */
}

// BEGIN REMOVED 2020-02-18 JM, this is already set.
// $locationId = isset($_REQUEST['locationId']) ? $_REQUEST['locationId'] : '';
// END REMOVED 2020-02-18 JM

include '../includes/header_fb.php';
if ($error) {
    echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>
<style>
body, table {
    background: white !important;
}
.form-control {
    width:50%;
}
</style>
<?php
echo '<h1>Service Loads</h1>';
echo '<center>';
    // Display a self-submitting HTML form (structured by a table), with the following content:
    // * hidden: act=update
    // * hidden: locationId
    // * For each distinct serviceLoad, whether already defined for this location or not:
    //     * a header row with just the serviceLoad name
    //     * a row for each value associated with that serviceLoad 
    echo '<form name="note" id="note" action="serviceloads.php" method="post">';
        echo '<table border="1" cellpadding="5" cellspacing="2" width="80%" class="table">';
            echo '<input type="hidden" name="act" value="update">';
            echo '<input type="hidden" name="locationId" value="' . intval($locationId) . '">';
            //$db = DB::getInstance(); already at the Top of the page.
            
            // $exist will map serviceLoadVarId to varValue for each row for the location in DB table LocationServiceLoad.
            //$exist = array();  already at the Top of the page.
            
            // BEGIN Query made more specific JM 2020-02-28
            // No need for this query, Location::getServiceLoad() already have the data.
            // $query = "select * from " . DB__NEW_DATABASE . ".locationServiceLoad where locationId = " . intval($locationId); // old query

            // $query = "select serviceLoadVarId, varValue from " . DB__NEW_DATABASE . ".locationServiceLoad where locationId = " . intval($locationId); 
            // END Query made more specific JM 2020-02-28

            foreach ($serviceLoads as $serviceLoad) { // content of DB table ServiceLoad as an array of ServiceLoad objects.
                echo '<tr>';
                    echo '<th colspan="2" bgcolor="#cccccc">' . $serviceLoad->getLoadName() . '</th>';
                echo '</tr>';
                
                $serviceLoad = new ServiceLoad($serviceLoad->getServiceLoadId());
                $serviceLoadVars = $serviceLoad->getServiceLoadVars();
                
                foreach ($serviceLoadVars as $serviceLoadVar) {
                 
                    $val = isset($exist[$serviceLoadVar->getServiceLoadVarId()]) ? $exist[$serviceLoadVar->getServiceLoadVarId()] : '';
  
                    echo '<tr>';
                        // Header row: service load variable name
                        echo '<td>' . $serviceLoadVar->getLoadVarName() . '</td>';
                        // If the variable is of type SERVLOADVARTYPE_MULTI, an HTML SELECT with name varValue_ServiceLoadVarId, 
                        //  initialized appropriately if the value is already set, and offering an appropriate name &  
                        //  (text) value for each possibility. If no pre-existing value, this initially shows "--choose--", 
                        //  with an empty string as value.
                        // There is a special case if variable is of type SERVLOADVARTYPE_MULTI but there are no possible values set in the DB: 
                        //  then we fall back to handling it as open-ended text.             
                        if ($serviceLoadVar->getLoadVarType()  == SERVLOADVARTYPE_MULTI) {
                            $data = $serviceLoadVar->getLoadVarData();
                     
                            $parts = explode("|", trim($data));
                            if (count($parts)) {
                                echo '<td><select class="form-control input-sm" id="selectVarValue_' . intval($serviceLoadVar->getServiceLoadVarId()) . '" 
                                name="varValue_' . intval($serviceLoadVar->getServiceLoadVarId()) . '"><option value="">--choose--</option>';
                                    foreach ($parts as $part) {

                                        $selected = ($val == $part) ? ' selected ' : '';
                                        echo '<option value="' . htmlspecialchars($part) . '" ' . $selected . '>' . htmlspecialchars($part) . '</option>';
                                       
                                    }
                                echo '</select></td>';
                            } else {
                                echo '<td><input class="form-control input-sm" type="text" id="varValue_' . intval($serviceLoadVar->getServiceLoadVarId()) . '"
                                name="varValue_' . intval($serviceLoadVar->getServiceLoadVarId()) . '" value="' . htmlspecialchars($val) . '" size="16" maxlength="64"></td>';
                            }                            
                        } else {
                            // Not SERVLOADVARTYPE_MULTI. The single value for that variable: open-ended text
                            echo '<td><input type="text" class="form-control input-sm" id="singleVarValue_' . intval($serviceLoadVar->getServiceLoadVarId()) . '"
                            name="varValue_' . intval($serviceLoadVar->getServiceLoadVarId()) . '" value="' . htmlspecialchars($val) . '" size="16" maxlength="64"></td>';
                        }
                    echo '</tr>';     
                }
                
                //define("SERVLOADVARTYPE_SINGLE",2); // Commented out by Martin before 2019
            }
            
            echo '<tr>';
                echo '<td colspan="2"><input type="submit"  class="btn btn-secondary mx-auto" value="update"></td>';
            echo '</tr>';
        echo '</table>';
    echo '</form>';
echo '</center>';

include '../includes/footer_fb.php';

?>