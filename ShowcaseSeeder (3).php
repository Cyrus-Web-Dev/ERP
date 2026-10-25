<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BudgetService;
use App\Services\FixedAssetService;
use App\Services\JournalService;
use App\Services\PeriodService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * DATA SHOWCASE (demo) untuk MKP (Keuangan & Akuntansi).
 *
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * Semua transaksi dibuat lewat service asli aplikasi (SalesService, PurchaseService, FixedAssetService, JournalService),
 * jadi jurnal otomatis terbentuk SEIMBANG dan Buku Besar, Neraca, Laba Rugi, Arus Kas, dan Anggaran vs Realisasi ikut terisi.
 *
 * 5 halaman yang terisi:
 *   1. Invoice (penjualan)   -> 10 pelanggan + 10 invoice (lunas, sebagian, belum bayar, jatuh tempo lewat, void)
 *   2. Bill (pembelian)      -> 10 vendor + 10 bill + pembayaran
 *   3. Jurnal umum           -> 10 jurnal manual (posted, menunggu approval, ditolak, draf) + jurnal otomatis dari transaksi
 *   4. Aset tetap            -> 8 aset + depresiasi bulanan sampai bulan lalu
 *   5. Anggaran              -> 8 akun x 12 bulan tahun berjalan (anggaran vs realisasi)
 * Periode lama otomatis DITUTUP (menunjukkan fitur tutup buku); bulan berjalan dan bulan lalu tetap terbuka.
 *
 * Aman dijalankan ulang: bila data showcase sudah ada, transaksi dilewati.
 */
class ShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'Demo@12345';

    private JournalService $journals;

    private SalesService $sales;

    private PurchaseService $purchases;

    private FixedAssetService $assets;

    private User $mgr;

    private User $acct;

    private User $fin;

    /** @var array<string,int> kode akun => id */
    private array $acc = [];

    private CarbonImmutable $today;

    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, ChartOfAccountSeeder::class]);

        $this->today = CarbonImmutable::now();
        $this->acc = ChartOfAccount::pluck('id', 'code')->all();
        $this->company();
        $this->users();
        $customers = $this->customers();
        $vendors = $this->vendors();

        if (Invoice::where('description', 'like', '[SHW]%')->exists()) {
            $this->command?->warn('Transaksi showcase sudah pernah dibuat, dilewati (master data & anggaran tetap diperbarui).');
            $this->budgets();

            return;
        }

        $periods = app(PeriodService::class);
        $this->journals = app(JournalService::class);
        $this->sales = app(SalesService::class);
        $this->purchases = app(PurchaseService::class);
        $this->assets = app(FixedAssetService::class);

        try {
            $this->openingCapital();
            $this->invoices($customers);       // halaman 1
            $this->bills($vendors);            // halaman 2
            $this->fixedAssets();              // halaman 4
            $this->manualJournals();           // halaman 3
            $this->closeOldPeriods($periods);
        } catch (Throwable $e) {
            $this->clock(null);
            throw $e;
        }
        $this->clock(null);
        $this->budgets();                      // halaman 5

        $this->command?->info('Data showcase MKP selesai dibuat.');
        $this->command?->table(['Peran', 'Email', 'Kata sandi'], [
            ['Manager', $this->mgr->email, self::PASSWORD],
            ['Accounting', $this->acct->email, self::PASSWORD],
            ['Finance', $this->fin->email, self::PASSWORD],
        ]);
    }

    /* ----------------------------------------------------------- dasar */

    private function clock(?CarbonImmutable $d): void
    {
        $t = $d ? Carbon::instance($d->setTime(10, 15)->toDateTime()) : null;
        Carbon::setTestNow($t);
        CarbonImmutable::setTestNow($d?->setTime(10, 15));
    }

    private function monthStart(int $monthsAgo): CarbonImmutable
    {
        return $this->today->startOfMonth()->subMonths($monthsAgo);
    }

    /** Tanggal pada bulan ($monthsAgo bulan lalu) hari ke-$day, tidak melewati hari ini. */
    private function date(int $monthsAgo, int $day): CarbonImmutable
    {
        $d = $this->monthStart($monthsAgo);
        $d = $d->day(min($day, $d->daysInMonth));

        return $d->gt($this->today) ? $this->today : $d;
    }

    private function company(): void
    {
        $c = CompanyProfile::first() ?? new CompanyProfile();
        $c->fill(['name' => 'PT Nusantara Makmur Sejahtera', 'legal_name' => 'PT Nusantara Makmur Sejahtera', 'npwp' => '01.234.567.8-201.000',
            'address' => 'Jl. Jenderal Sudirman No. 88, Padang, Sumatera Barat', 'phone' => '0751-555000', 'email' => 'keuangan@nusantaramakmur.test', 'currency' => 'IDR'])->save();
    }

    private function users(): void
    {
        $defs = [
            'mgr' => ['Bambang Hartono', 'bambang.manager@contoh.test', 'admin', null, 'Manager Keuangan'],
            'acct' => ['Citra Maharani', 'citra.accounting@contoh.test', 'staff', 'accounting', 'Staf Akuntansi'],
            'fin' => ['Dedi Firmansyah', 'dedi.finance@contoh.test', 'staff', 'finance', 'Staf Keuangan'],
        ];
        foreach ($defs as $key => [$name, $email, $role, $position, $title]) {
            $u = User::where('email', $email)->first()
                ?? User::create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'email_verified_at' => now(), 'role_label' => $role,
                    'status' => 'active', 'position' => $position, 'job_title' => $title, 'department' => 'Divisi Keuangan']);
            $u->syncRoles([$role]);
            if ($position) {
                $u->syncPermissions(config("mkp.positions.{$position}.permissions", []));
            }
            $this->{$key} = $u;
        }
    }

    /** @return array<int,Customer> */
    private function customers(): array
    {
        $rows = [
            ['PT Andalas Retail Mandiri', 'Jl. Pemuda No. 12, Padang', '0751-700101', 'ap@andalasretail.example', 250000000],
            ['CV Minang Berkah Sejahtera', 'Jl. Khatib Sulaiman No. 5, Padang', '0751-700102', 'keuangan@minangberkah.example', 120000000],
            ['PT Samudra Logistik Nusantara', 'Jl. By Pass KM 8, Padang', '0751-700103', 'finance@samudralog.example', 300000000],
            ['Rumah Sakit Siti Hajar', 'Jl. Veteran No. 21, Padang', '0751-700104', 'pembelian@rssitihajar.example', 200000000],
            ['Universitas Bumi Andalas', 'Jl. Limau Manis, Padang', '0751-700105', 'bendahara@bumiandalas.example', 150000000],
            ['PT Pantai Indah Resort', 'Jl. Samudera No. 3, Pariaman', '0751-700106', 'accounting@pantaiindah.example', 180000000],
            ['Dinas Pendidikan Kota Padang', 'Jl. Jenderal Sudirman, Padang', '0751-700107', 'bendahara@disdikpadang.example', 100000000],
            ['Toko Elektronik Sinar Jaya', 'Pasar Raya Blok B, Padang', '0751-700108', 'sinarjaya@contoh.example', 60000000],
            ['PT Bukit Barisan Agro', 'Jl. Raya Solok KM 12', '0755-700109', 'finance@bukitbarisan.example', 220000000],
            ['Yayasan Pendidikan Harapan Bangsa', 'Jl. Adinegoro No. 40, Padang', '0751-700110', 'admin@harapanbangsa.example', 80000000],
        ];
        $out = [];
        foreach ($rows as $i => [$name, $addr, $phone, $email, $limit]) {
            $out[$i] = Customer::updateOrCreate(['code' => sprintf('CUS-%04d', $i + 1)], ['name' => $name, 'address' => $addr, 'phone' => $phone, 'email' => $email,
                'npwp' => sprintf('02.%03d.%03d.%d-201.000', 310 + $i * 11, 120 + $i * 17, $i % 9), 'credit_limit' => $limit, 'is_active' => true]);
        }

        return $out;
    }

    /** @return array<int,Vendor> */
    private function vendors(): array
    {
        $rows = [
            ['PT Properti Andalas Prima', 'Jl. Sudirman No. 90, Padang', '0751-800101', 'tagihan@propertiandalas.example'],
            ['PLN UP3 Padang', 'Jl. Moh. Hatta, Padang', '0751-800102', 'layanan@pln-padang.example'],
            ['PT Telkom Indonesia Witel Sumbar', 'Jl. Khatib Sulaiman, Padang', '0751-800103', 'billing@telkom-sumbar.example'],
            ['Prima ATK & Supplies', 'Jl. Pondok No. 7, Padang', '0751-555105', 'cs@primaatk.example'],
            ['Mitra Teknologi Nusantara', 'Jl. Proklamasi No. 15, Padang', '0751-555101', 'sales@mitratek.example'],
            ['CV Media Kreasi Digital', 'Jl. Ujung Gurun No. 4, Padang', '0751-800106', 'finance@mediakreasi.example'],
            ['Kantor Jasa Akuntan Publik Rahman & Rekan', 'Jl. Bundo Kanduang, Padang', '0751-800107', 'admin@kap-rahman.example'],
            ['PT Cahaya Bersih Servis', 'Jl. Gajah Mada, Padang', '0751-800108', 'ops@cahayabersih.example'],
            ['CV Sumber Pangan Sejati', 'Jl. Pasar Baru, Padang', '0751-800109', 'order@sumberpangan.example'],
            ['Bengkel & Dealer Andalas Auto', 'Jl. Raya Lubuk Begalung, Padang', '0751-800110', 'service@andalasauto.example'],
        ];
        $out = [];
        foreach ($rows as $i => [$name, $addr, $phone, $email]) {
            $out[$i] = Vendor::updateOrCreate(['code' => sprintf('VEN-%04d', $i + 1)], ['name' => $name, 'address' => $addr, 'phone' => $phone, 'email' => $email,
                'npwp' => sprintf('03.%03d.%03d.%d-201.000', 410 + $i * 9, 220 + $i * 19, $i % 9), 'is_active' => true]);
        }

        return $out;
    }

    /* -------------------------------------------------- modal awal */

    private function openingCapital(): void
    {
        $d = $this->monthStart(11)->addDay();
        $this->clock($d);
        $j = $this->journals->create([
            'journal_date' => $d->toDateString(), 'type' => 'umum', 'reference' => 'AKTA-001',
            'description' => 'Setoran modal awal pemegang saham', 'created_by' => $this->acct->id,
        ], [
            ['account_id' => $this->acc['1-1200'], 'debit' => 900000000, 'credit' => 0, 'description' => 'Penerimaan setoran modal ke rekening bank'],
            ['account_id' => $this->acc['3-1100'], 'debit' => 0, 'credit' => 900000000, 'description' => 'Modal saham disetor'],
        ]);
        $this->clock($d->addDay());
        $this->journals->approve($j, $this->mgr->id);
        $this->journals->post($j->fresh(), $this->mgr->id);
        $this->clock($d->addDays(2));
        $this->journals->create([
            'journal_date' => $d->addDays(2)->toDateString(), 'type' => 'umum', 'reference' => 'KAS-001', 'description' => 'Pembentukan kas kecil kantor', 'created_by' => $this->acct->id,
        ], [
            ['account_id' => $this->acc['1-1100'], 'debit' => 15000000, 'credit' => 0, 'description' => 'Kas kecil'],
            ['account_id' => $this->acc['1-1200'], 'debit' => 0, 'credit' => 15000000, 'description' => 'Penarikan dari bank'],
        ], true);
        $kas = Journal::where('reference', 'KAS-001')->first();
        $this->journals->post($kas, $this->acct->id);
    }

    /* ------------------------------------------------ 1. INVOICE */

    private function invoices(array $c): void
    {
        $rev = fn (string $code) => $this->acc[$code];
        $sale = $rev('4-1100');
        $svc = $rev('4-1200');
        $ar = $this->acc['1-1300'];
        $tax = $this->acc['2-1300'];
        $bank = $this->acc['1-1200'];

        // [pelanggan, bulan lalu, hari, tempo, item[[akun, uraian, qty, harga]], ppn?, pembayaran[[hari setelah terbit, bagian(1=lunas)]], void?]
        $rows = [
            [0, 8, 5, 30, [[$sale, 'Paket perangkat kantor (laptop, monitor, printer)', 12, 18500000], [$svc, 'Instalasi & konfigurasi jaringan', 1, 12000000]], true, [[26, 1]], false],
            [2, 7, 12, 30, [[$svc, 'Jasa manajemen gudang digital - 6 bulan', 6, 35000000]], true, [[33, 1]], false],
            [3, 6, 3, 45, [[$sale, 'Pengadaan komputer & printer ruang administrasi', 25, 9800000], [$svc, 'Pelatihan pengguna sistem', 2, 7500000]], true, [[40, 0.6], [62, 0.4]], false],
            [4, 5, 10, 30, [[$svc, 'Lisensi & implementasi sistem informasi akademik', 1, 185000000]], true, [[28, 0.5], [55, 0.5]], false],
            [5, 4, 8, 30, [[$sale, 'Perangkat Wi-Fi & CCTV area resort', 1, 96500000]], true, [[35, 1]], false],
            [6, 3, 15, 30, [[$sale, 'Laptop pembelajaran siswa', 40, 7200000]], false, [[44, 0.5]], false],
            [1, 2, 4, 14, [[$svc, 'Audit sistem inventaris & rekomendasi', 1, 28500000]], true, [], false],
            [8, 2, 20, 30, [[$svc, 'Pemeliharaan jaringan kebun (kontrak tahunan)', 1, 64000000], [$sale, 'Perangkat tambahan switch & access point', 8, 3250000]], true, [[31, 0.4]], false],
            [7, 1, 9, 14, [[$sale, 'Printer thermal & scanner barcode', 10, 2150000]], true, [], true],
            [9, 1, 18, 30, [[$svc, 'Pendampingan implementasi aplikasi administrasi sekolah', 1, 42000000]], false, [], false],
        ];

        foreach ($rows as $n => [$ci, $ago, $day, $term, $items, $ppn, $pays, $void]) {
            $d = $this->date($ago, $day);
            $this->clock($d);
            $subtotal = array_sum(array_map(fn ($i) => round($i[2] * $i[3], 2), $items));
            $inv = $this->sales->createInvoice([
                'customer_id' => $c[$ci]->id, 'invoice_date' => $d->toDateString(), 'due_date' => $d->addDays($term)->toDateString(),
                'description' => '[SHW] '.$items[0][1], 'receivable_account_id' => $ar,
                'tax_amount' => $ppn ? round($subtotal * 0.11, 2) : 0, 'tax_account_id' => $ppn ? $tax : null,
            ], array_map(fn ($i) => ['revenue_account_id' => $i[0], 'description' => $i[1], 'qty' => $i[2], 'price' => $i[3]], $items), $this->fin->id);

            foreach ($pays as [$after, $part]) {
                $pd = $d->addDays($after);
                if ($pd->gt($this->today)) {
                    continue;
                }
                $this->clock($pd);
                $inv = $inv->fresh();
                $amount = $part >= 1 ? $inv->remainingBalance() : round((float) $inv->total * $part, 2);
                $amount = min($amount, $inv->remainingBalance());
                if ($amount > 0) {
                    $this->sales->recordPayment($inv, ['payment_date' => $pd->toDateString(), 'amount' => $amount, 'cash_account_id' => $bank, 'note' => 'Transfer bank'.($part < 1 ? ' (termin)' : '')], $this->fin->id);
                }
            }
            if ($void) {
                $this->clock($d->addDays(2));
                $this->sales->voidInvoice($inv->fresh(), $this->mgr->id);
            }
        }
    }

    /* --------------------------------------------------- 2. BILL */

    private function bills(array $v): void
    {
        $ap = $this->acc['2-1100'];
        $tax = $this->acc['2-1300'];
        $bank = $this->acc['1-1200'];
        $sewa = $this->acc['5-1200'];
        $listrik = $this->acc['5-1300'];
        $perlengkapan = $this->acc['5-1400'];
        $pemasaran = $this->acc['5-1600'];
        $lain = $this->acc['5-1900'];
        $stok = $this->acc['1-1400'];

        // [vendor, bulan lalu, hari, tempo, item[[akun, uraian, qty, harga]], ppn?, pembayaran[[hari, bagian]]]
        $rows = [
            [0, 8, 1, 14, [[$sewa, 'Sewa kantor lantai 3 - triwulan IV', 3, 18000000]], false, [[10, 1]]],
            [1, 6, 5, 20, [[$listrik, 'Listrik & air kantor pusat', 1, 6250000]], false, [[15, 1]]],
            [3, 6, 14, 14, [[$perlengkapan, 'Kertas, toner, dan alat tulis kantor', 1, 8750000]], true, [[12, 1]]],
            [4, 5, 7, 30, [[$stok, 'Persediaan switch & access point untuk proyek', 20, 2850000]], true, [[25, 1]]],
            [5, 4, 11, 30, [[$pemasaran, 'Kampanye digital & materi promosi', 1, 14500000]], true, [[28, 0.5], [45, 0.5]]],
            [6, 3, 2, 30, [[$lain, 'Jasa audit laporan keuangan tahunan', 1, 45000000]], true, [[27, 1]]],
            [1, 3, 5, 20, [[$listrik, 'Listrik & air kantor pusat', 1, 6840000]], false, [[18, 1]]],
            [7, 2, 10, 14, [[$lain, 'Jasa kebersihan & perawatan gedung', 3, 4200000]], false, [[13, 1]]],
            [2, 1, 6, 20, [[$listrik, 'Internet dedicated & telepon kantor', 1, 5400000]], true, [[14, 1]]],
            [9, 1, 20, 21, [[$lain, 'Servis & perawatan kendaraan operasional', 1, 7850000]], false, []],
        ];

        foreach ($rows as [$vi, $ago, $day, $term, $items, $ppn, $pays]) {
            $d = $this->date($ago, $day);
            $this->clock($d);
            $subtotal = array_sum(array_map(fn ($i) => round($i[2] * $i[3], 2), $items));
            $bill = $this->purchases->createBill([
                'vendor_id' => $v[$vi]->id, 'bill_date' => $d->toDateString(), 'due_date' => $d->addDays($term)->toDateString(),
                'description' => '[SHW] '.$items[0][1], 'payable_account_id' => $ap,
                'tax_amount' => $ppn ? round($subtotal * 0.11, 2) : 0, 'tax_account_id' => $ppn ? $tax : null,
            ], array_map(fn ($i) => ['expense_account_id' => $i[0], 'description' => $i[1], 'qty' => $i[2], 'price' => $i[3]], $items), $this->fin->id);

            foreach ($pays as [$after, $part]) {
                $pd = $d->addDays($after);
                if ($pd->gt($this->today)) {
                    continue;
                }
                $this->clock($pd);
                $bill = $bill->fresh();
                $amount = $part >= 1 ? $bill->remainingBalance() : round((float) $bill->total * $part, 2);
                $amount = min($amount, $bill->remainingBalance());
                if ($amount > 0) {
                    $this->purchases->recordPayment($bill, ['payment_date' => $pd->toDateString(), 'amount' => $amount, 'cash_account_id' => $bank, 'note' => 'Transfer bank'], $this->fin->id);
                }
            }
        }
    }

    /* ------------------------------------------------ 4. ASET TETAP */

    private function fixedAssets(): void
    {
        $accum = $this->acc['1-2900'];
        $exp = $this->acc['5-1500'];
        $bank = $this->acc['1-1200'];
        $eq = $this->acc['1-2400'];
        $veh = $this->acc['1-2300'];
        $bld = $this->acc['1-2200'];

        // [nama, kategori, bulan lalu, hari, harga, residu, umur(bulan), akun aset]
        $rows = [
            ['Server rack Dell PowerEdge R650', 'Peralatan Kantor', 10, 6, 84500000, 5000000, 48, $eq],
            ['Mobil operasional Toyota Avanza', 'Kendaraan', 10, 14, 245000000, 40000000, 96, $veh],
            ['Renovasi & interior kantor lantai 3', 'Bangunan', 9, 20, 180000000, 0, 120, $bld],
            ['Laptop karyawan (set 10 unit)', 'Peralatan Kantor', 7, 3, 125000000, 10000000, 48, $eq],
            ['AC split central ruang server', 'Peralatan Kantor', 6, 9, 38500000, 2000000, 60, $eq],
            ['Sepeda motor operasional (2 unit)', 'Kendaraan', 5, 12, 46000000, 6000000, 60, $veh],
            ['Mesin fotokopi & printer produksi', 'Peralatan Kantor', 3, 5, 32750000, 2000000, 48, $eq],
            ['Genset 20 kVA', 'Peralatan Kantor', 2, 17, 64000000, 4000000, 96, $eq],
        ];

        foreach ($rows as [$name, $cat, $ago, $day, $cost, $salvage, $life, $assetAcc]) {
            $d = $this->date($ago, $day);
            $this->clock($d);
            $asset = $this->assets->acquire([
                'name' => $name, 'category' => $cat, 'acquisition_date' => $d->toDateString(), 'acquisition_cost' => $cost, 'salvage_value' => $salvage,
                'useful_life_months' => $life, 'asset_account_id' => $assetAcc, 'accumulated_depreciation_account_id' => $accum,
                'depreciation_expense_account_id' => $exp, 'payment_account_id' => $bank,
            ], $this->acct->id);

            // depresiasi tiap akhir bulan, mulai bulan perolehan sampai bulan lalu
            for ($k = $ago; $k >= 1; $k--) {
                $end = $this->monthStart($k)->endOfMonth();
                $this->clock($end->gt($this->today) ? $this->today : $end);
                $this->assets->runDepreciation($asset->fresh(), $end->toDateString(), $this->acct->id);
            }
        }
    }

    /* ------------------------------------------------ 3. JURNAL MANUAL */

    private function manualJournals(): void
    {
        $a = $this->acc;
        $jv = function (CarbonImmutable $d, string $ref, string $desc, array $lines, User $by) {
            $this->clock($d);

            return $this->journals->create(['journal_date' => $d->toDateString(), 'type' => 'umum', 'reference' => $ref, 'description' => $desc, 'created_by' => $by->id], $lines);
        };

        // 1-5. Beban gaji 5 bulan terakhir (di bawah batas approval 50 juta? tidak: total ~ 312 juta -> melalui approval Manager)
        foreach ([5, 4, 3, 2, 1] as $i => $ago) {
            $d = $this->date($ago, 28);
            $gaji = 276000000 + $i * 4500000;
            $j = $jv($d, 'GAJI-'.$d->format('Ym'), 'Beban gaji karyawan '.$d->locale('id')->translatedFormat('F Y'), [
                ['account_id' => $a['5-1100'], 'debit' => $gaji, 'credit' => 0, 'description' => 'Gaji & tunjangan karyawan'],
                ['account_id' => $a['1-1200'], 'debit' => 0, 'credit' => $gaji, 'description' => 'Transfer gaji via bank'],
            ], $this->acct);
            $this->clock($d->addDay());
            $this->journals->approve($j->fresh(), $this->mgr->id);
            $this->journals->post($j->fresh(), $this->mgr->id);
        }

        // 6. Sewa dibayar dimuka setahun (Manager menyetujui)
        $d = $this->date(3, 8);
        $j = $jv($d, 'SEWA-GD-01', 'Sewa gudang dibayar dimuka 12 bulan', [
            ['account_id' => $a['1-1600'], 'debit' => 96000000, 'credit' => 0, 'description' => 'Sewa gudang Jan-Des'],
            ['account_id' => $a['1-1200'], 'debit' => 0, 'credit' => 96000000, 'description' => 'Pembayaran via bank'],
        ], $this->acct);
        $this->clock($d->addDay());
        $this->journals->approve($j->fresh(), $this->mgr->id);
        $this->journals->post($j->fresh(), $this->mgr->id);

        // 7. Penyesuaian: pemakaian perlengkapan (di bawah batas -> bisa langsung diposting)
        $d = $this->monthStart(1)->endOfMonth();
        $this->clock($d);
        $j = $this->journals->create(['journal_date' => $d->toDateString(), 'type' => 'penyesuaian', 'reference' => 'ADJ-PERL', 'description' => 'Penyesuaian pemakaian perlengkapan kantor', 'created_by' => $this->acct->id], [
            ['account_id' => $a['5-1400'], 'debit' => 3250000, 'credit' => 0, 'description' => 'Perlengkapan terpakai'],
            ['account_id' => $a['1-1500'], 'debit' => 0, 'credit' => 3250000, 'description' => 'Berkurangnya perlengkapan'],
        ]);
        // 8. Penyesuaian: amortisasi sewa gudang bulan lalu
        $d2 = $this->monthStart(1)->endOfMonth();
        $j2 = $this->journals->create(['journal_date' => $d2->toDateString(), 'type' => 'penyesuaian', 'reference' => 'ADJ-SEWA', 'description' => 'Amortisasi sewa gudang bulan lalu', 'created_by' => $this->acct->id], [
            ['account_id' => $a['5-1200'], 'debit' => 8000000, 'credit' => 0, 'description' => 'Beban sewa gudang 1 bulan'],
            ['account_id' => $a['1-1600'], 'debit' => 0, 'credit' => 8000000, 'description' => 'Pengurangan beban dibayar dimuka'],
        ]);
        $this->journals->post($j->fresh(), $this->acct->id);
        $this->journals->post($j2->fresh(), $this->acct->id);

        // 9. Menunggu approval Manager (nilai besar, bulan berjalan)
        $d = $this->today->startOfMonth()->addDays(min(2, max(0, $this->today->day - 1)));
        $this->clock($d);
        $this->journals->create(['journal_date' => $d->toDateString(), 'type' => 'umum', 'reference' => 'PAJAK-PPH25', 'description' => 'Angsuran PPh Pasal 25 & penyesuaian utang pajak', 'created_by' => $this->acct->id], [
            ['account_id' => $a['2-1300'], 'debit' => 58500000, 'credit' => 0, 'description' => 'Pelunasan utang pajak'],
            ['account_id' => $a['1-1200'], 'debit' => 0, 'credit' => 58500000, 'description' => 'Setoran pajak via bank'],
        ]);

        // 10. Ditolak Manager
        $rej = $this->journals->create(['journal_date' => $d->toDateString(), 'type' => 'umum', 'reference' => 'PRV-112', 'description' => 'Pembayaran sewa kendaraan direksi (kurang dokumen pendukung)', 'created_by' => $this->acct->id], [
            ['account_id' => $a['5-1900'], 'debit' => 72000000, 'credit' => 0, 'description' => 'Sewa kendaraan'],
            ['account_id' => $a['1-1200'], 'debit' => 0, 'credit' => 72000000, 'description' => 'Pembayaran via bank'],
        ]);
        $this->journals->reject($rej->fresh(), $this->mgr->id, 'Mohon lampirkan perjanjian sewa dan faktur sebelum diajukan ulang.');

        // 11. Draf jurnal kecil
        $this->journals->create(['journal_date' => $d->toDateString(), 'type' => 'umum', 'reference' => 'REIMB-OKT', 'description' => 'Penggantian biaya perjalanan dinas (draf)', 'created_by' => $this->acct->id], [
            ['account_id' => $a['5-1900'], 'debit' => 2350000, 'credit' => 0, 'description' => 'Perjalanan dinas Jakarta'],
            ['account_id' => $a['1-1100'], 'debit' => 0, 'credit' => 2350000, 'description' => 'Pembayaran dari kas kecil'],
        ]);
    }

    /* ---------------------------------------------- tutup periode lama */

    private function closeOldPeriods(PeriodService $periods): void
    {
        // tutup bulan-bulan lama; 2 bulan terakhir (bulan lalu & berjalan) tetap terbuka
        for ($k = 11; $k >= 2; $k--) {
            $m = $this->monthStart($k);
            try {
                $periods->close($m->year, $m->month);
            } catch (Throwable $e) {
                // masih ada draf di bulan itu: biarkan terbuka
            }
        }
    }

    /* ------------------------------------------------ 5. ANGGARAN */

    private function budgets(): void
    {
        $svc = app(BudgetService::class);
        $year = $this->today->year;
        $plan = [
            '4-1100' => [95000000, 1.0], '4-1200' => [72000000, 1.0],
            '5-1100' => [290000000, 0.0], '5-1200' => [26000000, 0.0], '5-1300' => [12500000, 0.0],
            '5-1400' => [9000000, 0.0], '5-1500' => [18000000, 0.0], '5-1600' => [12000000, 0.0],
        ];
        $season = [1 => 0.85, 2 => 0.9, 3 => 0.95, 4 => 1.0, 5 => 1.0, 6 => 1.05, 7 => 1.05, 8 => 1.1, 9 => 1.1, 10 => 1.15, 11 => 1.2, 12 => 1.3];
        foreach ($plan as $code => [$base, $seasonal]) {
            if (! isset($this->acc[$code])) {
                continue;
            }
            for ($m = 1; $m <= 12; $m++) {
                $f = $seasonal > 0 ? $season[$m] : 1.0;
                $svc->setBudget($this->acc[$code], $year, $m, round($base * $f / 100000) * 100000);
            }
        }
    }
}
