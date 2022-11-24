<?php
/*  404.php 

    EXECUTIVE SUMMARY: we actually use this on both 403 and 404. Header, 
    footer and "Sorry, what you're looking for doesn't exist!"
    
*/

require_once './inc/config.php';
include_once BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='404 - Not found - ".str_replace("'", "\'", CUSTOMER_NAME)." services';\n</script>\n";

?>
	<div id="container" class="clearfix">
		<div class="main-content">
			<h1>Sorry, what you're looking for doesn't exist!</h1>
		</div>
	</div>
	
<?php 
include_once BASEDIR . '/includes/footer.php';
?>