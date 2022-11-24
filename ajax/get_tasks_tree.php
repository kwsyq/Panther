<?php
/*  ajax/get_tasks_tree.php

    Usage: in workordertasks.php, on Workorder Elements Tasks tab 

     Based on the selected workorder task, it search for all his children's and builds a hierarchical task tree.

    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrder
    INPUT $_REQUEST['workOrderTaskId']: primary key in DB table workOrderTask

    Returns JSON for an associative array with the following members:    
    * 'data': array. Each element is an associative array with elements:
        * 'elementId': identifies the element.
        * 'elementName': identifies the element name.
        * 'parentId': is null for the element, for a workorderTask is the id of the parent.
        * 'taskId': identifies the task.
        * 'parentTaskId': alias 'parentId', is null for the element, for a workorderTask is the id of the parent.
        * 'workOrderTaskId': identifies the workOrderTask.
        * 'extraDescription':  extra description for a specific workOrderTask.
        * 'tally':  tally for a specific workOrderTask, default 0.
        * 'hasChildren': identifies if a element/ woT has childrens.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';
    $parentIdTask = isset($_REQUEST['workOrderTaskId']) ? $_REQUEST['workOrderTaskId'] : '';
  

    $query = "SELECT elementId as id, elementName as Title, null as parentId, 
    null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, null as tally, 
    elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren 
    from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
    UNION ALL
    SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId, 
    w.extraDescription as extraDescription, tl.tally as tally, getElement(w.workOrderTaskId),
    e.elementName, false as Expanded, false as hasChildren
    from workOrderTask w
    LEFT JOIN task t on w.taskId=t.taskId
    LEFT JOIN taskTally tl on w.workOrderTaskId=tl.workOrderTaskId
    LEFT JOIN element e on w.parentTaskId=e.elementId
    WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null ORDER BY FIELD(elementName, 'General') DESC";
    
        $res=$db->query($query);
    
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

    function buildTree(array $elements, $parentId) {
        $branch = array();
       
        foreach ($elements as $element) {
            if ($element['parentId'] == $parentId) {
                $children = buildTree($elements, $element['id']);
                if ($children) {
                    $element['items'] = $children;
                }
                $branch[] = $element;
            }
        }
    
        return $branch;
    }
    
    $tree22 = buildTree($out, $parentId  = $parentIdTask);
    
    $data[] = $tree22;


    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>