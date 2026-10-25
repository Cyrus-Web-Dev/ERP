<?php

return [

    /*
    | Pendaftaran akun:
    |   open   : siapa pun boleh mendaftar, tetapi akun baru MENUNGGU PERSETUJUAN Manager Pengadaan.
    |   closed : pendaftaran ditutup (kecuali belum ada user sama sekali).
    | Pendaftar PERTAMA otomatis menjadi Manager (admin).
    */
    'registration' => env('PROCURA_REGISTRATION', 'open'),

    // Admin awal yang dibuat oleh `php artisan migrate --seed` (opsional).
    'admin' => [
        'name' => env('PROCURA_ADMIN_NAME', 'Administrator'),
        'email' => env('PROCURA_ADMIN_EMAIL'),
        'password' => env('PROCURA_ADMIN_PASSWORD'),
    ],

    // Isi data contoh (vendor, katalog, beberapa permintaan) saat `migrate --seed`.
    'seed_demo' => (bool) env('PROCURA_SEED_DEMO', true),

    // Pengajuan dana dengan nilai >= ini WAJIB disetujui internal (Manager Pengadaan) sebelum dikirim ke Keuangan.
    'internal_approval_threshold' => (float) env('PROCURA_INTERNAL_APPROVAL_THRESHOLD', 25000000),

    // Tarif PPN bawaan untuk Purchase Order (persen).
    'default_tax_percent' => (float) env('PROCURA_DEFAULT_TAX', 11),

    // Berapa hari sebelum tanggal dibutuhkan sebuah permintaan mulai ditandai "mendesak".
    'due_soon_days' => (int) env('PROCURA_DUE_SOON_DAYS', 5),

    /*
    | Posisi kerja + hak akses AWAL yang otomatis tercentang saat Manager menyetujui staf.
    | Manager tetap bisa menambah/mengurangi centang per orang.
    */
    'positions' => [
        'purchasing' => ['label' => 'Purchasing', 'permissions' => ['request.manage', 'quotation.manage', 'po.manage', 'vendor.manage', 'catalog.manage']],
        'admin_desk' => ['label' => 'Admin Pengadaan', 'permissions' => ['request.manage', 'funding.submit']],
        'receiving' => ['label' => 'Penerimaan Barang', 'permissions' => ['receiving.manage', 'handover.manage']],
        'finance_liaison' => ['label' => 'Penghubung Keuangan', 'permissions' => ['funding.submit', 'payment.manage']],
        'inventaris_liaison' => ['label' => 'Penghubung Inventaris', 'permissions' => ['request.create', 'handover.confirm']],
        'keuangan_liaison' => ['label' => 'Penghubung Keuangan (keputusan dana)', 'permissions' => ['finance.decide', 'finance.disburse']],
        'lainnya' => ['label' => 'Lainnya', 'permissions' => []],
    ],

    // Hak akses yang bisa diberikan per orang (Manager selalu punya semuanya).
    'permission_labels' => [
        'request.manage' => 'Terima & proses permintaan inventaris',
        'quotation.manage' => 'Kelola penawaran vendor',
        'funding.submit' => 'Ajukan dana & catat keputusan Keuangan',
        'funding.approve' => 'Setujui pengajuan dana bernilai besar (internal)',
        'po.manage' => 'Buat & kirim Purchase Order',
        'payment.manage' => 'Catat pembayaran vendor & LPJ dana',
        'receiving.manage' => 'Catat penerimaan barang/jasa',
        'handover.manage' => 'Serah terima ke Inventaris',
        'vendor.manage' => 'Kelola data vendor',
        'catalog.manage' => 'Kelola katalog barang',
        'request.create' => 'Buat permintaan (sisi Inventaris)',
        'handover.confirm' => 'Konfirmasi serah terima (sisi Inventaris)',
        'finance.decide' => 'Putuskan pengajuan dana (sisi Keuangan)',
        'finance.disburse' => 'Cairkan dana (sisi Keuangan)',
    ],

    // Kategori barang/jasa (sejajar dengan modul aset di sistem Inventaris).
    'categories' => [
        'elektronik' => 'Elektronik',
        'non_elektronik' => 'Non Elektronik',
        'perabotan' => 'Perabotan',
        'kendaraan' => 'Kendaraan',
        'gedung' => 'Gedung & Renovasi',
        'jasa' => 'Jasa',
        'habis_pakai' => 'Habis Pakai',
    ],

    // Integrasi REST API dengan sistem lain (Inventaris, Keuangan, dst).
    'api' => [
        'rate_limit_per_minute' => (int) env('PROCURA_API_RATE_LIMIT', 120),
        'webhook_timeout' => (int) env('PROCURA_WEBHOOK_TIMEOUT', 8),
    ],

    'backup' => [
        'keep' => (int) env('PROCURA_BACKUP_KEEP', 10),
        'mysqldump_path' => env('PROCURA_MYSQLDUMP_PATH'),
        'pg_dump_path' => env('PROCURA_PG_DUMP_PATH'),
    ],
];
