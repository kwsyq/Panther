<?php 
/*  location.php

    EXECUTIVE SUMMARY: top-level page to add, view or edit a location.
    On window load, this loads & appends Google map script. Via a callback, 
     that sets the map to the latitude & longitude for the location.
     
    We can arrive here several ways: 
    * From the main Panther page to add a location that is not associated with anything in particular.
    * From a job, company, or person (hereafter "jcp") page to add, view or edit a location associated with that page, or
        (initially arriving here the same way as for "add") to search for an existing location to "add"
        to that job, company, or person.
        * NOTE that the location can already be linked via DB tables job, companyLocation, personLocation to other
          jobs, companies and/or people besides the jcp.
    * From either main Panther page or a jcp page to edit an existing location. 
        
    Additional functionality: this page can load fb/serviceloads.php to associate a service load with this location.
    
    I rewrote this file so heavily 2019-11 that I see no point to retaining history of changes inline. You can consult SVN if you really need that history. 

    PRIMARY INPUT: If optional $_REQUEST['locationId'] is present, it functions as a primary input, 
    but this page can also be used to build a new location. (Title is set appropriately as "Location" or "Add location".)

    SECONDARY INPUTS: 
    * $_REQUEST['jobId'], $_REQUEST['personId'], $_REQUEST['companyId'] indicate a jcp page to offer to go back to; there is also an intent
        to connect this location to that jcp if the connection is not already there.
    * $_REQUEST['q'] - search string, to match existing locations (added 2019-11)
    * $_REQUEST['updated'] - Boolean (0 or 1) for self-call *after* handling $act=='update' (added 2019-11)
    * $_REQUEST['cloned'] - Boolean (0 or 1): if true, then within the sequence of self-submits of this page we have cloned the location.
                            That means we don't need to offer to clone again. (added 2019-11)
                            "cloned" is rather a shorthand here. What it really means is that they have resolved the issue of possibly
                            needing to clone. If they resolve it by saying "Yes, I want to change this for everything that is linked"
                            then technically there is no cloning involved, but we still set "cloned" to true.
                            

    Optional $_REQUEST['act']. Possible values: 
    * 'update'. On update, besides updating location table, we can update job, personLocation, companyLocation tables.
      The following addditional inputs are potentially relevant:
       * $_REQUEST['name']
       * $_REQUEST['address1']
       * $_REQUEST['address2']
       * $_REQUEST['suite']
       * $_REQUEST['city']
       * $_REQUEST['state']
       * $_REQUEST['country']
       * $_REQUEST['postalCode']
       * $_REQUEST['latitude']
       * $_REQUEST['longitude']
       * $_REQUEST['googleGeo'] //Unused
       * $_REQUEST['customerId']
       * $_REQUEST['jobId']
       * $_REQUEST['personId']
       * $_REQUEST['companyId']
    * 'search': use $_REQUEST['q'] as the basis for a search on locations.
    
    >>>00001 It would be nice that as this self-submits it could consistently use POST. The trickiest part of that to change would be where it does 
    header("Location: $url"); and doesn't even make a round trip to the client.
*/

include './inc/config.php';
include './inc/access.php';

// ADDED by George 2020-09-10, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;
$error_is_db = false;
$db = DB::getInstance();

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

$v->rule('integer', ['locationId', 'jobId', 'personId', 'companyId']);
$v->rule('optional', ['locationId', 'jobId', 'personId', 'companyId']);


$locationId = isset($_REQUEST['locationId']) ? intval($_REQUEST['locationId']) : 0;
$jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;
$personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
$companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
$afterUpdate = isset($_REQUEST['updated']) ? (!!$_REQUEST['updated']) : false;
$cloned = isset($_REQUEST['cloned']) ? (!!$_REQUEST['cloned']) : false;


// If not 0 (for new one), check for existance. Came from edit location.
if($locationId) {
    if (!Location::validate($locationId)) {
        $errorId = '637356792943754941';
        $logger->error2($errorId, "The provided locationId ". $locationId ." does not correspond to an existing DB row in location table");
        $_SESSION["error_message"] = "LocationId is not valid. Please check the input!"; // Message for end user
        $_SESSION["errorId"] = $errorId;
        header("Location: /error.php");
        die(); 
    }
}

$location = new Location($locationId); // We don't validate before doing this, but if $locationId == 0 we will identify this 
                                       // as "no such location exists yet". That's OK.

if($locationId>0 && $act=='deleteLocation'){
    if(!$location->delete()){
        $error="Cannot delete location";
    } else {
        header("Location: /");
        die();
    }
}
// We should never have more than one of $jobId, $personId, $companyId set, since that amounts to what page we came from (the jcp).
$coming_from = array();

if ($jobId) {
    $coming_from[] = 'jobId';
    if (!Job::validate($jobId)) {
        $errorId = '637354235604168787';
        $logger->error2($errorId, "The provided jobId ". $jobId ." does not correspond to an existing DB row in job table");
        $_SESSION["error_message"] = "JobId is not valid. Please check the input!"; // Message for end user
        $_SESSION["errorId"] = $errorId;
        header("Location: /error.php");
        die(); 
    }

    $job = new Job($jobId);
   
    }
if ($personId) {
    $coming_from[] = 'personId';
    if (!Person::validate($personId)) {
        $errorId = '637354246505211494';
        $logger->error2($errorId, "The provided personId ". $personId ." does not correspond to an existing DB row in person table");
        $_SESSION["error_message"] = "PersonId is not valid. Please check the input!"; // Message for end user
        $_SESSION["errorId"] = $errorId;
        header("Location: /error.php");
        die(); 
    }

    $person = new Person($personId);

    }
if ($companyId) {
    $coming_from[] = 'companyId';
    if (!Company::validate($companyId)) {
        $errorId = '637354246957250504';
        $logger->error2($errorId, "The provided companyId ". $companyId ." does not correspond to an existing DB row in company table");
        $_SESSION["error_message"] = "CompanyId is not valid. Please check the input!"; // Message for end user
        $_SESSION["errorId"] = $errorId;
        header("Location: /error.php");
        die(); 
    }

    $company = new Company($companyId);
    }

if (count($coming_from) > 1) {
    $errorId = '1574329969';
    $error = "location.php should have only one of jobId, personId, companyId nonzero; has jobId=$jobId, personId=$personId, companyId=$companyId.";
    $logger->error2($errorId, $error);

    $_SESSION["error_message"] = trim( "Must have exactly one of jobId, companyId, personId; input gave: " .
    ($jobId ? 'jobId ' : '') . ($companyId ? 'companyId ' : '') . ($personId ? 'personId ' : '') ); // Message for end User on error page.

    $_SESSION["errorId"] = $errorId;
    header("Location: /error.php");
    die(); 
    // >>>00002 might want to bail out & inform user, but this probably will never happen, so logging is presumably enough. 
}

$jcpExists = !!count($coming_from); // "jcp" meaning any job/company/person we would navigate back to.

$jcpType = $jobId ? 'job' :
           ($personId ? 'person' :
           ($companyId ? 'company' : ''));

