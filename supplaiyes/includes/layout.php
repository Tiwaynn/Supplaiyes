<?php
declare(strict_types=1);

function page_header(string $title, string $area = 'user', string $active = ''): void
{
    $GLOBALS['spl_area'] = $area;
    $u = current_user();
    $flash = get_flash();
    $adminNav = [
        'dashboard' => ['Dashboard', 'admin/index.php'],
        'inventory' => ['Inventaris', 'admin/foods.php'],
        'add'       => ['Tambah Makanan', 'admin/food_form.php'],
        'reserv'    => ['Reservasi', 'admin/reservations.php'],
        'logs'      => ['Riwayat', 'admin/logs.php'],
        'reports'   => ['Laporan', 'admin/reports.php'],
    ];
    $userNav = [
        'home'    => ['Beranda', 'catalog.php'],
        'reserv'  => ['Reservasi Saya', 'reservations.php'],
        'profile' => ['Profil', 'profile.php'],
    ];
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · SUPPLAIYES</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">
<?php if ($area === 'admin'): ?>
<div class="md:flex min-h-screen">
  <aside class="md:w-60 shrink-0 bg-green-800 text-green-50 md:min-h-screen">
    <div class="px-5 py-4 border-b border-green-700">
      <div class="text-xl font-extrabold tracking-wide">SUPPLAIYES</div>
      <div class="text-xs text-green-200">Surplus food management · Admin</div>
    </div>
    <nav class="flex md:flex-col gap-1 overflow-x-auto p-2 text-sm">
      <?php foreach ($adminNav as $k => [$label, $href]): ?>
        <a href="<?= e(url($href)) ?>" class="whitespace-nowrap rounded-lg px-3 py-2 <?= $k === $active ? 'bg-white text-green-800 font-semibold' : 'hover:bg-green-700' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="md:mt-6">
        <?= csrf_field() ?>
        <button class="whitespace-nowrap rounded-lg px-3 py-2 text-left hover:bg-green-700 w-full">Keluar<?= $u ? ' (' . e($u['name']) . ')' : '' ?></button>
      </form>
    </nav>
  </aside>
  <main class="flex-1 min-w-0 p-4 md:p-8">
<?php elseif ($area === 'user'): ?>
<header class="bg-green-700 text-white shadow">
  <div class="mx-auto max-w-6xl px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-2">
    <a href="<?= e(url('catalog.php')) ?>" class="text-xl font-extrabold tracking-wide">SUPPLAIYES</a>
    <nav class="flex gap-1 text-sm flex-1">
      <?php foreach ($userNav as $k => [$label, $href]): ?>
        <a href="<?= e(url($href)) ?>" class="rounded-lg px-3 py-1.5 <?= $k === $active ? 'bg-white text-green-800 font-semibold' : 'hover:bg-green-600' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= e(url('logout.php')) ?>" class="text-sm">
      <?= csrf_field() ?>
      <span class="mr-2 text-green-100 hidden sm:inline"><?= e($u['name'] ?? '') ?></span>
      <button class="rounded-lg bg-green-900/40 px-3 py-1.5 hover:bg-green-900/70">Keluar</button>
    </form>
  </div>
</header>
<main class="mx-auto max-w-6xl p-4 md:p-6">
<?php else: ?>
<main class="mx-auto flex min-h-screen max-w-md flex-col justify-center p-4">
  <div class="mb-6 text-center">
    <div class="text-3xl font-extrabold tracking-wide text-green-700">SUPPLAIYES</div>
    <div class="text-sm text-gray-500">Selamatkan makanan, bantu sesama</div>
  </div>
<?php endif; ?>
<?php foreach ($flash as [$type, $msg]): ?>
  <div class="mb-4 rounded-lg border px-4 py-3 text-sm <?= $type === 'ok' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?>"><?= e($msg) ?></div>
<?php endforeach; ?>
<?php
}

function page_footer(): void
{
    $area = $GLOBALS['spl_area'] ?? 'user';
    echo $area === 'admin' ? '</main></div>' : '</main>';
    ?>
<div id="toasts" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<div id="qty-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
    <h3 class="text-lg font-bold text-green-800">Reservasi makanan</h3>
    <p id="qty-food" class="mt-1 text-sm text-gray-600"></p>
    <label class="mt-4 block text-sm font-medium">Jumlah porsi
      <input id="qty-input" type="number" min="1" value="1" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
    </label>
    <p id="qty-hint" class="mt-1 text-xs text-gray-500"></p>
    <div class="mt-5 flex justify-end gap-2">
      <button id="qty-cancel" type="button" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Batal</button>
      <button id="qty-ok" type="button" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Reservasi</button>
    </div>
  </div>
</div>

<script>
window.SPL = {
  base: <?= json_encode(rtrim((string)config('base_url'), '/') . '/') ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  poll: <?= (int)config('poll_interval_ms') ?>,
  maxQty: <?= (int)config('max_qty') ?>
};
</script>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}
