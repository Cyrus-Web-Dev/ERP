<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengabari sistem lain (Inventaris, Keuangan, ...) saat terjadi sesuatu di PROCURA.
 * Setiap pengiriman ditandatangani HMAC-SHA256 dengan rahasia milik klien:
 *   X-Procura-Signature: sha256=<hmac(body)>
 * Kegagalan webhook TIDAK PERNAH menggagalkan aksi pengguna; hasilnya dicatat di tabel webhook_deliveries
 * dan bisa dikirim ulang dari halaman Integrasi.
 */
class WebhookService
{
    public function dispatch(string $event, array $data): void
    {
        try {
            ApiClient::where('is_active', true)->whereNotNull('webhook_url')->get()
                ->filter(fn (ApiClient $c) => in_array($event, $c->webhook_events ?? [], true))
                ->each(function (ApiClient $client) use ($event, $data) {
                    $delivery = WebhookDelivery::create([
                        'api_client_id' => $client->id,
                        'event' => $event,
                        'payload' => ['event' => $event, 'occurred_at' => now()->toIso8601String(), 'data' => $data],
                    ]);
                    $this->send($delivery);
                });
        } catch (Throwable $e) {
            Log::warning('[Webhook] Gagal menjadwalkan pengiriman: '.$e->getMessage());
        }
    }

    public function send(WebhookDelivery $delivery): void
    {
        $client = $delivery->client;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $client->webhook_secret);

        try {
            $response = Http::timeout((int) config('procura.api.webhook_timeout', 8))
                ->connectTimeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Procura-Event' => $delivery->event,
                    'X-Procura-Delivery' => (string) $delivery->id,
                    'X-Procura-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($client->webhook_url);

            $delivery->update([
                'attempts' => $delivery->attempts + 1,
                'response_code' => $response->status(),
                'status' => $response->successful() ? 'delivered' : 'failed',
                'error' => $response->successful() ? null : 'HTTP '.$response->status(),
                'delivered_at' => $response->successful() ? now() : null,
            ]);
        } catch (Throwable $e) {
            $delivery->update(['attempts' => $delivery->attempts + 1, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }
}
