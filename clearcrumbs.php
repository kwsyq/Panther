<?php 

/*  clearcrumbs.php

    EXECUTIVE SUMMARY: Hit like a PAGE, but no HTML content of its own. Clear crumbs for this user, 
    then redirect to page specified by $_REQUEST['uri'].
    
*/    

require_once './inc/config.php';


/* [BEGIN commented out by Martin before 2019]
if (isset($_SESSION['crumbs'])){
	unset($_SESSION['crumbs']);
}
if (isset($_SESSION['searches'])){
	unset($_SESSION['searches']);
}
[END commented out by Martin before 2019]
*/

$crumbs = new Crumbs(null, $user);
$crumbs->deleteCrumbs();

$uri = isset($_REQUEST['uri']) ? $_REQUEST['uri'] : '';

if (strlen($uri)){
	header("Location: " . $uri);
}

?>