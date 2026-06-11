<?php
require_once __DIR__ . '/require_auth.php';
header('Content-Type: application/json');

$cache_file = sys_get_temp_dir() . '/21stealth_prices.json';
$cache_ttl  = 60; // 1 minute

// Return cached prices if still fresh
if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_ttl) {
    echo file_get_contents($cache_file);
    exit;
}

// Fetch USD prices + 24h change from CoinGecko (single call).
// Works keyless; a free Demo API key in config.php raises the rate limit.
$ids     = 'bitcoin,ethereum,solana,litecoin,dogecoin,tron';
$cg_key  = $config['coingecko_api_key'] ?? '';
$headers = "Accept: application/json\r\nUser-Agent: 21stealth\r\n"
         . ($cg_key ? "x-cg-demo-api-key: {$cg_key}\r\n" : '');

$cgResponse = @file_get_contents(
    "https://api.coingecko.com/api/v3/simple/price?ids={$ids}&vs_currencies=usd&include_24hr_change=true",
    false,
    stream_context_create(['http' => [
        'timeout'       => 10,
        'header'        => $headers,
        'ignore_errors' => true,
    ]])
);

$cg = $cgResponse !== false ? (json_decode($cgResponse, true) ?: []) : [];

// If the upstream call failed entirely, fall back to stale cache when available
if (empty($cg['bitcoin']['usd'])) {
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
        exit;
    }
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch prices']);
    exit;
}

$coin = fn($id) => [
    'usd'       => $cg[$id]['usd'] ?? 0,
    'change24h' => $cg[$id]['usd_24h_change'] ?? null,
];

$result = json_encode([
    'bitcoin'  => $coin('bitcoin'),
    'ethereum' => $coin('ethereum'),
    'solana'   => $coin('solana'),
    'litecoin' => $coin('litecoin'),
    'dogecoin' => $coin('dogecoin'),
    'tron'     => $coin('tron'),
]);

file_put_contents($cache_file, $result);
echo $result;
