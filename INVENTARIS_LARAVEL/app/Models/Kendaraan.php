<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kendaraan extends Model
{
    protected $table = 'kendaraan';

    protected $primaryKey = 'id_kendaraan';

    protected $fillable = [
        'merk',
        'plat_nomor',
        'foto_kendaraan',
        'jumlah',
        'harga_kendaraan',
        'total_keseluruhan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'harga_kendaraan' => 'decimal:2',
            'total_keseluruhan' => 'decimal:2',
        ];
    }

    public function peminjaman()
    {
        return $this->hasMany(PeminjamanKendaraan::class, 'id_kendaraan', 'id_kendaraan');
    }
}
