<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi 2FA - Sistem Inventaris</title>
    <link rel="icon" href="{{ asset('images/logo.jpg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;}</style>
    @include('auth.partials._bg-style')
</head>
<body class="min-h-screen auth-bg flex items-center justify-center p-4">
    @include('auth.partials._bg-elements')

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-6">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-white/80 text-xs font-medium mb-5 backdrop-blur-sm">
                <i class="bi bi-shield-check text-teal-300 auth-sparkle"></i> Verifikasi 2 Langkah
            </span>

            <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="w-16 h-16 rounded-full mx-auto auth-logo-ring">

            <h1 class="text-white text-xl font-bold mt-4">Verifikasi 2 Faktor</h1>
            <p class="text-slate-400 text-sm">Kode 6 digit sudah dikirim ke email Anda</p>
        </div>

        <div class="auth-card bg-white rounded-2xl shadow-2xl p-8">
            <form method="POST" action="{{ route('2fa.verify.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1 text-center">Masukkan Kode Verifikasi</label>
                    <input type="text" name="code" maxlength="6" inputmode="numeric" autofocus required
                           placeholder="123456"
                           class="auth-input w-full px-4 py-3 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 text-center text-2xl font-bold tracking-[0.5em]">
                </div>

                <button type="submit" class="auth-btn w-full bg-gradient-to-r from-teal-600 to-blue-600 hover:from-teal-500 hover:to-blue-500 text-white font-medium py-2.5 rounded-lg">
                    Verifikasi &amp; Masuk
                </button>
            </form>

            <form id="form-resend" method="POST" action="{{ route('2fa.resend') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full text-center text-sm text-teal-600 hover:underline">
                    Kirim ulang kode
                </button>
            </form>

            <p class="text-center text-xs text-slate-400 mt-4">Kode berlaku selama 5 menit. Maksimal 8 kali percobaan.</p>

            <p class="text-center text-sm text-slate-500 mt-4 pt-4 border-t border-slate-100">
                <a href="{{ route('login') }}" class="text-slate-500 hover:underline">
                    <i class="bi bi-arrow-left"></i> Batal, kembali ke login
                </a>
            </p>
        </div>
    </div>

    @if(session('success'))
        <script>Swal.fire({ icon: 'success', title: 'Berhasil', text: @json(session('success')), confirmButtonColor: '#0d9488', timer: 2000, timerProgressBar: true });</script>
    @endif
    @if(session('error'))
        <script>Swal.fire({ icon: 'error', title: 'Gagal', text: @json(session('error')), confirmButtonColor: '#0d9488' });</script>
    @endif
    @if($errors->any())
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Periksa kembali isian Anda',
                html: `<ul class="text-left list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>`,
                confirmButtonColor: '#0d9488',
            });
        </script>
    @endif
</body>
</html>
