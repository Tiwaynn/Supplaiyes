<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
run_maintenance(db());
$pdo = db();

$tot = $pdo->query("SELECT COALESCE(SUM(distributed_qty),0) AS portions,
        COALESCE(SUM(distributed_qty * unit_weight_kg),0) AS kg,
        COALESCE(SUM(withdrawn_qty),0) AS withdrawn FROM foods")->fetch();

$incoming = $pdo->query("SELECT l.created_at, l.qty_change, f.id, f.name, f.size_label, u.name AS actor_name
    FROM food_status_logs l JOIN foods f ON f.id = l.food_id LEFT JOIN users u ON u.id = l.actor_id
    WHERE l.action = 'created' ORDER BY l.id DESC LIMIT 50")->fetchAll();

$distributed = $pdo->query("SELECT l.created_at, l.qty_change, l.action, l.note, f.id, f.name, u.name AS actor_name
    FROM food_status_logs l JOIN foods f ON f.id = l.food_id LEFT JOIN users u ON u.id = l.actor_id
    WHERE l.action IN ('distributed','pickup') ORDER BY l.id DESC LIMIT 50")->fetchAll();

$withdrawn = $pdo->query("SELECT l.created_at, l.qty_change, l.note, f.id, f.name, u.name AS actor_name
    FROM food_status_logs l JOIN foods f ON f.id = l.food_id LEFT JOIN users u ON u.id = l.actor_id
    WHERE l.action = 'withdrawn' ORDER BY l.id DESC LIMIT 50")->fetchAll();

$reservations = $pdo->query("SELECT r.code, r.qty, r.status, r.reserved_at, f.name AS food_name, u.name AS user_name
    FROM reservations r JOIN foods f ON f.id = r.food_id JOIN users u ON u.id = r.user_id
    ORDER BY r.id DESC LIMIT 50")->fetchAll();

function report_section(string $title, array $heads, array $rows, callable $cell): void
{
    echo '<section class="mb-8"><h2 class="mb-2 font-bold">' . e($title) . '</h2>';
    echo '<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>';
    foreach ($heads as $h) {
        echo '<th class="px-3 py-3">' . e($h) . '</th>';
    }
    echo '</tr></thead><tbody class="divide-y divide-gray-100">';
    if (!$rows) {
        echo '<tr><td colspan="' . count($heads) . '" class="px-4 py-6 text-center text-gray-500">Belum ada data.</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($cell($r) as $c) {
            echo '<td class="px-3 py-2">' . $c . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></section>';
}

page_header('Laporan', 'admin', 'reports');
?>
<h1 class="mb-1 text-2xl font-extrabold text-green-800">Laporan</h1>
<p class="mb-5 text-sm text-gray-500">Rekap makanan masuk, terdistribusi, ditarik, dan reservasi (50 data terbaru per bagian).</p>

<div class="mb-8 grid gap-4 sm:grid-cols-3">
  <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase text-gray-500">Porsi berhasil disalurkan</div><div class="mt-1 text-3xl font-extrabold text-blue-700"><?= (int)$tot['portions'] ?></div></div>
  <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase text-gray-500">Estimasi berat diselamatkan</div><div class="mt-1 text-3xl font-extrabold text-green-700"><?= e(number_format((float)$tot['kg'], 1, ',', '.')) ?> kg</div><div class="text-xs text-gray-400">porsi terdistribusi × berat per porsi</div></div>
  <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><div class="text-xs uppercase text-gray-500">Porsi ditarik</div><div class="mt-1 text-3xl font-extrabold text-red-700"><?= (int)$tot['withdrawn'] ?></div></div>
</div>

<?php
report_section('Riwayat makanan masuk', ['Waktu', 'Makanan', 'Ukuran', 'Jumlah masuk', 'Dicatat oleh'], $incoming,
    fn($r) => [e(dt($r['created_at'])), '#' . (int)$r['id'] . ' ' . e($r['name']), e($r['size_label']), (int)$r['qty_change'], e($r['actor_name'] ?? 'Sistem')]);

report_section('Riwayat makanan terdistribusi', ['Waktu', 'Makanan', 'Jenis', 'Porsi', 'Oleh', 'Catatan'], $distributed,
    fn($r) => [e(dt($r['created_at'])), '#' . (int)$r['id'] . ' ' . e($r['name']), $r['action'] === 'pickup' ? 'Pengambilan reservasi' : 'Ditandai terdistribusi', (int)$r['qty_change'], e($r['actor_name'] ?? 'Sistem'), e($r['note'])]);

report_section('Riwayat makanan ditarik', ['Waktu', 'Makanan', 'Porsi ditarik', 'Oleh', 'Catatan'], $withdrawn,
    fn($r) => [e(dt($r['created_at'])), '#' . (int)$r['id'] . ' ' . e($r['name']), abs((int)$r['qty_change']), e($r['actor_name'] ?? 'Sistem'), e($r['note'])]);

report_section('Riwayat reservasi', ['Waktu', 'Kode', 'Penerima', 'Makanan', 'Jumlah', 'Status'], $reservations,
    fn($r) => [e(dt($r['reserved_at'])), '<span class="font-mono">' . e($r['code']) . '</span>', e($r['user_name']), e($r['food_name']), (int)$r['qty'], badge(RES_LABELS, $r['status'])]);

page_footer();
