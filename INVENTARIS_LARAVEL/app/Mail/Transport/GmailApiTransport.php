<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport ini meniru persis alur di config_email.php pada project PHP
 * native: refresh access token pakai OAuth2 refresh_token, lalu kirim
 * pesan mentah (raw MIME, base64url) lewat Gmail API "messages.send".
 *
 * Kredensial dibaca dari (urutan prioritas sama seperti versi lama):
 *   1. storage/app/credentials/token.json + credentials.json
 *      (tinggal salin file yang sudah kamu pakai sebelumnya - tidak perlu
 *      setup OAuth dari nol lagi)
 *   2. fallback ke env: GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN
 */
class GmailApiTransport extends AbstractTransport
{
    public function __toString(): string
    {
        return 'gmailapi';
    }

    protected function doSend(SentMessage $message): void
    {
        [$clientId, $clientSecret, $refreshToken, $tokenPath] = $this->muatKredensial();

        if (! $clientId || ! $clientSecret || ! $refreshToken) {
            throw new RuntimeException(
                'Kredensial Gmail API belum lengkap. Salin token.json & credentials.json ke '.
                'storage/app/credentials/, atau isi GMAIL_CLIENT_ID/GMAIL_CLIENT_SECRET/GMAIL_REFRESH_TOKEN di .env.'
            );
        }

        $accessToken = $this->refreshAccessToken($clientId, $clientSecret, $refreshToken, $tokenPath);

        $rawMime = MessageConverter::toEmail($message->getOriginalMessage())->toString();
        $rawEncoded = rtrim(strtr(base64_encode($rawMime), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->asJson()
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $rawEncoded,
            ]);

        if (! $response->successful()) {
            Log::error('Gagal kirim email via Gmail API: '.$response->body());

            throw new RuntimeException('Gagal kirim email via Gmail API: '.$response->body());
        }
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function muatKredensial(): array
    {
        $tokenPath = storage_path('app/credentials/token.json');
        $credPath = storage_path('app/credentials/credentials.json');

        if (is_readable($tokenPath) && is_readable($credPath)) {
            $token = json_decode((string) file_get_contents($tokenPath), true) ?: [];
            $cred = json_decode((string) file_get_contents($credPath), true) ?: [];
            $cfg = $cred['web'] ?? $cred['installed'] ?? $cred;

            return [
                $cfg['client_id'] ?? null,
                $cfg['client_secret'] ?? null,
                $token['refresh_token'] ?? null,
                $tokenPath,
            ];
        }

        // Fallback: kredensial langsung dari .env (tanpa file token.json)
        return [
            config('services.gmail_api.client_id'),
            config('services.gmail_api.client_secret'),
            config('services.gmail_api.refresh_token'),
            null,
        ];
    }

    private function refreshAccessToken(string $clientId, string $clientSecret, string $refreshToken, ?string $tokenPath): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            Log::error('Gagal refresh token Gmail API: '.$response->body());

            throw new RuntimeException('Gagal refresh access token Gmail API: '.$response->body());
        }

        $data = $response->json();

        // Simpan access_token terbaru kembali ke token.json, sama seperti versi lama,
        // supaya file itu selalu berisi token yang up to date.
        if ($tokenPath) {
            $token = json_decode((string) file_get_contents($tokenPath), true) ?: [];
            $token['access_token'] = $data['access_token'];
            if (isset($data['expires_in'])) {
                $token['expires_in'] = $data['expires_in'];
                $token['created'] = time();
            }
            file_put_contents($tokenPath, json_encode($token, JSON_PRETTY_PRINT));
        }

        return $data['access_token'];
    }
}
