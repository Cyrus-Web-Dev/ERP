<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceDeskController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InventoryDeskController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'has.role'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');

    // ---- Permintaan (dari Inventaris) -------------------------------------------------
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [RequestController::class, 'create'])->middleware('permission:request.create|request.manage')->name('requests.create');
    Route::post('/requests', [RequestController::class, 'store'])->middleware('permission:request.create|request.manage')->name('requests.store');
    Route::get('/requests/{purchaseRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::middleware('permission:request.manage')->group(function () {
        Route::post('/requests/{purchaseRequest}/review', [RequestController::class, 'review'])->name('requests.review');
        Route::post('/requests/{purchaseRequest}/reject', [RequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{purchaseRequest}/cancel', [RequestController::class, 'cancel'])->name('requests.cancel');
    });
    Route::middleware('permission:quotation.manage')->group(function () {
        Route::post('/requests/{purchaseRequest}/quotations', [RequestController::class, 'storeQuotation'])->name('requests.quotations.store');
        Route::delete('/requests/{purchaseRequest}/quotations/{quotation}', [RequestController::class, 'destroyQuotation'])->name('requests.quotations.destroy');
        Route::post('/requests/{purchaseRequest}/quotations/{quotation}/select', [RequestController::class, 'selectQuotation'])->name('requests.quotations.select');
    });
    Route::post('/requests/{purchaseRequest}/handover', [RequestController::class, 'handover'])->middleware('permission:handover.manage')->name('requests.handover');
    Route::post('/requests/{purchaseRequest}/funding', [FinanceDeskController::class, 'submit'])->middleware('permission:funding.submit')->name('requests.funding.store');
    Route::post('/requests/{purchaseRequest}/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:po.manage')->name('requests.po.store');

    // ---- Meja Inventaris ----------------------------------------------------------------
    Route::get('/meja-inventaris', [InventoryDeskController::class, 'index'])->name('inventory-desk.index');
    Route::post('/meja-inventaris/laporan', [InventoryDeskController::class, 'store'])->middleware('permission:request.manage|request.create')->name('inventory-desk.store');
    Route::post('/meja-inventaris/laporan/baca-semua', [InventoryDeskController::class, 'readAll'])->name('inventory-desk.read-all');
    Route::post('/meja-inventaris/laporan/{report}/baca', [InventoryDeskController::class, 'read'])->name('inventory-desk.read');
    Route::post('/meja-inventaris/serah-terima/{handover}/konfirmasi', [InventoryDeskController::class, 'confirmHandover'])->middleware('permission:handover.confirm')->name('inventory-desk.confirm');

    // ---- Meja Keuangan --------------------------------------------------------------------
    Route::get('/meja-keuangan', [FinanceDeskController::class, 'index'])->name('finance-desk.index');
    Route::get('/meja-keuangan/dana/{funding}', [FinanceDeskController::class, 'show'])->name('finance-desk.show');
    Route::post('/meja-keuangan/dana/{funding}/setujui-internal', [FinanceDeskController::class, 'approveInternal'])->middleware('permission:funding.approve')->name('finance-desk.approve-internal');
    Route::post('/meja-keuangan/dana/{funding}/ajukan-ulang', [FinanceDeskController::class, 'resubmit'])->middleware('permission:funding.submit')->name('finance-desk.resubmit');
    Route::post('/meja-keuangan/dana/{funding}/keputusan', [FinanceDeskController::class, 'decide'])->middleware('permission:finance.decide')->name('finance-desk.decide');
    Route::post('/meja-keuangan/dana/{funding}/pencairan', [FinanceDeskController::class, 'disburse'])->middleware('permission:finance.disburse')->name('finance-desk.disburse');
    Route::post('/meja-keuangan/dana/{funding}/lpj', [FinanceDeskController::class, 'settle'])->middleware('permission:payment.manage')->name('finance-desk.settle');
    Route::post('/meja-keuangan/laporan', [FinanceDeskController::class, 'storeReport'])->middleware('permission:funding.submit|finance.decide')->name('finance-desk.report.store');
    Route::post('/meja-keuangan/laporan/{report}/baca', [FinanceDeskController::class, 'readReport'])->name('finance-desk.report.read');

    // ---- Purchase Order, penerimaan, pembayaran ---------------------------------------
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])->name('purchase-orders.pdf');
    Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware(['permission:po.manage', 'throttle:20,1'])->name('purchase-orders.send');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:po.manage')->name('purchase-orders.cancel');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:receiving.manage')->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/pay', [PurchaseOrderController::class, 'pay'])->middleware('permission:payment.manage')->name('purchase-orders.pay');

    // ---- Data master ----------------------------------------------------------------------
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->middleware('permission:vendor.manage')->name('vendors.store');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->middleware('permission:vendor.manage')->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->middleware('permission:vendor.manage')->name('vendors.destroy');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog', [CatalogController::class, 'store'])->middleware('permission:catalog.manage')->name('catalog.store');
    Route::put('/catalog/{catalogItem}', [CatalogController::class, 'update'])->middleware('permission:catalog.manage')->name('catalog.update');
    Route::delete('/catalog/{catalogItem}', [CatalogController::class, 'destroy'])->middleware('permission:catalog.manage')->name('catalog.destroy');

    Route::get('/analitik', [AnalyticsController::class, 'index'])->name('analytics.index');

    // ---- Lampiran & profil ------------------------------------------------------------------
    Route::post('/attachments', [AttachmentController::class, 'store'])->middleware('throttle:30,1')->name('attachments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::get('/profile/photo/{user}', [ProfileController::class, 'photo'])->name('profile.photo');

    // ---- Khusus Manager ---------------------------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::post('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
            Route::post('/users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
            Route::put('/users/{user}/access', [UserManagementController::class, 'updateAccess'])->name('users.access');
            Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
            Route::post('/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
            Route::post('/users/{user}/message', [UserManagementController::class, 'message'])->middleware('throttle:10,1')->name('users.message');

            Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
            Route::post('/backup', [BackupController::class, 'store'])->middleware('throttle:6,1')->name('backup.store');
            Route::get('/backup/{filename}/download', [BackupController::class, 'download'])->name('backup.download');
            Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy');
        });

        Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->name('settings.activity-log.index');

        Route::get('/integrasi', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::post('/integrasi', [IntegrationController::class, 'store'])->name('integrations.store');
        Route::put('/integrasi/{client}', [IntegrationController::class, 'update'])->name('integrations.update');
        Route::post('/integrasi/{client}/token', [IntegrationController::class, 'regenerate'])->name('integrations.regenerate');
        Route::post('/integrasi/{client}/ping', [IntegrationController::class, 'test'])->name('integrations.test');
        Route::delete('/integrasi/{client}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
        Route::post('/integrasi/pengiriman/{delivery}/kirim-ulang', [IntegrationController::class, 'resend'])->name('integrations.resend');
    });
});

require __DIR__.'/auth.php';
