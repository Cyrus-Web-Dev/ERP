@extends('layouts.app')

@section('title', 'Peminjaman Kendaraan - Sistem Inventaris')
@section('page-title', 'Peminjaman Kendaraan')

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex gap-2 flex-1">
            <select name="status" class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">Semua Status</option>
                <option value="Dipinjam" {{ $statusFilter === 'Dipinjam' ? 'selected' : '' }}>Dipinjam</option>
                <option value="Dikembalikan" {{ $statusFilter === 'Dikembalikan' ? 'selected' : '' }}>Dikembalikan</option>
            </select>
            <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-funnel"></i> Filter</button>
        </form>

        @if(auth()->user()->level_akses === 'super_admin')
            <a href="{{ route('peminjaman.create') }}" class="shrink-0 px-4 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium text-center">
                <i class="bi bi-plus-circle"></i> Pinjam Kendaraan
            </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Foto</th>
                        <th class="text-left px-5 py-3">Peminjam</th>
                        <th class="text-left px-5 py-3">Kendaraan</th>
                        <th class="text-left px-5 py-3">Tujuan</th>
                        <th class="text-left px-5 py-3">Pinjam</th>
                        <th class="text-left px-5 py-3">Kembali</th>
                        <th class="text-left px-5 py-3">Status</th>
                        @if(auth()->user()->level_akses === 'super_admin')
                            <th class="text-right px-5 py-3">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($peminjaman as $p)
                        <tr>
                            <td class="px-5 py-3">
                                @if($p->kendaraan?->foto_kendaraan)
                                    <img src="{{ asset('storage/mobil/'.$p->kendaraan->foto_kendaraan) }}"
                                         class="w-12 h-12 rounded-lg object-cover border border-slate-200 cursor-zoom-in"
                                         onclick="zoomImage(this.src, '{{ addslashes($p->kendaraan->merk) }} ({{ $p->kendaraan->plat_nomor }})')">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-300"><i class="bi bi-truck-front"></i></div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $p->peminjam }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $p->kendaraan->merk ?? '-' }} ({{ $p->kendaraan->plat_nomor ?? '-' }})</td>
                            <td class="px-5 py-3 text-slate-600">{{ $p->tujuan }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $p->tanggal_pinjam->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-slate-500 text-xs">{{ $p->tanggal_kembali->format('d M Y') }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs px-2 py-1 rounded-full {{ $p->status === 'Dipinjam' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $p->status }}
                                </span>
                            </td>
                            @if(auth()->user()->level_akses === 'super_admin')
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if($p->status === 'Dipinjam')
                                            <form id="kembalikan-{{ $p->id_peminjaman }}" method="POST" action="{{ route('peminjaman.kembalikan', $p) }}">@csrf</form>
                                            <button type="button" onclick="konfirmasiKembalikan('kembalikan-{{ $p->id_peminjaman }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">
                                                <i class="bi bi-arrow-return-left"></i> Kembalikan
                                            </button>
                                        @endif
                                        <form id="hapus-peminjaman-{{ $p->id_peminjaman }}" method="POST" action="{{ route('peminjaman.destroy', $p) }}">@csrf @method('DELETE')</form>
                                        <button type="button" onclick="confirmHapus('hapus-peminjaman-{{ $p->id_peminjaman }}', { title: 'Hapus data peminjaman ini?' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Belum ada data peminjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($peminjaman->hasPages())
        <div>{{ $peminjaman->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
    function konfirmasiKembalikan(formId) {
        Swal.fire({
            icon: 'question',
            title: 'Tandai kendaraan sudah dikembalikan?',
            showCancelButton: true,
            confirmButtonText: 'Ya, dikembalikan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#64748b',
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(formId).submit();
        });
    }
</script>
@endpush
@endsection
