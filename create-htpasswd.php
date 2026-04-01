<?php
$username = 'admin';       // Benutzername anpassen
$password = 'PASSWORT';    // Passwort anpassen

$hash = password_hash($password, PASSWORD_BCRYPT);
$line = $username . ':' . $hash . PHP_EOL;

$file = __DIR__ . '/.htpasswd';

echo 'Target file: ' . $file . '<br>';
echo 'Dir writable: ' . (is_writable(__DIR__) ? 'YES' : 'NO') . '<br>';

$result = file_put_contents($file, $line);
if ($result === false) {
    echo 'ERROR: Could not write file.';
} else {
    echo 'Done. Written ' . $result . ' bytes.';
}
?>
