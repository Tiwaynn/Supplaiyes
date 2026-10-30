<?php
require __DIR__ . '/../includes/bootstrap.php';

$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    http_response_code(403);
    exit;
}
run_maintenance(db());
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$rows = inventory_rows(db(), $_GET);
require __DIR__ . '/../admin/partials/inventory_rows.php';
