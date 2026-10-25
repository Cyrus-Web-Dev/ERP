<?php

use App\Http\Controllers\Api\FundingApiController;
use App\Http\Controllers\Api\RequestApiController;
use Illuminate\Support\Facades\Route;

/*
| REST API PROCURA v1 — dipakai sistem lain (Inventaris, Keuangan, dst).
| Autentikasi: Authorization: Bearer <token klien API>  (dibuat di menu Integrasi).
| Semua respons JSON: {"data": ...} atau {"error": {"code","message","details"}}.
*/
Route::prefix('v1')->middleware(['throttle:procura-api'])->group(function () {
    Route::middleware('api.client')->get('/ping', [RequestApiController::class, 'ping']);

    Route::middleware('api.client:catalog:read')->get('/catalog', [RequestApiController::class, 'catalog']);

    // Sisi Inventaris
    Route::middleware('api.client:requests:write')->group(function () {
        Route::post('/requests', [RequestApiController::class, 'store']);
        Route::post('/requests/{number}/notes', [RequestApiController::class, 'note']);
    });
    Route::middleware('api.client:requests:read')->group(function () {
        Route::get('/requests', [RequestApiController::class, 'index']);
        Route::get('/requests/{number}', [RequestApiController::class, 'show']);
    });
    Route::middleware('api.client:handover:confirm')->post('/handovers/{number}/confirm', [FundingApiController::class, 'confirmHandover']);

    // Sisi Keuangan
    Route::middleware('api.client:funding:read')->group(function () {
        Route::get('/funding', [FundingApiController::class, 'index']);
        Route::get('/funding/{number}', [FundingApiController::class, 'show']);
    });
    Route::middleware('api.client:funding:decide')->group(function () {
        Route::post('/funding/{number}/decision', [FundingApiController::class, 'decision']);
        Route::post('/funding/{number}/disbursement', [FundingApiController::class, 'disbursement']);
        Route::post('/funding/{number}/notes', [FundingApiController::class, 'note']);
    });

    Route::fallback(fn () => response()->json(['error' => ['code' => 'not_found', 'message' => 'Endpoint tidak ditemukan.']], 404));
});
