<?php


include "../inc/config.php";

define('CHECK_OK', 1);
define('CHECK_LOCAL', 2);
define('CHECK_NOTOK_INTEGRITY', 3);
define('CHECK_NOTOK_NOTABLE', 4);
define('CHECK_EXTRA', 5);
$prefixes=["updated"];
$conn=new mysqli(DB__HOST, DB__USER, DB__PASS);
$conn->select_db(DB__NEW_DATABASE);
$database=DB__NEW_DATABASE;

$conn->query("drop table if exists integrityResult");

$conn->query("CREATE TABLE `integrityResult` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`tableName` VARCHAR(255) NULL,
	`externalTableName` VARCHAR(255) NULL,
	`columnName` VARCHAR(255) NULL,
	`checkResultCode` int unsigned not null default '0',
	`checkResult` TEXT NULL,
	`transferred` INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`)
)");


$query="select * from integrityData where active=1";
$res=$conn->query($query);
echo $query."<br>";
while($row=$res->fetch_assoc()){
    extract($row);
    if($status=="OK"){
        // check integrity data
        $query="select * from ".$tableName." where ".$tableField." not in (select ".$externalTableField." from ".$externalTableName.")";
        $result=$conn->query($query);
        if($result->num_rows==0){
            $conn->query("insert into integrityResult set 
                tableName='".$tableName."', 
                externalTableName = '".$externalTableName."' , 
                columnName='".$tableField."',
                checkResultCode=".CHECK_OK.",
                checkResult='OK', 
                transferred=0");				
            echo $tableName. " - ". $tableField. " - ". $externalTableName. " : OK "."<br>";
        } else {
            $datas=array();
            $datas[]=[
                "tableName" => $externalTableName,
                "results" => $result->fetch_all(MYSQLI_ASSOC)
            ];

            //$datas=$result->fetch_all(MYSQLI_ASSOC);
            $conn->query("insert into integrityResult set 
                tableName='".$tableName."', 
                externalTableName = '".$externalTableName."' , 
                columnName='".$tableField."',
                checkResultCode=".CHECK_NOTOK_INTEGRITY.",
                checkResult='".$conn->real_escape_string(json_encode($datas))."', 
                transferred=0");
                echo $tableName. " - ". $tableField. " - ". $externalTableName. " : Error ". $result->num_rows ." rows with broken integrity <br>";
        }
    } else if ($row['externalTableName']){
        $conn->query("insert into integrityResult set 
            tableName='".$tableName."', 
            externalTableName = '".$externalTableName."' , 
            columnName='".$tableField."',
            checkResultCode=".CHECK_NOTOK_NOTABLE.",
            checkResult='No link between this 2 tables (maybe the table $externalTableName does not exists)', 
            transferred=0");

            echo $query."<br>";
        continue;

    } else {
        if($rule){
            $rules=json_decode($rule, true);
            $fielddiff=$rules['fieldName'];
            $datas=array();
            foreach($rules['rules'] as $value){
                $query="select * from ".$tableName." where companyPersonContactTypeId=".$value['id']." and id not in (select ".$value['field']." as id from ".$value['table'].")";
                $resCount=$conn->query($query);
                if($resCount->num_rows>0){
                     $datas[]=[
                         "tableName" => $value['table'],
                         "results" => $resCount->fetch_all(MYSQLI_ASSOC)
                    ];
                    //$datas[]=json_encode($resCount->fetch_all(MYSQLI_ASSOC));
                }
            }

            if(count($datas)>0){
                $conn->query("insert into integrityResult set 
                    tableName='".$tableName."', 
                    externalTableName = '".$externalTableName."' , 
                    columnName='".$tableField."',
                    checkResultCode=".CHECK_NOTOK_INTEGRITY.",
                    checkResult='".$conn->real_escape_string(json_encode($datas))."', 
                    transferred=0");
                    print_r($conn);
                    continue;
            } else {
                    
                    $conn->query("insert into integrityResult set 
                        tableName='".$tableName."', 
                        externalTableName = '".$externalTableName."' , 
                        columnName='".$tableField."',
                        checkResultCode=".CHECK_OK.",
                        checkResult='OK', 
                        transferred=0");				
                    echo $tableName. " - ". $tableField. " - ". $externalTableName. " : OK "."<br>";
                    continue;
            }

        } else {
            $conn->query("insert into integrityResult set 
                tableName='".$tableName."', 
                externalTableName = '".$externalTableName."' , 
                columnName='".$tableField."',
                checkResultCode=".CHECK_EXTRA.",
                checkResult='Generic Id. Need extra investigation.', 
                transferred=0");
                print_r($conn);
                continue;
        }
   }
}

?>