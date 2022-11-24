<?php
/*  ajax/vacationsummary.php

    NO INPUTs

    Get vacation time info for the current user.
    
    Returns JSON for an associative array with the following members:    
        * 'status': always "success".
        * 'total': Total vacation/sick time ever allocated to this person. Based on 
            DB table VacationTime, which was introduced in October 2018; adjustments
            were made at that time to bring everything in balance. It is possible
            that the effective starting date on tracked vacation for anyone employed at
            SSS at or before that time might be later than their actual hire date 
            In hours, as decimal number with two digits past the decimal point. 
            0 if somehow called by a non-employee.
        * 'used': total vacation/sick time ever used by this employee. In hours, as decimal number with two digits past the decimal point.
        * 'remain': In hours, as decimal number with two digits past the decimal point. Should always be total minus used.
            Parenthesized, as well as negative sign, if negative.
*/

include '../inc/config.php';
include '../inc/access.php';

$data = array();
$data['status'] = 'success';

$remain = 0;
$total = number_format((float)intval($user->getTotalVacationTime(Array('currentonly'=>true)))/60, 2, '.', '');
$used = number_format((float)intval($user->getVacationUsed())/60, 2, '.', '');

$remain = number_format((float)(intval($user->getTotalVacationTime(Array('currentonly'=>true))) - intval($user->getVacationUsed()))/60, 2, '.', '');

if ($remain < 0){
    $remain = '(' . $remain . ')';
}

$data['total'] = $total;
$data['used'] = $used;
$data['remain'] = $remain;

header('Content-Type: application/json');
echo json_encode($data);
die();

?>