// Get $linkedJobs/$linkedPersons/$linkedCompanies arrays at earliest opportunity.
$linkedJobs = Array();
$linkedCompanies = Array();
$linkedPersons = Array();


if (intval($location->getLocationId())) {

    // Job
    if ($jobId) {
        $linkedJobs = $location->getJobUsage($error_is_db);

        if($error_is_db) { //true on query failed.
            $errorId = '637356821025155975';
            $error = "We could not display the Locations associated with this Job. Database Error. ";
            $logger->errorDB($errorId, 'getJobUsage method failed.', $db);
}
    }

    // Company
    if ($companyId) {
        $linkedCompanies = $location->getCompanyUsage($error_is_db);
    
        if($error_is_db) { //true on query failed.
            $errorId = '637356857765240553';
            $error = "We could not display the Locations associated with this Company. Database Error. ";
            $logger->errorDB($errorId, 'getCompanyUsage method failed.', $db);
        }
    }

    // Person
    if ($personId) {
        $linkedPersons = $location->getPersonUsage($error_is_db);

        if($error_is_db) { //true on query failed.
            $errorId = '637356861711386780';
            $error = "We could not display the Locations associated with this Person. Database Error. ";
            $logger->errorDB($errorId, 'getPersonUsage method failed.', $db);
        }
    }
}

// If NOT cloned, and there is more than one linked job/company/person, then
//  we don't want them to be able to modify until they have resolved whether they want to clone
//  or to apply the changes in a way that affects all linked jobs/companies/persons.

$countAlreadyLinked = count($linkedJobs) + count($linkedPersons) + count($linkedCompanies);
$jcpAlreadyLinked = false; // "jcp" meaning any job/company/person we would navigate back to.
if ($jcpExists) {
    if ($jobId) {
        foreach ($linkedJobs AS $linkedJob) {
            if ($jobId == $linkedJob['jobId']) {
                $jcpAlreadyLinked = true;
                break;
            }
        }
    } else if ($personId) {
        foreach ($linkedPersons AS $linkedPerson) {
            if ($personId == $linkedPerson['personId']) {
                $jcpAlreadyLinked = true;
                break;
            }
        }
    } else if ($companyId) {
        foreach ($linkedCompanies AS $linkedCompany) {
            if ($companyId == $linkedCompany['companyId']) {
                $jcpAlreadyLinked = true;
                break;
            }
        }
    }
} // END if ($jcpExists) 

$readonly = !$cloned && (
                $countAlreadyLinked > 1 // if 2 or more were already linked, then certainly we need to sort out cloning.
                || ($jcpExists && ($countAlreadyLinked == 1) && !$jcpAlreadyLinked) // the jcp pushes us over the "2 or more" edge
            );
$readonly_attribute = $readonly ? ' readonly' : '';

$crumbs = new Crumbs(null, $user);
    

// Check States
$states = allStates(); // Array with all the States.
$state_abbreviations = array();

foreach($states as $state){
    $state_abbreviations[] = $state[1]; // Array with valid State Abbreviations.
}
//End check states

