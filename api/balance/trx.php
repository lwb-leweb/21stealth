<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config  = require __DIR__ . '/../../config.php';
$address = $_GET['address'] ?? '';

if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid TRX address']);
    exit;
}

$header_str = "Content-Type: application/json\r\n";
if (!empty($config['trongrid_api_key'])) {
    $header_str .= 'TRON-PRO-API-KEY: ' . $config['trongrid_api_key'] . "\r\n";
}

$data = null;
for ($attempt = 0; $attempt < 4; $attempt++) {
    if ($attempt > 0) sleep(2 * $attempt);

    $ctx = stream_context_create(['http' => [
        'timeout'       => 10,
        'header'        => $header_str,
        'ignore_errors' => true,
    ]]);

    $res = @file_get_contents('https://api.trongrid.io/v1/accounts/' . urlencode($address), false, $ctx);

    if ($res === false) continue;

    // Check HTTP status from response headers
    $status = 200;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $h, $m)) {
            $status = (int)$m[1];
        }
    }

    if ($status === 429) continue;
    if ($status !== 200) break;

    $data = json_decode($res, true);
    break;
}

if ($data === null) {
    http_response_code(502);
    echo json_encode(['error' => 'TRX API rate limited – please try again']);
    exit;
}

$account = $data['data'][0] ?? null;
$trx     = ($account['balance'] ?? 0) / 1e6;
$tokens  = [['key' => 'trx', 'label' => 'TRX', 'balance' => $trx]];

$usdt_contract = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$usdc_contract = 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8';

foreach ($account['trc20'] ?? [] as $entry) {
    if (isset($entry[$usdt_contract])) {
        $usdt = intval($entry[$usdt_contract]) / 1e6;
        if ($usdt > 0) $tokens[] = ['key' => 'usdt', 'label' => 'USDT (TRC-20)', 'balance' => $usdt];
    }
    if (isset($entry[$usdc_contract])) {
        $usdc = intval($entry[$usdc_contract]) / 1e6;
        if ($usdc > 0) $tokens[] = ['key' => 'usdc', 'label' => 'USDC (TRC-20)', 'balance' => $usdc];
    }
}

echo json_encode(['tokens' => $tokens]);
