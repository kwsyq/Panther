<?php


use Ahc\Jwt\JWT;
require './vendor/autoload.php';

$isDebug=1;
/*
function base64url_decode($data) {
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
*/
require_once "../inc/config.php";
require_once "../inc/functions.php";

if($isDebug==1 && isset($_REQUEST['woid'])){
	$woid=$_REQUEST['woid'];
} else { // use token
	$token=isset($_REQUEST['token'])?$_REQUEST['token']:"";
	//$token="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ3byI6MTA1NDF9.djmfUMjX6DYJlEF93lpIktNixBk13iVJKsXV4CzeOW4";
	$retdata=array();

	if($token==""){
		$retdata['status']="error";
		$retdata['message']="Token not present";
		header('Content-Type: application/json');
		echo json_encode($retdata);
		exit;
	}
	
	$passdecoded=base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU");

	$jwt = new JWT($passdecoded, 'HS256', 3600, 10);
	$claims=$jwt->decode($token);

	if(!isset($claims['wo'])){
		$retdata['status']="error";
		$retdata['message']="Token not valid!";
		header('Content-Type: application/json');
		echo json_encode($retdata);
		exit;	
	}
	$woid=$claims['wo'];	
}
/*
if(isset($_REQUEST['woid'])){
	$woid=$_REQUEST['woid'];
}
*/

$db=DB::getInstance();

//$woid=$_GET['workorderId'];

$output=[
	'workOrderId' => $woid,
	'workOrderDescription' => '',
	'workOrderGenesisDate' => '',
	'jobNumber' => '',
	'jobName' => '',
	'Client' => [
		'Name' => '',
		'Company' =>'', 
		'StreetAddress' => '',
		'City' => '',
		'State' => '',
		'Zip' => '',
		'Phone' => '',
		'Email' => '',
		'Origin' => '',
	],
	'DesignProfessional' => [
		'Name' => '',
		'Company' => '',
		'StreetAddress' => '',
		'City' => '',
		'State' => '',
		'Zip' => '',
		'Phone' => '',
		'Email' => '',
		'Origin' => '',
	],
	'EOR' => [
		'Name' => '',
		'Company' => '',
		'StreetAddress' => '',
		'City' => '',
		'State' => '',
		'Zip' => '',
		'Phone' => '',
		'Email' => '',
		'Origin' => '',
	],
	'LocationInfo' => [
		'locationName' => '',
		'locationAddress1' => '',
		'locationAddress2' => '',
		'locationSuite' => '',
		'locationCity' => '',
		'locationState' => '',
		'locationCountry' => '',
		'locationPostalCode' => '',
		'locationLatitude' => '',
		'locationLongitude' => '',
	]
];

$resServicesLoads=$db->query("select slv.loadVarName, sl.loadName from serviceLoadVar slv, serviceLoad sl where slv.serviceLoadId=sl.serviceLoadId ");
while($rowsl=$resServicesLoads->fetch_assoc()){
	$output[str_replace("-", "", str_replace(" ", "", $rowsl['loadName']))][str_replace("-", "", str_replace(" ", "", $rowsl['loadVarName']))]='';
}

$wo=$db->query("select * from workOrder where workOrderId=$woid")->fetch_assoc();

if(!$wo){
    $output['Status'] = 'Workorder not exists'.$woid;
    header('Content-Type: application/json');
	echo json_encode($output);
    die();

}

//echo "select typeName from workOrderDescriptionType where workOrderDescriptionType=".$wo['workOrderDescriptionTypeId'];

$jobId=$wo['jobId'];

$output['workOrderDescriptionTypeId']=$wo['workOrderDescriptionTypeId'];
$output['workOrderDescriptionType']=$db->query("select typeName from workOrderDescriptionType where workOrderDescriptionTypeId=".$wo['workOrderDescriptionTypeId'])->fetch_assoc()['typeName'];
$output['workOrderDescription']=$wo['description'];
$output['workOrderGenesisDate']=($wo['genesisDate']===null?'':$wo['genesisDate']);

$output['jobId'] = $jobId;

$job=$db->query("select * from job where jobId=$jobId")->fetch_assoc();
$output['jobNumber'] = $job['number'];
$output['jobName'] = $job['name'];

