<?php

namespace App\Http\Controllers;

use App\Models\FundingRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $items = [];
        $add = function (string $type, string $text, string $url) use (&$items) {
            $items[] = compact('type', 'text', 'url');
        };

        if ($user->hasRole('admin') && ($n = User::where('status', 'pending')->count()) > 0) {
            $add('warning', "{$n} pendaftar menunggu persetujuan", route('admin.users.index', ['status' => 'pending']));
        }
        if ($user->can('request.manage') && ($n = PurchaseRequest::where('status', 'submitted')->count()) > 0) {
            $add('info', "{$n} permintaan baru dari Inventaris", route('requests.index', ['status' => 'submitted']));
        }
        if ($user->can('funding.approve') && ($n = FundingRequest::where('status', 'pending_internal')->count()) > 0) {
            $add('warning', "{$n} pengajuan dana menunggu persetujuan internal", route('finance-desk.index', ['tab' => 'waiting']));
        }
        if ($user->can('finance.decide') && ($n = FundingRequest::where('status', 'submitted')->count()) > 0) {
            $add('warning', "{$n} pengajuan dana menunggu keputusan Keuangan", route('finance-desk.index', ['tab' => 'waiting']));
        }
        if ($user->can('finance.disburse') && ($n = FundingRequest::where('status', 'approved')->count()) > 0) {
            $add('info', "{$n} dana disetujui, belum dicairkan", route('finance-desk.index', ['tab' => 'cash']));
        }
        if ($user->can('po.manage') && ($n = PurchaseRequest::where('status', 'funded')->count()) > 0) {
            $add('success', "{$n} permintaan sudah didanai — siap dibuatkan PO", route('requests.index', ['status' => 'funded']));
        }
        if ($user->can('request.manage') && ($n = Report::where('direction', 'in')->whereNull('read_at')->where('counterpart', 'keuangan')->count()) > 0) {
            $add('info', "{$n} kabar baru dari Keuangan", route('finance-desk.index', ['tab' => 'reports']));
        }
        if ($user->can('receiving.manage') && ($n = PurchaseOrder::whereIn('status', ['sent', 'partial'])->whereDate('expected_date', '<', now()->toDateString())->count()) > 0) {
            $add('danger', "{$n} PO lewat tanggal kedatangan", route('purchase-orders.index', ['status' => 'sent']));
        }
        if ($user->can('handover.manage') && ($n = PurchaseRequest::where('status', 'received')->count()) > 0) {
            $add('info', "{$n} permintaan siap diserahkan ke Inventaris", route('requests.index', ['status' => 'received']));
        }

        return response()->json(['count' => count($items), 'items' => $items]);
    }
}
