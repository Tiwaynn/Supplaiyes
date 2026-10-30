<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . $config['db_timezone'] . "'",
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Koneksi database gagal. Pastikan MySQL di XAMPP aktif, database "supplaiyes" sudah di-import, dan config/config.php benar.');
        }
    }
    return $pdo;
}
