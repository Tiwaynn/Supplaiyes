<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
run_maintenance(db());

$rows = inventory_rows(db(), $_GET);
$archived = ($_GET['archived'] ?? '') === '1';
$inp = 'rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none';

page_header('Inventaris', 'admin', 'inventory');
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold text-green-800"><?= $archived ? 'Arsip makanan' : 'Inventaris makanan' ?></h1>
    <p class="text-sm text-gray-500">Tabel diperbarui otomatis setiap beberapa detik.</p>
  </div>
  <a href="<?= e(url('admin/food_form.php')) ?>" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">+ Tambah Makanan</a>
</div>

<form method="get" class="mb-4 flex flex-wrap gap-2">
  <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari nama / kategori / ID" class="<?= $inp ?>">
  <select name="status" class="<?= $inp ?>">
    <option value="">Semua status</option>
    <?php foreach (STATUS_LABELS as $k => [$l]): ?><option value="<?= e($k) ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> Tampilkan arsip</label>
  <button class="rounded-lg border border-green-700 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">Terapkan</button>
</form>

<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
  <table class="w-full text-left text-sm">
    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
      <tr>
        <th class="px-3 py-3">ID</th><th class="px-3 py-3">Nama</th><th class="px-3 py-3">Kategori</th><th class="px-3 py-3">Ukuran / Porsi</th>
        <th class="px-3 py-3">Harga</th><th class="px-3 py-3">Waktu produksi</th><th class="px-3 py-3">Stok</th>
        <th class="px-3 py-3">Batas distribusi</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Keamanan</th><th class="px-3 py-3">Aksi</th>
      </tr>
    </thead>
    <tbody id="inv-body" class="divide-y divide-gray-100"><?php require __DIR__ . '/partials/inventory_rows.php'; ?></tbody>
  </table>
</div>
<?php page_footer();
