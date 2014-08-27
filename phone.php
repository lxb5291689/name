<?php
$uri = "127.0.0.1/data.php";
// 参数数组
$data = json_encode(array (
        'name' => 'tanteng',
    'password' => 'password'
));
$ch = curl_init ();
curl_setopt ( $ch, CURLOPT_URL, $uri );
curl_setopt ( $ch, CURLOPT_POST, 1 );
curl_setopt ( $ch, CURLOPT_HEADER, 0 );
curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                       'Content-Type: application/json',                                                             'Content-Length: ' . strlen($data))                                                   
);       
$return = curl_exec ( $ch );
curl_close ( $ch );
print_r($return);
?>