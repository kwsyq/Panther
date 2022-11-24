<?php
/*  services.php

    EXECUTIVE SUMMARY: Static page. SSS services list; hierarchical, but not linked out. 
    There is a RewriteRule in the .htaccess to allow this to be invoked as "services/foo" rather than "services.php".
*/

include './inc/config.php';

$crumbs = new Crumbs(null, $user);

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='".str_replace("'", "\'", CUSTOMER_NAME)." services';\n</script>\n";
?>

    <div id="container" class="clearfix">
        <div class="main-content">
            <h1>Our Services</h1>
            <ul>
                <li>Residential Remodel </li>
                <li>Residential New Construction </li>
                <li>Apartment </li>
                <li>Multifamily
                    <ul>
                        <li>townhouse </li>
                        <li>rowhouse </li>
                    </ul>
                </li>
                <li>Light Commercial
                    <ul>
                        <li>Tenant Improvement </li>
                        <li>New Construction </li>
                    </ul>
                </li>
                <li>Soil Retention
                    <ul>
                        <li>soldier pile </li>
                        <li>Retaining Wall </li>
                        <li>(not Rockery) </li>
                    </ul>
                </li>
                <li>Stormwater Detention
                    <ul>
                        <li>Vault </li>
                        <li>Minivault </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
	
	
<?php 
include BASEDIR . '/includes/footer.php';
?>