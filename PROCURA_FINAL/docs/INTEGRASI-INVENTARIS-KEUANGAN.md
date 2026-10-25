# Tahap berikutnya: menyambungkan Inventaris & Keuangan ke PROCURA

PROCURA sudah siap dipanggil. Yang perlu ditambahkan di dua sistem lama nanti hanyalah **satu halaman + satu service HTTP** di masing-masing.

## Di proyek Inventaris (Laravel Blade) — ✅ SUDAH DIBUAT
Halaman *Pengajuan ke Pengadaan* dan *Laporan dari Pengadaan* sudah tersedia di paket `inventaris-laravel-PROCURA`; lihat `docs/INTEGRASI-PROCURA.md` di sana. Rincian rancangan di bawah tetap berlaku sebagai referensi.

1. Buat klien API `Sistem Inventaris` di menu **Integrasi API** PROCURA (preset sudah menyalakan scope yang dibutuhkan). Isi `.env` Inventaris:
   `PROCURA_URL=http://127.0.0.1:8000/api/v1` dan `PROCURA_TOKEN=pcr_...`
2. Halaman **"Pengajuan ke Pengadaan"**: form barang → `POST /requests` (`external_ref` = nomor permintaan internal, `Idempotency-Key` = nomor yang sama).
3. Halaman **"Laporan dari Pengadaan"**: daftar dari `GET /requests?updated_since=…`, atau terima webhook `request.status_changed` / `handover.created` di satu route
   (verifikasi `X-Procura-Signature`). Tombol **Konfirmasi diterima** → `POST /handovers/{no}/confirm`, lalu barang dicatat sebagai aset/persediaan.

## Di proyek Keuangan (MKP) — ✅ SUDAH DIBUAT
Menu *Dana Pengadaan* sudah tersedia di paket `mkp-app-PROCURA`; lihat `docs/INTEGRASI-PROCURA.md` di sana (termasuk jurnal pencairan & LPJ). Rancangan di bawah tetap berlaku sebagai referensi.

1. Klien API `Sistem Keuangan` (scope `funding:read`, `funding:decide`) + webhook `funding.submitted` dan `funding.settled`.
2. Halaman **"Pengajuan Dana dari Pengadaan"**: terima webhook (atau tarik `GET /funding?status=submitted`), tampilkan, lalu tombol **Setujui / Tolak / Minta revisi**
   → `POST /funding/{no}/decision`. Keputusan otomatis muncul di Meja Keuangan PROCURA dan mengabari Inventaris.
3. Saat kas keluar: catat jurnal (Dr Uang Muka Pengadaan / Kr Kas-Bank) lalu `POST /funding/{no}/disbursement`.
4. Saat webhook `funding.settled` tiba: jurnal LPJ (Dr Beban/Persediaan, Kr Uang Muka Pengadaan; sisa dana kembali ke Kas).

Selama tahap itu belum dikerjakan, Meja Inventaris dan Meja Keuangan di PROCURA tetap berfungsi penuh secara mandiri
(user dengan izin `finance.decide` / `finance.disburse` / `request.create` memerankan divisi terkait).
