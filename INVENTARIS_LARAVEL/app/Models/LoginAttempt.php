<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'login_attempts';

    protected $fillable = [
        'ip_address',
        'username',
        'waktu',
        'berhasil',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'berhasil' => 'boolean',
        ];
    }
}
