<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bangunan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BangunanController extends Controller
{
    public const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $kondisi = $request->query('kondisi', '');

        $query = Bangunan::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_gedung', 'like', "%{$search}%")
                    ->orWhere('kode_gedung', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%");
            });
        }

        if ($kondisi !== '') {
            $query->where('kondisi', $kondisi);
        }

        $bangunan = $query->orderByDesc('id')->paginate(10)->withQueryString();

        return view('bangunan.index', [
            'bangunan' => $bangunan,
            'search' => $search,
            'kondisiFilter' => $kondisi,
            'kondisiList' => self::KONDISI,
        ]);
    }

    public function create()
    {
        return view('bangunan.create', ['kondisiList' => self::KONDISI]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode_gedung' => ['required', 'string', 'max:50', 'unique:bangunan,kode_gedung'],
            'nama_gedung' => ['required', 'string', 'max:150'],
            'harga_gedung' => ['required', 'numeric', 'min:0'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'lokasi' => ['nullable', 'string', 'max:200'],
            'fungsi' => ['nullable', 'string', 'max:150'],
            'tanggal_berdiri' => ['nullable', 'date', 'before_or_equal:today'],
            'pengelola' => ['nullable', 'string', 'max:150'],
            'jabatan_pengelola' => ['nullable', 'string', 'max:100'],
            'foto_gedung' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'foto_pengelola' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('foto_gedung')) {
            $data['foto_gedung'] = basename($request->file('foto_gedung')->store('gedung', 'public'));
        }
        if ($request->hasFile('foto_pengelola')) {
            $data['foto_pengelola'] = basename($request->file('foto_pengelola')->store('pengelola', 'public'));
        }

        $bangunan = Bangunan::create($data);

        ActivityLog::catat('bangunan', 'INSERT', 'Tambah Gedung', "Menambah gedung \"{$bangunan->nama_gedung}\" (Kode: {$bangunan->kode_gedung})");

        return redirect()->route('bangunan.index')->with('success', 'Data gedung berhasil ditambahkan!');
    }

    public function edit(Bangunan $bangunan)
    {
        return view('bangunan.edit', ['bangunan' => $bangunan, 'kondisiList' => self::KONDISI]);
    }

    public function update(Request $request, Bangunan $bangunan)
    {
        $data = $request->validate([
            'kode_gedung' => ['required', 'string', 'max:50', Rule::unique('bangunan', 'kode_gedung')->ignore($bangunan->id)],
            'nama_gedung' => ['required', 'string', 'max:150'],
            'harga_gedung' => ['required', 'numeric', 'min:0'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'lokasi' => ['nullable', 'string', 'max:200'],
            'fungsi' => ['nullable', 'string', 'max:150'],
            'tanggal_berdiri' => ['nullable', 'date', 'before_or_equal:today'],
            'pengelola' => ['nullable', 'string', 'max:150'],
            'jabatan_pengelola' => ['nullable', 'string', 'max:100'],
            'foto_gedung' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'foto_pengelola' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('foto_gedung')) {
            if ($bangunan->foto_gedung) {
                Storage::disk('public')->delete('gedung/'.$bangunan->foto_gedung);
            }
            $data['foto_gedung'] = basename($request->file('foto_gedung')->store('gedung', 'public'));
        }
        if ($request->hasFile('foto_pengelola')) {
            if ($bangunan->foto_pengelola) {
                Storage::disk('public')->delete('pengelola/'.$bangunan->foto_pengelola);
            }
            $data['foto_pengelola'] = basename($request->file('foto_pengelola')->store('pengelola', 'public'));
        }

        $namaLama = $bangunan->nama_gedung;
        $bangunan->update($data);

        ActivityLog::catat('bangunan', 'UPDATE', 'Ubah Gedung', "Mengubah gedung \"{$namaLama}\" menjadi \"{$bangunan->nama_gedung}\"");

        return redirect()->route('bangunan.index')->with('success', 'Data gedung berhasil diperbarui!');
    }

    public function destroy(Bangunan $bangunan)
    {
        if ($bangunan->foto_gedung) {
            Storage::disk('public')->delete('gedung/'.$bangunan->foto_gedung);
        }
        if ($bangunan->foto_pengelola) {
            Storage::disk('public')->delete('pengelola/'.$bangunan->foto_pengelola);
        }

        $nama = $bangunan->nama_gedung;
        $kode = $bangunan->kode_gedung;
        $bangunan->delete();

        ActivityLog::catat('bangunan', 'DELETE', 'Hapus Gedung', "Menghapus gedung \"{$nama}\" (Kode: {$kode})");

        return redirect()->route('bangunan.index')->with('success', 'Data gedung berhasil dihapus!');
    }
}
