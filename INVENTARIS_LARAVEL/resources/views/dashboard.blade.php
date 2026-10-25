@extends('layouts.app')

@section('title', 'Dashboard - Sistem Inventaris')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        @php
            $cards = [
                ['label' => 'Barang Elektronik', 'value' => $stats['elektronik'], 'icon' => 'bi-cpu-fill', 'color' => 'bg-brand-600'],
                ['label' => 'Barang Non Elektronik', 'value' => $stats['non_elektronik'], 'icon' => 'bi-tools', 'color' => 'bg-emerald-600'],
                ['label' => 'Perabotan', 'value' => $stats['perabotan'], 'icon' => 'bi-lamp-fill', 'color' => 'bg-amber-500'],
                ['label' => 'Kendaraan', 'value' => $stats['kendaraan'], 'icon' => 'bi-truck-front-fill', 'color' => 'bg-sky-600'],
                ['label' => 'Gedung', 'value' => $stats['gedung'], 'icon' => 'bi-building', 'color' => 'bg-violet-600'],
            ];
        @endphp

        @foreach($cards as $c)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="w-10 h-10 rounded-lg {{ $c['color'] }} text-white flex items-center justify-center mb-3">
                    <i class="bi {{ $c['icon'] }}"></i>
                </div>
                <p class="text-2xl font-bold text-slate-800">{{ $c['value'] }}</p>
                <p class="text-sm text-slate-500">{{ $c['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 lg:col-span-1">
            <h2 class="font-semibold text-slate-800 mb-4">Distribusi Nilai Aset</h2>
            <canvas id="chartNilai" height="220"></canvas>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6 lg:col-span-2">
            <h2 class="font-semibold text-slate-800 mb-4">Jumlah Item per Modul</h2>
            <canvas id="chartJumlah" height="180"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill text-amber-500"></i>
            <h2 class="font-semibold text-slate-800">Perlu Perhatian</h2>
        </div>

        @if($perluPerhatian->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">
                <i class="bi bi-check-circle text-emerald-500"></i> Semua aset dalam kondisi baik dan stok mencukupi.
            </p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($perluPerhatian as $item)
                    <div class="flex items-center justify-between py-2.5">
                        <div class="flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full {{ $item['level'] === 'red' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $item['nama'] }}</p>
                                <p class="text-xs text-slate-400">{{ $item['sumber'] }} &middot; {{ $item['catatan'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('chartNilai'), {
        type: 'doughnut',
        data: {
            labels: @json($chartNilai['labels']),
            datasets: [{
                data: @json($chartNilai['data']),
                backgroundColor: ['#3568f7', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                borderWidth: 0,
            }],
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
        },
    });

    new Chart(document.getElementById('chartJumlah'), {
        type: 'bar',
        data: {
            labels: @json($chartJumlah['labels']),
            datasets: [{
                data: @json($chartJumlah['data']),
                backgroundColor: '#3568f7',
                borderRadius: 6,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
</script>
@endpush
