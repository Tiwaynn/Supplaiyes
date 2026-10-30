<?php
declare(strict_types=1);

/**
 * Logika inventaris & reservasi. Semua perubahan stok/status lewat fungsi di sini
 * (di dalam transaksi + SELECT ... FOR UPDATE) supaya tidak ada pemesanan ganda.
 */

function tx(PDO $pdo, callable $fn)
{
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Log ---------- */
function log_food(PDO $pdo, int $foodId, ?int $actor, string $action, ?string $oldS, ?string $newS,
                  ?string $oldSaf, ?string $newSaf, int $qty, string $note = ''): void
{
    $pdo->prepare('INSERT INTO food_status_logs
        (food_id, actor_id, action, old_status, new_status, old_safety, new_safety, qty_change, note)
        VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$foodId, $actor, $action, $oldS, $newS, $oldSaf, $newSaf, $qty, $note ?: null]);
}

function log_reservation(PDO $pdo, int $rid, ?int $actor, ?string $old, string $new, string $note = ''): void
{
    $pdo->prepare('INSERT INTO reservation_logs (reservation_id, actor_id, old_status, new_status, note) VALUES (?,?,?,?,?)')
        ->execute([$rid, $actor, $old, $new, $note ?: null]);
}

/* ---------- Status ---------- */
function lock_food(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT f.*, (f.distribution_deadline <= NOW()) AS past FROM foods f WHERE f.id = ? FOR UPDATE');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function active_reserved_qty(PDO $pdo, int $foodId): int
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM reservations WHERE food_id = ? AND status = 'reserved'");
    $st->execute([$foodId]);
    return (int)$st->fetchColumn();
}

function compute_status(array $f, int $activeQty): string
{
    if ($f['safety_status'] === 'tidak_layak') {
        return 'rejected';
    }
    if ((int)$f['stock'] <= 0 && $activeQty <= 0) {
        return (int)$f['distributed_qty'] > 0 ? 'distributed' : 'out_of_stock';
    }
    if ((int)$f['past'] === 1) {
        return 'expired';
    }
    return (int)$f['stock'] > 0 ? 'available' : 'reserved';
}

/** Hitung ulang status inventaris; catat log bila berubah atau $force = true. Harus dipanggil di dalam transaksi. */
function sync_status(PDO $pdo, int $id, ?int $actor, string $action, int $qty, string $note,
                     bool $force = false, ?string $oldSafety = null): ?string
{
    $f = lock_food($pdo, $id);
    if (!$f) {
        return null;
    }
    $new = compute_status($f, active_reserved_qty($pdo, $id));
    $changed = $new !== $f['status'];
    if ($changed) {
        $pdo->prepare('UPDATE foods SET status = ? WHERE id = ?')->execute([$new, $id]);
    }
    if ($changed || $force) {
        log_food($pdo, $id, $actor, $action, $f['status'], $new, $oldSafety ?? $f['safety_status'], $f['safety_status'], $qty, $note);
    }
    return $new;
}

