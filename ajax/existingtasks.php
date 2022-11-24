<?php
/*  ajax/existingtasks.php

    INPUT $_REQUEST['workOrderId']: primary key in DB table WorkOrder
    INPUT $_REQUEST['elementId']: primary key in DB table Element. Optional, and
        can be a comma-separated list; any elementIds that don't already exist 
        in the DB will be ignored. 

    Martin said in 2018 said that going forward we would always add all parent tasks 
    when we add workOrderTasks, "but for old workOrders that didn't do that, 'gold' fills 
    in the blanks up the hierarchy. However, Joe observes July 2020 that there are still 
    ways to add a workOrderTask without adding all parent tasks, and while I'd
    like to fix that, this means that even jobs that are very recent as of July 2020 may
    have this issue.)
    
    If $_REQUEST['elementId'] is non-empty, processing is restricted to elements that match 
    this input. 
    
    From workOrderId, this works out tasks, elementGroups. The rest of what this does is best understood in terms of the structure it returns.
    
    Simplification in v2020-4 after conversation between Joe & Ron 2020-09-09: we are interested only in the workOrderTasks associated
    with precisely the list of elementIds passed in. Not "overlapping the list" or "including at least the list"; corresponding exactly.    
    The case where nothing is passed in corresponds to wanting the "General" workOrderTasks, those not associated with any element.

    Returns JSON for an associative array with the following members:    
        * 'status': "fail" if workOrderId not valid or other failure; "success" on success.
        * 'maxlevel': max value of level in any of the wotsForDisplay values (see below for 'wotsForDisplay').
        * 'elementgroups' (on success only): an array.
           Simplification: as of 2020-4, this output array should have exactly one element. The index will depend on the 'elementId' input  
           and can be any of the following:
            * '' or 0 - "General" workOrderTasks, not specific to any particular element of the job
            * a single number corresponding to a single-number input elementId
            * a comma-separated list of elementIds (no spaces), corresponding exactly to a multi-element input elementId
           Because of this and other simplifications, we are now passing back the same information redunantly in several places.  
          * The value of this single array element is an associative array with the following array elements:
            * 'element' (array with the following array elements):
                * 'elementId' - a single elementId, or 0 or '', or a comma-separated array of elementIds (no spaces), as appropriate. This will be 
                  identical to the sole index to 'elementgroups' 
                * 'elementName' - the corresponding single element name or a comma-separated array of element names, with spaces. I (JM) believe this (and the
                  similar item below) are blank on the "General" case, but they just might say "General".
                We've carried this through for consistency, it is redundant to what follows.
            * 'elementId': exactly as above  
            * 'elementName': exactly as above
            * 'wotsForDisplay': 
               (Prior to 2020-07-31 JM this was called 'gold', now changed to 'wotsForDisplay'. This is NOT the same thing 
               as 'gold' in the return of $workorder->getWorkOrderTasksTree(), so that was a terrible naming convention.
               No change comments inline for this change of name.) 
               A flat array (small-integer indexes) representing a pre-order traversal of the workOrderTask hierarchy for this elementgroup,
               with the tree structure based on the corresponding taskId of the abstract task associated with each workOrderTask.
               "Fake" nodes fill in for any ancestors that lack an overt workOrderTask.
               Each element in this array is a further associative array and represents a workOrderTask.
               The indexes in that associative array are:   
                * 'type': 'real' or 'fake' ("fake" means faked up by filling in parent/ancestor of a task that is "real" for this workorder)
                * 'level'
                * 'data': a further associative array (>>>00001, >>>00014 the following is POSSIBLY INCOMPLETE deserves more study)
                    * if wotsForDisplay['type'] is 'fake':
                      * ['icon']
                      * ['description']
                      * maybe more in some circumstances
                    * if wotsForDisplay['type'] is 'real':
                      * ['workOrderId']
                      * ['taskId']
                      * ['taskStatusId']
                      * ['workOrderTaskId']
                      * ['task']['taskId'] - yes, this is redundant to a level up
                      * ['task']['icon'] - NOTE here and the next, one level deeper than for 'fake'
                      * ['task']['description']
                      * ['task']['billingDescription']
                      * ['task']['estQuantity']
                      * ['task']['estCost']
                      * ['task']['taskTypeId']
                      * ['task']['sortOrder']
                      * maybe more in some circumstances
                * 'times': a count of workOrderTaskTime rows. How many separate times some employee has logged time worked
                     for this workOrder. Always 0 if element is "fake".
                     
     As of 2020-09-10 (JM), this has been so rewritten that I got rid of change history within the file; consult version control if you need that.                 
*/

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'fail';

