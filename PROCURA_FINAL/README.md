# PROCURA — Sistem Procurement & Pengadaan

Bagian dari suite ERP bersama **Inventaris** dan **Keuangan (MKP)**. Stack: Laravel 11 · Vue 3 · Inertia · PostgreSQL · REST API · Gmail API · Argon2id.

## Alur
```
Inventaris minta → Pengadaan tinjau → cari penawaran vendor → ajukan dana ke Keuangan
   → Keuangan putuskan (setuju / tolak / revisi) → dana cair → PO ke vendor (email Gmail)
   → barang/jasa diterima → bayar vendor → LPJ dana → serah terima ke Inventaris → selesai
```
Halaman khusus: **Meja Inventaris** (laporan masuk/keluar ↔ Inventaris, serah terima) dan **Meja Keuangan** (pengajuan, keputusan, pencairan, LPJ).
Tiap langkah tercatat di lini masa permintaan, buku laporan, log aktivitas, dan dikirim sebagai webhook ke sistem lain.

## Instalasi (Windows/Laragon/XAMPP atau Linux)
Kebutuhan: PHP ≥ 8.2 (ekstensi `pdo_pgsql`, `sodium`, `mbstring`, `gd`), Composer, Node 18+, PostgreSQL 14+.

1. Buat database kosong: `CREATE DATABASE procura_db;`
2. `composer install`
3. `copy .env.example .env` lalu isi `DB_PASSWORD` (dan `PROCURA_ADMIN_EMAIL` + `PROCURA_ADMIN_PASSWORD` bila ingin login tanpa OTP email)
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `npm install && npm run build`   (pengembangan: `npm run dev`)
7. `php artisan serve` → buka **http://127.0.0.1:8000** (pakai alamat ini persis, sama dengan `APP_URL`)

Pendaftar pertama otomatis menjadi Manager; pendaftar berikutnya menunggu persetujuan Manager.
Akun lewat CLI: `php artisan procura:user email@contoh.id --name="Nama" --password="rahasia123" --role=admin`.

### Gmail API
Salin `credentials.json` + `token.json` dari proyek Inventaris/MKP ke `storage/app/credentials/` (sama seperti proyek sebelumnya), atau isi `GMAIL_CLIENT_ID/SECRET/REFRESH_TOKEN`.
Dipakai untuk OTP login/registrasi, notifikasi akun, dan **pengiriman PO ke vendor**. Uji: `php artisan procura:mail-test alamat@anda.com`.

### Argon2id
`HASH_DRIVER=argon2id` (memory 64 MB, time 4). Cek: `php artisan procura:hash-status`. Hash bcrypt lama otomatis di-upgrade saat login berhasil.

## Peran & hak akses
Manager (semua akses) · Staff (hak per orang, dicentang Manager saat menyetujui; ada preset posisi: Purchasing, Admin Pengadaan, Penerimaan Barang, Penghubung Inventaris, Penghubung Keuangan…) · Viewer (baca saja).
Pengajuan dana ≥ `PROCURA_INTERNAL_APPROVAL_THRESHOLD` (bawaan Rp 25 juta) wajib disetujui internal (izin `funding.approve`) sebelum sampai ke Keuangan.

## REST API & integrasi
Lihat `docs/API.md` dan `docs/INTEGRASI-INVENTARIS-KEUANGAN.md`. Klien dibuat di menu **Integrasi API** (token hash SHA-256, scope, idempotency key, webhook HMAC-SHA256).

## Tes
`php artisan test` (tes alur lengkap + API; memakai SQLite in-memory sesuai `phpunit.xml`).
