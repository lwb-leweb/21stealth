<?php
date_default_timezone_set('UTC');
$config = require __DIR__ . '/../config.php';

$stats = null;
$error = null;

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $unique_total = (int) $pdo->query('SELECT COUNT(DISTINCT session_id) FROM events')->fetchColumn();
    $active_today = (int) $pdo->query("SELECT COUNT(DISTINCT session_id) FROM events WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $active_7d    = (int) $pdo->query("SELECT COUNT(DISTINCT session_id) FROM events WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
    $active_30d   = (int) $pdo->query("SELECT COUNT(DISTINCT session_id) FROM events WHERE created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
    $returning    = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT session_id FROM events GROUP BY session_id HAVING COUNT(DISTINCT DATE(created_at)) >= 2) t")->fetchColumn();

    $event_rows = $pdo->query("SELECT event, COUNT(*) as cnt FROM events GROUP BY event ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

    $day_rows = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as cnt FROM events WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(created_at) ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);

    $stats = compact('unique_total','active_today','active_7d','active_30d','returning','event_rows','day_rows');
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>21 Stealth — Analytics</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0d0d0d; color: #e2e2e2; padding: 2rem; }
  h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 2rem; color: #fff; }
  h2 { font-size: 1rem; font-weight: 600; margin: 2rem 0 1rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.05em; }
  .cards { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
  .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.25rem 1.5rem; min-width: 140px; }
  .card .value { font-size: 2rem; font-weight: 700; color: #fff; }
  .card .label { font-size: 0.75rem; color: #888; margin-top: 0.25rem; }
  table { width: 100%; border-collapse: collapse; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; overflow: hidden; }
  th, td { padding: 0.6rem 1rem; text-align: left; font-size: 0.875rem; }
  th { color: #888; font-weight: 500; border-bottom: 1px solid #2a2a2a; }
  tr + tr td { border-top: 1px solid #1e1e1e; }
  td:last-child { text-align: right; color: #ccc; }
  .error { color: #f87171; background: #2a1212; border: 1px solid #7f1d1d; border-radius: 8px; padding: 1rem; }
  .updated { font-size: 0.75rem; color: #555; margin-top: 2rem; }
</style>
</head>
<body>
<h1>21 Stealth — Analytics</h1>

<?php if ($error): ?>
  <p class="error">Database error: <?= htmlspecialchars($error) ?></p>
<?php else: ?>

<h2>Overview</h2>
<div class="cards">
  <div class="card"><div class="value"><?= $stats['unique_total'] ?></div><div class="label">Unique sessions (all time)</div></div>
  <div class="card"><div class="value"><?= $stats['active_today'] ?></div><div class="label">Active today</div></div>
  <div class="card"><div class="value"><?= $stats['active_7d'] ?></div><div class="label">Active last 7 days</div></div>
  <div class="card"><div class="value"><?= $stats['active_30d'] ?></div><div class="label">Active last 30 days</div></div>
  <div class="card"><div class="value"><?= $stats['returning'] ?></div><div class="label">Returning sessions</div></div>
</div>

<h2>Events by type</h2>
<table>
  <thead><tr><th>Event</th><th>Count</th></tr></thead>
  <tbody>
  <?php foreach ($stats['event_rows'] as $row): ?>
    <tr><td><?= htmlspecialchars($row['event']) ?></td><td><?= (int)$row['cnt'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Events per day (last 30 days)</h2>
<table>
  <thead><tr><th>Date</th><th>Events</th></tr></thead>
  <tbody>
  <?php foreach ($stats['day_rows'] as $row): ?>
    <tr><td><?= htmlspecialchars($row['date']) ?></td><td><?= (int)$row['cnt'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p class="updated">Generated at <?= date('Y-m-d H:i:s') ?> UTC</p>

<?php endif; ?>
</body>
</html>
