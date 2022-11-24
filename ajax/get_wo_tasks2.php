<?php
/*  ajax/get_wo_tasks2.php

    Usage: on workordertasks.php, WorkOrder Templates, get the workOrderTasks tree data. 
    
    Possible actions: 
        *Searching a job by number, display all the workorders with workOrderTasks tree data.

    INPUT $_REQUEST['workOrderId']: primary key in DB table workOrder.

    Returns JSON for an associative array with the following members:    
        * 'data': array. Each element is an associative array with elements:
            * 'elementId': identifies the element.
            * 'elementName': identifies the element name.
            * 'taskId': identifies the task.
            * 'parentTaskId': is null for the element, for a workorderTask is the id of the parent.
            * 'workOrderTaskId': identifies the workOrderTask.
*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $workOrderId = isset($_REQUEST['workOrderId']) ? $_REQUEST['workOrderId'] : '';
    $telerikjob = isset($_REQUEST['telerikjob']) ? $_REQUEST['telerikjob'] : false;
  

    $query = "select c.elementId, c.elementName, d.taskId, d.text, d.parentTaskId, d.workOrderTaskId from
    (
    select e.elementId, e.elementName
    from element e left join workOrder wo on e.jobId=wo.jobId
    where wo.workOrderId=" . intval($workOrderId) . " ) c
    left join
    (
    SELECT t.taskId, t.description as text, w.parentTaskId, w.workOrderTaskId, e.elementId, e.elementName
    FROM " . DB__NEW_DATABASE . ".task t JOIN " . DB__NEW_DATABASE . ".workOrderTask w ON w.taskId = t.taskId
    JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement we ON we.workOrderTaskId = w.workOrderTaskId
    left JOIN " . DB__NEW_DATABASE . ".element e ON e.elementId=we.elementId
    WHERE t.taskId <> 1 AND w.workOrderId = " . intval($workOrderId) . " ) d on c.elementId=d.elementId";
    
   
   
    $elementTasks = array();
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $elementTasks[] = $row;
      
        }
    }

  
        $newPack2 = array();
        $allTasksPack2 = array();
        foreach ($elementTasks as $a) {
    
            $newPack2[$a['parentTaskId']][] = $a;

        }
  
      
    
    
          
        foreach($elementTasks as $key=>$value) {

            if(  $value["parentTaskId"] == $value["elementId"] ) {
    
                $createAllTasks3 = createTreePack2($newPack2, array($elementTasks[$key]));
            
                $found = false;
                foreach($data as $k=>$v) {
                
                    $tree = array();
                    if($v['elementId'] == $value['parentTaskId']) {
                        $data[$k]['items'][] = $createAllTasks3[0];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $node = array();

                    $node['items'][] = $createAllTasks3[0];
                    $node['elementId'] = $value['elementId'];
                    $node['text'] = $value['elementName'];
                
                    $data[] = $node;
                }
            } else if ( $value["parentTaskId"] == null && $telerikjob == false) {
                $node2 = array();
                $node2['elementId'] = $value['elementId'];
                $node2['text'] = $value['elementName'];
                $data[] = $node2;
            } 
        }
        
     
        function createTreePack2(&$listPack3, $parent) {
   
            $tree = array();
            foreach ($parent as $k=>$l ) {
    
             
                if(isset($listPack3[$l['workOrderTaskId']]) ) {
    
                    $l['items'] = createTreePack2($listPack3, $listPack3[$l['workOrderTaskId']]);
                  
                }
          
                $tree[] = $l;
 
               
            } 
      
            return $tree;
        }
    



    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>