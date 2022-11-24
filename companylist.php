<?php

/*
  companylist.php

  EXECUTIVE SUMMARY: This is a top-level page. Allows user to view data about all companies.
    Displays a dynamic table :
     * Company Id
     * Company Name
     * Company URL
     * Company Address - city, number and street
     * Company Email Address - if many we display the first one
     * Company Phone - if many we display the first one
    Number of Active Jobs assocciated to a specific company
    Number of Invoices assocciated to a specific company ( status 64 - Awaiting Payment ).

  * sp_geCompanyList() a store procedure that returns all the companies and their associations.
    In DataBase:
      * sp_geCompanyList() - store procedure with all the data.
      * companyjobsactive - view with active jobs associated to a specific company
      * companywoinovoices - view with active invoices associated to a workorder then linked to a specific company.

    Before running be sure we run the Sql files from /etc: (in this files are the structures):
      sp_geCompanyList.sql - procedure structure 
      companyjobsactive.sql - view structure 
      companywoinovoices.sql - view structure 

*/

require_once './inc/config.php';
require_once './inc/access.php';
?>

<?php
  $error = "";
  $db = DB::getInstance();

  $companyList = array();
  $result = $db->query("CALL sp_geCompanyList()");

  if ( $result ) {
    while ($row = $result->fetch_assoc()) {
      $companyList[] = $row;
    }
  } else {
    $errorId = "637480280029384807";
    $error = "We could not display the Companies. Database Error.";  // Message for end user
    $logger->errorDB($errorId, "sp_geCompanyList() procedure failed. Database Error.", $db);
  }
  $result->close();
  $db->next_result();
?>

<?php
include_once BASEDIR . '/includes/header.php';
if ($error) {
  echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$error</div>";
}
?>

<link href="https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js" ></script>

<style>
body {
    background-color: #fff;
}
</style>

<div class="container-fluid mt-10">
<table class="stripe row-border cell-border  " id="companyList" style="width:100%">
  <thead>
    <tr>
      <th scope="col">Company ID</th>
      <th scope="col">Company Name</th>
      <th scope="col">Url</th>
      <th scope="col">Location (city/ address)</th>
      <th scope="col">Email</th>
      <th scope="col">Phone</th>
      <th scope="col">Jobs</th>
      <th scope="col">Invoices</th>
    </tr>
  </thead>
  <tbody>
  <?php 
    foreach( $companyList as $company ) {
  ?>
    <tr>
        <th><?=$company['companyId']?></th>
        <td><a href="company/<?=$company['companyId'] ?>" target="_blank"> <?=$company['companyName'] ?></a></td>
        <td><a href="<?=$company['companyURL'] ?>" target="_blank"> <?=$company['companyURL'] ?></a></td>
        <td><a href="/location.php?locationId=<?=$company['locationId'] ?>&companyId=<?=$company['companyId'] ?>" target='_blank'> <?= $company["city"] ?> - <?= $company["address1"] ?></a></td>
        <td><a href="mailto:<?=$company['emailAddress']?>"><?=$company['emailAddress']?></a></td>
        <td><?=$company['phoneNumber']?></td>
        <td><?=$company['nrJobs']?></td>
        <td><?=$company['nrInvoices'] ?></td>
    </tr>
<?php } ?>
  </tbody>
</table>
</div>

<script>
$(document).ready(function() {
    $('#companyList').DataTable({
      "autoWidth": false
    });
} );
</script>

<?php
include_once BASEDIR . '/includes/footer.php';
?>