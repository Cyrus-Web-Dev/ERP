@extends('layouts.app')

@section('title', 'Backup Data - Sistem Inventaris')
@section('page-title', 'Backup Data')

@section('content')
<div class="space-y-5">

    <div class="bg-white rounded-xl border border-slate-200 p-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="font-semibold text-slate-800">Buat Backup Baru</h2>
            <p class="text-sm text-slate-500 mt-1">Membuat file dump SQL lengkap dari seluruh tabel database saat ini.</p>
        </div>
        <form id="form-backup" method="POST" action="{{ route('backup.store') }}">
            @csrf
        </form>
        <button type="button" onclick="konfirmasiBackup()" class="px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium">
            <i class="bi bi-database-add"></i> Buat Backup Sekarang
        </button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800">Riwayat Backup</h2>
        </div>

        @if($files->isEmpty())
            <p class="text-sm text-slate-400 px-6 py-10 text-center">Belum ada file backup. Klik "Buat Backup Sekarang" di atas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="text-left px-6 py-3">Nama File</th>
                            <th class="text-left px-6 py-3">Ukuran</th>
                            <th class="text-left px-6 py-3">Dibuat</th>
                            <th class="text-right px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($files as $f)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-slate-700">{{ $f['nama'] }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $f['ukuran'] }}</td>
                                <td class="px-6 py-3 text-slate-500 text-xs">{{ \Illuminate\Support\Carbon::createFromTimestamp($f['dibuat'])->format('d M Y H:i') }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('backup.download', $f['nama']) }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-medium">
                                            <i class="bi bi-download"></i> Unduh
                                        </a>
                                        <form id="hapus-backup-{{ $loop->index }}" method="POST" action="{{ route('backup.destroy', $f['nama']) }}">@csrf @method('DELETE')</form>
                                        <button type="button" onclick="confirmHapus('hapus-backup-{{ $loop->index }}', { title: 'Hapus file {{ $f['nama'] }}?' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3">
        <i class="bi bi-info-circle"></i> File backup disimpan di server (folder privat, tidak bisa diakses langsung lewat URL). Untuk keamanan tambahan, disarankan mengunduh dan menyimpan salinannya secara berkala di tempat lain.
    </div>
</div>

@push('scripts')
<script>
    function konfirmasiBackup() {
        Swal.fire({
            icon: 'question',
            title: 'Buat backup database sekarang?',
            text: 'Proses ini bisa memakan waktu beberapa detik tergantung ukuran data.',
            showCancelButton: true,
            confirmButtonText: 'Ya, buat backup',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#254bea',
            cancelButtonColor: '#64748b',
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('form-backup').submit();
        });
    }
</script>
@endpush
@endsection
