<?php
/*  _admin/invoicestatus/index.php

    EXECUTIVE SUMMARY: PAGE to manage invoice statuses.
    
    No primary input, because this looks at all invoice statuses.
    
    Completely reworked for v2020-3. 
    In v2020-3, we've gotten rid of substatuses here; also, it doesn't make sense for a user to add a status or change the uniqueName, because 
    that relates to hardcoded stuff.
    
    Optional INPUT $_REQUEST['act'], possible values:
        * 'update' takes additional inputs: 
            * $_REQUEST['invoiceStatusId'] - primary key to DB table InvoiceStatus
            * $_REQUEST['statusName'] - desired display name for the new status
            * $_REQUEST['color'] - desired new color to associate with status, chosen from a palette.
                                   RGB described in 6 lowercase hex digits.
                                   DEFAULT 'ffffff' (white).
            * $_REQUEST['notes'] - arbitrary note, up to 256 characters
*/

include '../../inc/config.php';
$db = DB::getInstance();

$error = '';
$warn = '';

function writeSevereErrorPage($message) {
    ?>        
    <!DOCTYPE html>
    <html>
    <head>
    </head>
    <body>
    <div class="alert alert-danger" role="alert" id="action-error" style="color:red"><?= $message ?></div>
    </body>
    </html>
    <?php
}

if ($act) {
    $v=new Validator2($_REQUEST);
    list($error, $errorId) = $v->init_validation();
    unset ($errorId);
    if ($error) {
        $error = "Error(s) found in init validation: [".json_encode($v->errors())."]";
        $logger->error2('1589919871', $error);
        
        writeSevereErrorPage($error);
        die();
    }
}
    
if ($act == 'update') {
    $v->rule('required', ['invoiceStatusId', 'statusName']);
    $v->rule('integer', 'invoiceStatusId');
    $v->rule('min', 'invoiceStatusId', 1);
    $v->rule('lengthMin', 'statusName', 1);
    if( !$v->validate() ) {
        $error = "Errors found: ".json_encode($v->errors());
        $logger->error2('637188438766423560', $error);
    }    
    if ( !$error ) {
        $invoiceStatusId = intval($_REQUEST['invoiceStatusId']);
        $statusName = trim($_REQUEST['statusName']);
        $color = isset($_REQUEST['color']) ? strtolower(trim($_REQUEST['color'])) : 'ffffff';
        $notes = isset($_REQUEST['notes']) ? trim($_REQUEST['notes']) : '';
        
        $query = "SELECT invoiceStatusId FROM invoiceStatus ";
        $query .= "WHERE statusName = '" . $db->real_escape_string($statusName) . "' ";
        $query .= "AND invoiceStatusId <> $invoiceStatusId;";
        
        $result = $db->query($query);
        if ( !$result ) {
            $error = 'DB error setting color and statusName for an invoiceStatus';
            $logger->errorDb('1589920914', $error, $db);                    
        } else if ($result->num_rows > 0) {
            // Not really an error -- user gave plausible but invalid value -- but
            // in the UI we handle it like an error
            $error = "invoiceStatus '$statusName' already exists";
        }
    }
    if ( !$error ) {
        // $color should be 6 lowercase hex characters
        if (preg_match("/^#[0-9a-f]{6,6}$/", $color)) {
            $color = substr($color, 1); // strip leading '#'
        } else {
            // Not a big deal if that's invalid, just go to white & warn that we did so.
            $color = 'ffffff';            
            $warn .= "Color coerced from " . (isset($_REQUEST['color']) ? $_REQUEST['color'] : '[missing input]' ) . " to '$color'\n";
        }
        
        // truncate notes if needed
        $notes = trim(substr($notes, 0, 256)); // >>>00016 right now truncates silently, should use uniform truncation code once that exists     
        
        if ($invoiceStatusId) {
            $query = "UPDATE " . DB__NEW_DATABASE . ".invoiceStatus SET ";
            $query .= "color = '" . $db->real_escape_string($color) . "', ";
            $query .= "statusName = '" . $db->real_escape_string($statusName) . "', ";
            $query .= "notes = '" . $db->real_escape_string($notes) . "' ";
            $query .= "WHERE invoiceStatusId = " . intval($invoiceStatusId) . ";";
            $result = $db->query($query);
            if ($result) {
                header("Location: {$_SERVER['REQUEST_URI']}"); // reload clean, so a refresh won't repeat an action
                die();
            } else {
                $error = 'DB error setting color, statusName, notes for an invoiceStatus';
                $logger->errorDb('1584652504', $error, $db);
                $error .= ", see log";
            }
        }
    }
}

// Select 0-level statuses in alphabetical order; these are the only ones we care about: 
//  substatuses (statuses with non-zero parents) are vestigial from v2020-2
// Each member of $invoiceStatuses will be an associative array that is the canonical representation of a row in DB table InvoiceStatus.
// If there is a displayOrder, it wins out; otherwise, statuses are alphabetical
$invoiceStatuses = array();
$query = "SELECT * FROM " . DB__NEW_DATABASE . ".invoiceStatus " . 
    // "WHERE parentId = 0 " . // REMOVED 2020-08-10 JM, no more invoiceStatus.parentId 
    "ORDER BY displayOrder, statusName ASC;";
$result = $db->query($query);
if (!$result) {
    $error = 'Cannot select invoiceStatuses'; 
    $logger->errorDb('1584652504', $error, $db);
    writeSevereErrorPage($error);
    die();
}
while ($row = $result->fetch_assoc()) {
    $invoiceStatuses[] = $row;
}

