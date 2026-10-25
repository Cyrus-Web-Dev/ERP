@extends('layouts.app')

@section('title', 'Jadwal Maintenance - Sistem Inventaris')
@section('page-title', 'Jadwal Maintenance')

@section('content')
<div class="space-y-5">

    @if(auth()->user()->level_akses === 'super_admin')
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Tambah Jadwal Baru</h2>
        <form method="POST" action="{{ route('maintenance.store') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-start">
            @csrf

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Tipe Aset</label>
                <select name="tipe" id="tipeSelect" required onchange="gantiTipe()"
                        class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
                    @foreach($tipeLabel as $val => $label)
                        <option value="{{ $val }}" {{ old('tipe') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Item</label>
                @foreach($daftarPerTipe as $tipeVal => $daftar)
                    <select name="item_id" data-tipe-item="{{ $tipeVal }}"
                            class="tipe-item-select {{ $loop->first ? '' : 'hidden' }} w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
                        @forelse($daftar as $d)
                            <option value="{{ $d['id'] }}">{{ $d['label'] }}</option>
                        @empty
                            <option value="">Belum ada data</option>
                        @endforelse
                    </select>
                @endforeach
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Jenis Maintenance</label>
                <input type="text" name="jenis_maintenance" value="{{ old('jenis_maintenance') }}" placeholder="Mis. Servis Rutin" required
                       class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal Jadwal</label>
                <input type="date" name="tanggal_jadwal" value="{{ old('tanggal_jadwal') }}" required
                       class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Catatan (opsional)</label>
                <input type="text" name="catatan" value="{{ old('catatan') }}"
                       class="w-full px-3 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 text-sm">
            </div>

            <div class="md:col-span-5">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium">
                    <i class="bi bi-plus-circle"></i> Tambah Jadwal
                </button>
            </div>
        </form>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Tipe</th>
                        <th class="text-left px-5 py-3">Item</th>
                        <th class="text-left px-5 py-3">Jenis</th>
                        <th class="text-left px-5 py-3">Tanggal</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-left px-5 py-3">Catatan</th>
                        @if(auth()->user()->level_akses === 'super_admin')
                            <th class="text-right px-5 py-3">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($jadwal as $j)
                        @php
                            if ($j->status === 'Selesai') {
                                $badge = 'bg-slate-200 text-slate-600'; $ket = 'Selesai';
                            } else {
                                $hariLagi = now()->startOfDay()->diffInDays($j->tanggal_jadwal->startOfDay(), false);
                                if ($hariLagi < 0) { $badge = 'bg-red-100 text-red-700'; $ket = 'Terlewat'; }
                                elseif ($hariLagi <= 7) { $badge = 'bg-amber-100 text-amber-700'; $ket = "Segera ({$hariLagi} hari)"; }
                                else { $badge = 'bg-emerald-100 text-emerald-700'; $ket = 'Terjadwal'; }
                            }
                            $tipeColor = match($j->tipe) {
                                'elektronik' => 'bg-indigo-100 text-indigo-700',
                                'non_elektronik' => 'bg-emerald-100 text-emerald-700',
                                'perabotan' => 'bg-amber-100 text-amber-700',
                                'kendaraan' => 'bg-sky-100 text-sky-700',
                                default => 'bg-violet-100 text-violet-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <span class="text-xs px-2 py-1 rounded-full {{ $tipeColor }}">{{ $tipeLabel[$j->tipe] ?? ucfirst($j->tipe) }}</span>
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $j->item_nama }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $j->jenis_maintenance }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $j->tanggal_jadwal->format('d M Y') }}</td>
                            <td class="px-5 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $badge }}">{{ $ket }}</span></td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $j->catatan ?: '-' }}</td>
                            @if(auth()->user()->level_akses === 'super_admin')
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if($j->status !== 'Selesai')
                                            <form id="selesai-{{ $j->id }}" method="POST" action="{{ route('maintenance.selesai', $j) }}">@csrf</form>
                                            <button type="button" onclick="document.getElementById('selesai-{{ $j->id }}').submit()"
                                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        @endif
                                        <form id="hapus-jadwal-{{ $j->id }}" method="POST" action="{{ route('maintenance.destroy', $j) }}">@csrf @method('DELETE')</form>
                                        <button type="button" onclick="confirmHapus('hapus-jadwal-{{ $j->id }}', { title: 'Hapus jadwal ini?' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Belum ada jadwal maintenance.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function gantiTipe() {
        const tipe = document.getElementById('tipeSelect').value;
        document.querySelectorAll('.tipe-item-select').forEach(sel => {
            const aktif = sel.dataset.tipeItem === tipe;
            sel.classList.toggle('hidden', !aktif);
            sel.disabled = !aktif;
        });
    }
    // Set kondisi awal saat halaman dimuat
    gantiTipe();
</script>
@endpush
@endsection
