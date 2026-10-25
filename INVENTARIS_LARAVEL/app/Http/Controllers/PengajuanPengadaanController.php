<?php

namespace App\Http\Controllers;

use App\Exceptions\ProcuraException;
use App\Models\ActivityLog;
use App\Models\LaporanPengadaan;
use App\Models\PengajuanPengadaan;
use App\Services\PengadaanSync;
use App\Services\ProcuraClient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PengajuanPengadaanController extends Controller
{
    public function __construct(private PengadaanSync $sync, private ProcuraClient $client)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $tampil = in_array($request->query('tampil'), ['aktif', 'selesai', 'semua', 'perlu'], true) ? $request->query('tampil') : 'aktif';

        $query = PengajuanPengadaan::withCount('items')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('judul', 'ilike', "%{$search}%")->orWhere('nomor', 'ilike', "%{$search}%")->orWhere('procura_nomor', 'ilike', "%{$search}%")))
            ->when($tampil === 'aktif', fn ($q) => $q->whereNotIn('status', ['completed', 'rejected', 'cancelled']))
            ->when($tampil === 'selesai', fn ($q) => $q->whereIn('status', ['completed', 'rejected', 'cancelled']))
            ->when($tampil === 'perlu', fn ($q) => $q->where(fn ($w) => $w->whereNull('procura_nomor')->orWhere(fn ($x) => $x->where('serah_terima_status', 'handed_over'))));

        return view('pengajuan-pengadaan.index', [
            'daftar' => $query->orderByDesc('id')->paginate(10)->withQueryString(),
            'search' => $search,
            'tampil' => $tampil,
            'ringkas' => [
                'aktif' => PengajuanPengadaan::whereNotIn('status', ['completed', 'rejected', 'cancelled'])->count(),
                'konfirmasi' => PengajuanPengadaan::where('serah_terima_status', 'handed_over')->count(),
                'gagal' => PengajuanPengadaan::whereNull('procura_nomor')->count(),
                'selesai' => PengajuanPengadaan::where('status', 'completed')->count(),
            ],
            'terhubung' => $this->client->configured(),
        ]);
    }

    public function create()
    {
        return view('pengajuan-pengadaan.create', [
            'katalog' => collect($this->client->catalog())->groupBy('category')->map(fn ($g) => $g->values())->all(),
            'terhubung' => $this->client->configured(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'kategori' => ['required', Rule::in(array_keys(config('procura.kategori')))],
            'prioritas' => ['required', Rule::in(array_keys(config('procura.prioritas')))],
            'tanggal_dibutuhkan' => ['nullable', 'date', 'after_or_equal:today'],
            'alasan' => ['nullable', 'string', 'max:2000'],
            'nama_pemohon' => ['required', 'string', 'max:120'],
            'unit_pemohon' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.nama' => ['required', 'string', 'max:200'],
            'items.*.spesifikasi' => ['nullable', 'string', 'max:500'],
            'items.*.jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.satuan' => ['required', 'string', 'max:20'],
            'items.*.harga_estimasi' => ['nullable', 'numeric', 'min:0'],
        ], [
            'judul.required' => 'Judul pengajuan wajib diisi.',
            'nama_pemohon.required' => 'Nama pemohon wajib diisi.',
            'tanggal_dibutuhkan.after_or_equal' => 'Tanggal dibutuhkan tidak boleh di masa lalu.',
            'items.required' => 'Tambahkan minimal satu barang/jasa.',
            'items.*.nama.required' => 'Nama barang/jasa wajib diisi.',
            'items.*.jumlah.required' => 'Jumlah wajib diisi.',
            'items.*.jumlah.gt' => 'Jumlah harus lebih dari nol.',
            'items.*.satuan.required' => 'Satuan wajib diisi.',
        ]);

        $p = $this->simpan($data, $request->user()->id_user);

        ActivityLog::catat('pengadaan', 'INSERT', 'Ajukan ke Pengadaan', "Pengajuan {$p->nomor}: {$p->judul}");

        try {
            $this->sync->kirim($p);

            return redirect()->route('pengajuan.show', $p)->with('success', "Pengajuan {$p->nomor} terkirim ke Pengadaan (No. {$p->procura_nomor}).");
        } catch (ProcuraException $e) {
            // Data tidak hilang: tersimpan lokal dan bisa dikirim ulang.
            return redirect()->route('pengajuan.show', $p)->with('error', "Pengajuan {$p->nomor} tersimpan, tetapi belum terkirim ke Pengadaan. {$e->getMessage()} Anda bisa mengirim ulang dari halaman ini.");
        }
    }

    public function show(PengajuanPengadaan $pengajuan)
    {
        // Tarik status terbaru bila data basi (>2 menit) atau baru ditandai basi oleh webhook.
        if ($pengajuan->procura_nomor && ! $pengajuan->selesai() && $this->client->configured()
            && (! $pengajuan->disinkronkan_at || $pengajuan->disinkronkan_at->lt(now()->subMinutes(2)))) {
            $this->sync->sinkron($pengajuan);
            $pengajuan->refresh();
        }

        $pengajuan->load(['items', 'pembuat']);

        $diterima = $pengajuan->itemDiterima();
        [$ruteAset, $paramAset] = config("procura.rute_aset.{$pengajuan->kategori}", [null, []]);

        $barisAset = collect($diterima)->map(function ($b) use ($ruteAset, $paramAset) {
            $b['url'] = $ruteAset ? route($ruteAset, array_filter([
                ($paramAset['nama'] ?? null) => $b['nama'], ($paramAset['jumlah'] ?? null) => $b['jumlah'], ($paramAset['harga'] ?? null) => $b['harga'],
            ], fn ($v, $k) => $k !== '' && $v !== null, ARRAY_FILTER_USE_BOTH)) : null;

            return $b;
        })->all();

        return view('pengajuan-pengadaan.show', [
            'p' => $pengajuan,
            'laporan' => $pengajuan->laporan()->limit(40)->get(),
            'barisAset' => $barisAset,
            'jasa' => $pengajuan->kategori === 'jasa',
            'pesananPo' => $pengajuan->snapshot['purchase_orders'] ?? [],
            'danaStatus' => collect($pengajuan->snapshot['funding'] ?? [])->last(),
            'terhubung' => $this->client->configured(),
        ]);
    }

    public function kirimUlang(PengajuanPengadaan $pengajuan)
    {
        if ($pengajuan->sudahTerkirim()) {
            return back()->with('error', 'Pengajuan ini sudah terkirim ke Pengadaan.');
        }

        try {
            $this->sync->kirim($pengajuan);
        } catch (ProcuraException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::catat('pengadaan', 'UPDATE', 'Kirim ulang ke Pengadaan', "Pengajuan {$pengajuan->nomor} terkirim sebagai {$pengajuan->procura_nomor}");

        return back()->with('success', "Terkirim ke Pengadaan (No. {$pengajuan->procura_nomor}).");
    }

    public function sinkron(PengajuanPengadaan $pengajuan)
    {
        if (! $pengajuan->sudahTerkirim()) {
            return back()->with('error', 'Pengajuan ini belum terkirim ke Pengadaan.');
        }

        return $this->sync->sinkron($pengajuan)
            ? back()->with('success', 'Status terbaru dari Pengadaan sudah dimuat.')
            : back()->with('error', $pengajuan->fresh()->error_terakhir ?? 'Gagal memuat status dari Pengadaan.');
    }

    public function konfirmasi(Request $request, PengajuanPengadaan $pengajuan)
    {
        if (! $pengajuan->serah_terima_nomor || $pengajuan->serah_terima_status === 'confirmed') {
            return back()->with('error', 'Tidak ada serah terima yang menunggu konfirmasi.');
        }

        try {
            $this->client->confirmHandover($pengajuan->serah_terima_nomor, $request->user()->nama_lengkap);
        } catch (ProcuraException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->sync->sinkron($pengajuan);   // tarik status terbaru (bisa langsung "Selesai")
        LaporanPengadaan::catat($pengajuan, 'keluar', 'konfirmasi', "Serah terima {$pengajuan->serah_terima_nomor} dikonfirmasi", 'Dikonfirmasi oleh '.$request->user()->nama_lengkap, 'konfirmasi:'.$pengajuan->serah_terima_nomor, null, $request->user()->id_user);
        ActivityLog::catat('pengadaan', 'UPDATE', 'Konfirmasi serah terima', "Serah terima {$pengajuan->serah_terima_nomor} untuk {$pengajuan->nomor} dikonfirmasi");

        return back()->with('success', 'Serah terima dikonfirmasi. Silakan catat barangnya ke data aset.');
    }

    public function asetDicatat(PengajuanPengadaan $pengajuan)
    {
        $pengajuan->update(['aset_dicatat_at' => now()]);
        ActivityLog::catat('pengadaan', 'UPDATE', 'Barang pengadaan dicatat sebagai aset', "Pengajuan {$pengajuan->nomor} ditandai sudah dicatat ke data aset");

        return back()->with('success', 'Ditandai sudah dicatat ke data aset.');
    }

    public function catatan(Request $request, PengajuanPengadaan $pengajuan)
    {
        $data = $request->validate(['judul' => ['required', 'string', 'max:200'], 'isi' => ['nullable', 'string', 'max:3000']], ['judul.required' => 'Isi perihal catatan.']);

        if (! $pengajuan->sudahTerkirim()) {
            return back()->with('error', 'Pengajuan belum terkirim ke Pengadaan, catatan belum bisa dikirim.');
        }

        try {
            $this->client->sendNote($pengajuan->procura_nomor, $data['judul'], $data['isi'] ?? null, $request->user()->nama_lengkap);
        } catch (ProcuraException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        LaporanPengadaan::catat($pengajuan, 'keluar', 'catatan', $data['judul'], $data['isi'] ?? null, null, null, $request->user()->id_user);

        return back()->with('success', 'Catatan terkirim ke Pengadaan.');
    }

    public function destroy(PengajuanPengadaan $pengajuan)
    {
        if ($pengajuan->sudahTerkirim()) {
            return back()->with('error', 'Pengajuan yang sudah terkirim tidak bisa dihapus. Minta Pengadaan membatalkannya.');
        }
        $nomor = $pengajuan->nomor;
        $pengajuan->delete();
        ActivityLog::catat('pengadaan', 'DELETE', 'Hapus pengajuan draf', "Pengajuan {$nomor} (belum terkirim) dihapus");

        return redirect()->route('pengajuan.index')->with('success', 'Pengajuan draf dihapus.');
    }

    private function simpan(array $data, int $userId): PengajuanPengadaan
    {
        for ($coba = 0; $coba < 3; $coba++) {
            try {
                return DB::transaction(function () use ($data, $userId) {
                    $prefix = 'PGJ-'.now()->format('ym').'-';
                    $terakhir = PengajuanPengadaan::where('nomor', 'like', $prefix.'%')->orderByDesc('nomor')->lockForUpdate()->value('nomor');
                    $nomor = $prefix.str_pad((string) (((int) substr((string) $terakhir, -4)) + 1), 4, '0', STR_PAD_LEFT);

                    $p = PengajuanPengadaan::create([
                        'nomor' => $nomor, 'judul' => $data['judul'], 'kategori' => $data['kategori'], 'prioritas' => $data['prioritas'],
                        'tanggal_dibutuhkan' => $data['tanggal_dibutuhkan'] ?? null, 'alasan' => $data['alasan'] ?? null,
                        'nama_pemohon' => $data['nama_pemohon'], 'unit_pemohon' => $data['unit_pemohon'] ?? null,
                        'total_estimasi' => collect($data['items'])->sum(fn ($i) => (float) $i['jumlah'] * (float) ($i['harga_estimasi'] ?? 0)),
                        'status' => 'draft', 'dibuat_oleh' => $userId,
                    ]);
                    foreach ($data['items'] as $i) {
                        $p->items()->create([
                            'nama' => $i['nama'], 'spesifikasi' => $i['spesifikasi'] ?? null, 'jumlah' => $i['jumlah'],
                            'satuan' => $i['satuan'], 'harga_estimasi' => $i['harga_estimasi'] ?? 0,
                        ]);
                    }

                    return $p;
                });
            } catch (QueryException $e) {
                if ($coba === 2 || ! str_contains($e->getMessage(), 'nomor')) {
                    throw $e;   // bukan tabrakan nomor, atau sudah 3x gagal
                }
            }
        }
    }
}
