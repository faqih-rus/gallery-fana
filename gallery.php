<?php
require __DIR__ . '/config.php';

$photos = load_photos();

/* Urut kronologis: yang ada tanggal naik (cerita dari awal), tanpa tanggal ke akhir. */
usort($photos, function ($a, $b) {
    $da = $a['date'] ?? '';
    $db = $b['date'] ?? '';
    if ($da === '' && $db === '') {
        return strcmp($a['uploaded_at'] ?? '', $b['uploaded_at'] ?? '');
    }
    if ($da === '') return 1;
    if ($db === '') return -1;
    return strcmp($da, $db);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(GALLERY_TITLE) ?> · <?= e(GALLERY_TAGLINE) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#ffffff; --bg-light:#f8f9fa; --bg-panel:#f0f1f3;
    --ink:#1a1a1a; --muted:#666666; --faint:#999999;
    --accent:#4a90e2; --gold:#d4a574;
    --line:#d0d0d0;
    --display:'Cormorant Garamond',serif; --ui:'Jost',sans-serif;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0; color:var(--ink); font-family:var(--ui); font-weight:300;
    background:var(--bg);
  }

  .shell{position:relative; z-index:2; max-width:1180px; margin:0 auto; padding:0 22px 110px}

  /* ===== HERO ===== */
  .hero{text-align:center; padding:12vh 10px 10vh}
  .kicker{font-size:12px; letter-spacing:.42em; text-transform:uppercase; color:var(--accent); margin-bottom:24px; font-weight:600}
  .hero h1{
    font-family:var(--display); font-weight:500; font-size:clamp(56px,12vw,128px); line-height:.96;
    margin:0; letter-spacing:.5px; color:var(--ink);
  }
  .hero .tag{font-family:var(--display); font-style:italic; font-size:clamp(19px,3.4vw,27px); color:var(--muted); margin-top:20px}
  .divider{display:flex; align-items:center; justify-content:center; gap:16px; margin:32px auto 0; max-width:240px; color:var(--accent)}
  .divider::before,.divider::after{content:""; height:2px; flex:1; background:var(--line)}
  .divider span{font-size:12px; letter-spacing:.2em; font-weight:600}

  /* ===== GRID (masonry via columns) ===== */
  .grid{column-count:3; column-gap:24px}
  @media(max-width:900px){.grid{column-count:2}}
  @media(max-width:560px){.grid{column-count:1}}

  figure.shot{
    break-inside:avoid; margin:0 0 24px; position:relative; border-radius:12px; overflow:hidden;
    border:1px solid var(--line); background:var(--bg-light); cursor:zoom-in;
    opacity:0; transform:translateY(22px); animation:rise .9s cubic-bezier(.2,.7,.2,1) forwards;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
  }
  @keyframes rise{to{opacity:1; transform:none}}
  figure.shot img{display:block; width:100%; height:auto; transition:transform 1.1s cubic-bezier(.2,.7,.2,1), filter .6s; filter:saturate(1) brightness(1)}
  figure.shot:hover img{transform:scale(1.03); filter:saturate(1.05) brightness(1.02)}
  figure.shot figcaption{
    position:absolute; inset:auto 0 0 0; padding:48px 18px 18px; color:var(--ink);
    background:linear-gradient(0deg, rgba(255,255,255,.98), rgba(255,255,255,.88) 55%, transparent);
    transform:translateY(8px); opacity:0; transition:opacity .45s, transform .45s;
  }
  figure.shot:hover figcaption{opacity:1; transform:none}
  figcaption .date{font-size:12px; letter-spacing:.16em; text-transform:uppercase; color:var(--accent); margin-bottom:6px; font-weight:600}
  figcaption .cap{font-family:var(--display); font-style:italic; font-size:20px; line-height:1.25; color:var(--ink)}
  figcaption .loc{font-size:13px; color:var(--muted); margin-top:6px}

  /* ===== EMPTY ===== */
  .empty{text-align:center; padding:10vh 0; color:var(--muted)}
  .empty .mark{font-size:42px; color:var(--accent)}
  .empty p{font-family:var(--display); font-style:italic; font-size:24px; margin:16px 0 0; color:var(--ink)}
  .empty a{color:var(--accent); font-weight:600}

  /* ===== FOOTER ===== */
  footer{text-align:center; margin-top:90px; color:var(--muted); font-size:13px; letter-spacing:.06em}
  footer .heart{color:#e74c3c}

  /* ===== LIGHTBOX ===== */
  .lb{position:fixed; inset:0; z-index:50; display:none; place-items:center; padding:30px;
      background:rgba(255,255,255,.96); backdrop-filter:blur(6px); cursor:zoom-out}
  .lb.open{display:grid; animation:fade .35s ease}
  @keyframes fade{from{opacity:0}to{opacity:1}}
  .lb figure{margin:0; max-width:92vw; max-height:90vh; text-align:center; cursor:auto}
  .lb img{max-width:92vw; max-height:76vh; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,.15)}
  .lb .info{margin-top:20px; color:var(--ink)}
  .lb .info .date{font-size:12px; letter-spacing:.18em; text-transform:uppercase; color:var(--accent); font-weight:600}
  .lb .info .cap{font-family:var(--display); font-style:italic; font-size:24px; margin-top:8px; color:var(--ink)}
  .lb .info .loc{font-size:14px; color:var(--muted); margin-top:6px}
  .lb button{position:fixed; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.95); border:1px solid var(--line); color:var(--ink); width:50px; height:50px; border-radius:50%; font-size:22px; cursor:pointer; transition:background .2s; font-weight:600}
  .lb button:hover{background:var(--bg-light); color:var(--accent); border-color:var(--accent)}
  .lb .prev{left:22px} .lb .next{right:22px}
  .lb .close{top:22px; right:22px; transform:none; width:44px; height:44px; font-size:20px}
  @media(max-width:560px){.lb button{display:none} .lb .close{display:block}}
