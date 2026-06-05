<?php
/**
 * thumb.php — Pelayan gambar pintar.
 *
 * Membuat versi kecil (thumbnail) dari foto di /uploads lalu menyimpannya di
 * cache, supaya galeri ringan & cepat dibuka — terutama di HP. Foto asli yang
 * berukuran besar tidak pernah dikirim utuh ke browser kecuali memang perlu.
 *
 *   thumb.php?f=abc.jpg&s=640   -> kotak (square crop) sisi 640px  (untuk grid)
 *   thumb.php?f=abc.jpg&w=1600  -> muat lebar, sisi terpanjang 1600px (untuk detail)
 *
 * Aman gagal: kalau ekstensi GD tidak ada, foto asli tetap dilayani apa adanya.
 */

require __DIR__ . '/config.php';

/* Lepaskan kunci session secepatnya supaya banyak gambar bisa dimuat paralel
   (tanpa ini, setiap request thumb.php akan saling antre karena lock session). */
if (function_exists('session_write_close')) {
    @session_write_close();
}

ini_set('memory_limit', '256M');

/* ===== Validasi parameter ===== */
$f = isset($_GET['f']) ? basename((string) $_GET['f']) : '';
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$src = UPLOAD_DIR . '/' . $f;

if ($f === '' || strpbrk($f, "/\\") !== false
    || !in_array($ext, ALLOWED_EXT, true) || !is_file($src)) {
    http_response_code(404);
    exit;
}

/* Mode & ukuran: s = kotak (crop), w = muat sisi terpanjang. */
$mode = 'w';
$size = 0;
if (isset($_GET['s'])) { $mode = 's'; $size = (int) $_GET['s']; }
elseif (isset($_GET['w'])) { $mode = 'w'; $size = (int) $_GET['w']; }
$size = max(80, min(2000, $size ?: 640));

function mime_for(string $ext): string
{
    switch ($ext) {
        case 'png':  return 'image/png';
        case 'webp': return 'image/webp';
        case 'gif':  return 'image/gif';
        default:     return 'image/jpeg';
    }
}

/* Kirim file dengan header cache panjang + dukungan 304 (Not Modified). */
function stream_file(string $path, string $mime): void
{
    $mtime = @filemtime($path) ?: time();
    $etag  = '"' . md5($path . '|' . $mtime) . '"';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);

    $inm = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    if (($inm !== '' && $inm === $etag) || ($ims !== '' && @strtotime($ims) >= $mtime)) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . (string) (@filesize($path) ?: 0));
    readfile($path);
}

/* ===== Fallback: tanpa GD, atau GIF (jaga animasinya) -> kirim asli ===== */
$haveGd = function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
if (!$haveGd || $ext === 'gif') {
    stream_file($src, mime_for($ext));
    exit;
}

/* ===== Cache ===== */
$canWebp  = function_exists('imagewebp') && strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'image/webp') !== false;
$outFmt   = $canWebp ? 'webp' : 'jpg';
$cacheDir = UPLOAD_DIR . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheFile = $cacheDir . '/' . $f . '-' . $mode . $size . '.' . $outFmt;

if (is_file($cacheFile) && @filemtime($cacheFile) >= @filemtime($src)) {
    stream_file($cacheFile, $outFmt === 'webp' ? 'image/webp' : 'image/jpeg');
    exit;
}

/* ===== Muat sumber ===== */
$info = @getimagesize($src);
if ($info === false) {
    stream_file($src, mime_for($ext));
    exit;
}

switch ($info[2]) {
    case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
    case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
    case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
    default:             $img = false;
}
if (!$img) {
    stream_file($src, mime_for($ext));
    exit;
}

/* Perbaiki rotasi dari EXIF (foto HP sering "miring"). */
if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $exif = @exif_read_data($src);
    $o = $exif['Orientation'] ?? 0;
    if ($o === 3) {
        $img = imagerotate($img, 180, 0);
    } elseif ($o === 6) {
        $img = imagerotate($img, -90, 0);
    } elseif ($o === 8) {
        $img = imagerotate($img, 90, 0);
    }
}

$sw = imagesx($img);
$sh = imagesy($img);

/* Hitung area sumber & ukuran tujuan. */
if ($mode === 's') {
    $side = min($sw, $sh);
    $sx = (int) (($sw - $side) / 2);
    $sy = (int) (($sh - $side) / 2);
    $out = min($size, $side);               // jangan perbesar melebihi aslinya
    $dw = $dh = $out;
    $srcW = $srcH = $side;
} else {
    $long  = max($sw, $sh);
    $scale = min(1.0, $size / $long);       // jangan perbesar
    $sx = $sy = 0;
    $dw = max(1, (int) round($sw * $scale));
    $dh = max(1, (int) round($sh * $scale));
    $srcW = $sw;
    $srcH = $sh;
}

$dst = imagecreatetruecolor($dw, $dh);
$white = imagecolorallocate($dst, 255, 255, 255);
imagefilledrectangle($dst, 0, 0, $dw, $dh, $white);   // ratakan transparansi ke putih
imagecopyresampled($dst, $img, 0, 0, $sx, $sy, $dw, $dh, $srcW, $srcH);
imagedestroy($img);

/* Tulis ke cache (atomik via file sementara). */
$tmp = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
$ok = false;
if ($outFmt === 'webp') {
    $ok = @imagewebp($dst, $tmp, 80);
} else {
    @imageinterlace($dst, true);            // progressive JPEG (muncul bertahap, terasa cepat)
    $ok = @imagejpeg($dst, $tmp, 82);
}
imagedestroy($dst);

if ($ok && @rename($tmp, $cacheFile)) {
    stream_file($cacheFile, $outFmt === 'webp' ? 'image/webp' : 'image/jpeg');
} else {
    @unlink($tmp);
    stream_file($src, mime_for($ext));      // generate gagal -> kirim asli
}
