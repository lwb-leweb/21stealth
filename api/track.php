<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

const ALLOWED_EVENTS = [
    'app_start', 'wallet_added', 'wallet_edited',
    'config_imported', 'config_exported', 'config_saved',
    'refresh_all', 'pwa_installed',
];

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    exit;
}

$session_id = $body['session_id'] ?? '';
$event      = $body['event'] ?? '';
$properties = $body['properties'] ?? null;

if ($properties !== null && strlen(json_encode($properties)) > 512) {
    http_response_code(400);
    exit;
}

// Validate UUID v4
if (!preg_match(
    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
    $session_id
)) {
    http_response_code(400);
    exit;
}

if (!in_array($event, ALLOWED_EVENTS, true)) {
    http_response_code(400);
    exit;
}

$config = require __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare(
        'INSERT INTO events (session_id, event, properties) VALUES (?, ?, ?)'
    );
    $stmt->execute([
        $session_id,
        $event,
        $properties !== null ? json_encode($properties) : null,
    ]);
    http_response_code(200);
    echo '{}';
} catch (Exception $e) {
    http_response_code(500);
    exit;
}
