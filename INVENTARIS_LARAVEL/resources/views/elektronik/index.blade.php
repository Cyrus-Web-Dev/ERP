@extends('layouts.app')

@section('title', 'Data Barang Elektronik - Sistem Inventaris')
@section('page-title', 'Data Barang Elektronik')

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, kategori, atau kode aset..."
                   class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 flex-1 max-w-md">

            <select name="kategori" class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">Semua Kategori</option>
                @foreach($kategoriList as $kat)
                    <option value="{{ $kat }}" {{ $kategoriFilter === $kat ? 'selected' : '' }}>{{ $kat }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium">
                <i class="bi bi-search"></i> Cari
            </button>
            @if($search || $kategoriFilter)
                <a href="{{ route('elektronik.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
            @endif
        </form>

        @if(auth()->user()->level_akses === 'super_admin')
            <a href="{{ route('elektronik.create') }}"
               class="shrink-0 px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium text-center">
                <i class="bi bi-plus-circle"></i> Tambah Barang
            </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Foto</th>
                        <th class="text-left px-5 py-3">Nama Barang</th>
                        <th class="text-left px-5 py-3">Kategori</th>
                        <th class="text-left px-5 py-3">Kode Aset</th>
                        <th class="text-right px-5 py-3">Jumlah</th>
                        <th class="text-right px-5 py-3">Harga</th>
                        <th class="text-left px-5 py-3">Tgl Masuk</th>
                        @if(auth()->user()->level_akses === 'super_admin')
                            <th class="text-right px-5 py-3">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($barang as $b)
                        <tr>
                            <td class="px-5 py-3">
                                @if($b->foto_barang)
                                    <img src="{{ asset('storage/elektronik/'.$b->foto_barang) }}" class="w-12 h-12 rounded-lg object-cover border border-slate-200 cursor-zoom-in"
                                         onclick="zoomImage(this.src, '{{ addslashes($b->nama_barang) }}')">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-300">
                                        <i class="bi bi-image"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $b->nama_barang }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $b->kategori_barang }}</td>
                            <td class="px-5 py-3"><span class="text-xs font-mono px-2 py-1 rounded bg-slate-100 text-slate-600">{{ $b->kode_aset }}</span></td>
                            <td class="px-5 py-3 text-right text-slate-600">{{ $b->jumlah }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($b->harga, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $b->tgl->format('d M Y') }}</td>
                            @if(auth()->user()->level_akses === 'super_admin')
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('elektronik.edit', $b) }}"
                                           class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form id="hapus-barang-{{ $b->id }}" method="POST" action="{{ route('elektronik.destroy', $b) }}">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <button type="button" onclick="confirmHapus('hapus-barang-{{ $b->id }}', { title: 'Hapus {{ addslashes($b->nama_barang) }}?' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                                Belum ada data barang elektronik.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($barang->hasPages())
        <div>{{ $barang->links() }}</div>
    @endif
</div>
@endsection
