<?php
require_once __DIR__ . '/require_auth.php';
header('Content-Type: application/json');

$cache_file = sys_get_temp_dir() . '/21stealth_price_history.json';
$cache_ttl  = 3600; // 1 hour — historical daily closes don't change, today's may still update

if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_ttl) {
    echo file_get_contents($cache_file);
    exit;
}

// CoinGecko daily history — no API key required.
// Free tier caps daily granularity at 365 days; days > 90 are returned as daily closes.
$coins = ['bitcoin', 'ethereum', 'solana', 'litecoin', 'dogecoin', 'tron'];

$result = [];

// Works keyless; a free Demo API key in config.php raises the rate limit (30/min).
$cg_key  = $config['coingecko_api_key'] ?? '';
$headers = "Accept: application/json\r\nUser-Agent: 21stealth\r\n"
         . ($cg_key ? "x-cg-demo-api-key: {$cg_key}\r\n" : '');

$context = stream_context_create(['http' => [
    'timeout'       => 15,
    'header'        => $headers,
    'ignore_errors' => true,
]]);

// Fetch one coin's daily closes, retrying on CoinGecko's 429 rate limit.
$fetch_coin = function ($id) use ($context) {
    $url = "https://api.coingecko.com/api/v3/coins/{$id}/market_chart?vs_currency=usd&days=365";

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $response = @file_get_contents($url, false, $context);

        // $http_response_header is set by the HTTP wrapper after the call
        $status = 0;
        if (!empty($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($status === 429) {
            sleep(2); // backoff, then retry
            continue;
        }
        if ($response === false) return null;

        $points   = json_decode($response, true)['prices'] ?? [];
        $priceMap = [];
        foreach ($points as $point) {
            if (!isset($point[0], $point[1])) continue;
            $date            = date('Y-m-d', (int) ($point[0] / 1000)); // ms → s
            $priceMap[$date] = $point[1];                               // last point of a day wins
        }
        return $priceMap ?: null;
    }
    return null;
};

foreach ($coins as $i => $id) {
    if ($i > 0) usleep(1500000); // 1.5s between calls — stay under the free-tier rate limit
    $priceMap = $fetch_coin($id);
    if ($priceMap) $result[$id] = $priceMap;
}

// Only cache a complete result. On any missing coin, serve stale cache rather
// than freezing a partial dataset for the next hour.
if (count($result) < count($coins)) {
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
        exit;
    }
    if (empty($result['bitcoin'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch price history']);
        exit;
    }
    // No cache to fall back to: return the partial result without caching it.
    echo json_encode($result);
    exit;
}

$json = json_encode($result);
file_put_contents($cache_file, $json);
echo $json;
