<?php
require __DIR__ . '/../includes/bootstrap.php';

$u = current_user();
if (!$u || $u['role'] !== 'user') {
    json_out(['ok' => false, 'message' => 'Tidak diizinkan.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    json_out(['ok' => false, 'message' => 'Sesi tidak valid. Muat ulang halaman.'], 419);
}

try {
    cancel_reservation(db(), (int)($_POST['reservation_id'] ?? 0), (int)$u['id'], (int)$u['id'], 'Dibatalkan oleh pengguna');
    json_out(['ok' => true, 'message' => 'Reservasi dibatalkan.']);
} catch (UserError $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('cancel: ' . $e->getMessage());
    json_out(['ok' => false, 'message' => 'Terjadi kesalahan server.'], 500);
}
