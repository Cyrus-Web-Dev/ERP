@extends('layouts.app')

@section('title', $p->judul.' - Pengajuan ke Pengadaan')
@section('page-title', 'Detail Pengajuan')

@php
    $pengelola = auth()->user()->level_akses === 'super_admin';
    $kelasPrioritas = config("procura.prioritas.{$p->prioritas}.1", '');
    $labelPo = ['draft' => 'Disiapkan', 'sent' => 'Dikirim ke vendor', 'partial' => 'Diterima sebagian', 'received' => 'Diterima penuh', 'cancelled' => 'Dibatalkan'];
    $menungguKonfirmasi = $p->serah_terima_status === 'handed_over';
    $sudahKonfirmasi = $p->serah_terima_status === 'confirmed';
    $ikonJenis = ['status' => 'bi-signpost-split', 'pengiriman' => 'bi-send', 'pesanan' => 'bi-cart-check', 'penerimaan' => 'bi-box-seam', 'serah_terima' => 'bi-hand-thumbs-up', 'catatan' => 'bi-chat-left-text', 'konfirmasi' => 'bi-patch-check'];
@endphp

@section('content')
<div class="space-y-5">

    {{-- Kepala --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('pengajuan.index') }}" class="text-xs text-slate-500 hover:underline">← Semua pengajuan</a>
            <h2 class="mt-1 text-2xl font-semibold text-slate-800">{{ $p->judul }}</h2>
            <p class="mt-1.5 flex flex-wrap items-center gap-2 text-sm">
                <span class="font-mono text-xs text-slate-500">{{ $p->nomor }}@if($p->procura_nomor) → {{ $p->procura_nomor }}@endif</span>
                <x-status-pengadaan :status="$p->status" />
                <span class="px-2 py-0.5 rounded-md text-xs font-medium {{ $kelasPrioritas }}">{{ config("procura.prioritas.{$p->prioritas}.0") }}</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($p->sudahTerkirim())
                <form method="POST" action="{{ route('pengajuan.sinkron', $p) }}">@csrf
                    <button class="px-4 py-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-sm font-medium"><i class="bi bi-arrow-repeat"></i> Muat status terbaru</button>
                </form>
            @elseif($pengelola)
                <form method="POST" action="{{ route('pengajuan.kirim-ulang', $p) }}">@csrf
                    <button class="px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium"><i class="bi bi-send"></i> Kirim ulang ke Pengadaan</button>
                </form>
                <form id="hapus-pengajuan" method="POST" action="{{ route('pengajuan.destroy', $p) }}">@csrf @method('DELETE')</form>
                <button type="button" onclick="confirmHapus('hapus-pengajuan', { title: 'Hapus pengajuan draf?', text: 'Pengajuan ini belum terkirim ke Pengadaan.' })"
                        class="px-4 py-2.5 rounded-lg bg-white border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium"><i class="bi bi-trash"></i> Hapus draf</button>
            @endif
        </div>
    </div>

    @if($p->error_terakhir)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><i class="bi bi-exclamation-octagon-fill"></i> {{ $p->error_terakhir }}</div>
    @endif
    @if($p->alasan_penolakan)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <strong>{{ $p->status === 'cancelled' ? 'Dibatalkan' : 'Ditolak' }} oleh Pengadaan:</strong> {{ $p->alasan_penolakan }}
        </div>
    @endif

    {{-- Jalur --}}
    @if($p->sudahTerkirim())
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <x-pengadaan-tracker :stasiun="$p->stasiunKey()" :status="$p->status" />
            <p class="mt-4 text-sm text-slate-600">{{ $p->statusMeta()[2] }}</p>
            <p class="mt-1 text-xs text-slate-400">Terakhir dimuat {{ $p->disinkronkan_at?->diffForHumans() ?? 'belum pernah' }}.</p>
        </div>
    @endif

    {{-- Serah terima: tindakan paling penting, ditaruh di atas --}}
    @if($menungguKonfirmasi && $pengelola)
        <div class="rounded-xl border-2 border-orange-300 bg-orange-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-orange-900"><i class="bi bi-box-seam"></i> Barang sudah diserahkan Pengadaan — mohon konfirmasi</p>
                    <p class="text-sm text-orange-800 mt-1">
                        Serah terima <span class="font-mono">{{ $p->serah_terima_nomor }}</span> · {{ $p->serah_terima_tanggal?->format('d M Y') }} · penerima {{ $p->serah_terima_penerima }}.
                        Periksa barang terlebih dahulu, lalu konfirmasi.
                    </p>
                </div>
                <form id="form-konfirmasi" method="POST" action="{{ route('pengajuan.konfirmasi', $p) }}">@csrf</form>
                <button type="button" onclick="konfirmasiAksi('form-konfirmasi', 'Konfirmasi barang sudah diterima?', 'Pengadaan akan diberi tahu bahwa barang sudah Anda terima dengan baik.')"
                        class="px-5 py-2.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium"><i class="bi bi-check2-circle"></i> Konfirmasi sudah diterima</button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">

            {{-- Barang diminta --}}
            <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800 text-sm">Barang / jasa diminta</h3>
                    <span class="text-xs text-slate-500">Estimasi Rp {{ number_format($p->total_estimasi, 0, ',', '.') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase"><tr>
                            <th class="text-left px-5 py-2.5">Nama</th><th class="text-right px-5 py-2.5">Jumlah</th><th class="text-right px-5 py-2.5">Harga perkiraan</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($p->items as $i)
                                <tr>
                                    <td class="px-5 py-3"><p class="font-medium text-slate-800">{{ $i->nama }}</p>@if($i->spesifikasi)<p class="text-xs text-slate-500">{{ $i->spesifikasi }}</p>@endif</td>
                                    <td class="px-5 py-3 text-right text-slate-600">{{ rtrim(rtrim(number_format($i->jumlah, 2, ',', '.'), '0'), ',') }} {{ $i->satuan }}</td>
                                    <td class="px-5 py-3 text-right text-slate-600">Rp {{ number_format($i->harga_estimasi, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Perkembangan di Pengadaan --}}
            @if($p->sudahTerkirim() && ($danaStatus || count($pesananPo)))
                <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-100"><h3 class="font-semibold text-slate-800 text-sm">Perkembangan di Pengadaan</h3></div>
                    <div class="divide-y divide-slate-100 text-sm">
                        @if($danaStatus)
                            <div class="px-5 py-3 flex items-center justify-between gap-3">
                                <span class="text-slate-600"><i class="bi bi-cash-coin text-slate-400"></i> Pendanaan dari Keuangan</span>
                                <span class="font-medium text-slate-800">{{ $danaStatus['status_label'] ?? '—' }}</span>
                            </div>
                        @endif
                        @foreach($pesananPo as $po)
                            <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                                <span class="text-slate-600"><i class="bi bi-cart-check text-slate-400"></i> Pesanan ke <strong class="text-slate-800">{{ $po['vendor'] ?? 'vendor' }}</strong> <span class="font-mono text-xs text-slate-400">{{ $po['number'] ?? '' }}</span></span>
                                <span class="text-xs text-slate-500">
                                    {{ $labelPo[$po['status'] ?? ''] ?? ($po['status'] ?? '') }}
                                    @if(! empty($po['expected_date'])) · perkiraan tiba {{ \Illuminate\Support\Carbon::parse($po['expected_date'])->format('d M Y') }} @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Catat ke data aset --}}
            @if($p->serah_terima_status)
                <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-800 text-sm"><i class="bi bi-journal-plus"></i> Catat ke data aset</h3>
                        @if($p->aset_dicatat_at)
                            <span class="text-xs px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700"><i class="bi bi-check2"></i> Sudah dicatat {{ $p->aset_dicatat_at->format('d M Y') }}</span>
                        @endif
                    </div>
                    @if($jasa)
                        <p class="px-5 py-6 text-sm text-slate-500">Pengajuan ini berupa jasa, jadi tidak perlu dicatat sebagai aset.</p>
                    @elseif(count($barisAset))
                        <div class="divide-y divide-slate-100 text-sm">
                            @foreach($barisAset as $b)
                                <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800">{{ $b['nama'] }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ rtrim(rtrim(number_format($b['jumlah'], 2, ',', '.'), '0'), ',') }} {{ $b['satuan'] }} ·
                                            Rp {{ number_format($b['harga'], 0, ',', '.') }}/{{ $b['satuan'] }} (termasuk PPN) · {{ $b['vendor'] }}
                                        </p>
                                    </div>
                                    @if($b['url'])
                                        <a href="{{ $b['url'] }}" class="shrink-0 px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium"><i class="bi bi-plus-circle"></i> Buka form aset (terisi otomatis)</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">Foto dan detail lain dilengkapi di form aset. Kembali ke halaman ini setelah selesai.</p>
                            @if($pengelola && $sudahKonfirmasi && ! $p->aset_dicatat_at)
                                <form method="POST" action="{{ route('pengajuan.aset-dicatat', $p) }}">@csrf
                                    <button class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-xs font-medium"><i class="bi bi-check2-square"></i> Tandai sudah dicatat</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <p class="px-5 py-6 text-sm text-slate-500">Rincian barang yang diterima belum termuat. Klik “Muat status terbaru”.</p>
                    @endif
                </section>
            @endif

            {{-- Riwayat --}}
            <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800 text-sm">Riwayat & laporan</h3>
                    <a href="{{ route('laporan-pengadaan.index', ['tab' => 'masuk']) }}" class="text-xs text-brand-600 hover:underline">Semua laporan</a>
                </div>
                @if($laporan->count())
                    <ol class="p-5 space-y-4">
                        @foreach($laporan as $l)
                            <li class="flex gap-3">
                                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full text-sm {{ $l->arah === 'masuk' ? 'bg-brand-50 text-brand-600' : 'bg-slate-100 text-slate-500' }}"><i class="bi {{ $ikonJenis[$l->jenis] ?? 'bi-dot' }}"></i></span>
                                <div class="min-w-0 text-sm">
                                    <p class="font-medium text-slate-800">{{ $l->judul }}</p>
                                    @if($l->isi)<p class="text-slate-600 whitespace-pre-line">{{ $l->isi }}</p>@endif
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $l->created_at->format('d M Y H:i') }} · {{ $l->arah === 'masuk' ? 'dari Pengadaan' : 'dari Inventaris' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="px-5 py-8 text-center text-sm text-slate-400">Belum ada riwayat.</p>
                @endif
            </section>
        </div>

        {{-- Kolom kanan --}}
        <aside class="space-y-5">
            <section class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-800 text-sm mb-3">Pemohon</h3>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs text-slate-400">Nama</dt><dd>{{ $p->nama_pemohon }}</dd></div>
                    @if($p->unit_pemohon)<div><dt class="text-xs text-slate-400">Unit</dt><dd>{{ $p->unit_pemohon }}</dd></div>@endif
                    <div><dt class="text-xs text-slate-400">Kategori</dt><dd>{{ config("procura.kategori.{$p->kategori}") }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Dibutuhkan</dt><dd>{{ $p->tanggal_dibutuhkan?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Diajukan</dt><dd>{{ $p->created_at->format('d M Y H:i') }}@if($p->pembuat) oleh {{ $p->pembuat->nama_lengkap }}@endif</dd></div>
                    @if($p->alasan)<div><dt class="text-xs text-slate-400">Alasan</dt><dd class="whitespace-pre-line">{{ $p->alasan }}</dd></div>@endif
                </dl>
            </section>

            @if($pengelola && $p->sudahTerkirim() && ! $p->selesai())
                <section class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-slate-800 text-sm mb-1">Kirim catatan ke Pengadaan</h3>
                    <p class="text-xs text-slate-500 mb-3">Mis. minta dipercepat atau ubah spesifikasi.</p>
                    <form method="POST" action="{{ route('pengajuan.catatan', $p) }}" class="space-y-3">
                        @csrf
                        <input type="text" name="judul" value="{{ old('judul') }}" required maxlength="200" placeholder="Perihal"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <textarea name="isi" rows="3" maxlength="3000" placeholder="Isi catatan (opsional)"
                                  class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('isi') }}</textarea>
                        <button class="w-full px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-send"></i> Kirim catatan</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Konfirmasi aksi non-hapus dengan gaya SweetAlert2 yang sama dengan halaman lain.
    function konfirmasiAksi(formId, title, text) {
        Swal.fire({
            icon: 'question', title, text, showCancelButton: true,
            confirmButtonText: 'Ya, konfirmasi', cancelButtonText: 'Batal',
            confirmButtonColor: '#254bea', cancelButtonColor: '#64748b', reverseButtons: true,
        }).then((r) => { if (r.isConfirmed) document.getElementById(formId).submit(); });
    }
</script>
@endpush
