<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'barang';

    protected $fillable = [
        'nama_barang',
        'kategori_barang',
        'jumlah',
        'harga',
        'foto_barang',
        'tgl',
        'kode_aset',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'tgl' => 'date',
        ];
    }

    public function penggunaan()
    {
        return $this->hasMany(Penggunaan::class, 'barang_id');
    }
}
