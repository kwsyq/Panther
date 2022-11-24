<?php 
/* contact.php 

   EXECUTIVE SUMMARY: A basically static "contact us" page.
   Reworked 2019-02-01 JM to be customizable for different customers rather than just SSS
*/

require_once './inc/config.php';

include_once BASEDIR . '/includes/header.php';

$crumbs = new Crumbs(null, $user);

?>
<script>
    document.title = 'Contact <?php echo str_replace("'", "\'", CUSTOMER_NAME); ?>';
</script>

	<div id="container" class="clearfix">
		<div class="main-content">
			<h1>Contact Us</h1>
			<p><?php {echo CUSTOMER_PUBLIC_DESCRIPTION_HTML;} ?></p>
			<div class="contact-info">
				<h2 style="margin-top:0px;"><?php {
                    echo CUSTOMER_FULL_NAME;
                    if (CUSTOMER_USES_REGISTERED_TM) {
                        echo ' &reg;';
                    } else if (CUSTOMER_USES_TM) {
                        echo ' &trade;'; 
                    }
				} ?>"></h2>
				<p><?php {echo CUSTOMER_ADDRESS_AND_PHONE_HTML;} ?>&nbsp;&nbsp;|&nbsp;&nbsp;<a id="mailtoCustomer" href="mailto:<?php {echo CUSTOMER_INBOX;}?>"><?php {echo CUSTOMER_INBOX;}?></a></p>
				<img src="/cust/<?php echo $customer->getShortName(); ?>/img/<?php {echo CUSTOMER_CONTACT_IMAGE;} ?>" width="432px"/> </div>
			<div class="modulebox signin" >
				<h2 class="heading">Our Location</h2>
			<iframe width="425" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="<?php {echo CUSTOMER_GOOGLE_MAP_SRC;} ?>"></iframe>
					
			</div>
		</div>
	</div>
	
	
<?php 
include_once BASEDIR . '/includes/footer.php';
?>