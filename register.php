<?php 
/*  register.php

    EXECUTIVE SUMMARY: page for user to register a new account.

    There is a RewriteRule in the .htaccess to allow this to be invoked as "register/foo" rather than "register.php".
    
    * If there is no $_REQUEST['act'], this generates a registration page to fill in username, password (twice), & email.
    * If $_REQUEST['act'] is present, the only possible value is 'register'; that takes inputs 
         * $_REQUEST['username']
         * $_REQUEST['password']
         * $_REQUEST['passwordconfirm']
         * $_REQUEST['email']
      These are passed to a registration function, and we redirect to 'thank_you.php' on success.
*/

include './inc/config.php';

$crumbs = new Crumbs(null, $user);
$errors = array();
if ($act == "register") {
    $register = new Register($customer);
    
    $result = $register->register($_REQUEST);
    if (!$result) {
        // Something went wrong. Get an associative array of error messages; indexes are
        //  'username', 'password', 'email', 'insert failed'; only possible value for the last is 'yes', 
        //  but the others are strings to display to the user.
        $errors = $register->getErrors();
        if (array_key_exists('insert failed', $errors)) {
			// [Martin comment:] insert failed do something ... maybe redirect back somewherre ??? try again later ?? call us ??
			// JM comment: right now, we just drop through to this same registration page.
		}
	} else {
	    header("Location: /thankyou.php");
	}
}
?>

<?php 
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Register for ".str_replace("'", "\'", CUSTOMER_NAME)."';\n</script>\n";

$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
$passwordconfirm = isset($_REQUEST['passwordconfirm']) ? $_REQUEST['passwordconfirm'] : '';
$email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
?>

<tr> <?php /* >>>00031 How can we have TR element without a table outside? This is just wrong */ ?>
	<td>
	    <form name="register" id="registerForm" action="" method="POST">
	        <input type="hidden" name="act" value="register">
	        <table border="0" cellpadding="0" cellspacing="0">
                <?php
                /////////////////////////////////
                //  USERNAME 
                /////////////////////////////////
                // Display any username errors
                if (isset($errors['username'])) {		
                    echo '<tr>';
                        echo '<td colspan="2">';
                        echo '<ul>';
                        foreach ($errors['username'] as $error){
                            echo '<li>' . $error . '</li>';
                        }
                        echo '</ul>';
                        echo '</td>';
                    echo '</tr>';
                    $username = '';
                }
                ?>
                <tr>
                    <td>Username : </td>
                    <td><input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username) ?>" size="40" maxlength="128"></td>
                </tr>
                <?php 
                /////////////////////////////////
                //  PASSWORD 
                /////////////////////////////////

                // Display any password errors
                if (isset($errors['password'])){		
                    echo '<tr>';
                        echo '<td colspan="2">';
                        echo '<ul>';
                        foreach ($errors['password'] as $error){
                            echo '<li>' . $error . '</li>';
                        }
                        echo '</ul>';
                        echo '</td>';
                    echo '</tr>';
                    $password = '';
                    $passwordconfirm = '';
                }
                ?>
                <tr>
                    <td>Password : </td>
                    <td><input type="password" name="password" id="password" value="<?php echo htmlspecialchars($password) ?>" size="40" maxlength="32"></td>
                </tr>
                <tr>
                    <td>Confirm Pass : </td>
                    <td><input type="password" name="passwordconfirm" id="passwordconfirm" value="<?php echo htmlspecialchars($passwordconfirm) ?>" size="40" maxlength="32"></td>
                </tr>
                <?php 
                /////////////////////////////////
                //  EMAIL ADDRESS 
                /////////////////////////////////
                
                // Display any email address errors
                if (isset($errors['email'])){		
                    echo '<tr>';
                        echo '<td colspan="2">';
                        echo '<ul>';
                        foreach ($errors['email'] as $error){
                            echo '<li>' . $error . '</li>';
                        }
                        echo '</ul>';
                        echo '</td>';
                    echo '</tr>';
                    $email = '';
                }
                ?>
                <tr>
                    <td>Email : </td>
                    <td><input type="text" name="email" id="email" value="<?php echo htmlspecialchars($email) ?>" size="40" maxlength="255"></td>
                </tr>
                <tr>
                    <td colspan="2"><input type="submit" id="register" value="Register" border="0"></td>
                </tr>
            </table>
        </form>		
	</td>
</tr>

<?php 
include BASEDIR . '/includes/footer.php';
?>
