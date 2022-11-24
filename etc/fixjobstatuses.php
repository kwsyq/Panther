<?php
/* etc/fixjobstatuses.php

    One-time process (but harmless to run it again) to fix the jobStatusId column in 
    DB table 'job'. 
    
    This is intended as part of the conversion to v2020-4, but it should be OK to 
    run at any time.
    
    This also has the side effect of reporting (in syslog) any "bad" Job Numbers. 
    
    See http://sssengwiki.com/Job+status for context; basically, we are fixing 
    the jobStatusId column and eventually getting rid of the realStatus column.
    
*/

require_once __DIR__.'/../inc/config.php';

if (!(php_sapi_name() === 'cli')) {
    echo "Not to be run from web, must be run from a command line\n";
    $logger->info2('1605801242', 'Attempted to run etc/fixjobstatuses.php from web, must be run from command line.');
	die();
}
echo "Expect this to take about half a minute\n";

$db = DB::getInstance();

$logger->info2('1597782535', 'Running fixjobstatuses.php');

$query = "SELECT jobId FROM " . DB__NEW_DATABASE . ".job;";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1597782681', 'Hard DB error', $db);
    echo "Hard DB error, see log\n";
    die();
}

$numberActive = 0;
$numberDone = 0;

while ($row = $result->fetch_assoc()) {
    $job = new Job($row['jobId']);
    $workOrdersForJob = $job->getWorkOrders();
    $active = false;
    if (count($workOrdersForJob) == 0) {
        // special case, brand new job, no workOrders
        $active = true;
    } else {
        // Check to see: are there any active workOrders? If so, job should be active.
        foreach ($workOrdersForJob as $workOrderForJob) {
            if ( ! $workOrderForJob->getWorkOrderStatus()['isDone'] ) {
                $active = true;
                break;
            }
        }
    }
    if ($active) {
        ++$numberActive;
    } else {
        ++$numberDone;
    }
    $job->setJobActive($active);
}

$logger->info2('1597782890', "fixjobstatuses.php found $numberActive active jobs, $numberDone done.");
    
echo "SUCCESS\n";
   
?>