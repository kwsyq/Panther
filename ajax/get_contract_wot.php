<?php
/*  ajax/get_contract_wot.php

    Usage: in contract.php
    
    Actions: update qyt/ cost, use the cost value from history (reload gant on this action)
    Get the data of the contract: each element and the workorderTasks tree structures coresponding to that specific element
        and the total cost as sum of all the elements.
    

    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrderTask
    
    Returns JSON for an associative array with the following members:    
        * 'data['out']': array. Each element is an associative array with elements:
            * 'elementId': identifies the element.
            * 'elementName': identifies the element name.
            * 'parentId': is null for the element, for a workorderTask is the id of the parent.
            * 'taskId': identifies the task.
            * 'parentTaskId': alias 'parentId', is null for the element, for a workorderTask is the id of the parent.
            * 'workOrderTaskId': identifies the workOrderTask.
            * 'billingDescription':  billing description for a specific workOrderTask.
            * 'icon':  icon for a specific workOrderTask.
            * 'cost':  cost for a specific workOrderTask.
            * 'totCost':  totCost for a specific workOrderTask.
            * 'taskTypeId':  type of a task ( table tasktype ).
            * 'wikiLink':  Link to Wiki for a specific workOrderTask.
            * 'taskStatusId':  status for a specific workOrderTask ( active / inactive ).
            * 'taskContractStatus':  status for a specific workOrderTask ( inactive on arrow down ).
            * 'quantity':  quantity for a specific workOrderTask, default 0.
            * 'hoursTime':  time in minutes for a specific workOrderTask, available in workOrderTaskTime.
            * 'hasChildren': identifies if a element/ woT has childrens.
        * The data['out'] is order by General element ( the General element is first in the workOrderTasks tree data).

        And:
            * 'fail': "fail" on query failure ( database error with errorId ),
            * 'status': "success" on successful query.
            * 'data['elementsCost']': the total sum of all elements.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $data['status'] = 'fail';
    $data['error'] = '';
    $data['errorId'] = '';

    $data['elementsCost'] = 0;
    $data['out'] = '';

    $workOrderId = isset($_REQUEST['workOrderId']) ? intval($_REQUEST['workOrderId']) : 0;
    
    if(!$workOrderId) { // if not, die()
        $error = "Invalid workOrderId from Request.";
        $data['errorId'] = '637798391582193709';
        $logger->error2($data['errorId'], $error);
        $data['error'] = "ajax/get_contract_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    $query = "SELECT elementId as id, elementName as Title, null as parentId, 
    null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, '' as billingDescription, null as cost, null as quantity, 
    null as totCost, null as taskTypeId, '' as icon, '' as wikiLink, null as taskStatusId, null as taskContractStatus, null as hoursTime,  
    elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
    from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
    UNION ALL
    SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId, 
    w.extraDescription as extraDescription, w.billingDescription as billingDescription, w.cost as cost, w.quantity as quantity, w.totCost as totCost,
    w.taskTypeId as taskTypeId, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId,  w.taskContractStatus as taskContractStatus, wt.tiiHrs as hoursTime,
    getElement(w.workOrderTaskId),
    e.elementName, false as Expanded, false as hasChildren
    from workOrderTask w
    LEFT JOIN task t on w.taskId=t.taskId
    
    LEFT JOIN (
    
        SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
        FROM workOrderTaskTime wtH
        GROUP BY wtH.workOrderTaskId
        ) AS wt
        on wt.workOrderTaskId=w.workOrderTaskId
    
    LEFT JOIN element e on w.parentTaskId=e.elementId
    WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null  AND w.internalTaskStatus != 5 ORDER BY FIELD(elementName, 'General') DESC";
 
    $res=$db->query($query);

    if (!$res) {
        $error = "We could not select the Contract data. Database Error";
        $data['errorId'] = '637798392290639088';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/get_contract_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }
    
    $out=[];
    $parents=[];
    $elements=[];
    
    while( $row=$res->fetch_assoc() ) {
        $out[]=$row;
        if( $row['parentId']!=null ) {
            $parents[$row['parentId']]=1;
        }
        if( $row['taskId']==null)    {
            $elements[$row['elementId']] = $row['elementName'] ;
    
            
        }
      
    }
    
    for( $i=0; $i<count($out); $i++ ) {
      
        if( $out[$i]['Expanded'] == 1 )
        {
            $out[$i]['Expanded'] = true;
        } else {
            $out[$i]['Expanded'] = false;
        }
        
        if($out[$i]['hasChildren'] == 1)
        {
            $out[$i]['hasChildren'] = true;
        } else {
            $out[$i]['hasChildren'] = false;
        } 
    
        if( isset($parents[$out[$i]['id']]) ) {
            $out[$i]['hasChildren'] = true;
          
        }
        if ( $out[$i]['elementName'] == null ) {
            $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
        }
    
       
  
    }

    $data['out'] = $out;
    // end reload data.

    $wo = new WorkOrder($workOrderId);
    $jobId = $wo->getJobId();
    $job = new Job($jobId);

    
    $query = " SELECT e.elementId ";
    $query .= " FROM " . DB__NEW_DATABASE . ".element e ";
    $query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wo on wo.elementId = e.elementId ";
    $query .= " WHERE jobId = " . intval($jobId) ." group by e.elementId ";
    
    
    $result = $db->query($query);

    
    if (!$result) {
        $error = "We could not select the contract data. Database Error";
        $data['errorId'] = '637798392922072949';
        $logger->errorDb($data['errorId'], $error, $db);
        $data['error'] = "ajax/get_contract_wot.php: $error";
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }


    $allElements = [];
    while ($row = $result->fetch_assoc()) {
        $allElements[] = $row['elementId'];
    }
    
    
    
    $elementsCost = [];
    foreach($allElements as $value) {

        $wotArrSum = [];
        $query = "select workOrderTaskId,
        parentTaskId, totCost
        from    (select * from workOrderTask
        order by parentTaskId, workOrderTaskId) products_sorted,
        (select @pv := '$value') initialisation
        where   find_in_set(parentTaskId, @pv) and parentTaskId = '$value' and workOrderId = '$workOrderId'
        and     length(@pv := concat(@pv, ',', workOrderTaskId))";

        $result = $db->query($query);

        if (!$result) {
            $error = "We could not select the total cost. Database Error";
            $data['errorId'] = '637798393188769562';
            $logger->errorDb($data['errorId'], $error, $db);
            $data['error'] = "ajax/get_contract_wot.php: $error";
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }

        while( $row=$result->fetch_assoc() ) { 
            $elementsCost[$row['parentTaskId']][] = $row['totCost'];

        }
            
     
    }

    foreach($elementsCost as $key=>$el) {
         $elementsCost[$key] = array_sum($el);
    }
   
    $data['elementsCost'] = $elementsCost;

    if (!$data['error']) {
        $data['status'] = 'success';
    } 

    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>