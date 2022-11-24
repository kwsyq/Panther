<?php
/* fb/addlocation.php

    EXECUTIVE SUMMARY: Implements a fancybox popup page to add a location. Adds a row in location table.
    This page will be a child of the page that invokes it.
    Location is always associated with current customer (which as of 2019-04 always SSS).

    No input.
    
    On completion, navigates to the location page for the new location.

*/

include '../inc/config.php';
include '../inc/access.php';

// ADDED by George 2020-07-20, function do_primary_validation 
//calls Validator2::primary_validation includes validation for DB, customer, customerId.
do_primary_validation(APPLICATION_FATAL_ERROR);
// END Add

$error = '';
$errorId = 0;

$name = '';
$address1 = '';
$address2 = '';
$suite = '';
$city = '';
$state = HOME_STATE;
$country = HOME_COUNTRY;
$postalCode = '';
$latitude = '';
$longitude = '';

$states = allStates(); //array of US state names & abbreviations

$v=new Validator2($_REQUEST);
$v->stopOnFirstFail();

if ($act == 'addlocation') {

    $v->rule('required', [ 'address1', 'city', 'postalCode']);
    $v->rule('required', ['address1', 'city', 'postalCode']);
    $v->rule('numeric', ['latitude', 'longitude']);

    if (!$v->validate()) {
        $errorId = '637171288774197985';
        $logger->error2($errorId, "Error in input parameters ".json_encode($v->errors()));
        $error = "Error in input parameters, please fix them and try again";  
    } else {
        
        $name = isset($_REQUEST['name'])?$_REQUEST['name']:'';
        $address1 = $_REQUEST['address1'];
        if (array_key_exists('address2', $_REQUEST)) {
            $address2 = $_REQUEST['address2'];
        }
        if (array_key_exists('suite', $_REQUEST)) {
            $suite =  $_REQUEST['suite'];
        }
        $city = $_REQUEST['city'];
        
        if (array_key_exists('country', $_REQUEST)) {
            $country = $_REQUEST['country'];
        }
        if (!$country) {
            $country = HOME_COUNTRY;
        }
        
        if (array_key_exists('state', $_REQUEST)) {
            $state = $_REQUEST['state'];
            if ($country = "US") {
                $okState = false;
                foreach ($states as $stateData) {
                    // e.g. $stateData == Array('Washington', 'WA')
                    if ($state == $stateData[1]) {
                        $okState = true;
                        break;
                    }
                }
                if ( ! $okState ) {
                    $logger->error2('1584118778', "Invalid U.S. state $state coerced to '" . HOME_STATE . "'");
                    $state = HOME_STATE;
                }
            }
        }
        if ($country = "US" && !$state) {
            $state = HOME_STATE;
        }        
        
        $postalCode = $_REQUEST['postalCode'];
        
        if (array_key_exists('latitude', $_REQUEST)) {
            $latitude = $_REQUEST['latitude'];
        }
        if (array_key_exists('longitude', $_REQUEST)) {
            $longitude = $_REQUEST['longitude'];
        }
        
        $cleaned_request = Array(
            'name' => $name, 
            'address1' => $address1,
            'address2' => $address2,
            'suite' => $suite,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'postalCode' => $postalCode,
            'latitude' => $latitude,
            'longitude' => $longitude
        );        
        $location = Location::addLocation($cleaned_request);
      
        if (!$location) {
            $errorId = '637169435590626760';
            $error = 'addLocation method failed';
            $logger->error2($errorId, $error);
        }
    }
    
    if (!$error) {
        ?>
        <script type="text/javascript">
            top.window.location = '<?php echo $location->buildLink(); ?>';              
        </script>
        <?php
    }

} //end if addlocation

include '../includes/header_fb.php';

