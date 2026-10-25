<?php

namespace App\Http\Controllers;

use App\Models\FundingRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Report;
use App\Models\RequestEvent;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $open = PurchaseRequest::whereNotIn('status', ['completed', 'rejected', 'cancelled']);
        $pipeline = PurchaseRequest::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $soon = now()->addDays((int) config('procura.due_soon_days'))->toDateString();
        $attention = (clone $open)->where(fn ($q) => $q->where('priority', 'urgent')->orWhere(fn ($w) => $w->whereNotNull('needed_date')->whereDate('needed_date', '<=', $soon)))
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 else 2 end")->orderBy('needed_date')->limit(6)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'number' => $r->number, 'title' => $r->title, 'status' => $r->status, 'priority' => $r->priority,
                'needed_date' => $r->needed_date?->format('Y-m-d'), 'late' => $r->needed_date && $r->needed_date->isPast(),
            ]);

        $fundingWaiting = FundingRequest::where('status', 'submitted')->count();
        $fundingAwaitingCash = FundingRequest::where('status', 'approved')->count();
        $committed = (float) FundingRequest::whereIn('status', ['disbursed'])->sum('disbursed_amount');
        $spentYear = (float) FundingRequest::where('status', 'settled')->whereYear('settled_at', now()->year)->sum('actual_spent');
        $savedYear = (float) FundingRequest::where('status', 'settled')->whereYear('settled_at', now()->year)->sum('returned_amount');

        // Rata-rata hari dari permintaan masuk sampai selesai (12 bulan terakhir)
        $avgDays = PurchaseRequest::where('status', 'completed')->where('completed_at', '>=', now()->subYear())
            ->select(DB::raw('avg(extract(epoch from (completed_at - created_at)) / 86400) as d'))->value('d');

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'open' => $open->count(),
                'inbox' => (int) ($pipeline['submitted'] ?? 0),
                'funding_waiting' => $fundingWaiting,
                'funding_awaiting_cash' => $fundingAwaitingCash,
                'po_active' => PurchaseOrder::whereIn('status', ['sent', 'partial'])->count(),
                'committed' => $committed,
                'spent_year' => $spentYear,
                'saved_year' => $savedYear,
                'avg_days' => $avgDays !== null ? round((float) $avgDays, 1) : null,
                'unread_inventaris' => Report::where('counterpart', 'inventaris')->where('direction', 'in')->whereNull('read_at')->count(),
                'unread_keuangan' => Report::where('counterpart', 'keuangan')->where('direction', 'in')->whereNull('read_at')->count(),
            ],
            'pipeline' => $pipeline,
            'attention' => $attention,
            'events' => RequestEvent::with('request:id,number,title')->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get()
                ->map(fn ($e) => ['id' => $e->id, 'description' => $e->description, 'created_at' => $e->created_at->format('Y-m-d H:i'), 'request_id' => $e->purchase_request_id, 'number' => $e->request?->number]),
        ]);
    }
}
