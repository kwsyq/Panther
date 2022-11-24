<?php
    /* reset.php
    
        EXECUTIVE SUMMARY: Password reset page, relying on a code sent by email.
        
        Optional input $_REQUEST['act']. Possible values: 
        * 'send'
        * 'reply'
            * Takes $_REQUEST['email'] & $_REQUEST['code']
        * 'change'
            * Takes $_REQUEST['email'] & $_REQUEST['code']
            * Takes $_REQUEST['newpass'] & $_REQUEST['newpassconfirm']
        * 'complete' (I - JM - think this never arises as an input)
        
        REGISTRATION PROCESS is a series of calls to this page. 
        (1) It begins with the case where $_REQUEST['act'] is absent; 
        on self-submission, submits with $_REQUEST['act']='send'. (2) That iteration
        sends email, displays a message to check your mailbox.
        
        (3) The URL in the email calls this page again, with $_REQUEST['act']='reply', 
        appropriate $_REQUEST['email'] & $_REQUEST['code']. If that succeeds, gives 
        a form to create & confirm a new password. Self-submits $_REQUEST['act']='change',
        so it hits this page a 4th time.
   */


include './inc/config.php';

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Password reset for ".str_replace("'", "\'", CUSTOMER_NAME)."';\n</script>\n";

$crumbs = new Crumbs(null, $user);

?>

