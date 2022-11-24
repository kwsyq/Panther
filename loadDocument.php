<?php

    include "inc/config.php";

    $jobId=$_REQUEST['documentJobId'];
    $documentGroupId=$_REQUEST['documentGroupId'];

    $job=new Job($jobId);

    $files=$_FILES['documentFile'];

    $db->select_db("sssdev");

    $target_dir = "uploads/";
    $path_parts = pathinfo($_FILES["documentFile"]["name"]);

    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
    
    $fileOnDisk = uniqid('JobDocuments-'.$job->getRwName().'-'  , true) . ".".$path_parts['extension'];
    
    $originalFile = basename($_FILES["documentFile"]["name"]);
    
    // Check if $uploadOk is set to 0 by an error
    if (move_uploaded_file($_FILES["documentFile"]["tmp_name"], $target_dir.$fileOnDisk)) {
        echo "The file ". htmlspecialchars( basename( $_FILES["documentFile"]["name"])). " has been uploaded.";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }

    $db->query("insert into jobDocument set documentGroupId=".$documentGroupId.", 
                name='".$db->real_escape_string($_REQUEST['documentName'])."', 
                jobId=".$db->real_escape_string($_REQUEST['documentJobId']).", 
                fileName='".$db->real_escape_string($originalFile)."', 
                description='".$db->real_escape_string($_REQUEST['documentDescription'])."',
                fileOnDisk='".$db->real_escape_string($fileOnDisk)."', 
                userCreate=".$user->getUserId().",
                created_at=now()");

    header("Location: /documentCenter.php?rwname=".$job->getRwName());

    die();




?>