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
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500&family=Parisienne&display=swap" rel="stylesheet">
<style>
  :root{
    /* Blush & dusty-rose — lembut, tidak mencolok */
    --bg:#fff7fa; --bg-soft:#fdeef3; --bg-panel:#fbe6ee;
    --ink:#4a2c38; --muted:#9c7a86; --faint:#c6a9b3;
    --rose:#e0879f; --rose-deep:#c4607e; --rose-soft:#f4cbd8; --blush:#ffe7f0;
    --gold:#d8b48c;
    --line:#f1d9e2;
    --display:'Cormorant Garamond',serif; --ui:'Jost',sans-serif; --script:'Parisienne',cursive;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0; color:var(--ink); font-family:var(--ui); font-weight:300;
    background:
      radial-gradient(1100px 620px at 12% -8%, #ffe4ef 0%, transparent 58%),
      radial-gradient(1000px 540px at 102% 2%,  #fbe6fb 0%, transparent 55%),
      radial-gradient(950px 760px at 50% 116%,  #ffe9f2 0%, transparent 60%),
      linear-gradient(180deg,#fff8fb 0%, #fff2f7 100%);
    background-attachment:fixed;
    min-height:100vh;
  }

  /* Aurora lembut yang bergerak sangat pelan di belakang */
  body::before{
    content:""; position:fixed; inset:-20%; z-index:0; pointer-events:none;
    background:
      radial-gradient(620px 620px at 20% 30%, rgba(244,203,216,.35), transparent 60%),
      radial-gradient(560px 560px at 80% 70%, rgba(232,200,240,.30), transparent 60%);
    filter:blur(8px);
    animation:drift 26s ease-in-out infinite alternate;
  }
  @keyframes drift{
    from{transform:translate3d(-2%,-1%,0) scale(1)}
    to{transform:translate3d(3%,2%,0) scale(1.06)}
  }

  /* Lapisan hati melayang */
  #hearts{position:fixed; inset:0; z-index:1; overflow:hidden; pointer-events:none}
  #hearts svg{position:absolute; bottom:-8vh; will-change:transform,opacity; opacity:0}
  @keyframes floatUp{
    0%{transform:translateY(0) translateX(0) rotate(0); opacity:0}
    12%{opacity:var(--op)}
    88%{opacity:var(--op)}
    100%{transform:translateY(-118vh) translateX(var(--dx)) rotate(var(--rot)); opacity:0}
  }

  .shell{position:relative; z-index:3; max-width:1180px; margin:0 auto; padding:0 22px 120px}

  /* ===== HERO ===== */
  .hero{text-align:center; padding:14vh 10px 9vh}
  .kicker{font-size:11px; letter-spacing:.5em; text-transform:uppercase; color:var(--rose-deep); margin-bottom:26px; font-weight:500; opacity:0; animation:soft 1.2s ease .1s forwards}
  .hero h1{
    font-family:var(--display); font-weight:500; font-size:clamp(54px,11.5vw,124px); line-height:.98;
    margin:0; letter-spacing:1px; color:var(--ink);
    opacity:0; animation:soft 1.3s ease .25s forwards;
  }
  .hero .tag{
    font-family:var(--script); font-size:clamp(28px,5vw,46px); color:var(--rose-deep);
    margin-top:14px; line-height:1.1; opacity:0; animation:soft 1.4s ease .45s forwards;
  }
  @keyframes soft{from{opacity:0; transform:translateY(14px)}to{opacity:1; transform:none}}

  .divider{display:flex; align-items:center; justify-content:center; gap:18px; margin:34px auto 0; max-width:260px; color:var(--rose); opacity:0; animation:soft 1.5s ease .65s forwards}
  .divider::before,.divider::after{content:""; height:1px; flex:1; background:linear-gradient(90deg,transparent,var(--line),transparent)}
  .divider .beat{width:20px; height:20px; color:var(--rose); animation:beat 2.4s ease-in-out infinite}
  @keyframes beat{0%,100%{transform:scale(1)}14%{transform:scale(1.18)}28%{transform:scale(1)}42%{transform:scale(1.12)}}

  /* ===== GRID (masonry via columns) ===== */
  .grid{column-count:3; column-gap:24px}
  @media(max-width:900px){.grid{column-count:2}}
  @media(max-width:560px){.grid{column-count:1}}

  figure.shot{
    break-inside:avoid; margin:0 0 24px; position:relative; border-radius:16px; overflow:hidden;
    border:1px solid var(--line); background:var(--bg-soft); cursor:zoom-in;
    opacity:0; transform:translateY(26px) scale(.985); animation:rise 1s cubic-bezier(.2,.7,.2,1) forwards;
    box-shadow:0 10px 30px -16px rgba(196,96,126,.35);
    transition:box-shadow .5s, transform .5s;
  }
  figure.shot:hover{box-shadow:0 22px 50px -20px rgba(196,96,126,.45); transform:translateY(-4px)}
  @keyframes rise{to{opacity:1; transform:none}}
  figure.shot img{display:block; width:100%; height:auto; transition:transform 1.2s cubic-bezier(.2,.7,.2,1), filter .6s; filter:saturate(1.02)}
  figure.shot:hover img{transform:scale(1.05); filter:saturate(1.08) brightness(1.02)}
  figure.shot figcaption{
    position:absolute; inset:auto 0 0 0; padding:54px 20px 20px; color:var(--ink);
    background:linear-gradient(0deg, rgba(255,247,250,.98), rgba(255,247,250,.86) 55%, transparent);
    transform:translateY(10px); opacity:0; transition:opacity .5s, transform .5s;
  }
  figure.shot:hover figcaption{opacity:1; transform:none}
  figcaption .date{font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:var(--rose-deep); margin-bottom:6px; font-weight:500}
  figcaption .cap{font-family:var(--display); font-style:italic; font-size:21px; line-height:1.25; color:var(--ink)}
  figcaption .loc{font-size:13px; color:var(--muted); margin-top:6px}

  /* ===== EMPTY ===== */
  .empty{text-align:center; padding:12vh 0; color:var(--muted)}
  .empty .mark{font-size:46px; color:var(--rose); animation:beat 2.4s ease-in-out infinite; display:inline-block}
  .empty p{font-family:var(--display); font-style:italic; font-size:25px; margin:18px 0 0; color:var(--ink)}
  .empty a{color:var(--rose-deep); font-weight:500}

  /* ===== FOOTER ===== */
  footer{text-align:center; margin-top:96px; color:var(--muted); font-size:13px; letter-spacing:.08em}
  footer .heart{color:var(--rose-deep)}
  footer a{color:var(--faint); text-decoration:none}

  /* ===== LIGHTBOX ===== */
  .lb{position:fixed; inset:0; z-index:60; display:none; place-items:center; padding:30px;
      background:rgba(255,243,248,.92); backdrop-filter:blur(10px); cursor:zoom-out}
  .lb.open{display:grid; animation:fade .4s ease}
  @keyframes fade{from{opacity:0}to{opacity:1}}
  .lb figure{margin:0; max-width:92vw; max-height:90vh; text-align:center; cursor:auto}
  .lb img{max-width:92vw; max-height:76vh; border-radius:14px; box-shadow:0 24px 60px -20px rgba(196,96,126,.5)}
  .lb .info{margin-top:22px; color:var(--ink)}
  .lb .info .date{font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--rose-deep); font-weight:500}
  .lb .info .cap{font-family:var(--display); font-style:italic; font-size:25px; margin-top:8px; color:var(--ink)}
  .lb .info .loc{font-size:14px; color:var(--muted); margin-top:6px}
  .lb button{position:fixed; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.9); border:1px solid var(--line); color:var(--rose-deep); width:50px; height:50px; border-radius:50%; font-size:22px; cursor:pointer; transition:background .2s, color .2s, border-color .2s; font-weight:500; box-shadow:0 6px 18px -8px rgba(196,96,126,.4)}
  .lb button:hover{background:var(--rose); color:#fff; border-color:var(--rose)}
  .lb .prev{left:22px} .lb .next{right:22px}
  .lb .close{top:22px; right:22px; transform:none; width:44px; height:44px; font-size:20px}
  @media(max-width:560px){.lb .prev,.lb .next{display:none} .lb .close{display:block}}

  @media (prefers-reduced-motion: reduce){
    *{animation-duration:.001ms !important; animation-iteration-count:1 !important}
    body::before{animation:none}
    figure.shot{opacity:1; transform:none}
  }
</style>
</head>
<body>
<div id="hearts" aria-hidden="true"></div>
<div class="shell">

  <header class="hero">
    <div class="kicker"><?= SINCE !== '' ? 'sejak ' . e(SINCE) : 'sebuah ruang kenangan' ?></div>
    <h1><?= e(GALLERY_TITLE) ?></h1>
    <div class="tag"><?= e(GALLERY_TAGLINE) ?></div>
    <div class="divider">
      <svg class="beat" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
    </div>
  </header>

  <?php if (!$photos): ?>
    <div class="empty">
      <div class="mark">&#10084;</div>
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
    dibuat dengan <span class="heart">&#10084;</span> · <a href="admin.php">·</a>
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
  /* ===== Hati melayang — lembut, tidak mengganggu ===== */
  (function () {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const layer = document.getElementById('hearts');
    if (reduce || !layer) return;
    const colors = ['#f6b8cb', '#efa0bb', '#e98aa9', '#f7cdd9', '#e6c4ec'];
    const PATH = 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z';
    const rand = (a, b) => a + Math.random() * (b - a);
    const COUNT = window.innerWidth < 600 ? 9 : 16;

    for (let i = 0; i < COUNT; i++) {
      const s = rand(12, 30);
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('width', s);
      svg.setAttribute('height', s);
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', PATH);
      path.setAttribute('fill', colors[(Math.random() * colors.length) | 0]);
      svg.appendChild(path);

      const dur = rand(15, 28);
      svg.style.left = rand(0, 100) + 'vw';
      svg.style.setProperty('--op', rand(.08, .22).toFixed(2));
      svg.style.setProperty('--dx', rand(-60, 60).toFixed(0) + 'px');
      svg.style.setProperty('--rot', rand(-30, 30).toFixed(0) + 'deg');
      svg.style.filter = Math.random() < .4 ? 'blur(1.5px)' : 'none';
      svg.style.animation = `floatUp ${dur.toFixed(1)}s linear ${(-rand(0, dur)).toFixed(1)}s infinite`;
      layer.appendChild(svg);
    }
  })();

  /* ===== Lightbox ===== */
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
