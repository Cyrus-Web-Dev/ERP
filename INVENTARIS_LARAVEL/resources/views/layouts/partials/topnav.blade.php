@php
    $menuUtama = [
        ['route' => 'dashboard', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
        ['route' => 'laporan.index', 'icon' => 'bi-file-text', 'label' => 'Laporan'],
        ['route' => 'depresiasi.index', 'icon' => 'bi-graph-down-arrow', 'label' => 'Depresiasi Aset'],
    ];

    $menuAset = [
        ['route' => 'bangunan.index', 'icon' => 'bi-building', 'label' => 'Data Gedung Perusahaan'],
        ['route' => 'elektronik.index', 'icon' => 'bi-cpu-fill', 'label' => 'Data Barang Elektronik'],
        ['route' => 'non-elektronik.index', 'icon' => 'bi-tools', 'label' => 'Data Barang Non Elektronik'],
        ['route' => 'perabotan.index', 'icon' => 'bi-lamp-fill', 'label' => 'Data Perabotan'],
        ['route' => 'kendaraan.index', 'icon' => 'bi-truck-front-fill', 'label' => 'Kendaraan Operasional'],
    ];

    $menuTransaksi = [
        ['route' => 'penggunaan.index', 'icon' => 'bi-clipboard2-check-fill', 'label' => 'Penggunaan Barang Elektronik'],
        ['route' => 'peminjaman.index', 'icon' => 'bi-key-fill', 'label' => 'Peminjaman Kendaraan'],
        ['route' => 'label-qr.index', 'icon' => 'bi-qr-code', 'label' => 'Cetak Label QR'],
        ['route' => 'maintenance.index', 'icon' => 'bi-tools', 'label' => 'Jadwal Maintenance'],
    ];

    $menuPengadaan = [
        ['route' => 'pengajuan.index', 'icon' => 'bi-send-fill', 'label' => 'Pengajuan ke Pengadaan'],
        ['route' => 'laporan-pengadaan.index', 'icon' => 'bi-inbox-fill', 'label' => 'Laporan dari Pengadaan'],
    ];

    $menuAdmin = [
        ['route' => 'activity-logs.index', 'icon' => 'bi-clock-history', 'label' => 'Log Aktivitas'],
        ['route' => 'admin-approval.index', 'icon' => 'bi-person-check', 'label' => 'Persetujuan User'],
        ['route' => 'manage-accounts.index', 'icon' => 'bi-people', 'label' => 'Manajemen Akun'],
        ['route' => 'backup.index', 'icon' => 'bi-database', 'label' => 'Backup Data'],
    ];

    // Aktif juga di halaman turunannya (mis. pengajuan.show / pengajuan.create saat item menunya pengajuan.index).
    $cekAktif = fn (string $routeName) => request()->routeIs($routeName)
        || request()->routeIs($routeName.'.*')
        || request()->routeIs(\Illuminate\Support\Str::before($routeName, '.').'.*');
    $grupAktif = fn (array $items) => collect($items)->contains(fn ($m) => $cekAktif($m['route']));

    // Jumlah kabar baru dari Pengadaan (tidak boleh membuat halaman gagal bila migrasi belum dijalankan).
    try {
        $laporanBaru = \App\Models\LaporanPengadaan::belumDibaca();
    } catch (\Throwable $e) {
        $laporanBaru = 0;
    }

    $jumlahPending = 0;
    if (auth()->user()?->level_akses === 'super_admin') {
        $jumlahPending = \App\Models\User::where('approved', 0)->count()
            + \App\Models\User::whereNotNull('reset_requested_at')->count();
    }
@endphp

<header class="bg-white border-b border-slate-200 sticky top-0 z-40">
    <div class="max-w-[1600px] mx-auto px-4 md:px-6">
        <div class="h-16 flex items-center justify-between gap-4">

            <!-- Logo -->
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 shrink-0">
                <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="w-9 h-9 rounded-full object-cover">
                <span class="font-semibold text-slate-800 hidden sm:block">Sistem Inventaris</span>
            </a>

            <!-- Nav desktop -->
            <nav class="hidden lg:flex items-center gap-1 flex-1 justify-center text-sm">
                @foreach($menuUtama as $m)
                    <a href="{{ route($m['route']) }}"
                       class="px-3 py-2 rounded-lg font-medium transition
                              {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        {{ $m['label'] }}
                    </a>
                @endforeach

                @foreach([['Data Aset', $menuAset, 'bi-box-seam'], ['Transaksi', $menuTransaksi, 'bi-arrow-left-right'], ['Pengadaan', $menuPengadaan, 'bi-truck']] as [$judulGrup, $items, $iconGrup])
                    <div class="relative navgroup">
                        <button type="button" class="navgroup-btn flex items-center gap-1.5 px-3 py-2 rounded-lg font-medium transition
                                {{ $grupAktif($items) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                            <i class="bi {{ $iconGrup }} text-xs"></i>
                            {{ $judulGrup }}
                            @if($judulGrup === 'Pengadaan' && $laporanBaru > 0)
                                <span class="text-[10px] font-bold px-1.5 rounded-full bg-red-500 text-white leading-tight">{{ $laporanBaru }}</span>
                            @endif
                            <i class="bi bi-chevron-down text-[10px] opacity-60"></i>
                        </button>
                        <div class="navgroup-panel hidden absolute left-1/2 -translate-x-1/2 mt-2 w-72 bg-white rounded-xl shadow-lg border border-slate-200 p-2 z-40">
                            @foreach($items as $m)
                                <a href="{{ route($m['route']) }}"
                                   class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-sm transition
                                          {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <span class="flex items-center gap-3"><i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}</span>
                                    @if($m['route'] === 'laporan-pengadaan.index' && $laporanBaru > 0)
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-red-500 text-white leading-none">{{ $laporanBaru }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if(auth()->user()?->level_akses === 'super_admin')
                    <div class="relative navgroup">
                        <button type="button" class="navgroup-btn flex items-center gap-1.5 px-3 py-2 rounded-lg font-medium transition
                                {{ $grupAktif($menuAdmin) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                            <i class="bi bi-shield-lock text-xs"></i>
                            Admin
                            @if($jumlahPending > 0)
                                <span class="text-[10px] font-bold px-1.5 rounded-full bg-red-500 text-white leading-tight">{{ $jumlahPending }}</span>
                            @endif
                            <i class="bi bi-chevron-down text-[10px] opacity-60"></i>
                        </button>
                        <div class="navgroup-panel hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-lg border border-slate-200 p-2 z-40">
                            @foreach($menuAdmin as $m)
                                <a href="{{ route($m['route']) }}"
                                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition
                                          {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <span class="flex items-center gap-3"><i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}</span>
                                    @if($m['route'] === 'admin-approval.index' && $jumlahPending > 0)
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-red-500 text-white leading-none">{{ $jumlahPending }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </nav>

            <!-- Kanan: user menu + hamburger mobile -->
            <div class="flex items-center gap-2 shrink-0">
                <div class="relative">
                    <button type="button" id="userMenuBtn" class="flex items-center gap-2 hover:bg-slate-50 rounded-lg px-2 py-1.5 transition">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-medium text-slate-800 leading-tight">{{ auth()->user()->nama_lengkap ?? '-' }}</p>
                            <p class="text-xs text-slate-500 leading-tight">
                                {{ auth()->user()->level_akses === 'super_admin' ? 'Super Admin' : 'Admin' }}
                            </p>
                        </div>
                        @if(auth()->user()?->foto_profil)
                            <img src="{{ auth()->user()->fotoUrl() }}" class="w-9 h-9 rounded-full object-cover border border-slate-200">
                        @else
                            <div class="w-9 h-9 rounded-full bg-brand-600 text-white flex items-center justify-center font-semibold">
                                {{ strtoupper(substr(auth()->user()->nama_lengkap ?? 'U', 0, 1)) }}
                            </div>
                        @endif
                        <i class="bi bi-chevron-down text-slate-400 text-xs hidden sm:block"></i>
                    </button>

                    <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-slate-200 py-2 z-40">
                        <a href="{{ route('profile.show') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="bi bi-person-circle"></i> Profil Saya
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="bi bi-box-arrow-right"></i> Keluar
                            </button>
                        </form>
                    </div>
                </div>

                <button type="button" id="mobileMenuBtn" class="lg:hidden text-slate-500 hover:text-brand-600 text-2xl px-1">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Menu mobile (drawer di bawah navbar) -->
    <div id="mobileMenu" class="hidden lg:hidden border-t border-slate-200 max-h-[75vh] overflow-y-auto">
        <nav class="px-4 py-3 space-y-4 text-sm">
            <div>
                @foreach($menuUtama as $m)
                    <a href="{{ route($m['route']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600' }}">
                        <i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}
                    </a>
                @endforeach
            </div>
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Data Aset</p>
                @foreach($menuAset as $m)
                    <a href="{{ route($m['route']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600' }}">
                        <i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}
                    </a>
                @endforeach
            </div>
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Transaksi</p>
                @foreach($menuTransaksi as $m)
                    <a href="{{ route($m['route']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600' }}">
                        <i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}
                    </a>
                @endforeach
            </div>
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Pengadaan</p>
                @foreach($menuPengadaan as $m)
                    <a href="{{ route($m['route']) }}" class="flex items-center justify-between px-3 py-2.5 rounded-lg {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600' }}">
                        <span class="flex items-center gap-3"><i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}</span>
                        @if($m['route'] === 'laporan-pengadaan.index' && $laporanBaru > 0)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-red-500 text-white leading-none">{{ $laporanBaru }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            @if(auth()->user()?->level_akses === 'super_admin')
                <div>
                    <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Administrasi</p>
                    @foreach($menuAdmin as $m)
                        <a href="{{ route($m['route']) }}" class="flex items-center justify-between px-3 py-2.5 rounded-lg {{ $cekAktif($m['route']) ? 'bg-brand-50 text-brand-700' : 'text-slate-600' }}">
                            <span class="flex items-center gap-3"><i class="bi {{ $m['icon'] }}"></i> {{ $m['label'] }}</span>
                            @if($m['route'] === 'admin-approval.index' && $jumlahPending > 0)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-red-500 text-white leading-none">{{ $jumlahPending }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </nav>
    </div>
</header>

@push('scripts')
<script>
    // Dropdown grup menu desktop (Data Aset / Transaksi / Admin)
    document.querySelectorAll('.navgroup').forEach(group => {
        const btn = group.querySelector('.navgroup-btn');
        const panel = group.querySelector('.navgroup-panel');
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const sedangTerbuka = !panel.classList.contains('hidden');
            document.querySelectorAll('.navgroup-panel').forEach(p => p.classList.add('hidden'));
            if (!sedangTerbuka) panel.classList.remove('hidden');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.navgroup-panel').forEach(p => p.classList.add('hidden'));
    });

    // Menu mobile
    document.getElementById('mobileMenuBtn')?.addEventListener('click', function () {
        document.getElementById('mobileMenu')?.classList.toggle('hidden');
    });
</script>
@endpush
