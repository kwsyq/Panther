<?php
/*  ajax/get_wo_tasks.php

    Usage: on workordertasks.php, WorkOrder Elements Tasks tab, get the workOrderTasks tree data. 

    Possible actions: 
        *On select single/combined elements, from modal Select Single Elements(s),
            display all the workOrderTasks tree data for each element.

    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrder.
    INPUT $_REQUEST['jobId']: primary key in DB table job.
    INPUT $_REQUEST['ids']: the ids of elements we selected.

    General element logic: Check if General exists in the Database, table element, for this workorder.
        If No elementId found in element table, safe to insert the new General element.

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

        * The json data is order by General element ( the General element is first in the workOrderTasks tree data).
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';
    $jobId = isset($_REQUEST['jobId']) ? intval($_REQUEST['jobId']) : 0;
    $telerikjob = isset($_REQUEST['telerikjob']) ? $_REQUEST['telerikjob'] : false;
    $ids = isset($_REQUEST['ids']) ? $_REQUEST['ids'] : array();
 
  
    if($ids) {
        // General Element id 0
        if(in_array(0, $ids)) {
            // check if General exists. To be sure.
            $query = " SELECT elementId ";
            $query .= " FROM  " . DB__NEW_DATABASE . ".element  ";
            $query .= " WHERE elementName = '" . $db->real_escape_string("General") . "'";
            $query .= " AND workOrderId = " . intval($workOrderId) . ";";

            $result = $db->query($query);
            if ($result) {
                // No elementId found in element. Safe to insert the new General element in the element table.
                if ($result->num_rows == 0) {
    
                    //syslog(LOG_ERR, $jobId);
                    $query = "INSERT INTO " . DB__NEW_DATABASE . ".element (jobId, elementName, workOrderId) VALUES (";
                    $query .= intval($jobId).", ";
                    $query .= "'" . $db->real_escape_string("General") ."' ,";
                    $query .= intval($workOrderId)." ";
                    $query .= ")";
                    
                    syslog(LOG_ERR, $query);
                    $result = $db->query($query);
                  
                    if (!$result) {
                        $error = "We could not add the General Element in the Element table. Database Error";
                        $logger->errorDb('637648800509008129', $error, $db);
                        $data['error'] = "ajax/add_combined_elements.php: $error";
                        header('Content-Type: application/json');
                        echo json_encode($data);
                        die();
                    }
                } 
                       
            } 

            $query = " SELECT elementId ";
            $query .= " FROM  " . DB__NEW_DATABASE . ".element  ";
            $query .= " WHERE elementName = '" . $db->real_escape_string("General") . "'";
            $query .= " AND workOrderId = " . intval($workOrderId) . ";";
            
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
    
                    array_push($ids, $row["elementId"]);  // Add the new elementId for General
              
                }
            }
            unset( $ids[array_search( '0', $ids )] ); // Remove 0 for Old General from ids
        }
     


        $telerikjob = false;
        $ids2 = implode(',', array_map('intval', $ids));

        $query = " SELECT elementId as id, elementName as Title, null as parentId,
        null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, null as internalTaskStatus, '' as tally, null as hoursTime,
        elementId as elementId, elementName as elementName, false as Expanded, false as hasChildren
        FROM element WHERE elementId in ( $ids2 )
        UNION ALL
        SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId,
        w.workOrderTaskId as workOrderTaskId, w.extraDescription as extraDescription, w.internalTaskStatus as internalTaskStatus, tl.tally as tally, wt.tiiHrs as hoursTime, getElement(w.workOrderTaskId),
        e.elementName, false as Expanded, false as hasChildren
        FROM workOrderTask w
        LEFT JOIN task t ON w.taskId=t.taskId

        LEFT JOIN (

            SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
            FROM workOrderTaskTime wtH
            GROUP BY wtH.workOrderTaskId
        ) AS wt
        on wt.workOrderTaskId=w.workOrderTaskId

        LEFT JOIN taskTally tl on w.workOrderTaskId=tl.workOrderTaskId
        LEFT JOIN element e ON w.parentTaskId=e.elementId
        WHERE w.workOrderId=" . intval($workOrderId) . " AND w.parentTaskId is not null ORDER BY FIELD(elementName, 'General') DESC, internalTaskStatus DESC";
    } else {
        //$telerikjob = false;
        $query = "SELECT elementId as id, elementName as Title, null as parentId, 
        null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription,  null as internalTaskStatus, '' as tally, null as hoursTime,
        elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren 
        FROM element WHERE elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=" . intval($workOrderId) . ")
        UNION ALL
        SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, 
        w.workOrderTaskId as workOrderTaskId, w.extraDescription as extraDescription, w.internalTaskStatus as internalTaskStatus, tl.tally as tally, wt.tiiHrs as hoursTime, getElement(w.workOrderTaskId),
        e.elementName, false as Expanded, false as hasChildren
        FROM workOrderTask w
        LEFT JOIN task t ON w.taskId=t.taskId 

        LEFT JOIN ( 

            SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
            FROM workOrderTaskTime wtH
            GROUP BY wtH.workOrderTaskId
        ) AS wt
        on wt.workOrderTaskId=w.workOrderTaskId

        LEFT JOIN taskTally tl on w.workOrderTaskId=tl.workOrderTaskId
        LEFT JOIN element e ON w.parentTaskId=e.elementId
        WHERE w.workOrderId=" . intval($workOrderId) . " AND w.parentTaskId is not null  ORDER BY FIELD(elementName, 'General') DESC, internalTaskStatus DESC";

    }
   
    $res=$db->query($query);

    $out=[];
    $parents=[];
    $elements=[];

    while( $row=$res->fetch_assoc() ) {
        $out[]=$row;
        if( $row['parentId']!=null ) {
        $parents[$row['parentId']] = 1;
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

        if( isset($parents[$out[$i]['id']])  ) {
            $out[$i]['hasChildren'] = true;
        }
        if ( $out[$i]['elementName'] == null ) {
            $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
        }

    }

    $data[] = $out;


    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>