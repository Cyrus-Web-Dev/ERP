<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Kendaraan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KendaraanController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = Kendaraan::query()->withCount([
            'peminjaman as sedang_dipinjam' => fn ($q) => $q->where('status', 'Dipinjam'),
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('merk', 'like', "%{$search}%")
                    ->orWhere('plat_nomor', 'like', "%{$search}%");
            });
        }

        $kendaraan = $query->orderByDesc('id_kendaraan')->paginate(10)->withQueryString();

        return view('kendaraan.index', [
            'kendaraan' => $kendaraan,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('kendaraan.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'merk' => ['required', 'string', 'max:100'],
            'plat_nomor' => ['required', 'string', 'min:4', 'max:20', 'unique:kendaraan,plat_nomor'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga_kendaraan' => ['required', 'numeric', 'min:0'],
            'foto_kendaraan' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data['plat_nomor'] = strtoupper($data['plat_nomor']);
        // Dihitung di server (bukan dipercaya dari input client) supaya tidak bisa dimanipulasi.
        $data['total_keseluruhan'] = $data['jumlah'] * $data['harga_kendaraan'];
        $data['status'] = 'tersedia';
        $data['foto_kendaraan'] = basename($request->file('foto_kendaraan')->store('mobil', 'public'));

        $kendaraan = Kendaraan::create($data);

        ActivityLog::catat('kendaraan', 'INSERT', 'Tambah Kendaraan', "Menambah kendaraan \"{$kendaraan->merk}\" ({$kendaraan->plat_nomor})");

        return redirect()->route('kendaraan.index')->with('success', 'Data kendaraan berhasil ditambahkan!');
    }

    public function edit(Kendaraan $kendaraan)
    {
        return view('kendaraan.edit', ['kendaraan' => $kendaraan]);
    }

    public function update(Request $request, Kendaraan $kendaraan)
    {
        $data = $request->validate([
            'merk' => ['required', 'string', 'max:100'],
            'plat_nomor' => ['required', 'string', 'min:4', 'max:20', 'unique:kendaraan,plat_nomor,'.$kendaraan->id_kendaraan.',id_kendaraan'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga_kendaraan' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:tersedia,perbaikan,nonaktif'],
            'foto_kendaraan' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data['plat_nomor'] = strtoupper($data['plat_nomor']);
        $data['total_keseluruhan'] = $data['jumlah'] * $data['harga_kendaraan'];

        if ($request->hasFile('foto_kendaraan')) {
            if ($kendaraan->foto_kendaraan) {
                Storage::disk('public')->delete('mobil/'.$kendaraan->foto_kendaraan);
            }
            $data['foto_kendaraan'] = basename($request->file('foto_kendaraan')->store('mobil', 'public'));
        }

        $merkLama = $kendaraan->merk;
        $kendaraan->update($data);

        ActivityLog::catat('kendaraan', 'UPDATE', 'Ubah Kendaraan', "Mengubah kendaraan \"{$merkLama}\" menjadi \"{$kendaraan->merk}\" ({$kendaraan->plat_nomor})");

        return redirect()->route('kendaraan.index')->with('success', 'Data kendaraan berhasil diperbarui!');
    }

    public function destroy(Kendaraan $kendaraan)
    {
        $sedangDipinjam = $kendaraan->peminjaman()->where('status', 'Dipinjam')->count();

        if ($sedangDipinjam > 0) {
            return redirect()->route('kendaraan.index')
                ->with('error', "Kendaraan sedang dipinjam oleh {$sedangDipinjam} orang, tidak bisa dihapus!");
        }

        if ($kendaraan->foto_kendaraan) {
            Storage::disk('public')->delete('mobil/'.$kendaraan->foto_kendaraan);
        }

        $merk = $kendaraan->merk;
        $plat = $kendaraan->plat_nomor;
        $kendaraan->delete();

        ActivityLog::catat('kendaraan', 'DELETE', 'Hapus Kendaraan', "Menghapus kendaraan \"{$merk}\" ({$plat})");

        return redirect()->route('kendaraan.index')->with('success', 'Data kendaraan berhasil dihapus!');
    }
}
