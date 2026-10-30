<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
if ($u['role'] === 'admin') {
    redirect('admin/reservations.php');
}
run_maintenance(db());

$st = db()->prepare("SELECT r.*, f.name AS food_name, f.price, f.size_label, f.category
                     FROM reservations r JOIN foods f ON f.id = r.food_id
                     WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 200");
$st->execute([$u['id']]);
$rows = $st->fetchAll();
$active = array_filter($rows, fn($r) => $r['status'] === 'reserved');
$history = array_filter($rows, fn($r) => $r['status'] !== 'reserved');

page_header('Reservasi Saya', 'user', 'reserv');

function res_table(array $rows, bool $withCancel): void
{
    if (!$rows) {
        echo '<div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">Belum ada data.</div>';
        return;
    }
    echo '<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm"><table class="w-full text-left text-sm">';
    echo '<thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Makanan</th><th class="px-4 py-3">Jumlah</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Direservasi</th><th class="px-4 py-3">Ambil sebelum</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100">';
    foreach ($rows as $r) {
        echo '<tr><td class="px-4 py-3 font-mono font-semibold">' . e($r['code']) . '</td>';
        echo '<td class="px-4 py-3">' . e($r['food_name']) . '<div class="text-xs text-gray-500">' . e($r['size_label']) . '</div></td>';
        echo '<td class="px-4 py-3">' . (int)$r['qty'] . ' porsi</td>';
        echo '<td class="px-4 py-3">' . e(rupiah($r['qty'] * $r['price'])) . '</td>';
        echo '<td class="px-4 py-3">' . badge(RES_LABELS, $r['status']) . '</td>';
        echo '<td class="px-4 py-3 whitespace-nowrap">' . e(dt($r['reserved_at'])) . '</td>';
        echo '<td class="px-4 py-3 whitespace-nowrap">' . e(dt($r['expires_at'])) . '</td><td class="px-4 py-3">';
        if ($withCancel) {
            echo '<button data-cancel="' . (int)$r['id'] . '" class="rounded-lg border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Batalkan</button>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
?>
<h1 class="mb-1 text-2xl font-extrabold text-green-800">Reservasi saya</h1>
<p class="mb-5 text-sm text-gray-500">Tunjukkan kode reservasi ke petugas saat mengambil makanan. Reservasi yang tidak diambil sampai batas waktu akan dikembalikan ke stok.</p>

<h2 class="mb-2 font-bold">Menunggu diambil</h2>
<div class="mb-8"><?php res_table(array_values($active), true); ?></div>

<h2 class="mb-2 font-bold">Riwayat</h2>
<?php res_table(array_values($history), false); ?>
<?php page_footer();
