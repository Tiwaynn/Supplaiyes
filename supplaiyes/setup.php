<?php
/**
 * Instalasi awal SUPPLAIYES: membuat akun admin + data dummy.
 * Jalankan SEKALI di browser setelah import database.sql. Setelah sukses, file setup.lock dibuat
 * dan halaman ini menolak dijalankan lagi (hapus setup.php setelah selesai).
 */
require __DIR__ . '/includes/bootstrap.php';

$lock = __DIR__ . '/setup.lock';
$msg = '';
$ok = false;

if (file_exists($lock)) {
    http_response_code(403);
    exit('Setup sudah pernah dijalankan (setup.lock ada). Hapus file ini dan setup.lock bila ingin mengulang dari database kosong.');
}

$pdo = db();
try {
    $has = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
} catch (PDOException $e) {
    exit('Tabel belum ada. Import database.sql terlebih dahulu lewat phpMyAdmin.');
}

$adminEmail = 'admin@supplaiyes.test';
$adminPass  = 'Admin#12345';
$userEmail  = 'budi@supplaiyes.test';
$userPass   = 'User#12345';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($has > 0) {
        $msg = 'Akun admin sudah ada. Setup dibatalkan.';
    } else {
        try {
            tx($pdo, function () use ($pdo, $adminEmail, $adminPass, $userEmail, $userPass) {
                $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
                $ins->execute(['Admin SUPPLAIYES', $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'admin']);
                $adminId = (int)$pdo->lastInsertId();
                $ins->execute(['Budi Santoso', $userEmail, password_hash($userPass, PASSWORD_DEFAULT), 'user']);

                $h = fn(string $mod) => date('Y-m-d H:i:s', strtotime($mod));
                // [nama, kategori, deskripsi, ukuran, stok, harga normal, harga surplus, berat kg, produksi, deadline, keamanan]
                $foods = [
                    ['Nasi Ayam Bakar', 'Makanan Berat', 'Nasi putih, ayam bakar bumbu kecap, lalapan.', '1 kotak', 10, 25000, 8000, 0.45, '-3 hours', '+3 hours', 'layak'],
                    ['Roti Tawar Gandum', 'Roti & Kue', 'Roti tawar gandum utuh dari toko roti mitra.', '1 bungkus (10 lembar)', 15, 18000, 0, 0.30, '-6 hours', '+30 hours', 'layak'],
                    ['Soto Ayam', 'Makanan Berat', 'Soto ayam kuah bening dengan bihun dan telur.', '1 porsi', 8, 22000, 5000, 0.50, '-2 hours', '+2 hours', 'layak'],
                    ['Salad Buah Segar', 'Buah & Sayur', 'Potongan buah segar dengan yoghurt.', '250 gram', 12, 20000, 6000, 0.25, '-4 hours', '+8 hours', 'layak'],
                    ['Es Teh Manis Botol', 'Minuman', 'Teh manis dalam botol 350 ml.', '350 ml', 20, 8000, 0, 0.35, '-5 hours', '+48 hours', 'layak'],
                    ['Ayam Goreng Kremes', 'Lauk Pauk', 'Ayam goreng kremes, belum diperiksa petugas.', '2 potong', 6, 24000, 7000, 0.40, '-1 hours', '+4 hours', 'karantina'],
                    ['Donat Mix', 'Camilan', 'Aneka donat dari toko mitra.', '1 box (6 pcs)', 9, 30000, 10000, 0.36, '-8 hours', '+12 hours', 'layak'],
                    ['Gado-gado', 'Makanan Berat', 'Sayur rebus dengan bumbu kacang.', '1 porsi', 5, 18000, 4000, 0.40, '-1 hours', '+1 hours', 'layak'],
                ];
                $insF = $pdo->prepare("INSERT INTO foods (name, category, description, size_label, stock, normal_price, price, unit_weight_kg,
                    produced_at, distribution_deadline, safety_status, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,'available',?)");
                foreach ($foods as $f) {
                    $insF->execute([$f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6], $f[7], $h($f[8]), $h($f[9]), $f[10], $adminId]);
                    $id = (int)$pdo->lastInsertId();
                    sync_status($pdo, $id, $adminId, 'created', (int)$f[4], 'Data dummy awal', true);
                }
            });
            file_put_contents($lock, date('c'));
            $ok = true;
        } catch (Throwable $e) {
            error_log('setup: ' . $e->getMessage());
            $msg = 'Setup gagal: ' . $e->getMessage();
        }
    }
}

page_header('Setup', 'guest');
?>
<div class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
  <h1 class="text-xl font-bold">Setup SUPPLAIYES</h1>
  <?php if ($ok): ?>
    <div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">Setup selesai. Akun dan data dummy sudah dibuat.</div>
    <div class="text-sm">
      <p><b>Admin</b>: <?= e($adminEmail) ?> / <?= e($adminPass) ?></p>
      <p><b>User demo</b>: <?= e($userEmail) ?> / <?= e($userPass) ?></p>
      <p class="mt-2 text-red-700">Segera ganti password admin dan hapus file setup.php.</p>
    </div>
    <a href="<?= e(url('login.php')) ?>" class="block rounded-lg bg-green-700 py-2.5 text-center font-semibold text-white">Ke halaman login</a>
  <?php elseif ($has > 0): ?>
    <div class="rounded-lg bg-yellow-50 p-3 text-sm text-yellow-800">Akun admin sudah ada, setup tidak diperlukan. Hapus file setup.php.</div>
  <?php else: ?>
    <?php if ($msg): ?><div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><?= e($msg) ?></div><?php endif; ?>
    <p class="text-sm text-gray-600">Klik tombol untuk membuat akun admin, satu akun user demo, dan 8 data makanan contoh.</p>
    <form method="post"><?= csrf_field() ?><button class="w-full rounded-lg bg-green-700 py-2.5 font-semibold text-white hover:bg-green-800">Jalankan setup</button></form>
  <?php endif; ?>
</div>
<?php page_footer();
