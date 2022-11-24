<?php
/*  fb/email.php

    EXECUTIVE SUMMARY: Send an email.

    No "primary" input; initial input & input after self-submission are completely distinct.
    
    INITIAL INPUT: $_REQUEST['personId'], $_REQUEST['personEmailId'].
    
    INPUT AFTER SELF-SUBMISSION: $_REQUEST['act']='sendemail', $_REQUEST['to'], $_REQUEST['subject'], $_REQUEST['body'].
    
    >>>00016, >>>00002: as in so many cases, doesn't validate inputs & doesn't make much sense if the inputs aren't good.

*/

include '../inc/config.php';
include '../inc/access.php';

if ($act == 'sendemail') {    
    $to = isset($_REQUEST['to']) ? trim($_REQUEST['to']) : '';
    $subject = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';	
    $body = isset($_REQUEST['body']) ? trim($_REQUEST['body']) : '';
    
    $mail = new SSSMail();

    /*
    OLD CODE removed 2019-02-01 JM
    $mail->setFrom('inbox@ssseng.com', $user->getFirstName() . ' - Sound Structural Solutions');
    $mail->addTo('martin@allbsd.com', 'Martin');
    */
    // BEGIN NEW CODE 2019-02-01 JM    
    // Sent from customer company; shows person & company name
    $mail->setFrom(CUSTOMER_INBOX, $user->getFirstName() . ' - ' . CUSTOMER_NAME);
    
    // Always cc Dev
    $mail->addTo(EMAIL_DEV, 'Dev');
    // END NEW CODE 2019-02-01 JM	

    $mail->setSubject($subject); 
    $mail->setBodyText($body);
    
    $result = $mail->send();
    if ($result) {
        // Write "Send was ok" (not sure where in UI this appears, >>>00001 someone should work this out), 
        // wait 2 seconds and close fancybox.
        ?>
        Send was ok
        <script type="text/javascript">
        setTimeout(function(){ parent.$.fancybox.close(); }, 2000);
        </script>
        <?php
    } else {
        echo "send of Email failed!";
    }
} else {
    // The initial case: we get a personId & an emailId, which had better match.
    // We'll then set $recipient and ultimately $to based on these. 
    $personId = isset($_REQUEST['personId']) ? intval($_REQUEST['personId']) : 0;
    $personEmailId = isset($_REQUEST['personEmailId']) ? intval($_REQUEST['personEmailId']) : 0;    
    $obj = null;    
    $recipient = false;
    
    if ($personId) {
        $obj = new Person($personId, $user);
    }
    
    if ($obj) {    
        $recipient = $obj->getEmail($personEmailId);    
    }    
    
    include '../includes/header_fb.php';
    
    $to = '';
    
    if ($recipient) {
        $to = $recipient['emailAddress'];
    }
    
    echo '<form name="emailsend" id="emailSend" method="post" action="">';
        echo '<input type="hidden" name="act" value="sendemail">';
        echo '<input type="hidden" name="to" value="' .  htmlspecialchars($to) . '">';
        
        echo '<table border="0" cellpadding="5" cellspacing="2" width="100%">';
            echo '<tr>';
                echo '<td>To:&nbsp;</td>';
                echo '<td width="100%">' . htmlspecialchars($to) . '</td>';
            echo '</tr>';
            echo '<tr>';
                echo '<td>Subject:&nbsp;</td>';
                echo '<td width="100%"><input type="text" name="subject" id="subject" value="" size="50" maxlength="200"></td>';
            echo '</tr>';
            echo '<tr>';
                echo '<td>Body:&nbsp;</td>';
                echo '<td width="100%"><textarea name="body" id="body" rows="20" cols="80"></textarea></td>';
            echo '</tr>';
            echo '<tr>';
                echo '<td colspan="2"><input type="submit" id="submitEmail" value="send"></td>';
            echo '</tr>';
        echo '</table>';
    echo '</form>';
    
    include '../includes/footer_fb.php';
}

?>