<?php
/* ajax/billingprofileaction.php

    INPUT $_REQUEST['act'] - one of 'removePrimary', 'addPrimary', 'activate', 'deactivate'; sets $act in config.php
    INPUT $_REQUEST['companyId'] - required for $_REQUEST['act']=='removePrimary', $_REQUEST['act']=='addPrimary'
    INPUT $_REQUEST['billingProfileId'] - required for $_REQUEST['act']=='addPrimary', $_REQUEST['act']=='activate', $_REQUEST['act']=='deactivate'
    
*/
require_once '../inc/config.php';
require_once '../inc/access.php';

$data = array();
$data['status'] = 'fail';
$data['error'] = '';

$v=new Validator2($_REQUEST);
$v->rule('required', ['act']);
$v->rule('integer', ['companyId', 'billingProfileId']); 
$v->rule('min', 'companyId', 1);
$v->rule('min', 'billingProfileId', 1);

if(!$v->validate()){
    $logger->error2('1581623845', "Error input parameters ".json_encode($v->errors()));
	header('Content-Type: application/json');
    echo $v->getErrorJson();
    exit;
}

if (array_key_exists('companyId', $_REQUEST)) {
    $companyId = $_REQUEST['companyId'];
    if (!Company::validate($companyId)) {
        $data['error'] = "Invalid companyId {$_REQUEST['companyId']}";
        $logger->error2('1581623848', $data['error']);
    }
}

if ( !$data['error'] ) {
    if (array_key_exists('billingProfileId', $_REQUEST)) {
        $billingProfileId = $_REQUEST['billingProfileId'];
        if (!BillingProfile::validate($billingProfileId)) {
            $data['error'] = "Invalid billingProfileId {$_REQUEST['billingProfileId']}";
            $logger->error2('1581623848', $data['error']);
        }
    }
}

if ( !$data['error'] ) {
    if ( $act == 'removePrimary' || $act == 'addPrimary' ) {
        if (isset($companyId)) {
            $company = new Company($companyId);
            if (!$company) {
                $data['error'] = "$act failed to build Company object";
                $logger->error2('1581623852', $data['error']);
            }
        } else {
            $data['error'] = "$act requires companyId";
            $logger->error2('1581623853', $data['error']);
        }
    }
}

if ( !$data['error'] ) {
    if ( $act == 'addPrimary' || $act=='activate' || $act=='deactivate' ) {
        if (isset($billingProfileId)) {
            $billingProfile = new BillingProfile($billingProfileId);
            if (!$billingProfile) {
                $data['error'] = "$act failed to build BillingProfile object";
                $logger->error2('1581623869', $data['error']);
            }
        } else {
            $data['error'] = "$act requires billingProfileId";
            $logger->error2('1581623870', $data['error']);
        }
    }
}

if ( !$data['error'] ) {
    if ($act == 'removePrimary') {
        $logger->info2('1581630445', "Set NO primary billing profile for {$company->getCompanyName()} ({$company->getCompanyId()})");
        $company->setPrimaryBillingProfileId(null);
        $company->save();
    } else if ($act == 'addPrimary') {
        $logger->info2('1581630446', "Set primary billing profile $billingProfileId for {$company->getCompanyName()} ({$company->getCompanyId()})");
        $company->setPrimaryBillingProfileId($billingProfileId);
        $company->save();
    } else if ($act=='activate') {
        $logger->info2('1581630447', "Activate billing profile {$billingProfile->getBillingProfileId()} for company {$billingProfile->getCompanyId()}");
        $billingProfile->setActive(true);
        $billingProfile->save();
    } else if ($act=='deactivate') {
        $logger->info2('1581630447', "Deactivate billing profile {$billingProfile->getBillingProfileId()} for company {$billingProfile->getCompanyId()}");
        $billingProfile->setActive(false);
        $billingProfile->save();
    }
}

if ( !$data['error'] ) {
    $data['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($data);
?>