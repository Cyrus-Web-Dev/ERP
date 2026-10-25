<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use App\Models\FundingRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProcurementService;
use App\Services\VendorMailer;
use App\Services\WebhookService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * DATA SHOWCASE (demo) untuk PROCURA.
 *
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * Data dibuat dengan MENJALANKAN ALUR ASLI aplikasi (ProcurementService), bukan insert mentah, jadi semua halaman
 * saling nyambung: lini masa tiap permintaan, buku laporan Meja Inventaris & Meja Keuangan, nomor dokumen, rating vendor,
 * jejak aktivitas. Tanggal dimundurkan bertahap (sampai ~12 minggu lalu) supaya riwayatnya terlihat natural.
 *
 * 5 halaman yang terisi:
 *   1. Vendor               -> 10 vendor (rating, termin bayar, 1 masuk daftar hitam)
 *   2. Permintaan           -> 13 permintaan di berbagai tahap (baru, cari penawaran, menunggu dana, ... , selesai)
 *   3. Meja Keuangan        -> 10 pengajuan dana (menunggu keputusan, revisi, cair, LPJ selesai)
 *   4. Purchase Order       -> 8 PO (draf, terkirim, diterima sebagian, diterima lengkap + pembayaran vendor)
 *   5. Katalog barang       -> 10 item katalog
 *
 * Email ke vendor dan webhook TIDAK dikirim (diganti stub), jadi aman dijalankan tanpa Gmail/jaringan.
 * Aman dijalankan ulang: bila data showcase sudah ada, bagian alur dilewati.
 */
class ShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'Demo@12345';

    private Carbon $base;

    private ProcurementService $svc;

    private User $mgr;

    private User $purch;

    private User $recv;

    /** @var array<string,Vendor> */
    private array $vendors = [];

    private int $financeSeq = 140;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $this->base = Carbon::now();
        $this->users();
        $this->vendors();
        $catalog = $this->catalog();

        if (PurchaseRequest::where('external_ref', 'like', 'INV-SHW-%')->exists()) {
            $this->command?->warn('Alur showcase sudah pernah dibuat, bagian permintaan dilewati (vendor & katalog tetap diperbarui).');

            return;
        }

        $this->svc = $this->makeService();

        try {
            foreach ($this->scenarios($catalog) as $spec) {
                $this->process($spec);
            }
            $this->notes();
        } catch (Throwable $e) {
            $this->resetClock();
            throw $e;
        }
        $this->resetClock();

        $this->command?->info('Data showcase PROCURA selesai dibuat.');
        $this->command?->table(['Peran', 'Email', 'Kata sandi'], [
            ['Manager', $this->mgr->email, self::PASSWORD],
            ['Purchasing', $this->purch->email, self::PASSWORD],
            ['Penerimaan Barang', $this->recv->email, self::PASSWORD],
        ]);
    }

    /* ------------------------------------------------------------- dasar */

    private function makeService(): ProcurementService
    {
        // Stub: tidak ada email ke vendor / panggilan HTTP keluar saat seeding
        $webhooks = new class extends WebhookService
        {
            public function dispatch(string $event, array $data): void
            {
            }
        };
        $mailer = new class extends VendorMailer
        {
            public function __construct()
            {
            }

            public function sendPurchaseOrder(PurchaseOrder $po): array
            {
                return [true, null];
            }
        };

        return new ProcurementService($webhooks, $mailer);
    }

    private function users(): void
    {
        $defs = [
            'mgr' => ['Hendra Kusuma', 'hendra.manager@contoh.test', 'admin', null, 'Manager Pengadaan'],
            'purch' => ['Dina Pratiwi', 'dina.purchasing@contoh.test', 'staff', 'purchasing', 'Purchasing'],
            'recv' => ['Fitri Handayani', 'fitri.gudang@contoh.test', 'staff', 'receiving', 'Staf Penerimaan Barang'],
        ];
        foreach ($defs as $key => [$name, $email, $role, $position, $title]) {
            $u = User::where('email', $email)->first();
            if (! $u) {
                $u = User::create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'email_verified_at' => now(), 'role_label' => $role,
                    'status' => 'active', 'position' => $position, 'job_title' => $title, 'department' => 'Divisi Pengadaan']);
            }
            $u->syncRoles([$role]);
            if ($position) {
                $perms = config("procura.positions.{$position}.permissions", []);
                // staf Purchasing juga boleh mengajukan dana; staf Penerimaan juga mencatat pembayaran & LPJ
                $extra = $position === 'purchasing' ? ['funding.submit'] : ['payment.manage'];
                $u->syncPermissions(array_merge($perms, $extra));
            }
            $this->{$key} = $u;
        }
    }

    private function vendors(): void
    {
        // kode, nama, kategori, PIC, email, telepon, termin, rating awal, jumlah penilaian awal, status, bank, rekening
        $rows = [
            ['Mitra Teknologi Nusantara', 'elektronik', 'Bapak Rahmat', 'sales@mitratek.example', '0751-555101', 14, 4.60, 11, 'active', 'BCA', '0123456701'],
            ['CV Sinar Perabot Jaya', 'perabotan', 'Ibu Lestari', 'order@sinarperabot.example', '0751-555102', 7, 4.30, 8, 'active', 'Mandiri', '1130012345'],
            ['PT Andalas Otomotif', 'kendaraan', 'Bapak Zulkifli', 'fleet@andalasoto.example', '0751-555103', 30, 4.80, 5, 'active', 'BNI', '0098765432'],
            ['Karya Bangun Sumatera', 'gedung', 'Bapak Hendra', 'proyek@karyabangun.example', '0751-555104', 30, 4.10, 4, 'active', 'BRI', '0345678901'],
            ['Prima ATK & Supplies', 'habis_pakai', 'Ibu Maya', 'cs@primaatk.example', '0751-555105', 7, 4.70, 21, 'active', 'BCA', '0123499902'],
            ['Solusi Servis Prima', 'jasa', 'Bapak Doni', 'kontak@solusiservis.example', '0751-555106', 14, 4.40, 9, 'active', 'Mandiri', '1130077788'],
            ['Bina Komputer Padang', 'elektronik', 'Ibu Wulan', 'penjualan@binakomputer.example', '0751-555107', 14, 4.20, 13, 'active', 'BRI', '0345600012'],
            ['Mebel Minang Jaya', 'perabotan', 'Bapak Irwan', 'info@mebelminang.example', '0751-555108', 14, 4.50, 6, 'active', 'BNI', '0098700045'],
            ['Cool Service Andalas', 'jasa', 'Bapak Yusuf', 'admin@coolservice.example', '0751-555109', 14, 3.90, 7, 'active', 'BCA', '0123455567'],
            ['Sumber Rezeki Logistik', 'habis_pakai', 'Bapak Anton', 'cs@sumberrezeki.example', '0751-555110', 7, 2.10, 3, 'blacklisted', 'Mandiri', '1130000099'],
        ];
        foreach ($rows as $i => [$name, $cat, $pic, $email, $phone, $terms, $rating, $count, $status, $bank, $acct]) {
            $code = sprintf('VND-%04d', $i + 1);
            $v = Vendor::where('code', $code)->first();
            $data = ['name' => $name, 'category' => $cat, 'contact_person' => $pic, 'email' => $email, 'phone' => $phone, 'address' => 'Padang, Sumatera Barat',
                'npwp' => sprintf('01.%03d.%03d.%d-201.000', 100 + $i * 7, 200 + $i * 13, $i % 9), 'bank_name' => $bank, 'bank_account' => $acct, 'bank_holder' => $name,
                'payment_terms_days' => $terms, 'status' => $status,
                'notes' => $status === 'blacklisted' ? 'Masuk daftar hitam: pengiriman terlambat 3 kali berturut-turut dan barang tidak sesuai spesifikasi.' : null];
            if ($v) {
                $v->update($data); // rating dibiarkan agar riwayat penilaian tidak tertimpa
            } else {
                $v = Vendor::create($data + ['code' => $code, 'rating' => $rating, 'rating_count' => $count]);
            }
            $this->vendors[$code] = $v;
        }
    }

    /** @return array<string,CatalogItem> sku => item */
    private function catalog(): array
    {
        $rows = [
            ['ELK-001', 'Laptop kerja 14" (i5/16GB/512GB)', 'elektronik', 'unit', 12500000, 'Untuk staf kantor, garansi resmi 2 tahun.'],
            ['ELK-002', 'Monitor 24" IPS', 'elektronik', 'unit', 1850000, 'Full HD, HDMI + DisplayPort.'],
            ['ELK-003', 'Printer laser multifungsi', 'elektronik', 'unit', 3900000, 'Cetak, scan, fotokopi; duplex otomatis.'],
            ['ELK-004', 'AC split 2 PK inverter', 'elektronik', 'unit', 7200000, 'Belum termasuk instalasi.'],
            ['PRB-001', 'Kursi kerja ergonomis', 'perabotan', 'unit', 1450000, 'Sandaran jaring, tinggi dapat diatur.'],
            ['PRB-002', 'Meja kerja 140x70 cm', 'perabotan', 'unit', 1750000, 'Rangka besi, top HPL.'],
            ['NEL-001', 'Lemari arsip besi 4 laci', 'non_elektronik', 'unit', 2300000, 'Kunci centralized.'],
            ['KDR-001', 'Sepeda motor operasional 150cc', 'kendaraan', 'unit', 22000000, 'Termasuk STNK & BPKB.'],
            ['HBP-001', 'Kertas HVS A4 80gr (rim)', 'habis_pakai', 'rim', 52000, '500 lembar per rim.'],
            ['JSA-001', 'Servis berkala AC (per unit)', 'jasa', 'unit', 185000, 'Cuci unit indoor/outdoor + cek freon.'],
        ];
        $out = [];
        foreach ($rows as [$sku, $name, $cat, $unit, $price, $desc]) {
            $out[$sku] = CatalogItem::updateOrCreate(['sku' => $sku], ['name' => $name, 'category' => $cat, 'unit' => $unit, 'reference_price' => $price, 'description' => $desc, 'is_active' => true]);
        }

        return $out;
    }

    /* -------------------------------------------------------- skenario */

    /**
     * Tiap skenario: items, quotes, hari-mundur tiap tahap, dan sampai tahap mana alur dijalankan ('until').
     * Urutan tahap: create, review, quote, select, fund, internal, decide, disburse, po, send, receive, pay, settle, handover, confirm
     */
    private function scenarios(array $c): array
    {
        $id = fn (string $sku) => $c[$sku]->id;

        return [
            // 1. SELESAI PENUH (dana >= Rp25 juta -> persetujuan internal Manager dulu)
            [
                'title' => 'Penggantian laptop staf Gudang & Aset', 'category' => 'elektronik', 'requester' => 'Rina Marlina', 'unit' => 'Divisi Inventaris', 'priority' => 'high',
                'justification' => 'Tiga laptop lama sudah tidak sanggup menjalankan aplikasi inventaris dengan lancar.', 'ext' => 'INV-SHW-001', 'needed' => -60,
                'items' => [[$id('ELK-001'), 'Laptop kerja 14"', 'i5 gen-13, RAM 16GB, SSD 512GB', 3, 'unit', 12500000]],
                'quotes' => [['VND-0001', [11900000], 7, 'Transfer 14 hari setelah barang diterima'], ['VND-0007', [12400000], 10, 'Transfer 14 hari'], ['VND-0002', [12150000], 12, 'Transfer 7 hari']],
                'select' => 0, 'reason' => 'Harga terendah, garansi resmi, dan pengiriman tercepat.',
                'days' => ['create' => 84, 'review' => 83, 'quote' => 81, 'select' => 80, 'fund' => 80, 'internal' => 79, 'decide' => 78, 'disburse' => 77, 'po' => 76, 'send' => 76, 'receive' => 65, 'pay' => 63, 'settle' => 62, 'handover' => 61, 'confirm' => 60],
                'score' => 5, 'until' => 'confirm',
            ],
            // 2. SELESAI PENUH (< Rp25 juta -> langsung ke Keuangan)
            [
                'title' => 'Kursi & meja ruang arsip baru', 'category' => 'perabotan', 'requester' => 'Andi Saputra', 'unit' => 'Divisi Inventaris', 'priority' => 'normal',
                'justification' => 'Ruang arsip lantai 2 belum punya perabot kerja.', 'ext' => 'INV-SHW-002', 'needed' => -45,
                'items' => [[$id('PRB-001'), 'Kursi kerja ergonomis', null, 4, 'unit', 1450000], [$id('PRB-002'), 'Meja kerja 140x70 cm', null, 4, 'unit', 1750000]],
                'quotes' => [['VND-0002', [1380000, 1680000], 7, 'Transfer 7 hari'], ['VND-0008', [1420000, 1620000], 10, 'Transfer 14 hari']],
                'select' => 0, 'reason' => 'Total paling murah dan stok tersedia di gudang vendor.',
                'days' => ['create' => 70, 'review' => 69, 'quote' => 68, 'select' => 67, 'fund' => 66, 'decide' => 65, 'disburse' => 64, 'po' => 63, 'send' => 63, 'receive' => 55, 'pay' => 54, 'settle' => 53, 'handover' => 52, 'confirm' => 51],
                'score' => 4, 'until' => 'confirm',
            ],
            // 3. SELESAI PENUH - habis pakai
            [
                'title' => 'Kebutuhan ATK triwulan III', 'category' => 'habis_pakai', 'requester' => 'Rina Marlina', 'unit' => 'Divisi Inventaris', 'priority' => 'low',
                'justification' => 'Stok kertas dan toner gudang menipis.', 'ext' => 'INV-SHW-003', 'needed' => -35,
                'items' => [[$id('HBP-001'), 'Kertas HVS A4 80gr', null, 60, 'rim', 52000], [null, 'Toner printer laser (hitam)', 'Kompatibel seri printer kantor', 6, 'unit', 850000]],
                'quotes' => [['VND-0005', [49500, 820000], 3, 'Transfer 7 hari'], ['VND-0007', [53000, 790000], 5, 'Transfer 14 hari']],
                'select' => 0, 'reason' => 'Harga kertas lebih murah dan pengiriman 3 hari.',
                'days' => ['create' => 52, 'review' => 52, 'quote' => 51, 'select' => 50, 'fund' => 50, 'decide' => 49, 'disburse' => 48, 'po' => 47, 'send' => 47, 'receive' => 44, 'pay' => 43, 'settle' => 42, 'handover' => 41, 'confirm' => 41],
                'score' => 5, 'until' => 'confirm',
            ],
            // 4. SELESAI PENUH - jasa
            [
                'title' => 'Servis berkala AC gedung utama', 'category' => 'jasa', 'requester' => 'Rina Marlina', 'unit' => 'Divisi Inventaris', 'priority' => 'urgent',
                'justification' => 'Beberapa unit AC mulai kurang dingin menjelang musim panas.', 'ext' => 'INV-SHW-004', 'needed' => -28,
                'items' => [[$id('JSA-001'), 'Servis berkala AC', 'Cuci unit + cek freon', 18, 'unit', 185000]],
                'quotes' => [['VND-0006', [175000], 3, 'Transfer 14 hari'], ['VND-0009', [168000], 5, 'Transfer 14 hari']],
                'select' => 0, 'reason' => 'Teknisi bersertifikat, garansi pengerjaan 30 hari (selisih harga kecil).',
                'days' => ['create' => 40, 'review' => 40, 'quote' => 39, 'select' => 39, 'fund' => 38, 'decide' => 38, 'disburse' => 37, 'po' => 36, 'send' => 36, 'receive' => 30, 'pay' => 29, 'settle' => 28, 'handover' => 27, 'confirm' => 27],
                'score' => 4, 'until' => 'confirm',
            ],
            // 5. SUDAH DISERAHKAN, LPJ BELUM (dana masih "cair") -> halaman LPJ
            [
                'title' => 'Sepeda motor operasional tim lapangan', 'category' => 'kendaraan', 'requester' => 'Zulfahmi', 'unit' => 'Divisi Operasional', 'priority' => 'normal',
                'justification' => 'Menunjang mobilitas tim survei dan pengantaran dokumen.', 'ext' => 'INV-SHW-005', 'needed' => -10,
                'items' => [[$id('KDR-001'), 'Sepeda motor operasional 150cc', 'Warna hitam, termasuk STNK/BPKB', 3, 'unit', 22000000]],
                'quotes' => [['VND-0003', [21400000], 14, 'Transfer 30 hari']],
                'select' => 0, 'reason' => 'Dealer resmi tunggal di wilayah Padang; harga di bawah referensi katalog.',
                'days' => ['create' => 34, 'review' => 33, 'quote' => 32, 'select' => 31, 'fund' => 30, 'internal' => 29, 'decide' => 27, 'disburse' => 26, 'po' => 25, 'send' => 25, 'receive' => 12, 'pay' => 10, 'handover' => 7],
                'score' => 5, 'until' => 'handover',
            ],
            // 6. DITERIMA SEBAGIAN
            [
                'title' => 'Monitor & printer ruang administrasi', 'category' => 'elektronik', 'requester' => 'Andi Saputra', 'unit' => 'Divisi Inventaris', 'priority' => 'normal',
                'justification' => 'Menambah perangkat untuk dua staf baru.', 'ext' => 'INV-SHW-006', 'needed' => -3,
                'items' => [[$id('ELK-002'), 'Monitor 24" IPS', null, 5, 'unit', 1850000], [$id('ELK-003'), 'Printer laser multifungsi', null, 2, 'unit', 3900000]],
                'quotes' => [['VND-0001', [1790000, 3750000], 7, 'Transfer 14 hari'], ['VND-0007', [1820000, 3690000], 9, 'Transfer 14 hari']],
                'select' => 0, 'reason' => 'Total terendah dan garansi on-site.',
                'days' => ['create' => 24, 'review' => 23, 'quote' => 22, 'select' => 21, 'fund' => 20, 'decide' => 19, 'disburse' => 18, 'po' => 17, 'send' => 17, 'receive' => 8],
                'receive_qty' => [5, 1], 'score' => 4, 'until' => 'receive',
            ],
            // 7. PO TERKIRIM, MENUNGGU BARANG
            [
                'title' => 'Renovasi ruang arsip (partisi, keramik, cat)', 'category' => 'gedung', 'requester' => 'Zulfahmi', 'unit' => 'Divisi Operasional', 'priority' => 'high',
                'justification' => 'Ruang arsip lama lembap dan perlu dibagi menjadi dua area.', 'ext' => 'INV-SHW-007', 'needed' => 20,
                'items' => [[null, 'Renovasi ruang arsip 48 m²', 'Partisi gypsum, keramik 60x60, cat anti-jamur, instalasi lampu', 1, 'paket', 48000000]],
                'quotes' => [['VND-0004', [46500000], 21, 'DP 30%, pelunasan setelah serah terima']],
                'select' => 0, 'reason' => 'Satu-satunya kontraktor yang melakukan survei lokasi; harga di bawah RAB internal.',
                'days' => ['create' => 22, 'review' => 21, 'quote' => 19, 'select' => 18, 'fund' => 17, 'internal' => 16, 'decide' => 14, 'disburse' => 13, 'po' => 12, 'send' => 12],
                'until' => 'send',
            ],
            // 8. DANA CAIR, PO MASIH DRAF
            [
                'title' => 'Perabot ruang rapat direksi', 'category' => 'perabotan', 'requester' => 'Rina Marlina', 'unit' => 'Divisi Inventaris', 'priority' => 'normal',
                'justification' => 'Meja dan kursi rapat lama rusak.', 'ext' => 'INV-SHW-008', 'needed' => 14,
                'items' => [[null, 'Meja rapat 3 meter', 'Kayu jati, finishing melamin', 1, 'unit', 6500000], [null, 'Kursi rapat', 'Bahan kulit sintetis, roda', 10, 'unit', 1200000]],
                'quotes' => [['VND-0008', [6200000, 1150000], 14, 'Transfer 14 hari'], ['VND-0002', [6800000, 1180000], 10, 'Transfer 7 hari']],
                'select' => 0, 'reason' => 'Harga lebih rendah dan desain sesuai contoh.',
                'days' => ['create' => 14, 'review' => 13, 'quote' => 12, 'select' => 11, 'fund' => 10, 'decide' => 8, 'disburse' => 7, 'po' => 6],
                'until' => 'po',
            ],
            // 9. MENUNGGU KEPUTUSAN KEUANGAN
            [
                'title' => 'Switch jaringan & rak server', 'category' => 'elektronik', 'requester' => 'Hari Prasetyo', 'unit' => 'Divisi TI', 'priority' => 'high',
                'justification' => 'Jaringan lantai 2 sering putus karena switch lama kelebihan beban.', 'ext' => 'INV-SHW-009', 'needed' => 12,
                'items' => [[null, 'Switch 24 port managed', 'Gigabit, 4x SFP', 2, 'unit', 4800000], [null, 'Rak server 42U', 'Termasuk PDU dan kipas', 1, 'unit', 9000000]],
                'quotes' => [['VND-0001', [4650000, 8750000], 7, 'Transfer 14 hari'], ['VND-0007', [4900000, 8500000], 7, 'Transfer 14 hari']],
                'select' => 0, 'reason' => 'Total sedikit lebih rendah dan switch bergaransi 3 tahun.',
                'days' => ['create' => 7, 'review' => 6, 'quote' => 5, 'select' => 4, 'fund' => 3],
                'until' => 'fund',
            ],
            // 10. MENUNGGU PERSETUJUAN INTERNAL MANAGER (nilai besar)
            [
                'title' => 'Mobil dinas operasional', 'category' => 'kendaraan', 'requester' => 'Zulfahmi', 'unit' => 'Divisi Operasional', 'priority' => 'normal',
                'justification' => 'Kendaraan dinas lama sudah 9 tahun dan sering turun mesin.', 'ext' => 'INV-SHW-010', 'needed' => 40,
                'items' => [[null, 'Mobil MPV 1.500 cc', 'Transmisi otomatis, warna putih', 1, 'unit', 285000000]],
                'quotes' => [['VND-0003', [279500000], 30, 'Transfer 30 hari']],
                'select' => 0, 'reason' => 'Dealer resmi, harga on-the-road sudah termasuk pajak tahun pertama.',
                'days' => ['create' => 6, 'review' => 5, 'quote' => 4, 'select' => 3, 'fund' => 2],
                'until' => 'fund',
            ],
            // 11. KEUANGAN MEMINTA REVISI
            [
                'title' => 'Lemari arsip dokumen keuangan', 'category' => 'non_elektronik', 'requester' => 'Andi Saputra', 'unit' => 'Divisi Inventaris', 'priority' => 'low',
                'justification' => 'Dokumen kontrak dan faktur belum tersimpan rapi.', 'ext' => 'INV-SHW-011', 'needed' => 25,
                'items' => [[$id('NEL-001'), 'Lemari arsip besi 4 laci', null, 6, 'unit', 2300000]],
                'quotes' => [['VND-0002', [2250000], 7, 'Transfer 7 hari']],
                'select' => 0, 'reason' => 'Stok tersedia dan harga di bawah referensi katalog.',
                'days' => ['create' => 9, 'review' => 8, 'quote' => 7, 'select' => 6, 'fund' => 5, 'decide' => 4],
                'decide_mode' => 'revision', 'decide_note' => 'Mohon lampirkan minimal dua penawaran pembanding sebelum dana disetujui.', 'until' => 'decide',
            ],
            // 12. SEDANG CARI PENAWARAN
            [
                'title' => 'Pengadaan AC split ruang server', 'category' => 'elektronik', 'requester' => 'Hari Prasetyo', 'unit' => 'Divisi TI', 'priority' => 'urgent',
                'justification' => 'Suhu ruang server sering melewati 30°C.', 'ext' => 'INV-SHW-012', 'needed' => 8,
                'items' => [[$id('ELK-004'), 'AC split 2 PK inverter', 'Termasuk instalasi & pipa 5 meter', 4, 'unit', 7200000]],
                'quotes' => [['VND-0001', [7050000], 5, 'Transfer 14 hari'], ['VND-0007', [7150000], 6, 'Transfer 14 hari']],
                'days' => ['create' => 3, 'review' => 3, 'quote' => 2],
                'until' => 'quote',
            ],
            // 13. BARU MASUK
            [
                'title' => 'Kertas HVS A4 untuk kebutuhan akhir tahun', 'category' => 'habis_pakai', 'requester' => 'Rina Marlina', 'unit' => 'Divisi Inventaris', 'priority' => 'normal',
                'justification' => 'Persiapan laporan dan audit akhir tahun.', 'ext' => 'INV-SHW-013', 'needed' => 15,
                'items' => [[$id('HBP-001'), 'Kertas HVS A4 80gr', null, 80, 'rim', 52000]],
                'quotes' => [],
                'days' => ['create' => 0],
                'until' => 'create',
            ],
        ];
    }

    /* ----------------------------------------------------------- alur */

    private function resetClock(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    private function at(int $daysAgo, string $time): void
    {
        $t = $this->base->copy()->subDays($daysAgo)->setTimeFromTimeString($time);
        Carbon::setTestNow($t);
        CarbonImmutable::setTestNow($t);
    }

    private function process(array $s): void
    {
        $order = ['create', 'review', 'quote', 'select', 'fund', 'internal', 'decide', 'disburse', 'po', 'send', 'receive', 'pay', 'settle', 'handover', 'confirm'];
        $until = array_search($s['until'], $order, true);
        $d = $s['days'];
        $reached = fn (string $stage) => array_search($stage, $order, true) <= $until;

        // 1. permintaan masuk dari Inventaris (via API)
        $this->at($d['create'], '08:40');
        $pr = $this->svc->createRequest([
            'title' => $s['title'], 'category' => $s['category'], 'requester_name' => $s['requester'], 'requester_unit' => $s['unit'], 'justification' => $s['justification'],
            'priority' => $s['priority'], 'needed_date' => $this->base->copy()->addDays($s['needed'])->toDateString(), 'external_ref' => $s['ext'],
            'items' => array_map(fn ($i) => ['catalog_item_id' => $i[0], 'name' => $i[1], 'specification' => $i[2], 'quantity' => $i[3], 'unit' => $i[4], 'estimated_price' => $i[5]], $s['items']),
        ], 'inventaris', null, 'Sistem Inventaris (API)');

        if (! $reached('review') || $s['until'] === 'create') {
            return;
        }
        $this->at($d['review'], '09:15');
        $this->svc->startReview($pr, $this->purch);

        if (! $reached('quote') || ! $s['quotes']) {
            return;
        }
        $pr->load('items');
        $quotes = [];
        foreach ($s['quotes'] as $k => [$code, $prices, $lead, $terms]) {
            $this->at($d['quote'], sprintf('%02d:%02d', 10 + $k, 20 + $k * 7));
            $map = [];
            foreach ($pr->items->values() as $idx => $item) {
                $map[$item->id] = $prices[$idx];
            }
            $quotes[$k] = $this->svc->addQuotation($pr, [
                'vendor_id' => $this->vendors[$code]->id, 'vendor_ref' => 'PNW/'.$this->base->copy()->subDays($d['quote'])->format('ym').'/'.str_pad((string) (31 + $k * 8), 3, '0', STR_PAD_LEFT),
                'quoted_date' => $this->base->copy()->subDays($d['quote'])->toDateString(), 'valid_until' => $this->base->copy()->subDays($d['quote'])->addDays(21)->toDateString(),
                'lead_time_days' => $lead, 'payment_terms' => $terms, 'prices' => $map,
                'notes' => $k === 0 ? 'Harga sudah termasuk ongkos kirim ke Padang.' : null,
            ], $this->purch);
        }

        if (! $reached('select') || ! isset($s['select'])) {
            return;
        }
        $this->at($d['select'], '13:05');
        $this->svc->selectQuotation($pr->fresh(), $quotes[$s['select']]->fresh(), $s['reason'], $this->purch);

        if (! $reached('fund')) {
            return;
        }
        $this->at($d['fund'], '14:10');
        $pr = $pr->fresh();
        $amount = $this->svc->suggestedFundingAmount($pr);
        $f = $this->svc->submitFunding($pr, $amount, 'Pembelian '.mb_strtolower($s['title']).' sesuai penawaran terpilih (termasuk PPN 11%).', $this->purch);

        if ($f->status === 'pending_internal') {
            if (! $reached('internal') || ! isset($d['internal'])) {
                return;
            }
            $this->at($d['internal'], '10:10');
            $this->svc->approveInternal($f->fresh(), $this->mgr);
            $f = $f->fresh();
        }

        if (! $reached('decide')) {
            return;
        }
        $this->at($d['decide'], '14:20');
        $ref = sprintf('KEU-APP-%s-%04d', $this->base->copy()->subDays($d['decide'])->format('ym'), $this->financeSeq++);
        if (($s['decide_mode'] ?? 'approve') === 'revision') {
            $this->svc->financeDecide($f->fresh(), 'revision', null, $s['decide_note'], 'Dewi Kurniasih (Keuangan)', null, $ref, 'Sistem Keuangan (API)');

            return;
        }
        $this->svc->financeDecide($f->fresh(), 'approve', null, 'Disetujui sesuai nominal pengajuan.', 'Dewi Kurniasih (Keuangan)', null, $ref, 'Sistem Keuangan (API)');

        if (! $reached('disburse')) {
            return;
        }
        $this->at($d['disburse'], '11:45');
        $f = $f->fresh();
        $this->svc->disburse($f, (float) $f->approved_amount, 'transfer', 'TRF-'.$this->base->copy()->subDays($d['disburse'])->format('ymd').'-'.str_pad((string) $f->id, 3, '0', STR_PAD_LEFT), 'Kasir Keuangan', null, 'Sistem Keuangan (API)');

        if (! $reached('po')) {
            return;
        }
        $this->at($d['po'], '09:00');
        $deadline = $quotes[$s['select']]->lead_time_days;
        $po = $this->svc->createPurchaseOrder($pr->fresh(), [
            'order_date' => $this->base->copy()->subDays($d['po'])->toDateString(), 'expected_date' => $this->base->copy()->subDays($d['po'])->addDays($deadline)->toDateString(),
            'delivery_address' => 'Kantor Pusat, Jl. Jenderal Sudirman No. 88, Padang', 'notes' => 'Mohon sertakan surat jalan dan faktur pajak saat pengiriman.',
        ], $this->purch);

        if (! $reached('send')) {
            return;
        }
        $this->at($d['send'], '15:30');
        $this->svc->sendPurchaseOrder($po->fresh(), $this->purch);

        if (! $reached('receive')) {
            return;
        }
        $this->at($d['receive'], '10:25');
        $po = $po->fresh('items');
        $lines = [];
        foreach ($po->items as $idx => $item) {
            $qty = isset($s['receive_qty']) ? (float) $s['receive_qty'][$idx] : (float) $item->quantity;
            $lines[] = ['po_item_id' => $item->id, 'quantity_received' => $qty, 'quantity_rejected' => 0, 'note' => null];
        }
        $this->svc->receiveGoods($po, [
            'received_date' => $this->base->copy()->subDays($d['receive'])->toDateString(), 'delivery_note_no' => 'SJ/'.$this->base->copy()->subDays($d['receive'])->format('ym').'/'.str_pad((string) (118 + $po->id * 3), 4, '0', STR_PAD_LEFT),
            'condition' => 'good', 'vendor_score' => $s['score'] ?? null, 'notes' => isset($s['receive_qty']) ? 'Sisa barang menyusul, vendor menjanjikan pengiriman minggu depan.' : 'Barang sesuai PO dan dalam kondisi baik.', 'lines' => $lines,
        ], $this->recv);

        if (! $reached('pay')) {
            return;
        }
        $this->at($d['pay'], '13:50');
        $po = $po->fresh();
        $this->svc->payVendor($po, (float) $po->total, $this->base->copy()->subDays($d['pay'])->toDateString(), 'transfer', 'PAY-'.$po->number, 'Pelunasan sesuai termin vendor.', $this->recv);

        if ($reached('settle') && isset($d['settle'])) {
            $this->at($d['settle'], '15:10');
            $this->svc->settleFunding($f->fresh(), 'LPJ lengkap: faktur, surat jalan, dan bukti transfer terlampir.', $this->recv);
        }

        if (! $reached('handover') || ! isset($d['handover'])) {
            return;
        }
        $this->at($d['handover'], '09:40');
        $h = $this->svc->handOver($pr->fresh(), $this->base->copy()->subDays($d['handover'])->toDateString(), $s['requester'].' ('.$s['unit'].')', 'Barang diserahkan lengkap beserta dokumen garansi.', $this->recv);

        if ($reached('confirm') && isset($d['confirm'])) {
            $this->at($d['confirm'], '11:00');
            $this->svc->confirmHandover($h->fresh(), $s['requester'].' (Inventaris)', null, 'Sistem Inventaris (API)');
        }
    }

    /** Beberapa catatan manual di buku laporan agar kedua meja terlihat hidup. */
    private function notes(): void
    {
        $pick = fn (string $ext) => PurchaseRequest::where('external_ref', $ext)->first();
        $urgent = $pick('INV-SHW-012');
        $mobil = $pick('INV-SHW-010');
        $rev = $pick('INV-SHW-011');
        $funding = $rev ? FundingRequest::where('purchase_request_id', $rev->id)->first() : null;

        if ($urgent) {
            $this->at(2, '16:10');
            $this->svc->report('inventaris', 'in', 'note', 'Mohon dipercepat: AC ruang server', 'Suhu ruang server kemarin sempat 33°C. Mohon penawaran dan pengadaan diprioritaskan.', $urgent, null, false, null);
        }
        if ($mobil) {
            $this->at(1, '10:05');
            $this->svc->report('inventaris', 'out', 'note', 'Pengadaan mobil dinas menunggu persetujuan Manager', 'Karena nilainya besar, pengajuan dana harus disetujui Manager Pengadaan sebelum diteruskan ke Keuangan. Estimasi keputusan 2 hari kerja.', $mobil, null, false, $this->purch);
        }
        if ($rev && $funding) {
            $this->at(3, '15:40');
            $this->svc->report('keuangan', 'out', 'note', 'Tanggapan atas permintaan revisi '.$funding->number, 'Kami sedang meminta penawaran pembanding dari dua vendor lain dan akan mengajukan ulang minggu ini.', $rev, $funding, false, $this->purch);
        }
        $this->resetClock();
    }
}
