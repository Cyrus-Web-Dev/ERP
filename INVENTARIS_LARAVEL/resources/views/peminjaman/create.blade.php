@extends('layouts.app')

@section('title', 'Pinjam Kendaraan - Sistem Inventaris')
@section('page-title', 'Form Peminjaman Kendaraan')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-2xl">
    @if($kendaraanList->isEmpty())
        <div class="text-center py-8 text-slate-400">
            <i class="bi bi-emoji-frown text-3xl"></i>
            <p class="mt-2">Tidak ada kendaraan yang tersedia untuk dipinjam saat ini.</p>
        </div>
    @else
        <form method="POST" action="{{ route('peminjaman.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kendaraan <span class="text-red-500">*</span></label>
                <select name="id_kendaraan" id="pilihKendaraan" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">Pilih Kendaraan</option>
                    @foreach($kendaraanList as $k)
                        <option value="{{ $k->id_kendaraan }}" data-foto="{{ $k->foto_kendaraan ? asset('storage/mobil/'.$k->foto_kendaraan) : '' }}"
                                {{ (int) old('id_kendaraan') === $k->id_kendaraan ? 'selected' : '' }}>
                            {{ $k->merk }} - {{ $k->plat_nomor }} (Tersedia: {{ $k->jumlah - $k->sedang_dipinjam }})
                        </option>
                    @endforeach
                </select>
                <img id="previewFotoKendaraan" class="h-20 mt-2 rounded-lg border border-slate-200 hidden cursor-zoom-in" onclick="zoomImage(this.src, 'Kendaraan dipilih')">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Peminjam <span class="text-red-500">*</span></label>
                <input type="text" name="peminjam" value="{{ old('peminjam') }}" required
                       class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">NIK <span class="text-red-500">*</span></label>
                <input type="text" name="nik" value="{{ old('nik') }}" maxlength="16" inputmode="numeric" placeholder="16 digit NIK" required
                       class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tujuan Peminjaman <span class="text-red-500">*</span></label>
                <input type="text" name="tujuan" value="{{ old('tujuan') }}" required
                       class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Pinjam <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_pinjam" value="{{ old('tanggal_pinjam', date('Y-m-d')) }}" min="{{ date('Y-m-d') }}" required
                           class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Kembali <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_kembali" value="{{ old('tanggal_kembali') }}" min="{{ date('Y-m-d') }}" required
                           class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
            <p class="text-xs text-slate-400">Maksimal peminjaman 30 hari.</p>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition">
                    <i class="bi bi-check-circle"></i> Ajukan Peminjaman
                </button>
                <a href="{{ route('peminjaman.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition">Batal</a>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('pilihKendaraan')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const foto = opt?.dataset?.foto;
        const preview = document.getElementById('previewFotoKendaraan');
        if (foto) {
            preview.src = foto;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    });
</script>
@endpush
