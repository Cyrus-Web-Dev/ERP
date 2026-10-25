<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bangunan extends Model
{
    protected $table = 'bangunan';

    protected $fillable = [
        'kode_gedung',
        'nama_gedung',
        'harga_gedung',
        'kondisi',
        'lokasi',
        'fungsi',
        'foto_gedung',
        'tanggal_berdiri',
        'foto_pengelola',
        'pengelola',
        'jabatan_pengelola',
    ];

    public function getRouteKeyName(): string
    {
        return 'kode_gedung';
    }

    protected function casts(): array
    {
        return [
            'harga_gedung' => 'decimal:2',
            'tanggal_berdiri' => 'date',
        ];
    }
}
