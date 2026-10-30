<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/foods.php');
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$back = 'admin/foods.php';

try {
    switch ($action) {
        case 'distribute':
            distribute_food(db(), $id, (int)$admin['id']);
            flash('ok', 'Makanan ditandai sebagai terdistribusi.');
            break;
        case 'withdraw':
            withdraw_food(db(), $id, (int)$admin['id']);
            flash('ok', 'Makanan ditarik dari distribusi dan reservasi aktif dibatalkan.');
            break;
        case 'archive':
            archive_food(db(), $id, (int)$admin['id'], true);
            flash('ok', 'Makanan diarsipkan.');
            break;
        case 'restore':
            archive_food(db(), $id, (int)$admin['id'], false);
            flash('ok', 'Makanan dipulihkan dari arsip.');
            $back = 'admin/foods.php?archived=1';
            break;
        default:
            flash('err', 'Aksi tidak dikenal.');
    }
} catch (UserError $e) {
    flash('err', $e->getMessage());
} catch (Throwable $e) {
    error_log('food_action: ' . $e->getMessage());
    flash('err', 'Terjadi kesalahan server.');
}
redirect($back);
