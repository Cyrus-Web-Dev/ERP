<?php

/*
| Integrasi dengan PROCURA (Sistem Pengadaan) lewat REST API.
| Token & rahasia webhook dibuat oleh Manager PROCURA di menu "Integrasi API".
*/
return [
    // Contoh: http://127.0.0.1:8000/api/v1
    'url' => rtrim((string) env('PROCURA_URL', ''), '/'),
    'token' => env('PROCURA_TOKEN'),
    'webhook_secret' => env('PROCURA_WEBHOOK_SECRET'),
    'timeout' => (int) env('PROCURA_TIMEOUT', 10),

    // Pengajuan aktif yang belum disinkronkan lebih dari N menit ikut diperbarui oleh `php artisan procura:sync`.
    'sync_stale_minutes' => (int) env('PROCURA_SYNC_STALE_MINUTES', 10),

    'kategori' => [
        'elektronik' => 'Elektronik',
        'non_elektronik' => 'Non Elektronik',
        'perabotan' => 'Perabotan',
        'kendaraan' => 'Kendaraan',
        'gedung' => 'Gedung & Renovasi',
        'jasa' => 'Jasa',
        'habis_pakai' => 'Habis Pakai',
    ],

    'prioritas' => [
        'low' => ['Rendah', 'bg-slate-100 text-slate-600'],
        'normal' => ['Normal', 'bg-sky-100 text-sky-700'],
        'high' => ['Tinggi', 'bg-amber-100 text-amber-700'],
        'urgent' => ['Mendesak', 'bg-rose-100 text-rose-700'],
    ],

    // Stasiun jalur pengadaan (urutan tetap, sama dengan PROCURA).
    'stasiun' => [
        'submitted' => 'Diminta',
        'reviewing' => 'Ditinjau',
        'sourcing' => 'Penawaran',
        'funding' => 'Dana',
        'ordered' => 'Dipesan',
        'received' => 'Diterima',
        'handed_over' => 'Diserahkan',
    ],

    // status PROCURA => [label, kelas badge, kabar untuk pemohon]
    'status' => [
        'draft' => ['Belum terkirim', 'bg-slate-200 text-slate-700', 'Pengajuan tersimpan tetapi belum terkirim ke Pengadaan.'],
        'submitted' => ['Diterima Pengadaan', 'bg-sky-100 text-sky-700', 'Pengajuan diterima Bagian Pengadaan.'],
        'reviewing' => ['Sedang ditinjau', 'bg-indigo-100 text-indigo-700', 'Pengadaan sedang meninjau pengajuan Anda.'],
        'sourcing' => ['Mencari vendor', 'bg-violet-100 text-violet-700', 'Pengadaan sedang mencari dan membandingkan penawaran vendor.'],
        'funding' => ['Menunggu dana', 'bg-amber-100 text-amber-700', 'Pengadaan mengajukan dana ke Keuangan.'],
        'funded' => ['Dana cair', 'bg-teal-100 text-teal-700', 'Dana sudah cair, Pengadaan segera memesan ke vendor.'],
        'ordered' => ['Sudah dipesan', 'bg-blue-100 text-blue-700', 'Barang/jasa sudah dipesan ke vendor.'],
        'received' => ['Sudah diterima Pengadaan', 'bg-cyan-100 text-cyan-700', 'Barang/jasa sudah diterima Pengadaan, menunggu serah terima ke Inventaris.'],
        'handed_over' => ['Diserahkan — mohon konfirmasi', 'bg-emerald-100 text-emerald-700', 'Barang/jasa diserahkan ke Inventaris. Mohon konfirmasi penerimaan.'],
        'completed' => ['Selesai', 'bg-emerald-100 text-emerald-700', 'Pengadaan selesai.'],
        'rejected' => ['Ditolak', 'bg-rose-100 text-rose-700', 'Pengajuan ditolak Pengadaan.'],
        'cancelled' => ['Dibatalkan', 'bg-slate-200 text-slate-700', 'Pengajuan dibatalkan.'],
    ],

    // Kategori PROCURA => rute form aset di Inventaris (untuk "Catat ke data aset" setelah serah terima).
    'rute_aset' => [
        'elektronik' => ['elektronik.create', ['nama' => 'isi_nama', 'jumlah' => 'isi_jumlah', 'harga' => 'isi_harga']],
        'non_elektronik' => ['non-elektronik.create', ['nama' => 'isi_nama', 'jumlah' => 'isi_jumlah', 'harga' => 'isi_harga']],
        'habis_pakai' => ['non-elektronik.create', ['nama' => 'isi_nama', 'jumlah' => 'isi_jumlah', 'harga' => 'isi_harga']],
        'perabotan' => ['perabotan.create', ['nama' => 'isi_nama', 'jumlah' => 'isi_jumlah', 'harga' => 'isi_harga']],
        'kendaraan' => ['kendaraan.create', ['nama' => 'isi_nama', 'jumlah' => 'isi_jumlah', 'harga' => 'isi_harga']],
        'gedung' => ['bangunan.create', []],
    ],
];
