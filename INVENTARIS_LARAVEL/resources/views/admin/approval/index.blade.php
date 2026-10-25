@extends('layouts.app')

@section('title', 'Persetujuan User - Sistem Inventaris')
@section('page-title', 'Persetujuan User')

@section('content')
<div class="space-y-6">

    <!-- ===== Pendaftaran Baru ===== -->
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Menunggu Persetujuan Pendaftaran</h2>
            <span class="text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">{{ $pendingUsers->count() }} pending</span>
        </div>

        @if($pendingUsers->isEmpty())
            <p class="text-sm text-slate-400 px-6 py-8 text-center">Tidak ada pendaftaran yang menunggu persetujuan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="text-left px-6 py-3">Nama</th>
                            <th class="text-left px-6 py-3">Username</th>
                            <th class="text-left px-6 py-3">Email</th>
                            <th class="text-left px-6 py-3">Jabatan</th>
                            <th class="text-left px-6 py-3">Role</th>
                            <th class="text-left px-6 py-3">Daftar</th>
                            <th class="text-right px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pendingUsers as $u)
                            <tr>
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $u->nama_lengkap }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $u->username }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $u->email }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $u->jabatan }}</td>
                                <td class="px-6 py-3">
                                    <span class="text-xs px-2 py-1 rounded-full {{ $u->level_akses === 'super_admin' ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700' }}">
                                        {{ $u->level_akses === 'super_admin' ? 'Super Admin' : 'Admin' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-slate-500 text-xs">{{ $u->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form id="approve-user-{{ $u->id_user }}" method="POST" action="{{ route('admin-approval.approve-user', $u->id_user) }}">
                                            @csrf
                                        </form>
                                        <button type="button"
                                                onclick="konfirmasiAksi('approve-user-{{ $u->id_user }}', 'Setujui pendaftaran {{ $u->username }}?', 'success')"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">
                                            <i class="bi bi-check-lg"></i> Setujui
                                        </button>

                                        <form id="reject-user-{{ $u->id_user }}" method="POST" action="{{ route('admin-approval.reject-user', $u->id_user) }}">
                                            @csrf
                                        </form>
                                        <button type="button"
                                                onclick="konfirmasiAksi('reject-user-{{ $u->id_user }}', 'Tolak dan hapus pendaftaran {{ $u->username }}? Tindakan ini tidak bisa dibatalkan.', 'error')"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium">
                                            <i class="bi bi-x-lg"></i> Tolak
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- ===== Permintaan Reset Password ===== -->
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Permintaan Reset Password</h2>
            <span class="text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">{{ $pendingResets->count() }} pending</span>
        </div>

        @if($pendingResets->isEmpty())
            <p class="text-sm text-slate-400 px-6 py-8 text-center">Tidak ada permintaan reset password.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="text-left px-6 py-3">Nama</th>
                            <th class="text-left px-6 py-3">Username</th>
                            <th class="text-left px-6 py-3">Email</th>
                            <th class="text-left px-6 py-3">Diminta</th>
                            <th class="text-right px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pendingResets as $u)
                            <tr>
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $u->nama_lengkap }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $u->username }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $u->email }}</td>
                                <td class="px-6 py-3 text-slate-500 text-xs">{{ $u->reset_requested_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form id="approve-reset-{{ $u->id_user }}" method="POST" action="{{ route('admin-approval.approve-reset', $u->id_user) }}">
                                            @csrf
                                        </form>
                                        <button type="button"
                                                onclick="konfirmasiAksi('approve-reset-{{ $u->id_user }}', 'Setujui reset password untuk {{ $u->username }}? Link reset akan dikirim ke email user.', 'success')"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">
                                            <i class="bi bi-check-lg"></i> Setujui
                                        </button>
                                        <button type="button"
                                                onclick="tolakReset('{{ $u->id_user }}', '{{ $u->username }}')"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium">
                                            <i class="bi bi-x-lg"></i> Tolak
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function konfirmasiAksi(formId, teks, icon) {
        Swal.fire({
            icon: icon,
            title: 'Konfirmasi',
            text: teks,
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: icon === 'error' ? '#dc2626' : '#16a34a',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(formId).submit();
        });
    }

    function tolakReset(userId, username) {
        Swal.fire({
            icon: 'warning',
            title: `Tolak reset password ${username}?`,
            input: 'text',
            inputPlaceholder: 'Alasan penolakan (opsional)',
            showCancelButton: true,
            confirmButtonText: 'Tolak',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('/persetujuan-user') }}/${userId}/reject-reset`;
                form.innerHTML = `@csrf <input type="hidden" name="message" value="${(result.value || '').replace(/"/g, '&quot;')}">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endpush
@endsection
