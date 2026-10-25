@csrf
@if(isset($kendaraan))
    @method('PUT')
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Merk / Model <span class="text-red-500">*</span></label>
        <input type="text" name="merk" value="{{ old('merk', $kendaraan->merk ?? request('isi_nama', '')) }}" placeholder="Contoh: Toyota Avanza" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Plat Nomor <span class="text-red-500">*</span></label>
        <input type="text" name="plat_nomor" value="{{ old('plat_nomor', $kendaraan->plat_nomor ?? '') }}" placeholder="Contoh: B 1234 ABC" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 uppercase">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah Unit <span class="text-red-500">*</span></label>
        <input type="number" name="jumlah" id="inputJumlah" min="1" value="{{ old('jumlah', $kendaraan->jumlah ?? request('isi_jumlah', 1)) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Harga per Unit (Rp) <span class="text-red-500">*</span></label>
        <input type="number" name="harga_kendaraan" id="inputHarga" min="0" step="0.01" value="{{ old('harga_kendaraan', $kendaraan->harga_kendaraan ?? request('isi_harga', 0)) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Total Keseluruhan (otomatis)</label>
        <input type="text" id="totalPreview" readonly
               value="Rp {{ number_format(old('jumlah', $kendaraan->jumlah ?? request('isi_jumlah', 1)) * old('harga_kendaraan', $kendaraan->harga_kendaraan ?? request('isi_harga', 0)), 0, ',', '.') }}"
               class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500">
    </div>

    @if(isset($kendaraan))
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Status <span class="text-red-500">*</span></label>
            <select name="status" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                @foreach(['tersedia' => 'Tersedia', 'perbaikan' => 'Perbaikan', 'nonaktif' => 'Nonaktif'] as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $kendaraan->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">
            Foto Kendaraan @if(!isset($kendaraan))<span class="text-red-500">*</span>@endif
        </label>
        <input type="file" name="foto_kendaraan" accept="image/*" id="inputFoto" {{ isset($kendaraan) ? '' : 'required' }}
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        <p class="text-xs text-slate-400 mt-1">Format JPG/PNG/WEBP, maksimal 2MB. @if(isset($kendaraan)) Kosongkan jika tidak ingin mengubah foto. @endif</p>

        <div class="mt-3 flex gap-3 items-start">
            @if(isset($kendaraan) && $kendaraan->foto_kendaraan)
                <div>
                    <p class="text-xs text-slate-400 mb-1">Foto saat ini:</p>
                    <img src="{{ asset('storage/mobil/'.$kendaraan->foto_kendaraan) }}" class="h-28 rounded-lg border border-slate-200 cursor-zoom-in" onclick="zoomImage(this.src, 'Foto Saat Ini')">
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
    <a href="{{ route('kendaraan.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition">Batal</a>
</div>

@push('scripts')
<script>
    function hitungTotal() {
        const jumlah = parseFloat(document.getElementById('inputJumlah').value) || 0;
        const harga = parseFloat(document.getElementById('inputHarga').value) || 0;
        document.getElementById('totalPreview').value = 'Rp ' + (jumlah * harga).toLocaleString('id-ID');
    }
    document.getElementById('inputJumlah').addEventListener('input', hitungTotal);
    document.getElementById('inputHarga').addEventListener('input', hitungTotal);

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