</style>
</head>
<body>
<div class="shell">

  <header class="hero">
    <div class="kicker"><?= SINCE !== '' ? 'sejak ' . e(SINCE) : e(GALLERY_TAGLINE) ?></div>
    <h1><?= e(GALLERY_TITLE) ?></h1>
    <div class="tag"><?= e(GALLERY_TAGLINE) ?></div>
    <div class="divider"><span>✦</span></div>
  </header>

  <?php if (!$photos): ?>
    <div class="empty">
      <div class="mark">✦</div>
      <p>Halaman ini menunggu kenangan pertama kalian.</p>
      <p style="font-size:15px;font-style:normal;font-family:var(--ui);margin-top:18px">
        <a href="admin.php">Buka panel admin →</a>
      </p>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($photos as $i => $p):
        $src = 'uploads/' . $p['filename'];
        $cap = e($p['caption']);
        $date = e(pretty_date($p['date'] ?? ''));
        $loc = e($p['location'] ?? '');
        $delay = min($i * 0.08, 1.2);
      ?>
        <figure class="shot" style="animation-delay:<?= $delay ?>s"
                data-src="<?= e($src) ?>" data-cap="<?= $cap ?>" data-date="<?= $date ?>" data-loc="<?= $loc ?>">
          <img src="<?= e($src) ?>" alt="<?= $cap ?: 'kenangan' ?>" loading="lazy">
          <?php if ($cap || $date || $loc): ?>
          <figcaption>
            <?php if ($date): ?><div class="date"><?= $date ?></div><?php endif; ?>
            <?php if ($cap): ?><div class="cap"><?= $cap ?></div><?php endif; ?>
            <?php if ($loc): ?><div class="loc">⌖ <?= $loc ?></div><?php endif; ?>
          </figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <footer>
    dibuat dengan <span class="heart">&#10084;</span> · <a href="admin.php" style="color:var(--faint)">·</a>
  </footer>
</div>

<!-- Lightbox -->
<div class="lb" id="lb" aria-hidden="true">
  <button class="close" id="lbClose" aria-label="Tutup">&times;</button>
  <button class="prev" id="lbPrev" aria-label="Sebelumnya">&#8249;</button>
  <button class="next" id="lbNext" aria-label="Berikutnya">&#8250;</button>
  <figure>
    <img id="lbImg" src="" alt="">
    <div class="info">
      <div class="date" id="lbDate"></div>
      <div class="cap" id="lbCap"></div>
      <div class="loc" id="lbLoc"></div>
    </div>
  </figure>
</div>

<script>
  const shots = [...document.querySelectorAll('.shot')];
  const lb = document.getElementById('lb');
  const lbImg = document.getElementById('lbImg');
  const lbCap = document.getElementById('lbCap');
  const lbDate = document.getElementById('lbDate');
  const lbLoc = document.getElementById('lbLoc');
  let idx = 0;

  function render(i) {
    const f = shots[i];
    if (!f) return;
    idx = i;
    lbImg.src = f.dataset.src;
    lbCap.textContent = f.dataset.cap || '';
    lbDate.textContent = f.dataset.date || '';
    lbLoc.textContent = f.dataset.loc ? '⌖ ' + f.dataset.loc : '';
  }
  function open(i) { render(i); lb.classList.add('open'); lb.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function close() { lb.classList.remove('open'); lb.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
  const next = () => render((idx + 1) % shots.length);
  const prev = () => render((idx - 1 + shots.length) % shots.length);

  shots.forEach((f, i) => f.addEventListener('click', () => open(i)));
  document.getElementById('lbClose').addEventListener('click', close);
  document.getElementById('lbNext').addEventListener('click', e => { e.stopPropagation(); next(); });
  document.getElementById('lbPrev').addEventListener('click', e => { e.stopPropagation(); prev(); });
  lb.addEventListener('click', e => { if (e.target === lb) close(); });
  lb.querySelector('figure').addEventListener('click', e => e.stopPropagation());
  document.addEventListener('keydown', e => {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
  });
</script>
</body>
</html>
