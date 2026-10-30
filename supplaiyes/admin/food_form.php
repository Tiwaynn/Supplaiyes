<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role('admin');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$food = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM foods WHERE id = ?');
    $st->execute([$id]);
    $food = $st->fetch();
    if (!$food) {
        http_response_code(404);
        exit('Data makanan tidak ditemukan.');
    }
}
$isEdit = (bool)$food;

$defaults = [
    'name' => '', 'category' => 'Makanan Berat', 'description' => '', 'size_label' => '1 porsi', 'stock' => 10,
    'normal_price' => '', 'price' => 0, 'unit_weight_kg' => '0.30',
    'produced_at' => date('Y-m-d\TH:i'), 'distribution_deadline' => date('Y-m-d\TH:i', strtotime('+6 hours')),
    'safety_status' => 'karantina',
];
$v = $defaults;
if ($isEdit) {
    foreach ($defaults as $k => $_) {
        $v[$k] = $food[$k];
    }
    $v['produced_at'] = date('Y-m-d\TH:i', strtotime($food['produced_at']));
    $v['distribution_deadline'] = date('Y-m-d\TH:i', strtotime($food['distribution_deadline']));
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($defaults) as $k) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $allowedSafety = $isEdit ? ['layak', 'karantina', 'tidak_layak'] : ['layak', 'karantina'];

    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 120) $errors[] = 'Nama makanan harus 2-120 karakter.';
    if (!isset(CATEGORIES[$v['category']])) $errors[] = 'Kategori tidak valid.';
    if (mb_strlen($v['description']) > 1000) $errors[] = 'Deskripsi maksimal 1000 karakter.';
    if ($v['size_label'] === '' || mb_strlen($v['size_label']) > 50) $errors[] = 'Ukuran/porsi wajib diisi (maks. 50 karakter).';
    if (!ctype_digit($v['stock']) || (int)$v['stock'] > 100000) $errors[] = 'Jumlah stok harus bilangan bulat 0-100000.';
    if (!ctype_digit($v['price']) || (int)$v['price'] > 100000000) $errors[] = 'Harga surplus harus bilangan bulat >= 0 (0 = gratis).';
    if ($v['normal_price'] !== '' && !ctype_digit($v['normal_price'])) $errors[] = 'Harga normal harus bilangan bulat atau dikosongkan.';
    if (!is_numeric($v['unit_weight_kg']) || (float)$v['unit_weight_kg'] < 0 || (float)$v['unit_weight_kg'] > 100) $errors[] = 'Berat per porsi harus 0-100 kg.';
    $produced = parse_dt($v['produced_at']);
    $deadline = parse_dt($v['distribution_deadline']);
    if (!$produced) $errors[] = 'Waktu produksi/diterima tidak valid.';
    if (!$deadline) $errors[] = 'Batas distribusi tidak valid.';
    if ($produced && $deadline && strtotime($deadline) <= strtotime($produced)) $errors[] = 'Batas distribusi harus setelah waktu produksi.';
    if (!in_array($v['safety_status'], $allowedSafety, true)) $errors[] = 'Status keamanan pangan tidak valid.';

    if (!$errors) {
        try {
            $adminId = (int)$admin['id'];
            $normal = $v['normal_price'] === '' ? null : (int)$v['normal_price'];
            if ($isEdit) {
                tx($pdo, function () use ($pdo, $id, $v, $normal, $produced, $deadline, $adminId) {
                    $old = lock_food($pdo, $id);
                    $oldSafety = $old['safety_status'];
                    $newSafety = $v['safety_status'];
                    $pdo->prepare('UPDATE foods SET name=?, category=?, description=?, size_label=?, stock=?, normal_price=?, price=?,
                                   unit_weight_kg=?, produced_at=?, distribution_deadline=?, safety_status=? WHERE id=?')
                        ->execute([$v['name'], $v['category'], $v['description'] ?: null, $v['size_label'], (int)$v['stock'], $normal,
                                   (int)$v['price'], (float)$v['unit_weight_kg'], $produced, $deadline, $oldSafety === 'tidak_layak' ? 'tidak_layak' : $newSafety, $id]);
                    if ($newSafety === 'tidak_layak' && $oldSafety !== 'tidak_layak') {
                        withdraw_inner($pdo, $id, $adminId, 'Ditarik melalui form edit (tidak layak konsumsi)');
                    } else {
                        if ($oldSafety === 'tidak_layak' && $newSafety !== 'tidak_layak') {
                            $pdo->prepare('UPDATE foods SET safety_status = ? WHERE id = ?')->execute([$newSafety, $id]);
                        }
                        $delta = (int)$v['stock'] - (int)$old['stock'];
                        $note = 'Data diperbarui' . ($oldSafety !== $newSafety ? " (keamanan: {$oldSafety} → {$newSafety})" : '');
                        sync_status($pdo, $id, $adminId, 'updated', $delta, $note, true, $oldSafety);
                    }
                });
                flash('ok', 'Data makanan diperbarui.');
            } else {
                tx($pdo, function () use ($pdo, $v, $normal, $produced, $deadline, $adminId) {
                    $pdo->prepare("INSERT INTO foods (name, category, description, size_label, stock, normal_price, price, unit_weight_kg,
                                   produced_at, distribution_deadline, safety_status, status, created_by)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,'available',?)")
                        ->execute([$v['name'], $v['category'], $v['description'] ?: null, $v['size_label'], (int)$v['stock'], $normal,
                                   (int)$v['price'], (float)$v['unit_weight_kg'], $produced, $deadline, $v['safety_status'], $adminId]);
                    $newId = (int)$pdo->lastInsertId();
                    sync_status($pdo, $newId, $adminId, 'created', (int)$v['stock'], 'Makanan baru ditambahkan', true);
                });
                flash('ok', 'Makanan baru ditambahkan.');
            }
            redirect('admin/foods.php');
        } catch (UserError $e) {
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('food_form: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan ke database.';
        }
    }
}

