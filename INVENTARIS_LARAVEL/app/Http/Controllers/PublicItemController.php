<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\Bangunan;
use App\Models\Barang;
use App\Models\Kendaraan;
use App\Models\Perabotan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicItemController extends Controller
{
    // Whitelist ketat tipe yang boleh diakses publik lewat scan QR.
    private const TIPE_VALID = ['elektronik', 'gedung', 'non_elektronik', 'perabotan', 'kendaraan'];

    public function show(Request $request, string $type, string $id): Response
    {
        if (! in_array($type, self::TIPE_VALID, true)) {
            abort(404);
        }

        $item = match ($type) {
            'elektronik' => Barang::select(['nama_barang', 'kategori_barang', 'foto_barang', 'kode_aset'])->find($id),
            'non_elektronik' => Alat::select(['nama', 'jenis', 'foto'])->find($id),
            'perabotan' => Perabotan::select(['nama', 'merk', 'lokasi', 'status', 'foto'])->find($id),
            'kendaraan' => Kendaraan::select(['merk', 'plat_nomor', 'status', 'foto_kendaraan'])->find($id),
            'gedung' => Bangunan::select(['nama_gedung', 'kondisi', 'lokasi', 'fungsi', 'foto_gedung'])->where('kode_gedung', $id)->first(),
        };

        if (! $item) {
            abort(404, 'Item tidak ditemukan.');
        }

        // Folder foto berbeda-beda per modul, sama seperti struktur lama.
        $folderFoto = match ($type) {
            'elektronik' => 'elektronik',
            'non_elektronik' => 'dekorasi',
            'perabotan' => 'perabot',
            'kendaraan' => 'mobil',
            'gedung' => 'gedung',
        };

        $tampil = match ($type) {
            'elektronik' => [
                'judul' => $item->nama_barang,
                'subjudul' => $item->kategori_barang,
                'foto' => $item->foto_barang,
                'detail' => ['Kode Aset' => $item->kode_aset],
            ],
            'non_elektronik' => [
                'judul' => $item->nama,
                'subjudul' => $item->jenis,
                'foto' => $item->foto,
                'detail' => [],
            ],
            'perabotan' => [
                'judul' => $item->nama,
                'subjudul' => $item->merk,
                'foto' => $item->foto,
                'detail' => ['Lokasi' => $item->lokasi, 'Status' => $item->status],
            ],
            'kendaraan' => [
                'judul' => $item->merk,
                'subjudul' => $item->plat_nomor,
                'foto' => $item->foto_kendaraan,
                'detail' => ['Status' => ucfirst($item->status)],
            ],
            'gedung' => [
                'judul' => $item->nama_gedung,
                'subjudul' => $item->kondisi,
                'foto' => $item->foto_gedung,
                'detail' => ['Lokasi' => $item->lokasi, 'Fungsi' => $item->fungsi],
            ],
        };

        return response()->view('public.view-item', [
            'label' => match ($type) {
                'elektronik' => 'Barang Elektronik',
                'non_elektronik' => 'Barang Non Elektronik',
                'perabotan' => 'Perabotan',
                'kendaraan' => 'Kendaraan',
                'gedung' => 'Gedung',
            },
            'judul' => $tampil['judul'],
            'subjudul' => $tampil['subjudul'],
            'fotoUrl' => $tampil['foto'] ? asset('storage/'.$folderFoto.'/'.$tampil['foto']) : null,
            'detail' => array_filter($tampil['detail']),
        ]);
    }
}
