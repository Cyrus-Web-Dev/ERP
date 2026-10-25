<?php

namespace App\Http\Controllers;

use App\Models\Handover;
use App\Models\PurchaseRequest;
use App\Models\Report;
use App\Services\ProcurementService;
use App\Support\Workflow;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Meja Inventaris: laporan masuk dari Inventaris (permintaan) & laporan keluar ke Inventaris (kabar, serah terima). */
class InventoryDeskController extends Controller
{
    public function __construct(private ProcurementService $svc)
    {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['in', 'out', 'handover'], true) ? $request->tab : 'in';

        $reports = Report::with(['request:id,number,title', 'author:id,name'])
            ->where('counterpart', 'inventaris')
            ->when($tab !== 'handover', fn ($q) => $q->where('direction', $tab))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('subject', 'ilike', "%{$s}%")->orWhere('number', 'ilike', "%{$s}%")))
            ->orderByDesc('id')->paginate(12)->withQueryString()
            ->through(fn (Report $r) => $this->row($r));

        return Inertia::render('InventoryDesk/Index', [
            'tab' => $tab,
            'reports' => $reports,
            'filters' => $request->only(['search']),
            'inbox' => PurchaseRequest::where('status', 'submitted')->orderByDesc('id')->limit(6)->get(['id', 'number', 'title', 'requester_name', 'requester_unit', 'priority', 'needed_date'])
                ->map(fn ($r) => [...$r->toArray(), 'needed_date' => $r->needed_date?->format('Y-m-d')]),
            'awaitingConfirm' => Handover::with('request:id,number,title')->where('status', 'handed_over')->orderByDesc('id')->limit(8)->get()
                ->map(fn ($h) => ['id' => $h->id, 'number' => $h->number, 'handover_date' => $h->handover_date->format('Y-m-d'), 'receiver_name' => $h->receiver_name, 'request_id' => $h->purchase_request_id, 'title' => $h->request?->title]),
            'unread' => Report::where('counterpart', 'inventaris')->where('direction', 'in')->whereNull('read_at')->count(),
            'activeRequests' => PurchaseRequest::whereNotIn('status', ['completed', 'rejected', 'cancelled'])->orderByDesc('id')->limit(60)->get(['id', 'number', 'title']),
            'kinds' => Workflow::REPORT_KIND,
        ]);
    }

    /** Catat laporan manual (arah masuk = dari Inventaris, keluar = kabar dari Pengadaan). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'direction' => 'required|in:in,out',
            'subject' => 'required|string|max:200',
            'body' => 'nullable|string|max:3000',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
        ], ['subject.required' => 'Isi perihal laporan.']);

        $pr = $data['purchase_request_id'] ? PurchaseRequest::find($data['purchase_request_id']) : null;
        $r = $this->svc->report('inventaris', $data['direction'], 'note', $data['subject'], $data['body'] ?? null, $pr, null, false, $request->user());
        if ($pr) {
            $this->svc->event($pr, 'report', ($data['direction'] === 'in' ? 'Laporan dari Inventaris: ' : 'Laporan ke Inventaris: ').$data['subject'], $request->user());
        }

        return back()->with('success', "Laporan {$r->number} dicatat.");
    }

    public function read(Report $report)
    {
        abort_unless($report->counterpart === 'inventaris', 404);
        $report->update(['read_at' => $report->read_at ?? now()]);

        return back();
    }

    public function readAll()
    {
        Report::where('counterpart', 'inventaris')->where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua laporan Inventaris ditandai sudah dibaca.');
    }

    /** Konfirmasi serah terima (dipakai sisi Inventaris dalam mode mandiri; sistem Inventaris memakai REST API). */
    public function confirmHandover(Request $request, Handover $handover)
    {
        $user = $request->user();

        return $this->guard(fn () => $this->svc->confirmHandover($handover, $user->name, $user), 'Serah terima dikonfirmasi.');
    }

    private function row(Report $r): array
    {
        return [
            'id' => $r->id, 'number' => $r->number, 'direction' => $r->direction, 'kind' => $r->kind, 'subject' => $r->subject, 'body' => $r->body,
            'is_auto' => $r->is_auto, 'read' => (bool) $r->read_at, 'created_at' => $r->created_at->format('Y-m-d H:i'),
            'request_id' => $r->purchase_request_id, 'request_number' => $r->request?->number, 'author' => $r->author?->name,
        ];
    }
}
