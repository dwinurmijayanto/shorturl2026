<?php
/**
 * redirect.php — Halaman interstitial dengan countdown 8 detik + slot iklan
 * Route: /{code} → /redirect.php?code={code}
 */
require_once __DIR__ . '/shorturl_lib.php';

$code = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['code'] ?? '');

if (empty($code)) { header('Location: /'); exit; }

$entry = getUrlByCode($code);
if (!$entry) notFound();

$destUrl     = $entry['url'];
$destDisplay = parse_url($destUrl, PHP_URL_HOST) ?: $destUrl;

// ── Konfigurasi Iklan ────────────────────────────────────────────────────────
// Ganti nilai-nilai ini sesuai kebutuhan:

$adBanner = [
    // Iklan banner atas (gambar). Kosongkan string untuk menonaktifkan.
    'image_url'  => '',                          // contoh: 'https://cdn.example.com/banner.jpg'
    'link_url'   => '',                          // contoh: 'https://sponsor.com'
    'alt'        => 'Advertisement',
    'width'      => 728,
    'height'     => 90,
];

$adBox = [
    // Iklan kotak samping / tengah (teks atau gambar).
    'image_url'  => '',                          // biarkan kosong untuk mode teks
    'link_url'   => '',
    'alt'        => 'Advertisement',
    // Mode teks (dipakai jika image_url kosong):
    'title'      => '📢 Iklan Anda Di Sini',
    'body'       => 'Pasang iklan produk atau layanan Anda di halaman ini. Hubungi kami untuk info harga.',
    'cta'        => 'Pelajari Lebih Lanjut',
];

