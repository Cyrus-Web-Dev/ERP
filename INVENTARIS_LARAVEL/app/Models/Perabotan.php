<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perabotan extends Model
{
    protected $table = 'perabotan';

    protected $fillable = [
        'kode',
        'nama',
        'merk',
        'tgl_masuk',
        'foto',
        'stok',
        'harga',
        'lokasi',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'tgl_masuk' => 'date',
        ];
    }
}
