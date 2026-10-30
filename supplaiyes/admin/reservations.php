<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
run_maintenance(db());

$status = (string)($_GET['status'] ?? '');
$w = '1=1';
$p = [];
if (isset(RES_LABELS[$status])) {
    $w = 'r.status = ?';
    $p[] = $status;
}
$st = db()->prepare("SELECT r.*, f.name AS food_name, f.price, u.name AS user_name, u.email
    FROM reservations r JOIN foods f ON f.id = r.food_id JOIN users u ON u.id = r.user_id
    WHERE {$w} ORDER BY r.id DESC LIMIT 300");
$st->execute($p);
$rows = $st->fetchAll();

page_header('Reservasi', 'admin', 'reserv');
$btn = 'rounded-lg border px-2.5 py-1 text-xs font-semibold whitespace-nowrap';
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold text-green-800">Reservasi</h1>
    <p class="text-sm text-gray-500">Konfirmasi pengambilan setelah user menunjukkan kode reservasi.</p>
  </div>
  <form method="get">
    <select name="status" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
      <option value="">Semua status</option>
      <?php foreach (RES_LABELS as $k => [$l]): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
  <table class="w-full text-left text-sm">
    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
      <tr><th class="px-3 py-3">Kode</th><th class="px-3 py-3">Penerima</th><th class="px-3 py-3">Makanan</th><th class="px-3 py-3">Jumlah</th><th class="px-3 py-3">Total</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Direservasi</th><th class="px-3 py-3">Batas ambil</th><th class="px-3 py-3">Aksi</th></tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
    <?php if (!$rows): ?><tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">Belum ada reservasi.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr class="align-top hover:bg-gray-50">
        <td class="px-3 py-3 font-mono font-semibold"><?= e($r['code']) ?></td>
        <td class="px-3 py-3"><?= e($r['user_name']) ?><div class="text-xs text-gray-500"><?= e($r['email']) ?></div></td>
        <td class="px-3 py-3"><?= e($r['food_name']) ?></td>
        <td class="px-3 py-3"><?= (int)$r['qty'] ?></td>
        <td class="px-3 py-3 whitespace-nowrap"><?= e(rupiah($r['qty'] * $r['price'])) ?></td>
        <td class="px-3 py-3"><?= badge(RES_LABELS, $r['status']) ?></td>
        <td class="px-3 py-3 whitespace-nowrap"><?= e(dt($r['reserved_at'])) ?></td>
        <td class="px-3 py-3 whitespace-nowrap"><?= e(dt($r['expires_at'])) ?></td>
        <td class="px-3 py-3">
          <?php if ($r['status'] === 'reserved'): ?>
          <div class="flex gap-1.5">
            <form method="post" action="<?= e(url('admin/reservation_action.php')) ?>" data-confirm="Konfirmasi makanan sudah diambil?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="confirm"><button class="<?= $btn ?> border-green-600 text-green-700 hover:bg-green-50">Konfirmasi Pengambilan</button></form>
            <form method="post" action="<?= e(url('admin/reservation_action.php')) ?>" data-confirm="Batalkan reservasi ini?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="<?= $btn ?> border-red-400 text-red-700 hover:bg-red-50">Batalkan</button></form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php page_footer();
