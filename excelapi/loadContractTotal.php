<?php
    require_once "../inc/config.php";
    require_once "../inc/functions.php";
    $db = DB::getInstance();
    $db->select_db('sssengnew');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    //echo $db->error;
    $contracts = array();
    $sql = "select contractId, workOrderId from contract";
    $res = $db->query($sql);
    while ($row = $res->fetch_assoc()) {
        if(intval($row['workOrderId'])!=0) {
            $contracts[] = $row;
        }
    }
    foreach ($contracts as $c) {
        $workOrderId=intval($c['workOrderId']);
        $r = $db->query("select jobId from workOrder where workOrderId=" . $workOrderId);
        $jobId=-1;
        if($r){
            $line=$r->fetch_assoc();
            $jobId=$line['jobId'];
        }
        if ($jobId < 0) {
            echo $c['workOrderId'] . "<br>";
            continue;
        }
        if (intval($c['workOrderId']) > 14174) {
            echo $workOrderId."<br>";
            $workOrderId=intval($c['workOrderId']);
            $res = $db->query("CALL getWorkOrderTasks(" . $workOrderId . ")");
            //echo "errors: " . $db->error;
            $out = [];
            $parents = [];
            $elements = [];
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
                if ($row['parentId'] != null) {
                    $parents[$row['parentId']] = 1;
                }
                if ($row['taskId'] == null) {
                    $elements[$row['elementId']] = $row['elementName'];
                }
            }
            $res->close();
            if (count($out) == 0) {
                return 0;
            }
            for ($i = 0; $i < count($out); $i++) {
                if ($out[$i]['Expanded'] == 1) {
                    $out[$i]['Expanded'] = true;
                } else {
                    $out[$i]['Expanded'] = false;
                }
                if ($out[$i]['hasChildren'] == 1) {
                    $out[$i]['hasChildren'] = true;
                } else {
                    $out[$i]['hasChildren'] = false;
                }
                if (isset($parents[$out[$i]['id']])) {
                    $out[$i]['hasChildren'] = true;
                }
                if ($out[$i]['elementName'] == null) {
                    $out[$i]['elementName'] = (isset($elements[$out[$i]['elementId']]) ? $elements[$out[$i]['elementId']] : "");
                }
            }

            $query = " SELECT e.elementId ";
            $query .= " FROM " . DB__NEW_DATABASE . ".element e ";
            $query .= " RIGHT JOIN " . DB__NEW_DATABASE . ".workOrderTaskElement wo on wo.elementId = e.elementId ";
            $query .= " WHERE jobId = " . intval($jobId) . " group by e.elementId ";
            echo $query."<br>";

            $result1 = $db->query($query);
            //echo "result: ".$result1;


            $errorElements = '';
            if (!$result1) {
                $errorId = '637798491724341928';
                $errorElements = 'We could not retrive the cost for the elements. Database error. Error id: ' . $errorId;
                echo $db->error;
            }
            if ($errorElements) {
                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorElements</div>";
                echo $query;
            }
            continue;
            $allElements = [];
            if (!$errorElements) {
                while ($row = $result1->fetch_assoc()) {
                    $allElements[] = $row['elementId'];
                }
            }
            $result1->close();
            unset($errorElements);
            $elementsCost = [];
            $errorCostEl = '';
            foreach ($allElements as $value) {
                $query = "select workOrderTaskId,
		        parentTaskId, totCost
		        from    (select * from workOrderTask
		        order by parentTaskId, workOrderTaskId) products_sorted,
		        (select @pv := '$value') initialisation
		        where   find_in_set(parentTaskId, @pv) and parentTaskId = '$value' and workOrderId = '$workOrderId'
		        and     length(@pv := concat(@pv, ',', workOrderTaskId))";
                $result2 = $db->query($query);
                if (!$result2) {
                    $errorId = '637798493011752921';
                    $errorCostEl = 'We could not retrive the total cost for each Element. Database error. Error id: ' . $errorId;
                }
                if (!$errorCostEl) {
                    while ($row = $result2->fetch_assoc()) {
                        $elementsCost[$row['parentTaskId']][] = $row['totCost'];
                    }
                }
                $result2->close();
            }
            if ($errorCostEl) {
                echo "<div  class=\"alert alert-danger\" role=\"alert\" id=\"validator-warning\" style=\"color:red\">$errorCostEl</div>";
            }
            unset($errorCostEl);
            $sumTotalEl = 0;
            foreach ($elementsCost as $key => $el) {
                $elementsCost[$key] = array_sum($el);
                $sumTotalEl += array_sum($el);
            }
            print_r($elementsCost);
            return $sumTotalEl;
            die();
        }
    }
