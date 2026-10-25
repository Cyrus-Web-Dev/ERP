<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistem Inventaris')</title>
    <link rel="icon" href="{{ asset('images/logo.jpg') }}">

    <!-- Tailwind (Play CDN - cukup untuk development di XAMPP/Laragon tanpa build step) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef4ff', 100: '#dbe8ff', 200: '#bcd4ff', 300: '#8fb6ff',
                            400: '#5c8fff', 500: '#3568f7', 600: '#254bea', 700: '#1e3ac2',
                            800: '#1e319a', 900: '#1c2c79', 950: '#141d4d',
                        },
                        ink: '#111827',
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- SweetAlert2 untuk semua notifikasi CRUD -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-100 text-ink antialiased min-h-screen">

    @include('layouts.partials.topnav')

    <main class="max-w-[1600px] mx-auto p-4 md:p-6">
        @yield('content')
    </main>

    <!-- ===== Modal Zoom Foto (global, dipanggil lewat zoomImage(src, alt)) ===== -->
    <div id="imageZoomModal" class="hidden fixed inset-0 z-[100] bg-black/80 items-center justify-center p-4" onclick="closeImageZoom()">
        <button type="button" onclick="closeImageZoom()" class="absolute top-4 right-4 text-white/80 hover:text-white text-3xl leading-none">
            <i class="bi bi-x-lg"></i>
        </button>
        <img id="imageZoomImg" src="" alt="" class="max-w-full max-h-full rounded-lg shadow-2xl" onclick="event.stopPropagation()">
        <p id="imageZoomCaption" class="absolute bottom-6 left-0 right-0 text-center text-white/90 text-sm"></p>
    </div>

    <script>
        // ===== Dropdown menu user (avatar pojok kanan atas) =====
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userMenuDropdown = document.getElementById('userMenuDropdown');
        userMenuBtn?.addEventListener('click', function (e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', function (e) {
            if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden') && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });

        // ===== Zoom foto =====
        function zoomImage(src, caption) {
            if (!src) return;
            document.getElementById('imageZoomImg').src = src;
            document.getElementById('imageZoomCaption').textContent = caption || '';
            document.getElementById('imageZoomModal').classList.remove('hidden');
            document.getElementById('imageZoomModal').classList.add('flex');
        }
        function closeImageZoom() {
            document.getElementById('imageZoomModal').classList.add('hidden');
            document.getElementById('imageZoomModal').classList.remove('flex');
            document.getElementById('imageZoomImg').src = '';
        }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeImageZoom(); });

        // ===== Notifikasi flash session -> SweetAlert2 =====
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: @json(session('success')),
                confirmButtonColor: '#254bea',
                timer: 2200,
                timerProgressBar: true,
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: @json(session('error')),
                confirmButtonColor: '#254bea',
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'warning',
                title: 'Periksa kembali isian Anda',
                html: `<ul class="text-left list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>`,
                confirmButtonColor: '#254bea',
            });
        @endif

        /**
         * Helper global: konfirmasi hapus data dengan SweetAlert2,
         * lalu submit form dengan method DELETE.
         */
        function confirmHapus(formId, opts = {}) {
            Swal.fire({
                icon: 'warning',
                title: opts.title || 'Hapus data ini?',
                text: opts.text || 'Data yang sudah dihapus tidak bisa dikembalikan.',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            });
            return false;
        }
    </script>

    @stack('scripts')
</body>
</html>
