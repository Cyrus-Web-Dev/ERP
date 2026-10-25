@extends('layouts.app')

@section('title', 'Pengajuan ke Pengadaan - Sistem Inventaris')
@section('page-title', 'Pengajuan ke Pengadaan')

@section('content')
<div class="space-y-5">

    @unless($terhubung)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Integrasi PROCURA belum diatur. Isi <code class="font-mono">PROCURA_URL</code> dan <code class="font-mono">PROCURA_TOKEN</code> di file <code class="font-mono">.env</code>.
            Pengajuan tetap bisa disimpan, tetapi belum bisa dikirim ke Pengadaan.
        </div>
    @endunless

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Sedang berjalan', $ringkas['aktif'], 'bi-hourglass-split', 'text-brand-600', 'aktif'],
            ['Menunggu konfirmasi Anda', $ringkas['konfirmasi'], 'bi-box-seam', 'text-orange-600', 'perlu'],
            ['Belum terkirim', $ringkas['gagal'], 'bi-cloud-slash', 'text-rose-600', 'perlu'],
            ['Selesai', $ringkas['selesai'], 'bi-check2-circle', 'text-emerald-600', 'selesai'],
        ] as [$label, $n, $ikon, $warna, $filter])
            <a href="{{ route('pengajuan.index', ['tampil' => $filter]) }}" class="bg-white rounded-xl border border-slate-200 p-4 hover:border-brand-300 transition">
                <p class="text-xs text-slate-500 flex items-center gap-1.5"><i class="bi {{ $ikon }} {{ $warna }}"></i> {{ $label }}</p>
                <p class="text-2xl font-semibold mt-1 text-slate-800">{{ $n }}</p>
            </a>
        @endforeach
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex gap-2 flex-1 flex-wrap">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari judul atau nomor pengajuan..."
                   class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 flex-1 min-w-[12rem] max-w-md">
            <select name="tampil" onchange="this.form.submit()" class="px-3 py-2.5 rounded-lg border border-slate-300 bg-white text-sm">
                <option value="aktif" @selected($tampil === 'aktif')>Sedang berjalan</option>
                <option value="perlu" @selected($tampil === 'perlu')>Perlu tindakan</option>
                <option value="selesai" @selected($tampil === 'selesai')>Selesai / ditutup</option>
                <option value="semua" @selected($tampil === 'semua')>Semua</option>
            </select>
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-search"></i> Cari</button>
            @if($search)
                <a href="{{ route('pengajuan.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
            @endif
        </form>

        @if(auth()->user()->level_akses === 'super_admin')
            <a href="{{ route('pengajuan.create') }}" class="shrink-0 px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium text-center">
                <i class="bi bi-send-plus"></i> Buat Pengajuan
            </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Pengajuan</th>
                        <th class="text-left px-5 py-3 w-56">Jalur</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-right px-5 py-3">Estimasi</th>
                        <th class="text-left px-5 py-3">Dibutuhkan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($daftar as $p)
                        @php
                            $kelasPrioritas = config("procura.prioritas.{$p->prioritas}.1", '');
                            $telat = $p->tanggal_dibutuhkan && $p->tanggal_dibutuhkan->isPast() && ! $p->selesai();
                        @endphp
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-5 py-3 min-w-[16rem]">
                                <a href="{{ route('pengajuan.show', $p) }}" class="font-medium text-slate-800 hover:text-brand-700">{{ $p->judul }}</a>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <span class="font-mono">{{ $p->nomor }}</span>
                                    @if($p->procura_nomor)<span class="font-mono"> → {{ $p->procura_nomor }}</span>@endif
                                    · {{ $p->items_count }} item · {{ config("procura.kategori.{$p->kategori}") }}
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                @if($p->sudahTerkirim())
                                    <x-pengadaan-tracker :stasiun="$p->stasiunKey()" :status="$p->status" :ringkas="true" />
                                @else
                                    <span class="text-xs text-rose-600"><i class="bi bi-cloud-slash"></i> Belum sampai ke Pengadaan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col items-start gap-1">
                                    <x-status-pengadaan :status="$p->status" />
                                    @if(in_array($p->prioritas, ['high', 'urgent']))
                                        <span class="px-2 py-0.5 rounded-md text-xs font-medium {{ $kelasPrioritas }}">{{ config("procura.prioritas.{$p->prioritas}.0") }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-xs {{ $telat ? 'text-rose-600 font-semibold' : 'text-slate-500' }}">
                                {{ $p->tanggal_dibutuhkan?->format('d M Y') ?? '—' }}@if($telat) · terlambat @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">Belum ada pengajuan di tampilan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($daftar->hasPages())
        <div>{{ $daftar->links() }}</div>
    @endif
</div>
@endsection
