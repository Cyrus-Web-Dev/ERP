@extends('layouts.app')

@section('title', 'Data Gedung Perusahaan - Sistem Inventaris')
@section('page-title', 'Data Gedung Perusahaan')

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, kode, atau lokasi..."
                   class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 flex-1 max-w-md">
            <select name="kondisi" class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">Semua Kondisi</option>
                @foreach($kondisiList as $k)
                    <option value="{{ $k }}" {{ $kondisiFilter === $k ? 'selected' : '' }}>{{ $k }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-search"></i> Cari</button>
            @if($search || $kondisiFilter)
                <a href="{{ route('bangunan.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
            @endif
        </form>

        @if(auth()->user()->level_akses === 'super_admin')
            <a href="{{ route('bangunan.create') }}" class="shrink-0 px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium text-center">
                <i class="bi bi-plus-circle"></i> Tambah Gedung
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($bangunan as $b)
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="h-36 bg-slate-100">
                    @if($b->foto_gedung)
                        <img src="{{ asset('storage/gedung/'.$b->foto_gedung) }}" class="w-full h-full object-cover cursor-zoom-in"
                             onclick="zoomImage(this.src, '{{ addslashes($b->nama_gedung) }}')">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-slate-300"><i class="bi bi-building text-4xl"></i></div>
                    @endif
                </div>
                <div class="p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-mono px-2 py-1 rounded bg-slate-100 text-slate-600">{{ $b->kode_gedung }}</span>
                        @php
                            $kondisiColor = match($b->kondisi) {
                                'Baik' => 'bg-emerald-100 text-emerald-700',
                                'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                default => 'bg-red-100 text-red-700',
                            };
                        @endphp
                        <span class="text-xs px-2 py-1 rounded-full {{ $kondisiColor }}">{{ $b->kondisi }}</span>
                    </div>
                    <h3 class="font-semibold text-slate-800">{{ $b->nama_gedung }}</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ $b->lokasi ?: '-' }}</p>
                    <p class="text-sm font-medium text-slate-700 mt-2">Rp {{ number_format($b->harga_gedung, 0, ',', '.') }}</p>
                    @if($b->pengelola)
                        <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100">
                            @if($b->foto_pengelola)
                                <img src="{{ asset('storage/pengelola/'.$b->foto_pengelola) }}"
                                     class="w-8 h-8 rounded-full object-cover border border-slate-200 cursor-zoom-in"
                                     onclick="zoomImage(this.src, 'Pengelola: {{ addslashes($b->pengelola) }}')">
                            @else
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-300 text-xs"><i class="bi bi-person"></i></div>
                            @endif
                            <p class="text-xs text-slate-500">{{ $b->pengelola }} <span class="text-slate-400">({{ $b->jabatan_pengelola ?: '-' }})</span></p>
                        </div>
                    @endif

                    @if(auth()->user()->level_akses === 'super_admin')
                        <div class="flex gap-2 mt-4">
                            <a href="{{ route('bangunan.edit', $b) }}" class="flex-1 text-center px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium"><i class="bi bi-pencil"></i> Ubah</a>
                            <form id="hapus-bangunan-{{ $b->id }}" method="POST" action="{{ route('bangunan.destroy', $b) }}">@csrf @method('DELETE')</form>
                            <button type="button" onclick="confirmHapus('hapus-bangunan-{{ $b->id }}', { title: 'Hapus {{ addslashes($b->nama_gedung) }}?' })"
                                    class="flex-1 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium"><i class="bi bi-trash"></i> Hapus</button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-slate-200 py-12 text-center text-slate-400">
                Belum ada data gedung.
            </div>
        @endforelse
    </div>

    @if($bangunan->hasPages())
        <div>{{ $bangunan->links() }}</div>
    @endif
</div>
@endsection
