<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\Bangunan;
use App\Models\Barang;
use App\Models\Kendaraan;
use App\Models\Perabotan;
use Carbon\Carbon;

class DepresiasiController extends Controller
{
    /**
     * Masa manfaat (tahun) per kategori aset, dipakai untuk penyusutan
     * garis lurus (straight-line). Mengikuti kelompok penyusutan fiskal
     * yang umum dipakai di Indonesia (PMK 96/2009) sebagai acuan wajar:
     * kelompok 1 (elektronik/non-elektronik kecil) = 4 tahun,
     * kelompok 2 (perabotan/kendaraan) = 8 tahun, bangunan permanen = 20 tahun.
     */
    public const MASA_MANFAAT = [
        'Elektronik' => 4,
        'Non Elektronik' => 5,
        'Perabotan' => 8,
        'Kendaraan' => 8,
        'Gedung' => 20,
    ];

    public function index()
    {
        $items = collect();

        $items = $items->concat(Barang::all()->map(fn ($b) => $this->hitung('Elektronik', $b->nama_barang, $b->harga, $b->tgl)));
        $items = $items->concat(Alat::all()->map(fn ($a) => $this->hitung('Non Elektronik', $a->nama, $a->harga, $a->tanggal_beli)));
        $items = $items->concat(Perabotan::all()->map(fn ($p) => $this->hitung('Perabotan', $p->nama, $p->harga, $p->tgl_masuk)));
        $items = $items->concat(Kendaraan::all()->map(fn ($k) => $this->hitung('Kendaraan', $k->merk.' ('.$k->plat_nomor.')', $k->harga_kendaraan, $k->created_at)));
        $items = $items->concat(Bangunan::all()->map(fn ($g) => $this->hitung('Gedung', $g->nama_gedung, $g->harga_gedung, $g->tanggal_berdiri)));

        $items = $items->sortBy('sumber')->values();

        $ringkasan = [
            'totalPerolehan' => $items->sum('hargaPerolehan'),
            'totalBuku' => $items->sum('nilaiBuku'),
            'totalPenyusutan' => $items->sum('akumulasiPenyusutan'),
        ];

        return view('depresiasi.index', compact('items', 'ringkasan'));
    }

    private function hitung(string $sumber, string $nama, ?float $harga, $tanggalPerolehan): array
    {
        $masaManfaat = self::MASA_MANFAAT[$sumber];
        $harga = (float) ($harga ?? 0);

        if (! $tanggalPerolehan) {
            // Tidak ada tanggal perolehan tercatat -> anggap baru, belum menyusut.
            return [
                'sumber' => $sumber, 'nama' => $nama, 'tanggal' => null,
                'hargaPerolehan' => $harga, 'umurTahun' => 0, 'masaManfaat' => $masaManfaat,
                'penyusutanPerTahun' => round($harga / $masaManfaat), 'akumulasiPenyusutan' => 0,
                'nilaiBuku' => $harga, 'persenTersisa' => 100,
            ];
        }

        $tanggal = $tanggalPerolehan instanceof Carbon ? $tanggalPerolehan : Carbon::parse($tanggalPerolehan);
        $umurTahun = $tanggal->diffInDays(now()) / 365.25;

        $penyusutanPerTahun = $harga / $masaManfaat;
        $akumulasi = min($penyusutanPerTahun * $umurTahun, $harga);
        $nilaiBuku = max($harga - $akumulasi, 0);
        $persenTersisa = $harga > 0 ? round(($nilaiBuku / $harga) * 100) : 100;

        return [
            'sumber' => $sumber,
            'nama' => $nama,
            'tanggal' => $tanggal,
            'hargaPerolehan' => $harga,
            'umurTahun' => round($umurTahun, 1),
            'masaManfaat' => $masaManfaat,
            'penyusutanPerTahun' => round($penyusutanPerTahun),
            'akumulasiPenyusutan' => round($akumulasi),
            'nilaiBuku' => round($nilaiBuku),
            'persenTersisa' => $persenTersisa,
        ];
    }
}
