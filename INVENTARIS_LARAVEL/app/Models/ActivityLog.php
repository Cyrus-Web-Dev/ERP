<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'username',
        'email',
        'module',
        'action',
        'action_display',
        'description',
        'ip_address',
    ];

    /**
     * Helper singkat supaya pemanggilan mirip fungsi autoLog() di project lama:
     * ActivityLog::catat('barang', 'INSERT', 'Menambah barang baru', [...]);
     */
    public static function catat(string $module, string $action, string $actionDisplay, string $description): void
    {
        $user = auth()->user();

        static::create([
            'user_id' => $user?->id_user,
            'username' => $user?->username,
            'email' => $user?->email,
            'module' => $module,
            'action' => $action,
            'action_display' => $actionDisplay,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}
