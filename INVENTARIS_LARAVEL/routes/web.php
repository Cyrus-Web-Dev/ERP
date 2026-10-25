<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes (Fase 2: login, register + verifikasi email, forgot/reset
| password admin-mediated - sama seperti alur project PHP native)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
    Route::post('/register/send-code', [RegisterController::class, 'sendCode'])->name('register.send-code');
    Route::post('/register/verify-code', [RegisterController::class, 'verifyCode'])->name('register.verify-code');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'submitRequest'])->name('password.request.submit');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset.form');
    Route::post('/reset-password/{token}', [PasswordResetController::class, 'resetPassword'])->name('password.reset.submit');

    Route::get('/2fa/verify', [\App\Http\Controllers\Auth\TwoFactorController::class, 'show'])->name('2fa.verify');
    Route::post('/2fa/verify', [\App\Http\Controllers\Auth\TwoFactorController::class, 'verify'])->name('2fa.verify.submit');
    Route::post('/2fa/resend', [\App\Http\Controllers\Auth\TwoFactorController::class, 'resend'])->name('2fa.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', fn () => redirect()->route('dashboard'));

// Halaman publik hasil scan QR label (sengaja TANPA middleware auth,
// supaya bisa dibuka langsung dari kamera HP tanpa perlu login -
// sama seperti view_item.php lama. Tidak menampilkan data harga/finansial.)
Route::get('/lihat/{type}/{id}', [\App\Http\Controllers\PublicItemController::class, 'show'])->name('public.view-item');

