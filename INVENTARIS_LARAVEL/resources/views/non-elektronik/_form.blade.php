@csrf
@if(isset($alat))
    @method('PUT')
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Barang <span class="text-red-500">*</span></label>
        <input type="text" name="nama" value="{{ old('nama', $alat->nama ?? request('isi_nama', '')) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jenis <span class="text-red-500">*</span></label>
        <select name="jenis" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Pilih Jenis</option>
            @foreach($jenisList as $j)
                <option value="{{ $j }}" {{ old('jenis', $alat->jenis ?? '') === $j ? 'selected' : '' }}>{{ $j }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Beli <span class="text-red-500">*</span></label>
        <input type="date" name="tanggal_beli" value="{{ old('tanggal_beli', isset($alat) ? $alat->tanggal_beli->format('Y-m-d') : date('Y-m-d')) }}"
               max="{{ date('Y-m-d') }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah <span class="text-red-500">*</span></label>
        <input type="number" name="jumlah" min="0" value="{{ old('jumlah', $alat->jumlah ?? request('isi_jumlah', 0)) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Harga (Rp) <span class="text-red-500">*</span></label>
        <input type="number" name="harga" min="0" step="0.01" value="{{ old('harga', $alat->harga ?? request('isi_harga', 0)) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Foto (opsional)</label>
        <input type="file" name="foto" accept="image/*" id="inputFoto"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        <p class="text-xs text-slate-400 mt-1">Format JPG/PNG, maksimal 2MB.</p>

        <div class="mt-3 flex gap-3 items-start">
            @if(isset($alat) && $alat->foto)
                <div>
                    <p class="text-xs text-slate-400 mb-1">Foto saat ini:</p>
                    <img src="{{ asset('storage/dekorasi/'.$alat->foto) }}" class="h-28 rounded-lg border border-slate-200 cursor-zoom-in" onclick="zoomImage(this.src, 'Foto Saat Ini')">
                </div>
            @endif
            <div id="previewWrap" class="hidden">
                <p class="text-xs text-slate-400 mb-1">Preview baru:</p>
                <img id="preview" class="h-28 rounded-lg border border-slate-200">
            </div>
        </div>
    </div>
</div>

<div class="flex gap-3 mt-8">
    <button type="submit" class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition">
        <i class="bi bi-check-circle"></i> Simpan Data
    </button>
    <a href="{{ route('non-elektronik.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition">Batal</a>
</div>

@push('scripts')
<script>
    document.getElementById('inputFoto')?.addEventListener('change', function () {
        const file = this.files[0];
        const wrap = document.getElementById('previewWrap');
        const preview = document.getElementById('preview');
        if (file) {
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; wrap.classList.remove('hidden'); };
            reader.readAsDataURL(file);
        } else {
            wrap.classList.add('hidden');
        }
    });
</script>
@endpush
