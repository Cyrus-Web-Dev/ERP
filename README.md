Project ERP — Enterprise Resources Planning (Suite)

====================================================

Inventaris => Pengadaan (Procurement) => Keuangan (Accounting)

SDM (Human Resources) => Payroll \& Budgeting => Keuangan (Accounting)

Semuanya terhubung seperti huruf U, yang pusatnya ada di Keuangan sebagai jantung perusahaan. Suite aplikasi terpisah (decoupled) yang saling terhubung lewat REST API + webhook,

membentuk satu ekosistem ERP untuk operasional perusahaan.



\--------------------------------------------------

1\. Backend Framework

\--------------------------------------------------

Teknologi Utama: Laravel 11+ (tiap aplikasi mandiri)



\--------------------------------------------------

2\. Frontend Framework \& Styling

\--------------------------------------------------

• Inventaris: Blade + Tailwind CSS + SweetAlert2

• PROCURA, MKP (Keuangan), SDM (HRIS), PAYBRIDGE (Payroll):

&#x20; Vue 3 (Composition API) + Inertia.js + Tailwind CSS



\--------------------------------------------------

3\. Database

\--------------------------------------------------

• PostgreSQL 13+/14+ (direkomendasikan untuk SDM, PAYBRIDGE, PROCURA; Inventaris \& MKP mendukung MySQL/PostgreSQL)

• Tiap aplikasi punya database sendiri (isolasi data \& performa)



\--------------------------------------------------

4\. API \& Real-time / Integrasi

\--------------------------------------------------

Teknologi Utama:

• REST API (Laravel Sanctum / token berbagi antar-app)

• Webhook ber-HMAC (notifikasi status antar modul)

• Gmail API (OAuth2) — OTP 2FA, notifikasi, pengiriman PO \& slip gaji

• Argon2id (password hashing di PROCURA / PAYBRIDGE / sebagian app)



\--------------------------------------------------

5\. Library Pendukung Utama

\--------------------------------------------------

Frontend:

&#x09;• ApexCharts / Chart.js — dashboard KPI, tren pendapatan/beban, rekap payroll

&#x09;• SweetAlert2 — konfirmasi aksi di Inventaris

Backend:

&#x09;• Spatie-style / RBAC internal — role \& permission per aplikasi

&#x09;• DomPDF — slip gaji, laporan, label

&#x09;• Gmail API client — email sistem (bukan SMTP biasa)

&#x09;• Queue / Scheduler Laravel — sync periodik, expire, backup, pengingat dokumen



\--------------------------------------------------

Tujuan (Goals)

\--------------------------------------------------



• Digitalisasi Operasional Perusahaan End-to-End:

&#x20; Mengganti proses kertas dan spreadsheet terpisah (aset, pengadaan, jurnal,

&#x20; absensi, gaji) menjadi rangkaian sistem digital yang saling terhubung.



• Transparansi Alur Uang \& Barang:

&#x20; Setiap permintaan barang → pengadaan → pencairan dana → penerimaan aset →

&#x20; jurnal akuntansi → beban gaji tercatat dan bisa dilacak lintas modul.



• Kontrol Internal \& Audit Trail:

&#x20; 2FA, approval berjenjang, periode akuntansi tertutup, ledger/jurnal seimbang,

&#x20; log aktivitas, dan webhook bermuatan tanda tangan agar tidak ada “celah gelap”

&#x20; antar sistem.



• Efisiensi SDM \& Penggajian:

&#x20; Master karyawan, absensi, cuti, KPI, hingga perhitungan BPJS + PPh 21 dan

&#x20; slip gaji terenkripsi dalam satu alur yang terhubung ke keuangan.



\--------------------------------------------------

Manfaat (Benefits)

\--------------------------------------------------



• Bagi Direksi / Pemilik:

&#x20; Visibilitas penuh atas aset, pengadaan, kas, laba rugi, biaya tenaga kerja,

&#x20; dan kepatuhan pajak — dalam dashboard per modul yang konsisten.



• Bagi Admin Operasional (Inventaris, Pengadaan, Keuangan):

&#x20; Alur permintaan → PO → penerimaan → jurnal otomatis mengurangi input ganda

&#x20; dan selisih stok/aset vs buku.



• Bagi HR \& Payroll:

&#x20; Data karyawan, absensi, dan komponen gaji tersinkron; slip PDF + email;

&#x20; anggaran departemen bisa digate saat proses gaji.



• Bagi Staf Lapangan / Karyawan:

&#x20; Portal self-service (cuti, dokumen, pengumuman), peminjaman kendaraan,

