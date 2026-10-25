<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Sistem Inventaris</title>
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
                <i class="bi bi-stars text-teal-300 auth-sparkle"></i> Pemulihan Akun
            </span>

            <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="w-16 h-16 rounded-full mx-auto auth-logo-ring">

            <h1 class="text-white text-xl font-bold mt-4">Lupa Password</h1>
            <p class="text-slate-400 text-sm">Permintaan Anda akan ditinjau oleh Super Admin</p>
        </div>

        <div class="auth-card bg-white rounded-2xl shadow-2xl p-8">
            <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3 mb-5 flex gap-2">
                <i class="bi bi-info-circle mt-0.5"></i>
                <span>Demi keamanan, link reset password tidak dikirim otomatis. Super Admin akan meninjau dan menyetujui permintaan Anda terlebih dahulu.</span>
            </div>

            <form method="POST" action="{{ route('password.request.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}" required
                           class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email Terdaftar</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="auth-input w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit"
                        class="auth-btn w-full bg-gradient-to-r from-blue-600 to-violet-600 hover:from-blue-500 hover:to-violet-500 text-white font-medium py-2.5 rounded-lg">
                    Kirim Permohonan
                </button>
            </form>

            <p class="text-center text-sm text-slate-500 mt-6">
                <a href="{{ route('login') }}" class="text-blue-600 font-medium hover:underline">
                    <i class="bi bi-arrow-left"></i> Kembali ke Login
                </a>
            </p>
        </div>
    </div>

    @if(session('success'))
        <script>Swal.fire({ icon: 'success', title: 'Terkirim', text: @json(session('success')), confirmButtonColor: '#254bea' });</script>
    @endif
    @if(session('error'))
        <script>Swal.fire({ icon: 'error', title: 'Gagal', text: @json(session('error')), confirmButtonColor: '#254bea' });</script>
    @endif
    @if($errors->any())
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Periksa kembali isian Anda',
                html: `<ul class="text-left list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>`,
                confirmButtonColor: '#254bea',
            });
        </script>
    @endif
</body>
</html>
