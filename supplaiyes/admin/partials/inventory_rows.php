<?php
// Dipakai oleh admin/foods.php dan api/admin_inventory.php. Variabel: $rows
$archivedView = ($_GET['archived'] ?? '') === '1';
if (!$rows) {
    echo '<tr><td colspan="11" class="px-4 py-8 text-center text-gray-500">Belum ada data makanan.</td></tr>';
    return;
}
$btn = 'rounded-lg border px-2.5 py-1 text-xs font-semibold whitespace-nowrap';
foreach ($rows as $r):
    $id = (int)$r['id'];
    $isArchived = (int)$r['is_archived'] === 1;
    ?>
<tr class="align-top hover:bg-gray-50">
  <td class="px-3 py-3 font-mono text-gray-500">#<?= $id ?></td>
  <td class="px-3 py-3 font-semibold"><?= e($r['name']) ?></td>
  <td class="px-3 py-3"><?= e(category_icon($r['category']) . ' ' . $r['category']) ?></td>
  <td class="px-3 py-3 whitespace-nowrap"><?= e($r['size_label']) ?></td>
  <td class="px-3 py-3 whitespace-nowrap"><?= e(rupiah($r['price'])) ?></td>
  <td class="px-3 py-3 whitespace-nowrap"><?= e(dt($r['produced_at'])) ?></td>
  <td class="px-3 py-3 whitespace-nowrap"><?= (int)$r['stock'] ?><?php if ((int)$r['reserved_qty'] > 0): ?> <span class="text-xs text-amber-700">(+<?= (int)$r['reserved_qty'] ?> dipesan)</span><?php endif; ?></td>
  <td class="px-3 py-3 whitespace-nowrap"><?= e(dt($r['distribution_deadline'])) ?></td>
  <td class="px-3 py-3"><?= badge(STATUS_LABELS, $r['status']) ?></td>
  <td class="px-3 py-3"><?= badge(SAFETY_LABELS, $r['safety_status']) ?></td>
  <td class="px-3 py-3">
    <div class="flex flex-wrap gap-1.5">
    <?php if ($isArchived): ?>
      <form method="post" action="<?= e(url('admin/food_action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="restore"><button class="<?= $btn ?> border-green-600 text-green-700 hover:bg-green-50">Pulihkan</button></form>
    <?php else: ?>
      <a href="<?= e(url('admin/food_form.php?id=' . $id)) ?>" class="<?= $btn ?> border-gray-300 text-gray-700 hover:bg-gray-100">Edit</a>
      <?php if ($r['safety_status'] === 'layak' && (int)$r['stock'] > 0): ?>
      <form method="post" action="<?= e(url('admin/food_action.php')) ?>" data-confirm="Tandai <?= (int)$r['stock'] ?> porsi tersisa sebagai terdistribusi?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="distribute"><button class="<?= $btn ?> border-blue-500 text-blue-700 hover:bg-blue-50">Tandai Terdistribusi</button></form>
      <?php endif; ?>
      <?php if ($r['safety_status'] !== 'tidak_layak'): ?>
      <form method="post" action="<?= e(url('admin/food_action.php')) ?>" data-confirm="Tarik makanan ini dari distribusi? Reservasi aktif akan dibatalkan."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="withdraw"><button class="<?= $btn ?> border-red-500 text-red-700 hover:bg-red-50">Tarik dari Distribusi</button></form>
      <?php endif; ?>
      <form method="post" action="<?= e(url('admin/food_action.php')) ?>" data-confirm="Arsipkan data makanan ini? Riwayat tetap tersimpan."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="archive"><button class="<?= $btn ?> border-gray-300 text-gray-600 hover:bg-gray-100">Arsipkan</button></form>
    <?php endif; ?>
    </div>
  </td>
</tr>
<?php endforeach;