page_header($isEdit ? 'Edit Makanan' : 'Tambah Makanan', 'admin', $isEdit ? 'inventory' : 'add');
$inp = 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none';
?>
<h1 class="mb-1 text-2xl font-extrabold text-green-800"><?= $isEdit ? 'Edit makanan #' . $id : 'Tambah makanan' ?></h1>
<p class="mb-5 text-sm text-gray-500">Hanya makanan berstatus <b>Layak Distribusi</b> yang tampil dan bisa direservasi oleh user.</p>

<?php foreach ($errors as $er): ?><div class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" class="max-w-3xl space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
  <?= csrf_field() ?>
  <div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm font-medium sm:col-span-2">Nama makanan
      <input name="name" value="<?= e($v['name']) ?>" required maxlength="120" class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium">Kategori
      <select name="category" class="<?= $inp ?>">
        <?php foreach (CATEGORIES as $c => $ic): ?><option value="<?= e($c) ?>" <?= $v['category'] === $c ? 'selected' : '' ?>><?= e($ic . ' ' . $c) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="block text-sm font-medium">Ukuran / porsi
      <input name="size_label" value="<?= e($v['size_label']) ?>" required maxlength="50" placeholder="mis. 1 kotak, 250 gram" class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium sm:col-span-2">Deskripsi
      <textarea name="description" rows="3" maxlength="1000" class="<?= $inp ?>"><?= e($v['description']) ?></textarea>
    </label>
    <label class="block text-sm font-medium">Jumlah stok (porsi)
      <input type="number" name="stock" min="0" value="<?= e($v['stock']) ?>" required class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium">Berat per porsi (kg)
      <input type="number" step="0.01" min="0" name="unit_weight_kg" value="<?= e($v['unit_weight_kg']) ?>" class="<?= $inp ?>">
      <span class="text-xs font-normal text-gray-500">Untuk estimasi berat makanan yang diselamatkan di laporan.</span>
    </label>
    <label class="block text-sm font-medium">Harga normal (Rp, opsional)
      <input type="number" name="normal_price" min="0" value="<?= e($v['normal_price']) ?>" class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium">Harga surplus (Rp, 0 = gratis)
      <input type="number" name="price" min="0" value="<?= e($v['price']) ?>" required class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium">Waktu produksi / diterima
      <input type="datetime-local" name="produced_at" value="<?= e($v['produced_at']) ?>" required class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium">Batas distribusi
      <input type="datetime-local" name="distribution_deadline" value="<?= e($v['distribution_deadline']) ?>" required class="<?= $inp ?>">
    </label>
    <label class="block text-sm font-medium sm:col-span-2">Status keamanan pangan
      <select name="safety_status" class="<?= $inp ?>">
        <?php foreach (SAFETY_LABELS as $k => [$l]): if (!$isEdit && $k === 'tidak_layak') continue; ?>
          <option value="<?= e($k) ?>" <?= $v['safety_status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="text-xs font-normal text-gray-500">Tetapkan berdasarkan jenis makanan, riwayat penyimpanan, dan hasil pemeriksaan petugas, bukan sekadar tampilan atau bau. Memilih "Tidak Layak Konsumsi" saat edit akan menarik makanan dan membatalkan reservasi aktif.</span>
    </label>
  </div>
  <div class="flex gap-2 pt-2">
    <button class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Simpan</button>
    <a href="<?= e(url('admin/foods.php')) ?>" class="rounded-lg px-5 py-2.5 text-sm text-gray-600 hover:bg-gray-100">Batal</a>
  </div>
</form>
<?php page_footer();
