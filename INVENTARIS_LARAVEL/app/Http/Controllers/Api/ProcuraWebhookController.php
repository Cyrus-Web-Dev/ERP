<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LaporanPengadaan;
use App\Services\PengadaanSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook dari PROCURA (POST /api/procura/webhook).
 *
 * Keamanan: tanda tangan HMAC-SHA256 atas isi mentah dengan PROCURA_WEBHOOK_SECRET (header X-Procura-Signature).
 * Idempotensi: X-Procura-Delivery disimpan; pengiriman ulang dengan ID sama diabaikan.
 * Penting: handler ini SENGAJA tidak memanggil balik API PROCURA (server PHP bawaan satu-utas akan saling menunggu).
 * Data lengkap ditarik nanti saat halaman detail dibuka atau oleh `procura:sync`.
 */
class ProcuraWebhookController extends Controller
{
    public function __invoke(Request $request, PengadaanSync $sync): JsonResponse
    {
        $secret = config('procura.webhook_secret');
        if (! $secret) {
            return response()->json(['error' => 'Webhook belum dikonfigurasi (PROCURA_WEBHOOK_SECRET kosong).'], 503);
        }

        $body = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
        if (! hash_equals($expected, (string) $request->header('X-Procura-Signature'))) {
            return response()->json(['error' => 'Tanda tangan tidak valid.'], 401);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Isi bukan JSON.'], 400);
        }

        $event = (string) ($request->header('X-Procura-Event') ?: ($payload['event'] ?? ''));
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }

        $delivery = (string) $request->header('X-Procura-Delivery');
        if ($delivery !== '' && LaporanPengadaan::where('delivery_id', $delivery)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            $this->proses($event, $payload['data'] ?? [], $delivery !== '' ? $delivery : null, $sync);
        } catch (Throwable $e) {
            // Tetap balas 200 agar PROCURA tidak mengulang terus; status akan dikoreksi oleh sinkron berikutnya.
            Log::error('[PROCURA webhook] '.$event.': '.$e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    private function proses(string $event, array $data, ?string $delivery, PengadaanSync $sync): void
    {
        [$nomor, $ref] = match ($event) {
            'request.status_changed' => [$data['number'] ?? null, $data['external_ref'] ?? null],
            'handover.created' => [$data['request']['number'] ?? null, $data['request']['external_ref'] ?? null],
            'goods.received' => [$data['purchase_order']['request_number'] ?? null, null],
            'purchase_order.sent', 'funding.submitted', 'funding.settled' => [$data['request_number'] ?? null, null],
            default => [null, null],
        };

        $p = $sync->cari($nomor, $ref);
        if (! $p) {
            return;   // permintaan yang bukan berasal dari Inventaris (mis. dibuat internal oleh Pengadaan)
        }

        switch ($event) {
            case 'request.status_changed':
                $sync->terapkanKabar($p, $data['status'] ?? null, $data['station'] ?? null, $data['rejection_reason'] ?? null);
                break;

            case 'purchase_order.sent':
                $sync->terapkanKabar($p, 'ordered');
                LaporanPengadaan::catat($p, 'masuk', 'pesanan', 'Barang sudah dipesan ke '.($data['vendor'] ?? 'vendor'),
                    'PO '.($data['number'] ?? '-').(! empty($data['expected_date']) ? ', perkiraan tiba '.date('d/m/Y', strtotime($data['expected_date'])) : ''), 'po:'.($data['number'] ?? ''), $delivery);
                break;

            case 'goods.received':
                $lengkap = (bool) ($data['complete'] ?? false);
                $sync->terapkanKabar($p, $lengkap ? 'received' : null);
                LaporanPengadaan::catat($p, 'masuk', 'penerimaan', $lengkap ? 'Barang/jasa sudah diterima Pengadaan' : 'Pengadaan menerima sebagian pesanan',
                    'Penerimaan '.($data['receipt'] ?? '-').' untuk PO '.($data['purchase_order']['number'] ?? '-').($lengkap ? '. Menunggu serah terima ke Inventaris.' : '.'), 'gr:'.($data['receipt'] ?? ''), $delivery);
                break;

            case 'handover.created':
                $sync->terapkanKabar($p, $data['request']['status'] ?? 'handed_over', $data['request']['station'] ?? 'handed_over');
                $p->update(['serah_terima_nomor' => $data['number'] ?? null, 'serah_terima_status' => $data['status'] ?? 'handed_over',
                    'serah_terima_tanggal' => $data['handover_date'] ?? null, 'serah_terima_penerima' => $data['receiver_name'] ?? null]);
                LaporanPengadaan::catat($p, 'masuk', 'serah_terima', 'Serah terima '.($data['number'] ?? '').': mohon konfirmasi',
                    'Diserahkan '.(! empty($data['handover_date']) ? date('d/m/Y', strtotime($data['handover_date'])) : '').', penerima '.($data['receiver_name'] ?? '-').'. Buka pengajuan untuk mengonfirmasi dan mencatat ke data aset.', 'ho:'.($data['number'] ?? ''), $delivery);
                break;

            default:
                $p->update(['disinkronkan_at' => null]);   // event lain: cukup tandai basi
        }
    }
}
