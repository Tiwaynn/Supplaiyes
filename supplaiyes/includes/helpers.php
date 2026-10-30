<?php
declare(strict_types=1);

class UserError extends RuntimeException {}

const CATEGORIES = [
    'Makanan Berat' => '🍛',
    'Lauk Pauk'     => '🍗',
    'Roti & Kue'    => '🥐',
    'Buah & Sayur'  => '🥬',
    'Minuman'       => '🥤',
    'Camilan'       => '🍪',
    'Lainnya'       => '🍽️',
];

const STATUS_LABELS = [
    'available'    => ['Tersedia',      'bg-green-100 text-green-800'],
    'reserved'     => ['Direservasi',   'bg-amber-100 text-amber-800'],
    'distributed'  => ['Terdistribusi', 'bg-blue-100 text-blue-800'],
    'expired'      => ['Kedaluwarsa',   'bg-gray-200 text-gray-700'],
    'rejected'     => ['Ditarik',       'bg-red-100 text-red-800'],
    'out_of_stock' => ['Stok Habis',    'bg-slate-200 text-slate-700'],
];

const SAFETY_LABELS = [
    'layak'       => ['Layak Distribusi',            'bg-green-100 text-green-800'],
    'karantina'   => ['Karantina / Perlu Evaluasi',  'bg-yellow-100 text-yellow-800'],
    'tidak_layak' => ['Tidak Layak Konsumsi',        'bg-red-100 text-red-800'],
];

const RES_LABELS = [
    'reserved'  => ['Menunggu Diambil', 'bg-amber-100 text-amber-800'],
    'picked_up' => ['Sudah Diambil',    'bg-green-100 text-green-800'],
    'cancelled' => ['Dibatalkan',       'bg-gray-200 text-gray-700'],
    'expired'   => ['Kedaluwarsa',      'bg-red-100 text-red-800'],
];

function config(string $key)
{
    global $config;
    return $config[$key] ?? null;
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string)config('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

function json_out(array $data, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals(csrf_token(), $t);
}

function csrf_verify(): void
{
    if (!csrf_ok()) {
        http_response_code(419);
        exit('Sesi tidak valid atau kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/* ---------- Flash ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function get_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Auth ---------- */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $user = $st->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_role(string $role): array
{
    $u = require_login();
    if ($u['role'] !== $role) {
        http_response_code(403);
        exit('403 - Akses ditolak untuk role Anda.');
    }
    return $u;
}

function home_for(array $u): string
{
    return $u['role'] === 'admin' ? 'admin/index.php' : 'catalog.php';
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    unset($_SESSION['login_fail'], $_SESSION['login_last']);
}

/* ---------- Format ---------- */
function rupiah($n): string
{
    $n = (int)$n;
    return $n === 0 ? 'Gratis' : 'Rp ' . number_format($n, 0, ',', '.');
}

function dt(?string $s): string
{
    if (!$s) {
        return '-';
    }
    static $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($s);
    return date('d', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y H:i', $t);
}

function time_left(int $secs): string
{
    if ($secs <= 0) {
        return 'Berakhir';
    }
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    if ($h >= 24) {
        return intdiv($h, 24) . ' hari ' . ($h % 24) . ' jam';
    }
    return $h > 0 ? "{$h} jam {$m} menit" : "{$m} menit";
}

function badge(array $map, string $key): string
{
    [$label, $cls] = $map[$key] ?? [$key, 'bg-gray-100 text-gray-700'];
    return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . e($label) . '</span>';
}

function category_icon(string $cat): string
{
    return CATEGORIES[$cat] ?? '🍽️';
}

function parse_dt(string $v): ?string
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v) ?: DateTime::createFromFormat('Y-m-d H:i:s', $v);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}
