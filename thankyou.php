<?php 
/*  thankyou.php
    EXECUTIVE SUMMARY: Static page. "Thanks for registering. Check your email."
*/

include './inc/config.php';
$crumbs = new Crumbs(null, $user);
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='".str_replace("'", "\'", CUSTOMER_NAME)." - Thank you';\n</script>\n";
?>
<br />
<br />
<br />
<tr>
    <td>
        Thanks for registering. Check your email.
    </td>
</tr>
<br />
<br />
<br />
<?php
include BASEDIR . '/includes/footer.php';
?>