&#x20; scan QR aset, tanpa harus antri ke admin untuk hal rutin.



\--------------------------------------------------

Kelebihan (Strengths)

\--------------------------------------------------



• Arsitektur Suite Terpisah (Decoupled):

&#x20; Tiap domain (aset, pengadaan, keuangan, SDM, payroll) adalah aplikasi

&#x20; Laravel mandiri — bisa di-deploy, di-scale, dan di-backup terpisah, tanpa

&#x20; single point of failure monolit raksasa.



• Integrasi Eksplisit lewat Kontrak API + Webhook:

&#x20; Bukan “saling baca tabel database”. Perubahan status dikirim sebagai event

&#x20; bertanda tangan; tiap app tetap punya batas domain yang jelas.



• Keamanan Operasional:

&#x20; 2FA email setiap login (sebagian besar app), rate-limit brute force,

&#x20; argon2id, lampiran bukti hanya lewat auth, periode akuntansi yang mengunci

&#x20; jurnal bulan tertutup.



• Siap Multi-Lingkungan:

&#x20; Bisa dijalankan di localhost (port berbeda per app) maupun production

&#x20; dengan domain/subdomain masing-masing + kredensial Gmail API bersama.



\--------------------------------------------------

Kekurangan (Weaknesses) \& Solusi Antisipasinya

\--------------------------------------------------



• Operasional multi-app (banyak .env, banyak port/database):

&#x09;• Solusi: dokumentasi port \& urutan install; script/composer per folder;

&#x09;  Gmail credentials disalin ke tiap `storage/app/credentials/`.



• Ketergantungan sinkronisasi antar-app:

&#x20; Jika webhook/API down, status bisa tertunda.

&#x09;• Solusi: retry webhook, command sync manual (ProcuraSync, PayBridge

&#x09;  schedule), log \& meja khusus (Meja Inventaris / Meja Keuangan).



• Stack frontend tidak seragam:

&#x20; Inventaris masih Blade; yang lain Inertia+Vue.

&#x09;• Solusi: disengaja (Inventaris migrasi dari PHP native); UI tetap

&#x09;  Tailwind sehingga kesan visual dekat. Unifikasi ke Inertia bisa jadi roadmap.



• Perhitungan pajak \& payroll butuh validasi ahli:

&#x09;• Solusi: uji paralel satu bulan dengan sistem lama; libatkan konsultan

&#x09;  pajak sebelum go-live pembayaran gaji.



\--------------------------------------------------

5 Aplikasi Utama (Modul Suite)

\--------------------------------------------------



1\. INVENTARIS — Manajemen Aset \& Inventori

• Stack: Laravel 11 + Blade + Tailwind + SweetAlert2

• Fungsi Utama:

&#x20; - Auth lengkap (login + 2FA email, register + verifikasi, reset password admin-mediated)

&#x20; - CRUD 6 jenis aset: Elektronik, Non-Elektronik, Perabotan, Kendaraan, Gedung, dll.

&#x20; - Transaksi: penggunaan barang (stok otomatis), peminjaman kendaraan

&#x20; - Laporan gabungan + export PDF/CSV, cetak label QR massal, halaman scan publik

&#x20; - Log aktivitas, manajemen akun, backup DB, persetujuan user baru

&#x20; - Integrasi pengadaan: terima barang dari PROCURA → masuk aset

• Hubungan:

&#x20; - Ke PROCURA: permintaan barang / serah terima hasil pengadaan

&#x20; - Ke MKP: (opsional) nilai aset \& penyusutan untuk jurnal



2\. PROCURA — Procurement \& Pengadaan

• Stack: Laravel 11 + Vue 3 + Inertia + PostgreSQL

• Fungsi Utama:

&#x20; - Alur: permintaan (dari Inventaris) → tinjau → penawaran vendor → pengajuan dana

&#x20;   → keputusan Keuangan → pencairan → PO email ke vendor → terima barang/jasa

&#x20;   → bayar vendor → LPJ → serah terima ke Inventaris

&#x20; - Meja Inventaris \& Meja Keuangan, timeline permintaan, laporan, log aktivitas

&#x20; - Approval internal untuk dana di atas threshold

&#x20; - REST API + webhook ke Inventaris \& MKP

• Hubungan:

&#x20; - Ke Inventaris: sumber permintaan \& tujuan serah terima barang

&#x20; - Ke MKP: pengajuan dana, pencairan, LPJ → jurnal / bill



3\. MKP — Manajemen Keuangan Perusahaan (Accounting)

