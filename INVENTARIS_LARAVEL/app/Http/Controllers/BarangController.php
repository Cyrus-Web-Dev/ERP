<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BarangController extends Controller
{
    // Sama seperti daftar kategori di add.php/edit.php lama
    public const KATEGORI = ['Komputer', 'Printer & Scanner', 'Fotocopy', 'Telepon', 'Proyektor'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $kategori = $request->query('kategori', '');

        $query = Barang::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'like', "%{$search}%")
                    ->orWhere('kategori_barang', 'like', "%{$search}%")
                    ->orWhere('kode_aset', 'like', "%{$search}%");
            });
        }

        if ($kategori !== '') {
            $query->where('kategori_barang', $kategori);
        }

        $barang = $query->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('elektronik.index', [
            'barang' => $barang,
            'search' => $search,
            'kategoriFilter' => $kategori,
            'kategoriList' => self::KATEGORI,
        ]);
    }

    public function create()
    {
        return view('elektronik.create', ['kategoriList' => self::KATEGORI]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_barang' => ['required', 'string', 'max:150'],
            'kategori_barang' => ['required', Rule::in(self::KATEGORI)],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga' => ['required', 'numeric', 'min:0'],
            'tgl' => ['required', 'date', 'before_or_equal:today'],
            'foto_barang' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('foto_barang')->store('elektronik', 'public');
        $data['foto_barang'] = basename($path);
        $data['kode_aset'] = $this->generateKodeAset();

        $barang = Barang::create($data);

        ActivityLog::catat('barang', 'INSERT', 'Tambah Barang Elektronik',
            "Menambah barang \"{$barang->nama_barang}\" (Kode Aset: {$barang->kode_aset})");

        return redirect()->route('elektronik.index')
            ->with('success', "Data barang berhasil ditambahkan! Kode Aset: {$barang->kode_aset}");
    }

    public function edit(Barang $barang)
    {
        return view('elektronik.edit', ['barang' => $barang, 'kategoriList' => self::KATEGORI]);
    }

    public function update(Request $request, Barang $barang)
    {
        $data = $request->validate([
            'nama_barang' => ['required', 'string', 'max:150'],
            'kategori_barang' => ['required', Rule::in(self::KATEGORI)],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga' => ['required', 'numeric', 'min:0'],
            'tgl' => ['required', 'date', 'before_or_equal:today'],
            'foto_barang' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('foto_barang')) {
            if ($barang->foto_barang) {
                Storage::disk('public')->delete('elektronik/'.$barang->foto_barang);
            }
            $path = $request->file('foto_barang')->store('elektronik', 'public');
            $data['foto_barang'] = basename($path);
        }

        $namaLama = $barang->nama_barang;
        $barang->update($data);

        ActivityLog::catat('barang', 'UPDATE', 'Ubah Barang Elektronik',
            "Mengubah barang \"{$namaLama}\" menjadi \"{$barang->nama_barang}\"");

        return redirect()->route('elektronik.index')->with('success', 'Data barang berhasil diperbarui!');
    }

    public function destroy(Barang $barang)
    {
        if ($barang->foto_barang) {
            Storage::disk('public')->delete('elektronik/'.$barang->foto_barang);
        }

        $nama = $barang->nama_barang;
        $kodeAset = $barang->kode_aset;
        $barang->delete();

        ActivityLog::catat('barang', 'DELETE', 'Hapus Barang Elektronik',
            "Menghapus barang \"{$nama}\" (Kode Aset: {$kodeAset})");

        return redirect()->route('elektronik.index')->with('success', 'Data barang berhasil dihapus!');
    }

    private function generateKodeAset(): string
    {
        do {
            $kode = 'ELK-'.date('Y').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Barang::where('kode_aset', $kode)->exists());

        return $kode;
    }
}
