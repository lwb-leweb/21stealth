<?php
header('Content-Type: application/json');

$tests = [];

// allow_url_fopen
$tests['allow_url_fopen'] = ini_get('allow_url_fopen') ? 'on' : 'off';

// file_get_contents
$fc = @file_get_contents('https://blockstream.info/api/address/bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');
$tests['file_get_contents'] = $fc !== false ? 'ok' : 'failed';

// curl
if (function_exists('curl_init')) {
    $ch = curl_init('https://blockstream.info/api/address/bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $tests['curl_http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tests['curl_error'] = curl_error($ch);
    curl_close($ch);
} else {
    $tests['curl'] = 'not available';
}

echo json_encode($tests, JSON_PRETTY_PRINT);