• Stack: Laravel 11 + Vue 3 + Inertia (+ MySQL/PostgreSQL)

• Fungsi Utama:

&#x20; - Chart of Accounts, jurnal umum (harus seimbang), posting, void

&#x20; - Invoice, Bill, pembayaran, aset tetap \& penyusutan

&#x20; - Periode akuntansi (tutup/buka bulan dengan alasan + log)

&#x20; - Laporan: Neraca Saldo, Laba Rugi, Neraca (+ export PDF/Excel)

&#x20; - Lampiran bukti transaksi (foto/PDF), backup, role Manager/Staff/Viewer

&#x20; - Modul Dana Pengadaan terhubung PROCURA

• Hubungan:

&#x20; - Ke PROCURA: putusan dana, pencairan, LPJ

&#x20; - Ke PAYBRIDGE: terima mapping jurnal beban gaji / hutang gaji

&#x20; - Ke Inventaris: nilai aset (jika diintegrasikan)



4\. SDM — HRIS (Human Resource Information System)

• Stack: Laravel 11 + Vue 3 + Inertia + PostgreSQL

• Fungsi Utama:

&#x20; - Karyawan (master lengkap), departemen \& jabatan

&#x20; - Absensi (check-in/out + koreksi), cuti \& izin (approval berjenjang)

&#x20; - Penilaian kinerja/KPI, rekrutmen (lowongan publik + pipeline)

&#x20; - Pelatihan, manajemen dokumen + pengingat kedaluwarsa

&#x20; - Pengumuman, Employee Self-Service Portal

&#x20; - RBAC, backup, export REST/Excel

&#x20; - (Sebagian instalasi) komponen payroll dasar — detail berat digeser ke PAYBRIDGE

• Hubungan:

&#x20; - Ke PAYBRIDGE: master karyawan, komponen, absensi/kehadiran sebagai input gaji

&#x20; - Ke MKP: (tidak langsung) lewat jurnal dari PAYBRIDGE



5\. PAYBRIDGE (PAYROLL) — Payroll \& Budgeting Terintegrasi

• Stack: Laravel 11 + Vue 3 + Inertia + PostgreSQL + webhook HMAC

• Fungsi Utama:

&#x20; - Komponen gaji, PTKP/TER, BPJS Kesehatan \& Ketenagakerjaan, PPh 21

&#x20; - Proses payroll per periode, slip PDF terenkripsi (email Gmail API)

&#x20; - Budget departemen × bulan × kategori, budget gate saat payroll

&#x20; - Register, rekap PPh/BPJS, biaya departemen, ringkasan pajak tahunan

&#x20; - Dorong jurnal ke MKP; sync data dari SDM

• Hubungan:

&#x20; - Ke SDM: tarik data karyawan \& kehadiran

&#x20; - Ke MKP: mapping jurnal beban gaji / hutang / potongan

&#x20; - Ke Anggaran: gate agar payroll tidak melebihi anggaran disetujui



\--------------------------------------------------

Alur Kerja Integrasi Suite (Localhost)

\--------------------------------------------------



1\. SDM: master karyawan \& struktur organisasi siap; absensi/cuti berjalan.

2\. Inventaris: aset tercatat; staf mengajukan kebutuhan barang (ke PROCURA).

3\. PROCURA: proses pengadaan → pengajuan dana ke MKP.

4\. MKP: setujui/tolak/revisi dana → pencairan tercatat → (nanti) jurnal otomatis.

5\. PROCURA: PO ke vendor (email) → barang diterima → serah terima ke Inventaris.

6\. Inventaris: aset baru masuk stok/register; QR label bisa dicetak.

7\. PAYBRIDGE: tarik data SDM → hitung gaji + BPJS + PPh 21 → slip PDF →

&#x20;  jurnal ke MKP; budget gate mencegah overspend.

8\. Owner/Manager: buka dashboard tiap app (atau laporan gabungan) untuk

&#x20;  melihat aset, kas, laba, dan biaya tenaga kerja secara konsisten.



\--------------------------------------------------

Port \& Database Saran (Local)

\--------------------------------------------------



| Aplikasi     | Folder              | Port saran | Database contoh   |

|--------------|---------------------|------------|-------------------|

| Inventaris   | INVENTARIS\_LARAVEL  | 8000       | inventaris\_db     |

| PROCURA      | PROCURA\_FINAL       | 8001       | procura\_db        |

| MKP          | MKP\_FINAL           | 8002       | mkp\_db            |

| SDM          | SDM\_FINAL           | 8003       | sdm\_hris          |

