<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id_user';

    public $incrementing = true;

    protected $fillable = [
        'nama_lengkap',
        'username',
        'password',
        'jabatan',
        'foto_profil',
        'email',
        'no_telepon',
        'alamat',
        'tanggal_lahir',
        'riwayat_pendidikan',
        'status',
        'id_role',
        'level_akses',
        'approved',
        'email_verified',
        'reset_token',
        'reset_token_expiry',
        'reset_requested_at',
        'approved_by',
        'approved_at',
        'last_login_at',
        'registration_date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'reset_token',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'email_verified' => 'boolean',
            'reset_token_expiry' => 'datetime',
            'reset_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'tanggal_lahir' => 'date',
            'last_login_at' => 'datetime',
            'registration_date' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role', 'id_role');
    }

    public function isSuperAdmin(): bool
    {
        return $this->level_akses === 'super_admin';
    }

    public function approvedBy()
    {
        return $this->belongsTo(self::class, 'approved_by', 'id_user');
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_profil ? asset('storage/profil/'.$this->foto_profil) : null;
    }

    /**
     * Sama seperti isFirstUser() di process_register.php lama:
     * true kalau belum ada satu pun user approved di database.
     */
    public static function isFirstUser(): bool
    {
        return static::where('approved', 1)->count() === 0;
    }
}
