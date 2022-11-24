<?php 

/*  about.php

    EXECUTIVE SUMMARY: Static "about" page for customer, as of 2019-03 always SSS. 
    There is a RewriteRule in the .htaccess to allow this to be invoked as "about/foo" rather than "about.php".

*/

require_once './inc/config.php';
require_once './inc/access.php';

include_once BASEDIR . '/includes/header.php';

$crumbs = new Crumbs(null, $user);

?>
<script>
    document.title = 'About <?php echo str_replace("'", "\'", CUSTOMER_NAME); ?>';
</script>

	<div id="container" class="clearfix">
		<div class="main-content">
		    <?php
		    /* OLD CONTENT here (in straight HTML) was replaced 2019-02-06
			<h1>About Us</h1>
			<ul>
				<li>started in 2005</li>
				<li>attended college together at Montana State</li>
				<li>Construction Exposure</li>
				<li>Combined 25 years Experience</li>
			</ul>
			<br />
			<p>We started Sound Structural Solutions in 2005 with the intention of providing our clients with straight forward solutions to their construction needs. With a combined experience of 25 years in both engineering and construction we have encountered first hand, many of the challenges faced in construction.</p>
			<p>Our friendship began at Montana State University while attending college together. Working together for so many years has allowed us to stay competitive within our industry. Constant collaboration requires us to justify our analysis and design outside a frame work larger than ourselves.</p>
			<p>Customer service is one of our driving concerns. Understanding many of the challenges in construction, we appreciate the people tasked with accomplishing these goals. We draw from our clients' experience as they have valuable insights that benefit good working solutions.</p>
			<p>Providing simple and easy to understand plans and documents is important for the successful completion of a project. Our goal is to save you time and money by making the information clear and quickly accessible. We don't expect you to wade through invalid details and notes leaving you or your inspector to make interpretations. Your feedback is welcome as we are constantly trying to improve our communications.</p>
		    END OLD CONTENT
		    */
		    // BEGIN added 2019-02-06 JM
			echo ABOUT_HTML; 
			// BEGIN added 2019-02-06 JM
			?>

		</div>
	</div>
	
	
<?php 
include_once BASEDIR . '/includes/footer.php';
?>