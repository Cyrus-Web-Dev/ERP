<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanPengadaan extends Model
{
    protected $table = 'pengajuan_pengadaan';

    protected $fillable = [
        'nomor', 'procura_nomor', 'judul', 'kategori', 'prioritas', 'tanggal_dibutuhkan', 'alasan', 'nama_pemohon', 'unit_pemohon',
        'total_estimasi', 'status', 'stasiun', 'alasan_penolakan', 'error_terakhir',
        'serah_terima_nomor', 'serah_terima_status', 'serah_terima_tanggal', 'serah_terima_penerima', 'serah_terima_catatan',
        'dikonfirmasi_at', 'aset_dicatat_at', 'snapshot', 'terkirim_at', 'disinkronkan_at', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_dibutuhkan' => 'date',
            'serah_terima_tanggal' => 'date',
            'total_estimasi' => 'float',
            'snapshot' => 'array',
            'terkirim_at' => 'datetime',
            'disinkronkan_at' => 'datetime',
            'dikonfirmasi_at' => 'datetime',
            'aset_dicatat_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengajuanPengadaanItem::class, 'pengajuan_id');
    }

    public function laporan(): HasMany
    {
        return $this->hasMany(LaporanPengadaan::class, 'pengajuan_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh', 'id_user');
    }

    public function sudahTerkirim(): bool
    {
        return $this->procura_nomor !== null;
    }

    public function selesai(): bool
    {
        return in_array($this->status, ['completed', 'rejected', 'cancelled'], true);
    }

    /** Masih perlu dipantau (dipakai perintah sinkron terjadwal). */
    public function scopeAktif($q)
    {
        return $q->whereNotNull('procura_nomor')->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
    }

    public function statusMeta(): array
    {
        return config("procura.status.{$this->status}", [$this->status, 'bg-slate-200 text-slate-700', '']);
    }

    /** Stasiun jalur (diturunkan dari status bila API belum memberi). */
    public function stasiunKey(): string
    {
        if ($this->stasiun) {
            return $this->stasiun;
        }

        return match ($this->status) {
            'reviewing' => 'reviewing', 'sourcing' => 'sourcing', 'funding', 'funded' => 'funding', 'ordered' => 'ordered',
            'received' => 'received', 'handed_over', 'completed' => 'handed_over', default => 'submitted',
        };
    }

    /** Item yang benar-benar diterima (dari PO di snapshot), lengkap dengan harga perolehan termasuk PPN. */
    public function itemDiterima(): array
    {
        $rows = [];
        foreach ($this->snapshot['purchase_orders'] ?? [] as $po) {
            if (($po['status'] ?? null) === 'cancelled') {
                continue;
            }
            $ppn = 1 + ((float) ($po['tax_percent'] ?? 0)) / 100;
            foreach ($po['items'] ?? [] as $i) {
                $qty = (float) ($i['received_quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $rows[] = [
                    'nama' => preg_replace('/\s+—\s+.*/u', '', (string) $i['description']),
                    'uraian' => $i['description'], 'jumlah' => $qty, 'satuan' => $i['unit'] ?? 'unit',
                    'harga' => round((float) $i['unit_price'] * $ppn, 2), 'po' => $po['number'] ?? null, 'vendor' => $po['vendor'] ?? null,
                ];
            }
        }

        return $rows;
    }
}
