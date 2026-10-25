<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alat extends Model
{
    protected $table = 'alat';

    protected $fillable = [
        'nama',
        'jenis',
        'tanggal_beli',
        'harga',
        'jumlah',
        'foto',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'tanggal_beli' => 'date',
        ];
    }
}
