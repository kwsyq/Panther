#!/usr/bin/php -q
<?php
/*  crons/stateofunion.php

    EXECUTIVE SUMMARY: SMSs a report to certain email addresses specified in inc/config.php.
     Report is just the number of workOrders etc. in certain categories & how much time/money is involved.
     Categories are similar to what crons/ajaxdata.php puts in the database.
*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1589576531', "crons/stateofunion.php must be run from the command line, was apparently accessed some other way.");
	die();
}

$reconstructed_cmd = 'php';
for ($i=0; $i<count($argv); ++$i) {
    $reconstructed_cmd .= ' ';
    $reconstructed_cmd .= $argv[$i]; 
}

// Critical logging will happen in any case, but does the caller want more?
$logging = false; 
$start_time = time(); 
foreach ($argv as $i => $value) {
    if ($value == '-log') {
        $logging = true;
        array_splice($argv, $i, 1); // remove that
        $logger->info2('1589576568', "start crons/stateofunion.php: $reconstructed_cmd");        
        break;
    }
}
unset($value, $i);

$titles = array();
$titles[0] = '(wo closed, tasks open)';
$titles[1] = '(wo open, tasks closed)';
$titles[2] = '(wo no invoice)';
$titles[3] = '(wo closed, open invoice)';
$titles[4] = '(mailroom -- awaiting delivery)';
$titles[5] = '(aging sumary - awaiting payment)';
$titles[6] = '(cred recs)';   // >>>00001 Looks like this is not done here - JM 2019-04
$titles[7] = '(do payments)'; // >>>00001 Looks like this is not done here - JM 2019-04

$body = '';

$fin = new Financial();

$body .= "State of the union....\n\n";

// BEGIN REMOVED 2020-05-15 BY POWELL AT RON'S REQUEST, IN PRODUCTION. CHANGE PORTED BACK INTO CONTROLLED SOURCE BY uJM
// $ret = $fin->getWOClosedTasksOpen();
// $body .= $titles[0];
// $body .= "\n";
// $body .= 'Count : ' . count($ret['workOrders']) . "\n";
// $body .= "===============\n";
// 
// $body .= "\n";
// 
// $ret = $fin->getWOOpenTasksClosed();
// $body .= $titles[1];
// $body .= "\n";
// $body .= 'Count : ' . count($ret['workOrders']) . "\n";
// $body .= "===============\n";
// 
// $body .= "\n";
// END REMOVED 2020-05-15 BY POWELL AT RON'S REQUEST

$ret = $fin->getWONoInvoice();
$body .= $titles[2];
$body .= "\n";
$body .= 'Count : ' . count($ret['workOrders']) . "\n";
$body .= 'Hours : ' . number_format($ret['other']['grandTotalTime']/60, 2) . " hr\n";
$body .= "===============\n";

$body .= "\n";

$ret = $fin->getWOClosedInvoiceOpen();
$body .= $titles[3];
$body .= "\n";
$body .= 'Count : ' . count($ret['invoices']) . "\n";
$body .= 'Trigger Total : ' . number_format($ret['other']['total'], 2, '.', ',') . "\n";
$body .= "===============\n";

$body .= "\n";

$ret = $fin->getAwaitingDelivery();
$body .= $titles[4];
$body .= "\n";
$body .= 'Count : ' . count($ret['invoices']) . "\n";
$body .= 'Trigger Total : ' . number_format($ret['other']['total'], 2, '.', ',') . "\n";
$body .= "===============\n";

$body .= "\n";

$ret = $fin->getAwaitingPayment();
$body .= $titles[5];
$body .= "\n";
$body .= 'Count : ' . count($ret['invoices']) . "\n";
$body .= 'Balance : ' . number_format($ret['other']['balance'], 2, '.', ',') . "\n";

$body .= "===============\n";

$success = false;

$smsNumber = $smsNumbers[FLOWROUTE_SMS_FRONT_DOOR];
$recips = array(CELL_PHONE_RON); 

if ($smsNumber['provider'] == SMSPROVIDERID_FLOWROUTE){
	if (class_exists($smsNumber['class'])){
		$className = $smsNumber['class'];
		foreach ($recips as $rkey => $recip){
			$sms = new $className(FLOWROUTE_SMS_FRONT_DOOR, $recip, '', $body, 'out', array());
			$success = $sms->processOutbound($body);
			if ($success) {
			    $logger->info2('1589576602', "State of union SMS successfully sent to $recip.");
			} else {
			    $logger->info2('1589576609', "FAILED to send state of union SMS to $recip.");
			}
		}
	}
}

if ($logging) {
    $logger->info2('1589576574', "crons/stateofunion.php succeeded. Elapsed time: " . (time() - $start_time) . " seconds.");
}
?>