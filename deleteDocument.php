<?php

    include "inc/config.php";

    $jobDocumentId=$_REQUEST['id'];

    $db->select_db("sssdev");

    $row=$db->query("select * from jobDocument where jobDocumentId=$jobDocumentId")->fetch_assoc();

    unlink($_SERVER['DOCUMENT_ROOT'] . "/uploads/".$row['fileOnDisk']);

    $db->query("delete from jobDocument where jobDocumentId=$jobDocumentId");

    echo "OK",

    die();



?>