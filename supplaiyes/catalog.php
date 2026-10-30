<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
if ($u['role'] === 'admin') {
    redirect('admin/index.php');
}
run_maintenance(db());

page_header('Beranda', 'user', 'home');
?>
<div class="mb-4">
  <h1 class="text-2xl font-extrabold text-green-800">Makanan surplus tersedia</h1>
  <p class="text-sm text-gray-500">Makanan layak konsumsi dengan harga terjangkau. Stok diperbarui otomatis. <span id="last-update" class="text-gray-400"></span></p>
</div>

<form id="filters" class="mb-5 grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
  <input name="q" type="search" placeholder="Cari nama makanan…" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none lg:col-span-2">
  <select name="category" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <option value="">Semua kategori</option>
    <?php foreach (CATEGORIES as $c => $ic): ?><option value="<?= e($c) ?>"><?= e($ic . ' ' . $c) ?></option><?php endforeach; ?>
  </select>
  <select name="type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <option value="all">Gratis &amp; berbayar</option>
    <option value="free">Gratis saja</option>
    <option value="paid">Berbayar saja</option>
  </select>
  <div class="flex gap-2">
    <input name="price_min" type="number" min="0" placeholder="Harga min" class="w-1/2 rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <input name="price_max" type="number" min="0" placeholder="Harga maks" class="w-1/2 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  </div>
  <select name="sort" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <option value="newest">Terbaru</option>
    <option value="deadline">Batas distribusi terdekat</option>
  </select>
</form>

<div id="catalog" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
  <div class="col-span-full p-10 text-center text-gray-400">Memuat…</div>
</div>
<?php page_footer();
