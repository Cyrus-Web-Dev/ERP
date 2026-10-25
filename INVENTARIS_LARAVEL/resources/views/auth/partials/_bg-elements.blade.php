<!-- Wrapper fixed terpisah dari body, supaya background TIDAK pernah mematikan scroll halaman -->
<div class="fixed inset-0 overflow-hidden -z-10 pointer-events-none">
    <!-- Blob cahaya mengambang -->
    <div class="auth-blob auth-blob-1 w-96 h-96 -top-20 -left-20"></div>
    <div class="auth-blob auth-blob-2 w-96 h-96 top-1/3 -right-24"></div>
    <div class="auth-blob auth-blob-3 w-80 h-80 bottom-0 left-1/4"></div>

    <!-- Garis gradien mengalir di bagian bawah, meniru referensi -->
    <svg class="absolute bottom-0 left-0 w-full h-56 opacity-60" viewBox="0 0 1440 240" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="authLineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#2dd4bf"/>
                <stop offset="50%" stop-color="#7c3aed"/>
                <stop offset="100%" stop-color="#3568f7"/>
            </linearGradient>
        </defs>
        <path d="M -100 180 C 250 100, 450 220, 720 140 S 1250 60, 1540 160"
              fill="none" stroke="url(#authLineGrad)" stroke-width="2.5"
              stroke-dasharray="10 14" style="animation: authDrift 22s linear infinite;"/>
        <path d="M -100 200 C 280 260, 500 140, 760 190 S 1280 240, 1540 120"
              fill="none" stroke="url(#authLineGrad)" stroke-width="1.5" opacity=".5"
              stroke-dasharray="6 10" style="animation: authDrift 30s linear infinite reverse;"/>
    </svg>
</div>
