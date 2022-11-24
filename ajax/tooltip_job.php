<?php
/*  ajax/tooltip_job.php

    INPUT $_REQUEST['jobId']: primary key to DB table Job
    
    Just returns misc info about a job.
    
    Returns JSON for an associative array with the following members:    
        * 'jobId: as input
        * 'number': Job Number
        * 'name': job name
        * 'description': job description
        * 'jobStatusName': name of job status
        * 'created': date
        * 'ancillaryHTML': Added 2019-11-13 JM. Return of AncillaryData::generateAncillaryDataForSearchResults('job', $_REQUEST['jobId']), 
            for additional display in tooltip.
*/    

include '../inc/config.php';
include '../inc/access.php';

$jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;
$job = new Job($jobId, $user);

$data = array();
$data['jobId'] = $job->getJobId();
$data['number'] = $job->getNumber();
$data['name'] = $job->getName();
$data['description'] = $job->getDescription();
//$data['notes'] = $job->getNotes(); // Commented out by Martin before 2019
$data['jobStatusName'] = $job->getJobStatusName();
$data['created'] = $job->getCreated();
$data['ancillaryHTML'] =  AncillaryData::generateAncillaryDataForSearchResults('job', $jobId);

header('Content-Type: application/json');
echo json_encode($data);
die();

?>