$query = "select t.inTable,t.companyPersonId,t.role as position,t.description,t.active,t.teamId,tp.teamPositionId ,p.personId 
		, p.firstName, p.lastName, c.companyName, tp.name,tp.description as tpdescription 
		from team t 
		join companyPerson cp on t.companyPersonId = cp.companyPersonId 
		 left join teamPosition tp on t.teamPositionId = tp.teamPositionId 
		  join person p on cp.personId = p.personId 
		   join company c on cp.companyId = c.companyId 
		 where t.id = $woid
         and t.inTable = 1 order by teamPositionId";
 
$eor=0;

$result = $db->query($query);

if (!$result) {
    $output['Status'] = 'Select person details failed: Hard DB error';
    header('Content-Type: application/json');
	echo json_encode($output);
    die();
}

if ($result->num_rows <= 0) {
    $output['Status'] = 'no data '. $result->num_rows;
	header('Content-Type: application/json');
	echo json_encode($output);
    die();
}
$teamJob=array();

while($rowTeamJob=$result->fetch_assoc()){
	$teamJob[]=$rowTeamJob;
}

//$teamJob = $result->fetch_all(MYSQLI_ASSOC);
$isClientOk=false;
$isDesignProfessionalOk=false;
$isEOROk=false;
$clientOrigin="";
$designProfessionalOrigin="";
$eorOrigin="";
foreach($teamJob as $member){
    $teamPositionId = $member['teamPositionId'];
	if($teamPositionId==1){ // Client

        $output['Client']['Name'] = $member['firstName']." ".$member['lastName'];
		$output['Client']['Company'] = $member['companyName'];

		$ct=new CompanyPerson($member['companyPersonId']);

		$contacts=$ct->getContacts();

		foreach($contacts as $contact){
			if($contact['type']=="Email"){
				$output['Client']['Email'] = $contact['dat'];
			}
			else if($contact['type']=="Phone"){
				$output['Client']['Phone'] = $contact['dat'];
			}
			else if($contact['type']=="Location"){
				$loc=new Location($contact['id']);
				$output['Client']['StreetAddress']=$loc->getAddress1();
				$output['Client']['City']=$loc->getCity();
				$output['Client']['State']=$loc->getState();
				$output['Client']['Zip']=$loc->getPostalCode();
			}

		}
		$isClientOk=true;
        $output['Client']['Origin'] = 'WorkOrder';
	}
	if($teamPositionId==2){ // Design Professional

		$ct=new CompanyPerson($member['companyPersonId']);

		$contacts=$ct->getContacts();
		$output['DesignProfessional']['Name'] = $member['firstName']." ".$member['lastName'];
		$output['DesignProfessional']['Company'] = $member['companyName'];

		foreach($contacts as $contact){
			if($contact['type']=="Email"){
				$output['DesignProfessional']['Email'] = $contact['dat'];
			}
			if($contact['type']=="Phone"){
				$output['DesignProfessional']['Phone'] = $contact['dat'];
			}
			if($contact['type']=="Location"){
				$loc=new Location($contact['id']);
				$output['DesignProfessional']['StreetAddress']=$loc->getAddress1();
				$output['DesignProfessional']['City']=$loc->getCity();
				$output['DesignProfessional']['State']=$loc->getState();
				$output['DesignProfessional']['Zip']=$loc->getPostalCode();
			}

		}	
		$isDesignProfessionalOk=true;
        $output['DesignProfessional']['Origin'] = 'WorkOrder';
	}
	if($teamPositionId==3){ // EOR
		$output['EOR']['Name'] = $member['firstName']." ".$member['lastName'];
		$isEOROk=true;
        $output['EOR']['Origin'] = 'WorkOrder';
	}
}

