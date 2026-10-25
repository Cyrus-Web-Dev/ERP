<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanPengadaanItem extends Model
{
    protected $table = 'pengajuan_pengadaan_item';

    protected $fillable = ['pengajuan_id', 'nama', 'spesifikasi', 'jumlah', 'satuan', 'harga_estimasi'];

    protected function casts(): array
    {
        return ['jumlah' => 'float', 'harga_estimasi' => 'float'];
    }
}
