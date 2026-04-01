<?php
// Central CORS + auth middleware.
// Include at the top of every API endpoint with:
//   require_once __DIR__ . '/require_auth.php';         (from backend/api/)
//   require_once __DIR__ . '/../require_auth.php';      (from backend/api/balance/ or xpub/)

// __DIR__ here is backend/api/, so one level up is backend/ where config.php lives
$config = require __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: https://app.21stealth.com');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-App-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$incoming = $_SERVER['HTTP_X_APP_KEY'] ?? '';
if (!isset($config['app_key']) || !hash_equals($config['app_key'], $incoming)) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
