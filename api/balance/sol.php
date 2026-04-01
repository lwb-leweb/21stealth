<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$address = $_GET['address'] ?? '';

if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid SOL address']);
    exit;
}

$payload = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'getBalance', 'params' => [$address]]);

$res = @file_get_contents('https://api.mainnet-beta.solana.com', false, stream_context_create(['http' => [
    'method'  => 'POST',
    'header'  => 'Content-Type: application/json',
    'content' => $payload,
    'timeout' => 10,
]]));

if ($res === false) {
    http_response_code(502);
    echo json_encode(['error' => 'SOL API failed']);
    exit;
}

$data = json_decode($res, true);
$sol  = $data['result']['value'] / 1e9;

echo json_encode(['tokens' => [['key' => 'sol', 'label' => 'SOL', 'balance' => $sol]]]);
