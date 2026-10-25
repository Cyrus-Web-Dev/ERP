# Integrasi Inventaris ⇄ PROCURA (Pengadaan)

Halaman baru di menu **Pengadaan**:

| Halaman | Fungsi |
|---|---|
| **Pengajuan ke Pengadaan** (`/pengajuan-pengadaan`) | Membuat pengajuan barang/jasa → dikirim ke PROCURA lewat REST API. Melacak posisinya di "jalur pengadaan", melihat perkembangan (dana, pesanan), **mengonfirmasi serah terima**, lalu **mencatat barang ke data aset** (form aset terisi otomatis). |
| **Laporan dari Pengadaan** (`/laporan-pengadaan`) | Kotak masuk kabar dari Pengadaan (perkembangan, pesanan, penerimaan, serah terima) + catatan keluar dari Inventaris. Lencana merah di menu = kabar belum dibaca. |

Hak akses mengikuti pola lama: `super_admin` boleh membuat/mengonfirmasi/menulis catatan, `admin` hanya membaca.

## Pengaturan (sekali saja)

1. **Di PROCURA** (login sebagai Manager) → menu **Integrasi API** → *Klien API baru* → pilih sistem **Inventaris** (izin & event sudah terisi otomatis).
   Isi *Alamat webhook* dengan alamat Inventaris, contoh XAMPP:
   `http://localhost/inventaris-laravel/public/api/procura/webhook`
   Simpan, lalu **salin token** (`pcr_…`) dan **rahasia webhook** (`whsec_…`) — keduanya hanya tampil sekali.
2. **Di Inventaris**, isi `.env`:
   ```
   PROCURA_URL=http://127.0.0.1:8000/api/v1
   PROCURA_TOKEN=pcr_...
   PROCURA_WEBHOOK_SECRET=whsec_...
   ```
   lalu `php artisan config:clear`.
3. `php artisan migrate` (membuat tabel `pengajuan_pengadaan`, `pengajuan_pengadaan_item`, `laporan_pengadaan`).
4. Di PROCURA, klik **Uji webhook** pada klien Inventaris → harus muncul "Ping diterima klien".

Perintah pelengkap webhook (tarik status tiap 10 menit): `php artisan schedule:work` (atau cron `* * * * * php artisan schedule:run`).
Manual: `php artisan procura:sync --semua`.

## Alur data

```
Inventaris                                   PROCURA
 Buat Pengajuan ── POST /requests ─────────▶  Permintaan baru (external_ref = PGJ-…)
                                              … tinjau → penawaran → dana → PO → terima …
 Laporan masuk  ◀── webhook (HMAC) ─────────  request.status_changed / purchase_order.sent /
 + jalur bergerak                             goods.received / handover.created
 Konfirmasi     ── POST /handovers/{no}/confirm ▶  Serah terima "Dikonfirmasi Inventaris" → Selesai
 Catatan        ── POST /requests/{no}/notes ──▶  Muncul di Meja Inventaris PROCURA
```

- **Tidak ada data hilang**: bila PROCURA sedang mati saat pengajuan dikirim, pengajuan tersimpan sebagai draf; tombol **Kirim ulang** aman diulang (nomor pengajuan dipakai sebagai `Idempotency-Key`, jadi tidak pernah menghasilkan permintaan ganda).
- **Webhook aman**: tanda tangan HMAC-SHA256 diverifikasi dengan `hash_equals`; `X-Procura-Delivery` dipakai agar pengiriman ulang tidak membuat laporan ganda.
- **Handler webhook tidak memanggil balik PROCURA** (server `php artisan serve` hanya melayani satu permintaan sekali waktu, panggilan balik akan saling menunggu). Rincian lengkap ditarik saat halaman detail dibuka atau oleh `procura:sync`.
- Pengajuan **Keuangan tidak melihat nominal dana**: Inventaris hanya menampilkan status pendanaan ("Menunggu dana", "Dana cair"), bukan angkanya.

## Setelah serah terima

Tombol **Buka form aset (terisi otomatis)** membuka form Barang Elektronik / Non Elektronik / Perabotan / Kendaraan dengan nama, jumlah, dan harga perolehan (harga PO + PPN) sudah terisi.
Foto & detail lain tetap dilengkapi manual. Pengajuan jasa tidak dicatat sebagai aset; gedung membuka form kosong.

## Uji manual webhook tanpa PROCURA

```bash
BODY='{"event":"purchase_order.sent","data":{"number":"PO-2610-0001","request_number":"PR-2610-0001","vendor":"Vendor Uji","expected_date":"2026-10-20"}}'
SIG="sha256=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "whsec_ISI_RAHASIA" | sed 's/^.* //')"
curl -i -X POST http://localhost/inventaris-laravel/public/api/procura/webhook \
  -H "Content-Type: application/json" -H "X-Procura-Event: purchase_order.sent" -H "X-Procura-Delivery: uji-1" \
  -H "X-Procura-Signature: $SIG" -d "$BODY"
```
Respons `200 {"ok":true}` = tanda tangan benar. `401` = rahasia tidak cocok. `503` = `PROCURA_WEBHOOK_SECRET` belum diisi.

## Pemecahan masalah

| Gejala | Penyebab & solusi |
|---|---|
| "Tidak bisa menghubungi PROCURA" | PROCURA belum berjalan / `PROCURA_URL` salah (harus berakhiran `/api/v1`). |
| "PROCURA menolak token API" | `PROCURA_TOKEN` salah/dibuat ulang/klien dinonaktifkan. Jalankan `php artisan config:clear`. |
| "Token … tidak punya izin" | Minta Manager menambah scope klien (`requests:write`, `requests:read`, `handover:confirm`, `catalog:read`). |
| Laporan tidak masuk otomatis | Cek log pengiriman di PROCURA → Integrasi API. Alamat webhook harus terjangkau dari server PROCURA; jalankan `procura:sync` sebagai cadangan. |
| Katalog Pengadaan tidak muncul di form | Scope `catalog:read` belum ada, atau PROCURA sedang mati (hasil di-cache 10 menit: `php artisan cache:clear`). |
