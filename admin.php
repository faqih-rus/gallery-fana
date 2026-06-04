<?php
require __DIR__ . '/config.php';

/* ===== Login ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (hash_equals(ADMIN_PASSWORD, (string) ($_POST['password'] ?? ''))) {
        $_SESSION['authed'] = true;
        flash('Selamat datang kembali.');
    } else {
        flash('Password salah.', 'err');
    }
    header('Location: admin.php');
    exit;
}

/* ===== Logout ===== */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

/* ===== Aksi terproteksi ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    if (!check_csrf()) {
        flash('Sesi kedaluwarsa, coba lagi.', 'err');
        header('Location: admin.php');
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $photos = load_photos();
        $files  = $_FILES['images'] ?? null;
        $added  = 0;

        if ($files && is_array($files['name'])) {
            $n = count($files['name']);
            for ($i = 0; $i < $n; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                if ($files['size'][$i] > MAX_FILE_SIZE) {
                    flash('Ada file lebih dari 12MB, dilewati.', 'err');
                    continue;
                }
                $tmp = $files['tmp_name'][$i];
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_EXT, true)) {
                    flash('Ada tipe file tak didukung, dilewati.', 'err');
                    continue;
                }
                if (@getimagesize($tmp) === false) {
                    flash('Ada file bukan gambar valid, dilewati.', 'err');
                    continue;
                }
                $name = bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($tmp, UPLOAD_DIR . '/' . $name)) {
                    $photos[] = [
                        'id'          => bin2hex(random_bytes(6)),
                        'filename'    => $name,
                        'caption'     => trim((string) ($_POST['caption'] ?? '')),
                        'date'        => trim((string) ($_POST['date'] ?? '')),
                        'location'    => trim((string) ($_POST['location'] ?? '')),
                        'uploaded_at' => date('c'),
                    ];
                    $added++;
                }
            }
        }
        save_photos($photos);
        if ($added > 0) {
            flash("Berhasil menambahkan {$added} foto.");
        } elseif (empty(take_flashes())) {
            flash('Tidak ada foto yang ditambahkan.', 'err');
        }
        header('Location: admin.php');
        exit;
    }

    if ($action === 'update') {
        $photos = load_photos();
        $i = find_photo($photos, (string) ($_POST['id'] ?? ''));
        if ($i !== null) {
            $photos[$i]['caption']  = trim((string) ($_POST['caption'] ?? ''));
            $photos[$i]['date']     = trim((string) ($_POST['date'] ?? ''));
            $photos[$i]['location'] = trim((string) ($_POST['location'] ?? ''));
            save_photos($photos);
            flash('Tersimpan.');
        }
        header('Location: admin.php');
        exit;
    }

    if ($action === 'delete') {
        $photos = load_photos();
        $i = find_photo($photos, (string) ($_POST['id'] ?? ''));
        if ($i !== null) {
            $file = UPLOAD_DIR . '/' . $photos[$i]['filename'];
            if (is_file($file)) {
                @unlink($file);
            }
            array_splice($photos, $i, 1);
            save_photos($photos);
            flash('Foto dihapus.');
        }
        header('Location: admin.php');
        exit;
    }
}

$photos = load_photos();
usort($photos, fn($a, $b) => strcmp($b['uploaded_at'] ?? '', $a['uploaded_at'] ?? ''));
$flashes = take_flashes();
$logged  = is_logged_in();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin · <?= e(GALLERY_TITLE) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#ffffff; --bg-light:#f8f9fa; --bg-panel:#f0f1f3;
    --ink:#1a1a1a; --muted:#666666; --faint:#999999;
    --accent:#4a90e2; --gold:#c9a96a; --danger:#e74c3c;
    --line:#d0d0d0;
    --display:'Cormorant Garamond',serif; --ui:'Jost',sans-serif;
  }
  *{box-sizing:border-box}
  body{
    margin:0; background:var(--bg); color:var(--ink); font-family:var(--ui);
    font-weight:300; line-height:1.6;
  }
  a{color:var(--accent); text-decoration:none}
  .wrap{max-width:1080px; margin:0 auto; padding:40px 22px 80px}
  header.top{display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:12px; border-bottom:2px solid var(--line); padding-bottom:20px; margin-bottom:36px}
  .brand{font-family:var(--display); font-size:32px; letter-spacing:.5px; color:var(--ink)}
  .brand small{font-family:var(--ui); font-size:12px; letter-spacing:.32em; text-transform:uppercase; color:var(--accent); display:block; margin-bottom:4px; font-weight:500}
  .nav{font-size:14px; letter-spacing:.04em}
  .nav a{margin-left:20px; color:var(--muted); font-weight:500}
  .nav a:hover{color:var(--accent)}

  /* Flash */
  .flash{padding:14px 18px; border-radius:10px; margin-bottom:16px; font-size:14px; border-left:4px solid; background:var(--bg-light)}
  .flash.err{border-color:var(--danger); color:#c0392b; background:#fadbd8}
  .flash.ok{border-color:var(--accent); color:var(--accent); background:#d6eaf8}

  /* Login card */
  .login{max-width:380px; margin:8vh auto 0; background:var(--bg-light); border:1px solid var(--line); border-radius:14px; padding:40px 36px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .login h1{font-family:var(--display); font-weight:500; font-size:36px; margin:.2em 0 .1em; color:var(--ink)}
  .login p{color:var(--muted); font-size:15px; margin:0 0 28px; font-weight:300}

  label{display:block; font-size:13px; letter-spacing:.1em; text-transform:uppercase; color:var(--ink); margin:0 0 8px; font-weight:500}
  input[type=text],input[type=password],input[type=date],textarea{
    width:100%; background:#ffffff; border:1px solid var(--line); color:var(--ink);
    border-radius:8px; padding:12px 14px; font-family:var(--ui); font-size:15px; font-weight:300; outline:none; transition:border-color .2s, box-shadow .2s;
  }
  input:focus,textarea:focus{border-color:var(--accent); box-shadow:0 0 0 3px rgba(74,144,226,.12)}
  textarea{resize:vertical; min-height:64px}
  .field{margin-bottom:18px; text-align:left}
  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:18px}

  .btn{appearance:none; cursor:pointer; border:2px solid var(--accent); background:var(--accent); color:#ffffff; font-family:var(--ui); font-size:14px; letter-spacing:.05em; padding:12px 24px; border-radius:8px; transition:background .2s, transform .15s; font-weight:500}
  .btn:hover{background:#3973d1; transform:translateY(-2px)}
  .btn.full{width:100%}
  .btn.ghost{border-color:var(--line); background:#ffffff; color:var(--muted)}
  .btn.ghost:hover{color:var(--accent); border-color:var(--accent)}
  .btn.danger{border-color:var(--danger); background:var(--danger); color:#ffffff}
  .btn.danger:hover{background:#d43f28}
  .btn.sm{padding:10px 16px; font-size:13px}

  /* Panels */
  .card{background:var(--bg-light); border:1px solid var(--line); border-radius:12px; padding:30px; margin-bottom:36px; box-shadow:0 1px 3px rgba(0,0,0,.06)}
  .card h2{font-family:var(--display); font-weight:500; font-size:28px; margin:0 0 8px; color:var(--ink)}
  .card .hint{color:var(--muted); font-size:14px; margin:0 0 24px; line-height:1.5}

  /* Dropzone */
  .drop{border:2px dashed var(--line); border-radius:12px; padding:32px; text-align:center; color:var(--muted); cursor:pointer; transition:border-color .2s, background .2s; margin-bottom:18px}
  .drop:hover,.drop.hot{border-color:var(--accent); background:rgba(74,144,226,.04); color:var(--ink)}
  .drop b{color:var(--accent); font-weight:600}
  .drop .files{margin-top:12px; font-size:14px; color:var(--ink)}

  /* List */
  .count{color:var(--muted); font-size:14px; margin-bottom:18px; letter-spacing:.04em; font-weight:500}
  .items{display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px}
  .item{background:#ffffff; border:1px solid var(--line); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 1px 3px rgba(0,0,0,.06)}
  .item .thumb{aspect-ratio:4/3; background:#f0f0f0 center/cover no-repeat}
  .item .body{padding:16px; display:flex; flex-direction:column; gap:12px; flex:1}
  .item .meta{font-size:12px; color:var(--accent); letter-spacing:.08em; text-transform:uppercase; font-weight:600}
  .item details summary{cursor:pointer; color:var(--muted); font-size:14px; list-style:none; font-weight:500}
  .item details summary::-webkit-details-marker{display:none}
  .item details[open] summary{color:var(--accent)}
  .row{display:flex; gap:10px; margin-top:auto}
  .empty{color:var(--muted); font-style:italic; font-family:var(--display); font-size:20px}
  @media(max-width:520px){.grid2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

<?php foreach ($flashes as $f): ?>
  <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>

<?php if (!$logged): ?>
  <!-- ===== LOGIN ===== -->
  <div class="login">
    <small style="letter-spacing:.32em;text-transform:uppercase;color:var(--gold);font-size:11px">Ruang Privat</small>
    <h1><?= e(GALLERY_TITLE) ?></h1>
    <p>Masuk untuk mengelola kenangan.</p>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <div class="field">
        <label for="pw">Password</label>
        <input id="pw" type="password" name="password" autofocus required>
      </div>
      <button class="btn full" type="submit">Masuk</button>
    </form>
    <p style="margin:22px 0 0"><a href="gallery.php">← lihat galeri</a></p>
  </div>

<?php else: ?>
  <!-- ===== DASHBOARD ===== -->
  <header class="top">
    <div class="brand"><small>Dasbor</small><?= e(GALLERY_TITLE) ?></div>
    <nav class="nav">
      <a href="gallery.php" target="_blank">Lihat Galeri ↗</a>
      <a href="admin.php?logout=1">Keluar</a>
    </nav>
  </header>

  <!-- Upload -->
  <section class="card">
    <h2>Tambah Kenangan</h2>
    <p class="hint">Pilih satu atau beberapa gambar sekaligus. Caption &amp; tanggal opsional, tapi bikin galeri terasa hidup.</p>
    <form method="post" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload">

      <label for="file" class="drop" id="drop">
        Tarik gambar ke sini atau <b>klik untuk memilih</b>
        <div class="files" id="fileList"></div>
      </label>
      <input id="file" type="file" name="images[]" accept="image/*" multiple hidden required>

      <div class="grid2">
        <div class="field">
          <label for="cap">Caption</label>
          <input id="cap" type="text" name="caption" placeholder="mis. Sore yang nggak buru-buru">
        </div>
        <div class="field">
          <label for="dt">Tanggal</label>
          <input id="dt" type="date" name="date">
        </div>
      </div>
      <div class="field">
        <label for="loc">Lokasi <span style="text-transform:none;letter-spacing:0;color:var(--faint)">(opsional)</span></label>
        <input id="loc" type="text" name="location" placeholder="mis. Lembang">
      </div>

      <button class="btn" type="submit">Unggah</button>
    </form>
  </section>

  <!-- List -->
  <section class="card">
    <h2>Kenangan Tersimpan</h2>
    <p class="count"><?= count($photos) ?> foto</p>

    <?php if (!$photos): ?>
      <p class="empty">Belum ada apa-apa di sini. Mulai dari satu foto. ✦</p>
    <?php else: ?>
      <div class="items">
        <?php foreach ($photos as $p): ?>
          <div class="item">
            <div class="thumb" style="background-image:url('uploads/<?= e($p['filename']) ?>')"></div>
            <div class="body">
              <?php if (!empty($p['date'])): ?>
                <div class="meta"><?= e(pretty_date($p['date'])) ?></div>
              <?php endif; ?>
              <div style="font-family:var(--display);font-size:18px;font-style:italic;color:var(--ink)">
                <?= $p['caption'] !== '' ? e($p['caption']) : '<span style="color:var(--faint)">tanpa caption</span>' ?>
              </div>
              <?php if (!empty($p['location'])): ?>
                <div style="font-size:12px;color:var(--muted)">⌖ <?= e($p['location']) ?></div>
              <?php endif; ?>

              <details>
                <summary>Edit</summary>
                <form method="post" style="margin-top:12px;display:flex;flex-direction:column;gap:10px">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                  <input type="text" name="caption" value="<?= e($p['caption']) ?>" placeholder="Caption">
                  <input type="date" name="date" value="<?= e($p['date']) ?>">
                  <input type="text" name="location" value="<?= e($p['location']) ?>" placeholder="Lokasi">
                  <button class="btn sm" type="submit">Simpan</button>
                </form>
              </details>

              <div class="row">
                <form method="post" onsubmit="return confirm('Hapus foto ini?')">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                  <button class="btn danger sm" type="submit">Hapus</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

</div>

<script>
  // Dropzone interaksi
  const drop = document.getElementById('drop');
  const input = document.getElementById('file');
  const list = document.getElementById('fileList');
  if (drop && input) {
    const show = () => {
      const n = input.files.length;
      list.textContent = n ? (n + ' file dipilih: ' + [...input.files].map(f => f.name).join(', ')) : '';
    };
    input.addEventListener('change', show);
    ['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('hot'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('hot'); }));
    drop.addEventListener('drop', e => { input.files = e.dataTransfer.files; show(); });
  }
</script>
</body>
</html>
