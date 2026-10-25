<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\FundingRequest;
use App\Models\PurchaseRequest;
use App\Models\Report;
use App\Services\ProcurementService;
use App\Support\Workflow;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Meja Keuangan: pengajuan dana ke Keuangan, KEPUTUSAN Keuangan (apa pun hasilnya tampil di sini),
 * pencairan dana, dan LPJ. Dalam sistem terpadu, sistem Keuangan memakai REST API untuk hal yang sama.
 */
class FinanceDeskController extends Controller
{
    public function __construct(private ProcurementService $svc)
    {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['waiting', 'decided', 'cash', 'settle', 'all', 'reports'], true) ? $request->tab : 'waiting';

        $funds = FundingRequest::with(['request:id,number,title,category'])
            ->when($tab === 'waiting', fn ($q) => $q->whereIn('status', ['pending_internal', 'submitted']))
            ->when($tab === 'decided', fn ($q) => $q->whereIn('status', ['approved', 'rejected', 'revision']))
            ->when($tab === 'cash', fn ($q) => $q->whereIn('status', ['approved', 'disbursed']))
            ->when($tab === 'settle', fn ($q) => $q->where('status', 'settled'))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'ilike', "%{$s}%")->orWhere('finance_ref', 'ilike', "%{$s}%")->orWhereHas('request', fn ($r) => $r->where('title', 'ilike', "%{$s}%"))))
            ->orderByDesc('id');

        $reports = $tab === 'reports'
            ? Report::with('request:id,number')->where('counterpart', 'keuangan')->orderByDesc('id')->paginate(12)->withQueryString()
                ->through(fn (Report $r) => ['id' => $r->id, 'number' => $r->number, 'direction' => $r->direction, 'kind' => $r->kind, 'subject' => $r->subject, 'body' => $r->body, 'is_auto' => $r->is_auto,
                    'read' => (bool) $r->read_at, 'created_at' => $r->created_at->format('Y-m-d H:i'), 'request_id' => $r->purchase_request_id, 'request_number' => $r->request?->number, 'funding_id' => $r->funding_request_id])
            : null;

        return Inertia::render('FinanceDesk/Index', [
            'tab' => $tab,
            'filters' => $request->only(['search']),
            'funds' => $tab === 'reports' ? null : $funds->paginate(12)->withQueryString()->through(fn (FundingRequest $f) => $this->row($f)),
            'reports' => $reports,
            'counts' => [
                'waiting' => FundingRequest::whereIn('status', ['pending_internal', 'submitted'])->count(),
                'decided' => FundingRequest::whereIn('status', ['approved', 'rejected', 'revision'])->count(),
                'cash' => FundingRequest::whereIn('status', ['approved', 'disbursed'])->count(),
                'unread' => Report::where('counterpart', 'keuangan')->where('direction', 'in')->whereNull('read_at')->count(),
            ],
            'totals' => [
                'requested' => (float) FundingRequest::whereIn('status', ['pending_internal', 'submitted'])->sum('amount'),
                'approved' => (float) FundingRequest::where('status', 'approved')->sum('approved_amount'),
                'in_hand' => (float) FundingRequest::where('status', 'disbursed')->sum('disbursed_amount') - (float) \App\Models\VendorPayment::whereIn('funding_request_id', FundingRequest::where('status', 'disbursed')->pluck('id'))->sum('amount'),
            ],
        ]);
    }

    public function show(FundingRequest $funding)
    {
        $funding->load(['request.items', 'quotation.vendor', 'submitter:id,name', 'payments.order:id,number']);
        $pr = $funding->request;

        return Inertia::render('FinanceDesk/Show', [
            'funding' => [
                ...$funding->toArray(),
                'submitter' => $funding->submitter?->name,
                'remaining' => $funding->remaining(),
                'paid' => (float) $funding->payments->sum('amount'),
                'attachments' => Attachment::panelFor($funding),
            ],
            'pr' => [...$pr->only(['id', 'number', 'title', 'category', 'requester_name', 'requester_unit', 'priority', 'justification', 'estimated_total', 'status'])],
            'items' => $pr->items,
            'quotation' => $funding->quotation ? ['number' => $funding->quotation->number, 'vendor' => $funding->quotation->vendor->name, 'total' => $funding->quotation->total, 'lead_time_days' => $funding->quotation->lead_time_days, 'selection_reason' => $funding->quotation->selection_reason] : null,
            'payments' => $funding->payments->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'paid_at' => $p->paid_at->format('Y-m-d'), 'method' => $p->method, 'reference' => $p->reference, 'po' => $p->order?->number]),
            'threshold' => config('procura.internal_approval_threshold'),
            'reports' => Report::where('funding_request_id', $funding->id)->orderByDesc('id')->get(['id', 'number', 'direction', 'kind', 'subject', 'body', 'created_at'])->map(fn ($r) => [...$r->toArray(), 'created_at' => $r->created_at->format('Y-m-d H:i')]),
        ]);
    }

    public function approveInternal(Request $request, FundingRequest $funding)
    {
        return $this->guard(fn () => $this->svc->approveInternal($funding, $request->user()), 'Disetujui internal dan diteruskan ke Keuangan.');
    }

    public function resubmit(Request $request, FundingRequest $funding)
    {
        $data = $request->validate(['amount' => 'required|numeric|gt:0', 'purpose' => 'required|string|max:1000']);

        return $this->guard(fn () => $this->svc->resubmitFunding($funding, (float) $data['amount'], $data['purpose'], $request->user()), 'Pengajuan diajukan ulang ke Keuangan.');
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate(['amount' => 'required|numeric|gt:0', 'purpose' => 'required|string|min:5|max:1000'], ['purpose.required' => 'Tulis tujuan penggunaan dana.', 'amount.gt' => 'Nominal harus lebih dari nol.']);

        return $this->guard(fn () => $this->svc->submitFunding($purchaseRequest, (float) $data['amount'], $data['purpose'], $request->user()), 'Pengajuan dana dibuat.');
    }

    public function decide(Request $request, FundingRequest $funding)
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject,revision',
            'approved_amount' => 'nullable|numeric|gt:0',
            'note' => 'nullable|string|max:1000',
            'finance_ref' => 'nullable|string|max:60',
        ], ['decision.required' => 'Pilih keputusan.']);
        $user = $request->user();

        return $this->guard(fn () => $this->svc->financeDecide($funding, $data['decision'], isset($data['approved_amount']) ? (float) $data['approved_amount'] : null, $data['note'] ?? null, $user->name, $user, $data['finance_ref'] ?? null), 'Keputusan Keuangan dicatat dan Pengadaan sudah dilapori.');
    }

    public function disburse(Request $request, FundingRequest $funding)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0', 'method' => 'required|in:transfer,tunai,cek', 'reference' => 'nullable|string|max:60',
        ]);
        $user = $request->user();

        return $this->guard(fn () => $this->svc->disburse($funding, (float) $data['amount'], $data['method'], $data['reference'] ?? null, $user->name, $user), 'Dana dicairkan. Pengadaan boleh membuat PO.');
    }

    public function settle(Request $request, FundingRequest $funding)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->guard(fn () => $this->svc->settleFunding($funding, $data['note'] ?? null, $request->user()), 'LPJ dikirim ke Keuangan.');
    }

    public function storeReport(Request $request)
    {
        $data = $request->validate([
            'direction' => 'required|in:in,out', 'subject' => 'required|string|max:200', 'body' => 'nullable|string|max:3000',
            'funding_request_id' => 'nullable|exists:funding_requests,id',
        ], ['subject.required' => 'Isi perihal laporan.']);

        $f = $data['funding_request_id'] ? FundingRequest::find($data['funding_request_id']) : null;
        $r = $this->svc->report('keuangan', $data['direction'], 'note', $data['subject'], $data['body'] ?? null, $f?->request, $f, false, $request->user());

        return back()->with('success', "Laporan {$r->number} dicatat.");
    }

    public function readReport(Report $report)
    {
        abort_unless($report->counterpart === 'keuangan', 404);
        $report->update(['read_at' => $report->read_at ?? now()]);

        return back();
    }

    private function row(FundingRequest $f): array
    {
        return [
            'id' => $f->id, 'number' => $f->number, 'status' => $f->status, 'amount' => $f->amount, 'approved_amount' => $f->approved_amount,
            'disbursed_amount' => $f->disbursed_amount, 'purpose' => $f->purpose, 'decided_by_name' => $f->decided_by_name, 'decision_note' => $f->decision_note,
            'submitted_at' => $f->submitted_at?->format('Y-m-d'), 'decided_at' => $f->decided_at?->format('Y-m-d'), 'finance_ref' => $f->finance_ref,
            'request' => ['id' => $f->request->id, 'number' => $f->request->number, 'title' => $f->request->title, 'category' => $f->request->category],
        ];
    }
}
