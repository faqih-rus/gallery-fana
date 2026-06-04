<?php
/**
 * config.php — Otak bersama untuk galeri.
 * Ubah pengaturan di bawah sesuai kebutuhan kalian berdua.
 */

declare(strict_types=1);

/* ===== PENGATURAN (ubah ini) ===== */
const ADMIN_PASSWORD  = 'ganti-password-ini';   // password halaman admin
const GALLERY_TITLE   = 'Kita';                  // nama / inisial kalian, mis. "A & V"
const GALLERY_TAGLINE = 'catatan kecil tentang kita';
const SINCE           = '';                       // mis. "2024" — kosongkan kalau tak perlu
const MAX_FILE_SIZE   = 12 * 1024 * 1024;         // 12 MB per gambar
const ALLOWED_EXT     = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

/* ===== PATH ===== */
define('BASE_DIR',   __DIR__);
define('UPLOAD_DIR', BASE_DIR . '/uploads');
define('DATA_FILE',  BASE_DIR . '/data/photos.json');

/* ===== Bikin folder otomatis saat pertama jalan ===== */
foreach ([UPLOAD_DIR, dirname(DATA_FILE)] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, '[]');
}
// Kunci folder data dari akses web langsung (Apache).
$htaccess = dirname(DATA_FILE) . '/.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
}

session_start();

/* ===== Penyimpanan data (JSON) ===== */
function load_photos(): array
{
    $raw  = @file_get_contents(DATA_FILE);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function save_photos(array $photos): void
{
    file_put_contents(
        DATA_FILE,
        json_encode(array_values($photos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function find_photo(array $photos, string $id): ?int
{
    foreach ($photos as $i => $p) {
        if (($p['id'] ?? '') === $id) {
            return $i;
        }
    }
    return null;
}

/* ===== Keamanan ===== */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function is_logged_in(): bool
{
    return !empty($_SESSION['authed']);
}

/* ===== Flash message ===== */
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ===== Util tampilan ===== */
function pretty_date(string $iso): string
{
    if ($iso === '') {
        return '';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
