<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function fetch_url(string $url, array $opts = []): array {
    $ctx = stream_context_create(['http' => array_merge([
        'timeout'       => 5,
        'ignore_errors' => true,
    ], $opts)]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/HTTP\/\S+\s+(\d+)/', $h, $m)) $status = (int)$m[1];
    }
    return [$body === false ? null : $body, $status];
}

function fetch_post(string $url, string $payload, array $headers = []): array {
    return fetch_url($url, [
        'method'  => 'POST',
        'header'  => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
        'content' => $payload,
    ]);
}

function result(string $status, ?string $message = null): array {
    return ['status' => $status, 'message' => $message];
}

$config = file_exists(__DIR__ . '/../../config.php')
    ? require __DIR__ . '/../../config.php'
    : [];

// BTC — Blockstream
function check_btc(): array {
    [$body, $code] = fetch_url('https://blockstream.info/api/blocks/tip/height');
    if ($body === null)              return result('critical', 'Blockstream unreachable');
    if ($code === 429)               return result('degraded', 'Rate limited');
    if ($code !== 200)               return result('critical', "HTTP $code");
    if (!is_numeric(trim($body)))    return result('critical', 'Unexpected response');
    return result('ok');
}

// ETH — public RPCs
function check_eth(array $config): array {
    $rpcs = [];
    if (!empty($config['alchemy_api_key'])) {
        $rpcs[] = ['url' => 'https://eth-mainnet.g.alchemy.com/v2/' . $config['alchemy_api_key'], 'primary' => true];
    }
    foreach ([
        'https://ethereum.publicnode.com',
        'https://gateway.tenderly.co/public/mainnet',
        'https://1rpc.io/eth',
    ] as $url) {
        $rpcs[] = ['url' => $url, 'primary' => false];
    }

    $payload   = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => []]);
    $primaryOk = false;
    foreach ($rpcs as $rpc) {
        [$body, $code] = fetch_post($rpc['url'], $payload);
        if ($code === 200 && !empty(json_decode($body ?? '', true)['result'])) {
            if ($rpc['primary']) return result('ok');
            $primaryOk = false;
            return result('degraded', 'Primary RPC unavailable, using fallback');
        }
        if ($rpc['primary']) $primaryOk = false;
    }
    return result('critical', 'All ETH RPCs unreachable');
}

// SOL — Solana mainnet RPC
function check_sol(): array {
    $payload = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'getHealth']);
    [$body, $code] = fetch_post('https://api.mainnet-beta.solana.com', $payload);
    if ($body === null || $code === 0) return result('critical', 'Solana RPC unreachable');
    if ($code === 429)                 return result('degraded', 'Rate limited');
    if ($code !== 200)                 return result('critical', "HTTP $code");
    $json = json_decode($body, true);
    if (($json['result'] ?? '') !== 'ok') return result('degraded', $json['error']['message'] ?? 'Unhealthy');
    return result('ok');
}

// LTC — Blockchair (primary) + BlockCypher (fallback)
function check_ltc(array $config): array {
    $url = 'https://api.blockchair.com/litecoin/stats';
    if (!empty($config['blockchair_api_key'])) $url .= '?key=' . $config['blockchair_api_key'];
    [$body, $code] = fetch_url($url);
    if ($code === 200 && !empty(json_decode($body ?? '', true)['data'])) return result('ok');

    // Fallback
    [$body, $code] = fetch_url('https://api.blockcypher.com/v1/ltc/main');
    if ($code === 429)                                                       return result('degraded', 'Rate limited');
    if ($code === 200 && !empty(json_decode($body ?? '', true)['height']))   return result('degraded', 'Primary unavailable, using fallback');
    return result('critical', 'All LTC APIs unreachable');
}

// DOGE — Blockchair (primary) + BlockCypher (fallback)
function check_doge(array $config): array {
    $url = 'https://api.blockchair.com/dogecoin/stats';
    if (!empty($config['blockchair_api_key'])) $url .= '?key=' . $config['blockchair_api_key'];
    [$body, $code] = fetch_url($url);
    if ($code === 200 && !empty(json_decode($body ?? '', true)['data'])) return result('ok');

    // Fallback
    [$body, $code] = fetch_url('https://api.blockcypher.com/v1/doge/main');
    if ($code === 429)                                                       return result('degraded', 'Rate limited');
    if ($code === 200 && !empty(json_decode($body ?? '', true)['height']))   return result('degraded', 'Primary unavailable, using fallback');
    return result('critical', 'All DOGE APIs unreachable');
}

// TRX — TronGrid
function check_trx(array $config): array {
    $headers = [];
    if (!empty($config['trongrid_api_key'])) $headers[] = 'TRON-PRO-API-KEY: ' . $config['trongrid_api_key'];
    [$body, $code] = fetch_url('https://api.trongrid.io/wallet/getnowblock', [
        'header' => implode("\r\n", $headers),
    ]);
    if ($body === null || $code === 0) return result('critical', 'TronGrid unreachable');
    if ($code === 429)                 return result('degraded', 'Rate limited');
    if ($code !== 200)                 return result('critical', "HTTP $code");
    if (empty(json_decode($body, true)['blockID'])) return result('critical', 'Unexpected response');
    return result('ok');
}

echo json_encode([
    'chains' => [
        'btc'  => check_btc(),
        'eth'  => check_eth($config),
        'sol'  => check_sol(),
        'ltc'  => check_ltc($config),
        'doge' => check_doge($config),
        'trx'  => check_trx($config),
    ],
    'ts' => time(),
]);
