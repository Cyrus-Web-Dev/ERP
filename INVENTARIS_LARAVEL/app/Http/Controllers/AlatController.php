<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Alat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AlatController extends Controller
{
    // Sama seperti $allowed_jenis di tambah.php lama
    public const JENIS = ['Peralatan Kantor', 'Dekorasi', 'Kebersihan'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $jenis = $request->query('jenis', '');

        $query = Alat::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('jenis', 'like', "%{$search}%");
            });
        }

        if ($jenis !== '') {
            $query->where('jenis', $jenis);
        }

        $alat = $query->orderByDesc('id')->paginate(10)->withQueryString();

        return view('non-elektronik.index', [
            'alat' => $alat,
            'search' => $search,
            'jenisFilter' => $jenis,
            'jenisList' => self::JENIS,
        ]);
    }

    public function create()
    {
        return view('non-elektronik.create', ['jenisList' => self::JENIS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'jenis' => ['required', Rule::in(self::JENIS)],
            'tanggal_beli' => ['required', 'date', 'before_or_equal:today'],
            'harga' => ['required', 'numeric', 'min:0'],
            'jumlah' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('foto')) {
            $data['foto'] = basename($request->file('foto')->store('dekorasi', 'public'));
        }

        $alat = Alat::create($data);

        ActivityLog::catat('alat', 'INSERT', 'Tambah Barang Non Elektronik', "Menambah alat \"{$alat->nama}\" ({$alat->jenis})");

        return redirect()->route('non-elektronik.index')->with('success', 'Data alat berhasil ditambahkan!');
    }

    public function edit(Alat $alat)
    {
        return view('non-elektronik.edit', ['alat' => $alat, 'jenisList' => self::JENIS]);
    }

    public function update(Request $request, Alat $alat)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'jenis' => ['required', Rule::in(self::JENIS)],
            'tanggal_beli' => ['required', 'date', 'before_or_equal:today'],
            'harga' => ['required', 'numeric', 'min:0'],
            'jumlah' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('foto')) {
            if ($alat->foto) {
                Storage::disk('public')->delete('dekorasi/'.$alat->foto);
            }
            $data['foto'] = basename($request->file('foto')->store('dekorasi', 'public'));
        }

        $namaLama = $alat->nama;
        $alat->update($data);

        ActivityLog::catat('alat', 'UPDATE', 'Ubah Barang Non Elektronik', "Mengubah alat \"{$namaLama}\" menjadi \"{$alat->nama}\"");

        return redirect()->route('non-elektronik.index')->with('success', 'Data alat berhasil diperbarui!');
    }

    public function destroy(Alat $alat)
    {
        if ($alat->foto) {
            Storage::disk('public')->delete('dekorasi/'.$alat->foto);
        }

        $nama = $alat->nama;
        $alat->delete();

        ActivityLog::catat('alat', 'DELETE', 'Hapus Barang Non Elektronik', "Menghapus alat \"{$nama}\"");

        return redirect()->route('non-elektronik.index')->with('success', 'Data alat berhasil dihapus!');
    }
}
