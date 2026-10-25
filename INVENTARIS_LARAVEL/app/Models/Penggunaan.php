<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penggunaan extends Model
{
    protected $table = 'penggunaan';

    protected $fillable = [
        'nama_karyawan',
        'foto_karyawan',
        'barang_id',
        'jumlah',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
