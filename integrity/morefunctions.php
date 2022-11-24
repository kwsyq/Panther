


<?php
   
/*

  This is still a draft


*/
   
    
require_once( "../inc/config.php");
    
function externalLinksDetail($databaseTable, $primaryKeyName, $primaryKeyValue) {
    global $db, $logger;
    $db=DB::getInstance();
    $output=array();
    
    $output['result']=false;
    $output['data']="";
    
    $signatureForErrorReporting = 'canDelete("' . $databaseTable . '", "' . $primaryKeyName . '", ' . $primaryKeyValue . ')';
    if( ! intval($primaryKeyValue) ) {
        $logger->error2('1592579546', 'primaryKeyValue (third argument) is not a number: ' . $signatureForErrorReporting);
        $output['data']='primaryKeyValue (third argument) is not a number: ' . $signatureForErrorReporting;
        return json_encode($output);
    }
    if ($primaryKeyValue == 0) {
        $logger->warn2('1592579548', 'primaryKeyValue (third argument) is zero. Free to delete. ' . $signatureForErrorReporting);
        $output['result']=true;
        $output['data']='primaryKeyValue (third argument) is zero. Free to delete. ' . $signatureForErrorReporting;
        return json_encode($output);
    }
    if ($primaryKeyValue < 0) {
        $logger->error2('1592579549', 'primaryKeyValue (third argument) is negative. MUST investigate. ' . $signatureForErrorReporting);
        $output['result']=false;
        $output['data']='primaryKeyValue (third argument) is negative. MUST investigate. ' . $signatureForErrorReporting;
        return json_encode($output);
    }

    $query = "SELECT * FROM ".DB__NEW_DATABASE.".integrityData " .
             "WHERE (isPrimaryKey=1) and (externalTableField = '" . $db->real_escape_string($primaryKeyName) . "' AND tableName <> '" . $db->real_escape_string($databaseTable) . "' " . 
             "OR (rule IS NOT NULL AND rule LIKE '%" . $db->real_escape_string($primaryKeyName) . "%'));";
    $resultMain = $db->query($query);
    if (!$resultMain) {
        $logger->errorDB('1592579591', "Hard DB error", $db);
        $output['result']=false;
        $output['data']="Hard DB error" . var_dump($db, true);
        return json_encode($output);
    }

    $rowsFound=array();
    while ($row=$resultMain->fetch_assoc()){
        if ($row['rule'] === null) {
            $query = "SELECT * " . 
                    "FROM  ". DB__NEW_DATABASE .".". $db->real_escape_string($row['tableName']) . " " .
                    "WHERE ". $db->real_escape_string($primaryKeyName) . "= $primaryKeyValue ";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDB('1592579620', "Hard DB error", $db);
                $output['result']=false;
                $output['data']="Hard DB error" . var_dump($db, true);
                return json_encode($output);
            }
            while($tableRow=$result->fetch_assoc()) {
                $tmp=array();
                $tmp['tableName']= $row['tableName'];
                $tmp['tableKey']= $row['tableName']."Id"; // ??????
                $tmp['tableKeyValue']= $tableRow[$tmp['tableKey']]; // ??????
                $tmp['data']=$tableRow;
                $rowsFound[]=$tmp;
            }            
        } else {
            $rules = json_decode($row['rule'], true);
            foreach ($rules['rules'] as $rule) {
                if ($rule['field'] == $primaryKeyName) { // filter out false positives
                    $query = "SELECT " . $row['tableField'] . 
                             "FROM ". DB__NEW_DATABASE . ".". $db->real_escape_string($row['tableName']) . " ".
                             "WHERE " . $row['tableField'] ."=$primaryKeyValue " . 
                             "AND ". $db->real_escape_string($rules['fieldName']) . "=". $db->real_escape_string($rule['id']) . " " .
                             "LIMIT 1;";
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDB('1592579775', "Hard DB error", $db);
                        $output['result']=false;
                        $output['data']="Hard DB error" . var_dump($db, true);
                        return json_encode($output);
                    }
                    while($tableRow=$result->fetch_assoc()) {
                        $tmp=array();
                        $tmp['tableName']= $row['tableName'];
                        $tmp['tableKey']= $row['tableName']."Id"; // ??????
                        $tmp['tableKeyValue']= $tableRow[$tmp['tableKey']]; // ??????
                        $tmp['data']=$tableRow;
                        $rowsFound[]=$tmp;
                    }            
                }
            }
        }
    }
    $output['result']=true;
    $output['data']=$rowsFound;
    
    return json_encode($output);
}
    
  
   //echo externalLinksDetail('companyPerson', 'companyPersonId', 3300);
    
    
?>