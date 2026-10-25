<?php

namespace App\Http\Middleware;

use App\Models\FundingRequest;
use App\Models\PurchaseRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use App\Support\Workflow;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang dikirim ke SETIAP halaman Vue ($page.props.*).
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'job_title' => $user->job_title,
                    'avatar_url' => $user->avatar_url,
                    'role_label' => $user->roleLabel(),
                    'position' => $user->positionLabel(),
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            // Jumlah pendaftar yang menunggu persetujuan (lencana di menu Kelola Pengguna; hanya untuk Manager)
            'pending_users' => fn () => $user && $user->hasRole('admin') ? User::where('status', 'pending')->count() : 0,
            'workflow' => fn () => Workflow::payload(),
            // Lencana menu: kabar Inventaris belum dibaca + permintaan baru; pengajuan dana menunggu / kabar Keuangan belum dibaca.
            'badges' => fn () => $user ? [
                'inventory' => Report::where('counterpart', 'inventaris')->where('direction', 'in')->whereNull('read_at')->count(),
                'finance' => FundingRequest::whereIn('status', ['pending_internal', 'submitted'])->count() + Report::where('counterpart', 'keuangan')->where('direction', 'in')->whereNull('read_at')->count(),
                'requests' => PurchaseRequest::where('status', 'submitted')->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
