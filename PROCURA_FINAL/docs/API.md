# REST API PROCURA v1

Base URL: `{APP_URL}/api/v1` · Format: JSON · Autentikasi: `Authorization: Bearer <token>`

Token dibuat oleh Manager di menu **Integrasi API**. Hanya hash SHA-256 token yang disimpan; token asli tampil sekali.
Setiap token punya *scope*; request di luar scope dijawab `403 insufficient_scope`. Batas: 120 request/menit/token (`PROCURA_API_RATE_LIMIT`).

Respons sukses `{"data": ...}` (daftar juga memuat `meta`). Respons gagal:
`{"error": {"code": "...", "message": "...", "details": {...}}}` — kode: `unauthenticated` 401, `invalid_token` 401, `insufficient_scope` 403,
`validation_failed` 422, `workflow_violation` 422 (aturan alur dilanggar), `duplicate_external_ref` 409, `not_found` 404.

## Sisi Inventaris

| Metode | Path | Scope | Keterangan |
|---|---|---|---|
| GET | `/ping` | (token apa pun) | Uji koneksi + daftar scope token |
| GET | `/catalog?q=&per_page=` | `catalog:read` | Katalog barang aktif |
| POST | `/requests` | `requests:write` | Kirim permintaan barang/jasa. Header `Idempotency-Key` disarankan |
| GET | `/requests?status=&external_ref=&updated_since=` | `requests:read` | Daftar / pelacakan status |
| GET | `/requests/{number\|external_ref}` | `requests:read` | Detail + dana + PO + serah terima |
| POST | `/requests/{number\|external_ref}/notes` | `requests:write` | Catatan tindak lanjut. Body: `{"subject": "…", "body": "…", "author": "Nama"}` |
| POST | `/handovers/{number}/confirm` | `handover:confirm` | Konfirmasi serah terima. Body: `{"confirmed_by": "Nama"}` |

`POST /requests` body:
```json
{ "title": "Laptop staf gudang", "category": "elektronik", "requester_name": "Rina", "requester_unit": "Divisi Inventaris",
  "external_ref": "INV-REQ-0231", "priority": "high", "needed_date": "2026-10-20", "justification": "…",
  "items": [ { "name": "Laptop 14 inci", "specification": "i5/16GB", "quantity": 3, "unit": "unit", "estimated_price": 12500000, "catalog_item_id": 1 } ] }
```
`category`: `elektronik, non_elektronik, perabotan, kendaraan, gedung, jasa, habis_pakai`. `priority`: `low, normal, high, urgent`.

## Sisi Keuangan

| Metode | Path | Scope | Keterangan |
|---|---|---|---|
| GET | `/funding?status=submitted,approved` | `funding:read` | Pengajuan dana (filter status dipisah koma) |
| GET | `/funding/{number}` | `funding:read` | Detail satu pengajuan |
| POST | `/funding/{number}/decision` | `funding:decide` | Keputusan Keuangan |
| POST | `/funding/{number}/disbursement` | `funding:decide` | Pencairan dana |
| POST | `/funding/{number}/notes` | `funding:decide` | Catatan/laporan dari Keuangan ke Pengadaan. Body: `{"subject": "…", "body": "…", "author": "Nama"}` |

`decision`: `{"decision": "approve|reject|revision", "approved_amount": 19800000, "note": "…", "decided_by": "Bu Sari", "finance_ref": "KEU-2026-0077"}`
(`note` wajib untuk `reject`/`revision`). `disbursement`: `{"amount": 19800000, "method": "transfer|tunai|cek", "reference": "TRF-001", "disbursed_by": "Bu Sari"}`
(tidak boleh melebihi nominal yang disetujui; hanya untuk pengajuan berstatus `approved`).

Payload dana (`GET /funding`, webhook `funding.*`) memuat `request_number`, `request_title`, `category`, nominal, keputusan, pencairan, dan LPJ.

Status pengajuan dana: `pending_internal → submitted → approved | rejected | revision → disbursed → settled`.
Status permintaan: `submitted, reviewing, sourcing, funding, funded, ordered, received, handed_over, completed, rejected, cancelled`.

## Webhook (PROCURA → sistem lain)

Diatur per klien. Metode `POST`, body:
```json
{ "event": "funding.submitted", "occurred_at": "2026-10-01T09:30:00+07:00", "data": { … } }
```
Header: `X-Procura-Event`, `X-Procura-Delivery`, `X-Procura-Signature: sha256=<HMAC-SHA256(body mentah, webhook_secret)>`.
Balas HTTP 2xx agar dianggap terkirim; yang gagal bisa dikirim ulang dari halaman Integrasi.

Event: `request.status_changed`, `funding.submitted`, `funding.settled`, `purchase_order.sent`, `goods.received`, `handover.created`.

Verifikasi (PHP):
```php
$expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
abort_unless(hash_equals($expected, $request->header('X-Procura-Signature')), 401);
```
