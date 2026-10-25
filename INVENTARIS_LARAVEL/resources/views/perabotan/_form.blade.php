@csrf
@if(isset($perabotan))
    @method('PUT')
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Kode <span class="text-red-500">*</span></label>
        <input type="text" name="kode" value="{{ old('kode', $perabotan->kode ?? '') }}" placeholder="Contoh: PRB-001" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama <span class="text-red-500">*</span></label>
        <input type="text" name="nama" value="{{ old('nama', $perabotan->nama ?? request('isi_nama', '')) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Merk</label>
        <input type="text" name="merk" value="{{ old('merk', $perabotan->merk ?? '') }}"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Masuk <span class="text-red-500">*</span></label>
        <input type="date" name="tgl_masuk" value="{{ old('tgl_masuk', isset($perabotan) ? $perabotan->tgl_masuk->format('Y-m-d') : date('Y-m-d')) }}"
               max="{{ date('Y-m-d') }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Stok <span class="text-red-500">*</span></label>
        <input type="number" name="stok" min="0" value="{{ old('stok', $perabotan->stok ?? request('isi_jumlah', 0)) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Harga (Rp) <span class="text-red-500">*</span></label>
        <input type="number" name="harga" min="0" step="0.01" value="{{ old('harga', $perabotan->harga ?? request('isi_harga', 0)) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Lokasi</label>
        <input type="text" name="lokasi" value="{{ old('lokasi', $perabotan->lokasi ?? '') }}" placeholder="Contoh: Ruang HRD, Lantai 2"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Status <span class="text-red-500">*</span></label>
        <select name="status" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            @foreach($statusList as $s)
                <option value="{{ $s }}" {{ old('status', $perabotan->status ?? 'Layak Pakai') === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">
            Foto @if(!isset($perabotan))<span class="text-red-500">*</span>@endif
        </label>
        <input type="file" name="foto" accept="image/*" id="inputFoto" {{ isset($perabotan) ? '' : 'required' }}
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        <p class="text-xs text-slate-400 mt-1">Format JPG/PNG, maksimal 2MB. @if(isset($perabotan)) Kosongkan jika tidak ingin mengubah foto. @endif</p>

        <div class="mt-3 flex gap-3 items-start">
            @if(isset($perabotan) && $perabotan->foto)
                <div>
                    <p class="text-xs text-slate-400 mb-1">Foto saat ini:</p>
                    <img src="{{ asset('storage/perabot/'.$perabotan->foto) }}" class="h-28 rounded-lg border border-slate-200 cursor-zoom-in" onclick="zoomImage(this.src, 'Foto Saat Ini')">
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
    <a href="{{ route('perabotan.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition">Batal</a>
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