/*
|--------------------------------------------------------------------------
| Halaman terproteksi (harus login + akun disetujui admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'approved'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profil', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profil', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/laporan', [\App\Http\Controllers\LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/export-pdf', [\App\Http\Controllers\LaporanController::class, 'exportPdf'])->name('laporan.export-pdf');
    Route::get('/laporan/export-csv', [\App\Http\Controllers\LaporanController::class, 'exportCsv'])->name('laporan.export-csv');
    Route::get('/depresiasi-aset', [\App\Http\Controllers\DepresiasiController::class, 'index'])->name('depresiasi.index');

    // ===== Modul Data Aset (CRUD akan dibangun di Fase 3 dst) =====
    Route::prefix('bangunan-gedung')->name('bangunan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BangunanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/tambah', [\App\Http\Controllers\BangunanController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\BangunanController::class, 'store'])->name('store');
            Route::get('/{bangunan}/edit', [\App\Http\Controllers\BangunanController::class, 'edit'])->name('edit');
            Route::put('/{bangunan}', [\App\Http\Controllers\BangunanController::class, 'update'])->name('update');
            Route::delete('/{bangunan}', [\App\Http\Controllers\BangunanController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('data-elektronik')->name('elektronik.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BarangController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/tambah', [\App\Http\Controllers\BarangController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\BarangController::class, 'store'])->name('store');
            Route::get('/{barang}/edit', [\App\Http\Controllers\BarangController::class, 'edit'])->name('edit');
            Route::put('/{barang}', [\App\Http\Controllers\BarangController::class, 'update'])->name('update');
            Route::delete('/{barang}', [\App\Http\Controllers\BarangController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('data-non-elektronik')->name('non-elektronik.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AlatController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/tambah', [\App\Http\Controllers\AlatController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\AlatController::class, 'store'])->name('store');
            Route::get('/{alat}/edit', [\App\Http\Controllers\AlatController::class, 'edit'])->name('edit');
            Route::put('/{alat}', [\App\Http\Controllers\AlatController::class, 'update'])->name('update');
            Route::delete('/{alat}', [\App\Http\Controllers\AlatController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('data-perabotan')->name('perabotan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PerabotanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/tambah', [\App\Http\Controllers\PerabotanController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\PerabotanController::class, 'store'])->name('store');
            Route::get('/{perabotan}/edit', [\App\Http\Controllers\PerabotanController::class, 'edit'])->name('edit');
            Route::put('/{perabotan}', [\App\Http\Controllers\PerabotanController::class, 'update'])->name('update');
            Route::delete('/{perabotan}', [\App\Http\Controllers\PerabotanController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('data-kendaraan')->name('kendaraan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KendaraanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/tambah', [\App\Http\Controllers\KendaraanController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\KendaraanController::class, 'store'])->name('store');
            Route::get('/{kendaraan}/edit', [\App\Http\Controllers\KendaraanController::class, 'edit'])->name('edit');
            Route::put('/{kendaraan}', [\App\Http\Controllers\KendaraanController::class, 'update'])->name('update');
            Route::delete('/{kendaraan}', [\App\Http\Controllers\KendaraanController::class, 'destroy'])->name('destroy');
        });
    });

    // ===== Modul Transaksi =====
    Route::prefix('penggunaan-elektronik')->name('penggunaan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PenggunaanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/tambah', [\App\Http\Controllers\PenggunaanController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\PenggunaanController::class, 'store'])->name('store');
            Route::get('/{penggunaan}/edit', [\App\Http\Controllers\PenggunaanController::class, 'edit'])->name('edit');
            Route::put('/{penggunaan}', [\App\Http\Controllers\PenggunaanController::class, 'update'])->name('update');
            Route::delete('/{penggunaan}', [\App\Http\Controllers\PenggunaanController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('peminjaman-kendaraan')->name('peminjaman.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PeminjamanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/pinjam', [\App\Http\Controllers\PeminjamanController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\PeminjamanController::class, 'store'])->name('store');
            Route::post('/{peminjaman}/kembalikan', [\App\Http\Controllers\PeminjamanController::class, 'kembalikan'])->name('kembalikan');
            Route::delete('/{peminjaman}', [\App\Http\Controllers\PeminjamanController::class, 'destroy'])->name('destroy');
        });
    });
    Route::get('/cetak-label-qr', [\App\Http\Controllers\QrLabelController::class, 'index'])->name('label-qr.index');
    Route::prefix('jadwal-maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MaintenanceController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::post('/', [\App\Http\Controllers\MaintenanceController::class, 'store'])->name('store');
            Route::post('/{maintenance}/selesai', [\App\Http\Controllers\MaintenanceController::class, 'selesai'])->name('selesai');
            Route::delete('/{maintenance}', [\App\Http\Controllers\MaintenanceController::class, 'destroy'])->name('destroy');
        });
    });

    // ===== Modul Pengadaan (terhubung ke sistem PROCURA lewat REST API) =====
    Route::prefix('pengajuan-pengadaan')->name('pengajuan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PengajuanPengadaanController::class, 'index'])->name('index');

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/buat', [\App\Http\Controllers\PengajuanPengadaanController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\PengajuanPengadaanController::class, 'store'])->middleware('throttle:20,1')->name('store');
        });

        Route::get('/{pengajuan}', [\App\Http\Controllers\PengajuanPengadaanController::class, 'show'])->whereNumber('pengajuan')->name('show');
        Route::post('/{pengajuan}/sinkron', [\App\Http\Controllers\PengajuanPengadaanController::class, 'sinkron'])->whereNumber('pengajuan')->middleware('throttle:30,1')->name('sinkron');

        Route::middleware('role:super_admin')->where(['pengajuan' => '[0-9]+'])->group(function () {
            Route::post('/{pengajuan}/kirim-ulang', [\App\Http\Controllers\PengajuanPengadaanController::class, 'kirimUlang'])->middleware('throttle:20,1')->name('kirim-ulang');
            Route::post('/{pengajuan}/konfirmasi-serah-terima', [\App\Http\Controllers\PengajuanPengadaanController::class, 'konfirmasi'])->name('konfirmasi');
            Route::post('/{pengajuan}/aset-dicatat', [\App\Http\Controllers\PengajuanPengadaanController::class, 'asetDicatat'])->name('aset-dicatat');
            Route::post('/{pengajuan}/catatan', [\App\Http\Controllers\PengajuanPengadaanController::class, 'catatan'])->middleware('throttle:20,1')->name('catatan');
            Route::delete('/{pengajuan}', [\App\Http\Controllers\PengajuanPengadaanController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('laporan-pengadaan')->name('laporan-pengadaan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LaporanPengadaanController::class, 'index'])->name('index');
        Route::post('/{laporan}/baca', [\App\Http\Controllers\LaporanPengadaanController::class, 'baca'])->whereNumber('laporan')->name('baca');
        Route::post('/baca-semua', [\App\Http\Controllers\LaporanPengadaanController::class, 'bacaSemua'])->name('baca-semua');
        Route::post('/catatan', [\App\Http\Controllers\LaporanPengadaanController::class, 'catatan'])->middleware(['role:super_admin', 'throttle:20,1'])->name('catatan');
    });

    // ===== Modul Admin (khusus super_admin) =====
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/log-aktivitas', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('/persetujuan-user', [ApprovalController::class, 'index'])->name('admin-approval.index');
        Route::post('/persetujuan-user/{user}/approve', [ApprovalController::class, 'approveUser'])->name('admin-approval.approve-user');
        Route::post('/persetujuan-user/{user}/reject', [ApprovalController::class, 'rejectUser'])->name('admin-approval.reject-user');
        Route::post('/persetujuan-user/{user}/approve-reset', [ApprovalController::class, 'approveReset'])->name('admin-approval.approve-reset');
        Route::post('/persetujuan-user/{user}/reject-reset', [ApprovalController::class, 'rejectReset'])->name('admin-approval.reject-reset');

        Route::prefix('manajemen-akun')->name('manage-accounts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AccountController::class, 'index'])->name('index');
            Route::put('/{user}/status', [\App\Http\Controllers\AccountController::class, 'updateStatus'])->name('update-status');
            Route::put('/{user}/role', [\App\Http\Controllers\AccountController::class, 'updateRole'])->name('update-role');
            Route::delete('/{user}', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('backup-data')->name('backup.')->group(function () {
            Route::get('/', [\App\Http\Controllers\BackupController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\BackupController::class, 'store'])->name('store');
            Route::get('/{filename}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('download');
            Route::delete('/{filename}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('destroy');
        });
    });
});
