<?php
/*  ajax/get_wo_tasks3.php

    Usage: on workorder.php, get the workOrderTasks tree data. 
    Possible actions: 
        *Assign person to a workOrderTask
        *Assign person to all workOrderTasks of an element
        *Add note to a workOrderTask.

    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrder.

    Returns JSON for an associative array with the following members:    
        * 'data': array. Each element is an associative array with elements:
            * 'elementId': identifies the element.
            * 'elementName': identifies the element name.
            * 'parentId': is null for the element, for a workorderTask is the id of the parent.
            * 'taskId': identifies the task.
            * 'parentTaskId': alias 'parentId', is null for the element, for a workorderTask is the id of the parent.
            * 'workOrderTaskId': identifies the workOrderTask.
            * 'extraDescription':  extra description for a specific workOrderTask.
            * 'icon':  icon for a specific workOrderTask.
            * 'wikiLink':  Link to Wiki for a specific workOrderTask.
            * 'taskStatusId':  status for a specific workOrderTask ( active / inactive ).
            * 'tally':  tally for a specific workOrderTask, default 0.
            * 'hoursTime':  time in minutes for a specific workOrderTask, available in workOrderTaskTime.
            * 'personInitials': the initials of a person added to a specific workOrderTask.
            * 'noteId': identifies the note for a specific workOrderTask, table note.
            * 'noteText': the text of the for a specific workOrderTask, table note.
            * 'inserted': identifies the person who wrote the note, table note.
            * 'firstName': identifies the person firstName who wrote the note, table note.
            * 'hasChildren': identifies if a element/ woT has childrens.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';

   
  
  
    $query = "SELECT elementId as id, elementName as Title, null as parentId, 
    null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription,  null as internalTaskStatus, '' as icon, '' as wikiLink, null as taskStatusId, null as tally, null as hoursTime,  
    null as personInitials, '' as noteId, '' as noteText, '' as inserted, '' as firstName, elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
    from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".$workOrderId.")
    UNION ALL
    SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId, 
    w.extraDescription as extraDescription, w.internalTaskStatus as internalTaskStatus, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId, tl.tally as tally, wt.tiiHrs as hoursTime, wopi.legacyInitials, 
    nv.id as noteId, nv.noteText as noteText, nv.inserted as inserted, nv.firstName as firstName, getElement(w.workOrderTaskId),
    e.elementName, false as Expanded, false as hasChildren
    from workOrderTask w
    LEFT JOIN task t on w.taskId=t.taskId
    LEFT JOIN taskTally tl on w.workOrderTaskId=tl.workOrderTaskId
   
    LEFT JOIN (

        SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
        FROM workOrderTaskTime wtH
        GROUP BY wtH.workOrderTaskId
        ) AS wt
        on wt.workOrderTaskId=w.workOrderTaskId



    LEFT JOIN (

        SELECT 
        nt.id, nt.noteText, nt.inserted, ps.firstName
        FROM note nt
        LEFT JOIN person ps on ps.personId=nt.personId   
        GROUP BY nt.id
        ) AS nv
        ON nv.id=w.workOrderTaskId

    LEFT JOIN (

        SELECT 
        wop.workOrderTaskId, GROUP_CONCAT( cp.legacyInitials SEPARATOR ', ') as legacyInitials
        FROM workOrderTaskPerson wop
        LEFT JOIN customerPerson cp on cp.personId=wop.personId   
        GROUP BY wop.workOrderTaskId
        ) AS wopi
        ON wopi.workOrderTaskId=w.workOrderTaskId

    LEFT JOIN element e on w.parentTaskId=e.elementId
    WHERE w.workOrderId=".$workOrderId." AND w.parentTaskId is not null ORDER BY FIELD(elementName, 'General') DESC, internalTaskStatus DESC";

    
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