<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cache_file = sys_get_temp_dir() . '/21stealth_stats.json';
$cache_ttl  = 60;

if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_ttl) {
    echo file_get_contents($cache_file);
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

    $unique_total = (int) $pdo->query(
        'SELECT COUNT(DISTINCT session_id) FROM events'
    )->fetchColumn();

    $active_today = (int) $pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM events WHERE DATE(created_at) = CURDATE()"
    )->fetchColumn();

    $active_7d = (int) $pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM events WHERE created_at >= NOW() - INTERVAL 7 DAY"
    )->fetchColumn();

    $active_30d = (int) $pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM events WHERE created_at >= NOW() - INTERVAL 30 DAY"
    )->fetchColumn();

    $returning = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT session_id FROM events
            GROUP BY session_id
            HAVING COUNT(DISTINCT DATE(created_at)) >= 2
         ) t"
    )->fetchColumn();

    $event_rows = $pdo->query(
        "SELECT event, COUNT(*) as cnt FROM events GROUP BY event ORDER BY cnt DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $events_by_type = [];
    foreach ($event_rows as $row) {
        $events_by_type[$row['event']] = (int) $row['cnt'];
    }

    $day_rows = $pdo->query(
        "SELECT DATE(created_at) as date, COUNT(*) as cnt
         FROM events
         WHERE created_at >= NOW() - INTERVAL 30 DAY
         GROUP BY DATE(created_at)
         ORDER BY date DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $events_by_day = array_map(
        fn($r) => ['date' => $r['date'], 'count' => (int) $r['cnt']],
        $day_rows
    );

    $result = json_encode([
        'unique_sessions_total' => $unique_total,
        'active_today'          => $active_today,
        'active_7d'             => $active_7d,
        'active_30d'            => $active_30d,
        'returning'             => $returning,
        'events_by_type'        => $events_by_type,
        'events_by_day'         => $events_by_day,
    ]);

    file_put_contents($cache_file, $result);
    echo $result;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
