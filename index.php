<?php
require_once 'shorturl_lib.php';

$message = '';
$error   = '';
$result  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $longUrl = trim($_POST['url'] ?? '');
    $alias   = trim($_POST['alias'] ?? '') ?: null;

    if (empty($longUrl)) {
        $error = 'URL tidak boleh kosong.';
    } elseif (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
        $error = 'URL tidak valid. Pastikan diawali http:// atau https://';
    } else {
        $result = createShortUrl($longUrl, $alias);
        if (isset($result['error'])) { $error = $result['error']; $result = null; }
    }
}

$baseUrl = getBaseUrl();
$recent  = getRecentUrls(5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SnapURL — Pendekkan Tautanmu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0a0a0f; --surface: #111118; --border: #1e1e2e;
            --accent: #6effe8; --text: #e8e8f0; --muted: #6b6b80; --card: #15151f;
        }
        body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; min-height: 100vh; overflow-x: hidden; }
        body::before {
            content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background:
                radial-gradient(ellipse 60% 40% at 15% 20%, rgba(110,255,232,0.07) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at 85% 80%, rgba(255,107,107,0.05) 0%, transparent 60%);
        }
        .grid-bg {
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background-image: linear-gradient(rgba(110,255,232,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(110,255,232,0.025) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .wrap { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; padding: 0 22px; }

        /* Header */
        header { padding: 44px 0 0; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.45rem; letter-spacing: -0.02em; }
        .logo em { color: var(--accent); font-style: normal; }

        /* Hero */
        .hero { padding: 64px 0 40px; text-align: center; }
        .badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(110,255,232,0.07); border: 1px solid rgba(110,255,232,0.2);
            border-radius: 100px; padding: 6px 16px; font-size: 0.73rem;
            font-family: 'DM Mono', monospace; color: var(--accent); letter-spacing: 0.06em; margin-bottom: 26px;
        }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); animation: blink 2s infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
        h1 { font-family: 'Syne', sans-serif; font-size: clamp(2.2rem, 6vw, 3.6rem); font-weight: 800; line-height: 1.05; letter-spacing: -0.03em; margin-bottom: 18px; }
        h1 .accent { display: block; color: var(--accent); }
        .sub { color: var(--muted); font-size: 1rem; font-weight: 300; line-height: 1.65; max-width: 400px; margin: 0 auto; }

        /* Card */
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 30px;
            margin-bottom: 20px; position: relative; overflow: hidden;
        }
        .card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(110,255,232,0.5), transparent);
        }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 0.7rem; font-family: 'DM Mono', monospace; letter-spacing: 0.09em; color: var(--muted); text-transform: uppercase; margin-bottom: 7px; }
        input[type="url"], input[type="text"] {
            width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
            padding: 13px 15px; color: var(--text); font-size: 0.93rem; font-family: 'DM Sans', sans-serif;
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(110,255,232,.1); }
        input::placeholder { color: var(--muted); }
        .alias-row { display: flex; }
        .alias-pfx {
            background: rgba(30,30,46,.8); border: 1px solid var(--border); border-right: none;
            border-radius: 10px 0 0 10px; padding: 13px 11px;
            font-family: 'DM Mono', monospace; font-size: 0.75rem; color: var(--muted); white-space: nowrap;
        }
        .alias-row input { border-radius: 0 10px 10px 0; }
        .btn {
            width: 100%; background: var(--accent); color: #060c0b; border: none; border-radius: 10px;
            padding: 15px; font-size: 0.95rem; font-weight: 700; font-family: 'Syne', sans-serif;
            cursor: pointer; margin-top: 6px; transition: all .2s;
        }
        .btn:hover { background: #90fff0; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(110,255,232,.2); }
        .btn:active { transform: none; }

        /* Result */
        .result {
            background: rgba(110,255,232,.05); border: 1px solid rgba(110,255,232,.25);
            border-radius: 16px; padding: 22px; margin-bottom: 20px;
            animation: up .35s cubic-bezier(.16,1,.3,1);
        }
        @keyframes up { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
        .result-lbl { font-size: 0.68rem; font-family: 'DM Mono', monospace; letter-spacing: .1em; color: var(--accent); text-transform: uppercase; margin-bottom: 10px; }
        .result-row { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 11px 14px; }
        .result-url { flex: 1; font-family: 'DM Mono', monospace; font-size: .95rem; color: var(--accent); text-decoration: none; word-break: break-all; }
        .btn-copy { background: none; border: 1px solid var(--accent); border-radius: 6px; padding: 5px 13px; color: var(--accent); font-size: .72rem; font-family: 'DM Mono', monospace; cursor: pointer; white-space: nowrap; transition: all .2s; }
        .btn-copy:hover, .btn-copy.ok { background: var(--accent); color: #060c0b; }
        .result-note { font-size: .75rem; color: var(--muted); margin-top: 10px; }
        .result-note strong { color: var(--text); }

        /* Error */
        .err { background: rgba(255,107,107,.08); border: 1px solid rgba(255,107,107,.3); border-radius: 12px; padding: 13px 16px; color: #ff9090; font-size: .88rem; margin-bottom: 18px; animation: up .3s ease; }

        /* Recent */
        .section-lbl { font-size: .68rem; font-family: 'DM Mono', monospace; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 12px; }
        .recent { margin-bottom: 48px; }
        .r-item { background: var(--card); border: 1px solid var(--border); border-radius: 11px; padding: 12px 16px; margin-bottom: 7px; display: flex; align-items: center; gap: 12px; transition: border-color .2s; }
        .r-item:hover { border-color: rgba(110,255,232,.2); }
        .r-code { font-family: 'DM Mono', monospace; font-size: .82rem; color: var(--accent); text-decoration: none; white-space: nowrap; }
        .r-dest { flex: 1; font-size: .78rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        footer { border-top: 1px solid var(--border); padding: 22px 0; text-align: center; margin-bottom: 20px; }
        footer p { font-size: .75rem; color: var(--muted); }

        @media(max-width:500px){ .alias-pfx{display:none} .alias-row input{border-radius:10px} }
    </style>
</head>
<body>
<div class="grid-bg"></div>
<div class="wrap">
    <header>
        <div class="logo">Snap<em>URL</em></div>
    </header>

    <section class="hero">
        <div class="badge">URL Shortener · Gratis & Cepat</div>
        <h1>Pendekkan URL-mu<span class="accent">dalam satu klik.</span></h1>
        <p class="sub">Buat tautan pendek yang rapi dan mudah dibagikan ke mana saja.</p>
    </section>

    <?php if ($error): ?>
    <div class="err">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
    <div class="result">
        <div class="result-lbl">✓ Tautan berhasil diperpendek</div>
        <div class="result-row">
            <a class="result-url" href="<?= htmlspecialchars($result['short_url']) ?>" target="_blank">
                <?= htmlspecialchars($result['short_url']) ?>
            </a>
            <button class="btn-copy" onclick="doCopy('<?= htmlspecialchars($result['short_url'], ENT_QUOTES) ?>', this)">Salin</button>
        </div>
        <div class="result-note">Kunjungan akan menampilkan halaman tunggu <strong>8 detik</strong> sebelum diarahkan ke tujuan.</div>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label for="url">URL Panjang</label>
                <input type="url" id="url" name="url" required placeholder="https://contoh.com/halaman-yang-sangat-panjang"
                       value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="alias">Alias Kustom <span style="font-style:italic;opacity:.6">(opsional)</span></label>
                <div class="alias-row">
                    <span class="alias-pfx"><?= htmlspecialchars($baseUrl) ?>/</span>
                    <input type="text" id="alias" name="alias" placeholder="nama-saya"
                           pattern="[a-zA-Z0-9_-]+" title="Huruf, angka, - dan _ saja"
                           value="<?= htmlspecialchars($_POST['alias'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn">Perpendek URL →</button>
        </form>
    </div>

    <?php if (!empty($recent)): ?>
    <div class="section-lbl">URL Terbaru</div>
    <div class="recent">
        <?php foreach ($recent as $item): ?>
        <div class="r-item">
            <a class="r-code" href="<?= htmlspecialchars($baseUrl . '/' . $item['code']) ?>" target="_blank">/<?= htmlspecialchars($item['code']) ?></a>
            <span class="r-dest" title="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['url']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <footer><p>SnapURL — Hosted di <a href="https://vercel.com" style="color:var(--accent);text-decoration:none">Vercel</a></p></footer>
</div>
<script>
function doCopy(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        btn.textContent = '✓ Disalin!'; btn.classList.add('ok');
        setTimeout(() => { btn.textContent = 'Salin'; btn.classList.remove('ok'); }, 2200);
    });
}
</script>
</body>
</html>
