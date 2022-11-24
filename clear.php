<?php 

/*  Clear.php

    EXECUTIVE SUMMARY: Clear all cookies and session variables.
*/


if (isset($_SERVER['HTTP_COOKIE'])) {
	$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
	foreach($cookies as $cookie) {
		$parts = explode('=', $cookie);
		$name = trim($parts[0]);
		setcookie($name, '', time()-10000000);
		setcookie($name, '', time()-10000000, '/');
	}
}

foreach ($_SESSION as $k => $v){
	unset($_SESSION[$k]);
}


header("Location: /login");


?>