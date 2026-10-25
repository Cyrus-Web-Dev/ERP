@extends('layouts.app')

@section('title', 'Penggunaan Barang Elektronik - Sistem Inventaris')
@section('page-title', 'Penggunaan Barang Elektronik')

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama karyawan atau barang..."
                   class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 flex-1 max-w-md">
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-search"></i> Cari</button>
            @if($search)
                <a href="{{ route('penggunaan.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
            @endif
        </form>

        @if(auth()->user()->level_akses === 'super_admin')
            <a href="{{ route('penggunaan.create') }}" class="shrink-0 px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium text-center">
                <i class="bi bi-plus-circle"></i> Tambah Penggunaan
            </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Foto</th>
                        <th class="text-left px-5 py-3">Nama Karyawan</th>
                        <th class="text-left px-5 py-3">Barang</th>
                        <th class="text-right px-5 py-3">Jumlah</th>
                        <th class="text-left px-5 py-3">Tanggal</th>
                        @if(auth()->user()->level_akses === 'super_admin')
                            <th class="text-right px-5 py-3">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($penggunaan as $p)
                        <tr>
                            <td class="px-5 py-3">
                                @if($p->foto_karyawan)
                                    <img src="{{ asset('storage/foto_karyawan/'.$p->foto_karyawan) }}" class="w-10 h-10 rounded-full object-cover border border-slate-200 cursor-zoom-in"
                                         onclick="zoomImage(this.src, '{{ addslashes($p->nama_karyawan) }}')">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-300"><i class="bi bi-person"></i></div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $p->nama_karyawan }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $p->barang->nama_barang ?? '(barang dihapus)' }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">{{ $p->jumlah }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $p->created_at->format('d M Y H:i') }}</td>
                            @if(auth()->user()->level_akses === 'super_admin')
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('penggunaan.edit', $p) }}" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium"><i class="bi bi-pencil"></i></a>
                                        <form id="hapus-penggunaan-{{ $p->id }}" method="POST" action="{{ route('penggunaan.destroy', $p) }}">@csrf @method('DELETE')</form>
                                        <button type="button" onclick="confirmHapus('hapus-penggunaan-{{ $p->id }}', { title: 'Hapus data ini?', text: 'Stok barang akan dikembalikan.' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Belum ada data penggunaan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($penggunaan->hasPages())
        <div>{{ $penggunaan->links() }}</div>
    @endif
</div>
@endsection
