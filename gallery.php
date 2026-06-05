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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#fff7fa">
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

  /* Aurora lembut — hanya digeser (transform), tidak di-blur ulang -> ringan */
  body::before{
    content:""; position:fixed; inset:-25%; z-index:0; pointer-events:none;
    background:
      radial-gradient(620px 620px at 20% 30%, rgba(244,203,216,.35), transparent 60%),
      radial-gradient(560px 560px at 80% 70%, rgba(232,200,240,.30), transparent 60%);
    filter:blur(20px); will-change:transform;
    animation:drift 30s ease-in-out infinite alternate;
  }
  @keyframes drift{
    from{transform:translate3d(-1.5%,-1%,0)}
    to{transform:translate3d(2%,1.5%,0)}
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

  .shell{position:relative; z-index:3; max-width:1060px; margin:0 auto; padding:0 clamp(16px,4vw,40px) 100px}

  /* ===== HERO ===== */
  .hero{text-align:center; padding:9vh 10px 5.5vh}
  .kicker{font-size:11px; letter-spacing:.5em; text-transform:uppercase; color:var(--rose-deep); margin-bottom:22px; font-weight:500; opacity:0; animation:soft 1.2s ease .1s forwards}
  .hero h1{
    font-family:var(--display); font-weight:500; font-size:clamp(46px,8.5vw,84px); line-height:1;
    margin:0; letter-spacing:1px; color:var(--ink);
    opacity:0; animation:soft 1.3s ease .25s forwards;
  }
  .hero .tag{
    font-family:var(--script); font-size:clamp(24px,4.2vw,38px); color:var(--rose-deep);
    margin-top:12px; line-height:1.1; opacity:0; animation:soft 1.4s ease .45s forwards;
  }
  @keyframes soft{from{opacity:0; transform:translateY(14px)}to{opacity:1; transform:none}}

  .divider{display:flex; align-items:center; justify-content:center; gap:18px; margin:26px auto 0; max-width:240px; color:var(--rose); opacity:0; animation:soft 1.5s ease .65s forwards}
  .divider::before,.divider::after{content:""; height:1px; flex:1; background:linear-gradient(90deg,transparent,var(--line),transparent)}
  .divider .beat{width:20px; height:20px; color:var(--rose); animation:beat 2.4s ease-in-out infinite}
  @keyframes beat{0%,100%{transform:scale(1)}14%{transform:scale(1.18)}28%{transform:scale(1)}42%{transform:scale(1.12)}}

  .count-line{text-align:center; color:var(--muted); font-size:12px; letter-spacing:.22em; text-transform:uppercase; margin:0 0 22px}

  /* ===== GRID ala Instagram — kotak rapat, kecil-kecil ===== */
  .grid{display:grid; grid-template-columns:repeat(3,1fr); gap:6px}
  @media(min-width:600px){.grid{grid-template-columns:repeat(4,1fr); gap:9px}}
  @media(min-width:860px){.grid{grid-template-columns:repeat(5,1fr); gap:10px}}
  @media(min-width:1120px){.grid{grid-template-columns:repeat(6,1fr); gap:11px}}

  figure.shot{
    margin:0; position:relative; aspect-ratio:1/1; overflow:hidden;
    border-radius:7px; cursor:pointer; background:var(--bg-soft);
    box-shadow:0 6px 18px -14px rgba(196,96,126,.5);
    -webkit-tap-highlight-color:transparent;
    opacity:1; animation:rise .8s cubic-bezier(.2,.7,.2,1) backwards;
  }
  @media(min-width:560px){figure.shot{border-radius:12px}}
  @keyframes rise{from{opacity:0; transform:translateY(16px) scale(.98)}}
  figure.shot img{
    display:block; width:100%; height:100%; object-fit:cover;
    transition:transform .6s cubic-bezier(.2,.7,.2,1); background:var(--bg-soft);
  }
  /* Overlay caption hanya di perangkat yang punya hover (desktop) */
  .shot .ov{
    position:absolute; inset:0; display:flex; align-items:flex-end; padding:14px;
    background:linear-gradient(0deg, rgba(74,44,56,.62), rgba(74,44,56,.12) 50%, transparent);
    color:#fff7fa; opacity:0; transition:opacity .35s; pointer-events:none;
  }
  .shot .ov .date{font-size:10px; letter-spacing:.16em; text-transform:uppercase; opacity:.9}
  .shot .ov .cap{font-family:var(--display); font-style:italic; font-size:16px; line-height:1.2; margin-top:2px}
  @media(hover:hover){
    figure.shot:hover{box-shadow:0 18px 40px -18px rgba(196,96,126,.5)}
    figure.shot:hover img{transform:scale(1.06)}
    figure.shot:hover .ov{opacity:1}
  }

  /* ===== EMPTY ===== */
  .empty{text-align:center; padding:12vh 0; color:var(--muted)}
  .empty .mark{font-size:46px; color:var(--rose); animation:beat 2.4s ease-in-out infinite; display:inline-block}
  .empty p{font-family:var(--display); font-style:italic; font-size:25px; margin:18px 0 0; color:var(--ink)}
  .empty a{color:var(--rose-deep); font-weight:500}

  /* ===== FOOTER ===== */
  footer{text-align:center; margin-top:70px; color:var(--muted); font-size:13px; letter-spacing:.08em}
  footer .heart{color:var(--rose-deep)}
  footer a{color:var(--faint); text-decoration:none}

  /* ===== LIGHTBOX / DETAIL (bisa di-scroll) ===== */
  .lb{position:fixed; inset:0; z-index:60; display:none; background:rgba(58,32,42,.86)}
  @supports ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){
    .lb{-webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px)}
  }
  .lb.open{display:block; animation:fade .3s ease}
  @keyframes fade{from{opacity:0}to{opacity:1}}

  .lb-scroll{
    position:absolute; inset:0; overflow-y:auto; -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
    padding:max(20px,env(safe-area-inset-top)) 16px max(30px,env(safe-area-inset-bottom));
  }
  .lb-fig{margin:0; min-height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:18px}
  .lb-fig img{
    max-width:min(94vw,1100px); width:auto; height:auto; border-radius:12px;
    box-shadow:0 24px 60px -22px rgba(0,0,0,.6); background:rgba(255,255,255,.06);
  }
  .lb.loading .lb-fig img{min-height:40vh; min-width:60vw}
  .lb .info{text-align:center; color:#fff7fa; max-width:560px; padding-bottom:6px}
  .lb .info .date{font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--rose-soft); font-weight:500}
  .lb .info .cap{font-family:var(--display); font-style:italic; font-size:24px; margin-top:8px; line-height:1.3}
  .lb .info .loc{font-size:14px; color:#e9cdd7; margin-top:6px}

  .lb-btn{
    position:fixed; z-index:2; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.28);
    color:#fff; border-radius:50%; cursor:pointer; display:grid; place-items:center;
    -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px);
    transition:background .2s, transform .15s;
  }
  .lb-btn:hover{background:rgba(255,255,255,.28)}
  .lb-btn:active{transform:scale(.92)}
  .lb .close{top:max(14px,env(safe-area-inset-top)); right:14px; width:44px; height:44px; font-size:24px}
  .lb .nav{top:50%; transform:translateY(-50%); width:48px; height:48px; font-size:26px}
  .lb .prev{left:12px} .lb .next{right:12px}
  .lb .counter{
    position:fixed; z-index:2; left:50%; transform:translateX(-50%);
    top:max(20px,env(safe-area-inset-top)); color:#fff7fa; font-size:13px; letter-spacing:.1em;
    background:rgba(0,0,0,.25); padding:6px 14px; border-radius:999px;
  }
  @media(max-width:560px){
    .lb .nav{display:none}            /* di HP cukup geser (swipe) kiri/kanan */
    .lb-fig img{max-width:100vw; border-radius:10px}
  }

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
    <p class="count-line"><?= count($photos) ?> kenangan</p>
    <div class="grid">
      <?php foreach ($photos as $i => $p):
        $file  = rawurlencode($p['filename']);
        $cap   = e($p['caption']);
        $date  = e(pretty_date($p['date'] ?? ''));
        $loc   = e($p['location'] ?? '');
        $t320  = 'thumb.php?f=' . $file . '&s=320';
        $t640  = 'thumb.php?f=' . $file . '&s=640';
        $full  = 'thumb.php?f=' . $file . '&w=1600';
        $delay = min($i * 0.04, .8);
      ?>
        <figure class="shot" style="animation-delay:<?= $delay ?>s"
                data-full="<?= e($full) ?>" data-cap="<?= $cap ?>" data-date="<?= $date ?>" data-loc="<?= $loc ?>">
          <img src="<?= e($t320) ?>"
               srcset="<?= e($t320) ?> 320w, <?= e($t640) ?> 640w"
               sizes="(min-width:1120px) 165px, (min-width:860px) 19vw, (min-width:600px) 24vw, 32vw"
               alt="<?= $cap ?: 'kenangan' ?>" loading="lazy" decoding="async">
          <?php if ($cap || $date): ?>
          <div class="ov">
            <div>
              <?php if ($date): ?><div class="date"><?= $date ?></div><?php endif; ?>
              <?php if ($cap): ?><div class="cap"><?= $cap ?></div><?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <footer>
    <p>Dirawat dengan <span class="heart">&#10084;</span> oleh <?= e(GALLERY_TITLE) ?></p>
  </footer>
