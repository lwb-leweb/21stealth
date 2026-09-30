<?php
// Password gate for admin pages (e.g. stats-dashboard.php).
// Include right after loading $config:
//   require_once __DIR__ . '/require_admin.php';
// Uses a session + login form instead of HTTP Basic Auth, because PHP under CGI/FPM
// (e.g. IONOS) often never receives the Authorization header.

$admin_password = $config['admin_password'] ?? '';
if ($admin_password === '') {
    http_response_code(503);
    exit('Admin password not configured (admin_password in config.php).');
}

session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_name('21stealth_admin');
session_start();

$self = strtok($_SERVER['REQUEST_URI'], '?');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: $self");
    exit;
}

$login_error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (hash_equals($admin_password, (string) $_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header("Location: $self");
        exit;
    }
    sleep(1); // slow down guessing
    $login_error = true;
}

if (empty($_SESSION['admin'])) {
    http_response_code(401);
    header('Cache-Control: no-store');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>21 Stealth — Login</title>
<style>
  body { font-family: system-ui, sans-serif; background: #0d0d0d; color: #e2e2e2; margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; box-sizing: border-box; }
  form { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.5rem; width: 100%; max-width: 320px; display: grid; gap: 0.75rem; }
  h1 { font-size: 1.1rem; margin: 0 0 0.25rem; color: #fff; }
  input, button { font: inherit; padding: 0.6rem 0.75rem; border-radius: 6px; border: 1px solid #333; }
  input { background: #0d0d0d; color: #fff; }
  button { background: #fff; color: #0d0d0d; border: 0; font-weight: 600; cursor: pointer; }
  .error { color: #f87171; font-size: 0.875rem; }
</style>
</head>
<body>
<form method="post">
  <h1>21 Stealth Admin</h1>
  <?php if ($login_error): ?><div class="error">Wrong password.</div><?php endif; ?>
  <input type="password" name="password" placeholder="Password" autofocus required autocomplete="current-password">
  <button type="submit">Log in</button>
</form>
</body>
</html>
<?php
    exit;
}
