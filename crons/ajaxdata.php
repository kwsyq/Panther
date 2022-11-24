#!/usr/bin/php -q
<?php
/*
    crons/ajaxdata.php
    Instantiates a Financial object, then runs a bunch of methods and summarizes the results 
    in DB table ajaxdata. Runs only from command-line interface, otherwise dies. 
    E.g how many workorders are open, how many have tasks still open, how many jobs need to be invoiced: 30,000-foot view.
*/


/*
create table ajaxData(
    ajaxDataId     int unsigned not null primary key auto_increment,
    dataName       varchar(64) not null unique,
    dataArray      text,
    inserted       timestamp not null default now()
)
*/

include __DIR__ . '/../inc/config.php';

// Must be run from command line (not web)
if (!is_command_line_interface()) {
    $logger->error2('1589559487', "crons/ajaxdata.php must be run from the command line, was apparently accessed some other way.");
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
        $logger->info2('1589559520', "start crons/ajaxdata.php: $reconstructed_cmd");        
        break;
    }
}
unset($value);

$db = DB::getInstance();

$titles = array();
$titles[0] = '(wo closed, tasks open)';
$titles[1] = '(wo open, tasks closed)';
$titles[2] = '(wo no invoice)';
$titles[3] = '(wo closed, open invoice)';
$titles[4] = '(mailroom -- awaiting delivery)';
$titles[5] = '(aging sumary - awaiting payment)';
$titles[6] = '(cred recs)';   // >>>00001 Looks like this is not done here - JM 2019-04
$titles[7] = '(do payments)'; // >>>00001 Looks like this is not done here - JM 2019-04

$fin = new Financial();

$ret = $fin->getWOClosedTasksOpen();
$dat = array();
$dat['title'] =  $titles[0];
$dat['data'] = array('Count' => count($ret['workOrders']));

$query = "DELETE from  " . DB__NEW_DATABASE . ".ajaxData WHERE dataName = 'tab0';";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562584', "Hard DB error", $db);
    die();
}

$query = "INSERT INTO " . DB__NEW_DATABASE . ".ajaxData (dataName, dataArray) VALUES (";
$query .= " 'tab0', ";
$query .= " '" . base64_encode(serialize($dat)) . "');";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562621', "Hard DB error", $db);
    die();
}
$ret = $fin->getWOOpenTasksClosed();

$dat = array();
$dat['title'] =  $titles[1];
$dat['data'] = array('Count' => count($ret['workOrders']));

$query = "DELETE FROM " . DB__NEW_DATABASE . ".ajaxData WHERE dataName = 'tab1';";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562652', "Hard DB error", $db);
    die();
}

$query = "INSERT INTO " . DB__NEW_DATABASE . ".ajaxData (dataName, dataArray) VALUES (";
$query .= " 'tab1', ";
$query .= " '" . base64_encode(serialize($dat)) . "') ";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562666', "Hard DB error", $db);
    die();
}

$ret = $fin->getWONoInvoice();
$dat = array();
$dat['title'] =  $titles[2];
$dat['data'] = array('Count' => count($ret['workOrders']), 
        'Hours' => number_format($ret['other']['grandTotalTime']/60, 2)
);
$query = "DELETE FROM " . DB__NEW_DATABASE . ".ajaxData WHERE dataName = 'tab2' ";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562679', "Hard DB error", $db);
    die();
}

$query = "INSERT INTO " . DB__NEW_DATABASE . ".ajaxData (dataName, dataArray) values (";
$query .= " 'tab2', ";
$query .= " '" . base64_encode(serialize($dat)) . "');";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562690', "Hard DB error", $db);
    die();
}

$ret = $fin->getWOClosedInvoiceOpen();
$dat = array();
$dat['title'] =  $titles[3];
$dat['data'] = array('Count' => count($ret['invoices']),
    'Trigger Total' => number_format($ret['other']['total'], 2, '.', ',')
);

$query = "DELETE FROM " . DB__NEW_DATABASE . ".ajaxData WHERE dataName = 'tab3';";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562704', "Hard DB error", $db);
    die();
}

$query = "INSERT INTO " . DB__NEW_DATABASE . ".ajaxData (dataName, dataArray) VALUES (";
$query .= " 'tab3', ";
$query .= " '" . base64_encode(serialize($dat)) . "');";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562744', "Hard DB error", $db);
    die();
}

$ret = $fin->getAwaitingDelivery();
$dat = array();
$dat['title'] =  $titles[4];
$dat['data'] = array('Count' => count($ret['invoices']),
    'Trigger Total' => number_format($ret['other']['total'], 2, '.', ',')
);

$query = "DELETE FROM " . DB__NEW_DATABASE . ".ajaxData where dataName = 'tab4';";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562795', "Hard DB error", $db);
    die();
}

$query = "INSERT INTO " . DB__NEW_DATABASE . ".ajaxData (dataName, dataArray) VALUES (";
$query .= " 'tab4', ";
$query .= " '" . base64_encode(serialize($dat)) . "') ";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562801', "Hard DB error", $db);
    die();
}

$ret = $fin->getAwaitingPayment();
$dat = array();
$dat['title'] =  $titles[5];
$dat['data'] = array('Count' => count($ret['invoices']),
    'Balance' => number_format($ret['other']['balance'], 2, '.', ',')
);

$query = "DELETE FROM " . DB__NEW_DATABASE . ".ajaxData where dataName = 'tab5';";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562825', "Hard DB error", $db);
    die();
}

$query = "INSERT INTO " . DB__NEW_DATABASE . ".ajaxData (dataName, dataArray) VALUES (";
$query .= " 'tab5', ";
$query .= " '" . base64_encode(serialize($dat)) . "');";
$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1589562865', "Hard DB error", $db);
    die();
}

if ($logging) {
    $logger->info2('1589562470', "crons/ajaxdata.php succeeded. Elapsed time: " . (time() - $start_time) . " seconds.");
}
?>