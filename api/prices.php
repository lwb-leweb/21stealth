<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cache_file = sys_get_temp_dir() . '/21stealth_prices.json';
$cache_ttl  = 60; // 1 minute

// Return cached prices if still fresh
if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_ttl) {
    echo file_get_contents($cache_file);
    exit;
}

// Fetch USD prices + 24h change from CryptoCompare (single call)
$ccResponse = @file_get_contents(
    'https://min-api.cryptocompare.com/data/pricemultifull?fsyms=BTC,ETH,SOL,LTC,DOGE,TRX&tsyms=USD',
    false,
    stream_context_create(['http' => ['timeout' => 10]])
);

if ($ccResponse === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch prices']);
    exit;
}

$cc = json_decode($ccResponse, true)['RAW'] ?? [];

$result = json_encode([
    'bitcoin'  => ['usd' => $cc['BTC']['USD']['PRICE']  ?? 0, 'change24h' => $cc['BTC']['USD']['CHANGEPCT24HOUR']  ?? null],
    'ethereum' => ['usd' => $cc['ETH']['USD']['PRICE']  ?? 0, 'change24h' => $cc['ETH']['USD']['CHANGEPCT24HOUR']  ?? null],
    'solana'   => ['usd' => $cc['SOL']['USD']['PRICE']  ?? 0, 'change24h' => $cc['SOL']['USD']['CHANGEPCT24HOUR']  ?? null],
    'litecoin' => ['usd' => $cc['LTC']['USD']['PRICE']  ?? 0, 'change24h' => $cc['LTC']['USD']['CHANGEPCT24HOUR']  ?? null],
    'dogecoin' => ['usd' => $cc['DOGE']['USD']['PRICE'] ?? 0, 'change24h' => $cc['DOGE']['USD']['CHANGEPCT24HOUR'] ?? null],
    'tron'     => ['usd' => $cc['TRX']['USD']['PRICE']  ?? 0, 'change24h' => $cc['TRX']['USD']['CHANGEPCT24HOUR']  ?? null],
]);

file_put_contents($cache_file, $result);
echo $result;
