<?php
/* /etc/one-tasktally-per-wot.php
    EXECUTIVE SUMMARY: impose a rule that there is only one tasktally per workOrderTask

    NO INPUTs. This is a one-time, global action as part of the v2020-4 release.
*/
require_once '../inc/config.php';

if (!(php_sapi_name() === 'cli')) {
    echo "Not to be run from web, must be run from a command line\n";
    $logger->info2('1600807471', 'Attempted to run etc/one-tasktally-per-wot.php from web, must be run from command line.');
	die();
}

$logger->info2('1600807550', 'Running etc/one-tasktally-per-wot.php');

$db = DB::getInstance();

$query="SELECT taskTallyId, workOrderTaskId FROM (
            SELECT taskTallyId, workOrderTaskId, COUNT(workOrderTaskId) AS c FROM taskTally 
            GROUP BY workOrderTaskId
        ) AS base
        WHERE base.c > 1";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1600806117', 'Hard DB error', $db);
    echo "Failed, see log\n";
    die();
}

while ($row = $result->fetch_assoc()) {
    $query2 = "SELECT * FROM taskTally WHERE workOrderTaskId = " . $row['workOrderTaskId'] . ";";
    $result2 = $db->query($query2);
    if (!$result2) {
        $logger->errorDb('1600806297', 'Hard DB error', $db);
        echo "Failed, see log\n";
        die();
    }
    if ($result2->num_rows < 2) {
        $logger->errorDb('1600806297', 'Inexplicably, prior query says there are multiple taskTallys for workOrderTaskId=' . $row['workOrderTaskId'] . 
                ', but we find ' . $result2->num_rows . ' when we run: ' . $query2);
        echo "Failed, see log\n";
        die();
    }
    $rows_to_consolidate = Array();
    $totalTally = 0;
    
    // Theoretically, from here to the bottom of the outer while-loop should really be inside a transaction.
    while ($row2 = $result2->fetch_assoc()) {
        $rows_to_consolidate[] = $row2;
        $totalTally += $row2['tally'];
    }
    $query3 = "UPDATE taskTally SET tally = $totalTally ";
    $query3 .= "WHERE taskTallyId=" . $rows_to_consolidate[0]['taskTallyId'] . ";";
    $result3 = $db->query($query3);
    if (!$result3) {
        $logger->errorDb('1600806789', 'Hard DB error', $db);
        echo "Failed, see log\n";
        die();
    }
    for ($i=1; $i<count($rows_to_consolidate); ++$i) {
        $query4 = "DELETE FROM taskTally ";
        $query4 .= "WHERE taskTallyId=" . $rows_to_consolidate[$i]['taskTallyId'] . ";";
        $result4 = $db->query($query4);
        if (!$result4) {
            $logger->errorDb('1600806855', 'Hard DB error', $db);
            echo "Failed, see log\n";
            die();
        }
    }
}

echo "SUCCESS\n";
?>