</div>

<!-- Lightbox / Detail -->
<div class="lb" id="lb" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="counter" id="lbCount"></div>
  <button class="lb-btn close" id="lbClose" aria-label="Tutup">&times;</button>
  <button class="lb-btn nav prev" id="lbPrev" aria-label="Sebelumnya">&#8249;</button>
  <button class="lb-btn nav next" id="lbNext" aria-label="Berikutnya">&#8250;</button>
  <div class="lb-scroll" id="lbScroll">
    <figure class="lb-fig">
      <img id="lbImg" src="" alt="">
      <figcaption class="info">
        <div class="date" id="lbDate"></div>
        <div class="cap" id="lbCap"></div>
        <div class="loc" id="lbLoc"></div>
      </figcaption>
    </figure>
  </div>
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
    const COUNT = window.innerWidth < 600 ? 7 : 13;
    const NS = 'http://www.w3.org/2000/svg';

    for (let i = 0; i < COUNT; i++) {
      const s = rand(12, 28);
      const svg = document.createElementNS(NS, 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('width', s);
      svg.setAttribute('height', s);
      const path = document.createElementNS(NS, 'path');
      path.setAttribute('d', PATH);
      path.setAttribute('fill', colors[(Math.random() * colors.length) | 0]);
      svg.appendChild(path);

      const dur = rand(16, 28);
      svg.style.left = rand(0, 100) + 'vw';
      svg.style.setProperty('--op', rand(.08, .2).toFixed(2));
      svg.style.setProperty('--dx', rand(-60, 60).toFixed(0) + 'px');
      svg.style.setProperty('--rot', rand(-30, 30).toFixed(0) + 'deg');
      svg.style.filter = Math.random() < .4 ? 'blur(1.5px)' : 'none';
      svg.style.animation = `floatUp ${dur.toFixed(1)}s linear ${(-rand(0, dur)).toFixed(1)}s infinite`;
      layer.appendChild(svg);
    }
  })();

  /* ===== Lightbox / Detail ===== */
  const shots   = [...document.querySelectorAll('.shot')];
  const lb      = document.getElementById('lb');
  const lbScroll= document.getElementById('lbScroll');
  const lbImg   = document.getElementById('lbImg');
  const lbCap   = document.getElementById('lbCap');
  const lbDate  = document.getElementById('lbDate');
  const lbLoc   = document.getElementById('lbLoc');
  const lbCount = document.getElementById('lbCount');
  let idx = 0;

  function preload(i) {
    const f = shots[i];
    if (f) { const im = new Image(); im.src = f.dataset.full; }
  }
  function render(i) {
    const f = shots[i];
    if (!f) return;
    idx = i;
    lb.classList.add('loading');
    lbImg.src = f.dataset.full;
    lbCap.textContent  = f.dataset.cap || '';
    lbDate.textContent = f.dataset.date || '';
    lbLoc.textContent  = f.dataset.loc ? '⌖ ' + f.dataset.loc : '';
    lbCount.textContent = (i + 1) + ' / ' + shots.length;
    lbScroll.scrollTop = 0;
    preload((i + 1) % shots.length);
    preload((i - 1 + shots.length) % shots.length);
  }
  lbImg.addEventListener('load', () => lb.classList.remove('loading'));

  function open(i)  { render(i); lb.classList.add('open'); lb.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function close()  { lb.classList.remove('open'); lb.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
  const next = () => render((idx + 1) % shots.length);
  const prev = () => render((idx - 1 + shots.length) % shots.length);

  shots.forEach((f, i) => f.addEventListener('click', () => open(i)));
  document.getElementById('lbClose').addEventListener('click', close);
  document.getElementById('lbNext').addEventListener('click', e => { e.stopPropagation(); next(); });
  document.getElementById('lbPrev').addEventListener('click', e => { e.stopPropagation(); prev(); });
  /* Tutup saat klik area kosong; biarkan gambar/caption tetap bisa diklik & di-scroll */
  lbScroll.addEventListener('click', e => { if (e.target === lbScroll || e.target.classList.contains('lb-fig')) close(); });

  document.addEventListener('keydown', e => {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
  });

  /* Geser (swipe) kiri/kanan di HP -> foto berikutnya/sebelumnya.
     Geser vertikal dibiarkan untuk men-scroll detail. */
  let tsx = 0, tsy = 0, tt = 0;
  lbScroll.addEventListener('touchstart', e => {
    const t = e.touches[0]; tsx = t.clientX; tsy = t.clientY; tt = Date.now();
  }, { passive: true });
  lbScroll.addEventListener('touchend', e => {
    const t = e.changedTouches[0];
    const dx = t.clientX - tsx, dy = t.clientY - tsy;
    if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.7 && (Date.now() - tt) < 650) {
      dx < 0 ? next() : prev();
    }
  }, { passive: true });

  /* ===== Panel galeri saling bertukar tempat — lembut, hemat & acak =====
     Grid kotak seragam: cukup gerakkan 2 ubin yang ditukar (transform = GPU),
     tanpa mengukur ulang seluruh grid -> tidak bikin browser berat. */
  (function () {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const grid = document.querySelector('.grid');
    if (reduce || !grid || grid.children.length < 4) return;

    const SWAP_EVERY = 5200;
    const SLIDE_MS   = 700;
    const EASE       = 'cubic-bezier(.2,.7,.2,1)';
    let paused = false, scrollT;

    grid.addEventListener('pointerenter', () => { paused = true; });
    grid.addEventListener('pointerleave', () => { paused = false; });
    window.addEventListener('scroll', () => {
      paused = true; clearTimeout(scrollT);
      scrollT = setTimeout(() => { paused = false; }, 800);
    }, { passive: true });

    const rnd = n => (Math.random() * n) | 0;

    function swapNodes(a, b) {
      const t = document.createComment('');
      a.replaceWith(t); b.replaceWith(a); t.replaceWith(b);
    }
    function visible() {
      const h = innerHeight, out = [];
      for (const el of grid.children) {
        const r = el.getBoundingClientRect();
        if (r.bottom > 0 && r.top < h) out.push(el);
      }
      return out;
    }

    function tick() {
      if (paused || document.hidden || lb.classList.contains('open')) return;
      const vis = visible();
      if (vis.length < 2) return;

      let i = rnd(vis.length), j = rnd(vis.length);
      if (i === j) j = (j + 1) % vis.length;
      const a = vis[i], b = vis[j];

      const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
      swapNodes(a, b);                       // ubin seragam -> posisi baru = posisi lama mitranya
      const dx = ra.left - rb.left, dy = ra.top - rb.top;
      if (!dx && !dy) return;

      a.style.transition = b.style.transition = 'none';
      a.style.transform = `translate(${dx}px,${dy}px)`;
      b.style.transform = `translate(${-dx}px,${-dy}px)`;
      a.style.zIndex = b.style.zIndex = '5';

      requestAnimationFrame(() => requestAnimationFrame(() => {
        a.style.transition = b.style.transition = `transform ${SLIDE_MS}ms ${EASE}`;
        a.style.transform = b.style.transform = '';
      }));
    }

    grid.addEventListener('transitionend', e => {
      if (e.propertyName !== 'transform') return;
      const el = e.target;
      if (!el.classList || !el.classList.contains('shot')) return;
      el.style.transition = ''; el.style.transform = ''; el.style.zIndex = '';
    });

    setInterval(tick, SWAP_EVERY);
  })();
</script>
</body>
</html>
