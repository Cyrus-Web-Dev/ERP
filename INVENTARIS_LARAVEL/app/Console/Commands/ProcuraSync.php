<?php

namespace App\Console\Commands;

use App\Models\PengajuanPengadaan;
use App\Services\PengadaanSync;
use App\Services\ProcuraClient;
use Illuminate\Console\Command;

class ProcuraSync extends Command
{
    protected $signature = 'procura:sync {--semua : Sinkronkan semua pengajuan aktif, bukan hanya yang basi}';

    protected $description = 'Tarik status terbaru pengajuan aktif dari PROCURA (pelengkap webhook).';

    public function handle(PengadaanSync $sync, ProcuraClient $client): int
    {
        if (! $client->configured()) {
            $this->warn('PROCURA_URL / PROCURA_TOKEN belum diisi. Dilewati.');

            return self::SUCCESS;
        }

        $batas = now()->subMinutes(config('procura.sync_stale_minutes'));
        $daftar = PengajuanPengadaan::aktif()
            ->when(! $this->option('semua'), fn ($q) => $q->where(fn ($w) => $w->whereNull('disinkronkan_at')->orWhere('disinkronkan_at', '<', $batas)))
            ->get();

        $ok = 0;
        foreach ($daftar as $p) {
            $sync->sinkron($p) ? $ok++ : $this->warn("Gagal: {$p->nomor} — {$p->error_terakhir}");
        }
        $this->info("Sinkron selesai: {$ok}/{$daftar->count()} pengajuan.");

        return self::SUCCESS;
    }
}
