<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use App\Services\ActivityLogger;
use App\Services\WebhookService;
use App\Support\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Halaman Integrasi (khusus Manager): klien REST API (Inventaris, Keuangan, ...), webhook, dan log pengiriman. */
class IntegrationController extends Controller
{
    public function index()
    {
        return Inertia::render('Integrations/Index', [
            'clients' => ApiClient::orderBy('name')->get()->map(fn (ApiClient $c) => [
                'id' => $c->id, 'name' => $c->name, 'system' => $c->system, 'token_prefix' => $c->token_prefix, 'scopes' => $c->scopes,
                'webhook_url' => $c->webhook_url, 'webhook_events' => $c->webhook_events ?? [], 'is_active' => $c->is_active,
                'last_used_at' => $c->last_used_at?->format('Y-m-d H:i'), 'last_used_ip' => $c->last_used_ip,
                'has_secret' => (bool) $c->webhook_secret,
            ]),
            'deliveries' => WebhookDelivery::with('client:id,name')->orderByDesc('id')->limit(25)->get()->map(fn ($d) => [
                'id' => $d->id, 'client' => $d->client?->name, 'event' => $d->event, 'status' => $d->status, 'response_code' => $d->response_code,
                'attempts' => $d->attempts, 'error' => $d->error, 'created_at' => $d->created_at->format('Y-m-d H:i:s'),
            ]),
            'scopes' => Workflow::API_SCOPES,
            'events' => Workflow::WEBHOOK_EVENTS,
            'baseUrl' => rtrim(config('app.url'), '/').'/api/v1',
            'newToken' => session('new_token'),
            'newSecret' => session('new_secret'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        $secret = filled($data['webhook_url'] ?? null) ? 'whsec_'.Str::random(32) : null;

        $client = ApiClient::create($data + ['token_prefix' => $prefix, 'token_hash' => $hash, 'webhook_secret' => $secret, 'created_by' => $request->user()->id]);
        ActivityLogger::log('integration.created', "Klien API {$client->name} ({$client->system}) dibuat", $client);

        return redirect()->route('integrations.index')->with('new_token', ['name' => $client->name, 'token' => $plain])->with('new_secret', $secret);
    }

    public function update(Request $request, ApiClient $client)
    {
        $data = $this->validated($request);
        if (filled($data['webhook_url'] ?? null) && ! $client->webhook_secret) {
            $data['webhook_secret'] = 'whsec_'.Str::random(32);
        }
        $client->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Klien API diperbarui.');
    }

    public function regenerate(Request $request, ApiClient $client)
    {
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        $client->update(['token_prefix' => $prefix, 'token_hash' => $hash]);
        ActivityLogger::log('integration.token', "Token klien API {$client->name} dibuat ulang", $client);

        return redirect()->route('integrations.index')->with('new_token', ['name' => $client->name, 'token' => $plain]);
    }

    public function destroy(ApiClient $client)
    {
        $client->delete();
        ActivityLogger::log('integration.deleted', "Klien API {$client->name} dihapus");

        return back()->with('success', 'Klien API dihapus.');
    }

    public function resend(WebhookDelivery $delivery, WebhookService $webhooks)
    {
        $webhooks->send($delivery);

        return back()->with($delivery->fresh()->status === 'delivered' ? 'success' : 'error', $delivery->fresh()->status === 'delivered' ? 'Webhook terkirim.' : 'Webhook gagal: '.$delivery->fresh()->error);
    }

    public function test(ApiClient $client, WebhookService $webhooks)
    {
        if (! $client->webhook_url) {
            return back()->withErrors(['general' => 'Klien ini belum punya alamat webhook.']);
        }
        $d = WebhookDelivery::create(['api_client_id' => $client->id, 'event' => 'ping', 'payload' => ['event' => 'ping', 'occurred_at' => now()->toIso8601String(), 'data' => ['message' => 'Uji koneksi dari PROCURA']]]);
        $webhooks->send($d);

        return back()->with($d->fresh()->status === 'delivered' ? 'success' : 'error', $d->fresh()->status === 'delivered' ? 'Ping diterima klien.' : 'Ping gagal: '.$d->fresh()->error);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'system' => ['required', Rule::in(['inventaris', 'keuangan', 'sdm', 'lainnya'])],
            'scopes' => 'required|array|min:1', 'scopes.*' => Rule::in(array_keys(Workflow::API_SCOPES)),
            'webhook_url' => ['nullable', 'url:http,https', 'max:500'],
            'webhook_events' => 'nullable|array', 'webhook_events.*' => Rule::in(array_keys(Workflow::WEBHOOK_EVENTS)),
        ], ['name.required' => 'Nama klien wajib diisi.', 'scopes.required' => 'Pilih minimal satu izin akses.', 'webhook_url.url' => 'Alamat webhook harus berupa URL http(s) yang valid.']);
        $data['webhook_events'] = $data['webhook_events'] ?? [];

        return $data;
    }
}
