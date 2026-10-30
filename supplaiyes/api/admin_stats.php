<?php
require __DIR__ . '/../includes/bootstrap.php';

$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    json_out(['error' => 'forbidden'], 403);
}
run_maintenance(db());
json_out(admin_stats(db()));
