<?php

/*
  tooltip_details.php

  EXECUTIVE SUMMARY: This is a top-level page. Allows user to view data about all entities.
  Specific to a page.

*/

require_once './inc/config.php';
require_once './inc/access.php';

// ADDED by George 2021-02-09, Validator2::primary_validation includes validation for DB, customer, customerId
do_primary_validation(APPLICATION_FATAL_ERROR);

$error = "";
$db = DB::getInstance();
$dataDetails = array();

$pageName = isset($_REQUEST['pageName']) ? trim($_REQUEST['pageName']) : '';

if ($pageName) {
    $query = "SELECT * FROM " . DB__NEW_DATABASE . ".tooltip ";
    $query .= " WHERE pageName = '" . $db->real_escape_string($pageName) . "' ";

    $result = $db->query($query);

    if (!$result) {
        $error = "We could not get the Tooltips Details. Database Error";
        $logger->errorDb('637483979782867920', $error, $db);
    } else {
        while ($row = $result->fetch_assoc()) {
            $dataDetails[] = $row;
        }
    }
}
?>


<?php
include_once BASEDIR . '/includes/header.php';
if ($error) {
  echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
} 
?>
<style>
    h2, #allDetails {
        padding: 20px;
    }
    h5 {
        color:#743535;
    }
    p, span {
        font-size: 16px;
    }
    #divDetails {
        padding: 8px;
    }

</style>

<div id="container" class="clearfix container-fluid">
    <div class="main-content" >
        <h2 >Tooltip details for <?=ucfirst($pageName)?></h2>
        <div id="allDetails">
            <?php foreach($dataDetails as $detail) { ?>
              
                <div id="<?=$detail["fieldName"]?>" style="padding: 8px;" class="border rounded mt-3">
                    <h5> <?=$detail["fieldLabel"]?></h5>
                    <p><?=$detail["tooltip"]?></p>
                    <span><?=$detail["help"]?></span>
                </div>
     
            <?php } ?>
        </div>
    </div>
</div>

<?php
include_once BASEDIR . '/includes/footer.php';
?>