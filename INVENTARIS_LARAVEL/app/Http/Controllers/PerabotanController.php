<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Perabotan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PerabotanController extends Controller
{
    public const STATUS = ['Layak Pakai', 'Tidak Layak'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');

        $query = Perabotan::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $perabotan = $query->orderByDesc('id')->paginate(10)->withQueryString();

        return view('perabotan.index', [
            'perabotan' => $perabotan,
            'search' => $search,
            'statusFilter' => $status,
            'statusList' => self::STATUS,
        ]);
    }

    public function create()
    {
        return view('perabotan.create', ['statusList' => self::STATUS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:perabotan,kode'],
            'nama' => ['required', 'string', 'max:150'],
            'merk' => ['nullable', 'string', 'max:100'],
            'tgl_masuk' => ['required', 'date', 'before_or_equal:today'],
            'stok' => ['required', 'integer', 'min:0'],
            'harga' => ['required', 'numeric', 'min:0'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(self::STATUS)],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $data['foto'] = basename($request->file('foto')->store('perabot', 'public'));

        $perabotan = Perabotan::create($data);

        ActivityLog::catat('perabotan', 'INSERT', 'Tambah Perabotan', "Menambah perabotan \"{$perabotan->nama}\" (Kode: {$perabotan->kode})");

        return redirect()->route('perabotan.index')->with('success', 'Data perabotan berhasil ditambahkan!');
    }

    public function edit(Perabotan $perabotan)
    {
        return view('perabotan.edit', ['perabotan' => $perabotan, 'statusList' => self::STATUS]);
    }

    public function update(Request $request, Perabotan $perabotan)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', Rule::unique('perabotan', 'kode')->ignore($perabotan->id)],
            'nama' => ['required', 'string', 'max:150'],
            'merk' => ['nullable', 'string', 'max:100'],
            'tgl_masuk' => ['required', 'date', 'before_or_equal:today'],
            'stok' => ['required', 'integer', 'min:0'],
            'harga' => ['required', 'numeric', 'min:0'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(self::STATUS)],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('foto')) {
            if ($perabotan->foto) {
                Storage::disk('public')->delete('perabot/'.$perabotan->foto);
            }
            $data['foto'] = basename($request->file('foto')->store('perabot', 'public'));
        }

        $namaLama = $perabotan->nama;
        $perabotan->update($data);

        ActivityLog::catat('perabotan', 'UPDATE', 'Ubah Perabotan', "Mengubah perabotan \"{$namaLama}\" menjadi \"{$perabotan->nama}\"");

        return redirect()->route('perabotan.index')->with('success', 'Data perabotan berhasil diperbarui!');
    }

    public function destroy(Perabotan $perabotan)
    {
        if ($perabotan->foto) {
            Storage::disk('public')->delete('perabot/'.$perabotan->foto);
        }

        $nama = $perabotan->nama;
        $kode = $perabotan->kode;
        $perabotan->delete();

        ActivityLog::catat('perabotan', 'DELETE', 'Hapus Perabotan', "Menghapus perabotan \"{$nama}\" (Kode: {$kode})");

        return redirect()->route('perabotan.index')->with('success', 'Data perabotan berhasil dihapus!');
    }
}
