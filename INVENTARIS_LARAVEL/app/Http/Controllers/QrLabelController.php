<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\Bangunan;
use App\Models\Barang;
use App\Models\Kendaraan;
use App\Models\Perabotan;

class QrLabelController extends Controller
{
    public function index()
    {
        $items = collect();

        $items = $items->concat(
            Barang::orderBy('nama_barang')->get()->map(fn ($b) => [
                'type' => 'elektronik', 'id' => $b->id, 'nama' => $b->nama_barang, 'label' => 'Elektronik',
            ])
        );

        $items = $items->concat(
            Bangunan::orderBy('nama_gedung')->get()->map(fn ($g) => [
                'type' => 'gedung', 'id' => $g->kode_gedung, 'nama' => $g->nama_gedung, 'label' => 'Gedung',
            ])
        );

        $items = $items->concat(
            Alat::orderBy('nama')->get()->map(fn ($a) => [
                'type' => 'non_elektronik', 'id' => $a->id, 'nama' => $a->nama, 'label' => 'Non-Elektronik',
            ])
        );

        $items = $items->concat(
            Perabotan::orderBy('nama')->get()->map(fn ($p) => [
                'type' => 'perabotan', 'id' => $p->id, 'nama' => $p->nama, 'label' => 'Perabotan',
            ])
        );

        $items = $items->concat(
            Kendaraan::orderBy('merk')->get()->map(fn ($k) => [
                'type' => 'kendaraan', 'id' => $k->id_kendaraan, 'nama' => $k->merk, 'label' => 'Kendaraan',
            ])
        );

        return view('qr-label.index', ['items' => $items->values()]);
    }
}
