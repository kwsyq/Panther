<?php
/*  jobqb.php

    EXECUTIVE SUMMARY: Redirect to a job.php PAGE, based on $_REQUEST['number'], which is a Job Number. 
    Actual page URL will be based on job.rwname. "qb" = QuickBooks. 
    Theory is that this will eventually go away as we move away from QuickBooks.
*/

include './inc/config.php';
include './inc/access.php';

$db = DB::getInstance();

$number = isset($_REQUEST['number']) ? $_REQUEST['number'] : '';

$query = "select * from " . DB__NEW_DATABASE . ".job where number = '" . $number . "' ";

echo $query;

if ($result = $db->query($query)) {
    if ($result->num_rows > 0) {
        // >>>00018 JM: No good reason for a 'while' rather than an 'if',
        //  there should only be one such row (and if it were otherwise, then 
        //  we should probably die after redirecting).
        while ($row = $result->fetch_assoc()) {
            $job = new Job($row['jobId']);
            header("Location: " . $job->buildLink());
        }
    }
}


 ?>
