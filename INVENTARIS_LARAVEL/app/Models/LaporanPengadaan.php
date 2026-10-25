<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanPengadaan extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'laporan_pengadaan';

    protected $fillable = ['pengajuan_id', 'arah', 'jenis', 'judul', 'isi', 'kunci', 'delivery_id', 'dibaca_at', 'dibuat_oleh'];

    protected function casts(): array
    {
        return ['dibaca_at' => 'datetime'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPengadaan::class, 'pengajuan_id');
    }

    public static function belumDibaca(): int
    {
        return static::where('arah', 'masuk')->whereNull('dibaca_at')->count();
    }

    /**
     * Catat satu entri. Bila $kunci diberikan dan entri dengan kunci sama sudah ada dalam 10 menit terakhir
     * (mis. webhook + sinkron datang bersamaan), entri lama dikembalikan dan tidak dibuat ganda.
     */
    public static function catat(?PengajuanPengadaan $p, string $arah, string $jenis, string $judul, ?string $isi = null, ?string $kunci = null, ?string $deliveryId = null, ?int $userId = null): self
    {
        if ($kunci && $p) {
            $ada = static::where('pengajuan_id', $p->id)->where('kunci', $kunci)->where('created_at', '>=', now()->subMinutes(10))->first();
            if ($ada) {
                return $ada;
            }
        }

        return static::create([
            'pengajuan_id' => $p?->id, 'arah' => $arah, 'jenis' => $jenis, 'judul' => $judul, 'isi' => $isi, 'kunci' => $kunci,
            'delivery_id' => $deliveryId, 'dibuat_oleh' => $userId,
            // Entri keluar dari kita sendiri dianggap sudah dibaca.
            'dibaca_at' => $arah === 'keluar' ? now() : null,
        ]);
    }
}
