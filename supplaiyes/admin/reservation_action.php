<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/reservations.php');
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
try {
    switch ($_POST['action'] ?? '') {
        case 'confirm':
            confirm_pickup(db(), $id, (int)$admin['id']);
            flash('ok', 'Pengambilan dikonfirmasi.');
            break;
        case 'cancel':
            cancel_reservation(db(), $id, null, (int)$admin['id'], 'Dibatalkan oleh admin');
            flash('ok', 'Reservasi dibatalkan.');
            break;
        default:
            flash('err', 'Aksi tidak dikenal.');
    }
} catch (UserError $e) {
    flash('err', $e->getMessage());
} catch (Throwable $e) {
    error_log('reservation_action: ' . $e->getMessage());
    flash('err', 'Terjadi kesalahan server.');
}
redirect('admin/reservations.php');
