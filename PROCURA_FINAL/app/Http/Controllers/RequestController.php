<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CatalogItem;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Vendor;
use App\Services\ProcurementService;
use App\Support\Workflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RequestController extends Controller
{
    public function __construct(private ProcurementService $svc)
    {
    }

    public function index(Request $request)
    {
        $q = PurchaseRequest::query()
            ->when($request->status === 'open', fn ($w) => $w->whereNotIn('status', ['completed', 'rejected', 'cancelled']))
            ->when($request->status && $request->status !== 'open', fn ($w) => $w->where('status', $request->status))
            ->when($request->category, fn ($w, $c) => $w->where('category', $c))
            ->when($request->priority, fn ($w, $p) => $w->where('priority', $p))
            ->when($request->search, fn ($w, $s) => $w->where(fn ($x) => $x->where('title', 'ilike', "%{$s}%")->orWhere('number', 'ilike', "%{$s}%")->orWhere('requester_name', 'ilike', "%{$s}%")->orWhere('external_ref', 'ilike', "%{$s}%")))
            ->orderByRaw("case when status in ('completed','rejected','cancelled') then 1 else 0 end")
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->orderByDesc('id');

        return Inertia::render('Requests/Index', [
            'requests' => $q->paginate(15)->withQueryString()->through(fn ($r) => $this->row($r)),
            'filters' => $request->only(['status', 'category', 'priority', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Requests/Create', [
            'catalog' => CatalogItem::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'category', 'unit', 'reference_price']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $source = $request->input('source') === 'inventaris' ? 'inventaris' : 'internal';
        $pr = $this->svc->createRequest($data, $source, $request->user());

        return redirect()->route('requests.show', $pr)->with('success', "Permintaan {$pr->number} dibuat.");
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest)
    {
        $pr = $purchaseRequest->load([
            'items', 'quotations.vendor', 'quotations.items', 'fundingRequests.submitter:id,name',
            'purchaseOrders.vendor', 'purchaseOrders.receipts', 'handovers', 'events.user:id,name', 'handler:id,name', 'creator:id,name',
        ]);

        $quotations = $pr->quotations->map(fn (Quotation $q) => [
            'id' => $q->id, 'number' => $q->number, 'vendor' => ['id' => $q->vendor->id, 'name' => $q->vendor->name, 'rating' => $q->vendor->rating],
            'quoted_date' => $q->quoted_date->format('Y-m-d'), 'valid_until' => $q->valid_until?->format('Y-m-d'), 'lead_time_days' => $q->lead_time_days,
            'payment_terms' => $q->payment_terms, 'total' => $q->total, 'notes' => $q->notes, 'is_selected' => $q->is_selected, 'selection_reason' => $q->selection_reason,
            'prices' => $q->items->pluck('unit_price', 'purchase_request_item_id'),
            'attachments' => Attachment::panelFor($q),
        ]);

        return Inertia::render('Requests/Show', [
            'pr' => [
                ...$pr->only(['id', 'number', 'source', 'external_ref', 'requester_name', 'requester_unit', 'category', 'title', 'justification', 'priority', 'status', 'estimated_total', 'rejection_reason']),
                'needed_date' => $pr->needed_date?->format('Y-m-d'),
                'created_at' => $pr->created_at->format('Y-m-d H:i'),
                'station' => $pr->stationKey(),
                'closed' => $pr->isClosed(),
                'handler' => $pr->handler?->name,
                'creator' => $pr->creator?->name,
            ],
            'items' => $pr->items,
            'quotations' => $quotations,
            'fundings' => $pr->fundingRequests->map(fn ($f) => [...$f->toArray(), 'submitter' => $f->submitter?->name, 'remaining' => $f->remaining(), 'attachments' => Attachment::panelFor($f)]),
            'orders' => $pr->purchaseOrders->map(fn ($p) => ['id' => $p->id, 'number' => $p->number, 'status' => $p->status, 'vendor' => $p->vendor->name, 'total' => $p->total, 'expected_date' => $p->expected_date?->format('Y-m-d')]),
            'handovers' => $pr->handovers->map(fn ($h) => ['id' => $h->id, 'number' => $h->number, 'handover_date' => $h->handover_date->format('Y-m-d'), 'receiver_name' => $h->receiver_name, 'status' => $h->status, 'confirmed_by_name' => $h->confirmed_by_name, 'notes' => $h->notes]),
            'events' => $pr->events,
            'attachments' => Attachment::panelFor($pr),
            'vendors' => Vendor::where('status', 'active')->orderBy('name')->get(['id', 'name', 'category', 'rating', 'payment_terms_days']),
            'suggestedAmount' => $this->svc->suggestedFundingAmount($pr),
            'threshold' => config('procura.internal_approval_threshold'),
        ]);
    }

    public function review(Request $request, PurchaseRequest $purchaseRequest)
    {
        return $this->guard(fn () => $this->svc->startReview($purchaseRequest, $request->user()), 'Permintaan mulai ditinjau. Inventaris sudah dikabari.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], ['reason.required' => 'Tulis alasan penolakan.', 'reason.min' => 'Alasan terlalu singkat.']);

        return $this->guard(fn () => $this->svc->reject($purchaseRequest, $data['reason'], $request->user()), 'Permintaan ditolak dan Inventaris sudah dikabari.');
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], ['reason.required' => 'Tulis alasan pembatalan.']);

        return $this->guard(fn () => $this->svc->cancel($purchaseRequest, $data['reason'], $request->user()), 'Permintaan dibatalkan.');
    }

    public function storeQuotation(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'vendor_ref' => 'nullable|string|max:60',
            'quoted_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quoted_date',
            'lead_time_days' => 'required|integer|min:0|max:365',
            'payment_terms' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000',
            'prices' => 'required|array',
            'prices.*' => 'nullable|numeric|min:0',
        ], ['vendor_id.required' => 'Pilih vendor.', 'valid_until.after_or_equal' => 'Masa berlaku tidak boleh sebelum tanggal penawaran.']);

        return $this->guard(fn () => $this->svc->addQuotation($purchaseRequest, $data, $request->user()), 'Penawaran dicatat.');
    }

    public function destroyQuotation(Request $request, PurchaseRequest $purchaseRequest, Quotation $quotation)
    {
        abort_unless($quotation->purchase_request_id === $purchaseRequest->id, 404);

        return $this->guard(function () use ($quotation) {
            if ($quotation->is_selected && $quotation->request->fundingRequests()->whereNotIn('status', ['rejected', 'cancelled'])->exists()) {
                throw new \App\Exceptions\WorkflowException('Penawaran ini sudah dipakai pengajuan dana dan tidak bisa dihapus.');
            }
            $quotation->delete();
        }, 'Penawaran dihapus.');
    }

    public function selectQuotation(Request $request, PurchaseRequest $purchaseRequest, Quotation $quotation)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:400'], ['reason.required' => 'Tulis alasan memilih vendor ini (mis. harga terendah, garansi, waktu kirim).']);

        return $this->guard(fn () => $this->svc->selectQuotation($purchaseRequest, $quotation, $data['reason'], $request->user()), 'Penawaran dipilih.');
    }

    public function handover(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'handover_date' => 'required|date', 'receiver_name' => 'required|string|max:120', 'notes' => 'nullable|string|max:1000',
        ], ['receiver_name.required' => 'Isi nama penerima di Inventaris.']);

        return $this->guard(fn () => $this->svc->handOver($purchaseRequest, $data['handover_date'], $data['receiver_name'], $data['notes'] ?? null, $request->user()), 'Serah terima dicatat dan Inventaris sudah dilapori.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'category' => ['required', Rule::in(array_keys(config('procura.categories')))],
            'requester_name' => 'required|string|max:120',
            'requester_unit' => 'nullable|string|max:120',
            'external_ref' => 'nullable|string|max:60',
            'justification' => 'nullable|string|max:2000',
            'priority' => ['required', Rule::in(array_keys(Workflow::PRIORITY))],
            'needed_date' => 'nullable|date|after_or_equal:today',
            'items' => 'required|array|min:1|max:50',
            'items.*.name' => 'required|string|max:200',
            'items.*.specification' => 'nullable|string|max:500',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999999',
            'items.*.unit' => 'required|string|max:20',
            'items.*.estimated_price' => 'nullable|numeric|min:0',
            'items.*.catalog_item_id' => 'nullable|exists:catalog_items,id',
        ], [
            'title.required' => 'Judul permintaan wajib diisi.',
            'requester_name.required' => 'Nama pemohon wajib diisi.',
            'items.required' => 'Tambahkan minimal satu barang/jasa.',
            'items.*.name.required' => 'Nama barang/jasa wajib diisi.',
            'items.*.quantity.gt' => 'Jumlah harus lebih dari nol.',
            'needed_date.after_or_equal' => 'Tanggal dibutuhkan tidak boleh di masa lalu.',
        ]);
    }

    private function row(PurchaseRequest $r): array
    {
        return [
            'id' => $r->id, 'number' => $r->number, 'title' => $r->title, 'category' => $r->category, 'priority' => $r->priority, 'status' => $r->status,
            'requester_name' => $r->requester_name, 'requester_unit' => $r->requester_unit, 'source' => $r->source,
            'needed_date' => $r->needed_date?->format('Y-m-d'), 'estimated_total' => $r->estimated_total, 'station' => $r->stationKey(),
            'late' => $r->needed_date && $r->needed_date->isPast() && ! $r->isClosed(),
        ];
    }
}
