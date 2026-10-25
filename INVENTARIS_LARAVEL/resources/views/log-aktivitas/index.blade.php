@extends('layouts.app')

@section('title', 'Log Aktivitas - Sistem Inventaris')
@section('page-title', 'Log Aktivitas')

@section('content')
<div class="space-y-5">

    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Module</label>
            <select name="module" class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
                <option value="">Semua Module</option>
                @foreach($moduleList as $m)
                    <option value="{{ $m }}" {{ $filter['module'] === $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">User</label>
            <select name="username" class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
                <option value="">Semua User</option>
                @foreach($usernameList as $u)
                    <option value="{{ $u }}" {{ $filter['username'] === $u ? 'selected' : '' }}>{{ $u }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Dari Tanggal</label>
            <input type="date" name="tgl_dari" value="{{ $filter['tglDari'] }}"
                   class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sampai Tanggal</label>
            <input type="date" name="tgl_sampai" value="{{ $filter['tglSampai'] }}"
                   class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-funnel"></i> Terapkan</button>
        <a href="{{ route('activity-logs.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Waktu</th>
                        <th class="text-left px-5 py-3">User</th>
                        <th class="text-left px-5 py-3">Module</th>
                        <th class="text-left px-5 py-3">Aksi</th>
                        <th class="text-left px-5 py-3">Deskripsi</th>
                        <th class="text-left px-5 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        @php
                            $aksiColor = match($log->action) {
                                'INSERT' => 'bg-emerald-100 text-emerald-700',
                                'UPDATE' => 'bg-amber-100 text-amber-700',
                                'DELETE' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr>
                            <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $log->username ?? '-' }}</td>
                            <td class="px-5 py-3"><span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ $log->module }}</span></td>
                            <td class="px-5 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $aksiColor }}">{{ $log->action_display ?? $log->action }}</span></td>
                            <td class="px-5 py-3 text-slate-600">{{ $log->description }}</td>
                            <td class="px-5 py-3 text-slate-400 text-xs">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Belum ada aktivitas tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($logs->hasPages())
        <div>{{ $logs->links() }}</div>
    @endif
</div>
@endsection