/* ---------- Reservasi ---------- */
function reserve_food(PDO $pdo, int $userId, int $foodId, int $qty): array
{
    $max = (int)config('max_qty');
    if ($qty < 1 || $qty > $max) {
        throw new UserError("Jumlah reservasi harus 1 sampai {$max} porsi.");
    }
    return tx($pdo, function () use ($pdo, $userId, $foodId, $qty) {
        $f = lock_food($pdo, $foodId);
        if (!$f || (int)$f['is_archived'] === 1) {
            throw new UserError('Makanan tidak ditemukan.');
        }
        if ($f['safety_status'] !== 'layak') {
            throw new UserError('Makanan ini tidak dapat direservasi.');
        }
        if ((int)$f['past'] === 1) {
            throw new UserError('Batas waktu distribusi sudah lewat.');
        }
        if ((int)$f['stock'] < $qty) {
            $left = (int)$f['stock'];
            throw new UserError($left > 0 ? "Stok tidak mencukupi, tersisa {$left} porsi." : 'Stok sudah habis.');
        }

        $pdo->prepare('UPDATE foods SET stock = stock - ? WHERE id = ? AND stock >= ?')->execute([$qty, $foodId, $qty]);

        do {
            $code = 'SPL-' . strtoupper(bin2hex(random_bytes(3)));
            $chk = $pdo->prepare('SELECT 1 FROM reservations WHERE code = ?');
            $chk->execute([$code]);
        } while ($chk->fetchColumn());

        $pdo->prepare('INSERT INTO reservations (code, user_id, food_id, qty, status, expires_at)
                       VALUES (?, ?, ?, ?, \'reserved\', LEAST(DATE_ADD(NOW(), INTERVAL ? MINUTE), ?))')
            ->execute([$code, $userId, $foodId, $qty, (int)config('reservation_hold_minutes'), $f['distribution_deadline']]);
        $rid = (int)$pdo->lastInsertId();

        log_reservation($pdo, $rid, $userId, null, 'reserved', "Reservasi {$qty} porsi");
        sync_status($pdo, $foodId, $userId, 'reserved', -$qty, "Reservasi {$code}", true);

        return ['id' => $rid, 'code' => $code];
    });
}

/** Batalkan / kedaluwarsakan reservasi. $userId != null artinya pemilik yang membatalkan. */
function cancel_reservation(PDO $pdo, int $rid, ?int $userId, ?int $actor, string $note, string $newStatus = 'cancelled'): void
{
    tx($pdo, function () use ($pdo, $rid, $userId, $actor, $note, $newStatus) {
        $st = $pdo->prepare('SELECT * FROM reservations WHERE id = ? FOR UPDATE');
        $st->execute([$rid]);
        $r = $st->fetch();
        if (!$r || $r['status'] !== 'reserved') {
            throw new UserError('Reservasi tidak dapat dibatalkan (sudah diproses).');
        }
        if ($userId !== null && (int)$r['user_id'] !== $userId) {
            throw new UserError('Reservasi ini bukan milik Anda.');
        }
        if ($userId !== null && strtotime($r['expires_at']) <= time()) {
            throw new UserError('Batas waktu reservasi sudah lewat.');
        }

        $f = lock_food($pdo, (int)$r['food_id']);
        $restock = 0;
        if ($f && $f['safety_status'] === 'layak' && (int)$f['past'] === 0) {
            $pdo->prepare('UPDATE foods SET stock = stock + ? WHERE id = ?')->execute([(int)$r['qty'], (int)$r['food_id']]);
            $restock = (int)$r['qty'];
        }
        $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?')->execute([$newStatus, $rid]);
        log_reservation($pdo, $rid, $actor, 'reserved', $newStatus, $note);
        sync_status($pdo, (int)$r['food_id'], $actor, $newStatus === 'expired' ? 'expired' : 'restock', $restock,
            $note . ($restock ? '' : ' (stok tidak dikembalikan)'), true);
    });
}

function confirm_pickup(PDO $pdo, int $rid, int $adminId): void
{
    tx($pdo, function () use ($pdo, $rid, $adminId) {
        $st = $pdo->prepare('SELECT * FROM reservations WHERE id = ? FOR UPDATE');
        $st->execute([$rid]);
        $r = $st->fetch();
        if (!$r || $r['status'] !== 'reserved') {
            throw new UserError('Reservasi tidak dalam status menunggu diambil.');
        }
        lock_food($pdo, (int)$r['food_id']);
        $pdo->prepare('UPDATE reservations SET status = \'picked_up\' WHERE id = ?')->execute([$rid]);
        $pdo->prepare('UPDATE foods SET distributed_qty = distributed_qty + ? WHERE id = ?')->execute([(int)$r['qty'], (int)$r['food_id']]);
        log_reservation($pdo, $rid, $adminId, 'reserved', 'picked_up', 'Pengambilan dikonfirmasi admin');
        sync_status($pdo, (int)$r['food_id'], $adminId, 'pickup', (int)$r['qty'], 'Pengambilan ' . $r['code'], true);
    });
}

/* ---------- Aksi admin ---------- */
/** Tanpa transaksi sendiri; panggil di dalam tx(). */
function withdraw_inner(PDO $pdo, int $id, int $actor, string $note): void
{
    $f = lock_food($pdo, $id);
    if (!$f) {
        throw new UserError('Data makanan tidak ditemukan.');
    }
    if ($f['safety_status'] === 'tidak_layak') {
        throw new UserError('Makanan ini sudah ditarik dari distribusi.');
    }
    $qty = (int)$f['stock'];
    $st = $pdo->prepare("SELECT id, qty FROM reservations WHERE food_id = ? AND status = 'reserved' FOR UPDATE");
    $st->execute([$id]);
    foreach ($st->fetchAll() as $r) {
        $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?")->execute([(int)$r['id']]);
        log_reservation($pdo, (int)$r['id'], $actor, 'reserved', 'cancelled', 'Dibatalkan: makanan ditarik dari distribusi');
        $qty += (int)$r['qty'];
    }
    $pdo->prepare("UPDATE foods SET safety_status = 'tidak_layak', status = 'rejected', withdrawn_qty = withdrawn_qty + ?, stock = 0 WHERE id = ?")
        ->execute([$qty, $id]);
    log_food($pdo, $id, $actor, 'withdrawn', $f['status'], 'rejected', $f['safety_status'], 'tidak_layak', -$qty, $note);
}

function withdraw_food(PDO $pdo, int $id, int $actor, string $note = 'Ditarik dari distribusi'): void
{
    tx($pdo, fn() => withdraw_inner($pdo, $id, $actor, $note));
}

function distribute_food(PDO $pdo, int $id, int $actor): void
{
    tx($pdo, function () use ($pdo, $id, $actor) {
        $f = lock_food($pdo, $id);
        if (!$f) {
            throw new UserError('Data makanan tidak ditemukan.');
        }
        if ($f['safety_status'] !== 'layak') {
            throw new UserError('Hanya makanan berstatus Layak Distribusi yang bisa ditandai terdistribusi.');
        }
        $qty = (int)$f['stock'];
        if ($qty <= 0) {
            throw new UserError('Tidak ada stok tersedia untuk ditandai terdistribusi.');
        }
        $pdo->prepare('UPDATE foods SET distributed_qty = distributed_qty + ?, stock = 0 WHERE id = ?')->execute([$qty, $id]);
        sync_status($pdo, $id, $actor, 'distributed', $qty, "Ditandai terdistribusi ({$qty} porsi)", true);
    });
}

function archive_food(PDO $pdo, int $id, int $actor, bool $archive): void
{
    tx($pdo, function () use ($pdo, $id, $actor, $archive) {
        $f = lock_food($pdo, $id);
        if (!$f) {
            throw new UserError('Data makanan tidak ditemukan.');
        }
        if ($archive && active_reserved_qty($pdo, $id) > 0) {
            throw new UserError('Tidak bisa diarsipkan: masih ada reservasi aktif.');
        }
        $pdo->prepare('UPDATE foods SET is_archived = ? WHERE id = ?')->execute([$archive ? 1 : 0, $id]);
        log_food($pdo, $id, $actor, $archive ? 'archived' : 'restored', $f['status'], $f['status'],
            $f['safety_status'], $f['safety_status'], 0, $archive ? 'Diarsipkan (soft delete)' : 'Dipulihkan dari arsip');
    });
}

/* ---------- Pemeliharaan otomatis ---------- */
function run_maintenance(PDO $pdo): void
{
    try {
        $ids = $pdo->query("SELECT id FROM reservations WHERE status = 'reserved' AND expires_at < NOW()")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $rid) {
            try {
                cancel_reservation($pdo, (int)$rid, null, null, 'Reservasi melewati batas waktu pengambilan', 'expired');
            } catch (UserError $e) {
                // sudah diproses proses lain
            }
        }
        $ids = $pdo->query("SELECT id FROM foods WHERE is_archived = 0 AND status IN ('available','reserved') AND distribution_deadline <= NOW()")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $fid) {
            tx($pdo, fn() => sync_status($pdo, (int)$fid, null, 'expired', 0, 'Batas distribusi tercapai'));
        }
    } catch (Throwable $e) {
        error_log('SUPPLAIYES maintenance: ' . $e->getMessage());
    }
}

