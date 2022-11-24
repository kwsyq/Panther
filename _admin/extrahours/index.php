<?php
/*  _admin/extrahours/index.php

    EXECUTIVE SUMMARY: Manage Standard hourly rate for extra services. 
    
    No primary input.

    Optional INPUT $_REQUEST['act'] has possible values: 
        * 'updatehour' takes additional input: 
            * $_REQUEST['hoursRate']
            * $_REQUEST['hoursRateId']
*/

include '../../inc/config.php';
include '../../inc/access.php';

$db = DB::getInstance();    
    
if ($act == 'updatehour') {
    // Insert a row into DB table extraHoursService.
    //Standard hourly rate for extra services 
    $hoursRate = isset($_REQUEST['hoursRate']) ? intval($_REQUEST['hoursRate']) : 0; 
    $hoursRateId = isset($_REQUEST['hoursRateId']) ? intval($_REQUEST['hoursRateId']) : 0;       

    /*$query = " INSERT INTO " . DB__NEW_DATABASE . ".extraService (hoursRate) values (";
    $query .= " " . intval($hoursRate) . " ";*/

    $query = " UPDATE " . DB__NEW_DATABASE . ".extraHoursService SET  ";
    $query .= " hoursRate = " . intval($hoursRate) . " ";
    $query .= " WHERE hoursRateId = " . intval($hoursRateId);

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not Update the Standard hourly rate for extra services. Database Error";
        $logger->errorDb('637770634443145608', $error, $db);
    } 
}

    /*  
    INPUT $hoursRateId should be primary key in DB table extraHoursService.
    Return an array representing the hours rate, we return an
    associative array containing:
        * hoursRateId: primary key in DB table extraHoursService 
        * hoursRate (Standard hourly rate for extra services, used in code)
    */
include_once BASEDIR . '/includes/header_admin.php';
?>
<!DOCTYPE html>
<html>
<head>
</head>
<body>
<?php

$query = " SELECT hoursRate, hoursRateId, date ";
$query .= " FROM " . DB__NEW_DATABASE . ".extraHoursService ";

$hoursRate = 0;
$hoursRateId = 0;

$result = $db->query($query);
if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc(); 
        $hoursRate = $row['hoursRate']; // the cost
        $hoursRateId = $row['hoursRateId']; // the ID
        $hoursDate = $row['date']; // Last updated
        
    }
} else {
    $error = "We could not Select the Standard hourly rate for extra services. Database Error";
    $logger->errorDb('637770640371521462', $error, $db);
}

// Self-submitting form as follows:
//   * HTML INPUT element "hoursRate"; initially has value="0". 
//   * submit button labeled 'Update'. 
?>

<div class="container-fluid ">
<div style="padding:50px">
<h3>Standard hourly rate for extra services: </h3> <span class="font-italic">(Last updated on: <?=$hoursDate?>) </span></br>
    <div class="d-flex justify-content-start mt-3">
 
        <div>
            <form name="updateHourForm" action=" " method="post" id="updateHourForm">
                <input type="hidden" name="act" value="updatehour">
                <input type="hidden" name="hoursRateId" value="<?=$hoursRateId?>">
                <div class="form-group row">
                    <label for="hoursRate" class="col-sm-4 col-form-label">Hourly Rate: </label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control" id="hoursRate" name="hoursRate" value="<?=$hoursRate?>" placeholder="Hourly rate" maxlength="30" required>
                    </div>
                </div>
                <div class="form-group row mt-2">
                    <button type="submit" id="updateHouryRate" class="btn btn-secondary btn-sm  mr-auto ml-auto">Update</button>
                </div>  
            </form>
        </div>
    </div>
</div>
</div>
<script>

var jsonErrors = <?=json_encode($v->errors())?>;

var validator = $('#updateHourForm').validate({
    errorClass: 'text-danger',
    errorElement: "span",
    rules: { 
        hoursRate:{
            required: true
        }
    }
});


validator.showErrors(jsonErrors);

// The moment they start typing (or pasting) in a field, remove the validator warning
$('input').on('keyup change', function() {
    $('#validator-warning').hide();
    if($('#hoursRate').hasClass('text-danger')){
        $('#hoursRate').removeClass('text-danger');
    }
});
</script>
?>
</body>
</html>
