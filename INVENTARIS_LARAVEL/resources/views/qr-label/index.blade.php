@extends('layouts.app')

@section('title', 'Cetak Label QR - Sistem Inventaris')
@section('page-title', 'Cetak Label QR')

@section('content')
<div class="space-y-5">

    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex flex-wrap gap-2 items-center mb-4">
            <input type="text" id="cariBarang" placeholder="Cari nama barang..."
                   class="px-4 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm flex-1 max-w-xs">
            <button type="button" onclick="pilihSemua(true)" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium">Pilih Semua</button>
            <button type="button" onclick="pilihSemua(false)" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium">Batal Semua</button>
            <div class="flex-1"></div>
            <button type="button" onclick="generateLabel()" class="px-4 py-2 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium">
                <i class="bi bi-qr-code"></i> Buat Label Terpilih
            </button>
        </div>

        <div class="border border-slate-200 rounded-lg max-h-96 overflow-y-auto divide-y divide-slate-100" id="daftarBarang">
            @forelse($items as $item)
                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer item-row" data-nama="{{ strtolower($item['nama']) }}">
                    <input type="checkbox" class="chk-item" value="{{ $item['type'] }}|{{ $item['id'] }}|{{ $item['nama'] }}|{{ $item['label'] }}">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $item['label'] }}</span>
                    <span class="text-sm text-slate-800">{{ $item['nama'] }}</span>
                </label>
            @empty
                <p class="px-4 py-8 text-center text-slate-400 text-sm">Belum ada data barang di semua modul.</p>
            @endforelse
        </div>
    </div>

    <div id="hasilLabel" class="bg-white rounded-xl border border-slate-200 p-4 hidden">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800">Preview Label</h3>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium">
                <i class="bi bi-printer"></i> Cetak
            </button>
        </div>
        <div id="printArea" class="flex flex-wrap gap-3"></div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #printArea, #printArea * { visibility: visible; }
        #printArea { position: absolute; top: 0; left: 0; width: 100%; }
    }
    .qr-label { width: 170px; border: 1px dashed #94a3b8; border-radius: 8px; padding: 10px; text-align: center; }
    .qr-label .nama { font-weight: 600; font-size: 11px; margin-top: 6px; word-break: break-word; }
    .qr-label .tipe { font-size: 9px; color: #64748b; }
</style>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.getElementById('cariBarang').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.item-row').forEach(row => {
            row.style.display = row.dataset.nama.includes(q) ? '' : 'none';
        });
    });

    function pilihSemua(state) {
        document.querySelectorAll('.item-row').forEach(row => {
            if (row.style.display !== 'none') {
                row.querySelector('.chk-item').checked = state;
            }
        });
    }

    function generateLabel() {
        const checked = document.querySelectorAll('.chk-item:checked');
        if (checked.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Belum ada barang dipilih', confirmButtonColor: '#254bea' });
            return;
        }

        const printArea = document.getElementById('printArea');
        printArea.innerHTML = '';

        checked.forEach(chk => {
            const [type, id, nama, label] = chk.value.split('|');
            const url = new URL(`/lihat/${encodeURIComponent(type)}/${encodeURIComponent(id)}`, window.location.origin).href;

            const div = document.createElement('div');
            div.className = 'qr-label';
            const qrBox = document.createElement('div');
            div.appendChild(qrBox);

            const namaEl = document.createElement('div');
            namaEl.className = 'nama';
            namaEl.textContent = nama;
            div.appendChild(namaEl);

            const tipeEl = document.createElement('div');
            tipeEl.className = 'tipe';
            tipeEl.textContent = label;
            div.appendChild(tipeEl);

            printArea.appendChild(div);

            new QRCode(qrBox, { text: url, width: 120, height: 120, correctLevel: QRCode.CorrectLevel.M });
        });

        document.getElementById('hasilLabel').classList.remove('hidden');
        document.getElementById('hasilLabel').scrollIntoView({ behavior: 'smooth' });
    }
</script>
@endpush
