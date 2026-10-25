@extends('layouts.app')

@section('title', 'Buat Pengajuan ke Pengadaan - Sistem Inventaris')
@section('page-title', 'Buat Pengajuan ke Pengadaan')

@php
    $baris = old('items', [['nama' => '', 'spesifikasi' => '', 'jumlah' => 1, 'satuan' => 'unit', 'harga_estimasi' => 0]]);
    $kelasInput = 'w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500';
@endphp

@section('content')
<div class="max-w-4xl space-y-5">

    @unless($terhubung)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="bi bi-exclamation-triangle-fill"></i> Integrasi PROCURA belum diatur di <code class="font-mono">.env</code>.
            Pengajuan akan tersimpan sebagai draf dan bisa dikirim ulang setelah integrasi aktif.
        </div>
    @endunless

    <form method="POST" action="{{ route('pengajuan.store') }}" id="formPengajuan" class="space-y-5">
        @csrf

        <section class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Informasi pengajuan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Judul pengajuan <span class="text-red-500">*</span></label>
                    <input type="text" name="judul" value="{{ old('judul') }}" required placeholder="Contoh: Penggantian laptop staf gudang" class="{{ $kelasInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama pemohon <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_pemohon" value="{{ old('nama_pemohon', auth()->user()->nama_lengkap) }}" required class="{{ $kelasInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Unit / divisi</label>
                    <input type="text" name="unit_pemohon" value="{{ old('unit_pemohon', auth()->user()->jabatan ?: 'Divisi Inventaris') }}" class="{{ $kelasInput }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                    <select name="kategori" id="kategori" required class="{{ $kelasInput }} bg-white">
                        @foreach(config('procura.kategori') as $k => $label)
                            <option value="{{ $k }}" @selected(old('kategori', 'elektronik') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prioritas <span class="text-red-500">*</span></label>
                    <select name="prioritas" required class="{{ $kelasInput }} bg-white">
                        @foreach(config('procura.prioritas') as $k => [$label])
                            <option value="{{ $k }}" @selected(old('prioritas', 'normal') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Dibutuhkan paling lambat</label>
                    <input type="date" name="tanggal_dibutuhkan" value="{{ old('tanggal_dibutuhkan') }}" min="{{ date('Y-m-d') }}" class="{{ $kelasInput }}">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Alasan / latar belakang</label>
                    <textarea name="alasan" rows="2" placeholder="Mengapa barang/jasa ini dibutuhkan?" class="{{ $kelasInput }}">{{ old('alasan') }}</textarea>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800">Barang / jasa yang dibutuhkan</h3>
                <button type="button" id="tambahBaris" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 text-sm"><i class="bi bi-plus-lg"></i> Tambah baris</button>
            </div>

            <div id="daftarBaris" class="divide-y divide-slate-100">
                @foreach($baris as $i => $b)
                    @include('pengajuan-pengadaan._baris', ['i' => $i, 'b' => $b, 'kelasInput' => $kelasInput])
                @endforeach
            </div>

            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-xl">
                <span class="text-sm text-slate-500">Perkiraan total (sebelum PPN)</span>
                <span class="text-lg font-semibold text-slate-800" id="totalEstimasi">Rp 0</span>
            </div>
        </section>

        <p class="text-xs text-slate-500">
            <i class="bi bi-info-circle"></i> Pengajuan dikirim langsung ke Bagian Pengadaan. Harga di sini hanya perkiraan; harga sebenarnya ditentukan dari penawaran vendor.
        </p>

        <div class="flex justify-end gap-2">
            <a href="{{ route('pengajuan.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Batal</a>
            <button type="submit" id="btnKirim" class="px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium"><i class="bi bi-send"></i> Kirim ke Pengadaan</button>
        </div>
    </form>
</div>

{{-- Templat baris baru (diisi JS) --}}
<template id="templatBaris">
    @include('pengajuan-pengadaan._baris', ['i' => '__I__', 'b' => ['nama' => '', 'spesifikasi' => '', 'jumlah' => 1, 'satuan' => 'unit', 'harga_estimasi' => 0], 'kelasInput' => $kelasInput])
</template>
@endsection

@push('scripts')
<script>
    const KATALOG = @json($katalog);   // { kategori: [ {id, sku, name, unit, reference_price}, ... ] }
    const daftar = document.getElementById('daftarBaris');
    const tmpl = document.getElementById('templatBaris').innerHTML;
    const kategori = document.getElementById('kategori');
    let nextIdx = {{ count($baris) }};

    const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    function hitungTotal() {
        let total = 0;
        daftar.querySelectorAll('[data-baris]').forEach(r => {
            total += (parseFloat(r.querySelector('[data-jumlah]').value) || 0) * (parseFloat(r.querySelector('[data-harga]').value) || 0);
        });
        document.getElementById('totalEstimasi').textContent = rupiah(total);
    }

    function isiPilihanKatalog() {
        const items = KATALOG[kategori.value] || [];
        daftar.querySelectorAll('[data-katalog]').forEach(sel => {
            sel.innerHTML = '<option value="">Isi cepat dari katalog Pengadaan… (atau ketik manual)</option>' +
                items.map(k => `<option value="${k.id}">${k.name.replace(/</g, '&lt;')} — ${rupiah(k.reference_price)}</option>`).join('');
            sel.closest('[data-katalog-wrap]').classList.toggle('hidden', items.length === 0);
        });
    }

    function pasang(row) {
        row.querySelectorAll('[data-jumlah],[data-harga]').forEach(el => el.addEventListener('input', hitungTotal));
        row.querySelector('[data-hapus]').addEventListener('click', () => {
            if (daftar.querySelectorAll('[data-baris]').length > 1) { row.remove(); hitungTotal(); }
        });
        row.querySelector('[data-katalog]').addEventListener('change', (e) => {
            const k = (KATALOG[kategori.value] || []).find(x => String(x.id) === e.target.value);
            if (!k) return;
            row.querySelector('[data-nama]').value = k.name;
            row.querySelector('[data-satuan]').value = k.unit;
            row.querySelector('[data-harga]').value = k.reference_price;
            hitungTotal();
        });
    }

    daftar.querySelectorAll('[data-baris]').forEach(pasang);
    kategori.addEventListener('change', isiPilihanKatalog);

    document.getElementById('tambahBaris').addEventListener('click', () => {
        const wrap = document.createElement('div');
        wrap.innerHTML = tmpl.replaceAll('__I__', nextIdx++).trim();
        const row = wrap.firstElementChild;
        daftar.appendChild(row);
        pasang(row);
        isiPilihanKatalog();
        row.querySelector('[data-nama]').focus();
    });

    // Cegah kirim ganda saat tombol diklik dua kali.
    document.getElementById('formPengajuan').addEventListener('submit', () => {
        const b = document.getElementById('btnKirim');
        b.disabled = true; b.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengirim…';
    });

    isiPilihanKatalog();
    hitungTotal();
</script>
@endpush
