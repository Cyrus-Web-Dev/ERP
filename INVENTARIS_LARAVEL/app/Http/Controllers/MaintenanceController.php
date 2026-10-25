<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Alat;
use App\Models\Bangunan;
use App\Models\Barang;
use App\Models\JadwalMaintenance;
use App\Models\Kendaraan;
use App\Models\Perabotan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaintenanceController extends Controller
{
    // Label tampilan per tipe, dipakai di controller & bisa dipakai view kalau perlu
    public const TIPE_LABEL = [
        'elektronik' => 'Barang Elektronik',
        'non_elektronik' => 'Barang Non Elektronik',
        'perabotan' => 'Perabotan',
        'kendaraan' => 'Kendaraan',
        'gedung' => 'Gedung',
    ];

    public function index()
    {
        $jadwal = JadwalMaintenance::orderByRaw("(status = 'Terjadwal') DESC")
            ->orderBy('tanggal_jadwal')
            ->get();

        $daftarPerTipe = [
            'elektronik' => Barang::orderBy('nama_barang')->get(['id', 'nama_barang'])
                ->map(fn ($b) => ['id' => $b->id, 'label' => $b->nama_barang]),
            'non_elektronik' => Alat::orderBy('nama')->get(['id', 'nama'])
                ->map(fn ($a) => ['id' => $a->id, 'label' => $a->nama]),
            'perabotan' => Perabotan::orderBy('nama')->get(['id', 'nama', 'kode'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => "{$p->nama} ({$p->kode})"]),
            'kendaraan' => Kendaraan::orderBy('merk')->get(['id_kendaraan', 'merk', 'plat_nomor'])
                ->map(fn ($k) => ['id' => $k->id_kendaraan, 'label' => "{$k->merk} - {$k->plat_nomor}"]),
            'gedung' => Bangunan::orderBy('nama_gedung')->get(['kode_gedung', 'nama_gedung'])
                ->map(fn ($g) => ['id' => $g->kode_gedung, 'label' => $g->nama_gedung]),
        ];

        return view('maintenance.index', [
            'jadwal' => $jadwal,
            'daftarPerTipe' => $daftarPerTipe,
            'tipeLabel' => self::TIPE_LABEL,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipe' => ['required', 'in:elektronik,non_elektronik,perabotan,kendaraan,gedung'],
            'item_id' => ['required', 'string'],
            'jenis_maintenance' => ['required', 'string', 'max:150'],
            'tanggal_jadwal' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        // Ambil nama item dari tabel aslinya (bukan dari input manual),
        // supaya tidak bisa dipalsukan dan tetap konsisten - sama seperti lama.
        $itemNama = match ($data['tipe']) {
            'elektronik' => Barang::find($data['item_id'])?->nama_barang,
            'non_elektronik' => Alat::find($data['item_id'])?->nama,
            'perabotan' => Perabotan::find($data['item_id'])?->nama,
            'kendaraan' => Kendaraan::find($data['item_id'])?->merk,
            'gedung' => Bangunan::where('kode_gedung', $data['item_id'])->first()?->nama_gedung,
        };

        if (! $itemNama) {
            throw ValidationException::withMessages(['item_id' => 'Item tidak ditemukan.']);
        }

        $jadwal = JadwalMaintenance::create([
            'tipe' => $data['tipe'],
            'item_id' => $data['item_id'],
            'item_nama' => $itemNama,
            'jenis_maintenance' => $data['jenis_maintenance'],
            'tanggal_jadwal' => $data['tanggal_jadwal'],
            'catatan' => $data['catatan'] ?? null,
            'status' => 'Terjadwal',
            'dibuat_oleh' => $request->user()->username,
        ]);

        ActivityLog::catat('maintenance', 'INSERT', 'Tambah Jadwal Maintenance',
            "Menjadwalkan {$jadwal->jenis_maintenance} untuk {$jadwal->item_nama}");

        return back()->with('success', 'Jadwal maintenance berhasil ditambahkan.');
    }

    public function selesai(JadwalMaintenance $maintenance)
    {
        $maintenance->update(['status' => 'Selesai', 'selesai_at' => now()]);

        ActivityLog::catat('maintenance', 'UPDATE', 'Selesaikan Maintenance',
            "Menandai selesai: {$maintenance->jenis_maintenance} - {$maintenance->item_nama}");

        return back()->with('success', 'Jadwal ditandai selesai.');
    }

    public function destroy(JadwalMaintenance $maintenance)
    {
        $info = "{$maintenance->jenis_maintenance} - {$maintenance->item_nama}";
        $maintenance->delete();

        ActivityLog::catat('maintenance', 'DELETE', 'Hapus Jadwal Maintenance', "Menghapus jadwal: {$info}");

        return back()->with('success', 'Jadwal dihapus.');
    }
}
