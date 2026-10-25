@csrf
@if(isset($bangunan))
    @method('PUT')
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Kode Gedung <span class="text-red-500">*</span></label>
        <input type="text" name="kode_gedung" value="{{ old('kode_gedung', $bangunan->kode_gedung ?? '') }}" placeholder="Contoh: GDG-001" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Gedung <span class="text-red-500">*</span></label>
        <input type="text" name="nama_gedung" value="{{ old('nama_gedung', $bangunan->nama_gedung ?? '') }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Harga Gedung (Rp) <span class="text-red-500">*</span></label>
        <input type="number" name="harga_gedung" min="0" step="0.01" value="{{ old('harga_gedung', $bangunan->harga_gedung ?? 0) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Kondisi <span class="text-red-500">*</span></label>
        <select name="kondisi" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            @foreach($kondisiList as $k)
                <option value="{{ $k }}" {{ old('kondisi', $bangunan->kondisi ?? 'Baik') === $k ? 'selected' : '' }}>{{ $k }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Lokasi</label>
        <input type="text" name="lokasi" value="{{ old('lokasi', $bangunan->lokasi ?? '') }}"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Fungsi</label>
        <input type="text" name="fungsi" value="{{ old('fungsi', $bangunan->fungsi ?? '') }}" placeholder="Contoh: Kantor Pusat, Gudang"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Berdiri</label>
        <input type="date" name="tanggal_berdiri" value="{{ old('tanggal_berdiri', isset($bangunan) && $bangunan->tanggal_berdiri ? $bangunan->tanggal_berdiri->format('Y-m-d') : '') }}"
               max="{{ date('Y-m-d') }}"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div></div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Pengelola</label>
        <input type="text" name="pengelola" value="{{ old('pengelola', $bangunan->pengelola ?? '') }}"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jabatan Pengelola</label>
        <input type="text" name="jabatan_pengelola" value="{{ old('jabatan_pengelola', $bangunan->jabatan_pengelola ?? '') }}"
               placeholder="Contoh: Manajer Operasional, Kepala Cabang, dll."
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Foto Gedung</label>
        <input type="file" name="foto_gedung" accept="image/*" id="inputFotoGedung"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        @if(isset($bangunan) && $bangunan->foto_gedung)
            <img src="{{ asset('storage/gedung/'.$bangunan->foto_gedung) }}" class="h-24 rounded-lg border border-slate-200 mt-2 cursor-zoom-in" onclick="zoomImage(this.src, 'Foto Gedung')">
        @endif
        <img id="previewGedung" class="h-24 rounded-lg border border-slate-200 mt-2 hidden">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Foto Pengelola</label>
        <input type="file" name="foto_pengelola" accept="image/*" id="inputFotoPengelola"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        @if(isset($bangunan) && $bangunan->foto_pengelola)
            <img src="{{ asset('storage/pengelola/'.$bangunan->foto_pengelola) }}" class="h-24 rounded-lg border border-slate-200 mt-2 cursor-zoom-in" onclick="zoomImage(this.src, 'Foto Pengelola')">
        @endif
        <img id="previewPengelola" class="h-24 rounded-lg border border-slate-200 mt-2 hidden">
    </div>
</div>

<div class="flex gap-3 mt-8">
    <button type="submit" class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition">
        <i class="bi bi-check-circle"></i> Simpan Data
    </button>
    <a href="{{ route('bangunan.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition">Batal</a>
</div>

@push('scripts')
<script>
    function pasangPreview(inputId, previewId) {
        document.getElementById(inputId)?.addEventListener('change', function () {
            const file = this.files[0];
            const preview = document.getElementById(previewId);
            if (file) {
                const reader = new FileReader();
                reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
                reader.readAsDataURL(file);
            }
        });
    }
    pasangPreview('inputFotoGedung', 'previewGedung');
    pasangPreview('inputFotoPengelola', 'previewPengelola');
</script>
@endpush
