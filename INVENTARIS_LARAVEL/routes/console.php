<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pelengkap webhook: tarik status pengajuan aktif tiap 10 menit (butuh `php artisan schedule:work` atau cron `schedule:run`).
\Illuminate\Support\Facades\Schedule::command('procura:sync')->everyTenMinutes()->withoutOverlapping();
