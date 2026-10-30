<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

$errors = [];
$name = $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors[] = 'Nama harus 2-80 karakter.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errors[] = 'Format email tidak valid.';
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) $errors[] = 'Password minimal 8 karakter dan mengandung huruf serta angka.';
    if ($pass !== $pass2) $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetchColumn()) {
            $errors[] = 'Email sudah terdaftar.';
        }
    }
    if (!$errors) {
        // role selalu 'user'; admin tidak bisa dibuat lewat registrasi publik
        db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')")
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        login_user((int)db()->lastInsertId());
        flash('ok', 'Akun berhasil dibuat. Selamat datang!');
        redirect('catalog.php');
    }
}

page_header('Daftar', 'guest');
?>
<form method="post" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
  <h1 class="text-xl font-bold">Daftar akun</h1>
  <?php foreach ($errors as $er): ?><div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($er) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <label class="block text-sm font-medium">Nama lengkap
    <input name="name" value="<?= e($name) ?>" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
  </label>
  <label class="block text-sm font-medium">Email
    <input type="email" name="email" value="<?= e($email) ?>" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
  </label>
  <label class="block text-sm font-medium">Password
    <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
    <span class="text-xs font-normal text-gray-500">Minimal 8 karakter, kombinasi huruf dan angka.</span>
  </label>
  <label class="block text-sm font-medium">Ulangi password
    <input type="password" name="password2" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
  </label>
  <button class="w-full rounded-lg bg-green-700 py-2.5 font-semibold text-white hover:bg-green-800">Daftar</button>
  <p class="text-center text-sm text-gray-500">Sudah punya akun? <a class="font-semibold text-green-700" href="<?= e(url('login.php')) ?>">Masuk</a></p>
</form>
<?php page_footer();
