<?php
require_once __DIR__ . '/require_auth.php';
header('Content-Type: application/json');

$cache_file = sys_get_temp_dir() . '/21stealth_price_history_v2.json';
$lock_file  = sys_get_temp_dir() . '/21stealth_price_history.lock';
$cache_ttl  = 3600; // 1 hour — historical daily closes don't change, today's may still update

// CoinGecko daily history — no API key required.
// Free tier caps daily granularity at 365 days; days > 90 are returned as daily closes.
$coins = ['bitcoin', 'ethereum', 'solana', 'litecoin', 'dogecoin', 'tron'];

// Cache is per coin: { coin: { fetched: ts, prices: { date: usd } } }.
// A coin that fails to refresh keeps its last good data, so a rate-limited
// fetch never throws away what we already have.
$load_cache = function () use ($cache_file) {
    $decoded = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : null;
    return is_array($decoded) ? $decoded : [];
};

$stale_coins = function ($cache) use ($coins, $cache_ttl) {
    return array_values(array_filter($coins, fn($id) =>
        empty($cache[$id]['prices']) || time() - ($cache[$id]['fetched'] ?? 0) >= $cache_ttl
    ));
};

$respond = function ($cache) use ($coins) {
    $out = [];
    foreach ($coins as $id) {
        if (!empty($cache[$id]['prices'])) $out[$id] = $cache[$id]['prices'];
    }
    if (!$out) {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch price history']);
        exit;
    }
    echo json_encode($out);
    exit;
};

$cache = $load_cache();
if (!$stale_coins($cache)) $respond($cache);

// Only one request refreshes at a time (avoids a stampede on CoinGecko at expiry).
// If another request is already refreshing and every coin has (stale) data, serve that
// right away; if data is missing, wait for the running refresh instead.
$lock = fopen($lock_file, 'c');
$has_all = count(array_filter($coins, fn($id) => !empty($cache[$id]['prices']))) === count($coins);
if (!flock($lock, $has_all ? LOCK_EX | LOCK_NB : LOCK_EX)) $respond($cache);

// Another request may have refreshed while we waited for the lock
$cache = $load_cache();
$stale = $stale_coins($cache);
if (!$stale) $respond($cache);

// After a rate limit, don't make every visitor wait for more 429s: serve what we have
// until the cooldown is over (the free tier allows only a few calls per minute)
if (time() < ($cache['_cooldown_until'] ?? 0)) $respond($cache);

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
// Returns the price map, null on a failed/empty response, or false when still rate-limited.
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
    return false;
};

foreach ($stale as $i => $id) {
    if ($i > 0) usleep(1500000); // 1.5s between calls — stay under the free-tier rate limit
    $priceMap = $fetch_coin($id);
    if ($priceMap === false) { // rate-limited: keep old data, retry the rest after a cooldown
        $cache['_cooldown_until'] = time() + 60;
        break;
    }
    if ($priceMap) $cache[$id] = ['fetched' => time(), 'prices' => $priceMap];
}

// Atomic write so concurrent readers never see a half-written file
$tmp = $cache_file . '.' . getmypid();
file_put_contents($tmp, json_encode($cache));
rename($tmp, $cache_file);
flock($lock, LOCK_UN);

$respond($cache);