<div id="container" class="clearfix">
    <div class="main-content">
        <h1>Password Reset</h1>
        <?php
        if ($act == 'change') {
            // STEP 4
            $newpass = isset($_REQUEST['newpass']) ? $_REQUEST['newpass'] : '';
            $newpassconfirm = isset($_REQUEST['newpassconfirm']) ? $_REQUEST['newpassconfirm'] : '';
            $resetCode = isset($_REQUEST['code']) ? $_REQUEST['code'] : '';
            $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
            
            $errors = array();
            
            $newpass = trim($newpass);
            $newpassconfirm = trim($newpassconfirm);
            if ($newpass != $newpassconfirm){
                $errors[] = 'Passwords don\'t match';
            }
            
            checkPassword($newpass, $errors);  // >>>00006 probably shouldn't bother calling this if passwords don't match
            
            if (count($errors)) {
                echo '<h3>Problem!</h3>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . $error . '</li>';
                }
                echo '</ul>';
                $act = 'reply'; // BACK TO STEP 3 
            } else {
                $db = DB::getInstance();
					
                $secure = new SecureHash();
                $salt = '';
                $encrypted = $secure->create_hash($newpass, $salt); // This modifies $salt
                
                $query = " update " . DB__NEW_DATABASE . ".person set ";
                $query .= " pass = '" . $db->real_escape_string($encrypted) . "' ";
                $query .= " ,salt = '" . $db->real_escape_string($salt) . "' ";
                $query .= " ,resetCode = '" . $db->real_escape_string($resetCode . '_used') . "' ";
                $query .= " where username = '" . $db->real_escape_string($email) . "' ";
                $query .= " and resetCode = '" . $db->real_escape_string($resetCode) . "' ";

                $db->query($query); // >>>00002 ignores failure on DB query! Does this throughout file, 
                                    // haven't noted each instance.
					
                echo 'Password updated.  Go to <a href="/login">login page</a> now.<p><p>';
                
                $act = 'complete';                
            }	
        } // END if ($act == 'change') {
        
        if ($act == 'complete') {
            // DO NOTHING, WE ARE DONE
        } else if ($act == 'reply') {
            // STEP 3 			
            $db = DB::getInstance();
            
            $resetCode = isset($_REQUEST['code']) ? $_REQUEST['code'] : '';
            $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
            
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".person  ";
            $query .= " where username = '" . $db->real_escape_string($email) . "' ";
            $query .= " and resetCode = '" . $db->real_escape_string($resetCode) . "' ";
            $query .= " and now() - resetTime < 600 ";

            $row = false;
            
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                }
            }
            
            if ($row) {
            ?>
                <div class="contact-info">
                    <form method="post" name="login" id="loginForm" action="reset.php">
                        <input type="hidden" name="act" value="change">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($resetCode); ?>">
                        <br>
                        <?php /* >>>00031 JM 2020-02-21 as in login.php, twice here, very weird HTML to put INPUT inside LABEL instead of using
                                 ID on INPUT & a matching FOR on the LABEL. It looks like Martin was doing this mainly to "defeat" some stylesheet.
                                 Probably just leave this HTML mess since UI looks OK and there is nothing deep going on here.
                                 */ ?>
                        <label class="username"> <span>New Pass</span> 
                            <input name="newpass" id="newpass" value="" type="password"  maxlength="128">
                        </label>
                        <br>
                        <label class="username"> <span>Confirm</span>
                            <input name="newpassconfirm" id="newpassconfirm" value="" type="password"  maxlength="128">
                        </label>
                        <input class="submit button-signin" type="submit" id="go" value="Go!">
                        <br />
                    </form>
					<p>  <?php /* >>>00031 dubious use of HTML P element in the following (paragraph, never closed); <BR/> would be better */ ?>
					<p>
					<p>
					<p>
					<p>
					<p>
					
            <?php
			} else {
			    // [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]
			    //echo 'Thanks.';  // problem here .. but just saying thanks :)
			    // [END COMMENTED OUT BY MARTIN BEFORE 2019]
			}
		} else if ($act == 'send') {
		    // STEP 2
		    $db = DB::getInstance();
		    
		    $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
		    $email = trim($email);  // [Martin comment:] fuck validation for now
				
            $query = " select * ";
            $query .= " from " . DB__NEW_DATABASE . ".person  "; 
            $query .= " where customerId = " . intval($customer->getCustomerId());
            $query .= " and username = '" . $db->real_escape_string($email) . "' ";

            $row = false;
				
            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();				
                }
            }
            
            if ($row) {
                $code = generateCode(15);
			
                $query = " update " . DB__NEW_DATABASE . ".person  ";
                $query .= " set resetCode = '" . $db->real_escape_string($code) . "', resetTime = now() ";
                $query .= " where customerId = " . intval($customer->getCustomerId());
                $query .= " and username = '" . $db->real_escape_string($email) . "' ";					

                $db->query($query);
                
                $subject = 'SSS Password Reset';

                $body = "Heres your reset link \n";
                $body .= " http://" . HTTP_HOST . "/reset.php?act=reply&email=" . rawurlencode($row['username']) . "&code=" . $code . "\n";
                
                $mail = new SSSMail();
                
                /*
                OLD CODE removed 2019-02-05 JM
                $mail->setFrom('inbox@ssseng.com', 'Sound Structural Solutions');
                */
                // BEGIN NEW CODE 2019-02-05 JM
                $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
                // END NEW CODE 2019-02-05 JM

                $mail->addTo($row['username'], $row['firstName'] . ' ' . $row['lastName']);                
                $mail->setSubject($subject);                
                $mail->setBodyText($body);                
                $result = $mail->send();
                
                if ($result) {
                    // success
                } else {
                    // fail
                }
            }
            echo "Thanks, if that email is in our system we will send a reset link.  Check your inbox!";
        } else {
            // STEP 1
            ?>
            <p>Enter your email address</p>
            <div class="contact-info">
                <form method="post" name="login" id="login" action="">
                    <input type="hidden" name="act" value="send">
                    <label class="username"> <span>Email</span> <?php /* >>>00031 Weird use of LABEL here, lacks FOR attribute, instead
                                                                                puts the labeled input inside the label*/ ?>
                        <input name="email" id="email" value="" type="text"  maxlength="128">
                    </label>
                    <input class="submit button-signin" id="goEmail" type="submit" value="Go!">
                    <br />
                </form>
                <p>  <?php /* >>>00031 dubious use of HTML P element in the following (paragraph, never closed); <BR/> would be better */ ?>
                <p>
                <p>
                <p>
                <p>
                <p>
        <?php
        }
        ?>
        </div>  <?php /* >>>00031: very poor handling if DIVs: we always close 3 at the end, but we may
                        only have opened 2. They should be closed on the same conditionals that they are opened. */ ?>
    </div>
</div>	
	
<?php 
include BASEDIR . '/includes/footer.php';
?>