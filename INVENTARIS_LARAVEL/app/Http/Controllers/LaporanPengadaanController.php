<?php

namespace App\Http\Controllers;

use App\Exceptions\ProcuraException;
use App\Models\LaporanPengadaan;
use App\Models\PengajuanPengadaan;
use App\Services\ProcuraClient;
use Illuminate\Http\Request;

/** Halaman "Laporan dari Pengadaan": kabar masuk (webhook/sinkron) + catatan keluar dari Inventaris. */
class LaporanPengadaanController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['baru', 'masuk', 'keluar'], true) ? $request->query('tab') : 'baru';

        $laporan = LaporanPengadaan::with('pengajuan:id,nomor,judul')
            ->when($tab === 'baru', fn ($q) => $q->where('arah', 'masuk')->whereNull('dibaca_at'))
            ->when($tab === 'masuk', fn ($q) => $q->where('arah', 'masuk'))
            ->when($tab === 'keluar', fn ($q) => $q->where('arah', 'keluar'))
            ->orderByDesc('id')->paginate(12)->withQueryString();

        return view('laporan-pengadaan.index', [
            'laporan' => $laporan,
            'tab' => $tab,
            'jumlahBaru' => LaporanPengadaan::belumDibaca(),
            'pengajuanAktif' => PengajuanPengadaan::aktif()->orderByDesc('id')->get(['id', 'nomor', 'judul']),
        ]);
    }

    public function baca(LaporanPengadaan $laporan)
    {
        $laporan->update(['dibaca_at' => $laporan->dibaca_at ?? now()]);

        return back();
    }

    public function bacaSemua()
    {
        LaporanPengadaan::where('arah', 'masuk')->whereNull('dibaca_at')->update(['dibaca_at' => now()]);

        return back()->with('success', 'Semua laporan ditandai sudah dibaca.');
    }

    public function catatan(Request $request, ProcuraClient $client)
    {
        $data = $request->validate([
            'pengajuan_id' => ['required', 'integer', 'exists:pengajuan_pengadaan,id'],
            'judul' => ['required', 'string', 'max:200'],
            'isi' => ['nullable', 'string', 'max:3000'],
        ], ['pengajuan_id.required' => 'Pilih pengajuan yang dimaksud.', 'judul.required' => 'Isi perihal catatan.']);

        $p = PengajuanPengadaan::findOrFail($data['pengajuan_id']);
        if (! $p->sudahTerkirim()) {
            return back()->withInput()->with('error', 'Pengajuan itu belum terkirim ke Pengadaan.');
        }

        try {
            $client->sendNote($p->procura_nomor, $data['judul'], $data['isi'] ?? null, $request->user()->nama_lengkap);
        } catch (ProcuraException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        LaporanPengadaan::catat($p, 'keluar', 'catatan', $data['judul'], $data['isi'] ?? null, null, null, $request->user()->id_user);

        return back()->with('success', 'Catatan terkirim ke Pengadaan.');
    }
}