if(!($isClientOk && $isDesignProfessionalOk && $isEOROk)){

	$query = "select t.inTable,t.companyPersonId,t.role as position,t.description,t.active,t.teamId,tp.teamPositionId ,p.personId 
			, p.firstName, p.lastName, c.companyName, tp.name,tp.description as tpdescription 
			from team t 
			join companyPerson cp on t.companyPersonId = cp.companyPersonId 
			 left join teamPosition tp on t.teamPositionId = tp.teamPositionId 
			  join person p on cp.personId = p.personId 
			   join company c on cp.companyId = c.companyId 
			 where t.id = $jobId
	         and t.inTable = 2 order by teamPositionId";
	 
	$eor=0;

	$result = $db->query($query);

	if (!$result) {
	    $output['Status'] = 'Select person details failed: Hard DB error';
	    header('Content-Type: application/json');
		echo json_encode($output);
	    die();
	}

	if ($result->num_rows > 0) {
		$teamJob=array();

		while($rowTeamJob=$result->fetch_assoc()){
			$teamJob[]=$rowTeamJob;
		}

		foreach($teamJob as $member){
		    $teamPositionId = $member['teamPositionId'];
			if($teamPositionId==1 && !$isClientOk){ // Client

		        $output['Client']['Name'] = $member['firstName']." ".$member['lastName'];
				$output['Client']['Company'] = $member['companyName'];

				$ct=new CompanyPerson($member['companyPersonId']);

				$contacts=$ct->getContacts();

				foreach($contacts as $contact){
					if($contact['type']=="Email"){
						$output['Client']['Email'] = $contact['dat'];
					}
					else if($contact['type']=="Phone"){
						$output['Client']['Phone'] = $contact['dat'];
					}
					else if($contact['type']=="Location"){
						$loc=new Location($contact['id']);
						$output['Client']['StreetAddress']=$loc->getAddress1();
						$output['Client']['City']=$loc->getCity();
						$output['Client']['State']=$loc->getState();
						$output['Client']['Zip']=$loc->getPostalCode();
					}

				}
				$output['Client']['Origin'] = 'Job';
				$isClientOk=true;
			}
			if($teamPositionId==2 && !$isDesignProfessionalOk){ // Design Professional

				$ct=new CompanyPerson($member['companyPersonId']);

				$contacts=$ct->getContacts();
				$output['DesignProfessional']['Name'] = $member['firstName']." ".$member['lastName'];
				$output['DesignProfessional']['Company'] = $member['companyName'];

				foreach($contacts as $contact){
					if($contact['type']=="Email"){
						$output['DesignProfessional']['Email'] = $contact['dat'];
					}
					if($contact['type']=="Phone"){
						$output['DesignProfessional']['Phone'] = $contact['dat'];
					}
					if($contact['type']=="Location"){
						$loc=new Location($contact['id']);
						$output['DesignProfessional']['StreetAddress']=$loc->getAddress1();
						$output['DesignProfessional']['City']=$loc->getCity();
						$output['DesignProfessional']['State']=$loc->getState();
						$output['DesignProfessional']['Zip']=$loc->getPostalCode();
					}

				}	
				$output['DesignProfessional']['Origin'] = 'Job';
				$isDesignProfessionalOk=true;
			}
			if($teamPositionId==3 && !$isEOROk){ // EOR
				$output['EOR']['Name'] = $member['firstName']." ".$member['lastName'];
				$isEOROk=true;
				$output['EOR']['Origin'] = 'Job';
				$isEOROk=true;
			}
		}
	}
}

$resWo=$db->query("select * from team where inTable=1 and teamPositionId=3 and id=$woid");

$teamWo=array();

while($rowTeamWo=$resWo->fetch_assoc()){
	$teamWo[]=$rowTeamWo;
}
//->fetch_all(MYSQLI_ASSOC);

if(count($teamWo)>0){
	$output['EOR']['Name'] = $member['firstName']." ".$member['lastName'];
}

$location=$db->query("select locationId from job jl where jl.jobId = $jobId")->fetch_assoc();
if($location){
	$jobLocation=new Location($location['locationId']);

	$output['LocationInfo']['locationName'] = $jobLocation->getName();
	$output['LocationInfo']['locationAddress1']= $jobLocation->getAddress1();
	$output['LocationInfo']['locationAddress2'] = $jobLocation->getAddress2();
	$output['LocationInfo']['locationSuite'] = $jobLocation->getSuite();
	$output['LocationInfo']['locationCity'] = $jobLocation->getCity();
	$output['LocationInfo']['locationState'] = $jobLocation->getState();
	$output['LocationInfo']['locationCountry'] = $jobLocation->getCountry();
	$output['LocationInfo']['locationPostalCode'] = $jobLocation->getPostalCode();
	$output['LocationInfo']['locationLatitude'] = $jobLocation->getLatitude();
	$output['LocationInfo']['locationLongitude'] = $jobLocation->getLongitude();

	$services=$jobLocation->getServiceLoad();
	foreach ($services as $row) {
		$output[str_replace("-", "", str_replace(" ", "", $row['loadName']))][str_replace("-", "", str_replace(" ", "", $row['loadVarName']))]=$row['varValue'];
	}
}

$jobObj= new Job($jobId);
if($jobObj){
	$elements=$jobObj->getElements();
	$i=1;
	$elem=array();

	foreach($elements as $element){
		$elem[]=$element->getElementName();
	}
	$output['Elements']=$elem;
}
header('Content-Type: application/json');
echo json_encode($output);

exit;

?>