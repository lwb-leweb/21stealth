<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config  = require __DIR__ . '/../../config.php';
$address = $_GET['address'] ?? '';

if (!preg_match('/^D[1-9A-HJ-NP-Za-km-z]{25,34}$/', $address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid DOGE address']);
    exit;
}

function fetch_url(string $url, int $timeout = 10): array {
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (!empty($http_response_header)) {
        preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
        $status = (int)($m[1] ?? 0);
    }
    return [$body, $status];
}

$satoshi = null;

// Primary: Blockchair
$url = 'https://api.blockchair.com/dogecoin/dashboards/address/' . urlencode($address);
if (!empty($config['blockchair_api_key'])) {
    $url .= '?key=' . $config['blockchair_api_key'];
}
[$res, $status] = fetch_url($url);
if ($res !== false && $status === 200) {
    $data    = json_decode($res, true);
    $satoshi = $data['data'][$address]['address']['balance'] ?? null;
}

// Fallback: BlockCypher
if ($satoshi === null) {
    [$res, $status] = fetch_url('https://api.blockcypher.com/v1/doge/main/addrs/' . urlencode($address) . '/balance');
    if ($res !== false && $status === 200) {
        $data    = json_decode($res, true);
        $satoshi = $data['balance'] ?? null;
    }
}

if ($satoshi === null) {
    http_response_code(502);
    echo json_encode(['error' => 'DOGE API failed']);
    exit;
}

$doge = $satoshi / 1e8;
echo json_encode(['tokens' => [['key' => 'doge', 'label' => 'DOGE', 'balance' => $doge]]]);