/* ---------- Query admin ---------- */
function admin_stats(PDO $pdo): array
{
    $row = $pdo->query("SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(status='available' AND safety_status='layak'),0) AS available_items,
            COALESCE(SUM(CASE WHEN status='available' AND safety_status='layak' THEN stock ELSE 0 END),0) AS available_portions,
            COALESCE(SUM(distributed_qty),0) AS distributed,
            COALESCE(SUM(withdrawn_qty),0) AS withdrawn
        FROM foods WHERE is_archived = 0")->fetch();
    $row['reserved'] = (int)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM reservations WHERE status='reserved'")->fetchColumn();
    return array_map('intval', $row);
}

function inventory_rows(PDO $pdo, array $g): array
{
    $w = [];
    $p = [];
    $w[] = 'f.is_archived = ' . (($g['archived'] ?? '') === '1' ? 1 : 0);
    $q = trim((string)($g['q'] ?? ''));
    if ($q !== '') {
        $w[] = '(f.name LIKE ? OR f.category LIKE ? OR f.id = ?)';
        $p[] = "%{$q}%";
        $p[] = "%{$q}%";
        $p[] = ctype_digit($q) ? (int)$q : 0;
    }
    $s = (string)($g['status'] ?? '');
    if (isset(STATUS_LABELS[$s])) {
        $w[] = 'f.status = ?';
        $p[] = $s;
    }
    $sql = "SELECT f.*, (SELECT COALESCE(SUM(r.qty),0) FROM reservations r WHERE r.food_id = f.id AND r.status = 'reserved') AS reserved_qty
            FROM foods f WHERE " . implode(' AND ', $w) . ' ORDER BY f.id DESC LIMIT 300';
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
