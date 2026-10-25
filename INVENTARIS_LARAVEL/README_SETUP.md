# Sistem Inventaris — Laravel (SELESAI, Fase 1–10)

Migrasi penuh dari project PHP native (`inventaris_hardened_v13_FULL.zip`) ke Laravel 11 + Blade + Tailwind + SweetAlert2.

## Fitur yang sudah jadi
- **Auth lengkap**: login (proteksi brute-force 5x/15menit & 20x/24jam), **verifikasi 2 Faktor (2FA) via email setiap login**, register + verifikasi email kode 6 digit, lupa/reset password (admin-mediated)
- **Email dikirim lewat Gmail API (OAuth2)** — sama persis mekanismenya dengan project PHP native, BUKAN SMTP biasa (lihat bagian "Setup Email" di bawah)
- **CRUD 6 modul aset**: Data Elektronik, Data Non Elektronik, Perabotan, Kendaraan, Data Gedung Perusahaan — semua dengan upload foto, SweetAlert2 di setiap aksi, dan pembatasan role (hanya Super Admin/"Pengelola" yang bisa tambah/ubah/hapus, Admin/"Pembaca" hanya bisa lihat)
- **Transaksi**: Penggunaan Barang Elektronik (otomatis kurangi/kembalikan stok), Peminjaman Kendaraan (cek ketersediaan unit, tombol kembalikan)
- **Laporan gabungan**: filter lintas 5 modul + **export PDF** (landscape) dan **CSV**
- **Cetak Label QR massal**: pilih banyak barang sekaligus, QR dibuat di browser, siap print; ada halaman publik (tanpa login) untuk hasil scan
- **Log Aktivitas**: semua aksi tambah/ubah/hapus/approve/reject tercatat dan bisa difilter
- **Manajemen Akun**: ubah status aktif/nonaktif, ubah role, hapus akun (dengan proteksi: tidak bisa hapus/nonaktifkan diri sendiri atau Super Admin terakhir)
- **Backup Database**: generate dump SQL manual (bukan shell_exec/mysqldump, supaya jalan di hosting apapun), simpan di folder privat, bisa diunduh/dihapus dari halaman admin
- **Persetujuan User**: approve/reject pendaftaran baru & permintaan reset password, lengkap dengan email notifikasi

## Penyesuaian yang sengaja dibuat dari versi asli (biar transparan)
1. Masa berlaku kode verifikasi email: 60 detik → **5 menit** (lebih realistis untuk email sungguhan).
2. Menu "Ganti Password DB" tidak dipindahkan — di Laravel kredensial database dikelola lewat `.env`, bukan halaman admin.
3. Form Peminjaman Kendaraan disederhanakan: field HR yang sangat banyak (alamat, no. telp, BPJS, agama, dll) dipangkas jadi nama, NIK, dan tujuan saja.
4. Fitur "Riwayat per-item" (`riwayat_barang.php`) tidak dipindahkan — sudah tercakup oleh halaman Log Aktivitas yang lebih umum.
5. **Jadwal Maintenance** masih berupa halaman placeholder ("segera hadir") — tabelnya sudah dibuat di migration tapi CRUD-nya belum dibangun. Tinggal bilang kalau mau dilanjutkan.
6. Jabatan Pengelola gedung: input **teks bebas** (bukan dropdown), sesuai permintaan terakhir.

## Cara menjalankan di XAMPP / Laragon (Windows)

### 1. Install dependency
```bash
composer install
```

### 2. Environment
```bash
cp .env.example .env
php artisan key:generate
```
Sesuaikan `.env` (database, email SMTP) sesuai kebutuhan.

### 3. Database (PostgreSQL)
Project ini sekarang default-nya pakai **PostgreSQL**, bukan MySQL/XAMPP lagi. Langkah-langkahnya:

1. **Install PostgreSQL** kalau belum ada — download di https://www.postgresql.org/download/windows/ (installer-nya sudah termasuk pgAdmin untuk GUI). Cat alat password `postgres` yang kamu buat saat instalasi.
2. **Aktifkan extension PHP untuk Postgres** — XAMPP sudah menyediakan file-nya, tinggal diaktifkan:
   - Buka `C:\xampp\php\php.ini`
   - Cari baris `;extension=pdo_pgsql` dan `;extension=pgsql`, hapus tanda `;` di depannya
   - Simpan, lalu **restart Apache** dari XAMPP Control Panel
   - Cek sudah aktif: jalankan `php -m | findstr pgsql` di CMD, harus muncul `pdo_pgsql` dan `pgsql`
