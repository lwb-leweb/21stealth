<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$xpub = $_GET['xpub'] ?? '';

if (!preg_match('/^dgub[a-km-zA-HJ-NP-Z1-9]{103,109}$/', $xpub)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid xPub key']);
    exit;
}

$config = file_exists(__DIR__ . '/../../../config.php')
    ? require __DIR__ . '/../../../config.php'
    : [];

$ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);

// Primary: Blockchair
$url = 'https://api.blockchair.com/dogecoin/dashboards/xpub/' . urlencode($xpub);
if (!empty($config['blockchair_api_key'])) $url .= '?key=' . $config['blockchair_api_key'];
$body = @file_get_contents($url, false, $ctx);
$data = ($body !== false) ? json_decode($body, true) : null;

if ($data && isset($data['data'][$xpub]['addresses'])) {
    $addrData = $data['data'][$xpub]['addresses'];
    $totalDoge = 0;
    $addresses = [];
    foreach ($addrData as $addr => $info) {
        $doge = ($info['balance'] ?? 0) / 1e8;
        $totalDoge += $doge;
        if ($doge > 0) {
            $addresses[] = [
                'address' => $addr,
                'tokens'  => [['key' => 'doge', 'label' => 'DOGE', 'balance' => $doge]],
            ];
        }
    }
    echo json_encode([
        'tokens'    => [['key' => 'doge', 'label' => 'DOGE', 'balance' => $totalDoge]],
        'addresses' => $addresses,
    ]);
    exit;
}

// Fallback: BlockCypher
$url2 = 'https://api.blockcypher.com/v1/doge/main/addrs/' . urlencode($xpub) . '/balance';
$body2 = @file_get_contents($url2, false, $ctx);
$data2 = ($body2 !== false) ? json_decode($body2, true) : null;

if ($data2 && isset($data2['balance'])) {
    $doge = $data2['balance'] / 1e8;
    echo json_encode([
        'tokens'    => [['key' => 'doge', 'label' => 'DOGE', 'balance' => $doge]],
        'addresses' => [],
    ]);
    exit;
}

http_response_code(502);
echo json_encode(['error' => 'DOGE API unreachable']);
exit;
