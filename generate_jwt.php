<?php
/*
  generate_jwt.php

    EXECUTIVE SUMMARY: Generates a token JWT based on header, payload and signature. 
        Checks if the user is logged in the system. When the link "Learn More" is clicked on the tooltip show, 
        we redirect the user to the User Manual, a CMS system, without the need to log in. 
        JWT: has a secret key and an expiration date of 30 minutes.

    This file is required in: footer and footer_fb.
*/

require_once BASEDIR.'/inc/config.php';
$email = "";
$jwt = "";

if (isset($user) && $user) { 
    $email = $user->getUsername(); // user email address to login on wordpress.
}
// PHP has no base64UrlEncode function, replacing + with -, / with _ and = with ''.
// This way we can pass the string within URLs without any URL encoding.
function base64UrlEncode($text)
{
    return str_replace(
        ['+', '/', '='],
        ['-', '_', ''],
        base64_encode($text)
    );
}

$secret = "coZOtVb78y";

// Create the token header
$header = json_encode([
    'typ' => 'JWT',
    'alg' => 'HS256'
]);

$payload = json_encode([
    'username' =>  $email,
    'exp' => ((new DateTime())->modify('+30 minutes')->getTimestamp()), // time to expire
]);

// Encode Header
$base64UrlHeader = base64UrlEncode($header);

// Encode Payload
$base64UrlPayload = base64UrlEncode($payload);

// Create Signature Hash
$signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

// Encode Signature to Base64Url String
$base64UrlSignature = base64UrlEncode($signature);

// Create JWT
$jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

?>