| PAYBRIDGE    | PAYROLL             | 8004       | paybridge         |



Gmail: salin `credentials.json` + `token.json` yang sama ke

`storage/app/credentials/` di setiap aplikasi yang butuh OTP/email.



\--------------------------------------------------

Roadmap yang Disarankan

\--------------------------------------------------



1\. ✅ Inventaris (aset + QR + integrasi pengadaan)

2\. ✅ PROCURA (alur pengadaan + meja Inventaris/Keuangan)

3\. ✅ MKP (jurnal, laporan, periode, dana pengadaan)

4\. ✅ SDM (HRIS lengkap)

5\. ✅ PAYBRIDGE (payroll + budget + jurnal ke MKP)

6\. Unifikasi UX (Inventaris → Inertia) \& single sign-on opsional

7\. Dashboard konsolidasi lintas-app (read-only dari API)

8\. H2H bank / Coretax resmi / notifikasi WhatsApp (opsional)



\--------------------------------------------------

Ringkasan Positioning

\--------------------------------------------------



ERP ini = \*\*suite 5 aplikasi Laravel\*\* yang membentuk rantai:



&#x20; SDM + PAYBRIDGE  →  orang \& biaya tenaga kerja

&#x20; INVENTARIS + PROCURA  →  barang \& pengadaan

&#x20; MKP  →  uang \& laporan keuangan



### Pola yang sama di setiap folder



```bash

cd <FOLDER\_APP>

composer install

cp .env.example .env

php artisan key:generate

\# buat database kosong, isi DB\_\* di .env

\# (opsional) isi ADMIN\_EMAIL / ADMIN\_PASSWORD sesuai prefix app

php artisan migrate --seed

npm install \&\& npm run build    # Inventaris: npm opsional bila murni Blade

php artisan serve --host=127.0.0.1 --port=<PORT>

```



\### Akun admin pertama (contoh)



| App | Cara |

|-----|------|

| Inventaris | Register + approve, atau seeder / perintah user di README\_SETUP |

| PROCURA | Pendaftar pertama = Manager; atau `php artisan procura:user ... --role=admin` |

| MKP | Lihat README (Manager); seed / perintah setara |

| SDM | `.env` `SDM\_ADMIN\_EMAIL` + `SDM\_ADMIN\_PASSWORD` sebelum seed, atau `php artisan sdm:user ... --role=super-admin` |

| PAYBRIDGE | `.env` `PAY\_ADMIN\_EMAIL` + `PAY\_ADMIN\_PASSWORD`, atau perintah di README |



OTP login: di local sering muncul di log jika Gmail belum siap; production wajib Gmail API valid.  

Uji kirim: `php artisan <prefix>:mail-test emailanda@gmail.com` (prefix `mkp`, `procura`, `sdm`, dll. sesuai app).



\### Scheduler (production)



Jalankan `php artisan schedule:run` tiap menit (cron) \*\*per aplikasi\*\* yang membutuhkannya (expire, sync, backup, pengingat dokumen, retry webhook).



\---



\## Keamanan ringkas



\- Password: bcrypt atau \*\*argon2id\*\* (PROCURA, PAYBRIDGE; cek `php artisan …:hash-status` bila ada).

\- 2FA email hampir di semua app login staf.

\- Rate-limit percobaan login (email+IP).

\- Jurnal MKP harus seimbang; bulan tertutup mengunci create/edit/post/void.

\- Slip gaji PDF bisa dienkripsi; lampiran bukti hanya lewat sesi login.

\- Webhook antar-app memakai \*\*HMAC\*\* — jangan expose secret di frontend.



Checklist production per app ada di README masing-masing dan `docs/KEAMANAN.md` (PAYBRIDGE).



\---



\## Perintah \& dokumen berguna



| Lokasi | Isi |

|--------|-----|

| `\*/README.md` atau `README\_SETUP.md` | Install, role, troubleshooting app itu |

| `PROCURA\_FINAL/docs/API.md` | Kontrak API pengadaan |

| `\*/docs/INTEGRASI\*.md` | Urutan sambung Inventaris ↔ PROCURA ↔ MKP ↔ SDM ↔ PAYBRIDGE |

| `MKP\_FINAL/PANDUAN-FITUR.txt` | Peta halaman \& fungsi akuntansi |

| `PAYROLL/docs/PANDUAN-PAYROLL.md` | Istilah PTKP/TER/BPJS, alur, kontrol fraud |

| `PANDUAN-DATA-SHOWCASE.md` | Data demo / showcase suite |

| `php artisan test` | Di folder app yang menyediakan suite tes |



