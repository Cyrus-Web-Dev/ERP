<?php

use App\Http\Controllers\Api\ProcuraWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook dari PROCURA. Tanpa sesi/CSRF; keamanan lewat tanda tangan HMAC (lihat controller).
Route::post('/procura/webhook', ProcuraWebhookController::class)->middleware('throttle:120,1')->name('procura.webhook');
