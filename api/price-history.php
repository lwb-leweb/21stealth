<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cache_file = sys_get_temp_dir() . '/21stealth_price_history.json';
$cache_ttl  = 3600; // 1 hour — historical daily closes don't change, today's may still update

if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_ttl) {
    echo file_get_contents($cache_file);
    exit;
}

$api_key = 'YOUR_API_KEY_HERE';

$coins = [
    'bitcoin'  => 'BTC',
    'ethereum' => 'ETH',
    'solana'   => 'SOL',
    'litecoin' => 'LTC',
    'dogecoin' => 'DOGE',
    'tron'     => 'TRX',
];

$result = [];

$context = stream_context_create(['http' => [
    'timeout' => 10,
    'header'  => "authorization: Apikey {$api_key}",
]]);

foreach ($coins as $id => $symbol) {
    $url      = "https://min-api.cryptocompare.com/data/v2/histoday?fsym={$symbol}&tsym=USD&limit=2000";
    $response = @file_get_contents($url, false, $context);
    if ($response === false) continue;

    $data = json_decode($response, true);
    $days = $data['Data']['Data'] ?? [];

    $priceMap = [];
    foreach ($days as $day) {
        if (empty($day['time']) || empty($day['close'])) continue;
        $date           = date('Y-m-d', $day['time']);
        $priceMap[$date] = $day['close'];
    }

    $result[$id] = $priceMap;
}

$json = json_encode($result);
file_put_contents($cache_file, $json);
echo $json;
