<?php

namespace App\Support;

use App\Models\User;

class Registration
{
    /**
     * Pendaftaran terbuka kecuali PROCURA_REGISTRATION=closed di .env. Selalu boleh bila belum ada satu pun user
     * (pendaftar pertama otomatis menjadi Manager). Akun baru berikutnya menunggu persetujuan Manager.
     */
    public static function allowed(): bool
    {
        return config('procura.registration') !== 'closed' || ! User::query()->exists();
    }
}
