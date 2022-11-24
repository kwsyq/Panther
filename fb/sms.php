<?php
/*  fb/sms.php

    EXECTUTIVE SUMMARY: Send a SMS. At least as of 2019-05, this is always about replying to an SMS, so inboundSmsId is always germane.

    INITIAL input: 
        * $_REQUEST['inboundSmsId']
        * $_REQUEST['personId']
        * $_REQUEST['companyId']
        * $_REQUEST['to']. Should be phone number.
        * $_REQUEST['from']. Should be phone number.

        Optional $_REQUEST['act']. Only possible value: 'sendsms', which uses:
        * $_REQUEST['inboundSmsId]
        * $_REQUEST['to']
        * $_REQUEST['from']
        * $_REQUEST['body']
        (so in contrast to initial inputs, ignores 'personId', 'companyId', adds 'body').
        
    >>>00026 JM: Looks to me like nothing here limits this to the permitted length of an SMS,
    and if you work on that remember to consider $contextPrefix below.
*/

include '../inc/config.php';
include '../inc/access.php';

if ($act == 'sendsms') {
    $success = false;
    $to = isset($_REQUEST['to']) ? trim($_REQUEST['to']) : '';
    $from = isset($_REQUEST['from']) ? trim($_REQUEST['from']) : '';
    $body = isset($_REQUEST['body']) ? trim($_REQUEST['body']) : '';
    $inboundSmsId = isset($_REQUEST['inboundSmsId']) ? intval($_REQUEST['inboundSmsId']) : 0;

    /* OLD CODE REPLACED 2019-05-02 JM
    $body = "From Sound Structural Solutions\n\n" . $body;
    */
    // BEGIN REPLACEMENT 2019-05-02 JM
    $contextPrefix = 'From ' . CUSTOMER_NAME;
    $body = "$contextPrefix\n\n" . $body;
    // END REPLACEMENT 2019-05-02 JM

    // BEGIN MARTIN COMMENT
    // since this could potentially be more than one number
    // validate against allowed
    //$from = FLOWROUTE_SMS_DID;
    // end MARTIN COMMENT

    if (key_exists($from, $smsNumbers)) { // array $smsNumbers is in inc/config.php
        $smsNumber = $smsNumbers[$from];
        if ($smsNumber['provider'] == SMSPROVIDERID_FLOWROUTE) {
            if (class_exists($smsNumber['class'])) {
                $className = $smsNumber['class'];
                $sms = new $className($from, $to, '', $body, 'out', array());

                // BEGIN MARTIN COMMENT
                // this method in the class was originally just for auto returns from inbound sms
                // but just cobbling using it here
                // END MARTIN COMMENT
                $success = $sms->processOutbound($body);
            }
        }
    }

    if ($success) {
        // Display "Send was ok" for 2 seconds, and close fancybox.
        // Also, put outbound SMS in database
        ?>
        Send was ok
        <script type="text/javascript">
        setTimeout(function(){ parent.$.fancybox.close(); }, 2000);
        </script>
        <?php

        $db = DB::getInstance();        
        
        /* OLD CODE REPLACED 2019-05-02 JM
        $body = str_replace("From Sound Structural Solutions","", $body);
        */
        // BEGIN REPLACEMENT 2019-05-02 JM
        // Strip the prefix back out.
        $body = str_replace($contextPrefix, "", $body);
        // END REPLACEMENT 2019-05-02 JM
        $body = trim($body);
        $body = substr($body, 0, 1024);            
        
        $query = " insert into " . DB__NEW_DATABASE . ".outboundSms (inboundSmsId, didTo, didFrom, body) values (";
        $query .= " " . intval($inboundSmsId) . " ";
        $query .= " ," . intval($to) . " ";
        $query .= " ," . intval($from) . " ";
        $query .= " ,'" . $db->real_escape_string($body) . "') ";
        
        $db->query($query);           
    } else {
        echo "send of SMS failed!";
        // and leave fancybox open.
    }
} else {
    /*
    // BEGIN MARTIN COMMENT
    create table outboundSms(
        outboundSmsId   int unsigned not null primary key auto_increment,
        inboundSmsId    int unsigned not null default 0,
        didTo           bigint unsigned,
        didFrom		    bigint unsigned,
        body            text,
        inserted        timestamp not null default now());
    // END MARTIN COMMENT    
    */
    
    $inboundSmsId = isset($_REQUEST['inboundSmsId']) ? intval($_REQUEST['inboundSmsId']) : 0;
    $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
    $companyId = isset($_REQUEST['companyId']) ? intval($_REQUEST['companyId']) : 0;
    $to = isset($_REQUEST['to']) ? intval($_REQUEST['to']) : '';
    $from = isset($_REQUEST['from']) ? intval($_REQUEST['from']) : '';
    
    include '../includes/header_fb.php';

    // Self-submitting form with hidden:
    //   * act='sendsms' 
    //   * to
    //   * from. 
    // Visibly displays non-editable "to" number, a textarea for the message body, and a submit button labeled "send".
    echo '<form name="smssend" id="smsSend" method="post" action="">';
        echo '<input type="hidden" name="act" value="sendsms">';
        echo '<input type="hidden" name="to" value="' .  htmlspecialchars($to) . '">';
        echo '<input type="hidden" name="from" value="' .  htmlspecialchars($from) . '">';
        echo '<input type="hidden" name="inboundSmsId" value="' .  htmlspecialchars($inboundSmsId) . '">';
        
        echo '<table border="0" cellpadding="5" cellspacing="2" width="100%">';
            echo '<tr>';
                echo '<td>To:&nbsp;</td>';
                echo '<td width="100%">' . htmlspecialchars($to) . '</td>';
            echo '</tr>';
            echo '<tr>';
                echo '<td>Body:&nbsp;</td>';
                echo '<td width="100%"><textarea name="body" rows="35" cols="80"></textarea></td>';
            echo '</tr>';
            echo '<tr>';
                echo '<td colspan="2"><input type="submit" id="submitSms" value="send"></td>';
            echo '</tr>';
        echo '</table>';
    echo '</form>';

    include '../includes/footer_fb.php';
}
?>
