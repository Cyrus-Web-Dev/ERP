<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daftar Akun - Sistem Inventaris</title>
    <link rel="icon" href="{{ asset('images/logo.jpg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;}</style>
    @include('auth.partials._bg-style')
</head>
<body class="min-h-screen auth-bg flex items-center justify-center p-4 py-10">
    @include('auth.partials._bg-elements')

    <div class="w-full max-w-lg relative z-10">
        <div class="text-center mb-6">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-white/80 text-xs font-medium mb-5 backdrop-blur-sm">
                <i class="bi bi-stars text-teal-300 auth-sparkle"></i> Bergabung Sekarang
            </span>

            <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="w-16 h-16 rounded-full mx-auto auth-logo-ring">

            <h1 class="text-white text-xl font-bold mt-4">Daftar Akun Baru</h1>
            <p class="text-slate-400 text-sm">Verifikasi email diperlukan sebelum akun bisa dibuat</p>
        </div>

        <div class="auth-card bg-white rounded-2xl shadow-2xl p-8">
            <form method="POST" action="{{ route('register.store') }}" id="formRegister" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" required
                           class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" name="username" value="{{ old('username') }}" required
                               class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" value="{{ old('jabatan') }}" required
                               class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" required minlength="8"
                           class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-slate-400 mt-1">Minimal 8 karakter.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Daftar sebagai</label>
                    <select name="role" required
                            class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih role --</option>
                        <option value="pengelola" {{ old('role') === 'pengelola' ? 'selected' : '' }}>Pengelola (Super Admin)</option>
                        <option value="pembaca" {{ old('role') === 'pembaca' ? 'selected' : '' }}>Pembaca (Admin)</option>
                    </select>
                </div>

                <hr class="border-slate-200">

                <!-- ===== Bagian Verifikasi Email ===== -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <div class="flex gap-2">
                        <input type="email" name="email" id="inputEmail" value="{{ old('email') }}" required
                               class="auth-input flex-1 px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" id="btnKirimKode"
                                class="auth-btn shrink-0 px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium">
                            Kirim Kode
                        </button>
                    </div>
                </div>

                <div id="blokKodeVerifikasi" class="hidden">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kode Verifikasi (6 digit)</label>
                    <div class="flex gap-2 items-center">
                        <input type="text" id="inputKode" maxlength="6" inputmode="numeric" placeholder="123456"
                               class="auth-input flex-1 px-4 py-2.5 rounded-lg border border-slate-300 tracking-[0.5em] text-center font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" id="btnVerifikasiKode"
                                class="auth-btn shrink-0 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
                            Verifikasi
                        </button>
                    </div>
                    <p class="text-xs mt-1" id="statusVerifikasi"></p>
                </div>

                <button type="submit" id="btnDaftar" disabled
                        class="auth-btn w-full bg-gradient-to-r from-blue-600 to-violet-600 hover:from-blue-500 hover:to-violet-500 disabled:from-slate-300 disabled:to-slate-300 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-lg">
                    Daftar
                </button>
            </form>

            <p class="text-center text-sm text-slate-500 mt-6">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="text-blue-600 font-medium hover:underline">Masuk</a>
            </p>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let emailTerverifikasi = null;
        let cooldownTimer = null;

        function toastError(msg) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: msg, confirmButtonColor: '#254bea' });
        }
        function toastSuccess(msg) {
            Swal.fire({ icon: 'success', title: 'Berhasil', text: msg, confirmButtonColor: '#254bea', timer: 2000, timerProgressBar: true });
        }

        document.getElementById('btnKirimKode').addEventListener('click', async function () {
            const email = document.getElementById('inputEmail').value.trim();
            if (!email) { toastError('Isi email terlebih dahulu.'); return; }

            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Mengirim...';

            try {
                const res = await fetch(@json(route('register.send-code')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ email }),
                });
                const data = await res.json();

                if (data.success) {
                    toastSuccess(data.message);
                    document.getElementById('blokKodeVerifikasi').classList.remove('hidden');
                    let sisa = 30;
                    btn.textContent = `Kirim ulang (${sisa}s)`;
                    cooldownTimer = setInterval(() => {
                        sisa--;
                        if (sisa <= 0) {
                            clearInterval(cooldownTimer);
                            btn.disabled = false;
                            btn.textContent = 'Kirim Ulang';
                        } else {
                            btn.textContent = `Kirim ulang (${sisa}s)`;
                        }
                    }, 1000);
                } else {
                    toastError(data.message);
                    btn.disabled = false;
                    btn.textContent = 'Kirim Kode';
                }
            } catch (e) {
                toastError('Terjadi kesalahan. Coba lagi.');
                btn.disabled = false;
                btn.textContent = 'Kirim Kode';
            }
        });

        document.getElementById('btnVerifikasiKode').addEventListener('click', async function () {
            const email = document.getElementById('inputEmail').value.trim();
            const code = document.getElementById('inputKode').value.trim();
            const status = document.getElementById('statusVerifikasi');

            if (code.length !== 6) { toastError('Kode harus 6 digit.'); return; }

            try {
                const res = await fetch(@json(route('register.verify-code')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ email, code }),
                });
                const data = await res.json();

                if (data.success) {
                    emailTerverifikasi = email;
                    status.textContent = '✓ Email terverifikasi';
                    status.className = 'text-xs mt-1 text-emerald-600 font-medium';
                    document.getElementById('inputEmail').readOnly = true;
                    document.getElementById('btnDaftar').disabled = false;
                    toastSuccess(data.message);
                } else {
                    status.textContent = data.message;
                    status.className = 'text-xs mt-1 text-red-600';
                    toastError(data.message);
                }
            } catch (e) {
                toastError('Terjadi kesalahan. Coba lagi.');
            }
        });

        document.getElementById('formRegister').addEventListener('submit', function (e) {
            const email = document.getElementById('inputEmail').value.trim();
            if (emailTerverifikasi !== email) {
                e.preventDefault();
                toastError('Silakan verifikasi email terlebih dahulu.');
            }
        });

        @if($errors->any())
            Swal.fire({
                icon: 'warning',
                title: 'Periksa kembali isian Anda',
                html: `<ul class="text-left list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>`,
                confirmButtonColor: '#254bea',
            });
        @endif
    </script>
</body>
</html>
