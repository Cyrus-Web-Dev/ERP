<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Barang;
use App\Models\Penggunaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PenggunaanController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = Penggunaan::with('barang');

        if ($search !== '') {
            $query->where('nama_karyawan', 'like', "%{$search}%")
                ->orWhereHas('barang', fn ($q) => $q->where('nama_barang', 'like', "%{$search}%"));
        }

        $penggunaan = $query->orderByDesc('id')->paginate(10)->withQueryString();

        return view('penggunaan.index', ['penggunaan' => $penggunaan, 'search' => $search]);
    }

    public function create()
    {
        $barangList = Barang::orderBy('nama_barang')->get();

        return view('penggunaan.create', ['barangList' => $barangList]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_karyawan' => ['required', 'string', 'max:150'],
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'foto_karyawan' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $barang = Barang::findOrFail($data['barang_id']);

        if ($barang->jumlah < $data['jumlah']) {
            throw ValidationException::withMessages([
                'jumlah' => "Stok \"{$barang->nama_barang}\" tidak mencukupi! Tersedia: {$barang->jumlah}.",
            ]);
        }

        $data['foto_karyawan'] = basename($request->file('foto_karyawan')->store('foto_karyawan', 'public'));

        $penggunaan = DB::transaction(function () use ($data, $barang) {
            $penggunaan = Penggunaan::create($data);
            $barang->decrement('jumlah', $data['jumlah']);

            return $penggunaan;
        });

        ActivityLog::catat('penggunaan', 'INSERT', 'Tambah Penggunaan Barang',
            "{$penggunaan->nama_karyawan} menggunakan {$data['jumlah']} unit \"{$barang->nama_barang}\"");

        return redirect()->route('penggunaan.index')->with('success', 'Data penggunaan berhasil ditambahkan!');
    }

    public function edit(Penggunaan $penggunaan)
    {
        $barangList = Barang::orderBy('nama_barang')->get();

        return view('penggunaan.edit', ['penggunaan' => $penggunaan, 'barangList' => $barangList]);
    }

    public function update(Request $request, Penggunaan $penggunaan)
    {
        $data = $request->validate([
            'nama_karyawan' => ['required', 'string', 'max:150'],
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'foto_karyawan' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $barangBaru = Barang::findOrFail($data['barang_id']);

        DB::transaction(function () use ($request, $data, $penggunaan, $barangBaru) {
            if ($penggunaan->barang_id === (int) $data['barang_id']) {
                // Barang sama: sesuaikan stok berdasarkan selisih jumlah
                $selisih = $data['jumlah'] - $penggunaan->jumlah;

                if ($selisih > 0 && $barangBaru->jumlah < $selisih) {
                    throw ValidationException::withMessages([
                        'jumlah' => "Stok \"{$barangBaru->nama_barang}\" tidak mencukupi untuk penambahan ini! Tersedia: {$barangBaru->jumlah}.",
                    ]);
                }

                $barangBaru->decrement('jumlah', $selisih);
            } else {
                // Barang berbeda: kembalikan stok barang lama, kurangi stok barang baru
                if ($barangBaru->jumlah < $data['jumlah']) {
                    throw ValidationException::withMessages([
                        'barang_id' => "Stok \"{$barangBaru->nama_barang}\" tidak mencukupi! Tersedia: {$barangBaru->jumlah}.",
                    ]);
                }

                Barang::whereKey($penggunaan->barang_id)->increment('jumlah', $penggunaan->jumlah);
                $barangBaru->decrement('jumlah', $data['jumlah']);
            }

            if ($request->hasFile('foto_karyawan')) {
                if ($penggunaan->foto_karyawan) {
                    Storage::disk('public')->delete('foto_karyawan/'.$penggunaan->foto_karyawan);
                }
                $data['foto_karyawan'] = basename($request->file('foto_karyawan')->store('foto_karyawan', 'public'));
            }

            $penggunaan->update($data);
        });

        ActivityLog::catat('penggunaan', 'UPDATE', 'Ubah Penggunaan Barang', "Mengubah data penggunaan oleh {$penggunaan->nama_karyawan}");

        return redirect()->route('penggunaan.index')->with('success', 'Data penggunaan berhasil diperbarui!');
    }

    public function destroy(Penggunaan $penggunaan)
    {
        DB::transaction(function () use ($penggunaan) {
            Barang::whereKey($penggunaan->barang_id)->increment('jumlah', $penggunaan->jumlah);

            if ($penggunaan->foto_karyawan) {
                Storage::disk('public')->delete('foto_karyawan/'.$penggunaan->foto_karyawan);
            }

            $penggunaan->delete();
        });

        ActivityLog::catat('penggunaan', 'DELETE', 'Hapus Penggunaan Barang', 'Menghapus data penggunaan dan mengembalikan stok');

        return redirect()->route('penggunaan.index')->with('success', 'Data penggunaan berhasil dihapus dan stok dikembalikan!');
    }
}