$countdown = 8; // detik
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mengarahkan… — SnapURL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0a0a0f; --surface: #111118; --border: #1e1e2e;
            --accent: #6effe8; --text: #e8e8f0; --muted: #6b6b80; --card: #15151f;
            --count: <?= $countdown ?>;
        }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'DM Sans', sans-serif; min-height: 100vh;
            display: flex; flex-direction: column; align-items: center;
            overflow-x: hidden;
        }
        body::before {
            content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background:
                radial-gradient(ellipse 70% 50% at 50% 0%, rgba(110,255,232,0.07) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 80% 90%, rgba(255,107,107,0.04) 0%, transparent 60%);
        }
        .grid-bg {
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background-image: linear-gradient(rgba(110,255,232,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(110,255,232,0.025) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* ── Progress bar atas ── */
        #progress-bar {
            position: fixed; top: 0; left: 0; height: 3px;
            background: linear-gradient(90deg, var(--accent), #5be3f8);
            width: 100%; transform-origin: left;
            animation: shrink <?= $countdown ?>s linear forwards;
            z-index: 100;
            box-shadow: 0 0 12px rgba(110,255,232,.6);
        }
        @keyframes shrink { from{width:100%} to{width:0%} }

        /* ── Layout ── */
        .page { position: relative; z-index: 1; width: 100%; max-width: 780px; padding: 0 20px; display: flex; flex-direction: column; align-items: center; }

        /* ── Top Ad Banner (728×90 leaderboard) ── */
        .ad-banner {
            width: 100%; max-width: 728px; height: 90px;
            background: var(--card); border: 1px dashed rgba(110,255,232,.2);
            border-radius: 10px; overflow: hidden; margin: 28px 0 0;
            display: flex; align-items: center; justify-content: center;
        }
        .ad-banner a { display: block; width: 100%; height: 100%; }
        .ad-banner img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ad-placeholder-lbl { font-size: .7rem; font-family: 'DM Mono', monospace; letter-spacing: .1em; color: rgba(110,255,232,.3); text-transform: uppercase; }

        /* ── Main box ── */
        .main { width: 100%; display: flex; gap: 20px; margin-top: 24px; align-items: flex-start; flex-wrap: wrap; }

        /* ── Countdown card ── */
        .countdown-card {
            flex: 1; min-width: 280px;
            background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 36px 32px;
            position: relative; overflow: hidden; text-align: center;
        }
        .countdown-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(110,255,232,.5), transparent);
        }

        /* Ring timer */
        .ring-wrap { position: relative; width: 130px; height: 130px; margin: 0 auto 28px; }
        .ring-wrap svg { transform: rotate(-90deg); }
        .ring-bg { fill: none; stroke: var(--border); stroke-width: 6; }
        .ring-fg {
            fill: none; stroke: var(--accent); stroke-width: 6; stroke-linecap: round;
            stroke-dasharray: 345;
            stroke-dashoffset: 0;
            animation: ring <?= $countdown ?>s linear forwards;
            filter: drop-shadow(0 0 6px rgba(110,255,232,.5));
        }
        @keyframes ring { from{stroke-dashoffset:0} to{stroke-dashoffset:345} }
        .ring-num {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif; font-size: 2.6rem; font-weight: 800; color: var(--accent);
            line-height: 1;
        }

        .redirect-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; }
        .redirect-dest {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
            padding: 8px 14px; font-family: 'DM Mono', monospace; font-size: .78rem; color: var(--muted);
            margin-bottom: 24px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .dest-icon { width: 16px; height: 16px; border-radius: 3px; flex-shrink: 0; }

        .btn-skip {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--accent); color: #060c0b; border: none; border-radius: 10px;
            padding: 13px 28px; font-size: .9rem; font-weight: 700; font-family: 'Syne', sans-serif;
            cursor: not-allowed; opacity: .45; transition: all .3s; width: 100%; justify-content: center;
            text-decoration: none;
        }
        .btn-skip.ready { opacity: 1; cursor: pointer; animation: pop .4s cubic-bezier(.16,1,.3,1); }
        .btn-skip.ready:hover { background: #90fff0; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(110,255,232,.25); }
        @keyframes pop { from{transform:scale(.95)} to{transform:scale(1)} }

        .skip-note { font-size: .72rem; color: var(--muted); margin-top: 12px; }
        .skip-note span { color: var(--accent); font-family: 'DM Mono', monospace; font-weight: 500; }

        /* ── Ad box (sidebar) ── */
        .ad-box {
            width: 260px; flex-shrink: 0;
            background: var(--card); border: 1px dashed rgba(110,255,232,.2); border-radius: 16px; overflow: hidden;
        }
        .ad-box-inner {
            padding: 22px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px;
            min-height: 240px; justify-content: center;
        }
        .ad-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ad-box-lbl { font-size: .62rem; font-family: 'DM Mono', monospace; letter-spacing: .12em; color: rgba(110,255,232,.3); text-transform: uppercase; margin-bottom: 2px; }
        .ad-box-icon { font-size: 2rem; opacity: .4; }
        .ad-box-title { font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 700; color: var(--text); line-height: 1.3; }
        .ad-box-body { font-size: .8rem; color: var(--muted); line-height: 1.55; }
        .ad-box-cta {
            display: inline-block; background: rgba(110,255,232,.1); border: 1px solid rgba(110,255,232,.25);
            border-radius: 7px; padding: 8px 18px; font-size: .75rem; font-family: 'Syne', sans-serif;
            font-weight: 700; color: var(--accent); text-decoration: none; margin-top: 4px; transition: all .2s;
        }
        .ad-box-cta:hover { background: rgba(110,255,232,.2); }

        /* ── Bottom info ── */
        .bottom { margin: 20px 0 36px; text-align: center; }
        .bottom p { font-size: .75rem; color: var(--muted); }
        .bottom a { color: var(--accent); text-decoration: none; }

        @media(max-width: 620px) {
            .ad-box { width: 100%; }
            .ad-banner { height: 60px; }
        }
    </style>
</head>
<body>
<div class="grid-bg"></div>
<div id="progress-bar"></div>

<div class="page">

    <!-- ── SLOT IKLAN: Banner Atas (728×90) ─────────────────────────────── -->
    <div class="ad-banner">
        <?php if (!empty($adBanner['image_url'])): ?>
            <a href="<?= htmlspecialchars($adBanner['link_url']) ?>" target="_blank" rel="noopener sponsored">
                <img src="<?= htmlspecialchars($adBanner['image_url']) ?>"
                     alt="<?= htmlspecialchars($adBanner['alt']) ?>"
                     width="<?= $adBanner['width'] ?>" height="<?= $adBanner['height'] ?>">
            </a>
        <?php else: ?>
            <!-- GANTI: isi $adBanner['image_url'] di redirect.php untuk menampilkan banner -->
            <span class="ad-placeholder-lbl">[ Slot Iklan Banner 728 × 90 ]</span>
        <?php endif; ?>
    </div>
    <!-- ── AKHIR SLOT IKLAN BANNER ── -->

    <div class="main">

        <!-- ── Countdown Card ── -->
        <div class="countdown-card">
            <div class="ring-wrap">
                <svg viewBox="0 0 120 120" width="130" height="130">
                    <circle class="ring-bg" cx="60" cy="60" r="55"/>
                    <circle class="ring-fg" cx="60" cy="60" r="55"/>
                </svg>
                <div class="ring-num" id="num"><?= $countdown ?></div>
            </div>

            <div class="redirect-title">Anda akan diarahkan ke:</div>
            <div class="redirect-dest">
                <img class="dest-icon"
                     src="https://www.google.com/s2/favicons?sz=32&domain=<?= urlencode($destDisplay) ?>"
                     alt="" onerror="this.style.display='none'">
                <?= htmlspecialchars($destDisplay) ?>
            </div>

            <a id="skip-btn" class="btn-skip" href="<?= htmlspecialchars($destUrl) ?>">
                <span id="skip-lbl">Menunggu…</span>
                <span>→</span>
            </a>
            <div class="skip-note">Otomatis redirect dalam <span id="skip-sec"><?= $countdown ?></span> detik</div>
        </div>

        <!-- ── SLOT IKLAN: Kotak Samping (260×auto) ─────────────────────── -->
        <div class="ad-box">
            <?php if (!empty($adBox['image_url'])): ?>
                <a href="<?= htmlspecialchars($adBox['link_url']) ?>" target="_blank" rel="noopener sponsored" style="display:block">
                    <img src="<?= htmlspecialchars($adBox['image_url']) ?>" alt="<?= htmlspecialchars($adBox['alt']) ?>">
                </a>
            <?php else: ?>
                <!-- GANTI: isi $adBox['image_url'] atau edit teks di $adBox di redirect.php -->
                <div class="ad-box-inner">
                    <div class="ad-box-lbl">Iklan</div>
                    <div class="ad-box-icon">📣</div>
                    <div class="ad-box-title"><?= htmlspecialchars($adBox['title']) ?></div>
                    <div class="ad-box-body"><?= htmlspecialchars($adBox['body']) ?></div>
                    <?php if (!empty($adBox['link_url'])): ?>
                        <a class="ad-box-cta" href="<?= htmlspecialchars($adBox['link_url']) ?>" target="_blank" rel="noopener sponsored">
                            <?= htmlspecialchars($adBox['cta']) ?>
                        </a>
                    <?php else: ?>
                        <span class="ad-box-cta" style="opacity:.5;cursor:default">Slot Tersedia</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- ── AKHIR SLOT IKLAN KOTAK ── -->

    </div>

    <div class="bottom">
        <p>Layanan oleh <a href="/">SnapURL</a> · <a href="/">Buat short URL-mu</a></p>
    </div>
</div>

<script>
(function () {
    const DEST   = <?= json_encode($destUrl) ?>;
    const TOTAL  = <?= $countdown ?>;
    let   left   = TOTAL;

    const numEl  = document.getElementById('num');
    const secEl  = document.getElementById('skip-sec');
    const btn    = document.getElementById('skip-btn');
    const lblEl  = document.getElementById('skip-lbl');

    const tick = setInterval(() => {
        left--;
        if (numEl)  numEl.textContent  = left;
        if (secEl)  secEl.textContent  = left;

        if (left <= 0) {
            clearInterval(tick);
            // Aktifkan tombol
            btn.classList.add('ready');
            lblEl.textContent = 'Lanjut ke Tujuan';
            // Redirect otomatis
            window.location.href = DEST;
        }
    }, 1000);

    // Cegah klik sebelum waktunya
    btn.addEventListener('click', function (e) {
        if (!btn.classList.contains('ready')) e.preventDefault();
    });
})();
</script>
</body>
</html>
