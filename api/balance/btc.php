<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$address = $_GET['address'] ?? '';

if (!preg_match('/^(1|3)[1-9A-HJ-NP-Za-km-z]{24,33}$|^bc1[0-9a-z]{6,87}$/', $address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid BTC address']);
    exit;
}

$res = @file_get_contents(
    'https://blockstream.info/api/address/' . urlencode($address),
    false,
    stream_context_create(['http' => ['timeout' => 10]])
);

if ($res === false) {
    http_response_code(502);
    echo json_encode(['error' => 'BTC API failed']);
    exit;
}

$data    = json_decode($res, true);
$satoshi = $data['chain_stats']['funded_txo_sum'] - $data['chain_stats']['spent_txo_sum'];
$btc     = $satoshi / 1e8;

echo json_encode(['tokens' => [['key' => 'btc', 'label' => 'BTC', 'balance' => $btc]]]);
