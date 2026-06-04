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
    --bg:#13100e; --ink:#f4ebe1; --muted:#b6a596; --faint:#7c6f63;
    --rose:#cf9180; --gold:#cba96c;
    --line:rgba(203,169,108,.16);
    --display:'Cormorant Garamond',serif; --ui:'Jost',sans-serif;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0; color:var(--ink); font-family:var(--ui); font-weight:300;
    background:
      radial-gradient(1100px 600px at 75% -8%, rgba(207,145,128,.12), transparent 55%),
      radial-gradient(900px 600px at 12% 8%, rgba(203,169,108,.08), transparent 55%),
      var(--bg);
    background-attachment:fixed;
  }
  /* Tekstur grain halus */
  body::before{
    content:""; position:fixed; inset:0; pointer-events:none; opacity:.04; z-index:1; mix-blend-mode:overlay;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  }

  .shell{position:relative; z-index:2; max-width:1180px; margin:0 auto; padding:0 22px 110px}

  /* ===== HERO ===== */
  .hero{text-align:center; padding:16vh 10px 13vh}
  .kicker{font-size:11.5px; letter-spacing:.42em; text-transform:uppercase; color:var(--gold); margin-bottom:22px}
  .hero h1{
    font-family:var(--display); font-weight:500; font-size:clamp(56px,12vw,128px); line-height:.96;
    margin:0; letter-spacing:.5px;
    background:linear-gradient(180deg,#fbf3ea,#d9c2a7); -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  .hero .tag{font-family:var(--display); font-style:italic; font-size:clamp(19px,3.4vw,27px); color:var(--muted); margin-top:18px}
  .divider{display:flex; align-items:center; justify-content:center; gap:16px; margin:34px auto 0; max-width:240px; color:var(--gold)}
  .divider::before,.divider::after{content:""; height:1px; flex:1; background:linear-gradient(90deg,transparent,var(--line),transparent)}
  .divider span{font-size:11px; letter-spacing:.2em}

  /* ===== GRID (masonry via columns) ===== */
  .grid{column-count:3; column-gap:20px}
  @media(max-width:900px){.grid{column-count:2}}
  @media(max-width:560px){.grid{column-count:1}}

  figure.shot{
    break-inside:avoid; margin:0 0 20px; position:relative; border-radius:14px; overflow:hidden;
    border:1px solid var(--line); background:#0d0b09; cursor:zoom-in;
    opacity:0; transform:translateY(22px); animation:rise .9s cubic-bezier(.2,.7,.2,1) forwards;
  }
  @keyframes rise{to{opacity:1; transform:none}}
  figure.shot img{display:block; width:100%; height:auto; transition:transform 1.1s cubic-bezier(.2,.7,.2,1), filter .6s; filter:saturate(.96) brightness(.97)}
  figure.shot:hover img{transform:scale(1.045); filter:saturate(1.04) brightness(1.02)}
  figure.shot figcaption{
    position:absolute; inset:auto 0 0 0; padding:46px 18px 16px; color:#fff;
    background:linear-gradient(0deg, rgba(10,8,6,.92), rgba(10,8,6,.55) 55%, transparent);
    transform:translateY(8px); opacity:0; transition:opacity .45s, transform .45s;
  }
  figure.shot:hover figcaption{opacity:1; transform:none}
  figcaption .date{font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:var(--gold); margin-bottom:5px}
  figcaption .cap{font-family:var(--display); font-style:italic; font-size:20px; line-height:1.25}
  figcaption .loc{font-size:12px; color:var(--muted); margin-top:5px}

  /* ===== EMPTY ===== */
  .empty{text-align:center; padding:8vh 0; color:var(--faint)}
  .empty .mark{font-size:38px; color:var(--gold)}
  .empty p{font-family:var(--display); font-style:italic; font-size:24px; margin:14px 0 0}
  .empty a{color:var(--gold)}

  /* ===== FOOTER ===== */
  footer{text-align:center; margin-top:90px; color:var(--faint); font-size:12px; letter-spacing:.06em}
  footer .heart{color:var(--rose)}

  /* ===== LIGHTBOX ===== */
  .lb{position:fixed; inset:0; z-index:50; display:none; place-items:center; padding:30px;
      background:rgba(8,6,5,.92); backdrop-filter:blur(6px); cursor:zoom-out}
  .lb.open{display:grid; animation:fade .35s ease}
  @keyframes fade{from{opacity:0}to{opacity:1}}
  .lb figure{margin:0; max-width:92vw; max-height:90vh; text-align:center; cursor:auto}
  .lb img{max-width:92vw; max-height:76vh; border-radius:10px; box-shadow:0 30px 80px rgba(0,0,0,.6)}
  .lb .info{margin-top:16px; color:var(--ink)}
  .lb .info .date{font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:var(--gold)}
  .lb .info .cap{font-family:var(--display); font-style:italic; font-size:24px; margin-top:6px}
  .lb .info .loc{font-size:13px; color:var(--muted); margin-top:4px}
  .lb button{position:fixed; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.06); border:1px solid var(--line); color:var(--ink); width:50px; height:50px; border-radius:50%; font-size:22px; cursor:pointer; transition:background .2s}
  .lb button:hover{background:rgba(203,169,108,.22)}
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
