<?php
/*  login.php

    EXECUTIVE SUMMARY: 
    Top-level page. A RewriteRule in the .htaccess allows this to be invoked as "login/foo" rather than "login.php".
    
    INPUTS: 
    * On initial call, $_REQUEST['ret'] optionally specifies page to navigate to on success, kept in hidden form input.
    * Optional $_REQUEST['act']. Only possible value: 'login', which uses $_REQUEST['username'], $_REQUEST['password'], $_REQUEST['ret'].
    * If no $_REQUEST['act'], generates a login page to fill in username, password, & request login.
    
    On successful login, set $_SESSION['username'] and redirect either to $_REQUEST['ret'] or (if no such value) to panther.php. 
    Otherwise, reshow page.
*/

include './inc/config.php';

$crumbs = new Crumbs(null, $user);
$errors = array();
$ret = isset($_REQUEST['ret']) ? $_REQUEST['ret'] : '';

if ($act == "login") {
    $username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
    $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
    
    if (strlen($username) && strlen($password)) {
        // [Martin comment:] this is technically a person from person table.  just calling a user here to avoid confusion and some recursion
        $user = User::getByLogin($username, $password, $customer);
        if ($user) {
            $_SESSION['username'] = $user->getUsername();
            if (strlen($ret)) {
                header("Location: https://" .$_SERVER['HTTP_HOST'] . $ret);
            } else {
                header("Location: /panther.php" );
            }
            /*
            [BEGIN commented out by Martin before 2019]
            if (isset($_SESSION['last_url']) && strlen($_SESSION['last_uri'])){
                header("Location: " . $_SESSION['last_uri']);
            } else {
                header("Location: /panther.php" );
            }
            [END commented out by Martin before 2019]
            */
			die();
		}
	}
} // END if ($act == "login")

// [BEGIN commented out by Martin before 2019]
//$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
//$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
//$passwordconfirm = isset($_REQUEST['passwordconfirm']) ? $_REQUEST['passwordconfirm'] : '';
//$email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
// [END commented out by Martin before 2019]
?>

<?php 
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Panther Login';\n</script>\n"; 

?>
<div id="container" class="clearfix">
    <div class="full-box clearfix dbhead login" id="dbhead">
        <h2 class="heading" style="font-weight:bold;">Please Log In</h2>
        <form method="post" id="login" name="login" action="">
            <input type="hidden" name="act" value="login">
            <input type="hidden" name="ret" value="<?php echo htmlspecialchars($ret); ?>">
            <?php /* >>>00031 Fieldset is a bit odd here; also twice here, very weird HTML to put INPUT inside LABEL instead of using
                     ID on INPUT & a matching FOR on the LABEL. It looks like Martin was doing this mainly to "defeat" some stylesheet.
                     Probably just leave this HTML mess since UI looks OK and there is nothing deep going on here.
                     */ ?>
            <fieldset class="textbox">
                <label class="username"> <span>Email / Username</span>
                    <input name="username" id="username" value="" type="text" placeholder="Username" maxlength="128">
                </label>
                <label class="password"> <span>Password</span>
                    <input name="password" value="" type="password" id="password" placeholder="Password" maxlength="32">
                </label>
                <input class="submit button-signin" type="submit" id="signIn" value="Sign In">
                <input class="submit button-signin" type="button" id="azuresignIn" value="Azure Sign In">
                <br />
                <?php /* 2020-02-18 JM: removed unbalanced close of FORM. We close it below, but this made that close be ignored 
                </form>  
                */ ?> 
                <p> <a class="forgot" id="forgotPassword" href="/reset.php">Forgot your password?</a></p>
            </fieldset>
        </form>
    </div>
</div>

<script>
    (function () {
        document.getElementById('azuresignIn').onclick = function() {
            location.href = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?response_type=id_token+code&response_mode=form_post&client_id=12ae36f4-8dc3-474a-9f33-eb4699ff1c80&scope=openid+profile+https%3a%2f%2fgraph.microsoft.com%2fmail.read&redirect_uri=https://ssseng.com/checkLogin.php&nonce=abcdef';
        };
    }());
</script>

<?php
include BASEDIR . '/includes/footer.php';
?>
