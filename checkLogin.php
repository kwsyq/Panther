<?php
session_start();
//include './inc/config.php';

//print_r($_REQUEST);
//die();
$token=$_REQUEST['id_token'];
$code=$_REQUEST['code'];

$token_arr = explode('.', $token);
$headers_enc = $token_arr[0];
$claims_enc = $token_arr[1];
$sig_enc = $token_arr[2];

// 2 base 64 url decoding
$headers_arr = json_decode(base64_url_decode($headers_enc), TRUE);
$claims_arr = json_decode(base64_url_decode($claims_enc), TRUE);
$sig = base64_url_decode($sig_enc);

// 3 get key list
$keylist = file_get_contents('https://login.microsoftonline.com/consumers/discovery/v2.0/keys');
$keylist_arr = json_decode($keylist, TRUE);
foreach($keylist_arr['keys'] as $key => $value) {

    // 4 select one key
    if($value['kid'] == $headers_arr['kid']) {
//echo $value['kid'].'-'.$headers_arr['kid'];
//die();
        // 5 get public key from key info
        $cert_txt = '-----BEGIN CERTIFICATE-----' . "\n" . chunk_split($value['x5c'][0], 64) . '-----END CERTIFICATE-----';
        $cert_obj = openssl_x509_read($cert_txt);
        $pkey_obj = openssl_pkey_get_public($cert_obj);
        $pkey_arr = openssl_pkey_get_details($pkey_obj);
        $pkey_txt = $pkey_arr['key'];
//echo $sig;
//die();
        // 6 validate signature
        $token_valid = openssl_verify($headers_enc . '.' . $claims_enc, $sig, $pkey_txt, OPENSSL_ALGO_SHA256);
    }
}

$result_txt = 'Token is Invalid (or not authenticated) ...';
if($token_valid == 1)
    $result_txt = 'Token is Valid !';


if($token_valid==1){

    $username=$claims_arr['preferred_username'];
    if (strlen($username)) {
        $_SESSION['username'] = $username;
        header("Location: panther.php");
        exit();
    }
} else {
    header("Location: login.php");

}




function base64_url_decode($arg) {
    $res = $arg;
    $res = str_replace('-', '+', $res);
    $res = str_replace('_', '/', $res);
    switch (strlen($res) % 4) {
        case 0:
            break;
        case 2:
            $res .= "==";
            break;
        case 3:
            $res .= "=";
            break;
        default:
            break;
    }
    $res = base64_decode($res);
    return $res;
}


?>