if (!$error && $act == 'update') {
    // Besides updating location table, if there is a jcp we update job, companyLocation, or personLocation table.
    $jcpAlreadyAssociated = false; // whether the jcp is already associated with this location
    // BEGIN ADDED 2020-03-17 JM

    $location_request = Array();

    $v->rule('required', ['address1', 'city', 'postalCode']);
    $v->rule('numeric', ['customerId', 'locationId']);
    $v->rule('latitude', 'latitude');
    $v->rule('longitude', 'longitude');
    $v->rule('in', 'state', $state_abbreviations); // state value must be in array.


    if(!$v->validate()) {
        $errorId = '637356906844634442';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";
    } else {
        // If the input is longer than the field from Database, Location tabel, we take care of it in Location.class::Setters($val).
        // For each element of the array $location_request (except: latitude and longitude. Are checked and Log for invalid format).
        $location_request['name'] = isset($_REQUEST['name'])?$_REQUEST['name']:''; // already required

        $location_request['address1'] = $_REQUEST['address1']; // already required

        $location_request['address2'] = isset($_REQUEST['address2']) ? $_REQUEST['address2'] : '';
        $location_request['suite'] = isset($_REQUEST['suite']) ? $_REQUEST['suite'] : '';

        $location_request['city'] = $_REQUEST['city']; // already required

        $location_request['state'] = isset($_REQUEST['state']) ? $_REQUEST['state'] : '';
        $location_request['country'] = isset($_REQUEST['country']) ? $_REQUEST['country'] : '';

        $location_request['postalCode'] = $_REQUEST['postalCode']; // already required

        $location_request['latitude'] = isset($_REQUEST['latitude']) ? $_REQUEST['latitude'] : '';
        $location_request['longitude'] = isset($_REQUEST['longitude']) ? $_REQUEST['longitude'] : '';

        //$location_request['googleGeo'] = isset($_REQUEST['googleGeo']) ? $_REQUEST['googleGeo'] : ''; //Unused
        $location_request['customerId'] = isset($_REQUEST['customerId']) ? $_REQUEST['customerId'] : '';
    // END ADDED 2020-03-17 JM
    
    if (intval($location->getLocationId())) {
        // Existing location, perform any update & analyze whether we want to link a jcp. 

        /* BEGIN REPLACED 2020-03-17 JM
        // Pass all the arguments to $location->update, let it take the ones it cares about.
        $location->update($_REQUEST);
        // END REPLACED 2020-03-17 JM
        */
        // BEGIN REPLACEMENT 2020-03-17 JM
        
            $success = $location->update($location_request);
            if (!$success) {
                $error = "Update location failed";
           $logger->error2('637215239035123152', "Update location failed");
       }

        // END REPLACEMENT 2020-03-17 JM   
        
        // Update can have a side effect of associating an existing location (rather than a brand new one) to a job/person/company;
        //  we want to head that off if the assocation has already been made.
        
        // Check to see whether job/person/company is already associated with this location
        // NOTE that we should never have more than one of the three.
        // Unfortunately, Job handles getting locations a bit differently from Person & Company.
        // Beginning with v2020-3, based on http://bt.dev2.ssseng.com/view.php?id=153, there can actually be only one location for a given job,
        //  but we've left the code here unchanged, since there is really no need to mess with it.
            if (!$error) {
        if (intval($jobId)) {
                    // Job Locations
                    $jobLocations = $job->getLocations($error_is_db);  // an array of location objects

                    if($error_is_db) { //true on query failed.
                        $errorId = '637358560725714055';
                        $error = 'We could not display the Locations for this Job. Database Error.';
                        $logger->errorDB($errorId, 'Job getLocations method failed.', $db);
                    }

            foreach($jobLocations AS $jobLocation) {
                if (intval($jobLocation->getLocationId()) == intval($locationId)) {
                    $jcpAlreadyAssociated = true;
                    break;
                }
            }
        } else if (intval($companyId)) {
                    // Company Locations
                    $companyLocations = $company->getLocations($error_is_db); // an array of associative arrays
                    if($error_is_db) { //true on query failed.
                        $errorId = '637358564939278865';
                        $error = 'We could not display the Locations for this Company. Database Error.';
                        $logger->errorDB($errorId, 'Company getLocations method failed.', $db);
                    }

            foreach($companyLocations AS $companyLocation) {
                if (intval($companyLocation['locationId']) == intval($locationId)) {
                    $jcpAlreadyAssociated = true;
                    break;
                }
            }
        } else if (intval($personId)) {
                    // Person Locations
                    $personLocations = $person->getLocations($error_is_db); // an array of associative arrays
                    if($error_is_db) { //true on query failed.
                        $errorId = '637358565919975100';
                        $error = 'We could not display the Locations for this Person. Database Error.';
                        $logger->errorDB($errorId, 'Person getLocations method failed.', $db);
                    }

            foreach($personLocations AS $personLocation) {
                if (intval($personLocation['locationId']) == intval($locationId)) {
                    $jcpAlreadyAssociated = true;
                    break;
                }
            }
        }
            }

    } else {
        // Create the brand new location
        /* BEGIN REPLACED 2020-03-17 JM
        $location = Location::addLocation($_REQUEST);
        // END REPLACED 2020-03-17 JM
        */
        // BEGIN REPLACEMENT 2020-03-17 JM
        $location = Location::addLocation($location_request);
        // END REPLACEMENT 2020-03-17 JM
        
        // location is brand new, so $jcpAlreadyAssociated is necessarily false, and we will want to link jcp.
        // Also note: if somehow addLocation fails, $location===false.
    }
    }
    // BEGIN ADDED 2020-03-17 JM
    unset($location_request, $state_abbreviations); // don't let this "leak", limit its scope
    // END ADDED 2020-03-17 JM
    
    if ($location===false) { // this check added 2020-03-17 JM
        // >>>00002 should presumably report something in the UI. Looks like that is still not addressed 2020-04-23 JM
        // This (and similar issues elsewhere) will probably require one of two approaches:
        //  1) An interstitial page before reloading location.php
        //  2) My (JM) preference: use a cookie to put a message on the page after reload. See the use of the 'stamps_message'
        //     cookie in _admin/employee/stamps.php for an example.

        // If query failled on Insert -> AddLocation. Prevent further errors on page.
        $errorId = '637365400668827780';
        $logger->errorDB($errorId, "We could not Add new Location. Database Error", $db);
        $_SESSION["error_message"] = "We could not Add new Location. Database Error. Please contact an administrator!"; // Message for end user
        $_SESSION["errorId"] = $errorId;
        header("Location: /error.php");
        die();
    } else {
        // Either a preexisting location or one we just added, and we want to link the jcp 
        if (intval($location->getLocationId()) && !$jcpAlreadyAssociated) {
            if (intval($jobId)) {
                //$db = DB::getInstance(); Declared at the top of the page.
                /* BEGIN REPLACED JM 2020-05-11: for http://bt.dev2.ssseng.com/view.php?id=153
                $query  = "insert into " . DB__NEW_DATABASE . ".jobLocation (jobId, locationId, jobLocationTypeId) values (";
                $query .= " " . intval($jobId) . " ";
                $query .= " ," . intval($location->getLocationId()) . " ";
                $query .= " ," . intval(JOBLOCTYPE_SITE) . ") ";                    
                // END REPLACED JM 2020-05-11
                */    
                // BEGIN REPLACEMENT JM 2020-05-05: for http://bt.dev2.ssseng.com/view.php?id=153
                $query  = "UPDATE " . DB__NEW_DATABASE . ".job ";
                $query .= "SET locationId=" . intval($location->getLocationId()) . " ";
                $query .= "WHERE jobId=" . intval($jobId) . ";";
                // END REPLACEMENT JM 2020-05-05
                
                $success = $db->query($query);
                if (!$success) {
                    $error = "Insert failed connecting new location to job.";
                    $logger->errorDb('1574275565', "Insert failed connecting new location to job", $db);
                } else {
                    $logger->info2('637371544867143260', "update Job location => action success. Affected rows: ". $db->affected_rows);
                }    
            } else if (intval($personId)) {                
                // [Martin comment:] still need to determine (ask usewr) job location type id
                // JM 2019-11-21: >>>00032 What Martin seems to have meant is that we need a way to set addressType
                //  At present, that doesn't seem to be used at all.
                //$db = DB::getInstance(); Declared at the top of the page.       
                $query  = "INSERT INTO " . DB__NEW_DATABASE . ".personLocation (personId, locationId) VALUES (";
                $query .= " " . intval($personId) . " ";
                $query .= " ," . intval($location->getLocationId()) . ") ";
                $success = $db->query($query);
                if (!$success) {
                    $error = "Insert failed connecting new location to person.";
                    $logger->errorDb('1574275576', "Insert failed connecting new location to person", $db);
                }    
            } else if (intval($companyId)){                
                // [Martin comment:] still need to determine (ask usewr) job location type id
                // JM 2019-11-21: >>>00032 What Martin seems to have meant is that we need a way to set locationTypeId
                // to something other than 0; at present, you do that only on the company.php page. Seems relevant to
                // be able to set this to the value meaning "accounts payable"
                //$db = DB::getInstance(); Declared at the top of the page.        
                $query  = "INSERT INTO " . DB__NEW_DATABASE . ".companyLocation (companyId, locationId) VALUES (";
                $query .= " " . intval($companyId) . " ";
                $query .= " ," . intval($location->getLocationId()) . ") ";
                $success = $db->query($query);
                if (!$success) {
                    $error = "Insert failed connecting new location to company";
                    $logger->errorDb('1574275587', "Insert failed connecting new location to company", $db);
                }    
            }
        } // END if (intval($location->getLocationId()) && !$jcpAlreadyAssociated)
        if (!$error) {
        
        // Reload page. If we have a jcp, the new page will offer to navigate back to where we came from.
        $url = "/location.php?locationId=" . intval($location->getLocationId()) . "&updated=1";
        if ($cloned) {
            $url .= "&cloned=1";
        }
        if (intval($jobId)) {
            $url .= "&jobId=$jobId";
        } else if (intval($companyId)) {
            $url .= "&companyId=$companyId";
        } else if (intval($personId)) {
            $url .= "&personId=$personId";
        }
        
        header("Location: $url");
            die();
    }
    }
} // END if ($act == 'update')

if (!$error && $act == 'search') {
    $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';
    $q = trim($q); // we truncate for DB in Search::searchLocations().
    $q_long_enough = strlen($q) >= 2;
    if ($q_long_enough) {
        $search = new Search('mypage', $user);

        $locationsFromSearch = $search->searchLocations($q, $error_is_db);
        if($error_is_db){ // True on query failed.
            $errorId = '637360406292220769';
            $error= 'Searching for matching locations failed.';
            $logger->errorDB($errorId, 'Searching for matching locations failed.', $db);
        }
    } else {
        $locationsFromSearch = Array();
    }
}

