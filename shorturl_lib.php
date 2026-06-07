<?php
/**
 * shorturl_lib.php — Library inti SnapURL (tanpa statistik klik)
 * Storage: Vercel KV (Upstash Redis) atau JSON file fallback (dev lokal)
 */

define('CODE_LENGTH', 6);
define('DATA_FILE', '/tmp/shorturl_data.json');

// ─── Base URL ────────────────────────────────────────────────────────────────
function getBaseUrl(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return rtrim($proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');
}

// ─── Deteksi storage ─────────────────────────────────────────────────────────
function useKv(): bool {
    return !empty(getenv('KV_REST_API_URL')) && !empty(getenv('KV_REST_API_TOKEN'));
}

// ─── Vercel KV helpers ───────────────────────────────────────────────────────
function kvRequest(string $method, string $path, mixed $body = null): mixed {
    $url   = rtrim(getenv('KV_REST_API_URL'), '/') . $path;
    $token = getenv('KV_REST_API_TOKEN');
    $ch    = curl_init($url);
    $opts  = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

function kvGet(string $key): mixed {
    $r = kvRequest('GET', '/get/' . rawurlencode($key));
    return isset($r['result']) ? json_decode($r['result'], true) : null;
}

function kvSet(string $key, mixed $value): void {
    kvRequest('POST', '/set/' . rawurlencode($key), $value);
}

function kvLPush(string $key, string $val): void {
    kvRequest('POST', '/lpush/' . rawurlencode($key), $val);
}

function kvLRange(string $key, int $s, int $e): array {
    $r = kvRequest('GET', '/lrange/' . rawurlencode($key) . '/' . $s . '/' . $e);
    return $r['result'] ?? [];
}

// ─── JSON file fallback ──────────────────────────────────────────────────────
function loadData(): array {
    if (!file_exists(DATA_FILE)) return ['urls' => [], 'recent' => []];
    return json_decode(file_get_contents(DATA_FILE), true) ?: ['urls' => [], 'recent' => []];
}

function saveData(array $data): void {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ─── Generate kode unik ──────────────────────────────────────────────────────
function generateCode(): string {
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < CODE_LENGTH; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// ─── Buat Short URL ──────────────────────────────────────────────────────────
function createShortUrl(string $longUrl, ?string $alias = null): array {
    if ($alias !== null && !preg_match('/^[a-zA-Z0-9_-]{2,30}$/', $alias)) {
        return ['error' => 'Alias hanya boleh huruf, angka, - dan _ (2–30 karakter).'];
    }

    return useKv()
        ? createShortUrlKv($longUrl, $alias)
        : createShortUrlFile($longUrl, $alias);
}

function createShortUrlKv(string $longUrl, ?string $alias): array {
    if ($alias) {
        if (kvGet('url:' . $alias)) {
            return ['error' => 'Alias "' . $alias . '" sudah digunakan.'];
        }
        $code = $alias;
    } else {
        $existCode = kvGet('rev:' . md5($longUrl));
        if ($existCode && kvGet('url:' . $existCode)) {
            $entry = kvGet('url:' . $existCode);
            return array_merge($entry, ['short_url' => getBaseUrl() . '/' . $existCode]);
        }
        do { $code = generateCode(); } while (kvGet('url:' . $code));
    }

    $entry = ['code' => $code, 'url' => $longUrl, 'created_at' => date('c')];
    kvSet('url:' . $code, $entry);
    kvSet('rev:' . md5($longUrl), $code);
    kvLPush('recent', $code);

    return array_merge($entry, ['short_url' => getBaseUrl() . '/' . $code]);
}

function createShortUrlFile(string $longUrl, ?string $alias): array {
    $data = loadData();

    if ($alias) {
        if (isset($data['urls'][$alias])) {
            return ['error' => 'Alias "' . $alias . '" sudah digunakan.'];
        }
        $code = $alias;
    } else {
        foreach ($data['urls'] as $c => $e) {
            if ($e['url'] === $longUrl) {
                return array_merge($e, ['short_url' => getBaseUrl() . '/' . $c]);
            }
        }
        do { $code = generateCode(); } while (isset($data['urls'][$code]));
    }

    $entry = ['code' => $code, 'url' => $longUrl, 'created_at' => date('c')];
    $data['urls'][$code] = $entry;
    array_unshift($data['recent'], $code);
    $data['recent'] = array_slice($data['recent'], 0, 50);
    saveData($data);

    return array_merge($entry, ['short_url' => getBaseUrl() . '/' . $code]);
}

// ─── Ambil URL berdasarkan kode ──────────────────────────────────────────────
function getUrlByCode(string $code): ?array {
    if (useKv()) return kvGet('url:' . $code) ?: null;
    $data = loadData();
    return $data['urls'][$code] ?? null;
}

// ─── URL terbaru ─────────────────────────────────────────────────────────────
function getRecentUrls(int $limit = 5): array {
    if (useKv()) {
        $codes  = kvLRange('recent', 0, $limit - 1);
        $result = [];
        foreach ($codes as $c) {
            $e = kvGet('url:' . $c);
            if ($e) $result[] = $e;
        }
        return $result;
    }
    $data   = loadData();
    $result = [];
    foreach (array_slice($data['recent'], 0, $limit) as $c) {
        if (isset($data['urls'][$c])) $result[] = $data['urls'][$c];
    }
    return $result;
}

// ─── 404 page ────────────────────────────────────────────────────────────────
function notFound(): void {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>404 — SnapURL</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@800&family=DM+Sans:wght@400&display=swap" rel="stylesheet">
    <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#0a0a0f;color:#e8e8f0;font-family:'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}
    h1{font-family:'Syne',sans-serif;font-size:5rem;font-weight:800;color:#6effe8;line-height:1}
    p{color:#6b6b80;margin:12px 0 28px}
    a{display:inline-block;background:#6effe8;color:#0a0a0f;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-family:'Syne',sans-serif}
    </style></head><body>
    <div><h1>404</h1><p>Tautan tidak ditemukan atau sudah kadaluarsa.</p><a href="/">← Buat URL Baru</a></div>
    </body></html>
    <?php
    exit;
}