// >>>00002, >>>00016: should validate inputs, log any errors.
$workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
$elementIdScratch = isset($_REQUEST['elementId']) ? $_REQUEST['elementId'] : '';
$elementIds = explode(',', $elementIdScratch);

$workorder = new WorkOrder($workOrderId); 

if (intval($workorder->getWorkOrderId())) {
    $elementgroups = $workorder->getWorkOrderTasksTree();
    $egs = array();    
    $maxlevel = 0;
    foreach ($elementgroups as $elementgroupdata) {
        // BEGIN ad hoc fix 2020-09-16 JM
        // It seems that "General" can show up as a NULL; we want it to be the number 0
        if (is_null($elementgroupdata['elementId'])) {
            $elementgroupdata['elementId'] = 0;
        }
        // END ad hoc fix 2020-09-16 JM
        if (is_string($elementgroupdata['elementId']) && strpos($elementgroupdata['elementId'], ',') !== false) {
            // Specific multi-element, introduced for v2020-4
            $elementIds2 = explode(',', $elementgroupdata['elementId']);
            if (count($elementIds) != count($elementIds2)) {
                // can't possibly be a match
                continue;
            }
            $match = true;
            foreach ($elementIds as $elementId) {
                if (!in_array($elementId, $elementIds2)) {
                    $match = false;
                    break;
                }
            }
            if (!$match) {
                continue;
            }             
        } else if (is_numeric($elementgroupdata['elementId']) && 
                (count($elementIds) != 1 || intval($elementIds[0]) != intval($elementgroupdata['elementId']))
            )
        {
            // single element, and it doesn't match
            continue;
        } else if ($elementgroupdata['elementId'] == PHP_INT_MAX) {
            // Generic multi-element, shouldn't arise for v2020-4 or later
            $logger->error2('1599753781', 'WorkOrder ' . $workorder->getWorkOrderId() . ' has an elementgroup with the old generic multi-element marker, ' .
                'PHP_INT_MAX (' . PHP_INT_MAX . ')');
            continue;            
        }
        $eg = array();
        
        if ($elementgroupdata['element'] == false) {
            // JM doesn't think this happens any more in v2020-4, but keeping it just in case
            $eg['element'] = false;
        } else {
            $eg['element'] = Array('elementId' => $elementgroupdata['elementId'], 'elementName' => $elementgroupdata['elementName']);
        }
        $eg['elementName'] = $elementgroupdata['elementName'];
        $eg['elementId'] = $elementgroupdata['elementId'];
        $eg['wotsForDisplay'] = array();
        
        if (isset($elementgroupdata['gold'])) {        
            if (is_array($elementgroupdata['gold'])) {                    
                foreach ($elementgroupdata['gold'] as $index => $gold) {
                    $wot = $gold['data']; // This can actually be either a string like 'a245' for an internal node ('fake' task) 
                                          // OR a WorkOrderTask object ('real' task).
                    
                    if (intval($gold['level']) > $maxlevel) {
                        $maxlevel = $gold['level'];
                    }
                    
                    if ($gold['type'] == 'real') {
                        $t = $gold['data']->getWorkOrderTaskTime();
                        $g = array('type' => $gold['type'], 'level' => $gold['level'], 'data' => $gold['data']->toArray(), 'times' => count($t));
                    } else {
                        // "fake"
                        $task = new Task(str_replace("a", "", $gold['data']));                        
                        $g = array('type' => $gold['type'], 'level' => $gold['level'], 'data' => $task->toArray(), 'times' => 0);                        
                    }                        
                    $eg['wotsForDisplay'][] = $g;
                }
            }
        }
        $egs[] = $eg;
    }    
    
    $data['status'] = 'success';
    $data['elementgroups'] = $egs;
    $data['maxlevel'] = intval($maxlevel);
}

header('Content-Type: application/json');
echo json_encode($data);
die();
?>