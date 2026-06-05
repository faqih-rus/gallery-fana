<?php
/**
 * comment.php — Endpoint komentar (JSON).
 *
 * Siapa pun boleh menulis komentar di tiap foto. Disimpan ke data/comments.json
 * (tanpa database). Tambahan baru — tidak mengubah alur foto yang sudah ada.
 *
 *   GET  comment.php?photo=<id>   -> daftar komentar foto itu
 *   POST comment.php              -> kirim komentar (butuh csrf + isi)
 */

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    // JSON_INVALID_UTF8_SUBSTITUTE: jangan pernah gagal hanya karena 1 byte rusak.
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* Bersihkan UTF-8 rusak (tanpa mbstring) supaya penyimpanan JSON tak pernah korup. */
function clean_utf8(string $s): string
{
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($r !== false) {
            return $r;
        }
    }
    return $s;
}

/* Potong per-karakter UTF-8 tanpa mbstring (pakai PCRE /u yang selalu tersedia). */
function str_limit_chars(string $s, int $max): string
{
    if ($s === '') {
        return $s;
    }
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        return substr($s, 0, $max);   // fallback aman
    }
    return count($chars) <= $max ? $s : implode('', array_slice($chars, 0, $max));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ===== Ambil daftar komentar ===== */
if ($method === 'GET') {
    @session_write_close();   // GET tak perlu session -> lepas kunci, bisa paralel
    $pid  = (string) ($_GET['photo'] ?? '');
    $list = comments_for(load_comments(), $pid);
    $clean = array_map(static fn($c) => [
        'name' => (string) ($c['name'] ?? 'Anonim'),
        'body' => (string) ($c['body'] ?? ''),
        'at'   => (string) ($c['at'] ?? ''),
    ], array_values($list));
    out(['ok' => true, 'comments' => $clean]);
}

/* ===== Kirim komentar ===== */
if ($method === 'POST') {
    if (!check_csrf()) {
        out(['ok' => false, 'error' => 'csrf'], 400);
    }
    @session_write_close();

    // Honeypot: bot biasanya mengisi kolom tersembunyi ini.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        out(['ok' => true]);   // pura-pura sukses, tapi tidak disimpan
    }

    $pid  = (string) ($_POST['photo'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));

    if ($body === '') {
        out(['ok' => false, 'error' => 'empty'], 422);
    }

    // Foto harus benar-benar ada.
    if (find_photo(load_photos(), $pid) === null) {
        out(['ok' => false, 'error' => 'notfound'], 404);
    }

    // Bersihkan & batasi panjang (tag dibuang; tampil sebagai teks biasa).
    $name = str_limit_chars(clean_utf8(strip_tags($name)), MAX_NAME_LEN);
    $body = str_limit_chars(clean_utf8(strip_tags($body)), MAX_COMMENT_LEN);
    if ($name === '') {
        $name = 'Anonim';
    }
    if (trim($body) === '') {
        out(['ok' => false, 'error' => 'empty'], 422);
    }

    $entry = [
        'id'   => bin2hex(random_bytes(6)),
        'name' => $name,
        'body' => $body,
        'at'   => date('c'),
    ];

    $all = load_comments();
    if (!isset($all[$pid]) || !is_array($all[$pid])) {
        $all[$pid] = [];
    }
    $all[$pid][] = $entry;

    if (!save_comments($all)) {
        out(['ok' => false, 'error' => 'save'], 500);
    }

    out(['ok' => true, 'comment' => ['name' => $name, 'body' => $body, 'at' => $entry['at']]]);
}

out(['ok' => false, 'error' => 'method'], 405);
