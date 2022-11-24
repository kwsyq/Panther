<?php
/*  ajax/taskchilds.php

    $_REQUEST['parentId']: primary key in DB table Task
    $_REQUEST['alphabetical']: optional, default 'false'. If 'true' order alphabetically by description, otherwise use sortOrder.
      Everything related to 'alphabetical' added 2020-08-24 JM for v2020-4, to address http://bt.dev2.ssseng.com/view.php?id=229 

    Returns data about the specified task, and all the child tasks of the specifed task.
    // >>>00012: "childs" is rather painful non-English, why not "children"?

    Returns JSON for an associative array with the following members:    
        * 'task': associative array of:
            * 'taskId'
            * 'icon'
            * 'description'
            * 'billingDescription'
            * 'estQuantity'
            * 'estCost'
            * 'taskTypeId'
            * 'sortOrder' 
        * 'childs': an array of similar associative arrays, with one associative array per child.
        
   NOTE that this goes down only one level: nothing here about the children's children.
   
   >>>00002, >>>00016: should validate inputs.
*/    

include '../inc/config.php';
include '../inc/access.php';

$db = DB::getInstance();

$parentId = isset($_REQUEST['parentId']) ? intval($_REQUEST['parentId']) : 0;
$alphabetical = isset($_REQUEST['alphabetical']) && $_REQUEST['alphabetical'] == 'true';
$task = new Task($parentId);
$childs = $task->getChilds($alphabetical);

$data['task'] = $task->toArray();
foreach ($childs as $child) {
    $data['childs'][] = $child->toArray();    
}
if ($alphabetical) {
}

header('Content-Type: application/json');
echo json_encode($data);
die();

?>