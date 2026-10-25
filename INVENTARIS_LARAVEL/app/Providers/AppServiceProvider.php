<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Daftarkan driver "gmailapi" supaya bisa dipakai lewat MAIL_MAILER=gmailapi di .env
        Mail::extend('gmailapi', fn () => new GmailApiTransport);
    }
}
