<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalMaintenance extends Model
{
    const UPDATED_AT = null;

    protected $table = 'jadwal_maintenance';

    protected $fillable = [
        'tipe',
        'item_id',
        'item_nama',
        'jenis_maintenance',
        'tanggal_jadwal',
        'catatan',
        'status',
        'dibuat_oleh',
        'selesai_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_jadwal' => 'date',
            'selesai_at' => 'datetime',
        ];
    }
}
