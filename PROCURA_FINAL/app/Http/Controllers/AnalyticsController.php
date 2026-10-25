<?php

namespace App\Http\Controllers;

use App\Models\FundingRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);

        $monthly = PurchaseOrder::where('status', '!=', 'cancelled')->whereYear('order_date', $year)
            ->select(DB::raw('extract(month from order_date)::int as m'), DB::raw('sum(total) as total'))->groupBy('m')->pluck('total', 'm');
        $months = collect(range(1, 12))->map(fn ($m) => (float) ($monthly[$m] ?? 0))->all();

        $byCategory = PurchaseRequest::join('purchase_orders', 'purchase_orders.purchase_request_id', '=', 'purchase_requests.id')
            ->where('purchase_orders.status', '!=', 'cancelled')->whereYear('purchase_orders.order_date', $year)
            ->select('purchase_requests.category', DB::raw('sum(purchase_orders.total) as total'))->groupBy('purchase_requests.category')->orderByDesc('total')->get()
            ->map(fn ($r) => ['key' => $r->category, 'label' => config("procura.categories.{$r->category}", $r->category), 'total' => (float) $r->total]);

        $topVendors = Vendor::withSum(['orders as spend' => fn ($q) => $q->where('status', '!=', 'cancelled')->whereYear('order_date', $year)], 'total')
            ->withCount(['orders as orders_n' => fn ($q) => $q->where('status', '!=', 'cancelled')->whereYear('order_date', $year)])
            ->orderByDesc('spend')->limit(6)->get()->filter(fn ($v) => $v->spend > 0)->values()
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'spend' => (float) $v->spend, 'orders' => $v->orders_n, 'rating' => $v->rating]);

        // Selisih perkiraan Inventaris vs realisasi (hanya permintaan yang sudah punya PO)
        $variance = PurchaseRequest::join('purchase_orders', 'purchase_orders.purchase_request_id', '=', 'purchase_requests.id')
            ->where('purchase_orders.status', '!=', 'cancelled')->whereYear('purchase_orders.order_date', $year)
            ->selectRaw('sum(purchase_requests.estimated_total * (1 + ?::numeric / 100.0)) as est, sum(purchase_orders.total) as act', [config('procura.default_tax_percent')])->first();

        $cycle = PurchaseRequest::where('status', 'completed')->whereYear('completed_at', $year)
            ->select(DB::raw('avg(extract(epoch from (completed_at - created_at)) / 86400) as d'))->value('d');

        $decisions = FundingRequest::whereYear('created_at', $year)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return Inertia::render('Analytics/Index', [
            'year' => $year,
            'years' => range(now()->year, max(now()->year - 4, 2024)),
            'months' => $months,
            'byCategory' => $byCategory,
            'topVendors' => $topVendors,
            'summary' => [
                'spend' => array_sum($months),
                'orders' => PurchaseOrder::where('status', '!=', 'cancelled')->whereYear('order_date', $year)->count(),
                'requests' => PurchaseRequest::whereYear('created_at', $year)->count(),
                'cycle_days' => $cycle !== null ? round((float) $cycle, 1) : null,
                'estimated' => (float) ($variance->est ?? 0),
                'actual' => (float) ($variance->act ?? 0),
                'returned' => (float) FundingRequest::where('status', 'settled')->whereYear('settled_at', $year)->sum('returned_amount'),
            ],
            'decisions' => $decisions,
        ]);
    }
}
