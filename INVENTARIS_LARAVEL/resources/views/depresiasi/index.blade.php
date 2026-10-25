@extends('layouts.app')

@section('title', 'Depresiasi Aset - Sistem Inventaris')
@section('page-title', 'Depresiasi Aset')

@section('content')
<div class="space-y-5">

    <div class="bg-brand-50 border border-brand-100 text-brand-800 rounded-xl p-4 text-sm">
        <i class="bi bi-info-circle mr-1"></i>
        Dihitung dengan metode <strong>garis lurus (straight-line)</strong>: Nilai Buku = Harga Perolehan &minus; (Harga Perolehan &divide; Masa Manfaat &times; Umur). Masa manfaat: Elektronik 4th, Non Elektronik 5th, Perabotan &amp; Kendaraan 8th, Gedung 20th.
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500">Total Harga Perolehan</p>
            <p class="text-xl font-bold text-slate-800 mt-1">Rp {{ number_format($ringkasan['totalPerolehan'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500">Akumulasi Penyusutan</p>
            <p class="text-xl font-bold text-red-600 mt-1">Rp {{ number_format($ringkasan['totalPenyusutan'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs text-slate-500">Total Nilai Buku Saat Ini</p>
            <p class="text-xl font-bold text-emerald-600 mt-1">Rp {{ number_format($ringkasan['totalBuku'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Sumber</th>
                        <th class="text-left px-5 py-3">Nama</th>
                        <th class="text-left px-5 py-3">Tgl Perolehan</th>
                        <th class="text-right px-5 py-3">Harga Perolehan</th>
                        <th class="text-right px-5 py-3">Umur</th>
                        <th class="text-right px-5 py-3">Penyusutan/Th</th>
                        <th class="text-right px-5 py-3">Nilai Buku</th>
                        <th class="text-left px-5 py-3 w-32">Sisa Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $it)
                        @php
                            $badge = match($it['sumber']) {
                                'Elektronik' => 'bg-indigo-100 text-indigo-700',
                                'Non Elektronik' => 'bg-emerald-100 text-emerald-700',
                                'Perabotan' => 'bg-amber-100 text-amber-700',
                                'Kendaraan' => 'bg-red-100 text-red-700',
                                default => 'bg-teal-100 text-teal-700',
                            };
                            $barColor = $it['persenTersisa'] >= 50 ? 'bg-emerald-500' : ($it['persenTersisa'] >= 20 ? 'bg-amber-500' : 'bg-red-500');
                        @endphp
                        <tr>
                            <td class="px-5 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $badge }}">{{ $it['sumber'] }}</span></td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $it['nama'] }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $it['tanggal']?->format('d M Y') ?? '-' }}</td>
                            <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($it['hargaPerolehan'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 text-xs">{{ $it['umurTahun'] }} th</td>
                            <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($it['penyusutanPerTahun'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-medium text-slate-800">Rp {{ number_format($it['nilaiBuku'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full {{ $barColor }}" style="width: {{ $it['persenTersisa'] }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500 w-9 text-right">{{ $it['persenTersisa'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Belum ada data aset.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
