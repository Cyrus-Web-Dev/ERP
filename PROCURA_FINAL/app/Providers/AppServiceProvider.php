<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Services\GmailApiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GmailApiService::class);
    }

    public function boot(): void
    {
        // Daftarkan driver "gmail_api" (dipakai MAIL_MAILER=gmail_api).
        Mail::extend('gmail_api', fn () => new GmailApiTransport($this->app->make(GmailApiService::class)));

        if (! config('mail.mailers.gmail_api')) {
            config(['mail.mailers.gmail_api' => ['transport' => 'gmail_api']]);
        }

        // Batas permintaan REST API per token (atau per IP bila tanpa token).
        RateLimiter::for('procura-api', fn (Request $request) => Limit::perMinute((int) config('procura.api.rate_limit_per_minute', 120))
            ->by($request->bearerToken() ? hash('sha256', $request->bearerToken()) : $request->ip()));
    }
}
