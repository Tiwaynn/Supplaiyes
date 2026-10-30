<?php
require __DIR__ . '/../includes/bootstrap.php';

if (!current_user()) {
    json_out(['items' => [], 'error' => 'unauthorized'], 401);
}
run_maintenance(db());

$where = ['is_archived = 0', "safety_status = 'layak'", "status IN ('available','reserved','out_of_stock')", 'distribution_deadline > NOW()'];
$p = [];

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = 'name LIKE ?';
    $p[] = '%' . addcslashes($q, '%_\\') . '%';
}
$cat = (string)($_GET['category'] ?? '');
if ($cat !== '' && isset(CATEGORIES[$cat])) {
    $where[] = 'category = ?';
    $p[] = $cat;
}
$type = (string)($_GET['type'] ?? 'all');
if ($type === 'free') {
    $where[] = 'price = 0';
} elseif ($type === 'paid') {
    $where[] = 'price > 0';
}
if (($_GET['price_min'] ?? '') !== '' && ctype_digit((string)$_GET['price_min'])) {
    $where[] = 'price >= ?';
    $p[] = (int)$_GET['price_min'];
}
if (($_GET['price_max'] ?? '') !== '' && ctype_digit((string)$_GET['price_max'])) {
    $where[] = 'price <= ?';
    $p[] = (int)$_GET['price_max'];
}
$order = (($_GET['sort'] ?? '') === 'deadline') ? 'distribution_deadline ASC, id DESC' : 'created_at DESC, id DESC';

$sql = 'SELECT id, name, category, size_label, normal_price, price, stock, status, distribution_deadline,
               TIMESTAMPDIFF(SECOND, NOW(), distribution_deadline) AS secs
        FROM foods WHERE ' . implode(' AND ', $where) . " ORDER BY {$order} LIMIT 200";
$st = db()->prepare($sql);
$st->execute($p);

$items = [];
foreach ($st->fetchAll() as $r) {
    $secs = (int)$r['secs'];
    [$label, $cls] = STATUS_LABELS[$r['status']];
    $items[] = [
        'id'                 => (int)$r['id'],
        'name'               => $r['name'],
        'category'           => $r['category'],
        'icon'               => category_icon($r['category']),
        'size_label'         => $r['size_label'],
        'normal_price'       => $r['normal_price'] ? (int)$r['normal_price'] : null,
        'normal_price_label' => $r['normal_price'] ? rupiah($r['normal_price']) : '',
        'price'              => (int)$r['price'],
        'price_label'        => rupiah($r['price']),
        'stock'              => (int)$r['stock'],
        'status'             => $r['status'],
        'status_label'       => $label,
        'status_class'       => $cls,
        'deadline_label'     => dt($r['distribution_deadline']),
        'time_left'          => time_left($secs),
        'priority'           => $secs > 0 && $secs <= (int)config('priority_hours') * 3600 && $r['status'] === 'available',
    ];
}
json_out(['items' => $items, 'server_time' => date('c')]);
