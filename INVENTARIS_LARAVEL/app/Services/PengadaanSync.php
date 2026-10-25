<?php

namespace App\Services;

use App\Exceptions\ProcuraException;
use App\Models\LaporanPengadaan;
use App\Models\PengajuanPengadaan;
use Illuminate\Support\Carbon;

/** Menjaga data lokal pengajuan tetap selaras dengan PROCURA (kirim, tarik status, terima kabar webhook). */
class PengadaanSync
{
    public function __construct(private ProcuraClient $client)
    {
    }

    /**
     * Kirim pengajuan ke PROCURA. Aman diulang: nomor lokal dipakai sebagai Idempotency-Key + external_ref,
     * jadi pengiriman ulang setelah timeout tidak membuat permintaan ganda di Pengadaan.
     *
     * @throws ProcuraException
     */
    public function kirim(PengajuanPengadaan $p): void
    {
        $p->loadMissing('items');
        $payload = [
            'title' => $p->judul,
            'category' => $p->kategori,
            'priority' => $p->prioritas,
            'requester_name' => $p->nama_pemohon,
            'requester_unit' => $p->unit_pemohon,
            'external_ref' => $p->nomor,
            'needed_date' => $p->tanggal_dibutuhkan?->toDateString(),
            'justification' => $p->alasan,
            'items' => $p->items->map(fn ($i) => [
                'name' => $i->nama, 'specification' => $i->spesifikasi, 'quantity' => $i->jumlah, 'unit' => $i->satuan, 'estimated_price' => $i->harga_estimasi,
            ])->all(),
        ];

        try {
            $remote = $this->client->submit($payload, $p->nomor);
        } catch (ProcuraException $e) {
            if ($e->status === 409 && ! empty($e->details['number'])) {
                $remote = $this->client->find($e->details['number']);   // sudah pernah masuk: pakai yang ada
            } else {
                $p->update(['error_terakhir' => $e->getMessage()]);
                throw $e;
            }
        }

        $p->fill(['terkirim_at' => $p->terkirim_at ?? now()])->save();
        $this->terapkan($p, $remote, false);
        LaporanPengadaan::catat($p, 'keluar', 'pengiriman', 'Pengajuan dikirim ke Pengadaan', "Nomor di Pengadaan: {$p->procura_nomor}", 'kirim', null, auth()->user()?->id_user);
    }

    /** Tarik status terbaru. Mengembalikan false bila gagal (pesan tersimpan di error_terakhir). */
    public function sinkron(PengajuanPengadaan $p): bool
    {
        if (! $p->procura_nomor) {
            return false;
        }

        try {
            $this->terapkan($p, $this->client->find($p->procura_nomor));

            return true;
        } catch (ProcuraException $e) {
            $p->update(['error_terakhir' => $e->getMessage()]);

            return false;
        }
    }

    /** Terapkan data lengkap dari GET /requests/{no}. */
    public function terapkan(PengajuanPengadaan $p, array $remote, bool $catat = true): void
    {
        $statusLama = $p->status;
        $ho = collect($remote['handovers'] ?? [])->last();

        $p->fill([
            'procura_nomor' => $remote['number'],
            'status' => $remote['status'],
            'stasiun' => $remote['station'] ?? null,
            'alasan_penolakan' => $remote['rejection_reason'] ?? null,
            'snapshot' => $remote,
            'disinkronkan_at' => now(),
            'error_terakhir' => null,
        ]);

        if ($ho) {
            $p->fill([
                'serah_terima_nomor' => $ho['number'],
                'serah_terima_status' => $ho['status'],
                'serah_terima_tanggal' => $ho['handover_date'] ?? null,
                'serah_terima_penerima' => $ho['receiver_name'] ?? null,
                'serah_terima_catatan' => $ho['notes'] ?? null,
            ]);
            if ($ho['status'] === 'confirmed' && ! $p->dikonfirmasi_at) {
                $p->dikonfirmasi_at = ! empty($ho['confirmed_at']) ? Carbon::parse($ho['confirmed_at']) : now();
            }
        }
        $p->save();

        if ($catat && $statusLama !== $p->status) {
            $this->catatStatus($p);
        }
    }

    /** Terapkan kabar ringkas dari webhook (tanpa memanggil balik PROCURA). Snapshot ditandai basi agar ditarik saat halaman dibuka. */
    public function terapkanKabar(PengajuanPengadaan $p, ?string $status, ?string $station = null, ?string $alasan = null): void
    {
        $lama = $p->status;
        $p->fill(array_filter([
            'status' => $status, 'stasiun' => $station, 'alasan_penolakan' => $alasan,
        ], fn ($v) => $v !== null));
        $p->disinkronkan_at = null;
        $p->save();

        if ($status && $lama !== $status) {
            $this->catatStatus($p);
        }
    }

    public function cari(?string $procuraNomor, ?string $externalRef): ?PengajuanPengadaan
    {
        $p = $procuraNomor ? PengajuanPengadaan::where('procura_nomor', $procuraNomor)->first() : null;

        return $p ?? ($externalRef ? PengajuanPengadaan::where('nomor', $externalRef)->first() : null);
    }

    private function catatStatus(PengajuanPengadaan $p): void
    {
        [$label, , $pesan] = $p->statusMeta();
        $isi = in_array($p->status, ['rejected', 'cancelled'], true) && $p->alasan_penolakan ? 'Alasan: '.$p->alasan_penolakan : null;

        LaporanPengadaan::catat($p, 'masuk', 'status', $pesan ?: $label, $isi, 'status:'.$p->status);
    }
}