include BASEDIR . '/includes/header.php';
if ($error) {
    echo "<div class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
// for debugging purposes: echo inputs into page
echo "<!-- \$jobId = $jobId, \$personId = $personId, \$companyId = $companyId".
     " \$locationId = $locationId, ". 
     " \$afterUpdate = ". ($afterUpdate ? '1' : '0') . ', '.
     " \$cloned = ". ($cloned ? '1' : '0') .
     (isset($q) ? ", \$q='$q'" : ''). 
     " -->\n"; 

?>
<?php

// Add title
if ($locationId) {
    $address1 = $location->getAddress1();
}
echo "<script>\ndocument.title ='" . 
    ($locationId ? ('Location: ' . ($address1 ? (' ' . $address1) : '(no street address)')) : 'Add Location') . 
    "';\n</script>\n"; 
unset ($address1);    
?>

<script>
<?php /* Google maps code >>>00001 not closely studied JM 2019-03-21 */ ?>
function initialize() {
    const geocoder = new google.maps.Geocoder();
    var myLatlng = new google.maps.LatLng(<?php echo ($locationId && $location ? $location->getLatitude() : 0);?>, 
                   <?php echo ($locationId && $location ? $location->getLongitude() : 0);?>);
    var mapOptions = {
        zoom: 14, 
        center: myLatlng,
        gestureHandling: 'greedy'
    }
    var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
    

      var marker = new google.maps.Marker({
        position: myLatlng,
        map: map
      });
      // double click event
      google.maps.event.addListener(map, 'dblclick', function(e) {
        var positionDoubleclick = e.latLng;
        marker.setPosition(positionDoubleclick);

        $("#latitude").val(e.latLng.lat().toFixed(6));
        $("#longitude").val(e.latLng.lng().toFixed(6));

        map.setCenter(marker.getPosition());
        map.setZoom(14);

        geocoder
        .geocode({ location: e.latLng })
        .then((response) => {
          if (response.results[0]) {
            var s=response.results[0].address_components;
            console.log(s);
            $("#address1").val(s[0].short_name + " " + s[1].short_name);

            $("#city").val(s[2].short_name);

            $("#state").val(s[4].short_name);
            let obj = s.find(o => o.types[0] === 'postal_code');
            console.log(obj);
            if(obj!==undefined)
                $("#postalCode").val(obj.short_name);

          } else {
            alert("No results found");
          }
        })
    .catch((e) => window.alert("Geocoder failed due to: " + e));
        // if you don't do this, the map will zoom in
        //e.originalEvent.stopPropagation();
      });

}

function loadScript() {
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://maps.googleapis.com/maps/api/js?v=3.exp&key=<?php echo CUSTOMER_GOOGLE_LOADSCRIPT_KEY.'&'; ?>callback=initialize';
    document.body.appendChild(script);
}

window.onload = loadScript;

<?php /* >>>00007: since the following is now a no-op, and appears to be uncalled, we can probably just delete it.
         For now, I've left it alone just in case it is called in some manner I don't see. - JM */ ?>
function submitSite() {
    <?php /* BEGIN commented out by Martin before 2019 */ ?>
    //if (document.upInfo.cmpCompany.value == "") {
    //    document.upInfo.cmpCompany.focus();
    //    alert("please enter the company name");
    //    return false;
    //}
    //return true;
    <?php /* END commented out by Martin before 2019 */ ?>    
}
</script>
<style>
.error {
    color: #FF0000;
}
</style>
<div class="container clearfix"> 
    <div class="main-content">
        <div class="full-box clearfix">
            <h2 class="heading"><?php echo ($location->getLocationId()) ? 'Location' : 'Add Location'; ?></h2>
            <?php
                if ($act == 'search') {
                    // Results of search
                    if (!$q_long_enough) {
                        echo '<p style="color:red">Search string must be at least 2 characters.</p>'."\n";
                    } else if (! count($locationsFromSearch)) {
                        echo '<p style="color:red">No matching locations.</p>'."\n";
                    } else {
                        echo "<div id=\"context-div\" data-jobid=\"$jobId\" data-personid=\"$personId\" data-companyid=\"$companyId\" style=\"display:none\"></div>\n";
                        echo "Click any of the following locations to load it.<br />";
                        foreach ($locationsFromSearch AS $loc) {
                            echo '<a id="existingLocation' . $loc->getLocationId() . '" class="existing-location" style="cursor:pointer" '.
                                 'data-locationid="'. $loc->getLocationId() .'">'. 
                                 htmlspecialchars($loc->getFormattedAddress())."</a><br />\n";
                        }
                    }
                }
            ?>
            <script>
            // Action for clicking on any of the existing locations above that were returned from a search.
            // NOTE that the form here is never visible: we create this form as an easy way to do a POST.
            $('a.existing-location').click(function() {
                let $this = $(this);
                let $loadLocationForm = $('<form id="load-location" style="display:none" method="post" action="location.php"></form>');
                let jobId = $('#context-div').data('jobid');
                let personId = $('#context-div').data('personid');
                let companyId = $('#context-div').data('companyid');
                $loadLocationForm.append('<input type="hidden" name="locationId" value="' + $this.data('locationid') + '">');
                if (jobId) {
                    $loadLocationForm.append('<input type="hidden" name="jobId" value="' + jobId + '">');
                }
                if (personId) {
                    $loadLocationForm.append('<input type="hidden" name="personId" value="' + personId + '">');
                }
                if (companyId) {
                    $loadLocationForm.append('<input type="hidden" name="companyId" value="' + companyId + '">');
                }
                $loadLocationForm.append('<input type="submit" id="load-location-submit">');
                $loadLocationForm.appendTo('body');
                $('#load-location-submit').click();
            });
            </script>
            <?php    
            ?>
            <table>
                <tbody>
                    <tr>
                        <td>
<?php
// BEGIN OUTDENT 1                            
echo '<center>'."\n";
echo '<table border="0" cellpadding="0" cellspacing="0" class="table table-sm table-borderless">'."\n";
    echo '<tr>';
        echo '<td colspan="2"><h1>Address/Map</h1></td>';
    echo '</tr>'."\n";
    
    // If we just updated for a jcp, allow further editing but give then an easy way back to the jcp page.
    if ( $afterUpdate && ($jobId || $companyId || $personId) ) {
        if ($jobId) {
            echo '<tr>';
                echo '<td colspan="2"><a id="linkReturnToJob" href="'.$job->buildLink().'">Return to job: ';
                echo $job->getName(). ' (' .$job->getNumber().')</a></td>'; 
            echo '</tr>'."\n";
        }
        if ($companyId) {
            echo '<tr>';
                echo '<td colspan="2"><a id="linkReturnToCompany" href="'.$company->buildLink().'">Return to company: ';
                echo $company->getCompanyName().'</a></td>'; 
            echo '</tr>'."\n";
        }
        if ($personId) {
            echo '<tr>';
                echo '<td colspan="2"><a id="linkReturnToPerson" href="'.$person->buildLink().'">Return to person: ';
                echo $person->getFormattedName(true).'</a></td>'; 
            echo '</tr>'."\n";
        }
    }
    if (!intval($location->getLocationId())) {
        // UI to search for an existing location
        echo '<tr>'."\n";
            echo '<form name="find-existing-location" id="findExistingLocation" method="post" action="location.php">'."\n";
            echo '<input type="hidden" name="customerId" value="' . intval($customer->getCustomerId()) . '" />'."\n";
            echo '<input type="hidden" name="act" value="search" />'."\n";
            echo '<input type="hidden" name="jobId" value="' . intval($jobId) . '" />'."\n";
            echo '<input type="hidden" name="personId" value="' . intval($personId) . '" />'."\n";
            echo '<input type="hidden" name="companyId" value="' . intval($companyId) . '" />'."\n";
            echo '<input id="submit-search" type="submit" style="display:none" />'."\n";
            if ($act == 'search') {
                $label = 'Existing location (search again)';
            } else {
                $label = 'Existing location (enter search term)';
            }
            // We separate out "visible-submit-search" because we want the effect of a normal
            //  form submit button, but we want something we cannot style into an '<input type="submit"...> 
            echo "<td colspan=\"2\">$label&nbsp;".
                 '<input type="text" name="q" id="qSearch" value="" size="40" maxlength="64">&nbsp;'.
                 '<button id="visible-submit-search"><image src="/cust/' . $customer->getShortName() . '/img/button/button_search_32x32.png" border="0" height="16" width="16"></button></td>'."\n";
            echo '</form>'."\n";
        echo '</tr>'."\n";
?>
<script>
    $('#visible-submit-search').click(function() {
        $('#submit-search').click();
    });
</script>
<?php        
    }

    if ($readonly) {
        echo '<tr class="cloning">'."\n";
            echo '<td colspan="2">'."\n";
               echo 'This location is (or will be) linked to more than one job/company/person, so it is read-only. ' . 
                    'If you want to modify it, first indicate which jobs/companies/persons changes will apply to.';
            echo '</td>'."\n";
        echo '</tr>'."\n";
        
        // The current jcp may not even yet be linked to this location (e.g. if we just selected an existing location from a search, and we
        //   are planning to make that linkage) so it may not be in $linkedJobs/$linkedCompanies/$linkedPersons. 
        //   Also, it needs special handling in any case (differently styled, and you cannot uncheck it).
        if ($jobId) {
            echo '<tr class="cloning"><td colspan="2">'."\n";
                echo '<input id="include-in-clone-job-'. $jobId. '" type="checkbox" '.
                     'name="include-in-clone" value="job-'. $jobId.'"'.
                     ' checked disabled >&nbsp;'.
                     '<label for="include-in-clone-job-'. $jobId. '">'.
                     '<b>[J] '. $job->getName(). '&nbsp;(' . $job->getNumber(). ')</b>'.
                     '</label>'.
                     "\n";
            echo "</td></tr>\n";           
        } else if ($personId) {
            echo '<tr class="cloning"><td colspan="2">'."\n";
                echo '<input id="include-in-clone-person-'. $personId. '" type="checkbox" '.
                     'name="include-in-clone" value="person-'. $personId.'"'.
                     ' checked disabled >&nbsp;'. 
                     '<label for="include-in-clone-person-'. $personId. '">'.
                     '<b>[P] '. $person->getFirstName() . '&nbsp;' . $person->getLastName(). '</b>'.
                     '</label>'.
                     "\n";
            echo "</td></tr>\n";           
        } else if ($companyId) {
            echo '<tr class="cloning"><td colspan="2">'."\n";
                echo '<input id="include-in-clone-company-'. $companyId. '" type="checkbox" '.
                     'name="include-in-clone" value="company-'. $companyId.'"'.
                     ' checked disabled>&nbsp;'. 
                     '<label for="include-in-clone-company-'. $companyId. '">'.
                     '<b>[C] '. $company->getCompanyName() .'</b>'.
                     '</label>'.
                     "\n";
            echo "</td></tr>\n";           
        }
        
        // And now display the rest of them, with checkboxes initially checked:
        foreach($linkedJobs AS $linkedJob) {
            $linkedJobId = $linkedJob['jobId'];
            if ($linkedJobId != $jobId) {
                echo '<tr class="cloning"><td colspan="2">'."\n";
                    echo '<input id="include-in-clone-job-'. $linkedJobId. '" type="checkbox" '.
                         'name="include-in-clone" value="job-'. $linkedJobId.'"'.
                         ' checked>&nbsp;'. 
                         '<label for="include-in-clone-job-'. $linkedJobId. '">'.
                         '[J] '. $linkedJob['jobname']. '&nbsp;(' . $linkedJob['number']. ')'.
                         '</label>'.
                         "\n";
                echo "</td></tr>\n";
            }
        }
        foreach($linkedPersons AS $linkedPerson) {
            $linkedPersonId = $linkedPerson['personId'];
            if ($linkedPersonId != $personId) {
                echo '<tr class="cloning"><td colspan="2">'."\n";
                    echo '<input id="include-in-clone-person-'. $linkedPersonId. '" type="checkbox" '.
                         'name="include-in-clone" value="person-'. $linkedPersonId.'"'.
                         ' checked>&nbsp;'. 
                         '<label for="include-in-clone-person-'. $linkedPersonId. '">'.
                         '[P] '. $linkedPerson['firstName'] . '&nbsp;' . $linkedPerson['lastName'].
                         '</label>'.
                         "\n";
                echo "</td></tr>\n";
            }
       }
       foreach($linkedCompanies AS $linkedCompany) {
            $linkedCompanyId = $linkedCompany['companyId'];
            if ($linkedCompanyId != $companyId) {
                echo '<tr class="cloning"><td colspan="2">'."\n";
                    $target = $linkedCompanyId == $personId; // if this company is the way we got here, then certainly we want to clone this one.
                    echo '<input id="include-in-clone-company-'. $linkedCompanyId. '" type="checkbox" '.
                         'name="include-in-clone" value="company-'. $linkedCompanyId.'"'.
                         ' checked>&nbsp;'. 
                         '<label for="include-in-clone-company-'. $linkedCompanyId. '">'.
                         '[C] '. $linkedCompany['companyName'] .
                         '</label>'.
                         "\n";
                echo "</td></tr>\n";
            }
       }
       echo '<tr class="cloning">'."\n";
           echo '<td colspan="2">'."\n";
              echo '<button id="clone-button">Edit location for the selected entities</button>';
           echo '</td>'."\n";
       echo '</tr>'."\n";
    ?>
    <script>
    // NOTE that the form that follows is never visible: we create this form as an easy way to do a POST.
    $('#clone-button').click(function() {
        if ($('[name="include-in-clone"]').not(':checked').length == 0) {
            // including them all. Optimize for this: just falsely say cloned=1 and reload
            let $nocloneForm = $('<form id="noclone" style="display:none" method="post" action="location.php"></form>');
            $nocloneForm.append('<input type="hidden" name="locationId" value="<?php echo $locationId; ?>">');
            $nocloneForm.append('<input type="hidden" name="jobId" value="<?php echo $jobId; ?>">');
            $nocloneForm.append('<input type="hidden" name="personId" value="<?php echo $personId; ?>">');
            $nocloneForm.append('<input type="hidden" name="companyId" value="<?php echo $companyId; ?>">');
            $nocloneForm.append('<input type="hidden" name="cloned" value="1">');
            $nocloneForm.append('<input type="submit" id="noclone-submit">');
            $nocloneForm.appendTo('body');
            $('#noclone-submit').click();
        } else {
            // We need to break this location into two separate rows in the Location table and then use the new one
            $.ajax({
                url: 'ajax/clonelocation.php',
                data: {
                    locationId: <?php echo $locationId; ?>
                },
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['status']) {
                        if (data['status'] == 'success') {
                            let newLocationId = data['locationId'];
                            
                            // >>>00001 There may be a better way to handle this array of values, but I don't know it. - JM 2019-11-21
                            let includeInClone=[];
                            $('input[name="include-in-clone"]').filter(':checked').each(function(){
                                includeInClone.push($(this).val());
                            });
                            
                            $.ajax({
                                url: 'ajax/changelinkedlocation.php',
                                data: {
                                    oldLocationId: <?php echo $locationId; ?>, 
                                    newLocationId: newLocationId,
                                    itemsToRelink: JSON.stringify(includeInClone)
                                },
                                async: false,
                                type: 'post',
                                context: this,
                                success: function(data, textStatus, jqXHR) {
                                    if (data['status']) {
                                        if (data['status'] == 'success') {
                                            // Cloning is complete. 
                                            // Reload this page with the new location ID & 'cloned'
                                            // Again, a form that is never visible: we create this form as an easy way to do a POST.
                                            let $cloneForm = $('<form id="clone" style="display:none" method="post" action="location.php"></form>');
                                            $cloneForm.append('<input type="hidden" name="locationId" value="' + newLocationId + '">');
                                            $cloneForm.append('<input type="hidden" name="jobId" value="<?php echo $jobId; ?>">');
                                            $cloneForm.append('<input type="hidden" name="personId" value="<?php echo $personId; ?>">');
                                            $cloneForm.append('<input type="hidden" name="companyId" value="<?php echo $companyId; ?>">');
                                            $cloneForm.append('<input type="hidden" name="cloned" value="1">');
                                            $cloneForm.append('<input type="submit" id="clone-submit">');
                                            $cloneForm.appendTo('body');
                                            $('#clone-submit').click();                                            
                                        } else {
                                            alert(data['error']);
                                        }
                                    } else {
                                        alert('error no status');
                                    }
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    alert('error in AJAX protocol or in called function, see server log');
                                }
                            }); // END ajax: clonelocation.php
                        } else {
                            alert(data['error']);
                        }
                    } else {
                        alert('error no status');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('error in AJAX protocol or in called function, see server log');
                }
            }); // END ajax: clonelocation.php
        }
    });
    </script>
    <?php
    } // END if ($readonly) {

    // Main display of data about the relevant location.
    // UI to modify values (or may be readonly if we are waiting for resolution on "cloned"
    // 
    // New feature 2019-11: if NOT cloned, and there is more than one linked job/company/person, then
    //  we don't want them to be able to modify until they have resolved whether they want to clone
    //  or to apply the changes in a way that affects all linked jobs/companys/persons.
    // $cloned and $readonly were introduced at this time.  
    echo '<tr>'."\n";
        echo '<td>'."\n";
            echo '<form name="location" id="locationId" method="post" action="location.php">'."\n";
                echo '<input type="hidden" name="cloned" value="' . ($cloned ? '1' : '0') . '">'."\n";
                echo '<input type="hidden" name="locationId" value="' . intval($location->getLocationId()) . '">'."\n";
                echo '<input type="hidden" name="customerId" value="' . intval($customer->getCustomerId()) . '">'."\n";
                echo '<input type="hidden" name="act" value="update">'."\n";
                echo '<input type="hidden" name="jobId" value="' . intval($jobId) . '">'."\n";
                echo '<input type="hidden" name="personId" value="' . intval($personId) . '">'."\n";
                echo '<input type="hidden" name="companyId" value="' . intval($companyId) . '">'."\n";
                echo '<table border="0" cellpadding="0" cellspacing="0">'."\n";
                    echo '<tr>';
                        echo '<td>Name</td>'."\n";
                        echo '<td><input type="text" name="name" id="name" value="' . htmlspecialchars($location->getName()) . '"'.
                            $readonly_attribute. ' size="40" maxlength="128">';
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Address 1</td>';
                        echo '<td><input type="text" name="address1" id="address1" value="' . htmlspecialchars($location->getAddress1()) . '"'. 
                            $readonly_attribute. '  size="40" maxlength="128">';
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Address 2</td>';
                        echo '<td><input type="text" name="address2" id="address2" value="' . htmlspecialchars($location->getAddress2()) . '"'.
                            $readonly_attribute. ' size="40" maxlength="128">';
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Suite</td>';
                        echo '<td><input type="text" name="suite" id="suite" value="' . htmlspecialchars($location->getSuite()) . '"'.
                            $readonly_attribute. ' size="40" maxlength="16">';
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>City</td>';
                        echo '<td><input type="text" name="city" id="city" value="' . htmlspecialchars($location->getCity()) . '"'.
                            $readonly_attribute. ' size="40" maxlength="64">';
                    echo '</tr>'."\n";
                    echo '<tr>'."\n";
                        echo '<td>State</td>'."\n";
                            //$states = allStates(); Already at the top of the page.
                            $locstate = strtoupper($location->getState());
                            if (!strlen($locstate)) {
                                /* OLD CODE REMOVED 2019-03-21 JM
                                $locstate = 'WA';
                                */
                                // BEGIN NEW CODE 2019-03-21 JM
                                $locstate = HOME_STATE;
                                // END NEW CODE 2019-03-21 JM
                            }
                            if ($readonly) {
                                echo '<td><input type="text" name="state" id="state" value="' . htmlspecialchars($locstate) . '"'.
                                     $readonly_attribute. ' size="40" maxlength="64">';
                            } else {
                                echo '<td><select name="state" id="stateSelect">'."\n";                            
                                    foreach ($states AS $state) {
                                        $selected = ($locstate == $state[1]) ? ' selected ' : '';
                                        echo '<option value="' . $state[1] . '" ' . $selected . '>' . $state[0] . '</option>'."\n";
                                    }
                                echo '</select></td>'."\n";
                            }
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Country</td>';
                            $loccountry = strtoupper($location->getCountry());
                            if (!strlen($loccountry)) {
                                $loccountry = HOME_COUNTRY;
                            }
                        echo '<td><input type="text" name="country" id="country" value="' . htmlspecialchars($loccountry) . '"'.
                             $readonly_attribute. ' size="40" maxlength="2"></td>';
                        
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Postal Code</td>';
                        echo '<td><input type="text" name="postalCode" id="postalCode" value="' . htmlspecialchars($location->getPostalCode()) . '"'.
                             $readonly_attribute. ' size="40" maxlength="64">';
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Latitude</td>';
                        echo '<td><input type="text" name="latitude" id="latitude" value="' . htmlspecialchars($location->getLatitude()) . '"'.
                             $readonly_attribute. ' size="40" maxlength="16">';
                    echo '</tr>'."\n";
                    echo '<tr>';
                        echo '<td>Longitude</td>';
                        echo '<td><input type="text" name="longitude" id="longitude" value="' . htmlspecialchars($location->getLongitude()) . '"'.
                             $readonly_attribute. ' size="40" maxlength="16">';
                    echo '</tr>'."\n";
                echo '</table>'."\n";
                
                if ($readonly && !$jcpExists) {
                    echo '<div><b>You must confirm (above) what entities this change applies to before editing.</b></div>';
                } else {
                    if ($readonly) {
                        // if we arrive here, there must be a jcp
                        $label = "Add existing location to $jcpType (no edits to location)";
                    } else if (!intval($location->getLocationId())) {
                        $label = "Add Location" . ($jcpExists ? " to $jcpType" : '');
                    } else if ( $jcpExists && !$jcpAlreadyLinked ) {
                        $label = "Update Location and Add to $jcpType";
                    } else {
                        $label = 'Update Location';  // no links being formed, only meaningful thing is to edit location conent.
                    }
                    echo '<center><input type="submit" id="submitLocation" value="' . $label . '" border="0">';
                    if(canDelete("location", "locationId", intval($location->getLocationId()))){
                        echo '<button class="deleteLocation" id="deleteLocation" type="button" style="border: 1px solid black; margin-left: 10px; padding-left: 3px; padding-right: 3px">Delete Location!!</button>';
                    } 
                    echo '</center>'."\n";
                }
            echo '</form>'."\n";
        echo '</td>'."\n";
        echo '<td>'."\n";
    ?>
            <div class="clearfix"></div>
            <div class="col" style="width:520px; float:right; height:400px; border:1px solid #aaa;" >
                <div id="map-canvas" style="width: 100%; height: 100%"></div>
            </div>
    <?php
        echo '</td>'."\n";
    echo '<tr>'."\n";
    if (($location->getLocationId())) {
    /* JM 2019-11: The USGS thing is no longer supported. Probably can kill this all, but to be safe, we are just making rows invisible with "display:none". */ 
        echo '<tr style="display:none">';
            echo '<td colspan="2"><h1>USGS Stuff</h1></td>';
        echo '</tr>';
        echo '<tr style="display:none">';
            echo '<td colspan="2">';
        ?>    
<?php /* OUTDENT SCRIPT >>>00006 but why is it buried in the middle of nested tables?? */ ?>
<script type="text/javascript">
    var getData = function() {
        var dat = document.getElementById('datadiv');
        var edition = document.getElementById('designCode');
        var variant = document.getElementById('designCodeVariant');
        var siteclass = document.getElementById('siteclass');
        var lat = document.getElementById('lat');
        var lng = document.getElementById('lng');
        
        dat.innerHTML = '';
        $.ajax({
            url: '/ajax/usgsajax.php?edition=' + escape(edition.value) + '&variant=' + escape(variant.value) + '&siteclass=' + escape(siteclass.value) + '&lat=' + escape(lat.value) + '&lng=' + escape(lng.value),
            dataType: 'json',
            type: 'GET',
            success: function(data, textStatus, jqXHR) {
                if (data['status'] == 'success') {
                    var html = '';
                    html += data['html']['table'];
                    html += '<p>';
                    var images = data['html']['images'];
                    for (i = 0; i < images.length; i++) {
                        html += '<img src="' + images[i].src + '" width="' + images[i].width + '" height="' + images[i].height + '">';
                    }
                    dat.innerHTML = html;
                } else {
                    alert ('something went wrong)');
                }
            }
        });
    }
</script>    
<?php /* END OUTDENT SCRIPT */ ?>
                <form name="dataform">
                <table border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>Design Code Reference Document</td>
                        <td>
                            <select id="designCode" required="" name="designCode">
                                <option value="">Please Select...</option>
                                <optgroup label="Derived from USGS hazard data available in 2008" value="undefined">
                                    <option value="asce_41-2013">2013 ASCE 41</option>
                                    <option value="ibc-2012">2012 IBC</option>
                                    <option value="asce-2010">2010 ASCE 7 (w/July 2013 errata)</option>
                                    <option value="nehrp-2009">2009 NEHRP</option>
                                </optgroup>
                                <optgroup label="Derived from USGS hazard data available in 2002" value="undefined">
                                    <option value="aashto-2009">2009 AASHTO</option>
                                    <option value="ibc-2009">2006/09 IBC</option>
                                    <option value="asce-2005">2005 ASCE 7</option>
                                    <option value="nehrp-2003">2003 NEHRP</option>
                                </optgroup>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Earthquake Hazard Level</td>
                        <td>
                            <select id="designCodeVariant" name="designCodeVariant">
                                <option value="0">BSE-2N</option>
                                <option value="1">BSE-1N</option>
                                <option value="2">BSE-2E</option>
                                <option value="3">BSE-1E</option>
                                <option value="4">Custom</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Site Soil Classification </td>
                        <td>
                            <select id="siteclass" required="" name="siteclass">
                                <option value="">Please Select...</option>
                                <option value="0"> Site Class A - "Hard Rock" </option>
                                <option value="1"> Site Class B - "Rock" </option>
                                <option value="2"> Site Class C - "Very Dense Soil and Soft Rock" </option>
                                <option value="3"> Site Class D - "Stiff Soil" (Default) </option>
                                <option value="4"> Site Class E - "Soft Clay Soil" </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Lat, Lng</td>
                        <td>
                            <input type="text" id="lat" name="lat" value="<?php echo $location->getLatitude(); ?>" size="10">,<input type="text" id="lng" name="lng" value="<?php echo $location->getLongitude(); ?>" size="10">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><input type="button" id="go" value="go" border="0" onclick="getData()"></td>
                    </tr>
                </table>
                </form>
                <hr>
                <hr>
                <hr>
                <div id='datadiv'> <?php /* placeholder, will be filled in by return from /ajax/usgsajax.php in function getData */ ?>
                </div>
                <?php
            echo '</td>';
        echo '</tr>'; // END <tr style="display:none">
        }
    echo '</table>'."\n";
