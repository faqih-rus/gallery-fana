<?php
/**
 * config.php — Otak bersama untuk galeri.
 * Ubah pengaturan di bawah sesuai kebutuhan kalian berdua.
 */

declare(strict_types=1);

/* ===== Mode produksi: jangan bocorkan error ke pengunjung ===== */
error_reporting(E_ALL);
ini_set('display_errors', '0');   // set '1' saat ngoprek di lokal
ini_set('log_errors', '1');       // error tetap tercatat di log Apache

/* ===== PENGATURAN (ubah ini) ===== */
const ADMIN_PASSWORD  = '18122005';   // password halaman admin
const GALLERY_TITLE   = 'FAQIH & NAJWA';                  // nama / inisial kalian, mis. "A & V"
const GALLERY_TAGLINE = 'Memori indah kita berdua';   // deskripsi singkat, mis. "Foto-foto perjalanan kami"
const SINCE           = '';                       // mis. "2024" — kosongkan kalau tak perlu
const MAX_FILE_SIZE   = 12 * 1024 * 1024;         // 12 MB per gambar
const ALLOWED_EXT     = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

/* ===== PATH ===== */
define('BASE_DIR',   __DIR__);
define('UPLOAD_DIR', BASE_DIR . '/uploads');
define('DATA_FILE',  BASE_DIR . '/data/photos.json');

/* ===== Mulai session sedini mungkin (sebelum ada output apa pun) ===== */
session_start();

/* ===== Bikin folder & file otomatis, tanpa membocorkan warning ===== */
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0775, true);
}
$dataDir = dirname(DATA_FILE);
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}
if (is_dir($dataDir) && is_writable($dataDir)) {
    if (!file_exists(DATA_FILE)) {
        @file_put_contents(DATA_FILE, '[]');
    }
    // Kunci folder data dari akses web langsung (Apache).
    $htaccess = $dataDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
}

/* ===== Penyimpanan data (JSON) ===== */
function load_photos(): array
{
    $raw  = @file_get_contents(DATA_FILE);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function save_photos(array $photos): bool
{
    $ok = @file_put_contents(
        DATA_FILE,
        json_encode(array_values($photos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    return $ok !== false;
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

/* ===== Komentar (penyimpanan JSON, sederhana — TANPA database) =====
   Tambahan baru; tidak mengubah logika foto yang sudah ada. Disimpan di folder
   data/ yang sudah dikunci dari akses web langsung. */
define('COMMENTS_FILE', BASE_DIR . '/data/comments.json');
const MAX_COMMENT_LEN = 600;   // batas panjang isi komentar
const MAX_NAME_LEN    = 40;    // batas panjang nama

function load_comments(): array
{
    $raw  = @file_get_contents(COMMENTS_FILE);
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function save_comments(array $all): bool
{
    $ok = @file_put_contents(
        COMMENTS_FILE,
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    return $ok !== false;
}

/* Ambil daftar komentar satu foto (dari struktur { "<id_foto>": [ ... ] }). */
function comments_for(array $all, string $pid): array
{
    return isset($all[$pid]) && is_array($all[$pid]) ? $all[$pid] : [];
}
