<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config  = require __DIR__ . '/../../config.php';
$address = $_GET['address'] ?? '';

if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ETH address']);
    exit;
}

$rpcs = [];
if (!empty($config['alchemy_api_key'])) {
    $rpcs[] = 'https://eth-mainnet.g.alchemy.com/v2/' . $config['alchemy_api_key'];
}
$rpcs = array_merge($rpcs, [
    'https://ethereum.publicnode.com',
    'https://gateway.tenderly.co/public/mainnet',
    'https://1rpc.io/eth',
]);

function eth_rpc(array $rpcs, array $body): ?string {
    $payload = json_encode($body);
    foreach ($rpcs as $url) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => $payload,
            'timeout' => 10,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) continue;
        $json = json_decode($res, true);
        if (isset($json['result']) && is_string($json['result'])) return $json['result'];
    }
    return null;
}

function hex_to_float(string $hex): float {
    // Strip 0x prefix if present
    $hex = strpos($hex, '0x') === 0 ? substr($hex, 2) : $hex;
    return hexdec($hex);
}

function pad_address(string $address): string {
    // Safely remove 0x prefix using substr
    $stripped = strpos($address, '0x') === 0 ? substr($address, 2) : $address;
    return str_pad(strtolower($stripped), 64, '0', STR_PAD_LEFT);
}

function erc20_balance(array $rpcs, string $contract, string $address, int $decimals): float {
    $data   = '0x70a08231' . pad_address($address);
    $result = eth_rpc($rpcs, [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_call',
        'params'  => [['to' => $contract, 'data' => $data], 'latest'],
    ]);
    if (!$result || $result === '0x') return 0;
    return hex_to_float($result) / pow(10, $decimals);
}

$eth_hex = eth_rpc($rpcs, [
    'jsonrpc' => '2.0', 'id' => 1,
    'method'  => 'eth_getBalance',
    'params'  => [$address, 'latest'],
]);

if ($eth_hex === null) {
    http_response_code(502);
    echo json_encode(['error' => 'ETH RPC unavailable']);
    exit;
}

$eth  = hex_to_float($eth_hex) / 1e18;
$usdt = erc20_balance($rpcs, '0xdAC17F958D2ee523a2206206994597C13D831ec7', $address, 6);
$usdc = erc20_balance($rpcs, '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', $address, 6);

$tokens = [['key' => 'eth', 'label' => 'ETH', 'balance' => $eth]];
if ($usdt > 0) $tokens[] = ['key' => 'usdt', 'label' => 'USDT', 'balance' => $usdt];
if ($usdc > 0) $tokens[] = ['key' => 'usdc', 'label' => 'USDC', 'balance' => $usdc];

echo json_encode(['tokens' => $tokens]);
