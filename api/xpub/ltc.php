<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$xpub = $_GET['xpub'] ?? '';

if (!preg_match('/^[LM][a-km-zA-HJ-NP-Z1-9]{107,113}$/', $xpub)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid xPub key']);
    exit;
}

$config = file_exists(__DIR__ . '/../../../config.php')
    ? require __DIR__ . '/../../../config.php'
    : [];

$ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);

// Primary: Blockchair
$url = 'https://api.blockchair.com/litecoin/dashboards/xpub/' . urlencode($xpub);
if (!empty($config['blockchair_api_key'])) $url .= '?key=' . $config['blockchair_api_key'];
$body = @file_get_contents($url, false, $ctx);
$data = ($body !== false) ? json_decode($body, true) : null;

if ($data && isset($data['data'][$xpub]['addresses'])) {
    $addrData = $data['data'][$xpub]['addresses'];
    $totalLtc = 0;
    $addresses = [];
    foreach ($addrData as $addr => $info) {
        $ltc = ($info['balance'] ?? 0) / 1e8;
        $totalLtc += $ltc;
        if ($ltc > 0) {
            $addresses[] = [
                'address' => $addr,
                'tokens'  => [['key' => 'ltc', 'label' => 'LTC', 'balance' => $ltc]],
            ];
        }
    }
    echo json_encode([
        'tokens'    => [['key' => 'ltc', 'label' => 'LTC', 'balance' => $totalLtc]],
        'addresses' => $addresses,
    ]);
    exit;
}

// Fallback: BlockCypher
$url2 = 'https://api.blockcypher.com/v1/ltc/main/addrs/' . urlencode($xpub) . '/balance';
$body2 = @file_get_contents($url2, false, $ctx);
$data2 = ($body2 !== false) ? json_decode($body2, true) : null;

if ($data2 && isset($data2['balance'])) {
    $ltc = $data2['balance'] / 1e8;
    echo json_encode([
        'tokens'    => [['key' => 'ltc', 'label' => 'LTC', 'balance' => $ltc]],
        'addresses' => [],
    ]);
    exit;
}

http_response_code(502);
echo json_encode(['error' => 'LTC API unreachable']);
exit;
