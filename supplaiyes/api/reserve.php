<?php
require __DIR__ . '/../includes/bootstrap.php';

$u = current_user();
if (!$u) {
    json_out(['ok' => false, 'message' => 'Silakan masuk terlebih dahulu.'], 401);
}
if ($u['role'] !== 'user') {
    json_out(['ok' => false, 'message' => 'Hanya akun penerima makanan yang dapat melakukan reservasi.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    json_out(['ok' => false, 'message' => 'Sesi tidak valid. Muat ulang halaman.'], 419);
}

$foodId = (int)($_POST['food_id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 0);

try {
    $r = reserve_food(db(), (int)$u['id'], $foodId, $qty);
    json_out(['ok' => true, 'code' => $r['code'], 'message' => 'Reservasi berhasil.']);
} catch (UserError $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('reserve: ' . $e->getMessage());
    json_out(['ok' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.'], 500);
}
