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

  .shell{position:relative; z-index:3; max-width:1100px; margin:0 auto; padding:0 clamp(16px,4vw,38px) 100px}

  /* ===== HERO ===== */
  .hero{text-align:center; padding:9vh 10px 5.5vh}
  .kicker{font-size:11px; letter-spacing:.5em; text-transform:uppercase; color:var(--rose-deep); margin-bottom:22px; font-weight:500; opacity:0; animation:soft 1.2s ease .1s forwards}
  .hero h1{
    font-family:var(--display); font-weight:500; font-size:clamp(46px,8.5vw,86px); line-height:1;
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

  /* ===== GRID =====
     Mobile: kotak rapat ala Instagram (kecil-kecil).
     Desktop (>=700px): masonry — tinggi foto beragam, agak besar & berpola. */
  .grid{display:grid; grid-template-columns:repeat(3,1fr); gap:6px}
  @media(min-width:460px){.grid{grid-template-columns:repeat(4,1fr); gap:8px}}

  figure.shot{
    margin:0; position:relative; aspect-ratio:1/1; overflow:hidden;
    border-radius:8px; cursor:pointer; background:var(--bg-soft);
    box-shadow:0 6px 18px -14px rgba(196,96,126,.5);
    -webkit-tap-highlight-color:transparent;
    opacity:1; animation:rise .8s cubic-bezier(.2,.7,.2,1) backwards;
  }
  @keyframes rise{from{opacity:0; transform:translateY(16px) scale(.98)}}
  figure.shot img{
    display:block; width:100%; height:100%; object-fit:cover; background:var(--bg-soft);
    transition:transform .6s cubic-bezier(.2,.7,.2,1);
  }

  /* Masonry untuk layar lebar */
  @media(min-width:700px){
    .grid{display:block; column-count:3; column-gap:16px; grid-template-columns:none}
    figure.shot{aspect-ratio:auto; width:100%; display:block; margin:0 0 16px; break-inside:avoid; border-radius:14px}
    figure.shot img{height:auto}
  }
  @media(min-width:1040px){.grid{column-count:4}}

  /* Overlay caption — hanya di perangkat ber-hover (desktop) */
  .shot .ov{
    position:absolute; inset:0; display:flex; align-items:flex-end; padding:16px;
    background:linear-gradient(0deg, rgba(74,44,56,.66), rgba(74,44,56,.10) 52%, transparent);
    color:#fff7fa; opacity:0; transition:opacity .35s; pointer-events:none;
  }
  .shot .ov .date{font-size:10px; letter-spacing:.16em; text-transform:uppercase; opacity:.9}
  .shot .ov .cap{font-family:var(--display); font-style:italic; font-size:18px; line-height:1.25; margin-top:3px}
  @media(hover:hover){
    figure.shot:hover{box-shadow:0 18px 40px -18px rgba(196,96,126,.5)}
    figure.shot:hover img{transform:scale(1.05)}
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

  /* ===== LIGHTBOX / DETAIL (fit + bisa di-scroll) ===== */
  .lb{position:fixed; inset:0; z-index:60; display:none; background:rgba(58,32,42,.9)}
  @supports ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){
    .lb{-webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px)}
  }
  .lb.open{display:block; animation:fade .3s ease}
  @keyframes fade{from{opacity:0}to{opacity:1}}

  .lb-scroll{
    position:absolute; inset:0; overflow-y:auto; -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
    padding:max(18px,env(safe-area-inset-top)) 16px max(28px,env(safe-area-inset-bottom));
  }
  .lb-fig{margin:0 auto; min-height:86vh; max-width:940px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px}
  .lb-fig img{
    max-width:min(92vw,940px); max-height:80vh; width:auto; height:auto; border-radius:12px;
    box-shadow:0 24px 60px -22px rgba(0,0,0,.6); background:rgba(255,255,255,.06);
  }
  .lb.loading .lb-fig img{min-height:36vh; min-width:54vw}
  .lb .info{text-align:center; color:#fff7fa; max-width:560px}
  .lb .info .date{font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--rose-soft); font-weight:500}
  .lb .info .cap{font-family:var(--display); font-style:italic; font-size:23px; margin-top:8px; line-height:1.3}
  .lb .info .loc{font-size:14px; color:#e9cdd7; margin-top:6px}

  /* Komentar di dalam detail */
  .lb-comments{max-width:560px; margin:6px auto 0; color:#fff7fa}
  .lb-comments h3{font-family:var(--display); font-style:italic; font-weight:500; font-size:21px; text-align:center; margin:0 0 16px; color:var(--rose-soft)}
  .cmt-list{display:flex; flex-direction:column; gap:10px; margin-bottom:18px}
  .cmt{background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.16); border-radius:12px; padding:10px 14px}
  .cmt .cmt-head{display:flex; align-items:baseline; gap:8px; flex-wrap:wrap}
  .cmt .who{font-size:13px; font-weight:500; color:#fff}
  .cmt .when{font-size:11px; color:#e2c4ce; letter-spacing:.03em}
  .cmt .text{font-size:14px; margin-top:4px; color:#f6e6ec; white-space:pre-wrap; word-break:break-word; line-height:1.5}
  .cmt-empty{font-size:13px; color:#e2c4ce; text-align:center; font-style:italic; padding:6px 0}
  .cmt-form{display:flex; flex-direction:column; gap:10px}
  .cmt-form .hp{position:absolute; left:-9999px; width:1px; height:1px; opacity:0; pointer-events:none}
  .cmt-form input[type=text],.cmt-form textarea{
    width:100%; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.24); color:#fff;
    border-radius:10px; padding:10px 13px; font-family:var(--ui); font-size:14px; font-weight:300; outline:none;
    transition:border-color .2s, background .2s;
  }
  .cmt-form input:focus,.cmt-form textarea:focus{border-color:var(--rose); background:rgba(255,255,255,.18)}
  .cmt-form input::placeholder,.cmt-form textarea::placeholder{color:#e3c6d0}
  .cmt-form textarea{min-height:62px; resize:vertical}
  .cmt-form button{align-self:flex-end; cursor:pointer; border:1px solid var(--rose); background:var(--rose); color:#fff; border-radius:999px; padding:9px 24px; font-family:var(--ui); font-size:14px; font-weight:500; transition:background .2s, transform .15s}
  .cmt-form button:hover{background:var(--rose-deep)}
  .cmt-form button:disabled{opacity:.55; cursor:default}

  .lb-btn{
    position:fixed; z-index:2; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.28);
    color:#fff; border-radius:50%; cursor:pointer; display:grid; place-items:center;
    -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px);
    transition:background .2s;
  }
  .lb-btn:hover{background:rgba(255,255,255,.28)}
  .lb .close{top:max(14px,env(safe-area-inset-top)); right:14px; width:44px; height:44px; font-size:24px}
  .lb .nav{top:50%; transform:translateY(-50%); width:48px; height:48px; font-size:26px}
  .lb .prev{left:12px} .lb .next{right:12px}
  .lb .counter{
    position:fixed; z-index:2; left:50%; transform:translateX(-50%);
    top:max(18px,env(safe-area-inset-top)); color:#fff7fa; font-size:13px; letter-spacing:.1em;
    background:rgba(0,0,0,.28); padding:6px 14px; border-radius:999px;
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
        $t400  = 'thumb.php?f=' . $file . '&w=400';
        $t760  = 'thumb.php?f=' . $file . '&w=760';
        $full  = 'thumb.php?f=' . $file . '&m=1500';
        $delay = min($i * 0.04, .8);
      ?>
        <figure class="shot" style="animation-delay:<?= $delay ?>s"
                data-id="<?= e($p['id']) ?>" data-full="<?= e($full) ?>"
                data-cap="<?= $cap ?>" data-date="<?= $date ?>" data-loc="<?= $loc ?>">
          <img src="<?= e($t400) ?>"
               srcset="<?= e($t400) ?> 400w, <?= e($t760) ?> 760w"
               sizes="(min-width:1040px) 250px, (min-width:700px) 31vw, (min-width:460px) 24vw, 32vw"
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
    <section class="lb-comments" id="lbComments">
      <h3>Komentar</h3>
      <div class="cmt-list" id="cmtList"></div>
      <form class="cmt-form" id="cmtForm" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="photo" id="cmtPhoto" value="">
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="text" name="name" id="cmtName" placeholder="Nama (opsional)" maxlength="40">
        <textarea name="body" id="cmtBody" placeholder="Tulis sesuatu yang manis…" maxlength="600" required></textarea>
        <button type="submit">Kirim</button>
      </form>
    </section>
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

  /* ===== Lightbox / Detail + Komentar ===== */
  const shots   = [...document.querySelectorAll('.shot')];
  const lb      = document.getElementById('lb');
  const lbScroll= document.getElementById('lbScroll');
  const lbImg   = document.getElementById('lbImg');
  const lbCap   = document.getElementById('lbCap');
  const lbDate  = document.getElementById('lbDate');
  const lbLoc   = document.getElementById('lbLoc');
  const lbCount = document.getElementById('lbCount');
  const cmtList = document.getElementById('cmtList');
  const cmtForm = document.getElementById('cmtForm');
  const cmtPhoto= document.getElementById('cmtPhoto');
  const cmtBody = document.getElementById('cmtBody');
  let idx = 0, cmtReq = 0;

  function preload(i) {
    const f = shots[i];
    if (f) { const im = new Image(); im.src = f.dataset.full; }
  }
  function timeAgo(iso) {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return 'baru saja';
    const m = Math.floor(s / 60); if (m < 60) return m + ' menit lalu';
    const h = Math.floor(m / 60); if (h < 24) return h + ' jam lalu';
    const dd = Math.floor(h / 24); if (dd < 30) return dd + ' hari lalu';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function addComment(c, atTop) {
    const wrap = document.createElement('div'); wrap.className = 'cmt';
    const head = document.createElement('div'); head.className = 'cmt-head';
    const who = document.createElement('span'); who.className = 'who'; who.textContent = c.name || 'Anonim';
    const when = document.createElement('span'); when.className = 'when'; when.textContent = timeAgo(c.at);
    head.append(who, when);
    const text = document.createElement('div'); text.className = 'text'; text.textContent = c.body || '';
    wrap.append(head, text);
    if (atTop && cmtList.firstChild) cmtList.insertBefore(wrap, cmtList.firstChild);
    else cmtList.appendChild(wrap);
  }
  function loadComments(pid) {
    const mine = ++cmtReq;
    cmtList.innerHTML = '<div class="cmt-empty">Memuat…</div>';
    fetch('comment.php?photo=' + encodeURIComponent(pid))
      .then(r => r.json())
      .then(d => {
        if (mine !== cmtReq) return;          // sudah pindah foto -> abaikan
        cmtList.innerHTML = '';
        const list = (d && d.comments) || [];
        if (!list.length) { cmtList.innerHTML = '<div class="cmt-empty">Belum ada komentar. Jadilah yang pertama ♡</div>'; return; }
        list.forEach(c => addComment(c, false));
      })
      .catch(() => { if (mine === cmtReq) cmtList.innerHTML = '<div class="cmt-empty">Gagal memuat komentar.</div>'; });
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
    cmtPhoto.value = f.dataset.id || '';
    lbScroll.scrollTop = 0;
    loadComments(f.dataset.id || '');
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
  /* Klik area kosong (latar/figur) -> tutup; gambar, caption, & komentar tetap aman */
  lbScroll.addEventListener('click', e => {
    if (e.target === lbScroll || e.target.classList.contains('lb-fig')) close();
  });

  document.addEventListener('keydown', e => {
    if (!lb.classList.contains('open')) return;
    const ae = document.activeElement;
    const typing = ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA');
    if (e.key === 'Escape') close();
    else if (!typing && e.key === 'ArrowRight') next();
    else if (!typing && e.key === 'ArrowLeft') prev();
  });

  /* Geser (swipe) kiri/kanan di HP -> foto lain. Geser vertikal -> scroll detail. */
  let tsx = 0, tsy = 0, tt = 0;
  lbScroll.addEventListener('touchstart', e => {
    const t = e.touches[0]; tsx = t.clientX; tsy = t.clientY; tt = Date.now();
  }, { passive: true });
  lbScroll.addEventListener('touchend', e => {
    if (e.target.closest('.lb-comments')) return;     // jangan ganggu interaksi komentar
    const t = e.changedTouches[0];
    const dx = t.clientX - tsx, dy = t.clientY - tsy;
    if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.7 && (Date.now() - tt) < 650) {
      dx < 0 ? next() : prev();
    }
  }, { passive: true });

  /* Kirim komentar */
  cmtForm.addEventListener('submit', e => {
    e.preventDefault();
    if (!cmtBody.value.trim()) return;
    const btn = cmtForm.querySelector('button');
    btn.disabled = true;
    fetch('comment.php', { method: 'POST', body: new FormData(cmtForm) })
      .then(r => r.json())
      .then(d => {
        if (d && d.ok && d.comment) {
          const empty = cmtList.querySelector('.cmt-empty');
          if (empty) empty.remove();
          addComment(d.comment, true);
          cmtBody.value = '';
        } else {
          alert('Komentar gagal dikirim. Coba lagi ya.');
        }
      })
      .catch(() => alert('Komentar gagal dikirim. Periksa koneksi.'))
      .finally(() => { btn.disabled = false; });
  });

  /* ===== Panel galeri saling bertukar tempat — lembut & acak (FLIP) =====
     Bekerja untuk grid kotak (HP) maupun masonry (desktop). Dijeda saat
     hover, scroll, tab tersembunyi, atau lightbox terbuka. */
  (function () {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const grid = document.querySelector('.grid');
    if (reduce || !grid || grid.children.length < 3) return;

    const SWAP_EVERY = 5200, SLIDE_MS = 800, EASE = 'cubic-bezier(.2,.7,.2,1)';
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

    function tick() {
      if (paused || document.hidden || lb.classList.contains('open')) return;
      const kids = [...grid.children];
      if (kids.length < 3) return;
      let i = rnd(kids.length), j = rnd(kids.length);
      if (i === j) j = (j + 1) % kids.length;

      const first = kids.map(el => el.getBoundingClientRect());   // First
      swapNodes(kids[i], kids[j]);

      let moved = false;                                          // Invert
      kids.forEach((el, k) => {
        const last = el.getBoundingClientRect();
        const dx = first[k].left - last.left, dy = first[k].top - last.top;
        if (!dx && !dy) return;
        el.style.transition = 'none';
        el.style.transform = `translate(${dx}px, ${dy}px)`;
        el.style.zIndex = '5';
        moved = true;
      });
      if (!moved) return;

      requestAnimationFrame(() => requestAnimationFrame(() => {   // Play
        kids.forEach(el => {
          if (!el.style.transform) return;
          el.style.transition = `transform ${SLIDE_MS}ms ${EASE}`;
          el.style.transform = '';
        });
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
