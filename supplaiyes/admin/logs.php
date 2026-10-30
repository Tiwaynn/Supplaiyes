<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$tab = ($_GET['t'] ?? 'food') === 'reservation' ? 'reservation' : 'food';

if ($tab === 'food') {
    $rows = db()->query("SELECT l.*, f.name AS food_name, u.name AS actor_name, u.role AS actor_role
        FROM food_status_logs l JOIN foods f ON f.id = l.food_id LEFT JOIN users u ON u.id = l.actor_id
        ORDER BY l.id DESC LIMIT 300")->fetchAll();
} else {
    $rows = db()->query("SELECT l.*, r.code, f.name AS food_name, u.name AS actor_name, u.role AS actor_role
        FROM reservation_logs l JOIN reservations r ON r.id = l.reservation_id JOIN foods f ON f.id = r.food_id
        LEFT JOIN users u ON u.id = l.actor_id
        ORDER BY l.id DESC LIMIT 300")->fetchAll();
}

function actor_label(array $r): string
{
    if (!$r['actor_name']) {
        return 'Sistem';
    }
    return $r['actor_name'] . ($r['actor_role'] === 'admin' ? ' (admin)' : ' (user)');
}

page_header('Riwayat', 'admin', 'logs');
$tabCls = fn($on) => 'rounded-lg px-4 py-2 text-sm font-semibold ' . ($on ? 'bg-green-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100');
?>
<h1 class="mb-1 text-2xl font-extrabold text-green-800">Riwayat perubahan</h1>
<p class="mb-4 text-sm text-gray-500">Semua perubahan tercatat dengan waktu dan identitas pelaku. Data tidak dihapus permanen.</p>
<div class="mb-4 flex gap-2">
  <a class="<?= $tabCls($tab === 'food') ?>" href="?t=food">Inventaris makanan</a>
  <a class="<?= $tabCls($tab === 'reservation') ?>" href="?t=reservation">Reservasi</a>
</div>

<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
<table class="w-full text-left text-sm">
<?php if ($tab === 'food'): ?>
  <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Waktu</th><th class="px-3 py-3">Makanan</th><th class="px-3 py-3">Aksi</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Perubahan porsi</th><th class="px-3 py-3">Oleh</th><th class="px-3 py-3">Catatan</th></tr></thead>
  <tbody class="divide-y divide-gray-100">
  <?php foreach ($rows as $r): ?>
    <tr class="align-top">
      <td class="px-3 py-2 whitespace-nowrap"><?= e(dt($r['created_at'])) ?></td>
      <td class="px-3 py-2">#<?= (int)$r['food_id'] ?> <?= e($r['food_name']) ?></td>
      <td class="px-3 py-2 font-mono text-xs"><?= e($r['action']) ?></td>
      <td class="px-3 py-2 whitespace-nowrap"><?= $r['old_status'] ? e(STATUS_LABELS[$r['old_status']][0] ?? $r['old_status']) . ' → ' : '' ?><?= $r['new_status'] ? badge(STATUS_LABELS, $r['new_status']) : '' ?></td>
      <td class="px-3 py-2"><?= (int)$r['qty_change'] > 0 ? '+' : '' ?><?= (int)$r['qty_change'] ?></td>
      <td class="px-3 py-2 whitespace-nowrap"><?= e(actor_label($r)) ?></td>
      <td class="px-3 py-2 text-gray-600"><?= e($r['note']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
<?php else: ?>
  <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Waktu</th><th class="px-3 py-3">Kode</th><th class="px-3 py-3">Makanan</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Oleh</th><th class="px-3 py-3">Catatan</th></tr></thead>
  <tbody class="divide-y divide-gray-100">
  <?php foreach ($rows as $r): ?>
    <tr class="align-top">
      <td class="px-3 py-2 whitespace-nowrap"><?= e(dt($r['created_at'])) ?></td>
      <td class="px-3 py-2 font-mono font-semibold"><?= e($r['code']) ?></td>
      <td class="px-3 py-2"><?= e($r['food_name']) ?></td>
      <td class="px-3 py-2 whitespace-nowrap"><?= $r['old_status'] ? e(RES_LABELS[$r['old_status']][0] ?? $r['old_status']) . ' → ' : '' ?><?= badge(RES_LABELS, $r['new_status']) ?></td>
      <td class="px-3 py-2 whitespace-nowrap"><?= e(actor_label($r)) ?></td>
      <td class="px-3 py-2 text-gray-600"><?= e($r['note']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
<?php endif; ?>
</table>
<?php if (!$rows): ?><div class="p-8 text-center text-sm text-gray-500">Belum ada riwayat.</div><?php endif; ?>
</div>
<?php page_footer();
