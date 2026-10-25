@extends('layouts.app')

@section('title', 'Profil Saya - Sistem Inventaris')
@section('page-title', 'Profil Saya')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ===== Kartu ringkas ===== -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 text-center lg:col-span-1 h-fit">
        @if($user->foto_profil)
            <img src="{{ $user->fotoUrl() }}"
                 class="w-28 h-28 rounded-full object-cover mx-auto border-4 border-slate-100 cursor-zoom-in"
                 onclick="zoomImage(this.src, '{{ addslashes($user->nama_lengkap) }}')">
        @else
            <div class="w-28 h-28 rounded-full bg-brand-600 text-white flex items-center justify-center mx-auto text-3xl font-bold">
                {{ strtoupper(substr($user->nama_lengkap, 0, 1)) }}
            </div>
        @endif

        <h2 class="font-semibold text-slate-800 text-lg mt-4">{{ $user->nama_lengkap }}</h2>
        <p class="text-sm text-slate-500">{{ $user->jabatan ?: '-' }}</p>

        <span class="inline-block mt-2 text-xs px-2.5 py-1 rounded-full {{ $user->level_akses === 'super_admin' ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700' }}">
            {{ $user->level_akses === 'super_admin' ? 'Super Admin' : 'Admin' }}
        </span>

        <div class="text-left mt-6 space-y-2 text-sm border-t border-slate-100 pt-4">
            <div class="flex justify-between"><span class="text-slate-400">Username</span><span class="text-slate-700 font-medium">{{ $user->username }}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Email</span><span class="text-slate-700 font-medium">{{ $user->email }}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Bergabung</span><span class="text-slate-700 font-medium">{{ $user->registration_date?->format('d M Y') ?? '-' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Login Terakhir</span><span class="text-slate-700 font-medium">{{ $user->last_login_at?->format('d M Y H:i') ?? 'Sesi ini' }}</span></div>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <!-- ===== Edit Informasi Pribadi ===== -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Informasi Pribadi</h2>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Foto Profil</label>
                    <input type="file" name="foto_profil" accept="image/*" id="inputFotoProfil"
                           class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                    <p class="text-xs text-slate-400 mt-1">Kosongkan jika tidak ingin mengubah foto. Maks 2MB.</p>
                    <img id="previewFotoProfil" class="h-20 w-20 rounded-full object-cover border border-slate-200 mt-2 hidden">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $user->nama_lengkap) }}" required
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" value="{{ old('jabatan', $user->jabatan) }}"
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">No. Telepon</label>
                        <input type="text" name="no_telepon" value="{{ old('no_telepon', $user->no_telepon) }}"
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $user->tanggal_lahir?->format('Y-m-d')) }}" max="{{ date('Y-m-d') }}"
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Alamat</label>
                    <textarea name="alamat" rows="2"
                              class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('alamat', $user->alamat) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Riwayat Pendidikan</label>
                    <textarea name="riwayat_pendidikan" rows="3" placeholder="Contoh: S1 Teknik Informatika - Universitas ABC (2018-2022)"
                              class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('riwayat_pendidikan', $user->riwayat_pendidikan) }}</textarea>
                </div>

                <button type="submit" class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition">
                    <i class="bi bi-check-circle"></i> Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- ===== Ubah Password ===== -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Ubah Password</h2>

            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password Lama <span class="text-red-500">*</span></label>
                    <input type="password" name="password_lama" required
                           class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Password Baru <span class="text-red-500">*</span></label>
                        <input type="password" name="password_baru" minlength="8" required
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password Baru <span class="text-red-500">*</span></label>
                        <input type="password" name="password_baru_confirmation" minlength="8" required
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-medium transition">
                    <i class="bi bi-shield-lock"></i> Ubah Password
                </button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('inputFotoProfil')?.addEventListener('change', function () {
        const file = this.files[0];
        const preview = document.getElementById('previewFotoProfil');
        if (file) {
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
@endsection
