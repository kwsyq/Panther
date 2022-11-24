<?php

require_once './inc/config.php';

$ctr=new Contract(791);

$woId=$ctr->getWorkOrderId();
echo $woId;

$wo=new WorkOrder($woId);

$cl=$wo->getTeamPosition(TEAM_POS_ID_CLIENT, 0, 1);
print_r($cl);
$client=new CompanyPerson($cl[0]['companyPersonId']);

print_r($client);

die();

?>