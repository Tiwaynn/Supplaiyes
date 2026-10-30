<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    $fails = (int)($_SESSION['login_fail'] ?? 0);
    $last  = (int)($_SESSION['login_last'] ?? 0);
    if ($fails >= 5 && time() - $last < 60) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 1 menit.';
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify($pass, $row['password_hash'])) {
            login_user((int)$row['id']);
            redirect('index.php');
        }
        $_SESSION['login_fail'] = (time() - $last >= 60) ? 1 : $fails + 1;
        $_SESSION['login_last'] = time();
        $error = 'Email atau password salah.';
    }
}

page_header('Masuk', 'guest');
?>
<form method="post" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
  <h1 class="text-xl font-bold">Masuk</h1>
  <?php if ($error): ?><div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label class="block text-sm font-medium">Email
    <input type="email" name="email" value="<?= e($email) ?>" required autofocus class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
  </label>
  <label class="block text-sm font-medium">Password
    <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-green-600 focus:outline-none">
  </label>
  <button class="w-full rounded-lg bg-green-700 py-2.5 font-semibold text-white hover:bg-green-800">Masuk</button>
  <p class="text-center text-sm text-gray-500">Belum punya akun? <a class="font-semibold text-green-700" href="<?= e(url('register.php')) ?>">Daftar</a></p>
</form>
<?php page_footer();
