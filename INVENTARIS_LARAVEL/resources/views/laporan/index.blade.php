@extends('layouts.app')

@section('title', 'Laporan Inventaris - Sistem Inventaris')
@section('page-title', 'Laporan Inventaris')

@section('content')
<div class="space-y-5">

    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Cari Nama</label>
            <input type="text" name="search" value="{{ $filter['search'] }}" placeholder="Nama barang..."
                   class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sumber</label>
            <select name="sumber" class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
                <option value="">Semua Sumber</option>
                @foreach($sumberList as $s)
                    <option value="{{ $s }}" {{ $filter['sumber'] === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Dari Tanggal</label>
            <input type="date" name="tgl_dari" value="{{ $filter['tgl_dari'] }}"
                   class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sampai Tanggal</label>
            <input type="date" name="tgl_sampai" value="{{ $filter['tgl_sampai'] }}"
                   class="px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
        </div>

        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-funnel"></i> Terapkan</button>
        <a href="{{ route('laporan.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>

        <div class="flex-1"></div>

        <a href="{{ route('laporan.export-pdf', $filter) }}" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </a>
        <a href="{{ route('laporan.export-csv', $filter) }}" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs text-slate-500">Total Item</p>
            <p class="text-lg font-bold text-slate-800">{{ $gabungan->count() }}</p>
        </div>
        @foreach($sumberList as $s)
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500">{{ $s }}</p>
                <p class="text-sm font-bold text-slate-800">Rp {{ number_format($totalPerSumber[$s] ?? 0, 0, ',', '.') }}</p>
                <p class="text-[11px] text-slate-400">{{ $jumlahPerSumber[$s] ?? 0 }} item</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Sumber</th>
                        <th class="text-left px-5 py-3">Nama</th>
                        <th class="text-left px-5 py-3">Kategori/Merk</th>
                        <th class="text-right px-5 py-3">Jumlah</th>
                        <th class="text-right px-5 py-3">Harga</th>
                        <th class="text-right px-5 py-3">Total</th>
                        <th class="text-left px-5 py-3">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($gabungan as $row)
                        @php
                            $badge = match($row['sumber']) {
                                'Elektronik' => 'bg-indigo-100 text-indigo-700',
                                'Non Elektronik' => 'bg-emerald-100 text-emerald-700',
                                'Perabotan' => 'bg-amber-100 text-amber-700',
                                'Kendaraan' => 'bg-red-100 text-red-700',
                                default => 'bg-teal-100 text-teal-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-5 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $badge }}">{{ $row['sumber'] }}</span></td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $row['nama'] }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $row['kategori'] }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">{{ $row['jumlah'] }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($row['harga'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-800">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $row['tanggal']?->format('d M Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Tidak ada data yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
                @if($gabungan->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold text-slate-800">
                            <td colspan="5" class="px-5 py-3 text-right">Total Keseluruhan</td>
                            <td class="px-5 py-3 text-right">Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
