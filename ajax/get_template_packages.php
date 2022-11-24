<?php
/*  ajax/get_template_packages.php

    Usage: on workordertasks.php, Templates tab, get the Templates Packages 
        and the coresponding workOrderTasks tree data order by the sort value.

    Possible actions: 
        *On select from dropdown : Date asc, Date desc, Name asc, Name desc. 
            The Templates Packages will be ordered by the specified selection.
        *On Templates tab, display all the Templates Packages and the workOrderTasks tree data for each package.


    INPUT $_REQUEST['value_sort']. Possible value: Date asc, Date desc, Name asc, Name desc.

    Returns JSON for an associative array with the following members:    
    * 'data': array. Each element is an associative array with elements:
        * 'taskId': identifies the task.
        * 'description': text of the workorderTask/ package name.
        * 'taskPackageTaskId': identifies the task in a Package ( table taskPackageTask).
        * 'parentTaskId': is 1000 for the package, for a workorderTask is the id of the parent.
        * 'taskPackageId': identifies the task Package ( table taskPackage ).

*/

    include '../inc/config.php';
    include '../inc/access.php';

    $db = DB::getInstance();
    $data = array();

    $allPackagesIds = array();
    $allPackages = array();
    $value_sort =  isset($_REQUEST['value_sort']) ? trim($_REQUEST['value_sort']) : 'taskPackageId ASC';

    $query = " SELECT taskPackageId, packageName";
    $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackage ORDER BY " . $db->real_escape_string($value_sort) . " ";


    $taskIds = array();            
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allPackagesIds[] = $row["taskPackageId"];
        }
    } else {
        $logger->errorDB('1594234143344', 'Hard DB error', $db);
    }


    foreach ($allPackagesIds as $package) {

        $query = " SELECT t.taskId, t.description as text, tp.packageName, tpt.taskPackageTaskId, tpt.parentTaskId, tpt.taskPackageId ";
        $query .= " FROM  " . DB__NEW_DATABASE . ".taskPackageTask tpt ";
        $query .= " JOIN  " . DB__NEW_DATABASE . ".taskPackage tp ON tp.taskPackageId = tpt.taskPackageId ";
        $query .= " JOIN   " . DB__NEW_DATABASE . ".task t ON tpt.taskId = t.taskId ";
        $query .= " WHERE tpt.taskPackageId = " . intval($package) . "";


        $result = $db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $allPackages[] = $row;
            }
        }
    }

    unset($allPackagesIds, $package);
    
 
    $newPack = array();
    $allTasksPack = array();
    foreach ($allPackages as $a) {
     
        $newPack[$a['parentTaskId']][] = $a;
    
    }
  
    if($allPackages) {
      
        foreach($allPackages as $key=>$value) {
      
            
            if($value["parentTaskId"] == "1000" ) {
          
                $createAllTasks2 = createTreePack($newPack, array($allPackages[$key]));

                $found = false;
                foreach($data as $k=>$v) {
                    if($v['taskPackageId'] == $value['taskPackageId']) {
                        $data[$k]['items'][] = $createAllTasks2[0];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $node = array();
                    $node['text'] = $value['packageName'];
                    $node['taskPackageId'] = $value['taskPackageId'];
                    $node['items'][] = $createAllTasks2[0];
                    $data[] = $node;
                }
               
            }
           
        }
    }
 
    function createTreePack(&$listPack, $parent) {

        $tree = array();
        foreach ($parent as $k=>$l ) {
            if(isset($listPack[$l['taskPackageTaskId']]) ) {
              
                $l['items'] = createTreePack($listPack, $listPack[$l['taskPackageTaskId']]);
            }
      
            $tree[] = $l;
           
        } 
  
        return $tree;
    }


    header('Content-Type: application/json');
    echo json_encode($data);
    die();
?>