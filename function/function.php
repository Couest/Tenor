<?php

function wlog($type,$content) {
    $dir = dirname(__DIR__) . "/{$type}/log";
    $file = $dir . '/' . date('Y-m-d') . '.log';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $content = "{$content}" . PHP_EOL;
    file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
}

function close_php() {
    echo json_encode(["code" => 200]);
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        header('Connection: close');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
}

function check_file($type) {
    $type = dirname(__DIR__)."/".$type;
    if (!is_dir($type)) mkdir($type, 0777, true);
    if (!is_dir($type."/plugin")) mkdir($type."/plugin", 0777, true);
    if (!is_dir($type."/log")) mkdir($type."/log", 0777, true);
    if (!is_dir($type."/function")) mkdir($type."/function", 0777, true);
    if (!is_dir($type."/database")) mkdir($type."/database", 0777, true);
}

function qq_plat_sign($payload,$seed) {
    while (strlen($seed) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        $seed .= $seed;
    }
    $privateKey = sodium_crypto_sign_secretkey(
        sodium_crypto_sign_seed_keypair(substr($seed, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES))
    );
    $signature = bin2hex(
        sodium_crypto_sign_detached(
            $payload['d']['event_ts'] . $payload['d']['plain_token'], 
            $privateKey
        )
    );
    echo json_encode([
        'plain_token' => $payload['d']['plain_token'],
        'signature' => $signature
    ]);
    exit;
}


function curl($url, $method, $headers, $params){
$url = str_replace(" ", "%20", $url);
    if (is_array($params)) {
        $requestString = http_build_query($params);
    } else {
        $requestString = $params ? : '';
    }
    if (empty($headers)) {
        $headers = array('Content-type: text/json'); 
    } elseif (!is_array($headers)) {
        parse_str($headers,$headers);
    }
    // setting the curl parameters.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // turning off the server and peer verification(TrustManager Concept).
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    // setting the POST FIELD to curl
    switch ($method){  
        case "GET" : curl_setopt($ch, CURLOPT_HTTPGET, 1);break;  
        case "POST": curl_setopt($ch, CURLOPT_POST, 1);
                     curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);break;  
        case "PUT" : curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");   
                     curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);break;  
        case "DELETE":  curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "DELETE");   
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);break;  
    }
    // getting response from server
    $response = curl_exec($ch);
    
    //close the connection
    curl_close($ch);
    
    //return the response
    if (stristr($response, 'HTTP 404') || $response == '') {
        return array('Error' => '请求错误');
    }
    return $response;
}

function 前缀后($str,$prefix) {
    if (strpos($str,$prefix) !== false) {
        return substr($str, strlen($prefix));
    } else {
        return $str;
    }
}
function 前缀($str,$prefix) {
    if (strpos($str,$prefix) === 0) {     
        return true;
    } else {
       
        return false;
    }
}