?>
<!DOCTYPE html>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <link rel="stylesheet" type="text/css" href="../spectrum/spectrum.css">
    <script type="text/javascript" src="../spectrum/spectrum.js"></script>
    
    <style type="text/css">
        .full-spectrum .sp-palette {
            max-width: 200px;
        }
    </style>
</head>
<body bgcolor="#ffffff">
<?php
    if ($warn) {
?>
        <div style="color:red"><?= $warn ?></div>
<?php
    }
    if ($error) {
?>        
        <div class="alert alert-danger" role="alert" id="action-error" style="color:red"><?= $error ?></div>
<?php
    }
?>
    <table border="1" cellpadding="2" cellspacing="1">
        <thead>
            <tr>    
                <th>Status Name (<i>uq: unique name</i>)</th>
                <th>Color</th>
                <th>Notes</th>
                <th>Sent</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>

        <?php
        // for each top-level status
        foreach ($invoiceStatuses as $invoiceStatus) {          
        // A row/form to view & edit invoiceStatuses
        ?>
            <tr>
            <form name="type_<?= intval($invoiceStatus['invoiceStatusId']) ?>" method="post">
                <input type="hidden" name="invoiceStatusId" value="<?= intval($invoiceStatus['invoiceStatusId']) ?>">
                <input type="hidden" name="act" value="update">           
                <!-- "Status Name" -->
                <td><input name="statusName" maxlength="32" value="<?= $invoiceStatus['statusName'] ?>"> 
                         (<i>uq: '<?= $invoiceStatus['uniqueName'] ?>'</i>)</td>
                <!-- "Color" (palette) -->
                <td><input name="color" type="text" class="full" value="#<?= $invoiceStatus['color'] ?>"/></td>
                <!-- "Notes" -->
                <td><input name="notes" maxlength="256" size="40" value="<?= $invoiceStatus['notes'] ?>"></td>
                <!-- "Sent" -->
                <td><?= $invoiceStatus['sent'] ? 'yes' : 'no' ?></td>
                <!-- (no header) Submit button, labeled "update" -->
                <td><input type="submit" value="update"></td>
            </form>
            </tr>
        <?php
        }
        ?>
        </tbody>
    </table>
    <p>NOTE: these statuses are hardcoded; there is no way to add one at the administrative interface because they are meaningless without code behind them.
    Similarly, the order is fixed, as is which statuses indicate that the invoice has been sent.</p>
    <p>NOTE: Unlike v2020-2 and earlier, there are no special per-employee statuses. You can add employee information independently of status when
        setting a status.</p>
    
    </center>
    
    <script type='text/javascript'> //<![CDATA[  // CDATA COMMENTED OUT BY MARTIN BEFORE 2019
    
    // Implement palette
    $(".full").spectrum({        
        showInput: true,
        className: "full-spectrum",
        showInitial: true,
        showPalette: true,
        showSelectionPalette: true,
        maxSelectionSize: 10,
        preferredFormat: "hex",
        localStorageKey: "spectrum.demo",
        move: function (color) {},
        show: function () {},
        beforeShow: function () {},
        hide: function () {},
        change: function() {},
        palette: [
            ["rgb(0, 0, 0)", "rgb(67, 67, 67)", "rgb(102, 102, 102)",
            "rgb(204, 204, 204)", "rgb(217, 217, 217)","rgb(255, 255, 255)"],
            ["rgb(152, 0, 0)", "rgb(255, 0, 0)", "rgb(255, 153, 0)", "rgb(255, 255, 0)", "rgb(0, 255, 0)",
            "rgb(0, 255, 255)", "rgb(74, 134, 232)", "rgb(0, 0, 255)", "rgb(153, 0, 255)", "rgb(255, 0, 255)"], 
            ["rgb(230, 184, 175)", "rgb(244, 204, 204)", "rgb(252, 229, 205)", "rgb(255, 242, 204)", "rgb(217, 234, 211)", 
            "rgb(208, 224, 227)", "rgb(201, 218, 248)", "rgb(207, 226, 243)", "rgb(217, 210, 233)", "rgb(234, 209, 220)", 
            "rgb(221, 126, 107)", "rgb(234, 153, 153)", "rgb(249, 203, 156)", "rgb(255, 229, 153)", "rgb(182, 215, 168)", 
            "rgb(162, 196, 201)", "rgb(164, 194, 244)", "rgb(159, 197, 232)", "rgb(180, 167, 214)", "rgb(213, 166, 189)", 
            "rgb(204, 65, 37)", "rgb(224, 102, 102)", "rgb(246, 178, 107)", "rgb(255, 217, 102)", "rgb(147, 196, 125)", 
            "rgb(118, 165, 175)", "rgb(109, 158, 235)", "rgb(111, 168, 220)", "rgb(142, 124, 195)", "rgb(194, 123, 160)",
            "rgb(166, 28, 0)", "rgb(204, 0, 0)", "rgb(230, 145, 56)", "rgb(241, 194, 50)", "rgb(106, 168, 79)",
            "rgb(69, 129, 142)", "rgb(60, 120, 216)", "rgb(61, 133, 198)", "rgb(103, 78, 167)", "rgb(166, 77, 121)",
            "rgb(91, 15, 0)", "rgb(102, 0, 0)", "rgb(120, 63, 4)", "rgb(127, 96, 0)", "rgb(39, 78, 19)", 
            "rgb(12, 52, 61)", "rgb(28, 69, 135)", "rgb(7, 55, 99)", "rgb(32, 18, 77)", "rgb(76, 17, 48)"]
        ]
    });
    
    
    </script>

</body>
</html>