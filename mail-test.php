<?php 
   /* mail-test.php
      Simple self-submitting form STRICTLY FOR TESTING PURPOSES: 
      enter an email address and a test message should be sent to that email address.
      Lets us know if SSSMail is working on this server.
      
      NOTE that SSSMail uses ZendMail and is distinct from the built-in mail in PHP.
    */

include './inc/config.php';

$sending_mail = false;
$time = time();
if ($act == 'send-mail') {
    $sending_mail = true;
    $body = "Test mail\n";
    $mail = new SSSMail();
    $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
    $mail->addTo($_REQUEST["email-address"], $_REQUEST["email-name"]);
    $subject = 'Test mail ' . $time;
    $mail->setSubject($subject);
    $mail->setBodyText($body);
    $mail_result = $mail->send();
}
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Mail test';\n</script>\n";

if ($sending_mail) {
    if ($mail_result) {
        echo "<p><b>$subject believed sent successfully</b></p>\n"; 
    } else {
        echo "<p><b>failure sending $subject</b></p>\n"; 
    }
    echo '<a id="mailtestReload" href="mail-test.php">Reload to send another email.</a>'."\n";
} else {
?>    
    <h1>Mail test</h1>
    <p style="text-align:left">Simple self-submitting form for testing purposes: enter an email address and
    a test message should be sent to that email address.
    Lets us know if sending SSSMail (based on ZendMail) is working on this server.
    <b>No validation of input, this is an internal testing tool only</b>
    </p>
    
    <form id="mail-test-form" method="post">
    <input type="hidden" name="act" value="send-mail" />
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email address</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><input type="text" id="email-name" name="email-name" value="Name goes here"></td>
                <td><input type="text" id="email-address" name="email-address" value="<?php echo EMAIL_DEV; ?>"></td>
                <td><input type="submit" id="sendEmail" value="Send email"></td>
            </tr>
        </tbody>
    </table>
    </form>
<?php
}
include BASEDIR . '/includes/footer.php';
?>
