<?php

namespace App\Services;

use App\Exceptions\ProcuraException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Klien REST API PROCURA v1 (lihat docs/API.md di proyek PROCURA). */
class ProcuraClient
{
    public function configured(): bool
    {
        return filled(config('procura.url')) && filled(config('procura.token'));
    }

    /** @return array<string,mixed> */
    public function ping(): array
    {
        return $this->call('GET', '/ping');
    }

    /** Katalog barang Pengadaan untuk isi cepat form. Gagal = daftar kosong (form tetap bisa diisi manual). */
    public function catalog(): array
    {
        if (! $this->configured()) {
            return [];
        }

        return Cache::remember('procura.catalog', 600, function () {
            try {
                return $this->call('GET', '/catalog', ['per_page' => 100]);
            } catch (ProcuraException $e) {
                Log::info('[PROCURA] Katalog tidak bisa dimuat: '.$e->getMessage());

                return [];   // tetap di-cache 10 menit agar halaman tidak lambat saat PROCURA mati
            }
        });
    }

    /** Kirim pengajuan. $idempotencyKey mencegah data ganda bila permintaan diulang. */
    public function submit(array $payload, string $idempotencyKey): array
    {
        return $this->call('POST', '/requests', $payload, $idempotencyKey);
    }

    public function find(string $number): array
    {
        return $this->call('GET', '/requests/'.rawurlencode($number));
    }

    public function confirmHandover(string $handoverNumber, string $confirmedBy): array
    {
        return $this->call('POST', '/handovers/'.rawurlencode($handoverNumber).'/confirm', ['confirmed_by' => $confirmedBy]);
    }

    public function sendNote(string $number, string $subject, ?string $body, string $author): void
    {
        $this->call('POST', '/requests/'.rawurlencode($number).'/notes', ['subject' => $subject, 'body' => $body, 'author' => $author]);
    }

    /** @return array<string,mixed>  isi "data" dari tanggapan */
    private function call(string $method, string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        if (! $this->configured()) {
            throw new ProcuraException('Integrasi PROCURA belum diatur. Isi PROCURA_URL dan PROCURA_TOKEN di file .env.');
        }

        try {
            $http = $this->http($idempotencyKey);
            $response = $method === 'GET' ? $http->get($path, $data) : $http->post($path, $data);
        } catch (ConnectionException $e) {
            Log::warning('[PROCURA] Koneksi gagal: '.$e->getMessage());
            throw new ProcuraException('Tidak bisa menghubungi PROCURA. Pastikan aplikasi Pengadaan sedang berjalan dan PROCURA_URL benar.');
        }

        return $this->handle($response);
    }

    private function http(?string $idempotencyKey): PendingRequest
    {
        $http = Http::baseUrl(config('procura.url'))
            ->withToken(config('procura.token'))
            ->acceptJson()
            ->timeout(config('procura.timeout'))
            ->connectTimeout(5);

        return $idempotencyKey ? $http->withHeaders(['Idempotency-Key' => $idempotencyKey]) : $http;
    }

    private function handle(Response $r): array
    {
        if ($r->successful()) {
            return $r->json('data') ?? [];
        }

        $error = $r->json('error') ?? [];
        $message = $error['message'] ?? null;
        $details = is_array($error['details'] ?? null) ? $error['details'] : [];

        $friendly = match (true) {
            $r->status() === 401 => 'PROCURA menolak token API. Periksa PROCURA_TOKEN (mungkin sudah dibuat ulang atau klien dinonaktifkan).',
            $r->status() === 403 => 'Token API ini tidak punya izin untuk aksi tersebut. Minta Manager Pengadaan menambah scope-nya.',
            $r->status() === 404 => 'Data tidak ditemukan di PROCURA.',
            $r->status() === 429 => 'Terlalu banyak permintaan ke PROCURA. Coba lagi sebentar lagi.',
            $r->status() === 422 => $this->firstValidationMessage($details) ?? ($message ?: 'PROCURA menolak data yang dikirim.'),
            $r->status() >= 500 => 'PROCURA sedang bermasalah (HTTP '.$r->status().'). Coba lagi nanti.',
            default => $message ?: 'PROCURA membalas dengan HTTP '.$r->status().'.',
        };

        throw new ProcuraException($friendly, $r->status(), $details);
    }

    private function firstValidationMessage(array $details): ?string
    {
        foreach ($details as $messages) {
            $first = is_array($messages) ? ($messages[0] ?? null) : $messages;
            if ($first) {
                return 'PROCURA menolak data: '.$first;
            }
        }

        return null;
    }
}
