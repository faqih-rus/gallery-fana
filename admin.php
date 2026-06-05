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

    if ($action === 'delete_comment') {
        $all = load_comments();
        $pid = (string) ($_POST['photo'] ?? '');
        $cid = (string) ($_POST['cid'] ?? '');
        if (isset($all[$pid]) && is_array($all[$pid])) {
            $all[$pid] = array_values(array_filter(
                $all[$pid],
                fn($c) => ($c['id'] ?? '') !== $cid
            ));
            if (empty($all[$pid])) {
                unset($all[$pid]);
            }
            save_comments($all);
            flash('Komentar dihapus.');
        }
        header('Location: admin.php');
        exit;
    }
}

$photos = load_photos();
usort($photos, fn($a, $b) => strcmp($b['uploaded_at'] ?? '', $a['uploaded_at'] ?? ''));
$allComments = load_comments();
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
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500&family=Parisienne&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#fff7fa; --bg-light:#fdeef3; --bg-panel:#fbe6ee;
    --ink:#4a2c38; --muted:#9c7a86; --faint:#c6a9b3;
    --accent:#c4607e; --rose:#e0879f; --rose-soft:#f4cbd8; --gold:#d8b48c; --danger:#d96a7e;
    --line:#f1d9e2;
    --display:'Cormorant Garamond',serif; --ui:'Jost',sans-serif; --script:'Parisienne',cursive;
  }
  *{box-sizing:border-box}
  body{
    margin:0; color:var(--ink); font-family:var(--ui);
    font-weight:300; line-height:1.6; min-height:100vh;
    background:
      radial-gradient(1000px 560px at 10% -8%, #ffe4ef 0%, transparent 58%),
      radial-gradient(900px 520px at 102% 4%,  #fbe6fb 0%, transparent 55%),
      linear-gradient(180deg,#fff8fb 0%, #fff2f7 100%);
    background-attachment:fixed;
  }
  /* Lapisan hati melayang, sangat halus */
  #hearts{position:fixed; inset:0; z-index:0; overflow:hidden; pointer-events:none}
  #hearts svg{position:absolute; bottom:-8vh; opacity:0; will-change:transform,opacity}
  @keyframes floatUp{
    0%{transform:translateY(0) translateX(0) rotate(0); opacity:0}
    12%{opacity:var(--op)}88%{opacity:var(--op)}
    100%{transform:translateY(-118vh) translateX(var(--dx)) rotate(var(--rot)); opacity:0}
  }
  a{color:var(--accent); text-decoration:none}
  .wrap{position:relative; z-index:2; max-width:1080px; margin:0 auto; padding:40px 22px 90px}
  header.top{display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:12px; border-bottom:1px solid var(--line); padding-bottom:20px; margin-bottom:36px}
  .brand{font-family:var(--display); font-size:34px; letter-spacing:.5px; color:var(--ink)}
  .brand small{font-family:var(--ui); font-size:11px; letter-spacing:.4em; text-transform:uppercase; color:var(--accent); display:block; margin-bottom:4px; font-weight:500}
  .nav{font-size:14px; letter-spacing:.04em}
  .nav a{margin-left:20px; color:var(--muted); font-weight:400}
  .nav a:hover{color:var(--accent)}

  /* Flash */
  .flash{padding:14px 18px; border-radius:12px; margin-bottom:16px; font-size:14px; border-left:4px solid; background:var(--bg-light)}
  .flash.err{border-color:var(--danger); color:#b04258; background:#fbe0e6}
  .flash.ok{border-color:var(--accent); color:var(--accent); background:#fce6ee}

  /* Login card */
  .login{max-width:390px; margin:9vh auto 0; background:rgba(255,255,255,.7); backdrop-filter:blur(8px); border:1px solid var(--line); border-radius:18px; padding:44px 36px; text-align:center; box-shadow:0 20px 50px -24px rgba(196,96,126,.4)}
  .login .seal{width:30px; height:30px; color:var(--rose); margin:0 auto 6px; animation:beat 2.4s ease-in-out infinite}
  @keyframes beat{0%,100%{transform:scale(1)}14%{transform:scale(1.18)}28%{transform:scale(1)}42%{transform:scale(1.12)}}
  .login h1{font-family:var(--display); font-weight:500; font-size:38px; margin:.1em 0 .1em; color:var(--ink)}
  .login p{color:var(--muted); font-size:15px; margin:0 0 28px; font-weight:300}

  label{display:block; font-size:12px; letter-spacing:.14em; text-transform:uppercase; color:var(--ink); margin:0 0 8px; font-weight:500}
  input[type=text],input[type=password],input[type=date],textarea{
    width:100%; background:rgba(255,255,255,.85); border:1px solid var(--line); color:var(--ink);
    border-radius:10px; padding:12px 14px; font-family:var(--ui); font-size:15px; font-weight:300; outline:none; transition:border-color .2s, box-shadow .2s;
  }
  input:focus,textarea:focus{border-color:var(--rose); box-shadow:0 0 0 3px rgba(224,135,159,.18)}
  textarea{resize:vertical; min-height:64px}
  .field{margin-bottom:18px; text-align:left}
  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:18px}

  .btn{appearance:none; cursor:pointer; border:1px solid var(--accent); background:var(--accent); color:#fff; font-family:var(--ui); font-size:14px; letter-spacing:.05em; padding:12px 26px; border-radius:10px; transition:background .2s, transform .15s, box-shadow .2s; font-weight:500; box-shadow:0 8px 20px -10px rgba(196,96,126,.6)}
  .btn:hover{background:#ad506c; transform:translateY(-2px); box-shadow:0 12px 26px -10px rgba(196,96,126,.7)}
  .btn.full{width:100%}
  .btn.ghost{border-color:var(--line); background:rgba(255,255,255,.7); color:var(--muted); box-shadow:none}
  .btn.ghost:hover{color:var(--accent); border-color:var(--rose)}
  .btn.danger{border-color:var(--danger); background:transparent; color:var(--danger); box-shadow:none}
  .btn.danger:hover{background:var(--danger); color:#fff}
  .btn.sm{padding:9px 16px; font-size:13px}

  /* Panels */
  .card{background:rgba(255,255,255,.66); backdrop-filter:blur(8px); border:1px solid var(--line); border-radius:16px; padding:32px; margin-bottom:34px; box-shadow:0 16px 40px -26px rgba(196,96,126,.4)}
  .card h2{font-family:var(--display); font-weight:500; font-size:30px; margin:0 0 8px; color:var(--ink)}
  .card .hint{color:var(--muted); font-size:14px; margin:0 0 24px; line-height:1.5}

  /* Dropzone */
  .drop{border:2px dashed var(--rose-soft); border-radius:14px; padding:34px; text-align:center; color:var(--muted); cursor:pointer; transition:border-color .2s, background .2s, color .2s; margin-bottom:18px}
  .drop:hover,.drop.hot{border-color:var(--rose); background:rgba(224,135,159,.06); color:var(--ink)}
  .drop b{color:var(--accent); font-weight:500}
  .drop .files{margin-top:12px; font-size:14px; color:var(--ink)}

  /* List */
  .count{color:var(--muted); font-size:14px; margin-bottom:18px; letter-spacing:.04em; font-weight:400}
  .items{display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px}
  .item{background:rgba(255,255,255,.85); border:1px solid var(--line); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 12px 30px -22px rgba(196,96,126,.5); transition:transform .3s, box-shadow .3s}
  .item:hover{transform:translateY(-3px); box-shadow:0 18px 40px -22px rgba(196,96,126,.6)}
  .item .thumb{aspect-ratio:4/3; background:#fbe6ee center/cover no-repeat}
  .item .body{padding:16px; display:flex; flex-direction:column; gap:12px; flex:1}
  .item .meta{font-size:11px; color:var(--accent); letter-spacing:.1em; text-transform:uppercase; font-weight:500}
  .item details summary{cursor:pointer; color:var(--muted); font-size:14px; list-style:none; font-weight:400}
  .item details summary::-webkit-details-marker{display:none}
  .item details[open] summary{color:var(--accent)}
  .row{display:flex; gap:10px; margin-top:auto}
  .empty{color:var(--muted); font-style:italic; font-family:var(--display); font-size:21px}
  @media(max-width:520px){.grid2{grid-template-columns:1fr}}
  @media (prefers-reduced-motion: reduce){*{animation:none !important}}
</style>
</head>
<body>
<div id="hearts" aria-hidden="true"></div>
<div class="wrap">

<?php foreach ($flashes as $f): ?>
  <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>

<?php if (!$logged): ?>
  <!-- ===== LOGIN ===== -->
  <div class="login">
    <svg class="seal" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
    <small style="letter-spacing:.4em;text-transform:uppercase;color:var(--gold);font-size:11px">Ruang Privat</small>
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
            <div class="thumb" style="background-image:url('thumb.php?f=<?= e(rawurlencode($p['filename'])) ?>&amp;s=480')"></div>
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

              <?php $cs = comments_for($allComments, $p['id']); ?>
              <details>
                <summary>Komentar (<?= count($cs) ?>)</summary>
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:10px">
                  <?php if (!$cs): ?>
                    <div style="color:var(--faint);font-size:13px;font-style:italic">Belum ada komentar.</div>
                  <?php else: foreach ($cs as $c): ?>
                    <div style="background:var(--bg-light);border:1px solid var(--line);border-radius:10px;padding:10px 12px">
                      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
                        <span style="font-size:12px;color:var(--accent);font-weight:500"><?= e($c['name'] ?? 'Anonim') ?></span>
                        <form method="post" onsubmit="return confirm('Hapus komentar ini?')" style="margin:0">
                          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_comment">
                          <input type="hidden" name="photo" value="<?= e($p['id']) ?>">
                          <input type="hidden" name="cid" value="<?= e($c['id'] ?? '') ?>">
                          <button type="submit" title="Hapus komentar" style="border:none;background:none;color:var(--danger);cursor:pointer;font-size:13px;padding:0">&times; hapus</button>
                        </form>
                      </div>
                      <div style="font-size:13px;color:var(--ink);white-space:pre-wrap;word-break:break-word;margin-top:2px"><?= e($c['body'] ?? '') ?></div>
                    </div>
                  <?php endforeach; endif; ?>
                </div>
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
  // Hati melayang lembut di latar
  (function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const layer = document.getElementById('hearts');
    if (!layer) return;
    const colors = ['#f6b8cb', '#efa0bb', '#e98aa9', '#f7cdd9', '#e6c4ec'];
    const PATH = 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z';
    const rand = (a, b) => a + Math.random() * (b - a);
    const COUNT = window.innerWidth < 600 ? 7 : 12;
    const NS = 'http://www.w3.org/2000/svg';
    for (let i = 0; i < COUNT; i++) {
      const s = rand(12, 28);
      const svg = document.createElementNS(NS, 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('width', s); svg.setAttribute('height', s);
      const path = document.createElementNS(NS, 'path');
      path.setAttribute('d', PATH);
      path.setAttribute('fill', colors[(Math.random() * colors.length) | 0]);
      svg.appendChild(path);
      const dur = rand(16, 28);
      svg.style.left = rand(0, 100) + 'vw';
      svg.style.setProperty('--op', rand(.06, .18).toFixed(2));
      svg.style.setProperty('--dx', rand(-50, 50).toFixed(0) + 'px');
      svg.style.setProperty('--rot', rand(-25, 25).toFixed(0) + 'deg');
      svg.style.filter = Math.random() < .4 ? 'blur(1.5px)' : 'none';
      svg.style.animation = `floatUp ${dur.toFixed(1)}s linear ${(-rand(0, dur)).toFixed(1)}s infinite`;
      layer.appendChild(svg);
    }
  })();

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
