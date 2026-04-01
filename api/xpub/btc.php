<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$xpub = $_GET['xpub'] ?? '';

// Validate: base58 chars, reasonable length for an xpub key
if (!preg_match('/^[xyzYZ][a-km-zA-HJ-NP-Z1-9]{107,113}$/', $xpub)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid xPub key']);
    exit;
}

$url = 'https://blockchain.info/multiaddr?active=' . urlencode($xpub) . '&n=0';
$ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
$body = @file_get_contents($url, false, $ctx);

if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'BTC API unreachable']);
    exit;
}

$data = json_decode($body, true);
if (!isset($data['addresses'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected BTC API response']);
    exit;
}

$totalSatoshi = 0;
$addresses = [];

foreach ($data['addresses'] as $addr) {
    if (!isset($addr['address'])) continue;
    $satoshi = ($addr['final_balance'] ?? 0);
    $btc = $satoshi / 1e8;
    $totalSatoshi += $satoshi;
    if ($btc > 0) {
        $addresses[] = [
            'address' => $addr['address'],
            'tokens'  => [['key' => 'btc', 'label' => 'BTC', 'balance' => $btc]],
        ];
    }
}

echo json_encode([
    'tokens'    => [['key' => 'btc', 'label' => 'BTC', 'balance' => $totalSatoshi / 1e8]],
    'addresses' => $addresses,
]);