echo '</center>'."\n";
// END OUTDENT 1
?>
                
                
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>    
<?php  if ($location->getLocationId()){   ?>    
        <div class="container clearfix"> 
            <div class="main-content">
                <div class="full-box clearfix">
                    <h2 class="heading">Service Loads</h2>
                        <?php /* JM 2019-11-20 If I understand correctly, Martin put class "add" on an "edit" button solely to position
                                 it in a certain way on the heading bar, >>>00012 which suggests that this class is poorly named. */ ?>
                        <a data-fancybox-type="iframe" id="linkServiceLoads" class="button add show_hide fancyboxIframe"  href="/fb/serviceloads.php?locationId=<?php echo $location->getLocationId(); ?>">Edit</a>
                        <?php
                            //$error_is_db = false;
                            $ret = $location->getServiceLoad($error_is_db);
                            if($error_is_db){ // True on query failed.
                                $errorId = '637363751029469562';
                                $logger->errorDB($errorId, 'getServiceLoad method failed.', $db);
                            }
                            echo '<table border="0" cellpadding="0" cellspacing="0">';
                                $currentServiceLoadId = 0;
                                foreach ($ret AS $row) {
                                    if ($currentServiceLoadId != $row['serviceLoadId']) {
                                        echo '<tr>';
                                            echo '<th colspan="2">' . $row['loadName'] . '</th>';
                                        echo '</tr>';
                                    }                                    
                                    echo '<tr>';
                                        echo '<td>' . $row['loadVarName'] . '</td>';
                                        echo '<td>' . $row['varValue'] . '</td>';
                                    echo '</tr>';
                                    $currentServiceLoadId = $row['serviceLoadId'];
                                }
                            echo '</table>';
                        ?>
                </div>
            </div>
        </div>
        
        <div class="container clearfix"> 
            <div class="main-content">
                <div class="full-box clearfix">
                    <h2 class="heading">Jobs</h2>
                    <table>
                        <tbody>    
                            <tr>
                            <th>Number</th>
                            <th>Name</th>
                            <?php /* <th>Loc Type</th> REMOVED 2020-07-20 JM to address http://bt.dev2.ssseng.com/view.php?id=181 (Error - Add Location to Job - LOC TYPE)
                            */ ?>
                        </tr>    
                        <?php 
                            foreach ($linkedJobs AS $row){
                                echo '<tr>';
                                    $loc = $row['obj'];
                                    $j = new Job($row['jobId']);
                                    echo '<td>';
                                        echo '<a id="linkJob' . $j->getJobId() . '" href="' . $j->buildLink() . '">' . $j->getNumber() . '</a>';
                                    echo '</td>';
                                    echo '<td>';
                                        echo $row['jobname'];
                                    echo '</td>';
                                    /* BEGIN REMOVED 2020-07-20 JM to address http://bt.dev2.ssseng.com/view.php?id=181 (Error - Add Location to Job - LOC TYPE)
                                    // 'locationType' was removed from the database, but I had missed this one place it was still referenced in the code
                                    echo '<td>';
                                        echo $row['locationType'];
                                    echo '</td>';
                                    // END REMOVED 2020-07-20 JM
                                    */
                                echo '</tr>';
                            }                        
                        ?>        
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="container clearfix"> 
            <div class="main-content">
                <div class="full-box clearfix">
                    <h2 class="heading">Companies</h2>
                    <table>
                        <tbody>    
                            <tr>
                                <th>Name</th>
                            </tr>    
                            <?php 
                            foreach ($linkedCompanies AS $row){
                                echo '<tr>';
                                    $loc = $row['obj'];
                                    $c = new Company($row['companyId']);
                                    echo '<td>';
                                        echo '<a id="linkCompany' . $c->getCompanyId() . '"  href="' . $c->buildLink() . '">' . $c->getCompanyName() . '</a>';
                                    echo '</td>';
                                echo '</tr>';
                                }                        
                                ?>        
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="container clearfix"> 
            <div class="main-content">
                <div class="full-box clearfix">
                    <h2 class="heading">Persons</h2>
                    <table>
                        <tbody>    
                            <tr>
                                <th>Name</th>
                            </tr>    
                            <?php 
                            foreach ($linkedPersons AS $row){
                                echo '<tr>';
                                    $loc = $row['obj'];
                                    $p = new Person($row['personId']);
                                    echo '<td>';
                                        echo '<a id="linkPerson' . $p->getPersonId() . '"  href="' . $p->buildLink() . '">' . $p->getFirstName() . '&nbsp;' . $p->getLastName() . '</a>';
                                    echo '</td>';
                                echo '</tr>';
                                }                        
                            ?>        
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php } ?>
    </div>
