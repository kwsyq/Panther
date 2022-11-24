<?php
/* ajax/setTeamPosition.php

   This was originally created for etc/multiple-client-report.php but could prove useful elsewhere.
   
   INPUT $_REQUEST['teamId'] - the row in the 'team' table for which we are modifying teamPositionId 
   INPUT $_REQUEST['teamPositionId'] - the new teamPositionId
*/
include '../inc/config.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);
$v->rule('required', ['teamId', 'teamPositionId']);
$v->rule('min', 'teamId', 1);
$v->rule('min', 'teamPositionId', 1);

if (!$v->validate()) {
    $data['error'] = "Error in input parameters ".json_encode($v->errors());
    $logger->error2('1600962731', $data['error']);
}

if (!$data['error']) {
    $teamId = intval($_REQUEST['teamId']);
    if (!TeamCompanyPerson::validate($teamId)) {
        $data['error'] = "$teamId is not a valid primary key in DB table 'team'";
        $logger->error2('1600962896', $data['error']);        
    }
}

if (!$data['error']) {
    $teamPositionId = intval($_REQUEST['teamPositionId']);
    if (!TeamCompanyPerson::validateTeamPositionId($teamPositionId)) {
        $data['error'] = "$teamPositionId is not a valid primary key in DB table teamPosition";
        $logger->error2('1600963176', $data['error']);        
    }
}

if (!$data['error']) {
    $teamCompanyPerson = new TeamCompanyPerson($teamId);    
    $team_array = array();  
    $team_array['teamPositionId'] = $teamPositionId;
    $teamCompanyPerson->update($team_array);	// The function takes an array.
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>