<?php 
//session_start();
    ini_set('memory_limit', '-1');
    $_SESSION['username']='rskinner@ssseng.com';

    require_once '../inc/config.php';
    //require_once '../inc/access.php';
    $db=DB::getInstance();
    $query = "select * from masterList3";
    $result = $db->query($query);
    $rows=[];
    while($row=$result->fetch_assoc()){
        $rows[]=$row;
    };
    header('Content-Type: application/json');
    echo json_encode($rows);






?>