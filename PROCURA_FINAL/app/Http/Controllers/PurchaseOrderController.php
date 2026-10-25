<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\ProcurementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function __construct(private ProcurementService $svc)
    {
    }

    public function index(Request $request)
    {
        $orders = PurchaseOrder::with(['vendor:id,name', 'request:id,number,title'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'ilike', "%{$s}%")->orWhereHas('vendor', fn ($v) => $v->where('name', 'ilike', "%{$s}%"))->orWhereHas('request', fn ($r) => $r->where('title', 'ilike', "%{$s}%"))))
            ->orderByDesc('id')->paginate(15)->withQueryString()
            ->through(fn (PurchaseOrder $p) => [
                'id' => $p->id, 'number' => $p->number, 'status' => $p->status, 'vendor' => $p->vendor->name, 'total' => $p->total, 'paid' => $p->paidTotal(),
                'order_date' => $p->order_date->format('Y-m-d'), 'expected_date' => $p->expected_date?->format('Y-m-d'),
                'late' => $p->expected_date && $p->expected_date->isPast() && in_array($p->status, ['sent', 'partial'], true),
                'request' => ['id' => $p->request->id, 'number' => $p->request->number, 'title' => $p->request->title],
            ]);

        return Inertia::render('PurchaseOrders/Index', ['orders' => $orders, 'filters' => $request->only(['status', 'search'])]);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $po = $purchaseOrder->load(['vendor', 'items', 'request', 'funding', 'receipts.items', 'receipts.receiver:id,name', 'payments.payer:id,name']);

        return Inertia::render('PurchaseOrders/Show', [
            'po' => [
                ...$po->only(['id', 'number', 'status', 'subtotal', 'tax_percent', 'tax_amount', 'total', 'payment_terms', 'delivery_address', 'notes']),
                'order_date' => $po->order_date->format('Y-m-d'), 'expected_date' => $po->expected_date?->format('Y-m-d'), 'sent_at' => $po->sent_at?->format('Y-m-d H:i'),
                'paid' => $po->paidTotal(),
            ],
            'vendor' => $po->vendor,
            'request' => $po->request->only(['id', 'number', 'title', 'status']),
            'funding' => $po->funding ? [...$po->funding->only(['id', 'number', 'status', 'disbursed_amount']), 'remaining' => $po->funding->remaining()] : null,
            'items' => $po->items,
            'receipts' => $po->receipts->map(fn ($g) => [
                'id' => $g->id, 'number' => $g->number, 'received_date' => $g->received_date->format('Y-m-d'), 'delivery_note_no' => $g->delivery_note_no, 'condition' => $g->condition,
                'vendor_score' => $g->vendor_score, 'notes' => $g->notes, 'receiver' => $g->receiver?->name, 'items' => $g->items, 'attachments' => Attachment::panelFor($g),
            ]),
            'payments' => $po->payments->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'paid_at' => $p->paid_at->format('Y-m-d'), 'method' => $p->method, 'reference' => $p->reference, 'notes' => $p->notes, 'payer' => $p->payer?->name]),
            'attachments' => Attachment::panelFor($po),
        ]);
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'order_date' => 'required|date', 'expected_date' => 'nullable|date|after_or_equal:order_date', 'tax_percent' => 'required|numeric|min:0|max:100',
            'payment_terms' => 'nullable|string|max:120', 'delivery_address' => 'nullable|string|max:500', 'notes' => 'nullable|string|max:1000',
        ], ['expected_date.after_or_equal' => 'Tanggal dibutuhkan tidak boleh sebelum tanggal pesan.']);

        try {
            $po = $this->svc->createPurchaseOrder($purchaseRequest, $data, $request->user());
        } catch (\App\Exceptions\WorkflowException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $po)->with('success', "PO {$po->number} dibuat sebagai draf.");
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            [$mailed, $error] = $this->svc->sendPurchaseOrder($purchaseOrder, $request->user());
        } catch (\App\Exceptions\WorkflowException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with($mailed ? 'success' : 'error', $mailed ? "PO dikirim ke {$purchaseOrder->vendor->name} lewat email." : "PO ditandai terkirim. Email ke vendor tidak terkirim: {$error}");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        return $this->guard(fn () => $this->svc->cancelPurchaseOrder($purchaseOrder, $request->user()), 'PO dibatalkan.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'received_date' => 'required|date|before_or_equal:today', 'delivery_note_no' => 'nullable|string|max:60', 'condition' => 'required|in:good,partial_damage,rejected',
            'vendor_score' => 'nullable|integer|min:1|max:5', 'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1', 'lines.*.po_item_id' => 'required|integer', 'lines.*.quantity_received' => 'nullable|numeric|min:0',
            'lines.*.quantity_rejected' => 'nullable|numeric|min:0', 'lines.*.note' => 'nullable|string|max:200',
        ], ['received_date.before_or_equal' => 'Tanggal penerimaan tidak boleh di masa depan.']);

        $data['lines'] = array_map(fn ($l) => $l + ['quantity_received' => 0, 'quantity_rejected' => 0], $data['lines']);

        return $this->guard(fn () => $this->svc->receiveGoods($purchaseOrder, $data, $request->user()), 'Penerimaan dicatat dan Inventaris sudah dikabari.');
    }

    public function pay(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0', 'paid_at' => 'required|date|before_or_equal:today', 'method' => 'required|in:transfer,tunai,cek',
            'reference' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:500',
        ]);

        return $this->guard(fn () => $this->svc->payVendor($purchaseOrder, (float) $data['amount'], $data['paid_at'], $data['method'], $data['reference'] ?? null, $data['notes'] ?? null, $request->user()), 'Pembayaran vendor dicatat.');
    }

    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['vendor', 'items', 'request']);

        return Pdf::loadView('pdf.purchase-order', ['po' => $purchaseOrder])->setPaper('a4')->stream("{$purchaseOrder->number}.pdf");
    }
}
