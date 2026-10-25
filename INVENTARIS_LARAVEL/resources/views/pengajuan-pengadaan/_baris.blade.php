<div data-baris class="grid grid-cols-1 sm:grid-cols-12 gap-3 px-6 py-4">
    <div data-katalog-wrap class="sm:col-span-12 hidden">
        <select data-katalog aria-label="Isi cepat dari katalog Pengadaan" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-xs text-slate-600"></select>
    </div>
    <div class="sm:col-span-5">
        <label class="block text-xs font-medium text-slate-600 mb-1">Nama barang / jasa <span class="text-red-500">*</span></label>
        <input data-nama type="text" name="items[{{ $i }}][nama]" value="{{ $b['nama'] ?? '' }}" required class="{{ $kelasInput }}">
        @error("items.$i.nama")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 mb-1">Jumlah <span class="text-red-500">*</span></label>
        <input data-jumlah type="number" name="items[{{ $i }}][jumlah]" min="0.01" step="any" value="{{ $b['jumlah'] ?? 1 }}" required class="{{ $kelasInput }}">
        @error("items.$i.jumlah")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 mb-1">Satuan <span class="text-red-500">*</span></label>
        <input data-satuan type="text" name="items[{{ $i }}][satuan]" value="{{ $b['satuan'] ?? 'unit' }}" required class="{{ $kelasInput }}">
    </div>
    <div class="sm:col-span-3">
        <label class="block text-xs font-medium text-slate-600 mb-1">Harga perkiraan / satuan (Rp)</label>
        <input data-harga type="number" name="items[{{ $i }}][harga_estimasi]" min="0" step="any" value="{{ $b['harga_estimasi'] ?? 0 }}" class="{{ $kelasInput }}">
    </div>
    <div class="sm:col-span-11">
        <label class="block text-xs font-medium text-slate-600 mb-1">Spesifikasi</label>
        <input type="text" name="items[{{ $i }}][spesifikasi]" value="{{ $b['spesifikasi'] ?? '' }}" placeholder="Merek, ukuran, kebutuhan khusus…" class="{{ $kelasInput }}">
    </div>
    <div class="sm:col-span-1 flex items-end">
        <button type="button" data-hapus title="Hapus baris" class="w-full px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 text-sm"><i class="bi bi-trash"></i></button>
    </div>
</div>
