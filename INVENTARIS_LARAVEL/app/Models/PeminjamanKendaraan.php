<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeminjamanKendaraan extends Model
{
    protected $table = 'peminjaman_kendaraan';

    protected $primaryKey = 'id_peminjaman';

    protected $fillable = [
        'id_kendaraan',
        'peminjam',
        'nik',
        'tujuan',
        'tanggal_pinjam',
        'tanggal_kembali',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pinjam' => 'date',
            'tanggal_kembali' => 'date',
        ];
    }

    public function kendaraan()
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan', 'id_kendaraan');
    }
}
