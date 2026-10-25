<?php

namespace App\Http\Controllers\Api;

use App\Models\CatalogItem;
use App\Models\PurchaseRequest;
use App\Services\ProcurementService;
use App\Support\Presenter;
use App\Support\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequestApiController extends ApiController
{
    public function __construct(private ProcurementService $svc)
    {
    }

    public function ping(Request $request)
    {
        $c = $this->client($request);

        return $this->ok(['app' => config('app.name'), 'client' => $c->name, 'system' => $c->system, 'scopes' => $c->scopes, 'server_time' => now()->toIso8601String()]);
    }

    public function catalog(Request $request)
    {
        $items = CatalogItem::where('is_active', true)->when($request->q, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))->orderBy('name')->paginate(min((int) $request->get('per_page', 50), 100));

        return $this->paginated($items, fn ($i) => ['id' => $i->id, 'sku' => $i->sku, 'name' => $i->name, 'category' => $i->category, 'unit' => $i->unit, 'reference_price' => $i->reference_price]);
    }

    public function index(Request $request)
    {
        $list = PurchaseRequest::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->external_ref, fn ($q, $s) => $q->where('external_ref', $s))
            ->when($request->updated_since, fn ($q, $s) => $q->where('updated_at', '>=', $s))
            ->orderByDesc('id')->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($list, fn ($r) => Presenter::request($r, false));
    }

    public function show(string $number)
    {
        return $this->ok(Presenter::request(PurchaseRequest::where('number', $number)->orWhere('external_ref', $number)->firstOrFail()));
    }

    /** POST /requests/{number}/notes — catatan tindak lanjut dari Inventaris ("mohon dipercepat", perubahan spesifikasi, dst). */
    public function note(Request $request, string $number)
    {
        $pr = PurchaseRequest::where('number', $number)->orWhere('external_ref', $number)->firstOrFail();
        $v = Validator::make($request->all(), ['subject' => 'required|string|max:200', 'body' => 'nullable|string|max:3000', 'author' => 'required|string|max:120']);
        if ($v->fails()) {
            return $this->error(422, 'validation_failed', 'Data catatan tidak valid.', $v->errors()->toArray());
        }
        $d = $v->validated();
        $client = $this->client($request);

        $this->svc->report('inventaris', 'in', 'note', $d['subject'], trim(($d['body'] ?? '')."\n\n— {$d['author']}"), $pr, null, false, null);
        $this->svc->event($pr, 'report', 'Catatan dari Inventaris: '.$d['subject'], null, "API: {$client->name}");

        return $this->ok(['request' => $pr->number, 'recorded' => true], 201);
    }

    /** POST /requests — dipakai sistem Inventaris untuk mengirim permintaan barang/jasa. Mendukung header Idempotency-Key. */
    public function store(Request $request)
    {
        $client = $this->client($request);

        $v = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'category' => ['required', Rule::in(array_keys(config('procura.categories')))],
            'requester_name' => 'required|string|max:120',
            'requester_unit' => 'nullable|string|max:120',
            'external_ref' => 'nullable|string|max:60',
            'justification' => 'nullable|string|max:2000',
            'priority' => ['nullable', Rule::in(array_keys(Workflow::PRIORITY))],
            'needed_date' => 'nullable|date',
            'items' => 'required|array|min:1|max:50',
            'items.*.name' => 'required|string|max:200',
            'items.*.specification' => 'nullable|string|max:500',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.estimated_price' => 'nullable|numeric|min:0',
            'items.*.catalog_item_id' => 'nullable|exists:catalog_items,id',
        ]);
        if ($v->fails()) {
            return $this->error(422, 'validation_failed', 'Data permintaan tidak valid.', $v->errors()->toArray());
        }

        $key = $request->header('Idempotency-Key');
        if ($key) {
            $existing = DB::table('idempotency_keys')->where(['api_client_id' => $client->id, 'key' => $key])->first();
            if ($existing) {
                return $this->ok(Presenter::request(PurchaseRequest::findOrFail($existing->resource_id)));
            }
        }
        // external_ref unik per klien: kirim ulang dengan ref sama mengembalikan data yang sudah ada
        if ($request->filled('external_ref') && ($dup = PurchaseRequest::where('external_ref', $request->external_ref)->where('source', $client->system === 'inventaris' ? 'inventaris' : 'api')->first())) {
            return $this->error(409, 'duplicate_external_ref', "Permintaan dengan external_ref ini sudah ada: {$dup->number}.", ['number' => $dup->number]);
        }

        $data = $v->validated();
        $data['items'] = array_map(fn ($i) => $i + ['unit' => 'unit'], $data['items']);
        $pr = $this->svc->createRequest($data, $client->system === 'inventaris' ? 'inventaris' : 'api', null, "API: {$client->name}");

        if ($key) {
            DB::table('idempotency_keys')->insert(['api_client_id' => $client->id, 'key' => $key, 'resource_type' => 'purchase_request', 'resource_id' => $pr->id, 'created_at' => now()]);
        }

        return $this->ok(Presenter::request($pr->fresh()), 201);
    }
}
