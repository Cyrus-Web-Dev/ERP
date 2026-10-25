<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\Bangunan;
use App\Models\Barang;
use App\Models\Kendaraan;
use App\Models\Perabotan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    public const SUMBER = ['Elektronik', 'Non Elektronik', 'Perabotan', 'Kendaraan', 'Gedung'];

    public function index(Request $request)
    {
        $data = $this->kumpulkanData($request);

        $filter = array_merge(
            ['search' => '', 'sumber' => '', 'tgl_dari' => '', 'tgl_sampai' => ''],
            $request->only(['search', 'sumber', 'tgl_dari', 'tgl_sampai'])
        );

        return view('laporan.index', $data + [
            'filter' => $filter,
            'sumberList' => self::SUMBER,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->kumpulkanData($request);

        $pdf = Pdf::loadView('laporan.pdf', $data + [
            'dicetakOleh' => auth()->user()->nama_lengkap,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Laporan_Inventaris_'.now()->format('Y-m-d').'.pdf');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->kumpulkanData($request);

        $filename = 'Laporan_Inventaris_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sumber', 'Nama', 'Kategori/Merk', 'Jumlah', 'Harga', 'Total', 'Tanggal']);

            foreach ($data['gabungan'] as $row) {
                fputcsv($out, [
                    $row['sumber'],
                    $row['nama'],
                    $row['kategori'],
                    $row['jumlah'],
                    $row['harga'],
                    $row['total'],
                    $row['tanggal']?->format('Y-m-d') ?? '-',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Setara ambilSemuaData() + filterLaporan() di laporan.php lama:
     * gabungkan data dari 5 modul jadi satu format seragam, lalu filter
     * berdasarkan pencarian, sumber/kategori, dan rentang tanggal.
     */
    private function kumpulkanData(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $sumber = $request->query('sumber', '');
        $tglDari = $request->query('tgl_dari', '');
        $tglSampai = $request->query('tgl_sampai', '');

        $elektronik = Barang::query()
            ->when($search, fn ($q) => $q->where('nama_barang', 'like', "%{$search}%"))
            ->when($tglDari, fn ($q) => $q->whereDate('tgl', '>=', $tglDari))
            ->when($tglSampai, fn ($q) => $q->whereDate('tgl', '<=', $tglSampai))
            ->get()
            ->map(fn ($b) => [
                'sumber' => 'Elektronik', 'nama' => $b->nama_barang, 'kategori' => $b->kategori_barang,
                'jumlah' => $b->jumlah, 'harga' => $b->harga, 'total' => $b->harga * $b->jumlah, 'tanggal' => $b->tgl,
            ]);

        $nonElektronik = Alat::query()
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->when($tglDari, fn ($q) => $q->whereDate('tanggal_beli', '>=', $tglDari))
            ->when($tglSampai, fn ($q) => $q->whereDate('tanggal_beli', '<=', $tglSampai))
            ->get()
            ->map(fn ($a) => [
                'sumber' => 'Non Elektronik', 'nama' => $a->nama, 'kategori' => $a->jenis,
                'jumlah' => $a->jumlah, 'harga' => $a->harga, 'total' => $a->harga * $a->jumlah, 'tanggal' => $a->tanggal_beli,
            ]);

        $perabotan = Perabotan::query()
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->when($tglDari, fn ($q) => $q->whereDate('tgl_masuk', '>=', $tglDari))
            ->when($tglSampai, fn ($q) => $q->whereDate('tgl_masuk', '<=', $tglSampai))
            ->get()
            ->map(fn ($p) => [
                'sumber' => 'Perabotan', 'nama' => $p->nama, 'kategori' => $p->merk ?: '-',
                'jumlah' => $p->stok, 'harga' => $p->harga, 'total' => $p->harga * $p->stok, 'tanggal' => $p->tgl_masuk,
            ]);

        $kendaraan = Kendaraan::query()
            ->when($search, fn ($q) => $q->where('merk', 'like', "%{$search}%"))
            ->get()
            ->map(fn ($k) => [
                'sumber' => 'Kendaraan', 'nama' => $k->merk, 'kategori' => $k->plat_nomor,
                'jumlah' => $k->jumlah, 'harga' => $k->harga_kendaraan, 'total' => $k->total_keseluruhan, 'tanggal' => $k->created_at,
            ]);

        $gedung = Bangunan::query()
            ->when($search, fn ($q) => $q->where('nama_gedung', 'like', "%{$search}%"))
            ->when($tglDari, fn ($q) => $q->whereDate('tanggal_berdiri', '>=', $tglDari))
            ->when($tglSampai, fn ($q) => $q->whereDate('tanggal_berdiri', '<=', $tglSampai))
            ->get()
            ->map(fn ($g) => [
                'sumber' => 'Gedung', 'nama' => $g->nama_gedung, 'kategori' => $g->kondisi,
                'jumlah' => 1, 'harga' => $g->harga_gedung, 'total' => $g->harga_gedung, 'tanggal' => $g->tanggal_berdiri,
            ]);

        $perModul = [
            'Elektronik' => $elektronik, 'Non Elektronik' => $nonElektronik, 'Perabotan' => $perabotan,
            'Kendaraan' => $kendaraan, 'Gedung' => $gedung,
        ];

        $gabungan = collect();
        foreach ($perModul as $namaSumber => $rows) {
            if ($sumber === '' || $sumber === $namaSumber) {
                $gabungan = $gabungan->concat($rows);
            }
        }

        return [
            'gabungan' => $gabungan->values(),
            'totalKeseluruhan' => $gabungan->sum('total'),
            'totalPerSumber' => collect($perModul)->map(fn ($rows) => $rows->sum('total')),
            'jumlahPerSumber' => collect($perModul)->map(fn ($rows) => $rows->count()),
        ];
    }
}