3. **Buat database kosong** bernama `inventaris_db` lewat pgAdmin (klik kanan "Databases" → Create → Database)
4. Isi `.env` sesuai instalasi Postgres kamu (default sudah cocok kalau ikut instalasi standar):
   ```
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=inventaris_db
   DB_USERNAME=postgres
   DB_PASSWORD=isi_password_postgres_kamu
   ```
5. Jalankan seperti biasa:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```
   Ini membuat semua tabel + 1 akun Super Admin default: **`superadmin`** / **`password123`**

> **Catatan soal fitur Backup Data:** untuk PostgreSQL, file backup yang dihasilkan hanya berisi **data** (bukan struktur tabel) — karena merekonstruksi ulang DDL Postgres secara manual (tipe data, sequence, index) berisiko meleset tanpa `pg_dump`. Cara pakainya: di database tujuan, jalankan `php artisan migrate` dulu untuk membuat strukturnya, baru import file backup ini untuk mengisi datanya. Untuk MySQL, fitur backup tetap dump penuh (struktur + data) seperti sebelumnya.
>
> Masih ingin pakai MySQL/XAMPP seperti sebelumnya? Tinggal ganti `DB_CONNECTION=mysql` di `.env` (lihat komentar di `.env.example`), semua tetap kompatibel — project ini mendukung kedua database sekaligus, tidak perlu pilih salah satu secara permanen.

### 4. Setup Email (Gmail API — WAJIB sebelum coba login/register)
Karena login sekarang selalu minta **2FA lewat email**, dan register butuh kode verifikasi email, kamu **wajib** setup pengirim email dulu sebelum bisa dites. Dua pilihan:

**Opsi A — Pakai ulang kredensial Gmail API yang lama (paling gampang, langsung jalan):**
Salin 2 file yang sudah kamu pakai di project PHP native ke folder ini:
```
storage/app/credentials/token.json
storage/app/credentials/credentials.json
```
Selesai — tidak perlu ubah apapun lagi di `.env`, `MAIL_MAILER=gmailapi` sudah default.

**Opsi B — Belum punya kredensial (setup dari nol):**
Buat OAuth Client di [Google Cloud Console](https://console.cloud.google.com/) (aktifkan Gmail API, buat OAuth Client ID tipe "Desktop app" atau "Web application"), lakukan consent flow untuk dapat `refresh_token`, lalu isi di `.env`:
```
GMAIL_CLIENT_ID=xxxxx
GMAIL_CLIENT_SECRET=xxxxx
GMAIL_REFRESH_TOKEN=xxxxx
```

**Opsi C — Cuma mau testing cepat tanpa Gmail sama sekali:**
Ganti `MAIL_MAILER=smtp` di `.env`, lalu isi kredensial [Mailtrap](https://mailtrap.io) (gratis, tidak perlu App Password Google).

### 5. Jalankan
```bash
php artisan serve
```
Buka `http://127.0.0.1:8000`, atau lewat Apache XAMPP langsung ke folder project (sudah ada `.htaccess` di root yang redirect ke `public/`).

## Struktur folder penting
```
app/Models/              -> semua model Eloquent
app/Http/Controllers/    -> satu controller per modul
app/Http/Controllers/Auth/     -> login, register, reset password
app/Http/Controllers/Admin/    -> approval user & reset password
app/Mail/                -> template email (kode verifikasi, approval, reset)
database/migrations/     -> struktur tabel (nama tabel/kolom sama seperti versi lama)
resources/views/         -> satu folder per modul (index/create/edit/_form)
routes/web.php           -> semua route aplikasi
```

Kalau ada bug atau ada modul yang mau disempurnakan lagi (Jadwal Maintenance, riwayat per-item, dll), tinggal lanjutkan dari sini.


---

## Modul Pengadaan (terhubung ke PROCURA)

Menu baru **Pengadaan → Pengajuan ke Pengadaan** dan **Laporan dari Pengadaan**. Pengajuan barang/jasa dikirim ke sistem Pengadaan (PROCURA) lewat REST API,
kabar perkembangannya masuk lewat webhook, dan barang yang diserahkan bisa langsung dicatat ke data aset.

Setelah `git pull`/salin file: `php artisan migrate`, isi 3 variabel `PROCURA_*` di `.env` — panduan lengkap di **docs/INTEGRASI-PROCURA.md**.