</div>
<script>
    $('.deleteLocation').mousedown(function(){
        let locationId=<?=(isset($_REQUEST['locationId'])?intval($_REQUEST['locationId']):0)?>;
        alert(locationId);
        if(confirm("Are you sure you want to delete the location??IS A HARD DELETE!!!")){
            location.href = "location.php?act=deleteLocation&locationId=" + locationId;
        }
	});

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#locationId').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        address1: "required",
        city: "required",
        postalCode: "required",
        latitude: "latitude",
        longitude: "longitude"
    }
});

validator.showErrors(jsonErrors);

// The moment they start typing (or pasting) in a field, remove the validator warning
$('input').on('keyup change', function(){
    $('#validator-warning').hide();
    $('#name-error').hide();
    $('#address1-error').hide();
    $('#postalCode-error').hide();
    $('#longitude-error').hide();
    $('#latitude-error').hide();

    if ($("input[type=text]").hasClass("text-danger")) {
        $("input[type=text]").removeClass("text-danger");
    }
});

// Add Bootstrap classes to form input and select.
$("input[type=text]").addClass("form-control form-control-sm");
$("select").addClass("form-control form-control-sm");
$("input[type=text], select" ).width("70%");
$("input:text[name=q]").removeClass("form-control form-control-sm");
$("input:text[name=q]").width("30%");

