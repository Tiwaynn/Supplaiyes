<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
run_maintenance(db());
$s = admin_stats(db());

$cards = [
    ['total_items',        'Total makanan terdaftar',      'jenis makanan', 'text-green-800'],
    ['available_portions', 'Makanan tersedia',             'porsi siap dipesan', 'text-emerald-700'],
    ['distributed',        'Berhasil didistribusikan',     'porsi', 'text-blue-700'],
    ['reserved',           'Sedang direservasi',           'porsi menunggu diambil', 'text-amber-700'],
    ['withdrawn',          'Ditarik (tidak layak)',        'porsi', 'text-red-700'],
];

$hours = (int)config('priority_hours');
$prio = db()->prepare("SELECT id, name, size_label, stock, distribution_deadline FROM foods
    WHERE is_archived = 0 AND safety_status = 'layak' AND status = 'available'
      AND distribution_deadline > NOW() AND distribution_deadline <= DATE_ADD(NOW(), INTERVAL ? HOUR)
    ORDER BY distribution_deadline ASC LIMIT 8");
$prio->execute([$hours]);
$prio = $prio->fetchAll();

$logs = db()->query("SELECT l.*, f.name AS food_name, u.name AS actor_name
    FROM food_status_logs l JOIN foods f ON f.id = l.food_id LEFT JOIN users u ON u.id = l.actor_id
    ORDER BY l.id DESC LIMIT 8")->fetchAll();

page_header('Dashboard', 'admin', 'dashboard');
?>
<h1 class="mb-1 text-2xl font-extrabold text-green-800">Dashboard</h1>
<p class="mb-5 text-sm text-gray-500">Ringkasan inventaris surplus makanan. Angka diperbarui otomatis.</p>

<div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
  <?php foreach ($cards as [$key, $label, $unit, $color]): ?>
  <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
    <div class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= e($label) ?></div>
    <div class="mt-1 text-3xl font-extrabold <?= $color ?>" data-stat="<?= e($key) ?>"><?= (int)$s[$key] ?></div>
    <div class="text-xs text-gray-400"><?= e($unit) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <h2 class="mb-3 font-bold">⏱ Prioritas distribusi <span class="text-xs font-normal text-gray-500">(≤ <?= $hours ?> jam lagi)</span></h2>
    <?php if (!$prio): ?><p class="text-sm text-gray-500">Tidak ada makanan yang mendekati batas distribusi.</p><?php endif; ?>
    <ul class="divide-y divide-gray-100 text-sm">
      <?php foreach ($prio as $p): ?>
      <li class="flex items-center justify-between gap-3 py-2">
        <div><div class="font-semibold"><?= e($p['name']) ?></div><div class="text-xs text-gray-500"><?= e($p['size_label']) ?> · stok <?= (int)$p['stock'] ?></div></div>
        <div class="whitespace-nowrap text-xs text-orange-700"><?= e(dt($p['distribution_deadline'])) ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Aktivitas terbaru</h2><a class="text-xs font-semibold text-green-700" href="<?= e(url('admin/logs.php')) ?>">Lihat semua →</a></div>
    <ul class="divide-y divide-gray-100 text-sm">
      <?php foreach ($logs as $l): ?>
      <li class="py-2">
        <div><span class="font-semibold"><?= e($l['food_name']) ?></span> · <span class="text-gray-600"><?= e($l['action']) ?></span> <?= $l['new_status'] ? badge(STATUS_LABELS, $l['new_status']) : '' ?></div>
        <div class="text-xs text-gray-500"><?= e(dt($l['created_at'])) ?> · <?= e($l['actor_name'] ?? 'Sistem') ?><?= $l['note'] ? ' · ' . e($l['note']) : '' ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php page_footer();
