<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/inventory.php';
require __DIR__ . '/layout.php';
