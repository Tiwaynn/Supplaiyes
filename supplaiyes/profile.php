<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    if ($do === 'name') {
        $name = trim((string)($_POST['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors[] = 'Nama harus 2-80 karakter.';
        } else {
            db()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $u['id']]);
            flash('ok', 'Profil diperbarui.');
            redirect('profile.php');
        }
    } elseif ($do === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$u['id']]);
        if (!password_verify($cur, (string)$st->fetchColumn())) {
            $errors[] = 'Password saat ini salah.';
        } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors[] = 'Password baru minimal 8 karakter dan mengandung huruf serta angka.';
        } elseif ($new !== (string)($_POST['password2'] ?? '')) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('ok', 'Password berhasil diganti.');
            redirect('profile.php');
        }
    }
}

page_header('Profil', $u['role'] === 'admin' ? 'admin' : 'user', 'profile');
$inp = 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none';
?>
<h1 class="mb-4 text-2xl font-extrabold text-green-800">Profil</h1>
<?php foreach ($errors as $er): ?><div class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($er) ?></div><?php endforeach; ?>
<div class="grid gap-6 md:grid-cols-2">
  <form method="post" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <h2 class="font-bold">Data akun</h2>
    <?= csrf_field() ?><input type="hidden" name="do" value="name">
    <div class="text-sm"><span class="text-gray-500">Email:</span> <?= e($u['email']) ?></div>
    <label class="block text-sm font-medium">Nama<input name="name" value="<?= e($u['name']) ?>" required class="<?= $inp ?>"></label>
    <button class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Simpan</button>
  </form>
  <form method="post" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <h2 class="font-bold">Ganti password</h2>
    <?= csrf_field() ?><input type="hidden" name="do" value="password">
    <label class="block text-sm font-medium">Password saat ini<input type="password" name="current" required class="<?= $inp ?>"></label>
    <label class="block text-sm font-medium">Password baru<input type="password" name="password" required class="<?= $inp ?>"></label>
    <label class="block text-sm font-medium">Ulangi password baru<input type="password" name="password2" required class="<?= $inp ?>"></label>
    <button class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Ganti password</button>
  </form>
</div>
<?php page_footer();
