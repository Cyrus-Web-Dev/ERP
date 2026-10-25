<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use App\Services\ProcurementService;
use Illuminate\Database\Seeder;

/** Data contoh (vendor, katalog, dan beberapa permintaan dari Inventaris) supaya aplikasi tidak kosong saat pertama dibuka. */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            ['Mitra Teknologi Nusantara', 'elektronik', 'Bapak Rahmat', 'sales@mitratek.example', '0751-555101', 14],
            ['CV Sinar Perabot Jaya', 'perabotan', 'Ibu Lestari', 'order@sinarperabot.example', '0751-555102', 7],
            ['PT Andalas Otomotif', 'kendaraan', 'Bapak Zulkifli', 'fleet@andalasoto.example', '0751-555103', 30],
            ['Karya Bangun Sumatera', 'gedung', 'Bapak Hendra', 'proyek@karyabangun.example', '0751-555104', 30],
            ['Prima ATK & Supplies', 'habis_pakai', 'Ibu Maya', 'cs@primaatk.example', '0751-555105', 7],
            ['Solusi Servis Prima', 'jasa', 'Bapak Doni', 'kontak@solusiservis.example', '0751-555106', 14],
        ];
        foreach ($vendors as $i => [$name, $cat, $pic, $email, $phone, $terms]) {
            Vendor::firstOrCreate(['code' => sprintf('VND-%04d', $i + 1)], [
                'name' => $name, 'category' => $cat, 'contact_person' => $pic, 'email' => $email, 'phone' => $phone,
                'payment_terms_days' => $terms, 'address' => 'Padang, Sumatera Barat', 'status' => 'active',
            ]);
        }

        $catalog = [
            ['ELK-001', 'Laptop kerja 14" (i5/16GB/512GB)', 'elektronik', 'unit', 12500000],
            ['ELK-002', 'Monitor 24" IPS', 'elektronik', 'unit', 1850000],
            ['ELK-003', 'Printer laser multifungsi', 'elektronik', 'unit', 3900000],
            ['PRB-001', 'Kursi kerja ergonomis', 'perabotan', 'unit', 1450000],
            ['PRB-002', 'Meja kerja 140x70 cm', 'perabotan', 'unit', 1750000],
            ['NEL-001', 'Lemari arsip besi 4 laci', 'non_elektronik', 'unit', 2300000],
            ['HBP-001', 'Kertas HVS A4 80gr (rim)', 'habis_pakai', 'rim', 52000],
            ['JSA-001', 'Servis berkala AC (per unit)', 'jasa', 'unit', 185000],
        ];
        foreach ($catalog as [$sku, $name, $cat, $unit, $price]) {
            CatalogItem::firstOrCreate(['sku' => $sku], ['name' => $name, 'category' => $cat, 'unit' => $unit, 'reference_price' => $price]);
        }

        if (PurchaseRequest::query()->exists()) {
            return;
        }
        $svc = app(ProcurementService::class);
        $svc->createRequest([
            'title' => 'Penggantian laptop staf Gudang & Aset', 'category' => 'elektronik', 'requester_name' => 'Rina Marlina', 'requester_unit' => 'Divisi Inventaris',
            'justification' => 'Tiga laptop lama sudah tidak bisa menjalankan aplikasi inventaris dengan lancar.', 'priority' => 'high',
            'needed_date' => now()->addDays(14)->toDateString(), 'external_ref' => 'INV-REQ-0231',
            'items' => [['name' => 'Laptop kerja 14"', 'specification' => 'i5, RAM 16GB, SSD 512GB', 'quantity' => 3, 'unit' => 'unit', 'estimated_price' => 12500000]],
        ], 'inventaris', null, 'Contoh data');
        $svc->createRequest([
            'title' => 'Kursi & meja ruang arsip', 'category' => 'perabotan', 'requester_name' => 'Andi Saputra', 'requester_unit' => 'Divisi Inventaris',
            'justification' => 'Ruang arsip baru belum punya perabot.', 'priority' => 'normal', 'needed_date' => now()->addDays(30)->toDateString(), 'external_ref' => 'INV-REQ-0232',
            'items' => [
                ['name' => 'Kursi kerja ergonomis', 'quantity' => 4, 'unit' => 'unit', 'estimated_price' => 1450000],
                ['name' => 'Meja kerja 140x70 cm', 'quantity' => 4, 'unit' => 'unit', 'estimated_price' => 1750000],
            ],
        ], 'inventaris', null, 'Contoh data');
        $svc->createRequest([
            'title' => 'Servis berkala AC gedung utama', 'category' => 'jasa', 'requester_name' => 'Rina Marlina', 'requester_unit' => 'Divisi Inventaris',
            'priority' => 'urgent', 'needed_date' => now()->addDays(4)->toDateString(), 'external_ref' => 'INV-REQ-0233',
            'items' => [['name' => 'Servis berkala AC', 'specification' => 'Cuci + cek freon', 'quantity' => 18, 'unit' => 'unit', 'estimated_price' => 185000]],
        ], 'inventaris', null, 'Contoh data');
    }
}
