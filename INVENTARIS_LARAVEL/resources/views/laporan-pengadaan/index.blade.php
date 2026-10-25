@extends('layouts.app')

@section('title', 'Laporan dari Pengadaan - Sistem Inventaris')
@section('page-title', 'Laporan dari Pengadaan')

@php
    $ikonJenis = ['status' => 'bi-signpost-split', 'pengiriman' => 'bi-send', 'pesanan' => 'bi-cart-check', 'penerimaan' => 'bi-box-seam', 'serah_terima' => 'bi-hand-thumbs-up', 'catatan' => 'bi-chat-left-text', 'konfirmasi' => 'bi-patch-check'];
    $labelJenis = ['status' => 'Perkembangan', 'pengiriman' => 'Pengiriman', 'pesanan' => 'Pemesanan', 'penerimaan' => 'Penerimaan', 'serah_terima' => 'Serah terima', 'catatan' => 'Catatan', 'konfirmasi' => 'Konfirmasi'];
    $tabs = ['baru' => 'Belum dibaca', 'masuk' => 'Semua dari Pengadaan', 'keluar' => 'Terkirim dari Inventaris'];
    $pengelola = auth()->user()->level_akses === 'super_admin';
@endphp

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="lg:col-span-2 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex flex-wrap gap-1 rounded-xl bg-slate-200/60 p-1">
                @foreach($tabs as $k => $label)
                    <a href="{{ route('laporan-pengadaan.index', ['tab' => $k]) }}"
                       class="inline-flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium transition {{ $tab === $k ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $label }}
                        @if($k === 'baru' && $jumlahBaru > 0)<span class="rounded-full bg-red-500 px-1.5 text-[10px] font-bold leading-4 text-white">{{ $jumlahBaru }}</span>@endif
                    </a>
                @endforeach
            </div>
            @if($jumlahBaru > 0)
                <form method="POST" action="{{ route('laporan-pengadaan.baca-semua') }}">@csrf
                    <button class="px-3 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-sm">Tandai semua dibaca</button>
                </form>
            @endif
        </div>

        @forelse($laporan as $l)
            @php $baru = $l->arah === 'masuk' && ! $l->dibaca_at; @endphp
            <article class="relative rounded-xl border bg-white p-4 pl-5 {{ $baru ? 'border-orange-300' : 'border-slate-200' }}">
                <span class="absolute inset-y-3 left-0 w-1 rounded-r {{ $l->arah === 'masuk' ? 'bg-brand-500' : 'bg-slate-400' }}"></span>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="px-2 py-0.5 rounded-md font-medium {{ $l->arah === 'masuk' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }}">{{ $l->arah === 'masuk' ? 'Dari Pengadaan' : 'Dari Inventaris' }}</span>
                            <span class="text-slate-500"><i class="bi {{ $ikonJenis[$l->jenis] ?? 'bi-dot' }}"></i> {{ $labelJenis[$l->jenis] ?? $l->jenis }}</span>
                            @if($baru)<span class="px-2 py-0.5 rounded-md bg-orange-100 text-orange-700 font-medium">Baru</span>@endif
                        </p>
                        <h3 class="mt-1 font-medium text-slate-800">{{ $l->judul }}</h3>
                    </div>
                    <span class="text-xs text-slate-400 shrink-0">{{ $l->created_at->format('d M Y H:i') }}</span>
                </div>
                @if($l->isi)<p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $l->isi }}</p>@endif
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="text-slate-500">
                        @if($l->pengajuan)
                            <a href="{{ route('pengajuan.show', $l->pengajuan) }}" class="text-brand-600 hover:underline">{{ $l->pengajuan->nomor }} · {{ \Illuminate\Support\Str::limit($l->pengajuan->judul, 50) }}</a>
                        @endif
                    </span>
                    @if($baru)
                        <form method="POST" action="{{ route('laporan-pengadaan.baca', $l) }}">@csrf<button class="px-2.5 py-1 rounded-lg text-brand-700 hover:bg-brand-50 font-medium">Tandai dibaca</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center">
                <p class="font-medium text-slate-700">{{ $tab === 'baru' ? 'Tidak ada laporan baru' : 'Belum ada laporan' }}</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-400">Kabar dari Pengadaan (pesanan, penerimaan, serah terima) muncul di sini otomatis.</p>
            </div>
        @endforelse

        @if($laporan->hasPages())<div>{{ $laporan->links() }}</div>@endif
    </div>

    <aside class="space-y-5">
        @if($pengelola)
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-800 text-sm mb-1">Tulis catatan ke Pengadaan</h3>
                <p class="text-xs text-slate-500 mb-3">Untuk pengajuan yang masih berjalan.</p>
                @if($pengajuanAktif->isEmpty())
                    <p class="text-sm text-slate-400">Belum ada pengajuan aktif yang sudah terkirim.</p>
                @else
                    <form method="POST" action="{{ route('laporan-pengadaan.catatan') }}" class="space-y-3">
                        @csrf
                        <select name="pengajuan_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-sm">
                            <option value="">Pilih pengajuan…</option>
                            @foreach($pengajuanAktif as $pa)
                                <option value="{{ $pa->id }}" @selected((int) old('pengajuan_id') === $pa->id)>{{ $pa->nomor }} · {{ \Illuminate\Support\Str::limit($pa->judul, 40) }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="judul" value="{{ old('judul') }}" required maxlength="200" placeholder="Perihal"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <textarea name="isi" rows="3" maxlength="3000" placeholder="Isi catatan (opsional)"
                                  class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('isi') }}</textarea>
                        <button class="w-full px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-send"></i> Kirim</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="bg-white rounded-xl border border-slate-200 p-5 text-sm text-slate-600">
            <h3 class="font-semibold text-slate-800 text-sm mb-2">Membuat pengajuan baru?</h3>
            <p class="text-xs text-slate-500 mb-3">Kebutuhan barang atau jasa dikirim lewat halaman pengajuan.</p>
            <a href="{{ route('pengajuan.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-300 hover:bg-slate-50 text-sm font-medium"><i class="bi bi-send"></i> Buka Pengajuan ke Pengadaan</a>
        </section>
    </aside>
</div>
@endsection
