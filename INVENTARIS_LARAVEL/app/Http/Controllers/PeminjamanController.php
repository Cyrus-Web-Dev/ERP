<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Kendaraan;
use App\Models\PeminjamanKendaraan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeminjamanController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', '');

        $query = PeminjamanKendaraan::with('kendaraan');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $peminjaman = $query->orderByDesc('id_peminjaman')->paginate(10)->withQueryString();

        return view('peminjaman.index', ['peminjaman' => $peminjaman, 'statusFilter' => $status]);
    }

    public function create()
    {
        // Hanya tampilkan kendaraan yang masih ada unit tersedia
        $kendaraanList = Kendaraan::withCount([
            'peminjaman as sedang_dipinjam' => fn ($q) => $q->where('status', 'Dipinjam'),
        ])->get()->filter(fn ($k) => $k->jumlah > $k->sedang_dipinjam)->values();

        return view('peminjaman.create', ['kendaraanList' => $kendaraanList]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_kendaraan' => ['required', 'integer', 'exists:kendaraan,id_kendaraan'],
            'peminjam' => ['required', 'string', 'max:150'],
            'nik' => ['required', 'digits:16'],
            'tujuan' => ['required', 'string', 'max:255'],
            'tanggal_pinjam' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_kembali' => ['required', 'date', 'after:tanggal_pinjam'],
        ]);

        $lamaHari = now()->parse($data['tanggal_pinjam'])->diffInDays(now()->parse($data['tanggal_kembali']));
        if ($lamaHari > 30) {
            throw ValidationException::withMessages(['tanggal_kembali' => 'Maksimal peminjaman adalah 30 hari.']);
        }

        DB::transaction(function () use ($data) {
            $kendaraan = Kendaraan::whereKey($data['id_kendaraan'])->lockForUpdate()->firstOrFail();

            $sedangDipinjam = $kendaraan->peminjaman()->where('status', 'Dipinjam')->count();
            if ($kendaraan->jumlah <= $sedangDipinjam) {
                throw ValidationException::withMessages(['id_kendaraan' => 'Kendaraan ini sedang tidak tersedia.']);
            }

            $data['status'] = 'Dipinjam';
            $peminjaman = PeminjamanKendaraan::create($data);

            ActivityLog::catat('peminjaman_kendaraan', 'INSERT', 'Peminjaman Kendaraan',
                "{$peminjaman->peminjam} meminjam {$kendaraan->merk} ({$kendaraan->plat_nomor})");
        });

        return redirect()->route('peminjaman.index')->with('success', 'Peminjaman kendaraan berhasil dicatat!');
    }

    public function kembalikan(PeminjamanKendaraan $peminjaman)
    {
        if ($peminjaman->status !== 'Dipinjam') {
            return back()->with('error', 'Data ini sudah dikembalikan sebelumnya.');
        }

        $peminjaman->update(['status' => 'Dikembalikan']);

        ActivityLog::catat('peminjaman_kendaraan', 'UPDATE', 'Pengembalian Kendaraan', "{$peminjaman->peminjam} mengembalikan kendaraan");

        return back()->with('success', 'Kendaraan berhasil ditandai sebagai dikembalikan!');
    }

    public function destroy(PeminjamanKendaraan $peminjaman)
    {
        $nama = $peminjaman->peminjam;
        $peminjaman->delete();

        ActivityLog::catat('peminjaman_kendaraan', 'DELETE', 'Hapus Peminjaman', "Menghapus data peminjaman oleh {$nama}");

        return redirect()->route('peminjaman.index')->with('success', 'Data peminjaman berhasil dihapus!');
    }
}