/* George ADDED 2021-03-12. Jquery Validation.
Validation fields address1,  address2, postalCode, if not empty, validate only if we have a valid US address/ zip code format! */
$('#submitLocation').click(function() {
    $(".error").hide();
        var hasError = false;

        // Accepts minimum three character. May include a-z, A-Z alphabets, numbers, whitespace, comma(,), dot(.), apostrophe ('), and dash(-) symbols.
        var addressReg = /^[a-zA-Z0-9\s,.'-]{3,}$/;
        // address1
        var addressVal = $("#address1").val();
        addressVal =  addressVal.trim(); //trim value
        // address2
        var addressVal2 = $("#address2").val();
        addressVal2 =  addressVal2.trim(); //trim value

        // US Zip Codes
        var postalCodeReg = /(^\d{5}$)|(^\d{5}-\d{4}$)/;
        var postalCodeVal = $("#postalCode").val();
        postalCodeVal =  postalCodeVal.trim(); //trim value

        if ( addressVal != '' && !addressReg.test(addressVal) ) {
            $("#address1").after('<span class="error">Please enter a valid address.</span>');
            hasError = true;
        }

        if ( addressVal2 != '' && !addressReg.test(addressVal2) ) {
            $("#address2").after('<span class="error">Please enter a valid address.</span>');
            hasError = true;
        }

        if ( postalCodeVal != '' && !postalCodeReg.test(postalCodeVal) ) {
            $("#postalCode").after('<span class="error">Please enter a valid Postal Code.</span>');
            hasError = true;
        }

        if (hasError == true) {
            return false;
        }
});

$('#address1, #address2, #postalCode').on('mousedown', function() {
    // George 2021-03-12 : hide error-messages on mousedown in input filed
    $('.error').hide();
});
//End ADD

</script>
<?php
include BASEDIR . '/includes/footer.php';
?>