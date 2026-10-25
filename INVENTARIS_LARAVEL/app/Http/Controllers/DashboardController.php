<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\Bangunan;
use App\Models\Barang;
use App\Models\Kendaraan;
use App\Models\Perabotan;

class DashboardController extends Controller
{
    // Ambang batas stok dianggap "rendah" dan perlu perhatian di dashboard.
    private const AMBANG_STOK_RENDAH = 2;

    public function index()
    {
        $nilaiElektronik = (float) Barang::sum('harga');
        $nilaiNonElektronik = (float) Alat::sum('harga');
        $nilaiPerabotan = (float) Perabotan::sum('harga');
        $nilaiKendaraan = (float) Kendaraan::sum('total_keseluruhan');
        $nilaiGedung = (float) Bangunan::sum('harga_gedung');

        $stats = [
            'elektronik' => Barang::count(),
            'non_elektronik' => Alat::count(),
            'perabotan' => Perabotan::count(),
            'kendaraan' => Kendaraan::count(),
            'gedung' => Bangunan::count(),
            'nilai_elektronik' => $nilaiElektronik,
            'nilai_non_elektronik' => $nilaiNonElektronik,
            'nilai_perabotan' => $nilaiPerabotan,
        ];

        // Data untuk chart distribusi nilai aset (donut) & jumlah item per modul (bar)
        $chartNilai = [
            'labels' => ['Elektronik', 'Non Elektronik', 'Perabotan', 'Kendaraan', 'Gedung'],
            'data' => [$nilaiElektronik, $nilaiNonElektronik, $nilaiPerabotan, $nilaiKendaraan, $nilaiGedung],
        ];

        $chartJumlah = [
            'labels' => ['Elektronik', 'Non Elektronik', 'Perabotan', 'Kendaraan', 'Gedung'],
            'data' => [$stats['elektronik'], $stats['non_elektronik'], $stats['perabotan'], $stats['kendaraan'], $stats['gedung']],
        ];

        $perluPerhatian = $this->kumpulkanPerluPerhatian();

        return view('dashboard', compact('stats', 'chartNilai', 'chartJumlah', 'perluPerhatian'));
    }

    /**
     * Kumpulkan item yang butuh perhatian: stok/jumlah menipis, kondisi rusak,
     * status tidak layak/perbaikan - supaya kelihatan di dashboard tanpa
     * harus buka satu-satu tiap modul.
     */
    private function kumpulkanPerluPerhatian()
    {
        $items = collect();

        Barang::where('jumlah', '<=', self::AMBANG_STOK_RENDAH)->get()->each(function ($b) use ($items) {
            $items->push(['sumber' => 'Elektronik', 'nama' => $b->nama_barang, 'catatan' => "Stok tersisa {$b->jumlah}", 'level' => $b->jumlah === 0 ? 'red' : 'amber']);
        });

        Alat::where('jumlah', '<=', self::AMBANG_STOK_RENDAH)->get()->each(function ($a) use ($items) {
            $items->push(['sumber' => 'Non Elektronik', 'nama' => $a->nama, 'catatan' => "Stok tersisa {$a->jumlah}", 'level' => $a->jumlah === 0 ? 'red' : 'amber']);
        });

        Perabotan::where('stok', '<=', self::AMBANG_STOK_RENDAH)->orWhere('status', 'Tidak Layak')->get()->each(function ($p) use ($items) {
            $catatan = $p->status === 'Tidak Layak' ? 'Kondisi: Tidak Layak' : "Stok tersisa {$p->stok}";
            $items->push(['sumber' => 'Perabotan', 'nama' => $p->nama, 'catatan' => $catatan, 'level' => $p->status === 'Tidak Layak' ? 'red' : 'amber']);
        });

        Bangunan::where('kondisi', '!=', 'Baik')->get()->each(function ($g) use ($items) {
            $items->push(['sumber' => 'Gedung', 'nama' => $g->nama_gedung, 'catatan' => "Kondisi: {$g->kondisi}", 'level' => $g->kondisi === 'Rusak Berat' ? 'red' : 'amber']);
        });

        Kendaraan::where('status', '!=', 'tersedia')->get()->each(function ($k) use ($items) {
            $items->push(['sumber' => 'Kendaraan', 'nama' => "{$k->merk} ({$k->plat_nomor})", 'catatan' => 'Status: '.ucfirst($k->status), 'level' => $k->status === 'nonaktif' ? 'red' : 'amber']);
        });

        return $items->sortBy(fn ($i) => $i['level'] === 'red' ? 0 : 1)->take(10)->values();
    }
}
