<?php

include "../inc/config.php";

$db=DB::getInstance();
$db->select_db(DB__NEW_DATABASE);

$db->query("ALTER TABLE contractBillingProfile ADD COLUMN shadowBillingProfile2 text AFTER shadowBillingProfile");
if($db->error){
    echo $db->error."\n";
}

$db->query("ALTER TABLE invoiceBillingProfile ADD COLUMN shadowBillingProfile2 text AFTER shadowBillingProfile");
if($db->error){
    echo $db->error."\n";
}
$db->query("ALTER TABLE integrityData ADD COLUMN rule2 text AFTER rule");
if($db->error){
    echo $db->error."\n";
}
$db->query("ALTER TABLE integrityData ADD COLUMN deleted_at datetime AFTER rule2");
if($db->error){
    echo $db->error."\n";
}
$db->query("ALTER TABLE integrityData ADD COLUMN deleted_by varchar(50) AFTER deleted_at");
if($db->error){
    echo $db->error."\n";
}
$db->query("ALTER TABLE contractLanguage ADD COLUMN deleted_at datetime AFTER inserted");
if($db->error){
    echo $db->error."\n";
}
$db->query("ALTER TABLE contractLanguage ADD COLUMN deleted_by varchar(50) AFTER deleted_at");
if($db->error){
    echo $db->error."\n";
}
$db->query("update integrityData set rule2 = '". str_replace(" ", "", $db->real_escape_string('{
  "fieldName": "shadowBillingProfile2",
  "tests": [
    "billingProfileId",
    "companyId",
    "companyPersonId",
    "personEmailId",
    "companyEmailId",
    "personLocationId",
    "companyLocationId",
    "contractLanguageId"
  ]
}'))."' where tableName='invoiceBillingProfile' and tableField='invoiceBillingProfileId'");
if($db->error){
    echo $db->error."\n";
}

$db->query("update integrityData set rule2 = '". str_replace(" ", "", $db->real_escape_string('{
  "fieldName": "shadowBillingProfile2",
  "tests": [
    "billingProfileId",
    "companyId",
    "companyPersonId",
    "personEmailId",
    "companyEmailId",
    "personLocationId",
    "companyLocationId",
    "contractLanguageId"
  ]
}'))."' where tableName='contractBillingProfile' and tableField='contractBillingProfileId'");
if($db->error){
    echo $db->error."\n";
}


$db->query("update integrityData set rule2 = '". str_replace(" ", "", $db->real_escape_string('{
    "fieldName":"data2",
    "tests":[
        "elementId",
        "workOrderTaskId",
        "workOrderId",
        "taskId",
        "taskStatusId",
        "taskTypeId"
    ]
}'))."' where tableName='contract' and tableField='contractId'");
if($db->error){
    echo $db->error."\n";
}

$db->query("update integrityData set rule2 = '". str_replace(" ", "", $db->real_escape_string('{
    "fieldName":"data2",
    "tests":[
        "elementId",
        "workOrderTaskId",
        "workOrderId",
        "taskId",
        "taskStatusId",
        "taskTypeId"
    ]
}'))."' where tableName='invoice' and tableField='invoiceId'");

if($db->error){
    echo $db->error."\n";
}


$res=$db->query("select * from contractBillingProfile");
if($db->error){
    echo $db->error."\n";
}
while($row=$res->fetch_assoc())
{
    $sbp=$row['shadowBillingProfile'];
    $bp = unserialize(base64_decode($sbp));
    $db->query("update contractBillingProfile set shadowBillingProfile2='".$db->real_escape_string(json_encode($bp))."' where contractBillingProfileId=".$row['contractBillingProfileId']);
}

$res=$db->query("select * from invoiceBillingProfile");
while($row=$res->fetch_assoc())
{
    $sbp=$row['shadowBillingProfile'];
    $bp = unserialize(base64_decode($sbp));
    $db->query("update invoiceBillingProfile set shadowBillingProfile2='".$db->real_escape_string(json_encode($bp))."' where invoiceBillingProfileId=".$row['invoiceBillingProfileId']);
}

$res=$db->query("select * from invoice");
while($row=$res->fetch_assoc())
{
    if($row['data2']===null || $row['data2']==""){
        $dataField=$row['data'];
        $data2Field = unserialize(base64_decode($dataField));
        $db->query("update invoice set data2='".$db->real_escape_string(json_encode($dataField))."' where invoiceId=".$row['invoiceId']);
    }
}
$res=$db->query("select * from contract");
while($row=$res->fetch_assoc())
{
    if($row['data2']===null || $row['data2']=="")
    {
        $dataField=$row['data'];
        $data2Field = unserialize(base64_decode($dataField));
        $db->query("update contract set data2='".$db->real_escape_string(json_encode($data2Field))."' where contractId=".$row['contractId']);
    }
}

$res=$db->query("select * from invoice");
while($row=$res->fetch_assoc())
{
    if($row['data2']===null || $row['data2']=="")
    {
        $dataField=$row['data'];
        $data2Field = unserialize(base64_decode($dataField));
        $db->query("update invoice set data2='".$db->real_escape_string(json_encode($data2Field))."' where invoiceId=".$row['invoiceId']);
    }
}

$res=$db->query("select locationId from location");
while($row=$res->fetch_assoc())
{
    if(canDelete("location", "locationId", $row['locationId']))
    {
        $db->query("update location set customerId=2 where locationId=".$row['locationId']);
    }
}
$db->query("delete from location where customerId=2");

if($db->error){
    echo $db->error."\n";
}


// analise andadd comments
$db->query("delete from companyPerson where personId not in (select personId from person) and personId>2");
if($db->error){
    echo $db->error."\n";
}

$db->query("delete from companyPerson where companyId not in (select companyId from company) and companyId>1");
if($db->error){
    echo $db->error."\n";
}



