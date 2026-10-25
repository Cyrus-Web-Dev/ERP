@csrf
@if(isset($penggunaan))
    @method('PUT')
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Karyawan <span class="text-red-500">*</span></label>
        <input type="text" name="nama_karyawan" value="{{ old('nama_karyawan', $penggunaan->nama_karyawan ?? '') }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Barang Elektronik <span class="text-red-500">*</span></label>
        <select name="barang_id" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">Pilih Barang</option>
            @foreach($barangList as $b)
                <option value="{{ $b->id }}" {{ (int) old('barang_id', $penggunaan->barang_id ?? 0) === $b->id ? 'selected' : '' }}>
                    {{ $b->nama_barang }} (Stok: {{ $b->jumlah }}@if(isset($penggunaan) && $penggunaan->barang_id === $b->id) + {{ $penggunaan->jumlah }} punya transaksi ini @endif)
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah <span class="text-red-500">*</span></label>
        <input type="number" name="jumlah" min="1" value="{{ old('jumlah', $penggunaan->jumlah ?? 1) }}" required
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">
            Foto Karyawan @if(!isset($penggunaan))<span class="text-red-500">*</span>@endif
        </label>
        <input type="file" name="foto_karyawan" accept="image/*" id="inputFoto" {{ isset($penggunaan) ? '' : 'required' }}
               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
        <p class="text-xs text-slate-400 mt-1">Format JPG/PNG, maksimal 2MB. @if(isset($penggunaan)) Kosongkan jika tidak ingin mengubah foto. @endif</p>

        <div class="mt-3 flex gap-3 items-start">
            @if(isset($penggunaan) && $penggunaan->foto_karyawan)
                <img src="{{ asset('storage/foto_karyawan/'.$penggunaan->foto_karyawan) }}" class="h-24 w-24 object-cover rounded-full border border-slate-200 cursor-zoom-in" onclick="zoomImage(this.src, 'Foto Saat Ini')">
            @endif
            <img id="preview" class="h-24 w-24 object-cover rounded-full border border-slate-200 hidden">
        </div>
    </div>
</div>

<div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3 mt-5">
    <i class="bi bi-info-circle"></i> Stok barang akan otomatis {{ isset($penggunaan) ? 'disesuaikan' : 'dikurangi' }} sesuai jumlah yang diinput.
</div>

<div class="flex gap-3 mt-6">
    <button type="submit" class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition">
        <i class="bi bi-check-circle"></i> Simpan Data
    </button>
    <a href="{{ route('penggunaan.index') }}" class="px-6 py-2.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition">Batal</a>
</div>

@push('scripts')
<script>
    document.getElementById('inputFoto')?.addEventListener('change', function () {
        const file = this.files[0];
        const preview = document.getElementById('preview');
        if (file) {
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
