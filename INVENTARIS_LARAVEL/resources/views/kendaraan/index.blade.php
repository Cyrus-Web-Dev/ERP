@extends('layouts.app')

@section('title', 'Kendaraan Operasional - Sistem Inventaris')
@section('page-title', 'Kendaraan Operasional')

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari merk atau plat nomor..."
                   class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 flex-1 max-w-md">
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-search"></i> Cari</button>
            @if($search)
                <a href="{{ route('kendaraan.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
            @endif
        </form>

        @if(auth()->user()->level_akses === 'super_admin')
            <a href="{{ route('kendaraan.create') }}" class="shrink-0 px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium text-center">
                <i class="bi bi-plus-circle"></i> Tambah Kendaraan
            </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Foto</th>
                        <th class="text-left px-5 py-3">Merk</th>
                        <th class="text-left px-5 py-3">Plat Nomor</th>
                        <th class="text-right px-5 py-3">Jumlah</th>
                        <th class="text-right px-5 py-3">Dipinjam</th>
                        <th class="text-right px-5 py-3">Total Nilai</th>
                        <th class="text-left px-5 py-3">Status</th>
                        @if(auth()->user()->level_akses === 'super_admin')
                            <th class="text-right px-5 py-3">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($kendaraan as $k)
                        <tr>
                            <td class="px-5 py-3">
                                @if($k->foto_kendaraan)
                                    <img src="{{ asset('storage/mobil/'.$k->foto_kendaraan) }}" class="w-12 h-12 rounded-lg object-cover border border-slate-200 cursor-zoom-in"
                                         onclick="zoomImage(this.src, '{{ addslashes($k->merk) }} ({{ $k->plat_nomor }})')">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-300"><i class="bi bi-image"></i></div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $k->merk }}</td>
                            <td class="px-5 py-3"><span class="text-xs font-mono px-2 py-1 rounded bg-slate-100 text-slate-600">{{ $k->plat_nomor }}</span></td>
                            <td class="px-5 py-3 text-right text-slate-600">{{ $k->jumlah }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">{{ $k->sedang_dipinjam }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($k->total_keseluruhan, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $statusColor = match($k->status) {
                                        'tersedia' => 'bg-emerald-100 text-emerald-700',
                                        'perbaikan' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-200 text-slate-600',
                                    };
                                @endphp
                                <span class="text-xs px-2 py-1 rounded-full {{ $statusColor }}">{{ ucfirst($k->status) }}</span>
                            </td>
                            @if(auth()->user()->level_akses === 'super_admin')
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('kendaraan.edit', $k) }}" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium"><i class="bi bi-pencil"></i></a>
                                        <form id="hapus-kendaraan-{{ $k->id_kendaraan }}" method="POST" action="{{ route('kendaraan.destroy', $k) }}">@csrf @method('DELETE')</form>
                                        <button type="button" onclick="confirmHapus('hapus-kendaraan-{{ $k->id_kendaraan }}', { title: 'Hapus {{ addslashes($k->merk) }} ({{ $k->plat_nomor }})?' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Belum ada data kendaraan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($kendaraan->hasPages())
        <div>{{ $kendaraan->links() }}</div>
    @endif
</div>
@endsection