if ($error) {
    echo "<div id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<style>    
    body {
        background: white !important;
    }
    .error {
    color: #FF0000;
}
</style>


<div class="container-fluid">

<form name="note" action="addlocation.php" method="post" id="addLocationForm">
    <input type="hidden" name="act" value="addlocation">
    <div class="form-group row">
        <label for="name" class="col-sm-4 col-form-label">Name*</label>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="name" name="name" value='<?php echo isset($_REQUEST['name']) ? $_REQUEST['name'] : ''; ?>'  placeholder="name" maxlength="128">
        </div>
    </div>
    <div class="form-group row">
        <label for="address1" class="col-sm-4 col-form-label">Address*</label>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="address1" name="address1"  value='<?php echo isset($_REQUEST['address1']) ? $_REQUEST['address1'] : ''; ?>'  placeholder="address line 1" maxlength="128">
        </div>
        <div class="col-sm-4"></div>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="address2" name="address2"  value='<?php echo isset($_REQUEST['address2']) ? $_REQUEST['address2'] : ''; ?>' placeholder="address line 2" maxlength="128">
        </div>
    </div>
    <div class="form-group row">
        <label for="suite" class="col-sm-4 col-form-label">Suite</label>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="suite" name="suite" value='<?php echo isset($_REQUEST['suite']) ? $_REQUEST['suite'] : ''; ?>' placeholder="suite" maxlength="16">
        </div>
    </div>
    <div class="form-group row">
        <label for="city" class="col-sm-4 col-form-label">City*</label>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="city" name="city" value='<?php echo isset($_REQUEST['city']) ? $_REQUEST['city'] : ''; ?>' placeholder="city" maxlength="64">
        </div>
    </div>
    <div class="form-group row">
        <label for="state" class="col-sm-4 col-form-label">State/Country</label>
        <div class="col-sm-6">
            <?php
                // $states = allStates(); // moved this above - JM 2020-03-13
                echo '<select class="form-control" id="state" name="state">';
                foreach ($states as $stname) {
                    $selected = ($state == $stname[1]) ? ' selected ' : '';
                    echo '<option value="' . $stname[1] . '" ' . $selected . '>' . $stname[0] . '</option>';
                }
                echo "</select>";
            ?>
        </div>
        <div class="col-sm-2">
        <input type="text" class="form-control" id="country" name="country" value="<?=htmlspecialchars($country)?>" maxlength="2">
        </div>
    </div>
    <div class="form-group row">
        <label for="postalCode" class="col-sm-4 col-form-label">Postal Code*</label>
        <div class="col-sm-8">
            <input type="text" class="form-control" id="postalCode" name="postalCode" value='<?php echo isset($_REQUEST['postalCode']) ? $_REQUEST['postalCode'] : ''; ?>' placeholder="postal code" maxlength="64">
        </div>
    </div>
    <div class="form-group row">
        <label for="latitude" class="col-sm-4 col-form-label">Latitude/Longitude</label>
        <div class="col-sm-4">
            <input type="text" class="form-control" id="latitude" name="latitude" value='<?php echo isset($_REQUEST['latitude']) ? $_REQUEST['latitude'] : ''; ?>' placeholder="latitude" maxlength="16">
        </div>
        <div class="col-sm-4">
            <input type="text" class="form-control" id="longitude" name="longitude" value='<?php echo isset($_REQUEST['longitude']) ? $_REQUEST['longitude'] : ''; ?>' placeholder="longitude" maxlength="16">
        </div>
    </div>

    <div class="form-group row mt-4">
        <button type="submit" id="addLocation" class="btn btn-secondary mx-auto">Add location</button>
    </div>  
</form>
</div>
<script>

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#addLocationForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        address1: "required",
        city: "required",
        postalCode: "required",
        longitude: "numeric",
        latitude: "numeric"
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


// George ADDED 2021-04-28. Jquery Validation.
// Validation fields address1,  address2, postalCode, if not empty, validate only if we have a valid US address/ zip code format!
$('#addLocation').click(function() {
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
    // George 2021-04-28 : hide error-messages on mousedown in input filed
    $('.error').hide();
});
//End ADD
</script>
<?php
include '../includes/footer_fb.php';
?>