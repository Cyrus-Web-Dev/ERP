<?php

namespace Database\Seeders;

use App\Models\PengajuanPengadaan;
use App\Models\PengajuanPengadaanItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * DATA SHOWCASE (demo) untuk INVENTARIS.
 *
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * 5 halaman utama:
 *   1. Barang Elektronik        -> 10 barang (komputer, printer, proyektor, dst) lengkap dengan kode aset
 *   2. Kendaraan & Peminjaman   -> 8 kendaraan (tersedia / perbaikan / nonaktif) + 10 riwayat peminjaman (2 masih dipinjam)
 *   3. Perabotan                -> 10 perabot (layak pakai & tidak layak) per lokasi
 *   4. Gedung / Bangunan        -> 8 gedung dengan kondisi & pengelola
 *   5. Pengajuan Pengadaan      -> 9 pengajuan di semua tahap (belum terkirim ... selesai, ditolak) + buku laporan
 * Bonus: 8 alat, 8 catatan penggunaan barang, 8 jadwal maintenance (terjadwal & selesai).
 * Halaman Depresiasi (penyusutan) dan Dashboard ikut terisi otomatis karena dihitung dari data di atas.
 *
 * Catatan: pengajuan yang berstatus "sudah terkirim" memakai nomor PROCURA contoh (PR-xxxx). JANGAN jalankan
 * `php artisan procura:sync` selama demo bila PROCURA tidak berisi nomor itu, karena akan menandai error sinkron.
 * Aman dijalankan berulang (data dengan kode/plat/nomor sama diperbarui, bukan digandakan).
 */
class ShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'Demo@12345';

    private Carbon $today;

    private int $adminId;

    public function run(): void
    {
        $this->today = Carbon::now();

        $this->users();
        $this->barang();            // halaman 1
        $this->kendaraan();         // halaman 2
        $this->perabotan();         // halaman 3
        $this->bangunan();          // halaman 4
        $this->pengajuan();         // halaman 5
        $this->alat();
        $this->penggunaan();
        $this->maintenance();

        $this->command?->info('Data showcase INVENTARIS selesai dibuat.');
        $this->command?->table(['Peran', 'Username', 'Kata sandi'], [
            ['Super Admin', 'superadmin', 'password123 (bawaan DatabaseSeeder)'],
            ['Super Admin', 'kepala.aset', self::PASSWORD],
            ['Admin', 'admin.gudang', self::PASSWORD],
        ]);
    }

    private function ago(int $days, string $time = '09:00:00'): string
    {
        return $this->today->copy()->subDays($days)->format('Y-m-d').' '.$time;
    }

    private function date(int $daysAgo): string
    {
        return $this->today->copy()->subDays($daysAgo)->toDateString();
    }

    /* ------------------------------------------------------------- akun */

    private function users(): void
    {
        $defs = [
            ['kepala.aset', 'Hendra Kusuma', 'Kepala Divisi Aset', 'hendra.aset@contoh.test', 1, 'super_admin', '0812-3400-5001'],
            ['admin.gudang', 'Rina Marlina', 'Staf Gudang & Aset', 'rina.gudang@contoh.test', 2, 'admin', '0812-3400-5002'],
        ];
        foreach ($defs as [$username, $name, $jabatan, $email, $role, $level, $phone]) {
            DB::table('users')->updateOrInsert(['username' => $username], [
                'nama_lengkap' => $name, 'password' => Hash::make(self::PASSWORD), 'jabatan' => $jabatan, 'email' => $email, 'no_telepon' => $phone,
                'status' => 'aktif', 'id_role' => $role, 'level_akses' => $level, 'approved' => 1, 'email_verified' => 1,
                'registration_date' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->adminId = (int) DB::table('users')->where('username', 'admin.gudang')->value('id_user');
    }

    /* -------------------------------------------- 1. BARANG ELEKTRONIK */

    private function barang(): void
    {
        $y = $this->today->year;
        // kode, nama, kategori, jumlah, harga satuan, hari lalu perolehan
        $rows = [
            ['01', 'Laptop Lenovo ThinkPad E14 Gen 5', 'Komputer', 12, 13500000, 420],
            ['02', 'PC Desktop HP ProDesk 400 G9', 'Komputer', 8, 9800000, 700],
            ['03', 'Laptop ASUS Vivobook 14 (staf lapangan)', 'Komputer', 6, 8900000, 210],
            ['04', 'Printer Epson L5290 All-in-One', 'Printer & Scanner', 4, 3250000, 300],
            ['05', 'Scanner Dokumen Fujitsu fi-7160', 'Printer & Scanner', 2, 11500000, 520],
            ['06', 'Printer HP LaserJet Pro M404dn', 'Printer & Scanner', 5, 4150000, 130],
            ['07', 'Mesin Fotokopi Ricoh MP 2014AD', 'Fotocopy', 1, 28500000, 860],
            ['08', 'Telepon IP Panasonic KX-HDV130', 'Telepon', 15, 1150000, 380],
            ['09', 'Proyektor Epson EB-X06', 'Proyektor', 3, 6900000, 600],
            ['10', 'Proyektor BenQ MW550 (ruang rapat)', 'Proyektor', 2, 7600000, 90],
        ];
        foreach ($rows as [$no, $nama, $kategori, $jumlah, $harga, $ago]) {
            $kode = "ELK-{$y}-01{$no}";
            DB::table('barang')->updateOrInsert(['kode_aset' => $kode], [
                'nama_barang' => $nama, 'kategori_barang' => $kategori, 'jumlah' => $jumlah, 'harga' => $harga, 'tgl' => $this->date($ago),
                'created_at' => $this->ago($ago), 'updated_at' => $this->ago($ago),
            ]);
        }
    }

    /* ---------------------------------------- 2. KENDARAAN & PEMINJAMAN */

    private function kendaraan(): void
    {
        // merk, plat, jumlah, harga satuan, status, hari lalu perolehan
        $rows = [
            ['Toyota Avanza 1.5 G', 'BA 1234 AB', 1, 245000000, 'tersedia', 1100],
            ['Toyota Kijang Innova Reborn 2.4 V', 'BA 2045 CD', 1, 395000000, 'tersedia', 800],
            ['Mitsubishi L300 Pick Up', 'BA 8821 EF', 1, 232000000, 'tersedia', 1500],
            ['Daihatsu Gran Max Blind Van', 'BA 9102 GH', 1, 198000000, 'perbaikan', 1300],
            ['Honda Vario 125 CBS', 'BA 6721 JK', 1, 23500000, 'tersedia', 450],
            ['Yamaha NMAX 155 Connected', 'BA 6755 LM', 1, 34500000, 'tersedia', 300],
            ['Suzuki Carry Futura (tua)', 'BA 7210 NP', 1, 112000000, 'nonaktif', 2800],
            ['Isuzu Elf NLR 55 (distribusi)', 'BA 9345 QR', 1, 415000000, 'tersedia', 600],
        ];
        foreach ($rows as [$merk, $plat, $jumlah, $harga, $status, $ago]) {
            DB::table('kendaraan')->updateOrInsert(['plat_nomor' => $plat], [
                'merk' => $merk, 'jumlah' => $jumlah, 'harga_kendaraan' => $harga, 'total_keseluruhan' => $harga * $jumlah, 'status' => $status,
                'created_at' => $this->ago($ago), 'updated_at' => $this->ago(min($ago, 30)),
            ]);
        }
        $id = fn (string $plat) => (int) DB::table('kendaraan')->where('plat_nomor', $plat)->value('id_kendaraan');

        // plat, peminjam, NIK, tujuan, hari lalu pinjam, lama (hari) / null bila masih dipinjam
        $loans = [
            ['BA 1234 AB', 'Fajar Nugroho', '1371012812820003', 'Kunjungan klien ke Bukittinggi', 38, 2],
            ['BA 2045 CD', 'Dr. Andini Prameswari', '1371015402800002', 'Rapat koordinasi di Dinas Perindustrian', 33, 1],
            ['BA 8821 EF', 'Agus Pratama', '1303022403920001', 'Pengiriman perabot ke cabang Pariaman', 27, 3],
            ['BA 6721 JK', 'Rina Kusuma Dewi', '1304016804970004', 'Survei lokasi pemasaran di Solok', 21, 1],
            ['BA 9345 QR', 'Dewi Anggraini', '1371014908950005', 'Distribusi barang ke Pasaman', 18, 2],
            ['BA 6755 LM', 'Rizky Hidayat', '1377013001930006', 'Perawatan jaringan kantor cabang', 12, 1],
            ['BA 2045 CD', 'Hendra Wijaya', '1376010509840007', 'Seminar teknologi di Bandara Minangkabau', 9, 1],
            ['BA 1234 AB', 'Siti Rahmawati', '1375011211870008', 'Penyerahan laporan ke KPP Pratama', 6, 1],
            ['BA 1234 AB', 'Budi Santoso', '1371011705900009', 'Audit lapangan ke vendor di Padang Panjang', 1, null],
            ['BA 6721 JK', 'Maya Putri Utami', '1371015606940010', 'Antar dokumen kepegawaian ke BPJS', 0, null],
        ];
        foreach ($loans as [$plat, $peminjam, $nik, $tujuan, $ago, $lama]) {
            $pinjam = $this->date($ago);
            DB::table('peminjaman_kendaraan')->updateOrInsert(['id_kendaraan' => $id($plat), 'peminjam' => $peminjam, 'tanggal_pinjam' => $pinjam], [
                'nik' => $nik, 'tujuan' => $tujuan, 'tanggal_kembali' => $lama === null ? null : $this->date($ago - $lama), 'status' => $lama === null ? 'Dipinjam' : 'Dikembalikan',
                'created_at' => $this->ago($ago, '08:15:00'), 'updated_at' => $this->ago($lama === null ? $ago : $ago - $lama, '16:40:00'),
            ]);
        }
    }

    /* -------------------------------------------------- 3. PERABOTAN */

    private function perabotan(): void
    {
        // kode, nama, merk, stok, harga, lokasi, status, hari lalu masuk
        $rows = [
            ['PRB-0001', 'Kursi Kerja Ergonomis', 'Chairman', 24, 1450000, 'Ruang Kerja Lantai 2', 'Layak Pakai', 400],
            ['PRB-0002', 'Meja Kerja 140x70 cm', 'Olympic', 24, 1750000, 'Ruang Kerja Lantai 2', 'Layak Pakai', 400],
            ['PRB-0003', 'Lemari Arsip Besi 4 Laci', 'Brother', 10, 2300000, 'Ruang Arsip', 'Layak Pakai', 520],
            ['PRB-0004', 'Sofa Tamu 3-2-1', 'Informa', 1, 8500000, 'Lobi Utama', 'Layak Pakai', 700],
            ['PRB-0005', 'Meja Rapat Kayu Jati 3 m', 'Mebel Minang Jaya', 1, 6200000, 'Ruang Rapat Direksi', 'Layak Pakai', 90],
            ['PRB-0006', 'Kursi Rapat Kulit Sintetis', 'Chairman', 10, 1150000, 'Ruang Rapat Direksi', 'Layak Pakai', 90],
            ['PRB-0007', 'Rak Buku Kayu 5 Susun', 'Ligna', 6, 1350000, 'Perpustakaan Kecil', 'Layak Pakai', 610],
            ['PRB-0008', 'Whiteboard Magnetik 120x240', 'Sakana', 5, 950000, 'Ruang Pelatihan', 'Layak Pakai', 330],
            ['PRB-0009', 'Kursi Plastik Lipat (rusak engsel)', 'Napolly', 18, 185000, 'Gudang Perabot', 'Tidak Layak', 1400],
            ['PRB-0010', 'Locker Karyawan 12 Pintu', 'Alba', 4, 3150000, 'Ruang Loker', 'Layak Pakai', 480],
        ];
        foreach ($rows as [$kode, $nama, $merk, $stok, $harga, $lokasi, $status, $ago]) {
            DB::table('perabotan')->updateOrInsert(['kode' => $kode], [
                'nama' => $nama, 'merk' => $merk, 'tgl_masuk' => $this->date($ago), 'stok' => $stok, 'harga' => $harga, 'lokasi' => $lokasi, 'status' => $status,
                'created_at' => $this->ago($ago), 'updated_at' => $this->ago($ago),
            ]);
        }
    }

    /* ------------------------------------------------ 4. GEDUNG */

    private function bangunan(): void
    {
        // kode, nama, harga, kondisi, lokasi, fungsi, tahun lalu berdiri, pengelola, jabatan
        $rows = [
            ['GDG-001', 'Kantor Pusat', 4850000000, 'Baik', 'Jl. Jenderal Sudirman No. 88, Padang', 'Perkantoran & administrasi', 8, 'Hendra Kusuma', 'Kepala Divisi Aset'],
            ['GDG-002', 'Gudang Utama', 2750000000, 'Baik', 'Kawasan Industri Bypass KM 9, Padang', 'Penyimpanan & distribusi barang', 6, 'Fajar Nugroho', 'Kepala Operasional'],
            ['GDG-003', 'Gudang Cabang Padang Panjang', 1450000000, 'Rusak Ringan', 'Jl. Raya Padang Panjang KM 2', 'Gudang transit wilayah timur', 11, 'Agus Pratama', 'Koordinator Gudang'],
            ['GDG-004', 'Aula Serbaguna', 1250000000, 'Baik', 'Kompleks Kantor Pusat, Padang', 'Pelatihan, rapat besar, dan acara perusahaan', 5, 'Maya Putri Utami', 'Staf SDM'],
            ['GDG-005', 'Mess Karyawan', 980000000, 'Rusak Ringan', 'Jl. Adinegoro No. 17, Padang', 'Hunian karyawan luar kota', 9, 'Rina Marlina', 'Staf Gudang & Aset'],
            ['GDG-006', 'Pos Satpam Gerbang Utama', 85000000, 'Baik', 'Gerbang Kantor Pusat, Padang', 'Keamanan & pencatatan tamu', 4, 'Hendra Kusuma', 'Kepala Divisi Aset'],
            ['GDG-007', 'Workshop & Bengkel Ringan', 620000000, 'Rusak Berat', 'Belakang Gudang Utama, Padang', 'Perawatan kendaraan & peralatan', 14, 'Fajar Nugroho', 'Kepala Operasional'],
            ['GDG-008', 'Ruang Arsip & Dokumen', 540000000, 'Baik', 'Lantai Dasar Kantor Pusat', 'Penyimpanan arsip kontrak dan dokumen legal', 3, 'Rina Marlina', 'Staf Gudang & Aset'],
        ];
        foreach ($rows as [$kode, $nama, $harga, $kondisi, $lokasi, $fungsi, $thn, $pengelola, $jabatan]) {
            DB::table('bangunan')->updateOrInsert(['kode_gedung' => $kode], [
                'nama_gedung' => $nama, 'harga_gedung' => $harga, 'kondisi' => $kondisi, 'lokasi' => $lokasi, 'fungsi' => $fungsi,
                'tanggal_berdiri' => $this->today->copy()->subYears($thn)->startOfMonth()->addDays(9)->toDateString(), 'pengelola' => $pengelola, 'jabatan_pengelola' => $jabatan,
                'created_at' => $this->ago(30 * $thn), 'updated_at' => $this->ago(20),
            ]);
        }
    }

    /* ----------------------------------------- 5. PENGAJUAN PENGADAAN */

    private function pengajuan(): void
    {
        $t = $this->today;
        $u = $this->adminId;
        $y = $t->format('ym');

        // [judul, kategori, prioritas, hari lalu dibuat, dibutuhkan (+hari), alasan, pemohon, unit, status, stasiun, procura no, item[[nama, spek, jml, satuan, harga]], ekstra]
        $rows = [
            ['Penggantian laptop staf Gudang & Aset', 'elektronik', 'high', 84, -50, 'Tiga laptop lama sudah tidak sanggup menjalankan aplikasi inventaris dengan lancar.', 'Rina Marlina', 'Divisi Inventaris',
                'completed', 'handed_over', 'PR-%s-0001', [['Laptop kerja 14"', 'i5 gen-13, RAM 16GB, SSD 512GB', 3, 'unit', 12500000]],
                ['dana' => ['FR-%s-0001', 'disbursed', 'Dana dicairkan'], 'po' => ['PO-%s-0001', 'Mitra Teknologi Nusantara', 'received', -62], 'ho' => ['HO-%s-0001', 'confirmed', -60, 'Rina Marlina'], 'aset' => true]],
            ['Kursi & meja ruang arsip baru', 'perabotan', 'normal', 62, -40, 'Ruang arsip lantai 2 belum punya perabot kerja.', 'Andi Saputra', 'Divisi Inventaris',
                'handed_over', 'handed_over', 'PR-%s-0002', [['Kursi kerja ergonomis', null, 4, 'unit', 1450000], ['Meja kerja 140x70 cm', null, 4, 'unit', 1750000]],
                ['dana' => ['FR-%s-0002', 'disbursed', 'Dana dicairkan'], 'po' => ['PO-%s-0002', 'CV Sinar Perabot Jaya', 'received', -50], 'ho' => ['HO-%s-0002', 'handed_over', -5, 'Andi Saputra']]],
            ['Servis berkala AC gedung utama', 'jasa', 'urgent', 44, -25, 'Beberapa unit AC mulai kurang dingin menjelang musim panas.', 'Rina Marlina', 'Divisi Inventaris',
                'received', 'received', 'PR-%s-0003', [['Servis berkala AC', 'Cuci unit + cek freon', 18, 'unit', 185000]],
                ['dana' => ['FR-%s-0003', 'disbursed', 'Dana dicairkan'], 'po' => ['PO-%s-0003', 'Solusi Servis Prima', 'received', -18]]],
            ['Proyektor ruang pelatihan', 'elektronik', 'normal', 22, 10, 'Proyektor ruang pelatihan sering mati sendiri saat dipakai lebih dari 1 jam.', 'Maya Putri Utami', 'Divisi SDM',
                'ordered', 'ordered', 'PR-%s-0004', [['Proyektor 4000 lumens', 'Resolusi WXGA, HDMI x2', 2, 'unit', 7400000]],
                ['dana' => ['FR-%s-0004', 'disbursed', 'Dana dicairkan'], 'po' => ['PO-%s-0004', 'Bina Komputer Padang', 'sent', 6]]],
            ['Mobil dinas operasional', 'kendaraan', 'normal', 12, 45, 'Kendaraan dinas lama sudah 9 tahun dan sering turun mesin.', 'Fajar Nugroho', 'Divisi Operasional',
                'funding', 'funding', 'PR-%s-0005', [['Mobil MPV 1.500 cc', 'Transmisi otomatis, warna putih', 1, 'unit', 285000000]],
                ['dana' => ['FR-%s-0005', 'submitted', 'Menunggu keputusan Keuangan']]],
            ['Lemari arsip dokumen kontrak', 'non_elektronik', 'low', 9, 25, 'Dokumen kontrak dan faktur belum tersimpan rapi.', 'Andi Saputra', 'Divisi Inventaris',
                'sourcing', 'sourcing', 'PR-%s-0006', [['Lemari arsip besi 4 laci', 'Kunci centralized', 6, 'unit', 2300000]], []],
            ['Kebutuhan ATK triwulan IV', 'habis_pakai', 'normal', 4, 15, 'Stok kertas dan toner gudang menipis.', 'Rina Marlina', 'Divisi Inventaris',
                'reviewing', 'reviewing', 'PR-%s-0007', [['Kertas HVS A4 80gr', null, 60, 'rim', 52000], ['Toner printer laser hitam', null, 6, 'unit', 850000]], []],
            ['Mesin fotokopi pengganti (draf)', 'elektronik', 'normal', 1, 30, 'Mesin fotokopi lama sering macet dan suku cadangnya sudah tidak tersedia.', 'Rina Marlina', 'Divisi Inventaris',
                'draft', null, null, [['Mesin fotokopi multifungsi A3', 'Kecepatan 25 ppm, duplex otomatis', 1, 'unit', 32000000]], []],
            ['Renovasi taman dan area parkir', 'gedung', 'low', 30, 60, 'Mempercantik area depan kantor.', 'Hendra Kusuma', 'Divisi Aset',
                'rejected', 'reviewing', 'PR-%s-0008', [['Pekerjaan lansekap & paving', '120 m²', 1, 'paket', 85000000]],
                ['tolak' => 'Ditunda ke tahun anggaran berikutnya karena prioritas dana dialihkan ke penggantian perangkat TI.']],
        ];

        foreach ($rows as $k => [$judul, $kat, $prio, $ago, $need, $alasan, $pemohon, $unit, $status, $stasiun, $prNo, $items, $x]) {
            $created = $t->copy()->subDays($ago)->setTime(9, 20);
            $nomor = 'PGJ-'.$created->format('ym').'-'.str_pad((string) ($k + 1), 4, '0', STR_PAD_LEFT);
            $total = array_sum(array_map(fn ($i) => $i[2] * $i[4], $items));
            $procura = $prNo ? sprintf($prNo, $created->format('ym')) : null;
            $sent = $procura !== null;

            // ringkasan keadaan terakhir di Pengadaan (dipakai halaman detail: "Perkembangan di Pengadaan")
            $snapshot = null;
            if ($sent) {
                $snapshot = ['number' => $procura, 'status' => $status, 'station' => $stasiun, 'funding' => [], 'purchase_orders' => [], 'handovers' => []];
                if (isset($x['dana'])) {
                    $snapshot['funding'][] = ['number' => sprintf($x['dana'][0], $created->format('ym')), 'status' => $x['dana'][1], 'status_label' => $x['dana'][2], 'amount' => round($total * 1.11)];
                }
                if (isset($x['po'])) {
                    $snapshot['purchase_orders'][] = ['number' => sprintf($x['po'][0], $created->format('ym')), 'vendor' => $x['po'][1], 'status' => $x['po'][2], 'expected_date' => $t->copy()->addDays($x['po'][3])->toDateString()];
                }
                if (isset($x['ho'])) {
                    $snapshot['handovers'][] = ['number' => sprintf($x['ho'][0], $created->format('ym')), 'status' => $x['ho'][1], 'handover_date' => $t->copy()->addDays($x['ho'][2])->toDateString(), 'receiver_name' => $x['ho'][3], 'notes' => 'Barang diserahkan lengkap beserta dokumen garansi.'];
                }
            }

            $p = PengajuanPengadaan::updateOrCreate(['nomor' => $nomor], [
                'procura_nomor' => $procura, 'judul' => $judul, 'kategori' => $kat, 'prioritas' => $prio, 'tanggal_dibutuhkan' => $t->copy()->addDays($need)->toDateString(), 'alasan' => $alasan,
                'nama_pemohon' => $pemohon, 'unit_pemohon' => $unit, 'total_estimasi' => $total, 'status' => $status, 'stasiun' => $stasiun, 'alasan_penolakan' => $x['tolak'] ?? null,
                'error_terakhir' => null, 'snapshot' => $snapshot, 'terkirim_at' => $sent ? $created->copy()->addMinutes(3) : null, 'disinkronkan_at' => $sent ? $t->copy()->subHours(2 + $k) : null, 'dibuat_oleh' => $u,
                'serah_terima_nomor' => isset($x['ho']) ? sprintf($x['ho'][0], $created->format('ym')) : null, 'serah_terima_status' => $x['ho'][1] ?? null,
                'serah_terima_tanggal' => isset($x['ho']) ? $t->copy()->addDays($x['ho'][2])->toDateString() : null, 'serah_terima_penerima' => $x['ho'][3] ?? null,
                'serah_terima_catatan' => isset($x['ho']) ? 'Barang diserahkan lengkap beserta dokumen garansi.' : null,
                'dikonfirmasi_at' => ($x['ho'][1] ?? null) === 'confirmed' ? $t->copy()->addDays($x['ho'][2])->setTime(11, 0) : null,
                'aset_dicatat_at' => ! empty($x['aset']) ? $t->copy()->addDays($x['ho'][2] + 1)->setTime(14, 0) : null,
            ]);
            DB::table('pengajuan_pengadaan')->where('id', $p->id)->update(['created_at' => $created, 'updated_at' => $t->copy()->subDays(max(0, $ago - 3 - $k))]);

            PengajuanPengadaanItem::where('pengajuan_id', $p->id)->delete();
            foreach ($items as [$nama, $spek, $jml, $satuan, $harga]) {
                PengajuanPengadaanItem::create(['pengajuan_id' => $p->id, 'nama' => $nama, 'spesifikasi' => $spek, 'jumlah' => $jml, 'satuan' => $satuan, 'harga_estimasi' => $harga]);
            }

            // buku laporan (kabar masuk dari Pengadaan + catatan keluar dari Inventaris)
            DB::table('laporan_pengadaan')->where('pengajuan_id', $p->id)->where('delivery_id', 'like', 'shw-%')->delete();
            $logs = $sent ? [['keluar', 'status', "Pengajuan {$nomor} dikirim ke Pengadaan", "Pengajuan \"{$judul}\" dikirim oleh {$pemohon}.", $ago]] : [];
            if (in_array($status, ['reviewing', 'sourcing', 'funding', 'ordered', 'received', 'handed_over', 'completed', 'rejected'], true)) {
                $logs[] = ['masuk', 'status', 'Pengajuan ditinjau Pengadaan', 'Tim Pengadaan menerima dan mulai meninjau pengajuan Anda.', $ago - 1];
            }
            if (in_array($status, ['funding', 'ordered', 'received', 'handed_over', 'completed'], true)) {
                $logs[] = ['masuk', 'pengiriman', 'Pengadaan mengajukan dana ke Keuangan', 'Penawaran vendor sudah dipilih dan pengajuan dana dikirim ke Keuangan.', $ago - 5];
            }
            if (in_array($status, ['ordered', 'received', 'handed_over', 'completed'], true)) {
                $logs[] = ['masuk', 'pesanan', 'Pesanan dikirim ke vendor', 'Dana cair dan PO sudah dikirim ke vendor.', $ago - 9];
            }
            if (in_array($status, ['received', 'handed_over', 'completed'], true)) {
                $logs[] = ['masuk', 'penerimaan', 'Barang/jasa sudah diterima Pengadaan', 'Pengadaan telah menerima barang/jasa dari vendor.', $ago - 14];
            }
            if (in_array($status, ['handed_over', 'completed'], true)) {
                $logs[] = ['masuk', 'serah_terima', 'Barang diserahkan ke Inventaris', 'Mohon konfirmasi penerimaan dan catat sebagai aset.', max(0, -($x['ho'][2] ?? 0))];
            }
            if ($status === 'completed') {
                $logs[] = ['keluar', 'konfirmasi', 'Serah terima dikonfirmasi', 'Barang sudah diterima dan dicatat sebagai aset Inventaris.', max(0, -($x['ho'][2] ?? 0))];
            }
            if ($status === 'rejected') {
                $logs[] = ['masuk', 'status', 'Pengajuan ditolak Pengadaan', $x['tolak'] ?? '', $ago - 3];
            }
            foreach ($logs as $n => [$arah, $jenis, $judulLog, $isi, $logAgo]) {
                DB::table('laporan_pengadaan')->insert([
                    'pengajuan_id' => $p->id, 'arah' => $arah, 'jenis' => $jenis, 'judul' => $judulLog, 'isi' => $isi, 'kunci' => "shw-{$p->id}-{$n}", 'delivery_id' => 'shw-'.$p->id.'-'.$n,
                    'dibaca_at' => ($arah === 'masuk' && $logAgo < 6 && $n > 0) ? null : $t->copy()->subDays(max(0, $logAgo))->setTime(15, 30), 'dibuat_oleh' => $arah === 'keluar' ? $u : null,
                    'created_at' => $t->copy()->subDays(max(0, $logAgo))->setTime(9 + ($n % 6), 10 + $n * 3),
                ]);
            }
        }
    }

    /* ------------------------------------------------------ bonus: ALAT */

    private function alat(): void
    {
        // nama, jenis, harga, jumlah, hari lalu
        $rows = [
            ['Mesin Penghancur Kertas Fellowes', 'Peralatan Kantor', 2750000, 2, 480],
            ['Mesin Laminating A3', 'Peralatan Kantor', 1850000, 1, 640],
            ['Stapler Heavy Duty Max', 'Peralatan Kantor', 425000, 10, 300],
            ['Tanaman Hias Artifisial Lobi (set)', 'Dekorasi', 1250000, 6, 360],
            ['Lukisan Dinding Pemandangan Minang', 'Dekorasi', 3800000, 3, 720],
            ['Vacuum Cleaner Industri Karcher', 'Kebersihan', 3450000, 2, 250],
            ['Mesin Pel Lantai Otomatis', 'Kebersihan', 5200000, 1, 190],
            ['Troli Kebersihan Multifungsi', 'Kebersihan', 1650000, 3, 410],
        ];
        foreach ($rows as [$nama, $jenis, $harga, $jumlah, $ago]) {
            DB::table('alat')->updateOrInsert(['nama' => $nama], ['jenis' => $jenis, 'tanggal_beli' => $this->date($ago), 'harga' => $harga, 'jumlah' => $jumlah, 'created_at' => $this->ago($ago), 'updated_at' => $this->ago($ago)]);
        }
    }

    /* ------------------------------------------------- bonus: PENGGUNAAN */

    private function penggunaan(): void
    {
        $rows = [
            ['Budi Santoso', 'Laptop Lenovo ThinkPad E14 Gen 5', 1, 40],
            ['Siti Rahmawati', 'Laptop Lenovo ThinkPad E14 Gen 5', 1, 38],
            ['Rizky Hidayat', 'Laptop ASUS Vivobook 14 (staf lapangan)', 1, 30],
            ['Agus Pratama', 'PC Desktop HP ProDesk 400 G9', 1, 26],
            ['Dewi Anggraini', 'PC Desktop HP ProDesk 400 G9', 1, 21],
            ['Maya Putri Utami', 'Telepon IP Panasonic KX-HDV130', 1, 14],
            ['Fajar Nugroho', 'Proyektor Epson EB-X06', 1, 8],
            ['Rina Kusuma Dewi', 'Printer Epson L5290 All-in-One', 1, 3],
        ];
        foreach ($rows as [$nama, $barang, $jumlah, $ago]) {
            $bid = DB::table('barang')->where('nama_barang', $barang)->value('id');
            if ($bid) {
                DB::table('penggunaan')->updateOrInsert(['nama_karyawan' => $nama, 'barang_id' => $bid], ['jumlah' => $jumlah, 'created_at' => $this->ago($ago), 'updated_at' => $this->ago($ago)]);
            }
        }
    }

    /* ------------------------------------------------ bonus: MAINTENANCE */

    private function maintenance(): void
    {
        $kend = fn (string $plat) => (string) DB::table('kendaraan')->where('plat_nomor', $plat)->value('id_kendaraan');
        $brg = fn (string $nama) => (string) DB::table('barang')->where('nama_barang', $nama)->value('id');
        $prb = fn (string $kode) => (string) DB::table('perabotan')->where('kode', $kode)->value('id');

        // tipe, item_id, nama, jenis, hari (negatif = lampau), status, catatan
        $rows = [
            ['kendaraan', $kend('BA 1234 AB'), 'Toyota Avanza 1.5 G - BA 1234 AB', 'Servis berkala 40.000 km', -35, 'Selesai', 'Ganti oli, filter, dan cek rem. Biaya Rp1.850.000.'],
            ['kendaraan', $kend('BA 9102 GH'), 'Daihatsu Gran Max Blind Van - BA 9102 GH', 'Perbaikan transmisi', -4, 'Terjadwal', 'Kendaraan di bengkel rekanan, estimasi selesai 5 hari.'],
            ['kendaraan', $kend('BA 2045 CD'), 'Toyota Kijang Innova Reborn - BA 2045 CD', 'Pajak tahunan & perpanjangan STNK', 12, 'Terjadwal', 'Siapkan BPKB dan KTP pemilik.'],
            ['gedung', 'GDG-003', 'Gudang Cabang Padang Panjang', 'Perbaikan atap bocor', 9, 'Terjadwal', 'Kondisi Rusak Ringan, ganti 14 lembar seng.'],
            ['gedung', 'GDG-001', 'Kantor Pusat', 'Pengecatan ulang fasad', -60, 'Selesai', 'Selesai tepat waktu, vendor Karya Bangun Sumatera.'],
            ['elektronik', $brg('Mesin Fotokopi Ricoh MP 2014AD'), 'Mesin Fotokopi Ricoh MP 2014AD', 'Servis berkala & ganti drum', 6, 'Terjadwal', 'Teknisi vendor datang pukul 10.00.'],
            ['elektronik', $brg('Proyektor Epson EB-X06'), 'Proyektor Epson EB-X06', 'Ganti lampu proyektor', -20, 'Selesai', 'Lampu diganti, jam pakai di-reset.'],
            ['perabotan', $prb('PRB-0009'), 'Kursi Plastik Lipat (rusak engsel)', 'Pemeriksaan kelayakan & penghapusan', 15, 'Terjadwal', 'Usulkan penghapusan aset bila tidak layak diperbaiki.'],
        ];
        foreach ($rows as [$tipe, $itemId, $nama, $jenis, $hari, $status, $catatan]) {
            if ($itemId === '') {
                continue;
            }
            $when = $this->today->copy()->addDays($hari);
            DB::table('jadwal_maintenance')->updateOrInsert(['tipe' => $tipe, 'item_id' => $itemId, 'jenis_maintenance' => $jenis], [
                'item_nama' => $nama, 'tanggal_jadwal' => $when->toDateString(), 'catatan' => $catatan, 'status' => $status, 'dibuat_oleh' => 'Rina Marlina',
                'created_at' => $when->copy()->subDays(10), 'selesai_at' => $status === 'Selesai' ? $when->copy()->addDay()->setTime(15, 0) : null,
            ]);
        }
    }
}
