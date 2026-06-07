# SnapURL — PHP Short URL untuk Vercel

## File Struktur

```
shorturl/
├── index.php          # Halaman utama (form buat URL)
├── redirect.php       # Halaman interstitial 8 detik + slot iklan
├── shorturl_lib.php   # Library inti (buat URL, ambil URL, storage)
└── vercel.json        # Konfigurasi routing Vercel
```

## Deploy ke Vercel

### 1. Pasang Vercel KV (Upstash Redis)

Di Vercel dashboard:
- Buka project → **Storage** → **Create Database** → pilih **KV**
- Setelah dibuat, klik **Connect to Project**
- Environment variable berikut otomatis ditambahkan:
  - `KV_REST_API_URL`
  - `KV_REST_API_TOKEN`

> **Tanpa KV**, data disimpan di `/tmp/shorturl_data.json` (hanya untuk dev lokal, tidak persisten di Vercel).

### 2. Deploy

```bash
npm i -g vercel
cd shorturl/
vercel --prod
```

## Konfigurasi Iklan

Edit bagian **`$adBanner`** dan **`$adBox`** di `redirect.php`:

```php
// Banner atas (728×90)
$adBanner = [
    'image_url' => 'https://cdn.kamu.com/banner.jpg',
    'link_url'  => 'https://sponsor.com',
    'alt'       => 'Nama Sponsor',
];

// Kotak samping
$adBox = [
    // Tampilkan gambar:
    'image_url' => 'https://cdn.kamu.com/kotak.jpg',
    'link_url'  => 'https://sponsor.com',
    // Atau tampilkan teks (jika image_url dikosongkan):
    'title' => 'Judul Iklan',
    'body'  => 'Deskripsi singkat produk atau layanan.',
    'cta'   => 'Kunjungi Sekarang',
];
```

## Cara Kerja Redirect

1. User buka `https://domain.com/abc123`
2. Vercel route → `redirect.php?code=abc123`
3. Halaman interstitial tampil dengan:
   - **Ring countdown 8 detik** (animasi CSS)
   - **Progress bar** di atas halaman
   - **Slot iklan banner** (atas) dan **slot iklan kotak** (samping)
   - Tombol "Lanjut" aktif setelah 8 detik
4. Otomatis redirect ke URL tujuan

## Alias Kustom

User bisa memilih alias sendiri, contoh:
- URL: `https://nama-domain.com/promo-lebaran`

Aturan alias: huruf, angka, `-` dan `_`, panjang 2–30 karakter.
