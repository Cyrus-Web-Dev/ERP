# Panduan Data Showcase (5 ERP)
ShowcaseSeeder ini hanya data CONTOH
Tiap project mendapat satu file: `database/seeders/ShowcaseSeeder.php`.

## Cara pakai (sama untuk kelima project)
1. Salin file ke folder project yang sesuai (struktur folder di `outputs/` sudah mengikuti project):

   | Folder di outputs | Project asal |
   |---|---|
   | `paybridge/`  | paybridge.zip |
   | `procura/`    | procura-sistem-pengadaan.zip |
   | `mkp/`        | mkp-app-fixed.zip |
   | `sdm-hris/`   | sdm-hris.zip |
   | `inventaris/` | inventaris-laravel-PROCURA.zip |

2. Pastikan `.env` sudah memakai PostgreSQL dan migrasi sudah jalan:
   `php artisan migrate`  (project baru: `php artisan migrate --seed`)
3. Di terminal/CMD, dari folder project, jalankan:
   `php artisan db:seed --class=ShowcaseSeeder`
4. Bila muncul "class not found": `composer dump-autoload`, lalu ulangi langkah 3.
5. Di akhir proses, terminal menampilkan tabel akun demo (email/username + kata sandi `Demo@12345`).

Aman dijalankan berulang (data tidak digandakan). Di mode production Laravel meminta `--force`.

## Halaman yang terisi
- PAYBRIDGE : Karyawan, Kontrak, Putaran Payroll (+dana, setoran pajak/BPJS, batch bank), Input & Kasbon, Anggaran
- PROCURA   : Vendor, Permintaan, Meja Keuangan (pengajuan dana), Purchase Order, Katalog
- MKP       : Invoice, Bill, Jurnal Umum, Aset Tetap, Anggaran
- SDM-HRIS  : Karyawan, Absensi, Cuti, Payroll, Rekrutmen (+ Kinerja, Pelatihan, Pengumuman, Libur)
- INVENTARIS: Barang Elektronik, Kendaraan & Peminjaman, Perabotan, Gedung, Pengajuan Pengadaan (+ Alat, Penggunaan, Maintenance)

## Catatan penting
- Belum pernah dijalankan di PHP sungguhan (lingkungan pembuat tidak punya PHP). Coba dulu di database salinan/uji,
  jalankan `php -l database/seeders/ShowcaseSeeder.php`, dan perbaiki bila ada error lalu kabari saya.
- PAYBRIDGE & SDM memakai 10 karyawan yang sama (EMP-101..EMP-110) agar konsisten saat dipamerkan bersama.
- Inventaris: jangan jalankan `php artisan procura:sync` saat demo, karena nomor PR contoh tidak ada di PROCURA.
- Email (Gmail API) dan webhook tidak dikirim saat seeding.
