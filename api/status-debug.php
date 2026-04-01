<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'chains' => [
        'btc'  => ['status' => 'critical', 'message' => 'Blockstream unreachable'],
        'eth'  => ['status' => 'degraded', 'message' => 'Primary RPC unavailable, using fallback'],
        'sol'  => ['status' => 'degraded', 'message' => 'Rate limited'],
        'ltc'  => ['status' => 'ok',       'message' => null],
        'doge' => ['status' => 'ok',       'message' => null],
        'trx'  => ['status' => 'critical', 'message' => 'TronGrid unreachable'],
    ],
    'ts' => time(),
]);
