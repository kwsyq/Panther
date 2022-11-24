<?php 

	use Ahc\Jwt\JWT;
	require './vendor/autoload.php';

	function base64url_decode($data) {
	  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	}

	$passdecoded=base64url_decode("R9MyWaEoyiMYViVWo8Fk4TUGWiSoaW6U1nOqXri8_XU");
echo $passdecoded;
	$jwt = new JWT($passdecoded, 'HS256', 3600, 10);

	$token = $jwt->encode([
		'uid' => 125,
	]);

	echo $token."<br>";

	$payload = $jwt->decode("eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyIjo1ODcsImV4cCI6MTY1NzMwNzc3Mn0.A_AB-roWdCSaDJcf8BL-vzfwkwqiwliWZ1dZLgwj4Go");

	print_r($payload);


print_r($_SERVER);

?>