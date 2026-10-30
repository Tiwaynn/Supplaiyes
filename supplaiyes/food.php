<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
if ($u['role'] === 'admin') {
    redirect('admin/foods.php');
}
run_maintenance(db());

$st = db()->prepare("SELECT *, TIMESTAMPDIFF(SECOND, NOW(), distribution_deadline) AS secs
                     FROM foods WHERE id = ? AND is_archived = 0 AND safety_status = 'layak'");
$st->execute([(int)($_GET['id'] ?? 0)]);
$f = $st->fetch();
if (!$f) {
    http_response_code(404);
    page_header('Tidak ditemukan', 'user', 'home');
    echo '<div class="rounded-xl bg-white p-8 text-center shadow-sm">Makanan tidak ditemukan atau sudah tidak tersedia. <a class="font-semibold text-green-700" href="' . e(url('catalog.php')) . '">Kembali ke beranda</a></div>';
    page_footer();
    exit;
}
$secs = (int)$f['secs'];
$can = $f['status'] === 'available' && (int)$f['stock'] > 0 && $secs > 0;
$priority = $secs > 0 && $secs <= (int)config('priority_hours') * 3600;

page_header($f['name'], 'user', 'home');
?>
<script>document.addEventListener('DOMContentLoaded', function(){ document.body.dataset.page = 'food'; });</script>
<a href="<?= e(url('catalog.php')) ?>" class="mb-3 inline-block text-sm text-green-700 hover:underline">← Kembali</a>
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm md:flex">
  <div class="flex h-48 items-center justify-center bg-gradient-to-br from-green-100 to-green-50 text-8xl md:h-auto md:w-72 md:shrink-0"><?= e(category_icon($f['category'])) ?></div>
  <div class="flex-1 p-6">
    <div class="flex flex-wrap gap-2">
      <?= badge(STATUS_LABELS, $f['status']) ?>
      <?php if ($priority): ?><span class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-800">⏱ Prioritas Distribusi</span><?php endif; ?>
    </div>
    <h1 class="mt-2 text-2xl font-extrabold text-gray-900"><?= e($f['name']) ?></h1>
    <p class="text-sm text-gray-500"><?= e($f['category']) ?> · <?= e($f['size_label']) ?></p>
    <p class="mt-3">
      <?php if ($f['normal_price']): ?><span class="mr-2 text-sm text-gray-400 line-through"><?= e(rupiah($f['normal_price'])) ?></span><?php endif; ?>
      <span class="text-2xl font-extrabold text-green-700"><?= e(rupiah($f['price'])) ?></span>
    </p>
    <?php if ($f['description']): ?><p class="mt-3 whitespace-pre-line text-sm text-gray-700"><?= e($f['description']) ?></p><?php endif; ?>
    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
      <div><dt class="text-gray-500">Stok tersedia</dt><dd class="font-semibold"><?= (int)$f['stock'] ?> porsi</dd></div>
      <div><dt class="text-gray-500">Batas distribusi</dt><dd class="font-semibold"><?= e(dt($f['distribution_deadline'])) ?> (<?= e(time_left($secs)) ?>)</dd></div>
      <div><dt class="text-gray-500">Waktu produksi / diterima</dt><dd class="font-semibold"><?= e(dt($f['produced_at'])) ?></dd></div>
      <div><dt class="text-gray-500">Status keamanan pangan</dt><dd class="font-semibold"><?= e(SAFETY_LABELS[$f['safety_status']][0]) ?></dd></div>
    </dl>
    <div class="mt-6">
      <button <?= $can ? '' : 'disabled' ?> data-reserve="<?= (int)$f['id'] ?>" data-name="<?= e($f['name']) ?>" data-stock="<?= (int)$f['stock'] ?>"
        class="rounded-lg px-6 py-2.5 font-semibold text-white <?= $can ? 'bg-green-700 hover:bg-green-800' : 'cursor-not-allowed bg-gray-300' ?>">Reservasi</button>
      <?php if (!$can): ?><span class="ml-3 text-sm text-gray-500">Saat ini tidak dapat direservasi.</span><?php endif; ?>
    </div>
    <p class="mt-4 text-xs text-gray-500">Reservasi ditahan selama <?= (int)config('reservation_hold_minutes') ?> menit (atau sampai batas distribusi). Ambil makanan dan tunjukkan kode reservasi ke petugas.</p>
  </div>
</div>
<?php page_footer();
